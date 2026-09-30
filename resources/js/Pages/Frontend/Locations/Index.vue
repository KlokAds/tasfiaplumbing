<template>
  <FrontendLayout>
    <PageHero title="Areas we serve" eyebrow="Locations" lead="We work across Singapore. These are the areas where we do the most jobs, with local details and the services available there."
      :crumbs="[{ label: 'Areas we serve' }]" />

    <section class="section-y s-bg-alt">
      <div class="container-app">
        <div v-for="(list, region) in byRegion" :key="region" class="mb-12 last:mb-0">
          <h2 class="h-section !text-[1.4rem] mb-5">{{ region }}</h2>
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
            <Link v-for="l in list" :key="l.id" :href="`/locations/${l.slug}`" class="group card card-link overflow-hidden flex flex-col">
              <div v-if="l.image" class="aspect-[16/9] overflow-hidden"><img :src="'/' + l.image" :alt="l.name" loading="lazy" class="img-cover group-hover:scale-[1.04] transition-transform duration-500" /></div>
              <div class="p-5 flex-1 flex flex-col">
                <h3 class="h-card">{{ l.name }}</h3>
                <p v-if="l.intro" class="mt-2 text-[15px] s-muted line-clamp-3 leading-relaxed">{{ l.intro }}</p>
                <div v-if="l.property_types?.length" class="mt-auto pt-4 flex flex-wrap gap-1.5">
                  <span v-for="t in l.property_types" :key="t" class="chip !py-0.5 !text-[13px]">{{ t }}</span>
                </div>
              </div>
            </Link>
          </div>
        </div>
        <p v-if="!locations.length" class="s-muted">We serve all of Singapore. <Link href="/contact" class="link">Ask us about your area.</Link></p>
      </div>
    </section>

    <CtaBand page="locations" />
  </FrontendLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import FrontendLayout from '@/Layouts/FrontendLayout.vue';
import PageHero from '@/Components/Site/PageHero.vue';
import CtaBand from '@/Components/Site/CtaBand.vue';

const props = defineProps({ locations: { type: Array, default: () => [] } });
const byRegion = computed(() => props.locations.reduce((acc, l) => {
  const key = l.region ? `${l.region} Singapore` : 'Singapore';
  (acc[key] ||= []).push(l);
  return acc;
}, {}));
</script>
