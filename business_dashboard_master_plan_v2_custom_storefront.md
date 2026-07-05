# Business Dashboard: Courier Integration, Fraud Detection, Multi-Company & Custom Storefront — Master Plan (v2)

## Document Purpose

এই ডকুমেন্ট **Business Dashboard** (Laravel + Filament ERP) সিস্টেমের জন্য চারটা বড় আপগ্রেডের সম্পূর্ণ পরিকল্পনা:

1. **Multi-Company System**
2. **Courier Integration Module**
3. **Customer Success & Risk Score (Fraud Detection) Module**
4. **Custom Lightweight Storefront — সম্পূর্ণ ERP থেকে ম্যানেজড E-Commerce**

> **v1 থেকে পরিবর্তন:** Lunar e-commerce package সরিয়ে দেওয়া হয়েছে। কারণ ও যুক্তি নিচের "কেন Lunar নয়" সেকশনে আছে। এর বদলে একটা **native Blade + Livewire storefront** ডিজাইন করা হয়েছে যা সম্পূর্ণভাবে এই একই ERP কোডবেস ও Filament প্যানেল থেকে নিয়ন্ত্রিত হবে — কোনো আলাদা package, আলাদা admin panel, বা sync layer ছাড়াই।

> **v2 আপডেট (স্ট্যাটাস ভেরিফিকেশন):** বিদ্যমান রিপোর `ERP_PHASE_ROADMAP.md` ও `PROJECT_GUIDE.md` সরাসরি পড়ে এবং ব্যবহারকারীর সরাসরি ফিডব্যাক থেকে যাচাই করা হয়েছে যে **Part 1 (Multi-Company)-এর আর্কিটেকচার নতুন installation-এর জন্য কার্যত সম্পূর্ণ** — কিন্তু **বিদ্যমান production data এখনো "Main Company"-তে আছে এবং ৪টা আসল company-তে ভাগ করা হয়নি**, এবং queue/scheduled command/export/backup-এর cross-cutting isolation audit বাকি। **Part 2 (Courier)**-এর Manual ও Steadfast booking flow সম্পন্ন, কিন্তু পরিকল্পিত architecture (CourierManager/CourierProviderInterface), webhook reliability, এবং Pathao/RedX/E-Courier adapter এখনো বাকি। তাই ডকুমেন্টের শুরুতে নতুন **"Part 0: Pre-requisite ERP Stabilization"** যুক্ত করা হয়েছে (repo roadmap-এর Phase 9 কভার করে), এবং Part 1/Part 2-এ বিস্তারিত ✅ সম্পন্ন / ❌ বাকি তালিকা যুক্ত হয়েছে।

> **v2 সংশোধনী (Domain Routing):** Part 4 (Storefront)-এ আগে ভুলভাবে subdomain-based routing (`gadget.zamzamint.com`) লেখা ছিল। ব্যবহারকারী নিশ্চিত করেছেন প্রতিটা company-র **সম্পূর্ণ আলাদা, বিদ্যমান custom domain** আছে (`tasneemknitindustry.com`, `noorsolaren.com`, `zamzamgadgetbd.com`, `zamzamint.com`), এবং ভবিষ্যতে নতুন company নতুন domain সহ যুক্ত করার সক্ষমতা প্রয়োজন। তাই Part 4.4 সম্পূর্ণ rewrite করে একটা **`ResolveCompanyFromDomain` middleware** ভিত্তিক ডিজাইন যুক্ত করা হয়েছে, যা route definition-এ কোনো company hardcode না করে runtime-এ ডেটাবেস lookup করে domain থেকে company শনাক্ত করে — ফলে নতুন company যুক্ত করতে কোনো কোড পরিবর্তন বা নতুন deployment লাগবে না।

> **v3 আপডেট (সরাসরি কোড-ভিত্তিক যাচাই, latest `PROJECT_GUIDE.md` থেকে):** ব্যবহারকারীর আপলোড করা latest `PROJECT_GUIDE.md` সরাসরি পড়ে Part 1 ও Part 2-এর implementation বিস্তারিতভাবে কনফার্ম করা হয়েছে — exact ফাইল পাথ, model নাম, এবং কোডে থাকা safeguard (cross-company courier reject, "All Companies" view-এ write action disable) সব যুক্ত হয়েছে। `tests/Feature/MultiCompanyIsolationTest.php` ও `CourierIntegrationTest.php` বাস্তবে exist করে এটা কনফার্ম হয়েছে (আগে "কোনো leak-test নেই" ভুলভাবে লেখা ছিল)। `.env`/`composer.json`-এর কিছু আগের ক্রিটিকাল সমস্যা (ADMIN_PASSWORD, MAIL_FROM_ADDRESS, session/queue/cache driver) এখন ঠিক হয়ে গেছে বলে কনফার্ম হয়েছে। একটা সম্পূর্ণ নতুন **"Release and Update Safety"** সিস্টেম (Part 0.3) আবিষ্কৃত হয়েছে যা ভবিষ্যতের সব বড় migration/launch-এর জন্য backup ও changelog নিয়ম বাধ্যতামূলক করে। Part 4 (Storefront)-এ নিশ্চিত করা হয়েছে যে এই অংশ এখনো সম্পূর্ণ কোডবিহীন — তাই Part 4-এর ডিজাইন অপরিবর্তিত থাকবে।

> **v4 সংশোধনী (Shopify-Style UI, Filament থেকে আলাদা):** একটা প্রাথমিক storefront প্রোটোটাইপ (`app.zamzamint.com/storefront`) দেখতে Filament admin panel-এর মতো হয়ে গিয়েছিল — ব্যবহারকারী এটা প্রত্যাখ্যান করেছেন এবং Shopify-এর মতো e-commerce layout/flow চেয়েছেন। Part 4.6 সম্পূর্ণ rewrite করা হয়েছে: (১) একটা **HARD RULE** যুক্ত হয়েছে যে storefront কখনো Filament-এর কোনো component/CSS class পুনরায় ব্যবহার করবে না (এটাই মূল bug-এর কারণ ছিল), (২) Filament-এর Amber রঙ storefront primary color থেকে সরানো হয়েছে, (৩) Shopify-style নির্দিষ্ট component pattern (বড় product card, top navbar, sticky cart drawer, two-column checkout) যুক্ত হয়েছে, (৪) **Light/Dark mode টগল বাধ্যতামূলক** করা হয়েছে (আগে শুধু dark mode ছিল)। Vengeance UI (animation library) বিবেচনা করা হয়েছিল কিন্তু ব্যবহারকারী নিশ্চিত করেছেন যে শুধু Shopify-style layout/flow দরকার, animation/interaction effect গুরুত্বপূর্ণ না — তাই এটা স্কোপের বাইরে রাখা হয়েছে।

> **v5 বড় আপডেট (ব্যবহারকারীর সরাসরি আপলোড করা লেটেস্ট `PROJECT_GUIDE.md` থেকে, ২০২৬-০৭):** এই আপডেটে বিশাল পরিমাণ কাজ "সম্পন্ন" হিসেবে চিহ্নিত হয়েছে যা আগের ভার্সনে "বাকি" ছিল। মূল পরিবর্তনসমূহ: **(১) Part 1** — data migration tool (`companies:migrate-data`) তৈরি হয়ে গেছে, কিন্তু ব্যবসায়িক সিদ্ধান্ত বদলেছে: bulk migration করা হবে না, নতুন এন্ট্রি সরাসরি সঠিক company-তে হবে; isolation leak-test matrix এখন সব company-owned model কভার করে বলে কোডে confirmed। **(২) Part 0** — Shipment ও Container tracking সম্পন্ন। **(৩) Part 3 (Fraud/Risk)** — সম্পূর্ণ implement, আগে এটা শুধু ডিজাইন ছিল। **(৪) Part 4 (Storefront)** — সবচেয়ে বড় পরিবর্তন: domain routing, cart, checkout, order tracking, customer account, content pages, dark/light mode toggle (vanilla JS, Alpine/Livewire না — এটা Tech Stack সেকশনে সংশোধন করা হয়েছে), product sort/filter, related products, editable homepage hero — সব বাস্তবায়িত। বাকি শুধু: WooCommerce migration (Part 12), production domain go-live, এবং Part 4.6-এর "Top-Class Reference Pattern" (mega menu ইত্যাদি) যা এখনো শুধু ডিজাইন। এই আপডেটে আরও লক্ষ্য করা গেছে যে storefront order ইচ্ছাকৃতভাবে `draft` থাকে এবং admin review না করা পর্যন্ত stock deduct হয় না — এটা একটা গুরুত্বপূর্ণ ব্যবসায়িক নিয়ন্ত্রণ যা আগে ডকুমেন্টেড ছিল না।

**Investor/Mudarabah Module ও Lead/CRM Module:** এই দুটো এখনকার চারটা প্রধান কাজের স্কোপের বাইরে রাখা হয়েছে। ডকুমেন্টের শেষে **"Part 11: ভবিষ্যৎ মডিউল ও Build Order"** সেকশনে এগুলোর বিস্তারিত প্ল্যান এবং ঠিক কখন এগুলো শুরু করতে হবে তার স্পষ্ট নির্দেশনা দেওয়া আছে। **এজেন্ট/ডেভেলপারের জন্য নির্দেশ: Part 1-10 সম্পূর্ণ না হওয়া পর্যন্ত Part 11-এর কোনো কাজ শুরু করবেন না।**

**WooCommerce Data Migration:** ব্যবহারকারীর বর্তমান ৪টা কোম্পানির ওয়েবসাইট WooCommerce-এ চলছে, যেখানে real customer ও product ডেটা আছে। এই ডেটা হারানো চলবে না। সংক্ষিপ্ত নোট **"Part 12: WooCommerce Data Migration (সংক্ষিপ্ত নোট)"**-এ ডকুমেন্টের শেষে রাখা হয়েছে — Part 4 (Storefront Foundation) শুরু হওয়ার সময় এই নোট পূর্ণ বিস্তারিত পরিকল্পনায় রূপান্তরিত হবে। **এই নোট না পড়ে Part 4 শুরু করবেন না, কারণ WooCommerce থেকে ডেটা না আনলে storefront চালু হওয়ার আগেই পুরনো customer/product ডেটা হারিয়ে যাওয়ার ঝুঁকি থাকবে।**

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

# Part 0: Pre-requisite ERP Stabilization (Repo Roadmap Phase 9 অনুযায়ী)

> **⚠️ এজেন্টের জন্য কঠোর নির্দেশ:** বিদ্যমান রিপোর `ERP_PHASE_ROADMAP.md`-এ স্পষ্টভাবে লেখা আছে যে Phase 10 (E-commerce/Storefront) শুরু করার পূর্বশর্ত হলো Phase 9 (Production Operations)-এর আইটেমগুলো স্থিতিশীল হওয়া। এই Master Plan-এর Part 4 (Custom Storefront)-এ যাওয়ার আগে নিচের আইটেমগুলো repo-তে আসলে সম্পন্ন হয়েছে কিনা যাচাই করুন। Part 1 ও Part 2 (Multi-Company, Courier)-এর অধিকাংশ কাজ ইতিমধ্যে সম্পন্ন হওয়ার কারণে এই Part 0-এর কাজ তাদের **সাথে সমান্তরালে বা তার ঠিক পরে** করা যেতে পারে, কিন্তু Part 4 শুরুর আগে এই Part 0 সম্পূর্ণ হওয়া আবশ্যক।

## 0.1 repo roadmap অনুযায়ী অবশিষ্ট Pre-requisite আইটেম

```txt
[✅] Per-product landed cost allocation — কোডে যাচাই করা হয়েছে (2026-07-05)।
    `PurchaseWorkflowService::syncLandedCosts()` China-to-BD extra cost
    প্রতিটা purchase item-এ subtotal-অনুপাতে allocate করে
    (`allocated_cost`, `landed_unit_cost` কলাম), rounding remainder শেষ
    item-এ যায়, এবং `update_cost_price` চালু থাকলে received purchase-এ
    product-এর `cost_price` landed unit cost দিয়ে আপডেট হয়। টেস্ট:
    `PurchaseTest::purchase landed cost is distributed by item subtotal`।
[✅] Shipment ও container tracking — সম্পন্ন ও কোডে যাচাই করা হয়েছে।
    Company-scoped containers (container number, shipping line, route,
    lifecycle status, estimated/actual departure-arrival) ও shipments
    (Purchase/Container link, carrier, transport mode, tracking number,
    status, dates) দুটোই আছে। Shipment/Container resource sidebar-এ
    আলাদা না রেখে ইচ্ছাকৃতভাবে Purchase View/Edit page-এর ভেতরে embed
    করা হয়েছে। Draft purchase-এ shipment planning সম্ভব, received
    purchase-এ read-only logistics history দেখা যায়।
[✅] PDF export — কোডে যাচাই করা হয়েছে (2026-07-05)। Invoice PDF
    (`OrderPdfController`, route `orders.pdf`) এবং report PDF — sales,
    purchases সহ সব report type — (`ReportPdfController`, route
    `reports.export.pdf`, dompdf দিয়ে) দুটোই আছে। টেস্ট: `ReportsTest`-এ
    "sales report exports pdf" ও "invoice exports pdf"।
[✅] Backup safety নিয়ম সম্পন্ন (যদিও পূর্ণ automated backup package নয়) —
    production deployment-এ migration-এর আগে database backup বাধ্যতামূলক
    নিয়ম কোডবেসে ডকুমেন্টেড আছে (Part 0.3 দেখুন), এবং data migration
    command নিজেই automatic backup তৈরি করে ও `--no-backup` production-এ
    reject করে (দেখুন Part 1.9)। Routine scheduled backup এখন কনফার্ম
    (2026-07-05): নিজস্ব `DatabaseBackupService` + `backup:database`
    কমান্ড scheduler-এ দৈনিক 02:00-এ চলে (bootstrap/app.php), পুরনো
    ব্যাকআপ auto-cleanup হয়, `backup:verify` কমান্ড disposable SQLite-এ
    restore drill চালায়, admin panel থেকে download করা যায় (permission
    সহ), Google Drive upload অপশনও আছে — `BackupSystemTest` কভার করে।
    spatie/laravel-backup দরকার নেই।
[✅] Final production hosting/domain — Coolify + Nixpacks deployment flow
    সম্পূর্ণভাবে ডকুমেন্টেড, production env variable সেট নিশ্চিত
    (SESSION_DRIVER=file, CACHE_STORE=file, QUEUE_CONNECTION=sync ছোট
    install-এর জন্য, বড় install-এ database/redis-এ migrate করা যায়)
[ ] npm run build সমস্যা — এখনো নিশ্চিতভাবে যাচাই করা যায়নি এই
    ডকুমেন্টেশন থেকে, তবে "Testing Checklist"-এ `npm run build`
    handoff-এর আগে বাধ্যতামূলক ধাপ হিসেবে আছে
[✅] Manual purchase costing smoke test — সম্পন্ন, Testing Checklist-এর
    ধাপ ৪-৭-এ China-to-BD cost ও purchase total/due calculation যাচাইয়ের
    নির্দিষ্ট নির্দেশনা আছে
```

