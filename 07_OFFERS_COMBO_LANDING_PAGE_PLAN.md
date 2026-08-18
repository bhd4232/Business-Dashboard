# অফার মডিউল — সিঙ্গেল অফার, কম্বো অফার, AI ল্যান্ডিং পেজ, রিভিউ সিস্টেম

> **Status:** Draft — Claude Code দিয়ে এক্সিকিউট করার জন্য প্রস্তুত
> **Created:** ২০২৬-০৮-১৫
> **স্কোপ:** স্টোরফ্রন্ট মেনুতে "অফার" (সিঙ্গেল অফার / কম্বো অফার), প্রতিটা অফারের জন্য AI-জেনারেটেড বাংলা ল্যান্ডিং পেজ (Filament ব্লক-এডিটরে কাস্টমাইজযোগ্য), এক-পেজ ফানেল (ল্যান্ডিং → ইন-পেজ চেকআউট → থ্যাংক ইউ), চেকআউটে অটো কাস্টমার অ্যাকাউন্ট তৈরি, আর নতুন কাস্টমার রিভিউ সিস্টেম।

---

## ০. সিদ্ধান্ত (owner-confirmed) ও প্রেক্ষাপট

আগের আলোচনায় নিশ্চিত হওয়া তিনটা সিদ্ধান্ত:

1. **ল্যান্ডিং পেজ এডিটর:** Filament ব্লক-এডিটর (Repeater-ভিত্তিক) — কোনো ভিজুয়াল ড্র্যাগ-ড্রপ ফ্রন্টএন্ড বিল্ডার না। প্রতিটা সেকশন (Hero, USP, FAQ, Testimonial ইত্যাদি) একটা ব্লক, ব্লক অ্যাড/রিমুভ/রিঅর্ডার/এডিট করা যাবে ফর্ম ফিল্ড দিয়ে। বিদ্যমান স্ট্যাক (Filament/Livewire) দিয়েই তৈরি হয়, তুলনামূলক দ্রুত ও কম ঝুঁকিপূর্ণ।
2. **কম্বো দাম ও স্টক:** দামের জন্য দুইটা মোড সাপোর্ট করবে — auto-sum (কম্পোনেন্ট প্রোডাক্টের দামের যোগফল) অথবা manual override — আর তার ওপর ঐচ্ছিক manual discount (% বা flat) বসানো যাবে। স্টক প্রতিটা কম্পোনেন্ট প্রোডাক্ট থেকে সত্যিকারের ইনভেন্টরি থেকেই কাটবে (কোনো আলাদা "কম্বো স্টক" কাউন্টার থাকবে না) — নিচে ধাপ ২-এ dedicated ব্যাখ্যা আছে কীভাবে এটা বিদ্যমান stock/accounting পাইপলাইনে বিনা পরিবর্তনে ফিট করে।
3. **রিভিউ/টেস্টিমোনিয়াল:** সম্পূর্ণ নতুন কাস্টমার-রিভিউ সিস্টেম তৈরি হবে — কাস্টমার তার ডেলিভারড অর্ডার থেকে রিভিউ সাবমিট করবে, অ্যাডমিন মডারেট (approve/reject) করবে, approved রিভিউগুলো ল্যান্ডিং পেজের Testimonial ব্লকে বসানো যাবে।

**যা ইতিমধ্যে কোডবেজে আছে ও reuse হবে (নতুন করে বানাতে হবে না):**

- `App\Services\Crm\AiLlmClient` + `App\Services\Crm\AiSettingsService` — প্রতি-কোম্পানি এনক্রিপ্টেড AI provider/API key (`companies.settings->ai`, Anthropic/OpenAI) ইতিমধ্যে আছে (CRM auto-reply ফিচারে ব্যবহৃত)। ল্যান্ডিং পেজ জেনারেশনের জন্য এই একই ক্রেডেনশিয়াল reuse হবে — আলাদা কোনো নতুন AI settings UI লাগবে না।
- `App\Services\CustomerAccountService::register()` — পাসওয়ার্ড দিয়ে Customer অ্যাকাউন্ট রেজিস্টার/আপগ্রেড করার মেথড ইতিমধ্যে আছে (ফোন-ম্যাচিং দিয়ে বিদ্যমান CRM কাস্টমার রেকর্ড reuse করে ডুপ্লিকেট এড়ায়)। অফার-চেকআউটে শুধু `Str::password()` দিয়ে একটা random পাসওয়ার্ড জেনারেট করে এই মেথডে পাস করলেই অটো-অ্যাকাউন্ট তৈরি হয়ে যাবে — নতুন করে কাস্টমার-অথ লজিক লিখতে হবে না।
- `App\Models\Customer` — ইতিমধ্যে `Authenticatable` (storefront `customer` guard), `isRegistered()` মেথড, password/remember_token/OTP ফিল্ড সব আছে।
- স্টক পাইপলাইন — `StockMovementService::syncProductStock()` কনফার্ম করে যে প্রোডাক্ট স্টক `StockMovement` লেজার থেকে ডেরাইভড, `OrderItem` তৈরির সময় সরাসরি ডিক্রিমেন্ট হয় না (workflow status transition-এ movement তৈরি হয়)। তাই কম্বো অর্ডারকে যদি সাধারণ `OrderItem` রো-তে "explode" করে দেওয়া হয় (নিচে ধাপ ৫), পুরো stock/accounting/reporting পাইপলাইন **কোনো পরিবর্তন ছাড়াই** কাজ করবে।
- `StorefrontSetting.header_menu`/`footer_menu` JSON menu builder (Filament Repeater, `type` অনুযায়ী shop/category/page/track/account) — অফার মেনু আইটেম এতে একটা নতুন `type` হিসেবে যোগ হবে।

