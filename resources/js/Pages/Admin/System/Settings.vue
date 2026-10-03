<template>
  <AdminLayout title="Site status & email">
    <PageHeader title="Site status & email" description="Switch the website offline for an update, turn on error details for a short time, set up the email server and choose which countries can open the site." />

    <nav class="a-tabs mb-5">
      <button v-for="t in tabs" :key="t.key" type="button" @click="setTab(t.key)" :class="['a-tab', tab === t.key && 'a-tab-active']">
        {{ t.label }}
        <span v-if="t.badge" :class="['a-badge ml-1.5', t.badgeCls]">{{ t.badge }}</span>
      </button>
    </nav>

    <!-- Site status -->
    <div v-show="tab === 'status'" class="space-y-5">
      <form @submit.prevent="saveStatus" class="admin-card overflow-hidden">
        <header class="a-card-head">
          <div>
            <h3 class="a-card-title">Maintenance mode</h3>
            <p class="a-card-sub">Visitors see a tidy "back soon" page with your WhatsApp and phone. You and your team still see the full site while signed in.</p>
          </div>
          <span :class="['a-badge', status.maintenance ? 'a-badge-warning' : 'a-badge-success']">{{ status.maintenance ? 'Offline for visitors' : 'Live' }}</span>
        </header>
        <div class="p-5 space-y-4">
          <label class="a-toggle-row">
            <span><span class="block text-sm font-semibold">Take the website offline</span><span class="block text-xs a-muted mt-0.5">Google is told the site is only down for a while (status 503), so rankings are not affected. Keep it short: a few hours at most.</span></span>
            <input v-model="statusForm.maintenance" type="checkbox" class="a-switch mt-0.5" />
          </label>
          <div class="grid md:grid-cols-[1fr_16rem] gap-4">
            <div>
              <label class="admin-label">Message for visitors</label>
              <input v-model="statusForm.maintenance_message" type="text" class="admin-input" maxlength="300" placeholder="The site is down for a short update. You can still reach us for quotes and bookings." />
            </div>
            <div>
              <label class="admin-label">Back at <span class="a-subtle font-normal">(optional)</span></label>
              <input v-model="statusForm.maintenance_back" type="text" class="admin-input" maxlength="60" placeholder="today at 6 pm" />
            </div>
          </div>
          <div class="flex flex-wrap items-center justify-between gap-3">
            <a href="/admin/system/settings/preview?mode=maintenance" target="_blank" class="text-[13px] a-accent font-semibold">See what visitors see ↗</a>
            <button type="submit" :disabled="statusForm.processing" class="admin-btn-primary">Save</button>
          </div>
        </div>
      </form>

      <form @submit.prevent="saveStatus" class="admin-card overflow-hidden">
        <header class="a-card-head">
          <div>
            <h3 class="a-card-title">Site mode</h3>
            <p class="a-card-sub">Live for the real website. Testing for a copy on a test server: Google is told not to index it.</p>
          </div>
          <span :class="['a-badge', server.environment === 'production' ? 'a-badge-success' : 'a-badge-warning']">Now: {{ server.environment === 'production' ? 'Live' : 'Testing' }}</span>
        </header>
        <div class="p-5 space-y-4">
          <div class="grid sm:grid-cols-3 gap-3">
            <label v-for="m in envModes" :key="m.value" class="a-choice">
              <input v-model="statusForm.env" type="radio" :value="m.value" />
              <span><span class="block text-sm font-semibold">{{ m.label }}</span><span class="block text-xs a-muted mt-0.5">{{ m.help }}</span></span>
            </label>
          </div>
          <p v-if="statusForm.env === 'local' && server.environment === 'production'" class="a-alert a-alert-warning text-xs">Testing mode on the live website hides it from Google. Use it only on a test copy.</p>
          <div class="flex justify-end"><button type="submit" :disabled="statusForm.processing" class="admin-btn-secondary">Save</button></div>
        </div>
      </form>

      <form @submit.prevent="saveStatus" class="admin-card overflow-hidden">
        <header class="a-card-head">
          <div>
            <h3 class="a-card-title">Website address (production URL)</h3>
            <p class="a-card-sub">The real domain. Sitemap, canonical tags, Google schema and email links always use it, even if the server's .env says something else.</p>
          </div>
        </header>
        <div class="p-5 flex flex-wrap items-end gap-3">
          <div class="flex-1 min-w-[16rem]">
            <input v-model="statusForm.app_url" type="url" class="admin-input a-mono" :placeholder="server.app_url" />
            <p v-if="statusForm.errors.app_url" class="a-error">{{ statusForm.errors.app_url }}</p>
            <p v-else class="a-help">Example: <span class="a-mono">https://tasfiaplumbing.sg</span>. Leave empty to use APP_URL from .env. Starting with https:// also forces secure links.</p>
          </div>
          <button type="submit" :disabled="statusForm.processing" class="admin-btn-secondary">Save</button>
        </div>
      </form>

      <section class="admin-card overflow-hidden">
        <header class="a-card-head">
          <div>
            <h3 class="a-card-title">Debug mode</h3>
            <p class="a-card-sub">Shows the full technical error instead of "Server error". Only for signed-in team members, and it turns itself off after {{ status.debug_minutes }} minutes.</p>
          </div>
          <span :class="['a-badge', debugLeft ? 'a-badge-warning' : '']">{{ debugLeft ? `On · ${debugLeft} left` : 'Off' }}</span>
        </header>
        <div class="p-5 flex flex-wrap items-center justify-between gap-4">
          <p class="text-sm a-muted max-w-xl">Use it when something breaks and a developer asks for the error message. Visitors never see the details.</p>
          <button v-if="!debugLeft" type="button" class="admin-btn-secondary" @click="setDebug(true)">Turn on for {{ status.debug_minutes }} minutes</button>
          <button v-else type="button" class="admin-btn-primary" @click="setDebug(false)">Turn off now</button>
        </div>
        <p v-if="server.env_debug && server.environment === 'production'" class="mx-5 mb-5 a-alert a-alert-danger text-xs">APP_DEBUG is <b>true</b> in the server's .env file, so every visitor sees error details. Set <span class="a-mono">APP_DEBUG=false</span> on the live server.</p>
      </section>
    </div>

    <!-- Email -->
    <form v-show="tab === 'mail'" @submit.prevent="saveMail" class="space-y-5">
      <section class="admin-card overflow-hidden">
        <header class="a-card-head">
          <div>
            <h3 class="a-card-title">Email server (SMTP)</h3>
            <p class="a-card-sub">Used for enquiry alerts, article approvals and password emails. Get these details from your email provider (Google Workspace, Zoho, Outlook, your hosting…).</p>
          </div>
          <span :class="['a-badge', mail.enabled && mail.host ? 'a-badge-success' : 'a-badge-warning']">{{ mail.enabled && mail.host ? 'SMTP on' : 'Not set up' }}</span>
        </header>
        <div class="p-5 space-y-4">
          <p v-if="!mail.enabled" class="a-alert a-alert-warning text-xs">Right now the server uses the <span class="a-mono">{{ mail.env_mailer }}</span> mailer<template v-if="mail.env_mailer === 'log'">, so emails are only written to a log file and nobody receives them</template>. Fill in the details below and switch SMTP on.</p>
          <label class="a-toggle-row">
            <span><span class="block text-sm font-semibold">Send emails through this server</span><span class="block text-xs a-muted mt-0.5">Overrides the MAIL_ settings in the server's .env file.</span></span>
            <input v-model="mailForm.enabled" type="checkbox" class="a-switch mt-0.5" />
          </label>

          <div>
            <p class="admin-label">Quick fill</p>
            <div class="flex flex-wrap gap-2">
              <button v-for="p in presets" :key="p.name" type="button" class="admin-btn-secondary !py-1.5 !text-xs" @click="usePreset(p)">{{ p.name }}</button>
            </div>
          </div>

          <div class="grid md:grid-cols-[1fr_8rem_10rem] gap-4">
            <div>
              <label class="admin-label">SMTP host</label>
              <input v-model="mailForm.host" type="text" class="admin-input a-mono" placeholder="smtp.gmail.com" />
              <p v-if="mailForm.errors.host" class="a-error">{{ mailForm.errors.host }}</p>
            </div>
            <div>
              <label class="admin-label">Port</label>
              <input v-model.number="mailForm.port" type="number" class="admin-input a-mono" placeholder="587" />
              <p v-if="mailForm.errors.port" class="a-error">{{ mailForm.errors.port }}</p>
            </div>
            <div>
              <label class="admin-label">Security</label>
              <SelectBox v-model="mailForm.encryption" class="admin-input">
                <option value="tls">TLS (port 587)</option>
                <option value="ssl">SSL (port 465)</option>
                <option value="none">None</option>
              </SelectBox>
            </div>
          </div>
          <div class="grid md:grid-cols-2 gap-4">
            <div>
              <label class="admin-label">Username</label>
              <input v-model="mailForm.username" type="text" class="admin-input" autocomplete="off" placeholder="you@yourcompany.com" />
            </div>
            <div>
              <label class="admin-label">Password / app password</label>
              <p class="admin-input flex items-center justify-between gap-2">
                <span>{{ mail.has_password ? 'Saved' : 'Not set' }}</span>
                <button v-if="apiKeys" type="button" class="underline text-sm" @click="setTab('keys')">{{ mail.has_password ? 'Change' : 'Add' }} in API keys</button>
                <span v-else class="a-subtle text-sm">A Super Admin sets it in API keys</span>
              </p>
              <p class="a-help">Gmail and Outlook need an <b>app password</b>, not your normal password. Stored encrypted.</p>
            </div>
          </div>
          <div class="grid md:grid-cols-2 gap-4">
            <div>
              <label class="admin-label">From address</label>
              <input v-model="mailForm.from_address" type="email" class="admin-input" placeholder="hello@yourcompany.com" />
              <p v-if="mailForm.errors.from_address" class="a-error">{{ mailForm.errors.from_address }}</p>
              <p v-else class="a-help">Use an address on your own domain, or the same as the username.</p>
            </div>
            <div>
              <label class="admin-label">From name</label>
              <input v-model="mailForm.from_name" type="text" class="admin-input" :placeholder="server.app_name" />
            </div>
          </div>
          <div class="flex justify-end"><button type="submit" :disabled="mailForm.processing" class="admin-btn-primary">Save email settings</button></div>
        </div>
      </section>

      <section class="admin-card p-5 flex flex-wrap items-end gap-3">
        <div class="flex-1 min-w-[14rem]">
          <label class="admin-label">Send a test email to</label>
          <input v-model="testTo" type="email" class="admin-input" placeholder="you@example.com" />
        </div>
        <button type="button" class="admin-btn-secondary" :disabled="testing || !testTo" @click="sendTest">{{ testing ? 'Sending…' : 'Send test email' }}</button>
        <p v-if="testResult" role="status" :class="['w-full rounded-lg px-3.5 py-2.5 text-sm', testResult.ok ? 'bg-emerald-50 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200' : 'bg-red-50 text-red-800 dark:bg-red-950/40 dark:text-red-200']">{{ testResult.text }}</p>
        <p class="w-full a-help">Save first. The test uses the saved settings and shows the exact error if the server refuses.</p>
      </section>
    </form>

    <!-- Country access -->
    <form v-show="tab === 'geo'" @submit.prevent="saveGeo" class="admin-card overflow-hidden">
      <header class="a-card-head">
        <div>
          <h3 class="a-card-title">Country access</h3>
          <p class="a-card-sub">Stop spam enquiries and bots from countries you do not serve. Google, Bing and WhatsApp/Facebook link previews are always allowed, so SEO is safe.</p>
        </div>
        <span :class="['a-badge', geoForm.mode !== 'off' ? 'a-badge-accent' : '']">{{ geo.mode === 'off' ? 'Everyone allowed' : geo.mode === 'allow' ? 'Only listed countries' : 'Listed countries blocked' }}</span>
      </header>
      <div class="p-5 space-y-4">
        <div :class="['a-alert block text-xs', geo.your_country ? 'a-alert-info' : 'a-alert-warning']">
          <template v-if="geo.your_country">Country detection works. You are visiting from <b>{{ geo.your_country }}</b>; your own country is never blocked.</template>
          <template v-else>The server cannot see visitor countries yet, so this setting has no effect. Put the site behind <b>Cloudflare</b> (free plan): it tells the site each visitor's country. Until then nobody is blocked.</template>
        </div>
        <div class="grid sm:grid-cols-3 gap-3">
          <label v-for="m in geoModes" :key="m.value" :class="['a-choice', geoForm.mode === m.value && 'is-on']">
            <input v-model="geoForm.mode" type="radio" :value="m.value" />
            <span><span class="block text-sm font-semibold">{{ m.label }}</span><span class="block text-xs a-muted mt-0.5">{{ m.help }}</span></span>
          </label>
        </div>
        <div v-if="geoForm.mode !== 'off'">
          <label class="admin-label">{{ geoForm.mode === 'allow' ? 'Allowed countries' : 'Blocked countries' }}</label>
          <CountryPicker v-model="geoForm.countries" :placeholder="geoForm.mode === 'allow' ? 'Search, e.g. Singapore' : 'Search a country to block'" />
          <p v-if="geoForm.errors.countries" class="a-error">{{ geoForm.errors.countries }}</p>
          <p v-else class="a-help">Type to search, tick as many as you need. {{ geoForm.mode === 'allow' ? 'Visitors from every other country see the "not available" page.' : 'Visitors from these countries see the "not available" page.' }}</p>
        </div>
        <div v-if="geoForm.mode !== 'off'">
          <label class="admin-label">Message on the blocked page</label>
          <input v-model="geoForm.message" type="text" class="admin-input" maxlength="300" placeholder="This website only serves customers in Singapore." />
        </div>
        <div class="flex flex-wrap items-center justify-between gap-3">
          <a href="/admin/system/settings/preview?mode=blocked" target="_blank" class="text-[13px] a-accent font-semibold">See the blocked page ↗</a>
          <button type="submit" :disabled="geoForm.processing" class="admin-btn-primary">Save</button>
        </div>
      </div>
    </form>

    <!-- API keys: every key, token and password in one place (Super Admin only, App\Support\ApiKeys) -->
    <section v-if="apiKeys" v-show="tab === 'keys'" class="admin-card overflow-hidden">
      <header class="a-card-head"><div><h3 class="a-card-title">API keys</h3><p class="a-card-sub">Every key, token and password the website uses, in one place. Saved encrypted and never shown again (only the last 4 characters). Only a Super Admin can change them, and every change is emailed to the main admin mailbox.</p></div></header>
      <ul class="a-divide">
        <li v-for="k in apiKeys" :key="k.name" class="px-5 py-4 grid lg:grid-cols-[1fr_auto] gap-3 lg:items-center">
          <div class="min-w-0 text-sm">
            <p class="font-semibold">{{ k.label }}
              <span v-if="k.saved" class="a-badge a-badge-success ml-1">Saved ••••{{ k.last4 }}</span>
              <span v-else-if="k.from_server" class="a-badge ml-1">From the server file</span>
              <span v-else class="a-badge ml-1">Not set</span></p>
            <p class="a-muted mt-0.5">{{ k.used_for }} Used in <a :href="k.page.url" class="underline">{{ k.page.label }}</a>.</p>
            <p class="a-subtle mt-0.5">Get it: {{ k.get }}</p>
            <template v-if="k.usage">
              <form @submit.prevent="saveLimit(k)" class="mt-2 flex flex-wrap items-center gap-2">
                <span>This month: <b>{{ k.usage.used }}</b> of {{ k.usage.limit }} searches (about {{ Math.floor(Math.max(0, k.usage.limit - k.usage.used) / k.usage.per_article) }} articles left).</span>
                <label class="a-muted" :for="`limit-${k.name}`">Monthly limit</label>
                <input :id="`limit-${k.name}`" v-model.number="limitInput" type="number" min="0" step="1" class="admin-input w-28" />
                <button class="admin-btn-secondary a-btn-sm" :disabled="limitInput === k.usage.limit || limitInput === ''">Save limit</button>
              </form>
              <p class="a-subtle mt-1">The Brave plan includes $5 of credit a month = 1,000 searches, for all sites using the same key together. Keep the limits of all sites under 1,000 (240 each for 4 sites) and the card is never charged. Each article uses {{ k.usage.per_article }} searches.</p>
            </template>
            <p v-if="keyErrors[k.name]" class="a-error">{{ keyErrors[k.name] }}</p>
          </div>
          <form @submit.prevent="saveKey(k)" class="flex flex-wrap gap-2">
            <input v-model="keyInputs[k.name]" type="password" autocomplete="new-password" :aria-label="k.label" :placeholder="k.saved ? 'Paste a new one to replace' : 'Paste here'" class="admin-input a-mono sm:w-64" />
            <button class="admin-btn-primary a-btn-sm" :disabled="!(keyInputs[k.name] || '').trim() || keySaving === k.name">{{ keySaving === k.name ? 'Checking…' : 'Save' }}</button>
            <button v-if="k.saved" type="button" @click="removeKey(k)" class="a-btn-ghost a-btn-sm !a-text-danger">Remove</button>
          </form>
        </li>
      </ul>
    </section>

    <!-- Server health -->
    <section v-show="tab === 'server'" class="admin-card overflow-hidden">
      <header class="a-card-head"><div><h3 class="a-card-title">Server check</h3><p class="a-card-sub">What the live server needs for everything to work. Red items are for your developer or hosting.</p></div></header>
      <ul class="a-divide">
        <li v-for="c in serverChecks" :key="c.label" class="px-5 py-3.5 flex items-start gap-3">
          <span :class="['mt-0.5 w-5 h-5 shrink-0 rounded-full grid place-items-center text-[11px] font-bold text-white', c.ok ? 'bg-emerald-600' : c.warn ? 'bg-amber-500' : 'bg-red-600']">{{ c.ok ? '✓' : '!' }}</span>
          <div class="min-w-0">
            <p class="text-sm font-semibold">{{ c.label }} <span class="font-normal a-muted">· {{ c.value }}</span></p>
            <p v-if="!c.ok" class="text-xs a-muted mt-0.5">{{ c.fix }}</p>
          </div>
        </li>
      </ul>
    </section>
  </AdminLayout>
