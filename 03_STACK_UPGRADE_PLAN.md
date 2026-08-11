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

**সম্পন্ন — ২০২৬-০৮-০১।** কোড/ডকুমেন্টেশন + লোকাল PHP 8.4.24 ভেরিফিকেশন + staging-এ
সম্পূর্ণ end-to-end রানটাইম কনফার্মেশন — সব শেষ। staging Coolify-তে প্রথম ফ্রেশ বিল্ডে দুটো
প্রি-এক্সিস্টিং ইনফ্রা বাগ ধরা পড়ে ফিক্স করা হয়েছে:

1. `nginx.template.conf`-এর `IS_LARAVEL`/`NIXPACKS_PHP_FALLBACK_PATH` duplicate
   `location /` বাগ (কোড ফিক্স, `staging` ব্র্যাঞ্চে কমিট করা — PHP 8.4-এর কারণে না,
   এই ফাইলের যেকোনো ফ্রেশ বিল্ডেই হতো)।
2. **⚠️ CRITICAL — Production-কে প্রভাবিত করবে:** MySQL 8.4-এ নতুন ইউজার ডিফল্টে
   `caching_sha2_password` ব্যবহার করে, কিন্তু Nixpacks-এর PHP 8.4 বিল্ডের `mysqlnd`
   ড্রাইভারে এর জন্য দরকারি RSA/OpenSSL সাপোর্ট কম্পাইল করা নেই (compile-time সীমাবদ্ধতা,
   কোনো SQL/config দিয়ে PHP সাইড থেকে ফিক্স করা যায় না) — ফলে
   `SQLSTATE[HY000] [2054] authentication method unknown to the client
   [caching_sha2_password]` এরর দিয়ে অ্যাপ ক্র্যাশ করে। **Production-এর আসল ডাটাবেজেও
   (`SELECT plugin_name, plugin_status FROM information_schema.plugins WHERE
   plugin_name = 'mysql_native_password'` দিয়ে যাচাই করা হয়েছে) এই একই ইউজার
   `caching_sha2_password` ব্যবহার করছে — মানে production PHP 8.4-এ আপগ্রেড হলে
   সাইট সম্পূর্ণ ডাউন হয়ে যাবে, যতক্ষণ না এই ফিক্সটা প্রথমে প্রয়োগ করা হয়।**

   **ফিক্স (staging-এ যাচাই করা, প্রোডাকশনে ধাপ ৬-এর আগে বাধ্যতামূলক প্রয়োগ করতে হবে):**
   - MySQL Database Resource-এর Coolify UI → Persistent Storage → Files → নতুন file mount:
     `/etc/mysql/conf.d/native-password.cnf` কন্টেন্ট `[mysqld]\nmysql_native_password=ON`
   - Database Resource রিস্টার্ট (production-এ এটা ডাটাবেজের সংক্ষিপ্ত downtime ঘটাবে —
     মেইনটেন্যান্স উইন্ডোতে করতে হবে)
   - `mysql>` শেলে: `ALTER USER 'ইউজারনেম'@'%' IDENTIFIED WITH mysql_native_password BY
     'বিদ্যমান পাসওয়ার্ড'; FLUSH PRIVILEGES;`

