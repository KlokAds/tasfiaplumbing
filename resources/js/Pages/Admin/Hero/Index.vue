<template>
  <AdminLayout title="Homepage hero">
    <PageHeader title="Homepage hero" description="The first thing visitors see. One clear headline works better than a slider: the headline is the homepage's main heading (H1), so it should say what you do and where.">
      <a href="/" target="_blank" class="admin-btn-secondary">View homepage</a>
    </PageHeader>

    <div v-if="extraSlides" class="a-alert a-alert-info mb-5 items-center">
      <span class="flex-1">The old slider had {{ extraSlides }} more slide(s). The homepage only ever showed the first one, so the others are not used.</span>
      <button v-if="!readOnly" @click="removeExtra" class="admin-btn-secondary a-btn-sm shrink-0">Remove unused slides</button>
    </div>

    <form @submit.prevent="save" class="grid grid-cols-1 2xl:grid-cols-[1fr_1.1fr] gap-5 items-start">
      <!-- ============ Form ============ -->
      <div class="space-y-5">
        <section class="admin-card overflow-hidden">
          <header class="a-card-head"><h3 class="a-card-title">Text</h3></header>
          <div class="p-5 space-y-4">
            <div>
              <label class="admin-label">Small line above the headline</label>
              <input v-model="form.eyebrow" type="text" maxlength="80" class="admin-input" placeholder="e.g. Singapore · Licensed contractor" :disabled="readOnly" />
            </div>
            <div>
              <div class="flex justify-between items-baseline">
                <label class="admin-label">Headline (H1) *</label>
                <span :class="['text-[11px] a-mono', form.title.length > 70 ? 'a-text-warning' : 'a-subtle']">{{ form.title.length }}/70</span>
              </div>
              <input v-model="form.title" type="text" required maxlength="120" class="admin-input text-base font-semibold" placeholder="e.g. Plumbing services in Singapore" :disabled="readOnly" />
              <p v-if="form.errors.title" class="a-error">{{ form.errors.title }}</p>
              <p v-else class="a-help">Include your main service and “Singapore”. Keep it under 70 characters.</p>
            </div>
            <div>
              <label class="admin-label">Supporting text</label>
              <textarea v-model="form.subtitle" rows="3" maxlength="400" class="admin-input" placeholder="One or two sentences: what you fix, how fast, and why customers choose you." :disabled="readOnly"></textarea>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="admin-label">Button text</label>
                <input v-model="form.btn_name" type="text" maxlength="40" class="admin-input" placeholder="Get a free quote" :disabled="readOnly" />
              </div>
              <div>
                <label class="admin-label">Button link</label>
                <input v-model="form.btn_link" type="text" class="admin-input a-mono" placeholder="/contact" :disabled="readOnly" />
                <p v-if="form.errors.btn_link" class="a-error">{{ form.errors.btn_link }}</p>
              </div>
            </div>
          </div>
        </section>

        <section class="admin-card overflow-hidden">
          <header class="a-card-head">
            <div>
              <h3 class="a-card-title">Trust badges</h3>
              <p class="a-card-sub">Short, true claims shown in a strip under the hero. One per line, up to 6.</p>
            </div>
          </header>
          <div class="p-5">
            <textarea v-model="form.badges" rows="5" class="admin-input" :disabled="readOnly" placeholder="Licensed contractor&#10;Same-day response&#10;Islandwide service&#10;90-day workmanship warranty"></textarea>
            <p class="a-help">Only claims you can prove (licence numbers, warranty terms). Leave empty to hide the strip.</p>
          </div>
        </section>

        <section class="admin-card overflow-hidden">
          <header class="a-card-head"><h3 class="a-card-title">Layout</h3></header>
          <div class="p-5 space-y-4">
            <label class="a-toggle-row">
              <span>
                <span class="block text-sm font-semibold">Quote form next to the headline</span>
                <span class="block text-xs a-muted mt-0.5">Recommended. Visitors can ask for a price without scrolling.</span>
              </span>
              <input v-model="form.show_quote_form" type="checkbox" class="a-switch mt-0.5" :disabled="readOnly" />
            </label>
            <div>
              <label class="admin-label">Background photo <span class="font-normal a-subtle">(optional)</span></label>
              <div class="flex items-center gap-3">
                <label class="a-dropzone w-40 aspect-[16/9] shrink-0">
                  <img v-if="bgUrl" :src="bgUrl" class="w-full h-full object-cover" alt="" />
                  <span v-else class="text-xs">Choose photo</span>
                  <input type="file" accept="image/*" class="hidden" :disabled="readOnly" @change="pickImage" />
                </label>
            <LibraryButton v-if="!readOnly" class="mt-1.5" @pick="p => { form.img = p.file; form.remove_img = false; preview = p.url; }" />
                <div class="text-xs a-muted space-y-2">
                  <p>A real photo of your team at work, 1920×1080 or larger. It is darkened so the text stays readable.</p>
                  <button v-if="bgUrl && !readOnly" type="button" @click="clearImage" class="a-btn-ghost a-danger a-btn-sm">Remove photo</button>
                </div>
              </div>
            </div>
            <p class="text-xs a-muted">The numbers under the headline come from <Link href="/admin/home-static" class="font-semibold a-accent">Sections & counters</Link>.</p>
          </div>
        </section>

        <div v-if="!readOnly" class="flex justify-end">
          <button type="submit" :disabled="form.processing" class="admin-btn-primary">{{ form.processing ? 'Saving…' : 'Save hero' }}</button>
        </div>
      </div>

      <!-- ============ Live preview ============ -->
      <section class="admin-card overflow-hidden 2xl:sticky 2xl:top-20">
        <header class="a-card-head">
          <h3 class="a-card-title">Live preview</h3>
          <span class="a-badge">Updates as you type</span>
        </header>
        <div class="p-4">
          <div class="relative rounded-xl overflow-hidden text-white" style="background: #0c0f14">
            <img v-if="bgUrl" :src="bgUrl" class="absolute inset-0 w-full h-full object-cover opacity-30" alt="" />
            <div class="relative p-6 sm:p-8 grid gap-6" :class="form.show_quote_form ? 'md:grid-cols-[1.4fr_1fr]' : ''">
              <div>
                <p v-if="form.eyebrow" class="inline-flex items-center gap-2 text-[10px] font-semibold uppercase tracking-[0.12em] text-[#7cc4ff]"><span class="w-1.5 h-1.5 rounded-full bg-[#2f8cf0]"></span>{{ form.eyebrow }}</p>
                <h2 class="mt-3 text-2xl sm:text-3xl font-bold leading-tight tracking-tight">{{ form.title || 'Your headline' }}</h2>
                <p v-if="form.subtitle" class="mt-3 text-sm text-white/70 leading-relaxed">{{ form.subtitle }}</p>
                <div class="mt-5 flex flex-wrap gap-2">
                  <span class="px-4 py-2 rounded-md bg-[#1a66d2] text-sm font-semibold">{{ form.btn_name || 'Get a free quote' }}</span>
                  <span class="px-4 py-2 rounded-md border border-white/20 text-sm font-semibold">Call us</span>
                </div>
                <div v-if="counters.length" class="mt-6 pt-5 border-t border-white/10 grid grid-cols-2 sm:grid-cols-4 gap-3">
                  <div v-for="c in counters.slice(0, 4)" :key="c.id">
                    <p class="text-lg font-bold">{{ c.c_count }}</p>
                    <p class="text-[10px] text-white/55">{{ c.c_title }}</p>
                  </div>
                </div>
              </div>
              <div v-if="form.show_quote_form" class="rounded-lg bg-white text-[#111827] p-4 space-y-2 self-start">
                <p class="text-sm font-bold">Request a quote</p>
                <div v-for="i in 3" :key="i" class="h-7 rounded bg-[#f3f4f6]"></div>
                <div class="h-8 rounded bg-[#1a66d2]"></div>
              </div>
            </div>
          </div>
          <div v-if="badgeList.length" class="mt-2 rounded-lg border a-border px-4 py-2.5 flex flex-wrap justify-center gap-x-5 gap-y-1 text-xs a-muted">
            <span v-for="b in badgeList" :key="b" class="inline-flex items-center gap-1.5"><span class="a-accent">✓</span>{{ b }}</span>
          </div>
        </div>
      </section>
    </form>
  </AdminLayout>
