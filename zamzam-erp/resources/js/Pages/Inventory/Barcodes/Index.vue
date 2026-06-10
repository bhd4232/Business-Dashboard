<template>
  <AppLayout>
    <Head title="Barcodes" />

    <!-- ── Page Header ─────────────────────────────────────────────── -->
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-semibold text-slate-900 dark:text-slate-100">Barcodes / QR Codes</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Generate and print product barcodes</p>
      </div>
      <div class="flex gap-2">
        <!-- Settings button -->
        <button @click="openSettings"
          class="inline-flex items-center gap-2 border border-slate-300 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700/50 text-slate-700 dark:text-slate-300 text-sm font-medium px-4 py-2 rounded-lg transition-colors">
          <Settings2Icon class="w-4 h-4" />
          Sequence Settings
        </button>

        <!-- Print selected -->
        <button v-if="selectedIds.size > 0" @click="printSelected"
          class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
          <PrinterIcon class="w-4 h-4" />
          Print Selected ({{ selectedIds.size }})
        </button>

        <!-- Bulk generate -->
        <button @click="bulkGenerate" :disabled="generating"
          class="inline-flex items-center gap-2 border border-slate-300 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700/50 text-slate-700 dark:text-slate-300 text-sm font-medium px-4 py-2 rounded-lg transition-colors">
          <LoaderIcon v-if="generating" class="w-4 h-4 animate-spin" />
          <RefreshCwIcon v-else class="w-4 h-4" />
          Generate All Missing
        </button>
      </div>
    </div>

    <!-- ── Table ───────────────────────────────────────────────────── -->
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
      <table class="w-full">
        <thead>
          <tr class="bg-slate-50 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700">
            <!-- Select all -->
            <th class="px-4 py-3 w-10">
              <input type="checkbox" :checked="allSelected" :indeterminate="someSelected"
                @change="toggleAll"
                class="w-4 h-4 rounded border-slate-300 dark:border-slate-600 text-indigo-600 cursor-pointer" />
            </th>
            <th class="text-left text-sm font-medium text-slate-700 dark:text-slate-300 px-4 py-3">Product</th>
            <th class="text-left text-sm font-medium text-slate-700 dark:text-slate-300 px-4 py-3">Barcode</th>
            <th class="text-left text-sm font-medium text-slate-700 dark:text-slate-300 px-4 py-3">Type</th>
            <th class="text-left text-sm font-medium text-slate-700 dark:text-slate-300 px-4 py-3">Primary</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="barcodes.data.length === 0">
            <td colspan="5" class="text-center py-12 text-slate-400">
              <QrCodeIcon class="w-10 h-10 mx-auto mb-2 text-slate-300" />
              <p class="text-sm">No barcodes found</p>
              <button @click="bulkGenerate" class="mt-3 text-sm text-indigo-600 dark:text-primary-400 hover:text-indigo-700 dark:hover:text-primary-300 font-medium">
                Generate for all products
              </button>
            </td>
          </tr>
          <tr v-for="b in barcodes.data" :key="b.id"
            class="border-b border-slate-100 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700/50 cursor-pointer transition-colors"
            @click.stop="openDetail(b)">
            <!-- Per-row checkbox -->
            <td class="px-4 py-3" @click.stop>
              <input type="checkbox" :checked="selectedIds.has(b.id)"
                @change="toggleRow(b.id)"
                class="w-4 h-4 rounded border-slate-300 dark:border-slate-600 text-indigo-600 cursor-pointer" />
            </td>
            <td class="px-4 py-4">
              <p class="text-sm font-medium text-slate-900 dark:text-slate-100">{{ b.product?.name }}</p>
              <p v-if="b.variant" class="text-xs text-slate-500 dark:text-slate-400">{{ b.variant.variant_name }}</p>
            </td>
            <td class="px-4 py-4 text-sm font-mono text-slate-700 dark:text-slate-300">{{ b.barcode }}</td>
            <td class="px-4 py-4">
              <span class="bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300 rounded-full px-2.5 py-0.5 text-xs font-medium uppercase">
                {{ b.type }}
              </span>
            </td>
            <td class="px-4 py-4">
              <span v-if="b.is_primary" class="bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300 rounded-full px-2.5 py-0.5 text-xs font-medium">Primary</span>
            </td>
          </tr>
        </tbody>
      </table>

      <div v-if="barcodes.last_page > 1" class="flex items-center justify-between px-6 py-3 border-t border-slate-200 dark:border-slate-700">
        <p class="text-sm text-slate-500 dark:text-slate-400">{{ barcodes.from }}–{{ barcodes.to }} of {{ barcodes.total }}</p>
        <div class="flex gap-1">
          <Link v-for="link in barcodes.links" :key="link.label" :href="link.url || '#'"
            :class="['px-3 py-1 text-sm rounded-lg transition-colors',
              link.active ? 'bg-indigo-600 text-white' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700/50',
              !link.url ? 'opacity-40 pointer-events-none' : '']"
            v-html="link.label" />
        </div>
      </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════ -->
    <!-- MODAL: Barcode Detail + Print                                  -->
    <!-- ══════════════════════════════════════════════════════════════ -->
    <Teleport to="body">
      <div v-if="detailBarcode" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4"
        @click.self="detailBarcode = null">
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-xl w-full max-w-md p-6">
          <!-- Header -->
          <div class="flex items-start justify-between mb-5">
            <div>
              <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Barcode Detail</h2>
              <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">{{ detailBarcode.product?.name }}</p>
            </div>
            <button @click="detailBarcode = null" class="text-slate-400 hover:text-slate-600 transition-colors">
              <XIcon class="w-5 h-5" />
            </button>
          </div>

          <!-- Barcode SVG preview -->
          <div class="flex flex-col items-center bg-slate-50 dark:bg-slate-700/50 rounded-xl p-5 mb-5 border border-slate-200 dark:border-slate-700">
            <svg :id="`barcode-detail-${detailBarcode.id}`" class="max-w-full"></svg>
            <p class="mt-2 text-xs font-mono text-slate-600 dark:text-slate-400 tracking-widest">{{ detailBarcode.barcode }}</p>
          </div>

          <!-- Info rows -->
          <div class="space-y-2 text-sm mb-6">
            <div class="flex justify-between">
              <span class="text-slate-500 dark:text-slate-400">Product</span>
              <span class="font-medium text-slate-800 dark:text-slate-200">{{ detailBarcode.product?.name }}</span>
            </div>
            <div v-if="detailBarcode.variant" class="flex justify-between">
              <span class="text-slate-500 dark:text-slate-400">Variant</span>
              <span class="font-medium text-slate-800 dark:text-slate-200">{{ detailBarcode.variant.variant_name }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-slate-500 dark:text-slate-400">Type</span>
              <span class="bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300 rounded-full px-2.5 py-0.5 text-xs font-medium uppercase">{{ detailBarcode.type }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-slate-500 dark:text-slate-400">Primary</span>
              <span v-if="detailBarcode.is_primary" class="bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300 rounded-full px-2.5 py-0.5 text-xs font-medium">Yes</span>
              <span v-else class="text-slate-400 text-xs">No</span>
            </div>
          </div>

          <!-- Actions -->
          <div class="flex gap-2 justify-end">
            <button @click="detailBarcode = null"
              class="px-4 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
              Close
            </button>
            <button @click="printSingle(detailBarcode)"
              class="inline-flex items-center gap-2 px-4 py-2 text-sm bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg transition-colors">
              <PrinterIcon class="w-4 h-4" />
              Print Label
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ══════════════════════════════════════════════════════════════ -->
    <!-- MODAL: Sequence Settings                                       -->
    <!-- ══════════════════════════════════════════════════════════════ -->
    <Teleport to="body">
      <div v-if="showSettings" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4"
        @click.self="showSettings = false">
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-xl w-full max-w-lg p-6">
          <!-- Header -->
          <div class="flex items-start justify-between mb-5">
            <div>
              <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Barcode Sequence Settings</h2>
              <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Customize how new barcode numbers are generated</p>
            </div>
            <button @click="showSettings = false" class="text-slate-400 hover:text-slate-600 transition-colors">
              <XIcon class="w-5 h-5" />
            </button>
          </div>

          <div v-if="settingsLoading" class="flex items-center justify-center py-10 text-slate-400">
            <LoaderIcon class="w-5 h-5 animate-spin mr-2" />
            Loading...
          </div>
          <div v-else class="space-y-4">
            <!-- Row 1: Prefix + Separator + Suffix -->
            <div class="grid grid-cols-3 gap-3">
              <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Prefix</label>
                <input v-model="settingsForm.prefix" type="text" maxlength="10"
                  class="w-full border border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:placeholder:text-slate-500 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                  placeholder="ZAM" @input="refreshPreview" />
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Separator</label>
                <input v-model="settingsForm.separator" type="text" maxlength="5"
                  class="w-full border border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:placeholder:text-slate-500 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                  placeholder="(none)" @input="refreshPreview" />
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Suffix</label>
                <input v-model="settingsForm.suffix" type="text" maxlength="10"
                  class="w-full border border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:placeholder:text-slate-500 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                  placeholder="(none)" @input="refreshPreview" />
              </div>
            </div>

            <!-- Row 2: Sequence digits + Start -->
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Sequence Digits</label>
                <input v-model.number="settingsForm.sequence_digits" type="number" min="1" max="12"
                  class="w-full border border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                  @input="refreshPreview" />
                <p class="text-xs text-slate-400 dark:text-slate-500 mt-0.5">e.g. 6 → 000001</p>
              </div>
              <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Current Sequence #</label>
                <input v-model.number="settingsForm.current_sequence" type="number" min="1"
                  class="w-full border border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent" />
                <p class="text-xs text-slate-400 dark:text-slate-500 mt-0.5">Next barcode will use this number</p>
              </div>
            </div>

            <!-- Row 3: Toggles -->
            <div class="grid grid-cols-3 gap-3">
              <label class="flex items-center gap-2 cursor-pointer select-none">
                <input type="checkbox" v-model="settingsForm.include_year" @change="refreshPreview"
                  class="w-4 h-4 rounded border-slate-300 dark:border-slate-600 text-indigo-600" />
                <span class="text-sm text-slate-700 dark:text-slate-300">Include Year</span>
              </label>
              <label class="flex items-center gap-2 cursor-pointer select-none">
                <input type="checkbox" v-model="settingsForm.include_month" @change="refreshPreview"
                  :disabled="!settingsForm.include_year"
                  class="w-4 h-4 rounded border-slate-300 dark:border-slate-600 text-indigo-600 disabled:opacity-40" />
                <span class="text-sm text-slate-700 dark:text-slate-300">Include Month</span>
              </label>
              <label class="flex items-center gap-2 cursor-pointer select-none">
                <input type="checkbox" v-model="settingsForm.reset_annually"
                  class="w-4 h-4 rounded border-slate-300 dark:border-slate-600 text-indigo-600" />
                <span class="text-sm text-slate-700 dark:text-slate-300">Reset Yearly</span>
              </label>
            </div>

            <!-- Preview -->
            <div class="bg-indigo-50 border border-indigo-200 rounded-xl px-4 py-3 flex items-center justify-between">
              <span class="text-xs text-indigo-500 font-medium uppercase tracking-wide">Preview</span>
              <span class="font-mono text-indigo-700 font-semibold text-base tracking-widest">{{ settingsPreview }}</span>
            </div>

            <!-- Actions -->
            <div class="flex gap-2 justify-end pt-1">
              <button @click="showSettings = false"
                class="px-4 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                Cancel
              </button>
              <button @click="saveSettings" :disabled="settingsSaving"
                class="inline-flex items-center gap-2 px-4 py-2 text-sm bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg transition-colors disabled:opacity-60">
                <LoaderIcon v-if="settingsSaving" class="w-3.5 h-3.5 animate-spin" />
                Save Settings
              </button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>

  </AppLayout>
</template>

<script setup>
import { ref, computed, nextTick, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import {
  RefreshCwIcon, LoaderIcon, QrCodeIcon, XIcon,
  PrinterIcon, Settings2Icon,
} from 'lucide-vue-next'
import JsBarcode from 'jsbarcode'
import AppLayout from '@/Layouts/AppLayout.vue'
import { useToast } from '@/Composables/useToast'

// ─── Props ────────────────────────────────────────────────────────────────
const props = defineProps({ barcodes: { type: Object, required: true } })

// ─── Toast ────────────────────────────────────────────────────────────────
const { success, error: showError } = useToast()

// ─── Bulk generate ────────────────────────────────────────────────────────
const generating = ref(false)

async function bulkGenerate() {
  generating.value = true
  try {
    const res = await window.axios.post('/api/v1/barcodes/bulk-generate')
    success(`Generated ${res.data.generated} barcodes successfully!`)
    router.reload()
  } catch (err) {
    showError(err.response?.data?.message || 'Failed to generate barcodes.')
  } finally {
    generating.value = false
  }
}

// ─── Row selection ────────────────────────────────────────────────────────
const selectedIds = ref(new Set())

const allSelected = computed(
  () => props.barcodes.data.length > 0 &&
        props.barcodes.data.every(b => selectedIds.value.has(b.id))
)
const someSelected = computed(
  () => !allSelected.value && props.barcodes.data.some(b => selectedIds.value.has(b.id))
)

function toggleAll() {
  if (allSelected.value) {
    selectedIds.value = new Set()
  } else {
    selectedIds.value = new Set(props.barcodes.data.map(b => b.id))
  }
}

function toggleRow(id) {
  const s = new Set(selectedIds.value)
  if (s.has(id)) s.delete(id)
  else s.add(id)
  selectedIds.value = s
}

// ─── Barcode detail popup ─────────────────────────────────────────────────
const detailBarcode = ref(null)

function openDetail(b) {
  detailBarcode.value = b
  nextTick(() => renderDetailBarcode(b))
}

function renderDetailBarcode(b) {
  const el = document.getElementById(`barcode-detail-${b.id}`)
  if (!el) return
  try {
    JsBarcode(el, b.barcode, {
      format: mapType(b.type),
      lineColor: '#1e293b',
      background: '#f8fafc',
      width: 2,
      height: 70,
      displayValue: false,
      margin: 8,
    })
  } catch {
    // QR or unrecognised type: show nothing
  }
}

watch(detailBarcode, (b) => {
  if (b) nextTick(() => renderDetailBarcode(b))
})

// ─── Print single label ───────────────────────────────────────────────────
function printSingle(b) {
  detailBarcode.value = null
  doPrint([b])
}

// ─── Print selected ───────────────────────────────────────────────────────
function printSelected() {
  const items = props.barcodes.data.filter(b => selectedIds.value.has(b.id))
  if (!items.length) return
  doPrint(items)
}

/**
 * Render barcode to an off-screen in-memory SVG and return its outerHTML.
 * JsBarcode works on detached SVG elements — no DOM insertion needed.
 */
function buildBarcodeSvgHtml(b) {
  const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg')
  try {
    JsBarcode(svg, b.barcode, {
      format:       mapType(b.type),
      lineColor:    '#000000',
      background:   '#ffffff',
      width:        2,
      height:       60,
      displayValue: false,
      margin:       6,
    })
  } catch {
    // QR or unrecognised format — leave SVG empty
  }
  return svg.outerHTML
}

/** Build a self-contained HTML document with all barcode labels. */
function buildPrintHtml(items) {
  const labels = items.map(b => {
    const svgHtml    = buildBarcodeSvgHtml(b)
    const variantRow = b.variant
      ? `<p class="variant">${escHtml(b.variant.variant_name ?? '')}</p>`
      : ''
    return `
      <div class="label">
        ${svgHtml}
        <p class="product">${escHtml(b.product?.name ?? '')}</p>
        ${variantRow}
        <p class="code">${escHtml(b.barcode)}</p>
      </div>`
  }).join('\n')

  return `<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Barcode Labels</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: Arial, Helvetica, sans-serif; background: #fff; }
    .wrapper { display: flex; flex-wrap: wrap; gap: 14px; padding: 20px; }
    .label {
      display: flex; flex-direction: column; align-items: center;
      border: 1px solid #cbd5e1; border-radius: 6px;
      padding: 10px 14px; page-break-inside: avoid; break-inside: avoid;
      min-width: 150px; max-width: 200px;
    }
    .label svg { max-width: 170px; width: 100%; }
    .product { font-size: 10px; font-weight: 600; color: #1e293b; margin-top: 5px; text-align: center; }
    .variant { font-size: 9px; color: #64748b; margin-top: 1px; text-align: center; }
    .code    { font-size: 9px; font-family: monospace; color: #475569; margin-top: 3px; letter-spacing: 0.05em; }
    @media print {
      body  { margin: 0; }
      .wrapper { padding: 10px; gap: 10px; }
    }
  </style>
</head>
<body>
  <div class="wrapper">${labels}</div>
</body>
</html>`
}

/**
 * Inject a hidden iframe, write the label HTML into it, then trigger the
 * browser's native print dialog.  No popup window — no popup-blocker issues.
 */
function doPrint(items) {
  // Remove any leftover iframe from a previous print
  document.getElementById('__barcode_print_iframe')?.remove()

  const iframe = document.createElement('iframe')
  iframe.id = '__barcode_print_iframe'
  // Keep it off-screen; the print dialog reads the document, not the screen rect
  iframe.style.cssText =
    'position:fixed;top:0;left:0;width:0;height:0;border:0;opacity:0;pointer-events:none;'
  document.body.appendChild(iframe)

  const doc = iframe.contentDocument ?? iframe.contentWindow.document
  doc.open()
  doc.write(buildPrintHtml(items))
  doc.close()

  // Small delay lets the browser parse & lay out the iframe document
  setTimeout(() => {
    iframe.contentWindow?.focus()
    iframe.contentWindow?.print()
    // Remove the iframe after a grace period so the print dialog can finish
    setTimeout(() => document.getElementById('__barcode_print_iframe')?.remove(), 2000)
  }, 250)
}

/** Minimal HTML escaping for label text. */
function escHtml(str) {
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
}

// ─── Settings popup ───────────────────────────────────────────────────────
const showSettings  = ref(false)
const settingsLoading = ref(false)
const settingsSaving  = ref(false)
const settingsForm  = ref({
  prefix:           'ZAM',
  suffix:           '',
  separator:        '',
  include_year:     false,
  include_month:    false,
  sequence_digits:  6,
  sequence_start:   1,
  current_sequence: 1,
  reset_annually:   false,
})
const settingsPreview = ref('ZAM000001')

async function openSettings() {
  showSettings.value = true
  settingsLoading.value = true
  try {
    const res = await window.axios.get('/api/v1/barcodes/settings')
    const d = res.data
    settingsForm.value = {
      prefix:           d.prefix           ?? 'ZAM',
      suffix:           d.suffix           ?? '',
      separator:        d.separator        ?? '',
      include_year:     !! d.include_year,
      include_month:    !! d.include_month,
      sequence_digits:  d.sequence_digits  ?? 6,
      sequence_start:   d.sequence_start   ?? 1,
      current_sequence: d.current_sequence ?? 1,
      reset_annually:   !! d.reset_annually,
    }
    settingsPreview.value = d.preview_example ?? buildPreview()
  } catch (err) {
    showError('Failed to load settings.')
  } finally {
    settingsLoading.value = false
  }
}

function refreshPreview() {
  settingsPreview.value = buildPreview()
}

function buildPreview() {
  const f = settingsForm.value
  const sep   = f.separator ?? ''
  const parts = []
  if (f.prefix) parts.push(f.prefix)
  if (f.include_year) parts.push(new Date().getFullYear().toString())
  if (f.include_year && f.include_month) parts.push(String(new Date().getMonth() + 1).padStart(2, '0'))
  const digits = Math.max(1, f.sequence_digits ?? 6)
  parts.push(String(f.sequence_start ?? 1).padStart(digits, '0'))
  let code = parts.join(sep)
  if (f.suffix) code += f.suffix
  return code
}

async function saveSettings() {
  settingsSaving.value = true
  try {
    const res = await window.axios.put('/api/v1/barcodes/settings', settingsForm.value)
    settingsPreview.value = res.data.preview_example ?? buildPreview()
    success('Barcode sequence settings saved!')
    showSettings.value = false
  } catch (err) {
    const errors = err.response?.data?.errors
    if (errors) {
      const msg = Object.values(errors).flat().join(', ')
      showError(msg)
    } else {
      showError(err.response?.data?.message || 'Failed to save settings.')
    }
  } finally {
    settingsSaving.value = false
  }
}

// ─── Helper ───────────────────────────────────────────────────────────────
function mapType(type) {
  const map = { ean13: 'EAN13', code128: 'CODE128', custom: 'CODE128' }
  return map[type] ?? 'CODE128'
}
</script>
