<template>
  <AdminLayout title="Writing guide">
    <PageHeader title="Writing guide" description="How to write and fill in every page so it scores well for Google (SEO), answer engines (AEO), AI search (GEO) and trust (E-E-A-T). The rules and checks come from the same code that scores your pages.">
      <template #meta><p class="mt-2 text-sm a-subtle">Site scanned {{ ago(scan.generated_at) }}. It is scanned again every morning, and the plan is emailed every Friday.</p></template>
      <button @click="rescan" :disabled="scanning" class="admin-btn-secondary">{{ scanning ? 'Scanning…' : 'Re-scan now' }}</button>
    </PageHeader>

    <div class="grid grid-cols-1 xl:grid-cols-[15rem_1fr] gap-6">
      <nav class="admin-card p-2 h-fit flex xl:flex-col gap-1 overflow-x-auto a-side-sticky a-side-sticky-page guide-nav" aria-label="Guide sections">
        <a v-for="s in sections" :key="s.id" :href="`#${s.id}`" @click.prevent="jump(s.id)"
          :class="['guide-toc', active === s.id && 'on']" :aria-current="active === s.id ? 'true' : null">{{ s.label }}</a>
      </nav>

      <div class="space-y-6 min-w-0 guide">
        <!-- This week's plan -->
        <section id="plan" class="admin-card p-5 sm:p-6 scroll-mt-32">
          <h2>This week's plan</h2>
          <p>Made by the daily scan. Do these in order; the scan picks new ones as you finish them.</p>

          <h3 class="font-bold mt-5 mb-2">Articles to update</h3>
          <ol v-if="plan.articles?.length" class="space-y-3">
            <li v-for="(a, i) in plan.articles" :key="a.url" class="rounded-xl border a-border p-4">
              <div class="flex items-baseline gap-2 min-w-0">
                <span :class="['a-badge tabular-nums shrink-0', badge(a.score)]">{{ a.score }}</span>
                <a :href="a.url" class="font-bold hover:underline min-w-0">{{ i + 1 }}. {{ a.name }}</a>
              </div>
              <p class="mt-1 a-muted">Why now: {{ a.reasons.join(' · ') }}</p>
              <p v-if="a.missing.length" class="mt-1">Add: {{ a.missing.join(', ') }}. <a href="#article" class="underline a-muted">How</a></p>
            </li>
          </ol>
          <p v-else class="a-muted">Nothing to update this week. Every article scores 80+ or was updated in the last 30 days.</p>

          <h3 class="font-bold mt-6 mb-2">New article ideas</h3>
          <template v-if="scan.search_connected">
            <ul v-if="plan.ideas?.length" class="space-y-2">
              <li v-for="q in plan.ideas" :key="q.query" class="flex flex-wrap items-baseline justify-between gap-2 rounded-xl border a-border px-4 py-3">
                <span class="font-semibold">"{{ q.query }}"</span>
                <span class="a-muted tabular-nums">{{ fmt(q.impressions) }} impressions · position {{ q.position }}</span>
              </li>
            </ul>
            <p v-else class="a-muted">No new questions this week: your pages already cover what people search for.</p>
            <p class="mt-2 a-subtle">People search for these on Google and see your site, but no page answers them yet. One article per question, written as in "Writing an article".</p>
          </template>
          <p v-else class="a-muted">Connect Google Search Console (<Link href="/admin/insights/google" class="underline">Google connections</Link>) and the scan will suggest articles from what people really search for.</p>

          <h3 class="font-bold mt-6 mb-2">Services to improve</h3>
          <ul v-if="plan.services?.length" class="space-y-2">
            <li v-for="s in plan.services" :key="s.url" class="rounded-xl border a-border px-4 py-3">
              <div class="flex items-baseline gap-2 min-w-0">
                <span :class="['a-badge tabular-nums shrink-0', badge(s.score)]">{{ s.score }}</span>
                <a :href="s.url" class="font-semibold hover:underline min-w-0">{{ s.name }}</a>
              </div>
              <p v-if="s.missing.length" class="mt-1 a-muted">Add: {{ s.missing.join(', ') }}. <a href="#service" class="underline">How</a></p>
            </li>
          </ul>
          <p v-else class="a-muted">Every live service scores 80 or more.</p>

          <h3 class="font-bold mt-6 mb-2">Other pages and settings</h3>
          <ul v-if="plan.site?.length" class="space-y-2">
            <li v-for="t in plan.site" :key="t.label" class="rounded-xl border a-border px-4 py-3 flex items-start justify-between gap-3">
              <div class="min-w-0">
                <p class="font-semibold"><span :class="['a-badge mr-1', t.level === 'must' ? 'a-badge-danger' : 'a-badge-warning']">{{ t.level === 'must' ? 'Must' : 'Should' }}</span> {{ t.label }}</p>
                <p class="mt-1 a-muted">{{ t.detail }}</p>
              </div>
              <Link :href="t.url" class="admin-btn-secondary shrink-0">Fix</Link>
            </li>
          </ul>
          <p v-else class="a-muted">Nothing open. See "Other pages and settings" below for regular upkeep.</p>
        </section>

        <!-- Live scan -->
        <section id="now" class="admin-card p-5 sm:p-6 scroll-mt-32">
          <h2>Gaps across the site</h2>
          <p>Every live article and service checked against the rules below. Fix the items at the top first: they cost the most points across the most pages.</p>
          <div class="flex flex-wrap gap-2 mt-4">
            <button v-for="t in scanTypes" :key="t.key" @click="scanType = t.key" :class="[scanType === t.key ? 'admin-btn-primary' : 'admin-btn-secondary']">{{ t.label }} ({{ fmt(scan.types?.[t.key]?.total || 0) }})</button>
          </div>
          <template v-if="current.total">
            <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 mt-4">
              <div v-for="k in ['score', 'seo', 'aeo', 'geo', 'eeat']" :key="k" :class="['rounded-xl p-3 text-center', tone(current.avg[k])]">
                <p class="text-sm font-semibold opacity-80">{{ k === 'score' ? 'Average' : pillarShort[k] }}</p>
                <p class="text-2xl font-bold tabular-nums">{{ current.avg[k] }}</p>
              </div>
            </div>

            <div v-if="scanType === 'article' && scan.authors?.length" class="mt-4 rounded-xl border a-border p-4 warn">
              <p class="font-bold">Writers without a job title or bio</p>
              <p class="a-muted">One fix per person lifts E-E-A-T on all their articles. Each writer fills these in under <Link href="/admin/account" class="underline">My account</Link>, or the Super Admin does it in <Link href="/admin/users" class="underline">Team</Link>. Articles without an author show the Default author chosen on the Articles page, else the team with its own title and bio.</p>
              <ul class="mt-2 space-y-1">
                <li v-for="a in scan.authors" :key="a.name"><b>{{ a.name }}</b> · {{ fmt(a.articles) }} live {{ a.articles === 1 ? 'article' : 'articles' }} · missing {{ [!a.job_title && 'job title', !a.bio && 'bio'].filter(Boolean).join(' and ') }}</li>
              </ul>
            </div>

            <h3 class="font-bold mt-6 mb-2">Fix these first</h3>
            <ol class="space-y-3">
              <li v-for="(issue, i) in shownIssues" :key="issue.pillar + issue.label" class="rounded-xl border a-border p-4">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                  <p class="font-bold">{{ i + 1 }}. {{ issue.label }} <span class="font-normal a-muted">· {{ pillarShort[issue.pillar] }}</span></p>
                  <p class="tabular-nums a-muted">{{ fmt(issue.count) }} of {{ fmt(current.total) }} ({{ issue.share }}%)</p>
                </div>
                <div class="share mt-2"><i :style="{ width: issue.share + '%' }"></i></div>
                <p class="mt-2">{{ issue.tip }}</p>
                <p class="mt-2 text-sm a-muted">Weakest pages with this gap:</p>
                <ul class="mt-1 flex flex-col gap-1">
                  <li v-for="pg in issue.pages" :key="pg.url" class="flex items-baseline gap-2 min-w-0">
                    <span :class="['a-badge tabular-nums shrink-0', badge(pg.score)]">{{ pg.score }}</span>
                    <a :href="pg.url" class="truncate hover:underline">{{ pg.name }}</a>
                  </li>
                </ul>
              </li>
            </ol>
            <button v-if="current.issues.length > shownCount" @click="showAll = !showAll" class="admin-btn-secondary mt-3">{{ showAll ? 'Show fewer' : `Show all ${current.issues.length} gaps` }}</button>
            <p v-if="!current.issues.length" class="mt-4">Nothing missing. Every check passes.</p>
          </template>
          <p v-else class="mt-4 a-muted">No live {{ scanType === 'article' ? 'articles' : 'services' }} yet.</p>
        </section>

        <!-- Scores -->
        <section id="scores" class="admin-card p-5 sm:p-6 scroll-mt-32">
          <h2>How pages are scored</h2>
          <p>Every article and service page gets four scores out of 100. You see them in the editor sidebar, in the approval email and in SEO Health. Aim for <b>80 or more</b> in each one.</p>
          <div class="grid sm:grid-cols-2 gap-3 mt-4">
            <div v-for="p in pillarCards" :key="p.key" class="rounded-xl border a-border p-4">
              <p class="font-bold">{{ p.short }} <span v-if="pillars[p.key] !== p.short" class="font-normal a-muted">· {{ pillars[p.key] }}</span></p>
              <p class="mt-1 a-muted">{{ p.text }}</p>
            </div>
          </div>
          <p class="mt-4 a-muted">The <b>SEO checklist</b> score (the big number in the approval email) is the publish gate: errors there must be fixed before a page goes live. The four scores show how good the content is.</p>
        </section>

        <!-- Article -->
        <section id="article" class="admin-card p-5 sm:p-6 scroll-mt-32">
          <h2>Writing an article</h2>
          <p>One article answers one question people search for. Use this outline. Each block below is a part of the page, in order.</p>
          <ol class="guide-outline mt-4">
            <li v-for="(b, i) in outline" :key="i">
              <div class="flex flex-wrap items-baseline gap-x-2">
                <span class="font-bold">{{ b.part }}</span>
                <span class="a-badge">{{ b.where }}</span>
              </div>
              <p class="mt-1">{{ b.rule }}</p>
              <p v-if="b.example" class="example"><span class="lbl">Example</span>{{ b.example }}</p>
            </li>
          </ol>
          <p class="mt-4 a-muted">Prices, warranty and response times in the examples come from your own service settings. If a value shows as <span class="ph">S$__</span>, there is no price in the price list yet: put your real price there, never a guess.</p>
        </section>

        <!-- Direct answer -->
        <section id="answer" class="admin-card p-5 sm:p-6 scroll-mt-32">
          <h2>The direct answer</h2>
          <p>The <b>Direct answer / excerpt</b> field (articles) and the <b>Direct answer</b> field (services) is the text Google AI Overviews and ChatGPT quote most. It must answer the question on its own, without the rest of the page.</p>
          <div class="grid md:grid-cols-2 gap-3 mt-4">
            <div class="rounded-xl border a-border p-4 bad">
              <p class="lbl">Weak</p>
              <p>Welcome to {{ brand || 'our company' }}! Are you looking for the best {{ svcLower }} in Singapore? Our expert team delivers quality service you can trust.</p>
              <p class="mt-2 a-muted">No answer, no price, no facts. Every company could write this.</p>
            </div>
            <div class="rounded-xl border a-border p-4 good">
              <p class="lbl">Strong</p>
              <p>{{ answerExample }}</p>
              <p class="mt-2 a-muted">Answers first, gives the S$ range and real facts, says where it applies, names the business.</p>
            </div>
          </div>
          <ul class="ticks mt-4">
            <li>Answer the question in the first sentence.</li>
            <li>40–60 words, 1–2 sentences.</li>
            <li>Include a S$ range or a number (time, warranty).</li>
            <li>Say where it applies: Singapore, HDB, condo, landed, office.</li>
            <li>Name the business once.</li>
            <li>Only real prices and facts. If you don't know one, leave it out.</li>
          </ul>
        </section>

        <!-- SEO fields -->
        <section id="fields" class="admin-card p-5 sm:p-6 scroll-mt-32">
          <h2>The SEO fields</h2>
          <p>These fields are in the SEO panel of every article, service, location and category.</p>
          <div class="mt-4 space-y-3">
            <div v-for="f in fields" :key="f.name" class="rounded-xl border a-border p-4">
              <p class="font-bold">{{ f.name }}</p>
              <p class="mt-1">{{ f.rule }}</p>
              <p v-if="f.good" class="example"><span class="lbl">Good</span>{{ f.good }}</p>
              <p v-if="f.bad" class="example bad-ex"><span class="lbl">Avoid</span>{{ f.bad }}</p>
            </div>
          </div>
        </section>

        <!-- Service pages -->
        <section id="service" class="admin-card p-5 sm:p-6 scroll-mt-32">
          <h2>Service pages</h2>
          <p>A service page is where people book. It must answer: what is it, how much, how fast, what guarantee, where. Aim for at least <b>{{ rules.min_words?.service }} words</b> and <b>{{ rules.min_faqs?.service }} FAQs</b>.</p>
          <div class="mt-4 space-y-3">
            <div v-for="f in serviceParts" :key="f.name" class="rounded-xl border a-border p-4">
              <div class="flex flex-wrap items-baseline gap-x-2"><span class="font-bold">{{ f.name }}</span><span class="a-badge">{{ f.where }}</span></div>
              <p class="mt-1">{{ f.rule }}</p>
              <p v-if="f.example" class="example"><span class="lbl">Example</span>{{ f.example }}</p>
            </div>
          </div>
        </section>

        <!-- Location pages -->
        <section id="location" class="admin-card p-5 sm:p-6 scroll-mt-32">
          <h2>Location pages</h2>
          <p>Each area page must be truly about that area. Pages that only swap the area name are treated as duplicates and do not rank. Aim for at least <b>{{ rules.min_words?.location }} words</b> and <b>{{ rules.min_faqs?.location }} FAQs</b>.</p>
          <ul class="ticks mt-4">
            <li v-for="t in locationParts" :key="t"><span v-html="t"></span></li>
          </ul>
        </section>

        <!-- Other pages -->
        <section id="other" class="admin-card p-5 sm:p-6 scroll-mt-32">
          <h2>Other pages and settings</h2>
          <p>These pages are not scored one by one, but Google and AI search read them to decide whether to trust the whole site.</p>
          <div class="mt-4 grid md:grid-cols-2 gap-3">
            <div v-for="o in otherPages" :key="o.name" class="rounded-xl border a-border p-4 flex flex-col gap-1">
              <div class="flex items-baseline justify-between gap-2">
                <Link :href="o.href" class="font-bold hover:underline">{{ o.name }}</Link>
                <span class="a-badge whitespace-nowrap">{{ o.when }}</span>
              </div>
              <ul class="ticks small">
                <li v-for="t in o.items" :key="t">{{ t }}</li>
              </ul>
            </div>
          </div>
        </section>

        <!-- All checks -->
        <section id="checks" class="admin-card p-5 sm:p-6 scroll-mt-32">
          <h2>Every check behind the scores</h2>
          <p>This is the full list the editor uses. Heavier checks (weight 3) move the score most.</p>
          <div class="flex gap-2 mt-4">
            <button v-for="t in ['article', 'service']" :key="t" @click="checkType = t" :class="[checkType === t ? 'admin-btn-primary' : 'admin-btn-secondary']">{{ t === 'article' ? 'Articles' : 'Service pages' }}</button>
          </div>
          <div class="mt-4 grid lg:grid-cols-2 gap-3">
            <div v-for="(list, key) in checks[checkType]" :key="key" class="rounded-xl border a-border p-4">
              <p class="font-bold">{{ pillarShort[key] }} <span v-if="pillars[key] !== pillarShort[key]" class="font-normal a-muted">· {{ pillars[key] }}</span></p>
              <ul class="mt-2 space-y-2">
                <li v-for="c in list" :key="c.label">
                  <p class="font-semibold">{{ c.label }} <span class="a-subtle font-normal">· weight {{ c.weight }}</span></p>
                  <p class="a-muted">{{ c.tip }}</p>
                </li>
              </ul>
            </div>
          </div>
        </section>

        <!-- Never -->
        <section id="never" class="admin-card p-5 sm:p-6 scroll-mt-32">
          <h2>Never do this</h2>
          <ul class="crosses">
            <li v-for="t in never" :key="t">{{ t }}</li>
          </ul>
        </section>

        <!-- Publish checklist -->
        <section id="publish" class="admin-card p-5 sm:p-6 scroll-mt-32">
          <h2>Before you press Publish</h2>
          <ul class="checklist">
            <li v-for="(t, i) in publishList" :key="i">
              <label class="flex items-start gap-3 cursor-pointer"><input type="checkbox" class="mt-1" /> <span>{{ t }}</span></label>
            </li>
          </ul>
          <p class="mt-3 a-subtle">The ticks are only for you and are not saved.</p>
        </section>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';

