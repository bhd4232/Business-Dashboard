<template>
  <div>
    <!-- Group toggle button -->
    <button
      @click="$emit('toggle')"
      :class="[
        'w-full flex items-center gap-3 py-2 rounded-lg text-sm transition-all duration-150 group',
        collapsed ? 'justify-center px-2' : 'px-3',
        isGroupActive
          ? 'text-slate-900 dark:text-slate-100 font-medium'
          : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-slate-100',
      ]"
      :title="collapsed ? label : ''"
    >
      <!-- Icon: 3D if icon3d provided, otherwise lucide -->
      <span
        :class="[
          'shrink-0 transition-transform duration-200',
          isGroupActive ? 'scale-110' : 'group-hover:scale-105',
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
          :class="['w-4 h-4 shrink-0', iconColor]"
        />
      </span>

      <!-- Label + arrow (only when expanded sidebar) -->
      <template v-if="!collapsed">
        <span class="flex-1 text-left truncate">{{ label }}</span>
        <ChevronDownIcon
          :class="['w-3.5 h-3.5 text-slate-400 dark:text-slate-500 transition-transform duration-200', open ? 'rotate-180' : '']"
        />
      </template>

      <!-- Collapsed: active dot indicator -->
      <span
        v-else-if="isGroupActive"
        class="absolute right-1 top-1 w-1.5 h-1.5 rounded-full bg-primary-500"
      ></span>
    </button>

    <!-- Sub-menu items -->
    <Transition name="slide-down">
      <div
        v-if="!collapsed && open"
        class="ml-4 pl-3 border-l border-slate-200 dark:border-slate-700 space-y-0.5 mt-0.5 mb-1"
      >
        <a
          v-for="item in items"
          :key="item.routeName"
          :href="resolveHref(item)"
          @click.prevent="navigateTo(item)"
          :class="[
            'flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs transition-all duration-150 group',
            isItemActive(item)
              ? 'bg-primary-50 dark:bg-primary-950/60 text-primary-700 dark:text-primary-300 font-semibold'
              : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-slate-100',
          ]"
        >
          <!-- Dot -->
          <span
            :class="[
              'w-1.5 h-1.5 rounded-full shrink-0 transition-colors',
              isItemActive(item)
                ? 'bg-primary-500'
                : 'bg-slate-300 dark:bg-slate-600 group-hover:bg-slate-500 dark:group-hover:bg-slate-400',
            ]"
          ></span>

          <span class="truncate flex-1">{{ item.label }}</span>

          <!-- Coming soon badge -->
          <span
            v-if="item.comingSoon"
            class="ml-auto text-[9px] font-semibold bg-slate-100 dark:bg-slate-700 text-slate-400 dark:text-slate-500 px-1.5 py-0.5 rounded-full"
          >
            Soon
          </span>
        </a>
      </div>
    </Transition>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { ChevronDownIcon } from 'lucide-vue-next'
import * as icons from 'lucide-vue-next'
import ThreeDIcon from '@/Components/UI/ThreeDIcon.vue'

const props = defineProps({
  label:     { type: String,  required: true },
  icon:      { type: String,  required: true },
  icon3d:    { type: String,  default: null },   // Icons8 Fluency key
  iconColor: { type: String,  default: 'text-slate-500' },
  groupKey:  { type: String,  required: true },
  open:      { type: Boolean, default: false },
  collapsed: { type: Boolean, default: false },
  items:     { type: Array,   default: () => [] },
})

defineEmits(['toggle'])

const page = usePage()

const iconComp = computed(() => {
  return icons[props.icon + 'Icon'] || icons[props.icon] || icons.CircleIcon
})

const isGroupActive = computed(() =>
  props.items.some(item => isItemActive(item))
)

function resolveHref(item) {
  try { return route(item.routeName) } catch { return '#' }
}

function isItemActive(item) {
  try {
    const r = route(item.routeName)
    return page.url === r || page.url.startsWith(r + '/')
  } catch { return false }
}

function navigateTo(item) {
  if (item.comingSoon) return
  try { router.visit(route(item.routeName)) } catch {}
}
</script>
