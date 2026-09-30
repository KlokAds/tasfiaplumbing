<template>
  <FrontendLayout>
    <PageHero :title="aboutContent?.title || `About ${company.name}`" eyebrow="About us" :lead="lead"
      :image="banner || photos[0] || null" :crumbs="[{ label: 'About us' }]" />

    <!-- Who we are -->
    <section class="section-y s-bg">
      <div :class="['container-app grid grid-cols-1 gap-12 lg:gap-16 items-center', photos.length ? 'lg:grid-cols-[1.05fr_1fr]' : '']">
        <div :class="photos.length ? '' : 'max-w-3xl'">
          <p class="eyebrow">Who we are</p>
          <h2 class="h-section mt-3">{{ company.legal_name || company.name }}</h2>
          <div class="prose-site mt-5 !text-[16.5px]" v-html="aboutContent?.short_desc || ''"></div>

          <ul v-if="factList.length" class="mt-7 grid sm:grid-cols-2 gap-3">
            <li v-for="f in factList" :key="f" class="flex items-center gap-3 text-[15px] s-heading font-medium">
              <span class="shrink-0 w-7 h-7 rounded-full grid place-items-center bg-[var(--s-accent-soft)] s-accent">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
              </span>
              {{ f }}
            </li>
          </ul>

          <div class="mt-8 flex flex-wrap gap-3">
            <WhatsAppButton topic="about">Get a free quote</WhatsAppButton>
            <Link href="/projects" class="btn btn-secondary">See our work
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 5l7 7-7 7" /></svg>
            </Link>
          </div>
        </div>

        <!-- Photo collage: one large, two small -->
        <div v-if="photos.length" class="grid grid-cols-2 gap-3 sm:gap-4">
          <div :class="['overflow-hidden rounded-[22px] border s-border s-surface-2', photos.length > 1 ? 'row-span-2 aspect-[4/5] sm:aspect-auto' : 'col-span-2 aspect-[4/3]']">
            <img :src="img(photos[0], 800)" :srcset="srcset(photos[0], 1280)" sizes="(min-width: 1024px) 300px, 50vw" :alt="`${company.name} plumbing work`" class="img-cover" loading="lazy" decoding="async" />
          </div>
          <div v-for="(p, i) in photos.slice(1, 3)" :key="p" :class="['overflow-hidden rounded-[22px] border s-border s-surface-2 aspect-[4/3]', photos.length === 2 ? 'row-span-2 !aspect-auto' : '']">
            <img :src="img(p, 640)" :srcset="srcset(p, 960)" sizes="(min-width: 1024px) 300px, 50vw" :alt="`${company.name} job ${i + 2}`" class="img-cover" loading="lazy" decoding="async" />
          </div>
        </div>
      </div>

      <!-- Numbers -->
      <div v-if="stats.length" class="container-app mt-14">
        <dl class="s-surface rounded-[24px] border s-border grid grid-cols-2 lg:grid-cols-4" style="box-shadow: var(--s-shadow)">
          <div v-for="(c, i) in stats.slice(0, 4)" :key="c.id" :class="['p-6 lg:p-7 text-center flex flex-col-reverse gap-1', i % 2 ? 'border-l s-border' : '', i > 1 ? 'border-t lg:border-t-0 s-border' : '', i === 2 ? 'lg:border-l' : '']">
            <dt class="text-[15px] s-subtle">{{ c.label }}</dt>
            <dd class="text-[2rem] lg:text-[2.2rem] font-bold s-heading leading-none tabular-nums" style="font-family: var(--font-display)">{{ c.value }}</dd>
          </div>
        </dl>
      </div>
    </section>

    <!-- What you can expect (admin: About → skills) -->
    <section v-if="values.length" class="section-y s-bg-alt">
      <div class="container-app">
        <div class="max-w-2xl">
          <p class="eyebrow">How we work</p>
          <h2 class="h-section mt-3">What you can expect from us</h2>
        </div>
        <div class="mt-9 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-5">
          <div v-for="v in values" :key="v.id" class="rounded-[22px] border s-border s-surface p-7">
            <span class="icon-box">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" :d="v.icon" /></svg>
            </span>
            <h3 class="h-card !text-[1.15rem] mt-5">{{ v.title }}</h3>
            <p v-if="v.text" class="mt-2 text-[15px] s-muted leading-relaxed">{{ v.text }}</p>
            <div v-if="v.percent" class="mt-4">
              <div class="flex justify-between text-[13px] s-subtle mb-1.5"><span>Rating</span><span class="font-bold s-accent">{{ v.percent }}%</span></div>
              <div class="h-1.5 rounded-full s-surface-2 overflow-hidden"><div class="h-full rounded-full bg-[var(--s-accent)]" :style="{ width: v.percent + '%' }"></div></div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Reviews -->
    <section v-if="reviews.items?.length" class="section-y s-bg">
      <div class="container-app">
        <div class="flex flex-wrap items-end justify-between gap-4">
          <div>
            <p class="eyebrow">Reviews</p>
            <h2 class="h-section mt-3">What customers say</h2>
            <p v-if="reviews.google" class="mt-3 flex items-center gap-2 text-[15px] s-muted">
              <Stars :value="reviews.google.rating" />
              <span><strong class="s-heading">{{ reviews.google.rating?.toFixed(1) }}</strong> from {{ reviews.google.total }} Google reviews</span>
            </p>
          </div>
          <Link href="/reviews" class="btn btn-secondary btn-sm">All reviews</Link>
        </div>
        <div class="mt-8 grid grid-cols-1 md:grid-cols-3 gap-5">
          <ReviewCard v-for="(r, i) in reviews.items" :key="i" :review="r" />
        </div>
      </div>
    </section>

    <!-- People (authors with a bio) -->
    <section v-if="team.length" class="section-y s-bg-alt">
      <div class="container-app">
        <p class="eyebrow">The people</p>
        <h2 class="h-section mt-3 mb-8">Who you will work with</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
          <div v-for="p in team" :key="p.id" class="card p-6">
            <div class="flex items-center gap-4">
              <img v-if="p.image" :src="img('/' + p.image, 96)" :alt="p.name" class="w-14 h-14 rounded-full object-cover" loading="lazy" />
              <span v-else class="w-14 h-14 rounded-full bg-[var(--s-accent)] text-white text-lg font-bold flex items-center justify-center">{{ p.name.charAt(0) }}</span>
              <div>
                <p class="h-card">{{ p.name }}</p>
                <p class="text-[14px] s-subtle">{{ p.job_title }}</p>
              </div>
            </div>
            <p class="mt-4 text-[15px] s-muted leading-relaxed">{{ p.bio }}</p>
          </div>
        </div>
      </div>
    </section>

    <section v-if="partners.length" class="py-12 s-bg-alt border-y s-border">
      <div class="container-app">
        <p class="text-center text-[12.5px] font-bold uppercase tracking-[0.14em] s-subtle mb-7">Trusted by</p>
        <div class="flex flex-wrap items-center justify-center gap-x-12 gap-y-6">
          <div v-for="p in partners" :key="p.id" class="h-10 w-28 flex items-center justify-center opacity-60 hover:opacity-100 transition dark:invert dark:hue-rotate-180">
            <img :src="'/' + p.image" alt="" class="max-h-full max-w-full object-contain grayscale" loading="lazy" />
          </div>
        </div>
      </div>
    </section>

    <CtaBand />
  </FrontendLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import FrontendLayout from '@/Layouts/FrontendLayout.vue';
