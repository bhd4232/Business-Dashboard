<template>
  <AppLayout>
    <Head :title="`Edit — ${product.name}`" />

    <div class="mb-6 flex items-center gap-4">
      <BackButton label="Back" @click="goBack" />
      <div>
        <h1 class="text-2xl font-semibold text-slate-900 dark:text-slate-100">Edit Product</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5 font-mono">{{ product.sku }}</p>
      </div>
    </div>

    <form @submit.prevent="submit">
      <!-- Basic Info -->
      <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 mb-4">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center gap-2">
          <PackageIcon class="w-4 h-4 text-amber-600" />
          <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-200">Product Information</h2>
        </div>
        <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">

          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Product Name (English) <span class="text-red-500">*</span></label>
            <input v-model="form.name" type="text"
              class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:focus:ring-primary-900/30 px-3 py-2 text-sm focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100"
              :class="{ 'border-red-400': errors.name }" />
            <p v-if="errors.name" class="mt-1 text-xs text-red-600">{{ errors.name }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Product Name (Chinese)</label>
            <input v-model="form.name_chinese" type="text"
              class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:focus:ring-primary-900/30 px-3 py-2 text-sm focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100" />
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Category <span class="text-red-500">*</span></label>
            <CategoryCombobox
              v-model="form.category_id"
              :categories="categoryList"
              :has-error="!!errors.category_id"
              @category-created="onCategoryCreated"
            />
            <p v-if="errors.category_id" class="mt-1 text-xs text-red-600">{{ errors.category_id }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Unit <span class="text-red-500">*</span></label>
            <select v-model="form.unit"
              class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 px-3 py-2 text-sm focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 bg-white">
              <option value="piece">Piece</option>
              <option value="kg">Kg</option>
              <option value="meter">Meter</option>
              <option value="box">Box</option>
              <option value="carton">Carton</option>
              <option value="dozen">Dozen</option>
            </select>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">SKU</label>
            <input v-model="form.sku" type="text"
              class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:focus:ring-primary-900/30 px-3 py-2 text-sm focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 font-mono" />
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Min Stock Alert</label>
            <input v-model.number="form.min_stock_alert" type="number" min="0"
              class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:focus:ring-primary-900/30 px-3 py-2 text-sm focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100" />
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Weight (kg)</label>
            <input v-model.number="form.weight_kg" type="number" min="0" step="0.001"
              class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:focus:ring-primary-900/30 px-3 py-2 text-sm focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 font-mono" />
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Volume (cm³)</label>
            <input v-model.number="form.volume_cm3" type="number" min="0" step="0.001"
              class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:focus:ring-primary-900/30 px-3 py-2 text-sm focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 font-mono" />
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Status</label>
            <label class="flex items-center gap-3 cursor-pointer mt-1">
              <div class="relative">
                <input type="checkbox" v-model="form.is_active" class="sr-only" />
                <div :class="form.is_active ? 'bg-indigo-600' : 'bg-slate-300'"
                  class="w-10 h-6 rounded-full transition-colors"></div>
                <div :class="form.is_active ? 'translate-x-4' : 'translate-x-0'"
                  class="absolute top-1 left-1 w-4 h-4 bg-white rounded-full shadow transition-transform"></div>
              </div>
              <span class="text-sm text-slate-700 dark:text-slate-300">{{ form.is_active ? 'Active' : 'Inactive' }}</span>
            </label>
          </div>

          <div class="md:col-span-2">
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Description</label>
            <textarea v-model="form.description" rows="3"
              class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:focus:ring-primary-900/30 px-3 py-2 text-sm focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100" />
          </div>
        </div>
      </div>

      <!-- Pricing -->
      <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 mb-4">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center gap-2">
          <TagIcon class="w-4 h-4 text-amber-600" />
          <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-200">Pricing</h2>
        </div>
        <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">

          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Regular Price (BDT)</label>
            <div class="relative">
              <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400 dark:text-slate-500 text-sm font-medium pointer-events-none">৳</span>
              <input v-model.number="form.regular_price" type="number" min="0" step="0.01" placeholder="0.00"
                class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:placeholder:text-slate-500 dark:focus:ring-primary-900/30 pl-7 pr-3 py-2 text-sm focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 font-mono"
                :class="{ 'border-red-400': errors.regular_price }" />
            </div>
            <p v-if="errors.regular_price" class="mt-1 text-xs text-red-600">{{ errors.regular_price }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Selling Price (BDT)</label>
            <div class="relative">
              <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400 dark:text-slate-500 text-sm font-medium pointer-events-none">৳</span>
              <input v-model.number="form.selling_price" type="number" min="0" step="0.01" placeholder="0.00"
                class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:placeholder:text-slate-500 dark:focus:ring-primary-900/30 pl-7 pr-3 py-2 text-sm focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 font-mono"
                :class="{ 'border-red-400': errors.selling_price }" />
            </div>
            <p v-if="errors.selling_price" class="mt-1 text-xs text-red-600">{{ errors.selling_price }}</p>
          </div>

        </div>
      </div>

      <!-- Variants Section -->
      <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 mb-6">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
          <div class="flex items-center gap-2">
            <LayersIcon class="w-4 h-4 text-amber-600" />
            <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-200">Variants</h2>
          </div>
          <div class="flex items-center gap-3">
            <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-400 cursor-pointer">
              <input type="checkbox" v-model="hasVariants" class="rounded border-slate-300 text-indigo-600" />
              Has Variants
            </label>
            <button v-if="hasVariants" type="button" @click="addVariant"
              class="text-sm text-indigo-600 hover:text-indigo-700 dark:text-primary-400 dark:hover:text-primary-300 font-medium flex items-center gap-1">
              <PlusIcon class="w-3 h-3" /> Add Variant
            </button>
          </div>
        </div>
        <div v-if="hasVariants" class="p-6">
          <div v-if="form.variants.length === 0" class="text-center py-4 text-slate-400 text-sm">
            Add variants (e.g.: Red - Large, Blue - Small)
          </div>
          <div v-for="(variant, idx) in form.variants" :key="idx"
            class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-3 p-4 bg-slate-50 dark:bg-slate-700/50 rounded-lg relative">
            <button type="button" @click="removeVariant(idx)"
              class="absolute top-2 right-2 text-slate-400 hover:text-red-500 transition-colors">
              <XIcon class="w-4 h-4" />
            </button>
            <div>
              <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Variant Name *</label>
              <input v-model="variant.variant_name" type="text"
                class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 px-3 py-1.5 text-sm focus:outline-none focus:border-indigo-500" />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">SKU</label>
              <input v-model="variant.sku" type="text"
                class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 px-3 py-1.5 text-sm focus:outline-none focus:border-indigo-500 font-mono" />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Weight (kg)</label>
              <input v-model.number="variant.weight_kg" type="number" min="0" step="0.001"
                class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 px-3 py-1.5 text-sm focus:outline-none focus:border-indigo-500 font-mono" />
            </div>
          </div>
        </div>
      </div>

      <!-- Sticky Submit -->
      <div class="sticky bottom-0 bg-white dark:bg-slate-800 border-t border-slate-200 dark:border-slate-700 shadow-lg -mx-6 px-6 py-4 flex items-center justify-end gap-3">
        <button type="button" @click="goBack"
          class="px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 border border-slate-300 dark:border-slate-600 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
          Cancel
        </button>
        <button type="submit" :disabled="saving"
          class="px-6 py-2 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white text-sm font-medium rounded-lg flex items-center gap-2 transition-colors">
          <LoaderIcon v-if="saving" class="w-4 h-4 animate-spin" />
          Save Changes
        </button>
      </div>
    </form>

  </AppLayout>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import { PackageIcon, LayersIcon, PlusIcon, XIcon, LoaderIcon, TagIcon } from 'lucide-vue-next'
import AppLayout        from '@/Layouts/AppLayout.vue'
import BackButton       from '@/Components/UI/BackButton.vue'
import CategoryCombobox from '@/Components/UI/CategoryCombobox.vue'
import { useToast } from '@/Composables/useToast'

const props = defineProps({
  product:    { type: Object, required: true },
  categories: { type: Array,  default: () => [] },
})

const { success, error: showError } = useToast()
const saving      = ref(false)
const errors      = ref({})
const hasVariants = ref(props.product.has_variants ?? false)

const categoryList = ref([...props.categories])
function onCategoryCreated(newCat) {
  categoryList.value.push({ ...newCat, children: [] })
}

const form = reactive({
  name:             props.product.name            ?? '',
  name_chinese:     props.product.name_chinese    ?? '',
  category_id:      props.product.category_id     ?? null,
  unit:             props.product.unit            ?? 'piece',
  sku:              props.product.sku             ?? '',
  min_stock_alert:  props.product.min_stock_alert ?? 0,
  regular_price:    props.product.regular_price   ?? null,
  selling_price:    props.product.selling_price   ?? null,
  weight_kg:        props.product.weight_kg       ?? null,
  volume_cm3:       props.product.volume_cm3      ?? null,
  description:      props.product.description     ?? '',
  is_active:        props.product.is_active       ?? true,
  variants:         (props.product.variants ?? []).map(v => ({
    id:           v.id,
    variant_name: v.variant_name,
    sku:          v.sku         ?? '',
    weight_kg:    v.weight_kg   ?? null,
  })),
})

function goBack() {
  router.visit(route('products.show', props.product.id))
}

function addVariant() {
  form.variants.push({ variant_name: '', sku: '', weight_kg: null })
}

function removeVariant(idx) {
  form.variants.splice(idx, 1)
}

async function submit() {
  saving.value = true
  errors.value = {}

  const payload = { ...form, has_variants: hasVariants.value }
  if (!hasVariants.value) payload.variants = []

  try {
    await window.axios.put(`/api/v1/products/${props.product.id}`, payload)
    success(`"${form.name}" updated successfully!`)
    router.visit(route('products.show', props.product.id))
  } catch (err) {
    if (err.response?.status === 422) {
      errors.value = err.response.data.errors || {}
      const firstMsg = Object.values(errors.value)[0]?.[0]
      if (firstMsg) showError(firstMsg)
    } else {
      showError(err.response?.data?.message || 'Failed to update product.')
    }
    saving.value = false
  }
}
</script>
