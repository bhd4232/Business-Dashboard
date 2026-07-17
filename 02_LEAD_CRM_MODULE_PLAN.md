# Lead/CRM Module — বিস্তারিত স্টেপ-বাই-স্টেপ ইমপ্লিমেন্টেশন প্ল্যান

## ✅ Current Status Snapshot (Master Plan v2 অনুযায়ী, ২০২৬-০৭ আপডেট)

এই ফাইল স্বয়ংসম্পূর্ণ — Claude Code সরাসরি এটা পড়ে কাজ শুরু করতে পারবে। প্রেক্ষাপটের জন্য মূল ERP-এর বর্তমান অবস্থা:

```txt
✅ Multi-Company System      — সম্পন্ন (Company, BelongsToCompany, CompanyScope,
                                CompanyContext, SetCurrentCompany middleware সব কোডে আছে)
✅ Courier Integration        — সম্পন্ন (Manual+Steadfast+webhook+CourierManager);
                                Pathao/RedX/E-Courier live client এখনো বাকি
✅ Customer Success/Risk Score — সম্পন্ন (rule-based scoring, blacklist, approval flow)
✅ Storefront Foundation      — সম্পন্ন (domain routing, cart, checkout, tracking,
                                dark/light mode) — WooCommerce migration ও production
                                domain go-live এখনো বাকি
✅ Lead/CRM Module (ধাপ ১–৯)  — সম্পন্ন ২০২৬-০৭-১৭ (Lead, LeadActivity, Quotation,
                                QuotationItem, LeadConversionService, Filament resources,
                                public quotation page, quotations:mark-expired, tests);
                                ধাপ ১০–১১ (Conversation Inbox, Click-to-Order)ও সম্পন্ন ২০২৬-০৭-১৭
✅ ধাপ ১৩–১৪ (AI Auto-Reply + FEP window) — সম্পন্ন ২০২৬-০৭-১৭ (AiReplyService tool-calling
                                agent, guardrails, CompanyFaq, AI Assistant settings, CTWA 72h,
                                AiAutoReplyTest — সব LLM কল mocked)
❌ ধাপ ১৫–১৭ (Sales-agent customer-context tool, ভিজ্যুয়াল অর্ডার ফর্ম, কাস্টমার
                                লগইন/পাসওয়ার্ড অ্যাকাউন্ট) — শুধু প্ল্যান, ইমপ্লিমেন্ট হয়নি (এই আপডেটে যুক্ত)
❌ Investor/Mudarabah Module  — এই মডিউলের ঠিক পরে আসে (01_INVESTOR_MUDARABAH_MODULE_PLAN.md)
```

> **প্রাসঙ্গিক বাস্তব ব্যবহার-ক্ষেত্র:** Garments Machinery company (Tasneem Knit Industry)-র storefront redesign আলোচনায় একটা "Machine Request" ফর্মের প্রয়োজন উঠে এসেছে — কাস্টমার মেশিনের বিবরণ দিয়ে inquiry পাঠাবেন, owner সেটা রিভিউ করে quotation পাঠাবেন। **এটা হুবহু এই Lead/CRM module-এর কাজ** (Lead → Quotation → Order flow) — machine-sourcing business model-এর জন্য আলাদা কোনো নতুন সিস্টেম লাগবে না, নিচের ডিজাইনই যথেষ্ট। storefront-এর সাধারণ checkout ফর্মের বদলে এই company-র জন্য একটা `quote_request` storefront mode যুক্ত হলে, সেই ফর্ম সরাসরি এই মডিউলের `Lead` তৈরি করবে।

## ⚠️ পূর্বশর্ত (এই কাজ শুরু করার আগে যাচাই করুন)

```txt
[✅] Multi-Company system সম্পূর্ণ এবং company isolation test পাস করেছে — কনফার্ম
[ ] Customer ও Order মডেল স্থিতিশীল এবং production-এ চলছে — verify করুন
[ ] Investor/Mudarabah module-এর আগে এই মডিউল করতে হবে (build order অনুযায়ী,
    Investor module-এর জন্য বাস্তব Lead→Order revenue ডেটা প্রয়োজন)
```

যদি উপরের কোনো শর্ত পূরণ না হয়, ব্যবহারকারীকে জিজ্ঞেস করুন আগে এগোনো উচিত কিনা।

> **কোডবেজ-যাচাই নোট (২০২৬-০৭-১৭):** এই প্ল্যানের কোড স্নিপেটগুলো বাস্তব কোডবেজের সাথে মিলিয়ে সংশোধিত — Filament **v4.11** (Schemas-ভিত্তিক রিসোর্স স্ট্রাকচার, `BadgeColumn` নেই), `Order`-এ `GeneratesSequentialNumber` + অটো totals (ম্যানুয়াল total সেট করা যাবে না), `OrderItem`-এ `company_id`/`product_variant_id`/`unit_cost` আবশ্যক, `Order::SOURCES`-এ নতুন `crm`/`chat` const যোগ করতে হবে। Order তৈরির রেফারেন্স ইমপ্লিমেন্টেশন: `Storefront\CheckoutController::store()`।

---

## মডিউলের উদ্দেশ্য

Facebook/WhatsApp/ফোন/Walk-in থেকে আসা প্রতিটা ব্যবসায়িক inquiry ট্র্যাক করা, ফলো-আপ ম্যানেজ করা, এবং Lead থেকে Quotation → Customer → Order-এ স্বাভাবিকভাবে রূপান্তর করা।

```txt
Lead তৈরি (Facebook/WhatsApp/ফোন থেকে)
→ Lead Activity/ফলো-আপ নোট যুক্ত
→ Quotation তৈরি ও পাঠানো (WhatsApp-শেয়ারযোগ্য লিংক)
→ Quotation accepted হলে → বিদ্যমান Customer + Order মডেলে convert
→ Lead status "won" → converted_customer_id ও converted_order_id সেভ
→ এরপরের পুরো flow (stock, payment, courier, risk) অপরিবর্তিত — বিদ্যমান লজিক ব্যবহার হবে
```

**গুরুত্বপূর্ণ নীতি:** এই মডিউল বিদ্যমান `Customer` এবং `Order` মডেলের যুক্তি **পুনর্লিখন করবে না** — শুধু তার আগে একটা নতুন "pre-customer" স্তর যুক্ত করবে। `Customer` মডেলে already `customer_source` ফিল্ড আছে (walk-in, facebook, website, referral, phone_call, other) — Lead module এই বিদ্যমান প্যাটার্নের স্বাভাবিক সম্প্রসারণ।

---

## ধাপ ১: Database Migration

### 1.1 `leads` টেবিল

```bash
php artisan make:migration create_leads_table
```

```php
Schema::create('leads', function (Blueprint $table) {
    $table->id();
    $table->foreignId('company_id')->constrained()->cascadeOnDelete();
    $table->string('name');
    $table->string('phone');
    $table->string('email')->nullable();
    $table->enum('source', [
        'facebook', 'whatsapp', 'website', 'referral', 'walk_in', 'phone_call', 'other',
    ])->default('other');
    $table->enum('status', ['new', 'contacted', 'quoted', 'won', 'lost'])->default('new');
    $table->text('interest')->nullable(); // কী পণ্যে আগ্রহী
    $table->decimal('estimated_value', 15, 2)->nullable();
    $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
    $table->dateTime('next_follow_up_at')->nullable();
    $table->foreignId('converted_customer_id')->nullable()->constrained('customers')->nullOnDelete();
    $table->foreignId('converted_order_id')->nullable()->constrained('orders')->nullOnDelete();
    $table->text('note')->nullable();
    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();

    $table->index(['company_id', 'status']);
    $table->index('phone');
});
```

### 1.2 `lead_activities` টেবিল

```bash
php artisan make:migration create_lead_activities_table
```

```php
Schema::create('lead_activities', function (Blueprint $table) {
    $table->id();
    $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
    $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
    $table->enum('type', ['call', 'message', 'note', 'meeting', 'status_change']);
    $table->text('note')->nullable();
    $table->dateTime('next_action_at')->nullable();
    $table->timestamps();
});
```

### 1.3 `quotations` টেবিল

```bash
php artisan make:migration create_quotations_table
```

```php
Schema::create('quotations', function (Blueprint $table) {
    $table->id();
    $table->foreignId('company_id')->constrained()->cascadeOnDelete();
    $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
    $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
    $table->string('quotation_number')->unique(); // GeneratesSequentialNumber trait দিয়ে অটো (Order-এর মতো)
    $table->enum('status', ['draft', 'sent', 'accepted', 'rejected', 'expired'])->default('draft');
    $table->date('valid_until')->nullable();
    $table->decimal('discount_amount', 15, 2)->default(0);
    $table->decimal('total_amount', 15, 2)->default(0);
    $table->foreignId('converted_order_id')->nullable()->constrained('orders')->nullOnDelete();
    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();

    $table->index(['company_id', 'status']);
});
```

