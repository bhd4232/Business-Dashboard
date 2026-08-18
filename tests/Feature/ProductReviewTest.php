<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\StockMovement;
use App\Models\StorefrontSetting;
use App\Services\CompanyContext;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_customer_can_submit_a_review_from_a_completed_order(): void
    {
        $company = $this->createStore('review.example.test');
        app(CompanyContext::class)->set($company);

        [$customer, $order, $product] = $this->completedOrderFor($company);

        $this->actingAs($customer, 'customer')
            ->post('http://review.example.test/account/orders/'.$order->order_number.'/review', [
                'product_id' => $product->getKey(),
                'rating' => 5,
                'comment' => 'Great product!',
            ])
            ->assertRedirect();

        $review = ProductReview::withoutGlobalScopes()->first();
        $this->assertNotNull($review);
        $this->assertSame(ProductReview::STATUS_PENDING, $review->status);
        $this->assertSame(5, $review->rating);
        $this->assertSame($customer->getKey(), $review->customer_id);
        $this->assertSame($order->getKey(), $review->order_id);
    }

    public function test_duplicate_review_for_the_same_order_and_product_is_blocked(): void
    {
        $company = $this->createStore('review-dup.example.test');
        app(CompanyContext::class)->set($company);

        [$customer, $order, $product] = $this->completedOrderFor($company);

        $this->actingAs($customer, 'customer')
            ->post('http://review-dup.example.test/account/orders/'.$order->order_number.'/review', [
                'product_id' => $product->getKey(),
                'rating' => 4,
            ])->assertRedirect();

        $this->actingAs($customer, 'customer')
            ->post('http://review-dup.example.test/account/orders/'.$order->order_number.'/review', [
                'product_id' => $product->getKey(),
                'rating' => 2,
            ])->assertRedirect();

        $this->assertSame(1, ProductReview::withoutGlobalScopes()->count());
    }

    public function test_review_cannot_be_submitted_for_a_non_completed_order(): void
    {
        $company = $this->createStore('review-status.example.test');
        app(CompanyContext::class)->set($company);

        [$customer, $order, $product] = $this->completedOrderFor($company, 'confirmed');

        $this->actingAs($customer, 'customer')
            ->post('http://review-status.example.test/account/orders/'.$order->order_number.'/review', [
                'product_id' => $product->getKey(),
                'rating' => 5,
            ])
            ->assertNotFound();

        $this->assertSame(0, ProductReview::withoutGlobalScopes()->count());
    }

    public function test_only_approved_reviews_are_returned_by_the_approved_scope(): void
    {
        $company = $this->createStore('review-approved.example.test');
        app(CompanyContext::class)->set($company);

        [, , $product] = $this->completedOrderFor($company);

        ProductReview::query()->create([
            'company_id' => $company->getKey(),
            'product_id' => $product->getKey(),
            'customer_name' => 'Pending Reviewer',
            'rating' => 4,
            'status' => ProductReview::STATUS_PENDING,
        ]);
        ProductReview::query()->create([
            'company_id' => $company->getKey(),
            'product_id' => $product->getKey(),
            'customer_name' => 'Approved Reviewer',
            'rating' => 5,
            'status' => ProductReview::STATUS_APPROVED,
        ]);

        $approved = ProductReview::query()->approved()->get();
        $this->assertCount(1, $approved);
        $this->assertSame('Approved Reviewer', $approved->first()->customer_name);
    }

    /**
     * @return array{0: Customer, 1: Order, 2: Product}
     */
    protected function completedOrderFor(Company $company, string $status = Order::STATUS_COMPLETED): array
    {
        $customer = Customer::query()->create([
            'company_id' => $company->getKey(),
            'name' => 'Review Customer',
            'phone' => '01799990000',
            'password' => bcrypt('secret-password'),
            'customer_type' => 'regular',
            'customer_source' => 'website',
            'is_active' => true,
        ]);

        $product = Product::query()->create([
            'company_id' => $company->getKey(),
            'name' => 'Reviewed Product',
            'sku' => 'REVIEW-'.uniqid(),
            'price' => 500,
            'sale_price' => 500,
            'cost_price' => 300,
            'stock' => 10,
            'unit' => 'pcs',
            'reorder_level' => 1,
            'vat_rate' => 0,
            'is_active' => true,
            'status' => Product::STATUS_AVAILABLE,
        ]);

        // Product stock is derived from the StockMovement ledger, not the
        // `stock` column set at create() time — an explicit opening
        // movement is required before the workflow's stock check (triggered
        // by the status update below) will allow the sale.
        StockMovement::query()->create([
            'product_id' => $product->getKey(),
            'type' => 'opening',
            'quantity' => 10,
            'reference_type' => Product::class,
            'reference_id' => $product->getKey(),
            'note' => 'Review test opening stock',
        ]);

        $order = Order::query()->create([
            'company_id' => $company->getKey(),
            'customer_id' => $customer->getKey(),
            'customer_name' => $customer->name,
            'order_date' => now()->toDateString(),
            'status' => 'draft',
            'source' => Order::SOURCE_STOREFRONT,
        ]);

        OrderItem::query()->create([
            'order_id' => $order->getKey(),
            'product_id' => $product->getKey(),
            'quantity' => 1,
            'unit_price' => 500,
            'unit_cost' => 300,
        ]);

        // Status must be reached via an update() transition (not set at
        // create() time) so the workflow's stock-movement listener runs
        // against real OrderItem rows — matches the pattern already
        // established in OrderLedgersTest.
        $order->update(['status' => $status]);

        return [$customer, $order->fresh(), $product];
    }

    protected function createStore(string $domain): Company
    {
        static $counter = 0;
        $counter++;

        $company = Company::query()->create([
            'name' => 'Store '.$domain,
            'slug' => str($domain)->slug()->toString(),
            'domain' => $domain,
            'domain_verified' => true,
            'invoice_prefix' => 'REV'.$counter,
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
        ]);

        StorefrontSetting::query()->create([
            'company_id' => $company->getKey(),
            'theme_color' => '#0F766E',
            'is_published' => true,
        ]);

        return $company;
    }
}
