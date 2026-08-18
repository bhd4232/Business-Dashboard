# PayStation Payment Gateway — Multi-Company Integration Plan

> **Status:** Draft — Claude Code দিয়ে এক্সিকিউট করার জন্য প্রস্তুত
> **Created:** ২০২৬-০৮-১৫
> **স্কোপ:** PayStation (Bangladesh Bank-লাইসেন্সপ্রাপ্ত PSO, Service Hub Limited) কে ZamZam ERP-এর multi-company স্টোরফ্রন্টে অ্যাড করা — প্রতিটা কোম্পানি তার **নিজের** PayStation MID/credentials নিজে বসাবে, কোনো shared/global credential থাকবে না।

---

## ০. প্রেক্ষাপট — কেন company-scoped, একটা shared credential না

PayStation-এর নিজস্ব FAQ ও Terms & Conditions অনুযায়ী একটা merchant account (MID) একটা নির্দিষ্ট legal entity + একটা নির্দিষ্ট ওয়েবসাইটের সাথে বাঁধা; নতুন ওয়েবসাইটের জন্য নতুন MID লাগে, আর settlement যায় সেই নির্দিষ্ট "Second Party"-র ব্যাংক অ্যাকাউন্টে। তাই এই ERP-তে multi-company storefront-এর জন্য PayStation credentials **অবশ্যই প্রতিটা কোম্পানির জন্য আলাদাভাবে** সংরক্ষণ করতে হবে — এটা CLAUDE.md-এর নিয়মের সাথেও মিলে যায় ("External credentials... always admin-configurable encrypted settings fields; the owner plugs in keys").

**সুখবর:** এই আর্কিটেকচার ইতিমধ্যে কোডবেজে আছে, অন্য একটা গেটওয়ের (ZiniPay) জন্য। যাচাইকৃত রিয়েল কোড:

- `StorefrontSetting` মডেল (`app/Models/StorefrontSetting.php`) — `use BelongsToCompany;` (company-scoped), এতে `online_payment_enabled` (bool) আর `payment_credentials` (cast: `encrypted:array` — অর্থাৎ ডাটাবেসে এনক্রিপ্টেড JSON) কলাম আছে। বর্তমানে এতে `zinipay_api_key` / `zinipay_base_url` কী দুটো সংরক্ষিত হয়।
- `App\Services\ZiniPayClient` — hosted-checkout ক্লায়েন্ট: `isConfigured(StorefrontSetting $setting)`, `createPayment(...)`, `verifyPayment(...)` — সবকিছু `$setting->payment_credentials` থেকে ক্রেডেনশিয়াল পড়ে, কোনো global config/env ব্যবহার করে না।
- `App\Services\StorefrontPaymentService::verifyAndFinalize()` — পেমেন্ট ভেরিফাই করে অর্ডার ফাইনালাইজ করে।
- `App\Http\Controllers\ZiniPayWebhookController` — IPN/webhook হ্যান্ডলার, route: `POST /webhooks/zinipay/{payment}` (`routes/web.php` লাইন ৩৫২-৩৫৪, নাম `zinipay.webhook`, `throttle:120,1` মিডলওয়্যার)।
- `App\Filament\Resources\StorefrontSettings\StorefrontSettingResource.php` (লাইন ~৮১২-৮৩৩) — "Online Payments (ZiniPay)" সেকশনে `payment_credentials.zinipay_api_key` / `payment_credentials.zinipay_base_url` ফিল্ড।
- `App\Http\Controllers\Storefront\CheckoutController` — কনস্ট্রাক্টরে সরাসরি `ZiniPayClient $zinipay` ইনজেক্ট করা, আর ৩ জায়গায় ব্যবহৃত: `assertOnlinePaymentAvailable()`, `checkoutView()`-এ `onlinePaymentAvailable`, `startCheckoutAdvancePayment()`।
- `App\Models\StorefrontPayment` — `gateway` কলাম আগে থেকেই আছে (free-text string, এখন `'zinipay'` হার্ডকোড করে বসানো হয়, লাইন ~২৩৮)।

**সিদ্ধান্ত:** ZiniPay সরিয়ে ফেলা না — PayStation-কে **দ্বিতীয় বিকল্প গেটওয়ে** হিসেবে ঠিক একই প্যাটার্নে যোগ করা, আর `StorefrontSetting`-এ একটা `online_payment_gateway` সিলেক্টর যোগ করা যাতে প্রতিটা কোম্পানি নিজে বেছে নিতে পারে সে কোন গেটওয়ে ব্যবহার করবে (ZiniPay নাকি PayStation), নিজের ক্রেডেনশিয়াল দিয়ে।

