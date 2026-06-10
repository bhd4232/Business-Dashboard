<template>
  <AppLayout>
    <Head title="Invoices" />

    <!-- Page Header -->
    <div class="flex items-center justify-between mb-4">
      <div class="flex items-center gap-3">
        <ThreeDIcon name="sales" size="lg" />
        <div>
          <h1 class="text-2xl font-semibold text-slate-900 dark:text-slate-100">Invoices</h1>
          <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Manage and track customer invoices</p>
        </div>
      </div>
      <div class="flex items-center gap-2">
        <Link :href="route('settings.invoice')"
          class="inline-flex items-center gap-2 border border-slate-300 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700/50 text-slate-600 dark:text-slate-300 text-sm font-medium px-3 py-2 rounded-lg transition-colors"
          title="Invoice Settings">
          <Settings2Icon class="w-4 h-4" />
          <span class="hidden sm:inline">Settings</span>
        </Link>
        <Link :href="route('invoices.create')"
          class="inline-flex items-center gap-2 bg-primary-600 hover:bg-primary-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
          <PlusIcon class="w-4 h-4" /> New Invoice
        </Link>
      </div>
    </div>

    <!-- Status Tabs -->
    <div class="flex gap-1 flex-wrap mb-4 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-1.5 shadow-sm">
      <button
        v-for="tab in statusTabs"
        :key="tab.value"
        @click="setStatus(tab.value)"
        :class="[
          'flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium transition-colors',
          activeStatus === tab.value
            ? 'bg-primary-600 text-white shadow-sm'
            : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700/50',
        ]"
      >
        {{ tab.label }}
        <span
          v-if="tab.count > 0"
          :class="activeStatus === tab.value
            ? 'bg-white/25 text-white'
            : 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-400'"
          class="text-xs font-semibold rounded-full px-1.5 py-0.5 min-w-[1.25rem] text-center"
        >{{ tab.count }}</span>
      </button>
    </div>

    <!-- Filters Bar -->
    <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl shadow-sm p-3 mb-4 flex flex-wrap gap-2 items-center">
      <input
        v-model="filters.search"
        @input="debouncedSearch"
        type="text"
        placeholder="Search invoice no, customer..."
        class="border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 rounded-lg px-3 py-1.5 text-sm w-56 focus:outline-none focus:ring-2 focus:ring-primary-500/40"
      />
      <select
        v-model="filters.customer_id"
        @change="applyFilters"
        class="border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 rounded-lg px-3 py-1.5 text-sm focus:outline-none"
      >
        <option value="">All Customers</option>
        <option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }}{{ c.business_name ? ` (${c.business_name})` : '' }}</option>
      </select>
      <input
        v-model="filters.date_from"
        @change="applyFilters"
        type="date"
        class="border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 rounded-lg px-3 py-1.5 text-sm focus:outline-none"
      />
      <span class="text-slate-400 text-xs">to</span>
      <input
        v-model="filters.date_to"
        @change="applyFilters"
        type="date"
        class="border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 rounded-lg px-3 py-1.5 text-sm focus:outline-none"
      />
      <button
        v-if="hasActiveFilters"
        @click="clearFilters"
        class="inline-flex items-center gap-1.5 border border-slate-300 dark:border-slate-600 text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700/50 text-sm px-3 py-1.5 rounded-lg transition-colors"
      >
        <XIcon class="w-3.5 h-3.5" /> Clear
      </button>
    </div>

    <!-- Table -->
    <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl shadow-sm overflow-hidden">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-slate-100 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/50">
            <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide">Invoice No</th>
            <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide">Customer</th>
            <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide">Issue Date</th>
            <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide">Due Date</th>
            <th class="text-right px-4 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide">Total</th>
            <th class="text-right px-4 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide">Paid</th>
            <th class="text-right px-4 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide">Due</th>
            <th class="text-left px-4 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide">Status</th>
            <th class="text-right px-4 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
          <tr v-if="invoices.data.length === 0">
            <td colspan="9" class="text-center py-12 text-slate-400 dark:text-slate-500">
              No invoices found.
            </td>
          </tr>
          <tr
            v-for="inv in invoices.data"
            :key="inv.id"
            class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors"
          >
            <td class="px-4 py-3">
              <Link :href="route('invoices.show', inv.id)" class="font-mono font-semibold text-primary-600 dark:text-primary-400 hover:underline">
                {{ inv.invoice_no }}
              </Link>
            </td>
            <td class="px-4 py-3">
              <div class="text-slate-800 dark:text-slate-200 font-medium">{{ inv.customer?.name }}</div>
              <div v-if="inv.customer?.business_name" class="text-xs text-slate-400 dark:text-slate-500">{{ inv.customer.business_name }}</div>
            </td>
            <td class="px-4 py-3 text-slate-600 dark:text-slate-400">{{ formatDate(inv.issue_date) }}</td>
            <td class="px-4 py-3 text-slate-600 dark:text-slate-400">{{ inv.due_date ? formatDate(inv.due_date) : '—' }}</td>
            <td class="px-4 py-3 text-right font-mono text-slate-800 dark:text-slate-200">৳{{ formatNumber(inv.total_bdt) }}</td>
            <td class="px-4 py-3 text-right font-mono text-emerald-600 dark:text-emerald-400">৳{{ formatNumber(inv.paid_bdt) }}</td>
            <td class="px-4 py-3 text-right font-mono" :class="Number(inv.due_bdt) > 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-400'">
              ৳{{ formatNumber(inv.due_bdt) }}
            </td>
            <td class="px-4 py-3">
              <span :class="statusColors[inv.status]" class="rounded-full px-2.5 py-0.5 text-xs font-medium capitalize">
                {{ inv.status }}
              </span>
            </td>
            <td class="px-4 py-3">
              <div class="flex items-center gap-1.5 justify-end">
                <Link :href="route('invoices.show', inv.id)"
                  class="p-1.5 text-slate-500 dark:text-slate-400 hover:text-primary-600 dark:hover:text-primary-400 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700/50 transition-colors"
                  title="View">
                  <EyeIcon class="w-4 h-4" />
                </Link>
                <Link
                  v-if="['draft','issued'].includes(inv.status)"
                  :href="route('invoices.edit', inv.id)"
                  class="p-1.5 text-slate-500 dark:text-slate-400 hover:text-primary-600 dark:hover:text-primary-400 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700/50 transition-colors"
                  title="Edit">
                  <EditIcon class="w-4 h-4" />
                </Link>
                <Link :href="route('invoices.show', inv.id) + '?print=1'"
                  class="p-1.5 text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700/50 transition-colors"
                  title="Print">
                  <PrinterIcon class="w-4 h-4" />
                </Link>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="invoices.last_page > 1" class="flex items-center justify-between mt-4 text-sm text-slate-600 dark:text-slate-400">
      <span>Showing {{ invoices.from }}–{{ invoices.to }} of {{ invoices.total }}</span>
      <div class="flex gap-1">
        <Link
          v-for="page in invoices.links"
          :key="page.label"
          :href="page.url || ''"
          :class="[
            'px-3 py-1.5 rounded-lg border transition-colors',
            page.active
              ? 'bg-primary-600 text-white border-primary-600'
              : page.url
                ? 'border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700/50'
                : 'border-slate-100 dark:border-slate-700 text-slate-300 dark:text-slate-600 cursor-default',
          ]"
          v-html="page.label"
          preserve-scroll
        />
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, reactive } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { PlusIcon, EyeIcon, EditIcon, PrinterIcon, XIcon, Settings2Icon } from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'
import ThreeDIcon from '@/Components/UI/ThreeDIcon.vue'

