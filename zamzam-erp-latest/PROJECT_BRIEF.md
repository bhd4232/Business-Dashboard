# ZamZam ERP - Project Brief

This document summarizes the current test project, the intended ERP scope, and the recommended next development direction. It is compiled from `PROJECT_GUIDE.md`, `ERP_PHASE_ROADMAP.md`, and the detailed module documents in `docs/`.

## 1. Project Identity

ZamZam ERP is a China-to-Bangladesh wholesale business management system. The business flow starts from China supplier purchase, import/costing, and warehouse stock, then moves into wholesale sales, customer dues, payments, accounts, reports, and audit trails.

Current application focus:

- Product inventory
- Supplier purchase
- China-to-Bangladesh purchase costing
- Sales invoice
- Customer and supplier due tracking
- Accounts and ledger
- Reports and dashboard
- Role-based access and audit log

Future ERP vision:

- Multi-currency procurement in CNY, USD, and BDT
- International shipment/container tracking
- Per-product landed cost allocation
- Multi-warehouse inventory
- Wholesale and retail sales channels
- WooCommerce import and native storefronts
- Reseller panel
- Mobile app
- Conversation and AI agent hub
- Monthly automated reports via WhatsApp, Telegram, and email

## 2. Current Technical Stack

The current implemented project is a Laravel admin dashboard application.

| Area | Current Stack |
| --- | --- |
| Backend | Laravel 12 |
| Admin Panel | Filament 4 |
| Frontend Build | Vite |
| Styling | Tailwind CSS 4 |
| Database | Laravel migrations, expected MySQL/MariaDB for production |
| Admin Route | `/admin` |
| Public Route | `/` |
| Deployment Target | Coolify with GitHub and Nixpacks |

Important note: the larger planning docs in `docs/` describe a future architecture that includes Laravel 13, Inertia.js, Vue 3, Redis, React Native, storefronts, and AI features. The current working codebase described by `PROJECT_GUIDE.md` is Laravel 12 with Filament 4.

## 3. Current Core Modules

### Inventory

Implemented:

- Categories
- Products
- Stock Movements
- Product image upload
- Product status: `available`, `coming_soon`
- Product stock calculated from stock movement records
- Low stock tracking through reorder level
- Stock movement history on product view

Important stock rules:

- Opening, purchase, and return movements increase stock.
- Sale movements reduce stock.
- Adjustment movements use signed quantity.
- Negative stock is blocked.
- Stock is derived from movement history, not manually trusted as an isolated number.

### Purchasing and China-to-BD Costing

Implemented:

- Suppliers
- Purchases
- Purchase Items
- Supplier Payments
- Purchase statuses: `draft`, `received`, `cancelled`
- Fixed China-to-Bangladesh cost fields
- Custom purchase cost fields stored as JSON
- Purchase report/export support for fixed and dynamic custom costs

Fixed purchase-level costing fields:

- Machine Purchase
- Inspection
- Freight to Ctg
- Duty
- C&F
- Misc
- Truck
- Load & Unload
- Spare Parts
- CAM
- Positive Feeder
- Cylinder

Important purchase rules:

- Draft and cancelled purchases do not affect stock.
- Received purchases increase stock.
- Cancelling a received purchase removes related stock movements when safe.
- Cancellation is blocked if stock would become negative.
- Fixed and custom purchase costs are included in purchase total and due amount.
- These costs are purchase-level costs, not product item lines.
- Supplier balance is synced from received purchase due minus supplier payments.

### Coming Soon Products

The system creates placeholder products for common China-to-BD purchase cost items. They use status `coming_soon` and remain inactive so they do not appear in normal active product dropdowns.

Purpose:

- Keep future purchase-related items visible in product planning.
- Let dashboard/reporting count planned products.
- Avoid mixing purchase-level costs into active product stock.

### Sales

Implemented:

- Customers
- Orders
- Order Items
- Customer Payments
- Printable invoice route: `/admin/orders/{order}/print`

Important sales rules:

- Orders are multi-product sales invoices.
- Order totals come from items, discount, VAT, and paid amount.
- Confirmed and completed orders create sale stock movements.
- Draft and cancelled orders do not affect stock.
- Customer balance is opening balance plus confirmed/completed invoice due minus customer payments.
- Insufficient stock is blocked.

### Accounts and Ledger

Implemented:

- Accounts
- Customer Payments
- Supplier Payments
- Expense Categories
- Expenses
- Transaction Ledger

Important money rules:

- Account balance equals opening balance plus ledger inflow minus ledger outflow.
- Customer payments create `in` ledger entries.
- Supplier payments and expenses create `out` ledger entries.
- Overpayments are blocked.
- Supplier payments and expenses are blocked if the account would go negative.
- Transaction Ledger is intended as read-only history.

