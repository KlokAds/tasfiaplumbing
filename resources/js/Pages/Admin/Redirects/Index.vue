<template>
  <AdminLayout title="Redirects & 404s">
    <PageHeader title="Redirects & 404s" description="Keeps old URLs, backlinks and bookmarks working. Every changed or deleted page is handled here automatically; add your own for URLs from the old website.">
      <button v-if="can('redirects.create')" @click="openModal()" class="admin-btn-primary">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14" /></svg>
        Add redirect
      </button>
    </PageHeader>

    <div class="space-y-5">
      <StickyBar>
      <div class="grid grid-cols-2 lg:grid-cols-5 gap-3">
        <div v-for="card in statCards" :key="card.label" class="admin-card a-stat">
          <p class="a-stat-label">{{ card.label }}</p>
          <p class="a-stat-value" :class="card.tone">{{ card.value }}</p>
        </div>
      </div>
      </StickyBar>

      <div class="admin-card overflow-hidden">
        <nav class="a-tabs px-4">
          <button @click="tab = 'redirects'" :class="['a-tab', tab === 'redirects' && 'a-tab-active']">Redirects</button>
          <button @click="tab = '404'" :class="['a-tab', tab === '404' && 'a-tab-active']">Broken URLs (404) <span v-if="stats.open_404" class="a-badge a-badge-danger">{{ stats.open_404 }}</span></button>
          <button @click="tab = 'import'" :class="['a-tab', tab === 'import' && 'a-tab-active']">Import / export</button>
        </nav>
        <div class="p-5">
        <!-- Redirects -->
        <div v-if="tab === 'redirects'">
          <form @submit.prevent="applySearch" class="pb-4 flex flex-col sm:flex-row gap-2">
            <input v-model="searchText" type="search" placeholder="Search old or new URL…" class="admin-input sm:max-w-sm" />
            <SelectBox v-model="codeFilter" @change="applySearch" class="admin-input sm:max-w-[10rem]">
              <option value="">All types</option>
              <option value="301">301 permanent</option>
              <option value="302">302 temporary</option>
              <option value="410">410 gone</option>
            </SelectBox>
          </form>
          <div class="overflow-x-auto -mx-5 border-t a-border">
            <table class="a-table">
              <thead>
                <tr>
                  <th class="w-10 !pr-0"><input type="checkbox" :checked="bulk.all.value" :indeterminate.prop="bulk.some.value" @change="bulk.toggleAll()" aria-label="Select all" /></th>
                  <th>Old URL</th>
                  <th>Goes to</th>
                  <th>Type</th>
                  <th class="text-right">Hits</th>
                  <th>Source</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="r in redirects.data" :key="r.id" :class="[!r.is_active && 'opacity-50', bulk.has(r.id) && 'a-row-selected']">
                  <td class="w-10 !pr-0"><input type="checkbox" :checked="bulk.has(r.id)" @change="bulk.toggle(r.id)" :aria-label="`Select`" /></td>
                  <td class="font-mono text-xs a-text break-all max-w-xs">{{ r.from_path }}</td>
                  <td class="font-mono text-xs break-all max-w-xs">
                    <span v-if="r.code === 410" class="a-subtle">— removed —</span>
                    <span v-else class="a-text">{{ r.to_path }}</span>
                    <span v-if="r.is_chain" class="a-badge a-badge-warning ml-1">Chain</span>
                  </td>
                  <td><span :class="codeBadge(r.code)">{{ r.code }}</span></td>
                  <td class="text-right a-muted">{{ r.hit_count }}</td>
                  <td class="text-xs a-muted">{{ r.source }}</td>
                  <td class="text-right whitespace-nowrap">
                    <button v-if="can('redirects.edit')" @click="openModal(r)" class="a-btn-ghost a-btn-sm">Edit</button>
                    <button v-if="can('redirects.delete')" @click="remove(r)" class="a-btn-ghost a-danger a-btn-sm">Delete</button>
                  </td>
                </tr>
              </tbody>
            </table>
            <p v-if="!redirects.data.length" class="text-center text-sm a-muted py-12">No redirects yet. Import the old-URL map from the audit, or add them one by one.</p>
          </div>
          <div class="pt-2"><Pagination :meta="redirects" /></div>
        </div>

        <!-- 404 log -->
        <div v-if="tab === '404'">
          <p class="text-sm a-muted mb-4">
            URLs visitors or Googlebot requested that do not exist. Highest hits first. Point each one to the most relevant live page,
            mark it 410 if it is truly gone, or ignore bot noise (e.g. /wp-login.php).
          </p>
          <div class="overflow-x-auto -mx-5 border-t a-border">
            <table class="a-table">
              <thead>
                <tr>
                  <th>URL</th>
                  <th class="text-right">Hits</th>
                  <th>Last seen</th>
                  <th>Came from</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="n in notFound.data" :key="n.id">
                  <td class="font-mono text-xs a-text break-all max-w-sm">
                    {{ n.path }}
                    <p v-if="n.suggestion" class="mt-1 font-sans text-[12px] a-muted">Did you mean <a :href="n.suggestion" target="_blank" rel="noopener" class="a-accent hover:underline break-all">{{ n.suggestion }}</a>?</p>
                  </td>
                  <td class="text-right font-semibold">{{ n.hits }}</td>
                  <td class="text-xs a-muted whitespace-nowrap">{{ n.last_seen_at ? new Date(n.last_seen_at).toLocaleString('en-SG') : '' }}</td>
                  <td class="text-xs a-muted break-all max-w-xs">{{ n.last_referer || '—' }}</td>
                  <td class="text-right whitespace-nowrap">
                    <template v-if="can('redirects.create')">
                      <button v-if="n.suggestion" @click="openModal(null, n.path, 301, n.suggestion)" class="admin-btn-primary a-btn-sm">Redirect there</button>
                      <button @click="openModal(null, n.path)" :class="[n.suggestion ? 'a-btn-ghost' : 'admin-btn-secondary', 'a-btn-sm']">{{ n.suggestion ? 'Other page' : 'Redirect' }}</button>
                      <button @click="openModal(null, n.path, 410)" class="a-btn-ghost a-btn-sm">Mark gone</button>
                    </template>
                    <button v-if="can('redirects.edit')" @click="ignore(n)" class="a-btn-ghost a-btn-sm">Ignore</button>
                  </td>
                </tr>
              </tbody>
            </table>
            <p v-if="!notFound.data.length" class="text-center text-sm a-muted py-12">No open 404s.</p>
          </div>
          <div class="pt-2"><Pagination :meta="notFound" /></div>
        </div>

        <!-- Import / export -->
        <div v-if="tab === 'import'" class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <div class="space-y-3">
            <h3 class="font-bold a-text">Import CSV</h3>
            <p class="text-sm a-muted">
              Columns: <code class="font-mono text-xs">from,to,code</code>. Code is optional (301 when “to” is set, 410 when empty).
              Existing rows with the same “from” are updated. Chains are flattened automatically. Rows that point to the homepage are rejected.
            </p>
            <pre class="a-code">from,to,code
