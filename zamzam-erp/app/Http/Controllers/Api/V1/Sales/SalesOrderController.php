<?php

namespace App\Http\Controllers\Api\V1\Sales;

use App\Http\Controllers\Concerns\HasTrash;
use App\Http\Requests\Sales\StoreSalesOrderRequest;
use App\Http\Requests\Sales\UpdateSalesOrderRequest;
use App\Models\Sales\SalesOrder;
use App\Models\Sales\SoAttachment;
use App\Models\Sales\SoPayment;
use App\Services\Sales\SalesOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SalesOrderController
{
    use HasTrash;

    public function __construct(private SalesOrderService $service) {}

    // ─── List ─────────────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $query = SalesOrder::with(['customer:id,name,business_name,phone'])
            ->orderByDesc('id');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }
        if ($request->filled('source')) {
            $query->where('source', $request->source);
        }

        return response()->json($query->paginate(20));
    }

    // ─── Store ────────────────────────────────────────────────────────────

    public function store(StoreSalesOrderRequest $request): JsonResponse
    {
        $order = $this->service->createSalesOrder($request->validated(), auth()->id());

        return response()->json([
            'message' => 'Sales order created successfully.',
            'order'   => $order,
        ], 201);
    }

    // ─── Show ─────────────────────────────────────────────────────────────

    public function show(SalesOrder $salesOrder): JsonResponse
    {
        $salesOrder->load([
            'customer:id,name,business_name,phone,email',
            'priceTier:id,name,code',
            'items.product:id,name,sku',
            'items.variant:id,variant_name,sku',
            'confirmedBy:id,name',
            'createdBy:id,name',
        ]);

        return response()->json($salesOrder);
    }

    // ─── Update ───────────────────────────────────────────────────────────

    public function update(UpdateSalesOrderRequest $request, SalesOrder $salesOrder): JsonResponse
    {
        if (! $salesOrder->canBeEdited()) {
            return response()->json(['message' => 'Only draft orders can be edited.'], 422);
        }

        $order = $this->service->updateSalesOrder($salesOrder, $request->validated());

        return response()->json([
            'message' => 'Sales order updated successfully.',
            'order'   => $order,
        ]);
    }

    // ─── Bulk Status Change ───────────────────────────────────────────────

    public function bulkStatusChange(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids'    => ['required', 'array', 'min:1'],
            'ids.*'  => ['integer'],
            'status' => ['required', 'string', 'in:draft,on_hold,confirmed,processing,picked,dispatched,delivered,flagged,cancelled,returned'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $results = $this->service->bulkChangeStatus(
            $data['ids'],
            $data['status'],
            $data['reason'] ?? null,
            auth()->id()
        );

        $msg = "Status changed for {$results['success']} order(s).";
        if ($results['failed'] > 0) {
            $msg .= " {$results['failed']} order(s) failed.";
        }

        return response()->json([
            'message' => $msg,
            'results' => $results,
        ], $results['failed'] > 0 && $results['success'] === 0 ? 422 : 200);
    }

    // ─── Confirm ──────────────────────────────────────────────────────────

    public function confirm(SalesOrder $salesOrder): JsonResponse
    {
        try {
            $order = $this->service->confirmSalesOrder($salesOrder, auth()->id());
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => "Order {$order->order_no} confirmed successfully.",
            'order'   => $order,
        ]);
    }

    // ─── Cancel ───────────────────────────────────────────────────────────

    public function cancel(SalesOrder $salesOrder): JsonResponse
    {
        try {
            $order = $this->service->cancelSalesOrder($salesOrder);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => "Order {$order->order_no} has been cancelled.",
            'order'   => $order,
        ]);
    }

    // ─── Receive Payment ──────────────────────────────────────────────────

    public function receivePayment(Request $request, SalesOrder $salesOrder): JsonResponse
    {
        if (! auth()->check()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $data = $request->validate([
            'amount_bdt'   => ['required', 'numeric', 'min:0.01'],
            'method'       => ['required', 'in:cash,bkash,nagad,rocket,bank_transfer,cheque,other'],
            'payment_type' => ['required', 'in:payment,advance'],
            'reference'    => ['nullable', 'string', 'max:100'],
            'payment_date' => ['required', 'date'],
            'notes'        => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $payment = $this->service->receivePayment($salesOrder, $data, auth()->id());
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        // Return fresh order totals along with the new payment
        $salesOrder->refresh();

        return response()->json([
            'message' => 'Payment recorded successfully.',
            'payment' => $payment,
            'order'   => [
                'paid_bdt' => $salesOrder->paid_bdt,
                'due_bdt'  => $salesOrder->due_bdt,
            ],
        ], 201);
    }

    // ─── List Payments ────────────────────────────────────────────────────

    public function payments(SalesOrder $salesOrder): JsonResponse
    {
        $payments = $salesOrder->payments()->with('receivedBy:id,name')->get();

        return response()->json($payments);
    }

    // ─── Update Payment ───────────────────────────────────────────────────

    public function updatePayment(Request $request, SalesOrder $salesOrder, SoPayment $payment): JsonResponse
    {
        if (! auth()->check()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if ($payment->sales_order_id !== $salesOrder->id) {
            return response()->json(['message' => 'Payment does not belong to this order.'], 422);
        }

        $data = $request->validate([
            'amount_bdt'   => ['required', 'numeric', 'min:0.01'],
            'method'       => ['required', 'in:cash,bkash,nagad,rocket,bank_transfer,cheque,other'],
            'payment_type' => ['required', 'in:payment,advance'],
            'reference'    => ['nullable', 'string', 'max:100'],
            'payment_date' => ['required', 'date'],
            'notes'        => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $updated = $this->service->updatePayment($salesOrder, $payment, $data);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $salesOrder->refresh();

        return response()->json([
            'message' => 'Payment updated successfully.',
            'payment' => $updated,
            'order'   => [
                'paid_bdt' => $salesOrder->paid_bdt,
                'due_bdt'  => $salesOrder->due_bdt,
            ],
        ]);
    }

    // ─── Attachments ──────────────────────────────────────────────────────

    public function storeAttachment(Request $request, SalesOrder $salesOrder): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:10240'], // 10 MB
        ]);

        $file      = $request->file('file');
        $path      = $file->store("sales-orders/{$salesOrder->id}/attachments", 'public');

        $attachment = SoAttachment::create([
            'sales_order_id' => $salesOrder->id,
            'original_name'  => $file->getClientOriginalName(),
            'file_path'      => $path,
            'mime_type'      => $file->getMimeType(),
            'file_size'      => $file->getSize(),
            'uploaded_by'    => auth()->id(),
        ]);

        return response()->json([
            'message'    => 'Attachment uploaded.',
            'attachment' => $attachment,
        ], 201);
    }

    public function attachments(SalesOrder $salesOrder): JsonResponse
    {
        return response()->json($salesOrder->attachments()->with('uploadedBy:id,name')->get());
    }

    public function destroyAttachment(SalesOrder $salesOrder, SoAttachment $attachment): JsonResponse
    {
        if ($attachment->sales_order_id !== $salesOrder->id) {
            return response()->json(['message' => 'Attachment does not belong to this order.'], 422);
        }

        Storage::disk('public')->delete($attachment->file_path);
        $attachment->delete();

        return response()->json(['message' => 'Attachment deleted.']);
    }

    // ─── Destroy (soft delete) ────────────────────────────────────────────

    public function destroy(SalesOrder $salesOrder): JsonResponse
    {
        if (! $salesOrder->canBeDeleted()) {
            return response()->json(['message' => 'Only draft or cancelled orders can be deleted.'], 422);
        }

        return $this->softDelete($salesOrder, 'Sales order');
    }

    // ─── Trash ────────────────────────────────────────────────────────────

    public function trashed(): JsonResponse
    {
        $orders = SalesOrder::onlyTrashed()
            ->with(['customer:id,name,business_name'])
            ->orderByDesc('deleted_at')
            ->get();

        return response()->json($orders);
    }

    public function restore(int $id): JsonResponse
    {
        $order = SalesOrder::onlyTrashed()->findOrFail($id);

        return $this->restoreModel($order, 'Sales order');
    }

    public function forceDelete(int $id): JsonResponse
    {
        $order = SalesOrder::onlyTrashed()->findOrFail($id);

        return $this->purgeModel($order, 'Sales order');
    }
}
