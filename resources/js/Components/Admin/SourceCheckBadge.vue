<template>
  <!-- Source check (App\Support\SourceCheck): sentences of the article found on other websites -->
  <div v-if="on || result" class="text-[13px]">
    <button v-if="result?.found" type="button" @click="open = !open" class="a-badge a-badge-danger" :aria-expanded="open">
      {{ result.found }} of {{ result.total }} sentences found online {{ open ? '▴' : '▾' }}
    </button>
    <span v-else-if="result?.error" class="a-badge a-badge-warning" :title="result.error">Source check failed</span>
    <span v-else-if="result && result.total" class="a-badge a-badge-success" :title="`Checked ${result.total} sentences on the web`">Not found online</span>
    <span v-else-if="result" class="a-badge" title="Too short to check">Source check: too short</span>
    <span v-else class="a-badge" title="Checked within a few minutes">Source check: waiting</span>

    <div v-if="result?.found && (open || detailed)" class="mt-2 space-y-2 text-left whitespace-normal">
      <div v-for="(m, i) in result.matches" :key="i" class="rounded-lg border a-border px-3 py-2">
        <p class="italic">“{{ m.sentence }}”</p>
        <p class="mt-1 flex flex-wrap gap-x-3 gap-y-1">
          <a v-for="u in m.urls" :key="u" :href="u" target="_blank" rel="noopener noreferrer nofollow" class="underline break-all">{{ host(u) }}</a>
        </p>
      </div>
      <p class="a-muted">A short common phrase can match by chance. Whole sentences on other sites usually mean the text was copied.</p>
    </div>
    <p v-else-if="result?.error && detailed" class="mt-1 a-muted">{{ result.error }}</p>
  </div>
</template>

<script setup>
import { ref } from 'vue';

defineProps({
  result: { type: Object, default: null },
  on: { type: Boolean, default: false },
  detailed: { type: Boolean, default: false },
});

const open = ref(false);

function host(url) {
  try {
    return new URL(url).hostname.replace(/^www\./, '');
  } catch (e) {
    return url;
  }
}
</script>
