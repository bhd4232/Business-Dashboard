<template>
  <AppLayout>
    <Head :title="customer.name" />

    <!-- Back -->
    <Link :href="route('customers.index')"
      class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 text-sm font-medium hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-all shadow-sm group mb-6">
      <ArrowLeftIcon class="w-4 h-4 group-hover:-translate-x-0.5 transition-transform" />
      Customers
    </Link>

    <!-- Header -->
    <div class="flex items-start justify-between mb-6">
      <div class="flex items-center gap-4">
        <div class="w-14 h-14 rounded-2xl bg-orange-100 dark:bg-orange-900/30 flex items-center justify-center">
          <ThreeDIcon name="customers" size="lg" />
        </div>
        <div>
          <div class="flex items-center gap-2">
            <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">{{ customer.name }}</h1>
            <span :class="customer.is_active
                ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300'
                : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300'"
              class="rounded-full px-2.5 py-0.5 text-xs font-medium">
              {{ customer.is_active ? 'Active' : 'Inactive' }}
            </span>
          </div>
          <p v-if="customer.business_name" class="text-sm text-slate-500 dark:text-slate-400">{{ customer.business_name }}</p>
          <div class="flex items-center gap-2 mt-1">
            <span class="text-xs font-mono text-slate-400 dark:text-slate-500">{{ customer.customer_code }}</span>
            <span :class="customer.type === 'wholesale'
                ? 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300'
                : 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300'"
              class="rounded-full px-2 py-0.5 text-xs font-medium capitalize">
              {{ customer.type }}
            </span>
            <span v-for="tag in customer.tags" :key="tag.id"
              class="rounded-full px-2 py-0.5 text-xs font-medium"
              :style="{ backgroundColor: tag.color + '22', color: tag.color }">
              {{ tag.name }}
            </span>
          </div>
        </div>
      </div>
      <div class="flex items-center gap-2">
        <Link :href="route('customers.edit', customer.id)"
          class="inline-flex items-center gap-2 border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50 text-sm font-medium px-4 py-2 rounded-lg transition-colors">
          <Icon3D name="Edit" size="sm" />
          Edit
        </Link>
        <button @click="confirmDelete"
          class="inline-flex items-center gap-2 border border-red-200 dark:border-red-800 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 text-sm font-medium px-4 py-2 rounded-lg transition-colors">
          <TrashIcon class="w-4 h-4" />
          Delete
        </button>
      </div>
    </div>

    <!-- Stat Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
      <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-5 shadow-sm">
        <p class="text-xs text-slate-500 dark:text-slate-400 mb-1">Total Orders</p>
        <p class="text-2xl font-bold text-slate-900 dark:text-slate-100">{{ customer.total_orders ?? 0 }}</p>
      </div>
      <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-5 shadow-sm">
        <p class="text-xs text-slate-500 dark:text-slate-400 mb-1">Total Delivered</p>
        <p class="text-2xl font-bold text-slate-900 dark:text-slate-100">৳{{ formatNumber(customer.total_delivered_value_bdt) }}</p>
      </div>
      <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-5 shadow-sm">
        <p class="text-xs text-slate-500 dark:text-slate-400 mb-1">Outstanding</p>
        <p class="text-2xl font-bold" :class="Number(customer.outstanding_balance_bdt) > 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-900 dark:text-slate-100'">
          ৳{{ formatNumber(customer.outstanding_balance_bdt) }}
        </p>
      </div>
      <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-5 shadow-sm">
        <p class="text-xs text-slate-500 dark:text-slate-400 mb-1">Credit Limit</p>
        <p class="text-2xl font-bold text-slate-900 dark:text-slate-100">৳{{ formatNumber(customer.credit_limit_bdt) }}</p>
        <div class="mt-2 h-1.5 rounded-full bg-slate-100 dark:bg-slate-700 overflow-hidden">
          <div class="h-full rounded-full transition-all"
            :class="creditUsedPct > 80 ? 'bg-red-500' : creditUsedPct > 50 ? 'bg-amber-500' : 'bg-emerald-500'"
            :style="{ width: Math.min(creditUsedPct, 100) + '%' }" />
        </div>
        <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">{{ creditUsedPct }}% used</p>
      </div>
    </div>

    <!-- 2 column layout -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Left: Contact & info -->
      <div class="space-y-5">

        <!-- Contact -->
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-5 shadow-sm">
          <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100 mb-3">Contact</h3>
          <div class="space-y-2.5">
            <div class="flex items-center gap-2">
              <PhoneIcon class="w-4 h-4 text-slate-400 dark:text-slate-500 flex-shrink-0" />
              <span class="text-sm text-slate-700 dark:text-slate-300 font-mono">{{ customer.phone }}</span>
            </div>
            <div v-if="customer.email" class="flex items-center gap-2">
              <MailIcon class="w-4 h-4 text-slate-400 dark:text-slate-500 flex-shrink-0" />
              <span class="text-sm text-slate-700 dark:text-slate-300">{{ customer.email }}</span>
            </div>
            <div v-if="customer.address || customer.district" class="flex items-start gap-2">
              <MapPinIcon class="w-4 h-4 text-slate-400 dark:text-slate-500 flex-shrink-0 mt-0.5" />
              <span class="text-sm text-slate-700 dark:text-slate-300">
                {{ [customer.address, customer.area, customer.city, customer.district].filter(Boolean).join(', ') }}
              </span>
            </div>
          </div>
        </div>

        <!-- Commercial -->
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-5 shadow-sm">
          <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100 mb-3">Commercial</h3>
          <dl class="space-y-2">
            <div class="flex justify-between text-sm">
              <dt class="text-slate-500 dark:text-slate-400">Price Tier</dt>
              <dd class="text-slate-900 dark:text-slate-100 font-medium">{{ customer.price_tier?.name || '—' }}</dd>
            </div>
            <div class="flex justify-between text-sm">
              <dt class="text-slate-500 dark:text-slate-400">Source</dt>
              <dd class="text-slate-900 dark:text-slate-100 capitalize">{{ customer.source || '—' }}</dd>
            </div>
            <div class="flex justify-between text-sm">
              <dt class="text-slate-500 dark:text-slate-400">Salesman</dt>
              <dd class="text-slate-900 dark:text-slate-100">{{ customer.assigned_salesman?.name || '—' }}</dd>
            </div>
            <div class="flex justify-between text-sm">
              <dt class="text-slate-500 dark:text-slate-400">Rating</dt>
              <dd>
                <div class="flex gap-0.5">
                  <StarIcon v-for="i in 5" :key="i" class="w-3.5 h-3.5"
                    :class="i <= (customer.rating || 0) ? 'text-amber-400 fill-amber-400' : 'text-slate-200 fill-slate-200 dark:text-slate-600 dark:fill-slate-600'" />
                </div>
              </dd>
            </div>
            <div class="flex justify-between text-sm">
              <dt class="text-slate-500 dark:text-slate-400">Last Order</dt>
              <dd class="text-slate-900 dark:text-slate-100">{{ customer.last_order_at ? formatDate(customer.last_order_at) : '—' }}</dd>
            </div>
          </dl>
        </div>

        <!-- Notes -->
        <div v-if="customer.notes" class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-5 shadow-sm">
          <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100 mb-2">Notes</h3>
          <p class="text-sm text-slate-600 dark:text-slate-400 whitespace-pre-wrap">{{ customer.notes }}</p>
        </div>
      </div>

      <!-- Right: Recent Orders -->
      <div class="lg:col-span-2">
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
          <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Recent Orders</h3>
          </div>
          <div v-if="!customer.sales_orders?.length" class="py-12 text-center">
            <ThreeDIcon name="orders" size="xl" class="mx-auto mb-2 opacity-30" />
            <p class="text-sm text-slate-400 dark:text-slate-500">No orders yet</p>
          </div>
          <table v-else class="w-full">
            <thead>
              <tr class="bg-slate-50 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700">
                <th class="text-left text-xs font-medium text-slate-500 dark:text-slate-400 px-5 py-2.5">Order #</th>
                <th class="text-left text-xs font-medium text-slate-500 dark:text-slate-400 px-5 py-2.5">Date</th>
                <th class="text-left text-xs font-medium text-slate-500 dark:text-slate-400 px-5 py-2.5">Total</th>
                <th class="text-left text-xs font-medium text-slate-500 dark:text-slate-400 px-5 py-2.5">Status</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="order in customer.sales_orders" :key="order.id"
                class="border-b border-slate-100 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                <td class="px-5 py-3 text-sm font-mono font-medium text-primary-600 dark:text-primary-400">{{ order.order_number }}</td>
                <td class="px-5 py-3 text-sm text-slate-600 dark:text-slate-400">{{ formatDate(order.created_at) }}</td>
                <td class="px-5 py-3 text-sm font-medium text-slate-900 dark:text-slate-100">৳{{ formatNumber(order.total_amount_bdt) }}</td>
                <td class="px-5 py-3">
                  <span :class="statusClass(order.status)" class="rounded-full px-2.5 py-0.5 text-xs font-medium capitalize">
                    {{ order.status }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Delete Confirm -->
    <ConfirmDialog
      :show="showDeleteDialog"
      title="Move customer to trash?"
      :description="`'${customer.name}' will be moved to trash. All related data is preserved.`"
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
import { ArrowLeftIcon, TrashIcon, PhoneIcon, MailIcon, MapPinIcon, StarIcon } from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'
import ThreeDIcon from '@/Components/UI/ThreeDIcon.vue'
import Icon3D from '@/Components/UI/Icon3D.vue'
import ConfirmDialog from '@/Components/UI/ConfirmDialog.vue'
import { useToast } from '@/Composables/useToast'

const props = defineProps({
  customer: { type: Object, required: true },
})

const { success, error: showError } = useToast()
const showDeleteDialog = ref(false)
const deleting = ref(false)

const creditUsedPct = computed(() => {
  const limit = Number(props.customer.credit_limit_bdt) || 0
  const used  = Number(props.customer.outstanding_balance_bdt) || 0
  if (!limit) return 0
  return Math.round((used / limit) * 100)
})

function confirmDelete() { showDeleteDialog.value = true }

async function executeDelete() {
  deleting.value = true
  try {
    await window.axios.delete(`/api/v1/customers/${props.customer.id}`)
    success(`"${props.customer.name}" moved to trash.`)
    router.visit(route('customers.index'))
  } catch (err) {
    showError(err.response?.data?.message || 'Failed to delete.')
  } finally {
    deleting.value = false
  }
}

function formatNumber(v) {
  if (!v) return '0'
  return Number(v).toLocaleString('en-BD')
}

function formatDate(d) {
  if (!d) return ''
  return new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
}

function statusClass(status) {
  const map = {
    pending:   'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
    confirmed: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
    packed:    'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
    delivered: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300',
    cancelled: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300',
  }
  return map[status] || 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300'
}
</script>
