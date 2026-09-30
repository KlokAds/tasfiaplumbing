<template>
  <AdminLayout title="Search & visitors">
    <PageHeader title="Search & visitors" description="How people find the website on Google and what they do on it.">
        <button v-if="connected" type="button" class="admin-btn-secondary" :disabled="loading" @click="go({ refresh: 1 })">{{ loading ? 'Refreshing…' : 'Refresh' }}</button>
    </PageHeader>

    <!-- Not set up -->
    <section v-if="!connected || (!hasGsc && !hasGa4)" class="admin-card p-8 text-center max-w-2xl mx-auto">
      <div class="w-12 h-12 rounded-2xl a-tint-accent a-accent grid place-items-center mx-auto"><svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 20V10m6 10V4m6 16v-7m4 7H2" /></svg></div>
      <h3 class="mt-4 text-base font-bold">{{ connected ? 'Choose what to show' : 'Connect Google to see your reports here' }}</h3>
      <p class="mt-1.5 text-sm a-muted">Search clicks, the words people search, top pages, visitors, traffic sources and WhatsApp / call clicks — without opening Google's tools.</p>
      <Link v-if="can('analytics.connect')" href="/admin/insights/google" class="admin-btn-primary mt-5 inline-flex">{{ connected ? 'Choose properties' : 'Set up Google connection' }}</Link>
      <p v-else class="mt-4 text-xs a-subtle">Ask the site owner to connect Google.</p>
    </section>

    <div v-else class="space-y-8">
      <!-- Period -->
      <section class="admin-card p-3 sm:p-4 flex flex-col lg:flex-row lg:items-center gap-3 lg:gap-5">
        <div class="flex flex-wrap items-center gap-1.5" role="group" aria-label="Period">
          <button v-for="p in periodButtons" :key="p.key" type="button" @click="pick(p.key)"
            :class="['px-3.5 py-2 rounded-lg text-[13.5px] font-semibold transition', current === p.key ? 'bg-[var(--a-accent)] text-white shadow-sm' : 'a-muted hover:bg-[var(--a-panel-3)]']"
            :aria-pressed="current === p.key">{{ p.label }}</button>
        </div>
        <form v-if="showCustom" @submit.prevent="applyCustom" class="flex flex-wrap items-end gap-2">
          <label class="text-xs a-muted">From<input v-model="from" type="date" :max="to || today" required class="admin-input !py-1.5 mt-1 block" /></label>
          <label class="text-xs a-muted">To<input v-model="to" type="date" :min="from" :max="today" required class="admin-input !py-1.5 mt-1 block" /></label>
          <button type="submit" class="admin-btn-primary a-btn-sm">Apply</button>
        </form>
        <p class="lg:ml-auto text-[13px] a-muted">
          <span class="font-semibold a-text">{{ fmtRange(gscRange.start, gscRange.end) }}</span>
          <span class="a-subtle"> · compared with {{ fmtRange(gscRange.previous_start, gscRange.previous_end) }}</span>
        </p>
      </section>

      <!-- Search Console (loads after the page has opened) -->
      <section v-if="hasGsc" class="space-y-5">
        <SectionHead icon="search" title="Google Search" :sub="`${gscProperty?.replace('sc-domain:', '')} · data is about 2 days behind`" />
        <Deferred data="gsc">
          <template #fallback><ReportSkeleton label="Loading search data from Google…" :tables="2" /></template>
          <p v-if="gsc && !gsc.ok" class="a-alert a-alert-danger text-sm">{{ gsc.error }}</p>
          <div v-else-if="gsc" class="space-y-5">
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
              <Kpi icon="click" label="Clicks from Google" :value="gsc.data.now.clicks" :before="gsc.data.before.clicks" />
              <Kpi icon="eye" label="Shown in results" :value="gsc.data.now.impressions" :before="gsc.data.before.impressions" />
              <Kpi icon="percent" label="Click-through rate" :value="gsc.data.now.ctr" :before="gsc.data.before.ctr" suffix="%" />
              <Kpi icon="rank" label="Average position" :value="gsc.data.now.position" :before="gsc.data.before.position" lower-is-better help="1 = top of Google" />
            </div>
            <section class="admin-card p-5">
              <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
                <h3 class="a-card-title">{{ gscRange.weekly ? 'Clicks per week' : 'Clicks per day' }}</h3>
                <span class="flex items-center gap-4 text-xs a-muted">
                  <span class="flex items-center gap-1.5"><span class="w-4 h-0.5 rounded bg-[var(--a-accent)]"></span>Clicks</span>
                  <span class="flex items-center gap-1.5"><span class="w-4 border-t-2 border-dashed a-border-2"></span>Shown</span>
                </span>
              </div>
              <TrendChart :points="gsc.data.daily.map(d => ({ date: d.date, value: d.clicks }))" :second="gsc.data.daily.map(d => ({ date: d.date, value: d.impressions }))" unit="clicks" unit2="impressions" :label="gscRange.weekly ? 'Clicks from Google per week' : 'Clicks from Google per day'" />
            </section>
            <div class="grid grid-cols-1 xl:grid-cols-2 gap-5 items-start">
              <DataTable title="What people searched" :rows="gsc.data.queries" key-label="Search term" empty="No searches yet. New sites take a few weeks to appear." />
              <DataTable title="Pages people clicked" :rows="gsc.data.pages" key-label="Page" path-key="path" empty="No page clicks yet." />
            </div>
            <SimpleTable v-if="gsc.data.devices?.length" title="Search clicks by device" :rows="gsc.data.devices.map(d => ({ ...d, key: deviceName(d.key) }))" :cols="[['key', 'Device'], ['clicks', 'Clicks'], ['impressions', 'Shown']]" bar="clicks" />
          </div>
        </Deferred>
      </section>

      <!-- Analytics (loads after the page has opened) -->
      <section v-if="hasGa4" class="space-y-5">
        <SectionHead icon="users" title="Visitors" :sub="`Google Analytics · ${fmtRange(ga4Range.start, ga4Range.end)}`" />
        <Deferred data="ga4">
          <template #fallback><ReportSkeleton label="Loading visitor data from Google Analytics…" :tables="4" /></template>
          <p v-if="ga4 && !ga4.ok" class="a-alert a-alert-danger text-sm">{{ ga4.error }}</p>
          <div v-else-if="ga4" class="space-y-5">
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
              <Kpi icon="users" label="Visitors" :value="ga4.data.now.activeUsers" :before="ga4.data.before.activeUsers" />
              <Kpi icon="door" label="Visits" :value="ga4.data.now.sessions" :before="ga4.data.before.sessions" />
              <Kpi icon="eye" label="Page views" :value="ga4.data.now.screenPageViews" :before="ga4.data.before.screenPageViews" />
              <Kpi icon="spark" label="Engaged visits" :value="Math.round(ga4.data.now.engagementRate * 1000) / 10" :before="Math.round(ga4.data.before.engagementRate * 1000) / 10" suffix="%" help="Stayed 10 s+, 2+ pages or converted" />
            </div>

            <section class="admin-card p-5">
              <h3 class="a-card-title mb-4">{{ ga4Range.weekly ? 'Visitors per week' : 'Visitors per day' }}</h3>
              <TrendChart :points="ga4.data.daily.map(d => ({ date: d.date, value: d.users }))" unit="visitors" :label="ga4Range.weekly ? 'Visitors per week' : 'Visitors per day'" />
            </section>

            <section class="admin-card overflow-hidden">
              <header class="a-card-head"><div><h3 class="a-card-title">Leads</h3><p class="a-card-sub">Counted by the conversion events (Logo, footer & tracking → Send conversion events).</p></div></header>
              <div class="grid grid-cols-2 lg:grid-cols-4 divide-x a-divide">
                <div v-for="e in leadEvents" :key="e.key" class="p-5">
                  <p class="text-xs a-subtle">{{ e.label }}</p>
                  <p class="text-2xl font-extrabold mt-1 tabular-nums">{{ e.count.toLocaleString() }}</p>
                </div>
              </div>
            </section>

            <div class="grid grid-cols-1 xl:grid-cols-2 gap-5 items-start">
              <SimpleTable title="Most viewed pages" :rows="ga4.data.pages" :cols="[['key', 'Page'], ['screenPageViews', 'Views'], ['activeUsers', 'Visitors']]" link />
              <SimpleTable title="Where visitors come from" :rows="ga4.data.channels" :cols="[['key', 'Channel'], ['sessions', 'Visits'], ['activeUsers', 'Visitors']]" bar="sessions" />
              <SimpleTable title="Top sources" :rows="ga4.data.sources" :cols="[['key', 'Source'], ['sessions', 'Visits']]" bar="sessions" />
              <div class="space-y-5">
                <SimpleTable title="Devices" :rows="ga4.data.devices.map(d => ({ ...d, key: deviceName(d.key) }))" :cols="[['key', 'Device'], ['activeUsers', 'Visitors']]" bar="activeUsers" />
                <SimpleTable title="Cities" :rows="ga4.data.cities" :cols="[['key', 'City'], ['activeUsers', 'Visitors']]" bar="activeUsers" />
              </div>
            </div>
          </div>
        </Deferred>
      </section>
      <p v-if="gsc || ga4" class="text-xs a-subtle text-center">Updated {{ updated }}. Reports are kept for a few hours (Insights → Google → Automatic updates); press Refresh for the latest numbers.</p>
    </div>
  </AdminLayout>
