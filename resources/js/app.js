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

// The website's own visitor counter (App\Support\VisitorStats): one small signal per page view and
// per WhatsApp, call or chat click. Admin pages are not counted; counting never breaks the page.
window.__siteEvent = (type, withReferrer = false) => {
    try {
        if (location.pathname.startsWith('/admin') || navigator.webdriver) return;
        const body = JSON.stringify({
            t: type,
            p: location.pathname,
            z: Intl.DateTimeFormat().resolvedOptions().timeZone,
            r: withReferrer ? document.referrer : '',
            w: window.innerWidth,
        });
        if (!(navigator.sendBeacon && navigator.sendBeacon('/t', new Blob([body], { type: 'application/json' })))) {
            fetch('/t', { method: 'POST', body, headers: { 'Content-Type': 'application/json' }, keepalive: true }).catch(() => {});
        }
    } catch (e) {
        // ignore
    }
};
let firstSignal = true;
router.on('navigate', () => {
    const first = firstSignal;
    firstSignal = false;
    setTimeout(() => window.__siteEvent('visit', first), first ? 1500 : 300);
});

// GTM / GA4 page views for pages opened inside the site (the first page is counted on load).
let firstVisit = true;
router.on('navigate', () => {
    if (firstVisit) { firstVisit = false; return; }
    setTimeout(() => window.__trackPage?.(), 50); // after the new page title is set
});
