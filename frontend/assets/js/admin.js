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
        <button class="btn btn-outline" id="confirm-modal-confirm" type="button">Confirm</button>
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

// ---- Styled alert dialog ----
// Replaces the browser's native alert() so decision feedback matches the
// app's UI. Lazily builds one overlay and reuses it, like confirmDialog().
let alertOverlay = null;

function ensureAlertOverlay() {
  if (alertOverlay) return alertOverlay;

  alertOverlay = document.createElement('div');
  alertOverlay.className = 'modal-overlay';
  alertOverlay.id = 'alert-modal-overlay';
  alertOverlay.innerHTML = `
    <div class="modal" style="max-width:380px;">
      <p id="alert-modal-message" style="margin-bottom:20px; font-size:14px;"></p>
      <div style="display:flex; justify-content:flex-end;">
        <button class="btn btn-pink" id="alert-modal-ok" type="button">OK</button>
      </div>
    </div>
  `;
  document.body.appendChild(alertOverlay);
  return alertOverlay;
}

function alertDialog(message, okLabel = 'OK') {
  const el = ensureAlertOverlay();
  el.querySelector('#alert-modal-message').textContent = message || '';

  const okBtn = el.querySelector('#alert-modal-ok');
  okBtn.textContent = okLabel;

  function close() {
    el.classList.remove('active');
    okBtn.removeEventListener('click', close);
  }

  okBtn.addEventListener('click', close);

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

// ---- EmailJS configuration ----
// EmailJS is loaded via CDN in portal.php: no package installation or SMTP
// configuration is required. Its public key, service ID and template ID are
// read from backend/.env and safely exposed here by portal.php.
const EMAILJS_CONFIG = window.TAPP_EMAILJS_CONFIG || {};

function emailJsIsConfigured() {
  return Boolean(
    window.emailjs
    && EMAILJS_CONFIG.publicKey
    && EMAILJS_CONFIG.serviceId
    && EMAILJS_CONFIG.templateId
  );
}

async function sendWelcomeEmailViaEmailJS(employee) {
  if (!emailJsIsConfigured()) {
    const reason = 'EmailJS is not configured. Add EMAILJS_PUBLIC_KEY, EMAILJS_SERVICE_ID and EMAILJS_TEMPLATE_ID to backend/.env.';
    console.error(reason);
    return { sent: false, reason };
  }

  try {
    emailjs.init({ publicKey: EMAILJS_CONFIG.publicKey });
    await emailjs.send(EMAILJS_CONFIG.serviceId, EMAILJS_CONFIG.templateId, {
      employee_name: employee.name,
      employee_id: employee.employee_id,
      employee_email: employee.email,
      department: employee.department || '',
      position: employee.position || '',
      temporary_password: employee.password,
    });
    return { sent: true, reason: '' };
  } catch (error) {
    console.error('EmailJS failed to send the welcome email:', error);
    return {
      sent: false,
      reason: error?.text || error?.message || 'EmailJS rejected the request. Check the browser console for more detail.',
    };
  }
}

// ---- Password generation helper ----
function generateRandomPassword(length = 12) {
  const upper = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
  const lower = 'abcdefghijklmnopqrstuvwxyz';
  const numbers = '0123456789';
  const symbols = '!@#$%^&*';
  const all = upper + lower + numbers + symbols;

  let password = '';
  password += upper[Math.floor(Math.random() * upper.length)];
  password += lower[Math.floor(Math.random() * lower.length)];
  password += numbers[Math.floor(Math.random() * numbers.length)];
  password += symbols[Math.floor(Math.random() * symbols.length)];

  for (let i = password.length; i < length; i++) {
    password += all[Math.floor(Math.random() * all.length)];
  }

  return password.split('').sort(() => Math.random() - 0.5).join('');
}

// ---- Employees: directory, search/filter, register/edit/delete ----

// employee_id format is S-### — take the highest existing number
// (across all rows, not just visible ones, so filtering doesn't skip IDs)
// and increment it, rather than anything random.
function nextEmployeeId(tbody) {
  let max = 0;
  tbody.querySelectorAll('tr[data-id]').forEach((row) => {
    const n = Number((row.dataset.id || '').split('-')[1]);
    if (Number.isFinite(n) && n > max) max = n;
  });
  return `S-${String(max + 1).padStart(3, '0')}`;
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
  const passwordEl = panel.querySelector('#emp-password-input');
  const regenerateBtn = panel.querySelector('#btn-regenerate-password');
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
    passwordEl.value = generateRandomPassword();
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

  regenerateBtn?.addEventListener('click', (e) => {
    e.preventDefault();
    passwordEl.value = generateRandomPassword();
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
        // Local PHP action (avoids broken HTTP loopback to /backend/public).
        // Password is always generated server-side.
        const response = await fetch('actions/register_employee.php', {
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

        const created = body.data || body || {};
        const temporaryPassword = created.temporary_password || body.temporary_password || '';
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

        // Show the auto-generated password so admin can copy / share it
        if (passwordEl) {
          passwordEl.value = temporaryPassword;
        }

        // Show success message
        if (errorEl) {
          errorEl.style.display = 'none';
        }

        const emailResult = await sendWelcomeEmailViaEmailJS({
          name,
          employee_id: created.employee_id || nextEmployeeId(tbody),
          email,
          department,
          position,
          password: temporaryPassword,
        });

        if (emailResult.sent) {
          alert(`Employee registered successfully!\n\nLogin details:\nEmployee ID: ${created.employee_id}\nTemporary password: ${temporaryPassword}\n\nWelcome email sent to ${email}.\nEmployee must change this password on first login.`);
        } else {
          alert(`Employee registered successfully!\n\nLogin details:\nEmployee ID: ${created.employee_id}\nTemporary password: ${temporaryPassword}\n\n(Email could not be sent: ${emailResult.reason})\nEmployee must change this password on first login.`);
        }
      } catch (error) {
        const msg = error.message || 'Unable to register employee';
        if (errorEl) {
          errorEl.textContent = msg;
          errorEl.style.display = 'block';
        }
        alert('Registration failed: ' + msg);
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

// ---- Live dashboard rendering (SSE + polling fallback) ----
// Mirrors the PHP helpers in admin/data.php so re-rendered markup matches
// the initial server-side render exactly.

const FEED_DOT_COLORS = {
  clock_in: '#9aa574',
  clock_out: '#d2a7a7',
  leave_applied: '#d3ac77',
  leave_decided: '#9aa574',
};
const FEED_TEXTS = {
  clock_in: 'clocked in',
  clock_out: 'clocked out',
  leave_applied: 'applied for leave',
  leave_decided: 'had a leave request decided',
};

function feedDotColor(type) {
  return FEED_DOT_COLORS[type] || '#cdb9ab';
}
function feedText(type) {
  return FEED_TEXTS[type] || type || '';
}

function attendanceBadge(status, isLate) {
  const map = {
    present: ['badge-offsite', 'Offsite'],
    offsite: ['badge-offsite', 'Offsite'],
    onsite: ['badge-onsite', 'Onsite'],
    absent: ['badge-absent', 'Absent'],
  };
  const [cls, label] = map[status] || map.offsite;
  return { class: cls, label };
}

function employeeBadge(status) {
  const map = {
    onsite: ['badge-onsite', 'Onsite'],
    present: ['badge-offsite', 'Offsite'],
    offsite: ['badge-offsite', 'Offsite'],
    absent: ['badge-absent', 'Absent'],
  };
  const [cls, label] = map[status] || map.offsite;
  return { class: cls, label };
}

function initialsOfName(name) {
  const parts = String(name || '').trim().split(/\s+/);
  return ((parts[0]?.[0] ?? '') + (parts[parts.length - 1]?.[0] ?? '')).toUpperCase();
}

function renderStats(stats) {
  const s = stats || {};
  const set = (id, val) => {
    const el = document.getElementById(id);
    if (el) el.textContent = String(val ?? 0);
  };
  set('stat-onsite', s.employees_onsite);
  set('stat-checked-in', s.checked_in_today);
  set('stat-late', s.late_arrivals);
  set('stat-absent', s.employees_absent);
  set('bloom-rate', `${s.on_time_rate_pct ?? 0}%`);
  set('bloom-onsite', s.employees_onsite);
  set('bloom-late', s.late_arrivals);
  set('bloom-absent', s.employees_absent);
}

function renderLiveFeed(feed) {
  const list = document.getElementById('live-feed-list');
  if (!list) return;
  const items = feed || [];
  if (!items.length) {
    list.innerHTML = '<p class="muted" style="padding:12px 0;">No activity yet today.</p>';
    return;
  }
  list.innerHTML = items.map((item) => `
    <div class="feed-item">
      <span class="feed-time">${escapeHtml(item.time_label)}</span>
      <span class="feed-dot" style="background:${feedDotColor(item.event_type)};"></span>
      <span class="feed-text"><b>${escapeHtml(item.employee_name)}</b> ${escapeHtml(feedText(item.event_type))}</span>
    </div>
  `).join('');
}

function renderLateArrivals(lateList) {
  const wrap = document.getElementById('late-arrivals-list');
  if (!wrap) return;
  const late = lateList || [];
  if (!late.length) {
    wrap.innerHTML = '<p class="muted" style="padding:12px 0;">No late arrivals today. 🎉</p>';
    return;
  }
  wrap.innerHTML = `
    <div class="table-wrap">
      <table>
        <thead><tr><th>Employee</th><th>Clock In</th><th>Minutes Late</th></tr></thead>
        <tbody>
          ${late.map((row) => `
            <tr>
              <td><div class="emp-cell"><div class="avatar">${escapeHtml(initialsOfName(row.name))}</div><div class="emp-name">${escapeHtml(row.name)}</div></div></td>
              <td>${escapeHtml(row.clock_in)}</td>
              <td><span class="badge badge-late">${Number(row.minutes_late || 0)} min</span></td>
            </tr>
          `).join('')}
        </tbody>
      </table>
    </div>
  `;
}

function renderAttendanceTable(attendance) {
  const tbody = document.getElementById('attendance-tbody');
  if (!tbody) return;
  const rows = attendance || [];
  tbody.innerHTML = rows.map((entry) => {
    const badge = attendanceBadge(entry.status, entry.is_late);
    return `
      <tr data-name="${escapeHtml(entry.name)}">
        <td><div class="emp-cell"><div class="avatar">${escapeHtml(entry.initials)}</div><div class="emp-name">${escapeHtml(entry.name)}</div></div></td>
        <td>${escapeHtml(entry.clock_in ?? '—')}</td>
        <td>${escapeHtml(entry.clock_out ?? '—')}</td>
        <td>${escapeHtml(entry.total_hours ?? '—')}</td>
        <td><span class="badge ${badge.class}">${escapeHtml(badge.label)}</span></td>
      </tr>
    `;
  }).join('');

  // Re-apply the attendance search filter after re-render.
  const searchEl = document.getElementById('attendance-search');
  if (searchEl && searchEl.value.trim()) {
    const query = searchEl.value.trim().toLowerCase();
    tbody.querySelectorAll('tr[data-name]').forEach((row) => {
      row.style.display = (row.dataset.name || '').toLowerCase().includes(query) ? '' : 'none';
    });
  }
}

function renderEmployeeStatuses(employees) {
  const tbody = document.getElementById('employee-tbody');
  if (!tbody) return;
  const emps = employees || [];
  emps.forEach((emp) => {
    const row = tbody.querySelector(`tr[data-id="${CSS.escape(emp.employee_id)}"]`);
    if (!row) return;
    const badge = employeeBadge(emp.today_attendance_status);
    const cell = row.children[4];
    if (cell) {
      cell.innerHTML = `<span class="badge ${badge.class}">${escapeHtml(badge.label)}</span>`;
    }
  });
}

// Fetches the same dashboard endpoint portal.php uses for its initial data,
// and re-renders every dynamic section (stats, feed, bloom, late arrivals,
// attendance table, employee statuses, leave lists).
async function refreshDashboard() {
  const refreshBtn = document.getElementById('btn-refresh-leave');
  try {
    if (refreshBtn) refreshBtn.disabled = true;
    const response = await fetch(`${API_ROOT}/api/admin/dashboard`, {
      credentials: 'include',
    });
    if (!response.ok) {
      const body = await response.json().catch(() => ({}));
      throw new Error(body.error?.message || 'Unable to refresh dashboard');
    }
    const body = await response.json();
    renderDashboard(body.data || {});
  } catch (error) {
    console.error('Dashboard refresh error:', error);
  } finally {
    if (refreshBtn) refreshBtn.disabled = false;
  }
}

function renderPasswordResets(data) {
  const tbody = document.getElementById('password-reset-tbody');
  if (!tbody) return;
  const requests = (data && data.password_reset_requests) || [];
  if (!requests.length) {
    tbody.innerHTML = '<tr><td colspan="5" style="opacity:0.6;">No password reset requests.</td></tr>';
    return;
  }
  tbody.innerHTML = requests.map((r) => {
    const pending = r.status === 'pending';
    const actions = pending
      ? `<button class="btn btn-pink" type="button" data-pr-approve="${escapeHtml(r.id)}">Approve</button>
         <button class="btn btn-outline" type="button" data-pr-reject="${escapeHtml(r.id)}">Reject</button>`
      : '—';
    return `<tr data-id="${escapeHtml(r.id)}">
      <td>${escapeHtml(r.employee_id || '')}</td>
      <td>${escapeHtml(r.email || '')}</td>
      <td>${escapeHtml(r.created_at || '')}</td>
      <td><span class="badge">${escapeHtml(r.status || '')}</span></td>
      <td class="pr-actions-col"><div style="display:flex;gap:8px;flex-wrap:wrap;">${actions}</div></td>
    </tr>`;
  }).join('');
}

function renderDashboard(data) {
  renderStats(data.dashboard_stats);
  renderLiveFeed(data.live_feed);
  renderLateArrivals(data.late_arrivals_list);
  renderAttendanceTable(data.attendance_monitoring);
  renderEmployeeStatuses(data.employees);
  renderLeaveLists(data);
  renderPasswordResets(data);
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
// that streams "update" events whenever anything on the dashboard changes
// (clock in/out, leave requests, late arrivals, employee presence). The
// browser's EventSource is built-in — no packages. EventSource auto-reconnects
// when the server ends the stream (the PHP side caps each connection at
// ~25s), so this is effectively continuous with ~2-3s of latency at most.
function startLeavePolling(intervalMs = 30000) {
  const panel = document.getElementById('a-leave');
  if (!panel) return;

  // SSE stream (primary, near-instant updates). Relative to the admin portal
  // page, which lives in the same /admin/ directory as this endpoint.
  const url = 'stream_updates.php';
  try {
    leaveEventSource = new EventSource(url);
    leaveEventSource.addEventListener('update', (event) => {
      try {
        const data = JSON.parse(event.data);
        renderDashboard(data);
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
      refreshDashboard();
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
      // Update the whole dashboard live instead of reloading the page.
      await refreshDashboard();
    } catch (error) {
      alert(error.message || 'Unable to update leave request');
      btn.disabled = false;
    }
  });

  // Manual refresh button in the Pending Requests card header.
  const refreshBtn = document.getElementById('btn-refresh-leave');
  refreshBtn?.addEventListener('click', refreshDashboard);
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

// Computes the [startDate, endDate] pair for a report range. Daily = today,
// weekly = last 7 days, monthly = last 30 days (all inclusive).
function reportDateRange(range) {
  const end = new Date();
  const start = new Date();
  if (range === 'weekly') {
    start.setDate(start.getDate() - 6);
  } else if (range === 'monthly') {
    start.setDate(start.getDate() - 29);
  }
  const iso = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
  return { start: iso(start), end: iso(end) };
}

// Fetches attendance report rows for a date range from the backend API.
// Falls back to the DOM (today's data) if the API call fails.
async function fetchReportRows(range) {
  const { start, end } = reportDateRange(range);
  try {
    const response = await fetch(
      `${API_ROOT}/api/admin/reports/attendance?start=${encodeURIComponent(start)}&end=${encodeURIComponent(end)}`,
      { credentials: 'include' }
    );
    if (!response.ok) {
      throw new Error(`API ${response.status}`);
    }
    const body = await response.json();
    return (body.data && body.data.rows) || [];
  } catch (error) {
    console.warn('Report API fetch failed, falling back to DOM data:', error);
    return null; // signal caller to fall back
  }
}

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

  // Builds export sections. For attendance-based tiles (daily, employee_hours)
  // it fetches rows from the backend API for the selected date range so
  // weekly/monthly exports include the full range, not just today's DOM data.
  // Falls back to the DOM (today's data) if the API is unreachable.
  async function selectedSections() {
    const cards = selectedCards();
    const sections = [];

    for (const tile of cards) {
      const data = TILE_DATA[tile.dataset.key];
      if (!data) continue;
      const range = tile.dataset.range || 'none';

      let rows;
      if (tile.dataset.key === 'daily' || tile.dataset.key === 'employee_hours') {
        const apiRows = await fetchReportRows(range);
        if (apiRows !== null) {
          // Map API rows to the export column shape.
          if (tile.dataset.key === 'daily') {
            rows = apiRows.map((r) => ({
              Date: r.date || '',
              Employee: r.name || '',
              'Clock In': r.clock_in || '',
              'Clock Out': r.clock_out || '',
              Hours: r.total_hours ?? '',
              Status: r.status ? r.status.charAt(0).toUpperCase() + r.status.slice(1) : '',
            }));
          } else {
            rows = apiRows.map((r) => ({
              Date: r.date || '',
              Employee: r.name || '',
              Hours: r.total_hours ?? '',
            }));
          }
        } else {
          rows = data.build();
        }
      } else {
        rows = data.build();
      }

      if (rows && rows.length) {
        sections.push({
          title: `${data.title} - ${rangeLabel(range)}`,
          rows,
        });
      }
    }

    return sections;
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

  csvBtn.addEventListener('click', async () => {
    try {
      csvBtn.disabled = true;
      const sections = await selectedSections();
      if (!sections.length) {
        alert('No data available for export. Please select a time range and ensure there is data to export.');
        return;
      }
      downloadCsv(exportFilename('csv'), { sections });
    } catch (error) {
      console.error('CSV export error:', error);
      alert('Failed to export CSV: ' + error.message);
    } finally {
      csvBtn.disabled = false;
    }
  });

  pdfBtn.addEventListener('click', async () => {
    try {
      pdfBtn.disabled = true;
      const sections = await selectedSections();
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
    } finally {
      pdfBtn.disabled = false;
    }
  });

  updateExportButtons();
}

// ---- Settings: Google Sheets sync ----
function wireGoogleSheets() {
  const panel = document.getElementById('a-settings');
  if (!panel) return;

  const toggleBtn = panel.querySelector('#toggle-google-sheets');
  const urlEl = panel.querySelector('#setting-google-sheets-url');
  const saveBtn = panel.querySelector('#btn-save-google-sheets');
  const testBtn = panel.querySelector('#btn-test-google-sheets');
  const errorEl = panel.querySelector('#google-sheets-error');
  const successEl = panel.querySelector('#google-sheets-success');
  if (!toggleBtn || !urlEl || !saveBtn || !testBtn) return;

  let syncEnabled = toggleBtn.getAttribute('aria-pressed') === 'true';

  function showError(message) {
    if (successEl) successEl.style.display = 'none';
    if (errorEl) {
      errorEl.textContent = message;
      errorEl.style.display = 'block';
    }
  }

  function showSuccess(message) {
    if (errorEl) errorEl.style.display = 'none';
    if (successEl) {
      successEl.textContent = message;
      successEl.style.display = 'block';
    }
  }

  toggleBtn.addEventListener('click', () => {
    syncEnabled = !syncEnabled;
    toggleBtn.classList.toggle('on', syncEnabled);
    toggleBtn.setAttribute('aria-pressed', String(syncEnabled));
    if (successEl) successEl.style.display = 'none';
    if (errorEl) errorEl.style.display = 'none';
  });

  saveBtn.addEventListener('click', async () => {
    try {
      const payload = {
        google_sheets_sync_enabled: syncEnabled ? 1 : 0,
        google_sheets_webhook_url: (urlEl.value || '').trim(),
      };

      const response = await fetch(`${API_ROOT}/api/admin/settings`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify(payload),
      });

      const body = await response.json().catch(() => ({}));
      if (!response.ok) {
        const message = body.error?.message || 'Unable to save Google Sheets settings';
        throw new Error(message);
      }

      showSuccess('Google Sheets sync settings saved.');
      setTimeout(() => {
        if (successEl) successEl.style.display = 'none';
      }, 3000);
    } catch (error) {
      showError(error.message);
    }
  });

  testBtn.addEventListener('click', async () => {
    const url = (urlEl.value || '').trim();
    if (!url) {
      showError('Please enter the Google Apps Script Web App URL first.');
      return;
    }

    testBtn.disabled = true;
    try {
      const response = await fetch(`${API_ROOT}/api/admin/settings/test-google-sheets`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({ url }),
      });

      const body = await response.json().catch(() => ({}));
      if (!response.ok) {
        const message = body.error?.message || 'Connection test failed';
        throw new Error(message);
      }

      showSuccess(body.data?.message || 'Connection successful! A test row was added to your spreadsheet.');
    } catch (error) {
      showError(error.message);
    } finally {
      testBtn.disabled = false;
    }
  });
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
    const email = emailEl.value.trim();
    const pass = passEl.value;
    const passc = passConfirmEl.value;
    const ok = name && email && pass && pass === passc;
    errorEl.style.display = ok ? 'none' : 'block';
    return ok;
  }

  inviteBtn.addEventListener('click', () => {
    nameEl.value = '';
    emailEl.value = '';
    passEl.value = '';
    passConfirmEl.value = '';
    errorEl.style.display = 'none';
    open();
  });

  cancelBtn.addEventListener('click', () => close());

  createBtn.addEventListener('click', async () => {
    if (!validate()) return;

    createBtn.disabled = true;
    try {
      const response = await fetch(`${API_ROOT}/api/admin/admins/invite`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({
          name: nameEl.value.trim(),
          email: emailEl.value.trim(),
          password: passEl.value,
          password_confirm: passConfirmEl.value,
        }),
      });

      const body = await response.json().catch(() => ({}));
      if (!response.ok) {
        const message = body.error?.message || 'Unable to create admin account';
        throw new Error(message);
      }

      close();
      // Refresh the admin list if it's rendered.
      loadAdmins();
      window.alert('Admin account created successfully.');
    } catch (error) {
      errorEl.textContent = error.message;
      errorEl.style.display = 'block';
    } finally {
      createBtn.disabled = false;
    }
  });
}

// ---- Manage Admins: list, promote, demote ----
// Fetches the current admin list and renders it in the Manage Admins card.
// Also wires the "Promote to Admin" action for staff users and the
// "Demote" action for existing admins.
async function loadAdmins() {
  const listEl = document.getElementById('admin-list');
  if (!listEl) return;

  try {
    const response = await fetch(`${API_ROOT}/api/admin/admins`, {
      credentials: 'include',
    });
    if (!response.ok) {
      const body = await response.json().catch(() => ({}));
      throw new Error(body.error?.message || 'Unable to load admins');
    }
    const body = await response.json();
    const admins = body.data || [];

    if (!admins.length) {
      listEl.innerHTML = '<p class="muted" style="padding:8px 0;">No admin accounts found.</p>';
      return;
    }

    listEl.innerHTML = admins.map((admin) => `
      <div class="admin-row" data-employee-id="${escapeHtml(admin.employee_id)}">
        <div class="emp-cell">
          <div class="avatar">${escapeHtml(admin.initials)}</div>
          <div>
            <div class="emp-name">${escapeHtml(admin.name)}</div>
            <div class="muted" style="font-size:12px;">${escapeHtml(admin.email)}</div>
          </div>
        </div>
        <div class="admin-actions">
          <span class="badge badge-present">Admin</span>
          <button class="btn btn-outline btn-sm" data-action="demote" data-id="${escapeHtml(admin.employee_id)}" type="button">Demote</button>
        </div>
      </div>
    `).join('');

    // Wire demote buttons.
    listEl.querySelectorAll('[data-action="demote"]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const id = btn.dataset.id;
        const name = btn.closest('.admin-row')?.querySelector('.emp-name')?.textContent || id;
        confirmDialog(`Demote ${name} (${id}) back to staff? They will lose admin access.`, async () => {
          try {
            const response = await fetch(
              `${API_ROOT}/api/admin/admins/demote/${encodeURIComponent(id)}`,
              {
                method: 'PUT',
                credentials: 'include',
              }
            );
            const body = await response.json().catch(() => ({}));
            if (!response.ok) {
              const message = body.error?.message || 'Unable to demote admin';
              throw new Error(message);
            }
            await loadAdmins();
          } catch (error) {
            window.alert(error.message);
          }
        });
      });
    });
  } catch (error) {
    listEl.innerHTML = `<p class="muted" style="padding:8px 0;">${escapeHtml(error.message)}</p>`;
  }
}

// ---- Promote staff to admin ----
// Adds a "Promote to Admin" action to the employee table rows so an admin
// can convert a staff member or newly registered user into an admin.
function wirePromoteToAdmin() {
  const tbody = document.getElementById('employee-tbody');
  if (!tbody) return;

  // Add a promote button to each employee row's action cell.
  tbody.querySelectorAll('tr[data-id]').forEach((row) => {
    const actionsCell = row.querySelector('td:last-child');
    if (!actionsCell) return;
    if (actionsCell.querySelector('[data-action="promote"]')) return;

    const promoteBtn = document.createElement('button');
    promoteBtn.className = 'btn-icon';
    promoteBtn.title = 'Promote to Admin';
    promoteBtn.dataset.action = 'promote';
    promoteBtn.dataset.id = row.dataset.id;
    promoteBtn.type = 'button';
    promoteBtn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 013 3L7 19l-4 1 1-4 12.5-12.5z"/><path d="M12 8l1.5 3 3 1.5-3 1.5L12 17l-1.5-3-3-1.5 3-1.5L12 8z"/></svg>';
    actionsCell.appendChild(promoteBtn);

    promoteBtn.addEventListener('click', () => {
      const name = row.querySelector('.emp-name')?.textContent || '';
      const id = row.dataset.id;
      confirmDialog(`Promote ${name} (${id}) to admin? They will gain access to the admin portal.`, async () => {
        try {
          const response = await fetch(
            `${API_ROOT}/api/admin/admins/promote/${encodeURIComponent(id)}`,
            {
              method: 'PUT',
              credentials: 'include',
            }
          );
          const body = await response.json().catch(() => ({}));
          if (!response.ok) {
            const message = body.error?.message || 'Unable to promote user to admin';
            throw new Error(message);
          }
          // Remove the row from the employee table since they're now an admin.
          row.remove();
          // Refresh the admin list.
          loadAdmins();
          window.alert(`${name} has been promoted to admin.`);
        } catch (error) {
          window.alert(error.message);
        }
      });
    });
  });
}

wireEmployees();
wirePasswordResets();
wireAttendance();
wireLeave();
startLeavePolling();
wirePromoteToAdmin();

// Ensure PDF libraries are loaded before wiring reports
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => {
    wireReports();
    wireSettings();
    wireGoogleSheets();
    wireAdminInvite();
    loadAdmins();
  });
} else {
  wireReports();
  wireSettings();
  wireGoogleSheets();
  wireAdminInvite();
  loadAdmins();
}


