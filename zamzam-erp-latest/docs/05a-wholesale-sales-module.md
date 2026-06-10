# Module 5A: Wholesale Sales Management

## Overview

Manages wholesale/B2B sales to resellers including price tiers, wholesale orders, invoicing, and integration with the Wholesale WooCommerce store.

## Database Tables

### customers
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | Auto-increment (ERP internal) |
| customer_code | varchar(50) unique | ERP-generated customer code (format from id_format_settings) |
| external_id | varchar(50) nullable unique | External reference ID (e.g., WooCommerce/C-5851976) |
| name | varchar(255) | Contact person name |
| business_name | varchar(255) nullable | Shop/business name |
| type | enum | wholesale, retail |
| phone | varchar(20) | Primary phone (unique, used for duplicate detection) |
| email | varchar(255) nullable | |
| address | text nullable | |
| city | varchar(100) nullable | |
| area | varchar(100) nullable | Dhaka area/zone |
| trade_license_no | varchar(100) nullable | |
| nid_no | varchar(50) nullable | |
| photo | varchar(500) nullable | |
| credit_limit_bdt | decimal(14,2) default 0 | Maximum credit allowed |
| outstanding_balance_bdt | decimal(14,2) default 0 | Current unpaid balance |
| price_tier_id | bigint FK nullable price_tiers.id | Assigned price tier |
| source | varchar(50) nullable | Acquisition source: whatsapp, website, messenger, offline, live_chat, other |
| source_detail | varchar(255) nullable | Additional source detail |
| rating | tinyint nullable | 1-5 |
| is_active | boolean default true | |
| assigned_salesman_id | bigint FK nullable users.id | Responsible salesman |
| last_order_at | timestamp nullable | Date of last order |
| total_orders | int default 0 | Total order count |
| total_delivered_value_bdt | decimal(14,2) default 0 | Total delivered order value in BDT |
| sms_count | int default 0 | SMS sent count |
| woo_customer_id | bigint nullable | WooCommerce customer ID (wholesale store) |
| notes | text nullable | |
| created_by | bigint FK users.id | |
| created_at | timestamp | |
| updated_at | timestamp | |

**Indexes on customers:**
```sql
CREATE UNIQUE INDEX customers_customer_code_unique ON customers(customer_code);
CREATE UNIQUE INDEX customers_external_id_unique ON customers(external_id);
CREATE INDEX idx_customers_phone ON customers(phone);
CREATE INDEX idx_customers_source ON customers(source);
CREATE INDEX idx_customers_type ON customers(type);
CREATE INDEX idx_customers_last_order ON customers(last_order_at);
CREATE INDEX idx_customers_price_tier ON customers(price_tier_id);
```

### customer_tags
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| name | varchar(100) | Display name (e.g., VIP, Regular, New, Frozen) |
| slug | varchar(100) unique | URL-safe slug (e.g., vip, regular, new, frozen) |
| color | varchar(7) default '#6366F1' | Hex color for UI badge |
| description | text nullable | Tag description |
| is_auto_assign | boolean default false | Auto-assign to new customers |
| auto_assign_condition | json nullable | Conditions for auto-assignment (e.g., {"order_value_exceeds": 500000}) |
| linked_price_tier_id | bigint FK nullable price_tiers.id | Optional linked price tier |
| is_active | boolean default true | |
| sort_order | int default 0 | Display order |
| customers_count | int default 0 | Cached count of tagged customers |
| created_at | timestamp | |
| updated_at | timestamp | |

**Relationship:** Tags are labels/filters (many-to-many with customers). A tag can optionally link to a price tier (suggested on import). Tags ≠ Tiers. A customer can have multiple tags but only one price tier.

### customer_customer_tag (pivot)
| Column | Type | Notes |
|--------|------|-------|
| customer_id | bigint FK customers.id | |
| customer_tag_id | bigint FK customer_tags.id | |
| created_at | timestamp | |

**Primary key:** (customer_id, customer_tag_id)

### id_format_settings
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| entity_type | varchar(50) unique | Entity name (e.g., customers, products, suppliers) |
| prefix | varchar(10) default '' | ID prefix (e.g., CUS, PRO, SUP) |
| suffix | varchar(10) default '' | ID suffix |
| separator | varchar(5) default '-' | Separator between parts (e.g., -, /, ., or empty) |
| include_year | boolean default false | Include year in ID |
| year_format | varchar(4) default 'YYYY' | Year format: YYYY or YY |
| include_month | boolean default false | Include month in ID |
| sequence_digits | int default 4 | Sequence padding digits (4 = 0001, 6 = 000001) |
| sequence_start | int default 1 | Starting number for sequence |
| reset_annually | boolean default false | Reset sequence each year |
| current_sequence | int default 1 | Current sequence counter |
| preview_example | varchar(50) nullable | Example: CUS-2026-0001 |
| created_at | timestamp | |
| updated_at | timestamp | |

