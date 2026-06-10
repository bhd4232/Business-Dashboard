<template>
  <AppLayout>
    <Head :title="order.po_number" />

    <div class="mb-6">
      <BackButton label="Purchase Orders" to="purchase-orders.index" />
      <div class="flex items-start justify-between">
        <div>
          <h1 class="text-2xl font-semibold text-slate-900 dark:text-slate-100 font-mono">{{ order.po_number }}</h1>
          <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">{{ order.supplier?.name_english }}</p>
        </div>
        <div class="flex items-center gap-2">
          <button v-if="order.status === 'draft'" @click="confirmPO"
            class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
            <CheckCircleIcon class="w-4 h-4" /> Confirm
          </button>
          <button v-if="canCancel" @click="cancelPO"
            class="inline-flex items-center gap-2 border border-red-300 hover:bg-red-50 dark:hover:bg-red-900/20 text-red-600 text-sm font-medium px-4 py-2 rounded-lg transition-colors">
            <XCircleIcon class="w-4 h-4" /> Cancel
          </button>
          <Link v-if="order.status === 'draft'" :href="route('purchase-orders.edit', order.id)"
            class="inline-flex items-center gap-2 border border-slate-300 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700/50 text-slate-700 dark:text-slate-300 text-sm font-medium px-4 py-2 rounded-lg transition-colors">
            <PencilIcon class="w-4 h-4" /> Edit
          </Link>
          <button
            v-if="order.status === 'draft' || order.status === 'cancelled'"
            @click="showDeleteDialog = true"
            class="inline-flex items-center gap-2 border border-red-300 hover:bg-red-50 text-red-600 text-sm font-medium px-4 py-2 rounded-lg transition-colors">
            <TrashIcon class="w-4 h-4" /> Delete
          </button>
        </div>
      </div>
    </div>

    <!-- Status + Info Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
      <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-4">
        <p class="text-xs text-slate-500 dark:text-slate-400 mb-1">Status</p>
        <span :class="statusColor(order.status)" class="rounded-full px-2.5 py-0.5 text-xs font-medium">
          {{ statusLabel(order.status) }}
        </span>
      </div>
      <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-4">
        <p class="text-xs text-slate-500 dark:text-slate-400 mb-1">Order Date</p>
        <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">{{ order.order_date }}</p>
      </div>
      <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-4">
        <p class="text-xs text-slate-500 dark:text-slate-400 mb-1">Total (CNY)</p>
        <p class="text-sm font-semibold text-slate-800 dark:text-slate-200 font-mono">¥{{ Number(order.total_cny).toLocaleString() }}</p>
      </div>
      <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-4">
        <p class="text-xs text-slate-500 dark:text-slate-400 mb-1">Total (BDT)</p>
        <p class="text-sm font-semibold text-slate-800 dark:text-slate-200 font-mono">৳{{ Number(order.total_bdt).toLocaleString() }}</p>
      </div>
    </div>

    <!-- Tabs -->
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700">
      <div class="flex border-b border-slate-200 dark:border-slate-700 px-6">
        <button v-for="tab in tabs" :key="tab.id" @click="activeTab = tab.id"
          :class="['py-3 px-4 text-sm font-medium border-b-2 transition-colors -mb-px',
            activeTab === tab.id
              ? 'border-indigo-600 text-indigo-600'
              : 'border-transparent text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100']">
          {{ tab.label }}
        </button>
      </div>

      <!-- Items Tab -->
      <div v-if="activeTab === 'items'" class="p-6">
        <table class="w-full">
          <thead>
            <tr class="bg-slate-50 dark:bg-slate-700/50 rounded-lg">
              <th class="text-left text-sm font-medium text-slate-700 dark:text-slate-300 px-4 py-2">Product</th>
              <th class="text-right text-sm font-medium text-slate-700 dark:text-slate-300 px-4 py-2">Price (CNY)</th>
              <th class="text-right text-sm font-medium text-slate-700 dark:text-slate-300 px-4 py-2">Qty</th>
              <th class="text-right text-sm font-medium text-slate-700 dark:text-slate-300 px-4 py-2">Received</th>
              <th class="text-right text-sm font-medium text-slate-700 dark:text-slate-300 px-4 py-2">Total (CNY)</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in order.items" :key="item.id" class="border-b border-slate-100 dark:border-slate-700">
              <td class="px-4 py-3">
                <p class="text-sm font-medium text-slate-900 dark:text-slate-100">{{ item.product?.name }}</p>
                <p v-if="item.variant" class="text-xs text-slate-500 dark:text-slate-400">{{ item.variant.variant_name }}</p>
                <p class="text-xs text-slate-400 dark:text-slate-500 font-mono">{{ item.product?.sku }}</p>
              </td>
              <td class="px-4 py-3 text-right text-sm font-mono text-slate-700 dark:text-slate-300">
                ¥{{ Number(item.supplier_price_cny).toLocaleString() }}
              </td>
              <td class="px-4 py-3 text-right text-sm font-mono text-slate-700 dark:text-slate-300">{{ item.quantity }}</td>
              <td class="px-4 py-3 text-right text-sm font-mono">
                <span :class="item.received_qty >= item.quantity ? 'text-emerald-600' : 'text-amber-600'">
                  {{ item.received_qty }}/{{ item.quantity }}
                </span>
              </td>
              <td class="px-4 py-3 text-right text-sm font-mono font-semibold text-slate-800 dark:text-slate-200">
                ¥{{ Number(item.subtotal_cny).toLocaleString() }}
              </td>
            </tr>
          </tbody>
          <tfoot>
            <tr class="border-t-2 border-slate-200 dark:border-slate-700">
              <td colspan="4" class="px-4 py-3 text-right text-sm font-semibold text-slate-700 dark:text-slate-300">Total</td>
              <td class="px-4 py-3 text-right text-sm font-mono font-bold text-slate-900 dark:text-slate-100">
                ¥{{ Number(order.total_cny).toLocaleString() }}
              </td>
            </tr>
          </tfoot>
        </table>
      </div>

      <!-- Details Tab -->
      <div v-if="activeTab === 'details'" class="p-6">
        <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <dt class="text-xs text-slate-500 dark:text-slate-400 mb-0.5">Supplier</dt>
            <dd class="text-sm font-medium text-slate-800 dark:text-slate-200">{{ order.supplier?.name_english }}</dd>
          </div>
          <div>
            <dt class="text-xs text-slate-500 dark:text-slate-400 mb-0.5">Exchange Rate</dt>
            <dd class="text-sm font-mono text-slate-800 dark:text-slate-200">1 CNY = ৳{{ order.exchange_rate }}</dd>
          </div>
          <div>
            <dt class="text-xs text-slate-500 dark:text-slate-400 mb-0.5">Expected Delivery</dt>
            <dd class="text-sm text-slate-800 dark:text-slate-200">{{ order.expected_delivery_date || '—' }}</dd>
          </div>
          <div>
            <dt class="text-xs text-slate-500 dark:text-slate-400 mb-0.5">Created By</dt>
            <dd class="text-sm text-slate-800 dark:text-slate-200">{{ order.created_by?.name }}</dd>
          </div>
          <div v-if="order.approved_by">
            <dt class="text-xs text-slate-500 dark:text-slate-400 mb-0.5">Approved By</dt>
            <dd class="text-sm text-slate-800 dark:text-slate-200">{{ order.approved_by?.name }} — {{ order.approved_at }}</dd>
          </div>
          <div v-if="order.notes" class="md:col-span-2">
            <dt class="text-xs text-slate-500 dark:text-slate-400 mb-0.5">Notes</dt>
            <dd class="text-sm text-slate-800 dark:text-slate-200">{{ order.notes }}</dd>
          </div>
        </dl>
      </div>
    </div>

    <!-- Delete Confirm Dialog -->
    <ConfirmDialog
      :show="showDeleteDialog"
      title="Move to trash?"
      :description="`PO '${order.po_number}' will be moved to trash. You can restore it later.`"
      confirm-text="Move to Trash"
      variant="danger"
      :loading="deleting"
      @confirm="executeDelete"
      @cancel="showDeleteDialog = false"
    />

  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { ChevronLeftIcon, PencilIcon, CheckCircleIcon, XCircleIcon, TrashIcon } from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'
