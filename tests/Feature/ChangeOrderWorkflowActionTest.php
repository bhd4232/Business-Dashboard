<?php

namespace Tests\Feature;

use App\Filament\Resources\Orders\Pages\EditOrder;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use App\Services\CompanyContext;
use App\Services\OrderStatusWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

/**
 * Coverage for the "Change status" action (App\Filament\Resources\Orders\
 * Actions\ChangeOrderWorkflowAction), shared by the order Edit page, the
 * order View page, and the Orders list row action. Owner-reported bug: from
 * the edit page, picking "Confirmed" and submitting the modal appeared to do
 * nothing. Manual reproduction (real browser against the running app) found
 * the transition itself works end to end; this suite locks that in and adds
 * the missing coverage, plus the new "don't fail silently" behaviour for any
 * unexpected error the underlying service throws.
 */
class ChangeOrderWorkflowActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_transitions_a_draft_order_to_confirmed_and_notifies_with_the_new_status(): void
    {
        $order = $this->draftOrder();

        Livewire::test(EditOrder::class, ['record' => $order->getKey()])
            ->callAction('changeWorkflowStatus', data: ['stage' => 'confirmed'])
            ->assertHasNoActionErrors()
            ->assertNotified('Order status updated');

        $this->assertSame('confirmed', $order->fresh()->status);
        $this->assertSame(1, $order->fresh()->statusTransitions()->count());
    }

    public function test_it_rejects_a_transition_the_order_is_not_allowed_to_make(): void
    {
        $order = $this->draftOrder();

        Livewire::test(EditOrder::class, ['record' => $order->getKey()])
            // Draft can only go to confirmed or cancelled -- refunded is not
            // reachable directly, even though it's a valid stage in general.
            ->callAction('changeWorkflowStatus', data: ['stage' => 'refunded'])
            ->assertHasActionErrors(['stage']);

        $this->assertSame('draft', $order->fresh()->status);
    }

    public function test_it_requires_a_reason_for_cancellation(): void
    {
        $order = $this->draftOrder();

        Livewire::test(EditOrder::class, ['record' => $order->getKey()])
            ->callAction('changeWorkflowStatus', data: ['stage' => 'cancelled', 'reason' => ''])
            ->assertHasActionErrors(['reason']);

        $this->assertSame('draft', $order->fresh()->status);
    }

    public function test_it_also_works_as_the_row_action_on_the_orders_list(): void
    {
        $order = $this->draftOrder();

        Livewire::test(ListOrders::class)
            ->callTableAction('changeWorkflowStatus', $order, data: ['stage' => 'confirmed'])
            ->assertHasNoTableActionErrors()
            ->assertNotified('Order status updated');

        $this->assertSame('confirmed', $order->fresh()->status);
    }

    public function test_an_unexpected_failure_is_reported_and_shown_instead_of_failing_silently(): void
    {
        $order = $this->draftOrder();

        $this->mock(OrderStatusWorkflowService::class)
            ->shouldReceive('transition')
            ->once()
            ->andThrow(new RuntimeException('simulated workflow failure'));

        Livewire::test(EditOrder::class, ['record' => $order->getKey()])
            ->callAction('changeWorkflowStatus', data: ['stage' => 'confirmed'])
            ->assertActionHalted('changeWorkflowStatus')
            ->assertNotified('Could not update the order status');

        $this->assertSame('draft', $order->fresh()->status);
    }

    protected function draftOrder(): Order
    {
        $company = Company::query()->create([
            'name' => 'Workflow Action Co',
            'slug' => 'workflow-action-co',
            'invoice_prefix' => 'WAC',
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
        ]);
        app(CompanyContext::class)->set($company);

        $customer = Customer::query()->create([
            'name' => 'Workflow Buyer',
            'phone' => '01744444444',
            'opening_balance' => 0,
            'is_active' => true,
        ]);

        $order = Order::query()->create([
            'customer_id' => $customer->id,
            'customer_name' => 'Workflow Buyer',
            'status' => Order::STATUS_DRAFT,
            'source' => Order::SOURCE_ADMIN,
        ]);

        $admin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        $this->actingAs($admin)->withSession(['current_company_id' => $company->id]);

        return $order;
    }
}
