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
    return;
  }
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

// ---- Leave management: approve/decline ----
// Each decision now calls the backend API at /api/admin/leave-requests/{id}
// instead of the legacy actions/leave_decision.php endpoint.
const API_ROOT = '../../backend/public';

async function decideLeave(leaveId, decision) {
  return fetch(`${API_ROOT}/api/admin/leave-requests/${encodeURIComponent(leaveId)}`, {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json' },
    credentials: 'include',
    body: JSON.stringify({ status: decision }),
  });
}

function wireLeave() {
  const panel = document.getElementById('a-leave');
  if (!panel) return;
  const list = panel.querySelector('#leave-list');
  if (!list) return;

  list.querySelectorAll('[data-decision]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      const card = btn.closest('.leave-req-card');
      const response = await decideLeave(card.dataset.leaveId, btn.dataset.decision);
      const data = await response.json().catch(() => ({ message: 'Unable to update leave request' }));

      if (!response.ok) {
        alert(data.message || 'Unable to update leave request');
        return;
      }

      window.location.reload();
    });
  });
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
    return {
      Employee: row.dataset.name,
      'Clock In': attendanceCellText(cells, 1),
      'Clock Out': attendanceCellText(cells, 2),
      Hours: attendanceCellText(cells, 3),
      Status: attendanceCellText(cells, 4),
    };
  });
}

function buildEmployeeHoursRows() {
  const tbody = document.getElementById('attendance-tbody');
  if (!tbody) return [];
  return [...tbody.querySelectorAll('tr[data-name]')].map((row) => {
    const cells = row.querySelectorAll('td');
    return {
      Employee: row.dataset.name,
      'Hours Today': attendanceCellText(cells, 3),
    };
  });
}

function buildLeaveRows() {
  const cards = [
    ...document.querySelectorAll('#leave-list .leave-req-card'),
    ...document.querySelectorAll('#leave-history-list .leave-req-card'),
  ];
  return cards.map((card) => ({
    Employee: card.dataset.employeeName,
    'Leave Type': card.dataset.leaveTypeLabel,
    Days: card.dataset.durationDays,
    Reason: card.dataset.reason,
    Status: card.dataset.status.charAt(0).toUpperCase() + card.dataset.status.slice(1),
    'Decided On': card.dataset.decidedAt || '',
  }));
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
  if (!rowsBySections.length) return;

  const { jsPDF } = window.jspdf;
  const doc = new jsPDF();
  const today = new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });

  doc.setFontSize(14);
  doc.text(title, 14, 16);
  doc.setFontSize(10);
  doc.text(`Exported ${today}`, 14, 22);

  let cursorY = 30;
  rowsBySections.forEach(({ title: sectionTitle, rows }) => {
    doc.setFontSize(12);
    doc.text(sectionTitle, 14, cursorY);

    const headers = Object.keys(rows[0]);
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
}

function wireReports() {
  const panel = document.getElementById('a-reports');
  if (!panel) return;
  const tileEls = Array.from(panel.querySelectorAll('.report-tile[data-key]'));
  const csvBtn = panel.querySelector('#btn-export-reports-csv');
  const pdfBtn = panel.querySelector('#btn-export-reports-pdf');
  if (!csvBtn || !pdfBtn) return;

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
    const sections = selectedSections();
    if (!sections.length) return;
    downloadCsv(exportFilename('csv'), { sections });
  });

  pdfBtn.addEventListener('click', () => {
    const sections = selectedSections();
    if (!sections.length) return;
    const title = sections.length === 1 ? sections[0].title : 'Report Export';
    downloadPdf(exportFilename('pdf'), title, sections);
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

  const nameEl = panel.querySelector('#settings-company-name');
  const startEl = panel.querySelector('#settings-hours-start');
  const endEl = panel.querySelector('#settings-hours-end');
  const thresholdEl = panel.querySelector('#settings-late-threshold');
  const saveBtn = panel.querySelector('#btn-save-settings');
  const successEl = panel.querySelector('#settings-save-success');
  if (!saveBtn) return;

  function markDirty() {
    successEl.style.display = 'none';
    saveBtn.disabled = false;
  }
  [nameEl, startEl, endEl, thresholdEl].forEach((el) => el?.addEventListener('input', markDirty));

  saveBtn.addEventListener('click', async () => {
    const payload = {
      company_name: nameEl.value.trim(),
      working_hours_start: startEl.value,
      working_hours_end: endEl.value,
      late_threshold_minutes: Number(thresholdEl.value),
    };

    try {
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

      successEl.style.display = 'block';
      saveBtn.disabled = true;
    } catch (error) {
      alert(error.message);
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
wireReports();
wireSettings();
wireAdminInvite();
