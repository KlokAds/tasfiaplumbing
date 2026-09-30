<template>
  <AdminLayout title="Website text">
    <PageHeader title="Website text" description="Headings and short texts that are not part of a single page's content: the homepage steps, the call-to-action banners, the contact page. Leave a field empty to hide that line." />

    <form @submit.prevent="save" class="space-y-5">
      <section v-for="s in sections" :key="s.title" class="admin-card overflow-hidden">
        <header class="a-card-head">
          <h3 class="a-card-title">{{ s.title }}</h3>
          <a v-if="s.page" :href="s.page" target="_blank" class="text-[13px] a-accent font-semibold">View on site ↗</a>
        </header>
        <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-4">
          <div v-for="f in s.fields" :key="f.key" :class="f.type === 'textarea' && 'md:col-span-2'">
            <label class="admin-label flex items-center justify-between gap-2">
              <span>{{ f.label }}</span>
              <button v-if="f.default && form.texts[f.key] !== f.default" type="button" class="text-[11px] a-subtle hover:underline" :disabled="readOnly" @click="form.texts[f.key] = f.default">Use suggested text</button>
            </label>
            <textarea v-if="f.type === 'textarea'" v-model="form.texts[f.key]" rows="2" maxlength="600" class="admin-input" :placeholder="f.default" :disabled="readOnly"></textarea>
            <input v-else v-model="form.texts[f.key]" type="text" maxlength="600" class="admin-input" :placeholder="f.default" :disabled="readOnly" />
          </div>
        </div>
      </section>

      <div v-if="!readOnly" class="sticky bottom-4 flex justify-end">
        <button type="submit" :disabled="form.processing" class="admin-btn-primary shadow-lg">{{ form.processing ? 'Saving…' : 'Save all' }}</button>
      </div>
    </form>
  </AdminLayout>
</template>

<script setup>
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import { usePermissions } from '@/Composables/usePermissions';

const props = defineProps({ sections: Array });
const { can } = usePermissions();
const readOnly = computed(() => !can('homepage.edit'));
const form = useForm({ texts: Object.fromEntries(props.sections.flatMap((s) => s.fields.map((f) => [f.key, f.value]))) });
const save = () => form.post('/admin/website-text', { preserveScroll: true });
</script>
