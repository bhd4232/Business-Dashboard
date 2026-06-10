<template>
  <AppLayout>
    <Head :title="`Edit: ${record.name}`" />

    <!-- Back link -->
    <Link :href="route('customers.show', record.id)"
      class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 text-sm font-medium hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-all shadow-sm group mb-6">
      <ArrowLeftIcon class="w-4 h-4 group-hover:-translate-x-0.5 transition-transform" />
      {{ record.name }}
    </Link>

    <div class="flex items-center gap-3 mb-6">
      <ThreeDIcon name="customers" size="lg" />
      <div>
        <h1 class="text-2xl font-semibold text-slate-900 dark:text-slate-100">Edit Customer</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">{{ record.customer_code }}</p>
      </div>
    </div>

    <form @submit.prevent="submit" class="space-y-6 max-w-4xl">

      <!-- Basic Info -->
      <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
          <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100">Basic Information</h2>
        </div>
        <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-5">
          <div class="md:col-span-2">
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
              Customer Type <span class="text-red-500">*</span>
            </label>
            <div class="flex gap-3">
              <label v-for="t in ['wholesale','retail']" :key="t"
                :class="form.type === t
                  ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20 text-primary-700 dark:text-primary-300'
                  : 'border-slate-300 dark:border-slate-600 text-slate-600 dark:text-slate-400 hover:border-slate-400 dark:hover:border-slate-500'"
                class="flex-1 flex items-center justify-center gap-2 px-4 py-3 border-2 rounded-xl cursor-pointer transition-all capitalize text-sm font-medium">
                <input type="radio" :value="t" v-model="form.type" class="sr-only" />
                <span>{{ t === 'wholesale' ? '🏪' : '🛒' }}</span>
                {{ t }}
              </label>
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Name <span class="text-red-500">*</span></label>
            <input v-model="form.name" type="text"
              class="w-full px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 dark:text-slate-100 focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-100 dark:focus:ring-primary-900/30" />
            <p v-if="errors.name" class="text-xs text-red-600 dark:text-red-400 mt-1">{{ errors.name }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Business / Shop Name</label>
            <input v-model="form.business_name" type="text"
              class="w-full px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 dark:text-slate-100 focus:outline-none focus:border-primary-500" />
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Phone <span class="text-red-500">*</span></label>
            <input v-model="form.phone" type="tel"
              class="w-full px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 dark:text-slate-100 font-mono focus:outline-none focus:border-primary-500" />
            <p v-if="errors.phone" class="text-xs text-red-600 dark:text-red-400 mt-1">{{ errors.phone }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Email</label>
            <input v-model="form.email" type="email"
              class="w-full px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 dark:text-slate-100 focus:outline-none focus:border-primary-500" />
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Source</label>
            <select v-model="form.source"
              class="w-full px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 dark:text-slate-100 focus:outline-none focus:border-primary-500">
              <option value="">Select source</option>
              <option value="direct">Direct</option>
              <option value="referral">Referral</option>
              <option value="messenger">Messenger</option>
              <option value="whatsapp">WhatsApp</option>
              <option value="woocommerce">WooCommerce</option>
              <option value="other">Other</option>
            </select>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Source Detail</label>
            <input v-model="form.source_detail" type="text"
              class="w-full px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 dark:text-slate-100 focus:outline-none focus:border-primary-500" />
          </div>

          <!-- Status toggle -->
          <div class="md:col-span-2 flex items-center gap-3">
            <button type="button" @click="form.is_active = !form.is_active"
              :class="form.is_active ? 'bg-emerald-500' : 'bg-slate-300 dark:bg-slate-600'"
              class="relative w-10 h-5 rounded-full transition-colors focus:outline-none">
              <span :class="form.is_active ? 'translate-x-5' : 'translate-x-0.5'"
                class="absolute top-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform" />
            </button>
            <span class="text-sm font-medium text-slate-700 dark:text-slate-300">
              {{ form.is_active ? 'Active' : 'Inactive' }}
            </span>
          </div>
        </div>
      </div>

      <!-- Address -->
      <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
          <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100">Address</h2>
        </div>
        <div class="p-6 grid grid-cols-1 md:grid-cols-3 gap-5">
          <div class="md:col-span-3">
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Full Address</label>
            <textarea v-model="form.address" rows="2"
              class="w-full px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 dark:text-slate-100 focus:outline-none focus:border-primary-500 resize-none" />
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Area / Upazila</label>
            <input v-model="form.area" type="text"
              class="w-full px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 dark:text-slate-100 focus:outline-none focus:border-primary-500" />
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">City</label>
            <input v-model="form.city" type="text"
              class="w-full px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 dark:text-slate-100 focus:outline-none focus:border-primary-500" />
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">District</label>
            <input v-model="form.district" type="text"
              class="w-full px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 dark:text-slate-100 focus:outline-none focus:border-primary-500" />
          </div>
        </div>
      </div>

      <!-- Commercial -->
      <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
          <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100">Commercial Settings</h2>
        </div>
        <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-5">
          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Price Tier</label>
            <select v-model="form.price_tier_id"
              class="w-full px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 dark:text-slate-100 focus:outline-none focus:border-primary-500">
              <option :value="null">No tier (default)</option>
              <option v-for="tier in priceTiers" :key="tier.id" :value="tier.id">{{ tier.name }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Credit Limit (BDT)</label>
            <div class="relative">
              <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm text-slate-400 dark:text-slate-500">৳</span>
              <input v-model="form.credit_limit_bdt" type="number" min="0" step="100"
                class="w-full pl-7 pr-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 dark:text-slate-100 focus:outline-none focus:border-primary-500" />
            </div>
          </div>
          <div class="md:col-span-2">
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Tags</label>
            <div class="flex flex-wrap gap-2">
              <button v-for="tag in tags" :key="tag.id" type="button" @click="toggleTag(tag.id)"
                :class="form.tag_ids.includes(tag.id) ? 'ring-2 ring-offset-1 ring-offset-white dark:ring-offset-slate-800' : 'opacity-60 hover:opacity-90'"
                class="rounded-full px-3 py-1 text-xs font-medium transition-all"
                :style="{ backgroundColor: tag.color + '22', color: tag.color }">
                {{ tag.name }}
              </button>
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Assigned Salesman</label>
            <select v-model="form.assigned_salesman_id"
              class="w-full px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 dark:text-slate-100 focus:outline-none focus:border-primary-500">
              <option :value="null">Unassigned</option>
              <option v-for="sm in salesmen" :key="sm.id" :value="sm.id">{{ sm.name }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Rating</label>
            <div class="flex gap-1">
              <button v-for="i in 5" :key="i" type="button" @click="form.rating = i">
                <StarIcon class="w-6 h-6 transition-colors"
                  :class="i <= form.rating ? 'text-amber-400 fill-amber-400' : 'text-slate-300 dark:text-slate-600 fill-slate-300 dark:fill-slate-600'" />
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Notes -->
      <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700">
          <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100">Notes</h2>
        </div>
        <div class="p-6">
          <textarea v-model="form.notes" rows="3"
            class="w-full px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 dark:text-slate-100 focus:outline-none focus:border-primary-500 resize-none" />
        </div>
      </div>

      <!-- Actions -->
      <div class="flex items-center gap-3 pb-6">
        <Link :href="route('customers.show', record.id)"
          class="px-4 py-2 text-sm font-medium text-slate-600 dark:text-slate-400 border border-slate-300 dark:border-slate-600 rounded-xl hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
          Cancel
        </Link>
        <button type="submit" :disabled="loading"
          class="inline-flex items-center gap-2 px-6 py-2 bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold rounded-xl transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
          <LoaderIcon v-if="loading" class="w-4 h-4 animate-spin" />
          <Icon3D v-else name="Save" size="sm" color="text-white" />
          {{ loading ? 'Saving...' : 'Save Changes' }}
        </button>
      </div>

    </form>
  </AppLayout>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { ArrowLeftIcon, StarIcon, LoaderIcon } from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'
import ThreeDIcon from '@/Components/UI/ThreeDIcon.vue'
import Icon3D from '@/Components/UI/Icon3D.vue'
import { useToast } from '@/Composables/useToast'

const props = defineProps({
  record:     { type: Object, required: true },
  tags:       { type: Array,  default: () => [] },
  priceTiers: { type: Array,  default: () => [] },
  salesmen:   { type: Array,  default: () => [] },
})

const { success, error: showError } = useToast()

const loading = ref(false)
const errors  = ref({})

const form = reactive({
  type:                 props.record.type,
  name:                 props.record.name,
  business_name:        props.record.business_name ?? '',
  phone:                props.record.phone,
  email:                props.record.email ?? '',
  address:              props.record.address ?? '',
  area:                 props.record.area ?? '',
  city:                 props.record.city ?? '',
  district:             props.record.district ?? '',
  source:               props.record.source ?? '',
  source_detail:        props.record.source_detail ?? '',
  price_tier_id:        props.record.price_tier_id ?? null,
  credit_limit_bdt:     props.record.credit_limit_bdt ?? 0,
  tag_ids:              props.record.tags?.map(t => t.id) ?? [],
  assigned_salesman_id: props.record.assigned_salesman_id ?? null,
  rating:               props.record.rating ?? 0,
  is_active:            props.record.is_active,
  notes:                props.record.notes ?? '',
})

function toggleTag(id) {
  const idx = form.tag_ids.indexOf(id)
  if (idx === -1) form.tag_ids.push(id)
  else form.tag_ids.splice(idx, 1)
}

async function submit() {
  loading.value = true
  errors.value  = {}
  try {
    await window.axios.put(`/api/v1/customers/${props.record.id}`, form)
    success('Customer updated successfully!')
    router.visit(route('customers.show', props.record.id))
  } catch (err) {
    if (err.response?.status === 422) {
      errors.value = err.response.data.errors || {}
    }
    showError(err.response?.data?.message || 'Failed to update customer.')
  } finally {
    loading.value = false
  }
}
</script>
