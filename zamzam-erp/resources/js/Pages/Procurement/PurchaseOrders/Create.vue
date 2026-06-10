<template>
  <AppLayout>
    <Head title="New Purchase Order" />

    <div class="mb-6">
      <BackButton label="Purchase Orders" to="purchase-orders.index" />
      <h1 class="text-2xl font-semibold text-slate-900 dark:text-slate-100">New Purchase Order</h1>
    </div>

    <form @submit.prevent="submit">
      <!-- Basic Info -->
      <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 mb-4">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center gap-2">
          <ClipboardListIcon class="w-4 h-4 text-purple-600" />
          <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-200">Order Information</h2>
        </div>
        <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Supplier <span class="text-red-500">*</span></label>
            <select v-model="form.supplier_id"
              class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 px-3 py-2 text-sm focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 bg-white"
              :class="{ 'border-red-400': errors.supplier_id }">
              <option value="">Select supplier</option>
              <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name_english }}</option>
            </select>
            <p v-if="errors.supplier_id" class="mt-1 text-xs text-red-600">{{ errors.supplier_id }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Currency <span class="text-red-500">*</span></label>
            <select v-model="form.currency_id"
              class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 px-3 py-2 text-sm focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 bg-white">
              <option v-for="c in currencies" :key="c.id" :value="c.id">{{ c.code }} - {{ c.name }}</option>
            </select>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Exchange Rate (BDT) <span class="text-red-500">*</span></label>
            <div class="relative">
              <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 dark:text-slate-400 text-sm">1 CNY =</span>
              <input v-model.number="form.exchange_rate" type="number" step="0.000001" min="0" placeholder="16.50"
                class="w-full pl-20 pr-12 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:placeholder:text-slate-500 dark:focus:ring-primary-900/30 px-3 py-2 text-sm focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 font-mono"
                :class="{ 'border-red-400': errors.exchange_rate }" />
              <span class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 dark:text-slate-400 text-sm">BDT</span>
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Order Date <span class="text-red-500">*</span></label>
            <input v-model="form.order_date" type="date"
              class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:focus:ring-primary-900/30 px-3 py-2 text-sm focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100"
              :class="{ 'border-red-400': errors.order_date }" />
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Expected Delivery</label>
            <input v-model="form.expected_delivery_date" type="date"
              class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:focus:ring-primary-900/30 px-3 py-2 text-sm focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100" />
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Notes</label>
            <input v-model="form.notes" type="text"
              class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:focus:ring-primary-900/30 px-3 py-2 text-sm focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100" />
          </div>
        </div>
      </div>

      <!-- Items -->
      <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 mb-4">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
          <div class="flex items-center gap-2">
            <PackageIcon class="w-4 h-4 text-purple-600" />
            <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-200">Items</h2>
          </div>
          <button type="button" @click="addItem"
            class="text-sm text-indigo-600 hover:text-indigo-700 dark:text-primary-400 dark:hover:text-primary-300 font-medium flex items-center gap-1">
            <PlusIcon class="w-3 h-3" /> Add Item
          </button>
        </div>
        <div class="p-4">
          <p v-if="errors.items" class="text-sm text-red-600 mb-3">{{ errors.items }}</p>

          <div v-if="form.items.length > 0" class="overflow-x-auto">
            <table class="w-full mb-3">
              <thead>
                <tr class="bg-slate-50 dark:bg-slate-700/50 rounded-lg">
                  <th class="text-left text-xs font-medium text-slate-600 dark:text-slate-400 px-3 py-2">Product</th>
                  <th class="text-right text-xs font-medium text-slate-600 dark:text-slate-400 px-3 py-2 w-32">Price (CNY)</th>
                  <th class="text-right text-xs font-medium text-slate-600 dark:text-slate-400 px-3 py-2 w-24">Qty</th>
                  <th class="text-right text-xs font-medium text-slate-600 dark:text-slate-400 px-3 py-2 w-28" title="Approximate weight per unit in kg">
                    Wt/pc (kg)
                  </th>
                  <th class="text-right text-xs font-medium text-slate-600 dark:text-slate-400 px-3 py-2 w-32">Total (CNY)</th>
                  <th class="w-8"></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(item, idx) in form.items" :key="idx" class="border-b border-slate-100 dark:border-slate-700">
                  <!-- Product search cell -->
                  <td class="px-3 py-2 relative" style="min-width: 220px;">
                    <div class="relative">
                      <input
                        v-model="item.product_search"
                        type="text"
                        placeholder="Type to search product..."
                        autocomplete="off"
                        @input="onProductInput(idx)"
                        @focus="item.showDropdown = item.searchResults.length > 0"
                        @blur="closeDropdown(idx)"
                        class="w-full rounded-lg border px-3 py-1.5 text-sm focus:outline-none pr-7"
                        :class="[
                          item.product_id
                            ? 'border-green-400 bg-green-50 focus:border-green-500'
                            : errors[`items.${idx}.product_id`]
                              ? 'border-red-400'
                              : 'border-slate-300 focus:border-indigo-500'
                        ]"
                      />
                      <!-- search spinner or check icon -->
                      <span class="absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none">
                        <LoaderIcon v-if="item.searching" class="w-3.5 h-3.5 animate-spin text-indigo-400" />
                        <CheckIcon v-else-if="item.product_id" class="w-3.5 h-3.5 text-green-500" />
                        <SearchIcon v-else class="w-3.5 h-3.5" />
                      </span>
                      <!-- Dropdown -->
                      <div
                        v-if="item.showDropdown && item.searchResults.length > 0"
                        class="absolute z-50 top-full mt-1 left-0 right-0 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg shadow-xl max-h-56 overflow-y-auto"
                      >
                        <template v-for="p in item.searchResults" :key="p.id">
                          <!-- Product without variants -->
                          <button
                            v-if="!p.has_variants || !p.variants?.length"
                            type="button"
                            @mousedown.prevent="selectProduct(idx, p)"
                            class="w-full text-left px-3 py-2 hover:bg-indigo-50 dark:hover:bg-indigo-900/20 transition-colors border-b border-slate-100 dark:border-slate-700 last:border-0"
                          >
                            <div class="text-sm font-medium text-slate-800 dark:text-slate-200 truncate">{{ p.name }}</div>
                            <div class="text-xs text-slate-400 mt-0.5 flex gap-3">
                              <span>{{ p.sku }}</span>
                              <span v-if="p.name_chinese" class="text-slate-500">{{ p.name_chinese }}</span>
                              <span v-if="p.weight_kg" class="text-teal-600">{{ p.weight_kg }} kg</span>
                            </div>
                          </button>
                          <!-- Product with variants -->
                          <template v-else>
                            <div class="px-3 py-1.5 text-xs font-semibold text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-700/50 sticky top-0 border-b border-slate-100 dark:border-slate-700">
                              {{ p.name }}
                              <span v-if="p.name_chinese" class="font-normal text-slate-400">— {{ p.name_chinese }}</span>
                            </div>
                            <button
                              v-for="v in p.variants"
                              :key="v.id"
                              type="button"
                              @mousedown.prevent="selectProduct(idx, p, v)"
                              class="w-full text-left px-3 py-1.5 pl-6 hover:bg-indigo-50 dark:hover:bg-indigo-900/20 transition-colors border-b border-slate-100 dark:border-slate-700 last:border-0"
                            >
                              <div class="text-sm text-slate-700 dark:text-slate-300">{{ v.variant_name }}</div>
                              <div class="text-xs text-slate-400 mt-0.5 flex gap-3">
                                <span>{{ v.sku }}</span>
                                <span v-if="v.weight_kg" class="text-teal-600">{{ v.weight_kg }} kg</span>
                              </div>
                            </button>
                          </template>
                        </template>
                      </div>
                      <!-- No results hint -->
                      <div
                        v-if="item.showDropdown && !item.searching && item.searchResults.length === 0 && item.product_search.length >= 2"
                        class="absolute z-50 top-full mt-1 left-0 right-0 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg shadow-xl px-3 py-2 text-sm text-slate-500 dark:text-slate-400"
                      >
                        No products found
                      </div>
                    </div>
                    <p v-if="errors[`items.${idx}.product_id`]" class="mt-1 text-xs text-red-600">
                      {{ errors[`items.${idx}.product_id`][0] }}
                    </p>
                  </td>

                  <!-- Price -->
                  <td class="px-3 py-2">
                    <input v-model.number="item.supplier_price_cny" type="number" min="0" step="0.01"
                      class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 px-2 py-1.5 text-sm focus:outline-none focus:border-indigo-500 font-mono text-right" />
                  </td>

                  <!-- Qty -->
                  <td class="px-3 py-2">
                    <input v-model.number="item.quantity" type="number" min="1"
                      class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 px-2 py-1.5 text-sm focus:outline-none focus:border-indigo-500 font-mono text-right" />
                  </td>

                  <!-- Approx weight per piece -->
                  <td class="px-3 py-2">
                    <div class="relative">
                      <input v-model.number="item.approx_weight_kg" type="number" min="0" step="0.001"
                        placeholder="0.000"
                        class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 px-2 py-1.5 pr-7 text-sm focus:outline-none focus:border-teal-500 font-mono text-right"
                        :class="{ 'border-teal-400 bg-teal-50': item.approx_weight_kg > 0 }" />
                      <span class="absolute right-2 top-1/2 -translate-y-1/2 text-xs text-slate-400 dark:text-slate-500 pointer-events-none">kg</span>
                    </div>
                  </td>

                  <!-- Total -->
                  <td class="px-3 py-2 text-right text-sm font-mono text-slate-700 dark:text-slate-300">
                    ¥{{ ((item.supplier_price_cny || 0) * (item.quantity || 0)).toLocaleString() }}
                  </td>

                  <!-- Remove -->
                  <td class="px-3 py-2">
                    <button type="button" @click="removeItem(idx)"
                      class="text-slate-400 hover:text-red-500 transition-colors">
                      <XIcon class="w-4 h-4" />
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div v-else class="text-center py-8 text-slate-400 dark:text-slate-500 text-sm border-2 border-dashed border-slate-200 dark:border-slate-700 rounded-lg">
            Add items to this purchase order
          </div>
        </div>

        <!-- Totals -->
        <div v-if="form.items.length > 0" class="border-t border-slate-200 dark:border-slate-700 px-6 py-4">
          <div class="flex justify-end">
            <div class="w-72 space-y-2">
              <div class="flex justify-between text-sm">
                <span class="text-slate-600 dark:text-slate-400">Total (CNY)</span>
                <span class="font-mono font-semibold">¥{{ totalCny.toLocaleString() }}</span>
              </div>
              <div class="flex justify-between text-sm">
                <span class="text-slate-600 dark:text-slate-400">Total (BDT)</span>
                <span class="font-mono font-semibold">৳{{ totalBdt.toLocaleString() }}</span>
              </div>
              <div v-if="totalWeightKg > 0" class="flex justify-between text-sm pt-1 border-t border-slate-100 dark:border-slate-700">
                <span class="text-teal-700 flex items-center gap-1">
                  <WeightIcon class="w-3.5 h-3.5" />
                  Est. Total Weight
                </span>
                <span class="font-mono font-semibold text-teal-700">
                  {{ totalWeightKg >= 1000
                    ? (totalWeightKg / 1000).toFixed(3) + ' t'
                    : totalWeightKg.toFixed(3) + ' kg'
                  }}
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Sticky Submit -->
      <div class="sticky bottom-0 bg-white dark:bg-slate-800 border-t border-slate-200 dark:border-slate-700 shadow-lg -mx-6 px-6 py-4 flex items-center justify-end gap-3">
        <Link :href="route('purchase-orders.index')"
          class="px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-slate-100 border border-slate-300 dark:border-slate-600 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
          Cancel
        </Link>
        <button type="submit" :disabled="saving"
          class="px-6 py-2 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white text-sm font-medium rounded-lg transition-colors flex items-center gap-2">
          <LoaderIcon v-if="saving" class="w-4 h-4 animate-spin" />
          Save as Draft
        </button>
      </div>
    </form>

  </AppLayout>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import {
  ClipboardListIcon, PackageIcon, PlusIcon, XIcon,
  LoaderIcon, SearchIcon, CheckIcon, WeightIcon,
} from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'
import BackButton from '@/Components/UI/BackButton.vue'
import { useToast } from '@/Composables/useToast'

