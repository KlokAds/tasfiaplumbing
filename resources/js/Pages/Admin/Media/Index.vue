<template>
  <AdminLayout title="Media Library">
    <PageHeader title="Media library" description="Every uploaded image and file. Files used on any page, logo, banner or inside article text are locked and cannot be deleted; remove them from the page first.">
      <button @click="go({}, true)" class="admin-btn-secondary">Re-scan</button>
      <label v-if="can('media.create')" class="admin-btn-primary cursor-pointer">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" /></svg>
        {{ uploading ? 'Uploading…' : currentFolder ? 'Upload here' : 'Upload' }}
        <input type="file" multiple accept="image/jpeg,image/png,image/webp,image/gif,image/avif,application/pdf" class="hidden" @change="upload" :disabled="uploading" />
      </label>
    </PageHeader>

    <StickyBar>
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
      <div class="admin-card a-stat">
        <p class="a-stat-label">Files</p>
        <p class="a-stat-value">{{ summary.total }}</p>
        <p class="a-stat-hint">{{ formatSize(summary.total_size) }}</p>
      </div>
      <button @click="go({ status: 'used' })" class="admin-card a-stat text-left a-hover">
        <p class="a-stat-label">In use (locked)</p>
        <p class="a-stat-value a-text-success">{{ summary.total - summary.unused }}</p>
      </button>
      <button @click="go({ status: 'unused' })" class="admin-card a-stat text-left a-hover">
        <p class="a-stat-label">Not used anywhere</p>
        <p class="a-stat-value a-text-warning">{{ summary.unused }}</p>
        <p class="a-stat-hint">{{ formatSize(summary.unused_size) }} can be freed</p>
      </button>
      <div class="admin-card a-stat" title="Pages point to image files that do not exist on the server">
        <p class="a-stat-label">Broken image links</p>
        <p :class="['a-stat-value', summary.missing && 'a-text-danger']">{{ summary.missing }}</p>
      </div>
    </div>
    </StickyBar>



    <div class="grid grid-cols-1 lg:grid-cols-[16rem_1fr] gap-5 items-start">
      <!-- Folder tree -->
      <aside class="admin-card overflow-hidden lg:sticky lg:top-24">
        <header class="flex items-center justify-between px-3 py-2.5 border-b a-border">
          <p class="text-[11px] font-bold uppercase tracking-wider a-subtle">Folders</p>
          <button v-if="can('media.create')" type="button" class="a-btn-ghost a-btn-sm !px-2" title="New folder" @click="openFolderDialog('create')">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 11v6m-3-3h6M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z" /></svg>
            New
          </button>
        </header>
        <nav class="p-1.5 max-h-[60vh] overflow-y-auto a-scroll">
          <button type="button" :class="['fm-folder', !currentFolder && 'is-active']" @click="go({ folder: '', page: '' })">
            <svg class="w-4 h-4 shrink-0 opacity-70" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" /></svg>
            <span class="flex-1 truncate text-left">All files</span>
            <span class="text-[11px] a-subtle tabular-nums">{{ summary.total }}</span>
          </button>
          <template v-for="f in visibleFolders" :key="f.path">
            <div :class="['fm-folder', currentFolder === f.path && 'is-active', dropTarget === f.path && 'is-drop']" :style="{ paddingLeft: `${0.5 + f.depth * 0.85}rem` }"
              @click="go({ folder: f.path, page: '' })" @dragover.prevent="dropTarget = f.path" @dragleave="dropTarget = null" @drop.prevent="onDrop(f.path)">
              <button v-if="hasChildren(f)" type="button" class="w-4 h-4 grid place-items-center shrink-0 a-subtle" :aria-label="expanded.has(f.path) ? 'Collapse' : 'Expand'" @click.stop="toggleFolder(f.path)">
                <svg :class="['w-3 h-3 transition-transform', expanded.has(f.path) && 'rotate-90']" fill="none" stroke="currentColor" stroke-width="2.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
              </button>
              <span v-else class="w-4 shrink-0"></span>
              <svg class="w-4 h-4 shrink-0 text-amber-500" fill="currentColor" viewBox="0 0 24 24"><path d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z" /></svg>
              <span class="flex-1 truncate text-left">{{ f.depth === 0 ? 'Uploads (top folder)' : f.name }}</span>
              <span v-if="f.count" class="text-[11px] a-subtle tabular-nums">{{ f.count }}</span>
            </div>
          </template>
        </nav>
        <p v-if="can('media.edit')" class="px-3 py-2 border-t a-border text-[11px] a-subtle">Tip: drag files onto a folder to move them.</p>
      </aside>

      <div class="admin-card overflow-hidden min-w-0">
        <!-- Location + folder actions -->
        <div class="px-4 py-3 border-b a-border flex flex-wrap items-center gap-2">
          <nav class="flex flex-wrap items-center gap-1 text-sm min-w-0" aria-label="Folder path">
            <button type="button" class="font-semibold a-hover-text" @click="go({ folder: '', page: '' })">All files</button>
            <template v-for="c in crumbs" :key="c.path">
              <span class="a-subtle">/</span>
              <button type="button" :class="['truncate max-w-[12rem]', c.path === currentFolder ? 'font-semibold' : 'a-muted a-hover-text']" @click="go({ folder: c.path, page: '' })">{{ c.name }}</button>
            </template>
          </nav>
          <div v-if="currentFolder" class="ml-auto flex gap-1.5">
            <button type="button" class="a-btn-ghost a-btn-sm" :disabled="zipping" @click="downloadZip({ folder: currentFolder })">{{ zipping ? 'Preparing…' : 'Download folder' }}</button>
            <button v-if="can('media.create')" type="button" class="a-btn-ghost a-btn-sm" @click="openFolderDialog('create')">New subfolder</button>
            <button v-if="can('media.edit') && currentFolder !== 'Admin'" type="button" class="a-btn-ghost a-btn-sm" @click="openFolderDialog('rename')">Rename</button>
            <button v-if="can('media.delete') && currentFolder.startsWith('Admin/')" type="button" class="a-btn-ghost a-danger a-btn-sm" @click="deleteFolder">Delete folder</button>
          </div>
        </div>

        <div class="p-3 border-b a-border flex flex-col lg:flex-row gap-2">
          <div class="a-seg self-start">
            <button v-for="st in statusTabs" :key="st.key" @click="go({ status: st.key })" :class="(filters.status || '') === st.key && 'is-on'">{{ st.label }}</button>
          </div>
          <SelectBox :model-value="filters.sort || ''" @update:model-value="v => go({ sort: v })" class="admin-input lg:max-w-[11rem]">
            <option value="">Newest first</option>
            <option value="size">Largest first</option>
          </SelectBox>
          <form @submit.prevent="go({ search })" class="lg:ml-auto">
            <input v-model="search" type="search" placeholder="Search file name…" class="admin-input lg:w-64" />
          </form>
        </div>

        <!-- Selection bar -->
        <div v-if="selected.length || (can('media.delete') && unusedOnPage.length)" class="px-4 py-2.5 border-b a-border flex flex-wrap items-center gap-2 text-sm a-panel-2">
          <template v-if="selected.length">
            <span class="font-semibold">{{ selected.length }} selected</span>
            <button v-if="can('media.edit')" @click="openTransfer('move')" class="admin-btn-secondary a-btn-sm">Move to…</button>
            <button v-if="can('media.create')" @click="openTransfer('copy')" class="admin-btn-secondary a-btn-sm">Copy to…</button>
            <button type="button" :disabled="zipping" @click="downloadZip({ paths: selected })" class="admin-btn-secondary a-btn-sm">{{ zipping ? 'Preparing…' : selected.length === 1 ? 'Download' : 'Download (.zip)' }}</button>
            <button v-if="can('media.delete') && selectedUnused.length" @click="deleteSelected" class="a-btn-danger a-btn-sm">Delete {{ selectedUnused.length }} unused</button>
            <button @click="selected = []" class="a-btn-ghost a-btn-sm">Clear</button>
          </template>
          <button v-if="can('media.delete') && unusedOnPage.length" @click="selectAllUnused" class="font-semibold a-accent text-[13px] ml-auto">Select all unused on this page ({{ unusedOnPage.length }})</button>
        </div>

        <div class="p-4 grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 2xl:grid-cols-6 gap-3">
          <div v-for="f in files.data" :key="f.path" :draggable="can('media.edit')" @dragstart="onDragStart(f, $event)" @dragend="dropTarget = null"
            :class="['group relative rounded-xl border overflow-hidden a-panel transition', selected.includes(f.path) ? 'border-[var(--a-accent)] ring-2 ring-[var(--a-accent-soft)]' : 'a-border hover:border-[var(--a-border-2)]']">
            <button type="button" @click="detail = f" class="block w-full aspect-square a-panel-3">
              <img v-if="isImage(f)" :src="f.url" :alt="f.alt || ''" loading="lazy" class="w-full h-full object-cover" draggable="false" />
              <span v-else class="w-full h-full flex items-center justify-center text-xs font-bold a-muted uppercase">{{ f.ext }}</span>
            </button>
            <label class="absolute top-2 left-2 rounded-md p-1 cursor-pointer" style="background: var(--a-panel)" title="Select">
              <input v-model="selected" type="checkbox" :value="f.path" class="block" />
            </label>
            <span :class="['absolute top-2 right-2 a-badge', f.usage_count ? 'a-badge-success' : 'a-badge-warning']" style="backdrop-filter: blur(4px)">
              {{ f.usage_count ? `In use · ${f.usage_count}` : 'Unused' }}
            </span>
            <div class="px-2.5 py-2">
              <div class="text-xs font-semibold truncate" :title="f.name">{{ f.name }}</div>
              <div v-if="f.uploaded_by" class="text-[11px] a-muted truncate" :title="`Uploaded by ${f.uploaded_by}`">by {{ f.uploaded_by }}</div>
              <div class="text-[11px] a-subtle flex justify-between gap-2">
                <span :class="f.size > 500000 && 'a-text-warning'">{{ formatSize(f.size) }}</span>
                <span v-if="!currentFolder" class="truncate" :title="f.folder">{{ f.folder.split('/').pop() }}</span>
                <span v-else-if="!f.alt && isImage(f)" class="a-text-warning">no alt</span>
              </div>
            </div>
          </div>
        </div>
        <div v-if="!files.data.length" class="a-empty">
          <p class="font-semibold">{{ currentFolder ? 'This folder is empty' : 'No files match' }}</p>
          <p v-if="currentFolder && can('media.create')" class="text-sm a-muted mt-1">Upload files here with the Upload button, or move files into it.</p>
        </div>
        <div class="px-4 pb-4"><Pagination :meta="files" /></div>
      </div>
    </div>

    <!-- New / rename folder -->
    <Modal :show="!!folderDialog" :title="folderDialog === 'rename' ? 'Rename folder' : 'New folder'" :subtitle="folderDialog === 'rename' ? 'Pages that use images in this folder are updated automatically.' : `Inside ${folderLabel(folderParent)}`" width="lg" @close="folderDialog = null">
      <form @submit.prevent="saveFolder" class="space-y-4">
        <div>
          <label class="admin-label">Folder name</label>
          <input ref="folderInput" v-model="folderName" type="text" required maxlength="60" class="admin-input" placeholder="e.g. Bathroom jobs" />
          <p class="a-help">Letters, numbers, spaces, - and _.</p>
        </div>
        <div v-if="folderDialog === 'create'">
          <label class="admin-label">Inside</label>
          <SelectBox v-model="folderParent" class="admin-input">
            <option v-for="f in folders" :key="f.path" :value="f.path">{{ folderLabel(f.path) }}</option>
          </SelectBox>
        </div>
        <div class="flex justify-end gap-2">
          <button type="button" class="admin-btn-secondary" @click="folderDialog = null">Cancel</button>
          <button type="submit" class="admin-btn-primary">{{ folderDialog === 'rename' ? 'Rename' : 'Create folder' }}</button>
        </div>
      </form>
    </Modal>

    <!-- Move / copy -->
    <Modal :show="!!transferMode" :title="transferMode === 'move' ? `Move ${selected.length} file(s)` : `Copy ${selected.length} file(s)`" :subtitle="transferMode === 'move' ? 'Pages that use these images keep working: their links are updated.' : 'The copies are new files; the originals stay where they are.'" width="lg" @close="transferMode = null">
      <form @submit.prevent="doTransfer" class="space-y-4">
        <div>
          <label class="admin-label">To folder</label>
          <SelectBox v-model="transferTo" class="admin-input">
            <option v-for="f in folders" :key="f.path" :value="f.path">{{ folderLabel(f.path) }}</option>
          </SelectBox>
        </div>
        <div class="flex justify-end gap-2">
          <button type="button" class="admin-btn-secondary" @click="transferMode = null">Cancel</button>
          <button type="submit" class="admin-btn-primary">{{ transferMode === 'move' ? 'Move here' : 'Copy here' }}</button>
        </div>
      </form>
    </Modal>

    <Modal :show="!!detail" :title="detail?.name" :subtitle="detail?.folder" width="4xl" @close="detail = null">
      <div v-if="detail" class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="a-panel-2 rounded-xl flex items-center justify-center min-h-[16rem] overflow-hidden">
          <img v-if="isImage(detail)" :src="detail.url" :alt="detail.alt || ''" class="max-h-[26rem] w-auto" />
          <a v-else :href="detail.url" target="_blank" class="text-sm font-semibold underline">Open {{ detail.ext.toUpperCase() }}</a>
        </div>
        <div class="space-y-4 text-sm">
          <dl class="grid grid-cols-2 gap-3">
            <div><dt class="text-xs a-subtle">Size</dt><dd class="font-semibold" :class="detail.size > 500000 ? 'a-text-warning' : ''">{{ formatSize(detail.size) }}</dd></div>
            <div><dt class="text-xs a-subtle">Dimensions</dt><dd class="font-semibold">{{ detail.width ? `${detail.width}×${detail.height}` : '—' }}</dd></div>
            <div class="col-span-2"><dt class="text-xs a-subtle">Uploaded by</dt><dd class="font-semibold">{{ detail.uploaded_by || 'Not recorded (uploaded before this was tracked)' }}<span v-if="detail.uploaded_by && detail.uploaded_at" class="font-normal a-muted"> · {{ new Date(detail.uploaded_at).toLocaleString('en-SG', { dateStyle: 'medium', timeStyle: 'short' }) }}</span></dd></div>
            <div class="col-span-2"><dt class="text-xs a-subtle">Modified</dt><dd class="font-semibold">{{ new Date(detail.modified).toLocaleString('en-SG') }}</dd></div>
          </dl>
          <p v-if="detail.size > 500000" class="text-xs a-text-warning">Over 500 KB. Compress or convert to WebP before re-uploading; big images slow the page (LCP).</p>

          <div>
            <label class="admin-label">URL</label>
            <div class="flex gap-2">
              <input :value="detail.url" readonly class="admin-input a-mono text-xs" @focus="e => e.target.select()" />
              <button type="button" @click="copy(detail.url)" class="admin-btn-secondary">{{ copied ? 'Copied' : 'Copy' }}</button>
            </div>
          </div>

          <form v-if="isImage(detail) && can('media.edit')" @submit.prevent="saveAlt" class="space-y-2">
            <label class="admin-label">Default alt text</label>
            <div class="flex gap-2">
              <input v-model="altForm.alt" type="text" maxlength="255" class="admin-input" placeholder="Describe the photo" />
              <button type="submit" class="admin-btn-primary">Save</button>
            </div>
            <p class="a-help">Suggested when this image is inserted into a page. Describe what is in the photo, e.g. “Technician replacing a water heater in an HDB bathroom”.</p>
          </form>

          <div>
            <p class="admin-label">Used in</p>
            <ul v-if="detail.usages.length" class="space-y-1">
              <li v-for="(u, i) in detail.usages" :key="i">
                <Link :href="u.url" class="text-sm hover:underline"><span class="a-subtle">{{ u.label }}:</span> {{ u.title }} <span class="text-xs a-subtle">({{ u.field }})</span></Link>
              </li>
              <li v-if="detail.usage_count > detail.usages.length" class="text-xs a-muted">…and {{ detail.usage_count - detail.usages.length }} more</li>
            </ul>
            <p v-else class="text-sm a-text-warning">Not used anywhere.</p>
          </div>

          <div class="pt-3 border-t a-border">
            <div class="flex flex-wrap gap-2">
              <a :href="`/admin/media/download?path=${encodeURIComponent(detail.path)}`" class="admin-btn-secondary">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                Download
              </a>
              <button v-if="!detail.usage_count && !detail.legacy && can('media.delete')" type="button" @click="deleteOne(detail)" class="a-btn-danger">Delete file permanently</button>
            </div>
            <p v-if="detail.usage_count" class="a-alert a-alert-info text-xs mt-3">Locked: this file is in use. Replace or remove it on the pages above first.</p>
            <p v-else-if="detail.legacy" class="a-alert a-alert-info text-xs mt-3">From the old website's folder: kept as it is. New uploads go to the Admin folder.</p>
          </div>
        </div>
      </div>
    </Modal>
  </AdminLayout>
