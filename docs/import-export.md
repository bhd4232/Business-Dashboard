# Import and Export Guide

Business Dashboard supports CSV import/export for product, customer, and supplier onboarding. Reports can also be exported as CSV or PDF.

## Product CSV

Columns:

```txt
name,sku,description,category,barcode,unit,brand,cost_price,sale_price,stock,reorder_level,vat_rate,status,is_active
```

Notes:

- `name` is required.
- `sku` should be unique.
- `category` is created when missing.
- `stock` creates or adjusts stock movements.
- `status` should be `available` or `coming_soon`.
- `is_active` accepts truthy/falsy values such as `1`, `0`, `true`, `false`.

Download sample:

```txt
/admin/products/import/sample
```

Export:

```txt
/admin/products/export/csv
```

## Customer CSV

Columns:

```txt
name,phone,email,address,type,source,opening_balance,is_active
```

Notes:

- `name` is required.
- `email` must be valid when provided.
- `opening_balance` is used for initial due tracking.
- `type` can be used for retail, wholesale, dealer, or custom segments.
- `source` can track referral, Facebook, showroom, marketplace, or other sources.

Download sample:

```txt
/admin/customers/import/sample
```

Export:

```txt
/admin/customers/export/csv
```

## Supplier CSV

Columns:

```txt
name,phone,email,company_name,address,opening_balance,is_active
```

Notes:

- `name` is required.
- `email` must be valid when provided.
- `opening_balance` is used for supplier payable tracking.

Download sample:

```txt
/admin/suppliers/import/sample
```

Export:

```txt
/admin/suppliers/export/csv
```

## Report Exports

Available report types:

```txt
sales
purchases
profit
stock
low-stock
customer-dues
supplier-dues
expenses
ledger
```

CSV export URL:

```txt
/admin/reports/export/{type}?date_from=2026-06-01&date_to=2026-06-30
```

PDF export URL:

```txt
/admin/reports/export/{type}/pdf?date_from=2026-06-01&date_to=2026-06-30
```

## Permissions

- Product CSV routes require inventory view access.
- Customer CSV routes require sales view access.
- Supplier CSV routes require purchasing view access.
- Report export requires `reports.export`.

## Import Checklist

1. Download the sample CSV.
2. Keep the heading row unchanged.
3. Validate unique SKUs before importing products.
4. Import categories/products before sales and purchase data.
5. Review stock movement records after product stock import.
6. Export data after import and compare totals.
