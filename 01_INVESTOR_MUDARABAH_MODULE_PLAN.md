# Investor Module (Mudarabah Profit-Sharing) — বিস্তারিত স্টেপ-বাই-স্টেপ ইমপ্লিমেন্টেশন প্ল্যান

> **v2 আপডেট উৎস (২০২৬-০৭):** এই ভার্সন ব্যবহারকারীর দেওয়া তিনটা বাস্তব ডকুমেন্ট থেকে সংশোধিত — (১) একটা বাস্তব project-এর profit-sharing settlement sheet, (২) channel partner (Mohaimin Patwari)-র সাথে চুক্তির DOCX, (৩) একজন investor-এর সাথে স্বাক্ষরিত physical contract-এর স্ক্যান কপি। এই ডকুমেন্টগুলো থেকে profit-split percentage সংশোধন, itemized cost structure, security cheque/guarantor tracking, এবং legal contract field যুক্ত হয়েছে।

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
❌ Lead/CRM Module            — এখনো শুরু হয়নি (এই Investor module-এর ঠিক আগে করতে হবে,
                                দেখুন নিচের পূর্বশর্ত)
❌ Investor/Mudarabah Module  — এই ফাইলের বিষয়, এখনো শুরু হয়নি
```

## ⚠️ পূর্বশর্ত (এই কাজ শুরু করার আগে যাচাই করুন)

```txt
[✅] Multi-Company system সম্পূর্ণ এবং company isolation test পাস করেছে — কনফার্ম
[ ] Account/Ledger module স্থিতিশীল এবং production-এ চলছে — verify করুন
[ ] Lead/CRM module (02_LEAD_CRM_MODULE_PLAN.md) সম্পন্ন এবং স্থিতিশীল —
    এই Investor module Lead/CRM-এর ঠিক পরে আসে, কারণ Investor-দের দেখানোর
    জন্য বাস্তব revenue/profit ডেটা দরকার যা Lead→Order flow থেকে আসে
[ ] ব্যবহারকারী নিশ্চিত করেছেন যে এখনই এই মডিউল শুরু করার সময় — এটা
    আর্থিকভাবে স্পর্শকাতর (profit-sharing settlement), তাই স্থিতিশীল
    কোডবেসে শুরু করা উচিত