---

## ধাপ ১: ডাটা মডেল

### ১.১ `offers` টেবিল

```php
// database/migrations/2026_08_16_000000_create_offers_table.php
Schema::create('offers', function (Blueprint $table) {
    $table->id();
    $table->foreignId('company_id')->constrained()->cascadeOnDelete();
    $table->string('type'); // 'single' | 'combo'
    $table->string('title');
    $table->string('slug');
    $table->string('status')->default('draft'); // draft | published | archived
    $table->text('short_description')->nullable();

    // দাম
    $table->string('price_mode')->default('auto_sum'); // auto_sum | manual
    $table->decimal('manual_price', 12, 2)->nullable();
    $table->string('discount_type')->nullable(); // percent | flat
    $table->decimal('discount_value', 12, 2)->nullable();

    // ল্যান্ডিং পেজ কন্টেন্ট (ব্লক-ভিত্তিক, ধাপ ৩ দেখুন)
    $table->json('blocks')->nullable();
    $table->boolean('is_ai_generated')->default(false);
    $table->timestamp('ai_generated_at')->nullable();

    // SEO / meta
    $table->string('meta_title')->nullable();
    $table->text('meta_description')->nullable();
    $table->string('cover_image')->nullable();

    $table->boolean('online_payment_required')->default(false); // পুরোপুরি অগ্রিম পেমেন্ট বাধ্যতামূলক কিনা
    $table->timestamps();

    $table->unique(['company_id', 'slug']);
});
```

### ১.২ `offer_items` টেবিল (কম্বোর কম্পোনেন্ট প্রোডাক্ট; সিঙ্গেল অফারেও ১টা রো থাকবে — একই কাঠামো পুনঃব্যবহারযোগ্য)

```php
Schema::create('offer_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('company_id')->constrained()->cascadeOnDelete();
    $table->foreignId('offer_id')->constrained()->cascadeOnDelete();
    $table->foreignId('product_id')->constrained()->cascadeOnDelete();
    $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
    $table->unsignedInteger('quantity')->default(1); // কম্বোতে এই প্রোডাক্টের কয়টা ইউনিট থাকে
    $table->unsignedInteger('sort_order')->default(0);
    $table->timestamps();
});
```

### ১.৩ মডেল

```php
// app/Models/Offer.php
class Offer extends Model
{
    use BelongsToCompany;

    public const TYPE_SINGLE = 'single';
    public const TYPE_COMBO = 'combo';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'company_id', 'type', 'title', 'slug', 'status', 'short_description',
        'price_mode', 'manual_price', 'discount_type', 'discount_value',
        'blocks', 'is_ai_generated', 'ai_generated_at',
        'meta_title', 'meta_description', 'cover_image', 'online_payment_required',
    ];

    protected $casts = [
        'blocks' => 'array',
        'is_ai_generated' => 'boolean',
        'ai_generated_at' => 'datetime',
        'online_payment_required' => 'boolean',
        'manual_price' => 'decimal:2',
        'discount_value' => 'decimal:2',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(OfferItem::class)->orderBy('sort_order');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class); // ধাপ ৬ দেখুন — review offer বা product-এর সাথে যুক্ত হতে পারে
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }
}
```

`OfferItem` মডেল — সাধারণ `BelongsToCompany` মডেল, `offer()`/`product()`/`variant()` রিলেশন।

**CLAUDE.md কমপ্লায়েন্স:** `Offer` আর `OfferItem` দুটোই company-owned মডেল, তাই `BelongsToCompany` + `CompanyScope` লাগবে আর `MultiCompanyIsolationTest::test_every_company_owned_model_uses_the_company_scope_contract`-এ যোগ করতে হবে।

---

## ধাপ ২: দাম ও স্টক লজিক — `OfferPricingService`

