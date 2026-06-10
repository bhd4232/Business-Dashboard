import { defineStore } from 'pinia'
import { usePreferredDark } from '@vueuse/core'

// ── Theme color presets ──────────────────────────────────────────────────────
export const THEME_PRESETS = {
  indigo:  { 50: '#eef2ff', 100: '#e0e7ff', 500: '#6366f1', 600: '#4f46e5', 700: '#4338ca' },
  blue:    { 50: '#eff6ff', 100: '#dbeafe', 500: '#3b82f6', 600: '#2563eb', 700: '#1d4ed8' },
  violet:  { 50: '#f5f3ff', 100: '#ede9fe', 500: '#8b5cf6', 600: '#7c3aed', 700: '#6d28d9' },
  emerald: { 50: '#ecfdf5', 100: '#d1fae5', 500: '#10b981', 600: '#059669', 700: '#047857' },
  orange:  { 50: '#fff7ed', 100: '#ffedd5', 500: '#f97316', 600: '#ea580c', 700: '#c2410c' },
  rose:    { 50: '#fff1f2', 100: '#ffe4e6', 500: '#f43f5e', 600: '#e11d48', 700: '#be123c' },
  sky:     { 50: '#f0f9ff', 100: '#e0f2fe', 500: '#0ea5e9', 600: '#0284c7', 700: '#0369a1' },
  amber:   { 50: '#fffbeb', 100: '#fef3c7', 500: '#f59e0b', 600: '#d97706', 700: '#b45309' },
}

export const useThemeStore = defineStore('theme', {
  state: () => ({
    /** @type {string} */
    theme_color: 'indigo',
    /** @type {string|null} */
    accent_hex: null,
    /** @type {boolean} */
    dark_mode: false,
    /** @type {'light'|'dark'|'system'} */
    display_mode: 'system',
  }),

  getters: {
    /**
     * Returns the active primary shade map.
     * If accent_hex is set, returns it as the 500 shade only (others remain from preset).
     */
    primaryShades(state) {
      const base = THEME_PRESETS[state.theme_color] ?? THEME_PRESETS.indigo
      if (state.accent_hex) {
        return { ...base, 500: state.accent_hex, 600: state.accent_hex }
      }
      return base
    },
  },

  actions: {
    /**
     * Called from app.js with Inertia shared props on boot.
     */
    initFromProps(prefs) {
      if (!prefs) return
      this.theme_color = prefs.theme_color ?? 'indigo'
      this.accent_hex  = prefs.accent_hex  ?? null
      this.dark_mode   = prefs.dark_mode   ?? false
      // Determine display_mode from DB state
      if (!prefs.dark_mode) {
        this.display_mode = 'system'
      } else {
        this.display_mode = 'dark'
      }
      this.applyToDOM()
    },

    /**
     * Apply a preset theme color by name.
     */
    setTheme(colorName) {
      if (!THEME_PRESETS[colorName]) return
      this.theme_color = colorName
      this.accent_hex  = null
      this.applyToDOM()
    },

    /**
     * Apply a custom hex color as the primary accent.
     */
    setAccentHex(hex) {
      this.accent_hex = hex
      this.applyToDOM()
    },

    /**
     * Toggle dark mode on/off.
     */
    toggleDark() {
      this.dark_mode = !this.dark_mode
      this.display_mode = this.dark_mode ? 'dark' : 'light'
      this.applyToDOM()
    },

    /**
     * Set display mode: 'light', 'dark', or 'system'.
     */
    setDisplayMode(mode) {
      this.display_mode = mode
      if (mode === 'dark') {
        this.dark_mode = true
      } else if (mode === 'light') {
        this.dark_mode = false
      } else {
        // system: check OS preference
        const prefersDark = usePreferredDark()
        this.dark_mode = prefersDark.value
      }
      this.applyToDOM()
    },

    /**
     * Apply the current theme state to the DOM.
     * Sets <html> class for theme + dark mode.
     * Sets CSS variables for runtime color switching.
     */
    applyToDOM() {
      const html = document.documentElement

      // ── Theme color class ───────────────────────────────────────────────
      // Remove all existing theme classes
      Object.keys(THEME_PRESETS).forEach(name => {
        html.classList.remove(`theme-${name}`)
      })
      html.classList.add(`theme-${this.theme_color}`)

      // ── Custom hex override ─────────────────────────────────────────────
      if (this.accent_hex) {
        html.style.setProperty('--brand-50',  this.primaryShades[50])
        html.style.setProperty('--brand-100', this.primaryShades[100])
        html.style.setProperty('--brand-500', this.accent_hex)
        html.style.setProperty('--brand-600', this.accent_hex)
        html.style.setProperty('--brand-700', this.primaryShades[700])
      } else {
        // Clear any inline overrides — let CSS class handle it
        html.style.removeProperty('--brand-50')
        html.style.removeProperty('--brand-100')
        html.style.removeProperty('--brand-500')
        html.style.removeProperty('--brand-600')
        html.style.removeProperty('--brand-700')
      }

      // ── Dark mode class ─────────────────────────────────────────────────
      if (this.dark_mode) {
        html.classList.add('dark')
      } else {
        html.classList.remove('dark')
      }
    },
  },
})
