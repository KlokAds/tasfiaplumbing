<template>
  <!--
    Calendar popover for dates and date + time. Same value format as the browser inputs it
    replaces ("2026-10-05" or "2026-10-05T09:30"), so forms and the server stay unchanged.
  -->
  <div ref="root" class="dpk relative">
    <button ref="trigger" type="button" :class="[$attrs.class, 'dpk-trigger']" :disabled="disabled" :aria-expanded="open" @click="toggle">
      <svg class="dpk-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
      <span :class="['dpk-value', !modelValue && 'dpk-placeholder']">{{ display || placeholder }}</span>
      <span v-if="modelValue && clearable" class="dpk-clear" role="button" aria-label="Clear" @click.stop="clear">×</span>
    </button>

    <Teleport :to="teleportTo" :disabled="!teleportTo">
      <div v-if="open" ref="panel" class="dpk-panel" :style="panelStyle" @keydown.esc="close">
        <div class="dpk-cal">
          <div class="dpk-head">
            <button type="button" class="dpk-nav" aria-label="Previous month" @click="shift(-1)">‹</button>
            <span class="dpk-month">{{ monthLabel }}</span>
            <button type="button" class="dpk-nav" aria-label="Next month" @click="shift(1)">›</button>
          </div>
          <div class="dpk-grid dpk-dow"><span v-for="d in dow" :key="d">{{ d }}</span></div>
          <div class="dpk-grid">
            <button v-for="c in cells" :key="c.key" type="button" :disabled="c.disabled"
              :class="['dpk-day', !c.inMonth && 'is-out', c.today && 'is-today', c.selected && 'is-selected']"
              @click="pickDay(c)">{{ c.day }}</button>
          </div>
          <div class="dpk-foot">
            <button type="button" class="dpk-link" :disabled="todayDisabled" @click="pickToday">Today</button>
            <button v-if="withTime" type="button" class="dpk-link" @click="pickTomorrowMorning">Tomorrow 9 am</button>
          </div>
        </div>
        <div v-if="withTime" class="dpk-times">
          <p class="dpk-times-title">Time</p>
          <div ref="timeList" class="dpk-time-list">
            <button v-for="t in times" :key="t.value" type="button" :disabled="t.disabled"
              :class="['dpk-time', t.value === timePart && 'is-selected']" @click="pickTime(t.value)">{{ t.label }}</button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';

defineOptions({ inheritAttrs: false });
const props = defineProps({
  modelValue: { type: String, default: '' },
  withTime: Boolean,          // date + time ("YYYY-MM-DDTHH:mm")
  min: String,                // same format as the value
  max: String,
  placeholder: { type: String, default: 'Choose a date' },
  clearable: { type: Boolean, default: true },
  disabled: Boolean,
  step: { type: Number, default: 15 }, // minutes between time options
});
const emit = defineEmits(['update:modelValue']);

const pad = (n) => String(n).padStart(2, '0');
const ymd = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
const parse = (s) => {
  if (!s) return null;
  const [date, time = '00:00'] = s.split('T');
  const [y, m, d] = date.split('-').map(Number);
  const [hh, mm] = time.split(':').map(Number);
  return y ? new Date(y, m - 1, d, hh || 0, mm || 0) : null;
};

const datePart = computed(() => (props.modelValue || '').split('T')[0]);
const timePart = computed(() => (props.modelValue || '').split('T')[1]?.slice(0, 5) || '');
const minDate = computed(() => (props.min || '').split('T')[0]);
const maxDate = computed(() => (props.max || '').split('T')[0]);

