<?php

namespace App\Http\Controllers\Web\Admin\Shipping;

use App\Enums\CostAllocationMethod;
use App\Enums\ShipmentCostType;
use App\Enums\ShipmentStatus;
use App\Enums\ShippingType;
use App\Http\Controllers\Controller;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Shipping\Shipment;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InternationalShipmentController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('shipments.view');

        $shipments = Shipment::with(['purchaseOrder.supplier', 'createdBy'])
            ->when($request->search, fn($q, $s) =>
                $q->where('shipment_no', 'like', "%{$s}%")
                  ->orWhere('carrier', 'like', "%{$s}%")
            )
            ->when($request->status        , fn($q, $s) => $q->where('status', $s))
            ->when($request->shipping_type , fn($q, $t) => $q->where('shipping_type', $t))
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Shipping/International/Index', [
            'shipments'     => $shipments,
            'statuses'      => collect(ShipmentStatus::cases())->map(fn($s) => ['value' => $s->value, 'label' => $s->label()]),
            'shippingTypes' => collect(ShippingType::cases())->map(fn($t) => ['value' => $t->value, 'label' => $t->label()]),
            'filters'       => $request->only(['search', 'status', 'shipping_type']),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('shipments.create');

        $purchaseOrders = PurchaseOrder::whereIn('status', ['confirmed', 'partially_shipped'])
            ->with('supplier')
            ->orderByDesc('order_date')
            ->get(['id', 'po_number', 'supplier_id', 'expected_delivery_date']);

        return Inertia::render('Shipping/International/Create', [
            'purchaseOrders'       => $purchaseOrders,
            'shippingTypes'        => collect(ShippingType::cases())->map(fn($t) => ['value' => $t->value, 'label' => $t->label()]),
            'allocationMethods'    => collect(CostAllocationMethod::cases())->map(fn($m) => ['value' => $m->value, 'label' => $m->label()]),
        ]);
    }

    public function show(Shipment $shipment): Response
    {
        $this->authorize('shipments.view');

        return Inertia::render('Shipping/International/Show', [
            'shipment' => $shipment->load([
                'purchaseOrder.supplier',
                'purchaseOrder.items.product',
                'items.product',
                'items.variant',
                'items.poItem',
                'costs',
                'documents.uploadedBy',
                'statusHistory.changedBy',
                'createdBy',
            ]),
            'statuses'      => collect(ShipmentStatus::cases())->map(fn($s) => ['value' => $s->value, 'label' => $s->label()]),
            'costTypes'     => collect(ShipmentCostType::cases())->map(fn($c) => ['value' => $c->value, 'label' => $c->label()]),
            'allocationMethods' => collect(CostAllocationMethod::cases())->map(fn($m) => ['value' => $m->value, 'label' => $m->label()]),
        ]);
    }

    public function landingCost(Shipment $shipment): Response
    {
        $this->authorize('shipments.view');

        return Inertia::render('Shipping/International/LandingCost', [
            'shipment' => $shipment->load([
                'items.product',
                'items.poItem.purchaseOrder',
                'costs',
                'landingCostAllocations.product',
            ]),
        ]);
    }
}