```php
// app/Services/OfferPricingService.php
class OfferPricingService
{
    /** কম্পোনেন্ট প্রোডাক্টগুলোর দামের যোগফল (auto_sum মোডের বেস, আর manual মোডেও রেফারেন্স হিসেবে "was" প্রাইস দেখানোর জন্য) */
    public function componentsSubtotal(Offer $offer): float
    {
        return $offer->items->sum(function (OfferItem $item): float {
            $unitPrice = $item->productVariant?->price ?? $item->product->price;

            return (float) $unitPrice * $item->quantity;
        });
    }

    /** চূড়ান্ত বিক্রয়মূল্য — price_mode + discount প্রয়োগের পর */
    public function finalPrice(Offer $offer): float
    {
        $base = $offer->price_mode === 'manual' && $offer->manual_price !== null
            ? (float) $offer->manual_price
            : $this->componentsSubtotal($offer);

        if ($offer->discount_type === 'percent' && $offer->discount_value) {
            $base -= $base * ((float) $offer->discount_value / 100);
        } elseif ($offer->discount_type === 'flat' && $offer->discount_value) {
            $base -= (float) $offer->discount_value;
        }

        return max(0, round($base, 2));
    }

    /**
     * কম্বো অফারকে সাধারণ OrderItem রো-তে "explode" করা — প্রতিটা কম্পোনেন্ট
     * প্রোডাক্টের জন্য একটা আলাদা লাইন, quantity = component_qty × ordered_qty।
     * দাম আনুপাতিকভাবে বণ্টন করা হয় (প্রতিটা কম্পোনেন্টের স্বাভাবিক দামের
     * অনুপাতে final price ভাগ হয়) যাতে discount থাকলেও per-product
     * accounting/COGS মোটামুটি সঠিক থাকে। এভাবে বিদ্যমান stock/accounting
     * পাইপলাইন (OrderWorkflowService, StockMovementService) কোনো পরিবর্তন
     * ছাড়াই কম্বো অর্ডার হ্যান্ডেল করতে পারে — ওদের চোখে এটা শুধু N-টা
     * সাধারণ OrderItem, "কম্বো" ধারণাটা ওদের জানারই দরকার নেই।
     *
     * @return array<int, array{product_id:int, product_variant_id:?int, variant_label:?string, quantity:int, unit_price:float, unit_cost:float}>
     */
    public function explodeToOrderLines(Offer $offer, int $orderedQuantity): array
    {
        $componentsSubtotal = $this->componentsSubtotal($offer) ?: 1; // divide-by-zero guard
        $finalPrice = $this->finalPrice($offer);

        return $offer->items->map(function (OfferItem $item) use ($orderedQuantity, $componentsSubtotal, $finalPrice): array {
            $variant = $item->productVariant;
            $product = $item->product;
            $componentLineNormal = (float) ($variant?->price ?? $product->price) * $item->quantity;
            $share = $componentLineNormal / $componentsSubtotal;
            $totalQty = $item->quantity * $orderedQuantity;

            return [
                'product_id' => $product->getKey(),
                'product_variant_id' => $variant?->getKey(),
                'variant_label' => $variant?->label(),
                'quantity' => $totalQty,
                'unit_price' => round(($finalPrice * $share) / $totalQty, 2),
                'unit_cost' => (float) ($variant?->cost_price ?? $product->cost_price ?? 0),
            ];
        })->all();
    }

    /** চেকআউটের আগে স্টক আছে কিনা যাচাই — প্রতিটা কম্পোনেন্ট প্রোডাক্টের বিপরীতে */
    public function assertInStock(Offer $offer, int $orderedQuantity): void
    {
        foreach ($offer->items as $item) {
            $available = $item->productVariant?->stock ?? $item->product->stock;
            $needed = $item->quantity * $orderedQuantity;

            abort_if($needed > $available, 422, "{$item->product->name} does not have enough stock for this offer.");
        }
    }
}
```

সিঙ্গেল অফার (`type = single`) একই মডেল ব্যবহার করে, শুধু `offer_items`-এ ১টা রো থাকে (`quantity = 1`, সাধারণত `price_mode = manual` দিয়ে ডিসকাউন্ট প্রাইস বসানো হবে) — কোনো আলাদা কোড পাথ লাগবে না।

---

## ধাপ ৩: ল্যান্ডিং পেজ ব্লক স্কিমা

`offers.blocks` কলামে JSON অ্যারে, প্রতিটা এলিমেন্ট একটা ব্লক:

```json
[
  {"id": "b1", "type": "hero", "data": {"headline": "...", "subheadline": "...", "image": "offers/xyz.webp", "cta_label": "এখনই অর্ডার করুন"}},
  {"id": "b2", "type": "product_gallery", "data": {"images": ["...", "..."]}},
  {"id": "b3", "type": "usp_list", "data": {"heading": "কেন এই প্রোডাক্ট কিনবেন", "items": [{"icon": "check", "text": "..."}]}},
  {"id": "b4", "type": "how_to_buy", "data": {"heading": "কিভাবে অর্ডার করবেন", "steps": ["...", "..."]}},
  {"id": "b5", "type": "faq", "data": {"items": [{"question": "...", "answer": "..."}]}},
  {"id": "b6", "type": "testimonials", "data": {"heading": "কাস্টমার রিভিউ", "review_ids": [12, 15, 9]}},
  {"id": "b7", "type": "rich_text", "data": {"html": "..."}}
]
```

