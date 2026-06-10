<template>
  <AppLayout>
    <Head title="Suppliers" />

    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-semibold text-slate-900 dark:text-slate-100">Suppliers</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Manage Chinese suppliers</p>
      </div>
      <div class="flex items-center gap-2">
        <button @click="toggleTrash"
          :class="showTrash ? 'bg-red-50 border-red-300 text-red-700 dark:bg-red-900/20 dark:border-red-800 dark:text-red-300' : 'border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50'"
          class="inline-flex items-center gap-2 border text-sm font-medium px-4 py-2 rounded-lg transition-colors">
          <TrashIcon class="w-4 h-4" />
          Trash
          <span v-if="trashedCount" class="bg-red-500 text-white text-xs rounded-full px-1.5 py-0.5 ml-0.5">{{ trashedCount }}</span>
        </button>
        <Link :href="route('suppliers.create')"
          class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
          <PlusIcon class="w-4 h-4" /> New Supplier
        </Link>
      </div>
    </div>

    <!-- Filters -->
    <div v-if="!showTrash" class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-4 mb-4">
      <div class="flex items-center gap-3 flex-wrap">
        <div class="relative flex-1 min-w-48">
          <SearchIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
          <input v-model="searchQuery" @input="debouncedSearch" type="text" placeholder="Search by name or WeChat ID..."
            class="w-full pl-9 pr-4 py-2 text-sm border border-slate-300 rounded-lg focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 dark:bg-slate-800 dark:border-slate-600 dark:text-slate-100 dark:focus:ring-primary-900/30 dark:placeholder:text-slate-500" />
        </div>
        <button @click="resetFilters" class="text-sm text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300 px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700/50">Reset</button>
      </div>
    </div>

    <!-- Active Suppliers Table -->
    <div v-if="!showTrash" class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
      <table class="w-full">
        <thead>
          <tr class="bg-slate-50 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700">
            <th class="text-left text-sm font-medium text-slate-700 dark:text-slate-300 px-6 py-3">Supplier</th>
            <th class="text-left text-sm font-medium text-slate-700 dark:text-slate-300 px-6 py-3">City</th>
            <th class="text-left text-sm font-medium text-slate-700 dark:text-slate-300 px-6 py-3">WeChat</th>
            <th class="text-left text-sm font-medium text-slate-700 dark:text-slate-300 px-6 py-3">Rating</th>
            <th class="text-left text-sm font-medium text-slate-700 dark:text-slate-300 px-6 py-3">Status</th>
            <th class="text-right text-sm font-medium text-slate-700 dark:text-slate-300 px-6 py-3">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="suppliers.data.length === 0">
            <td colspan="6" class="text-center py-12 text-slate-400">
              <BuildingIcon class="w-10 h-10 mx-auto mb-2 text-slate-300 dark:text-slate-600" />
              <p class="text-sm">No suppliers found</p>
            </td>
          </tr>
          <tr v-for="supplier in suppliers.data" :key="supplier.id"
            class="border-b border-slate-100 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
            <td class="px-6 py-4">
              <div>
                <p class="text-sm font-medium text-slate-900 dark:text-slate-100">{{ supplier.name_english }}</p>
                <p class="text-xs text-slate-500 dark:text-slate-400 font-mono">{{ supplier.name_chinese }}</p>
              </div>
            </td>
            <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-400">{{ supplier.city }}{{ supplier.province ? ', ' + supplier.province : '' }}</td>
            <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-400 font-mono">{{ supplier.wechat_id || '—' }}</td>
            <td class="px-6 py-4">
              <div class="flex gap-0.5">
                <StarIcon v-for="i in 5" :key="i" class="w-4 h-4"
                  :class="i <= (supplier.rating || 0) ? 'text-amber-400 fill-amber-400' : 'text-slate-200 fill-slate-200 dark:text-slate-600 dark:fill-slate-600'" />
              </div>
            </td>
            <td class="px-6 py-4">
              <span :class="supplier.is_active ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300'"
                class="rounded-full px-2.5 py-0.5 text-xs font-medium">
                {{ supplier.is_active ? 'Active' : 'Inactive' }}
              </span>
            </td>
            <td class="px-6 py-4 text-right">
              <div class="flex items-center justify-end gap-2">
                <Link :href="route('suppliers.show', supplier.id)" class="text-sm text-indigo-600 hover:text-indigo-700 dark:text-primary-400 dark:hover:text-primary-300 font-medium">View</Link>
                <Link :href="route('suppliers.edit', supplier.id)" class="text-sm text-slate-600 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200">Edit</Link>
                <button @click="confirmDelete(supplier)"
                  class="p-1.5 text-red-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors">
                  <TrashIcon class="w-3.5 h-3.5" />
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>

      <div v-if="suppliers.last_page > 1" class="flex items-center justify-between px-6 py-3 border-t border-slate-200 dark:border-slate-700">
        <p class="text-sm text-slate-500 dark:text-slate-400">{{ suppliers.from }}–{{ suppliers.to }} of {{ suppliers.total }} suppliers</p>
        <div class="flex gap-1">
          <Link v-for="link in suppliers.links" :key="link.label" :href="link.url || '#'"
            :class="['px-3 py-1 text-sm rounded-lg transition-colors',
              link.active ? 'bg-indigo-600 text-white' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700',
              !link.url ? 'opacity-40 pointer-events-none' : '']"
            v-html="link.label" />
        </div>
      </div>
    </div>

    <!-- Trash Section -->
    <div v-if="showTrash">
      <div class="flex items-center gap-3 mb-4">
        <BackButton label="Back to Suppliers" @click="toggleTrash" />
        <TrashIcon class="w-5 h-5 text-red-500" />
        <h2 class="text-lg font-semibold text-slate-800 dark:text-slate-200">Trash</h2>
        <span class="text-sm text-slate-500 dark:text-slate-400">Deleted suppliers — restore or permanently remove</span>
      </div>
      <div v-if="trashedLoading" class="text-center py-8 text-slate-400 text-sm">Loading...</div>
      <div v-else-if="trashedItems.length === 0" class="bg-white dark:bg-slate-800 rounded-xl border border-dashed border-slate-300 dark:border-slate-600 p-10 text-center text-slate-400">
        <p class="text-sm">Trash is empty</p>
      </div>
      <div v-else class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
        <table class="w-full">
          <thead>
            <tr class="bg-red-50 dark:bg-red-900/20 border-b border-red-100 dark:border-red-800">
              <th class="text-left text-sm font-medium text-red-700 dark:text-red-300 px-6 py-3">Supplier</th>
              <th class="text-left text-sm font-medium text-red-700 dark:text-red-300 px-6 py-3">City</th>
              <th class="text-left text-sm font-medium text-red-700 dark:text-red-300 px-6 py-3">Deleted</th>
              <th class="text-right text-sm font-medium text-red-700 dark:text-red-300 px-6 py-3">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in trashedItems" :key="item.id" class="border-b border-slate-100 dark:border-slate-700 opacity-80 hover:opacity-100">
              <td class="px-6 py-4">
                <p class="text-sm font-medium text-slate-700 dark:text-slate-300 line-through decoration-red-300">{{ item.name_english }}</p>
                <p class="text-xs text-slate-400 dark:text-slate-500 font-mono">{{ item.name_chinese }}</p>
              </td>
              <td class="px-6 py-4 text-sm text-slate-500 dark:text-slate-400">{{ item.city || '—' }}</td>
              <td class="px-6 py-4 text-xs text-red-500">{{ formatDate(item.deleted_at) }}</td>
              <td class="px-6 py-4 text-right">
                <div class="flex items-center justify-end gap-2">
                  <button @click="restoreItem('suppliers', item)"
                    class="inline-flex items-center gap-1 text-xs text-emerald-600 hover:text-emerald-700 font-medium px-2.5 py-1 border border-emerald-200 dark:border-emerald-800 rounded-lg hover:bg-emerald-50 dark:hover:bg-emerald-900/20">
                    <RotateCcwIcon class="w-3 h-3" /> Restore
                  </button>
                  <button v-if="canPurge" @click="confirmPurge(item, 'suppliers')"
                    class="inline-flex items-center gap-1 text-xs text-red-600 hover:text-red-700 font-medium px-2.5 py-1 border border-red-200 dark:border-red-800 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20">
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
      :description="deleteTarget ? `'${deleteTarget.name_english}' will be moved to trash.` : ''"
      confirm-text="Move to Trash"
      variant="danger"
      :loading="deleting"
      @confirm="executeDelete"
      @cancel="deleteTarget = null"
    />
    <ConfirmDialog
      :show="!!purgeTarget"
      title="Permanently delete?"
      :description="purgeTarget ? `'${purgeTarget.item?.name_english}' will be permanently deleted. This cannot be undone.` : ''"
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
import { PlusIcon, SearchIcon, BuildingIcon, StarIcon, TrashIcon, RotateCcwIcon, XIcon } from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'
import BackButton from '@/Components/UI/BackButton.vue'
import ConfirmDialog from '@/Components/UI/ConfirmDialog.vue'
import { useToast } from '@/Composables/useToast'

