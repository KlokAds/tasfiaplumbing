<template>
  <AdminLayout title="Services">
    <PageHeader title="Service pages" description="Your money pages. Each needs a category, a one-line answer with the price, real prices, 3+ FAQs and 300+ words.">
      <button v-if="can('services.create')" @click="openModal()" class="admin-btn-primary">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14" /></svg>
        Add service
      </button>
    </PageHeader>

    <StickyBar>
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
      <div v-for="s in stats" :key="s.label" class="admin-card a-stat">
        <p class="a-stat-label">{{ s.label }}</p>
        <p class="a-stat-value" :class="s.tone">{{ s.value }}</p>
      </div>
    </div>
    </StickyBar>

    <div class="admin-card overflow-hidden">
      <div class="flex flex-col md:flex-row gap-2 p-4 border-b a-border">
        <input v-model="search" type="search" placeholder="Search services…" class="admin-input md:max-w-xs" />
        <SelectBox v-model="categoryFilter" class="admin-input md:max-w-[15rem]">
          <option value="">All categories</option>
          <option value="none">Without category</option>
          <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
        </SelectBox>
        <label class="inline-flex items-center gap-2 text-sm a-muted md:ml-auto cursor-pointer">
          <input v-model="onlyIssues" type="checkbox" class="a-switch" /> Only pages with SEO errors
        </label>
      </div>

      <div class="overflow-x-auto">
        <table class="a-table">
          <thead>
            <tr>
              <th class="w-10 !pr-0"><input type="checkbox" :checked="bulk.all.value" :indeterminate.prop="bulk.some.value" @change="bulk.toggleAll()" aria-label="Select all" /></th>
            <th>Service</th>
              <th>Category</th>
              <th class="text-center">Words</th>
              <th class="text-center">Prices</th>
              <th class="text-center">FAQs</th>
              <th class="text-center">Articles</th>
              <th>SEO</th>
              <th class="text-right">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="s in pager.rows.value" :key="s.id" :class="bulk.has(s.id) && 'a-row-selected'">
              <td class="w-10 !pr-0"><input type="checkbox" :checked="bulk.has(s.id)" @change="bulk.toggle(s.id)" :aria-label="`Select`" /></td>
              <td>
                <div class="flex items-center gap-3">
                  <img :src="s.image ? '/' + s.image : '/logo.png'" alt="" class="w-12 h-9 rounded-md object-cover a-panel-3 shrink-0" />
                  <div class="min-w-0">
                    <button @click="openModal(s)" class="font-semibold truncate max-w-xs text-left hover:underline">{{ s.name }}</button> <ChangeBadge :created="s.created_at" :updated="s.updated_at" class="ml-2 shrink-0" />
                    <div class="flex items-center gap-1.5">
                      <span class="text-xs a-subtle a-mono truncate max-w-[14rem]">{{ s.public_path }}</span>
                      <span v-if="!s.is_active" class="a-badge">Hidden</span>
                      <span v-if="s.noindex" class="a-badge a-badge-warning">Noindex</span>
                    </div>
                  </div>
                </div>
              </td>
              <td class="a-muted">{{ s.category?.name || '—' }}</td>
              <td class="text-center tabular-nums" :class="s.word_count < 300 ? 'a-text-danger font-semibold' : 'a-muted'">{{ s.word_count }}</td>
              <td class="text-center tabular-nums" :class="!s.prices_count ? 'a-text-warning font-semibold' : 'a-muted'">{{ s.prices_count }}</td>
              <td class="text-center tabular-nums" :class="s.faqs_count < 3 ? 'a-text-warning font-semibold' : 'a-muted'">{{ s.faqs_count }}</td>
              <td class="text-center tabular-nums a-muted">{{ s.articles_count }}</td>
              <td><SeoScore :seo="s.seo" /></td>
              <td class="text-right whitespace-nowrap">
                <a :href="s.public_path" target="_blank" class="a-btn-ghost a-btn-sm">View</a>
                <button v-if="can('services.edit')" @click="openModal(s)" class="a-btn-ghost a-btn-sm">Edit</button>
                <button v-if="can('services.delete')" @click="remove(s)" class="a-btn-ghost a-danger a-btn-sm">Delete</button>
              </td>
            </tr>
          </tbody>
        </table>
        <div v-if="!filtered.length" class="a-empty">
          <p class="font-semibold">No services match</p>
          <p class="text-sm a-muted mt-1">Change the filters or add a service.</p>
        </div>
        <ClientPagination :pager="pager" />
      </div>
    </div>

    <!-- ============ Editor ============ -->
    <Modal :show="modalOpen" :title="editing ? 'Edit service' : 'New service'" subtitle="Summary → facts → body → prices → FAQs. The name is the H1." width="5xl" @close="modalOpen = false">
      <form @submit.prevent="save" class="space-y-5">
        <!-- Unsaved work was put back automatically -->
        <div v-if="autosave.restored.value" class="rounded-xl border px-4 py-3 flex flex-col sm:flex-row sm:items-center gap-3 justify-between" style="border-color: var(--a-accent); background: var(--a-accent-soft)">
          <p class="text-sm"><span class="font-semibold">Your unsaved changes are back</span> <span class="a-muted">(autosaved {{ new Date(autosave.restored.value.saved_at).toLocaleString('en-SG', { dateStyle: 'medium', timeStyle: 'short' }) }}). Save when you are ready.</span></p>
          <button type="button" @click="autosave.undoRestore()" class="admin-btn-secondary a-btn-sm shrink-0">Discard changes</button>
        </div>
        <AutosaveRestore :autosave="autosave" />

        <div class="grid grid-cols-1 xl:grid-cols-[1fr_20rem] gap-6">
          <div class="space-y-5 min-w-0">
            <div>
              <label class="admin-label">Service name (H1) *</label>
              <input v-model="form.name" type="text" required placeholder="e.g. Water Heater Repair" class="admin-input text-base" :aria-invalid="!!(form.errors.name || titleConflict)" />
              <p v-if="form.errors.name" class="a-error">{{ form.errors.name }}</p>
              <p v-else-if="titleConflict" class="a-error">{{ describe(titleConflict) }}</p>
            </div>

            <div>
              <label class="admin-label">Direct answer</label>
              <textarea v-model="form.short_summary" rows="2" maxlength="500" placeholder="e.g. Water heater repair in Singapore usually costs S$80–S$250. Same-day service islandwide, 90-day warranty." class="admin-input"></textarea>
              <p class="a-help">Shown at the top of the page. This is the line Google AI Overviews and ChatGPT quote most. <a href="/admin/seo/guide#service" target="_blank" class="underline">Service page guide</a></p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="admin-label">Response time</label>
                <input v-model="form.response_time" type="text" placeholder="Same day" class="admin-input" />
              </div>
              <div>
                <label class="admin-label">Warranty</label>
                <input v-model="form.warranty" type="text" placeholder="90 days workmanship" class="admin-input" />
              </div>
            </div>

            <div>
              <div class="flex justify-between items-baseline">
                <label class="admin-label">Page content *</label>
                <span :class="['text-[11px] a-mono', words < 300 ? 'a-text-danger' : 'a-text-success']">{{ words }} words{{ words < 300 ? ' · aim for 300+' : '' }}</span>
              </div>
              <RichEditor v-model="form.desc" min-height="380px" />
              <p v-if="form.errors.desc" class="a-error">{{ form.errors.desc }}</p>
            </div>

            <FaqRepeater v-model="form.faqs" :minimum="3" hint="Real customer questions. Put a price FAQ first (e.g. “How much does … cost in Singapore?”)." />

            <SeoPanel :form="form" path-prefix="/service/" :title-fallback="form.name" :desc-fallback="form.short_summary" :editing="!!editing" :original-slug="editing?.slug" :meta-warning="metaConflict ? describe(metaConflict) : ''" />
          </div>

          <aside class="space-y-4 a-side-sticky">
            <div class="a-section !bg-transparent space-y-3">
              <label class="flex items-center justify-between gap-3 cursor-pointer">
                <span>
                  <span class="block text-sm font-semibold">Visible on the website</span>
                  <span class="block text-xs a-muted">Off hides the page and removes it from the sitemap.</span>
                </span>
                <input v-model="form.is_active" type="checkbox" class="a-switch" />
              </label>
              <div>
                <label class="admin-label">Category</label>
                <SelectBox v-model="form.category_id" class="admin-input">
                  <option :value="null">— Choose category —</option>
                  <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                </SelectBox>
                <p v-if="!categories.length" class="a-help"><Link href="/admin/service-categories" class="underline">Create categories first</Link></p>
              </div>
              <div>
                <label class="admin-label">Display order</label>
                <input v-model="form.order" type="number" min="0" class="admin-input" />
              </div>
            </div>

            <ContentQualityPanel :payload="qualityPayload" :active="modalOpen" />

            <div v-if="editing" class="a-section !bg-transparent">
              <div class="flex items-center justify-between mb-2">
                <span class="text-sm font-bold">Prices</span>
                <Link :href="`/admin/pricing?service=${editing.id}`" class="text-xs font-semibold a-accent">Manage</Link>
              </div>
              <ul v-if="editing.prices?.length" class="text-[13px] space-y-1.5">
                <li v-for="p in editing.prices" :key="p.id" class="flex justify-between gap-3">
                  <span class="a-muted truncate">{{ p.item }}</span>
                  <span class="font-semibold a-mono shrink-0">{{ p.label }}</span>
                </li>
              </ul>
              <p v-else class="text-xs a-text-warning">No prices yet. Add 3–5 real price ranges; AI answers quote clear prices.</p>
            </div>

            <div v-for="img in images" :key="img.field">
              <label class="admin-label">{{ img.label }}</label>
              <label class="a-dropzone aspect-[16/9]">
                <img v-if="previews[img.field] || current[img.field]" :src="previews[img.field] || '/' + current[img.field]" class="w-full h-full object-cover" alt="" />
                <span v-else class="text-xs">Click to choose an image</span>
                <input type="file" accept="image/*" class="hidden" @change="e => pickImage(img.field, e)" />
              </label>
              <LibraryButton class="mt-1.5" @pick="p => { form[img.field] = p.file; previews[img.field] = p.url; }" />
              <p v-if="form.errors[img.field]" class="a-error">{{ form.errors[img.field] }}</p>
            </div>
          </aside>
        </div>

        <div class="sticky bottom-0 -mx-6 -mb-6 px-6 py-3 border-t a-border flex flex-col-reverse sm:flex-row sm:items-center justify-between gap-3" style="background: var(--a-panel)">
          <AutosaveStatus :autosave="autosave" />
          <div class="flex justify-end gap-2">
            <button type="button" @click="modalOpen = false" class="a-btn-ghost">Close</button>
            <button type="submit" :disabled="form.processing || hasConflict || !canSave" class="admin-btn-primary">{{ form.processing ? 'Saving…' : editing ? 'Save changes' : 'Create service' }}</button>
          </div>
        </div>
      </form>
    </Modal>
    <BulkBar :bulk="bulk" :can-delete="can('services.delete')" />
  </AdminLayout>
