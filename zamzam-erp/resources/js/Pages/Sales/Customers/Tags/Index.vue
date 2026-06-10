<template>
  <AppLayout>
    <Head title="Customer Tags" />

    <div class="flex items-center justify-between mb-6">
      <div class="flex items-center gap-3">
        <Link :href="route('customers.index')"
          class="inline-flex items-center gap-1.5 text-sm text-slate-600 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200 border border-slate-300 dark:border-slate-600 px-3 py-1.5 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700/50">
          <ArrowLeftIcon class="w-4 h-4" /> Customers
        </Link>
        <TagIcon class="w-5 h-5 text-slate-500 dark:text-slate-400" />
        <div>
          <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100">Customer Tags</h1>
          <p class="text-xs text-slate-500 dark:text-slate-400">Manage tags and link them to price tiers</p>
        </div>
      </div>
      <button @click="openCreate"
        class="inline-flex items-center gap-2 bg-primary-600 hover:bg-primary-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
        <PlusIcon class="w-4 h-4" /> Add Tag
      </button>
    </div>

    <!-- Tags Grid -->
    <div v-if="tags.length === 0" class="bg-white dark:bg-slate-800 rounded-xl border border-dashed border-slate-300 dark:border-slate-600 p-12 text-center">
      <TagIcon class="w-10 h-10 mx-auto mb-3 text-slate-300 dark:text-slate-600" />
      <p class="text-sm text-slate-500 dark:text-slate-400">No tags yet. Create your first tag to organize customers.</p>
    </div>

    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <div v-for="tag in localTags" :key="tag.id"
        class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm p-5 border-l-4 transition-all hover:shadow-md"
        :style="{ borderLeftColor: tag.color }">
        <div class="flex items-start justify-between">
          <div class="flex items-center gap-2">
            <span class="w-3 h-3 rounded-full flex-shrink-0" :style="{ backgroundColor: tag.color }" />
            <span class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ tag.name }}</span>
          </div>
          <div class="flex items-center gap-1">
            <button @click="openEdit(tag)"
              class="p-1.5 text-slate-400 dark:text-slate-500 hover:text-primary-600 dark:hover:text-primary-400 hover:bg-primary-50 dark:hover:bg-primary-900/20 rounded-lg transition-colors">
              <PencilIcon class="w-3.5 h-3.5" />
            </button>
            <button @click="confirmDelete(tag)"
              class="p-1.5 text-slate-400 dark:text-slate-500 hover:text-red-600 dark:hover:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors">
              <TrashIcon class="w-3.5 h-3.5" />
            </button>
          </div>
        </div>
        <p v-if="tag.description" class="text-xs text-slate-500 dark:text-slate-400 mt-1.5">{{ tag.description }}</p>
        <div class="flex items-center gap-3 mt-3 pt-3 border-t border-slate-100 dark:border-slate-700">
          <span class="text-xs text-slate-500 dark:text-slate-400">
            <span class="font-medium text-slate-700 dark:text-slate-300">{{ tag.customers_count }}</span> customers
          </span>
          <span v-if="tag.linked_price_tier" class="text-xs bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-300 rounded-full px-2 py-0.5">
            {{ tag.linked_price_tier.name }}
          </span>
          <span v-if="tag.is_auto_assign" class="text-xs bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300 rounded-full px-2 py-0.5">
            Auto-assign
          </span>
        </div>
      </div>
    </div>

    <!-- Create / Edit Modal -->
    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div class="absolute inset-0 bg-black/40 dark:bg-black/60" @click="closeModal" />
      <div class="relative bg-white dark:bg-slate-800 rounded-2xl shadow-2xl w-full max-w-md border border-slate-200 dark:border-slate-700">
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 dark:border-slate-700">
          <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100">
            {{ editTarget ? 'Edit Tag' : 'New Tag' }}
          </h2>
          <button @click="closeModal" class="p-1.5 rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700">
            <XIcon class="w-4 h-4" />
          </button>
        </div>
        <form @submit.prevent="submitTag" class="p-6 space-y-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Name <span class="text-red-500">*</span></label>
            <input v-model="tagForm.name" type="text" required
              class="w-full px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 dark:text-slate-100 focus:outline-none focus:border-primary-500" />
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Color <span class="text-red-500">*</span></label>
            <div class="flex items-center gap-3 flex-wrap">
              <button v-for="c in PRESET_COLORS" :key="c" type="button"
                @click="tagForm.color = c"
                :class="tagForm.color === c ? 'ring-2 ring-offset-2 ring-offset-white dark:ring-offset-slate-800 ring-slate-400' : ''"
                class="w-7 h-7 rounded-full transition-all"
                :style="{ backgroundColor: c }" />
              <input v-model="tagForm.color" type="color" class="w-7 h-7 rounded-full border-0 cursor-pointer" title="Custom color" />
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Description</label>
            <input v-model="tagForm.description" type="text"
              class="w-full px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 dark:text-slate-100 focus:outline-none focus:border-primary-500" />
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Linked Price Tier</label>
            <select v-model="tagForm.linked_price_tier_id"
              class="w-full px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 dark:text-slate-100 focus:outline-none focus:border-primary-500">
              <option :value="null">None</option>
              <option v-for="tier in priceTiers" :key="tier.id" :value="tier.id">{{ tier.name }}</option>
            </select>
          </div>
          <div class="flex items-center gap-2">
            <button type="button" @click="tagForm.is_auto_assign = !tagForm.is_auto_assign"
              :class="tagForm.is_auto_assign ? 'bg-emerald-500' : 'bg-slate-300 dark:bg-slate-600'"
              class="relative w-9 h-5 rounded-full transition-colors">
              <span :class="tagForm.is_auto_assign ? 'translate-x-4' : 'translate-x-0.5'"
                class="absolute top-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform" />
            </button>
            <span class="text-sm text-slate-700 dark:text-slate-300">Auto-assign to new customers</span>
          </div>
          <div class="flex justify-end gap-3 pt-2">
            <button type="button" @click="closeModal"
              class="px-4 py-2 text-sm text-slate-600 dark:text-slate-400 border border-slate-300 dark:border-slate-600 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700/50">
              Cancel
            </button>
            <button type="submit" :disabled="saving"
              class="inline-flex items-center gap-2 px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold rounded-lg transition-colors disabled:opacity-50">
              <LoaderIcon v-if="saving" class="w-4 h-4 animate-spin" />
              {{ saving ? 'Saving...' : (editTarget ? 'Save Changes' : 'Create Tag') }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Delete Confirm -->
    <ConfirmDialog
      :show="!!deleteTarget"
      title="Delete tag?"
      :description="deleteTarget ? `Delete '${deleteTarget.name}'? ${deleteTarget.customers_count > 0 ? `⚠️ This tag has ${deleteTarget.customers_count} customer(s).` : ''}` : ''"
      confirm-text="Delete Tag"
      variant="danger"
      :loading="deleting"
      @confirm="executeDelete"
      @cancel="deleteTarget = null"
    />

  </AppLayout>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import { ArrowLeftIcon, TagIcon, PlusIcon, PencilIcon, TrashIcon, XIcon, LoaderIcon } from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'
import ConfirmDialog from '@/Components/UI/ConfirmDialog.vue'
import { useToast } from '@/Composables/useToast'

const props = defineProps({
  tags:       { type: Array, default: () => [] },
  priceTiers: { type: Array, default: () => [] },
})

const { success, error: showError } = useToast()

const PRESET_COLORS = [
  '#6366F1','#F59E0B','#10B981','#EF4444','#3B82F6',
  '#EC4899','#8B5CF6','#14B8A6','#F97316','#64748B',
]

const localTags  = ref([...props.tags])
const showModal  = ref(false)
const editTarget = ref(null)
const saving     = ref(false)
const deleteTarget = ref(null)
const deleting   = ref(false)

const tagForm = reactive({
  name:                 '',
  color:                '#6366F1',
  description:          '',
  linked_price_tier_id: null,
  is_auto_assign:       false,
})

function openCreate() {
  editTarget.value = null
  Object.assign(tagForm, { name: '', color: '#6366F1', description: '', linked_price_tier_id: null, is_auto_assign: false })
  showModal.value = true
}

function openEdit(tag) {
  editTarget.value = tag
  Object.assign(tagForm, {
    name:                 tag.name,
    color:                tag.color,
    description:          tag.description ?? '',
    linked_price_tier_id: tag.linked_price_tier_id ?? null,
    is_auto_assign:       tag.is_auto_assign,
  })
  showModal.value = true
}

function closeModal() { showModal.value = false; editTarget.value = null }

async function submitTag() {
  saving.value = true
  try {
    if (editTarget.value) {
      const res = await window.axios.put(`/api/v1/customer-tags/${editTarget.value.id}`, tagForm)
      const idx = localTags.value.findIndex(t => t.id === editTarget.value.id)
      if (idx !== -1) localTags.value[idx] = res.data
      success('Tag updated!')
    } else {
      const res = await window.axios.post('/api/v1/customer-tags', tagForm)
      localTags.value.push(res.data)
      success('Tag created!')
    }
    closeModal()
  } catch (err) {
    showError(err.response?.data?.message || 'Failed to save tag.')
  } finally {
    saving.value = false
  }
}

function confirmDelete(tag) { deleteTarget.value = tag }

async function executeDelete() {
  if (!deleteTarget.value) return
  deleting.value = true
  try {
    await window.axios.delete(`/api/v1/customer-tags/${deleteTarget.value.id}`)
    localTags.value = localTags.value.filter(t => t.id !== deleteTarget.value.id)
    success('Tag deleted.')
    deleteTarget.value = null
  } catch (err) {
    showError(err.response?.data?.message || 'Failed to delete tag.')
  } finally {
    deleting.value = false
  }
}
</script>
