# Business Dashboard: Courier Integration, Fraud Detection, Multi-Company & Custom Storefront — Master Plan (v2)

## Document Purpose

এই ডকুমেন্ট **Business Dashboard** (Laravel + Filament ERP) সিস্টেমের জন্য চারটা বড় আপগ্রেডের সম্পূর্ণ পরিকল্পনা:

1. **Multi-Company System**
2. **Courier Integration Module**
3. **Customer Success & Risk Score (Fraud Detection) Module**
4. **Custom Lightweight Storefront — সম্পূর্ণ ERP থেকে ম্যানেজড E-Commerce**

> **v1 থেকে পরিবর্তন:** Lunar e-commerce package সরিয়ে দেওয়া হয়েছে। কারণ ও যুক্তি নিচের "কেন Lunar নয়" সেকশনে আছে। এর বদলে একটা **native Blade + Livewire storefront** ডিজাইন করা হয়েছে যা সম্পূর্ণভাবে এই একই ERP কোডবেস ও Filament প্যানেল থেকে নিয়ন্ত্রিত হবে — কোনো আলাদা package, আলাদা admin panel, বা sync layer ছাড়াই।

**Investor/Mudarabah Module ও Lead/CRM Module:** এই দুটো এখনকার চারটা প্রধান কাজের স্কোপের বাইরে রাখা হয়েছে। ডকুমেন্টের শেষে **"Part 11: ভবিষ্যৎ মডিউল ও Build Order"** সেকশনে এগুলোর বিস্তারিত প্ল্যান এবং ঠিক কখন এগুলো শুরু করতে হবে তার স্পষ্ট নির্দেশনা দেওয়া আছে। **এজেন্ট/ডেভেলপারের জন্য নির্দেশ: Part 1-10 সম্পূর্ণ না হওয়া পর্যন্ত Part 11-এর কোনো কাজ শুরু করবেন না।**

---

## কেন Lunar নয় — সিদ্ধান্তের পেছনের কারণ

| বিবেচ্য বিষয় | Custom Lightweight Storefront | Lunar Package |
|---|---|---|
| B2B wholesale (tiered pricing, MOQ) fit | স্বাভাবিকভাবেই মেলে | Lunar মূলত B2C retail-কেন্দ্রিক, MOQ/tiered logic উপর থেকে চাপাতে হবে |
| AI agent hallucination risk | কম — agent নিজের কোডবেসেই কাজ করে, প্রতিটা মডেল/রিলেশন context-এ visible | বেশি — কম-পরিচিত package internals নিয়ে agent ভুল অনুমান করে |
| Multi-company architecture fit | সরাসরি একই `company_id` + `BelongsToCompany` trait কাজ করবে | আলাদা channel system, দুই দিক থেকে mapping table লাগবে |
| Maintenance | সম্পূর্ণ নিয়ন্ত্রণ, এক কোডবেস | প্যাকেজ আপডেট = breaking change risk |
| Long-term flexibility | ১০০% কাস্টমাইজযোগ্য | প্যাকেজের architecture decision মেনে চলতে হয় |

**মূল নীতি:** Hallucination কমে ছোট-স্কোপ টাস্ক, পরিচিত Laravel pattern, এবং প্রতি ধাপে টেস্ট থেকে — প্যাকেজ পরিবর্তন থেকে নয়। তাই Lunar-এর বদলে আমরা agent-friendly, ছোট-ছোট ধাপে ভাঙা custom storefront architecture বেছে নিয়েছি।

---

## Current Product Context

বিদ্যমান ERP-তে যা আছে:

```
Product/Category management · Inventory & stock movement · Supplier & purchase management
China-to-Bangladesh purchase costing · Customer & order management · Customer/Supplier payments
Expense & account ledger · Reports & exports · Role & permission system · Audit logging
```

**নতুন দিকনির্দেশ:**

> **Multi-company ERP, native storefront, courier automation, COD risk scoring, এবং customer success tracking — সবকিছু একই Filament admin panel থেকে নিয়ন্ত্রিত।**

---

# Part 1: Multi-Company System

(আগের প্ল্যানের মতই অপরিবর্তিত — এটাই ভিত্তি, বাকি সব মডিউল এর উপর নির্ভর করবে)

## 1.1 Business Requirement

```txt
1. Garments Machinery Company
2. Solar Items Company
3. Gadget Items Company
4. Gift Items Company
```

প্রতিটা company-র থাকবে আলাদা: products, customers, suppliers, purchases, stock, sales, accounts, reports, invoice branding, courier settings, staff permissions।

## 1.2 আর্কিটেকচার সিদ্ধান্ত

> **Single Application + Single Database + Multi-Company Data Isolation**

```txt
Single Laravel App → Single Database → Multiple Companies → Company-wise Data Isolation → Owner-level Group Reporting
```

## 1.3 Companies টেবিল

```txt
companies
---------
id, name, slug, business_type, logo, phone, email, address,
currency, timezone, invoice_prefix, is_active, settings JSON,
created_at, updated_at
```

## 1.4 Company-User Access (Pivot)

```txt
company_user
------------
id, company_id, user_id, role, is_default, created_at, updated_at
```

## 1.5 `company_id` যুক্ত হবে এসব টেবিলে

```txt
categories, products, stock_movements, suppliers, purchases, purchase_items,
customers, orders, order_items, customer_payments, supplier_payments,
accounts, expenses, expense_categories, transaction_ledgers,
courier_providers, courier_bookings, customer_risk_profiles, fraud_checks,
audit_logs, storefront_settings, storefront_pages, carts
```

**নিয়ম:** প্রতিটা business record-কে অবশ্যই একটা company-র অন্তর্গত হতে হবে।

## 1.6 `BelongsToCompany` Trait

