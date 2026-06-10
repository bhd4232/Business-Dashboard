<template>
  <AppLayout>
    <Head title="Customers" />

    <!-- Page Header -->
    <div class="flex items-center justify-between mb-6">
      <div class="flex items-center gap-3">
        <ThreeDIcon name="customers" size="lg" />
        <div>
          <h1 class="text-2xl font-semibold text-slate-900 dark:text-slate-100">Customers</h1>
          <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Manage wholesale & retail customers</p>
        </div>
      </div>
      <div class="flex items-center gap-2">
        <Link :href="route('customer-tags.index')"
          class="inline-flex items-center gap-2 border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50 text-sm font-medium px-4 py-2 rounded-lg transition-colors">
          <TagIcon class="w-4 h-4" /> Tags
        </Link>
        <button @click="toggleTrash"
          :class="showTrash ? 'bg-red-50 border-red-300 text-red-700 dark:bg-red-900/20 dark:border-red-800 dark:text-red-300' : 'border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50'"
          class="inline-flex items-center gap-2 border text-sm font-medium px-4 py-2 rounded-lg transition-colors">
          <TrashIcon class="w-4 h-4" />
          Trash
          <span v-if="trashedCount" class="bg-red-500 text-white text-xs rounded-full px-1.5 py-0.5 ml-0.5">{{ trashedCount }}</span>
        </button>
        <Link :href="route('customers.create')"
          class="inline-flex items-center gap-2 bg-primary-600 hover:bg-primary-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
          <PlusIcon class="w-4 h-4" /> New Customer
        </Link>
      </div>
    </div>

    <!-- Filters -->
    <div v-if="!showTrash" class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-4 mb-4">
      <div class="flex items-center gap-3 flex-wrap">
        <div class="relative flex-1 min-w-48">
          <SearchIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 dark:text-slate-500" />
          <input v-model="filters.search" @input="debouncedSearch" type="text" placeholder="Search name, phone, ID..."
            class="w-full pl-9 pr-4 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-100 dark:focus:ring-primary-900/30 bg-white dark:bg-slate-800 dark:text-slate-100 dark:placeholder:text-slate-500" />
        </div>
        <select v-model="filters.type" @change="applyFilters"
          class="text-sm border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 bg-white dark:bg-slate-800 dark:text-slate-100 focus:outline-none focus:border-primary-500">
          <option value="">All Types</option>
          <option value="wholesale">Wholesale</option>
          <option value="retail">Retail</option>
        </select>
        <select v-model="filters.tag_id" @change="applyFilters"
          class="text-sm border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 bg-white dark:bg-slate-800 dark:text-slate-100 focus:outline-none focus:border-primary-500">
          <option value="">All Tags</option>
          <option v-for="tag in tags" :key="tag.id" :value="tag.id">{{ tag.name }}</option>
        </select>
        <select v-model="filters.price_tier_id" @change="applyFilters"
          class="text-sm border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 bg-white dark:bg-slate-800 dark:text-slate-100 focus:outline-none focus:border-primary-500">
          <option value="">All Tiers</option>
          <option v-for="tier in priceTiers" :key="tier.id" :value="tier.id">{{ tier.name }}</option>
        </select>
        <button @click="resetFilters" class="text-sm text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
          Reset
        </button>
      </div>
    </div>

    <!-- Customers Table -->
    <div v-if="!showTrash" class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
      <table class="w-full">
        <thead>
          <tr class="bg-slate-50 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700">
            <th class="text-left text-sm font-medium text-slate-700 dark:text-slate-300 px-6 py-3">Customer</th>
            <th class="text-left text-sm font-medium text-slate-700 dark:text-slate-300 px-6 py-3">Phone</th>
            <th class="text-left text-sm font-medium text-slate-700 dark:text-slate-300 px-6 py-3">Type</th>
            <th class="text-left text-sm font-medium text-slate-700 dark:text-slate-300 px-6 py-3">Tags</th>
            <th class="text-left text-sm font-medium text-slate-700 dark:text-slate-300 px-6 py-3">Credit</th>
            <th class="text-left text-sm font-medium text-slate-700 dark:text-slate-300 px-6 py-3">Status</th>
            <th class="text-right text-sm font-medium text-slate-700 dark:text-slate-300 px-6 py-3">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="customers.data.length === 0">
            <td colspan="7" class="text-center py-16">
              <ThreeDIcon name="customers" size="xl" class="mx-auto mb-3 opacity-30" />
              <p class="text-sm text-slate-400 dark:text-slate-500">No customers found</p>
              <Link :href="route('customers.create')"
                class="mt-3 inline-flex items-center gap-1 text-sm text-primary-600 dark:text-primary-400 hover:underline">
                <PlusIcon class="w-4 h-4" /> Add your first customer
              </Link>
            </td>
          </tr>
          <tr v-for="customer in customers.data" :key="customer.id"
            class="border-b border-slate-100 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors cursor-pointer"
            @click="$inertia.visit(route('customers.show', customer.id))">
            <td class="px-6 py-4" @click.stop>
              <Link :href="route('customers.show', customer.id)">
                <p class="text-sm font-semibold text-slate-900 dark:text-slate-100 hover:text-primary-600 dark:hover:text-primary-400">
                  {{ customer.name }}
                </p>
                <p v-if="customer.business_name" class="text-xs text-slate-500 dark:text-slate-400">{{ customer.business_name }}</p>
                <p class="text-xs text-slate-400 dark:text-slate-500 font-mono">{{ customer.customer_code }}</p>
              </Link>
            </td>
            <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-300 font-mono">{{ customer.phone }}</td>
            <td class="px-6 py-4">
              <span :class="customer.type === 'wholesale'
                  ? 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300'
                  : 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300'"
                class="rounded-full px-2.5 py-0.5 text-xs font-medium capitalize">
                {{ customer.type }}
              </span>
            </td>
            <td class="px-6 py-4">
              <div class="flex flex-wrap gap-1">
                <span v-for="tag in customer.tags" :key="tag.id"
                  class="rounded-full px-2 py-0.5 text-xs font-medium"
                  :style="{ backgroundColor: tag.color + '22', color: tag.color }">
                  {{ tag.name }}
                </span>
              </div>
            </td>
            <td class="px-6 py-4">
              <div>
                <p class="text-sm font-medium text-slate-900 dark:text-slate-100">৳{{ formatNumber(customer.outstanding_balance_bdt) }}</p>
                <p class="text-xs text-slate-400 dark:text-slate-500">/ ৳{{ formatNumber(customer.credit_limit_bdt) }}</p>
              </div>
            </td>
            <td class="px-6 py-4">
              <span :class="customer.is_active
                  ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300'
                  : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300'"
                class="rounded-full px-2.5 py-0.5 text-xs font-medium">
                {{ customer.is_active ? 'Active' : 'Inactive' }}
              </span>
            </td>
            <td class="px-6 py-4 text-right" @click.stop>
              <div class="flex items-center justify-end gap-2">
                <Link :href="route('customers.show', customer.id)" class="text-sm text-primary-600 dark:text-primary-400 hover:text-primary-700 font-medium">View</Link>
                <Link :href="route('customers.edit', customer.id)" class="text-sm text-slate-600 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200">Edit</Link>
                <button @click="confirmDelete(customer)"
                  class="p-1.5 text-red-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors">
                  <TrashIcon class="w-3.5 h-3.5" />
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Pagination -->
      <div v-if="customers.last_page > 1" class="flex items-center justify-between px-6 py-3 border-t border-slate-200 dark:border-slate-700">
        <p class="text-sm text-slate-500 dark:text-slate-400">
          {{ customers.from }}–{{ customers.to }} of {{ customers.total }} customers
        </p>
        <div class="flex gap-1">
          <Link v-for="link in customers.links" :key="link.label" :href="link.url || '#'"
            :class="['px-3 py-1 text-sm rounded-lg transition-colors',
              link.active ? 'bg-primary-600 text-white' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700',
              !link.url ? 'opacity-40 pointer-events-none' : '']"
            v-html="link.label" />
        </div>
      </div>
    </div>

    <!-- Trash Section -->
    <div v-if="showTrash">
      <div class="flex items-center gap-3 mb-4">
        <button @click="toggleTrash" class="inline-flex items-center gap-1.5 text-sm text-slate-600 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200 border border-slate-300 dark:border-slate-600 px-3 py-1.5 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700/50">
          <ArrowLeftIcon class="w-4 h-4" /> Back
        </button>
        <h2 class="text-lg font-semibold text-slate-800 dark:text-slate-200">Trash</h2>
        <span class="text-sm text-slate-500 dark:text-slate-400">Deleted customers</span>
      </div>
      <div v-if="trashedLoading" class="text-center py-8 text-slate-400 dark:text-slate-500 text-sm">Loading...</div>
      <div v-else-if="trashedItems.length === 0" class="bg-white dark:bg-slate-800 rounded-xl border border-dashed border-slate-300 dark:border-slate-600 p-10 text-center text-slate-400 dark:text-slate-500">
        <p class="text-sm">Trash is empty</p>
      </div>
      <div v-else class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
        <table class="w-full">
          <thead>
            <tr class="bg-red-50 dark:bg-red-900/20 border-b border-red-100 dark:border-red-800">
              <th class="text-left text-sm font-medium text-red-700 dark:text-red-300 px-6 py-3">Customer</th>
              <th class="text-left text-sm font-medium text-red-700 dark:text-red-300 px-6 py-3">Phone</th>
              <th class="text-left text-sm font-medium text-red-700 dark:text-red-300 px-6 py-3">Deleted</th>
              <th class="text-right text-sm font-medium text-red-700 dark:text-red-300 px-6 py-3">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in trashedItems" :key="item.id"
              class="border-b border-slate-100 dark:border-slate-700 opacity-80 hover:opacity-100">
              <td class="px-6 py-4">
                <p class="text-sm font-medium text-slate-700 dark:text-slate-300 line-through decoration-red-300">{{ item.name }}</p>
                <p class="text-xs text-slate-400 dark:text-slate-500 font-mono">{{ item.customer_code }}</p>
              </td>
              <td class="px-6 py-4 text-sm text-slate-500 dark:text-slate-400 font-mono">{{ item.phone }}</td>
              <td class="px-6 py-4 text-xs text-red-500 dark:text-red-400">{{ formatDate(item.deleted_at) }}</td>
              <td class="px-6 py-4 text-right">
                <div class="flex items-center justify-end gap-2">
                  <button @click="restoreItem(item)"
                    class="inline-flex items-center gap-1 text-xs text-emerald-600 hover:text-emerald-700 dark:text-emerald-400 font-medium px-2.5 py-1 border border-emerald-200 dark:border-emerald-800 rounded-lg hover:bg-emerald-50 dark:hover:bg-emerald-900/20">
                    <RotateCcwIcon class="w-3 h-3" /> Restore
                  </button>
                  <button v-if="canPurge" @click="confirmPurge(item)"
                    class="inline-flex items-center gap-1 text-xs text-red-600 hover:text-red-700 dark:text-red-400 font-medium px-2.5 py-1 border border-red-200 dark:border-red-800 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20">
                    <XIcon class="w-3 h-3" /> Delete Forever
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Confirm Dialogs -->
    <ConfirmDialog
      :show="!!deleteTarget"
      title="Move to trash?"
      :description="deleteTarget ? `'${deleteTarget.name}' will be moved to trash.` : ''"
      confirm-text="Move to Trash"
      variant="danger"
      :loading="deleting"
      @confirm="executeDelete"
      @cancel="deleteTarget = null"
    />
    <ConfirmDialog
      :show="!!purgeTarget"
      title="Permanently delete?"
      :description="purgeTarget ? `'${purgeTarget.name}' will be permanently deleted. This cannot be undone.` : ''"
      confirm-text="Delete Forever"
      variant="danger"
      :loading="purging"
      @confirm="executePurge"
      @cancel="purgeTarget = null"
    />

  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { PlusIcon, SearchIcon, TagIcon, TrashIcon, RotateCcwIcon, XIcon, ArrowLeftIcon } from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'
