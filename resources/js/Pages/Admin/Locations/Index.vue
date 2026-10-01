<template>
  <AdminLayout title="Locations">
    <PageHeader title="Location pages" description="Only areas you really serve, each with unique local content: property types, common problems, real jobs. Copy-paste area pages count as doorway pages and hurt rankings.">
      <button v-if="can('locations.create')" @click="openModal()" class="admin-btn-primary">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14" /></svg>
        Add location
      </button>
    </PageHeader>

    <div class="admin-card a-stat mb-5 flex flex-col sm:flex-row sm:items-center gap-4">
      <div class="flex-1">
        <p class="a-stat-label">Plan: 8–10 strong location pages</p>
        <div class="mt-2 a-progress"><span :style="{ width: Math.min(100, locations.length / 8 * 100) + '%' }"></span></div>
      </div>
      <p class="text-2xl font-bold tabular-nums">{{ locations.length }}<span class="text-sm a-subtle font-medium"> / 8</span></p>
    </div>

    <div class="admin-card overflow-hidden">
      <div v-if="!locations.length" class="a-empty">
        <p class="font-semibold">No locations yet</p>
        <p class="text-sm a-muted mt-1">Start with the areas where you get the most jobs (check enquiries by postal code).</p>
      </div>
      <div v-else class="overflow-x-auto">
        <table class="a-table">
          <thead>
            <tr><th class="w-10 !pr-0"><input type="checkbox" :checked="bulk.all.value" :indeterminate.prop="bulk.some.value" @change="bulk.toggleAll()" aria-label="Select all" /></th><th>Area</th><th>Region</th><th>Property types</th><th class="text-center">Services</th><th class="text-center">Words</th><th class="text-center">FAQs</th><th>SEO</th><th class="text-right">Actions</th></tr>
          </thead>
          <tbody>
            <tr v-for="l in pager.rows.value" :key="l.id" :class="bulk.has(l.id) && 'a-row-selected'">
              <td class="w-10 !pr-0"><input type="checkbox" :checked="bulk.has(l.id)" @change="bulk.toggle(l.id)" :aria-label="`Select`" /></td>
              <td>
                <button @click="openModal(l)" class="font-semibold hover:underline">{{ l.name }}</button> <ChangeBadge :created="l.created_at" :updated="l.updated_at" class="ml-2" />
                <span v-if="l.is_featured" class="a-badge a-badge-success ml-1.5">In menu</span>
                <span v-if="!l.is_active" class="a-badge ml-1.5">Hidden</span>
                <p class="text-xs a-subtle a-mono">{{ l.public_path }}</p>
              </td>
              <td class="a-muted">{{ l.region || '—' }}</td>
              <td class="text-xs a-muted">{{ (l.property_types || []).join(', ') || '—' }}</td>
              <td class="text-center tabular-nums a-muted">{{ l.service_ids.length }}</td>
              <td class="text-center tabular-nums" :class="l.word_count < 250 ? 'a-text-danger font-semibold' : 'a-muted'">{{ l.word_count }}</td>
              <td class="text-center tabular-nums" :class="l.faqs_count < 2 ? 'a-text-warning font-semibold' : 'a-muted'">{{ l.faqs_count }}</td>
              <td><SeoScore :seo="l.seo" align-right /></td>
              <td class="text-right whitespace-nowrap">
                <a :href="l.public_path" target="_blank" class="a-btn-ghost a-btn-sm">View</a>
                <button v-if="can('locations.edit')" @click="openModal(l)" class="a-btn-ghost a-btn-sm">Edit</button>
                <button v-if="can('locations.delete')" @click="remove(l)" class="a-btn-ghost a-danger a-btn-sm">Delete</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <ClientPagination :pager="pager" />
    </div>

    <Modal :show="modalOpen" :title="editing ? `Edit ${editing.name}` : 'New location'" subtitle="Write like a local: estates, HDB blocks vs condos, typical problems, real jobs done here." width="4xl" @close="modalOpen = false">
      <form @submit.prevent="save" class="space-y-5">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div>
            <label class="admin-label">Area name *</label>
            <input v-model="form.name" type="text" required class="admin-input" placeholder="Tampines" :aria-invalid="!!(form.errors.name || titleConflict)" />
            <p v-if="titleConflict" class="a-error">{{ describe(titleConflict) }}</p>
          </div>
          <div>
            <label class="admin-label">Region</label>
            <SelectBox v-model="form.region" class="admin-input">
              <option :value="null">—</option>
              <option v-for="r in regions" :key="r" :value="r">{{ r }}</option>
            </SelectBox>
          </div>
          <div>
            <label class="admin-label">Order</label>
            <input v-model="form.sort_order" type="number" class="admin-input" />
          </div>
        </div>

        <div>
          <label class="admin-label">Unique local intro *</label>
          <textarea v-model="form.intro" rows="3" class="admin-input" placeholder="e.g. Most Tampines jobs are in 4- and 5-room HDB flats built in the 1990s, where concealed pipe leaks and water heater failures are common..."></textarea>
        </div>
        <div>
          <div class="flex justify-between items-baseline">
            <label class="admin-label">Local content</label>
            <span :class="['text-[11px] font-mono', words < 250 ? 'a-text-danger' : 'a-text-success']">{{ words }} words</span>
          </div>
          <RichEditor v-model="form.description" min-height="260px" />
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="admin-label">Property types you actually serve here</label>
            <div class="flex flex-wrap gap-2">
              <label v-for="t in propertyTypes" :key="t" class="a-choice !py-1.5 !px-3 !gap-2 items-center text-sm">
                <input v-model="form.property_types" type="checkbox" :value="t" /> {{ t }}
              </label>
            </div>
          </div>
          <div>
            <label class="admin-label">Nearby areas <span class="font-normal a-subtle">(comma separated)</span></label>
            <input v-model="nearbyText" type="text" class="admin-input" placeholder="Pasir Ris, Bedok, Simei" />
          </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="admin-label">Latitude</label>
            <input v-model="form.latitude" type="text" class="admin-input" placeholder="1.3526" />
          </div>
          <div>
            <label class="admin-label">Longitude</label>
            <input v-model="form.longitude" type="text" class="admin-input" placeholder="103.9447" />
          </div>
        </div>

        <div>
          <label class="admin-label">Services offered in this area</label>
          <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2 max-h-56 overflow-y-auto a-scroll">
            <label v-for="s in services" :key="s.id" class="a-choice !py-2 text-sm">
              <input v-model="form.service_ids" type="checkbox" :value="s.id" class="mt-0.5" />
              <span class="truncate">{{ s.name }}</span>
            </label>
          </div>
          <p class="a-help">{{ form.service_ids.length }} selected. These are linked from the location page.</p>
        </div>

        <div>
          <label class="admin-label">Real job photo from this area</label>
          <div class="flex items-center gap-4">
            <img v-if="preview || editing?.image" :src="preview || '/' + editing.image" class="w-28 h-16 object-cover rounded-lg border a-border" alt="" />
            <input type="file" accept="image/*" @change="pickImage" />
              <LibraryButton class="mt-1.5" @pick="p => { form.image = p.file; preview = p.url; }" />
          </div>
          <p class="a-help">A photo from a real job here is strong proof of local experience.</p>
        </div>

        <FaqRepeater v-model="form.faqs" :minimum="2" hint="Local questions, e.g. “Do you serve Tampines on weekends?”, “Is HDB approval needed for …?”" />

        <SeoPanel :form="form" path-prefix="/locations/" :title-fallback="form.name ? `Handyman & Repair Services in ${form.name}` : ''" :desc-fallback="form.intro" :editing="!!editing" :original-slug="editing?.slug" :show-focus="false" :meta-warning="metaConflict ? describe(metaConflict) : ''" />

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <label class="a-toggle-row">
            <span><span class="block text-sm font-semibold">Published</span><span class="block text-xs a-muted">Visible and in the sitemap</span></span>
            <input v-model="form.is_active" type="checkbox" class="a-switch" />
          </label>
          <label class="a-toggle-row">
            <span><span class="block text-sm font-semibold">Show in menu & footer</span><span class="block text-xs a-muted">For your main areas</span></span>
            <input v-model="form.is_featured" type="checkbox" class="a-switch" />
          </label>
        </div>

        <div v-if="Object.keys(form.errors).length" class="a-alert a-alert-danger flex-col gap-0.5">
          <p v-for="(msg, key) in form.errors" :key="key">{{ msg }}</p>
        </div>

        <div class="flex justify-end gap-3 pt-4 border-t a-border">
          <button type="button" @click="modalOpen = false" class="admin-btn-secondary">Cancel</button>
          <button type="submit" :disabled="form.processing || !!titleConflict || !!metaConflict" class="admin-btn-primary">{{ form.processing ? 'Saving…' : 'Save location' }}</button>
        </div>
      </form>
    </Modal>
    <BulkBar :bulk="bulk" :can-delete="can('locations.delete')" />
  </AdminLayout>
