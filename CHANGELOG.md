# Changelog

All notable production changes to Business Dashboard are documented here.

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
