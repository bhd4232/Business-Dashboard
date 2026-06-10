# Module 3B: Domestic Logistics (Bangladesh Courier Services)

## Overview

Bangladesh domestic courier and delivery management inspired by Nuport's delivery partner features. Manages courier provider integration (Pathao, RedX, Steadfast, eCourier, Paperfly, Carrybee), parcel booking, tracking, COD reconciliation, delivery success metrics, electronic POD, shipping labels, and fake order detection.

## Menu Position

```
📦 Shipping & Logistics
  └── 🚚 Domestic Logistics
        ├── Courier Partners
        ├── Delivery Zones
        ├── Parcels List
        ├── Create Parcel
        ├── Parcel Detail
        ├── Delivery Tracking Board
        ├── Shipping Labels
        ├── COD Reconciliation
        ├── Courier Bills
        └── Delivery Success Meter
```

## Database Tables

### courier_providers
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| name | varchar(255) | Steadfast, Pathao, RedX, eCourier, Paperfly, Carrybee |
| code | varchar(50) unique | steadfast, pathao, redx, ecourier, paperfly, carrybee |
| logo_path | varchar(500) nullable | Courier logo |
| api_url | varchar(500) nullable | API base URL |
| api_key | varchar(500) nullable | Encrypted |
| api_secret | varchar(500) nullable | Encrypted |
| api_enabled | boolean default false | API integration active? |
| default_delivery_charge_inside_bdt | decimal(8,2) nullable | Inside Dhaka |
| default_delivery_charge_outside_bdt | decimal(8,2) nullable | Outside Dhaka |
| cod_charge_percent | decimal(5,2) default 0 | COD charge % |
| weight_charge_per_kg_bdt | decimal(8,2) nullable | Extra weight charge |
| return_charge_bdt | decimal(8,2) default 0 | Return parcel charge |
| max_delivery_days | int nullable | Expected max delivery days |
| coverage_areas | json nullable | Supported areas/cities |
| is_active | boolean default true | |
| created_at | timestamp | |
| updated_at | timestamp | |

### courier_parcels
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| parcel_no | varchar(50) unique | Auto: CP-2026-0001 |
| courier_provider_id | bigint FK courier_providers.id | |
| sales_order_id | bigint FK nullable sales_orders.id | |
| invoice_id | bigint FK nullable invoices.id | |
| customer_id | bigint FK customers.id | |
| shipment_type | enum | regular, express, same_day |
| delivery_type | enum | inside_dhaka, outside_dhaka, sub_city |
| payment_type | enum | prepaid, cod |
| cod_amount_bdt | decimal(14,2) nullable | COD collection amount |
| weight_kg | decimal(8,3) nullable | |
| parcel_content | varchar(255) nullable | |
| parcel_value_bdt | decimal(14,2) nullable | Declared value |
| number_of_items | int default 1 | |
| sender_name | varchar(255) | |
| sender_phone | varchar(20) | |
| sender_address | text | Warehouse address |
| recipient_name | varchar(255) | |
| recipient_phone | varchar(20) | |
| recipient_alt_phone | varchar(20) nullable | |
| recipient_address | text | |
| recipient_city | varchar(100) nullable | |
| recipient_area | varchar(100) nullable | |
| recipient_zone | varchar(100) nullable | Pathao zone |
| recipient_district | varchar(100) nullable | For RedX/eCourier |
| courier_tracking_id | varchar(255) nullable | Courier's tracking # |
| courier_consignment_id | varchar(255) nullable | Courier's consignment ID |
| delivery_charge_bdt | decimal(8,2) | |
| cod_charge_bdt | decimal(8,2) default 0 | |
| total_charge_bdt | decimal(8,2) | delivery + cod + weight extra |
| status | enum | pending, picked_up, in_transit, out_for_delivery, delivered, partial_delivery, returned, cancelled, lost |
| courier_status | varchar(50) nullable | Raw status from courier API |
| courier_status_updated_at | timestamp nullable | Last API sync time |
| delivered_at | timestamp nullable | |
| returned_at | timestamp nullable | |
| return_reason | varchar(255) nullable | |
| cancellation_reason | varchar(255) nullable | |
| pod_image_path | varchar(500) nullable | Electronic POD photo |
| pod_signature_path | varchar(500) nullable | Electronic POD signature |
| pod_submitted_by | bigint FK nullable users.id | Who submitted POD |
| pod_submitted_at | timestamp nullable | When POD submitted |
| delivery_attempt_count | int default 0 | How many delivery attempts |
| label_generated | boolean default false | Shipping label generated? |
| label_path | varchar(500) nullable | PDF label file path |
| notes | text nullable | |
| created_by | bigint FK users.id | |
| created_at | timestamp | |
| updated_at | timestamp | |