const props = defineProps({
  brand: { type: String, default: '' },
  rules: { type: Object, default: () => ({}) },
  pillars: { type: Object, default: () => ({}) },
  checks: { type: Object, default: () => ({ article: {}, service: {} }) },
  scan: { type: Object, default: () => ({ types: {}, authors: [], plan: {} }) },
  example: { type: Object, default: () => ({}) },
});

const checkType = ref('article');

// ---------- Table of contents: highlight the section in view, smooth jump on click ----------
const active = ref('plan');
let observer;
function jump(id) {
  active.value = id;
  document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  history.replaceState(history.state, '', `#${id}`);
}
onMounted(() => {
  const visible = new Map();
  observer = new IntersectionObserver((entries) => {
    entries.forEach((e) => (e.isIntersecting ? visible.set(e.target.id, e.boundingClientRect.top) : visible.delete(e.target.id)));
    // The topmost section that reaches the upper part of the screen is the one being read.
    const first = sections.find((s) => visible.has(s.id));
    if (first) active.value = first.id;
  }, { rootMargin: '-128px 0px -55% 0px' });
  sections.forEach((s) => { const el = document.getElementById(s.id); if (el) observer.observe(el); });
  const hash = location.hash.slice(1);
  if (sections.some((s) => s.id === hash)) active.value = hash;
});
onBeforeUnmount(() => observer?.disconnect());