</template>

<script setup>
import StickyBar from '@/Components/Admin/StickyBar.vue';
import SelectBox from '@/Components/SelectBox.vue';
import { confirmDialog } from '@/Composables/useConfirm';
import { computed, ref, watch } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { toast } from '@/Composables/useToast';
import { compressImage } from '@/Composables/compressImage';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Modal from '@/Components/Admin/Modal.vue';
import Pagination from '@/Components/Admin/Pagination.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import { usePermissions } from '@/Composables/usePermissions';

const { can } = usePermissions();

const props = defineProps({
  files: Object,
  summary: Object,
  folders: { type: Array, default: () => [] },   // [{ path, name, depth, count }]
  filters: Object,
  uploadFolder: String,
});

// ---- folders
const currentFolder = computed(() => props.filters?.folder || '');
const crumbs = computed(() => {
  if (!currentFolder.value) return [];
  const parts = currentFolder.value.split('/');
  return parts.map((name, i) => ({ name: i === 0 ? 'Uploads' : name, path: parts.slice(0, i + 1).join('/') }));
});
const folderLabel = (path) => (path || '').split('/').map((p, i) => (i === 0 ? 'Uploads' : p)).join(' / ');
const expanded = ref(new Set(['Admin', ...crumbs.value.map((c) => c.path)]));
const hasChildren = (f) => props.folders.some((x) => x.path.startsWith(f.path + '/'));
const visibleFolders = computed(() => props.folders.filter((f) => {
  const parts = f.path.split('/');
  for (let i = 1; i < parts.length; i++) {
    if (!expanded.value.has(parts.slice(0, i).join('/'))) return false;
  }
  return true;
}));
function toggleFolder(path) {
  const next = new Set(expanded.value);
  next.has(path) ? next.delete(path) : next.add(path);
  expanded.value = next;
}

