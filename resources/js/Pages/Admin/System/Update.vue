<template>
  <AdminLayout title="Updates">
    <PageHeader title="System updates" description="Brings the live website up to date with the latest version on GitHub: code, packages, database and caches, in one click." />
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
      <div class="xl:col-span-2 space-y-6">
        <div class="admin-card p-6">
          <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
            <div>
              <h2 class="text-xl font-bold a-text">Update from GitHub</h2>
              <p class="text-sm a-muted mt-1">
                Pulls the latest code from <span class="font-mono">{{ config.remote }}/{{ config.branch }}</span>, installs packages, updates the database and clears caches.
              </p>
            </div>
            <span :class="['shrink-0 a-badge', environment === 'production' ? 'a-badge-success' : 'a-badge-warning']">{{ environment }}</span>
          </div>

          <div v-if="!status.repo" class="mt-5 space-y-3">
            <p class="a-alert a-alert-warning">{{ status.message }}</p>
            <p v-if="!github.can_run" class="a-alert a-alert-danger text-xs">This server does not let PHP run programs (<span class="a-mono">proc_open</span> is disabled), so updates cannot run from here. Ask your hosting to enable it.</p>
            <div class="flex flex-wrap items-center gap-3">
              <button type="button" :disabled="!github.repo_url || running || !github.can_run" class="admin-btn-primary" @click="startConnect">{{ running ? 'Connecting…' : 'Connect to GitHub' }}</button>
              <span class="text-xs a-muted">{{ github.repo_url ? `Uses ${github.repo_url} (${github.branch})` : 'Save the repository address below first.' }}</span>
            </div>
            <p class="text-xs a-subtle">Connecting replaces the code files with the GitHub version. Your .env, uploaded images, database and settings are not touched.</p>
          </div>

          <template v-else>
            <dl class="mt-5 grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
              <div><dt class="text-xs a-subtle">Branch</dt><dd class="font-mono font-semibold">{{ status.branch }}</dd></div>
              <div><dt class="text-xs a-subtle">Current version</dt><dd class="font-mono font-semibold">{{ status.commit }}</dd></div>
              <div class="col-span-2"><dt class="text-xs a-subtle">Last change</dt><dd class="font-semibold line-clamp-1">{{ status.commit_subject }}</dd><dd class="text-xs a-muted">{{ status.commit_date ? new Date(status.commit_date).toLocaleString('en-SG') : '' }}</dd></div>
              <div class="col-span-2 md:col-span-4"><dt class="text-xs a-subtle">Repository</dt><dd class="font-mono text-xs break-all">{{ status.remote_url }}</dd></div>
            </dl>

            <div v-if="status.modified_count" class="mt-4 a-alert a-alert-danger flex-col gap-1 text-xs">
              <p class="font-bold">{{ status.modified_count }} file(s) were edited directly on this server.</p>
              <p class="mt-1">The update may stop to protect them. Make changes in GitHub, not on the server.</p>
              <p class="font-mono mt-1">{{ status.modified_files.join(', ') }}</p>
            </div>

            <div class="mt-6 flex flex-wrap items-center gap-3">
              <button @click="check" :disabled="checking || running" class="admin-btn-secondary">{{ checking ? 'Checking…' : 'Check for updates' }}</button>
              <button @click="startMigrate" :disabled="running" class="admin-btn-secondary" title="Only run new database changes (no code download)">Run migrations</button>
              <button @click="connecting = false; mode = 'update'; confirmOpen = true" :disabled="running" class="admin-btn-primary">{{ running ? 'Updating…' : 'Update now' }}</button>
              <span v-if="checkResult?.ok" :class="['a-badge', checkResult.behind ? 'a-badge-success' : '']">{{ checkResult.behind ? `${checkResult.behind} update(s) ready` : 'Up to date' }}</span>
            </div>

            <ul v-if="checkResult?.incoming?.length" class="mt-4 border a-border rounded-xl a-divide">
              <li v-for="c in checkResult.incoming" :key="c.hash" class="px-3 py-2 text-sm flex gap-3">
                <span class="font-mono text-xs a-subtle shrink-0">{{ c.hash }}</span>
                <span class="flex-1 a-text">{{ c.subject }}</span>
                <span class="text-xs a-subtle shrink-0">{{ c.author }} · {{ c.when }}</span>
              </li>
            </ul>
          </template>
        </div>

        <!-- Repository -->
        <form class="admin-card overflow-hidden" @submit.prevent="saveGithub">
          <header class="a-card-head">
            <div>
              <h3 class="a-card-title">GitHub repository</h3>
              <p class="a-card-sub">Where the code lives. For a private repository add an access token; it is stored encrypted and never written into the code.</p>
            </div>
            <span :class="['a-badge', status.repo ? 'a-badge-success' : 'a-badge-warning']">{{ status.repo ? 'Connected' : 'Not connected' }}</span>
          </header>
          <div class="p-5 grid md:grid-cols-[1fr_11rem] gap-4">
            <div>
              <label class="admin-label">Repository (User/Repo)</label>
              <input v-model="gh.repo_url" type="text" required class="admin-input a-mono text-xs" placeholder="KlokAds/tasfiaplumbing" />
              <p v-if="gh.errors.repo_url" class="a-error">{{ gh.errors.repo_url }}</p>
            </div>
            <div>
              <label class="admin-label">Branch</label>
              <input v-model="gh.branch" type="text" required class="admin-input a-mono text-xs" placeholder="main" />
              <p v-if="gh.errors.branch" class="a-error">{{ gh.errors.branch }}</p>
            </div>
            <div>
              <label class="admin-label">GitHub username <span class="font-normal a-subtle">(optional)</span></label>
              <input v-model="gh.username" type="text" class="admin-input a-mono text-xs" placeholder="KlokAds" />
              <p class="a-help">Needed only for a classic token.</p>
            </div>
            <div>
              <label class="admin-label flex items-center justify-between">
                <span>Personal access token <span class="font-normal a-subtle">(only for a private repository)</span></span>
              </label>
              <p class="admin-input flex items-center justify-between gap-2"><span>{{ github.has_token ? 'Saved' : 'Not set' }}</span><a href="/admin/system/settings?tab=keys" class="underline text-sm">{{ github.has_token ? 'Change' : 'Add' }} in API keys</a></p>
              <p class="a-help">GitHub → Settings → Developer settings → <b>Fine-grained tokens</b> → Generate: choose only this repository, permission <b>Contents: Read-only</b>. Read-only means the website can download code but can never change GitHub.</p>
            </div>
            <div class="md:col-span-2 flex justify-end"><button type="submit" class="admin-btn-secondary" :disabled="gh.processing">Save</button></div>
          </div>
        </form>

        <div v-if="liveLog" class="admin-card p-6">
          <div class="flex items-center justify-between">
            <h3 class="font-bold a-text">{{ connecting ? 'Connection' : 'Update' }} #{{ liveLog.id }}</h3>
            <span :class="statusBadge(liveLog.status)">{{ liveLog.status }}</span>
          </div>
          <pre ref="logBox" class="mt-3 a-code max-h-[28rem] !whitespace-pre-wrap">{{ liveLog.output || 'Starting…' }}</pre>
          <p v-if="liveLog.status === 'success'" class="mt-3 text-sm a-text-success">Done. Version {{ liveLog.commit_before }} → {{ liveLog.commit_after }}. <button @click="router.reload()" class="underline font-semibold">Reload page</button></p>
          <p v-if="liveLog.status === 'failed'" class="mt-3 text-sm a-text-danger">Update stopped. The site keeps running the last working steps; read the log above and fix the cause in GitHub, then run again.</p>
        </div>

        <div class="admin-card p-6">
          <h3 class="font-bold a-text mb-3">History</h3>
          <table class="a-table">
            <tbody>
              <tr v-for="l in logs" :key="l.id">
                <td class="font-mono text-xs a-subtle">#{{ l.id }}</td>
                <td><span :class="statusBadge(l.status)">{{ l.status }}</span></td>
                <td class="font-mono text-xs">{{ l.commit_before || '—' }} → {{ l.commit_after || '—' }}</td>
                <td class="text-xs a-muted">{{ l.user }}</td>
                <td class="text-xs a-muted">{{ new Date(l.created_at).toLocaleString('en-SG') }}</td>
                <td class="text-right"><button @click="showLog(l.id)" class="a-btn-ghost a-btn-sm">View log</button></td>
              </tr>
            </tbody>
          </table>
          <p v-if="!logs.length" class="text-sm a-muted">No updates run yet.</p>
        </div>
      </div>

      <div class="space-y-6">
        <div class="admin-card p-6">
          <h3 class="font-bold a-text">What “Update now” runs</h3>
          <ol class="mt-3 space-y-1.5 text-sm list-decimal list-inside a-muted">
            <li v-for="s in steps" :key="s">{{ s }}</li>
          </ol>
          <p class="text-xs a-muted mt-3">Stops at the first failed step. Only these fixed commands can run; nothing typed on this page reaches the server shell.</p>
        </div>
        <div class="admin-card p-6 text-sm space-y-2 a-muted">
          <h3 class="font-bold a-text">Workflow</h3>
          <p>1. Change code on your computer and test it.</p>
          <p>2. Run <code class="font-mono text-xs">npm run build</code> so the compiled design files are included.</p>
          <p>3. Commit and push to GitHub (<span class="font-mono text-xs">{{ config.branch }}</span> branch).</p>
          <p>4. Open this page on the live site → Check for updates → Update now.</p>
          <p class="text-xs a-muted pt-2">First time on a new server: save the repository above and click <b>Connect to GitHub</b>. The server needs git installed and PHP allowed to run it (most hosts, including Hostinger, allow this).</p>
        </div>
      </div>
    </div>

    <Modal :show="confirmOpen" :title="mode === 'connect' ? 'Connect to GitHub' : mode === 'migrate' ? 'Run migrations' : 'Confirm update'" :subtitle="mode === 'connect' ? 'The code on this server will be replaced with the GitHub version.' : mode === 'migrate' ? 'New database changes are applied. No code is downloaded.' : 'The live site will be updated to the latest GitHub version.'" width="xl" @close="confirmOpen = false">
      <form @submit.prevent="deploy" class="space-y-4">
        <div>
          <label class="admin-label">Your admin password</label>
          <input v-model="password" type="password" required autocomplete="current-password" class="admin-input" />
          <p v-if="deployError" class="a-error">{{ deployError }}</p>
        </div>
        <div class="flex justify-end gap-3">
          <button type="button" @click="confirmOpen = false" class="admin-btn-secondary">Cancel</button>
          <button type="submit" :disabled="running" class="admin-btn-primary">{{ mode === 'connect' ? 'Connect' : mode === 'migrate' ? 'Run migrations' : 'Update now' }}</button>
        </div>
      </form>
    </Modal>
  </AdminLayout>
