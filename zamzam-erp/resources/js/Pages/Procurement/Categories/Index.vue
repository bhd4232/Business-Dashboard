<template>
  <AppLayout>
    <Head title="Categories" />

    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-semibold text-slate-900 dark:text-slate-100">Product Categories</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Manage product classification</p>
      </div>
      <div class="flex items-center gap-2">
        <button @click="toggleTrash"
          :class="showTrash ? 'bg-red-50 border-red-300 text-red-700 dark:bg-red-900/20 dark:border-red-700 dark:text-red-400' : 'border-slate-300 text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-700/50'"
          class="inline-flex items-center gap-2 border text-sm font-medium px-4 py-2 rounded-lg transition-colors">
          <TrashIcon class="w-4 h-4" />
          Trash
          <span v-if="trashedCount" class="bg-red-500 text-white text-xs rounded-full px-1.5 py-0.5 ml-0.5">{{ trashedCount }}</span>
        </button>
        <button @click="openCreateModal"
          class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
          <PlusIcon class="w-4 h-4" /> New Category
        </button>
      </div>
    </div>

    <!-- Active Categories Table -->
    <div v-if="!showTrash" class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
      <table class="w-full">
        <thead>
          <tr class="bg-slate-50 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700">
            <th class="text-left text-sm font-medium text-slate-700 dark:text-slate-300 px-6 py-3">Category</th>
            <th class="text-left text-sm font-medium text-slate-700 dark:text-slate-300 px-6 py-3">Sub-categories</th>
            <th class="text-right text-sm font-medium text-slate-700 dark:text-slate-300 px-6 py-3">Products</th>
            <th class="text-right text-sm font-medium text-slate-700 dark:text-slate-300 px-6 py-3">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="categories.length === 0">
            <td colspan="4" class="text-center py-10 text-slate-400 text-sm">No categories yet</td>
          </tr>
          <template v-for="cat in categories" :key="cat.id">
            <tr class="border-b border-slate-100 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700/50">
              <td class="px-6 py-3">
                <div class="flex items-center gap-2">
                  <TagIcon class="w-4 h-4 text-indigo-500" />
                  <span class="text-sm font-medium text-slate-900 dark:text-slate-100">{{ cat.name }}</span>
                  <span class="text-xs text-slate-400 dark:text-slate-500 font-mono">{{ cat.slug }}</span>
                </div>
              </td>
              <td class="px-6 py-3 text-sm text-slate-500 dark:text-slate-400">
                {{ cat.children?.length || 0 }} sub-categories
              </td>
              <td class="px-6 py-3 text-right text-sm text-slate-700 dark:text-slate-300">{{ cat.products_count || 0 }}</td>
              <td class="px-6 py-3 text-right">
                <div class="flex items-center justify-end gap-2">
                  <button @click="editCategory(cat)"
                    class="text-xs text-indigo-600 hover:text-indigo-700 dark:text-primary-400 dark:hover:text-primary-300 font-medium">Edit</button>
                  <button @click="confirmDelete(cat)"
                    class="p-1 text-red-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors">
                    <TrashIcon class="w-3.5 h-3.5" />
                  </button>
                </div>
              </td>
            </tr>
            <tr v-for="child in cat.children" :key="child.id"
              class="border-b border-slate-100 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700/50 bg-slate-50/50 dark:bg-slate-800/50">
              <td class="px-6 py-2 pl-12">
                <div class="flex items-center gap-2">
                  <span class="text-slate-300 dark:text-slate-600 text-sm">↳</span>
                  <span class="text-sm text-slate-700 dark:text-slate-300">{{ child.name }}</span>
                  <span class="text-xs text-slate-400 dark:text-slate-500 font-mono">{{ child.slug }}</span>
                </div>
              </td>
              <td class="px-6 py-2 text-sm text-slate-400 dark:text-slate-500">—</td>
              <td class="px-6 py-2 text-right text-sm text-slate-700 dark:text-slate-300">{{ child.products_count || 0 }}</td>
              <td class="px-6 py-2 text-right">
                <div class="flex items-center justify-end gap-2">
                  <button @click="editCategory(child)"
                    class="text-xs text-indigo-600 hover:text-indigo-700 dark:text-primary-400 dark:hover:text-primary-300 font-medium">Edit</button>
                  <button @click="confirmDelete(child)"
                    class="p-1 text-red-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors">
                    <TrashIcon class="w-3.5 h-3.5" />
                  </button>
                </div>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>

    <!-- Trash Section -->
    <div v-if="showTrash">
      <div class="flex items-center gap-3 mb-4">
        <BackButton label="Back to Categories" @click="toggleTrash" />
        <TrashIcon class="w-5 h-5 text-red-500" />
        <h2 class="text-lg font-semibold text-slate-800 dark:text-slate-200">Trash</h2>
        <span class="text-sm text-slate-500 dark:text-slate-400">Deleted categories — restore or permanently remove</span>
      </div>
      <div v-if="trashedLoading" class="text-center py-8 text-slate-400 text-sm">Loading...</div>
      <div v-else-if="trashedItems.length === 0" class="bg-white dark:bg-slate-800 rounded-xl border border-dashed border-slate-300 dark:border-slate-600 p-10 text-center text-slate-400">
        <p class="text-sm">Trash is empty</p>
      </div>
      <div v-else class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
        <table class="w-full">
          <thead>
            <tr class="bg-red-50 dark:bg-red-900/20 border-b border-red-100 dark:border-red-800">
              <th class="text-left text-sm font-medium text-red-700 px-6 py-3">Category</th>
              <th class="text-left text-sm font-medium text-red-700 px-6 py-3">Parent</th>
              <th class="text-left text-sm font-medium text-red-700 px-6 py-3">Deleted</th>
              <th class="text-right text-sm font-medium text-red-700 px-6 py-3">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in trashedItems" :key="item.id" class="border-b border-slate-100 dark:border-slate-700 opacity-80 hover:opacity-100">
              <td class="px-6 py-4">
                <div class="flex items-center gap-2">
                  <TagIcon class="w-4 h-4 text-slate-300" />
                  <span class="text-sm font-medium text-slate-700 dark:text-slate-300 line-through decoration-red-300">{{ item.name }}</span>
                  <span class="text-xs text-slate-400 dark:text-slate-500 font-mono">{{ item.slug }}</span>
                </div>
              </td>
              <td class="px-6 py-4 text-sm text-slate-500 dark:text-slate-400">{{ item.parent?.name || '—' }}</td>
              <td class="px-6 py-4 text-xs text-red-500">{{ formatDate(item.deleted_at) }}</td>
              <td class="px-6 py-4 text-right">
                <div class="flex items-center justify-end gap-2">
                  <button @click="restoreItem(item)"
                    class="inline-flex items-center gap-1 text-xs text-emerald-600 hover:text-emerald-700 font-medium px-2.5 py-1 border border-emerald-200 rounded-lg hover:bg-emerald-50 dark:hover:bg-emerald-900/20">
                    <RotateCcwIcon class="w-3 h-3" /> Restore
                  </button>
                  <button v-if="canPurge" @click="confirmPurge(item)"
                    class="inline-flex items-center gap-1 text-xs text-red-600 hover:text-red-700 font-medium px-2.5 py-1 border border-red-200 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20">
                    <XIcon class="w-3 h-3" /> Delete Forever
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Create/Edit Modal -->
    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4">
      <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-xl w-full max-w-md p-6">
        <div class="flex items-center justify-between mb-5">
          <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100">
            {{ editing ? 'Edit Category' : 'New Category' }}
          </h3>
          <button @click="closeModal" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300">
            <XIcon class="w-5 h-5" />
          </button>
        </div>

        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Name <span class="text-red-500">*</span></label>
            <input v-model="modalForm.name" type="text" placeholder="Electronics"
              class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:placeholder:text-slate-500 dark:focus:ring-primary-900/30 px-3 py-2 text-sm focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100"
              :class="{ 'border-red-400': formErrors.name }" />
            <p v-if="formErrors.name" class="mt-1 text-xs text-red-600">{{ formErrors.name[0] }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Parent Category</label>
            <select v-model="modalForm.parent_id"
              class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 px-3 py-2 text-sm focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 bg-white">
              <option value="">— No parent (root category) —</option>
              <option v-for="cat in rootCategories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
            </select>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Sort Order</label>
            <input v-model.number="modalForm.sort_order" type="number" min="0"
              class="w-full rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:focus:ring-primary-900/30 px-3 py-2 text-sm focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100" />
          </div>
        </div>

        <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-slate-100 dark:border-slate-700">
          <button @click="closeModal"
            class="px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 border border-slate-300 dark:border-slate-600 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
            Cancel
          </button>
          <button @click="saveCategory" :disabled="saving"
            class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white text-sm font-medium rounded-lg flex items-center gap-2 transition-colors">
            <LoaderIcon v-if="saving" class="w-4 h-4 animate-spin" />
            <CheckIcon v-else class="w-4 h-4" />
            {{ saving ? 'Saving...' : 'Save' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Confirm Delete Dialog -->
    <ConfirmDialog
      :show="!!deleteTarget"
      title="Move to trash?"
      :description="deleteTarget ? `'${deleteTarget.name}' will be moved to trash. Categories with products cannot be deleted.` : ''"
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
import { Head, router, usePage } from '@inertiajs/vue3'
import { PlusIcon, TagIcon, LoaderIcon, XIcon, CheckIcon, TrashIcon, RotateCcwIcon } from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'
import ConfirmDialog from '@/Components/UI/ConfirmDialog.vue'
import BackButton from '@/Components/UI/BackButton.vue'
import { useToast } from '@/Composables/useToast'

const props = defineProps({
  categories: { type: Array, default: () => [] },
})

const page = usePage()
const { success, error: showError } = useToast()

const canPurge = computed(() =>
  page.props.auth?.user?.permissions?.includes('admin.trash.purge') ||
  page.props.auth?.user?.roles?.includes('admin')
)

// Modal state
const showModal  = ref(false)
const editing    = ref(null)
const saving     = ref(false)
const formErrors = ref({})
const modalForm  = ref({ name: '', parent_id: '', sort_order: 0 })

// Trash state
const showTrash      = ref(false)
const trashedItems   = ref([])
const trashedCount   = ref(0)
const trashedLoading = ref(false)

// Delete/purge state
const deleteTarget = ref(null)
const deleting     = ref(false)
const purgeTarget  = ref(null)
const purging      = ref(false)

const rootCategories = computed(() =>
  props.categories.filter(c => !c.parent_id)
)

function openCreateModal() {
  editing.value    = null
  formErrors.value = {}
  modalForm.value  = { name: '', parent_id: '', sort_order: 0 }
  showModal.value  = true
}

function editCategory(cat) {
  editing.value    = cat
  formErrors.value = {}
  modalForm.value  = { name: cat.name, parent_id: cat.parent_id || '', sort_order: cat.sort_order || 0 }
  showModal.value  = true
}

function closeModal() {
  showModal.value  = false
  formErrors.value = {}
}

async function saveCategory() {
  if (!modalForm.value.name.trim()) {
    formErrors.value = { name: ['Category name is required.'] }
    return
  }

  saving.value     = true
  formErrors.value = {}

  try {
    const url    = editing.value ? `/api/v1/categories/${editing.value.id}` : '/api/v1/categories'
    const method = editing.value ? 'put' : 'post'

    await window.axios[method](url, modalForm.value)

    success(editing.value
      ? `Category "${modalForm.value.name}" updated successfully!`
      : `Category "${modalForm.value.name}" created successfully!`
    )
    closeModal()
    router.reload()
  } catch (err) {
    if (err.response?.status === 422) {
      formErrors.value = err.response.data.errors || {}
      const firstMsg = Object.values(err.response.data.errors || {})[0]?.[0]
      if (firstMsg) showError(firstMsg)
    } else {
      showError(err.response?.data?.message || 'Something went wrong. Please try again.')
    }
  } finally {
    saving.value = false
  }
}

async function toggleTrash() {
  showTrash.value = !showTrash.value
  if (showTrash.value) loadTrashed()
}

async function loadTrashed() {
  trashedLoading.value = true
  try {
    const res = await window.axios.get('/api/v1/categories/trashed')
    trashedItems.value = res.data
    trashedCount.value = res.data.length
  } catch (e) {
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
    await window.axios.delete(`/api/v1/categories/${deleteTarget.value.id}`)
    success(`"${deleteTarget.value.name}" moved to trash.`)
    deleteTarget.value = null
    router.reload()
  } catch (err) {
    showError(err.response?.data?.message || 'Failed to delete category.')
  } finally {
    deleting.value = false
  }
}

async function restoreItem(item) {
  try {
    await window.axios.post(`/api/v1/categories/${item.id}/restore`)
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
    await window.axios.delete(`/api/v1/categories/${purgeTarget.value.id}/force`)
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

function formatDate(d) {
  if (!d) return ''
  return new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
}
</script>
