# ZamZam ERP - Implementation Plan

এই ফাইলটি Laravel 13 + Tyro Dashboard ভিত্তিক ZamZam ERP build/refactor plan। এটি agent/developer-কে current repo state, target architecture, এবং phase-wise কাজের order বুঝতে সাহায্য করবে।

## 1. Verified Current State

Inspection date: 2026-06-10

Laravel app location:

```text
my-erp-app/
```

Current verified stack from `my-erp-app/composer.json`:

| Area | Current Value |
| --- | --- |
| PHP | `^8.3` |
| Laravel | `^13.8` |
| Tyro Dashboard | `hasinhayder/tyro-dashboard ^1.36` |
| Sanctum | `^4.0` |
| Vite | `^8.0.0` |
| Tailwind | `^4.0.0` |

Current verified structure:

- `my-erp-app/routes/web.php` only returns the default `welcome` view.
- `my-erp-app/database/migrations/` currently has default Laravel users/cache/jobs migrations plus Sanctum token migration.
- ERP models, migrations, services, and admin resources are not yet implemented in this Laravel 13 app.
- Tyro Dashboard config files exist:
  - `my-erp-app/config/tyro-dashboard.php`
  - `my-erp-app/config/tyro-login.php`
- User model already uses Tyro-related traits:
  - `HasTyroRoles`
  - `HasTwoFactorAuth`

Important conclusion:

The project is already on the new target baseline: Laravel 13 + Tyro Dashboard. The next work is not framework migration; it is ERP module implementation, Tyro resource registration, website management, and business service logic.

## 2. Target Dashboard Strategy

Tyro Dashboard should be used for:

- Authentication and dashboard shell
- User management
- Role and privilege management
- Audit trail
- Dynamic CRUD for low-risk/simple resources
- Admin navigation and global dashboard UI

Custom Laravel controllers/services should be used for:

- Stock mutation
- Purchase receive/cancel workflows
- Sales confirmation/cancellation workflows
- Payment and account ledger mutation
- Report aggregation
- CSV/PDF export
- Website rendering

Rule:

Use Tyro dynamic CRUD for data entry and management where safe, but keep all stock and money calculations in Laravel services with database transactions.

## 3. Route Direction

Tyro Dashboard default prefix is:

```text
/dashboard
```

Recommended ZamZam target:

```text
/admin      Admin/dashboard control center
/           Public website
```

Implementation options:

1. Set `TYRO_DASHBOARD_PREFIX=admin` in `.env`.
2. Keep Tyro default `/dashboard` temporarily during development, then switch to `/admin` before handoff.

Recommended:

Use `/admin` from the start to match project docs and future admin expectations.

## 4. Phase 1 - Foundation Hardening

Goal: Make the Laravel 13 + Tyro base production-ready before ERP modules are added.

Status: Completed for local baseline on 2026-06-10.

Tasks:

- Set app name, timezone, locale, and URL in `.env`.
- Set `TYRO_DASHBOARD_PREFIX=admin`.
- Set `TYRO_LOGIN_REDIRECT_AFTER_LOGIN=/admin`.
- Keep Tyro Login registration enabled when the login page should show the default register link:
  - `TYRO_LOGIN_REGISTRATION_ENABLED=true`
- Seed default admin user.
- Seed core roles:
  - `super_admin`
  - `manager`
  - `sales_staff`
  - `inventory_staff`
  - `accountant`
- Map Tyro admin roles to project roles.
- Confirm `/login`, `/admin`, and `/` work.
- Run `php artisan test`.
- Run `npm run build`.

Deliverables:

- Updated `.env.example`
- Seeder for admin/roles
- Confirmed Tyro dashboard access

Completed:

- `APP_NAME` changed to `ZamZam ERP` in local/example environment.
- Tyro Dashboard prefix set to `/admin`.
- Tyro Login redirect after login set to `/admin`.
- Public registration enabled so the Tyro Login page shows the default register link.
- Tyro core migrations published into `database/migrations`.
- Default ZamZam roles and privileges seeded.
- Default admin seeded:
  - Email: `admin@zamzamint.com`
  - Password: `password`
- Verification passed:
  - `php artisan route:list`
  - `php artisan db:seed`
  - `php artisan test`
  - `npm run build`

## 5. Phase 2 - Website Management

Goal: Public website and admin-managed website content in the same Laravel app.

Status: Completed for Phase 2 website CMS scope on 2026-06-10.

Initial tables:

- `site_settings`
- `site_pages`
- `site_sections`
- `site_menus`
- `site_banners`

