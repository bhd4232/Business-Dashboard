# Investor Profit ও Reseller Commission — Auto Bank/MFS Payout Plan

> **Status:** Draft — কোডিং শুরুর আগে owner-এর কনফার্মেশন লাগবে (নিচে "❗ Owner Decision লাগবে" চিহ্নিত প্রতিটা জায়গায়)
> **Created:** ২০২৬-০৯-১১
> **স্কোপ:** বিদ্যমান Investor/Mudarabah module (`01_INVESTOR_MUDARABAH_MODULE_PLAN.md`, কোড already আছে) এবং Reseller module-এর জন্য একটা **শেয়ার্ড ব্যাংক/MFS auto-payout ইঞ্জিন** — settlement/commission ক্যালকুলেশন থেকে শুরু করে ব্যাংক অ্যাকাউন্টে/MFS-এ প্রকৃত টাকা পাঠানো পর্যন্ত পুরো চেইন।
> **এই সেশনে যা হয়েছে:** ব্যবহারকারীর নিশ্চিতকরণ অনুযায়ী **শুধু এই plan ডকুমেন্ট** — কোনো migration/model/code লেখা হয়নি।

---

## ০. এই মুহূর্তে যা এখনো অজানা (owner confirm করবেন, কোড শুরুর পূর্বশর্ত)

আলোচনায় জিজ্ঞেস করা প্রশ্ন ও উত্তর:

```txt
[❓] BEFTN-এর জন্য ব্যাংকের সাথে সংযোগ কীভাবে হবে?
    → owner: "এখনো জানি না, ব্যাংকে কথা বলে জানাবো।"
    এই plan তাই ধরে নিচ্ছে না যে bank API/SFTP পাওয়া যাবে — Phase 1
    ডিজাইন করা হয়েছে "manual file-export" মোডে (নিচে ধাপ ২ ও Phase
    breakdown দেখুন) যাতে bank negotiation শেষ না হওয়া পর্যন্ত কাজ
    আটকে না থাকে। ব্যাংক থেকে উত্তর এলে শুধু একটা নতুন adapter class
    যুক্ত হবে — বাকি সিস্টেম অপরিবর্তিত থাকবে।

[❓] Reseller commission ডেলিভারির পর ঠিক কতদিন hold থাকবে (return
    window)?
    → owner: "কিছুদিন hold রেখে (রিটার্ন উইন্ডো শেষে)" — নির্দিষ্ট দিন
    সংখ্যা এখনো দেননি। এই plan-এ hold period **company-level configurable
    সেটিংস (দিন সংখ্যা)** হিসেবে ডিজাইন করা হয়েছে, hardcode না — কোডিং
    শুরুর ঠিক আগে exact সংখ্যা (৩ দিন? ৭ দিন?) confirm করতে হবে।

[❓] Reseller commission-এর রেট/সূত্র কী — অর্ডার ভ্যালুর flat %,
    প্রোডাক্ট-ভিত্তিক আলাদা %, নাকি ভলিউম-ভিত্তিক tier?
    → এখনো জিজ্ঞেস করা হয়নি। কোড শুরুর আগে বাধ্যতামূলক — CLAUDE.md
    নিয়ম অনুযায়ী "Never invent demo/placeholder business rules"।

[❓] Reseller payout কোন MFS প্রোভাইডার(গুলো) সাপোর্ট করতে হবে —
    bKash/Nagad/Rocket, একটা নাকি একাধিক? আর owner-এর কি bKash/Nagad-এর
    সাথে ইতিমধ্যে merchant "Disbursement/B2C Payout" চুক্তি আছে, নাকি
    সেটাও নতুন করে করতে হবে (এটাও bank BEFTN-এর মতোই আলাদা business
    approval লাগে, কোড দিয়ে বাইপাস করা যায় না)?

[❓] পে-আউট ব্যাচ কে approve করবে — একজন super admin, নাকি maker-checker
    (একজন batch generate করবে, আলাদা একজন approve করে bank-এ পাঠাবে)?
    টাকা সরাসরি নড়ে এই workflow-তে, তাই সুপারিশ হলো কমপক্ষে একটা মানুষের
    approval ধাপ **কখনোই বাদ দেওয়া যাবে না**, পুরোপুরি unattended
    auto-transfer না করা।

[❓] Investor/Reseller-এর প্রকৃত bank routing number সংগ্রহ আছে কি না —
    বর্তমান `settlement_payouts` টেবিলে bank name/branch/account number
    আছে কিন্তু ১৩-ডিজিট BEFTN routing number নেই, retrofit করতে হবে।

[❓] Commission পাঠানোর পরে অর্ডার return/refund হলে (hold window পার
    হয়ে যাওয়ার পরেও, যেমন warranty return) — সেই টাকা কীভাবে রিকভার
    হবে (পরের payout থেকে কেটে নেওয়া, নাকি আলাদা receivable)?

[❓] Investor profit / Reseller commission পেমেন্টে কোনো TDS/উৎসে কর
    কাটতে হয় কিনা — এটা accountant/owner-এর কাছ থেকে নিশ্চিত হওয়া
    দরকার, ভুল হলে আইনি সমস্যা হতে পারে।
```

