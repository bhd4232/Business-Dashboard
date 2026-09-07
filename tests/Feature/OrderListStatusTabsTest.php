<?php

namespace Tests\Feature;

use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Models\Company;
use App\Models\CourierBooking;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use App\Services\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Owner request: status quick-filter tabs above the Orders list (Nuport-style
 * order dashboard) — "All" plus one tab per Order::STATUSES value, clicking a
 * tab shows only orders in that status.
 */
class OrderListStatusTabsTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_order_button_sits_beside_the_title_on_mobile(): void
    {
        $company = $this->company();
        app(CompanyContext::class)->set($company);
        $this->actingAs($this->admin())->withSession(['current_company_id' => $company->id]);

        Livewire::test(ListOrders::class)
            ->assertSeeHtml('zz-orders-mobile-header-action');

        $theme = file_get_contents(resource_path('css/filament/admin/theme.css'));
        $this->assertStringContainsString('.fi-header:has(.zz-orders-mobile-header-action)', $theme);
        $this->assertStringContainsString('@media (max-width: 63.999rem)', $theme);
    }

    public function test_status_tabs_exist_for_all_and_every_order_status(): void
    {
        $company = $this->company();
        app(CompanyContext::class)->set($company);
        $this->actingAs($this->admin())->withSession(['current_company_id' => $company->id]);

        $tabs = Livewire::test(ListOrders::class)->instance()->getTabs();

        $this->assertSame(
            array_merge(['all'], array_keys(Order::STATUSES)),
            array_keys($tabs),
        );
    }

    public function test_selecting_a_status_tab_scopes_the_list_and_the_all_tab_shows_everything(): void
    {
        $company = $this->company();
        app(CompanyContext::class)->set($company);
        $customer = Customer::query()->create([
            'name' => 'Tab Buyer', 'phone' => '01700000123', 'opening_balance' => 0, 'is_active' => true,
        ]);

        $draft = $this->order($customer, 'draft');
        $confirmed = $this->order($customer, 'confirmed');
        $cancelled = $this->order($customer, 'cancelled');

        $this->actingAs($this->admin())->withSession(['current_company_id' => $company->id]);

        Livewire::test(ListOrders::class)
            ->assertCanSeeTableRecords([$draft, $confirmed, $cancelled])
            ->set('activeTab', 'confirmed')
            ->assertCanSeeTableRecords([$confirmed])
            ->assertCanNotSeeTableRecords([$draft, $cancelled])
            ->set('activeTab', 'draft')
            ->assertCanSeeTableRecords([$draft])
            ->assertCanNotSeeTableRecords([$confirmed, $cancelled])
            ->set('activeTab', 'all')
            ->assertCanSeeTableRecords([$draft, $confirmed, $cancelled]);
    }

    protected function company(): Company
    {
        return Company::query()->create([
            'name' => 'Status Tabs Co', 'slug' => 'status-tabs-co',
            'invoice_prefix' => 'STT', 'currency' => 'BDT', 'timezone' => 'Asia/Dhaka', 'is_active' => true,
        ]);
    }

    protected function admin(): User
    {
        return User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
    }

    protected function order(Customer $customer, string $status): Order
    {
        return Order::query()->create([
            'customer_id' => $customer->getKey(),
            'customer_name' => $customer->name,
            'status' => $status,
            'delivery_status' => CourierBooking::STATUS_NOT_BOOKED,
            'source' => Order::SOURCE_ADMIN,
        ]);
    }
}
