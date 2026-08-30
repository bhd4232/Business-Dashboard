<?php

namespace Tests\Feature;

use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Models\Company;
use App\Models\CourierBooking;
use App\Models\Order;
use App\Models\User;
use App\Services\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Owner request (2026-08-30): every list in the app should show its newest
 * record first by default. OrdersTable never calls its own ->defaultSort(),
 * so without the app-wide fallback registered in AppServiceProvider (see
 * App\Support\DefaultTableSort), Filament's own trailing key-sort tie-break
 * would list the oldest order on top and the newest at the very bottom.
 */
class DefaultTableSortTest extends TestCase
{
    use RefreshDatabase;

    public function test_orders_list_shows_the_newest_order_first_by_default(): void
    {
        $company = Company::query()->create([
            'name' => 'Sort Order Co',
            'slug' => 'sort-order-co',
            'invoice_prefix' => 'SOC',
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
        ]);
        app(CompanyContext::class)->set($company);

        $older = Order::query()->create([
            'customer_name' => 'Older Buyer',
            'status' => 'draft',
            'delivery_status' => CourierBooking::STATUS_NOT_BOOKED,
            'source' => Order::SOURCE_ADMIN,
        ]);
        $older->forceFill(['created_at' => now()->subDay()])->saveQuietly();

        $newer = Order::query()->create([
            'customer_name' => 'Newer Buyer',
            'status' => 'draft',
            'delivery_status' => CourierBooking::STATUS_NOT_BOOKED,
            'source' => Order::SOURCE_ADMIN,
        ]);
        $newer->forceFill(['created_at' => now()])->saveQuietly();

        $user = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

        Livewire::test(ListOrders::class)
            ->assertCanSeeTableRecords([$newer, $older], inOrder: true);
    }
}