**নোট:** `lead_id` এবং `customer_id` দুটোই nullable, কারণ Quotation একটা নতুন Lead-এর জন্য হতে পারে, অথবা বিদ্যমান Customer-এর জন্য (যিনি নতুন পণ্যের দাম জানতে চাইছেন) হতে পারে।

### 1.4 `quotation_items` টেবিল

```bash
php artisan make:migration create_quotation_items_table
```

```php
Schema::create('quotation_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('company_id')->constrained()->cascadeOnDelete(); // OrderItem-এর প্যাটার্ন অনুযায়ী
    $table->foreignId('quotation_id')->constrained()->cascadeOnDelete();
    $table->foreignId('product_id')->constrained()->cascadeOnDelete();
    $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete(); // ProductVariant বিদ্যমান
    $table->string('variant_label')->nullable();
    $table->integer('quantity');
    $table->decimal('unit_price', 15, 2);
    $table->decimal('subtotal', 15, 2);
    $table->timestamps();
});
```

**নোট (কোডবেজ-যাচাইকৃত):** বিদ্যমান `OrderItem`-এ `company_id`, `product_variant_id`, `variant_label`, `unit_cost` আছে — quotation item সেই প্যাটার্ন অনুসরণ করবে, যাতে Order-এ convert করার সময় ফিল্ড ম্যাপিং সরল থাকে। `lead_activities`-এ `company_id` নেই — এটা ইচ্ছাকৃত, parent `Lead` দিয়ে স্কোপড (যেমন `ConversationMessage` parent দিয়ে স্কোপড)।

### 1.5 মাইগ্রেশন রান

```bash
php artisan migrate
```

**ম্যানুয়াল চেক:** `leads`, `lead_activities`, `quotations`, `quotation_items` — চারটা টেবিল তৈরি হয়েছে কিনা ডেটাবেসে দেখুন।

---

## ধাপ ২: Eloquent Models

### 2.1 `Lead` মডেল

```bash
php artisan make:model Lead
```

```php
namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lead extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'name', 'phone', 'email', 'source', 'status',
        'interest', 'estimated_value', 'assigned_to', 'next_follow_up_at',
        'converted_customer_id', 'converted_order_id', 'note', 'created_by',
    ];

    protected $casts = [
        'next_follow_up_at' => 'datetime',
        'estimated_value' => 'decimal:2',
    ];

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(LeadActivity::class);
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class);
    }

    public function convertedCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'converted_customer_id');
    }

    public function convertedOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'converted_order_id');
    }

    public function isConverted(): bool
    {
        return $this->status === 'won' && $this->converted_customer_id !== null;
    }
}
```