```php
namespace App\Models\Concerns;

use App\Models\Company;
use App\Scopes\CompanyScope;

trait BelongsToCompany
{
    protected static function bootBelongsToCompany(): void
    {
        static::creating(function ($model): void {
            if (! $model->company_id && app()->bound('company.context')) {
                $model->company_id = app('company.context')->id();
            }
        });

        static::addGlobalScope(new CompanyScope);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
```

**⚠️ গুরুত্বপূর্ণ রিস্ক নোট:** Global Scope multi-tenant সিস্টেমের সবচেয়ে common bug সোর্স — কোথাও `withoutGlobalScope()` ভুলে রয়ে গেলে এক company-র ডেটা আরেক company-তে leak করতে পারে। প্রতিটা নতুন query builder ব্যবহারের জায়গায় (queue job, scheduled command, report service) ম্যানুয়ালি verify করতে হবে যে company scope সঠিকভাবে apply হচ্ছে। টেস্টে অবশ্যই "company isolation leak test" থাকতে হবে — একটা company-র user অন্য company-র data access করতে পারছে না, এটা যাচাই করার জন্য।

## 1.7 Company Switcher (Filament)

```txt
Company: [Garments Machinery ▼]
```

Owner/Super Admin: "All Companies" সহ সব company select করতে পারবেন। Staff শুধু assigned company দেখবেন। Session key: `current_company_id`।

## 1.8 Invoice Numbering (Company-wise)

```txt
GM-20260621-0001   (Garments Machinery)
SOL-20260621-0001  (Solar Items)
GAD-20260621-0001  (Gadget Items)
GFT-20260621-0001  (Gift Items)
```

## 1.9 Migration Strategy (বিদ্যমান ডেটা)

```txt
1. companies টেবিল তৈরি
2. ডিফল্ট company তৈরি: "Main Company"
3. core টেবিলে company_id nullable যুক্ত
4. বিদ্যমান রেকর্ড Main Company ID দিয়ে backfill
5. নিরাপদ হলে company_id required করা
6. আসল companies তৈরি (Garments/Solar/Gadget/Gift)
7. সঠিক company-তে ডেটা সরানো
```

---

# Part 2: Courier Integration Module

(অপরিবর্তিত মূল আর্কিটেকচার — আগের প্ল্যান থেকেই)

## 2.1 Courier Gateway আর্কিটেকচার

```txt
CourierManager
├── CourierProviderInterface
├── ManualCourier Adapter      ← প্রথমে এটাই বানাতে হবে
├── SteadfastCourier Adapter
├── PathaoCourier Adapter
├── RedxCourier Adapter
└── Future Adapters
```

## 2.2 প্রধান টেবিল

```txt
courier_providers       — company_id, name, slug, credentials (encrypted), settings JSON
courier_bookings        — company_id, order_id, tracking_id, recipient info, cod_amount, status
courier_status_logs     — status change history
courier_webhook_logs    — raw webhook payload debugging
```

## 2.3 Internal Delivery Status (Normalized)

```txt
not_booked → booking_pending → booked → picked_up → in_transit
→ delivered / partial_delivered / returned / cancelled / failed
```

**নিয়ম:** Order status (`draft/confirmed/completed/cancelled`) আর Delivery status আলাদা রাখতে হবে — দুটো ভিন্ন workflow।

## 2.4 Priority Order

```txt
1. Manual Courier   — API ছাড়াই কাজ করে, আগে বানাতে হবে
2. Steadfast        — BD COD/e-commerce-এ জনপ্রিয়
3. Pathao           — strong merchant delivery
4. RedX             — সাধারণ e-commerce delivery
```

## 2.5 Filament Resources

```txt
CourierProviderResource, CourierBookingResource,
CourierStatusLogResource, CourierWebhookLogResource
```

Order resource-এ courier action: Create Booking, Track, Sync Status, Cancel Booking, Print Label, Mark Manual Delivered/Returned।

---

# Part 3: Customer Success & Risk Score (Fraud Detection)

(অপরিবর্তিত মূল আর্কিটেকচার)

## 3.1 কেন এই নাম

> UI-তে "Fraud" শব্দ এড়িয়ে **"Customer Success & Risk Score"** ব্যবহার করুন — কম আক্রমণাত্মক, legally নিরাপদ।

## 3.2 ফর্মুলা

```txt
Success Ratio = Delivered Orders / Total Courier Orders × 100
Return Ratio  = Returned Orders / Total Courier Orders × 100
Cancel Ratio  = Cancelled Orders / Total Courier Orders × 100
```

## 3.3 Risk Level ও Score Range

```txt
80–100  = Low Risk / Good Customer
50–79   = Medium Risk / Verify Before Shipping
0–49    = High Risk / Call Confirm বা Manager Approval প্রয়োজন
Blacklisted = Owner Approval ছাড়া Ship করা যাবে না
```

## 3.4 Rule-Based Scoring (ML না, শুরুতে এটাই)

```txt
Start Score = 100
- 30  যদি return ratio > 50%
- 20  যদি total orders > 2 এবং success ratio < 50%
- 15  যদি একই ফোন একাধিক নামে ব্যবহৃত
- 15  যদি high COD amount + first order
- 10  যদি address খুব ছোট/অসম্পূর্ণ
- 10  যদি সাম্প্রতিক duplicate order থাকে
- 20  যদি repeated cancellation থাকে
- 50  যদি phone/address blacklisted
```

## 3.5 মূল টেবিল

```txt
customer_risk_profiles   — company_id nullable, phone, success/return/cancel ratio, risk_score, risk_level
customer_risk_events     — event log (order_delivered, order_returned, blacklist_match...)
customer_blacklists      — company_id nullable (null = global blacklist)
fraud_checks             — প্রতি order-এর risk check history
```

**গুরুত্বপূর্ণ:** Phone-based risk **global** হতে পারে (company_id null), কিন্তু company-wise success ratio আলাদা থাকবে। একটা ফোন এক company-তে return করলে আরেক company অর্ডার নেওয়ার আগে alert পাবে।