### courier_parcel_items
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| courier_parcel_id | bigint FK courier_parcels.id | |
| product_id | bigint FK products.id | |
| product_variant_id | bigint FK nullable product_variants.id | |
| qty | int | |
| created_at | timestamp | |

### courier_status_history
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| courier_parcel_id | bigint FK courier_parcels.id | |
| status | varchar(50) | |
| location | varchar(255) nullable | Hub/area |
| notes | text nullable | |
| courier_raw_data | json nullable | API response data |
| source | enum | manual, api_sync |
| changed_at | timestamp | |
| created_at | timestamp | |

### courier_bills
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| courier_provider_id | bigint FK courier_providers.id | |
| bill_number | varchar(50) nullable | Courier's invoice/bill number |
| period_start | date | |
| period_end | date | |
| total_parcels | int | |
| total_delivery_charge_bdt | decimal(14,2) | |
| total_cod_charge_bdt | decimal(14,2) | |
| total_cod_collected_bdt | decimal(14,2) | COD amount collected by courier |
| total_deduction_bdt | decimal(14,2) default 0 | Courier deductions |
| net_payable_bdt | decimal(14,2) | What courier owes ZamZam (COD - charges) |
| status | enum | draft, confirmed, paid, disputed |
| paid_at | timestamp nullable | |
| notes | text nullable | |
| created_by | bigint FK users.id | |
| created_at | timestamp | |
| updated_at | timestamp | |

### courier_bill_items
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| courier_bill_id | bigint FK courier_bills.id | |
| courier_parcel_id | bigint FK courier_parcels.id | |
| delivery_charge_bdt | decimal(8,2) | |
| cod_charge_bdt | decimal(8,2) default 0 | |
| cod_collected_bdt | decimal(14,2) nullable | |
| deduction_bdt | decimal(8,2) default 0 | |
| deduction_reason | varchar(255) nullable | |
| net_amount_bdt | decimal(14,2) | |
| created_at | timestamp | |

### courier_performance_metrics
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| courier_provider_id | bigint FK courier_providers.id | |
| period_type | enum | daily, weekly, monthly |
| period_start | date | |
| period_end | date | |
| total_parcels | int | |
| delivered_count | int | |
| returned_count | int | |
| lost_count | int | |
| cancelled_count | int | |
| delivery_success_rate | decimal(5,2) | delivered / total * 100 |
| avg_delivery_hours_inside | decimal(8,2) nullable | Avg time inside Dhaka |
| avg_delivery_hours_outside | decimal(8,2) nullable | Avg time outside Dhaka |
| cod_collected_bdt | decimal(14,2) | |
| cod_pending_bdt | decimal(14,2) | |
| total_delivery_charge_bdt | decimal(14,2) | |
| return_rate_percent | decimal(5,2) | |
| on_time_rate_percent | decimal(5,2) nullable | Delivered within max_delivery_days |
| calculated_at | timestamp | |
| created_at | timestamp | |

### delivery_zones
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| courier_provider_id | bigint FK nullable courier_providers.id | null = all couriers |
| zone_name | varchar(255) | e.g. "Dhaka Zone 1" |
| zone_type | enum | inside_dhaka, outside_dhaka, sub_city |
| city | varchar(100) nullable | |
| district | varchar(100) nullable | |
| areas | json | ["Mirpur", "Pallabi", "Rupnagar"] |
| delivery_charge_bdt | decimal(8,2) | |
| cod_charge_percent | decimal(5,2) default 0 | |
| estimated_delivery_hours | int nullable | |
| is_active | boolean default true | |
| created_at | timestamp | |
| updated_at | timestamp | |

### fake_order_detections
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| customer_id | bigint FK nullable customers.id | |
| ip_address | varchar(45) | |
| order_id | bigint FK nullable sales_orders.id | |
| detection_type | enum | ip_block, duplicate_order, suspicious_pattern, high_value_cod, manual_flag |
| reason | text | Auto-generated reason |
| action_taken | enum | flagged, blocked_ip, order_cancelled, manual_review |
| is_resolved | boolean default false | |
| resolved_by | bigint FK nullable users.id | |
| resolved_at | timestamp nullable | |
| created_at | timestamp | |

