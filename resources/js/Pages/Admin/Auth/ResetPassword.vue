<template>
  <Head title="Choose a new password" />
  <AuthShell>
    <h2 class="text-[1.9rem] font-semibold a-display">Choose a new password</h2>
    <p class="text-sm a-muted mt-1">At least 10 characters, with letters and numbers. This link works once.</p>

    <form @submit.prevent="submit" class="mt-8 space-y-4">
      <div>
        <label class="admin-label" for="email">Email</label>
        <input id="email" v-model="form.email" type="email" required autocomplete="username" class="admin-input" :aria-invalid="!!form.errors.email" />
        <p v-if="form.errors.email" class="a-error">{{ form.errors.email }}</p>
      </div>

      <div>
        <label class="admin-label" for="password">New password</label>
        <div class="relative">
          <input id="password" v-model="form.password" :type="show ? 'text' : 'password'" required minlength="10" autofocus autocomplete="new-password" class="admin-input pr-16" :aria-invalid="!!form.errors.password" />
          <button type="button" @click="show = !show" class="absolute right-2 top-1/2 -translate-y-1/2 text-xs font-semibold a-muted a-hover-text px-2 py-1">{{ show ? 'Hide' : 'Show' }}</button>
        </div>
        <p v-if="form.errors.password" class="a-error">{{ form.errors.password }}</p>
      </div>

      <div>
        <label class="admin-label" for="password_confirmation">Repeat new password</label>
        <input id="password_confirmation" v-model="form.password_confirmation" :type="show ? 'text' : 'password'" required autocomplete="new-password" class="admin-input" />
      </div>

      <button type="submit" :disabled="form.processing" class="admin-btn-primary w-full !py-2.5">
        {{ form.processing ? 'Saving…' : 'Save new password' }}
      </button>
    </form>

    <p class="mt-6 text-sm a-muted">
      Link expired? <Link href="/admin/forgot-password" class="font-semibold text-[#1759c4] dark:text-[#7cc4ff] hover:underline">Ask for a new one</Link>
    </p>
  </AuthShell>
</template>

<script setup>
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthShell from '@/Components/Admin/AuthShell.vue';

const props = defineProps({ token: String, email: String });

const show = ref(false);
const form = useForm({ token: props.token, email: props.email, password: '', password_confirmation: '' });

function submit() {
  form.post('/admin/reset-password', { onFinish: () => form.reset('password', 'password_confirmation') });
}
</script>