---

## ⚠️ ধাপ ০ (কোডের আগে, বাধ্যতামূলক): PayStation-এর আসল API ডকুমেন্টেশন যাচাই

এই প্ল্যানে নিচে যে মেথড/এন্ডপয়েন্ট শেপ দেখানো হয়েছে তা **ZiniPayClient-এর কাঠামো অনুসরণ করে একটা টেমপ্লেট মাত্র** — PayStation-এর প্রকৃত API ফিল্ড নাম, endpoint path, auth স্কিম (API key header, নাকি merchant-id+password, নাকি HMAC signature) আমি এই সেশনে যাচাই করতে পারিনি (শুধু তাদের FAQ/Terms পাবলিক পেজ পড়েছি, ডেভেলপার ডকুমেন্টেশন পড়িনি)। কোড লেখার আগে অবশ্যই:

```txt
[ ] merchant.paystation.com.bd -এ লগইন করে "Documentation" মেনু থেকে API রেফারেন্স (create-payment,
    verify, IPN/webhook payload) সংগ্রহ করা — অথবা owner-এর account manager-কে বলে ডেভেলপার
    ডকুমেন্ট/sandbox credential চেয়ে নেওয়া
[ ] sandbox/test MID পাওয়া গেলে সেটা দিয়ে প্রথমে sandbox-এ টেস্ট করা, প্রোডাকশন MID দিয়ে না
[ ] IPN/webhook-এ signature verification আছে কিনা (অনেক BD গেটওয়ে hash/checksum দেয়) — থাকলে
    PayStationClient-এ সেটা implement করা, অনুমান করে বাদ দেওয়া যাবে না
[ ] প্রতিটা active কোম্পানির জন্য আলাদা PayStation MID লাগবে (ধাপ ০-এর নিয়ম অনুযায়ী) — owner-কে
    জানানো, একটা MID দিয়ে সব কোম্পানি চালানো যাবে না
```

নিচের কোড টেমপ্লেটে যেখানেই বাস্তব এন্ডপয়েন্ট/পেলোড অনিশ্চিত সেখানে `// TODO: verify against PayStation docs` কমেন্ট রাখা হয়েছে — Claude Code এক্সিকিউশনের সময় এগুলো আসল ডকুমেন্টেশন দিয়ে পূরণ করতে হবে।

---

## ধাপ ১: গেটওয়ে অ্যাবস্ট্রাকশন ইন্টারফেস তৈরি

```php
// app/Contracts/PaymentGatewayClient.php
namespace App\Contracts;

use App\Models\StorefrontSetting;

interface PaymentGatewayClient
{
    public static function isConfigured(StorefrontSetting $setting): bool;

    /** @return array{payment_url: string, invoice_id: ?string} */
    public function createPayment(
        StorefrontSetting $setting,
        float $amount,
        string $customerName,
        ?string $customerEmail,
        string $redirectUrl,
        string $cancelUrl,
        string $webhookUrl,
        array $metadata = [],
    ): array;

    /** @return array raw verify payload, must include a normalized 'status' key */
    public function verifyPayment(StorefrontSetting $setting, string $invoiceId): array;
}
```

`ZiniPayClient`-এ `implements PaymentGatewayClient` যোগ করুন — মেথড সিগনেচার ইতিমধ্যে হুবহু মিলে যায়, কোনো বিহেভিয়ার বদলাবে না, শুধু টাইপ-সেফটি যোগ হবে।

---

## ধাপ ২: `PayStationClient` সার্ভিস

