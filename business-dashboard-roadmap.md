# Business Dashboard - Repo Review & Development Roadmap

## 1. Project Overview

`bhd4232/Business-Dashboard` is a Laravel + Filament based business management dashboard. It is designed for inventory, sales, purchases, customer and supplier dues, expenses, account ledgers, and reporting.

This is not a blank Laravel project. It already contains real business logic, including:

- Sales and order management
- Purchase management
- Inventory and stock tracking
- Customer due tracking
- Supplier due tracking
- Expense management
- Account ledger
- CSV report export
- Role and permission system
- Audit log model
- Import and purchase costing workflow

Recommended positioning:

> **Business Dashboard - Inventory, Purchase Costing & Sales Management System for Import/Wholesale Businesses**

---

## 2. Current Tech Stack

The project currently uses:

- Laravel 12
- PHP 8.2+
- Filament 4.0
- Vite 6
- Tailwind CSS 4
- Node 20
- PHPUnit
- Laravel Pint
- Laravel Sail
- Laravel Pail

This is a modern stack and suitable for building a production-grade business dashboard.

---

## 3. Current Strong Points

### 3.1 Modern Laravel + Filament Stack

The project uses Laravel 12 and Filament 4. This is a strong foundation for a professional admin dashboard.

### 3.2 Business Modules Already Exist

The code indicates support for:

- Products
- Categories
- Orders
- Customers
- Purchases
- Suppliers
- Payments
- Expenses
- Accounts
- Stock movements
- Transaction ledger
- Reports

### 3.3 Report Service Exists

`ReportService` already separates report-related logic from the UI layer. This is a good architectural decision.

Current dashboard summary includes:

- Sales today
- Purchases today
- Customer payments today
- Supplier payments today
- Expenses today
- Customer due
- Supplier due
- Low stock count
- Coming soon count
- Account balance

### 3.4 Role and Permission System Exists

The app already has role-based access logic.

Current default roles:

- Super Admin
- Manager
- Sales Staff
- Inventory Staff
- Accountant

Current permission areas:

- Dashboard
- Sales
- Purchasing
- Inventory
- Accounts
- Reports
- Users

### 3.5 Stock Movement Logic Exists

The product model supports stock adjustment logic. Opening stock and adjustment stock are handled separately, and manual adjustments require a reason that is saved in the audit trail.

### 3.6 CSV Export Exists

The system can export multiple report types as CSV.

Current report export types:

- Sales report
- Purchase report
- Product profit report
- Stock report
- Low stock report
- Customer due report
- Supplier due report
- Expense report
- Account transaction report

### 3.7 Import/Wholesale Business Direction Exists

The project contains China-to-Bangladesh purchase-costing fields and items such as:

- Machine Purchase
- Inspection
- Freight to Ctg
- Duty
- C&F
- Misc
- Truck
- Load & Unload
- Spare Parts

This makes the project useful for import, wholesale, gadget, and inventory-heavy businesses.

---

## 4. Main Problems to Fix

### 4.1 README Is Still Default Laravel README

The current README does not describe this project. It still contains default Laravel framework content.

Recommended README structure:

```md
# Business Dashboard

A Laravel + Filament business management dashboard for inventory, sales, purchase, accounts, expenses, dues, and reports.

## Features
## Tech Stack
## Requirements
## Installation
## Environment Variables
## Admin Login
## Modules
## Report Export
## Deployment
## Roadmap
```

Priority: **High**

### 4.2 Project Positioning Is Not Clear

The app has many features, but the product identity is not clear.

Recommended positioning options:

- Generic SME Dashboard: for small and medium businesses that need inventory, sales, purchase, due, and expense management.
- Import Business Dashboard: for businesses importing products from China or other countries and needing purchase costing, stock, and sales tracking.
- Gadget/Wholesale ERP Lite: for wholesale gadget businesses, electronics businesses, and local distributors.

Recommended choice:

> **Inventory, Purchase Costing & Sales Management System for Import/Wholesale Businesses**

Priority: **High**

### 4.3 Route File Is Too Heavy

The report export logic is currently inside `routes/web.php`. This works, but it is not ideal for long-term maintenance.

Recommended structure:

```txt
app/
  Http/
    Controllers/
      Admin/
        ReportExportController.php
  Services/
    ReportService.php
    ReportExportService.php
  Enums/
    ReportType.php
```

Clean route example:

```php
Route::middleware('auth')
    ->get('/admin/reports/export/{type}', ReportExportController::class)
    ->name('reports.export');
```

Priority: **High**

### 4.4 Default Admin Password Risk

The database seeder uses admin credentials from environment variables, but any weak fallback password should be removed before production.

Recommended approach:

```php
'password' => env('ADMIN_PASSWORD')
    ? Hash::make(env('ADMIN_PASSWORD'))
    : throw new RuntimeException('ADMIN_PASSWORD is required'),
```

Also add:

- Strong password validation
- Login rate limit
- Production checklist
- `.env.example` documentation

Priority: **High**

### 4.5 Public Repo Privacy Check Needed

Because the repository is public, check for sensitive data.

Check these items:

- `.env` file accidentally committed or not
- API keys
- Database dumps
- Client emails
- Private invoices
- Uploaded files
- Access tokens
- Hardcoded credentials
- Client-specific branding

Priority: **High**

---

## 5. Feature Improvements Needed

### 5.1 Dashboard Analytics

The current dashboard summary is a good start, but it should be more actionable.

Add these widgets:

- Today sales
- Today purchase
- Today expense
- Current cash/bank balance
- Total customer due
- Total supplier due
- Monthly sales
- Monthly purchase
- Gross profit
- Net profit
- Low stock products
- Top selling products
- Top customers
- Top suppliers
- Expense breakdown
- Sales vs purchase chart
- Profit chart

Add alerts:

- Low stock alert
- Customer due alert
- Supplier due alert
- Negative profit alert
- Large expense alert

Priority: **High**

### 5.2 Inventory Module

Current product model already supports:

- Name
- Description
- SKU
- Barcode
- Unit
- Brand
- Cost price
- Sale price
- Stock
- Reorder level
- VAT rate
- Status
- Image
- Category

Recommended improvements:

| Feature | Priority |
|---|---:|
| Product CSV import/export | High |
| Stock adjustment reason | High |
| Damage/lost stock entry | High |
| Stock valuation report | High |
| Barcode scan support | Medium |
| Product image optimization | Medium |
| Bulk price update | Medium |
| Product variant support | Medium |
| Unit conversion | Medium |
| Stock transfer | Medium |

Product variant ideas useful for gadget/wholesale businesses:

- Color
- Model
- Size
- Storage
- Warranty
- Supplier SKU
- Serial number

Priority: **High**

### 5.3 Purchase Costing System

This is one of the strongest parts of the project. It should be improved and productized.

Recommended improvements:

| Feature | Priority |
|---|---:|
| Landed cost calculation | High |
| Cost per product auto distribution | High |
| Purchase payment schedule | High |
| Supplier invoice upload | High |
| Partial receive | Medium |
| Multi-currency support | Medium |
| Purchase status timeline | Medium |
| Custom cost category manager | Medium |

Recommended purchase flow:

```txt
Create Purchase
-> Select Supplier
-> Add Products
-> Add Product Cost
-> Add Shipping/Duty/C&F/Custom Costs
-> Calculate Landed Cost
-> Receive Stock
-> Create Supplier Due/Payment
-> Update Inventory
```

Priority: **High**

### 5.4 Sales and Order Flow

The app already has an order print route. The sales flow now needs polish.

Recommended improvements:

| Feature | Priority |
|---|---:|
| Professional invoice design | High |
| A4 print support | High |
| Partial payment support | High |
| Customer due auto update | High |
| Return/refund flow | High |
| Discount/tax/shipping fields | Medium |
| Thermal print support | Medium |
| Order status timeline | Medium |
| Sales channel field | Medium |

Recommended order status flow:

```txt
Draft
-> Confirmed
-> Completed

Cancelled
Returned
```

Priority: **High**

### 5.5 Accounts and Ledger

The project has account and transaction ledger logic. This should be made audit-safe.

Recommended improvements:

| Feature | Priority |
|---|---:|
| Cash and bank account separation | High |
| Customer payment ledger | High |
| Supplier payment ledger | High |
| Expense ledger | High |
| Transaction delete restriction | High |
| Adjustment entry with reason | High |
| Transfer between accounts | Medium |
| Opening balance lock | Medium |

Important rule:

Normal users should not be able to delete sensitive financial records.

Recommended approach:

- Only Super Admin can delete sensitive records
- Payment edits must be logged
- Old and new values must be saved in the audit log
- For serious accounting, prefer reversal entries instead of direct delete

Priority: **High**

