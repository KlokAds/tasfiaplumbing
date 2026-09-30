<template>
  <FrontendLayout>
    <PageHero title="Price list" eyebrow="Pricing" lead="Typical prices for our most common jobs in Singapore. Every job is different, so we confirm the exact price before any work starts."
      :crumbs="[{ label: 'Price list' }]">
      <template #below>
        <p v-if="lastReviewed" class="mt-4 text-[14px] s-subtle">Prices last checked {{ new Date(lastReviewed).toLocaleDateString('en-SG', { month: 'long', year: 'numeric' }) }}.</p>
      </template>
    </PageHero>

    <section class="section-y s-bg-alt">
      <div class="container-app grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_20rem] gap-10">
        <div class="space-y-10 min-w-0">
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
          <p v-if="!groups.length" class="s-muted">Prices are being updated. Contact us for a quote.</p>
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

defineProps({ groups: { type: Array, default: () => [] }, lastReviewed: String });
</script>
