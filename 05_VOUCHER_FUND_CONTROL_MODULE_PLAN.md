# Voucher & Fund Control Module — বিস্তারিত স্টেপ-বাই-স্টেপ ইমপ্লিমেন্টেশন প্ল্যান

> **উৎস:** ব্যবহারকারীর দেওয়া দুটো ডিজাইন ডকুমেন্টের কম্বিনেশন —
> **Doc 2 (Fund & Inventory Control)** = accounting ভিত্তি (Stock ≠ Expense, Fund Source, Transaction Type)
> **Doc 1 (Voucher & Fund Control)** = workflow স্তর (Credit/Debit Voucher, Approval states, Money Receipt)
> এবং **নতুন Integration নিয়ম** যা বিদ্যমান ERP মডিউলের সাথে সংঘর্ষ প্রতিরোধ করে।

## ✅ Current Status Snapshot (Master Plan v2 অনুযায়ী, ২০২৬-০৭)

```txt
✅ Multi-Company System       — সম্পন্ন (BelongsToCompany trait, CompanyScope,
                                 CompanyContext সব কোডে আছে)
✅ Accounts/Expense/Ledger     — সম্পন্ন (accounts, expenses, transaction_ledgers
                                 টেবিল production-এ চলছে)
✅ Customer/Supplier Payments  — সম্পন্ন (customer_payments, supplier_payments
                                 production-এ চলছে — এই মডিউলের সাথে integration
                                 strategy নিচে ধাপ ১-এ)
✅ Purchase + China-BD Costing — সম্পন্ন (custom cost fields JSON সহ)
✅ Customer Risk approval flow — সম্পন্ন (একটা ছোট approval-request pattern
                                 ইতিমধ্যে কোডে আছে)
❌ Task/Approval Workflow      — এখনো শুরু হয়নি (03_TASK_APPROVAL_WORKFLOW_
                                 MODULE_PLAN.md — এই Voucher module সেটার
                                 ApprovalGateService pattern ব্যবহার করবে)
❌ Investor/Mudarabah          — এখনো শুরু হয়নি (01_INVESTOR_MUDARABAH_MODULE_
                                 PLAN.md — Investment integration নিচে দেখুন)
❌ Voucher & Fund Control      — এই ফাইলের বিষয়
```

## ⚠️ পূর্বশর্ত ও Sequencing সিদ্ধান্ত

```txt
[ ] এই মডিউল Task/Approval Workflow (03 ফাইল)-এর ApprovalGateService-এর
    উপর নির্ভরশীল। দুটো অপশন:
    ক) আগে 03 ফাইলের ApprovalGateService অংশটুকু implement করা (পুরো Task
       module না, শুধু approval core), তারপর এই মডিউল — সুপারিশকৃত
    খ) এই মডিউলে সরল approval logic দিয়ে শুরু করে পরে ApprovalGateService-এ
       migrate — দ্বিতীয় পছন্দ, রিফ্যাক্টর খরচ বাড়ায়
    ব্যবহারকারীর সাথে নিশ্চিত করুন কোনটা।
[ ] বিদ্যমান Accounts/Ledger স্থিতিশীল ও ব্যাকআপ আছে — এই মডিউল সরাসরি
    টাকার হিসাব স্পর্শ করে, ভুল হলে balance mismatch হবে
[ ] Investor/Mudarabah module-এর সাথে Investment overlap সিদ্ধান্ত
    (নিচে ধাপ ৮) ব্যবহারকারীর সাথে নিশ্চিত করা
```

---

## Core Accounting Rules (Doc 2 থেকে — সব কোডের ভিত্তি)

```txt
Rule 1: Stock/Inventory Purchase কখনো Expense না — এটা Fund Source থেকে
        Inventory Asset-এ রূপান্তর।
        Bank -১০,০০,০০০ → Inventory +১০,০০,০০০ → Expense = ০

Rule 2: Expense শুধু তাই যা Asset তৈরি করে না — rent, salary, internet,
        electricity, marketing, refund, maintenance, subscription

Rule 3: Inventory Purchase সবসময় Fund Source ব্যবহার করবে, Expense
        Account কখনো না

Rule 4 (নতুন, এই ERP-র জন্য): প্রতিটা নতুন টেবিলে company_id +
        BelongsToCompany trait বাধ্যতামূলক (Part 1 নিয়ম)

Rule 5 (নতুন): কোনো Financial Record hard delete হবে না — শুধু status
        পরিবর্তন (cancelled/rejected) + audit log
```

