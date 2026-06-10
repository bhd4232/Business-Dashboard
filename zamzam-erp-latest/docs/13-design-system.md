# Module 13: ZamZam ERP Design System

## Design Philosophy

```
3 Core Principles:

1. "বাজার থেকে অফিস" — হোলসেল ব্যবসার গতি, সরলতা, আর বিশ্বাস
2. "স্পিড ওভার বিউটি" — 500+ অর্ডার/দিন ম্যানেজ করতে হবে, UI তে ক্লিক কম
3. "ডাটা ফার্স্ট" — সংখ্যা, স্ট্যাটাস, অ্যাকশন সব এক নজরে
```

### Inspiration References

| ERP | কী নেবো | কী ছাড়বো |
|-----|---------|----------|
| **ERPNext** | ক্লিন লেআউট, ডকটাইপ ওয়ার্কফ্লো | পুরোনো UI, জ্যাঙ্গো টেমপ্লেট |
| **Odoo** | মডিউল সুইচার, কানেক্টেড ফ্লো | ওভার-অ্যানিমেশন, জটিল নেভিগেশন |
| **Intercom** | চ্যাট UI, কাস্টমার প্রোফাইল প্যানেল | SaaS-স্পেসিফিক ফিচার |
| **Shopify** | স্ট্যাটস কার্ড, অ্যাকশন বাটন | ই-কমার্স-স্পেসিফিক |
| **Notion** | ক্লিন টাইপোগ্রাফি, ব্লক এডিটর | ডকুমেন্ট-ফোকাসড UI |

---

## Color System

### Primary Brand

```
┌──────┐ ┌──────┐ ┌──────┐ ┌──────┐ ┌──────┐
│ 50   │ │ 100  │ │ 500  │ │ 600  │ │ 700  │
│#EEF2│ │#C7D2│ │#6366│ │#4F46│ │#3730│
│ FF   │ │ FE   │ │ F1   │ │ F1   │ │ A9   │
└──────┘ └──────┘ └──────┘ └──────┘ └──────┘
Indigo-50  Indigo-100  Indigo-500  Indigo-600  Indigo-700
(bg)       (hover bg)  (buttons)    (active)     (dark)
```

### Status Colors

| Status | Color | Hex | Usage |
|--------|-------|-----|-------|
| Success | Emerald | `#10B981` | Delivered, Completed, Paid, In Stock |
| Warning | Amber | `#F59E0B` | Pending, Confirmed, Low Stock, 50s Timer (mid) |
| Danger | Red | `#EF4444` | Cancelled, Overdue, Failed, 50s Timer (low), Urgent |
| Info | Blue | `#3B82F6` | In Transit, Partial, Syncing, System |
| Neutral | Slate | `#64748B` | Draft, Idle, Muted |

### Domain Colors (Module-specific accents)

| Module | Color | Hex |
|--------|-------|-----|
| Purchase/Procurement | Purple | `#9333EA` (purple-600) |
| Shipping/Logistics | Cyan | `#0891B2` (cyan-600) |
| Sales | Orange | `#EA580C` (orange-600) |
| Finance | Emerald-600 | `#059669` |
| Chat/AI | Violet | `#7C3AED` (violet-600) |
| Inventory | Amber | `#D97706` (amber-600) |
| WooCommerce | Purple | `#9333EA` (purple-600) |
| Reseller | Teal | `#0D9488` |

### Surfaces

| Element | Color | Tailwind |
|---------|-------|----------|
| Background | `#F8FAFC` | Slate-50 |
| Card/Surface | `#FFFFFF` | White |
| Card Hover | `#F1F5F9` | Slate-100 |
| Border | `#E2E8F0` | Slate-200 |
| Border Focus | `#6366F1` | Primary |
| Text Primary | `#0F172A` | Slate-900 |
| Text Secondary | `#475569` | Slate-600 |
| Text Muted | `#94A3B8` | Slate-400 |

### Bangladesh Touch 🇧🇩

| Element | Color | Hex |
|---------|-------|-----|
| Brand Accent | BD Flag Green | `#006A4E` |
| Brand Red | BD Flag Red | `#F42A41` |
| Logo background | White with green accent | — |

### Dark Mode (Optional)

| Element | Light | Dark |
|---------|-------|------|
| Background | `#F8FAFC` | `#0F172A` |
| Surface | `#FFFFFF` | `#1E293B` |
| Surface Hover | `#F1F5F9` | `#334155` |
| Border | `#E2E8F0` | `#475569` |
| Text Primary | `#0F172A` | `#F1F5F9` |
| Text Secondary | `#475569` | `#94A3B8` |
| Text Muted | `#94A3B8` | `#64748B` |

Toggle: Settings > Appearance > Light ☀️ | Dark 🌙 | System 💻

---

## Typography

### Font Stack

| Purpose | Font | Source |
|---------|------|--------|
| Primary (EN/numbers) | Inter | Google Fonts |
| Bengali | Noto Sans Bengali | Google Fonts |
| Chinese | Noto Sans SC | Google Fonts |
| Monospace (SKU, codes) | JetBrains Mono | Google Fonts |

