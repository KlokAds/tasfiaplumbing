<template>
  <AdminLayout title="Website checklist">
    <PageHeader title="Website checklist" description="Everything the public website needs, checked live against your data. Start with the important items; each one says why it matters and links to where you fix it." />

    <StickyBar>
    <!-- Summary -->
    <section class="admin-card p-5 mb-5">
      <div class="flex flex-col lg:flex-row lg:items-center gap-5">
        <div class="flex items-center gap-4 lg:w-72 shrink-0">
          <div class="relative w-16 h-16 shrink-0">
            <svg viewBox="0 0 36 36" class="w-16 h-16 -rotate-90"><circle cx="18" cy="18" r="15.5" fill="none" stroke="var(--a-panel-3)" stroke-width="3.5" /><circle cx="18" cy="18" r="15.5" fill="none" stroke="var(--a-accent)" stroke-width="3.5" stroke-linecap="round" :stroke-dasharray="`${pct * 0.974} 100`" /></svg>
            <span class="absolute inset-0 grid place-items-center text-sm font-bold tabular-nums">{{ pct }}%</span>
          </div>
          <div>
            <p class="text-base font-bold">{{ done }} of {{ total }} complete</p>
            <p class="text-xs a-muted mt-0.5">{{ mustOpen ? 'Finish the important items first.' : 'All important items are done.' }}</p>
          </div>
        </div>
        <div class="grid grid-cols-3 gap-3 flex-1">
          <button v-for="s in summary" :key="s.key" type="button" @click="status = status === s.key ? 'todo' : s.key"
            :class="['rounded-xl border a-border px-4 py-3 text-left transition hover:border-[var(--a-border-2)]', status === s.key && 'ring-2 ring-[var(--a-accent)] border-transparent']">
            <p class="text-[11px] font-semibold a-subtle flex items-center gap-1.5"><span :class="['w-2 h-2 rounded-full', s.dot]"></span>{{ s.label }}</p>
            <p class="text-2xl font-bold tabular-nums mt-0.5">{{ s.count }}</p>
          </button>
        </div>
      </div>
    </section>
    </StickyBar>

    <div class="grid grid-cols-1 lg:grid-cols-[15rem_1fr] gap-5 items-start">
      <!-- Groups -->
      <nav class="admin-card p-2 lg:sticky lg:top-[8.75rem]" aria-label="Checklist sections">
        <button type="button" :class="['a-nav-item w-full', !group && 'is-active']" @click="group = ''">
          <span class="flex-1 text-left">All sections</span>
          <span class="text-[11px] a-subtle tabular-nums">{{ done }}/{{ total }}</span>
        </button>
        <button v-for="g in groups" :key="g.group" type="button" :class="['a-nav-item w-full', group === g.group && 'is-active']" @click="group = g.group">
          <span class="flex-1 min-w-0 text-left">
            <span class="block truncate">{{ g.group }}</span>
            <span class="block mt-1 h-1 rounded-full a-panel-3 overflow-hidden"><span class="block h-full rounded-full" :class="groupDone(g) === g.items.length ? 'bg-emerald-500' : 'bg-[var(--a-accent)]'" :style="{ width: (groupDone(g) / g.items.length) * 100 + '%' }"></span></span>
          </span>
          <span v-if="groupMust(g)" class="a-badge a-badge-danger !text-[10px] !px-1.5">{{ groupMust(g) }}</span>
          <span v-else class="text-[11px] a-subtle tabular-nums">{{ groupDone(g) }}/{{ g.items.length }}</span>
        </button>
      </nav>

      <!-- Items -->
      <section class="admin-card">
        <header class="a-card-head lg:sticky lg:top-[8.75rem] z-10 rounded-t-[inherit]" style="background: var(--a-panel)">
          <div>
            <h3 class="a-card-title">{{ group || 'All sections' }}</h3>
            <p class="a-card-sub">{{ rows.length }} {{ statusLabel }}</p>
          </div>
          <div class="a-seg">
            <button type="button" :class="status === 'todo' && 'is-on'" @click="status = 'todo'">To do</button>
            <button type="button" :class="status === 'done' && 'is-on'" @click="status = 'done'">Done</button>
            <button type="button" :class="status === 'all' && 'is-on'" @click="status = 'all'">All</button>
          </div>
        </header>

        <ul v-if="rows.length" class="divide-y a-divide">
          <li v-for="i in rows" :key="i.key" class="px-5 py-4 flex items-start gap-4">
            <span :class="['mt-0.5 w-6 h-6 shrink-0 rounded-full grid place-items-center', i.ok ? 'a-tint-success a-text-success' : i.level === 'must' ? 'a-tint-danger a-text-danger' : 'a-tint-warning a-text-warning']">
              <svg v-if="i.ok" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
              <svg v-else class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 7v6m0 4h.01" /></svg>
            </span>
            <div class="flex-1 min-w-0">
              <div class="flex flex-wrap items-center gap-2">
                <p class="text-sm font-semibold">{{ i.label }}</p>
                <span v-if="!i.ok" :class="['a-badge !text-[10px]', i.level === 'must' ? 'a-badge-danger' : 'a-badge-warning']">{{ i.level === 'must' ? 'Important' : 'Recommended' }}</span>
                <span v-if="!group" class="text-[11px] a-subtle">· {{ i.group }}</span>
              </div>
              <p class="text-[13px] a-muted mt-1 leading-relaxed max-w-3xl">{{ i.detail }}</p>
            </div>
            <div class="flex items-center gap-2 shrink-0">
              <a v-if="i.view" :href="i.view" target="_blank" class="admin-btn-secondary a-btn-sm" title="See it on the website">View</a>
              <Link v-if="i.fix && !i.ok" :href="i.fix" class="admin-btn-primary a-btn-sm">Fix</Link>
            </div>
          </li>
        </ul>
        <div v-else class="a-empty">
          <p class="font-semibold">{{ status === 'done' ? 'Nothing finished here yet' : 'All done here' }}</p>
          <p class="text-sm a-muted mt-1">{{ status === 'done' ? 'Items move here as you complete them.' : 'Pick another section on the left.' }}</p>
        </div>
      </section>
    </div>
  </AdminLayout>
