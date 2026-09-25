# Bangla + English (i18n) Plan — A to Z

> **বাংলা সারসংক্ষেপ:** এই প্ল্যান অনুযায়ী স্টোরফ্রন্ট (পরে অ্যাডমিন, ইমেইল, SMS) বাংলা ও ইংরেজি দুই ভাষায় চলবে। কোন দেশের ভিজিটর কোন ভাষা ডিফল্ট দেখবে, সেটা প্রতিটা কোম্পানি Filament থেকে ঠিক করবে (যেমন বাংলাদেশ → বাংলা, বাকি সব দেশ → ইংরেজি)। ভিজিটর নিজে ভাষা বদলালে সেটাই সবসময় প্রাধান্য পাবে। এই রিপোতে যে এজেন্টই কাজ করুক, নতুন বা বদলানো প্রতিটা দৃশ্যমান লেখা একই কমিটে `lang/en.json` আর `lang/bn.json` দুই ফাইলেই যোগ করবে। নিয়মটা `CLAUDE.md`-এ বাধ্যতামূলক করা আছে।

Status: **planned** (owner-approved direction, 2026-09-25). Phases run in order; each phase is its own PR, deployable on its own, and must leave both languages working.

---

## 0. Rules every agent follows from today (even before Phase 1 ships)

These apply to **all** work in this repo, not only i18n PRs. They are mirrored in `CLAUDE.md` → "Bilingual (Bangla + English) rule".

1. **Every new or changed user-visible string is written in both languages in the same commit.**
   - In Blade/PHP: wrap it with `__('English source text')` and add the key to **both** `lang/en.json` and `lang/bn.json`.
   - The English sentence itself is the key (Laravel JSON translation style). Keep keys short, full sentences, no trailing spaces.
   - Placeholders use Laravel's `:name` syntax: `__('Order :number placed', ['number' => $order->order_number])`. Both files must use the same placeholders.
2. **Never write Bangla directly into Blade/PHP** (except inside `lang/bn.json`), and never write English UI text without `__()`. The only exceptions are admin-entered content (it is already in whichever language the owner typed) and log messages.
3. **If you cannot produce a correct Bangla translation, still add the key to `lang/bn.json` with the English text and list it under "Needs Bangla review" in `UPDATE_NOTES.md`.** Never leave a key out of one file — the parity test (Phase 1, step 1.6) will fail.
4. **Admin-entered storefront content stays admin-managed** (`CLAUDE.md` rule). Where a setting holds customer-facing text, the bilingual version is a pair of fields (`*_en` / `*_bn`) or a JSON translation column — see Phase 4. Do not put that text in `lang/*.json`.
5. **Numbers, money and dates go through the formatting helpers** (`MoneyFormatter`, and from Phase 3 `LocaleFormatter`), never `number_format()` in a view, so Bangla digits (০-৯) work everywhere.
6. **Tests assert with `__()`** (e.g. `->assertSee(__('Add to cart'))`), not hardcoded English, so the suite passes in either locale.
7. Before handoff run `php artisan test --filter=TranslationParityTest` in addition to the normal test runs.

---

## 1. Current state (verified from code, 2026-09-25)

| Area | Today |
|---|---|
| Translation files | **None.** No `lang/` directory. `config/app.php` locale = `env('APP_LOCALE', 'en')`. |
| `__()` usage in storefront | ~1 call. All storefront text is hardcoded English (54 storefront Blade files). |
| Existing Bangla | Some admin defaults are Bangla literals (e.g. `StorefrontSetting::DEFAULT_NEW_CUSTOMER_ADVANCE_MESSAGE`); delivery-area keywords already accept Bangla (`StorefrontDeliveryAreaResolver`, `BangladeshDistricts`). |
| Fonts | Admin picks fonts from `StorefrontSetting::FONT_OPTIONS` (includes Hind Siliguri / Noto Sans Bengali) and a "Bangla Optimized" typography preset exists. |
| `<html lang>` | Layout already renders `app()->getLocale()`. |
| Emails / SMS | 1 mailable (`StorefrontLoginOtp`), SMS/WhatsApp text in `StorefrontNotificationService`, CRM broadcasts. All single-language. |
| Admin panel | Filament 5, English only. |

