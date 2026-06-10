<template>
  <aside
    :class="[
      'flex flex-col bg-white dark:bg-slate-900 border-r border-slate-200 dark:border-slate-700 shrink-0 z-30 h-screen transition-all duration-300 ease-in-out',
      collapsed ? 'w-16' : 'w-60',
    ]"
  >
    <!-- ── Logo ── -->
    <div class="h-14 flex items-center px-4 border-b border-slate-200 dark:border-slate-700 shrink-0 gap-2.5">
      <div class="shrink-0 transition-transform duration-200 hover:scale-110">
        <ThreeDIcon name="zap" size="md" />
      </div>
      <div v-if="!collapsed" class="overflow-hidden">
        <p class="text-sm font-bold text-slate-900 dark:text-slate-100 leading-none">ZamZam</p>
        <p class="text-xs text-slate-400 dark:text-slate-500 leading-none mt-0.5">ERP v1.0</p>
      </div>
    </div>

    <!-- ── Navigation ── -->
    <nav class="flex-1 overflow-y-auto py-3 px-2 space-y-0.5">

      <!-- Dashboard -->
      <SidebarItem
        :collapsed="collapsed"
        label="Dashboard"
        icon="LayoutDashboard"
        icon3d="dashboard"
        route-name="dashboard"
        icon-color="text-primary-600"
      />

      <!-- Procurement -->
      <SidebarGroup
        :collapsed="collapsed"
        label="Procurement"
        icon="ShoppingBag"
        icon3d="procurement"
        icon-color="text-violet-600"
        group-key="procurement"
        :open="openGroups.procurement"
        @toggle="toggleGroup('procurement')"
        :items="[
          { label: 'Suppliers',       routeName: 'suppliers.index'       },
          { label: 'Products',        routeName: 'products.index'        },
          { label: 'Purchase Orders', routeName: 'purchase-orders.index' },
          { label: 'Categories',      routeName: 'categories.index'      },
        ]"
      />

      <!-- Inventory & Warehouse -->
      <SidebarGroup
        :collapsed="collapsed"
        label="Inventory & Warehouse"
        icon="Warehouse"
        icon3d="inventory"
        icon-color="text-amber-600"
        group-key="inventory"
        :open="openGroups.inventory"
        @toggle="toggleGroup('inventory')"
        :items="[
          { label: 'Warehouses',   routeName: 'warehouses.index'        },
          { label: 'Stock',        routeName: 'stock.index'             },
          { label: 'Low Stock',    routeName: 'stock.low-stock'         },
          { label: 'Transfers',    routeName: 'stock-transfers.index'   },
          { label: 'Adjustments',  routeName: 'stock-adjustments.index' },
          { label: 'Barcodes',     routeName: 'barcodes.index'          },
        ]"
      />

      <!-- Sales & Orders -->
      <SidebarGroup
        :collapsed="collapsed"
        label="Sales & Orders"
        icon="ShoppingCart"
        icon3d="sales"
        icon-color="text-orange-500"
        group-key="sales"
        :open="openGroups.sales"
        @toggle="toggleGroup('sales')"
        :items="[
          { label: 'Orders',           routeName: 'sales-orders.index'  },
          { label: 'Customers',        routeName: 'customers.index'     },
          { label: 'Invoices',         routeName: 'invoices.index'      },
          { label: 'Invoice Settings', routeName: 'settings.invoice'    },
        ]"
      />

      <!-- Shipping & Logistics -->
      <SidebarGroup
        :collapsed="collapsed"
        label="Shipping & Logistics"
        icon="Truck"
        icon3d="shipping"
        icon-color="text-cyan-600"
        group-key="shipping"
        :open="openGroups.shipping"
        @toggle="toggleGroup('shipping')"
        :items="[
          { label: 'International (CN→BD)', routeName: 'shipments.index' },
          { label: 'Domestic',              routeName: 'parcels.index',   comingSoon: true },
        ]"
      />

      <!-- Accounts & Finance -->
      <SidebarGroup
        :collapsed="collapsed"
        label="Accounts & Finance"
        icon="Banknote"
        icon3d="finance"
        icon-color="text-emerald-600"
        group-key="finance"
        :open="openGroups.finance"
        @toggle="toggleGroup('finance')"
        :items="[
          { label: 'Payments', routeName: 'payments.index', comingSoon: true },
          { label: 'Accounts', routeName: 'accounts.index', comingSoon: true },
        ]"
      />

      <!-- Chat & AI (conditional) -->
      <SidebarGroup
        v-if="modules.conversation_ai"
        :collapsed="collapsed"
        label="Chat & AI"
        icon="MessageSquare"
        icon3d="chat"
        icon-color="text-violet-500"
        group-key="chat"
        :open="openGroups.chat"
        @toggle="toggleGroup('chat')"
        :items="[
          { label: 'Conversations', routeName: 'conversations.index' },
          { label: 'Workflows',     routeName: 'workflows.index'     },
        ]"
      />

      <!-- Reports -->
      <SidebarItem
        :collapsed="collapsed"
        label="Reports"
        icon="BarChart2"
        icon3d="reports"
        route-name="reports.index"
        icon-color="text-primary-500"
        coming-soon
      />

      <!-- Settings -->
      <SidebarItem
        :collapsed="collapsed"
        label="Settings"
        icon="Settings"
        icon3d="settings"
        route-name="settings.index"
        icon-color="text-slate-500"
      />

    </nav>

    <!-- ── User Footer ── -->
    <div class="shrink-0 border-t border-slate-200 dark:border-slate-700 p-3">
      <div class="flex items-center gap-3">
        <div class="w-8 h-8 rounded-full bg-primary-100 dark:bg-primary-900/40 flex items-center justify-center shrink-0">
          <span class="text-xs font-bold text-primary-700 dark:text-primary-300">{{ userInitial }}</span>
        </div>
        <div v-if="!collapsed" class="overflow-hidden flex-1">
          <p class="text-xs font-medium text-slate-900 dark:text-slate-100 truncate">{{ user?.name }}</p>
          <p class="text-xs text-slate-400 dark:text-slate-500 truncate">{{ user?.email }}</p>
        </div>
        <!-- Collapse toggle -->
        <button
          @click="$emit('toggle')"
          class="ml-auto p-1 rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-600 dark:hover:text-slate-300 transition-colors shrink-0"
          :title="collapsed ? 'Expand sidebar' : 'Collapse sidebar'"
        >
          <ChevronLeftIcon v-if="!collapsed" class="w-4 h-4" />
          <ChevronRightIcon v-else class="w-4 h-4" />
        </button>
      </div>
    </div>
  </aside>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { ChevronLeftIcon, ChevronRightIcon } from 'lucide-vue-next'
