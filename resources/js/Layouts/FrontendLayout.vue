<template>
  <Head v-if="meta" :title="meta.title">
    <meta head-key="description" name="description" :content="meta.description" />
    <meta head-key="robots" name="robots" :content="meta.robots" />
    <link head-key="canonical" rel="canonical" :href="meta.canonical" />
    <meta head-key="og:type" property="og:type" :content="meta.type" />
    <meta head-key="og:site_name" property="og:site_name" :content="meta.site_name" />
    <meta head-key="og:title" property="og:title" :content="meta.title" />
    <meta head-key="og:description" property="og:description" :content="meta.description" />
    <meta head-key="og:url" property="og:url" :content="meta.canonical" />
    <meta head-key="og:image" property="og:image" :content="meta.image" />
    <meta head-key="twitter:card" name="twitter:card" content="summary_large_image" />
  </Head>

  <div class="site min-h-screen flex flex-col pb-[5.5rem] lg:pb-0">
    <div ref="topMarker" aria-hidden="true" class="absolute top-0 left-0 w-px h-2 pointer-events-none"></div>
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-[100] btn btn-primary">Skip to content</a>

    <!-- Header -->
    <header :class="['sticky top-0 z-50 s-bg border-b transition-shadow', scrolled ? 's-border shadow-[var(--s-shadow-sm)]' : 'border-transparent']">
      <div class="container-app h-[4.5rem] lg:h-20 flex items-center justify-between gap-2 sm:gap-6">
        <!-- The brand may shrink so the buttons always fit, even on 320 px phones. -->
        <Link href="/" class="flex items-center gap-2.5 sm:gap-3 min-w-0">
          <img :src="img(company.logo, 160)" alt="" width="48" height="48" class="w-10 h-10 sm:w-12 sm:h-12 shrink-0 object-contain dark:bg-white dark:rounded-full dark:p-0.5" />
          <span class="leading-tight min-w-0">
            <span class="block truncate text-[15px] min-[360px]:text-[17px] font-bold tracking-[-0.02em] s-heading" style="font-family: var(--font-display)">{{ company.name }}</span>
            <span class="block truncate text-[12px] min-[360px]:text-[13px] s-subtle">Plumbing Service · Singapore</span>
          </span>
        </Link>

        <nav class="hidden xl:flex items-center gap-7" aria-label="Main">
          <Link v-for="item in primaryBefore" :key="item.href" :href="item.href" :class="navClass(item.href)">{{ item.label }}</Link>

          <div class="relative" @mouseenter="openMenu('services')" @mouseleave="closeMenuSoon">
            <button type="button" :class="[navClass('/services'), 'inline-flex items-center gap-1']" :aria-expanded="open === 'services'" aria-haspopup="true" @click="toggle('services')">
              Services
              <svg :class="['w-3.5 h-3.5 opacity-60 transition-transform', open === 'services' && 'rotate-180']" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
            </button>
          </div>

          <div v-if="showAreasMenu" class="relative" @mouseenter="openMenu('locations')" @mouseleave="closeMenuSoon">
            <button type="button" :class="[navClass('/locations'), 'inline-flex items-center gap-1']" :aria-expanded="open === 'locations'" aria-haspopup="true" @click="toggle('locations')">
              Locations
              <svg :class="['w-3.5 h-3.5 opacity-60 transition-transform', open === 'locations' && 'rotate-180']" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
            </button>
            <transition enter-active-class="transition duration-150" enter-from-class="opacity-0 translate-y-1" leave-active-class="transition duration-100" leave-to-class="opacity-0">
              <div v-if="open === 'locations'" class="absolute left-1/2 -translate-x-1/2 top-full pt-3 w-[26rem]" @mouseenter="openMenu('locations')" @mouseleave="closeMenuSoon">
                <div class="card overflow-hidden" style="box-shadow: var(--s-shadow-lg)">
                  <div class="p-3 grid grid-cols-2 gap-0.5">
                    <Link v-for="l in topLocations" :key="l.href" :href="l.href" class="px-3 py-2.5 rounded-lg text-[15px] s-text hover:bg-[var(--s-surface-2)]" @click="open = null">{{ l.name }}</Link>
                  </div>
                  <Link href="/locations" class="flex items-center justify-between px-5 py-3 border-t s-border text-[14px] font-semibold s-heading hover:text-[var(--s-accent-text)]" @click="open = null">
                    All areas we serve <span aria-hidden="true">→</span>
                  </Link>
                </div>
              </div>
            </transition>
          </div>

          <Link v-for="item in primaryAfter" :key="item.href" :href="item.href" :class="navClass(item.href)">{{ item.label }}</Link>
        </nav>

        <div class="flex items-center gap-1.5 min-[360px]:gap-2 shrink-0">
          <button type="button" @click="searchOpen = true" class="w-10 h-10 rounded-full flex items-center justify-center s-muted hover:bg-[var(--s-surface-2)] hover:text-[var(--s-heading)]" aria-label="Search the website" title="Search (Ctrl K)">
            <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M21 21l-5.2-5.2M17 10.5a6.5 6.5 0 11-13 0 6.5 6.5 0 0113 0z" /></svg>
          </button>
          <a v-if="company.tel" :href="'tel:' + tel" class="hidden xl:inline-flex whitespace-nowrap px-2 text-[15px] font-semibold s-heading hover:text-[var(--s-accent-text)]">{{ company.phone }}</a>
          <WhatsAppButton size="sm" class="hidden sm:inline-flex !px-5 !py-2.5 !text-[15px]">Get a free quote</WhatsAppButton>
          <button type="button" @click="mobileOpen = !mobileOpen" class="xl:hidden w-10 h-10 rounded-full flex items-center justify-center s-heading hover:bg-[var(--s-surface-2)]" :aria-expanded="mobileOpen" aria-label="Menu">
            <svg v-if="!mobileOpen" class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16" /></svg>
            <svg v-else class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6L6 18" /></svg>
          </button>
        </div>
      </div>

      <!-- Services mega menu (full width under the header) -->
      <transition enter-active-class="transition duration-150" enter-from-class="opacity-0 -translate-y-1" leave-active-class="transition duration-100" leave-to-class="opacity-0">
        <div v-if="open === 'services'" class="hidden xl:block absolute inset-x-0 top-full border-t s-border" style="background: var(--s-bg); box-shadow: var(--s-shadow-lg)" @mouseenter="openMenu('services')" @mouseleave="closeMenuSoon">
          <div class="container-app py-7 grid grid-cols-[17rem_1fr] gap-8">
            <!-- Intro panel -->
            <div class="rounded-[18px] text-white p-6 relative overflow-hidden flex flex-col" style="background: var(--s-dark)">
              <div class="relative flex-1">
                <p class="text-[12.5px] font-bold uppercase tracking-[0.12em] text-[#bfe3ff]">Our services</p>
                <p class="mt-2 text-[17px] font-bold leading-snug" style="font-family: var(--font-display)">Licensed plumbing across Singapore</p>
                <p class="mt-2 text-[14px] text-white/75 leading-relaxed">Every service page shows real prices and what is included.</p>
              </div>
              <div class="relative mt-5 space-y-2">
                <Link href="/services" class="btn btn-sm w-full bg-white !text-[#0f3f8a] hover:bg-[#e9f2fe]" @click="open = null">All {{ serviceCount }} services</Link>
                <Link href="/pricing" class="btn btn-light btn-sm w-full" @click="open = null">Price list</Link>
              </div>
            </div>

            <!-- Services -->
            <div class="min-w-0">
              <div :class="['grid gap-x-8 gap-y-6', serviceCategories.length > 1 ? 'grid-cols-3' : 'grid-cols-1']">
                <div v-for="group in serviceCategories" :key="group.name" class="min-w-0">
                  <Link :href="group.href" class="flex items-center justify-between gap-2 pb-2 mb-1 border-b s-border text-[13px] font-bold uppercase tracking-[0.1em] s-subtle hover:text-[var(--s-accent-text)]" @click="open = null">
                    {{ group.name }} <span aria-hidden="true">→</span>
                  </Link>
                  <ul :class="serviceCategories.length > 1 ? '' : 'grid grid-cols-3 gap-x-6'">
                    <li v-for="s in group.services" :key="s.href">
                      <Link :href="s.href" class="group flex items-center gap-2.5 py-2 text-[15px] s-text hover:text-[var(--s-accent-text)] transition-colors" @click="open = null">
                        <span class="w-1.5 h-1.5 rounded-full bg-[var(--s-border-2)] group-hover:bg-[var(--s-accent)] transition-colors shrink-0"></span>
                        <span class="truncate">{{ s.name }}</span>
                      </Link>
                    </li>
                  </ul>
                </div>
              </div>
              <div v-if="company.tel" class="mt-6 pt-5 border-t s-border flex items-center justify-between gap-4 text-[15px]">
                <p class="s-muted">Not sure which service you need? We will tell you on the phone.</p>
                <a :href="'tel:' + tel" class="inline-flex items-center gap-2 font-bold s-heading hover:text-[var(--s-accent-text)]">
                  <svg class="w-4 h-4 s-accent" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" :d="icons.phone" /></svg>
                  {{ company.phone }}
                </a>
              </div>
            </div>
          </div>
        </div>
      </transition>

      <!-- Mobile menu -->
      <div v-if="mobileOpen" class="xl:hidden border-t s-border max-h-[calc(100dvh-4.5rem)] overflow-y-auto" style="background: var(--s-bg)">
        <nav class="container-app py-4" aria-label="Mobile">
          <div class="space-y-1">
            <Link v-for="item in primaryBefore" :key="item.href" :href="item.href" :class="mobileClass(item.href)" @click="mobileOpen = false">{{ item.label }}</Link>

            <div class="rounded-xl border s-border overflow-hidden">
              <button type="button" @click="mobileSection = mobileSection === 'services' ? null : 'services'" class="w-full flex items-center justify-between px-4 py-3 text-[16px] font-semibold s-heading" :aria-expanded="mobileSection === 'services'">
                Services
                <svg :class="['w-4 h-4 s-subtle transition-transform', mobileSection === 'services' && 'rotate-180']" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
              </button>
              <div v-if="mobileSection === 'services'" class="border-t s-border s-bg-alt">
                <!-- Long lists scroll inside a short box, so the menu stays compact -->
                <div v-if="serviceCount > 8" class="px-3 pt-3">
                  <input v-model="mobileServiceQuery" type="search" class="input !py-2 !text-[15px]" :placeholder="`Find a service (${serviceCount})`" aria-label="Find a service" />
                </div>
                <div class="relative">
                  <div class="max-h-[17rem] overflow-y-auto overscroll-contain pb-2">
                    <template v-for="group in mobileServiceGroups" :key="group.name">
                      <p v-if="mobileServiceGroups.length > 1" class="sticky top-0 z-[1] px-4 pt-3 pb-1 text-[12.5px] font-bold uppercase tracking-[0.1em] s-subtle s-bg-alt">{{ group.name }}</p>
                      <Link v-for="s in group.services" :key="s.href" :href="s.href" class="block px-4 py-2.5 text-[15.5px] s-text border-b s-border last:border-b-0 active:bg-[var(--s-surface-2)]" @click="mobileOpen = false">{{ s.name }}</Link>
                    </template>
                    <p v-if="!mobileServiceGroups.length" class="px-4 py-4 text-[15px] s-muted">No service matches “{{ mobileServiceQuery }}”.</p>
                  </div>
                  <div v-if="serviceCount > 6" class="pointer-events-none absolute inset-x-0 bottom-0 h-8" style="background: linear-gradient(to bottom, transparent, var(--s-bg-alt))"></div>
                </div>
                <div class="p-3 grid grid-cols-2 gap-2 border-t s-border">
                  <Link href="/services" class="btn btn-secondary btn-sm" @click="mobileOpen = false">All services</Link>
                  <Link href="/pricing" class="btn btn-secondary btn-sm" @click="mobileOpen = false">Price list</Link>
                </div>
              </div>
            </div>

            <div v-if="showAreasMenu" class="rounded-xl border s-border overflow-hidden">
              <button type="button" @click="mobileSection = mobileSection === 'locations' ? null : 'locations'" class="w-full flex items-center justify-between px-4 py-3 text-[16px] font-semibold s-heading" :aria-expanded="mobileSection === 'locations'">
                Locations
                <svg :class="['w-4 h-4 s-subtle transition-transform', mobileSection === 'locations' && 'rotate-180']" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
              </button>
              <div v-if="mobileSection === 'locations'" class="border-t s-border s-bg-alt">
                <div v-if="topLocations.length > 8" class="px-3 pt-3">
                  <input v-model="mobileAreaQuery" type="search" class="input !py-2 !text-[15px]" :placeholder="`Find your area (${topLocations.length})`" aria-label="Find your area" />
                </div>
                <div class="relative">
                  <div class="max-h-[17rem] overflow-y-auto overscroll-contain pb-2">
                    <Link v-for="l in mobileAreas" :key="l.href" :href="l.href" class="block px-4 py-2.5 text-[15.5px] s-text border-b s-border last:border-b-0 active:bg-[var(--s-surface-2)]" @click="mobileOpen = false">{{ l.name }}</Link>
                    <p v-if="!mobileAreas.length" class="px-4 py-4 text-[15px] s-muted">No area matches “{{ mobileAreaQuery }}”.</p>
                  </div>
                  <div v-if="topLocations.length > 6" class="pointer-events-none absolute inset-x-0 bottom-0 h-8" style="background: linear-gradient(to bottom, transparent, var(--s-bg-alt))"></div>
                </div>
                <div class="p-3 border-t s-border">
                  <Link href="/locations" class="btn btn-secondary btn-sm w-full" @click="mobileOpen = false">All areas we serve</Link>
                </div>
              </div>
            </div>

            <Link v-for="item in primaryAfter" :key="item.href" :href="item.href" :class="mobileClass(item.href)" @click="mobileOpen = false">{{ item.label }}</Link>
          </div>

          <div class="mt-5 pt-5 border-t s-border space-y-3">
            <a v-if="company.tel" :href="'tel:' + tel" class="flex items-center gap-3 text-[16px] font-semibold s-heading">
              <span class="icon-box !w-9 !h-9"><svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" :d="icons.phone" /></svg></span>
              {{ company.phone }}
            </a>
            <a v-if="company.email" :href="'mailto:' + company.email" class="flex items-center gap-3 text-[15px] s-muted">
              <span class="icon-box !w-9 !h-9"><svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" :d="icons.mail" /></svg></span>
              <span class="truncate">{{ company.email }}</span>
            </a>
            <WhatsAppButton class="w-full mt-2">Get a free quote on WhatsApp</WhatsAppButton>
          </div>
        </nav>
      </div>
    </header>

    <main id="main" class="flex-1">
      <slot />
    </main>

    <!-- Footer -->
    <footer class="mt-auto s-bg-alt border-t s-border">
      <div class="container-app pt-16 lg:pt-20 pb-10">
        <div class="grid gap-12 lg:grid-cols-[1.25fr_0.8fr_0.8fr_1.15fr] lg:gap-10">
          <div class="max-w-sm">
            <Link href="/" class="inline-flex items-center gap-3">
              <img :src="img(company.footer_logo || company.logo, 160)" alt="" width="48" height="48" class="w-12 h-12 object-contain dark:bg-white dark:rounded-full dark:p-0.5" loading="lazy" />
              <span class="leading-tight">
                <span class="block text-[17px] font-bold s-heading" style="font-family: var(--font-display)">{{ company.name }}</span>
                <span class="block text-[13px] s-subtle">Plumbing Service · Singapore</span>
              </span>
            </Link>
            <p v-if="company.summary" class="mt-5 text-[15px] s-muted leading-relaxed">{{ company.summary }}</p>
            <div v-if="socialLinks.length" class="mt-6 flex flex-wrap gap-2">
              <a v-for="s in socialLinks" :key="s.href" :href="s.href" target="_blank" rel="noopener" :aria-label="s.label" class="w-10 h-10 rounded-full border s-border s-surface flex items-center justify-center s-muted hover:text-[var(--s-accent-text)] hover:border-[var(--s-border-2)] transition-colors">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path :d="s.icon" /></svg>
              </a>
            </div>
          </div>

          <nav aria-label="Services">
            <p class="text-[13px] font-semibold uppercase tracking-[0.1em] s-subtle">Services</p>
            <ul class="mt-5 space-y-3 text-[15px]">
              <li v-for="s in footerTopServices.slice(0, 5)" :key="s.href"><Link :href="s.href" class="s-muted hover:text-[var(--s-heading)]">{{ s.name }}</Link></li>
              <li><Link href="/services" class="font-semibold s-accent">All services →</Link></li>
            </ul>
          </nav>

          <nav aria-label="Company">
            <p class="text-[13px] font-semibold uppercase tracking-[0.1em] s-subtle">Company</p>
            <ul class="mt-5 space-y-3 text-[15px]">
              <li v-for="l in footerCompany" :key="l.href"><Link :href="l.href" class="s-muted hover:text-[var(--s-heading)]">{{ l.label }}</Link></li>
              <li><Link href="/pricing" class="s-muted hover:text-[var(--s-heading)]">Price list</Link></li>
            </ul>
          </nav>

          <div>
            <p class="text-[13px] font-semibold uppercase tracking-[0.1em] s-subtle">Contact</p>
            <ul class="mt-5 space-y-4 text-[15px]">
              <li v-if="company.tel">
                <a :href="'tel:' + tel" class="flex items-start gap-3 group">
                  <svg class="w-5 h-5 mt-0.5 shrink-0 s-accent" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" :d="icons.phone" /></svg>
                  <span><span class="block font-semibold s-heading group-hover:text-[var(--s-accent-text)]">{{ company.phone }}</span><span class="block text-[14px] s-subtle">Call or WhatsApp</span></span>
                </a>
              </li>
              <li v-if="company.email">
                <a :href="'mailto:' + company.email" class="flex items-start gap-3 s-muted hover:text-[var(--s-heading)]">
                  <svg class="w-5 h-5 mt-0.5 shrink-0 s-accent" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" :d="icons.mail" /></svg>
                  <span class="break-all">{{ company.email }}</span>
                </a>
              </li>
              <li v-if="company.address" class="flex items-start gap-3 s-muted">
                <svg class="w-5 h-5 mt-0.5 shrink-0 s-accent" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" :d="icons.pin" /></svg>
                <span>{{ company.address }}</span>
              </li>
              <li v-if="company.hours" class="flex items-start gap-3 s-muted">
                <svg class="w-5 h-5 mt-0.5 shrink-0 s-accent" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" :d="icons.clock" /></svg>
                <span>Open {{ company.hours }}</span>
              </li>
            </ul>
          </div>
        </div>

        <div class="mt-14 pt-7 border-t s-border flex flex-col md:flex-row md:items-center justify-between gap-4 text-[14px] s-subtle">
          <p>{{ company.copyright }}</p>
          <nav class="flex flex-wrap gap-x-6 gap-y-2" aria-label="Legal">
            <Link href="/privacy-policy" class="hover:text-[var(--s-heading)]">Privacy Policy</Link>
            <Link href="/terms-of-service" class="hover:text-[var(--s-heading)]">Terms of Service</Link>
            <a href="/sitemap.xml" class="hover:text-[var(--s-heading)]">Sitemap</a>
          </nav>
        </div>
      </div>
    </footer>

    <!-- WhatsApp (desktop): labelled pill -->
    <a v-if="company.whatsapp" :href="whatsappUrl" target="_blank" rel="noopener"
      :class="company.chat ? 'left-6' : 'right-6'"
      class="hidden lg:inline-flex fixed z-40 bottom-6 items-center gap-2.5 rounded-full bg-[#15803d] hover:bg-[#166534] text-white pl-4 pr-5 py-3 text-[15px] font-semibold shadow-[0_12px_30px_-10px_rgba(21,128,61,0.7)] transition-colors">
      <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path :d="icons.whatsapp" /></svg>
      WhatsApp us
    </a>

    <Lightbox />
    <SearchOverlay :open="searchOpen" @close="searchOpen = false" />

    <!-- Phone: floating call / WhatsApp card -->
    <nav class="lg:hidden fixed inset-x-3 z-40" style="bottom: calc(0.75rem + env(safe-area-inset-bottom))" aria-label="Quick actions">
      <div class="max-w-md mx-auto grid grid-cols-2 gap-2 rounded-[20px] s-surface border s-border p-2" style="box-shadow: var(--s-shadow-lg)">
        <a v-if="company.tel" :href="'tel:' + tel" class="h-12 rounded-[14px] flex items-center justify-center gap-2 text-[15px] font-semibold s-heading bg-[var(--s-surface-2)] active:opacity-80">
          <svg class="w-[18px] h-[18px] s-accent" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" :d="icons.phone" /></svg>
          Call now
        </a>
        <Link v-else href="/contact" class="h-12 rounded-[14px] flex items-center justify-center gap-2 text-[15px] font-semibold s-heading bg-[var(--s-surface-2)]">Contact</Link>
        <a v-if="company.whatsapp" :href="whatsappUrl" target="_blank" rel="noopener" class="h-12 rounded-[14px] flex items-center justify-center gap-2 text-[15px] font-semibold text-white bg-[#15803d] active:opacity-90">
          <svg class="w-[18px] h-[18px]" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path :d="icons.whatsapp" /></svg>
          WhatsApp
        </a>
        <Link v-else href="/contact" class="h-12 rounded-[14px] flex items-center justify-center text-[15px] font-semibold text-white" style="background: var(--s-btn)">Get a quote</Link>
      </div>
    </nav>

    <!-- Toast -->
    <transition enter-active-class="transition duration-200" enter-from-class="opacity-0 translate-y-2" leave-active-class="transition duration-150" leave-to-class="opacity-0">
      <div v-if="toast" class="fixed z-50 top-24 right-5 left-5 sm:left-auto sm:w-96 card p-4 flex gap-3" style="box-shadow: var(--s-shadow-lg)" role="status">
        <span :class="['w-8 h-8 rounded-full flex items-center justify-center shrink-0 text-white', toast.type === 'error' ? 'bg-[#dc2626]' : 'bg-[#16a34a]']">{{ toast.type === 'error' ? '!' : '✓' }}</span>
        <p class="text-[15px] s-text pt-1.5 flex-1">{{ toast.message }}</p>
        <button @click="toast = null" class="s-subtle text-[15px]" aria-label="Close">✕</button>
      </div>
    </transition>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import Lightbox from '@/Components/Site/Lightbox.vue';
