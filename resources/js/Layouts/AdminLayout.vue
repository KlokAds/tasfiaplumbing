<template>
  <div class="admin-ui min-h-screen flex">
    <div v-if="sidebarOpen" @click="sidebarOpen = false" class="fixed inset-0 z-40 bg-black/50 backdrop-blur-[1px] lg:hidden"></div>

    <!-- ============ Sidebar ============ -->
    <aside :class="['a-side fixed inset-y-0 left-0 z-50 w-[16rem] flex flex-col transition-transform duration-200 lg:translate-x-0', sidebarOpen ? 'translate-x-0' : '-translate-x-full']">
      <div class="h-[4.5rem] px-5 flex items-center gap-3 shrink-0">
        <Link href="/admin/dashboard" class="flex items-center gap-3 min-w-0">
          <span class="w-11 h-11 rounded-full bg-white flex items-center justify-center shrink-0 p-1">
            <img src="/logo.png" alt="" class="w-full h-full object-contain" />
          </span>
          <span class="min-w-0 leading-tight">
            <span class="block text-[15px] font-semibold truncate a-display text-white">{{ page.props.admin?.brand }}</span>
            <span class="block text-[11.5px] text-white/50">Plumbing admin</span>
          </span>
        </Link>
      </div>

      <div class="px-4 pb-2">
        <button @click="openSearch" class="a-side-search w-full flex items-center gap-2 px-3.5 py-2.5 rounded-full text-[13px] transition-colors">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" :d="icons.search" /></svg>
          <span class="flex-1 text-left">Jump to…</span>
          <span class="a-kbd">Ctrl K</span>
        </button>
      </div>

      <nav class="flex-1 overflow-y-auto a-scroll px-4 py-4 space-y-6">
        <div v-for="group in visibleGroups" :key="group.label">
          <p class="a-nav-label">{{ group.label }}</p>
          <Link v-for="item in group.items" :key="item.label" :href="item.href" @click="sidebarOpen = false"
            :class="['a-nav-item', isActive(item) && 'is-active']">
            <span class="flex items-center gap-2.5 min-w-0">
              <svg class="w-[17px] h-[17px] shrink-0 opacity-70" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" :d="icons[item.icon]" /></svg>
              <span class="truncate">{{ item.label }}</span>
            </span>
            <span v-if="item.badge || item.count != null" class="flex items-center gap-1 shrink-0">
              <span v-if="item.badge" :title="item.badgeTitle" :class="['min-w-5 px-1.5 rounded-full text-[10.5px] font-bold text-center leading-[18px]', item.alert ? 'bg-[#f59e0b] text-[#1f1300]' : 'bg-white/10 text-white/70']">{{ item.badge }}</span>
              <span v-if="item.count != null" class="min-w-5 px-1.5 rounded-full text-[10.5px] font-semibold text-center leading-[18px] bg-white/10 text-white/75 tabular-nums">{{ Number(item.count).toLocaleString() }}</span>
            </span>
          </Link>
        </div>
      </nav>

      <div class="p-4 border-t border-white/10">
        <Link href="/admin/account" class="a-side-user flex items-center gap-2.5 p-2 rounded-full">
          <Avatar :name="user?.name" :image="admin.user?.image" size="sm" />
          <span class="min-w-0 leading-tight">
            <span class="block text-[13px] font-semibold truncate text-white">{{ user?.name }}</span>
            <span class="block text-[11px] text-white/50 truncate">{{ admin.role || 'No role' }}</span>
          </span>
        </Link>
      </div>
    </aside>

    <!-- ============ Main ============ -->
    <div class="flex-1 min-w-0 lg:pl-[16rem] flex flex-col">
      <header class="sticky top-0 z-30 h-16 px-4 sm:px-6 lg:px-8 flex items-center justify-between gap-4 border-b a-border"
        style="background: var(--a-panel)">
        <div class="flex items-center gap-3 min-w-0">
          <button @click="sidebarOpen = true" class="lg:hidden a-btn-ghost a-btn-icon" aria-label="Open menu">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16" /></svg>
          </button>
          <div class="min-w-0">
            <p class="text-[11px] a-subtle truncate">{{ breadcrumb }}</p>
            <h1 class="text-[18px] font-semibold truncate a-display">{{ title }}</h1>
          </div>
        </div>

        <div class="flex items-center gap-1 sm:gap-2">
          <a href="/" target="_blank" class="hidden md:inline-flex a-btn-ghost a-btn-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" /></svg>
            View site
          </a>

          <!-- Theme -->
          <div class="a-seg hidden sm:inline-flex" role="radiogroup" aria-label="Theme">
            <button v-for="opt in themeOptions" :key="opt.value" type="button" role="radio" :aria-checked="theme === opt.value" :title="opt.label"
              @click="setTheme(opt.value)" :class="['!px-2', theme === opt.value && 'is-on']">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" :d="opt.icon" /></svg>
            </button>
          </div>

          <!-- Notifications -->
          <div class="relative" ref="bellRef">
            <button @click="bellOpen = !bellOpen" class="relative a-btn-ghost a-btn-icon" aria-label="Notifications">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 00-4-5.7V5a2 2 0 10-4 0v.3C7.7 6.2 6 8.4 6 11v3.2c0 .5-.2 1-.6 1.4L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" /></svg>
              <span v-if="notificationCount" class="absolute top-0.5 right-0.5 min-w-4 h-4 px-1 rounded-full bg-[var(--a-accent)] text-white text-[9px] font-bold flex items-center justify-center ring-2 ring-[var(--a-bg)]">{{ notificationCount > 99 ? '99+' : notificationCount }}</span>
            </button>
            <transition enter-active-class="transition duration-150" enter-from-class="opacity-0 -translate-y-1" leave-active-class="transition duration-100" leave-to-class="opacity-0">
              <div v-if="bellOpen" class="absolute right-0 mt-2 w-[22rem] max-w-[calc(100vw-2rem)] admin-card p-2" style="box-shadow: var(--a-shadow-lg)">
                <div class="flex items-center justify-between px-2.5 pt-1 pb-2">
                  <span class="text-sm font-bold">Notifications</span>
                  <button v-if="notes.unread" @click="router.post('/admin/notifications/read-all', {}, { preserveScroll: true })" class="text-xs font-semibold a-accent">Mark all read</button>
                </div>
                <div v-if="notes.items?.length" class="max-h-72 overflow-y-auto a-scroll">
                  <a v-for="n in notes.items" :key="n.id" :href="`/admin/notifications/${n.id}`" class="flex gap-2.5 px-2.5 py-2 rounded-lg a-hover">
                    <span :class="['mt-1.5 a-dot', n.read ? 'opacity-0' : n.level === 'danger' ? 'text-[var(--a-danger)]' : n.level === 'warning' ? 'text-[var(--a-warning)]' : 'text-[var(--a-success)]']"></span>
                    <span class="min-w-0">
                      <span :class="['block text-[13px] leading-snug', !n.read && 'font-semibold']">{{ n.title }}</span>
                      <span class="block text-xs a-muted truncate">{{ n.body }}</span>
                      <span class="block text-[11px] a-subtle mt-0.5">{{ timeAgo(n.at) }}</span>
                    </span>
                  </a>
                </div>
                <div v-if="waiting.length" class="mt-1 pt-1.5 border-t a-border">
                  <p class="px-2.5 py-1 text-[11px] font-bold uppercase tracking-wider a-subtle">Waiting for you</p>
                  <Link v-for="w in waiting" :key="w.href" :href="w.href" class="a-menu-item" @click="bellOpen = false">
                    <span>{{ w.label }}</span><span :class="['a-badge', w.cls]">{{ w.count }}</span>
                  </Link>
                </div>
                <p v-if="!notes.items?.length && !waiting.length" class="px-3 py-6 text-sm a-muted text-center">You're all caught up.</p>
              </div>
            </transition>
          </div>

          <!-- User -->
          <div class="relative" ref="userRef">
            <button @click="userOpen = !userOpen" class="flex items-center gap-2 p-1 rounded-full a-hover" aria-label="Account menu">
              <Avatar :name="user?.name" :image="admin.user?.image" size="sm" />
            </button>
            <transition enter-active-class="transition duration-150" enter-from-class="opacity-0 -translate-y-1" leave-active-class="transition duration-100" leave-to-class="opacity-0">
              <div v-if="userOpen" class="absolute right-0 mt-2 w-60 admin-card p-1.5" style="box-shadow: var(--a-shadow-lg)">
                <div class="px-2.5 py-2 mb-1 border-b a-border">
                  <p class="text-sm font-semibold truncate">{{ user?.name }}</p>
                  <p class="text-xs a-subtle truncate">{{ user?.email }}</p>
                </div>
                <Link href="/admin/account" class="a-menu-item" @click="userOpen = false">My account</Link>
                <button v-if="can['system.cache']" type="button" @click="quickClear" class="a-menu-item">Clear website cache</button>
                <div class="sm:hidden px-2.5 py-2">
                  <p class="text-[11px] a-subtle mb-1.5">Theme</p>
                  <div class="a-seg w-full">
                    <button v-for="opt in themeOptions" :key="opt.value" type="button" @click="setTheme(opt.value)" :class="['flex-1', theme === opt.value && 'is-on']">{{ opt.label }}</button>
                  </div>
                </div>
                <a href="/" target="_blank" class="a-menu-item md:hidden">View site</a>
                <div class="my-1 border-t a-border"></div>
                <button type="button" @click="logout" class="a-menu-item a-text-danger">Sign out</button>
              </div>
            </transition>
          </div>
        </div>
      </header>

      <!-- Section tabs stay under the top bar while scrolling -->
      <div v-if="tabs.length > 1" data-admin-tabs class="sticky top-16 z-[25] px-4 sm:px-6 lg:px-8 border-b a-border" style="background: color-mix(in srgb, var(--a-bg) 92%, transparent); backdrop-filter: blur(8px)">
        <nav class="a-tabs !border-0 max-w-[1400px] mx-auto">
          <Link v-for="tab in tabs" :key="tab.href" :href="tab.href" :class="['a-tab', isTabActive(tab) && 'a-tab-active']">{{ tab.label }}</Link>
        </nav>
      </div>

      <main class="flex-1 px-4 py-6 sm:px-6 lg:px-8">
        <div class="max-w-[1400px] mx-auto">
          <slot />
        </div>
      </main>
    </div>

    <!-- ============ Jump to (Ctrl+K) ============ -->
    <div v-if="searchOpen" class="fixed inset-0 z-[80] flex items-start justify-center p-4 pt-[12vh]" @keydown.esc="searchOpen = false">
      <div class="absolute inset-0 bg-black/50" @click="searchOpen = false"></div>
      <div class="relative w-full max-w-lg admin-card overflow-hidden" style="box-shadow: var(--a-shadow-lg)">
        <div class="flex items-center gap-2 px-4 border-b a-border">
          <svg class="w-4 h-4 a-subtle" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" :d="icons.search" /></svg>
          <input ref="searchInput" v-model="searchQuery" @keydown.down.prevent="searchIndex = Math.min(searchIndex + 1, searchResults.length - 1)" @keydown.up.prevent="searchIndex = Math.max(searchIndex - 1, 0)" @keydown.enter.prevent="goSearch(searchResults[searchIndex])"
            type="text" placeholder="Go to a page…" class="flex-1 py-3.5 bg-transparent outline-none text-[15px]" />
          <span class="a-kbd">Esc</span>
        </div>
        <ul class="max-h-80 overflow-y-auto a-scroll p-1.5">
          <li v-for="(r, i) in searchResults" :key="r.href + r.label">
            <button @click="goSearch(r)" @mousemove="searchIndex = i" :class="['w-full flex items-center justify-between gap-3 px-3 py-2 rounded-lg text-left text-sm', i === searchIndex && 'a-panel-3']">
              <span class="flex items-center gap-2.5"><svg class="w-4 h-4 a-subtle" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" :d="icons[r.icon] || icons.arrow" /></svg>{{ r.label }}</span>
              <span class="text-xs a-subtle">{{ r.group }}</span>
            </button>
          </li>
          <li v-if="!searchResults.length" class="px-3 py-6 text-center text-sm a-muted">Nothing found.</li>
        </ul>
      </div>
    </div>

    <!-- ============ Toasts ============ -->
    <!-- Just under the top bar, right side; errors stay until closed, success fades after a few seconds -->
    <div class="fixed top-[4.75rem] right-4 z-[90] space-y-2 w-[min(92vw,26rem)]" aria-live="polite">
      <transition-group enter-active-class="transition duration-200 ease-out" enter-from-class="opacity-0 -translate-y-2" leave-active-class="transition duration-150" leave-to-class="opacity-0 translate-x-4">
        <div v-for="t in toasts" :key="t.id" :class="['admin-card flex items-start gap-3 px-4 py-3 border-l-4', t.type === 'error' ? '!border-l-[var(--a-danger)]' : t.type === 'info' ? '!border-l-[var(--a-info)]' : '!border-l-[var(--a-success)]']" style="box-shadow: var(--a-shadow-lg)" :role="t.type === 'error' ? 'alert' : 'status'">
          <span :class="['w-7 h-7 rounded-full flex items-center justify-center shrink-0', t.type === 'error' ? 'a-tint-danger a-text-danger' : t.type === 'info' ? 'a-tint-info a-text-info' : 'a-tint-success a-text-success']">
            <svg v-if="t.type === 'info'" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 16v-4m0-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            <svg v-else-if="t.type === 'error'" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v5m0 3h.01" /></svg>
            <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
          </span>
          <p class="text-sm flex-1 whitespace-pre-line pt-1">{{ t.message }}</p>
          <button @click="dismiss(t.id)" class="a-subtle a-hover-text text-sm pt-1" aria-label="Dismiss">✕</button>
        </div>
      </transition-group>
    </div>

    <ConfirmDialog />
  </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import ConfirmDialog from '@/Components/Admin/ConfirmDialog.vue';
