<template>
  <div class="relative" @mouseleave="hover = null">
    <div class="flex">
      <!-- Left scale (main line) -->
      <div class="w-9 shrink-0 h-44 flex flex-col justify-between text-[10.5px] a-subtle tabular-nums text-right pr-2 -mt-1.5" aria-hidden="true">
        <span v-for="t in ticks(max1)" :key="'a' + t">{{ short(t) }}</span>
      </div>
      <svg :viewBox="`0 0 ${W} ${H}`" class="flex-1 min-w-0 h-44 overflow-visible" preserveAspectRatio="none" role="img" :aria-label="label">
        <line v-for="g in 5" :key="g" x1="0" :x2="W" :y1="gridY(g - 1)" :y2="gridY(g - 1)" stroke="var(--a-border)" stroke-width="1" vector-effect="non-scaling-stroke" />
        <path v-if="points.length > 1" :d="area" fill="var(--a-accent)" opacity="0.1" />
        <path v-if="second.length > 1" :d="line2" fill="none" stroke="var(--a-text-3, #94a3b8)" stroke-width="1.5" stroke-dasharray="4 3" vector-effect="non-scaling-stroke" />
        <path v-if="points.length > 1" :d="line" fill="none" stroke="var(--a-accent)" stroke-width="2.25" vector-effect="non-scaling-stroke" stroke-linejoin="round" />
        <rect v-for="(p, i) in points" :key="i" :x="x(i) - step / 2" y="0" :width="step" :height="H" fill="transparent" @mouseenter="hover = i" />
        <line v-if="hover !== null" :x1="x(hover)" :x2="x(hover)" y1="0" :y2="H - PAD" stroke="var(--a-border-2)" vector-effect="non-scaling-stroke" />
      </svg>
      <!-- Right scale (dashed line) -->
      <div v-if="second.length > 1" class="w-10 shrink-0 h-44 flex flex-col justify-between text-[10.5px] a-subtle tabular-nums pl-2 -mt-1.5" aria-hidden="true">
        <span v-for="t in ticks(max2)" :key="'b' + t">{{ short(t) }}</span>
      </div>
    </div>
    <!-- Dots: drawn in HTML so they stay round however wide the chart is -->
    <div class="absolute top-0 h-44 pointer-events-none" :style="{ left: '2.25rem', right: second.length > 1 ? '2.5rem' : '0' }" aria-hidden="true">
      <template v-if="points.length <= 45">
        <span v-for="(p, i) in points" :key="'d' + i" class="absolute w-2 h-2 -ml-1 -mt-1 rounded-full border-2 border-[var(--a-accent)]"
          :style="{ left: `${(x(i) / W) * 100}%`, top: `${(y1(p.value) / H) * 100}%`, background: hover === i ? 'var(--a-accent)' : 'var(--a-panel)' }"></span>
      </template>
      <span v-else-if="hover !== null" class="absolute w-2.5 h-2.5 -ml-[5px] -mt-[5px] rounded-full bg-[var(--a-accent)] ring-2 ring-[var(--a-panel)]"
        :style="{ left: `${(x(hover) / W) * 100}%`, top: `${(y1(points[hover].value) / H) * 100}%` }"></span>
    </div>
    <!-- Dates under the chart -->
    <div class="relative h-5 mt-1.5 text-[11px] a-subtle" :style="{ marginLeft: '2.25rem', marginRight: second.length > 1 ? '2.5rem' : '0' }" aria-hidden="true">
      <span v-for="i in dateTicks" :key="'t' + i" class="absolute whitespace-nowrap"
        :style="{ left: `${(x(i) / W) * 100}%`, transform: i === 0 ? 'none' : i === points.length - 1 ? 'translateX(-100%)' : 'translateX(-50%)' }">{{ fmtDate(points[i]?.date) }}</span>
    </div>
    <div v-if="hover !== null" class="absolute top-0 pointer-events-none rounded-lg a-panel border a-border shadow-lg px-2.5 py-1.5 text-xs whitespace-nowrap z-10"
      :style="{ left: `clamp(0px, calc(${(x(hover) / W) * 100}% - 40px), calc(100% - 150px))` }">
      <p class="a-subtle">{{ fmtDate(points[hover].date, true) }}</p>
      <p class="font-bold">{{ points[hover].value.toLocaleString() }} {{ unit }}</p>
      <p v-if="second[hover]" class="a-muted">{{ second[hover].value.toLocaleString() }} {{ unit2 }}</p>
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
  points: { type: Array, default: () => [] },   // [{ date, value }]
  second: { type: Array, default: () => [] },   // optional dashed series (own scale, shown on the right)
  unit: { type: String, default: '' },
  unit2: { type: String, default: '' },
  label: { type: String, default: 'Trend' },
});

const W = 600; const H = 170; const PAD = 2;
const hover = ref(null);
const step = computed(() => (props.points.length > 1 ? W / (props.points.length - 1) : W));
const x = (i) => (props.points.length > 1 ? (i * W) / (props.points.length - 1) : W / 2);

// A "nice" top value (1, 2, 5, 10, 20, 50 …) so the scale reads well.
const nice = (v) => {
  if (v <= 4) return 4;
  const p = 10 ** Math.floor(Math.log10(v));
  const m = [1, 2, 2.5, 5, 10].find((f) => f * p >= v);
  return m * p;
};
const max1 = computed(() => nice(Math.max(0, ...props.points.map((p) => p.value))));
const max2 = computed(() => nice(Math.max(0, ...props.second.map((p) => p.value))));
const yFor = (max) => (v) => (H - PAD) - (v / max) * (H - PAD * 2);
const y1 = (v) => yFor(max1.value)(v);
const gridY = (i) => PAD + ((H - PAD * 2) * i) / 4;
const ticks = (max) => [max, max * 0.75, max * 0.5, max * 0.25, 0];
const short = (v) => (v >= 1000 ? `${Math.round((v / 1000) * 10) / 10}k` : Number.isInteger(v) ? v : v.toFixed(1));

const path = (list, max) => {
  const y = yFor(max);
  return list.map((p, i) => `${i ? 'L' : 'M'}${x(i).toFixed(1)},${y(p.value).toFixed(1)}`).join(' ');
};
const line = computed(() => path(props.points, max1.value));
const line2 = computed(() => path(props.second, max2.value));
const area = computed(() => `${line.value} L${W},${H - PAD} L0,${H - PAD} Z`);

// About five dates along the bottom, always including the first and last.
const dateTicks = computed(() => {
  const n = props.points.length;
  if (n <= 1) return n ? [0] : [];
  const count = Math.min(n, 5);
  return [...new Set(Array.from({ length: count }, (_, k) => Math.round((k * (n - 1)) / (count - 1))))];
});
const fmtDate = (d, year = false) => (d ? new Date(d + 'T00:00:00').toLocaleDateString('en-SG', year ? { day: 'numeric', month: 'short', year: 'numeric' } : { day: 'numeric', month: 'short' }) : '');
</script>