---

## 2. Target behaviour

### 2.1 Locales
- Supported: `bn` (Bangla) and `en` (English). The list lives in one place: `config/locales.php` → `supported`.
- Adding a third language later must be a config + `lang/<code>.json` change only.

### 2.2 Which language a visitor sees — resolution order
The first rule that gives an answer wins:

1. **Explicit choice** — the visitor used the language switcher (stored in a `storefront_locale` cookie, 1 year) or a logged-in customer's saved `preferred_locale`.
2. **Country rule** — the visitor's country (see 2.3) looked up in the company's **Locale by country** table (admin-managed).
3. **Company default** — `storefront_settings.default_locale`.
4. **App default** — `config('app.locale')`.

Search-engine bots are **not** redirected; each language has its own URL (see 2.4) so both versions are indexable.

### 2.3 Country detection (no new paid service)
- Read the country from the edge/CDN header first: `CF-IPCountry` (Cloudflare), `CloudFront-Viewer-Country`, or a header name the admin sets (`storefront_settings.country_header`, default `CF-IPCountry`).
- If there is no header, the country is **unknown** → skip rule 2 and fall to the company default. No IP-geolocation database in Phase 1 (can be added later as an optional driver).
- Detection is a single class `App\Support\Locale\VisitorCountry` so tests can fake it.

### 2.4 URLs and SEO
- Default language of the company has clean URLs (`/products/...`). The other language uses a prefix (`/en/products/...` or `/bn/products/...`).
- Every page outputs `<link rel="alternate" hreflang="bn">`, `hreflang="en"`, `hreflang="x-default"` and a self-canonical.
- `sitemap.xml` (when the SEO phase of the Marketplace Pro plan ships) lists both language URLs.
- Preview routes (`/storefront/{slug}/...`) accept `?lang=bn|en` instead of a prefix and stay `noindex`.

### 2.5 Admin setting: "Language" section (Filament → Storefront Settings)
Built with Filament default components only (`AGENTS.md`).

| Field | Type | Notes |
|---|---|---|
| `default_locale` | Select (Bangla / English) | Company default (rule 3). |
| `enabled_locales` | CheckboxList | Which languages the storefront offers; switcher hidden when only one. |
| `locale_country_rules` | Repeater: Country (Select, ISO-3166 alpha-2 list) → Language (Select) | Rule 2. Example the owner may enter: `BD → bn`. **No rows are pre-filled** — owner enters real rules. |
| `country_header` | Text, default `CF-IPCountry` | Which request header carries the country. |
| `digit_style` | Select: Bangla digits in Bangla / Always Western digits | For prices and numbers (৳১,২৫০ vs ৳1,250). |
| `show_language_switcher` | Toggle | Header switcher on/off. |

---

## 3. Phases (A → Z)

Each phase lists **Do**, **Files**, **Tests**, and **Done when**. Do them in order.

### Phase 1 — Foundation (no visible change for visitors)
**Do**
1.1 Create `config/locales.php` (`supported => ['bn' => [...], 'en' => [...]]` with native name, `dir => 'ltr'`, digit set).
1.2 Create `lang/en.json` and `lang/bn.json` (start with `{}` plus the first keys you move).
1.3 Migration on `storefront_settings`: `default_locale` (string 5, default `'en'` so nothing changes until the owner picks), `enabled_locales` (json, nullable → means `[default_locale]`), `locale_country_rules` (json, nullable), `country_header` (string 60, nullable), `digit_style` (string 16, default `'western'`), `show_language_switcher` (bool, default false). Add to `$fillable`/`$casts`.
1.4 Migration on `customers`: `preferred_locale` (string 5, nullable).
1.5 `App\Support\Locale\VisitorCountry` and `App\Support\Locale\LocaleResolver` implementing 2.2 exactly (pure class, no global state; takes Request + StorefrontSetting + ?Customer).
1.6 `tests/Unit/TranslationParityTest.php`: fails if `lang/en.json` and `lang/bn.json` do not have identical key sets, if any value is empty, or if placeholders (`:word`) differ between the two files.
1.7 `tests/Unit/LocaleResolverTest.php`: every branch of 2.2 (cookie beats country, country beats default, unknown country falls through, disabled locale is ignored, invalid cookie value ignored).