## 0.2 আগের Code Review থেকে এখনো-অনিশ্চিত ক্রিটিকাল/হাই-প্রায়োরিটি আইটেম

আগের code review-এ (`BUSINESS_DASHBOARD_REVIEW.md`) যে ক্রিটিকাল সমস্যাগুলো চিহ্নিত হয়েছিল, সেগুলো এখনো ঠিক হয়েছে কিনা এই Part 0-এর অংশ হিসেবে যাচাই করা জরুরি — কারণ Multi-Company বা Storefront-এর মতো বড় কাজ শুরু করার আগে এই ভিত্তিগত সমস্যাগুলো (যেমন `composer.json`-এর `block-insecure: false`, `minimum-stability: dev`, session/queue/cache সব database driver হওয়া) অমীমাংসিত থাকলে নতুন কাজের উপর সরাসরি প্রভাব পড়বে।

```txt
[✅] .env.example-এ ADMIN_PASSWORD খালি থাকার সমস্যা — ঠিক হয়েছে, কনফার্ম করা হয়েছে।
    PROJECT_GUIDE.md অনুযায়ী এখন `ADMIN_PASSWORD` সেট করা বাধ্যতামূলক db:seed
    চালানোর আগে, এবং ডকুমেন্টেশনে আর real email/password (admin@zamzamint.com)
    উল্লেখ নেই — generic placeholder (admin@example.com) ব্যবহার হচ্ছে।
[✅] MAIL_FROM_ADDRESS placeholder — ঠিক হয়েছে, এখন admin@example.com
    ব্যবহার হচ্ছে production env উদাহরণে (আগে hello@example.com ছিল)
[✅] composer.json-এ block-insecure — যাচাই সম্পন্ন (2026-07-05): এখন
    `"audit": {"block-insecure": true}` সেট আছে।
[✅] minimum-stability — যাচাই সম্পন্ন (2026-07-05): এখন `"stable"` +
    `"prefer-stable": true`।
[✅] dompdf version — যাচাই সম্পন্ন (2026-07-05): `barryvdh/laravel-dompdf`
    এখন `^3.1`-এ pinned, wildcard নেই।
[✅] SESSION_DRIVER/QUEUE_CONNECTION/CACHE_STORE — PROJECT_GUIDE.md-এ এখন
    স্পষ্ট নির্দেশনা আছে: ছোট/single-server install-এ file/sync প্রেফার করা,
    বড় MySQL/Redis production-এ database/redis-এ migrate করা যায়। production
    env উদাহরণে এখন file/sync ডিফল্ট — এটা আগের সমস্যার সঠিক সমাধান।
```

## 0.3 নতুন আবিষ্কৃত সিস্টেম — Release and Update Safety

PROJECT_GUIDE.md-এর latest ভার্সনে একটা সম্পূর্ণ নতুন সিস্টেম পাওয়া গেছে যা মূল Master Plan v1-এ ছিল না, কিন্তু এটা Part 0 (Pre-requisite Stabilization)-এর সাথে সরাসরি সম্পর্কিত এবং ভবিষ্যতের সব বড় migration (data migration, storefront launch)-এর জন্য গুরুত্বপূর্ণ একটা safety net।

```txt
✅ app/Support/AppRelease.php          — রিলিজ মেটাডেটা কেন্দ্রীভূত করে
✅ app/Filament/Pages/ReleaseNotes.php  — admin panel-এ Release Notes পেজ
✅ config/release.php                   — রিলিজ কনফিগারেশন
✅ CHANGELOG.md                         — production change history রেকর্ড
✅ docs/release-policy.md, docs/update-safety.md — পলিসি ডকুমেন্টেশন
✅ tests/Feature/ReleaseNotesTest.php   — টেস্ট কভারেজ আছে
```

**গুরুত্বপূর্ণ নিয়ম যা এই সিস্টেম থেকে এসেছে:**

```txt
✅ Production deployment documentation-এ এখন স্পষ্ট লেখা আছে: migration-এর
   আগে database backup বাধ্যতামূলক।
✅ Routine production update-এ broad seeder, migrate:fresh, বা অন্য কোনো
   destructive command চালানো নিষিদ্ধ।
✅ Release type categorize করা হয়: major, minor, patch, security, hotfix,
   maintenance — প্রতিটা আপডেটের প্রভাব স্পষ্টভাবে চিহ্নিত থাকে।
```

**এজেন্টের জন্য নির্দেশ — এই সিস্টেম Part 1.9 (data migration)-এর সাথে সরাসরি যুক্ত:** Part 1.9-এর ধাপ ৭ (production data migration, Main Company থেকে আসল ৪টা company-তে ভাগ করা) চালানোর আগে, এই Release/Update Safety সিস্টেমের নিয়ম অনুসরণ করে অবশ্যই backup নিতে হবে এবং `CHANGELOG.md`-এ এই migration-কে একটা স্পষ্ট entry (সম্ভবত "major" বা "maintenance" টাইপ) হিসেবে রেকর্ড করতে হবে। Storefront launch (Part 4)-এর সময়ও একই নিয়ম প্রযোজ্য।

## 0.4 Build Order-এ অবস্থান

```txt
Part 0 (Pre-requisite Stabilization)         ┐
        +                                     ├─ সমান্তরালে করা যেতে পারে
Part 1.9 ধাপ ৭ (production data migration)    ┤  (backup system Part 0-এ
        +                                     │   থাকা Part 1.9-এর পূর্বশর্ত)
Part 1.10 (cross-cutting isolation audit)     ┘
        ↓
Part 2 (Courier) — অবশিষ্ট কাজ (2.4-এ তালিকাভুক্ত ক্রম অনুযায়ী)
        ↓
Part 3 (Fraud/Risk)
        ↓
Part 0, Part 1.9 (ধাপ ৭), ও Part 1.10 — তিনটাই সম্পূর্ণ নিশ্চিত হলেই → Part 4 (Storefront) শুরু
```

**গুরুত্বপূর্ণ নির্ভরতা:** Part 1.9-এর ধাপ ৭ (data migration) শুরু করার আগে Part 0-এর backup system সম্পন্ন হওয়া আবশ্যক, কারণ এই migration destructive এবং ভুল হলে production ডেটা মিশে যাওয়ার ঝুঁকি আছে।

---

# Part 1: Multi-Company System

> **✅ স্ট্যাটাস: নতুন installation-এর জন্য Part 1 কার্যত complete। বিদ্যমান production data migration বাকি।**
>
> এটা একটা গুরুত্বপূর্ণ পার্থক্য — আর্কিটেকচার (companies টেবিল, BelongsToCompany trait, CompanyScope, company_id সব টেবিলে) সম্পূর্ণ implement হয়ে গেছে এবং নতুন কোনো installation-এ এটা সঠিকভাবে কাজ করবে। কিন্তু **বিদ্যমান production data এখনো ভাগ হয়নি** — নিচে সুনির্দিষ্ট অবশিষ্ট কাজ:
>
> ```txt
> ❌ ১. পুরনো production data বর্তমানে "Main Company"-তে backfill করা আছে।
>    এটাকে Garments/Solar/Gadget/Gift — এই চারটা আসল company-তে সঠিকভাবে
>    ভাগ করার জন্য একটা migration/import tool এখনো তৈরি হয়নি।
>    (এটাই Part 1-এর সবচেয়ে গুরুত্বপূর্ণ অবশিষ্ট কাজ — এটা ছাড়া বাস্তবে
>    multi-company আলাদা করে দেখানো সম্ভব হবে না, কারণ সব ডেটা এখনো এক জায়গায়।)
>
> ❌ ২. Queue job, scheduled command, export, ও backup/restore flow-এর
>    পূর্ণ company-isolation audit এখনো বাকি। এই জায়গাগুলোতে CompanyScope
>    bypass হওয়ার ঝুঁকি বেশি, কারণ queue job/command প্রায়ই কোনো authenticated
>    user context ছাড়া চলে এবং company context manually set করতে হয়।
>
> ❌ ৩. সব company-scoped model নিয়ে একটা বিস্তৃত leak-test matrix নেই।
>    বর্তমান টেস্ট গুরুত্বপূর্ণ flow কভার করে, কিন্তু প্রতিটা model (categories,
>    products, stock_movements, suppliers, purchases, customers, orders,
>    accounts, expenses, ইত্যাদি — মোট ~২০টা model, দেখুন 1.5) আলাদাভাবে
>    isolation test করা হয়নি।
> ```
>
> **এজেন্টের জন্য নির্দেশ:** নতুন কোনো ফিচার (Part 2-14) এই Part 1-এর architecture-এর উপর নির্ভর করে নিরাপদে এগিয়ে যেতে পারে, কারণ architecture সম্পূর্ণ। কিন্তু **production-এ company isolation আসলে কাজ করছে দেখানোর জন্য** (owner-কে demo করা, বা multi-company reporting-এর সঠিকতা যাচাই করা), অবশ্যই প্রথমে ❌১ (data migration) সম্পন্ন করতে হবে — নাহলে সব company-তে একই (Main Company-র) ডেটা দেখাবে এবং isolation feature থাকলেও বাস্তবে কোনো পার্থক্য বোঝা যাবে না। ❌২ ও ❌৩ — এই দুটো production-এ চলে যাওয়ার আগে, বিশেষত Part 0 (Pre-requisite Stabilization)-এর সাথে একই সময়ে সম্পন্ন করা উচিত, কারণ backup/restore audit-ও Part 0-এর backup system কাজের সাথে সরাসরি যুক্ত।

### ✅ কোডে নিশ্চিতভাবে যাচাই করা Implementation (PROJECT_GUIDE.md সরাসরি পড়ে কনফার্ম)

```txt
app/Models/Company.php                                — companies প্রোফাইল, ব্র্যান্ডিং, currency,
                                                          timezone, invoice prefix, JSON settings
app/Models/Concerns/BelongsToCompany.php               — নতুন রেকর্ডে company_id স্বয়ংক্রিয় assign
app/Scopes/CompanyScope.php                            — query-level isolation scope
app/Services/CompanyContext.php                        — request-lifecycle company context resolver
app/Http/Middleware/SetCurrentCompany.php              — session company selection resolve করে
app/Http/Controllers/Admin/CompanySwitchController.php — company switch করার controller
app/Filament/Resources/Companies/                       — Company CRUD resource
resources/views/filament/partials/company-switcher.blade.php — Filament টপ-বার switcher UI
tests/Feature/MultiCompanyIsolationTest.php             — isolation টেস্ট (বিদ্যমান, কিন্তু matrix
                                                          সম্পূর্ণ কভারেজ কিনা যাচাই বাকি — দেখুন 1.10)
```

### নতুন আবিষ্কৃত Safeguard (Master Plan v1-এ ছিল না)

```txt
✅ Cross-company courier selection ও booking service-layer-এ reject করা হয় —
   মানে কোনো company-র user ভুলবশত বা ইচ্ছাকৃতভাবে অন্য company-র courier
   provider ব্যবহার করতে পারবে না, এটা service-এর ভেতরেই hard-check করা।
✅ "All Companies" view selected থাকলে courier provider creation ও booking
   action সম্পূর্ণ disable হয়ে যায় — owner-level group reporting view থেকে
   ভুলবশত কোনো company-specific write action হওয়ার ঝুঁকি এতে কমে।
```

**এজেন্টের জন্য নোট:** এই দুটো pattern (service-layer cross-company reject + "All Companies" view-এ write action disable) ভবিষ্যতের অন্য module-এও (Lead/CRM, Investor, Storefront) অনুসরণ করা উচিত — এটা company isolation নিরাপদ রাখার একটা প্রমাণিত pattern এই কোডবেসে।

### Company Invoice Numbering — নিশ্চিত হওয়া ফরম্যাট

```txt
GAD-20260623-0001   (Gadget company, কোম্পানির prefix + তারিখ + daily sequence)
```
এটা Part 1.8-এ আগে যা প্ল্যান করা হয়েছিল তার সাথে সামঞ্জস্যপূর্ণ এবং বাস্তবে কাজ করছে।

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

## 1.9 Migration Strategy (বিদ্যমান ডেটা) — ✅ Tool সম্পন্ন, বাস্তবায়ন সিদ্ধান্ত পরিবর্তিত

