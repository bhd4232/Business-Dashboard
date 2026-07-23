# Changelog

All notable production changes to Business Dashboard are documented here.

## [1.21.0] - 2026-07-23

**Release type:** Minor Feature Update

Adds a user-controlled application upgrade flow, reliable in-app update notifications, and personal profile management from the header avatar menu.

### Added

- **Upgrade App in the profile menu:** a highlighted action appears immediately above Sign out only when a newer application build is available. The currently open app does not reload automatically; the user chooses when to upgrade and receives an explicit warning to save unfinished work first.
- **App-update notifications:** the existing Filament notification bell now receives persistent update alerts with a direct Release Notes action. Desktop and mobile unread counts refresh every 15 seconds, and each user gets at most one alert per application build.
- **Profile Settings:** the avatar menu now links to Filament's native profile page, where the signed-in user can safely update their own name, email, and password.

### Changed

- Storefront domain ownership is now unambiguous: Company create/edit no longer exposes writable domain or verification controls, while Site → Settings remains the sole editor. Changing a hostname resets its old verification status, related writes are transactional, and Company/Site Settings save actions now remain in the sticky Filament page header.
- Cloudflare R2 connection tests now validate and stage the values currently entered in the Cloud Storage form instead of silently testing only an older saved copy. Blank encrypted-secret fields retain the stored key, test clicks cannot enable R2, and missing bucket/domain values are identified on their exact fields.
- Application updates are detected from a build identity instead of only the top `CHANGELOG` version, so backend-only changes and multiple builds under the same human-readable version are no longer missed.
- Save-result refresh and Android pull-to-refresh check the loaded build first. If a newer build exists, they reveal the Upgrade App action instead of silently replacing the open app.

### Technical Notes

- Migration `2026_07_23_000000_create_app_update_tracking` adds each user's acknowledged deployment ID and the unique `app_update_deliveries` ledger. Deploy with `php artisan migrate --force`.
- `npm run build` now creates `public/build/deployment.json` from the source-tree hash, Vite manifest hash, build time, and Git/platform commit when available. The combined artifact identity changes for same-commit source/asset changes, and runtime readiness fails closed when the built manifest is missing or mismatched. `/health/version` exposes the no-cache identity for the admin poller.
- Rolling old/new containers are ordered by build time on both server and client. Older nodes cannot replace the newest notification baseline, trigger a downgrade prompt, or acknowledge a different deployment than the one the user confirmed.
- `AppUpdateService` writes Filament-format database notifications synchronously, so app-update delivery does not depend on a queue worker. Request-time discovery delivers only to the current user, while the scheduled `release:notify-deploy` command fills any missing users without duplicating existing deliveries.
- The deferred-upgrade contract retains the currently loaded browser/Capacitor frontend until consent. The PHP backend changes at server deployment time; retaining an entire old backend would require separate blue/green infrastructure and backward-compatible migrations.

## [1.20.0] - 2026-07-18

**Release type:** Minor Feature Update

Merges the two overlapping homepage-banner systems into one. Previously "Hero Slides" (full-width scheduled slider) and the Storefront Settings "Banner images" repeaters (fallback side-card with product links) lived side by side with different feature sets. Hero Slides is now the single place to manage homepage banners.

### Added

- **Product link on hero slides:** each slide can optionally link to a product (the old banner-only feature) — clicking the slide image opens that product's page. An explicit CTA URL still wins over the product link.
- **WooCommerce sync now imports variations and full product data:** variable products pull all their variations (option combinations like Size/Color with per-variation SKU, regular/sale price, active status, order, and image) into ERP product variations — matched by variation SKU (or option set) on re-sync so nothing duplicates. The import also now takes the full product description instead of only the short one, downloads the remaining photos into the product gallery (first sync only, so admin-curated galleries stay untouched), and fills the Brand field from a "Brand" attribute when the old site has one. Stock is still never imported (ERP stock comes from stock movements only).
- **Marketplace-style homepage (MoveOn-inspired), mobile-first:** round category icons in a swipeable row right under the hero, product cards in a dense 2-column grid on phones (4 columns desktop), a new "Explore more products" grid (homepage now shows up to 23 products instead of 12), the how-to-order steps moved to the bottom, and a brand-colored "Ready to order?" call-to-action band above the footer.
- **"How to order" steps redesigned and animated:** the four ordering steps are now cards with the same 3D gradient icon badges (search, cart, clipboard-check, package). Number chips were dropped in favour of a looping progress animation — each step lifts and its badge pulses in sequence, and on desktop a gradient progress line with a glowing runner dot travels from step to step, so a visitor instantly reads the order flow. The animation starts only when the section scrolls into view and respects reduced-motion settings.
- **Admin-managed header & footer navigation menus:** Storefront Settings has a new "Navigation Menus" section with two drag-to-reorder menu builders (header and footer). Each item has a label plus a link type — Shop all products, a specific Category, a Content page, Track order, My account, Become a reseller, or a Custom URL — with an optional "open in new tab" toggle. The header menu shows in the desktop navigation bar and in a new mobile hamburger drawer (which also lists categories); the footer menu becomes a "Quick links" column. When left empty, the storefront keeps its automatic defaults (Shop all / Track order / Account, and the published-pages footer list). Broken items (deleted category/page) are skipped automatically.
- **Premium trust badges:** the delivery/returns/payment reassurance lines are now three cards with 3D-style gradient icon badges (glossy highlight + colored glow shadow — blue truck, green returns, amber cash) instead of plain checkmark text.
- **Mobile hero banner fix:** slides with a portrait mobile image now get a taller 3:4 stage on phones so the image shows uncropped (previously the wide 16:9 crop cut off portrait banners); slides without a mobile image keep the wide crop.
- **One-time data migration:** existing Storefront Settings banner images (desktop + mobile, with their product tags) are automatically converted into hero slides on deploy — for companies that had no slides yet (companies already on slides never displayed the banner fallback, so their stale banners are not resurrected).

