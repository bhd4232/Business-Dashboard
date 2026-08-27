# স্টোরফ্রন্ট UX ফিক্স ও ফিচার — ১৪ আইটেম ডিটেইল প্ল্যান

> **Status:** Draft — Claude Code দিয়ে এক্সিকিউট করার জন্য প্রস্তুত
> **Created:** ২০২৬-০৮-১৫
> **স্কোপ:** owner-এর দেওয়া ১৪টা স্টোরফ্রন্ট বাগ/ফিচার আইটেম, প্রতিটা রিয়েল কোড ফাইল/লাইন রেফারেন্স দিয়ে গ্রাউন্ডেড।
> **নিশ্চিতকৃত সিদ্ধান্ত (owner):** আইটেম ৩ = "ফুটার বিল্ডার" (টাইপো ছিল "ওটার বিল্ডার")। ফুটার বিল্ডার **Filament ব্লক-এডিটর** দিয়ে হবে, ড্র্যাগ-ড্রপ ফ্রন্টএন্ড লাইব্রেরি দিয়ে না (আগের Offer মডিউলেও একই সিদ্ধান্ত নেওয়া হয়েছে — সামঞ্জস্যপূর্ণ)। আইটেম ৩ আর ৬ একই কাজ (ফুটার বিল্ডার + তার ভেতরে বটম ফুটার), তাই একসাথে ধাপ ৩-এ কভার করা হলো।

---

## ধাপ ১: ওয়েবসাইট ক্যাটাগরি বসানো

এটা কোডিং টাস্ক না — **অপারেশনাল/ডেটা-এন্ট্রি টাস্ক**। ক্যাটাগরি তৈরির পুরো সিস্টেম ইতিমধ্যে আছে: Filament-এ Category রিসোর্স (নাম, ছবি, আর `CategoryIconPicker` দিয়ে আইকন বেছে নেওয়ার অপশন — `app/Filament/Resources/Categories/Schemas/CategoryForm.php`), আর হোমপেজে "Top categories" সেকশন (ধাপ ৫-এ রিডিজাইন হচ্ছে) আর হেডার মেনুতে (ধাপ ১৪) এগুলো অটো দেখায়। Claude Code-এর করণীয় কিছু নেই এখানে — owner নিজে অ্যাডমিন প্যানেল থেকে ক্যাটাগরি অ্যাড করবেন। যদি "ক্যাটাগরি বসানো" বলতে আসলে অন্য কোনো নির্দিষ্ট বাগ/সমস্যা বোঝানো হয়ে থাকে (যেমন ক্যাটাগরি সেভ হচ্ছে না, বা হোমপেজে দেখাচ্ছে না), সেটা আলাদাভাবে স্পষ্ট করে জানালে এই প্ল্যানে যোগ করে দেওয়া যাবে।

---

## ধাপ ২: রিলেটেড প্রোডাক্ট গ্রিড — মোবাইলে ২, ডেক্সটপে ৫

**ফাইল:** `resources/views/storefront/products/show.blade.php`, লাইন ২৭৬-২৮৫ ("You may also like" সেকশন)।

**বর্তমান কোড (লাইন ২৭৯):**
```blade
<div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
```
বেস (mobile) ক্লাস না থাকায় ডিফল্টে ১ কলাম দেখায়, `sm:` থেকে ২ কলাম, `lg:` থেকে ৪ কলাম।

**ফিক্স:**
```blade
<div class="grid grid-cols-2 gap-3 sm:gap-5 lg:grid-cols-5">
```
— মোবাইলে বেস থেকেই `grid-cols-2` (রিকোয়ারমেন্ট অনুযায়ী প্রতি রোতে ২টা), আর `lg:grid-cols-5` (ডেক্সটপে ৫টা)। মোবাইলে `gap-3` (২-কলাম হওয়ায় কার্ড সরু, বেশি gap-এ আরও সরু দেখাবে), `sm:gap-5`-এ বড় স্ক্রিনে স্বাভাবিক gap ফিরে আসবে। `storefront.partials.product-card` কম্পোনেন্ট নিজে responsive (ফিক্সড উইডথ না), তাই এই একটা ক্লাস-বদলই যথেষ্ট, প্রোডাক্ট কার্ড partial-এ কোনো পরিবর্তন লাগবে না।

---

## ধাপ ৩ (মার্জড: আইটেম ৩ + ৬): ফুটার বিল্ডার

### ৩.১ বর্তমান অবস্থা

`resources/views/storefront/layout.blade.php`-এর ফুটার (লাইন ~৪৮০-৫৩৫ এলাকা) সম্পূর্ণ হার্ডকোডেড Blade মার্কআপ — কোম্পানি লোগো/নাম, `footerMenu` (JSON মেনু, আগে থেকেই Repeater-ভিত্তিক, দেখুন `StorefrontSetting.footer_menu`), সোশ্যাল লিংক, "Become a reseller" — সব একটাই ফিক্সড স্ট্রাকচার, কোনো সেকশন অ্যাড/রিমুভ/রিঅর্ডার করার উপায় নেই, আর কোনো "bottom bar" (কপিরাইট/লিগ্যাল লিংক সারি) নেই।

### ৩.২ নতুন কলাম: `storefront_settings.footer_blocks`

```php
// migration
Schema::table('storefront_settings', function (Blueprint $table) {
    $table->json('footer_blocks')->nullable()->after('footer_menu');
});
```

`StorefrontSetting::$fillable`-এ যোগ করুন, cast `'footer_blocks' => 'array'`।

### ৩.৩ ব্লক স্কিমা (Offer মডিউলে ব্যবহৃত একই প্যাটার্ন, `07_OFFERS_COMBO_LANDING_PAGE_PLAN.md` ধাপ ৩ দেখুন — সামঞ্জস্যের জন্য একই কনভেনশন অনুসরণ করা হলো)

