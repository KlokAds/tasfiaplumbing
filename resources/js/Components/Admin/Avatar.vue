<template>
  <span :class="['inline-flex items-center justify-center rounded-full overflow-hidden shrink-0 font-bold text-white', sizes[size]]" :style="!image ? { background: color } : null">
    <img v-if="image" :src="'/' + image" :alt="name || ''" class="w-full h-full object-cover" />
    <span v-else>{{ initials }}</span>
  </span>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  name: String,
  image: String,
  size: { type: String, default: 'md' },
});

const sizes = { sm: 'w-8 h-8 text-xs', md: 'w-10 h-10 text-sm', lg: 'w-16 h-16 text-xl' };

const initials = computed(() => (props.name || '?').split(/\s+/).filter(Boolean).slice(0, 2).map(w => w[0].toUpperCase()).join(''));

const palette = ['#1a66d2', '#0f766e', '#4338ca', '#be123c', '#0369a1', '#7c3aed', '#15803d'];
const color = computed(() => {
  let h = 0;
  for (const ch of props.name || '') h = (h * 31 + ch.charCodeAt(0)) >>> 0;
  return palette[h % palette.length];
});
</script>