### ip_blacklist
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| ip_address | varchar(45) unique | |
| reason | text | |
| blocked_by | bigint FK users.id | |
| blocked_at | timestamp | |
| is_active | boolean default true | |
| created_at | timestamp | |

## Status Flow - Parcel

```
PENDING → PICKED_UP → IN_TRANSIT → OUT_FOR_DELIVERY → DELIVERED
                                                       ↓
                                              PARTIAL_DELIVERY
      ↘ RETURNED ← ← ← ← ← ← ← ← ← ← (any status after picked_up)
      ↘ CANCELLED (from PENDING only)
      ↘ LOST (any status after IN_TRANSIT)
```

| Status | Description |
|--------|-------------|
| pending | Parcel created, awaiting pickup |
| picked_up | Courier picked up from warehouse |
| in_transit | At courier hub / sorting |
| out_for_delivery | Out for delivery to customer |
| delivered | Successfully delivered |
| partial_delivery | Partially delivered |
| returned | Returned to warehouse (undelivered) |
| cancelled | Cancelled before pickup |
| lost | Parcel lost in transit |

## API Routes

Prefix: `/api/shipping/domestic`

### Courier Providers
| Method | Endpoint | Description | Permission |
|--------|----------|-------------|-----------|
| GET | /api/shipping/domestic/couriers | List courier providers | shipping.domestic.view |
| POST | /api/shipping/domestic/couriers | Add courier provider | shipping.domestic.create |
| GET | /api/shipping/domestic/couriers/{id} | Courier detail | shipping.domestic.view |
| PUT | /api/shipping/domestic/couriers/{id} | Update courier | shipping.domestic.update |
| DELETE | /api/shipping/domestic/couriers/{id} | Deactivate courier | shipping.domestic.delete |
| POST | /api/shipping/domestic/couriers/{id}/test-api | Test API connection | shipping.domestic.view |

### Delivery Zones
| Method | Endpoint | Description | Permission |
|--------|----------|-------------|-----------|
| GET | /api/shipping/domestic/zones | List zones | shipping.domestic.view |
| POST | /api/shipping/domestic/zones | Create zone | shipping.domestic.create |
| PUT | /api/shipping/domestic/zones/{id} | Update zone | shipping.domestic.update |
| DELETE | /api/shipping/domestic/zones/{id} | Delete zone | shipping.domestic.delete |
| GET | /api/shipping/domestic/zones/calculate-charge | Calculate charge by area | shipping.domestic.view |

### Parcels
| Method | Endpoint | Description | Permission |
|--------|----------|-------------|-----------|
| GET | /api/shipping/domestic/parcels | List parcels (paginated, filterable) | shipping.domestic.view |
| POST | /api/shipping/domestic/parcels | Create parcel | shipping.domestic.create |
| GET | /api/shipping/domestic/parcels/{id} | Parcel detail | shipping.domestic.view |
| PUT | /api/shipping/domestic/parcels/{id} | Update parcel | shipping.domestic.update |
| PATCH | /api/shipping/domestic/parcels/{id}/status | Update status | shipping.domestic.update |
| DELETE | /api/shipping/domestic/parcels/{id} | Cancel parcel | shipping.domestic.delete |
| POST | /api/shipping/domestic/parcels/{id}/book-courier | Book with courier API | shipping.domestic.create |
| POST | /api/shipping/domestic/parcels/{id}/cancel-courier | Cancel with courier API | shipping.domestic.update |
| GET | /api/shipping/domestic/parcels/{id}/tracking | Get tracking timeline | shipping.domestic.view |
| POST | /api/shipping/domestic/parcels/{id}/sync-status | Sync status from courier API | shipping.domestic.view |
| POST | /api/shipping/domestic/parcels/bulk-create | Bulk create parcels | shipping.domestic.create |
| POST | /api/shipping/domestic/parcels/bulk-sync | Bulk sync courier statuses | shipping.domestic.view |

### Electronic POD
| Method | Endpoint | Description | Permission |
|--------|----------|-------------|-----------|
| POST | /api/shipping/domestic/parcels/{id}/pod | Submit POD (image + signature) | shipping.domestic.update |
| GET | /api/shipping/domestic/parcels/{id}/pod | View POD | shipping.domestic.view |

### Shipping Labels
| Method | Endpoint | Description | Permission |
|--------|----------|-------------|-----------|
| POST | /api/shipping/domestic/parcels/{id}/generate-label | Generate shipping label PDF | shipping.domestic.create |
| GET | /api/shipping/domestic/parcels/{id}/label | Download label | shipping.domestic.view |
| POST | /api/shipping/domestic/parcels/bulk-label | Bulk generate labels | shipping.domestic.create |

