<template>
  <AdminLayout title="Roles & permissions">
    <PageHeader title="Roles & permissions" description="A role is a set of permissions. Give each person the smallest role they need. Super Admin always has everything and is the only role that can publish articles unless you tick “Publish, schedule & approve” for another role.">
      <button v-if="can('roles.create')" @click="openModal()" class="admin-btn-primary">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14" /></svg>
        New role
      </button>
    </PageHeader>

    <p class="a-alert a-alert-info text-[13px] mb-5">
      <b>How it works:</b> click <b>New role</b> (or <b>Edit permissions</b> on a role) to open the permission table. Tick what the role may do in each area (View, Create, Edit, Delete and special rights), then save.
      Give the role to a person under <a href="/admin/users" class="font-semibold underline">Team members</a>.
    </p>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
      <article v-for="r in pager.rows.value" :key="r.id" class="admin-card p-5 flex flex-col">
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0">
            <h3 class="font-bold flex items-center gap-2 truncate">{{ r.label }}
              <span v-if="r.is_super" class="a-badge a-badge-accent">Owner</span>
              <span v-else-if="!r.is_builtin" class="a-badge">Custom</span>
            </h3>
            <p class="text-[13px] a-muted mt-1 line-clamp-2">{{ r.description || 'Custom role created by your team.' }}</p>
          </div>
          <span class="a-badge shrink-0">{{ r.users_count }} {{ r.users_count === 1 ? 'user' : 'users' }}</span>
        </div>

        <div class="mt-4">
          <div class="flex justify-between text-xs mb-1.5">
            <span class="a-muted">Access</span>
            <span class="font-semibold tabular-nums">{{ r.permissions.length }} / {{ total }}</span>
          </div>
          <div class="a-progress"><span :style="{ width: Math.round(r.permissions.length / total * 100) + '%' }"></span></div>
        </div>

        <ul class="mt-4 space-y-1.5 text-[13px]">
          <li v-for="g in summary(r)" :key="g.group" class="flex items-center justify-between gap-2">
            <span class="a-muted">{{ g.group }}</span>
            <span :class="['a-badge', g.on === g.all ? 'a-badge-success' : g.on ? 'a-badge-info' : '']">{{ g.on === g.all ? 'Full' : g.on ? `${g.on} of ${g.all}` : 'None' }}</span>
          </li>
        </ul>

        <div class="mt-auto pt-5 flex flex-wrap gap-2">
          <template v-if="!r.is_super">
            <button v-if="can('roles.edit')" @click="openModal(r)" class="admin-btn-secondary a-btn-sm">Edit permissions</button>
            <button v-if="can('roles.create')" @click="openModal(r, true)" class="a-btn-ghost a-btn-sm">Duplicate</button>
            <button v-if="!r.is_builtin && can('roles.delete')" @click="remove(r)" class="a-btn-ghost a-danger a-btn-sm ml-auto">Delete</button>
          </template>
          <p v-else class="text-xs a-subtle">Has every permission. Cannot be changed.</p>
        </div>
      </article>
    </div>
    <ClientPagination :pager="pager" :padded="false" :options="[9, 18, 36, 90]" class="pt-4" />

    <!-- ============ Editor ============ -->
    <Modal :show="modalOpen" :title="modalTitle" subtitle="Tick what this role may do. Create, edit and delete also turn on view." width="5xl" @close="modalOpen = false">
      <form @submit.prevent="save" class="space-y-5">
        <div class="grid grid-cols-1 sm:grid-cols-[1fr_auto] gap-4 items-end">
          <div>
            <label class="admin-label">Role name *</label>
            <input v-model="form.name" type="text" required :disabled="editing?.is_builtin && !duplicating" class="admin-input" placeholder="e.g. SEO assistant" />
            <p v-if="form.errors.name" class="a-error">{{ form.errors.name }}</p>
          </div>
          <div class="flex flex-wrap gap-2">
            <span class="a-subtle text-xs self-center mr-1">Quick fill:</span>
            <button type="button" @click="preset('view')" class="admin-btn-secondary a-btn-sm">View only</button>
            <button type="button" @click="preset('all')" class="admin-btn-secondary a-btn-sm">Everything</button>
            <button type="button" @click="preset('none')" class="a-btn-ghost a-btn-sm">Clear</button>
          </div>
        </div>

        <div class="rounded-xl border a-border overflow-hidden">
          <div class="overflow-x-auto a-scroll">
            <table class="a-table">
              <thead>
                <tr>
                  <th class="min-w-[14rem]">Area</th>
                  <th v-for="a in STANDARD" :key="a" class="text-center w-20">{{ actionTitle[a] }}</th>
                  <th class="min-w-[12rem]">Special rights</th>
                  <th class="w-16 text-center">All</th>
                </tr>
              </thead>
              <tbody v-for="(mods, group) in modules" :key="group">
                <tr>
                  <td :colspan="STANDARD.length + 3" class="!py-2 a-panel-2">
                    <div class="flex items-center justify-between">
                      <span class="a-section-title">{{ group }}</span>
                      <button type="button" @click="toggleGroup(mods)" class="text-xs font-semibold a-accent">{{ groupOn(mods) ? 'Clear group' : 'Select group' }}</button>
                    </div>
                  </td>
                </tr>
                <tr v-for="(m, key) in mods" :key="key">
                  <td>
                    <p class="font-semibold">{{ m.label }}</p>
                    <p v-if="m.help" class="text-xs a-subtle">{{ m.help }}</p>
                  </td>
                  <td v-for="a in STANDARD" :key="a" class="text-center !p-0">
                    <label v-if="m.actions[a]" :class="['perm-cell', has(`${key}.${a}`) && 'is-on']" :title="`${m.label}: ${m.actions[a]}`">
                      <input type="checkbox" :checked="has(`${key}.${a}`)" @change="toggle(key, a, m)" :aria-label="`${m.label}: ${m.actions[a]}`" />
                    </label>
                    <span v-else class="a-subtle">·</span>
                  </td>
                  <td>
                    <div class="flex flex-col gap-1.5">
                      <label v-for="(label, a) in specials(m)" :key="a" class="inline-flex items-center gap-2 text-[13px] cursor-pointer">
                        <input type="checkbox" :checked="has(`${key}.${a}`)" @change="toggle(key, a, m)" />
                        <span>{{ label }}</span>
                        <span v-if="SENSITIVE.includes(`${key}.${a}`)" class="a-badge a-badge-warning">Sensitive</span>
                      </label>
                      <span v-if="!Object.keys(specials(m)).length" class="a-subtle">—</span>
                    </div>
                  </td>
                  <td class="text-center">
                    <input type="checkbox" :checked="moduleOn(key, m)" :indeterminate.prop="moduleSome(key, m)" @change="toggleModule(key, m)" :aria-label="`All of ${m.label}`" />
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div class="sticky bottom-0 -mx-6 -mb-6 px-6 py-3 border-t a-border flex items-center justify-between gap-3" style="background: var(--a-panel)">
          <p class="text-sm a-muted"><span class="font-bold a-text">{{ form.permissions.length }}</span> of {{ total }} permissions</p>
          <div class="flex gap-2">
            <button type="button" @click="modalOpen = false" class="admin-btn-secondary">Cancel</button>
            <button type="submit" :disabled="form.processing" class="admin-btn-primary">{{ editing && !duplicating ? 'Save role' : 'Create role' }}</button>
          </div>
        </div>
      </form>
    </Modal>
  </AdminLayout>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import Modal from '@/Components/Admin/Modal.vue';