---

## ধাপ ১: বিদ্যমান সিস্টেমের সাথে Coexistence Strategy (সবার আগে এটা)

**⚠️ এটাই সবচেয়ে গুরুত্বপূর্ণ সিদ্ধান্ত — কোড লেখার আগে বুঝতে হবে।**

বিদ্যমান `customer_payments` ও `supplier_payments` production-এ চলছে। Voucher সিস্টেম এদের **replace করবে না** — বরং এদের **সামনে একটা approval/documentation স্তর** হিসেবে বসবে:

```txt
নতুন Flow:
Credit Voucher (Pending) → Verify → Approve
  → Approve হওয়ার মুহূর্তে ERP স্বয়ংক্রিয়ভাবে বিদ্যমান customer_payments-এ
    একটা entry তৈরি করবে (বিদ্যমান overpayment protection, due calculation,
    ledger logic সব অপরিবর্তিত ব্যবহার হবে)
  → voucher.payment_id ফিল্ডে সেই payment-এর reference থাকবে

Debit Voucher (Pending) → Approve
  → expense হলে বিদ্যমান expenses টেবিলে entry
  → supplier payment হলে বিদ্যমান supplier_payments-এ entry
  → voucher শুধু approval+documentation+audit স্তর

সুবিধা:
- বিদ্যমান রিপোর্ট, due calculation, ledger — কিছুই ভাঙবে না
- Voucher বন্ধ করলেও (feature flag) পুরনো flow কাজ করবে
- Migration লাগবে না — পুরনো payments অপরিবর্তিত থাকবে

Backward compatibility সিদ্ধান্ত (ব্যবহারকারীর সাথে নিশ্চিত করুন):
- সরাসরি payment creation (voucher ছাড়া) কি বন্ধ হবে, নাকি দুটোই চলবে?
- সুপারিশ: প্রথম ১-২ মাস দুটোই চালু (voucher optional), staff অভ্যস্ত
  হলে company settings-এ "require_voucher_for_payments" toggle অন করা
```

---

## ধাপ ২: Database Migrations

### 2.1 `fund_sources` টেবিল

```php
Schema::create('fund_sources', function (Blueprint $table) {
    $table->id();
    $table->foreignId('company_id')->constrained()->cascadeOnDelete();
    $table->string('name');                    // "Business Cash", "DBBL Bank", "bKash Personal"
    $table->enum('type', [
        'cash', 'bank', 'mobile_banking', 'wallet', 'petty_cash',
        'owner_investment', 'partner_investment', 'business_profit',
        'bank_loan', 'customer_advance', 'supplier_credit', 'other',
    ]);
    $table->foreignId('account_id')->nullable()->constrained()->nullOnDelete();
        // বিদ্যমান accounts টেবিলের সাথে link — cash/bank/mobile type-এর
        // fund source-গুলো বিদ্যমান Account-এর wrapper, নতুন balance-store না
    $table->decimal('opening_balance', 15, 2)->default(0);
    $table->boolean('is_active')->default(true);
    $table->timestamps();

    $table->index(['company_id', 'type']);
});
```

**⚠️ ডিজাইন সিদ্ধান্ত:** cash/bank/mobile_banking টাইপের fund source **বিদ্যমান `accounts` টেবিলের reference** — নতুন আলাদা balance রাখবে না (দুই জায়গায় balance রাখলে mismatch অনিবার্য)। Balance সবসময় বিদ্যমান Account থেকে পড়া হবে। শুধু capital-জাতীয় source (owner_investment, business_profit, loan) নিজস্ব balance track করবে।

### 2.2 `vouchers` টেবিল (Credit ও Debit একই টেবিলে, type দিয়ে আলাদা)

