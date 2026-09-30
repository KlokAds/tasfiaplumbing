<template>
  <div v-if="items.length" class="faq">
    <details v-for="(f, i) in items" :key="i" :open="i === 0 && openFirst">
      <summary>{{ f.question }}</summary>
      <div class="faq-a">{{ f.answer }}</div>
    </details>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({ faqs: { type: Array, default: () => [] }, openFirst: { type: Boolean, default: true } });
const items = computed(() => props.faqs.filter(f => f.question && f.answer));
</script>