```

যদি উপরের কোনো শর্ত পূরণ না হয়, ব্যবহারকারীকে জিজ্ঞেস করুন আগে এগোনো উচিত কিনা।

---

## মডিউলের উদ্দেশ্য

কোম্পানি একটা নির্দিষ্ট ডিলের (যেমন একটা মেশিন import) জন্য একটা "Project" খোলে যেখানে একাধিক investor টাকা বিনিয়োগ করেন। Project শেষে (trade cycle সম্পূর্ণ হলে — এটা ২/৬/১২ মাস বা যেকোনো নির্দিষ্ট দিনের হতে পারে, বাস্তব উদাহরণে ৬০ দিন) লাভ এই নিয়মে ভাগ হয়:

```txt
মোট মুনাফার ১০০%
├── Investor Pool:      ৪০%  (প্রতি investor তার বিনিয়োগ-অনুপাতে ভাগ পান)
├── Channel Partner:    ১০%  (যার মাধ্যমে investor এসেছেন — referral)
└── Company Net:        ৫০%
```

> **⚠️ গুরুত্বপূর্ণ সংশোধনী (real contract document থেকে confirmed, ২০২৬-০৭):** এই মডিউলের v1 ডিজাইনে ভুলভাবে **Investor 50% / Channel 10% / Company 40%** লেখা হয়েছিল (ব্যবহারকারীর একটা verbal বর্ণনা থেকে)। কিন্তু ব্যবহারকারীর দেওয়া দুটো independent বাস্তব ডকুমেন্ট — (১) Mohaimin Patwari-র সাথে channel partnership চুক্তি (DOCX): *"৪০% বিনিয়োগকারীরা পাবেন, আপনি পাবেন ৫০% এবং আমরা পাবো ১০%"*, এবং (২) একটা বাস্তব project-এর settlement sheet (PDF): *"40% to investors amount- 217823.2"* — দুটোই **Investor 40% / Company 50% / Channel 10%** নিশ্চিত করে। তাই এই সংশোধিত percentage-ই সঠিক ধরে ডিজাইন করা হয়েছে। **এজেন্টের জন্য নির্দেশ:** কোড লেখা শুরুর আগে ব্যবহারকারীর কাছে একবার নিশ্চিত করুন এটাই এখনকার নিয়ম কিনা — ভুল হলে investor payout ভুল হবে।

**নিয়ম:** কোনো সুদ/ব্যাংকিং ইন্টারেস্ট নেই — এটা সম্পূর্ণ ইসলামী শরীয়াহ-ভিত্তিক Mudarabah profit-loss sharing।

**Profit-এর সংজ্ঞা (DOCX চুক্তি থেকে, গুরুত্বপূর্ণ):** বিক্রয় মূল্য থেকে শুধু ক্রয় মূল্য ও **সরাসরি খরচ** বাদ যাবে — কোন খরচ বাদ যাবে তা investor-কে **আগেই জানাতে হবে** (transparency-এর শর্ত)। সাধারণ ব্যবসায়িক overhead (অফিস ভাড়া, বেতন ইত্যাদি) এই হিসাবে ঢুকবে না, শুধু সেই নির্দিষ্ট ডিল-সম্পর্কিত সরাসরি খরচ (import cost, local delivery, installation ইত্যাদি) ঢুকবে।

**⚠️ Percentage Configurability — Hard-coded রাখা যাবে না:** যদিও উপরের ৪০/১০/৫০ এখনকার নিয়ম, ভবিষ্যতে ভিন্ন investor/channel partner-এর সাথে ভিন্ন শতাংশে চুক্তি হতে পারে (Doc-এ channel partner personally negotiate করেছেন)। তাই এই percentage **প্রতিটা Project-এ configurable field** হিসেবে রাখতে হবে, কোডে hardcode করা `0.40`/`0.10`/`0.50` না — নিচের migration ও service design এই অনুযায়ী।

---

## ধাপ ১: Database Migration

### 1.1 `investment_projects` টেবিল

```bash
php artisan make:migration create_investment_projects_table
```

```php
Schema::create('investment_projects', function (Blueprint $table) {
    $table->id();
    $table->foreignId('company_id')->constrained()->cascadeOnDelete();
    $table->string('project_code')->unique();
        // ফরম্যাট: {sequence}/{year}-INV-{sequence} — বাস্তব উদাহরণ
        // "Project-01/2026-INV-01" অনুযায়ী। জেনারেশন লজিক নিচে মডেলে।
    $table->string('name');
    $table->text('description')->nullable();
    $table->string('deal_reference')->nullable();
        // যে নির্দিষ্ট মেশিন/চালানের জন্য প্রজেক্ট, যেমন "Shearing Machine
        // (Taiwan Made)" — Project প্রায়ই একটা নির্দিষ্ট ডিলের সাথে বাঁধা,
        // শুধু generic সময়ভিত্তিক fund pool না
    $table->foreignId('purchase_id')->nullable()->constrained()->nullOnDelete();
        // যদি এই project-এর টাকা দিয়ে একটা নির্দিষ্ট বিদ্যমান Purchase
        // রেকর্ড করা হয়, সরাসরি link — cost items সেখান থেকেই আসতে পারে
    $table->enum('duration_type', ['2_month', '6_month', '12_month', 'custom_days'])
          ->default('custom_days');
    $table->unsignedInteger('trade_cycle_days')->nullable();
        // duration_type='custom_days' হলে এই ফিল্ড ব্যবহার হবে — বাস্তব
        // উদাহরণে ৬০ দিন। Fixed month category-এর বাইরেও flexible রাখতে
    $table->date('start_date');
    $table->date('end_date')->nullable();
    $table->decimal('target_amount', 15, 2)->nullable();

    // ⚠️ Configurable split — hardcode না, প্রতিটা project-এ ভিন্ন হতে পারে
    $table->decimal('investor_share_percent', 5, 2)->default(40.00);
    $table->decimal('channel_partner_share_percent', 5, 2)->default(10.00);
    $table->decimal('company_share_percent', 5, 2)->default(50.00);
        // তিনটার যোগফল = 100 এই constraint model-level validation-এ রাখুন

    $table->enum('status', ['open', 'running', 'closed', 'settled'])->default('open');
    $table->timestamps();
});
```

**⚠️ Validation নিয়ম:** `investor_share_percent + channel_partner_share_percent + company_share_percent` অবশ্যই ঠিক ১০০.০০ হতে হবে — Filament form-এ ও model boot()-এ দুই জায়গাতেই যাচাই করুন।

### 1.2 `investors` টেবিল

```php
Schema::create('investors', function (Blueprint $table) {
    $table->id();
    $table->foreignId('company_id')->constrained()->cascadeOnDelete();
    $table->string('name');
    $table->string('guardian_name')->nullable();
        // পিতা/স্বামীর নাম — বাস্তব চুক্তিপত্রে বাধ্যতামূলক (দেখুন
        // ডকুমেন্টেড contract format)
    $table->string('phone');
    $table->string('email')->nullable();
    $table->text('address')->nullable();
    $table->string('nid_number')->nullable();
        // Contract-এ NID বাধ্যতামূলক — legal identity verification
    $table->foreignId('channel_partner_id')->nullable()
        ->constrained('investors')->nullOnDelete(); // অন্য investor-ও partner হতে পারেন
    $table->timestamps();

    $table->unique(['company_id', 'phone']);
});
```

**⚠️ Channel Partner Exclusivity — গুরুত্বপূর্ণ ব্যবসায়িক নিয়ম:** DOCX চুক্তির ধারা ১১ অনুযায়ী, একবার কোনো investor একটা channel partner-এর মাধ্যমে এলে, কোম্পানি সেই investor-এর সাথে channel partner-কে বাদ দিয়ে আলাদা চুক্তি করতে পারবে না (লঙ্ঘনে সর্বনিম্ন ১ লাখ টাকা জরিমানার শর্ত আছে)। তাই `channel_partner_id` **একবার সেট হলে UI থেকে পরিবর্তনযোগ্য রাখা উচিত না** (Filament ফর্মে শুধু super_admin-এর জন্য edit permission, এবং পরিবর্তন হলে audit log-এ বাধ্যতামূলক reason লেখা)।

### 1.3 `investments` টেবিল

```php
Schema::create('investments', function (Blueprint $table) {
    $table->id();
    $table->foreignId('project_id')->constrained('investment_projects')->cascadeOnDelete();
    $table->foreignId('investor_id')->constrained()->cascadeOnDelete();
    $table->decimal('amount', 15, 2);
    $table->enum('payment_method', ['cash', 'bkash', 'bank', 'other']);
    $table->string('payment_reference')->nullable();
    $table->date('invested_at');
    $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();
});
```

### 1.3-A `project_cost_items` টেবিল (নতুন — itemized cost breakdown)

> **প্রেক্ষাপট:** বাস্তব settlement sheet-এ costs আলাদা লাইন-আইটেমে ভাঙা থাকে (Machine Purchase, Servicing, ITS Inspection, China Local Transport, Customs, Freight, C&F CTG, তারপর আলাদাভাবে BD Local Expenses: Delivery, Installation, TT/LC+Insurance)। এটা বিদ্যমান Purchase module-এর **custom cost fields (JSON) প্যাটার্নের সাথে একই ধরনের** — সেই প্যাটার্ন অনুসরণ করুন, নতুন আলাদা ডিজাইন বানাবেন না।

```php
Schema::create('project_cost_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('project_id')->constrained('investment_projects')->cascadeOnDelete();
    $table->enum('category', ['landed_cost', 'local_expense']);
        // landed_cost = China/import-side (machine purchase, servicing,
        // inspection, transport, customs, freight, C&F)
        // local_expense = BD-side (delivery, installation, TT/LC+insurance)
        // এই দুই category-র যোগফল থেকেই net profit বের হবে (নিচে দেখুন)
    $table->string('label');   // "Machine Purchase", "ITS Inspection Charge"...
    $table->decimal('amount', 15, 2);
    $table->foreignId('purchase_id')->nullable()->constrained()->nullOnDelete();
        // বিদ্যমান Purchase রেকর্ডের সাথে link — যাতে actual invoice/receipt
        // attachment থেকে সরাসরি traceable হয় (transparency নিয়ম অনুযায়ী)
    $table->text('remarks')->nullable();
    $table->timestamps();

    $table->index(['project_id', 'category']);
});
```

**⚠️ Transparency নিয়ম (DOCX চুক্তি থেকে):** প্রতিটা cost item-এর জন্য receipt/invoice সংরক্ষিত থাকতে হবে এবং investor-কে দেখানোর জন্য উপলব্ধ থাকতে হবে — তাই `purchase_id` link এবং বিদ্যমান Purchase attachment infrastructure পুনর্ব্যবহার করাই সঠিক পথ, cost item-এ আলাদা file upload বানানোর দরকার নেই।

### 1.3-B `investor_security_instruments` টেবিল (নতুন — Cheque + Guarantor)

> **প্রেক্ষাপট:** বাস্তব চুক্তিতে প্রতিটা investment-এর নিরাপত্তা স্বরূপ investor-কে ১০০% মূলধনের সমান একটা চেক security হিসেবে দেওয়া হয় (কোম্পানির থেকে investor-এর কাছে), এবং চুক্তিতে একজন Guarantor-এর তথ্য যুক্ত থাকে। এটা একটা compliance/legal tracking প্রয়োজন, financial calculation না — কিন্তু legal risk কমানোর জন্য গুরুত্বপূর্ণ।

```php
Schema::create('investor_security_instruments', function (Blueprint $table) {
    $table->id();
    $table->foreignId('investment_id')->constrained()->cascadeOnDelete();
    $table->string('cheque_number')->nullable();
    $table->string('cheque_bank_name')->nullable();
    $table->decimal('cheque_amount', 15, 2)->nullable();  // সাধারণত investment amount-এর সমান
    $table->enum('cheque_status', ['held_by_investor', 'returned', 'cashed'])
          ->default('held_by_investor');
    $table->string('guarantor_name')->nullable();
    $table->string('guarantor_nid')->nullable();
    $table->string('guarantor_phone')->nullable();
    $table->string('contract_document_path')->nullable();
        // স্বাক্ষরিত চুক্তির স্ক্যান কপি (CamScanner-স্টাইল আপলোড) — বিদ্যমান
        // file storage pattern ব্যবহার করুন
    $table->timestamps();
});
```

### 1.4 `project_settlements` টেবিল

```php
Schema::create('project_settlements', function (Blueprint $table) {
    $table->id();
    $table->foreignId('project_id')->constrained('investment_projects')->cascadeOnDelete();
    $table->decimal('total_revenue', 15, 2);
    $table->decimal('total_cost', 15, 2);
        // project_cost_items-এর যোগফল থেকে auto-calculate হবে (নিচে
        // InvestmentProject::totalCostItems() মেথড দেখুন), ম্যানুয়ালি
        // টাইপ করা যাবে না — শুধু override করার প্রয়োজন হলে audit log সহ
    $table->decimal('net_profit', 15, 2); // total_revenue - total_cost
    $table->decimal('investor_pool_amount', 15, 2);   // net_profit * project.investor_share_percent
    $table->decimal('channel_partner_amount', 15, 2); // net_profit * project.channel_partner_share_percent
    $table->decimal('company_net_amount', 15, 2);     // net_profit * project.company_share_percent
    $table->decimal('annualized_return_percent', 5, 2)->nullable();
        // বাস্তব উদাহরণে "21.08%" — trade_cycle_days ভিত্তিক annualized
        // rate, শুধু reporting/investor-communication-এর জন্য, actual
        // payout calculation-এ ব্যবহার হয় না
    $table->enum('status', ['draft', 'confirmed', 'paid_out'])->default('draft');
    $table->foreignId('settled_by')->constrained('users');
    $table->timestamp('settled_at')->nullable();
    $table->timestamps();
});
```

**⚠️ গুরুত্বপূর্ণ নিয়ম:** `status = 'confirmed'` বা `'paid_out'` হয়ে গেলে কোনো amount ফিল্ড সরাসরি UPDATE করা যাবে না। ভুল হলে আলাদা reversal entry তৈরি করতে হবে।

**Annualized Rate ক্যালকুলেশন (reporting-only):**
```txt
annualized_return_percent = (net_profit / total_invested) × (365 / trade_cycle_days) × 100
বাস্তব উদাহরণ যাচাই: net_profit ratio প্রতি ৬০ দিনে যা হয়, তাকে ৩৬৫/৬০ দিয়ে
গুণ করলে annualized rate পাওয়া যায় — এটা শুধু investor-দের কাছে আকর্ষণীয়ভাবে
রিটার্ন communicate করার জন্য, প্রকৃত payout সবসময় project-ভিত্তিক actual
profit থেকেই হিসাব হবে।
```

### 1.5 `settlement_payouts` টেবিল

```php
Schema::create('settlement_payouts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('settlement_id')->constrained('project_settlements')->cascadeOnDelete();
    $table->foreignId('investor_id')->constrained()->cascadeOnDelete();
    $table->decimal('investment_amount', 15, 2);   // মূলধন
    $table->decimal('profit_share_amount', 15, 2);  // তার ভাগের মুনাফা
    $table->decimal('total_payout', 15, 2);         // মূলধন + মুনাফা
    $table->enum('payment_status', ['pending', 'paid'])->default('pending');
    $table->date('paid_at')->nullable();
    $table->string('payment_method')->nullable();

    // Flexible recipient — বাস্তব উদাহরণে payout investor-এর নিজের নামে
    // নাও যেতে পারে (nominee/alternate account), তাই আলাদা রাখা হয়েছে
    $table->string('recipient_name')->nullable();     // ডিফল্ট investor.name, override করা যাবে
    $table->string('recipient_bank_name')->nullable();
    $table->string('recipient_branch')->nullable();
    $table->string('recipient_account_number')->nullable();
    $table->string('payment_reference')->nullable();  // transaction ID/স্লিপ নম্বর

    $table->timestamps();
});
```

### 1.6 `channel_partner_payouts` টেবিল (১০% ভাগের জন্য আলাদা)

```php
Schema::create('channel_partner_payouts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('settlement_id')->constrained('project_settlements')->cascadeOnDelete();
    $table->foreignId('investor_id')->constrained()->cascadeOnDelete(); // channel partner হিসেবে যিনি কাজ করেছেন
    $table->decimal('amount', 15, 2);
    $table->enum('payment_status', ['pending', 'paid'])->default('pending');
    $table->date('paid_at')->nullable();
    $table->timestamps();
});
```

**টেস্ট:** মাইগ্রেশন রান করে `php artisan migrate` দিয়ে কনফার্ম করুন সব টেবিল তৈরি হয়েছে। `php artisan migrate:rollback` দিয়ে rollback-ও কাজ করে কিনা চেক করুন।

---

## ধাপ ২: Eloquent Models

### 2.1 `InvestmentProject` Model

```php
namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class InvestmentProject extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'name', 'description', 'duration_type',
        'start_date', 'end_date', 'target_amount', 'status',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'target_amount' => 'decimal:2',
    ];

    public function investments()
    {
        return $this->hasMany(Investment::class, 'project_id');
    }

    public function costItems()
    {
        return $this->hasMany(ProjectCostItem::class, 'project_id');
    }

    public function settlement()
    {
        return $this->hasOne(ProjectSettlement::class, 'project_id');
    }

    // landed_cost + local_expense category-র সব cost item-এর যোগফল
    public function totalCostItems(): float
    {
        return (float) $this->costItems()->sum('amount');
    }

    public function totalLandedCost(): float
    {
        return (float) $this->costItems()->where('category', 'landed_cost')->sum('amount');
    }

    public function totalLocalExpense(): float
    {
        return (float) $this->costItems()->where('category', 'local_expense')->sum('amount');
    }

    // মোট সংগৃহীত বিনিয়োগ
    public function totalInvested(): float
    {
        return (float) $this->investments()->sum('amount');
    }

    // প্রতি investor-এর বিনিয়োগ অনুপাত (settlement calculation-এ ব্যবহৃত হবে)
    public function investmentRatioFor(Investor $investor): float
    {
        $total = $this->totalInvested();
        if ($total <= 0) {
            return 0;
        }

        $investorAmount = $this->investments()
            ->where('investor_id', $investor->id)
            ->sum('amount');

        return $investorAmount / $total;
    }

    protected static function boot(): void
    {
        parent::boot();

        static::saving(function (self $project): void {
            $sum = $project->investor_share_percent
                + $project->channel_partner_share_percent
                + $project->company_share_percent;

            if (abs($sum - 100.00) > 0.01) {
                throw new \InvalidArgumentException(
                    'investor_share_percent + channel_partner_share_percent + company_share_percent অবশ্যই ১০০ হতে হবে।'
                );
            }

            if (! $project->project_code) {
                $project->project_code = static::generateProjectCode($project->company);
            }
        });
    }

    // ফরম্যাট: {sequence}/{year}-INV-{sequence} — বাস্তব উদাহরণ
    // "Project-01/2026-INV-01" অনুযায়ী
    public static function generateProjectCode(Company $company): string
    {
        $year = now()->year;
        $sequence = static::where('company_id', $company->id)
            ->whereYear('created_at', $year)
            ->count() + 1;

        $seq = str_pad((string) $sequence, 2, '0', STR_PAD_LEFT);

        return "{$seq}/{$year}-INV-{$seq}";
    }
}
```

### 2.2 `Investor` Model

```php
namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class Investor extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'name', 'phone', 'email', 'address', 'channel_partner_id'];

    public function channelPartner()
    {
        return $this->belongsTo(Investor::class, 'channel_partner_id');
    }

    public function referredInvestors()
    {
        return $this->hasMany(Investor::class, 'channel_partner_id');
    }

    public function investments()
    {
        return $this->hasMany(Investment::class);
    }

    public function totalInvestedLifetime(): float
    {
        return (float) $this->investments()->sum('amount');
    }
}
```

### 2.3 `Investment` Model

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Investment extends Model
{
    protected $fillable = [
        'project_id', 'investor_id', 'amount',
        'payment_method', 'payment_reference', 'invested_at', 'received_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'invested_at' => 'date',
    ];

    public function project()
    {
        return $this->belongsTo(InvestmentProject::class, 'project_id');
    }

    public function investor()
    {
        return $this->belongsTo(Investor::class);
    }
}
```

