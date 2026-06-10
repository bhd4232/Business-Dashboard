# Module 3A: International Shipping (China → Bangladesh)

## Overview

Manages international shipment lifecycle from China to Bangladesh - container booking, sea/air transit tracking, customs clearance, cost calculation, and landing cost computation per product.

## Menu Position

```
📦 Shipping & Logistics
  └── 🌐 International Shipping
        ├── Shipments List
        ├── Create Shipment
        ├── Shipment Detail + Tracking
        ├── Landing Cost
        └── Documents
```

## Database Tables

### shipments
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| shipment_no | varchar(50) unique | Auto: SH-2026-0001 |
| purchase_order_id | bigint FK nullable purchase_orders.id | Linked PO |
| carrier | varchar(255) nullable | Shipping line / forwarder |
| container_no | varchar(50) nullable | Container number |
| container_type | varchar(20) nullable | 20ft, 40ft, 40HC, LCL |
| bl_number | varchar(100) nullable | Bill of Lading |
| shipping_type | enum | sea, air, rail, courier |
| port_loading | varchar(100) nullable | e.g. Ningbo, Shenzhen |
| port_discharge | varchar(100) nullable | e.g. Chittagong |
| etd | date nullable | Estimated Time of Departure |
| eta | date nullable | Estimated Time of Arrival |
| atd | date nullable | Actual Time of Departure |
| ata | date nullable | Actual Time of Arrival |
| status | enum | booked, loaded, departed, in_transit, arrived, clearing, cleared, delivered_to_warehouse |
| cost_allocation_method | enum default 'weight' | weight, volume, value, quantity, manual — cost allocation basis |
| customs_agent | varchar(255) nullable | C&F agent name |
| customs_declaration_no | varchar(100) nullable | |
| tracking_url | varchar(500) nullable | |
| notes | text nullable | |
| created_by | bigint FK users.id | |
| created_at | timestamp | |
| updated_at | timestamp | |

### shipment_items
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| shipment_id | bigint FK shipments.id | |
| po_item_id | bigint FK po_items.id | |
| carton_count | int nullable | Number of cartons |
| qty | int | Quantity shipped |
| weight_kg | decimal(10,3) nullable | |
| volume_cbm | decimal(10,3) nullable | Cubic meters |
| created_at | timestamp | |
| updated_at | timestamp | |

### shipment_costs
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| shipment_id | bigint FK shipments.id | |
| type | enum | freight, duty, vat, ait, labour, transport, customs_fee, demurrage, other |
| description | varchar(255) nullable | |
| amount_bdt | decimal(14,2) | Cost in BDT |
| paid | boolean default false | |
| paid_at | timestamp nullable | |
| receipt_path | varchar(500) nullable | |
| created_at | timestamp | |
| updated_at | timestamp | |

### shipment_documents
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| shipment_id | bigint FK shipments.id | |
| type | enum | bl, packing_list, commercial_invoice, customs_declaration, certificate, other |
| file_name | varchar(255) | |
| file_path | varchar(500) | |
| file_size | int nullable | Bytes |
| uploaded_by | bigint FK users.id | |
| created_at | timestamp | |

### shipment_status_history
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| shipment_id | bigint FK shipments.id | |
| status | varchar(50) | Status at this point |
| location | varchar(255) nullable | Current location |
| notes | text nullable | |
| changed_by | bigint FK nullable users.id | Manual update user |
| changed_at | timestamp | |

### landing_cost_allocations
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| shipment_id | bigint FK shipments.id | |
| po_item_id | bigint FK po_items.id | |
| product_id | bigint FK products.id | |
| purchase_cost_bdt | decimal(14,2) | PO price converted to BDT |
| freight_allocated_bdt | decimal(14,2) | Allocated freight cost |
| duty_allocated_bdt | decimal(14,2) | Allocated customs duty |
| vat_allocated_bdt | decimal(14,2) | Allocated VAT |
| ait_allocated_bdt | decimal(14,2) | Allocated AIT |
| labour_allocated_bdt | decimal(14,2) | Allocated labour cost |
| transport_allocated_bdt | decimal(14,2) | Allocated transport cost |
| other_allocated_bdt | decimal(14,2) | Other allocated costs |
| total_landing_cost_bdt | decimal(14,2) | Sum of all |
| landing_cost_per_unit_bdt | decimal(12,2) | total / qty |
| created_at | timestamp | |

