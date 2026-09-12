# Investor / Mudarabah Module — v3 Gap Analysis & Remaining Work

> **উৎস:** `01_INVESTOR_MUDARABAH_MODULE_PLAN.md` (মূল প্ল্যান v2)। এই ফাইলটি একটি **অডিট + gap analysis**, ২০২৬-০৯-০৭ তারিখে করা। মালিকের দেওয়া ৫টি বাস্তব ডকুমেন্ট থেকে যাচাই করা হয়েছে:
>
> 1. `Profit of investment-01-26.xlsx` — master settlement sheet + ১৯টি per-investor sheet
> 2. per-investor PDF রিপোর্ট (১৯টি, `{Name}_Pro-inv-01-26.pdf`)
> 3. `Invesotr Dit Paper.pdf` — investor ডিট পেপার (৩টি ৳১০০ স্ট্যাম্পে চুক্তিপত্র + security চেক)
> 4. `_চুক্তি.docx` — channel partner (আবদুল মোহাইমিন পাটোয়ারী) ↔ কোম্পানি চুক্তি
> 5. `Investment Details.pdf` — investor register sheet (investment phase শেষে)
>
> **ক্লায়েন্ট:** Tasneem Knitting Industry (এই মডিউলের বাস্তব ব্যবহারকারী)। কোম্পানি: প্রোপ্রাইটর মোঃ মামুনুর রশিদ।

---

## ০. Owner Answers (২০২৬-০৯-০৭) — resolved

| # | প্রশ্ন | উত্তর | প্রভাব |
|---|---|---|---|
| Q1 | এক project-এ একাধিক investor group? | **না।** এই ফেজের target ছিল ৬২ লাখ, investor জমা দিয়েছে ৫৩ লাখ, **বাকি ৯ লাখ company নিজে পূরণ করেছে।** | **P1.7 (নতুন)** — `company_contribution_amount`। company gap-fill অংশটাও investor-pool rate-এ profit পায় (তার ৫০%-এর অতিরিক্ত)। "Groups" schema বানানোর দরকার নেই → P2.6 বাদ। |
| Q2 | Direct investor-দের ১০% কে পায়? | **Company রাখবে।** | **Finding #11 RESOLVED** — বর্তমান `SettlementService` লজিক (unallocated channel share → `company_net_amount`) সঠিক। কোনো পরিবর্তন লাগবে না। |
| Q3 | `loss_investor_borne` — profit_share সমানুপাতে নেগেটিভ? | **চুক্তিপত্র অনুযায়ী।** deed ধারা ৪: force-majeure লোকসান investor মূলধন-অনুপাতে বহন করবে। | **P1.3** sketch lock — `profit_share_amount = loss × (investor capital / total capital)` (নেগেটিভ)। |
| Q4 | Investment window hard না soft? | **Soft** — super_admin + যাকে access দেওয়া হবে সে override করতে পারবে। | **P2.3** — নতুন permission `investments.override_investment_window` + logged reason। |
| Q5 | Ledger integration কোথায়? | **পরামর্শ চাওয়া হয়েছে।** | **P2.5**-এ নিচে বিস্তারিত সুপারিশ দেওয়া হলো। |
| Q6 | Report/register-এ logo/branding? | **Company logo add করে দেবে।** | **P1.2** — বিদ্যমান `Company.logo` ফিল্ড ব্যবহার করুন (invoice-এর মতো); নতুন admin UI লাগবে না। |

---

## ১. Executive Summary

`ERP_PHASE_ROADMAP.md`-এ Phase 15 **"Done"** লেখা — এটি **ভুল**। সঠিক অবস্থা: **"Core calculation engine সম্পূর্ণ ও টেস্টেড; operational ও legal-compliance layer বাকি।"**

- ✅ **যা কাজ করে:** configurable 40/10/50 split, invested-capital ratio অনুযায়ী payout, rounding-safe remainder, immutable confirmed settlement, company isolation (৮টি মডেল), permission gating, signed Shearing Machine উদাহরণ টেস্ট (`InvestmentSettlementTest` — ১৬ passed)।
- ❌ **যা বাকি:** document management, per-investor report generation, investment window, loss handling, settlement correction path, nominee/witness/contract fields, ledger integration, ও কয়েকটি বাগ।

দুটি চুক্তিপত্রেই (investor deed + channel partner) এমন কিছু শর্ত আছে যা এখন সিস্টেমে **enforce বা capture হয় না** — এগুলো নিছক "nice to have" নয়, চুক্তিবদ্ধ বাধ্যবাধকতা।

---

## ২. বাস্তব ওয়ার্কফ্লো (ডকুমেন্ট থেকে নিশ্চিত)

