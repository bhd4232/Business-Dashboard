# ZamZam Storefront Redesign Plan — Amazon-Style Professional E-Commerce

> **Status:** Approved plan (owner: Abdullah) — implementation pending
> **Created:** 2026-07-14
> **Reference:** skybuybd.com single product page (https://skybuybd.com/product/abb-0437761594290)
> **Related docs:** `ECOMMERCE_PLAN.md`, `business_dashboard_master_plan_v2_custom_storefront.md`

---

## ১. লক্ষ্য (Goals)

বর্তমান স্টোরফ্রন্ট (Blade + Tailwind + Vite, multi-company) এর ভিত্তির উপর একটি প্রফেশনাল, Amazon-ধাঁচের ই-কমার্স অভিজ্ঞতা:

1. **হোম পেজ:** এনিমেটেড হিরো স্লাইডার, ইমেজসহ মডার্ন ক্যাটাগরি কার্ড, প্রোডাক্ট সেকশন/ক্যারোসেল।
2. **প্রোডাক্ট পেজ:** skybuybd-স্টাইল — গ্যালারি, tiered pricing টেবিল, ভ্যারিয়েন্ট (size/color) টেবিল থেকে সরাসরি Add, শিপিং/কস্ট ব্রেকডাউন, সাজেশন গ্রিড।
3. **চেকআউট:** ওয়ান-পেজ গেস্ট চেকআউট — নাম + ফোন + ঠিকানা + এরিয়া + পেমেন্ট, লগইন ছাড়াই।
4. **পেমেন্ট:** COD, bKash/Nagad ম্যানুয়াল (TrxID), bKash গেটওয়ে API — সব credential অ্যাডমিন-কনফিগারেবল encrypted settings।
5. **পারফরম্যান্স:** সুপার-ফাস্ট লোডিং — অপটিমাইজড ইমেজ, ক্যাশিং, মিনিমাল JS।

**নীতিমালা (CLAUDE.md অনুযায়ী):** স্টোরফ্রন্টে দৃশ্যমান সব কনটেন্ট (স্লাইড, ক্যাটাগরি ইমেজ, ব্যানার, অফার টেক্সট) Filament অ্যাডমিন থেকে ম্যানেজেবল হবে — কোনো হার্ডকোড নয়। নতুন company-owned মডেল `BelongsToCompany` + `CompanyScope` ব্যবহার করবে এবং `MultiCompanyIsolationTest`-এ যুক্ত হবে।

---

## ২. টেক অ্যাপ্রোচ

- **Stack অপরিবর্তিত:** Laravel Blade + Tailwind + Vite। ভারী JS ফ্রেমওয়ার্ক নয় — ইন্টারঅ্যাকটিভিটির জন্য **Alpine.js** (~15KB) যোগ হবে (slider, gallery, quantity, drawer)।
- **Progressive enhancement:** JS ছাড়াও সব ফর্ম কাজ করবে (বর্তমান form-POST প্যাটার্ন থাকবে); Alpine শুধু UX উন্নত করবে।
- **Cart interactions AJAX-enhanced:** add-to-cart এ পেজ রিলোডের বদলে fetch + mini-cart drawer আপডেট (fallback: বর্তমান redirect flow)।

---

## ৩. হোম পেজ রিডিজাইন

### ৩.১ হিরো স্লাইডার (এনিমেটেড)
- বর্তমান single static banner-এর বদলে **multi-slide carousel**: প্রতি স্লাইডে image (desktop + mobile), heading, subheading, CTA label + link, sort order, active toggle।
- **নতুন মডেল:** `StorefrontSlide` (company-owned) + Filament resource (repeater নয়, আলাদা resource — image upload, schedule start/end date ঐচ্ছিক)।
- এনিমেশন: CSS-based fade/slide transition, autoplay (5s), dots + arrows, swipe (Alpine touch), `prefers-reduced-motion` সম্মান করবে। প্রথম স্লাইডের ইমেজ `fetchpriority="high"` + preload — LCP দ্রুত রাখতে।

### ৩.২ ক্যাটাগরি কার্ডস (ইমেজসহ)
- `Category` মডেলে **`image` কলাম** (migration) + Filament category form-এ image upload।
- হোমে গ্রিড: মোবাইলে horizontal scroll-snap row (Amazon app-স্টাইল), ডেস্কটপে 4–6 কলাম গ্রিড। কার্ড = ছবি + নাম + প্রোডাক্ট সংখ্যা, hover-এ subtle zoom/lift এনিমেশন। ইমেজ না থাকলে বর্তমান initial-letter fallback।

### ৩.৩ প্রোডাক্ট সেকশনসমূহ
- বিদ্যমান carousels (`$carousels`) থাকবে; যোগ হবে: **Flash Sale / অফার স্ট্রিপ** (countdown timer — অ্যাডমিন থেকে অফার title, discount %, end datetime), **New Arrivals**, **Best Sellers** (order items থেকে computed, cached)।
- প্রোডাক্ট কার্ড আপগ্রেড: discount badge (আগের দাম কাটা + % off), sold count (ঐচ্ছিক), rating stars (রিভিউ ফেজে), স্থায়ী Add বাটন (মোবাইলে hover নেই তাই opacity-0 প্যাটার্ন বাদ)।
- **Trust strip:** ডেলিভারি/রিটার্ন/পেমেন্ট আইকন রো — টেক্সট অ্যাডমিন-সেটিংস থেকে।
- Scroll-reveal এনিমেশন: lightweight IntersectionObserver fade-up, `prefers-reduced-motion` guard।

### ৩.৪ হেডার/নেভিগেশন (Amazon-স্টাইল)
- Sticky header: logo + **প্রমিনেন্ট সার্চ বার** (ক্যাটাগরি dropdown সহ, live suggest পরের ফেজে) + cart icon (count badge) + track order।
- দ্বিতীয় সারি: category nav (desktop), মোবাইলে hamburger → slide-in drawer।
- **Mini-cart drawer:** cart-এ add করলে ডান দিক থেকে drawer — items, subtotal, "Checkout" বাটন।

---

## ৪. প্রোডাক্ট পেজ (skybuybd রেফারেন্স)

Layout: দুই কলাম (মোবাইলে stacked) —

### ৪.১ গ্যালারি (বাম)
- Main image + thumbnail strip (product images — `ProductImage` relation না থাকলে multi-image support যোগ হবে), click/hover-এ সুইচ, মোবাইলে swipe। Zoom-on-hover (desktop)।

### ৪.২ তথ্য ও ক্রয় (ডান)
- নাম, sold count, rating (পরের ফেজে), **অফার ব্যাজ + countdown** (সক্রিয় অফার থাকলে)।
- **Tiered pricing টেবিল** (skybuy-স্টাইল): `1+ / 20+ / 500+` quantity break অনুযায়ী দাম — নতুন `ProductPriceTier` মডেল (company-owned, ঐচ্ছিক; না থাকলে single price)। বিদ্যমান MOQ লজিকের সাথে সমন্বিত।
- **ভ্যারিয়েন্ট টেবিল:** color selector + size-ভিত্তিক রো (size | দাম | স্টক | qty stepper | Add) — বিদ্যমান variant সিস্টেম ব্যবহার করে। একাধিক size একসাথে কার্টে যোগ করা যাবে।
- **কস্ট/শিপিং ব্রেকডাউন প্যানেল** (import প্রোডাক্টের জন্য): By Air / By Sea per-kg রেট, আনুমানিক ওজন, advance % / due % হিসাব — ECOMMERCE_PLAN-এর landed cost ভিশনের সাথে সংযুক্ত; রেটগুলো অ্যাডমিন-সেটিংস। লোকাল/স্টক প্রোডাক্টে এই প্যানেল লুকানো থাকবে।
- **Sticky action bar (মোবাইল):** স্ক্রিনের নিচে fixed "Add to Cart" + "Buy Now" (Buy Now = কার্টে যোগ করে সরাসরি checkout)।
- Tabs: Description | Specification (key-value attributes) | Shipping & Return।
- নিচে **Related products** গ্রিড (একই category, cached)।

---

## ৫. ওয়ান-পেজ ইজি চেকআউট

একটাই পেজ, তিনটা ভিজ্যুয়াল ব্লক — কোনো step/লগইন নেই:

1. **ডেলিভারি তথ্য:** নাম, ফোন (BD format validation: `01[3-9]xxxxxxxx`), ঠিকানা, **ডেলিভারি এরিয়া select** (ঢাকার ভিতরে / ঢাকার বাইরে — চার্জ অ্যাডমিন-কনফিগারেবল `delivery_charge_inside` / `delivery_charge_outside`), ঐচ্ছিক note। Email ঐচ্ছিক ও collapsed।
2. **পেমেন্ট মেথড (radio cards):**
   - **Cash on Delivery** — ডিফল্ট।
   - **bKash/Nagad (Send Money):** সিলেক্ট করলে নম্বর + নির্দেশনা দেখাবে (অ্যাডমিন-সেটিংস থেকে), ইনপুট: sender number + TrxID → অর্ডার `payment_status = pending_verification`, অ্যাডমিন Filament থেকে verify করবে।
   - **bKash গেটওয়ে (API):** Tokenized Checkout — place order → bKash পেমেন্ট পেজ → callback-এ verify → `paid`। Sandbox/live credential সব encrypted settings ফিল্ড; pre-order advance flow (বিদ্যমান) এর সাথে একীভূত হবে।
3. **অর্ডার সামারি (sticky, ডান):** item list + qty inline edit, subtotal, ডেলিভারি চার্জ (এরিয়া বদলালে live update), advance due (pre-order থাকলে), **মোট** — তারপর বড় "অর্ডার কনফার্ম করুন" বাটন।

UX বিধি: সর্বোচ্চ ৪টি required ফিল্ড; ফোন থেকে রিটার্নিং কাস্টমার চেনা গেলে ঠিকানা autofill (পরের ফেজ); সাবমিটে button loading state + double-submit guard; success পেজে order no, WhatsApp/call বাটন, track link। ভ্যালিডেশন এরর inline, বাংলা মেসেজ।

**ভাষা:** স্টোরফ্রন্ট UI স্ট্রিং `lang/bn` + `lang/en` লোকালাইজেশন ফাইলে — অ্যাডমিন-সেটিংসে ডিফল্ট ভাষা টগল।

---

## ৬. পারফরম্যান্স (সুপার-ফাস্ট লোডিং)

- **ইমেজ:** আপলোডে Intervention Image দিয়ে WebP conversion + প্রি-জেনারেটেড সাইজ (thumb 400px / medium 800px / large 1600px), `srcset` + `sizes`, সব below-fold ইমেজে `loading="lazy"` + `decoding="async"`, hero প্রথম স্লাইড eager + preload। সব ইমেজে width/height attribute → zero CLS।
- **ক্যাশিং:** হোম পেজ ডেটা (slides, categories, featured, best sellers) per-company cache (৫–১০ মিনিট, admin save-এ invalidate); layout-এর nav category/footer page কুয়েরিগুলো view composer-এ সরিয়ে cache (বর্তমানে প্রতি রিকোয়েস্টে layout-এর ভিতরে কুয়েরি চলে)।
- **Asset:** Vite build, Alpine deferred, কোনো external font blocking নয় (font-display: swap / system stack), critical CSS ছোট রাখা।
- **HTTP:** static asset-এ far-future cache headers, gzip/brotli; ছবি `storage` symlink থেকে সরাসরি সার্ভ।
- **Budget:** হোম পেজ LCP < 2.5s (3G-fast), transferred JS < 60KB, প্রতি পেজ কুয়েরি সংখ্যা eager-loading দিয়ে নিয়ন্ত্রিত (debugbar দিয়ে ভেরিফাই)।

---

## ৭. অ্যাডমিন (Filament) সংযোজন

| আইটেম | ধরন |
|---|---|
| Storefront Slides | নতুন resource (image, heading, CTA, order, active, schedule) |
| Category image | বিদ্যমান Category form-এ upload |
| Offer/Flash sale | নতুন resource (title, %, end datetime, product scope) |
| Price tiers | Product form-এ repeater |
| Payment settings | encrypted fields: bKash API creds, ম্যানুয়াল bKash/Nagad নম্বর ও নির্দেশনা, COD on/off |
| Delivery charges | inside/outside Dhaka amounts |
| Payment verification | Order list-এ pending_verification filter + verify action (audit logged) |
| Trust strip / shipping rates (air/sea) | storefront settings ফিল্ড |

---

## ৮. ইমপ্লিমেন্টেশন ফেজ

**Phase 1 — Foundation & Home (৩–৪ দিন):** Alpine setup, header/nav + mini-cart drawer, hero slider (model + admin + frontend), category image + cards, product card v2, trust strip, image pipeline (WebP/srcset), caching।
**Phase 2 — Product page (২–৩ দিন):** multi-image gallery, variant টেবিল, tiered pricing, sticky mobile bar, tabs, related products, shipping breakdown প্যানেল।
**Phase 3 — Checkout & payments (৩–৪ দিন):** one-page checkout redesign, delivery area + charge, COD + ম্যানুয়াল bKash/Nagad + verification flow, bKash গেটওয়ে API, success পেজ, bn লোকালাইজেশন।
**Phase 4 — Polish (১–২ দিন):** offer countdown, scroll animation, best sellers, Lighthouse audit + performance budget verify।

প্রতিটি ফেজে: feature tests (নতুন model isolation test সহ), `php artisan test` (কোনো `--env` flag ছাড়া), `npm run build`, CHANGELOG + UPDATE_NOTES আপডেট, owner approval-এর পর commit।

---

## ৯. Out of scope (পরের ধাপ)

রিভিউ/রেটিং, উইশলিস্ট, কুপন, লাইভ সার্চ suggest, customer login/অ্যাকাউন্ট ড্যাশবোর্ড আপগ্রেড, SMS/email নোটিফিকেশন, Nagad গেটওয়ে API — `ECOMMERCE_PLAN.md` রোডম্যাপ অনুযায়ী পরে।
