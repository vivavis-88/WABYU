// In-memory mirror of what the server last confirmed. The server (JSON file) is the source of truth.
const listeners = new Set();
export const store = { memories: [], loaded: false };
export const visible = { ids: [] };

export const subscribe = (fn) => { listeners.add(fn); return () => listeners.delete(fn); };
const emit = (e) => listeners.forEach((fn) => fn(e));
const byNewest = (a, b) => (a.created_at < b.created_at ? 1 : a.created_at > b.created_at ? -1 : 0);

export const getById = (id) => store.memories.find((m) => m.id === id);
export const setVisible = (ids) => { visible.ids = ids; };

export function setAll(list) {
  store.memories = [...list].sort(byNewest);
  store.loaded = true;
  emit({ type: 'reset' });
}
export function upsert(m) {
  const i = store.memories.findIndex((x) => x.id === m.id);
  if (i >= 0) store.memories[i] = m; else store.memories.push(m);
  store.memories.sort(byNewest);
  emit({ type: 'upsert', id: m.id });
}
export function remove(id) {
  store.memories = store.memories.filter((m) => m.id !== id);
  emit({ type: 'remove', id });
}
