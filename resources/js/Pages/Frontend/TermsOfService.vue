<template>
  <FrontendLayout>
    <PageHero title="Terms of service" eyebrow="Legal" lead="The terms for using this website and our quotation process." :crumbs="[{ label: 'Terms of service' }]" />

    <section class="section-y s-bg">
      <div class="container-app max-w-3xl">
        <article class="prose-site">
          <p class="s-subtle text-[15px]">Last updated: {{ updated }}</p>

          <h2>1. Acceptance of terms</h2>
          <p>By using the {{ name }} website you agree to these terms. If you do not agree, please do not use the website.</p>

          <h2>2. Our services</h2>
          <p>{{ name }} provides plumbing repair, installation and maintenance services in Singapore. The services we offer, and their details, are listed on the <Link href="/services">services page</Link>.</p>

          <h2>3. Quotations and prices</h2>
          <p>Prices on this website and quotations given before a site visit are estimates. They can change after we see the site, because of material costs or changes to the scope. The final price is confirmed in writing before work starts.</p>

          <h2>4. Intellectual property</h2>
          <p>The text, images, logos, designs and project photos on this website belong to {{ name }}. Please do not copy, reuse or change them without our written permission.</p>

          <h2>5. Using the website</h2>
          <p>When using this website you agree to:</p>
          <ul>
            <li>Give accurate information in our forms</li>
            <li>Not misuse the website or try to access it without permission</li>
            <li>Not use it for anything unlawful</li>
            <li>Not send spam, harmful content or automated requests</li>
          </ul>

          <h2>6. Limitation of liability</h2>
          <p>We are not liable for indirect, incidental or consequential loss arising from use of this website. We work to keep the information accurate and current but cannot guarantee it is complete at all times.</p>

          <h2>7. Governing law</h2>
          <p>These terms are governed by the laws of the Republic of Singapore, and the courts of Singapore have exclusive jurisdiction over any dispute.</p>

          <h2>8. Changes</h2>
          <p>We may update these terms. The date above shows when they last changed. Continuing to use the website after a change means you accept the updated terms.</p>

          <h2>9. Contact</h2>
          <p>
            <strong>{{ company.legal_name || name }}</strong><br />
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
import { Link, usePage } from '@inertiajs/vue3';
import FrontendLayout from '@/Layouts/FrontendLayout.vue';
import PageHero from '@/Components/Site/PageHero.vue';

const company = computed(() => usePage().props.company || {});
const name = computed(() => company.value.legal_name || company.value.name || 'we');
// Change this date whenever the terms change.
const updated = '30 September 2026';
</script>
