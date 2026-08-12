<?php
require_once __DIR__ . '/../auth.php';
require_role('admin');
require_once __DIR__ . '/data.php';
$user = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>TAPP — Admin Portal</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,300;0,9..144,500;0,9..144,600;1,9..144,500&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/tokens.css">
<link rel="stylesheet" href="../assets/css/components/index.css">
<link rel="stylesheet" href="../assets/css/admin.css">
<!-- Reports tab PDF export — jsPDF + autoTable plugin, UMD builds. Loaded
     here only (not employee/portal.php, which has no PDF export). -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/4.2.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/5.0.8/jspdf.plugin.autotable.min.js"></script>
</head>
<body>

<div class="app-shell active" id="shell-admin">
  <div class="main-col">
    <div class="topbar">
      <div class="topbar-left">
        <button class="btn-icon nav-toggle-btn" id="nav-toggle" type="button" aria-label="Toggle menu" aria-expanded="false" aria-controls="nav-panel">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
        </button>
        <div class="topbar-title"><h2 id="a-panel-title">Dashboard</h2><p id="a-panel-sub">Live overview · <?= date('l, j F Y') ?></p></div>
      </div>
      <div class="user-chip">
        <div><div class="who" style="text-align:right;"><?= htmlspecialchars($user['name']) ?></div><div class="role" style="text-align:right;"><?= htmlspecialchars($user['role_label']) ?></div></div>
        <div class="avatar" style="background:var(--rust);"><?= htmlspecialchars($user['initials']) ?></div>
      </div>

      <aside class="sidebar" id="nav-panel">
        <div class="sidebar-brand">
          <svg class="lotus-mark" viewBox="0 0 100 100" fill="none">
            <path d="M50 78 C 38 68 32 54 38 42 C 44 54 48 60 50 78 Z" fill="#D2A7A7"/>
            <path d="M50 78 C 62 68 68 54 62 42 C 56 54 52 60 50 78 Z" fill="#D2A7A7" opacity="0.85"/>
            <path d="M50 78 C 42 62 42 46 50 34 C 58 46 58 62 50 78 Z" fill="#A18261"/>
          </svg>
          <span>TAPP</span>
        </div>
        <div class="nav-group-label">Admin Portal</div>
        <button class="nav-item active" data-panel="a-dashboard">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/></svg>
          Dashboard
        </button>
        <button class="nav-item" data-panel="a-employees">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c0-3.6 2.9-6 6.5-6s6.5 2.4 6.5 6"/><circle cx="18" cy="8.5" r="2.7"/><path d="M15.5 14.3c2.7.4 4.9 2.4 5 5.7"/></svg>
          Employees
        </button>
        <button class="nav-item" data-panel="a-attendance">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg>
          Attendance
        </button>
        <button class="nav-item" data-panel="a-leave">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 20 C4 12 8 5 14 3 C16 9 15 16 4 20Z"/></svg>
          Leave
        </button>
        <button class="nav-item" data-panel="a-reports">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 20V10M11 20V4M18 20v-7"/></svg>
          Reports
        </button>
        <button class="nav-item" data-panel="a-settings">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 00.3 1.9l.1.1a2 2 0 11-2.8 2.8l-.1-.1a1.7 1.7 0 00-1.9-.3 1.7 1.7 0 00-1 1.5V21a2 2 0 11-4 0v-.1a1.7 1.7 0 00-1-1.6 1.7 1.7 0 00-1.9.3l-.1.1a2 2 0 11-2.8-2.8l.1-.1a1.7 1.7 0 00.3-1.9 1.7 1.7 0 00-1.5-1H3a2 2 0 110-4h.1a1.7 1.7 0 001.5-1 1.7 1.7 0 00-.3-1.9l-.1-.1a2 2 0 112.8-2.8l.1.1a1.7 1.7 0 001.9.3H9a1.7 1.7 0 001-1.5V3a2 2 0 114 0v.1a1.7 1.7 0 001 1.5 1.7 1.7 0 001.9-.3l.1-.1a2 2 0 112.8 2.8l-.1.1a1.7 1.7 0 00-.3 1.9V9a1.7 1.7 0 001.5 1h.1a2 2 0 110 4h-.1a1.7 1.7 0 00-1.5 1z"/></svg>
          Settings
        </button>
        <div class="sidebar-foot">
          <a class="logout-btn" href="../logout.php">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><path d="M16 17l5-5-5-5M21 12H9"/></svg>
            Sign out
          </a>
        </div>
      </aside>
    </div>

    <div class="content">
      <!-- Dashboard -->
      <div class="tab-panel active" id="a-dashboard">
        <div class="grid grid-4" style="margin-bottom:20px;">
          <div class="card stat-card dot-green">
            <div class="stat-icon"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#9aa574" stroke-width="1.9"><path d="M4 20c0-4.4 3.6-7 8-7s8 2.6 8 7"/><circle cx="12" cy="8" r="4"/></svg></div>
            <div class="stat-num"><?= (int) $dashboardStats['employees_onsite'] ?></div>
            <div class="stat-label">Employees Onsite</div>
          </div>
          <div class="card stat-card dot-blue">
            <div class="stat-icon"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#D2A7A7" stroke-width="1.9"><path d="M20 6L9 17l-5-5"/></svg></div>
            <div class="stat-num"><?= (int) $dashboardStats['checked_in_today'] ?></div>
            <div class="stat-label">Checked In Today</div>
          </div>
          <div class="card stat-card dot-gold">
            <div class="stat-icon"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#d3ac77" stroke-width="1.9"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg></div>
            <div class="stat-num"><?= (int) $dashboardStats['late_arrivals'] ?></div>
            <div class="stat-label">Late Arrivals</div>
          </div>
          <div class="card stat-card dot-red">
            <div class="stat-icon"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#c26a52" stroke-width="1.9"><circle cx="12" cy="12" r="9"/><path d="M9 9l6 6M15 9l-6 6"/></svg></div>
            <div class="stat-num"><?= (int) $dashboardStats['employees_absent'] ?></div>
            <div class="stat-label">Employees Absent</div>
          </div>
        </div>

        <div class="grid grid-2">
          <div class="card">
            <div class="section-head"><h3>Live Feed</h3><span class="badge badge-present">Live</span></div>
            <?php foreach ($liveFeed as $item): ?>
            <div class="feed-item">
              <span class="feed-time"><?= htmlspecialchars($item['time_label']) ?></span>
              <span class="feed-dot" style="background:<?= htmlspecialchars(feed_dot_color($item['event_type'])) ?>;"></span>
              <span class="feed-text"><b><?= htmlspecialchars($item['employee_name']) ?></b> <?= htmlspecialchars(feed_text($item['event_type'])) ?></span>
            </div>
            <?php endforeach; ?>
          </div>
          <div class="card">
            <div class="section-head"><h3>Attendance Bloom</h3></div>
            <div class="bloom-wrap">
              <svg width="140" height="140" viewBox="0 0 140 140">
                <circle cx="70" cy="70" r="58" fill="none" stroke="rgba(210,167,167,0.12)" stroke-width="14"/>
                <circle cx="70" cy="70" r="58" fill="none" stroke="#9aa574" stroke-width="14" stroke-dasharray="316" stroke-dashoffset="55" stroke-linecap="round" transform="rotate(-90 70 70)"/>
                <circle cx="70" cy="70" r="58" fill="none" stroke="#d3ac77" stroke-width="14" stroke-dasharray="316" stroke-dashoffset="270" stroke-linecap="round" transform="rotate(59 70 70)"/>
                <circle cx="70" cy="70" r="58" fill="none" stroke="#c26a52" stroke-width="14" stroke-dasharray="316" stroke-dashoffset="295" stroke-linecap="round" transform="rotate(97 70 70)"/>
                <text x="70" y="65" text-anchor="middle" fill="#f3e9de" font-size="24" font-family="Fraunces, serif" font-weight="600"><?= (int) $dashboardStats['on_time_rate_pct'] ?>%</text>
                <text x="70" y="83" text-anchor="middle" fill="#cdb9ab" font-size="10">on-time rate</text>
              </svg>
              <div class="bloom-legend">
                <div class="lg-item"><span class="lg-dot" style="background:#9aa574;"></span> Present · <?= (int) $dashboardStats['employees_onsite'] ?></div>
                <div class="lg-item"><span class="lg-dot" style="background:#d3ac77;"></span> Late · <?= (int) $dashboardStats['late_arrivals'] ?></div>
                <div class="lg-item"><span class="lg-dot" style="background:#c26a52;"></span> Absent · <?= (int) $dashboardStats['employees_absent'] ?></div>
              </div>
            </div>
          </div>
        </div>

        <!-- Late arrivals list — employees who clocked in after the
             configured working-hours start + late threshold. -->
        <div class="card" style="margin-top:18px;">
          <div class="section-head">
            <h3>Late Arrivals</h3>
            <span class="muted">Working hours start <?= htmlspecialchars($systemSettings['working_hours_start']) ?> · <?= (int) $systemSettings['late_threshold_minutes'] ?> min grace</span>
          </div>
          <?php if (count($lateArrivalsList) === 0): ?>
            <p class="muted" style="padding:12px 0;">No late arrivals today. 🎉</p>
          <?php else: ?>
            <div class="table-wrap">
              <table>
                <thead><tr><th>Employee</th><th>Clock In</th><th>Minutes Late</th></tr></thead>
                <tbody>
                  <?php foreach ($lateArrivalsList as $late): ?>
                  <tr>
                    <td><div class="emp-cell"><div class="avatar"><?= htmlspecialchars(initials_of($late['name'])) ?></div><div class="emp-name"><?= htmlspecialchars($late['name']) ?></div></div></td>
                    <td><?= htmlspecialchars($late['clock_in']) ?></td>
                    <td><span class="badge badge-late"><?= (int) $late['minutes_late'] ?> min</span></td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Employees -->
      <div class="tab-panel" id="a-employees">
        <div class="card">
          <div class="toolbar">
            <input class="search-input" id="emp-search" placeholder="Search employees…">
            <select class="select-input" id="emp-department-filter">
              <?php foreach ($departmentOptions as $dept): ?>
              <option><?= htmlspecialchars($dept) ?></option>
              <?php endforeach; ?>
            </select>
            <button class="btn btn-pink btn-sm" id="btn-open-add-employee" type="button">+ Register Employee</button>
          </div>
          <div class="table-wrap">
            <table id="employee-table">
              <thead><tr><th>Employee</th><th>ID</th><th>Department</th><th>Position</th><th>Status</th><th></th></tr></thead>
              <tbody id="employee-tbody">
                <?php foreach ($employees as $emp): ?>
                <?php $status = employee_status_badge($emp); ?>
                <tr data-id="<?= htmlspecialchars($emp['employee_id']) ?>" data-email="<?= htmlspecialchars($emp['email']) ?>">
                  <td><div class="emp-cell"><div class="avatar"><?= htmlspecialchars($emp['initials']) ?></div><div><div class="emp-name"><?= htmlspecialchars($emp['name']) ?></div></div></div></td>
                  <td><?= htmlspecialchars($emp['employee_id']) ?></td>
                  <td><?= htmlspecialchars($emp['department']) ?></td>
                  <td><?= htmlspecialchars($emp['position']) ?></td>
                  <td><span class="badge <?= htmlspecialchars($status['class']) ?>"><?= htmlspecialchars($status['label']) ?></span></td>
                  <td>
                    <button class="btn-icon" title="Edit" data-action="edit" data-id="<?= htmlspecialchars($emp['employee_id']) ?>" type="button"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 013 3L7 19l-4 1 1-4 12.5-12.5z"/></svg></button>
                    <button class="btn-icon" title="Delete" data-action="delete" data-id="<?= htmlspecialchars($emp['employee_id']) ?>" type="button"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2m3 0l-1 14a2 2 0 01-2 2H7a2 2 0 01-2-2L4 6"/></svg></button>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

        <div class="modal-overlay" id="modal-add-employee">
          <div class="modal">
            <h3 id="employee-modal-title">Register Employee</h3>
            <div class="form-field" id="field-emp-name" style="margin-bottom:14px;"><label>Full Name</label><input type="text" id="emp-name-input" placeholder="e.g. Alicia Moreno"></div>
            <div class="form-row">
              <div class="form-field" id="field-emp-email"><label>Email</label><input type="text" id="emp-email-input" placeholder="name@tapp.co"></div>
              <div class="form-field" id="field-emp-department"><label>Department</label><input type="text" id="emp-department-input" placeholder="e.g. Engineering"></div>
            </div>
            <div class="form-row" style="margin-top:14px;">
              <div class="form-field" id="field-emp-position"><label>Position</label><input type="text" id="emp-position-input" placeholder="e.g. Backend Dev"></div>
              <div class="form-field"><label>Employee ID</label><input type="text" id="emp-id-input" placeholder="Auto-generated" disabled></div>
            </div>
            <p class="form-error" id="emp-form-error" style="display:none;">Please fill in all required fields above.</p>
            <div style="display:flex; gap:10px; margin-top:20px;">
              <!-- QR generation is intentionally unimplemented: it depends on
                   TokenController and the still-open NFC/QR hardware decision,
                   so submit only registers the employee. -->
              <button class="btn btn-pink" id="btn-register-employee" type="button">Register &amp; Generate QR</button>
              <button class="btn btn-outline" id="btn-cancel-add-employee" type="button">Cancel</button>
            </div>
          </div>
        </div>
      </div>

      <!-- Attendance monitoring -->
      <div class="tab-panel" id="a-attendance">
        <div class="card">
          <div class="toolbar">
            <input class="search-input" id="attendance-search" placeholder="Search by employee…">
            <button class="btn btn-outline btn-sm" type="button">Sort</button>
            <button class="btn btn-olive btn-sm" id="btn-export-attendance-csv" type="button">Export CSV</button>
          </div>
          <div class="table-wrap">
            <table>
              <thead><tr><th>Employee</th><th>Clock In</th><th>Clock Out</th><th>Hours</th><th>Status</th></tr></thead>
              <tbody id="attendance-tbody">
                <?php foreach ($attendanceMonitoring as $entry): ?>
                <?php $status = attendance_status_badge($entry['status'], $entry['is_late'] ?? false); ?>
                <tr data-name="<?= htmlspecialchars($entry['name']) ?>">
                  <td><div class="emp-cell"><div class="avatar"><?= htmlspecialchars($entry['initials']) ?></div><div class="emp-name"><?= htmlspecialchars($entry['name']) ?></div></div></td>
                  <td><?= htmlspecialchars($entry['clock_in'] ?? '—') ?></td>
                  <td><?= htmlspecialchars($entry['clock_out'] ?? '—') ?></td>
                  <td><?= htmlspecialchars($entry['total_hours'] ?? '—') ?></td>
                  <td><span class="badge <?= htmlspecialchars($status['class']) ?>"><?= htmlspecialchars($status['label']) ?></span></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Leave management -->
      <div class="tab-panel" id="a-leave">
        <div class="card">
          <div class="section-head"><h3>Pending Requests</h3><span class="muted" id="leave-pending-count"><?= count($pendingLeaveRequests) ?> awaiting review</span></div>
          <div id="leave-list">
            <?php foreach ($pendingLeaveRequests as $i => $req): ?>
            <?php
                $stroke = ['#D2A7A7', '#6C714F', '#6D382B'][$i % 3];
                $iconPath = $i % 3 === 2
                    ? '<path d="M12 3v6M12 21c-5-2-8-6-8-11 3 0 6 1.5 8 5 2-3.5 5-5 8-5 0 5-3 9-8 11Z"/>'
                    : '<path d="M4 20 C4 12 8 5 14 3 C16 9 15 16 4 20Z"/>';
                $dayLabel = $req['duration_days'] . ' day' . ($req['duration_days'] === 1 ? '' : 's');
            ?>
            <div class="leave-req-card" data-leave-id="<?= (int) $req['leave_id'] ?>" data-employee-name="<?= htmlspecialchars($req['employee_name']) ?>" data-leave-type-label="<?= htmlspecialchars($leaveTypeLabels[$req['leave_type']]) ?>" data-duration-days="<?= (int) $req['duration_days'] ?>" data-reason="<?= htmlspecialchars($req['reason']) ?>" data-status="pending" data-decided-at="">
              <div class="lr-main">
                <div class="lr-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="<?= $stroke ?>" stroke-width="1.8"><?= $iconPath ?></svg></div>
                <div>
                  <div class="lr-title"><?= htmlspecialchars($req['employee_name']) ?> — <?= htmlspecialchars($leaveTypeLabels[$req['leave_type']]) ?></div>
                  <div class="lr-sub"><?= htmlspecialchars($dayLabel) ?> · <?= htmlspecialchars($req['reason']) ?></div>
                </div>
              </div>
              <div class="leave-req-actions">
                <span class="badge badge-pending" style="margin-right:6px;">Pending</span>
                <button class="btn btn-olive btn-sm" data-decision="approved" type="button">Approve</button>
                <button class="btn btn-rust btn-sm" data-decision="declined" type="button">Decline</button>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="card" style="margin-top:18px;">
          <div class="section-head"><h3>Leave History</h3><span class="muted" id="leave-history-count"><?= count($leaveHistory) ?> decided</span></div>
          <div id="leave-history-list">
            <?php foreach ($leaveHistory as $i => $req): ?>
            <?php
                $stroke = ['#D2A7A7', '#6C714F', '#6D382B'][$i % 3];
                $iconPath = $i % 3 === 2
                    ? '<path d="M12 3v6M12 21c-5-2-8-6-8-11 3 0 6 1.5 8 5 2-3.5 5-5 8-5 0 5-3 9-8 11Z"/>'
                    : '<path d="M4 20 C4 12 8 5 14 3 C16 9 15 16 4 20Z"/>';
                $dayLabel = $req['duration_days'] . ' day' . ($req['duration_days'] === 1 ? '' : 's');
                $label = $req['status'] === 'approved' ? 'Approved' : 'Declined';
            ?>
            <div class="leave-req-card" data-leave-id="<?= (int) $req['leave_id'] ?>" data-employee-name="<?= htmlspecialchars($req['employee_name']) ?>" data-leave-type-label="<?= htmlspecialchars($leaveTypeLabels[$req['leave_type']]) ?>" data-duration-days="<?= (int) $req['duration_days'] ?>" data-reason="<?= htmlspecialchars($req['reason']) ?>" data-status="<?= htmlspecialchars($req['status']) ?>" data-decided-at="<?= htmlspecialchars($req['decided_at']) ?>">
              <div class="lr-main">
                <div class="lr-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="<?= $stroke ?>" stroke-width="1.8"><?= $iconPath ?></svg></div>
                <div>
                  <div class="lr-title"><?= htmlspecialchars($req['employee_name']) ?> — <?= htmlspecialchars($leaveTypeLabels[$req['leave_type']]) ?></div>
                  <div class="lr-sub"><?= htmlspecialchars($dayLabel) ?> · <?= htmlspecialchars($req['reason']) ?> · Decided <?= htmlspecialchars($req['decided_at']) ?></div>
                </div>
              </div>
              <div class="leave-req-actions">
                <span class="badge badge-<?= htmlspecialchars($req['status']) ?>"><?= htmlspecialchars($label) ?></span>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <!-- Reports -->
      <div class="tab-panel" id="a-reports">
        <div class="card">
          <div class="section-head"><h3>Generate Reports</h3></div>
          <div class="report-grid">
            <?php foreach ($reportTiles as $tile): ?>
            <button class="report-tile<?= $tile['disabled'] ? ' disabled' : '' ?>" data-key="<?= htmlspecialchars($tile['key']) ?>" type="button"<?= $tile['disabled'] ? ' disabled' : '' ?>>
              <div class="rt-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#D2A7A7" stroke-width="1.8"><?= $tile['icon'] ?></svg></div>
              <div class="rt-title"><?= htmlspecialchars($tile['title']) ?></div>
              <div class="rt-sub"><?= $tile['subtitle'] ?></div>
            </button>
            <?php endforeach; ?>
          </div>
          <div class="divider"></div>
          <div style="display:flex; gap:12px;">
            <button class="btn btn-olive" id="btn-export-reports-csv" type="button" disabled>Export as CSV</button>
            <button class="btn btn-outline" id="btn-export-reports-pdf" type="button" disabled>Export as PDF</button>
          </div>
        </div>
      </div>

      <!-- Settings -->
      <div class="tab-panel" id="a-settings">
        <div class="grid grid-2">
          <div class="card">
            <div class="section-head"><h3>System Settings</h3></div>
            <div class="form-field" style="margin-bottom:14px;"><label>Company Name</label><input type="text" id="settings-company-name" value="<?= htmlspecialchars($systemSettings['company_name']) ?>"></div>
            <div class="form-row">
              <div class="form-field"><label>Working Hours Start</label><input type="time" id="settings-hours-start" value="<?= htmlspecialchars($systemSettings['working_hours_start']) ?>"></div>
              <div class="form-field"><label>Working Hours End</label><input type="time" id="settings-hours-end" value="<?= htmlspecialchars($systemSettings['working_hours_end']) ?>"></div>
            </div>
            <div class="form-field" style="margin-top:14px;"><label>Late Threshold (minutes)</label><input type="number" id="settings-late-threshold" value="<?= (int) $systemSettings['late_threshold_minutes'] ?>"></div>
            <p class="form-success" id="settings-save-success" style="display:none;">Settings updated.</p>
            <button class="btn btn-pink" id="btn-save-settings" style="margin-top:16px;" type="button" disabled>Save Settings</button>
          </div>
          <div class="card">
            <div class="section-head"><h3>Integrations</h3></div>
            <div class="settings-row"><div><div class="sr-label">QR Code Clock-In</div><div class="sr-sub">Employees scan to clock in/out</div></div><div class="toggle<?= $integrationSettings['qr_clock_in_enabled'] ? ' on' : '' ?>" data-toggle></div></div>
            <div class="settings-row"><div><div class="sr-label">NFC Clock-In</div><div class="sr-sub">Employees tap a card or phone to clock in/out</div></div><div class="toggle<?= $integrationSettings['nfc_clock_in_enabled'] ? ' on' : '' ?>" data-toggle></div></div>
            <div class="settings-row"><div><div class="sr-label">Google Sheets Sync</div><div class="sr-sub">Mirror attendance to a live sheet</div></div><div class="toggle<?= $integrationSettings['google_sheets_sync_enabled'] ? ' on' : '' ?>" data-toggle></div></div>
            <div class="settings-row"><div><div class="sr-label">Raspberry Pi Device</div><div class="sr-sub"><?= htmlspecialchars($terminalStatus['device_name']) ?> · <?= htmlspecialchars($terminalStatus['network_label']) ?></div></div><span class="badge badge-present"><?= $terminalStatus['status'] === 'connected' ? 'Connected' : 'Disconnected' ?></span></div>
          </div>
        </div>
        <div class="card" style="margin-top:14px;">
          <div class="section-head"><h3>Manage Admins</h3></div>
          <p class="muted">Create new admin accounts for trusted colleagues.</p>
          <div style="margin-top:12px; display:flex; gap:10px;">
            <button class="btn btn-pink" id="btn-open-invite-admin" type="button">Invite Admin</button>
          </div>

          <div class="modal-overlay" id="modal-invite-admin">
            <div class="modal">
              <h3>Invite Admin</h3>
              <div class="form-field"><label>Full Name</label><input type="text" id="invite-admin-name" placeholder="e.g. Jordan Miles"></div>
              <div class="form-row" style="margin-top:10px;">
                <div class="form-field"><label>Employee ID</label><input type="text" id="invite-admin-employee-id" placeholder="e.g. ADM-001"></div>
                <div class="form-field"><label>Email</label><input type="email" id="invite-admin-email" placeholder="admin@tapp.co"></div>
              </div>
              <div class="form-row" style="margin-top:10px;">
                <div class="form-field"><label>Password</label><input type="password" id="invite-admin-password" placeholder="••••••••"></div>
                <div class="form-field"><label>Confirm Password</label><input type="password" id="invite-admin-password-confirm" placeholder="••••••••"></div>
              </div>
              <p class="form-error" id="invite-admin-error" style="display:none;">Please fill all fields and ensure passwords match.</p>
              <div style="display:flex; gap:10px; margin-top:16px;">
                <button class="btn btn-pink" id="btn-invite-admin-create" type="button">Create Admin</button>
                <button class="btn btn-outline" id="btn-invite-admin-cancel" type="button">Cancel</button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="../assets/js/app.js?v=<?= filemtime(__DIR__ . '/../assets/js/app.js') ?>"></script>
<script src="../assets/js/admin.js?v=<?= filemtime(__DIR__ . '/../assets/js/admin.js') ?>"></script>
</body>
</html>
