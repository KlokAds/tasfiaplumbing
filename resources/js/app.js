// Stylesheets are chosen per area in app.blade.php (public site vs admin).
// axios defaults are only needed by the admin.
if (window.location.pathname.startsWith('/admin')) import('./bootstrap');

import { createApp, h } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
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

// GTM / GA4 page views for pages opened inside the site (the first page is counted on load).
let firstVisit = true;
router.on('navigate', () => {
    if (firstVisit) { firstVisit = false; return; }
    setTimeout(() => window.__trackPage?.(), 50); // after the new page title is set
});
