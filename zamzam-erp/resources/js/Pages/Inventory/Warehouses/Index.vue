<template>
  <AppLayout>
    <Head title="Warehouses" />

    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-semibold text-slate-900 dark:text-slate-100">Warehouses</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Stock levels and valuation by warehouse</p>
      </div>
      <div class="flex items-center gap-2">
        <button @click="toggleTrash"
          :class="showTrash ? 'bg-red-50 border-red-300 text-red-700' : 'border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50'"
          class="inline-flex items-center gap-2 border text-sm font-medium px-4 py-2 rounded-lg transition-colors">
          <TrashIcon class="w-4 h-4" />
          Trash
          <span v-if="trashedItems.length" class="bg-red-500 text-white text-xs rounded-full px-1.5 py-0.5 ml-0.5">{{ trashedItems.length }}</span>
        </button>
        <button @click="openCreateModal"
          class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
          <PlusIcon class="w-4 h-4" /> New Warehouse
        </button>
      </div>
    </div>

    <!-- Active Warehouses Grid -->
    <div v-if="!showTrash">
      <div v-if="warehouses.length === 0" class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-12 text-center text-slate-400">
        <PackageIcon class="w-12 h-12 mx-auto mb-3 text-slate-300" />
        <p class="text-sm font-medium">No warehouses yet</p>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <div v-for="wh in warehouses" :key="wh.id" class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-5">
          <div class="flex items-start justify-between mb-3">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-xl bg-amber-100 flex items-center justify-center">
                <PackageIcon class="w-5 h-5 text-amber-600" />
              </div>
              <div>
                <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ wh.name }}</p>
                <p class="text-xs text-slate-500 dark:text-slate-400 font-mono">{{ wh.code }}</p>
              </div>
            </div>
            <div class="flex items-center gap-1">
              <span v-if="wh.is_default" class="bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300 text-xs px-2 py-0.5 rounded-full">Default</span>
              <span :class="wh.is_active ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300'" class="text-xs px-2 py-0.5 rounded-full">
                {{ wh.is_active ? 'Active' : 'Inactive' }}
              </span>
            </div>
          </div>
          <p v-if="wh.city" class="text-xs text-slate-500 dark:text-slate-400 mb-3 flex items-center gap-1">
            <MapPinIcon class="w-3 h-3" /> {{ wh.city }}{{ wh.address ? ' — ' + wh.address : '' }}
          </p>
          <div class="grid grid-cols-2 gap-3 mb-3">
            <div class="bg-slate-50 dark:bg-slate-700/50 rounded-lg p-3">
              <p class="text-xs text-slate-500 dark:text-slate-400 mb-1">Product Types</p>
              <p class="text-lg font-bold text-slate-900 dark:text-slate-100">{{ wh.stock_items_count || 0 }}</p>
            </div>
            <div class="bg-slate-50 dark:bg-slate-700/50 rounded-lg p-3">
              <p class="text-xs text-slate-500 dark:text-slate-400 mb-1">Stock Value (BDT)</p>
              <p class="text-sm font-bold text-slate-900 dark:text-slate-100 font-mono">৳{{ Number(wh.stock_value_bdt || 0).toLocaleString() }}</p>
            </div>
          </div>
          <!-- Actions -->
          <div class="flex gap-2">
            <Link :href="route('stock.index') + '?warehouse_id=' + wh.id"
              class="flex-1 text-center text-xs text-indigo-600 dark:text-primary-400 hover:text-indigo-700 dark:hover:text-primary-300 font-medium py-1.5 border border-indigo-200 rounded-lg hover:bg-indigo-50 dark:hover:bg-indigo-900/20 transition-colors">
              View Stock
            </Link>
            <button @click="openEditModal(wh)"
              class="px-3 py-1.5 text-xs text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100 font-medium border border-slate-200 dark:border-slate-700 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors flex items-center gap-1">
              <PencilIcon class="w-3 h-3" /> Edit
            </button>
            <button @click="confirmDelete(wh)"
              class="px-3 py-1.5 text-xs text-red-500 hover:text-red-700 font-medium border border-red-200 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors flex items-center gap-1">
              <TrashIcon class="w-3 h-3" />
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Trash Section -->
    <div v-if="showTrash">
      <div class="flex items-center gap-3 mb-4">
        <BackButton label="Back to Warehouses" @click="toggleTrash" />
        <TrashIcon class="w-5 h-5 text-red-500" />
        <h2 class="text-lg font-semibold text-slate-800 dark:text-slate-200">Trash</h2>
        <span class="text-sm text-slate-500 dark:text-slate-400">Deleted warehouses can be restored</span>
      </div>
      <div v-if="trashedLoading" class="text-center py-8 text-slate-400 text-sm">Loading...</div>
      <div v-else-if="trashedItems.length === 0" class="bg-white dark:bg-slate-800 rounded-xl border border-dashed border-slate-300 dark:border-slate-600 p-10 text-center text-slate-400">
        <TrashIcon class="w-8 h-8 mx-auto mb-2 text-slate-300" />
        <p class="text-sm">Trash is empty</p>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <div v-for="wh in trashedItems" :key="wh.id"
          class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-red-100 dark:border-red-800 p-5 opacity-75">
          <div class="flex items-start justify-between mb-2">
            <div>
              <p class="text-sm font-semibold text-slate-700 dark:text-slate-300">{{ wh.name }}</p>
              <p class="text-xs text-slate-400 dark:text-slate-500 font-mono">{{ wh.code }}</p>
              <p class="text-xs text-red-500 mt-1">Deleted {{ formatDate(wh.deleted_at) }}</p>
            </div>
          </div>
          <div class="flex gap-2 mt-3">
            <button @click="restoreItem('warehouses', wh)"
              class="flex-1 text-xs text-emerald-600 hover:text-emerald-700 font-medium py-1.5 border border-emerald-200 rounded-lg hover:bg-emerald-50 dark:hover:bg-emerald-900/20 transition-colors flex items-center justify-center gap-1">
              <RotateCcwIcon class="w-3 h-3" /> Restore
            </button>
            <button v-if="canPurge" @click="confirmPurge(wh, 'warehouses')"
              class="px-3 py-1.5 text-xs text-red-600 hover:text-red-700 font-medium border border-red-200 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors flex items-center gap-1">
              <XIcon class="w-3 h-3" /> Delete Forever
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Create/Edit Modal -->
    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4">
      <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-xl w-full max-w-md p-6">
        <div class="flex items-center justify-between mb-5">
          <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100">{{ editingWarehouse ? 'Edit Warehouse' : 'New Warehouse' }}</h3>
          <button @click="closeModal" class="text-slate-400 hover:text-slate-600"><XIcon class="w-5 h-5" /></button>
        </div>
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Name <span class="text-red-500">*</span></label>
            <input v-model="form.name" type="text" placeholder="Main Warehouse"
              class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:placeholder:text-slate-500 px-3 py-2 text-sm focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 dark:focus:ring-primary-900/30"
              :class="{ 'border-red-400': formErrors.name }" />
            <p v-if="formErrors.name" class="mt-1 text-xs text-red-600">{{ formErrors.name[0] }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Code <span class="text-red-500">*</span></label>
            <input v-model="form.code" type="text" placeholder="WH-MAIN" maxlength="20"
              class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:placeholder:text-slate-500 px-3 py-2 text-sm focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 dark:focus:ring-primary-900/30 font-mono uppercase"
              :class="{ 'border-red-400': formErrors.code }"
              @input="form.code = form.code.toUpperCase()"
              :disabled="!!editingWarehouse" />
            <p v-if="editingWarehouse" class="mt-1 text-xs text-slate-400 dark:text-slate-500">Code cannot be changed after creation</p>
            <p v-if="formErrors.code" class="mt-1 text-xs text-red-600">{{ formErrors.code[0] }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">City</label>
            <input v-model="form.city" type="text" placeholder="Dhaka"
              class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:placeholder:text-slate-500 px-3 py-2 text-sm focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 dark:focus:ring-primary-900/30" />
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Address</label>
            <input v-model="form.address" type="text"
              class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 px-3 py-2 text-sm focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 dark:focus:ring-primary-900/30" />
          </div>
          <div class="flex items-center gap-3">
            <div class="flex items-center gap-2">
              <input type="checkbox" id="is_default" v-model="form.is_default" class="w-4 h-4 rounded border-slate-300 dark:border-slate-600 text-indigo-600" />
              <label for="is_default" class="text-sm text-slate-700 dark:text-slate-300 cursor-pointer">Default warehouse</label>
            </div>
            <div class="flex items-center gap-2">
              <input type="checkbox" id="is_active" v-model="form.is_active" class="w-4 h-4 rounded border-slate-300 dark:border-slate-600 text-indigo-600" />
              <label for="is_active" class="text-sm text-slate-700 dark:text-slate-300 cursor-pointer">Active</label>
            </div>
          </div>
        </div>
        <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-slate-100 dark:border-slate-700">
          <button @click="closeModal" class="px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 border border-slate-300 dark:border-slate-600 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700/50">Cancel</button>
          <button @click="saveWarehouse" :disabled="saving"
            class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white text-sm font-medium rounded-lg flex items-center gap-2">
            <LoaderIcon v-if="saving" class="w-4 h-4 animate-spin" />
            <CheckIcon v-else class="w-4 h-4" />
            {{ saving ? 'Saving...' : (editingWarehouse ? 'Update' : 'Save') }}
          </button>
        </div>
      </div>
    </div>

    <!-- Confirm Delete Dialog -->
    <ConfirmDialog
      :show="!!deleteTarget"
      title="Move to trash?"
      :description="deleteTarget ? `'${deleteTarget.name}' will be moved to trash. You can restore it later.` : ''"
      confirm-text="Move to Trash"
      variant="danger"
      :loading="deleting"
      @confirm="executeDelete"
      @cancel="deleteTarget = null"
    />

    <!-- Confirm Purge Dialog -->
    <ConfirmDialog
      :show="!!purgeTarget"
      title="Permanently delete?"
      :description="purgeTarget ? `'${purgeTarget.item?.name}' will be permanently deleted. This cannot be undone.` : ''"
      confirm-text="Delete Forever"
      variant="danger"
      :loading="purging"
      @confirm="executePurge"
      @cancel="purgeTarget = null"
    />

  </AppLayout>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { PlusIcon, MapPinIcon, LoaderIcon, PackageIcon, XIcon, CheckIcon, PencilIcon, TrashIcon, RotateCcwIcon } from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'
import BackButton from '@/Components/UI/BackButton.vue'
import ConfirmDialog from '@/Components/UI/ConfirmDialog.vue'
import { useToast } from '@/Composables/useToast'

const props = defineProps({
  warehouses: { type: Array, default: () => [] },
})

const page = usePage()
const { success, error: showError } = useToast()

const canPurge = computed(() =>
  page.props.auth?.user?.permissions?.includes('admin.trash.purge') ||
  page.props.auth?.user?.roles?.includes('admin')
)

// Modal state
const showModal        = ref(false)
const editingWarehouse = ref(null)
const saving           = ref(false)
const formErrors       = ref({})
const form = reactive({ name: '', code: '', city: '', address: '', is_default: false, is_active: true })

// Trash state
const showTrash      = ref(false)
const trashedItems   = ref([])
const trashedLoading = ref(false)

// Delete/purge state
const deleteTarget = ref(null)
const deleting     = ref(false)
const purgeTarget  = ref(null)
const purging      = ref(false)

function openCreateModal() {
  editingWarehouse.value = null
  formErrors.value = {}
  Object.assign(form, { name: '', code: '', city: '', address: '', is_default: false, is_active: true })
  showModal.value = true
}

function openEditModal(wh) {
  editingWarehouse.value = wh
  formErrors.value = {}
  Object.assign(form, {
    name:       wh.name,
    code:       wh.code,
    city:       wh.city || '',
    address:    wh.address || '',
    is_default: !!wh.is_default,
    is_active:  wh.is_active !== false,
  })
  showModal.value = true
}

function closeModal() {
  showModal.value = false
  formErrors.value = {}
}

async function saveWarehouse() {
  if (!form.name.trim()) { formErrors.value = { name: ['Name is required.'] }; return }
  if (!form.code.trim() && !editingWarehouse.value) { formErrors.value = { code: ['Code is required.'] }; return }

  saving.value = true
  formErrors.value = {}
  try {
    if (editingWarehouse.value) {
      await window.axios.put(`/api/v1/warehouses/${editingWarehouse.value.id}`, form)
      success(`Warehouse "${form.name}" updated successfully!`)
    } else {
      await window.axios.post('/api/v1/warehouses', form)
      success(`Warehouse "${form.name}" created successfully!`)
    }
    closeModal()
    router.reload({ only: ['warehouses'] })
  } catch (err) {
    if (err.response?.status === 422) {
      formErrors.value = err.response.data.errors || {}
      const msg = Object.values(formErrors.value)[0]?.[0]
      if (msg) showError(msg)
    } else {
      showError(err.response?.data?.message || 'Failed to save warehouse.')
    }
  } finally {
    saving.value = false
  }
}

function confirmDelete(wh) { deleteTarget.value = wh }

async function executeDelete() {
  if (!deleteTarget.value) return
  deleting.value = true
  try {
    await window.axios.delete(`/api/v1/warehouses/${deleteTarget.value.id}`)
    success(`"${deleteTarget.value.name}" moved to trash.`)
    deleteTarget.value = null
    router.reload({ only: ['warehouses'] })
    if (showTrash.value) loadTrashed()
  } catch (err) {
    showError(err.response?.data?.message || 'Failed to delete warehouse.')
  } finally {
    deleting.value = false
  }
}

async function toggleTrash() {
  showTrash.value = !showTrash.value
  if (showTrash.value && trashedItems.value.length === 0) loadTrashed()
}

async function loadTrashed() {
  trashedLoading.value = true
  try {
    const res = await window.axios.get('/api/v1/warehouses/trashed')
    trashedItems.value = res.data
  } catch (e) {
    showError('Failed to load trash.')
  } finally {
    trashedLoading.value = false
  }
}

async function restoreItem(type, item) {
  try {
    await window.axios.post(`/api/v1/${type}/${item.id}/restore`)
    success(`"${item.name}" restored successfully!`)
    trashedItems.value = trashedItems.value.filter(i => i.id !== item.id)
    router.reload({ only: ['warehouses'] })
  } catch (err) {
    showError(err.response?.data?.message || 'Failed to restore.')
  }
}

function confirmPurge(item, type) { purgeTarget.value = { item, type } }

async function executePurge() {
  if (!purgeTarget.value) return
  purging.value = true
  try {
    await window.axios.delete(`/api/v1/${purgeTarget.value.type}/${purgeTarget.value.item.id}/force`)
    success(`"${purgeTarget.value.item.name}" permanently deleted.`)
    trashedItems.value = trashedItems.value.filter(i => i.id !== purgeTarget.value.item.id)
    purgeTarget.value = null
  } catch (err) {
    showError(err.response?.data?.message || 'Failed to delete permanently.')
  } finally {
    purging.value = false
  }
}

function formatDate(d) {
  if (!d) return ''
  return new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
}
</script>
