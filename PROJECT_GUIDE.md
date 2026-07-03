# ZamZam ERP Dashboard - Project Guide

এই গাইডটি নতুন developer, maintainer, অথবা AI agent-কে project-এর বর্তমান অবস্থা দ্রুত বুঝতে সাহায্য করার জন্য। কাজ শুরু করার আগে এই ফাইল এবং `ERP_PHASE_ROADMAP.md` পড়ুন।

## 1. Project Overview

- Project type: China to Bangladesh wholesale ERP management app
- Backend framework: Laravel 12
- Admin panel: Filament 4
- Frontend build tool: Vite
- Styling stack: Tailwind CSS 4
- Admin route: `/admin`
- Public route: `/`
- Main business focus: product inventory, purchase costing, sales invoice, supplier/customer due, accounts, reports, and audit trail
- Architecture: single application and database with company-wise business data isolation
- Delivery support: company-specific manual/custom courier and Steadfast API integration

## 1.1 Current Platform Foundations

### Multi-Company System

The application now supports multiple companies inside one Laravel installation and one database.

Core companies:

- Garments Machinery Company (`GM`)
- Solar Items Company (`SOL`)
- Gadget Items Company (`GAD`)
- Gift Items Company (`GFT`)

Important behavior:

- `companies` stores company profile, branding, currency, timezone, invoice prefix, active state, and JSON settings.
- `company_user` assigns users to companies with a company-specific role and default-company flag.
- Super Admin can select a company or `All Companies` from the Filament top-bar switcher.
- Staff can only select companies assigned to them.
- The selected company is stored in session key `current_company_id`.
- `SetCurrentCompany` resolves the session selection and initializes `CompanyContext` for each admin request.
- `BelongsToCompany` automatically assigns `company_id` to new business records and applies `CompanyScope` to queries.
- Core business records require `company_id`; existing records were backfilled to `Main Company` during migration.
- Company-scoped models include inventory, sales, purchasing, accounts, expenses, ledger, audit, and courier models.
- Company invoice numbers use the selected company's prefix, date, and daily sequence, for example `GAD-20260623-0001`.
- Dashboard summaries, reports, and widgets follow the active company context.
- User create/edit screens support assigned companies and a default company.
- Company-specific profile and branding are resolved through `CompanySettingsService`.
- Cross-company courier selection and booking are rejected at the service layer.
- While `All Companies` is selected, Super Admin can create a courier provider only by explicitly selecting its owner company; order booking actions still require a specific active company context.

Important files:

```text
app/Models/Company.php
app/Models/Concerns/BelongsToCompany.php
app/Scopes/CompanyScope.php
app/Services/CompanyContext.php
app/Http/Middleware/SetCurrentCompany.php
app/Http/Controllers/Admin/CompanySwitchController.php
app/Filament/Resources/Companies/
resources/views/filament/partials/company-switcher.blade.php
tests/Feature/MultiCompanyIsolationTest.php
```

Production migration note:

- The safe schema migration puts historical records in `Main Company` first.
- Moving real historical records from `Main Company` into Garments, Solar, Gadget, or Gift must be done with verified business mapping and backups.
- Never guess the destination company for existing production records.
- Use `php artisan companies:migrate-data {company-slug} {mapping.json} --dry-run` to validate an explicit mapping. A real run creates a database backup automatically; `--no-backup` is rejected in production.
- `docs/company-data-migration.example.json` documents the accepted aggregate mapping keys. Child purchase/order/stock/payment records move transactionally with their selected parent.
- The isolation contract test covers every current company-owned model, including courier, shipment, and container records.
- Current business decision: no bulk legacy reassignment is planned because almost all records will be entered fresh under the correct company. Any small number of historical exceptions should be reviewed and moved manually; do not run the bulk migration command without a new explicit decision.

### Courier and Delivery Integration

Courier data is company-specific. A company can have its own Custom/manual partners and its own encrypted API credentials.

Supported provider choices:

- Custom/manual
- Steadfast
- Pathao configuration placeholder
- RedX configuration placeholder
- E-Courier configuration placeholder

Implemented behavior:

- Manual/custom courier booking from Order list and Order detail.
- Active Custom provider selection during manual booking.
- Automatic manual tracking ID generation when none is supplied.
- Steadfast order creation through `https://portal.packzy.com/api/v1/create_order`.
- Steadfast status sync by tracking code or invoice.
- Steadfast consignment ID and tracking code storage.
- Steadfast API key and secret key are stored in the encrypted `credentials` model cast.
- Provider settings support contact person, phone, warehouse, delivery fees, courier costs, return costs, COD percentage, and base URL.
- Delivery status is independent from the sales Order status.
- Normalized delivery statuses are `not_booked`, `booking_pending`, `booked`, `picked_up`, `in_transit`, `delivered`, `partial_delivered`, `returned`, `cancelled`, and `failed`.
- Every manual or synchronized status change creates a courier status log.
- Orders expose booking, Steadfast booking, delivered, returned, and status information actions.
- Courier booking detail includes provider, invoice, recipient, COD amount, tracking data, and status history.
- Manual and Steadfast booking services verify that Order and Courier Provider belong to the same company.
- `CourierManager` and `CourierProviderInterface` provide the provider adapter boundary; Manual and Steadfast use concrete adapters.
- Pathao, RedX, and E-Courier resolve through explicit pending live adapters. They intentionally reject booking, sync, balance, and webhook operations with a clear setup message until official merchant API credentials, request field mapping, and sandbox/live response samples are available.
- API calls use bounded timeouts and retry/backoff.
- Signed incoming webhooks are deduplicated, logged, queued, retried, and processed inside the provider's explicit company context.
- Courier Status Log and Webhook Log resources provide operational diagnostics.
- Booking actions support cancellation and configurable tracking/label URL templates.
- `CourierReportService` exposes provider/company delivery, return, cancellation, success-rate, and COD aggregates.

Not implemented yet:

- Live Pathao, RedX, and E-Courier API clients beyond the pending adapter guardrail
- Provider-native remote cancellation/label endpoints where an official API contract is required; current actions use normalized cancellation and configurable label URLs.

Important files:

```text
app/Models/CourierProvider.php
app/Models/CourierBooking.php
app/Models/CourierStatusLog.php
app/Models/CourierWebhookLog.php
app/Services/CourierService.php
app/Services/CourierManager.php
app/Contracts/CourierProviderInterface.php
app/Services/Couriers/
app/Services/Couriers/PendingLiveCourierAdapter.php
app/Services/Couriers/PathaoCourierAdapter.php
app/Services/Couriers/RedxCourierAdapter.php
app/Services/Couriers/ECourierAdapter.php
app/Services/SteadfastCourierClient.php
app/Services/CourierReportService.php
app/Filament/Resources/CourierProviders/
app/Filament/Resources/CourierBookings/
app/Filament/Resources/CourierStatusLogs/
app/Filament/Resources/CourierWebhookLogs/
tests/Feature/CourierIntegrationTest.php
```

### Customer Success and Risk Score

- The module uses explainable rules rather than machine learning. Every deduction is stored as a named factor.
- Company-level profiles track courier totals plus delivered, returned, and cancelled ratios by customer phone.
- Scores map to Low (`80-100`), Medium (`50-79`), and High (`0-49`) risk; an active global/company blacklist produces the separate Blacklisted level.
- Checks run when an Order becomes confirmed/completed and again immediately before courier booking.
- Global or company blacklist matches block courier booking pending owner review.
- Terminal courier status changes create idempotent customer risk events and refresh the profile.
- Risk badges appear in Customer and Order lists/details; booking forms show the current score before submission.
- Super Admin manages global/company blacklist entries under the `Customer Success` navigation group.
- High-risk orders create manager approval requests before courier booking can continue.
- Blacklisted matches create owner approval requests before courier booking can continue.
- Risk review, risk event, and rule settings screens live under the `Customer Success` navigation group.
- The dashboard shows Customer Success & Risk stats plus an alert table for high-risk and blacklisted profiles.
- Super Admin can tune risk thresholds and deduction weights without changing code.

Important files:

```text
app/Services/CustomerRiskService.php
app/Services/CustomerRiskSettingsService.php
app/Models/CustomerRiskProfile.php
app/Models/CustomerRiskEvent.php
app/Models/CustomerBlacklist.php
app/Models/CustomerRiskReview.php
app/Models/FraudCheck.php
app/Filament/Resources/CustomerRiskProfiles/
app/Filament/Resources/CustomerBlacklists/
app/Filament/Resources/CustomerRiskReviews/
app/Filament/Resources/CustomerRiskEvents/
app/Filament/Pages/CustomerRiskSettings.php
tests/Feature/CustomerRiskTest.php
```

### Shipment and Container Tracking

- Company-scoped containers track container number, shipping line, route, lifecycle status, and estimated/actual departure and arrival dates.
- Company-scoped shipments link an optional Purchase and Container and track carrier, transport mode, tracking number, status, shipped/ETA/received dates, and notes.
- Shipment validation rejects a Purchase or Container from another company.
- Shipment and container tracking is embedded inside each Purchase View/Edit page; the standalone Shipment and Container resources are intentionally hidden from sidebar navigation.
- Draft purchases allow shipment planning and inline container creation. Received purchases show read-only logistics history. Cancelled purchases show existing logistics history only when records exist.

### Release and Update Safety