```json
[
  {"id": "f1", "type": "brand_about", "data": {"show_logo": true, "text": "..."}},
  {"id": "f2", "type": "quick_links", "data": {"heading": "Quick Links", "menu": "footer_menu"}},
  {"id": "f3", "type": "contact_info", "data": {"show_phone": true, "show_email": true, "show_address": true}},
  {"id": "f4", "type": "social_links", "data": {}},
  {"id": "f5", "type": "newsletter", "data": {"heading": "Subscribe", "placeholder": "Your email"}},
  {"id": "f6", "type": "bottom_bar", "data": {"copyright_text": "© {year} {company_name}. All rights reserved.", "legal_links": [{"label": "Privacy Policy", "page_id": 12}, {"label": "Terms", "page_id": 13}]}}
]
```

**bottom_bar** ব্লকটাই আইটেম ৬-এর "বটম ফুটার" — কপিরাইট টেক্সট (`{year}`/`{company_name}` টোকেন সাপোর্ট) + legal page লিংকের ছোট সারি, ফুটারের একদম নিচে সবসময় শেষ ব্লক হিসেবে রেন্ডার হবে।

### ৩.৪ Filament ব্লক-এডিটর

`StorefrontSettingResource.php`-এ "Footer" সেকশনে (বিদ্যমান `menuRepeater('footer_menu', ...)`-এর পাশে) একটা নতুন `Repeater::make('footer_blocks')` — অ্যাড/রিমুভ/রিঅর্ডার/এডিট, প্রতিটা ব্লক-টাইপের জন্য `->visible(fn (Get $get) => $get('type') === '...')` দিয়ে প্রাসঙ্গিক সাব-ফিল্ড (Offer মডিউলের ব্লক-এডিটরের সাথে একই কোড-প্যাটার্ন, ডুপ্লিকেট না করে দুটোর মধ্যে কমন একটা `BlockRepeaterFactory`/trait বের করে নেওয়া যেতে পারে — `app/Filament/Concerns/HasBlockRepeater.php`)।

### ৩.৫ রেন্ডারিং

`layout.blade.php`-এর বিদ্যমান হার্ডকোডেড ফুটার মার্কআপ সরিয়ে:

```blade
<footer class="border-t border-gray-200 dark:border-white/10">
    @foreach ($setting->footer_blocks ?? config('storefront.default_footer_blocks') as $block)
        @includeIf('storefront.partials.footer-blocks.'.$block['type'], ['data' => $block['data']])
    @endforeach
</footer>
```

`config('storefront.default_footer_blocks')` — একটা ডিফল্ট ব্লক সেট (উপরের ৩.৩-এর মতো), যাতে `footer_blocks` কলাম খালি থাকা বিদ্যমান কোম্পানিগুলোর ফুটার হুট করে ফাঁকা না হয়ে যায় (backward-compatible ডিফল্ট)। প্রতিটা `storefront/partials/footer-blocks/{brand_about,quick_links,contact_info,social_links,newsletter,bottom_bar}.blade.php` — বিদ্যমান ফুটার মার্কআপ থেকেই কনটেন্ট টেনে নতুন partial-এ ভাগ করুন (নতুন করে ডিজাইন করা লাগবে না, শুধু মডুলার করা)।

**মাইগ্রেশন নোট:** বিদ্যমান সব কোম্পানির জন্য একটা ডাটা-মাইগ্রেশন কমান্ড (`php artisan storefront:seed-default-footer-blocks`) লিখুন যা `footer_blocks` NULL থাকা প্রতিটা `StorefrontSetting`-এ ডিফল্ট ব্লক সেট বসিয়ে দেয় — এতে অ্যাডমিন প্রথমবার এডিট পেজে গেলেই আগে থেকে সাজানো ব্লক দেখতে পাবে, খালি না।

---

## ধাপ ৪: হেডার/ফুটার থিম-ফ্লেক্সিবল

**বর্তমান অবস্থা:** `App\Support\StorefrontThemeRegistry` তিনটা থিম সাপোর্ট করে (Built-in, Marketplace Pro, Noor Solar) কিন্তু শুধু **হোমপেজ** (`views[...]`) থিম অনুযায়ী বদলায় — হেডার/ফুটার (`storefront.layout.blade.php`) সব থিমে **একই**, `@extends('storefront.layout')` সব থিমের হোমভিউ-ই ব্যবহার করে।

**ফিক্স:** `StorefrontThemeRegistry`-তে প্রতিটা থিমের definition-এ ঐচ্ছিক `'layout'` কী যোগ করুন:

```php
self::BUILT_IN => [
    'label' => 'Built-in Theme',
    'layout' => 'storefront.layout', // ডিফল্ট
    ...
],
self::MARKETPLACE_PRO => [
    ...
    'layout' => 'storefront.themes.marketplace-pro.layout', // নতুন, থিম-নির্দিষ্ট হেডার/ফুটার
],
```

`public static function layoutView(?string $theme): string` মেথড যোগ করুন (`homeView()`-এর মতোই)। প্রতিটা থিমের হোমভিউ-এ `@extends(StorefrontThemeRegistry::layoutView($setting->theme))` (স্ট্যাটিক `@extends('storefront.layout')`-এর বদলে ডাইনামিক)। যে থিমের নিজস্ব `layout` নেই, সে ডিফল্ট `storefront.layout` ব্যবহার করবে (backward-compatible) — নতুন করে সব থিমের জন্য হেডার/ফুটার লেখা বাধ্যতামূলক না, শুধু যেটার দরকার সেটাতেই কাস্টম লেআউট বানানো যাবে।

নতুন থিম-নির্দিষ্ট লেআউট ফাইল (যেমন `storefront/themes/marketplace-pro/layout.blade.php`) মূল `storefront/layout.blade.php`-এর `<head>`/মেটা/স্ক্রিপ্ট অংশ (SEO, pixel, Alpine init) অপরিবর্তিত রেখে শুধু হেডার নেভ আর ফুটার মার্কআপ থিমের ডিজাইন ভাষায় নতুন করে সাজাবে — কমন অংশ একটা `@include('storefront.partials.head-scripts')`-এ বের করে দুটো লেআউটই শেয়ার করুক, যাতে ডুপ্লিকেশন কম হয় আর pixel/SEO ফিক্স একজায়গায় করলেই সব থিমে যায়।

