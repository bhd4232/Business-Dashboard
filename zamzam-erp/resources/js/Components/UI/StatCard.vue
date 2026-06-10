<template>
  <div
    class="group relative bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-100 dark:border-slate-700 p-5
           hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 overflow-hidden"
  >
    <!-- Subtle gradient accent top-left corner -->
    <div
      class="absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity duration-300
             bg-gradient-to-br from-primary-50/60 dark:from-primary-900/20 to-transparent pointer-events-none"
    />

    <div class="relative flex items-start justify-between mb-3">
      <!-- Icon: 3D if icon3d provided, otherwise lucide -->
      <div
        :class="[
          'transition-transform duration-300 group-hover:scale-110',
          !icon3d ? 'w-10 h-10 rounded-lg bg-primary-50 dark:bg-primary-900/30 flex items-center justify-center' : '',
        ]"
      >
        <ThreeDIcon
          v-if="icon3d"
          :name="icon3d"
          size="lg"
          :animate="false"
        />
        <component
          v-else
          :is="iconComponent"
          class="w-5 h-5 text-primary-600 dark:text-primary-400"
        />
      </div>

      <!-- Trend badge -->
      <span
        v-if="trend !== null"
        :class="trend >= 0
          ? 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300'
          : 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300'"
        class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium"
      >
        {{ trend >= 0 ? '▲' : '▼' }} {{ Math.abs(trend) }}%
      </span>
    </div>

    <p class="relative text-2xl font-bold text-slate-900 dark:text-slate-100 font-mono">{{ value }}</p>
    <p class="relative text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ label }}</p>

    <!-- Progress bar -->
    <div v-if="progress !== null" class="relative mt-3">
      <div class="h-1.5 bg-slate-100 dark:bg-slate-700 rounded-full overflow-hidden">
        <div
          class="h-full bg-primary-500 rounded-full transition-all duration-700"
          :style="{ width: `${Math.min(100, progress)}%` }"
        ></div>
      </div>
      <p v-if="subLabel" class="text-xs text-slate-400 dark:text-slate-500 mt-1">{{ subLabel }}</p>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import * as icons from 'lucide-vue-next'
import ThreeDIcon from '@/Components/UI/ThreeDIcon.vue'

const props = defineProps({
  label:    { type: String,           required: true },
  value:    { type: [String, Number], required: true },
  icon:     { type: String,           default: 'Circle' },
  icon3d:   { type: String,           default: null },   // Icons8 Fluency key
  color:    { type: String,           default: 'indigo' }, // kept for backward compat
  trend:    { type: Number,           default: null },
  progress: { type: Number,           default: null },
  subLabel: { type: String,           default: null },
})

const iconComponent = computed(() => icons[props.icon + 'Icon'] || icons[props.icon] || icons.CircleIcon)
</script>
