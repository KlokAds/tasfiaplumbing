<template>
  <AdminLayout title="Cache">
    <PageHeader title="Cache" description="The site keeps ready-made copies of settings, SEO scores, pages and Google reviews so it loads fast. Clear them when a change does not show up." />

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
      <section class="admin-card p-6 flex flex-col">
        <div class="w-10 h-10 rounded-xl a-tint-accent a-accent flex items-center justify-center mb-4">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.6m15.4 2A8 8 0 004.6 9m0 0H9m11 11v-5h-.6m0 0a8 8 0 01-15.4-2m15.4 2H15" /></svg>
        </div>
        <h3 class="text-base font-bold">Clear website data</h3>
        <p class="text-sm a-muted mt-1">Recommended first step. Refreshes settings, contact details, SEO scores, sitemap data and Google reviews. Safe any time; visitors notice nothing.</p>
        <ul class="mt-4 space-y-1.5 text-[13px] a-muted">
          <li>✓ Changed a setting but the site still shows the old one</li>
          <li>✓ SEO Health shows old scores</li>
          <li>✓ New Google reviews are not showing yet</li>
        </ul>
        <button @click="clear('data')" :disabled="busy" class="admin-btn-primary mt-6 self-start">Clear website data</button>
      </section>

      <section class="admin-card p-6 flex flex-col">
        <div class="w-10 h-10 rounded-xl a-panel-3 a-muted flex items-center justify-center mb-4">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2" /></svg>
        </div>
        <h3 class="text-base font-bold">Clear everything</h3>
        <p class="text-sm a-muted mt-1">Also clears compiled pages, routes and configuration. Use after a system update or a change to the server's .env file.<span v-if="status.environment === 'production'"> The site is re-optimised straight away.</span></p>
        <dl class="mt-4 grid grid-cols-2 gap-2 text-[13px]">
          <div class="rounded-lg a-panel-2 px-3 py-2"><dt class="a-subtle text-xs">Environment</dt><dd class="font-semibold">{{ status.environment }}</dd></div>
          <div class="rounded-lg a-panel-2 px-3 py-2"><dt class="a-subtle text-xs">Cache store</dt><dd class="font-semibold">{{ status.cache_store }}</dd></div>
          <div class="rounded-lg a-panel-2 px-3 py-2"><dt class="a-subtle text-xs">Config cached</dt><dd class="font-semibold">{{ status.config_cached ? 'Yes' : 'No' }}</dd></div>
          <div class="rounded-lg a-panel-2 px-3 py-2"><dt class="a-subtle text-xs">Compiled views</dt><dd class="font-semibold">{{ status.views }}</dd></div>
        </dl>
        <button @click="clear('all')" :disabled="busy" class="admin-btn-secondary mt-6 self-start">Clear everything</button>
      </section>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import { confirmDialog } from '@/Composables/useConfirm';

defineProps({ status: Object });
const busy = ref(false);

async function clear(scope) {
  const ok = await confirmDialog(scope === 'all'
    ? { title: 'Clear every cache?', message: 'The first page load afterwards is a little slower while everything is rebuilt. Nothing is deleted.', confirmText: 'Clear everything', tone: 'primary' }
    : { title: 'Clear website data?', message: 'Settings, SEO scores and Google reviews are rebuilt on the next visit.', confirmText: 'Clear website data', tone: 'primary' });
  if (!ok) return;
  busy.value = true;
  router.post('/admin/system/cache/clear', { scope }, { preserveScroll: true, onFinish: () => { busy.value = false; } });
}
</script>
