# ZamZam ERP - Project Overview

## Business Model

**China to Bangladesh Wholesale Business**

```
[China Suppliers] → [Purchase Order (CNY)] → [Shipping/Customs] → [Godown (BD)] → [Wholesale Sales (BDT)] → [Collection]
                                                                     ↓
                                                              [Retail Sales (BDT)]
```

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | Laravel 13 (PHP 8.3+) |
| Web Frontend | Inertia.js + Vue 3 |
| Mobile App | React Native |
| Database | MySQL 8 |
| Cache/Queue | Redis |
| AI SDK | laravel/ai (official first-party package) |

## Architecture

```
┌──────────────────────────────────────────────────────────────────────┐
│                    ZamZam ERP (Single Laravel App)                    │
│                                                                      │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐              │
│  │  Auth &   │ │ │ Supplier │ │ │Inventory │   │
│  │  Users    │ │ │& Purchase│ │ │& Warehouse│  │
│  └──────────┘ └──────────┘ └──────────┘   │
│  │                                                          │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐              │
│  │Wholesale │ │ │ Retail    │ │ │ Credit & │ │ Accounts │   │
│  │ Sales    │ │ │ Sales    │ │ │ Payments │ │ & Finance│   │
│  └────┬─────┘ └────┬─────┘ └──────────┘ └──────────┘   │
│  │             │                                     │
│  ┌────┴─────────────┴──────────┐ ┌──────────────────┐              │
│  │  🏪 Native Storefronts      │ │ 👤 Reseller Panel │              │
│  │  (Wholesale + Retail)       │ │ (Self-Service)   │              │
│  │  📥 WooCommerce Importer    │ └──────────────────┘              │
│  └─────────────────────────────┘                                     │
│  │                                                          │
│  ┌──────────────────┐ ┌──────────────────────────────┐           │
│  │ Reporting &      │ │ Mobile App (React Native)    │           │
│  │ Dashboard        │ │ (Sales, Stock, Collection)    │           │
│  └──────────────────┘ └──────────────────────────────┘           │
│  │                                                          │
│  ┌──────────────────────────────────────────────────────┐ │
│  │  Conversation & AI Agent Hub (Native Laravel)          │ │
│  │  • WhatsApp (Multi-Provider) + Messenger              │ │
│  │  • 50s Human-First Rule                              │ │
│  │  • Visual Workflow Builder (Vue Flow)                 │ │
│  │  • Laravel AI SDK Agent + Tool Calling                │ │
│  │  • Real-time Chat Dashboard (Laravel Reverb)          │ │
│  └──────────────────────────────────────────────────────┘ │
│                                                                      │
│  ┌──────────────────────────────────────────────────────┐ │
│  │  🧩 Module Toggle System (Settings > Modules)          │ │
│  │  • Wholesale Storefront: ON/OFF                        │ │
│  │  • Retail Storefront: ON/OFF                          │ │
│  │  • Reseller Panel: ON/OFF                             │ │
│  │  • Conversion & AI: ON/OFF                            │ │
│  │  • WooCommerce Importer: ON/OFF (one-time)           │ │
│  └──────────────────────────────────────────────────────┘ │
└──────────────────────────────────────────────────────────────────────┘
```

## Modules

| # | Module | File | Phase | Toggle |
|---|--------|------|-------|--------|
| 1 | Auth & User Management | [01-auth-module.md](01-auth-module.md) | 1 | — |
| 2 | Supplier & Procurement | [02-supplier-procurement-module.md](02-supplier-procurement-module.md) | 2-3 | — |
| 3A | International Shipping (CN→BD) | [03a-international-shipping.md](03a-international-shipping.md) | 3 | — |
| 3B | Domestic Logistics (BD Courier) | [03b-domestic-logistics.md](03b-domestic-logistics.md) | 3-4B | — |
| 4 | Inventory & Warehouse | [04-inventory-warehouse-module.md](04-inventory-warehouse-module.md) | 2 | — |
| 5A | Wholesale Sales | [05a-wholesale-sales-module.md](05a-wholesale-sales-module.md) | 4A | — |
| 5B | Retail Sales | [05b-retail-sales-module.md](05b-retail-sales-module.md) | 4B | — |
| 6 | Credit & Payment Management | [06-credit-payment-module.md](06-credit-payment-module.md) | 4A | — |
| 7 | Accounts & Finance | [07-accounts-finance-module.md](07-accounts-finance-module.md) | 6 | — |
| 8 | WooCommerce Importer + Native Storefront | [08-woocommerce-integration-module.md](08-woocommerce-integration-module.md) | 5A-5B | ✅ Toggleable |
| 9 | Reseller Panel | [09-reseller-panel-module.md](09-reseller-panel-module.md) | 5C | ✅ Toggleable |
| 10 | Reporting & Dashboard | [10-reporting-dashboard-module.md](10-reporting-dashboard-module.md) | 6 | — |
| 11 | Mobile App | [11-mobile-app-module.md](11-mobile-app-module.md) | 7 | — |
| 12 | Conversation & AI Agent Hub | [12-conversation-ai-agent-hub.md](12-conversation-ai-agent-hub.md) | 6 | ✅ Toggleable |
| 13 | Design System | [13-design-system.md](13-design-system.md) | 1 | — |
| - | Database Schema | [DB-SCHEMA.md](DB-SCHEMA.md) | - | — |