```txt
1. Investment Phase খোলা
   — কোম্পানি একটা project (মেশিন ডিল) ঠিক করে, ~১০ দিনের window দেয়
   — investor-রা এই সময়ে টাকা দেয়, প্রতি investor:
       • ৩টা ৳১০০ স্ট্যাম্পে মুদারাবা চুক্তিপত্র সই
       • investment এর সমপরিমাণ একটা কোম্পানি চেক security হিসেবে পায় (undated)
       • একজন গ্যারান্টর + একজন নমিনি চুক্তিতে যুক্ত করে

2. Register Freeze
   — window শেষে সব investor তথ্য একটা register sheet-এ (Investment Details)
   — কলাম: SL, নাম, পিতা, ঠিকানা, ফোন, NID/Passport, নমিনির নাম/NID/ফোন,
            বিনিয়োগ ৳, ৩টা স্ট্যাম্প নং, ১টা চেক নং

3. Trade Cycle (~৬০ দিন / ১.৫–২ মাস)
   — কোম্পানি মেশিন import করে, বিক্রি করে
   — সব voucher/লেনদেন একটা WhatsApp গ্রুপে আপলোড (চুক্তির শর্ত)

4. Settlement
   — Net Profit = Selling − (Landed cost + Local expense), শুধু সরাসরি খরচ
   — 40% investor pool / 10% channel partner / 50% company
   — investor payout = (তার মূলধন লাখে) × (rate per lac)
       rate per lac = investor_pool ÷ (মোট মূলধন ÷ ১,০০,০০০)   [বাস্তবে ৩৫১৩ টাকা]
   — annualized yearly = (investor_pool ÷ মোট মূলধন) × (৩৬০ ÷ cycle_days) × ১০০
       [বাস্তবে ২১.০৮%, ৩৬০-দিন বছর]

5. Per-Investor Report + Payout
   — প্রত্যেককে আলাদা PDF: full cost breakdown + net profit + 40% pool +
     rate per lac + "আপনি X লাখ × rate = Y টাকা" + ব্যাংক অ্যাকাউন্ট + Paid on তারিখ
   — টাকা পাঠানো হয় investor-এর (বা নমিনি/alternate) ব্যাংক অ্যাকাউন্টে

6. পরের Cycle
   — investor চাইলে profit তুলে নেয়, অথবা re-invest করে (পুঁজি+লাভ বাড়ে)
   — কোম্পানি ~২ মাস পর নতুন project খোলে; investor-রা roll করে
   — প্রত্যাহার/বাতিলে ৬০ দিন আগে লিখিত নোটিশ দিতে হয়
```

---

## ৩. Prioritized Gap List

প্রতিটি আইটেমে: **কী** / **কেন** (চুক্তির ভিত্তি সহ) / **ERP পরিবর্তনের রূপরেখা**।

### 🔴 P1 — Legal compliance ও core operational (এগুলো ছাড়া মডিউল বাস্তবে ব্যবহারযোগ্য নয়)

---

#### P1.1 — Document Management (attachment infrastructure)

**কী:** মডিউলের কোথাও ডকুমেন্ট আপলোড/সংরক্ষণের ব্যবস্থা নেই — একমাত্র `investor_security_instruments.contract_document_path` ছাড়া।

**কেন:** investor deed **ধারা ৩** ও channel partner চুক্তি **ধারা ২–৩**: "সব voucher/লেনদেন WhatsApp গ্রুপে আপলোড; ক্রয়-বিক্রয়ের কাগজ ও খরচের রিসিট লিখিতভাবে সংরক্ষণ করে investor-দের প্রদর্শন করতে হবে।" এটি transparency-র চুক্তিবদ্ধ শর্ত। মালিক সরাসরি বলেছেন: *"ডকুমেন্ট আপলোড করে রাখার অপশন নাই।"*

**রূপরেখা:** বিদ্যমান `VoucherAttachment` প্যাটার্ন অনুসরণ করুন (`app/Models/VoucherAttachment.php` + `VoucherAttachmentDownloadController` + private storage guard)।

```php
// নতুন migration: investment_documents টেবিল
Schema::create('investment_documents', function (Blueprint $table) {
    $table->id();
    $table->foreignId('company_id')->constrained()->cascadeOnDelete();
    $table->morphs('documentable');   // InvestmentProject | ProjectSettlement |
                                      // SettlementPayout | ProjectCostItem | Investment
    $table->string('file_path');
    $table->string('file_type')->nullable();
    $table->enum('category', [
        'settlement_sheet', 'investor_report', 'cost_receipt',
        'purchase_document', 'bank_slip', 'signed_contract', 'other',
    ])->default('other');
    $table->string('label')->nullable();
    $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();
    $table->index(['company_id', 'documentable_type', 'documentable_id']);
});
```

- মডেল: `InvestmentDocument` — `BelongsToCompany` + `saving()` hook-এ private-path guard (VoucherAttachment-এর মতো)।
- `App\Providers\AppServiceProvider`-এ `AuditObserver` তালিকায় যোগ করুন।
- `MultiCompanyIsolationTest`-এ যোগ করুন।
- নতুন `InvestmentDocumentDownloadController` (VoucherAttachmentDownloadController-এর কপি) + route।
- Filament: `InvestmentProjectResource`, `ProjectSettlementResource`, `PayoutsRelationManager`, `CostItemsRelationManager`-এ `DocumentsRelationManager` বা repeatable FileUpload।
- Storage: `CompanyStorageService::privateDirectory(..., 'investment-documents')`।

**⚠️ Cost receipt বাধ্যতামূলকতা:** `project_cost_items`-এ যোগ করুন — হয় `purchase_id` link **অথবা** অন্তত একটি `investment_documents` (category `cost_receipt`) থাকতে হবে, নইলে settlement block হবে (P1.4 দেখুন)। channel partner চুক্তি ধারা ২: "প্রমাণ সংগ্রহ করে লাভ দেখাতে হবে।"

---

#### P1.2 — Per-Investor Report + Project Register Generation

**কী:** সিস্টেম থেকে (ক) প্রতি investor-এর profit রিপোর্ট, (খ) project-এর investor register — কোনোটাই generate করা যায় না। মালিক এখন হাতে Excel/PDF বানান (প্রতি cycle-এ ১৯+ জনের জন্য)।

