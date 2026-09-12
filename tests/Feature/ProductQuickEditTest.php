<?php

namespace Tests\Feature;

use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\ProductResource;
use App\Models\Category;
use App\Models\Company;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\CompanyContext;
use Filament\Actions\EditAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The Products list "Quick Edit" row action: the full product form in a
 * slide-over, saved through the same ledger-safe path as the full edit page
 * (App\Filament\Concerns\PersistsProductFormData) so it never writes the
 * stock column directly or drops the variants relationship.
 */
class ProductQuickEditTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;

    protected User $user;

    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::query()->create([
            'name' => 'Quick Edit Co', 'slug' => 'quick-edit-co',
            'invoice_prefix' => 'QEC', 'currency' => 'BDT', 'timezone' => 'Asia/Dhaka', 'is_active' => true,
        ]);
        app(CompanyContext::class)->set($this->company);

        $this->user = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $this->actingAs($this->user)->withSession(['current_company_id' => $this->company->getKey()]);

        $this->category = Category::query()->create(['name' => 'Gadgets', 'slug' => 'gadgets-qe']);
    }

    protected function makeProduct(array $overrides = []): Product
    {
        return Product::query()->create(array_merge([
            'name' => 'Editable Product',
            'sku' => 'QE-001',
            'category_id' => $this->category->getKey(),
            'price' => 500,
            'sale_price' => 500,
            'cost_price' => 300,
            'stock' => 0,
            'reorder_level' => 2,
            'unit' => 'pcs',
            'vat_rate' => 0,
            'is_active' => true,
            'status' => Product::STATUS_AVAILABLE,
        ], $overrides));
    }

    public function test_quick_edit_opens_a_modal_while_the_plain_edit_still_links_to_the_edit_page(): void
    {
        $product = $this->makeProduct();

        $page = Livewire::test(ListProducts::class)->instance();

        // Quick Edit is exempted, so it opens its slide-over instead of
        // redirecting to the full edit page.
        $this->assertNull($page->getDefaultActionUrl(EditAction::make('quickEdit')));

        // The plain "Edit (full page)" action keeps the redirect.
        $this->assertSame(
            ProductResource::getUrl('edit', ['record' => $product]),
            $page->getDefaultActionUrl(EditAction::make()->record($product)),
        );
    }

    public function test_quick_edit_updates_fields_and_mirrors_sale_price_to_price(): void
    {
        $product = $this->makeProduct();

        Livewire::test(ListProducts::class)
            ->mountTableAction('quickEdit', $product)
            ->setTableActionData(['name' => 'Renamed via Quick Edit', 'sale_price' => 999])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $product->refresh();
        $this->assertSame('Renamed via Quick Edit', $product->name);
        $this->assertSame('999.00', (string) $product->sale_price);
        $this->assertSame('999.00', (string) $product->price);
    }

    public function test_quick_edit_stock_change_is_recorded_as_a_stock_movement(): void
    {
        $product = $this->makeProduct();
        StockMovement::query()->create(['product_id' => $product->getKey(), 'type' => 'opening', 'quantity' => 10]);
        $product->refresh();
        $this->assertSame(10, $product->stock);

        Livewire::test(ListProducts::class)
            ->mountTableAction('quickEdit', $product)
            ->setTableActionData(['stock' => 6])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $this->assertSame(6, $product->refresh()->stock);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->getKey(),
            'type' => 'adjustment',
            'quantity' => -4,
            'reason' => 'Product form stock correction',
        ]);
    }

    public function test_quick_edit_of_a_variant_product_keeps_its_variant_rows(): void
    {
        $product = $this->makeProduct([
            'sku' => 'QE-VAR-001',
            'has_variants' => true,
            'variant_attributes' => ['Size' => ['M']],
        ]);
        $variant = $product->variants()->create([
            'options' => ['Size' => 'M'],
            'sku' => 'QE-VAR-001-M',
            'stock' => 5,
            'sale_price' => 120,
            'is_active' => true,
        ]);

        Livewire::test(ListProducts::class)
            ->mountTableAction('quickEdit', $product)
            ->setTableActionData(['name' => 'Variant Parent Renamed'])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $this->assertSame('Variant Parent Renamed', $product->refresh()->name);
        $this->assertSame(1, $product->variants()->count());
        $this->assertDatabaseHas('product_variants', ['id' => $variant->getKey(), 'stock' => 5]);
    }
}
