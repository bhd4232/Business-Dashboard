# Handoff: Storefront Theme System (6 themes + Admin Theme Gallery)

## Overview
A multi-theme redesign of the ZamZam ERP public storefront. Each company (Garments Machinery, Tasneem Knitting Industry / future B2B machinery importers, Solar Items, Gadget Items, Gift Items, and future companies) can select one of 6 fully distinct visual themes from a new admin "Theme Gallery" page. One theme (Marketplace Pro) is built out as a complete flow: home → listing → product detail → cart → checkout → order tracking. The other 5 themes are homepage-only references; their layout/typography/component patterns should be extended to the same page set using the same recipe.

## About the Design Files
The files in this bundle are **design references built as static HTML** (Design Components) — they show intended look, layout, typography, and copy, not production code to copy directly. The task is to **recreate these designs as Laravel Blade views** inside the existing `zamzam-erp-project` codebase (Laravel 12 + Filament 4 + Tailwind CSS 4 + Vite), reusing the existing storefront data layer (`Company`, `Product`, `Category`, `StorefrontCart`, `Order`, `StorefrontSetting`, etc.) — not shipping the HTML files as-is. All product data, prices, names, and stats in the mockups are **placeholder content** for layout purposes only.

## Fidelity
**High-fidelity.** Colors, typography, spacing, and component structure below are final — recreate pixel-accurately using Tailwind/Blade in the existing codebase's conventions. Image placeholders (plain gray/tinted blocks) should be replaced with real `<img>` tags bound to `$product->image`, `$setting->banner_images`, etc., exactly like the current `resources/views/storefront/*` views already do.

---

## Theme System Architecture (recommended)