const props = defineProps({
  suppliers: { type: Object, required: true },
  filters:   { type: Object, default: () => ({}) },
})

const page = usePage()
const { success, error: showError } = useToast()

const canPurge = computed(() =>
  page.props.auth?.user?.permissions?.includes('admin.trash.purge') ||
  page.props.auth?.user?.roles?.includes('admin')
)

const searchQuery    = ref(props.filters.search || '')
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
  searchTimer = setTimeout(() => {
    router.get(route('suppliers.index'), { search: searchQuery.value }, { preserveState: true, replace: true })
  }, 400)
}

function resetFilters() {
  searchQuery.value = ''
  router.get(route('suppliers.index'), {}, { preserveState: false })
}

async function toggleTrash() {
  showTrash.value = !showTrash.value
  if (showTrash.value) loadTrashed()
}

async function loadTrashed() {
  trashedLoading.value = true
  try {
    const res = await window.axios.get('/api/v1/suppliers/trashed')
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
    await window.axios.delete(`/api/v1/suppliers/${deleteTarget.value.id}`)
    success(`"${deleteTarget.value.name_english}" moved to trash.`)
    deleteTarget.value = null
    router.reload()
  } catch (err) {
    showError(err.response?.data?.message || 'Failed to delete.')
  } finally {
    deleting.value = false
  }
}

async function restoreItem(type, item) {
  try {
    await window.axios.post(`/api/v1/${type}/${item.id}/restore`)
    success(`"${item.name_english}" restored successfully!`)
    trashedItems.value = trashedItems.value.filter(i => i.id !== item.id)
    trashedCount.value = trashedItems.value.length
    router.reload()
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
    success(`"${purgeTarget.value.item.name_english}" permanently deleted.`)
    trashedItems.value = trashedItems.value.filter(i => i.id !== purgeTarget.value.item.id)
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
