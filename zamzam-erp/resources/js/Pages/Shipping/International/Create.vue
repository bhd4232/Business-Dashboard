<template>
  <AppLayout>
    <Head title="New Shipment" />

    <div class="mb-6">
      <BackButton label="Shipments" to="shipments.index" />
      <h1 class="text-2xl font-semibold text-slate-900 dark:text-slate-100">New International Shipment</h1>
      <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">China → Bangladesh</p>
    </div>

    <form @submit.prevent="submit">

      <!-- Basic Info -->
      <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 mb-4">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center gap-2">
          <ShipIcon class="w-4 h-4 text-cyan-600" />
          <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-200">Shipment Information</h2>
        </div>
        <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">

          <!-- Shipping Type -->
          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Shipping Type <span class="text-red-500">*</span></label>
            <div class="flex gap-2 flex-wrap">
              <button v-for="t in shippingTypes" :key="t.value" type="button"
                @click="form.shipping_type = t.value"
                :class="['px-3 py-1.5 text-sm rounded-lg border transition-colors',
                  form.shipping_type === t.value
                    ? 'bg-cyan-600 border-cyan-600 text-white'
                    : 'border-slate-300 dark:border-slate-600 text-slate-600 dark:text-slate-400 hover:border-cyan-400 hover:text-cyan-700']">
                {{ t.label }}
              </button>
            </div>
            <p v-if="errors.shipping_type" class="mt-1 text-xs text-red-600">{{ errors.shipping_type }}</p>
          </div>

          <!-- Linked PO -->
          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Linked Purchase Order</label>
            <select v-model="form.purchase_order_id"
              class="w-full rounded-lg border border-slate-300 dark:border-slate-600 px-3 py-2 text-sm focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 dark:focus:ring-primary-900/30 bg-white dark:bg-slate-800 dark:text-slate-100">
              <option value="">None (standalone shipment)</option>
              <option v-for="po in purchaseOrders" :key="po.id" :value="po.id">
                {{ po.po_number }} — {{ po.supplier?.name_english }}
              </option>
            </select>
          </div>

          <!-- Carrier -->
          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Carrier / Forwarder</label>
            <input v-model="form.carrier" type="text" placeholder="e.g. COSCO, Maersk, DHL"
              class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:placeholder:text-slate-500 px-3 py-2 text-sm focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 dark:focus:ring-primary-900/30" />
          </div>

          <!-- Container Type (sea only) -->
          <div v-if="form.shipping_type === 'sea'">
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Container Type</label>
            <select v-model="form.container_type"
              class="w-full rounded-lg border border-slate-300 dark:border-slate-600 px-3 py-2 text-sm focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 dark:focus:ring-primary-900/30 bg-white dark:bg-slate-800 dark:text-slate-100">
              <option value="">Select</option>
              <option value="20ft">20ft FCL</option>
              <option value="40ft">40ft FCL</option>
              <option value="40HC">40ft HC FCL</option>
              <option value="LCL">LCL</option>
            </select>
          </div>

          <!-- BL / AWB Number -->
          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
              {{ form.shipping_type === 'air' ? 'AWB Number' : 'Bill of Lading' }}
            </label>
            <input v-model="form.bl_number" type="text" placeholder="Enter number..."
              class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:placeholder:text-slate-500 px-3 py-2 text-sm focus:outline-none focus:border-indigo-500 font-mono" />
          </div>

          <!-- Container No -->
          <div v-if="form.shipping_type === 'sea'">
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Container No.</label>
            <input v-model="form.container_no" type="text" placeholder="ABCD1234567"
              class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:placeholder:text-slate-500 px-3 py-2 text-sm focus:outline-none focus:border-indigo-500 font-mono" />
          </div>

        </div>
      </div>

      <!-- Route & Dates -->
      <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 mb-4">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center gap-2">
          <MapPinIcon class="w-4 h-4 text-cyan-600" />
          <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-200">Route & Schedule</h2>
        </div>
        <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">

          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Port of Loading</label>
            <input v-model="form.port_loading" type="text" placeholder="e.g. Ningbo, Shenzhen, Guangzhou"
              class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:placeholder:text-slate-500 px-3 py-2 text-sm focus:outline-none focus:border-indigo-500" />
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Port of Discharge</label>
            <input v-model="form.port_discharge" type="text" placeholder="e.g. Chittagong"
              class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:placeholder:text-slate-500 px-3 py-2 text-sm focus:outline-none focus:border-indigo-500" />
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">ETD (Departure)</label>
            <input v-model="form.etd" type="date"
              class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 px-3 py-2 text-sm focus:outline-none focus:border-indigo-500" />
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">ETA (Arrival)</label>
            <input v-model="form.eta" type="date"
              class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 px-3 py-2 text-sm focus:outline-none focus:border-indigo-500" />
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Customs Agent (C&F)</label>
            <input v-model="form.customs_agent" type="text" placeholder="Agent name"
              class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:placeholder:text-slate-500 px-3 py-2 text-sm focus:outline-none focus:border-indigo-500" />
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Tracking URL</label>
            <input v-model="form.tracking_url" type="url" placeholder="https://..."
              class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:placeholder:text-slate-500 px-3 py-2 text-sm focus:outline-none focus:border-indigo-500" />
          </div>

        </div>
      </div>

      <!-- Cost Allocation + Notes -->
      <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 mb-4">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center gap-2">
          <CalculatorIcon class="w-4 h-4 text-cyan-600" />
          <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-200">Cost Allocation & Notes</h2>
        </div>
        <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">

          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Landing Cost Allocation Method</label>
            <select v-model="form.cost_allocation_method"
              class="w-full rounded-lg border border-slate-300 dark:border-slate-600 px-3 py-2 text-sm focus:outline-none focus:border-indigo-500 bg-white dark:bg-slate-800 dark:text-slate-100">
              <option v-for="m in allocationMethods" :key="m.value" :value="m.value">{{ m.label }}</option>
            </select>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Notes</label>
            <textarea v-model="form.notes" rows="2" placeholder="Internal notes..."
              class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:placeholder:text-slate-500 px-3 py-2 text-sm focus:outline-none focus:border-indigo-500 resize-none dark:focus:ring-primary-900/30"></textarea>
          </div>

        </div>
      </div>

      <!-- Sticky Submit -->
      <div class="sticky bottom-0 bg-white dark:bg-slate-800 border-t border-slate-200 dark:border-slate-700 shadow-lg -mx-6 px-6 py-4 flex items-center justify-end gap-3">
        <Link :href="route('shipments.index')"
          class="px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-slate-100 border border-slate-300 dark:border-slate-600 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
          Cancel
        </Link>
        <button type="submit" :disabled="saving"
          class="px-6 py-2 bg-cyan-600 hover:bg-cyan-700 disabled:opacity-50 text-white text-sm font-medium rounded-lg transition-colors flex items-center gap-2">
          <LoaderIcon v-if="saving" class="w-4 h-4 animate-spin" />
          Create Shipment
        </button>
      </div>

    </form>

  </AppLayout>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { ShipIcon, MapPinIcon, CalculatorIcon, LoaderIcon } from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'
