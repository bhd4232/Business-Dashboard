# ZamZam ERP — Code Audit Report

**Date:** 2026-07-13
**Reviewer:** Claude (Fable 5) code audit
**Scope:** Full application code — `app/`, `routes/`, `bootstrap/`, `config/`, `resources/views/storefront`, `tests/`
**Baseline:** `main` @ `cc84bd2e`, framework Laravel 12 / Filament 4.11 / PHP 8.2, **234 tests passing (1017 assertions).**

> **Remediation status (2026-07-13):** All findings addressed — shipped as CHANGELOG `[1.10.0]` (Security). Test suite now **238 passing (1042 assertions)**. Each finding below is annotated with a **✅ Resolution** line. H-1/H-2 are ops/deploy actions and were addressed with a production template + docs (the app defaults are intentionally unchanged so local/demo keeps working). M-4 was hardened with documentation + a guard test rather than a runtime behaviour change, because the storefront guest flow relies on the current cleared-context semantics — see its resolution note.

---

## 1. Executive Summary

The codebase is **well-architected and disciplined**. The multi-company isolation pattern (`BelongsToCompany` + `CompanyScope` + a contract test), encrypted credential storage, HMAC webhook verification, server-side payment verification, and a broad feature-test suite all reflect a mature engineering approach. No critical vulnerability (RCE, SQL injection, auth bypass, credential leak) was found.

The issues below are mostly **production-hardening and scalability** concerns rather than functional bugs. The two most important are **using SQLite as the production database** and the **default `sync` queue driver** — both are fine for a single-user demo but will cause lock contention and slow request handling under real multi-user load. There is also a **storefront order-enumeration data-exposure** issue worth closing.

**Overall health: Good.** Address the High items before scaling to concurrent production traffic.

| Severity | Count | Theme |
|----------|-------|-------|
| 🔴 High | 2 | Production DB engine, queue driver |
| 🟠 Medium | 5 | Order-number race, order-tracking enumeration, sync-context reads, phone-only order access, heavy save chains |
| 🟡 Low | 6 | Debug flag in example env, blacklist scope, in-PHP stock sums, magic numbers, form-layer test gap, minor style |

---

## 2. What's Done Well (keep doing this)

- **Multi-company isolation contract** — `MultiCompanyIsolationTest::test_every_company_owned_model_uses_the_company_scope_contract` enforces that every company-owned model registers the scope. Excellent guardrail.
- **Middleware ordering** — `SetCurrentCompany` is deliberately pinned before `SubstituteBindings` in `bootstrap/app.php`, so route-model binding is already company-scoped. This is a subtle, correct decision.
- **Credential encryption** — `StorefrontSetting` and `CourierProvider` cast secret bags as `encrypted:array`; keys never live in plaintext columns.
- **Webhook hardening** — `CourierWebhookController` verifies an HMAC signature with `hash_equals` before doing any work; `ZiniPayWebhookController` never trusts the callback body and re-verifies server-side **and** checks the amount matches. Both endpoints are rate-limited and CSRF-exempt only where required.
- **Idempotency** — courier webhooks dedupe on `delivery_id` via `firstOrCreate`, so retried deliveries don't double-process.
- **Strong admin password policy** — `AdminPassword::rule()` requires 12+ chars, mixed case, numbers, symbols.
- **Guardrails on destructive user actions** — cannot deactivate/delete your own account or drop the last active super admin (`User::booted`).

---

## 3. Findings

### 🔴 HIGH

#### H-1 — SQLite is the default production database
**Where:** `config/database.php:19` (`'default' => env('DB_CONNECTION', 'sqlite')`), `.env.example:31`
SQLite allows only **one writer at a time**. This is a multi-company ERP with a public storefront (checkout, cart persistence) writing concurrently with admin panel activity, courier webhooks, and scheduled sync jobs. Under real concurrency this surfaces as `SQLSTATE: database is locked` errors and failed writes. The heavy per-save sync chains (see M-5) widen the write window and make lock collisions more likely.
**Impact:** Intermittent write failures and request errors under concurrent load; poor scalability.
**Fix:** Use MySQL 8 or PostgreSQL in production. Keep SQLite only for local/demo/testing. Document the required `DB_CONNECTION` in deploy docs and make the production `.env` explicit.
**✅ Resolution:** Added `.env.production.example` (`DB_CONNECTION=mysql`) and a "Production Hardening (must-do)" section in `docs/deployment.md`; `.env.example` now points to the production template. App default left as SQLite so local/demo/tests are unaffected — switching the engine is a deploy/ops action.