import PageHero from '@/Components/Site/PageHero.vue';
import CtaBand from '@/Components/Site/CtaBand.vue';
import ReviewCard from '@/Components/Site/ReviewCard.vue';
import Stars from '@/Components/Site/Stars.vue';
import WhatsAppButton from '@/Components/Site/WhatsAppButton.vue';
import { img, srcset } from '@/utils/img';
import { statsFrom, iconFor } from '@/utils/stats';

const props = defineProps({
  aboutContent: Object,
  photos: { type: Array, default: () => [] },
  banner: String,
  facts: { type: Object, default: () => ({}) },
  reviews: { type: Object, default: () => ({ items: [], google: null }) },
  counters: { type: Array, default: () => [] },
  skills: { type: Array, default: () => [] },
  partners: { type: Array, default: () => [] },
  team: { type: Array, default: () => [] },
});
const company = computed(() => usePage().props.company || {});
const stats = computed(() => statsFrom(props.counters));

const lead = computed(() => props.aboutContent?.subtitle || company.value.texts?.about_lead || '');

// Short, checkable facts built from real data (nothing is shown when it is unknown).
const factList = computed(() => {
  const f = props.facts || {};
  const now = new Date().getFullYear();
  return [
    /^(19|20)\d{2}$/.test(String(f.founded || '')) ? `Serving Singapore since ${f.founded} (${now - Number(f.founded)} years)` : null,
    f.services ? `${f.services} services under one team` : null,
    f.locations ? `Working in ${f.locations} areas across Singapore` : 'Working across Singapore',
    props.reviews?.google?.rating ? `Rated ${props.reviews.google.rating.toFixed(1)} / 5 on Google` : 'Clear price before any work starts',
  ].filter(Boolean);
});

// "Skills" in admin are really value cards. s_point used to hold the text; only a
// number is treated as a percentage.
const values = computed(() => props.skills.map((s) => {
  const point = String(s.s_point ?? '').trim();
  const isPct = /^\d{1,3}\s*%?$/.test(point);
  const title = s.s_title || s.title;
  return {
    id: s.id,
    title,
    text: s.s_subtitle || s.short_desc || (!isPct ? point : ''),
    percent: isPct ? Math.min(100, parseInt(point, 10)) : null,
    icon: iconFor(s.s_icon || s.icon_name, title),
  };
}).filter((v) => v.title));
</script>
