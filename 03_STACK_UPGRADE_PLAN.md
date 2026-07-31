# Stack Upgrade Plan — Laravel, Filament, Livewire, PHP লেটেস্ট ভার্সনে

> **Status:** Draft — অনুমোদনের অপেক্ষায়
> **Created:** ২০২৬-০৭-১৭
> **সুযোগ:** PHP, Laravel, Livewire, Filament লেটেস্ট স্টেবল ভার্সনে আপগ্রেড + `shahariar-ahmad/courier-fraud-checker-bd` প্যাকেজ সরিয়ে কাস্টম ইমপ্লিমেন্টেশন দিয়ে প্রতিস্থাপন।

---

## ০. এক নজরে — বর্তমান বনাম টার্গেট

```txt
                বর্তমান (কোডবেজে যাচাইকৃত)     →    টার্গেট
PHP             ^8.2                            →    ^8.4  (দীর্ঘতম সাপোর্ট উইন্ডো)
Laravel         ^12.0 (ইনস্টলড 12.62.0)         →    ^13.0
Livewire        ^3.0 (Filament-এর মাধ্যমে)      →    ^4.0
Filament        ^4.11.4 (ইনস্টলড 4.11.7)        →    ^5.0
Blade           আলাদা কোনো ভার্সন নেই — Laravel-এর
                সাথেই বান্ডলড (illuminate/view)  →    Laravel 13-এর সাথে অটো
```

**নোট:** Blade-এর নিজস্ব কোনো ভার্সন নম্বর নেই — এটা `laravel/framework`-এরই অংশ। তাই "Blade আপডেট" মানে Laravel আপডেট, আলাদা কোনো কাজ নেই।

**Filament v4→v5 এর ঝুঁকি কম কেন:** Filament টিম নিজেরাই বলেছে v4 আর v5-এর মধ্যে কোনো ফাংশনাল পার্থক্য নেই — শুধু Livewire v4 সাপোর্ট যোগ হয়েছে। তাই মূল ঝুঁকি Filament-এর API পরিবর্তনে নয়, Livewire v3→v4 আন্ডারনিয়াথ পরিবর্তনে (প্রতিটা ইন্টারঅ্যাক্টিভ কম্পোনেন্ট রি-রেন্ডার হয়)।

**⚠️ প্রি-শর্ত (এই আপগ্রেড শুরুর আগে):**

```txt
[ ] প্রোডাকশন ডাটাবেসের সম্পূর্ণ ব্যাকআপ নেওয়া (বিদ্যমান Backups পেজ থেকে)
[ ] স্টেজিং/ডেমো এনভায়রনমেন্টে প্রথমে পুরো আপগ্রেড চালিয়ে যাচাই করা, প্রোডাকশনে সরাসরি নয়
[ ] বিদ্যমান পুরো টেস্ট স্যুট (php artisan test, কোনো --env flag ছাড়া) বর্তমান
    স্ট্যাকে ১০০% পাস করছে কিনা কনফার্ম করা — এটাই আপগ্রেডের regression-detection baseline
[ ] owner approval — এটা মাল্টি-ডে ইনফ্রা কাজ, কোনো নতুন ফিচার এর মাঝখানে যোগ না করা ভালো
```

---

## ধাপ ১: `shahariar-ahmad/courier-fraud-checker-bd` সরানো (আপগ্রেডের আগে)

### ১.১ কেন আগে এটা করা দরকার

এই প্যাকেজ `composer.json`-এ `"*"` (কোনো ভার্সন কনস্ট্রেইন্ট ছাড়া) হিসেবে যোগ করা — অর্থাৎ এটা Laravel 13/PHP 8.4-এর সাথে কম্প্যাটিবল কিনা তার কোনো ঘোষণা নেই, এবং maintainer ছোট/individual (আপডেট না হওয়ার ঝুঁকি বেশি)। Laravel 13 আপগ্রেডের সময় এই একটা অনিশ্চিত ডিপেন্ডেন্সি পুরো `composer update` আটকে দিতে পারে। তাই আগে সরিয়ে কাস্টম কোড দিয়ে প্রতিস্থাপন করাই নিরাপদ — Laravel/Filament আপগ্রেডের রিস্ক থেকে এই রিস্ক আলাদা করে ফেলা হচ্ছে।