const props = defineProps({
  invoices:   { type: Object, required: true },
  customers:  { type: Array,  default: () => [] },
  filters:    { type: Object, default: () => ({}) },
  statusTabs: { type: Array,  default: () => [] },
})

const filters = reactive({
  search:      props.filters.search      ?? '',
  customer_id: props.filters.customer_id ?? '',
  date_from:   props.filters.date_from   ?? '',
  date_to:     props.filters.date_to     ?? '',
  status:      props.filters.status      ?? '',
})

const activeStatus = computed(() => filters.status)

const hasActiveFilters = computed(() =>
  filters.search || filters.customer_id || filters.date_from || filters.date_to
)

const statusColors = {
  draft:     'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300',
  issued:    'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
  partial:   'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
  paid:      'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
  overdue:   'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300',
  cancelled: 'bg-slate-100 text-slate-500 dark:bg-slate-700/50 dark:text-slate-400',
}

let searchTimeout = null
function debouncedSearch() {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(applyFilters, 350)
}

function setStatus(value) {
  filters.status = value
  applyFilters()
}

function applyFilters() {
  const query = {}
  if (filters.status)      query.status      = filters.status
  if (filters.search)      query.search      = filters.search
  if (filters.customer_id) query.customer_id = filters.customer_id
  if (filters.date_from)   query.date_from   = filters.date_from
  if (filters.date_to)     query.date_to     = filters.date_to
  router.get(route('invoices.index'), query, { preserveState: true, replace: true })
}

function clearFilters() {
  filters.search      = ''
  filters.customer_id = ''
  filters.date_from   = ''
  filters.date_to     = ''
  applyFilters()
}

function formatDate(d) {
  if (!d) return '—'
  const date = typeof d === 'string' ? d.split('T')[0] : d
  return new Date(date).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
}

function formatNumber(v) {
  if (v === null || v === undefined) return '0'
  return Number(v).toLocaleString('en-BD')
}
</script>
