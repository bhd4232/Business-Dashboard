# Changelog

All notable production changes to Business Dashboard are documented here.

## [1.10.0] - 2026-07-13

**Release type:** Security

Hardening pass resolving the findings from the code audit (`CODE_AUDIT_REPORT.md`).

### Security

- Storefront order tracking now requires the customer's phone number in addition to the order number. Order numbers are sequential and guessable, so the tracking page previously let anyone enumerate any order's items and totals; a matching phone is now required as a second factor (a mismatch is indistinguishable from "not found").
- The phone-only order history page no longer displays the customer's outstanding balance. A phone number is a weak secret, so the financial balance is no longer exposed there (order history itself is unchanged).

### Fixed

- Order (and purchase) numbers could collide under concurrent creation — two simultaneous checkouts could read the same sequence and fail one order on the unique index. Number generation now retries automatically on a duplicate and mints the next free value.
- Stock recomputation no longer loads a product's entire movement history into memory; it now sums in the database, which is materially faster for high-volume products.

### Changed

- Added a production environment template (`.env.production.example`) and a "Production Hardening" section to `docs/deployment.md` spelling out the three must-change settings for production: `APP_ENV=production`/`APP_DEBUG=false`, a real database instead of SQLite, and a non-`sync` queue driver with a running worker.

### Technical Notes

- New `App\Models\Concerns\GeneratesSequentialNumber` overrides `performInsert` to retry on a UNIQUE violation of the document-number column (database-agnostic; works on SQLite and MySQL/Postgres). Applied to `Order` and `Purchase`.
- New `App\Http\Controllers\Storefront\Concerns\MatchesCustomerPhone` centralizes the +880/0/formatting-tolerant phone match shared by the tracking and order-history lookups.
- `CompanyScope` and `SetCurrentCompany` now document the context contract: `none()` is fail-closed, `all()`/cleared are unscoped. `MultiCompanyIsolationTest::test_company_context_boundary_states` guards those semantics. The cleared-is-unscoped default was left in place deliberately — the storefront relies on it for guest route-model binding plus per-record ownership checks, so making it fail-closed is a larger, separate change.
- `CustomerBlacklist`'s deliberate omission of `CompanyScope` (it supports a global `company_id = NULL` entry) is now documented in-code so it isn't "fixed" by mistake.
- Minor maintainability: named the pre-order stock ceiling constant (`StorefrontCart::PREORDER_STOCK_CEILING`), replaced inline fully-qualified class references in `StorefrontCart` with imports, and routed the seeder admin password through `config('app.seed_admin_password')` instead of a raw `env()` call.
- New form-layer test `OrderFormTest` exercises the Order create screen through Livewire (the class of gap that hid the earlier Purchase-save crash).

## [1.9.4] - 2026-07-12

**Release type:** Critical Fix Update

### Fixed

- Creating or editing a Purchase with at least one item always failed with an error on "Save changes", and the item disappeared again after reloading the page — nothing was ever actually saved.

### Technical Notes

- Root cause: `purchase_items.allocated_cost` and `landed_unit_cost` are `NOT NULL` columns with a DB-level default of `0`, but those two values are computed after save (`PurchaseWorkflowService::syncLandedCosts()`); the Filament repeater form fields for them were read-only display fields that were never populated, so the initial insert explicitly bound `NULL` for both columns. In SQLite (and most databases) an explicit `NULL` in an insert bypasses the column's `DEFAULT`, so every insert hit the `NOT NULL` constraint and rolled back.
- Fix: added `->dehydrated(false)` to the `allocated_cost` and `landed_unit_cost` fields in `app/Filament/Resources/Purchases/Schemas/PurchaseForm.php`, so they're excluded from the saved payload entirely — the DB default (`0`) applies on insert, and the existing post-save sync then fills in the real computed values, same as it already did on every subsequent update.

## [1.9.3] - 2026-07-11

**Release type:** Patch

### Changed