---

## ধাপ ৫: হোমপেজ দ্বিতীয় সেকশন (Top Categories) রিডিজাইন

**ফাইল:** `resources/views/storefront/home.blade.php`, লাইন ৫৩-৮২। বর্তমানে ছোট গোল আইকন + লেবেল, হরাইজন্টাল স্ক্রল — জেনেরিক প্যাটার্ন।

**নতুন ডিজাইন প্রস্তাব:** কার্ড-ভিত্তিক গ্রিড (গোল আইকনের বদলে):

```blade
<section id="collections" class="border-b border-gray-200 dark:border-white/10" x-reveal>
    <div class="mx-auto w-full max-w-7xl px-4 py-8 sm:px-5 lg:px-6">
        <div class="mb-6 flex items-end justify-between gap-5">
            <div>
                <h2 class="text-xl font-semibold tracking-tight sm:text-2xl">Shop by category</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Browse our full collection by category</p>
            </div>
            <a class="inline-flex min-h-11 items-center text-sm font-medium text-gray-500 hover:text-gray-950 dark:hover:text-white" href="...">See all</a>
        </div>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-6">
            @foreach ($categories as $category)
                <a
                    class="group relative flex flex-col items-center gap-3 overflow-hidden rounded-xl border border-gray-200 bg-white p-4 transition hover:-translate-y-1 hover:shadow-lg hover:shadow-gray-200/60 dark:border-white/10 dark:bg-gray-900 dark:hover:shadow-none"
                    href="..."
                    x-reveal
                >
                    <div class="grid h-14 w-14 place-items-center rounded-full bg-[var(--storefront-brand)]/10 text-[var(--storefront-brand)] transition group-hover:bg-[var(--storefront-brand)] group-hover:text-white">
                        {{-- image / icon / initial — বিদ্যমান লজিক অপরিবর্তিত (লাইন ৬৪-৭৪) --}}
                    </div>
                    <div class="text-center text-sm font-medium text-gray-800 dark:text-gray-100">{{ $category->name }}</div>
                </a>
            @endforeach
        </div>
    </div>
</section>
```

মূল পরিবর্তন: গোল-আইকন-শুধু থেকে card (rounded border, hover lift + shadow + brand-color background transition) — বিদ্যমান ছবি/আইকন/initial ফলব্যাক লজিক (home.blade.php লাইন ৬৪-৭৪) অপরিবর্তিত রাখুন, শুধু চারপাশের কন্টেইনার/গ্রিড বদলান। `x-reveal` অ্যাট্রিবিউট যোগ হলো (ধাপ ১০-এ বাস্তবায়িত হবে)।

---

## ধাপ ৭: প্রোডাক্ট ডেসক্রিপশন — রিচ কন্টেন্ট এডিটর

**সমস্যা:** `app/Filament/Resources/Products/Schemas/ProductForm.php` লাইন ৩৩৬-এ `Textarea::make('description')` — প্লেইন টেক্সট, কোনো H1/H2/H3/বোল্ড/ইমেজ ফরম্যাটিং নেই। অথচ `StorefrontPageResource.php` লাইন ৯৮-এ পেজ কন্টেন্টের জন্য `RichEditor::make('content')` আগে থেকেই ব্যবহৃত — ঠিক এই একই এক্সপেরিয়েন্স চাওয়া হয়েছে।

**ফিক্স — `ProductForm.php`:**

```php
RichEditor::make('description')
    ->required(false)
    ->fileAttachmentsDisk(fn (): string => CompanyMedia::publicDiskName())
    ->fileAttachmentsDirectory(fn (Get $get, ?Product $record): string => CompanyMedia::publicDirectory('products/description', $record, $get('company_id')))
    ->columnSpanFull(),
```

(`use Filament\Forms\Components\RichEditor;` ইম্পোর্ট করুন; `Textarea` ইম্পোর্ট আর দরকার না হলে সরান।)

**ডাটাবেস:** `products.description` কলাম যদি `TEXT` টাইপ হয় (সাধারণত হয়) তাহলে HTML কন্টেন্ট ধারণ করতে কোনো মাইগ্রেশন লাগবে না। যদি `VARCHAR`/সীমিত লেংথ হয়, `TEXT`/`LONGTEXT`-এ আপগ্রেড করার মাইগ্রেশন লাগবে — `\Schema::getColumnType('products', 'description')` দিয়ে যাচাই করুন।

**স্টোরফ্রন্ট রেন্ডারিং — `resources/views/storefront/products/show.blade.php` লাইন ২৬৫:**

বর্তমান (escaped, plain text আউটপুট):
```blade
{{ $product->description ?: 'Product details will be updated soon...' }}
```

বদলে (Filament RichEditor সার্ভার-সাইড sanitized/purified HTML জেনারেট করে, তাই raw আউটপুট নিরাপদ):
```blade
<div class="prose prose-sm max-w-none dark:prose-invert">
    {!! $product->description ?: 'Product details will be updated soon. Contact us for specifications, availability, and delivery details.' !!}
</div>
```

Tailwind Typography প্লাগইন (`prose` ক্লাস) আগে থেকে প্রজেক্টে আছে কিনা `tailwind.config.js`-এ চেক করুন — না থাকলে `@tailwindcss/typography` যোগ করুন, নাহলে H1/H2/H3/লিস্ট/ইমেজ ফরম্যাটিং ভিজুয়ালি স্টাইল ছাড়াই দেখাবে।

---

## ধাপ ৮: পেমেন্ট ক্যান্সেল → ৪০৩ এর বদলে চেকআউট পেজে রিডাইরেক্ট

**ফাইল:** `app/Http/Controllers/Storefront/CheckoutController.php`, `paymentReturn()`/`paymentReturnPreview()` মেথড (লাইন ~৪০৭-৪২৬)।