### Courier Bills & COD Reconciliation
| Method | Endpoint | Description | Permission |
|--------|----------|-------------|-----------|
| GET | /api/shipping/domestic/bills | List courier bills | shipping.domestic.view |
| POST | /api/shipping/domestic/bills | Create bill | shipping.domestic.create |
| GET | /api/shipping/domestic/bills/{id} | Bill detail | shipping.domestic.view |
| POST | /api/shipping/domestic/bills/{id}/confirm | Confirm bill | shipping.domestic.update |
| POST | /api/shipping/domestic/bills/{id}/dispute | Dispute bill | shipping.domestic.update |

### Delivery Success Meter
| Method | Endpoint | Description | Permission |
|--------|----------|-------------|-----------|
| GET | /api/shipping/domestic/performance | Courier performance overview | shipping.domestic.view |
| GET | /api/shipping/domestic/performance/{courierId} | Specific courier metrics | shipping.domestic.view |
| GET | /api/shipping/domestic/performance/comparison | Compare couriers | shipping.domestic.view |

### Fake Order Detection
| Method | Endpoint | Description | Permission |
|--------|----------|-------------|-----------|
| GET | /api/orders/fake-detections | List detected fake orders | retail.view |
| POST | /api/orders/fake-detections/{id}/resolve | Resolve detection | retail.update |
| GET | /api/orders/ip-blacklist | IP blacklist | retail.view |
| POST | /api/orders/ip-blacklist | Add IP to blacklist | retail.update |
| DELETE | /api/orders/ip-blacklist/{id} | Remove IP | retail.update |

## Frontend Pages

| Page | Route | Component |
|------|-------|-----------|
| Courier Partners | /shipping/domestic/couriers | Domestic/Couriers/Index.vue |
| Add Courier | /shipping/domestic/couriers/create | Domestic/Couriers/Create.vue |
| Delivery Zones | /shipping/domestic/zones | Domestic/Zones/Index.vue |
| Parcels List | /shipping/domestic/parcels | Domestic/Parcels/Index.vue |
| Create Parcel | /shipping/domestic/parcels/create | Domestic/Parcels/Create.vue |
| Parcel Detail | /shipping/domestic/parcels/{id} | Domestic/Parcels/Show.vue |
| Tracking Board | /shipping/domestic/tracking-board | Domestic/TrackingBoard/Index.vue |
| Shipping Labels | /shipping/domestic/labels | Domestic/Labels/Index.vue |
| COD Reconciliation | /shipping/domestic/cod | Domestic/COD/Index.vue |
| Courier Bills | /shipping/domestic/bills | Domestic/Bills/Index.vue |
| Bill Detail | /shipping/domestic/bills/{id} | Domestic/Bills/Show.vue |
| Delivery Success Meter | /shipping/domestic/performance | Domestic/Performance/Index.vue |
| Fake Order Detection | /orders/fake-detections | Orders/FakeDetections/Index.vue |
| IP Blacklist | /orders/ip-blacklist | Orders/IPBlacklist/Index.vue |

## Business Logic

### Parcel Creation Flow
```
1. From Sales Order: Select order → Create parcel
   - Auto-fill: customer name, phone, address, items
   - Calculate: delivery charge (zone-based or inside/outside Dhaka)
   - Choose: courier provider, shipment type, COD/prepaid

2. From Invoice: Select invoice → Create parcel
   - Same as above + COD amount = invoice due

3. Standalone: Manual parcel entry (walk-in customers)

4. Bulk Create: Select multiple orders → Create parcels for all
```

### Delivery Charge Calculation
```php
class DeliveryChargeService
{
    public function calculate(int $courierId, string $deliveryType, ?float $weightKg = null): array
    {
        $courier = CourierProvider::findOrFail($courierId);
        
        $charge = match($deliveryType) {
            'inside_dhaka' => $courier->default_delivery_charge_inside_bdt,
            'outside_dhaka' => $courier->default_delivery_charge_outside_bdt,
            'sub_city' => ($courier->default_delivery_charge_inside_bdt + $courier->default_delivery_charge_outside_bdt) / 2,
        };
        
        // Check zone override
        $zone = DeliveryZone::where('courier_provider_id', $courierId)
            ->where('zone_type', $deliveryType)
            ->where('is_active', true)
            ->first();
        
        if ($zone) {
            $charge = $zone->delivery_charge_bdt;
        }
        
        // Weight surcharge
        $weightCharge = 0;
        if ($weightKg && $weightKg > 1 && $courier->weight_charge_per_kg_bdt) {
            $weightCharge = ($weightKg - 1) * $courier->weight_charge_per_kg_bdt;
        }
        
        return [
            'delivery_charge_bdt' => $charge,
            'weight_charge_bdt' => $weightCharge,
            'total_charge_bdt' => $charge + $weightCharge,
        ];
    }
}
```

