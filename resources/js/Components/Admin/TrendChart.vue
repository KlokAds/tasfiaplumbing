<template>
  <div class="relative" @mouseleave="hover = null">
    <svg :viewBox="`0 0 ${W} ${H}`" class="w-full h-44" preserveAspectRatio="none" role="img" :aria-label="label">
      <line v-for="g in 4" :key="g" x1="0" :x2="W" :y1="(H - PAD) * g / 4" :y2="(H - PAD) * g / 4" stroke="var(--a-border)" stroke-width="1" vector-effect="non-scaling-stroke" />
      <path v-if="points.length > 1" :d="area" fill="var(--a-accent)" opacity="0.1" />
      <path v-if="points.length > 1" :d="line" fill="none" stroke="var(--a-accent)" stroke-width="2" vector-effect="non-scaling-stroke" stroke-linejoin="round" />
      <path v-if="second.length > 1" :d="line2" fill="none" stroke="var(--a-text-3, #94a3b8)" stroke-width="1.5" stroke-dasharray="4 3" vector-effect="non-scaling-stroke" />
      <rect v-for="(p, i) in points" :key="i" :x="x(i) - step / 2" y="0" :width="step" :height="H" fill="transparent" @mouseenter="hover = i" />
      <line v-if="hover !== null" :x1="x(hover)" :x2="x(hover)" y1="0" :y2="H - PAD" stroke="var(--a-border-2)" vector-effect="non-scaling-stroke" />
    </svg>
    <div class="flex justify-between text-[11px] a-subtle mt-1"><span>{{ fmtDate(points[0]?.date) }}</span><span>{{ fmtDate(points[points.length - 1]?.date) }}</span></div>
    <div v-if="hover !== null" class="absolute top-0 pointer-events-none rounded-lg a-panel border a-border shadow-lg px-2.5 py-1.5 text-xs whitespace-nowrap"
      :style="{ left: `clamp(0px, calc(${(x(hover) / W) * 100}% - 60px), calc(100% - 130px))` }">
      <p class="a-subtle">{{ fmtDate(points[hover].date) }}</p>
      <p class="font-bold">{{ points[hover].value.toLocaleString() }} {{ unit }}</p>
      <p v-if="second[hover]" class="a-muted">{{ second[hover].value.toLocaleString() }} {{ unit2 }}</p>
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
  points: { type: Array, default: () => [] },   // [{ date, value }]
  second: { type: Array, default: () => [] },   // optional dashed series (own scale)
  unit: { type: String, default: '' },
  unit2: { type: String, default: '' },
  label: { type: String, default: 'Trend' },
});

const W = 600; const H = 170; const PAD = 6;
const hover = ref(null);
const step = computed(() => (props.points.length > 1 ? W / (props.points.length - 1) : W));
const x = (i) => (props.points.length > 1 ? (i * W) / (props.points.length - 1) : W / 2);
const scale = (list) => {
  const max = Math.max(1, ...list.map((p) => p.value));
  return (v) => (H - PAD) - (v / max) * (H - PAD - 8);
};
const path = (list) => {
  const y = scale(list);
  return list.map((p, i) => `${i ? 'L' : 'M'}${x(i).toFixed(1)},${y(p.value).toFixed(1)}`).join(' ');
};
const line = computed(() => path(props.points));
const line2 = computed(() => path(props.second));
const area = computed(() => `${line.value} L${W},${H - PAD} L0,${H - PAD} Z`);
const fmtDate = (d) => (d ? new Date(d).toLocaleDateString('en-SG', { day: 'numeric', month: 'short' }) : '');
</script>