**Default seed:** `entity_type=customers, prefix=CUS, separator=-, include_year=true, year_format=YYYY, sequence_digits=4, sequence_start=1` → generates `CUS-2026-0001`

**ID Generation Logic:**
```
format = prefix + separator + [year + separator] + [month + separator] + padded_sequence + separator + suffix
Example outputs based on settings:
  CUS + - + 2026 + - + 0001          = CUS-2026-0001
  CUS + - + 0001                      = CUS-0001
  CUS + / + 2026 + / + 0001           = CUS/2026/0001
  CUS20260001                          = CUS20260001 (separator=empty)
  ZAM + - + 00001                      = ZAM-00001 (5 digits)
  2026 + - + CUS + - + 0001           = 2026-CUS-0001
```

### data_imports
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| entity_type | enum | customers, products, suppliers |
| original_filename | varchar(255) | Uploaded file name |
| file_path | varchar(500) nullable | Stored file path |
| file_size_kb | int nullable | File size in KB |
| total_rows | int | Total rows in file |
| imported_count | int default 0 | Successfully imported |
| updated_count | int default 0 | Existing records updated |
| skipped_count | int default 0 | Skipped (duplicates) |
| failed_count | int default 0 | Failed rows |
| duplicate_action | enum default 'skip' | skip, update, create_new |
| column_mapping | json not null | CSV column → ERP field mapping |
| tag_mapping | json nullable | CSV tag value → ERP tag ID mapping |
| source_mapping | json nullable | CSV source value → ERP source enum mapping |
| default_values | json nullable | Default values for unmapped fields |
| error_report_path | varchar(500) nullable | Path to error CSV download |
| status | enum | uploading, mapping, validating, importing, completed, failed |
| started_at | timestamp nullable | |
| completed_at | timestamp nullable | |
| duration_seconds | int nullable | |
| created_by | bigint FK users.id | |
| created_at | timestamp | |
| updated_at | timestamp | |

### data_import_errors
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| data_import_id | bigint FK data_imports.id | |
| row_number | int | Row number in source file |
| error_type | enum | validation, duplicate, format, missing_required, unknown |
| field_name | varchar(100) nullable | Field that caused the error |
| field_value | text nullable | Invalid value |
| error_message | text | Human-readable error |
| raw_row_data | json nullable | Full row data for debugging |
| created_at | timestamp | |

### price_tiers
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| name | varchar(100) | Bronze, Silver, Gold, Platinum |
| min_order_value_bdt | decimal(14,2) default 0 | Minimum order value for this tier |
| discount_percent | decimal(5,2) default 0 | Default discount % |
| payment_terms_days | int default 0 | Credit period in days |
| description | text nullable | |
| is_active | boolean default true | |
| created_at | timestamp | |
| updated_at | timestamp | |

### product_price_tiers
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| product_id | bigint FK products.id | |
| product_variant_id | bigint FK nullable product_variants.id | |
| price_tier_id | bigint FK price_tiers.id | |
| price_bdt | decimal(12,2) | Custom price for this tier |
| min_qty | int default 1 | Minimum quantity for this price |
| max_qty | int nullable | Maximum quantity for this price (null = unlimited) |
| is_active | boolean default true | |
| created_at | timestamp | |
| updated_at | timestamp | |

**Unique constraint**: (product_id, product_variant_id, price_tier_id, min_qty)

### sales_orders
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| order_number | varchar(50) unique | Auto: WS-2026-0001 |
| customer_id | bigint FK customers.id | |
| warehouse_id | bigint FK warehouses.id | Fulfillment warehouse |
| type | enum default 'wholesale' | wholesale, retail |
| payment_type | enum | cash, credit, partial |
| subtotal_bdt | decimal(14,2) default 0 | Before discount |
| discount_bdt | decimal(14,2) default 0 | Total discount |
| total_bdt | decimal(14,2) default 0 | Grand total |
| paid_bdt | decimal(14,2) default 0 | Amount paid |
| due_bdt | decimal(14,2) default 0 | total - paid |
| status | enum | draft, confirmed, processing, packed, delivered, invoiced, cancelled |
| delivery_address | text nullable | If different from customer address |
| delivery_date | date nullable | Expected delivery |
| notes | text nullable | |
| source | enum | erp, wholesale_storefront, retail_storefront, woocommerce, phone, manual |
| woo_order_id | bigint nullable | WooCommerce order ID |
| confirmed_by | bigint FK nullable users.id | |
| confirmed_at | timestamp nullable | |
| created_by | bigint FK users.id | |
| created_at | timestamp | |
| updated_at | timestamp | |