```txt
✅ ১. companies টেবিল তৈরি
✅ ২. ডিফল্ট company তৈরি: "Main Company"
✅ ৩. core টেবিলে company_id nullable যুক্ত
✅ ৪. বিদ্যমান রেকর্ড Main Company ID দিয়ে backfill
✅ ৫. নিরাপদ হলে company_id required করা
✅ ৬. আসল companies তৈরি (Garments/Solar/Gadget/Gift)
✅ ৭. Migration tool তৈরি হয়ে গেছে — `php artisan companies:migrate-data
   {company-slug} {mapping.json} --dry-run`। Real run স্বয়ংক্রিয়ভাবে
   database backup তৈরি করে; production-এ `--no-backup` reject হয়।
   `docs/company-data-migration.example.json` accepted mapping key
   ডকুমেন্ট করে। Child purchase/order/stock/payment records তাদের
   selected parent-এর সাথে transactionally move হয়।
```

### ⚠️ ব্যবসায়িক সিদ্ধান্ত পরিবর্তিত (গুরুত্বপূর্ণ)

PROJECT_GUIDE.md-এ এখন স্পষ্ট লেখা আছে:

> "Current business decision: no bulk legacy reassignment is planned because almost all records will be entered fresh under the correct company. Any small number of historical exceptions should be reviewed and moved manually; do not run the bulk migration command without a new explicit decision."

**এর মানে:** আগে Master Plan-এ ধরে নেওয়া হয়েছিল যে সব পুরনো "Main Company" ডেটা bulk migrate করে ৪টা company-তে ভাগ করা হবে। কিন্তু বাস্তবে ব্যবসায়িক সিদ্ধান্ত হয়েছে — **bulk migration করা হবে না**। এর বদলে নতুন সব entry সরাসরি সঠিক company-তে করা হবে, এবং পুরনো historical ডেটার মধ্যে অল্প কিছু exception (যদি থাকে) ম্যানুয়ালি রিভিউ করে সরানো হবে। Tool টা তৈরি আছে এবং ব্যবহারযোগ্য, কিন্তু **এখনই এটা চালানোর কোনো পরিকল্পনা নেই** — নতুন explicit সিদ্ধান্ত ছাড়া bulk migration command চালানো নিষেধ।

**এজেন্টের জন্য নির্দেশ:** Part 1.9-এর ধাপ ৭ আর "বাকি কাজ" হিসেবে গণ্য হবে না — tool ও process দুটোই সম্পন্ন এবং প্রস্তুত। এখন এটা শুধু owner-এর "on-demand" সিদ্ধান্তের অপেক্ষায়, ব্লকিং টাস্ক না। যদি ব্যবহারকারী ভবিষ্যতে bulk migration করতে চান, তাহলে `companies:migrate-data` কমান্ড ব্যবহার করুন — নতুন কিছু তৈরি করার দরকার নেই।

**⚠️ Part 0 নির্ভরতা এখন resolved:** আগে লেখা ছিল migration করার আগে Part 0-এর backup system দরকার। এখন কমান্ড নিজেই automatic backup নেয়, তাই এই নির্ভরতা কার্যত সমাধান হয়ে গেছে।

## 1.10 অবশিষ্ট কাজ — Cross-Cutting Isolation Audit

```txt
✅ Queue job company-isolation audit — সম্পন্ন (2026-07-05)। একমাত্র queued
   job `ProcessCourierWebhook` webhook log থেকে provider-এর company resolve
   করে `CompanyContext::set()` করে এবং finally-তে `clear()` করে — সঠিক
   প্যাটার্ন। নতুন job লিখলে একই প্যাটার্ন অনুসরণ করতে হবে।

✅ Scheduled command company-isolation audit — সম্পন্ন (2026-07-05)।
   `storefront:send-abandoned-cart-reminders` প্রতি company-র setting ধরে
   লুপ করে এবং প্রতিটা query-তে explicit `company_id` ফিল্টার দেয়;
   `backup:database` ইচ্ছাকৃতভাবে পুরো-DB (company-নিরপেক্ষ) — ঠিক আছে।

✅ Export company-isolation audit — সম্পন্ন (2026-07-05), এবং এই audit-এ
   একটা আসল bug ধরা পড়ে ঠিক করা হয়েছে: `SetCurrentCompany` middleware
   route model binding-এর *পরে* চলত, ফলে `/admin/orders/{order}/pdf`-এর
   মতো implicit-binding route-এ CompanyScope binding query-কে constrain
   করত না — এক company-র user অন্য company-র order PDF নামাতে পারত।
   ফিক্স: bootstrap/app.php-তে `prependToPriorityList()` দিয়ে
   `SetCurrentCompany`-কে `SubstituteBindings`-এর আগে বসানো হয়েছে।
   Regression টেস্ট: `CrossCuttingIsolationAuditTest` (product/customer
   CSV export, report CSV export, cross-company order PDF 404)।

✅ Backup/restore flow company-isolation audit — সম্পন্ন (2026-07-05)।
   বর্তমানে per-company backup/restore ফিচার নেই; backup পুরো database
   পর্যায়ে (সব company একসাথে) এবং download শুধু backup permission-ধারী
   user-এর জন্য। ভবিষ্যতে per-company restore বানালে তখন নতুন audit লাগবে।

✅ Comprehensive leak-test matrix — সম্পন্ন বলে কনফার্ম হয়েছে
   PROJECT_GUIDE.md-এ Part 1.9-এর প্রসঙ্গে স্পষ্ট লেখা আছে: "The isolation
   contract test covers every current company-owned model, including
   courier, shipment, and container records।" অর্থাৎ `MultiCompanyIsolationTest.php`
   এখন Part 1.5-এ তালিকাভুক্ত সব model কভার করে বলে নিশ্চিত করা হয়েছে —
   আগের "আংশিক/অনিশ্চিত" স্ট্যাটাস এখন সম্পন্ন হিসেবে আপডেট করা হলো।
   **এজেন্টের জন্য নোট:** ভবিষ্যতে নতুন কোনো company-scoped model (Lead/CRM,
   Investor module ইত্যাদি) যুক্ত হলে, এই isolation contract test-এ সেই নতুন
   model-ও যুক্ত করতে হবে যাতে "covers every current company-owned model"
   বিবৃতিটা সত্য থাকে।
```

**Build Order-এ অবস্থান:** এই Part 1.10-এর কাজ Part 0 (Pre-requisite Stabilization)-এর সাথে সমান্তরালে করা উচিত, কারণ এটাও এক ধরনের production-readiness/stabilization কাজ — নতুন ফিচার (Part 2-14) যুক্ত করার আগে নয়, বরং তার পাশাপাশি।

---

# Part 2: Courier Integration Module

> **✅ আপডেটেড স্ট্যাটাস (কোডে সরাসরি যাচাই করা হয়েছে):** পরিকল্পিত আর্কিটেকচার (CourierProviderInterface, CourierManager), webhook endpoint + signature verification + queue + retry + idempotency, CourierStatusLogResource, CourierWebhookLogResource, Order action (Cancel Booking/Print Label/Track), এবং CourierReportService (success/return ratio, COD summary, company-wise performance) — সব **সম্পন্ন**। শুধু বাকি: Pathao/RedX/E-Courier-এর **live API client** (এখন explicit "pending" guardrail adapter আছে), Steadfast balance UI-তে দেখানো, এবং production monitoring/alerting। নিচে বিস্তারিত তালিকা।

## 2.0 সম্পন্ন vs বাকি কাজের সম্পূর্ণ তালিকা

### ✅ যা সম্পন্ন হয়েছে (PROJECT_GUIDE.md দিয়ে কনফার্ম করা বিস্তারিত)

```txt
Manual/custom courier booking — Order list ও Order detail থেকে দুই জায়গা থেকেই করা যায়
Active Custom provider সিলেকশন manual booking-এর সময়
Tracking ID না দিলে স্বয়ংক্রিয় manual tracking ID generate হয়
Steadfast order creation — https://portal.packzy.com/api/v1/create_order এন্ডপয়েন্ট দিয়ে
Steadfast status sync — tracking code বা invoice দিয়ে
Steadfast consignment ID ও tracking code সংরক্ষণ হয়
Steadfast API key/secret key এনক্রিপ্টেড `credentials` model cast-এ সুরক্ষিত
Provider settings: contact person, phone, warehouse, delivery fees, courier costs,
  return costs, COD percentage, base URL — সব কনফিগারযোগ্য
Delivery status sales Order status থেকে independent (পরিকল্পনা অনুযায়ী ✅)
Normalized delivery status enum বাস্তবায়িত: not_booked, booking_pending, booked,
  picked_up, in_transit, delivered, partial_delivered, returned, cancelled, failed
প্রতিটা manual/synchronized status change-এ courier status log তৈরি হয়
Order-এ courier action: booking, Steadfast booking, delivered, returned, status info
Courier booking detail-এ provider, invoice, recipient, COD amount, tracking data,
  status history — সব দেখানো হয়
✅ Manual ও Steadfast booking service Order ও Courier Provider একই company-র
  কিনা যাচাই করে (cross-company booking block করা — Part 1-এর নতুন আবিষ্কৃত
  safeguard-এর সাথে সম্পর্কিত)
```

### ✅ নিশ্চিত হওয়া ফাইল পাথ

```txt
app/Models/CourierProvider.php
app/Models/CourierBooking.php
app/Models/CourierStatusLog.php
app/Models/CourierWebhookLog.php
app/Services/CourierService.php
app/Services/SteadfastCourierClient.php
app/Filament/Resources/CourierProviders/
app/Filament/Resources/CourierBookings/
tests/Feature/CourierIntegrationTest.php
```

### ⚠️ Pathao/RedX/E-Courier — সংশোধিত স্ট্যাটাস

PROJECT_GUIDE.md স্পষ্ট করে বলছে এগুলো এখন **"configuration placeholder"** হিসেবে provider choice-এ দেখা যায় (অর্থাৎ Filament dropdown-এ option আছে), কিন্তু **কোনো live API client implement হয়নি**। এটা আগের "তৈরি হয়নি" বলার চেয়ে একটু বেশি অগ্রগতি — UI-level provider স্লট রেডি আছে, কিন্তু backend API integration শূন্য থেকে শুরু করতে হবে।

```txt
[✅] Pathao — live API client সম্পন্ন (2026-07-05): PathaoCourierClient
    (issue-token OAuth password grant + token cache, orders, order info,
    city/zone/area/store list), booking/sync/webhook adapter, Orders
    টেবিলে "Book Pathao" action। বাকি শুধু owner-এর merchant credential
    (client_id/secret/username/password) বসানো।
[✅] RedX — live API client সম্পন্ন (2026-07-05): RedxCourierClient
    (API-ACCESS-TOKEN header, parcel create/info/track/areas), adapter +
    "Book RedX" action। বাকি শুধু owner-এর access token বসানো।
[✅] E-Courier — live API client সম্পন্ন (2026-07-05): ECourierClient
    (API-KEY/API-SECRET/USER-ID headers, order-place/track/cancel +
    reference lists), adapter + "Book E-Courier" action। বাকি শুধু
    owner-এর credential বসানো।
```

### ✅ Architecture — সম্পন্ন (আপডেট: কোডে যাচাই করা হয়েছে)

```txt
[✅] CourierProviderInterface তৈরি হয়েছে — app/Contracts/CourierProviderInterface.php
[✅] CourierManager তৈরি হয়েছে — app/Services/CourierManager.php, Manual ও Steadfast
    এখন concrete adapter (app/Services/Couriers/) ব্যবহার করে, if-else duplicate লজিক নেই
```

**⚠️ গুরুত্বপূর্ণ আর্কিটেকচারাল সিদ্ধান্ত প্রয়োজন:** Pathao/RedX/E-Courier adapter যুক্ত করার আগে `CourierService`-কে `CourierManager` + `CourierProviderInterface` প্যাটার্নে refactor করা উচিত কিনা, নাকি বর্তমান `CourierService`-এর ভেতরেই নতুন provider-এর জন্য case যুক্ত করতে থাকা ঠিক হবে — এটা একটা trade-off: refactor করলে স্বল্পমেয়াদে বেশি কাজ কিন্তু দীর্ঘমেয়াদে নতুন provider যুক্ত করা সহজ হবে এবং hallucination-prone duplicate code এড়ানো যাবে। **সুপারিশ:** Pathao adapter শুরু করার ঠিক আগে এই refactor একটা ছোট, আলাদা ধাপ হিসেবে করা — যাতে agent একসাথে দুটো কাজ (refactor + নতুন provider) না করে, যেটা hallucination ঝুঁকি বাড়ায়।

### ⚠️ যা এখনো বাকি — API Adapters (আপডেট: guardrail সম্পন্ন, live client বাকি)

```txt
[✅] Pathao/RedX/E-Courier live adapters সম্পন্ন (2026-07-05) — আগের
    PendingLiveCourierAdapter guardrail সরিয়ে আসল adapter বসানো হয়েছে
    (app/Services/Couriers/PathaoCourierAdapter.php, RedxCourierAdapter.php,
    ECourierAdapter.php), create/sync/webhookStatus কাজ করে; credential
    ছাড়া booking চাইলে স্পষ্ট "credentials required" validation error।
[✅] Live API client — সম্পন্ন (PathaoCourierClient, RedxCourierClient,
    ECourierClient), API contract ওয়েব রিসার্চে অফিসিয়াল ডক থেকে যাচাই
    করা; টেস্ট Http::fake দিয়ে (LiveCourierAdaptersTest)। আসল sandbox
    টেস্ট owner-এর merchant credential পাওয়ার পরে করতে হবে।
```

### ✅ যা এখন সম্পন্ন — Webhook ও Reliability (আপডেট: কোডে যাচাই করা হয়েছে)

```txt
[✅] Courier webhook endpoint তৈরি — routes/web.php: POST /webhooks/couriers/{provider}
    (CourierWebhookController)
[✅] Webhook signature verification আছে
[✅] Webhook payload logging + processing — courier_webhook_logs-এ log হয়ে
    provider-এর company context-এর ভেতরে process হয়
[✅] Queue-based webhook processing (queued, synchronous না)
[✅] Idempotency guarantee — signed webhook দেডুপ্লিকেট করা হয়
[✅] Retry mechanism আছে (webhook ও API call উভয়ের জন্য bounded timeout + backoff)
[✅] Production monitoring/alerting সম্পন্ন (2026-07-05) — scheduled `couriers:sync-statuses` (প্রতি ৩০ মিনিটে, per-company), sync failure streak/stale booking/webhook permanent failure-এ admin database notification, Courier Health dashboard widget
```

