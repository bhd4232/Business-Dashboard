<template>
  <button
    @click="navigate"
    class="flex items-center gap-3 w-full px-3 py-2.5 rounded-lg hover:bg-primary-50 dark:hover:bg-primary-900/20 group transition-colors text-left"
  >
    <div class="w-8 h-8 bg-primary-100 dark:bg-primary-900/30 rounded-lg flex items-center justify-center shrink-0">
      <component :is="iconComponent" class="w-4 h-4 text-primary-600 dark:text-primary-400" />
    </div>
    <span class="text-sm font-medium text-slate-700 dark:text-slate-300 group-hover:text-primary-700 dark:group-hover:text-primary-300">{{ label }}</span>
  </button>
</template>

<script setup>
import { computed } from 'vue'
import { router } from '@inertiajs/vue3'
import * as icons from 'lucide-vue-next'

const props = defineProps({
  label: { type: String, required: true },
  icon:  { type: String, default: 'Circle' },
  route: { type: String, required: true },
  color: { type: String, default: 'indigo' }, // kept for backward compat
})

const iconComponent = computed(() => icons[props.icon] || icons.Circle)

function navigate() {
  try { router.visit(window.route(props.route)) } catch { /* route not defined yet */ }
}
</script>
