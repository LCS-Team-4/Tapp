// employee.js — employee-portal-only interactivity, translated from the
// current JS build's views/{profile,leave,history}.js + clockin.js into
// plain DOM operations against portal.php's PHP-rendered markup. Uses the
// global switchTab() defined in app.js (loaded first) for the "Quick
// Actions" deep-links; does not touch app.js itself.

const EYE_ICON = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8Z"/><circle cx="12" cy="12" r="3"/>';
const EYE_OFF_ICON = '<path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a21.8 21.8 0 0 1 5.06-6.06M9.9 4.24A10.94 10.94 0 0 1 12 4c7 0 11 8 11 8a21.8 21.8 0 0 1-3.22 4.44"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/><path d="M1 1l22 22"/>';

// ---- Profile sub-tabs (Account / Leave / History) ----

function activateSubtab(container, subtabId) {
  const btn = container.querySelector(`.segmented-item[data-subtab="${subtabId}"]`);
  const panel = container.querySelector(`[data-subtab-panel="${subtabId}"]`);
  if (!btn || !panel) return;
  container.querySelectorAll('.segmented-item').forEach((b) => b.classList.remove('active'));
  btn.classList.add('active');
  container.querySelectorAll('.subtab-panel').forEach((p) => p.classList.remove('active'));
  panel.classList.add('active');
}

// Lets Dashboard's "Quick Actions" jump straight to a tab (and, for
// Profile, a specific sub-tab) — mirrors main.js's switchPanelDirect().
function switchPanelDirect(panelId, subtabId) {
  const btn = document.querySelector(`.nav-item[data-panel="${panelId}"]`);
  if (btn) switchTab(btn);
  if (subtabId && panelId === 'e-profile') {
    const panel = document.getElementById('e-profile');
    if (panel) activateSubtab(panel, subtabId);
  }
}

function wireProfileSubtabs() {
  const profilePanel = document.getElementById('e-profile');
  if (!profilePanel) return;
  profilePanel.querySelectorAll('.segmented-item').forEach((btn) => {
    btn.addEventListener('click', () => activateSubtab(profilePanel, btn.dataset.subtab));
  });
}

function wireDashboardDeepLinks() {
  document.querySelectorAll('[data-goto]').forEach((btn) => {
    btn.addEventListener('click', () => switchPanelDirect(btn.dataset.goto, btn.dataset.subtab));
  });
}

const API_ROOT = '../../backend/public';

async function submitLeaveRequest(payload) {
  return fetch(`${API_ROOT}/api/leave-request`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    credentials: 'include',
    body: JSON.stringify(payload),
  });
}

async function updateLeaveRequest(leaveId, payload) {
  return fetch(`${API_ROOT}/api/leave-requests/${encodeURIComponent(leaveId)}`, {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json' },
    credentials: 'include',
    body: JSON.stringify(payload),
  });
}

async function cancelLeaveRequest(leaveId) {
  return fetch(`${API_ROOT}/api/leave-requests/${encodeURIComponent(leaveId)}`, {
    method: 'DELETE',
    credentials: 'include',
  });
}

// ---- Attendance: read-only live clock ----
// Clock-in/out events come from RFID cards. The portal only displays the
// current time and the latest persisted attendance times.
function startLiveClock() {
  const timeEl = document.getElementById('live-clock');
  const dateEl = document.getElementById('live-clock-date');
  if (!timeEl) return;

  const timeFormatter = new Intl.DateTimeFormat(undefined, {
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hour12: true,
  });
  const dateFormatter = new Intl.DateTimeFormat(undefined, {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  });

  function tick() {
    const now = new Date();
    timeEl.textContent = timeFormatter.format(now);
    if (dateEl) dateEl.textContent = dateFormatter.format(now);
  }

  tick();
  window.setInterval(tick, 1000);
}

// ---- Profile: Account form + password change + show/hide toggles ----

