<template>
  <div class="flex h-screen bg-slate-50 dark:bg-slate-900 overflow-hidden">

    <!-- Sidebar -->
    <AppSidebar :collapsed="sidebarCollapsed" @toggle="sidebarCollapsed = !sidebarCollapsed" />

    <!-- Main content -->
    <div class="flex-1 flex flex-col min-w-0 overflow-hidden">

      <!-- Top Bar -->
      <AppTopBar @toggle-sidebar="sidebarCollapsed = !sidebarCollapsed" />

      <!-- Page content -->
      <Transition name="page-fade" mode="out-in">
        <main :key="$page.url" class="flex-1 overflow-y-auto">
          <div class="p-6 max-w-[1600px] mx-auto">
            <slot />
          </div>
        </main>
      </Transition>

    </div>
  </div>

  <!-- Toast Notifications -->
  <ToastContainer />
</template>

<script setup>
import { ref } from 'vue'
import AppSidebar from '@/Components/UI/AppSidebar.vue'
import AppTopBar  from '@/Components/UI/AppTopBar.vue'
import ToastContainer from '@/Components/UI/ToastContainer.vue'

const sidebarCollapsed = ref(false)
</script>
