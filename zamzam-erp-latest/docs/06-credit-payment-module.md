# Module 6: Credit & Payment Management

## Overview

Manages credit sales, payment collection, customer credit ledger, aging reports, and supplier payments. Critical module for Bangladesh wholesale business where credit (বাকি) sales are common.

## Database Tables

### credit_ledger
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| customer_id | bigint FK customers.id | |
| entry_type | enum | debit, credit |
| amount_bdt | decimal(14,2) | |
| balance_bdt | decimal(14,2) | Running balance after this entry |
| reference_type | varchar(100) nullable | Invoice, Payment, SalesReturn, Adjustment |
| reference_id | bigint nullable | |
| description | varchar(255) | |
| date | date | Transaction date |
| created_by | bigint FK users.id | |
| created_at | timestamp | |

**Index**: (customer_id, date) for fast ledger queries

### payments
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| payment_number | varchar(50) unique | Auto: PAY-2026-0001 |
| payable_type | enum | invoice, purchase_order |
| payable_id | bigint | |
| payer_type | enum | customer, supplier |
| payer_id | bigint | customer_id or supplier_id |
| amount_bdt | decimal(14,2) | |
| method | enum | cash, bank_transfer, bkash, nagad, rocket, cheque, online |
| reference | varchar(255) nullable | Cheque no, transaction ID, bkash TrxID |
| bank_name | varchar(255) nullable | For bank/cheque |
| bank_account_id | bigint FK nullable accounts.id | Internal bank account |
| status | enum | pending, processing, completed, bounced, cancelled |
| payment_date | date | |
| notes | text nullable | |
| received_by | bigint FK nullable users.id | Who received cash |
| created_by | bigint FK users.id | |
| created_at | timestamp | |
| updated_at | timestamp | |

### payment_allocations
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| payment_id | bigint FK payments.id | |
| invoice_id | bigint FK invoices.id | |
| amount_bdt | decimal(14,2) | Amount allocated to this invoice |
| created_at | timestamp | |

### supplier_payments
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| payment_number | varchar(50) unique | Auto: SP-2026-0001 |
| purchase_order_id | bigint FK purchase_orders.id | |
| supplier_id | bigint FK suppliers.id | |
| amount | decimal(14,2) | |
| currency_id | bigint FK currencies.id | CNY, USD, or BDT |
| exchange_rate | decimal(12,6) nullable | If paying in different currency |
| amount_bdt | decimal(14,2) | Converted to BDT |
| method | enum | tt, alipay, wechat, bank_transfer, cash |
| reference | varchar(255) nullable | TT reference, transaction ID |
| bank_name | varchar(255) nullable | |
| bank_account_id | bigint FK nullable accounts.id | |
| status | enum | pending, completed, cancelled |
| payment_date | date | |
| notes | text nullable | |
| created_by | bigint FK users.id | |
| created_at | timestamp | |
| updated_at | timestamp | |

### credit_adjustments
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| customer_id | bigint FK customers.id | |
| type | enum | write_off, discount, penalty, correction |
| amount_bdt | decimal(14,2) | |
| reason | text | |
| approved_by | bigint FK users.id | |
| approved_at | timestamp nullable | |
| status | enum | pending, approved, rejected |
| created_by | bigint FK users.id | |
| created_at | timestamp | |
| updated_at | timestamp | |

## Core Business Logic

### Credit Ledger Entry System

The credit ledger is an **append-only** running balance system.

```
Every financial transaction creates a ledger entry:

1. Invoice Created (Credit Sale):
   entry_type = DEBIT, amount = invoice total
   balance = previous_balance + amount
   (Customer owes MORE)

2. Payment Received:
   entry_type = CREDIT, amount = payment amount
   balance = previous_balance - amount
   (Customer owes LESS)

3. Sales Return (Credit Note):
   entry_type = CREDIT, amount = return amount
   balance = previous_balance - amount
   (Customer owes LESS)

4. Credit Adjustment (Write-off):
   entry_type = CREDIT, amount = write-off amount
   balance = previous_balance - amount
   (Customer owes LESS)

5. Late Payment Penalty:
   entry_type = DEBIT, amount = penalty amount
   balance = previous_balance + amount
   (Customer owes MORE)
```

### Running Balance Calculation
```php
class CreditService
{
    public function addEntry(int $customerId, string $entryType, float $amount, ...): CreditLedger
    {
        return DB::transaction(function () use ($customerId, $entryType, $amount) {
            $lastEntry = CreditLedger::where('customer_id', $customerId)
                ->orderBy('id', 'desc')
                ->lockForUpdate()
                ->first();
            
            $previousBalance = $lastEntry?->balance_bdt ?? 0;
            $newBalance = $entryType === 'debit' 
                ? $previousBalance + $amount 
                : $previousBalance - $amount;
            
            $entry = CreditLedger::create([
                'customer_id' => $customerId,
                'entry_type' => $entryType,
                'amount_bdt' => $amount,
                'balance_bdt' => $newBalance,
                // ...
            ]);
            
            // Update customer's outstanding balance
            Customer::where('id', $customerId)->update([
                'outstanding_balance_bdt' => $newBalance
            ]);
            
            return $entry;
        });
    }
}
```