```php
Schema::create('vouchers', function (Blueprint $table) {
    $table->id();
    $table->foreignId('company_id')->constrained()->cascadeOnDelete();
    $table->string('voucher_number')->unique();   // CV-GAD-20260710-0001 / DV-GAD-...
    $table->enum('type', ['credit', 'debit']);
    $table->enum('status', ['pending', 'verified', 'approved', 'rejected', 'cancelled'])
          ->default('pending');
    $table->enum('transaction_type', [
        'inventory_purchase', 'business_expense', 'capital_investment',
        'owner_withdrawal', 'supplier_payment', 'customer_payment',
        'loan', 'refund', 'asset_purchase', 'fund_transfer', 'other',
    ]);
    $table->decimal('amount', 15, 2);
    $table->string('currency', 3)->default('BDT');

    // পক্ষসমূহ (সবগুলো nullable — type অনুযায়ী প্রাসঙ্গিক)
    $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
    $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
    $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();      // invoice link
    $table->foreignId('purchase_id')->nullable()->constrained()->nullOnDelete();
    $table->foreignId('expense_category_id')->nullable()->constrained()->nullOnDelete();

    // Fund
    $table->foreignId('fund_source_id')->nullable()->constrained()->nullOnDelete();
    $table->foreignId('account_id')->nullable()->constrained()->nullOnDelete();
        // credit-এ: কোন account-এ টাকা এসেছে; debit-এ: কোন account থেকে গেছে

    // Payment details
    $table->string('payment_method')->nullable();    // bkash/nagad/bank_transfer/cash...
    $table->string('transaction_id')->nullable();
    $table->enum('confirmation_source', [
        'telegram', 'whatsapp', 'messenger', 'sms', 'phone_call', 'email', 'manual',
    ])->nullable();

    $table->text('purpose')->nullable();
    $table->text('remarks')->nullable();

    // Workflow tracking
    $table->foreignId('submitted_by')->constrained('users');
    $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
    $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
    $table->dateTime('verified_at')->nullable();
    $table->dateTime('approved_at')->nullable();
    $table->text('rejection_reason')->nullable();

    // Approve হওয়ার পরে তৈরি হওয়া downstream record-এর reference
    $table->string('resulting_model_type')->nullable();  // CustomerPayment/Expense/SupplierPayment
    $table->unsignedBigInteger('resulting_model_id')->nullable();

    $table->timestamps();

    $table->index(['company_id', 'type', 'status']);
    $table->index('transaction_type');
});
```

### 2.3 `voucher_attachments` টেবিল

```php
Schema::create('voucher_attachments', function (Blueprint $table) {
    $table->id();
    $table->foreignId('voucher_id')->constrained()->cascadeOnDelete();
    $table->string('file_path');       // payment screenshot, bill, receipt, slip
    $table->string('file_type')->nullable();
    $table->string('label')->nullable();  // "Payment Screenshot", "Vendor Bill"
    $table->timestamps();
});
```

### 2.4 `fund_transfers` টেবিল

```php
Schema::create('fund_transfers', function (Blueprint $table) {
    $table->id();
    $table->foreignId('company_id')->constrained()->cascadeOnDelete();
    $table->string('transfer_number')->unique();
    $table->foreignId('from_account_id')->constrained('accounts');
    $table->foreignId('to_account_id')->constrained('accounts');
    $table->decimal('amount', 15, 2);
    $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
    $table->text('remarks')->nullable();
    $table->foreignId('requested_by')->constrained('users');
    $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
    $table->dateTime('approved_at')->nullable();
    $table->timestamps();
});
```

### 2.5 `purchases` টেবিলে নতুন কলাম (Funding Source)

```php
Schema::table('purchases', function (Blueprint $table) {
    $table->json('funding_sources')->nullable();
    // format: [{"fund_source_id": 1, "amount": 2000000},
    //          {"fund_source_id": 4, "amount": 8000000}]
    // Multiple source সাপোর্টের জন্য JSON — Doc 2-এর popup design অনুযায়ী
});
```

---

## ধাপ ৩: Models

```txt
app/Models/FundSource.php        — BelongsToCompany; balance() মেথড যা
                                    linked Account থেকে বা নিজস্ব ledger থেকে পড়ে
app/Models/Voucher.php           — BelongsToCompany; isCredit()/isDebit();
                                    attachments(); resultingModel() (morphTo-স্টাইল)
app/Models/VoucherAttachment.php
app/Models/FundTransfer.php      — BelongsToCompany
```

সব model-এ Part 1-এর `BelongsToCompany` trait ব্যবহার — নতুন isolation pattern তৈরি করা যাবে না, এবং `MultiCompanyIsolationTest`-এ এই নতুন model-গুলো যুক্ত করতে হবে (Part 1.10-এর নিয়ম: "isolation contract test covers every company-owned model" সত্য রাখতে হবে)।

---

## ধাপ ৪: Core Service — `VoucherService`