## Status Flow

```
BOOKED → LOADED → DEPARTED → IN_TRANSIT → ARRIVED → 
CLEARING → CLEARED → DELIVERED_TO_WAREHOUSE
```

| Status | Description |
|--------|-------------|
| booked | Container/shipment booked with carrier |
| loaded | Goods loaded into container |
| departed | Vessel/flight departed from China port |
| in_transit | En route to Bangladesh |
| arrived | Arrived at Chittagong/Dhaka port |
| clearing | Customs clearance in progress |
| cleared | Customs cleared |
| delivered_to_warehouse | Delivered to warehouse, goods received |

## API Routes

Prefix: `/api/shipping/international`

### Shipments
| Method | Endpoint | Description | Permission |
|--------|----------|-------------|-----------|
| GET | /api/shipping/international/shipments | List shipments (paginated, filterable) | shipping.international.view |
| POST | /api/shipping/international/shipments | Create shipment | shipping.international.create |
| GET | /api/shipping/international/shipments/{id} | Get shipment detail | shipping.international.view |
| PUT | /api/shipping/international/shipments/{id} | Update shipment | shipping.international.update |
| DELETE | /api/shipping/international/shipments/{id} | Delete shipment (booked only) | shipping.international.delete |
| PATCH | /api/shipping/international/shipments/{id}/status | Update shipment status | shipping.international.update |
| GET | /api/shipping/international/shipments/{id}/tracking | Get tracking timeline | shipping.international.view |
| GET | /api/shipping/international/shipments/{id}/landing-cost | Get landing cost breakdown | shipping.international.view |

### Shipment Items
| Method | Endpoint | Description | Permission |
|--------|----------|-------------|-----------|
| POST | /api/shipping/international/shipments/{id}/items | Add item to shipment | shipping.international.create |
| PUT | /api/shipping/international/shipments/{id}/items/{itemId} | Update item | shipping.international.update |
| DELETE | /api/shipping/international/shipments/{id}/items/{itemId} | Remove item | shipping.international.update |

### Shipment Costs
| Method | Endpoint | Description | Permission |
|--------|----------|-------------|-----------|
| GET | /api/shipping/international/shipments/{id}/costs | List costs for shipment | shipping.international.view |
| POST | /api/shipping/international/shipments/{id}/costs | Add cost entry | shipping.international.create |
| PUT | /api/shipping/international/shipments/{id}/costs/{costId} | Update cost | shipping.international.update |
| DELETE | /api/shipping/international/shipments/{id}/costs/{costId} | Delete cost | shipping.international.delete |
| POST | /api/shipping/international/shipments/{id}/calculate-landing | Calculate landing cost | shipping.international.create |

### Shipment Documents
| Method | Endpoint | Description | Permission |
|--------|----------|-------------|-----------|
| GET | /api/shipping/international/shipments/{id}/documents | List documents | shipping.international.view |
| POST | /api/shipping/international/shipments/{id}/documents | Upload document | shipping.international.create |
| DELETE | /api/shipping/international/shipments/{id}/documents/{docId} | Delete document | shipping.international.delete |
| GET | /api/shipping/international/shipments/{id}/documents/{docId}/download | Download file | shipping.international.view |

### Shipment Status History
| Method | Endpoint | Description | Permission |
|--------|----------|-------------|-----------|
| GET | /api/shipping/international/shipments/{id}/status-history | Get full timeline | shipping.international.view |

## Frontend Pages