- Application release metadata is centralized in `AppRelease` and `config/release.php`.
- The admin panel includes a Release Notes page.
- `CHANGELOG.md` records notable production changes.
- Production deployment documentation requires a database backup before migrations.
- Routine production updates must not run broad seeders, `migrate:fresh`, or other destructive commands.
- Release types include major, minor, patch, security, hotfix, and maintenance updates.

Important files:

```text
app/Support/AppRelease.php
app/Filament/Pages/ReleaseNotes.php
resources/views/filament/pages/release-notes.blade.php
config/release.php
CHANGELOG.md
docs/release-policy.md
docs/update-safety.md
tests/Feature/ReleaseNotesTest.php
```

### Storefront Foundation

The project includes a native Laravel Blade storefront foundation. Do not install Lunar or create a duplicate ecommerce model layer. Storefront work must reuse the existing ERP `Company`, `Product`, `Category`, `Customer`, `Order`, stock, risk, and courier flows.

Current behavior:

- Public storefront routing is custom-domain aware through `ResolveCompanyFromDomain`.
- Company domains live on `companies.domain`; verification state lives on `companies.domain_verified`.
- Storefront publishing and brand settings live in `storefront_settings`.
- The Filament admin resource is `Storefront Settings` under the `Storefront` navigation group.
- `Storefront Settings` also acts as the admin launch-readiness dashboard using Filament default table columns/actions: readiness score, missing setup checklist, domain verification, visible product count, published page count, Preview, Open Site, and Pages shortcuts.
- Storefront domain and domain verification are edited from the Storefront Settings form even though the canonical fields live on `companies.domain` and `companies.domain_verified`; create/edit pages synchronize those company fields on save so the list dashboard and edit form do not drift. Duplicate domains assigned to another company are rejected with a form validation error before the database unique constraint can throw a 500.
- Storefront content pages live in `storefront_pages`.
- The Filament admin resource is `Storefront Pages` under the `Storefront` navigation group for About, Return Policy, Privacy Policy, Terms, and similar public pages.
- Storefront Settings list/edit pages include `Manage Pages` shortcuts so policy/content pages can be opened directly from settings.
- Product storefront URLs use company-scoped product slugs.
- Orders have a `source` value so future checkout orders can be identified as `storefront`.
- Production storefront URLs are domain based, for example `/products`, `/category/{slug}`, and `/product/{slug}` on the mapped company domain.
- Local development preview URLs are available without editing the hosts file:
  - `/storefront`
  - `/storefront/{company-slug}`
  - `/storefront/{company-slug}/products`
  - `/storefront/{company-slug}/category/{slug}`
  - `/storefront/{company-slug}/product/{slug}`
  - `/storefront/{company-slug}/cart`
- The demo seeder creates a published Main Company storefront setting so `/storefront` shows products immediately after `php artisan demo:refresh`.
- Storefront cart is session based and company scoped. Cart keys are isolated by company ID, so items added on one storefront domain/company do not appear in another company cart.
- Cart supports add, update quantity, remove item, stock capping, empty-cart state, and subtotal summary.
- Cart routes on production custom domains:
  - `GET /cart`
  - `POST /cart/items/{product-slug}`
  - `PATCH /cart/items/{product-slug}`
  - `DELETE /cart/items/{product-slug}`
- Cart routes on local preview:
  - `GET /storefront/{company-slug}/cart`
  - `POST /storefront/{company-slug}/cart/items/{product-slug}`
  - `PATCH /storefront/{company-slug}/cart/items/{product-slug}`
  - `DELETE /storefront/{company-slug}/cart/items/{product-slug}`
- Checkout is now implemented for storefront carts. Customers submit name, phone, optional email, delivery address, and note.
- Checkout reuses or updates an existing company-scoped Customer by phone; new storefront customers use `customer_source = website`.
- Checkout creates existing ERP `Order` and `OrderItem` records with `source = storefront`.
- Storefront orders are created as `draft` by design. Stock is not deducted until an admin reviews and confirms/completes the order through the ERP order workflow.
- Draft storefront orders do not increase `Today Sales`; the dashboard shows them separately as `Storefront Pending` with pending order count and amount.
- The Orders table and Order detail page display the order `Source` badge, and the Orders table can be filtered by `Admin` or `Storefront` source.
- Checkout validates current cart stock and clears the cart after successful order creation.
- Checkout success pages show the generated ERP order number and order summary.
- Production checkout routes on custom domains:
  - `GET /checkout`
  - `POST /checkout`
  - `GET /checkout/success/{order}`
