<template>
  <AppLayout>
    <Head title="Settings" />

    <!-- Page Header -->
    <div class="mb-6 flex items-center gap-3">
      <ThreeDIcon name="settings" size="lg" />
      <div>
        <h1 class="text-xl font-bold text-slate-900 dark:text-slate-100">Settings</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">Customize your appearance and preferences</p>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

      <!-- ── Left: Settings panels ──────────────────────────────────────── -->
      <div class="lg:col-span-2 space-y-6">

        <!-- Accent Color Card -->
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6 shadow-sm">
          <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100 mb-1">Accent Color</h2>
          <p class="text-sm text-slate-500 dark:text-slate-400 mb-4">Choose a primary color for the interface.</p>

          <!-- Preset swatches -->
          <div class="flex flex-wrap gap-3 mb-5">
            <button
              v-for="(shades, name) in THEME_PRESETS"
              :key="name"
              @click="selectPreset(name)"
              :title="capitalize(name)"
              :style="{ backgroundColor: shades[500] }"
              :class="[
                'w-9 h-9 rounded-full border-2 transition-all duration-150 flex items-center justify-center',
                form.theme_color === name && !form.accent_hex
                  ? 'border-slate-900 dark:border-white scale-110 shadow-md'
                  : 'border-transparent hover:scale-105',
              ]"
            >
              <CheckIcon v-if="form.theme_color === name && !form.accent_hex" class="w-4 h-4 text-white drop-shadow" />
            </button>
          </div>

          <!-- Custom hex color picker -->
          <div class="border-t border-slate-100 dark:border-slate-700 pt-4">
            <label class="text-sm font-medium text-slate-700 dark:text-slate-300 block mb-2">Custom Color</label>
            <div class="flex items-center gap-3">
              <!-- Color input -->
              <div class="relative">
                <input
                  type="color"
                  :value="colorInputValue"
                  @input="onColorPickerInput"
                  class="w-10 h-10 rounded-lg border border-slate-200 dark:border-slate-600 cursor-pointer p-0.5 bg-white dark:bg-slate-700"
                />
              </div>
              <!-- Hex text input -->
              <input
                type="text"
                v-model="customHex"
                @input="onCustomHexInput"
                placeholder="#6366f1"
                maxlength="7"
                class="w-32 px-3 py-2 text-sm rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-800 dark:text-slate-200 font-mono focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-100 dark:focus:ring-primary-900/30 transition-all"
              />
              <!-- Clear custom -->
              <button
                v-if="form.accent_hex"
                @click="clearCustomHex"
                class="text-xs text-slate-500 dark:text-slate-400 hover:text-red-500 transition-colors"
              >
                Reset
              </button>
              <!-- Preview strip — always shows the current active primary color -->
              <div
                class="flex-1 h-8 rounded-lg border border-slate-200 dark:border-slate-600 transition-all duration-200"
                :style="{ backgroundColor: previewColor }"
              ></div>
            </div>
          </div>
        </div>

        <!-- Display Mode Card -->
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6 shadow-sm">
          <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100 mb-1">Display Mode</h2>
          <p class="text-sm text-slate-500 dark:text-slate-400 mb-4">Choose between light, dark, or system preference.</p>

          <div class="flex gap-3">
            <button
              v-for="mode in displayModes"
              :key="mode.value"
              @click="selectDisplayMode(mode.value)"
              :class="[
                'flex-1 flex flex-col items-center gap-2 py-4 px-3 rounded-xl border-2 transition-all duration-150 text-center',
                form.display_mode === mode.value
                  ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20 text-primary-700 dark:text-primary-300'
                  : 'border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-700/50 text-slate-600 dark:text-slate-400 hover:border-slate-300 dark:hover:border-slate-600',
              ]"
            >
              <span class="text-2xl">{{ mode.emoji }}</span>
              <span class="text-xs font-semibold">{{ mode.label }}</span>
            </button>
          </div>
        </div>

        <!-- Save Button -->
        <div class="flex items-center justify-between">
          <p v-if="saveStatus === 'saved'" class="text-sm text-emerald-600 dark:text-emerald-400 flex items-center gap-1.5">
            <CheckCircleIcon class="w-4 h-4" /> Preferences saved
          </p>
          <p v-else-if="saveStatus === 'error'" class="text-sm text-red-600 dark:text-red-400">
            Failed to save. Please try again.
          </p>
          <div v-else></div>

          <button
            @click="savePreferences"
            :disabled="saving"
            class="inline-flex items-center gap-2 px-5 py-2.5 bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold rounded-xl transition-colors disabled:opacity-50 disabled:cursor-not-allowed shadow-sm"
          >
            <LoaderIcon v-if="saving" class="w-4 h-4 animate-spin" />
            <Icon3D v-else name="Save" size="sm" color="text-white" />
            {{ saving ? 'Saving...' : 'Save Preferences' }}
          </button>
        </div>

      </div>

      <!-- ── Right: Live Preview ──────────────────────────────────────────── -->
      <div class="space-y-4">
        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-4 shadow-sm">
          <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300 mb-3">Live Preview</h2>

          <!-- Mini sidebar preview -->
          <div
            :class="[
              'rounded-xl overflow-hidden border transition-all duration-300',
              isPreviewDark
                ? 'bg-slate-900 border-slate-700'
                : 'bg-white border-slate-200',
            ]"
          >
            <!-- Mini top bar -->
            <div
              :class="[
                'h-8 flex items-center px-3 gap-2 border-b',
                isPreviewDark ? 'border-slate-700 bg-slate-900' : 'border-slate-200 bg-white',
              ]"
            >
              <div class="w-2 h-2 rounded-full" :style="{ backgroundColor: previewColor }"></div>
              <div :class="['h-1.5 rounded flex-1', isPreviewDark ? 'bg-slate-700' : 'bg-slate-100']"></div>
              <div class="w-4 h-4 rounded-full" :style="{ backgroundColor: previewColor }"></div>
            </div>

            <div class="flex">
              <!-- Mini sidebar -->
              <div
                :class="[
                  'w-16 p-2 space-y-1 border-r',
                  isPreviewDark ? 'border-slate-700 bg-slate-900' : 'border-slate-200 bg-white',
                ]"
              >
                <div class="h-2 rounded" :style="{ backgroundColor: previewColor, opacity: 0.15 }"></div>
                <div class="h-2 rounded" :style="{ backgroundColor: previewColor }"></div>
                <div :class="['h-2 rounded', isPreviewDark ? 'bg-slate-700' : 'bg-slate-100']"></div>
                <div :class="['h-2 rounded', isPreviewDark ? 'bg-slate-700' : 'bg-slate-100']"></div>
              </div>

              <!-- Mini content -->
              <div
                :class="[
                  'flex-1 p-2 space-y-1.5',
                  isPreviewDark ? 'bg-slate-800' : 'bg-slate-50',
                ]"
              >
                <!-- Stat cards row -->
                <div class="grid grid-cols-2 gap-1.5">
                  <div
                    v-for="i in 2"
                    :key="i"
                    :class="[
                      'rounded p-1.5',
                      isPreviewDark ? 'bg-slate-700' : 'bg-white border border-slate-200',
                    ]"
                  >
                    <div class="w-3 h-3 rounded-sm mb-1" :style="{ backgroundColor: previewColor, opacity: 0.3 }"></div>
                    <div :class="['h-1.5 rounded w-full mb-0.5', isPreviewDark ? 'bg-slate-600' : 'bg-slate-200']"></div>
                    <div :class="['h-1 rounded w-2/3', isPreviewDark ? 'bg-slate-600' : 'bg-slate-100']"></div>
                  </div>
                </div>
                <!-- A button preview -->
                <div class="h-4 rounded-md w-16 mt-1" :style="{ backgroundColor: previewColor }"></div>
              </div>
            </div>
          </div>

          <!-- Color swatch display -->
          <div class="mt-3 flex gap-1.5">
            <div
              v-for="shade in [50, 100, 500, 600, 700]"
              :key="shade"
              class="flex-1 h-5 rounded text-center text-[8px] leading-5 font-mono overflow-hidden"
              :style="{ backgroundColor: currentShades[shade] }"
            ></div>
          </div>
          <div class="flex gap-1.5 mt-0.5">
            <div v-for="shade in [50, 100, 500, 600, 700]" :key="shade" class="flex-1 text-center text-[8px] text-slate-400">{{ shade }}</div>
          </div>
        </div>

        <!-- Current settings summary -->
        <div class="bg-slate-50 dark:bg-slate-700/50 rounded-xl border border-slate-200 dark:border-slate-700 p-4">
          <h3 class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide mb-2">Current</h3>
          <div class="space-y-1.5 text-sm">
            <div class="flex items-center justify-between">
              <span class="text-slate-600 dark:text-slate-400">Theme</span>
              <span class="font-medium text-slate-900 dark:text-slate-100 capitalize">
                {{ form.accent_hex ? `Custom (${form.accent_hex})` : form.theme_color }}
              </span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-slate-600 dark:text-slate-400">Mode</span>
              <span class="font-medium text-slate-900 dark:text-slate-100 capitalize">{{ form.display_mode }}</span>
            </div>
          </div>
        </div>
      </div>

    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { Head } from '@inertiajs/vue3'