</template>

<script setup>
import SelectBox from '@/Components/SelectBox.vue';
import { computed, onBeforeUnmount, reactive, ref } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import CountryPicker from '@/Components/Admin/CountryPicker.vue';
import { confirmDialog } from '@/Composables/useConfirm';

const props = defineProps({ status: Object, mail: Object, geo: Object, server: Object, apiKeys: { type: Array, default: null } });

const tab = ref(new URLSearchParams(location.search).get('tab') || 'status');
function setTab(key) {
  tab.value = key;
  const u = new URL(location.href); u.searchParams.set('tab', key); history.replaceState(history.state, '', u);
}

const serverChecks = computed(() => {
  const s = props.server;
  const seenMin = s.scheduler_seen ? Math.round((Date.now() - new Date(s.scheduler_seen)) / 60000) : null;
  return [
    { label: 'Environment', value: s.environment, ok: s.environment === 'production', warn: true, fix: 'Set APP_ENV=production in .env on the live server.' },
    { label: 'Error details (APP_DEBUG)', value: s.env_debug ? 'on' : 'off', ok: !s.env_debug, fix: 'Set APP_DEBUG=false in .env on the live server. Use the 30-minute debug switch instead.' },
    { label: 'HTTPS', value: s.app_url, ok: s.https, fix: 'Install an SSL certificate and set APP_URL=https://… in .env. Google ranks https sites higher and browsers warn on http.' },
    { label: 'Site name (APP_NAME)', value: s.app_name, ok: s.app_name && s.app_name !== 'Laravel', warn: true, fix: 'Set APP_NAME="Tasfia Plumbing" in .env (used in email subjects).' },
    { label: 'Scheduled tasks (cron)', value: seenMin === null ? 'never ran' : seenMin <= 2 ? 'running' : `last run ${seenMin} min ago`, ok: seenMin !== null && seenMin <= 2, fix: 'Add a cron job: * * * * * cd /path/to/site && php artisan schedule:run >> /dev/null 2>&1. Needed for scheduled articles and Google data sync.' },
    { label: 'Email', value: props.mail.enabled && props.mail.host ? `SMTP · ${props.mail.host}` : props.mail.env_mailer, ok: !!(props.mail.enabled && props.mail.host) || props.mail.env_mailer === 'smtp', fix: 'Set up the Email tab so enquiry alerts reach you.' },
    { label: 'Upload size limit', value: `${s.upload_max} / post ${s.post_max}`, ok: parseInt(s.upload_max) >= 10, warn: true, fix: 'Set upload_max_filesize=10M and post_max_size=12M in php.ini (hosting control panel → PHP settings).' },
    { label: 'Folders writable', value: s.storage_writable ? 'yes' : 'no', ok: s.storage_writable, fix: 'storage/ and public/ must be writable by PHP for uploads and image resizing.' },
    { label: 'PHP / Laravel', value: `PHP ${s.php} · Laravel ${s.laravel}`, ok: true },
  ];
});