**ব্লক টাইপ ও ব্যবহার:** `hero` (ব্যানার/হেডলাইন), `product_gallery` (ছবি), `usp_list` (কেন কিনবেন), `how_to_buy` (কিভাবে কিনবেন), `faq` (প্রশ্নোত্তর), `testimonials` (`review_ids` দিয়ে approved রিভিউ থেকে নির্দিষ্ট কয়টা বেছে বসানো, বা খালি রেখে "সর্বোচ্চ রেটিং-এর N টা" অটো-পুল করার অপশন), `rich_text` (ফ্রি-ফর্ম কাস্টম কন্টেন্ট — flexibility-র জন্য)। **চেকআউট ফর্ম কোনো ব্লক না** — এটা পেজের নিচে সবসময় ফিক্সড থাকবে (ধাপ ৭), যাতে অ্যাডমিন ভুলে সরিয়ে না ফেলে।

**Filament ব্লক-এডিটর UI (`OfferResource`-এর ফর্মে):**

```php
Repeater::make('blocks')
    ->schema([
        Select::make('type')
            ->options([
                'hero' => 'Hero banner',
                'product_gallery' => 'Product gallery',
                'usp_list' => 'Why buy (USP list)',
                'how_to_buy' => 'How to buy',
                'faq' => 'FAQ',
                'testimonials' => 'Testimonials',
                'rich_text' => 'Custom text/HTML',
            ])
            ->live()
            ->required(),
        // প্রতিটা type-এর জন্য আলাদা sub-schema, ->visible(fn (Get $get) => $get('type') === '...')
        // দিয়ে শুধু প্রাসঙ্গিক ফিল্ড দেখানো হবে (হুবহু bannerRepeater()-এ যে প্যাটার্ন
        // ইতিমধ্যে StorefrontSettingResource.php-এ আছে সেটাই অনুসরণ করুন)
    ])
    ->reorderable()
    ->collapsible()
    ->itemLabel(fn (array $state): ?string => $state['type'] ?? null)
    ->addActionLabel('নতুন সেকশন যোগ করুন'),
```

এভাবেই "এলিমেন্ট অ্যাড/রিমুভ/এডিট" রিকোয়ারমেন্ট পূরণ হয় — Filament Repeater-এর নিজস্ব reorder/delete/collapse UI ব্যবহার করে, নতুন কোনো ফ্রন্টএন্ড বিল্ডার লাইব্রেরি ছাড়াই।

---

## ধাপ ৪: AI ল্যান্ডিং পেজ জেনারেশন

```php
// app/Services/OfferLandingPageAiGenerator.php
class OfferLandingPageAiGenerator
{
    public function __construct(protected AiSettingsService $aiSettings) {}

    public function generate(Offer $offer): array
    {
        $company = $offer->company;
        $settings = $this->aiSettings->all($company);

        if (blank($settings['api_key'] ?? null)) {
            throw new RuntimeException('এই কোম্পানির জন্য কোনো AI provider/API key কনফিগার করা নেই। প্রথমে AI Assistant Settings পেজ থেকে সেট করুন।');
        }

        $client = new AiLlmClient($settings['provider'], $settings['api_key'], $settings['model']);

        $productContext = $offer->items->map(fn (OfferItem $item): array => [
            'name' => $item->product->name,
            'description' => $item->product->description,
            'price' => (float) ($item->productVariant?->price ?? $item->product->price),
        ])->all();

        $system = <<<'PROMPT'
            তুমি একজন বাংলাদেশি ই-কমার্স কপিরাইটার। প্রোডাক্ট তথ্যের ওপর ভিত্তি করে বাংলা ভাষায়
            একটা ল্যান্ডিং পেজের কন্টেন্ট তৈরি করবে। শুধুমাত্র নিচের JSON স্কিমা মেনে রেসপন্স দাও।
            **কখনো দাম, স্টক পরিমাণ, বা ডেলিভারি-সংক্রান্ত সংখ্যা নিজে থেকে বানাবে না** — শুধু
            যা ইনপুটে দেওয়া হয়েছে তাই ব্যবহার করো। বাকি সবকিছু (হেডলাইন, USP, FAQ প্রশ্ন-উত্তর,
            "কিভাবে অর্ডার করবেন" ধাপ) স্বাভাবিক মার্কেটিং কপি হিসেবে লিখতে পারো।
            PROMPT;
        // ⚠️ এই "কখনো দাম/স্টক বানাবে না" guardrail-টা AiReplyService-এ ইতিমধ্যে প্রতিষ্ঠিত
        // "Never Echo/Never Hallucinate" নীতিরই সম্প্রসারণ — এখানেও একই কড়াকড়ি বজায় রাখা জরুরি।

        $result = $client->chat($system, [
            ['role' => 'user', 'content' => json_encode(['offer_title' => $offer->title, 'products' => $productContext], JSON_UNESCAPED_UNICODE)],
        ], tools: []); // structured output — response_format/JSON মোড ব্যবহার করুন যদি provider সাপোর্ট করে

        $blocks = $this->parseBlocksFromResponse($result['text']); // JSON পার্স + স্কিমা ভ্যালিডেশন

        $offer->update([
            'blocks' => $blocks,
            'is_ai_generated' => true,
            'ai_generated_at' => now(),
        ]);

        return $blocks;
    }

    protected function parseBlocksFromResponse(?string $text): array
    {
        // JSON extract + প্রতিটা ব্লকের 'type' ধাপ ৩-এর whitelist-এর মধ্যে আছে কিনা validate
        // করুন — অচেনা/malformed ব্লক থাকলে বাদ দিন, পুরো জেনারেশন ফেল করাবেন না।
    }
}
```