### Payment Collection Flow

```
1. Salesman receives payment from customer
2. Creates payment record:
   - customer, amount, method, reference
3. Payment allocation:
   - Auto-allocate to oldest invoices first (FIFO)
   - Or manual allocation by salesman
4. On payment completion:
   a. Credit ledger entry (CREDIT)
   b. Invoice status updated (paid/partially_paid)
   c. Customer balance updated
   d. Bank/cash account updated
```

### Payment Allocation (FIFO Auto)
```php
public function autoAllocatePayment(Payment $payment): void
{
    $remaining = $payment->amount_bdt;
    $unpaidInvoices = Invoice::where('customer_id', $payment->payer_id)
        ->where('status', '!=', 'paid')
        ->where('due_bdt', '>', 0)
        ->orderBy('issued_at', 'asc') // Oldest first
        ->get();
    
    foreach ($unpaidInvoices as $invoice) {
        if ($remaining <= 0) break;
        
        $allocationAmount = min($remaining, $invoice->due_bdt);
        
        PaymentAllocation::create([
            'payment_id' => $payment->id,
            'invoice_id' => $invoice->id,
            'amount_bdt' => $allocationAmount,
        ]);
        
        $invoice->paid_bdt += $allocationAmount;
        $invoice->due_bdt -= $allocationAmount;
        $invoice->status = $invoice->due_bdt <= 0 ? 'paid' : 'partially_paid';
        $invoice->save();
        
        $remaining -= $allocationAmount;
    }
}
```

### Supplier Payment Flow

```
1. Purchase order confirmed → Payment scheduled
2. Procurement/Accountant creates supplier payment:
   - Currency: CNY/USD/BDT
   - Method: T/T, Alipay, WeChat Pay, Bank Transfer
   - Exchange rate recorded
3. On payment completion:
   a. PO paid amount updated
   b. Accounts module: bank account debited
   c. Currency conversion recorded
```

### Aging Report Logic

```
Categories:
- Current (Not yet due)
- 1-30 days overdue
- 31-60 days overdue
- 61-90 days overdue
- 90+ days overdue (Bad debt risk)

Calculation per invoice:
  days_overdue = today - invoice.due_date
  
  if days_overdue <= 0 → Current
  if days_overdue <= 30 → 1-30
  if days_overdue <= 60 → 31-60
  if days_overdue <= 90 → 61-90
  if days_overdue > 90 → 90+

Total aging = SUM of all unpaid invoice amounts by category
```

### Credit Limit Check

```
On order creation:
  available_credit = credit_limit - outstanding_balance
  if order_total > available_credit:
    → Block order OR require admin approval
  
On payment:
  outstanding_balance decreases
  available_credit increases
```

### Cheque Payment Tracking

```
1. Cheque received → Payment status: pending
2. Cheque deposited to bank → status: processing (awaiting clearing)
3. Cheque cleared by bank → status: completed, ledger entry created
4. Cheque bounced → status: bounced, reverse ledger entry created
```

> **Note:** `processing` status added to `payments.status` enum specifically for cheques deposited but not yet cleared by the bank.

## API Routes

### Customer Payments
| Method | Endpoint | Description | Permission |
|--------|----------|-------------|-----------|
| GET | /api/payments | List payments (filterable) | payment.view |
| POST | /api/payments | Record payment | payment.create |
| GET | /api/payments/{id} | Payment detail | payment.view |
| PUT | /api/payments/{id} | Update (pending only) | payment.update |
| DELETE | /api/payments/{id} | Cancel payment | payment.delete |
| POST | /api/payments/{id}/complete | Mark completed | payment.update |
| POST | /api/payments/{id}/bounce | Mark cheque bounced | payment.update |
| GET | /api/payments/{id}/allocations | View allocations | payment.view |
| POST | /api/payments/{id}/allocate | Manual allocate | payment.create |

### Credit Ledger
| Method | Endpoint | Description | Permission |
|--------|----------|-------------|-----------|
| GET | /api/customers/{id}/ledger | Customer credit ledger | credit.view |
| GET | /api/customers/{id}/statement | Account statement (date range) | credit.view |
| GET | /api/customers/{id}/aging | Aging breakdown | credit.view |

### Credit Adjustments
| Method | Endpoint | Description | Permission |
|--------|----------|-------------|-----------|
| POST | /api/credit-adjustments | Create adjustment | credit.create |
| GET | /api/credit-adjustments | List adjustments | credit.view |
| POST | /api/credit-adjustments/{id}/approve | Approve | credit.approve |
| POST | /api/credit-adjustments/{id}/reject | Reject | credit.approve |

