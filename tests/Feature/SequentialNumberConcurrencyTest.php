<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Order;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Services\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards the GeneratesSequentialNumber concern (audit M-1): when a concurrent
 * request grabs the number an insert was about to use, the insert must retry
 * and mint a fresh unique number instead of failing on the UNIQUE index.
 */
class SequentialNumberConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_number_collision_is_retried_and_resolved(): void
    {
        $company = $this->company();
        app(CompanyContext::class)->set($company);

        $first = Order::query()->create([
            'customer_name' => 'First Buyer',
            'status' => 'draft',
            'source' => Order::SOURCE_STOREFRONT,
        ]);

        // The next order's first-choice number is stolen once by a simulated
        // concurrent request, forcing the retry path.
        $stole = false;
        Order::creating(function (Order $order) use (&$stole): void {
            if ($stole || blank($order->order_number)) {
                return;
            }

            $stole = true;

            Order::query()->create([
                'order_number' => $order->order_number,
                'customer_name' => 'Concurrent Buyer',
                'status' => 'draft',
                'source' => Order::SOURCE_STOREFRONT,
            ]);
        });

        $second = Order::query()->create([
            'customer_name' => 'Second Buyer',
            'status' => 'draft',
            'source' => Order::SOURCE_STOREFRONT,
        ]);

        $this->assertTrue($stole);
        $this->assertNotSame($first->order_number, $second->order_number);
        $this->assertSame(1, Order::query()->where('order_number', $second->order_number)->count());
        $this->assertSame(3, Order::query()->distinct()->count('order_number'));
    }

    public function test_purchase_number_collision_is_retried_and_resolved(): void
    {
        $company = $this->company();
        app(CompanyContext::class)->set($company);

        $supplier = Supplier::query()->create(['name' => 'Concurrency Supplier']);

        $first = Purchase::query()->create([
            'supplier_id' => $supplier->id,
            'purchase_date' => now()->toDateString(),
            'status' => 'draft',
        ]);

        $stole = false;
        Purchase::creating(function (Purchase $purchase) use (&$stole, $supplier): void {
            if ($stole || blank($purchase->purchase_number)) {
                return;
            }

            $stole = true;

            Purchase::query()->create([
                'purchase_number' => $purchase->purchase_number,
                'supplier_id' => $supplier->id,
                'purchase_date' => now()->toDateString(),
                'status' => 'draft',
            ]);
        });

        $second = Purchase::query()->create([
            'supplier_id' => $supplier->id,
            'purchase_date' => now()->toDateString(),
            'status' => 'draft',
        ]);

        $this->assertTrue($stole);
        $this->assertNotSame($first->purchase_number, $second->purchase_number);
        $this->assertSame(1, Purchase::query()->where('purchase_number', $second->purchase_number)->count());
        $this->assertSame(3, Purchase::query()->distinct()->count('purchase_number'));
    }

    private function company(): Company
    {
        return Company::query()->create([
            'name' => 'Concurrency Co',
            'slug' => 'concurrency-co',
            'invoice_prefix' => 'CNC',
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
        ]);
    }
}
