<template>
  <AdminLayout title="Google connections">
    <PageHeader title="Google connections" description="Connect your Google account once. The website then imports all your Google reviews, and this admin shows Search Console, Analytics and which pages Google has indexed." />

    <div class="grid grid-cols-1 xl:grid-cols-[1fr_22rem] gap-5 items-start">
      <div class="space-y-5">
        <!-- Status -->
        <section class="admin-card p-5 flex flex-wrap items-center gap-4">
          <span :class="['w-11 h-11 rounded-xl grid place-items-center shrink-0', connected ? 'a-tint-success a-text-success' : 'a-panel-3 a-muted']">
            <svg class="w-6 h-6" viewBox="0 0 48 48" aria-hidden="true"><path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3C33.7 32.7 29.2 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.3-.1-2.4-.4-3.5z"/><path fill="#FF3D00" d="M6.3 14.7l6.6 4.8C14.7 15.1 19 12 24 12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 16.3 4 9.7 8.3 6.3 14.7z"/><path fill="#4CAF50" d="M24 44c5.2 0 9.9-2 13.4-5.2l-6.2-5.2C29.2 35.1 26.7 36 24 36c-5.2 0-9.6-3.3-11.3-7.9l-6.5 5C9.5 39.6 16.2 44 24 44z"/><path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-.8 2.2-2.2 4.2-4.1 5.6l6.2 5.2C37 38.2 44 33 44 24c0-1.3-.1-2.4-.4-3.5z"/></svg>
          </span>
          <div class="flex-1 min-w-0">
            <p class="text-sm font-bold">{{ connected ? 'Connected' : 'Not connected' }}<span v-if="email" class="font-normal a-muted"> · {{ email }}</span></p>
            <p class="text-xs a-muted mt-0.5">{{ connected ? 'Reports, index checks and reviews update on their own — see Automatic updates below.' : 'Finish the 4 setup steps on the right, then connect.' }}</p>
          </div>
          <button v-if="!connected" type="button" class="admin-btn-primary" :disabled="!client.id || !client.has_secret" @click="connect">Connect Google</button>
          <button v-else type="button" class="admin-btn-secondary" @click="disconnect">Disconnect</button>
        </section>
        <p v-if="lastError" class="a-alert a-alert-danger text-xs">Last problem: {{ lastError }}</p>

        <!-- Client -->
        <form @submit.prevent="saveClient" class="admin-card overflow-hidden">
          <header class="a-card-head"><div><h3 class="a-card-title">OAuth client</h3><p class="a-card-sub">From Google Cloud → APIs & Services → Credentials (step 3 on the right).</p></div></header>
          <div class="p-5 grid md:grid-cols-2 gap-4">
            <div>
              <label class="admin-label">Client ID</label>
              <input v-model="clientForm.client_id" type="text" class="admin-input a-mono text-xs" placeholder="1234-abc.apps.googleusercontent.com" />
              <p v-if="clientForm.errors.client_id" class="a-error">{{ clientForm.errors.client_id }}</p>
            </div>
            <div>
              <label class="admin-label">Client secret</label>
              <input v-model="clientForm.client_secret" type="password" autocomplete="new-password" class="admin-input a-mono text-xs" :placeholder="client.has_secret ? '•••••••• saved (leave blank to keep)' : 'GOCSPX-…'" />
            </div>
            <div class="md:col-span-2 flex justify-end"><button type="submit" class="admin-btn-secondary" :disabled="clientForm.processing">Save</button></div>
          </div>
        </form>

        <!-- What to use -->
        <form v-if="connected" @submit.prevent="saveProps" class="admin-card overflow-hidden">
          <header class="a-card-head flex items-start justify-between gap-3">
            <div><h3 class="a-card-title">What to use</h3><p class="a-card-sub">Only what your Google account can see is listed. Added a site or profile in Google just now? Refresh the lists.</p></div>
            <button type="button" class="admin-btn-secondary a-btn-sm shrink-0" :disabled="refreshing" @click="refreshLists">{{ refreshing ? 'Refreshing…' : 'Refresh lists' }}</button>
          </header>
          <p v-if="refreshNote" role="status" class="a-alert a-alert-success text-sm mx-5 mt-4">{{ refreshNote }}</p>
          <div class="p-5 space-y-5">
            <div>
              <label class="admin-label">Business Profile (reviews)</label>
              <SelectBox v-model="propsForm.gbp" class="admin-input">
                <option value="">— Do not import reviews —</option>
                <option v-for="l in lists.locations" :key="l.name" :value="l.name">{{ l.title }}{{ l.address ? ' · ' + l.address : '' }}</option>
              </SelectBox>
              <p v-if="listErrors.locations" class="a-error">{{ explain(listErrors.locations, 'gbp') }}</p>
              <p v-else-if="!lists.locations.length" class="a-help">No business found for this Google account. Use the account that manages your Google Business Profile.</p>
              <p v-else class="a-help">All reviews are imported (the Places API only gives 5). Replies you write on Google show under each review.</p>
            </div>
            <div>
              <label class="admin-label">Search Console property</label>
              <SelectBox v-model="propsForm.gsc" class="admin-input">
                <option value="">— None —</option>
                <option v-for="s in lists.sites" :key="s.url" :value="s.url">{{ s.url.replace('sc-domain:', 'Domain: ') }}</option>
              </SelectBox>
              <p v-if="listErrors.sites" class="a-error">{{ explain(listErrors.sites, 'gsc') }}</p>
              <p v-else-if="!lists.sites.length" class="a-help">No property yet. Add {{ origin }} in <a href="https://search.google.com/search-console" target="_blank" rel="noopener" class="a-accent">Search Console</a> and verify it (the site already supports the HTML-tag method under SEO → Schema & robots).</p>
            </div>
            <div>
              <label class="admin-label">Analytics 4 property</label>
              <SelectBox v-model="propsForm.ga4" class="admin-input">
                <option value="">— None —</option>
                <option v-for="p in lists.properties" :key="p.id" :value="p.id">{{ p.name }} · {{ p.account }} ({{ p.id.replace('properties/', '') }})</option>
              </SelectBox>
              <p v-if="listErrors.properties" class="a-error">{{ explain(listErrors.properties, 'ga4') }}</p>
              <p v-else class="a-help">The property that receives data from your Tag Manager.</p>
            </div>
            <div class="flex justify-end"><button type="submit" class="admin-btn-primary" :disabled="propsForm.processing">{{ propsForm.processing ? 'Saving…' : 'Save' }}</button></div>
          </div>
        </form>

        <!-- Automatic updates (server cron) -->
        <form v-if="connected && automation" @submit.prevent="saveSync" class="admin-card overflow-hidden">
          <header class="a-card-head"><div><h3 class="a-card-title">Automatic updates</h3><p class="a-card-sub">The server does these on its own, in the background. Choose how often.</p></div></header>
          <p v-if="!automation.cron_ok" class="a-alert a-alert-warning text-sm mx-5 mt-5">The server cron has not run in the last 10 minutes{{ automation.cron_seen ? ` (last seen ${ago(automation.cron_seen)})` : '' }}, so nothing updates automatically. Check the cron job in your hosting panel.</p>
          <div class="a-divide">
            <div v-for="t in automation.tasks" :key="t.key" class="p-5 grid grid-cols-1 md:grid-cols-[1fr_11rem] gap-3 items-start">
              <div class="min-w-0">
                <p class="text-sm font-bold">{{ t.label }}</p>
                <p class="text-xs a-muted mt-0.5">{{ t.help }}</p>
                <p v-if="!t.available" class="text-xs a-subtle mt-2">Choose the matching Google property above to use this.</p>
                <p v-else class="text-xs mt-2 flex flex-wrap gap-x-3 gap-y-1">
                  <span class="a-muted">Last run: <span :class="t.ok ? 'a-text' : 'a-text-danger'">{{ t.last ? `${ago(t.last)} · ${t.result}` : 'not yet' }}</span></span>
                  <span class="a-muted">Next: <span class="a-text">{{ t.every ? (t.next ? whenNext(t.next) : '—') : 'off' }}</span></span>
                </p>
              </div>
              <div class="flex md:flex-col gap-2">
                <SelectBox v-model="syncForm[t.key]" class="admin-input flex-1">
                  <option v-for="o in t.options" :key="o" :value="o">{{ everyLabel(o) }}</option>
                </SelectBox>
                <button v-if="t.available" type="button" class="admin-btn-secondary a-btn-sm whitespace-nowrap" :disabled="running === t.key" @click="runNow(t.key)">{{ running === t.key ? 'Running…' : 'Run now' }}</button>
              </div>
            </div>
            <div class="p-5 grid grid-cols-1 md:grid-cols-[1fr_11rem] gap-3 items-center">
              <div><p class="text-sm font-bold">Pages per index check</p><p class="text-xs a-muted mt-0.5">Google allows about 2,000 checks a day. "Run now" stops after about 20 seconds.</p></div>
              <SelectBox v-model="syncForm.index_batch" class="admin-input">
                <option v-for="o in automation.index_batch_options" :key="o" :value="o">{{ o }} pages</option>
              </SelectBox>
            </div>
          </div>
          <div class="px-5 py-3 border-t a-border flex justify-end"><button type="submit" class="admin-btn-primary" :disabled="syncForm.processing">Save automatic updates</button></div>
        </form>

        <section v-if="connected && selected.gbp" class="admin-card p-5 flex flex-wrap items-center gap-4">
          <div class="flex-1 min-w-0">
            <p class="text-sm font-bold">Google reviews</p>
            <p class="text-xs a-muted mt-0.5">
              {{ reviews.count }} imported<span v-if="reviews.rating"> · {{ reviews.rating }}★ from {{ reviews.total }} reviews</span><span v-if="reviews.synced_at"> · last sync {{ ago(reviews.synced_at) }}</span>
            </p>
          </div>
          <Link href="/admin/reviews" class="admin-btn-secondary">Manage reviews</Link>
          <button type="button" class="admin-btn-primary" @click="sync">Sync now</button>
        </section>
      </div>

      <!-- Setup guide -->
      <aside class="admin-card overflow-hidden xl:sticky xl:top-20">
        <header class="a-card-head"><h3 class="a-card-title">One-time setup (about 15 min)</h3></header>
        <ol class="p-5 space-y-5 text-[13px] leading-relaxed">
          <li class="flex gap-3">
            <span class="a-step">1</span>
            <div>
              <p class="font-semibold">Create a Google Cloud project</p>
              <p class="a-muted">Free. Open <a href="https://console.cloud.google.com/projectcreate" target="_blank" rel="noopener" class="a-accent">console.cloud.google.com</a> with the Google account that owns your Business Profile, Search Console and Analytics.</p>
            </div>
          </li>
          <li class="flex gap-3">
            <span class="a-step">2</span>
            <div>
              <p class="font-semibold">Switch on these APIs</p>
              <ul class="a-muted list-disc pl-4 mt-1 space-y-0.5">
                <li v-for="api in apis" :key="api.id"><a :href="`https://console.cloud.google.com/apis/library/${api.id}`" target="_blank" rel="noopener" class="a-accent">{{ api.name }}</a></li>
              </ul>
              <p class="a-muted mt-1.5">Reviews also need Business Profile API access: <a href="https://developers.google.com/my-business/content/prereqs#request-access" target="_blank" rel="noopener" class="a-accent">request it here</a> (Google approves in a few days). Search Console and Analytics work straight away.</p>
            </div>
          </li>
          <li class="flex gap-3">
            <span class="a-step">3</span>
            <div>
              <p class="font-semibold">Create the OAuth client</p>
              <p class="a-muted"><a href="https://console.cloud.google.com/auth/overview" target="_blank" rel="noopener" class="a-accent">Google Auth Platform</a> → set app name and your email, audience <b>External</b>, then <b>Publish app</b> (in "Testing" the login stops working after 7 days).</p>
              <p class="a-muted mt-1">Clients → Create client → <b>Web application</b>. Add this <b>Authorised redirect URI</b>:</p>
              <div class="mt-1.5 flex items-center gap-1.5">
                <code class="a-code !text-[11px] !py-1.5 !px-2 !whitespace-normal flex-1 min-w-0 break-all">{{ redirectUri }}</code>
                <button type="button" class="admin-btn-secondary !py-1 !px-2 !text-[11px]" @click="copy(redirectUri)">{{ copied ? 'Copied' : 'Copy' }}</button>
              </div>
            </div>
          </li>
          <li class="flex gap-3">
            <span class="a-step">4</span>
            <div>
              <p class="font-semibold">Paste and connect</p>
              <p class="a-muted">Copy the Client ID and secret into the form, save, then click <b>Connect Google</b>. Google shows "app not verified" because it is your own app: click <b>Advanced → Go to …</b> and allow everything.</p>
            </div>
          </li>
        </ol>
      </aside>
    </div>
  </AdminLayout>