// ---------- Live scan ----------
const scanTypes = [{ key: 'article', label: 'Articles' }, { key: 'service', label: 'Service pages' }];
const scanType = ref('article');
const showAll = ref(false);
const shownCount = 6;
const scanning = ref(false);
const plan = computed(() => props.scan.plan || {});
const current = computed(() => props.scan.types?.[scanType.value] || { total: 0, issues: [] });
const shownIssues = computed(() => (showAll.value ? current.value.issues : current.value.issues.slice(0, shownCount)));
const fmt = (n) => String(n).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
const tone = (s) => (s >= 80 ? 'tone-good' : s >= 60 ? 'tone-mid' : 'tone-bad');
const badge = (s) => (s >= 80 ? 'a-badge-success' : s >= 60 ? 'a-badge-warning' : 'a-badge-danger');
function ago(iso) {
  if (!iso) return 'never';
  const d = (Date.now() - new Date(iso).getTime()) / 1000;
  if (d < 60) return 'just now';
  if (d < 3600) return `${Math.floor(d / 60)} min ago`;
  if (d < 86400) return `${Math.floor(d / 3600)} h ago`;
  return `${Math.floor(d / 86400)} days ago`;
}
function rescan() {
  scanning.value = true;
  router.get('/admin/seo/guide', { refresh: 1 }, { preserveScroll: true, replace: true, onFinish: () => { scanning.value = false; } });
}
const year = new Date().getFullYear();
const pillarShort = { seo: 'SEO', aeo: 'AEO', geo: 'GEO', eeat: 'E-E-A-T' };

