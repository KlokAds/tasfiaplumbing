<template>
  <section class="py-16 lg:py-24 s-bg">
    <div class="container-app">
      <div class="rounded-[24px] text-white px-6 py-12 sm:px-12 sm:py-14" style="background: var(--s-dark)">
        <div class="grid lg:grid-cols-[1fr_auto] gap-8 items-center">
          <div class="max-w-2xl">
            <h2 class="h-section !text-white">{{ heading }}</h2>
            <p v-if="body" class="mt-3 text-white/80 text-[1.02rem] leading-relaxed">{{ body }}</p>
          </div>
          <div class="flex flex-col sm:flex-row gap-3">
            <WhatsAppButton size="lg" green :topic="topic">Get a free quote</WhatsAppButton>
            <a v-if="company.tel" :href="'tel:' + company.tel" class="btn btn-lg bg-white !text-[#0f3f8a] hover:bg-[#e9f2fe]">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.95.68l1.5 4.5a1 1 0 01-.5 1.2l-2.26 1.13a11 11 0 005.52 5.52l1.13-2.26a1 1 0 011.2-.5l4.5 1.5a1 1 0 01.68.95V19a2 2 0 01-2 2h-1C9.7 21 3 14.3 3 6V5z" /></svg>
              {{ company.phone }}
            </a>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import WhatsAppButton from '@/Components/Site/WhatsAppButton.vue';

// Texts come from Admin → Website text. "page" picks that page's banner (services, projects,
// locations); empty fields fall back to the general banner.
const props = defineProps({
  page: { type: String, default: '' },
  title: String,
  text: String,
  topic: { type: String, default: '' },
});
const company = computed(() => usePage().props.company || {});
const t = computed(() => company.value.texts || {});
const heading = computed(() => props.title || (props.page && t.value[`cta_${props.page}_title`]) || t.value.cta_title);
const body = computed(() => props.text || (props.page && t.value[`cta_${props.page}_text`]) || t.value.cta_text);
</script>
