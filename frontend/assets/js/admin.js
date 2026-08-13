// admin.js — admin-portal-only interactivity, translated from the current
// JS build's views/{employees,attendance}.js + shared/ui/components/
// confirm-modal.js + shared/utils/csv.js into plain DOM operations against
// portal.php's PHP-rendered markup. Does not touch app.js (shell) itself.

const EDIT_ICON = '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 013 3L7 19l-4 1 1-4 12.5-12.5z"/>';
const DELETE_ICON = '<path d="M3 6h18M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2m3 0l-1 14a2 2 0 01-2 2H7a2 2 0 01-2-2L4 6"/>';

// Escapes user-typed text (employee name, department, etc.) before it's
// interpolated into an innerHTML template — needed since these values come
// from a live registration form, not hardcoded placeholder data.
function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str ?? '';
  return div.innerHTML;
}

// ---- Confirm dialog ----
// Ported from shared/ui/components/confirm-modal.js: reuses the
// .modal-overlay/.modal shell so it matches the rest of the app instead of
// a native confirm() popup. Lazily builds one overlay and reuses it.
let confirmOverlay = null;

function ensureConfirmOverlay() {
  if (confirmOverlay) return confirmOverlay;

  confirmOverlay = document.createElement('div');
  confirmOverlay.className = 'modal-overlay';
  confirmOverlay.id = 'confirm-modal-overlay';
  confirmOverlay.innerHTML = `
    <div class="modal" style="max-width:380px;">
      <p id="confirm-modal-message" style="margin-bottom:20px; font-size:14px;"></p>
      <div style="display:flex; gap:10px;">
        <button class="btn btn-rust" id="confirm-modal-confirm" type="button">Confirm</button>
        <button class="btn btn-outline" id="confirm-modal-cancel" type="button">Cancel</button>
      </div>
    </div>
  `;
  document.body.appendChild(confirmOverlay);
  return confirmOverlay;
}

function confirmDialog(message, onConfirm) {
  const el = ensureConfirmOverlay();
  el.querySelector('#confirm-modal-message').textContent = message;

  const confirmBtn = el.querySelector('#confirm-modal-confirm');
  const cancelBtn = el.querySelector('#confirm-modal-cancel');

  function close() {
    el.classList.remove('active');
    confirmBtn.removeEventListener('click', handleConfirm);
    cancelBtn.removeEventListener('click', handleCancel);
  }
  function handleConfirm() { close(); onConfirm(); }
  function handleCancel() { close(); }

  confirmBtn.addEventListener('click', handleConfirm);
  cancelBtn.addEventListener('click', handleCancel);

  el.classList.add('active');
}

