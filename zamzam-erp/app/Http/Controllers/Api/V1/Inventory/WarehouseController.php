<?php

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Http\Controllers\Concerns\HasTrash;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreWarehouseRequest;
use App\Models\Inventory\Warehouse;
use App\Services\Inventory\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WarehouseController extends Controller
{
    use HasTrash;
    public function __construct(private StockService $stockService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('warehouses.view');

        $warehouses = Warehouse::active()
            ->withCount('stockItems')
            ->get()
            ->map(function ($w) {
                $w->stock_value_bdt = $this->stockService->getStockValuation($w->id);
                return $w;
            });

        return response()->json($warehouses);
    }

    public function store(StoreWarehouseRequest $request): JsonResponse
    {
        $data = $request->validated();

        if ($request->boolean('is_default')) {
            Warehouse::where('is_default', true)->update(['is_default' => false]);
        }

        $warehouse = Warehouse::create($data);

        return response()->json($warehouse, 201);
    }

    public function show(Warehouse $warehouse): JsonResponse
    {
        $this->authorize('warehouses.view');

        $warehouse->load('stockItems.product.category');
        $warehouse->stock_value_bdt = $this->stockService->getStockValuation($warehouse->id);

        return response()->json($warehouse);
    }

    public function update(Request $request, Warehouse $warehouse): JsonResponse
    {
        $this->authorize('warehouses.manage');

        $data = $request->validate([
            'name'       => 'sometimes|string|max:255',
            'address'    => 'nullable|string',
            'city'       => 'nullable|string|max:100',
            'is_default' => 'nullable|boolean',
            'is_active'  => 'nullable|boolean',
        ]);

        if ($request->boolean('is_default')) {
            Warehouse::where('id', '!=', $warehouse->id)
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }

        $warehouse->update($data);

        return response()->json($warehouse);
    }

    public function destroy(Warehouse $warehouse): JsonResponse
    {
        $this->authorize('warehouses.manage');

        if ($warehouse->stockItems()->where('quantity', '>', 0)->exists()) {
            return response()->json(['message' => 'Cannot trash a warehouse with existing stock.'], 422);
        }

        return $this->softDelete($warehouse, 'Warehouse');
    }

    public function trashed(Request $request): JsonResponse
    {
        $this->authorize('warehouses.view');
        $warehouses = Warehouse::onlyTrashed()->orderByDesc('deleted_at')->get();
        return response()->json($warehouses);
    }

    public function restore(int $id): JsonResponse
    {
        $this->authorize('warehouses.manage');
        $warehouse = Warehouse::onlyTrashed()->findOrFail($id);
        return $this->restoreModel($warehouse, 'Warehouse');
    }

    public function forceDelete(int $id): JsonResponse
    {
        $warehouse = Warehouse::onlyTrashed()->findOrFail($id);
        return $this->purgeModel($warehouse, 'Warehouse');
    }
}
