<template>
  <AppLayout>
    <Head title="New Stock Transfer" />

    <div class="mb-6">
      <BackButton label="Stock Transfers" to="stock-transfers.index" />
      <h1 class="text-2xl font-semibold text-slate-900 dark:text-slate-100">New Stock Transfer</h1>
    </div>

    <form @submit.prevent="submit">
      <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 mb-4">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center gap-2">
          <ArrowRightLeftIcon class="w-4 h-4 text-amber-600" />
          <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-200">Warehouse Selection</h2>
        </div>
        <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">From Warehouse <span class="text-red-500">*</span></label>
            <select v-model="form.from_warehouse_id"
              class="w-full rounded-lg border border-slate-300 dark:border-slate-600 px-3 py-2 text-sm focus:outline-none focus:border-indigo-500 bg-white dark:bg-slate-800 dark:text-slate-100"
              :class="{ 'border-red-400': errors.from_warehouse_id }">
              <option value="">Select warehouse</option>
              <option v-for="w in warehouses" :key="w.id" :value="w.id"
                :disabled="w.id === form.to_warehouse_id">{{ w.name }} ({{ w.code }})</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">To Warehouse <span class="text-red-500">*</span></label>
            <select v-model="form.to_warehouse_id"
              class="w-full rounded-lg border border-slate-300 dark:border-slate-600 px-3 py-2 text-sm focus:outline-none focus:border-indigo-500 bg-white dark:bg-slate-800 dark:text-slate-100"
              :class="{ 'border-red-400': errors.to_warehouse_id }">
              <option value="">Select warehouse</option>
              <option v-for="w in warehouses" :key="w.id" :value="w.id"
                :disabled="w.id === form.from_warehouse_id">{{ w.name }} ({{ w.code }})</option>
            </select>
          </div>
          <div class="md:col-span-2">
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Notes</label>
            <input v-model="form.notes" type="text"
              class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 px-3 py-2 text-sm focus:outline-none focus:border-indigo-500 dark:focus:ring-primary-900/30" />
          </div>
        </div>
      </div>

      <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 mb-6">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
          <div class="flex items-center gap-2">
            <PackageIcon class="w-4 h-4 text-amber-600" />
            <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-200">Items</h2>
          </div>
          <button type="button" @click="addItem"
            class="text-sm text-indigo-600 dark:text-primary-400 hover:text-indigo-700 dark:hover:text-primary-300 font-medium flex items-center gap-1">
            <PlusIcon class="w-3 h-3" /> Add Item
          </button>
        </div>
        <div class="p-6">
          <div v-for="(item, idx) in form.items" :key="idx"
            class="grid grid-cols-3 gap-4 mb-3 p-3 bg-slate-50 dark:bg-slate-700/50 rounded-lg relative">
            <button type="button" @click="removeItem(idx)"
              class="absolute top-2 right-2 text-slate-400 hover:text-red-500">
              <XIcon class="w-4 h-4" />
            </button>
            <div class="col-span-2">
              <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Product ID</label>
              <input v-model="item.product_id" type="number" placeholder="Product ID"
                class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:placeholder:text-slate-500 px-3 py-1.5 text-sm focus:outline-none focus:border-indigo-500 font-mono" />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Quantity *</label>
              <input v-model.number="item.quantity" type="number" min="1"
                class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 px-3 py-1.5 text-sm focus:outline-none focus:border-indigo-500 font-mono" />
            </div>
          </div>
          <div v-if="form.items.length === 0" class="text-center py-6 text-slate-400 text-sm border-2 border-dashed border-slate-200 dark:border-slate-700 rounded-lg">
            Add items to transfer
          </div>
        </div>
      </div>

      <div class="sticky bottom-0 bg-white dark:bg-slate-800 border-t border-slate-200 dark:border-slate-700 shadow-lg -mx-6 px-6 py-4 flex items-center justify-end gap-3">
        <Link :href="route('stock-transfers.index')"
          class="px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 border border-slate-300 dark:border-slate-600 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700/50">Cancel</Link>
        <button type="submit" :disabled="saving"
          class="px-6 py-2 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white text-sm font-medium rounded-lg flex items-center gap-2">
          <LoaderIcon v-if="saving" class="w-4 h-4 animate-spin" />
          Create Transfer
        </button>
      </div>
    </form>

  </AppLayout>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { ChevronLeftIcon, ArrowRightLeftIcon, PackageIcon, PlusIcon, XIcon, LoaderIcon } from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'
import BackButton from '@/Components/UI/BackButton.vue'
import { useToast } from '@/Composables/useToast'

const props = defineProps({ warehouses: { type: Array, default: () => [] } })

const { success, error: showError } = useToast()
const saving = ref(false)
const errors = ref({})

const form = reactive({ from_warehouse_id: '', to_warehouse_id: '', notes: '', items: [] })

function addItem() {
  form.items.push({ product_id: null, product_variant_id: null, quantity: 1 })
}

function removeItem(idx) { form.items.splice(idx, 1) }

async function submit() {
  saving.value = true; errors.value = {}
  try {
    await window.axios.post('/api/v1/stock-transfers', form)
    success('Stock transfer created successfully!')
    router.visit(route('stock-transfers.index'))
  } catch (err) {
    if (err.response?.status === 422) {
      errors.value = err.response.data.errors || {}
      const firstMsg = Object.values(errors.value)[0]?.[0]
      if (firstMsg) showError(firstMsg)
    } else {
      showError(err.response?.data?.message || 'Failed to create transfer.')
    }
    saving.value = false
  }
}
</script>