## 3.6 Risk Check Workflow

```txt
Order Confirmed → Fraud/Risk Check
→ Low Risk: কুরিয়ার বুকিং অনুমোদিত
→ Medium Risk: কল কনফার্ম ওয়ার্নিং
→ High Risk: ম্যানেজার অনুমোদন প্রয়োজন
→ Blacklisted: মালিকের অনুমোদন প্রয়োজন
```

## 3.7 UI Placement

Order Create/View, Order Table, Customer View, Courier Booking Modal, Dashboard Alert Widget — সবখানে risk badge দেখানো।

---

# Part 4: Custom Lightweight Storefront — সম্পূর্ণ ERP থেকে ম্যানেজড

## 4.1 মূলনীতি

> **এক কোডবেস, এক ডেটাবেস, এক admin panel (Filament)। স্টোরফ্রন্ট কোনো আলাদা সিস্টেম নয় — এটা ERP-এর Public-facing extension মাত্র।**

```txt
┌──────────────────────────────────────────────┐
│           একই Laravel Application              │
├───────────────────┬────────────────────────────┤
│  /admin (Filament)   │   / (Public Storefront)    │
│  সম্পূর্ণ ERP কন্ট্রোল    │   Blade + Livewire + Alpine │
├───────────────────┴────────────────────────────┤
│         একই Models: Product, Order, Customer       │
│         একই Database, একই company_id scope         │
└──────────────────────────────────────────────┘
```

কোনো sync service লাগবে না, কোনো duplicate product/order মডেল লাগবে না, কোনো mapping table লাগবে না — যেটা Lunar-এর সাথে লাগতো।

## 4.2 Tech Stack

```txt
Backend:    Laravel 12 (বিদ্যমান)
Frontend:   Blade + Livewire 3 + Alpine.js
Styling:    Tailwind CSS 4 (বিদ্যমান, Filament-এর সাথে consistent)
Admin:      Filament 4 (বিদ্যমান প্যানেলেই storefront management যুক্ত হবে)
```

**কেন এই স্ট্যাক:** Filament নিজেই Livewire-এর উপর built — তাই আপনার team/agent দুই আলাদা framework শিখবে না, একই pattern সব জায়গায় চলবে।

## 4.3 ডেটাবেস — নতুন টেবিল (সব `company_id` সহ)

```txt
storefront_settings
--------------------
id, company_id, domain/subdomain, theme_color, logo, banner_images JSON,
whatsapp_number, meta_title, meta_description, is_published,
created_at, updated_at

storefront_pages
------------------
id, company_id, slug, title, content (markdown/blocks JSON),
is_published, created_at, updated_at
        — About Us, Wholesale Policy, Return Policy ইত্যাদির জন্য

carts
------
id, company_id, customer_id nullable, session_id nullable,
status (active/converted/abandoned), created_at, updated_at

cart_items
-----------
id, cart_id, product_id, quantity, unit_price_snapshot, created_at, updated_at

storefront_customers   (যদি আলাদা guard লাগে, নাহলে বিদ্যমান Customer মডেল ব্যবহার)
----------------------
id, company_id, customer_id, password, phone_verified_at,
created_at, updated_at

banners
--------
id, company_id, image, title, link_url, sort_order, is_active,
created_at, updated_at

coupons
--------
id, company_id, code, type (percentage/fixed), value, min_order_amount,
usage_limit, used_count, expires_at, is_active, created_at, updated_at
```

**Order মডেলে যুক্ত হবে:** `source` enum (`admin`, `storefront`) — যাতে বোঝা যায় কোন চ্যানেল থেকে অর্ডার এসেছে, কিন্তু stock/account/report লজিক সম্পূর্ণ অপরিবর্তিত থাকবে।

## 4.4 Routes Structure

```php
// routes/storefront.php (company subdomain বা path-based)

Route::domain('{company}.zamzamint.com')->group(function () {
    Route::get('/', HomeController::class);
    Route::get('/category/{slug}', CategoryController::class);
    Route::get('/product/{slug}', ProductController::class);
    Route::get('/cart', CartController::class);
    Route::get('/checkout', CheckoutController::class);
    Route::get('/account/orders', OrderHistoryController::class);
    Route::get('/track/{orderNo}', OrderTrackController::class);
});
```

**Multi-company routing সিদ্ধান্ত:** Subdomain-based (`gadget.zamzamint.com`, `garments.zamzamint.com`) সবচেয়ে পরিষ্কার — প্রতিটা company-র নিজস্ব ব্র্যান্ডিং অনুভূতি থাকবে, SEO-তেও আলাদা সাইটের মত আচরণ করবে।

## 4.5 Filament-এ Storefront ম্যানেজমেন্ট — নতুন Resource

```txt
app/Filament/Resources/
├── StorefrontSettingsResource/   — থিম, লোগো, ব্যানার, WhatsApp নম্বর
├── StorefrontPageResource/       — About, Policy পেজ এডিট
├── BannerResource/                — হোমপেজ স্লাইডার, drag-drop reorder
├── CouponResource/                — ডিসকাউন্ট কোড
└── CartResource/  (read-only)    — abandoned cart দেখার জন্য
```

Order resource-এ আগে থেকে যা আছে তাতে যুক্ত হবে: `source` কলাম (Admin/Storefront ব্যাজ), storefront থেকে আসা order-এ "অনলাইন অর্ডার" ট্যাগ।

---

## 4.6 মডার্ন প্রোফেশনাল UI/UX ডিজাইন

### Design System — Foundation

