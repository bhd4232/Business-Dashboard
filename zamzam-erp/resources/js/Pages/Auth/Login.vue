<template>
  <div class="min-h-screen bg-slate-50 flex items-center justify-center p-4">
    <div class="w-full max-w-md">

      <!-- Logo -->
      <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-14 h-14 bg-indigo-600 rounded-2xl mb-4 shadow-lg">
          <span class="text-white text-2xl font-bold">Z</span>
        </div>
        <h1 class="text-2xl font-bold text-slate-900">ZamZam ERP</h1>
        <p class="text-slate-500 text-sm mt-1">China → Bangladesh Wholesale System</p>
      </div>

      <!-- Card -->
      <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-8">
        <h2 class="text-xl font-semibold text-slate-900 mb-6">Sign In</h2>

        <!-- Status message -->
        <div v-if="status" class="mb-4 p-3 bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm rounded-lg">
          {{ status }}
        </div>

        <form @submit.prevent="submit" class="space-y-4">

          <!-- Login (email or phone) -->
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">
              Email or Phone
              <span class="text-red-500">*</span>
            </label>
            <input
              v-model="form.login"
              type="text"
              placeholder="example@email.com or 01XXXXXXXXX"
              autocomplete="username"
              :class="[
                'w-full px-3 py-2.5 text-sm rounded-lg border transition-all',
                form.errors.login
                  ? 'border-red-400 focus:border-red-500 focus:ring-2 focus:ring-red-100'
                  : 'border-slate-300 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100'
              ]"
            />
            <p v-if="form.errors.login" class="mt-1 text-xs text-red-600 flex items-center gap-1">
              <AlertCircleIcon class="w-3 h-3" />
              {{ form.errors.login }}
            </p>
          </div>

          <!-- Password -->
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">
              Password
              <span class="text-red-500">*</span>
            </label>
            <div class="relative">
              <input
                v-model="form.password"
                :type="showPassword ? 'text' : 'password'"
                placeholder="••••••••"
                autocomplete="current-password"
                :class="[
                  'w-full px-3 py-2.5 text-sm rounded-lg border pr-10 transition-all',
                  form.errors.password
                    ? 'border-red-400 focus:border-red-500 focus:ring-2 focus:ring-red-100'
                    : 'border-slate-300 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100'
                ]"
              />
              <button
                type="button"
                @click="showPassword = !showPassword"
                class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
              >
                <EyeOffIcon v-if="showPassword" class="w-4 h-4" />
                <EyeIcon    v-else              class="w-4 h-4" />
              </button>
            </div>
            <p v-if="form.errors.password" class="mt-1 text-xs text-red-600 flex items-center gap-1">
              <AlertCircleIcon class="w-3 h-3" />
              {{ form.errors.password }}
            </p>
          </div>

          <!-- Remember me -->
          <div class="flex items-center justify-between">
            <label class="flex items-center gap-2 cursor-pointer">
              <input
                v-model="form.remember"
                type="checkbox"
                class="w-4 h-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
              />
              <span class="text-sm text-slate-600">Remember me</span>
            </label>
          </div>

          <!-- Submit -->
          <button
            type="submit"
            :disabled="form.processing"
            class="w-full py-2.5 px-4 bg-indigo-600 hover:bg-indigo-700 disabled:bg-indigo-400 text-white text-sm font-medium rounded-lg transition-colors flex items-center justify-center gap-2"
          >
            <Loader2Icon v-if="form.processing" class="w-4 h-4 animate-spin" />
            <span>{{ form.processing ? 'Signing in...' : 'Sign In' }}</span>
          </button>

        </form>
      </div>

      <!-- Footer -->
      <p class="text-center text-xs text-slate-400 mt-6">
        ZamZam International &copy; {{ new Date().getFullYear() }}
      </p>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useForm, Head } from '@inertiajs/vue3'
import { EyeIcon, EyeOffIcon, AlertCircleIcon, Loader2Icon } from 'lucide-vue-next'

defineProps({
  status:           { type: String,  default: null },
  canResetPassword: { type: Boolean, default: false },
})

const showPassword = ref(false)

const form = useForm({
  login:    '',
  password: '',
  remember: false,
})

function submit() {
  form.post(route('login.submit'), {
    onFinish: () => form.reset('password'),
  })
}
</script>
