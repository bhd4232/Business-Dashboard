<?php

namespace Tests\Feature;

use App\Jobs\PushWooCommerceOrderStatusJob;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Order;
use App\Models\StorefrontSetting;
use App\Services\WooCommerceOrderSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Covers the ERP -> WooCommerce direction of the order sync (the reverse of
 * WooCommerceOrderWebhookTest's WooCommerce -> ERP webhook ingestion): an
 * admin changing a WooCommerce-sourced order's status in the ERP pushes that
 * status back to WooCommerce via its REST API, and the two directions never
 * ping-pong each other.
 */
class WooCommerceOrderStatusPushTest extends TestCase
{
    use RefreshDatabase;

    public function test_changing_a_woocommerce_orders_status_in_the_erp_queues_a_push_job(): void
    {
        Queue::fake();

        $order = $this->createWooCommerceOrder();

        $order->update(['status' => Order::STATUS_COMPLETED]);

        Queue::assertPushed(
            PushWooCommerceOrderStatusJob::class,
            fn (PushWooCommerceOrderStatusJob $job): bool => $job->orderId === $order->getKey(),
        );
    }

    public function test_changing_a_non_woocommerce_orders_status_never_queues_a_push(): void
    {
        Queue::fake();

        $company = $this->createCompany();
        $customer = Customer::query()->create(['company_id' => $company->getKey(), 'name' => 'Admin Customer']);
        $order = Order::query()->create([
            'company_id' => $company->getKey(),
            'customer_id' => $customer->getKey(),
            'source' => Order::SOURCE_ADMIN,
            'status' => Order::STATUS_DRAFT,
        ]);

        $order->update(['status' => Order::STATUS_CONFIRMED]);

        Queue::assertNotPushed(PushWooCommerceOrderStatusJob::class);
    }

    /**
     * The exact loop WooCommerceOrderSyncService::upsertOrder()/
     * markCancelled() must prevent: a status change that just arrived FROM
     * a WooCommerce webhook must never immediately queue a push of that
     * same status straight back.
     */
    public function test_a_status_change_synced_in_from_a_webhook_does_not_queue_a_push_back(): void
    {
        Queue::fake();

        $order = $this->createWooCommerceOrder();
        $order->suppressWooCommercePush = true;
        $order->update(['status' => Order::STATUS_COMPLETED]);

        Queue::assertNotPushed(PushWooCommerceOrderStatusJob::class);
    }

    public function test_push_status_to_woocommerce_sends_the_mapped_status_via_rest_api(): void
    {
        Http::fake(['*' => Http::response(['id' => 501], 200)]);

        $order = $this->createWooCommerceOrder();
        $order->suppressWooCommercePush = true;
        $order->update(['status' => Order::STATUS_COMPLETED]);

        app(WooCommerceOrderSyncService::class)->pushStatusToWooCommerce($order->fresh());

        Http::assertSent(function ($request): bool {
            return $request->method() === 'PUT'
                && str_contains($request->url(), 'wp-json/wc/v3/orders/501')
                && $request['status'] === 'completed'
                && $request->hasHeader('Authorization');
        });
    }

    public function test_push_status_to_woocommerce_is_a_safe_no_op_without_configured_credentials(): void
    {
        Http::fake();

        $company = $this->createCompany();
        // No StorefrontSetting row at all for this company.
        $customer = Customer::query()->create(['company_id' => $company->getKey(), 'name' => 'No Creds Customer']);
        $order = Order::query()->create([
            'company_id' => $company->getKey(),
            'customer_id' => $customer->getKey(),
            'source' => Order::SOURCE_WOOCOMMERCE,
            'external_reference' => 'woo-999',
            'status' => Order::STATUS_COMPLETED,
        ]);

        app(WooCommerceOrderSyncService::class)->pushStatusToWooCommerce($order);

        Http::assertNothingSent();
    }

    public function test_push_status_to_woocommerce_is_a_safe_no_op_for_an_admin_sourced_order(): void
    {
        Http::fake();

        $company = $this->createCompany();
        $customer = Customer::query()->create(['company_id' => $company->getKey(), 'name' => 'Admin Customer']);
        $order = Order::query()->create([
            'company_id' => $company->getKey(),
            'customer_id' => $customer->getKey(),
            'source' => Order::SOURCE_ADMIN,
            'status' => Order::STATUS_COMPLETED,
        ]);

        app(WooCommerceOrderSyncService::class)->pushStatusToWooCommerce($order);

        Http::assertNothingSent();
    }

    protected function createCompany(): Company
    {
        return Company::query()->create([
            'name' => 'Status Push Co '.uniqid(),
            'slug' => 'status-push-co-'.uniqid(),
            'invoice_prefix' => 'SPC'.random_int(100, 999),
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
        ]);
    }

    protected function createWooCommerceOrder(): Order
    {
        $company = $this->createCompany();

        StorefrontSetting::query()->create([
            'company_id' => $company->getKey(),
            'woocommerce_base_url' => 'https://example-store.test',
            'woocommerce_credentials' => [
                'consumer_key' => 'ck_test',
                'consumer_secret' => 'cs_test',
                'webhook_secret' => 'whatever',
            ],
        ]);

        $customer = Customer::query()->create(['company_id' => $company->getKey(), 'name' => 'Woo Customer']);

        return Order::query()->create([
            'company_id' => $company->getKey(),
            'customer_id' => $customer->getKey(),
            'source' => Order::SOURCE_WOOCOMMERCE,
            'external_reference' => 'woo-501',
            'status' => Order::STATUS_PROCESSING,
        ]);
    }
}
