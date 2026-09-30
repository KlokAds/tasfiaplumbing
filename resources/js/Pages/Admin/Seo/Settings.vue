<template>
  <AdminLayout :title="title">
    <PageHeader :title="title" :description="scope === 'business'
      ? 'Your official business details. They feed the LocalBusiness schema on every page, so they must match your Google Business Profile exactly.'
      : 'How the site appears in search results, and which search and AI crawlers may read it.'" />

    <div class="grid grid-cols-1 xl:grid-cols-[1fr_24rem] gap-5 items-start">
      <form @submit.prevent="save" class="space-y-5">
        <section v-if="scope === 'business'" class="admin-card p-5">
          <div class="flex items-start justify-between gap-4">
            <div>
              <h3 class="a-card-title">Name, address, phone (NAP)</h3>
              <p class="a-card-sub">Kept in one place so every page shows the same details.</p>
            </div>
            <Link href="/admin/settings/contact" class="admin-btn-secondary a-btn-sm shrink-0">Edit contact details</Link>
          </div>
          <dl class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-2 text-sm">
            <div class="rounded-lg a-panel-2 px-3 py-2"><dt class="text-xs a-subtle">Phone</dt><dd class="font-semibold">{{ nap.phone || '—' }}</dd></div>
            <div class="rounded-lg a-panel-2 px-3 py-2"><dt class="text-xs a-subtle">Email</dt><dd class="font-semibold break-all">{{ nap.email || '—' }}</dd></div>
            <div class="rounded-lg a-panel-2 px-3 py-2"><dt class="text-xs a-subtle">Address</dt><dd class="font-semibold">{{ nap.address || '—' }}</dd></div>
          </dl>
        </section>

        <section v-for="(group, gkey) in groups" :key="gkey" class="admin-card overflow-hidden">
          <header class="a-card-head">
            <div>
              <h3 class="a-card-title">{{ group.label }}</h3>
              <p v-if="group.help" class="a-card-sub">{{ group.help }}</p>
            </div>
          </header>
          <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-4">
            <div v-for="(field, key) in group.fields" :key="key" :class="field.type === 'textarea' || field.type === 'toggle' ? 'md:col-span-2' : ''">
              <label v-if="field.type === 'toggle'" class="a-toggle-row">
                <span>
                  <span class="block text-sm font-semibold">{{ field.label }}</span>
                  <span v-if="field.help" class="block text-xs a-muted mt-0.5">{{ field.help }}</span>
                </span>
                <input v-model="form[fk(key)]" type="checkbox" class="a-switch mt-0.5" :disabled="readOnly" />
              </label>
              <template v-else>
                <label class="admin-label">{{ field.label }}</label>
                <SelectBox v-if="field.type === 'select'" v-model="form[fk(key)]" class="admin-input" :disabled="readOnly">
                  <option v-for="opt in field.options" :key="opt" :value="opt">{{ opt }}</option>
                </SelectBox>
                <textarea v-else-if="field.type === 'textarea'" v-model="form[fk(key)]" rows="3" class="admin-input" :disabled="readOnly"></textarea>
                <input v-else v-model="form[fk(key)]" type="text" class="admin-input" :disabled="readOnly" />
                <p v-if="form.errors[fk(key)]" class="a-error">{{ form.errors[fk(key)] }}</p>
                <p v-else-if="field.help" class="a-help">{{ field.help }}</p>
              </template>
            </div>
          </div>
        </section>

        <div v-if="!readOnly" class="flex justify-end sticky bottom-4">
          <button type="submit" :disabled="form.processing" class="admin-btn-primary" style="box-shadow: var(--a-shadow-lg)">{{ form.processing ? 'Saving…' : 'Save settings' }}</button>
        </div>
      </form>

      <aside class="space-y-5 xl:sticky xl:top-20">
        <section v-if="scope === 'seo'" class="admin-card overflow-hidden">
          <header class="a-card-head">
            <h3 class="a-card-title">robots.txt</h3>
            <a href="/robots.txt" target="_blank" class="text-xs font-semibold a-accent">Open</a>
          </header>
          <div class="p-4 space-y-3">
            <p v-if="environment !== 'production'" class="a-alert a-alert-warning text-xs">This is a {{ environment }} copy, so everything is blocked here. On the live site (APP_ENV=production) the rules below apply.</p>
            <pre class="a-code !whitespace-pre-wrap">{{ preview.robots }}</pre>
          </div>
        </section>
        <section class="admin-card overflow-hidden">
          <header class="a-card-head">
            <h3 class="a-card-title">Site schema (JSON-LD)</h3>
            <a href="https://search.google.com/test/rich-results" target="_blank" rel="noopener" class="text-xs font-semibold a-accent">Test</a>
          </header>
          <div class="p-4">
            <p class="text-xs a-muted mb-3">Added to every public page. Updates as soon as you save.</p>
            <pre class="a-code max-h-[26rem]">{{ preview.schema }}</pre>
          </div>
        </section>
        <section class="admin-card p-5 text-sm space-y-2">
          <p class="a-section-title mb-1">Useful tools</p>
          <a href="/sitemap.xml" target="_blank" class="block a-accent hover:underline">Sitemap (/sitemap.xml)</a>
          <a href="https://search.google.com/search-console" target="_blank" rel="noopener" class="block a-accent hover:underline">Google Search Console</a>
          <a href="https://www.bing.com/webmasters" target="_blank" rel="noopener" class="block a-accent hover:underline">Bing Webmaster Tools</a>
          <a href="https://business.google.com" target="_blank" rel="noopener" class="block a-accent hover:underline">Google Business Profile</a>
        </section>
      </aside>
    </div>
  </AdminLayout>
</template>

<script setup>
import SelectBox from '@/Components/SelectBox.vue';
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import { usePermissions } from '@/Composables/usePermissions';

const props = defineProps({
  scope: { type: String, default: 'seo' },
  title: { type: String, default: 'Schema & robots' },
  action: { type: String, default: '/admin/seo/settings' },
  groups: Object,
  values: Object,
  nap: Object,
  preview: Object,
  environment: String,
});
const { can } = usePermissions();
const readOnly = computed(() => !can(props.scope === 'business' ? 'settings.edit' : 'seo_settings.edit'));

// Dotted keys (business.uen) would be parsed as nested arrays by Laravel, so the form uses "__".
const fk = key => key.replace(/\./g, '__');

const initial = {};
Object.values(props.groups).forEach(group => {
  Object.entries(group.fields).forEach(([key, field]) => {
    const value = props.values[key];
    initial[fk(key)] = field.type === 'toggle' ? value === '1' || value === true : (value ?? '');
  });
});
const form = useForm(initial);

function save() {
  form.post(props.action, { preserveScroll: true });
}
</script>