```php
// app/Services/VoucherService.php — মূল business logic

মেথডসমূহ:

submitCreditVoucher(array $data): Voucher
  - voucher_number জেনারেট (CV-{company_prefix}-{date}-{seq},
    বিদ্যমান invoice numbering pattern অনুসরণ করে)
  - status = pending
  - notification পাঠানো verify-permission-ওয়ালা user-দের

verify(Voucher $voucher): void
  - শুধু pending → verified
  - verified_by, verified_at সেট

approve(Voucher $voucher): void
  - শুধু verified → approved (credit-এর জন্য); debit-এ pending → approved
    সরাসরি অনুমোদিত হতে পারে (ব্যবহারকারীর সাথে নিশ্চিত করুন দুই-ধাপ
    দরকার কিনা debit-এও)
  - DB::transaction-এর ভেতরে:
    ক) transaction_type অনুযায়ী accounting logic নির্বাচন (নিচের matrix)
    খ) resulting record তৈরি (customer_payment/expense/supplier_payment)
    গ) voucher-এ resulting_model reference সেভ
    ঘ) Money Receipt জেনারেট (credit হলে)
  - ⚠️ Balance আপডেট বিদ্যমান Account/ledger logic দিয়েই হবে — এই service
    নিজে সরাসরি balance কলাম আপডেট করবে না

reject(Voucher $voucher, string $reason): void
cancel(Voucher $voucher): void  — শুধু pending অবস্থায়
```

### Transaction Type → Accounting Logic Matrix

```txt
transaction_type          → approve হলে যা ঘটবে
─────────────────────────────────────────────────
customer_payment          → customer_payments entry (বিদ্যমান logic),
                             invoice due আপডেট, Money Receipt
inventory_purchase        → purchase-এর funding_sources অনুযায়ী fund
                             deduct; Expense তৈরি হবে না (Rule 1!)
business_expense          → expenses entry (বিদ্যমান logic)
supplier_payment          → supplier_payments entry (বিদ্যমান logic)
capital_investment        → FundSource (owner_investment ইত্যাদি) balance+,
                             linked Account balance+ — Investor module-এর
                             সাথে integration ধাপ ৮ দেখুন
owner_withdrawal          → Account balance−, owner capital− ; Expense না
refund                    → customer refund entry + Account−
fund_transfer             → FundTransfer flow ব্যবহার করুন (আলাদা টেবিল)
asset_purchase            → Account−, asset হিসেবে রেকর্ড (Expense না)
loan                      → Account+, loan liability track
```

---

## ধাপ ৫: Approval Integration (নতুন approval সিস্টেম বানাবেন না)

```txt
⚠️ কঠোর নিয়ম: এই মডিউল 03_TASK_APPROVAL_WORKFLOW_MODULE_PLAN.md-এর
ApprovalGateService pattern ব্যবহার করবে। যদি সেই মডিউল এখনো implement
না হয়ে থাকে, তাহলে শুধু ApprovalGateService + approval_requests +
approval_rules অংশটুকু আগে implement করুন (পুরো Task module লাগবে না)।

approval_rules-এ নতুন request_type যুক্ত হবে:
  'credit_voucher'  — threshold_amount সহ (যেমন ৫০,০০০+ টাকার credit
                      voucher-এ manager approval)
  'debit_voucher'   — (যেমন ১০,০০০+ টাকার debit-এ approval)
  'fund_transfer'
  'owner_withdrawal' — সবসময় owner-level approval

Customer Risk module-এ যে approval-request pattern ইতিমধ্যে কোডে আছে,
সেটার সাথে সামঞ্জস্য রাখুন — ভবিষ্যতে তিনটা (risk, task, voucher) এক
ApprovalGateService-এ একীভূত হওয়ার পথ খোলা রাখতে হবে।
```

---

## ধাপ ৬: Money Receipt Generation

```txt
approve হওয়ার পরে credit voucher-এর জন্য:
- dompdf দিয়ে PDF receipt (বিদ্যমান PDF export infrastructure ব্যবহার
  করুন — Part 0-এ PDF export এখন সম্পন্ন বলে confirmed)
- Receipt-এ: company logo/branding (company-wise, বিদ্যমান invoice
  branding pattern), voucher number, client, amount, payment method,
  date, "Verified & Approved" স্ট্যাম্প-স্টাইল টেক্সট
- Shareable link: signed URL route (Laravel signed routes) —
  /receipt/{voucher_number}?signature=... — login ছাড়া দেখা যাবে কিন্তু
  guess করা যাবে না
- WhatsApp share বাটন (wa.me link, বিদ্যমান quotation share pattern)
```