### so_items
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| sales_order_id | bigint FK sales_orders.id | |
| product_id | bigint FK products.id | |
| product_variant_id | bigint FK nullable product_variants.id | |
| price_bdt | decimal(12,2) | Selling price per unit |
| landing_cost_bdt | decimal(12,2) | Cost per unit (for profit calc) |
| qty | int | |
| discount_bdt | decimal(12,2) default 0 | Line-level discount |
| subtotal_bdt | decimal(14,2) | (price - discount) * qty |
| delivered_qty | int default 0 | Qty actually delivered |
| notes | text nullable | |
| created_at | timestamp | |
| updated_at | timestamp | |

### invoices
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| invoice_number | varchar(50) unique | Auto: INV-2026-0001 |
| sales_order_id | bigint FK sales_orders.id | |
| customer_id | bigint FK customers.id | |
| total_bdt | decimal(14,2) | |
| paid_bdt | decimal(14,2) default 0 | |
| due_bdt | decimal(14,2) | total - paid |
| due_date | date nullable | |
| status | enum | draft, sent, paid, partially_paid, overdue, cancelled |
| issued_at | date | Invoice date |
| notes | text nullable | |
| created_by | bigint FK users.id | |
| created_at | timestamp | |
| updated_at | timestamp | |

### invoice_items
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| invoice_id | bigint FK invoices.id | |
| product_id | bigint FK products.id | |
| product_variant_id | bigint FK nullable product_variants.id | |
| description | varchar(255) nullable | |
| qty | int | |
| price_bdt | decimal(12,2) | |
| discount_bdt | decimal(12,2) default 0 | |
| subtotal_bdt | decimal(14,2) | |
| landing_cost_bdt | decimal(12,2) nullable | For profit calculation |
| created_at | timestamp | |

### sales_returns
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| return_number | varchar(50) unique | Auto: SR-2026-0001 |
| sales_order_id | bigint FK sales_orders.id | |
| customer_id | bigint FK customers.id | |
| reason | text | |
| total_bdt | decimal(14,2) | Refund amount |
| status | enum | pending, approved, rejected, completed |
| approved_by | bigint FK nullable users.id | |
| approved_at | timestamp nullable | |
| created_by | bigint FK users.id | |
| created_at | timestamp | |
| updated_at | timestamp | |

### return_items
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| sales_return_id | bigint FK sales_returns.id | |
| product_id | bigint FK products.id | |
| product_variant_id | bigint FK nullable product_variants.id | |
| qty | int | |
| refund_price_bdt | decimal(12,2) | Price per unit for refund |
| subtotal_bdt | decimal(14,2) | qty * refund_price |
| condition | enum | good, damaged, expired |
| notes | text nullable | |
| created_at | timestamp | |

## Customer Tags Management

Tags are **labels/filters** for customers — separate from price tiers. A customer can have multiple tags but only one price tier.

### Tag vs Tier Relationship

| Aspect | Tags | Price Tiers |
|--------|------|-------------|
| Purpose | Labels, filters, segmentation | Pricing level, discount % |
| Relationship | Many-to-many with customers | Many-to-one (customer has one tier) |
| Examples | VIP, Regular, New, Frozen, Wholesale-Only, COD-Only | Platinum, Gold, Silver, Bronze |
| Auto-assign | Optional (on new customer, or when order value exceeds threshold) | Based on volume/history |
| Linked | Tag can optionally suggest a tier (VIP → Platinum) | Independent |

### Tag CRUD

**Create Tag:**
```php
'rules' => [
    'name'      => 'required|string|max:100|unique:customer_tags',
    'slug'      => 'nullable|string|max:100|unique:customer_tags', // auto-generated from name if null
    'color'     => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
    'description' => 'nullable|string|max:500',
    'is_auto_assign' => 'boolean',
    'auto_assign_condition' => 'required_if:is_auto_assign,true|nullable|array',
    'linked_price_tier_id' => 'nullable|exists:price_tiers,id',
]
```

**Auto-assign conditions:**
```json
// Example: Auto-assign "VIP" tag when total delivered value exceeds ৳5,00,000
{
    "type": "order_value_exceeds",
    "value": 500000,
    "currency": "BDT"
}

// Example: Auto-assign "New" tag to all new customers
{
    "type": "new_customer"
}

// Example: Auto-assign "Wholesale" when type = wholesale
{
    "type": "customer_type",
    "value": "wholesale"
}
```

**Delete Tag:** Only allowed when `customers_count = 0`. Tag is soft-deleted (is_active = false).

### Tag Display

Tags are rendered as colored badges on customer list and detail pages:
```
Badge: rounded-full px-2.5 py-0.5 text-xs font-medium
  VIP:     bg-amber-100 text-amber-700   (color: #F59E0B)
  REGULAR: bg-blue-100 text-blue-700     (color: #3B82F6)
  NEW:     bg-emerald-100 text-emerald-700 (color: #10B981)
  FROZEN:  bg-red-100 text-red-700        (color: #EF4444)
```

Custom colors supported — admin can pick any hex color during tag creation.

## Customer ID Format

Each entity (customers, products, suppliers) can have a customizable ID format configured via `id_format_settings`.