import ThreeDIcon from '@/Components/UI/ThreeDIcon.vue'
import ConfirmDialog from '@/Components/UI/ConfirmDialog.vue'
import { useToast } from '@/Composables/useToast'

const props = defineProps({
  customers:  { type: Object, required: true },
  tags:       { type: Array, default: () => [] },
  priceTiers: { type: Array, default: () => [] },
  filters:    { type: Object, default: () => ({}) },
})

const page = usePage()
const { success, error: showError } = useToast()

const canPurge = computed(() =>
  page.props.auth?.user?.permissions?.includes('admin.trash.purge') ||
  page.props.auth?.user?.roles?.includes('admin')
)

const filters = ref({
  search:        props.filters.search || '',
  type:          props.filters.type || '',
  tag_id:        props.filters.tag_id || '',
  price_tier_id: props.filters.price_tier_id || '',
})

const showTrash      = ref(false)
const trashedItems   = ref([])
const trashedCount   = ref(0)
const trashedLoading = ref(false)
const deleteTarget   = ref(null)
const deleting       = ref(false)
const purgeTarget    = ref(null)
const purging        = ref(false)

let searchTimer = null
function debouncedSearch() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(applyFilters, 400)
}

function applyFilters() {
  const params = {}
  if (filters.value.search)        params.search        = filters.value.search
  if (filters.value.type)          params.type          = filters.value.type
  if (filters.value.tag_id)        params.tag_id        = filters.value.tag_id
  if (filters.value.price_tier_id) params.price_tier_id = filters.value.price_tier_id
  router.get(route('customers.index'), params, { preserveState: true, replace: true })
}

