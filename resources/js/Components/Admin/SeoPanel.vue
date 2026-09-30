<template>
  <section class="rounded-xl border a-border overflow-hidden">
    <header class="flex items-center justify-between px-4 py-3 border-b a-border a-panel-2">
      <div class="flex items-center gap-2">
        <svg class="w-4 h-4 a-subtle" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M21 21l-5.2-5.2M17 10.5a6.5 6.5 0 11-13 0 6.5 6.5 0 0113 0z" /></svg>
        <span class="text-sm font-bold">Search appearance</span>
      </div>
      <span class="text-[11px] a-subtle">How it looks on Google</span>
    </header>

    <div class="p-4 space-y-4">
      <!-- Google preview -->
      <div class="rounded-lg border a-border a-panel p-3.5">
        <div class="flex items-center gap-2">
          <span class="w-6 h-6 rounded-full a-panel-3 flex items-center justify-center text-[10px] font-bold a-muted">T</span>
          <div class="min-w-0 leading-tight">
            <p class="text-[12px] a-text truncate">{{ $page.props.admin?.brand || 'Tasfia Plumbing' }}</p>
            <p class="text-[11px] a-subtle truncate">{{ siteHost }}{{ pathPrefix }}{{ form.slug || 'from-the-title' }}</p>
          </div>
        </div>
        <p class="mt-1.5 text-[17px] leading-snug text-[#1a0dab] dark:text-[#8ab4f8] line-clamp-1">{{ previewTitle }}</p>
        <p class="text-[13px] a-muted mt-0.5 line-clamp-2 leading-relaxed">{{ previewDesc }}</p>
      </div>

      <div>
        <label class="admin-label">URL slug</label>
        <div class="flex items-stretch">
          <span class="inline-flex items-center px-3 rounded-l-[10px] border border-r-0 a-border-2 a-panel-2 text-xs a-mono a-muted">{{ pathPrefix }}</span>
          <input v-model="form.slug" type="text" :placeholder="editing ? '' : 'Leave empty to make it from the title'" class="admin-input a-mono text-xs !rounded-l-none" />
        </div>
        <p v-if="form.errors?.slug" class="a-error">{{ form.errors.slug }}</p>
        <p v-else class="a-help">Short, lowercase, words joined with "-". Example: <span class="a-mono">water-heater-repair</span> (not <span class="a-mono">water-heater-repair-service-singapore-best-price</span>).</p>
        <p v-if="editing && originalSlug && form.slug !== originalSlug" class="mt-1.5 text-xs leading-relaxed a-text-warning">
          The URL will change. {{ pathPrefix }}{{ originalSlug }} will 301-redirect to the new one automatically. Avoid changing URLs that already rank or have backlinks.
        </p>
      </div>

      <div>
        <div class="flex justify-between items-baseline">
          <label class="admin-label">Meta title</label>
          <span :class="counterClass(titleLength, 30, 60)" class="text-[11px] a-mono">{{ titleLength }}/60</span>
        </div>
        <input v-model="form.meta_title" type="text" :placeholder="titleFallback || 'Main keyword first, then area or benefit'" class="admin-input" :aria-invalid="!!(form.errors?.meta_title || metaWarning)" />
        <p v-if="form.errors?.meta_title" class="a-error">{{ form.errors.meta_title }}</p>
        <p v-else-if="metaWarning" class="a-error">{{ metaWarning }}</p>
        <p v-else class="a-help">Leave empty to use the title. Under 60 characters, different from every other page. Example: <span class="a-text">Water Heater Repair Singapore | Same-Day, from S$80</span></p>
      </div>

      <div>
        <div class="flex justify-between items-baseline">
          <label class="admin-label">Meta description</label>
          <span :class="counterClass((form.meta_desc || '').length, 70, 160)" class="text-[11px] a-mono">{{ (form.meta_desc || '').length }}/160</span>
        </div>
        <textarea v-model="form.meta_desc" rows="2" placeholder="Answer the searcher's question in one line: what, where, price range, why you." class="admin-input"></textarea>
        <p class="a-help">120–160 characters; this is the grey text under the title on Google. Example: <span class="a-text">Leaking or no hot water? Licensed technicians fix most water heaters the same day across Singapore. Repairs from S$80, 90-day warranty.</span></p>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div v-if="showFocus">
          <label class="admin-label">Focus keyword</label>
          <input v-model="form.focus_keyword" type="text" placeholder="e.g. water heater repair singapore" class="admin-input" />
          <p v-if="!keywordChecks.length" class="a-help">The one search phrase this page should rank for. Used for the checks only; it is not added to the page. Example: <span class="a-mono">water heater repair singapore</span></p>
          <div v-if="keywordChecks.length" class="mt-2 flex flex-wrap gap-1.5">
            <span v-for="c in keywordChecks" :key="c.label" :class="['a-badge', c.ok ? 'a-badge-success' : 'a-badge-warning']">{{ c.ok ? '✓' : '!' }} {{ c.label }}</span>
          </div>
        </div>
        <div>
          <label class="admin-label">Canonical URL <span class="font-normal a-subtle">(optional)</span></label>
          <input v-model="form.canonical" type="url" placeholder="Leave empty = this page" class="admin-input" />
          <p class="a-help">Leave empty in almost every case: the page then points to itself. Fill it only when this page is a copy of another one, with the full address of the original. Example: <span class="a-mono break-all">{{ origin }}{{ pathPrefix }}water-heater-repair</span></p>
        </div>
      </div>

      <label class="a-toggle-row">
        <span>
          <span class="block text-sm font-semibold">Hide from Google (noindex)</span>
          <span class="block text-xs a-muted mt-0.5">Only for thin or duplicate pages you are not ready to improve. Also removed from the sitemap.</span>
        </span>
        <input v-model="form.noindex" type="checkbox" class="a-switch mt-0.5" />
      </label>
    </div>
  </section>
</template>

<script setup>
import { computed } from 'vue';
const origin = typeof window !== 'undefined' ? window.location.origin : '';

const props = defineProps({
  form: { type: Object, required: true },
  pathPrefix: { type: String, required: true },
  titleFallback: { type: String, default: '' },
  descFallback: { type: String, default: '' },
  editing: Boolean,
  originalSlug: String,
  showFocus: { type: Boolean, default: true },
  metaWarning: { type: String, default: '' },
});

const siteHost = typeof window !== 'undefined' ? window.location.host : '';
const previewTitle = computed(() => props.form.meta_title || props.titleFallback || 'Page title');
const previewDesc = computed(() => props.form.meta_desc || props.descFallback || 'Write a meta description, or Google picks text from the page.');
const titleLength = computed(() => (props.form.meta_title || props.titleFallback || '').length);

const keywordChecks = computed(() => {
  const kw = (props.form.focus_keyword || '').trim().toLowerCase();
  if (!kw) return [];
  const words = kw.split(/\s+/).filter(w => w.length > 2);
  const hasAll = text => words.every(w => (text || '').toLowerCase().includes(w));
  return [
    { label: 'In title', ok: hasAll(previewTitle.value) },
    { label: 'In description', ok: hasAll(props.form.meta_desc || props.descFallback) },
    { label: 'In URL', ok: hasAll((props.form.slug || props.titleFallback || '').replace(/-/g, ' ')) },
  ];
});

function counterClass(len, min, max) {
  if (len === 0) return 'a-subtle';
  if (len > max) return 'a-text-danger font-bold';
  if (len < min) return 'a-text-warning';
  return 'a-text-success';
}
</script>