const sections = [
  { id: 'plan', label: "This week's plan" },
  { id: 'now', label: 'Gaps across the site' },
  { id: 'scores', label: 'How pages are scored' },
  { id: 'article', label: 'Writing an article' },
  { id: 'answer', label: 'The direct answer' },
  { id: 'fields', label: 'The SEO fields' },
  { id: 'service', label: 'Service pages' },
  { id: 'location', label: 'Location pages' },
  { id: 'other', label: 'Other pages and settings' },
  { id: 'checks', label: 'Every check behind the scores' },
  { id: 'never', label: 'Never do this' },
  { id: 'publish', label: 'Before you press Publish' },
];

const pillarCards = [
  { key: 'seo', short: 'SEO', text: 'Can Google find and rank the page: title, description, focus keyword, length, headings, links, images.' },
  { key: 'aeo', short: 'AEO', text: 'Can Google AI Overviews, ChatGPT and voice assistants lift a clear answer: direct answer, question headings, FAQs, lists, prices.' },
  { key: 'geo', short: 'GEO', text: 'Will AI search trust and quote it: real numbers, Singapore context, official sources, your brand name, fresh content.' },
  { key: 'eeat', short: 'E-E-A-T', text: 'Experience, expertise, authority, trust: a real named author with a job title and bio, first-hand job details, a linked service.' },
];

