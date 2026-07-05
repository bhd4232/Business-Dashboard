# Update Notes

This file is a working update log for changes that may become commits. Use it to decide what a pending commit contains before approving any `git commit` or push.

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
