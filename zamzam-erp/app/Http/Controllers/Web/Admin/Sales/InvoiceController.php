<?php

namespace App\Http\Controllers\Web\Admin\Sales;

use App\Models\Sales\Customer;
use App\Models\Sales\Invoice;
use App\Models\Sales\SalesOrder;
use App\Models\Settings\InvoiceSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController
{
    // ─── Index ────────────────────────────────────────────────────────────

    public function index(Request $request): Response
    {
        $perPage = min(max((int) $request->input('per_page', 20), 5), 100);

        $query = Invoice::with([
            'customer:id,name,business_name,phone',
            'createdBy:id,name',
        ])->orderByDesc('id');

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

        $invoices = $query->paginate($perPage)->withQueryString();

        // Status tab counts
        $raw        = Invoice::selectRaw('status, count(*) as cnt')->groupBy('status')->pluck('cnt', 'status')->toArray();
        $totalCount = (int) array_sum($raw);

        $statusTabs = [
            ['value' => '',          'label' => 'All',       'count' => $totalCount],
            ['value' => 'draft',     'label' => 'Draft',     'count' => (int) ($raw['draft']     ?? 0)],
            ['value' => 'issued',    'label' => 'Issued',    'count' => (int) ($raw['issued']    ?? 0)],
            ['value' => 'partial',   'label' => 'Partial',   'count' => (int) ($raw['partial']   ?? 0)],
            ['value' => 'paid',      'label' => 'Paid',      'count' => (int) ($raw['paid']      ?? 0)],
            ['value' => 'overdue',   'label' => 'Overdue',   'count' => (int) ($raw['overdue']   ?? 0)],
            ['value' => 'cancelled', 'label' => 'Cancelled', 'count' => (int) ($raw['cancelled'] ?? 0)],
        ];

        return Inertia::render('Sales/Invoices/Index', [
            'invoices'   => $invoices,
            'customers'  => Customer::active()->orderBy('name')->get(['id', 'name', 'business_name', 'phone']),
            'filters'    => $request->only(['status', 'customer_id', 'search', 'date_from', 'date_to', 'per_page']),
            'statusTabs' => $statusTabs,
        ]);
    }

    // ─── Create ───────────────────────────────────────────────────────────

    public function create(Request $request): Response|RedirectResponse
    {
        $order = null;
        if ($request->filled('sales_order_id')) {
            $order = SalesOrder::with([
                'customer:id,name,business_name,phone,email,address,city,area',
                'items.product:id,name,sku',
                'items.variant:id,variant_name,sku',
                'invoice:id,invoice_no,sales_order_id',
            ])->find($request->sales_order_id);

            // If SO already has an invoice, redirect to it
            if ($order && $order->invoice) {
                return redirect()->route('invoices.show', $order->invoice->id)
                    ->with('info', "Sales Order {$order->order_no} already has Invoice {$order->invoice->invoice_no}.");
            }
        }

        return Inertia::render('Sales/Invoices/Create', [
            'order'       => $order,
            'customers'   => Customer::active()->orderBy('name')->get(['id', 'name', 'business_name', 'phone']),
            'salesOrders' => $order ? null : SalesOrder::orderByDesc('id')->get(['id', 'order_no', 'customer_id']),
        ]);
    }

    // ─── Show ─────────────────────────────────────────────────────────────

    public function show(Invoice $invoice): Response
    {
        $invoice->load([
            'customer:id,name,business_name,phone,email,address,city,area',
            'salesOrder:id,order_no,status,total_bdt,paid_bdt,due_bdt,delivery_partner',
            'items.product:id,name,sku,weight_kg,image',
            'items.variant:id,variant_name,sku',
            'createdBy:id,name',
        ]);

        return Inertia::render('Sales/Invoices/Show', [
            'invoice'  => $invoice,
            'settings' => InvoiceSetting::instance(),
        ]);
    }

    // ─── Edit ─────────────────────────────────────────────────────────────

    public function edit(Invoice $invoice): Response|RedirectResponse
    {
        if (! $invoice->canBeEdited()) {
            return redirect()->route('invoices.show', $invoice)
                ->with('error', 'Only draft or issued invoices can be edited.');
        }

        $invoice->load([
            'items.product:id,name,sku,has_variants',
            'items.variant:id,variant_name,sku',
            'salesOrder:id,order_no,customer_id',
        ]);

        return Inertia::render('Sales/Invoices/Edit', [
            'invoice'    => $invoice,
            'customers'  => Customer::active()->orderBy('name')->get(['id', 'name', 'business_name', 'phone']),
        ]);
    }
}