// ---------- Examples built from this site's own services ----------
const svc = computed(() => props.example.service || 'Your service');
const svcLower = computed(() => svc.value.toLowerCase());
const loc = computed(() => props.example.location || 'Tampines');
const money = (n) => `S$${Number(n).toLocaleString('en', { maximumFractionDigits: 2 })}`;
const priceText = computed(() => {
  const { price_from: f, price_to: t } = props.example;
  if (!f) return 'S$__–S$__';
  return t && t > f ? `${money(f)}–${money(t)}` : `from ${money(f)}`;
});
const extras = computed(() => [
  props.example.response_time && `${props.example.response_time} response`,
  props.example.warranty && `${props.example.warranty} warranty`,
].filter(Boolean).join(', '));
const answerExample = computed(() => `${svc.value} in Singapore usually costs ${priceText.value}, depending on the job and parts needed. ${props.brand || 'We'} ${props.brand ? 'serves' : 'serve'} HDB flats, condos and landed homes islandwide${extras.value ? ` (${extras.value})` : ''}.`);

// Keyword first, brand last; the brand is dropped when it would push the title past the limit.
const metaTitleExample = computed(() => {
  const max = props.rules.title_max ?? 60;
  const withBrand = `${svc.value} Singapore | ${props.brand || 'Brand'}`;
  return withBrand.length <= max ? withBrand : `${svc.value} in Singapore`;
});

