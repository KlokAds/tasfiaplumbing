<template>
  <AdminLayout title="Search & visitors">
    <PageHeader title="Search & visitors" description="How people find the website on Google and what they do on it. Last 28 days compared with the 28 days before.">
        <button v-if="connected" type="button" class="admin-btn-secondary" :disabled="loading" @click="refresh">{{ loading ? 'Refreshing…' : 'Refresh' }}</button>
    </PageHeader>

    <!-- Not set up -->
    <section v-if="!connected || (!gsc && !ga4)" class="admin-card p-8 text-center max-w-2xl mx-auto">
      <div class="w-12 h-12 rounded-2xl a-tint-accent a-accent grid place-items-center mx-auto"><svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 20V10m6 10V4m6 16v-7m4 7H2" /></svg></div>
      <h3 class="mt-4 text-base font-bold">{{ connected ? 'Choose what to show' : 'Connect Google to see your reports here' }}</h3>
      <p class="mt-1.5 text-sm a-muted">Search clicks, the words people search, top pages, visitors, traffic sources and WhatsApp / call clicks — without opening Google's tools.</p>
      <Link v-if="can('analytics.connect')" href="/admin/insights/google" class="admin-btn-primary mt-5 inline-flex">{{ connected ? 'Choose properties' : 'Set up Google connection' }}</Link>
      <p v-else class="mt-4 text-xs a-subtle">Ask the site owner to connect Google.</p>
    </section>

    <div v-else class="space-y-6">
      <!-- Search Console -->
      <template v-if="gsc">
        <h2 class="text-sm font-bold uppercase tracking-wider a-subtle flex items-center gap-2">Google Search <span class="normal-case tracking-normal font-normal">· {{ gscProperty?.replace('sc-domain:', '') }}</span></h2>
        <p v-if="!gsc.ok" class="a-alert a-alert-danger text-sm">{{ gsc.error }}</p>
        <template v-else>
          <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <Kpi label="Clicks from Google" :value="gsc.data.now.clicks" :before="gsc.data.before.clicks" />
            <Kpi label="Times shown in results" :value="gsc.data.now.impressions" :before="gsc.data.before.impressions" />
            <Kpi label="Click-through rate" :value="gsc.data.now.ctr" :before="gsc.data.before.ctr" suffix="%" />
            <Kpi label="Average position" :value="gsc.data.now.position" :before="gsc.data.before.position" lower-is-better help="1 = top of Google" />
          </div>
          <section class="admin-card p-5">
            <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
              <h3 class="a-card-title">Daily clicks</h3>
              <span class="text-xs a-subtle">— clicks · - - impressions · data is 2–3 days behind</span>
            </div>
            <TrendChart :points="gsc.data.daily.map(d => ({ date: d.date, value: d.clicks }))" :second="gsc.data.daily.map(d => ({ date: d.date, value: d.impressions }))" unit="clicks" unit2="impressions" label="Daily clicks from Google" />
          </section>
          <div class="grid grid-cols-1 xl:grid-cols-2 gap-5">
            <DataTable title="What people searched" :rows="gsc.data.queries" key-label="Search term" empty="No searches yet. New sites take a few weeks to appear." />
            <DataTable title="Pages people clicked" :rows="gsc.data.pages" key-label="Page" path-key="path" :site="site" empty="No page clicks yet." />
          </div>
        </template>
      </template>

      <!-- Analytics -->
      <template v-if="ga4">
        <h2 class="text-sm font-bold uppercase tracking-wider a-subtle pt-2">Visitors (Analytics)</h2>
        <p v-if="!ga4.ok" class="a-alert a-alert-danger text-sm">{{ ga4.error }}</p>
        <template v-else>
          <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <Kpi label="Visitors" :value="ga4.data.now.activeUsers" :before="ga4.data.before.activeUsers" />
            <Kpi label="Visits" :value="ga4.data.now.sessions" :before="ga4.data.before.sessions" />
            <Kpi label="Page views" :value="ga4.data.now.screenPageViews" :before="ga4.data.before.screenPageViews" />
            <Kpi label="Engaged visits" :value="Math.round(ga4.data.now.engagementRate * 1000) / 10" :before="Math.round(ga4.data.before.engagementRate * 1000) / 10" suffix="%" help="Stayed 10 s+, opened 2+ pages or converted" />
          </div>

          <section class="admin-card p-5">
            <h3 class="a-card-title mb-3">Visitors per day</h3>
            <TrendChart :points="ga4.data.daily.map(d => ({ date: d.date, value: d.users }))" unit="visitors" label="Visitors per day" />
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

          <div class="grid grid-cols-1 xl:grid-cols-2 gap-5">
            <SimpleTable title="Most viewed pages" :rows="ga4.data.pages" :cols="[['key', 'Page'], ['screenPageViews', 'Views'], ['activeUsers', 'Visitors']]" link />
            <SimpleTable title="Where visitors come from" :rows="ga4.data.channels" :cols="[['key', 'Channel'], ['sessions', 'Visits'], ['activeUsers', 'Visitors']]" bar="sessions" />
            <SimpleTable title="Top sources" :rows="ga4.data.sources" :cols="[['key', 'Source'], ['sessions', 'Visits']]" bar="sessions" />
            <div class="space-y-5">
              <SimpleTable title="Devices" :rows="ga4.data.devices" :cols="[['key', 'Device'], ['activeUsers', 'Visitors']]" bar="activeUsers" />
              <SimpleTable title="Cities" :rows="ga4.data.cities" :cols="[['key', 'City'], ['activeUsers', 'Visitors']]" bar="activeUsers" />
            </div>
          </div>
        </template>
      </template>
      <p class="text-xs a-subtle text-center">Updated {{ updated }}. Reports are kept for a few hours; press Refresh for the latest numbers.</p>
    </div>
  </AdminLayout>
