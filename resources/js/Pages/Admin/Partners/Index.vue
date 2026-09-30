<template>
  <AdminLayout title="Partner logos">
    <PageHeader title="Partner logos" description="Logos of developers, contractors and business clients you have worked with, shown in a strip on the homepage. Only use logos you have permission to show." />

    <div class="grid grid-cols-1 lg:grid-cols-[20rem_1fr] gap-5 items-start">
      <form v-if="can('homepage.edit')" @submit.prevent="submit" class="admin-card p-5 space-y-4 lg:sticky lg:top-20">
        <div>
          <h3 class="a-card-title">Upload a logo</h3>
          <p class="a-card-sub">PNG or SVG with a transparent background looks best.</p>
        </div>
        <label class="a-dropzone h-36">
          <img v-if="previewUrl" :src="previewUrl" alt="" class="max-h-28 max-w-[85%] object-contain" />
          <template v-else>
            <svg class="w-6 h-6 a-subtle" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" /></svg>
            <span class="text-xs">Click to choose a file</span>
          </template>
          <input ref="fileInput" type="file" accept="image/*" class="hidden" @change="pick" />
        </label>
        <LibraryButton @pick="p => { form.image = p.file; previewUrl = p.url; }" />
        <p v-if="form.errors.image" class="a-error">{{ form.errors.image }}</p>
        <button type="submit" :disabled="form.processing || !form.image" class="admin-btn-primary w-full">{{ form.processing ? 'Uploading…' : 'Add logo' }}</button>
      </form>

      <section class="admin-card overflow-hidden" :class="!can('homepage.edit') && 'lg:col-span-2'">
        <header class="a-card-head">
          <h3 class="a-card-title">On the homepage <span class="a-subtle font-medium">{{ partners.length }}</span></h3>
        </header>
        <BulkSelectAll v-if="can('homepage.edit') && partners.length" :bulk="bulk" />
        <div v-if="partners.length" class="p-4 grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-3">
          <div v-for="item in partners" :key="item.id" :class="['relative rounded-xl border overflow-hidden flex flex-col', bulk.has(item.id) ? 'border-[var(--a-accent)]' : 'a-border']">
          <label class="absolute top-2 left-2 rounded-md p-1 cursor-pointer z-[1]" style="background: var(--a-panel)" title="Select"><input type="checkbox" class="block" :checked="bulk.has(item.id)" @change="bulk.toggle(item.id)" /></label>
            <div class="bg-white h-24 flex items-center justify-center p-4">
              <img :src="'/' + item.image" alt="" class="max-h-full max-w-full object-contain" />
            </div>
            <div v-if="can('homepage.edit')" class="px-2 py-1.5 border-t a-border flex justify-end">
              <button @click="remove(item)" class="a-btn-ghost a-danger a-btn-sm">Remove</button>
            </div>
          </div>
        </div>
        <div v-else class="a-empty">
          <p class="font-semibold">No logos yet</p>
          <p class="text-sm a-muted mt-1">The logo strip stays hidden on the homepage until you add some.</p>
        </div>
      </section>
    </div>
    <BulkBar :bulk="bulk" :can-delete="can('homepage.edit')" />
  </AdminLayout>
</template>

<script setup>
import BulkSelectAll from '@/Components/Admin/BulkSelectAll.vue';
import LibraryButton from '@/Components/Admin/LibraryButton.vue';
import BulkBar from '@/Components/Admin/BulkBar.vue';
import { useBulk } from '@/Composables/useBulk';
import { ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import { confirmDialog } from '@/Composables/useConfirm';
import { usePermissions } from '@/Composables/usePermissions';

const props = defineProps({ partners: Array });
const { can } = usePermissions();

const previewUrl = ref(null);
const fileInput = ref(null);
const form = useForm({ image: null });

function pick(e) {
  const file = e.target.files[0];
  form.image = file || null;
  previewUrl.value = file ? URL.createObjectURL(file) : null;
}

function submit() {
  form.post('/admin/partners', {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => {
      form.reset();
      previewUrl.value = null;
      if (fileInput.value) fileInput.value.value = '';
    },
  });
}

async function remove(item) {
  if (await confirmDialog({ title: 'Remove this logo?', message: 'It disappears from the homepage.', confirmText: 'Remove logo' })) {
    router.delete(`/admin/partners/${item.id}`, { preserveScroll: true });
  }
}

// Select rows for bulk delete (confirm popup; each item follows the normal delete rules).
const bulk = useBulk('partners', () => props.partners, { label: 'logo' });
</script>
