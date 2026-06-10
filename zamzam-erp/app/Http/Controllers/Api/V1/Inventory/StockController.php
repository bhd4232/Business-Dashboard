<?php

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\StockItem;
use App\Models\Inventory\StockTransaction;
use App\Services\Inventory\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockController extends Controller
{
    public function __construct(private StockService $service) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('inventory.view');

        $query = StockItem::with(['product.category', 'variant', 'warehouse'])
            ->when($request->warehouse_id, fn($q, $w) => $q->where('warehouse_id', $w))
            ->when($request->category_id, fn($q, $c) =>
                $q->whereHas('product', fn($pq) => $pq->where('category_id', $c))
            )
            ->when($request->search, fn($q, $s) =>
                $q->whereHas('product', fn($pq) =>
                    $pq->where('name', 'like', "%{$s}%")->orWhere('sku', 'like', "%{$s}%")
                )
            )
            ->when($request->boolean('low_stock'), fn($q) =>
                $q->whereHas('product', fn($pq) =>
                    $pq->whereColumn('stock_items.quantity', '<=', 'products.min_stock_alert')
                       ->where('min_stock_alert', '>', 0)
                )
            )
            ->orderByDesc('updated_at');

        return response()->json($query->paginate(50));
    }

    public function show(int $productId, Request $request): JsonResponse
    {
        $this->authorize('inventory.view');

        $query = StockItem::with(['warehouse'])
            ->where('product_id', $productId)
            ->when($request->variant_id, fn($q, $v) => $q->where('product_variant_id', $v));

        return response()->json($query->get());
    }

    public function lowStock(): JsonResponse
    {
        $this->authorize('inventory.view');

        return response()->json($this->service->getLowStockItems());
    }

    public function valuation(Request $request): JsonResponse
    {
        $this->authorize('inventory.view');

        $warehouseId = $request->warehouse_id;
        $total = $this->service->getStockValuation($warehouseId);

        return response()->json(['total_value_bdt' => $total, 'warehouse_id' => $warehouseId]);
    }

    public function transactions(Request $request): JsonResponse
    {
        $this->authorize('inventory.view');

        $query = StockTransaction::with(['product', 'variant', 'warehouse', 'createdBy'])
            ->when($request->product_id, fn($q, $p) => $q->where('product_id', $p))
            ->when($request->warehouse_id, fn($q, $w) => $q->where('warehouse_id', $w))
            ->when($request->type, fn($q, $t) => $q->where('type', $t))
            ->orderByDesc('created_at');

        return response()->json($query->paginate(50));
    }
}