const props = defineProps({
  suppliers:  { type: Array, default: () => [] },
  currencies: { type: Array, default: () => [] },
})

const { success, error: showError } = useToast()
const saving = ref(false)
const errors = ref({})

const form = reactive({
  supplier_id:            '',
  currency_id:            props.currencies.find(c => c.code === 'CNY')?.id || '',
  exchange_rate:          16.50,
  order_date:             new Date().toISOString().split('T')[0],
  expected_delivery_date: '',
  notes:                  '',
  items:                  [],
})

// ─── Item helpers ────────────────────────────────────────────────────────────

function newItem() {
  return {
    // Fields sent to API
    product_id:          null,
    product_variant_id:  null,
    supplier_price_cny:  0,
    quantity:            1,
    approx_weight_kg:    0,
    // UI-only state (stripped before submit)
    product_search:   '',
    searchResults:    [],
    searching:        false,
    showDropdown:     false,
    _searchTimer:     null,
  }
}

function addItem() {
  form.items.push(newItem())
}

function removeItem(idx) {
  form.items.splice(idx, 1)
}

// ─── Product autocomplete ────────────────────────────────────────────────────

function onProductInput(idx) {
  const item = form.items[idx]
  // If user edits after selection, clear the bound product
  item.product_id         = null
  item.product_variant_id = null
  item.showDropdown       = false

  clearTimeout(item._searchTimer)

  if (item.product_search.trim().length < 2) {
    item.searchResults = []
    return
  }

  item._searchTimer = setTimeout(() => doSearch(idx), 300)
}

