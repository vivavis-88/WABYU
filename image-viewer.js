import { imageSrc } from './api.js';
import { getById, visible, subscribe } from './store.js';
import { openDialog, closeDialog, onDismiss, formatDate } from './ui.js';

export function initViewer(root, signal, { onEdit, onDelete, onClose }) {
  const dlg = root.querySelector('#viewer');
  const img = root.querySelector('#viewer-img');
  const stage = root.querySelector('#viewer-stage');
  const prev = root.querySelector('#viewer-prev');
  const next = root.querySelector('#viewer-next');
  let id = null;

  function render() {
    const m = id && getById(id);
    if (!m) return;
    img.src = imageSrc(m);
    img.alt = `Photograph: ${m.title}`;
    root.querySelector('#viewer-title').textContent = m.title;
    root.querySelector('#viewer-comment').textContent = m.comment;
    root.querySelector('#viewer-meta').textContent = [m.category, formatDate(m.memory_date)].filter(Boolean).join('  ·  ');
    const solo = visible.ids.length < 2;
    prev.hidden = next.hidden = solo;
  }

  function go(d) {
    const ids = visible.ids;
    if (ids.length < 2) return;
    const i = ids.indexOf(id);
    id = ids[(i + d + ids.length) % ids.length];
    render();
  }

  async function close() {
    const was = id;
    await closeDialog(dlg);
    id = null;
    onClose(was);
  }

  root.querySelector('#viewer-close').addEventListener('click', close, { signal });
  onDismiss(dlg, close, signal);
  prev.addEventListener('click', () => go(-1), { signal });
  next.addEventListener('click', () => go(1), { signal });
  root.querySelector('#viewer-edit').addEventListener('click', () => id && onEdit(id), { signal });
  root.querySelector('#viewer-delete').addEventListener('click', () => id && onDelete(id), { signal });   // always the confirmation flow

  dlg.addEventListener('keydown', (e) => {
    if (e.key === 'ArrowLeft') go(-1);
    else if (e.key === 'ArrowRight') go(1);
  }, { signal });

  // Touch / pointer swipe
  let sx = 0, sy = 0, tracking = false;
  stage.addEventListener('pointerdown', (e) => { tracking = true; sx = e.clientX; sy = e.clientY; }, { signal });
  stage.addEventListener('pointerup', (e) => {
    if (!tracking) return; tracking = false;
    const dx = e.clientX - sx, dy = e.clientY - sy;
    if (Math.abs(dx) > 50 && Math.abs(dy) < 70) go(dx < 0 ? 1 : -1);
  }, { signal });
  stage.addEventListener('pointercancel', () => { tracking = false; }, { signal });

  const unsub = subscribe((e) => {
    if (!dlg.open || !id) return;
    if (e.type === 'remove' && e.id === id) {
      const ids = visible.ids.filter((x) => x !== id);
      if (ids.length) { id = ids[0]; render(); } else close();
    } else if (e.type === 'upsert' && e.id === id) render();
  });
  signal.addEventListener('abort', unsub);

  return {
    open(memoryId) { id = memoryId; render(); openDialog(dlg); root.querySelector('#viewer-close').focus(); },
    isOpen: () => dlg.open,
  };
}