import Avatar from '@/Components/Admin/Avatar.vue';
import { confirmDialog } from '@/Composables/useConfirm';
import { useToast } from '@/Composables/useToast';

defineProps({ title: { type: String, default: 'Dashboard' } });

const page = usePage();
const sidebarOpen = ref(false);
const admin = computed(() => page.props.admin || {});
const counts = computed(() => admin.value.counts || {});
const can = computed(() => admin.value.can || {});
const notes = computed(() => admin.value.notifications || { unread: 0, items: [] });
const user = computed(() => page.props.auth?.user);

function timeAgo(iso) {
  if (!iso) return '';
  const d = (Date.now() - new Date(iso).getTime()) / 1000;
  if (d < 60) return 'just now';
  if (d < 3600) return Math.floor(d / 60) + ' min ago';
  if (d < 86400) return Math.floor(d / 3600) + ' h ago';
  return new Date(iso).toLocaleDateString('en-SG', { day: 'numeric', month: 'short' });
}

// ---------- Navigation ----------
// Each item may carry tabs: related screens shown as tabs under the header.
// An item is visible when the user may open at least one of its screens.
const groups = computed(() => {
  const c = counts.value;
  return [
    { label: 'Overview', items: [
      { label: 'Dashboard', icon: 'home', exact: true, tabs: [{ label: 'Dashboard', href: '/admin/dashboard' }] },
      { label: 'Website checklist', icon: 'check', badge: c.checklist, alert: true, tabs: [{ label: 'Website checklist', href: '/admin/checklist', can: 'settings.view' }] },
      { label: 'Enquiries', icon: 'mail', badge: c.unread, alert: true, tabs: [{ label: 'Enquiries', href: '/admin/messages', can: 'enquiries.view' }] },
    ] },
    { label: 'Content', items: [
      { label: 'Articles', icon: 'doc', badge: c.review, badgeTitle: 'Waiting for approval', alert: true, count: c.articles, tabs: [{ label: 'Articles', href: '/admin/blogs', can: 'articles.create' }, { label: 'Audit', href: '/admin/blogs-audit', can: 'articles.publish' }] },
      { label: 'Services', icon: 'wrench', count: c.services, tabs: [
        { label: 'Services', href: '/admin/services', can: 'services.view' },
        { label: 'Categories', href: '/admin/service-categories', can: 'categories.view' },
        { label: 'Price list', href: '/admin/pricing', can: 'pricing.view' },
        { label: 'FAQs', href: '/admin/faqs', can: 'faqs.view' },
      ] },
      { label: 'Locations', icon: 'pin', count: c.locations, tabs: [{ label: 'Locations', href: '/admin/locations', can: 'locations.view' }] },
      { label: 'Projects', icon: 'briefcase', count: c.projects, tabs: [{ label: 'Projects', href: '/admin/projects', can: 'projects.view' }] },
      { label: 'Reviews', icon: 'star', count: c.reviews, tabs: [{ label: 'Reviews', href: '/admin/reviews', can: 'reviews.view' }] },
    ] },
    { label: 'Website', items: [
      { label: 'Homepage', icon: 'layout', tabs: [
        { label: 'Hero', href: '/admin/hero', can: 'homepage.view' },
        { label: 'Sections & counters', href: '/admin/home-static', can: 'homepage.view' },
        { label: 'Partner logos', href: '/admin/partners', can: 'homepage.view' },
        { label: 'Website text', href: '/admin/website-text', can: 'homepage.view' },
      ] },
      { label: 'About page', icon: 'info', tabs: [{ label: 'About page', href: '/admin/about', can: 'about.view' }] },
      { label: 'Page banners', icon: 'photo', tabs: [{ label: 'Page banners', href: '/admin/breadcrumbs', can: 'banners.view' }] },
      { label: 'Media library', icon: 'image', tabs: [{ label: 'Media library', href: '/admin/media', can: 'media.view' }] },
    ] },
    { label: 'Insights', items: [
      { label: 'Search & visitors', icon: 'chart', tabs: [
        { label: 'Reports', href: '/admin/insights', can: 'analytics.view', exact: true },
        { label: 'Index status', href: '/admin/insights/indexing', can: 'analytics.view' },
        { label: 'Google connections', href: '/admin/insights/google', can: 'analytics.connect' },
      ] },
    ] },
    { label: 'SEO', items: [
      { label: 'Writing guide', icon: 'doc', tabs: [{ label: 'Writing guide', href: '/admin/seo/guide' }] },
      { label: 'SEO Health', icon: 'pulse', tabs: [{ label: 'SEO Health', href: '/admin/seo/health', can: 'seo_health.view' }] },
      { label: 'Page SEO', icon: 'search', tabs: [{ label: 'Page SEO', href: '/admin/page-seo', can: 'page_seo.view' }] },
      { label: 'Redirects & 404s', icon: 'arrow', badge: c.open_404s, tabs: [{ label: 'Redirects', href: '/admin/redirects', can: 'redirects.view' }] },
      { label: 'Schema & robots', icon: 'shield', tabs: [{ label: 'Schema & robots', href: '/admin/seo/settings', can: 'seo_settings.view' }] },
    ] },
    { label: 'Settings', items: [
      { label: 'Business settings', icon: 'cog', tabs: [
        { label: 'Contact & social', href: '/admin/settings/contact', can: 'settings.view' },
        { label: 'Identity & hours', href: '/admin/settings/business', can: 'settings.view' },
        { label: 'Logo, footer & tracking', href: '/admin/settings/footer', can: 'settings.view' },
      ] },
      { label: 'Team & roles', icon: 'users', tabs: [
        { label: 'Users', href: '/admin/users', can: 'users.view' },
        { label: 'Roles & permissions', href: '/admin/roles', can: 'roles.view' },
      ] },
      { label: 'System', icon: 'server', tabs: [
        { label: 'Site status & email', href: '/admin/system/settings', can: 'system.settings' },
        { label: 'Cache', href: '/admin/system/cache', can: 'system.cache' },
        { label: 'Updates', href: '/admin/system/update', can: 'system.update' },
      ] },
    ] },
  ];
});