function wireAccountForm() {
  const nameEl = document.getElementById('profile-name');
  const emailEl = document.getElementById('profile-email');
  const nameWrap = document.getElementById('field-profile-name');
  const emailWrap = document.getElementById('field-profile-email');
  const errorEl = document.getElementById('profile-save-error');
  const successEl = document.getElementById('profile-save-success');
  const saveBtn = document.getElementById('btn-save-profile');
  if (!saveBtn) return;

  saveBtn.addEventListener('click', () => {
    successEl.style.display = 'none';
    const name = nameEl.value.trim();
    const email = emailEl.value.trim();

    const nameEmpty = !name;
    const emailEmpty = !email;
    nameWrap?.classList.toggle('invalid', nameEmpty);
    emailWrap?.classList.toggle('invalid', emailEmpty);

    if (nameEmpty || emailEmpty) {
      errorEl.textContent = 'Full Name and Email are required.';
      errorEl.style.display = 'block';
      return;
    }
    errorEl.style.display = 'none';

    // Front-end only — no PATCH /api/employees/{id} endpoint yet.
    const whoEl = document.querySelector('.topbar .who');
    if (whoEl) whoEl.textContent = name;

    successEl.style.display = 'block';
  });
}

function wirePasswordForm() {
  const currentEl = document.getElementById('pwd-current');
  const newEl = document.getElementById('pwd-new');
  const confirmEl = document.getElementById('pwd-confirm');
  const errorEl = document.getElementById('profile-password-error');
  const successEl = document.getElementById('profile-password-success');
  const updateBtn = document.getElementById('btn-update-password');
  if (!updateBtn) return;

  updateBtn.addEventListener('click', () => {
    successEl.style.display = 'none';

    if (!currentEl.value) {
      errorEl.textContent = 'Current Password is required.';
      errorEl.style.display = 'block';
      return;
    }
    if (!newEl.value || newEl.value !== confirmEl.value) {
      errorEl.textContent = 'New Password and Confirm New Password must match.';
      errorEl.style.display = 'block';
      return;
    }
    errorEl.style.display = 'none';

    // Front-end only — no PATCH /api/auth/password endpoint yet.
    currentEl.value = '';
    newEl.value = '';
    confirmEl.value = '';
    successEl.style.display = 'block';
  });
}

function wirePasswordToggles() {
  document.querySelectorAll('.pw-toggle').forEach((btn) => {
    btn.addEventListener('click', () => {
      const input = document.getElementById(btn.dataset.target);
      if (!input) return;
      const isHidden = input.type === 'password';
      input.type = isHidden ? 'text' : 'password';
      btn.innerHTML = `<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">${isHidden ? EYE_OFF_ICON : EYE_ICON}</svg>`;
      btn.title = isHidden ? 'Hide password' : 'Show password';
      btn.setAttribute('aria-label', btn.title);
    });
  });
}

