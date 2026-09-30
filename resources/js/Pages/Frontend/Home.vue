<template>
  <FrontendLayout>
    <!-- ============ Hero: message left, quick quote card right ============ -->
    <section class="s-bg-alt">
      <div class="container-app pt-12 pb-28 lg:pt-20 lg:pb-36 grid lg:grid-cols-[1.1fr_0.9fr] gap-14 items-center">
        <div>
          <p v-if="hero.eyebrow" class="inline-flex items-center gap-2 rounded-full s-surface border s-border pl-3 pr-4 py-1.5 text-[14px] font-medium s-muted">
            <span class="w-2 h-2 rounded-full" style="background: var(--s-btn)"></span>{{ hero.eyebrow }}
          </p>
          <h1 class="h-display mt-6">{{ hero.title || `Plumbing services in Singapore` }}</h1>
          <p v-if="hero.subtitle" class="mt-6 text-[1.18rem] leading-relaxed s-muted max-w-xl">{{ hero.subtitle }}</p>
          <div class="mt-9 flex flex-col sm:flex-row gap-3">
            <WhatsAppButton size="lg" class="shadow-[0_10px_24px_-10px_rgba(23,89,196,.7)]">Get a free quote</WhatsAppButton>
            <a v-if="company.tel" :href="'tel:' + tel" class="btn btn-secondary btn-lg">Call {{ company.phone }}</a>
          </div>
          <ul v-if="hero.badges?.length" class="mt-10 grid sm:grid-cols-3 gap-4 text-[15px] s-text">
            <li v-for="(b, i) in hero.badges.slice(0, 3)" :key="b" class="flex items-center gap-2.5">
              <span class="w-8 h-8 shrink-0 rounded-full flex items-center justify-center s-accent bg-[var(--s-accent-soft)]">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" :d="badgeIcons[i]" /></svg>
              </span>
              {{ b }}
            </li>
          </ul>
        </div>

        <!-- Quick quote card -->
        <div class="relative">
          <div class="absolute -inset-4 lg:-inset-6 rounded-[36px]" style="background: linear-gradient(135deg, var(--s-accent-soft), transparent 70%)" aria-hidden="true"></div>
          <form class="relative s-surface rounded-[28px] p-6 sm:p-8" style="box-shadow: var(--s-shadow-lg)" @submit.prevent="sendQuick">
            <div class="flex items-center justify-between gap-3">
              <h2 class="text-[1.35rem] font-semibold s-heading" style="font-family: var(--font-display)">Get a price today</h2>
              <span class="text-[13px] font-semibold text-[var(--s-good-text)] bg-[var(--s-good-bg)] rounded-full px-3 py-1 whitespace-nowrap">Free · No obligation</span>
            </div>
            <fieldset class="mt-5">
              <legend class="text-[14px] font-semibold s-muted">What needs fixing?</legend>
              <div class="mt-3 flex flex-wrap gap-2">
                <button v-for="p in problems" :key="p" type="button" :aria-pressed="quick.problem === p" @click="quick.problem = p"
                  :class="['rounded-full border px-4 py-2 text-[14.5px] font-medium transition-colors', quick.problem === p ? 'text-white border-transparent' : 's-border s-heading hover:border-[var(--s-border-2)]']"
                  :style="quick.problem === p ? 'background: var(--s-btn)' : ''">{{ p }}</button>
              </div>
            </fieldset>
            <div class="mt-6 grid grid-cols-2 gap-3">
              <div>
                <label for="quick-name" class="label">Your name</label>
                <input id="quick-name" v-model="quick.name" type="text" autocomplete="name" class="input" placeholder="David Tan" />
              </div>
              <div>
                <label for="quick-area" class="label">Area</label>
                <input id="quick-area" v-model="quick.area" type="text" autocomplete="address-level2" class="input" placeholder="e.g. Tampines" />
              </div>
            </div>
            <p class="mt-5 flex items-center gap-3 rounded-xl s-bg-alt px-4 py-3.5 text-[15px] s-text">
              <svg class="w-5 h-5 shrink-0 s-accent" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 2m6-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
              <span>Same-day visits, <strong class="s-heading">price agreed before work</strong></span>
            </p>
            <button type="submit" class="btn btn-whatsapp btn-lg w-full mt-6">
              <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M12.04 2.6C6.8 2.6 2.56 6.83 2.56 12.04c0 1.8.5 3.5 1.45 5.03l-.96 3.49 3.58-.94a9.45 9.45 0 004.82 1.32h.01c5.25 0 9.5-4.18 9.5-9.41a9.42 9.42 0 00-2.78-6.71 9.4 9.4 0 00-6.72-2.78z" /></svg>
              Send on WhatsApp
            </button>
            <p class="mt-3 text-center text-[13.5px] s-subtle">
              WhatsApp opens with your message ready to send.
              <a v-if="hero.show_quote_form" href="#quote" class="link">Prefer a form?</a>
            </p>
          </form>
        </div>
      </div>
    </section>

    <!-- ============ Numbers ============ -->
    <section v-if="stats.length" class="relative z-10 -mt-14">
      <div class="container-app">
        <dl class="s-surface rounded-[24px] border s-border grid grid-cols-2 lg:grid-cols-4" style="box-shadow: var(--s-shadow)">
          <div v-for="(c, i) in stats.slice(0, 4)" :key="c.id" :class="['p-6 lg:p-7 text-center flex flex-col-reverse gap-1', i % 2 ? 'border-l s-border' : '', i > 1 ? 'border-t lg:border-t-0 s-border' : '', i === 2 ? 'lg:border-l' : '']">
            <dt class="text-[15px] s-subtle">{{ c.label }}</dt>
            <dd class="text-[2rem] lg:text-[2.2rem] font-bold s-heading leading-none tabular-nums" style="font-family: var(--font-display)">{{ c.value }}</dd>
          </div>
        </dl>
      </div>
    </section>

    <!-- Everything below the first screen is built one frame later (splits the start-up work in two). -->
    <template v-if="restReady">
    <!-- ============ Services ============ -->
    <section v-if="services.length" class="py-24 lg:py-32 s-bg">
      <div class="container-app">
        <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-6">
          <div class="max-w-2xl">
            <p class="eyebrow">Our services</p>
            <h2 class="h-section mt-3">{{ homeStatic?.h_s_title || 'Every plumbing job, one reliable team' }}</h2>
          </div>
          <Link href="/services" class="link shrink-0 text-[15px]">View all {{ allServicesCount }} services →</Link>
        </div>
        <ul class="mt-12 grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
          <li v-for="s in services.slice(0, 5)" :key="s.id"><ServiceCard :service="s" /></li>
          <li>
            <a :href="wa('a plumbing problem (photo attached)')" target="_blank" rel="noopener" class="lift h-full rounded-[22px] p-7 flex flex-col text-white" style="background: var(--s-dark)">
              <span class="w-14 h-14 rounded-2xl bg-white/10 flex items-center justify-center">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.9a2 2 0 001.7-.9l.8-1.2A2 2 0 0110.1 4h3.8a2 2 0 011.7.9l.8 1.2a2 2 0 001.7.9H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9zM15 13a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
              </span>
              <h3 class="mt-6 text-[1.25rem] font-semibold" style="font-family: var(--font-display)">Not sure what's wrong?</h3>
              <p class="mt-2 text-[15.5px] leading-relaxed text-white/75">Send us a photo on WhatsApp. A plumber will tell you what it is and what it costs.</p>
              <span class="mt-auto pt-6 font-semibold text-[#9fd0ff]">Send a photo →</span>
            </a>
          </li>
        </ul>
      </div>
    </section>

    <!-- ============ Recent work ============ -->
    <section v-if="projects.length" class="py-24 lg:py-32 s-bg-alt">
      <div class="container-app">
        <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-6">
          <div class="max-w-2xl">
            <p class="eyebrow">Recent work</p>
            <h2 class="h-section mt-3">{{ homeStatic?.h_p_title || 'Recent plumbing jobs' }}</h2>
          </div>
          <Link href="/projects" class="link shrink-0 text-[15px]">See all projects →</Link>
        </div>
        <div class="mt-12 grid md:grid-cols-3 gap-6">
          <figure v-for="p in projects.slice(0, 6)" :key="p.id" class="s-surface rounded-[22px] overflow-hidden border s-border">
            <div class="aspect-[4/3] overflow-hidden s-surface-2">
              <img :src="p.image ? img(p.image, 640) : '/logo.png'" :srcset="srcset(p.image, 1024)" sizes="(min-width: 768px) 380px, 100vw" :alt="p.name" loading="lazy" decoding="async" width="640" height="480" class="img-cover" />
            </div>
            <figcaption class="p-6">
              <span v-if="p.service" class="text-[13px] font-semibold s-accent">{{ p.service.name }}</span>
              <span v-else-if="p.completed_on" class="text-[13px] font-semibold s-accent">{{ month(p.completed_on) }}</span>
              <p class="mt-1.5 text-[1.1rem] font-semibold s-heading" style="font-family: var(--font-display)">{{ p.name }}</p>
            </figcaption>
          </figure>
        </div>
      </div>
    </section>

    <!-- ============ Quote form (switch in Admin → Homepage) ============ -->
    <section v-if="hero.show_quote_form" id="quote" class="py-24 lg:py-32 s-bg scroll-mt-28">
      <div class="container-app grid lg:grid-cols-[0.85fr_1.15fr] gap-12 lg:gap-20 items-start">
        <div>
          <p class="eyebrow">How it works</p>
          <h2 class="h-section mt-3">{{ texts.steps_title || 'From message to finished job' }}</h2>
          <ol class="mt-10 space-y-8">
            <li v-for="(step, i) in steps" :key="step.title" class="flex gap-4">
              <span class="w-10 h-10 shrink-0 rounded-full flex items-center justify-center text-white font-semibold" style="background: var(--s-btn); font-family: var(--font-display)">{{ i + 1 }}</span>
              <div>
                <h3 class="h-card">{{ step.title }}</h3>
                <p class="mt-1.5 text-[15.5px] s-muted leading-relaxed">{{ step.text }}</p>
              </div>
            </li>
          </ol>
        </div>
        <div class="s-surface rounded-[28px] border s-border p-6 sm:p-10" style="box-shadow: var(--s-shadow)">
          <h2 class="h-card !text-[1.35rem]">Send the details</h2>
          <p class="mt-1.5 text-[15px] s-muted">{{ texts.quote_intro || 'Tell us the problem. A plumber replies with a price.' }}</p>
          <div class="mt-7">
            <QuoteForm :services="serviceOptions" subject="Quote request · Homepage" id-prefix="home" />
          </div>
        </div>
      </div>
    </section>

    <!-- ============ Guides ============ -->
    <section v-if="latestBlogs.length" :class="['py-24 lg:py-32', hero.show_quote_form ? 's-bg-alt' : 's-bg']">
      <div class="container-app">
        <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-6">
          <div class="max-w-2xl">
            <p class="eyebrow">Guides</p>
            <h2 class="h-section mt-3">{{ homeStatic?.h_b_title || 'Know the problem, and the price' }}</h2>
          </div>
          <Link href="/blogs" class="link shrink-0 text-[15px]">All articles →</Link>
        </div>
        <div class="mt-12 grid lg:grid-cols-[1.35fr_1fr] gap-6">
          <Link :href="`/blogs/${lead.slug}`" class="lift group rounded-[22px] overflow-hidden border s-border s-surface flex flex-col">
            <div class="aspect-[16/9] overflow-hidden s-surface-2">
              <img :src="lead.image ? img(lead.image, 800) : '/logo.png'" :srcset="srcset(lead.image, 1280)" sizes="(min-width: 1024px) 640px, 100vw" alt="" loading="lazy" decoding="async" width="800" height="450" class="img-cover" />
            </div>
            <div class="p-7">
              <p class="text-[14px] s-subtle"><time :datetime="lead.published_at">{{ date(lead.published_at) }}</time></p>
              <h3 class="mt-2 text-[1.45rem] font-semibold leading-snug s-heading group-hover:text-[var(--s-accent-text)] transition-colors" style="font-family: var(--font-display)">{{ cleanTitle(lead.name) }}</h3>
              <p v-if="lead.excerpt" class="mt-3 text-[15.5px] s-muted leading-relaxed line-clamp-2">{{ lead.excerpt }}</p>
            </div>
          </Link>
          <ul class="flex flex-col gap-6">
            <li v-for="b in latestBlogs.slice(1, 4)" :key="b.id" class="flex-1">
              <Link :href="`/blogs/${b.slug}`" class="lift group h-full rounded-[22px] overflow-hidden border s-border s-surface flex">
                <span class="w-36 sm:w-44 shrink-0 s-surface-2 overflow-hidden">
                  <img :src="b.image ? img(b.image, 320) : '/logo.png'" alt="" loading="lazy" decoding="async" class="img-cover" />
                </span>
                <span class="p-5 sm:p-6 min-w-0 flex flex-col justify-center">
                  <time class="text-[14px] s-subtle" :datetime="b.published_at">{{ date(b.published_at) }}</time>
                  <span class="mt-1.5 text-[1.08rem] font-semibold leading-snug s-heading line-clamp-3 group-hover:text-[var(--s-accent-text)] transition-colors" style="font-family: var(--font-display)">{{ cleanTitle(b.name) }}</span>
                </span>
              </Link>
            </li>
          </ul>
        </div>
      </div>
    </section>

    <CtaBand title="Water where it shouldn't be?" text="Send us a photo on WhatsApp. A plumber replies with a clear price, usually the same day." />
    </template>
  </FrontendLayout>
