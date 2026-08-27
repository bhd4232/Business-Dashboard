# ড্যাশবোর্ড ড্রিলডাউন, WooCommerce অর্ডার ইন্টিগ্রেশন, ব্যাকএন্ড সিকিউরিটি হার্ডেনিং

> **Status:** Draft — Claude Code দিয়ে এক্সিকিউট করার জন্য প্রস্তুত
> **Created:** ২০২৬-০৮-১৫
> **স্কোপ:** ৩টা আইটেম, প্রতিটা রিয়েল কোড ফাইল/লাইন রেফারেন্স দিয়ে গ্রাউন্ডেড।

---

## ধাপ ১: ড্যাশবোর্ড — রেসপন্সিভ কার্ড + ডায়নামিক ড্রিলডাউন

### ১.১ Customer Success & Courier Health — মোবাইলে ২ কলাম

**পাওয়া গেছে:** তিনটা `StatsOverviewWidget` আছে:
- `app/Filament/Widgets/CustomerRiskOverview.php` (heading: "Customer Success & Risk") — কোনো `getColumns()` override নেই।
- `app/Filament/Widgets/CourierHealthWidget.php` (heading: "Courier Health") — কোনো `getColumns()` override নেই।
- `app/Filament/Widgets/BusinessOverview.php` (heading: "Business Overview") — **ইতিমধ্যে** `getColumns()` override করা আছে (`'default' => 2, 'lg' => 5`) — এটাই কাঙ্ক্ষিত মোবাইল-২-কলাম প্যাটার্নের রেডিমেড রেফারেন্স।

**ফিক্স:** দুটো ফাইলেই হুবহু `BusinessOverview.php`-এর প্যাটার্ন কপি করুন:

```php
// CustomerRiskOverview.php — ৩টা স্ট্যাট, মোবাইলে ২+১ না চেয়ে ২ কলামই যথেষ্ট
protected function getColumns(): array
{
    return [
        'default' => 2,
        'lg' => 3,
    ];
}

// CourierHealthWidget.php — ৪টা স্ট্যাট
protected function getColumns(): array
{
    return [
        'default' => 2,
        'lg' => 4,
    ];
}
```

কোনো নতুন CSS/মাইগ্রেশন লাগে না — Filament-এর নিজস্ব রেসপন্সিভ গ্রিড সিস্টেম, শুধু কলাম-সংখ্যা ডিক্লেয়ার করলেই যথেষ্ট।

### ১.২ Overview সেকশনের প্রতিটা কার্ড — ক্লিক করলে ডায়নামিক পপআপ

**লক্ষ্য:** `BusinessOverview` widget-এর প্রতিটা স্ট্যাট কার্ড (Today Sales, Storefront Pending, Today Purchases, Customer Payments, Supplier Payments, Today Expenses, Customer Due, Supplier Payable, Account Balance, Low Stock Items, Coming Soon Products) ক্লিক করলে সংশ্লিষ্ট ডাটার লিস্ট একটা মোডাল/পপআপে দেখাবে।

**⚠️ ইমপ্লিমেন্টেশন ঝুঁকি নোট:** Filament-এর standard `StatsOverviewWidget`/`Stat` ক্লাস নেটিভভাবে "ক্লিক করলে মোডাল" সাপোর্ট করে না (`Stat` শুধু `->url()` — নতুন পেজে নেভিগেট — সাপোর্ট করে)। সত্যিকারের ইন-প্লেস পপআপের জন্য widget-টাকে একটা কাস্টম Livewire + Filament Actions কম্পোনেন্টে রূপান্তর করতে হবে। এই কোডবেজে এর কোনো বিদ্যমান রেফারেন্স/প্রিসিডেন্ট নেই (`InteractsWithActions`/`mountAction` কোনো Filament Resource/Widget-এ ব্যবহৃত হয়নি), তাই নিচের কোড **Filament-এর ডকুমেন্টেড Actions API অনুযায়ী ডিজাইন করা হয়েছে কিন্তু প্রজেক্টের ইনস্টলড Filament ভার্সনের বিপরীতে verify করে নেওয়া জরুরি** (`vendor/filament/actions`-এর সোর্স/ডক দেখে মেথড সিগনেচার মিলিয়ে নিন) — Filament-এর Actions API মেজর ভার্সনে ভার্সনে কিছুটা বদলেছে।

**দুটো অপশন — সুপারিশ B, কিন্তু A সহজ ফলব্যাক:**

