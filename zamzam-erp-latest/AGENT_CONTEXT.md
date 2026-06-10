# ZamZam ERP - Agent Context

এই ফাইলটি AI agent, developer, maintainer, অথবা future contributor-এর জন্য দ্রুত project context বোঝার handoff document। কাজ শুরু করার আগে এই ফাইলটি পড়ুন, তারপর প্রয়োজন অনুযায়ী `PROJECT_BRIEF.md`, `PROJECT_GUIDE.md`, `ERP_PHASE_ROADMAP.md`, এবং `docs/` দেখুন।

## 1. Project Direction

ZamZam ERP একটি China-to-Bangladesh wholesale ERP system। লক্ষ্য হলো purchase, import costing, inventory, sales, accounts, reports, website, এবং future automation এক system থেকে manage করা।

New target direction:

- Framework target: latest Laravel 13.x
- PHP target: PHP 8.3+
- Dashboard/admin UI target: Tyro Dashboard
- Admin panel এবং public/business website একই Laravel application থেকে manage হবে
- এক dashboard থেকে ERP modules, website content, storefront settings, reports, users, roles, এবং business operations control করা যাবে
- Current old implementation/docs-এ Filament 4/Laravel 12 reference থাকলে সেটাকে legacy/current-state context হিসেবে ধরতে হবে, final target হিসেবে নয়

Official Laravel 13 docs confirm Laravel 13.x is available and requires PHP 8.3+. Use official Laravel 13 documentation as the primary framework reference.

## 2. Important Context Rule

এই repo-তে দুই ধরনের documentation আছে:

1. Current/legacy implementation notes
2. Future full ERP planning docs

Agent-এর কাজ করার সময় এই distinction ধরে এগোতে হবে।

Current/legacy files:

- `PROJECT_GUIDE.md`
- `ERP_PHASE_ROADMAP.md`
- Existing Laravel 12 + Filament 4 implementation notes

New target files:

- `PROJECT_BRIEF.md`
- `AGENT_CONTEXT.md`
- `docs/` future ERP module specifications

Decision rule:

- যদি code maintenance করতে হয়, আগে existing codebase দেখে কাজ করুন।
- যদি new build/refactor/scaffold করতে হয়, Laravel 13 + Tyro Dashboard target ধরে কাজ করুন।
- যদি Filament এবং Tyro Dashboard conflict করে, new target architecture-এ Tyro Dashboard প্রাধান্য পাবে।
- Business logic সবসময় existing ERP rules থেকে নিতে হবে, UI/admin implementation নতুন target অনুযায়ী করা যাবে।

## 3. Product Goal

একটি single-dashboard ERP তৈরি করতে হবে যেখানে admin/staff একই জায়গা থেকে নিচের কাজগুলো করতে পারবে:

- Product catalog management
- Inventory and stock movement
- Supplier management
- China purchase costing
- Purchase receive and supplier due
- Customer management
- Wholesale sales invoice
- Customer payment and due
- Supplier payment
- Expense and account balance
- Transaction ledger
- Reports and CSV/PDF exports
- Website content and storefront management
- User, role, permission, and audit control
- Future shipping, landed cost, reseller, mobile, and AI automation modules

## 4. Target Architecture

Recommended target architecture:

```text
Laravel 13 Application
├── Tyro Dashboard Admin
│   ├── ERP operations
│   ├── Reports
│   ├── Website management
│   ├── Storefront settings
│   ├── Users, roles, permissions
│   └── Audit logs
├── Public Website
│   ├── Home
│   ├── Product/catalog pages
│   ├── About/company pages
│   ├── Contact/inquiry forms
│   └── Future wholesale/retail storefront
├── Business Services
│   ├── Stock service
│   ├── Purchase costing service
│   ├── Sales/order service
│   ├── Account ledger service
│   └── Report service
└── Database
    ├── ERP transaction tables
    ├── Website CMS tables
    ├── Role/permission tables
    └── Audit/log tables
```

Core principle:

- Dashboard is the control center.
- Website is managed from the dashboard.
- ERP calculations must stay in services/models, not in Blade views or dashboard UI only.
- Every stock or money mutation must be traceable.

## 5. Target Tech Stack

| Area | Target |
| --- | --- |
| Backend | Laravel 13.x |
| PHP | 8.3+ |
| Admin/Dashboard | Tyro Dashboard |
| Frontend Build | Vite |
| Styling | Tailwind CSS |
| Database | MySQL or MariaDB |
| Auth | Laravel auth with role/permission layer |
| Reports | Service-based reports with CSV first, PDF later |
| Deployment | GitHub + Coolify + Nixpacks |
| Storage | Laravel public storage with persistent volume |

Optional/future:

- Redis for cache/queue
- Laravel Reverb for realtime features
- Laravel AI SDK for future AI agent hub
- React Native mobile app
- WooCommerce importer and native storefront modules

## 6. Dashboard Scope

Tyro Dashboard should manage:

- Dashboard KPIs
- Products
- Categories
- Stock Movements
- Suppliers
- Purchases
- Purchase Items
- China-to-BD purchase costs
- Customers
- Orders/Sales Invoices
- Customer Payments
- Supplier Payments
- Accounts
- Expenses
- Transaction Ledger
- Reports and exports
- Website pages/sections
- Website menus
- Website banners/sliders
- Website product visibility
- Storefront settings
- Users and roles
- Audit logs

Admin dashboard route should be consistent and predictable. Recommended:

```text
/admin
```

Public website route:

```text
/
```

## 7. Website Management Scope

The website should not be a separate disconnected app. It should read managed content from the same Laravel database.

Initial website-manageable content:

- Site identity: logo, name, contact, address, social links
- Homepage banner/hero
- Featured categories
- Featured products
- About content
- Contact information
- Footer links
- SEO title and meta description

