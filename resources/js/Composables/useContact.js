import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

/**
 * WhatsApp-first contact links. Most Singapore customers prefer WhatsApp to a form,
 * so the main call-to-action opens a chat with a message already written for the page.
 */
export function useContact() {
  const page = usePage();
  const company = computed(() => page.props.company || {});
  const tel = computed(() => company.value.tel || '');

  const waBase = computed(() => {
    const w = company.value.whatsapp || '';
    if (!w) return '';
    return w.startsWith('http') ? w : 'https://wa.me/' + w.replace(/\D/g, '');
  });

  /** WhatsApp link with a ready message, e.g. wa('Water heater repair'). Falls back to /contact. */
  function wa(topic = '') {
    if (!waBase.value) return '/contact';
    const name = company.value.name || '';
    const text = topic
      ? `Hi ${name}, I would like a quote for ${topic}.`
      : `Hi ${name}, I would like a quote.`;
    return waBase.value + (waBase.value.includes('?') ? '&' : '?') + 'text=' + encodeURIComponent(text);
  }

  const hasWhatsapp = computed(() => !!waBase.value);

  return { company, tel, wa, hasWhatsapp };
}