### Courier API Integration Pattern
```php
interface CourierApiClientInterface
{
    public function createConsignment(array $parcelData): array;
    public function trackConsignment(string $trackingId): array;
    public function cancelConsignment(string $consignmentId): array;
    public function getZones(): array;
}

class PathaoClient implements CourierApiClientInterface { ... }
class RedXClient implements CourierApiClientInterface { ... }
class SteadfastClient implements CourierApiClientInterface { ... }
class ECourierClient implements CourierApiClientInterface { ... }
class PaperflyClient implements CourierApiClientInterface { ... }
```

Phase 1: Manual tracking # entry. Phase 2: Full API integration per courier.

### Electronic POD Submission (Mobile App)
```
1. Delivery person opens mobile app
2. Selects parcel → taps "Mark Delivered"
3. App prompts:
   a. Capture photo (customer receiving parcel)
   b. Capture signature (customer signs on screen)
   c. Auto-capture GPS location
   d. Auto-capture timestamp
4. All data uploaded to ERP
5. Parcel status → delivered
6. If COD: Payment record auto-created
7. Customer receives SMS notification
```

### Shipping Label Generation
```
Label PDF content:
┌─────────────────────────────────┐
│ ZamZam Trading                   │
│ Sender: Warehouse, Mirpur, Dhaka │
├─────────────────────────────────┤
│ TO: Customer Name                │
│ Phone: 01XXXXXXXXX               │
│ Address: Full delivery address    │
│ City: Dhaka / Chittagong         │
├─────────────────────────────────┤
│ Courier: Pathao                  │
│ Tracking: PH-123456789           │
│ Type: COD  Amount: ৳1,500       │
├─────────────────────────────────┤
│ [BARCODE: CP-2026-0001]          │
│ [QR CODE: {parcel_no, tracking}] │
└─────────────────────────────────┘

Bulk label: Multiple labels in single PDF (A4 page, 4 labels per page)
```

### COD Reconciliation Flow
```
1. Courier delivers COD parcels → collects cash from customers
2. Courier sends weekly/monthly bill:
   - List of delivered parcels + COD collected
   - Delivery charges + COD charges
   - Deductions (if any)
3. ERP creates Courier Bill:
   - Auto-populate from delivered parcels in period
   - COD collected by courier (from courier data)
   - Delivery charges owed to courier
   - Net: COD collected - delivery charges = payable by courier
4. Verify: ERP data vs courier data
   - Match parcels one by one
   - Flag discrepancies
5. Confirm bill → Payment → Journal entry

Net Payable = Total COD Collected - Total Delivery Charges - Total Deductions
If positive: Courier owes ZamZam (most common for COD)
If negative: ZamZam owes Courier (prepaid orders)
```

### Delivery Success Meter
```
Courier Performance Dashboard:

1. Delivery Success Rate = (Delivered / Total) * 100
2. Return Rate = (Returned / Total) * 100
3. Average Delivery Time:
   - Inside Dhaka: hours from picked_up to delivered
   - Outside Dhaka: hours from picked_up to delivered
4. COD Collection Rate = (COD Collected / COD Total) * 100
5. On-Time Rate = (Delivered within max_days / Total Delivered) * 100
6. Lost Rate = (Lost / Total) * 100

Comparison view across all couriers for period selection.

Auto-calculated daily via scheduled job, stored in courier_performance_metrics.
```

### Fake Order Detection
```
Auto-detection rules:

1. IP Blacklist: Order from blocked IP → auto-flag
2. Duplicate Detection: 
   - Same phone + same address within 1 hour
   - Same customer 3+ orders in 24 hours
3. High Value COD: 
   - COD amount > configurable threshold (e.g. ৳50,000)
   - First-time customer + high COD
4. Suspicious Pattern:
   - Multiple orders to same address, different names
   - Order placed from VPN/tor IP (if detectable)
5. Delivery Failure History:
   - Customer with 2+ returned parcels
   - Customer with 2+ cancelled orders

Actions:
- flagged: Mark for admin review, order holds
- blocked_ip: Add IP to blacklist, cancel order
- order_cancelled: Auto-cancel suspicious order
- manual_review: Send to admin for review

Admin can:
- Resolve detection (approve or reject order)
- Add/remove IP from blacklist
- Whitelist customer (disable auto-detection)
```

