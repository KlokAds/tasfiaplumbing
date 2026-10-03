<template>
  <AdminLayout title="Enquiries">
    <PageHeader title="Enquiries" description="Messages sent from the website contact and quote forms. Reply fast: most customers contact two or three companies.">
      <span v-if="unreadCount" class="a-badge a-badge-accent">{{ unreadCount }} unread on this page</span>
    </PageHeader>

    <div class="flex flex-col sm:flex-row gap-2 mb-4">
      <input v-model="searchQuery" @input="filterMessages" type="search" placeholder="Search by name, phone or email…" class="admin-input sm:max-w-md" />
      <div class="a-seg sm:ml-auto self-start">
        <button type="button" @click="setUnread(false)" :class="!onlyUnread && 'is-on'">All</button>
        <button type="button" @click="setUnread(true)" :class="onlyUnread && 'is-on'">Unread</button>
      </div>
    </div>

    <!-- Follow-up status, and the Spam folder (spam is never deleted, only moved) -->
    <div class="flex flex-wrap items-center gap-1.5 mb-4" role="group" aria-label="Status">
      <button type="button" @click="setBox('', '')" :class="chip(!filters.box && !filters.status)">Inbox</button>
      <button v-for="(label, key) in statuses" :key="key" type="button" @click="setBox('', key)" :class="chip(!filters.box && filters.status === key)">{{ label }} <span class="tabular-nums opacity-70">{{ statusCounts[key] || 0 }}</span></button>
      <button type="button" @click="setBox('spam', '')" :class="chip(filters.box === 'spam')">Spam <span class="tabular-nums opacity-70">{{ spamCount }}</span></button>
      <span class="ml-auto flex flex-wrap gap-3">
        <button v-if="can('enquiries.edit') && filters.box !== 'spam'" type="button" @click="scanSpam" class="text-[13px] font-semibold a-accent">Check open enquiries for spam</button>
        <button v-if="can('enquiries.edit')" type="button" @click="openTemplates" class="text-[13px] font-semibold a-accent">Ready replies</button>
      </span>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,26rem)_1fr] gap-4 items-start">
      <!-- List -->
      <section class="admin-card overflow-hidden">
        <BulkSelectAll v-if="can('enquiries.delete') && messages.data?.length" :bulk="bulk" />
        <ul v-if="messages.data?.length" class="a-divide max-h-[70vh] overflow-y-auto a-scroll">
          <li v-for="msg in messages.data" :key="msg.id" :class="['flex items-stretch', bulk.has(msg.id) && 'a-row-selected']">
            <label v-if="can('enquiries.delete')" class="pl-3 flex items-start pt-4 cursor-pointer" title="Select"><input type="checkbox" :checked="bulk.has(msg.id)" @change="bulk.toggle(msg.id)" aria-label="Select" /></label>
            <button @click="openMessage(msg)" :class="['w-full text-left px-4 py-3.5 flex gap-3 transition', selected?.id === msg.id ? 'a-tint-accent' : 'a-hover']">
              <span :class="['mt-1.5 a-dot', msg.is_read == 0 ? 'text-[var(--a-accent)]' : 'opacity-0']"></span>
              <span class="min-w-0 flex-1">
                <span class="flex items-baseline justify-between gap-2">
                  <span :class="['truncate', msg.is_read == 0 ? 'font-bold' : 'font-medium']">{{ msg.name || 'Unknown' }}</span>
                  <span class="text-[11px] a-subtle shrink-0">{{ shortDate(msg.created_at) }}</span>
                </span>
                <span class="block text-[13px] truncate" :class="msg.is_read == 0 ? 'a-text' : 'a-muted'">{{ msg.subject || 'Website enquiry' }}</span>
                <span class="block text-xs a-subtle truncate">{{ msg.message }}</span>
                <span v-if="msg.is_spam || (msg.status && msg.status !== 'new')" class="mt-1 inline-flex"><span :class="['a-badge', statusBadge(msg)]">{{ msg.is_spam ? 'Spam' : statuses[msg.status] }}</span></span>
              </span>
            </button>
          </li>
        </ul>
        <div v-else class="a-empty">
          <p class="font-semibold">No enquiries</p>
          <p class="text-sm a-muted mt-1">{{ onlyUnread ? 'Everything is read.' : 'Nothing matches this search.' }}</p>
        </div>
        <div class="px-4 pb-3"><Pagination :meta="messages" /></div>
      </section>

      <!-- Detail -->
      <section class="admin-card lg:sticky lg:top-20" :class="!selected && 'hidden lg:block'">
        <template v-if="selected">
          <header class="a-card-head items-start">
            <div class="min-w-0">
              <p class="text-xs a-subtle">{{ longDate(selected.created_at) }}</p>
              <h3 class="text-lg font-bold mt-0.5 truncate">{{ selected.name }}</h3>
              <p class="text-sm a-muted">{{ selected.subject || 'Website enquiry' }}</p>
            </div>
            <button @click="selected = null" class="a-btn-ghost a-btn-icon lg:hidden" aria-label="Close">✕</button>
          </header>
          <div class="p-5 space-y-5">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
              <a v-if="selected.phone" :href="'tel:' + selected.phone" class="a-choice items-center !gap-3">
                <span class="w-8 h-8 rounded-lg a-panel-3 flex items-center justify-center">📞</span>
                <span class="min-w-0"><span class="block text-xs a-subtle">Phone</span><span class="block font-semibold truncate">{{ selected.phone }}</span></span>
              </a>
              <a v-if="selected.email" :href="'mailto:' + selected.email" class="a-choice items-center !gap-3">
                <span class="w-8 h-8 rounded-lg a-panel-3 flex items-center justify-center">✉️</span>
                <span class="min-w-0"><span class="block text-xs a-subtle">Email</span><span class="block font-semibold truncate">{{ selected.email }}</span></span>
              </a>
            </div>
            <div>
              <p class="a-section-title mb-2">Message</p>
              <div class="rounded-xl a-panel-2 border a-border p-4 text-sm leading-relaxed whitespace-pre-wrap">{{ selected.message }}</div>
            </div>
            <!-- Spam: why, and the way back -->
            <p v-if="selected.is_spam" class="a-alert a-alert-warning text-sm">In Spam{{ selected.spam_reason ? ': ' + selected.spam_reason : '.' }} Not right? Press “Not spam”.</p>
            <p v-if="selected.page" class="text-[13px] a-muted">Sent from <a :href="selected.page" target="_blank" rel="noopener" class="underline">{{ selected.page }}</a></p>
            <!-- Follow-up status -->
            <div v-if="!selected.is_spam" class="flex flex-wrap items-center gap-1.5">
              <span class="text-sm font-semibold mr-1">Status</span>
              <button v-for="(label, key) in statuses" :key="key" type="button" :disabled="!can('enquiries.edit')" @click="setStatus(selected, key)"
                :class="['px-3 py-1.5 rounded-lg text-[13px] font-semibold border transition', (selected.status || 'new') === key ? 'bg-[var(--a-accent)] text-white border-transparent' : 'a-border a-muted hover:bg-[var(--a-panel-3)]']">{{ label }}</button>
            </div>
            <!-- Reply with a ready text (editable) -->
            <div v-if="!selected.is_spam && (selected.phone || selected.email)" class="space-y-2">
              <div class="flex flex-wrap items-center gap-2">
                <span class="text-sm font-semibold">Reply</span>
                <SelectBox v-model="templateIndex" class="admin-input a-input-sm !w-auto" aria-label="Ready reply">
                  <option :value="-1">Write my own</option>
                  <option v-for="(t, i) in templates" :key="i" :value="i">{{ t.name }}</option>
                </SelectBox>
              </div>
              <textarea v-model="replyText" rows="3" class="admin-input text-sm" placeholder="Your reply…" aria-label="Reply text"></textarea>
            </div>
            <div class="flex flex-wrap gap-2 pt-1">
              <a v-if="selected.phone && !selected.is_spam" :href="whatsappLink(selected)" target="_blank" rel="noopener" @click="replied(selected)" class="admin-btn-primary !bg-[#16a34a] hover:!bg-[#15803d]">Reply on WhatsApp</a>
              <a v-if="selected.email && !selected.is_spam" :href="emailLink(selected)" @click="replied(selected)" class="admin-btn-secondary">Reply by email</a>
              <button v-if="can('enquiries.edit')" type="button" @click="setSpam(selected, !selected.is_spam)" class="a-btn-ghost">{{ selected.is_spam ? 'Not spam' : 'Spam' }}</button>
              <button v-if="can('enquiries.delete')" @click="deleteMessage(selected.id)" class="a-btn-ghost a-danger ml-auto">Delete</button>
            </div>
          </div>
        </template>
        <div v-else class="a-empty">
          <div class="a-empty-icon">✉</div>
          <p class="font-semibold">Select an enquiry</p>
          <p class="text-sm a-muted mt-1">Opening a message marks it as read.</p>
        </div>
      </section>
    </div>
    <!-- Ready replies: the texts offered on each enquiry -->
    <Modal :show="templatesOpen" title="Ready replies" subtitle="Offered on each enquiry for WhatsApp and email. {name}, {subject} and {brand} are filled in." width="2xl" @close="templatesOpen = false">
      <div class="space-y-3">
        <div v-for="(t, i) in editTemplates" :key="i" class="rounded-xl border a-border p-3 space-y-2">
          <div class="flex gap-2">
            <input v-model="t.name" maxlength="60" class="admin-input text-sm" placeholder="Name, e.g. Ask for photos" aria-label="Name" />
            <button type="button" @click="editTemplates.splice(i, 1)" class="a-btn-ghost a-danger a-btn-sm">Remove</button>
          </div>
          <textarea v-model="t.text" rows="3" maxlength="1000" class="admin-input text-sm" aria-label="Text"></textarea>
        </div>
        <button type="button" @click="editTemplates.push({ name: '', text: '' })" :disabled="editTemplates.length >= 12" class="admin-btn-secondary a-btn-sm">+ Add a reply</button>
        <div class="flex justify-end gap-2 pt-2">
          <button type="button" @click="templatesOpen = false" class="a-btn-ghost">Cancel</button>
          <button type="button" @click="saveTemplates" :disabled="editTemplates.some((t) => !t.name.trim() || !t.text.trim())" class="admin-btn-primary">Save</button>
        </div>
      </div>
    </Modal>
    <BulkBar :bulk="bulk" :can-delete="can('enquiries.delete')" />
  </AdminLayout>
