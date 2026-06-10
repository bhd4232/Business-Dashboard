import './bootstrap';
import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { createPinia } from 'pinia';
import { useThemeStore } from '@/Stores/useThemeStore';
import { usePreferredDark } from '@vueuse/core';

const appName = import.meta.env.VITE_APP_NAME || 'ZamZam ERP';

createInertiaApp({
    title: (title) => `${title} — ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        const pinia = createPinia()
        const app = createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .use(pinia)

        // ── Theme initialisation ──────────────────────────────────────────
        const themeStore = useThemeStore(pinia)
        const userPrefs  = props.initialPage?.props?.userPreferences

        if (userPrefs) {
            // User has saved preferences — apply them
            themeStore.initFromProps(userPrefs)
        } else {
            // New user: detect OS dark preference
            const prefersDark = usePreferredDark()
            themeStore.dark_mode   = prefersDark.value
            themeStore.display_mode = 'system'
            themeStore.applyToDOM()
        }

        return app.mount(el)
    },
    progress: {
        color: '#6366f1',
    },
});