const folderDialog = ref(null);
const folderName = ref('');
const folderParent = ref('Admin');
const folderInput = ref(null);
function openFolderDialog(kind) {
  folderDialog.value = kind;
  folderParent.value = currentFolder.value || 'Admin';
  folderName.value = kind === 'rename' ? currentFolder.value.split('/').pop() : '';
  setTimeout(() => folderInput.value?.focus(), 50);
}
function saveFolder() {
  const done = { preserveScroll: true, onSuccess: () => { folderDialog.value = null; } };
  folderDialog.value === 'rename'
    ? router.post('/admin/media/folders/rename', { path: currentFolder.value, name: folderName.value }, done)
    : router.post('/admin/media/folders', { parent: folderParent.value, name: folderName.value }, done);
}
async function deleteFolder() {
  if (await confirmDialog({ title: `Delete folder "${currentFolder.value.split('/').pop()}"?`, message: 'Only empty folders can be deleted.', confirmText: 'Delete folder' })) {
    router.post('/admin/media/folders/delete', { path: currentFolder.value }, { preserveScroll: true });
  }
}

// ---- move / copy
const transferMode = ref(null);
const transferTo = ref('Admin');
function openTransfer(mode) {
  transferMode.value = mode;
  transferTo.value = currentFolder.value || props.uploadFolder || 'Admin';
}
function doTransfer() {
  router.post('/admin/media/transfer', { paths: selected.value, to: transferTo.value, mode: transferMode.value }, {
    preserveScroll: true,
    onSuccess: () => { transferMode.value = null; selected.value = []; },
  });
}
const dropTarget = ref(null);
let dragged = [];
function onDragStart(f, e) {
  dragged = selected.value.includes(f.path) ? [...selected.value] : [f.path];
  e.dataTransfer.effectAllowed = 'move';
  e.dataTransfer.setData('text/plain', dragged.join('\n'));
}
async function onDrop(folder) {
  dropTarget.value = null;
  if (!dragged.length || !can('media.edit')) return;
  const ok = await confirmDialog({ title: `Move ${dragged.length} file(s) to "${folderLabel(folder)}"?`, message: 'Pages that use them are updated automatically.', confirmText: 'Move', tone: 'primary' });
  if (ok) router.post('/admin/media/transfer', { paths: dragged, to: folder, mode: 'move' }, { preserveScroll: true, onSuccess: () => { selected.value = []; } });
  dragged = [];
}

