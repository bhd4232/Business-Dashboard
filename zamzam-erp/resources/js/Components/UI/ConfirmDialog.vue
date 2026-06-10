<template>
  <Teleport to="body">
    <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4">
      <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-xl w-full max-w-sm p-6">
        <div class="flex items-center gap-3 mb-4">
          <div :class="iconBg" class="w-10 h-10 rounded-full flex items-center justify-center shrink-0">
            <component :is="iconComponent" class="w-5 h-5" :class="iconColor" />
          </div>
          <div>
            <h3 class="text-base font-semibold text-slate-900 dark:text-slate-100">{{ title }}</h3>
            <p v-if="description" class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">{{ description }}</p>
          </div>
        </div>
        <div class="flex gap-2 justify-end">
          <button @click="$emit('cancel')"
            class="px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 border border-slate-300 dark:border-slate-600 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">
            Cancel
          </button>
          <button @click="$emit('confirm')" :disabled="loading" :class="confirmClass"
            class="px-4 py-2 text-sm font-medium text-white rounded-lg transition-colors disabled:opacity-50 flex items-center gap-1.5">
            <LoaderIcon v-if="loading" class="w-3.5 h-3.5 animate-spin" />
            {{ loading ? 'Processing...' : confirmText }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { computed } from 'vue'
import { TrashIcon, AlertTriangleIcon, LoaderIcon } from 'lucide-vue-next'

const props = defineProps({
  show:        { type: Boolean, default: false },
  title:       { type: String,  default: 'Are you sure?' },
  description: { type: String,  default: '' },
  confirmText: { type: String,  default: 'Confirm' },
  variant:     { type: String,  default: 'danger' },
  loading:     { type: Boolean, default: false },
})

defineEmits(['confirm', 'cancel'])

const iconBg        = computed(() => props.variant === 'danger' ? 'bg-red-100' : 'bg-amber-100')
const iconColor     = computed(() => props.variant === 'danger' ? 'text-red-600' : 'text-amber-600')
const iconComponent = computed(() => props.variant === 'danger' ? TrashIcon : AlertTriangleIcon)
const confirmClass  = computed(() => props.variant === 'danger' ? 'bg-red-600 hover:bg-red-700' : 'bg-amber-500 hover:bg-amber-600')
</script>