### Configuration

| Setting | Options | Default (Customers) |
|---------|---------|---------------------|
| Prefix | 2-10 chars (A-Z, 0-9) | CUS |
| Suffix | 2-10 chars (A-Z, 0-9) | (empty) |
| Separator | -, /, ., or none | - |
| Include Year | Yes/No | Yes |
| Year Format | YYYY or YY | YYYY |
| Include Month | Yes/No | No |
| Sequence Digits | 4-6 | 4 |
| Sequence Start | Any number | 1 |
| Reset Annually | Yes/No | No |
| Current Sequence | Auto-incremented | 1 |

### ID Generation Service

```php
class IdGenerationService
{
    public function generate(string $entityType): string
    {
        $format = IdFormatSetting::where('entity_type', $entityType)
            ->lockForUpdate()
            ->first();

        $parts = [];
        $sep = $format->separator;

        if ($format->prefix) {
            $parts[] = $format->prefix;
        }

        if ($format->include_year) {
            $yearPart = $format->year_format === 'YYYY'
                ? (string) now()->year
                : now()->format('y');
            $parts[] = $yearPart;
        }

        if ($format->include_month) {
            $parts[] = str_pad(now()->month, 2, '0', STR_PAD_LEFT);
        }

        $sequence = str_pad(
            $format->current_sequence,
            $format->sequence_digits,
            '0',
            STR_PAD_LEFT
        );
        $parts[] = $sequence;

        if ($format->suffix) {
            $parts[] = $format->suffix;
        }

        $id = implode($sep, $parts);

        // Increment sequence FIRST, then handle annual reset for NEXT call
        $format->current_sequence++;

        // If reset_annually is enabled and the year has changed, reset sequence to start value
        if ($format->reset_annually) {
            $currentYear = now()->year;
            $lastYear = optional($format->updated_at)->year ?? $currentYear;
            if ($currentYear > $lastYear) {
                $format->current_sequence = $format->sequence_start;
            }
        }

        $format->save();

        return $id;
    }

    public function previewNext(string $entityType, int $count = 3): array
    {
        // Returns next N IDs without incrementing sequence
        // Used in settings UI preview
    }
}
```

**Important:** ID format changes only affect NEW customers. Existing customer codes are never changed.

## Data Import (Universal)

The data import module is an **extensible, entity-agnostic** import system. Customer import is the first implemented entity, with architecture supporting future expansion to products, suppliers, etc.

### Import Flow — 5-Step Wizard

```
Step 1: Upload
   ├── Drag & drop CSV/XLSX file (max 10MB, 10,000 rows)
   ├── Download demo CSV template button
   ├── File validation (type, size, row count)
   └── Auto-detect column headers from first row

Step 2: Column Mapping
   ├── Auto-map CSV columns to ERP fields (by name matching)
   ├── Manual override dropdowns for each column
   ├── Required field validation (name, phone)
   ├── Tag mapping: CSV tag values → ERP tag IDs
   │   ├── Existing tags shown as dropdown
   │   ├── "+ Create Tag" option inline
   │   └── Optional: link tag to price tier
   ├── Source mapping: CSV source values → ERP source enum
   └── Duplicate detection settings:
       ├── Field to check: phone (recommended), email, external_id
       └── Action on duplicate: skip, update, create_new

Step 3: Validation & Preview
   ├── Show total rows, valid rows, duplicate rows, error rows
   ├── Preview table (first 10 rows with mapped data)
   ├── Duplicate rows highlighted with conflict details
   ├── Error rows listed with field-level messages
   └── Proceed/Cancel decision

Step 4: Import Progress
   ├── Progress bar (processed/total)
   ├── Live counters: imported, updated, skipped, failed
   └── Background job (Laravel Queue) for large imports

Step 5: Complete
   ├── Summary: total, imported, updated, skipped, failed
   ├── Download error report CSV (if any failed rows)
   └── Link to view imported customers
```

### Downloadable CSV Template

**File:** `zamzam_customer_import_template.csv`

```csv
External_ID,Name,Phone,Email,Tags,Source,City,Address,Notes,Price_Tier,Credit_Limit_BDT,Assigned_Salesman
C-EXAMPLE1,Rahim Store,+8801711222333,rahim@example.com,"VIP,Wholesale",whatsapp,Chattogram District,"123 Main Road, Chittagong",Regular customer who pays on time,Platinum,500000,Ahmed
C-EXAMPLE2,Karim Traders,+8801811222444,karim@example.com,REGULAR,website,Dhaka District,"456 Road, Dhaka",,Silver,200000,
,New Customer,+8801911222555,,NEW,offline,Gazipur District,"789 Road, Gazipur",New customer,,50000,
```

**Template features:**
- Column headers match ERP field names (mapped via `entity_import_configs`)
- 3 sample rows demonstrating VIP, REGULAR, NEW tags
- Tags column accepts comma-separated values
- External_ID is optional — ERP auto-generates `customer_code` if empty
- Required fields marked in template header comments