#### H-2 — `sync` queue driver runs external I/O inside the request
**Where:** `.env.example:67` (`QUEUE_CONNECTION=sync`); jobs `ProcessCourierWebhook` (5 retries, external HTTP) and `CheckExternalCourierFraudJob` (logs into up to three courier merchant panels).
With `sync`, `dispatch()` runs the job inline. `CheckExternalCourierFraudJob::dispatch(...)->afterCommit()` fires **during the checkout request**, so a customer's checkout blocks on slow merchant-panel logins — exactly what the job's own docblock says it exists to avoid. Courier webhook processing also runs inline before the 202 response.
**Impact:** Slow/blocked checkout and webhook responses; retry/backoff config on `ProcessCourierWebhook` is effectively dead under `sync`.
**Fix:** Use `database` (config default already) or Redis in production with a running worker (`php artisan queue:work`). Ensure the deploy provisions a worker process.
**✅ Resolution:** `.env.production.example` sets `QUEUE_CONNECTION=database` and the deploy doc's hardening section requires a supervised `queue:work` worker. Deploy/ops action; app default unchanged.

---

### 🟠 MEDIUM

#### M-1 — Order number generation has a race condition
**Where:** `app/Models/Order.php:163` `nextOrderNumber()` (and the analogous logic in `Purchase`).
The next sequence is computed as `SELECT max(order_number) ... + 1` with no lock. Two concurrent checkouts can read the same max and generate an identical `order_number`, which then violates the `unique` index (`orders` migration) and fails one order. The storefront checkout wraps order creation in a transaction, but the number is still computed without `lockForUpdate`.
**Impact:** Occasional checkout failure under concurrency; harder to reproduce on SQLite (H-1 serializes writes) but a real defect on MySQL/Postgres.
**Fix:** Generate the number inside the transaction with `lockForUpdate`, or use a dedicated per-company counter row, or catch the unique violation and retry.
**✅ Resolution:** New `App\Models\Concerns\GeneratesSequentialNumber` overrides `performInsert` to retry on a UNIQUE violation of the number column, regenerating from the advanced max each attempt (DB-agnostic). Applied to `Order` and `Purchase`; covered by `SequentialNumberConcurrencyTest`.

#### M-2 — Storefront order tracking is enumerable
**Where:** `OrderTrackController::storefrontOrder()` (`/track/{orderNo}`) and `resources/views/storefront/track/show.blade.php`.
Lookup is by `order_number` only, with no ownership check. Order numbers are sequential and guessable (`PREFIX-YYYYMMDD-0001`). The page reveals **order items, quantities, line totals, order total, and outstanding due amount**. No direct PII (name/address) is shown, but competitors or bad actors can enumerate a store's order volume and contents.
**Impact:** Business-data leakage / enumeration.
**Fix:** Require a second factor to view details (e.g. phone number matching the order, or a signed/opaque tracking token issued at checkout) instead of a raw sequential number.
**✅ Resolution:** Tracking now requires the order's customer phone as a second factor; a mismatch returns the search form with a generic "couldn't find" message (indistinguishable from not-found). `storefrontOrder()` returns `?Order` and matches via the shared `MatchesCustomerPhone` trait. Tests in `StorefrontFoundationTest` updated to assert the order number alone no longer reveals details.

#### M-3 — Order history & reorder gated on phone number alone
**Where:** `AccountOrdersController::ordersForPhone()` / `reorderIntoCart()`.
Anyone who supplies a phone number sees all of that phone's storefront orders **and the customer's current outstanding balance** (`customerDue`). A phone number is a weak, widely-known secret.
**Impact:** Customer order history + financial balance exposed to anyone who knows the phone number.
**Fix:** Add a lightweight verification step (OTP to the phone, or an emailed magic link) before exposing order history and balances.
**✅ Resolution (partial, per owner):** Removed the customer outstanding-balance figure from the phone-only history page — the sharpest leak — so a phone number no longer exposes financial state. Order history itself remains phone-gated (expected self-service); full OTP/magic-link verification was deferred as it needs SMS/email gateway config. `StorefrontB2bTest` now asserts the balance is not shown.

