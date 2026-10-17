// All server communication. The CSRF token travels in a header on every request.
const meta = document.querySelector('meta[name="csrf-token"]');
const base = document.body.dataset.base || '';
let csrf = meta ? meta.content : '';

export const setCsrf = (t) => { csrf = t; if (meta) meta.content = t; };
export const url = (path) => `${base}/${path}`;
export const imageSrc = (m) => url(m.image_url);

export class ApiError extends Error {
  constructor(message, status = 0, data = null) { super(message); this.status = status; this.data = data; }
}

async function request(path, { method = 'GET', json, form } = {}) {
  const headers = { Accept: 'application/json', 'X-CSRF-Token': csrf };
  let body;
  if (form) body = form;
  else if (json) { headers['Content-Type'] = 'application/json'; body = JSON.stringify(json); }
  let res;
  try {
    res = await fetch(url(path), { method, headers, body, credentials: 'same-origin' });
  } catch {
    throw new ApiError('Could not reach the server. Check that Apache is running, then try again.');
  }
  let data = null;
  try { data = await res.json(); } catch { /* non-JSON error page */ }
  if (res.status === 401 && data && data.auth === false) window.dispatchEvent(new CustomEvent('wabyu:unauthorized'));
  if (!res.ok || !data || data.ok === false) {
    throw new ApiError((data && data.error) || 'Something went wrong. Please try again.', res.status, data);
  }
  return data;
}

export const login = (password) => request('login.php', { method: 'POST', json: { password } });
export const logout = () => request('logout.php', { method: 'POST', json: {} });
export const listMemories = async () => (await request('api/memories-list.php')).memories;
export const createMemory = async (form) => (await request('api/memory-create.php', { method: 'POST', form })).memory;
export const updateMemory = async (form) => (await request('api/memory-update.php', { method: 'POST', form })).memory;
export const deleteMemory = (id, password) => request('api/memory-delete.php', { method: 'POST', json: { id, password } });

export async function loadGalleryFragment() {
  const res = await fetch(url('gallery.php?fragment=1'), { credentials: 'same-origin' });
  if (!res.ok) throw new ApiError('Could not open the album. Please try again.', res.status);
  return res.text();
}