## Roles & Access

| Role | Access Level |
|------|-------------|
| Admin | Full access |
| Manager | All modules (no delete) |
| Accountant | Accounts, Payments, Reports |
| Salesman | Sales, Customers, Collection, Domestic Parcels |
| Storekeeper | Inventory, Goods Receive, Stock Transfer |
| Procurement | Suppliers, PO, International Shipping |
| Reseller | Reseller Panel only (own data) |

## Menu Structure

```
📦 ZamZam ERP
├── 📊 Dashboard (role-based)
├── 🛒 Orders
│   ├── Wholesale Orders
│   ├── Retail Orders
│   ├── Picking Lists
│   ├── Order Dashboard (connected WooCommerce + ERP)
│   └── Fake Order Detection + IP Blacklist
├── 🚢 Shipping & Logistics
│   ├── 🌐 International Shipping (CN → BD)
│   │   ├── Shipments List
│   │   ├── Create Shipment
│   │   ├── Shipment Detail + Tracking
│   │   ├── Landing Cost
│   │   └── Shipment Documents
│   └── 🚚 Domestic Logistics (BD Delivery)
│       ├── Courier Partners
│       ├── Delivery Zones
│       ├── Parcels List
│       ├── Create Parcel
│       ├── Delivery Tracking Board
│       ├── Shipping Labels
│       ├── COD Reconciliation
│       ├── Courier Bills
│       └── Delivery Success Meter
├── 📦 Inventory & Warehouse
├── 👥 Customers & Resellers
│   ├── 📋 Customer List (filter by tag, tier, source)
│   ├── ➕ Create Customer
│   ├── 📊 Customer Detail (tags, orders, ledger)
│   ├── 🏷️ Customer Tags
│   │   ├── Tag List
│   │   ├── Create Tag
│   │   └── Edit Tag (with linked price tier)
│   ├── 📥 Import Customers
│   │   ├── Step 1: Upload CSV/XLSX (+ Download Template)
│   │   ├── Step 2: Column Mapping (with tag/source mapping)
│   │   ├── Step 3: Validation & Preview
│   │   ├── Step 4: Import Progress
│   │   └── Step 5: Complete
│   └── 🏪 Reseller Panel
├── 💰 Accounts & Finance
├── 🔗 WooCommerce Importer
│   ├── Connect Store
│   ├── Select Data to Import
│   ├── Import Progress
│   └── Import History & Logs
├── 💬 Conversations
│   ├── Inbox (Unassigned + My Chats)
│   ├── All Conversations
│   ├── Quick Reply Templates
│   ├── Conversation Tags
│   └── Agent Action Logs
├── 🤖 Chatbot Builder
│   ├── Workflows List
│   ├── Workflow Editor (Drag & Drop)
│   ├── Workflow Test Mode
│   ├── Workflow Executions
│   └── Workflow Templates
└── 📈 Reports
    ├── 📊 Dashboard (role-based)
    ├── 📅 Monthly Reports
    │   ├── Report List (year/month grid)
    │   ├── Report Dashboard (interactive charts)
    │   └── Report PDF Preview
    ├── 🛒 Sales Reports
    │   ├── Sales Summary
    │   ├── By Product
    │   ├── By Customer
    │   └── By Salesman
    ├── 📦 Inventory Reports
    │   ├── Stock Summary
    │   ├── Stock Valuation
    │   ├── Movement Report
    │   └── Slow Moving
    ├── 💳 Credit Reports
    │   ├── Aging Report
    │   ├── Collection Report
    │   └── Credit Utilization
    ├── 💹 Profit Reports
    │   ├── Product Profitability
    │   ├── Category Profitability
    │   └── Monthly P&L
    ├── 🚢 Shipping Reports
    │   ├── Shipment Summary
    │   └── Landing Cost Analysis
    ├── 💰 Financial Reports
    │   ├── Trial Balance
    │   ├── Balance Sheet
    │   └── Cash Flow
    └── ⚙️ Delivery Settings
        ├── WhatsApp Recipients
        ├── Telegram Recipients
        ├── Email Recipients
        └── Test Delivery
└── ⚙️ Settings
    ├── 🆔 ID Format Settings
    │   ├── Customer ID Format
    │   ├── Product ID Format (future)
    │   └── Supplier ID Format (future)
    ├── 🧩 Module Management
    │   ├── Wholesale Storefront: ON/OFF
    │   ├── Retail Storefront: ON/OFF
    │   ├── Reseller Panel: ON/OFF
    │   ├── Conversation & AI: ON/OFF
    │   └── WooCommerce Importer: ON/OFF
    ├── 📦 Storefront Settings
    │   ├── Wholesale Storefront
    │   │   ├── Store Name, Theme, Pricing
    │   │   ├── Minimum Order Value
    │   │   ├── Credit Order Settings
    │   │   └── Default Price Tier
    │   └── Retail Storefront
    │       ├── Store Name, Theme
    │       ├── Delivery Charges
    │       ├── Free Delivery Minimum
    │       └── Guest Checkout Toggle
    ├── 📱 WhatsApp Providers
    └── 👤 User & Role Management
```

