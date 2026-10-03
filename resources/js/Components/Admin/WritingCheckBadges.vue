<template>
  <!-- Free writing check (App\Support\WritingCheck): AI-style phrases, text the same as another article -->
  <div v-if="check?.checked_at" class="text-[13px]">
    <div class="flex flex-wrap gap-1.5">
      <span v-if="check.duplicate" class="a-badge a-badge-danger" :title="`Same text as: ${check.duplicate.name}`">{{ check.duplicate.percent }}% same as another article</span>
      <span v-if="check.ai_phrases?.length" class="a-badge a-badge-warning" :title="check.ai_phrases.join(', ')">{{ check.ai_phrases.length }} AI-style phrase{{ check.ai_phrases.length === 1 ? '' : 's' }}</span>
      <span v-if="clean" class="a-badge a-badge-success" title="No AI-style phrases, no copy of another article on this site">Writing check OK</span>
    </div>
    <div v-if="detailed && !clean" class="mt-2 space-y-1.5 whitespace-normal">
      <p v-if="check.duplicate">
        <b>{{ check.duplicate.percent }}%</b> of the text is the same as
        <a :href="check.duplicate.path" target="_blank" rel="noopener" class="underline">{{ check.duplicate.name }}</a>.
        Two pages with the same text compete in Google: rewrite it, or update the older article instead.
      </p>
      <p v-if="check.ai_phrases?.length">AI-style phrases: <b>{{ check.ai_phrases.map((p) => `“${p}”`).join(', ') }}</b>. Plain, first-hand wording reads better and ranks better.</p>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  check: { type: Object, default: null },
  detailed: { type: Boolean, default: false },
});

const clean = computed(() => !props.check?.duplicate && !props.check?.ai_phrases?.length);
</script>
