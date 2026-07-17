<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Lead;
use App\Models\Order;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\StockMovement;
use App\Services\CompanyContext;
use App\Services\Crm\LeadConversionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuotationTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::query()->create([
            'name' => 'Quote Co',
            'slug' => 'quote-co',
            'invoice_prefix' => 'QC',
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
        ]);

        app(CompanyContext::class)->set($this->company);

        $this->product = Product::query()->create([
            'name' => 'Quoted Product',
            'sku' => 'QC-PROD-001',
            'price' => 1000,
            'sale_price' => 1000,
            'cost_price' => 600,
            'stock' => 20,
            'unit' => 'pcs',
            'reorder_level' => 1,
            'vat_rate' => 0,
            'is_active' => true,
            'status' => Product::STATUS_AVAILABLE,
        ]);

        StockMovement::query()->create([
            'company_id' => $this->company->getKey(),
            'product_id' => $this->product->getKey(),
            'type' => 'opening',
            'quantity' => 20,
            'reference_type' => Product::class,
            'reference_id' => $this->product->getKey(),
            'note' => 'Quotation test opening stock',
        ]);
    }

    protected function quotationWithItems(string $status = 'accepted', float $discount = 0): Quotation
    {
        $lead = Lead::query()->create(['name' => 'Quote Lead', 'phone' => '01777777777', 'source' => 'facebook']);

        $quotation = Quotation::query()->create([
            'lead_id' => $lead->getKey(),
            'status' => $status,
            'discount_amount' => $discount,
        ]);

        QuotationItem::query()->create([
            'quotation_id' => $quotation->getKey(),
            'product_id' => $this->product->getKey(),
            'quantity' => 2,
            'unit_price' => 1000,
        ]);

        return $quotation->fresh();
    }

    public function test_quotation_total_is_calculated_from_items(): void
    {
        $quotation = $this->quotationWithItems('draft', discount: 100);

        $this->assertSame(2000.0, (float) $quotation->items()->sum('subtotal'));
        $this->assertSame(1900.0, (float) $quotation->total_amount);
        $this->assertStringStartsWith('QT-', $quotation->quotation_number);
    }

    public function test_only_accepted_quotations_convert_to_orders(): void
    {
        $quotation = $this->quotationWithItems('sent');

        $this->expectException(\RuntimeException::class);

        app(LeadConversionService::class)->convertQuotationToOrder($quotation);
    }

    public function test_accepted_quotation_converts_to_order_and_marks_lead_won(): void
    {
        $quotation = $this->quotationWithItems('accepted');

        $order = app(LeadConversionService::class)->convertQuotationToOrder($quotation);

        $this->assertSame(Order::SOURCE_CRM, $order->source);
        $this->assertSame('draft', $order->status);
        $this->assertSame(2, (int) $order->items()->sum('quantity'));
        $this->assertSame($order->getKey(), $quotation->fresh()->converted_order_id);

        $lead = $quotation->lead->fresh();
        $this->assertSame('won', $lead->status);
        $this->assertSame($order->getKey(), $lead->converted_order_id);
        $this->assertNotNull($lead->converted_customer_id);

        // Confirming the order drives stock via the existing workflow.
        $order->update(['status' => 'confirmed']);
        $this->assertTrue(
            StockMovement::query()
                ->where('reference_type', Order::class)
                ->where('reference_id', $order->getKey())
                ->exists(),
        );
    }

    public function test_reconverting_a_converted_quotation_returns_the_same_order(): void
    {
        $quotation = $this->quotationWithItems('accepted');

        $service = app(LeadConversionService::class);
        $first = $service->convertQuotationToOrder($quotation);
        $second = $service->convertQuotationToOrder($quotation->fresh());

        $this->assertSame($first->getKey(), $second->getKey());
        $this->assertSame(1, Order::query()->count());
    }

    public function test_mark_expired_command_expires_past_sent_quotations(): void
    {
        $expired = $this->quotationWithItems('sent');
        $expired->update(['valid_until' => now()->subDay()->toDateString()]);

        $current = $this->quotationWithItems('sent');
        $current->update(['valid_until' => now()->addDay()->toDateString()]);

        $this->artisan('quotations:mark-expired')->assertSuccessful();

        $this->assertSame('expired', $expired->fresh()->status);
        $this->assertSame('sent', $current->fresh()->status);
    }

    public function test_public_quotation_page_is_viewable_without_auth(): void
    {
        $quotation = $this->quotationWithItems('sent');

        app(CompanyContext::class)->clear();

        $this->get(route('quotation.public', $quotation->quotation_number))
            ->assertOk()
            ->assertSee($quotation->quotation_number)
            ->assertSee('Quoted Product');
    }
}