**অপশন A (সহজ, কম ঝুঁকি — মোডাল না, সরাসরি ফিল্টার করা লিস্ট পেজে নেভিগেট):**
```php
Stat::make('Today Sales', $this->money($summary['sales_today']))
    ->url(OrderResource::getUrl('index', ['tableFilters' => ['order_date' => ['date_from' => now()->toDateString(), 'date_to' => now()->toDateString()]]]))
    ->icon(Heroicon::OutlinedDocumentCurrencyBangladeshi)
    ->color('success'),
```
প্রতিটা স্ট্যাটের জন্য সংশ্লিষ্ট রিসোর্স (Orders/Purchases/Transactions/Products) আর তার টেবিল ফিল্টার প্যারামিটার বসিয়ে দিলেই কাজ শেষ — Filament-এর নিজস্ব টেবিল ফিল্টারিং reuse হয়, নতুন কোনো মোডাল কম্পোনেন্ট লাগে না।

**অপশন B (owner-এর "পপআপ" শব্দের সাথে সবচেয়ে নিখুঁত মিল — কাস্টম মোডাল):**

```php
// app/Filament/Widgets/BusinessOverview.php — StatsOverviewWidget-এর বদলে কাস্টম Widget
namespace App\Filament\Widgets;

use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas; // বা প্রজেক্টে ইনস্টলড Filament ভার্সনে যেটা সঠিক নাম
use Filament\Widgets\Widget;

class BusinessOverview extends Widget implements HasActions
{
    use InteractsWithActions;

    protected string $view = 'filament.widgets.business-overview';

    public function getStatsData(): array
    {
        $summary = app(ReportService::class)->dashboardSummary();
        // ধাপ ১.২.১-এ বর্ণিত মেটাডাটাসহ প্রতিটা স্ট্যাট অ্যারে হিসেবে রিটার্ন করুন
        // (label, value, icon, color, drilldown_action_name)
    }

    public function viewTodaySalesAction(): Action
    {
        return Action::make('viewTodaySales')
            ->label('Today\'s Sales')
            ->modalHeading('Today\'s Sales')
            ->modalContent(fn () => view('filament.widgets.partials.today-sales-list', [
                'sales' => app(ReportService::class)->sales(now()->startOfDay(), now()->endOfDay()),
            ]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close');
    }

    // ...প্রতিটা স্ট্যাটের জন্য একটা করে অনুরূপ *Action() মেথড —
    // viewStorefrontPendingAction(), viewTodayPurchasesAction(), viewCustomerPaymentsAction(),
    // viewSupplierPaymentsAction(), viewTodayExpensesAction(), viewCustomerDueAction(),
    // viewSupplierPayableAction(), viewLowStockAction(), viewComingSoonAction()
    // (Account Balance-এর কোনো "লিস্ট" নেই, ওটা শুধু ব্যালেন্স — ওখানে ক্লিক করলে Accounts
    // রিসোর্সে নেভিগেট করাই স্বাভাবিক, পপআপ না)
}
```

ব্লেড ভিউতে (`resources/views/filament/widgets/business-overview.blade.php`) প্রতিটা স্ট্যাট কার্ড বিদ্যমান Filament স্ট্যাট-কার্ড ভিজুয়াল স্টাইল (রঙ/আইকন/`zz-business-overview-stat` ক্লাস — `BusinessOverview.php` লাইন ৭২-এ বিদ্যমান) হুবহু বজায় রেখে বানাতে হবে, কিন্তু প্রতিটা কার্ড একটা বাটন/`<div wire:click="mountAction('viewTodaySales')">`-এ মোড়ানো — ক্লিক করলে ওপরের `Action`-এর মোডাল খুলবে।

### ১.২.১ প্রতিটা স্ট্যাটের ডাটা-সোর্স (`ReportService`-এ যাচাই করুন/যোগ করুন)

`ReportService::dashboardSummary()` (লাইন ৩১-৬৩) প্রতিটা সংখ্যা কোন কুয়েরি থেকে আসে তা দেখায়; ড্রিলডাউন লিস্টের জন্য প্রতিটার একটা "লিস্ট রিটার্নকারী" ভার্সন লাগবে:

```txt
Today Sales          → ReportService::sales($from, $to) — ইতিমধ্যে আছে (লাইন ৬৫-৭১),
                        Order::customer সহ রিটার্ন করে, সরাসরি reuse করুন
Storefront Pending    → Order::query()->whereIn('source', [SOURCE_STOREFRONT, SOURCE_OFFER])
                        ->where('status', 'draft')->latest()->get() — dashboardSummary()-এর
                        কুয়েরিরই non-aggregated ভার্সন
Today Purchases       → ReportService-এ purchasesQuery($from, $to) মেথড আছে কিনা যাচাই করুন
                        (dashboardSummary() লাইন ৪৫-এ ব্যবহৃত) — থাকলে ->get() করুন
Customer/Supplier
  Payments            → TransactionLedger::query()->where('type', ...)->whereDate(...)->get()
Today Expenses        → ReportService-এ expensesQuery($from,$to) থাকলে ->get()
Customer Due          → CustomerDueAlertService-এ লিস্ট-রিটার্নকারী মেথড আছে কিনা চেক করুন
                        (এখন শুধু totalDue() ব্যবহৃত হচ্ছে, লাইন ৫৭)
Supplier Payable      → Supplier::query()->where('current_balance', '>', 0)->get()
Low Stock Items       → LowStockAlertService-এ লিস্ট-মেথড আছে কিনা চেক করুন (এখন শুধু count())
Coming Soon Products  → Product::query()->where('status', STATUS_COMING_SOON)->get()
```