উপরের প্রতিটা প্রশ্নের জবাব ছাড়া কোডিং শুরু করা উচিত নয় — নিচের ডিজাইন এই অনিশ্চয়তাগুলো মাথায় রেখে **যত বেশি সম্ভব configurable/swappable** রাখা হয়েছে, যাতে answer আসার পর পুরো re-design না লাগে।

---

## ১. NPSB বনাম BEFTN — কেন BEFTN লাগবে (টেকনিক্যাল প্রেক্ষাপট)

| | **NPSB** | **BEFTN** |
|---|---|---|
| পরিচালক | Bangladesh Bank-এর National Payment Switch | Bangladesh Bank-এর Electronic Funds Transfer Network |
| ধরন | Real-time, per-transaction, ইন্টার-ব্যাংক (এক থেকে এক) | Batch/bulk, deferred net settlement (দিনে ২টা settlement cycle) |
| ব্যবহার | ATM/POS/online একক transfer, তাৎক্ষণিক লাগবে এমন কেসে | Salary disbursement, dividend, vendor payment — **একসাথে একাধিক ভিন্ন ভিন্ন ব্যাংকের অ্যাকাউন্টে bulk credit** |
| প্রতি-লেনদেন খরচ/লিমিট | তুলনামূলক বেশি, প্রতিটা transaction আলাদা চার্জ | Bulk হওয়ায় per-transaction খরচ কম, কিন্তু তাৎক্ষণিক কনফার্মেশন নেই |
| Confirmation | Real-time success/fail | ব্যাংক batch process করে পরে; reconciliation লাগে (statement দেখে মিলাতে হয়) |

**সিদ্ধান্ত সঠিক:** একসাথে অনেক investor/reseller-এর ভিন্ন ভিন্ন ব্যাংকে টাকা পাঠাতে হলে BEFTN-ই standard পথ (salary disbursement-এর মতো)। বাস্তবতা:

- বাংলাদেশে **কোনো ইউনিভার্সাল পাবলিক BEFTN API নেই** যা সব ব্যাংকে একভাবে কাজ করে। প্রতিটা ব্যাংক তার নিজের নিয়মে কাজ করে — সাধারণত তিনটা সম্ভাব্য মোড:
  1. **File-export + manual submission** — bank-এর নির্ধারিত ফরম্যাটে (সাধারণত fixed-width text বা CSV: routing number, account number, account name, amount, reference) একটা ব্যাচ ফাইল বানিয়ে admin corporate internet banking-এর "Bulk Payment"/"Salary Disbursement" মডিউলে আপলোড করেন, বা branch-এ জমা দেন। **কোনো bank negotiation ছাড়াই কাজ করা যায়** — বেশিরভাগ ব্যাংকই এটা সাপোর্ট করে।
  2. **Corporate/Cash-Management API** — কিছু ব্যাংক (সাধারণত বড় corporate ক্লায়েন্টদের জন্য) সরাসরি push API/SFTP দেয়, কিন্তু এর জন্য আলাদা corporate banking agreement + IP whitelisting + digital signature/token লাগে — owner-এর ব্যাংকের সাথে কথা বলার পরই জানা যাবে এটা আছে কিনা।
  3. কিছু ব্যাংক BEFTN-এর বদলে তৃতীয় পক্ষের "bulk disbursement" agregator (যেমন SSLCommerz/PayStation-এর disbursement product, bKash-এর মতো MFS-ভিত্তিক corporate পে-আউট) অফার করে — সেটাও বিকল্প হতে পারে যদি সরাসরি bank BEFTN জটিল হয়।

- **তাই এই plan Phase 1-কে মোড (1)-এর উপর ভিত্তি করে ডিজাইন করছে** — একটা জেনেরিক/কনফিগারেবল BEFTN ফাইল এক্সপোর্টার, যেখানে ফরম্যাট owner-এর ব্যাংক নিশ্চিত করার পর সহজে টিউন করা যায় (নিচে ধাপ ৫.২)। bank API confirm হলে Phase 2-এ শুধু একটা নতুন adapter যুক্ত হবে, বাকি সিস্টেম (batch tracking, approval, reconciliation UI) একই থাকবে।

---

## ২. আর্কিটেকচার ওভারভিউ — শেয়ার্ড Payout ইঞ্জিন

Investor payout ও Reseller commission — দুটো সম্পূর্ণ ভিন্ন ট্রিগার থেকে আসে, কিন্তু "টাকা কীভাবে বেরিয়ে যাবে" অংশটা এক। একটা শেয়ার্ড ইঞ্জিন দুই মডিউলকেই সার্ভ করবে:

