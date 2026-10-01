<template>
  <div class="admin-ui min-h-screen grid lg:grid-cols-[1.05fr_1fr]">
    <!-- Brand side -->
    <aside class="hidden lg:flex flex-col justify-between p-14 text-white" style="background: #0a2540">
      <div class="flex items-center gap-4">
        <span class="w-16 h-16 rounded-full bg-white flex items-center justify-center p-1.5"><img src="/logo.png" alt="" class="w-full h-full object-contain" /></span>
        <span class="leading-tight">
          <span class="block text-[20px] font-semibold a-display">Tasfia Plumbing</span>
          <span class="block text-[13px] text-white/55">Singapore</span>
        </span>
      </div>

      <div class="max-w-md">
        <p class="text-[13px] font-semibold uppercase tracking-[0.12em] text-[#7cc4ff]">Website admin</p>
        <h1 class="mt-4 text-[2.6rem] font-semibold leading-[1.1] a-display">Your plumbing website, in one place.</h1>
        <p class="mt-5 text-[16px] text-white/70 leading-relaxed">Update services and prices, answer enquiries, publish guides and keep every page ready for Google.</p>
        <ul class="mt-9 space-y-4 text-[15px] text-white/85">
          <li v-for="t in ['Autosave, so nothing is lost', 'Approval before anything goes live', 'SEO checks on every page you write']" :key="t" class="flex items-center gap-3">
            <span class="w-7 h-7 rounded-full bg-[#1759c4] flex items-center justify-center shrink-0">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
            </span>{{ t }}
          </li>
        </ul>
      </div>

      <p class="text-[13px] text-white/40">© {{ new Date().getFullYear() }} Tasfia Plumbing · Singapore</p>
    </aside>

    <!-- Form side -->
    <main class="flex flex-col justify-center px-6 py-12 sm:px-12">
      <div class="w-full max-w-sm mx-auto">
        <div class="lg:hidden flex items-center gap-3 mb-10">
          <span class="w-12 h-12 rounded-full bg-white border a-border flex items-center justify-center p-1"><img src="/logo.png" alt="" class="w-full h-full object-contain" /></span>
          <span class="text-[17px] font-semibold a-display">Tasfia Plumbing</span>
        </div>

        <slot />

        <div class="mt-10 flex items-center justify-between text-xs a-subtle">
          <Link href="/" class="a-hover-text">← Back to website</Link>
          <div class="a-seg">
            <button v-for="t in ['light', 'dark']" :key="t" type="button" @click="setTheme(t)" :class="['capitalize', theme === t && 'is-on']">{{ t }}</button>
          </div>
        </div>
      </div>
    </main>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { Link } from '@inertiajs/vue3';

const theme = ref('light');

function setTheme(t) {
  theme.value = t;
  document.documentElement.classList.toggle('dark', t === 'dark');
  try { localStorage.setItem('tasfia_admin_theme', t); } catch (e) { /* ignore */ }
}

onMounted(() => {
  theme.value = document.documentElement.classList.contains('dark') ? 'dark' : 'light';
});
</script>