const outline = computed(() => [
  { part: 'Title', where: 'Title (H1)', rule: 'The question or main keyword first, 30–60 characters. Add the year only to price articles.', example: `How Much Does ${svc.value} Cost in Singapore (${year})?` },
  { part: 'Direct answer', where: 'Direct answer / excerpt', rule: '40–60 words that answer the title on their own, with the S$ range. See "The direct answer" below.', example: answerExample.value },
  { part: 'Short intro', where: 'Article body, first paragraph', rule: 'Under 70 words. Repeat the answer in plain words and use the focus keyword once. No "Welcome to…".' },
  { part: 'Price section', where: 'H2 + table', rule: 'An H2 written as a question, then a table: job, price range in S$, time needed. Only real prices from the Price list.', example: `How much does ${svcLower.value} cost in Singapore?` },
  { part: 'Signs or causes', where: 'H2 + bullet list', rule: 'What the reader sees at home and what causes it. A bullet list is easy for answer engines to lift.', example: `Signs you need ${svcLower.value}` },
  { part: 'How your team does it', where: 'H2 + numbered steps', rule: 'The real steps your team follows, plus one real job: area, property type, what you found, what you did. This is the "experience" in E-E-A-T.', example: `On a recent HDB job in ${loc.value}, our technician found [what you found] and [what you did, how long it took].` },
  { part: 'What we see on real jobs', where: 'Button “+ Add From our jobs section”', rule: 'Press the button under the article body. It adds this H2 with three labelled lines: where you see the problem most, one recent real job (area and building type, no names), and your advice, plus a photo line. Replace every [Replace: …] note with real details; an article cannot be submitted or published while one is left. On the page it shows as a "From our jobs" card. This is the strongest "experience" signal for E-E-A-T.', example: `A recent job: a customer in ${loc.value} called us because … On site, our technician found … and we … in about an hour. Our advice: we recommend …` },
  { part: 'Rules and safety', where: 'H2 + official link', rule: 'Where a rule applies (HDB renovation, PUB, SCDF fire doors, BCA, NEA), say it in one line and link to the official page.' },
  { part: 'Repair or replace / how to choose', where: 'H2', rule: 'Help the reader decide. Real numbers beat adjectives: lifespan in years, cost difference, time.' },
  { part: 'Call to action', where: 'Last paragraph', rule: 'Name the business, link to the service page and say how to book (WhatsApp, phone, form).' },
  { part: 'FAQs', where: 'FAQ fields', rule: 'At least 3 real questions with short, complete answers (1–3 sentences). Don\'t repeat the headings.', example: `How long does ${svcLower.value} take?` },
  { part: 'Links and image', where: 'Body + sidebar', rule: `At least 2 links to your own pages (the service, a related article), a featured image, alt text on every photo. At least ${props.rules.min_words?.article ?? 600} words in total.` },
  { part: 'Primary service', where: 'Sidebar', rule: 'Link the article to the service it supports. It adds a booking box and passes trust to the service page.' },
]);

const fields = computed(() => [
  { name: 'Focus keyword', rule: 'The one phrase this page should rank for, in lowercase. Use it in the title, the first 100 words and the meta title. One page per keyword: two pages with the same keyword compete with each other.', good: `${svcLower.value} singapore`, bad: 'best cheap fast service singapore 24 hours' },
  { name: 'Meta title', rule: `What Google shows as the blue link. Up to ${props.rules.title_max ?? 60} characters, keyword first, brand last. Every page needs its own.`, good: metaTitleExample.value, bad: 'Home | Services | Best | Singapore' },
  { name: 'Meta description', rule: `The grey text under the link. ${props.rules.desc_min ?? 70}–${props.rules.desc_max ?? 160} characters: the answer, one fact (price, time, warranty) and a reason to click.`, good: `${svc.value} in Singapore from ${priceText.value.replace(/^from /, '')}. HDB, condo and landed homes. Book on WhatsApp today.`, bad: 'We are the best company in Singapore. Click here to know more.' },
  { name: 'URL slug', rule: 'Short, lowercase, hyphens, the keyword. Set it once and don\'t change it. If you must, the old URL redirects automatically.', good: `${svcLower.value.replace(/[^a-z0-9]+/g, '-')}-cost-singapore`, bad: 'the-ultimate-complete-guide-to-everything-you-need-to-know-2023' },
  { name: 'Canonical URL', rule: 'Leave empty: the page then points to itself, which is right for almost every page. Only fill it when two pages say the same thing: put the main page\'s URL on the copy.' },
  { name: 'Noindex', rule: 'Hides the page from Google. Only for pages visitors need but Google should not show (thin or duplicate pages you keep). Never on a service page.' },
  { name: 'Featured image and alt text', rule: 'A real job photo, not stock. The alt text says what is in the photo in plain words.', good: `Technician doing ${svcLower.value} in an HDB flat`, bad: 'IMG_2034.jpg / image / best service' },
  { name: 'Author', rule: 'Set automatically to the writer. Each writer fills in a job title and a 2–3 sentence bio once in My account. Company accounts are not people: articles should be under a real name.', good: '"Senior technician, 12 years. Has worked on 3,000+ HDB and condo jobs."' },
]);

