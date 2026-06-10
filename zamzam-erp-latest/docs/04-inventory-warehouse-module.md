# Module 4: Inventory & Warehouse Management

## Overview

Multi-warehouse stock management with barcode/QR support, stock tracking, transfers, adjustments, and integration with purchase and sales modules.

## Database Tables

### warehouses
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| name | varchar(255) | e.g. "Main Godown Mirpur" |
| code | varchar(50) unique | Short code: MGD, MDP |
| address | text | |
| city | varchar(100) | |
| phone | varchar(20) nullable | |
| manager_id | bigint FK nullable users.id | Warehouse manager |
| capacity_cbm | decimal(12,3) nullable | Storage capacity |
| is_active | boolean default true | |
| created_at | timestamp | |
| updated_at | timestamp | |

### stock_items
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| product_id | bigint FK products.id | |
| product_variant_id | bigint FK nullable product_variants.id | |
| warehouse_id | bigint FK warehouses.id | |
| qty_available | int default 0 | Current available stock |
| qty_reserved | int default 0 | Reserved for pending orders |
| qty_committed | int default 0 | Committed to invoiced orders |
| landing_cost_per_unit_bdt | decimal(12,2) default 0 | Current avg landing cost |
| total_landing_cost_bdt | decimal(14,2) default 0 | qty * cost_per_unit |
| last_received_at | timestamp nullable | |
| last_sold_at | timestamp nullable | |
| created_at | timestamp | |
| updated_at | timestamp | |

**Unique constraint**: (product_id, product_variant_id, warehouse_id)

### stock_transactions
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| product_id | bigint FK products.id | |
| product_variant_id | bigint FK nullable product_variants.id | |
| warehouse_id | bigint FK warehouses.id | |
| type | enum | receive, sale, sale_return, transfer_in, transfer_out, adjust_increase, adjust_decrease |
| qty | int | Positive for in, stored as positive (direction from type) |
| reference_type | varchar(100) nullable | Polymorphic: PurchaseOrder, SalesOrder, StockTransfer, etc. |
| reference_id | bigint nullable | Polymorphic ID |
| landing_cost_per_unit_bdt | decimal(12,2) nullable | Cost at time of transaction |
| total_cost_bdt | decimal(14,2) nullable | qty * cost_per_unit |
| notes | text nullable | |
| created_by | bigint FK users.id | |
| created_at | timestamp | |

### stock_transfers
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| transfer_no | varchar(50) unique | Auto: ST-2026-0001 |
| from_warehouse_id | bigint FK warehouses.id | Source |
| to_warehouse_id | bigint FK warehouses.id | Destination |
| status | enum | draft, in_transit, received, cancelled |
| notes | text nullable | |
| approved_by | bigint FK nullable users.id | |
| approved_at | timestamp nullable | |
| created_by | bigint FK users.id | |
| created_at | timestamp | |
| updated_at | timestamp | |

### transfer_items
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| stock_transfer_id | bigint FK stock_transfers.id | |
| product_id | bigint FK products.id | |
| product_variant_id | bigint FK nullable product_variants.id | |
| qty | int | |
| received_qty | int default 0 | Qty confirmed at destination |
| landing_cost_per_unit_bdt | decimal(12,2) | Cost carried over |
| created_at | timestamp | |

### stock_adjustments
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| adjustment_no | varchar(50) unique | Auto: SA-2026-0001 |
| warehouse_id | bigint FK warehouses.id | |
| type | enum | count_correction, damage, expiry, theft, other |
| reason | text | Mandatory explanation |
| approved_by | bigint FK nullable users.id | |
| approved_at | timestamp nullable | |
| status | enum | pending, approved, rejected |
| created_by | bigint FK users.id | |
| created_at | timestamp | |
| updated_at | timestamp | |

### adjustment_items
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| stock_adjustment_id | bigint FK stock_adjustments.id | |
| product_id | bigint FK products.id | |
| product_variant_id | bigint FK nullable product_variants.id | |
| current_qty | int | System qty before adjustment |
| adjusted_qty | int | New qty (physical count) |
| difference | int | adjusted - current (can be negative) |
| notes | text nullable | Per-item reason |
| created_at | timestamp | |

### barcodes
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| product_id | bigint FK products.id | |
| product_variant_id | bigint FK nullable product_variants.id | |
| code | varchar(100) unique | Barcode/QR value |
| type | enum | ean13, qr, code128 |
| generated_at | timestamp | |
| created_at | timestamp | |

## API Routes

