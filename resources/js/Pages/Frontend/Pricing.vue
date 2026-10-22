<template>
  <FrontendLayout>
    <PageHero :title="groups.length ? 'Price list' : 'How our pricing works'" eyebrow="Pricing"
      :lead="groups.length ? 'Typical prices for our most common jobs in Singapore. Every job is different, so we confirm the exact price before any work starts.' : 'Every job is different, so we look at it first and give you a clear price before any work starts. Asking costs nothing.'"
      :crumbs="[{ label: 'Pricing' }]">
      <template #below>
        <p v-if="lastReviewed" class="mt-4 text-[14px] s-subtle">Prices last checked {{ new Date(lastReviewed).toLocaleDateString('en-SG', { month: 'long', year: 'numeric' }) }}.</p>
      </template>
    </PageHero>

    <section class="section-y s-bg-alt">
      <div class="container-app grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_20rem] gap-10">
        <div class="space-y-10 min-w-0">
          <!-- No published prices yet: how a price is given, in three steps -->
          <ol v-if="!groups.length" class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <li v-for="(step, i) in STEPS" :key="step.title" class="card p-5">
              <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-[var(--s-accent-soft)] s-accent text-[15px] font-bold">{{ i + 1 }}</span>
              <h2 class="mt-3 h-card !text-[1.05rem]">{{ step.title }}</h2>
              <p class="mt-1.5 text-[15px] s-muted">{{ step.text }}</p>
            </li>
          </ol>

          <div v-for="g in groups" :key="g.name">
            <h2 class="h-section !text-[1.5rem] mb-4">{{ g.name }}</h2>
            <div class="space-y-4">
              <div v-for="s in g.services" :key="s.id" class="card overflow-hidden">
                <div class="flex items-center justify-between gap-3 px-5 py-3.5 border-b s-border s-surface-2">
                  <h3 class="h-card !text-[1rem]">{{ s.name }}</h3>
                  <Link :href="`/service/${s.slug}`" class="text-[14px] link shrink-0">Details →</Link>
                </div>
                <table class="w-full text-[15.5px]">
                  <tbody>
                    <tr v-for="p in s.prices" :key="p.id" class="border-t first:border-t-0 s-border">
                      <td class="px-5 py-3">
                        {{ p.item }}
                        <span v-if="p.gst_note" class="block text-[13px] s-subtle">{{ p.gst_note }}</span>
                      </td>
                      <td class="px-5 py-3 text-right font-bold s-heading whitespace-nowrap">{{ p.label }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
          <!-- Services without a published price: quoted on request -->
          <div v-if="onRequest.length">
            <h2 class="h-section !text-[1.5rem]">{{ groups.length ? 'Priced on request' : 'Get a price for your job' }}</h2>
            <p class="mt-2 mb-6 s-muted">Send a photo or a short message about the job and we reply with a clear price, usually the same day.</p>
            <div class="space-y-6">
              <div v-for="g in onRequest" :key="g.name" class="card overflow-hidden">
                <p class="px-5 py-3.5 border-b s-border s-surface-2 h-card !text-[1rem]">{{ g.name }}</p>
                <ul class="s-divide">
                  <li v-for="s in g.services" :key="s.id" class="px-5 py-4 flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-6">
                    <div class="flex-1 min-w-0">
                      <Link :href="`/service/${s.slug}`" class="font-bold s-heading hover:text-[var(--s-accent-text)]">{{ s.name }}</Link>
                      <p v-if="s.short_summary" class="mt-1 text-[15px] s-muted line-clamp-2">{{ s.short_summary }}</p>
                    </div>
                    <WhatsAppButton size="sm" :topic="s.name" class="shrink-0 self-start sm:self-center">Ask for a price</WhatsAppButton>
                  </li>
                </ul>
              </div>
            </div>
          </div>
          <p v-if="!groups.length && !onRequest.length" class="s-muted">Contact us for a quote.</p>
        </div>

        <aside>
          <div class="card p-6 lg:sticky lg:top-24">
            <h2 class="h-card !text-[1.1rem]">What affects the price</h2>
            <ul class="mt-3 space-y-2 text-[15px] s-muted">
              <li>• Size and access (high ceilings, tight spaces)</li>
              <li>• Parts and materials you choose</li>
              <li>• Condo or HDB rules on working hours</li>
              <li>• Urgent, same-day or after-hours visits</li>
            </ul>
            <WhatsAppButton class="w-full mt-6">Get an exact quote</WhatsAppButton>
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
import WhatsAppButton from '@/Components/Site/WhatsAppButton.vue';

defineProps({ groups: { type: Array, default: () => [] }, onRequest: { type: Array, default: () => [] }, lastReviewed: String });

// Only what the business does today: free site visits from 8am to 10pm, the price agreed before work.
const STEPS = [
  { title: 'Tell us about the job', text: 'Send a photo or a short message on WhatsApp, or use the quote form.' },
  { title: 'Free site visit if needed', text: 'We come and check the job at no charge, any day from 8am to 10pm.' },
  { title: 'A clear price first', text: 'You get the price before any work starts, and you decide.' },
];
</script>
