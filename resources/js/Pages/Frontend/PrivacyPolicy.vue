<template>
  <FrontendLayout>
    <PageHero title="Privacy policy" eyebrow="Legal" lead="How we collect, use and protect your personal data under Singapore's Personal Data Protection Act (PDPA) 2012." :crumbs="[{ label: 'Privacy policy' }]" />

    <section class="section-y s-bg">
      <div class="container-app max-w-3xl">
        <article class="prose-site">
          <p class="s-subtle text-[15px]">Last updated: {{ updated }}</p>

          <h2>1. Information we collect</h2>
          <p>When you use our website or contact us about our services, we may collect:</p>
          <ul>
            <li><strong>Personal information:</strong> your name, email address, phone number and address when you send a contact or quote form.</li>
            <li><strong>Job details:</strong> what you need done, photos you send and where the property is.</li>
            <li><strong>Usage data:</strong> browser type, pages visited, time on site and the referring page, for analytics.</li>
            <li><strong>Cookies:</strong> essential cookies that make the website work, and analytics cookies if analytics is switched on.</li>
          </ul>

          <h2>2. How we use your information</h2>
          <ul>
            <li>To answer your enquiry and send you a quotation</li>
            <li>To arrange site visits and manage your job</li>
            <li>To send important updates about work we are doing for you</li>
            <li>To improve our website and services</li>
            <li>To meet our legal obligations in Singapore</li>
          </ul>

          <h2>3. How we protect it</h2>
          <p>We use industry-standard security to protect your personal data. It is stored securely and only people who need it to do their job can see it. We comply with the PDPA 2012.</p>

          <h2>4. Sharing</h2>
          <p>We do not sell, trade or rent your personal data. We only share it when needed to carry out your job (for example with a subcontractor working on your property) or when the law requires it.</p>

          <h2>5. Your rights</h2>
          <p>Under the PDPA you may:</p>
          <ul>
            <li>Ask to see the personal data we hold about you</li>
            <li>Ask us to correct data that is wrong</li>
            <li>Withdraw your consent to us using your data</li>
            <li>Ask us to delete your data</li>
          </ul>

          <h2>6. Contact us</h2>
          <p>For questions about this policy or to use your rights, contact our data protection contact:</p>
          <p>
            <strong>{{ company.legal_name || company.name }}</strong><br />
            <template v-if="company.address">{{ company.address }}<br /></template>
            <template v-if="company.email">Email: <a :href="'mailto:' + company.email">{{ company.email }}</a><br /></template>
            <template v-if="company.phone">Phone: <a :href="'tel:' + company.phone">{{ company.phone }}</a></template>
          </p>
        </article>
      </div>
    </section>
  </FrontendLayout>
</template>

<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import FrontendLayout from '@/Layouts/FrontendLayout.vue';
import PageHero from '@/Components/Site/PageHero.vue';

const company = computed(() => usePage().props.company || {});
// Change this date whenever the policy text changes.
const updated = '30 September 2026';
</script>
