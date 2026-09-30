<template>
  <AdminLayout title="Users">
    <PageHeader title="Team members" description="Everyone who can sign in to the admin. Each person gets one role; the role decides what they can see and do.">
      <Link v-if="can('roles.view')" href="/admin/roles" class="admin-btn-secondary">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3l8 3v6c0 4.5-3.4 8.3-8 9-4.6-.7-8-4.5-8-9V6l8-3zM9 12l2 2 4-4" /></svg>
        Roles & permissions
      </Link>
      <button @click="openModal()" class="admin-btn-primary">+ Add user</button>
    </PageHeader>

    <div class="admin-card overflow-hidden">
      <div class="overflow-x-auto">
        <table class="a-table">
          <thead><tr><th class="w-10 !pr-0"><input type="checkbox" :checked="bulk.all.value" :indeterminate.prop="bulk.some.value" @change="bulk.toggleAll()" aria-label="Select all" /></th><th>Name</th><th>Role</th><th>Status</th><th class="text-center">Articles</th><th>Last sign-in</th><th></th></tr></thead>
          <tbody>
            <tr v-for="u in pager.rows.value" :key="u.id" :class="bulk.has(u.id) && 'a-row-selected'">
              <td class="w-10 !pr-0"><input v-if="u.id !== me" type="checkbox" :checked="bulk.has(u.id)" @change="bulk.toggle(u.id)" aria-label="Select" /></td>
              <td>
                <div class="flex items-center gap-3">
                  <Avatar :name="u.name" :image="u.image" size="sm" />
                  <div class="min-w-0">
                    <p class="font-semibold truncate">{{ u.name }} <span v-if="u.id === me" class="a-badge ml-1">You</span></p>
                    <p class="text-xs a-subtle truncate">{{ u.email }}<span v-if="u.job_title"> · {{ u.job_title }}</span></p>
                  </div>
                </div>
              </td>
              <td><span :class="['a-badge', u.role === 'super-admin' ? 'a-badge-accent' : '']">{{ roleLabel(u.role) }}</span></td>
              <td><span :class="['a-badge', u.is_active ? 'a-badge-success' : '']">{{ u.is_active ? 'Active' : 'Deactivated' }}</span></td>
              <td class="text-center tabular-nums">{{ u.articles }}</td>
              <td class="a-muted whitespace-nowrap">{{ u.last_login_at ? new Date(u.last_login_at).toLocaleString('en-SG', { dateStyle: 'medium', timeStyle: 'short' }) : 'Never' }}</td>
              <td class="text-right whitespace-nowrap">
                <button @click="openModal(u)" class="a-btn-ghost a-btn-sm">Edit</button>
                <button v-if="u.id !== me" @click="remove(u)" class="a-btn-ghost a-btn-sm !a-text-danger">Delete</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <ClientPagination :pager="pager" />
    </div>

    <Modal :show="modalOpen" :title="editing ? `Edit ${editing.name}` : 'Add a team member'" width="2xl" @close="modalOpen = false">
      <form @submit.prevent="save" class="space-y-5">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="admin-label">Full name *</label>
            <input v-model="form.name" type="text" required class="admin-input" autocomplete="off" />
            <p v-if="form.errors.name" class="a-error">{{ form.errors.name }}</p>
          </div>
          <div>
            <label class="admin-label">Email *</label>
            <input v-model="form.email" type="email" required class="admin-input" autocomplete="off" />
            <p v-if="form.errors.email" class="a-error">{{ form.errors.email }}</p>
          </div>
          <div class="sm:col-span-2">
            <label class="admin-label">Job title <span class="font-normal a-subtle">(shown as author title on articles)</span></label>
            <input v-model="form.job_title" type="text" class="admin-input" placeholder="e.g. Senior Plumber" />
          </div>
        </div>

        <div>
          <label class="admin-label flex items-center justify-between">
            <span>Role *</span>
            <Link v-if="can('roles.create')" href="/admin/roles?new=1" class="text-[12px] a-accent font-semibold">+ Create a custom role</Link>
          </label>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
            <label v-for="r in roles" :key="r.name" :class="['flex items-start gap-3 p-3 rounded-xl border cursor-pointer transition', form.role === r.name ? 'border-[var(--a-accent)] bg-[var(--a-accent-soft)]' : 'a-border hover:bg-[var(--a-panel-2)]']">
              <input v-model="form.role" type="radio" :value="r.name" class="mt-1" />
              <span>
                <span class="block text-sm font-semibold">{{ r.label }}</span>
                <span class="block text-xs a-muted">{{ r.description || 'Custom role' }}</span>
              </span>
            </label>
          </div>
          <p v-if="form.errors.role" class="a-error">{{ form.errors.role }}</p>
        </div>

        <div>
          <label class="admin-label">{{ editing ? 'New password' : 'Password *' }}</label>
          <input v-model="form.password" type="password" :required="!editing" class="admin-input" autocomplete="new-password" :placeholder="editing ? 'Leave empty to keep the current password' : 'At least 8 characters, letters and numbers'" />
          <p v-if="form.errors.password" class="a-error">{{ form.errors.password }}</p>
        </div>

        <label v-if="!editing || editing.id !== me" class="flex items-start gap-3 text-sm">
          <input v-model="form.is_active" type="checkbox" class="mt-0.5 rounded" />
          <span><span class="font-semibold">Active</span><span class="block text-xs a-muted">Deactivated users cannot sign in, but their articles stay.</span></span>
        </label>

        <div class="flex justify-end gap-2 pt-4 border-t a-border">
          <button type="button" @click="modalOpen = false" class="admin-btn-secondary">Cancel</button>
          <button type="submit" :disabled="form.processing" class="admin-btn-primary">{{ editing ? 'Save changes' : 'Add user' }}</button>
        </div>
      </form>
    </Modal>
    <BulkBar :bulk="bulk" :can-delete="can('users.delete')" />
  </AdminLayout>
