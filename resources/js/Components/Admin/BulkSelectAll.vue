<template>
  <!-- "Select all" row for lists and card grids (tables use a checkbox in their header). -->
  <label :class="['flex items-center gap-2.5 text-[13px] cursor-pointer select-none', padded && 'px-4 py-2.5 border-b a-border a-panel-2']">
    <input type="checkbox" :checked="bulk.all.value" :indeterminate.prop="bulk.some.value" @change="bulk.toggleAll()" aria-label="Select all on this page" />
    <span class="font-semibold">{{ bulk.all.value ? 'All selected' : 'Select all' }}</span>
    <span class="a-subtle">{{ bulk.count.value ? `${bulk.count.value} selected` : hint }}</span>
  </label>
</template>

<script setup>
defineProps({
  bulk: { type: Object, required: true },
  padded: { type: Boolean, default: true },
  hint: { type: String, default: 'on this page' },
});
</script>