</template>

<script setup>
import ChangeBadge from '@/Components/Admin/ChangeBadge.vue';
import LibraryButton from '@/Components/Admin/LibraryButton.vue';
import BulkBar from '@/Components/Admin/BulkBar.vue';
import { useBulk } from '@/Composables/useBulk';
import SelectBox from '@/Components/SelectBox.vue';
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
import FaqRepeater from '@/Components/Admin/FaqRepeater.vue';
import RichEditor from '@/Components/Admin/RichEditor.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import { usePermissions } from '@/Composables/usePermissions';
import { useTitleCheck } from '@/Composables/useTitleCheck';

const { can } = usePermissions();

const props = defineProps({
  locations: Array,
  services: Array,
  regions: Array,
  propertyTypes: Array,
});

const modalOpen = ref(false);
const editing = ref(null);
const preview = ref(null);
const nearbyText = ref('');

const blank = () => ({
  name: '', slug: '', region: null, intro: '', description: '', property_types: [], latitude: '', longitude: '',
  sort_order: '', is_active: true, is_featured: false, meta_title: '', meta_desc: '', canonical: '', noindex: false,
  image: null, service_ids: [], faqs: [],
});
const form = useForm(blank());
const { titleConflict, metaConflict, describe } = useTitleCheck(form, 'location', () => editing.value?.id);

