import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

/**
 * Permission checks in admin pages, matching the server's route rules.
 * const { can } = usePermissions(); can('services.delete')
 * The server still enforces everything; this only hides buttons that would fail.
 */
export function usePermissions() {
  const page = usePage();
  const map = computed(() => page.props.admin?.can || {});
  const can = (name) => !!map.value[name];
  const canAny = (...names) => names.some(can);

  return { can, canAny };
}
