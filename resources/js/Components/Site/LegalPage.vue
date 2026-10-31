<template>
  <FrontendLayout>
    <PageHero :title="title" eyebrow="Legal" :lead="lead" :crumbs="[{ label: title }]">
      <template #below>
        <p class="mt-6 inline-flex items-center gap-2 text-[15px] s-muted">
          <svg class="w-[18px] h-[18px] shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" :d="ICONS.calendar" /></svg>
          Last updated <span class="font-semibold s-heading">{{ updated }}</span>
        </p>
      </template>
    </PageHero>

    <section class="section-y s-bg">
      <div class="container-app">
        <div class="mx-auto max-w-6xl grid gap-8 lg:grid-cols-[16rem_minmax(0,1fr)] lg:gap-14 xl:gap-20">
          <!-- The sections: a list that stays in view on large screens, a fold-out box on phones -->
          <aside class="min-w-0 lg:sticky lg:top-28 lg:self-start">
            <details class="lg:hidden card overflow-hidden group">
              <summary class="flex items-center justify-between gap-3 px-5 py-3.5 cursor-pointer list-none font-semibold s-heading [&::-webkit-details-marker]:hidden">
                On this page
                <svg class="w-5 h-5 s-subtle transition-transform group-open:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
              </summary>
              <ol class="border-t s-border px-2 py-2">
                <li v-for="(s, i) in all" :key="s.id">
                  <a :href="'#' + s.id" class="flex gap-3 rounded-lg px-3 py-2 text-[15px] s-text hover:bg-[var(--s-surface-2)]">
                    <span class="tabular-nums s-subtle">{{ i + 1 }}.</span>{{ s.title }}
                  </a>
                </li>
              </ol>
            </details>

            <nav class="hidden lg:block" aria-label="On this page">
              <p class="text-[14px] font-bold uppercase tracking-[0.12em] s-subtle">On this page</p>
              <ol class="mt-4 border-l s-border">
                <li v-for="(s, i) in all" :key="s.id">
                  <a :href="'#' + s.id" :aria-current="active === s.id ? 'location' : undefined"
                    :class="['-ml-px flex gap-3 border-l-2 py-1.5 pl-4 pr-2 text-[15px] leading-snug transition-colors',
                      active === s.id ? 'font-semibold s-heading border-[var(--s-accent)]' : 's-muted border-transparent hover:text-[var(--s-heading)]']">
                    <span class="tabular-nums w-5 shrink-0" :class="active === s.id ? 'text-[var(--s-accent-text)]' : 's-subtle'">{{ i + 1 }}.</span>{{ s.title }}
                  </a>
                </li>
              </ol>
              <div v-if="other" class="mt-8 border-t s-border pt-6">
                <p class="text-[14px] font-bold uppercase tracking-[0.12em] s-subtle">Also read</p>
                <Link :href="other.href" class="mt-3 inline-flex items-center gap-2 text-[15px] font-semibold text-[var(--s-accent-text)] hover:underline underline-offset-4">
                  {{ other.label }}
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6" /></svg>
                </Link>
              </div>
            </nav>
          </aside>

          <article class="min-w-0 max-w-3xl">
            <section v-for="(s, i) in all" :id="s.id" :key="s.id" ref="blocks" class="scroll-mt-28 border-t s-border py-10 first:border-t-0 first:pt-0 last:pb-0">
              <h2 class="flex items-baseline gap-3 text-[1.5rem] sm:text-[1.65rem] font-bold leading-tight tracking-[-0.01em] s-heading" style="font-family: var(--font-display)">
                <span class="tabular-nums text-[1.05rem] font-bold text-[var(--s-accent-text)]">{{ String(i + 1).padStart(2, '0') }}</span>
                {{ s.title }}
              </h2>
              <div v-if="$slots[s.id]" class="prose-site mt-4">
                <slot :name="s.id" />
              </div>

              <!-- Who to contact, one line each -->
              <dl v-if="s.id === 'contact'" class="mt-6 card divide-y divide-[var(--s-border)]">
                <div v-for="row in contactRows" :key="row.label" class="flex items-start gap-4 px-5 py-4">
                  <span class="icon-box !w-10 !h-10" aria-hidden="true">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" :d="ICONS[row.icon]" /></svg>
                  </span>
                  <div class="min-w-0">
                    <dt class="text-[14px] s-subtle">{{ row.label }}</dt>
                    <dd class="mt-0.5 text-[16px] font-semibold s-heading break-words">
                      <a v-if="row.href" :href="row.href" class="hover:text-[var(--s-accent-text)]">{{ row.value }}</a>
                      <template v-else>{{ row.value }}</template>
                    </dd>
                  </div>
                </div>
              </dl>
            </section>
          </article>
        </div>
      </div>
    </section>
  </FrontendLayout>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import FrontendLayout from '@/Layouts/FrontendLayout.vue';
