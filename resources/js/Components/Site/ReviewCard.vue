<template>
  <figure class="card p-6 flex flex-col h-full">
    <div class="flex items-center justify-between gap-3">
      <Stars :value="review.rating" />
      <svg v-if="review.source === 'google'" class="w-4 h-4" viewBox="0 0 48 48" aria-label="Google review"><path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3C33.7 32.7 29.2 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.3-.1-2.4-.4-3.5z"/><path fill="#FF3D00" d="M6.3 14.7l6.6 4.8C14.7 15.1 19 12 24 12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 16.3 4 9.7 8.3 6.3 14.7z"/><path fill="#4CAF50" d="M24 44c5.2 0 9.9-2 13.4-5.2l-6.2-5.2C29.2 35.1 26.7 36 24 36c-5.2 0-9.6-3.3-11.3-7.9l-6.5 5C9.5 39.6 16.2 44 24 44z"/><path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-.8 2.2-2.2 4.2-4.1 5.6l6.2 5.2C37 38.2 44 33 44 24c0-1.3-.1-2.4-.4-3.5z"/></svg>
    </div>
    <blockquote class="mt-3 text-[15.5px] leading-relaxed s-text flex-1">
      <p :class="clamp && !expanded ? 'line-clamp-5' : ''">{{ review.text }}</p>
      <button v-if="clamp && review.text?.length > 260" type="button" @click="expanded = !expanded" class="mt-1 text-[14px] link">{{ expanded ? 'Show less' : 'Read more' }}</button>
    </blockquote>
    <div v-if="review.reply" class="mt-4 rounded-xl s-surface-2 px-4 py-3 text-[14px] leading-relaxed">
      <p class="font-semibold s-heading text-[13px]">Reply from the owner</p>
      <p :class="['mt-1 s-muted', clamp && !expanded ? 'line-clamp-3' : '']">{{ review.reply }}</p>
    </div>
    <figcaption class="mt-5 pt-4 border-t s-border flex items-center gap-3">
      <img v-if="review.photo" :src="review.photo" :alt="review.name" class="w-10 h-10 rounded-full object-cover" loading="lazy" referrerpolicy="no-referrer" />
      <span v-else class="w-10 h-10 rounded-full flex items-center justify-center text-[15px] font-bold text-white shrink-0" :style="{ background: color }">{{ (review.name || '?').charAt(0).toUpperCase() }}</span>
      <div class="min-w-0">
        <component :is="review.author_url ? 'a' : 'p'" :href="review.author_url" target="_blank" rel="noopener nofollow" class="block text-[15px] font-semibold s-heading truncate">{{ review.name }}</component>
        <p class="text-[13px] s-subtle truncate">{{ [review.job, review.location, review.when].filter(Boolean).join(' · ') || 'Singapore' }}</p>
      </div>
    </figcaption>
  </figure>
</template>

<script setup>
import { computed, ref } from 'vue';
import Stars from '@/Components/Site/Stars.vue';

const props = defineProps({ review: { type: Object, required: true }, clamp: { type: Boolean, default: true } });
const expanded = ref(false);
const palette = ['#1a66d2', '#0e7490', '#0f3f8a', '#0369a1', '#4338ca', '#0f766e', '#1d4ed8'];
const color = computed(() => palette[[...(props.review.name || 'x')].reduce((a, c) => a + c.charCodeAt(0), 0) % palette.length]);
</script>
