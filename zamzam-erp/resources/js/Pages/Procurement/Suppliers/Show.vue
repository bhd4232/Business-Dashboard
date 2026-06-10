<template>
  <AppLayout>
    <Head :title="supplier.name_english" />

    <!-- Back + Header -->
    <div class="mb-6">
      <BackButton label="Suppliers" to="suppliers.index" />
      <div class="flex items-start justify-between">
        <div>
          <h1 class="text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ supplier.name_english }}</h1>
          <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5 font-mono">{{ supplier.name_chinese }}</p>
        </div>
        <div class="flex gap-2">
          <Link :href="route('suppliers.edit', supplier.id)"
            class="inline-flex items-center gap-2 border border-slate-300 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700/50 text-slate-700 dark:text-slate-300 text-sm font-medium px-4 py-2 rounded-lg transition-colors">
            <PencilIcon class="w-4 h-4" /> Edit
          </Link>
        </div>
      </div>
    </div>

    <!-- Header Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
      <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-4">
        <p class="text-xs text-slate-500 dark:text-slate-400 mb-1">City</p>
        <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">{{ supplier.city || '—' }}</p>
      </div>
      <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-4">
        <p class="text-xs text-slate-500 dark:text-slate-400 mb-1">WeChat</p>
        <p class="text-sm font-semibold text-slate-800 dark:text-slate-200 font-mono">{{ supplier.wechat_id || '—' }}</p>
      </div>
      <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-4">
        <p class="text-xs text-slate-500 dark:text-slate-400 mb-1">Rating</p>
        <div class="flex gap-0.5 mt-1">
          <StarIcon v-for="i in 5" :key="i" class="w-4 h-4"
            :class="i <= (supplier.rating || 0) ? 'text-amber-400 fill-amber-400' : 'text-slate-200 fill-slate-200 dark:text-slate-600 dark:fill-slate-600'" />
        </div>
      </div>
      <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-4">
        <p class="text-xs text-slate-500 dark:text-slate-400 mb-1">Status</p>
        <span :class="supplier.is_active ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300'"
          class="rounded-full px-2.5 py-0.5 text-xs font-medium">
          {{ supplier.is_active ? 'Active' : 'Inactive' }}
        </span>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
      <!-- Main Info -->
      <div class="lg:col-span-2 space-y-4">
        <!-- Contacts -->
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700">
          <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center gap-2">
            <UsersIcon class="w-4 h-4 text-purple-600" />
            <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-200">Contact Persons</h2>
          </div>
          <div class="p-4">
            <div v-if="!supplier.contacts?.length" class="text-center py-4 text-slate-400 text-sm">
              No contacts added
            </div>
            <div v-for="contact in supplier.contacts" :key="contact.id"
              class="flex items-start gap-3 p-3 rounded-lg border border-slate-100 dark:border-slate-700 mb-2 last:mb-0">
              <div class="w-8 h-8 rounded-full bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center shrink-0">
                <span class="text-purple-700 dark:text-purple-300 text-xs font-semibold">{{ contact.name[0] }}</span>
              </div>
              <div class="flex-1">
                <div class="flex items-center gap-2">
                  <p class="text-sm font-medium text-slate-900 dark:text-slate-100">{{ contact.name }}</p>
                  <span v-if="contact.is_primary"
                    class="bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300 text-xs px-1.5 py-0.5 rounded-full">Primary</span>
                </div>
                <p v-if="contact.phone" class="text-xs text-slate-500 dark:text-slate-400 font-mono">{{ contact.phone }}</p>
                <p v-if="contact.wechat_id" class="text-xs text-slate-500 dark:text-slate-400">WeChat: {{ contact.wechat_id }}</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Recent POs -->
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700">
          <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
            <div class="flex items-center gap-2">
              <ClipboardListIcon class="w-4 h-4 text-purple-600" />
              <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-200">Recent Purchase Orders</h2>
            </div>
            <Link :href="route('purchase-orders.index') + '?supplier_id=' + supplier.id"
              class="text-xs text-indigo-600 hover:text-indigo-700 dark:text-primary-400 dark:hover:text-primary-300">View All →</Link>
          </div>
          <div class="p-4">
            <div v-if="!supplier.purchase_orders?.length" class="text-center py-4 text-slate-400 text-sm">
              No orders yet
            </div>
            <div v-for="po in supplier.purchase_orders" :key="po.id"
              class="flex items-center justify-between py-2 border-b border-slate-100 dark:border-slate-700 last:border-0">
              <div>
                <Link :href="route('purchase-orders.show', po.id)"
                  class="text-sm font-mono font-medium text-indigo-600 hover:text-indigo-700 dark:text-primary-400 dark:hover:text-primary-300">{{ po.po_number }}</Link>
                <p class="text-xs text-slate-500 dark:text-slate-400">{{ po.order_date }}</p>
              </div>
              <div class="text-right">
                <p class="text-sm font-medium text-slate-800 dark:text-slate-200 font-mono">¥{{ Number(po.total_cny).toLocaleString() }}</p>
                <span :class="statusColor(po.status)"
                  class="rounded-full px-2 py-0.5 text-xs font-medium">{{ po.status }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Side Info -->
      <div class="space-y-4">
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-4">
          <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-200 mb-3">Details</h3>
          <dl class="space-y-2">
            <div v-if="supplier.phone">
              <dt class="text-xs text-slate-500 dark:text-slate-400">Phone</dt>
              <dd class="text-sm text-slate-800 dark:text-slate-200 font-mono">{{ supplier.phone }}</dd>
            </div>
            <div v-if="supplier.email">
              <dt class="text-xs text-slate-500 dark:text-slate-400">Email</dt>
              <dd class="text-sm text-slate-800 dark:text-slate-200">{{ supplier.email }}</dd>
            </div>
            <div v-if="supplier.address">
              <dt class="text-xs text-slate-500 dark:text-slate-400">Address</dt>
              <dd class="text-sm text-slate-800 dark:text-slate-200">{{ supplier.address }}</dd>
            </div>
            <div v-if="supplier.payment_terms">
              <dt class="text-xs text-slate-500 dark:text-slate-400">Payment Terms</dt>
              <dd class="text-sm text-slate-800 dark:text-slate-200">{{ supplier.payment_terms }}</dd>
            </div>
            <div v-if="supplier.notes">
              <dt class="text-xs text-slate-500 dark:text-slate-400">Notes</dt>
              <dd class="text-sm text-slate-800 dark:text-slate-200">{{ supplier.notes }}</dd>
            </div>
          </dl>
        </div>
      </div>
    </div>

  </AppLayout>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3'
import { ChevronLeftIcon, PencilIcon, StarIcon, UsersIcon, ClipboardListIcon } from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'
import BackButton from '@/Components/UI/BackButton.vue'

const props = defineProps({
  supplier: { type: Object, required: true },
})

const statusColors = {
  draft: 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300',
  confirmed: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
  shipped: 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300',
  received: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
  completed: 'bg-green-100 text-green-700',
  cancelled: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300',
}

function statusColor(status) {
  return statusColors[status] || 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300'
}
</script>
