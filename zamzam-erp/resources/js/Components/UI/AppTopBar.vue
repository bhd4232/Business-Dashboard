<template>
  <header class="h-14 bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-700 flex items-center px-4 gap-4 shrink-0 z-10">

    <!-- Mobile: Sidebar toggle -->
    <button
      @click="$emit('toggle-sidebar')"
      class="p-1.5 rounded-lg text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-700 dark:hover:text-slate-200 transition-colors lg:hidden"
    >
      <MenuIcon class="w-5 h-5" />
    </button>

    <!-- Search -->
    <div class="flex-1 max-w-md">
      <div class="relative">
        <SearchIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 dark:text-slate-500" />
        <input
          type="text"
          placeholder="Search... (Ctrl+K)"
          class="w-full pl-9 pr-4 py-2 text-sm bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-600 dark:text-slate-100 rounded-lg focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-100 dark:focus:ring-primary-900/30 transition-all placeholder:text-slate-400 dark:placeholder:text-slate-500"
          @keydown.ctrl.k.prevent="openSearch"
        />
      </div>
    </div>

    <!-- Right: Dark toggle + Notifications + User -->
    <div class="flex items-center gap-2 ml-auto">

      <!-- Dark mode toggle -->
      <button
        @click="toggleDarkMode()"
        class="p-2 rounded-lg text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-700 dark:hover:text-slate-200 transition-colors"
        :title="themeStore.dark_mode ? 'Switch to light mode' : 'Switch to dark mode'"
      >
        <SunIcon v-if="themeStore.dark_mode" class="w-5 h-5 icon-3d" />
        <MoonIcon v-else class="w-5 h-5 icon-3d" />
      </button>

      <!-- Notifications -->
      <button class="relative p-2 rounded-lg text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-700 dark:hover:text-slate-200 transition-colors">
        <BellIcon class="w-5 h-5" />
        <span class="absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full"></span>
      </button>

      <!-- Messages -->
      <button class="relative p-2 rounded-lg text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-700 dark:hover:text-slate-200 transition-colors">
        <MessageSquareIcon class="w-5 h-5" />
      </button>

      <!-- User dropdown -->
      <div class="relative" ref="userMenuRef">
        <button
          @click="userMenuOpen = !userMenuOpen"
          class="flex items-center gap-2 px-3 py-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors text-sm"
        >
          <!-- Avatar -->
          <div class="w-7 h-7 rounded-full bg-primary-600 flex items-center justify-center text-white text-xs font-semibold">
            {{ userInitial }}
          </div>
          <span class="font-medium text-slate-700 dark:text-slate-200 hidden sm:block">{{ user?.name }}</span>
          <ChevronDownIcon class="w-3.5 h-3.5 text-slate-400 dark:text-slate-500" />
        </button>

        <!-- Dropdown -->
        <div
          v-if="userMenuOpen"
          class="absolute right-0 top-full mt-1 w-52 bg-white dark:bg-slate-800 rounded-xl shadow-lg border border-slate-200 dark:border-slate-700 py-1 z-50"
        >
          <div class="px-4 py-2 border-b border-slate-100 dark:border-slate-700">
            <p class="text-sm font-medium text-slate-900 dark:text-slate-100">{{ user?.name }}</p>
            <p class="text-xs text-slate-500 dark:text-slate-400">{{ user?.email }}</p>
            <span class="inline-block mt-1 text-xs bg-primary-100 dark:bg-primary-900/40 text-primary-700 dark:text-primary-300 px-2 py-0.5 rounded-full">
              {{ user?.roles?.[0] }}
            </span>
          </div>
          <a href="#" class="flex items-center gap-2 px-4 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700">
            <UserIcon class="w-4 h-4" /> Profile
          </a>
          <Link :href="route('settings.index')" class="flex items-center gap-2 px-4 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700">
            <SettingsIcon class="w-4 h-4" /> Settings
          </Link>
          <div class="border-t border-slate-100 dark:border-slate-700 mt-1 pt-1">
            <button
              @click="logout"
              class="flex items-center gap-2 w-full px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20"
            >
              <LogOutIcon class="w-4 h-4" /> Logout
            </button>
          </div>
        </div>
      </div>

    </div>
  </header>
</template>

<script setup>
import { ref, computed } from 'vue'
import { usePage, router, Link } from '@inertiajs/vue3'
import { onClickOutside } from '@vueuse/core'
import {
  MenuIcon, SearchIcon, BellIcon, MessageSquareIcon,
  ChevronDownIcon, UserIcon, SettingsIcon, LogOutIcon,
  SunIcon, MoonIcon,
} from 'lucide-vue-next'
import { useThemeStore } from '@/Stores/useThemeStore'

defineEmits(['toggle-sidebar'])

const page         = usePage()
const user         = computed(() => page.props.auth?.user)
const userInitial  = computed(() => user.value?.name?.[0]?.toUpperCase() ?? 'U')
const userMenuOpen = ref(false)
const userMenuRef  = ref(null)
const themeStore   = useThemeStore()

onClickOutside(userMenuRef, () => { userMenuOpen.value = false })

function openSearch() { /* TODO: command palette */ }

async function toggleDarkMode() {
  themeStore.toggleDark()
  // Auto-save dark mode preference immediately
  try {
    await window.axios.put('/api/v1/user/preferences', {
      dark_mode: themeStore.dark_mode,
    })
  } catch {
    // Silent fail — preference saved in-memory even if API fails
  }
}

function logout() {
  router.post(route('logout'))
}
</script>
