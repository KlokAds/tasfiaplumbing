<template>
  <Teleport to="body">
    <div v-if="show" class="admin-ui fixed inset-0 z-[60] bg-black/55 backdrop-blur-[2px] flex items-start justify-center p-4 overflow-y-auto">
      <div class="admin-card w-full max-w-4xl my-8 shadow-2xl">
        <div class="flex items-center justify-between px-6 pt-5 pb-3 border-b a-border">
          <div class="flex gap-1.5">
            <button type="button" @click="openLibrary" :class="tabClass('library')">Media library</button>
            <button type="button" @click="tab = 'upload'" :class="tabClass('upload')">Upload new</button>
          </div>
          <button type="button" @click="close" class="p-2 rounded-lg a-subtle hover:text-[var(--a-text-2)]" aria-label="Close">✕</button>
        </div>

        <div class="p-6 space-y-4">
          <div v-if="tab === 'upload'" class="space-y-4">
            <div class="flex flex-wrap items-center gap-2">
              <span class="text-xs font-semibold a-muted">Save to folder</span>
              <SelectBox v-model="uploadFolder" class="admin-input !w-72 max-w-full">
                <option value="">Default (Media / this month)</option>
                <option v-for="f in folders" :key="f" :value="f">{{ folderLabel(f) }}</option>
              </SelectBox>
            </div>
            <label class="flex flex-col items-center justify-center gap-2 border-2 border-dashed a-border-2 rounded-xl p-8 cursor-pointer hover:border-[var(--a-border-2)] transition"
              @dragover.prevent @drop.prevent="e => pickFile(e.dataTransfer.files[0])">
              <img v-if="uploadPreview" :src="uploadPreview" class="max-h-48 rounded-lg" alt="" />
              <span class="text-sm font-semibold a-muted">{{ uploadFile ? uploadFile.name : 'Click or drop an image here' }}</span>
              <span class="text-xs a-muted">JPG, PNG, WebP, GIF · max 8 MB. Name the file descriptively (e.g. water-heater-repair-tampines.jpg).</span>
              <input type="file" accept="image/jpeg,image/png,image/webp,image/gif,image/avif" class="hidden" @change="e => pickFile(e.target.files[0])" />
            </label>
          </div>

          <div v-else class="space-y-3">
            <div class="flex flex-col sm:flex-row gap-2">
              <SelectBox v-model="folder" class="admin-input sm:!w-72" @change="load(true)">
                <option value="">All folders</option>
                <option v-for="f in folders" :key="f" :value="f">{{ folderLabel(f) }}</option>
              </SelectBox>
              <input v-model="search" @input="debouncedLoad" type="text" placeholder="Search file names…" class="admin-input flex-1" />
            </div>
            <p v-if="!loading && !library.length" class="text-sm a-muted py-8 text-center">No images in this folder yet. Upload them in <a href="/admin/media" target="_blank" class="a-accent font-semibold">Media library</a> or use “Upload new”.</p>
            <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 gap-2 max-h-[22rem] overflow-y-auto">
              <button v-for="img in library" :key="img.path" type="button" @click="choose(img)"
                :class="['relative aspect-square rounded-lg overflow-hidden border-2 a-panel-2 ', selected?.path === img.path ? 'border-amber-500' : 'border-transparent hover:border-[var(--a-border-2)]']">
                <img :src="img.url" :alt="img.alt || ''" loading="lazy" class="w-full h-full object-cover" />
              </button>
            </div>
            <div class="flex justify-between items-center text-xs a-muted">
              <span>{{ loading ? 'Loading…' : `${library.length} images` }}</span>
              <button v-if="hasMore && !loading" type="button" @click="loadMore" class="font-semibold underline">Load more</button>
            </div>
          </div>

          <div v-if="mode === 'select' && (uploadFile || selected)" class="flex justify-end pt-2 border-t a-border">
            <button type="button" @click="insert" :disabled="busy" class="admin-btn-primary">{{ busy ? 'Uploading…' : 'Use this image' }}</button>
          </div>
          <div v-else-if="uploadFile || selected" class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-2 border-t a-border">
            <div class="sm:col-span-2">
              <label class="admin-label">Alt text * <span class="font-normal a-subtle">(describe the photo for Google and screen readers)</span></label>
              <input v-model="alt" type="text" maxlength="255" placeholder="e.g. Replaced water heater in a Tampines HDB bathroom" class="admin-input" />
            </div>
            <div class="flex items-end">
              <button type="button" @click="insert" :disabled="!alt.trim() || busy" class="admin-btn-primary w-full">{{ busy ? 'Uploading…' : 'Insert image' }}</button>
            </div>
          </div>
          <p v-if="error" class="text-sm a-text-danger">{{ error }}</p>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, watch } from 'vue';