---

## ধাপ ৭: Filament Resources

```txt
app/Filament/Resources/Vouchers/       — মূল resource
  - টেবিলে: voucher_number, type badge (Credit=সবুজ/Debit=লাল), amount,
    transaction_type, status badge, submitted_by, তারিখ
  - Filter: type, status, transaction_type, date range
  - Actions: Verify (pending-এ), Approve (verified-এ), Reject (reason সহ),
    Print Receipt (approved credit-এ), View Attachments
  - Form: type অনুযায়ী conditional fields (credit হলে customer+invoice,
    debit হলে expense category+vendor) — Filament-এর ->visible(fn (Get $get)...)

app/Filament/Resources/FundSources/    — CRUD + balance display (read-only,
                                          linked Account থেকে)
app/Filament/Resources/FundTransfers/  — transfer request + approve action

Filament Pages:
app/Filament/Pages/FinanceDashboard.php — Doc 1+2-এর dashboard items:
  Today's Credit/Debit, Pending Voucher count, Pending Approval count,
  Total Cash/Bank/Mobile Banking balance, Fund Flow summary,
  Current Inventory Value, Total Investment, Owner Withdrawal,
  Recent Vouchers টেবিল
  ⚠️ "All Companies" view-এ এই dashboard read-only — voucher create/approve
  action disable (Part 1-এর বিদ্যমান safeguard pattern)
```

### Purchase Form Integration (Funding Source)

```txt
বিদ্যমান PurchaseForm-এ নতুন section:
  Repeater: funding_sources — প্রতি লাইনে FundSource select + amount
  Validation: সব লাইনের amount-এর যোগফল == purchase grand total
  Single source হলে এক লাইন; multiple হলে একাধিক (Doc 2-এর popup-এর
  বদলে Filament-native repeater — একই কাজ, কম জটিলতা)
```

---

## ধাপ ৮: Investor/Mudarabah Module-এর সাথে Integration

```txt
⚠️ Overlap সমাধান — দুটো আলাদা investment সিস্টেম থাকবে না:

Doc 2-এর "Investment Management" (Owner/Partner Investment) এবং
01_INVESTOR_MUDARABAH_MODULE_PLAN.md-এর investments টেবিল — এই দুটোর
সম্পর্ক:

সিদ্ধান্ত (ব্যবহারকারীর সাথে নিশ্চিত করুন):
ক) Mudarabah module-এর `investments` টেবিলই একমাত্র investment রেকর্ড —
   Voucher module-এর capital_investment transaction_type শুধু সেটার
   funding/approval workflow স্তর। Investment approve হলে Mudarabah
   investments-এ entry হয় + FundSource balance বাড়ে। — সুপারিশকৃত
খ) Owner-এর নিজস্ব capital investment (Mudarabah project-এর বাইরে)
   আলাদা রাখা — সেক্ষেত্রে FundSource-এর owner_investment type শুধু
   owner capital track করবে, Mudarabah investor-দের টাকা আলাদা থাকবে

Mudarabah module এখনো implement হয়নি — তাই এই Voucher module আগে হলে,
capital_investment logic এমনভাবে লিখুন যাতে পরে Mudarabah-র investments
টেবিলের সাথে link করা যায় (voucher.resulting_model_type প্যাটার্ন এটাই
সমাধান করে)।
```

---

## ধাপ ৯: Automatic Voucher (অন্য মডিউল থেকে)

```txt
বিদ্যমান মডিউল থেকে financial event হলে স্বয়ংক্রিয় voucher:

Purchase confirm         → auto Debit Voucher (inventory_purchase type)
Expense create           → auto Debit Voucher (business_expense type)
Supplier payment         → auto Debit Voucher (supplier_payment type)
Storefront order payment → auto Credit Voucher (customer_payment type)

Implementation: Model observer বা event listener (PurchaseObserver ইত্যাদি)।
Auto voucher-ও একই approval workflow অনুসরণ করবে (Doc 1-এর নিয়ম)।

⚠️ Feature flag: company settings-এ "auto_voucher_enabled" toggle —
শুরুতে off রেখে এক company-তে টেস্ট করে ধাপে ধাপে চালু করা (Release
& Update Safety নিয়ম অনুযায়ী)।
```

---

## ধাপ ১০: Notification