```php
// app/Services/PayStationClient.php
namespace App\Services;

use App\Contracts\PaymentGatewayClient;
use App\Models\StorefrontSetting;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * PayStation (paystation.com.bd) hosted-checkout ক্লায়েন্ট।
 *
 * ⚠️ এন্ডপয়েন্ট/পেলোড শেপ যাচাই করা হয়নি — merchant.paystation.com.bd/documentation
 * থেকে আসল রেফারেন্স নিয়ে নিচের TODO গুলো পূরণ করুন।
 */
class PayStationClient implements PaymentGatewayClient
{
    public const DEFAULT_BASE_URL = 'https://api.paystation.com.bd'; // TODO: verify

    public static function isConfigured(StorefrontSetting $setting): bool
    {
        return (bool) $setting->online_payment_enabled
            && filled(data_get($setting->payment_credentials, 'paystation_merchant_id'))
            && filled(data_get($setting->payment_credentials, 'paystation_password'));
            // TODO: field names/count depend on PayStation's actual auth scheme
    }

    public function createPayment(
        StorefrontSetting $setting,
        float $amount,
        string $customerName,
        ?string $customerEmail,
        string $redirectUrl,
        string $cancelUrl,
        string $webhookUrl,
        array $metadata = [],
    ): array {
        $response = Http::timeout(30)
            // TODO: correct auth header/body fields per PayStation docs
            ->withHeaders(['Authorization' => $this->credential($setting, 'paystation_merchant_id')])
            ->post($this->baseUrl($setting).'/v1/payment/create', [ // TODO: correct path
                'merchant_id' => $this->credential($setting, 'paystation_merchant_id'),
                'cus_name' => $customerName,
                'cus_email' => $customerEmail ?: 'noreply@example.com',
                'amount' => round($amount, 2),
                'redirect_url' => $redirectUrl,
                'cancel_url' => $cancelUrl,
                'webhook_url' => $webhookUrl,
                'metadata' => $metadata,
            ]);

        $paymentUrl = (string) $response->json('payment_url', ''); // TODO: correct response key

        if ($response->failed() || $paymentUrl === '') {
            throw new RuntimeException('PayStation payment could not be created: '.((string) $response->json('message', 'HTTP '.$response->status())));
        }

        return [
            'payment_url' => $paymentUrl,
            'invoice_id' => (string) $response->json('invoice_id'), // TODO: correct key
        ];
    }

    public function verifyPayment(StorefrontSetting $setting, string $invoiceId): array
    {
        $response = Http::timeout(30)
            ->withHeaders(['Authorization' => $this->credential($setting, 'paystation_merchant_id')])
            ->post($this->baseUrl($setting).'/v1/payment/verify', ['invoice_id' => $invoiceId]); // TODO

        if ($response->failed()) {
            throw new RuntimeException('PayStation verification failed (HTTP '.$response->status().').');
        }

        $raw = (array) $response->json();

        // ZiniPayClient/StorefrontPaymentService উভয়েই 'status' কী-তে 'COMPLETED'/'FAILED'
        // (uppercase) আশা করে — PayStation-এর status ভ্যালু ভিন্ন হলে (যেমন 'success'/'failed')
        // এখানে normalize করে দিন যাতে StorefrontPaymentService-এ কোনো গেটওয়ে-স্পেসিফিক
        // if/else লিখতে না হয়।
        $raw['status'] = match (strtolower((string) ($raw['status'] ?? ''))) {
            'success', 'completed', 'paid' => 'COMPLETED', // TODO: confirm actual values
            'failed', 'cancelled' => 'FAILED',
            default => (string) ($raw['status'] ?? ''),
        };

        return $raw;
    }

    protected function credential(StorefrontSetting $setting, string $key): string
    {
        $value = (string) data_get($setting->payment_credentials, $key, '');

        if ($value === '') {
            throw new RuntimeException("PayStation {$key} is not configured in the storefront settings.");
        }

        return $value;
    }

    protected function baseUrl(StorefrontSetting $setting): string
    {
        return rtrim((string) data_get($setting->payment_credentials, 'paystation_base_url') ?: self::DEFAULT_BASE_URL, '/');
    }
}
```

---

## ধাপ ৩: গেটওয়ে রিজলভার

দুইটা গেটওয়ে একসাথে সাপোর্ট করার জন্য একটা resolver লাগবে, যাতে `CheckoutController`/`StorefrontPaymentService` হার্ডকোড না করে সঠিক ক্লায়েন্ট বেছে নেয়।

