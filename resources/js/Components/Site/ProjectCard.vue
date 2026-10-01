<template>
  <figure class="h-full rounded-[22px] border s-border s-surface overflow-hidden flex flex-col">
    <div class="relative aspect-[4/3] overflow-hidden s-surface-2">
      <img :src="project.image ? img(project.image, 640) : '/logo.png'" :srcset="srcset(project.image, 1024)" sizes="(min-width: 1024px) 380px, (min-width: 640px) 50vw, 100vw" :alt="project.name" loading="lazy" decoding="async" width="640" height="480" class="img-cover " />
      <a v-if="project.video" :href="project.video" target="_blank" rel="noopener" class="absolute top-3 right-3 chip !bg-black/65 !text-white !border-transparent">▶ Video</a>
    </div>
    <figcaption class="p-6 flex-1 flex flex-col">
      <p class="text-[1.1rem] font-semibold s-heading leading-snug line-clamp-2" style="font-family: var(--font-display)">{{ project.name }}</p>
      <p v-if="details" class="mt-1 text-[13.5px] s-subtle">{{ details }}</p>
      <p v-if="project.summary" class="mt-2 text-[14px] s-muted line-clamp-3 leading-relaxed">{{ project.summary }}</p>
      <Link v-if="project.service && showService" :href="`/service/${project.service.slug}`" class="mt-auto pt-3 text-[13.5px] link">{{ project.service.name }} →</Link>
    </figcaption>
  </figure>
</template>

<script setup>
import { monthYear } from '@/utils/fmt';
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { img, srcset } from '@/utils/img';

const props = defineProps({ project: { type: Object, required: true }, showService: { type: Boolean, default: true } });
const details = computed(() => [
  props.project.area || props.project.location?.name,
  props.project.property_type,
  props.project.completed_on && monthYear(props.project.completed_on),
].filter(Boolean).join(' · '));
</script>
