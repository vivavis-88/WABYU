import * as api from './api.js';
import { imageSrc } from './api.js';
import { openDialog, closeDialog, onDismiss } from './ui.js';

const TYPES = ['image/jpeg', 'image/png', 'image/webp'];

/** Returns { open(memoryOrNull) -> Promise<savedMemory|null>, isDirty() }. */
export function initEditor(root, signal, { getCategories }) {
  const shell = root.querySelector('#gallery-shell');
  const maxBytes = Number(shell.dataset.maxBytes) || 15 * 1048576;
  const dlg = root.querySelector('#editor');
  const form = root.querySelector('#editor-form');
  const input = root.querySelector('#image-input');
  const zone = root.querySelector('#dropzone');
  const preview = root.querySelector('#image-preview');
  const field = (n) => form.elements.namedItem(n);
  const f = { title: field('title'), comment: field('comment'), date: field('memory_date'), category: field('category'), fav: field('favorite') };
  const saveBtn = root.querySelector('#editor-save');
  const formErr = root.querySelector('#editor-error');
  const discard = root.querySelector('#discard-bar');
  let current = null, initial = '', busy = false, settle = null, blobUrl = null;

  const snapshot = () => JSON.stringify([f.title.value.trim(), f.comment.value, f.date.value, f.category.value.trim(), f.fav.checked, input.files.length > 0]);
  const isDirty = () => dlg.open && snapshot() !== initial;
  const setErr = (name, msg) => { const n = root.querySelector(`#err-${name}`); if (n) n.textContent = msg || ''; };
  const clearErrors = () => { ['image', 'title', 'memory_date'].forEach((n) => setErr(n, '')); formErr.textContent = ''; f.title.removeAttribute('aria-invalid'); };
  const count = () => { root.querySelector('#comment-count').textContent = String(f.comment.value.length); };

  function setPreview(src) {
    if (blobUrl) { URL.revokeObjectURL(blobUrl); blobUrl = null; }
    if (src && src.startsWith('blob:')) blobUrl = src;
    preview.hidden = !src;
    zone.classList.toggle('has-image', !!src);
    if (src) preview.src = src; else preview.removeAttribute('src');
  }

  function pick(file) {
    setErr('image', '');
    if (!file) return;
    if (!TYPES.includes(file.type)) { input.value = ''; setErr('image', 'Please choose a JPEG, PNG, or WebP photograph.'); return; }
    if (file.size > maxBytes) { input.value = ''; setErr('image', `That photograph is over ${Math.round(maxBytes / 1048576)} MB.`); return; }
    setPreview(URL.createObjectURL(file));
  }

  input.addEventListener('change', () => pick(input.files[0]), { signal });
  ['dragenter', 'dragover'].forEach((t) => zone.addEventListener(t, (e) => { e.preventDefault(); zone.classList.add('is-over'); }, { signal }));
  ['dragleave', 'drop'].forEach((t) => zone.addEventListener(t, (e) => { e.preventDefault(); zone.classList.remove('is-over'); }, { signal }));
  zone.addEventListener('drop', (e) => { if (e.dataTransfer.files.length) { input.files = e.dataTransfer.files; pick(input.files[0]); } }, { signal });
  f.comment.addEventListener('input', count, { signal });
  f.title.addEventListener('input', () => { setErr('title', ''); f.title.removeAttribute('aria-invalid'); }, { signal });

  async function finish(result) {
    discard.hidden = true;
    await closeDialog(dlg);
    setPreview(null); input.value = '';
    const done = settle; settle = null; current = null;
    if (done) done(result);
  }
  function requestClose() {
    if (busy) return;
    if (isDirty()) { discard.hidden = false; root.querySelector('#keep-editing').focus(); return; }
    finish(null);
  }
  dlg.querySelectorAll('[data-editor-close]').forEach((b) => b.addEventListener('click', requestClose, { signal }));
  onDismiss(dlg, requestClose, signal);
  root.querySelector('#keep-editing').addEventListener('click', () => { discard.hidden = true; f.title.focus(); }, { signal });
  root.querySelector('#discard-changes').addEventListener('click', () => finish(null), { signal });
  window.addEventListener('beforeunload', (e) => { if (isDirty()) { e.preventDefault(); e.returnValue = ''; } }, { signal });

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (busy) return;
    clearErrors();
    let bad = false;
    if (!f.title.value.trim()) { setErr('title', 'Please give this memory a title.'); f.title.setAttribute('aria-invalid', 'true'); f.title.focus(); bad = true; }
    if (!current && !input.files.length) { setErr('image', 'Please choose a photograph.'); bad = true; }
    if (bad) return;

    const fd = new FormData(form);
    fd.set('favorite', f.fav.checked ? '1' : '0');
    if (current) fd.set('id', current.id);
    busy = true; saveBtn.setAttribute('aria-busy', 'true'); saveBtn.textContent = 'Saving…';
    try {
      const saved = current ? await api.updateMemory(fd) : await api.createMemory(fd);
      busy = false;
      initial = snapshot();       // nothing unsaved any more
      await finish(saved);
    } catch (ex) {
      formErr.textContent = ex.message;
      const fields = (ex.data && ex.data.fields) || {};
      Object.entries(fields).forEach(([k, v]) => setErr(k, v));
    } finally {
      busy = false; saveBtn.removeAttribute('aria-busy'); saveBtn.textContent = 'Save memory';
    }
  }, { signal });

  return {
    isDirty,
    open(memory = null) {
      current = memory;
      form.reset(); clearErrors(); discard.hidden = true;
      root.querySelector('#editor-title').textContent = memory ? 'Edit memory' : 'Add a memory';
      const list = root.querySelector('#category-list');
      list.replaceChildren(...getCategories().map((c) => Object.assign(document.createElement('option'), { value: c })));
      if (memory) {
        f.title.value = memory.title; f.comment.value = memory.comment; f.date.value = memory.memory_date;
        f.category.value = memory.category; f.fav.checked = memory.favorite;
        setPreview(imageSrc(memory)); zone.title = 'Choose a different photograph';
      } else { setPreview(null); zone.title = ''; }
      count();
      initial = snapshot();
      openDialog(dlg);
      f.title.focus();
      return new Promise((resolve) => { settle = resolve; });
    },
  };
}
