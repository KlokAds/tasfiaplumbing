<template>
  <FrontendLayout>
    <PageHero :title="breadcrumb?.b_bread_name || 'Guides & cost advice'" eyebrow="Articles" lead="Straight answers to the questions our customers ask most: what it costs in Singapore, what causes the problem and when to call a professional."
      :image="breadcrumb?.b_bread_image ? '/' + breadcrumb.b_bread_image : null" :crumbs="[{ label: 'Articles' }]">
      <form @submit.prevent="search" class="w-full lg:w-80" role="search">
        <label for="blog-q" class="sr-only">Search articles</label>
        <div class="relative">
          <input id="blog-q" v-model="q" type="search" placeholder="Search guides…" class="input !pl-10" />
          <svg class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 s-subtle" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M21 21l-5.2-5.2M17 10.5a6.5 6.5 0 11-13 0 6.5 6.5 0 0113 0z" /></svg>
        </div>
      </form>
    </PageHero>

    <section class="section-y s-bg-alt">
      <div class="container-app">
        <!-- Topics: one scrolling row on a phone, wrapped lines on wider screens (nothing cut off) -->
        <div v-if="services.length" class="flex gap-2 mb-8 overflow-x-auto pb-1 md:flex-wrap md:overflow-visible" style="scrollbar-width: none">
          <Link href="/blogs" :class="['chip shrink-0 text-sm px-3.5 py-1.5', !filters.service && 'chip-on']">All topics</Link>
          <Link v-for="(s, i) in services" :key="s.id" :href="`/blogs?service=${s.slug}`" :class="['chip shrink-0 text-sm px-3.5 py-1.5', filters.service === s.slug && 'chip-on', i >= TOPICS_SHOWN && !allTopics && 'md:hidden']">{{ s.name }}</Link>
          <button v-if="services.length > TOPICS_SHOWN" type="button" @click="allTopics = !allTopics" class="chip shrink-0 text-sm px-3.5 py-1.5 hidden md:inline-flex">{{ allTopics ? 'Fewer topics' : `+ ${services.length - TOPICS_SHOWN} more topics` }}</button>
        </div>

        <p v-if="filters.search" class="mb-6 s-muted">{{ blogs.total }} result(s) for “{{ filters.search }}” · <Link href="/blogs" class="link">Clear</Link></p>

        <h2 class="sr-only">Latest articles</h2>
        <div v-if="blogs.data.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
          <ArticleCard v-for="b in blogs.data" :key="b.id" :article="b" />
        </div>
        <div v-else class="card p-10 text-center">
          <p class="h-card">No articles found</p>
          <p class="mt-2 s-muted">Try another word, or ask us directly.</p>
          <WhatsAppButton green class="mt-5">Ask us on WhatsApp</WhatsAppButton>
        </div>

        <SitePagination :meta="blogs" />
      </div>
    </section>
  </FrontendLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import FrontendLayout from '@/Layouts/FrontendLayout.vue';
import PageHero from '@/Components/Site/PageHero.vue';
import WhatsAppButton from '@/Components/Site/WhatsAppButton.vue';
import ArticleCard from '@/Components/Site/ArticleCard.vue';
import SitePagination from '@/Components/Site/SitePagination.vue';

const props = defineProps({ blogs: Object, filters: { type: Object, default: () => ({}) }, services: { type: Array, default: () => [] }, breadcrumb: Object });
// Desktop shows the first topics and a button for the rest (a phone scrolls the whole row).
const TOPICS_SHOWN = 10;
const allTopics = ref(props.services.findIndex((s) => s.slug === props.filters.service) >= TOPICS_SHOWN);
const q = ref(props.filters?.search || '');
function search() {
  router.get('/blogs', { ...(q.value ? { search: q.value } : {}), ...(props.filters.service ? { service: props.filters.service } : {}) }, { preserveState: true });
}
</script>
