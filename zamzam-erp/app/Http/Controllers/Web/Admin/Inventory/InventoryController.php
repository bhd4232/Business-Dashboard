<?php

namespace App\Http\Controllers\Web\Admin\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\StockAdjustment;
use App\Models\Inventory\StockItem;
use App\Models\Inventory\StockTransfer;
use App\Models\Inventory\Warehouse;
use App\Services\Inventory\StockService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InventoryController extends Controller
{
    public function __construct(private StockService $service) {}

    public function warehouses(Request $request): Response
    {
        $this->authorize('inventory.view');

        $warehouses = Warehouse::active()
            ->withCount('stockItems')
            ->get()
            ->map(fn($w) => array_merge($w->toArray(), [
                'stock_value_bdt' => $this->service->getStockValuation($w->id),
            ]));

        return Inertia::render('Inventory/Warehouses/Index', [
            'warehouses' => $warehouses,
        ]);
    }

    public function stock(Request $request): Response
    {
        $this->authorize('inventory.view');

        $stocks = StockItem::with(['product.category', 'variant', 'warehouse'])
            ->when($request->warehouse_id, fn($q, $w) => $q->where('warehouse_id', $w))
            ->when($request->search, fn($q, $s) =>
                $q->whereHas('product', fn($pq) =>
                    $pq->where('name', 'like', "%{$s}%")->orWhere('sku', 'like', "%{$s}%")
                )
            )
            ->orderByDesc('updated_at')
            ->paginate(50)
            ->withQueryString();

        $warehouses = Warehouse::active()->select('id', 'name', 'code')->get();

        return Inertia::render('Inventory/Stock/Index', [
            'stocks'     => $stocks,
            'warehouses' => $warehouses,
            'filters'    => $request->only(['warehouse_id', 'search']),
            'total_value' => $this->service->getStockValuation($request->warehouse_id),
        ]);
    }

    public function stockShow(int $productId): Response
    {
        $this->authorize('inventory.view');

        $items = StockItem::with(['warehouse'])
            ->where('product_id', $productId)
            ->get();

        $product = \App\Models\Core\Product::with(['category', 'variants', 'barcodes'])
            ->findOrFail($productId);

        return Inertia::render('Inventory/Stock/Show', [
            'product'    => $product,
            'stockItems' => $items,
        ]);
    }

    public function lowStock(): Response
    {
        $this->authorize('inventory.view');

        $items = $this->service->getLowStockItems();

        return Inertia::render('Inventory/Stock/LowStock', [
            'items' => $items,
        ]);
    }

    public function transfers(Request $request): Response
    {
        $this->authorize('inventory.view');

        $transfers = StockTransfer::with(['fromWarehouse', 'toWarehouse', 'createdBy'])
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        $warehouses = Warehouse::active()->select('id', 'name')->get();

        return Inertia::render('Inventory/StockTransfers/Index', [
            'transfers'  => $transfers,
            'warehouses' => $warehouses,
            'filters'    => $request->only(['status']),
        ]);
    }

    public function transferCreate(): Response
    {
        $this->authorize('stock_transfers.create');

        $warehouses = Warehouse::active()->select('id', 'name', 'code')->get();

        return Inertia::render('Inventory/StockTransfers/Create', [
            'warehouses' => $warehouses,
        ]);
    }

    public function transferShow(StockTransfer $stockTransfer): Response
    {
        $this->authorize('inventory.view');

        return Inertia::render('Inventory/StockTransfers/Show', [
            'transfer' => $stockTransfer->load([
                'fromWarehouse', 'toWarehouse',
                'items.product.category', 'items.variant', 'createdBy',
            ]),
        ]);
    }

    public function adjustments(Request $request): Response
    {
        $this->authorize('inventory.view');

        $adjustments = StockAdjustment::with(['warehouse', 'createdBy'])
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        $warehouses = Warehouse::active()->select('id', 'name')->get();

        return Inertia::render('Inventory/StockAdjustments/Index', [
            'adjustments' => $adjustments,
            'warehouses'  => $warehouses,
        ]);
    }

    public function adjustmentCreate(): Response
    {
        $this->authorize('inventory.adjust');

        $warehouses = Warehouse::active()->select('id', 'name', 'code')->get();

        return Inertia::render('Inventory/StockAdjustments/Create', [
            'warehouses' => $warehouses,
        ]);
    }

    public function adjustmentShow(StockAdjustment $stockAdjustment): Response
    {
        $this->authorize('inventory.view');

        return Inertia::render('Inventory/StockAdjustments/Show', [
            'adjustment' => $stockAdjustment->load([
                'warehouse', 'items.product', 'items.variant',
                'createdBy', 'approvedBy',
            ]),
        ]);
    }

    public function barcodes(Request $request): Response
    {
        $this->authorize('inventory.view');

        $barcodes = \App\Models\Inventory\Barcode::with(['product', 'variant'])
            ->when($request->product_id, fn($q, $p) => $q->where('product_id', $p))
            ->orderByDesc('created_at')
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('Inventory/Barcodes/Index', [
            'barcodes' => $barcodes,
        ]);
    }
}