**কেন:** deed **ধারা ৩**: "যারা বিনিয়োগ করবেন তাদের নাম বা ছদ্ম নাম দিয়ে বিনিয়োগের পরিমাণ ও তারিখ উল্লেখ পূর্বক একটি ডকুমেন্ট তৈরি করা হবে।" — চুক্তিবদ্ধ deliverable। মালিক: *"প্রত্যেককে ইন্ডিভিজুয়ালি রিপোর্ট দেয়া হয়।"*

**রূপরেখা:** সব ডেটা ইতিমধ্যে মডেলে আছে (`project_cost_items`, `project_settlements`, `settlement_payouts` — bank details সহ)। শুধু render layer দরকার।

- **Per-investor report** (`SettlementPayout` per row) — Blade/PDF view:
  - Header: project_code + deal_reference (মেশিন নাম)
  - Landed cost breakdown (line items + total) — `costItems where category=landed_cost`
  - Selling Amount (`settlement.total_revenue`)
  - Gross Profit = Selling − Landed cost (+ %)  ← **নতুন derived, এখন কোথাও দেখানো হয় না**
  - Local expense breakdown (+ total)
  - Net Profit (+ %)
  - "40% to investors" = `settlement.investor_pool_amount`
  - Per-cycle % + **Annualized yearly %** (P1.5-এর সংশোধিত formula) + **rate per lac**
  - "{investor.display_name ?? name} invested-{amount/1e5} lac × {rate} = {profit_share_amount}"
  - Bank: `recipient_bank_name / recipient_branch / recipient_account_number`
  - "Paid on {paid_at}" (বা "Payment pending")
- **Project register** (`InvestmentProject`) — table view / PDF:
  - কলাম: SL, নাম, পিতা (guardian_name), ঠিকানা, ফোন, NID/Passport, নমিনির নাম/NID/ফোন, বিনিয়োগ ৳, স্ট্যাম্প নং, চেক নং, মোট
- Filament: `ViewInvestmentProject` ও `ViewProjectSettlement`-এ header action "Download Register" / "Download All Reports (zip)"; `PayoutsRelationManager`-এ per-row "Download Report"।
- বিদ্যমান PDF infra: `app/Http/Controllers/Admin/ReportPdfController.php`, `InvoiceDesignTest` প্যাটার্ন দেখুন।
- **Branding (Q6):** report/register header-এ বিদ্যমান `Company.logo` ফিল্ড ব্যবহার করুন (invoice যেভাবে করে) — company logo Filament Company settings থেকেই manageable, নতুন UI লাগবে না। মালিক logo add করে দেবে।
- **⚠️ Rendering:** dompdf (`barryvdh/laravel-dompdf`) ব্যবহার করা হয়নি — এটি বাংলা যুক্তাক্ষর ঠিকমতো render করে না (complex-text shaping নেই)। বদলে `orders/print.blade.php`-এর প্যাটার্নে **self-contained printable HTML** — নতুন ট্যাবে খোলে, Print বাটন / browser "Save as PDF" (মালিক এখন যেভাবে Excel→PDF করেন, একই workflow), Android app-এ `ZzPrintBridge` সাপোর্ট। বাংলা font stack: Noto Sans Bengali / Nikosh / SolaimanLipi / system।
  - Route: `investments.reports.investor-payout` (per `SettlementPayout`), `investments.reports.project-register` (per `InvestmentProject`)
  - Controller: `InvestmentReportController` (`auth` middleware + `investments.view` + company-scope check)
  - Views: `resources/views/investments/reports/` (investor-payout, project-register, partials/report-styles, partials/print-script)
  - Filament: `PayoutsRelationManager` per-row "Report"; `ViewInvestmentProject` + `ViewProjectSettlement` "Investor Register" header action
  - Report-এ investor-এর `display_name` (ছদ্মনাম) থাকলে সেটা দেখায় (`Investor::reportName()`); register আসল নাম রাখে।

---

#### P1.3 — Loss Handling (চুক্তির ধারা ৪ অনুযায়ী)

**কী:** `SettlementService` net profit নেগেটিভ হলে hard `RuntimeException` ছুঁড়ে দেয় ([`app/Services/Investment/SettlementService.php:42`](app/Services/Investment/SettlementService.php))। লোকসানি ডিল record বা close করার কোনো উপায় নেই — project চিরকাল `closed`-এ আটকে থাকে।

**কেন:** এটি "undefined rule" নয় — **দুটি চুক্তিতেই নিয়ম লেখা আছে**:
- Investor deed **ধারা ৪** + channel চুক্তি **ধারা ৪**: প্রাকৃতিক দুর্যোগ / জাহাজডুবি / রোড এক্সিডেন্ট / নিয়ন্ত্রণ-বহির্ভূত ঘটনায় লোকসান → **investor (মূলধনের মালিক) বহন করবে** (মূলধন অনুপাতে)।
- Investor deed **ধারা ২** + channel চুক্তি **ধারা ৪**: ম্যানেজারের **স্পষ্ট গাফলতি / দায়িত্বহীনতা / চুক্তিভঙ্গে** লোকসান → **ম্যানেজার বহন করবে**; গাফলতিতে মাল খোয়া গেলে → **পুরো মূলধন investor-দের ফেরত (জরিমানা)**।

**রূপরেখা:**

```php
// project_settlements-এ:
$table->enum('outcome', ['profit', 'loss_investor_borne', 'loss_manager_borne'])
      ->default('profit');
$table->text('loss_reason')->nullable();
```