</template>

<script setup>
import { computed, defineComponent, h, ref } from 'vue';
import { Deferred, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import TrendChart from '@/Components/Admin/TrendChart.vue';
import { usePermissions } from '@/Composables/usePermissions';

const props = defineProps({
  connected: Boolean, hasGsc: Boolean, hasGa4: Boolean, gsc: Object, ga4: Object,
  gscProperty: String, ga4Property: String, site: String,
  range: { type: Object, default: () => ({ key: '28d' }) },
  gscRange: { type: Object, default: () => ({}) },
  ga4Range: { type: Object, default: () => ({}) },
  presets: { type: Array, default: () => [] },
});
const { can } = usePermissions();

// ---- Period
const current = ref(props.range.key || '28d');
const periodButtons = computed(() => [...props.presets, { key: 'custom', label: 'Custom' }]);
const showCustom = computed(() => current.value === 'custom');
const today = new Date().toISOString().slice(0, 10);
const from = ref(props.range.from || props.gscRange.start || '');
const to = ref(props.range.to || props.gscRange.end || '');
const loading = ref(false);

function go(extra = {}) {
  const params = current.value === 'custom' ? { range: 'custom', from: from.value, to: to.value } : (current.value === '28d' ? {} : { range: current.value });
  loading.value = true;
  router.get('/admin/insights', { ...params, ...extra }, { preserveScroll: true, onFinish: () => { loading.value = false; } });
}
function pick(key) {
  current.value = key;
  if (key !== 'custom') go();
}
function applyCustom() { go(); }

const fmt = (d, withYear = true) => (d ? new Date(d + 'T00:00:00').toLocaleDateString('en-SG', withYear ? { day: 'numeric', month: 'short', year: 'numeric' } : { day: 'numeric', month: 'short' }) : '');
const fmtRange = (a, b) => (a && b ? `${fmt(a, a.slice(0, 4) !== b.slice(0, 4))} – ${fmt(b)}` : '');
const deviceName = (k) => ({ MOBILE: 'Mobile', DESKTOP: 'Desktop', TABLET: 'Tablet', mobile: 'Mobile', desktop: 'Desktop', tablet: 'Tablet' }[k] || k);

const updated = computed(() => {
  const t = props.gsc?.data?.fetched_at || props.ga4?.data?.fetched_at;
  return t ? new Date(t).toLocaleString('en-SG', { dateStyle: 'medium', timeStyle: 'short' }) : 'just now';
});

const leadEvents = computed(() => {
  const list = props.ga4?.data?.events || [];
  const get = (k) => list.find((e) => e.key === k)?.eventCount || 0;
  return [
    { key: 'generate_lead', label: 'Quote forms sent', count: get('generate_lead') },
    { key: 'click_whatsapp', label: 'WhatsApp clicks', count: get('click_whatsapp') },
    { key: 'click_call', label: 'Phone calls', count: get('click_call') },
    { key: 'click_email', label: 'Email clicks', count: get('click_email') },
  ];
});

const nf = (v) => (Number.isInteger(v) ? v.toLocaleString() : Number(v).toLocaleString(undefined, { maximumFractionDigits: 1 }));

const ICONS = {
  search: 'M21 21l-5.2-5.2M17 10.5a6.5 6.5 0 11-13 0 6.5 6.5 0 0113 0z',
  users: 'M17 20h5v-2a4 4 0 00-5-3.9M9 20H2v-2a4 4 0 014-4h3a4 4 0 014 4v2m-4-9a4 4 0 100-8 4 4 0 000 8zm8 0a3 3 0 100-6',
  click: 'M9 9l10 4-4 2-2 4-4-10zM5 3v3M3 5h3M13 5l-2 2M5 13l2-2',
  eye: 'M2.5 12s3.5-7 9.5-7 9.5 7 9.5 7-3.5 7-9.5 7-9.5-7-9.5-7zm9.5 3a3 3 0 100-6 3 3 0 000 6z',
  percent: 'M19 5L5 19M7.5 9a1.5 1.5 0 100-3 1.5 1.5 0 000 3zm9 9a1.5 1.5 0 100-3 1.5 1.5 0 000 3z',
  rank: 'M8 21h8m-4-4v4M6 3h12v5a6 6 0 01-12 0V3zM6 5H3v2a3 3 0 003 3m12-5h3v2a3 3 0 01-3 3',
  door: 'M5 21V4a1 1 0 011-1h9l4 3v15M3 21h18M14 12h.01',
  spark: 'M13 2L4 14h7l-1 8 9-12h-7l1-8z',
};
const icon = (name, cls = 'w-4 h-4') => h('svg', { class: cls, fill: 'none', stroke: 'currentColor', 'stroke-width': '1.9', viewBox: '0 0 24 24', 'aria-hidden': 'true' }, h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', d: ICONS[name] }));

const SectionHead = defineComponent({
  props: { icon: String, title: String, sub: String },
  setup(p) {
    return () => h('div', { class: 'flex items-center gap-3' }, [
      h('span', { class: 'w-9 h-9 rounded-xl a-tint-accent a-accent grid place-items-center shrink-0' }, icon(p.icon, 'w-[18px] h-[18px]')),
      h('div', { class: 'min-w-0' }, [
        h('h2', { class: 'text-[17px] font-bold leading-tight' }, p.title),
        h('p', { class: 'text-xs a-subtle truncate' }, p.sub),
      ]),
    ]);
  },
});

// Grey placeholders in the shape of the report (numbers, chart, tables) while Google answers.
const ReportSkeleton = defineComponent({
  props: { label: String, tables: { type: Number, default: 2 } },
  setup(p) {
    const bar = (cls) => h('div', { class: ['rounded-md a-panel-3 animate-pulse', cls] });
    return () => h('div', { class: 'space-y-5', role: 'status', 'aria-live': 'polite' }, [
      h('p', { class: 'flex items-center gap-2 text-sm a-muted' }, [
        h('span', { class: 'w-4 h-4 rounded-full border-2 border-[var(--a-accent)] border-t-transparent animate-spin', 'aria-hidden': 'true' }),
        p.label,
      ]),
      h('div', { class: 'grid grid-cols-2 lg:grid-cols-4 gap-4' }, [0, 1, 2, 3].map(() => h('div', { class: 'admin-card p-4 space-y-2.5' }, [bar('h-3 w-24'), bar('h-7 w-20'), bar('h-3 w-16')]))),
      h('div', { class: 'admin-card p-5 space-y-3' }, [bar('h-4 w-32'), bar('h-40 w-full')]),
      h('div', { class: 'grid grid-cols-1 xl:grid-cols-2 gap-5' }, Array.from({ length: p.tables }, () => h('div', { class: 'admin-card p-5 space-y-3' }, [bar('h-4 w-40'), ...[0, 1, 2, 3, 4, 5].map(() => bar('h-3.5 w-full'))]))),
    ]);
  },
});

const Kpi = defineComponent({
  props: { icon: String, label: String, value: Number, before: Number, suffix: { type: String, default: '' }, lowerIsBetter: Boolean, help: String },
  setup(p) {
    return () => {
      const diff = p.before ? ((p.value - p.before) / p.before) * 100 : null;
      const good = diff === null ? null : (p.lowerIsBetter ? diff < 0 : diff > 0);
      const flat = diff !== null && Math.abs(diff) < 0.5;
      const pill = diff === null || !isFinite(diff)
        ? h('span', { class: 'a-subtle' }, 'No earlier data')
        : h('span', { class: ['inline-flex items-center gap-0.5 rounded-full px-2 py-0.5 font-semibold', flat ? 'a-panel-3 a-muted' : good ? 'bg-emerald-500/12 a-text-success' : 'bg-red-500/10 a-text-danger'] }, `${diff > 0 ? '▲' : diff < 0 ? '▼' : ''} ${Math.abs(diff).toFixed(Math.abs(diff) < 10 ? 1 : 0)}%`);
      return h('div', { class: 'admin-card p-4 sm:p-5' }, [
        h('div', { class: 'flex items-center justify-between gap-2' }, [
          h('p', { class: 'text-xs font-semibold a-muted' }, p.label),
          p.icon ? h('span', { class: 'w-7 h-7 rounded-lg a-panel-3 a-subtle grid place-items-center' }, icon(p.icon, 'w-3.5 h-3.5')) : null,
        ]),
        h('p', { class: 'mt-2 text-[1.7rem] leading-none font-extrabold tabular-nums tracking-tight' }, nf(p.value) + p.suffix),
        h('p', { class: 'mt-2.5 text-xs flex flex-wrap items-center gap-1.5' }, [
          pill,
          h('span', { class: 'a-subtle' }, p.help || 'vs previous period'),
        ]),
      ]);
    };
  },
});

// Long tables show 10 rows at a time.
const PER_PAGE = 10;
function pager(rows, page) {
  const total = rows?.length || 0;
  const pages = Math.max(1, Math.ceil(total / PER_PAGE));
  const current = Math.min(page, pages);
  return { total, pages, current, slice: (rows || []).slice((current - 1) * PER_PAGE, current * PER_PAGE), start: (current - 1) * PER_PAGE, from: total ? (current - 1) * PER_PAGE + 1 : 0, to: Math.min(total, current * PER_PAGE) };
}
function pagerFooter(pg, set) {
  if (pg.pages <= 1) return null;
  const btn = (label, target, disabled, aria) => h('button', { type: 'button', class: 'admin-btn-secondary a-btn-sm !px-2.5', disabled, onClick: () => set(target), 'aria-label': aria }, label);
  return h('footer', { class: 'flex items-center justify-between gap-3 px-4 py-2.5 border-t a-border text-xs a-muted' }, [
    h('span', { class: 'tabular-nums' }, `${pg.from}–${pg.to} of ${pg.total}`),
    h('span', { class: 'flex items-center gap-1.5' }, [
      btn('‹', pg.current - 1, pg.current <= 1, 'Previous page'),
      h('span', { class: 'tabular-nums px-1' }, `${pg.current} / ${pg.pages}`),
      btn('›', pg.current + 1, pg.current >= pg.pages, 'Next page'),
    ]),
  ]);
}
const rankCell = (n) => h('td', { class: 'w-8 text-right tabular-nums a-subtle text-xs' }, n);
const positionBadge = (pos) => h('span', { class: ['inline-block min-w-[2.6rem] rounded-md px-1.5 py-0.5 text-center text-xs font-semibold tabular-nums', pos <= 3 ? 'bg-emerald-500/12 a-text-success' : pos <= 10 ? 'a-tint-accent a-accent' : pos <= 20 ? 'bg-amber-500/15 a-text-warning' : 'a-panel-3 a-muted'] }, pos);

const DataTable = defineComponent({
  props: { title: String, rows: Array, keyLabel: String, pathKey: String, empty: String },
  setup(p) {
    const page = ref(1);
    return () => {
      const pg = pager(p.rows, page.value);
      const maxCtr = Math.max(1, ...(p.rows || []).map((r) => r.ctr));
      return h('section', { class: 'admin-card overflow-hidden' }, [
        h('header', { class: 'a-card-head' }, [h('h3', { class: 'a-card-title' }, p.title), pg.total ? h('span', { class: 'text-xs a-subtle' }, `${pg.total} total`) : null]),
        pg.total ? h('div', { class: 'overflow-x-auto' }, h('table', { class: 'a-table' }, [
          h('thead', h('tr', ['#', p.keyLabel, 'Clicks', 'Shown', 'CTR', 'Position'].map((c, i) => h('th', { class: i > 1 || i === 0 ? 'text-right' : '' }, c)))),
          h('tbody', pg.slice.map((r, i) => h('tr', [
            rankCell(pg.start + i + 1),
            h('td', { class: 'max-w-[16rem] truncate' }, p.pathKey ? h('a', { href: r.key, target: '_blank', rel: 'noopener', class: 'a-accent hover:underline', title: r.key }, r[p.pathKey]) : h('span', { title: r.key }, r.key)),
            h('td', { class: 'text-right tabular-nums font-semibold' }, nf(r.clicks)),
            h('td', { class: 'text-right tabular-nums a-muted' }, nf(r.impressions)),
            h('td', { class: 'text-right tabular-nums' }, [
              h('span', r.ctr + '%'),
              h('div', { class: 'mt-1 ml-auto h-1 w-12 rounded-full a-panel-3 overflow-hidden' }, h('div', { class: 'h-full rounded-full bg-[var(--a-accent)]', style: { width: `${(r.ctr / maxCtr) * 100}%` } })),
            ]),
            h('td', { class: 'text-right' }, positionBadge(r.position)),
          ]))),
        ])) : h('p', { class: 'p-5 text-sm a-muted' }, p.empty),
        pagerFooter(pg, (n) => { page.value = n; }),
      ]);
    };
  },
});

const SimpleTable = defineComponent({
  props: { title: String, rows: Array, cols: Array, bar: String, link: Boolean },
  setup(p) {
    const page = ref(1);
    return () => {
      const pg = pager(p.rows, page.value);
      const max = p.bar ? Math.max(1, ...(p.rows || []).map((r) => r[p.bar])) : 1;
      return h('section', { class: 'admin-card overflow-hidden' }, [
        h('header', { class: 'a-card-head' }, [h('h3', { class: 'a-card-title' }, p.title), pg.total > PER_PAGE ? h('span', { class: 'text-xs a-subtle' }, `${pg.total} total`) : null]),
        pg.total ? h('table', { class: 'a-table' }, [
          h('thead', h('tr', ['#', ...p.cols.map(([, label]) => label)].map((label, i) => h('th', { class: i !== 1 ? 'text-right' : '' }, label)))),
          h('tbody', pg.slice.map((r, n) => h('tr', [rankCell(pg.start + n + 1), ...p.cols.map(([key], i) => h('td', { class: i ? 'text-right tabular-nums' : 'max-w-[16rem]' }, i ? nf(r[key]) : [
            h('div', { class: 'truncate' }, p.link ? h('a', { href: r[key], target: '_blank', rel: 'noopener', class: 'a-accent hover:underline', title: r[key] }, r[key]) : (r[key] === '(not set)' ? 'Unknown' : r[key])),
            p.bar ? h('div', { class: 'mt-1 h-1 rounded-full a-panel-3 overflow-hidden' }, h('div', { class: 'h-full rounded-full bg-[var(--a-accent)]', style: { width: `${(r[p.bar] / max) * 100}%` } })) : null,
          ]))]))),
        ]) : h('p', { class: 'p-5 text-sm a-muted' }, 'No data yet.'),
        pagerFooter(pg, (n) => { page.value = n; }),
      ]);
    };
  },
});
</script>
