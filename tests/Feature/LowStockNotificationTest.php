<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Services\LowStockAlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LowStockNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_low_stock_alert_service_finds_products_at_reorder_level(): void
    {
        Product::query()->create([
            'name' => 'Low Stock Product',
            'sku' => 'LOW-001',
            'price' => 100,
            'sale_price' => 100,
            'stock' => 2,
            'reorder_level' => 5,
        ]);
        Product::query()->create([
            'name' => 'Healthy Stock Product',
            'sku' => 'OK-001',
            'price' => 100,
            'sale_price' => 100,
            'stock' => 12,
            'reorder_level' => 5,
        ]);

        $alerts = app(LowStockAlertService::class);

        $this->assertTrue($alerts->hasAlerts());
        $this->assertSame(1, $alerts->count());
        $this->assertSame('Low Stock Product', $alerts->products()->first()?->name);
        $this->assertSame('1 product is at or below its reorder level.', $alerts->message());
    }

    public function test_dashboard_shows_low_stock_notification_widget(): void
    {
        $admin = User::factory()->create([
            'role' => 'super_admin',
            'is_active' => true,
        ]);
        Product::query()->create([
            'name' => 'Dashboard Low Stock',
            'sku' => 'LOW-DASH-001',
            'price' => 100,
            'sale_price' => 100,
            'stock' => 1,
            'reorder_level' => 3,
        ]);

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertSee('Low Stock Notifications')
            ->assertSee('Dashboard Low Stock')
            ->assertSee('1 product is at or below its reorder level.');
    }
}
