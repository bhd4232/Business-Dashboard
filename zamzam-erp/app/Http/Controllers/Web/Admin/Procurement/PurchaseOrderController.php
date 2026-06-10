<?php

namespace App\Http\Controllers\Web\Admin\Procurement;

use App\Enums\PoStatus;
use App\Http\Controllers\Controller;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\Supplier;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseOrderController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('purchase_orders.view');

        $orders = PurchaseOrder::with(['supplier'])
            ->when($request->search, fn($q, $s) =>
                $q->where('po_number', 'like', "%{$s}%")
            )
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->when($request->supplier_id, fn($q, $id) => $q->where('supplier_id', $id))
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        $suppliers = Supplier::active()->select('id', 'name_english')->orderBy('name_english')->get();

        return Inertia::render('Procurement/PurchaseOrders/Index', [
            'orders'    => $orders,
            'suppliers' => $suppliers,
            'statuses'  => collect(PoStatus::cases())->map(fn($s) => ['value' => $s->value, 'label' => $s->label()]),
            'filters'   => $request->only(['search', 'status', 'supplier_id']),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('purchase_orders.create');

        $suppliers  = Supplier::active()->select('id', 'name_english', 'name_chinese')->get();
        $currencies = \App\Models\Core\Currency::where('is_active', true)->get();

        return Inertia::render('Procurement/PurchaseOrders/Create', [
            'suppliers'  => $suppliers,
            'currencies' => $currencies,
        ]);
    }

    public function show(PurchaseOrder $purchaseOrder): Response
    {
        $this->authorize('purchase_orders.view');

        return Inertia::render('Procurement/PurchaseOrders/Show', [
            'order' => $purchaseOrder->load([
                'supplier', 'items.product.category',
                'items.variant', 'createdBy', 'approvedBy',
            ]),
        ]);
    }

    public function edit(PurchaseOrder $purchaseOrder): Response
    {
        $this->authorize('purchase_orders.edit');

        if ($purchaseOrder->status !== PoStatus::Draft) {
            return redirect()->route('purchase-orders.show', $purchaseOrder)
                ->with('error', 'Only draft purchase orders can be edited.');
        }

        $suppliers  = Supplier::active()->select('id', 'name_english', 'name_chinese')->get();
        $currencies = \App\Models\Core\Currency::where('is_active', true)->get();

        return Inertia::render('Procurement/PurchaseOrders/Edit', [
            'order'      => $purchaseOrder->load(['supplier', 'items.product', 'items.variant']),
            'suppliers'  => $suppliers,
            'currencies' => $currencies,
        ]);
    }
}
