<template>
  <AppLayout>
    <Head title="Stock Adjustments" />

    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-semibold text-slate-900 dark:text-slate-100">Stock Adjustments</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Damage, theft, or count corrections</p>
      </div>
      <Link :href="route('stock-adjustments.create')"
        class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
        <PlusIcon class="w-4 h-4" /> New Adjustment
      </Link>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
      <table class="w-full">
        <thead>
          <tr class="bg-slate-50 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700">
            <th class="text-left text-sm font-medium text-slate-700 dark:text-slate-300 px-6 py-3">Adjustment No.</th>
            <th class="text-left text-sm font-medium text-slate-700 dark:text-slate-300 px-6 py-3">Warehouse</th>
            <th class="text-left text-sm font-medium text-slate-700 dark:text-slate-300 px-6 py-3">Type</th>
            <th class="text-left text-sm font-medium text-slate-700 dark:text-slate-300 px-6 py-3">Reason</th>
            <th class="text-left text-sm font-medium text-slate-700 dark:text-slate-300 px-6 py-3">Date</th>
            <th class="text-right text-sm font-medium text-slate-700 dark:text-slate-300 px-6 py-3">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="adjustments.data.length === 0">
            <td colspan="6" class="text-center py-12 text-slate-400 text-sm">No adjustments found</td>
          </tr>
          <tr v-for="adj in adjustments.data" :key="adj.id"
            class="border-b border-slate-100 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700/50">
            <td class="px-6 py-4 text-sm font-mono font-semibold text-indigo-600 dark:text-primary-400">
              <Link :href="route('stock-adjustments.show', adj.id)">{{ adj.adjustment_no }}</Link>
            </td>
            <td class="px-6 py-4 text-sm text-slate-700 dark:text-slate-300">{{ adj.warehouse?.name }}</td>
            <td class="px-6 py-4">
              <span :class="typeColor(adj.type)" class="rounded-full px-2.5 py-0.5 text-xs font-medium">
                {{ typeLabel(adj.type) }}
              </span>
            </td>
            <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-400">{{ adj.reason }}</td>
            <td class="px-6 py-4 text-sm text-slate-500 dark:text-slate-400">{{ adj.created_at?.split('T')[0] }}</td>
            <td class="px-6 py-4 text-right">
              <Link :href="route('stock-adjustments.show', adj.id)"
                class="text-sm text-indigo-600 dark:text-primary-400 hover:text-indigo-700 dark:hover:text-primary-300 font-medium">View</Link>
            </td>
          </tr>
        </tbody>
      </table>

      <div v-if="adjustments.last_page > 1" class="flex items-center justify-between px-6 py-3 border-t border-slate-200 dark:border-slate-700">
        <p class="text-sm text-slate-500 dark:text-slate-400">{{ adjustments.from }}–{{ adjustments.to }} of {{ adjustments.total }}</p>
        <div class="flex gap-1">
          <Link v-for="link in adjustments.links" :key="link.label" :href="link.url || '#'"
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
import { Head, Link } from '@inertiajs/vue3'
import { PlusIcon } from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'

defineProps({
  adjustments: { type: Object, required: true },
  warehouses:  { type: Array, default: () => [] },
})

const typeColors = { add: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300', remove: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300', correction: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300' }
const typeLabels = { add: 'Increase', remove: 'Decrease', correction: 'Correction' }
function typeColor(t) { return typeColors[t] || 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300' }
function typeLabel(t) { return typeLabels[t] || t }
</script>