Filament `OfferResource`-এর এডিট পেজে একটা "AI দিয়ে জেনারেট করুন" অ্যাকশন বাটন — ক্লিক করলে এই সার্ভিস কল হয়ে `blocks` ফিল্ড পূরণ হয়ে যায়, তারপর অ্যাডমিন সরাসরি নিচের Repeater-এ ম্যানুয়ালি এডিট করতে পারে (regenerate করলে আগের ব্লকগুলো ওভাররাইট হবে কিনা নিশ্চিত করতে একটা কনফার্মেশন মডাল রাখুন)।

---

## ধাপ ৫: স্টোরফ্রন্ট ফানেল — রুট, কন্ট্রোলার, ভিউ

### ৫.১ রুট (`routes/web.php`)

```php
Route::get('/offers', [OfferController::class, 'index'])->name('storefront.offers.index');
Route::get('/offers/{offer:slug}', [OfferController::class, 'show'])->name('storefront.offers.show');
Route::post('/offers/{offer:slug}/checkout', [OfferCheckoutController::class, 'store'])->name('storefront.offers.checkout');
Route::get('/offers/{offer:slug}/thank-you/{order}', [OfferCheckoutController::class, 'thankYou'])->name('storefront.offers.thank-you');
```

(company-preview ভ্যারিয়েন্টও লাগবে, বিদ্যমান `storefront.preview.*` প্যাটার্ন অনুসরণ করে।)

### ৫.২ `OfferController::index` — লিস্টিং পেজ, Single/Combo ট্যাব-ফিল্টার

মেনু সরলীকরণ সিদ্ধান্ত: `/offers?type=single` বা `/offers?type=combo` কুয়েরি প্যারামিটার দিয়ে ফিল্টার করা একটা **একটাই** লিস্টিং পেজ — সাইটের হেডার মেনুতে "অফার" নামে একটা লিংক (`/offers`), পেজের ভেতরে দুইটা ট্যাব বাটন (সিঙ্গেল অফার / কম্বো অফার)। এটা বিদ্যমান `menuRepeater()`-এ **নতুন কোনো dropdown/submenu স্ট্রাকচার যোগ না করেই** কাজ করে — বর্তমান হেডার নেভিগেশন সম্পূর্ণ ফ্ল্যাট (`layout.blade.php` লাইন ৩৭১-৩৭৮, কোনো dropdown/submenu রেন্ডারিং লজিক নেই), তাই "মেনু থেকে অফার → সিঙ্গেল/কম্বো সাবমেনু" যদি সত্যিকারের ড্রপডাউন হিসেবে চান, সেটা একটা আলাদা, বড় UI কাজ (পুরো হেডার নেভিগেশন কম্পোনেন্ট পরিবর্তন, সব বিদ্যমান মেনু আইটেমকেও প্রভাবিত করবে) — এই প্ল্যানে **অপশনাল ধাপ ৫.৫** হিসেবে আলাদা রাখা হলো, ডিফল্ট সুপারিশ হলো ট্যাব-ভিত্তিক একটাই পেজ।

`menuRepeater()`-এ `type` অপশনে `'offers' => 'Offers'` যোগ করুন (কোনো sub-select লাগবে না, সরাসরি `/offers` route)।

### ৫.৩ `OfferController::show` — ল্যান্ডিং পেজ রেন্ডার

`resources/views/storefront/offers/show.blade.php` — `$offer->blocks` অ্যারের ওপর লুপ করে প্রতিটা ব্লক টাইপ অনুযায়ী partial include:

```blade
@foreach ($offer->blocks ?? [] as $block)
    @includeIf('storefront.offers.blocks.'.$block['type'], ['data' => $block['data']])
@endforeach
```

প্রতিটা ব্লক টাইপের জন্য `resources/views/storefront/offers/blocks/{hero,product_gallery,usp_list,how_to_buy,faq,testimonials,rich_text}.blade.php` — প্রতিটা storefront থিমের বিদ্যমান CSS ভ্যারিয়েবল (`--storefront-brand` ইত্যাদি) ব্যবহার করবে, নতুন কোনো CSS ফ্রেমওয়ার্ক না।

পেজের নিচে সবসময় একটা ফিক্সড চেকআউট ফর্ম সেকশন (নাম, ফোন, ঠিকানা, পরিমাণ, পেমেন্ট মেথড — বিদ্যমান `CheckoutController::validatedCheckout()`-এর সাথে হুবহু মিলিয়ে ফিল্ড রাখুন যাতে একই ভ্যালিডেশন রুল reuse করা যায়)।

### ৫.৪ `OfferCheckoutController` — অর্ডার তৈরি + অটো অ্যাকাউন্ট তৈরি

