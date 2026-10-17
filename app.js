import * as api from './api.js';
import { setCsrf } from './api.js';
import { startParticles } from './particles.js';
import { initLogin } from './auth.js';
import { initGallery } from './gallery.js';
import { sleep, toast } from './ui.js';

const $ = (id) => document.getElementById(id);
const loginView = $('login-view'), intro = $('intro'), appEl = $('app');
let gallery = null;

startParticles($('particles'));

const login = initLogin({ onSuccess: playIntroThenEnter });

function mountGallery() {
  gallery = initGallery(appEl, { onLogout: logout });
  gallery.reveal();
}

function showApp() {
  appEl.hidden = false;
  requestAnimationFrame(() => requestAnimationFrame(() => appEl.classList.add('is-visible')));
}

async function playIntroThenEnter() {
  const fragment = api.loadGalleryFragment().then((html) => ({ html }), (error) => ({ error }));

  loginView.classList.add('is-leaving');
  await sleep(450);
  loginView.hidden = true;
  loginView.classList.remove('is-leaving');

  // Scene 1: WABYU
  const brand = $('intro-brand'), cont = $('intro-continue');
  brand.replaceChildren(...[...'WABYU'].map((c, n) => {
    const s = Object.assign(document.createElement('span'), { className: 'ch', textContent: c });
    s.setAttribute('aria-hidden', 'true'); s.style.setProperty('--n', String(n));
    return s;
  }));
  brand.classList.remove('is-away'); cont.hidden = true; cont.classList.remove('is-in');
  intro.classList.remove('is-leaving'); intro.hidden = false;
  await sleep(2200);

  // Scene 2: Enjoy the Album
  brand.classList.add('is-away');
  await sleep(450);
  brand.hidden = true;
  cont.hidden = false; cont.classList.add('is-in');
  const btn = $('intro-btn');
  btn.focus();
  await new Promise((resolve) => {
    const go = () => { intro.removeEventListener('click', go); resolve(); };
    intro.addEventListener('click', go);           // click anywhere, tap, or Enter / Space on the button
  });

  // Scene 3: into the gallery
  const res = await fragment;
  intro.classList.add('is-leaving');
  await sleep(500);
  intro.hidden = true; brand.hidden = false;
  if (res.error) { toast(res.error.message); loginView.hidden = false; loginView.classList.add('is-entering'); return; }
  appEl.replaceChildren(...new DOMParser().parseFromString(res.html, 'text/html').body.childNodes);
  showApp();
  mountGallery();
}

async function logout() {
  try {
    const res = await api.logout();
    setCsrf(res.csrf);
  } catch (ex) { toast(ex.message); return; }
  appEl.classList.remove('is-visible');
  appEl.classList.add('is-leaving');
  await sleep(400);
  if (gallery) gallery.destroy();
  gallery = null;
  appEl.replaceChildren(); appEl.hidden = true; appEl.classList.remove('is-leaving');
  login.reset();
  loginView.hidden = false;
  loginView.classList.add('is-entering');
  login.focus();
}

window.addEventListener('wabyu:unauthorized', () => { if (gallery) location.reload(); });

if (document.body.dataset.auth === '1') {
  showApp();
  mountGallery();
} else {
  login.focus();
}
