# Module 2: Supplier & Procurement Management

## Overview

Manages China-based suppliers, product catalog with CNY pricing, and the full purchase order lifecycle from draft to goods received.

## Database Tables

### suppliers
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| name_chinese | varchar(255) | Chinese name |
| name_english | varchar(255) | English name |
| company_name | varchar(255) nullable | |
| wechat_id | varchar(100) nullable | Primary contact method |
| phone | varchar(20) nullable | |
| email | varchar(255) nullable | |
| address | text nullable | Chinese address |
| city | varchar(100) nullable | e.g. Yiwu, Guangzhou, Shenzhen |
| province | varchar(100) nullable | |
| country | varchar(2) default 'CN' | ISO code |
| website | varchar(500) nullable | |
| rating | tinyint nullable | 1-5 star rating |
| payment_terms | varchar(255) nullable | e.g. "30% advance, 70% before shipping" |
| preferred_currency | varchar(3) default 'CNY' | |
| bank_details | json nullable | Bank account info |
| notes | text nullable | |
| is_active | boolean default true | |
| created_by | bigint FK users.id | |
| created_at | timestamp | |
| updated_at | timestamp | |

### supplier_contacts
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| supplier_id | bigint FK suppliers.id | |
| name | varchar(255) | Contact person name |
| designation | varchar(100) nullable | Job title |
| wechat_id | varchar(100) nullable | |
| phone | varchar(20) nullable | |
| email | varchar(255) nullable | |
| is_primary | boolean default false | |
| created_at | timestamp | |
| updated_at | timestamp | |

### categories
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| parent_id | bigint FK nullable categories.id | For sub-categories |
| name | varchar(255) | |
| slug | varchar(255) unique | URL-friendly name |
| description | text nullable | |
| image | varchar(500) nullable | |
| is_active | boolean default true | |
| sort_order | int default 0 | |
| created_at | timestamp | |
| updated_at | timestamp | |

### products
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| sku | varchar(100) unique | Auto-generated or manual |
| name | varchar(255) | English name |
| name_chinese | varchar(255) nullable | Chinese name |
| category_id | bigint FK categories.id | |
| unit | varchar(20) default 'piece' | piece, kg, meter, box, carton |
| weight_kg | decimal(10,3) nullable | Per unit weight |
| volume_cm3 | decimal(12,3) nullable | Per unit volume |
| description | text nullable | |
| image | varchar(500) nullable | |
| barcode | varchar(100) nullable unique | EAN/UPC code |
| has_variants | boolean default false | |
| min_stock_alert | int default 0 | Low stock threshold |
| is_active | boolean default true | |
| created_by | bigint FK users.id | |
| created_at | timestamp | |
| updated_at | timestamp | |

### product_variants
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| product_id | bigint FK products.id | |
| variant_name | varchar(255) | e.g. "Red - Large" |
| sku | varchar(100) unique | |
| barcode | varchar(100) nullable unique | |
| attributes | json nullable | {"color": "red", "size": "L"} |
| weight_kg | decimal(10,3) nullable | |
| volume_cm3 | decimal(12,3) nullable | |
| is_active | boolean default true | |
| created_at | timestamp | |
| updated_at | timestamp | |

### product_suppliers
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| product_id | bigint FK products.id | |
| product_variant_id | bigint FK nullable product_variants.id | |
| supplier_id | bigint FK suppliers.id | |
| price_cny | decimal(12,2) | Supplier price in CNY |
| moq | int default 1 | Minimum order quantity |
| lead_time_days | int nullable | Production/delivery time |
| supplier_sku | varchar(100) nullable | Supplier's own SKU |
| product_url | varchar(500) nullable | 1688/Taobao/AliExpress link |
| is_preferred | boolean default false | Primary supplier for this product |
| last_purchased_at | timestamp nullable | |
| last_purchase_price_cny | decimal(12,2) nullable | |
| is_active | boolean default true | |
| created_at | timestamp | |
| updated_at | timestamp | |

### product_price_history
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| product_id | bigint FK products.id | |
| product_variant_id | bigint FK nullable product_variants.id | |
| supplier_id | bigint FK suppliers.id | |
| purchase_order_id | bigint FK purchase_orders.id | PO that recorded this price |
| price_cny | decimal(12,2) | Purchase price in CNY |
| price_bdt | decimal(12,2) | Converted to BDT at exchange rate |
| exchange_rate | decimal(12,6) | Rate used at time of purchase |
| qty | int | Quantity purchased in this PO |
| recorded_at | date | Date of goods receive |
| created_at | timestamp | |

**Index**: (product_id, recorded_at) for trend analysis queries

