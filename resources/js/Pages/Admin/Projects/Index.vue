<template>
  <AdminLayout title="Projects">
    <PageHeader title="Projects" description="Real jobs you finished. Each project linked to a service appears on that service page as proof of experience, which Google and AI search weigh heavily.">
      <button v-if="can('projects.create')" @click="openModal()" class="admin-btn-primary">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14" /></svg>
        Add project
      </button>
    </PageHeader>

    <StickyBar>
    <div v-if="unlinked" class="a-alert a-alert-warning mb-5">
      <span><span class="font-semibold">{{ unlinked }} project(s) are not linked to a service.</span> <span class="a-muted">Link them so they show on the right service page.</span></span>
      <button @click="go({ service: 'none', page: '' })" class="admin-btn-secondary a-btn-sm ml-auto shrink-0">Show them</button>
    </div>

    <div class="flex flex-col md:flex-row gap-2 mb-5">
      <form @submit.prevent="go({ search: searchQuery, page: '' })" class="md:w-80">
        <input v-model="searchQuery" type="search" placeholder="Search by title or area…" class="admin-input" />
      </form>
      <SelectBox :model-value="filters.service || ''" @update:model-value="v => go({ service: v, page: '' })" class="admin-input md:max-w-[16rem]">
        <option value="">All services</option>
        <option value="none">Not linked to a service</option>
        <option v-for="s in services" :key="s.id" :value="s.id">{{ s.name }}</option>
      </SelectBox>
      <p class="text-sm a-muted md:ml-auto self-center"><span class="font-semibold a-text">{{ projects.total }}</span> projects</p>
    </div>
    </StickyBar>

    <BulkSelectAll v-if="can('projects.delete') && projects.data.length" :bulk="bulk" :padded="false" class="mb-3" />
    <div v-if="projects.data.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
      <article v-for="p in projects.data" :key="p.id" :class="['admin-card overflow-hidden flex flex-col group', bulk.has(p.id) && 'ring-2 ring-[var(--a-accent)]']">
        <div class="aspect-[4/3] a-panel-3 relative overflow-hidden">
          <img :src="p.image ? '/' + p.image : '/logo.png'" :alt="p.name" class="w-full h-full object-cover group-hover:scale-[1.03] transition duration-300" loading="lazy" />
          <label class="absolute top-2 left-2 rounded-md p-1 cursor-pointer z-[1]" style="background: var(--a-panel)" title="Select"><input type="checkbox" class="block" :checked="bulk.has(p.id)" @change="bulk.toggle(p.id)" /></label>
          <span v-if="!p.is_active" class="a-badge absolute top-2 left-10">Hidden</span>
          <span v-if="p.video" class="a-badge absolute top-2 right-2">Video</span>
        </div>
        <div class="p-4 flex-1">
          <h3 class="text-sm font-bold line-clamp-2 leading-snug">{{ p.name }}</h3>
          <ChangeBadge :created="p.created_at" :updated="p.updated_at" class="mt-1 self-start" />
          <p class="text-xs a-subtle mt-1">{{ [p.area || p.location?.name, p.property_type, p.completed_on && fmtDate(p.completed_on)].filter(Boolean).join(' · ') || 'No details yet' }}</p>
          <span :class="['a-badge mt-2', p.service ? '' : 'a-badge-warning']">{{ p.service?.name || 'No service' }}</span>
        </div>
        <div class="a-row-actions px-3 py-2 border-t a-border flex justify-end">
          <button v-if="can('projects.edit')" @click="openModal(p)" class="a-btn-ghost a-btn-sm">Edit</button>
          <button v-if="can('projects.delete')" @click="remove(p)" class="a-btn-ghost a-danger a-btn-sm">Delete</button>
        </div>
      </article>
    </div>
    <div v-else class="admin-card a-empty">
      <p class="font-semibold">No projects found</p>
      <p class="text-sm a-muted mt-1">Add photos of finished jobs with the service and area.</p>
    </div>
    <Pagination :meta="projects" />

    <Modal :show="modalOpen" :title="editing ? 'Edit project' : 'Add project'" width="3xl" @close="modalOpen = false">
      <form @submit.prevent="save" class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-[1fr_14rem] gap-5">
          <div class="space-y-4">
            <div>
              <label class="admin-label">Title *</label>
              <input v-model="form.name" type="text" required class="admin-input" placeholder="e.g. Kitchen sink leak fixed in a Tampines 4-room HDB" />
              <p class="a-help">Say what was done and where. This is the photo's caption and alt text.</p>
              <p v-if="form.errors.name" class="a-error">{{ form.errors.name }}</p>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="admin-label">Service</label>
                <SelectBox v-model="form.service_id" class="admin-input">
                  <option :value="null">— Choose service —</option>
                  <option v-for="s in services" :key="s.id" :value="s.id">{{ s.name }}</option>
                </SelectBox>
              </div>
              <div>
                <label class="admin-label">Location page</label>
                <SelectBox v-model="form.location_id" class="admin-input">
                  <option :value="null">— None —</option>
                  <option v-for="l in locations" :key="l.id" :value="l.id">{{ l.name }}</option>
                </SelectBox>
              </div>
              <div>
                <label class="admin-label">Area / estate</label>
                <input v-model="form.area" type="text" class="admin-input" placeholder="e.g. Tampines St 81" />
              </div>
              <div>
                <label class="admin-label">Property type</label>
                <SelectBox v-model="form.property_type" class="admin-input">
                  <option :value="null">—</option>
                  <option v-for="t in propertyTypes" :key="t" :value="t">{{ t }}</option>
                </SelectBox>
              </div>
              <div>
                <label class="admin-label">Completed on</label>
                <DatePicker v-model="form.completed_on" :max="today" placeholder="When was it finished?" class="admin-input" />
              </div>
              <div>
                <label class="admin-label">Video link</label>
                <input v-model="form.video" type="url" class="admin-input" placeholder="https://youtube.com/…" />
                <p v-if="form.errors.video" class="a-error">{{ form.errors.video }}</p>
              </div>
            </div>
            <div>
              <label class="admin-label">What was the problem and what did you do?</label>
              <textarea v-model="form.summary" rows="3" maxlength="1000" class="admin-input" placeholder="2–3 sentences: the problem, the fix, how long it took."></textarea>
            </div>
          </div>
          <div class="space-y-3 a-side-sticky-md">
            <label class="admin-label">Photo {{ editing ? '' : '*' }}</label>
            <label class="a-dropzone aspect-[4/3]">
              <img v-if="preview || editing?.image" :src="preview || '/' + editing.image" class="w-full h-full object-cover" alt="" />
              <span v-else class="text-xs px-3">Click to choose a real photo of the job</span>
              <input type="file" accept="image/*" :required="!editing" class="hidden" @change="pickImage" />
            </label>
            <LibraryButton @pick="p => { form.image = p.file; preview = p.url; }" />
            <p v-if="form.errors.image" class="a-error">{{ form.errors.image }}</p>
            <label class="a-toggle-row">
              <span class="text-sm font-semibold">Visible</span>
              <input v-model="form.is_active" type="checkbox" class="a-switch" />
            </label>
          </div>
        </div>
        <div class="flex justify-end gap-2 pt-3 border-t a-border">
          <button type="button" @click="modalOpen = false" class="admin-btn-secondary">Cancel</button>
          <button type="submit" :disabled="form.processing" class="admin-btn-primary">{{ form.processing ? 'Saving…' : editing ? 'Save project' : 'Add project' }}</button>
        </div>
      </form>
    </Modal>
    <BulkBar :bulk="bulk" :can-delete="can('projects.delete')" />
  </AdminLayout>
