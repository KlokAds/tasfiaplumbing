<template>
  <!--
    "From Media library" for any image field. Emits { file, url }: put `file` in the form field
    (as if it were uploaded) and `url` in the preview. The server swaps the placeholder for the
    existing library file, so nothing is copied.
  -->
  <button type="button" :class="['inline-flex items-center gap-1.5 text-[12px] font-semibold a-accent hover:underline', $attrs.class]" :disabled="disabled" @click.stop.prevent="open = true">
    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.6-4.6a2 2 0 012.8 0L16 16m-2-2l1.6-1.6a2 2 0 012.8 0L20 14M14 8h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
    {{ label }}
  </button>
  <MediaPicker :show="open" mode="select" @close="open = false" @select="pick" />
</template>

<script setup>
import { ref } from 'vue';
import MediaPicker from '@/Components/Admin/MediaPicker.vue';
import { libraryFile } from '@/utils/libraryFile';

defineOptions({ inheritAttrs: false });
defineProps({ label: { type: String, default: 'Choose from Media library' }, disabled: Boolean });
const emit = defineEmits(['pick']);
const open = ref(false);

function pick({ path, url }) {
  emit('pick', { file: libraryFile(path), url, path });
}
</script>