Initial admin-managed content:

- Logo
- Site name
- Contact phone/email/address
- Social links
- Homepage hero/banner
- Featured categories
- Featured products
- About content
- Footer content
- SEO title/meta description

Public routes:

```text
GET /
GET /about
GET /contact
GET /products
GET /products/{slug}
```

Admin:

- Register safe CMS resources in `config/tyro-dashboard.php`.
- Use custom controller/view for website frontend.

Deliverables:

- Website models/migrations
- Basic public website views
- Tyro resources for website content
- Seed default site settings

Completed:

- Added website CMS tables:
  - `site_settings`
  - `site_banners`
  - `site_sections`
  - `site_pages`
  - `site_menus`
  - `contact_messages`
- Added Tyro `HasCrud` model resources:
  - `App\Models\SiteSetting`
  - `App\Models\SiteBanner`
  - `App\Models\SiteSection`
  - `App\Models\SitePage`
  - `App\Models\SiteMenu`
  - `App\Models\ContactMessage`
- Added public website controller:
  - `App\Http\Controllers\WebsiteController`
- Replaced default `/` welcome route with database-backed website homepage.
- Added public page route:
  - `/about`
  - `/contact`
  - `/products`
  - `/pages/{slug}`
- Added website Blade views:
  - `resources/views/website/layout.blade.php`
  - `resources/views/website/home.blade.php`
  - `resources/views/website/page.blade.php`
- Seeded default website settings, banner, sections, pages, and menus.
- Verification passed:
  - `php artisan migrate`
  - `php artisan db:seed`
  - `php artisan test`
  - `php artisan view:cache`
  - `npm run build`

Dashboard resource URLs:

```text
/admin/resources/site_settings
/admin/resources/site_banners
/admin/resources/site_sections
/admin/resources/site_pages
/admin/resources/site_menus
/admin/resources/contact_messages
```

Phase 2 polish completed:

- Added public contact form on `/`.
- Added `contact_messages` table and `App\Models\ContactMessage` Tyro `HasCrud` resource.
- Added editable homepage section types:
  - Featured Categories
  - Featured Products Placeholder
  - Service Block
  - CTA / Contact Block
- Added SEO polish:
  - Per-page canonical URLs
  - Meta title and description
  - Open Graph title, description, URL, type, and image
  - Twitter card tags
  - Favicon and apple-touch-icon from Website Settings
- Added media integration polish:
  - Existing uploaded images preview in Tyro edit forms for logo, favicon, OG images, banners, page OG images, and section images.
  - Recommended dimensions help text added for website media fields.
- Added header branding controls:
  - Header site name and tagline can be shown/hidden from Website Settings & SEO.
  - Header logo width and height can be customized from Website Settings & SEO.
  - Public header uses a flexible logo area instead of forcing the logo into a small square.
- Added admin sidebar override for a dedicated Website section:
  - Website Settings
  - Website Banners
  - Website Pages
  - Website Menus
  - Contact Messages
- Kept generic Resources menu from duplicating website resources.

## 6. Phase 3 - Product and Inventory Foundation

Goal: Product catalog and stock tracking.

Tables:

- `categories`
- `products`
- `stock_movements`

Product fields:

- category
- name
- slug
- SKU
- barcode
- description
- unit
- brand
- cost price
- sale price
- legacy price if needed
- stock
- reorder level
- VAT rate
- image
- status: `available`, `coming_soon`
- active/inactive

Stock movement types:

- `opening`
- `purchase`
- `sale`
- `return`
- `adjustment`

Service:

```text
App\Services\Inventory\StockService
```

Rules:

- Stock is calculated from stock movements.
- Negative stock is blocked.
- All stock updates go through `StockService`.
- Stock movements must be auditable.

Tyro usage:

- Categories: dynamic CRUD
- Products: dynamic CRUD first, custom enhancements later
- Stock Movements: preferably custom workflow or restricted CRUD

Deliverables:

- Migrations/models/factories
- StockService
- Tyro resource definitions
- Stock tests

## 7. Phase 4 - Supplier, Purchase, and China-to-BD Costing

Goal: Supplier purchase flow with fixed and custom China-to-BD purchase-level costs.

Tables:

- `suppliers`
- `purchases`
- `purchase_items`
- `supplier_payments`

Purchase statuses:

- `draft`
- `received`
- `cancelled`

Fixed China-to-BD cost fields:

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

Custom costs:

- Store as JSON initially in `purchases.custom_costs`
- Consider normalized `purchase_custom_costs` later if reporting becomes complex

Service:

```text
App\Services\Purchasing\PurchaseService
```

Rules:

- Draft purchase does not affect stock.
- Received purchase increases stock.
- Cancelled purchase does not affect stock.
- Cancelling a received purchase reverses stock only if safe.
- Fixed and custom costs are included in total and due.
- Supplier due is synced from received purchase due minus payments.

Deliverables:

- Purchase migrations/models
- PurchaseService
- Tyro resources for suppliers/purchases
- Purchase receive/cancel workflow
- Purchase tests

## 8. Phase 5 - Sales, Customers, and Invoice

Goal: Wholesale sales invoice flow.

Tables:

- `customers`
- `orders`
- `order_items`
- `customer_payments`

Order statuses:

- `draft`
- `confirmed`
- `completed`
- `cancelled`

Service:

```text
App\Services\Sales\OrderService
```

Rules:

- Draft/cancelled orders do not affect stock.
- Confirmed/completed orders reduce stock.
- Insufficient stock is blocked.
- Customer due is invoice due minus payments.
- Printable invoice route should exist.

Routes:

```text
GET /admin/orders/{order}/print
```

Deliverables:

- Sales migrations/models
- OrderService
- Invoice print view
- Customer/payment resources
- Sales tests

## 9. Phase 6 - Accounts, Expenses, and Ledger

Goal: Traceable cash/bank, payment, and expense flow.

Tables:

- `accounts`
- `expense_categories`
- `expenses`
- `transaction_ledgers`

Service:

```text
App\Services\Accounts\LedgerService
```

Rules:

- Account balance equals opening balance plus inflow minus outflow.
- Customer payment creates ledger inflow.
- Supplier payment creates ledger outflow.
- Expense creates ledger outflow.
- Overpayments are blocked.
- Negative account balance is blocked where required.
- Ledger entries should be read-only after creation, or reversed through explicit reversal.

Deliverables:

- Account/expense migrations/models
- LedgerService
- Tyro resources
- Account/payment tests

## 10. Phase 7 - Reports and Dashboard KPIs

Goal: Owner/admin can understand business health from one dashboard.

Service:

```text
App\Services\Reports\ReportService
```

Reports:

- Sales
- Purchases
- Profit
- Stock
- Low stock
- Customer dues
- Supplier dues
- Expenses
- Ledger

Dashboard KPIs:

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

Export:

```text
GET /admin/reports/export/{type}
```

Rules:

- Reports must respect role permissions.
- Purchase report includes fixed China-to-BD costs.
- Purchase report includes dynamic custom cost columns.

Deliverables:

- ReportService
- Report routes/controllers/views
- CSV export
- Report tests

## 11. Phase 8 - Permissions and Audit

Goal: Secure ERP actions and trace sensitive changes.

Roles:

- `super_admin`
- `manager`
- `sales_staff`
- `inventory_staff`
- `accountant`

Permission groups:

- inventory
- purchasing
- sales
- accounts
- reports
- website
- users
- audit

Rules:

- Super Admin has full access.
- Manager can work with most ERP modules.
- Sales Staff can work with sales and customers.
- Inventory Staff can work with inventory and view purchasing.
- Accountant can work with accounts and reports.
- Report export must be protected.
- Audit logs are Super Admin only.

Deliverables:

- Role/privilege seeder
- Policies or Tyro privilege mapping
- Audit configuration
- Permission tests

## 12. Phase 9 - Production Readiness

Goal: Deployable, backup-ready, and maintainable app.

Tasks:

- Run `php artisan test`.
- Run `npm run build`.
- Add production `.env.example` notes.
- Add Coolify deployment notes.
- Add storage persistence notes.
- Add backup/restore plan.
- Add admin password change checklist.
- Disable example/demo routes in production:
  - `TYRO_DASHBOARD_DISABLE_EXAMPLES=true`

Deliverables:

- Deployment checklist
- Backup checklist
- Production smoke test checklist

## 13. Immediate Next Action

Start with Phase 1.

Recommended first implementation tasks:

1. Update `.env.example` for Tyro admin route and ZamZam branding.
2. Add an admin/role seeder.
3. Confirm Tyro migrations and package migrations are runnable.
4. Run tests/build.
5. Then implement website settings and public homepage management.

## 14. Verification Commands

From `my-erp-app/`:

```bash
php artisan about
php artisan route:list
php artisan migrate
php artisan db:seed
php artisan test
npm run build
```

Development server:

```bash
composer run dev
```

Expected URLs:

```text
http://localhost:8001
http://localhost:8001/login
http://localhost:8001/admin
```
