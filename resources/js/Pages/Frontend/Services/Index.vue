<template>
  <FrontendLayout>
    <PageHero :title="breadcrumb?.s_bread_name || 'Our services'" eyebrow="Services" lead="Plumbing repairs and installations for HDB flats, condos, landed homes and businesses across Singapore. Every service page shows real prices."
      :image="breadcrumb?.s_bread_image ? '/' + breadcrumb.s_bread_image : null" :crumbs="[{ label: 'Services' }]">
      <Link href="/pricing" class="btn btn-secondary hidden lg:inline-flex">See the price list</Link>
    </PageHero>

    <div v-if="categories.length > 1" class="sticky top-20 z-30 border-b s-border" style="background: color-mix(in srgb, var(--s-bg) 94%, transparent); backdrop-filter: blur(10px)">
      <div class="container-app py-3 flex gap-2 overflow-x-auto" style="scrollbar-width: none">
        <a v-for="c in categories" :key="c.id" :href="`#${c.slug}`" class="chip shrink-0">{{ c.name }}</a>
        <a v-if="uncategorised.length" href="#more" class="chip shrink-0">More services</a>
      </div>
    </div>

    <section class="section-y s-bg-alt">
      <div class="container-app">
        <div class="mb-10 max-w-md relative">
          <label for="svc-q" class="sr-only">Find a service</label>
          <input id="svc-q" v-model="q" type="search" placeholder="Find a service, e.g. leak, door, tiling…" class="input !pl-10" />
          <svg class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 s-subtle" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M21 21l-5.2-5.2M17 10.5a6.5 6.5 0 11-13 0 6.5 6.5 0 0113 0z" /></svg>
          <p v-if="q && !anyMatch" class="mt-3 text-[15px] s-muted">No service matches “{{ q }}”. <Link :href="`/search?q=${encodeURIComponent(q)}`" class="link">Search the whole site</Link></p>
        </div>
      </div>
      <div class="container-app space-y-16">
        <div v-for="c in categories" v-show="filtered(c.services).length" :key="c.id" :id="c.slug" class="scroll-mt-32">
          <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4 mb-6">
            <div class="max-w-2xl">
              <h2 class="h-section">{{ c.name }}</h2>
              <p v-if="c.intro" class="mt-2 s-muted leading-relaxed">{{ c.intro }}</p>
            </div>
            <Link :href="`/services/${c.slug}`" class="link text-[15px] shrink-0">About {{ c.name.toLowerCase() }} →</Link>
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
            <ServiceCard v-for="s in filtered(c.services)" :key="s.id" :service="s" />
          </div>
        </div>

        <div v-if="filtered(uncategorised).length" id="more" class="scroll-mt-32">
          <h2 :class="categories.length ? 'h-section mb-6' : 'sr-only'">{{ categories.length ? 'More services' : 'All services' }}</h2>
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
            <ServiceCard v-for="s in filtered(uncategorised)" :key="s.id" :service="s" />
          </div>
        </div>
      </div>
    </section>

    <CtaBand page="services" />
  </FrontendLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import FrontendLayout from '@/Layouts/FrontendLayout.vue';
import PageHero from '@/Components/Site/PageHero.vue';
import ServiceCard from '@/Components/Site/ServiceCard.vue';
import CtaBand from '@/Components/Site/CtaBand.vue';

const props = defineProps({
  categories: { type: Array, default: () => [] },
  uncategorised: { type: Array, default: () => [] },
  breadcrumb: Object,
});

const q = ref('');
const words = computed(() => q.value.toLowerCase().split(/\s+/).filter(Boolean));
const filtered = list => (!words.value.length ? list : list.filter(s => {
  const hay = `${s.name} ${s.short_summary || ''}`.toLowerCase();
  return words.value.every(w => hay.includes(w));
}));
const anyMatch = computed(() => props.categories.some(c => filtered(c.services).length) || filtered(props.uncategorised).length > 0);
</script>