const allowed = t => !t.can || can.value[t.can];
const visibleGroups = computed(() => groups.value
  .map(g => ({
    ...g,
    items: g.items
      .map(i => ({ ...i, tabs: i.tabs.filter(allowed) }))
      .filter(i => i.tabs.length)
      .map(i => ({ ...i, href: i.tabs[0].href })),
  }))
  .filter(g => g.items.length));

const currentPath = computed(() => page.url.split('?')[0]);
const pathMatches = (href, exact) => exact ? currentPath.value === href : currentPath.value === href || currentPath.value.startsWith(href + '/');
const isActive = item => item.tabs.some(t => pathMatches(t.href, item.exact));
const activeItem = computed(() => {
  for (const g of visibleGroups.value) {
    const item = g.items.find(isActive);
    if (item) return { group: g.label, item };
  }
  return null;
});
const breadcrumb = computed(() => activeItem.value ? `${activeItem.value.group} / ${activeItem.value.item.label}` : 'Admin');
const tabs = computed(() => activeItem.value?.item.tabs || []);
const isTabActive = tab => pathMatches(tab.href, tab.exact);

// ---------- Jump to ----------
const searchOpen = ref(false);
const searchQuery = ref('');
const searchIndex = ref(0);
const searchInput = ref(null);
const searchIndexAll = computed(() => visibleGroups.value.flatMap(g => g.items.flatMap(i =>
  i.tabs.map(t => ({ label: i.tabs.length > 1 ? `${i.label}: ${t.label}` : i.label, href: t.href, icon: i.icon, group: g.label })))).concat([
  { label: 'My account', href: '/admin/account', icon: 'users', group: 'You' },
  ...(can.value['articles.create'] ? [{ label: 'Write a new article', href: '/admin/blogs?new=1', icon: 'doc', group: 'Action' }] : []),
]));
const searchResults = computed(() => {
  const q = searchQuery.value.trim().toLowerCase();
  const list = q ? searchIndexAll.value.filter(r => (r.label + ' ' + r.group).toLowerCase().includes(q)) : searchIndexAll.value;
  return list.slice(0, 12);
});
watch(searchQuery, () => { searchIndex.value = 0; });
async function openSearch() {
  searchQuery.value = '';
  searchOpen.value = true;
  sidebarOpen.value = false;
  await nextTick();
  searchInput.value?.focus();
}
function goSearch(r) {
  if (!r) return;
  searchOpen.value = false;
  router.visit(r.href);
}
function onKey(e) {
  if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); openSearch(); }
  if (e.key === 'Escape') { searchOpen.value = false; bellOpen.value = false; userOpen.value = false; }
}