const tabs = computed(() => [
  { key: 'status', label: 'Site status', badge: props.status.maintenance ? 'Offline' : (debugLeft.value ? 'Debug' : ''), badgeCls: 'a-badge-warning' },
  { key: 'mail', label: 'Email', badge: props.mail.enabled && props.mail.host ? '' : 'Set up', badgeCls: 'a-badge-warning' },
  { key: 'geo', label: 'Country access' },
  ...(props.apiKeys ? [{ key: 'keys', label: 'API keys' }] : []),
  { key: 'server', label: 'Server check', badge: serverChecks.value.filter((c) => !c.ok && !c.warn).length || '', badgeCls: 'a-badge-danger' },
]);

// ---- API keys (Super Admin): write-only, each saved and checked on its own
const keyInputs = reactive({});
const keyErrors = reactive({});
const keySaving = ref('');
const limitInput = ref(props.apiKeys?.find((k) => k.usage)?.usage.limit ?? 240);
function saveLimit(k) {
  router.post(`/admin/system/settings/keys/${k.name}`, { limit: limitInput.value }, { preserveScroll: true });
}
function saveKey(k) {
  keySaving.value = k.name;
  keyErrors[k.name] = '';
  router.post(`/admin/system/settings/keys/${k.name}`, { value: keyInputs[k.name] }, {
    preserveScroll: true,
    onSuccess: () => { keyInputs[k.name] = ''; },
    onError: (e) => { keyErrors[k.name] = e.value || 'Not saved.'; },
    onFinish: () => { keySaving.value = ''; },
  });
}
async function removeKey(k) {
  const ok = await confirmDialog({ title: `Remove the ${k.label}?`, message: `${k.used_for} It stops working until a new one is saved.`, confirmText: 'Remove', tone: 'danger' });
  if (ok) router.post(`/admin/system/settings/keys/${k.name}`, { remove: true }, { preserveScroll: true });
}