### Reports and Dashboard

Implemented dashboard metrics include:

- Today Sales
- Today Purchases
- Customer Payments
- Supplier Payments
- Today Expenses
- Customer Due
- Supplier Payable
- Account Balance
- Low Stock Items
- Coming Soon Products

Implemented report/export types:

- `sales`
- `purchases`
- `profit`
- `stock`
- `low-stock`
- `customer-dues`
- `supplier-dues`
- `expenses`
- `ledger`

Purchase report special behavior:

- Shows China-to-BD cost total.
- Shows custom cost labels dynamically as columns.
- CSV export includes fixed China-to-BD columns.
- CSV export includes dynamic custom cost columns from selected date range.

CSV export route:

```text
GET /admin/reports/export/{type}
```

### Users, Roles, Permissions, and Audit

Implemented roles:

- `super_admin`
- `manager`
- `sales_staff`
- `inventory_staff`
- `accountant`

Important access rules:

- Super Admin has full access.
- Manager can work with sales, purchasing, inventory, accounts, and reports.
- Sales Staff can work with sales, view inventory, and view reports, but cannot export reports.
- Inventory Staff can work with inventory, view purchasing, and view reports.
- Accountant can work with accounts, view sales/purchasing, and export reports.
- Inactive users cannot access the admin panel.
- User and Audit Log resources are restricted to Super Admin.

Audit log behavior:

- Core model create/update/delete events are logged.
- Logs store user, action, model type, model id, changed values, IP address, and user agent.
- Sensitive user fields are not stored in audit payloads.

## 4. Future ERP Scope From `docs/`

The `docs/` folder defines the larger product vision. These documents should be treated as long-term planning references, not fully implemented current behavior.

| Module | Future Scope |
| --- | --- |
| Auth & User Management | Spatie-style roles, permissions, activity log, reseller registration |
| Supplier & Procurement | China suppliers, supplier contacts, CNY pricing, purchase orders, price history |
| International Shipping | CN-to-BD shipments, shipment costs, documents, status history, landed cost allocation |
| Domestic Logistics | Courier partners, parcels, COD reconciliation, courier bills, fake order detection |
| Inventory & Warehouse | Multi-warehouse stock, stock transfers, adjustments, barcode/QR scanning |
| Wholesale Sales | Customer tags, price tiers, customer import, wholesale order lifecycle, returns |
| Retail Sales | Retail storefront, delivery charges, online payments, retail checkout |
| Credit & Payment | Credit ledger, payment allocation, aging reports, cheque tracking |
| Accounts & Finance | Chart of accounts, journals, expenses, balance sheet, cash flow |
| WooCommerce Integration | Store connection, one-time import, mappings, native storefront modules |
| Reseller Panel | Reseller self-service, own orders, invoices, payments, scoped data access |
| Reporting Dashboard | Role dashboards, PDF/Excel reports, monthly auto-report delivery |
| Mobile App | React Native app for sales, stock, collection, barcode scan |
| Conversation & AI Agent Hub | WhatsApp providers, chat inbox, workflow builder, AI tool calling |
| Design System | Admin UI patterns, colors, components, print/PDF invoice style |

## 5. Phase Roadmap Status

| Phase | Name | Status |
| --- | --- | --- |
| 0 | Project Stabilization | Done |
| 1 | Product and Inventory Foundation | Done |
| 2 | Supplier, Purchase, and China-to-BD Costing | Mostly done and evolving |
| 3 | Sales and Order Management | Done |
| 4 | Accounts and Payments | Done |
| 5 | Ledger, Dashboard, and Reporting | Done with future improvements planned |
| 6 | User, Role, Permission, and Audit | Done |
| 7 | Business Automation | Planned |
| 8 | UI/UX Polish and Production Readiness | In progress |
| 9 | Production Operations | Planned |

## 6. Recommended Immediate Priorities

1. Run `npm run build` and fix any frontend build issue.
2. Run focused automated tests for stock, purchase, sales, accounts, reports, and permissions.
3. Do a manual purchase costing smoke test in the admin panel.
4. Add per-product landed cost allocation.
5. Add shipment/container tracking.
6. Add PDF export for invoice and purchase report.
7. Add backup and restore documentation.
8. Finalize production hosting, domain, and database backup plan.

## 7. Development Rules

For every new module:

1. Create migration.
2. Create Eloquent model.
3. Add relationships.
4. Create Filament resource.
5. Configure form, table, infolist, and pages.
6. Add business logic in a model or service where appropriate.
7. Add permissions and audit checks if needed.
8. Add reports/exports if the module affects business visibility.
9. Add or update tests.
10. Run migrations and tests.
11. Update `PROJECT_GUIDE.md`.
12. Update `ERP_PHASE_ROADMAP.md`.

