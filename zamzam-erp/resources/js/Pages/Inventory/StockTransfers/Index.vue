<template>
  <AppLayout>
    <Head title="Stock Transfers" />

    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-semibold text-slate-900 dark:text-slate-100">Stock Transfers</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Move stock between warehouses</p>
      </div>
      <Link :href="route('stock-transfers.create')"
        class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
        <PlusIcon class="w-4 h-4" /> New Transfer
      </Link>
    </div>

    <!-- Filters -->
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-4 mb-4">
      <div class="flex items-center gap-3">
        <select v-model="selectedStatus" @change="applyFilters"
          class="text-sm border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 focus:outline-none focus:border-indigo-500 bg-white dark:bg-slate-800 dark:text-slate-100">
          <option value="">All Statuses</option>
          <option value="pending">Pending</option>
          <option value="in_transit">In Transit</option>
          <option value="completed">Completed</option>
          <option value="cancelled">Cancelled</option>
        </select>
        <button @click="resetFilters" class="text-sm text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300 px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700/50">Reset</button>
      </div>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
      <table class="w-full">
        <thead>
          <tr class="bg-slate-50 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700">
            <th class="text-left text-sm font-medium text-slate-700 dark:text-slate-300 px-6 py-3">Transfer No.</th>
            <th class="text-left text-sm font-medium text-slate-700 dark:text-slate-300 px-6 py-3">From</th>
            <th class="text-left text-sm font-medium text-slate-700 dark:text-slate-300 px-6 py-3">To</th>
            <th class="text-left text-sm font-medium text-slate-700 dark:text-slate-300 px-6 py-3">Date</th>
            <th class="text-left text-sm font-medium text-slate-700 dark:text-slate-300 px-6 py-3">Status</th>
            <th class="text-right text-sm font-medium text-slate-700 dark:text-slate-300 px-6 py-3">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="transfers.data.length === 0">
            <td colspan="6" class="text-center py-12 text-slate-400">
              <ArrowRightLeftIcon class="w-10 h-10 mx-auto mb-2 text-slate-300" />
              <p class="text-sm">No transfers found</p>
            </td>
          </tr>
          <tr v-for="t in transfers.data" :key="t.id"
            class="border-b border-slate-100 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700/50">
            <td class="px-6 py-4 text-sm font-mono font-semibold text-indigo-600 dark:text-primary-400">
              <Link :href="route('stock-transfers.show', t.id)">{{ t.transfer_no }}</Link>
            </td>
            <td class="px-6 py-4 text-sm text-slate-700 dark:text-slate-300">{{ t.from_warehouse?.name }}</td>
            <td class="px-6 py-4 text-sm text-slate-700 dark:text-slate-300">{{ t.to_warehouse?.name }}</td>
            <td class="px-6 py-4 text-sm text-slate-500 dark:text-slate-400">{{ t.created_at?.split('T')[0] }}</td>
            <td class="px-6 py-4">
              <span :class="statusColor(t.status)" class="rounded-full px-2.5 py-0.5 text-xs font-medium">
                {{ statusLabel(t.status) }}
              </span>
            </td>
            <td class="px-6 py-4 text-right">
              <Link :href="route('stock-transfers.show', t.id)"
                class="text-sm text-indigo-600 dark:text-primary-400 hover:text-indigo-700 dark:hover:text-primary-300 font-medium">View</Link>
            </td>
          </tr>
        </tbody>
      </table>

      <div v-if="transfers.last_page > 1" class="flex items-center justify-between px-6 py-3 border-t border-slate-200 dark:border-slate-700">
        <p class="text-sm text-slate-500 dark:text-slate-400">{{ transfers.from }}–{{ transfers.to }} of {{ transfers.total }} transfers</p>
        <div class="flex gap-1">
          <Link v-for="link in transfers.links" :key="link.label" :href="link.url || '#'"
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
import { PlusIcon, ArrowRightLeftIcon } from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
  transfers:  { type: Object, required: true },
  warehouses: { type: Array, default: () => [] },
  filters:    { type: Object, default: () => ({}) },
})

const selectedStatus = ref(props.filters.status || '')

function applyFilters() {
  router.get(route('stock-transfers.index'), { status: selectedStatus.value }, { preserveState: true, replace: true })
}

function resetFilters() {
  selectedStatus.value = ''
  router.get(route('stock-transfers.index'), {}, { preserveState: false })
}

const statusColors = {
  pending: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300', in_transit: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
  completed: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300', cancelled: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300',
}
const statusLabels = {
  pending: 'Pending', in_transit: 'In Transit', completed: 'Completed', cancelled: 'Cancelled',
}
function statusColor(s) { return statusColors[s] || 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300' }
function statusLabel(s) { return statusLabels[s] || s }
</script>