### 2.4 `ProjectSettlement` Model + Settlement Calculation Service

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectSettlement extends Model
{
    protected $fillable = [
        'project_id', 'total_revenue', 'total_cost', 'net_profit',
        'investor_pool_amount', 'channel_partner_amount', 'company_net_amount',
        'status', 'settled_by', 'settled_at',
    ];

    protected $casts = [
        'total_revenue' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'net_profit' => 'decimal:2',
        'investor_pool_amount' => 'decimal:2',
        'channel_partner_amount' => 'decimal:2',
        'company_net_amount' => 'decimal:2',
        'settled_at' => 'datetime',
    ];

    public function project()
    {
        return $this->belongsTo(InvestmentProject::class, 'project_id');
    }

    public function payouts()
    {
        return $this->hasMany(SettlementPayout::class, 'settlement_id');
    }

    public function channelPartnerPayouts()
    {
        return $this->hasMany(ChannelPartnerPayout::class, 'settlement_id');
    }
}
```

### 2.5 `SettlementService` — মূল ক্যালকুলেশন লজিক (সবচেয়ে গুরুত্বপূর্ণ ফাইল)

```php
namespace App\Services\Investment;

use App\Models\InvestmentProject;
use App\Models\ProjectSettlement;
use App\Models\SettlementPayout;
use App\Models\ChannelPartnerPayout;
use Illuminate\Support\Facades\DB;