### ১.২ বর্তমান ব্যবহার (কোডবেজ-যাচাইকৃত)

শুধু একটা ফাইলে ব্যবহৃত: `app/Services/ExternalCourierFraudService.php` (১৭৩ লাইন)। এই সার্ভিস ফোন নম্বর দিয়ে Pathao/Steadfast/RedX মার্চেন্ট প্যানেল থেকে ডেলিভারি হিস্টরি/সাকসেস-রেশিও আনে, ২৪ ঘণ্টা ক্যাশ করে, আর কখনো exception থ্রো করে না (কোনো কুরিয়ার fail করলে শুধু বাদ যায়, অর্ডার ব্লক হয় না)। প্যাকেজ থেকে ব্যবহৃত ৩টা ক্লাস:

```php
ShahariarAhmad\CourierFraudCheckerBd\Services\PathaoService::pathao($phone, ...$credentials)
ShahariarAhmad\CourierFraudCheckerBd\Services\SteadfastService::steadfast($phone, ...$credentials)
ShahariarAhmad\CourierFraudCheckerBd\Services\RedxService::getCustomerDeliveryStats($phone, ...$credentials)
```

### ১.৩ কাস্টম প্রতিস্থাপন

```bash
mkdir -p app/Services/CourierFraud
```

তিনটা ছোট, নিজস্ব HTTP client ক্লাস — প্রতিটা `Illuminate\Support\Facades\Http` দিয়ে সরাসরি কুরিয়ারের মার্চেন্ট API কল করবে:

```php
// app/Services/CourierFraud/PathaoFraudClient.php
// app/Services/CourierFraud/SteadfastFraudClient.php
// app/Services/CourierFraud/RedxFraudClient.php
```

প্রতিটার ইন্টারফেস অভিন্ন রাখুন (drop-in replacement, `ExternalCourierFraudService`-এর বাকি লজিক না বদলে শুধু `DRIVER_SERVICE_MAP`/কল সাইট বদলাতে হবে):

```php
interface CourierFraudClient
{
    /**
     * @return array{success_ratio: float|null, total_parcels: int, ...}|null
     *   null মানে fail/unconfigured — ExternalCourierFraudService এটা গ্রেসফুলি বাদ দেবে
     */
    public function checkByPhone(string $phone, array $credentials): ?array;
}
```

**⚠️ গুরুত্বপূর্ণ — API এন্ডপয়েন্ট ও রেসপন্স ফরম্যাট:** এই তিনটা ক্লায়েন্ট লেখার আগে প্রতিটা কুরিয়ারের অফিসিয়াল মার্চেন্ট API ডকুমেন্টেশন (Pathao Merchant API, Steadfast API, RedX Merchant API) থেকে সঠিক এন্ডপয়েন্ট, অথেনটিকেশন মেথড (token/API key), আর রেসপন্স স্কিমা যাচাই করে নিতে হবে — পুরনো প্যাকেজের সোর্স কোড (`vendor/shahariar-ahmad/courier-fraud-checker-bd/src/`) রেফারেন্স হিসেবে দেখে নেওয়া যেতে পারে (এখনো ইনস্টলড আছে, সরানোর আগে কপি করে রাখুন), কারণ ওখানেই বর্তমানে কাজ করা এন্ডপয়েন্ট/হেডার ফরম্যাট আছে।

### ১.৪ Composer থেকে সরানো

```bash
composer remove shahariar-ahmad/courier-fraud-checker-bd
```

`composer.json`-এ require ব্লক থেকে এন্ট্রি অটো মুছে যাবে।

### ১.৫ টেস্ট