```txt
রঙ (Color Palette):
  Primary:   Deep Teal (#0F766E) — ট্রাস্ট, প্রিমিয়াম অনুভূতি
  Secondary: Warm Amber (#F59E0B) — Filament admin-এর Amber-এর সাথে consistency
  Success:   Emerald (#10B981) — স্টক আছে, অর্ডার কনফার্ম
  Danger:    Rose (#F43F5E) — স্টক নেই, বাকি পেমেন্ট
  Neutral:   Zinc grays — ব্যাকগ্রাউন্ড, বর্ডার

টাইপোগ্রাফি:
  Heading:  "Noto Sans Bengali" + "Inter" — দুই ভাষার জন্য সামঞ্জস্যপূর্ণ
  Body:     একই কম্বো, Regular weight
  সংখ্যা:    Tabular figures (দাম, পরিমাণ align রাখার জন্য)

স্পেসিং:
  8px বেস গ্রিড, generous white space, mobile-first
```

### প্রতিটা company-র জন্য আলাদা থিম, কিন্তু একই কাঠামো

```txt
companies.settings JSON-এ:
{
  "theme": {
    "primary_color": "#0F766E",
    "logo_url": "...",
    "font_heading": "Noto Sans Bengali"
  }
}
```

Filament-এর `StorefrontSettingsResource`-এ color picker দিয়ে owner নিজেই company-ভিত্তিক থিম পরিবর্তন করতে পারবেন — কোনো কোড পরিবর্তন না করেই।

### B2B-Specific UX সিদ্ধান্ত (সাধারণ B2C শপ থেকে ভিন্ন)

আপনার বিজনেস wholesale reseller-কেন্দ্রিক, তাই:

1. **Tiered Pricing Table** প্রতি প্রোডাক্ট পেজে prominent ভাবে:
   ```
   ১-৯ পিস      ৫০৳/পিস
   ১০-৪৯ পিস    ৪৫৳/পিস
   ৫০+ পিস      ৪০৳/পিস
   ```
2. **MOQ ব্যাজ** কার্টে যুক্ত করার আগেই দেখানো, MOQ-এর কম quantity দিলে input ব্লক
3. **WhatsApp এ অর্ডার বাটন** — "কার্টে যুক্ত করুন"-এর সমান গুরুত্বে রাখা, ক্লিক করলে প্রি-ফিল্ড মেসেজ সহ WhatsApp খুলবে
4. **কাস্টমার Due ভিজিবিলিটি** — লগইন করা কাস্টমার নিজের বর্তমান বাকি দেখতে পারবেন (বিদ্যমান `Customer::currentBalance()` থেকে সরাসরি)
5. **Quick Reorder** — পুরনো অর্ডার থেকে এক ক্লিকে আবার অর্ডার করার বাটন

### পেজ-ভিত্তিক ডিজাইন স্পেসিফিকেশন

**হোমপেজ**
- হিরো ব্যানার (স্লাইডার, Filament থেকে ম্যানেজড)
- "নতুন পণ্য" + "জনপ্রিয় পণ্য" সেকশন (horizontal scroll কার্ড, মোবাইলে swipe)
- ক্যাটাগরি গ্রিড (আইকন + নাম, ৪-৬ কলাম)
- ট্রাস্ট ব্যাজ: "সরাসরি চায়না থেকে" / "হোলসেল প্রাইস" / "৫০০+ রিসেলার পার্টনার"
- Sticky WhatsApp ফ্লোটিং বাটন (নিচে-ডানে, সব পেজে)

**ক্যাটাগরি/প্রোডাক্ট লিস্টিং পেজ**
- বাম সাইডবার ফিল্টার (দাম রেঞ্জ, ব্র্যান্ড, স্টক স্ট্যাটাস) — মোবাইলে bottom sheet
- Grid view, sort (নতুন/দাম কম-বেশি/জনপ্রিয়)
- প্রতি কার্ডে: ছবি, নাম, MOQ ব্যাজ, tiered price preview, স্টক স্ট্যাটাস

**প্রোডাক্ট ডিটেল পেজ**
- Image gallery (zoom-on-hover, মোবাইলে swipe gesture)
- Tiered pricing টেবিল prominently উপরে
- স্টক ব্যাজ (এভেলেবল/কামিং সুন/লিমিটেড)
- দুই সমান বাটন: "কার্টে যুক্ত করুন" + "WhatsApp এ অর্ডার করুন"
- নিচে: প্রোডাক্ট ডিটেলস ট্যাব, রিলেটেড প্রোডাক্ট

**কার্ট (Slide-in Drawer, পেজ লোড ছাড়া)**
- প্রতি লাইনে quantity stepper + MOQ ভ্যালিডেশন
- রিয়েল-টাইম সাবটোটাল (Livewire reactive)
- Sticky bottom CTA মোবাইলে

**চেকআউট (Single-page, accordion স্টেপ)**
- ধাপ ১: ঠিকানা (সেভ করা থেকে সিলেক্ট বা নতুন)
- ধাপ ২: পেমেন্ট মেথড (COD/bKash/ব্যাংক)
- ধাপ ৩: রিভিউ ও কনফার্ম
- প্রগ্রেস ইন্ডিকেটর, বড় ফর্ম ফিল্ড, মোবাইল কীবোর্ড-ফ্রেন্ডলি input type

**অর্ডার ট্র্যাকিং**
- ভিজ্যুয়াল স্ট্যাটাস টাইমলাইন (রঙ-কোডেড): Pending → Processing → Shipped → Delivered
- গেস্ট ট্র্যাকিং (লগইন ছাড়া অর্ডার নম্বর দিয়ে)
- কুরিয়ার ট্র্যাকিং ID এবং লাইভ ডেলিভারি স্ট্যাটাস (Part 2 courier module থেকে)

---

## 4.7 Storefront → ERP অর্ডার ফ্লো (কোনো sync লাগবে না)