import { CheckIcon, CheckCircleIcon, LoaderIcon } from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'
import ThreeDIcon from '@/Components/UI/ThreeDIcon.vue'
import Icon3D from '@/Components/UI/Icon3D.vue'
import { useThemeStore, THEME_PRESETS } from '@/Stores/useThemeStore'

const props = defineProps({
  preferences: { type: Object, required: true },
})

const themeStore = useThemeStore()

// ── Form state ──────────────────────────────────────────────────────────────
const form = ref({
  theme_color:  props.preferences.theme_color  ?? 'indigo',
  accent_hex:   props.preferences.accent_hex   ?? null,
  dark_mode:    props.preferences.dark_mode    ?? false,
  display_mode: themeStore.display_mode        ?? 'system',
})

const customHex = ref(form.value.accent_hex ?? '')
const saving    = ref(false)
const saveStatus = ref(null) // null | 'saved' | 'error'

// ── Display modes ────────────────────────────────────────────────────────────
const displayModes = [
  { value: 'light',  label: 'Light',  emoji: '☀️' },
  { value: 'dark',   label: 'Dark',   emoji: '🌙' },
  { value: 'system', label: 'System', emoji: '💻' },
]

// ── Preview computed ─────────────────────────────────────────────────────────
const currentShades = computed(() => {
  if (form.value.accent_hex) {
    const base = THEME_PRESETS[form.value.theme_color] ?? THEME_PRESETS.indigo
    return { ...base, 500: form.value.accent_hex, 600: form.value.accent_hex }
  }
  return THEME_PRESETS[form.value.theme_color] ?? THEME_PRESETS.indigo
})

