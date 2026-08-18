# Noor Solar Energy — ERP Storefront UI/UX & Interaction Specification

## 1. Project Overview

**Brand:** Noor Solar Energy  
**Storefront Stack:** Laravel ERP Storefront + Livewire + Tailwind CSS + Three.js  
**Primary Market:** Bangladesh  
**Manufacturing Backbone:** Ronma Solar  
**Design Direction:** Premium, minimal, modern, engineering-focused, animated and conversion-driven

The storefront should position Noor Solar Energy as a modern Bangladesh-facing solar brand with strong manufacturing credibility, real product presentation, ERP-connected stock visibility, project quotation workflows, technical transparency and interactive product experiences.

The visual design must follow Noor Solar Energy's approved branding: **deep green + warm solar yellow/gold + warm off-white**.

---

## 2. Core Brand System

### Primary Colors

| Usage | Color |
|---|---|
| Deep Brand Green | `#064C38` |
| Dark Green | `#043628` |
| Solar Yellow / Gold | `#F5BF17` |
| Light Gold | `#FFD34D` |
| Soft Mint | `#EEF5F0` |
| Warm Off-White | `#F7F6F2` |
| Border / Neutral Line | `#E5E3DA` |

### Typography

- **Primary UI:** Inter
- **Display / Headings:** Sora
- Bold, compact, modern typography
- Strong visual hierarchy
- Avoid oversized paragraphs
- Use short, readable blocks of copy

### Brand Personality

- Premium
- Engineering-driven
- Dependable
- Modern
- Local
- Clean energy focused
- B2B-ready
- Enterprise-friendly

---

## 3. UI Principles

1. Use **Tailwind CSS as the main design system**.
2. Keep cards rounded, spacious and clean.
3. Avoid excessive shadows.
4. Use deep green for premium/technical sections.
5. Use solar yellow for highlights, CTA buttons, active states and energy-related visual emphasis.
6. Use real solar product/installations wherever possible.
7. Do not use abstract fake 3D solar assets where real product imagery is available.
8. Avoid text overlap on all breakpoints.
9. Every section must work on mobile, tablet and desktop.
10. Animations should support usability—not become decoration-only.

---

## 4. Header

### Components

- Noor Solar Energy logo
- Tagline: `POWER WITH CONFIDENCE`
- Main navigation:
  - Solutions
  - Modules
  - Technology
  - Global Network
  - Project Quote
- CTA:
  - `Get a Quote`
- Mobile hamburger menu

### Behavior

- Sticky header
- Backdrop blur
- Light warm background
- Compact mobile header
- CTA may collapse on small screens
- Menu interactions should be Livewire-friendly

---

## 5. Hero Section

### Main Headline

**Solar buying, re-engineered.**

### Supporting Copy

Real solar modules, ERP-connected stock signals, application-based discovery and project-ready quotation designed for Bangladesh.

### CTA

- Explore Modules
- Build My System

### UI Elements

- Real rooftop solar installation photo
- Floating product card
- ERP Live status
- N-Type TOPCon product indicator
- Project / B2B indicator
- Animated solar light beam
- Floating 3D status elements
- Subtle image parallax

### Hero Animation

- Mouse parallax
- Floating cards
- Gentle image drift
- Light beam pass
- Animated ERP status dot
- No aggressive motion

---

## 6. Technology Capability Ticker

Auto-scrolling horizontal capability ticker:

- N-Type TOPCon
- Bifacial Module
- Dual Glass
- ERP Live Stock
- Project Supply
- Bangladesh Support

### Interaction

- Infinite marquee
- Pause on hover if required
- Swipeable on mobile
- Fully responsive

---

## 7. Shop by Application

Users should start from their use-case rather than technical specifications.

### Categories

#### Home Solar
- Residential
- Hybrid systems
- Backup
- Electricity cost reduction

#### Office Solar
- Commercial rooftop
- Office buildings
- Business energy savings

#### Factory & Industrial
- Industrial rooftop
- High-capacity solar
- EPC quotation

#### Project / Utility Scale
- Bulk module supply
- Large centralized projects
- Project pricing

### UI

Use Bento-style cards with:
- Real installation photography
- Hover zoom
- 3D card tilt
- Glare effect
- CTA arrow

---

## 8. Featured Solar Modules

### Product Card Information

Each card may display:

- Real product photo
- Wattage
- Module family
- Stock status
- ERP status
- Technology
- Construction
- Project suitability
- Dealer / project quotation state
- CTA

