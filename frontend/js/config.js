// ============================================================
// frontend/js/config.js  — Central config & shared utilities
// ============================================================

const API_BASE   = 'https://parthtripathi.xo.je/backend/api';
const STORAGE_KEY = 'attendai_user';

// ── Auth helpers ─────────────────────────────────────────────
function getUser() {
  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    if (!raw) return null;
    const u = JSON.parse(raw);
    if (!u || !u.id || !u.role) return null;
    return u;
  } catch { return null; }
}
function setUser(obj) { localStorage.setItem(STORAGE_KEY, JSON.stringify(obj)); }
function clearUser()  { localStorage.removeItem(STORAGE_KEY); }

function requireLogin() {
  const user = getUser();
  if (!user) {
    window.location.replace('/attendance-system/frontend/index.html');
    throw new Error('Not authenticated');
  }
  return user;
}

async function doLogout() {
  try { await fetch(`${API_BASE}/auth.php`, { method: 'DELETE' }); } catch(_) {}
  clearUser();
  window.location.replace('/attendance-system/frontend/index.html');
}

// ── Navbar initialise ─────────────────────────────────────────
function initNav(activePage) {
  const user = getUser();
  if (!user) return;
  const nameEl = document.getElementById('nav-name');
  const roleEl = document.getElementById('nav-role');
  const avatarEl = document.getElementById('nav-avatar');
  const adminLink = document.getElementById('admin-link');
  if (nameEl) nameEl.textContent = user.name;
  if (roleEl) { roleEl.textContent = user.role; roleEl.className = 'nav-badge ' + user.role; }
  if (avatarEl) avatarEl.textContent = user.name.charAt(0).toUpperCase();
  if (adminLink) adminLink.style.display = user.role === 'admin' ? '' : 'none';
  // Set active link
  document.querySelectorAll('.nav-links a').forEach(a => {
    a.classList.toggle('active', a.dataset.page === activePage);
  });
}

// ── Toast notifications ───────────────────────────────────────
function getToastContainer() {
  let el = document.getElementById('toast-container');
  if (!el) {
    el = document.createElement('div');
    el.id = 'toast-container';
    el.className = 'toast-container';
    document.body.appendChild(el);
  }
  return el;
}
function showToast(message, type = 'default', duration = 3000) {
  const container = getToastContainer();
  const toast = document.createElement('div');
  const icons = { success: '✓', error: '✕', default: 'ℹ' };
  toast.className = `toast ${type}`;
  toast.innerHTML = `<span style="font-size:16px">${icons[type]||'ℹ'}</span><span>${escapeHtml(message)}</span>`;
  container.appendChild(toast);
  setTimeout(() => {
    toast.style.animation = 'toastOut 0.4s ease forwards';
    setTimeout(() => toast.remove(), 400);
  }, duration);
}

// ── Alert box ─────────────────────────────────────────────────
function showAlert(containerId, message, type = 'error') {
  const el = document.getElementById(containerId);
  if (!el) return;
  const icons = { success:'✓', error:'✕', info:'ℹ', warning:'⚠' };
  el.innerHTML = `<div class="alert alert-${type}"><span>${icons[type]||'ℹ'}</span><span>${escapeHtml(message)}</span></div>`;
  el.scrollIntoView({ behavior:'smooth', block:'nearest' });
}
function clearAlert(containerId) {
  const el = document.getElementById(containerId);
  if (el) el.innerHTML = '';
}

// ── Ripple effect on buttons ──────────────────────────────────
function addRipple(e) {
  const btn = e.currentTarget;
  const rect = btn.getBoundingClientRect();
  const size = Math.max(rect.width, rect.height) * 2;
  const x = e.clientX - rect.left - size / 2;
  const y = e.clientY - rect.top  - size / 2;
  const span = document.createElement('span');
  span.className = 'btn-ripple';
  span.style.cssText = `width:${size}px;height:${size}px;left:${x}px;top:${y}px`;
  btn.appendChild(span);
  span.addEventListener('animationend', () => span.remove());
}
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.btn').forEach(btn => btn.addEventListener('click', addRipple));
});

// ── Utilities ─────────────────────────────────────────────────
function escapeHtml(str) {
  const d = document.createElement('div');
  d.appendChild(document.createTextNode(String(str ?? '')));
  return d.innerHTML;
}
function todayStr() { return new Date().toISOString().split('T')[0]; }
function formatDate(ds) {
  if (!ds) return '—';
  return new Date(ds + 'T00:00:00').toLocaleDateString('en-IN',
    { day:'numeric', month:'short', year:'numeric' });
}
function formatTime(ts) {
  if (!ts) return '—';
  return new Date(ts).toLocaleTimeString('en-IN', { hour:'2-digit', minute:'2-digit' });
}
function sleep(ms) { return new Promise(r => setTimeout(r, ms)); }