- `SettlementService::calculateAndSettle()`-এ `$outcome` প্যারামিটার:
  - `profit` — বর্তমান লজিক।
  - `loss_investor_borne` — net_profit নেগেটিভ, `investor_pool_amount = net_profit × investor_share%` (নেগেটিভ, মূলধন ক্ষয়); প্রতি investor-এর `total_payout = principal + (negative profit_share)` (মূলধনের কম ফেরত)। channel/company payout ০।
  - `loss_manager_borne` — investor-দের পুরো মূলধন ফেরত (`total_payout = principal`, `profit_share_amount = 0`); লোকসান company_net_amount-এ নেগেটিভ।
- Filament settle action-এ negative revenue হলে outcome + reason ইনপুট চাওয়া হবে, `requiresConfirmation` + double-confirm।
- `settle` permission (super_admin only) — একই থাকবে।
- টেস্ট: তিনটা outcome path আলাদাভাবে।

**✅ Q3 resolved — চুক্তিপত্র অনুযায়ী:**
- `loss_investor_borne` (deed ধারা ৪ — force majeure): প্রতি investor `profit_share_amount = -round($loss × (তার মূলধন / মোট মূলধন), 2)`, `total_payout = principal + profit_share_amount` (কম ফেরত)। company gap-fill অংশও একই অনুপাতে লোকসান বহন করে। channel payout ০।
- `loss_manager_borne` (deed ধারা ২ — ম্যানেজারের গাফলতিতে মাল খোয়া): সব investor পুরো principal ফেরত (`profit_share_amount = 0`), পুরো লোকসান `company_net_amount`-এ নেগেটিভ।

---

#### P1.4 — Settlement Correction / Void Path

**কী:** Settlement সাথে সাথেই `confirmed` + immutable ([`app/Models/ProjectSettlement.php:19`](app/Models/ProjectSettlement.php)), `draft` স্টেপ নেই, void/reversal নেই, project `settled`-এ lock, resource-এ `canCreate/canEdit/canDelete = false`। ভুল revenue/cost/investor list দিয়ে settle করলে **super_admin-ও ঠিক করতে পারে না**।

**কেন:** আর্থিকভাবে স্পর্শকাতর। মালিক হাতে Excel-এ কাজ করেন যেখানে ভুল সংশোধন করা যায় — সিস্টেমে সেই নিরাপত্তা জাল না থাকলে এটি একটি regression।

**রূপরেখা (দুই অংশ):**

1. **Draft review step:**
   ```php
   // project_settlements.status enum: 'draft' যোগ করুন (আগে ছিল, বাদ পড়েছে)
   ->enum('status', ['draft', 'confirmed', 'paid_out'])->default('draft');
   ```
   - `calculateAndSettle()` → `draft` তৈরি করবে, payout schedule সহ।
   - `draft` অবস্থায় amount ফিল্ড editable (immutability guard শুধু `confirmed`/`paid_out`-এ)।
   - নতুন action **"Confirm Settlement"** (super_admin) → `draft → confirmed`, তখন project `settled`।

2. **Void (super_admin only, শুধু কোনো payout `paid` না হলে):**
   ```php
   public function voidSettlement(ProjectSettlement $s, string $reason, int $userId): void
   {
       if ($s->payouts()->where('payment_status', 'paid')->exists()
           || $s->channelPartnerPayouts()->where('payment_status', 'paid')->exists()) {
           throw new RuntimeException('Paid payouts থাকলে void করা যাবে না — reversal entry লাগবে।');
       }
       DB::transaction(function () use ($s, $reason, $userId) {
           $s->payouts()->delete();
           $s->channelPartnerPayouts()->delete();
           $s->project->forceFill(['status' => 'closed'])->saveQuietly();
           app(AuditLogService::class)->record('settlement_voided', $s, $s->toArray(), ['reason' => $reason]);
           $s->delete();
       });
   }
   ```
   - `ProjectSettlementResource`-এ header action, `settle` permission।

**Roadmap:** "settlement reversal" future work হিসেবে আছে — এটাকে P1-এ তুলে আনুন (কারণ কোনো escape hatch নেই)।

---

#### P1.5 — Annualized Return Formula সংশোধন + Rate Per Lac

**কী:** [`SettlementService.php:52`](app/Services/Investment/SettlementService.php) হিসাব করে:
```php
round(($netProfit / $totalInvested) * (365 / $trade_cycle_days) * 100, 2)
```
Project-01-এ এটি দেয় **~৫৩%**। কিন্তু মালিকের sheet investor-কে দেখায় **২১.০৮%**।

**কেন (settlement sheet থেকে):** বাস্তব formula =
```txt
annualized_yearly = (investor_pool_amount / total_invested) × (360 / trade_cycle_days) × 100
                  = (217823.2 / 6200000) × (360 / 60) × 100 = 21.08%
```
পার্থক্য: (১) **net-profit basis ❌ → investor-pool basis ✅**, (২) **৩৬৫ দিন ❌ → ৩৬০ দিন ✅**। বর্তমান সংখ্যাটি investor communication-এ ভুল ও বিভ্রান্তিকর।

**রূপরেখা:**
```php
// SettlementService-এ:
$annualizedReturn = $lockedProject->trade_cycle_days
    ? round(($investorPool / $totalInvested) * (360 / $lockedProject->trade_cycle_days) * 100, 2)
    : null;

$ratePerLac = round($investorPool / max($totalInvested / 100000, 0.01), 2); // নতুন
```
- `project_settlements`-এ `rate_per_lac` কলাম যোগ করুন (বা report-এ derive করুন — stored হলে immutability guard-এ যোগ করুন)।
- `annualized_return_percent` decimal(8,2) যথেষ্ট (২১.০৮ ঠিক আছে)।
- বিদ্যমান টেস্ট `test_configured_split_costs_payout_ratio_and_annualized_return_are_calculated`-এ `608.33` → নতুন মান আপডেট করুন।
- Report ও `ProjectSettlementResource` infolist-এ "per lac" ও সংশোধিত yearly % দেখান।

