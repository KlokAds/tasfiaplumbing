<template>
  <Head title="Sign in" />
  <div class="admin-ui min-h-screen grid lg:grid-cols-[1.05fr_1fr]">
    <!-- Brand side -->
    <aside class="hidden lg:flex flex-col justify-between p-14 text-white" style="background: #0a2540">
      <div class="flex items-center gap-4">
        <span class="w-16 h-16 rounded-full bg-white flex items-center justify-center p-1.5"><img src="/logo.png" alt="" class="w-full h-full object-contain" /></span>
        <span class="leading-tight">
          <span class="block text-[20px] font-semibold a-display">Tasfia Plumbing</span>
          <span class="block text-[13px] text-white/55">Plumbing Service · Singapore</span>
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

        <h2 class="text-[1.9rem] font-semibold a-display">Sign in</h2>
        <p class="text-sm a-muted mt-1">Use the email and password your administrator gave you.</p>

        <form @submit.prevent="submit" class="mt-8 space-y-4">
          <div>
            <label class="admin-label" for="email">Email</label>
            <input id="email" v-model="form.email" type="email" required autofocus autocomplete="username" placeholder="you@company.com" class="admin-input" :aria-invalid="!!form.errors.email" />
            <p v-if="form.errors.email" class="a-error">{{ form.errors.email }}</p>
          </div>

          <div>
            <label class="admin-label" for="password">Password</label>
            <div class="relative">
              <input id="password" v-model="form.password" :type="showPassword ? 'text' : 'password'" required autocomplete="current-password" class="admin-input pr-16" />
              <button type="button" @click="showPassword = !showPassword" class="absolute right-2 top-1/2 -translate-y-1/2 text-xs font-semibold a-muted a-hover-text px-2 py-1">{{ showPassword ? 'Hide' : 'Show' }}</button>
            </div>
            <p v-if="form.errors.password" class="a-error">{{ form.errors.password }}</p>
          </div>

          <label class="flex items-center gap-2.5 text-sm a-muted cursor-pointer select-none">
            <input v-model="form.remember" type="checkbox" />
            Keep me signed in on this device
          </label>

          <button type="submit" :disabled="form.processing" class="admin-btn-primary w-full !py-2.5">
            {{ form.processing ? 'Signing in…' : 'Sign in' }}
          </button>
        </form>

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
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({ email: '', password: '', remember: false });
const showPassword = ref(false);
const theme = ref('light');

function setTheme(t) {
  theme.value = t;
  document.documentElement.classList.toggle('dark', t === 'dark');
  try { localStorage.setItem('tasfia_admin_theme', t); } catch (e) { /* ignore */ }
}

onMounted(() => {
  theme.value = document.documentElement.classList.contains('dark') ? 'dark' : 'light';
});

function submit() {
  form.post('/admin/login', { onFinish: () => form.reset('password') });
}
</script>
