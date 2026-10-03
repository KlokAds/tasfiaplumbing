<template>
  <AdminLayout title="Article audit">
    <PageHeader title="Article audit" description="Every live article grouped by topic, with Search Console clicks (16 months), length, quality and overlap. Choose what happens to each one. Nothing on the website changes here.">
      <button @click="runAudit" :disabled="running" class="admin-btn-primary">{{ running ? 'Checking all articles…' : 'Run audit again' }}</button>
    </PageHeader>

    <!-- Status -->
    <div class="admin-card mb-4 px-5 py-4 text-sm space-y-1">
      <p v-if="!summary.computed_at" class="font-semibold">No audit yet. Click “Run audit again” (about a minute).</p>
      <template v-else>
        <p><span class="font-semibold">Last checked:</span> {{ when(summary.computed_at) }} · {{ summary.total.toLocaleString() }} of {{ summary.live_articles.toLocaleString() }} live articles ·
          <span :class="summary.search_console ? 'a-text-success' : 'a-text-warning'" class="font-semibold">{{ summary.search_console ? 'Search Console data included' : 'Without Search Console' }}</span></p>
        <p v-if="summary.error" class="a-text-warning">{{ summary.error }}</p>
        <p class="a-muted">Rules: an article with {{ thresholds.keep_clicks }}+ clicks is kept; {{ thresholds.seen_impressions }}+ impressions means Google shows it; under {{ thresholds.thin_words }} words is thin; {{ thresholds.merge_overlap }}%+ of the same text counts as a copy. An article is only merged into a stronger one.</p>
      </template>
    </div>

    <!-- Batch: the only step that changes the website (approved decisions, at most batch_size at a time) -->
    <div class="admin-card mb-4 px-5 py-4 flex flex-col lg:flex-row lg:items-center justify-between gap-3 text-sm">
      <div class="min-w-0">
        <p class="font-bold">Run the approved decisions</p>
        <p v-if="waitingTotal" class="a-muted">Waiting: <template v-for="(n, key, i) in summary.waiting" :key="key">{{ i ? ', ' : '' }}<b>{{ n }}</b> {{ actions[key] }}</template>.
          The next {{ Math.min(waitingTotal, summary.batch_size) }} run when you press the button.</p>
        <p v-else class="a-muted">Nothing waiting. Choose decisions below (or “Accept suggestions”) first.</p>
        <p class="a-subtle">Merge: 301 redirect to the stronger page, the article leaves the site (kept in the database). Noindex: stays live, hidden from Google. Update / New angle: goes on the writing to-do list. Every step can be undone. Nothing runs by itself.</p>
        <p v-if="summary.done" class="mt-1">Done so far: <b>{{ summary.done }}</b> ·
          <button type="button" class="underline" @click="go({ state: 'done', page: '' })">see them</button> ·
          writing to-do: <button type="button" class="underline" @click="go({ state: 'todo', page: '' })">{{ summary.todo }}</button></p>
      </div>
      <button type="button" @click="runBatch" :disabled="!waitingTotal || batching" class="admin-btn-primary shrink-0">{{ batching ? 'Running…' : `Run batch (${Math.min(waitingTotal, summary.batch_size)})` }}</button>
    </div>

    <!-- Suggestions at a glance (click to filter) -->
    <div class="flex flex-wrap gap-2 mb-4">
      <button v-for="(label, key) in actions" :key="key" type="button" @click="go({ suggestion: filters.suggestion === key ? '' : key, page: '' })"
        :class="['a-badge text-sm !px-3 !py-1.5', badge(key), filters.suggestion === key && 'ring-2 ring-[var(--a-accent)]']">
        {{ label }}: {{ (summary.suggested[key] || 0).toLocaleString() }}
      </button>
      <span class="a-badge text-sm !px-3 !py-1.5">Decided: {{ summary.decided.toLocaleString() }} of {{ summary.total.toLocaleString() }}</span>
    </div>

    <div class="admin-card overflow-hidden">
      <form @submit.prevent="go({ search, page: '' })" class="p-4 flex flex-col sm:flex-row gap-3 border-b a-border">
        <input v-model="search" type="search" placeholder="Search a topic or title…" class="admin-input flex-1" aria-label="Search" />
        <SelectBox :model-value="filters.state || ''" class="admin-input sm:!w-48" @update:model-value="(v) => go({ state: v, page: '' })">
          <option value="">All</option>
          <option value="open">Not decided yet</option>
          <option value="decided">Decided, not run yet</option>
          <option value="done">Done (run in a batch)</option>
          <option value="todo">Writing to-do (update / new angle)</option>
        </SelectBox>
        <button type="submit" class="admin-btn-secondary">Search</button>
      </form>

      <p v-if="!groups.data.length" class="p-6 text-sm a-muted">Nothing here.</p>

      <section v-for="g in groups.data" :key="g.key" class="border-b last:border-0 a-border">
        <header class="px-5 py-3 flex flex-wrap items-center justify-between gap-3 a-panel-2">
          <div class="min-w-0">
            <h3 class="font-bold">“{{ g.key }}”</h3>
            <p class="text-sm a-muted">{{ g.size }} article{{ g.size === 1 ? '' : 's' }} on this topic · {{ g.clicks.toLocaleString() }} clicks · {{ g.impressions.toLocaleString() }} impressions</p>
          </div>
          <button v-if="g.rows.some((r) => !r.decision && !r.applied)" type="button" @click="acceptGroup(g)" class="admin-btn-secondary a-btn-sm">Accept suggestions ({{ g.rows.filter((r) => !r.decision && !r.applied).length }})</button>
        </header>

        <div class="overflow-x-auto">
          <table class="a-table text-sm">
            <thead>
              <tr>
                <th>Article</th>
                <th class="text-right">Clicks</th>
                <th class="text-right">Shown</th>
                <th class="text-right">Words</th>
                <th class="text-right">Quality</th>
                <th>Same text</th>
                <th>Suggestion</th>
                <th>Decision</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="r in g.rows" :key="r.id" class="align-top">
                <td class="min-w-[14rem] max-w-[19rem]">
                  <a :href="r.article.path" target="_blank" rel="noopener" class="font-semibold hover:underline line-clamp-2">{{ r.article.name }}</a>
                  <p v-if="r.top_query" class="text-[13px] a-subtle mt-0.5">Top search: “{{ r.top_query }}”<template v-if="r.position"> · position {{ r.position }}</template></p>
                  <p v-if="r.service" class="text-[13px] a-text-warning mt-0.5">Same search as the service page <a :href="r.service.path" target="_blank" rel="noopener" class="underline">{{ r.service.name }}</a></p>
                </td>
                <td class="text-right tabular-nums font-semibold">{{ r.clicks.toLocaleString() }}</td>
                <td class="text-right tabular-nums a-muted">{{ r.impressions.toLocaleString() }}</td>
                <td class="text-right tabular-nums" :class="r.words < thresholds.thin_words ? 'a-text-danger font-semibold' : 'a-muted'">{{ r.words }}</td>
                <td class="text-right tabular-nums" :class="r.score < 60 ? 'a-text-warning' : 'a-muted'">{{ r.score }}</td>
                <td class="min-w-[9rem]">
                  <template v-if="r.dup && r.dup_percent >= 15">
                    <span :class="r.dup_percent >= thresholds.merge_overlap ? 'a-text-danger font-semibold' : 'a-muted'">{{ r.dup_percent }}%</span>
                    <a :href="r.dup.path" target="_blank" rel="noopener" class="block text-[13px] a-subtle hover:underline line-clamp-2">{{ r.dup.name }}</a>
                  </template>
                  <span v-else class="a-subtle">—</span>
                </td>
                <td class="min-w-[14rem] max-w-[18rem]">
                  <span :class="['a-badge', badge(r.suggestion)]">{{ actions[r.suggestion] }}</span>
                  <p class="text-[13px] a-muted mt-1 whitespace-normal">{{ r.reason }}</p>
                  <p v-if="r.target" class="text-[13px] mt-0.5">Into: <a :href="r.target.path" target="_blank" rel="noopener" class="underline">{{ r.target.name }}</a></p>
                </td>
                <td class="min-w-[12rem] w-[13rem] !whitespace-normal">
                  <!-- Already run: what happened, and undo -->
                  <div v-if="r.applied" class="space-y-1.5 whitespace-normal">
                    <span :class="['a-badge', badge(r.applied.action)]">Done: {{ actions[r.applied.action] }}</span>
                    <p v-if="r.applied.to" class="text-[13px] a-muted">301 to <a :href="r.applied.to" target="_blank" rel="noopener" class="underline break-all">{{ r.applied.to }}</a></p>
                    <p v-else-if="['update', 'retarget'].includes(r.applied.action)" class="text-[13px] a-muted">On the writing to-do list.</p>
                    <button type="button" @click="undo(r)" class="a-btn-ghost a-btn-sm">Undo</button>
                  </div>
                  <form v-else @submit.prevent="decide(r)" class="space-y-2">
                    <SelectBox v-model="pick[r.id].decision" class="admin-input a-input-sm" :aria-label="`Decision for ${r.article.name}`">
                      <option value="">— Not decided —</option>
                      <option v-for="(label, key) in actions" :key="key" :value="key">{{ label }}{{ key === r.suggestion ? ' (suggested)' : '' }}</option>
                    </SelectBox>
                    <SelectBox v-if="pick[r.id].decision === 'merge' && !r.service" v-model="pick[r.id].target_id" class="admin-input a-input-sm" aria-label="Merge into">
                      <option :value="null">Merge into…</option>
                      <option v-for="o in targets(g, r)" :key="o.id" :value="o.id">{{ o.name }}</option>
                    </SelectBox>
                    <p v-if="pick[r.id].decision === 'merge' && r.service" class="text-[13px] a-muted">Into the service page {{ r.service.name }}.</p>
                    <div class="flex items-center gap-2">
                      <button type="submit" class="admin-btn-primary a-btn-sm" :disabled="!changed(r)">Save</button>
                      <span v-if="r.decision" :class="['a-badge', badge(r.decision)]">{{ actions[r.decision] }}</span>
                    </div>
                  </form>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <div class="px-4 pb-4"><Pagination :meta="groups" /></div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import Pagination from '@/Components/Admin/Pagination.vue';