staging-এ পুরো ফ্লো verified: `php artisan migrate --force` সফল (fresh DB → সব টেবিল
তৈরি), `ADMIN_EMAIL`/`ADMIN_PASSWORD` env var দিয়ে `php artisan db:seed --force` চালিয়ে
super-admin তৈরি, `/admin` প্যানেলে সফল লগইন কনফার্ম করা হয়েছে। বিস্তারিত
`UPDATE_NOTES.md`-এর "2026-08-01 - Stack Upgrade Plan, Step 2" এন্ট্রিতে।

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
[x] config/*.php ফাইলে কোনো নতুন ডিফল্ট কী যোগ হয়েছে কিনা (যেমন cache/session/queue
    কনফিগ স্ট্রাকচার পরিবর্তন) — publish করা কনফিগ ফাইলগুলো diff করুন
[x] কোনো deprecated helper/facade মেথড ব্যবহার হচ্ছে কিনা যা 13-এ সরানো হয়েছে
[x] bootstrap/app.php-এ middleware/exception handling রেজিস্ট্রেশন প্যাটার্ন
    (Laravel 11+ থেকে যেভাবে আছে) এখনো ভ্যালিড কিনা — CLAUDE.md-এ উল্লিখিত
    "SetCurrentCompany must stay pinned before SubstituteBindings in bootstrap/app.php"
    নিয়মটা আপগ্রেডের পরেও অক্ষত আছে কিনা বিশেষভাবে পরীক্ষা করুন (multi-company
    isolation-এর মূল ভিত্তি এখানেই)
```

### ৩.৪ টেস্ট

```txt
[x] php artisan test পুরো স্যুট (কোনো --env flag ছাড়া)
[x] MultiCompanyIsolationTest বিশেষভাবে পাস করছে কিনা কনফার্ম (bootstrap.php পরিবর্তনের
    সবচেয়ে বেশি ঝুঁকিপূর্ণ জায়গা এটাই)
[x] npm run build (frontend asset pipeline Laravel ভার্সনের সাথে সরাসরি জড়িত না,
    কিন্তু vite.config.js-এ কোনো Laravel plugin ভার্সন pin থাকলে যাচাই)
```

**সম্পন্ন — ২০২৬-০৮-০১।** `composer why-not`-এ প্ল্যানের অনুমান করা তিনটা প্যাকেজ
(dompdf/openspout/filament) কোনোটাই আসল ব্লকার ছিল না — আসল ব্লকার ছিল
`laravel/tinker` v2.11.1 (illuminate ^12.0 পর্যন্তই সাপোর্ট করে)। `laravel/tinker:^3.0`
একসাথে require করে ঠিক হয়েছে। ফাইনাল: **Laravel 13.23.0**, **tinker 3.0.2**, Symfony
7.x→8.x চেইন আপগ্রেড। `phpunit/phpunit` ও `^12.0`-এ বাম্প করা হয়েছে (আপগ্রেড গাইডের
High-Impact ডিপেন্ডেন্সি তালিকা অনুযায়ী)।

কোড পরিবর্তন: `app/Providers/Filament/AdminPanelProvider.php`-এ
`VerifyCsrfToken::class` → `PreventRequestForgery::class` (Laravel 13-এ CSRF মিডলওয়্যার
rename হয়েছে; পুরনো নামটা deprecated alias হিসেবে এখনো কাজ করত, কিন্তু নতুন নামে
আপডেট করা হয়েছে)। `bootstrap/app.php`-এর `SetCurrentCompany`/`SubstituteBindings`
অর্ডারিং যাচাই করে অক্ষত পাওয়া গেছে।

আপগ্রেড গাইডের বাকি আইটেম যাচাই করে এই প্রজেক্টের জন্য প্রযোজ্য না পাওয়া গেছে:
`Route::domain()` ব্যবহার নেই, `->upsert()` কল নেই, cache/session prefix fallback
এই অ্যাপের নিজস্ব `config/cache.php`/`config/session.php`-এই হার্ডকোড করা (Laravel-এর
নতুন স্কেলিটন ডিফল্ট পরিবর্তনে প্রভাবিত হয় না)। **নোট (ভবিষ্যতের ঐচ্ছিক হার্ডেনিং, এই
আপগ্রেডের স্কোপে না):** `config/cache.php`-এ নতুন `serializable_classes` কী (ডিফল্ট
`false`) এখনো নেই — যোগ করলে deserialization হার্ডেনিং পাওয়া যাবে, কিন্তু
`StorefrontSlide::homeSlides()`-এর মতো জায়গায় Eloquent মডেল অবজেক্ট cache করা হয়,
তাই সেসব ক্লাস আগে allow-list করতে হবে; যেহেতু কী অনুপস্থিত থাকলে Laravel পুরনো
আনরেস্ট্রিক্টেড আচরণই বজায় রাখে (`vendor/laravel/framework`-এ কোড পড়ে নিশ্চিত করা
হয়েছে), এটা কোনো ব্লকার না।

`composer.json`/`composer.lock` লোকালি আপডেট হয়েছে, PHP 8.4.24-এ পুরো test suite —
**৫৫৬ পাস, ২৯৮১ অ্যাসারশন, কোনো রিগ্রেশন নেই।** `npm run build` সফল। বিস্তারিত
`UPDATE_NOTES.md`-এর "Stack Upgrade Plan, Step 3" এন্ট্রিতে। এখনো staging-এ পুশ/ডিপ্লয়
করা হয়নি — owner-এর অনুমোদনের অপেক্ষায়।

---

## ধাপ ৪: Livewire 3 → 4

Filament v5 ইনস্টল করলে Livewire v4 নিজে থেকেই dependency হিসেবে টেনে আসবে (ধাপ ৫-এ)। আলাদা করে `composer require livewire/livewire:^4.0` চালানোর দরকার নেই — Filament-এর কম্পোজার কনস্ট্রেইন্টই এটা রেজলভ করবে। তবে:

```txt
[x] প্রজেক্টে যদি কোনো কাস্টম Livewire কম্পোনেন্ট থাকে (Filament-এর বাইরে — যেমন
    "Inbox" পেজের মতো কিছু, যেটা ইতিমধ্যে Livewire-নির্ভর), সেগুলো Livewire v4-এর
    breaking change guide (livewire.com/docs/upgrade) অনুযায়ী চেক করুন — Livewire v4
    backward compatible রাখার চেষ্টা করেছে (v3 কম্পোনেন্ট "মূলত" চলে), কিন্তু
    wire:model/lazy loading-সংক্রান্ত এজ কেস থাকতে পারে
[x] AppServiceProvider::boot()-এ Livewire::component() দিয়ে যেসব কাস্টম কম্পোনেন্ট
    ম্যানুয়ালি রেজিস্টার করা আছে (ListProducts, CreateProduct ইত্যাদি) — এই
    রেজিস্ট্রেশন প্যাটার্ন Livewire v4-এ এখনো ভ্যালিড কিনা যাচাই করুন
```

**সম্পন্ন — ২০২৬-০৮-০১।** Livewire-এর অফিসিয়াল ৩→৪ breaking-change লিস্ট এই কোডবেসের
বিরুদ্ধে চেক করা হয়েছে: `Route::livewire()` মাইগ্রেশন প্রযোজ্য না (কোনো
`Route::get(Component::class)` স্টাইল ফুল-পেজ রুট নেই), `wire:model.blur`/`.change`
ব্যবহার নেই, `config/livewire.php`-এ পুরনো `layout`/`lazy_placeholder` কী নেই (শুধু
`temporary_file_upload` কনফিগার করা, রিনেমড কী দিয়ে প্রভাবিত হয় না), আর
`<livewire:...>` ট্যাগ (একটাই ব্যবহার — mobile notifications menu item) ইতিমধ্যে
self-closed। `AppServiceProvider`-এর ৭টা ম্যানুয়াল `Livewire::component()` রেজিস্ট্রেশন
(ListProducts/CreateProduct/EditProduct/ListCategories/CreateCategory/EditCategory/
Notifications) অপরিবর্তিত রাখা হয়েছে — Livewire v4 এই মেকানিজম সরায়নি, ফুল টেস্ট স্যুট
+ ব্রাউজার স্মোক টেস্ট দুটোতেই কাজ করেছে।

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
[x] সব Filament Resource ইতিমধ্যে v4-এর নতুন Schemas-ভিত্তিক স্ট্রাকচার ব্যবহার করে
    (Resources/{Name}/Schemas/, Tables/, Pages/) — v3→v4 মাইগ্রেশন আগেই হয়ে গেছে,
    তাই v4→v5-এ এই স্ট্রাকচার বদলাবে না (আগেই বলা হয়েছে: "no functional changes")
[x] কাস্টম Filament Page ক্লাসগুলো (Backups, Inbox, CloudStorageSettings ইত্যাদি —
    এই প্রজেক্টেই সাম্প্রতিক যোগ হওয়া) properly রেন্ডার হচ্ছে কিনা — এগুলো blade view
    + Livewire property বাইন্ডিং ব্যবহার করে, Livewire v4 রেন্ডারিং পরিবর্তনে
    সবচেয়ে বেশি স্পর্শকাতর
[x] wire:model.defer ব্যবহার হওয়া ফর্মগুলো (Backups, CloudStorageSettings পেজে
    আছে) — Livewire v4-এ .defer মডিফায়ারের আচরণ/সিনট্যাক্স বদলেছে কিনা চেক করুন
[x] Repeater/RelationManager-নির্ভর ফর্ম (Purchases, Quotations ইত্যাদি) — এগুলো
    জটিল reactive() ফিল্ড ব্যবহার করে, বেশি করে ম্যানুয়াল স্মোক টেস্ট দরকার
```

### ৫.৩ ম্যানুয়াল স্মোক টেস্ট চেকলিস্ট

```txt
[x] প্রতিটা মূল Resource-এ Create/Edit/List পেজ খুলছে, সেভ হচ্ছে
[x] FileUpload ফিল্ড (Product image, gallery, Category, StorefrontSlide, Company
    logo, StorefrontSetting) আপলোড + saveUploadedFileUsing() হুক (ImageOptimizerService)
    ঠিকমতো কাজ করছে — এটা কাস্টম ক্লোজার-নির্ভর, Livewire আপগ্রেডে সবচেয়ে বেশি
    ভাঙার ঝুঁকি এখানেই
[x] Inbox পেজ (Conversation, polling, chat UI) স্বাভাবিকভাবে কাজ করছে
[x] CloudStorageSettings পেজের ফর্ম সাবমিট/টেস্ট-কানেকশন বাটন কাজ করছে
[x] Dashboard widget/chart সব লোড হচ্ছে
```

**সম্পন্ন — ২০২৬-০৮-০১।** লোকাল ডেভ সার্ভারে (PHP 8.4.24, ব্রাউজারে সরাসরি) ম্যানুয়ালি
যাচাই করা হয়েছে: Dashboard (সব widget/stat-overview ঠিকমতো লোড), Products List + Create
(single-column ফর্ম, FileUpload ড্র্যাগ-ড্রপ UI ঠিকমতো রেন্ডার), Inbox পেজ (Channels/
Conversations ফিল্টার UI), CloudStorageSettings (টেক্সট ইনপুট টাইপ + toggle switch দুটোই
Livewire বাইন্ডিং দিয়ে কাজ করেছে — wire:model.defer নিয়ে কোনো সমস্যা পাওয়া যায়নি), এবং
Create Quotation-এ Repeater-এর "Add item" বাটন দিয়ে নতুন সারি যোগ করা (delete আইকনসহ)
— পুরো ফ্লোতে ব্রাউজার কনসোলে একটাও error আসেনি। FileUpload-এর প্রকৃত আপলোড + ImageOptimizerService
হুক আলাদাভাবে ম্যানুয়ালি টেস্ট করা হয়নি (এই টুলিং ফাইল পিকার চালাতে পারে না), তবে
`ImageOptimizerTest`/`CloudStorageSettingsTest`-সহ পুরো `php artisan test` স্যুট
(ফেক আপলোড দিয়ে ওই হুক-ই ব্যায়াম করে) পাস করেছে।

### ৫.৪ টেস্ট

```txt
[x] php artisan test পুরো স্যুট — Filament resource-এর Livewire টেস্ট
    (livewire()->test(...) প্যাটার্ন ব্যবহার হয়ে থাকলে) বিশেষভাবে
[x] সব ফাইল-আপলোড-সংক্রান্ত টেস্ট (যদি থাকে) পাস করছে
```

**সম্পন্ন — ২০২৬-০৮-০১।** `filament/filament` v4.12.5 → **v5.7.5** (livewire/livewire
v3.8.3 → **v4.3.4** স্বয়ংক্রিয়ভাবে dependency হিসেবে), কোনো কনফ্লিক্ট ছাড়াই।
`php artisan filament:upgrade` সফল। কোনো ম্যানুয়াল কোড পরিবর্তন লাগেনি — filament-এর
নিজস্ব upgrade script + `filament:upgrade` কমান্ডই যথেষ্ট ছিল। পুরো `php artisan test`
PHP 8.4.24-এ — **৫৫৬ পাস, ২৯৮১ অ্যাসারশন, কোনো রিগ্রেশন নেই** (Laravel 13 ধাপের সাথে
হুবহু একই সংখ্যা)। বিস্তারিত `UPDATE_NOTES.md`-এর "Stack Upgrade Plan, Step 4+5"
এন্ট্রিতে। এখনো staging-এ পুশ/ডিপ্লয় করা হয়নি — owner-এর অনুমোদনের অপেক্ষায়।

---

## ধাপ ৬: চূড়ান্ত যাচাই ও ডিপ্লয়

```txt
[x] ⚠️ CRITICAL, deploy-blocking: production MySQL-এ `mysql_native_password` প্লাগইন
    ACTIVE করা ও production-এর DB ইউজারকে সেটাতে ALTER USER করা (ধাপ ২-এর নোট দেখুন) —
    এটা না করলে PHP 8.4 ডিপ্লয় হওয়ার সাথে সাথেই production-এর সব পেজে
    "authentication method unknown to the client [caching_sha2_password]" এরর দিয়ে
    সাইট সম্পূর্ণ ডাউন হয়ে যাবে। এই ধাপ কোড ডিপ্লয়ের *আগে*, আলাদা মেইনটেন্যান্স
    উইন্ডোতে সম্পন্ন করে ভেরিফাই করে নিতে হবে (native `mysql` ক্লায়েন্ট দিয়ে না,
    আসল PHP অ্যাপ দিয়েই কানেকশন টেস্ট করা)
[x] পুরো php artisan test স্যুট (কোনো --env flag ছাড়া) ১০০% পাস
[x] npm run build সফল, কোনো JS কনসোল এরর নেই (dev tools দিয়ে ম্যানুয়াল চেক)
[x] স্টেজিং-এ কমপক্ষে ২৪-৪৮ ঘণ্টা রিয়েল ব্যবহার (অর্ডার প্লেস, Filament CRUD,
    Conversation Inbox, AI Auto-Reply — সব মডিউল ছুঁয়ে) করে কোনো রানটাইম এরর না
    পাওয়া কনফার্ম করুন
[x] composer.lock, composer.json, package.json/package-lock.json — সব ফাইনাল
    ভার্সন কমিট করার আগে CHANGELOG.md + UPDATE_NOTES.md আপডেট (CLAUDE.md নিয়ম
    অনুযায়ী, মেজর ভার্সন বাম্প হিসেবে ক্যাটাগরাইজ)
[x] owner-এর explicit approval-এর পরই commit/push (CLAUDE.md commit policy)
[x] প্রোডাকশন ডিপ্লয়ের ঠিক আগে আরেকবার প্রোডাকশন ডাটাবেস ব্যাকআপ
```

**সম্পন্ন — ২০২৬-০৮-১১।** production MySQL (`mysql-database-iom7u0wab3i2ucilif2kl1ms`,
আসল `DB_HOST`-এর কন্টেইনার) `mysql_native_password`-এ সুইচ করা হয়েছে,
`mysqldump` ব্যাকআপ নেওয়া হয়েছে, ফাইনাল `php artisan test` (৬০৮ পাস, ৩৫৪৭
অ্যাসারশন) পাস, `CHANGELOG.md`-এ `[2.0.0]` Major Version Update এন্ট্রি যোগ করে
owner-এর অনুমোদনের পর `main`-এ পুশ করা হয়েছে (শুধু এই ৬টা স্ট্যাক-আপগ্রেড কমিট —
staging-এ থাকা `a6d07277` OrderFlow parity কমিট owner-এর সিদ্ধান্তে এই ডিপ্লয়ে
অন্তর্ভুক্ত করা হয়নি)। Coolify ডিপ্লয় লগ: বিল্ড সফল, নতুন কন্টেইনার healthy পাস
করেছে, rolling update কোনো downtime ছাড়াই সম্পন্ন। `/health/version` দিয়ে লাইভ
ভেরিফাই: সঠিক কমিট (`589cdc2e`) ডিপ্লয়েড, `deployment_ready: true`। ব্রাউজারে
লগইন পেজ, নতুন Livewire 4 assets, সব নেটওয়ার্ক রিকোয়েস্ট 200 — কোনো এরর নেই।
পাশাপাশি `.github/workflows/deploy.yml`-এর হার্ডকোড করা PHP 8.2 (যেটা এই
`main` পুশে GitHub Actions CI ফেইল করিয়েছিল, Coolify-র আসল ডিপ্লয়কে প্রভাবিত
করেনি) 8.4-এ আপডেট করা হয়েছে। বিস্তারিত `UPDATE_NOTES.md`-এর "Stack Upgrade
Plan, Step 6" এন্ট্রিতে।

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
