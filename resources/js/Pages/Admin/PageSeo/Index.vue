<template>
  <AdminLayout title="Page SEO">
    <PageHeader title="Page SEO" description="Search titles and descriptions for the fixed pages (home, about, contact and the listing pages). Services, locations and articles have their own SEO box inside their editor." />

    <StickyBar>
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
      <div class="admin-card p-4"><p class="text-xs font-semibold a-muted">Pages</p><p class="mt-1 text-2xl font-bold">{{ pages.length }}</p></div>
      <div class="admin-card p-4"><p class="text-xs font-semibold a-muted">Average score</p><p class="mt-1 text-2xl font-bold" :class="tone(avg)">{{ avg }}</p></div>
      <div class="admin-card p-4"><p class="text-xs font-semibold a-muted">Missing title or description</p><p class="mt-1 text-2xl font-bold">{{ missing }}</p></div>
      <div class="admin-card p-4"><p class="text-xs font-semibold a-muted">Hidden from Google</p><p class="mt-1 text-2xl font-bold">{{ hidden }}</p></div>
    </div>
    </StickyBar>

    <div class="admin-card overflow-hidden">
      <div class="overflow-x-auto">
        <table class="a-table">
          <thead><tr><th>Page</th><th>Search result preview</th><th class="w-24">Score</th><th></th></tr></thead>
          <tbody>
            <tr v-for="p in sorted" :key="p.key">
              <td class="whitespace-nowrap">
                <p class="font-semibold">{{ p.label }}</p>
                <a :href="p.path" target="_blank" class="text-xs a-subtle font-mono hover:underline">{{ p.path }}</a>
              </td>
              <td class="max-w-lg">
                <p class="serp-title text-sm font-medium truncate">{{ fullTitle(p) }}</p>
                <p class="text-xs a-muted line-clamp-2">{{ p.meta_desc || defaultDesc || '—' }}</p>
                <ul v-if="p.seo.issues.length" class="mt-1 space-y-0.5">
                  <li v-for="(i, n) in p.seo.issues" :key="n" class="flex items-start gap-1.5 text-[11px]">
                    <span :class="['mt-1 w-1.5 h-1.5 rounded-full shrink-0', i.level === 'error' ? 'bg-red-500' : 'bg-amber-500']"></span>
                    <span class="a-subtle">{{ i.message }}</span>
                  </li>
                </ul>
              </td>
              <td><span :class="['a-badge', p.seo.errors ? 'a-badge-danger' : badge(p.seo.score)]">{{ p.seo.score }}</span></td>
              <td class="text-right"><button @click="open(p)" class="admin-btn-secondary a-btn-sm">Edit</button></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <Modal :show="!!editing" :title="`${editing?.label} · SEO`" :subtitle="editing?.path" width="3xl" @close="editing = null">
      <form v-if="editing" @submit.prevent="save" class="space-y-5">
        <!-- Google preview -->
        <div class="rounded-xl border a-border p-4 bg-white">
          <p class="text-xs text-[#202124] truncate">{{ origin }}{{ editing.path }}</p>
          <p class="text-lg leading-snug text-[#1a0dab] truncate">{{ fullTitle({ ...editing, meta_title: form.meta_title }) }}</p>
          <p class="text-sm text-[#4d5156] line-clamp-2">{{ form.meta_desc || defaultDesc || 'Google will pick text from the page.' }}</p>
        </div>

        <div>
          <div class="flex justify-between"><label class="admin-label">Meta title</label><span :class="['text-[11px] font-mono', counter(fullTitle({ ...editing, meta_title: form.meta_title }).length, 30, 60)]">{{ fullTitle({ ...editing, meta_title: form.meta_title }).length }}/60</span></div>
          <input v-model="form.meta_title" type="text" maxlength="255" class="admin-input" :placeholder="editing.default_title" />
          <p class="text-[11px] a-subtle mt-1">Main keyword first, then location or benefit.<span v-if="titleSuffix"> “{{ titleSuffix }}” is added automatically.</span></p>
        </div>

        <div>
          <div class="flex justify-between"><label class="admin-label">Meta description</label><span :class="['text-[11px] font-mono', counter(form.meta_desc.length, 120, 160)]">{{ form.meta_desc.length }}/160</span></div>
          <textarea v-model="form.meta_desc" rows="3" maxlength="500" class="admin-input" placeholder="What the page offers, who it is for, and a reason to click (price from, same-day, licensed)."></textarea>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="admin-label">Focus keyword <span class="font-normal a-subtle">(for your checklist only)</span></label>
            <input v-model="form.focus_keyword" type="text" class="admin-input" placeholder="e.g. plumber singapore" />
            <p v-if="form.focus_keyword && !keywordInTitle" class="text-[11px] a-text-warning mt-1">The keyword is not in the title.</p>
          </div>
          <div>
            <label class="admin-label">Canonical URL <span class="font-normal a-subtle">(leave empty)</span></label>
            <input v-model="form.canonical" type="url" class="admin-input" :placeholder="origin + editing.path" />
            <p v-if="form.errors.canonical" class="a-error">{{ form.errors.canonical }}</p>
          </div>
        </div>

        <div>
          <label class="admin-label">Social share image <span class="font-normal a-subtle">(1200×630, shown on WhatsApp and Facebook)</span></label>
          <div class="flex items-center gap-3">
            <img v-if="form.og_image" :src="form.og_image" class="w-32 aspect-[1200/630] object-cover rounded-lg border a-border" alt="" />
            <button type="button" @click="picker = true" class="admin-btn-secondary a-btn-sm">{{ form.og_image ? 'Change' : 'Choose image' }}</button>
            <button v-if="form.og_image" type="button" @click="form.og_image = ''" class="a-btn-ghost a-btn-sm">Remove</button>
          </div>
        </div>

        <label class="flex items-start gap-3 text-sm">
          <input v-model="form.noindex" type="checkbox" class="mt-0.5 rounded" />
          <span><span class="font-semibold">Hide from Google (noindex)</span><span class="block text-xs a-muted">Only for thin pages like privacy or terms. Never for home, services or contact.</span></span>
        </label>

        <div class="flex justify-end gap-2 pt-4 border-t a-border">
          <button type="button" @click="editing = null" class="admin-btn-secondary">Cancel</button>
          <button type="submit" :disabled="form.processing" class="admin-btn-primary">Save</button>
        </div>
      </form>
    </Modal>

    <MediaPicker :show="picker" @close="picker = false" @insert="img => { form.og_image = img.src; }" />
  </AdminLayout>