const display = computed(() => {
  const d = parse(props.modelValue);
  if (!d) return '';
  const date = d.toLocaleDateString('en-SG', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' });
  return props.withTime && timePart.value ? `${date}, ${fmtTime(timePart.value)}` : date;
});
function fmtTime(hm) {
  const [h, m] = hm.split(':').map(Number);
  return `${h % 12 || 12}:${pad(m)} ${h < 12 ? 'am' : 'pm'}`;
}

// ---- calendar
const view = ref(new Date());
const monthLabel = computed(() => view.value.toLocaleDateString('en-SG', { month: 'long', year: 'numeric' }));
const dow = ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'];
const cells = computed(() => {
  const first = new Date(view.value.getFullYear(), view.value.getMonth(), 1);
  const start = new Date(first);
  start.setDate(1 - ((first.getDay() + 6) % 7)); // Monday first
  const today = ymd(new Date());
  return Array.from({ length: 42 }, (_, i) => {
    const d = new Date(start);
    d.setDate(start.getDate() + i);
    const key = ymd(d);
    return {
      key, day: d.getDate(), date: key,
      inMonth: d.getMonth() === view.value.getMonth(),
      today: key === today,
      selected: key === datePart.value,
      disabled: !!((minDate.value && key < minDate.value) || (maxDate.value && key > maxDate.value)),
    };
  });
});
const todayDisabled = computed(() => {
  const t = ymd(new Date());
  return !!((minDate.value && t < minDate.value) || (maxDate.value && t > maxDate.value));
});
function shift(n) {
  view.value = new Date(view.value.getFullYear(), view.value.getMonth() + n, 1);
}

// ---- time
const times = computed(() => {
  const out = [];
  for (let m = 0; m < 24 * 60; m += props.step) {
    const value = `${pad(Math.floor(m / 60))}:${pad(m % 60)}`;
    const full = `${datePart.value}T${value}`;
    out.push({ value, label: fmtTime(value), disabled: !!(props.min && datePart.value && full < props.min.slice(0, 16)) });
  }
  return out;
});

function set(date, time) {
  if (!date) return emit('update:modelValue', '');
  emit('update:modelValue', props.withTime ? `${date}T${time || defaultTime(date)}` : date);
}
// First allowed slot of the day (never in the past when there is a minimum).
function defaultTime(date) {
  const ok = times.value.find((t) => !(props.min && `${date}T${t.value}` < props.min.slice(0, 16)) && t.value >= '09:00');
  return ok?.value || '09:00';
}
function pickDay(c) {
  if (c.disabled) return;
  set(c.date, timePart.value && !(props.min && `${c.date}T${timePart.value}` < props.min.slice(0, 16)) ? timePart.value : '');
  if (!props.withTime) close();
  else scrollTime();
}
function pickToday() {
  const t = new Date();
  view.value = new Date(t.getFullYear(), t.getMonth(), 1);
  pickDay({ date: ymd(t), disabled: false });
}
function pickTomorrowMorning() {
  const t = new Date();
  t.setDate(t.getDate() + 1);
  view.value = new Date(t.getFullYear(), t.getMonth(), 1);
  set(ymd(t), '09:00');
  close();
}
function pickTime(v) {
  set(datePart.value || ymd(new Date()), v);
  close();
}
function clear() {
  emit('update:modelValue', '');
}

// ---- popover
const open = ref(false);
const root = ref(null);
const trigger = ref(null);
const panel = ref(null);
const timeList = ref(null);
const teleportTo = ref(null);
const panelStyle = ref({});

function place() {
  if (!trigger.value) return;
  const r = trigger.value.getBoundingClientRect();
  const w = Math.min(props.withTime ? 440 : 300, window.innerWidth - 16);
  const h = props.withTime && window.innerWidth < 480 ? 520 : 340;
  const below = window.innerHeight - r.bottom - 8;
  const up = below < h && r.top > below;
  panelStyle.value = {
    position: 'fixed',
    width: `${w}px`,
    left: `${Math.min(Math.max(8, r.left), window.innerWidth - w - 8)}px`,
    ...(up ? { bottom: `${window.innerHeight - r.top + 4}px` } : { top: `${r.bottom + 4}px` }),
  };
}
function scrollTime() {
  nextTick(() => timeList.value?.querySelector('.is-selected')?.scrollIntoView({ block: 'center' }));
}
async function openPanel() {
  if (props.disabled) return;
  const d = parse(props.modelValue) || parse(props.min) || new Date();
  view.value = new Date(d.getFullYear(), d.getMonth(), 1);
  open.value = true;
  place();
  await nextTick();
  place();
  scrollTime();
}
const close = () => { open.value = false; };
const toggle = () => (open.value ? close() : openPanel());

const outside = (e) => {
  if (open.value && !root.value?.contains(e.target) && !panel.value?.contains(e.target)) close();
};
const reposition = (e) => {
  if (open.value && !(panel.value && e?.target && panel.value.contains?.(e.target))) place();
};
onMounted(() => {
  teleportTo.value = root.value?.closest('.admin-ui, .site') || null;
  document.addEventListener('mousedown', outside);
  document.addEventListener('touchstart', outside, { passive: true });
  window.addEventListener('resize', reposition);
  window.addEventListener('scroll', reposition, true);
});
onBeforeUnmount(() => {
  document.removeEventListener('mousedown', outside);
  document.removeEventListener('touchstart', outside);
  window.removeEventListener('resize', reposition);
  window.removeEventListener('scroll', reposition, true);
});
</script>

<style scoped>
.dpk-trigger { display: flex; align-items: center; gap: 0.55rem; width: 100%; text-align: left; cursor: pointer; background-image: none !important; }
.dpk-trigger:disabled { opacity: 0.6; cursor: not-allowed; }
.dpk-icon { width: 1rem; height: 1rem; flex-shrink: 0; opacity: 0.55; }
.dpk-value { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.dpk-placeholder { opacity: 0.55; }
.dpk-clear { width: 1.25rem; height: 1.25rem; display: grid; place-items: center; border-radius: 6px; opacity: 0.55; font-size: 1rem; }
.dpk-clear:hover { opacity: 1; background: var(--a-panel-3, var(--s-surface-2, #f1f5f9)); }

.dpk-panel {
  z-index: 70; display: flex; gap: 0; overflow: hidden;
  border-radius: 14px; border: 1px solid var(--a-border, var(--s-border, #e5e7eb));
  background: var(--a-panel, var(--s-surface, #fff)); color: var(--a-text, var(--s-text, #0f172a));
  box-shadow: 0 18px 44px -14px rgba(0, 0, 0, 0.4); animation: dpk-in 0.12s ease-out;
}
@keyframes dpk-in { from { opacity: 0; transform: translateY(-4px); } }
@media (max-width: 479px) { .dpk-panel { flex-direction: column; } .dpk-times { border-left: 0 !important; border-top: 1px solid var(--a-border, var(--s-border, #e5e7eb)); } .dpk-time-list { max-height: 9.5rem !important; display: grid !important; grid-template-columns: repeat(3, 1fr); gap: 4px; } }
.dpk-cal { padding: 12px; flex: 1; min-width: 0; }
.dpk-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; }
.dpk-month { font-weight: 700; font-size: 14px; }
.dpk-nav { width: 30px; height: 30px; border-radius: 8px; font-size: 18px; line-height: 1; }
.dpk-nav:hover { background: var(--a-panel-3, var(--s-surface-2, #f1f5f9)); }
.dpk-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 2px; }
.dpk-dow span { text-align: center; font-size: 11px; font-weight: 600; opacity: 0.5; padding: 4px 0; }
.dpk-day { height: 34px; border-radius: 8px; font-size: 13px; font-variant-numeric: tabular-nums; }
.dpk-day:hover:not(:disabled) { background: var(--a-panel-3, var(--s-surface-2, #f1f5f9)); }
.dpk-day.is-out { opacity: 0.35; }
.dpk-day.is-today { box-shadow: inset 0 0 0 1px var(--a-accent, var(--s-accent, #1a66d2)); font-weight: 700; }
.dpk-day.is-selected { background: var(--a-accent, var(--s-btn, #1452b0)) !important; color: #fff; font-weight: 700; opacity: 1; }
.dpk-day:disabled { opacity: 0.2; cursor: not-allowed; }
.dpk-foot { display: flex; justify-content: space-between; margin-top: 8px; padding-top: 8px; border-top: 1px solid var(--a-border, var(--s-border, #e5e7eb)); }
.dpk-link { font-size: 12px; font-weight: 600; color: var(--a-accent-text, var(--s-accent-text, #1452b0)); padding: 4px 6px; border-radius: 6px; }
.dpk-link:disabled { opacity: 0.4; }
.dpk-times { width: 8.5rem; flex-shrink: 0; border-left: 1px solid var(--a-border, var(--s-border, #e5e7eb)); display: flex; flex-direction: column; }
@media (max-width: 479px) { .dpk-times { width: auto; } }
.dpk-times-title { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; opacity: 0.55; padding: 12px 12px 6px; }
.dpk-time-list { overflow-y: auto; overscroll-behavior: contain; padding: 0 6px 8px; max-height: 17rem; display: flex; flex-direction: column; gap: 2px; }
.dpk-time { font-size: 13px; padding: 6px 8px; border-radius: 8px; text-align: left; font-variant-numeric: tabular-nums; }
.dpk-time:hover:not(:disabled) { background: var(--a-panel-3, var(--s-surface-2, #f1f5f9)); }
.dpk-time.is-selected { background: var(--a-accent, var(--s-btn, #1452b0)); color: #fff; font-weight: 600; }
.dpk-time:disabled { opacity: 0.25; cursor: not-allowed; }
</style>