| Page | Route | Component |
|------|-------|-----------|
| Shipments List | /shipping/international/shipments | International/Shipments/Index.vue |
| Shipment Create | /shipping/international/shipments/create | International/Shipments/Create.vue |
| Shipment Detail | /shipping/international/shipments/{id} | International/Shipments/Show.vue |
| Shipment Tracking | /shipping/international/shipments/{id}/tracking | International/Shipments/Tracking.vue |
| Landing Cost | /shipping/international/shipments/{id}/landing-cost | International/Shipments/LandingCost.vue |
| Shipment Documents | /shipping/international/shipments/{id}/documents | International/Shipments/Documents.vue |

## Business Logic

### Landing Cost Calculation (Core Logic)

This is the **most critical business logic** in the system.

```
Step 1: Convert Purchase Price to BDT
   purchase_cost_bdt = qty * supplier_price_cny * exchange_rate

Step 2: Allocate Shipment Costs to Items
   Allocation basis: weight_kg (or volume_cbm if weight not available)
   
   item_weight_ratio = item.weight_kg / total_shipment_weight_kg
   
   freight_allocated = total_freight * item_weight_ratio
   duty_allocated = total_duty * item_weight_ratio
   vat_allocated = total_vat * item_weight_ratio
   ait_allocated = total_ait * item_weight_ratio
   labour_allocated = total_labour * item_weight_ratio
   transport_allocated = total_transport * item_weight_ratio
   other_allocated = total_other * item_weight_ratio

Step 3: Calculate Total Landing Cost per Item
   total_landing_cost = purchase_cost_bdt 
                      + freight_allocated + duty_allocated 
                      + vat_allocated + ait_allocated 
                      + labour_allocated + transport_allocated 
                      + other_allocated

Step 4: Calculate Per Unit Landing Cost
   landing_cost_per_unit = total_landing_cost / qty
```

### Landing Cost Service Method
```php
class LandingCostService
{
    public function calculate(int $shipmentId): Collection
    {
        $shipment = Shipment::with(['items.poItem', 'costs'])->findOrFail($shipmentId);
        
        $totalWeight = $shipment->items->sum('weight_kg');
        $costsByType = $shipment->costs->groupBy('type')->map->sum('amount_bdt');
        
        return $shipment->items->map(function ($item) use ($totalWeight, $costsByType) {
            $weightRatio = $totalWeight > 0 ? $item->weight_kg / $totalWeight : 0;
            $purchaseCostBdt = $item->qty * $item->poItem->supplier_price_cny 
                             * $item->poItem->purchaseOrder->exchange_rate;
            
            // Allocation method from shipment setting (default: by weight)
            $allocationMethod = $item->shipment->cost_allocation_method ?? 'weight';
            $allocationRatio = match($allocationMethod) {
                'volume'   => $item->volume_cbm / max($shipment->items->sum('volume_cbm'), 0.001),
                'value'    => $purchaseCostBdt / max($shipment->items->sum(fn($i) => $i->qty * $i->poItem->supplier_price_cny * $i->poItem->purchaseOrder->exchange_rate), 0.01),
                'quantity' => $item->qty / max($shipment->items->sum('qty'), 1),
                default    => $weightRatio, // weight
            };
            
            $allocations = [
                'freight'   => ($costsByType['freight'] ?? 0) * $allocationRatio,
                'duty'      => ($costsByType['duty'] ?? 0) * $allocationRatio,
                'vat'       => ($costsByType['vat'] ?? 0) * $allocationRatio,
                'ait'       => ($costsByType['ait'] ?? 0) * $allocationRatio,
                'labour'    => ($costsByType['labour'] ?? 0) * $allocationRatio,
                'transport' => ($costsByType['transport'] ?? 0) * $allocationRatio,
                'other'     => ($costsByType['other'] ?? 0) * $allocationRatio,
            ];
            
            $totalLandingCost = $purchaseCostBdt + array_sum($allocations);
            
            return [
                'po_item_id' => $item->po_item_id,
                'product_id' => $item->poItem->product_id, // Fixed: shipment_items has no direct product_id
                'purchase_cost_bdt' => $purchaseCostBdt,
                'freight_allocated_bdt' => $allocations['freight'],
                'duty_allocated_bdt' => $allocations['duty'],
                'vat_allocated_bdt' => $allocations['vat'],
                'ait_allocated_bdt' => $allocations['ait'],
                'labour_allocated_bdt' => $allocations['labour'],
                'transport_allocated_bdt' => $allocations['transport'],
                'other_allocated_bdt' => $allocations['other'],
                'total_landing_cost_bdt' => $totalLandingCost,
                'landing_cost_per_unit_bdt' => $item->qty > 0 
                    ? $totalLandingCost / $item->qty : 0,
            ];
        });
    }
}
```