### Scale

| Size | px | Usage |
|------|----|-------|
| `text-xs` | 12 | Badges, meta, timestamps |
| `text-sm` | 14 | Table cells, subtitles |
| `text-base` | 16 | Body text, inputs |
| `text-lg` | 18 | Card titles |
| `text-xl` | 20 | Page subtitle |
| `text-2xl` | 24 | Page title |
| `text-3xl` | 30 | Stats numbers |
| `text-4xl` | 36 | Dashboard hero numbers |

### Weights

| Weight | Value | Usage |
|--------|-------|-------|
| Normal | 400 | Body text |
| Medium | 500 | Table headers, buttons |
| Semibold | 600 | Card titles, navigation |
| Bold | 700 | Page titles, stats |
| Extrabold | 800 | Hero numbers |

---

## Layout System

### App Shell

```
┌─────────────────────────────────────────────────────────────────────────┐
│ ┌──────┐ ┌──────────────────────────────────────────────────────────┐ │
│ │      │ │  Top Bar (h-14 / 56px)                                   │ │
│ │      │ │  ┌────────────────────┬──────────┬──────────────────┐    │ │
│ │      │ │  │ 🔍 Search...       │🔔 3 📨 5 │👤 Admin ▾       │    │ │
│ │      │ │  └────────────────────┴──────────┴──────────────────┘    │ │
│ │      │ ├──────────────────────────────────────────────────────────┤ │
│ │  S   │ │                                                          │ │
│ │  i   │ │  Page Content (scrollable)                               │ │
│ │  d   │ │                                                          │ │
│ │  e   │ │  padding: p-6 (24px)                                     │ │
│ │  b   │ │  max-width: max-w-[1600px] (centered on ultra-wide)     │ │
│ │  a   │ │                                                          │ │
│ │  r   │ │                                                          │ │
│ │      │ │                                                          │ │
│ │  w-60│ │                                                          │ │
│ │      │ │                                                          │ │
│ │      │ │                                                          │ │
│ │      │ │                                                          │ │
│ └──────┘ └──────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────────────┘

Sidebar:     w-60 (240px) expanded / w-16 (64px) collapsed
Top Bar:     h-14 (56px)
Content:     p-6 (24px)
Max Content: max-w-[1600px]
```

### Sidebar

```
Expanded (w-60):
┌────────────────────┐
│  🏪 ZamZam ERP     │
│  ─────────────────  │
│  📊 Dashboard       │
│  🛒 Orders          │
│    ├ Wholesale      │
│    ├ Retail         │
│    ├ Picking Lists  │
│    └ Fake Detection │
│  🚢 Shipping        │
│    ├ International  │
│    └ Domestic       │
│  📦 Inventory       │
│  👥 Customers       │
│  💰 Finance         │
│  🔗 WooCommerce     │
│  💬 Conversations   │
│  🤖 Chatbot Builder │
│  📈 Reports         │
│  ─────────────────  │
│  ⚙️ Settings        │
│  🔓 Logout          │
│                     │
│  ◀ Collapse         │
└────────────────────┘

Collapsed (w-16):
┌──────┐
│  🏪  │
│  ──  │
│  📊  │
│  🛒  │
│  🚢  │
│  📦  │
│  👥  │
│  💰  │
│  🔗  │
│  💬  │
│  🤖  │
│  📈  │
│  ──  │
│  ⚙️  │
│  🔓  │
│      │
│  ▶   │
└──────┘

Active item:  bg-primary/10 + text-primary + left-border-2
Hover:        bg-slate-100 (light) / bg-slate-700 (dark)
Tooltip:      on collapsed hover shows label
```

### Responsive Breakpoints

| Breakpoint | Width | Layout |
|-----------|-------|--------|
| `sm` | 640px | 1 column, drawer fullscreen, table → card list |
| `md` | 768px | 2 columns, sidebar collapsed |
| `lg` | 1024px | sidebar expanded, 3-col stats grid |
| `xl` | 1280px | full layout, wide tables |
| `2xl` | 1536px | max-width container, extra padding |

---

## Component Library

### 1. Stats Card (Dashboard)

```
┌─────────────────────┐  ┌─────────────────────┐  ┌─────────────────────┐
│ 📦 Total Orders     │  │ 💰 Revenue Today    │  │ 📊 Stock Alerts     │
│                     │  │                     │  │                     │
│     1,247           │  │     ৳24.5L          │  │     23              │
│  ▲ 12% vs yesterday │  │  ▲ 8% vs yesterday  │  │  ▼ 5 vs yesterday   │
│  [━━━━━━━━━━░░░░]  │  │  [━━━━━━━━░░░░░░]  │  │  [━━░░░░░░░░░░░░]  │
│  78% of monthly goal│  │  65% of monthly goal│  │  23 products low    │
└─────────────────────┘  └─────────────────────┘  └─────────────────────┘

Variants:
- Default: white bg, shadow-sm
- Success: green left-border-4
- Warning: amber left-border-4
- Danger: red left-border-4 + pulse icon
- Compact: small (multi-column grid)
```

