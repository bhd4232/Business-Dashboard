<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use App\Services\CustomerDueAlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerDueNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_due_alert_service_finds_customers_with_due_balance(): void
    {
        Customer::query()->create([
            'name' => 'Due Customer',
            'phone' => '+8801712345678',
            'opening_balance' => 250,
            'is_active' => true,
        ]);
        Customer::query()->create([
            'name' => 'Clear Customer',
            'opening_balance' => 0,
            'is_active' => true,
        ]);

        $alerts = app(CustomerDueAlertService::class);

        $this->assertTrue($alerts->hasAlerts());
        $this->assertSame(1, $alerts->count());
        $this->assertSame(250.0, $alerts->totalDue());
        $this->assertSame('Due Customer', $alerts->customers()->first()?->name);
        $this->assertSame('1 customer has BDT 250.00 due.', $alerts->message());
    }

    public function test_dashboard_shows_customer_due_notification_widget(): void
    {
        $admin = User::factory()->create([
            'role' => 'super_admin',
            'is_active' => true,
        ]);
        Customer::query()->create([
            'name' => 'Dashboard Due Customer',
            'phone' => '+8801712345678',
            'opening_balance' => 320,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertSee('Customer Due Notifications')
            ->assertSee('Dashboard Due Customer')
            ->assertSee('1 customer has BDT 320.00 due.');
    }
}