---

#### P1.6 — Nominee, Guarantor, Contract ও Cheque ফিল্ড

**কী:** register sheet ও deed-এ যে ফিল্ডগুলো আছে, ERP মডেলে নেই।

**কেন:** deed **ধারা ১০** (নমিনি বাধ্যতামূলক), register sheet কলাম, deed signature block (সাক্ষী), deed clause 7 + channel clause 6 (গ্যারান্টর, চেক)।

**রূপরেখা:**

```php
// investors টেবিলে ALTER:
$table->date('date_of_birth')->nullable();            // register/channel চুক্তিতে জন্ম তারিখ
$table->string('display_name')->nullable();           // deed ধারা ৩: ছদ্ম নাম
$table->boolean('is_channel_partner')->default(false); // P2.1 দেখুন
$table->renameColumn('nid_number', 'nid_or_passport'); // অথবা label পরিবর্তন
// নমিনি:
$table->string('nominee_name')->nullable();
$table->string('nominee_nid_or_passport')->nullable();
$table->string('nominee_phone')->nullable();
$table->string('nominee_relation')->nullable();
$table->string('nominee_address')->nullable();

// investor_security_instruments টেবিলে ALTER:
$table->date('contract_date')->nullable();
$table->string('contract_reference')->nullable();      // e.g. "Project-01/2026-INV-01"
$table->json('stamp_serial_numbers')->nullable();      // ["9363512","9363513","9363514"] — ৩টা
$table->string('cheque_branch')->nullable();           // এখন শুধু cheque_bank_name
$table->string('cheque_account_number')->nullable();
$table->string('cheque_account_holder')->nullable();   // "TASNEEM KNITTING INDUSTRY"
$table->string('guarantor_address')->nullable();
$table->string('guarantor_relation')->nullable();
$table->boolean('investor_signed_cheque_terms')->default(false); // channel ধারা ৭

// নতুন টেবিল: investment_witnesses (deed signature block)
Schema::create('investment_witnesses', function (Blueprint $table) {
    $table->id();
    $table->foreignId('company_id')->constrained()->cascadeOnDelete();
    $table->foreignId('investment_id')->constrained()->cascadeOnDelete();
    $table->string('name');
    $table->string('address')->nullable();
    $table->string('phone')->nullable();
    $table->date('signed_date')->nullable();
    $table->timestamps();
});
```
- সব নতুন company-owned মডেল → `BelongsToCompany` + `MultiCompanyIsolationTest` + `AuditObserver`।
- Filament: `InvestorResource` form-এ nominee section; `SecurityInstrumentsRelationManager`-এ নতুন ফিল্ড + `WitnessesRelationManager`।

---

#### P1.7 — Company Gap-Fill Contribution (Q1 উত্তর)

**কী:** বাস্তব Project-01-এ target ছিল ৳৬২ লাখ, investor জমা দিয়েছে ৳৫৩ লাখ, **বাকি ৳৯ লাখ company নিজে পূরণ করেছে।** ERP-তে company নিজে একটা project-এ capital position ধরতে পারে না — `investments.investor_id` সবসময় একটা `Investor` row লাগে।

**কেন:** rate per lac ও annualized সবই ৬২ লাখের উপর হিসাব হয়েছে (৩৫১৩ tk/lac × ৬২ ≈ ২১৭,৮২৩)। company-র ৯ লাখও investor-pool rate-এ profit পেয়েছে — যা তার ৫০% net-এর **অতিরিক্ত**, company_net-এ যোগ হয়েছে।

**রূপরেখা:**

```php
// investment_projects টেবিলে ALTER:
$table->decimal('company_contribution_amount', 15, 2)->default(0);
// ঐচ্ছিক note: কেন gap fill হলো
$table->string('company_contribution_note')->nullable();
```

- `InvestmentProject::totalInvested()` → **অপরিবর্তিত** (শুধু external investments; register-এ company থাকে না)।
- নতুন: `InvestmentProject::totalCapitalBase()` = `totalInvested() + company_contribution_amount` — rate/pool distribution-এর জন্য।
- `SettlementService::calculateAndSettle()`:
  ```php
  $externalInvested = round((float) $investments->sum('amount'), 2);
  $companyContribution = round((float) $lockedProject->company_contribution_amount, 2);
  $capitalBase = $externalInvested + $companyContribution;   // rate ও pool distribution

  $investorPool = round($netProfit * (investor_share% / 100), 2);
  $ratePerLac   = round($investorPool / max($capitalBase / 100000, 0.01), 2);

  // external investor payouts — capitalBase অনুপাতে (আগে ছিল totalInvested অনুপাতে)
  // প্রতি investor: round($investorPool * (inv.amount / $capitalBase), 2)
  $externalProfitTotal = Σ(external investor profit shares);

  // ⚠️ channel payout proration — DENOMINATOR = $externalInvested, capitalBase নয়।
  //    company-র নিজের contribution "referred"-ও নয় "direct investor"-ও নয়,
  //    তাই partner-এর ১০% হিসাবে ঢোকে না।
  //    $channelPayout = round($configuredChannelPool * ($partnerReferredCapital / $externalInvested), 2);

  // company gap-fill এর pool-share আপনাআপনি company_net-এ পড়ে:
  $companyNet = round($netProfit - $externalProfitTotal - $channelPayout, 2);
  ```
  - যাচাই (Project-01): netProfit 544,558; ratePerLac = 217,823.20 ÷ 62 ≈ 3,513.28;
    external profit total ≈ 217,823.20 × 53/62 ≈ **186,219**;
    channel (সব investor Mohaimin-এর মাধ্যমে) = 54,455.80 × 53/53 = **54,456**;
    companyNet = 544,558 − 186,219 − 54,456 = **303,883** ≈ 272,279 (৫০%) + 31,617 (৯ লাখের pool share)। ✓ (paise-স্তরের rounding পার্থক্য প্রত্যাশিত)