### 2. Data Table (Master List)

```
┌──────────────────────────────────────────────────────────────────────┐
│  📦 Purchase Orders                    [+ New PO] [📥 Import] [📤 Export]│
├──────────────────────────────────────────────────────────────────────┤
│  🔍 Search PO#, supplier...  │ Status: [All ▼] │ Date: [This Month ▼]│
├──────┬──────────┬──────────┬──────────┬──────────┬──────────┬──────┤
│  ☑   │ PO#      │ Supplier │ Total    │ Status   │ Date     │ Act. │
├──────┼──────────┼──────────┼──────────┼──────────┼──────────┼──────┤
│  ☐   │ PO-2026- │ Shenzhen │ ৳12.5L   │ 🟡 Confir│ 09 May   │  ⋮   │
│      │ 0001     │ Trading  │          │ med      │          │      │
│  ☐   │ PO-2026- │ Guangzho │ ৳8.2L    │ 🔵 Shipp│ 07 May   │  ⋮   │
│      │ 0002     │ Mfg      │          │ ed       │          │      │
│  ☐   │ PO-2026- │ Yiwu     │ ৳3.1L    │ 🟢 Recei│ 03 May   │  ⋮   │
│      │ 0003     │ General  │          │ ved      │          │      │
├──────┴──────────┴──────────┴──────────┴──────────┴──────────┴──────┤
│  3 selected  [Delete] [Export Selected]                              │
│  ◀ 1 2 3 ... 50 ▶   Showing 1-25 of 1,245                        │
└──────────────────────────────────────────────────────────────────────┘

Features:
- Sticky header
- Row checkbox (bulk actions)
- Inline status badge (colored dot + text)
- Sortable columns (click header)
- Resizable columns (drag)
- Row click → detail page
- ⋮ menu → Edit, Duplicate, Delete, Print
- Column visibility toggle
- Density toggle (compact/comfortable/spacious)
```

### 3. Status Badges

```
Style: rounded-full px-2.5 py-0.5 text-xs font-medium

Purchase Order:
  🟣 Draft          → bg-purple-100 text-purple-700
  🟡 Confirmed      → bg-amber-100 text-amber-700
  🔵 Partially Ship → bg-blue-100 text-blue-700
  🔵 Shipped        → bg-cyan-100 text-cyan-700
  🟢 Received       → bg-emerald-100 text-emerald-700
  ✅ Completed      → bg-green-100 text-green-700
  🔴 Cancelled      → bg-red-100 text-red-700

Sales Order:
  🟡 Pending        → bg-amber-100 text-amber-700
  🔵 Confirmed      → bg-blue-100 text-blue-700
  🟢 Packed         → bg-emerald-100 text-emerald-700
  ✅ Delivered      → bg-green-100 text-green-700
  🔴 Cancelled      → bg-red-100 text-red-700

Payment:
  🟡 Pending        → bg-amber-100 text-amber-700
  🟢 Paid           → bg-emerald-100 text-emerald-700
  🔴 Overdue        → bg-red-100 text-red-700 + pulse dot
  🔵 Partial        → bg-blue-100 text-blue-700

Courier:
  🟡 Pending        → bg-amber-100 text-amber-700
  🔵 In Transit     → bg-blue-100 text-blue-700
  🟢 Delivered      → bg-emerald-100 text-emerald-700
  🔴 Returned       → bg-red-100 text-red-700

Conversation:
  🟢 Active         → bg-emerald-100 text-emerald-700
  🟡 Idle           → bg-amber-100 text-amber-700
  🔵 AI Active      → bg-purple-100 text-purple-700
  ⚫ Closed         → bg-slate-100 text-slate-700
```

### 4. Form Pattern