const previewColor = computed(() => currentShades.value[500])

// Always returns a valid #rrggbb for <input type="color"> — avoids browser warning
const colorInputValue = computed(() =>
  /^#[0-9A-Fa-f]{6}$/.test(customHex.value)
    ? customHex.value
    : currentShades.value[500]
)

const isPreviewDark = computed(() => {
  if (form.value.display_mode === 'dark') return true
  if (form.value.display_mode === 'light') return false
  return themeStore.dark_mode
})

// ── Actions ──────────────────────────────────────────────────────────────────
function selectPreset(name) {
  form.value.theme_color = name
  form.value.accent_hex  = null
  customHex.value        = ''
  themeStore.setTheme(name)
}

function onCustomHexInput() {
  const val = customHex.value.trim()
  if (/^#[0-9A-Fa-f]{6}$/.test(val)) {
    form.value.accent_hex = val
    themeStore.setAccentHex(val)
  }
}

/** Handler for <input type="color"> — value is always a valid #rrggbb */
function onColorPickerInput(e) {
  customHex.value       = e.target.value
  form.value.accent_hex = e.target.value
  themeStore.setAccentHex(e.target.value)
}

function clearCustomHex() {
  customHex.value       = ''
  form.value.accent_hex = null
  themeStore.setTheme(form.value.theme_color)
}

function selectDisplayMode(mode) {
  form.value.display_mode = mode
  themeStore.setDisplayMode(mode)
  form.value.dark_mode = themeStore.dark_mode
}

function capitalize(str) {
  return str.charAt(0).toUpperCase() + str.slice(1)
}

async function savePreferences() {
  saving.value    = true
  saveStatus.value = null

  try {
    await window.axios.put('/api/v1/user/preferences', {
      theme_color: form.value.theme_color,
      accent_hex:  form.value.accent_hex || null,
      dark_mode:   form.value.dark_mode,
    })
    saveStatus.value = 'saved'
    setTimeout(() => { saveStatus.value = null }, 3000)
  } catch {
    saveStatus.value = 'error'
  } finally {
    saving.value = false
  }
}
</script>