/service-detail.php?id=5,/service/water-heater-repair,301
/old-page.html,/service/door-repair,301
/promo-2019.html,,410</pre>
            <form v-if="can('redirects.create')" @submit.prevent="importCsv" class="flex flex-wrap items-center gap-3">
              <input type="file" accept=".csv,text/csv" @change="e => importForm.file = e.target.files[0]" />
              <button type="submit" :disabled="!importForm.file || importForm.processing" class="admin-btn-primary">Import</button>
            </form>
            <p v-if="importForm.errors.file" class="text-xs a-text-danger">{{ importForm.errors.file }}</p>
            <pre v-if="$page.props.flash?.error" class="a-alert a-alert-danger text-xs whitespace-pre-wrap">{{ $page.props.flash.error }}</pre>
          </div>
          <div class="space-y-3">
            <h3 class="font-bold a-text">Export</h3>
            <p class="text-sm a-muted">Download every redirect with hit counts, e.g. to review with the audit sheet.</p>
            <a href="/admin/redirects/export" class="admin-btn-secondary inline-flex">Download CSV</a>
            <div class="mt-6 a-alert a-alert-warning flex-col gap-1">
              <p class="font-bold">Rules that protect your rankings</p>
              <p>• Map each old URL to the closest matching new page, never the homepage.</p>
              <p>• One hop only (A → C, not A → B → C).</p>
              <p>• Pages with backlinks get a 301, never a 410.</p>
              <p>• Keep redirects for at least 12 months.</p>
            </div>
          </div>
        </div>
        </div>
      </div>
    </div>

    <Modal :show="modalOpen" :title="editing ? 'Edit redirect' : 'Add redirect'" width="2xl" @close="modalOpen = false">
      <form @submit.prevent="save" class="space-y-4">
        <div>
          <label class="admin-label">Old URL (path) *</label>
          <input v-model="form.from_path" type="text" required class="admin-input a-mono" placeholder="/old-page.html or /page.php?id=5" />
        </div>
        <div>
          <label class="admin-label">Type</label>
          <SelectBox v-model.number="form.code" class="admin-input">
            <option :value="301">301 Moved permanently (normal)</option>
            <option :value="302">302 Temporary</option>
            <option :value="410">410 Gone (no replacement page)</option>
          </SelectBox>
        </div>
        <div v-if="form.code !== 410">
          <label class="admin-label">New URL *</label>
          <input v-model="form.to_path" type="text" class="admin-input a-mono" placeholder="/service/water-heater-repair (or full https:// URL for another domain)" />
        </div>
        <div>
          <label class="admin-label">Note</label>
          <input v-model="form.notes" type="text" class="admin-input text-sm" />
        </div>
        <label class="a-toggle-row">
          <span><span class="block text-sm font-semibold">Active</span><span class="block text-xs a-muted">Switch off to test without deleting.</span></span>
          <input v-model="form.is_active" type="checkbox" class="a-switch" />
        </label>
        <div v-if="Object.keys(form.errors).length" class="a-alert a-alert-danger flex-col gap-0.5">
          <p v-for="(msg, key) in form.errors" :key="key">{{ msg }}</p>
        </div>
        <div class="flex justify-end gap-3 pt-4 border-t a-border">
          <button type="button" @click="modalOpen = false" class="admin-btn-secondary">Cancel</button>
          <button type="submit" :disabled="form.processing" class="admin-btn-primary">Save redirect</button>
        </div>
      </form>
    </Modal>
    <BulkBar :bulk="bulk" :can-delete="can('redirects.delete')" />
  </AdminLayout>
