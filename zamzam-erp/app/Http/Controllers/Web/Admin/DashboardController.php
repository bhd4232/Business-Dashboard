<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Core\Product;
use App\Models\Inventory\StockItem;
use App\Models\Inventory\Warehouse;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\Supplier;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Dashboard/Index', [
            'stats' => $this->getStats(),
        ]);
    }

    private function getStats(): array
    {
        // Low stock: products where any stock_item qty <= min_stock_alert
        $lowStockCount = StockItem::whereHas('product', function ($q) {
            $q->whereColumn('stock_items.quantity', '<=', 'products.min_stock_alert')
              ->where('min_stock_alert', '>', 0);
        })->count();

        // Low stock items details (top 5)
        $lowStockItems = StockItem::with(['product', 'warehouse'])
            ->whereHas('product', function ($q) {
                $q->whereColumn('stock_items.quantity', '<=', 'products.min_stock_alert')
                  ->where('min_stock_alert', '>', 0);
            })
            ->limit(5)
            ->get()
            ->map(fn ($s) => [
                'name'      => $s->product?->name ?? 'Unknown',
                'qty'       => $s->quantity,
                'min_qty'   => $s->product?->min_stock_alert ?? 0,
                'warehouse' => $s->warehouse?->name ?? '',
            ]);

        // Pending purchase orders
        $pendingPOs = PurchaseOrder::whereIn('status', ['draft', 'confirmed'])->count();

        // Recent purchase orders (last 5 as proxy for recent activity)
        $recentOrders = PurchaseOrder::with('supplier')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn ($po) => [
                'id'          => $po->id,
                'po_number'   => $po->po_number,
                'supplier'    => $po->supplier?->name_english ?? $po->supplier?->name_chinese ?? '—',
                'total_bdt'   => number_format($po->total_bdt, 0),
                'status'      => $po->status instanceof \BackedEnum ? $po->status->value : $po->status,
                'date'        => $po->order_date?->format('d M') ?? '—',
            ]);

        // Active modules
        $modules = [
            ['label' => 'Procurement',  'active' => true],
            ['label' => 'Inventory',    'active' => true],
            ['label' => 'Sales',        'active' => false],
            ['label' => 'Shipping',     'active' => false],
            ['label' => 'Finance',      'active' => false],
            ['label' => 'Chat & AI',    'active' => false],
        ];

        return [
            'total_suppliers'    => Supplier::count(),
            'total_products'     => Product::count(),
            'total_warehouses'   => Warehouse::count(),
            'total_orders_month' => 0,         // Phase 4
            'revenue_month_bdt'  => 0,         // Phase 4
            'pending_parcels'    => 0,         // Phase 4
            'low_stock_count'    => $lowStockCount,
            'pending_pos'        => $pendingPOs,
            'low_stock_items'    => $lowStockItems,
            'recent_orders'      => $recentOrders,
            'modules'            => $modules,
        ];
    }
}
