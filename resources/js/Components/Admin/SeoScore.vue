<template>
  <div class="relative inline-block" @mouseleave="open = false">
    <button
      type="button"
      @click="open = !open"
      :class="['inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-bold border', tone]"
      :title="`SEO score ${seo.score}/100`"
    >
      SEO {{ seo.score }}
      <span v-if="seo.issues.length" class="font-semibold opacity-80">· {{ seo.issues.length }}</span>
    </button>
    <div
      v-if="open && seo.issues.length"
      :class="['absolute z-30 mt-1 w-72 admin-card p-3 space-y-1.5 text-left shadow-lg', alignRight ? 'right-0' : 'left-0']"
    >
      <div v-for="(issue, i) in seo.issues" :key="i" class="flex gap-2 text-xs leading-snug">
        <span :class="issue.level === 'error' ? 'a-text-danger' : 'a-text-warning'">{{ issue.level === 'error' ? '●' : '○' }}</span>
        <span class="a-muted">{{ issue.message }}</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
  seo: { type: Object, required: true },
  alignRight: Boolean,
});
const open = ref(false);

const tone = computed(() => {
  if (props.seo.errors > 0 || props.seo.score < 50) return 'a-tint-danger a-text-danger border-[color-mix(in_srgb,var(--a-danger)_35%,transparent)]   ';
  if (props.seo.score < 80) return 'a-tint-warning a-text-warning border-[color-mix(in_srgb,var(--a-warning)_35%,transparent)]   ';
  return 'a-tint-success a-text-success border-[color-mix(in_srgb,var(--a-success)_35%,transparent)]   ';
});
</script>
