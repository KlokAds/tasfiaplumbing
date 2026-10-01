<template>
  <AdminLayout title="Articles">
    <PageHeader title="Articles" :description="permissions.publish
      ? 'Support pages (cost guides, how-tos, HDB/condo problems). Each links to one service. You approve what the team submits and choose when it goes live.'
      : 'Support pages (cost guides, how-tos, HDB/condo problems). Write, then submit for approval. You get an email when it is approved, scheduled or sent back.'">
      <button @click="openModal()" class="admin-btn-primary">+ Write article</button>
    </PageHeader>

    <!-- Feedback for the writer -->
    <div v-if="feedback.length" class="admin-card mb-6 overflow-hidden">
      <header class="px-5 py-3 border-b a-border flex items-center gap-2">
        <span class="w-2 h-2 rounded-full bg-amber-500"></span>
        <h3 class="text-sm font-bold">Sent back to you</h3>
      </header>
      <ul>
        <li v-for="f in feedback" :key="f.key" class="px-5 py-3 border-b last:border-0 a-border flex flex-col sm:flex-row sm:items-center gap-3 justify-between">
          <div class="min-w-0">
            <p class="text-sm font-semibold truncate">{{ f.title }} <span class="a-badge ml-1">{{ f.kind }}</span></p>
            <p class="text-xs a-muted mt-0.5 whitespace-pre-line">“{{ f.note }}”</p>
          </div>
          <button v-if="f.blog" @click="openModal(f.blog)" class="admin-btn-secondary a-btn-sm shrink-0">Fix and resubmit</button>
        </li>
      </ul>
    </div>

    <StickyBar>
    <!-- Tabs -->
    <nav class="a-tabs mb-5">
      <button v-for="t in tabs" :key="t.key" @click="go({ tab: t.key === 'all' ? '' : t.key, page: '' })"
        :class="['a-tab flex items-center gap-2 whitespace-nowrap', currentTab === t.key && 'a-tab-active']">
        {{ t.label }}
        <span v-if="counts[t.key] !== undefined" :class="['a-badge', t.key === 'review' && counts.review ? 'a-badge-warning' : '']">{{ counts[t.key] }}</span>
      </button>
    </nav>

    <!-- Review queue (publishers) -->
    <section v-if="permissions.publish && currentTab === 'review' && revisions.length" class="admin-card mb-6 overflow-hidden">
      <header class="px-5 py-3 border-b a-border">
        <h3 class="text-sm font-bold">Changes to live articles</h3>
        <p class="text-xs a-muted">The live page stays as it is until you approve.</p>
      </header>
      <ul>
        <li v-for="r in revisions" :key="r.id" class="px-5 py-3 border-b last:border-0 a-border flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <div class="min-w-0">
            <p class="text-sm font-semibold truncate">{{ r.article?.name }}</p>
            <p class="text-xs a-subtle">Edited by {{ r.user || 'unknown' }} · {{ ago(r.created_at) }}</p>
          </div>
          <div class="flex gap-2 shrink-0">
            <button @click="previewRevision = r" class="a-btn-ghost a-btn-sm">Preview</button>
            <button @click="openReject({ revision: r })" class="admin-btn-secondary a-btn-sm">Reject</button>
            <button @click="approveRevision(r)" class="admin-btn-primary a-btn-sm">Approve changes</button>
          </div>
        </li>
      </ul>
    </section>

    <div v-if="unlinkedCount && permissions.edit_all && currentTab !== 'review'" class="admin-card mb-4 px-5 py-3 flex flex-wrap items-center justify-between gap-3">
      <p class="text-sm"><span class="font-bold">{{ unlinkedCount }}</span> <span class="a-muted">article(s) are not linked to a service page. Every article should support one money page.</span></p>
      <button @click="go({ filter: 'no_service', page: '' })" class="admin-btn-secondary a-btn-sm">Show them</button>
    </div>
    </StickyBar>

    <div class="admin-card overflow-hidden">
      <!-- Filters -->
      <form @submit.prevent="go({ search: filterForm.search, page: '' })" class="flex flex-col lg:flex-row gap-2 p-4 border-b a-border">
        <input v-model="filterForm.search" type="search" placeholder="Search titles…" class="admin-input lg:max-w-xs" />
        <SelectBox v-model="filterForm.filter" @change="go({ filter: filterForm.filter, page: '' })" class="admin-input lg:max-w-[16rem]">
          <option value="">Any SEO state</option>
          <option v-for="(label, key) in filterOptions" :key="key" :value="key">{{ label }}</option>
        </SelectBox>
        <SelectBox v-model="filterForm.service" @change="go({ service: filterForm.service, page: '' })" class="admin-input lg:max-w-[16rem]">
          <option value="">Any service</option>
          <option v-for="s in services" :key="s.id" :value="s.id">{{ s.name }}</option>
        </SelectBox>
        <button type="submit" class="admin-btn-secondary">Search</button>
      </form>

      <!-- Bulk -->
      <div v-if="selected.length" class="flex flex-wrap items-center gap-3 px-4 py-2.5 border-b a-border" style="background: var(--a-accent-soft)">
        <span class="text-sm font-semibold">{{ selected.length }} selected</span>
        <SelectBox v-model="bulkService" class="admin-input !w-auto !py-1.5 text-sm">
          <option value="">Link to service…</option>
          <option v-for="s in services" :key="s.id" :value="s.id">{{ s.name }}</option>
        </SelectBox>
        <button v-if="permissions.edit_all" @click="bulkAssign" :disabled="!bulkService" class="admin-btn-primary a-btn-sm">Assign</button>
        <button v-if="permissions.delete" @click="bulkDelete" class="a-btn-danger a-btn-sm">Delete selected</button>
        <button @click="selected = []" class="a-btn-ghost a-btn-sm ml-auto">Clear</button>
      </div>

      <div class="overflow-x-auto">
        <table class="a-table">
          <thead>
            <tr>
              <th v-if="permissions.edit_all || permissions.delete" class="w-8"><input type="checkbox" :checked="allSelected" @change="toggleAll" class="rounded" /></th>
              <th>Article</th>
              <th>Status</th>
              <th>Author</th>
              <th>Service</th>
              <th class="text-center">Words</th>
              <th>SEO</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="b in blogs.data" :key="b.id">
              <td v-if="permissions.edit_all || permissions.delete"><input v-model="selected" type="checkbox" :value="b.id" class="rounded" /></td>
              <td class="max-w-md">
                <button @click="openModal(b)" class="font-semibold text-left line-clamp-1 hover:underline">{{ b.name }}</button>
                <p class="text-xs a-subtle font-mono truncate">{{ b.public_path }}<span v-if="b.noindex" class="ml-2 a-text-warning font-sans font-semibold">noindex</span></p>
              </td>
              <td class="whitespace-nowrap">
                <span :class="['a-badge', status(b).cls]">{{ status(b).label }}</span> <ChangeBadge :created="b.created_at" :updated="b.updated_at" class="ml-1" />
                <p v-if="b.status === 'scheduled' || (b.status === 'pending' && b.scheduled_at)" class="text-[11px] a-subtle mt-0.5">{{ b.status === 'pending' ? 'Wants ' : '' }}{{ when(b.scheduled_at) }}</p>
                <p v-else-if="b.status === 'pending'" class="text-[11px] a-subtle mt-0.5">Sent {{ ago(b.submitted_at) }}</p>
              </td>
              <td class="whitespace-nowrap a-muted">{{ b.author_name || '—' }}</td>
              <td>
                <span v-if="b.primary_service" class="a-muted">{{ b.primary_service.name }}</span>
                <div v-else class="text-xs">
                  <span class="a-text-danger font-semibold">Not linked</span>
                  <button v-if="b.suggested_service && permissions.edit_all" @click="assignOne(b, b.suggested_service.id)" class="block text-left a-muted underline decoration-dotted">Use: {{ b.suggested_service.name }}</button>
                </div>
              </td>
              <td class="text-center tabular-nums" :class="b.word_count < 600 ? 'a-text-danger font-semibold' : 'a-muted'">{{ b.word_count }}</td>
              <td><SeoScore :seo="b.seo" align-right /></td>
              <td class="text-right whitespace-nowrap">
                <template v-if="permissions.publish && b.status === 'pending'">
                  <button @click="openReject({ blog: b })" class="a-btn-ghost a-btn-sm">Send back</button>
                  <button @click="openApprove(b)" class="admin-btn-primary a-btn-sm">Approve</button>
                </template>
                <a v-if="b.status === 'published'" :href="b.public_path" target="_blank" class="a-btn-ghost a-btn-sm">View</a>
                <button @click="openModal(b)" class="a-btn-ghost a-btn-sm">Edit</button>
                <button v-if="b.can_delete" @click="remove(b)" class="a-btn-ghost a-btn-sm !a-text-danger">Delete</button>
              </td>
            </tr>
          </tbody>
        </table>
        <div v-if="!blogs.data.length" class="px-5 py-14 text-center">
          <p class="font-semibold">{{ emptyText }}</p>
          <button v-if="currentTab === 'all' || currentTab === 'mine' || currentTab === 'drafts'" @click="openModal()" class="admin-btn-secondary a-btn-sm mt-3">Write an article</button>
        </div>
      </div>
      <div class="px-4 pb-4"><Pagination :meta="blogs" /></div>
    </div>

    <!-- ============ Editor ============ -->
    <Modal :show="modalOpen" :title="modalTitle" subtitle="Answer the title question early, link the service page, end with FAQs (price question first)." width="5xl" @close="closeModal">
      <form @submit.prevent="save('primary')" class="space-y-5">
        <!-- Unsaved work was put back automatically -->
        <div v-if="autosave.restored.value" class="rounded-xl border px-4 py-3 flex flex-col sm:flex-row sm:items-center gap-3 justify-between" style="border-color: var(--a-accent); background: var(--a-accent-soft)">
          <p class="text-sm"><span class="font-semibold">Your unsaved changes are back</span> <span class="a-muted">(autosaved {{ new Date(autosave.restored.value.saved_at).toLocaleString('en-SG', { dateStyle: 'medium', timeStyle: 'short' }) }}). Save when you are ready.</span></p>
          <button type="button" @click="autosave.undoRestore()" class="admin-btn-secondary a-btn-sm shrink-0">Discard changes</button>
        </div>

        <!-- Context: why the buttons are what they are -->
        <div v-if="editing?.review_note && editing.status === 'draft'" class="rounded-xl border border-amber-500/40 bg-amber-500/10 px-4 py-3 text-sm">
          <p class="font-semibold">Sent back by the reviewer</p>
          <p class="mt-1 whitespace-pre-line">{{ editing.review_note }}</p>
          <p class="mt-1 text-xs a-muted">Make the changes, then submit for approval again.</p>
        </div>
        <div v-else-if="liveEditNeedsApproval" class="rounded-xl border a-border a-panel-2 px-4 py-3 text-sm">
          <p class="font-semibold">This article is live</p>
          <p class="a-muted">Your changes are sent for approval. The live page does not change until they are approved.</p>
        </div>
        <div v-else-if="editing?.status === 'pending' && !permissions.publish" class="rounded-xl border a-border a-panel-2 px-4 py-3 text-sm">
          <p class="font-semibold">Waiting for approval</p>
          <p class="a-muted">You can still edit it. “Save draft” takes it out of the review queue.</p>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-[1fr_20rem] gap-6">
          <!-- Main column -->
          <div class="space-y-5 min-w-0">
            <div>
              <label class="admin-label">Title (H1) *</label>
              <input v-model="form.name" type="text" required class="admin-input text-base" placeholder="e.g. How Much Does Water Heater Repair Cost in Singapore (2026)?" :aria-invalid="!!(form.errors.name || titleConflict)" />
              <p v-if="form.errors.name" class="a-error">{{ form.errors.name }}</p>
              <p v-else-if="titleConflict" class="a-error">{{ describe(titleConflict) }} <a :href="titleConflict.edit_url" target="_blank" class="underline">Open it</a></p>
            </div>

            <div>
              <label class="admin-label">Direct answer / excerpt <span class="font-normal a-subtle">(1–2 lines with the price range; AI engines quote this)</span></label>
              <textarea v-model="form.excerpt" rows="2" maxlength="500" class="admin-input text-sm"></textarea>
            </div>

            <div>
              <div class="flex justify-between items-baseline">
                <label class="admin-label">Article body *</label>
                <span :class="['text-[11px] font-mono', words < 600 ? 'a-text-danger' : 'a-text-success']">{{ words }} words{{ words < 600 ? ' · aim for 600+' : '' }}</span>
              </div>
              <RichEditor v-model="form.desc" min-height="420px" />
              <p v-if="form.errors.desc" class="a-error">{{ form.errors.desc }}</p>
              <p v-if="form.primary_service_id && !hasServiceLink" class="text-xs a-text-warning mt-1">
                No link to the service page yet. Link some text to {{ selectedServicePath }} ({{ selectedServiceName }}).
              </p>
            </div>

            <FaqRepeater v-model="form.faqs" hint="Put the price question first, e.g. “How much does … cost in Singapore?” with an S$ range." />

            <SeoPanel :form="form" path-prefix="/blogs/" :title-fallback="form.name" :desc-fallback="form.excerpt" :editing="!!editing" :original-slug="editing?.slug" :meta-warning="metaConflict ? describe(metaConflict) : ''" />
          </div>

          <!-- Side column (stays in view while writing) -->
          <aside class="space-y-4 a-side-sticky">
            <!-- Publishing -->
            <div class="rounded-xl border a-border p-4 space-y-3">
              <div class="flex items-center justify-between">
                <h4 class="text-sm font-bold">Publishing</h4>
                <span v-if="editing" :class="['a-badge', status(editing).cls]">{{ status(editing).label }}</span>
                <span v-else class="a-badge">New</span>
              </div>

              <template v-if="!liveEditNeedsApproval && editing?.status !== 'published'">
                <p class="text-xs a-muted">When should it go live?</p>
                <label class="flex items-start gap-2 text-sm cursor-pointer">
                  <input v-model="form.schedule_mode" type="radio" value="now" class="mt-0.5" />
                  <span>{{ permissions.publish ? 'Publish now' : 'As soon as it is approved' }}</span>
                </label>
                <label class="flex items-start gap-2 text-sm cursor-pointer">
                  <input v-model="form.schedule_mode" type="radio" value="schedule" class="mt-0.5" />
                  <span>On a date and time</span>
                </label>
                <div v-if="form.schedule_mode === 'schedule'">
                  <DatePicker v-model="form.scheduled_at" with-time :min="minLocal" placeholder="Pick date and time" class="admin-input text-sm" />
                  <p class="text-[11px] a-subtle mt-1">Your time zone: {{ tz }}</p>
                  <p v-if="form.errors.scheduled_at" class="a-error">{{ form.errors.scheduled_at }}</p>
                  <p v-if="!permissions.publish" class="text-[11px] a-muted mt-1">If it is approved before this time, it goes live automatically at this time.</p>
                </div>
              </template>
              <p v-else-if="editing?.published_at" class="text-xs a-muted">Live since {{ when(editing.published_at) }}</p>

              <dl class="pt-2 border-t a-border text-xs space-y-1">
                <div class="flex justify-between gap-2"><dt class="a-subtle">Author</dt><dd class="font-semibold truncate">{{ editing ? (editing.author_name || '—') : `${me.name} (you)` }}</dd></div>
                <div v-if="editing?.submitted_at && editing.status === 'pending'" class="flex justify-between gap-2"><dt class="a-subtle">Submitted</dt><dd>{{ ago(editing.submitted_at) }}</dd></div>
              </dl>
            </div>

            <ContentQualityPanel :payload="qualityPayload" :active="modalOpen" />

            <div>
              <label class="admin-label">Primary service *</label>
              <SelectBox v-model="form.primary_service_id" class="admin-input">
                <option :value="null">— Choose service —</option>
                <option v-for="s in services" :key="s.id" :value="s.id">{{ s.name }}</option>
              </SelectBox>
              <p class="text-[11px] a-subtle mt-1">The money page this article supports.</p>
            </div>

            <div>
              <label class="admin-label">Featured image</label>
              <label class="a-dropzone aspect-[16/9]">
                <img v-if="preview || editing?.image" :src="preview || '/' + editing.image" class="w-full h-full object-cover" alt="" />
                <span v-else class="text-xs px-3">Click to choose a photo</span>
                <input type="file" accept="image/jpeg,image/png,image/webp" @change="pickImage" class="hidden" />
              </label>
              <p v-if="imageUploading" class="mt-1.5 text-xs a-muted">Uploading the photo…</p>
              <LibraryButton class="mt-1.5" @pick="p => { form.image_path = p.path; imageCheck = null; }" />
              <p v-if="form.errors.image" class="a-error">{{ form.errors.image }}</p>
              <p v-else-if="imageCheck" :class="['mt-1.5 text-xs', imageCheck.ok ? 'a-text-success' : 'a-text-warning']">{{ imageCheck.text }}</p>
              <ul class="mt-2 text-[11.5px] a-subtle space-y-0.5 leading-relaxed">
                <li><span class="font-semibold a-muted">Size:</span> 1200 × 675 px or larger (16:9 landscape)</li>
                <li><span class="font-semibold a-muted">Best:</span> 1600 × 900 px, a real job photo, subject in the middle</li>
                <li><span class="font-semibold a-muted">Format:</span> JPG or WebP. Big files are shrunk and converted for you</li>
                <li><span class="font-semibold a-muted">Avoid:</span> text on the image, logos, stock photos</li>
              </ul>
            </div>
          </aside>
        </div>

        <!-- Footer -->
        <div class="sticky bottom-0 -mx-6 -mb-6 px-6 py-3 border-t a-border flex flex-col-reverse sm:flex-row sm:items-center gap-3 justify-between" style="background: var(--a-panel)">
          <p class="text-xs a-subtle flex items-center gap-1.5">
            <span :class="['w-1.5 h-1.5 rounded-full', saveDot]"></span>{{ saveText }}
          </p>
          <div class="flex flex-wrap justify-end gap-2">
            <button type="button" @click="closeModal" class="a-btn-ghost">Close</button>
            <button v-if="canDelete(editing)" type="button" @click="remove(editing)" class="a-btn-ghost !a-text-danger">Delete</button>
            <button v-for="a in actions" :key="a.intent + a.label" type="button" :disabled="form.processing || (a.intent !== 'draft' && a.intent !== 'unpublish' && a.intent !== 'reject' && hasConflict)" :title="hasConflict ? 'Fix the duplicate title first' : ''" @click="a.run ? a.run() : save(a.intent)"
              :class="a.primary ? 'admin-btn-primary' : 'admin-btn-secondary'">{{ form.processing && busy === a.intent ? 'Saving…' : a.label }}</button>
          </div>
        </div>
      </form>
    </Modal>

    <!-- ============ Approve ============ -->
    <Modal :show="!!approving" title="Approve article" :subtitle="approving?.name" width="lg" @close="approving = null">
      <form @submit.prevent="submitApprove" class="space-y-4">
        <label class="flex items-start gap-3 p-3 rounded-xl border a-border cursor-pointer">
          <input v-model="approveForm.mode" type="radio" value="now" class="mt-1" />
          <span><span class="block text-sm font-semibold">Publish now</span><span class="block text-xs a-muted">Goes live immediately and is added to the sitemap.</span></span>
        </label>
        <label class="flex items-start gap-3 p-3 rounded-xl border a-border cursor-pointer">
          <input v-model="approveForm.mode" type="radio" value="schedule" class="mt-1" />
          <span class="flex-1">
            <span class="block text-sm font-semibold">Schedule</span>
            <span class="block text-xs a-muted">{{ approving?.scheduled_at ? `The author asked for ${when(approving.scheduled_at)}. You can change it.` : 'Goes live automatically at this time.' }}</span>
            <DatePicker v-if="approveForm.mode === 'schedule'" v-model="approveForm.scheduled_at" with-time :min="minLocal" placeholder="Pick date and time" class="admin-input text-sm mt-2" />
          </span>
        </label>
        <p v-if="approveForm.errors.scheduled_at" class="text-xs a-text-danger">{{ approveForm.errors.scheduled_at }}</p>
        <p class="text-xs a-muted">The author gets an email and a notification.</p>
        <div class="flex justify-end gap-2 pt-3 border-t a-border">
          <button type="button" @click="approving = null" class="admin-btn-secondary">Cancel</button>
          <button type="submit" :disabled="approveForm.processing" class="admin-btn-primary">{{ approveForm.mode === 'schedule' ? 'Approve and schedule' : 'Approve and publish' }}</button>
        </div>
      </form>
    </Modal>

    <!-- ============ Send back / reject ============ -->
    <Modal :show="!!rejecting" :title="rejecting?.revision ? 'Reject changes' : 'Send back to author'" :subtitle="rejecting?.title" width="lg" @close="rejecting = null">
      <form @submit.prevent="submitReject" class="space-y-4">
        <div>
          <label class="admin-label">What needs to change? *</label>
          <textarea v-model="rejectForm.note" rows="5" required maxlength="1000" class="admin-input" placeholder="e.g. Add the 2026 price range in the first paragraph and link to the water heater service page."></textarea>
          <p v-if="rejectForm.errors.note" class="a-error">{{ rejectForm.errors.note }}</p>
          <p class="text-xs a-muted mt-1">The author gets this note by email and sees it when they open the article.</p>
        </div>
        <div class="flex justify-end gap-2 pt-3 border-t a-border">
          <button type="button" @click="rejecting = null" class="admin-btn-secondary">Cancel</button>
          <button type="submit" :disabled="rejectForm.processing" class="a-btn-danger">{{ rejecting?.revision ? 'Reject changes' : 'Send back' }}</button>
        </div>
      </form>
    </Modal>

    <!-- ============ Revision preview ============ -->
    <Modal :show="!!previewRevision" :title="previewRevision?.payload?.name || 'Changes'" :subtitle="`Proposed by ${previewRevision?.user || 'unknown'}`" width="4xl" @close="previewRevision = null">
      <div v-if="previewRevision" class="space-y-4">
        <p v-if="previewRevision.payload.excerpt" class="text-sm a-muted">{{ previewRevision.payload.excerpt }}</p>
        <div class="preview-html rounded-xl border a-border p-4 max-h-[60vh] overflow-y-auto a-scroll" v-html="previewRevision.payload.desc"></div>
        <div class="flex justify-end gap-2 pt-3 border-t a-border">
          <button @click="openReject({ revision: previewRevision }); previewRevision = null" class="admin-btn-secondary">Reject</button>
          <button @click="approveRevision(previewRevision)" class="admin-btn-primary">Approve changes</button>
        </div>
      </div>
    </Modal>
  </AdminLayout>
