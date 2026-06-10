# Module 5B: Retail Sales Management

## Overview

Manages B2C/retail sales through the **Native Retail Storefront** (`/shop/*`) and direct walk-in sales. Handles online payments (SSLCommerz, bKash, Nagad), courier delivery management, and retail returns.

**Key Change:** Retail orders now originate from the native Laravel storefront instead of WooCommerce. The WooCommerce Importer (Module 8) is a one-time migration tool, not an ongoing sync.

## Architecture Context

```
Native Retail Storefront (/shop/*)
├── Product catalog (public browsing, MRP visible)
├── Cart + Checkout (SSLCommerz, bKash, Nagad)
├── Order tracking (customer-facing)
└── Customer profile (order history, balance)

Direct ERP Orders
├── Walk-in customers (source=erp)
├── Phone orders (source=phone)
└── WhatsApp orders via AI chatbot (source=whatsapp → retail type)
```

**Module Toggle:** The Retail Storefront can be enabled/disabled from Settings > Modules. When disabled, `/shop/*` routes return 404. At least one storefront (wholesale or retail) must remain active.

## Database Tables

Retail sales shares most tables with Wholesale (Module 5A) - `customers`, `sales_orders`, `so_items`, `invoices`, `invoice_items`, `sales_returns`, `return_items`.

The `sales_orders.type` field distinguishes `wholesale` vs `retail` orders.

### Additional Retail-Specific Tables

### storefront_settings (retail_storefront module)
Configuration for the retail storefront is stored in the `storefront_settings` table (Module 8). Keys specific to retail:

- `store_name` = ZamZam Shop
- `show_prices_without_login` = true
- `delivery_inside_dhaka` = 60
- `delivery_outside_dhaka` = 120
- `free_delivery_minimum` = 500
- `return_window_days` = 7
- `tax_percent` = 0
- `allow_guest_checkout` = true
- `theme_primary_color` = #6366F1

### deliveries
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| sales_order_id | bigint FK sales_orders.id | |
| courier_name | varchar(255) nullable | e.g. Pathao, Steadfast, RedX |
| tracking_number | varchar(100) nullable | Courier tracking |
| tracking_url | varchar(500) nullable | |
| delivery_type | enum | inside_dhaka, outside_dhaka, pickup |
| delivery_charge_bdt | decimal(8,2) | |
| delivery_address | text | |
| recipient_name | varchar(255) | |
| recipient_phone | varchar(20) | |
| status | enum | pending, picked_up, in_transit, out_for_delivery, delivered, failed, returned |
| attempted_at | timestamp nullable | First delivery attempt |
| delivered_at | timestamp nullable | |
| failed_reason | text nullable | If delivery failed |
| notes | text nullable | |
| created_by | bigint FK users.id | Who created the delivery record |
| created_at | timestamp | |
| updated_at | timestamp | |

### online_payments
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| sales_order_id | bigint FK sales_orders.id | |
| invoice_id | bigint FK nullable invoices.id | |
| gateway | enum | sslcommerz, bkash, nagad, rocket, card |
| transaction_id | varchar(255) | Gateway transaction ID |
| amount_bdt | decimal(14,2) | |
| currency | varchar(3) default 'BDT' | |
| status | enum | pending, processing, completed, failed, refunded |
| gateway_response | json nullable | Full response from gateway |
| paid_at | timestamp nullable | |
| created_at | timestamp | |

## Status Flow - Retail Sales Order

```
DRAFT → CONFIRMED → PROCESSING → PACKED → SHIPPED → DELIVERED → INVOICED
      ↘ CANCELLED
```

### Retail vs Wholesale Differences

| Aspect | Wholesale | Retail |
|--------|-----------|--------|
| Customer | Reseller/Business | End consumer |
| Pricing | Tier-based, volume discount | Fixed MRP/retail price |
| Payment | Cash/Credit/বাকি | Online payment (SSLCommerz/bKash/Nagad) |
| Delivery | Bulk, own transport | Courier/Pathao/Steadfast/RedX |
| Order Source | Native wholesale storefront + ERP + Phone | Native retail storefront + Walk-in |
| Invoice | Detailed tax invoice | Retail receipt |
| Credit | Common (বাকি) | Rare (prepaid) |
| Minimum Qty | Large quantities | 1 unit |
| Return Policy | Negotiable | Fixed 7-day window |

## API Routes

### Retail Orders
| Method | Endpoint | Description | Permission |
|--------|----------|-------------|-----------|
| GET | /api/retail/orders | List retail orders (admin) | retail.view |
| POST | /api/retail/orders | Create retail order (admin/phone) | retail.create |
| GET | /api/retail/orders/{id} | Order detail | retail.view |
| PUT | /api/retail/orders/{id} | Update (draft only) | retail.update |
| DELETE | /api/retail/orders/{id} | Cancel order | retail.delete |
| POST | /api/retail/orders/{id}/confirm | Confirm order | retail.update |
| POST | /api/retail/orders/{id}/ship | Mark shipped | retail.update |
| POST | /api/retail/orders/{id}/deliver | Mark delivered | retail.update |