```txt
┌─────────────────────┐        ┌─────────────────────────┐
│ Investor: Settlement │        │ Reseller: Order delivered│
│ confirmed (বিদ্যমান  │        │ + hold window পার        │
│ SettlementService)  │        │ (নতুন ResellerCommission │
│  → SettlementPayout  │        │  Service + scheduled cmd)│
│    (pending)         │        │  → ResellerCommission    │
└──────────┬───────────┘        │    (payable)             │
           │                    └────────────┬─────────────┘
           └────────────┬────────────────────┘
                         ▼
              ┌─────────────────────┐
              │  PayoutItem (নতুন,   │  ← polymorphic: SettlementPayout /
              │  পেন্ডিং, method     │     ChannelPartnerPayout / ResellerCommission
              │  bank/mfs নির্ধারিত)  │     -কে পয়েন্ট করে
              └──────────┬───────────┘
                         ▼
              ┌─────────────────────┐
              │  PayoutBatch (নতুন)  │  ← admin "Generate Batch" — method,
              │  status: draft       │     তারিখ, মোট টাকা/সংখ্যা অনুযায়ী গ্রুপ
              └──────────┬───────────┘
                         ▼
              ┌─────────────────────┐
              │ admin review + মানুষের│  ← কখনোই স্কিপ করা যাবে না —
              │ approval (বাধ্যতামূলক)│     এখানে ভুল হলে ভুল অ্যাকাউন্টে টাকা যাবে
              └──────────┬───────────┘
                         ▼
        ┌────────────────┴────────────────┐
        ▼                                  ▼
┌─────────────────────┐        ┌─────────────────────────┐
│ BEFTN ফাইল export     │        │ MFS disbursement (bKash/ │
│ (bank-নির্দিষ্ট ফরম্যাট, │        │ Nagad/Rocket) — API বা    │
│ adapter pattern)      │        │ manual send             │
└──────────┬───────────┘        └────────────┬─────────────┘
           └────────────┬────────────────────┘
                         ▼
              ┌─────────────────────┐
              │ Bank/MFS-এ জমা →      │  ← settlement আসতে সময় লাগে
              │ admin reconcile করে   │     (BEFTN দিনে ২ সাইকেল)
              │ batch "completed"     │
              │ / item "failed"       │
              │ মার্ক করে              │
              └──────────┬───────────┘
                         ▼
              SettlementPayout.payment_status = paid
              ResellerCommission.status = paid
```

**মূল নিয়ম:** পুরো ফ্লো-তে কোথাও "generate হলেই bank-এ টাকা চলে যায়" — এমন fully-unattended অটোমেশন রাখা হচ্ছে না। ক্যালকুলেশন + ব্যাচ তৈরি + ফাইল জেনারেশন অটো হবে, কিন্তু **bank-এ সাবমিট করার আগে একটা মানুষের approval ধাপ compulsory** — কারণ এখানে ভুল রিকভার করা কঠিন (টাকা সরাসরি অন্য কারো অ্যাকাউন্টে চলে গেলে ফেরত পাওয়া অনিশ্চিত)।

---

## ৩. Database Migrations

### ৩.১ `company_payout_settings` টেবিল (নতুন — ব্যাংক/MFS ক্রেডেনশিয়াল, encrypted)

বিদ্যমান প্যাটার্ন অনুসরণ (দেখুন `StorefrontSetting::payment_credentials` — `encrypted:array` cast, company-scoped)।

```php
Schema::create('company_payout_settings', function (Blueprint $table) {
    $table->id();
    $table->foreignId('company_id')->unique()->constrained()->cascadeOnDelete();

    // কোম্পানির নিজের disbursing bank account — যে অ্যাকাউন্ট থেকে টাকা বেরোবে
    $table->text('disbursing_bank_details')->nullable(); // encrypted:array
        // {bank_name, branch, routing_number, account_number, account_name}

    $table->enum('beftn_integration_mode', ['file_export_manual', 'bank_api'])
          ->default('file_export_manual');
        // owner ব্যাংকের সাথে কথা বলে API/SFTP পেলে 'bank_api'-তে পরিবর্তন হবে
    $table->text('beftn_api_credentials')->nullable(); // encrypted:array, mode='bank_api' হলেই ব্যবহৃত

    $table->boolean('mfs_bkash_enabled')->default(false);
    $table->text('mfs_bkash_credentials')->nullable(); // encrypted:array
    $table->boolean('mfs_nagad_enabled')->default(false);
    $table->text('mfs_nagad_credentials')->nullable(); // encrypted:array
    $table->boolean('mfs_rocket_enabled')->default(false);
    $table->text('mfs_rocket_credentials')->nullable(); // encrypted:array

    $table->unsignedInteger('reseller_commission_hold_days')->default(7);
        // ❗ owner confirm করার আগে ডিফল্ট শুধু placeholder, hardcode ব্যবসায়িক
        // নিয়ম না — কোডিং শুরুর আগে আসল সংখ্যা বসাতে হবে

    $table->timestamps();
});
```