// ---------- Theme ----------
const themeOptions = [
  { value: 'light', label: 'Light', icon: 'M12 3v2m0 14v2m9-9h-2M5 12H3m15.4-6.4l-1.4 1.4M7 17l-1.4 1.4m12.8 0L17 17M7 7L5.6 5.6M16 12a4 4 0 11-8 0 4 4 0 018 0z' },
  { value: 'dark', label: 'Dark', icon: 'M20.4 14.4A8 8 0 019.6 3.6a8 8 0 1010.8 10.8z' },
  { value: 'system', label: 'Auto', icon: 'M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z' },
];
const theme = ref('system');
const media = typeof window !== 'undefined' ? window.matchMedia('(prefers-color-scheme: dark)') : null;
function applyTheme() {
  const dark = theme.value === 'dark' || (theme.value === 'system' && media?.matches);
  document.documentElement.classList.toggle('dark', dark);
}
function setTheme(value) {
  theme.value = value;
  try { localStorage.setItem('tasfia_admin_theme', value); } catch (e) { /* ignore */ }
  applyTheme();
}

// ---------- Menus ----------
const bellOpen = ref(false);
const userOpen = ref(false);
const bellRef = ref(null);
const userRef = ref(null);
const waiting = computed(() => [
  counts.value.review && { label: 'Articles waiting for approval', href: '/admin/blogs?tab=review', count: counts.value.review, cls: 'a-badge-warning' },
  can.value['enquiries.view'] && counts.value.unread && { label: 'Unread enquiries', href: '/admin/messages', count: counts.value.unread, cls: 'a-badge-accent' },
  can.value['redirects.view'] && counts.value.open_404s && { label: 'Broken URLs (404)', href: '/admin/redirects?tab=404', count: counts.value.open_404s, cls: '' },
].filter(Boolean));
const notificationCount = computed(() => (notes.value.unread || 0) + waiting.value.reduce((s, w) => s + (w.href.includes('messages') ? 0 : w.count), 0));
function onDocClick(e) {
  if (bellRef.value && !bellRef.value.contains(e.target)) bellOpen.value = false;
  if (userRef.value && !userRef.value.contains(e.target)) userOpen.value = false;
}

