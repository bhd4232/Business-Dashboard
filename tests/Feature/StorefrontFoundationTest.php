<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Company;
use App\Models\CourierBooking;
use App\Models\CourierProvider;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\StorefrontPage;
use App\Models\StorefrontSetting;
use App\Models\User;
use App\Services\CompanyContext;
use App\Services\ReportService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_custom_domain_renders_company_storefront_products(): void
    {
        $company = $this->createPublishedStorefrontCompany('Gadget Store', 'shop.example.test');

        app(CompanyContext::class)->set($company);

        $category = Category::query()->create([
            'name' => 'Chargers',
            'slug' => 'chargers',
            'is_active' => true,
        ]);

        $product = Product::query()->create([
            'name' => 'Fast Charger',
            'sku' => 'FAST-CHARGER-001',
            'price' => 1200,
            'sale_price' => 1100,
            'cost_price' => 700,
            'stock' => 10,
            'unit' => 'pcs',
            'reorder_level' => 2,
            'vat_rate' => 0,
            'is_active' => true,
            'status' => Product::STATUS_AVAILABLE,
            'category_id' => $category->getKey(),
        ]);

        $this->get('http://shop.example.test/')
            ->assertOk()
            ->assertSee('Gadget Store')
            ->assertSee('Fast Charger')
            ->assertSee('Account')
            ->assertSee('Official storefront - live catalog, direct ordering')
            ->assertSee('property="og:title"', false)
            ->assertSee('http://shop.example.test/account/orders', false);

        $this->get('http://shop.example.test/product/'.$product->slug)
            ->assertOk()
            ->assertSee('Fast Charger')
            ->assertSee('BDT 1,100.00');
    }

    public function test_storefront_only_shows_current_domain_company_products(): void
    {
        $gadget = $this->createPublishedStorefrontCompany('Gadget Store', 'gadget.example.test');
        $gift = $this->createPublishedStorefrontCompany('Gift Store', 'gift.example.test');

        app(CompanyContext::class)->set($gadget);
        Product::query()->create([
            'name' => 'Gadget Cable',
            'sku' => 'GADGET-CABLE-001',
            'price' => 500,
            'sale_price' => 500,
            'cost_price' => 250,
            'stock' => 5,
            'unit' => 'pcs',
            'reorder_level' => 1,
            'vat_rate' => 0,
            'is_active' => true,
            'status' => Product::STATUS_AVAILABLE,
        ]);

        app(CompanyContext::class)->set($gift);
        Product::query()->create([
            'name' => 'Gift Box',
            'sku' => 'GIFT-BOX-001',
            'price' => 300,
            'sale_price' => 300,
            'cost_price' => 120,
            'stock' => 8,
            'unit' => 'pcs',
            'reorder_level' => 1,
            'vat_rate' => 0,
            'is_active' => true,
            'status' => Product::STATUS_AVAILABLE,
        ]);

        $this->get('http://gadget.example.test/products')
            ->assertOk()
            ->assertSee('Gadget Cable')
            ->assertDontSee('Gift Box');
    }

    public function test_unpublished_or_unknown_storefront_is_not_public(): void
    {
        $company = Company::query()->create([
            'name' => 'Draft Store',
            'slug' => 'draft-store',
            'domain' => 'draft.example.test',
            'domain_verified' => true,
            'invoice_prefix' => 'DRF',
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
        ]);

        StorefrontSetting::query()->create([
            'company_id' => $company->getKey(),
            'theme_color' => '#0F766E',
            'is_published' => false,
        ]);

        $this->get('http://draft.example.test/')
            ->assertNotFound();

        $this->get('http://unknown.example.test/products')
            ->assertNotFound();
    }

    public function test_local_root_keeps_marketing_homepage(): void
    {
        $this->get('http://127.0.0.1/')
            ->assertOk()
            ->assertSee('ZamZam ERP');
    }

    public function test_app_own_domain_root_redirects_to_admin_panel(): void
    {
        config(['app.admin_host' => 'app.zamzamint.com']);

        $this->get('http://app.zamzamint.com/')
            ->assertRedirect('/admin');
    }

    public function test_local_storefront_preview_renders_without_custom_host_mapping(): void
    {
        $company = $this->createPublishedStorefrontCompany('Local Demo Store', 'local-demo.example.test');

        app(CompanyContext::class)->set($company);

        Product::query()->create([
            'name' => 'Local Preview Product',
            'sku' => 'LOCAL-PREVIEW-001',
            'price' => 700,
            'sale_price' => 650,
            'cost_price' => 400,
            'stock' => 9,
            'unit' => 'pcs',
            'reorder_level' => 1,
            'vat_rate' => 0,
            'is_active' => true,
            'status' => Product::STATUS_AVAILABLE,
        ]);

        $this->get('http://127.0.0.1/storefront')
            ->assertOk()
            ->assertSee('Local Demo Store')
            ->assertSee('Local Preview Product')
            ->assertSee('/storefront/local-demo-store/products', false);
    }

    public function test_storefront_settings_admin_page_renders(): void
    {
        $user = User::factory()->create([
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get('/admin/storefront-settings')
            ->assertOk();
    }

    public function test_storefront_cart_add_update_and_remove_products(): void
    {
        $company = $this->createPublishedStorefrontCompany('Cart Store', 'cart.example.test');

        app(CompanyContext::class)->set($company);

        $product = Product::query()->create([
            'name' => 'Cart Product',
            'sku' => 'CART-PRODUCT-001',
            'price' => 1000,
            'sale_price' => 900,
            'cost_price' => 500,
            'stock' => 5,
            'unit' => 'pcs',
            'reorder_level' => 1,
            'vat_rate' => 0,
            'is_active' => true,
            'status' => Product::STATUS_AVAILABLE,
        ]);

        $this->post('http://cart.example.test/cart/items/'.$product->slug, ['quantity' => 2])
            ->assertRedirect();

        $this->get('http://cart.example.test/cart')
            ->assertOk()
            ->assertSee('Cart Product')
            ->assertSee('BDT 1,800.00');

        $this->patch('http://cart.example.test/cart/items/'.$product->slug, ['quantity' => 10])
            ->assertRedirect();

        $this->get('http://cart.example.test/cart')
            ->assertOk()
            ->assertSee('BDT 4,500.00')
            ->assertSee('value="5"', false);

        $this->delete('http://cart.example.test/cart/items/'.$product->slug)
            ->assertRedirect();

        $this->get('http://cart.example.test/cart')
            ->assertOk()
            ->assertSee('Your cart is empty')
            ->assertDontSee('Cart Product');
    }

    public function test_storefront_cart_is_company_scoped(): void
    {
        $gadget = $this->createPublishedStorefrontCompany('Gadget Cart Store', 'gadget-cart.example.test');
        $gift = $this->createPublishedStorefrontCompany('Gift Cart Store', 'gift-cart.example.test');

        app(CompanyContext::class)->set($gadget);
        $gadgetProduct = Product::query()->create([
            'name' => 'Gadget Cart Item',
            'sku' => 'GADGET-CART-001',
            'price' => 400,
            'sale_price' => 400,
            'cost_price' => 200,
            'stock' => 6,
            'unit' => 'pcs',
            'reorder_level' => 1,
            'vat_rate' => 0,
            'is_active' => true,
            'status' => Product::STATUS_AVAILABLE,
        ]);

        app(CompanyContext::class)->set($gift);
        Product::query()->create([
            'name' => 'Gift Cart Item',
            'sku' => 'GIFT-CART-001',
            'price' => 300,
            'sale_price' => 300,
            'cost_price' => 150,
            'stock' => 6,
            'unit' => 'pcs',
            'reorder_level' => 1,
            'vat_rate' => 0,
            'is_active' => true,
            'status' => Product::STATUS_AVAILABLE,
        ]);

        $this->post('http://gadget-cart.example.test/cart/items/'.$gadgetProduct->slug, ['quantity' => 1])
            ->assertRedirect();

        $this->get('http://gadget-cart.example.test/cart')
            ->assertOk()
            ->assertSee('Gadget Cart Item');

        $this->get('http://gift-cart.example.test/cart')
            ->assertOk()
            ->assertSee('Your cart is empty')
            ->assertDontSee('Gadget Cart Item');
    }

    public function test_local_preview_cart_routes_work_without_custom_host_mapping(): void
    {
        $company = $this->createPublishedStorefrontCompany('Preview Cart Store', 'preview-cart.example.test');

        app(CompanyContext::class)->set($company);

        $product = Product::query()->create([
            'name' => 'Preview Cart Product',
            'sku' => 'PREVIEW-CART-001',
            'price' => 750,
            'sale_price' => 700,
            'cost_price' => 350,
            'stock' => 4,
            'unit' => 'pcs',
            'reorder_level' => 1,
            'vat_rate' => 0,
            'is_active' => true,
            'status' => Product::STATUS_AVAILABLE,
        ]);

        $this->post("http://127.0.0.1/storefront/{$company->slug}/cart/items/{$product->slug}", ['quantity' => 3])
            ->assertRedirect();

        $this->get("http://127.0.0.1/storefront/{$company->slug}/cart")
            ->assertOk()
            ->assertSee('Preview Cart Product')
            ->assertSee('BDT 2,100.00');
    }

    public function test_storefront_checkout_creates_draft_erp_order_and_clears_cart(): void
    {
        $company = $this->createPublishedStorefrontCompany('Checkout Store', 'checkout.example.test');

        app(CompanyContext::class)->set($company);

        $product = Product::query()->create([
            'name' => 'Checkout Product',
            'sku' => 'CHECKOUT-PRODUCT-001',
            'price' => 1000,
            'sale_price' => 950,
            'cost_price' => 500,
            'stock' => 8,
            'unit' => 'pcs',
            'reorder_level' => 1,
            'vat_rate' => 0,
            'is_active' => true,
            'status' => Product::STATUS_AVAILABLE,
        ]);

        $this->post('http://checkout.example.test/cart/items/'.$product->slug, ['quantity' => 2])
            ->assertRedirect();

        $response = $this->post('http://checkout.example.test/checkout', [
            'name' => 'Storefront Buyer',
            'phone' => '+8801700111222',
            'email' => 'buyer@example.test',
            'address' => 'Mirpur, Dhaka',
            'note' => 'Call before delivery',
        ]);

        $order = Order::query()->where('source', Order::SOURCE_STOREFRONT)->first();

        $response->assertRedirect('http://checkout.example.test/checkout/success/'.$order->getKey());

        $this->assertDatabaseHas('customers', [
            'company_id' => $company->getKey(),
            'name' => 'Storefront Buyer',
            'phone' => '+8801700111222',
            'email' => 'buyer@example.test',
            'customer_source' => 'website',
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $order->getKey(),
            'company_id' => $company->getKey(),
            'customer_name' => 'Storefront Buyer',
            'status' => 'draft',
            'source' => Order::SOURCE_STOREFRONT,
            'subtotal' => 1900,
            'total_amount' => 1900,
            'due_amount' => 1900,
        ]);
        $summary = app(ReportService::class)->dashboardSummary();
        $this->assertSame(1, $summary['storefront_pending_orders']);
        $this->assertSame(1900.0, (float) $summary['storefront_pending_amount']);
        $this->assertSame(0.0, (float) $summary['sales_today']);

        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->getKey(),
            'product_id' => $product->getKey(),
            'quantity' => 2,
            'unit_price' => 950,
            'subtotal' => 1900,
        ]);
        $this->assertSame(0, StockMovement::query()->where('type', 'sale')->where('reference_id', $order->getKey())->count());

        $this->get('http://checkout.example.test/cart')
            ->assertOk()
            ->assertSee('Your cart is empty');
    }

    public function test_storefront_checkout_reuses_customer_by_phone_in_same_company(): void
    {
        $company = $this->createPublishedStorefrontCompany('Customer Reuse Store', 'reuse.example.test');

        app(CompanyContext::class)->set($company);

        $customer = Customer::query()->create([
            'name' => 'Old Name',
            'phone' => '+8801700555666',
            'opening_balance' => 0,
            'is_active' => true,
        ]);

        $product = Product::query()->create([
            'name' => 'Reuse Product',
            'sku' => 'REUSE-PRODUCT-001',
            'price' => 300,
            'sale_price' => 300,
            'cost_price' => 150,
            'stock' => 3,
            'unit' => 'pcs',
            'reorder_level' => 1,
            'vat_rate' => 0,
            'is_active' => true,
            'status' => Product::STATUS_AVAILABLE,
        ]);

        $this->post('http://reuse.example.test/cart/items/'.$product->slug, ['quantity' => 1]);
        $this->post('http://reuse.example.test/checkout', [
            'name' => 'Updated Buyer',
            'phone' => '+8801700555666',
            'address' => 'Uttara, Dhaka',
        ])->assertRedirect();

        $this->assertSame(1, Customer::query()->where('phone', '+8801700555666')->count());
        $this->assertDatabaseHas('customers', [
            'id' => $customer->getKey(),
            'name' => 'Updated Buyer',
            'address' => 'Uttara, Dhaka',
        ]);
    }

    public function test_local_preview_checkout_creates_storefront_order(): void
    {
        $company = $this->createPublishedStorefrontCompany('Preview Checkout Store', 'preview-checkout.example.test');

        app(CompanyContext::class)->set($company);

        $product = Product::query()->create([
            'name' => 'Preview Checkout Product',
            'sku' => 'PREVIEW-CHECKOUT-001',
            'price' => 500,
            'sale_price' => 450,
            'cost_price' => 200,
            'stock' => 5,
            'unit' => 'pcs',
            'reorder_level' => 1,
            'vat_rate' => 0,
            'is_active' => true,
            'status' => Product::STATUS_AVAILABLE,
        ]);

        $this->post("http://127.0.0.1/storefront/{$company->slug}/cart/items/{$product->slug}", ['quantity' => 2]);

        $this->get("http://127.0.0.1/storefront/{$company->slug}/checkout")
            ->assertOk()
            ->assertSee('Preview Checkout Product');

        $this->post("http://127.0.0.1/storefront/{$company->slug}/checkout", [
            'name' => 'Preview Buyer',
            'phone' => '+8801700999888',
            'address' => 'Dhanmondi, Dhaka',
        ])->assertRedirect();

        $this->assertDatabaseHas('orders', [
            'company_id' => $company->getKey(),
            'customer_name' => 'Preview Buyer',
            'source' => Order::SOURCE_STOREFRONT,
            'total_amount' => 900,
        ]);
    }

    public function test_storefront_order_tracking_shows_order_and_courier_status(): void
    {
        $company = $this->createPublishedStorefrontCompany('Tracking Store', 'track.example.test');

        app(CompanyContext::class)->set($company);

        $customer = Customer::query()->create([
            'name' => 'Tracking Buyer',
            'phone' => '+8801711111111',
            'opening_balance' => 0,
            'is_active' => true,
        ]);
        $product = Product::query()->create([
            'name' => 'Tracking Product',
            'sku' => 'TRACK-PRODUCT-001',
            'price' => 600,
            'sale_price' => 550,
            'cost_price' => 300,
            'stock' => 5,
            'unit' => 'pcs',
            'reorder_level' => 1,
            'vat_rate' => 0,
            'is_active' => true,
            'status' => Product::STATUS_AVAILABLE,
        ]);
        $order = Order::query()->create([
            'customer_id' => $customer->getKey(),
            'customer_name' => $customer->name,
            'status' => 'draft',
            'source' => Order::SOURCE_STOREFRONT,
        ]);
        OrderItem::query()->create([
            'order_id' => $order->getKey(),
            'product_id' => $product->getKey(),
            'quantity' => 2,
            'unit_price' => 550,
        ]);
        $order->update([
            'delivery_status' => CourierBooking::STATUS_IN_TRANSIT,
        ]);
        $provider = CourierProvider::query()->create([
            'name' => 'Manual Courier',
            'driver' => CourierProvider::DRIVER_MANUAL,
            'is_active' => true,
        ]);
        CourierBooking::query()->create([
            'courier_provider_id' => $provider->getKey(),
            'order_id' => $order->getKey(),
            'tracking_id' => 'TRK-123456',
            'recipient_name' => $customer->name,
            'status' => CourierBooking::STATUS_IN_TRANSIT,
        ]);

        $this->get('http://track.example.test/track')
            ->assertOk()
            ->assertSee('Track your storefront order');

        $this->get('http://track.example.test/track?order_number='.$order->order_number.'&phone=01711111111')
            ->assertRedirect('http://track.example.test/track/'.$order->order_number.'?phone=01711111111');

        // The order number alone is not enough — a matching phone is required.
        $this->get('http://track.example.test/track/'.$order->order_number)
            ->assertOk()
            ->assertDontSee('Tracking Product')
            ->assertSee('find an order matching');

        $this->get('http://track.example.test/track/'.$order->order_number.'?phone=01711111111')
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertSee('Tracking Product')
            ->assertSee('Draft')
            ->assertSee('Delivery update')
            ->assertSee('Tracking Updates')
            ->assertSee('Delivery status updated to In Transit.')
            ->assertSee('In Transit')
            ->assertDontSee('Booking Pending')
            ->assertDontSee('Partial Delivered')
            ->assertDontSee('Returned')
            ->assertDontSee('Cancelled')
            ->assertDontSee('Failed')
            ->assertSee('Manual Courier')
            ->assertSee('TRK-123456')
            ->assertSee('BDT 1,100.00');
    }

    public function test_storefront_order_tracking_does_not_leak_other_company_or_admin_orders(): void
    {
        $trackingCompany = $this->createPublishedStorefrontCompany('Safe Tracking Store', 'safe-track.example.test');
        $otherCompany = $this->createPublishedStorefrontCompany('Other Tracking Store', 'other-track.example.test');

        app(CompanyContext::class)->set($otherCompany);
        $otherCustomer = Customer::query()->create([
            'name' => 'Other Buyer',
            'phone' => '01900000000',
            'opening_balance' => 0,
            'is_active' => true,
        ]);
        $otherOrder = Order::query()->create([
            'customer_id' => $otherCustomer->getKey(),
            'customer_name' => 'Other Buyer',
            'status' => 'draft',
            'source' => Order::SOURCE_STOREFRONT,
        ]);

        app(CompanyContext::class)->set($trackingCompany);
        $adminCustomer = Customer::query()->create([
            'name' => 'Admin Buyer',
            'phone' => '01900000000',
            'opening_balance' => 0,
            'is_active' => true,
        ]);
        $adminOrder = Order::query()->create([
            'customer_id' => $adminCustomer->getKey(),
            'customer_name' => 'Admin Buyer',
            'status' => 'draft',
            'source' => Order::SOURCE_ADMIN,
        ]);

        // Even with the correct customer phone, an order from another company
        // or an admin-sourced order is never revealed on this storefront.
        $this->get('http://safe-track.example.test/track/'.$otherOrder->order_number.'?phone=01900000000')
            ->assertOk()
            ->assertDontSee('Order items')
            ->assertSee('find an order matching');

        $this->get('http://safe-track.example.test/track/'.$adminOrder->order_number.'?phone=01900000000')
            ->assertOk()
            ->assertDontSee('Order items')
            ->assertSee('find an order matching');
    }

    public function test_local_preview_order_tracking_works_without_custom_host_mapping(): void
    {
        $company = $this->createPublishedStorefrontCompany('Preview Tracking Store', 'preview-track.example.test');

        app(CompanyContext::class)->set($company);

        $customer = Customer::query()->create([
            'name' => 'Preview Tracking Buyer',
            'phone' => '01722222222',
            'opening_balance' => 0,
            'is_active' => true,
        ]);
        $order = Order::query()->create([
            'customer_id' => $customer->getKey(),
            'customer_name' => 'Preview Tracking Buyer',
            'status' => 'draft',
            'source' => Order::SOURCE_STOREFRONT,
        ]);

        $this->get("http://127.0.0.1/storefront/{$company->slug}/track")
            ->assertOk()
            ->assertSee('Preview Tracking Store')
            ->assertSee('Track your storefront order');

        $this->get("http://127.0.0.1/storefront/{$company->slug}/track?order_number={$order->order_number}&phone=01722222222")
            ->assertRedirect("http://127.0.0.1/storefront/{$company->slug}/track/{$order->order_number}?phone=01722222222");

        $this->get("http://127.0.0.1/storefront/{$company->slug}/track/{$order->order_number}?phone=01722222222")
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertSee('Draft')
            ->assertDontSee('Not Booked')
            ->assertDontSee('Delivery update');
    }

    public function test_completed_order_with_not_booked_delivery_keeps_frontend_delivery_status_not_booked(): void
    {
        $company = $this->createPublishedStorefrontCompany('Completed Hidden Delivery Store', 'completed-hidden-delivery.example.test');

        app(CompanyContext::class)->set($company);

        $customer = Customer::query()->create([
            'name' => 'Completed Buyer',
            'phone' => '01733333333',
            'opening_balance' => 0,
            'is_active' => true,
        ]);
        $order = Order::query()->create([
            'customer_id' => $customer->getKey(),
            'customer_name' => 'Completed Buyer',
            'status' => 'completed',
            'delivery_status' => CourierBooking::STATUS_NOT_BOOKED,
            'source' => Order::SOURCE_STOREFRONT,
        ]);

        $this->get("http://127.0.0.1/storefront/{$company->slug}/track/{$order->order_number}?phone=01733333333")
            ->assertOk()
            ->assertSee('Completed')
            ->assertDontSee('Not Booked')
            ->assertDontSee('Delivery update')
            ->assertDontSee('Tracking Updates')
            ->assertDontSee('Delivered');
    }

    public function test_storefront_customer_order_history_shows_matching_phone_orders(): void
    {
        $company = $this->createPublishedStorefrontCompany('Account Store', 'account.example.test');

        app(CompanyContext::class)->set($company);

        $customer = Customer::query()->create([
            'name' => 'Account Buyer',
            'phone' => '01728174614',
            'opening_balance' => 0,
            'is_active' => true,
        ]);
        $product = Product::query()->create([
            'name' => 'Account Product',
            'sku' => 'ACCOUNT-PRODUCT-001',
            'price' => 1200,
            'sale_price' => 1000,
            'cost_price' => 650,
            'stock' => 8,
            'unit' => 'pcs',
            'reorder_level' => 1,
            'vat_rate' => 0,
            'is_active' => true,
            'status' => Product::STATUS_AVAILABLE,
        ]);
        $order = Order::query()->create([
            'customer_id' => $customer->getKey(),
            'customer_name' => $customer->name,
            'status' => 'draft',
            'source' => Order::SOURCE_STOREFRONT,
        ]);
        OrderItem::query()->create([
            'order_id' => $order->getKey(),
            'product_id' => $product->getKey(),
            'quantity' => 2,
            'unit_price' => 1000,
        ]);
        $order->refresh();

        $this->get('http://account.example.test/account/orders')
            ->assertOk()
            ->assertSee('Find your storefront orders.');

        $this->get('http://account.example.test/account/orders?phone=01728174614')
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertSee('Draft')
            ->assertSee('BDT 2,000.00')
            ->assertSee('Track order')
            ->assertSee('http://account.example.test/track/'.$order->order_number, false);
    }

    public function test_storefront_customer_order_history_does_not_leak_other_company_or_admin_orders(): void
    {
        $accountCompany = $this->createPublishedStorefrontCompany('Safe Account Store', 'safe-account.example.test');
        $otherCompany = $this->createPublishedStorefrontCompany('Other Account Store', 'other-account.example.test');

        app(CompanyContext::class)->set($otherCompany);
        $otherCustomer = Customer::query()->create([
            'name' => 'Other Account Buyer',
            'phone' => '01728174614',
            'opening_balance' => 0,
            'is_active' => true,
        ]);
        $otherOrder = Order::query()->create([
            'customer_id' => $otherCustomer->getKey(),
            'customer_name' => $otherCustomer->name,
            'status' => 'draft',
            'source' => Order::SOURCE_STOREFRONT,
        ]);

        app(CompanyContext::class)->set($accountCompany);
        $adminCustomer = Customer::query()->create([
            'name' => 'Admin Account Buyer',
            'phone' => '01728174614',
            'opening_balance' => 0,
            'is_active' => true,
        ]);
        $adminOrder = Order::query()->create([
            'customer_id' => $adminCustomer->getKey(),
            'customer_name' => $adminCustomer->name,
            'status' => 'draft',
            'source' => Order::SOURCE_ADMIN,
        ]);

        $this->get('http://safe-account.example.test/account/orders?phone=01728174614')
            ->assertOk()
            ->assertSee('No storefront orders found')
            ->assertDontSee($otherOrder->order_number)
            ->assertDontSee($adminOrder->order_number);
    }

    public function test_storefront_public_pages_render_and_show_footer_links(): void
    {
        $company = $this->createPublishedStorefrontCompany('Content Store', 'content.example.test');

        app(CompanyContext::class)->set($company);

        $page = StorefrontPage::query()->create([
            'title' => 'Return Policy',
            'slug' => 'return-policy',
            'excerpt' => 'Simple returns for eligible storefront orders.',
            'content' => "Return within 7 days.\nKeep the invoice for verification.",
            'is_published' => true,
            'sort_order' => 1,
        ]);

        $this->get('http://content.example.test/')
            ->assertOk()
            ->assertSee('Return Policy')
            ->assertSee('http://content.example.test/pages/'.$page->slug, false);

        $this->get('http://content.example.test/pages/return-policy')
            ->assertOk()
            ->assertSee('Return Policy')
            ->assertSee('Simple returns for eligible storefront orders.')
            ->assertSee('Return within 7 days.')
            ->assertSee('Keep the invoice for verification.');

        $this->get('http://127.0.0.1/pages/return-policy')
            ->assertOk()
            ->assertSee('Return Policy')
            ->assertSee('/storefront/content-store/products', false);
    }

    public function test_storefront_public_pages_do_not_leak_unpublished_or_other_company_pages(): void
    {
        $contentCompany = $this->createPublishedStorefrontCompany('Safe Content Store', 'safe-content.example.test');
        $otherCompany = $this->createPublishedStorefrontCompany('Other Content Store', 'other-content.example.test');

        app(CompanyContext::class)->set($contentCompany);

        StorefrontPage::query()->create([
            'title' => 'Draft Policy',
            'slug' => 'draft-policy',
            'content' => 'This draft should stay hidden.',
            'is_published' => false,
        ]);

        app(CompanyContext::class)->set($otherCompany);

        StorefrontPage::query()->create([
            'title' => 'Other Policy',
            'slug' => 'other-policy',
            'content' => 'Other company page.',
            'is_published' => true,
        ]);

        $this->get('http://safe-content.example.test/pages/draft-policy')
            ->assertNotFound();

        $this->get('http://safe-content.example.test/pages/other-policy')
            ->assertNotFound();
    }

    private function createPublishedStorefrontCompany(string $name, string $domain): Company
    {
        $company = Company::query()->create([
            'name' => $name,
            'slug' => str($name)->slug()->toString(),
            'domain' => $domain,
            'domain_verified' => true,
            'invoice_prefix' => str($name)->substr(0, 3)->upper()->toString(),
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
        ]);

        StorefrontSetting::query()->create([
            'company_id' => $company->getKey(),
            'theme_color' => '#0F766E',
            'whatsapp_number' => '+8801700000000',
            'meta_title' => $name,
            'is_published' => true,
        ]);

        return $company;
    }
}