### Duplicate Detection

On import, the system checks for duplicates using:
1. **Primary:** `phone` (most reliable unique identifier for BD customers)
2. **Secondary:** `email` (if provided)
3. **Tertiary:** `external_id` (if provided, matches WooCommerce/legacy IDs)

**Duplicate actions:**
- **skip** — Skip row, keep existing record unchanged
- **update** — Update existing record with new data (merge fields)
- **create_new** — Create new record anyway (different phone/email = different customer)

### Import Service Architecture

```php
class DataImportService
{
    public function upload(UploadedFile $file, string $entityType): DataImport;
    public function map(DataImport $import, array $columnMapping, array $tagMapping, array $sourceMapping): DataImport;
    public function validate(DataImport $import): array;
    public function execute(DataImport $import): DataImport;
    public function getTemplate(string $entityType): string;
}
```

The service uses `entity_type` to determine:
- Which model to use (Customer, Product, Supplier)
- Which validation rules to apply
- Which fields are required
- Which fields are unique (for duplicate detection)
- Which column mapping template to use

### Supported Entity Types

| Entity | Status | Required Fields | Unique Fields |
|--------|--------|----------------|---------------|
| customers | Phase 4A | name, phone | phone, email, external_id |
| products | Future | name, sku, category_id | sku, barcode |
| suppliers | Future | name_chinese, name_english | wechat_id, email |

## Status Flow - Wholesale Sales Order

```
DRAFT → CONFIRMED → PROCESSING → PACKED → DELIVERED → INVOICED
      ↘ CANCELLED (from DRAFT only)
```

### Status Descriptions
| Status | Description | Action |
|--------|-------------|--------|
| draft | Order created, not confirmed | Edit/delete allowed |
| confirmed | Order confirmed, stock reserved | Stock reserved |
| processing | Being prepared | Pick/pack in progress |
| packed | Ready for delivery | Stock picked and packed |
| delivered | Goods delivered to customer | Stock deducted, invoice created |
| invoiced | Invoice generated | Payment tracking begins |
| cancelled | Order cancelled | Stock released |

### Credit Order Special Flow
```
DRAFT → CONFIRMED (check credit limit) → PROCESSING → PACKED → DELIVERED → INVOICED
                                                                  ↓
                                                         Payment tracking (Credit Module)
```

When `payment_type = credit`:
1. System checks `customers.outstanding_balance_bdt + order.total_bdt <= customers.credit_limit_bdt`
2. If exceeds credit limit, order requires Admin approval
3. On delivery, `outstanding_balance_bdt += due_bdt`

## Price Calculation Logic

### Tier-based Price Resolution
```
1. Get customer's price_tier_id
2. Lookup product_price_tiers for (product, tier, qty range)
3. If found → use tier price
4. If not found → use base wholesale price (product default)
5. Apply any additional discount (line-level or order-level)
```

### Volume-based Pricing Example
| Tier | Qty Range | Discount |
|------|-----------|----------|
| Bronze | 1-49 | 0% off base price |
| Silver | 50-199 | 5% off base price |
| Gold | 200-499 | 10% off base price |
| Platinum | 500+ | 15% off base price |

### Order Total Calculation
```
subtotal = SUM(so_items.subtotal_bdt)
discount = order-level discount (if any)
total = subtotal - discount
paid = amount received at order time
due = total - paid
```

## API Routes

### Customers
| Method | Endpoint | Description | Permission |
|--------|----------|-------------|-----------|
| GET | /api/customers | List customers (filter by type, tag, source, tier) | wholesale.view |
| POST | /api/customers | Create customer (auto-generates customer_code) | wholesale.create |
| GET | /api/customers/{id} | Customer detail (includes tags, tier, stats) | wholesale.view |
| PUT | /api/customers/{id} | Update customer | wholesale.update |
| DELETE | /api/customers/{id} | Deactivate customer | wholesale.delete |
| GET | /api/customers/{id}/orders | Customer's order history | wholesale.view |
| GET | /api/customers/{id}/ledger | Credit ledger | wholesale.view |
| PUT | /api/customers/{id}/credit-limit | Update credit limit | credit.approve |
| POST | /api/customers/{id}/tags | Attach tags to customer | wholesale.update |
| DELETE | /api/customers/{id}/tags/{tagId} | Detach tag from customer | wholesale.update |
| GET | /api/customers/by-code/{code} | Find by customer_code | wholesale.view |
| GET | /api/customers/by-external/{externalId} | Find by external_id | wholesale.view |