### ৩.২ `payout_batches` টেবিল (নতুন — শেয়ার্ড)

```php
Schema::create('payout_batches', function (Blueprint $table) {
    $table->id();
    $table->foreignId('company_id')->constrained()->cascadeOnDelete();
    $table->string('batch_number')->unique(); // যেমন "PB-2026-000123"
    $table->enum('method', ['beftn_bank', 'mfs_bkash', 'mfs_nagad', 'mfs_rocket', 'manual_other']);
    $table->enum('status', [
        'draft', 'pending_approval', 'approved', 'file_generated',
        'submitted_to_bank', 'partially_completed', 'completed', 'failed', 'cancelled',
    ])->default('draft');
    $table->decimal('total_amount', 15, 2)->default(0);
    $table->unsignedInteger('total_items')->default(0);
    $table->foreignId('generated_by')->constrained('users');
    $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('approved_at')->nullable();
    $table->string('export_file_path')->nullable(); // জেনারেট করা BEFTN ফাইল
    $table->timestamp('submitted_at')->nullable();
    $table->string('bank_batch_reference')->nullable(); // ব্যাংক দেওয়া reference, reconciliation-এ লাগবে
    $table->timestamp('completed_at')->nullable();
    $table->text('notes')->nullable();
    $table->timestamps();

    $table->index(['company_id', 'status']);
});
```

**⚠️ State machine নিয়ম:** `file_generated` বা তার পরের status-এ গেলে ব্যাচের ভেতরের item সংখ্যা/amount **আর পরিবর্তনযোগ্য না** — নতুন item যুক্ত করতে হলে নতুন ব্যাচ বানাতে হবে। একই ব্যাচের ফাইল দ্বিতীয়বার জেনারেট করা (double-submission ঝুঁকি) UI থেকে ব্লক করতে হবে।

### ৩.৩ `payout_items` টেবিল (নতুন — শেয়ার্ড, polymorphic)

```php
Schema::create('payout_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('company_id')->constrained()->cascadeOnDelete();
    $table->foreignId('payout_batch_id')->nullable()->constrained()->nullOnDelete();
        // nullable — ব্যাচে যুক্ত হওয়ার আগে payable/pending অবস্থায় থাকে
    $table->morphs('payable'); // payable_type/payable_id → SettlementPayout | ChannelPartnerPayout | ResellerCommission
    $table->string('recipient_name');
    $table->enum('payout_method', ['beftn_bank', 'mfs_bkash', 'mfs_nagad', 'mfs_rocket', 'manual_other']);

    // ব্যাংক পাঠানোর মুহূর্তের snapshot — পরে recipient তার account বদলালেও
    // পুরনো payout-এর রেকর্ড অপরিবর্তিত থাকা উচিত (audit-safe)
    $table->string('recipient_bank_name')->nullable();
    $table->string('recipient_branch')->nullable();
    $table->string('recipient_routing_number')->nullable(); // BEFTN routing number (১৩ ডিজিট)
    $table->string('recipient_account_number')->nullable();
    $table->string('recipient_mfs_number')->nullable(); // bKash/Nagad/Rocket নম্বর

    $table->decimal('amount', 15, 2);
    $table->enum('status', ['pending', 'in_batch', 'paid', 'failed', 'returned'])->default('pending');
    $table->string('failure_reason')->nullable();
    $table->string('bank_transaction_reference')->nullable();
    $table->timestamp('paid_at')->nullable();
    $table->timestamps();

    $table->index(['company_id', 'status']);
});
```

### ৩.৪ `reseller_commissions` টেবিল (নতুন)

```php
Schema::create('reseller_commissions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('company_id')->constrained()->cascadeOnDelete();
    $table->foreignId('order_id')->constrained()->cascadeOnDelete();
    $table->foreignId('reseller_customer_id')->constrained('customers')->cascadeOnDelete();
    $table->decimal('order_total', 15, 2);
    $table->decimal('commission_rate_percent', 5, 2); // ❗ রেট সূত্র owner confirm করার পর ঠিক হবে
    $table->decimal('commission_amount', 15, 2);
    $table->enum('status', ['holding', 'payable', 'in_batch', 'paid', 'reversed'])->default('holding');
        // holding    = ডেলিভারি কমপ্লিট, hold window চলছে
        // payable    = hold window পার, payout batch-এ যুক্ত হওয়ার অপেক্ষায়
        // in_batch   = payout_items-এ যুক্ত হয়ে গেছে
        // paid       = ব্যাংক/MFS-এ কনফার্ম হয়ে গেছে
        // reversed   = hold window-এর মধ্যে return/refund হওয়ায় বাতিল
    $table->date('order_delivered_at');
    $table->date('holding_until'); // = order_delivered_at + company_payout_settings.reseller_commission_hold_days
    $table->timestamp('promoted_to_payable_at')->nullable();
    $table->timestamp('reversed_at')->nullable();
    $table->string('reversal_reason')->nullable();
    $table->timestamps();

    $table->unique('order_id'); // এক অর্ডারে একটাই কমিশন রেকর্ড
    $table->index(['company_id', 'status']);
});
```