import PageHero from '@/Components/Site/PageHero.vue';

// A legal page (privacy, terms): numbered sections with a list of them beside the text and a contact card at the end.
// Each section's text is passed as a slot named after its id; the contact section is added here.
const props = defineProps({
  title: { type: String, required: true },
  lead: String,
  updated: { type: String, required: true },
  sections: { type: Array, required: true },
  contactTitle: { type: String, default: 'Contact us' },
  other: Object,
});

const ICONS = {
  calendar: 'M8 3v3M16 3v3M4 9h16M5 5h14a1 1 0 011 1v13a1 1 0 01-1 1H5a1 1 0 01-1-1V6a1 1 0 011-1z',
  company: 'M4 21V5a1 1 0 011-1h9a1 1 0 011 1v16M15 9h4a1 1 0 011 1v11M3 21h18M8 8h3M8 12h3M8 16h3',
  email: 'M4 6h16a1 1 0 011 1v10a1 1 0 01-1 1H4a1 1 0 01-1-1V7a1 1 0 011-1zM3.5 7l8.5 6 8.5-6',
  phone: 'M2.25 6.75c0 8.28 6.72 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.37c0-.52-.35-.97-.85-1.09l-4.42-1.1a1.12 1.12 0 00-1.17.42l-.97 1.29a1.12 1.12 0 01-1.21.38 12.04 12.04 0 01-7.14-7.14 1.12 1.12 0 01.38-1.21l1.29-.97c.36-.27.52-.73.42-1.17l-1.1-4.42a1.12 1.12 0 00-1.09-.85H4.5A2.25 2.25 0 002.25 4.5v2.25z',
  address: 'M12 21s-7-6.1-7-11.5a7 7 0 0114 0C19 14.9 12 21 12 21zM12 12a2.5 2.5 0 100-5 2.5 2.5 0 000 5z',
};

const all = computed(() => [...props.sections, { id: 'contact', title: props.contactTitle }]);

const company = computed(() => usePage().props.company || {});
const contactRows = computed(() => {
  const c = company.value;
  return [
    { icon: 'company', label: 'Company', value: c.legal_name || c.name },
    { icon: 'email', label: 'Email', value: c.email, href: c.email && 'mailto:' + c.email },
    { icon: 'phone', label: 'Phone', value: c.phone, href: (c.tel || c.phone) && 'tel:' + (c.tel || c.phone) },
    { icon: 'address', label: 'Address', value: c.address },
  ].filter((r) => r.value);
});

// The section being read is marked in the list.
const blocks = ref([]);
const active = ref(null);
let watcher = null;
onMounted(() => {
  active.value = all.value[0]?.id;
  watcher = new IntersectionObserver((entries) => {
    const seen = entries.filter((e) => e.isIntersecting).sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top)[0];
    if (seen) active.value = seen.target.id;
  }, { rootMargin: '-20% 0px -70% 0px' });
  blocks.value.forEach((el) => watcher.observe(el));
});
onBeforeUnmount(() => watcher?.disconnect());
</script>
