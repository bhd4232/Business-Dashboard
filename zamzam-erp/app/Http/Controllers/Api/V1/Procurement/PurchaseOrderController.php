<?php

namespace App\Http\Controllers\Api\V1\Procurement;

use App\Http\Controllers\Concerns\HasTrash;
use App\Http\Controllers\Controller;
use App\Http\Requests\Procurement\StorePurchaseOrderRequest;
use App\Models\Procurement\PurchaseOrder;
use App\Services\Procurement\ProcurementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseOrderController extends Controller
{
    use HasTrash;
    public function __construct(private ProcurementService $service) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('purchase_orders.view');

        $query = PurchaseOrder::with(['supplier', 'createdBy'])
            ->when($request->search, fn($q, $s) =>
                $q->where('po_number', 'like', "%{$s}%")
            )
            ->when($request->supplier_id, fn($q, $id) => $q->where('supplier_id', $id))
            ->when($request->status, fn($q, $st) => $q->where('status', $st))
            ->when($request->date_from, fn($q, $d) => $q->where('order_date', '>=', $d))
            ->when($request->date_to, fn($q, $d) => $q->where('order_date', '<=', $d))
            ->orderByDesc('created_at');

        return response()->json($query->paginate(25));
    }

    public function store(StorePurchaseOrderRequest $request): JsonResponse
    {
        $po = $this->service->createPurchaseOrder($request->validated(), auth()->id());

        return response()->json($po, 201);
    }

    public function show(PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->authorize('purchase_orders.view');

        return response()->json(
            $purchaseOrder->load(['supplier', 'items.product', 'items.variant', 'createdBy', 'approvedBy'])
        );
    }

    public function update(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->authorize('purchase_orders.edit');

        $data = $request->validate([
            'supplier_id'            => 'required|exists:suppliers,id',
            'currency_id'            => 'required|exists:currencies,id',
            'exchange_rate'          => 'required|numeric|min:0.000001',
            'order_date'             => 'required|date',
            'expected_delivery_date' => 'nullable|date|after:order_date',
            'notes'                  => 'nullable|string',
            'items'                      => 'required|array|min:1',
            'items.*.product_id'         => 'required|exists:products,id',
            'items.*.product_variant_id' => 'nullable|exists:product_variants,id',
            'items.*.supplier_price_cny' => 'required|numeric|min:0.01',
            'items.*.quantity'           => 'required|integer|min:1',
            'items.*.approx_weight_kg'   => 'nullable|numeric|min:0',
        ]);

        $po = $this->service->updatePurchaseOrder($purchaseOrder, $data);

        return response()->json($po);
    }

    public function confirm(PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->authorize('purchase_orders.confirm');

        $po = $this->service->confirmPurchaseOrder($purchaseOrder, auth()->id());

        return response()->json($po);
    }

    public function cancel(PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->authorize('purchase_orders.edit');

        $po = $this->service->cancelPurchaseOrder($purchaseOrder);

        return response()->json($po);
    }

    public function destroy(PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->authorize('purchase_orders.delete');
        if (!in_array($purchaseOrder->status->value, ['draft', 'cancelled'])) {
            return response()->json(['message' => 'Only draft or cancelled orders can be deleted.'], 422);
        }
        return $this->softDelete($purchaseOrder, 'Purchase Order');
    }

    public function trashed(Request $request): JsonResponse
    {
        $this->authorize('purchase_orders.view');
        return response()->json(PurchaseOrder::onlyTrashed()->with('supplier')->orderByDesc('deleted_at')->get());
    }

    public function restore(int $id): JsonResponse
    {
        $this->authorize('purchase_orders.edit');
        return $this->restoreModel(PurchaseOrder::onlyTrashed()->findOrFail($id), 'Purchase Order');
    }

    public function forceDelete(int $id): JsonResponse
    {
        return $this->purgeModel(PurchaseOrder::onlyTrashed()->findOrFail($id), 'Purchase Order');
    }
}
