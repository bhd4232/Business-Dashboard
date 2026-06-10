# ZamZam ERP — Agent Rules & Development Standards

This file is read automatically by every AI agent working on this project.
All rules here are **mandatory** and override any other default behaviour.

---

## Stack

- **Backend:** Laravel 13, PHP 8.3
- **Frontend:** Vue 3 (Composition API `<script setup>`), Inertia.js, Tailwind CSS, Lucide icons
- **DB:** MySQL
- **API pattern:** Web controller renders Inertia pages; API controller (`/api/v1/…`) handles all data mutations via `window.axios`

---

## ⚠️ CRUD Module Checklist — NEVER skip any item

Every time a new resource / module is created (e.g. Sales Orders, Invoices, Customers…),
**all of the following files must be created in a single pass**:

### Backend

| # | File | Notes |
|---|------|-------|
| 1 | `database/migrations/…_create_{module}_tables.php` | Schema |
| 2 | `app/Models/{Namespace}/{Model}.php` | Eloquent model with fillable, casts, relationships, scopes |
| 3 | `app/Http/Requests/{Namespace}/Store{Model}Request.php` | Validation for create |
| 4 | `app/Http/Requests/{Namespace}/Update{Model}Request.php` | Validation for update (or reuse Store with `sometimes`) |
| 5 | `app/Services/{Namespace}/{Module}Service.php` | Business logic |
| 6 | `app/Http/Controllers/Web/Admin/{Namespace}/{Model}Controller.php` | Inertia page controllers: `index`, `create`, `show`, **`edit`** |
| 7 | `app/Http/Controllers/Api/V1/{Namespace}/{Model}Controller.php` | JSON API: `index`, `store`, `show`, `update`, `destroy` |
| 8 | `routes/modules/{module}.php` | Both web routes AND `api/v1` routes |

**The web controller `edit()` method MUST always be present** and must call
`Inertia::render("…/Edit", […])` passing the existing record **plus** any
dropdown data needed (same as `create()`).

### Frontend (Vue pages) — ALL FOUR ARE MANDATORY

| # | File | Notes |
|---|------|-------|
| 1 | `resources/js/Pages/{Module}/Index.vue` | List + filters |
| 2 | `resources/js/Pages/{Module}/Create.vue` | Create form; submits `POST /api/v1/…` |
| 3 | **`resources/js/Pages/{Module}/Edit.vue`** | **ALWAYS required. No exceptions.** Pre-populates form from prop; submits `PUT /api/v1/…/{id}`; on success redirects to Show |
| 4 | `resources/js/Pages/{Module}/Show.vue` | Detail view; Edit button links to `{module}.edit` route |

> ### CRITICAL RULE
> `Edit.vue` **MUST** be created at the same time as `Create.vue`.
> There is NO scenario where Create.vue exists but Edit.vue does not.
> Edit.vue = Create.vue + existing record pre-population + PUT method + cancel → Show.

---

## Edit.vue Minimum Contract

```vue
<script setup>
const props = defineProps({
  record:    { type: Object, required: true },
  // same dropdown props as Create (suppliers, currencies, etc.)
})

// Pre-populate — strip T00:00:00Z from date fields
const form = reactive({
  field1: props.record.field1,
  some_date: props.record.some_date?.split("T")[0] ?? props.record.some_date,
})

async function submit() {
  await window.axios.put(`/api/v1/{resource}/${props.record.id}`, payload)
  router.visit(route("{resource}.show", props.record.id))
}
</script>
```

---

## API Routes Ordering Rule

In route files, **static path segments MUST appear before wildcard `{param}` segments**:

```php
// CORRECT
Route::get("/products/search",  [...]);
Route::get("/products/trashed", [...]);
Route::get("/products/{product}", [...]);

// WRONG — {product} swallows "search" and "trashed"
Route::get("/products/{product}", [...]);
Route::get("/products/search",  [...]);
```

---

## Web Controller edit() Pattern

```php
public function edit(MyModel $myModel): Response
{
    $this->authorize("my_module.edit");

    return Inertia::render("MyModule/Edit", [
        "record"   => $myModel->load(["relation"]),
        "dropdown" => RelatedModel::active()->select("id","name")->get(),
    ]);
}
```

---

## BackButton Component Limitation

`<BackButton to="route.name" />` does NOT support route params.
For parameterised back links use Inertia `<Link>` directly:

```vue
<Link :href="route("resource.show", record.id)" class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-slate-200 bg-white text-slate-600 text-sm font-medium hover:bg-slate-50 transition-all shadow-sm group">
  <ArrowLeftIcon class="w-4 h-4 group-hover:-translate-x-0.5 transition-transform" />
  {{ record.reference_number }}
</Link>
```

---