async function doSearch(idx) {
  const item = form.items[idx]
  item.searching = true
  try {
    const { data } = await window.axios.get('/api/v1/products/search', {
      params: { q: item.product_search },
    })
    item.searchResults = data
    item.showDropdown  = true
  } catch {
    item.searchResults = []
  } finally {
    item.searching = false
  }
}

function selectProduct(idx, product, variant = null) {
  const item = form.items[idx]
  item.product_id         = product.id
  item.product_variant_id = variant?.id ?? null
  item.product_search     = variant
    ? `${product.name} — ${variant.variant_name}`
    : product.name

  // Auto-fill weight: prefer variant weight, then product weight
  const autoWeight = parseFloat(variant?.weight_kg ?? product.weight_kg ?? 0)
  if (autoWeight > 0 && !item.approx_weight_kg) {
    item.approx_weight_kg = autoWeight
  }

  item.searchResults = []
  item.showDropdown  = false
}

function closeDropdown(idx) {
  // Slight delay so mousedown on a result fires first
  setTimeout(() => {
    if (form.items[idx]) form.items[idx].showDropdown = false
  }, 150)
}

// ─── Computed totals ─────────────────────────────────────────────────────────

const totalCny = computed(() =>
  form.items.reduce((sum, i) => sum + ((i.supplier_price_cny || 0) * (i.quantity || 0)), 0)
)

