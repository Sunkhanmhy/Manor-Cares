/**
 * Manor Cares — user dashboard
 */
import { apiGet, apiPost, setCsrfToken, toast, escapeHtml, formatDate, initSidebarNav, initLogout } from './api.js';

let currentUser = null;

async function loadMe() {
  const data = await apiGet('php/auth-me.php');
  if (!data.success) return null;
  setCsrfToken(data.csrf_token);
  return data.user;
}

function renderUser(user) {
  document.getElementById('welcomeHeading').textContent = `Welcome back, ${user.name.split(' ')[0]}`;
  document.getElementById('userName').textContent = user.name;
  document.getElementById('userPlan').textContent = `${capitalize(user.plan)} Plan`;
  document.getElementById('userAvatar').innerHTML = user.avatar_url
    ? `<img src="${escapeHtml(user.avatar_url)}" alt="${escapeHtml(user.name)}">`
    : `<span>${escapeHtml(user.name.charAt(0).toUpperCase())}</span>`;
  document.getElementById('statPlan').textContent = capitalize(user.plan);
  document.getElementById('statMemberSince').textContent = formatDate(user.created_at);

  document.getElementById('pfName').value = user.name || '';
  document.getElementById('pfEmail').value = user.email || '';
  document.getElementById('pfPhone').value = user.phone || '';
  document.getElementById('pfAddress').value = user.address || '';
}

function capitalize(str) {
  return str ? str.charAt(0).toUpperCase() + str.slice(1) : '';
}

function bookingRow(b, withActions) {
  const actions = withActions
    ? `<td><button class="icon-btn danger cancel-booking" data-id="${b.id}" ${['pending', 'confirmed'].includes(b.status) ? '' : 'disabled'} title="Cancel"><i class="fa-solid fa-xmark"></i></button></td>`
    : '';
  const propCol = withActions ? `<td>${escapeHtml(b.property_type || '—')}</td>` : '';
  return `<tr>
    <td>${escapeHtml(b.service_type)}</td>
    ${propCol}
    <td>${escapeHtml(b.address)}</td>
    <td>${formatDate(b.preferred_date)}</td>
    <td><span class="badge badge-${b.status}">${b.status}</span></td>
    ${actions}
  </tr>`;
}

async function loadBookings() {
  const data = await apiGet('php/user-bookings.php');
  const bookings = data.success ? data.bookings : [];

  const recentBody = document.getElementById('recentBookingsBody');
  const allBody = document.getElementById('allBookingsBody');

  recentBody.innerHTML = bookings.length
    ? bookings.slice(0, 5).map((b) => bookingRow(b, false)).join('')
    : `<tr><td colspan="4" class="dash-empty">No bookings yet — request your first cleaning!</td></tr>`;

  allBody.innerHTML = bookings.length
    ? bookings.map((b) => bookingRow(b, true)).join('')
    : `<tr><td colspan="6" class="dash-empty">No bookings yet.</td></tr>`;

  document.getElementById('statTotalBookings').textContent = bookings.length;
  document.getElementById('statPending').textContent = bookings.filter((b) => b.status === 'pending').length;

  allBody.querySelectorAll('.cancel-booking').forEach((btn) => {
    btn.addEventListener('click', async () => {
      if (btn.disabled) return;
      if (!confirm('Cancel this booking request?')) return;
      const res = await apiPost('php/user-booking-cancel.php', { id: btn.dataset.id });
      toast(res.message, !res.success);
      if (res.success) loadBookings();
    });
  });
}

function bindForms() {
  document.getElementById('newBookingForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const form = e.target;
    const successEl = form.querySelector('.form-success');
    const data = Object.fromEntries(new FormData(form).entries());
    const res = await apiPost('php/user-bookings.php', data);
    successEl.textContent = res.message;
    successEl.classList.toggle('form-error', !res.success);
    successEl.classList.add('show');
    if (res.success) {
      form.reset();
      loadBookings();
    }
  });

  document.getElementById('profileForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const form = e.target;
    const successEl = form.querySelector('.form-success');
    const data = Object.fromEntries(new FormData(form).entries());
    const res = await apiPost('php/user-profile-update.php', data);
    successEl.textContent = res.message;
    successEl.classList.toggle('form-error', !res.success);
    successEl.classList.add('show');
    if (res.success) {
      document.getElementById('userName').textContent = data.name;
      document.getElementById('welcomeHeading').textContent = `Welcome back, ${data.name.split(' ')[0]}`;
    }
  });

  document.getElementById('passwordForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const form = e.target;
    const successEl = form.querySelector('.form-success');
    const data = Object.fromEntries(new FormData(form).entries());
    const res = await apiPost('php/user-password-change.php', data);
    successEl.textContent = res.message;
    successEl.classList.toggle('form-error', !res.success);
    successEl.classList.add('show');
    if (res.success) form.reset();
  });
}

async function init() {
  initSidebarNav();
  initLogout();
  bindForms();

  currentUser = await loadMe();
  if (!currentUser) {
    window.location.href = 'create-account.html?mode=signin';
    return;
  }

  if (currentUser.role === 'admin') {
    window.location.href = 'admin-dashboard.html';
    return;
  }

  renderUser(currentUser);
  await loadBookings();

  document.getElementById('dashLoading').style.display = 'none';
  document.getElementById('dashContent').style.display = '';
}

document.addEventListener('DOMContentLoaded', init);