### purchase_orders
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| po_number | varchar(50) unique | Auto-generated: PO-2026-0001 |
| supplier_id | bigint FK suppliers.id | |
| currency_id | bigint FK currencies.id | Usually CNY |
| exchange_rate | decimal(12,6) | CNY to BDT rate at PO date |
| status | enum | draft, confirmed, partially_shipped, shipped, received, completed, cancelled |
| order_date | date | |
| expected_delivery_date | date nullable | |
| subtotal_cny | decimal(14,2) default 0 | Sum of items |
| total_cny | decimal(14,2) default 0 | subtotal + adjustments |
| total_bdt | decimal(14,2) default 0 | total_cny * exchange_rate |
| notes | text nullable | |
| terms_and_conditions | text nullable | |
| approved_by | bigint FK nullable users.id | |
| approved_at | timestamp nullable | |
| created_by | bigint FK users.id | |
| created_at | timestamp | |
| updated_at | timestamp | |

### po_items
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| purchase_order_id | bigint FK purchase_orders.id | |
| product_id | bigint FK products.id | |
| product_variant_id | bigint FK nullable product_variants.id | |
| supplier_price_cny | decimal(12,2) | Unit price in CNY |
| quantity | int | |
| subtotal_cny | decimal(14,2) | supplier_price * quantity |
| received_qty | int default 0 | Qty received so far |
| notes | text nullable | |
| created_at | timestamp | |
| updated_at | timestamp | |

## Status Flow - Purchase Order

```
DRAFT → CONFIRMED → PARTIALLY_SHIPPED → SHIPPED → 
RECEIVED → COMPLETED
        ↘ CANCELLED (from DRAFT or CONFIRMED only)
```

### Status Transitions
| From | To | Trigger | Permission |
|------|----|---------|-----------|
| draft | confirmed | Manager/Admin approves | purchase.approve |
| confirmed | cancelled | Manager/Admin cancels | purchase.approve |
| confirmed | partially_shipped | First shipment created | (auto via shipping module) |
| partially_shipped | shipped | All items assigned to shipments | (auto via shipping module) |
| shipped | received | Goods received in warehouse | (auto via inventory module) |
| received | completed | All items received + invoice matched | purchase.approve |
| draft | cancelled | Creator or Admin cancels | purchase.update |

## API Routes

### Suppliers
| Method | Endpoint | Description | Permission |
|--------|----------|-------------|-----------|
| GET | /api/suppliers | List suppliers (paginated) | supplier.view |
| POST | /api/suppliers | Create supplier | supplier.create |
| GET | /api/suppliers/{id} | Get supplier detail + contacts | supplier.view |
| PUT | /api/suppliers/{id} | Update supplier | supplier.update |
| DELETE | /api/suppliers/{id} | Deactivate supplier | supplier.delete |
| GET | /api/suppliers/{id}/products | Get supplier's product list | supplier.view |
| GET | /api/suppliers/{id}/orders | Get supplier's PO history | supplier.view |
| POST | /api/suppliers/{id}/contacts | Add contact person | supplier.update |
| PUT | /api/suppliers/{id}/contacts/{contactId} | Update contact | supplier.update |
| DELETE | /api/suppliers/{id}/contacts/{contactId} | Remove contact | supplier.update |

### Products
| Method | Endpoint | Description | Permission |
|--------|----------|-------------|-----------|
| GET | /api/products | List products (paginated, filterable) | product.view |
| POST | /api/products | Create product | product.create |
| GET | /api/products/{id} | Get product detail | product.view |
| PUT | /api/products/{id} | Update product | product.update |
| DELETE | /api/products/{id} | Deactivate product | product.delete |
| POST | /api/products/{id}/variants | Add variant | product.create |
| PUT | /api/products/{id}/variants/{variantId} | Update variant | product.update |
| DELETE | /api/products/{id}/variants/{variantId} | Deactivate variant | product.delete |
| GET | /api/products/{id}/suppliers | Get supplier pricing | product.view |
| POST | /api/products/{id}/suppliers | Link supplier + price | product.create |
| PUT | /api/products/{id}/suppliers/{psId} | Update supplier price | product.update |
| POST | /api/products/bulk-import | Import from Excel | product.create |

### Categories
| Method | Endpoint | Description | Permission |
|--------|----------|-------------|-----------|
| GET | /api/categories | List categories (tree) | product.view |
| POST | /api/categories | Create category | product.create |
| PUT | /api/categories/{id} | Update category | product.update |
| DELETE | /api/categories/{id} | Delete category | product.delete |