</template>

<script setup>
import StickyBar from '@/Components/Admin/StickyBar.vue';
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';

const props = defineProps({ groups: Array });
const group = ref('');
const status = ref('todo');

const all = computed(() => props.groups.flatMap((g) => g.items.map((i) => ({ ...i, group: g.group }))));
const done = computed(() => all.value.filter((i) => i.ok).length);
const total = computed(() => all.value.length);
const mustOpen = computed(() => all.value.filter((i) => !i.ok && i.level === 'must').length);
const pct = computed(() => (total.value ? Math.round((done.value / total.value) * 100) : 0));
const summary = computed(() => [
  { key: 'must', label: 'Important left', count: mustOpen.value, dot: 'bg-red-500' },
  { key: 'should', label: 'Recommended left', count: all.value.filter((i) => !i.ok && i.level !== 'must').length, dot: 'bg-amber-500' },
  { key: 'done', label: 'Done', count: done.value, dot: 'bg-emerald-500' },
]);
const groupDone = (g) => g.items.filter((i) => i.ok).length;
const groupMust = (g) => g.items.filter((i) => !i.ok && i.level === 'must').length;

const rank = (i) => (i.ok ? 2 : i.level === 'must' ? 0 : 1);
const rows = computed(() => all.value
  .filter((i) => !group.value || i.group === group.value)
  .filter((i) => ({ todo: !i.ok, done: i.ok, must: !i.ok && i.level === 'must', should: !i.ok && i.level !== 'must', all: true })[status.value])
  .sort((a, b) => rank(a) - rank(b)));
const statusLabel = computed(() => ({ todo: 'to do', done: 'done', must: 'important to do', should: 'recommended to do', all: 'items' })[status.value]);
</script>