**বর্তমান কোড:**
```php
public function paymentReturn(Request $request, StorefrontPayment $payment): RedirectResponse
{
    abort_unless($request->hasValidSignatureWhileIgnoring(['invoice_number', 'trx_id']), 403);
    ...
}
```

গেটওয়ে ক্যান্সেল-রিডাইরেক্ট যদি সাইনড URL-এ (`invoice_number`/`trx_id` ছাড়া) অন্য কোনো এক্সট্রা কুয়েরি-প্যারাম যোগ করে (বা প্যারাম বাদ দেয়), তাহলে সিগনেচার মিসম্যাচ হয়ে সরাসরি `abort(403)` — কাস্টমার একটা কাঁচা "403 Forbidden" পেজ দেখে, কোনো ফ্রেন্ডলি রিডাইরেক্ট নেই।

**ফিক্স:**

```php
public function paymentReturn(Request $request, StorefrontPayment $payment): RedirectResponse
{
    if (! $request->hasValidSignatureWhileIgnoring(['invoice_number', 'trx_id', 'status', 'cancel'])) {
        // সাইন করা লিংক ছাড়া/মিসম্যাচড রিটার্ন — গেটওয়ে-স্পেসিফিক এক্সট্রা প্যারাম বা
        // ক্যান্সেল/টাইমআউট রিডাইরেক্ট হতে পারে। ৪০৩ না দেখিয়ে চেকআউটে ফেরত পাঠান।
        Log::info('Storefront payment return had an invalid/expired signature — redirecting to checkout.', [
            'payment' => $payment->getKey(),
        ]);

        return redirect()->route('storefront.checkout.show')
            ->withErrors(['payment' => 'Your payment session ended or was cancelled. Please try again.']);
    }

    [$company, $setting] = $this->domainStorefront($request);

    return $this->completePaymentReturn($payment, $company, $setting, transactionId: $request->query('trx_id'));
}
```

`paymentReturnPreview()`-এও একই প্যাটার্ন প্রয়োগ করুন (`storefront.preview.checkout.show`-তে রিডাইরেক্ট)। `['invoice_number', 'trx_id', 'status', 'cancel']` — ইগনোর-লিস্টে `status`/`cancel` যোগ করা হলো ডিফেন্সিভভাবে (PayStation বা অন্য গেটওয়ে ক্যান্সেলের সময় এই নামে প্যারাম যোগ করতে পারে) — **⚠️ আসল প্যারাম নাম PayStation-এর ডকুমেন্টেশন/লাইভ টেস্ট দিয়ে যাচাই করুন**, অনুমান করে বসানো হয়েছে।

**নিরাপত্তা নোট:** সিগনেচার-চেক শিথিল করলেও এটা নিরাপত্তা দুর্বল করছে না — `completePaymentReturn()` সবসময় `$this->payments->verifyAndFinalize()` দিয়ে গেটওয়ের ভেরিফাই API-তে সার্ভার-টু-সার্ভার কল করে অর্ডার ফাইনালাইজ করে (দেখুন `StorefrontPaymentService::verifyAndFinalize()`), তাই সিগনেচারটা শুধু "এই {payment} রেকর্ডে র‍্যান্ডম URL গেস করে কেউ ঢুকতে পারবে না" তার একটা অতিরিক্ত গার্ড, আসল পেমেন্ট-ভেরিফিকেশন না। সিগনেচার ফেইল হলে অর্ডার তৈরি হয় না, শুধু কাস্টমারকে একটা ভদ্র মেসেজসহ চেকআউটে ফেরত পাঠানো হয়।

---

## ধাপ ৯: গুগল ম্যাপ লিংক ফিল্ড

**সমস্যা:** `resources/views/storefront/contact/show.blade.php` লাইন ৬:
```php
$mapUrl = $company->address ? 'https://www.google.com/maps/search/?api=1&query='.urlencode($company->address) : null;
```
ফ্রি-টেক্সট ঠিকানা থেকে অটো-জেনারেটেড গুগল ম্যাপস সার্চ URL — বাংলাদেশি ঠিকানায় প্রায়ই ভুল/অস্পষ্ট লোকেশনে নিয়ে যায়।

**ফিক্স:**

১. `Company` মডেলে নতুন কলাম যোগ করুন:
```php
Schema::table('companies', function (Blueprint $table) {
    $table->string('google_maps_url')->nullable()->after('address');
});
```

২. `CompanyResource.php`-এ `Textarea::make('address')` (লাইন ১১৮)-এর ঠিক পরে:
```php
TextInput::make('google_maps_url')
    ->label('Google Maps link')
    ->url()
    ->maxLength(500)
    ->helperText('Google Maps-এ আপনার ঠিকানায় গিয়ে "Share" → "Copy link" চাপুন, সেই লিংক এখানে বসান। খালি রাখলে ঠিকানা থেকে অটো-জেনারেটেড (কম নির্ভুল) সার্চ লিংক ব্যবহৃত হবে।'),
```

৩. `contact/show.blade.php` লাইন ৬:
```php
$mapUrl = $company->google_maps_url
    ?: ($company->address ? 'https://www.google.com/maps/search/?api=1&query='.urlencode($company->address) : null);
```

এভাবে owner সঠিক Google Maps "Share" লিংক বসালে সেটাই ব্যবহৃত হবে (নির্ভুল পিন-পয়েন্ট লোকেশন), আর না বসালে আগের মতো ফলব্যাক অটো-জেনারেশন কাজ করবে — কোনো ব্রেকিং চেঞ্জ না, শুধু correction করার অপশন যোগ হলো (ঠিক যা owner চেয়েছেন: "যদি অলরেডি এটা এড করা থাকে তাহলে পেজ তৈরীর মডিউলে এটা কারেকশন করার অপশন রেখো" — `CompanyResource` ফর্মই এই "পেজ তৈরীর মডিউল", owner যেকোনো সময় গিয়ে লিংক বদলাতে/ঠিক করতে পারবেন)।

---

## ধাপ ১০: হোমপেজ স্ক্রলিং অ্যানিমেশন