```
┌──────────────────────────────────────────────────────────────────┐
│  ← Back to Purchase Orders                                       │
│                                                                  │
│  Create Purchase Order                                           │
│  ─────────────────────────────────────────────────────────────── │
│                                                                  │
│  ┌── Section: Basic Info ──────────────────────────────────────┐│
│  │                                                              ││
│  │  Supplier *         [Select supplier ▼]  [+ New Supplier]   ││
│  │  Order Date *       [09/05/2026 📅]                          ││
│  │  Expected Delivery  [__/__/____ 📅]                         ││
│  │  Currency *         [CNY ▼]  Rate: [18.50]                  ││
│  │  Notes              [................................]         ││
│  │                                                              ││
│  └──────────────────────────────────────────────────────────────┘│
│                                                                  │
│  ┌── Section: Items ───────────────────────────────────────────┐│
│  │                                                              ││
│  │  [+ Add Item]  [📥 Import from Excel]                       ││
│  │                                                              ││
│  │  ┌──────┬────────┬────────┬──────┬────────┬──────┬────┐    ││
│  │  │Prod. │Variant │Price   │Qty   │Subtotal│Act. │    ││
│  │  ├──────┼────────┼────────┼──────┼────────┼──────┼────┤    ││
│  │  │Ceram.│Blue    │¥45.00  │500   │¥22,500│ ✕   │    ││
│  │  │ Mug  │        │        │      │        │     │    ││
│  │  └──────┴────────┴────────┴──────┴────────┴──────┴────┘    ││
│  │                                                              ││
│  └──────────────────────────────────────────────────────────────┘│
│                                                                  │
│  ┌── Section: Summary ─────────────────────────────────────────┐│
│  │                                                              ││
│  │  Subtotal (CNY):    ¥34,500.00                               ││
│  │  Exchange Rate:     1 CNY = ৳18.50                           ││
│  │  Total (BDT):       ৳6,38,250.00                             ││
│  │                                                              ││
│  └──────────────────────────────────────────────────────────────┘│
│                                                                  │
│  [Cancel]  [Save as Draft]  [Save & Confirm]                    │
└──────────────────────────────────────────────────────────────────┘

Form Rules:
- Required fields: red asterisk * + validation on blur
- Error messages: below field, red text + icon
- Success: green checkmark on valid field
- Sections: collapsible cards with gray header
- Multi-column: 2/3 columns on desktop, 1 on mobile
- Sticky bottom action bar on long forms
```

### 5. Detail Page (Record View)

```
┌──────────────────────────────────────────────────────────────────────────┐
│  ← Back to PO List                                                       │
│                                                                           │
│  PO-2026-0001                                          [Edit] [Print]   │
│  ─────────────────────────────────────────────────────────────────────── │
│                                                                           │
│  ┌── Header Cards ──────────────────────────────────────────────────────┐│
│  │                                                                     ││
│  │  🏭 Shenzhen Trading    │  🟡 Confirmed  │  📅 09 May 2026         ││
│  │  Supplier since 2024    │              │  Expected: 25 May         ││
│  │                          │              │                           ││
│  └─────────────────────────────────────────────────────────────────────┘│
│                                                                           │
│  ┌── Tabs ─────────────────────────────────────────────────────────────┐│
│  │  [Items]  [Costs]  [Shipping]  [Documents]  [History]  [Notes]     ││
│  └─────────────────────────────────────────────────────────────────────┘│
│                                                                           │
│  ┌── Tab Content ──────────────────────────────────────────────────────┐│
│  │  (content based on selected tab)                                     ││
│  └─────────────────────────────────────────────────────────────────────┘│
│                                                                           │
│  ┌── Timeline (always visible) ────────────────────────────────────────┐│
│  │  ● 09 May 10:30 — Created by Admin                                  ││
│  │  ● 09 May 11:15 — Confirmed by Manager                              ││
│  │  ● 12 May 14:00 — Shipment SH-001 created                           ││
│  │  ○ 25 May — Expected delivery (upcoming)                            ││
│  └─────────────────────────────────────────────────────────────────────┘│
└──────────────────────────────────────────────────────────────────────────┘
```

### 6. Multi-Currency Display

```
┌─────────────────────────────────────┐
│  💱 Multi-Currency Display           │
│                                      │
│  Purchase Price:  ¥45.00 CNY        │  ← Primary (transaction currency)
│  ≈ ৳832.50 BDT @ 18.50            │  ← Secondary (conversion)
│  ≈ $4.85 USD                       │  ← Optional (international)
│                                      │
│  Primary: text-lg font-bold         │
│  Converted: text-sm text-slate-500  │
│  Rate: text-xs text-slate-400       │
└─────────────────────────────────────┘
```

### 7. Modal / Drawer

```
Modal (center):
┌──────────────────────────────────────────┐
│  ⚠️ Confirm Delete                    ✕  │
│  ─────────────────────────────────────── │
│                                          │
│  Are you sure you want to delete         │
│  PO-2026-0001? This action cannot        │
│  be undone.                              │
│                                          │
│  Type "DELETE" to confirm:               │
│  [____________________]                   │
│                                          │
│          [Cancel]  [🗑️ Delete]           │
└──────────────────────────────────────────┘

Drawer (right side — quick view):
                                      ┌──────────────────────────────┐
                                      │  PO-2026-0001            ✕  │
                                      │  ────────────────────────── │
                                      │  Supplier: Shenzhen Trading │
                                      │  Status: 🟡 Confirmed       │
                                      │  Total: ৳6,38,250          │
                                      │                             │
                                      │  Items:                     │
                                      │  1. Ceramic Mug x500       │
                                      │  2. Glass Cup x1000        │
                                      │                             │
                                      │  [📄 Full Detail] [✏️ Edit] │
                                      └──────────────────────────────┘

Rules:
- Delete confirmation: type "DELETE" required
- Danger actions: red button
- Drawer width: w-[480px]
- Click outside → close (unsaved changes warning)
```

### 8. Filter Bar