const totalBdt = computed(() => totalCny.value * (form.exchange_rate || 0))

const totalWeightKg = computed(() =>
  form.items.reduce((sum, i) => sum + ((parseFloat(i.approx_weight_kg) || 0) * (i.quantity || 0)), 0)
)

// ─── Submit ──────────────────────────────────────────────────────────────────

async function submit() {
  saving.value = true
  errors.value = {}

  // Strip UI-only state before sending
  const payload = {
    supplier_id:            form.supplier_id,
    currency_id:            form.currency_id,
    exchange_rate:          form.exchange_rate,
    order_date:             form.order_date,
    expected_delivery_date: form.expected_delivery_date,
    notes:                  form.notes,
    items: form.items.map(i => ({
      product_id:         i.product_id,
      product_variant_id: i.product_variant_id,
      supplier_price_cny: i.supplier_price_cny,
      quantity:           i.quantity,
      approx_weight_kg:   i.approx_weight_kg || null,
    })),
  }

  try {
    await window.axios.post('/api/v1/purchase-orders', payload)
    success('Purchase Order created successfully!')
    router.visit(route('purchase-orders.index'))
  } catch (err) {
    if (err.response?.status === 422) {
      errors.value = err.response.data.errors || {}
      const firstMsg = Object.values(errors.value)[0]?.[0]
      if (firstMsg) showError(firstMsg)
    } else {
      showError(err.response?.data?.message || 'Failed to create purchase order.')
    }
    saving.value = false
  }
}
</script>