class SettlementService
{
    /**
     * ⚠️ total_cost এখন project_cost_items থেকে auto-calculate হয় —
     * ম্যানুয়ালি পাস করা প্যারামিটার না। total_revenue শুধু sales
     * amount (বাস্তব উদাহরণে "Selling Amount")।
     *
     * ⚠️ Percentage আর hardcode না (0.50/0.10/0.40) — project-এর নিজস্ব
     * investor_share_percent/channel_partner_share_percent/company_share_percent
     * ব্যবহার হয়, কারণ ভিন্ন project-এ ভিন্ন চুক্তি থাকতে পারে।
     */
    public function calculateAndSettle(
        InvestmentProject $project,
        float $totalRevenue,
        int $settledByUserId
    ): ProjectSettlement {
        if ($project->settlement()->exists()) {
            throw new \RuntimeException('এই প্রজেক্টের জন্য আগেই settlement তৈরি হয়েছে।');
        }

        $totalCost = $project->totalCostItems(); // landed_cost + local_expense যোগফল
        $netProfit = $totalRevenue - $totalCost;

        if ($netProfit < 0) {
            throw new \RuntimeException('Net profit নেগেটিভ — settlement করার আগে loss handling নিয়ম নিশ্চিত করুন।');
        }

        return DB::transaction(function () use ($project, $totalRevenue, $totalCost, $netProfit, $settledByUserId) {
            $investorPool = round($netProfit * ($project->investor_share_percent / 100), 2);
            $channelPartnerAmount = round($netProfit * ($project->channel_partner_share_percent / 100), 2);
            // company_net রাউন্ডিং-সেফ বাকি অংশ হিসেবে (তিন percentage-র
            // যোগফল 100 হওয়া model-level validation-এ নিশ্চিত করা আছে)
            $companyNet = round($netProfit - $investorPool - $channelPartnerAmount, 2);

            $annualizedRate = $project->trade_cycle_days
                ? round(($netProfit / max($project->totalInvested(), 1)) * (365 / $project->trade_cycle_days) * 100, 2)
                : null;

            $settlement = ProjectSettlement::create([
                'project_id' => $project->id,
                'total_revenue' => $totalRevenue,
                'total_cost' => $totalCost,
                'net_profit' => $netProfit,
                'investor_pool_amount' => $investorPool,
                'channel_partner_amount' => $channelPartnerAmount,
                'company_net_amount' => $companyNet,
                'annualized_return_percent' => $annualizedRate,
                'status' => 'draft',
                'settled_by' => $settledByUserId,
                'settled_at' => now(),
            ]);

            $this->generateInvestorPayouts($project, $settlement, $investorPool);
            $this->generateChannelPartnerPayouts($project, $settlement, $channelPartnerAmount);

            return $settlement;
        });
    }

