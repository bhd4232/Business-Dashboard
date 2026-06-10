<template>
  <AppLayout>
    <Head title="Invoice Settings" />

    <!-- Back button -->
    <Link
      :href="route('settings.index')"
      class="inline-flex items-center gap-1.5 text-sm text-slate-600 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200 border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-1.5 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-all shadow-sm group mb-4"
    >
      <ArrowLeftIcon class="w-4 h-4 group-hover:-translate-x-0.5 transition-transform" />
      Settings
    </Link>

    <!-- Page Header -->
    <div class="flex items-center justify-between mb-6">
      <div class="flex items-center gap-3">
        <ThreeDIcon name="sales" size="lg" />
        <div>
          <h1 class="text-xl font-bold text-slate-900 dark:text-slate-100">Invoice Settings</h1>
          <p class="text-sm text-slate-500 dark:text-slate-400">Customize invoice print layout, company info, and defaults</p>
        </div>
      </div>
      <button
        @click="save"
        :disabled="saving"
        class="inline-flex items-center gap-2 px-5 py-2.5 bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold rounded-xl transition-colors disabled:opacity-50 disabled:cursor-not-allowed shadow-sm"
      >
        <LoaderIcon v-if="saving" class="w-4 h-4 animate-spin" />
        <Icon3D v-else name="Save" size="sm" color="text-white" />
        {{ saving ? 'Saving...' : 'Save Settings' }}
      </button>
    </div>

    <!-- Status Banner -->
    <div
      v-if="saveStatus === 'saved'"
      class="mb-5 p-3 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-700/50 rounded-xl text-sm text-emerald-700 dark:text-emerald-400 flex items-center gap-2"
    >
      <CheckCircleIcon class="w-4 h-4 shrink-0" />
      Invoice settings saved successfully.
    </div>
    <div
      v-if="saveStatus === 'error'"
      class="mb-5 p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700/50 rounded-xl text-sm text-red-700 dark:text-red-400 flex items-center gap-2"
    >
      <AlertCircleIcon class="w-4 h-4 shrink-0" />
      Failed to save settings. Please try again.
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

      <!-- ── Left / Main: Settings panels ──────────────────────────────── -->
      <div class="lg:col-span-2 space-y-6">

        <!-- Company / Branding Card -->
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6 shadow-sm">
          <div class="flex items-center gap-2 mb-1">
            <BuildingIcon class="w-4 h-4 text-primary-500" />
            <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100">Company & Branding</h2>
          </div>
          <p class="text-sm text-slate-500 dark:text-slate-400 mb-5">Shown on printed / PDF invoices.</p>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <!-- Company Name -->
            <div class="sm:col-span-2">
              <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
                Company Name <span class="text-red-500">*</span>
              </label>
              <input
                v-model="form.company_name"
                type="text"
                placeholder="Zamzam International"
                class="w-full px-3 py-2 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500/40 focus:border-primary-500"
              />
              <p v-if="errors.company_name" class="mt-1 text-xs text-red-500">{{ errors.company_name }}</p>
            </div>

            <!-- Tagline -->
            <div class="sm:col-span-2">
              <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Tagline <span class="text-slate-400 font-normal">(optional)</span></label>
              <input
                v-model="form.company_tagline"
                type="text"
                placeholder="e.g. Your Trusted Gadget Store"
                class="w-full px-3 py-2 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500/40 focus:border-primary-500"
              />
            </div>

            <!-- Address -->
            <div class="sm:col-span-2">
              <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Address</label>
              <input
                v-model="form.address"
                type="text"
                placeholder="House-59, Road-6/A, Sector-5, Uttara, Dhaka"
                class="w-full px-3 py-2 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500/40 focus:border-primary-500"
              />
            </div>

            <!-- Hotline 1 -->
            <div>
              <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Hotline 1</label>
              <input
                v-model="form.hotline_1"
                type="text"
                placeholder="01811754232"
                class="w-full px-3 py-2 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500/40 focus:border-primary-500"
              />
            </div>

            <!-- Hotline 2 -->
            <div>
              <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Hotline 2</label>
              <input
                v-model="form.hotline_2"
                type="text"
                placeholder="01894449445"
                class="w-full px-3 py-2 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500/40 focus:border-primary-500"
              />
            </div>

            <!-- Hotline 3 -->
            <div>
              <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Hotline 3 <span class="text-slate-400 font-normal">(optional)</span></label>
              <input
                v-model="form.hotline_3"
                type="text"
                placeholder="01678413888"
                class="w-full px-3 py-2 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500/40 focus:border-primary-500"
              />
            </div>

            <!-- Email -->
            <div>
              <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Email</label>
              <input
                v-model="form.email"
                type="text"
                placeholder="zamzamgadgetsbd@gmail.com"
                class="w-full px-3 py-2 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500/40 focus:border-primary-500"
              />
            </div>

            <!-- Website -->
            <div>
              <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Website</label>
              <input
                v-model="form.website"
                type="text"
                placeholder="zamzamint.com"
                class="w-full px-3 py-2 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500/40 focus:border-primary-500"
              />
            </div>

            <!-- Facebook -->
            <div class="sm:col-span-2">
              <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Facebook Page</label>
              <input
                v-model="form.facebook"
                type="text"
                placeholder="facebook.com/zamzamintl"
                class="w-full px-3 py-2 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500/40 focus:border-primary-500"
              />
            </div>
          </div>
        </div>

        <!-- Invoice Configuration Card -->
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6 shadow-sm">
          <div class="flex items-center gap-2 mb-1">
            <FileTextIcon class="w-4 h-4 text-primary-500" />
            <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100">Invoice Configuration</h2>
          </div>
          <p class="text-sm text-slate-500 dark:text-slate-400 mb-5">Default values used when creating new invoices.</p>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <!-- Invoice Prefix -->
            <div>
              <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
                Invoice Number Prefix <span class="text-red-500">*</span>
              </label>
              <input
                v-model="form.invoice_prefix"
                type="text"
                placeholder="INV"
                maxlength="10"
                class="w-full px-3 py-2 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 text-sm font-mono uppercase focus:outline-none focus:ring-2 focus:ring-primary-500/40 focus:border-primary-500"
              />
              <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">Format: {{ form.invoice_prefix || 'INV' }}-2026-0001</p>
              <p v-if="errors.invoice_prefix" class="mt-1 text-xs text-red-500">{{ errors.invoice_prefix }}</p>
            </div>

            <!-- Default Payment Terms -->
            <div>
              <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
                Default Payment Terms <span class="text-slate-400 font-normal">(days)</span>
              </label>
              <input
                v-model.number="form.default_payment_terms_days"
                type="number"
                min="0"
                max="365"
                placeholder="e.g. 30"
                class="w-full px-3 py-2 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500/40 focus:border-primary-500"
              />
              <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">Leave blank for no default due date.</p>
            </div>

            <!-- Default Notes -->
            <div class="sm:col-span-2">
              <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
                Default Notes / Terms <span class="text-slate-400 font-normal">(optional)</span>
              </label>
              <textarea
                v-model="form.default_notes"
                rows="3"
                placeholder="e.g. Goods once sold will not be returned..."
                class="w-full px-3 py-2 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500/40 focus:border-primary-500 resize-none"
              ></textarea>
            </div>

            <!-- Thank You Message -->
            <div class="sm:col-span-2">
              <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Thank You Message</label>
              <input
                v-model="form.thank_you_message"
                type="text"
                placeholder="Thank You For Purchasing From Us."
                class="w-full px-3 py-2 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500/40 focus:border-primary-500"
              />
            </div>
          </div>
        </div>

        <!-- Print / PDF Options Card -->
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6 shadow-sm">
          <div class="flex items-center gap-2 mb-1">
            <PrinterIcon class="w-4 h-4 text-primary-500" />
            <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100">Print / PDF Display Options</h2>
          </div>
          <p class="text-sm text-slate-500 dark:text-slate-400 mb-5">Control what appears on the printed invoice layout.</p>

          <div class="space-y-4">
            <!-- Show product images -->
            <label class="flex items-center justify-between cursor-pointer group">
              <div>
                <p class="text-sm font-medium text-slate-800 dark:text-slate-200 group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors">Show Product Images</p>
                <p class="text-xs text-slate-500 dark:text-slate-400">Display product thumbnail photos in the items table.</p>
              </div>
              <div
                @click="form.show_product_images = !form.show_product_images"
                :class="[
                  'relative w-11 h-6 rounded-full transition-all duration-200 shrink-0 ml-4 cursor-pointer',
                  form.show_product_images ? 'bg-primary-600' : 'bg-slate-300 dark:bg-slate-600',
                ]"
              >
                <span
                  :class="[
                    'absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform duration-200',
                    form.show_product_images ? 'translate-x-5' : 'translate-x-0',
                  ]"
                ></span>
              </div>
            </label>

            <div class="border-t border-slate-100 dark:border-slate-700"></div>

            <!-- Show product weight -->
            <label class="flex items-center justify-between cursor-pointer group">
              <div>
                <p class="text-sm font-medium text-slate-800 dark:text-slate-200 group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors">Show Product Weight</p>
                <p class="text-xs text-slate-500 dark:text-slate-400">Display the weight column in the items table.</p>
              </div>
              <div
                @click="form.show_product_weight = !form.show_product_weight"
                :class="[
                  'relative w-11 h-6 rounded-full transition-all duration-200 shrink-0 ml-4 cursor-pointer',
                  form.show_product_weight ? 'bg-primary-600' : 'bg-slate-300 dark:bg-slate-600',
                ]"
              >
                <span
                  :class="[
                    'absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform duration-200',
                    form.show_product_weight ? 'translate-x-5' : 'translate-x-0',
                  ]"
                ></span>
              </div>
            </label>

            <div class="border-t border-slate-100 dark:border-slate-700"></div>

            <!-- Show delivery partner -->
            <label class="flex items-center justify-between cursor-pointer group">
              <div>
                <p class="text-sm font-medium text-slate-800 dark:text-slate-200 group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors">Show Delivery Partner</p>
                <p class="text-xs text-slate-500 dark:text-slate-400">Display the linked delivery partner on the printed invoice.</p>
              </div>
              <div
                @click="form.show_delivery_partner = !form.show_delivery_partner"
                :class="[
                  'relative w-11 h-6 rounded-full transition-all duration-200 shrink-0 ml-4 cursor-pointer',
                  form.show_delivery_partner ? 'bg-primary-600' : 'bg-slate-300 dark:bg-slate-600',
                ]"
              >
                <span
                  :class="[
                    'absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform duration-200',
                    form.show_delivery_partner ? 'translate-x-5' : 'translate-x-0',
                  ]"
                ></span>
              </div>
            </label>
          </div>
        </div>

        <!-- Save Button (bottom) -->
        <div class="flex items-center justify-end gap-3 pb-2">
          <p v-if="saveStatus === 'saved'" class="text-sm text-emerald-600 dark:text-emerald-400 flex items-center gap-1.5">
            <CheckCircleIcon class="w-4 h-4" /> Saved
          </p>
          <button
            @click="save"
            :disabled="saving"
            class="inline-flex items-center gap-2 px-5 py-2.5 bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold rounded-xl transition-colors disabled:opacity-50 disabled:cursor-not-allowed shadow-sm"
          >
            <LoaderIcon v-if="saving" class="w-4 h-4 animate-spin" />
            <Icon3D v-else name="Save" size="sm" color="text-white" />
            {{ saving ? 'Saving...' : 'Save Settings' }}
          </button>
        </div>

      </div>

      <!-- ── Right: Preview panel ──────────────────────────────────────── -->
      <div class="space-y-4">

        <!-- Print Preview Card -->
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-4 shadow-sm sticky top-6">
          <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300 mb-3 flex items-center gap-1.5">
            <EyeIcon class="w-4 h-4 text-slate-400" /> Invoice Print Preview
          </h2>

          <!-- Mini invoice preview -->
          <div class="bg-white rounded-lg border border-slate-200 p-3 text-[8px] leading-snug overflow-hidden">
            <!-- Header row -->
            <div class="flex justify-between items-start mb-2">
              <div class="flex items-center gap-1">
                <div class="text-[11px] font-black tracking-tighter text-black">
                  Za<span style="color:#e8a000;">▲</span>Zam
                </div>
                <div class="border-l border-slate-300 pl-1">
                  <div class="font-bold text-[9px] text-black">{{ form.company_name || 'Company Name' }}</div>
                  <div v-if="form.company_tagline" class="text-[7px] text-slate-500">{{ form.company_tagline }}</div>
                  <div class="text-[7px] text-slate-500">{{ form.hotline_1 || '—' }}</div>
                </div>
              </div>
              <div class="text-right">
                <div class="font-bold text-[9px]">Invoice No: <span class="font-mono">{{ form.invoice_prefix || 'INV' }}-2026-0001</span></div>
                <div class="text-slate-500">Date: 23 May 2026</div>
              </div>
            </div>

            <!-- Bill to box -->
            <div class="bg-slate-100 rounded px-2 py-1 mb-2">
              <div class="text-slate-500 text-[7px]">Bill To:</div>
              <div class="font-bold">Sample Customer</div>
              <div class="text-slate-400">01712000000</div>
            </div>

            <!-- Items table header -->
            <div class="grid text-center font-semibold text-white bg-slate-700 rounded-t py-0.5 mb-0"
              :class="form.show_product_images && form.show_product_weight ? 'grid-cols-7' : form.show_product_images || form.show_product_weight ? 'grid-cols-6' : 'grid-cols-5'"
            >
              <span>#</span>
              <span v-if="form.show_product_images">Img</span>
              <span class="col-span-2 text-left pl-1">Item</span>
              <span v-if="form.show_product_weight">Wt</span>
              <span>Price</span>
              <span>Qty</span>
              <span>Amt</span>
            </div>

            <!-- Sample row -->
            <div class="grid text-center border border-slate-200 border-t-0 py-0.5"
              :class="form.show_product_images && form.show_product_weight ? 'grid-cols-7' : form.show_product_images || form.show_product_weight ? 'grid-cols-6' : 'grid-cols-5'"
            >
              <span>1</span>
              <span v-if="form.show_product_images">
                <div class="w-4 h-4 bg-slate-200 rounded mx-auto"></div>
              </span>
              <span class="col-span-2 text-left pl-1">Sample Product</span>
              <span v-if="form.show_product_weight">0.5kg</span>
              <span>1,000</span>
              <span>2</span>
              <span class="font-mono">2,000</span>
            </div>

            <!-- Totals area -->
            <div class="flex mt-1 gap-1">
              <div class="flex-1 text-[7px] text-slate-500 border border-slate-200 p-1 rounded-sm">
                <div v-if="form.facebook">f {{ form.facebook }}</div>
                <div v-if="form.email">✉ {{ form.email }}</div>
                <div v-if="form.website">⊕ {{ form.website }}</div>
                <div v-if="form.address">◎ {{ form.address }}</div>
              </div>
              <div class="text-right text-[7px] space-y-0.5 w-24">
                <div class="flex justify-between"><span class="text-slate-500">Sub Total</span><span class="font-mono">2,000</span></div>
                <div class="flex justify-between font-bold"><span>Grand Total</span><span class="font-mono">2,000</span></div>
                <div class="flex justify-between bg-slate-800 text-white rounded px-0.5"><span>Due</span><span class="font-mono">2,000</span></div>
              </div>
            </div>

            <!-- Contact bar -->
            <div class="mt-1 bg-slate-800 text-white text-[7px] px-1 py-0.5 rounded flex gap-1 justify-between flex-wrap">
              <span v-if="form.hotline_1">{{ form.hotline_1 }}</span>
              <span v-if="form.hotline_2">|  {{ form.hotline_2 }}</span>
              <span v-if="form.hotline_3">| {{ form.hotline_3 }}</span>
            </div>

            <!-- Thank you -->
            <div class="text-center mt-1 text-slate-600 font-semibold text-[7px]">
              {{ form.thank_you_message || 'Thank You!' }}
            </div>
          </div>

          <p class="mt-2 text-xs text-slate-400 dark:text-slate-500 text-center">
            Live preview — updates as you type
          </p>
        </div>

        <!-- Quick info card -->
        <div class="bg-primary-50 dark:bg-primary-900/20 rounded-xl border border-primary-100 dark:border-primary-800/50 p-4">
          <div class="flex items-start gap-2">
            <InfoIcon class="w-4 h-4 text-primary-500 shrink-0 mt-0.5" />
            <div class="text-xs text-primary-700 dark:text-primary-300 space-y-1">
              <p class="font-semibold">These settings apply globally to all invoices.</p>
              <p>Changes will appear on all newly printed / PDF invoices. Existing saved invoice data is not affected.</p>
            </div>
          </div>
        </div>

      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import AppLayout    from '@/Layouts/AppLayout.vue'