### Retail Storefront (Public/Customer)
| Method | Endpoint | Description | Auth |
|--------|----------|-------------|------|
| GET | /shop/api/catalog | Product catalog (MRP pricing) | Public |
| GET | /shop/api/catalog/search | Search products | Public |
| GET | /shop/api/product/{slug} | Product detail | Public |
| POST | /shop/api/cart | Add to cart | Session |
| GET | /shop/api/cart | View cart | Session |
| PUT | /shop/api/cart/{id} | Update cart item | Session |
| DELETE | /shop/api/cart/{id} | Remove cart item | Session |
| POST | /shop/api/checkout | Place order | Customer auth |
| POST | /shop/api/payment/initiate | Initiate online payment | Customer auth |
| POST | /shop/api/payment/callback | Payment gateway callback | No auth |
| POST | /shop/api/payment/verify | Verify payment | Customer auth |
| GET | /shop/api/orders | Order history | Customer auth |
| GET | /shop/api/orders/{id} | Order detail | Customer auth |
| POST | /shop/api/register | Customer registration | Public |
| POST | /shop/api/login | Customer login | Public |

### Deliveries (Admin)
| Method | Endpoint | Description | Permission |
|--------|----------|-------------|-----------|
| POST | /api/retail/orders/{id}/delivery | Create delivery | retail.create |
| GET | /api/retail/orders/{id}/delivery | Get delivery info | retail.view |
| PUT | /api/retail/orders/{id}/delivery | Update delivery | retail.update |
| PATCH | /api/retail/orders/{id}/delivery/status | Update delivery status | retail.update |

### Online Payments (Admin)
| Method | Endpoint | Description | Permission |
|--------|----------|-------------|-----------|
| GET | /api/retail/payments | List online payments | retail.view |

### Retail Returns (Admin)
| Method | Endpoint | Description | Permission |
|--------|----------|-------------|-----------|
| POST | /api/retail/returns | Create return request | retail.create |
| GET | /api/retail/returns | List returns | retail.view |
| GET | /api/retail/returns/{id} | Return detail | retail.view |
| POST | /api/retail/returns/{id}/approve | Approve return | retail.update |
| POST | /api/retail/returns/{id}/reject | Reject return | retail.update |
| POST | /api/retail/returns/{id}/refund | Process refund | retail.update |

## Frontend Pages

### ERP Admin Pages
| Page | Route | Component |
|------|-------|-----------|
| Retail Orders | /retail/orders | Retail/Orders/Index.vue |
| Order Create | /retail/orders/create | Retail/Orders/Create.vue |
| Order Detail | /retail/orders/{id} | Retail/Orders/Show.vue |
| Deliveries | /retail/deliveries | Retail/Deliveries/Index.vue |
| Delivery Detail | /retail/deliveries/{id} | Retail/Deliveries/Show.vue |
| Online Payments | /retail/payments | Retail/Payments/Index.vue |
| Retail Returns | /retail/returns | Retail/Returns/Index.vue |

### Retail Storefront Pages (when module active)
| Page | Route | Component |
|------|-------|-----------|
| Catalog | /shop/catalog | Shop/Catalog/Index.vue |
| Product Detail | /shop/product/{slug} | Shop/Catalog/Show.vue |
| Cart | /shop/cart | Shop/Cart/Index.vue |
| Checkout | /shop/checkout | Shop/Checkout/Index.vue |
| Payment Success | /shop/checkout/success | Shop/Checkout/Success.vue |
| Payment Fail | /shop/checkout/fail | Shop/Checkout/Fail.vue |
| Login | /shop/login | Shop/Auth/Login.vue |
| Register | /shop/register | Shop/Auth/Register.vue |
| Orders | /shop/orders | Shop/Orders/Index.vue |
| Order Detail | /shop/orders/{id} | Shop/Orders/Show.vue |
| Profile | /shop/profile | Shop/Profile/Index.vue |

## Business Logic

### Retail Price Resolution
```
1. Product has a base retail price (MRP) stored in product_price_tiers or product itself
2. No tier-based discount for retail
3. Optional: sale/promotional price with date range
4. Delivery charge added based on location (inside/outside Dhaka)
5. Free delivery if order >= free_delivery_minimum
```

### Online Payment Flow (SSLCommerz) — Native Storefront
```
1. Customer places order on native retail storefront (/shop/checkout)
2. Customer clicks "Pay Now"
3. ERP PaymentService initiates SSLCommerz transaction directly
4. Customer redirected to SSLCommerz gateway
5. Customer completes payment
6. SSLCommerz callback → ERP (/api/shop/payment/callback)
7. ERP verifies transaction, updates online_payments
8. Order status updated to confirmed
9. Customer redirected to success page (/shop/checkout/success)
```

