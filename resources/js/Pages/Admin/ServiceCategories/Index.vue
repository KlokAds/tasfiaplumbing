<template>
  <AdminLayout title="Service Categories">
    <PageHeader title="Service categories" description="Silo hubs such as Leak Repair, Toilets or Water Heaters. They drive the Services menu, breadcrumbs and internal links.">
      <button v-if="can('categories.create')" @click="openModal()" class="admin-btn-primary">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14" /></svg>
        Add category
      </button>
    </PageHeader>

    <div v-if="uncategorised" class="a-alert a-alert-warning mb-5">
      <span class="font-semibold">{{ uncategorised }} service(s) have no category.</span>
      <span class="a-muted">Open a category and tick them, so they appear in the menu and get internal links.</span>
    </div>

    <div v-if="!categories.length" class="admin-card a-empty">
      <p class="font-semibold">No categories yet</p>
      <p class="text-sm a-muted mt-1">Start with 4–6 broad groups that match how customers search.</p>
    </div>

    <BulkSelectAll v-if="can('categories.delete') && categories.length" :bulk="bulk" :padded="false" class="mb-3" />
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
      <article v-for="c in pager.rows.value" :key="c.id" :class="['admin-card p-5 flex flex-col', bulk.has(c.id) && 'ring-2 ring-[var(--a-accent)]']">
        <div class="flex items-start justify-between gap-3">
          <div class="flex items-center gap-3 min-w-0">
            <input type="checkbox" class="shrink-0" :checked="bulk.has(c.id)" @change="bulk.toggle(c.id)" aria-label="Select" />
            <img v-if="c.image" :src="'/' + c.image" alt="" class="w-12 h-12 rounded-lg object-cover shrink-0" />
            <div class="min-w-0">
              <h3 class="font-bold truncate">{{ c.name }} <span v-if="!c.is_active" class="a-badge ml-1">Hidden</span></h3>
              <p class="text-xs a-subtle a-mono truncate">{{ c.public_path }}</p>
            </div>
          </div>
          <SeoScore :seo="c.seo" align-right />
        </div>
        <div class="mt-4 flex flex-wrap gap-1.5">
          <span v-for="s in c.services" :key="s.id" class="a-badge">{{ s.name }}</span>
          <span v-if="!c.services.length" class="text-xs a-text-warning">No services assigned yet</span>
        </div>
        <div class="a-row-actions mt-auto pt-4 flex">
          <a :href="c.public_path" target="_blank" class="a-btn-ghost a-btn-sm">View</a>
          <button v-if="can('categories.edit')" @click="openModal(c)" class="a-btn-ghost a-btn-sm">Edit</button>
          <button v-if="can('categories.delete')" @click="remove(c)" class="a-btn-ghost a-danger a-btn-sm">Delete</button>
        </div>
      </article>
    </div>
    <ClientPagination :pager="pager" :padded="false" class="pt-4" />

    <Modal :show="modalOpen" :title="editing ? 'Edit category' : 'New category'" width="3xl" @close="modalOpen = false">
      <form @submit.prevent="save" class="space-y-5">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div class="sm:col-span-2">
            <label class="admin-label">Name (H1) *</label>
            <input v-model="form.name" type="text" required class="admin-input" placeholder="e.g. Water Heater Services" />
          </div>
          <div>
            <label class="admin-label">Order</label>
            <input v-model="form.sort_order" type="number" class="admin-input" />
          </div>
        </div>

        <div>
          <label class="admin-label">Intro</label>
          <textarea v-model="form.intro" rows="2" class="admin-input" placeholder="What this category covers and who it's for."></textarea>
        </div>
        <div>
          <label class="admin-label">Description</label>
          <RichEditor v-model="form.description" min-height="200px" />
        </div>

        <div>
          <label class="admin-label">Services in this category</label>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-64 overflow-y-auto a-scroll">
            <label v-for="s in services" :key="s.id" class="a-choice !py-2">
              <input v-model="form.service_ids" type="checkbox" :value="s.id" class="mt-0.5" />
              <span class="text-sm min-w-0">
                <span class="block truncate">{{ s.name }}</span>
                <span v-if="s.category_id && s.category_id !== editing?.id" class="block text-[11px] a-text-warning">Moves from another category</span>
              </span>
            </label>
          </div>
          <p class="a-help">{{ form.service_ids.length }} selected. A service belongs to one category.</p>
        </div>

        <div>
          <label class="admin-label">Image</label>
          <div class="flex items-center gap-4">
            <img v-if="preview || editing?.image" :src="preview || '/' + editing.image" class="w-28 h-16 object-cover rounded-lg border a-border" alt="" />
            <input type="file" accept="image/*" @change="pickImage" />
              <LibraryButton class="mt-1.5" @pick="p => { form.image = p.file; preview = p.url; }" />
          </div>
        </div>

        <SeoPanel :form="form" path-prefix="/services/" :title-fallback="form.name" :desc-fallback="form.intro" :editing="!!editing" :original-slug="editing?.slug" :show-focus="false" />

        <label class="a-toggle-row">
          <span><span class="block text-sm font-semibold">Visible</span><span class="block text-xs a-muted">Shown in the Services menu and the sitemap.</span></span>
          <input v-model="form.is_active" type="checkbox" class="a-switch" />
        </label>

        <div class="flex justify-end gap-3 pt-4 border-t a-border">
          <button type="button" @click="modalOpen = false" class="admin-btn-secondary">Cancel</button>
          <button type="submit" :disabled="form.processing" class="admin-btn-primary">{{ form.processing ? 'Saving…' : 'Save category' }}</button>
        </div>
      </form>
    </Modal>
    <BulkBar :bulk="bulk" :can-delete="can('categories.delete')" />
  </AdminLayout>