// ---- Profile: Leave sub-tab (form + status list) ----
// Submit/edit/cancel now calls the backend API at /api/leave-request and
// /api/leave-requests/{id} instead of the legacy actions/leave.php endpoint.
function wireLeaveForm() {
  const panel = document.querySelector('[data-subtab-panel="leave"]');
  if (!panel) return;
  const typeEl = panel.querySelector('#leave-type');
  const startEl = panel.querySelector('#leave-start');
  const endEl = panel.querySelector('#leave-end');
  const reasonEl = panel.querySelector('#leave-reason');
  const errorEl = panel.querySelector('#leave-form-error');
  const submitBtn = panel.querySelector('#btn-submit-leave');
  const listEl = panel.querySelector('#leave-status-list');
  if (!submitBtn || !listEl) return;

  const fieldWrappers = {
    type: panel.querySelector('#field-leave-type'),
    start: panel.querySelector('#field-leave-start'),
    end: panel.querySelector('#field-leave-end'),
    reason: panel.querySelector('#field-leave-reason'),
  };

  const initialMin = startEl.min;
  let editingId = null;

  function clearFieldErrors() {
    Object.values(fieldWrappers).forEach((el) => el?.classList.remove('invalid'));
    if (errorEl) errorEl.style.display = 'none';
  }

  function validate() {
    const fields = [
      [typeEl, fieldWrappers.type],
      [startEl, fieldWrappers.start],
      [endEl, fieldWrappers.end],
      [reasonEl, fieldWrappers.reason],
    ];
    let valid = true;
    fields.forEach(([el, wrapper]) => {
      const empty = !el.value.trim();
      wrapper?.classList.toggle('invalid', empty);
      if (empty) valid = false;
    });
    if (errorEl) errorEl.style.display = valid ? 'none' : 'block';
    return valid;
  }

  listEl.querySelectorAll('.leave-req-card').forEach((card) => {
    card.querySelector('[data-leave-action="cancel"]')?.addEventListener('click', async () => {
      if (!confirm('Cancel this leave request?')) return;

      const response = await cancelLeaveRequest(card.dataset.leaveId);
      const data = await response.json().catch(() => ({ message: 'Unable to cancel leave request' }));

      if (!response.ok) {
        alert(data.message || 'Unable to cancel leave request');
        return;
      }

      window.location.reload();
    });

    card.querySelector('[data-leave-action="edit"]')?.addEventListener('click', () => {
      editingId = card.dataset.leaveId;
      typeEl.value = card.dataset.leaveType;
      startEl.value = card.dataset.startDate;
      endEl.min = startEl.value;
      endEl.value = card.dataset.endDate;
      reasonEl.value = card.dataset.reason;
      submitBtn.textContent = 'Update Request';
      clearFieldErrors();
    });
  });

  // Self-service leave requests can't be backdated, and the end date can't
  // be earlier than the start date — mirrors the source's datepicker
  // minDate options via the native <input type="date">'s min attribute.
  startEl.addEventListener('change', () => {
    endEl.min = startEl.value || initialMin;
    if (endEl.value && endEl.value < endEl.min) endEl.value = '';
  });

  submitBtn.addEventListener('click', async () => {
    if (!validate()) return;

    const fields = {
      leave_type: typeEl.value,
      start_date: startEl.value,
      end_date: endEl.value,
      reason: reasonEl.value.trim(),
    };

    if (editingId !== null) {
      const response = await updateLeaveRequest(editingId, fields);
      const data = await response.json().catch(() => ({ message: 'Unexpected response from server' }));

      if (!response.ok) {
        errorEl.textContent = data.message || 'Unable to update leave request';
        errorEl.style.display = 'block';
        return;
      }

      window.location.reload();
      return;
    }

    const response = await submitLeaveRequest(fields);
    const data = await response.json().catch(() => ({ message: 'Unexpected response from server' }));

    if (!response.ok) {
      errorEl.textContent = data.message || 'Unable to submit leave request';
      errorEl.style.display = 'block';
      return;
    }

    window.location.reload();
  });
}

// ---- Profile: History sub-tab (search + status filter) ----
// Ported from history.js, but re-worked to filter the PHP-rendered <tr>
// elements in place (toggling display) instead of re-rendering from a
// JS-side data array, since data.php only rendered the table once server
// side and there's no client-side attendanceHistory array to re-map.
function wireHistory() {
  const panel = document.querySelector('[data-subtab-panel="history"]');
  if (!panel) return;
  const searchEl = panel.querySelector('#history-search');
  const statusEl = panel.querySelector('#history-status-filter');
  const tbody = panel.querySelector('#history-tbody');
  if (!searchEl || !statusEl || !tbody) return;

  function applyFilters() {
    const query = searchEl.value.trim().toLowerCase();
    const status = statusEl.value;
    const rows = tbody.querySelectorAll('tr[data-status]');
    let anyVisible = false;

    rows.forEach((row) => {
      const dateLabel = (row.dataset.dateLabel || '').toLowerCase();
      const matchesQuery = !query || dateLabel.includes(query);
      const matchesStatus = !status || row.dataset.status === status;
      const visible = matchesQuery && matchesStatus;
      row.style.display = visible ? '' : 'none';
      if (visible) anyVisible = true;
    });

    let emptyRow = tbody.querySelector('tr[data-empty-row]');
    if (!anyVisible) {
      if (!emptyRow) {
        emptyRow = document.createElement('tr');
        emptyRow.setAttribute('data-empty-row', '');
        emptyRow.innerHTML = '<td colspan="5" class="muted">No matching records.</td>';
        tbody.appendChild(emptyRow);
      }
      emptyRow.style.display = '';
    } else if (emptyRow) {
      emptyRow.style.display = 'none';
    }
  }

  searchEl.addEventListener('input', applyFilters);
  statusEl.addEventListener('change', applyFilters);
}

wireProfileSubtabs();
wireDashboardDeepLinks();
startLiveClock();
wireAccountForm();
wirePasswordForm();
wirePasswordToggles();
wireLeaveForm();
wireHistory();