import SelectBox from '@/Components/SelectBox.vue';
import axios from 'axios';
import { compressImage } from '@/Composables/compressImage';

// mode "insert" (editor: asks for alt text) or "select" (image fields: just pick a file)
const props = defineProps({ show: Boolean, mode: { type: String, default: 'insert' } });
const emit = defineEmits(['close', 'insert', 'select']);

const tab = ref('library');
const folders = ref([]);
const folder = ref('');
const uploadFolder = ref('');
const folderLabel = (path) => path.split('/').map((p, i) => (i === 0 ? 'Uploads' : p)).join(' / ');
const alt = ref('');
const error = ref('');
const busy = ref(false);

const uploadFile = ref(null);
const uploadPreview = ref(null);

const library = ref([]);
const search = ref('');
const page = ref(1);
const hasMore = ref(false);
const loading = ref(false);
const selected = ref(null);

watch(() => props.show, (open) => {
  if (open) reset();
});

function reset() {
  tab.value = 'library';
  if (!library.value.length) load(true);
  alt.value = '';
  error.value = '';
  uploadFile.value = null;
  uploadPreview.value = null;
  selected.value = null;
}

function tabClass(name) {
  return ['px-3 py-1.5 rounded-lg text-sm font-semibold', tab.value === name
    ? 'a-inverse'
    : 'a-muted  hover:bg-[var(--a-panel-2)] '];
}

function pickFile(file) {
  if (!file) return;
  uploadFile.value = file;
  uploadPreview.value = URL.createObjectURL(file);
  selected.value = null;
  if (!alt.value) alt.value = file.name.replace(/\.[^.]+$/, '').replace(/[-_]+/g, ' ');
}

async function load(reset = true) {
  loading.value = true;
  if (reset) page.value = 1;
  try {
    const { data } = await axios.get('/admin/media/browse', { params: { search: search.value, page: page.value, folder: folder.value } });
    library.value = reset ? data.data : [...library.value, ...data.data];
    hasMore.value = data.has_more;
    if (data.folders) folders.value = data.folders;
  } catch (e) {
    error.value = sessionMessage(e) || 'Could not load the media library.';
  } finally {
    loading.value = false;
  }
}

let timer = null;
function debouncedLoad() {
  clearTimeout(timer);
  timer = setTimeout(() => load(true), 300);
}

function openLibrary() {
  tab.value = 'library';
  uploadFile.value = null;
  if (!library.value.length) load(true);
}

function loadMore() {
  page.value++;
  load(false);
}

function choose(img) {
  selected.value = img;
  alt.value = img.alt || img.name.replace(/\.[^.]+$/, '').replace(/[-_]+/g, ' ').replace(/\s[a-z0-9]{5}$/i, '');
}

async function insert() {
  error.value = '';
  if (selected.value) {
    props.mode === 'select'
      ? emit('select', { path: selected.value.path, url: selected.value.url })
      : emit('insert', { src: selected.value.url, alt: alt.value.trim() });
    close();
    return;
  }
  busy.value = true;
  try {
    const fd = new FormData();
    fd.append('files[]', await compressImage(uploadFile.value));
    fd.append('alt', alt.value.trim());
    if (uploadFolder.value) fd.append('folder', uploadFolder.value);
    const { data } = await axios.post('/admin/media', fd, { headers: { Accept: 'application/json' } });
    props.mode === 'select'
      ? emit('select', { path: data.files[0].path, url: data.files[0].url })
      : emit('insert', { src: data.files[0].url, alt: alt.value.trim() });
    library.value = [];
    close();
  } catch (e) {
    error.value = sessionMessage(e) || (e.response?.data?.errors ? Object.values(e.response.data.errors).flat()[0] : (e.response?.data?.message || 'Upload failed. Check your connection and try again.'));
  } finally {
    busy.value = false;
  }
}

// 401/419 = signed out or the page was open too long: say so plainly instead of "Unauthenticated".
function sessionMessage(e) {
  return [401, 419].includes(e?.response?.status)
    ? 'You have been signed out (session expired). Save your text somewhere, reload the page and sign in again.'
    : '';
}

function close() {
  emit('close');
}
</script>