## Multi-Currency

| Currency | Use Case |
|----------|----------|
| CNY | Purchase from China suppliers |
| USD | International payments (T/T) |
| BDT | Local sales, expenses, reports |

**All reports converted to BDT using exchange rates on transaction date.**

## Landing Cost Calculation

```
Landing Cost = Purchase Price (CNY → BDT at exchange rate)
             + Freight Cost (allocated per product by weight/volume ratio)
             + Customs Duty
             + VAT + AIT
             + Labour + Transport
             + Other Shipment Costs (allocated)
```

## Profit Calculation

```
Gross Profit = Sales Price (BDT) - Landing Cost (BDT)
Net Profit   = Gross Profit - Operating Expenses - Credit Loss
```

## Design System

Full design system documented in [13-design-system.md](13-design-system.md).

| Aspect | Standard |
|--------|----------|
| Primary Color | Indigo-500 `#6366F1` |
| Brand Accent | BD Green `#006A4E` |
| Font | Inter + Noto Sans Bengali + Noto Sans SC |
| Layout | Sidebar w-60 + Top Bar h-14 |
| Invoice Template | Zamzam International minimal style (dark navy + BD green) |
| Framework | Tailwind CSS v4 + Inertia.js + Vue 3 |

---

## UI Design Overview

> **For developers starting the project** — read this section first to understand the complete visual layout system before writing any frontend code. Detailed specs are in [13-design-system.md](13-design-system.md).

### App Shell (Every Page)

```
┌─────────────────────────────────────────────────────────────────────────┐
│ ┌──────┐ ┌──────────────────────────────────────────────────────────┐ │
│ │      │ │  Top Bar (h-14, bg-white, border-b)                      │ │
│ │      │ │  ┌────────────────────┬──────────┬──────────────────┐    │ │
│ │      │ │  │ 🔍 Search...       │🔔 3 📨 5 │👤 Admin ▾       │    │ │
│ │ S    │ │  └────────────────────┴──────────┴──────────────────┘    │ │
│ │ i    │ ├──────────────────────────────────────────────────────────┤ │
│ │ d    │ │                                                          │ │
│ │ e    │ │  Page Content (p-6, max-w-[1600px] centered)             │ │
│ │ b    │ │                                                          │ │
│ │ a    │ │  ┌─────────────────────────────────────────────────┐     │ │
│ │ r    │ │  │  Page Title                    [+ Action Button]  │     │ │
│ │      │ │  ├─────────────────────────────────────────────────┤     │ │
│ │ w-60 │ │  │                                                 │     │ │
│ │      │ │  │  ( Module Content )                             │     │ │
│ │      │ │  │                                                 │     │ │
│ │      │ │  └─────────────────────────────────────────────────┘     │ │
│ └──────┘ └──────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────────────┘
```

**Key Measurements:**
- Sidebar: `w-60` (240px) expanded, `w-16` (64px) collapsed
- Top Bar: `h-14` (56px), white bg, `border-b border-slate-200`
- Content: `p-6` (24px padding), `max-w-[1600px]` centered
- Card: `rounded-xl` (12px), `shadow-sm`, `bg-white`
- Active nav item: `bg-indigo-50 text-indigo-700 border-l-2 border-indigo-600`

### Typography Scale

| Size | Class | Usage |
|------|-------|-------|
| 36px | `text-4xl font-extrabold` | Dashboard hero numbers |
| 30px | `text-3xl font-bold` | Stats numbers |
| 24px | `text-2xl font-semibold` | Page titles |
| 20px | `text-xl font-semibold` | Section titles |
| 18px | `text-lg font-medium` | Card titles |
| 16px | `text-base` | Body text, inputs |
| 14px | `text-sm` | Table cells, subtitles |
| 12px | `text-xs` | Badges, meta, timestamps |

**Font stack:** `font-sans: 'Inter', 'Noto Sans Bengali', 'Noto Sans SC', sans-serif`
**Mono:** `font-mono: 'JetBrains Mono', monospace` (for SKU, order numbers, amounts)

### Color Palette (Quick Ref)

```
Primary Actions:  bg-indigo-600 hover:bg-indigo-700 text-white
Success:          bg-emerald-100 text-emerald-700 (badge) / bg-emerald-600 (button)
Warning:           bg-amber-100 text-amber-700
Danger:            bg-red-100 text-red-700 (badge) / bg-red-600 (button)
Info:              bg-blue-100 text-blue-700
Neutral:           bg-slate-100 text-slate-700

Page Background:   bg-slate-50 (#F8FAFC)
Card Background:    bg-white
Card Hover:         bg-slate-100
Border:             border-slate-200
Border Focus:       border-indigo-500 ring-indigo-200

Module Accent Colors (consistent with 13-design-system.md):
  Purchase:    purple-600  #9333EA
  Shipping:    cyan-600    #0891B2
  Sales:       orange-600  #EA580C
  Finance:     emerald-600 #059669
  Chat/AI:     violet-600  #7C3AED
  Inventory:   amber-600   #D97706
  WooCommerce: purple-600  #9333EA
  Reseller:    teal-600    #0D9488
```

