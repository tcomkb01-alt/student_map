/**
 * app.js – Shared utilities
 * Student Home Visit Map System
 */

'use strict';

/* ── Toast notifications ──────────────────────────────────── */
function showToast(msg, type = 'success', duration = 3500) {
  let container = document.getElementById('toast-container');
  if (!container) {
    container = document.createElement('div');
    container.id = 'toast-container';
    document.body.appendChild(container);
  }

  const icons = { success: '✅', error: '❌', warning: '⚠️', info: 'ℹ️' };
  const toast = document.createElement('div');
  toast.className = `toast ${type}`;
  toast.innerHTML = `<span class="toast-icon">${icons[type] || '💬'}</span><span class="toast-msg">${msg}</span>`;
  container.appendChild(toast);

  setTimeout(() => {
    toast.style.animation = 'slideInRight .3s ease reverse';
    toast.addEventListener('animationend', () => toast.remove(), { once: true });
  }, duration);
}

/* ── Modal helpers ───────────────────────────────────────── */
function openModal(id) {
  const el = document.getElementById(id);
  if (el) el.classList.add('show');
  document.body.style.overflow = 'hidden';
}
function closeModal(id) {
  const el = document.getElementById(id);
  if (el) el.classList.remove('show');
  document.body.style.overflow = '';
}

// Close modal when clicking outside
document.addEventListener('click', function (e) {
  if (e.target.classList.contains('modal-overlay')) {
    e.target.classList.remove('show');
    document.body.style.overflow = '';
  }
});

/* ── Confirm dialog (custom) ─────────────────────────────── */
function confirmDialog(message, onConfirm) {
  const id = '_confirm_modal_' + Date.now();
  const html = `
    <div id="${id}" class="modal-overlay show">
      <div class="modal" style="max-width:380px;">
        <div class="modal-header">
          <span class="modal-title">⚠️ ยืนยันการทำรายการ</span>
        </div>
        <div class="modal-body"><p>${message}</p></div>
        <div class="modal-footer">
          <button class="btn btn-light" onclick="document.getElementById('${id}').remove();document.body.style.overflow=''">ยกเลิก</button>
          <button class="btn btn-danger" id="${id}_ok">ยืนยัน</button>
        </div>
      </div>
    </div>`;
  document.body.insertAdjacentHTML('beforeend', html);
  document.getElementById(`${id}_ok`).onclick = function () {
    document.getElementById(id).remove();
    document.body.style.overflow = '';
    onConfirm();
  };
}

/* ── Fetch wrapper ───────────────────────────────────────── */
async function apiFetch(url, data = null, method = 'POST') {
  const opts = { method, headers: {} };
  if (data instanceof FormData) {
    opts.body = data;
  } else if (data) {
    opts.headers['Content-Type'] = 'application/json';
    opts.body = JSON.stringify(data);
  }
  const res  = await fetch(url, opts);
  const json = await res.json();
  return json;
}

/* ── Format helpers ──────────────────────────────────────── */
function formatPhone(p) {
  if (!p) return '–';
  const d = p.replace(/\D/g, '');
  return d.length === 10 ? `${d.slice(0,3)}-${d.slice(3,6)}-${d.slice(6)}` : p;
}

/* ── Sidebar toggle (mobile) ─────────────────────────────── */
(function () {
  const toggleBtn = document.getElementById('sidebar-toggle');
  const sidebar   = document.querySelector('.sidebar');
  const overlay   = document.querySelector('.sidebar-overlay');
  if (!toggleBtn || !sidebar) return;

  toggleBtn.addEventListener('click', () => {
    sidebar.classList.toggle('open');
    overlay && overlay.classList.toggle('show');
  });
  overlay && overlay.addEventListener('click', () => {
    sidebar.classList.remove('open');
    overlay.classList.remove('show');
  });
})();