### ✅ যা এখন সম্পন্ন — Filament Resources ও UI (আপডেট)

```txt
[✅] CourierStatusLogResource তৈরি হয়েছে
[✅] CourierWebhookLogResource তৈরি হয়েছে
[✅] Order action-এ "Cancel Booking" যুক্ত হয়েছে
[✅] Print Label — normalized cancellation + configurable label URL template
    দিয়ে করা হয়েছে (provider-native label endpoint না, কারণ official API
    contract এখনো নেই)
[✅] Dedicated tracking/status action আছে
[✅] Steadfast balance admin UI — সম্পন্ন (2026-07-05): Courier Providers
    টেবিলে "Balance" action, credential-সহ Steadfast provider-এ ক্লিক
    করলে current balance notification-এ দেখায়
```

### ✅ যা এখন সম্পন্ন — Reports (আপডেট)

```txt
[✅] Provider-wise delivered/returned/cancelled count রিপোর্ট — CourierReportService
[✅] Success/return ratio রিপোর্ট — CourierReportService
[✅] COD amount রিপোর্ট/সামারি — CourierReportService
[✅] Company-wise courier performance তুলনা — CourierReportService
```

## 2.1 মূল আর্কিটেকচার (পরিকল্পিত, আংশিক বাস্তবায়িত)

```txt
CourierManager                          ✅ সম্পন্ন (app/Services/CourierManager.php)
├── CourierProviderInterface             ✅ সম্পন্ন (app/Contracts/CourierProviderInterface.php)
├── ManualCourier Adapter      ✅ সম্পন্ন (app/Services/Couriers/ManualCourierAdapter.php)
├── SteadfastCourier Adapter   ✅ সম্পন্ন (app/Services/Couriers/SteadfastCourierAdapter.php)
├── PathaoCourier Adapter      ✅ সম্পন্ন (live client + adapter, 2026-07-05)
├── RedxCourier Adapter        ✅ সম্পন্ন (live client + adapter, 2026-07-05)
├── ECourierCourier Adapter    ✅ সম্পন্ন (live client + adapter, 2026-07-05)
└── Future Adapters
```

## 2.2 প্রধান টেবিল

```txt
courier_providers       — ✅ সম্পন্ন (company_id, name, slug, credentials, settings)
courier_bookings        — ✅ সম্পন্ন (order_id, tracking_id, recipient info, cod_amount, status)
courier_status_logs     — ✅ সম্পন্ন, Resource-ও তৈরি (CourierStatusLogResource)
courier_webhook_logs    — ✅ সম্পন্ন — payload logging/processing/dedup বাস্তবে কাজ করছে
```

## 2.3 Internal Delivery Status (Normalized)

```txt
not_booked → booking_pending → booked → picked_up → in_transit
→ delivered / partial_delivered / returned / cancelled / failed
```

**নিয়ম:** Order status (`draft/confirmed/completed/cancelled`) আর Delivery status আলাদা রাখতে হবে — দুটো ভিন্ন workflow। *(এই অংশের বাস্তবায়ন স্ট্যাটাস যাচাই করা হয়নি — কোডে গিয়ে নিশ্চিত করুন delivery_status আলাদা ফিল্ড হিসেবে আছে কিনা।)*

## 2.4 অবশিষ্ট কাজের Priority Order (Refactor → Webhook → নতুন Adapter)

পুরনো priority (Manual → Steadfast → Pathao → RedX) আংশিক প্রাসঙ্গিকতা হারিয়েছে কারণ Manual ও Steadfast already সম্পন্ন। এখন থেকে যা অবশিষ্ট তার জন্য নতুন priority:

```txt
✅ ১. CourierManager + CourierProviderInterface রিফ্যাক্টর — সম্পন্ন
✅ ২. Webhook endpoint + signature verification + processing + retry — সম্পন্ন
✅ ৩. CourierStatusLogResource + CourierWebhookLogResource — সম্পন্ন
✅ ৪. Order action: Cancel Booking, Print Label (label URL template), dedicated
   Track action — সম্পন্ন
✅ ৫. Pathao adapter — সম্পন্ন (2026-07-05), owner-এর credential বসালেই লাইভ
✅ ৬. RedX adapter — সম্পন্ন (2026-07-05), owner-এর token বসালেই লাইভ
✅ ৭. E-Courier adapter — সম্পন্ন (2026-07-05), owner-এর credential বসালেই লাইভ
✅ ৮. Courier reports (provider-wise delivered/returned/cancelled, success/return
   ratio, COD summary, company-wise performance) — CourierReportService দিয়ে সম্পন্ন
✅ ৯. Steadfast balance UI-তে দেখানো — সম্পন্ন (2026-07-05)
✅ ১০. Idempotency guarantee — সম্পন্ন; production monitoring/alerting সম্পন্ন (2026-07-05)
```

## 2.5 Filament Resources (স্ট্যাটাস সহ)

```txt
CourierProviderResource     — ✅ সম্পন্ন
CourierBookingResource       — ✅ সম্পন্ন
CourierStatusLogResource     — ✅ সম্পন্ন
CourierWebhookLogResource    — ✅ সম্পন্ন
```

Order resource-এ courier action — স্ট্যাটাস:
```txt
Create Booking         — ✅ সম্পন্ন
Track / Sync Status     — ✅ সম্পন্ন (dedicated track action আছে)
Cancel Booking          — ✅ সম্পন্ন
Print Label             — ✅ সম্পন্ন (configurable label URL template)
Mark Manual Delivered/Returned — ✅ সম্পন্ন
```

---

# Part 3: Customer Success & Risk Score (Fraud Detection)

> **✅ স্ট্যাটাস: সম্পূর্ণ implement হয়ে গেছে এবং কোডে সরাসরি কনফার্ম করা হয়েছে।** এই পুরো module — যা মূল Master Plan-এ শুধু ডিজাইন হিসেবে ছিল — এখন PROJECT_GUIDE.md অনুযায়ী বাস্তবায়িত। নিচে মূল ডিজাইন (রেফারেন্সের জন্য অপরিবর্তিত রাখা হলো) এবং তার পাশাপাশি প্রতিটার confirmed বাস্তব implementation স্ট্যাটাস দেওয়া হলো।

## 3.0 কোডে যাচাই করা বাস্তব Implementation

```txt
✅ Rule-based scoring (ML না) — ডিজাইন অনুযায়ীই implement হয়েছে; প্রতিটা
   deduction একটা "named factor" হিসেবে সংরক্ষিত হয় (ডিজাইনের চেয়ে ভালো —
   explainability আরও শক্তিশালী)
✅ Company-level profile — courier totals + delivered/returned/cancelled
   ratio, customer phone দিয়ে ট্র্যাক করা হয়
✅ Score mapping — Low (80-100), Medium (50-79), High (0-49); active
   global/company blacklist আলাদা "Blacklisted" level তৈরি করে
   (ডিজাইনের সাথে হুবহু মিলে যায়)
✅ Risk check timing — Order confirmed/completed হওয়ার সময়, এবং আবার
   ঠিক courier booking-এর আগে (double-check, ডিজাইনের চেয়ে বেশি robust)
✅ Blacklist enforcement — global বা company blacklist match হলে courier
   booking block হয়ে যায়, owner review-এর অপেক্ষায় থাকে
✅ Idempotent risk events — Terminal courier status change (delivered/
   returned/cancelled ইত্যাদি) idempotent customer risk event তৈরি করে
   এবং profile refresh করে (duplicate event তৈরি হওয়ার ঝুঁকি নেই)
✅ UI placement — Customer ও Order list/detail-এ risk badge; booking form-এ
   submit করার আগেই current score দেখানো হয় (3.7-এর ডিজাইনের সাথে হুবহু মিলে)
✅ Blacklist management — Super Admin `Customer Success` navigation group-এর
   অধীনে global/company blacklist entry ম্যানেজ করেন
✅ Approval workflow — High-risk order → manager approval request তৈরি হয়
   courier booking-এর আগে; Blacklisted match → owner approval request
   তৈরি হয় (এটা মূল ডিজাইনের 3.6-এর সাথে সরাসরি মিলে, কিন্তু আসলে একটা
   ছোট approval-request সিস্টেম হিসেবেই তৈরি হয়ে গেছে — Task/Approval
   Workflow module-এর (Part 11.3+) সাথে ভবিষ্যতে একীভূত করার কথা
   বিবেচনা করা যেতে পারে যাতে দুটো আলাদা approval সিস্টেম না থাকে)
✅ Settings — Super Admin কোনো কোড পরিবর্তন ছাড়াই risk threshold ও
   deduction weight টিউন করতে পারেন (`CustomerRiskSettingsService` দিয়ে)
✅ Dashboard widget — Customer Success & Risk stats + high-risk/blacklisted
   profile-এর জন্য একটা আলাদা alert table
```

### ✅ নিশ্চিত হওয়া ফাইল পাথ

```txt
app/Services/CustomerRiskService.php
app/Services/CustomerRiskSettingsService.php
app/Models/CustomerRiskProfile.php
app/Models/CustomerRiskEvent.php
app/Models/CustomerBlacklist.php
app/Models/CustomerRiskReview.php
app/Models/FraudCheck.php
app/Filament/Resources/CustomerRiskProfiles/
app/Filament/Resources/CustomerBlacklists/
app/Filament/Resources/CustomerRiskReviews/
app/Filament/Resources/CustomerRiskEvents/
app/Filament/Pages/CustomerRiskSettings.php
tests/Feature/CustomerRiskTest.php
```

**এজেন্টের জন্য নির্দেশ:** এই মডিউলে নতুন কোনো কাজ করার দরকার নেই, শুধু রক্ষণাবেক্ষণ। ভবিষ্যতে Lead/CRM module (Part 11.2) বা Investor module (Part 11.3) থেকে যদি কোনো নতুন customer-facing risk সংকেত আসে, সেটা এই বিদ্যমান `CustomerRiskService`-এ যুক্ত করা উচিত, নতুন আলাদা সিস্টেম তৈরি করা উচিত না।

---

## মূল ডিজাইন (রেফারেন্সের জন্য, উপরের implementation-এর ভিত্তি)

## 3.1 কেন এই নাম

> UI-তে "Fraud" শব্দ এড়িয়ে **"Customer Success & Risk Score"** ব্যবহার করুন — কম আক্রমণাত্মক, legally নিরাপদ। *(✅ implement হওয়া কোডে ঠিক এই নামেই "Customer Success" navigation group ব্যবহার হয়েছে।)*

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

*(নির্দিষ্ট weight-গুলো কোডে হুবহু এভাবেই আছে কিনা তা PROJECT_GUIDE.md-এ বিস্তারিত নেই — শুধু "explainable rules" ও "named factor" উল্লেখ আছে। যেহেতু `CustomerRiskSettingsService` দিয়ে এগুলো টিউনযোগ্য, precise numeric weight নিয়ে চিন্তা না করে এই framework-টাই গুরুত্বপূর্ণ বলে ধরে নেওয়া হচ্ছে।)*

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

> **⚠️ এই Part শুরু করার আগে পড়ুন (নোট ১ — WooCommerce):** ব্যবহারকারীর ৪টা কোম্পানির বর্তমান ওয়েবসাইট WooCommerce-এ চলছে, যেখানে real customer ও product ডেটা আছে। এই storefront চালু করার আগে সেই ডেটা migrate করতে হবে, নাহলে হারিয়ে যাবে। সংক্ষিপ্ত প্ল্যান ডকুমেন্টের শেষে **"Part 12: WooCommerce Data Migration"**-এ আছে — সেটা প্রথমে পড়ে নিশ্চিত করুন migration approach (one-time import vs phased rollout) নিয়ে ব্যবহারকারীর সাথে কথা হয়েছে, তারপর এই Part 4-এর কাজ শুরু করুন।
>
> **⚠️ নোট ২ — Domain Routing (গুরুত্বপূর্ণ আর্কিটেকচার সিদ্ধান্ত):** প্রতিটা company-র **নিজস্ব সম্পূর্ণ আলাদা domain** আছে (subdomain না) — `tasneemknitindustry.com`, `noorsolaren.com`, `zamzamgadgetbd.com`, `zamzamint.com`। এবং ব্যবহারকারী ভবিষ্যতে নতুন company যুক্ত করতে চান, প্রতিটার নিজস্ব নতুন domain সহ। তাই route definition-এ company hardcode করা চলবে না — **section 4.4-এ বর্ণিত `ResolveCompanyFromDomain` middleware** ব্যবহার করতে হবে, যা runtime-এ ডেটাবেস lookup করে domain থেকে company শনাক্ত করে। এই middleware তৈরি করা **Part 4-এর প্রথম এবং সবচেয়ে গুরুত্বপূর্ণ কাজ** — এর আগে কোনো controller/route লেখা ঠিক হবে না, কারণ পরের সব কাজ এই company-resolution mechanism-এর উপর নির্ভর করে।
>
> **✅ নোট ৩ (আপডেটেড, কোডে সরাসরি যাচাই করা হয়েছে):** এই Part 4-এর বেশিরভাগ কাজ এখন কোডে **সম্পন্ন** — `ResolveCompanyFromDomain` middleware, `companies.domain`/`domain_verified` কলাম, `storefront_settings`/`storefront_pages`/`carts`/`cart_items`/`banners`/`coupons` টেবিল, Home/ProductIndex/ProductShow/Cart/Checkout/OrderTrack/AccountOrders/Page controller, Shopify-style Blade view (admin Filament vocabulary থেকে আলাদা), এবং Filament-এ launch-readiness সহ `StorefrontSettingsResource` — সব বাস্তবায়িত হয়েছে (দেখুন `UPDATE_NOTES.md`-এর 2026-07-02/03 এন্ট্রি)। এখনো বাকি: **WooCommerce data migration (Part 12)** এবং **প্রকৃত production domain go-live (DNS/Coolify/SSL)**।