### Status Badges (All Modules)

```
Style: rounded-full px-2.5 py-0.5 text-xs font-medium

Draft:        bg-purple-100 text-purple-700    🟣
Pending:      bg-amber-100 text-amber-700      🟡
Confirmed:    bg-blue-100 text-blue-700        🔵
In Progress:  bg-cyan-100 text-cyan-700        🔵
Completed:    bg-emerald-100 text-emerald-700   🟢
Delivered:    bg-green-100 text-green-700      ✅
Cancelled:    bg-red-100 text-red-700          🔴
Overdue:      bg-red-100 text-red-700 + pulse   🔴⚡
```

### Page Patterns (4 Types)

#### 1. Dashboard Page (Stats + Charts)
```
┌──────────────────────────────────────────────────────────────────┐
│  📊 Dashboard                               [This Month ▼]      │
├──────────────────────────────────────────────────────────────────┤
│  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐        │
│  │ 💰 Revenue│ │ 📦 Orders│ │ 💹 Profit │ │ 📊 Stock │        │
│  │  ৳24.5L  │ │   347    │ │  ৳5.8L   │ │  23 Low  │        │
│  │  ▲ 12%   │ │  ▲ 8%    │ │  ▲ 15%   │ │  ▼ 5%    │        │
│  └──────────┘ └──────────┘ └──────────┘ └──────────┘        │
│                                                                  │
│  ┌── Chart Area ────────────────────────────────────────────┐   │
│  │  (Chart.js line/bar chart — revenue trend 30 days)       │   │
│  └──────────────────────────────────────────────────────────┘   │
│                                                                  │
│  ┌── Recent Orders ──────────────────────────────────────────┐  │
│  │  (Data table — last 5 orders with status badges)          │  │
│  └───────────────────────────────────────────────────────────┘  │
└──────────────────────────────────────────────────────────────────┘

Stats card: bg-white rounded-xl shadow-sm p-6
  - Icon + label (text-sm text-slate-600)
  - Number (text-3xl font-bold text-slate-900)
  - Trend (text-sm, ▲ green or ▼ red)
  - Progress bar optional (h-2 rounded bg-slate-100)
```

#### 2. List Page (Search + Filter + Table)
```
┌──────────────────────────────────────────────────────────────────┐
│  📦 Purchase Orders                    [+ New PO] [📥] [📤]    │
├──────────────────────────────────────────────────────────────────┤
│  🔍 Search... │ Status:[All▼] │ Supplier:[All▼] │ [Reset]     │
│  Active: [✕ Confirmed] [✕ May] [Clear All]                    │
├──────┬──────────┬──────────┬──────────┬──────────┬────────────┤
│  ☑   │ PO#      │ Supplier │ Total    │ Status   │ Actions     │
├──────┼──────────┼──────────┼──────────┼──────────┼────────────┤
│  ☐   │ PO-0001  │ Shenzhen │ ৳12.5L   │ 🟡 Conf. │ ⋮ Edit ... │
│  ☐   │ PO-0002  │ Guangzho │ ৳8.2L    │ 🔵 Ship. │ ⋮ Edit ... │
├──────┴──────────┴──────────┴──────────┴──────────┴────────────┤
│  3 selected  [Delete] [Export]   ◀ 1 2 3 ... 50 ▶  1-25/1245 │
└──────────────────────────────────────────────────────────────────┘

Table: bg-white rounded-xl shadow-sm overflow-hidden
  Header: bg-slate-50 text-sm font-medium text-slate-700
  Row: border-b border-slate-100 hover:bg-slate-50
  Checkbox: h-4 w-4 rounded border-slate-300
  Action menu: ⋮ dropdown (Edit, Duplicate, Delete, Print)
  Pagination: flex justify-between items-center px-6 py-3
```

