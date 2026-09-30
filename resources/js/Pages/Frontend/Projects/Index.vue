<template>
  <FrontendLayout>
    <PageHero :title="breadcrumb?.p_bread_name || 'Recent projects'" eyebrow="Our work" lead="Real jobs from real Singapore homes and businesses: what the problem was, what we did and where."
      :image="breadcrumb?.p_bread_image ? '/' + breadcrumb.p_bread_image : null" :crumbs="[{ label: 'Projects' }]" />

    <section class="section-y s-bg-alt">
      <div class="container-app">
        <form @submit.prevent="search" class="mb-5 max-w-md" role="search">
          <label for="proj-q" class="sr-only">Search projects</label>
          <div class="relative">
            <input id="proj-q" v-model="q" type="search" placeholder="Search projects, e.g. kitchen, Tampines, HDB…" class="input !pl-10" />
            <svg class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 s-subtle" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M21 21l-5.2-5.2M17 10.5a6.5 6.5 0 11-13 0 6.5 6.5 0 0113 0z" /></svg>
          </div>
        </form>
        <p v-if="filters.q" class="mb-5 s-muted">{{ projects.total }} project(s) for “{{ filters.q }}” · <Link href="/projects" class="link">Clear</Link></p>
        <div v-if="services.length" class="flex flex-wrap gap-2 mb-8">
          <Link href="/projects" :class="['chip', !filters.service && 'chip-on']" preserve-scroll>All</Link>
          <Link v-for="s in services" :key="s.id" :href="`/projects?service=${s.slug}`" :class="['chip', filters.service === s.slug && 'chip-on']" preserve-scroll>{{ s.name }}</Link>
        </div>

        <div v-if="projects.data.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
          <ProjectCard v-for="p in projects.data" :key="p.id" :project="p" />
        </div>
        <p v-else class="s-muted">No projects to show yet.</p>

        <SitePagination :meta="projects" />
      </div>
    </section>

    <CtaBand page="projects" />
  </FrontendLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import FrontendLayout from '@/Layouts/FrontendLayout.vue';
import PageHero from '@/Components/Site/PageHero.vue';
import ProjectCard from '@/Components/Site/ProjectCard.vue';
import SitePagination from '@/Components/Site/SitePagination.vue';
import CtaBand from '@/Components/Site/CtaBand.vue';

const props = defineProps({ projects: Object, services: { type: Array, default: () => [] }, filters: { type: Object, default: () => ({}) }, breadcrumb: Object });
const q = ref(props.filters?.q || '');
function search() {
  router.get('/projects', { ...(q.value.trim() ? { q: q.value.trim() } : {}), ...(props.filters.service ? { service: props.filters.service } : {}) }, { preserveState: true, preserveScroll: true });
}
</script>