```txt
Filament database notification (বিদ্যমান):
  voucher created → verify-permission users
  verified → approve-permission users
  approved/rejected → submitter

Email/Telegram/WhatsApp notification — Doc 1-এ আছে কিন্তু এখনকার স্কোপে
শুধু ERP-internal notification। External channel notification মূল Master
Plan-এর Notification Automation ফেজের অংশ — এখানে duplicate করবেন না,
শুধু event hook রাখুন যাতে পরে external channel যুক্ত করা সহজ হয়।
```

---

## ধাপ ১১: Permissions

```txt
বিদ্যমান gate-based permission pattern-এ নতুন permission:
  voucher.create, voucher.view, voucher.view_all, voucher.verify,
  voucher.approve, voucher.reject, voucher.cancel,
  fund_source.manage, fund_transfer.create, fund_transfer.approve,
  finance.dashboard

Role mapping (সুপারিশ):
  Sales Staff     → voucher.create (credit), voucher.view (own)
  Accountant      → + voucher.verify, fund_transfer.create
  Manager         → + voucher.approve (threshold-এর মধ্যে), finance.dashboard
  Super Admin     → সব + owner_withdrawal approve
```

---

## ধাপ ১২: Tests

```txt
tests/Feature/VoucherWorkflowTest.php
[ ] Credit voucher: pending → verified → approved flow
[ ] Approve হলে customer_payments-এ সঠিক entry + invoice due আপডেট
[ ] Reject-এ reason বাধ্যতামূলক, কোনো payment তৈরি হয় না
[ ] pending ছাড়া অন্য status-এ edit ব্লক

tests/Feature/AccountingRulesTest.php  ← সবচেয়ে গুরুত্বপূর্ণ
[ ] inventory_purchase voucher approve হলে Expense তৈরি হয় NA (Rule 1)
[ ] inventory_purchase-এ fund deduct + inventory value ঠিক থাকে
[ ] owner_withdrawal Expense তৈরি করে না
[ ] fund_transfer-এ এক account কমে আরেকটা বাড়ে, মোট অপরিবর্তিত
[ ] Multiple funding source-এর যোগফল purchase total-এর সমান না হলে
    validation ব্যর্থ হয়

tests/Feature/VoucherIsolationTest.php
[ ] Company A-র user company B-র voucher দেখতে/approve করতে পারে না
[ ] MultiCompanyIsolationTest-এ নতুন model যুক্ত করা হয়েছে
```

---

## ধাপ ১৩: ম্যানুয়াল স্মোক টেস্ট চেকলিস্ট

```txt
[ ] Credit voucher তৈরি → screenshot attach → verify → approve →
    customer payment তৈরি হয়েছে + Money Receipt PDF ডাউনলোড হয় যাচাই
[ ] Receipt-এর shareable link লগইন ছাড়া খোলে, ভুল signature-এ 403
[ ] ১ লাখ টাকার test purchase-এ dual funding source (cash ২০% + bank ৮০%)
    দিয়ে approve — দুটো account-ই সঠিক amount কমেছে, Expense রিপোর্টে
    কিছু যোগ হয়নি যাচাই
[ ] Fund transfer bank→bKash — দুই account-এর balance এবং মোট যাচাই
[ ] Threshold-এর উপরের debit voucher-এ approval request তৈরি হয় যাচাই
[ ] "All Companies" view-এ voucher create/approve বাটন disabled যাচাই
[ ] Finance Dashboard-এর সংখ্যাগুলো ম্যানুয়াল হিসাবের সাথে মেলে যাচাই
```

---

## Build Order-এ অবস্থান

```txt
বিদ্যমান সম্পন্ন ভিত্তি (Accounts, Payments, Purchase, Multi-Company)
        ↓
ApprovalGateService core (03 ফাইল থেকে শুধু approval অংশ)
        ↓
এই মডিউল: ধাপ ২→৩→৪→৫ (core) → ৬→৭ (UI/receipt) → ৯→১০ (automation)
        ↓
Investor/Mudarabah module (ধাপ ৮-এর integration decision সহ)
```

**এজেন্টের জন্য শেষ নির্দেশ:** এই মডিউল সরাসরি টাকার হিসাব স্পর্শ করে। প্রতিটা ধাপের পরে টেস্ট চালান, এবং ধাপ ১-এর coexistence সিদ্ধান্ত ও ধাপ ৮-এর Investor integration সিদ্ধান্ত — এই দুটো ব্যবহারকারীর explicit confirmation ছাড়া অনুমান করে implement করবেন না।