</template>

<script setup>
import BulkSelectAll from '@/Components/Admin/BulkSelectAll.vue';
import LibraryButton from '@/Components/Admin/LibraryButton.vue';
import BulkBar from '@/Components/Admin/BulkBar.vue';
import { useBulk } from '@/Composables/useBulk';
import { compressImage } from '@/Composables/compressImage';
import { confirmDialog } from '@/Composables/useConfirm';
import { computed, onMounted, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import ClientPagination from '@/Components/Admin/ClientPagination.vue';
import { usePaged } from '@/Composables/usePaged';
import Modal from '@/Components/Admin/Modal.vue';
import SeoPanel from '@/Components/Admin/SeoPanel.vue';
import SeoScore from '@/Components/Admin/SeoScore.vue';
import { defineAsyncComponent } from 'vue';
// The editor is the largest script: it loads when an editor is opened, not with the list.
const RichEditor = defineAsyncComponent(() => import('@/Components/Admin/RichEditor.vue'));
import PageHeader from '@/Components/Admin/PageHeader.vue';
import { usePermissions } from '@/Composables/usePermissions';

const { can } = usePermissions();

const props = defineProps({ categories: Array, services: Array });

const uncategorised = computed(() => props.services.filter(s => !s.category_id).length);

const modalOpen = ref(false);
const editing = ref(null);
const preview = ref(null);

const blank = () => ({
  name: '', slug: '', intro: '', description: '', sort_order: '', is_active: true,
  meta_title: '', meta_desc: '', canonical: '', noindex: false, image: null, service_ids: [],
});
const form = useForm(blank());

function openModal(c = null) {
  form.clearErrors();
  preview.value = null;
  editing.value = c;
  const data = blank();
  if (c) {
    Object.keys(data).forEach(k => {
      if (!['image', 'service_ids'].includes(k) && c[k] !== undefined && c[k] !== null) data[k] = c[k];
    });
    data.service_ids = c.services.map(s => s.id);
  }
  form.defaults(data);
  form.reset();
  modalOpen.value = true;
}

async function pickImage(e) {
  const file = await compressImage(e.target.files[0]);
  if (file) { form.image = file; preview.value = URL.createObjectURL(file); }
}

function save() {
  form.transform(d => ({ ...d, sync_services: true }))
    .post(editing.value ? `/admin/service-categories/${editing.value.id}` : '/admin/service-categories', {
      forceFormData: true,
      preserveScroll: true,
      onSuccess: () => { modalOpen.value = false; },
    });
}

async function remove(c) {
  if (await confirmDialog({ title: `Delete the category “${c.name}”?`, message: 'Its services stay online but lose their category. The category URL returns 410.', confirmText: 'Delete category' })) {
    router.delete(`/admin/service-categories/${c.id}`, { preserveScroll: true });
  }
}

onMounted(() => {
  const id = Number(new URLSearchParams(window.location.search).get('edit'));
  const c = id && props.categories.find(x => x.id === id);
  if (c) openModal(c);
});

// Long lists are paged (10–500 rows, choice remembered).
const pager = usePaged(computed(() => props.categories), 'categories');

// Select rows for bulk delete (confirm popup; each item follows the normal delete rules).
const bulk = useBulk('service-categories', () => pager.rows.value, { label: 'category', plural: 'categories' });
</script>
