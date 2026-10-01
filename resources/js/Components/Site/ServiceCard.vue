<template>
  <Link :href="`/service/${service.slug}`" class="lift group h-full rounded-[22px] border s-border s-surface p-7 flex flex-col">
    <span class="w-14 h-14 rounded-2xl flex items-center justify-center s-accent bg-[var(--s-accent-soft)]">
      <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" :d="serviceIcon(service.name)" /></svg>
    </span>
    <h3 class="h-card !text-[1.25rem] mt-6">{{ service.name }}</h3>
    <p v-if="service.short_summary" class="mt-2 text-[15.5px] leading-relaxed s-muted line-clamp-3">{{ service.short_summary }}</p>
    <span class="mt-auto pt-6">
      <span class="pt-5 border-t s-border flex items-center justify-between gap-3 text-[15px]">
        <span v-if="service.from_price" class="s-subtle">From <strong class="s-heading">S${{ groupDigits(service.from_price) }}</strong></span>
        <span v-else class="s-subtle">{{ service.response_time || 'Free quote' }}</span>
        <span class="font-semibold s-accent group-hover:translate-x-1 transition-transform">Details →</span>
      </span>
    </span>
  </Link>
</template>

<script setup>
import { groupDigits } from '@/utils/fmt';
import { Link } from '@inertiajs/vue3';
import { serviceIcon } from '@/utils/serviceIcon';

defineProps({ service: { type: Object, required: true } });
</script>