### Online Payment Flow (bKash) — Native Storefront
```
1. Customer selects bKash as payment method on native storefront
2. ERP PaymentService initiates bKash transaction
3. Customer redirected to bKash payment page
4. Customer completes payment
5. bKash callback → ERP (/api/shop/payment/callback)
6. ERP verifies transaction, records in online_payments
7. Order status updated to confirmed
8. Customer redirected to success page
```

### Delivery Management
```
1. Order confirmed → Delivery created (status: pending)
2. Assign courier → Update courier_name + tracking
3. Courier picks up → status: picked_up
4. In transit → status: in_transit
5. Out for delivery → status: out_for_delivery
6. Delivered → status: delivered, order status → delivered
7. Failed → status: failed, retry or return to warehouse
```

### Retail Return Flow
```
1. Customer requests return within return_window_days (7 days default)
2. Return created with status: pending
3. Admin reviews:
   - Approve: stock returned, refund processed
   - Reject: reason provided
4. On approval:
   a. Stock transaction (sale_return) created
   b. Refund initiated:
      - Online payment → gateway refund
      - bKash → bKash refund API
      - Cash → manual refund record
   c. Return status → completed
5. Product condition tracking:
   - Good: restock as available
   - Damaged: restock as damaged (separate tracking)
   - Expired: do not restock
```

### Courier Integration (Future)
```
Planned integrations:
- Pathao API (auto create shipment)
- Steadfast API
- RedX API
- Paperfly API

For Phase 1: Manual courier assignment + tracking number entry
```

## Events & Listeners

| Event | Listener | Description |
|-------|----------|-------------|
| RetailOrderCreated | CheckStockAvailability | Verify stock before confirming |
| RetailOrderConfirmed | ReserveStock | Reserve stock in inventory |
| RetailOrderShipped | NotifyCustomer | Send tracking info to customer |
| RetailOrderDelivered | CreateInvoice | Auto-generate receipt |
| PaymentReceived | UpdateOrderPaymentStatus | Mark order as paid |
| PaymentFailed | NotifyAdmin | Alert admin of failed payment |
| RetailReturnRequested | NotifyAdmin | Alert for review |
| RetailReturnApproved | RestockAndRefund | Process return |
| DeliveryFailed | NotifyAdmin | Alert for failed delivery |

## Validation Rules

### Retail Order Create
```php
'customer_id'    => 'nullable|exists:customers,id', // May be guest
'customer_name'  => 'required_without:customer_id|string|max:255',
'customer_phone' => 'required_without:customer_id|string|max:20',
'delivery_address' => 'required|string',
'delivery_type'  => 'required|in:inside_dhaka,outside_dhaka,pickup',
'items'          => 'required|array|min:1',
'items.*.product_id'  => 'required|exists:products,id',
'items.*.qty'         => 'required|integer|min:1',
'items.*.price_bdt'   => 'required|numeric|min:0',
```

### Delivery Create
```php
'courier_name'       => 'nullable|string|max:255',
'delivery_type'      => 'required|in:inside_dhaka,outside_dhaka,pickup',
'delivery_address'   => 'required_unless:delivery_type,pickup|string',
'recipient_name'     => 'required|string|max:255',
'recipient_phone'    => 'required|string|max:20',
```

### Return Create
```php
'sales_order_id' => 'required|exists:sales_orders,id',
'reason'         => 'required|string|min:10',
'items'          => 'required|array|min:1',
'items.*.product_id'  => 'required|exists:products,id',
'items.*.qty'         => 'required|integer|min:1',
'items.*.condition'   => 'required|in:good,damaged,expired',
```

## Developer Notes

1. Retail orders from native storefront have `source = 'retail_storefront'`
2. Walk-in/phone orders created directly in ERP have `source = 'erp'` or `source = 'phone'`
3. Orders imported from WooCommerce (one-time migration) have `source = 'woocommerce'`
4. Online payment integration is now **native** — SSLCommerz, bKash, Nagad called directly from Laravel (no WooCommerce middleware)
5. Retail storefront module can be toggled on/off from Settings > Modules
6. For Phase 1, delivery management is manual (no courier API integration)
7. Retail customer may be a guest (no customer_id) — use customer_name + customer_phone
8. Delivery charges configured in `storefront_settings` table (module: retail_storefront)
9. Refund for online payments must go through the original payment gateway
10. Retail invoices are simpler (receipt format) vs wholesale (detailed tax invoice)
11. Consider adding a `coupons` table for promotional discounts in future
12. Stock deduction for retail orders follows same logic as wholesale (via StockService)
13. **Fake Order Detection**: On storefront order, ERP checks IP against `ip_blacklist` table and runs detection rules (see 03b-domestic-logistics.md)
14. **Courier Integration**: Retail deliveries are managed in Domestic Logistics module (03b) — retail orders create `courier_parcels` entries
15. **Storefront routes** are wrapped in `module.active:retail_storefront` middleware — returns 404 if module inactive
16. **Guest checkout** controlled by `storefront_settings` key `allow_guest_checkout` — creates temporary customer record
