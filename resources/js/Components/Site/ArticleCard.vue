<template>
  <Link :href="`/blogs/${article.slug}`" class="lift group h-full rounded-[22px] border s-border s-surface overflow-hidden flex flex-col">
    <div class="aspect-[16/9] overflow-hidden s-surface-2">
      <img :src="article.image ? img(article.image, 640) : '/logo.png'" :srcset="srcset(article.image, 1024)" sizes="(min-width: 1024px) 380px, (min-width: 640px) 50vw, 100vw" :alt="article.name" loading="lazy" decoding="async" width="640" height="360" class="img-cover " />
    </div>
    <div class="p-6 flex-1 flex flex-col">
      <p class="text-[14px] s-subtle">
        <span v-if="article.primary_service" class="s-accent font-semibold">{{ article.primary_service.name }} · </span>
        <time v-if="article.published_at" :datetime="article.published_at">{{ date(article.published_at) }}</time>
      </p>
      <h3 class="mt-2 text-[1.15rem] font-semibold leading-snug s-heading line-clamp-3 group-hover:text-[var(--s-accent-text)] transition-colors" style="font-family: var(--font-display)">{{ cleanTitle(article.name) }}</h3>
      <p v-if="article.excerpt" class="mt-2 text-[15px] s-muted line-clamp-3 leading-relaxed">{{ article.excerpt }}</p>
      <span class="mt-auto pt-5 text-[15px] font-semibold s-accent">Read the guide →</span>
    </div>
  </Link>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import { img, srcset } from '@/utils/img';
import { cleanTitle } from '@/utils/cleanTitle';

defineProps({ article: { type: Object, required: true } });
const date = d => new Date(d).toLocaleDateString('en-SG', { day: 'numeric', month: 'short', year: 'numeric' });
</script>