    private function generateInvestorPayouts(
        InvestmentProject $project,
        ProjectSettlement $settlement,
        float $investorPool
    ): void {
        $totalInvested = $project->totalInvested();

        foreach ($project->investments as $investment) {
            $ratio = $totalInvested > 0
                ? $investment->amount / $totalInvested
                : 0;

            $profitShare = round($investorPool * $ratio, 2);

            SettlementPayout::create([
                'settlement_id' => $settlement->id,
                'investor_id' => $investment->investor_id,
                'investment_amount' => $investment->amount,
                'profit_share_amount' => $profitShare,
                'total_payout' => $investment->amount + $profitShare,
                'payment_status' => 'pending',
                'recipient_name' => $investment->investor->name, // ডিফল্ট, পরে override করা যাবে
            ]);
        }
    }

    private function generateChannelPartnerPayouts(
        InvestmentProject $project,
        ProjectSettlement $settlement,
        float $channelPartnerAmount
    ): void {
        // চ্যানেল পার্টনার অনুযায়ী গ্রুপ করে ভাগ করা (একাধিক investor একই partner-এর মাধ্যমে এসে থাকতে পারেন)
        $investorsWithPartner = $project->investments()
            ->with('investor.channelPartner')
            ->get()
            ->pluck('investor')
            ->unique('id')
            ->filter(fn ($investor) => $investor->channel_partner_id !== null);

        if ($investorsWithPartner->isEmpty()) {
            return; // কোনো channel partner নেই, পুরো ১০% company-তে যাবে— এই সিদ্ধান্ত owner নিশ্চিত করবেন
        }

        $perPartnerShare = round($channelPartnerAmount / $investorsWithPartner->pluck('channel_partner_id')->unique()->count(), 2);

        foreach ($investorsWithPartner->pluck('channel_partner_id')->unique() as $partnerId) {
            ChannelPartnerPayout::create([
                'settlement_id' => $settlement->id,
                'investor_id' => $partnerId,
                'amount' => $perPartnerShare,
                'payment_status' => 'pending',
            ]);
        }
    }
}
```

**⚠️ এজেন্টের জন্য নোট:** "চ্যানেল পার্টনার একাধিক হলে কীভাবে ১০% ভাগ হবে" — এই প্রশ্নের উত্তর কোডে assumption দিয়ে দেওয়া হয়েছে (সমান ভাগে)। বাস্তবে কাজ শুরু করার আগে **এই assumption ব্যবহারকারীকে কনফার্ম করতে হবে** — প্রতি investor-ভিত্তিক আলাদা ভাগ হবে, নাকি সমান ভাগ।

---

## ধাপ ৩: Filament Resources

### 3.1 `InvestmentProjectResource`

```bash
php artisan make:filament-resource InvestmentProject --generate
```

Form fields:
```txt
project_code (TextInput, read-only, auto-generated — দেখানো হবে কিন্তু edit করা যাবে না)
name (TextInput, required)
deal_reference (TextInput — "Shearing Machine (Taiwan Made)"-এর মতো)
purchase_id (Select, বিদ্যমান Purchase resource থেকে, nullable)
description (Textarea)
duration_type (Select: 2_month/6_month/12_month/custom_days)
trade_cycle_days (TextInput, numeric — শুধু duration_type='custom_days' হলে ->visible())
start_date, end_date (DatePicker)
target_amount (TextInput, numeric)