- **⚠️ রাউন্ডিং-সেফ remainder:** এখন "last investor gets remainder" লজিক investor pool-এর পুরোটা বিলি করে। company contribution থাকলে remainder company gap-fill share-এর সাথে মিলে company_net-এ যাবে — last-investor remainder trick বাদ দিয়ে সরল proportional + `companyNet = netProfit − externalProfitTotal − channelPayout` ব্যবহার করুন।
- Filament: `InvestmentProjectResource` form-এ "Company Contribution" ফিল্ড (P1 section-এ); funding progress badge `capitalBase / target` দেখাবে।
- টেস্ট: `company_contribution_amount > 0` দিয়ে settlement, Project-01 সংখ্যা cross-check।

---

### 🟠 P2 — গুরুত্বপূর্ণ, কিন্তু P1-এর পর

---

#### P2.1 — Channel Partner Modeling

**কী:** channel partner (মোহাইমিন) শুধু একটা `Investor` row (self-ref `channel_partner_id`)। কিন্তু তার নিজের চুক্তি, NID, জন্মতারিখ আছে; তিনি নিজে invest করেন না (register-এ নেই); zero-investment হওয়ায় `InvestorResource::canDelete()` তাকে মুছতে দেয় → referral chain + contracted partner নীরবে হারায়।

**রূপরেখা:**
- `investors.is_channel_partner` flag (P1.6-এ যোগ করা হয়েছে)।
- `investors.partner_agreement_document_path` বা `investment_documents` (category `signed_contract`, documentable = Investor)।
- `InvestorResource::canDelete()`: `! $record->referredInvestors()->exists()` যোগ করুন।
- `InvestorResource` table/filter-এ "Channel Partners" আলাদা করা।
- channel partner-এর payout history view (`channel_partner_payouts` where investor_id)।

#### P2.2 — Bug fixes (কোড অডিট থেকে)

| বাগ | ফাইল | ফিক্স |
|---|---|---|
| বিদ্যমান investor-এ প্রথমবার channel partner assign ভাঙা | [`app/Models/Investor.php:31`](app/Models/Investor.php) | `getOriginal('channel_partner_id') === null` হলে permission/reason চেক skip; `InvestorResource`-এ reason ফিল্ডের `visible`/`required` শর্তও ঠিক করুন |
| channel-partner-only investor delete → chain ভাঙে | [`app/Filament/Resources/Investors/InvestorResource.php:102`](app/Filament/Resources/Investors/InvestorResource.php) | `referredInvestors()->exists()` চেক (P2.1) |
| ~~`ChannelPartnerPayoutsRelationManager::markPaid` sibling ignore করে~~ **FIXED** (P1.4-এর সাথে) | — | দুটো relation manager-ই এখন investor + channel payout দুটোই pending চেক করে, আর শুধু `confirmed` settlement-এ কাজ করে |
| `canDelete` (Manager allow) vs Gate (`delete`→false) inconsistency | [`app/Models/User.php:537`](app/Models/User.php) + resources | একটি বেছে নিন — সম্ভবত Gate-কে delete→`investments.manage` করা |
| investments `closed` অবস্থায় freeze হয় না, শুধু `settled`-এ | [`.../InvestmentsRelationManager.php:26`](app/Filament/Resources/InvestmentProjects/RelationManagers/InvestmentsRelationManager.php) | `in_array($status, ['closed','settled'])` |

#### P2.3 — Investment Window / Phase (Q4 = soft override)

**কী:** project-এ নির্দিষ্ট investment-collection window নেই। মালিক ~১০ দিনের window দেন।

**রূপরেখা:**
```php
// investment_projects-এ:
$table->date('investment_opens_at')->nullable();
$table->date('investment_closes_at')->nullable();

// User::CUSTOM_PERMISSION_OPTIONS + ROLE_PERMISSIONS-এ নতুন:
'investments.override_investment_window' => 'Investments: Add Investment After Window Closes',
```
- `Investment` মডেল `saving()`: নতুন record হলে এবং (`invested_at > project.investment_closes_at` বা `project.status !== 'open'`) হলে —
  - user-এর `investments.override_investment_window` permission না থাকলে `ValidationException` ছুঁড়ুন;
  - permission থাকলে একটি reason field (`override_reason`) বাধ্যতামূলক → audit log-এ যায়।
- **Q4:** super_admin (wildcard) + যাকে আলাদাভাবে `investments.override_investment_window` দেওয়া হবে — তারাই window-পরবর্তী investment যোগ করতে পারবে।
- Project table-এ window badge ("Investment closes in 3 days" / "Window closed")।
- ঐচ্ছিক: scheduled command per company — window শেষ হলে status `open → running` (per-company loop, CLAUDE.md rule)।

#### P2.4 — Notice Period + Withdraw/Reinvest

**কী:** deed **ধারা ৫–৬**, channel **ধারা ৮**: প্রতি cycle-এ withdraw বা reinvest; প্রত্যাহারে ৬০ দিন লিখিত নোটিশ।