</template>

<script setup>
import ChangeBadge from '@/Components/Admin/ChangeBadge.vue';
import LibraryButton from '@/Components/Admin/LibraryButton.vue';
import StickyBar from '@/Components/Admin/StickyBar.vue';
import DatePicker from '@/Components/DatePicker.vue';
import SelectBox from '@/Components/SelectBox.vue';
import { computed, onMounted, reactive, ref, watch } from 'vue';
import axios from 'axios';
import { router, useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import Modal from '@/Components/Admin/Modal.vue';
import SeoPanel from '@/Components/Admin/SeoPanel.vue';
import SeoScore from '@/Components/Admin/SeoScore.vue';
import FaqRepeater from '@/Components/Admin/FaqRepeater.vue';
import Pagination from '@/Components/Admin/Pagination.vue';
import RichEditor from '@/Components/Admin/RichEditor.vue';
import ContentQualityPanel from '@/Components/Admin/ContentQualityPanel.vue';
import { compressImage } from '@/Composables/compressImage';
import { confirmDialog } from '@/Composables/useConfirm';
import { useAutosave } from '@/Composables/useAutosave';
import { useTitleCheck } from '@/Composables/useTitleCheck';
import { libraryFile, libraryUrl } from '@/utils/libraryFile';

const props = defineProps({
  blogs: Object,
  services: Array,
  filters: Object,
  filterOptions: Object,
  unlinkedCount: Number,
  counts: { type: Object, default: () => ({}) },
  revisions: { type: Array, default: () => [] },
  myRevisions: { type: Array, default: () => [] },
  editBlog: Object,
  permissions: { type: Object, default: () => ({}) },
});

const me = computed(() => usePage().props.auth?.user || {});
const tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
const currentTab = computed(() => {
  const t = props.filters?.tab || 'all';
  return t === 'mine' && !props.permissions.edit_all ? 'all' : t;
});

const tabs = computed(() => [
  { key: 'all', label: props.permissions.edit_all ? 'All' : 'My articles' },
  ...(props.permissions.edit_all ? [{ key: 'mine', label: 'Mine' }] : []),
  { key: 'drafts', label: 'Drafts' },
  { key: 'review', label: props.permissions.publish ? 'Needs approval' : 'Waiting approval' },
  { key: 'scheduled', label: 'Scheduled' },
  { key: 'published', label: 'Published' },
]);

const emptyText = computed(() => ({
  review: props.permissions.publish ? 'Nothing waiting for approval.' : 'Nothing waiting for approval.',
  scheduled: 'No articles scheduled.',
  drafts: 'No drafts.',
  published: 'No published articles match.',
}[currentTab.value] || 'No articles match.'));

// ---------- Helpers ----------
function status(b) {
  switch (b.status) {
    case 'published': return { label: 'Live', cls: 'a-badge-success' };
    case 'scheduled': return { label: 'Scheduled', cls: 'a-badge-info' };
    case 'pending': return { label: props.permissions.publish ? 'Needs approval' : 'Waiting approval', cls: 'a-badge-warning' };
    default: return b.review_note ? { label: 'Sent back', cls: 'a-badge-danger' } : { label: 'Draft', cls: '' };
  }
}
const when = iso => (iso ? new Date(iso).toLocaleString('en-SG', { weekday: 'short', day: 'numeric', month: 'short', hour: 'numeric', minute: '2-digit' }) : '');
function ago(iso) {
  if (!iso) return '';
  const d = (Date.now() - new Date(iso).getTime()) / 1000;
  if (d < 60) return 'just now';
  if (d < 3600) return Math.floor(d / 60) + ' min ago';
  if (d < 86400) return Math.floor(d / 3600) + ' h ago';
  return new Date(iso).toLocaleDateString('en-SG', { day: 'numeric', month: 'short' });
}
const pad = n => String(n).padStart(2, '0');
function toLocalInput(iso) {
  if (!iso) return '';
  const d = new Date(iso);
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}
const toIso = local => (local ? new Date(local).toISOString() : '');
const minLocal = toLocalInput(new Date(Date.now() + 5 * 60000).toISOString());
const canDelete = b => !!b?.can_delete;

// ---------- Feedback for the writer ----------
const feedback = computed(() => {
  const own = props.blogs.data
    .filter(b => b.status === 'draft' && b.review_note && b.author_id === me.value.id)
    .map(b => ({ key: 'b' + b.id, title: b.name, note: b.review_note, kind: 'Article', blog: b }));
  const revs = props.myRevisions
    .filter(r => r.status === 'rejected' && r.note)
    .map(r => ({ key: 'r' + r.id, title: r.article?.name, note: r.note, kind: 'Changes', blog: props.blogs.data.find(b => b.id === r.article_id) }));
  return [...own, ...revs].slice(0, 5);
});

// ---------- List / filters ----------
const filterForm = reactive({
  search: props.filters?.search || '',
  filter: props.filters?.filter || '',
  service: props.filters?.service || '',
});
function go(patch) {
  const params = { ...props.filters, ...patch };
  Object.keys(params).forEach(k => { if (params[k] === '' || params[k] == null) delete params[k]; });
  router.get('/admin/blogs', params, { preserveState: true, preserveScroll: true, replace: true });
}

const selected = ref([]);
const bulkService = ref('');
const allSelected = computed(() => props.blogs.data.length > 0 && props.blogs.data.every(b => selected.value.includes(b.id)));
function toggleAll() { selected.value = allSelected.value ? [] : props.blogs.data.map(b => b.id); }
async function bulkDelete() {
  const n = selected.value.length;
  const ok = await confirmDialog({ title: `Delete ${n} article${n === 1 ? '' : 's'}?`, message: 'Deleted articles return "410 gone" to Google. This cannot be undone.', confirmText: `Delete ${n}`, tone: 'danger' });
  if (ok) router.post('/admin/bulk/blogs/delete', { ids: selected.value }, { preserveScroll: true, onSuccess: () => { selected.value = []; } });
}
function bulkAssign() {
  router.post('/admin/blogs-bulk/assign-service', { ids: selected.value, service_id: bulkService.value }, {
    preserveScroll: true,
    onSuccess: () => { selected.value = []; bulkService.value = ''; },
  });
}
function assignOne(b, serviceId) {
  router.post('/admin/blogs-bulk/assign-service', { ids: [b.id], service_id: serviceId }, { preserveScroll: true });
}

// ---------- Editor ----------
const modalOpen = ref(false);
const editing = ref(null);
const preview = ref(null);
const busy = ref('');

// image_path: the featured image as a Media library path, so it survives in the autosaved draft.
const FIELDS = ['name', 'slug', 'primary_service_id', 'excerpt', 'desc', 'meta_title', 'meta_desc', 'focus_keyword', 'canonical', 'noindex', 'faqs', 'schedule_mode', 'scheduled_at', 'image_path'];
const blank = () => ({
  name: '', slug: '', primary_service_id: null, excerpt: '', desc: '',
  meta_title: '', meta_desc: '', focus_keyword: '', canonical: '', noindex: false,
  faqs: [], schedule_mode: 'now', scheduled_at: '', image: null, image_path: null,
});
const form = useForm(blank());
const autosave = useAutosave({ type: 'article', recordId: computed(() => editing.value?.id || 0), form, fields: FIELDS, active: modalOpen });

const { titleConflict, metaConflict, describe } = useTitleCheck(form, 'article', () => editing.value?.id);
const hasConflict = computed(() => !!(titleConflict.value || metaConflict.value));

const qualityPayload = computed(() => ({
  type: 'article',
  id: editing.value?.id || null,
  name: form.name, meta_title: form.meta_title, meta_desc: form.meta_desc, excerpt: form.excerpt,
  body: form.desc, focus_keyword: form.focus_keyword, slug: form.slug,
  image: preview.value || editing.value?.image || '',
  faqs: form.faqs, has_service: !!form.primary_service_id,
}));

const modalTitle = computed(() => (editing.value ? 'Edit article' : 'New article'));
const liveEditNeedsApproval = computed(() => editing.value?.status === 'published' && !props.permissions.publish);

const words = computed(() => (form.desc || '').replace(/<[^>]*>/g, ' ').split(/\s+/).filter(Boolean).length);
const selectedService = computed(() => props.services.find(s => s.id === form.primary_service_id));
const selectedServiceName = computed(() => selectedService.value?.name || '');
const selectedServicePath = computed(() => (selectedService.value ? `/service/${selectedService.value.slug}` : ''));
const hasServiceLink = computed(() => /\/services?\//.test(form.desc || ''));

function openModal(b = null) {
  form.clearErrors();
  preview.value = null;
  imageCheck.value = null;
  editing.value = b;
  const data = blank();
  if (b) {
    FIELDS.forEach(k => { if (b[k] !== undefined && b[k] !== null && !['faqs', 'scheduled_at'].includes(k)) data[k] = b[k]; });
    data.faqs = (b.faqs || []).map(f => ({ question: f.question, answer: f.answer }));
    if (b.scheduled_at && ['draft', 'pending', 'scheduled'].includes(b.status)) {
      data.schedule_mode = 'schedule';
      data.scheduled_at = toLocalInput(b.scheduled_at);
    }
  }
  form.defaults(data);
  form.reset();
  modalOpen.value = true;
  autosave.start(b);
}

function closeModal() {
  modalOpen.value = false;
  editing.value = null;
}

// Featured images show at 16:9 on the article page, in cards and when shared on
// WhatsApp/Facebook, so check the photo before it is uploaded.
const imageCheck = ref(null);
function checkImage(file) {
  return new Promise((resolve) => {
    const url = URL.createObjectURL(file);
    const im = new Image();
    im.onload = () => {
      const { naturalWidth: w, naturalHeight: h } = im;
      URL.revokeObjectURL(url);
      const ratio = w / h;
      if (w < 1200) resolve({ ok: false, text: `Only ${w} × ${h} px. Use at least 1200 px wide or it looks blurry on large screens.` });
      else if (ratio < 1.5 || ratio > 2.0) resolve({ ok: false, text: `${w} × ${h} px is not landscape 16:9, so the top/bottom or sides will be cropped. Crop it to 16:9 for the best result.` });
      else resolve({ ok: true, text: `${w} × ${h} px, good size and shape.` });
    };
    im.onerror = () => { URL.revokeObjectURL(url); resolve(null); };
    im.src = url;
  });
}

// A chosen library photo (or a restored draft) becomes the form's image.
watch(() => form.image_path, (path) => {
  if (!path) return;
  form.image = libraryFile(path);
  preview.value = libraryUrl(path);
});

// A photo from the computer goes to the Media library straight away, so the draft can keep it.
// If that is not allowed (no media permission) it is sent with the article as before.
const imageUploading = ref(false);
async function pickImage(e) {
  const original = e.target.files[0];
  e.target.value = '';
  if (!original) return;
  imageCheck.value = await checkImage(original);
  const file = await compressImage(original, { maxSide: 1920 });
  if (!file) return;
  preview.value = URL.createObjectURL(file);
  imageUploading.value = true;
  try {
    const body = new FormData();
    body.append('files[]', file);
    body.append('folder', 'Admin/Blog/Details');
    const { data } = await axios.post('/admin/media', body, { headers: { Accept: 'application/json' } });
    form.image_path = data.files[0].path;
  } catch (err) {
    form.image_path = null;
    form.image = file;
  } finally {
    imageUploading.value = false;
  }
}

/** Buttons depend on who you are and where the article is in the workflow. */
const actions = computed(() => {
  const s = editing.value?.status;
  const scheduling = form.schedule_mode === 'schedule' && form.scheduled_at;
  if (!props.permissions.publish) {
    if (s === 'published') return [{ intent: 'submit', label: 'Submit changes for approval', primary: true }];
    return [
      { intent: 'draft', label: 'Save draft' },
      { intent: 'submit', label: s === 'pending' ? 'Update submission' : 'Submit for approval', primary: true },
    ];
  }
  if (s === 'published') return [
    { intent: 'unpublish', label: 'Unpublish', confirm: true },
    { intent: 'publish', label: 'Update live article', primary: true },
  ];
  if (s === 'scheduled') return [
    { intent: 'unpublish', label: 'Unschedule' },
    { intent: 'publish', label: scheduling ? 'Save schedule' : 'Publish now', primary: true },
  ];
  const list = [{ intent: 'draft', label: 'Save draft' }];
  if (s === 'pending' && editing.value.author_id !== me.value.id) {
    list.push({ intent: 'reject', label: 'Send back', run: () => openReject({ blog: editing.value }) });
  }
  list.push({ intent: 'publish', label: scheduling ? 'Schedule' : (s === 'pending' ? 'Approve and publish' : 'Publish now'), primary: true });
  return list;
});

async function save(intent) {
  if (intent === 'primary') intent = actions.value.find(a => a.primary)?.intent || 'draft';
  if (intent === 'unpublish' && editing.value?.status === 'published') {
    const ok = await confirmDialog({
      title: 'Unpublish this article?',
      message: 'It disappears from the website and the sitemap. The URL starts returning 404 until you publish again. If it has traffic or backlinks, keep it live and edit it instead.',
      confirmText: 'Unpublish',
    });
    if (!ok) return;
  }
  busy.value = intent;
  const url = editing.value ? `/admin/blogs/${editing.value.id}` : '/admin/blogs';
  form
    .transform(d => {
      const { schedule_mode, ...rest } = d;
      return { ...rest, intent, scheduled_at: schedule_mode === 'schedule' ? toIso(d.scheduled_at) : '' };
    })
    .post(url, {
      forceFormData: true,
      preserveScroll: true,
      onSuccess: () => { autosave.clear(); closeModal(); },
      onFinish: () => { busy.value = ''; },
    });
}

const saveText = computed(() => ({
  pending: 'Unsaved changes…',
  saving: 'Autosaving…',
  saved: 'Draft autosaved',
  offline: 'Saved on this device (server not reachable, retrying)',
}[autosave.status.value] || 'Changes are autosaved while you type'));
const saveDot = computed(() => ({ pending: 'bg-amber-500', saving: 'bg-amber-500', saved: 'bg-emerald-500', offline: 'bg-red-500' }[autosave.status.value] || 'bg-slate-400'));

async function remove(b) {
  const live = b.status === 'published';
  const ok = await confirmDialog({
    title: `Delete “${b.name}”?`,
    message: live
      ? 'Its URL will return 410 (gone). If it has traffic or backlinks, add a redirect to a related page afterwards.'
      : 'This draft is deleted permanently.',
    confirmText: 'Delete article',
  });
  if (ok) router.delete(`/admin/blogs/${b.id}`, { preserveScroll: true, onSuccess: () => { if (editing.value?.id === b.id) closeModal(); } });
}

// ---------- Approve / reject ----------
const approving = ref(null);
const approveForm = useForm({ mode: 'now', scheduled_at: '' });
function openApprove(b) {
  approveForm.clearErrors();
  approving.value = b;
  const future = b.scheduled_at && new Date(b.scheduled_at) > new Date();
  approveForm.mode = future ? 'schedule' : 'now';
  approveForm.scheduled_at = future ? toLocalInput(b.scheduled_at) : '';
}
function submitApprove() {
  approveForm
    .transform(d => ({ mode: d.mode, scheduled_at: d.mode === 'schedule' ? toIso(d.scheduled_at) : '' }))
    .post(`/admin/blogs/${approving.value.id}/approve`, { preserveScroll: true, onSuccess: () => { approving.value = null; } });
}

const rejecting = ref(null);
const rejectForm = useForm({ note: '' });
function openReject({ blog = null, revision = null }) {
  rejectForm.reset();
  rejectForm.clearErrors();
  rejecting.value = { blog, revision, title: blog?.name || revision?.article?.name };
}
function submitReject() {
  const r = rejecting.value;
  const url = r.revision ? `/admin/revisions/${r.revision.id}/reject` : `/admin/blogs/${r.blog.id}/reject`;
  rejectForm.post(url, {
    preserveScroll: true,
    onSuccess: () => {
      rejecting.value = null;
      if (r.blog && editing.value?.id === r.blog.id) closeModal();
    },
  });
}

const previewRevision = ref(null);
async function approveRevision(r) {
  const ok = await confirmDialog({
    title: 'Make these changes live?',
    message: `The live article “${r.article?.name}” is replaced with the version from ${r.user || 'the editor'}.`,
    confirmText: 'Approve changes',
    tone: 'primary',
  });
  if (ok) router.post(`/admin/revisions/${r.id}/approve`, {}, { preserveScroll: true, onSuccess: () => { previewRevision.value = null; } });
}

onMounted(() => {
  if (props.editBlog) openModal(props.editBlog);
  else if (new URLSearchParams(window.location.search).get('new')) openModal();
});
</script>

<style scoped>
.preview-html { font-size: 0.9rem; line-height: 1.65; }
.preview-html :deep(h2) { font-size: 1.2rem; font-weight: 700; margin: 1.2em 0 0.4em; }
.preview-html :deep(h3) { font-size: 1.05rem; font-weight: 700; margin: 1em 0 0.3em; }
.preview-html :deep(p) { margin: 0.6em 0; }
.preview-html :deep(ul) { list-style: disc; padding-left: 1.4em; }
.preview-html :deep(ol) { list-style: decimal; padding-left: 1.4em; }
.preview-html :deep(a) { color: var(--a-accent-text); text-decoration: underline; }
.preview-html :deep(img) { max-width: 100%; height: auto; border-radius: 0.5rem; }
.preview-html :deep(table) { width: 100%; border-collapse: collapse; }
.preview-html :deep(td), .preview-html :deep(th) { border: 1px solid var(--a-border); padding: 0.35rem 0.5rem; }
</style>