### ৩.৫ Retrofit — বিদ্যমান টেবিলে routing number ও reseller payout preference যুক্ত করা

```php
// settlement_payouts, channel_partner_payouts টেবিলে
$table->string('recipient_routing_number')->nullable()->after('recipient_branch');

// customers টেবিলে (reseller payout preference)
$table->enum('reseller_payout_method', ['bank', 'mfs_bkash', 'mfs_nagad', 'mfs_rocket'])->nullable();
$table->text('reseller_payout_account_details')->nullable(); // encrypted:array
    // bank হলে {bank_name, branch, routing_number, account_number, account_name}
    // mfs হলে {msisdn}
```

---

## ৪. Models (সংক্ষিপ্ত, শুধু নতুন সম্পর্ক/মেথড)

```txt
CompanyPayoutSetting   — belongsTo Company (BelongsToCompany + CompanyScope)
PayoutBatch            — BelongsToCompany; hasMany PayoutItem; belongsTo User (generatedBy/approvedBy)
PayoutItem             — BelongsToCompany; belongsTo PayoutBatch; morphTo payable
ResellerCommission     — BelongsToCompany; belongsTo Order; belongsTo Customer (resellerCustomer);
                          hasOne PayoutItem (morphOne, payable_type=ResellerCommission)
SettlementPayout        — বিদ্যমান, + morphOne PayoutItem যুক্ত হবে
ChannelPartnerPayout    — বিদ্যমান, + morphOne PayoutItem যুক্ত হবে
Customer                — reseller payout preference-এর জন্য accessor: resellerPayoutAccountLabel()
```

সব নতুন মডেলে `use BelongsToCompany;` + `MultiCompanyIsolationTest::test_every_company_owned_model_uses_the_company_scope_contract()`-এর লিস্টে যুক্ত করা **বাধ্যতামূলক** (CLAUDE.md নিয়ম)।

---

## ৫. Services

### ৫.১ `ResellerCommissionService` (নতুন)

```txt
createOnDelivery(Order $order): ResellerCommission
    — Order stage 'delivered'/'completed'-এ পরিণত হওয়ার observer/listener থেকে কল হবে
    — শুধুমাত্র $order->reseller_customer_id !== null হলে চলবে
    — commission_rate/amount ক্যালকুলেশন ❗ owner-confirmed সূত্র অনুযায়ী
      (flat % ধরে নেওয়া এই ডকুমেন্টে placeholder মাত্র, কোড লেখার আগে
      চূড়ান্ত সূত্র লাগবে)
    — status='holding', holding_until = delivered_at + company setting-এর
      reseller_commission_hold_days

promoteDueToPayable(): int
    — scheduled command থেকে কল হবে, প্রতিটা কোম্পানির জন্য আলাদা লুপ
      (CLAUDE.md নিয়ম: "scheduled commands loop per company")
    — status='holding' AND holding_until <= today AND সংশ্লিষ্ট Order এখনো
      returned/refunded হয়নি — এমন সব রেকর্ড status='payable'-এ promote করে

reverseForReturnedOrder(Order $order, string $reason): void
    — Order stage 'returned'/'refunded'-এ গেলে (এখনো hold window-এর মধ্যে
      বা পরেও — ❗ owner confirm করবেন hold window পার হয়ে যাওয়ার পরের
      return-এ কী হবে) সংশ্লিষ্ট ResellerCommission status='reversed'
```

### ৫.২ `PayoutBatchService` (নতুন — শেয়ার্ড)

