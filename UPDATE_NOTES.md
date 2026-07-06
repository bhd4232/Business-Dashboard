# Update Notes

This file is a working update log for changes that may become commits. Use it to decide what a pending commit contains before approving any `git commit` or push.

## 2026-07-06 - Fix Android app status bar overlap

Reason:

- App loaded successfully on a real device (JDK 21 fix worked), but the admin panel's header was hidden behind/overlapping the phone's status bar (clock, signal, battery icons), making the nav menu, search, and company selector hard to tap.

Changed files:

- `capacitor.config.json` — added `plugins.StatusBar` config (`overlaysWebView: false`, `style: DARK`, `backgroundColor: #000000`) so the WebView renders below the status bar instead of underneath it.
- `android/app/src/main/assets/capacitor.config.json` — manually mirrored the same change (local `npx cap sync android` hit the known Windows EPERM issue again; CI's own `cap sync` on the Linux runner will regenerate this correctly from the root config on every build regardless).
- `CHANGELOG.md` — added `[1.6.3]` patch entry.
- `tests/Feature/ReleaseNotesTest.php` — bumped assertion to v1.6.3.

Notes:

- No PHP behavior changed; verified `php artisan test --filter=ReleaseNotesTest` (3 passed, 23 assertions).
- Requires a new APK build (this is a native config change, not a web deploy) — rebuild via GitHub Actions and reinstall on the test device to see the fix.

Commit status: Not committed. Commit and push require explicit user approval.

## 2026-07-06 - Fix build-android CI: JDK 21 required by Capacitor 7

Reason:

- After the gradlew permission fix, the `build-android` job progressed further but failed with `error: invalid source release: 21` compiling `capacitor-android` — Capacitor 7's Android library targets Java 21, but CI's JDK was set to 17.

Changed files:

- `.github/workflows/deploy.yml` — bumped `actions/setup-java` to `java-version: '21'`.
- `CHANGELOG.md` — added `[1.6.2]` patch entry.
- `tests/Feature/ReleaseNotesTest.php` — bumped assertion to v1.6.2.

Notes:

- Verified: `php artisan test --filter=ReleaseNotesTest` (3 passed, 23 assertions). No PHP/app behavior changed.

Commit status: Not committed. Commit and push require explicit user approval.

## 2026-07-06 - Fix build-android CI: gradlew permission denied

Reason:

- First `build-android` CI run (commit `690e70b2`) failed with `Permission denied` on `./gradlew` (exit 126). Windows checkouts don't preserve the Unix executable bit, so `android/gradlew` was committed as `100644` instead of `100755`.

Changed files:

- `android/gradlew` — restored executable bit via `git update-index --chmod=+x`.
- `.github/workflows/deploy.yml` — added `chmod +x ./gradlew` before running it in the `build-android` job, as a safety net.
- `CHANGELOG.md` — added `[1.6.1]` patch entry.
- `tests/Feature/ReleaseNotesTest.php` — bumped assertions to v1.6.1 / Patch.

Notes:

- Full suite not required to be rerun for this fix beyond `ReleaseNotesTest` (verified: 3 passed, 23 assertions) since no other app behavior changed.

Commit status: Not committed. Commit and push require explicit user approval.

## 2026-07-06 - Android app shell (Capacitor)

Reason:

- Owner wants the ERP admin panel available as a mobile app, starting with Android (storefront app deferred). Since the panel is already fully responsive and cloud-hosted, the fastest and lowest-maintenance path is a Capacitor WebView shell pointed at the live URL, rather than a separate native codebase.

Changed files:

- `capacitor.config.json` (new) — app id `com.zamzamint.erp`, loads `https://app.zamzamint.com`
- `mobile-shell/` (new) — placeholder web asset dir Capacitor requires, plus `mobile-shell/README.md` with build/setup instructions
- `android/` (new) — generated native Android project (Capacitor scaffold)
- `package.json` — added `@capacitor/core`, `@capacitor/android`, `@capacitor/app`, `@capacitor/status-bar`, `@capacitor/splash-screen`, `@capacitor/cli` (v7, matched to this machine's Node 20) and `mobile:sync` / `mobile:open` / `mobile:build` scripts
- `.github/workflows/deploy.yml` — added `build-android` CI job (Node + JDK 17, `cap sync`, `gradlew assembleDebug`, uploads the APK as a workflow artifact) so an APK can be built and downloaded without local Android Studio; also fixed this workflow's test step which ran `php artisan test --env=testing` (the same flag now forbidden by the new CLAUDE.md rule — harmless here since CI has no real demo database, but corrected for consistency)
- `CHANGELOG.md` (1.6.0 minor), `tests/Feature/ReleaseNotesTest.php` (v1.6.0)

Notes:

- This dev machine has no Android Studio/JDK installed, so no APK has been built or tested yet. The owner will install Android Studio locally to build and test.
- `npx cap sync android` hit intermittent `EPERM` errors on this machine (antivirus locking newly written files mid-copy) — the generated `android/app/src/main/assets/capacitor.config.json` was verified correct regardless; documented as a known issue in `mobile-shell/README.md`.
- Full suite re-verified after the version bump: `php artisan test` (no `--env` flag) — 213 passed (936 assertions).

Commit status: Not committed. Commit and push require explicit user approval.

## 2026-07-06 - Agent rule: tests must not touch demo data + Phase 8 verification

Reason:

- Owner asked for a hard rule that test runs never refresh/reseed the demo database. Investigating this uncovered that `php artisan test --env=testing` bypasses `phpunit.xml`'s env overrides and runs `RefreshDatabase` against the real `database/demo.sqlite` — this had already wiped the owner's demo data during a prior session. Ran `php artisan demo:refresh` to restore it.
- Also planned to build master plan Phase 8 (duplicate order / same-phone-multiple-name / high-COD-first-order detection); exploration found all three were already implemented in `CustomerRiskService` and gating courier booking via `assertCourierBookingAllowed` — just missing direct test coverage for two of the factors, and the master plan doc was stale.

Changed files:

- `CLAUDE.md` (new rule: never `--env` flag when testing; never demo:refresh/seed/migrate:fresh during testing)
- `tests/Feature/CustomerRiskTest.php` (2 new tests: `phone_multiple_names` and `recent_duplicate_order` factors)
- `business_dashboard_master_plan_v2_custom_storefront.md` (Phase 8 marked complete with detail; stale Phase 5 MOQ/tiered-pricing "unconfirmed" note corrected — it's implemented and tested)
- `CHANGELOG.md` (1.5.1 patch), `tests/Feature/ReleaseNotesTest.php` (v1.5.1)

Verification:

- `php artisan test` (no --env flag) — 213 passed (936 assertions)

Commit status: Not committed. Commit and push require explicit user approval.

## 2026-07-05 - Production Courier Monitoring & Alerting

Reason:

- Last remaining master plan Part 2 item: in production nobody would notice a broken courier API, a permanently failed webhook, or a booking stuck in transit. Adds scheduled status syncing plus persistent admin alerts.

Changed files:

- `app/Console/Commands/SyncCourierStatuses.php` (new — `couriers:sync-statuses`, per-company loop with CompanyContext, cooldown/batch-limit/failure-streak from admin-configurable provider settings, stale-booking detection)
- `app/Services/CourierAlertService.php` (new — Filament database notifications to active super admins + owning-company managers, deduped once per subject per day via cache)
- `app/Jobs/ProcessCourierWebhook.php` (`failed()` hook alerts admins after all retries are exhausted)
- `app/Filament/Widgets/CourierHealthWidget.php` (new dashboard stats), `CourierProviderResource` (Monitoring & Alerts settings section + Last Sync / Sync Failures columns)
- `app/Models/CourierProvider.php` (MONITORING_DEFAULTS + `monitoringSetting()`), `CourierBooking.php` (ACTIVE_STATUSES, `last_synced_at`)
- `app/Providers/Filament/AdminPanelProvider.php` (`->databaseNotifications()` bell)
- `bootstrap/app.php` (schedule: every 30 minutes, withoutOverlapping, onOneServer)
- Migrations: courier monitoring fields + `notifications` table
- `tests/Feature/CourierMonitoringTest.php` (new — 6 tests), `tests/Feature/ReleaseNotesTest.php` (v1.5.0), `CHANGELOG.md` (1.5.0)

Deploy notes:

- Run `php artisan migrate` (new `notifications` table is required by the admin panel bell). Scheduler + queue worker must be running.

Verification:

- `php artisan test --env=testing --filter=CourierMonitoringTest` — 6/6
- Full suite: 211 passed (934 assertions).

Commit status: Approved by owner; committed and pushed.

## 2026-07-05 - Live Pathao/RedX/E-Courier Couriers + Steadfast Balance UI

Reason:

- Master plan Part 2 remaining items. Owner asked to build the live courier clients now (API contracts researched from official docs) and show the Steadfast balance in admin; credentials stay admin-configurable so the owner plugs in merchant keys later.

Changed files:

- `app/Services/PathaoCourierClient.php`, `app/Services/RedxCourierClient.php`, `app/Services/ECourierClient.php` (new — verified endpoints: Pathao aladdin issue-token/orders/reference lists with cached bearer token; RedX v1.0.0-beta parcel/track/info/areas with API-ACCESS-TOKEN header; E-Courier order-place/track/cancel with API-KEY/API-SECRET/USER-ID headers)
- `app/Services/CourierService.php` (create/sync/normalize methods for the three couriers + shared `storeBooking`/`assertProviderUsable` helpers)
- `app/Services/Couriers/PathaoCourierAdapter.php`, `RedxCourierAdapter.php`, `ECourierAdapter.php` (now real adapters); `PendingLiveCourierAdapter.php` deleted
- `app/Filament/Resources/CourierProviders/CourierProviderResource.php` (driver-aware encrypted credential fields, sandbox helper text, Steadfast Balance action)
- `app/Filament/Resources/Orders/Tables/OrdersTable.php` (Book Pathao / Book RedX / Book E-Courier actions with courier-specific fields)
- `app/Filament/Resources/CourierBookings/CourierBookingResource.php` (sync action now covers all API drivers via CourierManager)
- `tests/Feature/LiveCourierAdaptersTest.php` (new — 5 tests), `tests/Feature/CourierIntegrationTest.php` (pending-adapter test now asserts credentials-required), `tests/Feature/ReleaseNotesTest.php` (v1.4.0)
- `CHANGELOG.md` (1.4.0), master plan Part 2 / Phase 2 / Phase 7 checkmarks

Verification:

- `php artisan test --filter="LiveCourierAdaptersTest|CourierIntegrationTest"` — 17/17
- Full suite: 205 passed (910 assertions)
- Live sandbox verification pending owner's merchant credentials.

Commit status: Approved by owner; committed and pushed.

## 2026-07-05 - Fix ReleaseNotesTest after 1.3.0 changelog entry

- Adding the 1.3.0 CHANGELOG entry changed the latest published release shown on the admin Release Notes page, so `ReleaseNotesTest` (which asserted v1.2.0 / Released 2026-06-24 as latest) failed. Updated the test to assert v1.3.0 / Released 2026-07-05. 3/3 pass.
- Commit status: Committed and pushed (follow-up to the approved 1.3.0 release-notes commit, which otherwise left the suite red).

## 2026-07-05 - Part 0 Verification + Part 1.10 Cross-Cutting Isolation Audit (route-binding isolation fix)

Reason:

- Master plan Part 0 (pre-requisite stabilization) items were still marked unverified, and Part 1.10 (queue/scheduled/export/backup isolation audit) was pending. The audit found and fixed a real cross-company data exposure bug.

Security fix (the important part):

- `SetCurrentCompany` middleware ran **after** route model binding (`SubstituteBindings`), so on implicit-binding admin routes such as `/admin/orders/{order}/pdf` the `CompanyScope` could not constrain the binding query — an authenticated staff user of company A could download company B's order PDF by guessing an order ID. Fixed in `bootstrap/app.php` with `prependToPriorityList()` so company context is bound before any route model binding resolves.

Changed files:

- `bootstrap/app.php` (middleware priority: `SetCurrentCompany` before `SubstituteBindings`)
- `tests/Feature/CrossCuttingIsolationAuditTest.php` (new — 4 regression tests: product CSV export, customer CSV export, report CSV export scoped to current company; cross-company order PDF returns 404)
- `business_dashboard_master_plan_v2_custom_storefront.md` (Part 0.1/0.2, Part 1.10, Phase 0/1 checkmarks updated with evidence)

Audit findings (no code change needed):

- Queue: the only queued job `ProcessCourierWebhook` sets `CompanyContext` from the webhook log's provider company and clears it in `finally` — correct pattern.
- Scheduled commands: `storefront:send-abandoned-cart-reminders` loops per company setting with explicit `company_id` filters; `backup:database` is whole-database by design.
- Backup: no per-company restore feature exists; downloads are permission-gated.
- Verified as already complete in code (plan doc was stale): per-product landed cost allocation (`PurchaseWorkflowService::syncLandedCosts()`), invoice + report PDF export (`OrderPdfController`, `ReportPdfController`), scheduled daily backups with restore-drill verification (`backup:database` at 02:00 + `backup:verify`), and composer.json hardening (`block-insecure: true`, `minimum-stability: stable`, dompdf pinned `^3.1`).

Verification:

- `php artisan test --filter=CrossCuttingIsolationAuditTest` — 4/4 (the order-PDF test failed before the middleware fix, proving the bug)
- Full suite: 200 passed (894 assertions)

Commit status: Not committed. Commit and push require explicit user approval.

## 2026-07-04 - Storefront Advanced Commerce: WooCommerce Import, ZiniPay Pre-order Payments, Reseller Applications, Abandoned Cart Reminders

Reason:

- Owner-confirmed business rules (via Q&A): ZiniPay gateway for online payments with COD limited to in-stock items and per-product pre-order advance percent; admin-approved resellers; automatic SMS + Meta Cloud WhatsApp abandoned-cart reminders; WooCommerce products-only import via REST API. All credentials are admin-configurable fields (owner will plug in keys later) — nothing is hardcoded.

Changed files:

- `database/migrations/2026_07_04_020000_add_woocommerce_credentials_to_storefront_settings_table.php` (new)
- `database/migrations/2026_07_04_030000_add_preorder_and_payment_support.php` (new)
- `database/migrations/2026_07_04_040000_add_reseller_fields_to_customers_table.php` (new)
- `database/migrations/2026_07_04_050000_create_storefront_cart_records_and_notification_settings.php` (new)
- `app/Models/StorefrontSetting.php`, `app/Models/Product.php`, `app/Models/Customer.php`, `app/Models/Order.php`
- `app/Models/StorefrontPayment.php`, `app/Models/StorefrontCartRecord.php` (new)
- `app/Services/WooCommerceImportService.php`, `app/Services/ZiniPayClient.php`, `app/Services/StorefrontNotificationService.php` (new)
- `app/Services/StorefrontCart.php` (persisted cart records + stable cart token)
- `app/Console/Commands/ImportWooCommerceProducts.php`, `app/Console/Commands/SendAbandonedCartReminders.php` (new)
- `app/Http/Controllers/Storefront/CheckoutController.php`, `app/Http/Controllers/Storefront/ResellerController.php` (new), `app/Http/Controllers/ZiniPayWebhookController.php` (new)
- `app/Filament/Resources/StorefrontSettings/StorefrontSettingResource.php` (ZiniPay, Abandoned Cart, WooCommerce sections)
- `app/Filament/Resources/Products/Schemas/ProductForm.php` (pre-order fields)
- `app/Filament/Resources/Customers/Schemas/CustomerForm.php`, `app/Filament/Resources/Customers/Tables/CustomersTable.php` (reseller status)
- `resources/views/storefront/` (product card/show pre-order states, checkout advance notice, success payment status, reseller apply page, footer link)
- `routes/web.php`, `bootstrap/app.php` (reseller + webhook routes, CSRF exception, hourly scheduler)
- `tests/Feature/WooCommerceImportTest.php`, `tests/Feature/StorefrontPreorderPaymentTest.php`, `tests/Feature/StorefrontResellerAndAbandonedCartTest.php` (new), `tests/Feature/MultiCompanyIsolationTest.php` (new models added to contract)

What changed:

- WooCommerce import: per-company base URL + encrypted consumer key/secret in storefront settings; `php artisan woocommerce:import-products {company-slug}` pulls published products (paged, retried), matches by SKU/slug (re-runs update, never duplicate), maps regular/sale price and first category, optionally downloads the first image. Stock intentionally stays 0 (ERP stock must come from stock movements).
- Pre-order + ZiniPay: `products.is_preorder` + `preorder_advance_percent` (per-product, default 100%); pre-order products can be ordered beyond stock; checkout computes the online advance (pre-order quantity beyond stock only) and redirects to ZiniPay hosted checkout (`/v1/payment/create`); webhook at `POST /webhooks/zinipay/{payment}` re-verifies via `/v1/payment/verify` (never trusts the webhook body) and amount-matches before marking `storefront_payments` completed. COD remains the flow for fully in-stock carts. Pre-order checkout is blocked with a clear error when online payment is not configured.
- Reseller: public `/reseller` application page (name, phone, business name, note) creates/updates a company-scoped Customer with `reseller_status = pending`; approved customers keep `approved` on re-application; admin approves from the Customer form's new Reseller section; Customers table shows a reseller badge. Price gating for approved resellers is deferred until customer login exists (documented).
- Abandoned carts: cart activity now also persists to `storefront_cart_records` (stable session token, converted on successful checkout, phone captured at checkout attempt); hourly `storefront:send-abandoned-cart-reminders` sends one SMS (generic GET-gateway URL template with placeholders) and one WhatsApp template message (Meta Cloud API) per stale cart, then marks it reminded.

Verification:

- `php artisan migrate --force` (4 migrations)
- `npm run build`
- New test files 12/12 passed; isolation contract extended with `ProductCarousel`, `StorefrontPayment`, `StorefrontCartRecord`
- Full suite `php artisan test` (196 passed, 882 assertions)

Commit status: Not committed. Commit and push require explicit user approval.

## 2026-07-04 - Storefront B2B: Tiered Pricing, MOQ, and Customer Due Visibility

Reason:

- Master plan Part 4.6 B2B UX items 1, 2, and 4 were the last storefront features implementable without external credentials: per-product tiered wholesale pricing, minimum order quantity enforcement, and showing a customer's current due on the account orders page.

Changed files:

- `database/migrations/2026_07_04_010000_add_moq_and_tier_prices_to_products_table.php` (new)
- `app/Models/Product.php`
- `app/Filament/Resources/Products/Schemas/ProductForm.php`
- `app/Services/StorefrontCart.php`
- `app/Http/Controllers/Storefront/AccountOrdersController.php`
- `resources/views/storefront/partials/product-card.blade.php`
- `resources/views/storefront/products/show.blade.php`
- `resources/views/storefront/cart/show.blade.php`
- `resources/views/storefront/account/orders.blade.php`
- `tests/Feature/StorefrontB2bTest.php` (new)
- `business_dashboard_master_plan_v2_custom_storefront.md`
- `PROJECT_GUIDE.md`

What changed:

- New nullable `products.moq` and `products.tier_prices` (JSON `{min_qty, price}` rows) columns; both optional so existing products behave exactly as before.
- `Product::effectiveMoq()`, `normalizedTiers()`, and `priceForQuantity()` helpers; tier prices override the sale price at matching quantities for non-variant lines only (variant lines keep their own variant price).
- Admin Product form gains a collapsible "Wholesale (B2B)" section with MOQ input and a tier-price repeater.
- `StorefrontCart` clamps add/update quantities up to the MOQ (0 still removes; stock cap wins when stock is below MOQ) and prices non-variant lines with `priceForQuantity()`, so tier pricing flows into checkout order items unchanged.
- Product page shows a "Wholesale pricing" range table (with the base price as the first row) and a "Minimum order" badge; the quantity input starts at and enforces the MOQ; product cards show an MOQ badge and quick-add uses the MOQ.
- Account orders page shows a "Current due" banner with the customer's `current_balance` (only when > 0, and only when the searched phone matched storefront orders in the current company — same access rule as order history).

Verification:

- `php artisan migrate --force`
- `npm run build`
- `php artisan test --filter=StorefrontB2bTest` (4/4 passed)
- Full suite `php artisan test` (184 passed, 822 assertions)

Commit status: Not committed. Commit and push require explicit user approval.

## 2026-07-04 - Storefront Quick Reorder

Reason:

- Master plan Part 4.6 B2B UX item 5 (Quick Reorder) was the last remaining pure-code storefront feature. Customers can now re-add a previous storefront order's items to their cart in one click from the account orders page.

Changed files:

- `app/Http/Controllers/Storefront/AccountOrdersController.php`
- `routes/web.php`
- `resources/views/storefront/account/orders.blade.php`
- `tests/Feature/StorefrontReorderTest.php` (new)
- `business_dashboard_master_plan_v2_custom_storefront.md`
- `PROJECT_GUIDE.md`

What changed:

- New `POST /account/orders/{orderNo}/reorder` route (production domain) and `POST /storefront/{company-slug}/account/orders/{orderNo}/reorder` (local preview), both named routes.
- `AccountOrdersController::reorder`/`reorderPreview` validate that the submitted phone matches the order's customer (same matching rules as the order-history lookup), that the order belongs to the current storefront company, and that it is a storefront-source order — otherwise 404.
- Available products/variants from the order are added to the session cart via the existing `StorefrontCart` service (stock capping applies); discontinued/inactive items are skipped. A flash status reports how many items were added, and the customer is redirected to the cart.
- `Reorder` button added next to `Track order` on each order card in `account/orders.blade.php`, posting the searched phone as a hidden field.
- Master plan Part 4 pending list refreshed: carousel/variants marked done (they were completed in commit 2626e5f0 but the doc still listed the carousel as pending), Quick Reorder marked done, and tiered pricing/MOQ/due-visibility explicitly listed as blocked on business decisions.

Verification:

- `php artisan test --filter=StorefrontReorderTest` (2/2 passed)
- Full suite `php artisan test` (180 passed, 807 assertions)

Commit status: Not committed. Commit and push require explicit user approval.

## 2026-07-04 - Variant Stock Movement CI Fix

Reason:

- GitHub CI failed on `ProductVariantTest::test_confirmed_order_deducts_variant_stock_and_restores_on_cancel` because variant sale movements were still validated against the parent product movement ledger. Variable products keep stock from active variants, so the parent ledger can be empty even when the selected variant has stock.

Changed files:

- `app/Services/StockMovementService.php`
- `PROJECT_GUIDE.md`
- `UPDATE_NOTES.md`

What changed:

- Stock movements with `product_variant_id` now validate projected stock against the selected variant's current stock plus the signed movement delta.
- Invalid variant/product combinations fail with a form validation message.
- The project guide now documents that variant stock movements are validated against variant stock, not parent product ledger stock.

Verification:

- `php artisan test --env=testing --filter=ProductVariantTest` passed: 6 tests, 19 assertions.
- `php artisan test --env=testing` passed: 178 tests, 798 assertions.

Commit status: User approved commit and push on 2026-07-04.

## 2026-07-04 - Storefront Variant Cart and CI Stabilization

Reason:

- Latest storefront merchandising work added variable product/cart behavior and exposed a CI-only failure where Storefront Settings domain sync tests failed under `php artisan test --env=testing`.

Changed files:

- `app/Filament/Resources/Orders/Schemas/OrderForm.php`
- `app/Filament/Resources/StorefrontSettings/Pages/CreateStorefrontSetting.php`
- `app/Filament/Resources/StorefrontSettings/Pages/EditStorefrontSetting.php`
- `app/Http/Controllers/Storefront/CartController.php`
- `app/Http/Controllers/Storefront/CheckoutController.php`
- `app/Models/StockMovement.php`
- `app/Services/OrderWorkflowService.php`
- `app/Services/StockMovementService.php`
- `app/Services/StorefrontCart.php`
- `database/migrations/2026_07_03_050000_add_product_variant_id_to_stock_movements.php`
- `database/seeders/DemoDataSeeder.php`
- `resources/views/storefront/cart/show.blade.php`
- `resources/views/storefront/checkout/show.blade.php`
- `resources/views/storefront/checkout/success.blade.php`
- `resources/views/storefront/products/show.blade.php`
- `tests/Feature/PhaseFourAdminPagesTest.php`
- `tests/Feature/ProductVariantTest.php`
- `PROJECT_GUIDE.md`

What changed:

- Storefront carts now keep product variants as separate lines using product + variant keys.
- Variable product pages can submit multiple variant quantities in one add-to-cart request.
- Checkout stores `product_variant_id`, `variant_label`, variant price, and variant cost on order items.
- Sale stock movements can reference a variant and update/restore variant stock with signed movement deltas.
- Variable products keep parent stock synced from active variant stock without the product stock ledger overwriting it.
- Demo data now includes richer storefront sample products, variant products, pages, and courier provider records.
- Storefront Settings domain sync tests now set Livewire `data.*` state directly and the create/edit pages merge raw form state before syncing company domain fields, stabilizing `php artisan test --env=testing`.

Verification:

- `php artisan test --env=testing --filter=PhaseFourAdminPagesTest` passed.
- `php artisan test --filter=PhaseFourAdminPagesTest` passed.
- `php artisan test --env=testing` passed: 176 tests, 787 assertions.

Commit status: Pending commit and push requested by user.

## 2026-07-03 - Storefront "Top-Class Reference Pattern" (Part 4.6 remaining items)

Reason:

- Master plan Part 4.6 listed 7 accepted UI patterns from the SkyBuy/MoveOn reference analysis (mega menu, dual banner, header chat+call button, curated carousel, "how to order" explainer, sister-company cross-promotion, mobile bottom nav) as not yet implemented. This pass implements everything except the curated carousel, which needs a new Filament resource and was intentionally left out of scope for this change.

Changed files:

- `database/migrations/2026_07_03_010000_add_dual_banner_and_phone_to_storefront_settings_table.php` (new)
- `app/Models/StorefrontSetting.php`
- `app/Filament/Resources/StorefrontSettings/StorefrontSettingResource.php`
- `resources/views/storefront/layout.blade.php`
- `resources/views/storefront/home.blade.php`
- `business_dashboard_master_plan_v2_custom_storefront.md`
- `PROJECT_GUIDE.md`

What changed:

- Added `storefront_settings.banner_image_mobile` (nullable) and `storefront_settings.phone_number` (nullable) columns.
- Header mega menu: hover dropdown under "Categories" listing the company's active categories that have available products.
- Header call button: `tel:` link next to WhatsApp, shown only when `phone_number` is set.
- Dual banner: `home.blade.php` hero uses `<picture>`/`<source media="(max-width: 639px)">` to show `banner_image_mobile` on phones and the existing desktop banner otherwise.
- "How to order" explainer: static 4-step icon+text section on the homepage between the hero and category grid.
- Sister-company cross-promotion: footer section linking to other active companies with a published storefront and a domain.
- Mobile bottom nav: fixed `sm:hidden` bar (Home/Category/Cart with badge/Account); `<main>` and `<footer>` get bottom spacing so content isn't hidden under it.
- `StorefrontSettingResource` form: new "Call support number" field and a second, separate "Banner image (mobile)" upload alongside the existing desktop banner upload field.

Verification:

- `php artisan migrate --force`
- `npm run build`
- `php artisan test --filter=StorefrontFoundationTest` (20/20)
- `php artisan test --filter=PhaseFourAdminPagesTest` (3/3)
- Full suite `php artisan test` (169/169 passed, 767 assertions)
- Manual verification via local preview: mega menu dropdown, mobile bottom nav rendering (confirmed via computed `display: grid` at 375px width since the screenshot tool was unavailable this session), category/product data on the homepage.

Commit status: Not committed. Commit and push require explicit user approval.

## 2026-07-03 - Storefront Visual Redesign and Admin Homepage Content Settings

Reason:

- The storefront was functionally complete but visually read as a template rather than a considered Shopify-quality ecommerce site: no real light/dark toggle (dark: classes only followed OS preference), heavy `font-black`/`rounded-full`-everywhere styling with little hierarchy, no quick-add hover interaction or quantity stepper, no sort/filter on the product listing, no related products, and hero copy hardcoded in the blade file instead of admin-editable.

Changed files:

- `resources/css/app.css`
- `resources/views/storefront/layout.blade.php`
- `resources/views/storefront/home.blade.php`
- `resources/views/storefront/partials/product-card.blade.php`
- `resources/views/storefront/products/index.blade.php`
- `resources/views/storefront/products/show.blade.php`
- `resources/views/storefront/cart/show.blade.php`
- `resources/views/storefront/checkout/show.blade.php`
- `resources/views/storefront/checkout/success.blade.php`
- `resources/views/storefront/account/orders.blade.php`
- `resources/views/storefront/track/show.blade.php`
- `resources/views/storefront/pages/show.blade.php`
- `app/Http/Controllers/Storefront/ProductIndexController.php`
- `app/Http/Controllers/Storefront/ProductShowController.php`
- `app/Http/Controllers/Storefront/PreviewController.php`
- `app/Models/StorefrontSetting.php`
- `app/Filament/Resources/StorefrontSettings/StorefrontSettingResource.php`
- `database/migrations/2026_07_03_000000_add_hero_and_theme_fields_to_storefront_settings_table.php`
- `PROJECT_GUIDE.md`
- `UPDATE_NOTES.md`

What changed:

- Added a real class-based dark mode toggle: `@custom-variant dark` in `app.css`, an inline pre-paint script plus a header sun/moon button in `layout.blade.php` that toggles `<html>.dark` and persists the choice to `localStorage`, defaulting to a new `storefront_settings.theme_mode` (`system`/`light`/`dark`) on first visit. Implemented in vanilla JS since no Alpine.js is loaded in the storefront Vite bundle.
- Replaced the `font-black` + `rounded-full`-everywhere visual language across all storefront views with a restrained hierarchy (semibold/medium weights, `rounded-lg`/`rounded-xl` components, thinner gray borders) closer to an actual Shopify storefront.
- Added a quick-add hover button on product cards, a vanilla-JS quantity stepper (`+`/`-`) on the product detail and cart pages, a sticky buy box on product detail, and a sticky order summary on cart/checkout.
- Added product listing sort (`?sort=price_asc|price_desc`, default newest) and category quick-filter chips.
- Added a "You may also like" related-products rail on product detail (same category, excludes current product, limit 4).
- Added admin-editable homepage hero fields (`hero_heading`, `hero_subheading`, `hero_cta_label`) and a `theme_mode` default select to the Storefront Settings form, under a new "Homepage Content" section; blank values fall back to the previous hardcoded copy.

Verification:

- `php artisan migrate --force` applied the new `storefront_settings` columns.
- `npm run build` passed.
- `php artisan test --filter=StorefrontFoundationTest` passed (20 tests).
- `php artisan test --filter=PhaseFourAdminPagesTest` passed (3 tests).
- Manually verified via a local `php artisan serve` preview: home, product listing (with sort/filter chips), product detail (stepper + related products), cart, and checkout in both light and dark mode, and at mobile (375px) and desktop (1440px) widths.

Commit status:

- Not committed. Commit and push require explicit user approval.

## 2026-07-03 - Storefront Settings Form Synchronization

Reason:

- Storefront Settings list showed domain and readiness fields, but the edit page did not expose every underlying setting, creating a synchronization gap for admins.
- A duplicate domain such as `zamzamgadgetbd.com` could still hit the database unique constraint when assigned to the wrong company from the Storefront Settings form.

Changed files:

- `app/Filament/Resources/StorefrontSettings/StorefrontSettingResource.php`
- `app/Filament/Resources/StorefrontSettings/Pages/CreateStorefrontSetting.php`
- `app/Filament/Resources/StorefrontSettings/Pages/EditStorefrontSetting.php`
- `tests/Feature/PhaseFourAdminPagesTest.php`
- `PROJECT_GUIDE.md`
- `UPDATE_NOTES.md`

What changed:

- Added a Filament default `Domain and Launch Readiness` section to the Storefront Settings form.
- Exposed `Storefront Domain` and `Domain verified` in Storefront Settings create/edit forms.
- Added read-only readiness, missing setup, visible products, and published pages summaries in the edit form.
- Synchronized `company_domain` and `company_domain_verified` form fields back to `companies.domain` and `companies.domain_verified` on create/save.
- Added tests that verify the edit form shows the same readiness-related options as the list and that saving Storefront Settings updates the company domain fields.
- Added duplicate-domain validation before saving company domain fields so admins get a form error instead of a 500.

Verification:

- `php artisan test --filter=PhaseFourAdminPagesTest` passed.
- `php artisan test --filter=StorefrontFoundationTest` passed.

Commit status:

- Not committed. Commit and push require explicit user approval.

## 2026-07-03 - Storefront Admin Launch Dashboard

Reason:

- The storefront site polish existed on the public side, but admins still needed a Filament-default dashboard surface to see whether each storefront is ready to launch.

Changed files:

- `app/Filament/Resources/StorefrontSettings/StorefrontSettingResource.php`
- `tests/Feature/PhaseFourAdminPagesTest.php`
- `PROJECT_GUIDE.md`
- `UPDATE_NOTES.md`

What changed:

- Added launch-readiness columns to Storefront Settings using Filament default table UI.
- Added missing setup visibility for publish/domain/logo/banner/SEO/WhatsApp/pages/products.
- Added visible product count and published page count per storefront.
- Added domain verified visibility.
- Added default Filament record actions for Preview, Open Site, and Pages.
- Updated admin page test coverage for the new storefront dashboard surface.
- Updated `PROJECT_GUIDE.md` with the admin launch dashboard behavior and verification command.

Verification:

- `php artisan test --filter=PhaseFourAdminPagesTest` passed.
- `php artisan test --filter=StorefrontFoundationTest` passed.

Commit status:

- Not committed. Commit and push require explicit user approval.

## 2026-07-02 - Storefront Part 4 Site Polish

Reason:

- WooCommerce migration is intentionally deferred until after the storefront site work is complete.
- Part 4 still needed customer-facing polish, launch readiness cleanup, and storefront verification without changing admin/dashboard UI.

Changed files:

- `resources/views/storefront/layout.blade.php`
- `resources/views/storefront/home.blade.php`
- `resources/views/storefront/partials/product-card.blade.php`
- `resources/views/storefront/products/show.blade.php`
- `resources/views/storefront/cart/show.blade.php`
- `resources/views/storefront/checkout/success.blade.php`
- `tests/Feature/StorefrontFoundationTest.php`
- `PROJECT_GUIDE.md`
- `UPDATE_NOTES.md`

What changed:

- Added SEO/Open Graph/Twitter metadata support from storefront settings.
- Added banner-image hero support and removed decorative gradient/orb styling from the storefront hero.
- Made the storefront header more mobile-safe by truncating long company names and hiding lower-priority actions on small screens.
- Added footer WhatsApp contact visibility for mobile users.
- Replaced internal/roadmap wording in cart and homepage copy with customer-facing storefront copy.
- Added clearer out-of-stock labels and disabled button text on product cards and product detail pages.
- Improved checkout success action wrapping for mobile screens.
- Added storefront test assertions for the public announcement and Open Graph metadata.
- Updated `PROJECT_GUIDE.md` with the polished storefront behavior and local smoke-check routes.

Verification:

- `php artisan test --filter=StorefrontFoundationTest` passed.
- `npm run build` passed.
- Local HTTP smoke checks returned `200` for `/storefront`, `/storefront/main-company/products`, `/storefront/main-company/cart`, `/storefront/main-company/track`, and `/storefront/main-company/account/orders`.
- Browser connector was unavailable due an environment metadata error, so screenshot-based visual QA could not be completed in this run.

Commit status:

- Not committed. Commit and push require explicit user approval.

## 2026-06-29 - Agent Rule: Filament Default Dashboard UI

Reason:

- Dashboard/admin UI should stay consistent and maintainable by using Filament's default UI system.

Changed files:

- `AGENTS.md`
- `UPDATE_NOTES.md`

What changed:

- Added a project rule that dashboard/admin UI must use Filament default components and patterns only.
- Clarified that even custom dashboard modules or elements should be built with Filament default UI rather than custom-styled dashboard components.

Verification:

- Documentation-only change; no tests required.

Commit status:

- Not committed. Commit and push require explicit user approval.

## 2026-06-29 - Courier Pending Live Adapter Guardrail

Reason:

- Part 4 storefront verification was complete, so the next safe Part 7 courier step was to prepare Pathao, RedX, and E-Courier adapter boundaries without pretending live API integrations are ready.
- Official merchant API credentials, request field mappings, and sandbox/live response samples are still required before enabling live Pathao, RedX, or E-Courier booking/sync/webhook flows.

Changed files:

- `app/Services/CourierManager.php`
- `app/Services/Couriers/PendingLiveCourierAdapter.php`
- `app/Services/Couriers/PathaoCourierAdapter.php`
- `app/Services/Couriers/RedxCourierAdapter.php`
- `app/Services/Couriers/ECourierAdapter.php`
- `tests/Feature/CourierIntegrationTest.php`
- `PROJECT_GUIDE.md`

What changed:

- Added explicit pending live adapters for Pathao, RedX, and E-Courier.
- Wired those adapters into `CourierManager`.
- Pending providers now fail live booking/sync/balance/webhook operations with a clear setup message instead of an ambiguous missing-adapter error.
- Added a feature test that confirms pending live providers resolve but reject booking until official API details are supplied.
- Updated `PROJECT_GUIDE.md` with the new courier adapter status and verification note.

Verification:

- `php artisan demo:refresh` passed.
- `php artisan test --filter=StorefrontFoundationTest` passed.
- `npm run build` passed.
- `php artisan test --filter=CourierIntegrationTest` passed.
- `php artisan test` passed: 167 tests, 737 assertions.

Commit status:

- Not committed. Commit and push require explicit user approval.
