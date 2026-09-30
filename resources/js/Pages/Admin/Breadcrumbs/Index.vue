<template>
  <AdminLayout title="Page banners">
    <PageHeader title="Page banners" description="The heading and photo at the top of each listing page. The title is the page's H1, so name the topic plainly, e.g. “Renovation & repair services”." />

    <form @submit.prevent="submit">
      <p class="a-help mb-4">Photo: 1920 × 500 px or larger, a real job photo. Text is placed on a dark overlay, so any photo stays readable.</p>

      <div class="grid grid-cols-1 md:grid-cols-2 2xl:grid-cols-3 gap-5">
        <section v-for="b in banners" :key="b.key" class="admin-card overflow-hidden flex flex-col">
          <!-- Live preview: how the banner looks on the page -->
          <label :class="['relative block aspect-[16/6] overflow-hidden group', readOnly ? '' : 'cursor-pointer']" :title="readOnly ? '' : 'Click to change the photo'">
            <img v-if="imageOf(b)" :src="imageOf(b)" class="absolute inset-0 w-full h-full object-cover" alt="" />
            <div v-else class="absolute inset-0 a-panel-3"></div>
            <div class="absolute inset-0 bg-gradient-to-r from-black/80 via-black/55 to-black/25"></div>
            <div class="relative h-full flex flex-col justify-end p-4">
              <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-orange-300">{{ b.label }}</p>
              <p class="text-white text-lg font-bold leading-tight line-clamp-2">{{ form[b.name] || b.placeholder }}</p>
            </div>
            <span v-if="!readOnly" class="absolute top-2.5 right-2.5 inline-flex items-center gap-1.5 rounded-lg bg-black/55 text-white text-[11px] font-semibold px-2.5 py-1.5 backdrop-blur-sm opacity-90 group-hover:opacity-100">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.6-4.6a2 2 0 012.8 0L16 16m-2-2l1.6-1.6a2 2 0 012.8 0L20 14M14 8h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
              {{ imageOf(b) ? 'Change photo' : 'Add photo' }}
            </span>
            <input type="file" accept="image/*" class="hidden" :disabled="readOnly" @change="e => pick(b.image, e)" />
          </label>

          <div class="p-4 space-y-2 flex-1">
            <LibraryButton v-if="!readOnly" @pick="p => { form[b.image] = p.file; previews[b.image] = p.url; }" />
            <div class="flex items-center justify-between gap-3">
              <label class="admin-label !mb-0" :for="`bn-${b.key}`">Banner title (H1)</label>
              <a :href="b.path" target="_blank" class="text-[11px] a-mono a-subtle a-hover-text">{{ b.path }} ↗</a>
            </div>
            <input :id="`bn-${b.key}`" v-model="form[b.name]" type="text" class="admin-input" :placeholder="b.placeholder" :disabled="readOnly" />
            <p v-if="previews[b.image]" class="text-[11px] a-text-warning">New photo chosen: save to apply.</p>
          </div>
        </section>
      </div>

      <div v-if="!readOnly" class="sticky bottom-4 mt-5 flex justify-end">
        <button type="submit" :disabled="form.processing" class="admin-btn-primary shadow-lg">{{ form.processing ? 'Saving…' : 'Save banners' }}</button>
      </div>
    </form>
  </AdminLayout>
</template>

<script setup>
import LibraryButton from '@/Components/Admin/LibraryButton.vue';
import { computed, reactive } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import { compressImage } from '@/Composables/compressImage';
import { usePermissions } from '@/Composables/usePermissions';

const props = defineProps({ breadcrumb: Object });
const { can } = usePermissions();
const readOnly = computed(() => !can('banners.edit'));

const banners = [
  { key: 's', label: 'Services', path: '/services', name: 's_bread_name', image: 's_bread_image', placeholder: 'Plumbing services' },
  { key: 'p', label: 'Projects', path: '/projects', name: 'p_bread_name', image: 'p_bread_image', placeholder: 'Recent projects in Singapore' },
  { key: 'b', label: 'Articles', path: '/blogs', name: 'b_bread_name', image: 'b_bread_image', placeholder: 'Guides & cost advice' },
  { key: 'f', label: 'Reviews', path: '/reviews', name: 'f_bread_name', image: 'f_bread_image', placeholder: 'Customer reviews' },
  { key: 'c', label: 'Contact', path: '/contact', name: 'c_bread_name', image: 'c_bread_image', placeholder: 'Contact us' },
];

const previews = reactive({});
const imageOf = (b) => previews[b.image] || (props.breadcrumb?.[b.image] ? '/' + props.breadcrumb[b.image] : null);
const form = useForm(Object.fromEntries(banners.flatMap(b => [[b.name, props.breadcrumb?.[b.name] || ''], [b.image, null]])));

async function pick(field, e) {
  const file = await compressImage(e.target.files[0], { maxSide: 2400 });
  if (file) { form[field] = file; previews[field] = URL.createObjectURL(file); }
}

function submit() {
  form.post('/admin/breadcrumbs', { forceFormData: true, preserveScroll: true });
}
</script>
