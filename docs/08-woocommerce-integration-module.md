# Module 8: WooCommerce Importer + Native Storefront

## Overview

This module has two parts:
1. **WooCommerce Importer** — One-time data migration tool to import products, categories, customers, and orders from existing WooCommerce stores into ZamZam ERP
2. **Native Storefront Module** — Laravel-built wholesale and retail storefronts that replace WooCommerce entirely, with module toggle on/off functionality

## Architecture

```
┌──────────────────────────────────────────────────────────────────────┐
│                    ZamZam ERP (Single Laravel App)                    │
│                                                                      │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐              │
│  │  ERP Admin   │  │  Wholesale   │  │   Retail     │              │
│  │  Dashboard   │  │  Storefront  │  │  Storefront  │              │
│  │  (Inertia)   │  │  (Inertia)   │  │  (Inertia)   │              │
│  │  /admin/*    │  │  /wholesale/*│  │  /shop/*     │              │
│  └──────────────┘  └──────────────┘  └──────────────┘              │
│         │                  │                  │                        │
│  ┌──────┴──────────────────┴──────────────────┴──────┐              │
│  │              Shared Laravel Backend                  │              │
│  │  (Models, Services, Policies, Queue, Events)        │              │
│  │                                                      │              │
│  │  • Auth & Multi-role (admin/reseller/customer)       │              │
│  │  • Product Catalog (shared, tier-based pricing)      │              │
│  │  • Order Engine (wholesale=credit, retail=prepaid)   │              │
│  │  • Credit System (বাকি management built-in)          │              │
│  │  • Stock Engine (real-time availability)             │              │
│  │  • AI Chatbot (WhatsApp → storefront orders)          │              │
│  └──────────────────────────────────────────────────────┘              │
│                                                                      │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐              │
│  │   MySQL DB   │  │    Redis     │  │  React Native │              │
│  │  (Single)    │  │   (Queue/    │  │  Mobile App   │              │
│  │              │  │    Cache)    │  │               │              │
│  └──────────────┘  └──────────────┘  └──────────────┘              │
│                                                                      │
│  ┌──────────────────────────────────────────────────────┐              │
│  │  WooCommerce Importer (one-time migration tool)      │              │
│  │  Connect → Select Data → Import → Deactivate         │              │
│  └──────────────────────────────────────────────────────┘              │
└──────────────────────────────────────────────────────────────────────┘
```

## Module Toggle System

All major modules can be enabled/disabled from Settings. At least one storefront must be active.

```
⚙️ Settings > Modules

┌────────────────────────────────────────────────────────────────────┐
│  🧩 Module Management                                                │
│                                                                      │
│  ┌────────────────────────────────────────────────────────────────┐│
│  │  🏪 Wholesale Storefront                    [🟢 Active] [⚙️]  ││
│  │  B2B storefront for resellers — tier pricing, credit, bulk      ││
│  │  Route: /wholesale/*                                           ││
│  └────────────────────────────────────────────────────────────────┘│
│                                                                      │
│  ┌────────────────────────────────────────────────────────────────┐│
│  │  🛒 Retail Storefront                       [🟢 Active] [⚙️]  ││
│  │  B2C storefront for end customers — prepaid, courier            ││
│  │  Route: /shop/*                                                ││
│  └────────────────────────────────────────────────────────────────┘│
│                                                                      │
│  ┌────────────────────────────────────────────────────────────────┐│
│  │  👤 Reseller Panel                          [🟢 Active] [⚙️]  ││
│  │  Self-service portal — orders, balance, credit tracking          ││
│  │  Route: /reseller/*                                            ││
│  └────────────────────────────────────────────────────────────────┘│
│                                                                      │
│  ┌────────────────────────────────────────────────────────────────┐│
│  │  💬 Conversation & AI Agent Hub              [🟢 Active] [⚙️]  ││
│  │  WhatsApp + Messenger chat, AI chatbot, workflow builder        ││
│  └────────────────────────────────────────────────────────────────┘│
│                                                                      │
│  ┌────────────────────────────────────────────────────────────────┐│
│  │  🔗 WooCommerce Importer                   [⚪ Inactive] [⚙️]  ││
│  │  One-time migration from WooCommerce — disable after import     ││
│  │  ⚠️ For one-time use only                                    ││
│  └────────────────────────────────────────────────────────────────┘│
│                                                                      │
│  ⚠️ At least one storefront module must be active                   │
└────────────────────────────────────────────────────────────────────┘
```

