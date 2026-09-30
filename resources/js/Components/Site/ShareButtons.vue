<template>
  <div class="flex items-center gap-1.5" role="group" aria-label="Share">
    <a v-for="s in links" :key="s.label" :href="s.href" target="_blank" rel="noopener" :aria-label="`Share on ${s.label}`" :title="`Share on ${s.label}`"
      class="w-9 h-9 rounded-lg border s-border s-muted hover:text-[var(--s-heading)] hover:border-[var(--s-border-2)] flex items-center justify-center transition">
      <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path :d="s.icon" /></svg>
    </a>
    <button type="button" @click="copy" :aria-label="copied ? 'Link copied' : 'Copy link'" :title="copied ? 'Copied' : 'Copy link'"
      class="h-9 px-3 rounded-lg border s-border s-muted hover:text-[var(--s-heading)] hover:border-[var(--s-border-2)] inline-flex items-center gap-1.5 text-[13.5px] font-semibold transition">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M10 14a4 4 0 005.66 0l3-3a4 4 0 10-5.66-5.66l-1 1M14 10a4 4 0 00-5.66 0l-3 3a4 4 0 105.66 5.66l1-1" /></svg>
      {{ copied ? 'Copied' : 'Copy link' }}
    </button>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue';

const props = defineProps({ title: { type: String, default: '' } });
const url = computed(() => (typeof window !== 'undefined' ? window.location.origin + window.location.pathname : ''));
const copied = ref(false);

const links = computed(() => {
  const u = encodeURIComponent(url.value);
  const t = encodeURIComponent(props.title);
  return [
    { label: 'WhatsApp', href: `https://wa.me/?text=${t}%20${u}`, icon: 'M12.04 2.6C6.8 2.6 2.56 6.83 2.56 12.04c0 1.8.5 3.5 1.45 5.03l-.96 3.49 3.58-.94a9.45 9.45 0 004.82 1.32h.01c5.25 0 9.5-4.18 9.5-9.41a9.42 9.42 0 00-2.78-6.71 9.4 9.4 0 00-6.72-2.78z' },
    { label: 'Facebook', href: `https://www.facebook.com/sharer/sharer.php?u=${u}`, icon: 'M24 12.07C24 5.45 18.63.07 12 .07S0 5.45 0 12.07c0 5.99 4.39 10.95 10.13 11.85v-8.38H7.08v-3.47h3.05V9.43c0-3.01 1.79-4.67 4.53-4.67 1.31 0 2.69.24 2.69.24v2.95h-1.52c-1.49 0-1.96.93-1.96 1.87v2.25h3.33l-.53 3.47h-2.8v8.38C19.61 23.02 24 18.06 24 12.07z' },
    { label: 'LinkedIn', href: `https://www.linkedin.com/sharing/share-offsite/?url=${u}`, icon: 'M20.45 20.45h-3.55v-5.57c0-1.33-.03-3.04-1.85-3.04-1.85 0-2.14 1.45-2.14 2.94v5.67H9.35V9h3.41v1.56h.05c.48-.9 1.64-1.85 3.37-1.85 3.6 0 4.27 2.37 4.27 5.46v6.28zM5.34 7.43a2.06 2.06 0 110-4.13 2.06 2.06 0 010 4.13zM7.12 20.45H3.56V9h3.56v11.45zM22.22 0H1.77C.79 0 0 .77 0 1.73v20.54C0 23.23.79 24 1.77 24h20.45c.98 0 1.78-.77 1.78-1.73V1.73C24 .77 23.2 0 22.22 0z' },
  ];
});

async function copy() {
  try {
    await navigator.clipboard.writeText(url.value);
    copied.value = true;
    setTimeout(() => { copied.value = false; }, 2000);
  } catch (e) { /* clipboard blocked */ }
}
</script>