import ClientPagination from '@/Components/Admin/ClientPagination.vue';
import { usePaged } from '@/Composables/usePaged';
import { confirmDialog } from '@/Composables/useConfirm';
import { usePermissions } from '@/Composables/usePermissions';

const props = defineProps({ roles: Array, modules: Object });
const { can } = usePermissions();
// 3 cards per row, so pages of 9.
const pager = usePaged(computed(() => props.roles), 'roles', 9);
// "+ Create a custom role" on the Team page lands here with ?new=1
onMounted(() => { if (new URLSearchParams(location.search).get('new') && can('roles.create')) openModal(); });

const STANDARD = ['view', 'create', 'edit', 'delete'];
const SENSITIVE = ['articles.publish', 'system.update', 'roles.edit', 'users.delete'];
const actionTitle = { view: 'View', create: 'Create', edit: 'Edit', delete: 'Delete' };

const allNames = computed(() => Object.values(props.modules).flatMap(mods => Object.entries(mods).flatMap(([key, m]) => Object.keys(m.actions).map(a => `${key}.${a}`))));
const total = computed(() => allNames.value.length);
const namesOf = (key, m) => Object.keys(m.actions).map(a => `${key}.${a}`);
const specials = m => Object.fromEntries(Object.entries(m.actions).filter(([a]) => !STANDARD.includes(a)));