### Cost Allocation Alternatives
Admin can choose allocation method per shipment:
1. **By Weight** (default) - proportional to item weight
2. **By Volume** - proportional to item volume (CBM)
3. **By Value** - proportional to item purchase value
4. **By Quantity** - proportional to item quantity
5. **Manual** - manually enter allocation per item

### Shipment → PO Status Update
When shipment status changes to `delivered_to_warehouse`:
1. Auto-create stock receive transaction in Inventory module
2. Update `po_items.received_qty`
3. Update PO status (partially_received / received)
4. Calculate and store landing cost allocation
5. Update `stock_items.landing_cost_per_unit_bdt`

### Customs Duty Calculation Helper
```
Duty Rate Reference (configurable):
- HS Code based duty rates
- Supplementary duty rates
- VAT: 15% (configurable)
- AIT: 5% (configurable)
```

## Events & Listeners

| Event | Listener | Description |
|-------|----------|-------------|
| InternationalShipmentStatusChanged | RecordStatusHistory | Add to shipment_status_history |
| InternationalShipmentStatusChanged | NotifyStakeholders | Email/notify relevant users |
| InternationalShipmentDelivered | CreateStockReceive | Auto stock receive in inventory |
| InternationalShipmentDelivered | CalculateLandingCost | Run landing cost calculation |
| InternationalShipmentDelivered | UpdatePurchaseOrderStatus | Update linked PO status |
| ShipmentCostAdded | RecalculateLandingCost | Recalculate if costs change |

## Validation Rules

### Shipment Create
```php
// shipment_no is AUTO-GENERATED (SH-{YEAR}-{SEQUENTIAL}) — not provided by user
'purchase_order_id'    => 'nullable|exists:purchase_orders,id',
'carrier'              => 'nullable|string|max:255',
'container_type'       => 'nullable|in:20ft,40ft,40HC,LCL',
'shipping_type'        => 'required|in:sea,air,rail,courier',
'port_loading'         => 'nullable|string|max:100',
'port_discharge'       => 'nullable|string|max:100',
'etd'                  => 'nullable|date',
'eta'                  => 'nullable|date|after_or_equal:etd',
```

### Shipment Item
```php
'po_item_id'    => 'required|exists:po_items,id',
'qty'           => 'required|integer|min:1',
'weight_kg'     => 'nullable|numeric|min:0',
'volume_cbm'    => 'nullable|numeric|min:0',
```

### Shipment Cost
```php
'type'          => 'required|in:freight,duty,vat,ait,labour,transport,customs_fee,demurrage,other',
'amount_bdt'    => 'required|numeric|min:0',
'description'   => 'nullable|string|max:255',
```

## Developer Notes

1. Landing cost calculation is **the most important calculation** in the entire ERP - test thoroughly
2. Use `app/Services/LandingCostService.php` for all landing cost logic
3. Store shipment documents in `storage/app/private/shipments/{id}/`
4. Shipment status changes must be atomic with history recording (use DB transactions)
5. Landing cost allocation should be recalculable (store calculation params, not just results)
6. Add index on `shipments.purchase_order_id` for PO → Shipments lookup
7. Support partial shipments (one PO can have multiple shipments)
8. Consider demurrage charges for delayed container return
9. All BDT amounts use 2 decimal precision
10. Shipment tracking can integrate with 3rd party APIs (COSCO, Maersk) in future
