<template>
  <Head title="Forgot password" />
  <AuthShell>
    <h2 class="text-[1.9rem] font-semibold a-display">Forgot password?</h2>
    <p class="text-sm a-muted mt-1">Enter the email you sign in with. If it belongs to an active account, we will email you a link to choose a new password.</p>

    <p v-if="status" role="status" class="mt-6 rounded-lg border border-emerald-200 bg-emerald-50 px-3.5 py-2.5 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200">{{ status }}</p>

    <form @submit.prevent="submit" class="mt-8 space-y-4">
      <div>
        <label class="admin-label" for="email">Email</label>
        <input id="email" v-model="form.email" type="email" required autofocus autocomplete="username" placeholder="you@company.com" class="admin-input" :aria-invalid="!!form.errors.email" />
        <p v-if="form.errors.email" class="a-error">{{ form.errors.email }}</p>
      </div>

      <button type="submit" :disabled="form.processing" class="admin-btn-primary w-full !py-2.5">
        {{ form.processing ? 'Sending…' : 'Email me a reset link' }}
      </button>
    </form>

    <p class="mt-6 text-sm a-muted">
      Remembered it? <Link href="/admin/login" class="font-semibold text-[#1759c4] dark:text-[#7cc4ff] hover:underline">Back to sign in</Link>
    </p>
  </AuthShell>
</template>

<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthShell from '@/Components/Admin/AuthShell.vue';

defineProps({ status: { type: String, default: null } });

const form = useForm({ email: '' });

function submit() {
  form.post('/admin/forgot-password', { preserveScroll: true });
}
</script>
