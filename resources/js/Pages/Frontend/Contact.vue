<template>
  <FrontendLayout>
    <PageHero :title="contact?.title || 'Contact us'" eyebrow="Get a free quote" compact
      :lead="texts.contact_lead" :crumbs="[{ label: 'Contact' }]" />

    <section class="py-10 sm:py-14 s-bg-alt">
      <div class="container-app grid grid-cols-1 lg:grid-cols-[22rem_1fr] gap-6 items-start">
        <!-- Quick ways to reach us -->
        <aside class="space-y-3 lg:sticky lg:top-24">
          <a v-if="hasWhatsapp" :href="wa()" target="_blank" rel="noopener" class="block rounded-[22px] p-6 bg-[#15803d] hover:bg-[#166534] text-white transition" style="box-shadow: var(--s-shadow)">
            <div class="flex items-center gap-4">
              <span class="w-12 h-12 rounded-xl bg-white/15 flex items-center justify-center shrink-0">
                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M12.04 2.6C6.8 2.6 2.56 6.83 2.56 12.04c0 1.8.5 3.5 1.45 5.03l-.96 3.49 3.58-.94a9.45 9.45 0 004.82 1.32h.01c5.25 0 9.5-4.18 9.5-9.41a9.42 9.42 0 00-2.78-6.71 9.4 9.4 0 00-6.72-2.78z" /></svg>
              </span>
              <span>
                <span class="block text-[13px] font-semibold text-white">Fastest reply</span>
                <span class="block text-[18px] font-bold">Chat on WhatsApp</span>
              </span>
            </div>
            <p v-if="texts.contact_whatsapp" class="mt-3 text-[14px] text-white/95">{{ texts.contact_whatsapp }}</p>
          </a>

          <a v-if="company.tel" :href="'tel:' + company.tel" class="card card-link p-4 flex items-center gap-4">
            <span class="icon-box"><svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.95.68l1.5 4.5a1 1 0 01-.5 1.2l-2.26 1.13a11 11 0 005.52 5.52l1.13-2.26a1 1 0 011.2-.5l4.5 1.5a1 1 0 01.68.95V19a2 2 0 01-2 2h-1C9.7 21 3 14.3 3 6V5z" /></svg></span>
            <span><span class="block text-[13px] s-subtle">Call us</span><span class="block text-[17px] font-bold s-heading">{{ company.phone }}</span></span>
          </a>

          <a v-if="company.email" :href="'mailto:' + company.email" class="card card-link p-4 flex items-center gap-4">
            <span class="icon-box"><svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.9 5.3a2 2 0 002.2 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg></span>
            <span class="min-w-0"><span class="block text-[13px] s-subtle">Email</span><span class="block text-[16px] font-bold s-heading truncate">{{ company.email }}</span></span>
          </a>
        </aside>

        <!-- Form -->
        <div class="card p-6 sm:p-8" style="box-shadow: var(--s-shadow)">
          <h2 class="h-section !text-[1.45rem]">Prefer a form?</h2>
          <p class="mt-1 mb-6 s-muted text-[15.5px]">Fill this in once: we get it by email, and WhatsApp opens with your message ready to send.</p>
          <QuoteForm :services="services" subject="Contact page enquiry" submit-label="Send my request" id-prefix="contact" />
        </div>
      </div>

      <!-- Address, hours, map -->
      <div class="container-app mt-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-[22rem_1fr] gap-6 items-stretch">
        <div class="card p-6 space-y-5">
          <div v-if="company.address">
            <p class="text-[13px] font-bold uppercase tracking-[0.1em] s-subtle">Address</p>
            <p class="mt-1.5 s-text leading-relaxed">{{ company.address }}</p>
            <a v-if="directions" :href="directions" target="_blank" rel="noopener" class="mt-2 inline-block text-[14px] link">Get directions →</a>
          </div>
          <div v-if="Object.values(hours).some(Boolean)">
            <p class="text-[13px] font-bold uppercase tracking-[0.1em] s-subtle">Opening hours</p>
            <dl class="mt-2 space-y-1.5 text-[15px]">
              <div v-for="(v, day) in hours" :key="day" class="flex justify-between gap-4"><dt class="s-muted">{{ day }}</dt><dd class="font-semibold s-heading">{{ fmtHours(v) }}</dd></div>
            </dl>
            <p v-if="hoursNote" class="mt-2 text-[14px] s-subtle">{{ hoursNote }}</p>
          </div>
        </div>
        <div v-if="mapSrc" class="card overflow-hidden min-h-[18rem]">
          <!-- The map only loads when asked: Google Maps is heavy and slows the page. -->
          <iframe v-if="showMap" :src="mapSrc" class="w-full h-full min-h-[18rem] border-0" loading="lazy" referrerpolicy="no-referrer-when-downgrade" :title="`Map: ${company.name}`" allowfullscreen></iframe>
          <button v-else type="button" @click="showMap = true" class="w-full h-full min-h-[18rem] flex flex-col items-center justify-center gap-3 s-surface-2 hover:bg-[var(--s-bg-alt)] transition">
            <span class="icon-box !w-12 !h-12"><svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21s-7-6.2-7-11.5A7 7 0 0112 2.5a7 7 0 017 7C19 14.8 12 21 12 21zm0-9a2.5 2.5 0 100-5 2.5 2.5 0 000 5z" /></svg></span>
            <span class="font-semibold s-heading">Show map</span>
            <span class="text-[13.5px] s-muted">Loads Google Maps</span>
          </button>
        </div>
      </div>
    </section>
  </FrontendLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import FrontendLayout from '@/Layouts/FrontendLayout.vue';
import PageHero from '@/Components/Site/PageHero.vue';
import QuoteForm from '@/Components/Site/QuoteForm.vue';
import { useContact } from '@/Composables/useContact';

const props = defineProps({ contact: Object, services: { type: Array, default: () => [] }, hours: { type: Object, default: () => ({}) }, hoursNote: String });
const { company, wa, hasWhatsapp } = useContact();
const texts = computed(() => company.value.texts || {});
const showMap = ref(false);

// Only Google Maps embeds are shown (the field may contain a full <iframe> tag).
const mapSrc = computed(() => {
  const m = (props.contact?.map || '').match(/https:\/\/www\.google\.com\/maps\/embed\?[^"'\s>]+/);
  return m ? m[0] : null;
});
const directions = computed(() => (company.value.address ? 'https://www.google.com/maps/dir/?api=1&destination=' + encodeURIComponent(company.value.address) : null));
const fmtHours = v => (!v ? '—' : String(v).toLowerCase() === 'closed' ? 'Closed' : String(v).replace('-', ' – '));
</script>