```php
class OfferCheckoutController extends Controller
{
    public function __construct(
        protected CompanyContext $context,
        protected OfferPricingService $pricing,
        protected CustomerAccountService $accounts,
        protected StorefrontDeliveryService $delivery,
        protected StorefrontDeliveryAreaResolver $deliveryAreas,
    ) {}

    public function store(Request $request, Offer $offer): View|RedirectResponse
    {
        abort_unless($offer->status === Offer::STATUS_PUBLISHED, 404);
        $company = $offer->company;
        $this->context->set($company);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['required', 'string', 'max:1000'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'payment_method' => ['required', 'in:cod,manual_bkash,manual_nagad'],
            'sender_number' => ['required_if:payment_method,manual_bkash,manual_nagad'],
            'trx_id' => ['required_if:payment_method,manual_bkash,manual_nagad'],
        ]);

        $this->pricing->assertInStock($offer, (int) $data['quantity']);
        $deliveryArea = $this->deliveryAreas->resolve($data['address'], $company->storefrontSetting);
        $lines = $this->pricing->explodeToOrderLines($offer, (int) $data['quantity']);
        $quote = $this->delivery->quote(collect($lines)->map(fn ($l) => ['product' => Product::find($l['product_id']), 'quantity' => $l['quantity']]), $deliveryArea, $company->storefrontSetting);

        $order = DB::transaction(function () use ($company, $offer, $data, $lines, $quote): Order {
            $customer = Customer::query()
                ->where('company_id', $company->getKey())
                ->where('phone', $data['phone'])
                ->lockForUpdate() // race-condition ফিক্স — আগের CRM প্ল্যানে (02_LEAD_CRM_MODULE_PLAN.md, ধাপ ১৬.৩) চিহ্নিত ঝুঁকি এখানেও প্রযোজ্য
                ->first();

            $isNewAccount = ! $customer?->isRegistered();
            $generatedPassword = $isNewAccount ? Str::password(12) : null;

            if ($isNewAccount) {
                $customer = $this->accounts->register([
                    'name' => $data['name'],
                    'phone' => $data['phone'],
                    'email' => $data['email'] ?? null,
                    'address' => $data['address'],
                    'password' => $generatedPassword,
                ]);
            }

            $order = Order::query()->create([
                'company_id' => $company->getKey(),
                'customer_id' => $customer->getKey(),
                'customer_name' => $customer->name,
                'order_date' => now()->toDateString(),
                'discount' => 0,
                'vat' => 0,
                'shipping_zone' => $deliveryArea,
                'shipping_fee' => $quote['fee'],
                'paid_amount' => 0,
                'status' => 'draft',
                'source' => Order::SOURCE_OFFER, // নতুন কনস্ট্যান্ট — Order::SOURCES-এ যোগ করুন
                'note' => "Offer landing page order: {$offer->title} (#{$offer->getKey()})",
            ]);

            foreach ($lines as $line) {
                OrderItem::query()->create(['order_id' => $order->getKey(), ...$line]);
            }

            $order->refresh();

            return [$order, $customer, $generatedPassword];
        });

        [$order, $customer, $generatedPassword] = $order;

        return view('storefront.offers.thank-you', [
            'offer' => $offer, 'order' => $order, 'customer' => $customer,
            'generatedPassword' => $generatedPassword, // শুধু এই একবারই দেখানো হবে, ডাটাবেসে প্লেইনটেক্সট কখনো সংরক্ষণ হয় না
        ]);
    }
}
```

**থ্যাংক ইউ পেজে (`storefront/offers/thank-you.blade.php`):** অর্ডার আইডি, কী অর্ডার হয়েছে, ডেলিভারি এস্টিমেট, আর `$generatedPassword` থাকলে — "আপনার অ্যাকাউন্ট তৈরি হয়েছে, লগইন: {phone}, পাসওয়ার্ড: {generatedPassword} — প্রোফাইল সেটিংস থেকে পরিবর্তন করতে পারবেন" বার্তা। এখানে email থাকলে একই তথ্য ইমেইলেও পাঠানো যায় (`StorefrontNotificationService` reuse করে) — কিন্তু SMS/WhatsApp-এ প্লেইনটেক্সট পাসওয়ার্ড পাঠানো এড়িয়ে চলুন, শুধু পেজে দেখানো আর (ঐচ্ছিক) ইমেইলে পাঠানো নিরাপদ।

**নোট — বিদ্যমান `CheckoutController`-এর সাথে সম্পর্ক:** এই নতুন কন্ট্রোলার সাধারণ multi-item cart-checkout-কে প্রভাবিত করে না, সম্পূর্ণ আলাদা রুট/কন্ট্রোলার। ভবিষ্যতে চাইলে `assertCartStock`/`createOrder`-এর কমন অংশ একটা shared trait/service-এ বের করে দুই কন্ট্রোলারই ব্যবহার করতে পারে (DRY), কিন্তু প্রথম ভার্সনে আলাদা রাখাই নিরাপদ (বিদ্যমান checkout flow ভাঙার ঝুঁকি নেই)।

### ৫.৫ (ঐচ্ছিক, পরে) সত্যিকারের ড্রপডাউন সাবমেনু