### ✅ 4.0 সম্পূর্ণ Confirmed Implementation সারাংশ (PROJECT_GUIDE.md থেকে, ২০২৬-০৭ আপডেট)

```txt
Domain ও Routing:
✅ ResolveCompanyFromDomain middleware — custom domain থেকে company resolve করে
✅ companies.domain ও companies.domain_verified কলাম
✅ Production route domain-based: /products, /category/{slug}, /product/{slug}
✅ Local preview route (hosts file এডিট ছাড়াই টেস্ট করার জন্য):
   /storefront, /storefront/{company-slug}, /storefront/{company-slug}/products,
   /storefront/{company-slug}/category/{slug}, /storefront/{company-slug}/product/{slug},
   /storefront/{company-slug}/cart

Cart:
✅ Session-based, company-scoped cart (এক company-র cart আরেক company-তে bleed করে না)
✅ Add/update/remove/stock-capping/empty-state/subtotal — সব কাজ করছে
✅ Production cart route: GET/POST/PATCH/DELETE /cart, /cart/items/{product-slug}

Checkout:
✅ সম্পূর্ণ implement — name, phone, email(optional), address, note নিয়ে অর্ডার নেয়
✅ Phone দিয়ে existing company-scoped Customer reuse/update; নতুন হলে customer_source=website
✅ ERP Order+OrderItem তৈরি হয় source=storefront দিয়ে (ডিজাইন অনুযায়ী, কোনো ডুপ্লিকেট মডেল নেই)
✅ Storefront order intentionally 'draft' — stock deduct হয় না যতক্ষণ না admin
   review করে confirm/complete করেন (এটা একটা ইচ্ছাকৃত ব্যবসায়িক নিয়ন্ত্রণ,
   আগের ডিজাইনে explicitly উল্লেখ ছিল না — নতুন গুরুত্বপূর্ণ তথ্য)
✅ Draft storefront order "Today Sales"-এ যোগ হয় না; আলাদা "Storefront Pending"
   হিসেবে dashboard-এ দেখানো হয় (pending count + amount)
✅ Order table/detail-এ Source badge (Admin/Storefront), filter করা যায়
✅ Checkout stock validate করে, সফল হলে cart clear করে
✅ Checkout success পেজে order number ও summary দেখায়

Order Tracking:
✅ Order number দিয়ে সার্চ — order status, delivery status (শুধু admin/courier
   update-এর পরে দেখা যায়), latest courier/tracking ID, totals, due, items
✅ Chronological "Tracking Updates" timeline (fixed status list না — audit log
   ও courier status log থেকে dynamically তৈরি)
✅ শুধু current company + source=storefront অর্ডার দেখা যায়; admin order বা
   অন্য company-র order 404 দেয় (isolation সঠিকভাবে কাজ করছে)

Customer Account:
✅ Phone number দিয়ে order history দেখা যায় (/account/orders)
✅ শুধু current-company storefront order দেখায়, admin-created/other-company order hidden

Content Pages:
✅ Published page footer link + /pages/{slug}-এ দেখা যায়
✅ Unpublished/other-company page 404 দেয়

UI/UX (Shopify-style লক্ষ্য অনুযায়ী):
✅ Dark mode — real class-based toggle (media query না), localStorage persist,
   company-ভিত্তিক default (system/light/dark), header sun/moon বাটন —
   এটা ঠিক Part 4.6-এ আমাদের বাধ্যতামূলক করা নিয়মের সাথে হুবহু মিলে যায়
✅ Product listing sort (price_asc/price_desc/newest) ও category quick-filter chip
✅ Product detail — breadcrumb, sticky buy box with quantity stepper,
   "You may also like" related-products rail (same category, current বাদ, limit ৪)
✅ Homepage hero heading/subheading/CTA admin-editable
   (storefront_settings.hero_heading/hero_subheading/hero_cta_label)
✅ SEO/Open Graph/Twitter metadata storefront settings থেকে আসে
✅ Footer WhatsApp contact, banner-image hero, explicit out-of-stock state

⚠️ গুরুত্বপূর্ণ নীতি যা কোডে confirmed:
"Public storefront copy should not expose implementation details such as
unfinished roadmap steps; customer-facing text should describe direct
ordering, review, confirmation, and tracking." — অর্থাৎ কাস্টমার-facing
টেক্সটে কখনো internal roadmap/অসম্পূর্ণ ফিচারের ইঙ্গিত থাকা যাবে না।
```

**✅ Part 4.6 "Top-Class Reference Pattern"-এর বেশিরভাগ কোডে সম্পন্ন (২০২৬-০৭-০৩):**
```txt
Mega menu, dual banner (desktop/mobile), header chat+call বাটন,
sister-company cross-promotion footer, mobile bottom nav, ও "কিভাবে অর্ডার
করবেন" explainer — সবগুলো layout.blade.php/home.blade.php ও
StorefrontSettingResource-এ implement, migrate, build, ও পুরো টেস্ট স্যুট
(১৬৯/১৬৯) দিয়ে verify করা হয়েছে।
```

**✅ পরবর্তীতে সম্পন্ন হওয়া আইটেম:**
```txt
✅ Curated Product Carousel — সম্পন্ন (commit 2626e5f0): ProductCarousel
   model + pivot table, ProductCarouselResource (Storefront group),
   homepage rendering, ProductCarouselTest সহ
✅ Product variants + gallery — সম্পন্ন (variant cart/checkout flow সহ)
✅ Quick Reorder (B2B UX #5) — সম্পন্ন (২০২৬-০৭-০৪): account/orders পেজে
   প্রতি অর্ডারে "Reorder" বাটন, phone-verified POST route production +
   preview দুটোতেই, StorefrontReorderTest দিয়ে কভার করা
```

**❌ যা এখনো storefront-এর ভেতরে বাকি:**
```txt
[ ] Production custom domain-এ actual DNS + Coolify SSL go-live (কোড-level
    কাজ সম্পন্ন, কিন্তু বাস্তব domain go-live infrastructure ধাপ বাকি)
[ ] WooCommerce data migration (Part 12 — এখনো সম্পূর্ণ আলাদা কাজ, storefront
    কোড-সম্পন্ন হলেও পুরনো WooCommerce ডেটা এখনো storefront-এ আনা হয়নি)
✅ Tiered pricing + MOQ (B2B UX #1-2) — সম্পন্ন (২০২৬-০৭-০৪):
    products.moq + products.tier_prices (JSON), admin ProductForm-এ
    "Wholesale (B2B)" সেকশন, cart-এ quantity-ভিত্তিক tier price (variant
    line বাদ — সেগুলো variant price রাখে), MOQ-এর নিচে quantity স্বয়ংক্রিয়
    clamp, প্রোডাক্ট পেজে wholesale টেবিল + MOQ badge (StorefrontB2bTest)
✅ Customer due visibility (B2B UX #4) — সম্পন্ন (২০২৬-০৭-০৪): account
    orders পেজে phone match হয়ে অর্ডার পাওয়া গেলে current_balance > 0
    হলে "Current due" ব্যানার দেখায়
✅ Advanced e-commerce features — কোডে সম্পন্ন (২০২৬-০৭-০৪, owner-এর
    কনফার্ম করা রিয়েল বিজনেস রুল অনুযায়ী):
    - ZiniPay online payment (hosted checkout + verify + webhook) —
      credentials admin panel-এ বসালেই চালু
    - Pre-order flow: is_preorder + per-product advance % (COD শুধু
      in-stock item-এর জন্য, pre-order-এ online advance বাধ্যতামূলক)
    - Reseller application (/reseller) + admin approve (Customer form)
    - Abandoned cart: DB-persisted cart record + hourly SMS/WhatsApp
      (Meta Cloud API) reminder command — gateway credentials admin-এ
    - WooCommerce product import: woocommerce:import-products কমান্ড
      (products-only, owner-এর সিদ্ধান্ত অনুযায়ী)
    বাকি শুধু: owner-এর আসল credentials (ZiniPay key, SMS gateway,
    WhatsApp token/template, WooCommerce key/secret) সেটআপ করা, এবং
    reseller-only price gating (customer login/OTP আসার পরে)
```

## 4.1 মূলনীতি

> **এক কোডবেস, এক ডেটাবেস, এক admin panel (Filament)। স্টোরফ্রন্ট কোনো আলাদা সিস্টেম নয় — এটা ERP-এর Public-facing extension মাত্র।**

```txt
┌──────────────────────────────────────────────┐
│           একই Laravel Application              │
├───────────────────┬────────────────────────────┤
│  /admin (Filament)   │   / (Public Storefront)    │
│  সম্পূর্ণ ERP কন্ট্রোল    │   Blade + vanilla JS       │
├───────────────────┴────────────────────────────┤
│         একই Models: Product, Order, Customer       │
│         একই Database, একই company_id scope         │
└──────────────────────────────────────────────┘
```

কোনো sync service লাগবে না, কোনো duplicate product/order মডেল লাগবে না, কোনো mapping table লাগবে না — যেটা Lunar-এর সাথে লাগতো।

## 4.2 Tech Stack

> **✅ আপডেট (বাস্তব implementation থেকে কনফার্ম):** নিচের original ডিজাইনে "Blade + Livewire + Alpine.js" লেখা ছিল, কিন্তু বাস্তবে storefront **plain vanilla JavaScript** দিয়ে বানানো হয়েছে — কোনো Alpine.js storefront bundle-এ লোড হয় না। Dark mode toggle ও quantity stepper (`[data-theme-toggle]`, `[data-qty-stepper]` ইত্যাদি) plain JS দিয়ে `layout.blade.php`-এ implement করা, Alpine directive দিয়ে না। এটা একটা ভালো সিদ্ধান্ত ছিল — storefront bundle হালকা থাকে, এবং Filament-এর নিজস্ব Alpine ব্যবহারের সাথে কোনো conflict/confusion হওয়ার সুযোগ নেই।

```txt
Backend:    Laravel 12 (বিদ্যমান)
Frontend:   Blade + plain vanilla JavaScript (Livewire/Alpine স্টোরফ্রন্ট
            bundle-এ ব্যবহৃত হয় না — শুধু admin-এর Filament নিজের Alpine
            আলাদাভাবে ব্যবহার করে, storefront-এ bleed করে না)
Styling:    Tailwind CSS 4, class-based dark mode (`@custom-variant dark`)
Admin:      Filament 4 (বিদ্যমান প্যানেলেই storefront management যুক্ত)
```

**কেন এই স্ট্যাক:** সাধারণ Blade + vanilla JS দিয়ে server-rendered, SEO-friendly storefront পাওয়া যায়, কোনো অতিরিক্ত JS framework bundle-এর ওজন ছাড়াই। Filament-এর admin panel নিজের Livewire/Alpine স্বাধীনভাবে চালায়, storefront সম্পূর্ণ আলাদা এবং হালকা থাকে।

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

## 4.4 Routes Structure — Custom Domain Mapping (Subdomain না)

> **⚠️ গুরুত্বপূর্ণ সংশোধনী:** এই Master Plan-এর আগের ভার্সনে subdomain-based রুটিং (`gadget.zamzamint.com`) লেখা ছিল। ব্যবহারকারী নিশ্চিত করেছেন প্রতিটা company-র **সম্পূর্ণ আলাদা, ইতিমধ্যে-বিদ্যমান custom domain** আছে:
>
> ```txt
> Garments Machinery → tasneemknitindustry.com
> Solar Items        → noorsolaren.com
> Gadget Items        → zamzamgadgetbd.com
> Gift Items          → zamzamint.com
> ```
>
> এবং ভবিষ্যতে নতুন company যুক্ত হলে তার নিজস্ব নতুন domain-ও থাকবে। তাই Laravel-এর সহজ `Route::domain('{company}.zamzamint.com')` pattern এখানে কাজ করবে না — কারণ এটা একই parent domain-এর subdomain ধরে নেয়। এর বদলে একটা **domain-to-company lookup middleware** প্রয়োজন।

### আর্কিটেকচার: Domain Resolver Middleware

```txt
ব্যবহারকারী tasneemknitindustry.com ভিজিট করল
→ Middleware রিকোয়েস্টের হোস্টনেম (tasneemknitindustry.com) দেখে
→ companies টেবিলে domain কলাম দিয়ে lookup করে কোন company এটা বের করে
→ company_id current request context-এ bind করে দেয়
→ একই HomeController/ProductController/CartController ইত্যাদি ব্যবহার হয়,
  কিন্তু এখন company_id দিয়ে শুধু সেই company-র product/order/theme দেখায়
```

### `companies` টেবিলে নতুন কলাম (Part 1.3-এর সাথে যুক্ত)

```txt
companies
---------
... (Part 1.3-এ উল্লেখিত কলামগুলো) ...
domain          — VARCHAR, UNIQUE  (যেমন: "tasneemknitindustry.com")
domain_verified — BOOLEAN, default false (DNS/SSL সঠিকভাবে সেটআপ হয়েছে কিনা)
```

### Middleware বাস্তবায়ন

```php
namespace App\Http\Middleware;

use App\Models\Company;
use Closure;
use Illuminate\Http\Request;

class ResolveCompanyFromDomain
{
    public function handle(Request $request, Closure $next)
    {
        $host = $request->getHost(); // যেমন: tasneemknitindustry.com

        $company = Company::where('domain', $host)
            ->where('is_active', true)
            ->first();

        if (! $company) {
            abort(404); // এই domain কোনো company-র সাথে match করেনি
        }

        // company context bind করা — এর পর থেকে BelongsToCompany trait
        // এই context ব্যবহার করে স্বয়ংক্রিয়ভাবে company_id scope করবে
        app()->instance('company.context', $company);

        return $next($request);
    }
}
```