**আবিষ্কার:** `home.blade.php` আর `themes/noor-solar/home.blade.php`-এ ইতিমধ্যে `x-reveal` অ্যাট্রিবিউট বসানো আছে (যেমন `home.blade.php` লাইন ৫৪), কিন্তু **এই Alpine ডিরেক্টিভটা কোথাও ডিফাইন করা নেই** — `layout.blade.php`-এ কোনো `Alpine.directive('reveal', ...)` রেজিস্ট্রেশন নেই। তাই এটা এখন একটা "মৃত" অ্যাট্রিবিউট, কোনো ভিজুয়াল ইফেক্ট নেই।

**ফিক্স — `layout.blade.php`-এর Alpine init স্ক্রিপ্টে যোগ করুন:**

```html
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.directive('reveal', (el) => {
            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                return; // অ্যাক্সেসিবিলিটি — মোশন-সেনসিটিভ ব্যবহারকারীদের জন্য অ্যানিমেশন স্কিপ
            }

            el.classList.add('opacity-0', 'translate-y-4');
            el.style.transition = 'opacity 600ms cubic-bezier(0.22, 1, 0.36, 1), transform 600ms cubic-bezier(0.22, 1, 0.36, 1)';

            const observer = new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        el.classList.remove('opacity-0', 'translate-y-4');
                        observer.unobserve(el);
                    }
                });
            }, { threshold: 0.15, rootMargin: '0px 0px -40px 0px' });

            observer.observe(el);
        });
    });
</script>
```

(এটা মূল Alpine.js স্ক্রিপ্ট লোড হওয়ার আগে/`alpine:init` ইভেন্টে বসাতে হবে — `layout.blade.php`-এ কোথায় Alpine CDN/init স্ক্রিপ্ট আছে সেটা খুঁজে সঠিক জায়গায় বসান, `defer`/লোড-অর্ডার নিশ্চিত করুন।)

এরপর homepage-এর প্রতিটা প্রধান সেকশনে (`hero`, "Top categories" [ধাপ ৫], active-offer countdown, প্রোডাক্ট গ্রিড, ফুটার [ধাপ ৩]) `x-reveal` অ্যাট্রিবিউট যোগ করুন যেখানে এখনো নেই। `prefers-reduced-motion` গার্ড ইতিমধ্যে ভেতরে আছে (CLAUDE.md-এ নির্দিষ্ট না থাকলেও এই কোডবেজের বিদ্যমান কনভেনশন — `image-banner.blade.php`-এও `reduced` চেক ব্যবহার হয়েছে, লাইন ৪২, সামঞ্জস্যপূর্ণ)।

---

## ধাপ ১১: হিরো ব্যানার ওভারলে টেক্সট — ফ্লেক্সিবল (ফিল্ড পূরণ থাকলেই শো)

**আবিষ্কার:** `StorefrontSlide` মডেলে `heading`/`subheading`/`cta_label`/`cta_url` ফিল্ড আগে থেকেই আছে (অ্যাডমিন ফর্মেও পূরণ করা যায়), **কিন্তু `resources/views/storefront/partials/image-banner.blade.php` (যেটা আসল স্লাইডার রেন্ডার করে) এই ফিল্ডগুলো কোথাও ব্যবহারই করে না** — শুধু ছবি + ক্লিকযোগ্য লিংক (`cta_url`) দেখায়, কোনো টেক্সট ওভারলে নেই। (নন-স্লাইড ফলব্যাক হিরো — `home.blade.php` লাইন ৭-৯ — আলাদা `$setting->hero_heading` ব্যবহার করে, স্লাইডের `heading` না।)

**ফিক্স — `image-banner.blade.php`-এ, প্রতিটা স্লাইডের `<picture>`-এর ওপর একটা কন্ডিশনাল ওভারলে যোগ করুন (লাইন ৯৯-১১২-এর কাছে):**

```blade
<div class="h-full w-full shrink-0 relative">
    @if ($slideHref)
        <a class="block h-full w-full" href="{{ $slideHref }}" aria-label="Open banner promotion">
    @endif
    <picture class="block h-full w-full">
        {{-- বিদ্যমান <source>/<img> অপরিবর্তিত --}}
    </picture>
    @if ($slideHref)
        </a>
    @endif

    @if ($slide->heading || $slide->subheading || $slide->cta_label)
        {{-- শুধু তখনই ওভারলে রেন্ডার হবে যখন অন্তত একটা ফিল্ড পূরণ করা আছে —
             খালি থাকলে এই ব্লকটা সম্পূর্ণ বাদ যাবে, ব্যানার শুধু ছবি হিসেবেই দেখাবে। --}}
        <div class="pointer-events-none absolute inset-0 flex items-center">
            <div class="mx-auto w-full max-w-7xl px-4 sm:px-5 lg:px-6">
                <div class="max-w-md rounded-lg bg-black/35 p-4 backdrop-blur-sm sm:p-6">
                    @if ($slide->heading)
                        <h2 class="text-xl font-semibold text-white sm:text-3xl">{{ $slide->heading }}</h2>
                    @endif
                    @if ($slide->subheading)
                        <p class="mt-2 text-sm text-white/90 sm:text-base">{{ $slide->subheading }}</p>
                    @endif
                    @if ($slide->cta_label)
                        <span class="pointer-events-auto mt-4 inline-flex items-center rounded-lg bg-[var(--storefront-brand)] px-5 py-2.5 text-sm font-medium text-white">
                            {{ $slide->cta_label }}
                        </span>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
```

**নোট:** ওভারলে `pointer-events-none` (বাইরের ডিভ) + ভেতরের CTA বক্সে `pointer-events-auto` — পুরো স্লাইড এমনিতেই ক্লিকযোগ্য (`<a>` র‍্যাপার), ওভারলে টেক্সট বক্স ক্লিক ব্লক করবে না। `cta_label` স্প্যান কোনো আলাদা `<a>` না (পুরো স্লাইডই লিংক), শুধু ভিজুয়াল বাটন — যদি ভবিষ্যতে `cta_url` স্লাইড-লিংক থেকে আলাদা একটা নির্দিষ্ট বাটন-লিংক হওয়া দরকার হয়, সেটা `Offer`/CRM প্ল্যানের মতো একটা আলাদা ছোট আইটেম হিসেবে পরে করা যাবে।

