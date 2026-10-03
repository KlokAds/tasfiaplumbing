<template>
  <AdminLayout title="Visitor counter">
    <PageHeader title="Visitor counter" description="The website's own count of real visitors, the countries they are in, and their WhatsApp, call and chat clicks. Bots and signed-in admins are not counted.">
      <label class="flex items-center gap-2 text-sm font-semibold cursor-pointer select-none">
        <input type="checkbox" :checked="dailyEmail" @change="toggleEmail($event.target.checked)" class="rounded" />
        Email me a summary every day at 6 pm
      </label>
    </PageHeader>

    <!-- Period -->
    <section class="admin-card p-3 sm:p-4 mb-5 flex flex-col lg:flex-row lg:items-center gap-3 lg:gap-5">
      <div class="flex flex-wrap items-center gap-1.5" role="group" aria-label="Period">
        <button v-for="(label, key) in periods" :key="key" type="button" @click="pick(key)"
          :class="['px-3 py-1.5 rounded-lg text-[13.5px] font-semibold transition whitespace-nowrap', current === key ? 'bg-[var(--a-accent)] text-white shadow-sm' : 'a-muted hover:bg-[var(--a-panel-3)]']"
          :aria-pressed="current === key">{{ label }}</button>
      </div>
      <form v-if="current === 'custom'" @submit.prevent="go({ range: 'custom', from, to })" class="flex flex-wrap items-end gap-2">
        <label class="text-[13px] a-muted">From<input v-model="from" type="date" :max="to" required class="admin-input !py-1.5 mt-1 block" /></label>
        <label class="text-[13px] a-muted">To<input v-model="to" type="date" :min="from" required class="admin-input !py-1.5 mt-1 block" /></label>
        <button type="submit" class="admin-btn-primary a-btn-sm">Apply</button>
      </form>
      <p class="lg:ml-auto text-[13px] a-muted"><span class="font-semibold a-text">{{ fmtRange(period.from, period.to) }}</span> · {{ timezone }}</p>
    </section>

    <p v-if="!countingSince" class="a-alert a-alert-info text-sm mb-5">Counting starts now: numbers appear as soon as real people open the website.</p>
    <p v-else class="text-[13px] a-muted mb-4">Counting since {{ fmtDate(countingSince) }}. Earlier days are not in this counter.</p>

    <!-- Totals -->
    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3 mb-6">
      <div v-for="c in cards" :key="c.key" class="admin-card px-4 py-3.5">
        <p class="text-[13px] a-muted">{{ c.label }}</p>
        <p class="text-2xl font-bold tabular-nums mt-0.5">{{ c.value.toLocaleString() }}</p>
      </div>
    </div>

    <div class="grid xl:grid-cols-[1.4fr_1fr] gap-5">
      <!-- Per day -->
      <section class="admin-card overflow-hidden">
        <header class="a-card-head"><div><h3 class="a-card-title">Day by day</h3><p class="a-card-sub">Visitors are people (each counted once a day); the other columns are clicks.</p></div></header>
        <div class="overflow-x-auto max-h-[34rem]">
          <table class="a-table text-sm">
            <thead><tr><th>Day</th><th class="text-right">Visitors</th><th class="text-right">Page views</th><th class="text-right">WhatsApp</th><th class="text-right">Calls</th><th class="text-right">Chats</th><th class="text-right">Messages</th></tr></thead>
            <tbody>
              <tr v-for="d in stats.days" :key="d.day">
                <td class="whitespace-nowrap">{{ fmtDate(d.day) }}</td>
                <td class="text-right tabular-nums font-semibold">{{ d.visitors }}</td>
                <td class="text-right tabular-nums a-muted">{{ d.visit }}</td>
                <td class="text-right tabular-nums" :class="d.whatsapp ? 'a-text-success font-semibold' : 'a-subtle'">{{ d.whatsapp }}</td>
                <td class="text-right tabular-nums" :class="d.call ? 'a-text-success font-semibold' : 'a-subtle'">{{ d.call }}</td>
                <td class="text-right tabular-nums" :class="d.chat ? 'a-text-success font-semibold' : 'a-subtle'">{{ d.chat }}</td>
                <td class="text-right tabular-nums a-muted">{{ d.chat_message }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <div class="space-y-5">
        <section class="admin-card overflow-hidden">
          <header class="a-card-head"><div><h3 class="a-card-title">Countries</h3><p class="a-card-sub">From the visitor's device time zone.</p></div></header>
          <p v-if="!stats.countries.length" class="px-5 py-4 text-sm a-muted">No visitors in this period yet.</p>
          <table v-else class="a-table text-sm">
            <thead><tr><th>Country</th><th class="text-right">Visitors</th><th class="text-right">Contacts</th></tr></thead>
            <tbody>
              <tr v-for="c in stats.countries" :key="c.code || 'none'">
                <td>{{ c.name }}</td>
                <td class="text-right tabular-nums font-semibold">{{ c.visitors }}</td>
                <td class="text-right tabular-nums" :class="c.contacts ? 'a-text-success font-semibold' : 'a-subtle'">{{ c.contacts }}</td>
              </tr>
            </tbody>
          </table>
        </section>

        <section class="admin-card overflow-hidden">
          <header class="a-card-head"><div><h3 class="a-card-title">Where they came from</h3></div></header>
          <ul class="a-divide text-sm">
            <li v-for="s in stats.sources" :key="s.source" class="px-5 py-2.5 flex justify-between gap-3"><span class="truncate">{{ s.source }}</span><span class="tabular-nums font-semibold">{{ s.visitors }}</span></li>
            <li v-if="!stats.sources.length" class="px-5 py-3 a-muted">—</li>
          </ul>
          <p class="px-5 py-2.5 border-t a-border text-[13px] a-muted">Phone {{ stats.devices.mobile || 0 }} · Computer {{ stats.devices.desktop || 0 }}</p>
        </section>
      </div>
    </div>

    <div class="grid lg:grid-cols-2 gap-5 mt-5">
      <section class="admin-card overflow-hidden">
        <header class="a-card-head"><div><h3 class="a-card-title">Most viewed pages</h3></div></header>
        <table class="a-table text-sm">
          <thead><tr><th>Page</th><th class="text-right">Views</th><th class="text-right">Visitors</th></tr></thead>
          <tbody>
            <tr v-for="p in stats.pages" :key="p.path"><td class="max-w-[22rem] truncate"><a :href="p.path" target="_blank" rel="noopener" class="hover:underline">{{ p.path }}</a></td><td class="text-right tabular-nums font-semibold">{{ p.views }}</td><td class="text-right tabular-nums a-muted">{{ p.visitors }}</td></tr>
            <tr v-if="!stats.pages.length"><td colspan="3" class="a-muted">—</td></tr>
          </tbody>
        </table>
      </section>
      <section class="admin-card overflow-hidden">
        <header class="a-card-head"><div><h3 class="a-card-title">Pages people contacted you from</h3><p class="a-card-sub">WhatsApp, call and chat clicks per page.</p></div></header>
        <table class="a-table text-sm">
          <thead><tr><th>Page</th><th class="text-right">Contacts</th></tr></thead>
          <tbody>
            <tr v-for="p in stats.contactPages" :key="p.path"><td class="max-w-[24rem] truncate"><a :href="p.path" target="_blank" rel="noopener" class="hover:underline">{{ p.path }}</a></td><td class="text-right tabular-nums font-semibold a-text-success">{{ p.contacts }}</td></tr>
            <tr v-if="!stats.contactPages.length"><td colspan="2" class="a-muted">—</td></tr>
          </tbody>
        </table>
      </section>
    </div>
  </AdminLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';

const props = defineProps({
  period: Object,
  periods: Object,
  stats: Object,
  labels: Object,
  countingSince: { type: String, default: null },
  dailyEmail: Boolean,
  timezone: String,
});

const current = ref(props.period.key);
const from = ref(props.period.from);
const to = ref(props.period.to);

const cards = computed(() => [
  { key: 'visitors', label: 'Visitors', value: props.stats.totals.visitors },
  ...Object.entries(props.labels).map(([key, label]) => ({ key, label, value: props.stats.totals[key] || 0 })),
]);

function go(params) {
  router.get('/admin/visitors', params, { preserveScroll: true, preserveState: true, replace: true });
}
function pick(key) {
  current.value = key;
  if (key !== 'custom') go(key === '30d' ? {} : { range: key });
}
function toggleEmail(on) {
  router.post('/admin/visitors/daily-email', { on }, { preserveScroll: true });
}

const fmtDate = (d) => new Date(d + 'T00:00:00').toLocaleDateString('en-SG', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' });
const fmtRange = (a, b) => (a === b ? fmtDate(a) : `${fmtDate(a)} – ${fmtDate(b)}`);
</script>
