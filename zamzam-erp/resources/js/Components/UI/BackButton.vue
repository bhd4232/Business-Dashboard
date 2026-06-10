<template>
  <!-- If `to` route provided → Inertia Link (no page reload) -->
  <!-- If `@click` used → emit click event (for local state toggle) -->
  <component
    :is="to ? Link : 'button'"
    v-bind="to ? { href: resolvedHref } : { type: 'button' }"
    @click="!to ? $emit('click') : undefined"
    class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 text-sm font-medium hover:bg-slate-50 dark:hover:bg-slate-700 hover:text-slate-900 dark:hover:text-slate-100 hover:border-slate-300 dark:hover:border-slate-600 transition-all shadow-sm group"
  >
    <ArrowLeftIcon class="w-4 h-4 group-hover:-translate-x-0.5 transition-transform" />
    <span>{{ label }}</span>
  </component>
</template>

<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { ArrowLeftIcon } from 'lucide-vue-next'

const props = defineProps({
  label: { type: String, default: 'Back' },
  to:    { type: String, default: null },   // route name e.g. 'suppliers.index'
})

defineEmits(['click'])

const resolvedHref = computed(() => {
  if (!props.to) return '#'
  try { return route(props.to) } catch { return '#' }
})
</script>