</template>

<script setup>
import BulkBar from '@/Components/Admin/BulkBar.vue';
import { useBulk } from '@/Composables/useBulk';
import StickyBar from '@/Components/Admin/StickyBar.vue';
import SelectBox from '@/Components/SelectBox.vue';
import { confirmDialog } from '@/Composables/useConfirm';
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Modal from '@/Components/Admin/Modal.vue';
import Pagination from '@/Components/Admin/Pagination.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import { usePermissions } from '@/Composables/usePermissions';

const { can } = usePermissions();

const props = defineProps({
  redirects: Object,
  notFound: Object,
  filters: Object,
  stats: Object,
});

const tab = ref(props.filters?.tab || 'redirects');
const searchText = ref(props.filters?.search || '');
const codeFilter = ref(props.filters?.code || '');

const statCards = computed(() => [
  { label: 'Redirects', value: props.stats.total },
  { label: '301 permanent', value: props.stats.permanent },
  { label: '410 gone', value: props.stats.gone },
  { label: 'Used (30 days)', value: props.stats.hits_30d },
  { label: 'Open 404s', value: props.stats.open_404, tone: props.stats.open_404 ? 'a-text-danger' : '' },
]);

const codeBadge = code => ['a-badge', code === 301 ? 'a-badge-success' : code === 410 ? '' : 'a-badge-warning'];

function applySearch() {
  const params = { search: searchText.value, code: codeFilter.value };
  Object.keys(params).forEach(k => { if (!params[k]) delete params[k]; });
  router.get('/admin/redirects', params, { preserveState: true, preserveScroll: true });
}

const modalOpen = ref(false);
const editing = ref(null);
const blank = () => ({ from_path: '', to_path: '', code: 301, is_active: true, notes: '' });
const form = useForm(blank());

function openModal(r = null, fromPath = '', code = 301, toPath = '') {
  form.clearErrors();
  editing.value = r;
  const data = blank();
  if (r) Object.assign(data, { from_path: r.from_path, to_path: r.to_path || '', code: r.code, is_active: r.is_active, notes: r.notes || '' });
  else Object.assign(data, { from_path: fromPath, code, ...(toPath ? { to_path: toPath } : {}) });
  form.defaults(data);
  form.reset();
  modalOpen.value = true;
}

function save() {
  const opts = { preserveScroll: true, onSuccess: () => { modalOpen.value = false; } };
  editing.value ? form.put(`/admin/redirects/${editing.value.id}`, opts) : form.post('/admin/redirects', opts);
}

async function remove(r) {
  if (await confirmDialog({ title: 'Delete this redirect?', message: `${r.from_path} will return 404 again. Keep redirects for at least 12 months.`, confirmText: 'Delete redirect' })) {
    router.delete(`/admin/redirects/${r.id}`, { preserveScroll: true });
  }
}

function ignore(n) {
  router.post(`/admin/not-found/${n.id}/resolve`, {}, { preserveScroll: true });
}

const importForm = useForm({ file: null });
function importCsv() {
  importForm.post('/admin/redirects/import', { forceFormData: true, preserveScroll: true, onSuccess: () => importForm.reset() });
}

// Select rows for bulk delete (confirm popup; each item follows the normal delete rules).
const bulk = useBulk('redirects', () => props.redirects.data, { label: 'redirect' });
</script>
