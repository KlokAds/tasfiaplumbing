<template>
  <FrontendLayout>
    <PageHero :title="category.name" eyebrow="Services" :lead="category.intro" :image="category.image ? '/' + category.image : null"
      :crumbs="[{ label: 'Services', href: '/services' }, { label: category.name }]" />

    <section class="section-y s-bg-alt">
      <div class="container-app">
        <h2 class="sr-only">{{ category.name }} services</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
          <ServiceCard v-for="s in services" :key="s.id" :service="s" />
        </div>
        <p v-if="!services.length" class="s-muted">No services in this category yet.</p>
      </div>
    </section>

    <section v-if="category.description" class="section-y s-bg">
      <div class="container-app max-w-3xl">
        <div class="prose-site" v-html="category.description"></div>
      </div>
    </section>

    <section v-if="articles.length" class="section-y s-bg-alt">
      <div class="container-app">
        <h2 class="h-section mb-8">Guides for {{ category.name.toLowerCase() }}</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
          <ArticleCard v-for="a in articles" :key="a.id" :article="a" />
        </div>
      </div>
    </section>

    <CtaBand />
  </FrontendLayout>
</template>

<script setup>
import FrontendLayout from '@/Layouts/FrontendLayout.vue';
import PageHero from '@/Components/Site/PageHero.vue';
import ServiceCard from '@/Components/Site/ServiceCard.vue';
import ArticleCard from '@/Components/Site/ArticleCard.vue';
import CtaBand from '@/Components/Site/CtaBand.vue';

defineProps({ category: Object, services: Array, articles: Array });
</script>
