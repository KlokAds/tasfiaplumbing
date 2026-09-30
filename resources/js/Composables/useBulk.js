import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { confirmDialog } from '@/Composables/useConfirm';

/**
 * Row selection + bulk delete for an admin list.
 *
 *   const bulk = useBulk('services', () => pager.rows.value, { label: 'service' });
 *   <input type="checkbox" :checked="bulk.all.value" :indeterminate.prop="bulk.some.value" @change="bulk.toggleAll()">
 *   <input type="checkbox" :checked="bulk.has(s.id)" @change="bulk.toggle(s.id)">
 *   <BulkBar :bulk="bulk" />
 */
export function useBulk(resource, rows, { label = 'item', plural = null } = {}) {
  const selected = ref([]);
  const visibleIds = computed(() => (rows() || []).map((r) => r.id));
  const has = (id) => selected.value.includes(id);
  const all = computed(() => visibleIds.value.length > 0 && visibleIds.value.every(has));
  const some = computed(() => !all.value && visibleIds.value.some(has));
  const busy = ref(false);

  function toggle(id) {
    selected.value = has(id) ? selected.value.filter((x) => x !== id) : [...selected.value, id];
  }
  function toggleAll() {
    selected.value = all.value
      ? selected.value.filter((id) => !visibleIds.value.includes(id))
      : [...new Set([...selected.value, ...visibleIds.value])];
  }
  const clear = () => { selected.value = []; };

  // Items deleted elsewhere (or filtered away by the server) drop out of the selection.
  watch(visibleIds, (ids) => {
    if (!selected.value.length) return;
    const known = new Set(ids);
    if (rows().length && selected.value.every((id) => !known.has(id))) selected.value = [];
  });

  const names = (n) => (n === 1 ? label : plural || `${label}s`);
  async function destroy() {
    const n = selected.value.length;
    if (!n) return;
    const ok = await confirmDialog({
      title: `Delete ${n} ${names(n)}?`,
      message: 'This cannot be undone. Anything that cannot be deleted (in use or protected) is skipped and listed in the message.',
      confirmText: `Delete ${n}`,
      tone: 'danger',
    });
    if (!ok) return;
    busy.value = true;
    router.post(`/admin/bulk/${resource}/delete`, { ids: selected.value }, {
      preserveScroll: true,
      onSuccess: () => clear(),
      onFinish: () => { busy.value = false; },
    });
  }

  return { selected, has, all, some, toggle, toggleAll, clear, destroy, busy, count: computed(() => selected.value.length), names };
}
