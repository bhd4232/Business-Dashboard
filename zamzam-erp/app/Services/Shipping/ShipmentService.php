<?php

namespace App\Services\Shipping;

use App\Enums\ShipmentStatus;
use App\Models\Shipping\Shipment;
use App\Models\Shipping\ShipmentStatusHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ShipmentService
{
    public function __construct(private LandingCostService $landingCostService) {}

    // ── Shipment Number ────────────────────────────────────────────

    public function generateShipmentNo(): string
    {
        $year   = now()->format('Y');
        $prefix = "SH-{$year}-";
        $last   = Shipment::where('shipment_no', 'like', "{$prefix}%")
                         ->orderByDesc('shipment_no')
                         ->value('shipment_no');
        $next   = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix . str_pad($next, 4, '0', STR_PAD_LEFT);
    }

    // ── Create ─────────────────────────────────────────────────────

    public function createShipment(array $data, int $createdBy): Shipment
    {
        return DB::transaction(function () use ($data, $createdBy) {
            $shipment = Shipment::create([
                'shipment_no'            => $this->generateShipmentNo(),
                'purchase_order_id'      => $data['purchase_order_id'] ?? null,
                'carrier'                => $data['carrier'] ?? null,
                'container_no'           => $data['container_no'] ?? null,
                'container_type'         => $data['container_type'] ?? null,
                'bl_number'              => $data['bl_number'] ?? null,
                'shipping_type'          => $data['shipping_type'],
                'port_loading'           => $data['port_loading'] ?? null,
                'port_discharge'         => $data['port_discharge'] ?? 'Chittagong',
                'etd'                    => $data['etd'] ?? null,
                'eta'                    => $data['eta'] ?? null,
                'status'                 => ShipmentStatus::Booked,
                'cost_allocation_method' => $data['cost_allocation_method'] ?? 'weight',
                'customs_agent'          => $data['customs_agent'] ?? null,
                'tracking_url'           => $data['tracking_url'] ?? null,
                'notes'                  => $data['notes'] ?? null,
                'created_by'             => $createdBy,
            ]);

            // Record initial status history
            $this->recordStatusHistory($shipment, ShipmentStatus::Booked, null, $createdBy);

            return $shipment->fresh(['purchaseOrder', 'createdBy']);
        });
    }

    // ── Update ─────────────────────────────────────────────────────

    public function updateShipment(Shipment $shipment, array $data): Shipment
    {
        $shipment->update([
            'purchase_order_id'      => $data['purchase_order_id'] ?? $shipment->purchase_order_id,
            'carrier'                => $data['carrier'] ?? $shipment->carrier,
            'container_no'           => $data['container_no'] ?? $shipment->container_no,
            'container_type'         => $data['container_type'] ?? $shipment->container_type,
            'bl_number'              => $data['bl_number'] ?? $shipment->bl_number,
            'shipping_type'          => $data['shipping_type'] ?? $shipment->shipping_type,
            'port_loading'           => $data['port_loading'] ?? $shipment->port_loading,
            'port_discharge'         => $data['port_discharge'] ?? $shipment->port_discharge,
            'etd'                    => $data['etd'] ?? $shipment->etd,
            'eta'                    => $data['eta'] ?? $shipment->eta,
            'atd'                    => $data['atd'] ?? $shipment->atd,
            'ata'                    => $data['ata'] ?? $shipment->ata,
            'cost_allocation_method' => $data['cost_allocation_method'] ?? $shipment->cost_allocation_method,
            'customs_agent'          => $data['customs_agent'] ?? $shipment->customs_agent,
            'customs_declaration_no' => $data['customs_declaration_no'] ?? $shipment->customs_declaration_no,
            'tracking_url'           => $data['tracking_url'] ?? $shipment->tracking_url,
            'notes'                  => $data['notes'] ?? $shipment->notes,
        ]);

        return $shipment->refresh();
    }

    // ── Status Transition ─────────────────────────────────────────

    public function advanceStatus(
        Shipment $shipment,
        int      $changedBy,
        ?string  $notes    = null,
        ?string  $location = null,
    ): Shipment {
        $next = $shipment->status->nextStatus();

        if ($next === null) {
            throw ValidationException::withMessages([
                'status' => 'Shipment has already reached its final status.',
            ]);
        }

        return DB::transaction(function () use ($shipment, $next, $changedBy, $notes, $location) {
            $shipment->update(['status' => $next]);
            $this->recordStatusHistory($shipment, $next, $notes, $changedBy, $location);

            // When delivered → calculate and store landing cost
            if ($next === ShipmentStatus::DeliveredToWarehouse) {
                $this->landingCostService->saveAllocations($shipment);
            }

            return $shipment->refresh();
        });
    }

    // ── Items ──────────────────────────────────────────────────────

    public function addItem(Shipment $shipment, array $data): \App\Models\Shipping\ShipmentItem
    {
        return $shipment->items()->create([
            'po_item_id'         => $data['po_item_id'] ?? null,
            'product_id'         => $data['product_id'],
            'product_variant_id' => $data['product_variant_id'] ?? null,
            'quantity'           => $data['quantity'],
            'carton_count'       => $data['carton_count'] ?? null,
            'weight_kg'          => $data['weight_kg'] ?? null,
            'volume_cm3'         => $data['volume_cm3'] ?? null,
        ]);
    }

    public function updateItem(\App\Models\Shipping\ShipmentItem $item, array $data): \App\Models\Shipping\ShipmentItem
    {
        $item->update([
            'quantity'     => $data['quantity']     ?? $item->quantity,
            'carton_count' => $data['carton_count'] ?? $item->carton_count,
            'weight_kg'    => $data['weight_kg']    ?? $item->weight_kg,
            'volume_cm3'   => $data['volume_cm3']   ?? $item->volume_cm3,
        ]);

        return $item->refresh();
    }

    // ── Private ────────────────────────────────────────────────────

    private function recordStatusHistory(
        Shipment       $shipment,
        ShipmentStatus $status,
        ?string        $notes,
        int            $changedBy,
        ?string        $location = null,
    ): ShipmentStatusHistory {
        return ShipmentStatusHistory::create([
            'shipment_id' => $shipment->id,
            'status'      => $status->value,
            'notes'       => $notes,
            'location'    => $location,
            'changed_by'  => $changedBy,
            'changed_at'  => now(),
        ]);
    }
}
