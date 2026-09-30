// Stylesheets are chosen per area in app.blade.php (public site vs admin).
// axios defaults are only needed by the admin.
if (window.location.pathname.startsWith('/admin')) import('./bootstrap');

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';

// Titles come from the server (SEO settings); keep the one already in the page otherwise.
createInertiaApp({
    title: (title) => title || document.title,
    resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
    progress: {
        color: '#1a66d2',
        showSpinner: false,
    },
});
