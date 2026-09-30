<template>
  <FrontendLayout>
    <PageHero :title="q ? `Results for “${q}”` : 'Search'" eyebrow="Search" compact :crumbs="[{ label: 'Search' }]">
      <template #below>
        <form @submit.prevent="go" class="mt-6 max-w-xl" role="search">
          <label for="page-search" class="sr-only">Search</label>
          <div class="relative">
            <input id="page-search" v-model="term" type="search" placeholder="Search services, guides, projects, areas…" class="input !pl-11 !py-3.5 text-[16px]" />
            <svg class="w-5 h-5 absolute left-3.5 top-1/2 -translate-y-1/2 s-subtle" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M21 21l-5.2-5.2M17 10.5a6.5 6.5 0 11-13 0 6.5 6.5 0 0113 0z" /></svg>
          </div>
        </form>
      </template>
    </PageHero>

    <section class="section-y !pt-10 s-bg-alt">
      <div class="container-app space-y-12">
        <p v-if="results" class="s-muted">{{ results.total }} result{{ results.total === 1 ? '' : 's' }}</p>

        <div v-if="results?.services.length">
          <h2 class="h-section !text-[1.4rem] mb-5">Services</h2>
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <ResultCard v-for="r in results.services" :key="r.url" :r="r" />
          </div>
        </div>
        <div v-if="results?.articles.length">
          <h2 class="h-section !text-[1.4rem] mb-5">Guides</h2>
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <ResultCard v-for="r in results.articles" :key="r.url" :r="r" />
          </div>
          <Link :href="`/blogs?search=${encodeURIComponent(q)}`" class="mt-4 inline-block text-[15px] link">More guides →</Link>
        </div>
        <div v-if="results?.projects.length">
          <h2 class="h-section !text-[1.4rem] mb-5">Projects</h2>
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <ResultCard v-for="r in results.projects" :key="r.url" :r="r" />
          </div>
        </div>
        <div v-if="results?.locations.length">
          <h2 class="h-section !text-[1.4rem] mb-5">Areas</h2>
          <div class="flex flex-wrap gap-2">
            <Link v-for="r in results.locations" :key="r.url" :href="r.url" class="chip !py-2 !px-4">{{ r.title }}</Link>
          </div>
        </div>

        <div v-if="results && !results.total" class="card p-10 text-center max-w-xl mx-auto">
          <p class="h-card">Nothing found for “{{ q }}”</p>
          <p class="mt-2 s-muted">Try a shorter word, or ask us directly. We reply fast.</p>
          <WhatsAppButton :topic="q" class="mt-5" />
        </div>
      </div>
    </section>
  </FrontendLayout>
</template>

<script setup>
import { h, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import FrontendLayout from '@/Layouts/FrontendLayout.vue';
import PageHero from '@/Components/Site/PageHero.vue';
import WhatsAppButton from '@/Components/Site/WhatsAppButton.vue';
import { img } from '@/utils/img';

const props = defineProps({ q: { type: String, default: '' }, results: Object });
const term = ref(props.q);
const go = () => { if (term.value.trim().length >= 2) router.get('/search', { q: term.value.trim() }); };

const ResultCard = (p) => h(Link, { href: p.r.url, class: 'group card card-link p-4 flex gap-4 items-start' }, () => [
  p.r.image
    ? h('img', { src: img(p.r.image, 160), alt: '', class: 'w-20 h-16 rounded-lg object-cover shrink-0', loading: 'lazy' })
    : null,
  h('span', { class: 'min-w-0' }, [
    h('span', { class: 'block font-semibold s-heading leading-snug line-clamp-2 group-hover:text-[var(--s-accent-text)]' }, p.r.title),
    p.r.text ? h('span', { class: 'block mt-1 text-[14px] s-muted line-clamp-2' }, p.r.text) : null,
  ]),
]);
ResultCard.props = ['r'];
</script>