// ---- Site status
const statusForm = useForm({
  section: 'status',
  maintenance: !!props.status.maintenance,
  maintenance_message: props.status.maintenance_message || '',
  maintenance_back: props.status.maintenance_back || '',
  app_url: props.status.app_url || '',
  env: props.status.env || '',
});
async function saveStatus() {
  if (statusForm.maintenance && !props.status.maintenance) {
    const ok = await confirmDialog({ title: 'Take the website offline?', message: 'Visitors will see the "back soon" page until you switch it off here. You stay signed in and can still see the site.', confirmText: 'Go offline', tone: 'danger' });
    if (!ok) return;
  }
  statusForm.post('/admin/system/settings', { preserveScroll: true });
}

const envModes = computed(() => [
  { value: 'production', label: 'Live (production)', help: 'Google indexes the site, error details are hidden.' },
  { value: 'local', label: 'Testing (local)', help: 'robots.txt blocks Google. For a test copy only.' },
  { value: '', label: 'Use server setting', help: `APP_ENV in .env (${props.status.env_file}).` },
]);
const now = ref(Date.now());
const timer = setInterval(() => { now.value = Date.now(); }, 15000);
onBeforeUnmount(() => clearInterval(timer));
const debugLeft = computed(() => {
  if (!props.status.debug_until) return '';
  const min = Math.ceil((new Date(props.status.debug_until) - now.value) / 60000);
  return min > 0 ? `${min} min` : '';
});
function setDebug(on) {
  router.post('/admin/system/settings/debug', { on }, { preserveScroll: true });
}