async function quickClear() {
  userOpen.value = false;
  const ok = await confirmDialog({
    title: 'Clear the website cache?',
    message: 'Use this when a change does not show on the website. Settings, SEO scores and Google reviews are rebuilt on the next visit. Nothing is deleted.',
    confirmText: 'Clear cache',
    tone: 'primary',
  });
  if (ok) router.post('/admin/system/cache/clear', { scope: 'data' }, { preserveScroll: true });
}

// ---------- Toasts ----------
// Shared with every page through useToast(), so axios actions show the same toasts as saved forms.
const { toasts, push: pushToast, dismiss } = useToast();
watch(() => page.props.flash, (flash) => {
  pushToast('success', flash?.success);
  pushToast('error', flash?.error);
}, { immediate: true });

// Every problem gets a clear message instead of a blank or technical screen.
const statusMessage = (status) => ({
  401: 'You have been signed out. Reload the page and sign in again.',
  403: 'Your role does not allow this. Ask the Super Admin for access.',
  404: 'That item no longer exists. It may have been deleted by someone else.',
  413: 'The file is too large for the server. Use a smaller photo.',
  419: 'This page was open too long and expired. Reload the page and try again; your autosaved text is kept.',
  429: 'Too many tries in a short time. Wait a minute and try again.',
}[status] || (status >= 500 ? `Something went wrong on the server (error ${status}). Try again; if it keeps happening, turn on Debug mode under System → Site status and send the message to your developer.` : `The request failed (error ${status}).`));
const offInvalid = router.on('invalid', (event) => {
  const status = event.detail.response?.status;
  // With debug on (developers), keep Laravel's detailed error screen for server errors.
  if (status >= 500 && admin.value?.debug) return;
  event.preventDefault();
  pushToast('error', statusMessage(status));
});
const offException = router.on('exception', (event) => {
  event.preventDefault();
  pushToast('error', 'No connection to the server. Check your internet and try again.');
});
const offError = router.on('error', (event) => {
  const first = Object.values(event.detail.errors || {})[0];
  if (first) pushToast('error', `Please check the form: ${first}`);
});
onBeforeUnmount(() => { offInvalid(); offException(); offError(); });

