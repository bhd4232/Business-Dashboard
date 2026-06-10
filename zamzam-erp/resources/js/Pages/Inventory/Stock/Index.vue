<template>
  <AppLayout>
    <Head title="Stock Overview" />

    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-semibold text-slate-900 dark:text-slate-100">Stock Overview</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">
          Total Value:
          <span class="font-mono font-semibold text-slate-800 dark:text-slate-200">৳{{ Number(totalValue).toLocaleString() }}</span>
        </p>
      </div>
      <div class="flex gap-2">
        <Link :href="route('stock.low-stock')"
          class="inline-flex items-center gap-2 border border-amber-300 bg-amber-50 hover:bg-amber-100 text-amber-700 text-sm font-medium px-4 py-2 rounded-lg transition-colors">
          <AlertTriangleIcon class="w-4 h-4" /> Low Stock
        </Link>
        <Link :href="route('stock-transfers.create')"
          class="inline-flex items-center gap-2 border border-slate-300 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700/50 text-slate-700 dark:text-slate-300 text-sm font-medium px-4 py-2 rounded-lg transition-colors">
          <ArrowRightLeftIcon class="w-4 h-4" /> Transfer
        </Link>
      </div>
    </div>

    <!-- Filters -->
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-4 mb-4">
      <div class="flex items-center gap-3 flex-wrap">
        <div class="relative flex-1 min-w-48">
          <SearchIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
          <input v-model="searchQuery" @input="debouncedSearch" type="text" placeholder="Product name or SKU..."
            class="w-full pl-9 pr-4 py-2 text-sm border border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:placeholder:text-slate-500 rounded-lg focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 dark:focus:ring-primary-900/30" />
        </div>
        <select v-model="selectedWarehouse" @change="applyFilters"
          class="text-sm border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 focus:outline-none focus:border-indigo-500 bg-white dark:bg-slate-800 dark:text-slate-100">
          <option value="">All Warehouses</option>
          <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
        </select>
        <button @click="resetFilters" class="text-sm text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300 px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
          Reset
        </button>
      </div>
    </div>

    <!-- Stock Table -->
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
      <table class="w-full">
        <thead>
          <tr class="bg-slate-50 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700">
            <th class="text-left text-sm font-medium text-slate-700 dark:text-slate-300 px-6 py-3">Product</th>
            <th class="text-left text-sm font-medium text-slate-700 dark:text-slate-300 px-6 py-3">Warehouse</th>
            <th class="text-right text-sm font-medium text-slate-700 dark:text-slate-300 px-6 py-3">Qty</th>
            <th class="text-right text-sm font-medium text-slate-700 dark:text-slate-300 px-6 py-3">Reserved</th>
            <th class="text-right text-sm font-medium text-slate-700 dark:text-slate-300 px-6 py-3">Avg Landing Cost</th>
            <th class="text-right text-sm font-medium text-slate-700 dark:text-slate-300 px-6 py-3">Total Value</th>
            <th class="text-right text-sm font-medium text-slate-700 dark:text-slate-300 px-6 py-3">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="stocks.data.length === 0">
            <td colspan="7" class="text-center py-12 text-slate-400">
              <PackageIcon class="w-10 h-10 mx-auto mb-2 text-slate-300" />
              <p class="text-sm">No stock records found</p>
            </td>
          </tr>
          <tr v-for="item in stocks.data" :key="item.id"
            class="border-b border-slate-100 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors"
            :class="isLowStock(item) ? 'bg-amber-50/30' : ''">
            <td class="px-6 py-4">
              <div>
                <p class="text-sm font-medium text-slate-900 dark:text-slate-100">{{ item.product?.name }}</p>
                <p v-if="item.variant" class="text-xs text-slate-500 dark:text-slate-400">{{ item.variant.variant_name }}</p>
                <p class="text-xs text-slate-400 dark:text-slate-500 font-mono">{{ item.product?.sku }}</p>
              </div>
            </td>
            <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-400">{{ item.warehouse?.name }}</td>
            <td class="px-6 py-4 text-right">
              <span :class="isLowStock(item) ? 'text-amber-600 font-semibold' : 'text-slate-800 dark:text-slate-200'"
                class="text-sm font-mono">{{ item.quantity }}</span>
              <AlertTriangleIcon v-if="isLowStock(item)" class="w-3 h-3 text-amber-500 inline ml-1" />
            </td>
            <td class="px-6 py-4 text-right text-sm font-mono text-slate-600 dark:text-slate-400">{{ item.reserved_qty }}</td>
            <td class="px-6 py-4 text-right text-sm font-mono text-slate-700 dark:text-slate-300">
              ৳{{ Number(item.avg_landing_cost_bdt).toLocaleString() }}
            </td>
            <td class="px-6 py-4 text-right text-sm font-mono font-semibold text-slate-800 dark:text-slate-200">
              ৳{{ Number((item.quantity || 0) * (item.avg_landing_cost_bdt || 0)).toLocaleString() }}
            </td>
            <td class="px-6 py-4 text-right">
              <Link :href="route('stock.show', item.product_id)"
                class="text-xs text-indigo-600 dark:text-primary-400 hover:text-indigo-700 dark:hover:text-primary-300 font-medium">Detail</Link>
            </td>
          </tr>
        </tbody>
      </table>

      <div v-if="stocks.last_page > 1" class="flex items-center justify-between px-6 py-3 border-t border-slate-200 dark:border-slate-700">
        <p class="text-sm text-slate-500 dark:text-slate-400">{{ stocks.from }}–{{ stocks.to }} of {{ stocks.total }} items</p>
        <div class="flex gap-1">
          <Link v-for="link in stocks.links" :key="link.label" :href="link.url || '#'"
            :class="['px-3 py-1 text-sm rounded-lg transition-colors',
              link.active ? 'bg-indigo-600 text-white' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700/50',
              !link.url ? 'opacity-40 pointer-events-none' : '']"
            v-html="link.label" />
        </div>
      </div>
    </div>

  </AppLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { SearchIcon, PackageIcon, AlertTriangleIcon, ArrowRightLeftIcon } from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
  stocks:      { type: Object, required: true },
  warehouses:  { type: Array, default: () => [] },
  filters:     { type: Object, default: () => ({}) },
  total_value: { type: Number, default: 0 },
})

const searchQuery      = ref(props.filters.search || '')
const selectedWarehouse = ref(props.filters.warehouse_id || '')
const totalValue = ref(props.total_value)

let searchTimer = null
function debouncedSearch() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(applyFilters, 400)
}

function applyFilters() {
  router.get(route('stock.index'), {
    search:       searchQuery.value,
    warehouse_id: selectedWarehouse.value,
  }, { preserveState: true, replace: true })
}

function resetFilters() {
  searchQuery.value = ''
  selectedWarehouse.value = ''
  router.get(route('stock.index'), {}, { preserveState: false })
}

function isLowStock(item) {
  const min = item.product?.min_stock_alert || 0
  return min > 0 && item.quantity <= min
}
</script>