import BackButton from '@/Components/UI/BackButton.vue'
import ConfirmDialog from '@/Components/UI/ConfirmDialog.vue'
import { useToast } from '@/Composables/useToast'

const props = defineProps({
  order: { type: Object, required: true },
})

const { success, error: showError } = useToast()

const activeTab = ref('items')
const tabs = [
  { id: 'items',   label: 'Items' },
  { id: 'details', label: 'Details' },
]

const canCancel       = computed(() => ['draft', 'confirmed'].includes(props.order.status))
const showDeleteDialog = ref(false)
const deleting         = ref(false)

const statusColors = {
  draft:             'bg-purple-100 text-purple-700',
  confirmed:         'bg-blue-100 text-blue-700',
  partially_shipped: 'bg-cyan-100 text-cyan-700',
  shipped:           'bg-indigo-100 text-indigo-700',
  received:          'bg-emerald-100 text-emerald-700',
  completed:         'bg-green-100 text-green-700',
  cancelled:         'bg-red-100 text-red-700',
}
const statusLabels = {
  draft: 'Draft', confirmed: 'Confirmed', partially_shipped: 'Partially Shipped',
  shipped: 'Shipped', received: 'Received', completed: 'Completed', cancelled: 'Cancelled',
}
function statusColor(s) { return statusColors[s] || 'bg-slate-100 text-slate-700' }
function statusLabel(s) { return statusLabels[s] || s }

async function confirmPO() {
  if (!confirm('Confirm this purchase order?')) return
  try {
    await window.axios.post(`/api/v1/purchase-orders/${props.order.id}/confirm`)
    success('Purchase order confirmed successfully!')
    router.reload()
  } catch (err) {
    showError(err.response?.data?.message || 'Failed to confirm purchase order.')
  }
}

async function cancelPO() {
  if (!confirm('Cancel this purchase order?')) return
  try {
    await window.axios.post(`/api/v1/purchase-orders/${props.order.id}/cancel`)
    success('Purchase order cancelled.')
    router.reload()
  } catch (err) {
    showError(err.response?.data?.message || 'Failed to cancel purchase order.')
  }
}

async function executeDelete() {
  deleting.value = true
  try {
    await window.axios.delete(`/api/v1/purchase-orders/${props.order.id}`)
    success(`PO "${props.order.po_number}" moved to trash.`)
    showDeleteDialog.value = false
    router.visit(route('purchase-orders.index'))
  } catch (err) {
    showError(err.response?.data?.message || 'Failed to delete purchase order.')
  } finally {
    deleting.value = false
  }
}
</script>