#### M-4 — "Cleared" company context reads across all companies
**Where:** `app/Scopes/CompanyScope.php:26` — when the context is *cleared* (not `none()`), `hasCompany()` is false and the scope applies **no** `company_id` filter.
`SetCurrentCompany` calls `->clear()` at the top of every request, and leaves it cleared for guests and for the `:optional` domain path. Any query that runs before an explicit `->set()` / `->all()` / `->none()` therefore reads across **all** companies. Current controllers set context explicitly, so this isn't exploited today — but it's a footgun: a future code path (a new guest route, a console path, a view composer) that queries a scoped model before context is set silently leaks cross-company data.
**Impact:** Latent isolation risk; correctness depends on every caller remembering to set context.
**Fix:** Make the *default* (unset) state fail-closed — treat "no context bound / cleared" as `none()` (deny) for guest/unauthenticated web requests, and require explicit `->all()` for cross-company reads. At minimum, add a regression test asserting a scoped query with a cleared context returns nothing on guest routes.
**✅ Resolution (hardened, not flipped):** Investigation showed the storefront guest flow **relies** on cleared=unscoped: guest route-model binding for `/checkout/success/{order}` and `/track` resolves the record before `ResolveCompanyFromDomain` sets context, then controllers verify `company_id` and `source` themselves. Flipping the default to fail-closed would 404 legitimate guest checkout/tracking and break many context-less tests. So the contract is now explicitly **documented** in `CompanyScope` and `SetCurrentCompany`, `CustomerBlacklist`'s exception is documented (L-2), and a guard test (`MultiCompanyIsolationTest::test_company_context_boundary_states`) pins the none/all/cleared semantics so any future change is caught. A full fail-closed redesign remains a larger, separate task.

#### M-5 — Heavy synchronous work in model save events
**Where:** `Order::booted` (`saved` → `syncTotalsStockAndCustomerBalance` → stock-movement upserts + customer-balance recompute + risk evaluation), `Purchase`/`PurchaseItem` (landed-cost re-allocation), `ProductVariant` (parent stock resync).
Each save triggers a cascade of additional queries and writes. It's functionally correct and well-contained via `saveQuietly()` to avoid loops, but it lengthens every write transaction. Combined with H-1 (SQLite single-writer) this materially increases lock-contention probability.
**Impact:** Slower writes; amplifies H-1.
**Fix:** After moving off SQLite this is acceptable. Where possible, move non-critical follow-ups (risk evaluation, external fraud) fully onto a real queue, and prefer SQL aggregate updates over load-all-then-sum (see L-3).
**✅ Resolution (partial):** The heaviest recompute (stock summation) now runs as a SQL aggregate instead of loading history into PHP (see L-3), shrinking the write window. Moving the external-fraud/risk follow-ups fully onto a worker is covered by the H-2 queue guidance (deploy action). The remaining synchronous chain is acceptable once off SQLite.

---

### 🟡 LOW

#### L-1 — Example env ships `APP_DEBUG=true` / `APP_ENV=local`
**Where:** `.env.example:2,4`. If a deploy copies the example verbatim, production leaks full stack traces and config. Add a clear "production must set `APP_ENV=production`, `APP_DEBUG=false`" note, or ship a separate `.env.production.example`.
**✅ Resolution:** Shipped `.env.production.example` (`APP_ENV=production`, `APP_DEBUG=false`) and added a header note to `.env.example` directing production deploys to it.

#### L-2 — `CustomerBlacklist` intentionally omits `CompanyScope`
**Where:** `app/Models/CustomerBlacklist.php`. This is **by design** (supports a global `company_id = NULL` entry plus per-company entries, and the resource is super-admin only), and `CustomerRiskService::blacklistMatch()` filters manually. But it's the one company-owned-ish model excluded from the isolation contract test, and the design isn't documented in-code. Add a short comment explaining the deliberate exception so a future maintainer doesn't "fix" it by adding the trait (which would break the global-entry feature) or accidentally query it unscoped elsewhere.
**✅ Resolution:** Added a class-level docblock explaining the deliberate omission and the required explicit-filter rule for any new query.

