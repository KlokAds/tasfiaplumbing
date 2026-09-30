<template>
  <AdminLayout title="My account">
    <PageHeader title="My account" description="Your profile is shown as the author on articles you write. A real name, job title and short bio help Google and AI engines trust the content (E-E-A-T)." />

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
      <form @submit.prevent="saveProfile" class="admin-card p-6 xl:col-span-2 space-y-5">
        <h3 class="font-bold">Profile</h3>
        <div class="flex items-center gap-4">
          <Avatar :name="profileForm.name" :image="preview ? null : profile.image" size="lg" v-if="!preview" />
          <img v-else :src="preview" class="w-16 h-16 rounded-full object-cover" alt="" />
          <label class="admin-btn-secondary a-btn-sm cursor-pointer">
            Change photo
            <input type="file" accept="image/*" class="hidden" @change="pickImage" />
          </label>
          <LibraryButton class="mt-1" label="Choose from library" @pick="p => { profileForm.image = p.file; preview = p.url; }" />
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="admin-label">Full name *</label>
            <input v-model="profileForm.name" type="text" required class="admin-input" />
            <p v-if="profileForm.errors.name" class="a-error">{{ profileForm.errors.name }}</p>
          </div>
          <div>
            <label class="admin-label">Email *</label>
            <input v-model="profileForm.email" type="email" required class="admin-input" />
            <p v-if="profileForm.errors.email" class="a-error">{{ profileForm.errors.email }}</p>
          </div>
          <div>
            <label class="admin-label">Job title</label>
            <input v-model="profileForm.job_title" type="text" class="admin-input" placeholder="e.g. Licensed Plumber, 12 years" />
          </div>
          <div>
            <label class="admin-label">LinkedIn or profile URL</label>
            <input v-model="profileForm.social_url" type="url" class="admin-input" placeholder="https://www.linkedin.com/in/…" />
            <p v-if="profileForm.errors.social_url" class="a-error">{{ profileForm.errors.social_url }}</p>
          </div>
        </div>
        <div>
          <label class="admin-label">Short bio <span class="font-normal a-subtle">(2–3 sentences: experience, licences, specialities)</span></label>
          <textarea v-model="profileForm.bio" rows="3" maxlength="1000" class="admin-input"></textarea>
        </div>
        <div class="flex justify-end pt-2">
          <button type="submit" :disabled="profileForm.processing" class="admin-btn-primary">Save profile</button>
        </div>
      </form>

      <div class="space-y-6">
        <section class="admin-card p-6">
          <h3 class="font-bold">Overview</h3>
          <dl class="mt-3 space-y-2 text-sm">
            <div class="flex justify-between"><dt class="a-muted">Role</dt><dd class="font-semibold">{{ profile.role }}</dd></div>
            <div class="flex justify-between"><dt class="a-muted">Articles</dt><dd class="font-semibold">{{ stats.articles }}</dd></div>
            <div class="flex justify-between"><dt class="a-muted">Published</dt><dd class="font-semibold">{{ stats.published }}</dd></div>
            <div class="flex justify-between"><dt class="a-muted">Waiting approval</dt><dd class="font-semibold">{{ stats.pending }}</dd></div>
            <div class="flex justify-between"><dt class="a-muted">Last sign-in</dt><dd class="font-semibold">{{ profile.last_login_at ? new Date(profile.last_login_at).toLocaleString('en-SG', { dateStyle: 'medium', timeStyle: 'short' }) : '—' }}</dd></div>
          </dl>
        </section>

        <form @submit.prevent="savePassword" class="admin-card p-6 space-y-4">
          <h3 class="font-bold">Change password</h3>
          <div>
            <label class="admin-label">Current password</label>
            <input v-model="pwForm.current_password" type="password" required autocomplete="current-password" class="admin-input" />
            <p v-if="pwForm.errors.current_password" class="a-error">{{ pwForm.errors.current_password }}</p>
          </div>
          <div>
            <label class="admin-label">New password</label>
            <input v-model="pwForm.password" type="password" required autocomplete="new-password" class="admin-input" />
            <p v-if="pwForm.errors.password" class="a-error">{{ pwForm.errors.password }}</p>
          </div>
          <div>
            <label class="admin-label">Repeat new password</label>
            <input v-model="pwForm.password_confirmation" type="password" required autocomplete="new-password" class="admin-input" />
          </div>
          <button type="submit" :disabled="pwForm.processing" class="admin-btn-secondary w-full">Update password</button>
        </form>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import LibraryButton from '@/Components/Admin/LibraryButton.vue';
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import Avatar from '@/Components/Admin/Avatar.vue';
import { compressImage } from '@/Composables/compressImage';

const props = defineProps({ profile: Object, stats: Object });

const preview = ref(null);
const profileForm = useForm({
  name: props.profile.name,
  email: props.profile.email,
  job_title: props.profile.job_title || '',
  social_url: props.profile.social_url || '',
  bio: props.profile.bio || '',
  image: null,
});

async function pickImage(e) {
  const file = await compressImage(e.target.files[0], { maxSide: 600 });
  if (file) {
    profileForm.image = file;
    preview.value = URL.createObjectURL(file);
  }
}

function saveProfile() {
  profileForm.post('/admin/account/profile', { forceFormData: true, preserveScroll: true });
}

const pwForm = useForm({ current_password: '', password: '', password_confirmation: '' });
function savePassword() {
  pwForm.put('/admin/account/password', { preserveScroll: true, onSuccess: () => pwForm.reset() });
}
</script>
