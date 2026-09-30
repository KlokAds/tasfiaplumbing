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
    <div>
      <label class="label" :for="id('msg')">Details *</label>
      <textarea :id="id('msg')" v-model="form.message" rows="4" required class="input" placeholder="What is the problem, where is it (HDB / condo / landed), and when do you need it done?"></textarea>
      <p v-if="errors.message" class="mt-1 text-[13px] text-[#dc2626]">{{ errors.message }}</p>
    </div>

    <button type="submit" :disabled="form.processing" class="btn btn-primary w-full btn-lg">
      <svg v-if="waUrl" class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M12.04 2.6C6.8 2.6 2.56 6.83 2.56 12.04c0 1.8.5 3.5 1.45 5.03l-.96 3.49 3.58-.94a9.45 9.45 0 004.82 1.32h.01c5.25 0 9.5-4.18 9.5-9.41a9.42 9.42 0 00-2.78-6.71 9.4 9.4 0 00-6.72-2.78z" /></svg>
      {{ form.processing ? 'Sending…' : submitLabel }}
    </button>
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
const form = useForm({ name: '', phone: '', email: '', subject: '', message: '', _hp: '' });
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
  return !Object.keys(clientErrors).length;
}

function submit() {
  if (!validate()) return;
  const data = { ...form.data() };
  const link = waUrl.value ? whatsappLink(data) : null;

  // One click does both: the request is saved and emailed, and WhatsApp opens with the
  // message written. The tab has to open inside the click or the browser blocks it.
  let win = null;
  if (link) {
    win = window.open('about:blank', '_blank');
  }

  form.transform(d => ({ ...d, subject: d.subject || props.subject || 'Website enquiry' }))
    .post('/messages', {
      preserveScroll: true,
      onSuccess: () => {
        sent.value = { name: data.name, whatsapp: link };
        if (window.__trackEvents) {
          (window.dataLayer = window.dataLayer || []).push({ event: 'generate_lead', form_name: props.idPrefix, service: data.subject || props.subject, page_path: location.pathname });
        }
        form.reset();
        if (link) {
          if (win && !win.closed) {
            win.opener = null;
            win.location.href = link;
          } else {
            window.location.href = link; // pop-up blocked: go to WhatsApp in this tab
          }
        }
      },
      onError: () => { if (win && !win.closed) win.close(); },
    });
}
</script>