#### L-3 — Stock recomputation sums in PHP, not SQL
**Where:** `StockMovementService::syncProductStock()` / `projectedStockFor()` load **all** movements for a product (`->get()->sum(...)`). For a high-volume product this loads the entire movement history into memory on every recompute. Prefer a SQL `SUM(CASE WHEN type='sale' THEN -qty ELSE qty END)` aggregate.
**✅ Resolution:** Added a `signedStockSum()` helper doing the `SUM(CASE ...)` in SQL; the three call sites (`syncProductStock`, `projectedStockFor`, `assertCanDelete`) now use it. Existing `StockMovementTest` still green.

#### L-4 — Magic number for pre-order available stock
**Where:** `StorefrontCart::availableStock()` returns `max((int) $product->stock, 100000)` for pre-order products. The `100000` cap is an unexplained magic constant; make it a named constant or a configurable per-product pre-order ceiling.
**✅ Resolution:** Extracted `StorefrontCart::PREORDER_STOCK_CEILING` with an explanatory docblock.

#### L-5 — Form-layer (Livewire) test coverage is thin
**Where:** `tests/Feature/`. Coverage is strong at the model/service layer, but almost all tests create records directly (`Model::create(...)`) and bypass the Filament form/Livewire pipeline. The recent purchase-save crash (`->dehydrated(false)`) shipped **precisely because** the form layer wasn't exercised — the new `test_create_purchase_form_saves_items_without_null_cost_column_error` is the first `Livewire::test(...)` test. Add form-layer tests for other create/edit resources that have computed or read-only fields (Orders, Products with variants, Stock Movements).
**✅ Resolution (started):** Added `OrderFormTest` driving the Order create screen through Livewire (confirms the items repeater's read-only `subtotal` is safely recomputed on save — the same class of bug as the Purchase crash). Products-with-variants and Stock-Movement form tests can follow the same pattern next.

#### L-6 — Minor maintainability nits
- `StorefrontCart` uses inline fully-qualified references (`\App\Models\StorefrontCartRecord`, `\Illuminate\Support\Facades\Schema`) instead of `use` imports — harder to scan.
- Storefront controllers carry near-duplicate `xxx` / `xxxPreview` method pairs (domain vs. preview). Consider extracting the shared body to reduce drift.
- `env('ADMIN_PASSWORD')` is read outside the config layer (`app/Support/AdminPassword.php:13`). It's seeder-time only, so `config:cache` doesn't break it, but reading `env()` outside `config/` is the documented anti-pattern; route it through a config value for consistency.

**✅ Resolution:** `StorefrontCart` inline FQ refs replaced with imports; `AdminPassword::fromEnvironment()` now reads `config('app.seed_admin_password')` (new config key). The near-duplicate `xxx`/`xxxPreview` controller pairs were left as-is (large refactor, low risk), but the shared phone-match logic they both grew was extracted to the `MatchesCustomerPhone` trait.

---

## 4. Recommended Priority Order

1. **H-1** — Move production to MySQL/Postgres. (Biggest reliability win.)
2. **H-2** — Switch production queue off `sync` and run a worker.
3. **M-2 / M-3** — Close storefront order enumeration and phone-only history access.
4. **M-1** — Make order-number generation concurrency-safe.
5. **M-4** — Make cleared/unset company context fail-closed on guest paths; add a regression test.
6. **M-5 / L-3** — After the DB move, lighten save chains and use SQL aggregates.
7. **L-1, L-2, L-4, L-5, L-6** — Housekeeping; fold into normal maintenance.

---

## 5. Notes & Method

- All findings are from static reading of the code plus the passing test run (`php artisan test`, 234 passed). No code was modified.
- Severity reflects production risk under real multi-user load, not demo behavior. Several High/Medium items are masked today by low traffic and by SQLite serializing writes.
- No secrets, destructive commands, or demo-data mutations were run during this audit (per project CLAUDE.md rules).
