<?php

namespace App\Http\Controllers\Web\Admin\Sales;

use App\Models\Sales\Customer;
use App\Models\Sales\PriceTier;
use App\Models\Sales\SalesOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SalesOrderController
{
    // ─── Status & source maps ─────────────────────────────────────────────

    private const STATUSES = [
        ['value' => 'draft',      'label' => 'Pending'],
        ['value' => 'on_hold',    'label' => 'On Hold'],
        ['value' => 'confirmed',  'label' => 'Approved'],
        ['value' => 'processing', 'label' => 'Processing'],
        ['value' => 'picked',     'label' => 'Ready To Ship'],
        ['value' => 'dispatched', 'label' => 'In-Transit'],
        ['value' => 'delivered',  'label' => 'Delivered'],
        ['value' => 'flagged',    'label' => 'Flagged'],
        ['value' => 'cancelled',  'label' => 'Cancelled'],
        ['value' => 'returned',   'label' => 'Returned'],
    ];

    private const SOURCES = [
        ['value' => 'erp',        'label' => 'ERP'],
        ['value' => 'storefront', 'label' => 'Storefront'],
        ['value' => 'whatsapp',   'label' => 'WhatsApp'],
        ['value' => 'messenger',  'label' => 'Messenger'],
        ['value' => 'woocommerce','label' => 'WooCommerce'],
        ['value' => 'reseller',   'label' => 'Reseller'],
    ];

    private const CANCEL_REASONS = [
        'Customer Unreachable',
        'Customer Payment Issues',
        'Customer Mistakenly Placed Order',
        'Customer Not Interested in Paying an Advance',
        'Customer Wants to Cancel',
        'Customer Will Not Be Available at Delivery Time',
        'Customer Will Order Later',
        'Customer Not Interested',
        'Delay Delivery',
        'Urgent Delivery Required',
        'Out of Area Coverage',
        'Product Stock-Out',
        'Product Price Issues',
        'Duplicate Order',
        'Fake Order',
        'Test Order',
        'Other',
    ];

    private const ON_HOLD_REASONS = [
        'Customer Unreachable',
        'Call Not Answered',
        'Follow-up Call Scheduled',
        'Invalid Phone Number',
        'Awaiting Customer Decision',
        'Pre-Order',
        'Out of Stock',
        'Payment Confirmation',
        'Additional Product Required',
        'Delivery Address Updated',
        'Delivery Date Updated',
        'Other',
    ];

    // ─── Index ────────────────────────────────────────────────────────────

    public function index(Request $request): Response
    {
        $perPage = min(max((int) $request->input('per_page', 20), 5), 100);

        $query = SalesOrder::with([
            'customer:id,name,business_name,phone,type,address,city,area,district,total_orders,source',
            'createdBy:id,name',
        ])->orderByDesc('id');

        // ── Standard filters ───────────────────────────
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }
        if ($request->filled('source')) {
            $query->where('source', $request->source);
        }
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('order_no', 'like', "%{$s}%")
                  ->orWhereHas('customer', fn ($q2) =>
                      $q2->where('name', 'like', "%{$s}%")
                         ->orWhere('business_name', 'like', "%{$s}%")
                         ->orWhere('phone', 'like', "%{$s}%")
                  );
            });
        }

        // ── Sub-filters ────────────────────────────────
        if ($request->filled('district')) {
            $query->whereHas('customer', fn ($q) => $q->where('district', $request->district));
        }
        if ($request->filled('cancel_reason')) {
            if ($request->cancel_reason === 'Other') {
                $query->where(fn ($q) => $q->whereNull('cancel_reason')->orWhere('cancel_reason', ''));
            } else {
                $query->where('cancel_reason', $request->cancel_reason);
            }
        }
        if ($request->filled('on_hold_reason')) {
            $query->where('on_hold_reason', $request->on_hold_reason);
        }
        if ($request->filled('flag_reason')) {
            $query->where('flag_reason', $request->flag_reason);
        }
        if ($request->filled('payment')) {
            match ($request->payment) {
                'due'       => $query->where('due_bdt', '>', 0),
                'collected' => $query->where('due_bdt', '<=', 0),
                default     => null,
            };
        }
        if ($request->filled('delivery_partner_filter')) {
            $query->where('delivery_partner', $request->delivery_partner_filter);
        }

        $orders = $query->paginate($perPage)->withQueryString();

        // ── Status tab counts (global — ignore active filters) ─────────
        $raw        = SalesOrder::selectRaw('status, count(*) as cnt')->groupBy('status')->pluck('cnt', 'status')->toArray();
        $totalCount = (int) array_sum($raw);

        $statusTabs = [
            ['value' => '',           'label' => 'All Orders',    'count' => $totalCount],
            ['value' => 'draft',      'label' => 'Pending',       'count' => (int) ($raw['draft']      ?? 0)],
            ['value' => 'on_hold',    'label' => 'On Hold',       'count' => (int) ($raw['on_hold']     ?? 0)],
            ['value' => 'confirmed',  'label' => 'Approved',      'count' => (int) ($raw['confirmed']   ?? 0)],
            ['value' => 'processing', 'label' => 'Processing',    'count' => (int) ($raw['processing']  ?? 0)],
            ['value' => 'picked',     'label' => 'Ready To Ship', 'count' => (int) ($raw['picked']      ?? 0)],
            ['value' => 'dispatched', 'label' => 'In-Transit',    'count' => (int) ($raw['dispatched']  ?? 0)],
            ['value' => 'delivered',  'label' => 'Delivered',     'count' => (int) ($raw['delivered']   ?? 0)],
            ['value' => 'flagged',    'label' => 'Flagged',       'count' => (int) ($raw['flagged']     ?? 0)],
            ['value' => 'cancelled',  'label' => 'Cancelled',     'count' => (int) ($raw['cancelled']   ?? 0)],
        ];

        // ── Sub-filter data (per active tab) ───────────────────────────

        // District chips (Approved / Ready To Ship tabs)
        $districtCounts = [];
        if (in_array($request->status, ['confirmed', 'picked'])) {
            $districtCounts = SalesOrder::where('status', $request->status)
                ->join('customers', 'customers.id', '=', 'sales_orders.customer_id')
                ->whereNotNull('customers.district')
                ->where('customers.district', '!=', '')
                ->selectRaw('customers.district as district, count(*) as count')
                ->groupBy('customers.district')
                ->orderByDesc('count')
                ->get()
                ->toArray();
        }

        // Cancel reason chips
        $cancelReasonCounts = [];
        if ($request->status === 'cancelled') {
            $cRaw = SalesOrder::where('status', 'cancelled')
                ->selectRaw("COALESCE(NULLIF(cancel_reason, ''), 'Other') as reason, count(*) as count")
                ->groupBy('reason')
                ->pluck('count', 'reason')
                ->toArray();

            $cancelReasonCounts = collect(self::CANCEL_REASONS)
                ->map(fn ($r) => ['reason' => $r, 'count' => (int) ($cRaw[$r] ?? 0)])
                ->filter(fn ($r) => $r['count'] > 0)
                ->values()
                ->toArray();
        }

        // On-hold reason chips
        $onHoldReasonCounts = [];
        if ($request->status === 'on_hold') {
            $hRaw = SalesOrder::where('status', 'on_hold')
                ->selectRaw("COALESCE(NULLIF(on_hold_reason, ''), 'Other') as reason, count(*) as count")
                ->groupBy('reason')
                ->pluck('count', 'reason')
                ->toArray();

            $onHoldReasonCounts = collect(self::ON_HOLD_REASONS)
                ->map(fn ($r) => ['reason' => $r, 'count' => (int) ($hRaw[$r] ?? 0)])
                ->filter(fn ($r) => $r['count'] > 0)
                ->values()
                ->toArray();
        }

        // Delivered: payment summary chips
        $deliveredStats = null;
        if ($request->status === 'delivered') {
            $deliveredStats = [
                'due_count'       => SalesOrder::where('status', 'delivered')->where('due_bdt', '>', 0)->count(),
                'collected_count' => SalesOrder::where('status', 'delivered')->where('due_bdt', '<=', 0)->count(),
            ];
        }

        // Flagged: return-type chips
        $flaggedStats = null;
        if ($request->status === 'flagged') {
            $flaggedStats = [
                'pending_returned' => SalesOrder::where('status', 'flagged')->where('flag_reason', 'pending_return')->count(),
                'returned'         => SalesOrder::where('status', 'flagged')->where('flag_reason', 'returned')->count(),
                'damaged'          => SalesOrder::where('status', 'flagged')->where('flag_reason', 'damaged')->count(),
            ];
        }

        // In-Transit: delivery-partner chips
        $deliveryPartnerCounts = [];
        if ($request->status === 'dispatched') {
            $deliveryPartnerCounts = SalesOrder::where('status', 'dispatched')
                ->whereNotNull('delivery_partner')
                ->where('delivery_partner', '!=', '')
                ->selectRaw('delivery_partner, count(*) as count')
                ->groupBy('delivery_partner')
                ->orderByDesc('count')
                ->get()
                ->toArray();
        }

        return Inertia::render('Sales/SalesOrders/Index', [
            'orders'                => $orders,
            'customers'             => Customer::active()->orderBy('name')->get(['id', 'name', 'business_name', 'phone']),
            'statuses'              => self::STATUSES,
            'sources'               => self::SOURCES,
            'filters'               => $request->only([
                'status', 'customer_id', 'source', 'type', 'search',
                'per_page', 'district', 'cancel_reason', 'on_hold_reason',
                'flag_reason', 'payment', 'delivery_partner_filter',
            ]),
            'statusTabs'            => $statusTabs,
            'districtCounts'        => $districtCounts,
            'cancelReasonCounts'    => $cancelReasonCounts,
            'onHoldReasonCounts'    => $onHoldReasonCounts,
            'deliveredStats'        => $deliveredStats,
            'flaggedStats'          => $flaggedStats,
            'deliveryPartnerCounts' => $deliveryPartnerCounts,
        ]);
    }

    // ─── Create ───────────────────────────────────────────────────────────

    public function create(): Response
    {
        return Inertia::render('Sales/SalesOrders/Create', [
            'customers'  => Customer::active()->orderBy('name')->get(['id', 'name', 'business_name', 'phone', 'price_tier_id', 'address', 'city', 'area']),
            'priceTiers' => PriceTier::active()->get(['id', 'name', 'code', 'discount_percent']),
            'statuses'   => self::STATUSES,
            'sources'    => self::SOURCES,
        ]);
    }

    // ─── Show ─────────────────────────────────────────────────────────────

    public function show(SalesOrder $salesOrder): Response
    {
        $salesOrder->load([
            'customer:id,name,business_name,phone,email,address,city,area',
            'priceTier:id,name,code',
            'items.product:id,name,sku',
            'items.variant:id,variant_name,sku',
            'confirmedBy:id,name',
            'createdBy:id,name',
            'payments.receivedBy:id,name',
            'attachments',
            'invoice:id,invoice_no,status,sales_order_id',
        ]);

        return Inertia::render('Sales/SalesOrders/Show', [
            'order' => $salesOrder,
        ]);
    }

    // ─── Edit ─────────────────────────────────────────────────────────────

    public function edit(SalesOrder $salesOrder): Response|RedirectResponse
    {
        if (! $salesOrder->canBeEdited()) {
            return redirect()->route('sales-orders.show', $salesOrder)
                ->with('error', 'Only draft orders can be edited.');
        }

        $salesOrder->load(['items.product:id,name,sku,has_variants', 'items.variant:id,variant_name,sku']);

        return Inertia::render('Sales/SalesOrders/Edit', [
            'order'      => $salesOrder,
            'customers'  => Customer::active()->orderBy('name')->get(['id', 'name', 'business_name', 'phone', 'price_tier_id', 'address', 'city', 'area']),
            'priceTiers' => PriceTier::active()->get(['id', 'name', 'code', 'discount_percent']),
            'sources'    => self::SOURCES,
        ]);
    }
}
