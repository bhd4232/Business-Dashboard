# Module 9: Reseller Panel

## Overview

A separate self-service panel for resellers within the ERP. Resellers can view their credit balance, order history, payment records, invoices, and product prices. This panel is NOT the WooCommerce store - it's an ERP-hosted portal for account management.

## Reseller Panel vs Native Wholesale Storefront

| Feature | Reseller Panel (ERP) | Wholesale Storefront (/wholesale/*) |
|---------|---------------------|--------------------------------------|
| Purpose | Account management, view balance | Browse & place orders |
| Login | ERP credentials (same account) | Same ERP credentials |
| Credit Info | Full ledger + balance | Summary only at checkout |
| Order Placement | View only (no ordering) | Full ordering capability |
| Invoice Access | All invoices + PDF download | Order history only |
| Payment History | Complete history | Basic |
| Price List | Tier-specific pricing | Tier-based catalog |
| Stock Check | Real-time stock | Real-time availability |

> **Note:** WooCommerce is a **one-time data importer only** — not an ongoing integration. The native Wholesale Storefront (`/wholesale/*`) is the permanent order placement interface for resellers.

## Database Tables

Uses existing tables from other modules:
- `users` (reseller user account)
- `customers` (reseller business profile)
- `credit_ledger` (credit history)
- `sales_orders` + `so_items` (order history)
- `invoices` + `invoice_items` (invoices)
- `payments` + `payment_allocations` (payment history)
- `price_tiers` + `product_price_tiers` (pricing)

### Additional Reseller-Specific Table

### reseller_profiles
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| user_id | bigint FK users.id | Link to auth user |
| customer_id | bigint FK customers.id | Link to customer record |
| business_name | varchar(255) | |
| trade_license | varchar(100) nullable | |
| nid_front_path | varchar(500) nullable | NID photo front |
| nid_back_path | varchar(500) nullable | NID photo back |
| business_address | text | |
| business_phone | varchar(20) | |
| business_email | varchar(255) nullable | |
| owner_name | varchar(255) | Proprietor/owner name |
| reference | varchar(255) nullable | Who referred this reseller |
| approval_status | enum | pending, approved, rejected, suspended |
| approved_by | bigint FK nullable users.id | |
| approved_at | timestamp nullable | |
| rejected_reason | text nullable | |
| suspended_reason | text nullable | |
| last_login_at | timestamp nullable | |
| created_at | timestamp | |
| updated_at | timestamp | |

### reseller_notifications
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| user_id | bigint FK users.id | |
| type | enum | order_status, payment_received, credit_limit_change, overdue, new_product, price_change |
| title | varchar(255) | |
| message | text | |
| data | json nullable | Related entity IDs |
| is_read | boolean default false | |
| read_at | timestamp nullable | |
| created_at | timestamp | |

## Reseller Registration & Approval Flow

```
Step 1: Reseller registers on Native Wholesale Storefront (/wholesale/register)
         OR fills form on ERP registration page (/reseller/register)
         ↓
Step 2: ERP creates user (role: reseller) + customer + reseller_profile
         approval_status = pending
         ↓
Step 3: Admin/Manager reviews in ERP:
         - Verify trade license
         - Verify NID
         - Check business details
         - Assign price tier
         - Set credit limit
         ↓
Step 4: Admin approves:
         - approval_status = approved
         - Assign price_tier_id to customer
         - Set credit_limit_bdt on customer
         - Activate user (is_active = true)
         - Send welcome email with ERP panel login + wholesale storefront link
         ↓
Step 5: Reseller can now:
         - Login to ERP Reseller Panel (/reseller) → view balance, invoices, history
         - Login to Native Wholesale Storefront (/wholesale) → browse & place orders
```

## API Routes (Reseller-Specific)

All routes require `auth:sanctum` + `role:reseller` middleware.
All routes ensure reseller can ONLY access their own data (scoped by customer_id).

### Dashboard
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /api/reseller/dashboard | Dashboard summary data |

### Profile
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /api/reseller/profile | Get reseller profile |
| PUT | /api/reseller/profile | Update profile (limited fields) |
| POST | /api/reseller/profile/documents | Upload trade license/NID |

### Credit & Balance
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /api/reseller/credit-summary | Credit limit, balance, available credit |
| GET | /api/reseller/credit-ledger | Credit ledger (paginated) |
| GET | /api/reseller/credit-statement | Statement (date range) |

### Orders
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /api/reseller/orders | Order history (paginated) |
| GET | /api/reseller/orders/{id} | Order detail (own orders only) |

### Invoices
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /api/reseller/invoices | Invoice list |
| GET | /api/reseller/invoices/{id} | Invoice detail |
| GET | /api/reseller/invoices/{id}/pdf | Download invoice PDF |

### Payments
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /api/reseller/payments | Payment history |
| GET | /api/reseller/payments/{id} | Payment detail |

### Products & Pricing
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /api/reseller/products | Product list with tier pricing |
| GET | /api/reseller/products/{id} | Product detail with price |
| GET | /api/reseller/products/{id}/stock | Stock availability |

### Notifications
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /api/reseller/notifications | List notifications |
| POST | /api/reseller/notifications/{id}/read | Mark as read |
| POST | /api/reseller/notifications/read-all | Mark all as read |

## Reseller Dashboard Data

```json
{
  "credit_summary": {
    "credit_limit_bdt": 500000,
    "outstanding_balance_bdt": 120000,
    "available_credit_bdt": 380000,
    "overdue_amount_bdt": 30000
  },
  "order_summary": {
    "total_orders": 45,
    "pending_orders": 3,
    "in_transit": 2,
    "delivered_this_month": 8
  },
  "payment_summary": {
    "total_paid_bdt": 2500000,
    "paid_this_month_bdt": 150000,
    "last_payment": {
      "date": "2026-04-28",
      "amount_bdt": 50000,
      "method": "bkash"
    }
  },
  "recent_orders": [...],
  "notifications": {
    "unread_count": 5
  }
}
```

## Frontend Pages (Reseller Panel)

Reseller panel uses a **different layout** from the admin panel - simpler, customer-facing design.

| Page | Route | Component |
|------|-------|-----------|
| Login | /reseller/login | Reseller/Auth/Login.vue |
| Dashboard | /reseller | Reseller/Dashboard/Index.vue |
| Profile | /reseller/profile | Reseller/Profile/Index.vue |
| Credit Summary | /reseller/credit | Reseller/Credit/Index.vue |
| Credit Ledger | /reseller/credit/ledger | Reseller/Credit/Ledger.vue |
| Credit Statement | /reseller/credit/statement | Reseller/Credit/Statement.vue |
| Orders | /reseller/orders | Reseller/Orders/Index.vue |
| Order Detail | /reseller/orders/{id} | Reseller/Orders/Show.vue |
| Invoices | /reseller/invoices | Reseller/Invoices/Index.vue |
| Invoice Detail | /reseller/invoices/{id} | Reseller/Invoices/Show.vue |
| Payments | /reseller/payments | Reseller/Payments/Index.vue |
| Products & Prices | /reseller/products | Reseller/Products/Index.vue |
| Product Detail | /reseller/products/{id} | Reseller/Products/Show.vue |
| Notifications | /reseller/notifications | Reseller/Notifications/Index.vue |

## Admin Pages (for managing resellers)

These are in the admin panel, accessible by Admin/Manager:

| Page | Route | Component |
|------|-------|-----------|
| Reseller Applications | /resellers | Resellers/Index.vue |
| Reseller Detail | /resellers/{id} | Resellers/Show.vue |
| Approve Reseller | /resellers/{id}/approve | Resellers/Approve.vue |
| Manage Credit Limit | /resellers/{id}/credit-limit | Resellers/CreditLimit.vue |
| Assign Price Tier | /resellers/{id}/price-tier | Resellers/PriceTier.vue |

## Business Logic

### Data Scoping (Security Critical)
```php
class ResellerScope
{
    // EVERY reseller API query must be scoped to the authenticated reseller's customer_id
    
    public function apply($query, User $user)
    {
        $resellerProfile = $user->resellerProfile;
        
        if (!$resellerProfile || $resellerProfile->approval_status !== 'approved') {
            return $query->whereRaw('1 = 0'); // Return nothing
        }
        
        return $query->where('customer_id', $resellerProfile->customer_id);
    }
}

// Usage in controllers:
public function getOrders(Request $request)
{
    return SalesOrder::where('type', 'wholesale')
        ->where('customer_id', $request->user()->resellerProfile->customer_id)
        ->paginate(20);
}
```

### Credit Summary Calculation
```php
public function getCreditSummary(ResellerProfile $reseller): array
{
    $customer = $reseller->customer;
    
    $overdueInvoices = Invoice::where('customer_id', $customer->id)
        ->where('status', 'overdue')
        ->sum('due_bdt');
    
    return [
        'credit_limit_bdt' => $customer->credit_limit_bdt,
        'outstanding_balance_bdt' => $customer->outstanding_balance_bdt,
        'available_credit_bdt' => $customer->credit_limit_bdt - $customer->outstanding_balance_bdt,
        'overdue_amount_bdt' => $overdueInvoices,
    ];
}
```

### Tier Pricing for Reseller
```php
public function getProductsWithPricing(ResellerProfile $reseller)
{
    $tierId = $reseller->customer->price_tier_id;
    
    return Product::with(['category', 'productPriceTiers' => function ($q) use ($tierId) {
            $q->where('price_tier_id', $tierId)->where('is_active', true);
        }])
        ->where('is_active', true)
        ->paginate(50);
}
```

### Notification Triggers
```
1. Order status change → notify reseller
2. Payment received → notify reseller
3. Credit limit changed → notify reseller
4. Invoice overdue → notify reseller
5. New product added → notify reseller (if in their category interest)
6. Price change → notify reseller
7. Reseller approved/rejected → notify via email
```

### Reseller Profile Update Rules
```
Reseller CAN update:
  - Business phone
  - Business email
  - Business address
  - Owner name

Reseller CANNOT update:
  - Business name (requires admin approval)
  - Trade license (requires admin re-verification)
  - Credit limit
  - Price tier
  - Approval status
```

## Events & Listeners

| Event | Listener | Description |
|-------|----------|-------------|
| ResellerRegistered | NotifyAdminsForApproval | Email/notify admin team |
| ResellerApproved | ActivateResellerAccount | Enable user + grant wholesale storefront access |
| ResellerApproved | SendWelcomeEmail | Send login credentials |
| ResellerRejected | SendRejectionEmail | Inform reseller |
| ResellerSuspended | DisableResellerAccess | Block login + wholesale storefront access |
| OrderStatusChanged | NotifyReseller | Push notification to reseller |
| PaymentReceived | NotifyResellerPaymentReceived | Confirm payment receipt |
| CreditLimitChanged | NotifyResellerCreditUpdate | Inform of new credit limit |
| InvoiceOverdue | NotifyResellerOverdue | Remind of overdue payment |
| PriceTierChanged | NotifyResellerPriceUpdate | Inform of price changes |

## Validation Rules

### Reseller Registration
```php
'business_name'     => 'required|string|max:255',
'business_phone'    => 'required|string|max:20',
'business_email'    => 'nullable|email|max:255',
'business_address'  => 'required|string',
'owner_name'        => 'required|string|max:255',
'trade_license'     => 'nullable|string|max:100',
'nid_front'         => 'nullable|image|max:2048',
'nid_back'          => 'nullable|image|max:2048',
'name'              => 'required|string|max:255', // Login name
'email'             => 'required|email|unique:users,email',
'phone'             => 'required|string|max:20|unique:users,phone',
'password'          => 'required|string|min:8|confirmed',
```

### Profile Update (Reseller self)
```php
'business_phone'    => 'nullable|string|max:20',
'business_email'    => 'nullable|email|max:255',
'business_address'  => 'nullable|string',
'owner_name'        => 'nullable|string|max:255',
```

## Developer Notes

1. Reseller panel MUST have strict data scoping - reseller can NEVER see other resellers' data
2. Use a separate route group with `middleware: [auth:sanctum, role:reseller]`
3. Reseller panel uses a different Vue layout (simplified, branded design)
4. Consider rate limiting reseller API endpoints (60 requests/minute)
5. Reseller panel does NOT allow order placement - that's done via the **Native Wholesale Storefront** (`/wholesale/*`)
6. Invoice PDFs served from ERP storage - use signed URLs for security
7. Notification system should support push notifications for mobile app (future)
8. Reseller documents (NID, trade license) stored in `storage/app/private/resellers/{id}/`
9. Credit ledger API must use cursor-based pagination (data can be very large)
10. All reseller actions must be logged in activity_log for audit trail
