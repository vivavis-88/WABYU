import * as api from './api.js';
import { imageSrc } from './api.js';
import { openDialog, closeDialog, onDismiss, shake, bindVisibilityToggle } from './ui.js';

/** Returns confirmDelete(memory) -> Promise<result|null>. null means cancelled; nothing was deleted. */
export function initDeleteDialog(root, signal) {
  const dlg = root.querySelector('#delete-dialog');
  const form = root.querySelector('#delete-form');
  const pw = root.querySelector('#delete-password');
  const err = root.querySelector('#delete-error');
  const confirmBtn = root.querySelector('#delete-confirm');
  const cancelBtn = root.querySelector('#delete-cancel');
  let busy = false, target = null, settle = null;

  bindVisibilityToggle(root.querySelector('#delete-toggle'), pw, signal);
  pw.addEventListener('input', () => { err.textContent = ''; pw.removeAttribute('aria-invalid'); }, { signal });

  async function finish(result) {
    pw.value = ''; pw.type = 'password';              // never keep the password around
    root.querySelector('#delete-toggle').setAttribute('aria-pressed', 'false');
    await closeDialog(dlg);
    const done = settle; settle = null; target = null;
    if (done) done(result);
  }
  const cancel = () => { if (!busy) finish(null); };

  cancelBtn.addEventListener('click', cancel, { signal });
  onDismiss(dlg, cancel, signal);

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (busy || !target) return;
    if (!pw.value) { err.textContent = 'Please enter the confirmation password.'; pw.setAttribute('aria-invalid', 'true'); shake(dlg); pw.focus(); return; }
    busy = true; confirmBtn.setAttribute('aria-busy', 'true'); confirmBtn.textContent = 'Deleting…'; cancelBtn.disabled = true;
    try {
      const res = await api.deleteMemory(target.id, pw.value);
      busy = false;
      await finish({ id: target.id, note: res.note || null });
    } catch (ex) {
      if (ex.status === 404 && ex.data && ex.data.code === 'not_found') { busy = false; await finish({ id: target.id, note: 'That memory was already removed.' }); }
      else {
        err.textContent = ex.message;
        pw.setAttribute('aria-invalid', 'true');
        shake(dlg); pw.focus(); pw.select();
      }
    } finally {
      busy = false; confirmBtn.removeAttribute('aria-busy'); confirmBtn.textContent = 'Confirm deletion'; cancelBtn.disabled = false;
    }
  }, { signal });

  return function confirmDelete(memory) {
    target = memory;
    root.querySelector('#delete-name').textContent = memory.title;
    root.querySelector('#delete-thumb').src = imageSrc(memory);
    pw.value = ''; err.textContent = '';
    openDialog(dlg);
    pw.focus();
    return new Promise((resolve) => { settle = resolve; });
  };
}