### Example

**550W**  
N-Type TOPCon Module

- In Stock
- ERP Live
- Dual Glass
- Bifacial
- Project Quote Available

### Pricing Behavior

#### Retail Product
Show public price.

#### Dealer / Wholesale
Show:
`Login to View Dealer Price`

#### Project / Bulk
Show:
`Request Project Pricing`

---

## 9. Product Card Hover Interaction

Every main storefront card should support premium mouse interaction.

### Desktop

- Pointer-follow 3D tilt
- Perspective transform
- Dynamic glare
- Small lift on hover
- Shadow intensity increase
- Border highlight using solar yellow
- Image scale/parallax
- Optional arrow movement

### Mobile

Do not rely on hover.

Instead use:
- Tap feedback
- subtle scale
- swipe interaction
- clear CTA

### Accessibility

Respect:

```css
@media (prefers-reduced-motion: reduce)
```

Disable or heavily reduce animations when requested by the user's OS/browser.

---

## 10. Magnetic CTA Buttons

Primary CTA buttons may move slightly toward the pointer.

Use this only for:

- Get a Quote
- Explore Modules
- Build My System
- Select Module
- Request Project Quote

Movement must remain subtle.

Do not apply magnetic interaction to small navigation links.

---

## 11. Three.js Interactive Module Lab

A key premium feature of the storefront.

### Purpose

Allow customers to inspect a solar module interactively before opening a technical datasheet.

### Features

- Real-time 3D rendering
- Front view
- Cell view
- Back view
- Pointer-based rotation
- Click-to-flip
- Floating motion
- Dynamic lighting
- Solar yellow accent light
- Green ambient light
- Animated scan line
- Module frame
- Cell grid
- Rear junction box

### Future Production Upgrade

Replace the demo-generated module with an actual:

- `.glb`
- `.gltf`

model connected to the ERP product SKU.

### Example Mapping

```text
ERP SKU
│
├── Product Name
├── Wattage
├── Technology
├── Stock
├── Datasheet
├── Main Product Image
├── 3D Model URL
├── Warranty
├── Certification
└── Warehouse Availability
```

---

## 12. Livewire + Three.js Integration

Three.js owns its WebGL canvas, so Livewire must not continuously morph that DOM region.

### Recommended Blade Pattern

```html
<div
    id="three-stage"
    wire:ignore
    data-livewire-hook="noor-module-viewer"
>
</div>
```

### Product Selection

Example:

```html
<button
    wire:click="selectSku('NOOR-550-TOPCON')"
    class="..."
>
    Select this module
</button>
```

or dispatch an event:

```js
window.dispatchEvent(
    new CustomEvent('noor:sku-selected', {
        detail: {
            sku: 'NOOR-550-TOPCON'
        }
    })
);
```

### Livewire Listener Example

```php
#[On('module-selected')]
public function selectModule(string $sku)
{
    $this->selectedSku = $sku;
}
```

### Important

When product/variant changes:

- Do not rebuild the entire Three.js renderer unnecessarily.
- Update model/material/state inside the existing scene.
- Dispose previous geometry/material/textures before loading another heavy model.

---

## 13. Solar System Builder

This should be a key conversion funnel.

### Step 1 — Application

- Home
- Office
- Shop
- Factory
- EPC / Large Project

### Step 2 — Required Capacity

Examples:

- 1–3 kW
- 3–10 kW
- 10–50 kW
- 50–100 kW
- 100 kW+

### Step 3 — Goal

- Backup
- Reduce Electricity Bill
- Hybrid
- Full Solar
- Industrial Project

### Step 4 — Project Details

- Customer name
- Phone
- Email
- Location
- Monthly electricity bill
- Roof / available area
- Expected project timeline

### Step 5 — ERP Lead

Create:

```text
Lead
│
├── Customer
├── Phone
├── Application
├── Capacity
├── Goal
├── Location
├── Estimated Budget
├── Assigned Sales Executive
├── Assigned Engineer
└── Lead Status
```

---

## 14. Manufacturer Technology Section

Explain manufacturer capability without overwhelming the customer.

### Main Areas

- Solar Cells
- High-conversion Modules
- Energy Storage
- EPC Turnkey Solutions

### UI Style

Use modern Bento cards.

Each item should include:
- Short title
- One-line benefit
- Icon
- Hover interaction
- Link to detailed information

---