#### 3. Create/Edit Page (Sections + Form)
```
┌──────────────────────────────────────────────────────────────────┐
│  ← Back to Purchase Orders                                       │
│                                                                  │
│  Create Purchase Order                                           │
│  ─────────────────────────────────────────────────────────────── │
│                                                                  │
│  ┌── Section: Basic Info ──────────────────────────────────────┐│
│  │  (Collapsible card with gray header)                          ││
│  │                                                              ││
│  │  ┌─────────────────┐  ┌─────────────────┐                  ││
│  │  │ Supplier *      │  │ Order Date *     │  (2-col grid)    ││
│  │  │ [Select ▼]      │  │ [📅]             │                  ││
│  │  └─────────────────┘  └─────────────────┘                  ││
│  │                                                              ││
│  └──────────────────────────────────────────────────────────────┘│
│                                                                  │
│  ┌── Section: Items ───────────────────────────────────────────┐│
│  │  [+ Add Item]  [📥 Import]                                  ││
│  │                                                              ││
│  │  (Inline editable table)                                     ││
│  │                                                              ││
│  └──────────────────────────────────────────────────────────────┘│
│                                                                  │
│  ┌── Section: Summary ─────────────────────────────────────────┐│
│  │  (Right-aligned totals: Subtotal, Tax, Grand Total)         ││
│  └──────────────────────────────────────────────────────────────┘│
│                                                                  │
│  [Cancel]  [Save as Draft]  [Save & Confirm]                    │
│                                                                  │
│  (Sticky bottom bar on long forms: bg-white border-t shadow-lg)  │
└──────────────────────────────────────────────────────────────────┘

Form input: w-full rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-200
Required: label with text-red-500 asterisk, validation on blur
Error: text-sm text-red-600 mt-1 with ⚠️ icon
Section: bg-white rounded-xl shadow-sm, collapsible header with ChevronDown icon
2-col: grid grid-cols-1 md:grid-cols-2 gap-6
3-col: grid grid-cols-1 md:grid-cols-3 gap-6
```

#### 4. Detail Page (Header + Tabs + Timeline)
```
┌──────────────────────────────────────────────────────────────────┐
│  ← Back to PO List                                               │
│                                                                   │
│  PO-2026-0001                              [Edit] [Print] [⋮]   │
│  ─────────────────────────────────────────────────────────────── │
│                                                                   │
│  ┌── Header Cards (3-4 stat cards in row) ────────────────────┐ │
│  │  🏭 Shenzhen Trading  │  🟡 Confirmed  │  📅 09 May 2026  │ │
│  └──────────────────────────────────────────────────────────────┘ │
│                                                                   │
│  ┌── Tabs ─────────────────────────────────────────────────────┐│
│  │  [Items]  [Costs]  [Shipping]  [Documents]  [History]       ││
│  └──────────────────────────────────────────────────────────────┘│
│                                                                   │
│  ┌── Tab Content ──────────────────────────────────────────────┐│
│  │  (Data table, form, or detail cards based on active tab)     ││
│  └──────────────────────────────────────────────────────────────┘│
│                                                                   │
│  ┌── Timeline (always visible, left-aligned) ──────────────────┐│
│  │  ● 09 May 10:30 — Created by Admin              (completed) ││
│  │  ● 09 May 11:15 — Confirmed by Manager           (completed) ││
│  │  ● 12 May 14:00 — Shipment SH-001 created        (completed) ││
│  │  ○ 25 May — Expected delivery                     (upcoming) ││
│  └──────────────────────────────────────────────────────────────┘│
└──────────────────────────────────────────────────────────────────┘

Tabs: flex border-b border-slate-200, active tab text-indigo-600 border-b-2 border-indigo-600
Tab content: py-6
Timeline: space-y-4, left-aligned dot + line
  Completed: bg-indigo-100 text-indigo-600 dot
  Upcoming: bg-slate-100 text-slate-400 dot (hollow)
```

#### Data Import Wizard (5-Step)
```
┌──────────────────────────────────────────────────────────────────────┐
│  📥 Import Customers                              Step 2 of 5       │
│  ─────────────────────────────────────────────────────────────────── │
│  [1 Upload]  [2 Mapping]  [3 Validate]  [4 Import]  [5 Complete]   │
│  ●───────────●───────────○────────────○────────────○               │
├──────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  Map your CSV columns to ERP fields:                                │
│                                                                      │
│  ┌──────────────────┬─────────────────────┬────────────────────┐  │
│  │  CSV Column      │  ERP Field           │  Sample Data        │  │
│  ├──────────────────┼─────────────────────┼────────────────────┤  │
│  │  Customer ID     │  [External ID ▼] ✅  │  C-5851976         │  │
│  │  Customer Name   │  [Name ▼] ✅ *       │  Abdullah Store    │  │
│  │  Phone           │  [Phone ▼] ✅ *      │  +8801728...       │  │
│  │  Customer Tag    │  [Tags ▼] ✅          │  REGULAR            │  │
│  │  Customer Source │  [Source ▼] ✅       │  MESSENGER          │  │
│  │  District        │  [City ▼] ✅          │  Gazipur District  │  │
│  └──────────────────┴─────────────────────┴────────────────────┘  │
│                                                                      │
│  🏷️ Tag Mapping:                                                    │
│  ┌─────────────────┬────────────────┬──────────────────────────┐  │
│  │  CSV Value       │  ERP Tag        │  Linked Price Tier       │  │
│  ├─────────────────┼────────────────┼──────────────────────────┤  │
│  │  VIP             │  VIP ✅          │  Platinum ▼              │  │
│  │  REGULAR         │  REGULAR ✅      │  Silver ▼                │  │
│  │  NEW             │  NEW ✅          │  Bronze ▼               │  │
│  │  (unmapped)      │  [+ Create Tag]  │                          │  │
│  └─────────────────┴────────────────┴──────────────────────────┘  │
│                                                                      │
│  [← Back]                                            [Next →]       │
└──────────────────────────────────────────────────────────────────────┘

Step indicator: flex items-center gap-2, completed=bg-indigo-600, current=bg-indigo-500 ring-4, future=bg-slate-200
Tag badges: rounded-full px-2.5 py-0.5 text-xs font-medium (same as customer tag colors)
Error badge: bg-red-50 text-red-700 rounded px-2 py-0.5
Success badge: bg-emerald-50 text-emerald-700 rounded px-2 py-0.5
Progress bar: h-2 rounded-full bg-slate-200, inner=bg-indigo-600 transition-all duration-300
```