### Customer Tags
| Method | Endpoint | Description | Permission |
|--------|----------|-------------|-----------|
| GET | /api/customer-tags | List all tags (with customer counts) | wholesale.view |
| POST | /api/customer-tags | Create tag | wholesale.create |
| GET | /api/customer-tags/{id} | Tag detail (with linked tier info) | wholesale.view |
| PUT | /api/customer-tags/{id} | Update tag | wholesale.update |
| DELETE | /api/customer-tags/{id} | Delete tag (only if no customers) | wholesale.delete |
| GET | /api/customer-tags/{id}/customers | List customers with this tag | wholesale.view |

### Data Import (Universal)
| Method | Endpoint | Description | Permission |
|--------|----------|-------------|-----------|
| GET | /api/data-imports/template/{entityType} | Download CSV template | import.customers |
| POST | /api/data-imports/upload | Upload file (step 1) | import.customers |
| POST | /api/data-imports/{id}/map | Column mapping (step 2) | import.customers |
| POST | /api/data-imports/{id}/validate | Validate (step 3) | import.customers |
| POST | /api/data-imports/{id}/execute | Start import (step 4, queued) | import.customers |
| GET | /api/data-imports/{id}/progress | Progress status (SSE/poll) | import.customers |
| GET | /api/data-imports | Import history list | import.customers |
| GET | /api/data-imports/{id} | Import detail with stats | import.customers |
| GET | /api/data-imports/{id}/errors | Download error report CSV | import.customers |
| DELETE | /api/data-imports/{id} | Delete import record | import.delete |

### ID Format Settings
| Method | Endpoint | Description | Permission |
|--------|----------|-------------|-----------|
| GET | /api/settings/id-format | Get all entity ID formats | settings.view |
| GET | /api/settings/id-format/{entityType} | Get specific entity format | settings.view |
| PUT | /api/settings/id-format/{entityType} | Update ID format | settings.manage |
| GET | /api/settings/id-format/{entityType}/preview | Preview next N IDs | settings.view |

### Price Tiers
| Method | Endpoint | Description | Permission |
|--------|----------|-------------|-----------|
| GET | /api/price-tiers | List tiers | wholesale.view |
| POST | /api/price-tiers | Create tier | wholesale.create |
| PUT | /api/price-tiers/{id} | Update tier | wholesale.update |
| DELETE | /api/price-tiers/{id} | Delete tier | wholesale.delete |
| GET | /api/price-tiers/{id}/products | Tier-specific prices | wholesale.view |
| POST | /api/price-tiers/{id}/products | Set product tier price | wholesale.create |
| PUT | /api/price-tiers/{id}/products/{pptId} | Update tier price | wholesale.update |
| POST | /api/price-tiers/{id}/products/bulk | Bulk set tier prices | wholesale.create |

### Wholesale Orders
| Method | Endpoint | Description | Permission |
|--------|----------|-------------|-----------|
| GET | /api/wholesale/orders | List wholesale orders | wholesale.view |
| POST | /api/wholesale/orders | Create order | wholesale.create |
| GET | /api/wholesale/orders/{id} | Order detail | wholesale.view |
| PUT | /api/wholesale/orders/{id} | Update order (draft only) | wholesale.update |
| DELETE | /api/wholesale/orders/{id} | Cancel order | wholesale.delete |
| POST | /api/wholesale/orders/{id}/confirm | Confirm order | wholesale.approve |
| POST | /api/wholesale/orders/{id}/process | Mark processing | wholesale.update |
| POST | /api/wholesale/orders/{id}/pack | Mark packed | wholesale.update |
| POST | /api/wholesale/orders/{id}/deliver | Mark delivered | wholesale.update |
| GET | /api/wholesale/orders/{id}/invoice | Generate invoice | wholesale.view |
| GET | /api/wholesale/orders/{id}/pdf | Download order PDF | wholesale.view |

### Invoices
| Method | Endpoint | Description | Permission |
|--------|----------|-------------|-----------|
| GET | /api/invoices | List invoices | wholesale.view |
| GET | /api/invoices/{id} | Invoice detail | wholesale.view |
| GET | /api/invoices/{id}/pdf | Download PDF | wholesale.view |
| POST | /api/invoices/{id}/send | Email invoice to customer | wholesale.update |
| PUT | /api/invoices/{id} | Update invoice (draft only) | wholesale.update |

### Sales Returns
| Method | Endpoint | Description | Permission |
|--------|----------|-------------|-----------|
| GET | /api/sales-returns | List returns | wholesale.view |
| POST | /api/sales-returns | Create return | wholesale.create |
| GET | /api/sales-returns/{id} | Return detail | wholesale.view |
| POST | /api/sales-returns/{id}/approve | Approve return | wholesale.approve |
| POST | /api/sales-returns/{id}/reject | Reject return | wholesale.approve |

## Frontend Pages