import BackButton from '@/Components/UI/BackButton.vue'
import { useToast } from '@/Composables/useToast'

const props = defineProps({
  purchaseOrders:    { type: Array, default: () => [] },
  shippingTypes:     { type: Array, default: () => [] },
  allocationMethods: { type: Array, default: () => [] },
})

const { success, error: showError } = useToast()
const saving = ref(false)
const errors = ref({})

const form = reactive({
  purchase_order_id:      '',
  shipping_type:          'sea',
  carrier:                '',
  container_no:           '',
  container_type:         '',
  bl_number:              '',
  port_loading:           '',
  port_discharge:         'Chittagong',
  etd:                    '',
  eta:                    '',
  customs_agent:          '',
  tracking_url:           '',
  cost_allocation_method: 'weight',
  notes:                  '',
})

async function submit() {
  saving.value = true
  errors.value = {}

  try {
    const payload = { ...form }
    if (!payload.purchase_order_id) delete payload.purchase_order_id

    const res = await window.axios.post('/api/v1/shipments', payload)
    success('Shipment created successfully!')
    router.visit(route('shipments.show', res.data.id))
  } catch (err) {
    if (err.response?.status === 422) {
      errors.value = err.response.data.errors || {}
      const firstMsg = Object.values(errors.value)[0]?.[0]
      if (firstMsg) showError(firstMsg)
    } else {
      showError(err.response?.data?.message || 'Failed to create shipment.')
    }
    saving.value = false
  }
}
</script>