## Icon Usage Rules

### Module/Section Level Icons (≥ w-8)
Use `<ThreeDIcon name="..." size="md|lg|xl" />` (Icons8 Fluency PNG):
- Sidebar navigation items
- Page headers (beside h1)
- Section headers
- Stat cards
- Dashboard widgets
- Empty state illustrations

### Inline/Action Level Icons (< w-6)
Use `<Icon3D name="Edit" size="sm" />` (Lucide + CSS 3D drop-shadow) for:
- Buttons (edit, delete, save, cancel)
- Table action columns
- Form field prefix/suffix
- Breadcrumb separators
- Badge/tag icons
- Dropdown menu items

> **Never** use bare Lucide icons without the `icon-3d` class or `Icon3D` wrapper for action-level icons.
> **Never** use `ThreeDIcon` at sizes smaller than `sm` (w-6).

---

## ⚠️ Dark Mode — MANDATORY FOR ALL NEW UI

> **এই রুল কোনো ব্যতিক্রম ছাড়াই সকল নতুন ফাইলে প্রযোজ্য:**
> Pages, modals, popups, drawers, components, forms, tables, cards — সবকিছু একই কমিটে dark mode সহ তৈরি করতে হবে।
> Dark mode কোনো "পরে করব" বিষয় নয় — এটি প্রথম থেকেই বাধ্যতামূলক।
>
> ❌ শুধু `bg-white text-slate-900` → **REJECTED**
> ✅ `bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100` → **ACCEPTED**
>
> কোনো নতুন Vue component/page dark mode ছাড়া complete ধরা হবে না।

## Dark Mode Rules

Every component **MUST** include `dark:` variants for:

| Element | Light | Dark |
|---------|-------|------|
| Page background | `bg-slate-50` | `dark:bg-slate-900` |
| Card/panel surface | `bg-white` | `dark:bg-slate-800` |
| Elevated surface | `bg-slate-50` | `dark:bg-slate-700` |
| Primary text | `text-slate-900` | `dark:text-slate-100` |
| Secondary text | `text-slate-600` | `dark:text-slate-300` |
| Muted text | `text-slate-400` | `dark:text-slate-400` |
| Borders | `border-slate-200` | `dark:border-slate-700` |
| Subtle borders | `border-slate-100` | `dark:border-slate-700` |
| Inputs | `bg-white border-slate-200` | `dark:bg-slate-800 dark:border-slate-600 dark:text-slate-100` |
| Hover rows | `hover:bg-slate-50` | `dark:hover:bg-slate-700/50` |
| Active state | `bg-indigo-50 text-indigo-700` | `dark:bg-indigo-950 dark:text-indigo-300` |

Dark mode is toggled via `.dark` class on `<html>`. Use `useThemeStore().toggleDark()`.

---

## Color Token Rules

**Hard-coded hex values (#6366f1 etc.) are FORBIDDEN.** Always use Tailwind tokens:

| Token range | Purpose | Runtime? |
|-------------|---------|---------|
| `primary-50` → `primary-700` | Brand accent (switchable) | ✅ Runtime CSS variable |
| `brand-600` / `brand-700` | BD Green accent (static) | ❌ Static |
| `slate-*` | Neutral surfaces/text | ❌ Static |

- `text-primary-600` resolves to the user's chosen theme color at runtime
- Never write `text-indigo-600` when you mean the primary brand color — use `text-primary-600`
- `bg-indigo-*` is only acceptable for hard-wired indigo accents unrelated to the theme

---

## Button Loading State Pattern

All async-action buttons MUST use this pattern:

```vue
<button
  @click="submit"
  :disabled="loading"
  class="inline-flex items-center gap-2 px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white
         text-sm font-semibold rounded-xl transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
>
  <LoaderIcon v-if="loading" class="w-4 h-4 animate-spin" />
  <Icon3D v-else name="Save" size="sm" color="text-white" />
  {{ loading ? 'Saving...' : 'Save' }}
</button>
```

- Import `LoaderIcon` from `lucide-vue-next` and `Icon3D` from `@/Components/UI/Icon3D.vue`
- Use `disabled:opacity-50 disabled:cursor-not-allowed` on every submit button

---

## Naming Conventions

| Concept | Convention | Example |
|---------|-----------|---------|
| Route names | `kebab-case.action` | `purchase-orders.edit` |
| Vue page path | `Domain/Resource/Action` | `Procurement/PurchaseOrders/Edit` |
| API endpoint | `/api/v1/kebab-case` | `/api/v1/purchase-orders` |
| Permission slug | `snake_case.action` | `purchase_orders.edit` |
| Model namespace | `App\Models\{Domain}\{Model}` | `App\Models\Procurement\PurchaseOrder` |
