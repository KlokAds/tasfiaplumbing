<template>
  <AdminLayout title="Reviews">
    <PageHeader title="Reviews" description="Shown on the Reviews page and the homepage. Google reviews appear automatically once connected; add your own reviews for customers who did not post on Google.">
      <a href="/reviews" target="_blank" class="admin-btn-secondary">View reviews page</a>
      <button v-if="can('reviews.create')" @click="openModal()" class="admin-btn-primary">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14" /></svg>
        Add review
      </button>
    </PageHeader>

    <div class="grid grid-cols-1 xl:grid-cols-[1fr_24rem] gap-6">
      <!-- ============ Google ============ -->
      <section class="admin-card overflow-hidden xl:order-2 h-fit">
        <header class="a-card-head">
          <div class="flex items-center gap-2.5">
            <svg class="w-5 h-5" viewBox="0 0 48 48" aria-hidden="true"><path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3C33.7 32.7 29.2 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.3-.1-2.4-.4-3.5z"/><path fill="#FF3D00" d="M6.3 14.7l6.6 4.8C14.7 15.1 19 12 24 12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 16.3 4 9.7 8.3 6.3 14.7z"/><path fill="#4CAF50" d="M24 44c5.2 0 9.9-2 13.4-5.2l-6.2-5.2C29.2 35.1 26.7 36 24 36c-5.2 0-9.6-3.3-11.3-7.9l-6.5 5C9.5 39.6 16.2 44 24 44z"/><path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-.8 2.2-2.2 4.2-4.1 5.6l6.2 5.2C37 38.2 44 33 44 24c0-1.3-.1-2.4-.4-3.5z"/></svg>
            <div>
              <h3 class="a-card-title">Google reviews</h3>
              <p class="a-card-sub">Pulled live from your Google Business Profile.</p>
            </div>
          </div>
          <span :class="['a-badge', status.cls]"><span class="a-dot"></span>{{ status.label }}</span>
        </header>

        <div v-if="profile" class="px-5 py-4 border-b a-border">
          <p class="a-alert a-alert-success text-xs">
            <b>All reviews are imported</b> from your Business Profile{{ profile.name ? ` (${profile.name})` : '' }}:
            {{ imported.length }} reviews<span v-if="profile.rating">, {{ profile.rating }}★ overall</span>. The Places API settings below are only a fallback.
          </p>
          <Link href="/admin/insights/google" class="mt-2 inline-block text-xs a-accent font-semibold">Google connections →</Link>
        </div>
        <div v-else class="px-5 pt-4">
          <p class="a-alert a-alert-info text-xs">The Places API only gives Google's 5 "most relevant" reviews. To show <b>every</b> review, connect your Business Profile under <Link href="/admin/insights/google" class="font-semibold underline">Insights → Google connections</Link>.</p>
        </div>
        <div v-if="google.result?.ok" class="px-5 py-4 border-b a-border flex items-center gap-4">
          <p class="text-3xl font-bold tabular-nums">{{ google.result.rating?.toFixed(1) }}</p>
          <div>
            <div class="flex gap-0.5 text-[#f5a623]"><span v-for="i in 5" :key="i" :class="i <= Math.round(google.result.rating) ? '' : 'opacity-25'">★</span></div>
            <p class="text-xs a-muted">{{ google.result.total }} reviews on Google · {{ google.result.reviews.length }} shown on the site</p>
          </div>
          <button @click="refresh" class="a-btn-ghost a-btn-sm ml-auto" title="Fetch again from Google">Refresh</button>
        </div>
        <div v-else-if="google.result && !google.result.ok" class="px-5 py-3 border-b a-border">
          <p class="a-alert a-alert-danger">{{ google.result.error }}</p>
        </div>

        <form @submit.prevent="saveGoogle" class="p-5 space-y-4">
          <label class="a-toggle-row">
            <span>
              <span class="block text-sm font-semibold">Show Google reviews on the website</span>
              <span class="block text-xs a-muted mt-0.5">Newest 5 reviews Google shares, plus your star rating.</span>
            </span>
            <input v-model="gForm.enabled" type="checkbox" class="a-switch mt-0.5" :disabled="!can('reviews.google')" />
          </label>

          <div>
            <label class="admin-label">Google Place ID</label>
            <input v-model="gForm.place_id" type="text" class="admin-input a-mono text-xs" placeholder="ChIJ…" :disabled="!can('reviews.google')" />
            <p v-if="gForm.errors.place_id" class="a-error">{{ gForm.errors.place_id }}</p>
            <p class="a-help">Find it with Google's <a href="https://developers.google.com/maps/documentation/places/web-service/place-id" target="_blank" rel="noopener" class="underline">Place ID Finder</a>: search your business name and copy the ID.</p>
          </div>

          <div>
            <label class="admin-label">Places API key</label>
            <input v-model="gForm.api_key" type="password" autocomplete="off" class="admin-input a-mono text-xs" :placeholder="google.has_key ? 'Saved (hidden). Paste a new key to replace it.' : 'AIza…'" :disabled="!can('reviews.google')" />
            <p v-if="gForm.errors.api_key" class="a-error">{{ gForm.errors.api_key }}</p>
            <p class="a-help">Google Cloud Console → enable “Places API (New)” → create an API key and restrict it to that API. Stored encrypted; never shown on the website.</p>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="admin-label">Show reviews rated</label>
              <SelectBox v-model="gForm.min_rating" class="admin-input" :disabled="!can('reviews.google')">
                <option value="5">5 stars only</option>
                <option value="4">4 stars and up</option>
                <option value="3">3 stars and up</option>
                <option value="1">All ratings</option>
              </SelectBox>
            </div>
            <label class="flex flex-col justify-end gap-2 cursor-pointer">
              <span class="admin-label !mb-0">Your own reviews</span>
              <span class="flex items-center gap-2 text-sm a-muted h-[38px]"><input v-model="gForm.show_own" type="checkbox" class="a-switch" :disabled="!can('reviews.google')" /> Show too</span>
            </label>
          </div>

          <button v-if="can('reviews.google')" type="submit" :disabled="gForm.processing" class="admin-btn-primary w-full">{{ gForm.processing ? 'Connecting…' : 'Save and test connection' }}</button>
          <p v-else class="text-xs a-subtle">You can view these settings. Changing them needs the “Connect Google reviews” permission.</p>
        </form>
      </section>

      <!-- ============ Own reviews ============ -->
      <section class="admin-card overflow-hidden xl:order-1">
        <header class="a-card-head">
          <div>
            <h3 class="a-card-title">Your reviews <span class="a-subtle font-medium">{{ reviews.length }}</span></h3>
            <p class="a-card-sub">Only add real reviews you can show proof of (message, email, signed form).</p>
          </div>
        </header>
        <BulkSelectAll v-if="can('reviews.delete') && reviews.length" :bulk="bulk" />
        <ul v-if="reviews.length" class="a-divide">
          <li v-for="r in pager.rows.value" :key="r.id" :class="['flex gap-4 px-5 py-4', bulk.has(r.id) && 'a-row-selected']">
            <input type="checkbox" class="mt-3 shrink-0" :checked="bulk.has(r.id)" @change="bulk.toggle(r.id)" aria-label="Select" />
            <img v-if="r.img" :src="'/' + r.img" alt="" class="w-10 h-10 rounded-full object-cover shrink-0" />
            <span v-else class="w-10 h-10 rounded-full a-inverse flex items-center justify-center text-sm font-bold shrink-0">{{ r.name.charAt(0) }}</span>
            <div class="min-w-0 flex-1">
              <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                <p class="font-semibold">{{ r.name }}</p>
                <span class="text-[#f5a623] text-sm tracking-tight">{{ '★'.repeat(r.rating || 5) }}<span class="opacity-25">{{ '★'.repeat(5 - (r.rating || 5)) }}</span></span>
                <span v-if="!r.is_active" class="a-badge">Hidden</span>
              </div>
              <p class="text-xs a-subtle">{{ [r.job, r.location, r.review_date && new Date(r.review_date).toLocaleDateString('en-SG', { month: 'short', year: 'numeric' })].filter(Boolean).join(' · ') || 'No details' }}</p>
              <p class="mt-1.5 text-[13px] a-muted line-clamp-3">{{ strip(r.desc) }}</p>
            </div>
            <div class="a-row-actions flex flex-col sm:flex-row items-end sm:items-start shrink-0">
              <button v-if="can('reviews.edit')" @click="openModal(r)" class="a-btn-ghost a-btn-sm">Edit</button>
              <button v-if="can('reviews.delete')" @click="remove(r)" class="a-btn-ghost a-danger a-btn-sm">Delete</button>
            </div>
          </li>
        </ul>
        <div v-else class="a-empty">
          <div class="a-empty-icon">★</div>
          <p class="font-semibold">No reviews of your own yet</p>
          <p class="text-sm a-muted mt-1">Connect Google reviews, or add a review a customer sent you.</p>
        </div>
        <ClientPagination :pager="pager" />
      </section>
    </div>

    <!-- ============ Imported Google reviews ============ -->
    <section v-if="imported.length" class="admin-card overflow-hidden mt-6">
      <header class="a-card-head">
        <div>
          <h3 class="a-card-title">Imported Google reviews <span class="a-subtle font-medium">{{ imported.length }}</span></h3>
          <p class="a-card-sub">Hide a review from the website if it is off-topic. It stays on Google; reply to reviews in your Google Business Profile.</p>
        </div>
        <div class="a-seg">
          <button type="button" :class="importedFilter === '' && 'is-on'" @click="importedFilter = ''">All</button>
          <button type="button" :class="importedFilter === 'shown' && 'is-on'" @click="importedFilter = 'shown'">Shown</button>
          <button type="button" :class="importedFilter === 'hidden' && 'is-on'" @click="importedFilter = 'hidden'">Hidden</button>
        </div>
      </header>
      <ul class="divide-y a-divide">
        <li v-for="r in importedPager.rows.value" :key="r.id" :class="['px-5 py-4 flex gap-4', r.is_hidden && 'opacity-60']">
          <img v-if="r.photo" :src="r.photo" alt="" class="w-9 h-9 rounded-full object-cover shrink-0" referrerpolicy="no-referrer" loading="lazy" />
          <span v-else class="w-9 h-9 rounded-full a-panel-3 grid place-items-center text-sm font-bold shrink-0">{{ (r.author || '?').charAt(0) }}</span>
          <div class="flex-1 min-w-0">
            <p class="text-sm font-semibold">{{ r.author }} <span class="text-[#f5a623] ml-1">{{ '★'.repeat(r.rating) }}<span class="opacity-25">{{ '★'.repeat(5 - r.rating) }}</span></span>
              <span class="a-subtle font-normal text-xs ml-1">{{ r.reviewed_at ? new Date(r.reviewed_at).toLocaleDateString('en-SG', { day: 'numeric', month: 'short', year: 'numeric' }) : '' }}</span></p>
            <p class="text-[13px] a-muted mt-1 line-clamp-3">{{ r.comment || '(rating only, no text: not shown on the website)' }}</p>
            <p v-if="r.reply" class="text-xs a-subtle mt-1.5 line-clamp-2"><b>Your reply:</b> {{ r.reply }}</p>
          </div>
          <button v-if="can('reviews.edit')" type="button" class="admin-btn-secondary !py-1.5 !text-xs self-start shrink-0" @click="toggleImported(r)">{{ r.is_hidden ? 'Show' : 'Hide' }}</button>
        </li>
      </ul>
      <ClientPagination :pager="importedPager" />
    </section>

    <!-- ============ Review editor ============ -->
    <Modal :show="modalOpen" :title="editing ? 'Edit review' : 'Add review'" width="2xl" @close="modalOpen = false">
      <form @submit.prevent="save" class="space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="admin-label">Customer name *</label>
            <input v-model="form.name" type="text" required class="admin-input" placeholder="e.g. Kelvin T." />
            <p class="a-help">First name and initial is fine for privacy.</p>
            <p v-if="form.errors.name" class="a-error">{{ form.errors.name }}</p>
          </div>
          <div>
            <label class="admin-label">Rating *</label>
            <div class="flex gap-1 h-[38px] items-center">
              <button v-for="i in 5" :key="i" type="button" @click="form.rating = i" :aria-label="`${i} stars`"
                :class="['text-2xl leading-none transition', i <= form.rating ? 'text-[#f5a623]' : 'a-subtle opacity-40 hover:opacity-70']">★</button>
            </div>
          </div>
          <div>
            <label class="admin-label">Job done</label>
            <input v-model="form.job" type="text" class="admin-input" placeholder="e.g. Water heater replacement" />
          </div>
          <div>
            <label class="admin-label">Area</label>
            <input v-model="form.location" type="text" class="admin-input" placeholder="e.g. Tampines HDB" />
          </div>
        </div>
        <div>
          <label class="admin-label">Review *</label>
          <textarea v-model="form.desc" rows="5" required maxlength="3000" class="admin-input" placeholder="The customer's own words."></textarea>
          <p v-if="form.errors.desc" class="a-error">{{ form.errors.desc }}</p>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="admin-label">Date of the review</label>
            <DatePicker v-model="form.review_date" :max="new Date().toISOString().slice(0, 10)" placeholder="Date of the review" class="admin-input" />
          </div>
          <div>
            <label class="admin-label">Photo (optional)</label>
            <input type="file" accept="image/*" @change="pickImage" />
              <LibraryButton class="mt-1.5" label="Choose photo from library" @pick="p => { form.img = p.file; }" />
          </div>
        </div>
        <label class="a-toggle-row">
          <span><span class="block text-sm font-semibold">Show on the website</span></span>
          <input v-model="form.is_active" type="checkbox" class="a-switch" />
        </label>
        <div class="flex justify-end gap-2 pt-3 border-t a-border">
          <button type="button" @click="modalOpen = false" class="admin-btn-secondary">Cancel</button>
          <button type="submit" :disabled="form.processing" class="admin-btn-primary">{{ editing ? 'Save review' : 'Add review' }}</button>
        </div>
      </form>
    </Modal>
    <BulkBar :bulk="bulk" :can-delete="can('reviews.delete')" />
  </AdminLayout>
