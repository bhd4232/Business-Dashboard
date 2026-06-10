<template>
  <div class="relative" ref="wrapperRef">

    <!-- Input field -->
    <div
      @click="openDropdown"
      :class="[
        'flex items-center gap-2 w-full rounded-lg border px-3 py-2 text-sm bg-white dark:bg-slate-800 cursor-text transition-all',
        isOpen
          ? 'border-primary-500 ring-2 ring-primary-100 dark:ring-primary-900/30'
          : hasError
            ? 'border-red-400 dark:border-red-500'
            : 'border-slate-300 dark:border-slate-600 hover:border-slate-400 dark:hover:border-slate-500',
      ]"
    >
      <!-- Selected badge -->
      <span v-if="selectedCategory && !isOpen" class="flex-1 text-slate-800 dark:text-slate-200 truncate">
        {{ selectedCategory.depth > 0 ? '↳ ' : '' }}{{ selectedCategory.name }}
      </span>

      <!-- Search input -->
      <input
        v-show="isOpen || !selectedCategory"
        ref="inputRef"
        v-model="search"
        type="text"
        :placeholder="selectedCategory ? selectedCategory.name : 'Search or type to create...'"
        class="flex-1 bg-transparent outline-none text-slate-800 dark:text-slate-200 placeholder:text-slate-400 dark:placeholder:text-slate-500 text-sm min-w-0"
        @input="onInput"
        @keydown.enter.prevent="onEnter"
        @keydown.escape="closeDropdown"
        @keydown.arrow-down.prevent="moveDown"
        @keydown.arrow-up.prevent="moveUp"
      />

      <!-- Icons -->
      <button
        v-if="selectedCategory"
        type="button"
        @click.stop="clearSelection"
        class="text-slate-300 dark:text-slate-600 hover:text-slate-500 dark:hover:text-slate-400 transition-colors shrink-0"
      >
        <XIcon class="w-3.5 h-3.5" />
      </button>
      <ChevronDownIcon
        :class="['w-4 h-4 text-slate-400 dark:text-slate-500 shrink-0 transition-transform', isOpen ? 'rotate-180' : '']"
      />
    </div>

    <!-- Dropdown -->
    <div
      v-if="isOpen"
      class="absolute z-50 top-full left-0 right-0 mt-1 bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-xl overflow-hidden"
    >
      <!-- List -->
      <div class="max-h-56 overflow-y-auto py-1">

        <template v-if="filteredList.length">
          <button
            v-for="(item, idx) in filteredList"
            :key="item.id"
            type="button"
            @mouseenter="highlighted = idx"
            @click="selectItem(item)"
            :class="[
              'w-full text-left px-3 py-2 text-sm flex items-center gap-2 transition-colors',
              highlighted === idx
                ? 'bg-primary-50 dark:bg-primary-900/40 text-primary-700 dark:text-primary-300'
                : 'text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700',
              item.id === modelValue ? 'font-semibold' : '',
            ]"
          >
            <span v-if="item.depth > 0" class="text-slate-400 dark:text-slate-500 text-xs ml-3">↳</span>
            <span :class="item.depth > 0 ? 'ml-1' : ''">{{ item.name }}</span>
            <CheckIcon v-if="item.id === modelValue" class="w-3.5 h-3.5 ml-auto text-primary-500" />
          </button>
        </template>

        <!-- No results -->
        <div v-else-if="!search" class="px-3 py-4 text-center text-xs text-slate-400 dark:text-slate-500">
          No categories found
        </div>
      </div>

      <!-- Create new -->
      <div
        v-if="search.trim() && !exactMatch"
        class="border-t border-slate-100 dark:border-slate-700"
      >
        <button
          type="button"
          :disabled="creating"
          @click="createCategory"
          :class="[
            'w-full text-left px-3 py-2.5 text-sm flex items-center gap-2 transition-colors',
            highlighted === filteredList.length
              ? 'bg-emerald-50 text-emerald-700'
              : 'text-emerald-700 hover:bg-emerald-50',
          ]"
          @mouseenter="highlighted = filteredList.length"
        >
          <LoaderIcon v-if="creating" class="w-4 h-4 animate-spin text-emerald-600 shrink-0" />
          <PlusCircleIcon v-else class="w-4 h-4 text-emerald-600 shrink-0" />
          <span>
            Create new category
            <strong>"{{ search.trim() }}"</strong>
          </span>
        </button>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, computed, watch, nextTick } from 'vue'
