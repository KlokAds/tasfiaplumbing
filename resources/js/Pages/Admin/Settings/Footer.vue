<template>
  <AdminLayout title="Logo, footer & tracking">
    <PageHeader title="Logo, footer & tracking" description="Brand logos, the footer text and your Google tracking codes." />

    <form @submit.prevent="submit" class="space-y-5">
      <section class="admin-card overflow-hidden">
        <header class="a-card-head"><h3 class="a-card-title">Logos</h3></header>
        <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-5">
          <div v-for="l in logos" :key="l.field">
            <label class="admin-label">{{ l.label }}</label>
            <label :class="['a-dropzone h-32', l.dark && '!bg-[#0c0f14]']">
              <img :src="previews[l.field] || (footer?.[l.field] ? '/' + footer[l.field] : '/logo.png')" alt="" class="max-h-20 max-w-[70%] object-contain" />
              <input type="file" accept="image/*" class="hidden" :disabled="readOnly" @change="e => pick(l.field, e)" />
            </label>
            <LibraryButton v-if="!readOnly" class="mt-1.5" @pick="p => { form[l.field] = p.file; previews[l.field] = p.url; }" />
            <p class="a-help">{{ l.help }}</p>
          </div>
        </div>
      </section>

      <section class="admin-card overflow-hidden">
        <header class="a-card-head"><h3 class="a-card-title">Footer</h3></header>
        <div class="p-5 space-y-4">
          <div>
            <label class="admin-label">Short company summary</label>
            <textarea v-model="form.f_short_desc" rows="3" class="admin-input" :disabled="readOnly" placeholder="One or two sentences: what you do, where, and since when."></textarea>
          </div>
          <div>
            <p class="admin-label">Copyright line</p>
            <p class="rounded-lg a-panel-2 border a-border px-3 py-2.5 text-sm">{{ copyright }}</p>
            <p class="a-help">Written automatically and updated every January. Set the start year under <Link href="/admin/settings/business" class="a-accent font-semibold">Identity & hours → Year founded</Link>; the company name comes from Legal company name.</p>
          </div>
        </div>
      </section>

      <section class="admin-card overflow-hidden">
        <header class="a-card-head">
          <div>
            <h3 class="a-card-title">Google tracking</h3>
            <p class="a-card-sub">Use one: Tag Manager (recommended, it can load Analytics for you) or Analytics directly.</p>
          </div>
        </header>
        <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="admin-label">Google Tag Manager ID</label>
            <input v-model="form.g_tag" type="text" placeholder="GTM-XXXXXXX" class="admin-input a-mono" :disabled="readOnly" />
            <p v-if="form.errors.g_tag" class="a-error">{{ form.errors.g_tag }}</p>
            <p v-else-if="form.g_tag && !/^GTM-[A-Z0-9]+$/.test(form.g_tag)" class="a-error">Should look like GTM-ABC1234.</p>
          </div>
          <div>
            <label class="admin-label">Google Analytics 4 ID</label>
            <input v-model="form.g_a_tag" type="text" placeholder="G-XXXXXXXXXX" class="admin-input a-mono" :disabled="readOnly" />
            <p v-if="form.errors.g_a_tag" class="a-error">{{ form.errors.g_a_tag }}</p>
            <p v-else-if="form.g_a_tag && !/^G-[A-Z0-9]+$/.test(form.g_a_tag)" class="a-error">Should look like G-ABC123XYZ.</p>
            <p v-else-if="form.g_tag" class="a-help">Not needed when Tag Manager is set: add GA4 inside Tag Manager instead.</p>
          </div>
          <p v-if="form.g_tag && form.g_a_tag" class="md:col-span-2 a-alert a-alert-warning text-xs">Both are set. The site only loads Tag Manager in that case; put GA4 inside Tag Manager.</p>
          <label class="md:col-span-2 a-toggle-row">
            <span>
              <span class="block text-sm font-semibold">Send conversion events</span>
              <span class="block text-xs a-muted mt-0.5">Pushes <span class="a-mono">generate_lead</span> (form sent), <span class="a-mono">click_call</span>, <span class="a-mono">click_whatsapp</span> and <span class="a-mono">click_email</span> to the dataLayer, so you can use them as conversions in GA4 and Google Ads.</span>
            </span>
            <input v-model="form.events" type="checkbox" class="a-switch mt-0.5" :disabled="readOnly" />
          </label>
          <p class="md:col-span-2 a-help">Tracking loads after the visitor's first scroll or tap (or 4 seconds), which keeps PageSpeed scores high. Visits are still counted.</p>
        </div>
      </section>

      <section class="admin-card overflow-hidden">
        <header class="a-card-head">
          <div>
            <h3 class="a-card-title">Live chat (Tawk.to)</h3>
            <p class="a-card-sub">Free chat widget. Messages arrive in the Tawk.to app on your phone.</p>
          </div>
          <span :class="['a-badge', form.tawk_enabled && form.tawk_id ? 'a-badge-success' : '']">{{ form.tawk_enabled && form.tawk_id ? 'On' : 'Off' }}</span>
        </header>
        <div class="p-5 space-y-4">
          <label class="a-toggle-row">
            <span><span class="block text-sm font-semibold">Show the chat on the website</span><span class="block text-xs a-muted mt-0.5">The WhatsApp button moves to the left so they do not overlap.</span></span>
            <input v-model="form.tawk_enabled" type="checkbox" class="a-switch mt-0.5" :disabled="readOnly" />
          </label>
          <div>
            <label class="admin-label">Property / widget ID</label>
            <input v-model="form.tawk_id" type="text" class="admin-input a-mono" placeholder="64a1b2c3d4e5f6a7b8c9d0e1/1h2abc3de" :disabled="readOnly" @blur="cleanTawk" />
            <p v-if="form.errors.tawk_id" class="a-error">{{ form.errors.tawk_id }}</p>
            <p v-else class="a-help">Tawk.to → Administration → Chat widget → copy the widget code. Paste the whole code or just the part after <span class="a-mono">embed.tawk.to/</span>; it is cleaned up for you.</p>
          </div>
        </div>
      </section>

      <div v-if="!readOnly" class="flex justify-end">
        <button type="submit" :disabled="form.processing" class="admin-btn-primary">{{ form.processing ? 'Saving…' : 'Save' }}</button>
      </div>
    </form>
  </AdminLayout>