- **Storefront primary-color consistency fix:** header/footer WhatsApp buttons, Buy Now, Add/Update cart, checkout, track-order, reseller, and category-filter buttons now use the company's configured brand color (Storefront Settings → Branding) as a solid fill instead of a hardcoded black button that only revealed the brand color on hover (or swapped to plain white in dark mode).
- **Redesigned printable invoice (matching the Zamzam International layout):** centered company name with hotline, scannable Code 128 barcode of the invoice number, Bill To block, delivery partner (from the latest courier booking), item table with SL / product image / item name / weight / unit price / qty / amount columns, Sub Total → Grand Total → black Due Amount bar, contact block with Facebook/email/website/address, footer contact strip (hotline · Facebook page · WhatsApp), thank-you message, and a scissor cut-slip for the courier parcel (mini header, Bill To, barcode, black due-amount chip).
- **Invoice page is now a true A4 sheet with the footer pinned to the bottom:** the printable page is sized to exactly 210mm × 297mm (both on screen and when printed), and the contact strip / thank-you note / courier cut-slip footer now always sits flush against the bottom of the page instead of floating right under the totals on short invoices. The item table header still repeats automatically on every printed page for orders long enough to span more than one page.
- **Invoice Settings in Settings → ERP Settings:** header hotline, footer hotline, Facebook page URL/label, WhatsApp number, website, thank-you message, and toggles for the product-image column, weight column, barcode, and cut-slip — all per company, nothing hardcoded.
- **Product weight (kg):** new optional field on products (Product form), shown in the invoice weight column and imported automatically from WooCommerce during sync.
- **New storefront Contact Us page** (professional reference design): a brand-colored hero, four contact method cards (Email, Chat on WhatsApp, Help Center, Call Us — each auto-hidden when its data isn't configured), an "Our Location" section (company address + a "Find on Map" link), a FAQ accordion pulled from the existing FAQ list (Settings → CRM → FAQs), and a "Still Have Questions?" call-to-action. Two new admin fields — Support email and Support hours — added to Storefront Settings; everything else (WhatsApp/phone/address/FAQs) reuses settings that already existed. Linked from the footer and reachable at `/contact`.
- **Professional redesign of admin-authored storefront pages** (About, Terms & Conditions, Privacy Policy, Return & Refund Policy, Advance Payment Policy, and any other page in Settings → Storefront → Pages): breadcrumb, optional cover image, "Last updated" date, and a "Still have questions? Contact us" box at the end. The page content editor is now a rich-text editor (headings, bold, lists, links, tables) instead of a plain textbox — existing plain-text pages keep rendering exactly as before.
- **Storefront accessibility & UX audit fixes** (Vercel Web Interface Guidelines review): the homepage now always has exactly one `<h1>` (previously missing when hero slides were configured); the header's "Categories" menu opens on click/keyboard instead of hover-only, so it's reachable without a mouse; a skip-to-content link, `color-scheme`/`theme-color` meta tags, and an automatic `<link rel="preconnect">` for the image storage origin were added; fixed-position bottom bars (mobile nav, sticky "Buy now") now respect the iPhone home-indicator safe area; checkout/contact/reseller/account phone fields use `type="tel"` with `autocomplete`; the checkout submit button now disables itself with a "Placing order…" state to prevent double-submits, warns before leaving with unsaved input, and its live total announces updates to screen readers; cart's "Remove" button now asks for confirmation; quantity inputs on the product page no longer lose their focus ring; and a few other small polish items (touch targets, image loading hints, curly quotes).
- **Storefront search bar and customer accounts:** the header now has a real product search box (name/SKU, works from any page, keeps the search term through sort/pagination) plus a customer profile icon with a dropdown menu. Customers can create an account (name, phone, optional email/address, password) or log in with either their phone number or email. Production "My Orders" and reorder actions require the authenticated owning customer; public guests can track one order with order number + checkout phone, while signed success/tracking links avoid exposing phone numbers in URLs. When accounts are disabled, account-history links fall back to manual tracking instead of a phone-only history list. Logged-in customers get immediate order history, profile/password management, reseller status, and checkout-prefilled contact/address data. The "Forgot password?" flow texts a 6-digit reset code through the store's existing SMS gateway. The "Official storefront - live catalog, direct ordering" announcement strip above the header has been removed. Login/register controls retain keyboard focus management and clear password requirements.

### Removed

- The "Banner images (desktop/mobile)" repeaters in Storefront Settings → Branding (replaced by a pointer note to Hero Slides), the `banner_images`/`banner_images_mobile` columns, and the fallback banner carousel on the homepage (with no slides, the hero now falls back to the first product photo as before).

### Technical Notes

- Migration `2026_07_18_120000_merge_storefront_banners_into_slides` adds `storefront_slides.product_id` (FK, null on product delete), copies banner data into slides, and drops the two JSON columns. Deploy needs `php artisan migrate`.
- `WooCommerceImportService` gains `importVariations()`/`importVariation()` (paginated `/products/{id}/variations`, upsert by SKU → options signature fallback, variant images downloaded once); parent `has_variants`/`variant_attributes` set from the Woo attribute definitions. New variable-product test in `WooCommerceImportTest`. Re-running "Sync WooCommerce" on an already-synced store backfills descriptions/galleries/variations.
- New dependency-free `App\Support\Code128` renders the invoice barcode as inline SVG (ISO/IEC 15417 Code 128-B with checksum). Invoice settings live in `companies.settings['invoice']` via `CompanySettingsService::invoice()/saveInvoice()`. Migration `2026_07_19_120000_add_weight_kg_to_products` adds `products.weight_kg`. New `InvoiceDesignTest`; `CompanySettingsTest` invoice assertions updated to the new markup.
- `resources/views/orders/print.blade.php`: `.invoice` is a flex column sized with `--page-width`/`--page-height` CSS variables (210mm/297mm, matching `@page { size: A4 }`); the contact strip, thank-you note, and cut-slip are now grouped in a `.invoice-footer` wrapper with `margin-top: auto`, which pushes the whole group flush to the bottom padding of the page. Verified via computed `getBoundingClientRect()` in-browser: on a normal (single-page) invoice the footer's bottom edge lands exactly at the page's bottom padding edge. Known limitation (not fixable in pure CSS in any browser's print engine, no `running()`/paged-media support in Chrome): if an order has enough items to spill onto a second printed page, the footer follows immediately after the last row rather than being pinned to the second page's bottom — same as before this change, and not the common case for this app's orders.
- Installed the `vercel-labs/agent-skills@web-design-guidelines` skill and ran it against all 13 `resources/views/storefront/**/*.blade.php` files, then fixed every finding: `layout.blade.php` (skip link, keyboard-operable Categories dropdown via Alpine `x-data`/`aria-expanded` instead of CSS `:hover`, `color-scheme`/`theme-color` meta, conditional `<link rel="preconnect">` derived from `Storage::disk('public')->url()`'s host, safe-area padding on the fixed mobile nav, icon-only call button `aria-label`, shared `data-confirm` submit-guard script); `home.blade.php` (sr-only `<h1>` on the hero-slides branch since the slide heading is only an `<h2>`, `text-balance` on both hero headings); `products/show.blade.php` (`fetchpriority="high"` on the main gallery image, `focus:ring` restored on the two quantity inputs that had `outline-none` with no replacement, `[touch-action:manipulation]` on stepper buttons, safe-area padding on the sticky "Buy now" bar); `partials/product-card.blade.php` (descriptive `aria-label` on the icon-only quick-add button); `cart/show.blade.php` (`data-confirm` on the Remove form, stepper touch-action); `checkout/show.blade.php` (`autocomplete`/`type="tel"`/`spellcheck` on the delivery form fields, submit-button disable + "Placing order…" state, `beforeunload` guard while the form is dirty, `aria-live="polite"` on the Alpine-computed total, `truncate`/`min-w-0` on the order-summary item name); `account/orders.blade.php`/`reseller/apply.blade.php`/`track/show.blade.php` (`type="tel"` + `autocomplete` on phone fields); `contact/show.blade.php` (`scroll-mt-24` on the `#faq` anchor so it doesn't land under the sticky header, curly apostrophes); `pages/show.blade.php` and `track/show.blade.php` (curly apostrophes). Full `php artisan test` — 327 passed (1421 assertions); `npm run build` succeeds; browser-verified the Categories dropdown (click + `aria-expanded` toggle), single `<h1>` count, and focus-ring/fetchpriority attributes directly via computed DOM state.
- Primary-color fix touches `layout.blade.php`, `home.blade.php`, `products/index.blade.php`, `products/show.blade.php`, `cart/show.blade.php`, `checkout/show.blade.php`, `checkout/success.blade.php`, `account/orders.blade.php`, `reseller/apply.blade.php`, `track/show.blade.php`, and `partials/product-card.blade.php` — CSS-only, no schema/route changes. Verified against `#0a68f5` (Main Company's configured theme color) in both light and dark mode via browser screenshot; full `StorefrontMenuTest`/`Storefront*` suite (58 tests) still passes; `npm run build` succeeds.
- New `App\Http\Controllers\Storefront\ContactController` (`storefront.contact` / `storefront.preview.contact`, same domain/preview pattern as `ResellerController`) renders `storefront/contact/show.blade.php`, reusing `StorefrontSetting::whatsapp_number/phone_number`, the new `contact_email`/`contact_hours` fields, `Company::address`, and the existing `CompanyFaq` list — no new model, no isolation-contract change. Migrations `2026_07_19_130000_add_contact_fields_to_storefront_settings` and `2026_07_19_140000_add_cover_image_to_storefront_pages`. `StorefrontPageResource` content field switched from `Textarea` to Filament `RichEditor` (rendered via `RichContentRenderer`, which auto-sanitizes); a plain `str_contains($content, '<')` check keeps existing plain-text pages rendering through the old paragraph-per-line path so no migration of existing content is needed. New `.storefront-richtext` CSS in `app.css` styles the rich content (no `@tailwindcss/typography` dependency added). New `tests/Feature/StorefrontContentPagesTest.php` (4 tests: Contact page full render, Contact page with nothing configured, rich-HTML page + cover image + CTA, legacy plain-text page still renders).
- Launch-readiness check "Banner uploaded" is now "Hero slide added" (an active slide exists). The storefront share (og:image) now uses the first active hero slide. `StorefrontBannerTest` rewritten around slides (product link, CTA-over-product precedence, mobile `<picture>` source).
- New `customer` auth guard/provider (`config/auth.php`) backed by the existing `Customer` model, which now implements `Authenticatable` (`password`/`remember_token`/`password_reset_code`/`password_reset_expires_at` added via migration, all hidden). Registration reuses an existing phone-matched Customer row instead of creating a duplicate, so a customer's pre-existing order history is attached to their new login automatically; a phone or email already in use by a *registered* (password-set) account blocks re-registration. New `App\Services\CustomerAccountService` (register/attemptLogin/reset-password-by-SMS-code/updatePassword — all scoped by the existing `CompanyContext`/`CompanyScope`, same as checkout/cart) and two new controllers, `Storefront\AccountAuthController` and `Storefront\AccountProfileController`, plus `storefront.account.{login,register,logout,forgot-password,reset-password,profile,password}*` routes (login/register/forgot-password throttled). Password reset re-sends via `StorefrontNotificationService::sendSms()` (new `smsConfigured()` helper) — if no SMS gateway is configured for the company, the flow says so upfront rather than silently failing; the same generic "if an account exists…" message is shown whether or not a phone matched, to avoid account enumeration. New `storefront_settings.customer_accounts_enabled` (default on) lets an owner disable the whole feature per company; `AccountOrdersController` now shows a logged-in customer's orders directly instead of asking for their phone number again. `ProductIndexController`/`PreviewController::products()` gained a `q` search param (name/SKU `LIKE`). New `tests/Feature/StorefrontCustomerAuthTest.php` (10 tests: register/login/logout, phone-record reuse, duplicate-phone block, profile/password update, SMS reset code round-trip via `Http::fake`, SMS-not-configured message, per-company disable, cross-company isolation, product search). Full `php artisan test` — 337 passed (1484 assertions); `npm run build` succeeds; browser-verified register → auto-login → profile prefill → checkout prefill → logout → login-by-email round trip on a live domain-routed company.

## [1.19.0] - 2026-07-18

**Release type:** Minor Feature Update

Automatic image optimization: every admin-uploaded image is compressed and converted to WebP before it's stored, so the storefront never serves untouched multi-megabyte camera photos. Also lays the groundwork for optional Cloudflare R2 object storage.

### Added

- **Automatic WebP compression on upload:** product featured/gallery images, category images, company logos, storefront logos, banners, and slides are all resized (product photos/banners capped at 1600px, logos/category tiles at 800px on the longest edge), stripped of EXIF metadata, and re-encoded as compressed WebP the moment they're uploaded in the admin panel. SVGs and animated GIFs/WebPs are stored untouched (re-encoding would break them).
- **Chat Channel save fix (live):** creating a Chat Channel while "All Companies" was selected silently assigned it to the default company, so it never appeared under the owner's real company (and retrying crashed on the duplicate Phone Number ID). The form now shows a required Company selector in All-Companies mode (same pattern as Courier Providers), the channel list shows a Company column in that mode, and a duplicate Phone Number ID / Page ID shows a proper validation message instead of a server error.
- **Cloudflare R2 disk (prepared, not active):** an S3-compatible `r2` filesystem disk plus `R2_*` env variables are in place for a future migration of public media off the server disk. Nothing switches over until a bucket + API token are created in the Cloudflare dashboard and the env vars are set.

### Technical Notes

- New `ImageOptimizerService` (Intervention Image v3, GD driver, WebP quality 82/85) wired into Filament `FileUpload::saveUploadedFileUsing()` via the `OptimizesUploadedImages` trait — single implementation, six forms opted in.
- New composer packages: `intervention/image` ^3.9, `league/flysystem-aws-s3-v3` ^3.25 (deploy: run `composer install`; server needs the PHP GD extension with WebP support — verified present locally).
- No schema changes, no migration. New `ImageOptimizerTest` (resize cap, no upscaling, compact cap, SVG/animated-GIF passthrough) — 5 passed. R2 disk config shape validated with throwaway credentials (adapter + URL builder resolve); no app code references the `r2` disk yet.
- Chat Channel diagnosis: channel creation was reproduced against a scratch MySQL 8.4 database (schema + encrypted-cast inserts all succeed), ruling out a SQLite-vs-MySQL schema issue — the failure is the All-Companies default-company fallback. New `ConversationChannelResourceTest` (saves into active company; All-Companies mode requires an explicit company; duplicate external_id → validation error, same ID under the other provider allowed).

## [1.18.0] - 2026-07-17

**Release type:** Minor Feature Update

Chat-order UX polish after live testing plus a WhatsApp Business-style Inbox overhaul: premium redesign of the customer order form and thank-you pages, clickable order links, catalog (product card with image) sending, near-realtime chat, pull-to-refresh in the mobile app, and fewer spurious "Error while loading page" toasts.

### Added

- **WhatsApp Business-style Inbox:** avatar + last-message-preview conversation list with unread badges, full-screen thread with back button on mobile (list ⇄ chat navigation like the WhatsApp app), chat wallpaper, date separators (আজ/গতকাল), delivery ticks (✓ sent, ✓✓ delivered/read, ⚠ failed), auto-scroll to the newest message, and a WhatsApp-style composer (rounded pill input that grows as you type, Enter sends / Shift+Enter for a new line, round send button, "+" attach button).
- **Catalog sending with product image:** the "+" button opens a catalog panel — pick a product (with live image/price preview) and quantity, and the customer receives a product card: on WhatsApp the product photo goes as an image message with name/price/order-link caption; on Messenger the photo is sent followed by the text. The image also shows in the Inbox thread bubble and on the customer's order form.
- **Chat order form + thank-you page redesign:** modern mobile-first look (Hind Siliguri Bengali font, brand mark header, gradient confirm button, focus rings, animated success checkmark, dashed order-number chip, trust footer) with full dark-mode support; the closed/expired page got the same treatment.
- **Back button on the thank-you page:** logged-in staff see "ইনবক্সে ফিরে যান" (returns to `/admin/crm/inbox`); customers see a plain "ফিরে যান" (history back).
- **Clickable links in Inbox chat bubbles:** URLs in any message (e.g. order-form links) are now real links that open in a new tab, with XSS-safe escaping.
- **Pull-to-refresh in the mobile app:** dragging down from the top of any admin page inside the Android (Capacitor) app shows a Chrome-style spinner and reloads the page. Activates only inside the app's webview — normal mobile browsers keep their native pull-to-refresh.

### Fixed

- **Sending an order form no longer closes the open chat.** The global "reload after any success notification" script was resetting the whole Inbox page (back to the empty two-pane state) every time "Order link sent." fired. The Inbox now opts out of that reload; sent messages and order forms appear in the thread instantly on the same request.
- **Messages feel realtime:** your own sends render immediately (no more waiting for the next poll), and incoming messages arrive via a 5-second visible-only poll with WhatsApp-style follow-scroll (the thread stays pinned to the newest message when you're at the bottom).
- Inbox polling runs only while the tab/app is visible (`wire:poll.visible`), so background polls on flaky mobile connections no longer surface Filament's "Error while loading page" toast for no user-visible reason. (That toast is Filament's generic notification for any failed Livewire request — a momentary network drop or a hit during a deploy triggers it.)
- **Order form quantity now recalculates the total live:** changing quantity on `/o/{token}` updates the grand total instantly in the browser (server still recomputes from real prices on submit).

### Technical Notes

- New `ConversationMessage::bodyHtml()` (escape-then-linkify, `target="_blank" rel="noopener noreferrer"`) and `mediaImageUrl()` (resolves catalog URLs and downloaded webhook media paths to displayable image URLs, images only).
- `ConversationMessengerService::send()` accepts an optional media URL — WhatsApp sends an image message with caption, Messenger sends the image then the text; the archived `conversation_messages` row stores `media_path`/`media_mime`. New `Conversation::latestMessage()` (`latestOfMany`) powers the list previews without N+1 queries.
- The `notificationsSent` reload listener now skips pages carrying a `data-zz-no-reload` attribute (the Inbox manages its own live state).
- Pull-to-refresh ships as a small vanilla-JS render hook in `AdminPanelProvider` (touch tracking with resistance, inner-scrollable detection so the thread list doesn't trigger it); detected via `window.Capacitor` / Android WebView UA.
- No schema changes. New `InboxPageTest` (reply archiving + state kept after send, catalog image on link/message, media URL resolution). Full suite: 307 passed (1282 assertions). Verified in the browser: new order form renders, live total recalculates (qty 2→5 updated ৳4,400→৳11,000 instantly), product image shows on the form; a test order previously submitted end-to-end; smoke-test data removed from the demo DB afterwards.

## [1.17.1] - 2026-07-17

**Release type:** Hotfix

Fixed a production 500 error when submitting the chat order form (`/o/{token}`) — and potentially any order-creating flow — on MySQL.

### Fixed

- `orders.status` was still defined as the original `enum('pending','processing','completed','cancelled')` even though the application has long used `draft`/`confirmed`. SQLite (local/demo) doesn't enforce enums so everything worked locally, but MySQL in strict mode rejects the `'draft'` insert, so the chat order submit crashed with a 500 on the live server. New migration converts the column to a plain `string(20)` (valid values enforced in code via `Order::STATUSES`).

### Technical Notes

- Migration `2026_07_17_030000_change_orders_status_to_string.php`; `down()` intentionally keeps the string type since reverting to the enum would reject legitimate `draft`/`confirmed` rows. Deploy needs `php artisan migrate`.
- Audited all remaining `enum()` columns: the voucher-module enums match the values the code writes; only `orders.status` was stale.
- Full suite: 304 passed (1266 assertions).

## [1.17.0] - 2026-07-17

**Release type:** Minor Feature Update

Full Lead/CRM module (`02_LEAD_CRM_MODULE_PLAN.md` steps 1–14): leads and quotations, a WhatsApp/Messenger conversation inbox with click-to-order links, and a grounded AI auto-reply assistant with a 72-hour CTWA ad reply window. Also ships multi-image product-taggable storefront banners and an app-wide single-column admin form layout.

### Added

- **Leads & Quotations (CRM nav group):** Lead resource with sources/statuses, activity log, follow-up reminders (overdue highlighted), convert-to-customer action; Quotation resource with product/variant repeater, auto totals, public share page (`/quotation/{number}`), WhatsApp share, convert-to-order (draft order flows through the existing confirm/stock/balance pipeline), and a daily `quotations:mark-expired` scheduled command.
- **Conversation Inbox:** Meta webhook endpoint (`/webhooks/meta`) with per-channel HMAC signature verification and dedupe, queued WhatsApp/Messenger message ingestion with media download, auto-linking of contacts to existing customers/leads (or auto-creating a lead), and a Filament Inbox page (unread badge, status filters, reply/notes, WhatsApp-style thread) styled with native Filament components.
- **Click-to-Order chat links:** one-tap `/o/{token}` mobile order form (Bengali) prefilled from the conversation — creates a draft order (source `chat`), locks the link, marks the lead won, and sends an automatic confirmation message back into the chat.
- **AI auto-reply assistant:** per-company tool-calling agent (Anthropic or OpenAI, admin-configurable encrypted API key) that answers only from live company data via tools (product/price lookup, FAQ, delivery charge, order-link creation). Code-enforced guardrails: every money amount in a reply is cross-checked against tool results ("Never Echo"), confidence threshold, complaint/discount keywords bypass the LLM straight to human handoff, consecutive-reply limit, 24h AI pause after a human reply, and an "I'm the assistant" transparency prefix. Handoffs set the conversation to pending with a "needs review" badge + database notification.
- **CTWA Free Entry Point:** conversations that start from a WhatsApp ad get the 72-hour reply window (vs 24h) with a live countdown badge in the Inbox.
- **FAQs resource:** keyword-matched FAQs answer instantly without an LLM call.
- **AI Assistant settings page** (super admin): provider, model, confidence threshold, reply limit, brand voice, encrypted API key (never round-tripped to the browser).
- **Storefront banners:** desktop and mobile banners are now multi-image carousels, and each banner can be tagged to a product so tapping it opens that product page.
- **Admin UX:** all Filament form/infolist sections app-wide now render in a single-column layout (37 resources touched) for a consistent, less cramped editing experience.

### Technical Notes

- New tables: `leads`, `lead_activities`, `quotations`, `quotation_items`, `conversation_channels`, `conversations`, `conversation_messages`, `chat_order_links`, `company_faqs`; new columns on `conversations`/`conversation_messages` (AI + CTWA fields) and `storefront_settings.banner_images_mobile` (with data migration from the old single mobile image). Deploy needs `php artisan migrate`; scheduler + queue worker already required.
- All new company-owned models use `BelongsToCompany` + `CompanyScope` and are registered in the `MultiCompanyIsolationTest` contract; queued jobs set/clear `CompanyContext` explicitly.
- Channel access tokens/app secrets and the per-company AI API key are encrypted at rest; all external credentials are admin-configurable settings.
- New tests: `LeadTest`, `LeadConversionTest`, `QuotationTest`, `ConversationIngestTest`, `ChatOrderLinkTest`, `AiAutoReplyTest` (all LLM/Graph API calls mocked), `StorefrontBannerTest`. Full suite: 304 passed (1266 assertions).

## [1.16.0] - 2026-07-15

**Release type:** Minor Feature Update

Added an admin-panel trigger for the WooCommerce product import, which previously only ran via a server-side `php artisan` command.

### Added

- "Sync WooCommerce" action button on the Storefront Settings edit page and list row: runs the existing `WooCommerceImportService` import on demand (with an option to skip image downloads), shows a success/failure toast with created/updated/skipped counts, and only appears once a site URL + consumer key/secret are saved for that company.

### Technical Notes

- No schema or service changes — reuses `WooCommerceImportService::importProducts()` exactly as the `woocommerce:import-products` CLI command does; the CLI command is unchanged and still works.
- New `StorefrontSettingResource::syncWooCommerceAction()` / `hasWooCommerceCredentials()` helpers shared between the list `recordActions` and `EditStorefrontSetting`'s header actions.
- Verified in the browser: button is hidden with no credentials saved, appears once a site URL + consumer key + secret are saved on a record.
- Full suite: 264 passed (1127 assertions).

## [1.15.0] - 2026-07-14

**Release type:** Minor Feature Update

Storefront redesign Phase 4 — Polish (`STOREFRONT_REDESIGN_PLAN.md`), completing the 4-phase redesign.

### Added

- Sitewide flash-sale/offer countdown banner on the homepage: admin sets a title, discount %, and end time; a live countdown (days/hours/minutes/seconds) ticks down and the banner disappears automatically once it ends.
- Scroll-reveal animation: the category grid, featured products, and each product carousel fade up into place the first time they scroll into view; respects `prefers-reduced-motion` (no animation, fully visible immediately) and degrades gracefully with no `IntersectionObserver` support.
- Best Sellers / New Arrivals sections and the category/product-card grids were already delivered in Phase 1; verified still working correctly alongside the new additions.

### Technical Notes

- New `storefront_settings` columns: `offer_title`, `offer_discount_percent`, `offer_ends_at`. New `StorefrontSetting::hasActiveOffer()` helper (title set + end time in the future).
- Scroll-reveal ships as a small custom Alpine directive (`x-reveal`, registered in `resources/js/app.js`) rather than a new dependency — no bundle-size increase from this phase (88.96 kB / 32.92 kB gzip, unchanged from Phase 1-3, still within the plan's <60KB gzip budget).
- Performance budget check: build output stayed within budget across all 4 phases; images already lazy-load with explicit dimensions from Phase 1-2 (zero added CLS risk this phase). A full formal Lighthouse audit was not run (no CI/Lighthouse tooling wired into this repo) — flagged as a follow-up if the owner wants a formal score, rather than claimed without having actually run it.
- New test: `tests/Feature/StorefrontOfferCountdownTest.php`.
- Full suite: 264 passed (1127 assertions).

This completes all 4 phases of `STOREFRONT_REDESIGN_PLAN.md`. Items explicitly deferred across the phases (flagged, not silently skipped) remain: the Intervention Image/WebP resize pipeline, a Specification tab, the import shipping-cost-breakdown panel, real bKash/Nagad gateway API, bn/en localization, and per-product-scoped offers (this phase's offer is sitewide, not per-product as the plan's admin table originally described) — each noted in its phase's entry above with the reasoning for deferring it.

## [1.14.0] - 2026-07-14

**Release type:** Minor Feature Update

Storefront redesign Phase 3 — One-Page Checkout & Payments (`STOREFRONT_REDESIGN_PLAN.md`).

### Added

- Delivery area selection (Inside / Outside Dhaka) with admin-configurable per-area delivery charges; the order summary live-updates the delivery charge and total as the customer picks an area, with no page reload.
- Payment method choice: Cash on Delivery (admin on/off toggle), and manual bKash/Nagad "Send Money" — the customer enters the number they sent from and the Transaction ID, which is captured as a `pending` `StorefrontPayment` for admin verification. Both manual methods are hidden from checkout entirely until the admin configures a receiving number.
- New admin-only "Storefront Payments" list (Filament) with Verify/Reject actions for pending manual bKash/Nagad payments.
- The success page shows a bKash/Nagad-specific "we are verifying your payment" notice for manual payments (distinct wording from the existing pre-order online-advance notice).

### Technical Notes

- New `storefront_settings` columns: `cod_enabled`, `delivery_charge_inside`, `delivery_charge_outside`, `manual_bkash_number`, `manual_bkash_instructions`, `manual_nagad_number`, `manual_nagad_instructions`.
- The customer's delivery-area choice is stored on the order's existing `shipping_zone`/`shipping_fee` columns (the same fields the generic courier `ShippingFeeService` would otherwise auto-fill) — since `Order`'s `creating` hook only auto-computes them when still `null`, passing the storefront's own charge at creation time cleanly overrides the auto-detection with no schema change and no double-charging risk.
- Manual bKash/Nagad payments reuse the existing `StorefrontPayment` model/table (`gateway` = `manual_bkash`/`manual_nagad`, `payment_method` holds the sender's number, `transaction_id` holds the TrxID) rather than adding new columns or a parallel payment table.
- All new checkout fields are optional server-side and default to the pre-Phase-3 behaviour (COD, no delivery charge) so nothing already integrating with the checkout endpoint (existing tests, any external callers) breaks without sending the new fields.
- Deliberately deferred (flagged, not silently skipped): bKash/Nagad **gateway API** integration (the plan's "bKash গেটওয়ে (API)" line) — the existing ZiniPay gateway already covers pre-order advance payments and is left as-is; a true bKash API integration is a separate, larger piece of work requiring real merchant credentials. Also deferred: bn/en `lang/` localization (plan section 5's "ভাষা" note) — no `lang/` directory exists in this repo yet; introducing full bilingual UI strings is a cross-cutting change that touches every storefront view, not scoped to checkout alone.
- New test: `tests/Feature/StorefrontManualPaymentTest.php`.
- Full suite: 261 passed (1120 assertions).

## [1.13.0] - 2026-07-14

**Release type:** Minor Feature Update

Storefront redesign Phase 2 — Product Page (`STOREFRONT_REDESIGN_PLAN.md`).

### Added

- Buy Now: a second button next to Add to cart that adds the item and redirects straight to checkout, skipping the cart page (single-variant products only).
- Sticky mobile action bar: Add to cart / Buy now stay reachable at the bottom of the screen while scrolling the product page on a phone.
- Description / Shipping & Return tabs on the product page (Alpine-powered).

### Technical Notes

- `CartController::addToCart()` now accepts an optional `buy_now=1` field on the existing cart-add POST; when present it redirects to `storefront.checkout.show` (or the preview equivalent) instead of back to the referring page. No new routes.
- The existing image gallery, tiered/wholesale pricing table, variant option table, and related-products grid were already implemented in an earlier pass and needed no changes for this phase.
- Deliberately deferred (flagged, not silently skipped): a Specification tab (no key-value spec field exists on `Product` yet — would need its own migration) and the plan's optional import shipping-cost-breakdown panel (no admin-configurable air/sea rate fields exist yet).
- New test: `tests/Feature/StorefrontBuyNowTest.php`.
- Full suite: 257 passed (1104 assertions).

## [1.12.0] - 2026-07-14

**Release type:** Minor Feature Update

Storefront redesign Phase 1 — Foundation & Home (`STOREFRONT_REDESIGN_PLAN.md`).

### Added

- Animated hero slider: new Storefront Slides admin resource (image, mobile image, heading/subheading/CTA, sort order, active toggle, optional schedule window). Multiple slides autoplay with fade transitions, dot navigation, swipe-friendly, and respect `prefers-reduced-motion`. Falls back to the existing single banner/hero copy when no slides are configured, so existing storefronts are unaffected until slides are added.
- Category images: `Category` now has an admin-uploadable image, shown on the homepage category grid (horizontal scroll on mobile, grid on desktop) with a hover zoom; falls back to the existing initial-letter tile when no image is set.
- Trust strip: three admin-configurable reassurance lines (delivery/return/payment) shown as an icon row under the hero.
- Product card v2: discount badge and struck-through compare price when `sale_price < price`; the "Add to cart" quick-add button is now always visible (not hover-only), matching mobile-first Amazon-style cards. Product card and category images now load lazily with `decoding="async"`.
- Alpine.js (~8KB gzipped) added for the hero slider's interactivity; no other framework changes.

### Technical Notes

- New table `storefront_slides` (company-owned, `BelongsToCompany`/`CompanyScope`, covered by `MultiCompanyIsolationTest`); `categories.image` and `storefront_settings.trust_strip_*` columns added.
- Homepage slide list is cached per company for 10 minutes (`storefront-home:{companyId}`), invalidated immediately on saving/deleting a `StorefrontSlide`, `Category`, or `StorefrontSetting`. Products/categories queries themselves are **not** cached yet (stock changes too frequently to risk staleness) — flagged for its own follow-up rather than rushed in.
- Full Intervention Image/WebP resize pipeline from the plan's performance section is **deliberately deferred** (flagged, not silently skipped): today's upload fields match the existing plain-`FileUpload` pattern used everywhere else in the app (Product, Company, Storefront Settings), so this ships consistently rather than introducing a one-off pipeline for just this feature.
- New test: `tests/Feature/StorefrontSlideTest.php` (active/inactive/scheduled visibility + company isolation).
- Full suite: 255 passed (1099 assertions). `npm run build` output: 88.55 kB JS (32.75 kB gzip), within the plan's <60KB gzip budget.

## [1.11.0] - 2026-07-14

**Release type:** Minor Feature Update

New Voucher & Fund Control module (`05_VOUCHER_FUND_CONTROL_MODULE_PLAN.md`).

### Added

- Credit and Debit Vouchers: a documentation/approval layer in front of the existing accounting system. A voucher goes pending → (optionally) verified → approved/rejected/cancelled; approving it books the correct existing record automatically — a Customer Payment, Supplier Payment, or Expense — so all existing due calculations, ledgers, and reports keep working unchanged. Creating payments/expenses directly, without a voucher, still works exactly as before (both paths stay supported).
- Fund Sources: named pools of money (cash, bank, mobile banking, wallet, petty cash, owner/partner investment, business profit, bank loan, customer advance, supplier credit). Account-linked types always read their balance from the existing Accounts/ledger system — never a second stored number that could drift out of sync.
- Fund Transfers: move money between two of your own accounts with a pending → approved/rejected step, recorded as a matching pair of ledger entries.
- Inventory purchases can now be funded from a Fund Source through a voucher, without ever creating an Expense — inventory is an asset conversion, not spend (enforced and covered by `AccountingRulesTest`).
- Money Receipt: a printable PDF for an approved credit voucher, reachable via a signed link that needs no login (for sharing with a customer) but can't be guessed.
- New permissions: `voucher.create/view/view_all/verify/approve/reject/cancel`, `fund_source.manage`, `fund_transfer.create/approve`, `finance.dashboard`, mapped onto the existing roles (Sales/Inventory Staff can submit vouchers; Accountant can verify; Manager/Super Admin can approve).

### Technical Notes

- New tables: `fund_sources`, `vouchers`, `voucher_attachments`, `fund_transfers`; `purchases` gained a `funding_sources` JSON column. All four new models use `BelongsToCompany`/`CompanyScope` and are covered by `MultiCompanyIsolationTest`'s contract test.
- This ships with the module plan's own documented fallback: simple inline approval logic (`VoucherService`), not the shared `ApprovalGateService` from the not-yet-built Task/Approval Workflow module (that module does not exist in this codebase yet). Migrating `verify()`/`approve()` onto a shared service later is a self-contained follow-up.
- Deliberately **not** included in this pass (flagged as follow-up, not silently skipped): automatic voucher creation from existing Purchase/Expense/SupplierPayment/Order events (module plan step 9). Wiring this in safely needs care to avoid double-booking a record that a manual voucher already created for the same event; shipping it half-done risked duplicate financial records, so it is deferred to its own change.
- `capital_investment` vouchers are Mudarabah-ready: they only move an owner-capital Fund Source/Account today, but route through `voucher.resulting_model_type`, so a future Mudarabah investor module can link its own `investments` table without changing this module (per the plan's step 8 decision).
- Full suite: 252 passed (1084 assertions).

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