## Part A: WooCommerce Importer

### Import Flow (4-Step Wizard)

```
Step 1: Connect
   ├── WooCommerce Store URL
   ├── Consumer Key + Secret
   ├── Store type: Wholesale / Retail / Both
   ├── Test Connection button
   └── Connection status indicator

Step 2: Select Data
   ├── ☐ Products (active, draft, with images)
   ├── ☐ Product Categories
   ├── ☐ Customers (WooCommerce customers → ERP customers)
   ├── ☐ Orders (order history)
   ├── ☐ Store Settings (if applicable)
   └── Estimated counts per entity

Step 3: Import Progress
   ├── Products: 123/450 ████████░░░░ 27%
   ├── Categories: 15/15 ████████████ 100% ✓
   ├── Customers: 610/621 ███████████░ 98% (3 duplicates skipped)
   ├── Orders: 345/1200 ███░░░░░░░░░ 28%
   └── Real-time progress bars (uses Laravel Queue + polling)

Step 4: Complete
   ├── 450 products imported
   ├── 610 customers imported (11 duplicates merged)
   ├── 1200 orders imported
   ├── Download error report (if any)
   └── "Migration Complete — Deactivate WooCommerce Importer"
```

### Import Data Mapping

| WooCommerce Entity | ERP Entity | Mapping Notes |
|-------------------|------------|---------------|
| Product (simple) | products | name, sku, description, price → landing_cost_bdt as base, images |
| Product (variable) | products + product_variants | Each variation → variant, attributes JSON |
| Product Category | categories | name, slug, parent_id (hierarchical) |
| Product Image | products.image / product_variants.image | Download to storage |
| Customer | customers | email → email, phone → phone, role → type (wholesale/retail), billing address |
| Customer (wholesale) | customers (type=wholesale) + external_id | If B2B plugin: map wholesale role → wholesale type |
| Order | sales_orders | Map status, line_items → so_items, total → total_bdt |
| Order Item | so_items | product mapping via SKU, qty, price |
| Coupon | (skip or log) | Coupons not imported in Phase 1 |

### Duplicate Detection During Import

| Entity | Unique Field | Action on Duplicate |
|--------|-------------|---------------------|
| Product | sku | Update existing (merge fields) |
| Category | slug | Skip (use existing) |
| Customer | phone → email fallback | Merge: keep existing, update missing fields |
| Order | woo_order_id (stored in external_id) | Skip entirely |

### Customer Import Special Logic

When importing WooCommerce customers:
1. `external_id` = WooCommerce customer ID
2. If customer exists (by phone match): update missing fields only
3. If customer is new: create with auto-generated `customer_code`
4. Wholesale customers get `type = wholesale`, retail get `type = retail`
5. Customer tags from WooCommerce groups → map to ERP customer_tags

## Part B: Native Storefront Module

### Storefront vs ERP Admin

