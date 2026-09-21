<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ResellerCommission;
use App\Models\ResellerProduct;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\CompanyContext;
use App\Services\CompanyStorageService;
use App\Services\OrderStatusWorkflowService;
use App\Services\Payouts\PayoutBatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use RuntimeException;
use Tests\TestCase;

class ResellerCommissionPayoutBatchTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $user;

    private Product $product;

    private Customer $buyer;

    private PayoutBatchService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::query()->create([
            'name' => 'Reseller Payout Co', 'slug' => 'reseller-payout-co', 'invoice_prefix' => 'RPC',
            'currency' => 'BDT', 'timezone' => 'Asia/Dhaka', 'is_active' => true,
        ]);
        app(CompanyContext::class)->set($this->company);
        $this->user = User::factory()->create(['role' => 'super_admin']);
        Auth::login($this->user);
        $this->service = app(PayoutBatchService::class);

        $this->product = Product::query()->create([
            'name' => 'Widget', 'sku' => 'WID-1', 'price' => 500, 'sale_price' => 500, 'stock' => 0,
        ]);
        StockMovement::query()->create(['product_id' => $this->product->id, 'type' => 'opening', 'quantity' => 1000]);
        $this->buyer = Customer::query()->create(['name' => 'Test Buyer', 'phone' => '01899999999']);
    }

    public function test_batch_only_includes_payable_commissions_with_matching_method_and_complete_details(): void
    {
        $bankCommission = $this->payableCommission('bank', ['bank_name' => 'Test Bank', 'branch' => 'Main', 'routing_number' => '123456789', 'account_number' => '00112233']);
        $this->payableCommission('mfs_bkash', ['msisdn' => '01710000000']); // different method, must be excluded
        $this->payableCommission('bank', []); // incomplete bank details, must be excluded

        $batch = $this->service->createBatchFromPendingResellerCommissions($this->company, 'bank', $this->user);

        $this->assertSame(1, $batch->total_items);
        $this->assertSame('beftn_bank', $batch->method);
        $this->assertSame(ResellerCommission::class, $batch->items->first()->payable_type);
        $this->assertSame($bankCommission->id, $batch->items->first()->payable_id);
    }

    public function test_throws_when_no_commission_matches_the_method(): void
    {
        $this->payableCommission('bank', ['bank_name' => 'Test Bank', 'branch' => 'Main', 'routing_number' => '123456789', 'account_number' => '00112233']);

        $this->expectException(RuntimeException::class);
        $this->service->createBatchFromPendingResellerCommissions($this->company, 'mfs_nagad', $this->user);
    }

    public function test_full_lifecycle_marks_the_commission_paid(): void
    {
        $commission = $this->payableCommission('bank', ['bank_name' => 'Test Bank', 'branch' => 'Main', 'routing_number' => '123456789', 'account_number' => '00112233']);

        $batch = $this->service->createBatchFromPendingResellerCommissions($this->company, 'bank', $this->user);
        $batch = $this->service->approve($batch, $this->user);
        $batch = $this->service->generateExportFile($batch);
        $batch = $this->service->markSubmitted($batch, 'BANK-REF-01');

        $this->service->reconcileItem($batch->items->first(), 'paid', 'UTR-1');

        $this->assertSame('completed', $batch->fresh()->status);
        $this->assertSame('paid', $commission->fresh()->status);
        $this->assertNotNull($commission->fresh()->paid_at);
    }

    public function test_mfs_batch_carries_the_msisdn_and_uses_the_mfs_formatter(): void
    {
        $this->payableCommission('mfs_bkash', ['msisdn' => '01710000000']);

        $batch = $this->service->createBatchFromPendingResellerCommissions($this->company, 'mfs_bkash', $this->user);

        $this->assertSame('mfs_bkash', $batch->method);
        $this->assertSame('01710000000', $batch->items->first()->recipient_mfs_number);

        $batch = $this->service->approve($batch, $this->user);
        $batch = $this->service->generateExportFile($batch);

        $this->assertStringContainsString('01710000000', app(CompanyStorageService::class)->readPrivate($batch->export_file_path, $this->company));
    }

    private function payableCommission(string $method, array $payoutDetails): ResellerCommission
    {
        static $sequence = 0;
        $sequence++;

        $reseller = Customer::query()->create([
            'name' => "Reseller {$sequence}",
            'phone' => "0181000{$sequence}00",
            'reseller_status' => 'approved',
            'reseller_payout_method' => $method,
            'reseller_payout_details' => $payoutDetails,
        ]);
        ResellerProduct::query()->create([
            'customer_id' => $reseller->id, 'product_id' => $this->product->id,
            'is_active' => true, 'wholesale_rate' => 350,
        ]);

        $order = Order::query()->create(['reseller_customer_id' => $reseller->id, 'customer_id' => $this->buyer->id, 'paid_amount' => 0, 'status' => Order::STATUS_DRAFT]);
        OrderItem::query()->create(['order_id' => $order->id, 'product_id' => $this->product->id, 'quantity' => 1, 'unit_price' => 500]);

        $workflow = app(OrderStatusWorkflowService::class);
        $order = $workflow->transition($order, Order::STAGE_CONFIRMED);
        $order = $workflow->transition($order, Order::STAGE_PROCESSING);
        $workflow->transition($order, Order::STAGE_DELIVERED);

        $commission = ResellerCommission::query()->where('order_id', $order->id)->firstOrFail();
        $commission->update(['status' => 'payable', 'holding_until' => now()->subDay()->toDateString()]);

        return $commission->fresh();
    }
}
