<template>
  <AppLayout>
    <Head :title="`Landing Cost — ${shipment.shipment_no}`" />

    <div class="mb-6">
      <Link :href="route('shipments.show', shipment.id)" class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-400 text-sm font-medium hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-all shadow-sm group">
        <ArrowLeftIcon class="w-4 h-4 group-hover:-translate-x-0.5 transition-transform" />
        {{ shipment.shipment_no }}
      </Link>
      <div class="flex items-start justify-between">
        <div>
          <h1 class="text-2xl font-semibold text-slate-900 dark:text-slate-100">Landing Cost</h1>
          <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">{{ shipment.shipment_no }} · Full cost allocation breakdown</p>
        </div>
        <button @click="calculate" :disabled="calculating"
          class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
          <RefreshCwIcon v-if="!calculating" class="w-4 h-4" />
          <LoaderIcon v-else class="w-4 h-4 animate-spin" />
          {{ calculating ? 'Calculating...' : 'Recalculate & Save' }}
        </button>
      </div>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
      <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-4">
        <p class="text-xs text-slate-500 dark:text-slate-400 mb-1">Total Purchase Cost</p>
        <p class="text-sm font-bold text-slate-900 dark:text-slate-100 font-mono">৳{{ fmt(totalPurchaseBdt) }}</p>
      </div>
      <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-4">
        <p class="text-xs text-slate-500 dark:text-slate-400 mb-1">Total Shipment Costs</p>
        <p class="text-sm font-bold text-slate-900 dark:text-slate-100 font-mono">৳{{ fmt(totalShipmentCosts) }}</p>
      </div>
      <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-4">
        <p class="text-xs text-slate-500 dark:text-slate-400 mb-1">Total Landing Cost</p>
        <p class="text-lg font-bold text-emerald-700 font-mono">৳{{ fmt(totalLandingCost) }}</p>
      </div>
      <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-4">
        <p class="text-xs text-slate-500 dark:text-slate-400 mb-1">Allocation Method</p>
        <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">{{ allocationMethodLabel }}</p>
      </div>
    </div>

    <!-- Cost Breakdown Summary -->
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 mb-6 overflow-hidden">
      <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center gap-2">
        <ReceiptIcon class="w-4 h-4 text-emerald-600" />
        <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-200">Shipment Cost Summary</h2>
      </div>
      <div class="p-4">
        <div v-if="shipment.costs.length === 0" class="text-center py-6 text-slate-400 text-sm">
          No costs recorded. Add costs on the shipment detail page first.
        </div>
        <div v-else class="grid grid-cols-2 md:grid-cols-4 gap-3">
          <div v-for="cost in shipment.costs" :key="cost.id"
            class="bg-slate-50 dark:bg-slate-700/50 rounded-lg p-3 border border-slate-200 dark:border-slate-700">
            <p class="text-xs font-medium text-slate-500 dark:text-slate-400 mb-1">{{ costTypeLabel(cost.cost_type) }}</p>
            <p class="text-sm font-mono font-semibold text-slate-800 dark:text-slate-200">৳{{ fmt(cost.amount_bdt) }}</p>
            <span v-if="cost.paid_at" class="text-xs text-emerald-600">Paid</span>
            <span v-else class="text-xs text-amber-600">Unpaid</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Allocation Table -->
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
      <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center gap-2">
        <CalculatorIcon class="w-4 h-4 text-emerald-600" />
        <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-200">Per-Product Cost Allocation</h2>
      </div>

      <div v-if="allocations.length === 0 && !calculating" class="text-center py-10 text-slate-400">
        <CalculatorIcon class="w-10 h-10 mx-auto mb-2 text-slate-300" />
        <p class="text-sm">Click "Recalculate & Save" to compute landing costs</p>
        <p class="text-xs text-slate-400 mt-1">Items and shipment costs must be recorded first</p>
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full min-w-[900px]">
          <thead>
            <tr class="bg-emerald-50 dark:bg-emerald-900/20 border-b border-emerald-100 dark:border-emerald-800">
              <th class="text-left text-xs font-medium text-emerald-800 px-4 py-3 sticky left-0 bg-emerald-50 dark:bg-emerald-900/20">Product</th>
              <th class="text-right text-xs font-medium text-emerald-800 px-3 py-3">Qty</th>
              <th class="text-right text-xs font-medium text-emerald-800 px-3 py-3">Purchase (BDT)</th>
              <th class="text-right text-xs font-medium text-emerald-800 px-3 py-3">Freight</th>
              <th class="text-right text-xs font-medium text-emerald-800 px-3 py-3">Duty</th>
              <th class="text-right text-xs font-medium text-emerald-800 px-3 py-3">VAT</th>
              <th class="text-right text-xs font-medium text-emerald-800 px-3 py-3">AIT</th>
              <th class="text-right text-xs font-medium text-emerald-800 px-3 py-3">Labour</th>
              <th class="text-right text-xs font-medium text-emerald-800 px-3 py-3">Transport</th>
              <th class="text-right text-xs font-medium text-emerald-800 px-3 py-3">Other</th>
              <th class="text-right text-xs font-medium text-emerald-800 px-4 py-3 bg-emerald-100 dark:bg-emerald-900/40">Total Landing</th>
              <th class="text-right text-xs font-medium text-emerald-800 px-4 py-3 bg-emerald-100 dark:bg-emerald-900/40">Per Unit</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="alloc in allocations" :key="alloc.po_item_id ?? alloc._sku ?? alloc.product_id"
              class="border-b border-slate-100 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700/50">
              <td class="px-4 py-3 sticky left-0 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/50">
                <p class="text-sm font-medium text-slate-900 dark:text-slate-100">{{ alloc._product_name }}</p>
                <p class="text-xs text-slate-400 dark:text-slate-500 font-mono">{{ alloc._sku }}</p>
              </td>
              <td class="px-3 py-3 text-right text-sm font-mono text-slate-700 dark:text-slate-300">{{ alloc.quantity }}</td>
              <td class="px-3 py-3 text-right text-sm font-mono text-slate-700 dark:text-slate-300">৳{{ fmt2(alloc.purchase_price_bdt) }}</td>
              <td class="px-3 py-3 text-right text-xs font-mono text-slate-600 dark:text-slate-400">৳{{ fmt2(alloc.allocated_freight_bdt) }}</td>
              <td class="px-3 py-3 text-right text-xs font-mono text-slate-600 dark:text-slate-400">৳{{ fmt2(alloc.allocated_customs_bdt) }}</td>
              <td class="px-3 py-3 text-right text-xs font-mono text-slate-600 dark:text-slate-400">৳{{ fmt2(alloc.allocated_vat_bdt) }}</td>
              <td class="px-3 py-3 text-right text-xs font-mono text-slate-600 dark:text-slate-400">৳{{ fmt2(alloc.allocated_ait_bdt) }}</td>
              <td class="px-3 py-3 text-right text-xs font-mono text-slate-600 dark:text-slate-400">৳{{ fmt2(alloc.allocated_labour_bdt) }}</td>
              <td class="px-3 py-3 text-right text-xs font-mono text-slate-600 dark:text-slate-400">৳{{ fmt2(alloc.allocated_transport_bdt) }}</td>
              <td class="px-3 py-3 text-right text-xs font-mono text-slate-600 dark:text-slate-400">৳{{ fmt2(alloc.allocated_other_bdt) }}</td>
              <td class="px-4 py-3 text-right text-sm font-mono font-bold text-emerald-700 bg-emerald-50 dark:bg-emerald-900/20">
                ৳{{ fmt2(alloc.total_landing_cost_bdt) }}
              </td>
              <td class="px-4 py-3 text-right text-sm font-mono font-bold text-slate-900 dark:text-slate-100 bg-emerald-50 dark:bg-emerald-900/20">
                ৳{{ fmt2(alloc.landing_cost_per_unit_bdt) }}
              </td>
            </tr>
          </tbody>
          <tfoot v-if="allocations.length">
            <tr class="border-t-2 border-emerald-200 dark:border-emerald-800 bg-emerald-50 dark:bg-emerald-900/20">
              <td colspan="2" class="px-4 py-3 text-sm font-semibold text-slate-700 dark:text-slate-300">Totals</td>
              <td class="px-3 py-3 text-right text-sm font-mono font-bold dark:text-slate-200">৳{{ fmt(totalPurchaseBdt) }}</td>
              <td colspan="7"></td>
              <td class="px-4 py-3 text-right text-sm font-mono font-bold text-emerald-800">৳{{ fmt(totalLandingCost) }}</td>
              <td class="px-4 py-3"></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>

  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import { CalculatorIcon, RefreshCwIcon, LoaderIcon, ReceiptIcon, ArrowLeftIcon } from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'
