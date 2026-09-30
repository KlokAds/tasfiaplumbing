import { ref } from 'vue';

// One shared list of toasts for the whole admin. AdminLayout shows them; any page or
// component can call toast.success('…') / toast.error('…') / toast.info('…').
const toasts = ref([]);
let nextId = 0;

function push(type, message, ms) {
  if (!message) return;
  const id = ++nextId;
  toasts.value.push({ id, type, message });
  setTimeout(() => dismiss(id), ms ?? (type === 'error' ? 9000 : 5000));
  return id;
}
function dismiss(id) {
  toasts.value = toasts.value.filter((t) => t.id !== id);
}

export const toast = {
  success: (m, ms) => push('success', m, ms),
  error: (m, ms) => push('error', m, ms),
  info: (m, ms) => push('info', m, ms),
};

export function useToast() {
  return { toasts, push, dismiss, toast };
}