### 5.6 Audit Log

`AuditLog` model exists, but actual logging should be verified.

Add or verify these logs:

| Audit Event | Priority |
|---|---:|
| Create record | High |
| Update record | High |
| Delete record | High |
| Payment edit/delete | High |
| Stock adjustment | High |
| Report export | Medium |
| User login/logout | Medium |
| User role change | High |

Audit log should store:

- User ID
- Action
- Model type
- Model ID
- Old values
- New values
- IP address
- User agent
- Timestamp

Priority: **High**

### 5.7 Role and Permission UI

Role logic exists, but it should be easy to understand from the admin panel.

Recommended improvements:

- Role matrix documentation
- Permission explanation in UI
- Custom role management
- Permission testing
- Better error message when access is denied

Suggested role matrix:

| Module | Super Admin | Manager | Sales Staff | Inventory Staff | Accountant |
|---|---:|---:|---:|---:|---:|
| Dashboard | Full | View | View | View | View |
| Sales | Full | Create/Edit | Create/Edit | View | View |
| Purchase | Full | Create/Edit | No | Create/Edit | View |
| Inventory | Full | Create/Edit | View | Create/Edit | No |
| Accounts | Full | Create/Edit | No | No | Create/Edit |
| Reports | Full | View/Export | View | View | View/Export |
| Users | Full | No | No | No | No |

Priority: **Medium/High**

### 5.8 Data Import and Export

Client onboarding will be difficult without import features.

Import features:

- Product CSV import
- Customer CSV import
- Supplier CSV import
- Opening stock import
- Account opening balance import
- Sample CSV download
- Import validation report

Export features:

- Product export
- Customer export
- Supplier export
- Stock export
- Full backup export

Priority: **High**

### 5.9 Backup and Recovery

Business data is sensitive and important. Backup is mandatory.

Recommended features:

| Feature | Priority |
|---|---:|
| Daily database backup | High |
| Manual backup button | Medium |
| Backup download permission | High |
| Auto cleanup old backups | Medium |
| Restore guide | High |
| Offsite backup | Medium |

Recommended package:

```bash
composer require spatie/laravel-backup
```

Priority: **High**

### 5.10 Notification System

The system should notify users about important business events.

Recommended notifications:

- Low stock
- Customer due
- Supplier payment due
- Large expense
- New order
- Purchase received
- Report exported
- User created/deactivated

Notification channels:

- In-app notification
- Email
- SMS/WhatsApp later

Priority: **Medium**

---

## 6. UI/UX Improvements

Filament default UI is functional, but product-grade polish is needed.

Improve these areas:

| Area | Improvement |
|---|---|
| Dashboard | KPI cards, charts, alerts |
| Navigation | Group menu by Sales, Purchase, Inventory, Accounts |
| Forms | Better sections, helper text, validation messages |
| Tables | Filters, badges, status colors, quick actions |
| Invoice | Professional brandable invoice template |
| Reports | Date filter, export buttons, visual summary |
| Empty states | Friendly empty-state messages |
| Mobile | Basic responsive check |

Priority: **Medium/High**

---

## 7. Testing Roadmap

Testing is needed before production use.

Minimum tests to add:

| Test | Priority |
|---|---:|
| User role permission test | High |
| Report export permission test | High |
| Sales order creates correct data | High |
| Purchase receive updates stock | High |
| Customer due calculation | High |
| Supplier due calculation | High |
| Low stock logic | Medium |
| CSV export response | Medium |
| Stock adjustment audit | Medium |

Recommended structure:

```txt
tests/
  Feature/
    Auth/
    Reports/
    Sales/
    Purchases/
    Inventory/
    Accounts/
  Unit/
    Services/
    Models/
```

Priority: **High**

---

## 8. Deployment Checklist

### Server Requirements

| Requirement | Recommended |
|---|---|
| PHP | 8.2+ |
| Node | 20 |
| Database | MySQL/MariaDB |
| Web server | Nginx/Apache |
| Queue | Database/Redis |
| Cron | Laravel scheduler |
| Storage | Local/S3 optional |