const words = computed(() => `${form.intro || ''} ${form.description || ''}`.replace(/<[^>]*>/g, ' ').split(/\s+/).filter(Boolean).length);

function openModal(l = null) {
  form.clearErrors();
  preview.value = null;
  editing.value = l;
  const data = blank();
  if (l) {
    Object.keys(data).forEach(k => {
      if (!['image', 'faqs', 'service_ids'].includes(k) && l[k] !== undefined && l[k] !== null) data[k] = l[k];
    });
    data.property_types = l.property_types || [];
    data.service_ids = [...l.service_ids];
    data.faqs = (l.faqs || []).map(f => ({ question: f.question, answer: f.answer }));
  }
  nearbyText.value = (l?.nearby_areas || []).join(', ');
  form.defaults(data);
  form.reset();
  modalOpen.value = true;
}

async function pickImage(e) {
  const file = await compressImage(e.target.files[0]);
  if (file) { form.image = file; preview.value = URL.createObjectURL(file); }
}

function save() {
  form.transform(d => ({
    ...d,
    nearby_areas: nearbyText.value.split(',').map(s => s.trim()).filter(Boolean),
  })).post(editing.value ? `/admin/locations/${editing.value.id}` : '/admin/locations', {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => { modalOpen.value = false; },
  });
}

async function remove(l) {
  if (await confirmDialog({ title: `Delete the ${l.name} page?`, message: 'Its URL will return 410 (gone). If it gets traffic, hide it instead, or redirect it to the closest area.', confirmText: 'Delete location' })) {
    router.delete(`/admin/locations/${l.id}`, { preserveScroll: true });
  }
}

onMounted(() => {
  const id = Number(new URLSearchParams(window.location.search).get('edit'));
  const l = id && props.locations.find(x => x.id === id);
  if (l) openModal(l);
});

// Long lists are paged (10–500 rows, choice remembered).
const pager = usePaged(computed(() => props.locations), 'locations');

// Select rows for bulk delete (confirm popup; each item follows the normal delete rules).
const bulk = useBulk('locations', () => pager.rows.value, { label: 'area page' });
</script>