Section: "Profit Split (%)" — ডিফল্ট ৪০/১০/৫০, কিন্তু editable
  investor_share_percent, channel_partner_share_percent, company_share_percent
  (তিনটা TextInput, নিচে live validation দেখাবে যোগফল ১০০ কিনা)

status (Select: open/running/closed/settled — Settled কখনোই ম্যানুয়ালি
        সিলেক্ট করা যাবে না, শুধু settlement action দিয়ে সেট হবে)
```

Table columns:
```txt
project_code, name, deal_reference, duration_type/trade_cycle_days,
total_invested (computed — investments sum), target_amount,
progress_bar (total_invested / target_amount %), status badge
```

RelationManager: **CostItemsRelationManager** (নতুন)
```txt
Fields: category (Select: landed_cost/local_expense), label, amount,
        purchase_id (Select, বিদ্যমান Purchase-এর সাথে link, nullable), remarks
Table-এ category-অনুযায়ী গ্রুপিং, নিচে "Total Landed Cost" ও
"Total Local Expense" summary দেখানো (Filament summarizer ব্যবহার করুন)
```

Custom action: **"Calculate & Settle"** বাটন (শুধু status = 'closed' হলে enable হবে) — এটা একটা Filament `Action` যেখানে শুধু **total_revenue** input নেওয়া হবে (total_cost আর ম্যানুয়াল input না, `project->totalCostItems()` থেকে auto-calculate) — `SettlementService::calculateAndSettle()` কল করবে।

### 3.2 `InvestorResource`

Form fields: `name, guardian_name, phone, email, address, nid_number, channel_partner_id (Select, searchable, অন্য investor থেকে — একবার সেট হলে শুধু super_admin edit করতে পারবেন, দেখুন 1.2-এর exclusivity নিয়ম)`

Infolist/View page-এ দেখাবে: তার সব project-এ investment history, lifetime total invested, lifetime total profit received।

RelationManager: **SecurityInstrumentsRelationManager** (Investment-এর ভিতরে, নতুন)
```txt
Fields: cheque_number, cheque_bank_name, cheque_amount, cheque_status,
        guarantor_name, guarantor_nid, guarantor_phone,
        contract_document_path (FileUpload — স্বাক্ষরিত চুক্তির স্ক্যান)
