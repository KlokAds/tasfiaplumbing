import { ref, watch } from 'vue';
import axios from 'axios';

/**
 * Warns while typing when a title or meta title is already used by another page.
 * The server blocks saving in that case too; this just tells the writer early.
 *
 * const { titleConflict, metaConflict } = useTitleCheck(form, 'article', () => editing.value?.id)
 */
export function useTitleCheck(form, type, getId) {
  const titleConflict = ref(null);
  const metaConflict = ref(null);

  function watchField(field, target) {
    let timer = null;
    let seq = 0;
    watch(() => form[field], (text) => {
      clearTimeout(timer);
      const value = (text || '').trim();
      if (value.length < 8) { target.value = null; return; }
      timer = setTimeout(async () => {
        const mine = ++seq;
        try {
          const { data } = await axios.get('/admin/content-check/title', { params: { text: value, type, id: getId() || undefined } });
          if (mine === seq) target.value = data.conflict;
        } catch (e) { /* offline: the server check on save still applies */ }
      }, 450);
    });
  }

  watchField('name', titleConflict);
  watchField('meta_title', metaConflict);

  const describe = (c) => c && `Already used as the ${c.field} of “${c.name}”. Make this one more specific so both pages can rank.`;

  return { titleConflict, metaConflict, describe };
}