```txt
createBatch(Company $company, string $method, Collection $payoutItemIds, int $generatedByUserId): PayoutBatch
    — শুধু status='pending' PayoutItem-গুলো নেওয়া যাবে, একই method-এর
    — ব্যাচ তৈরি হলে item-গুলোর status='in_batch', payout_batch_id সেট
    — AuditLogService-এ রেকর্ড

approve(PayoutBatch $batch, int $approvedByUserId): void
    — status: pending_approval → approved
    — ❗ owner confirm করবেন: generate আর approve একই ইউজার পারবে কিনা,
      নাকি বাধ্যতামূলক আলাদা ইউজার (maker-checker)

generateExportFile(PayoutBatch $batch): string
    — method='beftn_bank' হলে BeftnFileFormatter (নিচে ৫.৩) কল করে
    — method='mfs_*' হলে সংশ্লিষ্ট MFS adapter কল করে (API থাকলে সরাসরি
      disbursement কল করে, না থাকলে ম্যানুয়াল লিস্ট এক্সপোর্ট করে)
    — status: approved → file_generated, export_file_path সেভ

markSubmitted(PayoutBatch $batch, ?string $bankBatchReference): void
    — status: file_generated → submitted_to_bank

reconcile(PayoutBatch $batch, array $itemResults): void
    — $itemResults = [payout_item_id => ['status' => 'paid'|'failed', 'reference' => ...]]
    — প্রতিটা item আপডেট, তারপর সংশ্লিষ্ট SettlementPayout/ChannelPartnerPayout/
      ResellerCommission-এর status/paid_at সিঙ্ক করে
    — সব item paid হলে batch.status='completed', কিছু failed হলে
      'partially_completed'
```

### ৫.৩ `BeftnFileFormatter` — Interface + Adapter Pattern

```php
interface BeftnFileFormatter
{
    public function generate(PayoutBatch $batch, Collection $items, array $disbursingBankDetails): string; // ফাইল পাথ রিটার্ন করে
}
```

- **Phase 1 ডিফল্ট ইমপ্লিমেন্টেশন (`GenericBeftnCsvFormatter`):** একটা সাধারণ CSV/text export — routing number, account number, account name, amount, reference — যা admin ম্যানুয়ালি bank-এর bulk-payment পোর্টালে re-format/আপলোড করতে পারবেন। এটা কোনো নির্দিষ্ট ব্যাংকের exact ফরম্যাট গ্যারান্টি করে না।
- **Phase 2 (ব্যাংক কনফার্ম করার পর):** owner-এর ব্যাংক exact ফাইল স্পেসিফিকেশন (column order, delimiter, fixed-width padding, header/trailer record ইত্যাদি) দিলে সেই অনুযায়ী `<BankName>BeftnFormatter` নামে নতুন adapter ক্লাস যুক্ত হবে — `BeftnFileFormatter` ইন্টারফেস মানলে `PayoutBatchService`-এ কোনো পরিবর্তন লাগবে না, শুধু `CompanyPayoutSetting`-এ কোন formatter ব্যবহার হবে তা resolve হবে।

### ৫.৪ MFS Disbursement Adapters (bKash/Nagad/Rocket)

```txt
interface MfsDisbursementClient
{
    isConfigured(CompanyPayoutSetting $setting): bool;
    disburse(CompanyPayoutSetting $setting, string $msisdn, float $amount, string $reference): array;
        // রিটার্ন করবে normalized ['status' => 'success'|'failed', 'transaction_id' => ...]
}
```

- bKash-এর "Merchant Disbursement"/B2C payout API আছে, কিন্তু এটার জন্য bKash-এর সাথে **আলাদা merchant agreement/approval** লাগে (সাধারণ Checkout URL API থেকে ভিন্ন) — owner এটা আগে থেকেই থাকলে জানাবেন, না থাকলে API integration করার আগে সেই ব্যবসায়িক প্রক্রিয়া শেষ করতে হবে।
- API না থাকা পর্যন্ত Phase 1-এ MFS পে-আউটও **ম্যানুয়াল লিস্ট এক্সপোর্ট** (নাম্বার + amount CSV) হিসেবে রাখা যায় — admin নিজের bKash/Nagad merchant app/personal wallet থেকে ম্যানুয়ালি send করে, তারপর সিস্টেমে "Mark as sent" করে reconcile করেন। এটা `mfs_bkash`/`mfs_nagad`/`mfs_rocket` method-কেও `beftn_bank`-এর মতোই "file_export_manual" মোডে রাখে।

---

## ৬. Scheduled Command

```txt
php artisan make:command PromoteResellerCommissionsCommand

Schedule (routes/console.php বা bootstrap/app.php-এর schedule closure-এ, দৈনিক):
    Schedule::command('reseller-commissions:promote')->dailyAt('01:00');

কমান্ডের ভেতরে (CLAUDE.md নিয়ম — "scheduled commands loop per company"):
    Company::query()->each(function (Company $company) {
        CompanyContext::set($company); // বা বিদ্যমান প্যাটার্ন যা অন্য scheduled command ব্যবহার করে
        app(ResellerCommissionService::class)->promoteDueToPayable();
        CompanyContext::clear();
    });
```

বিদ্যমান কোনো scheduled command (যেমন abandoned cart reminder) থেকে ঠিক প্যাটার্নটা কপি করা উচিত — নতুন ডিজাইন না বানিয়ে।

---

## ৭. Filament Resources

### ৭.১ নতুন **Payouts** ক্লাস্টার

