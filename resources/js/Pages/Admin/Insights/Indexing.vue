<template>
  <AdminLayout title="Index status">
    <PageHeader title="Index status" description="Which pages of the sitemap Google has in its index. Only indexed pages can appear in search results. Checked automatically every night.">
      <button v-if="ready" type="button" class="admin-btn-secondary" :disabled="busy" @click="batch">{{ busy ? 'Checking…' : 'Check 40 pages now' }}</button>
    </PageHeader>

    <p v-if="!ready" class="a-alert a-alert-warning text-sm mb-5">
      Connect Google and choose your Search Console property to see this.
      <Link v-if="can('analytics.connect')" href="/admin/insights/google" class="font-semibold underline">Open Google connections</Link>
    </p>

    <StickyBar>
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-5">
      <button v-for="c in cards" :key="c.key" type="button" @click="filter = filter === c.key ? '' : c.key"
        :class="['admin-card p-4 text-left transition', filter === c.key && 'ring-2 ring-[var(--a-accent)]']">
        <p class="text-xs a-subtle flex items-center gap-1.5"><span :class="['w-2 h-2 rounded-full', c.dot]"></span>{{ c.label }}</p>
        <p class="mt-1 text-2xl font-extrabold tabular-nums">{{ summary[c.key] || 0 }}</p>
      </button>
    </div>
    </StickyBar>

    <section class="admin-card overflow-hidden">
      <div class="p-3 border-b a-border flex flex-wrap gap-2 items-center">
        <input v-model="q" type="search" class="admin-input !w-64 max-w-full" placeholder="Search pages…" />
        <SelectBox v-model="type" class="admin-input !w-44">
          <option value="">All types</option>
          <option v-for="t in types" :key="t" :value="t">{{ t }}</option>
        </SelectBox>
        <span class="ml-auto text-xs a-subtle">{{ shown.length }} of {{ rows.length }} pages</span>
      </div>
      <div class="overflow-x-auto">
        <table class="a-table">
          <thead><tr><th>Page</th><th>Status</th><th class="hidden md:table-cell">Google says</th><th class="hidden lg:table-cell">Last crawled</th><th class="text-right">Action</th></tr></thead>
          <tbody>
            <tr v-for="r in pager.rows.value" :key="r.url">
              <td class="max-w-[22rem]">
                <p class="truncate font-medium">{{ r.title || r.path }}</p>
                <a :href="r.url" target="_blank" rel="noopener" class="text-xs a-subtle truncate block hover:underline">{{ r.path }}</a>
              </td>
              <td><span :class="['a-badge whitespace-nowrap', badge[r.state].cls]">{{ badge[r.state].label }}</span></td>
              <td class="hidden md:table-cell text-xs a-muted max-w-[18rem]">
                <p>{{ r.error || r.coverage || '—' }}</p>
                <p v-if="r.google_canonical" class="mt-0.5 a-text-warning">Google prefers: {{ r.google_canonical }}</p>
                <p v-if="r.state === 'not_indexed'" class="mt-0.5 a-subtle">{{ advice(r.coverage) }}</p>
              </td>
              <td class="hidden lg:table-cell text-xs a-muted whitespace-nowrap">{{ r.last_crawl ? date(r.last_crawl) : '—' }}</td>
              <td class="text-right whitespace-nowrap">
                <button v-if="ready" type="button" class="admin-btn-secondary !py-1 !px-2.5 !text-xs" :disabled="checking === r.url" @click="inspect(r.url)">{{ checking === r.url ? '…' : 'Check' }}</button>
              </td>
            </tr>
            <tr v-if="!shown.length"><td colspan="5" class="text-center a-muted py-10">No pages match.</td></tr>
          </tbody>
        </table>
      </div>
      <ClientPagination :pager="pager" />
    </section>
  </AdminLayout>
</template>

<script setup>
import StickyBar from '@/Components/Admin/StickyBar.vue';
import SelectBox from '@/Components/SelectBox.vue';
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import ClientPagination from '@/Components/Admin/ClientPagination.vue';
import { usePaged } from '@/Composables/usePaged';
import { usePermissions } from '@/Composables/usePermissions';

const props = defineProps({ rows: Array, summary: { type: [Object, Array], default: () => ({}) }, ready: Boolean });
const { can } = usePermissions();

const badge = {
  indexed: { label: 'Indexed', cls: 'a-badge-success' },
  not_indexed: { label: 'Not indexed', cls: 'a-badge-warning' },
  error: { label: 'Check failed', cls: 'a-badge-danger' },
  unchecked: { label: 'Not checked yet', cls: '' },
};
const cards = [
  { key: 'indexed', label: 'Indexed', dot: 'bg-emerald-500' },
  { key: 'not_indexed', label: 'Not indexed', dot: 'bg-amber-500' },
  { key: 'error', label: 'Check failed', dot: 'bg-red-500' },
  { key: 'unchecked', label: 'Waiting for check', dot: 'bg-slate-400' },
];

const filter = ref(new URLSearchParams(location.search).get('state') || '');
const q = ref('');
const type = ref('');
const types = computed(() => [...new Set(props.rows.map((r) => r.type))]);
const shown = computed(() => props.rows.filter((r) =>
  (!filter.value || r.state === filter.value)
  && (!type.value || r.type === type.value)
  && (!q.value || `${r.title} ${r.path}`.toLowerCase().includes(q.value.toLowerCase()))));

const pager = usePaged(shown, 'indexing', 50);

// Plain-language advice for Google's most common "not indexed" reasons.
function advice(coverage = '') {
  const c = (coverage || '').toLowerCase();
  if (c.includes('discovered')) return 'Google knows the page but has not visited it yet. Link to it from other pages; it usually gets crawled within weeks.';
  if (c.includes('crawled')) return 'Google visited but chose not to index it. Make the content longer, more useful and different from similar pages.';
  if (c.includes('unknown')) return 'Google has never seen this URL. Make sure it is in the sitemap and linked from the menu or other pages.';
  if (c.includes('duplicate') || c.includes('canonical')) return 'Google thinks another page is the same. Make this page clearly different or redirect it.';
  if (c.includes('noindex')) return 'The page tells Google not to index it. Turn off "Hide from Google" in its SEO settings.';
  if (c.includes('404') || c.includes('not found')) return 'Google got "page not found". Add a redirect under SEO → Redirects.';
  return 'Open the page in Search Console for details, then "Request indexing".';
}

const date = (iso) => new Date(iso).toLocaleDateString('en-SG', { day: 'numeric', month: 'short', year: 'numeric' });
const checking = ref(null);
function inspect(url) {
  checking.value = url;
  router.post('/admin/insights/indexing/inspect', { url }, { preserveScroll: true, onFinish: () => { checking.value = null; } });
}
const busy = ref(false);
function batch() {
  busy.value = true;
  router.post('/admin/insights/indexing/batch', {}, { preserveScroll: true, onFinish: () => { busy.value = false; } });
}
</script>