### 2.2 `LeadActivity` মডেল

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadActivity extends Model
{
    protected $fillable = ['lead_id', 'user_id', 'type', 'note', 'next_action_at'];

    protected $casts = ['next_action_at' => 'datetime'];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

### 2.3 `Quotation` মডেল

```php
namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quotation extends Model
{
    use BelongsToCompany, \App\Models\Concerns\GeneratesSequentialNumber;

    protected function sequentialNumberColumn(): string
    {
        return 'quotation_number';
    }

    // creating hook-এ Order-এর order_number প্যাটার্ন অনুসরণ করে "QT-0001" ফরম্যাটে
    // নম্বর মিন্ট করুন — trait-টি concurrency-safe retry নিজেই হ্যান্ডেল করে।

    protected $fillable = [
        'company_id', 'lead_id', 'customer_id', 'quotation_number', 'status',
        'valid_until', 'discount_amount', 'total_amount', 'converted_order_id', 'created_by',
    ];

    protected $casts = [
        'valid_until' => 'date',
        'discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class);
    }

    public function convertedOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'converted_order_id');
    }

    public function recalculateTotal(): void
    {
        $itemsTotal = $this->items->sum('subtotal');
        $this->total_amount = $itemsTotal - $this->discount_amount;
        $this->save();
    }

    public function isExpired(): bool
    {
        return $this->valid_until && $this->valid_until->isPast() && $this->status === 'sent';
    }
}
```

### 2.4 `QuotationItem` মডেল

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationItem extends Model
{
    protected $fillable = ['quotation_id', 'product_id', 'quantity', 'unit_price', 'subtotal'];

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
```

### 2.5 বিদ্যমান `Customer` মডেলে যুক্ত করুন (সম্পাদনা, পুনর্লিখন না)

```php
// Customer.php-তে যুক্ত করুন, বিদ্যমান কিছু সরাবেন না
public function originLead(): HasOne
{
    return $this->hasOne(Lead::class, 'converted_customer_id');
}
```

---

## ধাপ ৩: Lead → Customer/Order Conversion Service

এটাই এই মডিউলের সবচেয়ে গুরুত্বপূর্ণ অংশ — এখানে কোনো ভুল করলে duplicate customer/order তৈরি হতে পারে।

### 3.1 `LeadConversionService` তৈরি করুন

```bash
mkdir -p app/Services/Crm
```

```php
namespace App\Services\Crm;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\Quotation;
use Illuminate\Support\Facades\DB;

class LeadConversionService
{
    /**
     * Lead থেকে Customer তৈরি করে (বিদ্যমান Customer মডেল ব্যবহার করে,
     * নতুন কোনো customer creation logic লেখা হয়নি)
     */
    public function convertToCustomer(Lead $lead): Customer
    {
        if ($lead->converted_customer_id) {
            return $lead->convertedCustomer;
        }

        return DB::transaction(function () use ($lead) {
            // ফোন নম্বর দিয়ে আগে দেখুন কাস্টমার আগে থেকেই আছে কিনা — duplicate এড়াতে
            $existing = Customer::where('company_id', $lead->company_id)
                ->where('phone', $lead->phone)
                ->first();

            $customer = $existing ?? Customer::create([
                'company_id' => $lead->company_id,
                'name' => $lead->name,
                'phone' => $lead->phone,
                'email' => $lead->email,
                'customer_type' => 'regular',          // CheckoutController-এর বিদ্যমান প্যাটার্ন
                'customer_source' => $lead->source,     // free-string কলাম, বিদ্যমান মানের সাথে সামঞ্জস্যপূর্ণ
                'opening_balance' => 0,
                'is_active' => true,
            ]);

            $lead->update(['converted_customer_id' => $customer->id]);

            return $customer;
        });
    }

    /**
     * Accepted Quotation থেকে Order তৈরি করে।
     * বিদ্যমান Order/OrderItem creation logic পুনরায় ব্যবহার করা হয়েছে,
     * নতুন stock movement logic লেখা হয়নি।
     */
    public function convertQuotationToOrder(Quotation $quotation): \App\Models\Order
    {
        if ($quotation->converted_order_id) {
            return $quotation->convertedOrder;
        }

        if ($quotation->status !== 'accepted') {
            throw new \RuntimeException('Only accepted quotations can be converted to an order.');
        }

        return DB::transaction(function () use ($quotation) {
            $customer = $quotation->customer
                ?? ($quotation->lead ? $this->convertToCustomer($quotation->lead) : null);

            if (! $customer) {
                throw new \RuntimeException('Quotation must have a customer or lead to convert.');
            }

            // ⚠️ কোডবেজ-যাচাইকৃত: Order তৈরির প্যাটার্ন হুবহু
            // Storefront\CheckoutController::store() অনুসরণ করবে —
            // order_number অটো (GeneratesSequentialNumber), company_id অটো (BelongsToCompany),
            // subtotal/total_amount/due_amount বিদ্যমান Order/OrderItem ইভেন্টে অটো-recalc হয়
            // (checkout-ও ম্যানুয়ালি total সেট করে না, শেষে $order->refresh() করে)।
            $order = \App\Models\Order::query()->create([
                'customer_id' => $customer->getKey(),
                'customer_name' => $customer->name,
                'order_date' => now()->toDateString(),
                'discount' => $quotation->discount_amount,
                'vat' => 0,
                'paid_amount' => 0,
                'status' => 'draft',
                'source' => \App\Models\Order::SOURCE_CRM, // নতুন const — নিচের নোট দেখুন
                'note' => "CRM quotation {$quotation->quotation_number} থেকে তৈরি",
            ]);

            foreach ($quotation->items as $item) {
                \App\Models\OrderItem::query()->create([
                    'order_id' => $order->getKey(),
                    'product_id' => $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                    'variant_label' => $item->variant_label,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'unit_cost' => ($item->productVariant?->cost_price
                        ?? $item->product->cost_price) ?? 0,
                ]);
            }

            $order->refresh(); // totals এখন অটো-ক্যালকুলেটেড

            $quotation->update(['converted_order_id' => $order->id]);

            if ($quotation->lead) {
                $quotation->lead->update([
                    'status' => 'won',
                    'converted_order_id' => $order->id,
                ]);
            }

            return $order;
        });
    }
}
```

**⚠️ গুরুত্বপূর্ণ:** stock movement ও total calculation বিদ্যমান Order লাইফসাইকেল (`OrderWorkflowService::sync()` ও মডেল ইভেন্ট) থেকেই হবে — এই service-এ পুনরায় লেখা হবে না। Order status `draft`-এ থাকায় বিদ্যমান confirm/complete workflow অপরিবর্তিত কাজ করবে।

**প্রয়োজনীয় ছোট সংযোজন (Order মডেলে):** `Order::SOURCES` const-এ এখন শুধু `admin` ও `storefront` আছে। যোগ করুন:

```php
public const SOURCE_CRM = 'crm';
// SOURCES অ্যারেতে: self::SOURCE_CRM => 'CRM',
```

চ্যাট-অর্ডারের (ধাপ ১১) জন্য একইভাবে `SOURCE_CHAT = 'chat'` যোগ হবে — অর্ডার লিস্টে source ফিল্টারে দুটোই দেখা যাবে।

---

## ধাপ ৪: Filament Resources

> **⚠️ কোডবেজ-যাচাইকৃত (২০২৬-০৭-১৭):** প্রজেক্টে **Filament v4.11** চলছে, v3 নয়। রিসোর্স স্ট্রাকচার হলো ডিরেক্টরি-ভিত্তিক: `app/Filament/Resources/Leads/{LeadResource.php, Pages/, Schemas/LeadForm.php, Schemas/LeadInfolist.php, Tables/LeadsTable.php}` — হুবহু বিদ্যমান `Customers/` রিসোর্সের মতো। v3-এর `Forms\Components\*` namespace আর `BadgeColumn` এখানে **চলবে না** — `Filament\Schemas\Schema`, `Filament\Forms\Components\*` (v4 path), এবং `TextColumn::make()->badge()` ব্যবহার করতে হবে। নিচের কোড v4 সিনট্যাক্সে।

### 4.1 `LeadResource`

```bash
php artisan make:filament-resource Lead --generate
```

তারপর `CustomerResource`-এর গঠন মিলিয়ে নিন (navigationGroup: `'Sales'` বা নতুন `'CRM'` group, `Heroicon` enum icon, `$recordTitleAttribute = 'name'`)।

`Schemas/LeadForm.php`:

```php
public static function configure(Schema $schema): Schema
{
    return $schema->components([
        TextInput::make('name')->required(),
        TextInput::make('phone')->required()->tel(),
        TextInput::make('email')->email(),
        Select::make('source')->options([
            'facebook' => 'Facebook', 'whatsapp' => 'WhatsApp', 'website' => 'Website',
            'referral' => 'Referral', 'walk_in' => 'Walk-in',
            'phone_call' => 'Phone Call', 'other' => 'Other',
        ])->required(),
        Select::make('status')->options([
            'new' => 'New', 'contacted' => 'Contacted', 'quoted' => 'Quoted',
            'won' => 'Won', 'lost' => 'Lost',
        ])->default('new'),
        Textarea::make('interest'),
        TextInput::make('estimated_value')->numeric(),
        Select::make('assigned_to')->relationship('assignedUser', 'name'),
        DateTimePicker::make('next_follow_up_at'),
        Textarea::make('note'),
    ]);
}
```

`Tables/LeadsTable.php` (v4-এ badge হলো `TextColumn`-এর modifier):

```php
TextColumn::make('name')->searchable(),
TextColumn::make('phone')->searchable(),
TextColumn::make('source')->badge(),
TextColumn::make('status')->badge()->color(fn (string $state): string => match ($state) {
    'new' => 'gray', 'contacted' => 'warning', 'quoted' => 'info',
    'won' => 'success', 'lost' => 'danger',
}),
TextColumn::make('estimated_value')->money('BDT'),
TextColumn::make('assignedUser.name'),
TextColumn::make('next_follow_up_at')->dateTime()
    ->color(fn ($state) => $state?->isPast() ? 'danger' : null), // overdue follow-up লাল
```

দরকারি টেবিল ফিল্টার: status, source, assigned_to, "আজকের follow-up", "overdue follow-up"।

কাস্টম অ্যাকশন (v4-এ `Filament\Actions\Action`, টেবিল অ্যাকশনও একই class):

```php
Action::make('convertToCustomer')
    ->label('Customer-এ পরিণত করুন')
    ->visible(fn (Lead $record) => ! $record->converted_customer_id)
    ->requiresConfirmation()
    ->action(fn (Lead $record) => app(LeadConversionService::class)->convertToCustomer($record)),
```

**Lead View পেজ:** `LeadInfolist` + দুটি relation manager — `ActivitiesRelationManager` (inline create: type, note, next_action_at) এবং `QuotationsRelationManager`। ধাপ ১০ হলে `ConversationsRelationManager`ও যুক্ত হবে — সব এক পেজে: lead তথ্য + follow-up + quotation + চ্যাট হিস্টরি।

**Dashboard widget (সুপারিশকৃত):** "আজকের follow-up" লিস্ট widget + status-ভিত্তিক lead count stats — বিদ্যমান dashboard widget প্যাটার্ন অনুসরণ করে।

### 4.2 `QuotationResource`

একই v4 স্ট্রাকচার। আইটেম ম্যানেজমেন্ট: `Repeater` — প্রতি রো-তে product Select (company-scoped, searchable), variant Select (product-dependent, `ProductVariant` বিদ্যমান), quantity, unit_price (product সিলেক্টে selling_price অটো-ফিল, override-যোগ্য), subtotal reactive-calculated। বিদ্যমান `Purchases` রিসোর্সের item repeater-এর গঠন অনুসরণ করুন।

কাস্টম অ্যাকশনসমূহ:

```php
Action::make('convertToOrder')
    ->label('Order-এ পরিণত করুন')
    ->visible(fn (Quotation $record) => $record->status === 'accepted' && ! $record->converted_order_id)
    ->requiresConfirmation()
    ->action(fn (Quotation $record) => app(LeadConversionService::class)->convertQuotationToOrder($record)),

Action::make('markAccepted')->visible(fn ($r) => $r->status === 'sent'),   // status টগল
Action::make('downloadPdf'),  // বিদ্যমান OrderPdfController প্যাটার্নে QuotationPdfController
```

**PDF:** admin-এ অর্ডার PDF যেভাবে হয় (`OrderPdfController`), একই লাইব্রেরি/প্যাটার্নে quotation PDF। **Expired অটো-মার্ক:** `quotations:mark-expired` scheduled command — `valid_until` পেরোনো `sent` quotation → `expired`, per-company লুপে।

---

## ধাপ ১৩: AI Auto-Reply — কাস্টমারের প্রশ্ন বুঝে নিজে থেকে উত্তর

> **কোডবেজ-যাচাই নোট (২০২৬-০৭-১৭, ৩):** এই সেকশনটা বাস্তবে ইমপ্লিমেন্ট হওয়া `Conversation` / `ConversationChannel` / `ConversationMessage` মডেল ও `conversations`/`conversation_messages` মাইগ্রেশনের উপর ভিত্তি করে লেখা (Conversation Inbox module ইতিমধ্যে কোডবেজে আছে)। বিদ্যমান `Conversation::withinReplyWindow()` মেথড ২৪ঘণ্টা service window চেক করে — CTWA-এর ৭২ঘণ্টা Free Entry Point window এই মেথডেই যুক্ত হবে (নিচে ১৩.০ দেখুন), নতুন কোনো প্যারালাল লজিক না লিখে।
>
> **রেফারেন্স ওয়ার্কফ্লো (২০২৬-০৭-১৭, ৪):** প্রজেক্ট ফোল্ডারের `n8n Workflows/` -এ দুটো প্রোডাকশন-চালু n8n চ্যাটবট পাওয়া গেছে — `Whatsapp Chatbot + Order Notification.json` (5-Minute Academy, WasenderAPI দিয়ে) এবং `messenger n8n workflows/Advanced Messenger 2.0.json` (HomeFood/"Foodik", Facebook Graph API দিয়ে)। এগুলো বাস্তবে চলা, বাংলাদেশি কাস্টমারদের সাথে কাজ করা সিস্টেম — তাই এই দুটোর architecture ও system-prompt প্যাটার্ন থেকে যা কাজ করে বলে প্রমাণিত, তা নিচে ১৩.৩–১৩.৮-এ অন্তর্ভুক্ত করা হলো। ZamZam CRM ডিজাইন এগুলোর সরাসরি কপি নয় — কিন্তু প্রমাণিত guardrail ও flow প্যাটার্নগুলো গ্রহণ করা হয়েছে, আর multi-tenant ERP-এর জন্য company-configurable করে সাধারণীকরণ করা হয়েছে (রেফারেন্স ওয়ার্কফ্লো দুটো single-business hardcoded, আমাদেরটা company-agnostic হতে হবে)।

### ১৩.০ পূর্বশর্ত সংযোজন — CTWA Free Entry Point কলাম

Facebook/Instagram-এ "Send WhatsApp Message" CTA বিজ্ঞাপনে ক্লিক করে আসা প্রথম মেসেজে Meta সাধারণ ২৪ঘণ্টার বদলে **৭২ঘণ্টা ফ্রি (FEP)** উইন্ডো দেয়। বিদ্যমান `conversations` টেবিলে migration দিয়ে যোগ করুন:

```php
Schema::table('conversations', function (Blueprint $table) {
    $table->string('entry_point')->nullable()->after('provider');   // 'ctwa_ad' | null
    $table->string('ad_referral_id')->nullable()->after('entry_point');
});
```

`Conversation::withinReplyWindow()`-এ যোগ করুন (বিদ্যমান মেথড এডিট, নতুন মেথড নয়):

```php
public function withinReplyWindow(): bool
{
    if (! in_array($this->provider, ['whatsapp', 'messenger'], true)) {
        return true;
    }

    $lastIncomingAt = $this->messages()->where('direction', 'incoming')->latest('sent_at')->value('sent_at');
    if ($lastIncomingAt === null) {
        return false;
    }

    $hours = $this->entry_point === 'ctwa_ad' ? 72 : 24;

    return now()->diffInHours($lastIncomingAt, true) < $hours;
}
```

`MetaWebhookController`-এ ইনকামিং মেসেজ পার্স করার সময় payload-এ `referral.source_type === 'ad'` পেলে নতুন Conversation-এ `entry_point = 'ctwa_ad'` ও `ad_referral_id` সেভ করুন (Meta শুধু ad-click থেকে আসা প্রথম মেসেজেই `referral` পাঠায়)। Inbox UI-তে countdown badge (`withinReplyWindow()` + বাকি সময় হিসাব করে দেখানো)।

### ১৩.১ নীতি — কেন "grounded-only", কখনো ফ্রি-ফর্ম নয়

ই-কমার্সে ভুল উত্তরের বাস্তব মূল্য আছে — AI যদি ভুল দাম বা "স্টকে আছে" বলে দেয় আর পরে না থাকে, সেটা সরাসরি বিরোধ/রিফান্ডে গড়ায়। তাই এই মডিউলে AI **কখনো নিজের জ্ঞান থেকে দাম/স্টক/অফার বলবে না** — শুধু আমাদের নিজস্ব ডেটাবেজ থেকে রিয়েল-টাইম লুকআপ করা তথ্য বলবে (RAG + tool-calling প্যাটার্ন, ফ্রি-টেক্সট generation নয়)। অনিশ্চিত/স্পর্শকাতর/জটিল প্রশ্নে সবসময় মানুষের কাছে হ্যান্ডঅফ করবে।

### ১৩.২ কী প্রশ্নের উত্তর AI নিজে দেবে (scope)

```txt
✅ দিতে পারবে (ডেটাবেজ-ভিত্তিক, deterministic lookup):
   - দাম/স্টক/ভ্যারিয়েন্ট → Product/ProductVariant (company-scoped) থেকে সরাসরি
   - ডেলিভারি চার্জ/পেমেন্ট মেথড → StorefrontSetting থেকে
   - "অর্ডার কীভাবে করব?" → ChatOrderLink (বিদ্যমান, ধাপ ১১) তৈরি করে পাঠিয়ে দেবে
   - সাধারণ FAQ → নতুন CompanyFaq টেবিল থেকে

❌ কখনো AI একা দেবে না — বাধ্যতামূলক human handoff:
   - দরদাম/কাস্টম ডিসকাউন্ট, অভিযোগ/রিফান্ড দাবি
   - কনফিডেন্স থ্রেশহোল্ডের নিচের উত্তর (১৩.৪)
   - কাস্টমার স্পষ্ট করে "মানুষের সাথে কথা বলতে চাই" বললে
```

### ১৩.৩ আর্কিটেকচার — Tool-Calling Agent (n8n রেফারেন্স থেকে গৃহীত প্যাটার্ন)

দুটো রেফারেন্স ওয়ার্কফ্লোই প্রমাণ করে যে single-shot "prompt + regex validate" এর চেয়ে **tool-calling agent loop** ঢের বেশি নির্ভরযোগ্য — LLM নিজে সিদ্ধান্ত নেয় কোন tool কখন কল করবে, আর প্রতিটা tool deterministic ডেটা রিটার্ন করে (Google Sheets/Supabase-এর জায়গায় আমাদের ক্ষেত্রে সরাসরি Eloquent query)। PHP-তে এই প্যাটার্ন follow করতে **Prism PHP** বা Anthropic/OpenAI SDK-এর native tool-use বাধ্যতামূলক (raw completion API নয়):

```txt
StoreIncomingMessageJob (বিদ্যমান) মেসেজ সেভ করার পর
→ AiAutoReplyJob dispatch (কোম্পানিতে AI enabled, conversation.human_handled_until
  না পার হলে স্কিপ, এবং Conversation::withinReplyWindow() true হলেই)
→ AiReplyService — LLM-কে tool definitions সহ কল করা হয়, LLM নিজে ঠিক করে কোনটা লাগবে:
   - lookup_product(name)        → Product/ProductVariant company-scoped সার্চ, দাম+স্টক+ভ্যারিয়েন্ট রিটার্ন
   - lookup_faq(topic)           → CompanyFaq থেকে
   - lookup_delivery_charge()    → StorefrontSetting থেকে
   - create_order_link(items[])  → ChatOrderLink তৈরি করে (বিদ্যমান ধাপ ১১)
   - escalate_to_human(reason)   → conversation.status='pending' + notify (১৩.৫)
   প্রতিটা tool call ও তার রেজাল্ট conversation-এর সাথে লগ হয় (audit)।
   LLM-কে system prompt-এ Foodik-স্টাইল কড়া নিয়ম দেওয়া হয় (১৩.৮ দেখুন) —
   বিশেষত "কখনো user-দাবি করা তথ্য repeat/echo করবে না, সবসময় tool কল করে
   নিজে যাচাই করবে"।
→ escalate_to_human কল হলে বা conversation intent complaint/pricing_negotiation
  হলে → সরাসরি handoff (১৩.৫), কোনো auto-reply পাঠানো হয় না
→ চূড়ান্ত উত্তর structured output validate (১৩.৪) → পাস করলে
  ConversationMessengerService দিয়ে reply, fail করলে handoff
→ AI রিপ্লাই ConversationMessage-এ direction=outgoing, sent_by=null,
  generated_by='ai' সহ সেভ (tool call trace raw_payload-এ)
```

`conversation_messages` টেবিলে migration দিয়ে যোগ:

```php
Schema::table('conversation_messages', function (Blueprint $table) {
    $table->string('generated_by', 10)->default('human')->after('sent_by'); // human | ai
    $table->decimal('ai_confidence', 4, 3)->nullable()->after('generated_by');
});
```

`conversations` টেবিলে আরও যোগ:

```php
Schema::table('conversations', function (Blueprint $table) {
    $table->boolean('ai_enabled')->default(true)->after('status');
    $table->dateTime('human_handled_until')->nullable()->after('ai_enabled');
});
```

### ১৩.৪ Confidence ও Grounding গার্ডরেইল

```txt
- LLM প্রম্পটে structured output বাধ্যতামূলক: { answer, used_product_ids[],
  confidence(0-1), needs_human(bool) }
- needs_human বা confidence < থ্রেশহোল্ড (ডিফল্ট 0.75, admin-কনফিগারেবল) হলে
  → auto-reply পাঠানো হয় না, conversation.status='pending' + agent নোটিফিকেশন
- Post-generation validation (সবচেয়ে গুরুত্বপূর্ণ সেফগার্ড): answer-এ টাকার
  অংক (৳/টাকা) থাকলে used_product_ids-এর প্রকৃত selling_price-এর সাথে exact
  না মিললে reply ব্লক — LLM-কে বিশ্বাস না করে কোডে cross-check
- একই conversation-এ পরপর ৩টার বেশি AI রিপ্লাইয়ের পর auto-handoff
- **"Never Echo" নিয়ম (Foodik রেফারেন্স থেকে, ১৩.৭.২ দেখুন):** system prompt-এ
  স্পষ্ট নিষেধ — কাস্টমার নিজে যদি দাম/স্টক/অফার সম্পর্কে কোনো দাবি করে
  ("আপনি তো বলেছিলেন ৫০০ টাকা"), AI সেটা কখনো সত্যি ধরে নেবে না বা repeat
  করবে না — সবসময় `lookup_product` tool কল করে নিজে যাচাই করবে। এটা শুধু
  ভুল তথ্য ঠেকানো নয়, prompt-injection resistance-ও (কাস্টমার চ্যাটে ভুয়া
  "system"/"admin" নির্দেশ পাঠিয়ে AI-কে বিভ্রান্ত করার চেষ্টা করলেও অকার্যকর
  থাকে, কারণ AI কখনো conversation body-কে instruction হিসেবে trust করে না)।

### ১৩.৫ Human Handoff

```txt
- handoff হলে conversation.status='pending', Inbox-এ "🤖→👤 needs review" ব্যাজ,
  Filament notification।
- মানুষ একবার reply দিলে human_handled_until = now()->addHours(24) সেট —
  ততক্ষণ AiAutoReplyJob স্কিপ।
- প্রথম AI মেসেজে সবসময় transparency: "আমি [Company]-এর অ্যাসিস্ট্যান্ট" identifier।
```

### ১৩.৬ Admin কনফিগারেশন

```txt
- Company-প্রতি AI settings (encrypted): LLM provider (Claude/OpenAI), API key,
  enabled toggle, confidence threshold, business-hours-only টগল, daily budget cap
- CompanyFaq রিসোর্স (প্রশ্ন-উত্তর জোড়া, AI context-এ ব্যবহৃত)
- ConversationChannel-এ ইতিমধ্যে auto_create_leads আছে — AI settings একই company
  settings গ্রুপে যুক্ত হবে, আলাদা টেবিল না বানিয়ে বিদ্যমান company settings
  প্যাটার্ন (CompanySettingsService) অনুসরণ করে
- মাসিক AI cost রিপোর্ট widget (token usage `ai_meta` json কলামে লগ)
```

**প্রোভাইডার নোট:** `ConversationChannel::PROVIDERS`-এ এখন শুধু `whatsapp`/`messenger` আছে, WasenderAPI-এর জন্য আলাদা এন্ট্রি নেই। রেফারেন্স ওয়ার্কফ্লো (৫-Minute Academy) বাস্তবে WasenderAPI ব্যবহার করে — এটা আগের আলোচনায় (ban ঝুঁকি, unofficial gateway) বিশ্লেষণ করা হয়েছে। ZamZam CRM-এ AI Auto-Reply feature-টা provider-agnostic (ConversationMessengerService interface-এর মাধ্যমে) — তাই company চাইলে WhatsApp Cloud API বা Wasender-জাতীয় গেটওয়ে, যেকোনোটাই বেছে নিতে পারবে, AI লজিক অপরিবর্তিত থাকবে।

### ১৩.৭ n8n রেফারেন্স থেকে নির্দিষ্ট প্যাটার্ন-ম্যাপিং

**১৩.৭.১ — WhatsApp workflow (5-Minute Academy) থেকে:**

```txt
✅ গ্রহণ করা হলো:
   - Keyword → fixed template map: ঘন ঘন জিজ্ঞাসিত প্রশ্নের জন্য deterministic
     টেমপ্লেট (LLM generation ছাড়াই) — দ্রুত ও সস্তা। আমাদের ক্ষেত্রে
     CompanyFaq-এর exact/fuzzy match হিট হলে LLM কলই লাগবে না।
   - Order flow-এর 3-tool sequence (Order Received → Number Collections →
     Confirmation) — আমাদের create_order_link tool একই ভূমিকা পালন করে,
     কিন্তু ডেটা সরাসরি Order/Lead টেবিলে যায় (Google Sheets/Supabase নয়)।
   - "PRICE RULES (NON-NEGOTIABLE)" ব্লক হুবহু গ্রহণযোগ্য নীতি — আমাদের
     "১৩.৪ Confidence ও Grounding গার্ডরেইল"-এ একই স্পিরিট কোডেই এনফোর্স করা।
   - Telegram-এ escalate করার প্যাটার্ন — আমরা Telegram-এর বদলে Filament
     notification + Inbox badge ব্যবহার করব (স্ট্যাক-নেটিভ, টিম যেখানে
     এমনিতেই কাজ করে)।
   - Audio message → Whisper transcribe → টেক্সট হিসেবে agent-কে দেওয়া:
     ভবিষ্যৎ ফেজে যোগ করা যায় (১৩.৯-এ multimodal note দেখুন) — প্রথম
     ভার্সনে স্কোপের বাইরে রাখা হয়েছে, কিন্তু আর্কিটেকচার এটা ব্লক করে না।

❌ গ্রহণ করা হয়নি (ইচ্ছাকৃতভাবে):
   - "SALES MINDSET" ব্লক (Minimum 3 enrollment attempt, aggressive follow-up
     SMS, "পরে করব" বললে urgency চাপ) — এটা এক-প্রোডাক্ট কোর্স-বিক্রির জন্য
     ঠিক আছে, কিন্তু multi-tenant ERP-তে প্রতিটা company-র নিজস্ব ব্র্যান্ড
     টোন থাকা উচিত। তাই এটা company-configurable "response tone" সেটিংয়ে
     ঐচ্ছিক টগল হিসেবে রাখা হবে, ডিফল্ট off।
   - Single business-এ hardcoded প্রোডাক্ট/দাম system prompt-এ — আমাদের প্রম্পট
     সবসময় dynamic tool-call দিয়ে তৈরি হয় (কোনো কোম্পানির প্রোডাক্ট তালিকা
     প্রম্পটে হার্ডকোড হবে না, CLAUDE.md নিয়ম অনুযায়ীও)।
```

**১৩.৭.২ — Messenger workflow ("Foodik"/HomeFood) থেকে:**

```txt
✅ গ্রহণ করা হলো:
   - "Rule 1: NEVER Echo User Information" — উপরে ১৩.৪-এ "Never Echo" নিয়ম
     হিসেবে সরাসরি গৃহীত। এটাই সবচেয়ে গুরুত্বপূর্ণ single guardrail।
   - "No Internal Source Mentions" — AI কখনো "database/tool/sheet-এ আছে"
     বলবে না, স্বাভাবিক ভাষায় উত্তর দেবে — আমাদের system prompt-এও যুক্ত হবে।
   - "Mandatory Search Protocol" (কোনো tool NULL রিটার্ন করলেই সাথে সাথে
     "তথ্য নেই" না বলে পরের প্রাসঙ্গিক tool চেক করা) — lookup_product miss
     করলে lookup_faq চেষ্টা করার মতো sequential fallback আমাদের
     AiReplyService-এও থাকবে।
   - Step-based order flow (product lookup → ID disambiguation → order →
     quantity update → confirmation) — আমাদের create_order_link tool-এর
     আগে "একাধিক প্রোডাক্ট মিলে গেলে কাস্টমারকে specify করতে বলা" ধাপ যোগ হবে।
   - কথোপকথন-ভিত্তিক টোন গাইডলাইন (২-৪ বাক্য, ইমোজি স্বাভাবিক ব্যবহার,
     আক্রমণাত্মক না হওয়া) — company settings-এ ঐচ্ছিক "brand voice" প্রম্পট
     ইনজেকশন হিসেবে থাকবে (company তাদের নিজের টোন লিখে দিতে পারবে)।

❌ গ্রহণ করা হয়নি:
   - Vector store (Pinecone) + embeddings + reranker RAG স্তর — বড় স্কেলে
     (শত শত প্রোডাক্ট/FAQ) দরকার হতে পারে, কিন্তু প্রথম ভার্সনে ওভার-ইঞ্জিনিয়ারিং।
     আমাদের ছোট FAQ + সরাসরি DB lookup tool যথেষ্ট; company-র ডেটা বড় হলে
     পরের ফেজে Scout/Meilisearch-ভিত্তিক সার্চে upgrade করা যাবে।
   - Redis chat memory আলাদা সার্ভিস হিসেবে — আমাদের conversation history
     ইতিমধ্যে `conversation_messages` টেবিলেই আছে, প্রতিটা AI কলে শেষ N
     মেসেজ DB থেকে fetch করে context বানানো হবে (আলাদা memory store লাগবে না)।
   - একই প্রম্পট একাধিক agent node-এ ডুপ্লিকেট করে LLM provider fallback
     (OpenAI + Groq প্যারালাল) — আমরা একটাই AiReplyService রাখব, provider
     সুইচ করা যাবে config দিয়ে (retry-on-failure দিয়ে resilience, ডুপ্লিকেট
     prompt maintenance এড়াতে)।
```

### ১৩.৯ সীমাবদ্ধতা (স্বীকৃত)

```txt
- Scope ইচ্ছাকৃতভাবে সংকীর্ণ (প্রোডাক্ট/দাম/স্টক/FAQ) — ফ্রি-ফর্ম চ্যাটবট নয়।
- LLM API লেটেন্সি (~১-৩ সে.) — reply-এর আগে WhatsApp mark_as_read/typing
  indicator পাঠানো হবে।
- ছবি/ভয়েস মেসেজ থেকে প্রশ্ন বোঝা (multimodal) — প্রথম ভার্সনে শুধু টেক্সট।
```

---

## ধাপ ১৪: AI সেকশনের টেস্ট

```txt
AiAutoReplyTest:
[ ] product_query intent-এ সঠিক প্রোডাক্ট ডেটা দিয়ে grounded reply পাঠায়
[ ] complaint/pricing_negotiation intent-এ কখনো AI reply পাঠায় না, সরাসরি handoff
[ ] confidence থ্রেশহোল্ডের নিচে হলে reply ব্লক হয়, conversation pending হয়
[ ] answer-এর টাকার অংক প্রকৃত selling_price-এর সাথে না মিললে reply ব্লক হয়
[ ] entry_point='ctwa_ad' conversation-এ ৭২ ঘণ্টা পর্যন্ত withinReplyWindow() true থাকে,
    সাধারণ conversation-এ ২৪ ঘণ্টা পর false হয়
[ ] human_handled_until সেট থাকলে AiAutoReplyJob স্কিপ হয়
[ ] AI রিপ্লাই generated_by='ai' সহ সেভ হয়, human রিপ্লাই 'human' সহ
[ ] কাস্টমার মেসেজে ভুয়া দাম দাবি ("আপনি বলেছিলেন ৫০০ টাকা") থাকলেও AI reply-তে
    সেই ভুয়া দাম আসে না — শুধু lookup_product-এর প্রকৃত মান ব্যবহার হয় (Never Echo)
```

সব টেস্ট প্লেইন `php artisan test`; LLM API কল সবসময় mocked/faked, কখনো লাইভ API-তে টেস্ট চলবে না।

---

## ধাপ ১৫: Sales Agent Persona — কাস্টমারকে "চেনা" কনটেক্সট দিয়ে কথা বলা

> **কোডবেজ-যাচাই নোট:** এই ধাপে ব্যবহৃত হবে বিদ্যমান `Customer`, `Order`, `OrderItem`, `CustomerRiskProfile`/`CustomerBlacklist` (Customer Success module, ইতিমধ্যে সম্পন্ন) — নতুন কোনো ডুপ্লিকেট ডেটা-মডেল নয়, শুধু tool দিয়ে read-access।

### ১৫.১ নীতি

AI শুধু প্রশ্নের উত্তর দেবে না — প্রতিটা রিপ্লাইয়ের আগে কাস্টমারকে "চিনে নেবে", যাতে কথোপকথনটা জেনেরিক bot-reply-এর মতো না লেগে ব্যক্তিগত সাপোর্ট এজেন্টের মতো লাগে (আগের অর্ডার/পছন্দের রেফারেন্স, নাম ধরে সম্বোধন)। এটা ধাপ ১৩-এর "transparency identifier" নিয়ম (এজেন্ট নিজেকে AI/অ্যাসিস্ট্যান্ট হিসেবে পরিচয় দেয়) **বাতিল করে না** — দুটো একসাথে থাকবে: এজেন্ট সৎভাবে জানাবে সে অ্যাসিস্ট্যান্ট, কিন্তু কথা বলবে কাস্টমারের প্রকৃত ইতিহাস জেনে, generic script পড়ার মতো নয়।

### ১৫.২ নতুন tool: `get_customer_context`

> **কোডবেজ-যাচাই নোট:** বাস্তবে ইমপ্লিমেন্ট হওয়া `AiReplyService::toolDefinitions()`-এ ইতিমধ্যে `lookup_product`, `lookup_faq`, `lookup_delivery_charge`, `create_order_link`, `escalate_to_human`, `submit_reply` — এই ৬টা tool রেজিস্টার্ড আছে (`executeTool()` মেথডে dispatch হয়)। নিচের `get_customer_context` এই একই রেজিস্ট্রিতে সপ্তম tool হিসেবে যুক্ত হবে, একই প্যাটার্নে (`toolDefinitions()` অ্যারেতে entry + `executeTool()`-এ case + প্রাইভেট মেথড)।

```php
// toolDefinitions()-এ যোগ:
[
    'name' => 'get_customer_context',
    'description' => 'Look up this conversation\'s customer profile and recent order history. Call this once at the start of every reply, before answering anything.',
    'input_schema' => ['type' => 'object', 'properties' => new \stdClass],
],
```

`AiReplyService::runAgentLoop()`-এ প্রম্পট নির্দেশনা যোগ হবে: "প্রথম tool call সবসময় `get_customer_context` হতে হবে" (Foodik রেফারেন্সের "Mandatory Search Protocol"-এর মতো বাধ্যতামূলক প্রথম ধাপ, ১৩.৭.২ দেখুন)।

```txt
Input: conversation.contact_phone (WhatsApp) বা conversation.customer_id (আগে থেকে linked থাকলে)
Lookup (company-scoped, বিদ্যমান মডেল থেকে):
  - Customer::where('company_id', $companyId)->where('phone', $phone)->first()
    → না পেলে "new_prospect" হিসেবে চিহ্নিত, কোনো ভুয়া ডেটা বানানো হবে না
  - পেলে: শেষ ৩টা Order (status, items সংক্ষেপে, order_date) — Order::with('items.product')
    ->where('customer_id', $customer->id)->latest('order_date')->limit(3)->get()
  - CustomerRiskProfile/CustomerBlacklist চেক (বিদ্যমান Customer Success module) —
    blacklisted বা high-risk হলে flag করে রিটার্ন, AI-কে জানানো হয় কিন্তু AI এই
    তথ্য কাস্টমারকে কখনো সরাসরি বলবে না
Output (LLM context-এ যোগ হয়, প্রম্পটে শুধু প্রয়োজনীয় অংশ, পুরো DB row নয়):
  { known: bool, name, order_count, last_order_summary, risk_flag: bool }
```

### ১৫.৩ ব্যবহারের নিয়ম (গার্ডরেইল)

```txt
- risk_flag = true হলে AI স্বয়ংক্রিয়ভাবে বেশি সহায়ক/ছাড় দেওয়ার চেষ্টা করবে না —
  বরং normal-flow-এ থেকে confidence থ্রেশহোল্ড কমিয়ে handoff-প্রবণ রাখা হবে
  (blacklisted কাস্টমারকে AI একা ডিল করবে না, ধাপ ১৩.৫)।
- PII discipline: AI কখনো কাস্টমারের পুরো ঠিকানা/ফোন/ব্যালেন্স চ্যাটে ডাম্প করে
  দেখাবে না — শুধু natural confirmation-এর জন্য ব্যবহার করবে
  ("আপনার আগের ডেলিভারি ঠিকানাতেই পাঠাবো?" — পুরো ঠিকানা না লিখে)।
- new_prospect (কোনো Customer রেকর্ড নেই) হলে AI স্বাভাবিক নতুন-কাস্টমার টোনে
  কথা বলবে, existing history থাকার ভান করবে না।
- এই context অন্য কোনো company-র কাস্টমার ডেটার সাথে কখনো মিশবে না — tool-টা
  সবসময় conversation.company_id দিয়ে scoped (BelongsToCompany/CompanyScope
  স্বয়ংক্রিয়ভাবে এটা নিশ্চিত করে, তবু tool কোডে explicit company_id where
  clause রাখা হবে defense-in-depth হিসেবে)।
```

---

## ধাপ ১৬: ভিজ্যুয়াল অর্ডার ফর্ম + অটো স্টোরফ্রন্ট অ্যাকাউন্ট

> **স্কোপ:** কাস্টমার চ্যাটে প্রোডাক্ট/দাম/কোয়ান্টিটি নিয়ে আগেই AI-এর সাথে কথা বলে ফেলেছে (ধাপ ১৩) — তাই ফর্মে আর সেসব জিজ্ঞেস করা হবে না, শুধু ডেলিভারি ও পেমেন্ট তথ্য নেওয়া হবে, prefilled অবস্থায়।

### ১৬.১ প্ল্যাটফর্ম-ভেদে ভিজ্যুয়াল ফর্ম কৌশল

Meta দুই প্ল্যাটফর্মে দুই রকম মেকানিজম সাপোর্ট করে — একটাই কোডে জোর করে গুঁজে দেওয়া ঠিক হবে না:

```txt
WhatsApp → WhatsApp Flows (Meta-নেটিভ মাল্টি-স্ক্রিন ইন-চ্যাট ফর্ম, Meta Business
  Manager-এ Flow JSON রেজিস্টার করতে হয়, নিজস্ব public endpoint + RSA
  encryption handshake লাগে — বাস্তব সেটআপ খরচ আছে, Meta business verification
  লাগবে)।
Messenger → নেটিভ কোনো "Flow" নেই; সমতুল্য অভিজ্ঞতা Messenger Webview
  (persistent webview button, in-chat browser) — এটাই বাস্তবে "ভিজ্যুয়াল ফর্ম"
  Messenger-এ সম্ভব, এবং এটা ধাপ ১১-এর বিদ্যমান ChatOrderLink পেজই ব্যবহার করে।

⚠️ ইমপ্লিমেন্টেশন কৌশল (ঝুঁকি কমাতে):
  MVP-তে দুই প্ল্যাটফর্মেই ChatOrderLink webview (বিদ্যমান, ধাপ ১১) ব্যবহার হবে —
  prefilled মোবাইল-ফার্স্ট পেজ, Meta approval/encryption নির্ভরতা ছাড়াই লঞ্চ করা যায়।
  WhatsApp Flow (নেটিভ, আরও মসৃণ UX) পরের ফেজে যোগ হবে যখন Meta Business
  verification সম্পন্ন হবে — আর্কিটেকচার এমনভাবে করা হবে যাতে flow_token→
  ChatOrderLink ম্যাপিং করে একই অর্ডার-ক্রিয়েশন কোড পুনরায় ব্যবহার হয়
  (নিচে ১৬.৩), দুটো আলাদা অর্ডার-ক্রিয়েশন পাথ লেখা হবে না।
```

### ১৬.২ WhatsApp Flow ডেটা ম্যাপিং (v2, Meta verification-এর পর)

```php
// migration: chat_order_links টেবিলে যোগ
Schema::table('chat_order_links', function (Blueprint $table) {
    $table->string('whatsapp_flow_token')->nullable()->unique()->after('token');
});
```

Flow-এর screen prefill payload-এ `chat_order_links.prefill` (items, name, phone) সরাসরি পাঠানো হয়; Flow submit হলে Meta আমাদের এন্ডপয়েন্টে encrypted response পাঠায় (address, area, payment_method) — ডিক্রিপ্ট করে সেই একই `ChatOrderLink` রেকর্ডে merge করে অর্ডার তৈরি হয় (নিচের ১৬.৩ পদ্ধতিতে, ওয়েবভিউ সাবমিশনের মতোই)।

### ১৬.৩ অর্ডার তৈরির ধাপ — বাগ-প্রুফ সিকোয়েন্স

> **কোডবেজ-যাচাই নোট:** `ChatOrderController::store()` ও এর `resolveCustomer()` হেল্পার ইতিমধ্যে বাস্তবে আছে (ধাপ ১১-এর অংশ হিসেবে ইমপ্লিমেন্ট হয়ে গেছে) — বর্তমান কোড `$link->isUsable()` চেক করে (ভেতরে সম্ভবত `converted_order_id` ইতিমধ্যে সেট কিনা দেখে idempotency দেয়), কিন্তু `resolveCustomer()`-এ Customer lookup-এ **কোনো `lockForUpdate()` নেই** — অর্থাৎ একই ফোন নম্বরে milliseconds ব্যবধানে দুটো সাবমিট এলে (ডাবল-ট্যাপ, বা Meta-র webhook retry থেকে দ্বিতীয়বার ফর্ম সাবমিট হলে) দুটো আলাদা `Customer` row তৈরি হয়ে যাওয়ার বাস্তব রেস-কন্ডিশন ঝুঁকি আছে। এই ধাপে পাসওয়ার্ড/অ্যাকাউন্ট যোগ করার সাথে সাথে এই বিদ্যমান gap-টাও ফিক্স করা আবশ্যক, নাহলে "একজন কাস্টমারের দুটো পাসওয়ার্ড" জাতীয় বাগ তৈরি হবে।

`resolveCustomer()`-এ নিচের সংশোধন করে, প্রতিটা ধাপ একই `DB::transaction()`-এর ভেতরে:

```txt
১. Customer lookup company-scoped phone দিয়ে, lockForUpdate() যোগ করে:
   Customer::where('company_id', $link->company_id)
       ->where('phone', $data['phone'])->lockForUpdate()->first();
   (MySQL/Postgres-এ effective; SQLite টেস্টে no-op কিন্তু নিরাপদ — বিদ্যমান
   GeneratesSequentialNumber trait-এর মতোই এখানে UNIQUE constraint
   (customers.company_id+phone, ধাপ ১৬.৪) ব্যাকআপ সেফগার্ড হিসেবে থাকবে)।

২. একটা bool ফ্ল্যাগ রাখুন: $isNewAccount = false; $plainPassword = null;

৩. Customer না থাকলে: নতুন Customer তৈরি (বিদ্যমান কোড অপরিবর্তিত) +
   $plainPassword = Str::password(10); // নিরাপদ random
   $customer->password = $plainPassword; // 'hashed' cast অটো Hash::make করবে
   $isNewAccount = true;

৪. Customer থাকে কিন্তু password column null (পুরনো কাস্টমার, ধাপ ১৬.৪-এর
   migration-এর পর এটাই ডিফল্ট অবস্থা):
   - একইভাবে $plainPassword তৈরি ও সেভ, $isNewAccount = true

৫. Customer-এর password ইতিমধ্যে সেট থাকলে: কিছুই regenerate হবে না,
   $isNewAccount = false, $plainPassword = null।
   ⚠️ এটাই মূল বাগ-প্রতিরোধ: দ্বিতীয়/তৃতীয় অর্ডারে পাসওয়ার্ড কখনো
   ওভাররাইট হবে না, বারবার প্লেইনটেক্সট পাঠানো হবে না।

৬. `resolveCustomer()` থেকে `[$customer, $isNewAccount, $plainPassword]`
   রিটার্ন করুন — `store()` মেথডে ব্যবহারের জন্য (বর্তমানে শুধু `$customer`
   রিটার্ন করে, সিগনেচার বদলাতে হবে)।

৭. Order/OrderItem তৈরি — বর্তমান `store()` কোড অপরিবর্তিত (ইতিমধ্যে সঠিক:
   `Order::SOURCE_CHAT`, company-scoped OrderItem)।

৮. ডেলিভারি সময়সীমা — কোনো হার্ডকোড ("৩-৫ দিন") নয়; বিদ্যমান শিপিং/এরিয়া
   সেটিংস থেকে company-configurable estimate (delivery_area অনুযায়ী
   StorefrontSetting-এর "estimated_delivery_days" ফিল্ড — না থাকলে নতুন
   migration দিয়ে যোগ করুন, admin থেকে এডিটযোগ্য)।

৯. ChatOrderLink.converted_order_id = $order->id (লক)।

১০. কনফার্মেশন মেসেজ কম্পোজ (নিচে ১৬.৫), ConversationMessengerService দিয়ে ওই
    একই conversation-এ পাঠানো — provider (WhatsApp/Messenger) conversation
    থেকেই resolve হয়, ভুল চ্যানেলে যাওয়ার সুযোগ নেই।

১১. মেসেজ পাঠানোর পর $plainPassword সাথে সাথে unset() — variable scope-এর
    বাইরে গেলেও যেন কোথাও লগ/এক্সেপশন ট্রেসে না থেকে যায় (নিচে ১৬.৬ দেখুন)।
```

### ১৬.৪ Customer Authentication — নতুন গার্ড

বিদ্যমান কোডবেজে **কোনো কাস্টমার লগইন সিস্টেম নেই** (`AccountOrdersController` phone-match দিয়ে চলে, session auth নয়) — এটা সম্পূর্ণ নতুন সংযোজন।

**Migration:**

```php
Schema::table('customers', function (Blueprint $table) {
    $table->string('password')->nullable()->after('email');
    $table->rememberToken()->after('password');
});
```

`customers` টেবিলে বর্তমানে `phone`/`email`-এ ইউনিক কনস্ট্রেইন্ট নেই (শুধু index) — লগইনের জন্য এটা company-স্কোপড ইউনিক হতে হবে, নাহলে একই ফোন দিয়ে দুইটা Customer row তৈরি হয়ে গেলে login অস্পষ্ট হবে:

```php
Schema::table('customers', function (Blueprint $table) {
    $table->unique(['company_id', 'phone']); // বিদ্যমান ডেটায় duplicate phone
    // থাকলে migration-এর আগে সেগুলো ম্যানুয়ালি merge/clean করতে হবে —
    // deploy-এর আগে dry-run migration চালিয়ে duplicate চেক করা বাধ্যতামূলক
});
```

**Model (`Customer.php` এডিট):**

```php
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Auth\Authenticatable as AuthenticatableTrait;

class Customer extends Model implements Authenticatable
{
    use AuthenticatableTrait, BelongsToCompany;

    protected $hidden = ['password', 'remember_token']; // Filament/API সিরিয়ালাইজেশনে যেন কখনো hash লিক না হয়

    protected $casts = [
        // বিদ্যমান casts + :
        'password' => 'hashed', // Laravel অটো-hash করবে, ডাবল-হ্যাশিং বাগ এড়াতে
        //   এই cast থাকলে LeadConversionService/চ্যাট-অর্ডার কোডে সরাসরি
        //   plaintext password অ্যাসাইন করলেই চলবে, Hash::make() আলাদা করে
        //   লাগবে না — ১৬.৩-এর ধাপ ৪/৫ এই cast-এর উপর নির্ভর করে লেখা।
    ];
}
```

**`config/auth.php`:**

```php
'guards' => [
    'web' => ['driver' => 'session', 'provider' => 'users'],       // বিদ্যমান, admin/staff
    'customer' => ['driver' => 'session', 'provider' => 'customers'], // নতুন
],

'providers' => [
    'users' => ['driver' => 'eloquent', 'model' => App\Models\User::class],
    'customers' => ['driver' => 'eloquent', 'model' => App\Models\Customer::class], // নতুন
],
```

Laravel-এর session guard নামভেদে আলাদা session key ব্যবহার করে — তাই admin panel (`web` guard) আর storefront customer login (`customer` guard) একই ব্রাউজারে একসাথে সমস্যা ছাড়াই কাজ করবে (একজন owner নিজের স্টোরফ্রন্টে customer হিসেবেও টেস্ট-লগইন করতে পারবেন, admin session না হারিয়ে)।

**Multi-company স্কোপিং (গুরুত্বপূর্ণ, ভুল হলে সিকিউরিটি বাগ):** Login attempt করার সময় শুধু phone+password নয়, বর্তমান storefront domain-এর `company_id`-ও credential-এর অংশ হতে হবে, নাহলে একই ফোন নম্বর দিয়ে অন্য কোম্পানির Customer row-এ ভুলবশত লগইন হয়ে যাওয়ার ঝুঁকি থাকে:

```php
Auth::guard('customer')->attempt([
    'phone' => $request->phone,
    'password' => $request->password,
    'company_id' => $currentCompany->id, // domain থেকে resolve করা company
]);
```

### ১৬.৫ কনফার্মেশন মেসেজ (টেমপ্লেট)

```txt
✅ আপনার অর্ডার কনফার্ম হয়েছে!

অর্ডার আইডি: {order_number}
প্রোডাক্ট: {item summary — নাম × কোয়ান্টিটি}
মোট: ৳{total_amount}
আনুমানিক ডেলিভারি: {estimated_delivery_days অনুযায়ী তারিখ}

{{ যদি $isNewAccount: }}
আপনার জন্য একটা স্টোরফ্রন্ট অ্যাকাউন্ট তৈরি করা হয়েছে যেখানে অর্ডার হিস্টরি ও
প্রোফাইল দেখতে পারবেন:
🔗 {login_url}
ইউজার: {phone}
পাসওয়ার্ড: {plainPassword}
(নিরাপত্তার জন্য লগইন করেই প্রোফাইল সেটিংস থেকে পাসওয়ার্ড পরিবর্তন করে নিন)

{{ else: }}
আপনার আগের অ্যাকাউন্টেই লগইন করে অর্ডারটি ট্র্যাক করতে পারবেন:
🔗 {login_url}
```

### ১৬.৬ পাসওয়ার্ড হ্যান্ডলিং — নিরাপত্তা সীমাবদ্ধতা (স্বীকৃত, ব্যবহারকারীর নির্দেশ অনুযায়ী)

```txt
⚠️ WhatsApp/Messenger চ্যাটে পাঠানো পাসওয়ার্ড কাস্টমারের চ্যাট হিস্টরি/ব্যাকআপে
   স্থায়ীভাবে থেকে যায় — এটা এই ডিজাইনের একটা inherent trade-off, ব্যবহারকারীর
   স্পষ্ট নির্দেশ অনুযায়ী implement করা হচ্ছে। ঝুঁকি কমাতে বাধ্যতামূলক:

   - প্লেইনটেক্সট পাসওয়ার্ড আমাদের নিজস্ব DB-তে **কখনো সংরক্ষণ হবে না** —
     শুধু outgoing মেসেজ পাঠানোর জন্য request payload-এ যাবে; সেই মেসেজ
     ConversationMessage-এ সেভ করার সময় body-তে পাসওয়ার্ড আসল ভ্যালুর বদলে
     "••••••••" দিয়ে রিপ্লেস করে সেভ হবে (কাস্টমারের চ্যাটে আসল ভ্যালু যাবে,
     কিন্তু আমাদের অডিট DB-তে না)।
   - Exception/log handler-এ কখনো request payload পুরোপুরি ডাম্প করা যাবে না
     (Laravel-এর `$dontFlash`/log context sanitization এই জব-এর জন্য যোগ করুন)।
   - শুধু প্রথমবার (নতুন অ্যাকাউন্ট তৈরির সময়) পাসওয়ার্ড পাঠানো হয় — পরের
     কোনো অর্ডারে reused/resent হবে না (ধাপ ১৬.৩-এর ধাপ ৬)।
   - Account settings-এ কাস্টমার নিজে পাসওয়ার্ড বদলাতে পারবে (১৬.৭)।
   - **সুপারিশ (ঐচ্ছিক, ভবিষ্যতে):** প্লেইন পাসওয়ার্ডের বদলে এক-ব্যবহারযোগ্য
     "ম্যাজিক লগইন লিংক" (সাইনড URL, ২৪ ঘণ্টা মেয়াদ) পাঠানো আরও নিরাপদ —
     ব্যবহারকারী চাইলে ১৬.x ফেজে এটাতে সুইচ করা যাবে, আর্কিটেকচার তাতে বাধা দেয় না।
```

### ১৬.৭ স্টোরফ্রন্ট Account Settings — পাসওয়ার্ড পরিবর্তন

নতুন রুট (middleware: `auth:customer`, বিদ্যমান `AccountOrdersController`-এর পাশে):

```php
Route::middleware('auth:customer')->group(function () {
    Route::get('/account/settings', [StorefrontAccountSettingsController::class, 'edit'])
        ->name('storefront.account.settings');
    Route::put('/account/settings/password', [StorefrontAccountSettingsController::class, 'updatePassword'])
        ->name('storefront.account.password.update');
});
```

```txt
- ফর্ম: current_password (Laravel-এর built-in current_password rule দিয়ে
  যাচাই), new_password (confirmed + Password::defaults() rule)
- সফল হলে flash success message, নতুন password ইমিডিয়েটলি কার্যকর
- বিদ্যমান AccountOrdersController-এর phone-match ভিত্তিক পুরনো ফ্লো
  (guest/non-logged-in ব্যবহারকারীর জন্য) অপরিবর্তিত থাকবে — লগইন করা
  কাস্টমার দেখলে Auth::guard('customer')->user()->id দিয়ে সরাসরি query,
  ওটাই বেশি নির্ভরযোগ্য এবং phone-format-mismatch বাগ এড়ায়।
- "Forgot password" (ভবিষ্যৎ ছোট সংযোজন, launch-blocker নয়): OTP বা
  WhatsApp-এ আবার নতুন পাসওয়ার্ড পাঠানোর অপশন — না থাকলে কাস্টমার ফোন
  হারালে/পাসওয়ার্ড ভুলে গেলে স্থায়ীভাবে লক আউট হয়ে যাবে, তাই এটা v1-এর
  পরপরই করা উচিত, প্ল্যানে স্পষ্ট চিহ্নিত করা থাকল যেন বাদ না পড়ে।
```

---

## ধাপ ১৭: ধাপ ১৫–১৬-এর টেস্ট

```txt
CustomerContextToolTest:
[ ] get_customer_context বিদ্যমান কাস্টমারের জন্য সঠিক last-3-orders রিটার্ন করে
[ ] অন্য company-র customer ডেটা কখনো leak করে না (company-scoped query)
[ ] blacklisted/risk-flagged কাস্টমারে risk_flag=true রিটার্ন হয়, AI response-এ
    সেই flag কাস্টমারের কাছে raw ভাবে exposed হয় না

ChatOrderAccountTest:
[ ] নতুন ফোন নম্বরে প্রথম চ্যাট-অর্ডারে Customer + password তৈরি হয়, confirmation
    মেসেজে plaintext password থাকে
[ ] একই কাস্টমারের দ্বিতীয় চ্যাট-অর্ডারে password অপরিবর্তিত থাকে, দ্বিতীয়
    মেসেজে password পাঠানো হয় না
[ ] ডাবল-সাবমিট (একই ChatOrderLink টোকেন দুইবার) একটাই Order তৈরি করে
    (lockForUpdate + converted_order_id চেক)
[ ] ConversationMessage-এ সেভ হওয়া confirmation body-তে আসল password নেই,
    "••••••••" মাস্ক করা আছে
[ ] company_id ছাড়া বা ভুল company_id দিয়ে login attempt ব্যর্থ হয় (cross-company
    login block)
[ ] MultiCompanyIsolationTest-এ Customer-এর password/guard-সংক্রান্ত কোনো নতুন
    কলাম isolation ভাঙে না তা কনফার্ম

StorefrontAccountSettingsTest:
[ ] সঠিক current_password দিলে password আপডেট হয়, ভুল দিলে reject
[ ] auth:customer middleware ছাড়া settings পেজ 302 redirect করে লগইনে
```

সব টেস্ট প্লেইন `php artisan test`; duplicate-phone migration চালানোর আগে স্টেজিং/ডেমো ডেটায় dry-run করে দেখা বাধ্যতামূলক (CLAUDE.md-এর demo-data-নিরাপত্তা নিয়ম অনুযায়ী), production migrate করার আগে owner approval লাগবে।