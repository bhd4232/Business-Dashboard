# Module 7: Accounts & Finance Management

## Overview

Full double-entry bookkeeping system with multi-currency support, chart of accounts, journal entries, expense tracking, and financial reporting. All monetary values are maintained in BDT with multi-currency conversion.

## Database Tables

### currencies
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| code | varchar(3) unique | CNY, BDT, USD |
| name | varchar(100) | Chinese Yuan, Bangladeshi Taka, US Dollar |
| symbol | varchar(5) | ¥, ৳, $ |
| is_base | boolean default false | BDT is base currency |
| decimal_places | tinyint default 2 | |
| created_at | timestamp | |
| updated_at | timestamp | |

### exchange_rates
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| from_currency_id | bigint FK currencies.id | |
| to_currency_id | bigint FK currencies.id | |
| rate | decimal(12,6) | 1 from = rate to |
| effective_date | date | |
| source | varchar(50) nullable | manual, api, auto |
| created_by | bigint FK users.id | |
| created_at | timestamp | |

**Unique constraint**: (from_currency_id, to_currency_id, effective_date)

### chart_of_accounts
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| code | varchar(20) unique | Account code: 1000, 2000, etc. |
| name | varchar(255) | Account name |
| type | enum | asset, liability, equity, income, expense |
| sub_type | varchar(50) nullable | current_asset, fixed_asset, current_liability, etc. |
| parent_id | bigint FK nullable chart_of_accounts.id | For hierarchy |
| is_system | boolean default false | System accounts cannot be deleted |
| is_active | boolean default true | |
| created_at | timestamp | |
| updated_at | timestamp | |

### Default Chart of Accounts
```
ASSETS (1000-1999)
  1100  Current Assets
    1110  Cash on Hand
    1120  Cash at Bank - Main
    1130  Cash at Bank - USD
    1140  bKash
    1150  Nagad
    1200  Accounts Receivable
    1210  Wholesale Receivable
    1220  Retail Receivable
  1300  Inventory
    1310  Finished Goods
  1400  Other Current Assets
    1410  Advance to Suppliers

LIABILITIES (2000-2999)
  2100  Current Liabilities
    2110  Accounts Payable
    2120  Supplier Payable (CNY)
    2130  Supplier Payable (USD)
    2140  VAT Payable
    2150  AIT Payable
    2160  Duties Payable
  2200  Other Liabilities
    2210  Customer Advances

EQUITY (3000-3999)
  3100  Owner's Equity
  3200  Retained Earnings

INCOME (4000-4999)
  4100  Sales Revenue
    4110  Wholesale Sales
    4120  Retail Sales
  4200  Other Income
    4210  Shipping Charged to Customer
    4220  Discount Received from Supplier

EXPENSES (5000-5999)
  5100  Cost of Goods Sold
    5110  Purchase Cost
    5120  Freight & Shipping Cost
    5130  Customs Duty
    5140  Landing Cost
  5200  Operating Expenses
    5210  Rent Expense
    5220  Salary & Wages
    5230  Utility Expense
    5240  Transport Expense
    5250  Office Expense
    5260  Marketing Expense
    5270  Bank Charges
  5300  Depreciation
  5400  Bad Debt Expense
  5500  Loss on Currency Exchange
```

### accounts (Cash/Bank Accounts)
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| chart_of_account_id | bigint FK chart_of_accounts.id | Linked COA entry |
| name | varchar(255) | e.g. "Dutch-Bangla Bank - Main" |
| type | enum | cash, bank, mobile_banking |
| bank_name | varchar(255) nullable | |
| account_number | varchar(100) nullable | |
| branch | varchar(255) nullable | |
| currency_id | bigint FK currencies.id | Account currency |
| opening_balance_bdt | decimal(14,2) default 0 | |
| current_balance_bdt | decimal(14,2) default 0 | Running balance |
| is_active | boolean default true | |
| created_at | timestamp | |
| updated_at | timestamp | |

### journals
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| journal_number | varchar(50) unique | Auto: JR-2026-0001 |
| date | date | |
| narration | text | Description of the journal |
| reference_type | varchar(100) nullable | Invoice, Payment, Shipment, etc. |
| reference_id | bigint nullable | |
| is_posted | boolean default false | Draft vs Posted |
| posted_at | timestamp nullable | |
| total_debit_bdt | decimal(14,2) default 0 | Must equal total_credit |
| total_credit_bdt | decimal(14,2) default 0 | Must equal total_debit |
| created_by | bigint FK users.id | |
| created_at | timestamp | |
| updated_at | timestamp | |

