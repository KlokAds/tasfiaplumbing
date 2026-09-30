import { onBeforeUnmount, ref, watch } from 'vue';
import axios from 'axios';

/**
 * Autosave for long editor forms.
 * Every change is written to this browser within ~1.5 s and to the server right after,
 * so a power cut, closed tab or crashed PC does not lose the text.
 * Opening the editor again puts newer unsaved work back automatically (`restored`);
 * `undoRestore()` goes back to the last saved version.
 *
 * useAutosave({ type: 'article', recordId: computedIdOrNull, form, fields: [...], active: refModalOpen })
 */
export function useAutosave({ type, recordId, form, fields, active }) {
  const status = ref('idle'); // idle | pending | saving | saved | offline
  const savedAt = ref(null);
  const restore = ref(null);
  const restored = ref(null); // { saved_at } when unsaved work was put back on open
  let original = null; // the saved version, for undoRestore()

  let lastJson = '';
  const suspended = ref(true); // reactive, so the watcher re-runs once loading has finished
  let localTimer = null;
  let retryTimer = null;
  let dirty = false;

  const id = () => recordId.value || 0;
  const key = () => `tasfia:draft:${type}:${id()}`;
  const url = () => `/admin/drafts/${type}/${id()}`;
  const snapshot = () => Object.fromEntries(fields.map((f) => [f, form[f]]));

  function writeLocal() {
    try {
      localStorage.setItem(key(), JSON.stringify({ payload: snapshot(), saved_at: new Date().toISOString() }));
    } catch (e) { /* storage full or blocked */ }
  }

  async function pushServer() {
    if (!dirty) return;
    status.value = 'saving';
    try {
      const { data } = await axios.put(url(), { payload: snapshot() });
      dirty = false;
      savedAt.value = data.saved_at;
      status.value = 'saved';
    } catch (e) {
      status.value = 'offline';
      clearTimeout(retryTimer);
      retryTimer = setTimeout(pushServer, 15000);
    }
  }

  watch(
    () => (active.value && !suspended.value ? JSON.stringify(snapshot()) : null),
    (json) => {
      if (!json || json === lastJson) return;
      lastJson = json;
      dirty = true;
      status.value = 'pending';
      clearTimeout(localTimer);
      localTimer = setTimeout(() => {
        writeLocal();
        pushServer();
      }, 1500);
    },
  );

  function flushNow() {
    if (status.value === 'pending') writeLocal();
  }
  window.addEventListener('beforeunload', flushNow);
  onBeforeUnmount(() => {
    flushNow();
    window.removeEventListener('beforeunload', flushNow);
    clearTimeout(localTimer);
    clearTimeout(retryTimer);
  });

  /** Call right after the form has been filled for the record being opened. */
  async function start(record = null) {
    suspended.value = true;
    restore.value = null;
    restored.value = null;
    original = null;
    status.value = 'idle';
    savedAt.value = null;
    dirty = false;
    lastJson = JSON.stringify(snapshot());

    let local = null;
    try { local = JSON.parse(localStorage.getItem(key()) || 'null'); } catch (e) { local = null; }
    let server = null;
    try { server = (await axios.get(url())).data; } catch (e) { server = null; }

    const newest = [local, server].filter((d) => d && d.payload && d.saved_at).sort((a, b) => new Date(b.saved_at) - new Date(a.saved_at))[0];
    const recordTime = record?.updated_at ? new Date(record.updated_at) : null;
    if (newest && (!recordTime || new Date(newest.saved_at) > recordTime) && JSON.stringify(newest.payload) !== lastJson) {
      // Put the unsaved work back straight away; the editor shows a note with an undo.
      original = snapshot();
      fields.forEach((f) => {
        if (f in newest.payload) form[f] = newest.payload[f];
      });
      restored.value = { saved_at: newest.saved_at };
      lastJson = JSON.stringify(snapshot());
      // A newer copy that only this browser has is sent to the server as well.
      if (newest === local && (!server?.saved_at || new Date(local.saved_at) > new Date(server.saved_at))) {
        dirty = true;
        pushServer();
      }
    }
    suspended.value = false;
  }

  /** Throw the restored work away and go back to the last saved version. */
  async function undoRestore() {
    if (!original) return;
    suspended.value = true;
    fields.forEach((f) => {
      if (f in original) form[f] = original[f];
    });
    lastJson = JSON.stringify(snapshot());
    restored.value = null;
    original = null;
    await discard();
    suspended.value = false;
  }

  function applyRestore() {
    if (!restore.value) return;
    fields.forEach((f) => {
      if (f in restore.value.payload) form[f] = restore.value.payload[f];
    });
    restore.value = null;
  }

  async function discard() {
    restore.value = null;
    clearTimeout(localTimer);
    dirty = false;
    try { localStorage.removeItem(key()); } catch (e) { /* ignore */ }
    try { await axios.delete(url()); } catch (e) { /* ignore */ }
  }

  /** After a successful save (the server already deleted its copy). */
  function clear() {
    restored.value = null;
    original = null;
    clearTimeout(localTimer);
    dirty = false;
    status.value = 'idle';
    try { localStorage.removeItem(key()); } catch (e) { /* ignore */ }
  }

  return { status, savedAt, restore, restored, start, applyRestore, undoRestore, discard, clear };
}