function summary(r) {
  const set = new Set(r.permissions);
  return Object.entries(props.modules).map(([group, mods]) => {
    const names = Object.entries(mods).flatMap(([k, m]) => namesOf(k, m));
    return { group, all: names.length, on: names.filter(n => set.has(n)).length };
  });
}

// ---------- Editor ----------
const modalOpen = ref(false);
const editing = ref(null);
const duplicating = ref(false);
const form = useForm({ name: '', permissions: [] });
const modalTitle = computed(() => duplicating.value ? `Duplicate ${editing.value.label}` : editing.value ? `Edit ${editing.value.label}` : 'New role');

function openModal(r = null, dup = false) {
  form.clearErrors();
  editing.value = r;
  duplicating.value = dup;
  form.defaults({ name: r ? (dup ? `${r.label} copy` : r.label) : '', permissions: r ? [...r.permissions] : [] });
  form.reset();
  modalOpen.value = true;
}

const has = n => form.permissions.includes(n);
function set(names, on) {
  const s = new Set(form.permissions);
  names.forEach(n => (on ? s.add(n) : s.delete(n)));
  form.permissions = allNames.value.filter(n => s.has(n));
}
function toggle(key, action, m) {
  const name = `${key}.${action}`;
  const on = !has(name);
  set([name], on);
  // Doing anything needs seeing it; removing "view" removes everything else in that area.
  if (m.actions.view) {
    if (on && action !== 'view') set([`${key}.view`], true);
    if (!on && action === 'view') set(namesOf(key, m), false);
  }
}
const moduleOn = (key, m) => namesOf(key, m).every(has);
const moduleSome = (key, m) => !moduleOn(key, m) && namesOf(key, m).some(has);
const toggleModule = (key, m) => set(namesOf(key, m), !moduleOn(key, m));
const groupNames = mods => Object.entries(mods).flatMap(([k, m]) => namesOf(k, m));
const groupOn = mods => groupNames(mods).every(has);
const toggleGroup = mods => set(groupNames(mods), !groupOn(mods));

function preset(kind) {
  if (kind === 'none') form.permissions = [];
  if (kind === 'all') form.permissions = allNames.value.filter(n => !SENSITIVE.includes(n));
  if (kind === 'view') form.permissions = allNames.value.filter(n => n.endsWith('.view'));
}

function save() {
  const opts = { preserveScroll: true, onSuccess: () => { modalOpen.value = false; } };
  editing.value && !duplicating.value ? form.put(`/admin/roles/${editing.value.id}`, opts) : form.post('/admin/roles', opts);
}

async function remove(r) {
  if (await confirmDialog({ title: `Delete the ${r.label} role?`, message: 'Only roles without users can be deleted. Move people to another role first.', confirmText: 'Delete role' })) {
    router.delete(`/admin/roles/${r.id}`, { preserveScroll: true });
  }
}
</script>

<style scoped>
/* The whole table cell is the click target, not just the tiny box. */
.perm-cell { display: flex; align-items: center; justify-content: center; min-height: 3rem; cursor: pointer; transition: background 0.12s; }
.perm-cell:hover { background: var(--a-panel-3); }
.perm-cell.is-on { background: var(--a-accent-soft); }
</style>