const statusTabs = [
  { key: '', label: 'All' },
  { key: 'used', label: 'In use' },
  { key: 'unused', label: 'Unused' },
];

const search = ref(props.filters?.search || '');
const selected = ref([]);
const detail = ref(null);
const uploading = ref(false);
const uploadError = ref('');
const copied = ref(false);

const unusedOnPage = computed(() => props.files.data.filter(f => !f.usage_count && !f.legacy).map(f => f.path));
const selectedUnused = computed(() => selected.value.filter((p) => unusedOnPage.value.includes(p)));

// ---- download: one file directly, several files or a folder as a .zip
const zipping = ref(false);
async function downloadZip(payload) {
  if (payload.paths?.length === 1) {
    window.location.href = `/admin/media/download?path=${encodeURIComponent(payload.paths[0])}`;
    return;
  }
  zipping.value = true;
  uploadError.value = '';
  try {
    const res = await axios.post('/admin/media/download-zip', payload, { responseType: 'blob' });
    const name = (res.headers['content-disposition'] || '').match(/filename="?([^";]+)"?/)?.[1] || 'media.zip';
    const url = URL.createObjectURL(res.data);
    const a = Object.assign(document.createElement('a'), { href: url, download: name });
    document.body.appendChild(a);
    a.click();
    a.remove();
    setTimeout(() => URL.revokeObjectURL(url), 5000);
    toast.success(`Download started: ${name}`);
  } catch (err) {
    let msg = 'Download failed.';
    try { msg = JSON.parse(await err.response?.data?.text?.())?.message || msg; } catch (e) { /* not JSON */ }
    toast.error([401, 419].includes(err.response?.status) ? 'Your session has expired. Reload the page and sign in again.' : msg);
  } finally {
    zipping.value = false;
  }
}