import SearchOverlay from '@/Components/Site/SearchOverlay.vue';
import WhatsAppButton from '@/Components/Site/WhatsAppButton.vue';
import { img } from '@/utils/img';

const page = usePage();
const company = computed(() => page.props.company || {});
const meta = computed(() => page.props.meta);
const serviceCategories = computed(() => page.props.serviceCategories || []);
const topLocations = computed(() => page.props.topLocations || []);
// The header menu gets an Areas dropdown once there are enough area pages to make it useful;
// the footer always lists them (internal links for local SEO).
const showAreasMenu = computed(() => topLocations.value.length >= 4);
const footerTopServices = computed(() => page.props.footerTopServices || []);
const tel = computed(() => company.value.tel || '');
const whatsappUrl = computed(() => {
  const w = company.value.whatsapp || '';
  if (w.startsWith('http')) return w;
  return 'https://wa.me/' + w.replace(/\D/g, '');
});

const primaryBefore = [
  { label: 'Home', href: '/' },
  { label: 'About', href: '/about' },
];
const primaryAfter = [
  { label: 'Pricing', href: '/pricing' },
  { label: 'Projects', href: '/projects' },
  { label: 'Articles', href: '/blogs' },
  { label: 'Contact', href: '/contact' },
];
const footerCompany = [
  { label: 'About us', href: '/about' },
  { label: 'Our projects', href: '/projects' },
  { label: 'Customer reviews', href: '/reviews' },
  { label: 'Areas we serve', href: '/locations' },
  { label: 'Tips & guides', href: '/blogs' },
  { label: 'Contact us', href: '/contact' },
];