</template>

<script setup>
import { nextTick, onBeforeUnmount, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { toast } from '@/Composables/useToast';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import Modal from '@/Components/Admin/Modal.vue';

const props = defineProps({
  status: Object,
  steps: Array,
  logs: Array,
  environment: String,
  config: Object,
  github: { type: Object, default: () => ({}) },
});

const gh = useForm({
  repo_url: (props.github.repo_url || '').replace(/^https:\/\/github\.com\//, '').replace(/\.git$/, ''),
  branch: props.github.branch || 'main',
  username: props.github.username || '',
});
const saveGithub = () => gh.post('/admin/system/update/github', { preserveScroll: true });
const connecting = ref(false);
const mode = ref('update');
function startConnect() {
  connecting.value = true;
  mode.value = 'connect';
  confirmOpen.value = true;
}
function startMigrate() {
  connecting.value = false;
  mode.value = 'migrate';
  confirmOpen.value = true;
}

const checking = ref(false);
const checkResult = ref(null);
const checkError = ref('');

async function check() {
  checking.value = true;
  checkError.value = '';
  try {
    checkResult.value = (await axios.post('/admin/system/update/check')).data;
    const r = checkResult.value;
    r.behind
      ? toast.success(`${r.behind} new update${r.behind > 1 ? 's are' : ' is'} ready on GitHub. Click "Update now" to install.`)
      : toast.info('Already up to date. The site runs the latest version from GitHub.');
    if (r.ahead) toast.info(`This server has ${r.ahead} change(s) that are not on GitHub.`);
  } catch (e) {
    checkError.value = e.response?.data?.message || 'Check failed.';
    toast.error(`Could not check for updates: ${checkError.value}`);
  } finally {
    checking.value = false;
  }
}

const confirmOpen = ref(false);
const password = ref('');
const deployError = ref('');
const running = ref(false);
const liveLog = ref(null);
const logBox = ref(null);
let poller = null;

async function fetchLatest(id = null) {
  const url = id ? `/admin/system/update/logs/${id}` : '/admin/system/update/logs/latest';
  const { data } = await axios.get(url);
  if (data) {
    liveLog.value = data;
    await nextTick();
    if (logBox.value) logBox.value.scrollTop = logBox.value.scrollHeight;
  }
  return data;
}

function startPolling() {
  stopPolling();
  poller = setInterval(async () => {
    try {
      const data = await fetchLatest();
      if (data && data.status !== 'running' && !running.value) stopPolling();
    } catch (e) { /* keep polling */ }
  }, 2000);
}
function stopPolling() {
  if (poller) clearInterval(poller);
  poller = null;
}

async function deploy() {
  deployError.value = '';
  running.value = true;
  liveLog.value = { id: '…', status: 'running', output: '' };
  startPolling();
  try {
    const { data } = await axios.post('/admin/system/update/deploy', { password: password.value, action: mode.value });
    confirmOpen.value = false;
    password.value = '';
    const log = await fetchLatest(data.id);
    const what = mode.value === 'connect' ? 'Connection to GitHub' : mode.value === 'migrate' ? 'Database update' : 'Update';
    log?.status === 'success'
      ? toast.success(`${what} finished${log.commit_after ? ` (version ${log.commit_after})` : ''}. Reload the page to see the new version.`)
      : toast.error(`${what} stopped at a step that failed. The log below shows why.`);
  } catch (e) {
    deployError.value = e.response?.data?.errors?.password?.[0] || e.response?.data?.message || 'Update could not start.';
    if (!e.response?.data?.errors?.password) toast.error(deployError.value);
    if (e.response?.status === 422 || e.response?.status === 409) liveLog.value = null;
  } finally {
    running.value = false;
    stopPolling();
  }
}

async function showLog(id) {
  await fetchLatest(id);
}

onBeforeUnmount(stopPolling);

function statusBadge(s) {
  const tone = s === 'success' ? 'a-tint-success a-text-success' : s === 'failed' ? 'a-tint-danger a-text-danger' : 'a-tint-warning a-text-warning';
  return `inline-block px-2 py-0.5 rounded-md text-xs font-bold ${tone}`;
}
</script>