function go(patch, refresh = false) {
  const params = { ...props.filters, ...patch };
  Object.keys(params).forEach(k => { if (!params[k]) delete params[k]; });
  if (refresh) params.refresh = 1;
  selected.value = [];
  router.get('/admin/media', params, { preserveState: true, preserveScroll: true });
}

const isImage = f => ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif', 'svg'].includes(f.ext);

function formatSize(bytes) {
  if (!bytes) return '0 KB';
  if (bytes > 1048576) return (bytes / 1048576).toFixed(1) + ' MB';
  return Math.max(1, Math.round(bytes / 1024)) + ' KB';
}

function selectAllUnused() {
  selected.value = [...new Set([...selected.value, ...unusedOnPage.value])];
}

function destroy(paths) {
  router.post('/admin/media/delete', { paths }, {
    preserveScroll: true,
    onSuccess: () => { selected.value = []; detail.value = null; },
  });
}

async function deleteSelected() {
  if (await confirmDialog({ title: `Delete ${selectedUnused.value.length} unused file(s)?`, message: 'They are not used on any page. Files that are in use are never deleted. This cannot be undone.', confirmText: 'Delete files' })) destroy(selectedUnused.value);
}

async function deleteOne(f) {
  if (await confirmDialog({ title: `Delete ${f.name}?`, message: 'It is not used on any page. This cannot be undone.', confirmText: 'Delete file' })) destroy([f.path]);
}