- Storefront order tracking lets customers search by ERP order number and view order status, the current delivery status only after an admin/courier update, latest courier/provider/tracking ID, totals, due amount, and ordered items. Default `Not Booked` delivery status stays hidden on the customer-facing page.
- Admin/courier status changes appear as chronological `Tracking Updates` rather than a fixed list of possible statuses. Order status and delivery status updates come from order audit logs; courier updates come from courier status logs.
- Tracking update markers and the `Latest` badge are styled in `resources/views/storefront/track/show.blade.php`; markers use 100px rounded corners and the latest badge uses compact 10px horizontal padding.
- Tracking only exposes orders from the current storefront company and only when `source = storefront`; admin orders and other-company orders return 404.
- Customer order history is available from the storefront header `Account` link and at `/account/orders`; customers search by checkout phone number and see only current-company storefront orders with links into live tracking. Admin-created orders and other-company orders are hidden.
- Published storefront pages are available from footer links and at `/pages/{slug}`. Unpublished pages and other-company pages return 404.
- In local/testing, `/pages/{slug}` falls back to the first published storefront company so admins can preview content pages on `127.0.0.1`; local company-scoped preview still works at `/storefront/{company-slug}/pages/{slug}`.
- Admin Order forms use two explicit labels: `Order Status` for invoice/stock/accounts/reporting workflow and `Delivery Status` for storefront tracking/courier progress; courier booking actions may update delivery status automatically.
- Production tracking routes on custom domains:
  - `GET /track`
  - `GET /track/{orderNo}`
- Production customer account routes on custom domains:
  - `GET /account/orders`
- Production storefront content routes on custom domains:
  - `GET /pages/{slug}`
- Local preview checkout routes:
  - `GET /storefront/{company-slug}/checkout`
  - `POST /storefront/{company-slug}/checkout`
  - `GET /storefront/{company-slug}/checkout/success/{order}`
- Local preview tracking routes:
  - `GET /storefront/{company-slug}/track`
  - `GET /storefront/{company-slug}/track/{orderNo}`
- Local preview customer account routes:
  - `GET /storefront/{company-slug}/account/orders`
- Local preview storefront content routes:
  - `GET /storefront/{company-slug}/pages/{slug}`
