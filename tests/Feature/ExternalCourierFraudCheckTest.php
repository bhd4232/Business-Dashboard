<?php

namespace Tests\Feature;

use App\Jobs\CheckExternalCourierFraudJob;
use App\Models\Company;
use App\Models\CourierProvider;
use App\Models\Customer;
use App\Models\CustomerRiskEvent;
use App\Models\CustomerRiskReview;
use App\Models\Order;
use App\Services\CompanyContext;
use App\Services\ExternalCourierFraudService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ExternalCourierFraudCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_check_by_phone_skips_couriers_without_fraud_check_credentials(): void
    {
        [$company] = $this->companyWithProvider(withCredentials: false);

        $result = app(ExternalCourierFraudService::class)->checkByPhone('01712345678', $company->id);

        $this->assertArrayNotHasKey('steadfast', $result);
        $this->assertNull($result['overall_success_ratio']);
    }

    public function test_check_by_phone_combines_courier_stats_and_logs_an_audit_event(): void
    {
        [$company] = $this->companyWithProvider(withCredentials: true);

        Http::fake($this->fakeSteadfastPortal(delivered: 5, cancelled: 2));

        $result = app(ExternalCourierFraudService::class)->checkByPhone('01712345678', $company->id);

        $this->assertSame(5, $result['steadfast']['success']);
        $this->assertSame(2, $result['steadfast']['cancel']);
        $this->assertSame(71.43, $result['overall_success_ratio']);
        $this->assertDatabaseHas('customer_risk_events', [
            'company_id' => $company->id,
            'event_type' => 'external_courier_fraud_check',
        ]);
    }

    public function test_check_by_phone_normalizes_international_bd_numbers_to_local_format(): void
    {
        [$company] = $this->companyWithProvider(withCredentials: true);

        Http::fake($this->fakeSteadfastPortal(delivered: 3, cancelled: 1));

        $result = app(ExternalCourierFraudService::class)->checkByPhone('+8801712345678', $company->id);

        $this->assertSame(3, $result['steadfast']['success']);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/user/frauds/check/01712345678'));
    }

    public function test_check_by_phone_caches_result_and_does_not_log_twice(): void
    {
        [$company] = $this->companyWithProvider(withCredentials: true);

        Http::fake($this->fakeSteadfastPortal(delivered: 1, cancelled: 0));

        $service = app(ExternalCourierFraudService::class);
        $service->checkByPhone('01712345678', $company->id);
        $service->checkByPhone('01712345678', $company->id);

        $this->assertSame(1, CustomerRiskEvent::query()->where('event_type', 'external_courier_fraud_check')->count());
    }

    public function test_check_by_phone_bypasses_cache_when_requested(): void
    {
        [$company] = $this->companyWithProvider(withCredentials: true);

        Http::fake($this->fakeSteadfastPortal(delivered: 1, cancelled: 0));

        $service = app(ExternalCourierFraudService::class);
        $service->checkByPhone('01712345678', $company->id);
        $service->checkByPhone('01712345678', $company->id, bypassCache: true);

        // A cached call makes no request; bypassing it must re-hit the portal,
        // e.g. so newly added courier credentials take effect immediately.
        Http::assertSentCount(2 * 5);
    }

    public function test_low_external_success_ratio_requests_a_manager_review_without_blocking_checkout(): void
    {
        $company = Company::query()->create(['name' => 'Fraud Co', 'slug' => 'fraud-co', 'invoice_prefix' => 'FRD', 'currency' => 'BDT', 'timezone' => 'Asia/Dhaka', 'is_active' => true]);
        app(CompanyContext::class)->set($company);

        CourierProvider::query()->create([
            'company_id' => $company->id,
            'name' => 'Steadfast',
            'slug' => 'steadfast',
            'driver' => CourierProvider::DRIVER_STEADFAST,
            'credentials' => ['fraud_check' => ['username' => 'owner@example.com', 'password' => 'secret']],
            'settings' => [],
            'is_active' => true,
        ]);

        $customer = Customer::query()->create(['name' => 'Risky Buyer', 'phone' => '01712345678', 'address' => 'Dhaka', 'opening_balance' => 0, 'is_active' => true]);
        $order = Order::withoutEvents(fn () => Order::query()->create([
            'company_id' => $company->id, 'order_number' => 'FRD-1', 'customer_id' => $customer->id, 'customer_name' => $customer->name,
            'order_date' => now(), 'subtotal' => 500, 'total_amount' => 500, 'paid_amount' => 0, 'due_amount' => 500, 'discount' => 0, 'vat' => 0, 'status' => 'draft',
        ]));

        Http::fake($this->fakeSteadfastPortal(delivered: 1, cancelled: 9));

        (new CheckExternalCourierFraudJob($order->id))->handle(
            app(ExternalCourierFraudService::class),
            app(\App\Services\CustomerRiskService::class),
            app(\App\Services\CustomerRiskSettingsService::class),
        );

        $this->assertDatabaseHas('customer_risk_reviews', [
            'order_id' => $order->id,
            'status' => CustomerRiskReview::STATUS_PENDING,
        ]);
    }

    protected function fakeSteadfastPortal(int $delivered, int $cancelled): \Closure
    {
        return function ($request) use ($delivered, $cancelled) {
            $url = $request->url();
            $method = $request->method();

            if (str_contains($url, '/user/frauds/check/')) {
                return Http::response(['total_delivered' => $delivered, 'total_cancelled' => $cancelled]);
            }

            if (str_contains($url, '/user/frauds/check')) {
                return Http::response('<meta name="csrf-token" content="tok">');
            }

            if (str_contains($url, '/logout')) {
                return Http::response('');
            }

            if ($method === 'GET' && str_contains($url, '/login')) {
                return Http::response('<input type="hidden" name="_token" value="tok">');
            }

            if ($method === 'POST' && str_contains($url, '/login')) {
                return Http::response('', 302);
            }

            return Http::response('', 404);
        };
    }

    protected function companyWithProvider(bool $withCredentials): array
    {
        $company = Company::query()->create(['name' => 'Fraud Test Co', 'slug' => 'fraud-test-co-'.uniqid(), 'invoice_prefix' => 'FTC', 'currency' => 'BDT', 'timezone' => 'Asia/Dhaka', 'is_active' => true]);
        app(CompanyContext::class)->set($company);

        CourierProvider::query()->create([
            'company_id' => $company->id,
            'name' => 'Steadfast',
            'slug' => 'steadfast',
            'driver' => CourierProvider::DRIVER_STEADFAST,
            'credentials' => $withCredentials
                ? ['fraud_check' => ['username' => 'owner@example.com', 'password' => 'secret']]
                : [],
            'settings' => [],
            'is_active' => true,
        ]);

        return [$company];
    }
}