**রূপরেখা:**
```php
Schema::create('investor_cycle_elections', function (Blueprint $table) {
    $table->id();
    $table->foreignId('company_id')->constrained()->cascadeOnDelete();
    $table->foreignId('settlement_payout_id')->constrained()->cascadeOnDelete();
    $table->enum('election', ['withdraw', 'reinvest', 'partial_reinvest']);
    $table->decimal('reinvest_amount', 15, 2)->nullable();
    $table->foreignId('next_project_id')->nullable()->constrained('investment_projects')->nullOnDelete();
    $table->timestamps();
});

Schema::create('investment_withdrawal_notices', function (Blueprint $table) {
    $table->id();
    $table->foreignId('company_id')->constrained()->cascadeOnDelete();
    $table->foreignId('investor_id')->constrained()->cascadeOnDelete();
    $table->foreignId('project_id')->nullable()->constrained('investment_projects')->nullOnDelete();
    $table->date('notice_given_at');
    $table->date('effective_at'); // notice_given_at + 60 days
    $table->text('reason')->nullable();
    $table->enum('status', ['pending', 'honored', 'cancelled'])->default('pending');
    $table->timestamps();
});
```
- Reinvest → পরের project-এ auto `Investment` তৈরি (principal + reinvested profit)।

#### P2.5 — Ledger / FundSource Integration (Q5 — সুপারিশ)

**কী:** `SettlementService` ও investment creation কোনো `Voucher` / `FundSource` / `TransactionLedger` entry করে না — investor capital ও payout বইখাতায় অদৃশ্য, finance dashboard-এ আসে না।

**সুপারিশ — বিদ্যমান Voucher + FundSource system-এর উপর দাঁড় করানো (নতুন double-entry engine বানাবেন না):**

ERP-তে ইতিমধ্যে আছে: `Voucher` (transaction type `capital_investment` — "Mudarabah-ready", `resulting_model_type`/`resulting_model_id` pointer, credit/debit, pending→verified→approved workflow, audit), `FundSource` (named pools; "owner/partner investment" type; account-linked types ledger থেকে balance পড়ে)।

```php
// investment_projects টেবিলে ALTER (admin-configurable, per project):
$table->foreignId('receiving_fund_source_id')->nullable()->constrained('fund_sources')->nullOnDelete();
$table->foreignId('payout_fund_source_id')->nullable()->constrained('fund_sources')->nullOnDelete();
// default একটা company setting থেকে আসবে

// Voucher::TRANSACTION_TYPES-এ নতুন:
'investor_payout' => 'Investor Payout (Mudarabah)',
```

**তিনটি money event:**

| Event | Voucher | দিক | ব্যাখ্যা |
|---|---|---|---|
| **Investment received** (`Investment` create হলে) | type `capital_investment`, `resulting_model_type = Investment` | **credit** → `receiving_fund_source_id` | investor-এর টাকা company bank-এ ঢুকল। এটি "Mudarabah investor capital" — company-র liability (ফেরত দিতে হবে)। `capital_investment` type ইতিমধ্যে non-expense হিসেবে treated (`NON_EXPENSE_TRANSACTION_TYPES`)। |
| **Direct cost** (`ProjectCostItem`) | `purchase_id` থাকলে বিদ্যমান Purchase flow ledger hit করে; নইলে type `business_expense` voucher | debit | P1.1-এ receipt বাধ্যতামূলক করা হয়েছে — একই সাথে expense voucher link বাধ্যতামূলক করা যায়। |
| **Payout paid** (`SettlementPayout` / `ChannelPartnerPayout` → `paid`) | type `investor_payout`, `resulting_model_type = SettlementPayout` | **debit** → `payout_fund_source_id`, amount = `total_payout` | note-এ breakdown: principal return (liability কমে) + profit share (Mudarabah profit distribution)। company-র ৫০% net + gap-fill share **retained** — কোনো cash movement নেই, voucher লাগবে না। |
| **Loss** | `loss_investor_borne` → payout voucher-এ কম amount (liability কম ফেরত); `loss_manager_borne` → `business_expense` voucher company-র নামে | — | P1.3 দেখুন |

**Implementation notes:**
- `Investment::created` ও payout `markPaid` action-এ `Voucher` auto-create — **status `pending`** (owner confirmed; কেউ verify/approve করবে বিদ্যমান voucher workflow-এ)।
- **আলাদা liability account লাগবে না** (owner confirmed) — `capital_investment` voucher type-ই যথেষ্ট, এটি ইতিমধ্যে `NON_EXPENSE_TRANSACTION_TYPES`-এ।
- Voucher auto-create failure যেন Investment/payout save fail না করায় — `DB::transaction` + graceful degradation, অথবা queued job (`CompanyContext` set/clear সহ — CLAUDE.md rule)।
- FundSource selection admin-configurable (encrypted না, শুধু dropdown) — hardcode নয়।

*(Q1 উত্তর: এক project-এ একাধিক investor "group" নেই — ৫৩ vs ৬২ লাখ পার্থক্য company gap-fill, P1.7-এ handled। "Groups" schema বাদ।)*

---

### 🟡 P3 — Polish / smaller

- **PII:** `nid_or_passport`, `nominee_nid_or_passport`, `guarantor_nid` plaintext — encryption বা masking বিবেচনা করুন।
- **Gross profit** settlement infolist-এ দেখান (P1.2-এ report-এ আছে)।
- **Test coverage:** settle action UI form path, দুটি "Mark as Paid" flow, permission-denied path, security instrument create/upload; `AdminNavigationClustersTest`-এ Investments cluster যোগ করুন।
- **Inline cheque number** — register view-এ investment row থেকে সরাসরি (এখন আলাদা relation manager)।
- **Nominee multiple phone** — text field, strict validation নয় (register-এ দেখা গেছে)।

