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

### 4.1 README Documentation

Status: **Completed**

The README now describes this project as a Laravel + Filament business dashboard for inventory, purchase costing, sales, accounts, backups, and reporting.

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

Priority: **Completed**

### 4.2 Project Positioning

Status: **Completed**

The product identity is now documented as an installable business dashboard for import/wholesale businesses.

Recommended positioning options:

- Generic SME Dashboard: for small and medium businesses that need inventory, sales, purchase, due, and expense management.
- Import Business Dashboard: for businesses importing products from China or other countries and needing purchase costing, stock, and sales tracking.
- Gadget/Wholesale ERP Lite: for wholesale gadget businesses, electronics businesses, and local distributors.

Recommended choice:

> **Inventory, Purchase Costing & Sales Management System for Import/Wholesale Businesses**

Priority: **Completed**

### 4.3 Report Export Route Structure

Status: **Completed**

Report export logic now lives behind controller/service classes instead of being embedded in `routes/web.php`.

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

Priority: **Completed**

### 4.4 Default Admin Password Risk

Status: **Completed**

The database seeder uses admin credentials from environment variables, does not include a weak fallback password, and validates admin password strength before creating the Super Admin user.

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

Current implementation:

- `ADMIN_PASSWORD` is required for the database seeder
- `ADMIN_PASSWORD` must be at least 12 characters
- Uppercase letters, lowercase letters, numbers, and symbols are required
- `admin:ensure-super` uses the same password validation
- `.env.example`, README, and deployment docs document the requirement

Priority: **Completed**

### 4.5 Public Repo Privacy Check Needed

Status: **Initial tracked-file scan completed on 2026-06-19**

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

Current scan result:

- `.env` is ignored and not tracked
- No tracked database dumps, private keys, access tokens, or archive backups were found
- No high-confidence live API keys were found in tracked files
- Composer security audit was cleared after updating `guzzlehttp/guzzle` and `guzzlehttp/psr7`
- `storage/app/private` is tracked only through `.gitignore`
- Documentation contains placeholder credentials only, such as `DB_PASSWORD=...`
- Demo/test email addresses are present and appear to be sample data

Repeat this scan before every public release or GitHub push.

Priority: **Recurring High**

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

Current status: **MVP coverage completed**

Latest verification on 2026-06-19:

```txt
php artisan test
115 tests, 407 assertions, passing
```

Keep adding tests for new SaaS, tenant, billing, and integration work.

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

Current status: **Local release dry run completed; server-specific staging deploy pending**

Deployment documentation exists in `docs/deployment.md`. Local release checks were completed on 2026-06-19:

- `composer validate --strict` passed
- `composer audit --abandoned=ignore` found no security vulnerability advisories
- `npm run build` passed
- `php artisan config:cache`, `route:cache`, and `view:cache` passed
- `php artisan schedule:list` shows daily `backup:database`
- `php artisan backup:database` created a database backup successfully
- `php artisan queue:restart` completed
- Public, pricing, docs, admin login, and health URLs returned HTTP 200 locally

The only remaining deployment step is a real server/staging run with production credentials, which cannot be completed from the local workspace alone.

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

Status: **Completed**

Tasks:

1. Rewrite README - Completed
2. Fix project name and positioning - Completed
3. Add `.env.example` - Completed
4. Remove weak default password fallback - Completed
5. Move report export logic to controller/service - Completed
6. Add admin dashboard screenshots - Completed
7. Improve invoice print design - Completed
8. Add role matrix documentation - Completed
9. Add demo data seeder - Completed
10. Add basic feature tests - Completed

Estimated priority: **Completed**

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

Release-readiness tasks:

```txt
1. Review and commit the completed Phase 1/security updates - Pending commit only
2. Run final local browser/HTTP/PDF QA for dashboard, invoice print, and report export - Completed locally
3. Run a local deployment dry run - Completed locally
4. Verify scheduler, queue worker, backup creation, and backup restore guide - Completed locally; repeat on target server
5. Repeat public-repo privacy scan immediately before push/release - Completed locally; repeat before push
6. Test with one real business dataset or realistic pilot dataset - Demo dataset completed; real pilot remains business validation
7. Update screenshots if any UI changes during final QA - Existing screenshots available; regenerate after final branding/demo data selection
8. Decide whether Phase 4 SaaS work should start now or stay later - Keep later
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

Before using it as a production product, the remaining work is now external/business-side:

- Commit and push after the user confirms no additional changes are needed
- Staging deployment verification with real server credentials
- Scheduler, queue, backup, and restore verification on the target server
- Repeat privacy/security scan immediately before public release
- Real business pilot testing
- Phase 4 SaaS build, if the product should become subscription SaaS

Recommended next move:

> Commit and deploy the single-business installable product to staging. After testing with real businesses, decide whether to convert it into a SaaS product.
