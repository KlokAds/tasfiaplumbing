<template>
  <a v-if="hasWhatsapp" :href="wa(topic)" target="_blank" rel="noopener" :class="['btn', green ? 'btn-whatsapp' : 'btn-primary', size && `btn-${size}`]">
    <svg class="w-[1.15em] h-[1.15em] shrink-0" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path :d="WA" /></svg>
    <slot>Get a free quote</slot>
  </a>
  <Link v-else href="/contact" :class="['btn btn-primary', size && `btn-${size}`]"><slot>Get a free quote</slot></Link>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import { useContact } from '@/Composables/useContact';

/**
 * Main call-to-action: opens WhatsApp with a message ready for the page's topic.
 * Brand orange by default; `green` only where the label itself says WhatsApp.
 */
defineProps({ topic: { type: String, default: '' }, size: { type: String, default: '' }, green: Boolean });
const { wa, hasWhatsapp } = useContact();

const WA = 'M17.47 14.38c-.3-.15-1.76-.87-2.03-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.94 1.17-.17.2-.35.22-.64.07-.3-.15-1.26-.46-2.4-1.48-.89-.79-1.49-1.77-1.66-2.07-.17-.3-.02-.46.13-.61.13-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.02-.52-.08-.15-.67-1.62-.92-2.22-.24-.58-.49-.5-.67-.51h-.57c-.2 0-.52.07-.8.37-.27.3-1.04 1.02-1.04 2.49s1.07 2.89 1.22 3.09c.15.2 2.1 3.2 5.08 4.49.71.31 1.26.49 1.7.63.71.22 1.36.19 1.87.12.57-.09 1.76-.72 2-1.41.25-.7.25-1.29.17-1.41-.07-.13-.27-.2-.57-.35zM12.04 21.5h-.01a9.45 9.45 0 01-4.82-1.32l-.35-.2-3.58.94.96-3.49-.23-.36a9.43 9.43 0 01-1.45-5.03C2.56 6.83 6.8 2.6 12.04 2.6c2.54 0 4.92.99 6.72 2.78a9.42 9.42 0 012.78 6.71c0 5.23-4.25 9.41-9.5 9.41zm8.08-17.5A11.33 11.33 0 0012.04.67C5.74.67.61 5.8.61 12.1c0 2.01.53 3.98 1.53 5.71L.52 23.76l6.07-1.59a11.4 11.4 0 005.45 1.39h.01c6.3 0 11.43-5.13 11.43-11.43 0-3.05-1.19-5.92-3.36-8.08z';
</script>