```php
// app/Services/PaymentGatewayResolver.php
namespace App\Services;

use App\Contracts\PaymentGatewayClient;
use App\Models\StorefrontSetting;
use RuntimeException;

class PaymentGatewayResolver
{
    public function __construct(
        protected ZiniPayClient $zinipay,
        protected PayStationClient $paystation,
    ) {}

    /** Setting-এ যা সিলেক্ট করা আছে সেটা দিয়ে (নতুন পেমেন্ট শুরু করার সময়) */
    public function forSetting(StorefrontSetting $setting): PaymentGatewayClient
    {
        return $this->byKey($setting->online_payment_gateway ?: 'zinipay');
    }

    /** একটা existing পেমেন্ট রেকর্ডে যে গেটওয়ে দিয়ে তৈরি হয়েছিল সেটা দিয়ে (ভেরিফাই করার সময়) —
     *  admin পরে গেটওয়ে বদলালেও পুরনো pending পেমেন্ট ভুল ক্লায়েন্ট দিয়ে ভেরিফাই হবে না। */
    public function byKey(string $gateway): PaymentGatewayClient
    {
        return match ($gateway) {
            'paystation' => $this->paystation,
            'zinipay' => $this->zinipay,
            default => throw new RuntimeException("Unknown payment gateway: {$gateway}"),
        };
    }

    public function isAnyConfigured(StorefrontSetting $setting): bool
    {
        return ZiniPayClient::isConfigured($setting) || PayStationClient::isConfigured($setting);
    }
}
```

---

## ধাপ ৪: মাইগ্রেশন

`payment_credentials` JSON কলাম হওয়ায় নতুন কী যোগ করতে স্কিমা বদলাতে হবে না। শুধু একটা সিলেক্টর কলাম লাগবে:

```php
// database/migrations/2026_08_15_000000_add_online_payment_gateway_to_storefront_settings_table.php
Schema::table('storefront_settings', function (Blueprint $table) {
    $table->string('online_payment_gateway')->default('zinipay')->after('online_payment_enabled');
});
```

`StorefrontSetting::$fillable`-এ `'online_payment_gateway'` যোগ করুন (এটা এনক্রিপ্টেড না, প্লেইন স্ট্রিং কাস্ট)।

---

## ধাপ ৫: বিদ্যমান কনজিউমার রিফ্যাক্টর

**`app/Services/StorefrontPaymentService.php`:**
- কনস্ট্রাক্টরে `ZiniPayClient $zinipay` এর বদলে `PaymentGatewayResolver $gateways` ইনজেক্ট করুন।
- `verifyAndFinalize()`-এ `$this->zinipay->verifyPayment(...)` কল-টা `$this->gateways->byKey($payment->gateway)->verifyPayment(...)` দিয়ে বদলান — এভাবে পেমেন্ট **যে গেটওয়ে দিয়ে তৈরি হয়েছিল সেটা দিয়েই** ভেরিফাই হবে, admin মাঝে গেটওয়ে বদলে দিলেও পুরনো পেমেন্টে সমস্যা হবে না।

**`app/Http/Controllers/Storefront/CheckoutController.php`:**
- কনস্ট্রাক্টরে `ZiniPayClient $zinipay` → `PaymentGatewayResolver $gateways`।
- `assertOnlinePaymentAvailable()` (লাইন ~২১১-২১৬): `ZiniPayClient::isConfigured($setting)` → `$this->gateways->isAnyConfigured($setting)`।
- `checkoutView()` (লাইন ~৪১৬): `'onlinePaymentAvailable' => ZiniPayClient::isConfigured($setting)` → `$this->gateways->isAnyConfigured($setting)`।
- `startCheckoutAdvancePayment()` (লাইন ~২৩৫-২৬১, ~২৭০-২৮০): `'gateway' => 'zinipay'` হার্ডকোড-টা `'gateway' => $setting->online_payment_gateway` দিয়ে বদলান, আর `$this->zinipay->createPayment(...)` কল-টা `$this->gateways->forSetting($setting)->createPayment(...)` দিয়ে।

**`app/Http/Controllers/ZiniPayWebhookController.php`:** অপরিবর্তিত থাকবে (এটা শুধু ZiniPay-এর জন্য)।

---

## ধাপ ৬: PayStation webhook কন্ট্রোলার + রুট

```php
// app/Http/Controllers/PayStationWebhookController.php
namespace App\Http\Controllers;

use App\Models\StorefrontPayment;
use App\Services\PaymentGatewayResolver;
use App\Services\StorefrontPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PayStationWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        StorefrontPayment $payment,
        StorefrontPaymentService $payments,
        PaymentGatewayResolver $gateways,
    ): JsonResponse {
        $invoiceId = trim((string) ($request->input('invoice_id') ?: $payment->invoice_id)); // TODO: confirm IPN field name

        if ($invoiceId === '') {
            return response()->json(['ok' => true]);
        }

        $setting = $payment->company?->storefrontSetting;

        if (! $setting || ! \App\Services\PayStationClient::isConfigured($setting)) {
            return response()->json(['ok' => false], 422);
        }

        // TODO: PayStation যদি IPN-এ signature/checksum দেয়, এখানে যাচাই করুন —
        // ব্যর্থ হলে 4xx রিটার্ন করুন, আর ভেরিফাই-না-করা পেলোড কখনো সরাসরি বিশ্বাস করে
        // অর্ডার ফাইনালাইজ করবেন না (নিচের verifyAndFinalize() সবসময় সার্ভার-টু-সার্ভার
        // verify API কল করে পেলোড re-confirm করে — এটা ZiniPay-এর প্যাটার্নই, রাখুন)।

        try {
            $payments->verifyAndFinalize($payment, $setting, $invoiceId);
        } catch (\Throwable $exception) {
            Log::warning('PayStation webhook verification failed', ['payment' => $payment->getKey(), 'error' => $exception->getMessage()]);

            return response()->json(['ok' => false], 502);
        }

        return response()->json(['ok' => true]);
    }
}
```