### রুট স্ট্রাকচার

```php
// routes/storefront.php

Route::middleware(['web', \App\Http\Middleware\ResolveCompanyFromDomain::class])
    ->group(function () {
        Route::get('/', HomeController::class);
        Route::get('/category/{slug}', CategoryController::class);
        Route::get('/product/{slug}', ProductController::class);
        Route::get('/cart', CartController::class);
        Route::get('/checkout', CheckoutController::class);
        Route::get('/account/orders', OrderHistoryController::class);
        Route::get('/track/{orderNo}', OrderTrackController::class);
    });
```

**মূল পার্থক্য পুরনো subdomain প্ল্যানের সাথে:** আগে route definition-এর ভেতরেই `Route::domain()` দিয়ে company hardcode করার চেষ্টা ছিল। এখন route definition company-নিরপেক্ষ — কোন company তা **middleware রানটাইমে ডেটাবেস lookup করে বের করে**। এর ফলে নতুন company যুক্ত করতে কোনো নতুন route বা কোনো কোড পরিবর্তন লাগবে না — শুধু Filament থেকে একটা নতুন company row তৈরি করে তার `domain` ফিল্ড পূরণ করলেই, এবং সার্ভারে সেই domain DNS দিয়ে পয়েন্ট করলেই, নতুন company-র storefront কাজ শুরু করবে।

### সার্ভার-লেভেল কনফিগারেশন (Domain পয়েন্ট করা)

```txt
প্রতিটা company-র domain (tasneemknitindustry.com, noorsolaren.com,
zamzamgadgetbd.com, zamzamint.com) — DNS A রেকর্ড দিয়ে একই সার্ভার
(একই Coolify app/IP)-এ পয়েন্ট করতে হবে।

Coolify-তে একটা Application-এর সাথে একাধিক custom domain যুক্ত করার
সুবিধা আছে — প্রতিটা domain-এর জন্য আলাদা SSL certificate (Let's Encrypt)
স্বয়ংক্রিয়ভাবে issue হবে। এটা routes/storefront.php কোডের সাথে সম্পর্কিত
না, এটা ইনফ্রাস্ট্রাকচার লেভেলের সেটআপ — Coolify dashboard-এ প্রতিটা
domain manually যুক্ত করতে হবে।
```

### নতুন Company ভবিষ্যতে যুক্ত করার সম্পূর্ণ প্রক্রিয়া

```txt
১. Filament-এ CompanyResource থেকে নতুন company তৈরি (নাম, business_type, ইত্যাদি)
২. নতুন company-র domain ফিল্ড পূরণ (যেমন: "newbrand.com")
৩. domain owner-কে DNS A রেকর্ড সেটআপ করতে বলা (Coolify সার্ভারের IP-তে পয়েন্ট করা)
৪. Coolify dashboard-এ এই নতুন domain যুক্ত করা (SSL auto-issue হবে)
৫. Filament StorefrontSettingsResource থেকে নতুন company-র থিম রঙ, লোগো,
   ব্যানার সেটআপ করা
৬. Product/Category নতুন company-র জন্য যুক্ত করা (company_id সহ)
৭. কোনো কোড পরিবর্তন বা নতুন deployment লাগবে না — domain DNS resolve
   হওয়ার সাথে সাথেই middleware স্বয়ংক্রিয়ভাবে সঠিক company শনাক্ত করবে
```

এটাই এই আর্কিটেকচারের মূল সুবিধা — **company সংখ্যা ৪ থেকে ৫, ১০, বা তার বেশি হলেও কোনো কোড পরিবর্তন লাগবে না।** শুধু ডেটাবেসে নতুন row এবং DNS/Coolify-তে নতুন domain যুক্ত করতে হবে।

## 4.5 Filament-এ Storefront ম্যানেজমেন্ট — নতুন Resource

```txt
app/Filament/Resources/
├── StorefrontSettingsResource/   — থিম, লোগো, ব্যানার, WhatsApp নম্বর, domain ফিল্ড
├── StorefrontPageResource/       — About, Policy পেজ এডিট
├── BannerResource/                — হোমপেজ স্লাইডার, drag-drop reorder
├── CouponResource/                — ডিসকাউন্ট কোড
└── CartResource/  (read-only)    — abandoned cart দেখার জন্য
```

CompanyResource (Part 1.3)-এর ফর্মে যুক্ত হবে নতুন ফিল্ড: `domain` (টেক্সট ইনপুট, unique validation), `domain_verified` (toggle, read-only বা ম্যানুয়াল কনফার্মেশন)।

Order resource-এ আগে থেকে যা আছে তাতে যুক্ত হবে: `source` কলাম (Admin/Storefront ব্যাজ), storefront থেকে আসা order-এ "অনলাইন অর্ডার" ট্যাগ।

---

## 4.6 মডার্ন প্রোফেশনাল UI/UX ডিজাইন — Shopify-Style (Filament থেকে সম্পূর্ণ আলাদা)

> **⚠️ গুরুত্বপূর্ণ সংশোধনী (ব্যবহারকারীর সরাসরি ফিডব্যাক থেকে):** একটা আগের storefront প্রোটোটাইপ (`app.zamzamint.com/storefront`) দেখতে হুবহু Filament admin panel-এর মতো হয়ে গিয়েছিল — এটা একটা গুরুত্বপূর্ণ ডিজাইন ব্যর্থতা। ব্যবহারকারী চান **Shopify-এর মতো e-commerce layout/flow** (product grid, cart, checkout) — animation/interaction effect (যেমন Vengeance UI-এর hover/glow effect) গুরুত্বপূর্ণ না, **মূল layout ও UX pattern-ই আসল লক্ষ্য**। নিচের নিয়মগুলো এই সমস্যা পুনরাবৃত্তি রোধ করার জন্য।

### ⚠️ HARD RULE — Filament থেকে সম্পূর্ণ আলাদা Design Vocabulary

```txt
storefront কোনো অবস্থাতেই Filament-এর কোনো component, CSS class, বা design
token পুনরায় ব্যবহার করবে না। এটাই মূল কারণ যে কারণে আগের প্রোটোটাইপ admin
panel-এর মতো দেখতে হয়েছিল — এজেন্ট সম্ভবত code-reuse-এর সুবিধার জন্য
Filament-এর তৈরি করা Tailwind utility class/card style/button style copy
করেছিল।

স্পষ্টভাবে নিষিদ্ধ:
[ ] Filament-এর default card shadow/border style storefront-এ ব্যবহার করা
[ ] Filament-এর form input style (ছোট, dense, admin-style) storefront-এর
    product page বা checkout-এ ব্যবহার করা
[ ] Filament-এর sidebar navigation pattern storefront-এর navigation-এ
    অনুকরণ করা
[ ] Filament-এর Amber primary color storefront-এর প্রধান রঙ হিসেবে রাখা
    (আগের ভার্সনে এটা ইচ্ছাকৃতভাবে consistency-র জন্য রাখা হয়েছিল —
    এটাই একটা ভুল ছিল, এখন বাদ দেওয়া হলো)

storefront-এর Blade component/CSS সম্পূর্ণ আলাদা directory ও namespace-এ
থাকবে (যেমন resources/views/storefront/ এবং resources/css/storefront.css),
যাতে admin-এর Filament styling কোনোভাবেই leak না করে এবং ভবিষ্যতে কেউ ভুলে
admin component import না করে।
```

### Design System — Shopify-Style Foundation

```txt
রঙ (Color Palette) — Filament Amber থেকে সম্পূর্ণ ভিন্ন:
  Primary:   Deep Teal (#0F766E) বা ব্যবসার নিজস্ব ব্র্যান্ড রঙ —
             প্রতিটা company নিজস্ব primary রঙ সেট করতে পারবে (4.6-এর
             থিম সেকশন দেখুন), কিন্তু কোনো company-র জন্যই Filament Amber
             ব্যবহার হবে না
  Background (Light): সাদা/অফ-হোয়াইট (#FFFFFF / #FAFAFA) — Shopify storefront-এর
             মতো clean, bright background
  Background (Dark):  গভীর কিন্তু pure black না (#0A0A0A থেকে #121212) —
             OLED-friendly কিন্তু কন্ট্রাস্ট বজায় রাখা
  Success:   Emerald (#10B981) — স্টক আছে, অর্ডার কনফার্ম
  Danger:    Rose (#F43F5E) — স্টক নেই, বাকি পেমেন্ট
  Neutral:   Zinc/Slate grays — উভয় mode-এই card border, secondary text

টাইপোগ্রাফি — বড়, আত্মবিশ্বাসী, Shopify-store-এর মতো:
  Heading:  বড় ফন্ট সাইজ (h1: 36-48px), "Noto Sans Bengali" + "Inter" combo,
            bold/semibold weight — admin panel-এর ছোট, dense heading থেকে
            সম্পূর্ণ ভিন্ন scale
  Body:     16px বেস (Filament-এর প্রায়ই 13-14px ছোট admin-style টেক্সট
            থেকে আলাদা — storefront-এ পড়া আরামদায়ক হতে হবে)
  সংখ্যা:    Tabular figures (দাম, পরিমাণ align রাখার জন্য)

স্পেসিং ও Layout — generous, breathable:
  12-16px বেস গ্রিড (Filament-এর tighter admin spacing থেকে বেশি),
  বড় product image-এর চারপাশে whitespace, card-এর মধ্যে কম clutter
```

### ✅ Light/Dark Mode — উভয়ই বাধ্যতামূলক (আগের সমস্যার সরাসরি সমাধান)

> **⚠️ আগের প্রোটোটাইপ শুধু dark mode-এ ছিল — এটা ঠিক করতে হবে।**

```txt
[ ] একটা ToggleTheme Livewire/Alpine component তৈরি করুন যা storefront-এর
    header-এ (সব পেজে) থাকবে — সূর্য/চাঁদ আইকন দিয়ে toggle
[ ] Tailwind CSS-এর `dark:` variant ব্যবহার করে প্রতিটা component-এ light
    ও dark দুটো স্টাইল define করতে হবে — কোনো component শুধু এক mode-এর
    জন্য hardcode করা চলবে না
[ ] ব্যবহারকারীর পছন্দ localStorage-এ সেভ থাকবে (browser-এ, পরের ভিজিটে
    মনে রাখার জন্য) — কিন্তু এটা artifact storage না, এটা সাধারণ production
    Laravel app, তাই এখানে standard browser localStorage ব্যবহার করা যাবে
[ ] প্রথম ভিজিটে system preference (`prefers-color-scheme`) অনুযায়ী
    ডিফল্ট mode সেট হবে, তারপর ব্যবহারকারীর ম্যানুয়াল choice override করবে
[ ] Light mode ডিফল্ট হিসেবে বিবেচনা করা ভালো (Shopify storefront সাধারণত
    light-first), কিন্তু এটা ব্যবসায়িক সিদ্ধান্ত — company branding অনুযায়ী
    company-ভিত্তিক ডিফল্টও রাখা যেতে পারে (storefront_settings টেবিলে
    default_theme_mode ফিল্ড যুক্ত করার কথা বিবেচনা করুন)
```

### প্রতিটা company-র জন্য আলাদা থিম, কিন্তু একই কাঠামো

```txt
companies.settings JSON-এ:
{
  "theme": {
    "primary_color": "#0F766E",
    "logo_url_light": "...",   ← light mode-এর জন্য আলাদা লোগো (dark/light bg-তে readable)
    "logo_url_dark": "...",    ← dark mode-এর জন্য আলাদা লোগো ভার্সন
    "font_heading": "Noto Sans Bengali",
    "default_theme_mode": "light"
  }
}
```

Filament-এর `StorefrontSettingsResource`-এ color picker দিয়ে owner নিজেই company-ভিত্তিক থিম পরিবর্তন করতে পারবেন — কোনো কোড পরিবর্তন না করেই। **লক্ষ্য করুন:** এই Resource নিজে Filament-এর ভেতরেই থাকবে (admin-side tool হওয়ায় Filament style-এ থাকা ঠিক আছে), কিন্তু এটা যে storefront-এর জন্য ডেটা generate করে, সেই storefront নিজে সম্পূর্ণ আলাদা design vocabulary-তে রেন্ডার হবে।

### Shopify-Style নির্দিষ্ট Component Pattern

```txt
Product Card:
  - বড়, square বা 4:5 aspect-ratio product image (Filament-এর ছোট thumbnail
    থেকে অনেক বড়)
  - Hover-এ subtle scale/zoom (transform: scale(1.03), transition smooth)
  - নাম, দাম স্পষ্ট hierarchy-তে (দাম বড়, bold)
  - "Quick Add" বা cart icon hover-এ overlay হয়ে আসা (ঐচ্ছিক, কিন্তু Shopify-তে
    common pattern)

Navigation:
  - Top horizontal navbar (Filament-এর sidebar না) — logo বাম দিকে, category
    links মাঝে, cart icon + theme toggle + account ডান দিকে
  - Sticky header (scroll করলেও উপরে থাকে)
  - Mobile-এ hamburger menu, full-screen overlay

Cart Drawer:
  - ডান দিক থেকে slide-in (Shopify-এর standard pattern)
  - বড় product thumbnail, quantity stepper বড় ও সহজে ট্যাপযোগ্য (মোবাইল-friendly)
  - Sticky checkout বাটন নিচে, subtotal স্পষ্টভাবে দেখানো

Checkout:
  - Single-page, কিন্তু ধাপ স্পষ্টভাবে section-এ ভাগ করা (accordion না,
    Shopify-এর মতো সবগুলো section একসাথে দেখা যায়, scroll করে পূরণ করা যায়)
  - ডান পাশে (desktop-এ) sticky order summary card — ক্রমাগত visible থাকে
    ব্যবহারকারী যত নিচে scroll করুন না কেন
```