- Storefront UI should use Tailwind CSS 4 via Vite and should aim for a polished Shopify-style ecommerce look: image-first product cards, clean collection tiles, strong CTA buttons, responsive mobile layout, and dark/light compatibility.
- Storefront layout includes SEO/Open Graph/Twitter metadata from storefront settings, compact mobile-safe header actions, footer WhatsApp contact, banner-image hero support, and explicit out-of-stock product states.
- Dark mode is a real class-based toggle, not just `prefers-color-scheme`. `resources/css/app.css` declares `@custom-variant dark (&:where(.dark, .dark *));` (storefront only; Filament's own dark mode is unaffected). `resources/views/storefront/layout.blade.php` sets the `dark` class before paint from `localStorage.storefrontTheme`, falling back to the company's `storefront_settings.theme_mode` (`system`/`light`/`dark`) on first visit, and exposes a header sun/moon button (`[data-theme-toggle]`) that flips the class and persists the choice. No Alpine.js is loaded in the storefront bundle, so the toggle and the quantity stepper (`[data-qty-stepper]`/`[data-qty-input]`/`[data-qty-increment]`/`[data-qty-decrement]`) are plain vanilla JS in `layout.blade.php`, not Alpine directives.
- Product listing supports `?sort=price_asc|price_desc` (default newest) and category quick-filter chips, handled in `StorefrontProductIndexController` and `PreviewController::products`.
- Product detail pages show a breadcrumb, a sticky buy box with a quantity stepper, and a "You may also like" related-products rail (same category, excludes current product, limit 4), sourced from `StorefrontProductShowController` and `PreviewController::product`.
- Homepage hero heading/subheading/CTA label are admin-editable via `storefront_settings.hero_heading`, `hero_subheading`, `hero_cta_label` (all nullable; blank falls back to the default "Shop the latest from {company}." copy in `home.blade.php`).
- Public storefront copy should not expose implementation details such as unfinished roadmap steps; customer-facing text should describe direct ordering, review, confirmation, and tracking.
- Avoid broad inline CSS for storefront pages unless it is a small dynamic CSS variable such as company theme color.
- Whenever storefront UI/routes/settings/cart/checkout are changed, update this guide with the affected files and verification steps.

Important files:

```text
app/Http/Middleware/ResolveCompanyFromDomain.php
app/Http/Controllers/Storefront/
app/Http/Controllers/Storefront/AccountOrdersController.php
app/Http/Controllers/Storefront/OrderTrackController.php
app/Http/Controllers/Storefront/PageController.php
app/Services/StorefrontCart.php
app/Models/StorefrontPage.php
app/Models/StorefrontSetting.php
app/Filament/Resources/StorefrontPages/
app/Filament/Resources/StorefrontSettings/
app/Filament/Resources/StorefrontSettings/Pages/CreateStorefrontSetting.php
app/Filament/Resources/StorefrontSettings/Pages/EditStorefrontSetting.php
database/migrations/2026_06_25_000000_add_storefront_foundation_fields.php
database/migrations/2026_06_25_001000_create_storefront_settings_table.php
database/migrations/2026_06_28_001000_create_storefront_pages_table.php
database/migrations/2026_07_03_000000_add_hero_and_theme_fields_to_storefront_settings_table.php
database/seeders/DemoDataSeeder.php
routes/web.php
resources/views/storefront/
resources/views/storefront/cart/show.blade.php
resources/views/storefront/checkout/
resources/views/storefront/track/show.blade.php
resources/views/storefront/layout.blade.php
resources/views/storefront/home.blade.php
resources/views/storefront/partials/product-card.blade.php
resources/views/storefront/products/index.blade.php
resources/views/storefront/products/show.blade.php
resources/views/storefront/cart/show.blade.php
resources/views/storefront/checkout/show.blade.php
app/Http/Controllers/Storefront/ProductIndexController.php
app/Http/Controllers/Storefront/ProductShowController.php
app/Http/Controllers/Storefront/PreviewController.php
resources/css/app.css
tests/Feature/StorefrontFoundationTest.php
```

Verification:

```bash
php artisan demo:refresh
php artisan test --filter=StorefrontFoundationTest
php artisan test --filter=PhaseFourAdminPagesTest
npm run build
```

Optional local HTTP smoke check after `php artisan serve --host=127.0.0.1 --port=8000`:

```bash
GET /storefront
GET /storefront/{company-slug}/products
GET /storefront/{company-slug}/cart
GET /storefront/{company-slug}/track
GET /storefront/{company-slug}/account/orders
```

Test note:

- `StorefrontFoundationTest` disables Vite and Laravel's `ValidateCsrfToken` middleware inside the test case so CI can verify storefront routing, rendering, carts, checkout, tracking, and public pages without requiring a prebuilt Vite manifest or browser-generated CSRF tokens.

## 2. Important Folders

```text
app/Models/                         Eloquent models
app/Services/                       Business/report services
app/Filament/Resources/             Filament admin resources
app/Filament/Pages/                 Custom Filament pages
app/Filament/Widgets/               Dashboard widgets
app/Providers/Filament/             Admin panel setup

database/migrations/                Database schema changes
database/seeders/                   Seed data
database/factories/                 Model factories

routes/web.php                      Web routes and CSV exports
resources/views/                    Blade views
resources/css/                      App CSS
resources/js/                       App JavaScript
public/                             Public assets and built files
storage/                            Runtime storage, logs, uploads
```

## 3. Current Core Modules

### Inventory

- Categories
- Products
- Stock Movements

Products support:

- name, description, SKU, barcode, unit, brand
- cost price, sale price, legacy price
- stock, reorder level, VAT rate
- active/inactive status
- product status: `available`, `coming_soon`
- image upload
- category relationship

Stock behavior:

- Stock is calculated from stock movements.
- Opening, purchase, and return movements increase stock.
- Sale movements reduce stock.
- Adjustment movements use signed quantity.
- Movements that would make product stock negative are blocked.
- Product view includes stock movement history.

### Purchasing

- Suppliers
- Purchases
- Purchase Items
- Supplier Payments

Purchase behavior:

- Purchases have statuses: `draft`, `received`, `cancelled`.
- Stock increases only when purchase status is `received`.
- Draft and cancelled purchases do not affect product stock.
- Cancelling a received purchase removes related purchase stock movements.
- Cancelling is blocked if stock would become negative.
- Purchase can optionally update product cost price.
- Supplier balance is synced from received purchase due minus supplier payments.

### China to BD Purchase Costing

The app includes dedicated China-to-Bangladesh wholesale purchase cost fields on the Purchase form.

Fixed optional purchase cost fields:

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

Important behavior:

- These fields are optional.
- They are purchase-level costs, not product dropdown items.
- They are included in `total_amount` and `due_amount`.
- They are stored directly on the `purchases` table.
- The `China to BD Costs` section is collapsible.

Custom purchase cost fields:

- The `China to BD Costs` section has an `Add new field` button.
- Clicking the button opens a modal/popup.
- The popup form accepts `Field Name` and `Amount`.
- Custom fields are stored in `purchases.custom_costs` as JSON.
- Custom costs are included in purchase total and due calculations.
- The `Custom Fields` block stays hidden until at least one custom field exists.
- Custom fields show on View Purchase.
- Purchase reports and CSV exports dynamically add custom cost columns based on labels used in the selected report date range.

Related files:

```text
app/Models/Purchase.php
app/Filament/Resources/Purchases/Schemas/PurchaseForm.php
app/Filament/Resources/Purchases/Schemas/PurchaseInfolist.php
app/Filament/Resources/Purchases/Tables/PurchasesTable.php
database/migrations/2026_06_07_010000_add_china_to_bd_costs_to_purchases_table.php
database/migrations/2026_06_07_020000_add_custom_costs_to_purchases_table.php
```

### Coming Soon Products

The app has placeholder products for future China-to-BD purchase-related items.

Placeholder product names:

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

Important behavior:

- They are created/ensured by `Product::ensureComingSoonPurchaseProducts()`.
- They use product status `coming_soon`.
- They are inactive so they do not appear in active product purchase dropdowns.
- Product page can show them with Coming Soon status.
- Dashboard can count Coming Soon products.

Related files:

```text
app/Models/Product.php
database/migrations/2026_06_07_000000_add_status_to_products_table.php
database/seeders/DatabaseSeeder.php
app/Filament/Resources/Products/
app/Filament/Widgets/BusinessOverview.php
```

### Sales

- Customers
- Orders
- Order Items
- Printable invoice page
- Customer Payments

Sales behavior:

- Orders are multi-product sales invoices.
- Order items store product, quantity, unit price, and subtotal.
- Order totals are calculated from items, discount, VAT, and paid amount.
- Confirmed/completed orders create grouped sale stock movements.
- Draft/cancelled orders do not affect stock.
- Customer current balance is opening balance plus confirmed/completed invoice due minus customer payments.
- Printable invoice route: `/admin/orders/{order}/print`
- Printable invoices hide zero-value discount, VAT, paid, and advance-style paid rows; paid amounts display as a negative deduction when greater than zero.
- Verify printable invoice behavior with `php artisan test --filter=CompanySettingsTest`.

### Accounts and Ledger

- Accounts
- Customer Payments
- Supplier Payments
- Expense Categories
- Expenses
- Transaction Ledger

Money behavior:

- Account balance = opening balance + ledger inflow - ledger outflow.
- Customer payments create ledger entries with direction `in`.
- Supplier payments create ledger entries with direction `out`.
- Expenses create ledger entries with direction `out`.
- Overpayments are blocked.
- Supplier payments and expenses are blocked if account balance would become negative.
- Transaction Ledger is intended as read-only history.

### Reports and Dashboard

Dashboard widget:

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

Reports page:

```text
app/Filament/Pages/Reports.php
resources/views/filament/pages/reports.blade.php
app/Services/ReportService.php
```

Available report/export types:

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

- Shows China to BD cost total.
- Dynamically shows custom cost field labels as columns.
- CSV export includes fixed China-to-BD cost columns.
- CSV export includes dynamic custom cost columns.

CSV export route:

```text
GET /admin/reports/export/{type}
```

### Users, Roles, Permissions, Audit

Roles:

- `super_admin`
- `manager`
- `sales_staff`
- `inventory_staff`
- `accountant`

Permission behavior:

- Super Admin has full access.
- Manager can work with sales, purchasing, inventory, accounts, and reports.
- Sales Staff can work with sales, view inventory, and view reports but cannot export reports.
- Inventory Staff can work with inventory, view purchasing, and view reports.
- Accountant can work with accounts, view sales/purchasing, and export reports.
- Inactive users cannot access the admin panel.
- User and Audit Log resources are restricted to Super Admin.

Audit behavior:

- Core model create/update/delete events create audit log entries.
- Audit logs store user, action, model type, model id, changed values, IP address, and user agent.
- Sensitive user fields are not stored in audit payloads.

## 4. Admin Panel

Filament admin panel is configured in:

```text
app/Providers/Filament/AdminPanelProvider.php
```

Panel settings:

- Panel ID: `admin`
- Path: `/admin`
- Login enabled
- SPA mode enabled
- Primary color: Amber
- Sidebar collapsible on desktop
- Resources auto-discovered from `app/Filament/Resources`

## 5. Main Resources

```text
app/Filament/Resources/Categories/
app/Filament/Resources/Products/
app/Filament/Resources/StockMovements/
app/Filament/Resources/Suppliers/
app/Filament/Resources/Purchases/
app/Filament/Resources/Customers/
app/Filament/Resources/Orders/
app/Filament/Resources/CustomerPayments/
app/Filament/Resources/SupplierPayments/
app/Filament/Resources/Accounts/
app/Filament/Resources/ExpenseCategories/
app/Filament/Resources/Expenses/
app/Filament/Resources/TransactionLedgers/
app/Filament/Resources/Users/
app/Filament/Resources/AuditLogs/
app/Filament/Resources/Companies/
app/Filament/Resources/CourierProviders/
app/Filament/Resources/CourierBookings/
```

## 6. Important Migrations

Inventory and products:

```text
2026_05_25_122248_create_products_table.php
2026_05_26_140736_create_categories_table.php
2026_05_26_141301_add_category_id_to_products_table.php
2026_05_28_213000_add_inventory_details_to_products_table.php
2026_05_29_000000_create_stock_movements_table.php
2026_05_29_010000_backfill_opening_stock_movements.php
2026_06_07_000000_add_status_to_products_table.php
```

Purchasing:

```text
2026_05_29_020000_create_suppliers_table.php
2026_05_29_021000_create_purchases_table.php
2026_05_29_022000_create_purchase_items_table.php
2026_06_07_010000_add_china_to_bd_costs_to_purchases_table.php
2026_06_07_020000_add_custom_costs_to_purchases_table.php
```

Sales:

```text
2026_05_25_123544_create_orders_table.php
2026_05_25_123604_create_order_items_table.php
2026_05_26_163719_add_details_to_orders_table.php
2026_05_30_000000_create_customers_table.php
2026_05_30_001000_add_invoice_fields_to_orders_table.php
2026_06_03_000000_add_profile_fields_to_customers_table.php
```

Accounts and audit:

```text
2026_06_02_010000_create_accounts_table.php
2026_06_02_011000_create_expense_categories_table.php
2026_06_02_012000_create_customer_payments_table.php
2026_06_02_013000_create_supplier_payments_table.php
2026_06_02_014000_create_expenses_table.php
2026_06_02_015000_create_transaction_ledgers_table.php
2026_06_03_010000_add_role_fields_to_users_table.php
2026_06_03_011000_create_audit_logs_table.php
```

Multi-company and courier:

```text
2026_06_22_000000_create_companies_table.php
2026_06_22_001000_add_company_id_to_core_business_tables.php
2026_06_22_002000_require_company_id_on_core_business_tables.php
2026_06_22_003000_create_courier_tables_and_delivery_status.php
2026_06_22_004000_add_provider_reference_to_courier_bookings.php
```

## 7. Local Setup

From project root:

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

Seeded admin account:

```text
Email: value of ADMIN_EMAIL, for example admin@example.com
Password: value of ADMIN_PASSWORD
```

`ADMIN_PASSWORD` is required before running `php artisan db:seed` and must be a strong password. Do not publish real admin credentials in documentation or commits.

## 8. Coolify Deployment with GitHub

Recommended deployment flow:

1. Push the Laravel project to GitHub.
2. In Coolify, create a new Application from GitHub App.
3. Select the repository and branch.
4. Use build pack: `Nixpacks`.
5. Expose port: `80`.
6. If the repository root contains `zamzam-erp-v12`, set Base Directory to `zamzam-erp-v12`.
7. Add MySQL or MariaDB resource in Coolify.
8. Configure Laravel environment variables.

Required production environment variables:

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
MAIL_FROM_ADDRESS=admin@example.com
```

For larger MySQL/Redis production deployments, `SESSION_DRIVER`, `CACHE_STORE`, and `QUEUE_CONNECTION` can be moved to `database` or `redis`. For SQLite or small single-server installs, prefer `file` sessions/cache and `sync` queue to avoid database write contention.

Generate `APP_KEY` locally:

```bash
php artisan key:generate --show
```

Post-deployment command:

```bash
php artisan migrate --force && php artisan storage:link && php artisan optimize:clear && php artisan config:cache && php artisan route:cache && php artisan view:cache
```

Persistent storage recommendation:

```text
/app/storage
```

At minimum, persist:

```text
/app/storage/app/public
```

## 9. Development Workflow

### Add a new module

1. Create migration.
2. Create Eloquent model.
3. Add relationships.
4. Create Filament resource.
5. Configure form, table, infolist, and pages.
6. Add business logic in model/service if needed.
7. Add tests.
8. Run migrations and tests.
9. Update `PROJECT_GUIDE.md` and `ERP_PHASE_ROADMAP.md`.

### Add a new field

1. Create a migration.
2. Add field to model `$fillable`.
3. Add cast if needed.
4. Add form field.
5. Add table/infolist/report/export display if relevant.
6. Update calculations if the field affects money or stock.
7. Add or update tests.
8. Update documentation.

### Add purchase cost behavior

1. Decide if the field is fixed or custom.
2. Fixed fields belong in `Purchase::CHINA_TO_BD_COST_FIELDS` and a migration column.
3. Custom fields belong in `custom_costs` JSON.
4. Update `Purchase::chinaToBdCostTotal()` if the cost affects totals.
5. Ensure reports and CSV exports expose the field where relevant.
6. Add tests in `PurchaseTest` and `ReportsTest`.

## 10. Testing Checklist

Before handoff:

```bash
php artisan test
npm run build
```

Focused test commands:

```bash
php artisan test --filter=StockMovementTest
php artisan test --filter=PurchaseTest
php artisan test --filter=SalesOrderTest
php artisan test --filter=AccountsAndPaymentsTest
php artisan test --filter=ReportsTest
php artisan test --filter=PhaseSixPermissionsTest
php artisan test --filter=MultiCompanyIsolationTest
php artisan test --filter=CourierIntegrationTest
php artisan test --filter=ReleaseNotesTest
```

Manual admin smoke checks:

1. Login to `/admin`.
2. Create category and product.
3. Create stock movements and confirm stock changes.
4. Create supplier and purchase.
5. Add China-to-BD costs and custom cost fields.
6. Confirm purchase total and due calculations.
7. Mark purchase received and confirm stock increases.
8. Create customer and sales invoice.
9. Confirm stock decreases for confirmed/completed sales.
10. Add customer/supplier payments.
11. Add expense and confirm account balance changes.
12. Check dashboard metrics.
13. Check purchase report and CSV export dynamic custom cost columns.
14. Check user permissions and audit logs.
15. Switch between assigned companies and confirm lists, reports, widgets, and invoice prefixes change correctly.
16. Confirm staff cannot select or access an unassigned company.
17. Create separate courier providers for two companies and confirm they never appear across company contexts.
18. Create a Custom courier booking and update it to delivered/returned.
19. Configure a Steadfast test provider and verify booking/status sync with safe non-production credentials.
20. Select `All Companies` and confirm courier provider creation and booking actions are unavailable.
21. Create Pathao, RedX, and E-Courier providers and confirm live booking fails with the explicit official-API setup message until credentials and field mappings are supplied.

## 11. Known Notes and Cleanup

- Some historical migrations are no-op/compatibility migrations. Keep them unless a fresh migration squash is intentionally planned.
- Product `price` is kept for legacy compatibility; `sale_price` is preferred for current UI.
- Coming Soon placeholder products are inactive to avoid appearing in active product dropdowns.
- Purchase fixed/custom costs are purchase-level costs, not product lines.
- `storage:link` is needed for public uploads.
- If deploying on Coolify, make sure migrations run after deployment.
- On HTTPS deployments behind Coolify/Traefik, set both `APP_URL` and `ASSET_URL` to the public `https://` URL and keep trusted-proxy handling enabled; otherwise Filament lazy component scripts may be blocked as mixed content.
- Run rollback tests only on disposable databases before production rollback work.
- Historical records initially belong to `Main Company`; production reassignment requires verified company mapping.
- `All Companies` is intended for owner-level reporting. Company-specific write actions must require one selected company.
- Pathao, RedX, and E-Courier currently appear as provider configuration choices and resolve through pending live adapters; their live API clients are not enabled yet.
- `courier_webhook_logs` stores signed inbound courier webhook delivery attempts for supported live adapters.
- Courier provider API credentials use an encrypted model cast; never expose them in logs, exports, or documentation.

## 12. Quick File Map

```text
Product model             app/Models/Product.php
Purchase model            app/Models/Purchase.php
Order model               app/Models/Order.php
Stock movement model      app/Models/StockMovement.php
Report service            app/Services/ReportService.php
Company model             app/Models/Company.php
Company context           app/Services/CompanyContext.php
Company scope             app/Scopes/CompanyScope.php
Courier service           app/Services/CourierService.php
Steadfast API client      app/Services/SteadfastCourierClient.php

Purchase form             app/Filament/Resources/Purchases/Schemas/PurchaseForm.php
Purchase infolist         app/Filament/Resources/Purchases/Schemas/PurchaseInfolist.php
Purchase table            app/Filament/Resources/Purchases/Tables/PurchasesTable.php
Reports page              app/Filament/Pages/Reports.php
Reports view              resources/views/filament/pages/reports.blade.php
CSV exports               routes/web.php
Dashboard widget          app/Filament/Widgets/BusinessOverview.php
Admin panel provider      app/Providers/Filament/AdminPanelProvider.php
Company switcher          resources/views/filament/partials/company-switcher.blade.php
Courier providers         app/Filament/Resources/CourierProviders/
Courier bookings          app/Filament/Resources/CourierBookings/
Release notes             app/Filament/Pages/ReleaseNotes.php
Seeder                    database/seeders/DatabaseSeeder.php
```

## 13. Documentation Rule

Every feature change should update:

- `PROJECT_GUIDE.md` for current behavior and implementation notes
- `ERP_PHASE_ROADMAP.md` for phase status, done criteria, and future work

Do not leave business-critical behavior only in code or conversation history.
