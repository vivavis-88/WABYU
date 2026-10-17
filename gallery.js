import * as api from './api.js';
import { imageSrc } from './api.js';
import { store, subscribe, setAll, upsert, remove, getById, setVisible } from './store.js';
import { el, icon, toast, sleep, formatDate } from './ui.js';
import { initEditor } from './memory-editor.js';
import { initViewer } from './image-viewer.js';
import { initDeleteDialog } from './delete-confirmation.js';

export function initGallery(root, { onLogout }) {
  const ac = new AbortController();
  const { signal } = ac;
  const $ = (s) => root.querySelector(s);
  const cardsEl = $('#cards'), statusEl = $('#status'), emptyEl = $('#empty-state'), noResEl = $('#no-results');
  const toolbar = $('.toolbar'), search = $('#search'), catSel = $('#category-filter');
  const tabs = [...root.querySelectorAll('.tab')];
  const nodes = new Map();
  const state = { view: 'all', query: '', category: '' };

  const categories = () => [...new Set(store.memories.map((m) => m.category).filter(Boolean))].sort((a, b) => a.localeCompare(b));

  /* ---------- dialogs ---------- */
  const confirmDelete = initDeleteDialog(root, signal);
  const editor = initEditor(root, signal, { getCategories: categories });

  async function editMemory(id) {
    const m = getById(id);
    if (!m) return;
    const saved = await editor.open(m);
    if (saved) { upsert(saved); toast('Changes saved'); }
  }
  async function addMemory() {
    const saved = await editor.open(null);
    if (saved) { upsert(saved); toast('Memory saved'); }
  }
  async function deleteMemory(id) {
    const m = getById(id);
    if (!m) return;
    const res = await confirmDelete(m);        // every deletion goes through here
    if (!res) return;
    const node = nodes.get(id);
    if (node) { node.classList.add('is-removing'); await sleep(300); }
    remove(id);
    toast(res.note || 'Memory deleted', res.note ? 6000 : 3400);
  }
  const viewer = initViewer(root, signal, {
    onEdit: editMemory,
    onDelete: deleteMemory,
    onClose: (id) => { const n = id && nodes.get(id); if (n) { n.scrollIntoView({ block: 'nearest', inline: 'center' }); n.querySelector('.card-media').focus({ preventScroll: true }); } },
  });

  /* ---------- cards ---------- */
  function buildCard(m) {
    const img = el('img', { alt: '', loading: 'lazy', decoding: 'async' });
    img.addEventListener('load', () => img.classList.add('is-loaded'));
    img.addEventListener('error', () => img.classList.add('is-loaded'));
    const media = el('button', { class: 'card-media', type: 'button', 'data-act': 'view' }, img);
    const meta = el('div', { class: 'card-meta' });
    const title = el('h3', { class: 'card-title' });
    const comment = el('p', { class: 'card-comment' });
    const fav = el('button', { class: 'tool', type: 'button', 'data-act': 'fav' }, icon('heart'));
    const edit = el('button', { class: 'tool', type: 'button', 'data-act': 'edit' }, icon('edit'));
    const del = el('button', { class: 'tool', type: 'button', 'data-act': 'delete' }, icon('trash'));
    const go = el('button', { class: 'card-go', type: 'button', 'data-act': 'view' }, icon('arrow'));
    const node = el('article', { class: 'card', role: 'listitem', 'data-id': m.id },
      media,
      el('div', { class: 'card-body' }, meta, title, comment,
        el('div', { class: 'card-actions' }, el('div', { class: 'card-tools' }, fav, edit, del), go)));
    node._p = { img, media, meta, title, comment, fav, edit, del, go };
    fillCard(node, m);
    node.addEventListener('animationend', (e) => { if (e.animationName === 'card-in') node.classList.remove('is-entering'); });
    return node;
  }

  function fillCard(node, m) {
    const p = node._p;
    const src = imageSrc(m);
    if (p.img.getAttribute('src') !== src) { p.img.classList.remove('is-loaded'); p.img.src = src; }
    p.img.alt = '';
    p.title.textContent = m.title;
    p.comment.textContent = m.comment;
    p.meta.replaceChildren(...[m.category, formatDate(m.memory_date)].filter(Boolean).map((t) => el('span', { text: t })));
    p.media.setAttribute('aria-label', `View ${m.title}`);
    p.go.setAttribute('aria-label', `Open ${m.title}`);
    p.edit.setAttribute('aria-label', `Edit ${m.title}`);
    p.del.setAttribute('aria-label', `Delete ${m.title}`);
    p.fav.setAttribute('aria-label', m.favorite ? `Remove ${m.title} from favorites` : `Add ${m.title} to favorites`);
    p.fav.setAttribute('aria-pressed', String(m.favorite));
    node.dataset.v = m.updated_at;
  }

  /* ---------- filtering / rendering ---------- */
  function compute() {
    let list = store.memories;
    if (state.view === 'favorites') list = list.filter((m) => m.favorite);
    if (state.view === 'recent') list = list.slice(0, 12);
    if (state.category) list = list.filter((m) => m.category === state.category);
    const q = state.query.trim().toLowerCase();
    if (q) list = list.filter((m) => `${m.title}\n${m.comment}\n${m.category}`.toLowerCase().includes(q));
    return list;
  }

  function syncCategories() {
    const cats = categories();
    if (state.category && !cats.includes(state.category)) state.category = '';
    catSel.replaceChildren(el('option', { value: '', text: 'All categories' }), ...cats.map((c) => el('option', { value: c, text: c })));
    catSel.value = state.category;
    catSel.hidden = cats.length === 0;
  }

  function render() {
    syncCategories();
    const list = compute();
    setVisible(list.map((m) => m.id));
    const total = store.memories.length;
    toolbar.hidden = total === 0;
    emptyEl.hidden = !(store.loaded && total === 0);
    noResEl.hidden = !(total > 0 && list.length === 0);
    cardsEl.hidden = list.length === 0;
    cardsEl.setAttribute('aria-busy', 'false');
    statusEl.textContent = total === 0 ? '' : `${list.length} of ${total} ${total === 1 ? 'memory' : 'memories'}`;

    const keep = new Set(list.map((m) => m.id));
    for (const [id, node] of nodes) if (!keep.has(id)) { node.remove(); nodes.delete(id); }
    let prev = null, i = 0;
    for (const m of list) {
      let node = nodes.get(m.id);
      if (!node) { node = buildCard(m); node.classList.add('is-entering'); node.style.setProperty('--i', String(Math.min(i, 10))); nodes.set(m.id, node); }
      else if (node.dataset.v !== m.updated_at) fillCard(node, m);
      const expected = prev ? prev.nextSibling : cardsEl.firstChild;
      if (node !== expected) cardsEl.insertBefore(node, expected);
      prev = node; i++;
    }
  }
  const unsub = subscribe(render);
  signal.addEventListener('abort', unsub);

  async function load() {
    cardsEl.setAttribute('aria-busy', 'true');
    statusEl.textContent = 'Loading your memories…';
    try {
      setAll(await api.listMemories());
    } catch (ex) {
      statusEl.replaceChildren(`${ex.message} `, el('button', { class: 'btn', type: 'button', text: 'Try again', id: 'retry-load' }));
      root.querySelector('#retry-load').addEventListener('click', load, { once: true, signal });
    }
  }

  /* ---------- events ---------- */
  let down = null;
  cardsEl.addEventListener('pointerdown', (e) => { down = { x: e.clientX, y: e.clientY }; }, { signal });
  cardsEl.addEventListener('click', async (e) => {
    const btn = e.target.closest('[data-act]');
    if (!btn) return;
    // A swipe must never trigger an action.
    if (down && Math.hypot(e.clientX - down.x, e.clientY - down.y) > 10) { e.preventDefault(); return; }
    const id = btn.closest('.card').dataset.id;
    const act = btn.dataset.act;
    if (act === 'view') viewer.open(id);
    else if (act === 'edit') editMemory(id);
    else if (act === 'delete') deleteMemory(id);
    else if (act === 'fav') {
      const m = getById(id);
      if (!m || btn.disabled) return;
      btn.disabled = true;
      try {
        const fd = new FormData(); fd.set('id', id); fd.set('favorite', m.favorite ? '0' : '1');
        upsert(await api.updateMemory(fd));
      } catch (ex) { toast(ex.message); } finally { btn.disabled = false; }
    }
  }, { signal });

  tabs.forEach((t) => t.addEventListener('click', () => {
    state.view = t.dataset.view;
    tabs.forEach((x) => x.setAttribute('aria-pressed', String(x === t)));
    render();
  }, { signal }));

  let timer = 0;
  search.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(() => { state.query = search.value; render(); }, 120); }, { signal });
  catSel.addEventListener('change', () => { state.category = catSel.value; render(); }, { signal });
  $('#clear-filters').addEventListener('click', () => {
    state.query = ''; state.category = ''; state.view = 'all'; search.value = '';
    tabs.forEach((x) => x.setAttribute('aria-pressed', String(x.dataset.view === 'all')));
    render();
  }, { signal });
  $('#add-memory').addEventListener('click', addMemory, { signal });
  $('#empty-add').addEventListener('click', addMemory, { signal });

  /* account menu */
  const menuBtn = $('#account-btn'), menu = $('#account-menu');
  const setMenu = (open) => { menu.hidden = !open; menuBtn.setAttribute('aria-expanded', String(open)); };
  menuBtn.addEventListener('click', (e) => { e.stopPropagation(); setMenu(menu.hidden); if (!menu.hidden) menu.querySelector('.menu-item').focus(); }, { signal });
  document.addEventListener('click', (e) => { if (!menu.contains(e.target)) setMenu(false); }, { signal });
  menu.addEventListener('keydown', (e) => { if (e.key === 'Escape') { setMenu(false); menuBtn.focus(); } }, { signal });
  $('#logout-btn').addEventListener('click', () => { setMenu(false); onLogout(); }, { signal });

  load();
  return { destroy: () => ac.abort(), reveal() { root.querySelector('.hero').classList.add('reveal'); } };
}