#### Customer Tags Management
```
┌──────────────────────────────────────────────────────────────────────┐
│  🏷️ Customer Tags                              [+ Add Tag]          │
├──────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  ┌────────────────────────────────────────────────────────────────┐  │
│  │  🟡 VIP                                        [✏️] [🗑️] │  │
│  │  Color: #F59E0B  │  Customers: 7  │  Linked Tier: Platinum    │  │
│  │  Auto-assign: No                                               │  │
│  └────────────────────────────────────────────────────────────────┘  │
│                                                                      │
│  ┌────────────────────────────────────────────────────────────────┐  │
│  │  🔵 REGULAR                                    [✏️] [🗑️] │  │
│  │  Color: #3B82F6  │  Customers: 489  │  Linked Tier: Silver     │  │
│  │  Auto-assign: No                                               │  │
│  └────────────────────────────────────────────────────────────────┘  │
│                                                                      │
│  ┌────────────────────────────────────────────────────────────────┐  │
│  │  🟢 NEW                                        [✏️] [🗑️] │  │
│  │  Color: #10B981  │  Customers: 125  │  Linked Tier: Bronze     │  │
│  │  Auto-assign: Yes (new customers)                              │  │
│  └────────────────────────────────────────────────────────────────┘  │
│                                                                      │
│  ┌────────────────────────────────────────────────────────────────┐  │
│  │  🔴 Frozen                                     [✏️] [🗑️] │  │
│  │  Color: #EF4444  │  Customers: 0  │  Linked Tier: None          │  │
│  │  Auto-assign: No                                               │  │
│  └────────────────────────────────────────────────────────────────┘  │
│                                                                      │
│  [+ Add Tag]                                                          │
└──────────────────────────────────────────────────────────────────────┘

Tag card: bg-white rounded-xl shadow-sm p-4 border-l-4 (border-l-color matches tag color)
Delete confirmation: modal with warning "X customers have this tag"
```

### Special Pages

#### Chat Inbox (3-Column Layout)
```
┌──────────────┬──────────────────────────────────────────────────────────┐
│              │  Rahim Store 🟢                                          │
│  FILTERS     │  WhatsApp • Last seen: 2 min ago                        │
│  ┌────────┐  │                                                          │
│  │All     │  │  👤 মগ এর দাম কত?                    │  10:32 AM    │
│  │Unread  │  │                                                          │
│  │Mine    │  │       🤖 100টা মগের দাম ৳৪,৫০০             │  10:33 AM │
│  │AI Act. │  │                                                          │
│  └────────┘  │  👤 কার্টে যোগ কর                       │  10:34 AM    │
│              │                                                          │
│  🔴 ACTIVE   ├──────────────────────────────────────────────────────────┤
│  🟢 Rahim    │  ⚡ AI is active  [🤖 Pause AI] [👤 Takeover]           │
│  🟢 Karim    ├──────────────────────────────────────────────────────────┤
│  🟡 Ali      │  💬 Type a message...                          📎 😊 📤  │
│              ├──────────────────────────────────────────────────────────┤
│  🟡 IDLE     │  📋 Customer  │  🛒 Cart  │  📊 Actions │  📦 Orders   │
│  Fatema      │                                                            │
│              │  Rahim Store                                │            │
│  ✅ CLOSED   │  📱 +880 1XXX  │  🏪 Wholesale  │  💰 ৳1.2L due │       │
│  Hassan      │  📦 45 orders  │  🏷️ [VIP] [Wholesale]  │             │
└──────────────┴──────────────────────────────────────────────────────────┘

Layout: 3-column — sidebar w-80 | chat flex-1 | info w-80
Chat bubbles:
  Customer: bg-white rounded-xl rounded-bl-sm (left-aligned)
  AI: bg-violet-50 rounded-xl rounded-bl-sm (left-aligned, 🤖 icon)
  Human: bg-blue-50 rounded-xl rounded-br-sm (right-aligned, 👤 icon)
50s Timer: h-2 rounded-full, bg-green-500 → bg-amber-500 → bg-red-500 (animated width)
```

