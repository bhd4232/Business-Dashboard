# Business Dashboard — Code Audit Report

**Repository:** https://github.com/bhd4232/Business-Dashboard  
**Date:** 2026-06-13  
**Stack:** Laravel + Filament + Tailwind CSS + Vite

---

## সারসংক্ষেপ (Summary)

| Category | Count |
|---|---|
| 🔴 Critical Bugs | 4 |
| 🟡 Warnings | 7 |
| 🔵 Improvements | 8 |
| ✅ Good Practices | 6 |

---

## 🔴 Critical — Bug ও Logic Error

### 1. Order model-এ unused fillable fields — data inconsistency risk

Order model-এ `product_id`, `unit_price`, `quantity` fillable হিসেবে আছে, কিন্তু order totals শুধু `OrderItem` থেকে calculate হয়। এই fields কোনো business logic-এ use হয় না — এগুলো থাকলে confusing এবং accidentally overwrite হওয়ার risk আছে।

```php
// Order model $fillable-এ:
'product_id', 'unit_price', 'quantity'  // ← এগুলো orphaned, remove করুন
```

---

### 2. Purchase `syncTotalsAndStock()`-এ N+1 query problem

প্রতিটি purchase save হলে `syncTotalsAndStock()` কল হয়, যেটা `$this->items()->get()` করে। কিন্তু purchase items save হওয়ার সময়ও parent purchase re-save হতে পারে — এই chain বড় purchase-এ অনেক redundant query তৈরি করতে পারে। Performance bottleneck হবে।

```php
static::saved(fn(Purchase $p) => $p->syncTotalsAndStock());
// triggers on every save — items loop-এ eager loading যোগ করুন
```

**Fix:** `$this->items` eager load করে একটা query-তে শেষ করুন।

---

### 3. `deploy.yml`-এ hardcoded placeholder path — deployment break করবে

CI/CD workflow-এ `cd /path/to/your/project` এখনো placeholder আছে। এই workflow main branch-এ push হলেই trigger হয়, কিন্তু SSH deploy step fail করবে কারণ path real না।

```yaml
script: cd /path/to/your/project  # ← actual server path দিয়ে replace করুন
```

**Fix:** GitHub Secret (`${{ secrets.DEPLOY_PATH }}`) হিসেবে store করে inject করুন।

---

### 4. `User.effectiveRole()` — নতুন user role null হলে super_admin পেয়ে যাবে

নতুন user create হলে `role` field null হতে পারে। `effectiveRole()` তখন `'super_admin'` return করে — এটা একটা security vulnerability।

```php
public function effectiveRole(): string
{
    return $this->getAttribute('role') ?: 'super_admin';  // ← dangerous default
}
```

**Fix:** Default value `'viewer'` বা `'staff'` করুন। Super admin explicitly assign হওয়া উচিত।

---

## 🟡 Warning — সতর্কতামূলক সমস্যা

### 1. `.env.example`-এ `APP_NAME = "Laravel"` — production-এ ভুল app name

`APP_NAME`, `APP_KEY`, `MAIL_FROM_ADDRESS` সব default Laravel placeholder। Deployment checklist আছে কিন্তু `.env.example` নিজেই correct default দেয় না।

**Fix:** `.env.example`-এ project-specific placeholder বসান।

---

### 2. `ReportService.profit()` — historical cost নয়, live cost_price use করছে

Profit report-এ product-এর *current* `cost_price` use হচ্ছে, sale-এর সময়কার cost নয়। পুরনো sale-এর profit ভুলভাবে calculate হবে যদি cost_price পরে update হয়।

```php
$cost = (float)($item->product?->cost_price ?? 0) * $qty;
// ← historical accuracy নেই
```

**Fix:** `OrderItem`-এ `unit_cost` (snapshot) field রাখুন এবং সেটা থেকে calculate করুন।

---

### 3. `routes/web.php`-এ `purchaseCustomCostLabels()` global function — collision risk

এই helper function `web.php`-এর ভেতর global scope-এ define করা। Large app-এ অন্য package বা file এই নামে কিছু define করলে conflict হবে।

**Fix:** `app/Helpers/PurchaseHelper.php`-এ static method হিসেবে move করুন এবং `composer.json` autoload-এ যোগ করুন।

---

### 4. `composer.json`-এ exact version pin — security patch পাবে না

```json
"filament/filament": "4.0"   // ← bugfix ও security patch আসবে না
```

**Fix:**
```json
"filament/filament": "^4.0"
```

---

### 5. `nixpacks.toml`-এ `NIXPACKS_PHP_FALLBACK_PATH` খালি

Deployment config-এ `NIXPACKS_PHP_FALLBACK_PATH = ""` — Coolify/nixpacks-এ এটা 404 fallback routing ভাঙতে পারে।

**Fix:**
```toml
NIXPACKS_PHP_FALLBACK_PATH = "/index.php"
```

---