const open = ref(null);
const mobileOpen = ref(false);
const mobileSection = ref(null);
const serviceCount = computed(() => serviceCategories.value.reduce((n, g) => n + g.services.length, 0));
// Mobile menu: filter the service and area lists by name.
const mobileServiceQuery = ref('');
const mobileAreaQuery = ref('');
const mobileAreas = computed(() => {
  const q = mobileAreaQuery.value.trim().toLowerCase();
  return q ? topLocations.value.filter((l) => l.name.toLowerCase().includes(q)) : topLocations.value;
});
const mobileServiceGroups = computed(() => {
  const q = mobileServiceQuery.value.trim().toLowerCase();
  return serviceCategories.value
    .map((g) => ({ ...g, services: q ? g.services.filter((s) => s.name.toLowerCase().includes(q)) : g.services }))
    .filter((g) => g.services.length);
});
let closeTimer = null;
// A short delay lets the pointer travel from the menu button into the panel.
const openMenu = name => { clearTimeout(closeTimer); open.value = name; };
const closeMenuSoon = () => { clearTimeout(closeTimer); closeTimer = setTimeout(() => { open.value = null; }, 160); };
const toggle = name => { clearTimeout(closeTimer); open.value = open.value === name ? null : name; };

const path = computed(() => (page.url || '/').split('?')[0]);
const isActive = href => (href === '/' ? path.value === '/' : path.value === href || path.value.startsWith(href + '/') || (href === '/services' && path.value.startsWith('/service/')));
const navClass = href => ['py-2 text-[15px] font-medium transition-colors', isActive(href) ? 's-heading' : 's-muted hover:text-[var(--s-heading)]'];
const mobileClass = href => ['block px-3 py-2.5 rounded-lg text-[16px] font-medium', isActive(href) ? 's-heading s-surface-2' : 's-muted'];

