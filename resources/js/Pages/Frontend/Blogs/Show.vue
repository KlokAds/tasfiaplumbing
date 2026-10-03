<template>
  <FrontendLayout>
    <!-- Reading progress -->
    <div class="fixed top-0 inset-x-0 z-[60] h-[3px] pointer-events-none" aria-hidden="true">
      <div class="h-full bg-[var(--s-accent)] origin-left" :style="{ transform: `scaleX(${progress})` }"></div>
    </div>

    <!-- Header -->
    <header class="s-bg border-b s-border">
      <div class="container-app pt-8 pb-10 lg:pt-12 lg:pb-12">
        <div class="max-w-[66rem]">
          <nav class="crumbs" aria-label="Breadcrumb">
            <Link href="/">Home</Link><span class="sep">/</span>
            <Link href="/blogs">Articles</Link>
            <template v-if="blog.primary_service"><span class="sep">/</span><Link :href="`/service/${blog.primary_service.slug}`">{{ blog.primary_service.name }}</Link></template>
          </nav>
          <Link v-if="blog.primary_service" :href="`/blogs?service=${blog.primary_service.slug}`" class="mt-5 inline-flex eyebrow">{{ blog.primary_service.name }}</Link>
          <h1 class="h-page mt-3 [text-wrap:balance]">{{ blog.name }}</h1>
          <p v-if="blog.excerpt" class="lead mt-4">{{ blog.excerpt }}</p>

          <div class="mt-7 max-w-[46rem] flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-3">
              <img v-if="author.image" :src="img(author.image, 96)" :alt="author.name" class="w-11 h-11 rounded-full object-cover" />
              <span v-else class="w-11 h-11 rounded-full bg-[var(--s-heading)] text-[var(--s-bg)] text-[14px] font-bold flex items-center justify-center">{{ initials }}</span>
              <div class="leading-tight">
                <p class="font-semibold s-heading text-[15.5px]">{{ author.name }}<span v-if="author.job_title" class="font-normal opacity-75"> · {{ author.job_title }}</span></p>
                <p class="text-[13.5px] s-subtle mt-0.5">
                  <time v-if="published" :datetime="blog.published_at">{{ date(published) }}</time>
                  <template v-if="updated && date(updated) !== date(published)"> · Updated <time :datetime="blog.content_updated_at">{{ date(updated) }}</time></template>
                  · {{ readMinutes }} min read
                </p>
              </div>
            </div>
            <ShareButtons :title="blog.name" />
          </div>
        </div>
      </div>
    </header>

    <section class="s-bg">
      <div class="container-app py-10 lg:py-12 grid grid-cols-1 lg:grid-cols-[minmax(0,46rem)_1fr] gap-10 xl:gap-16">
        <div class="min-w-0">
          <div v-if="blog.image" class="photo-frame aspect-[16/9] mb-10">
            <img :src="img(blog.image, 640)" alt="" aria-hidden="true" class="bg" />
            <img :src="img(blog.image, 1280)" :srcset="srcset(blog.image, 1600)" sizes="(min-width: 1024px) 736px, 100vw" :alt="blog.name" class="fg" fetchpriority="high" />
          </div>

          <!-- Mobile table of contents -->
          <details v-if="toc.length > 2" class="lg:hidden card mb-8 group">
            <summary class="flex items-center justify-between px-5 py-4 cursor-pointer list-none font-semibold s-heading">
              On this page <span class="text-[13px] font-medium s-subtle">{{ toc.length }} sections <span class="inline-block transition group-open:rotate-180">▾</span></span>
            </summary>
            <ol class="px-5 pb-4 pt-3 space-y-2 text-[15.5px] border-t s-border">
              <li v-for="h in toc" :key="h.id"><a :href="`#${h.id}`" class="s-muted hover:text-[var(--s-accent-text)]">{{ h.text }}</a></li>
            </ol>
          </details>

          <article ref="body" class="prose-site" v-html="html"></article>

          <!-- Prices for the related service -->
          <section v-if="prices.length" class="mt-12 card overflow-hidden">
            <div class="px-5 py-4 border-b s-border s-surface-2 flex items-center justify-between gap-3">
              <h2 class="h-card">{{ blog.primary_service.name }} prices</h2>
              <Link :href="`/service/${blog.primary_service.slug}`" class="text-[14px] link">Service details →</Link>
            </div>
            <table class="w-full text-[15.5px]">
              <tbody>
                <tr v-for="p in prices" :key="p.id" class="border-t first:border-t-0 s-border">
                  <td class="px-5 py-3">{{ p.item }}</td>
                  <td class="px-5 py-3 text-right font-bold s-heading whitespace-nowrap">{{ p.label }}</td>
                </tr>
              </tbody>
            </table>
          </section>

          <section v-if="blog.faqs?.length" class="mt-12">
            <h2 class="h-section !text-[1.6rem] mb-5">Frequently asked questions</h2>
            <FaqList :faqs="blog.faqs" />
          </section>

          <!-- End of article -->
          <div class="mt-12 rounded-[20px] text-white p-6 sm:p-8 relative overflow-hidden" style="background: var(--s-dark)">
            <div class="relative flex flex-col sm:flex-row sm:items-center justify-between gap-5">
              <div>
                <p class="text-[18px] font-bold" style="font-family: var(--font-display)">{{ blog.primary_service ? `Need ${blog.primary_service.name.toLowerCase()}?` : 'Need help with this?' }}</p>
                <p class="mt-1 text-[15px] text-white/80">Send a photo and get a price range, usually the same day.</p>
              </div>
              <WhatsAppButton green :topic="blog.primary_service?.name || ''" class="shrink-0" />
            </div>
          </div>

          <div class="mt-8 flex flex-wrap items-center justify-between gap-4 pt-6 border-t s-border">
            <p class="text-[15px] s-muted">Found this useful? Share it.</p>
            <ShareButtons :title="blog.name" />
          </div>

          <!-- About the author: the person, or the team with its title and bio -->
          <aside class="mt-8 card p-6 sm:p-7">
            <p class="text-[13px] font-bold uppercase tracking-[0.1em] s-subtle">About the author</p>
            <div class="mt-4 flex gap-4 sm:gap-5">
              <img v-if="author.image" :src="img(author.image, 160)" :alt="author.name" class="w-16 h-16 rounded-full object-cover shrink-0" />
              <span v-else class="w-16 h-16 rounded-full bg-[var(--s-heading)] text-[var(--s-bg)] text-[18px] font-bold flex items-center justify-center shrink-0">{{ initials }}</span>
              <div class="min-w-0">
                <p class="h-card">{{ author.name }}</p>
                <p v-if="author.job_title" class="mt-0.5 text-[14.5px] s-muted">{{ author.job_title }}</p>
                <p v-if="author.bio" class="mt-3 text-[15.5px] s-muted leading-relaxed">{{ author.bio }}</p>
                <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-[14px]">
                  <span v-if="updated || published" class="s-subtle">Last updated <time :datetime="blog.content_updated_at || blog.published_at">{{ date(updated || published) }}</time></span>
                  <a v-if="author.social_url" :href="author.social_url" target="_blank" rel="noopener me" class="link">View profile →</a>
                </div>
              </div>
            </div>
          </aside>
        </div>

        <!-- Desktop sidebar -->
        <aside class="hidden lg:block">
          <div class="sticky top-24 space-y-6">
            <nav v-if="toc.length > 2" aria-label="On this page">
              <p class="text-[13px] font-bold uppercase tracking-[0.12em] s-subtle mb-3">On this page</p>
              <ol class="border-l s-border max-h-[calc(100vh-26rem)] overflow-y-auto" style="scrollbar-width: thin">
                <li v-for="h in toc" :key="h.id">
                  <a :href="`#${h.id}`" :class="['block -ml-px pl-4 py-1.5 border-l-2 text-[15px] leading-snug transition-colors', active === h.id ? 'border-[var(--s-accent)] s-heading font-semibold' : 'border-transparent s-muted hover:text-[var(--s-heading)]']">{{ h.text }}</a>
                </li>
              </ol>
            </nav>

            <div class="card p-5" style="box-shadow: var(--s-shadow)">
              <p class="text-[13px] font-bold uppercase tracking-[0.12em] s-subtle">Need this fixed?</p>
              <p class="h-card mt-2">{{ blog.primary_service ? blog.primary_service.name : 'Talk to our team' }}</p>
              <p class="mt-1.5 text-[15px] s-muted leading-relaxed">{{ blog.primary_service?.short_summary || 'Send a photo, get a clear price range, usually the same day.' }}</p>
              <div class="mt-4 grid gap-2">
                <WhatsAppButton :topic="blog.primary_service?.name || ''" />
                <Link v-if="blog.primary_service" :href="`/service/${blog.primary_service.slug}`" class="btn btn-secondary">See the service</Link>
                <a v-if="company.tel" :href="'tel:' + company.tel" class="btn btn-ghost">Call {{ company.phone }}</a>
              </div>
            </div>
          </div>
        </aside>
      </div>
    </section>

    <section v-if="relatedBlogs.length" class="section-y s-bg-alt">
      <div class="container-app">
        <div class="flex items-end justify-between gap-4 mb-8">
          <h2 class="h-section">Keep reading</h2>
          <Link href="/blogs" class="link text-[15px]">All articles →</Link>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
          <ArticleCard v-for="b in relatedBlogs" :key="b.id" :article="b" />
        </div>
      </div>
    </section>
  </FrontendLayout>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import FrontendLayout from '@/Layouts/FrontendLayout.vue';