function resetFilters() {
  filters.value = { search: '', type: '', tag_id: '', price_tier_id: '' }
  router.get(route('customers.index'), {}, { preserveState: false })
}

async function toggleTrash() {
  showTrash.value = !showTrash.value
  if (showTrash.value) loadTrashed()
}

async function loadTrashed() {
  trashedLoading.value = true
  try {
    const res = await window.axios.get('/api/v1/customers/trashed')
    trashedItems.value = res.data
    trashedCount.value = res.data.length
  } catch {
    showError('Failed to load trash.')
  } finally {
    trashedLoading.value = false
  }
}

function confirmDelete(item) { deleteTarget.value = item }

async function executeDelete() {
  if (!deleteTarget.value) return
  deleting.value = true
  try {
    await window.axios.delete(`/api/v1/customers/${deleteTarget.value.id}`)
    success(`"${deleteTarget.value.name}" moved to trash.`)
    deleteTarget.value = null
    router.reload()
  } catch (err) {
    showError(err.response?.data?.message || 'Failed to delete.')
  } finally {
    deleting.value = false
  }
}

async function restoreItem(item) {
  try {
    await window.axios.post(`/api/v1/customers/${item.id}/restore`)
    success(`"${item.name}" restored successfully!`)
    trashedItems.value = trashedItems.value.filter(i => i.id !== item.id)
    trashedCount.value = trashedItems.value.length
    router.reload()
  } catch (err) {
    showError(err.response?.data?.message || 'Failed to restore.')
  }
}

function confirmPurge(item) { purgeTarget.value = item }

async function executePurge() {
  if (!purgeTarget.value) return
  purging.value = true
  try {
    await window.axios.delete(`/api/v1/customers/${purgeTarget.value.id}/force`)
    success(`"${purgeTarget.value.name}" permanently deleted.`)
    trashedItems.value = trashedItems.value.filter(i => i.id !== purgeTarget.value.id)
    trashedCount.value = trashedItems.value.length
    purgeTarget.value = null
  } catch (err) {
    showError(err.response?.data?.message || 'Failed to delete permanently.')
  } finally {
    purging.value = false
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
</script>
