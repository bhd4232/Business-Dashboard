import { ref } from 'vue'

// Shared reactive state — singleton across the app
const toasts = ref([])
let nextId = 0

function addToast(type, message, duration = 5000) {
  const id = ++nextId
  toasts.value.push({ id, type, message })
  setTimeout(() => removeToast(id), duration)
}

function removeToast(id) {
  toasts.value = toasts.value.filter(t => t.id !== id)
}

export function useToast() {
  return {
    toasts,
    removeToast,
    success: (msg) => addToast('success', msg),
    error:   (msg) => addToast('error',   msg),
    warning: (msg) => addToast('warning', msg),
    info:    (msg) => addToast('info',    msg),
  }
}