**⚠️ যেসব সার্ভিসে এখন শুধু `count()`/`sum()`/`total...()` মেথড আছে (`CustomerDueAlertService`, `LowStockAlertService`) সেখানে একটা প্যারালাল লিস্ট-রিটার্নকারী মেথড (`list()`/`items()`) যোগ করতে হবে — নতুন কুয়েরি লজিক লেখার আগে এই দুই সার্ভিস ফাইল খুলে বিদ্যমান কুয়েরি বিল্ডার এক্সপ্রেশন দেখে সেটারই non-aggregated ভার্সন বানান, ডুপ্লিকেট লজিক না লিখে যতটা সম্ভব একই কুয়েরি বিল্ডার চেইন reuse/এক্সট্র্যাক্ট করুন।**

প্রতিটা মোডাল লিস্টে বেশি রেকর্ড থাকলে (যেমন অনেক ট্রানজেকশন) একটা reasonable limit (`->limit(50)`) + "সব দেখুন" লিংক (সংশ্লিষ্ট রিসোর্সের ফিল্টার করা ইনডেক্স পেজে, অপশন A-এর প্যাটার্নে) রাখুন যাতে মোডাল কখনো শত শত রো দিয়ে ভারী না হয়ে যায়।

---

## ধাপ ২: WooCommerce অর্ডার ম্যানেজমেন্ট ইন্টিগ্রেশন (ওয়েবহুক)

### ২.১ বর্তমান অবস্থা (কোড-যাচাইকৃত)

`app/Services/WooCommerceImportService.php` — শুধু **প্রোডাক্ট** ইমপোর্ট করে (WooCommerce → ERP, one-time/re-runnable, REST API v3 `GET /wp-json/wc/v3/products` + `/variations`, Basic Auth দিয়ে `consumer_key`/`consumer_secret`)। **অর্ডার সিঙ্ক এখনো নেই কোথাও** — এটাই owner-এর মূল অনুরোধ। ক্রেডেনশিয়াল ইতিমধ্যে company-scoped (`StorefrontSetting.woocommerce_base_url` + `woocommerce_credentials` এনক্রিপ্টেড JSON), Filament UI-ও আছে (`app/Filament/Pages/Integrations.php`, "WooCommerce" ট্যাব, লাইন ২২৪-২৪৯)।

### ২.২ ডিজাইন সিদ্ধান্ত — ওয়েবহুক কেন পোলিং-এর চেয়ে ভালো

WooCommerce REST API নিজেই নেটিভ Webhook সাপোর্ট করে (WP অ্যাডমিন → WooCommerce → Settings → Advanced → Webhooks, অথবা REST API দিয়েও তৈরি করা যায়) — `order.created`/`order.updated`/`order.deleted` টপিকে সাবস্ক্রাইব করলে WooCommerce নিজে থেকেই প্রতিটা ইভেন্টে একটা নির্দিষ্ট delivery URL-এ POST করে, পুরো অর্ডার পেলোড JSON আকারে, সাথে `X-WC-Webhook-Signature` হেডারে HMAC-SHA256 সিগনেচার (webhook তৈরির সময় সেট করা secret দিয়ে) — এতে বারবার পোল করার দরকার নেই (রিয়েল-টাইম, কম রিসোর্স), আর সিগনেচার ভেরিফিকেশন দিয়ে নিশ্চিত হওয়া যায় রিকোয়েস্টটা সত্যিই WooCommerce থেকে এসেছে। **⚠️ এই সিগনেচার হেডারের নাম/এনকোডিং (base64 HMAC-SHA256) সাধারণ/স্টেবল WooCommerce কনভেনশন হলেও, প্রজেক্টে ইমপ্লিমেন্ট করার আগে owner-এর WooCommerce ভার্সনের অফিসিয়াল REST API ডকুমেন্টেশনে একবার কনফার্ম করে নিন।**

### ২.৩ নতুন মাইগ্রেশন/কনস্ট্যান্ট

```php
// Order.php-এ নতুন সোর্স কনস্ট্যান্ট (আগের PayStation/Offer প্ল্যানের SOURCE_OFFER/SOURCE_CRM
// এর মতোই প্যাটার্ন)
public const SOURCE_WOOCOMMERCE = 'woocommerce';
```
`Order::SOURCES` অ্যারেতে লেবেলসহ যোগ করুন, আর `SOURCE_STOREFRONT`/`SOURCE_OFFER` যেখানেই হ্যান্ডল হয় (রিপোর্ট, Filament badge রঙ) সেখানে `SOURCE_WOOCOMMERCE`-ও যোগ করুন।