```
┌──────────────────────────────────────────────────────────────────────────┐
│  🔍 Search PO#, supplier...  │ Status: [All ▼] │ Date: [This Month ▼]  │
│  │ Supplier: [All ▼] │ Type: [All ▼] │ [🔄 Reset] [📤 Export]        │
└──────────────────────────────────────────────────────────────────────────┘

Active filters as chips:
  [✕ Status: Confirmed] [✕ Date: May 2026] [✕ Supplier: Shenzhen]  Clear All
```

### 9. Notifications

```
Toast (auto-dismiss 5s):
  ┌─────────────────────────────────────────┐
  │  ✅ Purchase Order PO-2026-0001 created  │
  │                                    ↗️ View │
  └─────────────────────────────────────────┘

  ┌─────────────────────────────────────────┐
  │  ⚠️ Credit limit exceeded for Rahim Store│
  │                                    ↗️ View │
  └─────────────────────────────────────────┘

  ┌─────────────────────────────────────────┐
  │  ❌ Failed to sync WooCommerce order     │
  │                                    ↗️ View │
  └─────────────────────────────────────────┘

Bell Dropdown:
  ┌────────────────────────────────────────┐
  │  🔔 Notifications            Mark All  │
  │  ──────────────────────────────────── │
  │  🟢 5 min ago                         │
  │  New WhatsApp message from Rahim Store │
  │                                        │
  │  🟡 15 min ago                         │
  │  Shipment SH-001 arrived at Chittagong │
  │                                        │
  │  🔴 1 hour ago                         │
  │  3 orders flagged as suspicious        │
  │                                        │
  │  [View All Notifications]              │
  └────────────────────────────────────────┘
```

### 10. Empty State

```
┌──────────────────────────────────────────────────────────────┐
│                                                              │
│                    ┌─────────┐                               │
│                    │  📦     │                               │
│                    └─────────┘                               │
│                                                              │
│           No purchase orders yet                              │
│    Create your first PO to start procuring from China         │
│                                                              │
│              [+ Create Purchase Order]                        │
│                                                              │
└──────────────────────────────────────────────────────────────┘

Module-specific icons and messages:
- Auth: 🔐 "Sign in to get started"
- PO: 📦 "No purchase orders yet"
- Shipping: 🚢 "No shipments in transit"
- Sales: 🛒 "No orders today"
- Inventory: 📊 "Stock is healthy — no alerts"
- Chat: 💬 "No active conversations"
- Workflow: 🤖 "Create your first chatbot workflow"
```

---

## Animation / Transitions

| Element | Animation | Duration |
|---------|-----------|----------|
| Page transition | fade | 150ms |
| Sidebar collapse | width transition | 200ms ease |
| Modal open | fade + scale 95% → 100% | 150ms |
| Drawer slide | translate-x | 200ms ease |
| Toast appear | slide-down + fade | 200ms |
| Row hover | bg transition | 100ms |
| Button click | scale 98% | 50ms |
| Loading | skeleton pulse | 1.5s infinite |
| Status change | color transition | 300ms |

**NO:** bounce, spin (except loading), shake (except validation), particles/confetti

---

## Keyboard Shortcuts

| Shortcut | Action |
|----------|--------|
| `Ctrl+K` | Command palette (search anything) |
| `Ctrl+N` | New record (context-aware) |
| `Ctrl+S` | Save current form |
| `Ctrl+F` | Filter table |
| `Ctrl+E` | Export current view |
| `Ctrl+/` | Keyboard shortcuts help |
| `Ctrl+1-9` | Switch sidebar modules |
| `Esc` | Close modal/drawer |
| `?` | Show help tooltip |

### Command Palette (Ctrl+K)

```
┌──────────────────────────────────────────┐
│  🔍 Search commands, pages, records...   │
│  ─────────────────────────────────────── │
│  📦 New Purchase Order                   │
│  🛒 New Wholesale Order                  │
│  👤 Search Customer                       │
│  🔍 Search Product                        │
│  💬 Open Conversations                    │
│  📊 Go to Dashboard                       │
│  ─────────────────────────────────────── │
│  Recent:                                  │
│  📄 PO-2026-0001                         │
│  📄 SO-2026-0045                         │
│  👤 Rahim Store                           │
└──────────────────────────────────────────┘
```

---

## Mobile Responsive

### Table → Card List (Mobile)

```
Desktop:
  ┌──────┬──────┬──────┬──────┬──────┐
  │ PO#  │Suppl.│Total │Status│Act.  │
  └──────┴──────┴──────┴──────┴──────┘

Mobile:
  ┌─────────────────────────────────┐
  │ PO-2026-0001          🟡 Confirmed│
  │ Shenzhen Trading                 │
  │ Total: ৳6,38,250  │  09 May     │
  │                    [View] [Edit] │
  └─────────────────────────────────┘
```

---

## Print / PDF Style

### Invoice Design (Zamzam International Reference)

Based on the Zamzam International invoice — minimal, smart, professional.

