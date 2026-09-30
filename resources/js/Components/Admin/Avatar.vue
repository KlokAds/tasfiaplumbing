<template>
  <span :class="['inline-flex items-center justify-center rounded-full overflow-hidden shrink-0 font-bold text-white', sizes[size]]" :style="!image || broken ? { background: color } : null" :title="!image || broken ? name : null">
    <img v-if="image && !broken" :src="'/' + image" :alt="name || ''" class="w-full h-full object-cover" @error="broken = true" />
    <!-- No photo (or the file is gone): a default avatar instead of a broken image. -->
    <svg v-else viewBox="0 0 64 64" class="w-full h-full" aria-hidden="true">
      <circle cx="32" cy="26" r="11" fill="#fff" fill-opacity="0.95" />
      <path d="M21 25c-.6-8.3 4.3-14 11-14s11.6 5.7 11 14c-1.7-3.6-4.8-5.6-8.6-5.6h-4.8c-3.8 0-6.9 2-8.6 5.6z" fill="#1f2937" fill-opacity="0.55" />
      <path d="M10 64c1.2-12.4 10.4-20 22-20s20.8 7.6 22 20z" fill="#fff" fill-opacity="0.95" />
    </svg>
  </span>
</template>

<script setup>
import { computed, ref, watch } from 'vue';

const props = defineProps({
  name: String,
  image: String,
  size: { type: String, default: 'md' },
});

// A photo that no longer exists falls back to the default avatar.
const broken = ref(false);
watch(() => props.image, () => { broken.value = false; });

const sizes = { sm: 'w-8 h-8 text-xs', md: 'w-10 h-10 text-sm', lg: 'w-16 h-16 text-xl' };

const palette = ['#1a66d2', '#0f766e', '#4338ca', '#be123c', '#0369a1', '#7c3aed', '#15803d'];
const color = computed(() => {
  let h = 0;
  for (const ch of props.name || '') h = (h * 31 + ch.charCodeAt(0)) >>> 0;
  return palette[h % palette.length];
});
</script>
