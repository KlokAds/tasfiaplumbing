<template>
  <AdminLayout title="Price List">
    <PageHeader title="Price list" :description="`One place for every price. Service pages show the featured rows, /pricing shows all, and schema uses the same numbers. Only real prices; review them every ${staleAfterDays} days.`">
      <button v-if="staleIds.length && can('pricing.edit')" @click="markReviewed(staleIds)" class="admin-btn-secondary">Mark {{ staleIds.length }} as reviewed</button>
      <button v-if="can('pricing.create')" @click="openModal()" class="admin-btn-primary">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14" /></svg>
        Add price
      </button>
    </PageHeader>

    <div class="flex flex-col sm:flex-row sm:items-center gap-3 mb-4">
      <SelectBox :model-value="filters.service || ''" @update:model-value="v => router.get('/admin/pricing', v ? { service: v } : {}, { preserveScroll: true })" class="admin-input sm:max-w-xs">
        <option value="">All services</option>
        <option v-for="s in services" :key="s.id" :value="s.id">{{ s.name }}</option>
      </SelectBox>
      <p class="text-sm a-muted sm:ml-auto"><span class="font-semibold a-text">{{ prices.length }}</span> prices<span v-if="staleIds.length"> · <span class="a-text-warning font-semibold">{{ staleIds.length }} need review</span></span></p>
    </div>

    <div v-if="!prices.length" class="admin-card a-empty">
      <div class="a-empty-icon">S$</div>
      <p class="font-semibold">No prices yet</p>
      <p class="text-sm a-muted mt-1">Add 3–5 real price ranges per service, e.g. “Water heater replacement: S$250 – S$450 / unit”.</p>
    </div>

    <BulkSelectAll v-if="can('pricing.delete') && pager.rows.value.length" :bulk="bulk" :padded="false" class="mb-3" hint="prices on this page" />
    <div class="space-y-4">
      <section v-for="group in pager.rows.value" :key="group.service.id" class="admin-card overflow-hidden">
        <header class="a-card-head !py-3">
          <h3 class="a-card-title">{{ group.service.name }}</h3>
          <span class="a-badge">{{ group.items.length }}</span>
        </header>
        <div class="overflow-x-auto">
          <table class="a-table">
            <tbody>
              <tr v-for="p in group.items" :key="p.id" :class="bulk.has(p.id) && 'a-row-selected'">
                <td class="w-10 !pr-0"><input type="checkbox" :checked="bulk.has(p.id)" @change="bulk.toggle(p.id)" :aria-label="`Select`" /></td>
                <td>
                  <span class="font-medium">{{ p.item }}</span>
                  <span v-if="p.is_featured" class="a-badge a-badge-success ml-2">Featured</span>
                  <span v-if="!p.is_active" class="a-badge ml-2">Hidden</span>
                </td>
                <td class="a-mono font-semibold whitespace-nowrap">{{ p.label }}</td>
                <td class="text-xs a-muted">{{ p.gst_note }}</td>
                <td class="text-xs whitespace-nowrap" :class="p.is_stale ? 'a-text-warning font-semibold' : 'a-subtle'">
                  {{ p.last_reviewed_at ? 'Reviewed ' + new Date(p.last_reviewed_at).toLocaleDateString('en-SG', { day: 'numeric', month: 'short', year: 'numeric' }) : 'Never reviewed' }}
                </td>
                <td class="text-right whitespace-nowrap">
                  <button v-if="can('pricing.edit')" @click="openModal(p)" class="a-btn-ghost a-btn-sm">Edit</button>
                  <button v-if="can('pricing.delete')" @click="remove(p)" class="a-btn-ghost a-danger a-btn-sm">Delete</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </div>
    <ClientPagination :pager="pager" :padded="false" class="pt-4" />

    <Modal :show="modalOpen" :title="editing ? 'Edit price' : 'Add price'" width="2xl" @close="modalOpen = false">
      <form @submit.prevent="save" class="space-y-4">
        <div>
          <label class="admin-label">Service *</label>
          <SelectBox v-model="form.service_id" required class="admin-input">
            <option :value="null" disabled>Choose service</option>
            <option v-for="s in services" :key="s.id" :value="s.id">{{ s.name }}</option>
          </SelectBox>
        </div>
        <div>
          <label class="admin-label">Item *</label>
          <input v-model="form.item" type="text" required class="admin-input" placeholder="e.g. Storage water heater replacement (labour only)" />
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div>
            <label class="admin-label">From (S$) *</label>
            <input v-model="form.price_from" type="number" min="0" required class="admin-input" />
          </div>
          <div>
            <label class="admin-label">To (S$)</label>
            <input v-model="form.price_to" type="number" min="0" class="admin-input" placeholder="optional" />
          </div>
          <div>
            <label class="admin-label">Unit</label>
            <input v-model="form.unit" type="text" class="admin-input" placeholder="unit / sqft / hour" />
          </div>
        </div>
        <div>
          <label class="admin-label">GST / note</label>
          <input v-model="form.gst_note" type="text" class="admin-input" placeholder="e.g. Excl. GST, parts extra" />
        </div>
        <div class="rounded-xl a-panel-2 border a-border px-4 py-3 text-sm">Shown on the site as <span class="a-mono font-bold">{{ previewLabel }}</span></div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <label class="a-toggle-row">
            <span><span class="block text-sm font-semibold">Featured</span><span class="block text-xs a-muted">Shown on the service page</span></span>
            <input v-model="form.is_featured" type="checkbox" class="a-switch" />
          </label>
          <label class="a-toggle-row">
            <span><span class="block text-sm font-semibold">Active</span><span class="block text-xs a-muted">Off hides it everywhere</span></span>
            <input v-model="form.is_active" type="checkbox" class="a-switch" />
          </label>
        </div>
        <div v-if="Object.keys(form.errors).length" class="a-alert a-alert-danger flex-col gap-0.5">
          <p v-for="(msg, key) in form.errors" :key="key">{{ msg }}</p>
        </div>
        <div class="flex justify-end gap-3 pt-4 border-t a-border">
          <button type="button" @click="modalOpen = false" class="admin-btn-secondary">Cancel</button>
          <button type="submit" :disabled="form.processing" class="admin-btn-primary">Save price</button>
        </div>
      </form>
    </Modal>
    <BulkBar :bulk="bulk" :can-delete="can('pricing.delete')" />
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
import ClientPagination from '@/Components/Admin/ClientPagination.vue';
import { usePaged } from '@/Composables/usePaged';
import Modal from '@/Components/Admin/Modal.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import { usePermissions } from '@/Composables/usePermissions';

