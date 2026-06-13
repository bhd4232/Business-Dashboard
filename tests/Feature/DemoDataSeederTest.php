<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Expense;
use App\Models\Order;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\TransactionLedger;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DemoDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_data_seeder_creates_dashboard_ready_records(): void
    {
        $this->seed(DemoDataSeeder::class);

        $this->assertDatabaseHas('products', ['sku' => 'DEMO-ROUTER-001']);
        $this->assertDatabaseHas('customers', ['email' => 'farhan.retail@example.com']);
        $this->assertDatabaseHas('suppliers', ['email' => 'sales@shenzhen-demo.example']);
        $this->assertDatabaseHas('orders', ['order_number' => 'INV-DEMO-0001']);
        $this->assertDatabaseHas('purchases', ['purchase_number' => 'PUR-DEMO-0001']);

        $this->assertGreaterThan(0, Product::query()->where('sku', 'DEMO-ROUTER-001')->value('stock'));
        $this->assertGreaterThan(0, Customer::query()->where('email', 'farhan.retail@example.com')->value('current_balance'));
        $this->assertGreaterThan(0, TransactionLedger::query()->count());

        $this->assertSame(10, Product::query()->where('sku', 'like', 'DEMO-%')->where('status', Product::STATUS_AVAILABLE)->count());
        $this->assertSame(10, Customer::query()->count());
        $this->assertSame(10, Supplier::query()->count());
        $this->assertSame(10, Order::query()->where('order_number', 'like', 'INV-DEMO-%')->count());
        $this->assertSame(10, Purchase::query()->where('purchase_number', 'like', 'PUR-DEMO-%')->count());
        $this->assertSame(10, CustomerPayment::query()->where('payment_number', 'like', 'CPAY-DEMO-%')->count());
        $this->assertSame(10, SupplierPayment::query()->where('payment_number', 'like', 'SPAY-DEMO-%')->count());
        $this->assertSame(10, Expense::query()->where('expense_number', 'like', 'EXP-DEMO-%')->count());

        $this->assertSame(10, Order::query()->whereDate('order_date', today())->where('order_number', 'like', 'INV-DEMO-%')->count());
        $this->assertSame(10, Purchase::query()->whereDate('purchase_date', today())->where('purchase_number', 'like', 'PUR-DEMO-%')->count());
        $this->assertSame(10, Expense::query()->whereDate('expense_date', today())->where('expense_number', 'like', 'EXP-DEMO-%')->count());
    }

    public function test_demo_refresh_command_uses_isolated_database(): void
    {
        $demoPath = database_path('test-demo.sqlite');

        File::delete($demoPath);

        User::query()->create([
            'name' => 'Main Admin',
            'email' => 'main@example.com',
            'password' => 'password',
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        Artisan::call('demo:refresh', [
            '--database' => $demoPath,
        ]);

        $this->assertDatabaseHas('users', ['email' => 'main@example.com']);
        $this->assertDatabaseMissing('users', ['email' => 'demo@example.com']);

        $originalDefault = config('database.default');
        $originalDemoPath = config('database.connections.demo.database');

        Config::set('database.default', 'demo');
        Config::set('database.connections.demo.database', $demoPath);
        DB::purge('demo');

        try {
            $this->assertSame(1, User::query()->where('email', 'demo@example.com')->count());
            $this->assertSame(10, Product::query()->where('sku', 'like', 'DEMO-%')->where('status', Product::STATUS_AVAILABLE)->count());
            $this->assertSame(10, Order::query()->whereDate('order_date', today())->where('order_number', 'like', 'INV-DEMO-%')->count());
        } finally {
            Config::set('database.default', $originalDefault);
            Config::set('database.connections.demo.database', $originalDemoPath);
            DB::purge('demo');
            File::delete($demoPath);
        }
    }
}