### 6. Duplicate label list — `Product.COMING_SOON_PURCHASE_PRODUCTS` এবং `Purchase.CHINA_TO_BD_COST_FIELDS`

দুই জায়গায় same label list maintain করা হচ্ছে (Machine Purchase, Inspection, ইত্যাদি)। একটা update হলে আরেকটা miss হওয়ার chance আছে।

**Fix:** একটা `config/purchase_cost_fields.php` বা shared constant তৈরি করুন — single source of truth।

---

### 7. `deploy.yml`-এ automated test step নেই — broken code deploy হতে পারে

Workflow সরাসরি build করে deploy করে, কিন্তু `php artisan test` বা `phpunit` run করে না।

**Fix:** deploy step-এর আগে এটা যোগ করুন:

```yaml
- name: Run Tests
  run: php artisan test --env=testing
```

---

## 🔵 Improvement — উন্নতির সুযোগ

### 1. Product model-এ দুটো price field: `price` এবং `sale_price`

Stock report-এ `$product->sale_price ?? $product->price` দিয়ে fallback করছে — এটা legacy technical debt। একটা canonical field থাকা উচিত।

**Fix:** `sale_price`-কে primary রেখে `price` deprecate করুন, অথবা `getSellingPriceAttribute()` accessor তৈরি করুন।

---

### 2. CSV export সম্পূর্ণ collection memory-তে load করে

Report export route সব data একবারে `->get()` করে। হাজার হাজার records থাকলে PHP memory limit exceed করবে।

```php
// এখন:
$reports->sales($from, $to)->map(...)

// Fix:
$reports->sales($from, $to)->cursor()->each(function($order) use ($handle) {
    fputcsv($handle, [...]);
});
```

---

### 3. `StockMovement` validation exception model-এর ভেতর থেকে throw হচ্ছে

Model-এর `booted` hook থেকে `ValidationException` throw হচ্ছে। API context-এ unexpected behavior হতে পারে।

**Fix:** `StockMovementService` class-এ move করুন যেখানে HTTP/console context আলাদা handle করা যাবে।

---

### 4. Purchase number format-এ collision risk

```php
'PUR-' . now()->format('Ymd') . '-' . Str::upper(Str::random(5))
// High volume-এ collision theoretically possible
```

**Fix:** DB-এর sequential ID suffix যোগ করুন: `'PUR-' . now()->format('Ymd') . '-' . str_pad($id, 5, '0', STR_PAD_LEFT)`

---

### 5. `User.rolePermissions()`-এ `Schema::hasTable()` প্রতি request-এ call হচ্ছে

```php
Schema::hasTable('user_roles')  // ← প্রতি permission check-এ extra DB query
```

**Fix:** `Cache::remember()` দিয়ে এই check cache করুন।

---

### 6. README.md সম্পূর্ণ Laravel boilerplate

README এখনো Laravel default text। `PROJECT_GUIDE.md` আছে ঠিকই, কিন্তু README-তে অন্তত project overview, quick setup, এবং link থাকা উচিত।

---

### 7. Deploy workflow-এ `npm ci + npm run build` দুইবার run হচ্ছে

GitHub Actions-এ একবার, তারপর SSH-তে গিয়ে আবার। Build artifacts server-এ transfer করলে time ও bandwidth বাঁচবে।

---

### 8. Print route-এ শুধু `auth` middleware, role/permission check নেই

```php
Route::middleware('auth')->get('/admin/orders/{order}/print', ...)
// যেকোনো authenticated user access করতে পারে
```

**Fix:** Filament `canViewAny` বা custom policy যোগ করুন।

---

## ✅ Good Practices — যা ঠিকঠাক আছে

| # | কী ভালো |
|---|---|
| 1 | **Stock movement validation শক্তিশালী** — negative stock block, signed adjustment, projected stock calculation সব সঠিক |
| 2 | **Audit trail ও role system thoughtful** — super admin protection, self-deactivation block সব আছে |
| 3 | **`saveQuietly()` দিয়ে infinite loop এড়ানো** — model event loop ঠেকাতে সঠিক pattern |
| 4 | **Custom cost fields architecture চমৎকার** — JSON `custom_costs` + fixed `CHINA_TO_BD` fields, extensible design |
| 5 | **`PROJECT_GUIDE.md` ও `ERP_PHASE_ROADMAP.md` professional standard** — phase-based roadmap ও development rules ভালোভাবে documented |
| 6 | **Feature test coverage বেশ ভালো** — 12টি feature test file, phase-wise coverage আছে |

---

## Priority অনুযায়ী কাজের ক্রম

```
1. effectiveRole() security bug → fix immediately
2. deploy.yml placeholder path → fix before next deploy
3. deploy.yml-এ test step যোগ করুন
4. purchaseCustomCostLabels() → Helper class-এ move করুন
5. Profit report historical cost snapshot → next sprint
6. CSV export lazy loading → performance tuning phase
7. README update → documentation sprint
```

---

*Report generated via manual code inspection of public repository.*