Future website/storefront scope:

- Wholesale catalog
- Retail catalog
- Customer login
- Reseller login
- Cart/checkout
- Order inquiry
- WooCommerce import support

## 8. Core Business Rules

Inventory:

- Stock must be calculated from stock movement history.
- Purchase receive increases stock.
- Sales confirmation/completion decreases stock.
- Negative stock must be blocked.
- Adjustment must be signed and audited.

Purchasing:

- Purchase statuses: `draft`, `received`, `cancelled`
- Draft/cancelled purchases do not affect stock.
- Received purchases affect stock.
- Cancelling a received purchase must reverse purchase stock movement only when safe.
- Supplier balance equals received purchase due minus supplier payments.

China-to-BD costing:

- Fixed purchase-level costs must remain optional.
- Custom purchase costs should be stored as JSON or a normalized child table if the new build needs better reporting.
- Purchase-level costs are not product dropdown items.
- Total and due must include item subtotal plus fixed/custom purchase costs.

Sales:

- Orders are multi-product invoices.
- Draft/cancelled orders do not affect stock.
- Confirmed/completed orders affect stock.
- Customer due equals invoice due minus payments.
- Overpayment must be blocked unless a deliberate advance-payment feature is added.

Accounts:

- Account balance equals opening balance plus ledger inflow minus ledger outflow.
- Customer payment creates inflow.
- Supplier payment creates outflow.
- Expense creates outflow.
- Ledger records should be append-only or reversible, not silently deleted.

Audit:

- Important create/update/delete actions must be logged.
- Payments, stock, accounts, purchases, sales, and user changes are high-risk.
- Sensitive fields should not be stored in audit payloads.

## 9. Main ERP Modules

Current must-have modules:

1. Inventory
2. Supplier and Purchase
3. China-to-BD Costing
4. Sales and Invoice
5. Customer Due and Payment
6. Supplier Payment
7. Accounts and Expenses
8. Ledger
9. Reports and Dashboard
10. Users, Roles, Permissions
11. Audit Logs
12. Website Management

Future modules:

1. International Shipping
2. Shipment Documents
3. Per-product Landed Cost
4. Multi-warehouse Stock
5. Barcode/QR
6. Domestic Courier
7. Wholesale Storefront
8. Retail Storefront
9. Reseller Panel
10. WooCommerce Importer
11. Mobile App
12. Monthly Auto Report
13. Conversation and AI Agent Hub

## 10. Recommended Build Order

For a Laravel 13 + Tyro Dashboard rebuild or major refactor:

1. Fresh Laravel 13 setup and environment baseline
2. Tyro Dashboard integration
3. Auth, users, roles, and permissions
4. Website layout and dashboard-managed site settings
5. Product and category module
6. Stock movement module
7. Supplier and purchase module
8. China-to-BD purchase costing
9. Customer and sales invoice module
10. Payments, accounts, expenses, and ledger
11. Reports and exports
12. Audit logs
13. Deployment checklist
14. Future advanced modules

## 11. Development Standards

Use these rules for all implementation:

- Prefer Laravel conventions.
- Keep business logic in models/services, not only controllers or views.
- Use database transactions for stock and money operations.
- Use policies/gates/middleware for permissions.
- Add validation for all money, quantity, and status transitions.
- Do not allow negative stock unless a deliberate business rule exists.
- Do not allow negative account balance for restricted accounts.
- Every report should come from a service/query layer.
- Every export should respect role permissions.
- Website content should be editable from dashboard where practical.
- Update documentation after feature changes.

## 12. Suggested Folder Pattern

Recommended high-level structure:

```text
app/
├── Models/
├── Services/
│   ├── Inventory/
│   ├── Purchasing/
│   ├── Sales/
│   ├── Accounts/
│   ├── Reports/
│   └── Website/
├── Http/
│   ├── Controllers/
│   │   ├── Admin/
│   │   └── Website/
│   ├── Requests/
│   └── Middleware/
├── Policies/
└── View/Components/

resources/
├── views/
│   ├── admin/
│   ├── website/
│   └── reports/
├── css/
└── js/

database/
├── migrations/
├── seeders/
└── factories/
```

If Tyro Dashboard ships with its own preferred folder/layout conventions, follow Tyro for dashboard UI while keeping business logic in Laravel services/models.

## 13. Test and Verification

Before handoff, run:

```bash
php artisan test
npm run build
```

Minimum manual checks:

1. Login to dashboard.
2. Update website settings and verify public website changes.
3. Create category and product.
4. Add stock movement and verify stock.
5. Create supplier and purchase.
6. Add China-to-BD fixed/custom costs.
7. Receive purchase and verify stock.
8. Create customer and sales invoice.
9. Confirm sale and verify stock decrease.
10. Add customer payment and supplier payment.
11. Add expense and verify account balance.
12. Check reports.
13. Verify role restrictions.
14. Verify audit logs.

## 14. Documentation Rules

When implementation changes:

- Update `PROJECT_GUIDE.md` for current behavior.
- Update `ERP_PHASE_ROADMAP.md` for phase status.
- Update `PROJECT_BRIEF.md` if project direction changes.
- Update this `AGENT_CONTEXT.md` if agent handoff rules or target architecture changes.
- Add or update module docs under `docs/` for major future scope changes.

## 15. Quick Start For Agents

When a new agent starts:

1. Read this file first.
2. Read `PROJECT_BRIEF.md`.
3. Check current codebase before assuming old docs match implementation.
4. If the task is about new architecture, use Laravel 13 + Tyro Dashboard as the target.
5. If the task is about fixing existing code, preserve current behavior unless the user explicitly asks for migration/refactor.
6. Keep admin and website connected through the same Laravel app and database.

