<template>
  <AppLayout>
    <Head title="International Shipments" />

    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-semibold text-slate-900 dark:text-slate-100">International Shipments</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">China → Bangladesh shipment tracking</p>
      </div>
      <Link :href="route('shipments.create')"
        class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
        <PlusIcon class="w-4 h-4" /> New Shipment
      </Link>
    </div>

    <!-- Filters -->
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-4 mb-4">
      <div class="flex items-center gap-3 flex-wrap">
        <div class="relative flex-1 min-w-40">
          <SearchIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
          <input v-model="searchQuery" @input="debouncedSearch" type="text" placeholder="Shipment no. or carrier..."
            class="w-full pl-9 pr-4 py-2 text-sm border border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:placeholder:text-slate-500 rounded-lg focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 dark:focus:ring-primary-900/30" />
        </div>
        <select v-model="selectedStatus" @change="applyFilters"
          class="text-sm border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 focus:outline-none focus:border-indigo-500 bg-white dark:bg-slate-800 dark:text-slate-100">
          <option value="">All Statuses</option>
          <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
        </select>
        <select v-model="selectedType" @change="applyFilters"
          class="text-sm border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 focus:outline-none focus:border-indigo-500 bg-white dark:bg-slate-800 dark:text-slate-100">
          <option value="">All Types</option>
          <option v-for="t in shippingTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
        </select>
        <button @click="resetFilters" class="text-sm text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300 px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
          Reset
        </button>
      </div>
    </div>

    <!-- Table -->
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
      <table class="w-full">
        <thead>
          <tr class="bg-slate-50 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700">
            <th class="text-left text-sm font-medium text-slate-700 dark:text-slate-300 px-6 py-3">Shipment No.</th>
            <th class="text-left text-sm font-medium text-slate-700 dark:text-slate-300 px-6 py-3">Type</th>
            <th class="text-left text-sm font-medium text-slate-700 dark:text-slate-300 px-6 py-3">Carrier</th>
            <th class="text-left text-sm font-medium text-slate-700 dark:text-slate-300 px-6 py-3">PO / Route</th>
            <th class="text-left text-sm font-medium text-slate-700 dark:text-slate-300 px-6 py-3">ETA</th>
            <th class="text-left text-sm font-medium text-slate-700 dark:text-slate-300 px-6 py-3">Status</th>
            <th class="text-right text-sm font-medium text-slate-700 dark:text-slate-300 px-6 py-3">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="shipments.data.length === 0">
            <td colspan="7" class="text-center py-12 text-slate-400">
              <ShipIcon class="w-10 h-10 mx-auto mb-2 text-slate-300" />
              <p class="text-sm">No shipments found</p>
              <Link :href="route('shipments.create')" class="mt-2 inline-flex items-center gap-1 text-sm text-indigo-600 dark:text-primary-400 hover:text-indigo-700 dark:hover:text-primary-300 font-medium">
                <PlusIcon class="w-3 h-3" /> Create first shipment
              </Link>
            </td>
          </tr>
          <tr v-for="s in shipments.data" :key="s.id"
            class="border-b border-slate-100 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
            <td class="px-6 py-4">
              <Link :href="route('shipments.show', s.id)"
                class="text-sm font-mono font-semibold text-cyan-600 hover:text-cyan-700">
                {{ s.shipment_no }}
              </Link>
            </td>
            <td class="px-6 py-4">
              <span class="text-xs font-medium bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300 rounded-full px-2 py-0.5">
                {{ typeLabel(s.shipping_type) }}
              </span>
            </td>
            <td class="px-6 py-4 text-sm text-slate-700 dark:text-slate-300">{{ s.carrier || '—' }}</td>
            <td class="px-6 py-4">
              <p v-if="s.purchase_order" class="text-xs font-mono text-indigo-600 dark:text-primary-400">{{ s.purchase_order.po_number }}</p>
              <p class="text-xs text-slate-500 dark:text-slate-400">{{ s.port_loading || '?' }} → {{ s.port_discharge || '?' }}</p>
            </td>
            <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-400">{{ formatDate(s.eta) }}</td>
            <td class="px-6 py-4">
              <span :class="statusBadge(s.status)" class="rounded-full px-2.5 py-0.5 text-xs font-medium">
                {{ statusLabel(s.status) }}
              </span>
            </td>
            <td class="px-6 py-4 text-right">
              <div class="flex items-center justify-end gap-2">
                <Link :href="route('shipments.show', s.id)"
                  class="text-sm text-indigo-600 dark:text-primary-400 hover:text-indigo-700 dark:hover:text-primary-300 font-medium">View</Link>
                <Link :href="route('shipments.landing-cost', s.id)"
                  class="text-sm text-emerald-600 hover:text-emerald-700 font-medium">Cost</Link>
              </div>
            </td>
          </tr>
        </tbody>
      </table>

      <div v-if="shipments.last_page > 1" class="flex items-center justify-between px-6 py-3 border-t border-slate-200 dark:border-slate-700">
        <p class="text-sm text-slate-500 dark:text-slate-400">{{ shipments.from }}–{{ shipments.to }} of {{ shipments.total }} shipments</p>
        <div class="flex gap-1">
          <Link v-for="link in shipments.links" :key="link.label" :href="link.url || '#'"
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
import { PlusIcon, SearchIcon, ShipIcon } from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
  shipments:     { type: Object, required: true },
  statuses:      { type: Array, default: () => [] },
  shippingTypes: { type: Array, default: () => [] },
  filters:       { type: Object, default: () => ({}) },
})

const searchQuery   = ref(props.filters.search || '')
const selectedStatus = ref(props.filters.status || '')
const selectedType  = ref(props.filters.shipping_type || '')

let searchTimer = null
function debouncedSearch() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(applyFilters, 400)
}

function applyFilters() {
  router.get(route('shipments.index'), {
    search: searchQuery.value,
    status: selectedStatus.value,
    shipping_type: selectedType.value,
  }, { preserveState: true, replace: true })
}

function resetFilters() {
  searchQuery.value = ''
  selectedStatus.value = ''
  selectedType.value = ''
  router.get(route('shipments.index'), {}, { preserveState: false })
}

const statusBadges = {
  booked:                 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300',
  loaded:                 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
  departed:               'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300',
  in_transit:             'bg-cyan-100 text-cyan-700 dark:bg-cyan-900/30 dark:text-cyan-300',
  arrived:                'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
  clearing:               'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300',
  cleared:                'bg-teal-100 text-teal-700',
  delivered_to_warehouse: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
}

const statusLabels = {
  booked: 'Booked', loaded: 'Loaded', departed: 'Departed', in_transit: 'In Transit',
  arrived: 'Arrived', clearing: 'Clearing', cleared: 'Cleared', delivered_to_warehouse: 'Delivered',
}

const typeLabels = {
  sea: 'Sea', air: 'Air', rail: 'Rail', courier: 'Courier',
}

function statusBadge(s) { return statusBadges[s] || 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300' }
function statusLabel(s) { return statusLabels[s] || s }
function typeLabel(t)   { return typeLabels[t] || t }

function formatDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
}
</script>
