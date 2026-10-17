// Small shared helpers: DOM building, icons, toasts, dialogs. User text is only ever set via textContent.
export const reduced = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;
export const sleep = (ms) => new Promise((r) => setTimeout(r, reduced() ? 0 : ms));

export function el(tag, attrs = {}, ...kids) {
  const node = document.createElement(tag);
  for (const [k, v] of Object.entries(attrs)) {
    if (v === false || v == null) continue;
    if (k === 'class') node.className = v;
    else if (k === 'text') node.textContent = v;
    else node.setAttribute(k, v === true ? '' : v);
  }
  for (const kid of kids.flat()) if (kid != null) node.append(kid);
  return node;
}

const ICONS = {
  heart: ['M12 20s-7-4.4-7-10a4 4 0 0 1 7-2.5A4 4 0 0 1 19 10c0 5.6-7 10-7 10z'],
  edit: ['M4 20h4L19 9l-4-4L4 16v4z', 'M13.5 6.5l4 4'],
  trash: ['M5 7h14M10 7V5h4v2M7 7l1 12h8l1-12', 'M10 11v5M14 11v5'],
  arrow: ['M5 12h14M13 6l6 6-6 6'],
};
export function icon(name, size = 18) {
  const ns = 'http://www.w3.org/2000/svg';
  const svg = document.createElementNS(ns, 'svg');
  svg.setAttribute('viewBox', '0 0 24 24');
  svg.setAttribute('width', size);
  svg.setAttribute('height', size);
  svg.setAttribute('aria-hidden', 'true');
  for (const d of ICONS[name]) {
    const p = document.createElementNS(ns, 'path');
    p.setAttribute('d', d);
    svg.append(p);
  }
  return svg;
}

export function toast(message, ms = 3400) {
  let host = document.getElementById('toasts');
  if (!host) { host = el('div', { class: 'toasts', id: 'toasts', role: 'status', 'aria-live': 'polite' }); document.body.append(host); }
  const t = el('div', { class: 'toast', text: message });
  host.append(t);
  setTimeout(() => { t.classList.add('is-leaving'); setTimeout(() => t.remove(), reduced() ? 0 : 230); }, ms);
}

export function openDialog(dlg) {
  dlg.classList.remove('is-closing');
  if (!dlg.open) dlg.showModal();
  document.body.classList.add('is-locked');
}

export async function closeDialog(dlg) {
  if (!dlg.open) return;
  dlg.classList.add('is-closing');
  await sleep(200);
  dlg.close();
  dlg.classList.remove('is-closing');
  if (!document.querySelector('dialog[open]')) document.body.classList.remove('is-locked');
}

/** Escape key: run our own (animated, possibly guarded) close instead of the browser's instant one. */
export function onDismiss(dlg, fn, signal) {
  dlg.addEventListener('cancel', (e) => { e.preventDefault(); fn(); }, { signal });
}

export function shake(node) {
  node.classList.remove('shake');
  void node.offsetWidth;
  node.classList.add('shake');
}

const fmt = new Intl.DateTimeFormat(undefined, { year: 'numeric', month: 'long', day: 'numeric' });
export function formatDate(ymdOrIso) {
  if (!ymdOrIso) return '';
  const d = /^\d{4}-\d{2}-\d{2}$/.test(ymdOrIso) ? new Date(ymdOrIso + 'T00:00:00') : new Date(ymdOrIso);
  return Number.isNaN(d.getTime()) ? '' : fmt.format(d);
}

export function bindVisibilityToggle(btn, input, signal) {
  btn.addEventListener('click', () => {
    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    btn.setAttribute('aria-pressed', String(show));
    btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    input.focus();
  }, { signal });
}
