/**
 * Manor Cares — shared dashboard API client
 *
 * Thin fetch wrapper: always sends cookies, always attaches the CSRF token
 * (fetched once from /php/auth-me.php) to state-changing requests, and
 * redirects to the sign-in page on 401 so an expired/forged session never
 * silently fails.
 */

let csrfToken = '';

export function setCsrfToken(token) {
  csrfToken = token || '';
}

export async function apiGet(url) {
  const res = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
  return handleResponse(res);
}

export async function apiPost(url, data = {}) {
  const body = new FormData();
  Object.entries(data).forEach(([key, value]) => body.append(key, value ?? ''));

  const res = await fetch(url, {
    method: 'POST',
    credentials: 'same-origin',
    headers: { Accept: 'application/json', 'X-CSRF-Token': csrfToken },
    body,
  });
  return handleResponse(res);
}

async function handleResponse(res) {
  let data;
  try {
    data = await res.json();
  } catch (e) {
    data = { success: false, message: 'Unexpected server response.' };
  }

  if (res.status === 401) {
    window.location.href = 'create-account.html?mode=signin';
    return data;
  }

  return data;
}

export function toast(message, isError = false) {
  const el = document.getElementById('dashToast');
  if (!el) return;
  el.textContent = message;
  el.classList.toggle('error', isError);
  el.classList.add('show');
  setTimeout(() => el.classList.remove('show'), 4000);
}

export function escapeHtml(str) {
  return String(str ?? '').replace(/[&<>"']/g, (c) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
  }[c]));
}

export function formatDate(value) {
  if (!value) return '—';
  const d = new Date(value);
  if (Number.isNaN(d.getTime())) return value;
  return d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
}

export function initSidebarNav() {
  const navButtons = document.querySelectorAll('.dash-nav-item[data-view]');
  const views = document.querySelectorAll('.dash-view');

  function activate(viewName) {
    navButtons.forEach((btn) => btn.classList.toggle('active', btn.dataset.view === viewName));
    views.forEach((view) => view.classList.toggle('active', view.id === `view-${viewName}`));
  }

  navButtons.forEach((btn) => btn.addEventListener('click', () => activate(btn.dataset.view)));
  document.querySelectorAll('[data-view-link]').forEach((btn) => {
    btn.addEventListener('click', () => activate(btn.dataset.viewLink));
  });
}

export function initLogout(buttonId = 'logoutBtn') {
  const btn = document.getElementById(buttonId);
  if (!btn) return;
  btn.addEventListener('click', async () => {
    await apiPost('php/auth-logout.php');
    window.location.href = 'index.html';
  });
}
