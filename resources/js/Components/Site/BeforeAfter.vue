<template>
  <figure class="select-none">
    <div ref="box" class="relative overflow-hidden rounded-2xl border s-border s-surface-2 aspect-[4/3] cursor-ew-resize touch-pan-y"
      @pointerdown="start" @pointermove="move" @pointerup="stop" @pointercancel="stop" @pointerleave="stop">
      <!-- After (full) -->
      <img :src="img(after, 1280)" :srcset="srcset(after, 1600)" sizes="(min-width: 1024px) 760px, 100vw" :alt="`${title}: after`" class="absolute inset-0 w-full h-full object-cover" :loading="eager ? 'eager' : 'lazy'" :fetchpriority="eager ? 'high' : 'auto'" decoding="async" draggable="false" />
      <!-- Before (clipped) -->
      <img :src="img(before, 1280)" :srcset="srcset(before, 1600)" sizes="(min-width: 1024px) 760px, 100vw" :alt="`${title}: before`" class="absolute inset-0 w-full h-full object-cover" :style="{ clipPath: `inset(0 ${100 - pos}% 0 0)` }" :loading="eager ? 'eager' : 'lazy'" decoding="async" draggable="false" />

      <span class="absolute top-3 left-3 chip !bg-black/60 !text-white !border-white/15 backdrop-blur">Before</span>
      <span class="absolute top-3 right-3 chip !bg-[#1452b0] !text-white !border-transparent">After</span>

      <!-- Handle -->
      <div class="absolute inset-y-0 w-0.5 bg-white shadow-[0_0_0_1px_rgba(0,0,0,0.15)]" :style="{ left: pos + '%' }">
        <span class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-11 h-11 rounded-full bg-white text-[#0c1322] shadow-lg flex items-center justify-center">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7l-5 5 5 5M16 7l5 5-5 5" /></svg>
        </span>
      </div>

      <input v-model.number="pos" type="range" min="0" max="100" step="1" class="absolute inset-0 w-full h-full opacity-0 cursor-ew-resize" :aria-label="`Compare before and after: ${title}`" />
    </div>
    <figcaption class="mt-2.5 text-[14px] s-subtle text-center">Drag the handle to compare before and after</figcaption>
  </figure>
</template>

<script setup>
import { ref } from 'vue';
import { img, srcset } from '@/utils/img';

defineProps({ before: String, after: String, title: { type: String, default: '' }, eager: Boolean });

const pos = ref(50);
const box = ref(null);
let dragging = false;

function setFrom(e) {
  const r = box.value.getBoundingClientRect();
  pos.value = Math.min(100, Math.max(0, ((e.clientX - r.left) / r.width) * 100));
}
function start(e) { dragging = true; setFrom(e); }
function move(e) { if (dragging) setFrom(e); }
function stop() { dragging = false; }
</script>