- The Customer Success section's five separate sidebar pages (Risk Profiles, Blacklists, Risk Reviews, Risk Events, Risk Settings) are now one "Customer Success" sidebar entry with the five pages as tabs across the top, same as the earlier Courier tab consolidation.

### Technical Notes

- New `App\Filament\Clusters\CustomerSuccess` groups `CustomerRiskProfileResource`, `CustomerBlacklistResource`, `CustomerRiskReviewResource`, `CustomerRiskEventResource`, and the `CustomerRiskSettings` page — only `$cluster` set on each (replacing `$navigationGroup`), no resource/page logic changed.
- Routes moved from `/admin/customer-risk-profiles` etc. to `/admin/customer-success/customer-risk-profiles` etc. (including `/admin/customer-success/customer-risk-settings`); updated the 5 hardcoded URLs in `tests/Feature/CustomerRiskTest.php` accordingly.
- The mobile tab-dropdown's "renders behind the sticky header" bug (fixed for the Courier cluster in v1.9.1 via `.fi-dropdown-panel { z-index: 30 }`) already applies here automatically since that CSS rule is generic to all Filament dropdown panels — verified in browser, no extra fix needed.

## [1.9.2] - 2026-07-11

**Release type:** Minor Version Update

### Added

- Active users now get a notification bell alert whenever a new version is deployed to the server — it fires automatically once the deployed CHANGELOG shows a new version, no manual step needed, and points to Release Notes for details.

### Fixed

- On mobile, the gap between the header search box and the profile avatar was too wide (a visual leftover from hiding the notification bell there). Tightened it and shifted the avatar in from the screen edge.

### Technical Notes

