<template>
  <AdminLayout title="Dashboard">
    <PageHeader :title="`Good ${greeting}, ${firstName}`" :description="today">
      <Link v-for="q in quick" :key="q.href" :href="q.href" :class="q.primary ? 'admin-btn-primary' : 'admin-btn-secondary'">{{ q.label }}</Link>
    </PageHeader>

    <!-- Website checklist + Google numbers -->
    <div v-if="checklist || insights" class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
      <Link v-if="checklist" href="/admin/checklist" class="admin-card p-5 flex items-center gap-4 hover:border-[var(--a-border-2)] transition">
        <div class="relative w-14 h-14 shrink-0">
          <svg viewBox="0 0 36 36" class="w-14 h-14 -rotate-90"><circle cx="18" cy="18" r="15.5" fill="none" stroke="var(--a-panel-3)" stroke-width="4" /><circle cx="18" cy="18" r="15.5" fill="none" stroke="var(--a-accent)" stroke-width="4" stroke-linecap="round" :stroke-dasharray="`${checkPct * 0.974} 100`" /></svg>
          <span class="absolute inset-0 grid place-items-center text-xs font-bold">{{ checkPct }}%</span>
        </div>
        <div class="min-w-0 flex-1">
          <p class="text-sm font-bold">Website checklist</p>
          <p class="text-xs a-muted mt-0.5">{{ checklist.done }} of {{ checklist.total }} done<span v-if="checklist.must_open" class="a-text-danger font-semibold"> · {{ checklist.must_open }} important left</span></p>
        </div>
        <span class="text-xs a-accent font-semibold shrink-0">Open →</span>
      </Link>
      <Link v-if="insights" href="/admin/insights" class="admin-card p-5 hover:border-[var(--a-border-2)] transition">
        <p class="text-sm font-bold">Last 28 days on Google</p>
        <p v-if="insights.empty" class="text-xs a-muted mt-1">Open Search & visitors once to load the numbers.</p>
        <dl v-else class="mt-2 grid grid-cols-3 gap-3">
          <div v-if="insights.clicks !== null"><dt class="text-[11px] a-subtle">Search clicks</dt><dd class="text-xl font-bold tabular-nums">{{ insights.clicks.toLocaleString() }} <span v-if="insights.clicks_before" :class="['text-[11px] font-semibold', insights.clicks >= insights.clicks_before ? 'a-text-success' : 'a-text-danger']">{{ change(insights.clicks, insights.clicks_before) }}</span></dd></div>
          <div v-if="insights.users !== null"><dt class="text-[11px] a-subtle">Visitors</dt><dd class="text-xl font-bold tabular-nums">{{ insights.users.toLocaleString() }} <span v-if="insights.users_before" :class="['text-[11px] font-semibold', insights.users >= insights.users_before ? 'a-text-success' : 'a-text-danger']">{{ change(insights.users, insights.users_before) }}</span></dd></div>
          <div v-if="insights.leads !== null"><dt class="text-[11px] a-subtle">Leads (WhatsApp, calls, forms)</dt><dd class="text-xl font-bold tabular-nums">{{ insights.leads.toLocaleString() }}</dd></div>
        </dl>
      </Link>
    </div>

    <StickyBar>
    <!-- KPIs -->
    <div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
      <Link v-for="k in kpis" :key="k.label" :href="k.href" class="admin-card p-5 hover:border-[var(--a-border-2)] transition">
        <p class="text-xs font-semibold a-muted">{{ k.label }}</p>
        <p class="mt-2 text-3xl font-bold tracking-tight">{{ k.value.toLocaleString() }}<span v-if="k.suffix" class="text-base font-semibold a-subtle">{{ k.suffix }}</span></p>
        <p class="mt-1 text-xs a-subtle">{{ k.hint }}</p>
      </Link>
    </div>
    </StickyBar>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
      <div class="xl:col-span-2 space-y-6">
        <!-- Awaiting approval -->
        <section v-if="pending.length" class="admin-card">
          <header class="flex items-center justify-between px-5 py-4 border-b a-border">
            <div>
              <h3 class="font-bold">Waiting for your approval</h3>
              <p class="text-xs a-muted mt-0.5">Nothing goes live until you approve it.</p>
            </div>
            <Link href="/admin/blogs?tab=review" class="admin-btn-secondary a-btn-sm">Review all</Link>
          </header>
          <ul>
            <li v-for="p in pending" :key="p.type + p.id" class="flex items-center justify-between gap-4 px-5 py-3 border-b last:border-0 a-border">
              <div class="min-w-0">
                <p class="font-medium truncate">{{ p.title }}</p>
                <p class="text-xs a-subtle">{{ p.type === 'new' ? 'New article' : 'Changes to a live article' }} · {{ p.by }} · {{ ago(p.at) }}</p>
              </div>
              <Link :href="p.href" class="admin-btn-primary a-btn-sm">Review</Link>
            </li>
          </ul>
        </section>

        <!-- Needs attention -->
        <section class="admin-card">
          <header class="flex items-center justify-between px-5 py-4 border-b a-border">
            <div>
              <h3 class="font-bold">Needs attention</h3>
              <p class="text-xs a-muted mt-0.5">Highest-impact SEO tasks from the plan.</p>
            </div>
            <Link href="/admin/seo/health" class="admin-btn-secondary a-btn-sm">SEO Health</Link>
          </header>
          <ul v-if="attention.length">
            <li v-for="a in attention" :key="a.label">
              <Link :href="a.href" class="flex items-center justify-between gap-4 px-5 py-3 border-b last:border-0 a-border hover:bg-[var(--a-panel-2)]">
                <span class="flex items-center gap-3 min-w-0">
                  <span :class="['w-2 h-2 rounded-full shrink-0', dot(a)]"></span>
                  <span class="truncate">{{ a.label }}</span>
                </span>
                <span class="flex items-center gap-3 shrink-0">
                  <span class="font-bold tabular-nums">{{ a.count }}<span v-if="a.target" class="a-subtle font-medium"> / {{ a.target }}</span></span>
                  <svg class="w-4 h-4 a-subtle" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M9 5l7 7-7 7" /></svg>
                </span>
              </Link>
            </li>
          </ul>
          <p v-else class="px-5 py-8 text-sm a-muted text-center">Nothing urgent. Good work.</p>
        </section>

      </div>

      <div class="space-y-6">
        <!-- My work -->
        <section class="admin-card p-5">
          <h3 class="font-bold">My work</h3>
          <dl class="mt-3 grid grid-cols-3 gap-2">
            <div class="rounded-lg p-3 a-panel-2"><dt class="text-[11px] a-muted">In review</dt><dd class="text-xl font-bold">{{ mine.pending }}</dd></div>
            <div class="rounded-lg p-3 a-panel-2"><dt class="text-[11px] a-muted">Scheduled</dt><dd class="text-xl font-bold">{{ mine.scheduled }}</dd></div>
            <div class="rounded-lg p-3 a-panel-2"><dt class="text-[11px] a-muted">Autosaved</dt><dd class="text-xl font-bold">{{ mine.autosaved }}</dd></div>
          </dl>
          <div v-if="mine.drafts.length" class="mt-4">
            <p class="text-xs font-semibold a-muted mb-2">Continue writing</p>
            <Link v-for="d in mine.drafts" :key="d.id" :href="`/admin/blogs?tab=mine&edit=${d.id}`" class="block py-2 border-b last:border-0 a-border">
              <p class="text-sm font-medium truncate">{{ d.name }}</p>
              <p v-if="d.review_note" class="text-xs a-text-warning truncate">Feedback: {{ d.review_note }}</p>
              <p v-else class="text-xs a-subtle">Edited {{ ago(d.updated_at) }}</p>
            </Link>
          </div>
        </section>

        <!-- Publishing calendar -->
        <section v-if="scheduled.length" class="admin-card p-5">
          <div class="flex items-center justify-between">
            <h3 class="font-bold">Coming up</h3>
            <Link href="/admin/blogs?tab=scheduled" class="text-xs font-semibold" style="color: var(--a-accent-text)">All scheduled</Link>
          </div>
          <ul class="mt-3">
            <li v-for="s in scheduled" :key="s.id" class="flex gap-3 py-2 border-b last:border-0 a-border">
              <div class="w-11 shrink-0 rounded-lg a-panel-2 text-center py-1">
                <p class="text-[10px] font-bold uppercase a-subtle">{{ fmt(s.at, { month: 'short' }) }}</p>
                <p class="text-base font-bold leading-tight">{{ fmt(s.at, { day: 'numeric' }) }}</p>
              </div>
              <div class="min-w-0">
                <Link :href="`/admin/blogs?tab=scheduled&edit=${s.id}`" class="block text-sm font-medium truncate hover:underline">{{ s.name }}</Link>
                <p class="text-xs a-subtle">{{ fmt(s.at, { hour: 'numeric', minute: '2-digit' }) }} · {{ s.by }}</p>
              </div>
            </li>
          </ul>
        </section>

        <!-- SEO snapshot -->
        <section class="admin-card p-5">
          <div class="flex items-center justify-between">
            <h3 class="font-bold">SEO score</h3>
            <Link href="/admin/seo/health" class="text-xs font-semibold" style="color: var(--a-accent-text)">Details</Link>
          </div>
          <div class="mt-4 space-y-4">
            <Link v-for="(t, type) in seoByType" :key="type" :href="`/admin/seo/health?type=${type}`" class="block">
              <div class="flex justify-between text-sm mb-1.5">
                <span class="font-medium">{{ labels[type] }} <span class="a-subtle text-xs">{{ t.total }}</span></span>
                <span class="font-bold tabular-nums" :class="tone(t.avg_score)">{{ t.avg_score }}</span>
              </div>
              <div class="h-1.5 rounded-full a-panel-2 overflow-hidden">
                <div class="h-full rounded-full" :class="bar(t.avg_score)" :style="{ width: t.avg_score + '%' }"></div>
              </div>
            </Link>
          </div>
        </section>
      </div>
    </div>

      <!-- Recent enquiries -->
      <section v-if="recentMessages.length" class="admin-card overflow-hidden mt-6">
        <header class="flex items-center justify-between px-5 py-4 border-b a-border">
          <h3 class="font-bold">Latest enquiries</h3>
          <Link href="/admin/messages" class="admin-btn-secondary a-btn-sm">Open inbox</Link>
        </header>
        <div class="overflow-x-auto">
          <table class="a-table">
            <thead><tr><th>Customer</th><th>Subject</th><th>Received</th><th class="text-right">Actions</th></tr></thead>
            <tbody>
              <tr v-for="m in recentMessages" :key="m.id">
                <td>
                  <p class="font-semibold">{{ m.name || 'Unknown' }}</p>
                  <p class="text-xs a-subtle">{{ m.phone || m.email }}</p>
                </td>
                <td class="max-w-xs truncate a-muted">{{ m.subject || '—' }}</td>
                <td class="whitespace-nowrap a-muted">{{ ago(m.created_at) }}</td>
                <td class="text-right"><span v-if="!m.is_read" class="a-badge a-badge-accent">New</span></td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
  </AdminLayout>