### B2B-Specific UX সিদ্ধান্ত (সাধারণ B2C শপ থেকে ভিন্ন, কিন্তু Shopify visual language বজায় রেখে)

আপনার বিজনেস wholesale reseller-কেন্দ্রিক, তাই Shopify-style visual polish-এর সাথে এই B2B প্যাটার্নগুলো মিশ্রিত করতে হবে:

1. **Tiered Pricing Table** প্রতি প্রোডাক্ট পেজে prominent ভাবে, কিন্তু Shopify-style clean table design-এ:
   ```
   ১-৯ পিস      ৫০৳/পিস
   ১০-৪৯ পিস    ৪৫৳/পিস
   ৫০+ পিস      ৪০৳/পিস
   ```
2. **MOQ ব্যাজ** কার্টে যুক্ত করার আগেই দেখানো, MOQ-এর কম quantity দিলে input ব্লক
3. **WhatsApp এ অর্ডার বাটন** — "কার্টে যুক্ত করুন"-এর সমান গুরুত্বে রাখা, ক্লিক করলে প্রি-ফিল্ড মেসেজ সহ WhatsApp খুলবে
4. **কাস্টমার Due ভিজিবিলিটি** — লগইন করা কাস্টমার নিজের বর্তমান বাকি দেখতে পারবেন (বিদ্যমান `Customer::currentBalance()` থেকে সরাসরি)
5. **Quick Reorder** — পুরনো অর্ডার থেকে এক ক্লিকে আবার অর্ডার করার বাটন

### পেজ-ভিত্তিক ডিজাইন স্পেসিফিকেশন (Shopify-Style Layout)

**হোমপেজ**
- পূর্ণ-প্রস্থ হিরো ব্যানার (বড়, ইমেজ-ফোকাসড, Filament থেকে ম্যানেজড) — Shopify storefront-এর মতো large hero
- "নতুন পণ্য" + "জনপ্রিয় পণ্য" সেকশন (horizontal scroll কার্ড, বড় product image, মোবাইলে swipe)
- ক্যাটাগরি গ্রিড (বড় ইমেজ কার্ড + নাম, image-first — শুধু আইকন না)
- ট্রাস্ট ব্যাজ: "সরাসরি চায়না থেকে" / "হোলসেল প্রাইস" / "৫০০+ রিসেলার পার্টনার"
- Sticky WhatsApp ফ্লোটিং বাটন (নিচে-ডানে, সব পেজে)
- Light/Dark toggle হেডারে সবসময় visible

**ক্যাটাগরি/প্রোডাক্ট লিস্টিং পেজ**
- বাম সাইডবার ফিল্টার (দাম রেঞ্জ, ব্র্যান্ড, স্টক স্ট্যাটাস) — মোবাইলে bottom sheet
- Grid view (বড় product card, Shopify-density — admin table-এর dense row না), sort (নতুন/দাম কম-বেশি/জনপ্রিয়)
- প্রতি কার্ডে: বড় ছবি, নাম, MOQ ব্যাজ, tiered price preview, স্টক স্ট্যাটাস

**প্রোডাক্ট ডিটেল পেজ**
- বড় Image gallery (zoom-on-hover, মোবাইলে swipe gesture) — পেজের উল্লেখযোগ্য অংশ জুড়ে
- Tiered pricing টেবিল prominently উপরে
- স্টক ব্যাজ (এভেলেবল/কামিং সুন/লিমিটেড)
- দুই সমান বাটন: "কার্টে যুক্ত করুন" + "WhatsApp এ অর্ডার করুন" — বড়, স্পষ্ট, easily tappable
- নিচে: প্রোডাক্ট ডিটেলস ট্যাব, রিলেটেড প্রোডাক্ট (horizontal scroll কার্ড)

**কার্ট (Slide-in Drawer, পেজ লোড ছাড়া)**
- প্রতি লাইনে বড় product thumbnail + quantity stepper + MOQ ভ্যালিডেশন
- রিয়েল-টাইম সাবটোটাল (Livewire reactive)
- Sticky bottom CTA মোবাইলে

**চেকআউট**
- ডেস্কটপে দুই-কলাম লেআউট: বাম দিকে ফর্ম (ঠিকানা, পেমেন্ট), ডান দিকে sticky order summary
- মোবাইলে single column, order summary উপরে collapsible
- ধাপ: ঠিকানা (সেভ করা থেকে সিলেক্ট বা নতুন) → পেমেন্ট মেথড (COD/bKash/ব্যাংক) → রিভিউ ও কনফার্ম
- বড় ফর্ম ফিল্ড, মোবাইল কীবোর্ড-ফ্রেন্ডলি input type

**অর্ডার ট্র্যাকিং**
- ভিজ্যুয়াল স্ট্যাটাস টাইমলাইন (রঙ-কোডেড): Pending → Processing → Shipped → Delivered
- গেস্ট ট্র্যাকিং (লগইন ছাড়া অর্ডার নম্বর দিয়ে)
- কুরিয়ার ট্র্যাকিং ID এবং লাইভ ডেলিভারি স্ট্যাটাস (Part 2 courier module থেকে)

### Top-Class Reference Pattern (SkyBuy/MoveOn বিশ্লেষণ থেকে)

> **ব্যবহারকারীর রেফারেন্স:** `skybuybd.com` ও `moveon.global` — দুটোই China-to-Bangladesh শিপিং বিজনেস, যাদের UI/UX-কে "টপক্লাস" হিসেবে চিহ্নিত করা হয়েছে। `skybuybd.com` থেকে কাঠামোগত তথ্য সরাসরি fetch করে যাচাই করা হয়েছে। `moveon.global` একটা heavy JavaScript SPA হওয়ায় এর নির্দিষ্ট কাঠামো verify করা সম্ভব হয়নি — তাই এই সাব-সেকশনের নিচের প্যাটার্নগুলো মূলত SkyBuy-ভিত্তিক।
>
> **⚠️ গুরুত্বপূর্ণ পার্থক্য যা মাথায় রাখতে হবে:** SkyBuy একটা **"shop-for-me" aggregator marketplace** — তারা নিজেরা কোনো নির্দিষ্ট product বানায় না, বরং China-এর যেকোনো product order করার সুবিধা দেয় (তাই তাদের "Image Search" ফিচার গুরুত্বপূর্ণ — catalog সীমাহীন)। ব্যবহারকারীর ৪টা company নিজস্ব **নির্দিষ্ট, সীমিত product catalog** বিক্রি করে। তাই নিচের প্যাটার্নগুলোর মধ্যে যেগুলো "unlimited catalog" ধরে নিয়ে ডিজাইন করা (যেমন Image Search), সেগুলো সরাসরি প্রযোজ্য নাও হতে পারে — কিন্তু UI polish ও layout pattern সম্পূর্ণ transferable।

```txt
✅ সরাসরি গ্রহণযোগ্য প্যাটার্ন (এই Master Plan-এ যুক্ত করা হলো):

✅ ১. Mega Menu Navigation — কোডে সম্পন্ন (২০২৬-০৭-০৩)
   হেডারে "Categories" hover করলে multi-column dropdown খোলে
   (resources/views/storefront/layout.blade.php), company-র active
   category থেকে সরাসরি populate হয়। Sub-category/থাম্বনেইল ডেটা model-এ
   না থাকায় flat category list দেখানো হচ্ছে (থাম্বনেইল ভবিষ্যতে Category
   মডেলে image ফিল্ড যুক্ত হলে যোগ করা যাবে)।

✅ ২. Dual Banner Set (Desktop + Mobile আলাদা) — কোডে সম্পন্ন (২০২৬-০৭-০৩)
   storefront_settings.banner_image_mobile নতুন কলাম যুক্ত, home.blade.php-এ
   `<picture>` element দিয়ে ৬৪০px-এর নিচে mobile banner, তার উপরে desktop
   banner দেখানো হয়। StorefrontSettingResource-এর Branding সেকশনে দুটো
   আলাদা upload ফিল্ড আছে।

✅ ৩. Header-এ Chat + Call Support বাটন — কোডে সম্পন্ন (২০২৬-০৭-০৩)
   storefront_settings.phone_number নতুন কলাম, হেডারে WhatsApp বাটনের পাশে
   tel: লিংক সহ Call বাটন (phone_number সেট থাকলেই দেখায়)।

✅ ৪. Curated Category Carousel (Homepage-এ multiple) — কোডে সম্পন্ন
   ProductCarousel model + product_carousel_product pivot (sort_order সহ),
   ProductCarouselResource (Storefront navigation group) দিয়ে owner টাইটেল/
   সাবটাইটেল লিখে নির্দিষ্ট product select করতে পারেন, homepage-এ active
   carousel-গুলো order অনুযায়ী render হয় (ProductCarouselTest সহ)।

✅ ৫. "কিভাবে অর্ডার করবেন" Explainer — কোডে সম্পন্ন (২০২৬-০৭-০৩)
   হোমপেজে হিরো সেকশনের পরে ৪-ধাপের static icon+text explainer
   (home.blade.php) — কোনো নতুন ডেটা/backend লাগেনি।

✅ ৬. Sister-Company Cross-Promotion — কোডে সম্পন্ন (২০২৬-০৭-০৩)
   Footer-এ "Our other brands" সেকশন — বর্তমান company বাদে বাকি published
   storefront company-গুলোর domain-লিংক দেখায় (layout.blade.php)।

✅ ৭. Mobile Bottom Navigation Bar — কোডে সম্পন্ন (২০২৬-০৭-০৩)
   মোবাইলে (sm এর নিচে) fixed bottom nav: Home, Category, Cart (badge সহ),
   Account — layout.blade.php-এ যুক্ত, main/footer-এ bottom padding/margin
   দিয়ে content overlap এড়ানো হয়েছে।

⚠️ প্রযোজ্য না / সতর্কতার সাথে বিবেচনা করতে হবে:

[ ] Image Search (ছবি দিয়ে product খোঁজা) — SkyBuy-র জন্য প্রয়োজনীয়
    কারণ তাদের catalog সীমাহীন। ব্যবহারকারীর সীমিত, নির্দিষ্ট product
    catalog-এ এটার business value কম, এবং implementation cost
    (image recognition API/ML) তুলনামূলক বেশি। এখন স্কোপে রাখা হচ্ছে না,
    ভবিষ্যতে প্রয়োজন মনে হলে আলাদাভাবে বিবেচনা করা যাবে।
[ ] "Get App" বাটন (native mobile app promotion) — Master Plan-এ কোথাও
    native app তৈরির প্ল্যান নেই। PWA (Progressive Web App) দিয়ে আংশিকভাবে
    এই অভিজ্ঞতা দেওয়া সম্ভব, কিন্তু এখন এটা স্কোপে নেই।
```

**⚠️ Implementation status নোট:** এই Top-Class Pattern-গুলোর কোনোটাই এখনো PROJECT_GUIDE.md-এ নির্দিষ্টভাবে confirmed না — অর্থাৎ এগুলো এখনো শুধু ডিজাইন, বাস্তবায়িত না। Storefront foundation (cart, checkout, tracking) সম্পন্ন হওয়ার পর এই polish feature-গুলো পরবর্তী ধাপ হিসেবে যুক্ত করা যেতে পারে।

### Frontend Implementation নোট

```txt
Tailwind CSS dark mode strategy: 'class' (media query না) ব্যবহার করুন,
যাতে ToggleTheme component manually <html> ট্যাগে 'dark' class যোগ/বাদ
দিয়ে নিয়ন্ত্রণ করতে পারে — এটা localStorage persistence-এর সাথে কাজ করার
সবচেয়ে নির্ভরযোগ্য পদ্ধতি।

tailwind.config.js-এ storefront-এর জন্য আলাদা theme extend ব্লক রাখুন
(content path দিয়ে শুধু resources/views/storefront/**/*.blade.php স্ক্যান
করানো) যাতে admin-এর Filament theme override storefront-এ bleed না করে।
```

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

## 4.8 Multi-Company + Storefront (বাস্তব Domain অনুযায়ী)

```txt
Garments Machinery → tasneemknitindustry.com
Solar Items        → noorsolaren.com
Gadget Items        → zamzamgadgetbd.com
Gift Items          → zamzamint.com

ভবিষ্যৎ নতুন company → নতুন custom domain (কোনো কোড পরিবর্তন ছাড়াই, দেখুন 4.4)
```

প্রতিটা domain-এর জন্য একই Blade component/Livewire ব্যবহার হবে, কিন্তু 4.4-এ বর্ণিত `ResolveCompanyFromDomain` middleware দিয়ে `company_id` সনাক্ত হয় এবং তার ভিত্তিতে products, theme, banner আলাদা হয় — কোনো কোড ডুপ্লিকেশন ছাড়াই।

## 4.9 AI Agent দিয়ে কাজ করার নিয়ম (Hallucination কমানোর আসল উপায়)

### Hard Rules