1. Add a `theme` column to `storefront_settings` (migration), e.g. `enum('marketplace_pro','industrial_b2b','editorial_premium','fresh_value','bold_studio','corporate_classic')`, default `marketplace_pro`.
2. Add a **Theme Gallery** Filament page (`app/Filament/Pages/ThemeGallery.php` + Blade view) under the existing `Storefront` navigation group, next to `Storefront Settings`. See `Admin - Storefront Theme Gallery.dc.html` for the exact layout — a 3-column card grid, each card showing a mini abstract preview (colored blocks representing the theme's header/hero/cards), name, one-line tagline, description, "Preview" (opens the live storefront in a new tab) and "Apply theme" / "Active" button.
3. Split `resources/views/storefront/layout.blade.php` into one layout partial per theme (e.g. `resources/views/storefront/themes/{theme}/layout.blade.php`), or keep one layout file that branches header/footer/typography via `@if($setting->theme === '...')` includes — prefer separate partials per theme for maintainability, since the themes differ in structure, not just color.
4. Each theme needs its own set of page templates (home, products index, product show, cart, checkout, track) under `resources/views/storefront/themes/{theme}/`. Only `marketplace_pro` is fully specified below across all 6 page types; the other 5 are specified for the homepage and should be extended using the same visual language (see each theme's Design Tokens) when the corresponding page is built.
5. Existing controllers (`ProductIndexController`, `ProductShowController`, `StorefrontCart`, checkout/track controllers) do not change — only the view resolution changes to pick the theme-specific Blade file based on `$setting->theme`.

---

## Global notes across all themes

- All layouts are desktop-first, `max-width` container between 900–1360px depending on theme, centered, `padding: 0 32px`.
- All monetary values use `৳` (Bangladeshi Taka) prefix, comma-grouped.
- Header search bars and account/cart clusters must use `flex-shrink`/`min-width:0` so they do not overflow at 1280–1366px real desktop widths (this was a bug found and fixed during design — search input must be allowed to shrink, not fixed-width).
- Fonts are loaded from Google Fonts via `<link>` — self-host or keep as Google Fonts `@import`/`<link>` in the Vite-built CSS, consistent with how the rest of the app loads fonts.
- Every theme file in this bundle is self-contained (own fonts, own copy) — treat each as the single source of truth for that theme's tokens.

---

## Theme 1 — Marketplace Pro (default; dense, deal-driven retail)
**File:** `Storefront - Marketplace Pro.dc.html` (home), `Storefront - Marketplace Pro - Listing.dc.html`, `Storefront - Marketplace Pro - Product.dc.html`, `Storefront - Marketplace Pro - Cart.dc.html`, `Storefront - Marketplace Pro - Checkout.dc.html`, `Storefront - Marketplace Pro - Track.dc.html`

**Reference style:** Amazon/Walmart-style dense retail. Best default for general product companies (Garments Machinery Co. used as the example brand).

### Design Tokens
- Fonts: `Manrope` (weights 500/600/700/800) for all headings/prices/brand; `Source Sans 3` (400/500/600/700) for body text.
- Colors: header/footer navy `#0F2A43` (footer slightly darker `#071A2C`); primary CTA orange `#FF6A00`; page background `#F5F6F8`; card background `#FFFFFF`; body text `#14181F`; muted text `#6B7885`/`#5C6672`; link blue `#0A6CFF`; success/in-stock green `#1A7F37`; deal/discount red `#C4001D`; star rating fill `#FF9A00`.
- Radius: 8–14px on cards/buttons; pill (999px) on badges.
- Card style: white background, no border, 12–14px radius, no shadow (flat, dense grid aesthetic).

### Home page layout
1. Utility bar (navy `#071A2C`, 7px vertical padding, 12.5px text): free-shipping message left, "Track your order" / "Help center" links right.
2. Header (`#0F2A43`, 18px vertical padding): logo mark (38×38 orange rounded-square with initials) + brand name (Manrope 800 20px white) — flex-shrink:0; search bar (category `<select>` + text input + orange submit button, all joined, `border-radius:8px`, max-width ~520px, must shrink via `min-width:0`) flex:1; right cluster (Hello/Account&Lists, Orders&Returns, Cart-with-count-badge) flex-shrink:0, white text, 12.5–14px.
3. Category nav strip below header (same navy, 1px top border `rgba(255,255,255,0.1)`): "All categories" (with hamburger icon) + 5 category text links + "Today's deals" in orange, 13.5px semibold.
4. Hero (24px top margin): 2-column grid (`2fr 1fr`) — left is a large navy gradient banner (14px radius) with orange "SEASON CLEARANCE" pill badge, 38px Manrope 800 white headline, 15px gray-blue paragraph, orange CTA button; right column stacks 2 white cards ("Spare parts, in stock" / "Book installation & service") each with an image block and outlined button.
5. Trust strip: white rounded card (14px radius) with a 4-column grid of icon+title+subtitle trust items (delivery, secure payment, installation, returns).
6. "Today's deals" section: header with red countdown pill, 5-column product grid, each card has a red "-N%" discount badge (top-left, absolute), square image block, 2-line title, 5-star row + review count, price (red, Manrope 800) + strikethrough old price (gray).
7. "Shop by category" section: 6-column grid of circular icon tiles + label.
8. "Recommended for your business" (featured grid): 5-column product cards — image, title, stars+reviews, price (Manrope 800, black), green in-stock line, full-width navy "Add to cart" button.
9. Brand trust strip: full-width navy rounded band, headline + subcopy left, orange "Open a business account" button right.
10. Footer: navy `#071A2C`, 4-column link grid (Get to know us / Let us help you / Business accounts / Contact), bottom copyright bar.

### Listing page layout
- Same utility bar + header + breadcrumb (`Home / Category name`).
- Body: `260px` filter sidebar (white card: Category checkboxes with counts, Price range radios, Availability checkbox) + results column.
- Results header: "Showing 1-12 of 48 results" left, sort `<select>` right.
- 3-column product grid, same card style as home but with min-height title.
- Pagination: row of centered 36×36 rounded squares, active page navy-filled.

### Product detail page layout
- Breadcrumb: Home / Category / Product name.
- 3-column grid (`0.9fr 1fr 0.7fr`): thumbnail rail (4 stacked 64×64 squares, active one has orange 2px border) + large square image; product info (category link, H1 title, star row + rating count + in-stock, price block with strikethrough + "Save %" badge + VAT note, key-specs 2-column grid, "About this machine" paragraph); sticky buy box (price, in-stock line, quantity stepper, "Add to cart" orange button, "Buy now" navy button, 3 trust bullet points).
- "You may also like" 4-column related product grid below.

### Cart page layout
- Simplified header (logo + "Shopping Cart" label, no search/nav).
- 2-column grid: cart items list (white card, each row: 80×80 thumb, name + in-stock + remove link, qty stepper, line total) + order summary card (subtotal, VAT, delivery=Free in green, total in Manrope 800 18px, orange "Proceed to checkout" button).

### Checkout page layout
- Step indicator: "1. Delivery details → 2. Review order → 3. Confirmation" (13px bold, active step navy, others gray).
- 2-column grid: delivery form (name/phone 2-col, address textarea, order note textarea, payment method radio list: Cash on delivery / bKash / Bank transfer) + order summary card (same as cart, orange "Place order" button).

### Track page layout
- Order header card: order number (Manrope 800 19px) left, status pill right (e.g. "In transit", orange-tinted background `#FFF1E6` text `#B85200`).
- Vertical timeline card: left-aligned dots connected by a vertical line, each step has a colored dot (green=done, orange=current with "Latest" pill, gray=pending), title + timestamp.
- Bottom info row: Courier + tracking ID, Total.

---

## Theme 2 — Industrial B2B (corporate, spec-sheet led)
**File:** `Storefront - Tasneem Knitting Industry.dc.html`
**Reference style:** B2B industrial supplier (spec sheets, RFQ, catalog). Use for machinery/wholesale-import companies (example used: Tasneem Knitting Industry, a China→Bangladesh knitting-machine importer/reseller to garment factories).

### Design Tokens
- Fonts: `Space Grotesk` (500/600/700) headings/logo; `IBM Plex Sans` (400–700) body; `IBM Plex Mono` (500/600) for stat numbers and spec callouts.
- Colors: steel navy `#0B2036` (header/hero/CTA bands), darkest navy `#05131F` (utility bar/footer), safety amber accent `#F2A93B`, steel blue secondary `#3E7CB1`, warm off-white background `#FAF9F6`, card white `#FFFFFF`, hairline border `#E4E1D8`, body text `#10161C`, muted `#5B6770`/`#7A8790`.
- Radius: small — 4–6px throughout (sharper, more "engineered" feel than the retail themes). Buttons are 4px radius, not pill.

### Home page layout
1. Utility bar (`#05131F`): ISO cert + export-country count left, phone/email right.
2. Header (`#0B2036`): square amber logo mark + brand name/sub-label stack; center nav (Machinery/Spare Parts/Custom Solutions/Export & Logistics/About); right: "Track shipment" link + amber "Request a Quote" button (primary CTA pattern for this theme — replaces "Add to cart" everywhere).
3. Hero: full-width dark navy section (~520px), background photo placeholder with a left-to-right dark gradient overlay, amber "Est. 1998 · Bangladesh" pill, 44px Space Grotesk headline, paragraph, two CTAs (amber filled "Browse machinery catalog" + outline "Talk to an engineer"), a 4-column stat row below (hero stats, IBM Plex Mono numerals) separated by a top hairline.
4. Certifications strip: white band, "Certified & Trusted" label + 4 cert badges (dot + label).
5. "Product Lines" section: 3-column cards (photo 200px + name + description + bottom row: mono spec callout left, "Spec sheet →" link right).
6. RFQ band: full-width navy rounded panel, headline/subcopy left, amber "Request a Quote" + outline "Download brochure" buttons right.
7. "Global Reach" section: 2-column — text + 2×2 stat grid (amber left-border accent) on the left, world-map/photo placeholder on the right.
8. Client logos: centered label + 6-column logo placeholder grid.
9. Footer: darkest navy, 4-column (company blurb / Products / Company / Contact), bottom bar.

---

## Theme 4 — Fresh Value (friendly, value-focused)
**File:** `Storefront - Fresh Value.dc.html`
**Reference style:** Walmart-style approachable value retail. Applied to "Solar Items Co." in the mockup.

### Design Tokens
- Fonts: `Poppins` (500–800) for headings/brand/prices; `Nunito Sans` (400/600/700) for body.
- Colors: primary blue `#0071CE`, accent yellow `#FFC220`, dark footer navy `#10233A`, page background `#F3F7FC`, card white, savings-green `#00913C`, body text `#14202B`, muted `#5C6C7A`/`#93A2AF`.
- Radius: very rounded — 16px cards, 999px (pill) buttons and badges throughout. Friendliest/roundest of all 6 themes.

### Home page layout
1. Yellow utility bar (bold, centered, dark navy text) — single promo message with emoji.
2. Header (`#0071CE`): circular logo badge + brand name; pill-shaped search bar; right cluster (Store finder / Account links + pill "Cart" badge with count bubble). Category nav row below in a lighter blue.
3. Hero: blue gradient rounded panel (20px radius), yellow "ROLLBACK" pill, 42px Poppins 800 headline, paragraph, yellow pill CTA button; image placeholder block on the right with translucent white fill.
4. Value props: 4-column white rounded cards, emoji icon + bold title + muted subtitle, centered text.
5. "Rollback" product grid: yellow-pill "Rollback" section label, 4-column cards with green "Save ৳N" pill badge, rounded image block, price in blue Poppins 800 + strikethrough old price, full-width blue pill "Add to cart" button.
6. "Shop by need" tiles: 4-column colored rounded panels (each a different flat color — blue/green/orange/purple), white bold title + subtitle + underlined link.
7. Footer: dark navy, 4-column, Poppins bold section headers.

---

## Theme 5 — Bold Studio (high-contrast, statement type)
**File:** `Storefront - Bold Studio.dc.html`
**Reference style:** Bold graphic/magazine-style for tech and lifestyle gadget brands. Applied to "Gadget Items Company" (audio/wearables/smart home) in the mockup.

### Design Tokens
- Fonts: `Archivo` (600–900) for all display type, brand, and prices; `Work Sans` (400–600) for body copy.
- Colors: near-black background `#0A0A0A`, off-white text `#F5F5F0`, single neon-lime accent `#C6FF3D`, card surface `#151515`/`#1A1A1A`, hairline `#232323`, muted gray `#8A8A85`.
- Radius: near-zero — 2–4px only. Sharp, poster-like.
- Type scale is extreme: hero headline runs up to 96px Archivo 900 with tight (0.94) line-height — this theme leans on huge type more than imagery.

### Home page layout
1. Header: bold wordmark ("GADGET" + lime period), uppercase nav (New/Audio/Wearables/Smart Home), Search link + lime pill "Cart (N)" badge. Bottom hairline.
2. Hero: 2-column (`1.4fr 1fr`) bottom-aligned — massive multi-line Archivo 900 headline (one line in lime) on the left, short paragraph + lime CTA button on the right; full-width wide (21:8) image placeholder band beneath.
3. Stat strip: 4-column bordered grid (top+bottom hairline, vertical dividers), each cell a huge lime Archivo 900 number + uppercase label.
4. "DROP 04" asymmetric product grid: 2-column (`1.6fr 1fr`) — large dark card with bottom-anchored content (image + name + spec line + big price) on the left; two stacked smaller cards on the right.
5. Footer: wordmark + blurb / Shop / Support columns, hairline borders, small muted copyright line.

---

## Theme 6 — Corporate Classic (institutional, trust-first)
**File:** `Storefront - Corporate Classic.dc.html`
**Reference style:** Traditional corporate/holding-company site — formal, text-forward, institutional trust over visual flash. Applied to a "Gift Trading Holdings" wholesale/trading example (distinct from the machinery-specific Industrial B2B theme — this one suits general trading houses, conglomerates, or any company that wants a formal rather than modern-retail feel).

### Design Tokens
- Fonts: `Source Serif 4` (500–700) for all headings/wordmark; `Source Sans 3` (400–700) for body/nav.
- Colors: warm ivory background `#FBFAF7`, deep maroon accent `#7A1F2B` (used sparingly — CTA button, eyebrow labels, active border), forest-green footer `#1C2A1F`, hairline border `#E3E0D6`, muted text `#5C5C56`/`#6B6B63`, numbered-list ghost numerals `#D9D4C4`.
- Radius: 0 everywhere — sharp rectangular blocks, formal/institutional feel.
- Header has a distinctive 2px solid maroon bottom border (not a soft shadow) — a classic "masthead" device.

### Home page layout
1. Utility bar (dark green `#1C2A1F`): "A member of the ZamZam Group of Companies" left, head office location right.
2. Header (ivory bg, 2px maroon bottom border): square-bordered initials mark + serif company name + small-caps sub-label stack (flex-shrink:0, all `white-space:nowrap` to avoid the overlap bug found during design — nav and CTA must have `flex-shrink:0` too); center nav (Products/Wholesale/About the Group/Contact); maroon "Open an account" button, `white-space:nowrap`.
3. Hero: 2-column, eyebrow "Established 2004" in maroon, 40px serif headline, paragraph, dark-green filled + maroon-outline button pair; plain tinted image block on the right. Bottom hairline border closes the section.
4. "What we offer" — numbered services: 4-column, each cell left-bordered, huge ghost-tone serif numeral (01–04) + bold title + description.
5. "Product categories" — table-like list: each row is a 3-column grid (serif category name / description / right-aligned underlined "Enquire" link), rows separated by top hairlines, no card backgrounds (deliberately plain/formal).
6. Footer: dark green, 3-column (blurb / Company links / Contact), bottom bar.

---

## Admin — Theme Gallery (Filament dashboard page)
**File:** `Admin - Storefront Theme Gallery.dc.html`

### Layout
- Standard Filament shell: white 260px sidebar (nav groups "Business" with icon+label items, then "Storefront" group with Storefront Settings / **Theme Gallery** (highlighted active, amber background `#FEF3C7` text `#92400E`) / Storefront Pages / Product Carousels).
- Top bar: page title left, company `<select>` (switches which company's theme you're editing) + avatar circle right.
- Main content: intro heading + one-line description, then a 3-column grid of theme cards.
- **Theme card** (white, 1px `#E4E4E7` border, 12px radius): top 170px preview area rendered as abstract colored blocks (a thin accent bar + a header-height block + 3 equal content blocks) using the theme's own `previewBg`/`accent`/`surface` colors — this is a schematic, not a screenshot; a green "Active" pill appears top-right on the currently-applied theme. Below: theme name (bold 15px), one-line tagline (muted), 2–3 line description, then a 2-button row: outlined "Preview" (opens the theme's live storefront) + solid "Apply theme" / disabled-style "Active" state button.
- Fonts/colors: Filament's own admin theme — `Inter`, background `#F4F4F5`, primary accent amber `#F59E0B` (matches the existing Filament panel's configured Amber primary color per `AdminPanelProvider.php`).
- The 6 cards in the mockup use each theme's own token colors for the abstract preview swatches — reuse those exact hex values (listed under each theme above) so the gallery accurately previews the real theme.