```txt
[x] বিদ্যমান কুরিয়ার-ফ্রড-সংক্রান্ত টেস্ট (যদি থাকে) নতুন ক্লায়েন্ট দিয়ে পাস করছে
    — ExternalCourierFraudCheckTest (6 tests) অপরিবর্তিত অবস্থায় নতুন ক্লায়েন্টের
    বিপরীতে পাস করেছে
[x] প্রতিটা নতুন Client-এ Http::fake() দিয়ে সাকসেস ও ফেইলিউর দুই কেসের ইউনিট টেস্ট
    — tests/Unit/Services/CourierFraud/{Pathao,Steadfast,Redx}FraudClientTest.php
    (১৪টা টেস্ট)
[x] ExternalCourierFraudService::checkByPhone() কোনো কুরিয়ার fail করলেও exception
    থ্রো করে না তা কনফার্ম (বিদ্যমান আচরণ অপরিবর্তিত) — try/catch + null-চেক অক্ষত
[x] CheckExternalCourierFraudJob (checkout flow-এ ব্যবহৃত) স্বাভাবিকভাবে চলছে —
    সার্ভিসের পাবলিক ইন্টারফেস অপরিবর্তিত, job/controller-এ কোনো পরিবর্তন লাগেনি
```

**সম্পন্ন — ২০২৬-০৮-০১।** `app/Services/CourierFraud/` এ নতুন ইন্টারফেস + ৩টা ক্লায়েন্ট
(`PathaoFraudClient`, `SteadfastFraudClient`, `RedxFraudClient`), `ExternalCourierFraudService`
রিওয়্যার করা হয়েছে, প্যাকেজ `composer remove` দিয়ে সরানো হয়েছে। পূর্ণাঙ্গ বিস্তারিত
`UPDATE_NOTES.md`-এর "2026-08-01 - Stack Upgrade Plan, Step 1" এন্ট্রিতে। পুরো
`php artisan test` স্যুট — ৫৪৫ পাস (২৮৭৫ অ্যাসারশন), কোনো রিগ্রেশন নেই। কমিট এখনো
owner-এর অনুমোদনের অপেক্ষায়।

---

## ধাপ ২: PHP 8.2 → 8.4

### ২.১ কেন 8.3 না, সরাসরি 8.4

PHP 8.3-এর সিকিউরিটি সাপোর্ট শেষ ডিসেম্বর ২০২৭-এ, PHP 8.4-এর নভেম্বর ২০২৮-এ। যেহেতু লক্ষ্য "যত দিন সম্ভব হাত না দেওয়া", 8.4-ই সঠিক পছন্দ। Laravel 13 ন্যূনতম PHP 8.3 চায়, তাই 8.4 requirement-এর মধ্যেই পড়ে।

### ২.২ ইমপ্লিমেন্টেশন

```txt
[x] Coolify-তে অ্যাপের Build Pack/Dockerfile/nixpacks.toml-এ PHP ভার্সন 8.4-এ পরিবর্তন
    — এই রিপোতে Dockerfile নেই, nixpacks.toml-ই আসল Coolify বিল্ড কনফিগ; সেখানে কোনো
    PHP ভার্সন হার্ডকোড করা নেই, Nixpacks composer.json থেকেই অটো-ডিটেক্ট করে — তাই
    নিচের composer.json পরিবর্তনই যথেষ্ট, আলাদা এডিট লাগেনি
[x] composer.json-এ "php": "^8.2" → "php": "^8.4" আপডেট
[x] স্টেজিং সার্ভারে rebuild করে dependency/extension সমস্যা (gd, intl, imagick ইত্যাদি
    এক্সটেনশন লোড হচ্ছে কিনা) যাচাই — **লোকালিই যাচাই হয়ে গেছে।** owner-এর অনুরোধে
    windows.php.net থেকে অফিসিয়াল PHP 8.4.24 (TS, VS17, x64) বাইনারি ডাউনলোড করে
    (sha256 ভেরিফাই করে) Laragon-এ পাশাপাশি ইনস্টল করা হয়েছে, php.ini-তে ঠিক একই
    এক্সটেনশন সেট (gd, intl, mbstring, pdo_sqlite, pdo_mysql, sodium, xsl, zip
    ইত্যাদি) কনফিগার করে `composer update` চালানো হয়েছে — সফল, `composer.lock`
    রিফ্রেশড (Laravel 12.62.0→12.64.0, Filament 4.11.7→4.12.5, Livewire 3.8.1→3.8.3)।
    এছাড়াও staging-এ একটা প্রথম-বার-ফ্রেশ-বিল্ডেই একটা আসল বাগ ধরা পড়েছে ও ফিক্স হয়েছে
    (নিচে দেখো — nginx.template.conf crash loop)
[x] php artisan test পুরো স্যুট চালিয়ে দেখুন — deprecation warning থাকলে সেগুলো এই
    ধাপেই ধরা পড়বে, Laravel আপগ্রেডের সাথে গুলিয়ে ফেলার আগে — **আসল PHP 8.4.24-এ পুরো
    স্যুট চালানো হয়েছে: ৫৫৫ পাস (২৯৭৪ অ্যাসারশন), কোনো deprecation/regression নেই।**
```