#### Workflow Builder (Full-Screen Modal/Page)
```
┌────────┬────────────────────────────────────────────────────┬─────────────┐
│ NODES  │                                                    │  NODE       │
│ PALETTE│  (Vue Flow Canvas — drag & drop nodes)           │  CONFIG     │
│        │                                                    │             │
│ 🔵 Trig│  Nodes connected by edges (animated flow)         │  Model:    │
│ 🟢 AI  │  Right sidebar: click node → config panel         │  [gpt-4o▼] │
│ 🟡 Act │                                                    │             │
│ 🔴 Log │                                                    │  [🗑️ Del]  │
│ 🟣 Hum │  [Zoom In] [Zoom Out] [Reset] [Export JSON]      │  [📋 Copy] │
│ 🟠 Int │                                                    │             │
└────────┴────────────────────────────────────────────────────┴─────────────┘

Node colors: Trigger=blue, AI=green, Action=amber, Logic=red, Human=violet, Integration=orange
Edge styles: default=gray dotted, active=blue animated, condition-true=green, condition-false=red
```

#### Invoice Print (A4, minimal ZamZam style)
```
┌──────────────────────────────────────────────────────────────┐
│  🏪 LOGO                              ZAMZAM INTERNATIONAL │
│                                       Hotline: +880 1XXX   │
│                                       ┌─────────────────┐   │
│                                       │  |||||||||||||  │   │
│                                       │  BARCODE        │   │
│                                       └─────────────────┘   │
│                                       Invoice: SO-0966      │
│                                       Courier: Steadfast    │
│                                       Date: 06/05/2026      │
│                                                              │
│  BILL TO:                                                    │
│  Rahim Store, +880 1XXX, 123 Main Road, Chittagong         │
│                                                              │
│  SL│Image│Product Name    │Weight│Unit Price│Qty│Total      │
│  ──┼──────┼───────────────┼──────┼─────────┼───┼────────     │
│  01│ [🖼] │Ceramic Mug    │0.3kg │ ৳45.00  │100│৳4,500     │
│  02│ [🖼] │Glass Cup      │0.2kg │ ৳25.00  │200│৳5,000     │
│                                                              │
│                          Subtotal:       ৳12,500.00          │
│                          Delivery:          ৳120.00          │
│                          Grand Total:    ৳12,620.00          │
│                          Paid:             ৳5,000.00          │
│                          ┌──────────────────────────┐       │
│                          │  Due Amount:  ৳7,620.00   │       │
│                          └──────────────────────────┘       │
│                           (dark navy bg + white text)        │
│                                                              │
│  📘 FB  📧 Email  🌐 Web  🏢 Address                         │
│  ┌──────────────────────────────────────────────────────────┐│
│  │  📞 Hotline  │  📘 Facebook  │  💬 WhatsApp              ││
│  └──────────────────────────────────────────────────────────┘│
│  - - - - - - ✂ - - - - - - ✂ - - - - - - ✂ - - - - - - - │
│  RECEIPT: Zamzam │ SO-0966 │ ৳7,620 Due │ 06/05/2026       │
└──────────────────────────────────────────────────────────────┘

Invoice colors: Header=#1A1A2E, Accent=#006A4E, Text=#333333, Due box=#1A1A2E bg + #FFF text
```

### Component Library (Quick Reference)

| Component | Usage | Key Classes |
|-----------|-------|------------|
| Stats Card | Dashboard KPIs | `bg-white rounded-xl shadow-sm p-6` |
| Data Table | List pages | `bg-white rounded-xl shadow-sm overflow-hidden` |
| Status Badge | All status fields | `rounded-full px-2.5 py-0.5 text-xs font-medium` |
| Form Input | Create/Edit | `rounded-lg border-slate-300 focus:border-indigo-500` |
| Section Card | Form sections | `bg-white rounded-xl shadow-sm`, collapsible |
| Modal | Confirmations | `bg-white rounded-2xl shadow-xl max-w-md` |
| Drawer | Quick view | `w-[480px] bg-white shadow-xl` (right slide) |
| Filter Bar | List pages | `flex items-center gap-3 flex-wrap` |
| Toast | Notifications | `fixed top-4 right-4 z-50` auto-dismiss 5s |
| Empty State | No data pages | Icon + title + description + CTA button |
| Chat Bubble | Chat module | Customer=white, AI=violet-50, Human=blue-50 |
| 50s Timer | Chat module | `h-2 rounded-full` green→amber→red animated |
| Workflow Node | Chatbot builder | Color-coded by type, rounded-lg, Vue Flow |
| Invoice | Print/PDF | A4, #1A1A2E header, #006A4E accent, barcode |

### Responsive Breakpoints

| Breakpoint | Width | Behavior |
|-----------|-------|----------|
| `sm` | 640px | 1 column, sidebar collapsed, table → card list |
| `md` | 768px | 2 columns, sidebar collapsed |
| `lg` | 1024px | Sidebar expanded, 3-col stats grid |
| `xl` | 1280px | Full layout, wide tables |
| `2xl` | 1536px | Max-width container centered |

### Keyboard Shortcuts

| Shortcut | Action |
|----------|--------|
| `Ctrl+K` | Command palette (search anything) |
| `Ctrl+N` | New record (context-aware) |
| `Ctrl+S` | Save current form |
| `Ctrl+E` | Export current view |
| `Esc` | Close modal/drawer |

### Dark Mode (Optional)

