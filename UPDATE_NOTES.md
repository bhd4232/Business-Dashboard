# Update Notes

This file is a working update log for changes that may become commits. Use it to decide what a pending commit contains before approving any `git commit` or push.

## 2026-07-22 - Cloudflare R2 credential guidance in Cloud Storage

Reason:

- Every R2 credential and topology field needed concise, field-level instructions showing exactly where to obtain or configure its value in Cloudflare.
- Essential setup guidance must remain usable by keyboard and touch users without turning the settings form into a wall of helper text.

Important changed files:

- `app/Filament/Pages/CloudStorageSettings.php` - adds a native Filament R2 setup-guide action and accessible information actions beside all eight configuration fields, with current Cloudflare dashboard steps and official documentation links.
- `tests/Feature/CloudStorageSettingsTest.php` - verifies the setup modal content, exact field-to-help-action placement, icon-button accessibility configuration, credential guidance, and private-bucket safety guidance.
- `PROJECT_GUIDE.md` - documents the in-product R2 credential-help contract.

Verification:

- Focused R2 setup-guide and field-help test passed: 1 test, 41 assertions.
- Full Cloud Storage settings suite passed: 5 tests, 71 assertions.
- Full application suite passed: 446 tests, 2,330 assertions.
- Targeted Pint formatting, PHP syntax checks, `php artisan view:cache`, and `git diff --check` passed.

Commit status:

- Not committed. Commit and push require explicit user approval.

## 2026-07-22 - Keep Company Settings visible in All Companies mode

Reason:

- The Company Management selector hid Company Settings whenever Super Admin selected `All Companies`, leaving only the Companies item visible.

Important changed files:

- `app/Filament/Pages/CompanySettings.php` - separates page/navigation permission from the requirement for a specific active company while retaining the selected-company save guard.
- `resources/views/filament/pages/company-settings.blade.php` - shows a native Filament select-company empty state in `All Companies` mode and renders the form only for one selected company.
- `tests/Feature/CompanySettingsTest.php` - covers selector visibility, the safe empty state, normal selected-company rendering, and existing permission boundaries.
- `PROJECT_GUIDE.md` - documents the visible-page/non-writable-form contract for `All Companies` mode.

Verification:

- Focused Company Settings and admin navigation suite passed: 29 tests, 207 assertions.
- Full application suite passed: 445 tests, 2,288 assertions.
- Targeted Pint formatting, `php artisan view:cache`, and `git diff --check` passed.

Commit status:

- Not committed. Commit and push require explicit user approval.

## 2026-07-22 - Site labels and native Filament page UI

Reason:

- The storefront cluster repeated `Storefront` across its sidebar and child selector labels; the admin-facing module should read as `Site` with concise child names.
- Reports, Release Notes, Backups, and Product Setup used page-specific markup and styling instead of the project's required Filament-default admin patterns.

Important changed files:

- `app/Filament/Clusters/Storefront.php` - changed the admin navigation label and cluster breadcrumb to `Site` while retaining the `storefront` slug.
- `app/Filament/Resources/StorefrontSettings/StorefrontSettingResource.php`, `StorefrontPages/StorefrontPageResource.php`, and `StorefrontPayments/StorefrontPaymentResource.php` - shortened selector labels to `Settings`, `Pages`, and `Payments`; `Hero Slides` and `Homepage Carousels` remain unchanged.
- `app/Filament/Pages/Reports.php` and `resources/views/filament/pages/reports.blade.php` - moved report results to a dynamic Filament table with native filters, tabs, metric sections, empty states, and CSV/PDF header actions.
- `app/Filament/Pages/ReleaseNotes.php` and `resources/views/filament/pages/release-notes.blade.php` - render release metadata and changelog content with Filament sections, badges, buttons, and empty states.
- `app/Filament/Pages/Backups.php` and `resources/views/filament/pages/backups.blade.php` - replaced custom cards/forms/tables/modal styling with schema sections, native header actions, a Filament modal form, and infolist backup tables.
- `app/Filament/Pages/ProductSetup.php` and `resources/views/filament/pages/product-setup.blade.php` - replaced custom onboarding/license/checklist UI with schema sections, Filament form controls/actions, and an infolist checklist.
- Relevant navigation and page feature tests cover the display labels, native components, existing actions, and the removal of page-local CSS.
- `PROJECT_GUIDE.md` - documents the display-only Site name, unchanged storefront route/domain vocabulary, and the native Filament page contracts.

Route and compatibility notes:

- Canonical admin URLs remain `/admin/storefront/...`; no route, resource class, model, database table, or public storefront-domain name was renamed.
- Existing report exports, backup downloads, setup saves/license activation, and release-document links retain their current actions and authorization behavior.

Verification:

- Focused Site navigation-label test passed: 1 test, 7 assertions.
- Combined Site navigation, Reports, Release Notes, Backups, and Product Setup regression suite passed: 38 tests, 277 assertions.
- Full application suite passed: 444 tests, 2,278 assertions.
- Targeted Pint formatting, `php artisan view:cache`, and `git diff --check` passed.
- Browser-control visual QA could not start because the installed runtime rejected its environment metadata (`sandboxPolicy` missing); rendered Filament feature assertions cover the native component contract.

Commit status:

- Not committed. Commit and push require explicit user approval.

## 2026-07-22 - Unified Filament navigation clusters for business modules

Reason:

- Storefront, CRM, Finance, Sales, Purchasing, Inventory, Accounts, Reports, and Settings needed the same one-sidebar-item/page-selector behavior already used by Courier, Customer Success, and Company Management.
- The implementation must stay inside Filament's native navigation patterns, remain responsive, preserve permissions, and avoid breaking existing bookmarks or operational download/export URLs.

Important changed files:

- `app/Filament/Clusters/Storefront.php`, `Crm.php`, `Finance.php`, `Sales.php`, `Purchasing.php`, `Inventory.php`, `Accounts.php`, `Reports.php`, and `Settings.php` - added ordered native Filament clusters with top sub-navigation, responsive mobile selectors, icons, exact CRM breadcrumb text, and direct-root access guards.
- `app/Filament/Clusters/CompanyManagement.php`, `Courier.php`, and `CustomerSuccess.php` - aligned sidebar ordering and added the same direct-root authorization guard.
- The corresponding Filament resources/pages - replaced legacy navigation groups with ordered cluster membership. Storefront Payments now follows Homepage Carousels; Audit Logs and Release Notes have unique Settings order values.
- Hidden Shipment and Container resources remain outside Purchasing so they cannot accidentally expose its sidebar item to unauthorized roles. Expense Categories, Transaction Ledgers, and User Roles remain hidden selector support pages while their generated internal links use the relevant cluster route.
- `app/Providers/Filament/AdminPanelProvider.php` - removed the obsolete explicit navigation-group registry; Filament discovers and renders the cluster items directly.
- `app/Http/Controllers/Admin/LegacyAdminClusterRedirectController.php` and `routes/web.php` - authenticated legacy child URLs preserve deep record paths and query strings while redirecting to canonical cluster routes. Existing order print/PDF, report export, CSV sample/export, backup download, and attachment routes remain unchanged.
- `resources/views/chat-order/success.blade.php` and `closed.blade.php` - staff return links now target the canonical CRM Inbox URL.
- Existing feature tests now exercise canonical nested routes; `tests/Feature/AdminNavigationClustersTest.php` covers all class mappings, top selectors, root destinations, rendered selector labels, permission filtering, legacy query/deep-link compatibility, and custom-route precedence.
- `PROJECT_GUIDE.md`, `docs/backup-restore.md`, and `CHANGELOG.md` - documented the navigation contract and canonical admin paths.

Verification:

- `AdminNavigationClustersTest` passed: 8 tests, 79 assertions.
- Full application suite passed: 440 tests, 2,199 assertions.
- Targeted Pint validation passed for the cluster implementation and affected tests.
- `php artisan view:cache` and `git diff --check` passed.
- Route discovery confirms canonical nested routes for all nine modules plus the authenticated compatibility route.
- Browser-control visual QA could not start because the installed runtime rejects its environment metadata (`sandboxPolicy` missing); native Filament component rendering and feature assertions verify the desktop/mobile selector contract.

Commit status:

- Not committed. Commit and push require explicit user approval.

## 2026-07-22 - Company Management Filament cluster

Reason:

- Company Management should behave like the existing Courier and Customer Success pages: one sidebar destination with Filament-native page selection instead of separate menu entries.

Important changed files:

- `app/Filament/Clusters/CompanyManagement.php` - added the native cluster with top sub-navigation, matching Courier and Customer Success.
- `app/Filament/Resources/Companies/CompanyResource.php` and `app/Filament/Pages/CompanySettings.php` - registered Companies and Company Settings as ordered cluster children.
- `app/Providers/Filament/AdminPanelProvider.php` - removed the obsolete standalone Company Management navigation group.
- `routes/web.php` - preserved the former Companies and Company Settings entry URLs as authenticated redirects.
- `tests/Feature/CompanySettingsTest.php` - verifies cluster membership, top page selector, root redirect, child rendering, legacy redirects, and existing permission/company-context behavior.
- `PROJECT_GUIDE.md` - documents the cluster and canonical child routes.

Verification:

- `CompanySettingsTest` passed: 19 tests, 111 assertions.
- Route discovery exposes the cluster root plus Companies and Company Settings child routes under `/admin/company-management`.
- Full application suite passed: 432 tests, 2,120 assertions.
- Pint passed for every changed PHP file.

Commit status:

- Not committed. Commit and push require explicit user approval.

## 2026-07-22 - Invalid legacy company logos no longer crash Filament

Reason:

- A legacy global logo path had been copied into `companies.logo` before company-scoped storage validation was introduced. Clearing the global AppSetting restored `/admin/login`, but `/admin/companies` still threw a 500 while resolving the copied malformed company logo.

Important changed files:

- `app/Services/CompanySettingsService.php` - added a lightweight company-name resolver and made optional logo URL/PDF-path presentation fail closed when a stored path violates the company storage contract.
- `app/Support/CompanyMedia.php` - invalid or cross-company public media values now render as no image instead of crashing Filament tables and infolists.
- `app/Providers/Filament/AdminPanelProvider.php` - brand text no longer eagerly builds the full media-backed company profile.
- `tests/Feature/CompanySettingsTest.php` - covers malformed global branding, selected-company branding, and the Companies table while preserving the bad value for deliberate recovery.
- `PROJECT_GUIDE.md` - documents strict storage enforcement and fail-closed optional media rendering.

Verification:

- Reproduction tests failed with the production exception before the fix and passed afterward.
- `CompanySettingsTest` passed: 17 tests, 99 assertions.
- `CompanyStorageServiceTest` passed: 13 tests, 91 assertions, including strict traversal and cross-company rejection.
- Full application suite passed: 430 tests, 2,108 assertions.
- Pint passed for all changed PHP files.

Commit status:

- Not committed. Commit and push require explicit user approval.

## 2026-07-22 - Inbox thread containment, latest-message scroll, collapsible conversations, and compact product cards

Reason:

- Real desktop Inbox screenshots showed a long order URL forcing the outgoing bubble beyond the thread column, the initial thread position becoming unreliable after a large lazy-loaded image, the catalog image overwhelming the product message, and the fixed Conversations pane consuming thread width when the operator wanted to focus on chat.

Important changed files:

- `resources/views/filament/pages/inbox.blade.php` — every grid pane and message bubble now has explicit minimum-width/overflow containment; long text and links wrap anywhere; short threads anchor to the bottom; multi-phase synchronization after open/reload/navigation/layout changes keeps the newest message visible; lazy image loads preserve bottom stickiness; older messages remain above and independently scrollable; order-form images render as clickable 192×128 thumbnails while normal chat images retain a sensible larger cap. The desktop Conversations pane now uses Filament icon buttons to collapse into a persistent customer-profile rail with unread badges, while the thread automatically expands into the released width; mobile remains fully expanded.
- `app/Services/Meta/MetaGraphService.php` — root-relative local public-disk media is expanded with a public `APP_URL` before Meta delivery. Loopback/private-IP URLs are omitted so WhatsApp/Messenger still receive the order text instead of rejecting the entire message or receiving an unusable local URL.
- `tests/Feature/InboxPageTest.php` and `tests/Feature/MetaMessagingReliabilityTest.php` — rendered thumbnail/wrapping contracts, chronological newest-last history, latest-message resync hooks, collapsible profile-rail controls, absolute outbound media, and localhost text-only fallback are covered.
- `PROJECT_GUIDE.md` — the current thread behavior, collapsible desktop rail, and outbound media requirement are documented.

Verification:

- Full application suite — 428 passed, 2,100 assertions.
- Inbox suite — 15 passed, 86 assertions.
- `php artisan view:cache` and a static parse of the Inbox Alpine component — passed.
- `npm run build` — passed; compiled theme includes `overflow-wrap:anywhere`, `break-all`, `min-width:0`, the compact thumbnail utilities, and the responsive collapsed-rail layout classes.
- Browser-control visual QA could not start because the installed runtime still rejects its environment metadata (`sandboxPolicy` missing); the user-provided screenshot guided the fix and rendered-component/CSS assertions cover the affected contracts.

Commit status:

- Not committed. Commit and push require explicit user approval.

## 2026-07-21 - WhatsApp Cloud reliability and channel-based modern Inbox

Reason:

- WhatsApp callback verification had been configured, but incoming messages never appeared and outgoing attempts had no durable failure state. The Inbox also needed a channel-first, responsive workflow comparable to Meta Business Suite while remaining entirely within Filament's default component system.
- Live read-only diagnostics confirmed the public callback, verify token, and Phone Number ID mapping work. The saved Meta access token is expired (`OAuthException` code `190`, subcode `463`), which definitively blocks live outgoing messages and media until the owner replaces it. Callback verification alone also does not subscribe the app to the WABA or enable the `messages` field.

Important changed files:

- `app/Services/Meta/MetaGraphService.php`, `MetaGraphException.php`, `config/services.php`, `.env.example`, and `.env.production.example` — centralized configurable Graph `v25.0`, bearer-token requests, health/WABA subscription helpers, safe errors, no automatic retry for non-idempotent sends, and Meta-only size-limited media downloads.
- `database/migrations/2026_07_21_000300_add_meta_diagnostics_to_conversation_channels_table.php`, `app/Models/ConversationChannel.php`, and the Conversation Channel Filament resource/pages — WABA ID, connection-test/callback/subscription/webhook/inbound/outbound/error diagnostics, tenant-locked ownership, truthful `Configured` versus `Inbound confirmed` states, copyable callback, encrypted-secret preservation, and **Test & Subscribe**.
- `app/Http/Controllers/MetaWebhookController.php` and `app/Jobs/StoreIncomingMessageJob.php` — dotted handshake parameters, exact raw HMAC, all-entry/all-change company routing, Phone Number ID/WABA pairing, synchronous transactional core persistence before `200`, atomic dedupe/unread updates, monotonic message times/statuses, and durable queued media/AI work.
- `app/Services/Crm/ConversationMessengerService.php`, `app/Jobs/MarkConversationReadJob.php`, `DownloadConversationMediaJob.php`, `AiReplyService.php`, and `ChatOrderController.php` — durable `sending → sent/failed` archive for Meta delivery, truthful `internal` state for local/manual activity, sanitized retryable failure bubbles, atomic retry claim, reply-window enforcement, nonblocking durable read-receipt jobs, capped secure media handling, and AI handoff on delivery failure.
- `app/Filament/Pages/Inbox.php`, `resources/views/filament/pages/inbox.blade.php`, `resources/css/filament/admin/theme.css`, `AdminPanelProvider.php`, and `vite.config.js` — Filament-default channel tabs; URL-backed filters; paginated/list-thread-details desktop layout; mobile list/thread navigation; newest-50 history loading; accessible message log, forms, times, focus and reduced-motion behavior; reply/internal-note composer; retry, assignment, unread, status, AI and channel-health controls; company currency/timezone; and independent scrolling.
- `app/Models/User.php` — explicit `crm.view`/`crm.manage`; built-in access is limited to Super Admin, Manager, and Sales Staff.
- `StorefrontSettingResource.php` and `StorefrontNotificationService.php` — abandoned-cart templates may select the company's active WhatsApp Chat Channel so Inbox and storefront automation share one token/Phone Number ID; legacy encrypted values remain fallback-only until selected.
- Meta, ingest, channel resource, Inbox, AI, private-media, storefront-reminder, and permission feature tests — regression coverage for the repaired integration, tenant boundaries, failure archive/retry, nonblocking read, secure media, role privacy, and modern Inbox behavior.
- `PROJECT_GUIDE.md`, `ERP_PHASE_ROADMAP.md`, and `ECOMMERCE_PLAN.md` — current architecture, setup/recovery steps, permissions, deployment behavior, and verification documented.

Verification:

- Full application suite — 427 passed, 2,089 assertions.
- Latest Inbox + Meta reliability suites — 30 passed, 149 assertions.
- `php artisan view:cache` — passed.
- `npm run build` — passed; custom Filament theme contains the responsive utilities used by the Inbox.
- Browser-plugin visual QA could not start because the installed runtime rejected its environment metadata (`sandboxPolicy` missing); responsive/accessibility contracts were covered by Blade compilation, guideline review, rendered-component assertions, and the production CSS build.

Deployment and owner action:

- Run `php artisan migrate --force`, rebuild frontend assets, and clear/cache configuration/views.
- Replace the expired token with a permanent Meta System User token having `whatsapp_business_messaging` and `whatsapp_business_management`; confirm Phone Number ID and WABA ID; keep the Meta app Live; **Verify and Save** the callback; enable the WhatsApp `messages` field; then save and run **Test & Subscribe** in CRM → Chat Channels.
- Send a real customer WhatsApp message and confirm **Last Webhook** and **Last Inbound** update. The application cannot generate or renew the owner's Meta token and Meta offers no API proof for the dashboard's `messages` checkbox.

Commit status:

- Not committed. Commit and push require explicit user approval.

## 2026-07-21 - Company-isolated R2 storage and company-wise invoicing

Reason:

- Use one centrally managed Cloudflare R2 connection without mixing tenant objects, expose storefront media through a public CDN bucket, keep chat/voucher files private, migrate legacy objects without deletion, and make invoice identity/layout settings unambiguously company-specific.

Important changed files:

- `app/Services/StorageSettingsService.php`, `config/filesystems.php`, and `app/Filament/Pages/CloudStorageSettings.php` — distinct public/private R2 buckets, encrypted shared credentials, private-access attestation, connection-gated activation, custom-domain verification, and locked active topology.
- `app/Models/Company.php`, `app/Services/CompanyStorageService.php`, `app/Support/CompanyMedia.php`, and `app/Support/StorageUrl.php` — immutable company storage UUIDs, safe public/private prefixes, tenant-authorized writes, outage-tolerant dual reads, verified CDN preference manifests, and no unsupported R2 object ACLs.
- `app/Services/CompanyStorageMigrator.php`, `app/Console/Commands/MigrateCompanyStorage.php`, `database/migrations/2026_07_21_000000_add_storage_key_to_companies_table.php`, and `2026_07_21_000100_create_legacy_private_storage_paths_table.php` — dry-run/copy/checksum migration, destination preflight, resumable conflict handling, case-sensitive private legacy ownership registry, and source retention.
- `app/Http/Controllers/Admin/ConversationMediaController.php`, `VoucherAttachmentDownloadController.php`, `app/Jobs/DownloadConversationMediaJob.php`, and voucher/inbox models/views — authenticated company-aware private downloads, inactive-session denial, and private company-prefix uploads.
- Product/category/company/storefront Filament resources, storefront views, `CompanySettingsService`, `WooCommerceImportService`, and `DemoDataSeeder` — company-scoped public media writes and dual-read previews across local and R2 storage.
- `app/Filament/Pages/CompanySettings.php`, `resources/views/filament/pages/company-settings.blade.php`, `app/Services/CompanySettingsService.php`, `OrderPdfController.php`, and invoice print/PDF views — default Filament company settings UI, locked mounted-company saves, order-company-specific print/PDF settings, and nested invoice controls.
- `app/Filament/Resources/Companies/CompanyResource.php`, `app/Rules/AccessibleCompany.php`, and `database/migrations/2026_07_21_000200_add_unique_invoice_prefix_to_companies_table.php` — safe post-create company-logo uploads, Filament-compatible company authorization, normalized database-unique invoice prefixes, and inactive-company reactivation for Super Admin.
- Storage, private attachment, public media, company settings, invoicing, and migration feature tests — regression coverage for tenant boundaries, topology/attestation, backfills, copy verification, inactive users, context drift, invoice isolation, and the real PDF controller path.
- `PROJECT_GUIDE.md` — architecture, deployment sequence, important files, and verification commands.

Verification:

- Focused storage, storefront, settings, and invoicing suite — 71 passed, 482 assertions.
- `php artisan test --compact` — 388 passed, 1,879 assertions.
- `php artisan view:cache` — passed.
- `npm run build` — passed.
- `vendor/bin/pint --dirty` and `git diff --check` — passed.

Deployment notes:

- Run `php artisan migrate` first.
- In Cloud Storage, save configuration while disabled, test the public custom domain and the attested private bucket, then enable R2.
- Run `php artisan storage:migrate-company-files --company={slug} --scope=all` and review the dry-run before the production `--execute --force` run. Source objects are intentionally retained.
- Select a specific company before opening Company Settings and verify its invoice prefix/contact/layout settings; `All Companies` cannot edit these settings.

## 2026-07-20 - Storefront release-blocker, privacy, and responsive UX hardening

Reason:

- The storefront audit found broken variable-product quick add/mobile CTA behavior, inconsistent zero-stock preorder quantities, ambiguous manual-payment inputs, publicly enumerable phone-only order history, unsigned checkout-success pages, and several mobile/keyboard accessibility gaps.

Important changed files:

- `app/Http/Controllers/Storefront/CartController.php`, `resources/views/storefront/partials/product-card.blade.php`, `products/show.blade.php`, and `cart/show.blade.php` — variable products now open option selection; desktop/mobile variant CTAs stay synchronized; zero-stock preorders and cart MOQ/ceiling rules match `StorefrontCart`; pending and duplicate-submit states added.
- `app/Http/Controllers/Storefront/CheckoutController.php`, `resources/views/storefront/checkout/show.blade.php`, and `checkout/success.blade.php` — one shared bKash/Nagad sender/transaction input set, valid enabled-method fallback, semantic/error-focused checkout, signed production success/tracking URLs, and owner-or-signature authorization.
- `app/Http/Controllers/Storefront/AccountOrdersController.php`, `OrderTrackController.php`, and `resources/views/storefront/account/orders.blade.php` — production history/reorder requires the authenticated owning customer; disabled-account stores redirect to manual tracking; public tracking still uses order number + phone, while signed or owner access keeps phone out of URLs. Phone-only history remains only in the local/admin preview.
- `resources/views/storefront/layout.blade.php`, `home.blade.php`, `products/index.blade.php`, `products/show.blade.php`, `contact/show.blade.php`, and `resources/css/app.css` — accessible mobile menu/theme state/current navigation, global focus/touch treatment, brand-color contrast, FAQ/product-tab semantics, two-column mobile catalog, image dimensions, and safe-area spacing.
- `tests/Feature/ProductVariantTest.php`, `StorefrontFoundationTest.php`, `StorefrontManualPaymentTest.php`, `StorefrontMenuTest.php`, `StorefrontPreorderPaymentTest.php`, `StorefrontReorderTest.php`, and `StorefrontCustomerAuthTest.php` — regression coverage for the repaired purchase, privacy, payment, and accessibility contracts.
- `n8n Workflows/*.json` and `n8n Workflows/README.md` — 30 embedded Meta authorization/access-token values replaced with four `$env` expressions; credential/instance/workflow/webhook metadata and pinned data removed; required host variables and post-import credential/webhook checks documented.
- `PROJECT_GUIDE.md` — current routes, authorization rules, important files, and verification commands documented.

Verification:

- `php artisan test --compact` — 344 passed, 1,568 assertions.
- Storefront-focused suite — 82 passed, 472 assertions.
- `php artisan view:cache` — passed.
- `npm run build` — passed.
- Live QA at 390px mobile and 1440px desktop — no horizontal overflow, console errors, or failed requests; mobile menu focus/Escape, two-column catalog, synchronized variable-product CTAs, and keyboard product tabs verified.
- Pre-push secret/size audit — no credential literals remain in the five n8n JSON exports, no sensitive-name files found, and no changed file is 10 MB or larger.
- `php artisan test --compact tests/Feature/ReleaseNotesTest.php` — 3 passed, 23 assertions.
- `git diff --check` — passed.

## 2026-07-18 - Merge Storefront Settings banners into Hero Slides (v1.20.0)

Reason:

- Owner flagged the duplication: Hero Slides and the Storefront Settings "Banner images" section were two parallel homepage-banner systems (slides = full-width scheduled hero with text/CTA; banners = fallback side-card with product links, only shown when no slides existed). Merged into Hero Slides as the single system.

Changed files:

- `database/migrations/2026_07_18_120000_merge_storefront_banners_into_slides.php` (new) — adds `storefront_slides.product_id` FK; converts existing banner images (desktop+mobile paired by position, product tags kept, dropped tags for deleted products) into slides for companies without slides; drops `banner_images`/`banner_images_mobile`.
- `app/Models/StorefrontSlide.php` — `product_id` fillable + `product()` relation.
- `app/Models/StorefrontSetting.php` — banner fillables/casts and `bannerSlides()` removed.
- `app/Filament/Resources/StorefrontSlides/StorefrontSlideResource.php` — company-filtered "Link to product" select (company select now live).
- `app/Filament/Resources/StorefrontSettings/StorefrontSettingResource.php` — banner repeaters + `bannerRepeater()` removed (pointer note to Hero Slides); readiness check "Banner uploaded" → "Hero slide added" (active slide exists).
- `resources/views/storefront/home.blade.php` — slide image now clickable (CTA URL wins, else product page); banner fallback removed. `resources/views/storefront/partials/banner-carousel.blade.php` deleted. `layout.blade.php` og:image now from the first active slide.
- `tests/Feature/StorefrontBannerTest.php` — rewritten around slides (4 tests). `ReleaseNotesTest` bumped to v1.20.0.

Also in this batch — WooCommerce sync gaps (owner-reported: only simple base products, missing info):

