<template>
  <AdminLayout title="SEO Health">
    <PageHeader title="SEO Health" description="Every page checked against the publish checklist: titles, descriptions, content length, service links, FAQs, prices and duplicates.">
      <template #meta><p class="mt-2 text-xs a-subtle">Last scan {{ ago(generatedAt) }}</p></template>
      <button @click="go({}, true)" class="admin-btn-secondary">Re-scan now</button>
    </PageHeader>

    <StickyBar>
    <!-- Page types -->
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-6">
      <button @click="go({ type: '', code: '' })" :class="['admin-card p-4 text-left transition', !filters.type && 'ring-2 ring-[var(--a-accent)]']">
        <p class="text-xs font-semibold a-muted">All pages</p>
        <p class="mt-1 text-2xl font-bold">{{ totalPages }}</p>
        <p class="text-xs a-subtle">{{ totalErrors }} with errors</p>
      </button>
      <button v-for="(t, type) in byType" :key="type" @click="go({ type, code: '' })" :class="['admin-card p-4 text-left transition', filters.type === type && 'ring-2 ring-[var(--a-accent)]']">
        <div class="flex items-center justify-between">
          <p class="text-xs font-semibold a-muted">{{ labels[type] }}</p>
          <span :class="['a-badge', badge(t.avg_score)]">{{ t.avg_score }}</span>
        </div>
        <p class="mt-1 text-2xl font-bold">{{ t.total }}</p>
        <p class="text-xs a-subtle">{{ t.with_errors }} with errors · {{ t.clean }} clean</p>
      </button>
    </div>
    </StickyBar>

    <div class="grid grid-cols-1 xl:grid-cols-[18rem_1fr] gap-6">
      <!-- Issue filter -->
      <aside class="admin-card p-2 h-fit xl:sticky xl:top-20">
        <p class="px-3 pt-2 pb-1 text-[11px] font-bold uppercase tracking-wider a-subtle">Filter by issue</p>
        <button @click="go({ code: '' })" :class="['issue-row', !filters.code && 'issue-on']">
          <span>All issues</span>
        </button>
        <button v-for="c in visibleCodes" :key="c.type + c.code" @click="go({ type: c.type, code: c.code })"
          :class="['issue-row', filters.code === c.code && filters.type === c.type && 'issue-on']">
          <span class="flex items-start gap-2 min-w-0 text-left">
            <span :class="['mt-1.5 w-1.5 h-1.5 rounded-full shrink-0', c.level === 'error' ? 'bg-red-500' : 'bg-amber-500']"></span>
            <span class="min-w-0"><span class="block truncate">{{ short(c.message) }}</span><span class="block text-[11px] a-subtle">{{ labels[c.type] }}</span></span>
          </span>
          <span class="text-xs font-bold tabular-nums">{{ c.count }}</span>
        </button>
      </aside>

      <!-- Pages -->
      <section class="admin-card overflow-hidden">
        <header class="flex flex-wrap items-center justify-between gap-3 px-5 py-3 border-b a-border">
          <p class="text-sm a-muted"><span class="font-semibold" style="color: var(--a-text)">{{ items.total }}</span> pages · lowest score first</p>
          <label class="flex items-center gap-2 text-sm a-muted">
            <input type="checkbox" :checked="filters.level === 'error'" @change="e => go({ level: e.target.checked ? 'error' : '' })" class="rounded" />
            Errors only
          </label>
        </header>
        <div class="overflow-x-auto">
          <table class="a-table">
            <thead><tr><th>Page</th><th class="w-24">Score</th><th>Issues</th><th class="w-20"></th></tr></thead>
            <tbody>
              <tr v-for="item in items.data" :key="item.type + item.id">
                <td class="max-w-xs">
                  <p class="font-semibold truncate">{{ item.name }}</p>
                  <p class="text-xs a-subtle">{{ singular[item.type] }}</p>
                </td>
                <td><span :class="['a-badge', item.errors ? 'a-badge-danger' : badge(item.score)]">{{ item.score }}</span></td>
                <td>
                  <ul class="space-y-0.5">
                    <li v-for="(issue, i) in item.issues.slice(0, 2)" :key="i" class="flex items-start gap-2 text-xs">
                      <span :class="['mt-1.5 w-1.5 h-1.5 rounded-full shrink-0', issue.level === 'error' ? 'bg-red-500' : 'bg-amber-500']"></span>
                      <span class="a-muted">{{ issue.message }}</span>
                    </li>
                  </ul>
                  <p v-if="item.issues.length > 2" class="text-[11px] a-subtle mt-0.5 pl-3.5">+{{ item.issues.length - 2 }} more</p>
                </td>
                <td class="text-right"><Link :href="item.edit_url" class="admin-btn-secondary a-btn-sm">Fix</Link></td>
              </tr>
            </tbody>
          </table>
          <p v-if="!items.data.length" class="px-5 py-12 text-center text-sm a-muted">No pages match this filter.</p>
        </div>
        <div class="px-5 pb-4"><Pagination :meta="items" /></div>
      </section>
    </div>
  </AdminLayout>
</template>

<script setup>
import StickyBar from '@/Components/Admin/StickyBar.vue';
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import Pagination from '@/Components/Admin/Pagination.vue';

const props = defineProps({
  byType: Object,
  byCode: Array,
  items: Object,
  generatedAt: String,
  filters: Object,
});

const labels = { service: 'Services', category: 'Categories', location: 'Locations', article: 'Articles' };
const singular = { service: 'Service', category: 'Category', location: 'Location', article: 'Article' };

const totalPages = computed(() => Object.values(props.byType).reduce((a, t) => a + t.total, 0));
const totalErrors = computed(() => Object.values(props.byType).reduce((a, t) => a + t.with_errors, 0));
const visibleCodes = computed(() => props.byCode.filter(c => !props.filters.type || c.type === props.filters.type));

const badge = s => (s >= 80 ? 'a-badge-success' : s >= 50 ? 'a-badge-warning' : 'a-badge-danger');
const short = m => m.replace(/\s*\(.*$/, '').replace(/:.*$/, '').replace(/\.$/, '');

function go(patch, refresh = false) {
  const params = { ...props.filters, ...patch };
  Object.keys(params).forEach(k => { if (!params[k]) delete params[k]; });
  if (refresh) params.refresh = 1;
  router.get('/admin/seo/health', params, { preserveScroll: true, preserveState: true });
}

function ago(iso) {
  const m = Math.round((Date.now() - new Date(iso).getTime()) / 60000);
  return m < 1 ? 'just now' : m < 60 ? `${m} min ago` : new Date(iso).toLocaleString('en-SG');
}
</script>

<style scoped>
.issue-row { width: 100%; display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; padding: 0.5rem 0.75rem; border-radius: 0.5rem; font-size: 0.82rem; color: var(--a-text-2); }
.issue-row:hover { background: var(--a-panel-2); color: var(--a-text); }
.issue-on { background: var(--a-accent-soft); color: var(--a-accent-text); font-weight: 600; }
</style>