```txt
কাস্টমার চেকআউট করল
→ সরাসরি ERP-এর Order + OrderItem মডেলে রেকর্ড তৈরি (source = 'storefront')
→ একই StockMovement workflow ট্রিগার হয় (যেটা admin order-এও হয়)
→ Fraud/Risk check রান হয় (Part 3 অনুযায়ী)
→ Courier booking উপলব্ধ হয় (Part 2 অনুযায়ী)
→ Customer due, account ledger স্বয়ংক্রিয়ভাবে আপডেট
→ Admin panel-এ এই অর্ডার সাথে সাথে দেখা যায়, কোনো delay/sync job ছাড়াই
```

**এটাই Lunar-এর তুলনায় সবচেয়ে বড় সুবিধা** — কোনো `LunarOrderSyncService`, কোনো duplicate prevention logic, কোনো webhook delay লাগবে না। Order তৈরি হওয়া মাত্রই এটা ERP-এর native অংশ।

## 4.8 Multi-Company + Storefront

```txt
Garments Machinery → garments.zamzamint.com (বা path: /garments)
Solar Items        → solar.zamzamint.com
Gadget Items       → gadget.zamzamint.com
Gift Items         → gift.zamzamint.com
```

প্রতিটা subdomain-এর জন্য একই Blade component/Livewire ব্যবহার হবে, কিন্তু `company_id` দিয়ে products, theme, banner আলাদা হবে — কোনো কোড ডুপ্লিকেশন ছাড়াই।

## 4.9 AI Agent দিয়ে কাজ করার নিয়ম (Hallucination কমানোর আসল উপায়)

### Hard Rules

```txt
একটা সময়ে একটা ফিচার (Cart, তারপর Checkout, তারপর Order History — আলাদা আলাদা)
বিদ্যমান Order/Product/Customer মডেল পরিবর্তন না করে নতুন relationship/scope যুক্ত করা
নতুন প্রতিটা ফিচারের জন্য টেস্ট লেখা বাধ্যতামূলক
company_id isolation bypass করা যাবে না
স্টক লজিক ডুপ্লিকেট করা যাবে না — বিদ্যমান StockMovement workflow পুনঃব্যবহার
```

### ভালো Prompt উদাহরণ

```txt
"Create a CartService that adds a product to the cart, validates MOQ
from the product's purchase tier settings, and recalculates subtotal.
Use the existing Product model. Add tests for MOQ validation failure
and successful add-to-cart."
```

### খারাপ Prompt উদাহরণ

```txt
"Build the complete storefront with cart, checkout, and payment."
```

(এটা একসাথে অনেক কিছু — agent context হারাবে, inconsistent pattern তৈরি করবে)

---

# Part 5: Combined Workflow (সম্পূর্ণ Native, কোনো Sync লেয়ার নেই)

```txt
কাস্টমার company subdomain ভিজিট করল
→ Product ERP থেকে সরাসরি লোড (কোনো sync delay নেই)
→ কার্টে যুক্ত করল (MOQ ভ্যালিডেশন সহ)
→ চেকআউট করল
→ ERP Order সরাসরি তৈরি (source = storefront)
→ Customer risk profile চেক হলো
→ Risk score ক্যালকুলেট হলো
→ স্টক ERP-এর মাধ্যমে validate হলো
→ Courier booking তৈরি হলো
→ Tracking ID সেভ হলো
→ ডেলিভারি স্ট্যাটাস sync হলো
→ Delivered/returned রেজাল্ট রেকর্ড হলো
→ Customer success ratio আপডেট হলো
→ Company-wise রিপোর্ট আপডেট হলো
→ মালিক গ্রুপ সামারি দেখতে পারলেন
```

---

# Part 6: Recommended Development Phases

## Phase 1: Multi-Company Foundation
```txt
companies টেবিল, company_user pivot, Company model, CompanyContext service,
CompanyScope, BelongsToCompany trait, company switcher, core টেবিলে company_id,
বিদ্যমান ডেটা backfill, সব resource/report/widget আপডেট
+ Company isolation leak test লেখা (গুরুত্বপূর্ণ)
```

## Phase 2: Courier Foundation
```txt
courier টেবিল, CourierProviderInterface, CourierManager, ManualCourier provider,
delivery_status যুক্ত করা order workflow-তে, Filament courier resources,
order courier action, courier reports
```

## Phase 3: Fraud / Customer Success MVP
```txt
customer risk টেবিল, CustomerRiskService, success/return ratio ক্যালকুলেশন,
risk score generation, order/customer-এ risk badge, courier booking-এর আগে warning,
blacklist management, risk reports
```

## Phase 4: Storefront Foundation (Lunar-এর পরিবর্তে)
```txt
storefront_settings, storefront_pages, carts, cart_items, banners, coupons টেবিল
রুট স্ট্রাকচার (subdomain/path-based)
HomeController, CategoryController, ProductController — Livewire component
Design system সেটআপ (Tailwind config, color tokens, typography)
Filament-এ StorefrontSettingsResource, BannerResource
```

## Phase 5: Cart & Checkout
```txt
CartService (MOQ validation, tiered pricing লজিক)
CheckoutController, Order creation সরাসরি ERP Order মডেলে (source=storefront)
Customer registration/login (আলাদা guard, Filament admin guard-এর সাথে conflict-মুক্ত)
Address বুক, order confirmation
```

## Phase 6: Order Tracking & Account Pages
```txt
Order history পেজ, guest tracking by order number
ডেলিভারি স্ট্যাটাস টাইমলাইন UI (courier module-এর সাথে যুক্ত)
Customer due visibility, quick reorder
```

## Phase 7: Courier API Adapters
```txt
SteadfastCourier, PathaoCourier, RedxCourier adapter
API credential settings, booking API, tracking API, webhook handler
```

## Phase 8: Advanced Risk & Approval Workflow
```txt
High-risk order-এ manager approval, blacklisted customer-এ owner approval,
duplicate order detection, same-phone-multiple-name detection,
high-COD-first-order detection, risk rules manager
```