### Supplier Payments
| Method | Endpoint | Description | Permission |
|--------|----------|-------------|-----------|
| GET | /api/supplier-payments | List supplier payments | payment.view |
| POST | /api/supplier-payments | Create supplier payment | payment.create |
| GET | /api/supplier-payments/{id} | Detail | payment.view |
| PUT | /api/supplier-payments/{id} | Update (pending only) | payment.update |
| POST | /api/supplier-payments/{id}/complete | Mark completed | payment.update |

### Aging Report
| Method | Endpoint | Description | Permission |
|--------|----------|-------------|-----------|
| GET | /api/reports/aging | Full aging report | credit.view |
| GET | /api/reports/aging/summary | Aging summary | credit.view |

## Frontend Pages

| Page | Route | Component |
|------|-------|-----------|
| Payments List | /payments | Payments/Index.vue |
| Record Payment | /payments/create | Payments/Create.vue |
| Payment Detail | /payments/{id} | Payments/Show.vue |
| Customer Ledger | /customers/{id}/ledger | Customers/Ledger.vue |
| Customer Statement | /customers/{id}/statement | Customers/Statement.vue |
| Aging Report | /reports/aging | Reports/Aging.vue |
| Credit Adjustments | /credit-adjustments | CreditAdjustments/Index.vue |
| Supplier Payments | /supplier-payments | SupplierPayments/Index.vue |

## Events & Listeners

| Event | Listener | Description |
|-------|----------|-------------|
| PaymentReceived | CreateCreditLedgerEntry | Add CREDIT entry to ledger |
| PaymentReceived | UpdateCustomerBalance | Decrease outstanding |
| PaymentReceived | UpdateInvoiceStatus | Mark invoice paid/partially_paid |
| PaymentReceived | UpdateCashBankAccount | Increase cash/bank balance |
| ChequeBounced | ReverseLedgerEntry | Reverse the CREDIT entry |
| ChequeBounced | UpdateCustomerBalance | Increase outstanding back |
| InvoiceCreated | CreateDebitLedgerEntry | Add DEBIT entry (for credit sales) |
| SalesReturnApproved | CreateCreditLedgerEntry | Add CREDIT entry (return) |
| CreditLimitExceeded | NotifyAdmin | Alert admin for approval |
| PaymentOverdue | SendOverdueReminder | Notify customer + salesman |
| SupplierPaymentCompleted | UpdatePurchaseOrderPaidAmount | Update PO payment tracking |
| SupplierPaymentCompleted | UpdateBankAccount | Decrease bank balance |

## Validation Rules

### Customer Payment
```php
'payer_id'       => 'required|exists:customers,id',
'amount_bdt'     => 'required|numeric|min:0.01',
'method'         => 'required|in:cash,bank_transfer,bkash,nagad,rocket,cheque,online',
'reference'      => 'required_if:method,bank_transfer,bkash,nagad,rocket,cheque|string|max:255',
'payment_date'   => 'required|date',
'bank_account_id' => 'required_if:method,bank_transfer|exists:accounts,id',
// If cheque: due_date, bank_name, cheque_no required
```

### Supplier Payment
```php
'supplier_id'        => 'required|exists:suppliers,id',
'purchase_order_id'  => 'required|exists:purchase_orders,id',
'amount'             => 'required|numeric|min:0.01',
'currency_id'        => 'required|exists:currencies,id',
'exchange_rate'      => 'required_if:currency_id,!=,BDT|numeric|min:0',
'method'             => 'required|in:tt,alipay,wechat,bank_transfer,cash',
'reference'          => 'nullable|string|max:255',
'payment_date'       => 'required|date',
```

### Credit Adjustment
```php
'customer_id' => 'required|exists:customers,id',
'type'        => 'required|in:write_off,discount,penalty,correction',
'amount_bdt'  => 'required|numeric|min:0.01',
'reason'      => 'required|string|min:10',
```

## Developer Notes

1. Credit ledger is **append-only** - NEVER delete or modify entries, only create new ones
2. Use pessimistic locking (`lockForUpdate()`) when adding ledger entries to prevent race conditions
3. Running balance (`balance_bdt`) must always be consistent with SUM of all entries
4. Payment allocation (FIFO) should be the default, but allow manual override
5. bKash/Nagad integration for payment verification can be added later via their APIs
6. Cheque handling needs special attention - track deposit date, clearing date, bounce scenario
7. Supplier payments may involve currency conversion - always store in both original currency and BDT
8. Aging report should be cached and recalculated daily (not on every request)
9. Credit limit enforcement is a hard rule - only Admin can override
10. All payment records must be auditable - who received, when, where, reference