onMounted(() => {
  try { theme.value = localStorage.getItem('tasfia_admin_theme') || 'system'; } catch (e) { theme.value = 'system'; }
  if (!['light', 'dark', 'system'].includes(theme.value)) theme.value = 'system';
  applyTheme();
  media?.addEventListener?.('change', applyTheme);
  document.addEventListener('click', onDocClick);
  document.addEventListener('keydown', onKey);
});
onBeforeUnmount(() => {
  media?.removeEventListener?.('change', applyTheme);
  document.removeEventListener('click', onDocClick);
  document.removeEventListener('keydown', onKey);
});

function logout() {
  router.post('/admin/logout');
}

const icons = {
  home: 'M3 10.5L12 3l9 7.5V20a1 1 0 01-1 1h-5v-6h-6v6H4a1 1 0 01-1-1v-9.5z',
  pulse: 'M3 12h4l3 8 4-16 3 8h4',
  chart: 'M4 20V10m6 10V4m6 16v-7m4 7H2',
  check: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
  doc: 'M9 12h6m-6 4h6M7 3h7l5 5v12a1 1 0 01-1 1H7a2 2 0 01-2-2V5a2 2 0 012-2z',
  wrench: 'M14.7 6.3a4 4 0 00-5.4 5.2L3 17.8V21h3.2l6.3-6.3a4 4 0 005.2-5.4l-2.5 2.5-2.5-.5-.5-2.5 2.5-2.5z',
  pin: 'M12 21s-7-6.2-7-11.5A7 7 0 0112 2.5a7 7 0 017 7C19 14.8 12 21 12 21zm0-9a2.5 2.5 0 100-5 2.5 2.5 0 000 5z',
  layout: 'M4 5h16v4H4zM4 13h7v6H4zM15 13h5v6h-5z',
  info: 'M12 16v-4m0-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
  briefcase: 'M9 7V5a2 2 0 012-2h2a2 2 0 012 2v2m-9 0h12a2 2 0 012 2v9a2 2 0 01-2 2H6a2 2 0 01-2-2V9a2 2 0 012-2z',
  star: 'M12 3l2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1-4.4-4.3 6.1-.9L12 3z',
  search: 'M21 21l-5.2-5.2M17 10.5a6.5 6.5 0 11-13 0 6.5 6.5 0 0113 0z',
  image: 'M4 16l4.6-4.6a2 2 0 012.8 0L16 16m-2-2l1.6-1.6a2 2 0 012.8 0L20 14M14 8h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z',
  photo: 'M3 7h18M3 7v11a2 2 0 002 2h14a2 2 0 002-2V7M3 7l2-3h14l2 3M8 13h8',
  mail: 'M3 8l7.9 5.3a2 2 0 002.2 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
  arrow: 'M13 7l5 5m0 0l-5 5m5-5H6',
  cog: 'M10.3 4.3c.4-1.8 3-1.8 3.4 0a1.7 1.7 0 002.6 1c1.5-.9 3.3.8 2.4 2.4a1.7 1.7 0 001 2.6c1.8.4 1.8 3 0 3.4a1.7 1.7 0 00-1 2.6c.9 1.5-.8 3.3-2.4 2.4a1.7 1.7 0 00-2.6 1c-.4 1.8-3 1.8-3.4 0a1.7 1.7 0 00-2.6-1c-1.5.9-3.3-.8-2.4-2.4a1.7 1.7 0 00-1-2.6c-1.8-.4-1.8-3 0-3.4a1.7 1.7 0 001-2.6c-.9-1.5.8-3.3 2.4-2.4a1.7 1.7 0 002.6-1zM15 12a3 3 0 11-6 0 3 3 0 016 0z',
  shield: 'M12 3l7 3v6c0 4.5-3 8.2-7 9-4-.8-7-4.5-7-9V6l7-3z',
  users: 'M17 20h5v-2a3 3 0 00-5.4-1.9M17 20H7m10 0v-2c0-.7-.1-1.3-.4-1.9M7 20H2v-2a3 3 0 015.4-1.9M7 20v-2c0-.7.1-1.3.4-1.9m0 0a5 5 0 019.3 0M15 7a3 3 0 11-6 0 3 3 0 016 0z',
  server: 'M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01',
};
</script>