**নোট:** PHP 8.3/8.4-এ কিছু deprecated ফিচার (implicit nullable params, কিছু dynamic property warning) স্ট্রিক্টার হয়েছে — Laravel/Filament নিজেরা এসব সামলায়, কিন্তু প্রজেক্টের নিজস্ব কোডে (custom Service ক্লাসগুলো) কোনো constructor-এ `public function __construct($x = null)` টাইপের implicit-nullable প্যাটার্ন থাকলে PHP 8.4-এ deprecation notice আসতে পারে — টেস্ট রান করলে লগে দেখা যাবে।

**সম্পন্ন — ২০২৬-০৮-০১।** কোড/ডকুমেন্টেশন + লোকাল PHP 8.4.24 ভেরিফিকেশন — দুটোই শেষ।
staging Coolify-তে প্রথম ফ্রেশ বিল্ডে `nginx.template.conf`-এর একটা প্রি-এক্সিস্টিং বাগ
(IS_LARAVEL আর NIXPACKS_PHP_FALLBACK_PATH দুটো স্বাধীন `$if` ব্লক একসাথে `location /`
রেন্ডার করছিল → nginx `duplicate location` এরর → কন্টেইনার restart loop) ধরা পড়ে ফিক্স
করা হয়েছে — এটা PHP 8.4-এর কারণে না, এই ফাইলের যেকোনো ফ্রেশ বিল্ডেই হতো। বিস্তারিত
`UPDATE_NOTES.md`-এর "2026-08-01 - Stack Upgrade Plan, Step 2" এন্ট্রিতে। এরপর staging-এ
রিডিপ্লয় করে আসল রানটাইম কনফার্মেশন বাকি (`APP_KEY` env var-ও staging resource-এ সেট করা
লাগবে — লগে "Your app key is not set" ওয়ার্নিং এসেছে)।

---

## ধাপ ৩: Laravel 12 → 13

### ৩.১ কম্প্যাটিবিলিটি প্রি-চেক

```bash
composer why-not laravel/framework "^13.0"
```

এটা চালিয়ে দেখুন কোন প্যাকেজ Laravel 13 আটকাচ্ছে। সন্দেহভাজন তিনটা:

```txt
barryvdh/laravel-dompdf   — সাধারণত দ্রুত সাপোর্ট আসে, যাচাই করুন প্যাকেজিস্টে
openspout/openspout       — Laravel-নির্ভর নয় (স্ট্যান্ডঅ্যালোন spreadsheet লাইব্রেরি),
                            সমস্যা হওয়ার কথা না
filament/filament         — একই সাথে v5-এ যাচ্ছে (ধাপ ৫), তাই ^13.0 এর সাথেই কনফ্লিক্ট-ফ্রি হবে
```

### ৩.২ আপগ্রেড কমান্ড

```bash
composer require laravel/framework:^13.0 --with-all-dependencies
```

### ৩.৩ Laravel-এর নিজস্ব upgrade guide অনুসরণ

`laravel.com/docs/13.x/upgrade` পেজের চেকলিস্ট লাইন-বাই-লাইন মেলান — বিশেষভাবে দেখুন:

```txt
[ ] config/*.php ফাইলে কোনো নতুন ডিফল্ট কী যোগ হয়েছে কিনা (যেমন cache/session/queue
    কনফিগ স্ট্রাকচার পরিবর্তন) — publish করা কনফিগ ফাইলগুলো diff করুন
[ ] কোনো deprecated helper/facade মেথড ব্যবহার হচ্ছে কিনা যা 13-এ সরানো হয়েছে
[ ] bootstrap/app.php-এ middleware/exception handling রেজিস্ট্রেশন প্যাটার্ন
    (Laravel 11+ থেকে যেভাবে আছে) এখনো ভ্যালিড কিনা — CLAUDE.md-এ উল্লিখিত
    "SetCurrentCompany must stay pinned before SubstituteBindings in bootstrap/app.php"
    নিয়মটা আপগ্রেডের পরেও অক্ষত আছে কিনা বিশেষভাবে পরীক্ষা করুন (multi-company
    isolation-এর মূল ভিত্তি এখানেই)
```