import SidebarItem  from '@/Components/UI/SidebarItem.vue'
import SidebarGroup from '@/Components/UI/SidebarGroup.vue'
import ThreeDIcon   from '@/Components/UI/ThreeDIcon.vue'

defineProps({ collapsed: Boolean })
defineEmits(['toggle'])

const page        = usePage()
const user        = computed(() => page.props.auth?.user)
const userInitial = computed(() => user.value?.name?.[0]?.toUpperCase() ?? 'U')
const modules     = computed(() => page.props.modules || {})

// ─── Route-based group detection ─────────────────────────────────────────────
const routeGroupMap = {
  procurement: ['suppliers', 'products', 'categories', 'purchase-orders'],
  inventory:   ['warehouses', 'stock', 'stock-transfers', 'stock-adjustments', 'barcodes'],
  shipping:    ['shipping'],
  sales:       ['sales-orders', 'customers', 'invoices', 'settings/invoice'],
  finance:     ['payments', 'accounts', 'expenses'],
  chat:        ['conversations', 'workflows'],
}

const openGroups = ref({
  procurement: true,
  inventory:   true,
  sales:       false,
  shipping:    true,
  finance:     false,
  chat:        false,
})

watch(() => page.url, () => {
  Object.keys(routeGroupMap).forEach(key => {
    const segments = routeGroupMap[key] ?? []
    if (segments.some(seg => page.url.startsWith('/' + seg))) {
      openGroups.value[key] = true
    }
  })
}, { immediate: true })

function toggleGroup(key) {
  openGroups.value[key] = !openGroups.value[key]
}
</script>
