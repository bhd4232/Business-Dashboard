<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhaseTwoAdminPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_phase_two_admin_pages_render_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::query()->create(['name' => 'Admin Supplier']);
        $product = Product::query()->create([
            'name' => 'Admin Product',
            'sku' => 'ADMIN-PUR-001',
            'price' => 100,
            'sale_price' => 100,
            'stock' => 0,
        ]);
        $purchase = Purchase::query()->create([
            'supplier_id' => $supplier->id,
            'purchase_date' => now(),
            'status' => 'received',
        ]);

        PurchaseItem::query()->create([
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_cost' => 25,
        ]);

        $this->actingAs($user)
            ->get('/admin/suppliers')
            ->assertOk();

        $this->actingAs($user)
            ->get('/admin/purchases/create')
            ->assertOk()
            ->assertSee('Machine Purchase')
            ->assertSee('Inspection')
            ->assertSee('Freight to Ctg')
            ->assertSee('Cylinder')
            ->assertSee('Add new field')
            ->assertDontSee('Custom Fields');

        $this->actingAs($user)
            ->get("/admin/purchases/{$purchase->id}")
            ->assertOk()
            ->assertSee('Admin Product');
    }
}