const serviceParts = computed(() => [
  { name: 'Service name', where: 'Service name (H1)', rule: 'The plain name people search for. No "Best" or "No. 1".', example: svc.value },
  { name: 'Direct answer', where: 'Direct answer', rule: 'Price range, how fast you come, warranty, where. 40–60 words.', example: answerExample.value },
  { name: 'Response time and warranty', where: 'Response time / Warranty', rule: 'Fill both with what you really offer. They show on the page and count for E-E-A-T.', example: [props.example.response_time, props.example.warranty].filter(Boolean).join(' · ') || 'Same day · 90 days workmanship' },
  { name: 'Page content', where: 'Page content', rule: 'Sections with H2 headings: what is included, signs you need it, how the job is done (steps), price guide, areas and property types served, why choose you (real numbers: years, jobs done, licences), aftercare. Link to 2–3 related articles.' },
  { name: 'Prices', where: 'Price list', rule: 'Every common job with a S$ range, the unit (per door, per hour) and the GST note. Review every 3–6 months; the "last reviewed" date shows.' },
  { name: 'FAQs', where: 'FAQs', rule: `At least ${props.rules.min_faqs?.service ?? 3} questions customers really ask on WhatsApp or the phone, with short answers.` },
  { name: 'Photos', where: 'Images', rule: 'Main photo plus before and after photos from real jobs, with alt text.' },
  { name: 'SEO panel', where: 'SEO panel', rule: 'Focus keyword, meta title and meta description as in "The SEO fields".' },
]);

const locationParts = computed(() => [
  `<b>Unique local intro</b>: 2–3 sentences only true for ${loc.value}: the estates, common property types, typical problems there.`,
  '<b>Local content</b>: jobs you did in this area, how fast you get there, building or town council rules that matter.',
  '<b>Property types you actually serve here</b>: tick only the real ones.',
  '<b>Nearby areas</b> and <b>Services offered in this area</b>: link the area to the services you really do there.',
  '<b>Real job photo from this area</b>: one photo from a real job nearby, with alt text naming the area.',
  'Never copy one area\'s text into another and change only the name.',
]);

const otherPages = [
  { name: 'Homepage', href: '/admin/hero', when: 'Every 3 months', items: ['The hero heading says what you do and where (e.g. service + Singapore).', 'Counters show real numbers only (jobs done, years, reviews).', 'Keep the hero image light; it is the first thing that loads.'] },
  { name: 'About page', href: '/admin/about', when: 'Every 6 months', items: ['Real team, real names, years in business, licences and insurance.', 'Photos of the team and vans, not stock photos.', 'This page is the main trust (E-E-A-T) page for the whole site.'] },
  { name: 'Contact, identity and hours', href: '/admin/settings/contact', when: 'When anything changes', items: ['Business name, address and phone exactly the same as on Google Business Profile.', 'Opening hours correct, including public holidays.', 'UEN shown; social links working.'] },
  { name: 'Page SEO', href: '/admin/page-seo', when: 'Once, then yearly', items: ['Meta title and description for every fixed page: Home, About, Contact, Pricing, Projects, Reviews, Articles list.', 'Each one different; same length rules as other pages.'] },
  { name: 'Categories', href: '/admin/service-categories', when: 'When adding services', items: [`An intro of at least ${props.rules.min_words?.category ?? 120} words saying what the group covers.`, 'Meta title and description filled in.'] },
  { name: 'Price list', href: '/admin/pricing', when: 'Every 3–6 months', items: ['Real S$ ranges for every service, with unit and GST note.', 'Answer engines quote prices more than anything else.'] },
  { name: 'FAQs', href: '/admin/faqs', when: 'Monthly', items: ['Add the questions customers ask on WhatsApp and the phone.', 'Short, complete answers. No duplicate questions on different pages.'] },
  { name: 'Projects', href: '/admin/projects', when: 'After good jobs', items: ['Real job: area, property type, the problem, what you did, how long it took.', 'Before and after photos with alt text.', 'Link the project to its service.'] },
  { name: 'Reviews', href: '/admin/reviews', when: 'Weekly', items: ['Only real reviews; never edit a customer\'s words.', 'Reply to every Google review, good or bad.'] },
  { name: 'Media library and page banners', href: '/admin/media', when: 'When uploading', items: ['Descriptive file names (sliding-door-roller-hdb.jpg, not IMG_2034.jpg).', 'Alt text on every image. Real photos over stock.'] },
  { name: 'SEO Health', href: '/admin/seo/health', when: 'Weekly', items: ['Fix red errors first (missing titles, thin pages, duplicates), then warnings.'] },
  { name: 'Redirects and 404s', href: '/admin/redirects', when: 'Weekly', items: ['Every broken URL in the 404 list gets a redirect to the closest live page.', 'Before deleting a page, redirect it.'] },
  { name: 'Search and visitors', href: '/admin/insights', when: 'Monthly', items: ['Look at the questions people search for. Each good question can become an article.', 'Update the articles that bring the most visits first.'] },
  { name: 'Schema and robots', href: '/admin/seo/settings', when: 'Once', items: ['Business type and details correct; leave robots allowing search engines.'] },
];