// ---- Password reset requests (admin verification) ----
function wirePasswordResets() {
  const panel = document.getElementById('a-password-resets');
  if (!panel) return;
  const tbody = panel.querySelector('#password-reset-tbody');
  if (!tbody) return;

  async function loadRequests() {
    tbody.innerHTML = '<tr><td colspan="5" style="opacity:0.6;">Loading…</td></tr>';
    try {
      const response = await fetch('actions/list_password_resets.php', { credentials: 'include' });
      const body = await response.json().catch(() => ({}));
      if (!response.ok) throw new Error(body.error?.message || 'Failed to load');
      renderPasswordResets({ password_reset_requests: body.data?.requests || [] });
    } catch (e) {
      tbody.innerHTML = `<tr><td colspan="5" style="color:#c44;">${escapeHtml(e.message || 'Error')}</td></tr>`;
    }
  }

  tbody.addEventListener('click', (ev) => {
    const approveBtn = ev.target.closest('[data-pr-approve]');
    const rejectBtn = ev.target.closest('[data-pr-reject]');
    const id = approveBtn?.dataset.prApprove || rejectBtn?.dataset.prReject;
    if (!id) return;
    const decision = approveBtn ? 'approved' : 'rejected';
    const label = decision === 'approved' ? 'approve' : 'reject';

    confirmDialog(`Are you sure you want to ${label} this password reset request?`, async () => {
      try {
        const response = await fetch('actions/password_reset_decision.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'include',
          body: JSON.stringify({ request_id: Number(id), decision }),
        });
        const body = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(body.error?.message || 'Request failed');
        alertDialog(body.data?.message || 'Done');
        loadRequests();
      } catch (e) {
        alertDialog(e.message || 'Failed');
      }
    });
  });

  // Reload when tab is opened
  document.querySelectorAll('[data-panel="a-password-resets"]').forEach((btn) => {
    btn.addEventListener('click', () => loadRequests());
  });

  // Initial load if panel is somehow active
  if (panel.classList.contains('active')) loadRequests();
}