import ArticleCard from '@/Components/Site/ArticleCard.vue';
import FaqList from '@/Components/Site/FaqList.vue';
import WhatsAppButton from '@/Components/Site/WhatsAppButton.vue';
import ShareButtons from '@/Components/Site/ShareButtons.vue';
import { useContact } from '@/Composables/useContact';
import { img, srcset } from '@/utils/img';

const props = defineProps({
  blog: Object,
  author: { type: Object, default: () => ({}) },
  relatedBlogs: { type: Array, default: () => [] },
  prices: { type: Array, default: () => [] },
});
const { company } = useContact();

const date = d => new Date(d).toLocaleDateString('en-SG', { day: 'numeric', month: 'long', year: 'numeric' });
const published = computed(() => props.blog.published_at || props.blog.created_at);
const updated = computed(() => props.blog.content_updated_at);
const readMinutes = computed(() => Math.max(1, Math.round((props.blog.desc || '').replace(/<[^>]*>/g, ' ').split(/\s+/).filter(Boolean).length / 220)));
const initials = computed(() => (props.author.name || 'T').split(/\s+/).map(w => w[0]).slice(0, 2).join('').toUpperCase());

// Give every H2/H3 an id so the table of contents (and Google's jump links) can point to it.
const slugify = t => t.toLowerCase().replace(/<[^>]*>/g, '').replace(/&[a-z]+;/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '').slice(0, 60);
const parsed = computed(() => {
  const toc = [];
  const used = new Set();
  const html = (props.blog.desc || '')
    // A link with nothing to read inside (only spaces or empty tags, no image) is dropped, keeping what was inside.
    .replace(/<a\b[^>]*>((?:\s|&nbsp;|\u00a0|<(?!img\b)[^>]*>)*)<\/a>/gi, '$1')
    .replace(/<h([23])([^>]*)>([\s\S]*?)<\/h\1>/gi, (m, level, attrs, inner) => {
    const text = inner.replace(/<[^>]*>/g, '').replace(/&amp;/g, '&').replace(/&nbsp;|\u00a0/g, ' ').trim();
    if (!text && !/<img\b/i.test(inner)) return '';
    let id = slugify(text) || 'section';
    while (used.has(id)) id += '-2';
    used.add(id);
    // Empty headings (left over from the editor) stay out of the table of contents.
    if (level === '2' && text) toc.push({ id, text });
    return /\sid=/.test(attrs) ? m : `<h${level}${attrs} id="${id}">${inner}</h${level}>`;
  });
  return { html, toc };
});
const html = computed(() => parsed.value.html);
const toc = computed(() => parsed.value.toc);

// Reading progress bar and the highlighted section in the sidebar.
const body = ref(null);
const progress = ref(0);
const active = ref(null);
let ticking = false;
function update() {
  ticking = false;
  const el = body.value;
  if (!el) return;
  const r = el.getBoundingClientRect();
  progress.value = Math.min(1, Math.max(0, -r.top / Math.max(1, r.height - window.innerHeight * 0.6)));
  let current = toc.value[0]?.id || null;
  for (const h of toc.value) {
    const node = document.getElementById(h.id);
    if (node && node.getBoundingClientRect().top < 140) current = h.id;
  }
  active.value = current;
}
const onScroll = () => { if (!ticking) { ticking = true; requestAnimationFrame(update); } };
onMounted(() => { update(); window.addEventListener('scroll', onScroll, { passive: true }); });
onBeforeUnmount(() => window.removeEventListener('scroll', onScroll));
</script>
