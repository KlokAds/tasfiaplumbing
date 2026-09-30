<template>
  <Head title="Sign in" />
  <AuthShell>
    <h2 class="text-[1.9rem] font-semibold a-display">Sign in</h2>
    <p class="text-sm a-muted mt-1">Use the email and password your administrator gave you.</p>

    <p v-if="notice" role="status" class="mt-6 rounded-lg border border-emerald-200 bg-emerald-50 px-3.5 py-2.5 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200">{{ notice }}</p>

    <form @submit.prevent="submit" class="mt-8 space-y-4">
      <div>
        <label class="admin-label" for="email">Email</label>
        <input id="email" v-model="form.email" type="email" required autofocus autocomplete="username" placeholder="you@company.com" class="admin-input" :aria-invalid="!!form.errors.email" />
        <p v-if="form.errors.email" class="a-error">{{ form.errors.email }}</p>
      </div>

      <div>
        <div class="flex items-center justify-between">
          <label class="admin-label" for="password">Password</label>
          <Link href="/admin/forgot-password" class="text-xs font-semibold text-[#1759c4] dark:text-[#7cc4ff] hover:underline mb-1.5">Forgot password?</Link>
        </div>
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
  </AuthShell>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AuthShell from '@/Components/Admin/AuthShell.vue';

const form = useForm({ email: '', password: '', remember: false });
const showPassword = ref(false);
const notice = computed(() => usePage().props.flash?.success);

function submit() {
  form.post('/admin/login', { onFinish: () => form.reset('password') });
}
</script>
