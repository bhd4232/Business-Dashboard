<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CourierBooking;
use App\Models\CourierProvider;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\CompanySettingsService;
use App\Support\Code128;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Redesigned printable invoice (v1.20.0): barcode, delivery partner, weight
 * column, contact strip, courier cut-slip, and admin-configurable invoice
 * settings.
 */
class InvoiceDesignTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create([
            'role' => 'super_admin',
            'is_active' => true,
        ]);
    }

    private function makeOrder(array $orderAttributes = [], array $productAttributes = []): Order
    {
        $customer = Customer::query()->create([
            'name' => 'Shakil',
            'phone' => '+8801828076292',
            'address' => 'K-195/1, Khilkhet, Dhaka-1229',
        ]);

        $product = Product::query()->create(array_merge([
            'name' => 'Solar Fan with Power Bank',
            'sku' => 'INV-DESIGN-001',
            'price' => 1600,
            'sale_price' => 1600,
            'stock' => 500,
            'weight_kg' => 1.8,
        ], $productAttributes));

        $order = Order::query()->create(array_merge([
            'customer_id' => $customer->id,
            'order_date' => '2026-07-12',
            'status' => 'draft',
        ], $orderAttributes));

        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 1600,
        ]);

        return $order->refresh();
    }

    public function test_invoice_shows_barcode_weight_delivery_partner_and_cut_slip(): void
    {
        app(CompanySettingsService::class)->saveInvoice([
            'hotline' => '01811754232',
            'support_hotline' => '01894449445',
            'facebook_label' => 'fb.com/zamzamintl',
            'whatsapp' => '01678413888',
            'website' => 'https://zamzamint.com',
            'thank_you' => 'Thank You For Purchasing From Us.',
        ]);

        $order = $this->makeOrder();

        $provider = CourierProvider::query()->create([
            'name' => 'Steadfast',
            'driver' => 'steadfast',
            'is_active' => true,
        ]);

        CourierBooking::query()->create([
            'order_id' => $order->id,
            'courier_provider_id' => $provider->id,
            'status' => 'booked',
            'recipient_name' => 'Shakil',
            'recipient_phone' => '+8801828076292',
            'recipient_address' => 'K-195/1, Khilkhet, Dhaka-1229',
            'cod_amount' => 3200,
        ]);

        $this->actingAs($this->admin())
            ->get(route('orders.print', $order))
            ->assertOk()
            ->assertSee('Hotline: 01811754232')
            ->assertSee('Delivery Partner:')
            ->assertSee('Steadfast')
            ->assertSee('1.8 kg')
            ->assertSee('Item Name')
            ->assertSee('Weight')
            ->assertSee('Grand Total')
            ->assertSee('Due Amount')
            ->assertSee('fb.com/zamzamintl')
            ->assertSee('01678413888')
            ->assertSee('zamzamint.com')
            ->assertSee('Thank You For Purchasing From Us.')
            // Barcode SVG rendered twice: main invoice + cut slip.
            ->assertSee(substr(Code128::svg($order->order_number), 0, 60), false)
            ->assertSee('id="courier-slip"', false)
            ->assertSee('Invoice No: <strong>'.$order->order_number.'</strong>', false);
    }

    public function test_invoice_settings_toggles_hide_optional_sections(): void
    {
        app(CompanySettingsService::class)->saveInvoice([
            'show_images' => false,
            'show_weight' => false,
            'show_barcode' => false,
            'show_slip' => false,
            'thank_you' => '',
        ]);

        $order = $this->makeOrder();

        $response = $this->actingAs($this->admin())
            ->get(route('orders.print', $order))
            ->assertOk()
            ->assertDontSee('Weight')
            ->assertDontSee('>Image<', false)
            ->assertDontSee('id="courier-slip"', false)
            ->assertDontSee('Thank You');

        $this->assertStringNotContainsString('<svg', $response->getContent());
    }

    public function test_code128_generator_produces_valid_svg(): void
    {
        $svg = Code128::svg('SO-1119');

        $this->assertStringStartsWith('<svg', $svg);
        $this->assertStringContainsString('aria-label="SO-1119"', $svg);
        $this->assertStringContainsString('<rect', $svg);
        $this->assertSame('', Code128::svg(''));
    }

    public function test_invoice_settings_are_saved_per_company(): void
    {
        $service = app(CompanySettingsService::class);

        $service->saveInvoice([
            'hotline' => '01700000000',
            'show_weight' => false,
        ]);

        $stored = $service->invoice(Company::defaultCompany());

        $this->assertSame('01700000000', $stored['hotline']);
        $this->assertFalse($stored['show_weight']);
        $this->assertTrue($stored['show_images']);
        $this->assertSame('Thank You For Purchasing From Us.', $stored['thank_you']);

        $other = Company::query()->create([
            'name' => 'Other Co',
            'slug' => 'other-co-invoice',
            'invoice_prefix' => 'OTH',
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
        ]);

        $this->assertSame('', $service->invoice($other)['hotline']);
    }
}