const { can } = usePermissions();

const props = defineProps({
  prices: Array,
  services: Array,
  filters: Object,
  staleAfterDays: Number,
});

const grouped = computed(() => {
  const map = new Map();
  props.prices.forEach(p => {
    const key = p.service?.id ?? 0;
    if (!map.has(key)) map.set(key, { service: p.service || { id: 0, name: 'No service' }, items: [] });
    map.get(key).items.push(p);
  });
  return [...map.values()];
});

const staleIds = computed(() => props.prices.filter(p => p.is_stale).map(p => p.id));

const modalOpen = ref(false);
const editing = ref(null);
const blank = () => ({
  service_id: props.filters?.service ? Number(props.filters.service) : null,
  item: '', price_from: '', price_to: '', unit: '', gst_note: '', is_featured: false, is_active: true,
});
const form = useForm(blank());

const previewLabel = computed(() => {
  const from = Number(form.price_from || 0).toLocaleString('en-SG');
  const to = Number(form.price_to || 0);
  let label = to > Number(form.price_from || 0) ? `S$${from} – S$${to.toLocaleString('en-SG')}` : `From S$${from}`;
  return form.unit ? `${label} / ${form.unit}` : label;
});

function openModal(p = null) {
  form.clearErrors();
  editing.value = p;
  const data = blank();
  if (p) Object.keys(data).forEach(k => { if (p[k] !== undefined && p[k] !== null) data[k] = p[k]; });
  form.defaults(data);
  form.reset();
  modalOpen.value = true;
}

function save() {
  const opts = { preserveScroll: true, onSuccess: () => { modalOpen.value = false; } };
  editing.value ? form.put(`/admin/pricing/${editing.value.id}`, opts) : form.post('/admin/pricing', opts);
}

function markReviewed(ids) {
  router.post('/admin/pricing/reviewed', { ids }, { preserveScroll: true });
}

async function remove(p) {
  if (await confirmDialog({ title: `Delete “${p.item}”?`, message: 'It disappears from the service page, the price list and the schema.', confirmText: 'Delete price' })) router.delete(`/admin/pricing/${p.id}`, { preserveScroll: true });
}

// Long lists are paged (10–500 rows, choice remembered).
const pager = usePaged(computed(() => grouped.value), 'pricing');

// Select rows for bulk delete (confirm popup; each item follows the normal delete rules).
const bulk = useBulk('pricing', () => pager.rows.value.flatMap(g => g.items), { label: 'price' });
</script>