import SelectBox from '@/Components/SelectBox.vue';
import { confirmDialog } from '@/Composables/useConfirm';

const props = defineProps({
  groups: Object,
  filters: { type: Object, default: () => ({}) },
  actions: Object,
  summary: Object,
  thresholds: Object,
});

const search = ref(props.filters.search || '');
const running = ref(false);

// The decision being edited per row (saved one at a time).
const pick = reactive({});
watch(() => props.groups.data, (groups) => {
  groups.forEach((g) => g.rows.forEach((r) => {
    pick[r.id] = { decision: r.decision || '', target_id: r.decision_target?.id || r.target?.id || null };
  }));
}, { immediate: true });

const changed = (r) => (pick[r.id].decision || '') !== (r.decision || '')
  || (pick[r.id].decision === 'merge' && (pick[r.id].target_id || null) !== (r.decision_target?.id || null));

function targets(g, r) {
  const list = g.rows.filter((x) => x.id !== r.id).map((x) => x.article);
  if (r.target && !list.some((x) => x.id === r.target.id)) list.unshift(r.target);
  return list;
}

const badge = (key) => ({ keep: 'a-badge-success', update: 'a-badge-info', retarget: 'a-badge-accent', merge: 'a-badge-warning', noindex: 'a-badge-danger' }[key] || '');