async function upload(e) {
  const list = [...e.target.files];
  if (!list.length) return;
  uploading.value = true;
  uploadError.value = '';
  const fd = new FormData();
  for (const file of list) fd.append('files[]', await compressImage(file));
  if (currentFolder.value) fd.append('folder', currentFolder.value);
  try {
    const { data } = await axios.post('/admin/media', fd, { headers: { Accept: 'application/json' } });
    const n = data?.files?.length || list.length;
    toast.success(`${n} file${n === 1 ? '' : 's'} uploaded${currentFolder.value ? ` to ${currentFolder.value.split('/').pop()}` : ''}.`);
    router.reload({ preserveScroll: true });
  } catch (err) {
    if ([401, 419].includes(err.response?.status)) { toast.error('Your session has expired. Reload the page and sign in again, then upload.'); return; }
    uploadError.value = err.response?.data?.errors ? Object.values(err.response.data.errors).flat()[0] : (err.response?.data?.message || 'Upload failed.');
    toast.error(uploadError.value);
  } finally {
    uploading.value = false;
    e.target.value = '';
  }
}

const altForm = useForm({ path: '', alt: '' });
watch(detail, (f) => {
  if (f) { altForm.path = f.path; altForm.alt = f.alt || ''; copied.value = false; }
});
function saveAlt() {
  altForm.post('/admin/media/alt', { preserveScroll: true, onSuccess: () => { if (detail.value) detail.value.alt = altForm.alt; } });
}

async function copy(url) {
  try {
    await navigator.clipboard.writeText(window.location.origin + url);
    copied.value = true;
  } catch (e) { /* clipboard blocked */ }
}
</script>

<style scoped>
.fm-folder { display: flex; align-items: center; gap: 0.45rem; width: 100%; padding: 0.4rem 0.5rem; border-radius: 8px; font-size: 13px; cursor: pointer; color: var(--a-text-2); }
.fm-folder:hover { background: var(--a-panel-3); color: var(--a-text); }
.fm-folder.is-active { background: var(--a-accent-soft); color: var(--a-accent-text); font-weight: 600; }
.fm-folder.is-drop { outline: 2px dashed var(--a-accent); outline-offset: -2px; background: var(--a-accent-soft); }
</style>
