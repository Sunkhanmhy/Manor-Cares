/**
 * Manor Cares — admin dashboard
 */
import { apiGet, apiPost, setCsrfToken, toast, escapeHtml, formatDate, initSidebarNav, initLogout } from './api.js';

async function loadMe() {
  const data = await apiGet('php/auth-me.php');
  if (!data.success) return null;
  setCsrfToken(data.csrf_token);
  return data.user;
}

async function loadStats() {
  const data = await apiGet('php/admin-stats.php');
  if (!data.success) return;

  document.getElementById('statTotalUsers').textContent = data.users.total_users;
  document.getElementById('statActiveUsers').textContent = data.users.active_users;
  document.getElementById('statSuspendedUsers').textContent = data.users.suspended_users;
  document.getElementById('statAdminUsers').textContent = data.users.admin_users;

  document.getElementById('statPendingBookings').textContent = data.bookings.pending;
  document.getElementById('statConfirmedBookings').textContent = data.bookings.confirmed;
  document.getElementById('statCompletedBookings').textContent = data.bookings.completed;
  document.getElementById('statCancelledBookings').textContent = data.bookings.cancelled;

  const max = Math.max(1, ...data.growth.map((g) => Number(g.count)));
  document.getElementById('growthChart').innerHTML = data.growth.length
    ? data.growth.map((g) => `
      <div class="bar-wrap">
        <span class="val">${g.count}</span>
        <div class="bar" style="height:${Math.round((Number(g.count) / max) * 100)}%"></div>
        <span class="yr">${escapeHtml(g.month)}</span>
      </div>`).join('')
    : '<p style="color:var(--text-soft);">No sign-ups yet.</p>';
}

let allUsers = [];

async function loadUsers(query = '') {
  const data = await apiGet(`php/admin-users-list.php?q=${encodeURIComponent(query)}`);
  allUsers = data.success ? data.users : [];
  renderUsers();
}

function renderUsers() {
  const body = document.getElementById('usersBody');
  body.innerHTML = allUsers.length
    ? allUsers.map((u) => `
      <tr>
        <td>${escapeHtml(u.name)}</td>
        <td>${escapeHtml(u.email)}</td>
        <td>${escapeHtml(u.plan)}</td>
        <td><span class="badge badge-${u.role}">${u.role}</span></td>
        <td><span class="badge badge-${u.status}">${u.status}</span></td>
        <td>${formatDate(u.last_login_at)}</td>
        <td class="dash-row-actions">
          <button class="icon-btn edit-user" data-id="${u.id}" title="Edit"><i class="fa-solid fa-pen"></i></button>
          <button class="icon-btn danger delete-user" data-id="${u.id}" title="Delete"><i class="fa-solid fa-trash"></i></button>
        </td>
      </tr>`).join('')
    : `<tr><td colspan="7" class="dash-empty">No users found.</td></tr>`;

  body.querySelectorAll('.edit-user').forEach((btn) => btn.addEventListener('click', () => openUserModal(btn.dataset.id)));
  body.querySelectorAll('.delete-user').forEach((btn) => btn.addEventListener('click', () => deleteUser(btn.dataset.id)));
}

function openUserModal(id) {
  const user = allUsers.find((u) => String(u.id) === String(id));
  if (!user) return;
  document.getElementById('editUserId').value = user.id;
  document.getElementById('editUserName').value = user.name;
  document.getElementById('editUserRole').value = user.role;
  document.getElementById('editUserStatus').value = user.status;
  document.getElementById('userModal').classList.add('open');
}

async function deleteUser(id) {
  if (!confirm('Delete this user account? This cannot be undone.')) return;
  const res = await apiPost('php/admin-user-delete.php', { id });
  toast(res.message, !res.success);
  if (res.success) loadUsers(document.getElementById('userSearch').value);
}

async function loadBookings() {
  const data = await apiGet('php/admin-bookings.php');
  const bookings = data.success ? data.bookings : [];
  const body = document.getElementById('bookingsBody');
  const statuses = ['pending', 'confirmed', 'completed', 'cancelled'];

  body.innerHTML = bookings.length
    ? bookings.map((b) => `
      <tr>
        <td>${escapeHtml(b.user_name)}<br><span style="color:var(--text-soft); font-size:.8rem;">${escapeHtml(b.user_email)}</span></td>
        <td>${escapeHtml(b.service_type)}</td>
        <td>${escapeHtml(b.address)}</td>
        <td>${formatDate(b.preferred_date)}</td>
        <td><span class="badge badge-${b.status}">${b.status}</span></td>
        <td>
          <select class="booking-status" data-id="${b.id}" style="padding:8px 12px; border-radius:8px; border:1px solid var(--glass-border); background:var(--glass-bg); color:var(--text-main);">
            ${statuses.map((s) => `<option value="${s}" ${s === b.status ? 'selected' : ''}>${capitalize(s)}</option>`).join('')}
          </select>
        </td>
      </tr>`).join('')
    : `<tr><td colspan="6" class="dash-empty">No bookings yet.</td></tr>`;

  body.querySelectorAll('.booking-status').forEach((select) => {
    select.addEventListener('change', async () => {
      const res = await apiPost('php/admin-bookings.php', { id: select.dataset.id, status: select.value });
      toast(res.message, !res.success);
      if (res.success) loadStats();
    });
  });
}

async function loadAudit() {
  const data = await apiGet('php/admin-audit-log.php');
  const entries = data.success ? data.entries : [];
  const body = document.getElementById('auditBody');
  body.innerHTML = entries.length
    ? entries.map((e) => `
      <tr>
        <td>${formatDate(e.created_at)}</td>
        <td>${escapeHtml(e.actor_name || '—')}</td>
        <td>${escapeHtml(e.action)}</td>
        <td>${escapeHtml(e.target_name || '—')}</td>
        <td style="max-width:260px; white-space:normal;">${escapeHtml(JSON.stringify(e.meta))}</td>
      </tr>`).join('')
    : `<tr><td colspan="5" class="dash-empty">No activity recorded yet.</td></tr>`;
}

function capitalize(str) {
  return str.charAt(0).toUpperCase() + str.slice(1);
}

function bindEvents() {
  let searchTimer;
  document.getElementById('userSearch').addEventListener('input', (e) => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => loadUsers(e.target.value), 300);
  });

  document.getElementById('closeUserModal').addEventListener('click', () => {
    document.getElementById('userModal').classList.remove('open');
  });

  document.getElementById('userEditForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const id = document.getElementById('editUserId').value;
    const role = document.getElementById('editUserRole').value;
    const status = document.getElementById('editUserStatus').value;
    const res = await apiPost('php/admin-user-update.php', { id, role, status });
    toast(res.message, !res.success);
    if (res.success) {
      document.getElementById('userModal').classList.remove('open');
      loadUsers(document.getElementById('userSearch').value);
    }
  });
}

async function init() {
  initSidebarNav();
  initLogout();
  bindEvents();

  const user = await loadMe();
  if (!user) {
    window.location.href = 'create-account.html?mode=signin';
    return;
  }
  if (user.role !== 'admin') {
    window.location.href = 'user-dashboard.html';
    return;
  }

  document.getElementById('userName').textContent = user.name;

  await Promise.all([loadStats(), loadUsers(), loadBookings(), loadAudit()]);

  document.getElementById('dashLoading').style.display = 'none';
  document.getElementById('dashContent').style.display = '';
}

document.addEventListener('DOMContentLoaded', init);
