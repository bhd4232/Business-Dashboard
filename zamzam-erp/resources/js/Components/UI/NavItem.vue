<template>
  <a
    :href="href"
    @click.prevent="navigate"
    :class="[
      'flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-all duration-150 group',
      isActive
        ? 'bg-primary-50 dark:bg-primary-950/60 text-primary-700 dark:text-primary-300 border-l-2 border-primary-600'
        : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-slate-100 border-l-2 border-transparent',
      collapsed ? 'justify-center px-2' : ''
    ]"
    :title="collapsed ? item.label : ''"
  >
    <!-- Icon -->
    <component :is="iconComponent" class="w-4 h-4 shrink-0" />

    <!-- Label (hidden when collapsed) -->
    <span v-if="!collapsed" class="truncate">{{ item.label }}</span>

    <!-- Badge -->
    <span
      v-if="!collapsed && item.badge"
      class="ml-auto bg-primary-100 dark:bg-primary-900/40 text-primary-700 dark:text-primary-300 text-xs font-medium px-1.5 py-0.5 rounded-full"
    >
      {{ item.badge }}
    </span>
  </a>
</template>

<script setup>
import { computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import * as icons from 'lucide-vue-next'

const props = defineProps({
  item:      { type: Object,  required: true },
  collapsed: { type: Boolean, default: false },
})

const page = usePage()

const iconComponent = computed(() => icons[props.item.icon] || icons.Circle)

const href = computed(() => {
  try { return route(props.item.route) } catch { return '#' }
})

const isActive = computed(() => {
  try { return page.url.startsWith('/' + props.item.route.replace('.', '/').replace('index', '')) } catch { return false }
})

function navigate() {
  try { router.visit(route(props.item.route)) } catch { /* route not yet defined */ }
}
</script>