// ---- Email
const mailForm = useForm({
  section: 'mail',
  enabled: !!props.mail.enabled,
  host: props.mail.host || '',
  port: props.mail.port || 587,
  encryption: props.mail.encryption || 'tls',
  username: props.mail.username || '',
  from_address: props.mail.from_address || '',
  from_name: props.mail.from_name || '',
});
const presets = [
  { name: 'Gmail / Google Workspace', host: 'smtp.gmail.com', port: 587, encryption: 'tls' },
  { name: 'Outlook / Microsoft 365', host: 'smtp.office365.com', port: 587, encryption: 'tls' },
  { name: 'Zoho Mail', host: 'smtp.zoho.com', port: 465, encryption: 'ssl' },
  { name: 'cPanel hosting', host: 'mail.' + location.hostname.replace(/^www\./, ''), port: 465, encryption: 'ssl' },
];
function usePreset(p) {
  Object.assign(mailForm, { host: p.host, port: p.port, encryption: p.encryption });
}
function saveMail() {
  mailForm.post('/admin/system/settings', { preserveScroll: true });
}
const page = usePage();
const testTo = ref(page.props.auth?.user?.email || props.mail.from_address || '');
const testing = ref(false);
const testResult = ref(null);
function sendTest() {
  testing.value = true;
  testResult.value = null;
  router.post('/admin/system/settings/test-mail', { to: testTo.value }, {
    preserveScroll: true,
    onSuccess: (p) => {
      const f = p.props.flash || {};
      testResult.value = f.error ? { ok: false, text: f.error } : { ok: true, text: f.success || `Test email sent to ${testTo.value}.` };
    },
    onError: (errors) => { testResult.value = { ok: false, text: Object.values(errors)[0] || 'The test email could not be sent.' }; },
    onFinish: () => { testing.value = false; },
  });
}

// ---- Country access
const geoModes = [
  { value: 'off', label: 'Everyone', help: 'No country rules.' },
  { value: 'allow', label: 'Only these countries', help: 'Best for a local business.' },
  { value: 'block', label: 'Block these countries', help: 'Everyone else can visit.' },
];
const geoForm = useForm({ section: 'geo', mode: props.geo.mode || 'off', countries: (props.geo.countries || '').split(/[\s,]+/).filter(Boolean), message: props.geo.message || '' });
function saveGeo() {
  geoForm.transform((d) => ({ ...d, countries: d.countries.join(', ') })).post('/admin/system/settings', { preserveScroll: true });
}
</script>
