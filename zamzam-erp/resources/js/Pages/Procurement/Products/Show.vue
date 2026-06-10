<template>
  <AppLayout>
    <Head :title="product.name" />

    <!-- Header -->
    <div class="mb-6">
      <div class="flex items-center gap-4 mb-4">
        <BackButton label="Back to Products" to="products.index" />
      </div>
      <div class="flex items-start justify-between">
        <div>
          <div class="flex items-center gap-3">
            <h1 class="text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ product.name }}</h1>
            <span v-if="product.name_chinese" class="text-slate-400 dark:text-slate-500 text-sm font-mono">{{ product.name_chinese }}</span>
            <span
              :class="product.is_active ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300'"
              class="px-2.5 py-0.5 rounded-full text-xs font-medium"
            >
              {{ product.is_active ? 'Active' : 'Inactive' }}
            </span>
          </div>
          <p class="text-sm text-slate-500 dark:text-slate-400 mt-1 font-mono">SKU: {{ product.sku }}</p>
        </div>
        <Link
          :href="route('products.edit', product.id)"
          class="inline-flex items-center gap-2 border border-slate-300 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700/50 text-slate-700 dark:text-slate-300 text-sm font-medium px-4 py-2 rounded-lg transition-colors"
        >
          <PencilIcon class="w-4 h-4" /> Edit
        </Link>
      </div>
    </div>

    <!-- Info Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
      <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-4">
        <p class="text-xs text-slate-500 dark:text-slate-400 mb-1">Category</p>
        <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">{{ product.category?.name ?? '—' }}</p>
      </div>
      <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-4">
        <p class="text-xs text-slate-500 dark:text-slate-400 mb-1">Unit</p>
        <p class="text-sm font-semibold text-slate-800 dark:text-slate-200 capitalize">{{ product.unit ?? '—' }}</p>
      </div>
      <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-4">
        <p class="text-xs text-slate-500 dark:text-slate-400 mb-1">Min Stock Alert</p>
        <p class="text-sm font-semibold" :class="product.min_stock_alert > 0 ? 'text-amber-600' : 'text-slate-800 dark:text-slate-200'">
          {{ product.min_stock_alert ?? 0 }} units
        </p>
      </div>
      <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-4">
        <p class="text-xs text-slate-500 dark:text-slate-400 mb-1">Variants</p>
        <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">
          {{ product.has_variants ? (product.variants?.length ?? 0) + ' variants' : 'No variants' }}
        </p>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

      <!-- Left: Details + Variants -->
      <div class="lg:col-span-2 space-y-4">

        <!-- Description -->
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-5">
          <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-200 mb-3">Product Details</h2>
          <dl class="grid grid-cols-2 gap-3">
            <div>
              <dt class="text-xs text-slate-500 dark:text-slate-400">Weight</dt>
              <dd class="text-sm text-slate-800 dark:text-slate-200 font-mono">{{ product.weight_kg ? product.weight_kg + ' kg' : '—' }}</dd>
            </div>
            <div>
              <dt class="text-xs text-slate-500 dark:text-slate-400">Volume</dt>
              <dd class="text-sm text-slate-800 dark:text-slate-200 font-mono">{{ product.volume_cm3 ? product.volume_cm3 + ' cm³' : '—' }}</dd>
            </div>
            <div>
              <dt class="text-xs text-slate-500 dark:text-slate-400">Barcode</dt>
              <dd class="text-sm text-slate-800 dark:text-slate-200 font-mono">{{ product.barcode || '—' }}</dd>
            </div>
            <div>
              <dt class="text-xs text-slate-500 dark:text-slate-400">Has Variants</dt>
              <dd class="text-sm text-slate-800 dark:text-slate-200">{{ product.has_variants ? 'Yes' : 'No' }}</dd>
            </div>
            <div v-if="product.description" class="col-span-2">
              <dt class="text-xs text-slate-500 dark:text-slate-400 mb-1">Description</dt>
              <dd class="text-sm text-slate-700 dark:text-slate-300 leading-relaxed">{{ product.description }}</dd>
            </div>
          </dl>
        </div>

        <!-- Variants -->
        <div v-if="product.has_variants" class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
          <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700 flex items-center gap-2">
            <LayersIcon class="w-4 h-4 text-amber-600" />
            <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-200">Variants</h2>
            <span class="ml-auto bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 text-xs font-medium px-2 py-0.5 rounded-full">
              {{ product.variants?.length ?? 0 }}
            </span>
          </div>
          <div v-if="!product.variants?.length" class="p-6 text-center text-slate-400 text-sm">
            No variants added yet
          </div>
          <table v-else class="w-full">
            <thead>
              <tr class="bg-slate-50 dark:bg-slate-700/50 border-b border-slate-100 dark:border-slate-700 text-xs font-medium text-slate-500 dark:text-slate-400 uppercase">
                <th class="text-left px-6 py-3">Variant</th>
                <th class="text-left px-6 py-3">SKU</th>
                <th class="text-left px-6 py-3">Weight</th>
                <th class="text-left px-6 py-3">Status</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
              <tr v-for="v in product.variants" :key="v.id" class="hover:bg-slate-50 dark:hover:bg-slate-700/50">
                <td class="px-6 py-3 text-sm font-medium text-slate-800 dark:text-slate-200">{{ v.variant_name }}</td>
                <td class="px-6 py-3 text-sm font-mono text-slate-600 dark:text-slate-400">{{ v.sku }}</td>
                <td class="px-6 py-3 text-sm text-slate-600 dark:text-slate-400">{{ v.weight_kg ? v.weight_kg + ' kg' : '—' }}</td>
                <td class="px-6 py-3">
                  <span :class="v.is_active ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300'"
                    class="px-2 py-0.5 rounded-full text-xs font-medium">
                    {{ v.is_active ? 'Active' : 'Inactive' }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Barcodes -->
        <div v-if="product.barcodes?.length" class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
          <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700 flex items-center gap-2">
            <ScanLineIcon class="w-4 h-4 text-indigo-500" />
            <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-200">Barcodes</h2>
          </div>
          <div class="divide-y divide-slate-100 dark:divide-slate-700">
            <div v-for="b in product.barcodes" :key="b.id" class="px-6 py-3 flex items-center justify-between">
              <span class="font-mono text-sm text-slate-800 dark:text-slate-200">{{ b.barcode }}</span>
              <div class="flex items-center gap-2">
                <span class="text-xs text-slate-500 dark:text-slate-400 uppercase">{{ b.type }}</span>
                <span v-if="b.is_primary" class="bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300 text-xs px-2 py-0.5 rounded-full">Primary</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Right: Quick info -->
      <div class="space-y-4">
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-5">
          <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-200 mb-3">Quick Info</h3>
          <dl class="space-y-2.5">
            <div>
              <dt class="text-xs text-slate-500 dark:text-slate-400">SKU</dt>
              <dd class="text-sm font-mono text-indigo-600 dark:text-primary-400 font-medium">{{ product.sku }}</dd>
            </div>
            <div>
              <dt class="text-xs text-slate-500 dark:text-slate-400">Category</dt>
              <dd class="text-sm text-slate-800 dark:text-slate-200">{{ product.category?.name ?? '—' }}</dd>
            </div>
            <div>
              <dt class="text-xs text-slate-500 dark:text-slate-400">Unit</dt>
              <dd class="text-sm text-slate-800 dark:text-slate-200 capitalize">{{ product.unit }}</dd>
            </div>
            <div>
              <dt class="text-xs text-slate-500 dark:text-slate-400">Min Stock Alert</dt>
              <dd class="text-sm font-medium" :class="product.min_stock_alert > 0 ? 'text-amber-600' : 'text-slate-800 dark:text-slate-200'">
                {{ product.min_stock_alert ?? 0 }} units
              </dd>
            </div>
            <div>
              <dt class="text-xs text-slate-500 dark:text-slate-400">Status</dt>
              <dd>
                <span :class="product.is_active ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300'"
                  class="px-2.5 py-0.5 rounded-full text-xs font-medium">
                  {{ product.is_active ? 'Active' : 'Inactive' }}
                </span>
              </dd>
            </div>
          </dl>
        </div>

        <!-- Actions -->
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-5">
          <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-200 mb-3">Actions</h3>
          <div class="space-y-2">
            <Link
              :href="route('products.edit', product.id)"
              class="flex items-center gap-2 w-full px-3 py-2 text-sm text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-900/20 rounded-lg transition-colors font-medium"
            >
              <PencilIcon class="w-4 h-4" /> Edit Product
            </Link>
            <Link
              :href="route('stock.index') + '?product_id=' + product.id"
              class="flex items-center gap-2 w-full px-3 py-2 text-sm text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700/50 rounded-lg transition-colors"
            >
              <PackageIcon class="w-4 h-4" /> View Stock
            </Link>
          </div>
        </div>
      </div>
    </div>

  </AppLayout>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3'
import { PencilIcon, LayersIcon, PackageIcon, ScanLineIcon } from 'lucide-vue-next'
import AppLayout  from '@/Layouts/AppLayout.vue'
import BackButton from '@/Components/UI/BackButton.vue'

defineProps({
  product: { type: Object, required: true },
})
</script>
