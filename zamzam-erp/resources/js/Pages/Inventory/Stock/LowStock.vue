<template>
  <AppLayout>
    <Head title="Low Stock Alerts" />

    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-semibold text-slate-900 dark:text-slate-100">Low Stock Alerts</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">{{ items.length }} products below minimum threshold</p>
      </div>
      <Link :href="route('stock.index')"
        class="inline-flex items-center gap-2 border border-slate-300 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700/50 text-slate-700 dark:text-slate-300 text-sm font-medium px-4 py-2 rounded-lg transition-colors">
        <ArrowLeftIcon class="w-4 h-4" /> All Stock
      </Link>
    </div>

    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
      <table class="w-full">
        <thead>
          <tr class="bg-amber-50 border-b border-amber-200">
            <th class="text-left text-sm font-medium text-amber-800 px-6 py-3">Product</th>
            <th class="text-left text-sm font-medium text-amber-800 px-6 py-3">Warehouse</th>
            <th class="text-right text-sm font-medium text-amber-800 px-6 py-3">Current Stock</th>
            <th class="text-right text-sm font-medium text-amber-800 px-6 py-3">Min Threshold</th>
            <th class="text-right text-sm font-medium text-amber-800 px-6 py-3">Shortage</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="items.length === 0">
            <td colspan="5" class="text-center py-12 text-slate-400">
              <CheckCircleIcon class="w-10 h-10 mx-auto mb-2 text-emerald-300" />
              <p class="text-sm text-emerald-600">All products have sufficient stock!</p>
            </td>
          </tr>
          <tr v-for="item in items" :key="item.id"
            class="border-b border-slate-100 dark:border-slate-700 hover:bg-amber-50/50">
            <td class="px-6 py-4">
              <p class="text-sm font-medium text-slate-900 dark:text-slate-100">{{ item.product?.name }}</p>
              <p class="text-xs text-slate-400 dark:text-slate-500 font-mono">{{ item.product?.sku }}</p>
            </td>
            <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-400">{{ item.warehouse?.name }}</td>
            <td class="px-6 py-4 text-right">
              <span class="text-sm font-mono font-bold text-red-600">{{ item.quantity }}</span>
            </td>
            <td class="px-6 py-4 text-right text-sm font-mono text-slate-600 dark:text-slate-400">
              {{ item.product?.min_stock_alert || 0 }}
            </td>
            <td class="px-6 py-4 text-right">
              <span class="text-sm font-mono font-semibold text-amber-700">
                {{ (item.product?.min_stock_alert || 0) - item.quantity }}
              </span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

  </AppLayout>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3'
import { ArrowLeftIcon, CheckCircleIcon } from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'

defineProps({
  items: { type: Array, default: () => [] },
})
</script>