---

## ধাপ ১২: ইমেইল OTP — সেন্ড হয়েছে দেখায় কিন্তু আসে না

### ১২.১ মূল কারণ (কোড-যাচাইকৃত)

`app/Http/Controllers/Storefront/AccountAuthController.php::sendOtp()` (লাইন ১৮৪-২২২) — `$this->accounts->sendLoginOtp($company, $setting, $customer, $identifier)` কল হয় কিন্তু রিটার্ন ভ্যালু (bool — সফল/ব্যর্থ) **কখনো চেক হয় না**, সবসময় জেনেরিক "If a matching account exists, a 6-digit login code has been sent." মেসেজ দেখায় (এটা ইচ্ছাকৃত — অ্যাকাউন্ট এনুমারেশন এড়ানোর জন্য, তাই এই মেসেজিং প্যাটার্নটা বদলানো ঠিক হবে না)। প্রকৃত সমস্যা হলো **ইমেইল পাঠানোর ব্যাকএন্ডেই** — `StorefrontNotificationService::sendLoginOtpEmail()` Laravel-এর গ্লোবাল `.env` (`MAIL_MAILER`/`MAIL_HOST` ইত্যাদি) দিয়ে পাঠায়, আর SMS-এর মতো (`notification_credentials.sms_api_url` — প্রতি-কোম্পানি এনক্রিপ্টেড সেটিংস) **ইমেইলের জন্য কোনো প্রতি-কোম্পানি কনফিগারেশন নেই**। যদি সার্ভারের `.env`-এ `MAIL_MAILER=log` (ডিফল্ট লোকাল ভ্যালু) বা SMTP ক্রেডেনশিয়াল ভুল/অনুপস্থিত থাকে, ইমেইল কখনো বাস্তবে পাঠানো হয় না, শুধু লগে লেখা হয় বা সাইলেন্টলি ফেল করে (`sendLoginOtpEmail()`-এর try/catch ধরে ফেলে, `Log::warning` করে, কিন্তু caller-কে জানায় না)।

### ১২.২ ফিক্স — SMS-এর মতোই প্রতি-কোম্পানি ইমেইল সেটিংস

**মাইগ্রেশন লাগবে না** — `StorefrontSetting.notification_credentials` (এনক্রিপ্টেড JSON, ইতিমধ্যে SMS ক্রেডেনশিয়াল রাখে) কলামেই নতুন কী যোগ করুন:

```php
// StorefrontNotificationService.php-এ নতুন মেথড
public function mailConfigured(StorefrontSetting $setting): bool
{
    return filled(data_get($setting->notification_credentials, 'mail_host'))
        && filled(data_get($setting->notification_credentials, 'mail_username'));
}

protected function companyMailer(StorefrontSetting $setting): \Illuminate\Mail\Mailer
{
    $c = (array) $setting->notification_credentials;

    Config::set('mail.mailers.storefront_dynamic', [
        'transport' => 'smtp',
        'host' => $c['mail_host'] ?? null,
        'port' => (int) ($c['mail_port'] ?? 587),
        'encryption' => $c['mail_encryption'] ?? 'tls',
        'username' => $c['mail_username'] ?? null,
        'password' => $c['mail_password'] ?? null,
    ]);
    Config::set('mail.from', [
        'address' => $c['mail_from_address'] ?? $c['mail_username'] ?? config('mail.from.address'),
        'name' => $c['mail_from_name'] ?? config('mail.from.name'),
    ]);

    return Mail::mailer('storefront_dynamic');
}

public function sendLoginOtpEmail(StorefrontSetting $setting, string $email, string $companyName, string $code): bool
{
    try {
        $mailer = $this->mailConfigured($setting) ? $this->companyMailer($setting) : Mail::mailer();
        $mailer->to($email)->send(new StorefrontLoginOtp($companyName, $code));

        return true;
    } catch (\Throwable $exception) {
        Log::warning('Storefront login OTP email failed', ['error' => $exception->getMessage()]);

        return false;
    }
}
```

(সিগনেচার বদলে `StorefrontSetting $setting` প্যারামিটার যোগ হলো — কল-সাইট `CustomerAccountService::sendLoginOtp()`-এ আপডেট করুন।)

`StorefrontSettingResource.php`-তে বিদ্যমান SMS গেটওয়ে সেটিংস সেকশনের পাশে "Email (SMTP)" সেকশন — `notification_credentials.mail_host`/`mail_port`/`mail_username`/`mail_password` (`->password()->revealable()`)/`mail_encryption` (Select: tls/ssl/none)/`mail_from_address`/`mail_from_name` ফিল্ড — CLAUDE.md-এর "external credentials always admin-configurable" নিয়ম অনুযায়ী।

**Fallback:** `mailConfigured($setting)` false হলে গ্লোবাল `.env` মেইলার দিয়েই পাঠানোর চেষ্টা হবে (আগের আচরণ), যাতে যেসব কোম্পানি .env-লেভেল মেইল কনফিগ করেই কাজ করছিল তাদের কিছু না ভাঙে — নতুন প্রতি-কোম্পানি সেটিংস শুধু ওভাররাইড অপশন, বাধ্যতামূলক না।

### ১২.৩ যাচাই

প্রথমে **`.env`-এর `MAIL_MAILER` কনফিগারেশন সার্ভারে আসলেই কাজ করছে কিনা** যাচাই করুন (`php artisan tinker` দিয়ে টেস্ট মেইল পাঠিয়ে) — এটা কোডের বাগ না-ও হতে পারে, প্রোডাকশন সার্ভারে SMTP কখনো সেটআপই হয়নি এমনও হতে পারে। উপরের প্রতি-কোম্পানি সেটিংস UI থাকলে owner নিজেই একটা কাজ-করা SMTP (Gmail/Mailgun/Brevo ইত্যাদি) বসিয়ে সমস্যা সমাধান করতে পারবেন, সার্ভার `.env` টাচ না করেই।