</template>

<script setup>
import SelectBox from '@/Components/SelectBox.vue';
import { ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import { confirmDialog } from '@/Composables/useConfirm';

const props = defineProps({
  client: Object, redirectUri: String, origin: String, connected: Boolean, email: String, lastError: String,
  selected: Object, lists: Object, automation: Object, listErrors: { type: [Object, Array], default: () => ({}) }, reviews: Object,
});

const apis = [
  { id: 'searchconsole.googleapis.com', name: 'Google Search Console API' },
  { id: 'analyticsdata.googleapis.com', name: 'Google Analytics Data API' },
  { id: 'analyticsadmin.googleapis.com', name: 'Google Analytics Admin API' },
  { id: 'mybusinessaccountmanagement.googleapis.com', name: 'My Business Account Management API' },
  { id: 'mybusinessbusinessinformation.googleapis.com', name: 'My Business Business Information API' },
  { id: 'mybusiness.googleapis.com', name: 'Google My Business API (reviews)' },
];

const clientForm = useForm({ client_id: props.client?.id || '', client_secret: '' });
const saveClient = () => clientForm.post('/admin/insights/google/client', { preserveScroll: true, onSuccess: () => { clientForm.client_secret = ''; } });

const refreshing = ref(false);
const refreshNote = ref('');
let noteTimer;
function refreshLists() {
  refreshing.value = true;
  refreshNote.value = '';
  router.reload({
    data: { refresh: 1 },
    only: ['lists', 'listErrors'],
    onSuccess: (page) => {
      const l = page.props.lists || {};
      const n = (list, one, many) => `${(list || []).length} ${(list || []).length === 1 ? one : many}`;
      refreshNote.value = `Lists updated: ${n(l.sites, 'Search Console site', 'Search Console sites')}, ${n(l.properties, 'Analytics property', 'Analytics properties')}, ${n(l.locations, 'Business Profile', 'Business Profiles')}.`;
      clearTimeout(noteTimer);
      noteTimer = setTimeout(() => { refreshNote.value = ''; }, 8000);
    },
    onFinish: () => { refreshing.value = false; },
  });
}

// ---- Automatic updates
const syncForm = useForm({
  ...Object.fromEntries((props.automation?.tasks || []).map((t) => [t.key, t.every])),
  index_batch: props.automation?.index_batch || 150,
});
function saveSync() { syncForm.post('/admin/insights/google/sync', { preserveScroll: true }); }
const running = ref('');
function runNow(task) {
  running.value = task;
  router.post(`/admin/insights/google/sync/${task}/run`, {}, { preserveScroll: true, onFinish: () => { running.value = ''; } });
}
function everyLabel(h) {
  if (!h) return 'Off';
  if (h < 24) return h === 1 ? 'Every hour' : `Every ${h} hours`;
  if (h === 24) return 'Every day';
  if (h === 168) return 'Every week';
  return `Every ${h / 24} days`;
}
function whenNext(iso) {
  const d = new Date(iso);
  return d <= new Date() ? 'within 5 minutes' : d.toLocaleString('en-SG', { dateStyle: 'medium', timeStyle: 'short' });
}

const propsForm = useForm({ gsc: props.selected?.gsc || '', ga4: props.selected?.ga4 || '', gbp: props.selected?.gbp || '' });
const saveProps = () => propsForm.post('/admin/insights/google/properties', { preserveScroll: true });

const connect = () => router.post('/admin/insights/google/connect');
async function disconnect() {
  if (await confirmDialog({ title: 'Disconnect Google?', message: 'Reports stop updating and new reviews are no longer imported. Reviews already on the website stay.', confirmText: 'Disconnect', tone: 'danger' })) {
    router.post('/admin/insights/google/disconnect', {}, { preserveScroll: true });
  }
}
const sync = () => router.post('/admin/insights/google/reviews/sync', {}, { preserveScroll: true });

const copied = ref(false);
function copy(text) {
  navigator.clipboard?.writeText(text).then(() => { copied.value = true; setTimeout(() => { copied.value = false; }, 1500); });
}

function explain(msg, kind) {
  if (/not switched on|has not been used|disabled/i.test(msg)) return 'The API for this is not switched on yet (step 2). ' + msg;
  if (kind === 'gbp' && /quota|429|PERMISSION_DENIED|403/i.test(msg)) return 'Google has not approved Business Profile API access for this project yet (step 2, "request it here").';
  return msg;
}
function ago(iso) {
  const min = Math.round((Date.now() - new Date(iso)) / 60000);
  if (min < 60) return `${min} min ago`;
  if (min < 1440) return `${Math.round(min / 60)} h ago`;
  return `${Math.round(min / 1440)} days ago`;
}
</script>