### Purchase Orders
| Method | Endpoint | Description | Permission |
|--------|----------|-------------|-----------|
| GET | /api/purchase-orders | List POs (paginated, filterable) | purchase.view |
| POST | /api/purchase-orders | Create PO | purchase.create |
| GET | /api/purchase-orders/{id} | Get PO detail + items | purchase.view |
| PUT | /api/purchase-orders/{id} | Update PO (draft only) | purchase.update |
| DELETE | /api/purchase-orders/{id} | Cancel PO | purchase.delete |
| POST | /api/purchase-orders/{id}/confirm | Confirm PO | purchase.approve |
| POST | /api/purchase-orders/{id}/cancel | Cancel PO | purchase.approve |
| GET | /api/purchase-orders/{id}/pdf | Generate PO PDF | purchase.view |
| POST | /api/purchase-orders/{id}/items | Add item | purchase.update |
| PUT | /api/purchase-orders/{id}/items/{itemId} | Update item | purchase.update |
| DELETE | /api/purchase-orders/{id}/items/{itemId} | Remove item | purchase.update |

## Frontend Pages

| Page | Route | Component |
|------|-------|-----------|
| Suppliers List | /suppliers | Suppliers/Index.vue |
| Supplier Create | /suppliers/create | Suppliers/Create.vue |
| Supplier Detail | /suppliers/{id} | Suppliers/Show.vue |
| Products List | /products | Products/Index.vue |
| Product Create | /products/create | Products/Create.vue |
| Product Detail | /products/{id} | Products/Show.vue |
| Categories | /categories | Categories/Index.vue |
| PO List | /purchase-orders | PurchaseOrders/Index.vue |
| PO Create | /purchase-orders/create | PurchaseOrders/Create.vue |
| PO Detail | /purchase-orders/{id} | PurchaseOrders/Show.vue |

## Business Logic

### Product SKU Generation
```
Pattern: ZAM-{CATEGORY_CODE}-{SEQUENTIAL_NUMBER}
Example: ZAM-ELEC-0001, ZAM-COSM-0001
```

### PO Number Generation
```
Pattern: PO-{YEAR}-{SEQUENTIAL}
Example: PO-2026-0001
```

### PO Confirmation Rules
1. PO must have at least 1 item
2. Each item must have supplier_price_cny > 0 and quantity > 0
3. Exchange rate must be recorded at confirmation
4. Status must be `draft` to confirm
5. Only Admin/Manager can confirm

### Product-Supplier Linking
1. One product can have multiple suppliers
2. One supplier can supply multiple products
3. Exactly one supplier marked as `is_preferred` per product
4. Last purchase price auto-updated when PO is received

### Price History Tracking
Every time a PO item is received, the system:
1. Updates `product_suppliers.last_purchase_price_cny`
2. Updates `product_suppliers.last_purchased_at`
3. Records price in a price_history table for trend analysis

## Events & Listeners

| Event | Listener | Description |
|-------|----------|-------------|
| PurchaseOrderCreated | LogActivity | Record in activity log |
| PurchaseOrderConfirmed | NotifySupplier | Send confirmation to supplier (WeChat/email) |
| PurchaseOrderConfirmed | ReserveBudget | Notify accounts for fund reservation |
| PurchaseOrderReceived | UpdateProductSupplierPrice | Update last purchase price |
| ProductCreated | GenerateBarcode | Auto-generate barcode/QR |
| SupplierCreated | LogActivity | Record in activity log |

## Validation Rules

### Supplier Create
```php
'name_chinese'      => 'required|string|max:255',
'name_english'      => 'required|string|max:255',
'wechat_id'         => 'nullable|string|max:100',
'phone'             => 'nullable|string|max:20',
'email'             => 'nullable|email|max:255',
'city'              => 'nullable|string|max:100',
'payment_terms'     => 'nullable|string|max:255',
```

### Product Create
```php
'name'              => 'required|string|max:255',
'name_chinese'      => 'nullable|string|max:255',
'category_id'       => 'required|exists:categories,id',
'unit'              => 'required|string|max:20',
'weight_kg'         => 'nullable|numeric|min:0',
'volume_cm3'        => 'nullable|numeric|min:0',
'sku'               => 'nullable|string|max:100|unique:products,sku',
```

### Purchase Order Create
```php
'supplier_id'       => 'required|exists:suppliers,id',
'order_date'        => 'required|date',
'expected_delivery_date' => 'nullable|date|after:order_date',
'items'             => 'required|array|min:1',
'items.*.product_id'     => 'required|exists:products,id',
'items.*.product_variant_id' => 'nullable|exists:product_variants,id',
'items.*.supplier_price_cny' => 'required|numeric|min:0.01',
'items.*.quantity'   => 'required|integer|min:1',
```

## Developer Notes

1. Use `app/Models/Procurement/` namespace for all procurement models
2. Use `app/Services/ProcurementService.php` for PO business logic
3. Product images stored in `storage/app/public/products/`
4. Supplier documents stored in `storage/app/private/suppliers/{id}/`
5. PO PDF generated using barryvdh/laravel-dompdf
6. Bulk product import uses laravel-excel package
7. Exchange rate fetched from Currencies module at PO creation time
8. All monetary values stored with 2 decimal precision except exchange_rate (6 decimals)
9. Use database transactions for PO creation and status transitions
