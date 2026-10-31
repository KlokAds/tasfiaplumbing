<template>
  <LegalPage title="Privacy policy" lead="How we collect, use and protect your personal data under Singapore's Personal Data Protection Act (PDPA) 2012." :updated="updated" :sections="sections"
    contact-title="Contact us" :other="{ label: 'Terms of service', href: '/terms-of-service' }">
    <template #collect>
      <p>When you use our website or contact us about our services, we may collect:</p>
      <ul>
        <li><strong>Personal information:</strong> your name, email address, phone number and address when you send a contact or quote form.</li>
        <li><strong>Job details:</strong> what you need done, photos you send and where the property is.</li>
        <li><strong>Usage data:</strong> browser type, pages visited, time on site and the referring page, for analytics.</li>
        <li><strong>Cookies:</strong> essential cookies that make the website work, and analytics cookies if analytics is switched on.</li>
      </ul>
    </template>

    <template #use>
      <ul>
        <li>To answer your enquiry and send you a quotation</li>
        <li>To arrange site visits and manage your job</li>
        <li>To send important updates about work we are doing for you</li>
        <li>To improve our website and services</li>
        <li>To meet our legal obligations in Singapore</li>
      </ul>
    </template>

    <template #protect>
      <p>We use industry-standard security to protect your personal data. It is stored securely and only people who need it to do their job can see it. We comply with the PDPA 2012.</p>
    </template>

    <template #sharing>
      <p>We do not sell, trade or rent your personal data. We only share it when needed to carry out your job (for example with a subcontractor working on your property) or when the law requires it.</p>
    </template>

    <template #rights>
      <p>Under the PDPA you may:</p>
      <ul>
        <li>Ask to see the personal data we hold about you</li>
        <li>Ask us to correct data that is wrong</li>
        <li>Withdraw your consent to us using your data</li>
        <li>Ask us to delete your data</li>
      </ul>
    </template>

    <template #contact>
      <p>For questions about this policy or to use your rights, contact our data protection contact:</p>
    </template>
  </LegalPage>
</template>

<script setup>
import LegalPage from '@/Components/Site/LegalPage.vue';

// Change this date whenever the policy text changes.
const updated = '30 September 2026';
const sections = [
  { id: 'collect', title: 'Information we collect' },
  { id: 'use', title: 'How we use your information' },
  { id: 'protect', title: 'How we protect it' },
  { id: 'sharing', title: 'Sharing' },
  { id: 'rights', title: 'Your rights' },
];
</script>
