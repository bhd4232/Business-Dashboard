<template>
  <AppLayout>
    <Head title="New Supplier" />

    <!-- Back + Header -->
    <div class="mb-6">
      <BackButton label="Back to Suppliers" to="suppliers.index" />
      <h1 class="text-2xl font-semibold text-slate-900 dark:text-slate-100">Add New Supplier</h1>
    </div>

    <form @submit.prevent="submit">
      <!-- Basic Info Section -->
      <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 mb-4">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center gap-2">
          <BuildingIcon class="w-4 h-4 text-purple-600" />
          <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-200">Basic Information</h2>
        </div>
        <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">English Name <span class="text-red-500">*</span></label>
            <input v-model="form.name_english" type="text" placeholder="Shenzhen Trading Co."
              class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 dark:bg-slate-800 dark:border-slate-600 dark:text-slate-100 dark:focus:ring-primary-900/30 dark:placeholder:text-slate-500"
              :class="{ 'border-red-400': errors.name_english }" />
            <p v-if="errors.name_english" class="mt-1 text-xs text-red-600">{{ errors.name_english }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Chinese Name <span class="text-red-500">*</span></label>
            <input v-model="form.name_chinese" type="text" placeholder="深圳贸易有限公司"
              class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 dark:bg-slate-800 dark:border-slate-600 dark:text-slate-100 dark:focus:ring-primary-900/30 dark:placeholder:text-slate-500"
              :class="{ 'border-red-400': errors.name_chinese }" />
            <p v-if="errors.name_chinese" class="mt-1 text-xs text-red-600">{{ errors.name_chinese }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Company Name</label>
            <input v-model="form.company_name" type="text" placeholder="Trading Ltd."
              class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 dark:bg-slate-800 dark:border-slate-600 dark:text-slate-100 dark:focus:ring-primary-900/30 dark:placeholder:text-slate-500" />
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">WeChat ID</label>
            <input v-model="form.wechat_id" type="text" placeholder="wechat_username"
              class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 font-mono dark:bg-slate-800 dark:border-slate-600 dark:text-slate-100 dark:focus:ring-primary-900/30 dark:placeholder:text-slate-500" />
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Phone</label>
            <input v-model="form.phone" type="text" placeholder="+8613812345678"
              class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 font-mono dark:bg-slate-800 dark:border-slate-600 dark:text-slate-100 dark:focus:ring-primary-900/30 dark:placeholder:text-slate-500" />
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Email</label>
            <input v-model="form.email" type="email" placeholder="supplier@example.com"
              class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 dark:bg-slate-800 dark:border-slate-600 dark:text-slate-100 dark:focus:ring-primary-900/30 dark:placeholder:text-slate-500" />
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">City</label>
            <input v-model="form.city" type="text" placeholder="Shenzhen"
              class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 dark:bg-slate-800 dark:border-slate-600 dark:text-slate-100 dark:focus:ring-primary-900/30 dark:placeholder:text-slate-500" />
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Province</label>
            <input v-model="form.province" type="text" placeholder="Guangdong"
              class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 dark:bg-slate-800 dark:border-slate-600 dark:text-slate-100 dark:focus:ring-primary-900/30 dark:placeholder:text-slate-500" />
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Rating</label>
            <div class="flex gap-1">
              <button
                v-for="i in 5"
                :key="i"
                type="button"
                @click="form.rating = i"
                class="p-0.5 focus:outline-none"
              >
                <StarIcon class="w-6 h-6 transition-colors"
                  :class="i <= form.rating ? 'text-amber-400 fill-amber-400' : 'text-slate-300 fill-slate-200 hover:text-amber-300'" />
              </button>
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Payment Terms</label>
            <input v-model="form.payment_terms" type="text" placeholder="30% advance, 70% before shipping"
              class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 dark:bg-slate-800 dark:border-slate-600 dark:text-slate-100 dark:focus:ring-primary-900/30 dark:placeholder:text-slate-500" />
          </div>

          <div class="md:col-span-2">
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Address</label>
            <textarea v-model="form.address" rows="2" placeholder="Full address in China..."
              class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 dark:bg-slate-800 dark:border-slate-600 dark:text-slate-100" />
          </div>

          <div class="md:col-span-2">
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Notes</label>
            <textarea v-model="form.notes" rows="2"
              class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 dark:bg-slate-800 dark:border-slate-600 dark:text-slate-100" />
          </div>
        </div>
      </div>

      <!-- Contacts Section -->
      <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 mb-6">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
          <div class="flex items-center gap-2">
            <UserIcon class="w-4 h-4 text-purple-600" />
            <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-200">Contact Persons</h2>
          </div>
          <button type="button" @click="addContact"
            class="text-sm text-indigo-600 hover:text-indigo-700 dark:text-primary-400 dark:hover:text-primary-300 font-medium flex items-center gap-1">
            <PlusIcon class="w-3 h-3" /> Add Contact
          </button>
        </div>
        <div class="p-6">
          <div v-if="form.contacts.length === 0" class="text-center py-6 text-slate-400 text-sm">
            No contacts added yet
          </div>
          <div v-for="(contact, idx) in form.contacts" :key="idx"
            class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4 p-4 bg-slate-50 dark:bg-slate-700/50 rounded-lg relative">
            <button type="button" @click="removeContact(idx)"
              class="absolute top-2 right-2 text-slate-400 hover:text-red-500 transition-colors">
              <XIcon class="w-4 h-4" />
            </button>
            <div>
              <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Name *</label>
              <input v-model="contact.name" type="text"
                class="w-full rounded-lg border border-slate-300 px-3 py-1.5 text-sm focus:outline-none focus:border-indigo-500 dark:bg-slate-800 dark:border-slate-600 dark:text-slate-100" />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Phone</label>
              <input v-model="contact.phone" type="text"
                class="w-full rounded-lg border border-slate-300 px-3 py-1.5 text-sm focus:outline-none focus:border-indigo-500 font-mono dark:bg-slate-800 dark:border-slate-600 dark:text-slate-100" />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">WeChat</label>
              <input v-model="contact.wechat_id" type="text"
                class="w-full rounded-lg border border-slate-300 px-3 py-1.5 text-sm focus:outline-none focus:border-indigo-500 font-mono dark:bg-slate-800 dark:border-slate-600 dark:text-slate-100" />
            </div>
            <div class="flex items-center gap-2">
              <input type="checkbox" :id="'primary-' + idx" v-model="contact.is_primary"
                @change="setPrimary(idx)" class="rounded border-slate-300 dark:border-slate-600 text-indigo-600" />
              <label :for="'primary-' + idx" class="text-xs text-slate-600 dark:text-slate-400">Primary Contact</label>
            </div>
          </div>
        </div>
      </div>

      <!-- Sticky Submit Bar -->
      <div class="sticky bottom-0 bg-white dark:bg-slate-800 border-t border-slate-200 dark:border-slate-700 shadow-lg -mx-6 px-6 py-4 flex items-center justify-end gap-3">
        <Link :href="route('suppliers.index')"
          class="px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-slate-100 border border-slate-300 dark:border-slate-600 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
          Cancel
        </Link>
        <button type="submit" :disabled="saving"
          class="px-6 py-2 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white text-sm font-medium rounded-lg transition-colors flex items-center gap-2">
          <LoaderIcon v-if="saving" class="w-4 h-4 animate-spin" />
          Save Supplier
        </button>
      </div>
    </form>

  </AppLayout>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { ChevronLeftIcon, BuildingIcon, UserIcon, PlusIcon, XIcon, StarIcon, LoaderIcon } from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'
import BackButton from '@/Components/UI/BackButton.vue'
import { useToast } from '@/Composables/useToast'

const { success, error: showError } = useToast()
const saving = ref(false)
const errors = ref({})

const form = reactive({
  name_english:   '',
  name_chinese:   '',
  company_name:   '',
  wechat_id:      '',
  phone:          '',
  email:          '',
  address:        '',
  city:           '',
  province:       '',
  rating:         0,
  payment_terms:  '',
  notes:          '',
  contacts:       [],
})

function addContact() {
  form.contacts.push({ name: '', phone: '', wechat_id: '', is_primary: form.contacts.length === 0 })
}

function removeContact(idx) {
  form.contacts.splice(idx, 1)
}

function setPrimary(idx) {
  form.contacts.forEach((c, i) => {
    if (i !== idx) c.is_primary = false
  })
}

async function submit() {
  saving.value = true
  errors.value = {}

  try {
    await window.axios.post('/api/v1/suppliers', form)
    success(`Supplier "${form.name_english}" created successfully!`)
    router.visit(route('suppliers.index'))
  } catch (err) {
    if (err.response?.status === 422) {
      errors.value = err.response.data.errors || {}
      const firstMsg = Object.values(errors.value)[0]?.[0]
      if (firstMsg) showError(firstMsg)
    } else {
      showError(err.response?.data?.message || 'Failed to create supplier.')
    }
    saving.value = false
  }
}
</script>