### journal_entries
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| journal_id | bigint FK journals.id | |
| account_id | bigint FK chart_of_accounts.id | |
| debit_bdt | decimal(14,2) default 0 | |
| credit_bdt | decimal(14,2) default 0 | |
| description | varchar(255) nullable | |
| created_at | timestamp | |

### expenses
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| expense_number | varchar(50) unique | Auto: EXP-2026-0001 |
| category_id | bigint FK expense_categories.id | |
| account_id | bigint FK nullable accounts.id | Paid from which account |
| amount_bdt | decimal(14,2) | |
| description | text | |
| expense_date | date | |
| receipt_path | varchar(500) nullable | |
| is_billable | boolean default false | Can be charged to customer? |
| approved_by | bigint FK nullable users.id | |
| approved_at | timestamp nullable | |
| status | enum | pending, approved, rejected, paid |
| created_by | bigint FK users.id | |
| created_at | timestamp | |
| updated_at | timestamp | |

### expense_categories
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| name | varchar(255) | |
| chart_of_account_id | bigint FK chart_of_accounts.id | Default COA mapping |
| is_active | boolean default true | |
| created_at | timestamp | |

## Auto Journal Entry System

The system automatically creates journal entries for common transactions:

### Auto JE: Wholesale Credit Sale
```
DR: Accounts Receivable (1210)     ৳10,000
  CR: Wholesale Sales (4110)       ৳10,000

DR: Cost of Goods Sold (5110)      ৳7,000
  CR: Inventory (1310)             ৳7,000
```

### Auto JE: Cash Payment Received
```
DR: Cash on Hand (1110)            ৳5,000
  CR: Accounts Receivable (1210)   ৳5,000
```

### Auto JE: Bank Payment Received
```
DR: Cash at Bank (1120)            ৳5,000
  CR: Accounts Receivable (1210)   ৳5,000
```

### Auto JE: bKash Payment Received
```
DR: bKash (1140)                   ৳5,000
  CR: Accounts Receivable (1210)   ৳5,000
```

### Auto JE: Supplier Payment (T/T in USD)
```
DR: Supplier Payable (2130)        ৳50,000 (BDT equivalent)
  CR: Cash at Bank - USD (1130)    ৳50,000

(If exchange loss)
DR: Loss on Currency Exchange (5500) ৳500
  CR: Cash at Bank - USD (1130)       ৳500
```

### Auto JE: Goods Received (from Shipment)
```
DR: Inventory (1310)               ৳70,000 (landing cost)
  CR: Supplier Payable (2120)       ৳50,000 (purchase cost)
  CR: Freight & Shipping (5120)     ৳10,000 (freight allocated)
  CR: Customs Duty (5130)           ৳5,000 (duty allocated)
  CR: VAT Payable (2140)            ৳3,000
  CR: AIT Payable (2150)            ৳2,000
```

### Auto JE: Expense Recorded
```
DR: Rent Expense (5210)            ৳20,000
  CR: Cash at Bank (1120)          ৳20,000
```

### Auto JE: Sales Return
```
DR: Wholesale Sales (4110)         ৳3,000
  CR: Accounts Receivable (1210)   ৳3,000

DR: Inventory (1310)               ৳2,100
  CR: Cost of Goods Sold (5110)    ৳2,100
```

## API Routes

### Currencies & Exchange Rates
| Method | Endpoint | Description | Permission |
|--------|----------|-------------|-----------|
| GET | /api/currencies | List currencies | accounts.view |
| POST | /api/currencies | Create currency | accounts.create |
| PUT | /api/currencies/{id} | Update currency | accounts.update |
| GET | /api/exchange-rates | List rates | accounts.view |
| POST | /api/exchange-rates | Set exchange rate | accounts.create |
| GET | /api/exchange-rates/current | Current rates | accounts.view |
| GET | /api/exchange-rates/{from}/{to}/history | Rate history | accounts.view |

