<template>
  <Teleport to="body">
    <!-- Backdrop -->
    <Transition
      enter-active-class="transition-opacity duration-200"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition-opacity duration-200"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0">
      <div v-if="customerId" class="fixed inset-0 z-50 bg-black/40 dark:bg-black/60" @click="$emit('close')" />
    </Transition>

    <!-- Drawer -->
    <Transition
      enter-active-class="transition-transform duration-250 ease-out"
      enter-from-class="translate-x-full"
      enter-to-class="translate-x-0"
      leave-active-class="transition-transform duration-200 ease-in"
      leave-from-class="translate-x-0"
      leave-to-class="translate-x-full">

      <div v-if="customerId"
        class="fixed right-0 top-0 h-full z-50 w-full max-w-md bg-white dark:bg-slate-900 shadow-2xl flex flex-col overflow-hidden">

        <!-- Loading overlay -->
        <div v-if="loading" class="absolute inset-0 z-10 flex items-center justify-center bg-white/80 dark:bg-slate-900/80">
          <LoaderIcon class="w-6 h-6 animate-spin text-primary-600" />
        </div>

        <!-- ── Header ─────────────────────────────────────────────── -->
        <div class="flex items-start justify-between px-5 py-4 border-b border-slate-200 dark:border-slate-700 shrink-0">
          <div class="flex items-center gap-3 min-w-0">
            <!-- Avatar -->
            <div class="w-11 h-11 rounded-full bg-primary-100 dark:bg-primary-900/40 text-primary-700 dark:text-primary-300 flex items-center justify-center font-bold text-lg shrink-0 uppercase">
              {{ avatarLetter }}
            </div>
            <div class="min-w-0">
              <div class="flex items-center gap-2 flex-wrap">
                <h2 class="text-base font-bold text-slate-900 dark:text-slate-100 truncate">
                  {{ customer?.business_name || customer?.name }}
                </h2>
                <span v-if="customer?.business_name && customer?.name !== customer?.business_name"
                  class="text-xs text-slate-500 dark:text-slate-400 truncate">
                  ({{ customer.name }})
                </span>
              </div>
              <div class="flex items-center gap-2 mt-0.5 flex-wrap">
                <span class="text-xs text-slate-500 dark:text-slate-400 font-mono">#{{ customer?.customer_code }}</span>
                <span :class="tierBadge.cls" class="text-xs font-bold px-1.5 py-0.5 rounded">{{ tierBadge.label }}</span>
                <span :class="customer?.is_active
                  ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300'
                  : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300'"
                  class="text-xs font-medium px-1.5 py-0.5 rounded">
                  {{ customer?.is_active ? 'Active' : 'Inactive' }}
                </span>
              </div>
            </div>
          </div>
          <button @click="$emit('close')"
            class="p-1.5 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors shrink-0 ml-2">
            <XIcon class="w-5 h-5" />
          </button>
        </div>

        <!-- ── Scrollable body ──────────────────────────────────────── -->
        <div class="flex-1 overflow-y-auto">

          <!-- Contact info -->
          <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-700/50">
            <h3 class="text-xs font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wide mb-3">Contact</h3>
            <div class="space-y-2.5">

              <!-- Phone -->
              <div class="flex items-center gap-2">
                <PhoneIcon class="w-4 h-4 text-slate-400 shrink-0" />
                <span class="text-sm font-mono text-slate-700 dark:text-slate-200">{{ customer?.phone }}</span>
                <button @click="copyText(customer?.phone)"
                  class="p-0.5 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 rounded hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors" title="Copy">
                  <CopyIcon class="w-3.5 h-3.5" />
                </button>
                <a v-if="customer?.phone"
                  :href="`https://wa.me/${formatPhoneWa(customer.phone)}`"
                  target="_blank"
                  class="p-0.5 text-emerald-500 hover:text-emerald-600 rounded hover:bg-emerald-50 dark:hover:bg-emerald-900/20 transition-colors" title="WhatsApp">
                  <MessageCircleIcon class="w-4 h-4" />
                </a>
              </div>

              <!-- Email -->
              <div v-if="customer?.email" class="flex items-center gap-2">
                <MailIcon class="w-4 h-4 text-slate-400 shrink-0" />
                <span class="text-sm text-slate-700 dark:text-slate-200">{{ customer.email }}</span>
              </div>

              <!-- Address -->
              <div v-if="customer?.address || customer?.city" class="flex items-start gap-2">
                <MapPinIcon class="w-4 h-4 text-slate-400 shrink-0 mt-0.5" />
                <span class="text-sm text-slate-700 dark:text-slate-200 leading-snug">
                  {{ [customer?.address, customer?.area, customer?.city, customer?.district].filter(Boolean).join(', ') }}
                </span>
              </div>

            </div>
          </div>

          <!-- Stats -->
          <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-700/50">
            <h3 class="text-xs font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wide mb-3">Overview</h3>
            <div class="grid grid-cols-3 gap-3">
              <div class="bg-slate-50 dark:bg-slate-800 rounded-xl p-3 text-center">
                <div class="text-lg font-bold text-slate-800 dark:text-slate-100">{{ customer?.total_orders ?? 0 }}</div>
                <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Total Orders</div>
              </div>
              <div class="bg-slate-50 dark:bg-slate-800 rounded-xl p-3 text-center">
                <div class="text-sm font-bold text-emerald-600 dark:text-emerald-400 font-mono">
                  ৳{{ formatNum(customer?.total_delivered_value_bdt) }}
                </div>
                <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Delivered</div>
              </div>
              <div class="bg-slate-50 dark:bg-slate-800 rounded-xl p-3 text-center">
                <div :class="Number(customer?.outstanding_balance_bdt) > 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-800 dark:text-slate-100'"
                  class="text-sm font-bold font-mono">
                  ৳{{ formatNum(customer?.outstanding_balance_bdt) }}
                </div>
                <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Outstanding</div>
              </div>
            </div>
          </div>

          <!-- Credit & Price Tier -->
          <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-700/50">
            <div class="grid grid-cols-2 gap-4">
              <div>
                <div class="text-xs text-slate-500 dark:text-slate-400 mb-1">Price Tier</div>
                <div v-if="customer?.price_tier" class="flex items-center gap-1.5">
                  <span class="w-2 h-2 rounded-full bg-primary-500 inline-block"></span>
                  <span class="text-sm font-medium text-slate-800 dark:text-slate-200">{{ customer.price_tier.name }}</span>
                  <span v-if="customer.price_tier.discount_percent > 0" class="text-xs text-emerald-600 dark:text-emerald-400">
                    ({{ customer.price_tier.discount_percent }}% off)
                  </span>
                </div>
                <span v-else class="text-sm text-slate-400 dark:text-slate-500">—</span>
              </div>
              <div>
                <div class="text-xs text-slate-500 dark:text-slate-400 mb-1">Credit Limit</div>
                <div v-if="Number(customer?.credit_limit_bdt) > 0" class="text-sm font-mono font-medium text-slate-800 dark:text-slate-200">
                  ৳{{ formatNum(customer?.credit_limit_bdt) }}
                </div>
                <span v-else class="text-sm text-slate-400 dark:text-slate-500">No limit</span>
              </div>
            </div>
          </div>

          <!-- Tags -->
          <div v-if="customer?.tags?.length" class="px-5 py-4 border-b border-slate-100 dark:border-slate-700/50">
            <h3 class="text-xs font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wide mb-2">Tags</h3>
            <div class="flex flex-wrap gap-1.5">
              <span v-for="tag in customer.tags" :key="tag.id"
                class="text-xs font-medium px-2.5 py-1 rounded-full border"
                :style="{ borderColor: tag.color + '60', color: tag.color, backgroundColor: tag.color + '15' }">
                {{ tag.name }}
              </span>
            </div>
          </div>

          <!-- Notes -->
          <div v-if="customer?.notes" class="px-5 py-4 border-b border-slate-100 dark:border-slate-700/50">
            <h3 class="text-xs font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wide mb-2">Notes</h3>
            <p class="text-sm text-slate-600 dark:text-slate-300 leading-relaxed">{{ customer.notes }}</p>
          </div>

          <!-- Recent Orders -->
          <div class="px-5 py-4">
            <h3 class="text-xs font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wide mb-3">
              Recent Orders
              <span v-if="customer?.sales_orders?.length" class="ml-1 text-slate-300 dark:text-slate-600 font-normal normal-case">
                ({{ customer.sales_orders.length }})
              </span>
            </h3>

            <div v-if="!customer?.sales_orders?.length" class="text-sm text-slate-400 dark:text-slate-500 py-2">
              No orders yet.
            </div>

            <div v-else class="space-y-2">
              <Link
                v-for="order in customer.sales_orders.slice(0, 8)" :key="order.id"
                :href="route('sales-orders.show', order.id)"
                @click="$emit('close')"
                class="flex items-center justify-between p-2.5 rounded-lg border border-slate-100 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors group">
                <div>
                  <span class="text-sm font-mono font-semibold text-primary-600 dark:text-primary-400 group-hover:underline">
                    {{ order.order_no }}
                  </span>
                  <div class="text-xs text-slate-400 dark:text-slate-500 mt-0.5">{{ formatDate(order.created_at) }}</div>
                </div>
                <div class="text-right">
                  <span :class="orderStatusColor(order.status)" class="text-xs font-medium px-2 py-0.5 rounded-full capitalize">
                    {{ orderStatusLabel(order.status) }}
                  </span>
                  <div class="text-xs font-mono text-slate-700 dark:text-slate-300 mt-0.5">৳{{ formatNum(order.total_bdt) }}</div>
                </div>
              </Link>
            </div>
          </div>
        </div>

        <!-- ── Footer ─────────────────────────────────────────────── -->
        <div class="px-5 py-3 border-t border-slate-200 dark:border-slate-700 shrink-0 bg-slate-50 dark:bg-slate-800/50">
          <div class="flex items-center gap-2">
            <Link :href="customer ? route('customers.show', customer.id) : '#'"
              @click="$emit('close')"
              class="flex-1 inline-flex items-center justify-center gap-2 bg-primary-600 hover:bg-primary-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
              <ExternalLinkIcon class="w-4 h-4" />
              View Full Profile
            </Link>
            <Link v-if="customer" :href="route('customers.edit', customer.id)"
              @click="$emit('close')"
              class="inline-flex items-center justify-center gap-2 border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 text-sm font-medium px-3 py-2 rounded-lg transition-colors">
              <PencilIcon class="w-4 h-4" />
              Edit
            </Link>
          </div>
        </div>

      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { ref, watch, computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import {
  XIcon, PhoneIcon, MailIcon, MapPinIcon, CopyIcon,
  MessageCircleIcon, ExternalLinkIcon, PencilIcon, LoaderIcon,
} from 'lucide-vue-next'
import { useToast } from '@/Composables/useToast'

// ─── Props / emits ────────────────────────────────────────────────────────

const props = defineProps({
  customerId: { type: [Number, null], default: null },
})

defineEmits(['close'])

const { success, error: showError } = useToast()

// ─── State ────────────────────────────────────────────────────────────────

const customer = ref(null)
const loading  = ref(false)

// ─── Fetch when customerId changes ────────────────────────────────────────

watch(() => props.customerId, async (id) => {
  if (!id) { customer.value = null; return }
  loading.value = true
  try {
    const res = await window.axios.get(`/api/v1/customers/${id}`)
    customer.value = res.data
  } catch {
    showError('Failed to load customer details.')
  } finally {
    loading.value = false
  }
}, { immediate: true })

// ─── Computed helpers ─────────────────────────────────────────────────────

const avatarLetter = computed(() => {
  const name = customer.value?.business_name || customer.value?.name || '?'
  return name.charAt(0).toUpperCase()
})

const tierBadge = computed(() => {
  const n = customer.value?.total_orders ?? 0
  if (n >= 50) return { label: 'VIP',     cls: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' }
  if (n  <= 2) return { label: 'NEW',     cls: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300' }
  return             { label: 'REGULAR', cls: 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300' }
})

// ─── Order status helpers ─────────────────────────────────────────────────

const ORDER_LABELS = {
  draft: 'Pending', on_hold: 'On Hold', confirmed: 'Approved',
  processing: 'Processing', picked: 'Ready To Ship', dispatched: 'In-Transit',
  delivered: 'Delivered', flagged: 'Flagged', cancelled: 'Cancelled', returned: 'Returned',
}
const ORDER_COLORS = {
  draft:      'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
  on_hold:    'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300',
  confirmed:  'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
  processing: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
  picked:     'bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-300',
  dispatched: 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300',
  delivered:  'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
  flagged:    'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300',
  cancelled:  'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300',
  returned:   'bg-pink-100 text-pink-700 dark:bg-pink-900/30 dark:text-pink-300',
}

function orderStatusLabel(s) { return ORDER_LABELS[s] || s }
function orderStatusColor(s) { return ORDER_COLORS[s] || 'bg-slate-100 text-slate-600' }

// ─── Formatters ───────────────────────────────────────────────────────────

function formatNum(v) {
  if (!v && v !== 0) return '0'
  return Number(v).toLocaleString('en-BD')
}

function formatDate(d) {
  if (!d) return ''
  return new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
}

function formatPhoneWa(phone) {
  if (!phone) return ''
  let p = phone.replace(/[\s\-()]/g, '')
  if (p.startsWith('01')) p = '88' + p
  if (p.startsWith('+')) p = p.slice(1)
  return p
}

async function copyText(text) {
  if (!text) return
  try {
    await navigator.clipboard.writeText(text)
    success('Copied!')
  } catch {
    showError('Copy failed.')
  }
}
</script>
