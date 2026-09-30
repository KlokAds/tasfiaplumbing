<template>
  <AdminLayout title="Sections & counters">
    <PageHeader title="Sections & counters" description="Headings of the homepage sections, the trust numbers under the hero, and the capability bars on the About page. Homepage title and description live in Page SEO." />

    <nav class="a-tabs mb-5">
      <button v-for="t in tabs" :key="t.key" @click="activeTab = t.key" :class="['a-tab', activeTab === t.key && 'a-tab-active']">
        {{ t.label }} <span v-if="t.count !== undefined" class="a-badge">{{ t.count }}</span>
      </button>
    </nav>

    <!-- Section headings -->
    <form v-show="activeTab === 'sections'" @submit.prevent="submitSections" class="admin-card overflow-hidden">
      <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-5">
        <div v-for="f in sectionFields" :key="f.key" :class="f.wide && 'md:col-span-2'">
          <label class="admin-label">{{ f.label }}</label>
          <input v-model="sectionsForm[f.key]" type="text" class="admin-input" :placeholder="f.placeholder" :disabled="!can('homepage.edit')" />
          <p v-if="f.help" class="a-help">{{ f.help }}</p>
        </div>
      </div>
      <div class="px-5 py-3 border-t a-border a-panel-2 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <p class="text-xs a-muted">Search title and description: <Link href="/admin/page-seo" class="font-semibold a-accent">Page SEO → Homepage</Link></p>
        <button v-if="can('homepage.edit')" type="submit" :disabled="sectionsForm.processing" class="admin-btn-primary">{{ sectionsForm.processing ? 'Saving…' : 'Save headings' }}</button>
      </div>
    </form>

    <!-- Counters -->
    <div v-show="activeTab === 'stats'" class="grid grid-cols-1 lg:grid-cols-[20rem_1fr] gap-5 items-start">
      <form v-if="can('homepage.edit')" @submit.prevent="submitCounter" class="admin-card p-5 space-y-4">
        <div>
          <h3 class="a-card-title">Add a trust number</h3>
          <p class="a-card-sub">Only numbers you can prove. Customers and Google both check.</p>
        </div>
        <div>
          <label class="admin-label">Number *</label>
          <input v-model="counterForm.c_count" type="text" required placeholder="e.g. 12+ or 3,500+" class="admin-input" />
        </div>
        <div>
          <label class="admin-label">Label *</label>
          <input v-model="counterForm.c_title" type="text" required placeholder="e.g. Years in business" class="admin-input" />
        </div>
        <div>
          <label class="admin-label">Small print</label>
          <input v-model="counterForm.c_subtitle" type="text" placeholder="e.g. since 2014" class="admin-input" />
        </div>
        <button type="submit" :disabled="counterForm.processing" class="admin-btn-primary w-full">{{ counterForm.processing ? 'Adding…' : 'Add number' }}</button>
      </form>

      <section class="admin-card overflow-hidden">
        <div v-if="counters.length" class="grid grid-cols-2 sm:grid-cols-3 gap-px a-panel-3">
          <div v-for="item in counters" :key="item.id" class="a-panel p-5 flex flex-col">
            <p class="text-3xl font-bold tracking-tight">{{ item.c_count }}</p>
            <p class="text-sm font-semibold mt-1">{{ item.c_title }}</p>
            <p v-if="item.c_subtitle" class="text-xs a-subtle">{{ item.c_subtitle }}</p>
            <div v-if="can('homepage.edit')" class="mt-auto pt-3">
              <button @click="deleteCounter(item)" class="a-btn-ghost a-danger a-btn-sm -ml-2">Delete</button>
            </div>
          </div>
        </div>
        <div v-else class="a-empty">
          <p class="font-semibold">No numbers yet</p>
          <p class="text-sm a-muted mt-1">Three or four works best: years, jobs done, rating, response time.</p>
        </div>
      </section>
    </div>

    <!-- Skills -->
    <div v-show="activeTab === 'skills'" class="grid grid-cols-1 lg:grid-cols-[20rem_1fr] gap-5 items-start">
      <form v-if="can('homepage.edit')" @submit.prevent="submitSkill" class="admin-card p-5 space-y-4">
        <div>
          <h3 class="a-card-title">Add a capability</h3>
          <p class="a-card-sub">Shown as bars on the About page.</p>
        </div>
        <div>
          <label class="admin-label">Name *</label>
          <input v-model="skillForm.s_title" type="text" required placeholder="e.g. Leak detection & repair" class="admin-input" />
        </div>
        <div>
          <label class="admin-label">Level *</label>
          <input v-model="skillForm.s_point" type="text" required placeholder="e.g. 95%" class="admin-input" />
        </div>
        <div>
          <label class="admin-label">Detail</label>
          <input v-model="skillForm.s_subtitle" type="text" placeholder="e.g. Licensed plumbers" class="admin-input" />
        </div>
        <button type="submit" :disabled="skillForm.processing" class="admin-btn-primary w-full">{{ skillForm.processing ? 'Adding…' : 'Add capability' }}</button>
      </form>

      <section class="admin-card overflow-hidden">
        <ul v-if="skills.length" class="a-divide">
          <li v-for="item in skills" :key="item.id" class="px-5 py-4">
            <div class="flex items-center justify-between gap-3">
              <div class="min-w-0">
                <p class="font-semibold truncate">{{ item.s_title || item.title }}</p>
                <p v-if="item.s_subtitle || item.short_desc" class="text-xs a-subtle truncate">{{ item.s_subtitle || item.short_desc }}</p>
              </div>
              <span class="font-bold tabular-nums">{{ item.s_point || '—' }}</span>
              <button v-if="can('homepage.edit')" @click="deleteSkill(item)" class="a-btn-ghost a-danger a-btn-sm">Delete</button>
            </div>
            <div class="mt-2 a-progress"><span :style="{ width: pct(item.s_point) }"></span></div>
          </li>
        </ul>
        <div v-else class="a-empty">
          <p class="font-semibold">No capabilities yet</p>
          <p class="text-sm a-muted mt-1">The section stays hidden on the About page until you add some.</p>
        </div>
      </section>
    </div>
  </AdminLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import { confirmDialog } from '@/Composables/useConfirm';