- New `App\Console\Commands\NotifyLatestRelease` (`php artisan release:notify-deploy`), scheduled every 5 minutes in `bootstrap/app.php`. Compares `App\Support\AppRelease::latestPublished()['version']` (the CHANGELOG's top `## [x.y.z]` entry — the same source the Release Notes page already reads) against `AppSetting` key `release.last_notified_version`. On the very first run ever (no stored baseline), it only records the current version and does not notify, so existing installs aren't retroactively spammed about every past release the moment this feature ships. On every later run, a version change sends a `Filament\Notifications\Notification` to all active users via `sendToDatabase()` and updates the stored baseline.
- No deploy-script changes needed — detection is purely CHANGELOG-content-based (already deployed via `git pull`), not tied to an `APP_VERSION` env bump.
- `.fi-topbar-end`'s `column-gap` reduced from the default `1rem` to `0.375rem` in the existing `@media (max-width: 640px)` block (same `STYLES_AFTER` render hook used for the sticky header and the mobile notifications-in-profile-menu change) — closes the gap and pulls the avatar left by the same ~10px.

**Release type:** Patch

### Changed

- The Courier section's four separate sidebar pages (Providers, Bookings, Status Logs, Webhook Logs) are now one "Courier" sidebar entry with the four pages as tabs across the top of the page — click a tab to switch, no more four cluttered sidebar links.

### Fixed

- Fixed the mobile view of that tab bar (which collapses into a "Providers ▾" dropdown on narrow screens): opening it showed an empty panel because it rendered underneath the sticky page header instead of above it, hiding all four options.
- On mobile, the header notification bell is no longer a separate cramped icon — it now appears as a "Notifications" item inside the profile/avatar dropdown menu (next to Sign out and the theme switcher), and the avatar has 10px of right padding so it's no longer flush against the screen edge. Desktop is unaffected — the bell stays in the header as before.

### Technical Notes

- New `App\Filament\Clusters\Courier` (Filament's built-in Cluster feature) groups the four existing resources (`CourierProviderResource`, `CourierBookingResource`, `CourierStatusLogResource`, `CourierWebhookLogResource`) under one nav item with `SubNavigationPosition::Top` tabs — no resource logic changed, only `$cluster` set instead of `$navigationGroup`.
- Routes moved from `/admin/courier-*` to `/admin/courier/courier-*` (Filament's standard cluster URL prefixing); updated the two hardcoded URLs in `tests/Feature/CourierIntegrationTest.php` accordingly. No other code referenced the old paths.
- `AdminPanelProvider` now calls `->discoverClusters(in: app_path('Filament/Clusters'), for: 'App\Filament\Clusters')`.
- The mobile-dropdown bug's root cause was this project's own existing custom CSS making the page header `position: sticky; z-index: 20` (added in an earlier session for the sticky-header effect); the tab dropdown's panel had no explicit z-index so it rendered behind that header. Fixed by giving `.fi-dropdown-panel` (Filament's dropdown panel class, used by this tab dropdown and others like the table column manager) `z-index: 30` in the same `STYLES_AFTER` render hook.
- New `resources/views/filament/partials/mobile-notifications-menu-item.blade.php`, injected via a `PanelsRenderHook::USER_MENU_PROFILE_AFTER` hook — a real dropdown menu item (with unread-count badge) that dispatches Filament's own `open-modal` / `database-notifications` event, i.e. it opens the exact same notifications panel the topbar bell does, not a duplicate. Only visible below 640px (`.zz-mobile-notifications-item`); the topbar bell (`.fi-topbar-database-notifications-btn`) is hidden at that width instead.

## [1.9.0] - 2026-07-09

**Release type:** Minor Version Update

### Added

- Orders now get a dynamic "Shipping Fee" automatically: the customer's address is matched against the company's new Shipping Zones keyword lists (ERP Settings → Inside/Outside/Suburb Areas) to detect a zone, and that zone's fee from the company's first active courier provider's "Set Delivery Fees" is pulled in and folded into the order total. Staff can still override it manually on the order form; if no zone matches or no courier is configured yet, it defaults to BDT 0 and the field shows a note to set it manually.
- Added "Shipping Zones" to the ERP Settings page — comma-separated area/keyword lists per zone (Inside, Outside, Suburb), owner-managed, no hardcoded city list.

### Removed

- Removed the Filament "Welcome / Sign out" account widget from the admin Dashboard (visual clutter, redundant with the topbar user menu which already has sign-out).
- Removed the duplicate "Courier Delivery Cost" section on the Courier Provider form — it was an unused, visually identical duplicate of "Set Delivery Fees" (which is the section now wired up to actually affect order totals, see above).

### Technical Notes

- New `orders.shipping_zone` (nullable string) and `orders.shipping_fee` (decimal, default 0) columns; `total_amount` calculation (`OrderWorkflowService::sync()`) now includes `shipping_fee`.
- New `App\Services\ShippingFeeService::determineZone()` does a case-insensitive substring match of the customer's address against `companies.settings['shipping_zones']`; `feeFor()` combines that with the company's first active `CourierProvider`'s `settings.delivery_fees[zone]`. Both the admin Order form (live, recomputed when the customer changes) and the `Order::creating` model event (covers storefront checkout orders, which don't go through the Filament form) call into this service.
- `CompanySettingsService`/`CompanySettings` page persist the three area lists under `companies.settings.shipping_zones`, alongside the existing per-company `settings` JSON fields (dark logo, date format).

## [1.8.1] - 2026-07-08

**Release type:** Patch

### Fixed

- Fixed the Android app occasionally showing `net::ERR_SOCKET_NOT_CONNECTED` (and similar transient network errors) instead of recovering on its own, most noticeably when switching between Wi-Fi and mobile data or toggling a SIM's data connection. The WebView now retries a failed page load up to 3 times (2.5s apart) before giving up, and shows a friendly "Connection Problem" page with a Retry button if all retries fail.
- The app now also listens for connectivity returning (e.g. after airplane mode is turned off) and automatically reloads on its own if it's stuck on the error page — no manual retry needed in that case.

### Technical Notes

- New `ResilientBridgeWebViewClient` (extends Capacitor's own `BridgeWebViewClient`, not a plain `WebViewClient`, so plugin bridging and local-server URL interception keep working) retries only main-frame failures for a specific set of transient `net::ERR_*` codes (connection reset/refused, network changed, connection closed, socket not connected, name not resolved, internet disconnected) — a real 404/500 or SSL error from the app itself is left alone.
- New `NetworkMonitor` wraps `ConnectivityManager.registerNetworkCallback` to detect connectivity returning; added the `ACCESS_NETWORK_STATE` permission it needs.
- New local `android/app/src/main/assets/error.html` is the friendly error page (shown via `file:///android_asset/error.html`, never a network request itself, so it always renders even fully offline); its Retry button reloads the app's real configured server URL (read from `capacitor.config.json` via `CapConfig.getServerUrl()`, not hardcoded).
- `MainActivity` also now enables `domStorageEnabled`/`LOAD_DEFAULT` cache mode explicitly on the WebView.
- Could not run a local Android build to verify compilation (no JDK/Android SDK in this environment) — relies on the existing `build-android` CI job (GitHub Actions) to confirm it compiles; verify the next CI run before considering this fully verified.
- Optional follow-up not applied here (server-side, out of scope for this app-only fix): increasing Coolify/Traefik's `keepalive_timeout` if the error is still frequent after this fix ships.

## [1.8.0] - 2026-07-08

**Release type:** Minor Version Update

### Added

- Added a per-company admin dashboard color: each Company record now has its own "Dashboard Color" (Company Management → Companies → edit), applied panel-wide (sidebar, buttons, links) on every page — not just resource list pages — and switches instantly when the owner changes the active company in the topbar, no page reload or redeploy needed.
- Added a global auto-reload after any Filament save/create/delete action that doesn't already redirect (e.g. editing a record that stays on the same page, deleting a table row, saving a Settings page) — the page now always reflects the freshly persisted state instead of showing stale form data.

### Changed

- Renamed the "Company Settings" page to "ERP Settings" (same page, same URL) — it's now the single place for business profile and branding (logo, contact info, currency, timezone). The dashboard color lives on the Company record instead (see above), since it's a per-company property like the company's name or logo, not a global app setting.
- The "User Roles" page no longer has its own sidebar entry; it's reached via a new "Manage Roles" button on the Users page (still fully accessible, just not cluttering the main nav).

### Technical Notes

- New `companies.dashboard_color` column (hex, default `#F59E0B` — the previous static Amber), editable via a `ColorPicker` on `CompanyResource`. Deliberately a separate column from the existing storefront branding color (`StorefrontSetting.theme_color`) — the dashboard color is chosen for admin-panel readability, not customer-facing branding.
- New `App\Services\DynamicColorService` expands a single hex color into the full Filament 50–950 shade ladder (delegates to Filament's own OKLCH-based `Color::generatePalette()` rather than reimplementing HSL math, so the output matches how Filament renders any other `Color::*` palette).
- `AdminPanelProvider` injects the current company's shades as CSS custom property overrides (`--primary-50` … `--primary-950`) via a `HEAD_END` render hook that reads `CompanyContext` fresh on every request — this is why it applies to every page (list, Settings, Backups, Release Notes, Reports, etc.) and updates immediately on company switch, unlike the earlier attempt which only set Filament's static `->colors()` config once. "All Companies" mode keeps the static Amber fallback, consistent with the existing All-Companies write-action safeguard.
- The auto-reload listens for Filament's `notificationsSent` browser event (dispatched whenever a notification is flashed without an accompanying redirect) and calls `window.location.reload()`.

## [1.7.1] - 2026-07-08

**Release type:** Patch

### Fixed

- Fixed visiting `/` on the app's own domain (e.g. `app.zamzamint.com` — loaded by both the browser and the Android app shell) showing the generic marketing homepage instead of the admin panel. It now redirects to `/admin`, which shows the login page when signed out and the dashboard when signed in.

### Technical Notes

- New `ADMIN_APP_HOST` env var (`config('app.admin_host')`) names this host explicitly; when unset (local/testing), `/` keeps showing the marketing homepage as before. Production `.env` should set `ADMIN_APP_HOST=app.zamzamint.com`.

## [1.7.0] - 2026-07-08

**Release type:** Minor Version Update

### Added

- Added external cross-courier fraud check (Part 3.8 of the master plan): staff can look up a phone number's delivery success/cancel history on Pathao, Steadfast, and RedX merchant panels directly from the Order form ("Courier Fraud Check" button), using the `shahariar-ahmad/courier-fraud-checker-bd` package. Results now show inline next to the button (color-coded by the review threshold) instead of only in a notification toast.
- Storefront checkout runs the same check silently in the background (queued job, never visible to the customer and never blocks checkout); if the cross-courier success ratio is below a configurable threshold, a manager review is automatically requested using the existing courier-booking approval gate.
- Added a new "External courier success ratio review threshold" setting on the Risk Rule Settings page (default 50%).
- Added optional "External Fraud Check (Merchant Panel Login)" credentials on the Courier Provider form (Pathao/Steadfast/RedX) — separate from the existing booking API credentials, since this feature logs into the courier's own website rather than calling their booking API.

### Fixed

- Fixed the fraud-check button always reporting "no history found" for phone numbers stored in `+880`/`880` international format — the underlying package only accepts the local `01XXXXXXXXX` format. Numbers are now normalized before lookup.
- Fixed the manual "Courier Fraud Check" button returning a stale cached result (up to 24h old) after a courier's credentials were just added or changed. The manual button now always bypasses the cache; only the silent storefront background check still uses the 24h cache to limit repeated merchant-panel logins.

### Technical Notes

- Every real external lookup (not cache hits) is logged to the existing `customer_risk_events` table for audit trail; a result is only cached when at least one courier actually answered, so a temporary failure or missing-credentials result never sticks for 24h.
- A courier with no fraud-check credentials configured is silently skipped — this can never block order creation or courier booking.
- Pathao's official booking API doesn't offer a fraud-check endpoint; this feature logs into the courier's merchant website (same approach Steadfast/RedX use), which is inherently more fragile than a documented API and may need adjustment if a courier changes its website.

## [1.6.4] - 2026-07-06

**Release type:** Patch

### Fixed

- Fixed the Android app header still overlapping the status bar on Android 15 devices after the `[1.6.3]` StatusBar plugin fix. Root cause: the app targets SDK 35 (Android 15), which force-enables edge-to-edge layout system-wide — the StatusBar plugin's legacy overlay flags have no effect at that API level. Added `android:windowOptOutEdgeToEdgeEnforcement="true"` to the app theme to opt back out of the forced edge-to-edge layout.

### Technical Notes

- This opt-out attribute is only honored on Android 15 (API 35); Google has said it may stop being honored on a future Android version, at which point the fix will need to move to CSS safe-area-inset padding in the panel's layout instead.

## [1.6.3] - 2026-07-06

**Release type:** Patch

### Fixed

- Fixed the Android app header overlapping the phone's status bar, making the nav menu, search, and company selector hard to tap. Configured the `StatusBar` plugin (`overlaysWebView: false`) so the WebView content starts below the status bar instead of underneath it.

## [1.6.2] - 2026-07-06

**Release type:** Patch

### Fixed

- Fixed the `build-android` CI job failing with `error: invalid source release: 21` — Capacitor 7's Android library requires Java 21 to compile, but CI was set up with JDK 17. Bumped the CI job's JDK to 21 (Temurin).

## [1.6.1] - 2026-07-06

**Release type:** Patch

### Fixed

- Fixed the `build-android` CI job failing with "Permission denied" on `./gradlew` — Windows checkouts don't preserve the executable bit, so the committed `android/gradlew` lost it. Restored the executable bit on the file and added a `chmod +x ./gradlew` step before running it in CI as a safety net against this happening again.

## [1.6.0] - 2026-07-06

**Release type:** Minor Version Update

### Added

- Added a Capacitor-based Android app shell that loads the live admin panel (`https://app.zamzamint.com`) in a native WebView — no separate mobile codebase, login/sessions work exactly as in a browser, and web deploys show up in the app immediately.

### Technical Notes

- New `android/`, `mobile-shell/`, and `capacitor.config.json` (target URL lives here). See `mobile-shell/README.md` for build instructions.
- Added a `build-android` job to the GitHub Actions CI workflow that builds a debug APK in the cloud and uploads it as a downloadable artifact — no local Android Studio/JDK required. Local Android Studio setup remains documented as an alternative for developers who want it.
- Fixed the CI workflow's test step, which ran `php artisan test --env=testing` — the same flag now documented as forbidden, since it bypasses `phpunit.xml`'s environment overrides. Harmless in CI (no real demo database exists on the runner), but corrected for consistency.
- Storefront customer-facing app packaging is intentionally deferred to a later phase.

## [1.5.1] - 2026-07-06

**Release type:** Patch

### Added

- Added explicit test coverage confirming same-phone-multiple-name and recent-duplicate-order risk factors trigger correctly (`CustomerRiskTest`).

### Technical Notes

- Verified as complete in code: duplicate order detection, same-phone-multiple-name detection, high-COD-first-order detection, and manager/owner approval workflow before courier booking — all were already implemented in `CustomerRiskService` and gated by `assertCourierBookingAllowed`; master plan Phase 8 was marked incomplete in error and is now corrected.
- Also corrected a stale Phase 5 note: MOQ/tiered-pricing B2B enforcement is implemented and tested (`StorefrontB2bTest`), not unconfirmed.
- Added an agent rule: test runs must never refresh or reseed the demo/development database; always run `php artisan test` with no `--env` flag so `phpunit.xml`'s isolated in-memory database is used.

## [1.5.0] - 2026-07-05

**Release type:** Minor Version Update

### Added

- Added automatic courier status syncing: a scheduled `couriers:sync-statuses` command runs every 30 minutes and pulls delivery status updates from Steadfast, Pathao, RedX, and E-Courier for all active bookings, per company.
- Added persistent admin alerts (notification bell in the admin panel) when a courier provider's status sync keeps failing, when a courier webhook cannot be processed after all retries, or when bookings sit without a final delivery status for too long.
- Added a Courier Health dashboard widget: active deliveries, stale bookings, failed webhooks in the last 24 hours, and providers with sync errors.
- Added per-provider monitoring settings (stale-booking alert days, sync failure alert threshold, sync batch limit, sync cooldown) plus last-sync time and failure count visibility on the Courier Providers list.

### Technical Notes

- New `notifications` table and courier monitoring columns — run `php artisan migrate` on deploy (scheduler must already be running for the new command).
- Alerts go to active super admins and the owning company's managers, deduplicated to at most one alert per subject per day.

## [1.4.0] - 2026-07-05

**Release type:** Minor Version Update

### Added

- Added live Pathao courier integration: token-based authentication with caching, order booking from the Orders list, delivery status sync, and webhook status mapping.
- Added live RedX courier integration: parcel booking, tracking sync, and webhook status mapping.
- Added live E-Courier integration: order placement, tracking sync, cancel support, and webhook status mapping.
- Added driver-specific credential fields on the Courier Provider form (Pathao client ID/secret/username/password, RedX access token, E-Courier API key/secret/user ID) — all encrypted, owner plugs keys in later; sandbox/staging base URLs supported.
- Added a Steadfast "Balance" action on the Courier Providers list that shows the current merchant balance.
- The courier booking status sync action now works for all API providers (Steadfast, Pathao, RedX, E-Courier).

### Technical Notes

- API contracts were verified against official documentation; bookings without configured credentials fail with a clear validation message instead of silent errors.
- Live sandbox verification is still pending until merchant credentials are provided.

## [1.3.0] - 2026-07-05

**Release type:** Minor Version Update

### Added

- Added the custom storefront: per-company domain resolution, published pages, product listing/detail, cart, checkout that creates ERP orders, order tracking, and phone-verified customer order history with reorder and due-balance visibility.
- Added storefront merchandising: admin-managed product carousels, product variants in cart/checkout, and Filament-managed storefront settings (branding, theme, pages, footer links).
- Added B2B wholesale support: per-product MOQ and quantity-tiered pricing applied automatically in the cart and shown on product pages.
- Added pre-order support with per-product advance percent; cash-on-delivery remains for in-stock quantities only, while pre-order quantities require an online advance through the ZiniPay gateway (server-side verified webhook, amount-matched, never trusts the webhook body).
- Added reseller applications on the storefront with admin approval workflow on the Customer record.
- Added abandoned-cart recovery: carts persist with checkout contact, and an hourly command sends SMS (configurable GET-gateway URL template) and Meta Cloud WhatsApp template reminders.
- Added WooCommerce products-only import via the REST API (`woocommerce:import-products`), matching by SKU/slug so re-runs update instead of duplicate.
- All gateway/notification/import credentials are encrypted, per-company, admin-configurable settings — nothing is hardcoded.

### Security

- Fixed a cross-company data exposure: company context middleware ran after route model binding, so implicit-binding admin routes (for example the order PDF download) could resolve another company's record for an authenticated staff user. Company context is now bound before route model binding, with regression tests covering CSV/report exports and cross-company PDF access.

### Technical Notes

- Verified as complete in code: per-product landed cost allocation, invoice and report PDF export, scheduled daily database backups with restore-drill verification, and composer.json hardening (block-insecure, stable minimum-stability, pinned dompdf).
- Cross-cutting company-isolation audit (queue jobs, scheduled commands, exports, backups) completed and documented in the master plan.

## [1.2.0] - 2026-06-24

**Release type:** Minor Version Update

### Added

- Added explainable Customer Success and Risk Score profiles, courier success/return/cancel ratios, immutable order check history, and idempotent delivery events.
- Added global/company blacklist management and booking-time blacklist enforcement.
- Added Customer and Order risk badges plus booking-form risk visibility.
- Added Customer Success dashboard alerts, risk review approvals, risk event visibility, and configurable rule settings.

### Technical Notes

- Added disposable SQLite backup restore verification through `php artisan backup:verify`.
- Bulk Main Company data reassignment is intentionally not planned; new records will be entered under the correct company and rare historical exceptions reviewed manually.

## [1.1.0] - 2026-06-23

**Release type:** Minor Version Update

### Added

- Added backup-gated, dry-run company data reassignment tooling with transactional child-record migration.
- Added complete company-owned model isolation contract coverage.
- Added courier provider contracts, manager, Manual and Steadfast adapters, API retry/timeouts, signed idempotent queued webhooks, operational log resources, booking actions, and report aggregates.
- Added company-scoped shipment and container tracking inside each Purchase record, with status-aware draft planning and read-only received/cancelled history.

### Technical Notes

- Live Pathao, RedX, and E-Courier adapters require their official current API contracts and merchant credentials.
- Production company-data reassignment must use a reviewed mapping and pre-migration backup.

### Fixed

- Fixed Filament select, textarea, and checkbox components failing behind an HTTPS reverse proxy because lazy-loaded JavaScript URLs were generated with `http://`.

## [1.0.0] - 2026-06-21

**Release type:** Major Version Update

### Added

- Added visible app release metadata with version, release type, release date, and source commit support.
- Added an admin Release Notes page so deployed changes are visible inside the app.
- Added release policy documentation for major, minor, patch, security, hotfix, and maintenance releases.

### Fixed

- Fixed Top Business Performers cards so light mode uses light backgrounds while dark mode remains preserved.

### Technical Notes

- Added production update safety documentation covering backups, migrations, rollback, and forbidden destructive commands.
- Production updates must create a database backup before running migrations.
- Routine production deploys must not run seeders or destructive migration commands against live data.
