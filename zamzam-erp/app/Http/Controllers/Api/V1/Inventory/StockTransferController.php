<?php

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreStockTransferRequest;
use App\Models\Inventory\StockTransfer;
use App\Services\Inventory\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockTransferController extends Controller
{
    public function __construct(private StockService $service) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('stock_transfers.view');

        $query = StockTransfer::with(['fromWarehouse', 'toWarehouse', 'createdBy'])
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->when($request->warehouse_id, fn($q, $w) =>
                $q->where(fn($q) =>
                    $q->where('from_warehouse_id', $w)->orWhere('to_warehouse_id', $w)
                )
            )
            ->orderByDesc('created_at');

        return response()->json($query->paginate(25));
    }

    public function store(StoreStockTransferRequest $request): JsonResponse
    {
        $transfer = $this->service->createTransfer($request->validated(), auth()->id());

        return response()->json($transfer, 201);
    }

    public function show(StockTransfer $stockTransfer): JsonResponse
    {
        $this->authorize('stock_transfers.view');

        return response()->json(
            $stockTransfer->load(['fromWarehouse', 'toWarehouse', 'items.product', 'items.variant', 'createdBy'])
        );
    }

    public function dispatch(StockTransfer $stockTransfer): JsonResponse
    {
        $this->authorize('stock_transfers.create');

        if ($stockTransfer->status->value !== 'pending') {
            return response()->json(['message' => 'Only pending transfers can be dispatched.'], 422);
        }

        $stockTransfer->update(['status' => \App\Enums\TransferStatus::InTransit]);

        return response()->json($stockTransfer->refresh());
    }

    public function complete(StockTransfer $stockTransfer): JsonResponse
    {
        $this->authorize('stock_transfers.create');

        $transfer = $this->service->completeTransfer($stockTransfer, auth()->id());

        return response()->json($transfer);
    }

    public function cancel(StockTransfer $stockTransfer): JsonResponse
    {
        $this->authorize('stock_transfers.create');

        if (! $stockTransfer->canBeCancelled()) {
            return response()->json(['message' => 'This transfer cannot be cancelled.'], 422);
        }

        $stockTransfer->update(['status' => \App\Enums\TransferStatus::Cancelled]);

        return response()->json($stockTransfer->refresh());
    }
}