import { usePermissions } from '@/Composables/usePermissions';

const props = defineProps({ homeStatic: Object, counters: Array, skills: Array });
const { can } = usePermissions();

const activeTab = ref('sections');
const tabs = computed(() => [
  { key: 'sections', label: 'Section headings' },
  { key: 'stats', label: 'Trust numbers', count: props.counters.length },
  { key: 'skills', label: 'Capabilities (About page)', count: props.skills.length },
]);

const sectionFields = [
  { key: 'h_s_title', label: 'Services section heading', placeholder: 'Plumbing for Singapore homes', help: 'Use words people search for, e.g. “Renovation & repair services”.' },
  { key: 'h_s_subtitle', label: 'Services section intro', placeholder: 'Every plumbing job handled by one team.' },
  { key: 'h_p_title', label: 'Projects section heading', placeholder: 'Recent projects' },
  { key: 'h_p_subtitle', label: 'Projects section intro', placeholder: 'Real jobs across HDB, condo and landed homes.' },
  { key: 'h_test_title', label: 'Reviews section heading', placeholder: 'Trusted by homeowners and businesses' },
  { key: 'h_b_title', label: 'Articles section heading', placeholder: 'Guides and cost advice' },
];

const counterForm = useForm({ c_count: '', c_title: '', c_subtitle: '' });
const skillForm = useForm({ s_point: '', s_title: '', s_subtitle: '' });
const sectionsForm = useForm({
  h_s_title: props.homeStatic?.h_s_title || '',
  h_s_subtitle: props.homeStatic?.h_s_subtitle || '',
  h_p_title: props.homeStatic?.h_p_title || '',
  h_p_subtitle: props.homeStatic?.h_p_subtitle || '',
  h_test_title: props.homeStatic?.h_test_title || '',
  h_b_title: props.homeStatic?.h_b_title || '',
  meta_title: props.homeStatic?.meta_title || '',
  meta_description: props.homeStatic?.meta_description || '',
  meta_tag: props.homeStatic?.meta_tag || '',
});

const pct = v => { const n = parseFloat(String(v || '').replace('%', '')); return (isNaN(n) ? 0 : Math.min(100, n)) + '%'; };

const submitCounter = () => counterForm.post('/admin/home-static/counters', { preserveScroll: true, onSuccess: () => counterForm.reset() });
const submitSkill = () => skillForm.post('/admin/home-static/skills', { preserveScroll: true, onSuccess: () => skillForm.reset() });
const submitSections = () => sectionsForm.post('/admin/home-static', { preserveScroll: true });

async function deleteCounter(item) {
  if (await confirmDialog({ title: 'Delete this number?', message: `${item.c_count} ${item.c_title}`, confirmText: 'Delete' })) {
    router.delete(`/admin/home-static/counters/${item.id}`, { preserveScroll: true });
  }
}
async function deleteSkill(item) {
  if (await confirmDialog({ title: 'Delete this capability?', message: item.s_title || item.title, confirmText: 'Delete' })) {
    router.delete(`/admin/home-static/skills/${item.id}`, { preserveScroll: true });
  }
}
</script>
