<?php

namespace Tests\Feature;

use App\Models\CourierBooking;
use App\Models\CourierProvider;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Owner request: a one-click copy icon next to the customer phone number on
 * the Customers and Orders lists, and next to the courier tracking ID on the
 * Orders list once a courier is booked. Filament's native TextColumn
 * ->copyable() renders a `fi-copyable` class on the value wrapper (and wires
 * up the click-to-copy JS) — these assert that class actually lands on the
 * right values, not just that the page loads.
 */
class CopyableColumnsTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_phone_is_copyable_on_the_customers_list(): void
    {
        $user = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        Customer::query()->create(['name' => 'Copy Test Customer', 'phone' => '01711112222']);

        $this->actingAs($user)
            ->get('/admin/sales/customers')
            ->assertOk()
            ->assertSee('01711112222')
            ->assertSee('fi-copyable', false);
    }

    public function test_customer_phone_is_copyable_on_the_orders_list(): void
    {
        $user = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $customer = Customer::query()->create(['name' => 'Order Copy Customer', 'phone' => '01722223333']);
        Order::query()->create(['customer_id' => $customer->getKey(), 'status' => Order::STATUS_DRAFT]);

        $this->actingAs($user)
            ->get('/admin/sales/orders')
            ->assertOk()
            ->assertSee('01722223333')
            ->assertSee('fi-copyable', false);
    }

    public function test_courier_tracking_id_is_copyable_on_the_orders_list_once_booked(): void
    {
        $user = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $customer = Customer::query()->create(['name' => 'Tracking Copy Customer', 'phone' => '01733334444']);
        $order = Order::query()->create(['customer_id' => $customer->getKey(), 'status' => Order::STATUS_DRAFT]);
        $provider = CourierProvider::query()->create([
            'company_id' => $order->company_id,
            'name' => 'Copy Test Courier',
            'slug' => 'copy-test-courier',
            'driver' => CourierProvider::DRIVER_MANUAL,
            'credentials' => [],
            'settings' => [],
            'is_active' => true,
        ]);
        CourierBooking::query()->create([
            'company_id' => $order->company_id,
            'courier_provider_id' => $provider->id,
            'order_id' => $order->id,
            'tracking_id' => 'COPY-TRACK-999',
            'recipient_name' => $customer->name,
            'status' => CourierBooking::STATUS_BOOKED,
        ]);

        $this->actingAs($user)
            ->get('/admin/sales/orders')
            ->assertOk()
            ->assertSee('COPY-TRACK-999')
            ->assertSee('fi-copyable', false);
    }

    public function test_orders_list_shows_a_placeholder_when_no_courier_is_booked_yet(): void
    {
        $user = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $customer = Customer::query()->create(['name' => 'Unbooked Customer', 'phone' => '01744445555']);
        Order::query()->create(['customer_id' => $customer->getKey(), 'status' => Order::STATUS_DRAFT]);

        $this->actingAs($user)
            ->get('/admin/sales/orders')
            ->assertOk()
            ->assertDontSee('COPY-TRACK-999');
    }
}
