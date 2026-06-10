<template>
  <AppLayout>
    <Head title="Dashboard" />

    <!-- ── Page Header ── -->
    <div class="flex items-center justify-between mb-6">
      <div class="flex items-center gap-4">
        <ThreeDIcon name="dashboard" size="xl" />
        <div>
          <h1 class="text-2xl font-semibold text-slate-900 dark:text-slate-100">Dashboard</h1>
          <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">
            {{ greeting }}, {{ user?.name }} — {{ today }}
          </p>
        </div>
      </div>
      <!-- Period Selector -->
      <div class="flex bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg p-0.5 shadow-sm">
        <button
          v-for="p in periods"
          :key="p.value"
          @click="activePeriod = p.value"
          :class="[
            'px-3 py-1.5 text-xs font-medium rounded-md transition-all',
            activePeriod === p.value
              ? 'bg-indigo-600 text-white shadow-sm'
              : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100',
          ]"
        >
          {{ p.label }}
        </button>
      </div>
    </div>

    <!-- ── Stats Grid (6 cols) ── -->
    <div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4 mb-6">
      <StatCard
        label="Total Revenue"
        :value="'৳' + fmtNum(stats.revenue_month_bdt)"
        icon="TrendingUp"
        icon3d="trending_up"
        color="emerald"
        :trend="12"
        :progress="78"
        sub-label="vs last month"
      />
      <StatCard
        label="Purchase Orders"
        :value="stats.total_orders_month"
        icon="ShoppingCart"
        icon3d="purchase_orders"
        color="orange"
        :trend="8"
        :progress="65"
        sub-label="this month"
      />
      <StatCard
        label="Total Products"
        :value="stats.total_products"
        icon="Package"
        icon3d="products"
        color="indigo"
        :trend="null"
        :progress="null"
        sub-label="in catalog"
      />
      <StatCard
        label="Low Stock Items"
        :value="stats.low_stock_count"
        icon="AlertTriangle"
        icon3d="low_stock"
        color="amber"
        :trend="-5"
        :progress="stats.low_stock_count > 0 ? Math.min(100, stats.low_stock_count * 4) : 0"
        sub-label="need reorder"
      />
      <StatCard
        label="Active Suppliers"
        :value="stats.total_suppliers"
        icon="Building2"
        icon3d="suppliers"
        color="blue"
        :trend="null"
        :progress="null"
        sub-label="registered"
      />
      <StatCard
        label="Warehouses"
        :value="stats.total_warehouses"
        icon="Warehouse"
        icon3d="warehouses"
        color="cyan"
        :trend="null"
        :progress="null"
        sub-label="active locations"
      />
    </div>

    <!-- ── Charts Row ── -->
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-6">
      <!-- Revenue Chart -->
      <div class="xl:col-span-2 bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-100 dark:border-slate-700 p-6">
        <div class="flex items-center justify-between mb-5">
          <div class="flex items-center gap-3">
            <ThreeDIcon name="trending_up" size="md" />
            <div>
              <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100">Revenue Trend</h2>
              <p class="text-sm text-slate-500 dark:text-slate-400">Last 30 days performance</p>
            </div>
          </div>
          <div class="flex items-center gap-4 text-xs text-slate-500 dark:text-slate-400">
            <span class="flex items-center gap-1.5">
              <span class="w-3 h-3 rounded-sm bg-indigo-500 inline-block"></span>Revenue
            </span>
            <span class="flex items-center gap-1.5">
              <span class="w-3 h-3 rounded-sm bg-emerald-400 inline-block"></span>Profit
            </span>
          </div>
        </div>
        <!-- Chart placeholder -->
        <div class="relative h-[220px] flex flex-col items-center justify-center gap-3 bg-gradient-to-br from-slate-50 to-indigo-50 dark:from-slate-800 dark:to-slate-700 rounded-xl border-2 border-dashed border-indigo-100">
          <div class="flex items-end gap-1.5 h-20">
            <div v-for="h in chartBars" :key="h"
              :style="{ height: h + '%' }"
              class="w-4 bg-indigo-200 dark:bg-indigo-600 rounded-t hover:bg-indigo-400 transition-colors cursor-pointer"
            ></div>
          </div>
          <span class="text-xs text-slate-400 dark:text-slate-500 font-medium">Chart.js integration — Phase 4</span>
        </div>
      </div>

      <!-- Quick Access -->
      <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-100 dark:border-slate-700 p-6">
        <div class="flex items-center gap-2 mb-4">
          <ThreeDIcon name="zap" size="md" />
          <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100">Quick Access</h2>
        </div>
        <div class="space-y-2.5">
          <a
            v-for="link in quickLinks"
            :key="link.label"
            :href="link.href"
            class="flex items-center gap-3 p-3 rounded-xl border border-slate-100 dark:border-slate-700 hover:border-indigo-200 hover:bg-indigo-50 dark:hover:bg-indigo-900/20 transition-all group cursor-pointer"
          >
            <div class="shrink-0 transition-transform duration-200 group-hover:scale-110">
              <ThreeDIcon :name="link.icon3d" size="md" :animate="false" />
            </div>
            <span class="text-sm font-medium text-slate-700 dark:text-slate-300 group-hover:text-indigo-700 transition-colors">
              {{ link.label }}
            </span>
            <svg class="w-4 h-4 text-slate-300 dark:text-slate-600 ml-auto group-hover:text-indigo-400 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
            </svg>
          </a>
        </div>
      </div>
    </div>

    <!-- ── Bottom Row ── -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">

      <!-- Recent Purchase Orders -->
      <div class="lg:col-span-2 bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-100 dark:border-slate-700 overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 dark:border-slate-700">
          <div class="flex items-center gap-2">
            <ThreeDIcon name="purchase_orders" size="sm" />
            <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100">Recent Purchase Orders</h2>
          </div>
          <a :href="route('purchase-orders.index')" class="text-xs text-indigo-600 hover:text-indigo-700 dark:text-primary-400 dark:hover:text-primary-300 font-medium">
            View all →
          </a>
        </div>

        <div v-if="stats.recent_orders?.length" class="overflow-x-auto">
          <table class="w-full">
            <thead>
              <tr class="bg-slate-50 dark:bg-slate-700/50 border-b border-slate-100 dark:border-slate-700 text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wide">
                <th class="text-left px-6 py-3">PO #</th>
                <th class="text-left px-6 py-3">Supplier</th>
                <th class="text-left px-6 py-3">Amount</th>
                <th class="text-left px-6 py-3">Status</th>
                <th class="text-left px-6 py-3">Date</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
              <tr
                v-for="order in stats.recent_orders"
                :key="order.id"
                class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors"
              >
                <td class="px-6 py-3.5 font-mono text-sm text-indigo-600 font-medium">
                  {{ order.po_number }}
                </td>
                <td class="px-6 py-3.5 text-sm text-slate-800 dark:text-slate-200">{{ order.supplier }}</td>
                <td class="px-6 py-3.5 text-sm font-mono font-medium text-slate-900 dark:text-slate-100">
                  ৳{{ order.total_bdt }}
                </td>
                <td class="px-6 py-3.5">
                  <span :class="statusBadge(order.status)">
                    {{ fmtStatus(order.status) }}
                  </span>
                </td>
                <td class="px-6 py-3.5 text-xs text-slate-500 dark:text-slate-400">{{ order.date }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Empty state with 3D icon -->
        <div v-else class="flex flex-col items-center justify-center py-12 gap-3 text-slate-400">
          <ThreeDIcon name="purchase_orders" size="xl" />
          <p class="text-sm font-medium">No purchase orders yet</p>
          <a :href="route('purchase-orders.index')" class="text-xs text-indigo-500 hover:underline">
            Create your first PO →
          </a>
        </div>
      </div>

      <!-- Right Panel -->
      <div class="space-y-4">

        <!-- Stock Alerts -->
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-100 dark:border-slate-700 p-5">
          <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-2">
              <ThreeDIcon name="alert" size="sm" />
              <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Stock Alerts</h2>
            </div>
            <span class="w-6 h-6 rounded-full bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300 text-xs font-bold flex items-center justify-center">
              {{ stats.low_stock_count }}
            </span>
          </div>

          <div v-if="stats.low_stock_items?.length" class="space-y-2.5">
            <div
              v-for="item in stats.low_stock_items"
              :key="item.name"
              class="flex items-center gap-3"
            >
              <div
                :class="item.qty === 0 ? 'bg-red-500' : 'bg-amber-500'"
                class="w-2 h-2 rounded-full flex-shrink-0"
              ></div>
              <div class="flex-1 min-w-0">
                <p class="text-xs font-medium text-slate-800 dark:text-slate-200 truncate">{{ item.name }}</p>
                <p class="text-xs text-slate-500 dark:text-slate-400">{{ item.qty }} left / min {{ item.min_qty }}</p>
              </div>
              <a :href="route('purchase-orders.index')" class="text-xs text-indigo-600 hover:underline flex-shrink-0">
                Reorder
              </a>
            </div>
          </div>
          <div v-else class="flex flex-col items-center py-4 gap-2">
            <ThreeDIcon name="check" size="lg" />
            <p class="text-xs text-slate-400 dark:text-slate-500">All stock levels healthy</p>
          </div>
        </div>

        <!-- Pending Actions -->
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-100 dark:border-slate-700 p-5">
          <div class="flex items-center gap-2 mb-3">
            <ThreeDIcon name="activity" size="sm" />
            <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Pending Actions</h2>
          </div>
          <div class="space-y-1">
            <a
              :href="route('purchase-orders.index')"
              class="flex items-center justify-between p-2 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700/50 cursor-pointer transition-colors"
            >
              <span class="text-xs text-slate-700 dark:text-slate-300">POs awaiting review</span>
              <span class="text-xs font-bold px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300">
                {{ stats.pending_pos }}
              </span>
            </a>
            <div class="flex items-center justify-between p-2 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700/50 cursor-pointer transition-colors">
              <span class="text-xs text-slate-700 dark:text-slate-300">Low stock items</span>
              <span class="text-xs font-bold px-2 py-0.5 rounded-full bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300">
                {{ stats.low_stock_count }}
              </span>
            </div>
            <div class="flex items-center justify-between p-2 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700/50 cursor-pointer transition-colors">
              <span class="text-xs text-slate-700 dark:text-slate-300">Pending shipments</span>
              <span class="text-xs font-bold px-2 py-0.5 rounded-full bg-cyan-100 text-cyan-700 dark:bg-cyan-900/30 dark:text-cyan-300">
                {{ stats.pending_parcels }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ── Active Modules ── -->
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-100 dark:border-slate-700 p-5">
      <div class="flex items-center gap-2 mb-3">
        <ThreeDIcon name="layers" size="sm" />
        <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300">Active Modules</h2>
      </div>
      <div class="flex flex-wrap gap-2">
        <div
          v-for="mod in stats.modules"
          :key="mod.label"
          :class="mod.active
            ? 'bg-emerald-50 border-emerald-200 text-emerald-700 dark:bg-emerald-900/20 dark:border-emerald-800 dark:text-emerald-300'
            : 'bg-slate-50 border-slate-200 text-slate-400 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-500'"
          class="flex items-center gap-2 px-3 py-2 rounded-lg border text-xs font-medium"
        >
          <span
            :class="mod.active ? 'bg-emerald-500' : 'bg-slate-300'"
            class="w-2 h-2 rounded-full"
          ></span>
          {{ mod.label }}
        </div>
      </div>
    </div>

  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Head, usePage } from '@inertiajs/vue3'
import AppLayout   from '@/Layouts/AppLayout.vue'
import StatCard    from '@/Components/UI/StatCard.vue'
import ThreeDIcon  from '@/Components/UI/ThreeDIcon.vue'
import {
  ClipboardListIcon, ShoppingBagIcon, PackageIcon, UsersIcon,
} from 'lucide-vue-next'

const props = defineProps({
  stats: { type: Object, default: () => ({}) },
})

const page  = usePage()
const user  = computed(() => page.props.auth?.user)

const greeting = computed(() => {
  const h = new Date().getHours()
  if (h < 12) return 'Good morning'
  if (h < 17) return 'Good afternoon'
  return 'Good evening'
})

const today = computed(() => {
  return new Date().toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' })
})

const periods = [
  { label: 'Today',      value: 'today'  },
  { label: 'This Week',  value: 'week'   },
  { label: 'This Month', value: 'month'  },
  { label: 'This Year',  value: 'year'   },
]
const activePeriod = ref('month')

function fmtNum(n) {
  if (!n) return 0
  if (n >= 100000) return (n / 100000).toFixed(1) + 'L'
  return Number(n).toLocaleString()
}

const chartBars = [30, 45, 35, 60, 55, 70, 50, 80, 65, 75, 58, 85, 70, 90, 72, 88, 65, 55, 75, 68, 82, 78, 92, 85, 70, 60, 75, 88, 95, 80]

const quickLinks = [
  { label: 'New Purchase Order', href: route('purchase-orders.index'), icon3d: 'new_po'           },
  { label: 'Add Product',        href: route('products.index'),        icon3d: 'products'         },
  { label: 'Check Stock',        href: route('stock.index'),           icon3d: 'stock'            },
  { label: 'View Suppliers',     href: route('suppliers.index'),       icon3d: 'suppliers'        },
]

function statusBadge(status) {
  const map = {
    draft:             'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
    confirmed:         'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
    partially_shipped: 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300',
    shipped:           'bg-cyan-100 text-cyan-700 dark:bg-cyan-900/30 dark:text-cyan-300',
    received:          'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300',
    completed:         'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
    cancelled:         'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300',
  }
  return 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ' + (map[status] || 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300')
}

function fmtStatus(s) {
  return s ? s.charAt(0).toUpperCase() + s.slice(1).replace('_', ' ') : '—'
}
</script>
