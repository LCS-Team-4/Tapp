// employee.js — employee-portal-only interactivity, translated from the
// current JS build's views/{profile,leave,history}.js + clockin.js into
// plain DOM operations against portal.php's PHP-rendered markup. Uses the
// global switchTab() defined in app.js (loaded first) for the "Quick
// Actions" deep-links; does not touch app.js itself.

const EYE_ICON = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8Z"/><circle cx="12" cy="12" r="3"/>';
const EYE_OFF_ICON = '<path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a21.8 21.8 0 0 1 5.06-6.06M9.9 4.24A10.94 10.94 0 0 1 12 4c7 0 11 8 11 8a21.8 21.8 0 0 1-3.22 4.44"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/><path d="M1 1l22 22"/>';

// Escapes user-typed text before it's interpolated into an innerHTML
// template — needed since leave reasons / names come from live input.
function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str ?? '';
  return div.innerHTML;
}

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

  // Event delegation so edit/cancel stays wired after the cards are
  // re-rendered by renderLeaveStatusList().
  listEl.addEventListener('click', async (event) => {
    const cancelBtn = event.target.closest('[data-leave-action="cancel"]');
    if (cancelBtn) {
      const card = cancelBtn.closest('.leave-req-card');
      if (!card) return;
      if (!confirm('Cancel this leave request?')) return;

      const response = await cancelLeaveRequest(card.dataset.leaveId);
      const data = await response.json().catch(() => ({ message: 'Unable to cancel leave request' }));

      if (!response.ok) {
        alert(data.message || 'Unable to cancel leave request');
        return;
      }

      await refreshEmployeeProfile();
      return;
    }

    const editBtn = event.target.closest('[data-leave-action="edit"]');
    if (editBtn) {
      const card = editBtn.closest('.leave-req-card');
      if (!card) return;
      editingId = card.dataset.leaveId;
      typeEl.value = card.dataset.leaveType;
      startEl.value = card.dataset.startDate;
      endEl.min = startEl.value;
      endEl.value = card.dataset.endDate;
      reasonEl.value = card.dataset.reason;
      submitBtn.textContent = 'Update Request';
      clearFieldErrors();
    }
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

    // Backend errors come back as {"error": {"message": "..."}} — read both
    // shapes so the real message (e.g. overlapping leave dates) is shown.
    function errorMessage(data, fallback) {
      return data?.error?.message || data?.message || fallback;
    }

    if (editingId !== null) {
      const response = await updateLeaveRequest(editingId, fields);
      const data = await response.json().catch(() => ({ message: 'Unexpected response from server' }));

      if (!response.ok) {
        errorEl.textContent = errorMessage(data, 'Unable to update leave request');
        errorEl.style.display = 'block';
        return;
      }

      editingId = null;
      submitBtn.textContent = 'Submit Request';
      typeEl.selectedIndex = 0;
      startEl.value = '';
      endEl.value = '';
      endEl.min = initialMin;
      reasonEl.value = '';
      await refreshEmployeeProfile();
      return;
    }

    const response = await submitLeaveRequest(fields);
    const data = await response.json().catch(() => ({ message: 'Unexpected response from server' }));

    if (!response.ok) {
      errorEl.textContent = errorMessage(data, 'Unable to submit leave request');
      errorEl.style.display = 'block';
      return;
    }

    typeEl.selectedIndex = 0;
    startEl.value = '';
    endEl.value = '';
    endEl.min = initialMin;
    reasonEl.value = '';
    await refreshEmployeeProfile();
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

// ---- Live profile rendering (SSE + polling fallback) ----
// Mirrors the PHP helpers in employee/data.php so re-rendered markup matches
// the initial server-side render exactly.

const LEAVE_TYPE_OPTIONS = [
  { value: 'annual',    label: 'Annual Leave' },
  { value: 'sick',      label: 'Sick Leave' },
  { value: 'stu_leave', label: 'Study Leave' },
  { value: 'fr_leave',  label: 'Family Responsibility Leave' },
  { value: 'unpaid',    label: 'Unpaid Leave' },
  { value: 'emergency', label: 'Emergency Leave' },
  { value: 'other',     label: 'Other Leave' },
  { value: 'leave',     label: 'Leave' },
];

function leaveTypeLabel(value) {
  const opt = LEAVE_TYPE_OPTIONS.find((o) => o.value === value);
  return opt ? opt.label : value || '';
}

function formatDateRange(startDate, endDate) {
  const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
  const [sy, sm, sd] = String(startDate || '').split('-').map(Number);
  const [ey, em, ed] = String(endDate || '').split('-').map(Number);
  if (!sy || !ey) return '';
  if (startDate === endDate) return `${sd} ${months[sm - 1]}`;
  if (sy === ey && sm === em) return `${sd} – ${ed} ${months[sm - 1]}`;
  return `${sd} ${months[sm - 1]} – ${ed} ${months[em - 1]}`;
}

function renderEmployeeDashboard(data) {
  const today = data.today_status || {};

  // Today's status card
  const statusBadge = document.getElementById('today-status-badge');
  if (statusBadge) {
    statusBadge.className = `badge badge-${today.status || 'absent'}`;
    statusBadge.textContent = today.status === 'onsite' ? 'Clocked In'
      : today.status === 'present' ? 'Clocked Out'
      : today.status === 'late' ? 'Clocked In'
      : 'Not Clocked In';
  }
  const clockedIn = document.getElementById('today-clocked-in');
  if (clockedIn) clockedIn.textContent = today.clock_in || '—';

  // Week hours
  const weekHours = document.getElementById('week-hours');
  if (weekHours) weekHours.textContent = today.week_hours_logged ?? 0;
  const weekTarget = document.getElementById('week-hours-target');
  if (weekTarget) weekTarget.textContent = today.week_hours_target ?? 40;

  // Leave balances
  const balances = data.leave_balances || { annual_leave: data.leave_balance ?? 0 };
  const setBal = (id, val) => {
    const el = document.getElementById(id);
    if (el) el.textContent = String(val ?? 0);
  };
  setBal('bal-annual', balances.annual_leave);
  setBal('bal-sick', balances.sick_leave);
  setBal('bal-stu', balances.stu_leave);
  setBal('bal-fr', balances.fr_leave);

  // Pending leave card
  const pendingWrap = document.getElementById('pending-leave-wrap');
  if (pendingWrap) {
    const pending = (data.leave_requests || []).find((r) => r.status === 'pending');
    if (pending) {
      pendingWrap.innerHTML = `
        <div class="leave-req-card" style="margin-bottom:0;">
          <div class="lr-main">
            <div class="lr-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#D2A7A7" stroke-width="1.8"><path d="M4 20 C4 12 8 5 14 3 C16 9 15 16 4 20Z"/></svg></div>
            <div>
              <div class="lr-title">${escapeHtml(leaveTypeLabel(pending.leave_type))}</div>
              <div class="lr-sub">${escapeHtml(formatDateRange(pending.start_date, pending.end_date))} · ${escapeHtml(pending.reason)}</div>
            </div>
          </div>
          <span class="badge badge-pending">Pending</span>
        </div>
      `;
    } else {
      pendingWrap.innerHTML = '<p class="muted">No pending leave requests.</p>';
    }
  }

  // Attendance tab: clock badge + stat cards
  const clockBadge = document.getElementById('clock-badge');
  if (clockBadge) {
    clockBadge.className = `badge badge-${today.status || 'absent'}`;
    clockBadge.textContent = today.status === 'onsite' ? 'Clocked In'
      : today.status === 'present' ? 'Clocked Out'
      : today.status === 'late' ? 'Clocked In'
      : 'Not Clocked In';
  }
  const statClockIn = document.getElementById('stat-clock-in');
  if (statClockIn) statClockIn.textContent = today.clock_in || '—';
  const statClockOut = document.getElementById('stat-clock-out');
  if (statClockOut) statClockOut.textContent = today.clock_out || '—';
  const statTotalHours = document.getElementById('stat-total-hours');
  if (statTotalHours) statTotalHours.textContent = today.total_hours !== null && today.total_hours !== undefined ? today.total_hours : 'In progress';

  // Leave status list (Profile → Leave sub-tab)
  renderLeaveStatusList(data.leave_requests || []);

  // History table (Profile → History sub-tab)
  renderHistoryTable(data.attendance_history || []);
}

function renderLeaveStatusList(requests) {
  const list = document.getElementById('leave-status-list');
  if (!list) return;
  if (!requests.length) {
    list.innerHTML = '<p class="muted">No leave requests yet.</p>';
    return;
  }
  list.innerHTML = requests.map((req) => {
    const stroke = req.status === 'approved' ? '#6C714F' : (req.status === 'declined' ? '#6D382B' : '#D2A7A7');
    const iconPath = req.status === 'declined'
      ? '<path d="M12 3v6M12 21c-5-2-8-6-8-11 3 0 6 1.5 8 5 2-3.5 5-5 8-5 0 5-3 9-8 11Z"/>'
      : '<path d="M4 20 C4 12 8 5 14 3 C16 9 15 16 4 20Z"/>';
    const actions = req.status === 'pending'
      ? `
        <button class="btn-icon" data-leave-action="edit" data-id="${Number(req.leave_id)}" title="Edit request" aria-label="Edit request" type="button">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5Z"/></svg>
        </button>
        <button class="btn-icon" data-leave-action="cancel" data-id="${Number(req.leave_id)}" title="Cancel request" aria-label="Cancel request" type="button">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18"/><path d="M6 6l12 12"/></svg>
        </button>
      `
      : '';
    return `
      <div class="leave-req-card" data-leave-id="${Number(req.leave_id)}" data-leave-type="${escapeHtml(req.leave_type)}" data-start-date="${escapeHtml(req.start_date)}" data-end-date="${escapeHtml(req.end_date)}" data-reason="${escapeHtml(req.reason)}">
        <div class="lr-main">
          <div class="lr-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="${stroke}" stroke-width="1.8">${iconPath}</svg></div>
          <div>
            <div class="lr-title">${escapeHtml(leaveTypeLabel(req.leave_type))}</div>
            <div class="lr-sub">${escapeHtml(formatDateRange(req.start_date, req.end_date))} · ${escapeHtml(req.reason)}</div>
          </div>
        </div>
        <div class="leave-req-actions">
          <span class="badge badge-${escapeHtml(req.status)}">${escapeHtml(req.status.charAt(0).toUpperCase() + req.status.slice(1))}</span>
          ${actions}
        </div>
      </div>
    `;
  }).join('');
}

function renderHistoryTable(history) {
  const tbody = document.getElementById('history-tbody');
  if (!tbody) return;
  const rows = history || [];
  tbody.innerHTML = rows.map((row) => `
    <tr data-status="${escapeHtml(row.status)}" data-date-label="${escapeHtml(row.date_label)}">
      <td>${escapeHtml(row.date_label)}</td>
      <td>${escapeHtml(row.clock_in ?? '—')}</td>
      <td>${escapeHtml(row.clock_out ?? '—')}</td>
      <td>${escapeHtml(row.total_hours ?? '—')}</td>
      <td><span class="badge badge-${escapeHtml(row.status)}">${escapeHtml(row.status.charAt(0).toUpperCase() + row.status.slice(1))}</span></td>
    </tr>
  `).join('');

  // Re-use the established filter handler after re-render so the current
  // search, selected status, and no-results row remain correct.
  const searchEl = document.getElementById('history-search');
  if (searchEl) searchEl.dispatchEvent(new Event('input'));
}

// Fetches the same /users/profile endpoint portal.php uses for its initial
// data, and re-renders every dynamic section.
async function refreshEmployeeProfile() {
  try {
    const response = await fetch(`${API_ROOT}/api/users/profile`, {
      credentials: 'include',
    });
    if (!response.ok) return;
    const body = await response.json();
    renderEmployeeDashboard(body.data || {});
  } catch (error) {
    console.error('Employee profile refresh error:', error);
  }
}

let employeePollingTimer = null;
let employeeEventSource = null;

// Server-Sent Events (SSE): keep a long-lived connection to the PHP endpoint
// that streams "update" events whenever the user's profile data changes
// (clock in/out, leave status, balances, history). EventSource is built-in —
// no packages. It auto-reconnects when the server ends the stream (~25s cap),
// so this is effectively continuous with ~2-3s of latency at most.
function startEmployeeLiveUpdates(intervalMs = 30000) {
  const url = 'stream_updates.php';
  try {
    employeeEventSource = new EventSource(url);
    employeeEventSource.addEventListener('update', (event) => {
      try {
        const data = JSON.parse(event.data);
        renderEmployeeDashboard(data);
      } catch (e) {
        console.error('Bad SSE update payload:', e);
      }
    });
    employeeEventSource.onerror = () => {
      console.warn('SSE connection lost — will auto-reconnect.');
    };
  } catch (e) {
    console.error('SSE init failed:', e);
  }

  // Polling fallback (safety net, less frequent now that SSE is primary).
  if (employeePollingTimer) clearInterval(employeePollingTimer);
  employeePollingTimer = setInterval(() => {
    if (!document.hidden) {
      refreshEmployeeProfile();
    }
  }, intervalMs);
}

wireProfileSubtabs();
wireDashboardDeepLinks();
startLiveClock();
wireAccountForm();
wirePasswordForm();
wirePasswordToggles();
wireLeaveForm();
wireHistory();
startEmployeeLiveUpdates();