// ---- CSV export ----
// Ported from shared/utils/csv.js's downloadCsv(), unchanged Blob-download
// pattern (client-only export, nothing to fetch from a backend).
function escapeCsvValue(value) {
  const str = String(value ?? '');
  return /[",\n]/.test(str) ? `"${str.replace(/"/g, '""')}"` : str;
}

function rowsToCsvLines(rows) {
  if (!rows || !rows.length) return [];
  const headers = Object.keys(rows[0]);
  return [
    headers.join(','),
    ...rows.map((row) => headers.map((h) => escapeCsvValue(row[h])).join(',')),
  ];
}

// Two call shapes, same as the source's downloadCsv():
//   downloadCsv(filename, rows) — flat array of row objects.
//   downloadCsv(filename, { sections: [{ title, rows }, ...] }) — multiple
//   named sections combined into one file, each preceded by a title line.
// The Attendance tab (chunk 3) uses the flat-array shape; Reports (this
// chunk) uses the sections shape for multi-tile exports.
function downloadCsv(filename, data) {
  let lines;
  if (Array.isArray(data)) {
    lines = rowsToCsvLines(data);
  } else if (data && Array.isArray(data.sections)) {
    lines = [];
    data.sections.forEach(({ title, rows }) => {
      if (!rows || !rows.length) return;
      if (lines.length) lines.push('');
      lines.push(title, ...rowsToCsvLines(rows));
    });
  } else {
    console.error('Invalid data format for CSV export', data);
    return;
  }
  if (!lines.length) {
    console.warn('No data to export');
    return;
  }

  try {
    const blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
    console.log('CSV exported successfully:', filename);
  } catch (error) {
    console.error('Failed to download CSV:', error);
    throw error;
  }
}

// ---- Employees: directory, search/filter, register/edit/delete ----

// employee_id format is EMP-#### — take the highest existing number
// (across all rows, not just visible ones, so filtering doesn't skip IDs)
// and increment it, rather than anything random.
function nextEmployeeId(tbody) {
  let max = 0;
  tbody.querySelectorAll('tr[data-id]').forEach((row) => {
    const n = Number((row.dataset.id || '').split('-')[1]);
    if (Number.isFinite(n) && n > max) max = n;
  });
  return `EMP-${String(max + 1).padStart(4, '0')}`;
}

function initialsOf(name) {
  const parts = name.trim().split(/\s+/);
  return ((parts[0]?.[0] ?? '') + (parts[parts.length - 1]?.[0] ?? '')).toUpperCase();
}

const ALLOWED_DEPARTMENTS = ['Engineering', 'Design', 'Operations', 'Marketing', 'Finance', 'Security'];

function buildEmployeeRow(emp) {
  const tr = document.createElement('tr');
  tr.dataset.id = emp.employee_id;
  tr.dataset.email = emp.email;
  tr.innerHTML = `
    <td><div class="emp-cell"><div class="avatar">${escapeHtml(emp.initials)}</div><div><div class="emp-name">${escapeHtml(emp.name)}</div></div></div></td>
    <td>${escapeHtml(emp.employee_id)}</td>
    <td>${escapeHtml(emp.department)}</td>
    <td>${escapeHtml(emp.position)}</td>
    <td><span class="badge badge-offsite">Offsite</span></td>
    <td>
      <button class="btn-icon" title="Edit" data-action="edit" data-id="${escapeHtml(emp.employee_id)}" type="button"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">${EDIT_ICON}</svg></button>
      <button class="btn-icon" title="Delete" data-action="delete" data-id="${escapeHtml(emp.employee_id)}" type="button"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">${DELETE_ICON}</svg></button>
    </td>
  `;
  return tr;
}

function wireEmployees() {
  const panel = document.getElementById('a-employees');
  if (!panel) return;
  const searchEl = panel.querySelector('#emp-search');
  const departmentEl = panel.querySelector('#emp-department-filter');
  const tbody = panel.querySelector('#employee-tbody');
  if (!tbody) return;

  const modal = panel.querySelector('#modal-add-employee');
  const titleEl = panel.querySelector('#employee-modal-title');
  const nameEl = panel.querySelector('#emp-name-input');
  const emailEl = panel.querySelector('#emp-email-input');
  const deptEl = panel.querySelector('#emp-department-input');
  const positionEl = panel.querySelector('#emp-position-input');
  const idEl = panel.querySelector('#emp-id-input');
  const errorEl = panel.querySelector('#emp-form-error');
  const submitBtn = panel.querySelector('#btn-register-employee');

  const fieldWrappers = {
    name: panel.querySelector('#field-emp-name'),
    email: panel.querySelector('#field-emp-email'),
    department: panel.querySelector('#field-emp-department'),
    position: panel.querySelector('#field-emp-position'),
  };

  let editingId = null;

  function applyFilters() {
    const query = searchEl.value.trim().toLowerCase();
    const dept = departmentEl.value;
    tbody.querySelectorAll('tr[data-id]').forEach((row) => {
      const matchesQuery = !query || row.textContent.toLowerCase().includes(query);
      const matchesDept = dept === 'All departments' || row.children[2].textContent === dept;
      row.style.display = matchesQuery && matchesDept ? '' : 'none';
    });
  }

  searchEl?.addEventListener('input', applyFilters);
  departmentEl?.addEventListener('change', applyFilters);

  function openModal() { modal?.classList.add('active'); }
  function closeModal() { modal?.classList.remove('active'); }

  function clearFieldErrors() {
    Object.values(fieldWrappers).forEach((el) => el?.classList.remove('invalid'));
    if (errorEl) errorEl.style.display = 'none';
  }

  function resetForm() {
    nameEl.value = '';
    emailEl.value = '';
    deptEl.value = '';
    positionEl.value = '';
    idEl.value = '';
    editingId = null;
    titleEl.textContent = 'Register Employee';
    submitBtn.textContent = 'Register Employee';
    clearFieldErrors();
  }

  function validate() {
    const fields = [
      [nameEl, fieldWrappers.name],
      [emailEl, fieldWrappers.email],
      [deptEl, fieldWrappers.department],
      [positionEl, fieldWrappers.position],
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

  function wireRowActions(row) {
    row.querySelector('[data-action="edit"]')?.addEventListener('click', () => {
      editingId = row.dataset.id;
      nameEl.value = row.querySelector('.emp-name')?.textContent ?? '';
      emailEl.value = row.dataset.email ?? '';
      const department = row.children[2].textContent.trim();
      deptEl.value = ALLOWED_DEPARTMENTS.includes(department) ? department : '';
      positionEl.value = row.children[3].textContent;
      idEl.value = row.dataset.id;
      titleEl.textContent = 'Edit Employee';
      submitBtn.textContent = 'Save Changes';
      clearFieldErrors();
      openModal();
    });

    row.querySelector('[data-action="delete"]')?.addEventListener('click', () => {
      const name = row.querySelector('.emp-name')?.textContent ?? '';
      const id = row.dataset.id;
      confirmDialog(`Remove ${name} (${id}) from the employee roster?`, async () => {
        try {
          const response = await fetch(
            `${API_ROOT}/api/admin/employees/${encodeURIComponent(id)}`,
            {
              method: 'DELETE',
              credentials: 'include',
            }
          );

          const body = await response.json().catch(() => ({}));
          if (!response.ok) {
            const message = body.error?.message || 'Unable to delete employee';
            throw new Error(message);
          }

          row.remove();
        } catch (error) {
          window.alert(error.message);
        }
      });
    });
  }

  tbody.querySelectorAll('tr[data-id]').forEach(wireRowActions);

  panel.querySelector('#btn-open-add-employee')?.addEventListener('click', () => {
    resetForm();
    openModal();
  });
  panel.querySelector('#btn-cancel-add-employee')?.addEventListener('click', () => {
    closeModal();
    resetForm();
  });

  submitBtn?.addEventListener('click', async () => {
    if (!validate()) return;

    const name = nameEl.value.trim();
    const email = emailEl.value.trim();
    const department = deptEl.value.trim();
    const position = positionEl.value.trim();

    if (editingId !== null) {
      try {
        const response = await fetch(
          `${API_ROOT}/api/admin/employees/${encodeURIComponent(editingId)}`,
          {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({
              name,
              email,
              department,
              position,
            }),
          }
        );

        const body = await response.json().catch(() => ({}));
        if (!response.ok) {
          const message = body.error?.message || 'Unable to update employee';
          throw new Error(message);
        }

        const updated = body.data || {};
        const row = tbody.querySelector(`tr[data-id="${editingId}"]`);
        if (row) {
          row.querySelector('.avatar').textContent = initialsOf(updated.name || name);
          row.querySelector('.emp-name').textContent = updated.name || name;
          row.children[2].textContent = updated.department || department;
          row.children[3].textContent = updated.position || position;
          row.dataset.email = updated.email || email;
        }
      } catch (error) {
        if (errorEl) {
          errorEl.textContent = error.message;
          errorEl.style.display = 'block';
        }
        return;
      }
    } else {
      try {
        const response = await fetch(`${API_ROOT}/api/admin/employees`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'include',
          body: JSON.stringify({
            name,
            email,
            department,
            position,
          }),
        });

        const body = await response.json().catch(() => ({}));
        if (!response.ok) {
          const message = body.error?.message || 'Unable to register employee';
          throw new Error(message);
        }

        const created = body.data || {};
        const newRow = buildEmployeeRow({
          employee_id: created.employee_id || nextEmployeeId(tbody),
          name: created.name || name,
          initials: initialsOf(created.name || name),
          email: created.email || email,
          department,
          position,
        });
        wireRowActions(newRow);
        tbody.appendChild(newRow);
      } catch (error) {
        if (errorEl) {
          errorEl.textContent = error.message;
          errorEl.style.display = 'block';
        }
        return;
      }
    }

    applyFilters();
    closeModal();
    resetForm();
  });
}

// ---- Attendance monitoring: search ----
function wireAttendance() {
  const panel = document.getElementById('a-attendance');
  if (!panel) return;
  const searchEl = panel.querySelector('#attendance-search');
  const tbody = panel.querySelector('#attendance-tbody');
  if (!tbody) return;

  function applyFilter() {
    const query = searchEl.value.trim().toLowerCase();
    const rows = tbody.querySelectorAll('tr[data-name]');
    let anyVisible = false;

    rows.forEach((row) => {
      const name = (row.dataset.name || '').toLowerCase();
      const visible = !query || name.includes(query);
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

  searchEl?.addEventListener('input', applyFilter);
}

// ---- Shared date helper (leave decisions, report filenames) ----
function todayIso() {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

// Builds and submits a hidden POST form, then lets the redirect reload the
// page — same full-reload pattern employee.js's clockAction()/leave form
// use, so db.json (via the actions/*.php endpoints) is the one source of
// truth instead of local DOM state.
function postForm(action, fields) {
  const form = document.createElement('form');
  form.method = 'POST';
  form.action = action;
  form.style.display = 'none';
  Object.entries(fields).forEach(([name, value]) => {
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = name;
    input.value = value;
    form.appendChild(input);
  });
  document.body.appendChild(form);
  form.submit();
}

// ---- Leave management: approve/decline + live refresh ----
// Each decision now calls the backend API at /api/admin/leave-requests/{id}
// instead of the legacy actions/leave_decision.php endpoint. After a decision
// (or via the refresh button / auto-polling) the pending + history lists are
// re-rendered from the live dashboard API instead of reloading the page.
const API_ROOT = '../../backend/public';

const LEAVE_TYPE_LABELS = {
  annual: 'Annual Leave',
  sick: 'Sick Leave',
  unpaid: 'Unpaid Leave',
  emergency: 'Emergency Leave',
  other: 'Other Leave',
  leave: 'Leave',
};
const LEAVE_STROKES = ['#D2A7A7', '#6C714F', '#6D382B'];

function leaveTypeLabel(type) {
  return LEAVE_TYPE_LABELS[type] || LEAVE_TYPE_LABELS.other || type || 'Leave';
}

function dayLabel(days) {
  const n = Number(days || 0);
  return `${n} day${n === 1 ? '' : 's'}`;
}

function leaveIcon(i) {
  return i % 3 === 2
    ? '<path d="M12 3v6M12 21c-5-2-8-6-8-11 3 0 6 1.5 8 5 2-3.5 5-5 8-5 0 5-3 9-8 11Z"/>'
    : '<path d="M4 20 C4 12 8 5 14 3 C16 9 15 16 4 20Z"/>';
}

// Mirrors the card markup PHP-rendered in portal.php so a live refresh
// produces cards visually identical to the initial page render.
function pendingCardHTML(req, i) {
  return `
    <div class="leave-req-card" data-leave-id="${escapeHtml(req.leave_id)}" data-employee-name="${escapeHtml(req.employee_name)}" data-leave-type-label="${escapeHtml(leaveTypeLabel(req.leave_type))}" data-duration-days="${Number(req.duration_days || 0)}" data-reason="${escapeHtml(req.reason)}" data-status="pending" data-decided-at="">
      <div class="lr-main">
        <div class="lr-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="${LEAVE_STROKES[i % 3]}" stroke-width="1.8">${leaveIcon(i)}</svg></div>
        <div>
          <div class="lr-title">${escapeHtml(req.employee_name)} — ${escapeHtml(leaveTypeLabel(req.leave_type))}</div>
          <div class="lr-sub">${escapeHtml(dayLabel(req.duration_days))} · ${escapeHtml(req.reason)}</div>
        </div>
      </div>
      <div class="leave-req-actions">
        <span class="badge badge-pending" style="margin-right:6px;">Pending</span>
        <button class="btn btn-olive btn-sm" data-decision="approved" type="button">Approve</button>
        <button class="btn btn-rust btn-sm" data-decision="declined" type="button">Decline</button>
      </div>
    </div>
  `;
}

function historyCardHTML(req, i) {
  const status = req.status === 'approved' ? 'approved' : 'declined';
  const label = status === 'approved' ? 'Approved' : 'Declined';
  return `
    <div class="leave-req-card" data-leave-id="${escapeHtml(req.leave_id)}" data-employee-name="${escapeHtml(req.employee_name)}" data-leave-type-label="${escapeHtml(leaveTypeLabel(req.leave_type))}" data-duration-days="${Number(req.duration_days || 0)}" data-reason="${escapeHtml(req.reason)}" data-status="${escapeHtml(status)}" data-decided-at="${escapeHtml(req.decided_at)}">
      <div class="lr-main">
        <div class="lr-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="${LEAVE_STROKES[i % 3]}" stroke-width="1.8">${leaveIcon(i)}</svg></div>
        <div>
          <div class="lr-title">${escapeHtml(req.employee_name)} — ${escapeHtml(leaveTypeLabel(req.leave_type))}</div>
          <div class="lr-sub">${escapeHtml(dayLabel(req.duration_days))} · ${escapeHtml(req.reason)} · Decided ${escapeHtml(req.decided_at)}</div>
        </div>
      </div>
      <div class="leave-req-actions">
        <span class="badge badge-${escapeHtml(status)}">${escapeHtml(label)}</span>
      </div>
    </div>
  `;
}

async function decideLeave(leaveId, decision) {
  return fetch(`${API_ROOT}/api/admin/leave-requests/${encodeURIComponent(leaveId)}`, {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json' },
    credentials: 'include',
    body: JSON.stringify({ status: decision }),
  });
}

// Fetches the same dashboard endpoint portal.php uses for its initial data,
// and re-renders the pending + history leave cards (and their counts).
async function refreshLeaveData() {
  const refreshBtn = document.getElementById('btn-refresh-leave');
  try {
    if (refreshBtn) refreshBtn.disabled = true;
    const response = await fetch(`${API_ROOT}/api/admin/dashboard`, {
      credentials: 'include',
    });
    if (!response.ok) {
      const body = await response.json().catch(() => ({}));
      throw new Error(body.error?.message || 'Unable to refresh leave requests');
    }
    const body = await response.json();
    const data = body.data || {};
    renderLeaveLists(data);
  } catch (error) {
    console.error('Leave refresh error:', error);
  } finally {
    if (refreshBtn) refreshBtn.disabled = false;
  }
}

function renderLeaveLists(data) {
  const pendingList = document.getElementById('leave-list');
  const historyList = document.getElementById('leave-history-list');
  const pendingCount = document.getElementById('leave-pending-count');
  const historyCount = document.getElementById('leave-history-count');

  const pending = data.pending_leave_requests || [];
  const history = data.leave_history || [];

  if (pendingList) {
    pendingList.innerHTML = pending.map((req, i) => pendingCardHTML(req, i)).join('');
  }
  if (historyList) {
    historyList.innerHTML = history.map((req, i) => historyCardHTML(req, i)).join('');
  }
  if (pendingCount) {
    pendingCount.textContent = `${pending.length} awaiting review`;
  }
  if (historyCount) {
    historyCount.textContent = `${history.length} decided`;
  }
}

let leavePollingTimer = null;
let leaveEventSource = null;

// Server-Sent Events (SSE): keep a long-lived connection to the PHP endpoint
// that streams "update" events whenever pending-leave state changes (new
// request, or a decision). The browser's EventSource is built-in — no
// packages. EventSource auto-reconnects when the server ends the stream
// (the PHP side caps each connection at ~25s), so this is effectively
// continuous with ~2-3s of latency at most.
function startLeavePolling(intervalMs = 30000) {
  const panel = document.getElementById('a-leave');
  if (!panel) return;

  // SSE stream (primary, near-instant updates). Relative to the admin portal
  // page, which lives in the same /admin/ directory as this endpoint.
  const url = 'stream_leave_updates.php';
  try {
    leaveEventSource = new EventSource(url);
    leaveEventSource.addEventListener('update', (event) => {
      try {
        const data = JSON.parse(event.data);
        renderLeaveLists(data);
      } catch (e) {
        console.error('Bad SSE update payload:', e);
      }
    });
    leaveEventSource.onerror = () => {
      // EventSource reconnects automatically; just log it. The polling
      // fallback below covers any gap while it's disconnected.
      console.warn('SSE connection lost — will auto-reconnect.');
    };
  } catch (e) {
    console.error('SSE init failed:', e);
  }

  // Polling fallback (safety net, less frequent now that SSE is primary).
  if (leavePollingTimer) clearInterval(leavePollingTimer);
  leavePollingTimer = setInterval(() => {
    // Only poll while the page is visible to avoid needless background work.
    if (!document.hidden && document.getElementById('a-leave')) {
      refreshLeaveData();
    }
  }, intervalMs);
}

function wireLeave() {
  const panel = document.getElementById('a-leave');
  if (!panel) return;

  // Event delegation so approve/decline stays wired after the cards are
  // re-rendered by refreshLeaveData().
  panel.addEventListener('click', async (event) => {
    const btn = event.target.closest('[data-decision]');
    if (!btn) return;
    const card = btn.closest('.leave-req-card');
    if (!card) return;

    btn.disabled = true;
    try {
      const response = await decideLeave(card.dataset.leaveId, btn.dataset.decision);
      const data = await response.json().catch(() => ({ message: 'Unable to update leave request' }));
      if (!response.ok) {
        alert(data.message || 'Unable to update leave request');
        btn.disabled = false;
        return;
      }
      // Update the lists live instead of reloading the whole page.
      await refreshLeaveData();
    } catch (error) {
      alert(error.message || 'Unable to update leave request');
      btn.disabled = false;
    }
  });

  // Manual refresh button in the Pending Requests card header.
  const refreshBtn = document.getElementById('btn-refresh-leave');
  refreshBtn?.addEventListener('click', refreshLeaveData);
}

// ---- Reports: tile selection + CSV/PDF export ----
// Report row-builders read live DOM state (attendance table, leave cards)
// rather than a JS-side data array, so a leave decision made in the Leave
// tab or an edit made in Employees is reflected in the export — same
// cross-tab reactivity the source gets for free from sharing one
// placeholder.js module, achieved here via the DOM instead.
function attendanceCellText(cells, i) {
  const text = cells[i].textContent.trim();
  return text === '—' ? '' : text;
}

function buildDailyRows() {
  const tbody = document.getElementById('attendance-tbody');
  if (!tbody) return [];
  return [...tbody.querySelectorAll('tr[data-name]')].map((row) => {
    const cells = row.querySelectorAll('td');
    if (cells.length < 5) return null;
    return {
      Employee: row.dataset.name || '',
      'Clock In': attendanceCellText(cells, 1),
      'Clock Out': attendanceCellText(cells, 2),
      Hours: attendanceCellText(cells, 3),
      Status: attendanceCellText(cells, 4),
    };
  }).filter(row => row !== null);
}

function buildEmployeeHoursRows() {
  const tbody = document.getElementById('attendance-tbody');
  if (!tbody) return [];
  return [...tbody.querySelectorAll('tr[data-name]')].map((row) => {
    const cells = row.querySelectorAll('td');
    if (cells.length < 4) return null;
    return {
      Employee: row.dataset.name || '',
      'Hours Today': attendanceCellText(cells, 3),
    };
  }).filter(row => row !== null);
}

function buildLeaveRows() {
  const cards = [
    ...document.querySelectorAll('#leave-list .leave-req-card'),
    ...document.querySelectorAll('#leave-history-list .leave-req-card'),
  ];
  return cards.map((card) => {
    const status = (card.dataset.status || 'pending').charAt(0).toUpperCase() + (card.dataset.status || 'pending').slice(1);
    return {
      Employee: card.dataset.employeeName || '',
      'Leave Type': card.dataset.leaveTypeLabel || '',
      Days: card.dataset.durationDays || '',
      Reason: card.dataset.reason || '',
      Status: status,
      'Decided On': card.dataset.decidedAt || '',
    };
  }).filter(row => row.Employee && row['Leave Type']);
}

// Maps a report tile's data-key to the section title used in exports and
// the row-builder that supplies its data — only tiles with real backing
// data (daily, employee_hours, leave) have an entry; the other three tiles
// are rendered disabled in portal.php and never reach this map.
const TILE_DATA = {
  daily: { title: 'Daily Attendance', build: buildDailyRows },
  employee_hours: { title: 'Employee Hours', build: buildEmployeeHoursRows },
  leave: { title: 'Leave Reports', build: buildLeaveRows },
};

// Ported from pdf.js's downloadPdf(), adapted from the ESM `autoTable(doc,
// opts)` call style to the CDN UMD build's `doc.autoTable(opts)` method
// style (jsPDF + jspdf-autotable loaded via <script> tags in portal.php's
// <head> per the export decision — no bundler here to resolve npm imports).
function downloadPdf(filename, title, sections) {
  const rowsBySections = (sections || []).filter((s) => s.rows && s.rows.length);
  if (!rowsBySections.length) {
    console.warn('No data to export for PDF');
    return;
  }

  try {
    // Check for jsPDF - it's exposed as window.jspdf in UMD build
    if (!window.jspdf) {
      console.error('Available globals:', Object.keys(window).filter(k => k.includes('jsPDF') || k.includes('pdf')));
      throw new Error('jsPDF library not loaded. Please refresh the page and try again.');
    }

    const jsPDFModule = window.jspdf;
    if (!jsPDFModule.jsPDF) {
      throw new Error('jsPDF class not found in jspdf module');
    }

    const { jsPDF } = jsPDFModule;
    const doc = new jsPDF();
    
    // Verify autoTable is available
    if (!doc.autoTable) {
      throw new Error('jspdf-autotable plugin not loaded. Please refresh the page.');
    }

    const today = new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });

    doc.setFontSize(14);
    doc.text(title, 14, 16);
    doc.setFontSize(10);
    doc.text(`Exported ${today}`, 14, 22);

    let cursorY = 30;
    rowsBySections.forEach(({ title: sectionTitle, rows }) => {
      // Check if we need a new page
      if (cursorY > 250) {
        doc.addPage();
        cursorY = 15;
      }

      doc.setFontSize(12);
      doc.text(sectionTitle, 14, cursorY);

      const headers = Object.keys(rows[0] || {});
      if (headers.length === 0) {
        console.warn('No headers found for section:', sectionTitle);
        return;
      }

      doc.autoTable({
        startY: cursorY + 4,
        head: [headers],
        body: rows.map((row) => headers.map((h) => String(row[h] ?? ''))),
        styles: { fontSize: 9 },
        headStyles: { fillColor: [210, 167, 167], textColor: [28, 14, 12] },
        margin: { left: 14, right: 14 },
      });

      cursorY = doc.lastAutoTable.finalY + 14;
    });

    doc.save(filename);
    console.log('PDF exported successfully:', filename);
  } catch (error) {
    console.error('PDF export error:', error);
    throw error;
  }
}

function wireReports() {
  const panel = document.getElementById('a-reports');
  if (!panel) {
    console.warn('Reports panel not found');
    return;
  }
  const tileEls = Array.from(panel.querySelectorAll('.report-tile[data-key]'));
  const csvBtn = panel.querySelector('#btn-export-reports-csv');
  const pdfBtn = panel.querySelector('#btn-export-reports-pdf');
  if (!csvBtn || !pdfBtn) {
    console.warn('Export buttons not found', { csvBtn: !!csvBtn, pdfBtn: !!pdfBtn });
    return;
  }

  function selectedCards() {
    return tileEls.filter((tile) => (tile.dataset.range || 'none') !== 'none');
  }

  function updateExportButtons() {
    const hasSelection = selectedCards().length > 0;
    csvBtn.disabled = !hasSelection;
    pdfBtn.disabled = !hasSelection;
  }

  tileEls.forEach((tile) => {
    tile.querySelectorAll('[data-report-range]').forEach((btn) => {
      btn.addEventListener('click', () => {
        tile.dataset.range = btn.dataset.reportRange;
        tile.querySelectorAll('[data-report-range]').forEach((rangeBtn) => {
          rangeBtn.classList.toggle('active', rangeBtn === btn);
        });
        updateExportButtons();
      });
    });
  });

  function rangeLabel(range) {
    return range.charAt(0).toUpperCase() + range.slice(1);
  }

  function selectedSections() {
    return selectedCards()
      .map((tile) => {
        const data = TILE_DATA[tile.dataset.key];
        if (!data) return null;
        const range = tile.dataset.range || 'none';
        return {
          title: `${data.title} - ${rangeLabel(range)}`,
          rows: data.build(),
        };
      })
      .filter((section) => section.rows.length);
  }

  function exportFilename(ext) {
    const today = todayIso();
    const cards = selectedCards();
    if (cards.length === 1) {
      const card = cards[0];
      return `report-${card.dataset.key}-${card.dataset.range}-${today}.${ext}`;
    }
    return `report-export-${today}.${ext}`;
  }

  csvBtn.addEventListener('click', () => {
    try {
      const sections = selectedSections();
      if (!sections.length) {
        alert('No data available for export. Please select a time range and ensure there is data to export.');
        return;
      }
      downloadCsv(exportFilename('csv'), { sections });
    } catch (error) {
      console.error('CSV export error:', error);
      alert('Failed to export CSV: ' + error.message);
    }
  });

  pdfBtn.addEventListener('click', () => {
    try {
      const sections = selectedSections();
      if (!sections.length) {
        alert('No data available for export. Please select a time range and ensure there is data to export.');
        return;
      }
      
      // Check if libraries are loaded
      if (!window.jspdf) {
        alert('PDF library is loading. Please wait a moment and try again.');
        console.error('jsPDF not available on window object');
        return;
      }
      
      const title = sections.length === 1 ? sections[0].title : 'Report Export';
      downloadPdf(exportFilename('pdf'), title, sections);
    } catch (error) {
      console.error('PDF export error:', error);
      alert('Failed to export PDF: ' + error.message);
    }
  });

  updateExportButtons();
}

// ---- Settings: toggle switches + dirty-state save ----
function wireSettings() {
  const panel = document.getElementById('a-settings');
  if (!panel) return;

  panel.querySelectorAll('[data-toggle]').forEach((toggle) => {
    toggle.addEventListener('click', () => toggle.classList.toggle('on'));
  });

  const nameEl = panel.querySelector('#setting-company-name');
  const startEl = panel.querySelector('#setting-working-start');
  const endEl = panel.querySelector('#setting-working-end');
  const thresholdEl = panel.querySelector('#setting-late-threshold');
  const saveBtn = panel.querySelector('#btn-save-system-settings');
  const successEl = panel.querySelector('#settings-save-success');
  const errorEl = panel.querySelector('#settings-save-error');
  if (!saveBtn) {
    console.warn('Settings save button not found');
    return;
  }

  function markDirty() {
    if (successEl) successEl.style.display = 'none';
    if (errorEl) errorEl.style.display = 'none';
    saveBtn.disabled = false;
  }
  [nameEl, startEl, endEl, thresholdEl].forEach((el) => el?.addEventListener('input', markDirty));

  saveBtn.addEventListener('click', async () => {
    try {
      const payload = {
        company_name: (nameEl?.value || '').trim(),
        working_hours_start: startEl?.value || '08:00',
        working_hours_end: endEl?.value || '17:00',
        late_threshold_minutes: Number(thresholdEl?.value || 10),
      };

      const response = await fetch(`${API_ROOT}/api/admin/settings`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify(payload),
      });

      const body = await response.json().catch(() => ({}));
      if (!response.ok) {
        const message = body.error?.message || 'Unable to save settings';
        throw new Error(message);
      }

      if (successEl) {
        successEl.style.display = 'block';
        setTimeout(() => {
          successEl.style.display = 'none';
        }, 3000);
      }
      saveBtn.disabled = true;
      console.log('Settings saved successfully');
    } catch (error) {
      console.error('Settings save error:', error);
      if (errorEl) {
        errorEl.textContent = error.message;
        errorEl.style.display = 'block';
      } else {
        alert(error.message);
      }
    }
  });
}

function wireAdminInvite() {
  const inviteBtn = document.getElementById('btn-open-invite-admin');
  const modal = document.getElementById('modal-invite-admin');
  if (!inviteBtn || !modal) return;

  const nameEl = modal.querySelector('#invite-admin-name');
  const idEl = modal.querySelector('#invite-admin-employee-id');
  const emailEl = modal.querySelector('#invite-admin-email');
  const passEl = modal.querySelector('#invite-admin-password');
  const passConfirmEl = modal.querySelector('#invite-admin-password-confirm');
  const errorEl = modal.querySelector('#invite-admin-error');
  const createBtn = modal.querySelector('#btn-invite-admin-create');
  const cancelBtn = modal.querySelector('#btn-invite-admin-cancel');

  function open() { modal.classList.add('active'); }
  function close() { modal.classList.remove('active'); }

  function validate() {
    const name = nameEl.value.trim();
    const empId = idEl.value.trim();
    const email = emailEl.value.trim();
    const pass = passEl.value;
    const passc = passConfirmEl.value;
    const ok = name && empId && email && pass && pass === passc;
    errorEl.style.display = ok ? 'none' : 'block';
    return ok;
  }

  inviteBtn.addEventListener('click', () => {
    nameEl.value = '';
    idEl.value = '';
    emailEl.value = '';
    passEl.value = '';
    passConfirmEl.value = '';
    errorEl.style.display = 'none';
    open();
  });

  cancelBtn.addEventListener('click', () => close());

  createBtn.addEventListener('click', () => {
    if (!validate()) return;
    postForm('admin/actions/create_admin.php', {
      name: nameEl.value.trim(),
      employee_id: idEl.value.trim(),
      email: emailEl.value.trim(),
      password: passEl.value,
      password_confirm: passConfirmEl.value,
    });
  });
}

wireEmployees();
wireAttendance();
wireLeave();
startLeavePolling();

// Ensure PDF libraries are loaded before wiring reports
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => {
    wireReports();
    wireSettings();
    wireAdminInvite();
  });
} else {
  wireReports();
  wireSettings();
  wireAdminInvite();
}