import { useToast } from '@/Composables/useToast'

const props = defineProps({
  shipment: { type: Object, required: true },
})

const { success, error: showError } = useToast()

// Use saved allocations if available, else empty
const allocations = ref(
  (props.shipment.landing_cost_allocations ?? []).map(a => ({
    ...a,
    _product_name: a.product?.name,
    _sku:          a.product?.sku,
  }))
)

const calculating = ref(false)

async function calculate() {
  calculating.value = true
  try {
    const res = await window.axios.post(
      `/api/v1/shipments/${props.shipment.id}/calculate-landing-cost`
    )
    // Get fresh data with product names
    const res2 = await window.axios.get(
      `/api/v1/shipments/${props.shipment.id}/landing-cost`
    )
    allocations.value = (res2.data.allocations ?? []).map(a => a)
    success('Landing cost calculated and saved!')
  } catch (err) {
    showError(err.response?.data?.message || 'Calculation failed.')
  } finally {
    calculating.value = false
  }
}

// ── Computed totals ───────────────────────────────────────────────
const totalPurchaseBdt = computed(() =>
  allocations.value.reduce((s, a) => s + Number(a.purchase_price_bdt || 0), 0)
)
const totalShipmentCosts = computed(() =>
  (props.shipment.costs ?? []).reduce((s, c) => s + Number(c.amount_bdt || 0), 0)
)
const totalLandingCost = computed(() =>
  allocations.value.reduce((s, a) => s + Number(a.total_landing_cost_bdt || 0), 0)
)

const allocationMethodMap = {
  weight: 'By Weight (kg)', volume: 'By Volume (CBM)',
  value: 'By Purchase Value', quantity: 'By Quantity', manual: 'Manual',
}
const allocationMethodLabel = computed(
  () => allocationMethodMap[props.shipment.cost_allocation_method] ?? 'By Weight (kg)'
)

// ── Cost type labels ─────────────────────────────────────────────
const costTypeMap = {
  freight: 'Freight', customs_duty: 'Duty', vat: 'VAT', ait: 'AIT',
  labour: 'Labour', transport: 'Transport', customs_fee: 'Customs Fee',
  demurrage: 'Demurrage', other: 'Other',
}
function costTypeLabel(t) { return costTypeMap[t] || t }

// ── Format helpers ────────────────────────────────────────────────
function fmt(n)  { return Number(n || 0).toLocaleString('en-BD', { minimumFractionDigits: 2 }) }
function fmt2(n) { return Number(n || 0).toLocaleString('en-BD', { minimumFractionDigits: 2 }) }
</script>