---

## ৪. Owner Questions — সব উত্তর পাওয়া গেছে (২০২৬-০৯-০৭)

সব প্রশ্নের উত্তর §0-এ। সারসংক্ষেপ:

```txt
[✓] Q1. একাধিক group নেই → company gap-fill (৯ লাখ)। P1.7 নতুন। P2.6 বাদ।
[✓] Q2. Direct investor-দের ১০% company রাখবে → বর্তমান কোড সঠিক, finding #11 বাদ।
[✓] Q3. loss_investor_borne চুক্তি অনুযায়ী → মূলধন-অনুপাতে নেগেটিভ। P1.3 lock।
[✓] Q4. Soft override → নতুন permission investments.override_investment_window। P2.3।
[✓] Q5. Ledger → বিদ্যমান Voucher/FundSource-এর উপর (P2.5-এ বিস্তারিত সুপারিশ)।
[✓] Q6. Company logo add করে দেবে → বিদ্যমান Company.logo ব্যবহার। নতুন UI নেই।
```

**Q5 ফলো-আপ (২০২৬-০৯-০৭ — উত্তর পাওয়া গেছে):**
- আলাদা "Mudarabah Investor Capital" liability account **লাগবে না** — `capital_investment` voucher type-ই যথেষ্ট।
- Auto-তৈরি voucher **`pending`** থাকবে (কেউ verify করবে), auto-`approved` নয়।

---

## ৫. Suggested Build Order

```txt
Sprint 1 (P1 — legal/operational core):
  ✅ 1. P1.6  — nominee/guarantor/contract/cheque/DOB/display_name ফিল্ড + investment_witnesses
              (migration 2026_09_07_100000 + 100100, models, Filament) — DONE ২০২৬-০৯-০৭
  ✅ 2. P1.7  — company_contribution_amount + SettlementService capitalBase রিফ্যাক্টর — DONE
  ✅ 3. P1.5  — annualized formula fix (৩৬০ দিন + investor-pool basis) + rate_per_lac — DONE
  ✅ 4. P1.1  — investment_documents (polymorphic) + download controller + DocumentsRelationManager
              (projects/settlements/investments/cost-items); cost-proof check at settle (soft — ack করা যায়) — DONE
  ✅ 5. P1.2  — per-investor payout report + project register — **browser-print HTML** (dompdf নয়,
              কারণ dompdf বাংলা conjunct ঠিকমতো render করে না); Company.logo ব্যবহার;
              route + controller + blade + Filament actions — DONE
  ✅ 6. P1.4  — settlement `draft` → `confirmed` → `paid_out` (forward-only, model-enforced);
              `SettlementService::confirmSettlement()` + `voidSettlement()` (super_admin, blocked
              once any payout paid — deletes payouts/docs, project → `closed`, audited);
              markPaid/edit locked until `confirmed` — DONE
  ✅ 7. P1.3  — loss handling: `project_settlements.outcome` (`profit` /
              `loss_investor_borne` / `loss_manager_borne`) + `loss_reason`;
              `loss_investor_borne` → negative profit_share মূলধন-অনুপাতে (deed ধারা ৪),
              `loss_manager_borne` → full principal ফেরত, company net negative;
              settle action-এ outcome Select + reason — DONE

**Sprint 1 সম্পূর্ণ।** Migrations: `2026_09_07_100000/100100/100200/100300`. Full suite green।

Sprint 2 (P2):
  8. P2.2  — বাগ ফিক্স (দ্রুত, স্বয়ংসম্পূর্ণ) — আগে করলেও চলে
  9. P2.1  — channel partner modeling (is_channel_partner, delete guard, agreement doc)
  10. P2.3 — investment window (soft override permission)
  11. P2.4 — notice period + withdraw/reinvest
  12. P2.5 — ledger integration (Voucher/FundSource; account mapping confirm)

Sprint 3:
  13. P3   — PII, polish, test coverage

প্রতিটি আইটেমের পর: affected test files + full `php artisan test` (NO --env flag,
CLAUDE.md rule) + `npm run build` (frontend পরিবর্তন হলে)।
CHANGELOG + UPDATE_NOTES আপডেট (CLAUDE.md rule)।
roadmap Phase 15 → "Partially done" মার্ক করুন এই কাজ শুরুর সাথে সাথে।
কমিট শুধু owner-এর explicit অনুমতিতে; concurrent session আছে — ListAgents + git log চেক।
```

---

## ৬. Verified Facts Reference (audit ২০২৬-০৯-০৭)

```txt
✅ InvestmentSettlementTest — ১৬ passed
✅ ৮টি মডেল MultiCompanyIsolationTest-এ registered ও AuditObserver-এ observed
✅ Settlement immutability (updating guard), duplicate/negative block, lockForUpdate
✅ Permissions: investments.view/manage/settle/manage_channel_partner + Gate::before
✅ Private contract storage + authenticated company-scoped download controller
✅ Shearing Machine উদাহরণ: landed 6,199,942 + local 255,500, net 544,558,
   investor 40% = 217,823.20 — টেস্টে মেলে
❌ Document upload (P1.1) — নেই
❌ Report/register generation (P1.2) — নেই
❌ Loss handling (P1.3) — hard exception
❌ Settlement correction (P1.4) — কোনো path নেই
❌ Annualized formula (P1.5) — ৩৬৫ দিন + net-profit basis (ভুল)
❌ Nominee/witness/stamp/contract-date ফিল্ড (P1.6) — নেই
❌ Ledger integration (P2.5) — নেই
```