`woocommerce_credentials` JSON-এ (মাইগ্রেশন লাগবে না, JSON কলাম) নতুন কী:
```php
'webhook_secret' => '...', // Filament ফর্মে জেনারেট বাটন দিয়ে random string তৈরি
```

### ২.৪ ওয়েবহুক রিসিভার

```php
// app/Http/Controllers/WooCommerceWebhookController.php
class WooCommerceWebhookController extends Controller
{
    public function __invoke(Request $request, Company $company, WooCommerceOrderSyncService $sync): JsonResponse
    {
        $setting = $company->storefrontSetting;
        $secret = (string) data_get($setting?->woocommerce_credentials, 'webhook_secret');

        abort_if($secret === '', 404);

        $signature = base64_encode(hash_hmac('sha256', $request->getContent(), $secret, true));
        // ⚠️ হেডার নাম আর এনকোডিং WooCommerce-এর অফিসিয়াল ডকুমেন্টেশন দিয়ে verify করুন
        abort_unless(hash_equals($signature, (string) $request->header('X-WC-Webhook-Signature')), 403);

        $topic = (string) $request->header('X-WC-Webhook-Topic'); // যেমন 'order.created'
        $payload = (array) $request->json()->all();

        try {
            match (true) {
                str_starts_with($topic, 'order.') => $sync->handleOrderEvent($company, $topic, $payload),
                default => null,
            };
        } catch (\Throwable $exception) {
            Log::warning('WooCommerce webhook processing failed', ['company' => $company->getKey(), 'topic' => $topic, 'error' => $exception->getMessage()]);

            return response()->json(['ok' => false], 500); // WooCommerce রিট্রাই করবে
        }

        return response()->json(['ok' => true]);
    }
}
```

```php
// routes/web.php
Route::post('/webhooks/woocommerce/{company}', WooCommerceWebhookController::class)
    ->middleware('throttle:120,1')
    ->name('woocommerce.webhook');
```

(company রুট-মডেল-বাইন্ডিং `withoutGlobalScopes` লাগতে পারে, যেহেতু এটা আনঅথেনটিকেটেড এক্সটার্নাল কলার — অন্যান্য ওয়েবহুক কন্ট্রোলার যেমন `ZiniPayWebhookController`/`CourierWebhookController` কীভাবে কোম্পানি-কনটেক্সট ছাড়া সিকিউরলি কাজ করে সেই প্যাটার্ন অনুসরণ করুন।)

### ২.৫ `WooCommerceOrderSyncService`

```php
class WooCommerceOrderSyncService
{
    public function handleOrderEvent(Company $company, string $topic, array $payload): void
    {
        if (str_contains($topic, 'deleted')) {
            $this->markCancelled($company, $payload);
            return;
        }

        $this->upsertOrder($company, $payload);
    }

    protected function upsertOrder(Company $company, array $payload): Order
    {
        $wooOrderId = (int) ($payload['id'] ?? 0);
        abort_if($wooOrderId <= 0, 422);

        return DB::transaction(function () use ($company, $payload, $wooOrderId): Order {
            $customer = $this->resolveCustomer($company, $payload);

            $order = Order::withoutGlobalScopes()
                ->where('company_id', $company->getKey())
                ->where('external_reference', "woo-{$wooOrderId}") // নতুন nullable কলাম, নিচে দেখুন
                ->first() ?? new Order(['company_id' => $company->getKey(), 'source' => Order::SOURCE_WOOCOMMERCE, 'external_reference' => "woo-{$wooOrderId}"]);

            $order->fill([
                'customer_id' => $customer->getKey(),
                'customer_name' => $customer->name,
                'order_date' => $order->exists ? $order->order_date : now()->toDateString(),
                'status' => $this->mapStatus((string) ($payload['status'] ?? 'pending')),
            ]);
            $order->save();

            $this->syncItems($order, (array) ($payload['line_items'] ?? []));
            $order->refresh();

            return $order;
        });
    }

    // resolveCustomer(): ফোন/ইমেইল দিয়ে Customer::firstOrCreate() —
    //   WooCommerceImportService::resolveCategory()-এর মতো একই "find-or-create by natural key" প্যাটার্ন
    // syncItems(): line_items[].sku দিয়ে Product ম্যাচ (WooCommerceImportService::importProduct()-এ
    //   ব্যবহৃত SKU-প্রথম-তারপর-slug ম্যাচিং লজিক reuse করুন), না মিললে skip + Log::warning
    //   (order পুরোপুরি ফেল করাবেন না, যতটুকু ম্যাচ হয় ততটুকু আইটেম নিয়ে অর্ডার তৈরি হোক)
    // mapStatus(): WooCommerce status (pending/processing/on-hold/completed/cancelled/refunded)
    //   → ERP Order::STATUS_* ম্যাপিং টেবিল — ⚠️ owner-এর সাথে কনফার্ম করে নিন কোনটা কোনটার
    //   সমতুল্য, বিশেষত WooCommerce 'processing' বনাম ERP 'confirmed'/'processing'
}
```