```
┌──────────────────────────────────────────────────────────────────────────┐
│                                                                          │
│  ┌─────────────────┐                              ZAMZAM INTERNATIONAL │
│  │                 │                              ───────────────────── │
│  │   🏪 LOGO       │                              Hotline: +880 1XXX   │
│  │   (green accent)│                                                    │
│  └─────────────────┘                              ┌─────────────────┐   │
│                                                    │  |||||||||||||  │   │
│                                                    │  |||||||||||||  │   │
│                                                    │  |||||||||||||  │   │
│                                                    └─────────────────┘   │
│                                                    Invoice: SO-0966      │
│                                                    Courier: Steadfast    │
│                                                    Date: 06 May 2026     │
│                                                                          │
├──────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│  BILL TO                                                                 │
│  ────────                                                                │
│  Rahim Store                                                             │
│  📱 +880 1XXX-XXXXXX                                                    │
│  📍 123 Main Road, Chittagong, Bangladesh                               │
│                                                                          │
├──────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│  SL │ Image │ Product Name          │ Weight │ Unit Price │ Qty │ Total  │
│ ────┼───────┼───────────────────────┼────────┼────────────┼─────┼────────│
│  01 │ [🖼️] │ Ceramic Mug - Blue    │ 0.3 kg │   ৳45.00   │ 100 │৳4,500 │
│  02 │ [🖼️] │ Glass Cup - Clear     │ 0.2 kg │   ৳25.00   │ 200 │৳5,000 │
│  03 │ [🖼️] │ Dinner Plate - White  │ 0.4 kg │   ৳60.00   │  50 │৳3,000 │
│                                                                          │
├──────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│                                          Subtotal:          ৳12,500.00 │
│                                          Delivery:             ৳120.00 │
│                                          Discount:            -৳500.00 │
│                                          ─────────────────────────────  │
│                                          Grand Total:       ৳12,120.00 │
│                                          Paid:               ৳5,000.00 │
│                                          ┌─────────────────────────────┐│
│                                          │  Due Amount:   ৳7,120.00   ││
│                                          └─────────────────────────────┘│
│                                           (কালো bg + সাদা টেক্সট)      │
│                                                                          │
├──────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│  📘 facebook.com/ZamzamInt    📧 info@zamzam.com                        │
│  🌐 www.zamzam.com            🏢 House #12, Road #5, Chittagong         │
│                                                                          │
│  ┌──────────────────────────────────────────────────────────────────────┐│
│  │  📞 Hotline: +880 1XXX  │  📘 FB: /ZamzamInt  │  💬 WA: +880 1XXX ││
│  └──────────────────────────────────────────────────────────────────────┘│
│                                                                          │
│  - - - - - - - - - - ✂ - - - - - - - - - - ✂ - - - - - - - - - - - -  │
│                                                                          │
│  RECEIPT                                                                 │
│  Zamzam Int. │ SO-0966 │ Rahim Store │ Due: ৳7,120 │ 06/05/2026        │
│                                                                          │
└──────────────────────────────────────────────────────────────────────────┘
```

### Invoice Specifications

```
Page Size:     A4 (210mm × 297mm)
Margin:        15mm all sides
Font:          Inter (numbers/EN) + Noto Sans Bengali (BN)

COLOR PALETTE:
  Primary:     #1A1A2E (Dark Navy — header, brand name, table header)
  Accent:      #006A4E (BD Green — logo accent)
  Text:        #333333 (Main body text)
  Muted:       #666666 (Subtitle)
  Border:      #E0E0E0 (Table border)
  Due BG:      #1A1A2E (Dark navy bg — Due Amount highlight)
  Due Text:    #FFFFFF (White text — Due Amount)
  Footer Bar:  #F5F5F5 (Light gray bg — contact bar)
  Cutter Line: #CCCCCC (Dotted line)
```

### Invoice Section Breakdown

#### Header Section
```
- Left: Company logo (with BD green accent)
- Right: "ZAMZAM INTERNATIONAL" in large bold font (dark navy)
- Below logo: Hotline number for easy contact
```

#### Tracking & ID Section
```
- Right side: Barcode (Code-128 format, scannable)
- Below barcode: Invoice number (SO-XXXX), Delivery partner name, Date
- Barcode package: milon/barcode or picqer/php-barcode
```

#### Bill To Section
```
- "BILL TO" label in uppercase, bold
- Customer name (bold, larger)
- Mobile number with icon
- Full address with icon
```

#### Product Table
```
Column Widths (A4):
  SL:         8%  (10mm)  — Serial number
  Image:      10% (13mm)  — 40×40px thumbnail (base64 embedded)
  Name:       32% (42mm)  — Product name (bold) + SKU (small, muted)
  Weight:     12% (16mm)  — Weight per unit
  Unit Price: 15% (20mm)  — ৳XX.XX
  Qty:        10% (13mm)  — Quantity
  Total:      13% (17mm)  — ৳XX.XX (bold)

Table Style:
  Header:   #1A1A2E bg + #FFFFFF text (dark navy header row)
  Rows:     White bg, light border-bottom
  No zebra striping — clean minimal
  Last row: Bold border-bottom
```