</template>

<script setup>
import BulkSelectAll from '@/Components/Admin/BulkSelectAll.vue';
import BulkBar from '@/Components/Admin/BulkBar.vue';
import { useBulk } from '@/Composables/useBulk';
import { computed, ref, onMounted, watch } from 'vue';
import SelectBox from '@/Components/SelectBox.vue';
import Modal from '@/Components/Admin/Modal.vue';
import { router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import Pagination from '@/Components/Admin/Pagination.vue';
import { confirmDialog } from '@/Composables/useConfirm';
import { usePermissions } from '@/Composables/usePermissions';

const props = defineProps({
  messages: Object, filters: Object, openMessage: Object,
  statuses: { type: Object, default: () => ({}) },
  statusCounts: { type: Object, default: () => ({}) },
  spamCount: { type: Number, default: 0 },
  templates: { type: Array, default: () => [] },
  brand: { type: String, default: '' },
});
const { can } = usePermissions();

const searchQuery = ref(props.filters?.search || '');
const selected = ref(null);
const onlyUnread = computed(() => props.filters?.unread === 'true');
const unreadCount = computed(() => (props.messages?.data || []).filter(m => m.is_read == 0).length);

// ---- Inbox / status / Spam
function setBox(box, status) {
  router.get('/admin/messages', { search: searchQuery.value || undefined, box: box || undefined, status: status || undefined }, { preserveState: true, replace: true });
}
const chip = (on) => ['px-3 py-1.5 rounded-lg text-[13.5px] font-semibold transition whitespace-nowrap', on ? 'bg-[var(--a-accent)] text-white shadow-sm' : 'a-muted hover:bg-[var(--a-panel-3)]'];
const statusBadge = (m) => (m.is_spam ? 'a-badge-danger' : { contacted: 'a-badge-info', quoted: 'a-badge-accent', won: 'a-badge-success', lost: '' }[m.status] || '');
async function scanSpam() {
  const ok = await confirmDialog({ title: 'Check open enquiries for spam?', message: 'Enquiries of the last 6 months that are still “New” are checked. Spam is moved to the Spam folder, nothing is deleted, and “Not spam” brings one back.', confirmText: 'Check now' });
  if (ok) router.post('/admin/messages-scan-spam', {}, { preserveScroll: true });
}
function setStatus(m, status) {
  router.post(`/admin/messages/${m.id}/status`, { status }, { preserveScroll: true, preserveState: true, onSuccess: () => { m.status = status; m.is_read = 1; } });
}
function setSpam(m, spam) {
  router.post(`/admin/messages/${m.id}/spam`, { spam }, { preserveScroll: true, onSuccess: () => { selected.value = null; } });
}
// Replying from here marks a new enquiry "Contacted" (so the no-reply reminder stops).
function replied(m) {
  if ((m.status || 'new') === 'new' && can('enquiries.edit')) setStatus(m, 'contacted');
}

// ---- Ready replies
const templateIndex = ref(props.templates.length ? 0 : -1);
const replyText = ref('');
const fill = (text, m) => text
  .replaceAll('{name}', (m.name || '').trim().split(/\s+/)[0] || 'there')
  .replaceAll('{subject}', (m.subject && !/^website enquiry$/i.test(m.subject) ? m.subject : 'your enquiry').toLowerCase())
  .replaceAll('{brand}', props.brand);
watch([templateIndex, selected], () => {
  const t = props.templates[templateIndex.value];
  replyText.value = t && selected.value ? fill(t.text, selected.value) : '';
});
const waNumber = (phone) => { const d = (phone || '').replace(/\D/g, ''); return /^[3689]\d{7}$/.test(d) ? '65' + d : d; };
const whatsappLink = (m) => `https://wa.me/${waNumber(m.phone)}` + (replyText.value.trim() ? `?text=${encodeURIComponent(replyText.value.trim())}` : '');
const emailLink = (m) => `mailto:${m.email}?subject=${encodeURIComponent('Re: ' + (m.subject || 'Your enquiry'))}` + (replyText.value.trim() ? `&body=${encodeURIComponent(replyText.value.trim())}` : '');

const templatesOpen = ref(false);
const editTemplates = ref([]);
function openTemplates() {
  editTemplates.value = props.templates.map((t) => ({ ...t }));
  templatesOpen.value = true;
}
function saveTemplates() {
  router.post('/admin/messages-templates', { templates: editTemplates.value }, { preserveScroll: true, onSuccess: () => { templatesOpen.value = false; } });
}

// After a reload, keep the open enquiry in step with the fresh list.
watch(() => props.messages.data, (list) => {
  if (selected.value) selected.value = list.find((m) => m.id === selected.value.id) || selected.value;
});

let searchTimeout = null;
function filterMessages() {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    router.get('/admin/messages', { search: searchQuery.value || undefined, unread: props.filters?.unread, box: props.filters?.box, status: props.filters?.status }, { preserveState: true, replace: true });
  }, 300);
}
function setUnread(on) {
  router.get('/admin/messages', { search: searchQuery.value || undefined, unread: on ? 'true' : undefined, box: props.filters?.box, status: props.filters?.status }, { preserveState: true, replace: true });
}