watch(() => page.url, () => { mobileOpen.value = false; mobileSection.value = null; open.value = null; searchOpen.value = false; });

// Conversion events for GTM (switched on in admin): calls, WhatsApp and email clicks.
function trackClick(e) {
  if (!window.__trackEvents) return;
  const a = e.target.closest?.('a[href]');
  if (!a) return;
  const href = a.getAttribute('href') || '';
  const event = href.startsWith('tel:') ? 'click_call' : /wa\.me|whatsapp\.com/i.test(href) ? 'click_whatsapp' : href.startsWith('mailto:') ? 'click_email' : null;
  if (!event) return;
  (window.dataLayer = window.dataLayer || []).push({ event, link_url: href, page_path: location.pathname });
}
onMounted(() => document.addEventListener('click', trackClick, true));
onBeforeUnmount(() => document.removeEventListener('click', trackClick, true));

// Search: header button, Ctrl/Cmd+K or "/"
const searchOpen = ref(false);
function onSearchKey(e) {
  const typing = /input|textarea|select/i.test(e.target?.tagName || '') || e.target?.isContentEditable;
  if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); searchOpen.value = true; }
  else if (e.key === '/' && !typing) { e.preventDefault(); searchOpen.value = true; }
}
onMounted(() => document.addEventListener('keydown', onSearchKey));
onBeforeUnmount(() => document.removeEventListener('keydown', onSearchKey));

