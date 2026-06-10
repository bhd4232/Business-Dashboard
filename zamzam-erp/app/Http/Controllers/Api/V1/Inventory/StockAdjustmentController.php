<?php

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreStockAdjustmentRequest;
use App\Models\Inventory\StockAdjustment;
use App\Services\Inventory\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockAdjustmentController extends Controller
{
    public function __construct(private StockService $service) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('inventory.view');

        $query = StockAdjustment::with(['warehouse', 'createdBy'])
            ->when($request->warehouse_id, fn($q, $w) => $q->where('warehouse_id', $w))
            ->when($request->type, fn($q, $t) => $q->where('type', $t))
            ->orderByDesc('created_at');

        return response()->json($query->paginate(25));
    }

    public function store(StoreStockAdjustmentRequest $request): JsonResponse
    {
        $adjustment = $this->service->applyAdjustment($request->validated(), auth()->id());

        return response()->json($adjustment, 201);
    }

    public function show(StockAdjustment $stockAdjustment): JsonResponse
    {
        $this->authorize('inventory.view');

        return response()->json(
            $stockAdjustment->load(['warehouse', 'items.product', 'items.variant', 'createdBy', 'approvedBy'])
        );
    }
}