```txt
PayoutBatchResource (মূলত read/action-focused)
  Table: batch_number, method, status badge, total_amount, total_items,
         generated_by, approved_by, submitted_at
  Actions:
    "Review & Approve"  — status='pending_approval' হলে enable, আইটেমের
                           লিস্ট + মোট amount দেখিয়ে কনফার্ম করতে হবে
    "Generate Export File" — status='approved' হলে enable, ফাইল ডাউনলোড
                              লিংক দেখায়
    "Mark Submitted to Bank" — bank_batch_reference ইনপুট নেয়
    "Reconcile" — প্রতিটা item-এর জন্য paid/failed মার্ক করার ফর্ম (বা
                   bank statement CSV আপলোড করে auto-match — Phase 2)
  RelationManager: ItemsRelationManager (PayoutItem লিস্ট, recipient/status/amount)

CompanyPayoutSettingsPage (Filament Page, super-admin only)
  — disbursing bank account, BEFTN mode selector, MFS প্রোভাইডার টগল +
    ক্রেডেনশিয়াল, reseller_commission_hold_days — সব encrypted field,
    বিদ্যমান "Integrations"/"Image Provider Settings" পেজের প্যাটার্ন
    অনুসরণ করে বানানো
```

### ৭.২ বিদ্যমান Investor resource-এ যুক্ত হবে

- `ProjectSettlementResource` → বিদ্যমান "Mark as Paid" অ্যাকশনের বদলে/পাশে **"Send to Payout Batch"** অ্যাকশন — `SettlementPayout`/`ChannelPartnerPayout` (status='pending') থেকে `PayoutItem` তৈরি করে batch flow-এ ঢোকায়। ছোট/ক্যাশ পেমেন্টের জন্য পুরনো ম্যানুয়াল "Mark as Paid" রাখা যেতে পারে (সব investor payout ব্যাংকেই যাবে না, কিছু হয়তো নগদে)।
- `InvestorResource` ফর্মে routing number ফিল্ড যুক্ত (retrofit)।

### ৭.৩ বিদ্যমান Reseller resource-এ যুক্ত হবে

- `ResellerResource` → নতুন **CommissionsRelationManager**: holding/payable/paid কমিশন হিস্টরি, ব্যালান্স সামারি।
- `ResellerForm` → `reseller_payout_method` + `reseller_payout_account_details` ফিল্ড।
- স্টোরফ্রন্ট রিসেলার অ্যাকাউন্ট পেজ (`resources/views/storefront/account/reseller.blade.php`) → নিজের pending/holding/payable/paid ব্যালান্স দেখানো (read-only)।

---

## ৮. Permissions ও Audit

```txt
নতুন Gate/Permission:
  - manage-payout-settings   (super-admin only — bank/MFS credentials)
  - generate-payout-batch    (Manager/Accountant)
  - approve-payout-batch     (super-admin only — ❗ maker-checker করতে হলে
                               generate ও approve আলাদা ব্যবহারকারীর হাতে থাকা
                               উচিত, owner confirm করবেন)
  - reconcile-payout-batch   (Accountant)

Audit log (AuditLogService, বিদ্যমান প্যাটার্ন):
  - PayoutBatch তৈরি/approve/submit/reconcile — প্রতিটা স্টেপে
  - CompanyPayoutSetting পরিবর্তন — অ্যাকাউন্ট নম্বর মাস্কড করে লগ করা
    (শেষ ৪ ডিজিট বাদে বাকিটা ****) — পুরো নম্বর plaintext audit log-এ
    রাখা যাবে না
  - ResellerCommission.status পরিবর্তন (বিশেষত reversed)
```

---

## ৯. সিকিউরিটি ও কমপ্লায়েন্স নোট

```txt
- সব ব্যাংক/MFS ক্রেডেনশিয়াল encrypted:array cast (বিদ্যমান প্যাটার্ন) —
  plaintext কখনো ডাটাবেসে/লগে না
- Idempotency: একই PayoutBatch-এর ফাইল দুইবার জেনারেট বা bank-এ দুইবার
  সাবমিট করা UI থেকেই ব্লক করা (status state machine) — double-payment
  সবচেয়ে বড় ঝুঁকি
- Approval ছাড়া কোনো item bank/MFS-এ যাবে না — এটা কোনো অবস্থাতেই
  bypass করা যাবে না, এমনকি "trusted" ব্যবহারকারীর জন্যও না
- Recipient bank/MFS ডিটেইল পরিবর্তনের পর একটা "cooling period" বা
  অন্তত নোটিফিকেশন রাখা ভালো (fraud pattern: কেউ অ্যাকাউন্ট বদলে
  পরের payout hijack করার চেষ্টা করতে পারে) — ❗ owner-এর সাথে আলোচনা করে
  ঠিক করা, এই ডকুমেন্টে চূড়ান্ত সিদ্ধান্ত নেওয়া হয়নি
- TDS/উৎসে কর নিয়ে accountant-এর confirmation ছাড়া কিছু deduct করা
  হবে না (❗ ধাপ ০)
```