#### Payment Summary
```
Right-aligned:
  Subtotal          ৳XX,XXX.XX
  Delivery Charge      ৳XXX.XX
  Discount            -৳XXX.XX
  ─────────────────────────────
  Grand Total       ৳XX,XXX.XX
  Paid               ৳X,XXX.XX
  ┌─────────────────────────────┐
  │  Due Amount     ৳X,XXX.XX  │  ← #1A1A2E bg + #FFFFFF text
  └─────────────────────────────┘     rounded corners
                                       font-bold text-lg

Due Amount is the most eye-catching element:
  - Dark navy background (#1A1A2E)
  - White text (#FFFFFF)
  - Rounded corners
  - Bold font
  - Larger size than other amounts
  This is what customer/delivery man sees FIRST
```

#### Footer Section
```
Left side:
  📘 Facebook URL       📧 Email
  🌐 Website            🏢 Office address

Contact Bar (full-width, gray bg #F5F5F5):
  📞 Hotline: +880 1XXX  │  📘 FB: /ZamzamInt  │  💬 WA: +880 1XXX
```

#### Cutter Line + Receipt Stub
```
Dotted line with scissors icon:
  - - - - - - - - - - ✂ - - - - - - - - - - ✂ - - - - - - - -

Receipt (small tear-off stub):
  ┌──────────────────────────────────────────────────────────────────┐
  │  Zamzam Int. │ SO-0966 │ Rahim Store │ Due: ৳7,120 │ 06/05/26 │
  └──────────────────────────────────────────────────────────────────┘

Purpose: Customer signs and keeps / Delivery man takes back
```

### Invoice Variants (ERP Configurable)

```
┌─────────────────────────────────────────────────────────────┐
│  Invoice Settings                                           │
│                                                              │
│  Template: [Minimal (Zamzam) ▼]                             │
│  ☑ Show product images                                      │
│  ☑ Show weight column                                       │
│  ☑ Show barcode                                             │
│  ☑ Show cutter line + receipt stub                          │
│  ☑ Highlight due amount (dark bg + white text)              │
│  ☑ Show contact bar                                        │
│  ☐ Show company stamp area                                  │
│  ☐ Show payment terms                                       │
│  ☐ Show QR code (bKash/Nagad payment)                       │
│                                                              │
│  Brand Color:  [#1A1A2E ■]                                  │
│  Accent Color: [#006A4E ■]                                  │
│                                                              │
│  Paper Size: [A4 ▼]                                         │
│  Logo:       [Upload] zamzam-logo.png                       │
│                                                              │
│  [Preview]  [Save]                                          │
└─────────────────────────────────────────────────────────────┘

Templates:
1. Minimal (Default) — Reference Zamzam International invoice
2. With QR — bKash/Nagad payment QR code added
3. With Stamp — Company stamp + signature area
4. Wholesale — Price tier visible, credit terms, multiple currency
5. Retail — Most compact, no images
6. Delivery Slip — Only stub, product list, address
7. Proforma Invoice — Before order confirmation, "Estimate" label
8. Credit Note — Return/refund, negative amounts
```

### Invoice PDF Generation (Laravel)

```
Options:

1. DomPDF (Recommended for start)
   - resources/views/invoices/minimal.blade.php
   - Custom CSS + A4 layout
   - Barcode: milon/barcode (Code-128)
   - Product images: base64 embed
   - Package: barryvdh/laravel-dompdf

2. WKHTMLTOPDF (Optional upgrade)
   - More exact rendering (Chrome-like)
   - Requires wkhtmltopdf binary on server
   - Package: niklas/laravel-pdfmerger or custom wrapper

3. API-based (PDFShift/DocRaptor)
   - Cloud service, best quality
   - Extra cost per page
   - For high-volume or pixel-perfect needs

Implementation:
   InvoiceService::generate(SalesOrder $order, string $template = 'minimal'): string
   InvoiceService::preview(SalesOrder $order, string $template): View
   InvoiceService::stream(SalesOrder $order): StreamedResponse
   InvoiceService::download(SalesOrder $order): BinaryFileResponse
```

---

## 3D Icon System (ThreeDIcon.vue)

### Overview

ZamZam ERP uses **Icons8 Fluency** 3D-style icons via CDN for an interactive, modern UI. This replaces flat SVG icons in the Sidebar navigation, StatCards, Dashboard sections, and empty states.

**Component:** `resources/js/Components/UI/ThreeDIcon.vue`  
**CDN Base:** `https://img.icons8.com/fluency/96/{filename}.png`  
**Source:** Icons8 Fluency collection (consistent 3D style, 96px HiDPI)

### Usage

```vue
<!-- Basic usage -->
<ThreeDIcon name="dashboard" />

<!-- With size -->
<ThreeDIcon name="purchase_orders" size="lg" />

<!-- In StatCard (via icon3d prop) -->
<StatCard label="Revenue" value="৳24.5L" icon3d="trending_up" color="emerald" />

<!-- In Sidebar (via icon3d prop) -->
<SidebarItem label="Dashboard" icon="LayoutDashboard" icon3d="dashboard" route-name="dashboard" />
<SidebarGroup label="Procurement" icon="ShoppingBag" icon3d="purchase_orders" ... />
```

