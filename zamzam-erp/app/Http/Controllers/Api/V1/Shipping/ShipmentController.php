<?php

namespace App\Http\Controllers\Api\V1\Shipping;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shipping\StoreShipmentCostRequest;
use App\Http\Requests\Shipping\StoreShipmentRequest;
use App\Http\Requests\Shipping\UpdateShipmentStatusRequest;
use App\Models\Shipping\Shipment;
use App\Models\Shipping\ShipmentCost;
use App\Models\Shipping\ShipmentItem;
use App\Services\Shipping\LandingCostService;
use App\Services\Shipping\ShipmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ShipmentController extends Controller
{
    public function __construct(
        private ShipmentService     $service,
        private LandingCostService  $landingCostService,
    ) {}

    // ── List ───────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $this->authorize('shipments.view');

        $query = Shipment::with(['purchaseOrder', 'createdBy'])
            ->when($request->search, fn($q, $s) =>
                $q->where('shipment_no', 'like', "%{$s}%")
                  ->orWhere('carrier', 'like', "%{$s}%")
            )
            ->when($request->status,        fn($q, $s) => $q->where('status', $s))
            ->when($request->shipping_type, fn($q, $t) => $q->where('shipping_type', $t))
            ->when($request->date_from,     fn($q, $d) => $q->where('eta', '>=', $d))
            ->when($request->date_to,       fn($q, $d) => $q->where('eta', '<=', $d))
            ->orderByDesc('created_at');

        return response()->json($query->paginate(25));
    }

    // ── Create ─────────────────────────────────────────────────────

    public function store(StoreShipmentRequest $request): JsonResponse
    {
        $shipment = $this->service->createShipment($request->validated(), auth()->id());

        return response()->json($shipment, 201);
    }

    // ── Show ───────────────────────────────────────────────────────

    public function show(Shipment $shipment): JsonResponse
    {
        $this->authorize('shipments.view');

        return response()->json(
            $shipment->load([
                'purchaseOrder.supplier',
                'items.product',
                'items.variant',
                'items.poItem',
                'costs',
                'documents.uploadedBy',
                'statusHistory.changedBy',
                'createdBy',
            ])
        );
    }

    // ── Update ─────────────────────────────────────────────────────

    public function update(Request $request, Shipment $shipment): JsonResponse
    {
        $this->authorize('shipments.edit');

        $data     = $request->validate([
            'purchase_order_id'      => 'nullable|exists:purchase_orders,id',
            'shipping_type'          => 'sometimes|in:sea,air,rail,courier',
            'carrier'                => 'nullable|string|max:255',
            'container_no'           => 'nullable|string|max:50',
            'container_type'         => 'nullable|in:20ft,40ft,40HC,LCL',
            'bl_number'              => 'nullable|string|max:100',
            'port_loading'           => 'nullable|string|max:100',
            'port_discharge'         => 'nullable|string|max:100',
            'etd'                    => 'nullable|date',
            'eta'                    => 'nullable|date',
            'atd'                    => 'nullable|date',
            'ata'                    => 'nullable|date',
            'cost_allocation_method' => 'nullable|in:weight,volume,value,quantity,manual',
            'customs_agent'          => 'nullable|string|max:255',
            'customs_declaration_no' => 'nullable|string|max:100',
            'tracking_url'           => 'nullable|url|max:500',
            'notes'                  => 'nullable|string',
        ]);

        $shipment = $this->service->updateShipment($shipment, $data);

        return response()->json($shipment);
    }

    // ── Delete ─────────────────────────────────────────────────────

    public function destroy(Shipment $shipment): JsonResponse
    {
        $this->authorize('shipments.edit');

        if ($shipment->status->value !== 'booked') {
            return response()->json(['message' => 'Only booked shipments can be deleted.'], 422);
        }

        $shipment->delete();

        return response()->json(['message' => 'Shipment deleted.']);
    }

    // ── Status Advance ─────────────────────────────────────────────

    public function advanceStatus(UpdateShipmentStatusRequest $request, Shipment $shipment): JsonResponse
    {
        $shipment = $this->service->advanceStatus(
            $shipment,
            auth()->id(),
            $request->notes,
            $request->location,
        );

        return response()->json($shipment->load('statusHistory.changedBy'));
    }

    // ── Items ──────────────────────────────────────────────────────

    public function storeItem(Request $request, Shipment $shipment): JsonResponse
    {
        $this->authorize('shipments.create');

        $data = $request->validate([
            'po_item_id'         => 'nullable|exists:po_items,id',
            'product_id'         => 'required|exists:products,id',
            'product_variant_id' => 'nullable|exists:product_variants,id',
            'quantity'           => 'required|integer|min:1',
            'carton_count'       => 'nullable|integer|min:0',
            'weight_kg'          => 'nullable|numeric|min:0',
            'volume_cm3'         => 'nullable|numeric|min:0',
        ]);

        $item = $this->service->addItem($shipment, $data);

        return response()->json($item->load(['product', 'variant', 'poItem']), 201);
    }

    public function updateItem(Request $request, Shipment $shipment, ShipmentItem $item): JsonResponse
    {
        $this->authorize('shipments.edit');

        abort_if($item->shipment_id !== $shipment->id, 404);

        $data = $request->validate([
            'quantity'     => 'sometimes|integer|min:1',
            'carton_count' => 'nullable|integer|min:0',
            'weight_kg'    => 'nullable|numeric|min:0',
            'volume_cm3'   => 'nullable|numeric|min:0',
        ]);

        $item = $this->service->updateItem($item, $data);

        return response()->json($item);
    }

    public function destroyItem(Shipment $shipment, ShipmentItem $item): JsonResponse
    {
        $this->authorize('shipments.edit');

        abort_if($item->shipment_id !== $shipment->id, 404);
        $item->delete();

        return response()->json(['message' => 'Item removed.']);
    }

    // ── Costs ──────────────────────────────────────────────────────

    public function storeCost(StoreShipmentCostRequest $request, Shipment $shipment): JsonResponse
    {
        $cost = $shipment->costs()->create($request->validated());

        return response()->json($cost, 201);
    }

    public function updateCost(Request $request, Shipment $shipment, ShipmentCost $cost): JsonResponse
    {
        $this->authorize('shipments.edit');

        abort_if($cost->shipment_id !== $shipment->id, 404);

        $data = $request->validate([
            'cost_type'   => 'sometimes|in:freight,customs_duty,vat,ait,labour,transport,customs_fee,demurrage,other',
            'description' => 'nullable|string|max:255',
            'amount_bdt'  => 'sometimes|numeric|min:0',
            'amount_cny'  => 'nullable|numeric|min:0',
            'amount_usd'  => 'nullable|numeric|min:0',
            'voucher_no'  => 'nullable|string|max:100',
            'paid_at'     => 'nullable|date',
        ]);

        $cost->update($data);

        return response()->json($cost->refresh());
    }

    public function destroyCost(Shipment $shipment, ShipmentCost $cost): JsonResponse
    {
        $this->authorize('shipments.edit');

        abort_if($cost->shipment_id !== $shipment->id, 404);
        $cost->delete();

        return response()->json(['message' => 'Cost entry removed.']);
    }

    // ── Documents ──────────────────────────────────────────────────

    public function storeDocument(Request $request, Shipment $shipment): JsonResponse
    {
        $this->authorize('shipments.create');

        $request->validate([
            'doc_type' => 'required|in:bl,packing_list,invoice,certificate,customs_declaration,other',
            'title'    => 'required|string|max:255',
            'file'     => 'required|file|max:20480|mimes:pdf,jpg,jpeg,png,xlsx,xls,doc,docx',
        ]);

        $file = $request->file('file');
        $path = $file->store("shipments/{$shipment->id}", 'private');

        $doc = $shipment->documents()->create([
            'doc_type'     => $request->doc_type,
            'title'        => $request->title,
            'file_path'    => $path,
            'file_size_kb' => round($file->getSize() / 1024),
            'uploaded_by'  => auth()->id(),
        ]);

        return response()->json($doc->load('uploadedBy'), 201);
    }

    public function destroyDocument(Shipment $shipment, \App\Models\Shipping\ShipmentDocument $document): JsonResponse
    {
        $this->authorize('shipments.edit');

        abort_if($document->shipment_id !== $shipment->id, 404);
        Storage::disk('private')->delete($document->file_path);
        $document->delete();

        return response()->json(['message' => 'Document deleted.']);
    }

    // ── Landing Cost ───────────────────────────────────────────────

    public function getLandingCost(Shipment $shipment): JsonResponse
    {
        $this->authorize('shipments.view');

        $allocations = $this->landingCostService->calculate($shipment);

        return response()->json([
            'shipment'       => $shipment->load('costs'),
            'allocations'    => $allocations,
            'total_cost_bdt' => $shipment->totalCostBdt(),
            'allocation_method' => $shipment->cost_allocation_method,
        ]);
    }

    public function calculateAndSaveLandingCost(Shipment $shipment): JsonResponse
    {
        $this->authorize('shipments.create');

        $this->landingCostService->saveAllocations($shipment);
        $allocations = $shipment->landingCostAllocations()->with('product')->get();

        return response()->json([
            'message'     => 'Landing cost calculated and saved.',
            'allocations' => $allocations,
        ]);
    }

    // ── Tracking Timeline ──────────────────────────────────────────

    public function statusHistory(Shipment $shipment): JsonResponse
    {
        $this->authorize('shipments.view');

        return response()->json(
            $shipment->statusHistory()->with('changedBy')->get()
        );
    }
}