import ThreeDIcon   from '@/Components/UI/ThreeDIcon.vue'
import Icon3D       from '@/Components/UI/Icon3D.vue'
import {
  ArrowLeftIcon,
  BuildingIcon,
  FileTextIcon,
  PrinterIcon,
  EyeIcon,
  InfoIcon,
  CheckCircleIcon,
  AlertCircleIcon,
  LoaderIcon,
} from 'lucide-vue-next'

const props = defineProps({
  settings: { type: Object, required: true },
})

const form = reactive({
  // Company / Branding
  company_name:    props.settings.company_name    ?? 'Zamzam International',
  company_tagline: props.settings.company_tagline ?? '',
  address:         props.settings.address         ?? '',
  hotline_1:       props.settings.hotline_1       ?? '',
  hotline_2:       props.settings.hotline_2       ?? '',
  hotline_3:       props.settings.hotline_3       ?? '',
  email:           props.settings.email           ?? '',
  website:         props.settings.website         ?? '',
  facebook:        props.settings.facebook        ?? '',

  // Invoice Configuration
  invoice_prefix:              props.settings.invoice_prefix              ?? 'INV',
  default_payment_terms_days:  props.settings.default_payment_terms_days  ?? null,
  default_notes:               props.settings.default_notes               ?? '',
  thank_you_message:           props.settings.thank_you_message           ?? 'Thank You For Purchasing From Us.',

  // Print / PDF Options
  show_product_images:    props.settings.show_product_images    ?? true,
  show_product_weight:    props.settings.show_product_weight    ?? true,
  show_delivery_partner:  props.settings.show_delivery_partner  ?? true,
})

const saving    = ref(false)
const saveStatus= ref('')   // '' | 'saved' | 'error'
const errors    = reactive({})

async function save() {
  saving.value    = true
  saveStatus.value = ''
  Object.keys(errors).forEach(k => delete errors[k])

  try {
    const payload = { ...form }
    if (!payload.default_payment_terms_days) payload.default_payment_terms_days = null

    await window.axios.put('/api/v1/settings/invoice', payload)
    saveStatus.value = 'saved'
    setTimeout(() => { saveStatus.value = '' }, 3000)
  } catch (err) {
    if (err.response?.status === 422) {
      const apiErrors = err.response.data.errors ?? {}
      Object.assign(errors, Object.fromEntries(
        Object.entries(apiErrors).map(([k, v]) => [k, Array.isArray(v) ? v[0] : v])
      ))
    }
    saveStatus.value = 'error'
  } finally {
    saving.value = false
  }
}
</script>
