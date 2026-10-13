<template>
  <div v-if="sent" class="text-center py-6" role="status">
    <span class="mx-auto w-12 h-12 rounded-full bg-[#16a34a] text-white text-xl flex items-center justify-center">✓</span>
    <p class="h-card mt-4">Thank you, {{ sent.name.split(' ')[0] }}!</p>
    <p class="mt-1.5 text-[15px] s-muted">We have your request and usually reply the same working day.</p>
    <a v-if="sent.whatsapp" :href="sent.whatsapp" target="_blank" rel="noopener" class="btn btn-whatsapp w-full mt-5">Open WhatsApp</a>
    <button type="button" @click="sent = null" class="mt-3 text-[14px] link">Send another request</button>
  </div>

  <form v-else @submit.prevent="submit" class="space-y-3.5" novalidate>
    <input v-model="form._hp" type="text" class="hidden" tabindex="-1" autocomplete="off" aria-hidden="true" />
    <div>
      <label class="label" :for="id('name')">Your name *</label>
      <input :id="id('name')" v-model="form.name" type="text" required autocomplete="name" class="input" placeholder="e.g. David Tan" />
      <p v-if="errors.name" class="mt-1 text-[13px] text-[#dc2626]">{{ errors.name }}</p>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
      <div>
        <label class="label" :for="id('phone')">Phone / WhatsApp</label>
        <input :id="id('phone')" v-model="form.phone" type="tel" autocomplete="tel" class="input" placeholder="+65 8xxx xxxx" />
      </div>
      <div>
        <label class="label" :for="id('email')">Email *</label>
        <input :id="id('email')" v-model="form.email" type="email" required autocomplete="email" class="input" placeholder="you@email.com" />
        <p v-if="errors.email" class="mt-1 text-[13px] text-[#dc2626]">{{ errors.email }}</p>
      </div>
    </div>
    <div v-if="services.length">
      <label class="label" :for="id('service')">What do you need?</label>
      <SelectBox :id="id('service')" v-model="form.subject" class="input">
        <option value="">Choose a service (optional)</option>
        <option v-for="s in services" :key="s.id" :value="s.name">{{ s.name }}</option>
        <option value="Something else">Something else</option>
      </SelectBox>
    </div>
    <!-- Free site visit or a price from photos -->
    <div>
      <p class="label">How can we help?</p>
      <div class="grid grid-cols-2 gap-2" role="radiogroup" aria-label="How can we help?">
        <button v-for="o in HELP" :key="o.key" type="button" role="radio" :aria-checked="form.help === o.key" @click="form.help = o.key"
          :class="['rounded-xl border px-3 py-2.5 text-left transition-colors', form.help === o.key ? 'border-[var(--s-accent)] ring-2 ring-[var(--s-accent)]/30' : 's-border hover:border-[var(--s-accent)]']">
          <span class="block text-[15px] font-semibold s-heading">{{ o.title }}</span>
          <span class="block text-[13.5px] s-muted">{{ o.note }}</span>
        </button>
      </div>
    </div>
    <div v-if="form.help === 'visit'" class="grid grid-cols-1 sm:grid-cols-2 gap-3 rounded-xl border s-border p-3">
      <div>
        <label class="label" :for="id('date')">Preferred day *</label>
        <input :id="id('date')" v-model="form.visit_date" type="date" :min="today" :max="lastDay" class="input" />
        <p v-if="errors.visit_date" class="mt-1 text-sm text-[#dc2626]">{{ errors.visit_date }}</p>
      </div>
      <div>
        <label class="label" :for="id('time')">Preferred time</label>
        <select :id="id('time')" v-model="form.visit_time" class="input">
          <option v-for="s in SLOTS" :key="s.key" :value="s.key">{{ s.label }}</option>
        </select>
      </div>
      <div>
        <label class="label" :for="id('postal')">Postal code</label>
        <input :id="id('postal')" v-model="form.postal_code" type="text" inputmode="numeric" maxlength="6" autocomplete="postal-code" class="input" placeholder="e.g. 520123" />
        <p v-if="errors.postal_code" class="mt-1 text-sm text-[#dc2626]">{{ errors.postal_code }}</p>
      </div>
      <div>
        <p class="label">Property</p>
        <div class="flex flex-wrap gap-1.5">
          <button v-for="h in HOMES" :key="h" type="button" @click="form.property = form.property === h ? '' : h"
            :class="['rounded-full border px-3 py-1.5 text-[14px]', form.property === h ? 'border-[var(--s-accent)] s-heading font-semibold' : 's-border s-muted']">{{ h }}</button>
        </div>
      </div>
      <p class="sm:col-span-2 text-[13.5px] s-muted">The visit is free. We visit from 8am to 10pm and confirm the time with you on WhatsApp.</p>
    </div>

    <!-- Photos of the job (optional) -->
    <div>
      <p class="label">Photos <span class="font-normal s-muted">(optional, up to 3)</span></p>
      <div class="flex flex-wrap items-center gap-2">
        <div v-for="(ph, i) in photos" :key="ph.url" class="relative h-16 w-16 overflow-hidden rounded-lg border s-border">
          <img :src="ph.url" alt="" class="h-full w-full object-cover" />
          <button type="button" class="absolute right-0.5 top-0.5 flex h-5 w-5 items-center justify-center rounded-full bg-black/70 text-[13px] leading-none text-white" :aria-label="`Remove photo ${i + 1}`" @click="removePhoto(i)">×</button>
        </div>
        <label v-if="photos.length < 3" class="flex h-16 w-16 cursor-pointer flex-col items-center justify-center rounded-lg border border-dashed s-border text-[12.5px] s-muted hover:border-[var(--s-accent)]">
          <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7h3l2-3h8l2 3h3v12H3zM12 17a4 4 0 100-8 4 4 0 000 8z" /></svg>
          Add
          <input type="file" accept="image/*" multiple class="sr-only" @change="addPhotos" />
        </label>
      </div>
      <p v-if="errors.photos" class="mt-1 text-sm text-[#dc2626]">{{ errors.photos }}</p>
    </div>
    <div>
      <label class="label" :for="id('msg')">Details *</label>
      <textarea :id="id('msg')" v-model="form.message" rows="4" required class="input" placeholder="What is the problem, where is it (HDB / condo / landed), and when do you need it done?"></textarea>
      <p v-if="errors.message" class="mt-1 text-[13px] text-[#dc2626]">{{ errors.message }}</p>
    </div>

    <button type="submit" :disabled="form.processing" class="btn btn-primary w-full btn-lg">
      <svg v-if="waUrl" class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M12.04 2.6C6.8 2.6 2.56 6.83 2.56 12.04c0 1.8.5 3.5 1.45 5.03l-.96 3.49 3.58-.94a9.45 9.45 0 004.82 1.32h.01c5.25 0 9.5-4.18 9.5-9.41a9.42 9.42 0 00-2.78-6.71 9.4 9.4 0 00-6.72-2.78z" /></svg>
      {{ form.processing ? 'Sending…' : submitLabel }}
    </button>
    <p class="text-[14px] s-muted text-center font-medium">Free site visit · No obligation · We visit 8am – 10pm</p>
    <p class="text-[13px] s-subtle text-center">{{ waUrl ? 'We get your request by email and WhatsApp opens with your message ready. Just tap Send.' : 'We reply the same working day. Your details are only used to answer you.' }}</p>
  </form>