onMounted(() => { if (props.openMessage) openMessage(props.messages.data.find((m) => m.id === props.openMessage.id) || props.openMessage); });
function openMessage(msg) {
  selected.value = msg;
  if (msg.is_read == 0 && can('enquiries.edit')) {
    router.post(`/admin/messages/${msg.id}/read`, {}, { preserveScroll: true, preserveState: true, onSuccess: () => { msg.is_read = 1; } });
  }
}

async function deleteMessage(id) {
  if (await confirmDialog({ title: 'Delete this enquiry?', message: 'The message and the contact details are removed permanently.', confirmText: 'Delete enquiry' })) {
    router.delete(`/admin/messages/${id}`, { preserveScroll: true, onSuccess: () => { if (selected.value?.id === id) selected.value = null; } });
  }
}

const cleanPhone = phone => (phone || '').replace(/[^0-9]/g, '');
const shortDate = d => {
  const date = new Date(d);
  return date.toDateString() === new Date().toDateString()
    ? date.toLocaleTimeString('en-SG', { hour: 'numeric', minute: '2-digit' })
    : date.toLocaleDateString('en-SG', { day: 'numeric', month: 'short' });
};
const longDate = d => new Date(d).toLocaleString('en-SG', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric', hour: 'numeric', minute: '2-digit' });

const bulk = useBulk('messages', () => props.messages.data, { label: 'enquiry', plural: 'enquiries' });
</script>
