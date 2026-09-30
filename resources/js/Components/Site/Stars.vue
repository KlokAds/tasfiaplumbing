<template>
  <span class="stars" role="img" :aria-label="`${value} out of 5 stars`">
    <svg v-for="i in 5" :key="i" :class="[size, i <= Math.round(value || 0) ? '' : 'opacity-25']" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
      <path d="M10 15.27L16.18 19l-1.64-7.03L20 7.24l-7.19-.61L10 0 7.19 6.63 0 7.24l5.46 4.73L3.82 19z" />
    </svg>
  </span>
</template>

<script setup>
defineProps({ value: { type: Number, default: 5 }, size: { type: String, default: 'w-4 h-4' } });
</script>
