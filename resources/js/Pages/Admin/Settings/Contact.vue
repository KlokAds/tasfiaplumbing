<template>
  <AdminLayout title="Contact & social">
    <PageHeader title="Contact & social" description="Shown in the header, footer, contact page, WhatsApp button and the business schema. Use exactly the same phone and address as on your Google Business Profile." />

    <form @submit.prevent="submit" class="grid grid-cols-1 xl:grid-cols-[1fr_22rem] gap-5 items-start">
      <div class="space-y-5">
        <section class="admin-card overflow-hidden">
          <header class="a-card-head"><h3 class="a-card-title">How customers reach you</h3></header>
          <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="admin-label">Phone *</label>
              <input v-model="form.phone" type="tel" required placeholder="+65 8922 4022" class="admin-input" :disabled="readOnly" />
              <p class="a-help">Include +65. Used for click-to-call.</p>
            </div>
            <div>
              <label class="admin-label">Email *</label>
              <input v-model="form.email" type="email" required placeholder="hello@yourcompany.sg" class="admin-input" :disabled="readOnly" />
              <p class="a-help">Enquiry notifications also go here.</p>
            </div>
            <div class="md:col-span-2">
              <label class="admin-label">Address *</label>
              <input v-model="form.address" type="text" required placeholder="Block, street, #unit, Singapore postal code" class="admin-input" :disabled="readOnly" />
            </div>
            <div class="md:col-span-2">
              <label class="admin-label">Contact page heading</label>
              <input v-model="form.title" type="text" placeholder="e.g. Get a free quote" class="admin-input" :disabled="readOnly" />
            </div>
            <div class="md:col-span-2">
              <label class="admin-label">Google Maps embed link</label>
              <input v-model="form.map" type="text" placeholder="https://www.google.com/maps/embed?pb=…" class="admin-input a-mono text-xs" :disabled="readOnly" />
              <p class="a-help">Google Maps → your business → Share → Embed a map → copy only the <span class="a-mono">src="…"</span> link.</p>
            </div>
          </div>
        </section>

        <section class="admin-card overflow-hidden">
          <header class="a-card-head">
            <div>
              <h3 class="a-card-title">WhatsApp & social profiles</h3>
              <p class="a-card-sub">Filled profiles appear as icons in the footer and in Google's business info (schema sameAs). Leave a field empty to hide it. Paste the full link or just @username.</p>
            </div>
          </header>
          <div class="p-5 space-y-4">
            <div>
              <label class="admin-label">WhatsApp number</label>
              <input v-model="form.whatsapp" type="text" class="admin-input" :placeholder="form.phone ? `Empty = same as phone (${form.phone})` : '+65 9373 0360'" :disabled="readOnly" />
              <p v-if="form.errors.whatsapp" class="a-error">{{ form.errors.whatsapp }}</p>
              <p v-else class="a-help">Every "Get a free quote" button and the quote form open WhatsApp with this number: <span class="a-mono">{{ waPreview || '—' }}</span></p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div v-for="s in socials" :key="s.key">
                <label class="admin-label flex items-center justify-between">
                  <span>{{ s.label }}</span>
                  <a v-if="form.socials[s.key]?.startsWith('http')" :href="form.socials[s.key]" target="_blank" rel="noopener" class="text-[11px] a-accent font-semibold">Open ↗</a>
                </label>
                <input v-model="form.socials[s.key]" type="text" :placeholder="s.placeholder" class="admin-input" :disabled="readOnly" />
                <p v-if="form.errors[`socials.${s.key}`]" class="a-error">{{ form.errors[`socials.${s.key}`] }}</p>
              </div>
            </div>
          </div>
        </section>
      </div>

      <aside class="space-y-4 xl:sticky xl:top-20">
        <section class="admin-card p-5">
          <p class="a-section-title mb-3">Preview</p>
          <div class="rounded-xl border a-border p-4 space-y-2 text-sm">
            <p class="font-bold">{{ brand }}</p>
            <p class="a-muted">{{ form.address || 'Address' }}</p>
            <p><a :href="'tel:' + form.phone" class="a-accent font-semibold">{{ form.phone || 'Phone' }}</a></p>
            <p class="a-muted break-all">{{ form.email || 'Email' }}</p>
          </div>
          <div v-if="form.map?.startsWith('https://www.google.com/maps')" class="mt-3 rounded-xl overflow-hidden border a-border aspect-video">
            <iframe :src="form.map" class="w-full h-full" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="Map preview"></iframe>
          </div>
        </section>
        <button v-if="!readOnly" type="submit" :disabled="form.processing" class="admin-btn-primary w-full">{{ form.processing ? 'Saving…' : 'Save contact details' }}</button>
      </aside>
    </form>
  </AdminLayout>
</template>

<script setup>
import { computed } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import { usePermissions } from '@/Composables/usePermissions';

const props = defineProps({ contact: Object, socials: { type: Array, default: () => [] }, whatsapp: String });
const brand = computed(() => usePage().props.admin?.brand || 'Your business');
const { can } = usePermissions();
const readOnly = computed(() => !can('settings.edit'));


const form = useForm({
  title: props.contact?.title || '',
  email: props.contact?.email || '',
  phone: props.contact?.phone || '',
  address: props.contact?.address || '',
  map: props.contact?.map || '',
  whatsapp: props.whatsapp || '',
  socials: Object.fromEntries(props.socials.map((s) => [s.key, s.value || ''])),
});

// Same rule as the server: wa.me link, else WhatsApp number, else the phone (8 digits → +65).
const waPreview = computed(() => {
  const m = (form.whatsapp || '').match(/wa\.me\/(\d+)/);
  if (m) return 'wa.me/' + m[1];
  let d = (form.whatsapp || '').replace(/\D/g, '') || (form.phone || '').replace(/\D/g, '');
  if (!d) return '';
  if (d.length === 8) d = '65' + d;
  return 'wa.me/' + d;
});

function submit() {
  form.post('/admin/settings/contact', { preserveScroll: true });
}
</script>
