<?php

namespace App\Http\Controllers\Api\V1\Sales;

use App\Http\Requests\Sales\StoreInvoiceRequest;
use App\Http\Requests\Sales\UpdateInvoiceRequest;
use App\Models\Sales\Invoice;
use App\Models\Sales\SalesOrder;
use App\Services\Sales\InvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceController
{
    public function __construct(private InvoiceService $service) {}

    // ─── List ─────────────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $query = Invoice::with(['customer:id,name,business_name,phone'])
            ->orderByDesc('id');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('issue_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('issue_date', '<=', $request->date_to);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('invoice_no', 'like', "%{$s}%")
                  ->orWhereHas('customer', fn ($q2) =>
                      $q2->where('name', 'like', "%{$s}%")
                         ->orWhere('business_name', 'like', "%{$s}%")
                         ->orWhere('phone', 'like', "%{$s}%")
                  );
            });
        }

        return response()->json($query->paginate(20));
    }

    // ─── Store ────────────────────────────────────────────────────────────

    public function store(StoreInvoiceRequest $request): JsonResponse
    {
        $data = $request->validated();

        // If sales_order_id is provided, use createFromSalesOrder for proper SO-copy
        if (! empty($data['sales_order_id'])) {
            $order = SalesOrder::with(['items'])->findOrFail($data['sales_order_id']);
            $extra = array_diff_key($data, array_flip(['sales_order_id', 'customer_id']));
            $invoice = $this->service->createFromSalesOrder($order, $extra, auth()->id());
        } else {
            $invoice = $this->service->createInvoice($data, auth()->id());
        }

        return response()->json([
            'message' => 'Invoice created successfully.',
            'invoice' => $invoice,
        ], 201);
    }

    // ─── Show ─────────────────────────────────────────────────────────────

    public function show(Invoice $invoice): JsonResponse
    {
        $invoice->load([
            'customer:id,name,business_name,phone,email',
            'salesOrder:id,order_no,status',
            'items.product:id,name,sku',
            'items.variant:id,variant_name,sku',
            'createdBy:id,name',
        ]);

        return response()->json($invoice);
    }

    // ─── Update ───────────────────────────────────────────────────────────

    public function update(UpdateInvoiceRequest $request, Invoice $invoice): JsonResponse
    {
        if (! $invoice->canBeEdited()) {
            return response()->json(['message' => 'Only draft or issued invoices can be edited.'], 422);
        }

        $updated = $this->service->updateInvoice($invoice, $request->validated());

        return response()->json([
            'message' => 'Invoice updated successfully.',
            'invoice' => $updated,
        ]);
    }

    // ─── Issue ────────────────────────────────────────────────────────────

    public function issue(Invoice $invoice): JsonResponse
    {
        try {
            $updated = $this->service->issueInvoice($invoice);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Invoice issued successfully.',
            'invoice' => $updated,
        ]);
    }

    // ─── Cancel ───────────────────────────────────────────────────────────

    public function cancel(Invoice $invoice): JsonResponse
    {
        try {
            $updated = $this->service->cancelInvoice($invoice);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Invoice cancelled.',
            'invoice' => $updated,
        ]);
    }

    // ─── Sync Payment from SO ─────────────────────────────────────────────

    public function syncPayment(Invoice $invoice): JsonResponse
    {
        try {
            $updated = $this->service->syncPaymentFromOrder($invoice);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Payment synced from Sales Order.',
            'invoice' => $updated,
        ]);
    }
}
