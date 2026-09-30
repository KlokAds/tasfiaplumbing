import { computed, ref, watch } from 'vue';

/**
 * Pages a list that is already in the browser (filtered/searched client-side).
 * Page size is remembered per list, so each screen keeps the owner's choice.
 *
 *   const pager = usePaged(filtered, 'services');
 *   <tr v-for="s in pager.rows"> … <ClientPagination :pager="pager" />
 */
export function usePaged(list, key, defaultSize = 10) {
  const storeKey = `admin:per_page:${key}`;
  let saved = defaultSize;
  try { saved = Number(localStorage.getItem(storeKey)) || defaultSize; } catch (e) { /* private mode */ }

  const page = ref(1);
  const perPage = ref(saved);
  const total = computed(() => list.value.length);
  const lastPage = computed(() => Math.max(1, Math.ceil(total.value / perPage.value)));
  const rows = computed(() => list.value.slice((page.value - 1) * perPage.value, page.value * perPage.value));

  // A new search/filter starts again on page 1; never sit past the last page.
  watch(total, () => { if (page.value > lastPage.value) page.value = 1; });
  watch(perPage, (n) => {
    page.value = 1;
    try { localStorage.setItem(storeKey, String(n)); } catch (e) { /* ignore */ }
  });

  return {
    page, perPage, total, lastPage, rows,
    from: computed(() => (total.value ? (page.value - 1) * perPage.value + 1 : 0)),
    to: computed(() => Math.min(total.value, page.value * perPage.value)),
    reset: () => { page.value = 1; },
  };
}