</template>

<script setup>
import SelectBox from '@/Components/SelectBox.vue';
import { computed, reactive, ref } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({
  services: { type: Array, default: () => [] },
  subject: { type: String, default: '' },
  submitLabel: { type: String, default: 'Get my free quote' },
  idPrefix: { type: String, default: 'q' },
});

const page = usePage();
const company = computed(() => page.props.company || {});
const id = k => `${props.idPrefix}_${k}`;
const form = useForm({ name: '', phone: '', email: '', subject: '', message: '', _hp: '', help: 'visit', visit_date: '', visit_time: 'any', postal_code: '', property: '' });

// Free site visit or a price from photos; the day and time are what the customer prefers (confirmed on WhatsApp).
const HELP = [
  { key: 'visit', title: 'Free site visit', note: 'We come and check' },
  { key: 'photos', title: 'Price from photos', note: 'No visit needed' },
];
const SLOTS = [
  { key: 'any', label: 'Any time (8am – 10pm)' },
  { key: 'morning', label: 'Morning (8am – 12pm)' },
  { key: 'afternoon', label: 'Afternoon (12pm – 5pm)' },
  { key: 'evening', label: 'Evening (5pm – 10pm)' },
];
const HOMES = ['HDB', 'Condo', 'Landed', 'Shop / office'];
const isoDay = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
const today = isoDay(new Date());
const lastDay = isoDay(new Date(Date.now() + 60 * 86400000));
const niceDay = (iso) => (iso ? new Date(iso + 'T12:00:00').toLocaleDateString('en-SG', { weekday: 'short', day: 'numeric', month: 'short' }) : '');