// Scroll shadow on the header
// A tiny marker at the top of the page is watched instead of reading the scroll position,
// so the browser never has to recalculate layout for it.
const scrolled = ref(false);
const topMarker = ref(null);
let topObserver;
onMounted(() => {
  if (!topMarker.value || !('IntersectionObserver' in window)) return;
  topObserver = new IntersectionObserver(([entry]) => { scrolled.value = !entry.isIntersecting; });
  topObserver.observe(topMarker.value);
});
onBeforeUnmount(() => topObserver?.disconnect());

// Flash messages (e.g. after sending the contact form)
const toast = ref(null);
let toastTimer = null;
watch(() => page.props.flash, (flash) => {
  // Forms show their own success message; the toast is only for problems.
  const message = flash?.error;
  if (!message) return;
  toast.value = { type: flash?.error ? 'error' : 'success', message };
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => { toast.value = null; }, 7000);
}, { immediate: true });

const icons = {
  mail: 'M3 8l7.9 5.3a2 2 0 002.2 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
  clock: 'M12 8v4l3 2m6-2a9 9 0 11-18 0 9 9 0 0118 0z',
  pin: 'M12 21s-7-6.2-7-11.5A7 7 0 0112 2.5a7 7 0 017 7C19 14.8 12 21 12 21zm0-9a2.5 2.5 0 100-5 2.5 2.5 0 000 5z',
  phone: 'M3 5a2 2 0 012-2h3.28a1 1 0 01.95.68l1.5 4.5a1 1 0 01-.5 1.2l-2.26 1.13a11 11 0 005.52 5.52l1.13-2.26a1 1 0 011.2-.5l4.5 1.5a1 1 0 01.68.95V19a2 2 0 01-2 2h-1C9.7 21 3 14.3 3 6V5z',
  whatsapp: 'M17.47 14.38c-.3-.15-1.76-.87-2.03-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.94 1.17-.17.2-.35.22-.64.07-.3-.15-1.26-.46-2.4-1.48-.89-.79-1.49-1.77-1.66-2.07-.17-.3-.02-.46.13-.61.13-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.02-.52-.08-.15-.67-1.62-.92-2.22-.24-.58-.49-.5-.67-.51h-.57c-.2 0-.52.07-.8.37-.27.3-1.04 1.02-1.04 2.49s1.07 2.89 1.22 3.09c.15.2 2.1 3.2 5.08 4.49.71.31 1.26.49 1.7.63.71.22 1.36.19 1.87.12.57-.09 1.76-.72 2-1.41.25-.7.25-1.29.17-1.41-.07-.13-.27-.2-.57-.35zM12.04 21.5h-.01a9.45 9.45 0 01-4.82-1.32l-.35-.2-3.58.94.96-3.49-.23-.36a9.43 9.43 0 01-1.45-5.03C2.56 6.83 6.8 2.6 12.04 2.6c2.54 0 4.92.99 6.72 2.78a9.42 9.42 0 012.78 6.71c0 5.23-4.25 9.41-9.5 9.41zm8.08-17.5A11.33 11.33 0 0012.04.67C5.74.67.61 5.8.61 12.1c0 2.01.53 3.98 1.53 5.71L.52 23.76l6.07-1.59a11.4 11.4 0 005.45 1.39h.01c6.3 0 11.43-5.13 11.43-11.43 0-3.05-1.19-5.92-3.36-8.08z',
};