</template>

<script setup>
import StickyBar from '@/Components/Admin/StickyBar.vue';
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import Modal from '@/Components/Admin/Modal.vue';
import MediaPicker from '@/Components/Admin/MediaPicker.vue';

const props = defineProps({ pages: Array, titleSuffix: String, defaultDesc: String, brand: String });

const origin = typeof window !== 'undefined' ? window.location.origin : '';
const sorted = computed(() => [...props.pages].sort((a, b) => a.seo.score - b.seo.score));
const avg = computed(() => (props.pages.length ? Math.round(props.pages.reduce((s, p) => s + p.seo.score, 0) / props.pages.length) : 0));
const missing = computed(() => props.pages.filter(p => !p.meta_title || !p.meta_desc).length);
const hidden = computed(() => props.pages.filter(p => p.noindex).length);

const badge = s => (s >= 80 ? 'a-badge-success' : s >= 50 ? 'a-badge-warning' : 'a-badge-danger');
const tone = s => (s >= 80 ? 'a-text-success' : s >= 50 ? 'a-text-warning' : 'a-text-danger');
const counter = (n, min, max) => (n === 0 ? 'a-subtle' : n > max ? 'a-text-danger' : n < min ? 'a-text-warning' : 'a-text-success');

function fullTitle(p) {
  const t = (p.meta_title || '').trim() || p.default_title;
  // Same rule as the server (PageMeta): the suffix is added unless the title already names the brand.
  if (!props.titleSuffix || t.toLowerCase().includes((props.brand || '').toLowerCase())) return t;
  return t + props.titleSuffix;
}

const editing = ref(null);
const picker = ref(false);
const form = useForm({ meta_title: '', meta_desc: '', focus_keyword: '', og_image: '', canonical: '', noindex: false });
const keywordInTitle = computed(() => (form.meta_title || editing.value?.default_title || '').toLowerCase().includes(form.focus_keyword.toLowerCase().trim()));

function open(p) {
  form.clearErrors();
  form.defaults({
    meta_title: p.meta_title || '', meta_desc: p.meta_desc || '', focus_keyword: p.focus_keyword || '',
    og_image: p.og_image || '', canonical: p.canonical || '', noindex: !!p.noindex,
  });
  form.reset();
  editing.value = p;
}

function save() {
  form.put(`/admin/page-seo/${editing.value.key}`, { preserveScroll: true, onSuccess: () => { editing.value = null; } });
}
</script>