- `app/Services/WooCommerceImportService.php` — variable products now fetch and upsert their variations as `ProductVariant` rows (SKU or options-signature match; stock never imported); full `description` preferred over `short_description`; extra images → `gallery_images` (first sync only); `Brand` attribute → `brand`; parent gets `has_variants` + `variant_attributes`.
- `tests/Feature/WooCommerceImportTest.php` — new variable-product test (variations created with options/prices, brand + full description, re-sync doesn't duplicate).
- Owner should re-run "Sync WooCommerce" on the live store after deploy to backfill variations/descriptions/galleries.

Also in this batch — homepage redesign + mobile hero fix (owner-reported: mobile banner cropped; wants MoveOn-style homepage):

- `resources/views/storefront/home.blade.php` — hero stage is `aspect-[3/4]` on phones when any slide has a mobile image (uncropped portrait banners), else 16:9; round category icon scroller under the hero (replaces the old big category cards); featured grid now 2-col on mobile; "Explore more products" dense grid (products 9-23); steps section moved to the bottom; brand-colored "Ready to order?" CTA band before the footer.
- `HomeController`/`PreviewController` — homepage products 12 → 23.
- "How to order" steps → 4 cards with the same 3D gradient badges (violet search / blue cart / green clipboard / amber package); number chips removed, replaced by a looping sequence animation (card lift + badge pulse + halo ping per step, desktop gradient progress line with glowing runner dot), IntersectionObserver-gated (paused until scrolled into view) and prefers-reduced-motion aware; 2-col mobile, 4-col desktop.
- Trust strip redesigned as three premium cards with 3D-style gradient icon badges (glossy highlight, colored glow; truck/return-arrow/banknotes SVGs) — texts stay admin-managed.
- Verified in the browser preview (mobile 375px + desktop): hero 375x500 with the mobile image, all sections render, no horizontal overflow, no console errors.

Also in this batch — admin-managed header/footer navigation menus (owner request):

- `database/migrations/2026_07_19_100000_add_menus_to_storefront_settings.php` (new) — nullable JSON `header_menu` + `footer_menu` on `storefront_settings`.
- `app/Models/StorefrontSetting.php` — new fillables + array casts.
- `app/Filament/Resources/StorefrontSettings/StorefrontSettingResource.php` — "Navigation Menus" section with two reorderable repeaters (`menuRepeater()`); item = label + link type (shop/category/page/track/account/reseller/custom URL) + company-scoped category/page selects + open-in-new-tab toggle.
- `resources/views/storefront/layout.blade.php` — `$resolveMenu` closure (batched category/page slug lookups, preview-aware URLs, skips broken/empty items); desktop nav renders the header menu (Categories dropdown always kept; defaults Shop all/Track/Account when menu empty); new mobile hamburger drawer (menu links + category grid, icon swap + aria-expanded); footer "Quick links" column from footer menu (falls back to the automatic published-pages list).
- `tests/Feature/StorefrontMenuTest.php` (new) — 3 tests: all link types resolve in the header (custom URL new-tab, defaults suppressed), footer menu replaces the auto pages list, broken/empty items skipped with defaults restored.

Also in this batch — redesigned printable invoice + advanced invoice settings (owner request: match the Zamzam International invoice PDF, dynamic, multi-page):

- `resources/views/orders/print.blade.php` — full redesign matching the sample: centered company name + hotline header with logo left; Bill To left / Code 128 barcode + Invoice No / Delivery Partner (latest courier booking's provider) / Date right; bordered item table (SL, Image, Item Name, Weight, Unit Price, Qty, Amount); Sub Total → optional Discount/VAT/Delivery Charge/Paid → Grand Total → black Due Amount bar; contact block (Facebook/email/website/address); gray contact strip (footer hotline · Facebook page · WhatsApp); thank-you banner; dashed scissor cut-line + courier slip (mini header, Bill To, barcode, black due chip). Multi-page: `thead` repeats per printed page (`table-header-group`), rows/blocks use `page-break-inside: avoid` so the footer blocks + cut-slip flow to the last page.
- `app/Support/Code128.php` (new) — dependency-free Code 128-B inline-SVG barcode generator (checksum per ISO/IEC 15417).
- `app/Services/CompanySettingsService.php` — `INVOICE_DEFAULTS`, `invoice()`, `saveInvoice()` (stored per company in `companies.settings['invoice']`).
- `app/Filament/Pages/CompanySettings.php` + `resources/views/filament/pages/company-settings.blade.php` — new "Invoice Settings" card: header/footer hotlines, Facebook URL+label, WhatsApp, website, thank-you message, toggles (image column, weight column, barcode, cut-slip).
- `database/migrations/2026_07_19_120000_add_weight_kg_to_products.php` (new) + `Product` model + Product form — optional `weight_kg`; WooCommerce sync now imports Woo `weight`.
- `routes/web.php` — print route also loads `latestCourierBooking.provider` and passes invoice settings.
- `tests/Feature/InvoiceDesignTest.php` (new, 4 tests) — barcode/weight/delivery-partner/cut-slip render, toggles hide sections, Code128 validity, per-company settings. `CompanySettingsTest` invoice assertions updated to the new markup.
- Verified in browser: barcode SVG ×2 (invoice + slip), totals block, contact strip, thank-you, cut-slip all render on demo order MAIN-20260717-0002.

Also in this batch — storefront primary-color fix (owner-reported, with screenshots: header/footer buttons weren't using the configured brand color):

- Root cause: the site-wide "primary" CTA button pattern (`bg-gray-950 ... hover:bg-[var(--storefront-brand)] ... dark:bg-white dark:text-gray-950`) only ever showed the actual configured brand color (`--storefront-brand`, e.g. Main Company's `#0a68f5`) on `:hover` — the resting state was always black in light mode and white in dark mode, so the header WhatsApp button, Buy Now, Add/Update cart, checkout submit, track-order, reseller-apply, and category-filter chips never visibly matched the footer's already-correct brand-colored "Chat on WhatsApp" button.
- Changed every solid CTA button/chip across `layout.blade.php` (header WhatsApp + Cart hover border), `home.blade.php` (hero CTA), `products/index.blade.php` (category filter chips), `products/show.blade.php` (Buy Now, desktop + mobile sticky bar), `cart/show.blade.php` (Update, Browse products, Continue to checkout, disabled placeholder), `checkout/show.blade.php` (Place order), `checkout/success.blade.php` (Track this order + Continue shopping hover), `account/orders.blade.php` (Show orders, Track order), `reseller/apply.blade.php` (Submit), `track/show.blade.php` (Track order), and `partials/product-card.blade.php` (quick add-to-cart hover) to use `bg-[var(--storefront-brand)]` as the resting background with a simple `hover:opacity-90`, removing the black/white swap. Secondary/outline actions (Add to cart, Ask on WhatsApp) were left as neutral outline buttons to keep the primary/secondary hierarchy.
- Verified in the browser against Main Company's real configured brand color (`#0a68f5`): header WhatsApp button, Buy Now, Continue to checkout, and category chips all render solid blue at rest in both light and dark mode (previously only visible on hover). `php artisan test --filter=Storefront` — 58 passed (283 assertions), no regressions. `npm run build` succeeds.

Also in this batch — Contact Us page + professional storefront pages template (owner request: build About/Contact/Terms/Privacy/Return & Refund/Advance Payment pages to a "high professional design", referencing a MoveOn Contact page screenshot):

- New `App\Http\Controllers\Storefront\ContactController` + `resources/views/storefront/contact/show.blade.php` (bespoke, matching the reference): brand-gradient hero; four method cards (Email Us, Chat on WhatsApp, Help Center → on-page FAQ anchor, Call Us) each auto-hidden when its underlying data isn't configured; "Our Location" (company address + "Find on Map" Google-Maps-search link, hidden with no address); FAQ accordion reusing the existing `CompanyFaq` list (Settings → CRM → FAQs) — no separate FAQ content to maintain; "Still Have Questions?" CTA (WhatsApp, falling back to email). Routes `storefront.contact` (`/contact`) and `storefront.preview.contact`.
- `database/migrations/2026_07_19_130000_add_contact_fields_to_storefront_settings.php` (new) — `contact_email`, `contact_hours` on `storefront_settings` (everything else on the Contact page reuses fields that already existed: `whatsapp_number`, `phone_number`, `Company::address`). New "Support email"/"Support hours" fields added next to WhatsApp/phone in `StorefrontSettingResource`.
- Generic admin-authored pages (`storefront_pages` — About, Terms & Conditions, Privacy Policy, Return & Refund Policy, Advance Payment Policy, or any custom page) got a full template redesign in `resources/views/storefront/pages/show.blade.php`: breadcrumb, optional cover image banner, "Last updated" date, and a "Still have questions? Contact us" box linking to the new Contact page. `database/migrations/2026_07_19_140000_add_cover_image_to_storefront_pages.php` (new) — nullable `cover_image`; `StorefrontPageResource` gets a `FileUpload` for it (WebP-optimized, same pipeline as Hero Slides) and its `content` field switched from a plain textbox to Filament's `RichEditor` (headings, bold, lists, links, tables), rendered through `RichContentRenderer` (auto-sanitized HTML). Existing plain-text pages (no `<` in the stored content) keep rendering through the old paragraph-per-line path, so nothing needed re-entering.
- New `.storefront-richtext` CSS block in `resources/css/app.css` styles the rich content (headings/lists/links/blockquote/table) — no `@tailwindcss/typography` package added, hand-written to match the existing "no new JS/CSS dependency unless necessary" pattern.
- Footer "Contact" column gets a new "Contact us" link to the new page (owner note: the demo/local database already has an older generic `storefront_pages` record with slug `contact` from earlier demo seeding — it still works at `/pages/contact` and doesn't collide with the new `/contact` route, but the owner may want to unpublish or repurpose it to avoid two different "Contact" links).
- `tests/Feature/StorefrontContentPagesTest.php` (new, 4 tests): Contact page renders all cards/location/FAQ when configured; Contact page gracefully hides every optional section when nothing is configured (only the hero remains); a rich-HTML page renders its cover image, formatted content, and the Contact CTA; a legacy plain-text page (pre-existing content, no HTML) still renders exactly as before.
- Verified in the browser (Main Company, temporary local-only sample address/email/hours/2 FAQs, reverted afterward — no fabricated business data was left in the database): hero gradient, floating cards, gradient icon badges, location card, FAQ accordion (Alpine `x-show`/`x-transition`, no new JS dependency), CTA band all render correctly in light and dark mode, desktop and mobile (375px); an existing demo About page (plain-text legacy content) renders breadcrumb + "Last updated" + Contact CTA correctly through the legacy path.
- Full `php artisan test` — 327 passed (1421 assertions). `npm run build` succeeds (CSS 74.5KB → 78.4KB gzip 12.7KB, no new JS dependency).

Also in this batch — invoice A4 size + footer pinned to bottom of the last page (owner request):

- `resources/views/orders/print.blade.php` — `.invoice` is now sized to an exact A4 sheet (`--page-width: 210mm`, `--page-height: 297mm` CSS variables, matching the existing `@page { size: A4 }`) as a flex column. The contact strip, thank-you note, and courier cut-slip are grouped in a new `.invoice-footer` wrapper with `margin-top: auto`, which pushes that whole group flush against the bottom padding of the page instead of floating right under the totals table on a short invoice. Print media query switches `.invoice` to `width: 100%` / `min-height: calc(page height - 2 × page margin)` (the actual printable area within `@page`'s own 10mm/12mm margins); the small-screen media query reverts to a fluid `width: 100%` so phone viewing isn't forced to A4 proportions.
- Verified with the dev server + browser: logged in as `demo@example.com`, opened `/admin/orders/1/print`, and read the computed layout via `getBoundingClientRect()` — `.invoice` renders at 793.7px × 1122.5px (= 210mm × 297mm at 96dpi) and the footer's bottom edge lands exactly at the page's bottom padding, confirming the footer is flush to the bottom for a normal (single-page) invoice. All demo orders only have 2 items each, so a real multi-page invoice couldn't be observed from existing data; injected extra rows client-side (not saved) to confirm the known limitation: once an order's items overflow onto a second printed page, the footer follows immediately after the last row instead of being pinned to the second page's bottom — no browser's print engine supports CSS running-footers/paged-media, so this can't be solved with pure CSS. This matches the invoice's pre-existing behavior for long orders and isn't a regression; it's just not literally "always" for the rare multi-page case.
- `php artisan test --filter="InvoiceDesignTest|CompanySettingsTest|PhaseThreeAdminPagesTest"` — 14 passed (83 assertions). Full `php artisan test` — 327 passed (1421 assertions). No frontend build needed (invoice page isn't part of the Vite pipeline — plain `<style>` tag).

Also in this batch — storefront Web Interface Guidelines audit + fixes (owner asked to install `vercel-labs/agent-skills@web-design-guidelines` and run it against the storefront):

- Reviewed all 13 `resources/views/storefront/**/*.blade.php` files against Vercel's Web Interface Guidelines (fetched live from `vercel-labs/web-interface-guidelines`), then fixed every finding the owner asked to fix:
  - `layout.blade.php`: skip-to-content link (`#main-content`); Categories dropdown rebuilt with Alpine (`x-data="{ open: false }"`, `@click`, `:aria-expanded`, `@click.outside`, Escape-to-close) instead of CSS `:hover`-only, so keyboard users can reach it; `color-scheme` set on `<html>` alongside the existing dark-mode toggle, plus light/dark `<meta name="theme-color">`; a conditional `<link rel="preconnect">` for the "public" storage disk's own host (only emitted when it differs from the request host — so it stays silent on local disk, activates automatically once R2 is turned on); `padding-bottom: env(safe-area-inset-bottom)` on the fixed mobile bottom nav; `aria-label`/`aria-hidden` on the icon-only call button; a small shared `data-confirm` submit-guard script (any form with `data-confirm="..."` now shows a native confirm dialog before submitting).
  - `home.blade.php`: added an `sr-only <h1>` inside the hero-slides branch (the visible slide heading is only an `<h2>`, so the homepage previously had zero `<h1>` elements whenever slides were configured — the common case in production); `text-balance` on both hero headings (slide heading + no-slides fallback heading) since they're admin-controlled, variable-length text.
  - `products/show.blade.php`: `fetchpriority="high"` on the main gallery image (the page's likely LCP element); the two quantity `<input>`s (main + per-variant) had `outline-none` with no focus replacement at all — added `focus:ring-1 focus:ring-[var(--storefront-brand)]` back; `[touch-action:manipulation]` on the four stepper +/- buttons; safe-area padding on the mobile sticky "Buy now" bar.
  - `partials/product-card.blade.php`: the icon-only quick-add button had `title` but no `aria-label` — added `aria-label="Add {{ product name }} to cart"`.
  - `cart/show.blade.php`: the "Remove" line-item form now carries `data-confirm="Remove {{ product }} from your cart?"` (destructive action, previously fired with no confirmation); stepper touch-action.
  - `checkout/show.blade.php`: name/phone/email/address fields gained `autocomplete`, phone got `type="tel"`, email got `spellcheck="false"`; the submit button now disables itself and shows "Placing order…" on submit (guards against double-order from a double-click); a `beforeunload` guard warns before leaving the page once any field has been typed into; the Alpine-computed delivery-charge/total spans got `aria-live="polite"` so screen readers hear the updated total when the delivery-area radio changes; the order-summary line item name got `min-w-0 truncate`/`shrink-0` so a long product name can't push the price off the row.
  - `account/orders.blade.php`, `reseller/apply.blade.php`, `track/show.blade.php`: phone inputs standardized to `type="tel"` + `autocomplete="tel"` (a couple were plain `type="text"` with no autocomplete); reseller's name field got `autocomplete="name"`.
  - `contact/show.blade.php`: the `#faq` anchor (jump target from the "Help Center" card) got `scroll-mt-24` so it no longer lands underneath the sticky header when clicked.
  - `contact/show.blade.php`, `pages/show.blade.php`, `track/show.blade.php`: straight apostrophes in visible copy ("Can't", "you're", "couldn't") replaced with curly ones (`&rsquo;`).
  - Deliberately **not** changed (called out to the owner as low-value churn, not part of the fix pass): rewriting `number_format()`/Carbon date formatting to some `Intl`-equivalent, and wrapping every `{{ $company->name }}` in `translate="no"` — both are guideline line-items but disproportionate to rewrite across a single-locale (Bangladesh) storefront for a marginal, unconfirmed benefit.
- Verified in the browser (Main Company preview, logged in as `demo@example.com`): exactly one `<h1>` on the homepage (confirmed via `document.querySelectorAll('h1').length === 1`), Categories dropdown opens on click with `aria-expanded` toggling `true`/`false`, quantity input keeps its `focus:ring` class, main product image has `fetchpriority="high"`, quick-add buttons show descriptive `aria-label`s, theme-color metas and colorScheme both present — no console errors on any page checked.
- `php artisan test --filter=Storefront` — 62 passed (321 assertions). Full `php artisan test` — 327 passed (1421 assertions), no regressions. `npm run build` succeeds (CSS 78.35KB → 78.79KB for the new `text-balance`/`scroll-mt-24`/arbitrary `[touch-action:manipulation]` classes).

Also in this batch — header search bar + customer accounts (owner request: "header এ সার্চ বার নাই, মডার্ণ সার্চ বার যুক্ত কর" + a customer profile icon with login/profile options, dynamic, with admin-panel control where needed):

- Confirmed there was previously **no storefront customer authentication at all** — "Account" only meant an anonymous phone-number order lookup (`MatchesCustomerPhone`). Building a real profile icon meant building real login/registration first; asked the owner how customers should sign in (phone+OTP vs phone/email+password vs keep guest-only) before writing any of it — chose **phone or email + password**, owner's explicit pick.
- `database/migrations/2026_07_20_025125_add_auth_fields_to_customers_table.php` (new) — `password`, `remember_token`, `password_reset_code`, `password_reset_expires_at` on `customers` (all nullable; a null `password` still means "not a login account", same as any admin/checkout-created customer today). `database/migrations/2026_07_20_025128_add_customer_accounts_enabled_to_storefront_settings_table.php` (new) — `customer_accounts_enabled` boolean, default on.
- `app/Models/Customer.php` — implements `Authenticatable` (new `customer` guard in `config/auth.php`, provider model `Customer`); `password`/`remember_token`/`password_reset_code` hidden; new `isRegistered()` helper. No new model, so no `MultiCompanyIsolationTest` change needed — `Customer` was already `BelongsToCompany`-scoped.
- New `App\Services\CustomerAccountService`: `register()` reuses an existing phone-matched Customer row (so a walk-in/checkout customer who later signs up keeps their order history instead of getting a second, empty account) and blocks re-registration on a phone/email a *registered* account already uses; `attemptLogin()` accepts a phone or an email in one field; `sendPasswordResetCode()`/`resetPassword()` implement a 6-digit SMS code (hashed at rest, 15-minute expiry) reusing `StorefrontNotificationService::sendSms()` — the same admin-configured gateway abandoned-cart reminders already use. New `StorefrontNotificationService::smsConfigured()` helper.
- New `App\Http\Controllers\Storefront\AccountAuthController` (login/register/logout/forgot-password/reset-password) and `AccountProfileController` (profile view/update, password change), new routes under `/account/login`, `/account/register`, `/account/forgot-password`, `/account/reset-password`, `/account/profile`, `/account/password`, `/account/logout` (login/register/forgot-password throttled). `AccountOrdersController` now shows a logged-in customer's orders immediately — no phone re-entry — while the existing guest phone-lookup keeps working unchanged for anyone who doesn't create an account.
- New views: `storefront/account/login.blade.php` (single page, Alpine-tabbed Login/Create account), `forgot-password.blade.php`, `reset-password.blade.php`, `profile.blade.php` (name/email/address edit, password change, reseller-status/apply link, member-since).
- `layout.blade.php`: new modern search bar (desktop inline between nav and the icon cluster; a persistent full-width row on tablet/mobile) submitting to the products page (`?q=`, kept through sort/pagination); new profile-icon dropdown in the header icon cluster (guest: Log in / Create account / Track an order / Find my orders; logged in: name+phone header, My profile, My orders, Track an order, Become a reseller/Reseller status, Log out) built the same click+keyboard-accessible Alpine pattern as the existing Categories dropdown; mobile bottom-nav "Account" icon and the mobile menu's account line now point at login vs. profile depending on auth state. Everything is skipped/falls back to the old guest-only links when `customer_accounts_enabled` is off or in the admin storefront preview (customer login only exists on a real domain).
- `ProductIndexController`/`PreviewController::products()` — new `q` param (`name`/`sku` `LIKE`), `products/index.blade.php` shows a "Results for '…'" heading and a no-matches empty state, and the sort dropdown now keeps the search term via a hidden field.
- `checkout/show.blade.php` — name/phone/email/address now pre-fill from the logged-in customer (still fully editable, `old()` still wins on a validation error).
- `StorefrontSettingResource` — new "Enable customer login & registration" toggle (Storefront Publishing section) so the owner can turn the whole feature off per company and fall back to guest-only phone lookup.
- New `tests/Feature/StorefrontCustomerAuthTest.php` (10 tests): register→auto-login, wrong-password rejection, registration reuses an existing unregistered Customer row instead of duplicating, duplicate-phone registration blocked, profile info + password change (wrong current password rejected), forgot/reset-password full round trip (SMS code captured via `Http::fake` and used to reset), forgot-password's honest "not available" message when no SMS gateway is configured, `customer_accounts_enabled=false` 404s the login/register routes, cross-company isolation (same phone+password on a different company's domain does not log in), product search filters correctly.
- Full `php artisan test` — 337 passed (1484 assertions), no regressions. `npm run build` succeeds. Browser-verified live on a domain-routed company (Main Company's `domain` temporarily pointed at `localhost` for testing, reverted after): search returns correct results and keeps the query through sort; registered, was auto-logged-in and redirected to a pre-filled profile; header icon showed the "R" initial avatar; checkout pre-filled name/phone/email; logged out (redirected to login on visiting the profile page); logged back in with the **email** instead of the phone. Test customer row and the temporary domain change were both cleaned up afterward — no fabricated data left in the demo database.

Also in this batch — removed the header announcement bar (owner request):

- `layout.blade.php` — dropped the "Official storefront - live catalog, direct ordering" strip that sat above the header on every storefront page (unrelated to the "Official storefront" hero eyebrow tag on the homepage, which stays). `StorefrontFoundationTest`'s assertion on that exact string was removed. `php artisan test --filter=Storefront` — 72 passed (383 assertions). `npm run build` succeeds.

Also in this batch — `/web-design-guidelines` review of the login/register/logout profile-icon flow (owner re-described the exact behavior already built, invoked via the design-guidelines skill; reviewed the account auth views specifically rather than treating it as a new feature request):

- `account/login.blade.php:43,72` (confirmed in-browser, not just read from the code): `autofocus` on both the login and register panels' first field silently did nothing — `x-cloak` hides both tab `<div>`s (`display:none`) at initial parse, and the browser's one-time autofocus pass skips elements that aren't focusable at that moment; by the time Alpine boots and reveals the correct panel, autofocus has already been processed and discarded. Verified with `document.activeElement` — was `<body>` on both `/account/login` and `/account/register` before the fix. Fixed by replacing the native attribute with `x-init="$nextTick(() => ...)"` + `x-ref` on each panel's first field, focusing it once Alpine has settled — and now also re-focuses the newly-shown panel's field when the user clicks between the two tabs (previously nothing happened on tab switch either).
- `account/login.blade.php:12-32` — the Login/Create account toggle used `role="tab"`/`role="tablist"`/`aria-selected`, i.e. real ARIA Tabs semantics, but the panels had no matching `role="tabpanel"`/`aria-controls`, and there was no arrow-key navigation — a half-implemented pattern is worse than none, since screen readers announce "tab" then keyboard behaviour doesn't match. Simplified to what the widget actually is: a two-option toggle (`role="group"` + `aria-pressed` on each button), which is fully correct without needing a keyboard model this simple toggle doesn't need.
- `account/login.blade.php:92`, `reset-password.blade.php:30`, `profile.blade.php:66` — the three password-creation fields (register, reset, change-password) enforce `minlength="8"` but never showed that requirement as visible text; added "At least 8 characters." under each, wired via `aria-describedby`.
- `php artisan test --filter=Storefront` — 72 passed (383 assertions), no regressions. `npm run build` succeeds. Browser-verified the autofocus fix directly (`document.activeElement.id` is now `identifier` on `/account/login` and `name` on `/account/register`) and the toggle buttons' `aria-pressed` state.

Deploy: `php artisan migrate` **and `npm run build`** required (new Tailwind classes + JS-independent Alpine markup, no new JS bundle dependency).

Commit status: pending owner approval.

## 2026-07-18 - Automatic image optimization (WebP) + R2 storage groundwork

Reason:

- Pre-existing WIP found in the working tree (image optimizer wired into forms but its composer packages were never installed — uploads would have fataled). Owner asked to complete it.

Changed files:

- `app/Services/ImageOptimizerService.php` + `app/Filament/Concerns/OptimizesUploadedImages.php` (new, were untracked) — resize + EXIF-strip + WebP re-encode on upload; SVG/animated-GIF passthrough.
- Form opt-ins (were already edited): ProductForm (featured + gallery), CategoryForm, CompanyResource logo, StorefrontSettingResource logos/banners, StorefrontSlideResource images.
- `composer.json` + `composer.lock` — `intervention/image` ^3.9 and `league/flysystem-aws-s3-v3` ^3.25 now actually installed (lock updated; this was the missing piece).
- `config/filesystems.php` + `.env.example` — inactive `r2` disk + `R2_*` vars (activation needs a Cloudflare bucket/token from the owner; no app code references the disk yet).
- `tests/Feature/ImageOptimizerTest.php` (new) — 5 tests: resize to 1600px cap, no upscaling, 800px compact cap, SVG untouched, GIF untouched.
- `CHANGELOG.md` [1.19.0]; `ReleaseNotesTest` bumped to v1.19.0 / 2026-07-18.

Also in this batch — Chat Channel not saving on live:

- Root cause: with "All Companies" selected, `BelongsToCompany` falls back to the default company on create, so the channel saved under "Main Company" and never showed under the owner's real company; retrying then hit the global `provider+external_id` unique index as a 500. Reproduced/ruled out DB issues against a scratch MySQL 8.4 database (model-level creates succeed, encrypted casts fine).
- `app/Filament/Resources/ConversationChannels/ConversationChannelResource.php` — required Company selector visible only in All-Companies mode (Courier Provider pattern); Company column in the table in that mode; `unique` validation on external_id scoped to provider with a human-readable message.
- `tests/Feature/ConversationChannelResourceTest.php` (new) — 3 tests.

Verification: `ImageOptimizerTest` 5 passed (13 assertions) — real GD WebP encode through Livewire's temporary-upload path; GD WebP support confirmed on this machine; r2 disk config shape validated with throwaway creds. Full `php artisan test` — 315 passed (1318 assertions) including the Chat Channel fix below.

Deploy notes: run `composer install` on the live server after pulling; PHP GD extension with WebP support required (standard on most hosts). No migration.

Commit status: Not committed yet — awaiting owner approval.

## 2026-07-17 - Chat-order UX polish, WhatsApp Business-style Inbox, realtime chat, catalog with images, app pull-to-refresh

Reason:

- Owner's live testing feedback, batch 1: (a) random "Error while loading page" toast in the app's Inbox; (b) order links in chat bubbles were plain text; (c) thank-you page needed a back-to-inbox button; (d) order form/thank-you pages should feel premium/modern; (e) the Capacitor app has no way to reload a page.
- Batch 2: (f) Inbox should feel like WhatsApp Business (desktop + mobile) while keeping Filament UI; (g) catalog sending with product images; (h) sending an order form closed the open chat back to the empty state; (i) sent messages took ~10s (poll) to appear; (j) order form quantity change didn't recalculate the total; (k) new messages didn't surface/scroll like WhatsApp.

Changed files:

- `resources/views/chat-order/{layout,show,success,closed}.blade.php` — full modern redesign (Hind Siliguri font, gradients, animated checkmark, dark mode); success/closed pages show "ইনবক্সে ফিরে যান" for logged-in staff, "ফিরে যান" (history back) for customers; `show` adds product image thumbnails + vanilla-JS live grand-total recalculation on quantity change.
- `resources/views/filament/pages/inbox.blade.php` — WhatsApp Business-style rewrite: avatars + last-message previews + unread pills in the list, mobile list ⇄ full-screen thread with back button, chat wallpaper, date separators, delivery ticks, image bubbles, auto-scroll/follow-scroll (Alpine + MutationObserver), pill composer (Enter sends, auto-grow), "+" catalog panel with image preview; `wire:poll.visible.5s`; root `data-zz-no-reload`.
- `app/Filament/Pages/Inbox.php` — `deselectConversation()` (mobile back), catalog panel state, order-form message now carries product name/price + image; scroll-bottom dispatches after send/select.
- `app/Models/ConversationMessage.php` — `bodyHtml()` linkify helper + new `mediaImageUrl()`.
- `app/Models/Conversation.php` — `latestMessage()` `latestOfMany` relation for list previews.
- `app/Services/Crm/ConversationMessengerService.php` — optional media URL: WhatsApp image+caption message, Messenger image then text; archives `media_path`/`media_mime`.
- `app/Providers/Filament/AdminPanelProvider.php` — Chrome-style pull-to-refresh script (Capacitor/Android WebView only); `notificationsSent` reload now skips pages with `data-zz-no-reload` (fixes the Inbox closing after "Order link sent.").
- `tests/Feature/InboxPageTest.php` — new (reply archiving + state kept, catalog image on link/message, `mediaImageUrl()` resolution).
- `CHANGELOG.md` [1.18.0]; `ReleaseNotesTest` at v1.18.0.

Verification: `ChatOrderLinkTest|ConversationIngestTest|AiAutoReplyTest` (24 passed) + new `InboxPageTest` (3 passed); `view:cache` compiles clean; browser-verified locally: live total recalculates instantly (qty 2→5: ৳4,400→৳11,000), product image renders on the order form. Full `php artisan test` — 307 passed (1282 assertions). Smoke-test data removed from the demo DB.

Commit status: Committed and pushed with owner approval on 2026-07-18 (`859eed09`, v1.18.0).

## 2026-07-17 - Hotfix: orders.status enum breaks order creation on MySQL (live 500)

Reason:

- Owner reported: submitting the chat order form (`/o/{token}`) on the live server returns 500, while local works. Root cause: `orders.status` is still the original `enum('pending','processing','completed','cancelled')` from the 2026-05-25 migration; the app writes `'draft'`/`'confirmed'`. SQLite (local) ignores enum constraints; MySQL strict mode (production per `docs/deployment.md`) rejects the insert.

Changed files:

- New migration `database/migrations/2026_07_17_030000_change_orders_status_to_string.php` — `orders.status` → `string(20)` default `draft`.
- `CHANGELOG.md` [1.17.1] hotfix entry; `ReleaseNotesTest` bumped to v1.17.1.

Verification: full `php artisan test` — 304 passed (1266 assertions).

Deploy notes: run `php artisan migrate` on the live server after pulling. If the 500 persists afterwards, check `storage/logs/laravel.log` for the actual exception.

Commit status: Committed and pushed with owner approval on 2026-07-17 (`86879f4c`, v1.17.1). Confirmed fixed on live: order ZZIN-20260717-0001 succeeded.

## 2026-07-17 - Lead/CRM module (steps 1–14), banners, single-column admin layout

Reason:

- Owner attached `02_LEAD_CRM_MODULE_PLAN.md` and asked to implement it step by step. All plan steps (1–11, 13–14) are done: Lead/Quotation core, Conversation Inbox + click-to-order links, and the AI auto-reply assistant with CTWA 72h window. Also includes the earlier pending multi-image product-taggable storefront banners and the app-wide single-column Filament form layout.

Changed files (high level):

- New migrations: `2026_07_16_090000` (banner tags), `2026_07_17_000000` (lead/quotation tables), `2026_07_17_010000` (conversations/chat-order tables), `2026_07_17_020000` (AI + CTWA fields, `company_faqs`).
- New models: Lead, LeadActivity, Quotation, QuotationItem, ConversationChannel, Conversation, ConversationMessage, ChatOrderLink, CompanyFaq (+ Customer/Order/StorefrontSetting additions).
- New services: `app/Services/Crm/` (LeadConversionService, ConversationMessengerService, AiSettingsService, AiLlmClient, AiReplyService); jobs StoreIncomingMessageJob, DownloadConversationMediaJob, AiAutoReplyJob; command `quotations:mark-expired`.
- Controllers/routes: MetaWebhookController (`/webhooks/meta`, CSRF-exempt), QuotationPublicController (`/quotation/{number}`), ChatOrderController (`/o/{token}`).
- Filament: Leads, Quotations, ConversationChannels, CompanyFaqs resources; Inbox + AI Assistant Settings pages; 37 existing form/infolist files switched to single-column sections.
- Views: `resources/views/quotations/`, `chat-order/`, `filament/pages/{inbox,ai-assistant-settings}.blade.php`, storefront `banner-carousel` partial + home/layout updates.
- Tests: LeadTest, LeadConversionTest, QuotationTest, ConversationIngestTest, ChatOrderLinkTest, AiAutoReplyTest, StorefrontBannerTest; isolation contract extended; ReleaseNotesTest bumped to v1.17.0.
- Docs: `CHANGELOG.md` [1.17.0]; `PROJECT_GUIDE.md` Lead/CRM section; plan file status marks.

Verification: full `php artisan test` — 304 passed (1266 assertions). Browser-verified: Leads/Quotations pages, Inbox (Filament-styled), manual conversation → order link → public form → order created, AI Assistant Settings and FAQs pages. Smoke-test data cleaned from the demo DB afterwards.

Deploy notes: run `php artisan migrate`; a queue worker must be running (webhook ingest + AI replies are queued); scheduler already required (`quotations:mark-expired` daily 00:30). Meta webhook URL + verify token per channel are set in the new Conversation Channels resource; AI provider key in AI Assistant settings — all encrypted, admin-configurable.

Commit status: Committed and pushed with owner approval on 2026-07-17.

## 2026-07-15 - WooCommerce sync button

Reason:

- Owner reported that saving WooCommerce Consumer Key/Secret/Site URL in Storefront Settings did nothing — the import only ever ran via `php artisan woocommerce:import-products {company-slug}` from a server terminal; there was no admin-panel action to trigger it.

Changed files:

- `app/Filament/Resources/StorefrontSettings/StorefrontSettingResource.php` — new `syncWooCommerceAction()` / `hasWooCommerceCredentials()` helpers; "Sync WooCommerce" added to the list `recordActions`.
- `app/Filament/Resources/StorefrontSettings/Pages/EditStorefrontSetting.php` — same action added to the edit page's header actions, so it's reachable from the form the owner was actually looking at.
- `CHANGELOG.md` — new `[1.16.0]` Minor Feature entry; `tests/Feature/ReleaseNotesTest.php` bumped to v1.16.0.

Verification: full `php artisan test` — 264 passed (1127 assertions). Manually verified in the browser: button stays hidden while a record's WooCommerce fields are empty, appears once a site URL + consumer key + secret are saved, and disappears again once cleared (test credentials were saved and then removed during verification — no import was actually run against a live site).

Commit status: Not committed. Commit and push require explicit owner approval.

## 2026-07-14 - Storefront redesign Phase 4 (Polish) — plan complete

Reason:

- Final phase of `STOREFRONT_REDESIGN_PLAN.md` (Phases 1-3 landed earlier today). Phase 4 covers section 8's polish items: offer countdown, scroll animation, Best Sellers/New Arrivals (already delivered in Phase 1), and a performance budget check.

Changed files:

- New migration `database/migrations/2026_07_14_140000_add_offer_countdown_to_storefront_settings_table.php` — `offer_title`, `offer_discount_percent`, `offer_ends_at` on `storefront_settings`.
- `app/Models/StorefrontSetting.php` — new fillable/casts, `hasActiveOffer()` helper.
- `app/Filament/Resources/StorefrontSettings/StorefrontSettingResource.php` — new "Offer Countdown" section.
- `resources/views/storefront/home.blade.php` — sitewide flash-sale countdown banner (Alpine-powered live ticking countdown, auto-hides once expired); `x-reveal` scroll-animation attribute added to the category grid, featured products, and each carousel section.
- `resources/js/app.js` — small custom `Alpine.directive('reveal', ...)` (IntersectionObserver-based fade-up, respects `prefers-reduced-motion`, no-ops without `IntersectionObserver` support) — no new dependency.
- Tests: `tests/Feature/StorefrontOfferCountdownTest.php` (new).
- `CHANGELOG.md` — new `[1.15.0]` Minor Feature entry, marking the 4-phase plan complete; `tests/Feature/ReleaseNotesTest.php` bumped to v1.15.0.

Deliberately deferred (flagged, not silently skipped):

- Per-product-scoped offers — the plan's admin table originally described a scoped "Offer/Flash sale" resource; this phase ships a single sitewide countdown banner instead (title + %  + end time on the existing Storefront Settings), which covers the core UX ask without a new table/resource. A per-product scoped version would be its own follow-up.
- A formal Lighthouse audit — no Lighthouse/CI tooling is wired into this repo; verified the build stays within the plan's JS budget and that images already lazy-load with explicit dimensions (from Phases 1-2), but did not fabricate a Lighthouse score without actually running one.

Verification: full `php artisan test` — 264 passed (1127 assertions). `npm run build` — 88.96 kB JS / 32.92 kB gzip (unchanged from Phase 1, still within the plan's <60KB gzip budget — the reveal directive added no new dependency). Manually verified in the browser preview on the demo Main Company storefront: countdown banner ticks live (04h 59m 34s → decreasing), disappears when `offer_ends_at` is in the past, and the `x-reveal`'d sections correctly lose their `opacity-0`/`translate-y-3` classes once scrolled into view. No console errors.

Deploy notes: new migration — run `php artisan migrate` on deploy. No new JS dependency.

Commit status: Not committed. Commit and push require explicit owner approval.

**This completes all 4 phases of `STOREFRONT_REDESIGN_PLAN.md`.** Nothing has been committed across any of the 4 phases yet — 15 CHANGELOG-documented versions of work ([1.12.0] through [1.15.0], following [1.11.0] Voucher module and [1.10.0] audit remediation) are sitting in the working tree awaiting review and commit approval.

## 2026-07-14 - Storefront redesign Phase 3 (One-Page Checkout & Payments)

Reason:

- Continuing the phased `STOREFRONT_REDESIGN_PLAN.md` implementation (Phases 1-2 landed earlier today). Phase 3 covers section 5 of the plan (checkout/payments), scoped down after research: the ZiniPay gateway already covers online pre-order payments, and a real bKash/Nagad gateway API integration needs merchant credentials this task doesn't have — so this phase adds delivery-area charges and a manual bKash/Nagad "Send Money + TrxID" flow with admin verification, on top of the existing COD/ZiniPay paths.

Changed files:

- New migration `database/migrations/2026_07_14_130000_add_checkout_settings_to_storefront_settings_table.php` — `cod_enabled`, `delivery_charge_inside/outside`, `manual_bkash_number/instructions`, `manual_nagad_number/instructions` on `storefront_settings`.
- `app/Models/StorefrontSetting.php` — new fillable/casts, `cod_enabled` defaults true on create.
- `app/Filament/Resources/StorefrontSettings/StorefrontSettingResource.php` — new "Checkout & Delivery" section.
- `app/Http/Controllers/Storefront/CheckoutController.php` — `createOrder()` validates `delivery_area`/`payment_method`/`sender_number`/`trx_id` (all optional, defaulting to `inside`/`cod` so existing callers keep working); computes the delivery charge from the setting and stores it on the order's existing `shipping_zone`/`shipping_fee` fields; creates a `pending` `StorefrontPayment` for manual bKash/Nagad with the sender number and TrxID.
- `resources/views/storefront/checkout/show.blade.php` — redesigned with an Alpine-powered delivery-area toggle and payment-method radio cards (COD / bKash / Nagad, each hidden until the admin configures its number), with a live-updating delivery charge + total in the order summary.
- `resources/views/storefront/checkout/success.blade.php` — manual bKash/Nagad payments get their own "we are verifying your payment" wording, distinct from the existing pre-order online-advance message.
- New Filament resource `app/Filament/Resources/StorefrontPayments/` (Storefront > Storefront Payments) — admin list with Verify/Reject actions for pending manual payments.
- Tests: `tests/Feature/StorefrontManualPaymentTest.php` (new).
- `CHANGELOG.md` — new `[1.14.0]` Minor Feature entry; `tests/Feature/ReleaseNotesTest.php` bumped to v1.14.0.

Deliberately deferred (flagged, not silently skipped):

- Real bKash/Nagad gateway API (automatic, no TrxID entry) — needs actual merchant API credentials the store doesn't have configured; the existing ZiniPay integration continues to serve the pre-order online-advance use case.
- bn/en `lang/` localization — no `lang/` directory exists in this repo; this is a cross-cutting change touching every storefront view, not something to bolt onto the checkout phase alone.
- Returning-customer address autofill by phone (plan's "phase 2" nice-to-have within section 5) — not started.

Verification: full `php artisan test` — 261 passed (1120 assertions). `npm run build` succeeds (no new JS dependency). Manually verified in the browser preview on the demo Main Company storefront: Buy Now → checkout page shows delivery-area cards and live total; selecting bKash reveals the sender-number/TrxID fields and the configured Send Money number. No console errors.

Deploy notes: new migration — run `php artisan migrate` on deploy. Admin must configure delivery charges and/or bKash/Nagad numbers in Storefront Settings before those options appear at checkout (COD works with no configuration, matching prior behaviour).

Commit status: Not committed. Commit and push require explicit owner approval.

## 2026-07-14 - Storefront redesign Phase 2 (Product Page)

Reason:

- Continuing the phased `STOREFRONT_REDESIGN_PLAN.md` implementation (Phase 1 landed earlier today). Phase 2 covers section 4 of the plan (product page). Research first: the gallery, tiered/wholesale pricing table, variant option table, and related-products grid were already built in an earlier session — only the remaining gaps needed work.

Changed files:

- `app/Http/Controllers/Storefront/CartController.php` — `addToCart()` recognizes an optional `buy_now=1` field; when present, redirects to checkout instead of back. New `redirectToCheckout()` helper resolves the live vs. preview checkout route from the request's bound `company` route parameter.
- `resources/views/storefront/products/show.blade.php` — added a "Buy now" button next to "Add to cart" (single-variant products only); added a mobile-only sticky action bar (`fixed bottom-16`, sits above the existing bottom nav) with the same two actions wired to the main form via `form="product-purchase-form"`; moved the product description into a new Alpine-powered Description / Shipping & Return tab section below the buy box.
- Tests: `tests/Feature/StorefrontBuyNowTest.php` (new).
- `CHANGELOG.md` — new `[1.13.0]` Minor Feature entry; `tests/Feature/ReleaseNotesTest.php` bumped to v1.13.0.

Deliberately deferred (flagged, not silently skipped):

- Specification tab — no key-value spec field exists on `Product`; would need its own migration and admin UI, not bolted onto this pass.
- Import shipping-cost-breakdown panel (plan's optional item for imported products) — no admin-configurable air/sea per-kg rate fields exist yet; `Purchase::CHINA_TO_BD_COST_FIELDS` is a purchasing-side-only concept today with no storefront equivalent.

Verification: full `php artisan test` — 257 passed (1104 assertions). `npm run build` succeeds (no new JS dependency this phase). Manually verified in the browser preview on the demo Main Company storefront (`/storefront/main-company/product/barcode-scanner`): Buy now button, sticky mobile bar, and tab switching all confirmed working (tab switching verified via a dispatched click event after a browser-automation quirk affected a synthetic `.click()` call — Alpine's reactivity and `@click` binding both confirmed correct). No console errors.

Deploy notes: no new migration, no new JS dependency — safe to deploy without a rebuild step beyond the usual `npm run build`.

Commit status: Not committed. Commit and push require explicit owner approval.

## 2026-07-14 - Storefront redesign Phase 1 (Foundation & Home)

Reason:

- Owner asked to implement `STOREFRONT_REDESIGN_PLAN.md`. The plan itself is phased (4 phases) with tests/build/changelog and owner approval before each commit, so this pass covers Phase 1 only: hero slider, category images, trust strip, product card v2, Alpine.js, and home-data caching. Phases 2-4 (product page redesign, one-page checkout, performance polish) are separate follow-up work, not started.

Changed files:

- New migration `database/migrations/2026_07_14_120000_create_storefront_slides_table.php` — `storefront_slides` table, `categories.image`, `storefront_settings.trust_strip_delivery/return/payment`.
- New model `app/Models/StorefrontSlide.php` (`BelongsToCompany`, added to `MultiCompanyIsolationTest`), `activeNow()` scope (is_active + optional start/end window), `forCompany()` cached lookup.
- `app/Models/Category.php` — `image` fillable; cache-bust hook.
- `app/Models/StorefrontSetting.php` — `trust_strip_*` fillable; cache-bust hook.
- New Filament resource `app/Filament/Resources/StorefrontSlides/` (Storefront > Hero Slides).
- `app/Filament/Resources/Categories/Schemas/CategoryForm.php` — image upload field.
- `app/Filament/Resources/StorefrontSettings/StorefrontSettingResource.php` — new "Trust Strip" section.
- `app/Http/Controllers/Storefront/HomeController.php` + `app/Http/Controllers/Storefront/PreviewController.php` — pass `slides` to the homepage view.
- `resources/views/storefront/home.blade.php` — Alpine-powered hero slider (autoplay, dots, `prefers-reduced-motion` guard, fetchpriority on the first slide) with graceful fallback to the existing static banner when no slides exist; trust strip section; category cards now show images with a mobile horizontal-scroll row.
- `resources/views/storefront/partials/product-card.blade.php` — discount badge + struck-through compare price when `sale_price < price`; quick-add button no longer hover-only (mobile has no hover); lazy-loaded images.
- `resources/js/app.js` + `package.json` — added Alpine.js.
- `resources/css/app.css` — `[x-cloak]` rule.
- Tests: `tests/Feature/StorefrontSlideTest.php` (new); `tests/Feature/MultiCompanyIsolationTest.php` (StorefrontSlide contract).
- `CHANGELOG.md` — new `[1.12.0]` Minor Feature entry; `tests/Feature/ReleaseNotesTest.php` bumped to v1.12.0.

Deliberately deferred (flagged, not silently skipped):

- Full Intervention Image/WebP resize pipeline (plan's performance section) — today's image uploads use the same plain `FileUpload` pattern as every other image field in the app; a proper resize/WebP/srcset pipeline is cross-cutting and belongs in its own change, not bolted onto just the new fields.
- Caching of the products/categories homepage queries — only the new slides list is cached (10 min, invalidated on save). Product/category listings change too often (stock, availability) to risk staleness without more design; flagged for a follow-up.
- Flash Sale/offer countdown strip, Best Sellers/New Arrivals as distinct homepage sections beyond the existing `ProductCarousel` mechanism, scroll-reveal animations — plan items 3.3/3.4 nice-to-haves, left for Phase 4 polish.

Verification: full `php artisan test` — 255 passed (1099 assertions). `npm run build` — 88.55 kB JS / 32.75 kB gzip (plan budget: <60KB gzip JS). Manually verified in the browser preview: hero slide, trust strip line, and category image grid all render correctly on the demo Main Company storefront (`/storefront/main-company`); no console errors.

Deploy notes: new migration — run `php artisan migrate` on deploy. `npm run build` required (new JS dependency).

Commit status: Not committed. Commit and push require explicit owner approval.

## 2026-07-14 - Voucher & Fund Control module

Reason:

- Owner asked to implement `05_VOUCHER_FUND_CONTROL_MODULE_PLAN.md`. Two decisions the plan flagged as needing explicit owner confirmation were confirmed before building: (1) existing direct Customer/Supplier Payment and Expense creation stays fully supported alongside vouchers (voucher is optional, not mandatory); (2) `capital_investment` vouchers stay Mudarabah-ready (route through `resulting_model_type`) rather than fully separate, since the Mudarabah investor module doesn't exist yet.

Changed files:

- New migration `database/migrations/2026_07_14_000000_create_voucher_and_fund_control_tables.php` — `fund_sources`, `vouchers`, `voucher_attachments`, `fund_transfers`, plus `purchases.funding_sources` (JSON).
- New models: `app/Models/FundSource.php`, `app/Models/Voucher.php`, `app/Models/VoucherAttachment.php`, `app/Models/FundTransfer.php` — all `BelongsToCompany`, added to `MultiCompanyIsolationTest`'s contract.
- New services: `app/Services/VoucherService.php` (submit/verify/approve/reject/cancel + the transaction-type → accounting-effect matrix, Rule 1 enforced), `app/Services/FundTransferService.php` (double-entry ledger transfer).
- `app/Models/TransactionLedger.php` — added `voucher_credit`/`voucher_debit`/`fund_transfer` ledger types.
- `app/Models/Purchase.php` — `funding_sources` fillable/cast.
- `app/Models/User.php` — new `voucher.*`/`fund_source.manage`/`fund_transfer.*`/`finance.dashboard` permissions, mapped onto existing roles, plus `canX()` helper methods.
- New Filament resources: `app/Filament/Resources/Vouchers/`, `app/Filament/Resources/FundSources/`, `app/Filament/Resources/FundTransfers/` (Verify/Approve/Reject/Cancel/Print Receipt actions).
- Money Receipt: `app/Http/Controllers/Admin/VoucherReceiptController.php` + `resources/views/vouchers/receipt.blade.php`, reached via a signed `vouchers.receipt` route (no login required, signature can't be guessed) — added to `routes/web.php`.
- Tests: `tests/Feature/VoucherWorkflowTest.php`, `tests/Feature/AccountingRulesTest.php` (Rule 1 + fund-transfer + over-funding guard), `tests/Feature/VoucherIsolationTest.php`.
- `CHANGELOG.md` — new `[1.11.0]` Minor Feature entry; `tests/Feature/ReleaseNotesTest.php` bumped to v1.11.0 / Minor Feature / 2026-07-14.

Deliberately deferred (flagged, not silently skipped):

- Module plan step 9 (automatic voucher creation from Purchase/Expense/SupplierPayment/Order events) — wiring this in without risking a double-booked financial record (a manual voucher plus an auto one for the same event) needs its own careful pass; not rushed into this change.
- Purchase form's funding-sources repeater UI (nice-to-have; the `purchases.funding_sources` JSON column exists and the actual fund deduction/validation already works correctly through per-source `inventory_purchase` vouchers referencing `purchase_id`).
- Threshold-based approval routing and the shared `ApprovalGateService` — that service belongs to the not-yet-built Task/Approval Workflow module; `VoucherService` ships with simple inline approval logic per the plan's own documented fallback, with a comment marking the future migration path.

Verification: full `php artisan test` — 252 passed (1084 assertions). No frontend asset build needed (Filament PHP resources + Blade-only views).

Deploy notes: new migration — run `php artisan migrate` on deploy. No queue/cron/env changes.

Commit status: Not committed. Commit and push require explicit owner approval.

## 2026-07-13 - Code audit remediation (security + reliability hardening)

Reason:

- Owner asked to resolve the findings in `CODE_AUDIT_REPORT.md` one by one.

Changed files:

- `app/Models/Concerns/GeneratesSequentialNumber.php` (new) — retries the INSERT on a UNIQUE violation of a document-number column, regenerating the number each attempt. Applied to `Order` and `Purchase` (`app/Models/Order.php`, `app/Models/Purchase.php`). **Audit M-1.**
- `app/Http/Controllers/Storefront/Concerns/MatchesCustomerPhone.php` (new) — shared +880/0/formatting-tolerant customer-phone match.
- `app/Http/Controllers/Storefront/OrderTrackController.php` + `resources/views/storefront/track/show.blade.php` — order tracking now requires a matching phone as a second factor (order number alone is guessable). **Audit M-2.**
- `app/Http/Controllers/Storefront/AccountOrdersController.php` + `resources/views/storefront/account/orders.blade.php` — removed the customer outstanding-balance figure from the phone-only history page; refactored the phone match onto the shared trait. **Audit M-3.**
- `app/Scopes/CompanyScope.php` — documented the context contract (none=fail-closed, all/cleared=unscoped). Runtime behaviour intentionally unchanged (storefront guest binding depends on cleared=unscoped + ownership checks). **Audit M-4.**
- `app/Services/StockMovementService.php` — stock recompute now sums signed quantity in SQL instead of loading all movements into PHP. **Audit L-3.**
- `app/Services/StorefrontCart.php` — named `PREORDER_STOCK_CEILING` constant (**L-4**); replaced inline FQ class refs with imports (**L-6**).
- `app/Models/CustomerBlacklist.php` — documented the deliberate `CompanyScope` omission. **Audit L-2.**
- `config/app.php` + `app/Support/AdminPassword.php` — seeder admin password now read via `config('app.seed_admin_password')` not raw `env()`. **Audit L-6.**
- `.env.production.example` (new), `docs/deployment.md`, `.env.example` — production hardening guidance: MySQL/Postgres not SQLite, non-sync queue + worker, `APP_ENV=production`/`APP_DEBUG=false`. **Audit H-1, H-2, L-1** (deploy/ops; app defaults unchanged by design).
- Tests: `tests/Feature/SequentialNumberConcurrencyTest.php` (new, M-1), `tests/Feature/OrderFormTest.php` (new, L-5 form-layer), `MultiCompanyIsolationTest::test_company_context_boundary_states` (new, M-4 guard), updated `StorefrontFoundationTest`/`StorefrontB2bTest` for the new tracking/balance behaviour.
- `CHANGELOG.md` — new `[1.10.0]` Security entry; `tests/Feature/ReleaseNotesTest.php` bumped to v1.10.0 / Security / 2026-07-13.
- `CODE_AUDIT_REPORT.md` — marked each finding with its resolution.

Verification: full `php artisan test` — 238 passed (1042 assertions). No frontend asset build needed (Blade-only view changes).

Deploy notes: no new migration. H-1/H-2 are ops actions — set a real DB and a non-sync queue with a worker per `.env.production.example`.

Commit status: Not committed. Commit and push require explicit owner approval.

## 2026-07-12 - Fix Purchase "Save changes" always failing when an item is added

Reason:

- Owner reported (with a mobile screenshot): on Create/Edit Purchase, adding a product item then clicking "Save changes" always shows "Error while loading page", and the item disappears again after reloading — nothing was actually being saved.

Changed files:

- `app/Filament/Resources/Purchases/Schemas/PurchaseForm.php` — added `->dehydrated(false)` to the `allocated_cost` and `landed_unit_cost` read-only fields inside the `items` Repeater.
- `tests/Feature/PurchaseTest.php` — new regression test `test_create_purchase_form_saves_items_without_null_cost_column_error`, driving the actual `CreatePurchase` Livewire page via `Livewire::test()->fillForm()->call('create')`, since the existing Purchase tests all created `PurchaseItem` rows directly and never exercised the Filament form/repeater save path where this bug lived. Verified the test fails with the exact reported `NOT NULL constraint failed: purchase_items.allocated_cost` error when the fix is reverted, and passes with it applied.
- `CHANGELOG.md` — new `[1.9.4]` Critical Fix Update entry; `tests/Feature/ReleaseNotesTest.php` bumped to v1.9.4 / Critical Fix / 2026-07-12.

Root cause:

- `purchase_items.allocated_cost` and `landed_unit_cost` are `NOT NULL` columns with a schema-level `DEFAULT 0`, but both values are only ever computed after the record is saved, by `PurchaseWorkflowService::syncLandedCosts()` (called from `Purchase::saved` → `syncTotalsAndStock()`). The two form fields for them are read-only display fields that are never populated client-side, so Filament's repeater-relationship save included them as explicit `null` values in the insert. SQLite (like most databases) only applies a column's `DEFAULT` when the column is *omitted* from the insert list — an explicit `NULL` bypasses it — so every insert hit the `NOT NULL` constraint and the whole Livewire request 500'd, which is why the item appeared to "vanish" on reload (it was never actually persisted).
- Reproduced locally via the browser preview: confirmed the exact `500` on `livewire/update` and the matching `SQLSTATE[23000]... NOT NULL constraint failed: purchase_items.allocated_cost` entry in `storage/logs/laravel.log`.

Notes:

- With `dehydrated(false)`, these two fields are excluded from the saved payload entirely, so the DB `DEFAULT 0` applies cleanly on insert, and the existing post-save sync then fills in the real computed values — the exact same code path that already ran correctly on every subsequent update of an existing purchase.
- Verified in browser: created a test purchase with one item via the actual Create Purchase form — saved successfully (200 OK, redirected to the purchase's View page), and `allocated_cost`/`landed_unit_cost` were correctly computed afterward. Test purchase deleted from the demo database afterward.
- `php artisan test` — full suite, 234 passed (1017 assertions), no regressions.
- `npm run build` not run — PHP-only change, no frontend assets touched.

Commit status: Not committed. Commit and push require explicit user approval.

## 2026-07-11 - Customer Success pages merged into one page with header tabs

Reason:

- Owner asked to apply the same Courier-style tab consolidation to the Customer Success group (Risk Profiles, Blacklists, Risk Reviews, Risk Events — 4 separate sidebar pages), and to confirm the mobile dropdown's z-index fix applies here too. Then asked to also fold the separate "Risk Rule Settings" page (which had its own `Customer Success` sidebar group, outside the 4 resources) into the same cluster/tab bar.

Changed files:

- `app/Filament/Clusters/CustomerSuccess.php` (new) — same pattern as `app/Filament/Clusters/Courier.php`: `SubNavigationPosition::Top` tabs.
- `app/Filament/Resources/CustomerRiskProfiles/CustomerRiskProfileResource.php`, `CustomerBlacklists/CustomerBlacklistResource.php`, `CustomerRiskReviews/CustomerRiskReviewResource.php`, `CustomerRiskEvents/CustomerRiskEventResource.php` — replaced `$navigationGroup = 'Customer Success'` with `$cluster = CustomerSuccess::class`; added concise `$navigationLabel`s (Risk Profiles, Blacklists, Risk Reviews, Risk Events) for the tab bar.
- `app/Filament/Pages/CustomerRiskSettings.php` — same treatment: `$navigationGroup = 'Customer Success'` replaced with `$cluster = CustomerSuccess::class` and `$navigationLabel = 'Risk Settings'`. `Filament\Pages\Page` supports `$cluster` natively (confirmed in `vendor/filament/filament/src/Pages/Page.php`), same mechanism as resources.
- `tests/Feature/CustomerRiskTest.php` — updated all 5 hardcoded URLs (`/admin/customer-risk-profiles`, `-blacklists`, `-reviews`, `-events`, `-settings`) to their `/admin/customer-success/...` cluster-prefixed equivalents.
- `CHANGELOG.md` — `[1.9.3]` patch entry updated to describe all 5 tabs; `tests/Feature/ReleaseNotesTest.php` stays at v1.9.3 / Patch / 2026-07-11.

Notes:

- The mobile z-index fix (`.fi-dropdown-panel { z-index: 30 }`, added for the Courier cluster in v1.9.1) needed no changes — it's a generic rule that applies to every Filament dropdown panel, confirmed in browser at 375×812: opening the "Risk Profiles ▾" dropdown shows all options clearly above the header.
- Verified in browser at 1400×900: sidebar shows one "Customer Success" entry; 5 tabs (Risk Profiles, Blacklists, Risk Reviews, Risk Events, Risk Settings) render across the header, "Risk Settings" tab renders the actual settings form correctly.
- `php artisan test` — full suite, 233 passed (1010 assertions), no regressions.
- `npm run build` not run — Filament/PHP-only change.

Commit status: Not committed. Commit and push require explicit user approval.

## 2026-07-11 - Auto-notify on deploy + mobile header gap fix

Reason:

- Owner asked that whenever a new version is deployed to the server, users get a notification (sourced from the release notes / CHANGELOG) — no manual "post an announcement" step.
- Owner also pointed out (with a mobile screenshot) that the gap between the header search box and the profile avatar was too wide, and asked for the avatar to sit further left, about 10px, closer to the search box.

Changed files:

- `app/Console/Commands/NotifyLatestRelease.php` (new) — `php artisan release:notify-deploy`. Reads `App\Support\AppRelease::latestPublished()['version']` (the CHANGELOG's top entry — same source the existing Release Notes page already parses) and compares it against an `AppSetting` key (`release.last_notified_version`). First run ever just records the baseline (no notification, so existing installs don't get spammed about every past release retroactively the moment this ships); any later run where the version differs sends a `Notification::make()->success()->sendToDatabase()` to every active user and updates the baseline.
- `bootstrap/app.php` — scheduled the command `everyFiveMinutes()->withoutOverlapping()->onOneServer()`, alongside the existing `backup:database` / `storefront:send-abandoned-cart-reminders` / `couriers:sync-statuses` schedule entries.
- `tests/Feature/ReleaseNotificationTest.php` (new) — 4 tests: baseline-only first run, real notification + baseline update on a version change, no duplicate on a second run for the same version, and a missing/empty-CHANGELOG fallback that doesn't error.
- `app/Providers/Filament/AdminPanelProvider.php` — added `column-gap: 0.375rem` to `.fi-topbar-end` inside the existing `@media (max-width: 640px)` block (same block as the mobile notifications-in-profile-menu change from earlier today), reducing the default `1rem` gap and pulling the avatar in from the screen edge by the same amount.
- `CHANGELOG.md` — added `[1.9.2]` minor entry; `tests/Feature/ReleaseNotesTest.php` bumped to v1.9.2 / Minor Version Update / 2026-07-11.

Notes:

- Ran `php artisan release:notify-deploy` once against the real demo database to confirm it executes cleanly end-to-end (recorded the current CHANGELOG version, `1.9.1` at the time, as the baseline; sent no notifications, which is correct first-run behavior) — then deleted that `app_settings` row afterward so the real first production run starts from a clean baseline rather than my local test run.
- Verified the header gap fix in browser at 375×812: gap between search and avatar visibly tighter, avatar sits further from the screen edge; at 1400×900 (desktop) the gap is unchanged (still the default 16px) since the CSS is scoped to the mobile media query.
- `php artisan test` — full suite, 233 passed (1010 assertions), no regressions.
- `npm run build` not run — no frontend asset changes.

Commit status: Not committed. Commit and push require explicit user approval.

## 2026-07-11 - Courier pages merged into one page with header tabs

Reason:

- Owner pointed out the Courier group had four separate sidebar pages (Providers, Bookings, Status Logs, Webhook Logs) and asked for one page with the four as header tabs instead — click a tab, it loads, no navigating away to a different sidebar item.

Changed files:

- `app/Filament/Clusters/Courier.php` (new) — Filament's built-in Cluster feature (not a hand-rolled tab UI); `$subNavigationPosition = SubNavigationPosition::Top` renders the clustered resources as tabs across the page header instead of a nested sidebar list.
- `app/Filament/Resources/CourierProviders/CourierProviderResource.php`, `CourierBookings/CourierBookingResource.php`, `CourierStatusLogs/CourierStatusLogResource.php`, `CourierWebhookLogs/CourierWebhookLogResource.php` — replaced `$navigationGroup = 'Courier'` with `$cluster = Courier::class` on each (Filament automatically hides clustered resources from the main sidebar, showing only the cluster's single nav item).
- `app/Providers/Filament/AdminPanelProvider.php` — added `->discoverClusters(in: app_path('Filament/Clusters'), for: 'App\Filament\Clusters')`.
- `tests/Feature/CourierIntegrationTest.php` — updated 3 hardcoded URLs from `/admin/courier-*` to `/admin/courier/courier-*` (Filament's standard cluster URL prefix); confirmed no other code referenced the old paths.
- `CHANGELOG.md` — added `[1.9.1]` patch entry; `tests/Feature/ReleaseNotesTest.php` bumped to v1.9.1 / Patch / 2026-07-11.

Notes:

- Verified in browser at 1400×900: sidebar shows one "Courier" entry; the Courier Providers list page shows 4 tabs (Providers, Bookings, Status Logs, Webhook Logs) across the top; navigating directly to each of the 4 new URLs renders correctly.
- `php artisan test` — full suite, 229 passed (997 assertions) after fixing the 2 URL-dependent tests that initially failed with 404s.
- `npm run build` not run — Filament/PHP-only change, no frontend asset changes.

## 2026-07-11 - Fixed mobile tab-dropdown hidden behind sticky header (same Courier change)

Reason:

- Owner reported (with a mobile screenshot) that on the new Courier tab bar, the mobile "Providers ▾" dropdown opened but showed no options — the panel was rendering underneath the page's sticky header instead of above it.

Changed files:

- `app/Providers/Filament/AdminPanelProvider.php` — added `.fi-dropdown-panel { z-index: 30; }` to the existing `STYLES_AFTER` `<style>` block (same block that made the page header `position: sticky; z-index: 20` in an earlier session — that z-index was the root cause, since the dropdown panel had no explicit z-index of its own and lost the stacking fight).

Notes:

- Verified in browser at 375×812 (mobile preset): the "Providers ▾" dropdown now shows all four options (Providers, Bookings, Status Logs, Webhook Logs) clearly above the header; tapping "Bookings" correctly navigates and updates the dropdown label.
- `php artisan test` — full suite re-run, 229 passed (997 assertions), no regressions.
- `npm run build` not run — CSS is inlined via a PHP render hook, not a bundled asset.

## 2026-07-11 - Mobile header: notification bell tucked into profile avatar

Reason:

- Owner asked, for mobile, to move the notification bell icon into the profile/avatar icon on the right side of the header, and add 10px of right padding after the avatar (the header was cramped with hamburger, company switcher, search, bell, and avatar all fighting for space on a 375px-wide screen).

Changed files:

- `app/Providers/Filament/AdminPanelProvider.php` — added a `@media (max-width: 640px)` block to the existing `STYLES_AFTER` `<style>` (same block used for the sticky header and company switcher sizing): `.fi-topbar-end` gets `position: relative; padding-inline-end: 10px`, and `.fi-topbar-database-notifications-btn` (Filament's own bell button class) is absolutely positioned over the avatar's top-right corner at 75% scale.
- CSS-only change — no Blade/Livewire markup touched, so the bell still opens the real notifications panel and the avatar still opens the real user menu; only their visual position/size changed on narrow screens.

Notes:

- Verified in browser at 375×812: bell now sits as a small badge on the avatar's top-right corner, with visible padding before the screen edge; clicking the bell still opens "No notifications" panel correctly; clicking the avatar still opens the user menu (Demo Admin / theme switcher / Sign out) correctly. Desktop (1400×900) is unchanged — bell and avatar still show as separate full-size icons.
- `php artisan test` — full suite re-run, 229 passed (997 assertions), no regressions.
- `npm run build` not run — CSS is inlined via a PHP render hook, not a bundled asset.

## 2026-07-11 - Corrected: notification bell moved inside the profile dropdown, not overlaid on the avatar

Reason:

- Owner clarified the previous fix (bell as a small badge floating on the avatar's corner) was not what they meant by "insert into the profile icon" — they meant the actual dropdown menu that opens when tapping the avatar on mobile (the one showing "Demo Admin" / theme switcher / Sign out); the bell should be a row inside that menu.

Changed files:

- `app/Providers/Filament/AdminPanelProvider.php` — replaced the absolute-position badge CSS with: `.fi-topbar-database-notifications-btn { display: none }` inside the `@media (max-width: 640px)` block (hides the topbar bell entirely on mobile) plus a new `PanelsRenderHook::USER_MENU_PROFILE_AFTER` hook rendering a real menu item. Also fixed a CSS ordering bug caught while testing: the `.zz-mobile-notifications-item { display: none }` base rule must come *before* the media query, not after, or it wins the cascade at every width and the item never shows.
- `resources/views/filament/partials/mobile-notifications-menu-item.blade.php` (new) — a `<x-filament::dropdown.list.item>` with a bell icon and unread-count badge (`auth()->user()->unreadNotifications()->count()`), whose click handler dispatches `$dispatch('open-modal', { id: 'database-notifications' })` — Filament's own mechanism for opening that exact notifications modal (confirmed by reading `vendor/filament/support/resources/views/components/modal/index.blade.php`), so it's the same panel the topbar bell opens, not a duplicate/fake one. Only visible below 640px via the `.zz-mobile-notifications-item` class.

Notes:

- Verified in browser at 375×812: opening the avatar dropdown now shows "Demo Admin" header, then a "Notifications" row with a bell icon, then the theme switcher (sun/moon/system) row, then "Sign out" — tapping "Notifications" opens the real "No notifications" panel. At 1400×900 (desktop) the topbar bell still shows normally and the menu item stays hidden.
- `php artisan test` — full suite re-run, 229 passed (997 assertions), no regressions.
- `npm run build` not run — Blade/CSS-only change.

Commit status: Not committed. Commit and push require explicit user approval.

## 2026-07-09 - Dynamic shipping fee from Set Delivery Fees + dashboard cleanups

Reason:

- Owner asked to hide the Filament "Welcome / Sign out" account widget from the Dashboard (redundant, looked bad next to the other cards).
- Owner pointed out the Courier Provider create/edit form had a duplicate-looking pair of sections ("Set Delivery Fees" and "Courier Delivery Cost" — identical Delivery Type + Inside/Outside/Suburb layout). Confirmed via search that neither `settings.delivery_fees.*` nor `settings.courier_costs.*` was read anywhere else in the codebase (pure UI-only fields). Owner chose to drop "Courier Delivery Cost" and asked whether "Set Delivery Fees" actually does anything dynamically across the app/storefront — it didn't (saved but never read) — owner asked to make it dynamic.
- Clarified two business-rule questions before implementing (owner answers, not invented): (1) the Inside/Outside/Suburb zone for a new order is auto-detected from the customer's address; (2) since no courier is booked yet at order-creation time, the company's first active courier provider's fee is used; (3) since customer addresses are free text with no city/area column, zone matching is driven by an owner-managed keyword list per zone (ERP Settings → Shipping Zones), not a hardcoded city list.

Changed files:

- `app/Providers/Filament/AdminPanelProvider.php` — removed `AccountWidget` from `->widgets()`.
- `app/Filament/Resources/CourierProviders/CourierProviderResource.php` — removed the "Courier Delivery Cost" section.
- `database/migrations/2026_07_09_000000_add_shipping_fee_to_orders_table.php` (new) — `orders.shipping_zone` (nullable string), `orders.shipping_fee` (decimal, default 0). Migrated locally.
- `app/Services/ShippingFeeService.php` (new) — `determineZone()` matches a free-text address (case-insensitive substring) against `companies.settings.shipping_zones[zone]` keyword lists; `feeFor()` combines the matched zone with the company's first active `CourierProvider`'s `settings.delivery_fees[zone]`.
- `app/Models/Order.php` — `shipping_zone`/`shipping_fee` added to `$fillable`/casts; `creating` hook auto-populates them via `ShippingFeeService` when not already set (covers storefront checkout orders, which bypass the Filament form).
- `app/Services/OrderWorkflowService.php` — `sync()`'s `total_amount` calculation now adds `shipping_fee`.
- `app/Filament/Resources/Orders/Schemas/OrderForm.php` — added a live "Shipping Fee" field (with a helper text showing the auto-detected zone) to the Totals section, recomputed whenever the customer changes; folded into the live total preview. Staff can still override the value manually.
- `app/Filament/Pages/CompanySettings.php`, `app/Services/CompanySettingsService.php`, `resources/views/filament/pages/company-settings.blade.php` — added a "Shipping Zones" section (Inside/Outside/Suburb comma-separated area lists), persisted under `companies.settings.shipping_zones`.
- `tests/Feature/ShippingFeeServiceTest.php` (new) — zone matching, fee lookup (with/without a configured courier), and an end-to-end order-creation test asserting `shipping_zone`/`shipping_fee`/`total_amount`.
- `CHANGELOG.md` — added `[1.9.0]` minor entry; `tests/Feature/ReleaseNotesTest.php` bumped to v1.9.0 / Minor Version Update / 2026-07-09.

Notes:

- Fallback behavior when nothing matches: if the address doesn't match any configured keyword, or the company has no active courier provider yet, `shipping_fee` defaults to 0 and the order form shows a note to set it manually — no fee is invented.
- `php artisan test` — full suite re-run, 229 passed (997 assertions), no regressions.
- `npm run build` not run — no frontend asset changes (Filament/Blade only).

Commit status: Not committed. Commit and push require explicit user approval.

## 2026-07-08 - Android WebView network-error resilience (net::ERR_SOCKET_NOT_CONNECTED)

Reason:

- Owner reported the Android app occasionally shows `net::ERR_SOCKET_NOT_CONNECTED` (or similar) at `https://app.zamzamint.com/`, especially around Wi-Fi ↔ mobile data switching or dual-SIM data toggling — a native WebView/socket issue, not a Laravel backend bug. Owner supplied a detailed 8-step Kotlin-oriented implementation plan; adapted it to this app's actual Java + Capacitor (not raw WebView) structure since `MainActivity.java extends BridgeActivity` — Capacitor manages its own `WebViewClient` (`com.getcapacitor.BridgeWebViewClient`) internally, so a plain `WebViewClient` subclass would have silently broken plugin bridging and local-server URL interception.

Changed files:

- `android/app/src/main/java/com/zamzamint/erp/ResilientBridgeWebViewClient.java` (new) — extends Capacitor's `BridgeWebViewClient` (not `WebViewClient`) so `super.onReceivedError()`/`shouldInterceptRequest()`/etc. keep working. Retries only main-frame failures for a specific transient `net::ERR_*` code set (connect, timeout, host lookup, connection reset/refused, network changed, connection closed, socket-not-connected, name-not-resolved, internet-disconnected) up to 3 times, 2.5s apart; on the 4th failure loads a local `file:///android_asset/error.html?target=<real-url>` instead of retrying forever. Resets the retry counter once the real target URL finishes loading successfully.
- `android/app/src/main/java/com/zamzamint/erp/NetworkMonitor.java` (new) — thin wrapper around `ConnectivityManager.registerNetworkCallback`; calls back on `onAvailable()`.
- `android/app/src/main/java/com/zamzamint/erp/MainActivity.java` — overrides `load()` (called by `BridgeActivity.onCreate()` after the bridge is built) to swap in `ResilientBridgeWebViewClient` via the public `Bridge.setWebViewClient()` API, enable `domStorageEnabled`/`LOAD_DEFAULT` cache mode, and register `NetworkMonitor` — when connectivity returns while the local error page is showing, it reloads the real app automatically (no manual tap needed unless all 3 retries were already exhausted first).
- `android/app/src/main/assets/error.html` (new) — static "Connection Problem" page with a Retry button; reads the real target URL from a `?target=` query param (set by `ResilientBridgeWebViewClient`, itself read from `capacitor.config.json`'s `server.url` via `CapConfig.getServerUrl()` — never hardcoded).
- `android/app/src/main/AndroidManifest.xml` — added `ACCESS_NETWORK_STATE` permission (required for `ConnectivityManager.registerNetworkCallback`).
- `CHANGELOG.md` — added `[1.8.1]` patch entry; `tests/Feature/ReleaseNotesTest.php` bumped to v1.8.1 / Patch / 2026-07-08.

Notes:

- Deliberately did **not** implement step 7 (Coolify/Traefik `keepalive_timeout` tuning) from the owner's plan — that's server-side infrastructure, out of scope for this Laravel-repo-side fix, and the plan itself marks it optional/last-resort if the app-side fix isn't enough.
- **Not verified with a real build** — this environment has no local JDK/Android SDK (matches the project's existing pattern of using the `build-android` GitHub Actions CI job instead of local Android Studio). Code was reviewed carefully against Capacitor's actual `Bridge`/`BridgeWebViewClient`/`BridgeActivity` source (`node_modules/@capacitor/android`) to confirm method signatures (`Bridge.setWebViewClient(BridgeWebViewClient)`, `BridgeActivity.load()`, `CapConfig.getServerUrl()`) and Java 21 compatibility (`capacitor.build.gradle` already sets `sourceCompatibility 21`, so lambdas/method references used here are fine) — but the actual manual test checklist from the owner's plan (Wi-Fi↔mobile switch, airplane mode, weak-network simulation, dual-SIM toggle, background/foreground) still needs to be run on a real device or emulator after the next CI build.
- No Laravel/PHP files touched — `php artisan test` not re-run for backend logic, but `ReleaseNotesTest` was re-verified after the version bump (3 passed, 23 assertions).
- `npm run build` not applicable (native Android/Java changes only).

Commit status: Not committed. Commit and push require explicit user approval.

## 2026-07-08 - Auto-reload on save, User Roles moved under Users, per-company dashboard color (revised architecture)

Reason:

- Owner asked for three UI/settings changes: (1) any Filament save/create/delete should auto-reload the page instead of leaving stale form state visible; (2) the "User Roles" page shouldn't have its own sidebar entry — it should be reached from the Users page instead; (3) each company should be able to pick its own admin dashboard color.
- **First attempt (superseded same day):** stored the color as a `CompanySettingsService` setting and set it once via `->colors()` on the panel. Owner reported it only affected list/resource pages — `Product Setup`, `ERP Settings`, `Backups`, `Release Notes`, and `Reports` kept the old color — and asked for a real multi-shade palette generator, not one flat color. Root cause: `->colors()` bakes the palette into the panel config, which isn't necessarily re-evaluated identically across every page type, and a single color isn't the same as a full 50–950 shade ladder.
- Owner then supplied a concrete 8-step architecture (dedicated `companies.dashboard_color` column, `ColorPicker` on `CompanyResource`, a `DynamicColorService` shade generator, and a `PANELS_HEAD`-style render hook injecting CSS custom properties from `CompanyContext` on every request) and asked it to be followed exactly. Implemented that instead.

Changed files:

- `database/migrations/2026_07_08_000000_add_dashboard_color_to_companies_table.php` (new) — `companies.dashboard_color` (varchar(7), default `#F59E0B`), intentionally separate from `StorefrontSetting.theme_color` (customer-facing branding vs admin-panel readability).
- `app/Models/Company.php` — added `dashboard_color` to `$fillable`.
- `app/Filament/Resources/Companies/CompanyResource.php` — added `ColorPicker::make('dashboard_color')` to the form, plus `ColorColumn`/`ColorEntry` on the table/infolist so it's visible without opening edit.
- `app/Services/DynamicColorService.php` (new) — `generateShades(string $hex): array` (50..950 keyed shades) and `cssVariables()`, delegating to Filament's own `Color::generatePalette()` (OKLCH-based) rather than hand-rolling HSL math, so shades render identically to any other Filament `Color::*` palette.
- `app/Providers/Filament/AdminPanelProvider.php` — reverted `->colors()` to the static `Color::Amber` fallback (used for "All Companies" mode); added a `HEAD_END` render hook that reads `CompanyContext` fresh per request and injects `:root { --primary-50: ...; ...; --primary-950: ...; }` from the active company's `dashboard_color` — this is what makes every page type pick it up and switch instantly on company change, no reload/redeploy. Also kept the `SCRIPTS_AFTER` auto-reload-on-save hook and the User Roles/Manage Roles nav changes from the same day (see below).
- `app/Filament/Resources/UserRoles/UserRoleResource.php` — `$shouldRegisterNavigation = false` (dropped from sidebar, still routable).
- `app/Filament/Resources/Users/Pages/ListUsers.php` / `UserRoles/Pages/ListUserRoles.php` — "Manage Roles" / "Back to Users" header actions.
- **Reverted** the first attempt: `app/Services/CompanySettingsService.php` (removed `PRIMARY_COLOR`/`DEFAULT_PRIMARY_COLOR`/`primaryColorHex()`), `app/Filament/Pages/CompanySettings.php` + its Blade view (removed the "Dashboard Color" section — that page is now business profile/branding only, still renamed to "ERP Settings"), `tests/Feature/CompanySettingsTest.php` (removed the 3 primary-color tests).
- `tests/Feature/DashboardColorTest.php` (new) — 5 tests: color injected for the active company, switching company changes the injected shades, "All Companies" falls back to default, shade ladder has all 11 keys, invalid hex falls back to default.
- `CHANGELOG.md` — rewrote the `[1.8.0]` entry to describe the corrected architecture; `tests/Feature/ReleaseNotesTest.php` stays at v1.8.0 / Minor Version Update / 2026-07-08 (no version bump needed, nothing committed yet).

Notes:

- Verified live in a browser end-to-end: set a company's `dashboard_color` via the actual `ColorPicker` on the Company edit form (through Livewire, not just tinker), switched the active company via the real topbar switcher, and confirmed `getComputedStyle(document.documentElement).getPropertyValue('--primary-500')` matched the new color's OKLCH hue on **every** previously-broken page (Product Setup, ERP Settings, Backups, Release Notes, Reports) plus the Dashboard and Customers list — not just resource pages. Reset the test company's color back to `#F59E0B` afterward so no demo data was left altered.
- Discovered along the way: the local dev/demo split matters — `.env` has `DB_CONNECTION=demo`, and an earlier round of manual `tinker` verification calls had accidentally targeted a different, non-demo sqlite file, temporarily setting a company's name to "Test". That was corrected and confirmed to never have touched the real demo data (`Main Company`, `Garments Machinery Company`, etc. were intact throughout).
- The auto-reload-on-save and User Roles nav changes are unchanged from the first attempt and already verified working.
- Full suite verified: `php artisan test` (no `--env` flag) — 225 passed, 983 assertions.
- `npm run build` not required — no frontend asset changes (Blade/PHP only).

Commit status: Not committed. Commit and push require explicit user approval.

## 2026-07-08 - App own-domain root redirect + courier fraud check follow-up fixes

Reason:

- Owner reported (while manually testing on local server) that `app.zamzamint.com` (loaded by both the browser and the Android app shell) should show the login page when signed out and the dashboard directly when signed in, instead of the generic marketing homepage that `/` shows today.
- Also fixed 3 bugs the owner found while testing yesterday's courier fraud check feature: (1) phone numbers stored as `+880...`/`880...` always returned "no history" because the third-party package only accepts local `01...` format; (2) the manual "Courier Fraud Check" button kept showing a stale cached result after adding a new courier's credentials; (3) owner asked for the result to display inline next to the button (color-coded) instead of only in a notification toast.

Changed files:

- `app/Http/Controllers/Storefront/HomeController.php` — added `isAppOwnDomain()` check; when the resolved storefront company is null and the request host matches `config('app.admin_host')`, redirects to `/admin` instead of rendering `marketing.home`.
- `config/app.php` + `.env.example` — added `admin_host` config key sourced from new `ADMIN_APP_HOST` env var (left unset locally, so `/` still shows the marketing page in local/testing — verified via existing `test_local_root_keeps_marketing_homepage`).
- `app/Services/ExternalCourierFraudService.php` — added `normalizePhone()` (converts `+880`/`880` to local `01...` format before calling the package, which throws on non-local format); added a `bypassCache` parameter to `checkByPhone()`; only caches a result when at least one courier actually answered.
- `app/Filament/Resources/Orders/Schemas/OrderForm.php` — manual "Courier Fraud Check" button now passes `bypassCache: true`; replaced the notification-toast result with an inline `Flex`+`Html` status next to the button, color-coded (green/red/gray) against the existing `external_fraud_low_ratio_threshold` setting.
- `tests/Feature/ExternalCourierFraudCheckTest.php` — added phone-normalization test and cache-bypass test.
- `tests/Feature/StorefrontFoundationTest.php` — added `test_app_own_domain_root_redirects_to_admin_panel`.
- `CHANGELOG.md` — added `[1.7.1]` patch entry for the domain redirect; updated `[1.7.0]` with the two courier fraud check fixes; `tests/Feature/ReleaseNotesTest.php` bumped to v1.7.1 / Patch / 2026-07-08.

Notes:

- `ADMIN_APP_HOST` must be set to `app.zamzamint.com` in the production `.env` for the redirect to take effect there; nothing changes for customer storefront custom domains or any other unmatched host.
- Full suite verified: `php artisan test` (no `--env` flag) — 220 passed, then re-verified after the version bump (`ReleaseNotesTest`: 3 passed, 23 assertions).
- `npm run build` not required — no frontend asset changes.

Commit status: Not committed. Commit and push require explicit user approval.

## 2026-07-07 - External courier fraud check (Part 3.8)

Reason:

- Owner added a new master-plan section (Part 3.8) requesting a cross-courier fraud/delivery-history lookup by phone number, live and visible for admin staff, silent/background for storefront checkout. Owner explicitly chose to install `shahariar-ahmad/courier-fraud-checker-bd` (a third-party Composer package) after reviewing that it logs into each courier's own merchant panel (Pathao, Steadfast, RedX) rather than calling an official documented API — no official public fraud-check API could be found for these couriers.

Changed files:

- `composer.json` / `composer.lock` — added `shahariar-ahmad/courier-fraud-checker-bd` v2.0.2.
- `app/Services/ExternalCourierFraudService.php` (new) — cache (24h TTL per phone per company), fail-safe (never throws, skips unconfigured/failing couriers), logs every real external call to `customer_risk_events`.
- `app/Jobs/CheckExternalCourierFraudJob.php` (new) — storefront-side async check; if the cross-courier success ratio is below a configurable threshold, requests a manager review via the existing `CustomerRiskService::requestReview()` gate (same mechanism already used for high-risk/blacklisted orders).
- `app/Http/Controllers/Storefront/CheckoutController.php` — dispatches the job (`->afterCommit()`) right after order creation; customer never sees this.
- `app/Filament/Resources/Orders/Schemas/OrderForm.php` — added a "Courier Fraud Check" button next to the customer select; staff click it to see a live notification with per-courier success/cancel/total and overall ratio.
- `app/Filament/Resources/CourierProviders/CourierProviderResource.php` — added an optional "External Fraud Check (Merchant Panel Login)" section (Pathao/Steadfast/RedX), separate from existing booking API credentials, since this feature needs the courier's website login, not their API keys.
- `app/Services/CustomerRiskSettingsService.php` + `app/Filament/Pages/CustomerRiskSettings.php` — added `external_fraud_low_ratio_threshold` (default 50%), configurable on the existing Risk Rule Settings page.
- `tests/Feature/ExternalCourierFraudCheckTest.php` (new) — 4 tests covering: missing-credentials skip, combined stats + audit log, cache dedupe, low-ratio triggers manager review.
- `CHANGELOG.md` — added `[1.7.0]` entry; `tests/Feature/ReleaseNotesTest.php` bumped to v1.7.0 / Minor Version Update / 2026-07-07.

Notes:

- Pathao has no official public fraud-check API; this feature (and the third-party package) logs into the courier's own website, which is more fragile than a documented API and may break if a courier changes its site. This is a known, accepted trade-off per the owner's explicit choice.
- Full suite verified: `php artisan test` (no `--env` flag) — 217 passed, then re-verified after the version bump (`ReleaseNotesTest`: 3 passed, 23 assertions).
- `npm run build` not required — no frontend asset changes.

Commit status: Not committed. Commit and push require explicit user approval.

## 2026-07-06 - Fix Android app status bar overlap (Android 15 edge-to-edge)

Reason:

- The `[1.6.3]` StatusBar plugin fix (`overlaysWebView: false`) did not fix the overlap on the owner's real test device. Investigated the plugin's Android source (`node_modules/@capacitor/status-bar/android/.../StatusBar.java`) — it only sets legacy `SYSTEM_UI_FLAG_LAYOUT_FULLSCREEN` view flags, which Android 15 (API 35) ignores because API 35 force-enables edge-to-edge layout for apps that target it. `android/variables.gradle` has `targetSdkVersion = 35`, confirming the device is hitting this.

Changed files:

- `android/app/src/main/res/values/styles.xml` — added `android:windowOptOutEdgeToEdgeEnforcement="true"` to `AppTheme` and `AppTheme.NoActionBar` to opt back out of forced edge-to-edge on Android 15; added the `tools:` namespace needed for the `tools:targetApi="35"` guard.
- `CHANGELOG.md` — added `[1.6.4]` patch entry, noting this opt-out attribute may not be honored on a future Android version (would need CSS safe-area-inset padding on the server side instead, at that point).
- `tests/Feature/ReleaseNotesTest.php` — bumped assertion to v1.6.4.

Notes:

- No PHP behavior changed; verified `php artisan test --filter=ReleaseNotesTest` (3 passed, 23 assertions).
- Requires a new APK build + reinstall to test (native theme change, not a web deploy).

Commit status: Not committed. Commit and push require explicit user approval.

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