যদি ভবিষ্যতে "অফার" হোভার করলে "সিঙ্গেল অফার / কম্বো অফার" ড্রপডাউন দেখাতে হয়, `menuRepeater()`-এ একটা `children` রিপিটার নেস্ট করতে হবে, আর `layout.blade.php`-এর হেডার নেভ লুপে Alpine.js hover/click dropdown যোগ করতে হবে — এটা সব বিদ্যমান মেনু আইটেমকেও স্পর্শ করে বলে আলাদাভাবে, সাবধানে টেস্ট করে করা উচিত।

---

## ধাপ ৬: কাস্টমার রিভিউ সিস্টেম

### ৬.১ মাইগ্রেশন

```php
Schema::create('product_reviews', function (Blueprint $table) {
    $table->id();
    $table->foreignId('company_id')->constrained()->cascadeOnDelete();
    $table->foreignId('product_id')->constrained()->cascadeOnDelete();
    $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete(); // যাচাই করে যে সত্যিকারের ক্রেতা
    $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
    $table->string('customer_name'); // guest/pre-account রিভিউয়ের জন্যও snapshot নাম
    $table->unsignedTinyInteger('rating'); // ১-৫
    $table->text('comment')->nullable();
    $table->string('photo')->nullable(); // ঐচ্ছিক কাস্টমার-আপলোড ছবি
    $table->string('status')->default('pending'); // pending | approved | rejected
    $table->timestamps();
});
```

### ৬.২ মডেল ও পলিসি

`App\Models\ProductReview` — `BelongsToCompany`, `product()`/`order()`/`customer()` রিলেশন, `scopeApproved()`।

**সাবমিশন যোগ্যতা:** শুধু `Order::STATUS_COMPLETED` (অথবা delivery-stage অনুযায়ী delivered) অবস্থার অর্ডারের আইটেম থেকেই রিভিউ দেওয়া যাবে, আর প্রতি (customer, product, order) কম্বিনেশনে একটাই রিভিউ (unique constraint বা অ্যাপ-লেভেল চেক)।

### ৬.৩ স্টোরফ্রন্ট সাবমিশন — `AccountOrdersController`-এ যোগ

`resources/views/storefront/account/orders.blade.php`-এ প্রতিটা completed অর্ডার আইটেমের পাশে "রিভিউ দিন" বাটন (যদি ইতিমধ্যে রিভিউ না থাকে) — একটা নতুন `ProductReviewController::store()` যা logged-in `customer` guard থেকে কাস্টমার আইডেন্টিটি নেয় (নতুন কোনো auth লজিক লাগবে না, বিদ্যমান `Auth::guard('customer')->user()` reuse), rating + comment নেয়, `status = pending` দিয়ে সেভ করে।

### ৬.৪ অ্যাডমিন মডারেশন — `ProductReviewResource` (Filament)

লিস্ট + approve/reject bulk action + সিঙ্গেল action, `canManageSettings()`-এর মতো বিদ্যমান পারমিশন-চেক প্যাটার্ন অনুসরণ করে অ্যাক্সেস কন্ট্রোল।

### ৬.৫ ল্যান্ডিং পেজে testimonials ব্লক

ধাপ ৩-এর `testimonials` ব্লকের Filament sub-schema-তে একটা `Select::make('data.review_ids')->multiple()` — শুধু `status = approved` আর অফারের কম্পোনেন্ট প্রোডাক্টগুলোর সাথে সম্পর্কিত রিভিউ থেকে বেছে নেওয়া যাবে। খালি রাখলে ব্লেড partial অটো সর্বোচ্চ-রেটিং N-টা approved রিভিউ টেনে আনবে।

---

## ধাপ ৭: Filament অ্যাডমিন রিসোর্স

### `OfferResource` (`app/Filament/Resources/Offers/`)

- List পেজে `type` অনুযায়ী ট্যাব (Single Offer / Combo Offer), বিদ্যমান রিসোর্সগুলোর মতো `Tabs`/`SelectFilter` প্যাটার্ন।
- Form: Section ১ — বেসিক তথ্য (title, slug, status, type)। Section ২ — `offer_items` Repeater (প্রোডাক্ট/ভ্যারিয়েন্ট সিলেক্ট + quantity, combo হলে একাধিক রো, single হলে সাধারণত ১টা)। Section ৩ — pricing (`price_mode`, `manual_price`, `discount_type`, `discount_value`, আর `OfferPricingService::finalPrice()`-এর একটা লাইভ প্রিভিউ)। Section ৪ — "AI দিয়ে ল্যান্ডিং পেজ জেনারেট করুন" অ্যাকশন + ধাপ ৩-এর ব্লক Repeater।
- ছবি আপলোড ফিল্ড (`cover_image`) — বিদ্যমান `OptimizesUploadedImages` ট্রেইট reuse করুন (Products/Categories-এর মতো `->saveUploadedFileUsing(static::optimizeImageUpload())`)।

### `ProductReviewResource`

উপরে ৬.৪-এ বর্ণিত।

---

## ধাপ ৮: `Order::SOURCES`-এ নতুন কনস্ট্যান্ট

```php
public const SOURCE_OFFER = 'offer';
```