---

## ধাপ ১৩: রিসেলার সেকশন — টগল অন/অফ

**বর্তমান:** `layout.blade.php`-এ "Become a reseller"/"Reseller status" লিংক অ্যাকাউন্ট-ড্রপডাউন (লাইন ৩২৬-৩২৮) আর ফুটারে (লাইন ৫২৯-৫৩০) **সবসময়** দেখায়, কোনো on/off সুইচ নেই।

**ফিক্স:**

১. মাইগ্রেশন: `storefront_settings`-এ `reseller_program_enabled` (boolean, default true — বিদ্যমান আচরণ ভাঙবে না) কলাম।
২. `StorefrontSettingResource.php`-এ, বিদ্যমান `marketplace_*_enabled` টগলগুলোর (লাইন ১৬৭-১৮৬) ঠিক একই প্যাটার্নে:
```php
Toggle::make('reseller_program_enabled')
    ->label('Show reseller program')
    ->default(true)
    ->helperText('Off করলে অ্যাকাউন্ট মেনু আর ফুটার থেকে "Become a reseller" লিংক লুকানো থাকবে। রিসেলার রুট/ড্যাশবোর্ড কাজ করবে, শুধু লিংক দেখাবে না।'),
```
৩. `layout.blade.php` লাইন ৩২৬ আর ৫২৯-এ `@if ($setting->reseller_program_enabled)` র‍্যাপার যোগ করুন।

(ফুটার এই ধাপে যদি ইতিমধ্যে ব্লক-বিল্ডার-এ [ধাপ ৩] সরানো হয়ে থাকে, তাহলে ফুটারের রিসেলার লিংক `quick_links`/কাস্টম ব্লক ডাটার ভেতরে যাবে, আর `reseller_program_enabled` টগল সেই ব্লকের ভিজিবিলিটিও নিয়ন্ত্রণ করবে — বাস্তবায়নের সময় ধাপ ৩-এর সাথে সমন্বয় করে নিন, কোন ধাপ আগে করা হচ্ছে তার ওপর নির্ভর করে।)

---

## ধাপ ১৪: হেডার ক্যাটাগরি মেনুতে আইকন

**আবিষ্কার:** `Category` মডেলে `icon` ফিল্ড + `CategoryIconPicker` (Filament কম্পোনেন্ট, `app/Filament/Forms/Components/CategoryIconPicker.php`) + `StorefrontCategoryIcons` সাপোর্ট ক্লাস — **আইকন সিলেকশন সিস্টেম ইতিমধ্যে সম্পূর্ণ তৈরি**, আর হোমপেজ "Top categories" সেকশনে ব্যবহৃতও হচ্ছে (`storefront.partials.category-icon`)। শুধু **হেডার নেভিগেশন বার**-এ (`layout.blade.php` লাইন ৩৭১-৩৭৮, `$headerMenu` লুপ) আইকন দেখানো হয় না — সেখানে শুধু টেক্সট লেবেল।

**ফিক্স — নতুন কোনো ফিল্ড লাগবে না, বিদ্যমান `Category::$icon` reuse:**

`layout.blade.php`-এর `$resolveMenu` ক্লোজারে (লাইন ৯৪-১১৩ এলাকা), `type === 'category'` আইটেমের জন্য রেজলভ করার সময় সংশ্লিষ্ট `Category` মডেল থেকে `icon`/`image` তথ্যও সাথে নিয়ে আসুন (এখন শুধু `slug` আনা হচ্ছে, লাইন ৯২-এর কাছে) — `$menuCategorySlugs`-কে `$menuCategoryData` (slug + icon + image সহ একটা associative array/collection) দিয়ে প্রতিস্থাপন করুন।

হেডার নেভ লুপে (লাইন ৩৭১-৩৭৮):
```blade
@foreach ($headerMenu as $menuItem)
    <a
        class="inline-flex min-h-10 items-center gap-1.5 border-b-2 transition ..."
        href="{{ $menuItem['url'] }}"
        ...
    >
        @if ($menuItem['type'] === 'category' && ($icon = $menuItem['icon'] ?? null))
            <span class="grid h-5 w-5 place-items-center text-[var(--storefront-brand)]">
                @include('storefront.partials.category-icon', ['icon' => $icon, 'iconClass' => 'h-4 w-4'])
            </span>
        @endif
        {{ $menuItem['label'] }}
    </a>
@endforeach
```

মোবাইল মেনু লুপে (লাইন ৪৩৫-৪৩৭) একই প্যাটার্ন প্রয়োগ করুন। এভাবেই "ড্যাশবোর্ড মেনু বিল্ডার থেকে যেটা এড করা হবে সেটাই শো করবে" — অ্যাডমিন আলাদা কিছু করবে না, ক্যাটাগরির নিজস্ব আইকন (যেটা Category রিসোর্সে সেট করা আছে) অটোমেটিক হেডার মেনুতেও প্রতিফলিত হবে, দুই জায়গায় (হোমপেজ কার্ড + হেডার নেভ) সবসময় সিঙ্কড থাকবে — "ফ্লেক্সিবল এবং মডার্ন UX" রিকোয়ারমেন্ট পূরণ হয় কারণ আইকন ম্যানেজমেন্টের জন্য একটাই সোর্স-অফ-ট্রুথ (Category মডেল), ডুপ্লিকেট এন্ট্রির ঝামেলা নেই।

---

## টেস্ট চেকলিস্ট