### ৩.৪ টেস্ট

```txt
[ ] php artisan test পুরো স্যুট (কোনো --env flag ছাড়া)
[ ] MultiCompanyIsolationTest বিশেষভাবে পাস করছে কিনা কনফার্ম (bootstrap.php পরিবর্তনের
    সবচেয়ে বেশি ঝুঁকিপূর্ণ জায়গা এটাই)
[ ] npm run build (frontend asset pipeline Laravel ভার্সনের সাথে সরাসরি জড়িত না,
    কিন্তু vite.config.js-এ কোনো Laravel plugin ভার্সন pin থাকলে যাচাই)
```

---

## ধাপ ৪: Livewire 3 → 4

Filament v5 ইনস্টল করলে Livewire v4 নিজে থেকেই dependency হিসেবে টেনে আসবে (ধাপ ৫-এ)। আলাদা করে `composer require livewire/livewire:^4.0` চালানোর দরকার নেই — Filament-এর কম্পোজার কনস্ট্রেইন্টই এটা রেজলভ করবে। তবে:

```txt
[ ] প্রজেক্টে যদি কোনো কাস্টম Livewire কম্পোনেন্ট থাকে (Filament-এর বাইরে — যেমন
    "Inbox" পেজের মতো কিছু, যেটা ইতিমধ্যে Livewire-নির্ভর), সেগুলো Livewire v4-এর
    breaking change guide (livewire.com/docs/upgrade) অনুযায়ী চেক করুন — Livewire v4
    backward compatible রাখার চেষ্টা করেছে (v3 কম্পোনেন্ট "মূলত" চলে), কিন্তু
    wire:model/lazy loading-সংক্রান্ত এজ কেস থাকতে পারে
[ ] AppServiceProvider::boot()-এ Livewire::component() দিয়ে যেসব কাস্টম কম্পোনেন্ট
    ম্যানুয়ালি রেজিস্টার করা আছে (ListProducts, CreateProduct ইত্যাদি) — এই
    রেজিস্ট্রেশন প্যাটার্ন Livewire v4-এ এখনো ভ্যালিড কিনা যাচাই করুন
```

---

## ধাপ ৫: Filament 4 → 5

### ৫.১ আপগ্রেড কমান্ড

```bash
composer require filament/filament:^5.0 --with-all-dependencies
php artisan filament:upgrade
```

`filament:upgrade` কমান্ডটা বেশিরভাগ ছোট breaking change (mechanical rename ইত্যাদি) অটো ফিক্স করে — কিন্তু অফিসিয়াল upgrade guide (`filamentphp.com/docs/5.x/upgrade-guide`) অনুযায়ী ম্যানুয়াল ধাপগুলোও যাচাই করা আবশ্যক, স্ক্রিপ্ট সব ধরবে না।

### ৫.২ যা বিশেষভাবে দেখা দরকার (এই প্রজেক্টের জন্য)

```txt
[ ] সব Filament Resource ইতিমধ্যে v4-এর নতুন Schemas-ভিত্তিক স্ট্রাকচার ব্যবহার করে
    (Resources/{Name}/Schemas/, Tables/, Pages/) — v3→v4 মাইগ্রেশন আগেই হয়ে গেছে,
    তাই v4→v5-এ এই স্ট্রাকচার বদলাবে না (আগেই বলা হয়েছে: "no functional changes")
[ ] কাস্টম Filament Page ক্লাসগুলো (Backups, Inbox, CloudStorageSettings ইত্যাদি —
    এই প্রজেক্টেই সাম্প্রতিক যোগ হওয়া) properly রেন্ডার হচ্ছে কিনা — এগুলো blade view
    + Livewire property বাইন্ডিং ব্যবহার করে, Livewire v4 রেন্ডারিং পরিবর্তনে
    সবচেয়ে বেশি স্পর্শকাতর
[ ] wire:model.defer ব্যবহার হওয়া ফর্মগুলো (Backups, CloudStorageSettings পেজে
    আছে) — Livewire v4-এ .defer মডিফায়ারের আচরণ/সিনট্যাক্স বদলেছে কিনা চেক করুন
[ ] Repeater/RelationManager-নির্ভর ফর্ম (Purchases, Quotations ইত্যাদি) — এগুলো
    জটিল reactive() ফিল্ড ব্যবহার করে, বেশি করে ম্যানুয়াল স্মোক টেস্ট দরকার
```