For every new field:

1. Create migration.
2. Add the field to model `$fillable`.
3. Add cast if needed.
4. Add form input.
5. Add table, infolist, report, and export display if relevant.
6. Update calculations if the field affects money or stock.
7. Add or update tests.
8. Update documentation.

For purchase costing:

1. Decide whether the cost is fixed or custom.
2. Fixed fields belong in `Purchase::CHINA_TO_BD_COST_FIELDS` and database columns.
3. Custom fields belong in `purchases.custom_costs` JSON.
4. Update purchase total and due calculations.
5. Ensure reports and CSV exports expose the field.
6. Add tests in purchase and report test suites.

## 8. Testing Checklist

Run before handoff:

```bash
php artisan test
npm run build
```

Focused tests:

```bash
php artisan test --filter=StockMovementTest
php artisan test --filter=PurchaseTest
php artisan test --filter=SalesOrderTest
php artisan test --filter=AccountsAndPaymentsTest
php artisan test --filter=ReportsTest
php artisan test --filter=PhaseSixPermissionsTest
```

Manual smoke checks:

1. Login to `/admin`.
2. Create category and product.
3. Create stock movements and confirm stock changes.
4. Create supplier and purchase.
5. Add fixed China-to-BD costs and custom cost fields.
6. Confirm purchase total and due calculations.
7. Mark purchase as received and confirm stock increases.
8. Create customer and sales invoice.
9. Confirm stock decreases for confirmed/completed sales.
10. Add customer and supplier payments.
11. Add expense and confirm account balance changes.
12. Check dashboard metrics.
13. Check purchase report and CSV export custom cost columns.
14. Check user permissions and audit logs.

## 9. Local Setup

Install dependencies:

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
```

Run locally:

```bash
php artisan serve
npm run dev
```

Open:

```text
http://localhost:8000
http://localhost:8000/admin
```

Default seeded admin:

```text
Email: admin@zamzamint.com
Password: password
```

Change the default password before production use.

## 10. Production Deployment Notes

Recommended stack:

- GitHub repository
- Coolify application
- Nixpacks build pack
- MySQL or MariaDB database
- HTTPS domain
- Persistent Laravel storage

Required production environment values:

```env
APP_NAME="ZamZam ERP"
APP_ENV=production
APP_KEY=base64:YOUR_APP_KEY
APP_DEBUG=false
APP_URL=https://your-domain.com

DB_CONNECTION=mysql
DB_HOST=your-db-host
DB_PORT=3306
DB_DATABASE=your-db-name
DB_USERNAME=your-db-user
DB_PASSWORD=your-db-password

SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync
FILESYSTEM_DISK=public
LOG_CHANNEL=stack
MAIL_MAILER=log
```

Post-deployment command:

```bash
php artisan migrate --force && php artisan storage:link && php artisan optimize:clear && php artisan config:cache && php artisan route:cache && php artisan view:cache
```

Persistent storage recommendation:

```text
/app/storage
/app/storage/app/public
```

## 11. Key Files

| Area | File or Folder |
| --- | --- |
| Product model | `app/Models/Product.php` |
| Purchase model | `app/Models/Purchase.php` |
| Order model | `app/Models/Order.php` |
| Stock movement model | `app/Models/StockMovement.php` |
| Report service | `app/Services/ReportService.php` |
| Purchase form | `app/Filament/Resources/Purchases/Schemas/PurchaseForm.php` |
| Purchase infolist | `app/Filament/Resources/Purchases/Schemas/PurchaseInfolist.php` |
| Purchase table | `app/Filament/Resources/Purchases/Tables/PurchasesTable.php` |
| Reports page | `app/Filament/Pages/Reports.php` |
| Reports view | `resources/views/filament/pages/reports.blade.php` |
| CSV exports | `routes/web.php` |
| Dashboard widget | `app/Filament/Widgets/BusinessOverview.php` |
| Admin panel provider | `app/Providers/Filament/AdminPanelProvider.php` |
| Seeder | `database/seeders/DatabaseSeeder.php` |

## 12. Documentation Source Map

Use these files as the source of truth:

- `PROJECT_GUIDE.md`: current implemented behavior and developer handoff notes
- `ERP_PHASE_ROADMAP.md`: phase status, done criteria, next priorities
- `docs/00-project-overview.md`: long-term ERP product vision
- `docs/DB-SCHEMA.md`: future complete database schema reference
- `docs/13-design-system.md`: future UI, component, print, and PDF design rules

Documentation rule:

- Any implemented behavior change must update `PROJECT_GUIDE.md`.
- Any phase/status/priority change must update `ERP_PHASE_ROADMAP.md`.
- Any large future module design should be documented under `docs/`.