</template>

<script setup>
import { computed, nextTick, onMounted, reactive, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import FrontendLayout from '@/Layouts/FrontendLayout.vue';
import QuoteForm from '@/Components/Site/QuoteForm.vue';
import CtaBand from '@/Components/Site/CtaBand.vue';
import ServiceCard from '@/Components/Site/ServiceCard.vue';
import WhatsAppButton from '@/Components/Site/WhatsAppButton.vue';
import { useContact } from '@/Composables/useContact';
import { img, srcset } from '@/utils/img';
import { statsFrom } from '@/utils/stats';
import { cleanTitle } from '@/utils/cleanTitle';

const restReady = ref(false);
onMounted(() => {
  requestAnimationFrame(() => setTimeout(async () => {
    restReady.value = true;
    // A link such as /#quote points into the part that was just built.
    if (location.hash.length > 1) {
      await nextTick();
      document.getElementById(decodeURIComponent(location.hash.slice(1)))?.scrollIntoView();
    }
  }));
});

const props = defineProps({
  hero: { type: Object, default: () => ({}) },
  homeStatic: Object,
  services: { type: Array, default: () => [] },
  allServicesCount: Number,
  serviceOptions: { type: Array, default: () => [] },
  categories: { type: Array, default: () => [] },
  projects: { type: Array, default: () => [] },
  counters: { type: Array, default: () => [] },
  reviews: { type: Object, default: () => ({ items: [], google: null }) },
  latestBlogs: { type: Array, default: () => [] },
  partners: { type: Array, default: () => [] },
});

const stats = computed(() => statsFrom(props.counters));
const page = usePage();
const { company, tel, wa } = useContact();
const texts = computed(() => page.props.company?.texts || {});
// Admin → Website text → Homepage: How it works (a step without a title is hidden).
const steps = computed(() => [1, 2, 3].map((n) => ({ title: texts.value[`step${n}_title`], text: texts.value[`step${n}_text`] })).filter((s) => s.title));
const lead = computed(() => props.latestBlogs[0] || {});

// Hero quick-quote card: builds a WhatsApp message from the chosen problem, name and area.
const problems = ['Leaking pipe', 'Toilet', 'Tap & mixer', 'Water heater', 'Blocked drain', 'Something else'];
const quick = reactive({ problem: problems[0], name: '', area: '' });
function sendQuick() {
  const topic = quick.problem === 'Something else' ? 'a plumbing problem' : quick.problem.toLowerCase();
  let url = wa(topic);
  const extra = [quick.name && `Name: ${quick.name}`, quick.area && `Area: ${quick.area}`].filter(Boolean).join('. ');
  if (extra && url.includes('text=')) url += encodeURIComponent(` ${extra}.`);
  window.open(url, url.startsWith('http') ? '_blank' : '_self', 'noopener');
}

const badgeIcons = [
  'M13 10V3L4 14h7v7l9-11h-7z',
  'M9 12l2 2 4-4M7.8 4.7a3.4 3.4 0 001.9-.8 3.4 3.4 0 014.4 0 3.4 3.4 0 001.9.8 3.4 3.4 0 013.1 3.1c.1.7.4 1.4.8 1.9a3.4 3.4 0 010 4.4 3.4 3.4 0 00-.8 1.9 3.4 3.4 0 01-3.1 3.1 3.4 3.4 0 00-1.9.8 3.4 3.4 0 01-4.4 0 3.4 3.4 0 00-1.9-.8 3.4 3.4 0 01-3.1-3.1 3.4 3.4 0 00-.8-1.9 3.4 3.4 0 010-4.4 3.4 3.4 0 00.8-1.9 3.4 3.4 0 013.1-3.1z',
  'M9 12l2 2 4-4m5.6-4A11.9 11.9 0 0112 2.9 11.9 11.9 0 013.4 6 12 12 0 003 9c0 5.6 3.8 10.3 9 11.6 5.2-1.3 9-6 9-11.6 0-1-.1-2-.4-3z',
];


const date = (d) => (d ? new Date(d).toLocaleDateString('en-SG', { day: 'numeric', month: 'short', year: 'numeric' }) : '');
const month = (d) => new Date(d).toLocaleDateString('en-SG', { month: 'short', year: 'numeric' });
</script>