`Order::SOURCES` অ্যারেতে লেবেলসহ যোগ করুন, আর যেকোনো জায়গায় (রিপোর্ট, Filament badge কালার ম্যাপিং) যেখানে `SOURCE_STOREFRONT`/`SOURCE_CRM`/`SOURCE_CHAT` হ্যান্ডল করা হয় সেখানে `SOURCE_OFFER`-ও যোগ করুন (`grep -rn "SOURCE_STOREFRONT" app` দিয়ে সব call site বের করে নিন)।

---

## ধাপ ৯: টেস্ট

```txt
[ ] tests/Unit/Services/OfferPricingServiceTest.php — auto_sum vs manual price, percent/flat
    discount, explodeToOrderLines()-এর quantity/unit_price বণ্টন সঠিক কিনা
[ ] tests/Feature/OfferCheckoutTest.php — কম্বো অফার চেকআউট → সব কম্পোনেন্ট প্রোডাক্টের জন্য
    আলাদা OrderItem তৈরি হচ্ছে, স্টক পরিমাণ সঠিক, নতুন কাস্টমারের জন্য অ্যাকাউন্ট
    অটো-তৈরি হচ্ছে (password সেট আছে, isRegistered() true), বিদ্যমান কাস্টমার হলে duplicate
    অ্যাকাউন্ট তৈরি হচ্ছে না
[ ] tests/Feature/OfferCheckoutTest.php — স্টক অপর্যাপ্ত হলে 422 আর কোনো অর্ডার তৈরি হয় না
    (assertInStock)
[ ] tests/Feature/ProductReviewTest.php — শুধু completed অর্ডারের কাস্টমারই রিভিউ দিতে পারছে,
    ডুপ্লিকেট রিভিউ ব্লক হচ্ছে, pending রিভিউ অ্যাডমিন approve না করা পর্যন্ত পাবলিক পেজে
    দেখা যাচ্ছে না
[ ] tests/Feature/OfferLandingPageAiGeneratorTest.php — Http::fake() দিয়ে AiLlmClient মক করে
    ব্লক জেনারেশন, malformed JSON রেসপন্স গ্রেসফুলি হ্যান্ডল হচ্ছে, দাম/স্টক সংখ্যা প্রম্পটে
    hardcode করা ইনপুট ছাড়া অন্য কিছু ইনভেন্ট করছে না তা নিশ্চিত করার জন্য প্রম্পট-লেভেল রিভিউ
[ ] MultiCompanyIsolationTest-এ Offer/OfferItem/ProductReview যোগ — company A-র অফার/রিভিউ
    company B-র কনটেক্সটে কখনো দেখা যাচ্ছে না
[ ] পুরো php artisan test (কোনো --env flag ছাড়া)
```

---

## ধাপ ১০: ভেরিফিকেশন ও হ্যান্ডঅফ (CLAUDE.md অনুযায়ী)

```txt
[ ] php artisan test — পুরো স্যুট, কোনো রিগ্রেশন নেই
[ ] npm run build — যদি নতুন Blade/CSS/JS টাচ হয়
[ ] CHANGELOG.md (minor: নতুন ফিচার — Offers module) + UPDATE_NOTES.md, কমিটের আগে
[ ] Sandbox/staging-এ একটা ডেমো কোম্বো অফার বানিয়ে, AI দিয়ে ল্যান্ডিং পেজ জেনারেট করে,
    ম্যানুয়ালি ব্লক এডিট করে, পুরো ফানেল (ভিজিট → চেকআউট → থ্যাংক ইউ → অটো অ্যাকাউন্ট →
    প্রোফাইল থেকে পাসওয়ার্ড বদল) হাতে টেস্ট করা
[ ] owner-এর স্পষ্ট অনুমোদন ছাড়া git commit/push না করা
```

---

## সারসংক্ষেপ ফ্লো

```
ধাপ ১ (মডেল/মাইগ্রেশন) → ধাপ ২ (OfferPricingService)
   → ধাপ ৩ (ব্লক স্কিমা + Filament ব্লক-এডিটর) → ধাপ ৪ (AI জেনারেটর)
   → ধাপ ৫ (স্টোরফ্রন্ট ফানেল: রুট/কন্ট্রোলার/ভিউ + অটো অ্যাকাউন্ট)
   → ধাপ ৬ (রিভিউ সিস্টেম) → ধাপ ৭ (Filament OfferResource/ProductReviewResource)
   → ধাপ ৮ (Order::SOURCE_OFFER) → ধাপ ৯ (টেস্ট) → ধাপ ১০ (ভেরিফাই ও হ্যান্ডঅফ)
```

ধাপ ১-৪ ব্যাকএন্ড/ডাটা-লেয়ার, স্বাধীনভাবে টেস্টযোগ্য। ধাপ ৫-৭ কাস্টমার-facing ফানেল আর অ্যাডমিন UI, ধাপ ১-৪ শেষ হওয়ার পরই শুরু করা উচিত। ধাপ ৬ (রিভিউ) ধাপ ৫-এর ওপর নির্ভরশীল না — সমান্তরালে করা যায়।
