<template>
  <section class="rounded-xl border a-border overflow-hidden">
    <div class="flex items-center justify-between gap-3 px-4 py-3 border-b a-border a-panel-2">
      <div>
        <span class="text-sm font-bold">FAQs</span>
        <span :class="['ml-2 text-xs font-semibold', filled >= minimum ? 'a-text-success' : 'a-text-warning']">{{ filled }} / min {{ minimum }}</span>
        <p v-if="hint" class="text-xs a-muted mt-0.5">{{ hint }}</p>
      </div>
      <button type="button" @click="add" class="admin-btn-secondary a-btn-sm shrink-0">+ Add FAQ</button>
    </div>
    <div class="p-3 space-y-2">

    <div v-for="(faq, i) in modelValue" :key="i" class="a-panel border a-border rounded-xl p-3 space-y-2">
      <div class="flex items-center gap-2">
        <span class="text-[11px] font-bold a-subtle w-5">{{ i + 1 }}</span>
        <input v-model="faq.question" type="text" placeholder="Question customers actually ask, e.g. How much does water heater repair cost in Singapore?" class="admin-input flex-1 font-medium" />
        <div class="flex items-center">
          <button type="button" :disabled="i === 0" @click="move(i, -1)" class="a-btn-ghost a-btn-sm !px-2 disabled:opacity-30" title="Move up">↑</button>
          <button type="button" :disabled="i === modelValue.length - 1" @click="move(i, 1)" class="a-btn-ghost a-btn-sm !px-2 disabled:opacity-30" title="Move down">↓</button>
          <button type="button" @click="remove(i)" class="a-btn-ghost a-danger a-btn-sm !px-2" title="Remove">✕</button>
        </div>
      </div>
      <textarea v-model="faq.answer" rows="2" placeholder="Direct answer first (price range, time, area), then detail." class="admin-input"></textarea>
    </div>

    <p v-if="!modelValue.length" class="px-1 py-3 text-xs a-subtle">No FAQs yet. Start with the price question.</p>
    </div>
  </section>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  modelValue: { type: Array, required: true },
  minimum: { type: Number, default: 0 },
  hint: String,
});
const emit = defineEmits(['update:modelValue']);

const filled = computed(() => props.modelValue.filter(f => f.question?.trim() && f.answer?.trim()).length);

function add() {
  emit('update:modelValue', [...props.modelValue, { question: '', answer: '' }]);
}
function remove(i) {
  emit('update:modelValue', props.modelValue.filter((_, idx) => idx !== i));
}
function move(i, dir) {
  const list = [...props.modelValue];
  [list[i], list[i + dir]] = [list[i + dir], list[i]];
  emit('update:modelValue', list);
}
</script>