</template>

<script setup>
import ChangeBadge from '@/Components/Admin/ChangeBadge.vue';
import LibraryButton from '@/Components/Admin/LibraryButton.vue';
import BulkBar from '@/Components/Admin/BulkBar.vue';
import { useBulk } from '@/Composables/useBulk';
import StickyBar from '@/Components/Admin/StickyBar.vue';
import SelectBox from '@/Components/SelectBox.vue';
import { computed, onMounted, reactive, ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import ClientPagination from '@/Components/Admin/ClientPagination.vue';
import { usePaged } from '@/Composables/usePaged';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import Modal from '@/Components/Admin/Modal.vue';
import SeoPanel from '@/Components/Admin/SeoPanel.vue';
import SeoScore from '@/Components/Admin/SeoScore.vue';
import FaqRepeater from '@/Components/Admin/FaqRepeater.vue';
import { defineAsyncComponent } from 'vue';
// The editor is the largest script: it loads when an editor is opened, not with the list.
const RichEditor = defineAsyncComponent(() => import('@/Components/Admin/RichEditor.vue'));
import ContentQualityPanel from '@/Components/Admin/ContentQualityPanel.vue';
import AutosaveRestore from '@/Components/Admin/AutosaveRestore.vue';
import AutosaveStatus from '@/Components/Admin/AutosaveStatus.vue';
import { compressImage } from '@/Composables/compressImage';
import { confirmDialog } from '@/Composables/useConfirm';
import { useAutosave } from '@/Composables/useAutosave';
import { useTitleCheck } from '@/Composables/useTitleCheck';
import { usePermissions } from '@/Composables/usePermissions';

const props = defineProps({ services: Array, categories: Array });
const { can } = usePermissions();

const search = ref('');
const categoryFilter = ref('');
const onlyIssues = ref(false);

const filtered = computed(() => props.services.filter(s => {
  if (search.value && !s.name.toLowerCase().includes(search.value.toLowerCase())) return false;
  if (categoryFilter.value === 'none' && s.category_id) return false;
  if (categoryFilter.value && categoryFilter.value !== 'none' && s.category_id !== categoryFilter.value) return false;
  if (onlyIssues.value && !s.seo.errors) return false;
  return true;
}));

const stats = computed(() => {
  const list = props.services;
  const avg = list.length ? Math.round(list.reduce((a, s) => a + (s.seo?.score || 0), 0) / list.length) : 0;
  return [
    { label: 'Service pages', value: list.length },
    { label: 'Without category', value: list.filter(s => !s.category_id).length, tone: list.some(s => !s.category_id) ? 'a-text-warning' : '' },
    { label: 'Without prices', value: list.filter(s => !s.prices_count).length, tone: list.some(s => !s.prices_count) ? 'a-text-warning' : '' },
    { label: 'Average SEO score', value: avg, tone: avg >= 80 ? 'a-text-success' : avg >= 50 ? 'a-text-warning' : 'a-text-danger' },
  ];
});

const images = [
  { field: 'image', label: 'Main image' },
  { field: 'bef_img', label: 'Before photo' },
  { field: 'aft_img', label: 'After photo' },
];

const modalOpen = ref(false);
const editing = ref(null);
const previews = reactive({});
const current = reactive({});

const blank = () => ({
  name: '', slug: '', category_id: null, order: '', short_summary: '', response_time: '', warranty: '',
  desc: '', meta_title: '', meta_desc: '', focus_keyword: '', canonical: '', noindex: false, is_active: true,
  image: null, bef_img: null, aft_img: null, faqs: [],
});
const form = useForm(blank());
const autosave = useAutosave({
  type: 'service',
  recordId: computed(() => editing.value?.id || 0),
  form,
  fields: ['name', 'slug', 'category_id', 'order', 'short_summary', 'response_time', 'warranty', 'desc', 'meta_title', 'meta_desc', 'focus_keyword', 'canonical', 'noindex', 'is_active', 'faqs'],
  active: modalOpen,
});
const { titleConflict, metaConflict, describe } = useTitleCheck(form, 'service', () => editing.value?.id);
const hasConflict = computed(() => !!(titleConflict.value || metaConflict.value));
const canSave = computed(() => (editing.value ? can('services.edit') : can('services.create')));

const words = computed(() => (form.desc || '').replace(/<[^>]*>/g, ' ').split(/\s+/).filter(Boolean).length);
const qualityPayload = computed(() => ({
  type: 'service', id: editing.value?.id || null,
  name: form.name, meta_title: form.meta_title, meta_desc: form.meta_desc, excerpt: form.short_summary,
  body: form.desc, focus_keyword: form.focus_keyword, slug: form.slug,
  image: previews.image || current.image || '', faqs: form.faqs, warranty: form.warranty, response_time: form.response_time,
}));

function openModal(s = null) {
  form.clearErrors();
  Object.keys(previews).forEach(k => delete previews[k]);
  editing.value = s;
  const data = blank();
  if (s) {
    Object.keys(data).forEach(k => {
      if (!['image', 'bef_img', 'aft_img', 'faqs'].includes(k) && s[k] !== undefined && s[k] !== null) data[k] = s[k];
    });
    data.faqs = (s.faqs || []).map(f => ({ question: f.question, answer: f.answer }));
    images.forEach(i => { current[i.field] = s[i.field]; });
  } else {
    images.forEach(i => { current[i.field] = null; });
    data.order = props.services.length + 1;
  }
  form.defaults(data);
  form.reset();
  modalOpen.value = true;
  autosave.start(s);
}

async function pickImage(field, e) {
  const file = await compressImage(e.target.files[0]);
  if (file) {
    form[field] = file;
    previews[field] = URL.createObjectURL(file);
  }
}

function save() {
  const url = editing.value ? `/admin/services/${editing.value.id}` : '/admin/services';
  form.post(url, {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => { autosave.clear(); modalOpen.value = false; },
  });
}

async function remove(s) {
  const ok = await confirmDialog({
    title: `Delete “${s.name}”?`,
    message: 'Its URL will return 410 (gone). If the page has traffic or backlinks, hide it or redirect it to a related service instead.',
    confirmText: 'Delete service',
  });
  if (ok) router.delete(`/admin/services/${s.id}`, { preserveScroll: true });
}

onMounted(() => {
  const params = new URLSearchParams(window.location.search);
  const id = Number(params.get('edit'));
  const s = id && props.services.find(x => x.id === id);
  if (s) openModal(s);
  else if (params.get('new') && can('services.create')) openModal();
});

// Long lists are paged (10–500 rows, choice remembered).
const pager = usePaged(computed(() => filtered.value), 'services');

// Select rows for bulk delete (confirm popup; each item follows the normal delete rules).
const bulk = useBulk('services', () => pager.rows.value, { label: 'service' });
</script>