### Deployment Commands

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
```

Deployment documentation should cover:

- `.env` setup
- Database setup
- File permissions
- Queue setup
- Scheduler setup
- Backup setup
- Admin account setup
- Production security checklist

Priority: **High**

---

## 9. SaaS/Productization Plan

Currently, the project looks like a single-business dashboard. That is good for an MVP.

Recommended path:

```txt
Phase 1: Single business dashboard
Phase 2: White-label installable product
Phase 3: Multi-business SaaS
Phase 4: Subscription billing and client portal
```

Do not start with SaaS immediately.

SaaS will add complexity:

- Multi-tenancy
- Subscription billing
- Plan limits
- Tenant isolation
- Domain/subdomain mapping
- Client provisioning
- Tenant backups
- Platform admin panel

Recommended first target:

> Build a strong single-business installable product first. Then convert it into SaaS after testing with real businesses.

---

## 10. SaaS Features Needed Later

If this becomes SaaS, add:

- Tenant model
- Business/company profile
- User-to-tenant mapping
- Subscription plans
- Trial system
- Billing/payment gateway
- Feature limits per plan
- Storage limits
- Staff limits
- Report export limits
- Client portal
- Super admin dashboard
- Tenant backup/export
- Domain/subdomain setup

Priority: **Later**

---

## 11. Suggested Development Phases

### Phase 1 - Clean and Presentable MVP

Goal: Make the repo clean, understandable, and demo-ready.

Tasks:

1. Rewrite README
2. Fix project name and positioning
3. Add `.env.example`
4. Remove weak default password fallback
5. Move report export logic to controller/service
6. Add admin dashboard screenshots
7. Improve invoice print design
8. Add role matrix documentation
9. Add demo data seeder
10. Add basic feature tests

Estimated priority: **Start immediately**

### Phase 2 - Business-Ready Version

Goal: Make the app usable by real businesses.

Status: **Completed**

Tasks:

1. Product CSV import/export - Completed
2. Customer CSV import/export - Completed
3. Supplier CSV import/export - Completed
4. Purchase landed cost calculation - Completed
5. Stock adjustment reason and audit - Completed
6. Payment edit audit - Completed
7. Low stock notifications - Completed
8. Customer due notifications - Completed
9. Backup system - Completed
10. Better report filters - Completed
11. PDF invoice/export - Completed
12. Professional dashboard widgets - Completed

Estimated priority: **After Phase 1**

### Phase 3 - Sellable Product

Goal: Make it ready to sell to clients.

Status: **Completed**

Tasks:

1. Installer page - Completed
2. Company settings - Completed
3. Logo upload - Completed
4. Currency settings - Completed
5. Date/time format settings - Completed
6. White-label branding - Completed
7. Onboarding wizard - Completed
8. Demo mode - Completed
9. Documentation website - Completed
10. Landing page - Completed
11. Pricing page - Completed
12. License/activation system - Completed

Estimated priority: **After real business testing**

### Phase 4 - SaaS Version

Goal: Make it a subscription-based platform.

Tasks:

1. Multi-tenancy
2. Subscription billing
3. Client portal
4. Plan-based limits
5. Trial system
6. Auto provisioning
7. Platform admin dashboard
8. Tenant analytics
9. Usage tracking
10. Tenant backup/export

Estimated priority: **Later**

---

## 12. Immediate To-Do List

Start with these tasks first:

```txt
1. Rewrite README
2. Add proper project description
3. Add installation guide
4. Add .env.example
5. Remove default weak admin password
6. Refactor report export route
7. Improve invoice design
8. Add product/customer/supplier import
9. Add backup setup
10. Add role permission tests
```

---

## 13. Recommended README Short Description

```md
# Business Dashboard

Business Dashboard is a Laravel + Filament based inventory, sales, purchase, accounts, and reporting system for small businesses, importers, and wholesale operations.

It helps businesses manage products, stock, customers, suppliers, sales orders, purchases, expenses, payments, dues, and reports from one admin panel.
```

---

## 14. Recommended Repo Topics

Add these GitHub topics:

```txt
laravel
filament
inventory-management
business-dashboard
sales-management
purchase-management
accounting
erp-lite
small-business
wholesale
```

---

## 15. Final Assessment

This repository has strong potential.

It already has:

- A modern Laravel + Filament foundation
- Inventory logic
- Purchase logic
- Sales and report logic
- Role permissions
- Stock movement handling
- CSV exports
- Import/wholesale business direction

Before using it as a product, it needs:

- Better documentation
- Security cleanup
- Report code refactor
- UI/UX polish
- Import/export tools
- Backup system
- Audit logging verification
- Tests
- Deployment documentation

Recommended next move:

> First turn this into a clean single-business installable product. After testing with real businesses, convert it into a SaaS product.
