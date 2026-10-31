<template>
  <LegalPage title="Terms of service" lead="The terms for using this website and our quotation process." :updated="updated" :sections="sections"
    contact-title="Contact" :other="{ label: 'Privacy policy', href: '/privacy-policy' }">
    <template #acceptance>
      <p>By using the {{ name }} website you agree to these terms. If you do not agree, please do not use the website.</p>
    </template>

    <template #services>
      <p>{{ name }} provides plumbing repair, installation and maintenance services in Singapore. The services we offer, and their details, are listed on the <Link href="/services">services page</Link>.</p>
    </template>

    <template #quotations>
      <p>Prices on this website and quotations given before a site visit are estimates. They can change after we see the site, because of material costs or changes to the scope. The final price is confirmed in writing before work starts.</p>
    </template>

    <template #ip>
      <p>The text, images, logos, designs and project photos on this website belong to {{ name }}. Please do not copy, reuse or change them without our written permission.</p>
    </template>

    <template #use>
      <p>When using this website you agree to:</p>
      <ul>
        <li>Give accurate information in our forms</li>
        <li>Not misuse the website or try to access it without permission</li>
        <li>Not use it for anything unlawful</li>
        <li>Not send spam, harmful content or automated requests</li>
      </ul>
    </template>

    <template #liability>
      <p>We are not liable for indirect, incidental or consequential loss arising from use of this website. We work to keep the information accurate and current but cannot guarantee it is complete at all times.</p>
    </template>

    <template #law>
      <p>These terms are governed by the laws of the Republic of Singapore, and the courts of Singapore have exclusive jurisdiction over any dispute.</p>
    </template>

    <template #changes>
      <p>We may update these terms. The date above shows when they last changed. Continuing to use the website after a change means you accept the updated terms.</p>
    </template>
  </LegalPage>
</template>

<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import LegalPage from '@/Components/Site/LegalPage.vue';

const company = computed(() => usePage().props.company || {});
const name = computed(() => company.value.legal_name || company.value.name || 'we');
// Change this date whenever the terms change.
const updated = '30 September 2026';
const sections = [
  { id: 'acceptance', title: 'Acceptance of terms' },
  { id: 'services', title: 'Our services' },
  { id: 'quotations', title: 'Quotations and prices' },
  { id: 'ip', title: 'Intellectual property' },
  { id: 'use', title: 'Using the website' },
  { id: 'liability', title: 'Limitation of liability' },
  { id: 'law', title: 'Governing law' },
  { id: 'changes', title: 'Changes' },
];
</script>