### Warehouses
| Method | Endpoint | Description | Permission |
|--------|----------|-------------|-----------|
| GET | /api/warehouses | List warehouses | inventory.view |
| POST | /api/warehouses | Create warehouse | inventory.create |
| GET | /api/warehouses/{id} | Warehouse detail + stock summary | inventory.view |
| PUT | /api/warehouses/{id} | Update warehouse | inventory.update |
| DELETE | /api/warehouses/{id} | Deactivate warehouse | inventory.delete |

### Stock Items
| Method | Endpoint | Description | Permission |
|--------|----------|-------------|-----------|
| GET | /api/stock | List stock (filterable by warehouse, category, low_stock) | inventory.view |
| GET | /api/stock/{productId} | Stock across all warehouses | inventory.view |
| GET | /api/stock/{productId}/warehouse/{warehouseId} | Stock detail in specific warehouse | inventory.view |
| GET | /api/stock/low-stock | Products below minimum alert | inventory.view |
| GET | /api/stock/valuation | Total stock valuation | inventory.view |

### Stock Transactions
| Method | Endpoint | Description | Permission |
|--------|----------|-------------|-----------|
| GET | /api/stock-transactions | Transaction history (paginated) | inventory.view |
| GET | /api/stock-transactions/product/{productId} | Product-wise transaction history | inventory.view |

### Stock Transfers
| Method | Endpoint | Description | Permission |
|--------|----------|-------------|-----------|
| GET | /api/stock-transfers | List transfers | inventory.view |
| POST | /api/stock-transfers | Create transfer | inventory.transfer |
| GET | /api/stock-transfers/{id} | Transfer detail | inventory.view |
| POST | /api/stock-transfers/{id}/approve | Approve transfer | inventory.update |
| POST | /api/stock-transfers/{id}/receive | Confirm receipt | inventory.update |
| POST | /api/stock-transfers/{id}/cancel | Cancel transfer | inventory.update |

### Stock Adjustments
| Method | Endpoint | Description | Permission |
|--------|----------|-------------|-----------|
| GET | /api/stock-adjustments | List adjustments | inventory.view |
| POST | /api/stock-adjustments | Create adjustment | inventory.adjust |
| GET | /api/stock-adjustments/{id} | Adjustment detail | inventory.view |
| POST | /api/stock-adjustments/{id}/approve | Approve adjustment | inventory.update |
| POST | /api/stock-adjustments/{id}/reject | Reject adjustment | inventory.update |

### Barcode
| Method | Endpoint | Description | Permission |
|--------|----------|-------------|-----------|
| GET | /api/barcodes | List barcodes | inventory.view |
| POST | /api/barcodes/generate | Generate barcode for product | inventory.create |
| POST | /api/barcodes/generate-bulk | Bulk generate | inventory.create |
| GET | /api/barcodes/{id}/print | Get printable barcode | inventory.view |
| POST | /api/barcodes/scan | Scan and lookup product | inventory.view |

## Frontend Pages

| Page | Route | Component |
|------|-------|-----------|
| Warehouses | /warehouses | Warehouses/Index.vue |
| Stock Overview | /stock | Stock/Index.vue |
| Stock Detail | /stock/{productId} | Stock/Show.vue |
| Low Stock Alerts | /stock/low-stock | Stock/LowStock.vue |
| Stock Transactions | /stock-transactions | StockTransactions/Index.vue |
| Stock Transfers | /stock-transfers | StockTransfers/Index.vue |
| Transfer Detail | /stock-transfers/{id} | StockTransfers/Show.vue |
| Stock Adjustments | /stock-adjustments | StockAdjustments/Index.vue |
| Adjustment Detail | /stock-adjustments/{id} | StockAdjustments/Show.vue |
| Barcode Generate | /barcodes | Barcodes/Index.vue |
| Barcode Print | /barcodes/print | Barcodes/Print.vue |

## Business Logic

### Stock Update Rules

#### On Goods Receive (from Shipment)
```
1. Create stock_transaction (type: receive)
2. Update stock_items:
   - qty_available += received_qty
   - landing_cost_per_unit_bdt = weighted average of existing + new
   - total_landing_cost_bdt = qty_available * landing_cost_per_unit_bdt
   - last_received_at = now()
3. Weighted Average Cost Calculation:
   new_avg_cost = (existing_total_cost + new_total_cost) / (existing_qty + new_qty)
```

#### On Sale (from Sales Module)
```
1. Create stock_transaction (type: sale)
2. Update stock_items:
   - qty_reserved -= reserved_qty (if was reserved)
   - qty_available -= sold_qty
   - total_landing_cost_bdt = qty_available * landing_cost_per_unit_bdt
   - last_sold_at = now()
3. FIFO/Weighted Avg for cost (use weighted avg)
```