const never = [
  'Copy text from a competitor or another website, including AI rewrites of it.',
  'Name a competitor as if it were us, or leave another company\'s name in copied text.',
  'Invent prices, numbers of jobs, years, licences or reviews.',
  'Repeat the keyword again and again ("door repair singapore door repair cheap door repair").',
  'Publish two pages for the same keyword or with the same title.',
  'Start with "Welcome to…", "Are you looking for…" or "In today\'s fast-paced world…".',
  'Use stock photos as if they were your own jobs.',
  'Change the URL slug of a page that is already live without a reason.',
  'Turn on Noindex for a service page or set a canonical to another site.',
];

const publishList = computed(() => [
  'The title answers one question, 30–60 characters, keyword first.',
  'The direct answer is filled in: 40–60 words with a real S$ range.',
  'Focus keyword, meta title and meta description are filled in.',
  `At least ${props.rules.min_words?.article ?? 600} words, with H2 sections and at least one question heading.`,
  'A price table or list, and at least one real job detail from your team.',
  'At least 3 FAQs.',
  'Linked to its primary service, plus at least 2 links to your own pages.',
  'An official source linked where a rule is mentioned.',
  'Featured image set; every image has alt text.',
  'All four scores in the sidebar are 80 or more, and the SEO checklist has no errors.',
]);
</script>

<style scoped>
.guide h2 { font-size: 1.25rem; font-weight: 700; margin-bottom: .5rem; }
.guide p, .guide li { font-size: 15px; line-height: 1.65; }
.guide-toc { display: block; padding: .5rem .75rem; border-radius: .5rem; font-size: 14px; font-weight: 500; white-space: nowrap; color: var(--a-text-2); transition: background-color 120ms ease, color 120ms ease; }
.guide-toc:hover { background: var(--a-panel-3); color: var(--a-text); }
.guide-toc.on { background: var(--a-accent-soft); color: var(--a-accent); font-weight: 600; box-shadow: inset 3px 0 0 var(--a-accent); }
@media (min-width: 1280px) { .guide-toc { white-space: normal; } }
.guide-outline { counter-reset: s; display: flex; flex-direction: column; gap: .75rem; }
.guide-outline li { counter-increment: s; position: relative; padding: .875rem 1rem .875rem 3.25rem; border: 1px solid var(--a-border, #e5e7eb); border-radius: .75rem; }
.guide-outline li::before { content: counter(s); position: absolute; left: 1rem; top: .875rem; width: 1.6rem; height: 1.6rem; border-radius: 999px; display: grid; place-items: center; font-size: 13px; font-weight: 700; background: var(--a-accent-soft, #e8f0fe); color: var(--a-accent, #1d4ed8); }
.example { margin-top: .5rem; padding: .5rem .75rem; border-radius: .5rem; background: var(--a-surface-2, rgba(0, 0, 0, .04)); }
.example .lbl, .lbl { display: block; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; opacity: .7; margin-bottom: .125rem; }
.bad-ex { box-shadow: inset 3px 0 0 #dc2626; }
.good { box-shadow: inset 4px 0 0 #16a34a; }
.bad { box-shadow: inset 4px 0 0 #dc2626; }
.ph { padding: 0 .25rem; border-radius: .25rem; background: #fef3c7; color: #92400e; font-weight: 600; }
.ticks, .crosses, .checklist { display: flex; flex-direction: column; gap: .4rem; }
.ticks li, .crosses li { position: relative; padding-left: 1.5rem; }
.ticks li::before { content: '✓'; position: absolute; left: 0; color: #16a34a; font-weight: 700; }
.crosses li::before { content: '✕'; position: absolute; left: 0; color: #dc2626; font-weight: 700; }
.ticks.small li { font-size: 14px; }
.share { height: 6px; border-radius: 999px; background: var(--a-border, #e5e7eb); overflow: hidden; }
.share i { display: block; height: 100%; background: #dc2626; border-radius: 999px; }
.warn { box-shadow: inset 4px 0 0 #d97706; }
.tone-good { background: rgba(22, 163, 74, .1); color: #15803d; }
.tone-mid { background: rgba(217, 119, 6, .12); color: #b45309; }
.tone-bad { background: rgba(220, 38, 38, .1); color: #b91c1c; }
</style>
<style>
/* Below the slim sticky page header (not scoped: the sticky rule lives in admin.css). */
@media (min-width: 1280px) { .admin-ui .guide-nav { --a-sticky-top: 7.5rem; --a-sticky-room: 9rem; } }
</style>