```txt
একটা সময়ে একটা ফিচার (Cart, তারপর Checkout, তারপর Order History — আলাদা আলাদা)
বিদ্যমান Order/Product/Customer মডেল পরিবর্তন না করে নতুন relationship/scope যুক্ত করা
নতুন প্রতিটা ফিচারের জন্য টেস্ট লেখা বাধ্যতামূলক
company_id isolation bypass করা যাবে না
স্টক লজিক ডুপ্লিকেট করা যাবে না — বিদ্যমান StockMovement workflow পুনঃব্যবহার

⚠️ নিয়ম — Admin-Storefront Sync বাধ্যতামূলক (ব্যবহারকারীর সরাসরি নির্দেশ):
storefront-এ যেকোনো নতুন changeable/dynamic component যুক্ত হলে — banner
স্লট, ব্যাজ টাইপ, homepage section, carousel, promotional text, নতুন theme
setting, বা অন্য যেকোনো কিছু যা সময়ের সাথে পরিবর্তন হতে পারে — সেটার জন্য
অবশ্যই একটা সংশ্লিষ্ট Filament Resource/field/setting তৈরি করতে হবে, যাতে
owner কোনো কোড পরিবর্তন বা redeploy ছাড়াই admin panel থেকে সেটা নিয়ন্ত্রণ
করতে পারেন। storefront-এ hardcoded (Blade ফাইলে সরাসরি লেখা) কোনো
পরিবর্তনযোগ্য কন্টেন্ট তৈরি করা নিষিদ্ধ — টেক্সট, ইমেজ, লিংক, অর্ডার/সিকোয়েন্স,
সবকিছুই ডেটাবেস থেকে আসতে হবে এবং admin panel থেকে edit-যোগ্য হতে হবে।

উদাহরণ: 4.6-এ যুক্ত হওয়া "Top-Class Reference Pattern" ফিচারগুলোর প্রতিটার
জন্য এই নিয়ম প্রযোজ্য —
  - Mega menu-র category/sub-category structure → CategoryResource-এই
    parent-child relationship দিয়ে ম্যানেজড হবে, আলাদা hardcoded menu না
  - Dual banner (desktop/mobile) → BannerResource-এ দুটো আলাদা image field
  - Curated category carousel → নতুন HomepageSection/ProductCarousel
    Resource, যেখানে owner টাইটেল ও product manually select করবেন
  - Sister-company cross-promotion section → CompanyResource-এর মধ্যে
    "cross_promote_enabled" ও display order ফিল্ড
  - "কিভাবে অর্ডার করবেন" explainer → StorefrontPageResource বা একটা
    ছোট JSON-based ordered-list field

এজেন্টের জন্য self-check প্রশ্ন প্রতিটা storefront ফিচার তৈরির আগে:
"এই কন্টেন্ট/কম্পোনেন্ট যদি ছয় মাস পর ব্যবহারকারী পরিবর্তন করতে চান,
তাহলে কি তাকে আমাকে (বা অন্য কোনো ডেভেলপারকে) আবার ডাকতে হবে, নাকি
তিনি নিজেই Filament থেকে করতে পারবেন?" — উত্তর যদি "আমাকে ডাকতে হবে" হয়,
তাহলে এই ফিচারের জন্য একটা Filament-managed field/Resource আগে তৈরি করতে হবে।

✅ ইতিমধ্যে confirmed এই নিয়ম অনুসরণ করা হয়েছে: homepage hero heading/
subheading/CTA (storefront_settings-এ admin-editable), SEO/OG metadata
(storefront settings থেকে), theme mode (company-ভিত্তিক default) — এগুলো
সবই hardcoded না হয়ে Filament-managed, এটা ভালো নজির যা বাকি নতুন
ফিচারেও অনুসরণ করতে হবে।
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

## Phase 0: Pre-requisite Stabilization (Part 0 অনুযায়ী)
```txt
✅ landed cost allocation, shipment/container tracking, PDF export, backup
   system — সব কোডে যাচাই সম্পন্ন (2026-07-05, বিস্তারিত Part 0.1)
✅ ADMIN_PASSWORD, MAIL_FROM_ADDRESS, session/queue/cache driver — কনফার্ম হয়েছে ঠিক হয়েছে
✅ composer.json (block-insecure: true, minimum-stability: stable, dompdf ^3.1) — যাচাই সম্পন্ন (2026-07-05)
✅ নতুন আবিষ্কৃত: Release and Update Safety সিস্টেম (AppRelease, CHANGELOG.md,
   backup-before-migration নিয়ম) ইতিমধ্যে কোডে আছে — Part 0.3 দেখুন
— Phase 1/2-এর অবশিষ্ট কাজের সাথে সমান্তরালে করা যেতে পারে
```

## Phase 1: Multi-Company Foundation
```txt
✅ আর্কিটেকচার সম্পূর্ণ ও ফাইল-পাথ পর্যায়ে কনফার্ম — companies টেবিল, BelongsToCompany,
   CompanyScope, CompanyContext, SetCurrentCompany middleware, company switcher UI,
   Companies Filament resource — সব নিশ্চিতভাবে কোডে আছে
✅ নতুন আবিষ্কৃত safeguard — cross-company courier booking service-layer-এ reject,
   "All Companies" view-এ company-specific write action disable
❌ বাকি — বিদ্যমান production data এখনো "Main Company"-তে, Garments/Solar/
   Gadget/Gift-এ ভাগ করা হয়নি (বিস্তারিত প্ল্যান: Part 1.9, ধাপ ৭)
✅ সম্পন্ন (2026-07-05) — Queue/scheduled command/export/backup flow-এর
   cross-cutting isolation audit; একটা route-binding isolation bug ধরা
   পড়ে ফিক্স হয়েছে (বিস্তারিত: Part 1.10)
✅ `MultiCompanyIsolationTest.php` সব company-scoped model কভার করে
   (contract test); নতুন model যোগ হলে তালিকায় যুক্ত করতে হবে
```

## Phase 2: Courier Foundation
```txt
✅ সম্পন্ন — Manual/custom booking, Steadfast API (booking+sync+encrypted
   credentials), normalized delivery status enum, status log creation,
   cross-company booking block
✅ সম্পন্ন — CourierProviderInterface/CourierManager রিফ্যাক্টর, webhook endpoint +
   signature verification + queue + retry + idempotency, CourierStatusLogResource,
   CourierWebhookLogResource, Cancel Booking/Print Label/Track action,
   courier reports (success/return ratio, COD summary, company-wise performance)
✅ সম্পন্ন (2026-07-05) — Pathao/RedX/E-Courier live API client + adapter +
   booking action; owner-এর merchant credential বসালেই লাইভ (Part 2 দেখুন)
✅ সম্পন্ন (2026-07-05) — Steadfast balance UI (Courier Providers-এ Balance action)
✅ সম্পন্ন (2026-07-05) — production monitoring/alerting: scheduled status sync,
   admin alert (sync failure/stale booking/webhook failure), Courier Health widget
```

## Phase 3: Fraud / Customer Success MVP
```txt
✅ সম্পন্ন — customer risk টেবিল, CustomerRiskService, success/return ratio
   ক্যালকুলেশন, risk score generation, order/customer-এ risk badge,
   courier booking-এর আগে warning, blacklist management (CustomerRiskProfile/
   Event/Review resources + CustomerRiskAlerts/Overview widget), risk reports
```

## Phase 4: Storefront Foundation (Lunar-এর পরিবর্তে)
```txt
✅ সম্পন্ন — storefront_settings, storefront_pages, carts, cart_items, banners,
   coupons টেবিল
✅ সম্পন্ন — Domain-based রুট স্ট্রাকচার (ResolveCompanyFromDomain middleware)
✅ সম্পন্ন — Home/ProductIndex/ProductShow/Cart/Checkout/Page controller
✅ সম্পন্ন — Design system (Shopify-style, Filament থেকে আলাদা vocabulary)
✅ সম্পন্ন — Filament-এ StorefrontSettingsResource (launch-readiness dashboard সহ)
```

## Phase 5: Cart & Checkout
```txt
✅ সম্পন্ন — CartController, CheckoutController, Order creation সরাসরি ERP
   Order মডেলে (source=storefront)
✅ সম্পন্ন — Checkout phone-based (traditional password login না) — phone
   দিয়ে existing Customer match/update বা নতুন Customer তৈরি হয়; account
   order history-ও phone দিয়েই lookup হয় (কোনো customer password/OTP
   login system এখনো কোডে নেই — ভবিষ্যতে phone OTP লগইন চাইলে আলাদা যুক্ত
   করতে হবে)
⚠️ MOQ/tiered-pricing B2B UX বিস্তারিত এখনো কোডে confirmed না — CartController/
   CheckoutController-এ MOQ ভ্যালিডেশন লজিক আছে কিনা যাচাই করা প্রয়োজন
```

## Phase 6: Order Tracking & Account Pages
```txt
✅ সম্পন্ন — Order history পেজ (AccountOrdersController), guest tracking by
   order number (OrderTrackController)
✅ সম্পন্ন — ডেলিভারি স্ট্যাটাস courier module-এর সাথে যুক্ত
```

## Phase 7: Courier API Adapters
```txt
✅ সম্পন্ন — SteadfastCourier adapter (booking API, tracking API, webhook handler)
✅ সম্পন্ন (2026-07-05) — PathaoCourier, RedxCourier, ECourier live API client +
   adapter (booking/sync/webhook); owner-এর merchant credential বসালেই লাইভ
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

---

# Part 12: WooCommerce Data Migration (সংক্ষিপ্ত নোট — পরে বিস্তারিত হবে)

## ⚠️ প্রেক্ষাপট (কেন এই নোট গুরুত্বপূর্ণ)

```txt
ব্যবহারকারীর ৪টা কোম্পানির ওয়েবসাইট বর্তমানে WooCommerce-এ চলছে।
সেখানে real, live customer ও product ডেটা আছে।
Part 4 (Custom Storefront) তৈরি হলে এই ডেটা ERP-তে না আনলে হারিয়ে যাবে।
এই মডিউলের সম্পূর্ণ বিস্তারিত প্ল্যান এখনো লেখা হয়নি — যখন Part 4 (Storefront
Foundation) শুরু হবে, ঠিক তার আগে এই নোট থেকে একটা পূর্ণ implementation
প্ল্যান বানাতে হবে।
```

## সিদ্ধান্ত এখনো বাকি — দুটো পথ বিবেচনায় আছে

```txt
পথ A: One-Time Import + WooCommerce বন্ধ
  → WooCommerce থেকে Customer/Product/Order একবার Export-Import করে
    ERP-তে আনা, তারপর WooCommerce ধীরে ধীরে বন্ধ করে নতুন storefront চালু।
  → ঝুঁকি কম, কিন্তু এক ধাক্কায় migration, SEO impact সম্ভব।

পথ B: Live Ongoing Sync
  → WooCommerce সাইট চালু রেখে REST API দিয়ে দ্বিমুখী sync রাখা।
  → কোনো বড় ধাক্কা নেই, কিন্তু stock race condition, ৪টা company-র
    আলাদা sync logic maintain করা, dual maintenance burden — এই
    জটিলতাগুলো দীর্ঘমেয়াদে বাড়বে।

সুপারিশ করা মাঝামাঝি পথ: Phased Migration
  ১. WooCommerce ডেটা (Customer + Product + Order history) one-time import
  ২. নতুন ERP-storefront তৈরি ও টেস্ট (Part 4-10 অনুযায়ী)
  ৩. সবচেয়ে ছোট company-তে (যেমন Gift Items) প্রথমে নতুন storefront লাইভ,
     WooCommerce এক সপ্তাহ read-only/backup মোডে প্যারালাল রাখা
  ৪. সব ঠিক থাকলে সেই company-র WooCommerce বন্ধ, বাকি company-গুলোতে
     একই প্যাটার্নে ধাপে ধাপে রোলআউট
```

## যা নিশ্চিত করতে হবে Part 4 শুরুর আগে

```txt
[ ] ব্যবহারকারীর সাথে confirm করা — One-time import, Live sync, নাকি Phased migration?
[ ] WooCommerce REST API credentials (Consumer Key/Secret) সংগ্রহ এবং সংরক্ষণ পদ্ধতি ঠিক করা
[ ] WooCommerce-এর Customer ডেটা ফিল্ড ম্যাপিং ERP-এর Customer মডেলের সাথে
    (যেমন: WooCommerce billing_phone → ERP Customer.phone)
[ ] WooCommerce-এর Product ডেটা ফিল্ড ম্যাপিং (SKU, price, stock, image, category)
    ERP-এর Product মডেলের সাথে
[ ] Order history আনা হবে কিনা, নাকি শুধু Customer + Product (এটা ব্যবসায়িক সিদ্ধান্ত)
[ ] Duplicate detection logic — ফোন নম্বর/email দিয়ে আগে থেকেই ERP-তে থাকা
    Customer-এর সাথে WooCommerce-এর Customer মিলিয়ে দেখা (যেমন Lead/CRM
    মডিউলের LeadConversionService-এ যে duplicate-প্রতিরোধী প্যাটার্ন ব্যবহার
    হয়েছে, এখানেও একই নীতি প্রয়োগ হবে)
[ ] Currently এই Business-Dashboard কোডবেসে Customer/Product পেজে যে
    "Import" বাটন আছে বলে ব্যবহারকারী উল্লেখ করেছেন তার backend logic
    আসলে কাজ করে কিনা যাচাই করা — PROJECT_GUIDE.md-এর Section 3/5-এ
    কোনো import feature documented নেই, তাই Part 4 শুরুর আগে এটা প্রথমে
    লাইভ সাইটে টেস্ট করে নিশ্চিত হওয়া জরুরি (ভাঙা/অসম্পূর্ণ ফিচার হতে পারে)
```

## Build Order-এ অবস্থান

```txt
Part 1 (Multi-Company) সম্পূর্ণ হওয়ার পর, Part 4 (Storefront Foundation)
শুরু হওয়ার ঠিক আগে — এই Part 12-এর সংক্ষিপ্ত নোট থেকে একটা সম্পূর্ণ,
ধাপে-ধাপে implementation প্ল্যান (আলাদা MD ফাইলে, Lead/CRM ও Investor
মডিউলের মতো বিস্তারিত ফরম্যাটে) তৈরি করতে হবে।
```

**এজেন্টের জন্য নির্দেশ:** Part 4 (Storefront)-এ পৌঁছানোর আগে, ব্যবহারকারীকে জিজ্ঞেস করুন WooCommerce migration approach নিয়ে এখনও সিদ্ধান্ত হয়েছে কিনা। সিদ্ধান্ত না হলে, Part 4-এর storefront কোড লেখা শুরু করার আগে এই বিষয়ে স্পষ্টীকরণ চান।
