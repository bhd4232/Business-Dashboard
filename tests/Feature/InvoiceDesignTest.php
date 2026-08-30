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
use App\Services\CompanyContext;
use App\Services\CompanySettingsService;
use App\Services\CompanyStorageService;
use App\Support\Code128;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
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
        ], Company::defaultCompany());

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
            'provider_reference' => 'SF-CONSIGN-9911',
        ]);

        $response = $this->actingAs($this->admin())
            ->get(route('orders.print', $order))
            ->assertOk()
            ->assertSee('Hotline: 01811754232')
            ->assertSee('Delivery Partner:')
            ->assertSee('Steadfast')
            // Parcel ID shown between Delivery Partner and Date, both on the
            // main invoice and the courier cut-slip in the footer.
            ->assertSee('Parcel ID: <strong>SF-CONSIGN-9911</strong>', false)
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

        $this->assertSame(2, substr_count($response->getContent(), 'Parcel ID: <strong>SF-CONSIGN-9911</strong>'));
    }

    /**
     * Parcel ID is dynamic — most couriers' `provider_reference` duplicates
     * their tracking_id, but a manual/unbooked courier has neither, so the
     * line must not render a stray "Parcel ID:" label at all.
     */
    public function test_invoice_hides_parcel_id_when_the_booking_has_no_provider_reference(): void
    {
        $order = $this->makeOrder();

        $provider = CourierProvider::query()->create([
            'name' => 'Manual Courier',
            'driver' => 'manual',
            'is_active' => true,
        ]);

        CourierBooking::query()->create([
            'order_id' => $order->id,
            'courier_provider_id' => $provider->id,
            'status' => 'booked',
            'tracking_id' => 'MAN-1-20260829',
            'recipient_name' => 'Shakil',
            'cod_amount' => 3200,
        ]);

        $this->actingAs($this->admin())
            ->get(route('orders.print', $order))
            ->assertOk()
            ->assertSee('Delivery Partner:')
            ->assertDontSee('Parcel ID:');
    }

    public function test_invoice_settings_toggles_hide_optional_sections(): void
    {
        app(CompanySettingsService::class)->saveInvoice([
            'show_images' => false,
            'show_weight' => false,
            'show_barcode' => false,
            'show_slip' => false,
            'thank_you' => '',
        ], Company::defaultCompany());

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

    public function test_mobile_layout_does_not_override_the_a4_print_layout(): void
    {
        $order = $this->makeOrder();

        $this->actingAs($this->admin())
            ->get(route('orders.print', $order))
            ->assertOk()
            ->assertSee('@media screen and (max-width: 720px)', false)
            ->assertSee('-webkit-print-color-adjust: exact', false)
            ->assertSee('print-color-adjust: exact', false)
            ->assertDontSee('@media (max-width: 720px)', false);
    }

    /**
     * Owner report: tapping "Print" on mobile did not open the device's
     * print dialog. Root cause: window.print() was wrapped in a
     * setTimeout(), which drops the "user activation" flag most mobile
     * browsers require for window.print() to work — so the click handler
     * must call it synchronously, with no delay in between.
     */
    public function test_print_button_calls_print_synchronously_on_click_for_mobile_browsers(): void
    {
        $order = $this->makeOrder();

        $response = $this->actingAs($this->admin())
            ->get(route('orders.print', $order))
            ->assertOk();

        $content = $response->getContent();
        $clickHandlerStart = strpos($content, "addEventListener('click'");
        $this->assertNotFalse($clickHandlerStart);

        $clickHandlerBody = substr($content, $clickHandlerStart, 200);
        $this->assertStringContainsString('triggerPrint();', $clickHandlerBody);
        $this->assertStringNotContainsString('setTimeout', $clickHandlerBody);
    }

    /**
     * Owner report (follow-up): the print button works in a real mobile
     * browser now, but still does nothing inside the ZamZam Dashboard
     * Android app. Root cause: Android's WebView never implements
     * window.print() at all (unlike a real browser, where it just needed
     * the setTimeout fix above) — it's a silent no-op. The page must fall
     * back to the native ZzPrintBridge (PrintBridge.java, registered by
     * MainActivity) so printing works inside the app too.
     */
    public function test_print_button_falls_back_to_the_native_android_bridge_inside_the_app(): void
    {
        $order = $this->makeOrder();

        $this->actingAs($this->admin())
            ->get(route('orders.print', $order))
            ->assertOk()
            ->assertSee('window.ZzPrintBridge', false)
            ->assertSee("typeof window.ZzPrintBridge.print === 'function'", false)
            ->assertSee('window.ZzPrintBridge.print();', false);
    }

    public function test_print_typography_matches_the_compact_reference_scale(): void
    {
        $order = $this->makeOrder();

        $this->actingAs($this->admin())
            ->get(route('orders.print', $order))
            ->assertOk()
            ->assertSee('font-size: 10px;', false)
            ->assertSee('font-size: 28px;', false)
            ->assertSee('font-size: 12px;', false)
            ->assertSee('height: 34px;', false)
            ->assertSee('width: 34px;', false);
    }

    public function test_company_logo_is_rendered_in_the_invoice_header_and_courier_footer(): void
    {
        Storage::fake('public');

        $company = Company::defaultCompany();
        $logo = app(CompanyStorageService::class)->putPublic(
            $company,
            'company',
            'invoice-logo.png',
            'invoice-logo-bytes',
        );
        $company->forceFill(['logo' => $logo])->save();

        $order = $this->makeOrder();
        $response = $this->actingAs($this->admin())
            ->get(route('orders.print', $order))
            ->assertOk()
            ->assertSee('data-invoice-logo="main"', false)
            ->assertSee('data-invoice-logo="slip"', false)
            ->assertSee('loading="eager"', false)
            ->assertSee('object-position: left center', false)
            ->assertSee('max-width: 31.8mm', false)
            ->assertSee('max-height: 17.2mm', false)
            ->assertSee('max-width: 16.9mm', false)
            ->assertSee('max-height: 9.2mm', false);

        $logoUrl = app(CompanySettingsService::class)->profile($company->fresh())['logo_url'];

        $this->assertNotEmpty($logoUrl);
        $this->assertSame(2, substr_count($response->getContent(), 'src="'.$logoUrl.'"'));
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
        ], Company::defaultCompany());

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

        $service->saveInvoice([
            'hotline' => '01800000000',
            'thank_you' => 'Thank you from Other Co.',
        ], $other);

        $this->assertSame('01700000000', $service->invoice(Company::defaultCompany())['hotline']);
        $this->assertSame('01800000000', $service->invoice($other)['hotline']);
        $this->assertSame('Thank you from Other Co.', $service->invoice($other)['thank_you']);
        $this->assertStringStartsWith('MAIN-', Order::nextOrderNumber(Company::defaultCompany()));
        $this->assertStringStartsWith('OTH-', Order::nextOrderNumber($other));
    }

    public function test_print_and_pdf_controller_use_the_orders_own_company_invoice_settings(): void
    {
        $service = app(CompanySettingsService::class);
        $default = Company::defaultCompany();
        $service->saveInvoice([
            'hotline' => 'DEFAULT-HOTLINE',
            'thank_you' => 'Default company thanks',
        ], $default);
        $other = Company::query()->create([
            'name' => 'Dedicated Invoice Company',
            'slug' => 'dedicated-invoice-company',
            'invoice_prefix' => 'DIC',
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
        ]);
        $service->saveInvoice([
            'hotline' => 'OTHER-HOTLINE',
            'thank_you' => 'Other company thanks',
            'show_images' => false,
            'show_weight' => false,
            'show_barcode' => false,
            'show_slip' => false,
        ], $other);
        app(CompanyContext::class)->set($other);
        $order = $this->makeOrder();

        $this->actingAs($this->admin())
            ->withSession(['current_company_id' => 'all', 'current_company_selection_explicit' => true])
            ->get(route('orders.print', $order))
            ->assertOk()
            ->assertSee('OTHER-HOTLINE')
            ->assertSee('Other company thanks')
            ->assertDontSee('DEFAULT-HOTLINE');

        Pdf::shouldReceive('loadView')
            ->once()
            ->with('orders.pdf', Mockery::on(function (array $data) use ($order, $other): bool {
                return $data['order']->is($order)
                    && $data['company']['name'] === $other->name
                    && $data['invoice']['hotline'] === 'OTHER-HOTLINE'
                    && $data['invoice']['thank_you'] === 'Other company thanks'
                    && $data['invoice']['show_slip'] === false
                    && $data['productImages'] === [];
            }))
            ->andReturnSelf();
        Pdf::shouldReceive('setPaper')->once()->with('a4')->andReturnSelf();
        Pdf::shouldReceive('download')
            ->once()
            ->with($order->order_number.'.pdf')
            ->andReturn(response('PDF-CONTENT'));

        app(CompanyContext::class)->set($default);

        $this->actingAs($this->admin())
            ->withSession(['current_company_id' => 'all', 'current_company_selection_explicit' => true])
            ->get(route('orders.pdf', $order))
            ->assertOk()
            ->assertSee('PDF-CONTENT');
    }

    public function test_order_numbers_use_a_three_digit_daily_sequence_and_survive_overflow(): void
    {
        $company = Company::query()->create([
            'name' => 'Sequence Co',
            'slug' => 'sequence-co',
            'invoice_prefix' => 'ZMG',
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
        ]);
        app(CompanyContext::class)->set($company);

        $today = now()->format('Ymd');

        $first = Order::query()->create(['customer_name' => 'Buyer One', 'status' => 'draft', 'source' => Order::SOURCE_ADMIN]);
        $this->assertSame("ZMG-{$today}-001", $first->order_number);

        $second = Order::query()->create(['customer_name' => 'Buyer Two', 'status' => 'draft', 'source' => Order::SOURCE_ADMIN]);
        $this->assertSame("ZMG-{$today}-002", $second->order_number);

        // Once the daily count passes 999 the suffix grows past 3 digits --
        // the next number must read the whole numeric remainder (not just
        // its last 3 characters) or it would wrap back to "-000" and collide
        // with a number already used earlier today.
        $second->forceFill(['order_number' => "ZMG-{$today}-999"])->saveQuietly();
        $this->assertSame("ZMG-{$today}-1000", Order::nextOrderNumber($company));

        $second->forceFill(['order_number' => "ZMG-{$today}-1000"])->saveQuietly();
        $this->assertSame("ZMG-{$today}-1001", Order::nextOrderNumber($company));
    }
}
