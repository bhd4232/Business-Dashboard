<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CourierBooking;
use App\Models\CourierProvider;
use App\Models\Customer;
use App\Models\CustomerBlacklist;
use App\Models\CustomerRiskEvent;
use App\Models\CustomerRiskProfile;
use App\Models\CustomerRiskReview;
use App\Models\Order;
use App\Models\User;
use App\Services\CompanyContext;
use App\Services\CourierService;
use App\Services\CustomerRiskService;
use App\Services\CustomerRiskSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CustomerRiskTest extends TestCase
{
    use RefreshDatabase;

    public function test_rule_engine_calculates_ratios_score_and_explainable_factors(): void
    {
        [$company, $customer] = $this->customer('Risk Customer', '+8801700000001', 'Dhaka');
        $this->booking($this->order($company, $customer, 'RISK-1'), CourierBooking::STATUS_RETURNED);
        $this->booking($this->order($company, $customer, 'RISK-2'), CourierBooking::STATUS_RETURNED);
        $this->booking($this->order($company, $customer, 'RISK-3'), CourierBooking::STATUS_DELIVERED);

        $profile = app(CustomerRiskService::class)->evaluateCustomer($customer);

        $this->assertSame(3, $profile->total_courier_orders);
        $this->assertSame('33.33', $profile->success_ratio);
        $this->assertSame('66.67', $profile->return_ratio);
        $this->assertSame(CustomerRiskProfile::LEVEL_HIGH, $profile->risk_level);
        $this->assertArrayHasKey('high_return_ratio', $profile->factors);
        $this->assertArrayHasKey('low_success_ratio', $profile->factors);
    }

    public function test_order_check_stores_an_explainable_history_snapshot(): void
    {
        [$company, $customer] = $this->customer('First Order', '+8801700000002', 'Short');
        $order = $this->order($company, $customer, 'CHECK-1', 7000);

        $check = app(CustomerRiskService::class)->evaluateOrder($order);

        $this->assertDatabaseHas('fraud_checks', ['order_id' => $order->id, 'risk_score' => $check->risk_score]);
        $this->assertArrayHasKey('high_cod_first_order', $check->factors);
        $this->assertArrayHasKey('incomplete_address', $check->factors);
    }

    public function test_risk_rule_weights_are_configurable(): void
    {
        app(CustomerRiskSettingsService::class)->save([
            ...CustomerRiskSettingsService::DEFAULTS,
            'incomplete_address_deduction' => 35,
        ]);
        [$company, $customer] = $this->customer('Configurable Risk', '+8801700000012', 'Short');
        $order = $this->order($company, $customer, 'CONFIG-1', 1000);

        $check = app(CustomerRiskService::class)->evaluateOrder($order);

        $this->assertSame(35, $check->factors['incomplete_address']['deduction']);
    }

    public function test_same_phone_with_different_customer_name_is_flagged(): void
    {
        [$company, $customer] = $this->customer('Original Name', '+8801700000021', 'Complete Dhaka Address');
        app(CompanyContext::class)->set($company);
        Customer::query()->create(['name' => 'Different Name', 'phone' => '+8801700000021', 'address' => 'Complete Dhaka Address', 'opening_balance' => 0, 'is_active' => true]);

        $profile = app(CustomerRiskService::class)->evaluateCustomer($customer);

        $this->assertArrayHasKey('phone_multiple_names', $profile->factors);
    }

    public function test_duplicate_order_within_a_day_for_same_amount_is_flagged(): void
    {
        [$company, $customer] = $this->customer('Duplicate Customer', '+8801700000022', 'Complete Dhaka Address');
        $this->order($company, $customer, 'DUP-1', 1500);
        $secondOrder = $this->order($company, $customer, 'DUP-2', 1500);

        $check = app(CustomerRiskService::class)->evaluateOrder($secondOrder);

        $this->assertArrayHasKey('recent_duplicate_order', $check->factors);
    }

    public function test_global_blacklist_blocks_courier_booking(): void
    {
        [$company, $customer] = $this->customer('Blocked Customer', '+8801700000003', 'Full Dhaka Address');
        $order = $this->order($company, $customer, 'BLOCK-1');
        CustomerBlacklist::query()->create(['phone' => $customer->phone, 'reason' => 'Confirmed abusive delivery history.', 'is_active' => true]);

        $this->expectException(ValidationException::class);
        app(CourierService::class)->createManualBooking($order);
    }

    public function test_high_risk_order_requires_review_before_courier_booking(): void
    {
        [$company, $customer] = $this->customer('Approval Customer', '+8801700000013', 'Dhaka');
        $order = $this->order($company, $customer, 'APPROVE-1', 7000);
        $this->booking($this->order($company, $customer, 'APPROVE-2'), CourierBooking::STATUS_RETURNED);
        $this->booking($this->order($company, $customer, 'APPROVE-3'), CourierBooking::STATUS_RETURNED);
        $this->booking($this->order($company, $customer, 'APPROVE-4'), CourierBooking::STATUS_DELIVERED);

        try {
            app(CourierService::class)->createManualBooking($order);
            $this->fail('High-risk booking should require approval.');
        } catch (ValidationException) {
            $this->assertDatabaseHas('customer_risk_reviews', [
                'order_id' => $order->id,
                'approval_type' => CustomerRiskReview::TYPE_MANAGER,
                'status' => CustomerRiskReview::STATUS_PENDING,
            ]);
        }

        app(CustomerRiskService::class)->approveReview(CustomerRiskReview::query()->where('order_id', $order->id)->firstOrFail(), 'Called and confirmed.');

        $booking = app(CourierService::class)->createManualBooking($order);

        $this->assertSame($order->id, $booking->order_id);
    }

    public function test_terminal_delivery_status_creates_one_risk_event_and_refreshes_profile(): void
    {
        [$company, $customer] = $this->customer('Event Customer', '+8801700000004', 'Complete Dhaka Address');
        $order = $this->order($company, $customer, 'EVENT-1');
        $booking = $this->booking($order, CourierBooking::STATUS_BOOKED);

        app(CourierService::class)->updateStatus($booking, CourierBooking::STATUS_DELIVERED, 'Delivered.');
        app(CustomerRiskService::class)->recordDeliveryEvent($order, CourierBooking::STATUS_DELIVERED);

        $this->assertSame(1, CustomerRiskEvent::query()->count());
        $this->assertSame(1, CustomerRiskProfile::query()->first()->delivered_orders);
    }

    public function test_risk_profile_and_blacklist_admin_pages_render(): void
    {
        [$company, $customer] = $this->customer('Admin Risk Customer', '+8801700000005', 'Complete Dhaka Address');
        app(CustomerRiskService::class)->evaluateCustomer($customer);
        $user = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);

        $this->actingAs($user)->withSession(['current_company_id' => $company->id])
            ->get('/admin/customer-success/customer-risk-profiles')->assertOk()->assertSee('Admin Risk Customer');
        $this->actingAs($user)->withSession(['current_company_id' => $company->id])
            ->get('/admin/customer-success/customer-blacklists')->assertOk();
        $this->actingAs($user)->withSession(['current_company_id' => $company->id])
            ->get('/admin/customer-success/customer-risk-reviews')->assertOk();
        $this->actingAs($user)->withSession(['current_company_id' => $company->id])
            ->get('/admin/customer-success/customer-risk-events')->assertOk();
        $this->actingAs($user)->withSession(['current_company_id' => $company->id])
            ->get('/admin/customer-success/customer-risk-settings')->assertOk()->assertSee('Rule thresholds and deductions');
    }

    protected function customer(string $name, string $phone, string $address): array
    {
        $company = Company::query()->create(['name' => $name.' Company', 'slug' => str($name)->slug(), 'invoice_prefix' => 'RSK', 'currency' => 'BDT', 'timezone' => 'Asia/Dhaka', 'is_active' => true]);
        app(CompanyContext::class)->set($company);
        $customer = Customer::query()->create(['name' => $name, 'phone' => $phone, 'address' => $address, 'opening_balance' => 0, 'is_active' => true]);

        return [$company, $customer];
    }

    protected function order(Company $company, Customer $customer, string $number, float $due = 500): Order
    {
        return Order::withoutEvents(fn () => Order::query()->create(['company_id' => $company->id, 'order_number' => $number, 'customer_id' => $customer->id, 'customer_name' => $customer->name, 'order_date' => now(), 'subtotal' => $due, 'total_amount' => $due, 'paid_amount' => 0, 'due_amount' => $due, 'discount' => 0, 'vat' => 0, 'status' => 'draft']));
    }

    protected function booking(Order $order, string $status): CourierBooking
    {
        $provider = CourierProvider::query()->firstOrCreate(['company_id' => $order->company_id, 'slug' => 'risk-manual'], ['name' => 'Risk Manual', 'driver' => 'manual', 'credentials' => [], 'settings' => [], 'is_active' => true]);

        return CourierBooking::query()->create(['company_id' => $order->company_id, 'courier_provider_id' => $provider->id, 'order_id' => $order->id, 'tracking_id' => 'TRK-'.$order->id, 'recipient_name' => $order->customer_name, 'cod_amount' => $order->due_amount, 'status' => $status]);
    }
}