</template>

<script setup>
import BulkBar from '@/Components/Admin/BulkBar.vue';
import { useBulk } from '@/Composables/useBulk';
import { computed, ref } from 'vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import ClientPagination from '@/Components/Admin/ClientPagination.vue';
import { usePaged } from '@/Composables/usePaged';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import { usePermissions } from '@/Composables/usePermissions';
import Modal from '@/Components/Admin/Modal.vue';
import Avatar from '@/Components/Admin/Avatar.vue';
import { confirmDialog } from '@/Composables/useConfirm';

const props = defineProps({ users: Array, roles: Array });
const { can } = usePermissions();

const me = computed(() => usePage().props.auth?.user?.id);
const roleLabel = name => props.roles.find(r => r.name === name)?.label || name || 'No role';

const modalOpen = ref(false);
const editing = ref(null);
const blank = () => ({ name: '', email: '', job_title: '', role: 'writer', password: '', is_active: true });
const form = useForm(blank());

function openModal(u = null) {
  form.clearErrors();
  editing.value = u;
  const data = blank();
  if (u) Object.assign(data, { name: u.name, email: u.email, job_title: u.job_title || '', role: u.role || 'writer', is_active: u.is_active });
  form.defaults(data);
  form.reset();
  modalOpen.value = true;
}

function save() {
  const opts = { preserveScroll: true, onSuccess: () => { modalOpen.value = false; } };
  editing.value ? form.put(`/admin/users/${editing.value.id}`, opts) : form.post('/admin/users', opts);
}

async function remove(u) {
  const ok = await confirmDialog({
    title: `Delete ${u.name}?`,
    message: `${u.name} will no longer be able to sign in. Their ${u.articles} article(s) stay published with their name as author.\n\nTo pause access temporarily, deactivate the account instead.`,
    confirmText: 'Delete user',
  });
  if (ok) router.delete(`/admin/users/${u.id}`, { preserveScroll: true });
}

// Long lists are paged (10–500 rows, choice remembered).
const pager = usePaged(computed(() => props.users), 'users');

// Select rows for bulk delete (confirm popup; each item follows the normal delete rules).
const bulk = useBulk('users', () => pager.rows.value.filter(u => u.id !== me), { label: 'user' });
</script>
