<template>
  <Teleport to="body">
    <div class="fixed top-4 right-4 z-50 flex flex-col gap-2 w-80">
      <TransitionGroup name="toast">
        <div
          v-for="toast in toasts"
          :key="toast.id"
          :class="[
            'flex items-start gap-3 p-4 rounded-xl shadow-lg border text-sm',
            toastClasses[toast.type]
          ]"
        >
          <component :is="toastIcon(toast.type)" class="w-4 h-4 mt-0.5 shrink-0" />
          <p class="flex-1">{{ toast.message }}</p>
          <button @click="removeToast(toast.id)" class="text-current opacity-60 hover:opacity-100">
            <XIcon class="w-4 h-4" />
          </button>
        </div>
      </TransitionGroup>
    </div>
  </Teleport>
</template>

<script setup>
import { watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { CheckCircleIcon, AlertCircleIcon, InfoIcon, AlertTriangleIcon, XIcon } from 'lucide-vue-next'
import { useToast } from '@/Composables/useToast'

const page = usePage()
const { toasts, removeToast, success, error, warning, info } = useToast()

const toastClasses = {
  success: 'bg-emerald-50  dark:bg-emerald-900/30 border-emerald-200 dark:border-emerald-700 text-emerald-800 dark:text-emerald-200',
  error:   'bg-red-50      dark:bg-red-900/30     border-red-200     dark:border-red-700     text-red-800     dark:text-red-200',
  warning: 'bg-amber-50   dark:bg-amber-900/30   border-amber-200   dark:border-amber-700   text-amber-800   dark:text-amber-200',
  info:    'bg-blue-50    dark:bg-blue-900/30    border-blue-200    dark:border-blue-700    text-blue-800    dark:text-blue-200',
}

function toastIcon(type) {
  return { success: CheckCircleIcon, error: AlertCircleIcon, warning: AlertTriangleIcon, info: InfoIcon }[type] ?? InfoIcon
}

// Watch for flash messages from server (Inertia redirects with flash)
watch(
  () => page.props.flash,
  (flash) => {
    if (!flash) return
    if (flash.success) success(flash.success)
    if (flash.error)   error(flash.error)
    if (flash.warning) warning(flash.warning)
    if (flash.info)    info(flash.info)
  },
  { deep: true, immediate: true }
)
</script>

<style scoped>
.toast-enter-active,
.toast-leave-active { transition: all 0.3s ease; }
.toast-enter-from   { opacity: 0; transform: translateX(100%); }
.toast-leave-to     { opacity: 0; transform: translateX(100%); }
</style>