**নতুন কলাম:** `orders.external_reference` (nullable string, `unique(['company_id', 'external_reference'])`) — WooCommerce অর্ডার আইডি দিয়ে idempotent upsert করার জন্য (একই ওয়েবহুক দুইবার ডেলিভার হলেও ডুপ্লিকেট অর্ডার তৈরি হবে না, WooCommerce ওয়েবহুক at-least-once ডেলিভারি গ্যারান্টি দেয়, exactly-once না)।

### ২.৬ Filament UI সম্প্রসারণ (`Integrations.php`, "WooCommerce" ট্যাব)

বিদ্যমান `woocommerce_base_url`/`consumer_key`/`consumer_secret` ফিল্ডের পরে (লাইন ২২৭-২৪৩):

```php
TextInput::make('woocommerce_credentials.webhook_secret')
    ->label('Webhook secret')
    ->password()->revealable()->maxLength(255)
    ->suffixAction(
        \Filament\Forms\Components\Actions\Action::make('generate')
            ->icon('heroicon-m-arrow-path')
            ->action(fn (Set $set) => $set('woocommerce_credentials.webhook_secret', Str::random(40)))
    ),
Placeholder::make('woocommerce_webhook_url')
    ->label('Webhook delivery URL (paste into WooCommerce)')
    ->content(fn (): string => route('woocommerce.webhook', $this->companyId ?? 0))
    ->columnSpanFull(),
Placeholder::make('woocommerce_webhook_note')
    ->hiddenLabel()
    ->content('WordPress অ্যাডমিনে যান: WooCommerce → Settings → Advanced → Webhooks → Add webhook। Topic: "Order created" আর "Order updated" (দুইটা আলাদা webhook, অথবা একটা "Order updated" সব ইভেন্ট কভার করলে সেটাই)। Delivery URL-এ ওপরের URL বসান, Secret-এ এই একই secret বসান (এই দুইটা মিলতেই হবে)।')
    ->columnSpanFull(),
```

### ২.৭ (ঐচ্ছিক, আলাদা সিদ্ধান্ত দরকার) — ERP → WooCommerce স্ট্যাটাস পুশ-ব্যাক

owner-এর অনুরোধে শুধু "অর্ডার ম্যানেজমেন্ট + ওয়েবহুক কনফিগার" বলা হয়েছে, যা ইনবাউন্ড (WooCommerce → ERP) সিঙ্ককেই নির্দেশ করে — এই প্ল্যান সেটাই কভার করছে। কিন্তু যদি ERP-তে স্টাফ অর্ডার "shipped"/"completed" মার্ক করলে সেটা WooCommerce-এও রিফ্লেক্ট হওয়া দরকার হয় (two-way sync), সেটা একটা আলাদা, পরবর্তী ধাপ — `PUT /wp-json/wc/v3/orders/{id}` কল করে (বিদ্যমান consumer key/secret দিয়েই সম্ভব, নতুন ক্রেডেনশিয়াল লাগবে না) `Order` মডেলের status-পরিবর্তনের observer/event থেকে ট্রিগার করা যায়। **এটা এই প্ল্যানের স্কোপে নেই — owner নিশ্চিত করলে আলাদা ছোট প্ল্যান হিসেবে যোগ করা যাবে।**

### ২.৮ টেস্ট

```txt
[ ] WooCommerceWebhookController — বৈধ সিগনেচার সহ order.created পেলোডে নতুন Order+OrderItem
    তৈরি হচ্ছে; একই পেলোড দ্বিতীয়বার পাঠালে ডুপ্লিকেট না হয়ে existing order আপডেট হচ্ছে
    (external_reference unique constraint টেস্ট)
[ ] ভুল/অনুপস্থিত সিগনেচারে 403, প্রসেসিং এরর হলে 500 (WooCommerce রিট্রাই ট্রিগার করার জন্য)
[ ] SKU না মেলা লাইন-আইটেম গ্রেসফুলি স্কিপ হচ্ছে, পুরো অর্ডার ফেল করছে না
[ ] status ম্যাপিং টেবিলের প্রতিটা কেস কভার করা ইউনিট টেস্ট
[ ] company A-র webhook secret দিয়ে company B-র {company} রুটে হিট করলে 403/404
    (cross-company leak না হওয়া নিশ্চিত করা)
```

---

## ধাপ ৩: ব্যাকএন্ড/অ্যাডমিন কনটেন্ট সিকিউরিটি — বাস্তবতা ও বাস্তব সমাধান