const socialIcons = {
  facebook: 'M24 12.07C24 5.45 18.63.07 12 .07S0 5.45 0 12.07c0 5.99 4.39 10.95 10.13 11.85v-8.38H7.08v-3.47h3.05V9.43c0-3.01 1.79-4.67 4.53-4.67 1.31 0 2.69.24 2.69.24v2.95h-1.52c-1.49 0-1.96.93-1.96 1.87v2.25h3.33l-.53 3.47h-2.8v8.38C19.61 23.02 24 18.06 24 12.07z',
  instagram: 'M12 2.16c3.2 0 3.58.01 4.85.07 3.25.15 4.77 1.69 4.92 4.92.06 1.27.07 1.65.07 4.85s-.01 3.58-.07 4.85c-.15 3.23-1.66 4.77-4.92 4.92-1.27.06-1.64.07-4.85.07s-3.58-.01-4.85-.07c-3.26-.15-4.77-1.7-4.92-4.92-.06-1.27-.07-1.64-.07-4.85s.01-3.58.07-4.85C2.38 3.92 3.9 2.38 7.15 2.23 8.42 2.17 8.8 2.16 12 2.16zM12 0C8.74 0 8.33.01 7.05.07 2.7.27.27 2.69.07 7.05.01 8.33 0 8.74 0 12s.01 3.67.07 4.95c.2 4.36 2.62 6.78 6.98 6.98 1.28.06 1.69.07 4.95.07s3.67-.01 4.95-.07c4.35-.2 6.78-2.62 6.98-6.98.06-1.28.07-1.69.07-4.95s-.01-3.67-.07-4.95C23.73 2.7 21.31.27 16.95.07 15.67.01 15.26 0 12 0zm0 5.84a6.16 6.16 0 100 12.32 6.16 6.16 0 000-12.32zM12 16a4 4 0 110-8 4 4 0 010 8zm6.41-11.85a1.44 1.44 0 100 2.88 1.44 1.44 0 000-2.88z',
  linkedin: 'M20.45 20.45h-3.55v-5.57c0-1.33-.03-3.04-1.85-3.04-1.85 0-2.14 1.45-2.14 2.94v5.67H9.35V9h3.41v1.56h.05c.48-.9 1.64-1.85 3.37-1.85 3.6 0 4.27 2.37 4.27 5.46v6.28zM5.34 7.43a2.06 2.06 0 110-4.13 2.06 2.06 0 010 4.13zM7.12 20.45H3.56V9h3.56v11.45zM22.22 0H1.77C.79 0 0 .77 0 1.73v20.54C0 23.23.79 24 1.77 24h20.45c.98 0 1.78-.77 1.78-1.73V1.73C24 .77 23.2 0 22.22 0z',
};
socialIcons.x = 'M18.24 2.25h3.31l-7.23 8.26 8.5 11.24h-6.66l-5.21-6.82-5.97 6.82H1.67l7.73-8.84L1.25 2.25h6.83l4.71 6.23 5.45-6.23zm-1.16 17.52h1.83L7.08 4.13H5.12l11.96 15.64z';
socialIcons.youtube = 'M23.5 6.2a3 3 0 00-2.1-2.1C19.5 3.6 12 3.6 12 3.6s-7.5 0-9.4.5A3 3 0 00.5 6.2 31.4 31.4 0 000 12a31.4 31.4 0 00.5 5.8 3 3 0 002.1 2.1c1.9.5 9.4.5 9.4.5s7.5 0 9.4-.5a3 3 0 002.1-2.1A31.4 31.4 0 0024 12a31.4 31.4 0 00-.5-5.8zM9.6 15.6V8.4l6.3 3.6-6.3 3.6z';
socialIcons.tiktok = 'M19.6 6.7a4.8 4.8 0 01-3.8-4.2V2h-3.4v13.6a2.9 2.9 0 11-2-2.7V9.4a6.3 6.3 0 105.4 6.2V8.7a8.2 8.2 0 003.8 1V6.7z';
socialIcons.pinterest = 'M12 0a12 12 0 00-4.4 23.2c-.1-.9-.2-2.4 0-3.4l1.4-6s-.4-.7-.4-1.8c0-1.7 1-2.9 2.2-2.9 1 0 1.5.8 1.5 1.7 0 1-.7 2.6-1 4-.3 1.2.6 2.2 1.8 2.2 2.1 0 3.8-2.2 3.8-5.5 0-2.9-2.1-4.9-5-4.9-3.4 0-5.4 2.6-5.4 5.2 0 1 .4 2.1.9 2.7.1.1.1.2.1.3l-.3 1.4c-.1.2-.2.3-.4.2-1.5-.7-2.4-2.9-2.4-4.6 0-3.8 2.7-7.2 7.9-7.2 4.1 0 7.3 2.9 7.3 6.9 0 4.1-2.6 7.4-6.2 7.4-1.2 0-2.4-.6-2.8-1.4l-.7 2.9c-.3 1-1 2.3-1.5 3.1A12 12 0 1012 0z';
socialIcons.google = 'M12.24 10.29v3.67h5.2c-.22 1.34-1.58 3.93-5.2 3.93-3.13 0-5.68-2.59-5.68-5.79s2.55-5.79 5.68-5.79c1.78 0 2.97.76 3.66 1.41l2.49-2.4C16.79 3.84 14.74 2.9 12.24 2.9 7.21 2.9 3.14 6.97 3.14 12s4.07 9.1 9.1 9.1c5.25 0 8.74-3.69 8.74-8.89 0-.6-.07-1.05-.15-1.51h-8.59z';
const socialLabels = { google: 'Google reviews', x: 'X', youtube: 'YouTube', tiktok: 'TikTok', linkedin: 'LinkedIn' };
const socialLinks = computed(() => Object.entries(company.value.socials || {})
  .map(([k, href]) => ({ href, label: socialLabels[k] || k[0].toUpperCase() + k.slice(1), icon: socialIcons[k] }))
  .filter(s => s.icon));
</script>
