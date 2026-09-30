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
            <div class="flex flex-wrap gap-2 pt-1">
              <a v-if="selected.phone" :href="'https://wa.me/' + cleanPhone(selected.phone)" target="_blank" rel="noopener" class="admin-btn-primary !bg-[#16a34a] hover:!bg-[#15803d]">Reply on WhatsApp</a>
              <a v-if="selected.email" :href="`mailto:${selected.email}?subject=${encodeURIComponent('Re: ' + (selected.subject || 'Your enquiry'))}`" class="admin-btn-secondary">Reply by email</a>
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
    <BulkBar :bulk="bulk" :can-delete="can('enquiries.delete')" />
  </AdminLayout>
</template>

<script setup>
import BulkSelectAll from '@/Components/Admin/BulkSelectAll.vue';
import BulkBar from '@/Components/Admin/BulkBar.vue';
import { useBulk } from '@/Composables/useBulk';
import { computed, ref, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import Pagination from '@/Components/Admin/Pagination.vue';
import { confirmDialog } from '@/Composables/useConfirm';
import { usePermissions } from '@/Composables/usePermissions';

const props = defineProps({ messages: Object, filters: Object, openMessage: Object });
const { can } = usePermissions();

const searchQuery = ref(props.filters?.search || '');
const selected = ref(null);
const onlyUnread = computed(() => props.filters?.unread === 'true');
const unreadCount = computed(() => (props.messages?.data || []).filter(m => m.is_read == 0).length);

let searchTimeout = null;
function filterMessages() {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    router.get('/admin/messages', { search: searchQuery.value || undefined, unread: props.filters?.unread }, { preserveState: true, replace: true });
  }, 300);
}
function setUnread(on) {
  router.get('/admin/messages', { search: searchQuery.value || undefined, unread: on ? 'true' : undefined }, { preserveState: true, replace: true });
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
