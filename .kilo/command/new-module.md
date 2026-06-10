# /new-module

Create a complete CRUD module for ZamZam ERP.

## Usage
```
/new-module <ModuleName> [domain]
```
Example: `/new-module SalesOrder Sales`

## What this command does

Scaffolds **every required file** for a new CRUD module in one shot.
No file may be skipped.

---

## Mandatory File Checklist

### 1. Migration
`database/migrations/YYYY_MM_DD_HHMMSS_create_{snake}_tables.php`
- Create main table + any child/pivot tables
- Respect FK order (referenced tables first)

### 2. Model
`app/Models/{Domain}/{Model}.php`
- `$fillable`, `casts()`, relationships, `scopeActive()`

### 3. Form Requests
- `app/Http/Requests/{Domain}/Store{Model}Request.php`
- `app/Http/Requests/{Domain}/Update{Model}Request.php` (or use `sometimes` in Store)

### 4. Service
`app/Services/{Domain}/{Domain}Service.php`
- `create{Model}()`, `update{Model}()`, any status-transition methods

### 5. Web Controller (Inertia)
`app/Http/Controllers/Web/Admin/{Domain}/{Model}Controller.php`

Must have ALL four methods:
```php
public function index(): Response   { ... }
public function create(): Response  { ... }
public function show({Model} $m): Response { ... }
public function edit({Model} $m): Response {
    // REQUIRED — renders Edit Vue page
    return Inertia::render("{Domain}/{Models}/Edit", [
        "record" => $m->load([...]),
        // same dropdown data as create()
    ]);
}
```

### 6. API Controller (JSON)
`app/Http/Controllers/Api/V1/{Domain}/{Model}Controller.php`
- `index`, `store`, `show`, `update`, `destroy`
- Optionally: `confirm`, `cancel`, `trashed`, `restore`, `forceDelete`

### 7. Routes
`routes/modules/{module}.php`

```php
// Web routes
Route::prefix("{kebab}")->name("{kebab}.")->group(function () {
    Route::get("/",            [WebCtrl::class, "index"])->name("index");
    Route::get("/create",      [WebCtrl::class, "create"])->name("create");
    Route::get("/{m}",         [WebCtrl::class, "show"])->name("show");
    Route::get("/{m}/edit",    [WebCtrl::class, "edit"])->name("edit");   // MUST exist
});

// API routes — static before {param}
Route::prefix("api/v1")->middleware("web")->group(function () {
    Route::get("/{kebab}/trashed", [ApiCtrl::class, "trashed"]);  // static first!
    Route::get("/{kebab}",         [ApiCtrl::class, "index"]);
    Route::post("/{kebab}",        [ApiCtrl::class, "store"]);
    Route::get("/{kebab}/{m}",     [ApiCtrl::class, "show"]);
    Route::put("/{kebab}/{m}",     [ApiCtrl::class, "update"]);
    Route::delete("/{kebab}/{m}",  [ApiCtrl::class, "destroy"]);
});
```

### 8. Vue Pages — ALL FOUR ARE REQUIRED

| File | Key behaviour |
|------|--------------|
| `resources/js/Pages/{Domain}/{Models}/Index.vue` | Table + search/filter + pagination |
| `resources/js/Pages/{Domain}/{Models}/Create.vue` | Form → `POST /api/v1/{kebab}` |
| **`resources/js/Pages/{Domain}/{Models}/Edit.vue`** | **MANDATORY** — same form as Create, pre-populated from `record` prop → `PUT /api/v1/{kebab}/{id}` → redirect to Show |
| `resources/js/Pages/{Domain}/{Models}/Show.vue` | Read-only detail + action buttons (Confirm, Cancel, Edit, Delete) |

> ### ⚠️ Dark Mode Requirement (NON-NEGOTIABLE)
> উপরের চারটি Vue page-এর প্রতিটিতে অবশ্যই `dark:` Tailwind variants থাকতে হবে।
> শুধু light mode class লিখলে page **incomplete** ধরা হবে।
>
> **প্রতিটি element-এর জন্য নিচের mapping অনুসরণ করো (AGENTS.md Dark Mode Rules দেখো):**
>
> | Element | Light | Dark |
> |---------|-------|------|
> | Page background | `bg-slate-50` | `dark:bg-slate-900` |
> | Card/panel | `bg-white` | `dark:bg-slate-800` |
> | Primary text | `text-slate-900` | `dark:text-slate-100` |
> | Secondary text | `text-slate-600` | `dark:text-slate-300` |
> | Borders | `border-slate-200` | `dark:border-slate-700` |
> | Inputs | `bg-white border-slate-200` | `dark:bg-slate-800 dark:border-slate-600 dark:text-slate-100` |
> | Table row hover | `hover:bg-slate-50` | `dark:hover:bg-slate-700/50` |
>
> Modals, drawers, popups — এগুলোও একই নিয়মে dark mode compatible হতে হবে।

---

## Edit.vue Rules

1. Props must include the `record` object and every dropdown list that Create receives
2. `form` is pre-populated from `props.record`; date fields must strip the ISO-8601 time part:
   ```js
   some_date: props.record.some_date?.split("T")[0] ?? props.record.some_date
   ```
3. Submit goes to `PUT /api/v1/{kebab}/${props.record.id}`
4. On success: `router.visit(route("{kebab}.show", props.record.id))`
5. Cancel button → `route("{kebab}.show", record.id)`
6. "Back" link must use Inertia `<Link>` with `route("{kebab}.show", record.id)` (BackButton does not support params)

---

## Post-scaffold steps (always run)

```bash
php artisan migrate
npm run build
```

Verify no TypeScript/Vite errors before marking task complete.