### Props

| Prop | Type | Default | Values |
|------|------|---------|--------|
| `name` | String | required | See icon map below |
| `size` | String | `'md'` | `'xs'` `'sm'` `'md'` `'lg'` `'xl'` `'2xl'` |
| `animate` | Boolean | `true` | hover scale-110 on `true` |

### Size Classes

| Size | CSS | Pixel |
|------|-----|-------|
| `xs` | `w-5 h-5` | 20px |
| `sm` | `w-6 h-6` | 24px |
| `md` | `w-8 h-8` | 32px |
| `lg` | `w-10 h-10` | 40px |
| `xl` | `w-12 h-12` | 48px |
| `2xl` | `w-16 h-16` | 64px |

### Icon Name Map

| Key | File | Used In |
|-----|------|---------|
| `dashboard` | dashboard-layout.png | Sidebar, Dashboard header |
| `suppliers` | factory.png | Sidebar Procurement, StatCard |
| `products` | open-box.png | Sidebar, StatCard, Quick Access |
| `purchase_orders` | purchase-order.png | Sidebar, StatCard, Quick Access, empty states |
| `categories` | category.png | Sidebar Procurement |
| `inventory` | warehouse.png | Sidebar Inventory group |
| `warehouses` | commercial-development-management.png | StatCard |
| `stock` | box.png | Quick Access |
| `transfers` | transfer-between-accounts.png | Sidebar Inventory |
| `adjustments` | scales.png | Sidebar Inventory |
| `barcodes` | barcode.png | Sidebar Inventory |
| `shipping` | shipped.png | Sidebar Shipping group |
| `shipment` | cargo-ship.png | Shipping pages |
| `domestic` | delivery.png | Domestic logistics |
| `parcels` | parcel.png | Domestic logistics |
| `finance` | money-bag.png | Sidebar Finance group |
| `payments` | banknote.png | Finance pages |
| `accounts` | accounting.png | Finance pages |
| `chat` | chat.png | Sidebar Chat & AI |
| `workflows` | workflow.png | Sidebar Chat & AI |
| `reports` | bar-chart.png | Sidebar Reports |
| `settings` | gear.png | Sidebar Settings |
| `customers` | conference-call.png | Sales pages |
| `orders` | purchase-order.png | Sales pages |
| `invoices` | invoice.png | Sales pages |
| `sales` | sales-performance.png | Sidebar Sales group |
| `trending_up` | bullish.png | Dashboard Revenue StatCard |
| `low_stock` | low-battery.png | Dashboard Low Stock StatCard |
| `alert` | box-important.png | Stock Alerts section |
| `check` | checked.png | Healthy stock empty state |
| `activity` | performance-macbook.png | Pending Actions section |
| `layers` | stack.png | Active Modules section |
| `zap` | flash-on.png | Logo area, Quick Access header |
| `logout` | exit.png | User footer |

### Where 3D Icons Are Used

```
Sidebar:
  AppSidebar.vue     → Logo area (zap icon)
  SidebarItem.vue    → icon3d prop → ThreeDIcon size="sm"
  SidebarGroup.vue   → icon3d prop → ThreeDIcon size="sm"

Dashboard:
  Dashboard/Index.vue:
    - Page header    → ThreeDIcon size="xl" (dashboard)
    - StatCards      → icon3d prop (6 cards)
    - Revenue header → ThreeDIcon size="md" (trending_up)
    - Quick Access   → ThreeDIcon size="md" (per link)
    - Section headers → ThreeDIcon size="sm"
    - Empty states   → ThreeDIcon size="xl"

Other Pages (future):
  Empty states, page headers, section titles
```

### Implementation Rule

> **Every new page/component added to zamzam-erp MUST use ThreeDIcon for:**
> - Page header icon
> - Empty state icon
> - StatCard icons (via `icon3d` prop)
> - Sidebar navigation (via `icon3d` prop on SidebarItem/SidebarGroup)

---

## Tailwind CSS Configuration

```javascript
// tailwind.config.js
module.exports = {
  theme: {
    extend: {
      colors: {
        brand: {
          50: '#EEF2FF',
          100: '#C7D2FE',
          500: '#6366F1',
          600: '#4F46E5', // Correct Indigo-600 hex (was #4F46F1)
          700: '#4338CA',
        },
        bd: {
          green: '#006A4E',
          red: '#F42A41',
        },
        invoice: {
          primary: '#1A1A2E',
          accent: '#006A4E',
        },
      },
      fontFamily: {
        sans: ['Inter', 'Noto Sans Bengali', 'Noto Sans SC', 'sans-serif'],
        mono: ['JetBrains Mono', 'monospace'],
      },
      maxWidth: {
        content: '1600px',
      },
    },
  },
}
```