### Chart of Accounts
| Method | Endpoint | Description | Permission |
|--------|----------|-------------|-----------|
| GET | /api/chart-of-accounts | List COA (tree) | accounts.view |
| POST | /api/chart-of-accounts | Create account | accounts.create |
| PUT | /api/chart-of-accounts/{id} | Update account | accounts.update |
| DELETE | /api/chart-of-accounts/{id} | Deactivate (if no transactions) | accounts.delete |

### Cash/Bank Accounts
| Method | Endpoint | Description | Permission |
|--------|----------|-------------|-----------|
| GET | /api/accounts | List cash/bank accounts | accounts.view |
| POST | /api/accounts | Create account | accounts.create |
| GET | /api/accounts/{id} | Account detail + balance | accounts.view |
| PUT | /api/accounts/{id} | Update account | accounts.update |
| GET | /api/accounts/{id}/transactions | Account transaction history | accounts.view |
| POST | /api/accounts/{id}/transfer | Transfer between accounts | accounts.create |

### Journals
| Method | Endpoint | Description | Permission |
|--------|----------|-------------|-----------|
| GET | /api/journals | List journals | accounts.view |
| POST | /api/journals | Create journal (manual) | accounts.create |
| GET | /api/journals/{id} | Journal detail + entries | accounts.view |
| PUT | /api/journals/{id} | Update journal (draft only) | accounts.update |
| DELETE | /api/journals/{id} | Delete draft journal | accounts.delete |
| POST | /api/journals/{id}/post | Post journal | accounts.update |

### Expenses
| Method | Endpoint | Description | Permission |
|--------|----------|-------------|-----------|
| GET | /api/expenses | List expenses | accounts.view |
| POST | /api/expenses | Create expense | accounts.create |
| GET | /api/expenses/{id} | Expense detail | accounts.view |
| PUT | /api/expenses/{id} | Update expense | accounts.update |
| DELETE | /api/expenses/{id} | Delete (pending only) | accounts.delete |
| POST | /api/expenses/{id}/approve | Approve expense | accounts.update |
| POST | /api/expenses/{id}/reject | Reject expense | accounts.update |
| POST | /api/expenses/bulk-import | Import from Excel | accounts.create |

### Financial Reports
| Method | Endpoint | Description | Permission |
|--------|----------|-------------|-----------|
| GET | /api/reports/trial-balance | Trial balance | report.view |
| GET | /api/reports/profit-loss | Profit & Loss statement | report.profit |
| GET | /api/reports/balance-sheet | Balance sheet | report.view |
| GET | /api/reports/cash-flow | Cash flow statement | report.view |
| GET | /api/reports/general-ledger | General ledger | accounts.view |
| GET | /api/reports/bank-book | Bank book | accounts.view |
| GET | /api/reports/cash-book | Cash book | accounts.view |

## Frontend Pages

| Page | Route | Component |
|------|-------|-----------|
| Chart of Accounts | /chart-of-accounts | ChartOfAccounts/Index.vue |
| Cash/Bank Accounts | /bank-accounts | Accounts/Index.vue |
| Account Detail | /bank-accounts/{id} | Accounts/Show.vue |
| Account Transfer | /bank-accounts/transfer | Accounts/Transfer.vue |
| Journals | /journals | Journals/Index.vue |
| Journal Create | /journals/create | Journals/Create.vue |
| Journal Detail | /journals/{id} | Journals/Show.vue |
| Expenses | /expenses | Expenses/Index.vue |
| Expense Create | /expenses/create | Expenses/Create.vue |
| Exchange Rates | /exchange-rates | ExchangeRates/Index.vue |
| Trial Balance | /reports/trial-balance | Reports/TrialBalance.vue |
| Profit & Loss | /reports/profit-loss | Reports/ProfitLoss.vue |
| Balance Sheet | /reports/balance-sheet | Reports/BalanceSheet.vue |

## Business Logic