import { onClickOutside } from '@vueuse/core'
import { XIcon, ChevronDownIcon, CheckIcon, PlusCircleIcon, LoaderIcon } from 'lucide-vue-next'
import { useToast } from '@/Composables/useToast'

const props = defineProps({
  modelValue: { type: [Number, String], default: null },  // selected category_id
  categories: { type: Array, default: () => [] },         // [{id, name, children:[]}]
  hasError:   { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'category-created'])

const { success, error: showError } = useToast()

const wrapperRef  = ref(null)
const inputRef    = ref(null)
const isOpen      = ref(false)
const search      = ref('')
const highlighted = ref(0)
const creating    = ref(false)

// Flatten category tree → [{id, name, depth}]
const flatList = computed(() => {
  const result = []
  for (const cat of props.categories) {
    result.push({ id: cat.id, name: cat.name, depth: 0 })
    for (const child of (cat.children || [])) {
      result.push({ id: child.id, name: child.name, depth: 1 })
    }
  }
  return result
})

// Filtered by search
const filteredList = computed(() => {
  if (!search.value.trim()) return flatList.value
  const q = search.value.trim().toLowerCase()
  return flatList.value.filter(c => c.name.toLowerCase().includes(q))
})

// Check exact match (case-insensitive)
const exactMatch = computed(() =>
  flatList.value.some(c => c.name.toLowerCase() === search.value.trim().toLowerCase())
)

// Currently selected category object
const selectedCategory = computed(() =>
  flatList.value.find(c => c.id === props.modelValue) ?? null
)

// Open dropdown
function openDropdown() {
  isOpen.value = true
  highlighted.value = 0
  search.value = ''
  nextTick(() => inputRef.value?.focus())
}

function closeDropdown() {
  isOpen.value = false
  search.value = ''
}

function clearSelection() {
  emit('update:modelValue', null)
  search.value = ''
  nextTick(() => openDropdown())
}

function selectItem(item) {
  emit('update:modelValue', item.id)
  closeDropdown()
}

function onInput() {
  highlighted.value = 0
  if (!isOpen.value) isOpen.value = true
}

function onEnter() {
  const allOptions = [...filteredList.value]
  if (!exactMatch.value && search.value.trim()) {
    // "create" option is last
    if (highlighted.value === allOptions.length) {
      createCategory()
      return
    }
  }
  if (allOptions[highlighted.value]) {
    selectItem(allOptions[highlighted.value])
  }
}

function moveDown() {
  const max = filteredList.value.length + (!exactMatch.value && search.value.trim() ? 1 : 0) - 1
  highlighted.value = Math.min(highlighted.value + 1, max)
}

function moveUp() {
  highlighted.value = Math.max(highlighted.value - 1, 0)
}

// Create new category via API
async function createCategory() {
  const name = search.value.trim()
  if (!name || creating.value) return
  creating.value = true

  try {
    const res = await window.axios.post('/api/v1/categories', {
      name,
      is_active:  true,
      sort_order: 0,
    })
    const newCat = res.data
    success(`Category "${name}" created!`)
    emit('category-created', newCat)           // parent updates categories list
    emit('update:modelValue', newCat.id)        // select it
    closeDropdown()
  } catch (err) {
    showError(err.response?.data?.message || 'Failed to create category.')
  } finally {
    creating.value = false
  }
}

// Close on outside click
onClickOutside(wrapperRef, closeDropdown)
</script>
