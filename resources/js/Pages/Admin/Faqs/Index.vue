<template>
  <AdminLayout title="FAQs">
    <PageHeader title="FAQ library" description="Every FAQ on the site. Use real customer questions from WhatsApp and calls, and start each answer with the direct answer. Service, location and article FAQs can also be edited inside each page.">
      <button v-if="can('faqs.create')" @click="openModal()" class="admin-btn-primary">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14" /></svg>
        Add FAQ
      </button>
    </PageHeader>

    <div class="flex flex-col md:flex-row md:items-end gap-3 mb-4">
      <nav class="a-tabs flex-1">
        <button v-for="tab in tabs" :key="tab.key" @click="go({ scope: tab.key, page: '' })" :class="['a-tab', (filters.scope || '') === tab.key && 'a-tab-active']">
          {{ tab.label }} <span class="a-badge">{{ tab.count }}</span>
        </button>
      </nav>
      <form @submit.prevent="go({ search, page: '' })" class="md:w-72">
        <input v-model="search" type="search" placeholder="Search questions and answers…" class="admin-input" />
      </form>
    </div>

    <div class="admin-card overflow-hidden">
      <BulkSelectAll v-if="can('faqs.delete') && faqs.data.length" :bulk="bulk" />
      <ul v-if="faqs.data.length" class="a-divide">
        <li v-for="f in faqs.data" :key="f.id" :class="['px-5 py-4 flex items-start justify-between gap-4 a-hover', bulk.has(f.id) && 'a-row-selected']">
          <input type="checkbox" class="mt-1 shrink-0" :checked="bulk.has(f.id)" @change="bulk.toggle(f.id)" aria-label="Select" />
          <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-1.5 mb-1">
              <span class="a-badge">{{ f.scope_label }}</span>
              <span v-if="f.parent_name" class="text-xs a-subtle truncate">{{ f.parent_name }}</span>
              <span v-if="!f.is_active" class="a-badge a-badge-warning">Hidden</span>
            </div>
            <p class="font-semibold">{{ f.question }}</p>
            <p class="text-sm a-muted mt-1 whitespace-pre-line line-clamp-3">{{ f.answer }}</p>
          </div>
          <div class="a-row-actions shrink-0 flex">
            <button v-if="can('faqs.edit')" @click="openModal(f)" class="a-btn-ghost a-btn-sm">Edit</button>
            <button v-if="can('faqs.delete')" @click="remove(f)" class="a-btn-ghost a-danger a-btn-sm">Delete</button>
          </div>
        </li>
      </ul>
      <div v-else class="a-empty">
        <div class="a-empty-icon">?</div>
        <p class="font-semibold">No FAQs here yet</p>
        <p class="text-sm a-muted mt-1">Add the questions customers ask most, starting with price.</p>
      </div>
      <div class="px-4 pb-4"><Pagination :meta="faqs" /></div>
    </div>

    <Modal :show="modalOpen" :title="editing ? 'Edit FAQ' : 'Add FAQ'" width="2xl" @close="modalOpen = false">
      <form @submit.prevent="save" class="space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="admin-label">Belongs to</label>
            <SelectBox v-model="form.scope" :disabled="editing?.scope === 'article'" class="admin-input">
              <option value="global">Global (FAQ hub / homepage)</option>
              <option value="service">A service page</option>
              <option value="location">A location page</option>
              <option v-if="editing?.scope === 'article'" value="article">An article</option>
            </SelectBox>
          </div>
          <div v-if="form.scope === 'service' || form.scope === 'location'">
            <label class="admin-label">Page</label>
            <SelectBox v-model="form.parent_id" required class="admin-input">
              <option :value="null" disabled>Choose…</option>
              <option v-for="p in parents[form.scope]" :key="p.id" :value="p.id">{{ p.name }}</option>
            </SelectBox>
          </div>
        </div>
        <div>
          <label class="admin-label">Question *</label>
          <input v-model="form.question" type="text" required class="admin-input" />
        </div>
        <div>
          <label class="admin-label">Answer *</label>
          <textarea v-model="form.answer" rows="4" required class="admin-input"></textarea>
          <p class="a-help">Answer in the first sentence, then add detail. 40–80 words works best for Google and AI answers.</p>
        </div>
        <label class="a-toggle-row">
          <span><span class="block text-sm font-semibold">Show on the website</span><span class="block text-xs a-muted">Visible FAQs are also added to the page's FAQ schema.</span></span>
          <input v-model="form.is_active" type="checkbox" class="a-switch" />
        </label>
        <div v-if="Object.keys(form.errors).length" class="a-alert a-alert-danger flex-col gap-0.5">
          <p v-for="(msg, key) in form.errors" :key="key">{{ msg }}</p>
        </div>
        <div class="flex justify-end gap-3 pt-4 border-t a-border">
          <button type="button" @click="modalOpen = false" class="admin-btn-secondary">Cancel</button>
          <button type="submit" :disabled="form.processing" class="admin-btn-primary">Save FAQ</button>
        </div>
      </form>
    </Modal>
    <BulkBar :bulk="bulk" :can-delete="can('faqs.delete')" />
  </AdminLayout>
</template>

<script setup>
import BulkSelectAll from '@/Components/Admin/BulkSelectAll.vue';
import BulkBar from '@/Components/Admin/BulkBar.vue';
import { useBulk } from '@/Composables/useBulk';
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
  faqs: Object,
  filters: Object,
  counts: Object,
  parents: Object,
});

const search = ref(props.filters?.search || '');
const tabs = computed(() => [
  { key: '', label: 'All', count: Object.values(props.counts).reduce((a, b) => a + b, 0) },
  { key: 'global', label: 'Global', count: props.counts.global },
  { key: 'service', label: 'Services', count: props.counts.service },
  { key: 'location', label: 'Locations', count: props.counts.location },
  { key: 'article', label: 'Articles', count: props.counts.article },
]);

function go(patch) {
  const params = { ...props.filters, ...patch };
  Object.keys(params).forEach(k => { if (!params[k]) delete params[k]; });
  router.get('/admin/faqs', params, { preserveState: true, preserveScroll: true, replace: true });
}

const modalOpen = ref(false);
const editing = ref(null);
const blank = () => ({ scope: 'global', parent_id: null, question: '', answer: '', is_active: true, sort_order: 0 });
const form = useForm(blank());

function openModal(f = null) {
  form.clearErrors();
  editing.value = f;
  const data = blank();
  if (f) Object.assign(data, { scope: f.scope, parent_id: f.parent_id, question: f.question, answer: f.answer, is_active: f.is_active, sort_order: f.sort_order });
  form.defaults(data);
  form.reset();
  modalOpen.value = true;
}

function save() {
  const opts = { preserveScroll: true, onSuccess: () => { modalOpen.value = false; } };
  editing.value ? form.put(`/admin/faqs/${editing.value.id}`, opts) : form.post('/admin/faqs', opts);
}

async function remove(f) {
  if (await confirmDialog({ title: 'Delete this FAQ?', message: f.question, confirmText: 'Delete FAQ' })) router.delete(`/admin/faqs/${f.id}`, { preserveScroll: true });
}

// Select rows for bulk delete (confirm popup; each item follows the normal delete rules).
const bulk = useBulk('faqs', () => props.faqs.data, { label: 'FAQ' });
</script>
