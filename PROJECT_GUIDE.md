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
- The selected company is stored in session key `current_company_id`; `current_company_selection_explicit` distinguishes a deliberate switcher choice from an automatic fallback.
- `SetCurrentCompany` resolves the session selection and initializes `CompanyContext` for each admin request. When the session has no selection, the user's active `company_user.is_default` company is selected for both staff and Super Admin; Super Admin falls back to `All Companies` only when no active default exists. An explicit `All Companies` choice remains selected. Saving a new default while editing the signed-in user immediately updates both the active session and request context, so refreshes do not revert to `All Companies`.
- `SetCurrentCompany` is pinned before `SubstituteBindings` in the middleware priority list (`bootstrap/app.php`) so route model binding is always company-scoped; do not remove this ordering — without it an implicit binding like `/admin/orders/{order}/pdf` can resolve another company's record. Regression coverage: `tests/Feature/CrossCuttingIsolationAuditTest.php`.
- Queued jobs run without a request, so any new job touching company-scoped models must set `CompanyContext` explicitly and clear it in `finally` (see `ProcessCourierWebhook`); scheduled commands must loop per company with explicit `company_id` filters (see `SendAbandonedCartReminders`).
- `BelongsToCompany` automatically assigns `company_id` to new business records and applies `CompanyScope` to queries.
- Core business records require `company_id`; existing records were backfilled to `Main Company` during migration.
- Company-scoped models include inventory, sales, purchasing, accounts, expenses, ledger, audit, and courier models.
- Company invoice numbers use the selected company's prefix, date, and daily sequence, for example `GAD-20260623-0001`.
- Dashboard summaries, reports, and widgets follow the active company context.
- User create/edit screens support assigned companies and a default company.
- Company-specific profile and branding are resolved through `CompanySettingsService`.
- **Company Management** is a native Filament cluster at `/admin/company-management`. It exposes **Companies** and **Company Settings** through Filament's top sub-navigation (desktop tabs and mobile selector) while keeping one sidebar entry. Company Settings remains visible in `All Companies` mode but shows a native select-company empty state; its form and save action are available only after one specific company is selected, so profile and invoice changes can never fall through to an arbitrary default company.
- The Company Settings Livewire page pins its mounted company ID and rejects a save if another browser tab changes the active company, preventing stale cross-company writes.
- Invoice prefix, header/footer contact details, thank-you text, image/weight/barcode/cut-slip toggles, currency, date format, and branding are maintained per company from Company Settings. Prefixes are normalized and protected by a database unique index. Both printable and downloadable PDF invoices resolve settings from the order's own company.
- Company creation must finish before its logo can be uploaded, because the immutable storage UUID does not exist until the company record is saved. Upload the logo from the edit screen after creation.
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
app/Filament/Clusters/CompanyManagement.php
app/Filament/Resources/Companies/
app/Filament/Pages/CompanySettings.php
resources/views/filament/partials/company-switcher.blade.php
tests/Feature/MultiCompanyIsolationTest.php
```

### Filament Admin Navigation Clusters

Business modules use Filament's native `Cluster` and nested `NavigationItem` patterns. Each module heading is a toggle rather than a destination: clicking it only expands or collapses the submenu, and a chevron identifies headings that contain child links. Every authorized child page remains a separate icon-bearing navigation link. The submenus behave as an accordion, so opening one module collapses the others; the active module is restored open after navigation. Main menu spacing is compact, with a small consistent gap between neighboring items. On desktop the sidebar rests in Filament's icon-only collapsed state, expands on hover or keyboard focus, and collapses again when the pointer/focus leaves; touch/mobile navigation keeps Filament's normal drawer behavior. Child pages keep their own authorization rules, and every cluster root checks whether the user can access at least one child before redirecting.

Canonical module routes and selectors:

| Module | Canonical root | Selector pages |
| --- | --- | --- |
| Site | `/admin/storefront` | Hero Slides, Settings, Pages, Homepage Carousels, Payments |
| CRM | `/admin/crm` | Leads, Quotations, Inbox, Chat Channels, AI Assistant, FAQs |
| Finance | `/admin/finance` | Vouchers, Accounts, Expenses |
| Sales | `/admin/sales` | Customers, Orders, Customer Payments |
| Purchasing | `/admin/purchasing` | Suppliers, Purchases, Supplier Payments |
| Inventory | `/admin/inventory` | Products, Categories, Stock Movement |
| Reports | `/admin/reports` | Reports |
| Settings | `/admin/settings` | Users, Product Setup, Audit Logs, Backups, Cloud Storage Settings, Release Notes |

`Site` is the admin-facing display name for the storefront module. Inventory keeps its canonical `/admin/inventory` route and exposes Products, Categories, and Stock Movement as direct submenu destinations. This is a presentation-only hierarchy: existing PHP classes, resources, models, tables, and public-domain terminology remain unchanged.

Courier, Customer Success, and Company Management follow the same cluster pattern. Hidden support resources remain hidden: Shipment and Container stay embedded in Purchases; Expense Categories and Transaction Ledgers remain routable support pages; User Roles remains reachable from Users. Fund Transfers are embedded on the Vouchers page, while the legacy Fund Source and standalone Fund Transfer resources do not register navigation or routes. Hidden pages must not create extra sidebar submenu entries.

Former top-level Filament child URLs are handled by `LegacyAdminClusterRedirectController`, which preserves nested record paths and query strings. Existing custom operational URLs such as order print/PDF, report exports, CSV import/export samples, backup downloads, and private attachment downloads remain unchanged.

Important files:

```text
app/Filament/Clusters/
app/Filament/Clusters/NavigationCluster.php
app/Http/Controllers/Admin/LegacyAdminClusterRedirectController.php
app/Providers/Filament/AdminPanelProvider.php
resources/css/filament/admin/theme.css
resources/views/filament/partials/sidebar-navigation.blade.php
routes/web.php
tests/Feature/AdminNavigationClustersTest.php
```

Reports, Release Notes, Backups, and Product Setup follow Filament's native page UI patterns:

- Reports uses Filament field wrappers and inputs for filters, native tabs and metric sections, a Filament table for every report result, and table header actions for CSV/PDF exports.
- Release Notes uses Filament sections, badges, buttons, and empty states for current-release and changelog content.
- Backups uses schema sections, header actions, a native modal form for Google Drive settings, and infolist repeatable tables for backup files.
- Product Setup uses schema sections, native form controls and actions for onboarding/license changes, and an infolist checklist.
- These four pages have no page-local CSS or custom component styling. Backups and Product Setup keep only the standard Filament page wrapper in their Blade view; their content is declared in the page schema.

Verification:

```bash
php artisan test --compact tests/Feature/AdminNavigationClustersTest.php
php artisan route:list --path=admin --except-vendor
```

Production migration note:

- The safe schema migration puts historical records in `Main Company` first.
- Moving real historical records from `Main Company` into Garments, Solar, Gadget, or Gift must be done with verified business mapping and backups.
- Never guess the destination company for existing production records.
- Use `php artisan companies:migrate-data {company-slug} {mapping.json} --dry-run` to validate an explicit mapping. A real run creates a database backup automatically; `--no-backup` is rejected in production.
- `docs/company-data-migration.example.json` documents the accepted aggregate mapping keys. Child purchase/order/stock/payment records move transactionally with their selected parent.
- The isolation contract test covers every current company-owned model, including courier, shipment, and container records.
- Current business decision: no bulk legacy reassignment is planned because almost all records will be entered fresh under the correct company. Any small number of historical exceptions should be reviewed and moved manually; do not run the bulk migration command without a new explicit decision.

### Company Media and Cloudflare R2 Storage

Cloud credentials are global infrastructure settings, while every stored business object is company-scoped. Do not create one R2 credential set per company and do not rebind Laravel's stable `public` or `local` disks at runtime.

Storage contract:

- Each company has a generated, immutable UUID `companies.storage_key`. Never derive object ownership from a mutable company name, slug, or database ID.
- Public media keys use `companies/{storage_key}/public/{area}/{filename}`. Product/category images, company/storefront logos, hero slides, page covers, imported WooCommerce images, and demo images belong here.
- Private object keys use `companies/{storage_key}/private/{area}/{filename}`. Conversation media and voucher attachments belong here and must never be exposed through `Storage::url()`.
- `r2_public` and `r2_private` are stable named cloud disks and their bucket names must be different. The public bucket is served through its configured custom domain. Every r2.dev/custom-domain public-access option must be disabled on the private bucket; the Cloud Storage form records an explicit Super Admin confirmation because S3 credentials cannot inspect Cloudflare domain exposure.
- Cloudflare R2 does not implement S3 object ACLs. Do not add Flysystem `visibility` options to an R2 write. Public/private access is enforced by separate bucket configuration and authenticated application routes.
- R2 settings are managed globally at `/admin/settings/cloud-storage-settings` and are restricted to Super Admin. The secret access key remains encrypted in `app_settings`; a blank secret field preserves the stored value.
- The Cloud Storage page has a native Filament **R2 setup guide** plus a keyboard-accessible information action beside every R2 field. These explain the exact Cloudflare dashboard path, token permission/scope, one-time secret handling, S3 endpoint format, public custom-domain setup, private-bucket exposure checks, and the save/test/enable order. Each help modal links to the relevant official Cloudflare documentation; essential instructions are inside the modal rather than relying on hover-only tooltips.
- Stage credentials and bucket names while R2 is disabled, successfully test the public bucket/custom domain and any configured private bucket, then enable uploads. A **Test** action validates and persists the current Filament form draft before probing R2, preserves an existing encrypted secret when its password field is blank, and never changes the saved enable switch. Missing test requirements are attached to the exact form fields; the example bucket/domain text is prefixed with `e.g.` so it cannot be mistaken for a saved value. Activation is rejected until the required tests pass. Verified bucket/account topology is locked to prevent an in-place switch that would strand objects; any later account/bucket rotation requires a separately planned copy-and-verify operation.
- A green public-bucket test alone does **not** switch R2 on. The Cloud Storage status explicitly distinguishes a verified-but-disabled connection from **R2 uploads active**; turn on **Enable R2 for new uploads** and save after the test passes. When it is off, new public media continues to use the stable local `public` disk.
- A Filament image selection has three stages: native FilePond pre-resizes eligible JPEG/PNG files in the browser (1600px standard media / 800px compact media, `contain`, never upscale), Livewire posts that result to local `livewire-tmp`, then the final form save performs the authoritative server-side WebP/metadata optimization and writes public media to `r2_public` when enabled. SVG, GIF, and WebP bypass browser transformation to preserve vector/animated formats. A red `data.image.… failed to upload` message therefore happens before an R2 write and should be investigated as an HTTP upload limit, signed URL/proxy, or temporary-storage permission issue.
- `config/livewire.php` pins temporary uploads to the dedicated local `livewire-tmp` disk. Keep `FILESYSTEM_DISK=local` and `LIVEWIRE_TEMPORARY_UPLOAD_DISK=livewire-tmp` in production; do not point either setting at `r2_public`, which would require a separate browser-to-R2 CORS/presigned-upload design.
- Nixpacks deployments use the repository `nginx.template.conf`, which raises Nginx to a 16 MB request body and PHP-FPM to a 12 MB file / 16 MB POST limit. Image fields intentionally accept 12 MB sources so browser pre-compression can reduce camera photos before transfer; voucher attachments retain their 10 MB limit. The `ext-gd` Composer platform dependency ensures Nixpacks installs GD for the final WebP optimization step.
- When R2 is disabled, new writes return to the stable local `public`/`local` disks. Reads continue checking configured R2 disks, so disabling cloud writes does not hide previously uploaded cloud objects.
- `CompanyStorageService` is the storage boundary for disk selection, safe path construction, ownership validation, dual-read lookup, writes, and legacy copy operations. `CompanyMedia`/`StorageUrl` are the public-media presentation helpers.
- Filament product list/view image components must use `CompanyMedia::filamentPublicUrl()`. It converts local `/storage/...` URLs to fully-qualified URLs so Filament does not mistake them for paths on its default disk, while preserving absolute CDN/R2 URLs. Verify with `php artisan test --compact tests/Feature/CompanyStorageServiceTest.php tests/Feature/WooCommerceImportTest.php`.
- `CompanyStorageService` deliberately rejects malformed, wrong-scope, and cross-company paths. Optional UI branding/media resolvers fail closed to `null` when a stale database value violates that contract, so login and `/admin/company-management/companies` remain usable without exposing another company's object. The stored value is not rewritten automatically; audit it, keep a recovery copy, and re-upload the affected logo under the owning company's generated storage UUID.
- Private downloads use authenticated routes `conversation-messages.media` and `voucher-attachments.download`. Both resolve the selected company and return `404` for cross-company records; voucher downloads also enforce voucher permissions and own/all visibility.
- `DownloadConversationMediaJob` must resolve the channel's company explicitly, write through `putPrivate()`, and clear `CompanyContext` in `finally`.
- Long-lived queue workers refresh database-backed storage settings and disk instances before each loop. Deployment should still restart workers normally after application releases.

Legacy rollout:

- Existing unscoped public paths remain readable during rollout. An unscoped private path is readable only when `legacy_private_storage_paths` maps its exact case-sensitive SHA-256 identity to one company; paths referenced by multiple companies are marked conflicted and denied for every tenant until manually resolved.
- Run `php artisan storage:migrate-company-files --company={slug} --scope=all` first. This is a dry-run and does not change files or database paths.
- The command prints its public/private destination disks. Execution refuses an unintended local destination unless `--allow-local` is explicitly supplied. For an R2 rollout, configure, test, and enable both required R2 scopes before execution.
- After reviewing the counts and taking a database backup, run `php artisan storage:migrate-company-files --company={slug} --scope=all --execute --force` in production.
- The migration copies and SHA-256 verifies each destination before updating its database path. It never deletes source objects, refuses conflicting destinations, and is safe to resume.
- Verified public R2 copies write a small local preference manifest so retained local source files no longer shadow the CDN URL after cutover; URL rendering does not perform one S3 HEAD request per storefront image.
- Keep the legacy source objects until production validation and a separately approved cleanup plan are complete.

Important files:

```text
app/Services/StorageSettingsService.php
app/Services/CompanyStorageService.php
app/Services/CompanyStorageMigrator.php
app/Support/CompanyMedia.php
app/Support/StorageUrl.php
app/Console/Commands/MigrateCompanyStorage.php
app/Http/Controllers/Admin/ConversationMediaController.php
app/Http/Controllers/Admin/VoucherAttachmentDownloadController.php
app/Models/LegacyPrivateStoragePath.php
database/migrations/2026_07_21_000000_add_storage_key_to_companies_table.php
database/migrations/2026_07_21_000100_create_legacy_private_storage_paths_table.php
database/migrations/2026_07_21_000200_add_unique_invoice_prefix_to_companies_table.php
tests/Feature/CompanyStorageServiceTest.php
tests/Feature/CompanyStorageMigrationTest.php
tests/Feature/CompanyStorageSchemaMigrationTest.php
tests/Feature/CloudStorageSettingsTest.php
tests/Feature/PrivateAttachmentStorageTest.php
```

Verification:

```bash
php artisan test --compact tests/Feature/CompanyStorageServiceTest.php tests/Feature/CompanyStorageMigrationTest.php
php artisan test --compact tests/Feature/CloudStorageSettingsTest.php tests/Feature/PrivateAttachmentStorageTest.php
php artisan test --compact tests/Feature/CompanySettingsTest.php tests/Feature/WooCommerceImportTest.php tests/Feature/InboxPageTest.php
php artisan storage:migrate-company-files --company={company-slug} --scope=all
php artisan storage:migrate-company-files --company={company-slug} --scope=all --execute --force
```

### Courier and Delivery Integration

Courier data is company-specific. A company can have its own Custom/manual partners and its own encrypted API credentials.

Supported provider choices:

- Custom/manual
- Steadfast
- Pathao (live client)
- RedX (live client)
- E-Courier (live client)

Implemented behavior:

- Manual/custom courier booking from Order list and Order detail.
- Active Custom provider selection during manual booking.
- Automatic manual tracking ID generation when none is supplied.
- Steadfast order creation through `https://portal.packzy.com/api/v1/create_order`.
- Steadfast status sync by tracking code or invoice.
- Steadfast consignment ID and tracking code storage.
- Steadfast API key and secret key are stored in the encrypted `credentials` model cast.
- Provider settings support contact person, phone, warehouse, delivery fees, courier costs, return costs, COD percentage, and base URL.
- The Courier Provider form's `Delivery Partner` section is collapsible and remains expanded by default.
- The `Courier` cluster uses concise page-only selector labels: `Providers`, `Bookings`, `Status Logs`, and `Webhook Logs`; full resource titles remain on their pages.
- Delivery status is independent from the sales Order status.
- Normalized delivery statuses are `not_booked`, `booking_pending`, `booked`, `picked_up`, `in_transit`, `delivered`, `partial_delivered`, `returned`, `cancelled`, and `failed`.
- Order status is controlled after creation through the default Filament **Change status** action. Supported accounting statuses are `draft`, `confirmed`, `processing`, `completed`, `cancelled`, `returned`, and `refunded`; list rows, detail/edit pages, and a bulk action expose only legal next workflow stages.
- The operational `shipping` and `delivered` stages map to the existing delivery status instead of duplicating courier state: Shipping sets order `processing` + delivery `in_transit`; Delivered sets order `completed` + delivery `delivered`. Incomplete checkouts remain `storefront_cart_records`, and recovered checkouts retain their recovery link/metadata rather than becoming sales statuses.
- `confirmed`, `processing`, and `completed` reserve stock and count toward customer dues and sales reports. Cancelling, returning, or refunding releases the order's sale stock movement and removes its due/sales contribution. Cancellation, return, and refund transitions require a reason.
- Every order/delivery status mutation that uses model events writes a company-scoped immutable `order_status_transitions` row with before/after states, source, actor, workflow stage, and optional reason. The Order resource exposes this through the read-only **Status History** relation manager. Courier adapter updates keep their separate courier status log.
- Every manual or synchronized status change creates a courier status log.
- Orders expose booking, Steadfast booking, delivered, returned, and status information actions.
- Courier booking detail includes provider, invoice, recipient, COD amount, tracking data, and status history.
- Manual and Steadfast booking services verify that Order and Courier Provider belong to the same company.
- `CourierManager` and `CourierProviderInterface` provide the provider adapter boundary; Manual and Steadfast use concrete adapters.
- Pathao, RedX, and E-Courier have live adapters and API clients (`PathaoCourierClient` with cached issue-token auth, `RedxCourierClient` with API-ACCESS-TOKEN header, `ECourierClient` with API-KEY/API-SECRET/USER-ID headers). Driver-specific encrypted credential fields live on the Courier Provider form; booking without credentials fails with a clear validation message. Orders list exposes Book Pathao / Book RedX / Book E-Courier actions; the booking sync action covers all API drivers.
- The Courier Providers list has a Steadfast "Balance" action that shows the current merchant balance via `get_balance`.
- API calls use bounded timeouts and retry/backoff.
- Signed incoming webhooks are deduplicated, logged, queued, retried, and processed inside the provider's explicit company context.
- Courier Status Log and Webhook Log resources provide operational diagnostics.
- Booking actions support cancellation and configurable tracking/label URL templates.
- `CourierReportService` exposes provider/company delivery, return, cancellation, success-rate, and COD aggregates.
- Production monitoring: `couriers:sync-statuses` runs every 30 minutes (per company, cooldown/batch limit/thresholds admin-configurable on the provider's Monitoring & Alerts section). `CourierAlertService` sends persistent database notifications (admin panel bell) to super admins and the owning company's managers on repeated sync failures, stale bookings, and webhooks that fail after all retries — deduplicated once per subject per day. The dashboard has a Courier Health widget.
- **Courier Merchant Dashboard** (`Courier` cluster, first item): single-screen view of the current company's Steadfast integration so staff never need Steadfast's own website — live account balance (`get_balance`, 5-minute cache), delivery/return performance and margin totals (`CourierReportService`), a recent-consignments table, and a recent-returns preview. Bookings/Status Logs/Returns/Webhook Logs/Payments are intentionally hidden from the `Courier` cluster's sidebar (`protected static bool $shouldRegisterNavigation = false;` — routes stay reachable) and instead surfaced as a native Filament `StatsOverviewWidget` card grid (`App\Filament\Pages\CourierWidgets\CourierQuickLinksWidget`, kept outside the auto-discovered `app/Filament/Widgets/` directory so it only appears on this page, not the main admin Dashboard) at the bottom of the Dashboard, each card showing a live count and linking straight to its resource.
- **Return requests**: `SteadfastCourierClient::createReturnRequest()`/`returnStatus()` call Steadfast's return endpoints; `CourierService::requestReturn()` submits the request and records it locally in `courier_returns` (company-scoped, `BelongsToCompany`) so history is browsable without re-hitting the API. The "Request return" booking action is Steadfast-only for now. The exact endpoint paths aren't published in a canonical spec — see the docblock on `SteadfastCourierClient` — confirm against a live call once real credentials are configured.
- **Payment/settlement history**: `SteadfastCourierClient::payments()` backs a read-only **Payments** page (`Courier` cluster) that fetches live and caches 10 minutes; no local table for this yet since it changes rarely. Columns are derived from whatever keys Steadfast's response actually returns, since the exact shape isn't confirmed either.
- **Delivery fees and booking economics**: the Courier Provider form exposes only **Set Delivery Fees** (`settings.delivery_fees.{inside,outside,suburb}`) for outbound delivery pricing; the duplicate "Courier Delivery Cost" section is intentionally absent. Every booking records the configured `delivery_fee_charged` and `cod_charge_amount`. Existing providers that already contain legacy `settings.delivery_costs` values can still populate `delivery_cost` and `margin`, preserving historical reporting without keeping duplicate inputs in the form. The Bookings table/view expose these economics fields and `CourierReportService` sums available `margin` values per provider/company.
- **Customer Delivery Success Rate**: the "Courier Fraud Check" panel on the Order form now also runs automatically (cached, 24h) when a customer is selected, not only on manual button click, and renders as a per-courier table (Total / Delivered / Undelivered / Confidence) instead of plain text.
- Webhook signature enforcement is per-provider: `settings.webhook_signature_required` (default `true`) can be turned off if a courier is confirmed not to sign its webhook requests, without a code change.
- `courier_providers.credentials` must remain a nullable text column because `CourierProvider` uses Laravel's `encrypted:array` cast. The stored value is opaque encrypted ciphertext, not database JSON. Existing MySQL installations are corrected by `2026_08_13_000000_change_courier_provider_credentials_to_text`; deploy it before saving provider API credentials.

Not implemented yet:

- Provider-native remote cancellation/label endpoints where an official API contract is required; current actions use normalized cancellation and configurable label URLs.
- Return/payment endpoints for Pathao, RedX, and E-Courier (Steadfast only for now, per current scope).
- A locally-persisted payment/settlement history table (currently fetched live + cached; revisit with a `couriers:sync-payments` scheduled command if offline history/reporting is needed).

Important files:

```text
app/Models/CourierProvider.php
app/Models/CourierBooking.php
app/Models/CourierStatusLog.php
app/Models/CourierWebhookLog.php
app/Models/CourierReturn.php
app/Models/OrderStatusTransition.php
app/Services/CourierService.php
app/Services/OrderStatusWorkflowService.php
app/Services/CourierManager.php
app/Contracts/CourierProviderInterface.php
app/Services/Couriers/
app/Services/Couriers/PathaoCourierAdapter.php
app/Services/Couriers/RedxCourierAdapter.php
app/Services/Couriers/ECourierAdapter.php
app/Services/SteadfastCourierClient.php
app/Services/CourierReportService.php
app/Filament/Resources/CourierProviders/
app/Filament/Resources/CourierBookings/
app/Filament/Resources/CourierStatusLogs/
app/Filament/Resources/CourierWebhookLogs/
app/Filament/Resources/CourierReturns/
app/Filament/Pages/CourierMerchantDashboard.php
app/Filament/Pages/CourierPaymentHistory.php
tests/Feature/CourierIntegrationTest.php
tests/Feature/OrderStatusWorkflowTest.php
```

### Customer Success and Risk Score

- The module uses explainable rules rather than machine learning. Every deduction is stored as a named factor.
- Company-level profiles track courier totals plus delivered, returned, and cancelled ratios by customer phone.
- Scores map to Low (`80-100`), Medium (`50-79`), and High (`0-49`) risk; an active global/company blacklist produces the separate Blacklisted level.
- Checks run when an Order enters an accounted status (`confirmed`, `processing`, or `completed`) and again immediately before courier booking.
- Global or company blacklist matches block courier booking pending owner review.
- Terminal courier status changes create idempotent customer risk events and refresh the profile.
- Risk badges appear in Customer and Order lists/details; booking forms show the current score before submission.
- Super Admin manages global/company blacklist entries under the `Customer Success` cluster.
- High-risk orders create manager approval requests before courier booking can continue.
- Blacklisted matches create owner approval requests before courier booking can continue.
- Risk review, risk event, and rule settings screens live under the `Customer Success` cluster.
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

- Human-readable release metadata is centralized in `AppRelease`, `CHANGELOG.md`, and `config/release.php`; machine deployment identity is resolved separately by `AppDeployment`.
- `npm run build` writes `public/build/deployment.json` atomically. Its artifact identity combines Git/platform commit (when available), a deterministic whole-source hash (including PHP, routes, migrations, views, frontend source, public static files, locks, and changelog), and the built Vite manifest hash. Same-commit source/asset changes therefore still produce a new identity.
- Runtime readiness compares the metadata asset hash with the actual Vite manifest and fails closed when build metadata is missing, mixed, or belongs to another runtime commit.
- The no-cache `/health/version` response exposes `deployment_id`, readiness, source/assets hashes, build time, configured version, and latest published version. The admin updater requires two matching observations before treating a different deployment as ready, reducing false prompts during rolling replacement.
- Both browser and server compare deployment build times. A confirmed update remains sticky, older rolling nodes cannot overwrite the latest database baseline or masquerade as an upgrade, and the POST must carry the exact deployment ID the user confirmed before acknowledgement/cache clearing is allowed.
- An open admin/browser/Capacitor session never auto-reloads across a detected deployment. Save-result refresh, mobile pull-to-refresh, focus/online checks, and the 15-second poll reveal a warning-colored **Upgrade App** action immediately above **Sign out** instead. While that update remains pending, same-origin `/admin` anchor and cancellable Livewire SPA navigation are held on the loaded screen and open the upgrade prompt; hash-only navigation and normal save/Livewire actions remain available.
- The native confirmation modal warns the user to save unfinished work. Only its authenticated `POST /admin/app-upgrade` request can acknowledge the exact confirmed deployment and perform the deliberate cache-cleared full reload; notification delivery, viewing Release Notes, dismissing the modal, receiving a push, or tapping a push must never acknowledge it.
- Per-user acknowledgement lives on `users.acknowledged_app_deployment_id`. `app_update_deliveries` has a unique user/deployment pair, so retries and concurrent scheduler/request discovery cannot duplicate an update alert.
- The acknowledged deployment also records `acknowledged_app_version`, `acknowledged_app_commit`, and `acknowledged_app_built_at`. A newly created user is initialized to the then-current ready deployment; after that baseline, these values change only when Upgrade App is explicitly confirmed, allowing Release Notes to distinguish installed from available release state honestly.
- Release Notes resolves state through `AppReleaseStateService`: the acknowledged version is labelled **Installed version**, a newer unacknowledged release is labelled **Update available**, and release history is capped at the installed version so a deployment is never presented as installed merely because its code is now on the server.
- `AppUpdateService` writes Filament-format database notifications synchronously; update alerts do not depend on a queue worker. Request-time sync is non-blocking and delivers only to the current user; `release:notify-deploy` fills missing active users. Existing delivery rows provide a fast path for users who intentionally leave an update pending.
- Native Android push registration uses authenticated, throttled `POST /admin/push-devices` (`admin.push-devices.store`) and `DELETE /admin/push-devices` (`admin.push-devices.destroy`) endpoints. FCM tokens are encrypted at rest and indexed only by a SHA-256 lookup hash; token rotation and user changes update ownership idempotently.
- `resources/js/push-notifications.js` runs only in native Android, requests notification permission, registers the Capacitor/FCM token after an authenticated admin screen loads, and forwards foreground receipt or notification taps to `window.ZamZamAppUpdater.receiveUpdateNotification()`. That bridge verifies the server deployment before showing the prompt and never reloads or acknowledges on its own.
- `release:notify-deploy` also sends high-priority FCM HTTP v1 alerts when Firebase is enabled. `app_update_push_deliveries` uniquely identifies each device/deployment, transient failures are retried, and only provider-confirmed unregistered/mismatched tokens are disabled.
- The Firebase service-account JSON is a server secret. Prefer a read-only mounted file through `FIREBASE_CREDENTIALS`; the base64 environment alternative is supported when secret files are unavailable. Never put either credential in a `VITE_*` variable or commit it.
- Filament database notifications are eagerly mounted and poll every 15 seconds. The mobile avatar-menu notification badge is a matching Livewire poller and counts only unread Filament-format rows.
- The avatar menu exposes native **Profile Settings** at `/admin/profile`. It edits only the signed-in user's name, email, and password; role/company access remains outside that form.
- Deferred upgrade retains the currently loaded frontend shell only. The server-side PHP/backend changes when the release is deployed, and a refresh, sign-in, Android process restart/WebView eviction, deep link, or app reopen can load that current backend before acknowledgement. Preserving the old full application until a device approves requires immutable old/new blue/green releases, restart-safe per-device or per-user sticky routing, shared sessions/database/object storage, backward-compatible migrations, and explicit promotion/retirement; a single replaced Coolify container cannot guarantee that behavior.
- The admin panel includes a Release Notes page rendered with native Filament sections, badges, buttons, and empty states without page-local CSS.
- `CHANGELOG.md` records notable production changes.
- Production deployment documentation requires a database backup before migrations.
- Routine production updates must not run broad seeders, `migrate:fresh`, or other destructive commands.
- Release types include major, minor, patch, security, hotfix, and maintenance updates.

Important files:

```text
app/Support/AppRelease.php
app/Support/AppDeployment.php
app/Services/AppReleaseStateService.php
app/Services/AppUpdateService.php
app/Services/AppUpdatePushService.php
app/Services/FirebaseHttpV1Sender.php
app/Notifications/AppUpdateAvailable.php
app/Http/Controllers/Admin/AppUpgradeController.php
app/Http/Controllers/Admin/PushDeviceController.php
app/Http/Middleware/SyncAppUpdates.php
app/Models/AppUpdateDelivery.php
app/Models/AppUpdatePushDelivery.php
app/Models/PushDevice.php
app/Filament/Pages/ReleaseNotes.php
app/Providers/Filament/AdminPanelProvider.php
resources/views/filament/pages/release-notes.blade.php
resources/views/filament/partials/app-updater.blade.php
resources/js/app-updater.js
resources/js/push-notifications.js
scripts/write-deployment-metadata.mjs
database/migrations/2026_07_23_000000_create_app_update_tracking.php
database/migrations/2026_07_23_120000_create_native_push_tracking_tables.php
config/release.php
config/native_push.php
capacitor.config.json
android/app/src/main/AndroidManifest.xml
CHANGELOG.md
docs/release-policy.md
docs/update-safety.md
tests/Feature/AppUpgradeTest.php
tests/Feature/ReleaseNotificationTest.php
tests/Feature/MobileDatabaseNotificationsMenuItemTest.php
tests/Unit/Support/AppDeploymentTest.php
tests/Feature/ReleaseNotesTest.php
tests/Feature/AppUpdatePushTest.php
tests/Feature/PushDeviceRegistrationTest.php
tests/Node/app-updater.test.mjs
tests/Node/push-notifications.test.mjs
tests/Unit/Services/FirebaseHttpV1SenderTest.php
```

Verification:

```bash
npm run test:deployment-metadata
npm run test:app-updater
npm run test:push-notifications
npm run build
npx cap sync android
php artisan migrate --force
php artisan config:cache
php artisan release:notify-deploy
php artisan test tests/Feature/AppUpgradeTest.php tests/Feature/ReleaseNotificationTest.php tests/Feature/AppUpdatePushTest.php tests/Feature/PushDeviceRegistrationTest.php tests/Unit/Services/FirebaseHttpV1SenderTest.php
```

The committed `.env.testing` forces both the default SQLite and explicit
`demo` connections used by standalone Artisan `--env=testing` commands onto
in-memory SQLite. `demo:refresh` also respects its configured demo path and
skips filesystem setup for `:memory:`. This prevents CLI migration checks from
falling back to a persisted local/demo database; `TestingEnvironmentSafetyTest`
locks that contract.

### External n8n Workflow Exports

The repository includes importable Meta Messenger and WhatsApp workflow exports under `n8n Workflows/`. Live tokens must never be embedded in these JSON files. The HTTP nodes resolve their authorization values from n8n host environment variables:

- `META_MESSENGER_ACCESS_TOKEN`
- `META_MESSENGER_COMMENT_ACCESS_TOKEN`
- `META_MESSENGER_IMAGE_ACCESS_TOKEN`
- `META_WHATSAPP_ACCESS_TOKEN`

The committed JSON files are inactive public-safe templates: stored n8n credential references, instance/workflow IDs, generated webhook IDs, and pinned sample data are removed. After import, replace every `configure-*-webhook` path with a unique value, reconnect the destination instance's credentials, and verify its webhook URLs before activation. See `n8n Workflows/README.md` for the import checklist. Verify future export updates with a secret scan before committing.

### Storefront Foundation

The project includes a native Laravel Blade storefront foundation. Do not install Lunar or create a duplicate ecommerce model layer. Storefront work must reuse the existing ERP `Company`, `Product`, `Category`, `Customer`, `Order`, stock, risk, and courier flows.

Current behavior:

- Public storefront routing is custom-domain aware through `ResolveCompanyFromDomain`.
- Company domains live on `companies.domain`; verification state lives on `companies.domain_verified`.
- Storefront publishing and brand settings live in `storefront_settings`.
- The Filament admin resource is shown as `Settings` in the `Site` cluster; its internal resource and model names remain `StorefrontSettingResource` and `StorefrontSetting`.
- Site `Settings` also acts as the admin launch-readiness dashboard using Filament default table columns/actions: readiness score, missing setup checklist, domain verification, visible product count, published page count, Preview, and Open Site. Its table is fail-closed without a resolved context, shows only the selected company's storefront when one company is active, and shows every accessible storefront only in explicit `All Companies` mode.
- Site → Settings is the only admin editor for storefront domain and domain verification even though the canonical fields remain on `companies.domain` and `companies.domain_verified`. Company create/edit forms do not expose either writable control; Company list/view may show their values as read-only status. Storefront Settings create/edit synchronizes the company fields inside a database transaction, rejects duplicate domains before the unique constraint can throw a 500, and automatically clears verification whenever the normalized hostname changes. Save the new hostname first, verify DNS/server routing, then enable **Domain verified** in a second save.
- Storefront content pages live in `storefront_pages`.
- The Filament admin resource is shown as `Pages` in the `Site` cluster for About, Return Policy, Privacy Policy, Terms, and similar public pages; the underlying `StorefrontPageResource` name and routes remain unchanged.
- Site Settings create/edit submit actions use the default Filament page header; the custom Storefront edit header is sticky below the 4rem Filament top bar and keeps **Save changes** available while scrolling without duplicating a footer save button. Page management is intentionally available only from the main **Pages** navigation item, so Site Settings list, edit, and table rows do not duplicate page shortcuts. Company Settings uses the same header pattern and hides its save action in All Companies mode.
- Site Settings create/edit forms use the same section navigation pattern as the Meta CAPI page. Desktop shows an icon-only rail that expands on hover/focus; mobile shows a sticky menu button above the page title. The active section is highlighted and stored in the `section` query string, and only that section group is rendered while hidden form state remains available to the normal Filament save flow. The groups are Publishing, Theme & Layout, Design System, Homepage, Launch & Branding, Checkout & Protection, Payments & Integrations, Notifications, and Navigation & SEO. Homepage shows hero/trust/countdown fields for the Built-in theme and Marketplace Pro campaign fields for the Marketplace Pro theme, so the destination never becomes empty solely because of the selected theme. WooCommerce synchronization is available only inside the **Payments & Integrations → WooCommerce Import** section after saved credentials are present; it is not duplicated in page headers or Site Settings table actions.
- Verify the Site Settings navigation by opening `/admin/storefront/storefront-settings/{record}/edit`, switching several sections, confirming the `section` query value and active highlight change together, and checking the mobile menu at a width below 1024px. The focused automated coverage is `php artisan test tests/Feature/PhaseFourAdminPagesTest.php`.
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
- Variable products can be added to the storefront cart by option/variant. Cart line keys include product and variant IDs so multiple options of the same product remain separate lines.
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
- Storefront checkout stores `product_variant_id` and a human-readable `variant_label` on order items when the customer buys a product variant. Variant unit price and cost are used when present.
- Storefront orders are created as `draft` by design. Stock is not deducted until an admin reviews and confirms/completes the order through the ERP order workflow.
- Draft storefront orders do not increase `Today Sales`; the dashboard shows them separately as `Storefront Pending` with pending order count and amount.
- The Orders table and Order detail page display the order `Source` badge, and the Orders table can be filtered by `Admin` or `Storefront` source.
- Checkout validates current cart stock and clears the cart after successful order creation.
- Checkout protection is company-configurable from **Site → Site Theme → Checkout Protection**. `off` preserves the legacy checkout, `observe` records policy signals without blocking, and `enforce` rejects a policy violation with a generic customer-facing message. The policy can require a valid Bangladesh mobile number, enforce global/company phone/email/IP blacklist entries, and limit recent non-cancelled storefront orders by phone, email, and IP over a configurable time window. Valid `+880`/`880`/local submissions are normalized to `01XXXXXXXXX` before customer matching. `StorefrontCheckoutPolicyService` is authoritative for both production and preview checkout paths.
- Policy evaluations are written to company-scoped `storefront_checkout_attempts`. Raw identity values are not copied into this audit table: keyed hashes support rate-limit matching and masked phone/email/IP values support staff review. Outcomes progress through allowed/observed/blocked and, where applicable, pending-payment/accepted; accepted attempts link to the resulting ERP Order. The same record stores the courier success ratio (when a provider answered), required advance, and internal advance-reason keys. Staff with sales-view access get the read-only Filament default table at **Site → Checkout Attempts**. Blacklist reasons, courier scores, thresholds, and exact policy signals are never exposed to the customer.
- Checkout advances now use one server-authoritative eligibility decision in `StorefrontPaymentEligibilityService`. It takes the maximum—not the sum—of applicable pre-order, new-customer delivery, and courier-risk requirements, capped at the checkout total, so one checkout is never double-charged. The customer and final Order are not created until ZiniPay verifies the exact expected amount; the encrypted `storefront_payments.checkout_data` snapshot is then consumed atomically, the advance is recorded in `orders.paid_amount`, and the full invoice total/due balance remains intact. Legacy `new_customer_delivery` payments remain finalizable for deployment compatibility, while new flows use `checkout_advance`.
- Courier-history advance eligibility is opt-in per company at **Site → Site Theme → Checkout Protection**. Admins choose the minimum successful-delivery ratio, fixed or percentage advance, and whether a genuine zero-history response permits COD or requires the advance. `ExternalCourierFraudService` checks configured Pathao/Steadfast/RedX merchant accounts and reuses its 24-hour cache. A risk rule is applied only when at least one courier actually answers; missing credentials, timeouts, or all-provider failure are treated as `unavailable` and fail open. The storefront shows only the configurable generic advance notice.
- Storefront delivery is weight based: `products.weight_kg × quantity` is summed, rounded up to a minimum/started whole kilogram, then charged using company-specific first-kg rates (default BDT 70 inside Dhaka, BDT 110 outside) plus the additional-kg rate (default BDT 20). `StorefrontDeliveryService` is the single calculator used by both the checkout display and persisted order.
- Checkout payment returns use temporary-signed `/checkout/payment/{payment}/return` URLs. The return and ZiniPay webhook both call `StorefrontPaymentService`, so verification and order creation are idempotent; successful delivery advance is stored in `orders.paid_amount`, leaving only the product balance due on delivery.
- Checkout success pages show the generated ERP order number and order summary. Production success URLs are temporary-signed for 24 hours; an unsigned URL is accepted only for the authenticated customer who owns that order. ZiniPay redirect/cancel URLs use the same signed success URL. Local admin preview remains unsigned.
- Thank-you pages show customer/delivery data, item and payment breakdowns, a company-configurable WhatsApp group CTA, and a prefilled complaint link. Public complaints are accepted at `/complaints` (preview: `/storefront/{company}/complaints`) using an order ID, invoice/order number, or phone number. Records live in company-scoped `storefront_complaints`, receive a sequential `CMP-YYYYMMDD-####` reference, and are managed with Filament's default UI under **Site → Complaints**.
- Each company's Site Theme record has its own encrypted Telegram bot token and chat ID. When complaint Telegram delivery is enabled, `TelegramComplaintService` sends the complaint to that company's configured chat and retains sent/failed diagnostics on the complaint; a Telegram failure never loses the database complaint.
- Production checkout routes on custom domains:
  - `GET /checkout`
  - `POST /checkout`
  - `GET /checkout/success/{order}`
- Storefront order tracking lets customers search by ERP order number and view order status, the current delivery status only after an admin/courier update, latest courier/provider/tracking ID, totals, due amount, and ordered items. Default `Not Booked` delivery status stays hidden on the customer-facing page.
- Admin/courier status changes appear as chronological `Tracking Updates` rather than a fixed list of possible statuses. Order status and delivery status updates come from order audit logs; courier updates come from courier status logs.
- Tracking update markers and the `Latest` badge are styled in `resources/views/storefront/track/show.blade.php`; markers use 100px rounded corners and the latest badge uses compact 10px horizontal padding.
- Tracking only exposes orders from the current storefront company and only when `source = storefront`; admin orders and other-company orders are never revealed. Public search requires both order number and checkout phone. A temporary-signed tracking link or an authenticated owning customer may open the same order without putting the phone number in the URL.
- Production customer order history at `/account/orders` requires the company-scoped `customer` guard and shows only that customer's current-company storefront orders. Signed-out visitors are sent to account login; when customer accounts are disabled they are sent to manual order tracking. Phone-only history lookup is retained only on the local/authenticated admin preview route.
- Customer account settings use one responsive account shell: mobile gets horizontally scrollable 44px-touch navigation and desktop gets a sticky sidebar. `/account` shows order metrics, recent orders, and recent account activity; `/account/profile`, `/account/orders`, and `/account/activity` share the same private navigation and opaque dark-mode card surfaces.
- Customer activities persist in `storefront_customer_activities`, scoped by both company and customer. Registration, successful login, profile updates, password changes/resets, authenticated checkout, and successful reorder actions write customer-facing timeline events. Activity metadata may link an owned storefront order, but another customer or company cannot read it.
- Deployments and existing demo databases must run the customer-activity migration (`php artisan migrate`, demo: `php artisan migrate --database=demo`). Account actions remain available if the table is temporarily absent during deployment; activity recording resumes after migration instead of failing registration with a 500 response.
- B2B wholesale rules live on products: `products.moq` (nullable minimum order quantity) and `products.tier_prices` (nullable JSON list of `{min_qty, price}` rows), edited in the Product form's "Wholesale (B2B)" section. On the storefront, cart quantities are clamped up to the MOQ; quantity 0 still removes a normal line. Non-preorder stock remains the upper cap, while non-variant preorders can reach `StorefrontCart::PREORDER_STOCK_CEILING` (100,000) even at zero current stock. Non-variant cart lines use `Product::priceForQuantity()` — the deepest matching tier price, falling back to `selling_price`; variant lines keep their variant price. Covered by `tests/Feature/StorefrontB2bTest.php` and `tests/Feature/StorefrontPreorderPaymentTest.php`.
- Customer balances remain staff-only and are not rendered in storefront order history.
- Each order card has a `Reorder` button (`POST /account/orders/{orderNo}/reorder`, preview: `POST /storefront/{company-slug}/account/orders/{orderNo}/reorder`). Production requires the authenticated customer who owns the order; local preview retains phone verification. Reorder adds still-available products/variants with the same stock/preorder caps, skips discontinued items, and redirects to the cart with a status message. Covered by `tests/Feature/StorefrontReorderTest.php`.
- Published storefront pages are available from footer links and at `/pages/{slug}`. Unpublished pages and other-company pages return 404.
- In local/testing, `/pages/{slug}` falls back to the first published storefront company so admins can preview content pages on `127.0.0.1`; local company-scoped preview still works at `/storefront/{company-slug}/pages/{slug}`.
- Admin Order forms use two explicit labels: `Order Status` for invoice/stock/accounts/reporting workflow and `Delivery Status` for storefront tracking/courier progress; courier booking actions may update delivery status automatically.
- Existing Order forms show those status fields read-only; staff use the controlled **Change status** action for workflow updates. Mixed selections can use the bulk action, which updates legal records and reports skipped incompatible transitions without partially changing them.
- Production tracking routes on custom domains:
  - `GET /track`
  - `GET /track/{orderNo}`
- Production customer account routes on custom domains:
  - `GET /account`
  - `GET /account/orders`
  - `GET /account/activity`
  - `POST /account/orders/{orderNo}/reorder`
  - `GET|POST /account/login`
  - `GET|POST /account/otp`
  - `GET|POST /account/otp/verify`
  - `GET|POST /account/register`
  - `GET|POST /account/forgot-password`
  - `GET|POST /account/reset-password`
  - `GET|PATCH /account/profile`
  - `PUT /account/password`
  - `POST /account/logout`
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
  - `GET /storefront/{company-slug}/account`
  - `GET|POST /storefront/{company-slug}/account/login`
  - `GET|POST /storefront/{company-slug}/account/otp`
  - `GET|POST /storefront/{company-slug}/account/otp/verify`
  - `GET|POST /storefront/{company-slug}/account/register`
  - `GET|POST /storefront/{company-slug}/account/forgot-password`
  - `GET|POST /storefront/{company-slug}/account/reset-password`
  - `GET|PATCH /storefront/{company-slug}/account/profile`
  - `PUT /storefront/{company-slug}/account/password`
  - `GET /storefront/{company-slug}/account/orders`
  - `GET /storefront/{company-slug}/account/activity`
  - `POST /storefront/{company-slug}/account/logout`
- Local preview storefront content routes:
  - `GET /storefront/{company-slug}/pages/{slug}`
- Storefront UI should use Tailwind CSS 4 via Vite and should aim for a polished Shopify-style ecommerce look: image-first product cards, clean collection tiles, strong CTA buttons, responsive mobile layout, and dark/light compatibility.
- Storefront layout includes SEO/Open Graph/Twitter metadata from storefront settings, compact mobile-safe header actions, footer WhatsApp contact, banner-image hero support, and explicit out-of-stock product states.
- Dark mode is a real class-based toggle, not just `prefers-color-scheme`. `resources/css/app.css` declares `@custom-variant dark (&:where(.dark, .dark *));` (storefront only; Filament's own dark mode is unaffected). `resources/views/storefront/layout.blade.php` sets the `dark` class before paint from `localStorage.storefrontTheme`, falling back to the company's `storefront_settings.theme_mode` (`system`/`light`/`dark`) on first visit. The toggle exposes its current action and pressed state; quantity steppers and shell controls use plain JavaScript, while page-level disclosure/checkout state may use Alpine from `resources/js/app.js`.
- Product listing supports `?sort=price_asc|price_desc` (default newest) and category quick-filter chips, handled in `StorefrontProductIndexController` and `PreviewController::products`.
- Product detail pages show a breadcrumb, a compact premium gallery capped at 480px on desktop, pointer-position hover zoom for fine-pointer devices, a sticky buy box with a quantity stepper, and a "You may also like" related-products rail (same category, excludes current product, limit 4), sourced from `StorefrontProductShowController` and `PreviewController::product`.
- Variable product detail pages show available variants/options, variant images, per-option stock/price, and quantity inputs so customers can add multiple variants in one submit.
- When no hero slide exists, the Built-in homepage fallback heading/subheading/CTA remain admin-editable via `storefront_settings.hero_heading`, `hero_subheading`, `hero_cta_label` (all nullable; blank falls back to the default "Shop the latest from {company}." copy in `home.blade.php`). Configured hero slides render through one shared image-only carousel in both Built-in and Marketplace Pro themes: full viewport width, one-third viewport height on desktop, one-sixth viewport height on mobile, `object-cover` cropping, no visible slide heading/subheading/CTA overlay, and a smooth 700ms autoplay transition for multiple images. `cta_url` or a linked product can still make the entire clean image clickable. Reduced-motion preferences disable autoplay and transitions.
- Marketplace Pro no longer renders the announcement/quote strip above the homepage banner. Its legacy database fields remain readable for backward compatibility, but the storefront and Site Settings form do not expose or use them.
- Public storefront copy should not expose implementation details such as unfinished roadmap steps; customer-facing text should describe direct ordering, review, confirmation, and tracking.
- Header includes a hover mega menu ("Categories") listing the current company's active categories that have available products, plus an optional call button (`storefront_settings.phone_number`, `tel:` link) next to the WhatsApp button. Both are queried inline in `layout.blade.php`.
- Homepage hero banners support separate desktop and mobile images through `storefront_slides.image` and `storefront_slides.image_mobile`, swapped via a `<picture>` breakpoint at 640px in `partials/image-banner.blade.php`. Both variants use `object-cover` inside the viewport-based banner stage documented above.
- Homepage includes a static 4-step "how to order" explainer section (no backend data) between the hero and the category grid.
- Footer includes a "Our other brands" cross-promotion section listing other active companies that have a published storefront and a domain set, linking to `https://{domain}`.
- A fixed mobile bottom nav bar (Home/Category/Cart/Account, `sm:hidden`) is rendered in `layout.blade.php`; `<main>` gets `pb-16 sm:pb-0` and the footer gets `mb-16 sm:mb-0` so content does not sit under it on small screens.
- Curated homepage product carousels are implemented through the `Product Carousels` Filament resource. Active carousels are company scoped, ordered by sort order, hidden when empty/inactive, and rendered on the storefront homepage.
- Avoid broad inline CSS for storefront pages unless it is a small dynamic CSS variable such as company theme color.
- The 2026-07-20 storefront hardening pass added: keyboard-visible focus styles, touch-safe targets, automatic readable foreground text on merchant brand backgrounds, an accessible mobile menu and theme state, `aria-current` navigation, FAQ and product-tab semantics, a two-column 390px catalog, intrinsic product/content image dimensions, mobile safe-area spacing, pending/double-submit states, and checkout error-summary focus. Key UI files are `resources/views/storefront/layout.blade.php`, `home.blade.php`, `products/index.blade.php`, `products/show.blade.php`, `partials/product-card.blade.php`, `cart/show.blade.php`, `checkout/show.blade.php`, and `resources/css/app.css`.
- The 2026-08-01 mobile-responsive storefront pass standardizes primary controls at a minimum 44px touch target, makes category chips horizontally scrollable on narrow screens, keeps sort controls full-width and labeled on mobile, aligns product-card heights, compacts cart lines into an 80px thumbnail layout, stacks checkout delivery areas below 640px, and reduces mobile checkout padding. The homepage carousel now exposes a 44px Pause/Play control, avoids nested slide/CTA links, follows the resolved product CTA URL, and runs the decorative "How to order" sequence once instead of looping indefinitely. Verify at 375px, 768px, 1024px, and 1440px using the preview routes listed below, then run `npm run build` and the storefront feature tests.
- The 2026-08-02 dense-commerce storefront pass introduces a storefront-only Arial/system type stack, a flexible 1480px maximum content canvas, 16/20/24px responsive page gutters, a 60px primary header plus 40px desktop navigation row, 26-32px page-title scale, 14-15px body copy, compact 24-32px section spacing, 8px structural card radii, and opaque dark-mode cards. These rules apply across home, catalog, product detail, cart, checkout, tracking, account, reseller, contact, and content pages through `resources/css/app.css` and the shared Blade layout. Product detail uses a maximum 460px gallery with pointer-position hover zoom while preserving the mobile purchase bar and 44px touch targets. Verify representative pages at 375px, 768px, 1024px, and 1440px, then run `npm run build`, `php artisan view:cache`, and storefront feature tests.
- Storefront theme contrast is controlled from **Storefront → Settings → Theme Color Palette → Text on primary**. `Auto contrast` calculates a WCAG-oriented black/white foreground from `theme_color`; `Light text` and `Dark text` are explicit brand overrides. The persisted `storefront_settings.theme_foreground_mode` is resolved by `StorefrontSetting::themeForegroundColor()` and exposed as `--storefront-brand-contrast`, which covers solid theme buttons, badges, selected navigation, CTA sections, hover states, and the 90%-opacity pre-order badge. The same pass reduces profile-dropdown rows to 40px and compacts the account shell, sidebar, statistics, profile, order, and activity cards without changing their functionality.
- The professional storefront design system is managed from **Storefront → Settings → Theme Color Palette**, **Storefront Typography**, and **Modern Appearance**. The palette form uses separate Filament sections for shared Brand & Interaction colors, Light Mode surfaces, and Dark Mode surfaces so each semantic role is immediately visible. Palette presets (`Emerald Commerce`, `Ocean Blue`, `Royal Indigo`, `Premium Black & Gold`, and `Modern Rose`) populate editable primary, secondary, accent, light/dark canvas, card surface, text, muted text, and border colors; primary foreground can remain automatic or be overridden. Typography presets provide system, Inter, commerce (Rubik/Nunito Sans), Bangla-optimized (Hind Siliguri/Noto Sans Bengali), and editorial pairings, plus independent heading/body fonts, an accessibility-oriented 16px default (14–18px selectable), weights, heading scale, 1.4–1.65 line height, heading/body tracking, and 62/70/78-character long-form measures. Modern Appearance presets configure content width, responsive gutters, corner radius, card depth, hover feedback, and 0/150/220ms motion. `StorefrontSetting` validates every stored option and emits safe CSS-ready values; `resources/views/storefront/layout.blade.php` exposes them as scoped tokens and `resources/css/app.css` applies them across storefront pages, cards, controls, responsive wrappers, rich content, transitions, and hover states. Global `prefers-reduced-motion` handling always suppresses storefront animation regardless of the merchant setting.
- Product categories support an optional square image and any solid or outline Heroicon bundled with Filament. **Inventory → Categories** opens the company-scoped category list with view/edit actions, and the same media fields are available from category create/edit forms and the Product form's inline **Create category** action. Clicking **Category icon** opens a Filament modal containing the complete visual icon library with live search, scrolling, Enter-key filtering, one-click selection, selected-icon feedback, and a category-initial reset action. Search uses one delegated Filament panel script (`resources/views/filament/partials/category-icon-search.blade.php`) that directly toggles matching tile visibility on input/search/Enter events, avoiding component-scope coupling inside the modal and showing a native empty state when nothing matches. Category images are compressed to WebP and take priority over icons; the storefront falls back from image → selected icon → category initial in both Built-in Theme and Marketplace Pro. `App\Support\StorefrontCategoryIcons` derives and validates the catalog directly from Filament's `Heroicon` enum, maps the earlier curated icon keys for backward compatibility, and `resources/views/storefront/partials/category-icon.blade.php` renders the selected library icon. `database/migrations/2026_08_03_020000_expand_category_icon_length.php` expands the icon key column for the longest Filament names. Verify with `php artisan test tests/Feature/CategoryMediaTest.php tests/Feature/AdminNavigationClustersTest.php`, `php artisan view:cache`, and `npm.cmd run build`.
- Storefront theme selection is managed from **Site → Site Theme**. The existing storefront is registered as **Built-in Theme** and remains the database/default fallback. **Marketplace Pro** is based on `ERP Frontend Design System/ইকমার্স স্টোরফ্রন্ট ডিজাইন/Storefront Home Concepts.dc.html` and provides three responsive homepage templates: **Hero-driven**, **Campaign-driven**, and **Compact & dense**. `App\Support\StorefrontThemeRegistry` is the source of truth for theme labels, template options, safe normalization, and homepage view resolution; `HomeController` and `PreviewController` render the selected registered view. Marketplace Pro has independent Filament controls for the announcement bar, quote actions, business-account callouts, trust strip, deals, categories, campaign bulk-pricing panel, compact category sidebar, product density, and campaign copy. Shared palette, typography, appearance, catalog, cart, checkout, tracking, and customer-account functionality continue to work across both themes. The Marketplace Pro Blade implementation is `resources/views/storefront/themes/marketplace-pro/home.blade.php`, scoped styling lives in `resources/css/app.css`, and selected values persist through `database/migrations/2026_08_03_000000_add_theme_selection_to_storefront_settings_table.php`. Verify with `php artisan test tests/Feature/StorefrontThemeTest.php tests/Feature/PhaseFourAdminPagesTest.php`, the storefront regression tests, `php artisan view:cache`, and `npm.cmd run build` on Windows.
- In dark mode, each homepage "How to order" step uses an opaque `gray-900` card with a `gray-800` border so the animated progress track remains visually behind the cards instead of bleeding through their surfaces. Light-mode cards remain white.
- The responsive storefront header keeps the logo/search/actions in the primary row and renders desktop navigation in a separate row from 1024px upward so menu items cannot overlap the logo. Below 1024px the hamburger opens the existing mobile menu; below 640px the duplicate header cart is hidden because Cart remains available in the fixed bottom navigation. An authenticated profile dropdown exposes Account Overview, Profile Settings, My Orders, Account Activity, tracking, reseller status, logout, and the light/dark switch with icons and current-page highlighting.
- Variable-product cards never submit an incomplete quick-add request: they open the product option selector. On the detail page, desktop and mobile add buttons share the selected-variant quantity state. Checkout renders one shared manual-payment sender/transaction field pair, defaults to the first enabled payment method, and blocks submission only when no valid payment path exists or required preorder advance payment is unavailable.
- Checkout no longer asks the customer to select a delivery area. `StorefrontDeliveryAreaResolver` detects inside/outside Dhaka from the submitted delivery address, and `CheckoutController` performs the authoritative server-side calculation before creating an order or delivery-advance payment. Unknown addresses default to outside Dhaka. The browser mirrors the result only for the live order-summary preview. Admins can override the built-in English/Bangla locality list from **Storefront → Settings → Checkout & Delivery → Inside-Dhaka address keywords**. Key files: `app/Services/StorefrontDeliveryAreaResolver.php`, `app/Http/Controllers/Storefront/CheckoutController.php`, and `resources/views/storefront/checkout/show.blade.php`.
- Registered, active customer accounts support password-free login through a six-digit OTP sent to the submitted account email or phone. Email delivery uses Laravel's configured mailer; phone delivery uses the company-specific SMS URL template in **Storefront → Settings → Customer Notifications & Reminders**. Codes are hashed, single-use, expire after 10 minutes, allow five failed attempts, have a one-minute resend cooldown, and use generic responses for unknown accounts. OTP does not turn checkout-created CRM rows without a password into accounts. Key files: `app/Http/Controllers/Storefront/AccountAuthController.php`, `app/Services/CustomerAccountService.php`, `app/Mail/StorefrontLoginOtp.php`, and `resources/views/storefront/account/otp-*.blade.php`. Verify with `php artisan test tests/Feature/StorefrontCustomerAuthTest.php tests/Feature/StorefrontManualPaymentTest.php tests/Feature/StorefrontCustomerAdvanceAndComplaintTest.php`.
- Pre-order: products with `is_preorder = true` can be ordered beyond current stock ("Pre-order now" button/badge replaces "Out of stock"). The per-product `preorder_advance_percent` (default 100) of any pre-order quantity beyond stock feeds the unified checkout-advance decision; checkout is blocked when an advance is required but ZiniPay is not configured. No Customer or Order is created before exact server-side `/v1/payment/verify` confirmation (webhook `POST /webhooks/zinipay/{payment}`, CSRF-exempt, amount-matched). ZiniPay credentials: storefront settings "Online Payments (ZiniPay)" section (`online_payment_enabled` + encrypted `payment_credentials`).
- Reseller applications: public `/reseller` page (preview: `/storefront/{company-slug}/reseller`) creates/updates a company-scoped Customer with `reseller_status = pending`, `business_name`, `reseller_note`. Admin approves from the Customer form's Reseller section; the Customers table shows a reseller badge. Approved customers keep their status if they re-apply. Wholesale price gating per reseller is intentionally deferred until a customer login/OTP system exists — tier prices are currently public.
- Incomplete checkout recovery extends the existing company-scoped `storefront_cart_records` lifecycle instead of creating draft ERP orders. When **Site → Site Theme → Customer Notifications & Reminders → Capture incomplete checkout details** is enabled, checkout entry records the cart subtotal/start time and a throttled same-origin endpoint debounces valid name/phone/email/address input; email and address use encrypted model casts. Raw card/payment credentials are never captured. The read-only default Filament resource at **Site → Incomplete Checkouts** provides status/contact/cart/recovered-order details, filters, and 30-day started/open/recovered/revenue stats.
- The hourly `storefront:send-abandoned-cart-reminders` command sends one SMS and/or Meta Cloud WhatsApp template per stale active/checkout-started cart with a valid phone. SMS includes a company-domain recovery URL protected by a keyed HMAC token that is not stored in the database. Existing two-variable WhatsApp templates remain compatible; an optional approved three-variable recovery template receives customer name, store name, and the recovery URL. Opening a valid company-scoped link restores the original items, and successful checkout links the recovered ERP Order and revenue. Cart tokens rotate after conversion so a new cart cannot overwrite historical recovery analytics.
- WooCommerce product import: `php artisan woocommerce:import-products {company-slug}` (optional `--no-images`) reads `woocommerce_base_url` + encrypted `woocommerce_credentials` (consumer key/secret) from that company's storefront settings, pages through `/wp-json/wc/v3/products?status=publish`, matches by SKU then slug (re-running updates rather than duplicates), maps regular/sale price, first category (created if missing), description, and optionally downloads the first image to the public disk. Imported products start with stock 0 — stock must enter through stock movements.
- Meta commerce tracking is configured per company on the consolidated **Site → Meta CAPI** page. Its in-page section rail uses Filament controls and mirrors the main sidebar: on desktop it stays icon-only at 4.5rem, each menu button and its right border contract to the rail's 2.5rem inner width, and every text label is removed from layout until pointer hover or keyboard focus expands the buttons and rail over the content. On mobile the horizontal scroller is replaced by a Filament hamburger icon and vertical dropdown navigation with a 10px top offset, theme-aware top border, current-section label, and highlighted active item. The mobile navigation renders before the page title, and the combined custom Meta header remains sticky directly below the 4rem Filament topbar while the settings page scrolls. The header subtitle uses Filament's `text-sm` scale to match section descriptions; the rest of the page retains Filament's default typography. The selected item is deep-linked through the `section` query parameter and renders only its own content while preserving unsaved form state across section switches. Each section's primary controls remain configurable while the master tracking switch is off; only genuinely dependent fields are progressively disclosed by their own CAPI, browser, consent, purchase-timing, custom-event, or status-event controls. The page contains Overview, Connection & Pixels, Consent & Matching, Browser Events, Purchase Delivery, Status Events, and the authorized Event Log & Retries table. The former Site Theme Meta section was removed, and the old Meta Events URL redirects directly to the Event Log section. New/existing settings default to requiring an explicit storefront Meta-consent choice: the accessible consent panel stores a company-specific accept/decline cookie for 180 days, and neither browser Pixel nor CAPI customer context/delivery runs before acceptance. Domain-verification meta tags can still render independently. Local/admin preview routes never emit live tracking. Disabling the consent requirement is an admin/legal-policy decision, not an application default.
- Browser tracking can select `PageView`, `ViewContent`, `AddToCart`, `InitiateCheckout`, `Purchase`, `ViewCart`, `RemoveFromCart`, `ViewCategory`, `ViewItemList`, `Search`, and `CompleteRegistration`. Admins may also define selector-based link/click, scroll-visibility, and elapsed-time custom events through native Filament controls; invalid selectors fail safely. One primary and multiple additional Pixel/Dataset IDs are supported. Optional advanced matching initializes each Pixel with SHA-256-hashed authenticated-customer email/phone/external ID and never embeds raw identifiers in the rendered page.
- `Purchase` is sent server-side only after a storefront ERP Order exists, including orders created after verified checkout advance. Browser and server use deterministic `purchase-{company_id}-{order_id}` IDs for deduplication. Delivery timing is configurable as immediate, always after confirmation, or risk-aware: risk-aware orders with a known courier success ratio below the configured threshold wait until confirmation, while an unavailable ratio fails open to immediate delivery. The success page suppresses its browser Purchase when server delivery is intentionally delayed. `StorefrontMetaDispatchService` persists the consented matching context encrypted, associates any recovery cart, and clears the context after successful Purchase delivery; the scheduled retry command also expires remaining context after 30 days.
- Optional status-based CAPI events emit `Recovered` from the incomplete-checkout recovery subsystem and `Confirmed`, `Processing`, `Shipping`, `Delivered`, `Completed`, `Cancelled`, `Returned`, and/or `Refunded` after the corresponding committed lifecycle transition. Transition events use `status-{stage}-{company_id}-{order_id}-{transition_id}`; recovery uses a cart-specific ID. These `system_generated` events deliberately exclude staff browser IP/user-agent/cookies. The jobs are fail-open: Meta failure cannot reject checkout, payment verification, recovery, or an order transition.
- Meta CAPI credentials are separate from CRM channel tokens and encrypted in `storefront_settings.meta_tracking_credentials`; each configured Pixel can carry its own access token and test code. Email, normalized phone, and external customer ID are SHA-256 hashed before transmission. Company-scoped `storefront_meta_events` records one privacy-minimized delivery audit per Pixel/event with order, transition, or recovery-cart references. Under **Site → Meta CAPI → Event Log & Retries**, authorized staff can inspect delivery and retry failed/pending rows with the existing native Filament action. `php artisan storefront:retry-meta-events` queues bounded retries (maximum five attempts, failures at least ten minutes old), runs every ten minutes, skips already-sent Pixel deliveries, and clears encrypted attribution contexts older than 30 days. Production therefore requires the Laravel scheduler and queue worker to be running.
- Whenever storefront UI/routes/settings/cart/checkout are changed, update this guide with the affected files and verification steps.
- Verify checkout protection with `php artisan test tests/Feature/StorefrontCheckoutPolicyTest.php tests/Feature/StorefrontManualPaymentTest.php tests/Feature/StorefrontCustomerAdvanceAndComplaintTest.php tests/Feature/CustomerRiskTest.php tests/Feature/MultiCompanyIsolationTest.php`.
- Verify incomplete checkout recovery with `php artisan test tests/Feature/StorefrontIncompleteCheckoutRecoveryTest.php tests/Feature/StorefrontResellerAndAbandonedCartTest.php tests/Feature/StorefrontCustomerAdvanceAndComplaintTest.php`.
- Verify Meta Pixel/CAPI tracking with `php artisan test tests/Feature/StorefrontMetaTrackingTest.php tests/Feature/StorefrontFoundationTest.php tests/Feature/StorefrontPreorderPaymentTest.php tests/Feature/StorefrontRiskPaymentEligibilityTest.php` and compile the storefront with `php artisan view:cache` plus `npm.cmd run build` on Windows.

Important files:

```text
app/Http/Middleware/ResolveCompanyFromDomain.php
app/Http/Controllers/Storefront/
app/Http/Controllers/Storefront/AccountController.php
app/Http/Controllers/Storefront/AccountOrdersController.php
app/Http/Controllers/Storefront/CartController.php
app/Http/Controllers/Storefront/CheckoutController.php
app/Http/Controllers/Storefront/ComplaintController.php
app/Http/Controllers/Storefront/OrderTrackController.php
app/Http/Controllers/Storefront/PageController.php
app/Services/StorefrontCart.php
app/Services/StorefrontDeliveryService.php
app/Services/StorefrontPaymentService.php
app/Services/StorefrontOrderPlacementService.php
app/Services/StorefrontCheckoutPolicyService.php
app/Services/StorefrontMetaDispatchService.php
app/Services/StorefrontMetaConversionsService.php
app/Services/StorefrontMetaTrackingService.php
app/Filament/Pages/MetaCapiSettings.php
resources/views/filament/pages/meta-capi-settings.blade.php
app/Filament/Resources/StorefrontSettings/Pages/Concerns/HasStorefrontSettingsNavigation.php
resources/views/filament/resources/storefront-settings/settings-page.blade.php
resources/views/filament/resources/storefront-settings/partials/settings-header.blade.php
app/Services/TelegramComplaintService.php
app/Models/StorefrontComplaint.php
app/Services/StorefrontCustomerActivityService.php
app/Models/StorefrontCustomerActivity.php
app/Models/ProductCarousel.php
app/Models/ProductVariant.php
app/Models/StorefrontPage.php
app/Models/StorefrontSetting.php
app/Models/StorefrontCheckoutAttempt.php
app/Models/StorefrontMetaAttribution.php
app/Models/StorefrontMetaEvent.php
app/Models/OrderStatusTransition.php
app/Jobs/SendStorefrontMetaPurchaseJob.php
app/Jobs/SendStorefrontMetaRecoveredJob.php
app/Jobs/SendStorefrontMetaOrderStatusJob.php
app/Jobs/RetryStorefrontMetaEventJob.php
app/Console/Commands/RetryStorefrontMetaEvents.php
resources/views/storefront/partials/meta-pixel.blade.php
resources/views/storefront/partials/meta-consent.blade.php
resources/views/storefront/partials/image-banner.blade.php
app/Support/StorefrontCategoryIcons.php
app/Support/StorefrontThemeRegistry.php
app/Filament/Resources/ProductCarousels/
app/Filament/Resources/StorefrontPages/
app/Filament/Resources/StorefrontSettings/
app/Filament/Resources/StorefrontCheckoutAttempts/
app/Filament/Resources/StorefrontSettings/Pages/CreateStorefrontSetting.php
app/Filament/Resources/StorefrontSettings/Pages/EditStorefrontSetting.php
database/migrations/2026_06_25_000000_add_storefront_foundation_fields.php
database/migrations/2026_08_09_050000_add_storefront_meta_status_events.php
database/migrations/2026_06_25_001000_create_storefront_settings_table.php
database/migrations/2026_06_28_001000_create_storefront_pages_table.php
database/migrations/2026_08_02_000000_create_storefront_customer_activities_table.php
database/migrations/2026_08_02_010000_add_theme_foreground_mode_to_storefront_settings_table.php
database/migrations/2026_08_02_020000_add_theme_palette_and_typography_to_storefront_settings_table.php
database/migrations/2026_08_02_030000_add_advanced_theme_controls_to_storefront_settings_table.php
database/migrations/2026_08_03_000000_add_theme_selection_to_storefront_settings_table.php
database/migrations/2026_08_09_000000_add_storefront_checkout_protection.php
database/migrations/2026_08_03_010000_add_icon_to_categories_table.php
database/migrations/2026_07_03_000000_add_hero_and_theme_fields_to_storefront_settings_table.php
database/migrations/2026_07_03_010000_add_dual_banner_and_phone_to_storefront_settings_table.php
database/migrations/2026_07_03_020000_create_product_carousels_tables.php
database/migrations/2026_07_03_040000_create_product_variants_and_gallery.php
database/migrations/2026_07_03_050000_add_product_variant_id_to_stock_movements.php
database/seeders/DemoDataSeeder.php
routes/web.php
resources/views/storefront/
resources/views/storefront/cart/show.blade.php
resources/views/storefront/checkout/
resources/views/storefront/track/show.blade.php
resources/views/storefront/layout.blade.php
resources/views/storefront/home.blade.php
resources/views/storefront/themes/marketplace-pro/home.blade.php
resources/views/storefront/partials/product-card.blade.php
resources/views/storefront/partials/category-icon.blade.php
resources/views/storefront/products/index.blade.php
resources/views/storefront/products/show.blade.php
resources/views/storefront/cart/show.blade.php
resources/views/storefront/checkout/show.blade.php
app/Http/Controllers/Storefront/ProductIndexController.php
app/Http/Controllers/Storefront/ProductShowController.php
app/Http/Controllers/Storefront/PreviewController.php
resources/css/app.css
tests/Feature/StorefrontFoundationTest.php
tests/Feature/StorefrontCustomerAuthTest.php
tests/Feature/StorefrontManualPaymentTest.php
tests/Feature/StorefrontMenuTest.php
tests/Feature/StorefrontPreorderPaymentTest.php
tests/Feature/StorefrontReorderTest.php
```

Verification:

```bash
php artisan demo:refresh
php artisan test --filter=StorefrontFoundationTest
php artisan test --filter=StorefrontCustomerAuthTest
php artisan test --filter=PhaseFourAdminPagesTest
php artisan test --filter=ProductCarouselTest
php artisan test --filter=ProductVariantTest
php artisan test --filter=StorefrontMenuTest
php artisan test --filter=StorefrontManualPaymentTest
php artisan test --filter=StorefrontPreorderPaymentTest
php artisan test --filter=StorefrontReorderTest
php artisan view:cache
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
- optional gallery images and product variants with per-variant SKU, options, price, cost, stock, and images
- category relationship

Stock behavior:

- Stock is calculated from stock movements.
- Products marked `has_variants` use active variant stock as the parent product stock total; the ledger sync does not overwrite parent stock for variable products.
- Opening, purchase, and return movements increase stock.
- Sale movements reduce stock.
- Sale stock movements can reference `product_variant_id`; variant stock is validated against the variant's current stock, adjusted by signed movement delta, and restored when the movement is deleted.
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
- Downloadable company-aware PDF route: `/admin/orders/{order}/pdf`
- Company invoice settings and the normalized, database-unique invoice prefix are edited at `/admin/company-management/company-settings` after selecting that company from the top-bar switcher. The page always stays in the Company Management cluster's native Filament sub-navigation; in `All Companies` mode it shows a native select-company empty state instead of a writable form. With one company selected, it uses default form sections and pins the mounted company against cross-tab context drift. Legacy `/admin/company-settings` and `/admin/companies` entry URLs redirect to their cluster destinations.
- Print and PDF rendering use `$order->company`, not the currently selected company, so a permitted cross-company view cannot inherit the wrong branding or invoice contacts.
- Printable invoices hide zero-value discount, VAT, paid, and advance-style paid rows; paid amounts display as a negative deduction when greater than zero.
- The responsive stacked invoice layout is restricted to screen media. A4 printing keeps the desktop header, metadata, totals, contact strip, and courier-slip columns intact, and requests exact print colors for the gray rows and black due-amount bars.
- Printable invoice typography follows the compact reference hierarchy: 7.5pt-equivalent table/body/totals text, 9pt-equivalent supporting and thank-you text, 9–10.5pt customer/meta text, a 21pt-equivalent company title, and a smaller 7.5pt-equivalent courier-slip body. Product thumbnails are also reduced to 34px so item rows remain compact in physical prints.
- When a company logo is configured, the print view and downloadable PDF render that order company's light logo (falling back to its configured dark logo) in both the main header and courier-slip footer. Only the reference invoice's physical dimensions are followed: a left-aligned 90pt-by-48.7pt (31.8mm-by-17.2mm) main logo box and a 48pt-by-26.2pt (16.9mm-by-9.2mm) slip logo box, both preserving the uploaded logo's aspect ratio. The reference logo asset itself is never bundled or used. Upload the logo from the selected company's Company Settings; invoices never borrow another company's branding when their own logo is blank.
- Verify company invoice behavior with `php artisan test --compact tests/Feature/CompanySettingsTest.php tests/Feature/InvoiceDesignTest.php`.

### Accounts and Ledger

- Accounts
- Customer Payments
- Supplier Payments
- Expense Categories
- Expenses
- Transaction Ledger

Accounts are part of the Finance cluster. Their canonical route is `/admin/finance/accounts`; the former `/admin/accounts` entry redirects there. Expenses and the hidden Expense Categories/Transaction Ledger support pages also use the `/admin/finance/...` route prefix.

Money behavior:

- Account balance = opening balance + ledger inflow - ledger outflow.
- Customer payments create ledger entries with direction `in`.
- Supplier payments create ledger entries with direction `out`.
- Expenses create ledger entries with direction `out`.
- Expense Number is generated automatically by the `Expense` model, is hidden on create/edit forms, and cannot be changed after creation.
- Overpayments are blocked.
- Supplier payments and expenses are blocked if account balance would become negative.
- Transaction Ledger is intended as read-only history.

### Permanent Finance Accounts

- The Accounts UI no longer exposes Opening Balance. New manually created accounts start from the database default of zero, and `Current Balance` is presented simply as `Balance`.
- Every company automatically owns three permanent, read-only system accounts:
  - `Inventory Value` (`inventory`) = current non-variant product stock × cost price, plus active variant stock × variant cost price (falling back to the parent product cost).
  - `Customer Due` (`customer_due`) = the total of all positive customer current balances for that company.
  - `New Shipment` (`shipment`) = the total value of distinct purchases linked to planned/booked/shipped/in-transit/customs shipments. Received and cancelled shipments are excluded.
- System account balances are calculated live from company data; they are not ledger-operated cash/bank accounts.
- In `All Companies` mode, the Accounts list displays one aggregated set of the three permanent accounts instead of repeating one set per company. Selecting a specific company returns to that company's three rows and company-specific live balances.
- System accounts cannot be edited, deleted, bulk-selected, or selected in Voucher, Fund Transfer, Customer Payment, Supplier Payment, Expense, or legacy Fund Source account fields.
- The system-only `inventory`, `customer_due`, and `shipment` types are displayed in the Accounts list but are not offered on the New Account form.

Important files:

```text
app/Models/Account.php
app/Services/AccountBalanceService.php
app/Filament/Resources/Accounts/
database/migrations/2026_07_30_000000_add_permanent_system_accounts.php
tests/Feature/PermanentSystemAccountsTest.php
```

### Vouchers and Fund Transfers

- Canonical Vouchers route: `/admin/finance/vouchers`.
- Voucher forms select an `Account` directly for every transaction type, including inventory purchases.
- Fund Source is retained only as legacy data compatibility; it has no admin navigation or registered Filament page.
- Voucher Type offers Credit Voucher, Debit Voucher, and Fund Transfer on the create form.
- Credit Voucher uses a focused receipt form only: Receiving Account, Amount, Confirmed Via, Transaction / Reference ID, optional Order Invoices, Customer Account, and Notes & Attachments.
- Customer Account is optional for a Credit Voucher. If neither Customer Account nor Order Invoice is selected, approval records a direct account inflow without creating a Customer Payment.
- Order Invoices is an optional searchable multi-select. It searches customer name, customer phone, invoice number, and numeric invoice ID.
- When a customer is selected, invoice search results are limited to that customer. Server-side validation rejects mixed-customer invoices and invoices belonging to anyone other than the selected customer; when Customer Account is blank, the selected invoices supply the customer automatically.
- Credit Voucher invoice associations are stored in `order_voucher`; the legacy `vouchers.order_id` retains the first selected invoice for backward compatibility.
- On Debit Voucher, selecting `Inventory Purchase` uses the field order `Transaction Type → Purchase Number → Account → Amount`. Purchase Number is searchable: opening it lists only the latest 5 `draft` purchases, while search can find older draft purchase numbers. `received` (completed) and `cancelled` purchases are excluded in both the UI and server-side submission validation.
- Voucher forms do not expose a Payment Method field; downstream payment records continue to use the existing `other` fallback when no legacy method value is present.
- On Debit Voucher, selecting `Refund` requires a searchable Order Invoice. Only confirmed/completed invoices are eligible; search supports customer name, customer phone, invoice number, and numeric invoice ID. The selected invoice also supplies the voucher's customer reference.
- Voucher forms expose one `Notes` field backed by the existing `purpose` column. The redundant `remarks` field is no longer shown on Voucher or Fund Transfer forms; legacy stored remarks remain untouched.
- Voucher form fields do not display helper/instruction text beneath the controls.
- Selecting Fund Transfer shows `From Account`, `To Account`, and `Transaction Cost` in the same Voucher section; transaction-specific voucher fields, including the Debit Refund `Order Invoice`, are hidden and not required.
- Submitting that form creates a pending `FundTransfer`, not an invalid third value in the persisted credit/debit voucher enum.
- Fund Transfers appear as a native Filament table on the Vouchers list page for history and approval.
- Authorized users create transfers from the Voucher form and approve or reject them from the embedded table.
- On approval, the destination receives the transfer amount. The source is debited by the transfer amount plus `transaction_cost`; a non-zero cost creates a separate `fund_transfer_cost` ledger entry for audit visibility.
- Existing transfers remain compatible because `transaction_cost` defaults to `0.00`.
- The former standalone Fund Transfer resource has no registered Filament page.

Important files:

```text
app/Filament/Resources/Vouchers/VoucherResource.php
app/Filament/Resources/Vouchers/Pages/ListVouchers.php
app/Filament/Resources/Vouchers/Widgets/FundTransfersWidget.php
app/Filament/Resources/Accounts/AccountResource.php
app/Services/VoucherService.php
app/Services/FundTransferService.php
app/Http/Controllers/Admin/LegacyAdminClusterRedirectController.php
database/migrations/2026_07_29_000000_add_transaction_cost_to_fund_transfers_table.php
database/migrations/2026_07_29_010000_create_order_voucher_table.php
```

Verification:

```bash
php artisan test --filter=AdminNavigationClustersTest
php artisan test --filter=AccountingRulesTest
php artisan test --filter=VoucherWorkflowTest
```

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

The Reports page uses native Filament filter inputs, tabs, metric sections, and a dynamic Filament table. CSV and PDF exports are table header actions, empty results use the table empty state, and the Blade view contains no page-local CSS.

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

### Lead/CRM Module

Tracks business inquiries (Facebook/WhatsApp/phone/walk-in) and converts them to customers and orders.

Flow:

- `Lead` (status: new → contacted → quoted → won/lost) with follow-up activities (`LeadActivity`).
- `Quotation` (+ `QuotationItem`) with auto QT-number (`GeneratesSequentialNumber`), reactive item repeater, auto total recalculation.
- Public, unauthenticated quotation page: `GET /quotation/{quotationNumber}` (`quotation.public`) with a WhatsApp share action in the admin table.
- `LeadConversionService` (`app/Services/Crm/`) converts Lead → Customer (phone-deduplicated, idempotent) and accepted Quotation → draft Order (`source = crm`); stock/totals/balances flow through the existing Order lifecycle.
- `quotations:mark-expired` runs daily at 00:30 to expire past-`valid_until` sent quotations.
- All three company-owned models (`Lead`, `Quotation`, `QuotationItem`) use `BelongsToCompany` and are covered by `MultiCompanyIsolationTest`.

Admin resources live under the "CRM" navigation group: `app/Filament/Resources/Leads/`, `app/Filament/Resources/Quotations/`.

Conversation Inbox and chat-to-order:

- `ConversationChannel` is the company-owned source of truth for WhatsApp Cloud API / Messenger credentials. Its encrypted `access_token`/`app_secret`, Phone Number ID or Page ID, separate WABA ID, callback verification, WABA subscription, last connection test/webhook/inbound/outbound times, and sanitized latest error are managed from **CRM → Chat Channels**. An existing channel cannot be reassigned to another company because its conversations and credentials are tenant-bound. Storefront WhatsApp reminders should select the same active company channel; the older storefront token/Phone Number ID fields remain only as a migration-safe fallback.
- Meta Graph calls use `MetaGraphService` (`app/Services/Meta/`) and `META_GRAPH_API_VERSION` (currently `v25.0`). Access tokens are bearer headers, never query parameters or diagnostic payloads. Non-idempotent message sends are not automatically retried; media downloads accept HTTPS Meta CDN hosts only and enforce `META_MAX_MEDIA_BYTES`. Outbound root-relative catalog images are expanded with the public `APP_URL`; loopback/private-IP media is omitted with a text-only fallback so Meta never receives an unusable local URL.
- Webhook endpoint `GET|POST /webhooks/meta` (`MetaWebhookController`) accepts Meta's dotted handshake parameters and verifies `X-Hub-Signature-256` against the exact raw bytes. Every WhatsApp entry/change is routed by Phone Number ID plus WABA ID (Messenger by Page ID) to its own company. The core incoming message/status, dedupe, unread count, and channel timestamps are persisted synchronously before returning `200`; media copying, AI follow-ups, and read receipts are placed on the durable queue after core persistence and run with their own company context.
- Outgoing replies via `ConversationMessengerService` are archived as `sending` before Meta is called, then become `sent` or a persistent, sanitized `failed` bubble that staff can retry atomically. `sent → delivered → read/played` updates do not regress. WhatsApp free-form messages are blocked outside the 24-hour customer-service window (72 hours for CTWA); manual/phone activity remains internal-only.
- `MarkConversationReadJob` queues WhatsApp read receipts so opening a chat never waits on Meta. Incoming media is copied from Meta's expiring CDN into company-private storage by `DownloadConversationMediaJob`; declared and transferred sizes are capped before a large response can exhaust the worker.
- Filament **Inbox** (`app/Filament/Pages/Inbox.php` and `resources/views/filament/pages/inbox.blade.php`) uses actual channel tabs, URL-backed search/status/unread/assignment filters, paginated conversations, newest-50 chronological message loading, and repeated bottom synchronization after chat open, reload/navigation, layout changes, and lazy media so the latest bubble stays visible. The desktop Conversations pane persists its expanded/collapsed preference; collapsed mode follows Filament's sidebar pattern with customer profile icon buttons and unread badges while the thread flexibly fills the released width. It also provides preserved scroll when loading history, contained anywhere-wrapping bubbles, compact clickable product thumbnails, reply/internal-note modes, failed-message retry, product order links, assignment/status/AI controls, company-aware currency/timezone, channel health, and a responsive list/thread/details layout. It polls only while visible. Super Admin, Manager, and Sales Staff can use it; Inventory Staff and Accountant cannot read private CRM conversations. Custom roles use `crm.view` and `crm.manage`.
- Chat order links: `ChatOrderLink` token links (`GET|POST /o/{token}`, throttled + honeypot, 7-day default expiry). Submission creates a draft Order (`source = chat`) through the existing lifecycle, converts Lead → Customer, locks the link, and archives a confirmation message to the thread.

WhatsApp Cloud setup and recovery checklist:

1. In **CRM → Chat Channels**, enter the correct company, Phone Number ID, WABA ID, Meta App Secret, shared webhook verify token, and a permanent System User access token with `whatsapp_business_messaging` and `whatsapp_business_management`.
2. In Meta, set the displayed HTTPS callback URL, complete **Verify and Save**, enable the WhatsApp `messages` webhook field, and keep the app in Live mode. Callback verification and WABA app subscription are separate requirements.
3. Save the ERP channel, then run **Test & Subscribe**. Callback verification plus a successful `/{WABA-ID}/subscribed_apps` POST shows `Configured`; only a real received customer message shows `Inbound confirmed`. Meta does not expose an API that proves the dashboard's `messages` checkbox, so that item still needs manual confirmation.
4. Send a real customer message and confirm **Last Webhook** and **Last Inbound** update before troubleshooting the Inbox. A valid callback with neither timestamp usually means the WABA/app or `messages` field is not subscribed; a webhook timestamp without an inbound timestamp points to payload/signature/processing diagnostics.
5. Core ingest is synchronous, but production should keep `QUEUE_CONNECTION=database` or `redis` and a supervised queue worker running for media, AI, and read-receipt follow-ups. A stopped worker no longer hides the core text message, but those follow-up tasks will remain pending.

Focused verification:

```bash
php artisan test --compact tests/Feature/MetaMessagingReliabilityTest.php tests/Feature/ConversationIngestTest.php tests/Feature/ConversationChannelResourceTest.php tests/Feature/InboxPageTest.php
```

### Investor / Mudarabah Module

The company-scoped Mudarabah module lives under **Investments** in the Filament admin panel:

- **Projects** (`InvestmentProject`) hold the deal/purchase link, trade-cycle dates, target, status, and configurable profit split. The current default is Investor 40%, Channel Partner 10%, Company 50%. The form shows a live total with a success/error callout, blocks submission unless the split is exactly 100%, and the model repeats the same financial guard.
- Every Create/Edit Investment Project field has a Filament-default information icon. Its accessible tooltip and modal explain what to enter and whether the field affects funding progress, annualized reporting, or settlement calculations.
- **Investors** (`Investor`) hold legal identity and optional channel-partner assignment. Once assigned, a channel partner can only be changed by a Super Admin and a written reason is required and audited.
- Project lists show a Filament funding badge with the invested/target amount and percentage. Project View also shows the current funding percentage.
- **Investments** belong to a project and investor, record receipt details, and currently enforce at most one channel partner per project. Each project investment row shows its security-record count and has a direct **View** action. Signed contracts, security cheque, and guarantor data are managed from that investment view; contract files use company-private storage and the authenticated download route `investor-security-instruments.contract`. The hidden investment resource sends its breadcrumb back to the Investment Projects list instead of requiring a nonexistent index page.
- **Direct Costs** (`ProjectCostItem`) are itemized as landed cost or local expense and may link to an existing purchase. The project cost table is grouped by category and shows category subtotals plus a grand total. Project View and Settlement View show Total Landed Cost, Total Local Expense, and Total Direct Cost separately. Settlement cost is always calculated from these items; ordinary business overhead is not entered here.
- **Settlement** is available only for a closed project and only to a Super Admin. `SettlementService` locks the project, blocks duplicate/loss settlements, calculates investor payouts by invested-capital ratio, and writes immutable confirmed amounts. Channel share is paid only for the proportion of capital referred by the project's single channel partner; any unallocated channel share is added to Company Net.
- Investor payouts aggregate multiple deposits by the same investor. The rounding remainder is assigned to the final investor so the payout schedule equals the configured investor pool exactly. Payout recipient/bank details may be completed before **Mark as Paid**.
- Permissions: `investments.view` (Manager and Accountant by default), `investments.manage` (Manager), `investments.settle` and `investments.manage_channel_partner` (Super Admin through wildcard permission).
- Core models are registered with `AuditObserver`; settlement also writes a dedicated `project_settled` event containing request IP/user-agent through `AuditLogService`.
- The financial regression suite includes the signed Shearing Machine example: BDT 6,199,942 landed cost + BDT 255,500 local expense, BDT 544,558 net profit, and BDT 217,823.20 investor pool at 40%.

Important files:

```text
database/migrations/2026_07_27_120000_create_investor_mudarabah_tables.php
app/Models/InvestmentProject.php
app/Models/Investor.php
app/Models/Investment.php
app/Models/ProjectSettlement.php
app/Services/Investment/SettlementService.php
app/Filament/Clusters/Investments.php
app/Filament/Resources/InvestmentProjects/
app/Filament/Resources/Investors/
app/Filament/Resources/InvestmentRecords/
app/Filament/Resources/ProjectSettlements/
app/Http/Controllers/Admin/InvestorContractDownloadController.php
tests/Feature/InvestmentSettlementTest.php
```

Focused verification:

```bash
php artisan test --compact tests/Feature/InvestmentSettlementTest.php tests/Feature/MultiCompanyIsolationTest.php tests/Feature/PhaseSixPermissionsTest.php tests/Feature/AdminNavigationClustersTest.php
```

AI auto-reply (grounded-only assistant):

- `AiReplyService` (`app/Services/Crm/`) — tool-calling agent (Anthropic or OpenAI via `AiLlmClient`, per-company encrypted settings in `companies.settings['ai']` via `AiSettingsService`, admin page "AI Assistant"). Dispatched by `AiAutoReplyJob` after every incoming text message.
- Tools: `lookup_product`, `lookup_faq` (`CompanyFaq` model + FAQs resource, keyword shortcut answers without any LLM call), `lookup_delivery_charge`, `create_order_link`, `escalate_to_human`, `submit_reply`.
- Guardrails: replies only via structured `submit_reply`; confidence threshold; code-level price cross-check (every ৳ amount must match a tool result — "Never Echo"); complaint/negotiation/human-request keywords skip AI entirely; max consecutive AI replies then handoff; human reply pauses AI for 24h (`human_handled_until`); first AI message self-identifies. Handoff sets conversation to `pending` + database notification.
- CTWA Free Entry Point: conversations opened from a Click-to-WhatsApp ad (`entry_point = 'ctwa_ad'`, parsed from the webhook `referral`) get a 72h messaging window instead of 24h; the Inbox badge shows hours left.
- AI messages saved with `generated_by = 'ai'`, `ai_confidence`, and `ai_meta` (tool trace + token usage). LLM calls are always `Http::fake()`d in tests.

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
- Sidebar collapses to icons on desktop and expands on hover/focus
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
FILESYSTEM_DISK=local
LIVEWIRE_TEMPORARY_UPLOAD_DISK=livewire-tmp
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
/app/storage
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
php artisan test --filter=OrderStatusWorkflowTest
php artisan test --filter=AccountsAndPaymentsTest
php artisan test --filter=ReportsTest
php artisan test --filter=PhaseSixPermissionsTest
php artisan test --filter=MultiCompanyIsolationTest
php artisan test --filter=CourierIntegrationTest
php artisan test --filter=ReleaseNotesTest
php artisan test --filter=AppUpgradeTest
php artisan test --filter=ReleaseNotificationTest
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
22. Open the avatar menu and confirm Profile Settings edits only the current user.
23. Simulate a new deployment ID and confirm Upgrade App appears directly above Sign out without reloading the open page.
24. Confirm the bell receives one app-update notification, its Release Notes action works, and Upgrade App clears the matching unread state.

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
- Pathao, RedX, and E-Courier live API clients are implemented and tested against faked responses; live sandbox verification still requires the owner's merchant credentials in each provider's encrypted credential fields.
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
App deployment identity   app/Support/AppDeployment.php
App update orchestration  app/Services/AppUpdateService.php
App updater browser code  resources/js/app-updater.js
Seeder                    database/seeders/DatabaseSeeder.php
```

## 13. Documentation Rule

Every feature change should update:

- `PROJECT_GUIDE.md` for current behavior and implementation notes
- `ERP_PHASE_ROADMAP.md` for phase status, done criteria, and future work

Do not leave business-critical behavior only in code or conversation history.
