# Changelog

All notable production changes to Business Dashboard are documented here.

## [Unreleased]

### Added

- Real-time push notifications for business events, delivered to both the Android app and desktop browser tabs (including while a tab is backgrounded, via a new Firebase-powered service worker) — new orders, order status/delivery-status changes, new staff added to a company, a new company created, and a company's info being changed. Every alert is strictly scoped to the owning company's own active admin/staff — one company's activity never reaches another company's dashboard — and further gated by four new selectable permissions (**Notifications: New Orders**, **Order Status Changes**, **New Staff/User Added**, **Company Info Changes**) available wherever roles/permissions are already configured (Settings → User Roles, and each user's Custom Permissions). "New company created" is Super-Admin-only and unconditional, matching how the existing app-update notification already works. Firebase credentials (Web SDK config, Web Push certificate, and the server-side service-account key) are entered by a super admin on a new **Settings → Push Notification Settings** page — encrypted at rest, never `.env`-only — since a real Firebase project has to be created by the owner first.
- Android app: crashes are now saved on-device and automatically uploaded to the server the next time the app launches with connectivity, so a device-only crash the owner hits can be diagnosed from real data instead of guesswork. Super admins can review them under **Settings → Mobile Crash Reports** in the Filament admin panel (exception, message, full stack trace, app/Android version, device model, and when it happened).

### Changed

- Company Management's **Companies** list now follows the top-bar company switcher: a selected company shows only its own row, while **All Companies** shows the full permitted company list.
- Monetary values now begin with a currency symbol throughout the app: Bangladeshi Taka renders as **৳** instead of `BDT`, while other supported currencies use their own familiar symbols. All BDT amount inputs use the same **৳** prefix, and currency-code fields clarify the expected `BDT (৳)` value.

### Fixed

- Storefront Settings now commits native Filament Color Picker values when the picker loses focus, so changing one color and then moving to another field no longer restores a stale black value. The same reliable blur-save behavior also applies to the company dashboard color picker. Shared sticky **Save changes** actions now use Filament's native form-submit flow and explicitly target the page form, restoring save behavior on all affected resource create/edit pages, including Storefront Settings and Company pages.
- **Connect WhatsApp App** (CRM) settings form no longer silently fails to save: the Embedded Signup Configuration ID was wrongly required alongside the Meta App ID/Secret/verify token, even though Meta only issues a Configuration ID after the Tech Provider App Review step is approved — so saving credentials before that (the normal real-world order) failed Livewire validation with no visible error, and the form appeared to reset on reload. Configuration ID is now optional at save time, and validation errors are now shown inline on each field instead of failing silently.
- Storefront cart page's Subtotal and Total rows no longer crash with a 500 error. They referenced an out-of-scope loop variable (`$item`) instead of the cart's actual subtotal, so any customer opening the cart page hit "Undefined variable $item" on every request.
- Storefront checkout page's Subtotal, "Advance payable online now", and weight-based delivery rate note now show the actual cart subtotal, advance-due amount, and configured delivery rates instead of a leftover per-item loop value (a copy of the cart bug above, same root cause).
- Order PDF/invoice download (`/admin/orders/{order}/pdf`) no longer shows the last line item's unit price repeated for Subtotal, Discount, VAT, Total, Paid, Due, and the courier cut-slip's COD amount. Each row now reads its actual order-level field, and orders with no items no longer crash the download with "Undefined variable $item".
- Storefront offer pages (`/offers` and an individual offer's page) no longer 500. They called a non-existent `Offer::finalPrice()`/`Offer::componentsSubtotal()` model method instead of using the already-computed pricing values passed in from `OfferPricingService`.
- Storefront customer account dashboard (`/account`) no longer 500s with a PHP parse error — a stat card's "Total purchased" value had an incomplete expression that never included the actual amount.
- A product's Wholesale pricing table on its storefront page now shows each quantity tier's actual configured price instead of repeating the product's regular selling price on every row.
- Android app: when the WebView briefly fails to reach the server (Wi-Fi/mobile-data switching, a dropped connection), the raw Android "Web page not available / net::ERR_..." error page no longer flashes on screen during each automatic retry — the app's own friendly "Connection Problem" page now shows immediately on the first failure and stays up while retries continue silently underneath it. Also made retries more persistent (up to 6 attempts, 1.5s apart, was 3 attempts 2.5s apart) so a flaky connection gets more chances to recover before the user has to tap "Try Again" themselves.
- Android app: granting the notification permission (or already having granted it on a previous launch) no longer closes the app 1-2 seconds after opening. The app's Push Notifications setup was calling into Firebase before Firebase was actually configured for this build (no `google-services.json` yet — that's an owner-provided Firebase project file, not something invented here), which crashed the whole app the instant it ran. The app now checks with the native side first and skips push setup entirely until a real Firebase config is in place, so opening the app and signing in works normally either way; push notifications themselves will start working with no further code changes once that file is added.
- Android build: fixed a `build-android` CI failure (`cannot find symbol: FirebaseApp`) introduced by the fix above, caused by a Gradle module-visibility gap (the Firebase dependency existed but wasn't visible for compilation in the app's own module).
- **WooCommerce product sync** no longer crashes with "Duplicate entry ... for key `categories.categories_slug_unique`" when a company's synced category name happens to slug the same as another company's existing category (e.g. two companies both getting an "Audio" category). `categories.slug` was still database-wide unique from before multi-company support existed, even though categories are company-owned — it's now unique per company instead, matching how every other company-scoped table in this app works.
- A follow-up full-schema audit (triggered by the above) found and fixed the exact same database-wide-unique-despite-company-scoping bug on two more tables, and a related admin-form-only version of it on five forms:
  - **Products**: `sku` is now unique per company instead of app-wide — two companies stocking the same manufacturer's SKU (or importing from independent WooCommerce sites that happen to reuse a SKU) no longer crashes. `barcode` now has a proper per-company unique constraint in the database to match what the admin form already enforced.
  - **Expense Categories**: `slug` is now unique per company instead of app-wide — every company independently having a "Rent", "Utilities", or "Salary" expense category (extremely common) no longer crashes.
  - **Admin form false rejections**: the Product (SKU, barcode), Purchase's inline "add new product" (SKU, barcode), Expense Category (slug), Courier Provider (slug), and Investor (phone) forms all validated "uniqueness" without scoping the check to the current company, so they could incorrectly reject a value as "already taken" even when another of the owner's companies using the same value is completely legitimate (e.g. adding "Steadfast" as a courier provider to a second company, or the same investor's phone number backing two companies) — all five now scope the check to the current company, matching what the database actually requires.
  - **Related robustness gap**: Customer Payment, Supplier Payment, and Expense numbers were generated as a random (not sequential) code with no retry if two requests ever happened to mint the same one — unlike every other auto-numbered document (orders, purchases, vouchers, quotations, fund transfers), which already retries automatically under this exact collision. All three now use the same safe-under-concurrency retry.
- **Storefront Meta Pixel/CAPI consent banner never actually let tracking through, even after clicking "Accept"**: the banner set its choice via raw `document.cookie` JavaScript, but the storefront's `web` middleware group encrypts/decrypts cookies by default — Laravel silently discards any cookie it can't decrypt, so that plain-text cookie was invisible to the server on every following request. The banner kept reappearing and the Pixel/CAPI stayed off no matter what a customer clicked. "Accept"/"Decline" now submit through a real server route so Laravel sets the cookie through its own encryption pipeline, matching how it's read back.
- **New storefronts no longer require an accept/decline click before Meta measurement works at all**: "Require customer consent" now defaults to **off** for newly created storefront settings — Pixel and Conversions API events fire for every visitor immediately, matching how the Pixel is expected to behave out of the box. Existing storefronts that already have consent required turned on are unaffected; the toggle (Meta Pixel & Conversions API → Consent & Advanced Matching) can still be switched on for stores that specifically need an accept/decline gate for local regulation, and now works correctly when they do (see the bug above).

### Security

- Closed four race-condition (concurrency) gaps found during a deep audit, all following the same pattern: a stock/fund "is this safe?" check and the write that acts on it happened in two separate, unlocked queries, so two requests arriving at nearly the same moment could both pass the check off stale data.
  - **Stock oversell**: creating/updating/deleting a `StockMovement` now locks its Product row (and variant row, if any) for the whole operation, so the ledger-sum stock check and the insert that follows it can no longer race — two concurrent sales for the last unit of a product can no longer both pass validation and both go through.
  - **Variant stock lost-update**: the same lock also protects `ProductVariant.stock`'s read-modify-write delta, so two concurrent movements against one variant can no longer silently overwrite each other's update.
  - **Purchase overfunding**: approving an inventory-purchase voucher now locks both the voucher row and the Purchase row for the whole approval, so two vouchers funding the same purchase approved near-simultaneously can no longer jointly exceed the purchase total.
  - **Voucher double-processing**: `verify()`, `approve()`, `reject()`, and `cancel()` now all re-fetch and lock the voucher row inside their transaction before checking/changing its status, closing the narrower window where the same voucher could be processed twice from two concurrent requests.

### Technical Notes

- Applied the server-side follow-up deferred in `[1.8.1]`'s Android `net::ERR_SOCKET_NOT_CONNECTED` fix: the Nixpacks-managed app-server Nginx (`nginx.template.conf`) now sets an explicit `keepalive_timeout 120s` (was relying on Nginx's implicit 75s default), giving a kept-alive connection from the Android WebView more headroom across a Wi-Fi/mobile-data switch or a backgrounded app before this Nginx closes it. This only covers the Nixpacks Nginx in front of PHP-FPM inside the app container — the Coolify/Traefik edge reverse proxy sitting in front of it is configured in the Coolify dashboard, outside this repo, and its own idle-timeout is not changed by this entry. If the error still recurs after this change and reinstalling a freshly built APK, that edge timeout is the next thing to check.
- `App\Models\StockMovement::save()`/`delete()` now wrap `parent::save()`/`parent::delete()` in `DB::transaction()` with `lockForUpdate()` on the affected Product/ProductVariant rows (always locked in the same product-then-variant order to avoid deadlocks), acquired before the `saving` hook's ledger-sum validation runs.
- `App\Services\VoucherService::verify()/approve()/reject()/cancel()` now lock the target `Voucher` row (`lockForUpdate()`) inside `DB::transaction()` before re-checking status; `approve()` additionally locks the related `Purchase` row before summing already-approved funding vouchers against it.
- No schema changes to the race-condition fix. Full `php artisan test` run: all previously-passing tests still pass; 13 pre-existing storefront-view failures (unrelated `Undefined variable $item` in `storefront/cart/show.blade.php` and related view bugs, since fixed by the storefront-view fixes above) were confirmed present before these changes too (verified via `git stash`) and were out of scope for this fix.

## [2.1.0] - 2026-08-18

**Release type:** Minor Feature Update

### Added

- Added a selectable **Noor Solar Energy** storefront theme with its approved deep-green/solar-gold palette, Sora/Inter typography, application-led shopping, ERP product and stock signals, project quotation paths, an interactive system builder, and an accessible viewport-lazy Three.js solar-module lab with a WebGL fallback. The theme extends through the shared catalog, product, cart, checkout, tracking, contact, and account surfaces; its design decisions are persisted in `design-system/noor-solar-energy/MASTER.md`.
- New-customer checkout now collects only the calculated delivery charge through the active verified online gateway before creating the customer and order.
- Weight-based delivery pricing supports separate first-kilogram rates for inside and outside Dhaka plus a shared additional-kilogram rate.
- Customer complaints can be submitted with an order/invoice/phone reference and routed to a company-specific Telegram destination while remaining manageable from Filament.
- Registered customers can sign in with a secure, single-use email or SMS OTP alongside the existing password and password-reset flows.
- **Courier Merchant Dashboard** (Courier cluster, first item): a single admin screen showing live balance, delivery/return performance, delivery-margin totals, a clickable **Booking Status Summary** card grid (All/In Progress/Delivered/Partial Delivered/Returned/Cancelled/Failed — each card opens a popup listing that status's own bookings), a **Manage** quick-link card grid right below it, recent consignments, and recent return requests — across **every configured courier**, not Steadfast only. A "Courier" selector defaults to **All Couriers** (combined totals across every provider) and can be scoped to one specific courier at a time; Recent Consignments and the Booking Status Summary popup both show which courier each booking belongs to. Both card grids render with Filament's native stat-card styling, show 5 cards per row on desktop and 2 per row on mobile, and each card carries its own soft color tint. Bookings/Status Logs/Returns/Webhook Logs/Payments are removed from the Courier sidebar menu and surfaced instead as these Manage quick links (also courier-scoped) — staff never need to open any courier's own website.
- Courier bookings can now request a **Steadfast return** directly from the Bookings list/view; requests are recorded locally under a new **Returns** screen for history and filtering.
- A new **Payments** screen shows Steadfast payment/settlement history fetched live from the merchant API, with a **View** action per row opening a structured popup (settlement summary, timeline, and a card per consignment) showing which consignments were cleared under that settlement.
- The Bookings list can now filter by multiple statuses at once and by courier provider.
- Courier booking economics record the configured delivery fee, COD charge, and any previously stored courier cost/margin without exposing a duplicate **Courier Delivery Cost** section on the provider form.
- The Order form's courier reliability check now runs automatically when a customer is selected and displays a per-courier Total/Delivered/Undelivered/Confidence table.
- Courier Providers can be marked **Set as default courier** (one per company); new orders — admin-created or from the storefront — are automatically pre-assigned to it, editable anytime before booking from the order's new **Courier** field.
- The Orders list's five separate per-courier row buttons (Book courier / Book Steadfast / Book Pathao / Book RedX / Book E-Courier) are now one **Book courier** button: a popup shows the courier pre-selected (the order's assigned courier, or the company default), changeable before booking, with the driver-specific fields updating automatically to match.
- Orders list gained a **Book courier** bulk action: select multiple not-yet-booked orders, pick the courier in a popup (pre-filled with the company's default courier, changeable), and book them all in one click. Only couriers that need no extra per-order details (Steadfast, Manual) are offered here — Pathao/RedX/E-Courier require structured fields that can't be safely shared across a batch of different recipients, so use the row-level **Book courier** for those.
- Orders list gained a **Print invoices** bulk action: select any number of orders and print all of them in one click — every selected order's invoice opens in a single new tab and the browser/OS print dialog opens automatically, same "print preview" behaviour as printing one order.
- The order view page gained a **Payments History** ledger (add/edit/delete individual payments — type, method, amount, date, note; the order's Paid/Due totals now stay in sync with this ledger instead of one manually-typed number) and an **Associated Costs** ledger (Purchase / Courier Delivery / COD / Other cost lines), which together feed a new order-level **Profit** figure. Also added: total order weight, product variant, and customer type shown on the order items/details, a click-to-WhatsApp link next to the customer phone, a narrated per-order **Activity** feed (including "viewed"/"printed" events), and **Previous/Next** navigation arrows to page through orders without returning to the list.
- The Products list gained 10 stat cards at the top — **Total SKU, Total Variants, New SKU, Active SKU, Inactive SKU** (each counts every SKU-bearing row, a product plus every one of its variants counts individually, matching how the list itself shows one row per variant), **Total Available Quantity, Total Stock Value, Total Purchase Cost** (real on-hand stock, stock valued at cost price, and money actually spent on received purchases), **Total Shortage** (products at/below their reorder level — click through to a filtered Bulk Update Stock screen), and **Total Damage** (pieces recorded via the new Damage stock movement type below) — 5 cards per row on desktop and 2 per row on mobile, with 10px card padding. Mobile labels/values use 12px/16px while desktop values remain 20px. Currency cards use the `৳` symbol; whole amounts omit `.00`, while meaningful fractional amounts retain up to two decimal places without insignificant trailing zeroes.
- New **Bulk Update Stock** screen (Products list → Bulk Update Stock): every product appears in one searchable/filterable table (by name, SKU, ID, or category), showing current stock and how many pieces short of the reorder level. Each **New Stock** field starts blank; staff may stage one or many values without changing the database, then apply them together with the single Filament **Save changes** header action through the same ledger-safe path as the product edit form. The previous **Upload Stock CSV** and **Stock CSV Sample** header actions were removed from this page. The Total Shortage stat card deep-links here pre-filtered to only short products.
- Stock movements gained a **Damage** type (alongside Opening/Purchase/Sale/Return/Adjustment) for recording stock written off as damaged, with its own required reason and reporting via the Total Damage stat card and the existing Stock Movements filters.
- **Meta Ads Manager, Phase A** (new **Ads** cluster): connect one or more real Meta Ads accounts (App ID/Secret/Access Token/Ad Account ID, encrypted at rest, with a **Test Connection** action) and browse their real Campaigns, then drill into a campaign's Ad Sets, then an ad set's Ads — all in one screen with a breadcrumb, deep-linkable via the URL. Spend/Impressions/Clicks/CTR/CPC stat cards and every row's numbers come straight from Meta's own Insights API via a manual **Sync Data** action (no invented "market analysis" or revenue attribution — this app has no order-to-ad click tracking yet). Viewing/managing accounts requires a new **Marketing/Ads** permission (view/create/update/delete), granted to Manager and Super Admin by default.
- **Meta Ads Manager, Phase B** (write-back): every Campaign/Ad Set/Ad row on the Meta Ads Dashboard gained a **Pause**/**Resume** action (with a confirmation prompt), and Campaigns/Ad Sets gained an inline-editable **Daily Budget** field (Ads have no budget of their own, so it stays read-only there). Every edit writes to Meta first — the local row (and, for budget, the input itself) only updates once Meta actually confirms the change; a rejected or failed call leaves both Meta and the local row exactly as they were and shows a clear error, never a silent or partial update. Creating new campaigns/ad sets/ads and an AI marketing assistant remain separate follow-up phases.
- **Meta Ads Manager, Phase C** (new **New Campaign** action on the Meta Ads Dashboard): create a real Campaign + Ad Set + Ad on Meta in one form — campaign name and daily budget, an audience (age range, gender, Bangladesh-only geo for now), and an ad built from a real **Product** (its image, price, and storefront link auto-fill the headline/primary text/destination, all editable) with a Call To Action. Every object this creates always lands **Paused** on Meta, whatever the form says — use the Phase B Resume action when ready to actually spend. Ad Accounts gained a **Facebook Page ID** field (only needed for this — existing accounts keep working for viewing/syncing/pausing without it), since Meta requires every ad creative to be attached to a real Page.
- **Meta Ads Manager, Phase D** (new **AI Assistant** page in the Ads cluster): a grounded AI agent reasons over this company's own real stock, margin, and trailing 30/60-day sales data (plus any real past ad performance for a product) to recommend which product(s) are worth advertising — one top pick plus up to 2 ranked alternatives, each with real, specific reasoning, per the existing per-company AI provider settings shared with the WhatsApp assistant. Every recommendation is saved as a **draft** the owner reviews, edits, and explicitly sends to **Review & Launch** (which opens Phase C's New Campaign form pre-filled) — the AI never calls Meta and never activates anything itself; every value it proposes is either rejected outright (any product it didn't actually look up) or hard-clamped to a **Min/Max Daily Budget** the owner sets per Ad Account (the assistant is off entirely for an account until a Max Daily Budget is configured — there is no default spending ceiling).
- Each Meta Ad Account credential field (App ID, App Secret, Access Token, Ad Account ID, Facebook Page ID) now has an **(i)** hint icon next to its label — hovering shows exactly where in Meta's own interface (developers.facebook.com or business.facebook.com/settings) to find that value, since a wrong-shaped value pasted into the wrong field (e.g. an App Secret pasted into Access Token) fails Meta's API with an unhelpful "Cannot parse access token" error.
- **Meta Ads Manager, Phase E** (new **Events Manager** page in the Ads cluster): a **Pixel Health** panel shows the selected Pixel's real identity (name, created, last fired, active/unavailable) and its real event volume by type for the chosen window, straight from Meta — shown side by side with (never merged into) this app's own **Event Log** send-attempt counts for the same pixel/window, so "what Meta recorded" and "what we attempted to send" always stay clearly distinguishable. Below that, a real **Audiences** list/manager: sync existing Website and Lookalike Custom Audiences from Meta, create a new **Website Visitors** audience (from a Pixel already configured on Meta CAPI settings, with a retention window and optional URL filter) or a **Lookalike** audience (sourced only from this app's own Website audiences, with an editable target country defaulting to Bangladesh and a 1–20% size), and **Delete** any audience — every write goes to Meta first, the local row only changes once Meta confirms. "Customer List" (bulk hashed-PII-upload) audiences are deliberately not supported. The Pixel itself is still configured on the existing Storefront → Meta CAPI page — no new pixel-credential field was added.
- **PayStation online payment gateway**: Storefront Settings → **Online Payments** gained an **Active gateway** selector (ZiniPay or PayStation), so each company can plug in its own PayStation Merchant ID/Password instead of (or as well as configuring, though only one is active at a time) ZiniPay — each gateway's credential fields only show while that gateway is selected. Checkout, the payment return page, and the payment webhook all route through whichever gateway a payment was actually created with, so switching a company's active gateway never affects a payment already in progress. The Storefront Payments list/filter now recognizes `paystation` as a gateway label.
- CRM Inbox speed: the AI auto-reply agent now also pauses for a rolling 30 seconds whenever a staff member simply has that conversation open in the Inbox (not just after they actually send a reply, which already paused it for 24h) — the window renews for as long as the conversation stays open, so the AI never races an agent who is about to type a reply. The agent's FAQ and delivery-charge lookups are now cached per company for 5 minutes (product price/stock lookups always stay live, matching the existing "never invent price/stock" rule), and it can now request more than one lookup in the same turn instead of always going one round at a time.
- **WhatsApp Business App connection** (CRM → **Connect WhatsApp App**, super admin only): connects the owner's real, already-in-use WhatsApp Business App number into the Inbox via Meta's official Coexistence feature — the phone app keeps working, and up to ~180 days of past 1:1 chat history and contacts sync in automatically, with no risk to the number itself. Save the Meta App ID/Secret/Embedded Signup Configuration ID once, then a "Connect via WhatsApp Business App" button opens Meta's own popup — no manual token pasting for this path. Messages the owner sends from the phone app now also appear in the Inbox timeline (marked "Phone app"), and replies/order links sent from the Inbox reach the phone app the same way they always reached a Cloud-API-only number, since this reuses the existing Meta Cloud API pipeline end to end. Catalog/product message sending is not part of this round — it needs a separate Facebook Commerce Catalog integration this codebase doesn't have yet.
- **Quick Replies** (CRM → **Quick Replies**): staff can save short text snippets and insert them into an Inbox reply with one click (the bookmark icon next to the reply box) — the same idea as the WhatsApp Business App's own Quick Replies feature, now available from the ERP Inbox too.
- **Offers module** (new **Offers** and **Reviews** resources in the Storefront cluster; new public `/offers` funnel): admins build **Single Offer** or **Combo Offer** deals — pick component products (with quantities for combos), price them either as the auto-summed component total or a manual price, then apply an optional percent/flat discount, with a live price preview on the form. Each offer gets its own AI-draftable landing page built from reorderable content blocks (Hero, Product Gallery, Why Buy, How To Buy, FAQ, Testimonials, Custom Text) — a **Generate Landing Page with AI** action drafts Bengali marketing copy from the offer's real product data via the same per-company AI provider already used by the WhatsApp assistant (never inventing a price, stock number, or delivery detail), and every block stays manually editable afterward. The public offer page (`/offers`, tabbed Single/Combo, and `/offers/{slug}`) renders these blocks with a fixed checkout form at the bottom — a standalone one-product-line checkout (name/phone/email/address/quantity/payment method), separate from the existing cart/checkout flow, that explodes a combo into normal per-product `OrderItem` rows (price distributed proportionally, so the existing stock/accounting pipeline needs no changes) and auto-creates a storefront login for a new phone number (the generated password is shown exactly once on the thank-you page, never emailed/SMS'd or placed in a URL). Orders placed this way carry a new `Offer` order source, shown alongside Storefront/CRM/Chat everywhere order source is reported. A new customer-review system lets a logged-in customer leave a star rating + comment from any of their own **completed** orders (`/account/orders/{order}/review`); every review starts **pending** and only appears publicly (including in an offer's Testimonials block) after an admin approves it from the new **Reviews** list (bulk/row Approve, Reject, Delete).

### Fixed

- The Filament login page now resolves its brand name plus light/dark logos from the app's oldest active company before authentication, instead of falling back to legacy global settings when no company context exists. Authenticated pages continue to use the currently selected company, and **All Companies** mode keeps its neutral fallback.
- **Main Company** is no longer recreated by a hard-coded runtime `firstOrCreate` after deletion. Super admins now have confirmed Filament delete actions on company list, view, and edit screens; company-scoped audit metadata and automatic system accounts are removed transactionally with an otherwise-empty company, while database-linked business records safely block deletion with an explanatory notification.
- Selecting the **Noor Solar Energy** storefront theme no longer leaves **Site → Homepage** empty; its existing hero heading, subheading, and call-to-action controls now remain available in the Filament settings form and continue to drive the Noor homepage.
- The Category icon browser no longer clears its search or loses the intended selection when an icon tile is clicked. Selection now stays highlighted inside the modal until the new **Add Icon** action applies it; **Cancel** preserves the previous value, and category edit saves persist the chosen Heroicon correctly.
- Steadfast's payment and return-status API paths were corrected after live testing against a real merchant account surfaced 404s on the originally guessed `/payment` and `/return/{id}` paths; they are now `/payments` and `/get_return_request/{id}`, cross-verified against two independent open-source Steadfast API wrapper packages and confirmed end-to-end (list + drill-down) with a real merchant account's live settlement history.
- Printed/print-to-PDF invoices no longer get clipped on the right edge. The invoice page previously relied on a CSS `@page` margin, which not every print destination (a real printer, "Microsoft Print to PDF", Chrome's own "Save as PDF") honors the same way — some ignore it, some add their own default margin on top of it, and either way the invoice was rendering wider than the printable area. The margin is now built into the invoice's own padding with `@page { margin: 0 }`, so the printed page is always exactly what's shown on screen regardless of print destination.
- Editing an existing Meta Ad Account no longer shows the App Secret/Access Token fields blank and no longer fails to save (or would have wiped the real encrypted credentials) unless they were retyped from scratch every time. App Secret/Access Token still never round-trip to the browser as plaintext — they stay blank on edit and are only overwritten when a new value is actually typed, the same "leave blank to keep the existing value" pattern already used for `ConversationChannel`'s credentials. Also fixed: saving a **Max Daily Budget** (AI Marketing Assistant Guardrails) without also setting a Min Daily Budget no longer fails validation — Laravel's built-in `gte:field` rule cannot treat a blank comparison field as "no floor," so the comparison is now a custom rule that only applies when both are filled.

### Changed

- Monetary values now use one app-wide formatter: whole amounts omit trailing decimal zeroes (`BDT 0`, `BDT 1,625,000`) while meaningful fractions such as `BDT 2,520.5` or `BDT 2,520.25` remain visible across dashboard cards, Filament tables/infolists, storefront, invoices, quotations, receipts, and generated marketing copy.
- Dashboard stat cards no longer show secondary status/description lines beneath their values in **Business Overview**, **Customer Success & Risk**, or **Courier Health**; each native Filament card now contains only its title, primary value, icon, and state color.
- Filament page headers now use one app-wide responsive title scale (20px mobile, 22px tablet, 25px desktop) and 5px spacing above and below the header region across Dashboard, resources, custom settings pages, and Release Notes.
- The CRM Inbox now uses a compact Filament-native layout: channel selection lives in a page-header dropdown, page/section/box spacing is reduced to 5px, the 40px composer keeps an icon-only internal-note action, the thread fills the available viewport with the composer aligned at the bottom, and the empty-chat state is centered while populated conversations remain bottom-aligned and independently scrollable.
- On mobile, the Products page now groups Import CSV, Sample CSV, Bulk Update Stock, Export CSV, and New product into one native Filament action menu at the right of the page title; desktop keeps the existing individual buttons.
- The Products-list stat cards now span the full available header width, matching the main Dashboard layout while preserving the 5-column desktop and 2-column mobile grid.
- The main Dashboard's **Business Overview** now shows 5 stat cards per row on desktop and 2 per row on mobile; its native Filament cards use 10px padding and 20px number/value text for a denser summary.
- Checkout derives the delivery area from the submitted address instead of asking the customer to select an area. The server remains authoritative, configurable Dhaka locality keywords drive detection, and ambiguous addresses default to the outside-Dhaka rate.
- The order thank-you page shows customer/order details and a configurable WhatsApp group CTA.
- Courier-provider credentials now use a text database column, matching Laravel's encrypted-array ciphertext format and allowing providers to be created on MySQL without JSON validation errors.
- Courier Provider create/edit forms now use **Set Delivery Fees** as the only outbound delivery-fee section; the duplicate **Courier Delivery Cost** inputs were removed.
- Courier submenu items now use distinct outline icons: a dashboard panel icon for **Dashboard** and the courier-appropriate truck icon for **Providers**.
- The Courier Dashboard filter now renders as a compact Filament non-native `Select` instead of a full-width browser-native dropdown, and is shown only when the company has more than one active courier provider.
- Printed invoices now use a lighter, more modern system-font stack (Segoe UI / system-ui / Helvetica Neue, falling back to Arial) instead of plain Arial/Helvetica — layout and sizing are unchanged.
- The order edit form's **Paid Amount** field is now read-only once an order exists (still a normal editable field when creating one) — corrections go through the new Payments History ledger on the order view page instead, so the two write paths can't disagree.

### Security

- Login OTP values are stored hashed, expire after 10 minutes, allow at most five failed attempts, have a one-minute resend cooldown, and use non-enumerating responses for unknown accounts.

### Technical Notes

- Added `three` as a production dependency. Vite emits the Noor controller and Three.js as separate lazy chunks, so the 3D library is not downloaded by visitors using other themes and only loads for Noor storefront visitors when the module lab nears the viewport. Focus/touch/reduced-motion safeguards, responsive rendering, palette contrast, theme registration, product-page inheritance, and ERP product output are covered by the focused storefront theme test and production build.
- Added `predis/predis` (pure-PHP Redis client, no server extension to compile) and defaulted `config/database.php`'s `REDIS_CLIENT` to `predis`. Production is now recommended to run `QUEUE_CONNECTION=redis` and `CACHE_STORE=redis` (`.env.production.example` updated; concrete Coolify setup steps added under `docs/deployment.md` → "Redis on Coolify") instead of `sync`/`database`, so the CRM Inbox's AI auto-reply agent (up to 6 sequential LLM calls) runs on a real background worker instead of risking running inline inside the Meta webhook request. `QUEUE_CONNECTION=database` still works without a Redis service — it just misses the FAQ/delivery-charge lookup caching, which requires `CACHE_STORE=redis`. No local dev defaults changed (`.env` stays `sync`/`file`).
- The WhatsApp Coexistence connection reuses the existing Cloud API pipeline: `conversation_channels` gained a `connection_type` column (`cloud_api` default | `coexistence`), and `MetaWebhookController`/`StoreIncomingMessageJob` now also route three Coexistence-only webhook `field` values alongside the standard `messages` field — `smb_app_state_sync` (contacts sync), `history` (queued to a new `ImportConversationHistoryJob`, idempotent, does not bump unread counts or trigger AI replies), and `smb_message_echoes` (phone-app-sent messages, archived with `generated_by = 'phone_app'`). New `EmbeddedSignupSettingsService`/`EmbeddedSignupService` (`app/Services/Meta/`) handle the Embedded Signup code exchange and channel creation; app credentials are stored per company the same encrypted way as the existing AI Assistant settings. New `company_quick_replies` table backs the `QuickReply` model. Both `ConversationChannel` and `QuickReply` are covered by `MultiCompanyIsolationTest`.

## [2.0.1] - 2026-08-11

**Release type:** Maintenance Update

Fixes the CI workflow left stale by the v2.0.0 stack upgrade. No production code changes.

### Technical Notes

- `.github/workflows/deploy.yml`'s `shivammathur/setup-php` step was still pinned to PHP 8.2, which now fails Composer's platform check against `composer.json`'s `^8.4` requirement (`laravel/framework` 13.x itself needs `^8.3`, several `symfony/*` packages need `>=8.4.1`). This only affects the GitHub Actions CI check — it does not gate or affect Coolify's own deploy, which listens to the push webhook directly and already deployed v2.0.0 successfully before this was caught. Bumped to `8.4` to match.

## [2.0.0] - 2026-08-11

**Release type:** Major Version Update

Completes the Stack Upgrade Plan and clears every remaining Step 6 pre-production-deploy requirement: PHP raised to 8.4, Laravel to 13, Livewire to 4, and Filament to 5, plus the mandatory production MySQL authentication fix applied and verified ahead of this deploy. No user-facing behavior changes — this release is the infrastructure milestone that lets production run the same stack already verified on staging.

### Technical Notes

- `laravel/framework` 12.64.0 → **13.23.0** (with `laravel/tinker` 2.11.1 → 3.0.2 and `phpunit/phpunit` ^11.5 → ^12.0 as required companions); `filament/filament` and all `filament/*` sub-packages 4.12.5 → **5.7.5** with `livewire/livewire` 3.8.3 → **4.3.4** pulled in automatically. `composer.json`'s `"php"` constraint is `^8.4`. Full breaking-change review for both Laravel 13 and Livewire 4/Filament 5 found nothing this codebase needed to change beyond `AdminPanelProvider.php`'s CSRF middleware rename; `php artisan filament:upgrade` handled the rest mechanically.
- `nixpacks.toml`'s `NIXPACKS_NODE_VERSION` raised `"20"` → `"22"` so Nixpacks' pinned nodejs package (20.18.1) no longer falls short of `vite:^7.3.5`'s `>=20.19.0` floor; confirmed on the following staging deploy by the Vite engine warning disappearing entirely.
- Final full `php artisan test` on PHP 8.4.24, run immediately before this production push: **608 passed, 3,547 assertions, zero failures.** `npm run build` is clean with no console errors (confirmed via the staging deploy logs after the Node 22 fix).
- **Production pre-deploy critical fix completed:** production MySQL 8.4 defaults new/altered users to `caching_sha2_password`, which Nixpacks' PHP 8.4 `mysqlnd` build cannot authenticate against (no compiled RSA/OpenSSL support). Applied the same fix already verified on staging directly to the production database resource (`mysql-database-iom7u0wab3i2ucilif2kl1ms`, the container actually referenced by the app's `DB_HOST`): mounted `/etc/mysql/conf.d/native-password.cnf` with `mysql_native_password=ON`, restarted the database resource, then `ALTER USER ... IDENTIFIED WITH mysql_native_password` on the production app's database user (password unchanged) and `FLUSH PRIVILEGES`. Verified via the real Laravel application (`DB::connection()->getPdo()` from inside the running production app container), not the native `mysql` CLI, per the Step 6 requirement.
- Took a full production database backup (`mysqldump --no-tablespaces --single-transaction`) immediately before this deploy, stored on the production host.
- Owner ran staging with this full stack for several days across storefront and admin usage with no runtime errors before approving this production push.

## [1.23.0] - 2026-08-03

**Release type:** Minor Feature Update

Introduces a professional, fully configurable storefront design system with multiple homepage themes, a complete customer account experience, responsive commerce refinements, and richer category presentation.

### Added

- **Site Theme system:** the existing storefront is now the **Built-in Theme**, joined by **Marketplace Pro** with Hero-driven, Campaign-driven, and Compact & dense homepage templates. Each theme exposes only the feature settings relevant to its layout while sharing the existing catalog, cart, checkout, tracking, and account functionality.
- **Professional theme controls:** Storefront Settings now provides clearly separated brand, light-mode, and dark-mode palettes; automatic or explicit foreground contrast; typography presets and individual font controls; and modern appearance settings for content width, gutters, radius, card depth, hover feedback, and motion.
- **Complete customer account area:** customers can access an account overview, profile and password settings, order history, reorder actions, account activity, order tracking, reseller status, and logout. The header profile dropdown links directly to these destinations and contains the storefront light/dark switch.
- **Category image and icon support:** category create/edit forms and the Product form's inline Create category action can assign a compressed square image or a curated storefront icon. Storefront category tiles fall back from image to icon to category initial.
- **Visual Filament icon browser:** clicking the Category icon field opens a searchable, scrollable modal containing every solid and outline Heroicon bundled with the installed Filament version. Selecting a tile applies it immediately, while existing category icons remain backward compatible.

### Changed

- Storefront pages now use a denser responsive commerce layout with compact Amazon-inspired typography, spacing, cards, headings, controls, and account screens while retaining accessible 44px mobile touch targets.
- The responsive header keeps navigation below the logo/search row on desktop, moves account and theme controls into one compact profile menu, uses a mobile drawer below the desktop breakpoint, and hides the duplicate header cart on phones.
- Homepage banners preserve the complete uploaded image at full available width on desktop and mobile, adapting their height to each image's intrinsic aspect ratio instead of cropping it into a fixed stage.
- Product detail galleries are capped to a compact premium size and add pointer-position hover zoom on supported desktop devices; thumbnails and purchasing controls remain mobile responsive.
- Theme-colored buttons, badges, selected states, CTA areas, and navigation now resolve readable text from the configured foreground mode. Dark-mode cards use opaque matching surfaces so decorative lines and page effects do not bleed through them.
- The **Inventory** sidebar module exposes Products, Categories, and Stock Movement as direct submenu destinations. The desktop sidebar now rests in an icon-only collapsed state, expands on pointer hover or keyboard focus, and collapses when interaction leaves without changing Filament's mobile drawer.

### Technical Notes

- New storefront settings migrations add customer activity storage, theme foreground mode, palette and typography tokens, advanced appearance controls, theme/template selection, category icons, and sufficient icon-key length for every bundled Filament Heroicon. Deploy with `php artisan migrate --force` before serving the updated storefront.
- Theme registration and safe template resolution live in `App\Support\StorefrontThemeRegistry`; category icon validation lives in `App\Support\StorefrontCategoryIcons`. Shared CSS variables from `StorefrontSetting` drive palette, typography, spacing, radius, elevation, and motion consistently across storefront pages.
- The category icon picker is a Filament field/modal built from the installed `Filament\Support\Icons\Heroicon` enum, so the catalog tracks the framework icon library without maintaining a separate hardcoded list. A delegated panel script now filters the rendered icon tiles directly on typing, native search-clear, or Enter and prevents Enter from submitting the category form. Sidebar behavior uses Filament's native collapsible desktop state with a progressive hover/focus controller.
- Added storefront theme and category-media feature coverage. The focused theme/category/image-precompression suite passes with 9 tests and 55 assertions, and the production Vite build succeeds.

## [1.22.1] - 2026-08-01

**Release type:** Maintenance Update

Replaces the third-party `shahariar-ahmad/courier-fraud-checker-bd` package (Part 1 of the Stack Upgrade Plan) with an in-house HTTP client for the same Pathao/Steadfast/RedX fraud-check lookups, and raises the minimum PHP version to 8.4 (Part 2). No user-facing behavior changes — these are infrastructure steps ahead of the planned Laravel 13/Filament 5 upgrade.

### Technical Notes

- New `App\Services\CourierFraud\CourierFraudClient` interface plus `PathaoFraudClient`, `SteadfastFraudClient`, and `RedxFraudClient` — each replicates its corresponding package class's HTTP flow (Pathao merchant login + success-ratio lookup; Steadfast portal CSRF/cookie/login/fetch/logout; RedX token login with per-merchant cached access token) using `Illuminate\Support\Facades\Http` directly, with credentials passed in per call instead of read from static package config.
- `App\Services\ExternalCourierFraudService` now dispatches to the new clients (`DRIVER_CLIENT_MAP`) instead of the package's `PathaoService`/`SteadfastService`/`RedxService`; behavior (24h cache, graceful per-courier failure, audit logging) is unchanged, confirmed by the existing `ExternalCourierFraudCheckTest` suite passing unmodified against the new clients.
- Removed `shahariar-ahmad/courier-fraud-checker-bd` via `composer remove`; `bootstrap/cache/packages.php`/`services.php` regenerated via `composer install` to clear the stale manifest.
- New `tests/Unit/Services/CourierFraud/{Pathao,Steadfast,Redx}FraudClientTest.php` (14 tests) cover success and failure paths for each client with `Http::fake()`.
- `composer.json`'s `"php"` constraint raised `^8.2` → `^8.4` (longest security-support window; Laravel 13 requires PHP 8.3+ minimum). `docs/deployment.md`'s server requirement updated to match. `nixpacks.toml` needs no separate PHP-version pin — Nixpacks' PHP provider auto-detects the version from `composer.json`. Static scan of `app/` for the PHP 8.4 implicit-nullable-parameter deprecation (a typed scalar/class param defaulting to `null` without an explicit `?` prefix) found none.
- The local dev machine's PHP was then upgraded from 8.3.30 to the official PHP 8.4.24 (TS, VS17, x64) Windows build (windows.php.net, sha256-verified) so this could actually be verified locally instead of only on staging: `composer update` under real PHP 8.4 succeeded (`composer.lock` now fully refreshed — Laravel 12.62.0 → 12.64.0, Filament 4.11.7 → 4.12.5, Livewire 3.8.1 → 3.8.3, and other patch/minor bumps; nothing pinned to Laravel 13/Filament 5 yet, per plan ordering), and the full `php artisan test` suite passes under PHP 8.4.24 (555 passed, 2,974 assertions).
- **Fixed a pre-existing bug in `nginx.template.conf`** (found via a fresh Coolify staging build, which crash-looped nginx with `duplicate location "/"`): the Laravel (`IS_LARAVEL`) and PHP-fallback (`NIXPACKS_PHP_FALLBACK_PATH`) `location /` blocks were two independent `$if` blocks instead of one, so both rendered at once. Not caused by the PHP 8.4 change (would have broken any fresh build of this repo state) — first surfaced now because this was the first from-scratch build since the file was added.
- **That first attempt (nesting a second `$if` inside the first's `else`) was itself broken and shipped a second crash-looping build.** Nixpacks' renderer (`scripts/config/template.mjs`) resolves `$if(COND) (...) else (...)` with a single-pass, non-greedy regex that cannot parse nested parentheses — it stops each capture at the first literal `)`, so the nested block's text looked correctly nested but rendered into a broken `nginx.conf` with stray unmatched parentheses, and nginx exited immediately on container start. Verified by re-implementing Nixpacks' exact regex-based algorithm and rendering the real template file (both directly in Node, matching Nixpacks' own script, and as a permanent PHP test double). Fixed properly this time by dropping the conditional entirely: this repo's `nixpacks.toml` and Nixpacks' own auto-detection mean `IS_LARAVEL` is always `"yes"` here, so the non-Laravel fallback branch is dead code and is simply omitted rather than conditioned. New regression test `LivewireTemporaryUploadConfigurationTest::test_nixpacks_template_renders_without_leftover_conditional_syntax` actually renders the template (via a faithful PHP port of Nixpacks' algorithm) instead of only pattern-matching the raw source text, which is what let the first, broken fix pass unnoticed.
- **Confirmed staging fully live under PHP 8.4.** MySQL 8.4's `caching_sha2_password` default is incompatible with Nixpacks' PHP 8.4 `mysqlnd` build (no compiled OpenSSL/RSA support — a PHP build limitation, not fixable in code); switched staging's MySQL user to `mysql_native_password` (enabled via a mounted `/etc/mysql/conf.d/native-password.cnf`, server restart, then `ALTER USER`). **Production's database has the same incompatibility** (confirmed read-only) and will need the identical fix applied before production is ever redeployed on PHP 8.4 — tracked as a mandatory pre-deploy step in `03_STACK_UPGRADE_PLAN.md`'s Step 6 checklist. `php artisan migrate --force` and `php artisan db:seed --force` (with `ADMIN_*` env vars) then completed cleanly on the previously-empty staging database, and the Filament admin panel was reached with a live login.
- Part 3 of the Stack Upgrade Plan: `laravel/framework` 12.64.0 → **13.23.0**, with the required `laravel/tinker` 2.11.1 → 3.0.2 companion bump (2.x only supports `illuminate/*` up to ^12) and `phpunit/phpunit` ^11.5 → ^12.0. `app/Providers/Filament/AdminPanelProvider.php`'s `VerifyCsrfToken::class` updated to the renamed `PreventRequestForgery::class` (Laravel 13 CSRF middleware rename; old name still works as a deprecated alias). Checked every item in Laravel's 13.x upgrade guide against this codebase — no `Route::domain()`/`->upsert()` usage, cache/session key-prefix fallbacks are already app-defined (unaffected by the changed skeleton default), and the new `cache.serializable_classes` hardening key is absent from `config/cache.php` so behavior is unchanged (confirmed by reading the `CacheManager`/`FileStore` source directly) — left as an optional future hardening item since adopting it would require allow-listing `App\Models\StorefrontSlide` and other Eloquent objects currently cached as-is. Full `php artisan test` under PHP 8.4.24: 556 passed, 2,981 assertions, no regressions; `MultiCompanyIsolationTest` reconfirmed separately; `npm run build` succeeds.
- Parts 4+5 of the Stack Upgrade Plan (done together, since Filament v5 pulls Livewire v4 in automatically): `filament/filament` and all `filament/*` sub-packages 4.12.5 → **5.7.5**, `livewire/livewire` 3.8.3 → **4.3.4**. No code changes were needed — `filament:upgrade` handled the mechanical changes, and Livewire's 3→4 breaking-change list (route registration, `wire:model` modifier changes, config key renames, self-closing component tags) doesn't touch anything this codebase actually uses. Full `php artisan test`: 556 passed, 2,981 assertions, no regressions (same count as the Laravel 13 step). Manually browser-smoke-tested every area the plan flagged as highest-risk — dashboard widgets, Products list/create (FileUpload rendering), Inbox, Cloud Storage Settings (`wire:model` text input + toggle switch), and a Quotation's Repeater "Add item" flow — all working, zero console/server errors.
- `nixpacks.toml`'s `NIXPACKS_NODE_VERSION` raised `"20"` → `"22"`: staging's first post-Filament-5 deploy build succeeded but logged a Vite warning (`package.json`'s `vite:^7.3.5` and its own `engines.node` require `>=20.19.0`; Nixpacks' `nodejs_20` package resolves to 20.18.1, just under that floor). Nixpacks only supports major-version selection, not patch pins, so bumping to the `22` major line is the fix.

## [1.22.0] - 2026-07-23

**Release type:** Minor Feature Update

Adds native Android update notifications and makes the user-controlled client upgrade boundary explicit.

### Added

- **Android update push notifications:** registered Capacitor devices can receive one Firebase Cloud Messaging notification per deployment, including while the app is in the background. Opening the notification only reveals the pending update; it never acknowledges or activates it.
- **Installed versus available release state:** Release Notes now identifies the user's acknowledged version separately from the newly deployed version, so an available release is not presented as already installed before Upgrade App is confirmed.

### Changed

- When an update is pending, same-origin Filament SPA navigation is held on the already-loaded screen and opens the Upgrade App prompt instead of fetching a newer page. The authenticated Upgrade App POST remains the only path that acknowledges the deployment and performs a cache-cleared reload.
- Database notification delivery and native push delivery are tracked independently, with per-device/per-deployment deduplication and invalid-token retirement.
- Admin image uploads now resize eligible JPEG/PNG files in the browser before the Livewire transfer (1600px for standard media, 800px for compact media), then receive the existing final server-side WebP optimization. SVG, GIF, and WebP uploads stay on the compatibility-safe server path.

### Technical Notes

- Native Android push requires the Capacitor Push Notifications plugin, a Firebase Android app registered as `com.zamzamint.erp`, `android/app/google-services.json` injected at build time, and server-side Firebase HTTP v1 service-account credentials. Secrets are never committed.
- Deployments must run `php artisan migrate --force` and `php artisan release:notify-deploy` after the new container is healthy; the five-minute scheduler remains a recovery path.
- A single replaced Coolify container cannot retain an executable old PHP/Blade backend per user. This release preserves the loaded client screen and prevents SPA navigation across the detected boundary; strict continued use of the complete old backend requires immutable blue/green releases with sticky per-installation routing and backward-compatible shared storage/database migrations.

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