## 15. Manufacturer Trust Metrics

Possible trust-card structure:

- Production Bases
- Distributor Network
- Product Quality / Manufacturing Metrics
- Module Traceability

### Important

All manufacturer statistics must be verified before production publishing.

Do not hard-code unsupported claims.

---

## 16. Module Traceability

Build a product verification system.

### Customer Input

`Enter Product Serial Number`

### Result

```text
✓ Genuine Noor Solar Product

Product:
NOOR N-Type 550W

Serial:
NS550-XXXXXXXX

Manufacturing Batch:
XXXXXXXX

ERP Import Batch:
XXXXXXXX

Warehouse:
Dhaka

Warranty:
Active

Datasheet:
Download

Certification:
View
```

### ERP Link

Serial must be associated with:

- SKU
- Batch
- Import shipment
- Sales order
- Customer
- Warranty
- Installation
- Service history

---

## 17. Quality & Certification Library

Do not globally display certificates without SKU validation.

### Product-level Document Types

- Technical Datasheet
- IEC-related documents
- TÜV documents
- CE documents
- Quality certificates
- Warranty document
- Installation manual
- Test report

### ERP Data Model

```text
Product Document
│
├── Product SKU
├── Document Type
├── Certification Body
├── Applicable Market
├── Issue Date
├── Expiration Date
├── File
└── Verification Status
```

---

## 18. Global Network Section

Use manufacturer global network only as a supporting trust layer.

Noor should remain the main Bangladesh-facing brand.

### Message Structure

**Global manufacturing. Local execution.**

Manufacturer:
- R&D
- Production
- technology
- quality control

Noor Solar Energy:
- Bangladesh inventory
- consultation
- dealer support
- project sales
- ERP quotations
- local after-sales coordination

---

## 19. ERP Storefront Integration

### Product Data

```text
Product
│
├── SKU
├── Product Name
├── Series
├── Brand
├── Manufacturer
├── Wattage
├── Cell Technology
├── Module Type
├── Bifacial
├── Glass Type
├── Efficiency
├── Cell Count
├── Dimensions
├── Weight
├── Product Image
├── Gallery
├── 3D Model
├── Datasheet
├── Warranty
└── Certifications
```

### Inventory

```text
Inventory
│
├── Warehouse
├── Available
├── Reserved
├── Incoming
├── Incoming Shipment ETA
└── Stock Status
```

### Pricing

```text
Pricing
│
├── Retail
├── Dealer
├── Distributor
├── Project
├── Minimum Order Quantity
└── Special Quotation
```

---

## 20. Live Stock UI

Possible statuses:

- In Stock
- Low Stock
- Project Stock
- Incoming
- Pre-Order
- Out of Stock

### Example

```text
Available: 1,240 pcs
Reserved: 300 pcs
Incoming: 4,000 pcs
ETA: September
```

Avoid exposing internal business-sensitive inventory quantities if management does not want them public.

Instead show:

```text
In Stock
High Availability
Project Stock Available
Incoming Shipment
```

---

## 21. Product Details Page

### Hero

- Product name
- Real product images
- Interactive 3D model
- Wattage
- Technology
- Stock
- Price / Quote CTA

### Technical Tabs

- Overview
- Technical Specifications
- Electrical Characteristics
- Mechanical Data
- Performance
- Warranty
- Certifications
- Downloads
- Manufacturer

### CTA

- Buy Now
- Dealer Quote
- Project Quote
- WhatsApp / Sales Contact

---

## 22. Footer

### Column 1 — Brand

- Noor Solar Energy logo
- Short company description
- Quote CTA
- Browse Modules CTA

### Column 2 — Explore

- Solar Solutions
- Solar Modules
- Technology
- Manufacturing
- Global Network

### Column 3 — Customer

- Build My System
- Project Quotation
- Dealer / B2B
- Datasheets
- Warranty & Verification

### Column 4 — Bangladesh Support

ERP-managed:

- Office address
- Phone
- Email
- WhatsApp
- Business hours

### Bottom Links

- Privacy
- Terms
- Warranty
- Sitemap

---

## 23. Animation System

### Page-Level

- Scroll reveal
- Soft section entrance
- Image parallax
- Infinite ticker

### Card-Level

- Hover lift
- 3D tilt
- Mouse-follow glare
- Border highlight
- Subtle image zoom

### Product-Level

- Three.js rotation
- Module flip
- Dynamic lighting
- Scan effect
- Variant switching