```

### 3.3 `InvestmentResource` (সাধারণত `InvestmentProjectResource`-এর ভিতরে Relation Manager হিসেবে)

```txt
RelationManager: InvestmentsRelationManager (InvestmentProjectResource-এর ভিতরে)
Fields: investor_id (Select + inline create), amount, payment_method, payment_reference, invested_at
```

### 3.4 `ProjectSettlementResource` (মূলত Read-only/View focused)

Infolist-এ দেখাবে:
```txt
Total Revenue, Total Cost (landed_cost + local_expense breakdown সহ), Net Profit
Investor Pool (project.investor_share_percent%), Channel Partner
  (project.channel_partner_share_percent%), Company Net (project.company_share_percent%)
Annualized Return (%) — reporting-only badge
Settlement Payouts টেবিল (RelationManager) — প্রতি investor কত পেলেন,
  recipient_name/bank details edit করার সুযোগ (payout করার আগে)
Channel Partner Payouts টেবিল
```

Action: **"Mark as Paid"** — `settlement_payouts.payment_status` ও `channel_partner_payouts.payment_status` কে `pending → paid` করবে, `paid_at` সেট করবে।

---

## ধাপ ৪: Permission ও Audit

```txt
নতুন Gate/Permission যুক্ত করুন:
  - manage-investments (Super Admin, Manager)
  - view-investments (Accountant)
  - settle-projects (শুধু Super Admin — সবচেয়ে স্পর্শকাতর action)
  - manage-investor-channel-partner (শুধু Super Admin — channel_partner_id
    পরিবর্তনের জন্য, exclusivity নিয়ম রক্ষার্থে)

Audit log-এ যুক্ত করুন:
  - InvestmentProject create/update/delete
  - Investment create
  - ProjectSettlement create (settle action) — বিশেষভাবে গুরুত্বপূর্ণ, IP+user details সহ
  - SettlementPayout payment_status change
  - Investor.channel_partner_id পরিবর্তন — বাধ্যতামূলক reason সহ (exclusivity নিয়ম)
```

---

## ধাপ ৫: টেস্ট (বাধ্যতামূলক — আর্থিক হিসাব)

```bash
php artisan make:test InvestmentSettlementTest
```

আবশ্যক টেস্ট কেস:

```txt
test_investor_pool_matches_project_configured_percentage()
    — hardcode 50%/40% ধরে না নিয়ে, project.investor_share_percent
      অনুযায়ী সঠিক amount ক্যালকুলেট হচ্ছে কিনা (ডিফল্ট ৪০%)
test_channel_partner_amount_matches_project_configured_percentage()
    — ডিফল্ট ১০%
test_company_net_matches_project_configured_percentage()
    — ডিফল্ট ৫০%
test_project_percentages_must_sum_to_100()
    — investor+channel+company ≠ 100 হলে save ব্যর্থ হয় (model boot() validation)
test_total_cost_auto_calculated_from_cost_items()
    — project_cost_items-এর landed_cost + local_expense যোগফলই
      settlement-এর total_cost হয়, ম্যানুয়াল input না
test_investor_payout_ratio_matches_investment_ratio()
    — উদাহরণ: A invested 60,000, B invested 40,000 (total 100,000)
      profit pool হলে A/B তাদের ratio অনুযায়ী ভাগ পাবে