### ৩.১ একটা জরুরি সততার নোট, তারপর আসল সমাধান

"ফ্রন্টএন্ড থেকে ব্যাকএন্ড এলিমেন্ট এনক্রিপ্টেড থাকবে যা কেউ ভাঙতে পারবে না, ডিক্রিপ্ট করতে পারবে না" — এই লক্ষ্যটা যেভাবে লেখা হয়েছে সেভাবে ওয়েবে **আক্ষরিকভাবে সম্ভব না**, আর এটা বলে দেওয়াই সৎ পরামর্শ হবে চুপচাপ কিছু বানিয়ে "হয়ে গেছে" বলার চেয়ে। কারণ: ব্রাউজার যে HTML/CSS/JS রেন্ডার/এক্সিকিউট করে, সেটা ব্রাউজারকেই বুঝতে হয় — তাই "এনক্রিপ্ট করা কিন্তু ব্রাউজার তা পড়তে/চালাতে পারে" মানে ডিক্রিপশন-কী কোনো না কোনোভাবে ব্রাউজারের কাছেই থাকতে হয়, আর যেখানে কী থাকে সেখান থেকে সবসময় বের করে আনা সম্ভব (এই কারণেই DRM/JS-obfuscation স্কিম সবসময় শেষ পর্যন্ত ভাঙা যায় — এটা ZamZam-এর কোনো দুর্বলতা না, এটা ব্রাউজার-ভিত্তিক ওয়েবের মৌলিক সীমাবদ্ধতা)। Inspect Element/DevTools সবসময় DOM যা render হয়েছে তা দেখাবে, আর Network ট্যাব সবসময় request/response (TLS ব্রাউজারেই ডিক্রিপ্ট হয়ে যায়) দেখাবে — এটা আটকানোর কোনো সত্যিকারের কোড-লেভেল উপায় নেই।

**তবে এর পেছনের আসল উদ্বেগটা সম্পূর্ণ বৈধ আর সমাধানযোগ্য:** "একজন ভিজিটর যেন পাবলিক স্টোরফ্রন্ট ঘেঁটে অ্যাডমিন প্যানেলের গঠন, রুট, বা কোনো সার্ভার-সাইড তথ্য সম্পর্কে কিছু জানতে না পারে।" এটা "ফ্রন্টএন্ড এনক্রিপশন" দিয়ে না, বরং **তথ্য কখনো ব্রাউজারে পাঠানোই না হয়** তা নিশ্চিত করে অর্জন করা হয় — নিচের চেকলিস্ট এটাই করে, বাস্তবসম্মতভাবে।

### ৩.২ সবচেয়ে বড়, বাস্তব ঝুঁকি — `APP_DEBUG` (পাওয়া গেছে)

`.env.example` লাইন ৭-এ `APP_DEBUG=true` ডিফল্ট (কমেন্টে প্রোডাকশনে `false` করার নোট আছে, কিন্তু এটা শুধু একটা কমেন্ট — এনফোর্সড না)। **এটাই সবচেয়ে বাস্তব "ব্যাকএন্ড তথ্য ফ্রন্টএন্ডে চলে যাওয়ার" ঝুঁকি** — DOM ইন্সপেকশনের চেয়েও বহুগুণ গুরুত্বপূর্ণ: প্রোডাকশনে যদি সার্ভারের আসল `.env`-এ `APP_DEBUG=true` থেকে যায়, তাহলে স্টোরফ্রন্টের যেকোনো রুটে কোনো unhandled exception হলেই Laravel-এর ডিফল্ট ডিবাগ-পেজ (Ignition/Whoops) **যেকোনো ভিজিটরকে** পুরো স্ট্যাক ট্রেস, ফাইল পাথ, কোড স্নিপেট, আর কনফিগ ভ্যালু (কিছু রিড্যাক্টেড, কিছু না) দেখিয়ে দেয়।

**ফিক্স — দুই লেয়ারে:**

১. **অপারেশনাল:** owner-কে নিশ্চিত করতে হবে প্রোডাকশন সার্ভারের আসল `.env`-এ `APP_DEBUG=false` (Claude Code এটা করতে পারবে না, এটা সার্ভার-এক্সেস-নির্ভর — শুধু owner নিজে চেক করতে পারবেন Coolify env variables-এ)।

২. **কোড-লেভেল ডিফেন্স-ইন-ডেপথ (যাতে কেউ ভুলে `APP_DEBUG=true` রেখে দিলেও স্টোরফ্রন্ট ভিজিটর কখনো স্ট্যাক ট্রেস না দেখে):** `bootstrap/app.php`-এর `withExceptions()` ব্লক (এখন খালি, লাইন ৬৩-৬৫) — স্টোরফ্রন্ট রুটের জন্য জোর করে জেনেরিক এরর পেজ:

```php
->withExceptions(function (Exceptions $exceptions) {
    $exceptions->render(function (\Throwable $e, Request $request) {
        $isStorefront = ! $request->is('admin', 'admin/*', 'livewire/*', 'api/*');

        if ($isStorefront && ! app()->environment('local', 'testing')) {
            $status = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;
            $status = in_array($status, [404, 403, 419, 429, 500], true) ? $status : 500;

            return response()->view("errors.{$status}", [], $status);
        }

        return null; // অ্যাডমিন প্যানেল আর local/testing-এ Laravel-এর ডিফল্ট আচরণ অক্ষত থাকে
    });
})
```

`resources/views/errors/{403,404,419,429,500}.blade.php` — সাধারণ, ব্র্যান্ডেড, কোনো টেকনিক্যাল ডিটেইল ছাড়া এরর পেজ (স্টোরফ্রন্ট লেআউট থিমের সাথে মিলিয়ে)। এটা `APP_DEBUG`-এর ভুল কনফিগারেশন থেকেও স্টোরফ্রন্ট ভিজিটরকে প্রোটেক্ট করবে — সেফটি-নেট হিসেবে, `.env` ঠিক থাকা অবস্থাতেও এটা রাখা ভালো প্র্যাকটিস।

### ৩.৩ `robots.txt` — /admin ডিসক্রল

**পাওয়া গেছে:** `public/robots.txt`-এ `Disallow:` খালি (সব ক্রল অনুমোদিত) — সার্চ ইঞ্জিন `/admin` কখনো লিংক না পেলে এমনিতেও ইনডেক্স করবে না, কিন্তু এটা একটা স্ট্যান্ডার্ড, বিনামূল্যের হার্ডেনিং ধাপ:

```txt
User-agent: *
Disallow: /admin
Disallow: /livewire
```

(স্টোরফ্রন্টের বাকি সব রুট ইনডেক্সযোগ্য থাকবে, শুধু ব্যাকএন্ড-সম্পর্কিত পাথ বাদ।)

### ৩.৪ নিরাপত্তা হেডার মিডলওয়্যার (পাওয়া যায়নি — নতুন যোগ)

**পাওয়া গেছে:** কোনো `X-Frame-Options`/`Content-Security-Policy`/`Referrer-Policy` মিডলওয়্যার নেই। নতুন:

```php
// app/Http/Middleware/SecurityHeaders.php
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN'); // ক্লিকজ্যাকিং প্রোটেকশন — বিশেষত /admin লগইন পেজের জন্য গুরুত্বপূর্ণ
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');

        return $response;
    }
}
```

`bootstrap/app.php`-এর `->withMiddleware()` ব্লকে `$middleware->appendToGroup('web', SecurityHeaders::class);` (বিদ্যমান `PreventDemoModeWrites`/`SetCurrentCompany` লাইন ৫৩-৫৪-এর ঠিক পরে, একই প্যাটার্নে)। **CLAUDE.md-এর নিয়ম অনুযায়ী `SetCurrentCompany` মিডলওয়্যার `SubstituteBindings`-এর আগে পিন করা থাকতে হবে — নতুন `SecurityHeaders` মিডলওয়্যার এই অর্ডারিং-এ কোনো হস্তক্ষেপ করছে না তা যোগ করার পর আবার নিশ্চিত করে নিন।**

`Content-Security-Policy` ইচ্ছাকৃতভাবে এই তালিকায় রাখা হয়নি — এটা ভুল কনফিগার করলে সহজেই পুরো সাইট (স্টোরফ্রন্ট + অ্যাডমিন) ভেঙে দিতে পারে (ইনলাইন স্ক্রিপ্ট, CDN থেকে Alpine/Chart.js/pixel স্ক্রিপ্ট ব্লক হয়ে যাওয়া ইত্যাদি) — এটা একটা আলাদা, সাবধানে টেস্ট করে করা কাজ হওয়া উচিত, এই প্ল্যানের স্কোপে impulsively যোগ করা ঠিক হবে না।

### ৩.৫ `/admin` লগইন হার্ডেনিং

