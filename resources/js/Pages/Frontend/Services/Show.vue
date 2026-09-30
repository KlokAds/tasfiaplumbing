<template>
  <FrontendLayout>
    <PageHero :title="service.name" :eyebrow="service.category?.name || 'Plumbing service'" :crumbs="crumbs">
      <template #below>
        <p v-if="service.short_summary" class="mt-5 text-[1.12rem] leading-relaxed s-muted max-w-2xl">{{ service.short_summary }}</p>
        <div class="mt-6 flex flex-wrap gap-2">
          <span v-if="fromPrice" class="chip !bg-[var(--s-surface)]">From S${{ fromPrice.toLocaleString() }}</span>
          <span v-if="service.response_time" class="chip !bg-[var(--s-surface)]">⏱ {{ service.response_time }}</span>
          <span v-if="service.warranty" class="chip !bg-[var(--s-surface)]">✓ {{ service.warranty }}</span>
          <span v-if="reviews.google" class="chip !bg-[var(--s-surface)]">★ {{ reviews.google.rating?.toFixed(1) }} on Google</span>
        </div>
        <div class="mt-7 flex flex-col sm:flex-row gap-3">
          <WhatsAppButton size="lg" :topic="service.name">Get a free quote</WhatsAppButton>
          <a href="#quote" class="btn btn-secondary btn-lg">Send the details</a>
        </div>
      </template>
    </PageHero>

    <section class="section-y s-bg">
      <div class="container-app grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_22rem] gap-10 xl:gap-14">
        <div class="min-w-0 space-y-12">
          <!-- Before / after first (strongest proof), otherwise the main photo -->
          <section v-if="service.bef_img && service.aft_img">
            <BeforeAfter :before="service.bef_img" :after="service.aft_img" :title="service.name" eager />
          </section>
          <div v-else-if="service.image" class="photo-frame aspect-[16/10]">
            <img :src="img(service.image, 640)" alt="" aria-hidden="true" class="bg" />
            <img :src="img(service.image, 1280)" :srcset="srcset(service.image, 1600)" sizes="(min-width: 1024px) 760px, 100vw" :alt="service.name" class="fg" fetchpriority="high" />
          </div>

          <!-- Prices -->
          <section v-if="service.prices?.length" id="prices" class="scroll-mt-28">
            <h2 class="h-section !text-[1.6rem]">{{ service.name }} prices in Singapore</h2>
            <p class="mt-2 s-muted">Typical price ranges. The exact price depends on the job; we confirm it before we start.</p>
            <div class="mt-5 rounded-[18px] border s-border s-surface overflow-hidden">
              <table class="w-full text-[15.5px]">
                <thead class="s-surface-2">
                  <tr><th class="text-left font-semibold s-heading px-5 py-3">Job</th><th class="text-right font-semibold s-heading px-5 py-3">Price</th></tr>
                </thead>
                <tbody class="s-divide">
                  <tr v-for="p in service.prices" :key="p.id" class="border-t s-border">
                    <td class="px-5 py-3.5">
                      <span class="s-text">{{ p.item }}</span>
                      <span v-if="p.gst_note" class="block text-[13px] s-subtle">{{ p.gst_note }}</span>
                    </td>
                    <td class="px-5 py-3.5 text-right font-bold s-heading whitespace-nowrap">{{ p.label }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
            <Link href="/pricing" class="mt-3 inline-block text-[15px] link">See the full price list →</Link>
          </section>

          <!-- Content -->
          <article class="prose-site" v-html="service.desc"></article>

          <!-- Only one of before/after uploaded: show it with the main photo -->
          <section v-if="(service.bef_img || service.aft_img) && !(service.bef_img && service.aft_img)" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <figure v-for="f in [['Before', service.bef_img], ['After', service.aft_img]].filter(x => x[1])" :key="f[0]" class="photo-frame aspect-[4/3]">
              <img :src="img(f[1], 640)" :srcset="srcset(f[1], 1280)" sizes="(min-width: 1024px) 380px, 100vw" :alt="`${service.name}: ${f[0].toLowerCase()}`" class="fg !object-cover" loading="lazy" />
              <figcaption class="absolute top-3 left-3 chip !bg-black/60 !text-white !border-white/15">{{ f[0] }}</figcaption>
            </figure>
          </section>

          <!-- Recent jobs -->
          <section v-if="projects.length">
            <h2 class="h-section !text-[1.6rem]">Recent {{ service.name.toLowerCase() }} jobs</h2>
            <div class="mt-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
              <ProjectCard v-for="p in projects" :key="p.id" :project="p" :show-service="false" />
            </div>
          </section>

          <!-- FAQs -->
          <section v-if="service.faqs?.length" id="faq" class="scroll-mt-28">
            <h2 class="h-section !text-[1.6rem]">Frequently asked questions</h2>
            <div class="mt-5"><FaqList :faqs="service.faqs" /></div>
          </section>

          <!-- Areas -->
          <section v-if="locations.length">
            <h2 class="h-section !text-[1.6rem]">Where we do {{ service.name.toLowerCase() }}</h2>
            <div class="mt-4 flex flex-wrap gap-2">
              <Link v-for="l in locations" :key="l.id" :href="`/locations/${l.slug}`" class="chip">{{ l.name }}</Link>
            </div>
          </section>
        </div>

        <!-- Sidebar -->
        <aside class="space-y-5">
          <div id="quote" class="rounded-[24px] border s-border s-surface p-6 sm:p-7 lg:sticky lg:top-28 scroll-mt-28" style="box-shadow: var(--s-shadow-lg)">
            <h2 class="text-[1.25rem] font-semibold leading-snug s-heading" style="font-family: var(--font-display)">Get a price for {{ service.name.toLowerCase() }}</h2>
            <p class="text-[14px] s-subtle mt-1 mb-5">Tell us about the job. We reply with a price range, usually the same day.</p>
            <QuoteForm :subject="`Quote request · ${service.name}`" submit-label="Send my request" id-prefix="svc" />
            <div v-if="company.phone" class="mt-5 pt-5 border-t s-border text-center">
              <p class="text-[13.5px] s-subtle">Prefer to talk?</p>
              <a :href="'tel:' + company.tel" class="text-lg font-bold s-heading hover:text-[var(--s-accent-text)]">{{ company.phone }}</a>
            </div>
          </div>
        </aside>
      </div>
    </section>

    <!-- Reviews -->
    <section v-if="reviews.items.length" class="section-y s-bg-alt">
      <div class="container-app">
        <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4 mb-8">
          <h2 class="h-section">What customers say</h2>
          <Link href="/reviews" class="link text-[15px]">All reviews →</Link>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
          <ReviewCard v-for="(r, i) in reviews.items" :key="i" :review="r" />
        </div>
      </div>
    </section>

    <!-- Guides -->
    <section v-if="articles.length" class="section-y s-bg">
      <div class="container-app">
        <h2 class="h-section mb-8">Guides about {{ service.name.toLowerCase() }}</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
          <ArticleCard v-for="a in articles" :key="a.id" :article="a" />
        </div>
      </div>
    </section>

    <!-- Related -->
    <section v-if="relatedServices.length" class="section-y s-bg-alt">
      <div class="container-app">
        <h2 class="h-section mb-8">Related services</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
          <ServiceCard v-for="s in relatedServices" :key="s.id" :service="s" />
        </div>
      </div>
    </section>
  </FrontendLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import FrontendLayout from '@/Layouts/FrontendLayout.vue';
import PageHero from '@/Components/Site/PageHero.vue';
import ServiceCard from '@/Components/Site/ServiceCard.vue';
import ProjectCard from '@/Components/Site/ProjectCard.vue';
import ReviewCard from '@/Components/Site/ReviewCard.vue';
import ArticleCard from '@/Components/Site/ArticleCard.vue';
import QuoteForm from '@/Components/Site/QuoteForm.vue';
import FaqList from '@/Components/Site/FaqList.vue';
import WhatsAppButton from '@/Components/Site/WhatsAppButton.vue';
import BeforeAfter from '@/Components/Site/BeforeAfter.vue';
import { img, srcset } from '@/utils/img';

const props = defineProps({
  service: Object,
  relatedServices: { type: Array, default: () => [] },
  projects: { type: Array, default: () => [] },
  articles: { type: Array, default: () => [] },
  locations: { type: Array, default: () => [] },
  reviews: { type: Object, default: () => ({ items: [], google: null }) },
});

const company = computed(() => usePage().props.company || {});
const whatsapp = computed(() => {
  const w = company.value.whatsapp || '';
  const base = w.startsWith('http') ? w : 'https://wa.me/' + w.replace(/\D/g, '');
  return base + (base.includes('?') ? '&' : '?') + 'text=' + encodeURIComponent(`Hi, I need a quote for ${props.service.name}.`);
});
const fromPrice = computed(() => {
  const list = (props.service.prices || []).map(p => p.price_from).filter(Boolean);
  return list.length ? Math.min(...list) : null;
});
const crumbs = computed(() => [
  { label: 'Services', href: '/services' },
  ...(props.service.category ? [{ label: props.service.category.name, href: `/services/${props.service.category.slug}` }] : []),
  { label: props.service.name },
]);
</script>