</template>

<script setup>
import BulkSelectAll from '@/Components/Admin/BulkSelectAll.vue';
import LibraryButton from '@/Components/Admin/LibraryButton.vue';
import BulkBar from '@/Components/Admin/BulkBar.vue';
import { useBulk } from '@/Composables/useBulk';
import DatePicker from '@/Components/DatePicker.vue';
import SelectBox from '@/Components/SelectBox.vue';
import { computed, ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import ClientPagination from '@/Components/Admin/ClientPagination.vue';
import { usePaged } from '@/Composables/usePaged';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import Modal from '@/Components/Admin/Modal.vue';
import { compressImage } from '@/Composables/compressImage';
import { confirmDialog } from '@/Composables/useConfirm';
import { usePermissions } from '@/Composables/usePermissions';

const props = defineProps({ reviews: Array, google: Object, profile: Object, imported: { type: Array, default: () => [] } });
const importedFilter = ref('');
const importedList = computed(() => props.imported.filter((r) => importedFilter.value === '' || (importedFilter.value === 'hidden' ? r.is_hidden : !r.is_hidden)));
const importedPager = usePaged(importedList, 'google-reviews', 10);
const toggleImported = (r) => router.post(`/admin/reviews/imported/${r.id}/toggle`, {}, { preserveScroll: true });
const { can } = usePermissions();
const strip = html => (html || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();

const status = computed(() => {
  if (!props.google.enabled) return { label: 'Off', cls: '' };
  if (props.google.result?.ok) return { label: 'Connected', cls: 'a-badge-success' };
  return { label: 'Not working', cls: 'a-badge-danger' };
});

const gForm = useForm({
  enabled: props.google.enabled,
  place_id: props.google.place_id || '',
  api_key: '',
  min_rating: props.google.min_rating || '4',
  show_own: props.google.show_own,
});
function saveGoogle() {
  gForm.post('/admin/reviews/google', { preserveScroll: true, onSuccess: () => { gForm.api_key = ''; } });
}
function refresh() {
  router.post('/admin/reviews/google/refresh', {}, { preserveScroll: true });
}

const modalOpen = ref(false);
const editing = ref(null);
const blank = () => ({ name: '', desc: '', rating: 5, job: '', location: '', review_date: '', is_active: true, img: null });
const form = useForm(blank());

function openModal(r = null) {
  form.clearErrors();
  editing.value = r;
  const data = blank();
  if (r) Object.assign(data, {
    name: r.name, desc: strip(r.desc), rating: r.rating || 5, job: r.job || '', location: r.location || '',
    review_date: r.review_date ? r.review_date.slice(0, 10) : '', is_active: r.is_active !== false,
  });
  form.defaults(data);
  form.reset();
  modalOpen.value = true;
}
async function pickImage(e) {
  form.img = await compressImage(e.target.files[0], { maxSide: 400 });
}
function save() {
  const url = editing.value ? `/admin/reviews/${editing.value.id}` : '/admin/reviews';
  form.post(url, { forceFormData: true, preserveScroll: true, onSuccess: () => { modalOpen.value = false; } });
}
async function remove(r) {
  if (await confirmDialog({ title: `Delete the review from ${r.name}?`, message: 'It disappears from the website.', confirmText: 'Delete review' })) {
    router.delete(`/admin/reviews/${r.id}`, { preserveScroll: true });
  }
}

// Long lists are paged (10–500 rows, choice remembered).
const pager = usePaged(computed(() => props.reviews), 'reviews');

// Select rows for bulk delete (confirm popup; each item follows the normal delete rules).
const bulk = useBulk('reviews', () => pager.rows.value, { label: 'review' });
</script>