---

## ১০. Tests (পরবর্তীতে কোডিং ধাপে লিখতে হবে)

```txt
tests/Feature/PayoutBatchTest.php
    test_batch_only_accepts_pending_items_of_same_method()
    test_batch_cannot_regenerate_export_file_once_generated()
    test_approving_batch_requires_approve_permission()
    test_reconcile_marks_underlying_settlement_payout_paid()
    test_reconcile_partial_failure_sets_batch_partially_completed()
    test_company_isolation_batches_and_items()

tests/Feature/ResellerCommissionTest.php
    test_commission_created_on_order_delivered_with_holding_status()
    test_commission_promoted_to_payable_after_hold_days_elapsed()
    test_commission_not_promoted_before_hold_days_elapsed()
    test_commission_reversed_when_order_returned_within_hold_window()
    test_commission_amount_matches_configured_rate() // ❗ রেট সূত্র চূড়ান্ত হওয়ার পর
    test_company_isolation_reseller_commissions()

tests/Feature/CompanyPayoutSettingsTest.php
    test_only_super_admin_can_edit_payout_settings()
    test_credentials_stored_encrypted()
```

---

## ১১. ম্যানুয়াল ভেরিফিকেশন চেকলিস্ট (প্রোডাকশনের আগে)

```txt
[ ] একটা টেস্ট ব্যাচে ২-৩টা ভুয়া/টেস্ট bank account দিয়ে পুরো ফ্লো
    (draft → approve → export file → submit → reconcile) হাতে চালিয়ে
    দেখা
[ ] Export করা BEFTN ফাইল ব্যাংকের branch/করেসপন্ডেন্টকে দেখিয়ে ফরম্যাট
    আসলেই গ্রহণযোগ্য কিনা যাচাই (প্রথম আসল payout-এর আগে অবশ্যই)
[ ] প্রথম আসল payout ছোট amount দিয়ে pilot করা, পুরো ব্যাচ না
[ ] Hold period ও commission রেট owner-confirmed মান দিয়ে সেট আছে
    কিনা রিভিউ
[ ] Encrypted credential আসলেই ডাটাবেসে plaintext না — কোয়েরি করে
    সরাসরি চেক করা
[ ] Company isolation — অন্য company-র batch/item/commission দেখা
    যাচ্ছে না
[ ] Audit log-এ প্রতিটা approval/reconcile ধাপ রেকর্ড হচ্ছে
```

---

## ১২. পর্যায়ক্রমিক রোলআউট (Phased Plan)

```txt
Phase 1 (bank negotiation-এর জন্য অপেক্ষা না করে শুরু করা যায়):
  - সব migration/model/service (৩-৬)
  - PayoutBatch/PayoutItem/ResellerCommission Filament UI (৭)
  - GenericBeftnCsvFormatter (ম্যানুয়াল এক্সপোর্ট) + MFS ম্যানুয়াল-লিস্ট
    এক্সপোর্ট
  - Reconciliation ম্যানুয়াল (admin bank statement দেখে item-ভিত্তিক
    paid/failed মার্ক করে)
  - এই Phase-এই investor/reseller পে-আউট বাস্তবিকভাবে চালু করা যায় —
    "auto" অংশটা হলো ক্যালকুলেশন + ব্যাচিং + ফাইল-জেনারেশন, শুধু bank-এ
    literal button-চেপে-পাঠানো অংশটা এখনো manual

Phase 2 (owner ব্যাংক থেকে API/SFTP কনফার্ম করার পর):
  - <BankName>BeftnFormatter / সরাসরি push adapter যুক্ত
  - PayoutBatch-এ automatic submission + status polling (bank যদি callback
    দেয়) — reconciliation অংশ কম ম্যানুয়াল হবে

Phase 3 (MFS disbursement business approval পাওয়ার পর, যদি owner চায়):
  - bKash/Nagad/Rocket-এর প্রকৃত B2C disbursement API ইন্টিগ্রেশন
```

---

## ডকুমেন্টেশন আপডেট নোট (কোডিং শেষে বাধ্যতামূলক, এখন না)

```txt
এই ডকুমেন্ট শুধু plan — বাস্তব কোড লেখা শুরু হলে:
  PROJECT_GUIDE.md-তে "Payout Engine (Investor + Reseller)" সেকশন যুক্ত
  ERP_PHASE_ROADMAP.md-তে এই phase যুক্ত করা
  CHANGELOG.md-তে প্রতিটা ধাপের জন্য পৃথক entry (এই plan ডকুমেন্ট নিজে
    Maintenance/docs-only entry হিসেবে ইতিমধ্যে যুক্ত হয়েছে)
```
