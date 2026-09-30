import { reactive } from 'vue';

export const confirmState = reactive({
  open: false,
  title: '',
  message: '',
  confirmText: 'Delete',
  cancelText: 'Cancel',
  tone: 'danger',
  resolve: null,
});

/**
 * Promise-based replacement for window.confirm().
 * confirmDialog('Delete this?') or confirmDialog({ title, message, confirmText, tone: 'danger' | 'primary' })
 */
export function confirmDialog(options) {
  const opts = typeof options === 'string' ? { message: options } : options;
  if (confirmState.resolve) confirmState.resolve(false);

  return new Promise((resolve) => {
    Object.assign(confirmState, {
      open: true,
      title: opts.title || 'Are you sure?',
      message: opts.message || '',
      confirmText: opts.confirmText || 'Delete',
      cancelText: opts.cancelText || 'Cancel',
      tone: opts.tone || 'danger',
      resolve,
    });
  });
}

export function settleConfirm(value) {
  const resolve = confirmState.resolve;
  confirmState.open = false;
  confirmState.resolve = null;
  if (resolve) resolve(value);
}