`routes/web.php`-এ, `zinipay.webhook` রুটের পরেই (লাইন ~৩৫৪-এর পরে):

```php
Route::post('/webhooks/paystation/{payment}', PayStationWebhookController::class)
    ->middleware('throttle:120,1')
    ->name('paystation.webhook');
```

(এবং ফাইলের ওপরে `use App\Http\Controllers\PayStationWebhookController;` যোগ করুন।)

---

## ধাপ ৭: Filament admin ফর্ম (`StorefrontSettingResource.php`, লাইন ~৮১২-৮৩৩)

বর্তমান "Online Payments (ZiniPay)" সেকশন বদলে দুইটা গেটওয়ে থেকে বেছে নেওয়ার UI:

```php
Section::make('Online Payments')
    ->columnSpanFull()
    ->description('Used for unified checkout advances: pre-order, new-customer delivery, and optional courier-history eligibility. Product balances can remain Cash on Delivery. Each company must use its own gateway merchant account — never reuse another company\'s or website\'s credentials (violates most BD payment gateway terms and can trigger account suspension).')
    ->schema([
        Toggle::make('online_payment_enabled')
            ->label('Enable online payments')
            ->default(false)
            ->helperText('Turn on only after the selected gateway\'s credentials below are set.'),
        Select::make('online_payment_gateway')
            ->label('Active gateway')
            ->options(['zinipay' => 'ZiniPay', 'paystation' => 'PayStation'])
            ->default('zinipay')
            ->required()
            ->live(),

        // ZiniPay fields — unchanged, শুধু ->visible() যোগ হলো
        TextInput::make('payment_credentials.zinipay_api_key')
            ->label('ZiniPay API key')
            ->password()->revealable()->maxLength(255)
            ->visible(fn (Get $get): bool => $get('online_payment_gateway') === 'zinipay'),
        TextInput::make('payment_credentials.zinipay_base_url')
            ->label('ZiniPay base URL')
            ->url()->maxLength(255)
            ->placeholder(ZiniPayClient::DEFAULT_BASE_URL)
            ->helperText('Leave empty for the default.')
            ->visible(fn (Get $get): bool => $get('online_payment_gateway') === 'zinipay'),

        // PayStation fields — ফিল্ড নাম/সংখ্যা ধাপ ০-এর docs অনুযায়ী চূড়ান্ত করুন
        TextInput::make('payment_credentials.paystation_merchant_id')
            ->label('PayStation Merchant ID')
            ->password()->revealable()->maxLength(255)
            ->helperText('This company\'s own PayStation MID — do not reuse another company\'s or website\'s MID.')
            ->visible(fn (Get $get): bool => $get('online_payment_gateway') === 'paystation'),
        TextInput::make('payment_credentials.paystation_password')
            ->label('PayStation Password / API key')
            ->password()->revealable()->maxLength(255)
            ->visible(fn (Get $get): bool => $get('online_payment_gateway') === 'paystation'),
        TextInput::make('payment_credentials.paystation_base_url')
            ->label('PayStation base URL')
            ->url()->maxLength(255)
            ->placeholder(\App\Services\PayStationClient::DEFAULT_BASE_URL)
            ->helperText('Leave empty for the default.')
            ->visible(fn (Get $get): bool => $get('online_payment_gateway') === 'paystation'),
    ])
    ->columns(2)
    ->collapsible()
    ->collapsed(),
```

(`Select` ইম্পোর্ট আগে থেকেই ফাইলে আছে কিনা চেক করুন — `Get` ইতিমধ্যে ইমপোর্টেড।)

---

## ধাপ ৮: `StorefrontPaymentResource.php`-এ লেবেল/ফিল্টার