</template>

<script setup>
import StickyBar from '@/Components/Admin/StickyBar.vue';
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';

const props = defineProps({
  checklist: Object,
  insights: Object,
  kpis: Array,
  attention: Array,
  pending: Array,
  mine: Object,
  scheduled: { type: Array, default: () => [] },
  recentMessages: Array,
  seoByType: Object,
  quick: Array,
});

const page = usePage();
const firstName = computed(() => (page.props.auth?.user?.name || '').split(' ')[0]);
const greeting = computed(() => {
  const h = new Date().getHours();
  return h < 12 ? 'morning' : h < 17 ? 'afternoon' : 'evening';
});
const today = new Date().toLocaleDateString('en-SG', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });

const checkPct = computed(() => (props.checklist?.total ? Math.round((props.checklist.done / props.checklist.total) * 100) : 0));
const change = (now, before) => { const d = Math.round(((now - before) / before) * 100); return `${d >= 0 ? '▲' : '▼'} ${Math.abs(d)}%`; };

const labels = { service: 'Services', category: 'Categories', location: 'Locations', article: 'Articles' };

function dot(a) {
  if (a.level === 'ok') return 'bg-emerald-500';
  return a.level === 'error' ? 'bg-red-500' : 'bg-amber-500';
}
const tone = s => (s >= 80 ? 'a-text-success' : s >= 50 ? 'a-text-warning' : 'a-text-danger');
const bar = s => (s >= 80 ? 'bg-emerald-500' : s >= 50 ? 'bg-amber-500' : 'bg-red-500');

const fmt = (iso, opts) => new Date(iso).toLocaleString('en-SG', opts);

function ago(iso) {
  if (!iso) return '';
  const diff = (Date.now() - new Date(iso).getTime()) / 1000;
  if (diff < 60) return 'just now';
  if (diff < 3600) return Math.floor(diff / 60) + ' min ago';
  if (diff < 86400) return Math.floor(diff / 3600) + ' h ago';
  if (diff < 7 * 86400) return Math.floor(diff / 86400) + ' d ago';
  return new Date(iso).toLocaleDateString('en-SG', { day: 'numeric', month: 'short' });
}
</script>