### CTA-Level

- Magnetic hover
- arrow movement
- button lift

### Loading States

Use skeleton loaders for Livewire requests.

Avoid generic spinning loaders for every action.

---

## 24. Performance Rules

Three.js and animation must not damage storefront performance.

### Required

- Lazy-load 3D models
- Only initialize Three.js when viewer approaches viewport
- Use Draco compression for GLB where useful
- Compress textures
- Use WebP/AVIF product images
- Cap device pixel ratio
- Dispose unused Three.js resources
- Reduce effects on low-end/mobile devices
- Avoid multiple WebGL scenes on one page

### Target Experience

- Product listing should remain fast.
- 3D should enhance product detail, not block initial page render.

---

## 25. Mobile UX

Mobile should not simply shrink desktop.

### Mobile-Specific

- Horizontal swipe product rail
- Swipeable solution cards
- Floating `Get Quote` button
- Simple sticky header
- Large tap targets
- Reduced hover simulation
- Reduced WebGL effects
- Optional 3D fullscreen viewer

---

## 26. Recommended Component Architecture

```text
resources/
└── views/
    ├── livewire/
    │   ├── storefront/
    │   │   ├── home.blade.php
    │   │   ├── product-grid.blade.php
    │   │   ├── product-card.blade.php
    │   │   ├── product-details.blade.php
    │   │   ├── module-viewer.blade.php
    │   │   ├── stock-status.blade.php
    │   │   ├── quotation-builder.blade.php
    │   │   ├── solar-system-builder.blade.php
    │   │   ├── serial-verification.blade.php
    │   │   └── footer.blade.php
    │   └── ...
    └── components/
```

### JavaScript

```text
resources/js/
├── app.js
├── storefront/
│   ├── three-module-viewer.js
│   ├── tilt-cards.js
│   ├── magnetic-buttons.js
│   ├── product-carousel.js
│   └── livewire-bridge.js
```

---

## 27. Tailwind Architecture

Use Tailwind utility classes for:

- layout
- spacing
- typography
- color
- border
- radius
- shadows
- breakpoints
- hover state
- focus state
- responsive visibility

Only use custom CSS where dynamic pointer calculations or WebGL interaction require it.

### Example Card

```html
<article
    data-tilt
    class="
        rounded-[30px]
        border border-[#E5E3DA]
        bg-white
        p-5
        shadow-sm
        transition
        hover:-translate-y-1
        hover:shadow-xl
    "
>
    ...
</article>
```

---

## 28. Accessibility

Required:

- Semantic HTML
- Keyboard focus states
- Accessible navigation
- Buttons must not be divs
- Alt text for product imagery
- `prefers-reduced-motion`
- Sufficient text contrast
- Do not use animation as the only feedback mechanism
- Three.js controls must also have button-based alternatives

---

## 29. Final Experience Goal

The Noor Solar Energy storefront should feel like:

**Solar manufacturer credibility + premium technology brand + modern ERP commerce experience.**

The customer should immediately understand:

1. What Noor sells
2. Which solar solution fits them
3. Whether products are available
4. Which technology they are buying
5. How to request project pricing
6. Why the product is trustworthy
7. How to verify the product
8. How Noor supports them in Bangladesh

The storefront must not feel like a generic WooCommerce solar theme.

It should feel like a purpose-built digital sales platform for solar products and projects.

---

## 30. Current Prototype

Latest prototype direction:

**Tailwind CSS + Livewire-ready + Three.js interactive storefront**

Key implemented interaction concepts:

- Brand-aligned green/gold UI
- Tailwind responsive layout
- Sticky navigation
- Animated hero
- ERP live status
- Application-based discovery
- Product slider
- Manufacturer capability
- Quality and traceability
- Global network
- Full footer
- 3D hover tilt
- Dynamic glare
- Magnetic CTA
- Three.js solar module viewer
- Livewire-safe `wire:ignore` WebGL region
- ERP SKU event bridge

---

## 31. Production Note

Before launching the production storefront:

- Use Noor's final SVG logo.
- Replace demo product information with ERP data.
- Replace demo 3D module with real `.glb/.gltf` models.
- Verify all Ronma manufacturing statistics.
- Verify all certifications at exact SKU level.
- Verify partnership/exclusivity wording using signed commercial documents.
- Populate Bangladesh contact information from ERP/company settings.
- Connect quotation forms directly to ERP CRM / sales pipeline.
