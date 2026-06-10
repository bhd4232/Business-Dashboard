<template>
  <AppLayout>
    <Head :title="order ? `Invoice for ${order.order_no}` : 'New Invoice'" />

    <!-- Back -->
    <Link
      v-if="order"
      :href="route('sales-orders.show', order.id)"
      class="inline-flex items-center gap-1.5 text-sm text-slate-600 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200 border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-1.5 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-all shadow-sm group mb-4">
      <ArrowLeftIcon class="w-4 h-4 group-hover:-translate-x-0.5 transition-transform" />
      {{ order.order_no }}
    </Link>
    <Link
      v-else
      :href="route('invoices.index')"
      class="inline-flex items-center gap-1.5 text-sm text-slate-600 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200 border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-1.5 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-all shadow-sm group mb-4">
      <ArrowLeftIcon class="w-4 h-4 group-hover:-translate-x-0.5 transition-transform" />
      Invoices
    </Link>

    <!-- Header -->
    <div class="flex items-start gap-3 mb-6">
      <ThreeDIcon name="sales" size="lg" />
      <div>
        <h1 class="text-2xl font-semibold text-slate-900 dark:text-slate-100">
          {{ order ? `Invoice for ${order.order_no}` : 'New Invoice' }}
        </h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">
          {{ order ? 'Items pre-filled from Sales Order — quantities and prices are editable.' : 'Create a standalone invoice for a customer.' }}
        </p>
      </div>
    </div>

    <!-- Error Banner -->
    <div v-if="error" class="mb-4 p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700/50 rounded-xl text-sm text-red-700 dark:text-red-400 flex items-center gap-2">
      <AlertCircleIcon class="w-4 h-4 shrink-0" />
      {{ error }}
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

      <!-- Left Column: Form fields -->
      <div class="lg:col-span-2 space-y-5">

        <!-- SO Info (read-only) -->
        <div v-if="order" class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700/50 rounded-xl p-4">
          <h3 class="text-sm font-semibold text-blue-700 dark:text-blue-300 mb-2">Linked Sales Order</h3>
          <div class="grid grid-cols-2 gap-3 text-sm">
            <div>
              <span class="text-xs text-blue-500 dark:text-blue-400">Order No</span>
              <div class="font-mono font-semibold text-blue-800 dark:text-blue-200">{{ order.order_no }}</div>
            </div>
            <div>
              <span class="text-xs text-blue-500 dark:text-blue-400">Customer</span>
              <div class="text-blue-800 dark:text-blue-200">{{ order.customer?.name }}</div>
            </div>
            <div>
              <span class="text-xs text-blue-500 dark:text-blue-400">Total</span>
              <div class="font-mono text-blue-800 dark:text-blue-200">৳{{ formatNumber(order.total_bdt) }}</div>
            </div>
            <div>
              <span class="text-xs text-blue-500 dark:text-blue-400">Status</span>
              <div class="capitalize text-blue-800 dark:text-blue-200">{{ order.status }}</div>
            </div>
          </div>
        </div>

        <!-- Customer (standalone) -->
        <div v-if="!order" class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-5">
          <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-300 mb-3">Customer</h3>
          <select
            v-model="form.customer_id"
            class="w-full border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500/40"
            :class="{ 'border-red-400': fieldErrors.customer_id }"
          >
            <option value="">Select Customer...</option>
            <option v-for="c in customers" :key="c.id" :value="c.id">
              {{ c.name }}{{ c.business_name ? ` — ${c.business_name}` : '' }}{{ c.phone ? ` (${c.phone})` : '' }}
            </option>
          </select>
          <p v-if="fieldErrors.customer_id" class="text-xs text-red-500 mt-1">{{ fieldErrors.customer_id[0] }}</p>
        </div>

        <!-- Invoice Details -->
        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-5">
          <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-300 mb-3">Invoice Details</h3>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs text-slate-500 dark:text-slate-400 mb-1">Issue Date <span class="text-red-500">*</span></label>
              <input
                v-model="form.issue_date"
                type="date"
                class="w-full border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500/40"
                :class="{ 'border-red-400': fieldErrors.issue_date }"
              />
              <p v-if="fieldErrors.issue_date" class="text-xs text-red-500 mt-1">{{ fieldErrors.issue_date[0] }}</p>
            </div>
            <div>
              <label class="block text-xs text-slate-500 dark:text-slate-400 mb-1">Due Date</label>
              <input
                v-model="form.due_date"
                type="date"
                class="w-full border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500/40"
              />
            </div>
          </div>
          <div class="mt-4">
            <label class="block text-xs text-slate-500 dark:text-slate-400 mb-1">Notes</label>
            <textarea
              v-model="form.notes"
              rows="3"
              class="w-full border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500/40 resize-none"
              placeholder="Optional notes for this invoice..."
            />
          </div>
        </div>

        <!-- Items -->
        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-5">
          <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-300">Items</h3>
            <button
              type="button"
              @click="addItem"
              class="inline-flex items-center gap-1.5 text-xs text-primary-600 dark:text-primary-400 hover:text-primary-700 dark:hover:text-primary-300 font-medium"
            >
              <PlusCircleIcon class="w-4 h-4" /> Add Item
            </button>
          </div>

          <p v-if="fieldErrors.items" class="text-xs text-red-500 mb-2">{{ fieldErrors.items[0] }}</p>

          <div class="space-y-3">
            <div
              v-for="(item, idx) in form.items"
              :key="idx"
              class="grid grid-cols-12 gap-2 items-start p-3 bg-slate-50 dark:bg-slate-700/50 rounded-lg border border-slate-100 dark:border-slate-700"
            >
              <div class="col-span-12 sm:col-span-5">
                <label class="block text-xs text-slate-400 mb-1">Product</label>
                <input
                  v-model="item.product_name"
                  type="text"
                  placeholder="Product name or SKU..."
                  class="w-full border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 rounded-lg px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500/40"
                  readonly
                />
              </div>
              <div class="col-span-4 sm:col-span-2">
                <label class="block text-xs text-slate-400 mb-1">Qty</label>
                <input
                  v-model.number="item.quantity"
                  @input="recalculate"
                  type="number"
                  min="1"
                  class="w-full border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 rounded-lg px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500/40"
                />
              </div>
              <div class="col-span-5 sm:col-span-2">
                <label class="block text-xs text-slate-400 mb-1">Unit Price (৳)</label>
                <input
                  v-model.number="item.unit_price_bdt"
                  @input="recalculate"
                  type="number"
                  min="0"
                  step="0.01"
                  class="w-full border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 rounded-lg px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500/40"
                />
              </div>
              <div class="col-span-3 sm:col-span-1">
                <label class="block text-xs text-slate-400 mb-1">Disc%</label>
                <input
                  v-model.number="item.discount_percent"
                  @input="recalculate"
                  type="number"
                  min="0"
                  max="100"
                  step="0.01"
                  class="w-full border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 rounded-lg px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500/40"
                />
              </div>
              <div class="col-span-3 sm:col-span-2 text-right pt-5">
                <span class="text-sm font-mono font-semibold text-slate-700 dark:text-slate-300">
                  ৳{{ formatNumber(item.quantity * item.unit_price_bdt * (1 - (item.discount_percent || 0) / 100)) }}
                </span>
              </div>
              <div class="col-span-5 sm:col-span-2">
                <label class="block text-xs text-slate-400 mb-1">Unit Price (৳)</label>
                <input
                  v-model.number="item.unit_price_bdt"
                  @input="recalculate"
                  type="number"
                  min="0"
                  step="0.01"
                  class="w-full border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 rounded-lg px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500/40"
                />
              </div>
              <div class="col-span-3 sm:col-span-1">
                <label class="block text-xs text-slate-400 mb-1">Disc%</label>
                <input
                  v-model.number="item.discount_percent"
                  @input="recalculate"
                  type="number"
                  min="0"
                  max="100"
                  step="0.01"
                  class="w-full border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 rounded-lg px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500/40"
                />
              </div>
              <div class="col-span-3 sm:col-span-2 text-right pt-5">
                <span class="text-sm font-mono font-semibold text-slate-700 dark:text-slate-300">
                  ৳{{ formatNumber(item.quantity * item.unit_price_bdt * (1 - (item.discount_percent || 0) / 100)) }}
                </span>
              </div>
              <div class="col-span-5 sm:col-span-2">
                <label class="block text-xs text-slate-400 mb-1">Unit Price (৳)</label>
                <input
                  v-model.number="item.unit_price_bdt"
                  @input="recalculate"
                  type="number"
                  min="0"
                  step="0.01"
                  class="w-full border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 rounded-lg px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500/40"
                />
              </div>
              <div class="col-span-3 sm:col-span-1">
                <label class="block text-xs text-slate-400 mb-1">Disc%</label>
                <input
                  v-model.number="item.discount_percent"
                  @input="recalculate"
                  type="number"
                  min="0"
                  max="100"
                  step="0.01"
                  class="w-full border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 rounded-lg px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500/40"
                />
              </div>
              <div class="col-span-3 sm:col-span-2 text-right pt-5">
                <span class="text-sm font-mono font-semibold text-slate-700 dark:text-slate-300">
                  ৳{{ formatNumber(item.quantity * item.unit_price_bdt * (1 - (item.discount_percent || 0) / 100)) }}
                </span>
              </div>
              <div class="col-span-5 sm:col-span-2">
                <label class="block text-xs text-slate-400 mb-1">Unit Price (৳)</label>
                <input
                  v-model.number="item.unit_price_bdt"
                  @input="recalculate"
                  type="number"
                  min="0"
                  step="0.01"
                  class="w-full border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 rounded-lg px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500/40"
                />
              </div>
              <div class="col-span-3 sm:col-span-1">
                <label class="block text-xs text-slate-400 mb-1">Disc%</label>
                <input
                  v-model.number="item.discount_percent"
                  @input="recalculate"
                  type="number"
                  min="0"
                  max="100"
                  step="0.01"
                  class="w-full border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 rounded-lg px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500/40"
                />
              </div>
              <div class="col-span-3 sm:col-span-2 text-right pt-5">
                <span class="text-sm font-mono font-semibold text-slate-700 dark:text-slate-300">
                  ৳{{ formatNumber(item.quantity * item.unit_price_bdt * (1 - (item.discount_percent || 0) / 100)) }}
                </span>
              </div>
              <div v-if="!order" class="col-span-12 flex justify-end">
                <button type="button" @click="removeItem(idx)" class="text-xs text-red-500 hover:text-red-700 dark:hover:text-red-400">
                  Remove
                </button>
              </div>
            </div>
          </div>

          <!-- Standalone: product search helper note -->
          <p v-if="!order" class="text-xs text-slate-400 dark:text-slate-500 mt-3">
            Enter product details manually. Items can be edited after creation.
          </p>
        </div>
      </div>

      <!-- Right Column: Totals + Submit -->
      <div class="space-y-5">
        <!-- Adjustments -->
        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-5">
          <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-300 mb-3">Adjustments</h3>
          <div class="space-y-3">
            <div>
              <label class="block text-xs text-slate-500 dark:text-slate-400 mb-1">Discount (৳)</label>
              <input
                v-model.number="form.discount_bdt"
                @input="recalculate"
                type="number"
                min="0"
                step="0.01"
                class="w-full border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500/40"
              />
            </div>
            <div>
              <label class="block text-xs text-slate-500 dark:text-slate-400 mb-1">Delivery Charge (৳)</label>
              <input
                v-model.number="form.delivery_charge_bdt"
                @input="recalculate"
                type="number"
                min="0"
                step="0.01"
                class="w-full border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500/40"
              />
            </div>
          </div>
        </div>

        <!-- Live Totals -->
        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-5">
          <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-300 mb-3">Summary</h3>
          <dl class="space-y-2 text-sm">
            <div class="flex justify-between">
              <dt class="text-slate-500 dark:text-slate-400">Subtotal</dt>
              <dd class="font-mono font-semibold text-slate-800 dark:text-slate-200">৳{{ formatNumber(totals.subtotal) }}</dd>
            </div>
            <div v-if="totals.discount > 0" class="flex justify-between">
              <dt class="text-slate-500 dark:text-slate-400">Discount</dt>
              <dd class="font-mono text-red-500 dark:text-red-400">−৳{{ formatNumber(totals.discount) }}</dd>
            </div>
            <div v-if="totals.delivery > 0" class="flex justify-between">
              <dt class="text-slate-500 dark:text-slate-400">Delivery</dt>
              <dd class="font-mono text-slate-600 dark:text-slate-300">+৳{{ formatNumber(totals.delivery) }}</dd>
            </div>
            <div class="flex justify-between border-t border-slate-100 dark:border-slate-700 pt-2 mt-1">
              <dt class="font-semibold text-slate-700 dark:text-slate-300">Total</dt>
              <dd class="font-mono font-bold text-lg text-slate-900 dark:text-slate-100">৳{{ formatNumber(totals.total) }}</dd>
            </div>
          </dl>
        </div>

        <!-- Submit -->
        <button
          @click="submit"
          :disabled="loading"
          class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold rounded-xl transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
        >
          <LoaderIcon v-if="loading" class="w-4 h-4 animate-spin" />
          <Icon3D v-else name="Save" size="sm" color="text-white" />
          {{ loading ? 'Creating...' : 'Create Invoice' }}
        </button>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { reactive, computed, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { ArrowLeftIcon, PlusCircleIcon, AlertCircleIcon, LoaderIcon } from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'
import ThreeDIcon from '@/Components/UI/ThreeDIcon.vue'
import Icon3D from '@/Components/UI/Icon3D.vue'

const props = defineProps({
  order:       { type: Object, default: null },
  customers:   { type: Array,  default: () => [] },
  salesOrders: { type: Array,  default: null },
})

const loading    = ref(false)
const error      = ref(null)
const fieldErrors = ref({})

const todayISO = () => new Date().toISOString().split('T')[0]

// ─── Build initial items from SO or empty ─────────────────────────────────

function buildItems() {
  if (props.order?.items?.length) {
    return props.order.items.map(item => ({
      product_id:         item.product_id,
      product_variant_id: item.product_variant_id ?? null,
      product_name:       item.product?.name ?? 'Product',
      quantity:           item.quantity,
      unit_price_bdt:     Number(item.unit_price_bdt),
      discount_percent:   Number(item.discount_percent ?? 0),
    }))
  }
  return []
}

const form = reactive({
  sales_order_id:      props.order?.id ?? null,
  customer_id:         props.order?.customer_id ?? '',
  issue_date:          todayISO(),
  due_date:            '',
  notes:               '',
  discount_bdt:        Number(props.order?.discount_bdt ?? 0),
  delivery_charge_bdt: Number(props.order?.delivery_charge_bdt ?? 0),
  items:               buildItems(),
})

// ─── Add / Remove items (standalone mode) ────────────────────────────────

function addItem() {
  form.items.push({
    product_id:         null,
    product_variant_id: null,
    product_name:       '',
    quantity:           1,
    unit_price_bdt:     0,
    discount_percent:   0,
  })
}

function removeItem(idx) {
  form.items.splice(idx, 1)
  recalculate()
}

// ─── Live totals ─────────────────────────────────────────────────────────

const totals = computed(() => {
  const subtotal = form.items.reduce((sum, i) => {
    const gross = i.quantity * i.unit_price_bdt
    const disc  = gross * ((i.discount_percent || 0) / 100)
    return sum + (gross - disc)
  }, 0)
  const discount = Number(form.discount_bdt) || 0
  const delivery = Number(form.delivery_charge_bdt) || 0
  const total    = Math.max(0, subtotal - discount + delivery)
  return { subtotal, discount, delivery, total }
})

function recalculate() {}  // totals are computed — no-op, kept for @input handlers

// ─── Submit ───────────────────────────────────────────────────────────────

async function submit() {
  error.value       = null
  fieldErrors.value = {}
  loading.value     = true

  try {
    const payload = {
      sales_order_id:      form.sales_order_id,
      customer_id:         form.customer_id || (props.order?.customer_id ?? null),
      issue_date:          form.issue_date,
      due_date:            form.due_date || null,
      notes:               form.notes || null,
      discount_bdt:        form.discount_bdt,
      delivery_charge_bdt: form.delivery_charge_bdt,
      items: form.items.map(i => ({
        product_id:         i.product_id,
        product_variant_id: i.product_variant_id ?? null,
        quantity:           i.quantity,
        unit_price_bdt:     i.unit_price_bdt,
        discount_percent:   i.discount_percent ?? 0,
      })),
    }

    const res = await window.axios.post('/api/v1/invoices', payload)
    router.visit(route('invoices.show', res.data.invoice.id))
  } catch (err) {
    if (err.response?.status === 422) {
      fieldErrors.value = err.response.data.errors ?? {}
      error.value       = err.response.data.message ?? 'Validation failed.'
    } else {
      error.value = err.response?.data?.message ?? 'Failed to create invoice.'
    }
  } finally {
    loading.value = false
  }
}

// ─── Helpers ──────────────────────────────────────────────────────────────

function formatNumber(v) {
  if (v === null || v === undefined) return '0'
  return Number(v).toLocaleString('en-BD')
}
</script>