</template>

<script setup>
import LibraryButton from '@/Components/Admin/LibraryButton.vue';
import { computed, ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import { compressImage } from '@/Composables/compressImage';
import { confirmDialog } from '@/Composables/useConfirm';
import { usePermissions } from '@/Composables/usePermissions';

const props = defineProps({ hero: Object, extraSlides: Number, counters: Array, settings: Object });
const { can } = usePermissions();
const readOnly = computed(() => !can('homepage.edit'));

const form = useForm({
  title: props.hero?.title || '',
  subtitle: props.hero?.subtitle || props.hero?.short_desc || '',
  btn_name: props.hero?.btn_name || '',
  btn_link: props.hero?.btn_link || '',
  img: null,
  remove_img: false,
  eyebrow: props.settings?.eyebrow || '',
  badges: props.settings?.badges || '',
  show_quote_form: props.settings?.show_quote_form ?? true,
});

const preview = ref(null);
const bgUrl = computed(() => (form.remove_img ? null : preview.value || (props.hero?.img ? '/' + props.hero.img : null)));
const badgeList = computed(() => form.badges.split('\n').map(b => b.trim()).filter(Boolean).slice(0, 6));

async function pickImage(e) {
  const file = await compressImage(e.target.files[0], { maxSide: 2400 });
  if (file) { form.img = file; form.remove_img = false; preview.value = URL.createObjectURL(file); }
}
function clearImage() {
  form.img = null;
  preview.value = null;
  form.remove_img = true;
}

function save() {
  form.post('/admin/hero', { forceFormData: true, preserveScroll: true, onSuccess: () => { preview.value = null; form.img = null; } });
}

async function removeExtra() {
  if (await confirmDialog({ title: `Remove ${props.extraSlides} unused slide(s)?`, message: 'They were never shown on the website. The hero above stays.', confirmText: 'Remove', tone: 'primary' })) {
    router.delete('/admin/hero/extra', { preserveScroll: true });
  }
}
</script>