## Phase 9: Storefront Polish & Advanced Features
```txt
WhatsApp order button integration, abandoned cart recovery (SMS/WhatsApp reminder)
প্রোডাক্ট রিভিউ, রিলেটেড প্রোডাক্ট, উইশলিস্ট
Coupon system UI, banner management সম্পূর্ণ
```

## Phase 10: Group Dashboard & Advanced Reports
```txt
Company-wise sales/purchase/profit/stock value summary
Courier success by company, return rate by company
All-company dashboard widget, storefront performance by company
```

---

# Part 7: Suggested Navigation Structure (Filament)

```txt
Dashboard

Company Management
├── Companies
├── Company Users
└── Company Settings

Storefront
├── Storefront Settings (থিম, লোগো, ডোমেইন)
├── Pages (About, Policy)
├── Banners
├── Coupons
└── Abandoned Carts

Sales
├── Customers
├── Orders (source ব্যাজ: Admin/Storefront)
└── Customer Payments

Courier & Delivery
├── Courier Bookings
├── Courier Providers
├── Delivery Status Logs
└── Webhook Logs

Customer Risk
├── Risk Profiles
├── Fraud Checks
├── Blacklist
└── Risk Events

Inventory
├── Products
├── Categories
└── Stock Movements

Purchasing
├── Suppliers
├── Purchases
└── Supplier Payments

Accounts
├── Accounts
├── Expenses
└── Transaction Ledger

Reports
├── Sales Report
├── Storefront Report
├── Courier Report
├── Risk Report
└── Group Report (Company-wise তুলনা)

System
├── Users
├── Roles
├── Audit Logs
└── Backups
```

---

# Part 8: MVP Priority Summary

## Must Build First
```txt
Multi-company foundation + isolation test
Manual courier booking
Customer success ratio + risk score + blacklist
Storefront foundation (settings, theme, routing)
```

## Build Second
```txt
Cart & checkout (native, no sync layer)
Storefront order → ERP order flow
Steadfast/Pathao/RedX API adapter
High-risk order approval
Group dashboard
```

## Build Later
```txt
Advanced courier cost comparison, auto courier recommendation
Abandoned cart recovery automation
Customer mobile portal / PWA
AI-based risk prediction (rule-based যথেষ্ট না হলে)
```

---

# Part 9: Product Positioning

> **Multi-Company Business Dashboard with native ERP-managed Storefront, Courier Automation, COD Risk Detection, and Customer Success Management for Bangladesh Businesses.**

বাংলা পজিশনিং:

> **একই ড্যাশবোর্ড থেকে একাধিক কোম্পানির স্টক, বিক্রয়, ক্রয়, নিজস্ব অনলাইন স্টোরফ্রন্ট, কুরিয়ার, কাস্টমার রিস্ক, বাকি এবং রিপোর্ট ম্যানেজ করার সম্পূর্ণ বিজনেস সিস্টেম — কোনো থার্ড-পার্টি ই-কমার্স প্ল্যাটফর্মের নির্ভরতা ছাড়াই।**

---

# Part 10: Final Implementation Recommendation

```txt
1. Multi-Company System + Isolation Testing
2. Manual Courier Integration
3. Customer Success Ratio + Risk Score
4. Storefront Foundation (settings, routing, design system)
5. Cart & Checkout (native ERP order creation)
6. Order Tracking + Customer Account Pages
7. Courier API Adapters (Steadfast → Pathao → RedX)
8. High-Risk Approval Workflow
9. Storefront Polish (WhatsApp, abandoned cart, reviews)
10. Group Dashboard & Advanced Reports
```

**কেন এই ক্রম:**
```txt
Multi-company প্রথমে আসবে কারণ courier, risk, storefront-এর সব ডেটা সঠিক company-র হতে হবে।
Manual courier API courier-এর আগে কারণ এটা তাৎক্ষণিকভাবে কাজ করে।
Risk score courier booking-এর আগে যুক্ত থাকবে।
Storefront foundation ছোট ছোট ধাপে — settings → routing → cart → checkout — যাতে AI agent প্রতি ধাপে স্পষ্ট স্কোপ পায়।
API adapter একে একে যুক্ত হবে internal architecture স্থিতিশীল হওয়ার পর।
```

---

## Final Vision

```txt
Multi-Company Business OS
├── Company-wise ERP
├── Inventory & Stock
├── Sales & Purchase
├── Accounts & Ledger
├── Native ERP-Managed Storefront (কোনো থার্ড-পার্টি প্যাকেজ নেই)
├── Courier Booking & Tracking
├── COD Risk Detection
├── Customer Success Ratio
├── Company-wise Reports
├── Group Owner Dashboard
└── Future Mobile/PWA Layer
```

এই পরিকল্পনা ZamZam Business Dashboard-কে একটা সম্পূর্ণ স্বয়ংসম্পূর্ণ, নিরাপদ, এবং সম্পূর্ণ নিয়ন্ত্রণযোগ্য Bangladesh-market-ready Business OS-এ রূপান্তরিত করবে — যেখানে স্টোরফ্রন্ট কোনো আলাদা সিস্টেম নয়, বরং ERP-এরই একটা স্বাভাবিক সম্প্রসারণ।

---

# Part 11: ভবিষ্যৎ মডিউল ও Build Order (এজেন্টের জন্য বাধ্যতামূলক নির্দেশনা)

## ⚠️ এজেন্ট/ডেভেলপারের জন্য কঠোর নিয়ম

> **এই Part 11-এ বর্ণিত কোনো মডিউলের একটা লাইন কোডও লেখা শুরু করবেন না, যতক্ষণ না Part 1 থেকে Part 10-এ বর্ণিত কাজ (Multi-Company, Courier, Fraud/Risk, Storefront) সম্পূর্ণভাবে শেষ এবং production-এ স্থিতিশীল না হয়।**

কারণ:

```txt
Multi-company foundation শেষ না হলে নতুন মডিউলে company_id isolation ভুল হবে।
Storefront এবং Courier শেষ না হলে business-এর core revenue flow অস্থিতিশীল থাকবে।
একসাথে ৫-৬টা বড় সিস্টেম শুরু করলে কোনোটাই production-ready হবে না — scope creep সবচেয়ে বড় ঝুঁকি।
এজেন্ট যদি Part 11 আগেভাগে শুরু করে, তাহলে এটা context window-এ confusion তৈরি করবে এবং Part 1-10-এর কাজে ভুল লজিক মিশে যেতে পারে।
```

এজেন্ট যদি কখনো এই ডকুমেন্ট পড়ে কাজ শুরু করার আগে নিশ্চিত না হয় Part 1-10 সম্পূর্ণ হয়েছে কিনা, তাহলে তার প্রথম কাজ হবে **ব্যবহারকারীকে জিজ্ঞেস করা** — অনুমান করে কাজ শুরু করবে না।

---

## 11.1 Build Order — সম্পূর্ণ Sequencing

```txt
পর্যায় ১ (এখন — অগ্রাধিকার, Part 1-10 অনুযায়ী):
  Multi-Company Foundation
  Manual Courier Integration
  Customer Success Ratio + Risk Score
  Storefront Foundation → Cart → Checkout
  Courier API Adapters (Steadfast/Pathao/RedX)
  Group Dashboard

পর্যায় ২ (Part 1-10 সম্পূর্ণ ও স্থিতিশীল হওয়ার পরেই শুরু):
  ১১.২ — Lead/CRM Module    ← আগে এটা
  ১১.৩ — Investor/Mudarabah Module    ← এটা পরে
```

**কেন Lead/CRM আগে, Investor পরে — এই ক্রমের যুক্তি:**

```txt
Lead/CRM module ছোট স্কোপ — বিদ্যমান Customer মডেলের customer_source ফিল্ডের
  স্বাভাবিক সম্প্রসারণ মাত্র, নতুন কোনো জটিল financial calculation নেই।

Investor/Mudarabah module আর্থিকভাবে স্পর্শকাতর (profit-sharing settlement,
  ইসলামী শরীয়াহ ক্যালকুলেশন) — ভুল হলে real money এবং trust-এর ক্ষতি হয়।
  তাই এটা সবচেয়ে stable অবস্থায় কোডবেস থাকা অবস্থায় শুরু করা উচিত,
  এবং Lead/CRM-এর তুলনায় বেশি manual testing/owner review লাগবে।

Lead/CRM থেকে আসা নতুন কাস্টমার ও অর্ডার ভলিউম বাড়লে তখন বোঝা যাবে
  বিজনেস আসলে কতটা বড় হচ্ছে — এবং সেই বাস্তব ডেটা থেকেই Investor module-এর
  Project-ভিত্তিক profit-sharing হিসাব করা সহজ ও বাস্তবসম্মত হবে।
```

---

## 11.2 Lead/CRM Module (পর্যায় ২ — প্রথমে এটা)

### উদ্দেশ্য

Facebook/WhatsApp/ফোন থেকে আসা inquiry ট্র্যাক করা এবং Lead থেকে Customer/Order-এ convert করার flow।

### কেন এটা স্বাভাবিক সম্প্রসারণ

বিদ্যমান `Customer` model-এ already `customer_source` ফিল্ড আছে (walk-in, facebook, website, referral, phone_call, other) — তাই Lead module এই বিদ্যমান প্যাটার্নের উপরেই তৈরি হবে, নতুন কোনো ভিন্ন ডেটা মডেল ভাবনার দরকার নেই।

### নতুন টেবিল (company_id সহ — Part 1 Multi-Company-র উপর নির্ভরশীল)

```txt
leads
------
id, company_id, name, phone, email, source (facebook/whatsapp/website/
referral/walk_in/phone_call/other), status (new/contacted/quoted/won/lost),
interest, estimated_value, assigned_to (user_id), next_follow_up_at,
converted_customer_id nullable, converted_order_id nullable, note,
created_by, created_at, updated_at

lead_activities
-----------------
id, lead_id, user_id, type (call/message/note/meeting), note,
next_action_at, created_at, updated_at

quotations
-----------
id, company_id, lead_id nullable, customer_id nullable, quotation_number,
status (draft/sent/accepted/rejected/expired), valid_until, total_amount,
converted_order_id nullable, created_by, created_at, updated_at

quotation_items
------------------
id, quotation_id, product_id, quantity, unit_price, subtotal
```

### Flow

```txt
Lead তৈরি (Facebook/WhatsApp/ফোন থেকে)
→ Lead Activity যুক্ত (ফলো-আপ নোট)
→ Quotation তৈরি ও পাঠানো (WhatsApp শেয়ারযোগ্য লিংক)
→ Quotation accepted হলে → বিদ্যমান Order মডেলে convert
→ Lead status "won" → converted_customer_id ও converted_order_id সেভ
→ এর পরের পুরো flow (stock, payment, courier, risk) অপরিবর্তিত — Part 1-10-এর existing logic ব্যবহার হবে
```

### Filament Resources

```txt
LeadResource          — Kanban-style status board (New → Contacted → Quoted → Won/Lost)
QuotationResource      — Quotation তৈরি, PDF/শেয়ারযোগ্য লিংক জেনারেট
```

### স্পষ্টভাবে বাদ (এই মডিউলে যুক্ত হবে না)

```txt
Task/Approval Workflow সিস্টেম — এটা একটা সম্পূর্ণ আলাদা পঞ্চম সিস্টেম,
  Lead/CRM-এর সাথে মেশানো হবে না। প্রয়োজন হলে এটা একদম আলাদা ভবিষ্যৎ
  ডকুমেন্টে আলোচনা হবে, এই প্ল্যানের অংশ নয়।
```

