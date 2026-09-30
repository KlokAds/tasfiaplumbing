<template>
  <section class="relative s-bg-alt border-b s-border">
    <div :class="['relative container-app', compact ? 'py-10 sm:py-12' : 'py-12 sm:py-16 lg:py-20']">
      <nav v-if="crumbs.length" class="crumbs mb-6" aria-label="Breadcrumb">
        <Link href="/" class="inline-flex items-center gap-1.5">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 11l9-7 9 7M5 9.5V20h14V9.5" /></svg>
          Home
        </Link>
        <template v-for="c in crumbs" :key="c.label">
          <span class="sep">›</span>
          <Link v-if="c.href" :href="c.href">{{ c.label }}</Link>
          <span v-else class="s-heading font-medium" aria-current="page">{{ c.label }}</span>
        </template>
      </nav>
      <div :class="['grid gap-8 items-center', image && !compact ? 'lg:grid-cols-[1fr_22rem]' : 'lg:grid-cols-[1fr_auto]']">
        <div class="max-w-3xl">
          <p v-if="eyebrow" class="inline-flex items-center gap-2 rounded-full s-surface border s-border pl-3 pr-4 py-1.5 text-[14px] font-medium s-muted"><span class="w-2 h-2 rounded-full" style="background: var(--s-btn)"></span>{{ eyebrow }}</p>
          <h1 class="h-page mt-5">{{ title }}</h1>
          <p v-if="lead" class="mt-5 text-[1.12rem] leading-relaxed s-muted max-w-2xl">{{ lead }}</p>
          <slot name="below" />
        </div>
        <div v-if="image && !compact" class="hidden lg:block aspect-[4/3] overflow-hidden rounded-[22px]" style="box-shadow: var(--s-shadow)">
          <img :src="img(image, 640)" :srcset="srcset(image, 1024)" sizes="352px" alt="" class="img-cover" loading="lazy" decoding="async" />
        </div>
        <slot />
      </div>
    </div>
  </section>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import { img, srcset } from '@/utils/img';

defineProps({
  title: { type: String, required: true },
  eyebrow: String,
  lead: String,
  image: String,
  crumbs: { type: Array, default: () => [] },
  compact: Boolean,
});
</script>