- **রেট-লিমিটিং:** `AdminPanelProvider.php`-এ এখন কোনো এক্সপ্লিসিট থ্রটল নেই লগইন ফর্মে (Filament-এর নিজস্ব ডিফল্ট থাকতে পারে ভার্সন-ভেদে — যাচাই করুন)। না থাকলে `->loginRateLimiting(fn () => Limit::perMinute(5)->by(request()->ip()))`-জাতীয় কনফিগ (Filament-এর প্যানেল-বিল্ডার API-তে এই মেথড আছে কিনা ইনস্টলড ভার্সনে যাচাই করুন) অথবা `throttle:login` মিডলওয়্যার গ্রুপে যোগ করুন।
- **জেনেরিক রেসপন্স:** ভুল ইউজারনেম/পাসওয়ার্ডে "user not found" বনাম "wrong password" আলাদা মেসেজ না দেখানো (username enumeration এড়াতে) — Filament ডিফল্টে এটা ঠিকভাবেই করে, শুধু কাস্টমাইজ করার সময় এই আচরণ না ভাঙা নিশ্চিত করুন।
- **2FA (ঐচ্ছিক, উচ্চ-মূল্যের অ্যাডমিনদের জন্য সুপারিশ):** `pragmarx/google2fa` প্যাকেজ ইতিমধ্যে `composer.json`/vendor-এ আছে (কোথাও ব্যবহৃত হচ্ছে না এখনো) — `filament/filament` ইকোসিস্টেমে অফিসিয়াল 2FA প্লাগইন আছে কিনা বা এই প্যাকেজ দিয়ে কাস্টম যোগ করা লাগবে কিনা যাচাই করে, সুপার-অ্যাডমিন/`canManageSettings()` ইউজারদের জন্য অন্তত ঐচ্ছিক 2FA চালু করার সুযোগ রাখা যেতে পারে — এটা বড় কাজ, আলাদা প্ল্যান হিসেবে করাই ভালো, owner চাইলে জানাবেন।

### ৩.৬ যা ইতিমধ্যে ঠিক আছে (যাচাই করা হয়েছে, পরিবর্তন লাগবে না)

- `resources/views/storefront/layout.blade.php`-এ `/admin`, `livewire`, বা `filament` শব্দের কোনো রেফারেন্স নেই — স্টোরফ্রন্ট মার্কআপ/JS ইতিমধ্যে ব্যাকএন্ড-সচেতন কিছু leak করছে না।
- অ্যাডমিন প্যানেল আলাদা path (`/admin`, `AdminPanelProvider.php` লাইন ৪৬-৪৭) — স্টোরফ্রন্ট আর অ্যাডমিনের রুট/অ্যাসেট বান্ডল আলাদা, একটা ভিজিটর সাধারণ ব্রাউজিং-এ কখনো অ্যাডমিন-সম্পর্কিত নেটওয়ার্ক রিকোয়েস্ট/অ্যাসেট দেখবে না।

### ৩.৭ যাচাই

```txt
[ ] প্রোডাকশন সার্ভারে (কোডে না, সার্ভার env-এ) APP_DEBUG=false, LOG_LEVEL production-উপযোগী —
    owner নিজে Coolify-তে গিয়ে কনফার্ম করবেন
[ ] একটা ইচ্ছাকৃত 500 এরর ট্রিগার করে (যেমন সাময়িকভাবে একটা রুটে exception ছুঁড়ে) স্টোরফ্রন্টে
    জেনেরিক এরর পেজ দেখাচ্ছে, স্ট্যাক ট্রেস না — স্টেজিং-এ টেস্ট করুন, প্রোডাকশনে ইচ্ছাকৃত এরর
    ট্রিগার করবেন না
[ ] securityheaders.com বা Mozilla Observatory-এর মতো একটা ফ্রি স্ক্যানার দিয়ে ডোমেইন টেস্ট
    করে নতুন হেডারগুলো কার্যকর হয়েছে কিনা যাচাই
[ ] /admin-এ ৫-৬ বার ভুল পাসওয়ার্ড দিয়ে রেট-লিমিট ট্রিগার হচ্ছে কিনা
[ ] curl দিয়ে robots.txt দেখে /admin ডিসঅ্যালাউড আছে কিনা কনফার্ম
```

---

## ভেরিফিকেশন ও হ্যান্ডঅফ (CLAUDE.md অনুযায়ী)

```txt
[ ] php artisan test (কোনো --env flag ছাড়া) — নতুন webhook/exception-handling টেস্টসহ
    পুরো স্যুট পাস
[ ] npm run build — নতুন Blade error pages/widget view যোগ হওয়ায় দরকার
[ ] CHANGELOG.md + UPDATE_NOTES.md — ধাপ ১ (patch/minor UX), ধাপ ২ (minor — নতুন ইন্টিগ্রেশন),
    ধাপ ৩ (security — আলাদা ক্যাটাগরি, CLAUDE.md/release-policy.md অনুযায়ী)
[ ] Order মডেলে নতুন company-owned কিছু যোগ হয়নি (শুধু কলাম), তবে external_reference
    ইউনিক কনস্ট্রেইন্ট মাইগ্রেশন বিদ্যমান ডাটার সাথে কনফ্লিক্ট করছে না তা স্টেজিং-এ যাচাই করুন
[ ] owner-এর স্পষ্ট অনুমোদন ছাড়া git commit/push না করা; নিরাপত্তা-সম্পর্কিত পরিবর্তন
    (ধাপ ৩) প্রথমে স্টেজিং-এ পুরোপুরি যাচাই করে তারপর প্রোডাকশনে
```