### ৫.৩ ম্যানুয়াল স্মোক টেস্ট চেকলিস্ট

```txt
[ ] প্রতিটা মূল Resource-এ Create/Edit/List পেজ খুলছে, সেভ হচ্ছে
[ ] FileUpload ফিল্ড (Product image, gallery, Category, StorefrontSlide, Company
    logo, StorefrontSetting) আপলোড + saveUploadedFileUsing() হুক (ImageOptimizerService)
    ঠিকমতো কাজ করছে — এটা কাস্টম ক্লোজার-নির্ভর, Livewire আপগ্রেডে সবচেয়ে বেশি
    ভাঙার ঝুঁকি এখানেই
[ ] Inbox পেজ (Conversation, polling, chat UI) স্বাভাবিকভাবে কাজ করছে
[ ] CloudStorageSettings পেজের ফর্ম সাবমিট/টেস্ট-কানেকশন বাটন কাজ করছে
[ ] Dashboard widget/chart সব লোড হচ্ছে
```

### ৫.৪ টেস্ট

```txt
[ ] php artisan test পুরো স্যুট — Filament resource-এর Livewire টেস্ট
    (livewire()->test(...) প্যাটার্ন ব্যবহার হয়ে থাকলে) বিশেষভাবে
[ ] সব ফাইল-আপলোড-সংক্রান্ত টেস্ট (যদি থাকে) পাস করছে
```

---

## ধাপ ৬: চূড়ান্ত যাচাই ও ডিপ্লয়

```txt
[ ] পুরো php artisan test স্যুট (কোনো --env flag ছাড়া) ১০০% পাস
[ ] npm run build সফল, কোনো JS কনসোল এরর নেই (dev tools দিয়ে ম্যানুয়াল চেক)
[ ] স্টেজিং-এ কমপক্ষে ২৪-৪৮ ঘণ্টা রিয়েল ব্যবহার (অর্ডার প্লেস, Filament CRUD,
    Conversation Inbox, AI Auto-Reply — সব মডিউল ছুঁয়ে) করে কোনো রানটাইম এরর না
    পাওয়া কনফার্ম করুন
[ ] composer.lock, composer.json, package.json/package-lock.json — সব ফাইনাল
    ভার্সন কমিট করার আগে CHANGELOG.md + UPDATE_NOTES.md আপডেট (CLAUDE.md নিয়ম
    অনুযায়ী, মেজর ভার্সন বাম্প হিসেবে ক্যাটাগরাইজ)
[ ] owner-এর explicit approval-এর পরই commit/push (CLAUDE.md commit policy)
[ ] প্রোডাকশন ডিপ্লয়ের ঠিক আগে আরেকবার প্রোডাকশন ডাটাবেস ব্যাকআপ
```

---

## সারসংক্ষেপ — কাজের ক্রম

```txt
ধাপ ১ (fraud checker সরানো, স্বাধীন)
        ↓
ধাপ ২ (PHP 8.4)
        ↓
ধাপ ৩ (Laravel 13)
        ↓
ধাপ ৪+৫ (Livewire 4 + Filament 5, একসাথে — যেহেতু Filament v5 ইনস্টলেই Livewire v4 আসবে)
        ↓
ধাপ ৬ (চূড়ান্ত যাচাই + ডিপ্লয়)
```

প্রতিটা ধাপের পর `php artisan test` চালিয়ে পরের ধাপে যাওয়া — একসাথে সব আপগ্রেড করে একবারে ডিবাগ করার চেষ্টা করবেন না, তাহলে কোন পরিবর্তনে কোন ভাঙন হলো বোঝা কঠিন হয়ে যাবে।