```txt
[ ] ধাপ ২: /products/{slug} পেজে মোবাইল ভিউপোর্টে (< 640px) রিলেটেড প্রোডাক্ট ২ কলামে,
    ডেক্সটপে (≥ 1024px) ৫ কলামে দেখাচ্ছে
[ ] ধাপ ৩: ফুটার ব্লক Repeater-এ ব্লক অ্যাড/রিমুভ/রিঅর্ডার করলে পাবলিক ফুটারে সাথে সাথে
    প্রতিফলিত হচ্ছে; footer_blocks NULL থাকা পুরনো কোম্পানির ফুটার ভাঙেনি (ডিফল্ট ফলব্যাক)
[ ] ধাপ ৪: একই কোম্পানিতে থিম বদলালে (Built-in ↔ Marketplace Pro) হেডার/ফুটার-ও বদলাচ্ছে,
    যে থিমের কাস্টম লেআউট নেই সেটা এখনো ডিফল্ট লেআউট দেখাচ্ছে
[ ] ধাপ ৫: হোমপেজ ক্যাটাগরি সেকশন নতুন কার্ড ডিজাইনে, hover/focus স্টেট কাজ করছে
[ ] ধাপ ৭: প্রোডাক্ট এডিটে H1/H2/H3/বোল্ড/ইমেজ দিয়ে ডেসক্রিপশন লিখে সেভ করলে পাবলিক প্রোডাক্ট
    পেজে সঠিক ফরম্যাটিং সহ রেন্ডার হচ্ছে (XSS-সেফ — script ট্যাগ ইনজেক্ট করে যাচাই করুন)
[ ] ধাপ ৮: sandbox পেমেন্টে গিয়ে ইচ্ছাকৃতভাবে ক্যান্সেল/ব্যাক বাটনে ক্লিক করে ৪০৩-এর বদলে
    checkout পেজে বার্তাসহ ফেরত আসছে কিনা
[ ] ধাপ ৯: Company-তে google_maps_url বসিয়ে Contact পেজে "Find on Map" ক্লিক করলে সঠিক
    লোকেশনে যাচ্ছে; খালি রাখলে পুরনো ফলব্যাক লিংক কাজ করছে
[ ] ধাপ ১০: হোমপেজ স্ক্রল করলে সেকশনগুলো ফেড+স্লাইড-আপ অ্যানিমেশনে আসছে;
    prefers-reduced-motion অন থাকা ব্রাউজারে অ্যানিমেশন স্কিপ হচ্ছে
[ ] ধাপ ১১: একটা স্লাইডে heading/subheading/cta_label পূরণ করে ওভারলে দেখা যাচ্ছে,
    আরেকটা খালি রেখে ওভারলে সম্পূর্ণ অনুপস্থিত (শুধু ছবি) কিনা
[ ] ধাপ ১২: নতুন SMTP সেটিংসে একটা টেস্ট ইমেইল OTP পাঠিয়ে ইনবক্সে (স্প্যাম ফোল্ডারেও চেক)
    আসছে কিনা কনফার্ম; ভুল ক্রেডেনশিয়াল দিলে গ্রেসফুলি ফেইল করছে (কাস্টমারকে এখনো জেনেরিক
    মেসেজ দেখাচ্ছে, কিন্তু Log-এ প্রকৃত এরর যাচ্ছে) কিনা
[ ] ধাপ ১৩: টগল অফ করলে হেডার/ফুটার থেকে রিসেলার লিংক অদৃশ্য, রিসেলার রুট ম্যানুয়াল URL
    দিয়ে গেলে এখনো কাজ করছে (শুধু লিংক লুকানো, ফিচার বন্ধ না)
[ ] ধাপ ১৪: একটা ক্যাটাগরিতে আইকন সেট করে সেটা হেডার নেভ + মোবাইল মেনু দুই জায়গাতেই
    দেখা যাচ্ছে, আইকন-বিহীন ক্যাটাগরিতে আগের মতো শুধু টেক্সট
[ ] পুরো php artisan test (কোনো --env flag ছাড়া) পাস করছে, বিশেষত storefront-related টেস্ট
    ফাইলগুলো (StorefrontHomeTest, StorefrontCheckoutTest ইত্যাদি যা আগে থেকে আছে)
[ ] npm run build (নতুন CSS ক্লাস/Alpine স্ক্রিপ্ট যোগ হওয়ায় দরকার)
```

---

## ভেরিফিকেশন ও হ্যান্ডঅফ (CLAUDE.md অনুযায়ী)

```txt
[ ] CHANGELOG.md (minor/patch — একাধিক ছোট স্টোরফ্রন্ট ফিক্স+ফিচার, প্রতিটা আইটেম আলাদা বুলেটে)
    + UPDATE_NOTES.md, কমিটের আগে
[ ] নতুন company-owned মডেল কোনোটা তৈরি হয়নি এই প্ল্যানে (শুধু বিদ্যমান StorefrontSetting/
    Company-তে কলাম যোগ), তাই MultiCompanyIsolationTest-এ নতুন এন্ট্রি লাগার কথা না —
    তবে ধাপ ৩-এর footer_blocks company-scoped StorefrontSetting-এরই অংশ কিনা কনফার্ম করুন
[ ] owner-এর স্পষ্ট অনুমোদন ছাড়া git commit/push না করা
```

---

## অগ্রাধিকার/নির্ভরতা নোট

- ধাপ ২, ৮, ৯, ১০, ১১, ১২, ১৩, ১৪ — প্রতিটা স্বাধীন, ছোট, কম-ঝুঁকির ফিক্স, যেকোনো ক্রমে আলাদাভাবে করা যায়।
- ধাপ ৩ (ফুটার বিল্ডার) সবচেয়ে বড় কাজ — ধাপ ১৩ (রিসেলার টগল)-এর ফুটার-অংশ আর ধাপ ৪ (থিম-ফ্লেক্সিবল ফুটার)-এর সাথে ওভারল্যাপ করে, তাই এই তিনটা একসাথে পরিকল্পনা করে একই সিটিং-এ করা ভালো (আলাদা করলে দুইবার একই ফাইল টাচ করতে হবে)।
- ধাপ ৫ (হোমপেজ) আর ধাপ ১০ (স্ক্রল অ্যানিমেশন) একসাথে করা সুবিধাজনক — নতুন কার্ড ডিজাইনেই `x-reveal` বসিয়ে দিলে একবারেই টেস্ট করা যায়।