function go(params) {
  router.get('/admin/blogs-audit', { ...props.filters, ...params }, { preserveScroll: true, preserveState: true, replace: true });
}

function decide(r) {
  router.post(`/admin/blogs-audit/${r.id}/decide`, { decision: pick[r.id].decision || null, target_id: pick[r.id].target_id || null }, { preserveScroll: true });
}

async function acceptGroup(g) {
  const ok = await confirmDialog({ title: `Accept the suggestions for “${g.key}”?`, message: 'Only articles without a decision get one. You can still change each one. Nothing on the website changes yet.', confirmText: 'Accept' });
  if (ok) router.post('/admin/blogs-audit/accept-group', { group_key: g.key }, { preserveScroll: true });
}

const batching = ref(false);
const waitingTotal = computed(() => Object.values(props.summary.waiting || {}).reduce((a, n) => a + Number(n), 0));

async function runBatch() {
  const n = Math.min(waitingTotal.value, props.summary.batch_size);
  const list = Object.entries(props.summary.waiting || {}).map(([k, v]) => `${v} ${props.actions[k]}`).join(', ');
  const ok = await confirmDialog({
    title: `Run the next ${n} decisions?`,
    message: `Waiting: ${list}. Merged articles get a 301 redirect and leave the site (they stay in the database); noindex articles stay live but hidden from Google. Each step can be undone on this page.`,
    confirmText: `Run ${n}`,
  });
  if (!ok) return;
  batching.value = true;
  router.post('/admin/blogs-audit/batch', {}, { preserveScroll: true, onFinish: () => { batching.value = false; } });
}

async function undo(r) {
  const ok = await confirmDialog({ title: 'Undo this step?', message: `“${r.article.name}” goes back to how it was before the batch (live again, redirect switched off). Its decision is cleared.`, confirmText: 'Undo' });
  if (ok) router.post(`/admin/blogs-audit/${r.id}/undo`, {}, { preserveScroll: true });
}

function runAudit() {
  running.value = true;
  router.post('/admin/blogs-audit/run', {}, { preserveScroll: true, onFinish: () => { running.value = false; } });
}

function when(iso) {
  return new Date(iso).toLocaleString(undefined, { day: 'numeric', month: 'short', year: 'numeric', hour: 'numeric', minute: '2-digit' });
}
</script>