---

## 11.3 Investor / Mudarabah Module (পর্যায় ২ — Lead/CRM-এর পরে)

### উদ্দেশ্য

ইসলামী শরীয়াহ-ভিত্তিক Mudarabah মডেলে প্রতি ২/৬/১২ মাসে ওপেন করা প্রজেক্টে একাধিক investor-এর বিনিয়োগ ও মুনাফা বণ্টন ব্যবস্থাপনা।

### বণ্টন নীতি (নিশ্চিত করা)

```txt
মোট মুনাফার ১০০%
├── Investor Pool: ৫০%  (প্রতি investor তার বিনিয়োগ অনুপাতে ভাগ পাবেন)
├── Channel Partner: ১০%  (যার মাধ্যমে investor এসেছেন)
└── Company Net: ৪০%
```

**কোনো সুদ/ব্যাংকিং ইন্টারেস্ট-ভিত্তিক হিসাব থাকবে না — পুরোপুরি profit-loss sharing (Mudarabah)।**

### নতুন টেবিল (company_id সহ)

```txt
investment_projects
----------------------
id, company_id, name, description, duration_type (2_month/6_month/12_month),
start_date, end_date, target_amount, status (open/running/closed/settled),
created_at, updated_at

investors
-----------
id, company_id, name, phone, address, channel_partner_id nullable (অন্য investor-ও হতে পারেন),
created_at, updated_at

investments
-------------
id, project_id, investor_id, amount, payment_method, invested_at,
created_at, updated_at

project_settlements
----------------------
id, project_id, total_revenue, total_cost, net_profit,
investor_pool_amount (৫০%), channel_partner_amount (১০%), company_net_amount (৪০%),
settled_at, settled_by, created_at, updated_at

settlement_payouts
---------------------
id, settlement_id, investor_id, investment_amount, profit_share_amount,
total_payout (মূলধন + মুনাফা), paid_at, payment_method, created_at, updated_at
```

### Settlement Calculation Logic

```txt
Net Profit = Total Revenue − Total Cost (purchase + project-related expense)

Investor Pool = Net Profit × 50%
প্রতি Investor-এর Profit Share = (তার Investment Amount / মোট Investment Amount) × Investor Pool

Channel Partner Share = Net Profit × 10%
Company Net = Net Profit × 40%

প্রতি Investor-এর Final Payout = তার Investment Amount (মূলধন ফেরত) + তার Profit Share
```

### Filament Resources

```txt
InvestmentProjectResource   — প্রজেক্ট তৈরি, status track, fund progress bar
InvestorResource             — investor profile, তাদের সব project-এর investment history
InvestmentResource            — প্রতি project-এ কে কত দিয়েছেন
ProjectSettlementResource     — settlement calculate ও payout breakdown দেখানো (read-heavy, action: "Calculate & Settle")
```

### ⚠️ আর্থিক স্পর্শকাতরতা — অতিরিক্ত সতর্কতা প্রয়োজন

```txt
Settlement calculation-এর প্রতিটা formula-র জন্য আলাদা unit test লিখতে হবে।
Settlement একবার "settled" status হয়ে গেলে amount আর edit করা যাবে না —
  ভুল হলে reversal/adjustment entry আলাদাভাবে তৈরি করতে হবে, সরাসরি edit না।
যেকোনো settlement payout করার আগে owner-level manual approval/double-check
  বাধ্যতামূলক রাখা উচিত (যদিও এই প্ল্যানে আলাদা Approval Workflow সিস্টেম নেই,
  এই একটা specific জায়গায় simple "settled_by" + confirmation field দিয়ে accountability রাখা হয়েছে)।
```

### পূর্বশর্ত (Investor module শুরু করার আগে এগুলো নিশ্চিত থাকতে হবে)

```txt
Multi-Company system সম্পূর্ণ এবং company isolation টেস্ট পাস করেছে
  (Investor project অবশ্যই সঠিক company-র সাথে যুক্ত হতে হবে)
Account/Ledger module স্থিতিশীল (settlement payout ledger entry তৈরি করবে)
Lead/CRM module থেকে অন্তত একটা পূর্ণ business cycle (lead → order → delivery) দেখা গেছে,
  যাতে investor-দের দেখানোর মতো বাস্তব profit/revenue ডেটা থাকে
```

---

## 11.4 চূড়ান্ত সম্পূর্ণ ক্রম (Part 1 থেকে Part 11 — One Single Timeline)

```txt
১.  Multi-Company Foundation + Isolation Test
২.  Manual Courier Integration
৩.  Customer Success Ratio + Risk Score
৪.  Storefront Foundation (settings, routing, design system)
৫.  Cart & Checkout (native ERP order creation)
৬.  Order Tracking + Customer Account Pages
৭.  Courier API Adapters (Steadfast → Pathao → RedX)
৮.  High-Risk Approval Workflow
৯.  Storefront Polish (WhatsApp, abandoned cart, reviews)
১০. Group Dashboard & Advanced Reports
─────────────── এই পর্যন্ত Part 1-10, এখনকার অগ্রাধিকার ───────────────
১১. Lead/CRM Module (Lead → Quotation → Order convert)
১২. Investor/Mudarabah Module (Project → Investment → Settlement)
```

**এজেন্টের জন্য সংক্ষিপ্ত নির্দেশ:** যদি আপনাকে এই ডকুমেন্ট দেখিয়ে কোনো কাজ করতে বলা হয় এবং স্পষ্ট না থাকে কোন ধাপে আছি, তাহলে ব্যবহারকারীকে জিজ্ঞেস করুন "Part 1-10-এর কোন ধাপ পর্যন্ত সম্পূর্ণ হয়েছে?" — এর উত্তর পাওয়ার আগে ১১ বা ১২ নম্বর ধাপের কোনো কাজ শুরু করবেন না।