**Done when:** tests green; storefront renders exactly as before.

### Phase 2 — Middleware, routing, switcher
**Do**
2.1 `App\Http\Middleware\SetStorefrontLocale`: runs **after** `ResolveCompanyFromDomain` / `SetCurrentCompany` (needs the company's setting) and **before** `SubstituteBindings` stays untouched (`CLAUDE.md` pin). Calls `LocaleResolver`, then `app()->setLocale()`, `Carbon::setLocale()`, `Number::useLocale()`.
2.2 Route prefix for the non-default language on the three storefront route groups (main domain, preview, reseller store `/store/{resellerSlug}`). Use one route-registration helper so the three groups cannot drift.
2.3 `route_localized()` helper (or a `URL::defaults` approach) so every existing `route('storefront.*')` call keeps working and emits the right prefix. Grep for `route('storefront.` — every call must go through it.
2.4 Language switcher partial (`storefront.partials.language-switcher`): plain `<a>` links to the same page in the other language + sets the cookie via a tiny `GET /locale/{code}` route that redirects back (works without JS). Visible only when `show_language_switcher` and more than one enabled locale.
2.5 Save `preferred_locale` on the customer when a logged-in customer switches.
2.6 `hreflang` + canonical tags in every storefront layout (including theme layouts, e.g. Marketplace Pro).
2.7 Queued jobs and scheduled commands that send customer text must set the locale explicitly (customer's `preferred_locale` → company default), same pattern as `CompanyContext` set/clear (`CLAUDE.md`).

**Tests:** Feature tests for each resolution rule over HTTP (fake the country header), prefix routes on all three route groups, switcher cookie round-trip, `hreflang` present, `MultiCompanyIsolationTest` untouched and green (locale rules are per company).

**Done when:** switching language works end to end even though most text is still English.

### Phase 3 — Formatting (numbers, money, dates)
**Do**
3.1 `App\Support\LocaleFormatter`: `number()`, `money()` (৳ symbol, BD grouping `১২,৩৪,৫৬৭` vs `12,34,567` — confirm grouping style with owner), `date()`, `relativeTime()`; honours `digit_style`.
3.2 Make `MoneyFormatter` delegate to it so existing calls get Bangla digits automatically.
3.3 Replace every `number_format(` in storefront views with the helper (grep list in the PR description).
3.4 Alpine/JS: expose `window.storefrontLocale = {code, digits}` and one `formatMoney()` JS helper; replace `'BDT ' + x.toFixed(2)` patterns in checkout/offer/cart views.
3.5 Phone-number input keeps accepting Bangla digits and normalises to Western before validation (checkout, OTP, track).

**Tests:** unit tests for the formatter in both digit styles; checkout accepts `০১৭০০১১১৩৩৩`.

### Phase 4 — Admin-managed content in two languages
Storefront content the owner types (hero slides, trust strip, campaign text, footer blocks, menus, pages, offers, product name/description, category names, payment-method instructions, SEO title/description).
**Do**
4.1 Decide storage per model (record the decision in this file):
   - **Settings fields** (single values on `storefront_settings`): add a JSON column `translations` keyed `{field: {bn: ..., en: ...}}` and a `StorefrontSetting::translated('field')` accessor that falls back to the base column. No per-field migrations later.
   - **Content models** (`Product`, `Category`, `StorefrontPage`, `StorefrontSlide`, `Offer`, `StorefrontPaymentMethod`): add a `translations` JSON column + a small `HasTranslations` trait (`$model->t('name')`). Base column stays the default-language value, so ERP, invoices, WooCommerce sync and reports keep working unchanged.
   - No new Composer package unless the owner approves (keeps the dependency list small).
4.2 Filament: each translatable field gets a sibling "Bangla" / "English" input inside the same section using Filament `Tabs` (default components only). Only show the tab for enabled locales.
4.3 Search: product search matches both the base column and `translations` (SQLite + MySQL compatible `LIKE` on the JSON text for now).
4.4 Cache keys in `StorefrontListingCache` include the locale.

**Tests:** fallback to base value when a translation is missing; search finds a product by its Bangla name; cache does not leak English into Bangla pages; company isolation for the new columns.

### Phase 5 — Translate storefront UI strings (the big sweep)
Do it **page by page**, one PR per group so reviews stay small:
5.1 Layout(s), header, footer, mobile nav, error pages (`StorefrontErrorPages` / `x-layouts.error`).
5.2 Home (built-in, Marketplace Pro, Noor Solar).
5.3 Listing, search, product card, product detail.
5.4 Cart, checkout, success, payment return messages.
5.5 Track, account (login/OTP/register/orders/profile/reviews), reseller.
5.6 Offers (landing blocks, checkout, thank-you), contact, complaints, static pages.
5.7 Validation messages: `lang/bn/validation.php` + `lang/en/validation.php` (field names via `attributes`), and every `ValidationException::withMessages([...])` string in storefront controllers/services through `__()`.
5.8 Flash/status messages (`->with('storefront_status', ...)`).

Per PR: grep the touched files for any remaining quoted English between tags (`>[A-Z][a-z]`) — the guard test in 5.9 should enforce it.
5.9 Add `tests/Feature/StorefrontHardcodedTextGuardTest.php`: scans `resources/views/storefront/**` for visible text not wrapped in `__()`/`{{ }}` (allow-list for brand names, units, symbols). Starts scoped to the folders already translated; each PR widens the scope.

**Done when:** a Bangla visitor sees no English UI text on any storefront page (admin-entered content aside).

### Phase 6 — Customer messages (email, SMS, WhatsApp, push)
6.1 Mailables use `->locale($customer->preferred_locale ?? $companyDefault)`; email Blade templates use `__()`.
6.2 SMS/WhatsApp templates in `StorefrontNotificationService` and CRM broadcasts: per-locale template fields (admin-managed, Phase 4 storage). SMS length: Bangla uses UCS-2 (70 chars/segment) — show a segment counter hint in Filament.
6.3 Invoice/receipt PDFs (dompdf): embed a Bangla-capable font (subset) and pick the order's locale.

### Phase 7 — Admin panel (Filament) in Bangla (optional, owner decides timing)
7.1 Filament ships Bangla translations for its own UI; enable `bn` in the panel and add a per-user `locale` preference (users table).
7.2 Translate our own labels/helper texts in `app/Filament/**` with `__()` resource by resource.
7.3 Keep enum/status **values** in English in the database; only labels translate.

### Phase 8 — Fonts & typography per language
8.1 Bangla text always uses the theme's Bangla font via `unicode-range` (U+0980–09FF) so English text keeps the English font — one font stack, no per-locale CSS files.
8.2 `lang` attribute on `<html>` drives line-height tweaks (`:lang(bn)` gets slightly taller line-height).
8.3 Coordinate with the Marketplace Pro theme plan: fonts are self-hosted per theme; **ask the owner for the English font choice before implementing** (owner requested this).

### Phase 9 — QA, rollout, measurement
9.1 Playwright smoke: home → product → cart → checkout in `bn` and `en` at 360px and 1366px; no horizontal scroll (Bangla words are longer).
9.2 Owner reviews every Bangla string on a preview company before go-live; changes go straight into `lang/bn.json`.
9.3 Roll out to one company first, then enable for others (per-company `enabled_locales`).
9.4 Track language choice counts (switcher clicks) in the existing analytics/Meta events only if the owner wants it.

---

## 4. Checklist template for any future PR (copy into the PR description)

```
Bilingual checklist
- [ ] Every new/changed visible string uses __() and exists in lang/en.json AND lang/bn.json
- [ ] Placeholders identical in both files
- [ ] Numbers/money/dates through MoneyFormatter / LocaleFormatter
- [ ] Admin-entered text stored with translations (not in lang files)
- [ ] Tests assert with __(), TranslationParityTest green
- [ ] "Needs Bangla review" list updated in UPDATE_NOTES.md (if any)
```

## 5. Open questions for the owner (answer before the phase that needs it)
- Phase 1: Company default language for each existing company (Bangla or English)?
- Phase 3: Indian/BD digit grouping (`12,34,567`) or international (`1,234,567`)?
- Phase 4: Should product names be translated, or only descriptions? (ERP invoices keep the base name either way.)
- Phase 7: Is Bangla needed in the admin panel, and when?