</template>

<script setup>
import { computed, defineComponent, h, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import TrendChart from '@/Components/Admin/TrendChart.vue';
import { usePermissions } from '@/Composables/usePermissions';

const props = defineProps({ connected: Boolean, gsc: Object, ga4: Object, gscProperty: String, ga4Property: String, site: String });
const { can } = usePermissions();
const loading = ref(false);
const refresh = () => { loading.value = true; router.get('/admin/insights', { refresh: 1 }, { preserveScroll: true, onFinish: () => { loading.value = false; } }); };

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

const Kpi = defineComponent({
  props: { label: String, value: Number, before: Number, suffix: { type: String, default: '' }, lowerIsBetter: Boolean, help: String },
  setup(p) {
    return () => {
      const diff = p.before ? ((p.value - p.before) / p.before) * 100 : null;
      const good = diff === null ? null : (p.lowerIsBetter ? diff < 0 : diff > 0);
      return h('div', { class: 'admin-card p-4' }, [
        h('p', { class: 'text-xs a-subtle' }, p.label),
        h('p', { class: 'mt-1 text-2xl font-extrabold tabular-nums' }, nf(p.value) + p.suffix),
        h('p', { class: 'mt-1 text-xs flex items-center gap-1.5' }, [
          diff === null || !isFinite(diff) ? h('span', { class: 'a-subtle' }, 'No earlier data')
            : h('span', { class: ['font-semibold', Math.abs(diff) < 0.5 ? 'a-muted' : good ? 'a-text-success' : 'a-text-danger'] }, `${diff > 0 ? '▲' : diff < 0 ? '▼' : ''} ${Math.abs(diff).toFixed(0)}%`),
          p.help ? h('span', { class: 'a-subtle' }, '· ' + p.help) : null,
        ]),
      ]);
    };
  },
});

const DataTable = defineComponent({
  props: { title: String, rows: Array, keyLabel: String, pathKey: String, site: String, empty: String },
  setup(p) {
    return () => h('section', { class: 'admin-card overflow-hidden' }, [
      h('header', { class: 'a-card-head' }, h('h3', { class: 'a-card-title' }, p.title)),
      p.rows?.length ? h('div', { class: 'overflow-x-auto' }, h('table', { class: 'a-table' }, [
        h('thead', h('tr', [p.keyLabel, 'Clicks', 'Shown', 'CTR', 'Position'].map((c, i) => h('th', { class: i ? 'text-right' : '' }, c)))),
        h('tbody', p.rows.map((r) => h('tr', [
          h('td', { class: 'max-w-[18rem] truncate' }, p.pathKey ? h('a', { href: r.key, target: '_blank', rel: 'noopener', class: 'a-accent' }, r[p.pathKey]) : r.key),
          h('td', { class: 'text-right tabular-nums font-semibold' }, nf(r.clicks)),
          h('td', { class: 'text-right tabular-nums' }, nf(r.impressions)),
          h('td', { class: 'text-right tabular-nums' }, r.ctr + '%'),
          h('td', { class: 'text-right tabular-nums' }, r.position),
        ]))),
      ])) : h('p', { class: 'p-5 text-sm a-muted' }, p.empty),
    ]);
  },
});

const SimpleTable = defineComponent({
  props: { title: String, rows: Array, cols: Array, bar: String, link: Boolean },
  setup(p) {
    return () => {
      const max = p.bar ? Math.max(1, ...(p.rows || []).map((r) => r[p.bar])) : 1;
      return h('section', { class: 'admin-card overflow-hidden' }, [
        h('header', { class: 'a-card-head' }, h('h3', { class: 'a-card-title' }, p.title)),
        p.rows?.length ? h('table', { class: 'a-table' }, [
          h('thead', h('tr', p.cols.map(([, label], i) => h('th', { class: i ? 'text-right' : '' }, label)))),
          h('tbody', p.rows.map((r) => h('tr', p.cols.map(([key], i) => h('td', { class: i ? 'text-right tabular-nums' : 'max-w-[16rem]' }, i ? nf(r[key]) : [
            h('div', { class: 'truncate' }, p.link ? h('a', { href: r[key], target: '_blank', rel: 'noopener', class: 'a-accent' }, r[key]) : (r[key] === '(not set)' ? 'Unknown' : r[key])),
            p.bar ? h('div', { class: 'mt-1 h-1 rounded-full a-panel-3 overflow-hidden' }, h('div', { class: 'h-full rounded-full bg-[var(--a-accent)]', style: { width: `${(r[p.bar] / max) * 100}%` } })) : null,
          ]))))),
        ]) : h('p', { class: 'p-5 text-sm a-muted' }, 'No data yet.'),
      ]);
    };
  },
});
</script>
