<template>
  <!-- Single nav link -->
  <a
    :href="resolvedHref"
    @click.prevent="navigate"
    :class="[
      'flex items-center gap-3 py-2 rounded-lg text-sm transition-all duration-150 group relative',
      collapsed ? 'justify-center px-2' : 'px-3',
      isActive
        ? 'bg-primary-50 dark:bg-primary-950/60 text-primary-700 dark:text-primary-300 font-medium'
        : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-slate-100',
    ]"
    :title="collapsed ? label : ''"
  >
    <!-- Active left bar -->
    <span
      v-if="isActive"
      class="absolute left-0 top-1 bottom-1 w-0.5 bg-primary-600 rounded-r"
    ></span>

    <!-- Icon: 3D if icon3d provided, otherwise lucide -->
    <span
      :class="[
        'shrink-0 transition-transform duration-200',
        isActive ? 'scale-110' : 'group-hover:scale-105',
      ]"
    >
      <ThreeDIcon
        v-if="icon3d"
        :name="icon3d"
        size="sm"
        :animate="false"
      />
      <component
        v-else
        :is="iconComp"
        :class="['w-4 h-4', isActive ? 'text-primary-600 dark:text-primary-400' : iconColor]"
      />
    </span>

    <!-- Label -->
    <span v-if="!collapsed" class="truncate flex-1">{{ label }}</span>

    <!-- Coming soon badge -->
    <span
      v-if="!collapsed && comingSoon"
      class="ml-auto text-[9px] font-semibold bg-slate-100 dark:bg-slate-700 text-slate-400 dark:text-slate-500 px-1.5 py-0.5 rounded-full"
    >
      Soon
    </span>

    <!-- Badge -->
    <span
      v-else-if="!collapsed && badge"
      class="ml-auto bg-primary-100 dark:bg-primary-900/40 text-primary-700 dark:text-primary-300 text-xs font-bold px-1.5 py-0.5 rounded-full"
    >
      {{ badge }}
    </span>
  </a>
</template>

<script setup>
import { computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import * as icons from 'lucide-vue-next'
import ThreeDIcon from '@/Components/UI/ThreeDIcon.vue'

const props = defineProps({
  label:      { type: String,  required: true },
  icon:       { type: String,  required: true },
  icon3d:     { type: String,  default: null },    // Icons8 Fluency key
  routeName:  { type: String,  required: true },
  iconColor:  { type: String,  default: 'text-slate-500' },
  collapsed:  { type: Boolean, default: false },
  badge:      { type: [String, Number], default: null },
  comingSoon: { type: Boolean, default: false },
})

const page = usePage()

const iconComp = computed(() => {
  return icons[props.icon + 'Icon'] || icons[props.icon] || icons.CircleIcon
})

const resolvedHref = computed(() => {
  try { return route(props.routeName) } catch { return '#' }
})

const isActive = computed(() => {
  try {
    const r = route(props.routeName)
    return page.url === r || page.url.startsWith(r + '/')
  } catch { return false }
})

function navigate() {
  if (props.comingSoon) return
  try { router.visit(route(props.routeName)) } catch {}
}
</script>