#### On Stock Transfer
```
1. Create stock_transaction (type: transfer_out) at source warehouse
2. Create stock_transaction (type: transfer_in) at destination warehouse
3. Source: qty_available -= qty
4. Destination: qty_available += qty
5. Landing cost per unit carries over (same cost at destination)
6. Update both warehouses' stock_items
```

#### On Stock Adjustment
```
1. Must be approved by Admin/Manager
2. Create stock_transaction (type: adjust_increase or adjust_decrease)
3. Update stock_items qty_available
4. If decrease: total_landing_cost_bdt -= (difference * cost_per_unit)
5. If increase: total_landing_cost_bdt += (difference * cost_per_unit)
6. Record current_qty, adjusted_qty, and difference
```

### Stock Reservation (for Sales Orders)
```
When sales order is confirmed:
  stock_items.qty_reserved += ordered_qty
  stock_items.qty_available -= ordered_qty (physically still there)

When sales order is delivered:
  stock_items.qty_reserved -= delivered_qty
  stock_items.qty_committed += delivered_qty

When invoice is paid:
  stock_items.qty_committed -= sold_qty
  (qty is now fully consumed)
```

### Barcode Generation
```
Pattern: ZAM{PRODUCT_ID}{VARIANT_ID}{CHECKSUM}
Type: Code128 (default) or QR Code
QR Code content: {"sku": "ZAM-ELEC-0001", "id": 123, "name": "Product Name"}
Bulk generation: Generate for all products missing barcodes
```

### Barcode Scanning
```
1. Mobile/Web scans barcode/QR
2. System looks up product by barcode code
3. Returns: product info, current stock, warehouse locations, price
4. Used in: stock receive, stock count, sales order creation
```

### Low Stock Alert
```
Trigger: stock_items.qty_available <= products.min_stock_alert
Notification: Dashboard alert + optional email to warehouse manager
Frequency: Checked on every stock transaction
```

### Stock Valuation
```
Total Stock Value = SUM(stock_items.total_landing_cost_bdt) across all warehouses
Per Warehouse = SUM(stock_items.total_landing_cost_bdt) WHERE warehouse_id = X
Per Category = SUM via product → category join
```

## Events & Listeners

| Event | Listener | Description |
|-------|----------|-------------|
| GoodsReceived | UpdateStockItems | Increase qty_available, recalculate avg cost |
| GoodsReceived | UpdatePurchaseOrderStatus | Update PO received quantities |
| SalesOrderConfirmed | ReserveStock | Move qty to reserved |
| SalesOrderDelivered | CommitStock | Move reserved to committed |
| StockTransferred | UpdateSourceWarehouse | Decrease source qty |
| StockTransferReceived | UpdateDestinationWarehouse | Increase destination qty |
| StockAdjusted | UpdateStockAfterAdjustment | Apply adjustment to stock |
| LowStockDetected | SendLowStockAlert | Notify warehouse manager |
| BarcodeScanned | LogScanActivity | Record scan for audit |

## Validation Rules

### Stock Transfer
```php
'from_warehouse_id'  => 'required|exists:warehouses,id|different:to_warehouse_id',
'to_warehouse_id'    => 'required|exists:warehouses,id',
'items'              => 'required|array|min:1',
'items.*.product_id' => 'required|exists:products,id',
'items.*.qty'        => 'required|integer|min:1',
// Must validate qty <= qty_available at source warehouse
```

### Stock Adjustment
```php
'warehouse_id' => 'required|exists:warehouses,id',
'type'         => 'required|in:count_correction,damage,expiry,theft,other',
'reason'       => 'required|string|min:10',
'items'        => 'required|array|min:1',
'items.*.product_id'    => 'required|exists:products,id',
'items.*.adjusted_qty'  => 'required|integer|min:0',
// Must validate adjusted_qty is different from current_qty
```

### Barcode Scan
```php
'code' => 'required|string|max:100',
```

## Developer Notes

1. Use `app/Services/StockService.php` for ALL stock mutations - never update stock_items directly
2. Stock transactions are **append-only** - never delete, only create reversing transactions
3. All stock operations must be wrapped in database transactions
4. Use pessimistic locking (`lockForUpdate()`) on stock_items during concurrent operations
5. Weighted average cost is calculated on every receive transaction
6. Add database index on `stock_items(product_id, warehouse_id)` for fast lookups
7. Barcode generation uses `milon/barcode` Laravel package
8. QR codes generated with `simplesoftwareio/simple-qrcode`
9. Stock transaction table will grow fast - consider partitioning by month after 1M rows
10. Landing cost is always in BDT - even if purchase was in CNY, it's converted at PO/shipment time
