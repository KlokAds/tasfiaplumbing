<template>
  <AdminLayout title="About page">
    <PageHeader title="About page" description="Who you are, how long you have been doing this, licences and the people behind the work. This page is what Google and AI answers read to judge whether you can be trusted (E-E-A-T).">
      <a href="/about" target="_blank" class="admin-btn-secondary">View page</a>
    </PageHeader>

    <form @submit.prevent="submit" class="grid grid-cols-1 xl:grid-cols-[1fr_20rem] gap-5 items-start">
      <section class="admin-card p-5 space-y-5">
        <div>
          <label class="admin-label">Main heading (H1) *</label>
          <input v-model="form.title" type="text" required class="admin-input text-base" placeholder="e.g. About Tasfia Plumbing: licensed plumbers in Singapore" :disabled="readOnly" />
        </div>
        <div>
          <label class="admin-label">Tagline *</label>
          <input v-model="form.subtitle" type="text" required class="admin-input" placeholder="e.g. Licensed contractor · 3,000+ jobs across HDB, condo and landed homes" :disabled="readOnly" />
        </div>
        <div>
          <label class="admin-label">Your story *</label>
          <RichEditor v-model="form.short_desc" min-height="320px" />
        </div>
      </section>

      <aside class="space-y-4 a-side-sticky a-side-sticky-page">
        <section class="admin-card p-5">
          <p class="a-section-title mb-3">What a strong About page includes</p>
          <ul class="space-y-2 text-[13px]">
            <li v-for="c in checklist" :key="c.label" class="flex gap-2">
              <span :class="['mt-0.5 w-4 h-4 rounded-full shrink-0 flex items-center justify-center text-[10px] font-bold', c.ok ? 'a-tint-success a-text-success' : 'a-panel-3 a-subtle']">{{ c.ok ? '✓' : '·' }}</span>
              <span :class="c.ok ? 'a-muted' : ''">{{ c.label }}</span>
            </li>
          </ul>
        </section>

        <section class="admin-card p-5 space-y-4">
          <div v-for="img in images" :key="img.field">
            <label class="admin-label">{{ img.label }}</label>
            <label class="a-dropzone aspect-[16/10]">
              <img v-if="previews[img.field] || aboutContent?.[img.field]" :src="previews[img.field] || '/' + aboutContent[img.field]" class="w-full h-full object-cover" alt="" />
              <span v-else class="text-xs">Click to choose</span>
              <input type="file" accept="image/*" class="hidden" :disabled="readOnly" @change="e => pick(img.field, e)" />
            </label>
            <LibraryButton v-if="!readOnly" class="mt-1.5" @pick="p => { form[img.field] = p.file; previews[img.field] = p.url; }" />
            <p v-if="img.help" class="a-help">{{ img.help }}</p>
          </div>
        </section>

        <button v-if="!readOnly" type="submit" :disabled="form.processing" class="admin-btn-primary w-full">{{ form.processing ? 'Saving…' : 'Save About page' }}</button>
      </aside>
    </form>
  </AdminLayout>
</template>

<script setup>
import LibraryButton from '@/Components/Admin/LibraryButton.vue';
import { computed, reactive } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import RichEditor from '@/Components/Admin/RichEditor.vue';
import { compressImage } from '@/Composables/compressImage';
import { usePermissions } from '@/Composables/usePermissions';

const props = defineProps({ aboutContent: Object });
const { can } = usePermissions();
const readOnly = computed(() => !can('about.edit'));

const images = [
  { field: 'img_one', label: 'Main photo', help: 'Your team or owner on a real job site.' },
  { field: 'img_two', label: 'Second photo', help: 'Workshop, van, or a finished project.' },
  { field: 'a_bread_img', label: 'Page banner (top of the About page)', help: 'Wide photo, 1920 × 500 px or larger. Shown behind the page title with a dark overlay.' },
];
const previews = reactive({});

const form = useForm({
  title: props.aboutContent?.title || '',
  subtitle: props.aboutContent?.subtitle || '',
  short_desc: props.aboutContent?.short_desc || '',
  img_one: null,
  img_two: null,
  a_bread_img: null,
});

const text = computed(() => (form.short_desc || '').replace(/<[^>]*>/g, ' ').toLowerCase());
const checklist = computed(() => [
  { label: 'Year founded or years of experience', ok: /\b(since|founded|established)\s+(19|20)\d{2}\b|\b\d+\+?\s+years\b/.test(text.value) },
  { label: 'Licences or registrations (BCA, HDB, PUB, EMA…)', ok: /\b(bca|hdb|pub|ema|licen[cs]ed|registered|certified|bizsafe)\b/.test(text.value) },
  { label: 'Number of jobs or customers', ok: /\b\d[\d,]*\+?\s+(jobs|projects|customers|homes|clients)\b/.test(text.value) },
  { label: 'Areas you serve', ok: /\b(singapore|islandwide|hdb|condo|landed)\b/.test(text.value) },
  { label: 'Names of the people behind it', ok: /\b(founder|director|engineer|our team|manager|owner)\b/.test(text.value) },
  { label: 'At least 250 words', ok: text.value.split(/\s+/).filter(Boolean).length >= 250 },
  { label: 'Two real photos', ok: !!((previews.img_one || props.aboutContent?.img_one) && (previews.img_two || props.aboutContent?.img_two)) },
]);

async function pick(field, e) {
  const file = await compressImage(e.target.files[0]);
  if (file) { form[field] = file; previews[field] = URL.createObjectURL(file); }
}

function submit() {
  form.post('/admin/about', { forceFormData: true, preserveScroll: true });
}
</script>
