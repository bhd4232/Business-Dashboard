<template>
  <component
    :is="iconComponent"
    :class="[sizeClass, colorClass, 'icon-3d shrink-0']"
  />
</template>

<script setup>
import { computed } from 'vue'
import * as icons from 'lucide-vue-next'

const props = defineProps({
  /** Lucide icon name — PascalCase without "Icon" suffix, e.g. "Edit", "Trash2", "Save" */
  name:  { type: String, required: true },
  /** xs=w-3 h-3 | sm=w-4 h-4 | md=w-5 h-5 | lg=w-6 h-6 */
  size:  { type: String, default: 'sm', validator: v => ['xs', 'sm', 'md', 'lg'].includes(v) },
  /** Any Tailwind text-color class, e.g. "text-primary-600", "text-slate-500" */
  color: { type: String, default: 'text-primary-600' },
})

const iconComponent = computed(() => {
  // Try PascalCase + "Icon" suffix first, then bare name, then fallback
  return icons[props.name + 'Icon']
    ?? icons[props.name]
    ?? icons.CircleIcon
})

const sizeClass = computed(() => ({
  xs: 'w-3 h-3',
  sm: 'w-4 h-4',
  md: 'w-5 h-5',
  lg: 'w-6 h-6',
}[props.size]))

const colorClass = computed(() => props.color)
</script>