লাইন ৪৯-৫৬ (`TextColumn::make('gateway')->formatStateUsing(...)`) আর লাইন ৭৮-৮২ (`SelectFilter::make('gateway')`) — দুই জায়গাতেই:

```php
'paystation' => 'PayStation',
```

যোগ করুন (existing `'zinipay' => 'ZiniPay',`-এর পাশে)।

---

## ধাপ ৯: টেস্ট

```txt
[ ] tests/Unit/Services/PayStationClientTest.php — Http::fake() দিয়ে createPayment/verifyPayment
    এর সাকসেস ও ফেইলিউর কেস (ZiniPayClient-এর সমতুল্য ইউনিট টেস্ট থাকলে সেটাই টেমপ্লেট, না
    থাকলে tests/Feature/StorefrontPreorderPaymentTest.php-এর HTTP fake প্যাটার্ন রেফারেন্স নিন)
[ ] tests/Feature/StorefrontPreorderPaymentTest.php ধাঁচের একটা নতুন টেস্ট — company-র
    online_payment_gateway = 'paystation' সেট করে পুরো advance-payment ফ্লো (checkout →
    startCheckoutAdvancePayment → webhook simulate → order placed) end-to-end যাচাই
[ ] StorefrontPaymentService-এর verifyAndFinalize() যেন সঠিক গেটওয়ে ক্লায়েন্ট বেছে নেয়
    (payment->gateway = 'paystation' হলে PayStationClient কল হচ্ছে, ZiniPayClient না) — এটাই
    সবচেয়ে বাগ-প্রবণ জায়গা, আলাদা assertion দিয়ে টেস্ট করুন
[ ] Company A-র payment_credentials কখনো Company B-র PaymentGatewayResolver কলে ব্যবহার
    হচ্ছে না — StorefrontSetting আগে থেকেই BelongsToCompany-স্কোপড, কিন্তু resolver-এ সবসময়
    সঠিক $setting পাস হচ্ছে কিনা সেটা explicit টেস্ট করুন
[ ] পুরো php artisan test স্যুট (কোনো --env flag ছাড়া) পাস করছে কিনা
```

---

## ধাপ ১০: ভেরিফিকেশন ও হ্যান্ডঅফ (CLAUDE.md অনুযায়ী)

```txt
[ ] php artisan test (no --env) — পুরো স্যুট, কোনো রিগ্রেশন নেই
[ ] npm run build — শুধু যদি কোনো ফ্রন্টএন্ড অ্যাসেট বদলায় (এই কাজে সাধারণত লাগবে না, সবই
    ব্যাকএন্ড/অ্যাডমিন)
[ ] CHANGELOG.md-এ এন্ট্রি (minor: নতুন ফিচার — PayStation gateway support) + UPDATE_NOTES.md
    আপডেট, কমিটের আগে
[ ] Sandbox/staging-এ কমপক্ষে একটা ডেমো কোম্পানিতে PayStation চালু করে, sandbox MID দিয়ে
    একটা প্রি-অর্ডার অ্যাডভান্স পেমেন্ট সম্পূর্ণ করে অর্ডার তৈরি হওয়া পর্যন্ত ম্যানুয়ালি
    যাচাই করা — webhook path আর polling/return-URL path দুটোই টেস্ট করা
[ ] owner-এর স্পষ্ট অনুমোদন ছাড়া git commit/push না করা
```

---

## সারসংক্ষেপ ফ্লো

```
ধাপ ০ (docs verify, prerequisite, কোড লেখার আগে)
   → ধাপ ১ (Interface) → ধাপ ২ (PayStationClient) → ধাপ ৩ (Resolver)
   → ধাপ ৪ (Migration) → ধাপ ৫ (Refactor CheckoutController/StorefrontPaymentService)
   → ধাপ ৬ (Webhook) → ধাপ ৭ (Filament UI) → ধাপ ৮ (Admin labels)
   → ধাপ ৯ (Tests) → ধাপ ১০ (Verify & handoff)
```

প্রতিটা ধাপ স্বতন্ত্রভাবে টেস্টযোগ্য এবং ZiniPay-এর বিদ্যমান কার্যক্ষমতা কোথাও ভাঙে না — শুধু পাশাপাশি একটা দ্বিতীয় বিকল্প গেটওয়ে যোগ হচ্ছে, প্রতিটা কোম্পানি তার নিজস্ব credential দিয়ে যেটা বেছে নিতে পারবে।