---

## Assets
No real photography/logos are used — all imagery is represented as flat-colored placeholder blocks (`background: <tint color>`, no image). When implementing, wire these to the existing `<img>`/asset patterns already used in `resources/views/storefront/*.blade.php` (`asset('storage/'.$product->image)`, `$setting->banner_images`, etc.). No icon library is used — a handful of inline SVGs (search, cart, hamburger) are hand-drawn as inline `<svg>` and can be copied as-is or swapped for the codebase's existing icon set (Heroicons, already used in `resources/views/storefront/layout.blade.php`).

## Files in this bundle
```
Storefront - Marketplace Pro.dc.html                screenshots/01-marketplace-pro-home.png
Storefront - Marketplace Pro - Listing.dc.html       screenshots/02-marketplace-pro-listing.png
Storefront - Marketplace Pro - Product.dc.html       screenshots/03-marketplace-pro-product.png
Storefront - Marketplace Pro - Cart.dc.html          screenshots/04-marketplace-pro-cart.png
Storefront - Marketplace Pro - Checkout.dc.html      screenshots/05-marketplace-pro-checkout.png
Storefront - Marketplace Pro - Track.dc.html         screenshots/06-marketplace-pro-track.png
Storefront - Tasneem Knitting Industry.dc.html       screenshots/07-industrial-b2b.png   (Industrial B2B theme)
Storefront - Editorial Premium.dc.html               screenshots/08-editorial-premium.png
Storefront - Fresh Value.dc.html                     screenshots/09-fresh-value.png
Storefront - Bold Studio.dc.html                     screenshots/10-bold-studio.png
Storefront - Corporate Classic.dc.html               screenshots/11-corporate-classic.png
Admin - Storefront Theme Gallery.dc.html             screenshots/12-admin-theme-gallery.png
```
Each `.dc.html` file opens directly in any browser — open it to see the live rendered design and inspect exact markup/inline styles for any value not called out above. The `screenshots/` folder has a static PNG of each page (above-the-fold) for quick visual reference without opening every file.
