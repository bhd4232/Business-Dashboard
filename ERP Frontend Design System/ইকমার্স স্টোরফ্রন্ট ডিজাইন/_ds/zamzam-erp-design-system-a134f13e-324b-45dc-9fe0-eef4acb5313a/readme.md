# ZamZam ERP Design System

Design system for **ZamZam ERP** (shipped publicly as "Business Dashboard") — a Laravel + Filament inventory, purchasing, sales, accounts, and reporting ERP for small importers/wholesalers, plus a multi-tenant customer-facing **storefront** each company can run and re-theme.

This system covers **two product surfaces** built from the same codebase:

1. **Admin panel** — the internal ERP (Filament 4, amber accent, Inter). Products, orders, customers, suppliers, accounts, reports, and a **Theme Gallery** for picking the storefront's look.
2. **Storefront** — the public e‑commerce site each ZamZam-hosted company runs. It ships as a **6-theme system**; one theme, **Marketplace Pro**, is fully built out here (home → listing → product → cart) because it's the only one with a complete design across every page in the source. The other 5 are documented as color/type tokens only (see Colors group in the Design System tab) — they exist in the source as a home-page-only reference and a matching Theme Gallery entry, not a full page set.

### 2026 storefront modernization

Per the user's direction, the built Marketplace Pro storefront was upgraded to a more modern, animated execution (admin panel untouched, still exactly as recreated from source):
- **Typography**: Sora (display/headings/prices) + Inter (body) replaced the original Manrope + Source Sans 3 pairing — Inter is now shared with the admin panel.
- **Palette**: kept the brand navy (#0F2A43) + orange (#FF6A00), added a supporting teal accent (`--sf-accent` / `--sf-link`, #0D9488) for links, hover rings, and the cart-count badge.
- **Shape**: rounder, sleeker radii (`--radius-sf-lg` 14px, `--radius-sf-xl` 18px, new `--radius-sf-2xl` 24px for hero/CTA bands) — cards stay flat at rest, only lifting with a shadow on hover.
- **Motion** (`tokens/motion.css`, snappy 160–220ms timing): staggered fade-up reveals on section mount/page navigation, card hover-lift, button press/hover feedback, an add-to-cart pulse + "Added ✓" confirmation, a sticky header that shrinks and drops its category row on scroll, a page-transition fade between screens, and a shimmer skeleton loading state on first load.
- **Header search** simplified from the original category-dropdown + input + button combo to a single pill search (`components/storefront/SearchBar.jsx`).
- Layout density (the dense Amazon/Walmart-style grid) and information architecture were intentionally kept as-is per the source — only the visual/motion execution changed.

## Sources

- GitHub: [bhd4232/Business-Dashboard](https://github.com/bhd4232/Business-Dashboard) (branch `main`) — Laravel 12 / Filament 4 / Tailwind CSS 4 / Vite codebase. Explore it directly for the real component logic, Blade views, and business rules; this design system only captures the *visual* language.
- Within that repo, `ERP Frontend Design System/design_handoff_storefront_themes/` is a design-handoff bundle (static `.dc.html` mockups + a detailed `README.md`) written by a prior design pass for the storefront theme system — the primary source for all storefront visual specs in this project.
- Admin panel visuals were read from `app/Providers/Filament/AdminPanelProvider.php`, `resources/css/filament/admin/theme.css`, `app/Services/DynamicColorService.php`, and `resources/views/filament/partials/*`.

No Figma file was provided. Explore the repo further for anything not captured here — in particular the 5 non-Marketplace-Pro storefront themes only have a homepage designed in the source, and "Editorial Premium" has no dedicated design file at all (only token colors, referenced from the Theme Gallery mockup's data).

## Components

**Admin panel** (`components/admin/`): AdminButton, AdminBadge, Card (dashboard stat card), Input, SidebarNavItem.

**Storefront — Marketplace Pro** (`components/storefront/`): StorefrontButton, StorefrontBadge, PriceTag, ProductCard, SearchBar.

These are the primitives actually observed in the source markup (buttons, status pills, price displays, product cards, the joined category+search bar, sidebar nav links). No Figma component library was provided, so no other primitives were invented beyond what these two product surfaces use.

### Intentional additions
- `Card` (admin stat widget) — the source only shows widget content inline in Blade/PHP (`BusinessOverview`, `TopBusinessPerformers`, etc.) with no isolated markup to lift verbatim; this is a reasonable generalization of their visual shape (label, big number, delta).
- `Input` (admin) — same reasoning; Filament form fields are rendered by the framework, so this is a plain reconstruction of their visible style (label, border, radius), not copied from a specific file.

## UI kits

- `ui_kits/admin-dashboard/` — Dashboard (stat cards + trend + low-stock table), Products (table with status badges), and **Theme Gallery** (recreated pixel-for-pixel from the source `.dc.html` — sidebar, company switcher, 3-column theme cards with abstract previews).
- `ui_kits/storefront-marketplace-pro/` — Home, Listing, Product detail, and Cart, click-through and cross-linked, recreated from the source `Storefront - Marketplace Pro*.dc.html` files. Checkout and Track pages exist in the source but weren't built into this kit's click-through — see the source bundle for their exact layout if you need them next.

## Index

```
styles.css                      → root stylesheet (@imports only)
tokens/colors.css                colors.css, typography.css, spacing.css, effects.css
tokens/typography.css
tokens/spacing.css
tokens/effects.css
guidelines/                     16 foundation specimen cards (Colors, Type, Spacing, Brand groups)
components/admin/                AdminButton, AdminBadge, Card, Input, SidebarNavItem
components/storefront/           StorefrontButton, StorefrontBadge, PriceTag, ProductCard, SearchBar
ui_kits/admin-dashboard/         Dashboard, Products, Theme Gallery screens
ui_kits/storefront-marketplace-pro/  Home, Listing, Product, Cart screens
assets/                          (empty — no logo/icon/imagery files exist in the source; see Iconography)
SKILL.md                         Claude Code-compatible skill wrapper
github.md                        source-repo sync record
```

## Content fundamentals

The source is an ERP admin panel (internal, operational copy) plus a retail storefront (customer-facing, sales copy) — very different registers:

- **Admin panel copy is terse and functional**: "Add product", "Save changes", "Low stock", "Track shipment". No personality, no exclamation points, no emoji in body copy (emoji ARE used as literal sidebar/status icons in the Theme Gallery mockup, e.g. ⚙️ 🎨 📄 🎠 — see Iconography). Second person is avoided; labels describe the object, not "your" object (e.g. "Storefront Settings", not "Your storefront").
- **Storefront copy is plain, benefit-led retail copy**, second person implied through offers rather than direct address: *"Genuine parts, factory-direct pricing, and nationwide installation support."*, *"Up to 22% off circular knitting machines"*, *"Trusted by 1,200+ garment factories nationwide"*. Headlines lead with the number/offer (discount %, unit count) — a B2B-industrial register (garment-factory buyers), not consumer-casual.
- **Casing**: sentence case throughout for headings and buttons ("Add to cart", "Shop the sale", "Proceed to checkout") — never Title Case, never all-caps except tiny eyebrow labels ("SEASON CLEARANCE") which are all-caps by design (12px, letter-spaced).
- **Currency**: always ৳ (Bangladeshi Taka) prefix, comma-grouped, no decimals shown for whole-number pricing — this is a Bangladesh-market product; don't swap in $ or generic placeholders.
- **No filler stats or fake urgency beyond what's in the source pattern** — countdown timers and "N units left" scarcity messaging are part of the real Marketplace Pro design language, not to be invented further for other surfaces that didn't use them.

## Visual foundations

**Two distinct visual languages, cleanly separated by surface — do not mix them:**

- **Admin panel**: Inter, amber (#F59E0B) primary on white cards over a light-gray (#F9FAFB) page background, 1px gray-200 borders, 8–12px radius, no shadows beyond a barely-there `0 1px 2px rgba(0,0,0,.04)` on cards. Sidebar is white with grouped nav (uppercase 11px gray-400 group labels), active items get a soft amber-100 background + amber-800 text. Sticky page header uses a frosted-glass effect (`backdrop-filter: blur(10px)` over `rgb(249 250 251 / .94)`) — the **only** blur/glass usage anywhere in the system. Sidebar icons get a small spring-eased lift + rotate on hover (`cubic-bezier(0.34, 1.56, 0.64, 1)`, 220ms) — the one deliberate micro-animation; respects `prefers-reduced-motion`.
- **Storefront (Marketplace Pro)**: Sora (headings/prices, 500–800) + Inter (body) — updated 2026, see "2026 storefront modernization" above. Deep navy (#0F2A43/#071A2C) header/footer, orange (#FF6A00) CTA, a new teal accent (#0D9488) for links/hover rings, light gray (#F5F6F8) page background, **flat white cards with no border/shadow at rest** — they lift with a shadow only on hover, the one deliberate departure from "static flat" now that motion is part of the language. Radii are rounder than the original spec (14–24px) for a sleeker feel. Hero banners use a navy gradient + diagonal dark-to-transparent overlay for text legibility over imagery.
- **Layout**: both surfaces are desktop-first. Storefront max-width is 1360px, centered, `padding: 0 32px`; header search/account clusters use `flex-shrink`/`min-width:0` specifically to survive 1280–1366px real desktop widths (a bug the source explicitly called out and fixed — preserve this pattern in any new header work).
- **Imagery**: the source uses **zero real photography** — every image slot is a flat tinted rectangle (`background:#EEF1F4` on storefront, similarly neutral on admin). Treat this as the placeholder convention until real product photography is supplied; don't invent illustrations or stock photography.
- **The other 5 storefront themes** (Industrial B2B, Editorial Premium, Fresh Value, Bold Studio, Corporate Classic) each define a completely different visual system on purpose — steel/amber industrial, minimal serif corporate, rounded friendly retail, near-black high-contrast editorial, and formal maroon/ivory institutional respectively. Their full color and radius tokens are in `tokens/colors.css` / the Colors cards, for when one of those needs to be built out.
- **Corner radius as a brand signal**: each theme's radius is a deliberate personality marker, from 0px (Corporate Classic, formal) to 16px (Fresh Value, friendly) — see the "Corner radius" specimen card.

## Iconography

- **No icon library, icon font, or SVG sprite exists in the source.** The storefront mockups hand-draw a handful of inline SVGs (search magnifier, cart, hamburger/category-list) using simple 1.8–2.4px stroke outlines, no fill — the real codebase's storefront layout already uses **Heroicons** for equivalent icons (per the design-handoff README), so prefer Heroicons (outline style, ~1.8–2px stroke) for any new storefront icon needs, and copy the exact inline SVGs already used in `ui_kits/storefront-marketplace-pro/*.jsx` where they overlap.
- **The admin panel's Theme Gallery mockup uses plain emoji as sidebar/status icons** (⚙️ Storefront Settings, 🎨 Theme Gallery, 📄 Storefront Pages, 🎠 Product Carousels, plus 🚚 🔒 🛠️ ↩️ 📈 📦 🧾 for trust items and stat cards elsewhere). This is a real, deliberate pattern from the source — reuse it for admin dashboard icons rather than switching to an icon font, but don't extend emoji into the storefront's customer-facing surfaces where none appear.
- Filament's own admin panel ships Heroicons under the hood for framework chrome (nav caret markers, user-menu icons) — visible in `AdminPanelProvider.php` (`Heroicon::OutlinedUserCircle`, etc.). Match that when extending real admin chrome.
- No logo, brand mark, or icon assets exist as files anywhere in the source repo — see below.

## Assets

**No logo file exists in the source repository** — `assets/` is intentionally empty. Every brand mark seen in the mockups is a rendered initials badge (a colored rounded-square with 1–2 letters), which is why this system's `guidelines/brand-mark.card.html` reproduces that pattern (amber "Z" for the ZamZam ERP admin, per-tenant initials like "GM" for Garments Machinery Co. on the storefront) rather than inventing a logo. If a real logo/wordmark file exists in Figma or brand assets not attached here, add it to `assets/` and update the brand-mark card and `brandLogo()`/`darkModeBrandLogo()` calls in `AdminPanelProvider.php` accordingly.

## Fonts

All fonts are standard Google Fonts, loaded via `@import` in `tokens/typography.css` exactly as the source recommends ("keep as Google Fonts `@import`/`<link>`, consistent with how the rest of the app loads fonts"): **Inter** (admin, and storefront body as of the 2026 refresh) and **Sora** (storefront display/headings, replacing Manrope). The other 5 storefront themes' fonts (Space Grotesk/IBM Plex, Poppins/Nunito Sans, Archivo/Work Sans, Source Serif 4) are documented in the README's theme descriptions but not loaded globally, since only Marketplace Pro ships a built UI kit.