### Courier Status Sync (API)
```
Scheduled Job: Every 30 minutes
1. For each active courier with API enabled:
2. Get all parcels with status in [picked_up, in_transit, out_for_delivery]
3. Call courier API to get current status
4. Map courier status to ERP status
5. Update courier_parcels.status
6. Add entry to courier_status_history
7. If status = delivered → trigger delivery events

Status Mapping (varies per courier):
Pathao: "picked_up" → picked_up, "in_hub" → in_transit, etc.
RedX: "collected" → picked_up, "in_transit" → in_transit, etc.
```

## Events & Listeners

| Event | Listener | Description |
|-------|----------|-------------|
| ParcelCreated | ReserveStockForParcel | Reserve stock if not already reserved |
| ParcelDelivered | SubmitElectronicPOD | Record POD data |
| ParcelDelivered | UpdateDeliveryMetrics | Update courier performance metrics |
| ParcelDelivered | UpdateSalesOrderStatus | Mark order as delivered |
| ParcelDelivered | CreateAutoJournalDelivery | Journal entry for delivery charge |
| ParcelDelivered | RecordCODCollection | If COD, create payment record |
| ParcelReturned | RestockReturnedItems | Return items to inventory |
| ParcelReturned | UpdateDeliveryMetrics | Update return rate metrics |
| ParcelReturned | NotifySalesman | Alert salesman for follow-up |
| ParcelLost | LogLostParcel | Record loss + insurance claim |
| CourierBillConfirmed | SettleCODAmounts | COD reconciliation journal entry |
| SuspiciousOrderDetected | FlagForReview | Alert admin |
| IPBlocked | BlockFutureOrders | Cancel/block orders from IP |

## Validation Rules

### Courier Provider
```php
'name'                          => 'required|string|max:255',
'code'                          => 'required|string|max:50|unique:courier_providers,code',
'api_url'                       => 'nullable|url|max:500',
'default_delivery_charge_inside_bdt'  => 'nullable|numeric|min:0',
'default_delivery_charge_outside_bdt' => 'nullable|numeric|min:0',
'cod_charge_percent'            => 'nullable|numeric|min:0|max:100',
```

### Courier Parcel
```php
'courier_provider_id' => 'required|exists:courier_providers,id',
'sales_order_id'      => 'nullable|exists:sales_orders,id',
'customer_id'         => 'required|exists:customers,id',
'shipment_type'       => 'required|in:regular,express,same_day',
'delivery_type'       => 'required|in:inside_dhaka,outside_dhaka,sub_city',
'payment_type'        => 'required|in:prepaid,cod',
'cod_amount_bdt'      => 'required_if:payment_type,cod|nullable|numeric|min:0',
'recipient_name'      => 'required|string|max:255',
'recipient_phone'     => 'required|string|max:20',
'recipient_address'   => 'required|string',
'delivery_charge_bdt' => 'required|numeric|min:0',
```

### Courier Bill
```php
'courier_provider_id' => 'required|exists:courier_providers,id',
'period_start'        => 'required|date',
'period_end'          => 'required|date|after_or_equal:period_start',
```

### Fake Order Detection Resolve
```php
'is_resolved'    => 'required|boolean',
'action_taken'   => 'required|in:flagged,blocked_ip,order_cancelled,manual_review',
```

## Developer Notes

1. Use `app/Services/CourierClientFactory.php` to resolve courier-specific API clients
2. Each courier API client implements `CourierApiClientInterface`
3. Phase 1: Manual tracking # entry + manual status updates
4. Phase 2: Full API integration (separate feature branch per courier)
5. POD images stored in `storage/app/private/pod/{parcel_id}/`
6. Shipping labels generated as PDF using `barryvdh/laravel-dompdf`
7. Label template: thermal printer compatible (4x6 inch) + A4 bulk format
8. Fake order detection runs on order creation (middleware/hook)
9. Performance metrics calculated daily via scheduled job, not on-the-fly
10. COD reconciliation is CRITICAL for cash flow - test thoroughly
11. All courier API credentials stored encrypted in database
12. Rate limit courier API calls (max 30 requests/minute per provider)
13. Store POD images compressed (max 500KB each)
14. QR code on label uses `simplesoftwareio/simple-qrcode`
