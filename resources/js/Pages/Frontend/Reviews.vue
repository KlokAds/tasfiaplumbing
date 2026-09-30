<template>
  <FrontendLayout>
    <PageHero title="Customer reviews" eyebrow="Reviews" lead="What homeowners and businesses across Singapore say about our work." :crumbs="[{ label: 'Reviews' }]">
      <div v-if="reviews.google" class="card p-5 flex items-center gap-5 !bg-white !text-[#0c1322] !border-transparent shrink-0">
        <div>
          <p class="text-[13px] font-semibold text-[#6b7280] flex items-center gap-1.5">
            <svg class="w-4 h-4" viewBox="0 0 48 48" aria-hidden="true"><path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3C33.7 32.7 29.2 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.3-.1-2.4-.4-3.5z"/><path fill="#FF3D00" d="M6.3 14.7l6.6 4.8C14.7 15.1 19 12 24 12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 16.3 4 9.7 8.3 6.3 14.7z"/><path fill="#4CAF50" d="M24 44c5.2 0 9.9-2 13.4-5.2l-6.2-5.2C29.2 35.1 26.7 36 24 36c-5.2 0-9.6-3.3-11.3-7.9l-6.5 5C9.5 39.6 16.2 44 24 44z"/><path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-.8 2.2-2.2 4.2-4.1 5.6l6.2 5.2C37 38.2 44 33 44 24c0-1.3-.1-2.4-.4-3.5z"/></svg>
            Google rating
          </p>
          <p class="mt-1 flex items-baseline gap-2"><span class="text-4xl font-extrabold">{{ reviews.google.rating?.toFixed(1) }}</span><Stars :value="reviews.google.rating" size="w-5 h-5" /></p>
          <a :href="reviews.google.url" target="_blank" rel="noopener" class="text-[14px] text-[#6b7280] hover:underline">{{ reviews.google.total }} reviews</a>
        </div>
        <a :href="reviews.google.write_url" target="_blank" rel="noopener" class="btn btn-primary btn-sm">Write a review</a>
      </div>
    </PageHero>

    <section class="section-y s-bg-alt">
      <div class="container-app">
        <div v-if="reviews.items.length" class="columns-1 md:columns-2 lg:columns-3 gap-5">
          <div v-for="(r, i) in reviews.items.slice(0, shown)" :key="i" class="mb-5 break-inside-avoid"><ReviewCard :review="r" /></div>
        </div>
        <div v-if="reviews.items.length > shown" class="mt-4 text-center">
          <button type="button" class="btn btn-secondary" @click="shown += 12">Show more reviews <span class="s-subtle font-normal">({{ reviews.items.length - shown }} more)</span></button>
        </div>
        <div v-else class="card p-10 text-center max-w-xl mx-auto">
          <h2 class="h-card">Reviews are on their way</h2>
          <p class="mt-2 s-muted">Worked with us recently? We would really appreciate a few words about your experience.</p>
          <a v-if="reviews.google?.write_url" :href="reviews.google.write_url" target="_blank" rel="noopener" class="btn btn-primary mt-5">Review us on Google</a>
          <Link v-else href="/contact" class="btn btn-primary mt-5">Contact us</Link>
        </div>
        <p v-if="reviews.google" class="mt-8 text-center text-[14px] s-subtle">
          Google reviews come straight from our Google Business Profile.
          <a :href="reviews.google.url" target="_blank" rel="noopener" class="link">See all {{ reviews.google.total }} on Google</a>
        </p>
      </div>
    </section>

    <CtaBand page="projects" />
  </FrontendLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import FrontendLayout from '@/Layouts/FrontendLayout.vue';
import PageHero from '@/Components/Site/PageHero.vue';
import ReviewCard from '@/Components/Site/ReviewCard.vue';
import Stars from '@/Components/Site/Stars.vue';
import CtaBand from '@/Components/Site/CtaBand.vue';

defineProps({ reviews: { type: Object, default: () => ({ items: [], google: null, count: 0 }) } });
const shown = ref(12);
</script>
