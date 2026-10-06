<template>
  <FrontendLayout>
    <PageHero :title="(location.meta_title || '').split(' | ')[0] || `Plumbing services in ${location.name}`" :eyebrow="location.region ? `${location.region} Singapore` : 'Singapore'"
      :lead="location.intro" :image="location.image ? '/' + location.image : null"
      :crumbs="[{ label: 'Areas we serve', href: '/locations' }, { label: location.name }]">
      <template #below>
        <div v-if="location.property_types?.length" class="mt-5 flex flex-wrap gap-2">
          <span v-for="t in location.property_types" :key="t" class="chip !bg-[var(--s-surface)]">{{ t }}</span>
        </div>
      </template>
    </PageHero>

    <section class="section-y s-bg">
      <div class="container-app grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_22rem] gap-10 xl:gap-14">
        <div class="min-w-0 space-y-12">
          <article v-if="location.description" class="prose-site" v-html="location.description"></article>

          <section v-if="services.length">
            <h2 class="h-section !text-[1.6rem]">Services in {{ location.name }}</h2>
            <div class="mt-5 grid grid-cols-1 sm:grid-cols-2 gap-5">
              <ServiceCard v-for="s in services" :key="s.id" :service="s" />
            </div>
          </section>

          <section v-if="projects.length">
            <h2 class="h-section !text-[1.6rem]">Recent jobs in {{ location.name }}</h2>
            <div class="mt-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
              <ProjectCard v-for="p in projects" :key="p.id" :project="p" />
            </div>
          </section>

          <section v-if="location.faqs?.length">
            <h2 class="h-section !text-[1.6rem]">Questions from {{ location.name }} residents</h2>
            <div class="mt-5"><FaqList :faqs="location.faqs" /></div>
          </section>

          <section v-if="location.nearby_areas?.length || nearby.length">
            <h2 class="h-section !text-[1.4rem]">Nearby areas</h2>
            <div class="mt-4 flex flex-wrap gap-2">
              <Link v-for="n in nearby" :key="n.id" :href="`/locations/${n.slug}`" class="chip">{{ n.name }}</Link>
              <span v-for="n in (location.nearby_areas || []).filter(a => !nearby.some(x => x.name === a))" :key="n" class="chip">{{ n }}</span>
            </div>
          </section>
        </div>

        <aside>
          <div class="card p-6 lg:sticky lg:top-24" style="box-shadow: var(--s-shadow)">
            <h2 class="h-card !text-[1.15rem]">Get a quote in {{ location.name }}</h2>
            <p class="text-[14px] s-subtle mt-1 mb-5">Tell us what needs doing and your postal code.</p>
            <QuoteForm :subject="`Quote request · ${location.name}`" submit-label="Send my request" id-prefix="loc" />
          </div>
        </aside>
      </div>
    </section>
  </FrontendLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import FrontendLayout from '@/Layouts/FrontendLayout.vue';
import PageHero from '@/Components/Site/PageHero.vue';
import ServiceCard from '@/Components/Site/ServiceCard.vue';
import ProjectCard from '@/Components/Site/ProjectCard.vue';
import QuoteForm from '@/Components/Site/QuoteForm.vue';
import FaqList from '@/Components/Site/FaqList.vue';

defineProps({ location: Object, services: Array, projects: Array, nearby: Array });
</script>