</template>

<script setup>
import LibraryButton from '@/Components/Admin/LibraryButton.vue';
import { computed, reactive } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import { usePermissions } from '@/Composables/usePermissions';

const props = defineProps({ footer: Object, tracking: { type: Object, default: () => ({}) }, copyright: String });
const { can } = usePermissions();
const readOnly = computed(() => !can('settings.edit'));

const logos = [
  { field: 'main_logo', label: 'Header logo', help: 'Shown on a light background. PNG or SVG, at least 400 px wide.' },
  { field: 'f_logo', label: 'Footer logo', help: 'Shown on the dark footer. Use a white or light version.', dark: true },
];
const previews = reactive({});

const form = useForm({
  f_short_desc: props.footer?.f_short_desc || '',
  c_text: props.footer?.c_text || '',
  main_logo: null,
  f_logo: null,
  g_tag: props.footer?.g_tag || '',
  g_a_tag: props.footer?.g_a_tag || '',
  tawk_enabled: !!props.tracking?.tawk_enabled,
  tawk_id: props.tracking?.tawk_id || '',
  events: props.tracking?.events ?? true,
});

// Accept the full embed snippet or URL and keep only "propertyId/widgetId".
function cleanTawk() {
  const m = (form.tawk_id || '').match(/embed\.tawk\.to\/([a-z0-9]+\/[a-z0-9]+)/i);
  if (m) form.tawk_id = m[1];
  form.tawk_id = form.tawk_id.trim();
}

function pick(field, e) {
  const file = e.target.files[0];
  if (file) { form[field] = file; previews[field] = URL.createObjectURL(file); }
}

function submit() {
  cleanTawk();
  form.post('/admin/settings/footer', { forceFormData: true, preserveScroll: true });
}
</script>
