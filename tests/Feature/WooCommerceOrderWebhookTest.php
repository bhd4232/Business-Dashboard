<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\StorefrontSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class WooCommerceOrderWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_valid_order_created_webhook_creates_an_order_with_matched_items(): void
    {
        $company = $this->createCompanyWithWebhookSecret('woo-secret-1');
        $product = $this->createProduct($company, 'Webhook Product', 'WOO-SKU-1');

        $payload = $this->orderPayload(id: 501, sku: $product->sku);

        $response = $this->postWebhook($company, $payload, 'order.created', 'woo-secret-1');

        $response->assertOk()->assertJson(['ok' => true]);

        $order = Order::query()->where('company_id', $company->getKey())->where('external_reference', 'woo-501')->first();

        $this->assertNotNull($order);
        $this->assertSame(Order::SOURCE_WOOCOMMERCE, $order->source);
        $this->assertSame(Order::STATUS_PROCESSING, $order->status);
        $this->assertSame(1, $order->items()->count());
        $this->assertSame($product->getKey(), $order->items()->first()->product_id);
        $this->assertSame('Woo Customer', $order->customer_name);

        $customer = Customer::query()->where('phone', '01700000099')->first();
        $this->assertNotNull($customer);
        $this->assertSame($order->customer_id, $customer->getKey());
    }

    public function test_a_redelivered_webhook_updates_the_same_order_instead_of_duplicating_it(): void
    {
        $company = $this->createCompanyWithWebhookSecret('woo-secret-2');
        $product = $this->createProduct($company, 'Webhook Product 2', 'WOO-SKU-2');

        $payload = $this->orderPayload(id: 502, sku: $product->sku, status: 'pending');
        $this->postWebhook($company, $payload, 'order.created', 'woo-secret-2')->assertOk();

        $updatedPayload = $this->orderPayload(id: 502, sku: $product->sku, status: 'completed');
        $this->postWebhook($company, $updatedPayload, 'order.updated', 'woo-secret-2')->assertOk();

        $orders = Order::query()->where('company_id', $company->getKey())->where('external_reference', 'woo-502')->get();

        $this->assertCount(1, $orders);
        $this->assertSame(Order::STATUS_COMPLETED, $orders->first()->status);
    }

    public function test_a_line_item_with_no_matching_sku_is_skipped_without_failing_the_whole_order(): void
    {
        $company = $this->createCompanyWithWebhookSecret('woo-secret-3');
        $product = $this->createProduct($company, 'Matched Product', 'WOO-SKU-3');

        $payload = $this->orderPayload(id: 503, sku: $product->sku);
        $payload['line_items'][] = [
            'sku' => 'DOES-NOT-EXIST',
            'name' => 'Unknown product',
            'quantity' => 1,
            'total' => '50.00',
        ];

        $response = $this->postWebhook($company, $payload, 'order.created', 'woo-secret-3');

        $response->assertOk();

        $order = Order::query()->where('company_id', $company->getKey())->where('external_reference', 'woo-503')->first();

        $this->assertNotNull($order);
        $this->assertSame(1, $order->items()->count());
    }

    public function test_an_invalid_signature_is_rejected(): void
    {
        Log::spy();

        $company = $this->createCompanyWithWebhookSecret('woo-secret-4');
        $payload = $this->orderPayload(id: 504, sku: 'WHATEVER');

        $response = $this->post(route('woocommerce.webhook', $company), $payload, [
            'X-WC-Webhook-Topic' => 'order.created',
            'X-WC-Webhook-Signature' => base64_encode(hash_hmac('sha256', json_encode($payload), 'wrong-secret', true)),
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('orders', ['external_reference' => 'woo-504']);

        // A rejected delivery must leave a forensic trail — this exact log
        // line is what let a real production 403 get diagnosed at all
        // (WooCommerce's own delivery-failure banner only ever shows the
        // status code, never why).
        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(fn (string $message, array $context): bool => $message === 'WooCommerce webhook rejected: signature does not match the secret saved for this company.'
                && $context['company'] === $company->getKey()
                && $context['signature_header_present'] === true);
    }

    public function test_a_webhook_secret_cannot_be_used_against_a_different_companys_route(): void
    {
        $companyA = $this->createCompanyWithWebhookSecret('woo-secret-a');
        $companyB = $this->createCompanyWithWebhookSecret('woo-secret-b');

        $payload = $this->orderPayload(id: 505, sku: 'WHATEVER');

        // Signed with company A's secret but posted to company B's delivery URL.
        $response = $this->postWebhook($companyB, $payload, 'order.created', 'woo-secret-a');

        $response->assertForbidden();
        $this->assertDatabaseMissing('orders', ['company_id' => $companyB->getKey(), 'external_reference' => 'woo-505']);
    }

    public function test_order_deleted_topic_cancels_the_order_instead_of_deleting_it(): void
    {
        $company = $this->createCompanyWithWebhookSecret('woo-secret-6');
        $product = $this->createProduct($company, 'Webhook Product 6', 'WOO-SKU-6');

        $this->postWebhook($company, $this->orderPayload(id: 506, sku: $product->sku), 'order.created', 'woo-secret-6')->assertOk();

        $this->postWebhook($company, ['id' => 506], 'order.deleted', 'woo-secret-6')->assertOk();

        $order = Order::query()->where('company_id', $company->getKey())->where('external_reference', 'woo-506')->first();

        $this->assertNotNull($order);
        $this->assertSame(Order::STATUS_CANCELLED, $order->status);
    }

    private function postWebhook(Company $company, array $payload, string $topic, string $secret)
    {
        $body = json_encode($payload);

        return $this->call(
            'POST',
            route('woocommerce.webhook', $company),
            server: [
                'HTTP_X-WC-Webhook-Topic' => $topic,
                'HTTP_X-WC-Webhook-Signature' => base64_encode(hash_hmac('sha256', $body, $secret, true)),
                'CONTENT_TYPE' => 'application/json',
            ],
            content: $body,
        );
    }

    private function orderPayload(int $id, string $sku, string $status = 'processing'): array
    {
        return [
            'id' => $id,
            'number' => (string) $id,
            'status' => $status,
            'date_created' => '2026-08-20T10:00:00',
            'discount_total' => '0.00',
            'total_tax' => '0.00',
            'shipping_total' => '0.00',
            'billing' => [
                'first_name' => 'Woo',
                'last_name' => 'Customer',
                'phone' => '01700000099',
                'email' => 'woo-customer@example.test',
                'address_1' => '123 Test Road',
                'city' => 'Dhaka',
            ],
            'line_items' => [
                [
                    'sku' => $sku,
                    'name' => 'Line item',
                    'quantity' => 2,
                    'total' => '200.00',
                ],
            ],
        ];
    }

    private function createCompanyWithWebhookSecret(string $secret): Company
    {
        $company = Company::query()->create([
            'name' => 'Woo Webhook Company '.$secret,
            'slug' => 'woo-webhook-company-'.$secret,
            'invoice_prefix' => strtoupper(substr($secret, -4)),
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
        ]);

        StorefrontSetting::query()->create([
            'company_id' => $company->getKey(),
            'woocommerce_base_url' => 'https://example-store.test',
            'woocommerce_credentials' => [
                'consumer_key' => 'ck_test',
                'consumer_secret' => 'cs_test',
                'webhook_secret' => $secret,
            ],
        ]);

        return $company;
    }

    private function createProduct(Company $company, string $name, string $sku): Product
    {
        $category = Category::query()->create([
            'company_id' => $company->getKey(),
            'name' => 'Webhook Category',
            'slug' => 'webhook-category-'.$company->getKey(),
        ]);

        $product = Product::query()->create([
            'company_id' => $company->getKey(),
            'name' => $name,
            'sku' => $sku,
            'unit' => 'pcs',
            'cost_price' => 50,
            'sale_price' => 100,
            'price' => 100,
            'stock' => 20,
            'reorder_level' => 2,
            'is_active' => true,
            'category_id' => $category->id,
        ]);

        StockMovement::query()->create([
            'company_id' => $company->getKey(),
            'product_id' => $product->id,
            'type' => 'opening',
            'quantity' => 20,
            'reference_type' => Product::class,
            'reference_id' => $product->id,
            'note' => 'Webhook test opening stock',
        ]);

        return $product;
    }
}