test_settlement_cannot_be_created_twice_for_same_project()
test_settlement_throws_exception_on_negative_profit()
test_total_payout_equals_investment_amount_plus_profit_share()
test_settlement_status_cannot_be_manually_edited_after_confirmed()
test_annualized_rate_calculated_correctly_from_trade_cycle_days()
    — বাস্তব উদাহরণ ভ্যালিডেশন: ৬০ দিনের project-এ net profit ratio-কে
      365/60 দিয়ে annualize করলে প্রত্যাশিত rate পাওয়া যায়
test_company_isolation_investor_cannot_see_other_company_projects()
test_channel_partner_id_change_requires_permission_and_logs_reason()
```

উদাহরণ টেস্ট:

```php
public function test_investor_payout_ratio_matches_investment_ratio(): void
{
    $project = InvestmentProject::factory()->create([
        'status' => 'closed',
        'investor_share_percent' => 40.00,
        'channel_partner_share_percent' => 10.00,
        'company_share_percent' => 50.00,
    ]);

    $investorA = Investor::factory()->create();
    $investorB = Investor::factory()->create();

    Investment::factory()->create([
        'project_id' => $project->id,
        'investor_id' => $investorA->id,
        'amount' => 60000,
    ]);

    Investment::factory()->create([
        'project_id' => $project->id,
        'investor_id' => $investorB->id,
        'amount' => 40000,
    ]);

    ProjectCostItem::factory()->create([
        'project_id' => $project->id,
        'category' => 'landed_cost',
        'amount' => 90000,
    ]);
    ProjectCostItem::factory()->create([
        'project_id' => $project->id,
        'category' => 'local_expense',
        'amount' => 10000,
    ]);
    // total_cost = 100,000 (auto-calculated)

    $settlement = (new SettlementService())->calculateAndSettle(
        project: $project,
        totalRevenue: 200000, // net profit = 100,000
        settledByUserId: 1,
    );

    // Investor pool = 100,000 × 40% = 40,000
    $payoutA = $settlement->payouts()->where('investor_id', $investorA->id)->first();
    $payoutB = $settlement->payouts()->where('investor_id', $investorB->id)->first();

    $this->assertEquals(24000, $payoutA->profit_share_amount); // 60% of 40,000
    $this->assertEquals(16000, $payoutB->profit_share_amount); // 40% of 40,000
}
```

---

## ধাপ ৬: ম্যানুয়াল ভেরিফিকেশন চেকলিস্ট (প্রোডাকশনে যাওয়ার আগে)

```txt
[ ] একটা টেস্ট প্রজেক্টে ২ জন investor দিয়ে settlement চালিয়ে হাতে calculator দিয়ে cross-check করুন
[ ] বাস্তব ডকুমেন্টের উদাহরণ (Shearing Machine প্রজেক্ট) দিয়ে টেস্ট ডেটা তৈরি
    করে সিস্টেমের output বাস্তব settlement sheet-এর সংখ্যার সাথে মেলে কিনা
    cross-verify করুন (Total Cost 6,199,942 + 255,500, Net Profit 544,558,
    Investor 40% = 217,823.2)
[ ] Negative profit হলে সিস্টেম সঠিকভাবে block করছে কিনা
[ ] Settlement একবার করার পর দ্বিতীয়বার করতে গেলে error দেখাচ্ছে কিনা
[ ] Project percentage ১০০-এর বেশি/কম হলে save ব্যর্থ হচ্ছে কিনা
[ ] Channel partner ছাড়া investor-দের জন্য ১০% ভাগ কোথায় যাচ্ছে তা owner-এর
    সাথে কনফার্ম করা (company_net-এ যোগ হবে, নাকি আলাদা reserve?)
[ ] Company isolation — অন্য company-র investor/project দেখা যাচ্ছে না তা যাচাই
[ ] Audit log-এ settlement action সঠিকভাবে রেকর্ড হচ্ছে কিনা
[ ] Investor.channel_partner_id পরিবর্তনের চেষ্টা করলে permission ও audit
    reason বাধ্যতামূলক হচ্ছে কিনা
[ ] Security instrument (cheque+guarantor) তথ্য সেভ ও contract document
    upload/download কাজ করছে কিনা
```

---

## ডকুমেন্টেশন আপডেট (এই কাজ শেষে বাধ্যতামূলক)

```txt
PROJECT_GUIDE.md-তে নতুন সেকশন যুক্ত করুন: "Investor / Mudarabah Module"
ERP_PHASE_ROADMAP.md-তে এই phase-কে "Done" মার্ক করুন এবং Future Work লিখুন:
  - Multi-currency investment সাপোর্ট
  - Partial early withdrawal নিয়ম (DOCX চুক্তির ধারা ৫ অনুযায়ী — ১.৫-২ মাসে
    profit withdraw করা যায়, বা re-invest — এই flow এখনো ডিজাইন করা হয়নি,
    ভবিষ্যতে যুক্ত করতে হবে)
  - Investor-facing portal (নিজের investment status দেখার জন্য, ভবিষ্যতে)
  - Notice-period tracking (৬০ দিন/২ মাস আগে লিখিত নোটিশ — চুক্তির ধারা ৬)
```
