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

function downloadCsv(filename, rows) {
  const lines = rowsToCsvLines(rows);
  if (!lines.length) return;

  const blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = filename;
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  URL.revokeObjectURL(url);
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

// New registrations always land with employment status "active" and no
// today_attendance_status yet — same fallback employees.js's status badge
// lookup resolves to for a fresh hire, so it's hardcoded here rather than
// re-implementing the ATTENDANCE_BADGE/EMPLOYMENT_BADGE lookup in JS.
function buildEmployeeRow(emp) {
  const tr = document.createElement('tr');
  tr.dataset.id = emp.employee_id;
  tr.dataset.email = emp.email;
  tr.innerHTML = `
    <td><div class="emp-cell"><div class="avatar">${escapeHtml(emp.initials)}</div><div><div class="emp-name">${escapeHtml(emp.name)}</div></div></div></td>
    <td>${escapeHtml(emp.employee_id)}</td>
    <td>${escapeHtml(emp.department)}</td>
    <td>${escapeHtml(emp.position)}</td>
    <td><span class="badge badge-present">Active</span></td>
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
    submitBtn.textContent = 'Register & Generate QR';
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
      deptEl.value = row.children[2].textContent;
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
      confirmDialog(`Remove ${name} (${id}) from the employee roster?`, () => {
        // Front-end only — no DELETE /api/employees/{id} endpoint yet.
        row.remove();
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

  submitBtn?.addEventListener('click', () => {
    if (!validate()) return;

    const name = nameEl.value.trim();
    // Front-end only — no POST/PATCH /api/employees endpoint yet.
    if (editingId !== null) {
      const row = tbody.querySelector(`tr[data-id="${editingId}"]`);
      if (row) {
        row.querySelector('.avatar').textContent = initialsOf(name);
        row.querySelector('.emp-name').textContent = name;
        row.children[2].textContent = deptEl.value.trim();
        row.children[3].textContent = positionEl.value.trim();
        row.dataset.email = emailEl.value.trim();
      }
    } else {
      const newRow = buildEmployeeRow({
        employee_id: nextEmployeeId(tbody),
        name,
        initials: initialsOf(name),
        email: emailEl.value.trim(),
        department: deptEl.value.trim(),
        position: positionEl.value.trim(),
      });
      wireRowActions(newRow);
      tbody.appendChild(newRow);
    }

    applyFilters();
    closeModal();
    resetForm();
  });
}

// ---- Attendance monitoring: search + scoped CSV export ----
// The status select/Filter button from the design-reference markup were
// dropped, same as the current build's attendance.js — attendanceMonitoring
// has no field they meaningfully filtered beyond what search now covers.
// Sort stays inert (matches the current build's deliberate no-op button).
function wireAttendance() {
  const panel = document.getElementById('a-attendance');
  if (!panel) return;
  const searchEl = panel.querySelector('#attendance-search');
  const tbody = panel.querySelector('#attendance-tbody');
  const exportBtn = panel.querySelector('#btn-export-attendance-csv');
  if (!tbody) return;

  // Filters by toggling row visibility in place (no in-memory
  // attendanceMonitoring array to re-map from, since data.php only rendered
  // the table once server-side) — CSV export below reads whatever's
  // currently visible, which keeps "scoped to filtered rows" true without
  // needing a parallel JS data source.
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

  exportBtn?.addEventListener('click', () => {
    const rows = [...tbody.querySelectorAll('tr[data-name]')]
      .filter((row) => row.style.display !== 'none')
      .map((row) => {
        const cells = row.querySelectorAll('td');
        const cellText = (i) => {
          const text = cells[i].textContent.trim();
          return text === '—' ? '' : text;
        };
        return {
          Employee: row.dataset.name,
          'Clock In': cellText(1),
          'Clock Out': cellText(2),
          Hours: cellText(3),
          Status: cellText(4),
        };
      });
    downloadCsv('attendance.csv', rows);
  });
}

wireEmployees();
wireAttendance();