| Page | Route | Component |
|------|-------|-----------|
| Customers List | /customers | Customers/Index.vue |
| Customer Create | /customers/create | Customers/Create.vue |
| Customer Detail | /customers/{id} | Customers/Show.vue |
| Customer Ledger | /customers/{id}/ledger | Customers/Ledger.vue |
| Import Customers | /customers/import | Customers/Import.vue (5-step wizard) |
| Customer Tags | /customers/tags | Customers/Tags/Index.vue |
| Create Tag | /customers/tags/create | Customers/Tags/Create.vue |
| Edit Tag | /customers/tags/{id}/edit | Customers/Tags/Edit.vue |
| Tag Customers | /customers/tags/{id}/customers | Customers/Tags/Customers.vue |
| Price Tiers | /price-tiers | PriceTiers/Index.vue |
| Tier Products | /price-tiers/{id}/products | PriceTiers/Products.vue |
| Wholesale Orders | /wholesale/orders | Wholesale/Orders/Index.vue |
| Order Create | /wholesale/orders/create | Wholesale/Orders/Create.vue |
| Order Detail | /wholesale/orders/{id} | Wholesale/Orders/Show.vue |
| Invoices | /invoices | Invoices/Index.vue |
| Invoice Detail | /invoices/{id} | Invoices/Show.vue |
| Sales Returns | /sales-returns | SalesReturns/Index.vue |

## Business Logic

### Order Creation with Credit Check
```php
class WholesaleOrderService
{
    public function createOrder(array $data): SalesOrder
    {
        return DB::transaction(function () use ($data) {
            $customer = Customer::findOrFail($data['customer_id']);
            
            // Calculate order total
            $items = collect($data['items']);
            $totalBdt = $this->calculateOrderTotal($items, $customer);
            
            // Credit check for credit orders
            if ($data['payment_type'] === 'credit') {
                $newOutstanding = $customer->outstanding_balance_bdt + $totalBdt;
                
                if ($newOutstanding > $customer->credit_limit_bdt) {
                    throw new CreditLimitExceededException(
                        "Order amount ৳{$totalBdt} would exceed credit limit."
                    );
                }
            }
            
            // Create order + items
            $order = SalesOrder::create([...]);
            // ... create items
            
            return $order;
        });
    }
}
```

### Price Resolution for Order Item
```php
public function resolvePrice(int $productId, ?int $variantId, int $tierId, int $qty): float
{
    // 1. Check product_price_tiers for exact match (product + tier + qty range)
    $tierPrice = ProductPriceTier::where('product_id', $productId)
        ->where('price_tier_id', $tierId)
        ->where('min_qty', '<=', $qty)
        ->where(function ($q) use ($qty) {
            $q->whereNull('max_qty')->orWhere('max_qty', '>=', $qty);
        })
        ->where('is_active', true)
        ->first();
    
    if ($tierPrice) {
        return $tierPrice->price_bdt;
    }
    
    // 2. Fallback: base price + tier discount percentage
    $tier = PriceTier::find($tierId);
    $basePrice = $this->getBaseWholesalePrice($productId, $variantId);
    return $basePrice * (1 - $tier->discount_percent / 100);
}
```

### Invoice Generation
```
1. Auto-generated when order status → delivered
2. Invoice number: INV-{YEAR}-{SEQUENTIAL}
3. Invoice date = delivery date
4. Due date = invoice date + customer's tier payment_terms_days
5. If cash order: status = paid immediately
6. If credit order: status = sent, due_bdt = total
```

### Sales Return Processing
```
1. Return created with pending status
2. Admin/Manager approves
3. On approval:
   a. Stock transaction (type: sale_return) created
   b. stock_items.qty_available increased
   c. If refund via credit: customer.outstanding_balance_bdt decreased
   d. If refund via cash: record cash payment out
4. Return completed
```

## Events & Listeners

| Event | Listener | Description |
|-------|----------|-------------|
| WholesaleOrderConfirmed | ReserveStock | Reserve stock in inventory |
| WholesaleOrderDelivered | CreateInvoice | Auto-generate invoice |
| WholesaleOrderDelivered | UpdateCustomerBalance | Update outstanding if credit |
| WholesaleOrderCancelled | ReleaseReservedStock | Release reserved stock |
| InvoiceCreated | SendInvoiceNotification | Email/notify customer |
| InvoiceOverdue | SendOverdueReminder | Notify customer + salesman |
| SalesReturnApproved | RestockReturnedItems | Add back to inventory |
| SalesReturnApproved | AdjustCustomerBalance | Refund credit/cash |

## Validation Rules

### Customer Create
```php
'customer_code'   => 'nullable|string|max:50|unique:customers,customer_code', // auto-generated if null
'external_id'     => 'nullable|string|max:50|unique:customers,external_id',
'name'            => 'required|string|max:255',
'business_name'   => 'nullable|string|max:255',
'phone'           => 'required|string|max:20|unique:customers,phone',
'email'           => 'nullable|email|max:255|unique:customers,email',
'type'            => 'required|in:wholesale,retail',
'source'          => 'nullable|in:whatsapp,website,messenger,offline,live_chat,other',
'source_detail'   => 'nullable|string|max:255',
'credit_limit_bdt' => 'nullable|numeric|min:0',
'price_tier_id'   => 'nullable|exists:price_tiers,id',
'tags'            => 'nullable|array',
'tags.*'          => 'exists:customer_tags,id',
'address'         => 'nullable|string',
'city'            => 'nullable|string|max:100',
'area'            => 'nullable|string|max:100',
'assigned_salesman_id' => 'nullable|exists:users,id',
'notes'           => 'nullable|string',
```