</template>

<script setup>
import ChangeBadge from '@/Components/Admin/ChangeBadge.vue';
import BulkSelectAll from '@/Components/Admin/BulkSelectAll.vue';
import LibraryButton from '@/Components/Admin/LibraryButton.vue';
import BulkBar from '@/Components/Admin/BulkBar.vue';
import { useBulk } from '@/Composables/useBulk';
import StickyBar from '@/Components/Admin/StickyBar.vue';
import DatePicker from '@/Components/DatePicker.vue';
import SelectBox from '@/Components/SelectBox.vue';
import { ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import Modal from '@/Components/Admin/Modal.vue';
import Pagination from '@/Components/Admin/Pagination.vue';
import { compressImage } from '@/Composables/compressImage';
import { confirmDialog } from '@/Composables/useConfirm';
import { usePermissions } from '@/Composables/usePermissions';

const props = defineProps({ projects: Object, filters: Object, services: Array, locations: Array, propertyTypes: Array, unlinked: Number });
const { can } = usePermissions();
const today = new Date().toISOString().slice(0, 10);
const fmtDate = d => new Date(d).toLocaleDateString('en-SG', { month: 'short', year: 'numeric' });

const searchQuery = ref(props.filters?.search || '');
function go(patch) {
  const params = { ...props.filters, ...patch };
  Object.keys(params).forEach(k => { if (!params[k]) delete params[k]; });
  router.get('/admin/projects', params, { preserveState: true, preserveScroll: true, replace: true });
}

const modalOpen = ref(false);
const editing = ref(null);
const preview = ref(null);
const blank = () => ({ name: '', video: '', image: null, service_id: null, location_id: null, area: '', property_type: null, summary: '', completed_on: '', is_active: true });
const form = useForm(blank());

function openModal(p = null) {
  form.clearErrors();
  preview.value = null;
  editing.value = p;
  const data = blank();
  if (p) {
    Object.keys(data).forEach(k => { if (k !== 'image' && p[k] !== undefined && p[k] !== null) data[k] = p[k]; });
    data.completed_on = p.completed_on ? String(p.completed_on).slice(0, 10) : '';
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
  form.post(editing.value ? `/admin/projects/${editing.value.id}` : '/admin/projects', {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => { modalOpen.value = false; },
  });
}

async function remove(p) {
  if (await confirmDialog({ title: 'Delete this project?', message: p.name, confirmText: 'Delete project' })) {
    router.delete(`/admin/projects/${p.id}`, { preserveScroll: true });
  }
}

// Select rows for bulk delete (confirm popup; each item follows the normal delete rules).
const bulk = useBulk('projects', () => props.projects.data, { label: 'project' });
</script>