// Photos: large phone pictures are made smaller (1600px JPEG) before they are sent.
const photos = ref([]);
const shrink = (file) => new Promise((resolve) => {
  const url = URL.createObjectURL(file);
  const img = new Image();
  img.onload = () => {
    const k = Math.min(1, 1600 / Math.max(img.width, img.height));
    const c = document.createElement('canvas');
    c.width = Math.round(img.width * k); c.height = Math.round(img.height * k);
    c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
    c.toBlob((b) => resolve(b ? new File([b], file.name.replace(/\.[^.]+$/, '') + '.jpg', { type: 'image/jpeg' }) : file), 'image/jpeg', 0.82);
    URL.revokeObjectURL(url);
  };
  img.onerror = () => { URL.revokeObjectURL(url); resolve(file); }; // e.g. HEIC on a computer: sent as it is
  img.src = url;
});
async function addPhotos(e) {
  const files = [...(e.target.files || [])].filter(f => f.type.startsWith('image/') || /\.(heic|heif)$/i.test(f.name));
  e.target.value = '';
  for (const f of files.slice(0, 3 - photos.value.length)) {
    const small = await shrink(f);
    photos.value.push({ file: small, url: URL.createObjectURL(small) });
  }
}
function removePhoto(i) {
  URL.revokeObjectURL(photos.value[i].url);
  photos.value.splice(i, 1);
}
const sent = ref(null);
const clientErrors = reactive({});
const errors = computed(() => ({ ...clientErrors, ...form.errors }));

const waUrl = computed(() => {
  const w = company.value.whatsapp || '';
  if (!w) return null;
  return w.startsWith('http') ? w : 'https://wa.me/' + w.replace(/\D/g, '');
});

function whatsappLink(d) {
  const lines = [
    `Hi ${company.value.name || ''}, I would like a quote.`,
    '',
    `Name: ${d.name}`,
    d.phone && `Phone: ${d.phone}`,
    `Email: ${d.email}`,
    d.help === 'visit' && `Free site visit: ${niceDay(d.visit_date)}, ${SLOTS.find(s => s.key === d.visit_time)?.label || ''}`,
    d.help === 'visit' && !!d.postal_code && `Postal code: ${d.postal_code}`,
    d.help === 'visit' && !!d.property && `Property: ${d.property}`,
    d.help === 'photos' && 'Price from photos',
    photos.value.length > 0 && `Photos: ${photos.value.length} sent with the form`,
    (d.subject || props.subject) && `Service: ${d.subject || props.subject.replace(/^Quote request · /, '')}`,
    '',
    d.message,
  ].filter(l => l !== false && l !== undefined && l !== null);
  const base = waUrl.value;
  return base + (base.includes('?') ? '&' : '?') + 'text=' + encodeURIComponent(lines.join('\n'));
}

function validate() {
  Object.keys(clientErrors).forEach(k => delete clientErrors[k]);
  if (!form.name.trim()) clientErrors.name = 'Please enter your name.';
  if (!/^\S+@\S+\.\S+$/.test(form.email.trim())) clientErrors.email = 'Please enter a valid email.';
  if (!form.message.trim()) clientErrors.message = 'Please tell us a little about the job.';
  if (form.help === 'visit' && !form.visit_date) clientErrors.visit_date = 'Please choose a day for the visit.';
  if (form.postal_code && !/^\d{6}$/.test(form.postal_code.trim())) clientErrors.postal_code = 'A Singapore postal code has 6 digits.';
  return !Object.keys(clientErrors).length;
}

function submit() {
  if (!validate()) return;
  const data = { ...form.data() };
  const link = waUrl.value ? whatsappLink(data) : null;

  // One click does both: WhatsApp opens with the message written, and the request is saved and
  // emailed. WhatsApp opens straight away, inside the click: a blank tab sent there after the save
  // stays on about:blank in many phone and in-app browsers. The form is checked first (validate).
  let opened = false;
  if (link) {
    const win = window.open(link, '_blank');
    if (win) {
      opened = true;
      try { win.opener = null; } catch (e) { /* already on WhatsApp */ }
    }
  }

  form.transform(d => ({ ...d, photos: photos.value.map(p => p.file), subject: d.subject || props.subject || 'Website enquiry' }))
    .post('/messages', {
      preserveScroll: true,
      onSuccess: () => {
        sent.value = { name: data.name, whatsapp: link };
        if (window.__trackEvents) {
          (window.dataLayer = window.dataLayer || []).push({ event: 'generate_lead', form_name: props.idPrefix, service: data.subject || props.subject, page_path: location.pathname });
        }
        form.reset();
        photos.value.forEach(p => URL.revokeObjectURL(p.url));
        photos.value = [];
        if (link && !opened) {
          window.location.href = link; // pop-up blocked: go to WhatsApp in this tab
        }
      },
    });
}
</script>