### Customer Tag Create
```php
'name'                   => 'required|string|max:100|unique:customer_tags',
'slug'                   => 'nullable|string|max:100|unique:customer_tags',
'color'                  => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
'description'            => 'nullable|string|max:500',
'is_auto_assign'         => 'boolean',
'auto_assign_condition'  => 'required_if:is_auto_assign,true|nullable|array',
'linked_price_tier_id'   => 'nullable|exists:price_tiers,id',
```

### Data Import Upload
```php
'file'         => 'required|file|mimes:csv,xlsx|max:10240', // max 10MB
'entity_type'  => 'required|in:customers,products,suppliers',
```

### Data Import Mapping
```php
'column_mapping' => 'required|array',
'column_mapping.*.csv_column' => 'required|string',
'column_mapping.*.erp_field'  => 'required|string',
'tag_mapping'    => 'nullable|array',
'tag_mapping.*.csv_value'     => 'required|string',
'tag_mapping.*.tag_id'        => 'nullable|exists:customer_tags,id',
'tag_mapping.*.create_tag'    => 'boolean',
'tag_mapping.*.price_tier_id' => 'nullable|exists:price_tiers,id',
'source_mapping' => 'nullable|array',
'source_mapping.*.csv_value'  => 'required|string',
'source_mapping.*.erp_value'  => 'required|in:whatsapp,website,messenger,offline,live_chat,other',
'duplicate_action' => 'required|in:skip,update,create_new',
'duplicate_field'  => 'required|in:phone,email,external_id',
'default_values'   => 'nullable|array',
```

### ID Format Settings
```php
'prefix'           => 'nullable|string|max:10|alpha_num',
'suffix'           => 'nullable|string|max:10|alpha_num',
'separator'        => 'nullable|string|max:5',
'include_year'     => 'boolean',
'year_format'      => 'in:YYYY,YY',
'include_month'    => 'boolean',
'sequence_digits'  => 'integer|min:4|max:6',
'sequence_start'   => 'integer|min:1',
'reset_annually'   => 'boolean',
```

### Wholesale Order Create
```php
'customer_id'    => 'required|exists:customers,id',
'warehouse_id'   => 'required|exists:warehouses,id',
'payment_type'   => 'required|in:cash,credit,partial',
'delivery_date'  => 'nullable|date|after:today',
'items'          => 'required|array|min:1',
'items.*.product_id'  => 'required|exists:products,id',
'items.*.qty'         => 'required|integer|min:1',
'items.*.price_bdt'   => 'nullable|numeric|min:0', // auto-resolved if null
```

## Developer Notes

1. Use `app/Models/Sales/` namespace for all wholesale models
2. Use `app/Services/WholesaleOrderService.php` for order business logic
3. Invoice PDF uses `barryvdh/laravel-dompdf` with custom Blade template
4. Price tier resolution must handle: exact tier price → base + discount → fallback
5. Credit limit check is CRITICAL - never allow order if it would exceed credit limit (unless admin override)
6. Stock must be checked (qty_available - qty_reserved >= ordered_qty) before confirming order
7. All order status transitions must be logged with timestamp and user
8. WooCommerce order sync is handled by WooCommerce Integration module (Module 8)
9. Invoice due_date auto-calculated from payment_terms_days of customer's price tier
10. Use database transactions for order creation, status changes, and invoice generation
11. Customer `customer_code` is auto-generated using `IdGenerationService::generate('customers')` on create
12. Customer `external_id` stores the original WooCommerce/CSV ID for cross-reference
13. Tags and tiers are separate: `customer_tags` (many-to-many) ≠ `price_tiers` (many-to-one)
14. Tag `is_auto_assign` uses Redis-cached condition checks for performance on bulk import
15. Data import uses Laravel Queue (Redis) for files > 100 rows — smaller files process synchronously
16. Import duplicate detection queries `customers.phone` with index — ensure idx_customers_phone exists
17. On import, `IdGenerationService::generate()` runs inside a transaction with `lockForUpdate()` on `id_format_settings`
18. Tag customer count (`customers_count`) is denormalized and updated via model events (attach/detach)
19. CSV template files stored in `storage/app/templates/imports/customers_template.csv`
20. Import validation runs row-by-row; errors collected in `data_import_errors` table, not thrown immediately
21. `source` field on customers uses enum validation: whatsapp, website, messenger, offline, live_chat, other
22. Auth permissions: `import.customers` is separate from `wholesale.create` — import can be restricted
