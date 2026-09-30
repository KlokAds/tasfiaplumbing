<template>
  <Teleport to="body">
    <transition enter-active-class="transition duration-150" enter-from-class="opacity-0" leave-active-class="transition duration-100" leave-to-class="opacity-0">
      <div v-if="index !== null" class="fixed inset-0 z-[100] bg-black/90 flex items-center justify-center" role="dialog" aria-modal="true" aria-label="Photo viewer" @click.self="close">
        <img :src="images[index].src" :alt="images[index].alt" class="max-w-[92vw] max-h-[86vh] object-contain rounded-lg shadow-2xl select-none" />
        <p v-if="images[index].alt" class="absolute bottom-5 left-1/2 -translate-x-1/2 text-[15px] text-white/75 max-w-[80vw] text-center">{{ images[index].alt }}</p>
        <p class="absolute top-5 left-5 text-[15px] text-white/60 tabular-nums">{{ index + 1 }} / {{ images.length }}</p>
        <button type="button" @click="close" class="absolute top-4 right-4 w-10 h-10 rounded-full bg-white/10 hover:bg-white/20 text-white text-lg" aria-label="Close">✕</button>
        <template v-if="images.length > 1">
          <button type="button" @click="go(-1)" class="absolute left-3 sm:left-6 top-1/2 -translate-y-1/2 w-11 h-11 rounded-full bg-white/10 hover:bg-white/20 text-white text-xl" aria-label="Previous photo">‹</button>
          <button type="button" @click="go(1)" class="absolute right-3 sm:right-6 top-1/2 -translate-y-1/2 w-11 h-11 rounded-full bg-white/10 hover:bg-white/20 text-white text-xl" aria-label="Next photo">›</button>
        </template>
      </div>
    </transition>
  </Teleport>
</template>

<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';

/** Opens any photo inside `.prose-site` full size, with previous/next through all photos on the page. */
const images = ref([]);
const index = ref(null);

function onClick(e) {
  const img = e.target.closest?.('.prose-site img');
  if (!img || img.closest('a')) return;
  const all = [...document.querySelectorAll('.prose-site img')].filter(i => !i.closest('a'));
  images.value = all.map(i => ({ src: i.currentSrc || i.src, alt: i.alt || '' }));
  index.value = all.indexOf(img);
  document.body.style.overflow = 'hidden';
}
function close() {
  index.value = null;
  document.body.style.overflow = '';
}
function go(step) {
  index.value = (index.value + step + images.value.length) % images.value.length;
}
function onKey(e) {
  if (index.value === null) return;
  if (e.key === 'Escape') close();
  if (e.key === 'ArrowRight') go(1);
  if (e.key === 'ArrowLeft') go(-1);
}

onMounted(() => { document.addEventListener('click', onClick); document.addEventListener('keydown', onKey); });
onBeforeUnmount(() => { document.removeEventListener('click', onClick); document.removeEventListener('keydown', onKey); close(); });
</script>