| Aspect | ERP Admin (/admin/*) | Wholesale Storefront (/wholesale/*) | Retail Storefront (/shop/*) |
|--------|---------------------|------------------------------------|----------------------------|
| Access | Admin, Manager, Salesman | Reseller, Wholesale Customer | End Customer, Guest |
| Auth | Laravel Auth + Spatie | Laravel Auth + Reseller Role | Laravel Auth + Customer Role or Guest |
| UI | Full dashboard, all modules | Catalog, cart, checkout, orders | Catalog, cart, checkout, orders |
| Pricing | All prices visible | Tier-based (logged-in reseller tier) | MRP (everyone sees same price) |
| Payment | Cash, credit, partial | Credit/বাকি, advance | SSLCommerz, bKash, Nagad |
| Layout | Sidebar + Top Bar + Content | Storefront layout | Storefront layout |
| Mobile | React Native app | Responsive web + React Native | Responsive web |

### Storefront Settings per Module

```sql
-- storefront_settings table stores per-module configuration
-- Examples:
-- module: 'wholesale_storefront'
--   settings_key: 'store_name'              → 'ZamZam Wholesale'
--   settings_key: 'show_prices_without_login' → false
--   settings_key: 'min_order_value_bdt'      → 5000
--   settings_key: 'allow_credit_orders'        → true
--   settings_key: 'require_approval'           → false
--   settings_key: 'default_price_tier_id'     → 2 (Silver)
--   settings_key: 'theme_primary_color'        → '#006A4E'

-- module: 'retail_storefront'
--   settings_key: 'store_name'              → 'ZamZam Shop'
--   settings_key: 'show_prices_without_login' → true
--   settings_key: 'delivery_inside_dhaka'     → 60
--   settings_key: 'delivery_outside_dhaka'     → 120
--   settings_key: 'free_delivery_minimum'       → 500
--   settings_key: 'allow_guest_checkout'        → true
--   settings_key: 'theme_primary_color'        → '#6366F1'
```

### Wholesale Storefront Pages

```
/wholesale
├── /catalog              — Product catalog with tier pricing
├── /catalog/search       — Search products
├── /product/{slug}       — Product detail (tier prices, min order qty)
├── /cart                 — Wholesale cart (min order value check)
├── /checkout             — Checkout (credit/advance, delivery)
├── /orders               — Order history
├── /orders/{id}          — Order detail + tracking
├── /profile              — Reseller profile (balance, credit limit)
├── /wishlist             — Saved products (optional, future)
└── /api/v1/*             — Mobile app API endpoints
```

### Retail Storefront Pages

```
/shop
├── /catalog              — Product catalog (MRP visible to all)
├── /catalog/search       — Search products
├── /product/{slug}       — Product detail + delivery estimate
├── /cart                 — Shopping cart
├── /checkout             — Checkout (SSLCommerz, bKash, courier)
├── /checkout/success     — Payment success page
├── /checkout/fail        — Payment failure page
├── /orders               — Order history (requires login)
├── /orders/{id}          — Order detail + tracking
├── /profile              — Customer profile
├── /login                — Customer login
├── /register             — Customer registration
└── /api/v1/*             — Mobile app API endpoints
```

### Module-Based Routing

```php
// RouteServiceProvider.php
Route::middleware(['module.active:wholesale_storefront'])
    ->prefix('wholesale')
    ->name('wholesale.')
    ->group(function () {
        Route::get('/catalog', [CatalogController::class, 'wholesale'])->name('catalog');
        Route::get('/product/{slug}', [ProductController::class, 'show'])->name('product');
        Route::get('/cart', [CartController::class, 'index'])->name('cart');
        // ... more wholesale routes
    });

Route::middleware(['module.active:retail_storefront'])
    ->prefix('shop')
    ->name('shop.')
    ->group(function () {
        Route::get('/catalog', [CatalogController::class, 'retail'])->name('catalog');
        Route::get('/product/{slug}', [ProductController::class, 'show'])->name('product');
        Route::get('/cart', [CartController::class, 'index'])->name('cart');
        // ... more retail routes
    });

Route::middleware(['module.active:reseller_panel'])
    ->prefix('reseller')
    ->name('reseller.')
    ->group(function () {
        // ... reseller routes
    });
```

### ModuleActive Middleware

```php
class EnsureModuleIsActive
{
    public function handle($request, Closure $next, string $module)
    {
        if (!ModuleSetting::isModuleActive($module)) {
            abort(404);
        }
        return $next($request);
    }
}

// Registered in Kernel.php as 'module.active'
```

## Database Tables

### module_settings
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| module | varchar(50) | wholesale_storefront, retail_storefront, reseller_panel, conversation_ai, woocommerce_importer |
| is_active | boolean default false | Module on/off toggle |
| settings | json nullable | Module-specific configuration |
| activated_at | timestamp nullable | When module was activated |
| deactivated_at | timestamp nullable | When module was deactivated |
| created_at | timestamp | |
| updated_at | timestamp |

**Unique constraint:** (module)

**Default seed data:**
```sql
INSERT INTO module_settings (module, is_active) VALUES
('wholesale_storefront', true),
('retail_storefront', true),
('reseller_panel', true),
('conversation_ai', true),
('woocommerce_importer', false);
```

**Business rule:** At least one storefront module (wholesale_storefront or retail_storefront) must be active at all times. Enforced at application level.

### storefront_settings
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| module | enum | wholesale_storefront, retail_storefront |
| settings_key | varchar(100) | Configuration key |
| settings_value | json not null | Configuration value |
| created_at | timestamp | |
| updated_at | timestamp |

**Unique constraint:** (module, settings_key)

**Default wholesale_storefront settings:**
```json
{"store_name": "ZamZam Wholesale", "show_prices_without_login": false, "min_order_value_bdt": 5000, "allow_credit_orders": true, "require_approval": false, "default_price_tier_id": 2}
```

**Default retail_storefront settings:**
```json
{"store_name": "ZamZam Shop", "show_prices_without_login": true, "delivery_inside_dhaka": 60, "delivery_outside_dhaka": 120, "free_delivery_minimum": 500, "allow_guest_checkout": true}
```

### woocommerce_imports
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| store_url | varchar(500) not null | WooCommerce store URL |
| store_type | enum default 'both' | wholesale, retail, both |
| consumer_key | varchar(500) not null | WooCommerce API key |
| consumer_secret | varchar(500) not null | Encrypted API secret |
| import_products | boolean default false | |
| import_categories | boolean default false | |
| import_customers | boolean default false | |
| import_orders | boolean default false | |
| products_total | int default 0 | Total products found |
| products_imported | int default 0 | Successfully imported |
| categories_total | int default 0 | |
| categories_imported | int default 0 | |
| customers_total | int default 0 | |
| customers_imported | int default 0 | |
| orders_total | int default 0 | |
| orders_imported | int default 0 | |
| error_count | int default 0 | |
| error_report_path | varchar(500) nullable | |
| status | enum | connecting, scanning, ready, importing, completed, failed |
| connection_tested | boolean default false | |
| last_error | text nullable | |
| created_by | bigint FK users.id | |
| created_at | timestamp | |
| updated_at | timestamp | |

**Note:** consumer_key and consumer_secret must be encrypted at rest using Laravel's encrypt() helper.

### woocommerce_import_logs (replaces sync_logs for importer)
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| woocommerce_import_id | bigint FK woocommerce_imports.id | |
| entity_type | enum | product, category, customer, order, image |
| wc_entity_id | bigint nullable | WooCommerce entity ID |
| erp_entity_id | bigint nullable | ERP entity ID (after import) |
| erp_entity_type | varchar(100) nullable | products, categories, customers, sales_orders |
| action | enum | created, updated, skipped, failed |
| wc_data | json nullable | Original WooCommerce data |
| error_message | text nullable | |
| created_at | timestamp | |

### product_wc_mappings (kept for import reference)
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| product_id | bigint FK products.id | |
| product_variant_id | bigint FK nullable product_variants.id | |
| wc_product_id | bigint | WooCommerce product ID |
| wc_product_sku | varchar(100) nullable | |
| wc_store_url | varchar(500) | Original store URL |
| imported_at | timestamp | |
| created_at | timestamp | |

**Unique constraint:** (product_id, product_variant_id, wc_store_url)

### category_wc_mappings (kept for import reference)
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| category_id | bigint FK categories.id | |
| wc_category_id | bigint | WooCommerce category ID |
| wc_store_url | varchar(500) | |
| imported_at | timestamp | |
| created_at | timestamp |

**Unique constraint:** (category_id, wc_store_url)

### customer_wc_mappings (kept for import reference)
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| customer_id | bigint FK customers.id | |
| wc_customer_id | bigint | WooCommerce customer ID |
| wc_store_url | varchar(500) | |
| imported_at | timestamp | |
| created_at | timestamp |

**Unique constraint:** (customer_id, wc_store_url)

## API Routes

### Module Management
| Method | Endpoint | Description | Permission |
|--------|----------|-------------|-----------|
| GET | /api/settings/modules | List all modules with status | settings.view |
| PUT | /api/settings/modules/{module} | Toggle module on/off | settings.manage |
| GET | /api/settings/modules/{module}/config | Get module configuration | settings.view |
| PUT | /api/settings/modules/{module}/config | Update module configuration | settings.manage |

### Storefront Settings
| Method | Endpoint | Description | Permission |
|--------|----------|-------------|-----------|
| GET | /api/settings/storefront/{module} | Get storefront settings | settings.view |
| PUT | /api/settings/storefront/{module} | Update storefront settings | settings.manage |

### WooCommerce Importer (one-time)
| Method | Endpoint | Description | Permission |
|--------|----------|-------------|-----------|
| POST | /api/woocommerce-import/connect | Test & save connection | woocommerce.import |
| GET | /api/woocommerce-import/scan | Scan available data counts | woocommerce.import |
| POST | /api/woocommerce-import/start | Start import (select entities) | woocommerce.import |
| GET | /api/woocommerce-import/progress | Get import progress | woocommerce.import |
| GET | /api/woocommerce-import/logs | View import logs | woocommerce.import |
| GET | /api/woocommerce-import/errors | Download error report | woocommerce.import |
| DELETE | /api/woocommerce-import/cleanup | Remove import data & logs | woocommerce.import |

### Wholesale Storefront (Public/Logged-in)
| Method | Endpoint | Description | Auth |
|--------|----------|-------------|------|
| GET | /wholesale/api/catalog | Product catalog (tier pricing) | Customer auth |
| GET | /wholesale/api/catalog/search | Search products | Customer auth |
| GET | /wholesale/api/product/{slug} | Product detail | Customer auth |
| POST | /wholesale/api/cart | Add to cart | Customer auth |
| GET | /wholesale/api/cart | View cart | Customer auth |
| PUT | /wholesale/api/cart/{id} | Update cart item | Customer auth |
| DELETE | /wholesale/api/cart/{id} | Remove cart item | Customer auth |
| POST | /wholesale/api/checkout | Place order | Customer auth |
| GET | /wholesale/api/orders | Order history | Customer auth |
| GET | /wholesale/api/orders/{id} | Order detail | Customer auth |
| GET | /wholesale/api/profile | Customer profile | Customer auth |

### Retail Storefront (Public/Logged-in)
| Method | Endpoint | Description | Auth |
|--------|----------|-------------|------|
| GET | /shop/api/catalog | Product catalog (MRP) | Public |
| GET | /shop/api/catalog/search | Search products | Public |
| GET | /shop/api/product/{slug} | Product detail | Public |
| POST | /shop/api/cart | Add to cart | Session |
| GET | /shop/api/cart | View cart | Session |
| POST | /shop/api/checkout | Place order | Customer auth |
| POST | /shop/api/payment/initiate | Initiate online payment | Customer auth |
| POST | /shop/api/payment/callback | Payment gateway callback | No auth |
| POST | /shop/api/payment/verify | Verify payment | Customer auth |
| GET | /shop/api/orders | Order history | Customer auth |
| GET | /shop/api/orders/{id} | Order detail | Customer auth |
| POST | /shop/api/register | Customer registration | Public |
| POST | /shop/api/login | Customer login | Public |

## Frontend Pages

### ERP Admin — Module Management
| Page | Route | Component |
|------|-------|-----------|
| Module Settings | /settings/modules | Settings/Modules/Index.vue |
| Storefront Settings (Wholesale) | /settings/storefront/wholesale | Settings/Storefront/Wholesale.vue |
| Storefront Settings (Retail) | /settings/storefront/retail | Settings/Storefront/Retail.vue |

### ERP Admin — WooCommerce Importer
| Page | Route | Component |
|------|-------|-----------|
| Import Connect | /woocommerce-import | WooCommerceImport/Connect.vue |
| Import Scan | /woocommerce-import/scan | WooCommerceImport/Scan.vue |
| Import Progress | /woocommerce-import/progress | WooCommerceImport/Progress.vue |
| Import Complete | /woocommerce-import/complete | WooCommerceImport/Complete.vue |
| Import Logs | /woocommerce-import/logs | WooCommerceImport/Logs.vue |

### Wholesale Storefront (Inertia + Vue)
| Page | Route | Component |
|------|-------|-----------|
| Catalog | /wholesale/catalog | Wholesale/Catalog/Index.vue |
| Product Detail | /wholesale/product/{slug} | Wholesale/Catalog/Show.vue |
| Cart | /wholesale/cart | Wholesale/Cart/Index.vue |
| Checkout | /wholesale/checkout | Wholesale/Checkout/Index.vue |
| Orders | /wholesale/orders | Wholesale/Orders/Index.vue |
| Order Detail | /wholesale/orders/{id} | Wholesale/Orders/Show.vue |
| Profile | /wholesale/profile | Wholesale/Profile/Index.vue |

### Retail Storefront (Inertia + Vue)
| Page | Route | Component |
|------|-------|-----------|
| Catalog | /shop/catalog | Shop/Catalog/Index.vue |
| Product Detail | /shop/product/{slug} | Shop/Catalog/Show.vue |
| Cart | /shop/cart | Shop/Cart/Index.vue |
| Checkout | /shop/checkout | Shop/Checkout/Index.vue |
| Payment Success | /shop/checkout/success | Shop/Checkout/Success.vue |
| Payment Fail | /shop/checkout/fail | Shop/Checkout/Fail.vue |
| Login | /shop/login | Shop/Auth/Login.vue |
| Register | /shop/register | Shop/Auth/Register.vue |
| Orders | /shop/orders | Shop/Orders/Index.vue |
| Order Detail | /shop/orders/{id} | Shop/Orders/Show.vue |
| Profile | /shop/profile | Shop/Profile/Index.vue |

## Business Logic

### WooCommerce Import Service

```php
class WooCommerceImportService
{
    private WooCommerceClient $client;

    public function connect(string $url, string $key, string $secret): array
    {
        $this->client = new WooCommerceClient($url, $key, $secret, ['wp_api' => true, 'version' => 'wc/v3']);
        $testResult = $this->client->get('system_status');
        if ($testResult) {
            return ['connected' => true, 'store_name' => $testResult->store_name];
        }
        return ['connected' => false, 'error' => 'Connection failed'];
    }

    public function scan(): array
    {
        return [
            'products' => $this->client->count('products'),
            'categories' => $this->client->count('products/categories'),
            'customers' => $this->client->count('customers'),
            'orders' => $this->client->count('orders'),
        ];
    }

    public function importProducts(WooCommerceImport $import): void
    {
        $page = 1;
        do {
            $products = $this->client->get('products', ['per_page' => 100, 'page' => $page]);
            foreach ($products as $wcProduct) {
                $this->importSingleProduct($wcProduct, $import);
            }
            $page++;
        } while (count($products) === 100);
    }

    public function importSingleProduct(object $wcProduct, WooCommerceImport $import): Product
    {
        // Check if product exists (by SKU)
        $existing = Product::where('sku', $wcProduct->sku)->first();
        if ($existing && $import->duplicate_action === 'skip') {
            $this->logImport($import, 'product', $wcProduct->id, $existing->id, 'skipped');
            return $existing;
        }

        // Create or update product
        $product = Product::updateOrCreate(
            ['sku' => $wcProduct->sku],
            [
                'name' => $wcProduct->name,
                'name_chinese' => $wcProduct->meta_data['chinese_name'] ?? null,
                'description' => strip_tags($wcProduct->description),
                'is_active' => $wcProduct->status === 'publish',
                // WooCommerce price is stored in product_price_tiers, not directly on product
                // after import, set retail tier price from wc price:
                // ProductPriceTier::updateOrCreate([product_id, tier_id], ['price_bdt' => $wcProduct->price])
                // ... more field mappings
            ]
        );

        // Create mapping for reference
        ProductWcMapping::create([
            'product_id' => $product->id,
            'wc_product_id' => $wcProduct->id,
            'wc_product_sku' => $wcProduct->sku,
            'wc_store_url' => $import->store_url,
            'imported_at' => now(),
        ]);

        $this->logImport($import, 'product', $wcProduct->id, $product->id, $existing ? 'updated' : 'created');
        return $product;
    }

    public function importCustomers(WooCommerceImport $import): void
    {
        // Similar pattern: paginate, map, create/update, log
    }

    public function importOrders(WooCommerceImport $import): void
    {
        // Create sales_orders with source='woocommerce'
        // Map line_items to so_items
        // Link customer via customer_wc_mappings
    }

    public function importCategories(WooCommerceImport $import): void
    {
        // Create/update categories, map parent hierarchy
    }

    public function importImages(WooCommerceImport $import): void
    {
        // Download product images from WooCommerce
        // Store in Laravel storage, attach to products
    }
}
```

### Storefront Module Middleware

```php
// app/Http/Middleware/EnsureModuleIsActive.php
class EnsureModuleIsActive
{
    public function handle($request, Closure $next, string $module)
    {
        $moduleSetting = ModuleSetting::where('module', $module)->first();

        if (!$moduleSetting || !$moduleSetting->is_active) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Module not active'], 404);
            }
            abort(404);
        }

        return $next($request);
    }
}

// app/Http/Middleware/EnsureAtLeastOneStorefront.php
class EnsureAtLeastOneStorefront
{
    public function handle($request, Closure $next)
    {
        $wholesale = ModuleSetting::where('module', 'wholesale_storefront')->where('is_active', true)->exists();
        $retail = ModuleSetting::where('module', 'retail_storefront')->where('is_active', true)->exists();

        if (!$wholesale && !$retail) {
            throw new \Exception('At least one storefront module must be active.');
        }

        return $next($request);
    }
}
```

### Wholesale Storefront Price Resolution

```
1. Customer logs in (reseller/customer with wholesale type)
2. Get customer's price_tier_id
3. Lookup product_price_tiers for (product, tier, qty range)
4. If found → show tier price
5. If not found → show base wholesale price
6. Apply min order quantity check (from tier or storefront_settings)
7. Validate min_order_value_bdt from storefront_settings
```

### Retail Storefront Price Resolution

```
1. Show MRP (retail price) — visible to all, no login required for browsing
2. Login required for:
   - Adding to cart
   - Checkout
   - Order history
3. Guest checkout optional (controlled by storefront_settings: allow_guest_checkout)
4. Delivery charge calculated from storefront_settings
5. Free delivery threshold from storefront_settings
```

### Online Payment Flow (SSLCommerz/bKash) — Now Native

```
Old flow (WooCommerce):
  Customer → WooCommerce → SSLCommerz → WooCommerce → Webhook → ERP

New flow (Native):
  Customer → Laravel Storefront → SSLCommerz → Callback → ERP
  (No WooCommerce middleware — direct payment integration)
```

```php
class PaymentService
{
    public function initiatePayment(SalesOrder $order, string $gateway): array
    {
        // Direct integration with payment gateway from Laravel
        // No WooCommerce dependency

        return match($gateway) {
            'sslcommerz' => $this->initiateSSLCommerz($order),
            'bkash' => $this->initiateBkash($order),
            'nagad' => $this->initiateNagad($order),
            default => throw new \Exception('Unsupported gateway'),
        };
    }

    public function handleCallback(array $data): PaymentResult
    {
        // Verify callback, validate transaction
        // Update online_payments table
        // Update order status
        // Send confirmation notification
    }
}
```

## Events & Listeners

| Event | Listener | Description |
|-------|----------|-------------|
| ModuleActivated | ClearModuleCache | Clear cached module settings |
| ModuleDeactivated | DisableModuleRoutes | Prevent access to deactivated module |
| StorefrontOrderPlaced | ReserveStock | Reserve stock for storefront order |
| StorefrontPaymentReceived | UpdateOrderPayment | Mark order as paid |
| StorefrontOrderShipped | NotifyCustomer | Send tracking info |
| WooCommerceImportStarted | LockImportModule | Prevent concurrent imports |
| WooCommerceImportCompleted | NotifyAdmin | Send import summary |
| WooCommerceImportFailed | NotifyAdmin | Alert admin of import failure |

## Events for Module Toggle

```php
// app/Events/ModuleActivated.php
class ModuleActivated
{
    public function __construct(public string $module) {}
}

// app/Events/ModuleDeactivated.php
class ModuleDeactivated
{
    public function __construct(public string $module) {}
}

// app/Listeners/ClearModuleCache.php
class ClearModuleCache
{
    public function handle(ModuleActivated|ModuleDeactivated $event): void
    {
        Cache::forget('module_settings');
        Cache::forget("module_{$event->module}");
    }
}
```

## Validation Rules

### Module Toggle
```php
'module' => 'required|in:wholesale_storefront,retail_storefront,reseller_panel,conversation_ai,woocommerce_importer',
'is_active' => 'required|boolean',
// Business rule: cannot deactivate both storefronts simultaneously
```

### WooCommerce Import Connection
```php
'store_url' => 'required|url|max:500',
'store_type' => 'required|in:wholesale,retail,both',
'consumer_key' => 'required|string|max:500',
'consumer_secret' => 'required|string|max:500',
```

### Storefront Settings
```php
// Wholesale
'store_name' => 'required|string|max:255',
'show_prices_without_login' => 'boolean',
'min_order_value_bdt' => 'nullable|numeric|min:0',
'allow_credit_orders' => 'boolean',
'default_price_tier_id' => 'nullable|exists:price_tiers,id',

// Retail
'store_name' => 'required|string|max:255',
'show_prices_without_login' => 'boolean',
'delivery_inside_dhaka' => 'required|numeric|min:0',
'delivery_outside_dhaka' => 'required|numeric|min:0',
'free_delivery_minimum' => 'nullable|numeric|min:0',
'allow_guest_checkout' => 'boolean',
```

## Developer Notes

1. **WooCommerce Importer is one-time only** — after migration, deactivate the module. wc_mappings tables retained for reference.
2. **Module toggle uses cache** — `ModuleSetting::isModuleActive()` is cached in Redis with 5-min TTL. Cache cleared on toggle.
3. **Storefront shares same Laravel app** — no separate deployment. Routes are conditionally loaded based on module state.
4. **Wholesale storefront requires authentication** — customers must log in to see prices and place orders.
5. **Retail storefront allows guest browsing** — prices visible, cart requires session, checkout requires account.
6. **Payment gateways integrated directly** — SSLCommerz, bKash, Nagad APIs called from Laravel. No WooCommerce dependency.
7. **WooCommerce customer_key and consumer_secret encrypted** at rest using `encrypt()` helper. Never store in .env or config.
8. **Import uses Laravel Queue** — large imports (>100 records) processed via Redis queue. Progress tracked via `woocommerce_imports` table.
9. **Image import** — WooCommerce product images downloaded to `storage/app/public/products/` via Laravel Queue.
10. **After import, `sales_orders.source` = 'woocommerce'** — distinguishes imported orders from new native orders.
11. **Native storefront `sales_orders.source` = 'wholesale_storefront'** or **'retail_storefront'** — distinguishes native orders.
12. **Route model binding** — Product slug used for storefront URLs. Product must have `slug` field (auto-generated from name).
13. **Storefront layouts** — separate Inertia layouts from admin. `WholesaleLayout.vue` and `ShopLayout.vue`.
14. **SEO consideration** — retail storefront pages use Inertia SSR mode for search engine indexing.
15. **Cart storage** — wholesale cart in database (persistent), retail cart in session/database (hybrid).
16. **Credit check on wholesale storefront** — before order placement, verify `outstanding_balance_bdt + order_total <= credit_limit_bdt`.
17. **At least one storefront must remain active** — enforced by `EnsureAtLeastOneStorefront` middleware on the toggle endpoint.