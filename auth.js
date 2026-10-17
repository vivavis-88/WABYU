import * as api from './api.js';
import { setCsrf } from './api.js';
import { bindVisibilityToggle, shake } from './ui.js';

export function initLogin({ onSuccess }) {
  const form = document.getElementById('login-form');
  const input = document.getElementById('password');
  const err = document.getElementById('login-error');
  const btn = document.getElementById('login-submit');
  const panel = document.getElementById('login-panel');
  let busy = false;

  bindVisibilityToggle(document.getElementById('login-toggle'), input);
  input.addEventListener('input', () => { err.textContent = ''; input.removeAttribute('aria-invalid'); });

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (busy) return;
    if (!input.value) { err.textContent = 'Please enter your password.'; input.setAttribute('aria-invalid', 'true'); shake(panel); input.focus(); return; }
    busy = true; btn.setAttribute('aria-busy', 'true'); btn.textContent = 'Checking…';
    try {
      const res = await api.login(input.value);
      setCsrf(res.csrf);
      input.value = '';
      await onSuccess();
    } catch (ex) {
      err.textContent = ex.message;
      input.setAttribute('aria-invalid', 'true');
      shake(panel); input.focus(); input.select();
    } finally {
      busy = false; btn.removeAttribute('aria-busy'); btn.textContent = 'Enter';
    }
  });

  return {
    reset() {
      form.reset(); err.textContent = ''; input.type = 'password';
      document.getElementById('login-toggle').setAttribute('aria-pressed', 'false');
    },
    focus: () => input.focus(),
  };
}