### Multi-Currency Conversion
```php
class CurrencyService
{
    public function convert(float $amount, int $fromCurrencyId, int $toCurrencyId, ?string $date = null): float
    {
        if ($fromCurrencyId === $toCurrencyId) return $amount;
        
        $date = $date ?? now()->toDateString();
        
        $rate = ExchangeRate::where('from_currency_id', $fromCurrencyId)
            ->where('to_currency_id', $toCurrencyId)
            ->where('effective_date', '<=', $date)
            ->orderBy('effective_date', 'desc')
            ->first();
        
        if (!$rate) throw new ExchangeRateNotFoundException();
        
        return $amount * $rate->rate;
    }
    
    public function toBaseCurrency(float $amount, int $currencyId, ?string $date = null): float
    {
        $baseCurrency = Currency::where('is_base', true)->first();
        return $this->convert($amount, $currencyId, $baseCurrency->id, $date);
    }
}
```

### Journal Entry Validation
```
Double-entry rule:
  SUM(journal_entries.debit_bdt) MUST EQUAL SUM(journal_entries.credit_bdt)
  
  If not balanced → Journal cannot be posted
  
  Every journal must have at least 2 entries
  Each entry must have either debit OR credit (not both, not neither)
```

### Auto Journal Creation
```php
class JournalService
{
    public function createAutoJournal(string $narration, string $referenceType, int $referenceId, array $entries, int $createdBy): Journal
    {
        return DB::transaction(function () use ($narration, $referenceType, $referenceId, $entries, $createdBy) {
            $totalDebit = collect($entries)->sum('debit');
            $totalCredit = collect($entries)->sum('credit');
            
            if (abs($totalDebit - $totalCredit) > 0.01) {
                throw new JournalNotBalancedException();
            }
            
            $journal = Journal::create([
                'journal_number' => $this->generateJournalNumber(),
                'narration' => $narration,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'is_posted' => true,
                'posted_at' => now(),
                'total_debit_bdt' => $totalDebit,
                'total_credit_bdt' => $totalCredit,
                'created_by' => $createdBy,
            ]);
            
            foreach ($entries as $entry) {
                JournalEntry::create([
                    'journal_id' => $journal->id,
                    'account_id' => $entry['account_id'],
                    'debit_bdt' => $entry['debit'] ?? 0,
                    'credit_bdt' => $entry['credit'] ?? 0,
                    'description' => $entry['description'] ?? null,
                ]);
            }
            
            return $journal;
        });
    }
}
```

### Profit Calculation
```
Gross Profit = Wholesale Sales + Retail Sales - Cost of Goods Sold
Operating Profit = Gross Profit - Operating Expenses - Depreciation
Net Profit = Operating Profit + Other Income - Bad Debt - Exchange Loss

Per-Product Profit:
  Margin = (Selling Price - Landing Cost) / Selling Price * 100
  Profit BDT = Selling Price - Landing Cost (per unit)
```

### Account Balance Calculation
```
For any chart_of_accounts entry:
  Balance = SUM(debit_bdt) - SUM(credit_bdt) from journal_entries
  
  For Asset/Expense accounts: Normal balance = DEBIT
  For Liability/Equity/Income accounts: Normal balance = CREDIT
```

## Events & Listeners

| Event | Listener | Description |
|-------|----------|-------------|
| InvoiceCreated | CreateAutoJournalSale | Auto journal for credit sale |
| PaymentReceived | CreateAutoJournalPayment | Auto journal for payment |
| GoodsReceived | CreateAutoJournalPurchase | Auto journal for goods received |
| SupplierPaymentCompleted | CreateAutoJournalSupplierPayment | Auto journal for supplier payment |
| ExpenseApproved | CreateAutoJournalExpense | Auto journal for expense |
| SalesReturnApproved | CreateAutoJournalReturn | Auto journal for return |
| ExchangeRateChanged | FlagUnrealizedGainsLosses | Mark foreign currency entries for revaluation |

## Developer Notes

1. All journal entries are **immutable once posted** - no updates/deletes on posted journals
2. Manual journals start as draft (is_posted = false) - can edit until posted
3. Auto journals are created as posted (is_posted = true) immediately
4. Chart of accounts seeded by default - system accounts (`is_system = true`) cannot be deleted
5. Account balance is computed from journal_entries (not stored separately) for accuracy
6. Cash/bank account `current_balance_bdt` is a denormalized cache - recalculated from journals
7. Exchange rates should be updated daily (manual entry for now, API integration later)
8. All financial reports query journal_entries - add proper indexes for performance
9. Fiscal year concept not included in Phase 1 - add later
10. Use database transactions for all auto-journal operations