```
Toggle: ⚙️ Settings > Appearance > Light ☀️ | Dark 🌙 | System 💻

Dark backgrounds:
  Page:    #0F172A (Slate-900)
  Card:    #1E293B (Slate-800)
  Hover:   #334155 (Slate-700)
  Border:  #475569 (Slate-600)
  Text:    #F1F5F9 (Slate-100) / #94A3B8 (Slate-400)

Implementation: Tailwind dark: prefix on all component classes
```

## Development Phases

| Phase | Duration | Focus |
|-------|----------|-------|
| 1 | Week 1-3 | Foundation, Auth, Roles, DB, Multi-currency |
| 2 | Week 4-6 | Inventory, Supplier, Barcode/QR |
| 3 | Week 7-9 | Purchase, Shipping, Landing Cost |
| 4A | Week 10-12 | Wholesale Sales, Credit, Price Tiers |
| 4B | Week 13-14 | Retail Sales, Online Payment |
| 5A | Week 15-16 | Wholesale WooCommerce Integration |
| 5B | Week 17-18 | Retail WooCommerce Integration |
| 5C | Week 19-20 | Reseller Panel |
| 6 | Week 21-23 | Accounts, Reports, Dashboard, AI Agent Hub |
| 7 | Week 24-27 | Mobile App (React Native) |

## Project Structure

```
zamzam-erp/
├── app/
│   ├── Models/
│   │   ├── Auth/           (User, Role, Permission)
│   │   ├── Procurement/    (Supplier, PurchaseOrder, PoItem)
│   │   ├── Shipping/       (Shipment, ShipmentCost, ShipmentDoc)
│   │   ├── Inventory/      (Warehouse, StockItem, StockTransaction)
│   │   ├── Sales/          (Customer, SalesOrder, Invoice, SalesReturn, CustomerTag, DataImport, StorefrontOrder)
│   │   ├── Finance/        (Payment, CreditLedger, Journal, Expense)
│   │   ├── Chat/           (Conversation, ChatCart, AgentAction, WhatsappProvider, ChatbotWorkflow)
│   │   └── Core/           (Currency, ExchangeRate, Category, Product)
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Web/            (Inertia pages)
│   │   │   │   ├── Admin/      (ERP dashboard pages)
│   │   │   │   ├── Wholesale/  (Wholesale storefront pages)
│   │   │   │   ├── Shop/       (Retail storefront pages)
│   │   │   │   └── Reseller/   (Reseller panel pages)
│   │   │   └── Api/            (Mobile API + Reseller API + Storefront API)
│   ├── Middleware/
│   │   ├── EnsureModuleIsActive.php
│   │   ├── EnsureAtLeastOneStorefront.php
│   ├── Services/
│   │   ├── LandingCostService.php
│   │   ├── CreditService.php
│   │   ├── CurrencyService.php
│   │   ├── StockService.php
│   │   ├── ProfitCalculationService.php
│   │   ├── IdGenerationService.php
│   │   ├── DataImportService.php
│   │   ├── PaymentService.php
│   │   ├── WooCommerceImportService.php
│   │   ├── Chat/
│   │   │   ├── ConversationService.php
│   │   │   ├── HumanFirstTimerService.php
│   │   │   ├── AgentReplyService.php
│   │   │   └── WorkflowExecutorService.php
│   │   └── WhatsApp/
│   │       ├── WhatsAppService.php
│   │       └── Drivers/
│   │           ├── MetaOfficialDriver.php
│   │           ├── TwilioDriver.php
│   │           ├── WasenderDriver.php
│   │           ├── UltraMsgDriver.php
│   │           ├── MaytapiDriver.php
│   │           ├── EvolutionApiDriver.php
│   │           ├── WppConnectDriver.php
│   │           └── CustomHttpDriver.php
│   ├── Exports/
│   ├── Events/
│   ├── Listeners/
│   └── Policies/
├── database/
│   ├── migrations/
│   ├── seeders/
│   └── factories/
├── resources/
│   ├── js/
│   │   ├── Pages/          (Inertia pages per module)
│   │   │   ├── Customers/  (Index, Create, Show, Ledger, Import, Tags/)
│   │   │   ├── Wholesale/  (Storefront: Catalog, Product, Cart, Checkout, Orders)
│   │   │   ├── Shop/       (Storefront: Catalog, Product, Cart, Checkout, Auth)
│   │   │   ├── WooCommerceImport/ (Connect, Scan, Progress, Complete, Logs)
│   │   │   ├── Settings/   (Modules, Storefront, ID Format)
│   │   ├── Components/     (Shared Vue components)
│   │   ├── Layouts/
│   │   ├── Composables/
│   │   └── Pages/Chat/     (Chat inbox, conversation detail)
│   │       └── Pages/Chatbot/ (Workflow editor, test mode)
│   └── views/             (Blade for PDF)
├── routes/
│   ├── web.php
│   ├── api.php
│   └── webhook.php
├── mobile/                 (React Native)
│   ├── src/
│   │   ├── screens/
│   │   ├── components/
│   │   ├── api/
│   │   └── navigation/
│   └── app.json
└── docs/                   (This documentation)
```
