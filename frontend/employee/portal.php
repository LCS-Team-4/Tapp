<?php
require_once __DIR__ . '/../auth.php';
require_role('employee');
require_once __DIR__ . '/data.php';
$user = current_user();

$pendingLeave = null;
foreach ($leaveRequests as $req) {
    if ($req['status'] === 'pending') {
        $pendingLeave = $req;
        break;
    }
}
$firstName = explode(' ', $user['name'])[0];
$clockStatusLabels = [
    'onsite' => 'Clocked In',
    'present' => 'Clocked Out',
    'absent' => 'Not Clocked In',
    'late' => 'Clocked In',
];
$clockStatusLabel = $clockStatusLabels[$todayStatus['status']] ?? ucfirst((string) $todayStatus['status']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>TAPP — Employee Portal</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,300;0,9..144,500;0,9..144,600;1,9..144,500&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/tokens.css">
<link rel="stylesheet" href="../assets/css/components/index.css">
<link rel="stylesheet" href="../assets/css/employee.css">
</head>
<body>

<div class="app-shell active" id="shell-employee">
  <div class="main-col">
    <div class="topbar">
      <div class="topbar-left">
        <button class="btn-icon nav-toggle-btn" id="nav-toggle" type="button" aria-label="Toggle menu" aria-expanded="false" aria-controls="nav-panel">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
        </button>
        <div class="topbar-title"><h2 id="e-panel-title">Dashboard</h2><p id="e-panel-sub"><?= date('l, j F Y') ?></p></div>
      </div>
      <div class="user-chip">
        <div><div class="who" style="text-align:right;"><?= htmlspecialchars($user['name']) ?></div><div class="role" style="text-align:right;"><?= htmlspecialchars($user['role_label']) ?></div></div>
        <div class="avatar"><?= htmlspecialchars($user['initials']) ?></div>
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
        <div class="nav-group-label">Employee Portal</div>
        <button class="nav-item active" data-panel="e-dashboard">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/></svg>
          Dashboard
        </button>
        <button class="nav-item" data-panel="e-attendance">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg>
          Attendance
        </button>
        <button class="nav-item" data-panel="e-profile">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.4 3.6-7 8-7s8 2.6 8 7"/></svg>
          Profile
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
      <div class="tab-panel active" id="e-dashboard">
        <div class="grid grid-4" style="margin-bottom:20px;">
          <div class="card">
            <div class="sub">Welcome back</div>
            <h3 style="font-family:'Fraunces',serif; font-size:20px; display:flex; align-items:center; gap:10px;">Good morning, <?= htmlspecialchars($firstName) ?>
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#D2A7A7" stroke-width="1.8"><path d="M4 20 C4 12 8 5 14 3 C16 9 15 16 4 20Z"/></svg>
            </h3>
            <!-- <p class="muted" style="margin-top:8px;">You're clocked in and on track — 2 days of leave already approved this month.</p> -->
          </div>
          <div class="card">
            <div class="sub">Today's Status</div>
            <span class="badge badge-<?= htmlspecialchars($todayStatus['status']) ?>">Present</span>
            <p class="muted" style="margin-top:12px;">Clocked in at <?= htmlspecialchars($todayStatus['clock_in']) ?></p>
          </div>
          <div class="card">
            <div class="sub">This Week</div>
            <div class="stat-num" style="font-size:26px;"><?= htmlspecialchars($todayStatus['week_hours_logged']) ?> <span style="font-size:14px; color:var(--cream-dim); font-weight:600;">hrs</span></div>
            <p class="muted" style="margin-top:6px;">of <?= htmlspecialchars($todayStatus['week_hours_target']) ?> hr target</p>
          </div>
          <div class="card">
            <div class="sub">Leave Balances</div>
            <div style="display:flex; gap:16px; margin-top:10px; flex-wrap:wrap;">
              <div style="text-align:center;">
                <div class="stat-num" style="font-size:22px;"><?= (int) $leaveBalances['annual_leave'] ?></div>
                <div class="muted" style="font-size:12px;">Annual</div>
              </div>
              <div style="text-align:center;">
                <div class="stat-num" style="font-size:22px;"><?= (int) $leaveBalances['sick_leave'] ?></div>
                <div class="muted" style="font-size:12px;">Sick</div>
              </div>
              <div style="text-align:center;">
                <div class="stat-num" style="font-size:22px;"><?= (int) $leaveBalances['stu_leave'] ?></div>
                <div class="muted" style="font-size:12px;">Study</div>
              </div>
              <div style="text-align:center;">
                <div class="stat-num" style="font-size:22px;"><?= (int) $leaveBalances['fr_leave'] ?></div>
                <div class="muted" style="font-size:12px;">Family Resp.</div>
              </div>
            </div>
            <p class="muted" style="margin-top:8px;">days remaining per leave type</p>
          </div>
        </div>

        <div class="grid grid-2">
          <div class="card">
            <div class="section-head"><h3>Quick Actions</h3></div>
            <div style="display:flex; gap:14px; flex-wrap:wrap;">
              <button class="btn btn-pink" data-goto="e-attendance" type="button">View Attendance</button>
              <button class="btn btn-outline" data-goto="e-profile" data-subtab="leave" type="button">Apply for Leave</button>
              <button class="btn btn-outline" data-goto="e-profile" data-subtab="history" type="button">View History</button>
              <button class="btn btn-outline" data-goto="e-profile" type="button">Edit Profile</button>
            </div>
          </div>
          <div class="card">
            <div class="section-head"><h3>Pending Leave</h3></div>
            <?php if ($pendingLeave): ?>
            <div class="leave-req-card" style="margin-bottom:0;">
              <div class="lr-main">
                <div class="lr-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#D2A7A7" stroke-width="1.8"><path d="M4 20 C4 12 8 5 14 3 C16 9 15 16 4 20Z"/></svg></div>
                <div>
                  <div class="lr-title"><?= htmlspecialchars(leave_type_label($pendingLeave['leave_type'], $leaveTypeOptions)) ?></div>
                  <div class="lr-sub"><?= htmlspecialchars(format_date_range($pendingLeave['start_date'], $pendingLeave['end_date'])) ?> · <?= htmlspecialchars($pendingLeave['reason']) ?></div>
                </div>
              </div>
              <span class="badge badge-pending">Pending</span>
            </div>
            <?php else: ?>
            <p class="muted">No pending leave requests.</p>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Attendance -->
      <div class="tab-panel" id="e-attendance">
        <div class="card" style="margin-bottom:20px;">
          <div class="clock-hero">
            <div>
              <div class="eyebrow">Live Clock</div>
              <div class="clock-time" id="live-clock"><?= date('h:i:s A') ?></div>
              <div class="clock-sub" id="live-clock-date"></div>
            </div>
            <div class="clock-status">
              <span class="badge badge-<?= htmlspecialchars($todayStatus['status']) ?>" id="clock-badge"><?= htmlspecialchars($clockStatusLabel) ?></span>
            </div>
          </div>
        </div>
        <div class="grid grid-3">
          <div class="card"><div class="sub">Clock In Time</div><h3 style="font-size:22px;" id="stat-clock-in"><?= htmlspecialchars($todayStatus['clock_in'] ?? '—') ?></h3></div>
          <div class="card"><div class="sub">Clock Out Time</div><h3 style="font-size:22px;" id="stat-clock-out"><?= htmlspecialchars($todayStatus['clock_out'] ?? '—') ?></h3></div>
          <div class="card"><div class="sub">Total Hours Today</div><h3 style="font-size:22px;" id="stat-total-hours"><?= htmlspecialchars($todayStatus['total_hours'] !== null ? $todayStatus['total_hours'] : 'In progress') ?></h3></div>
        </div>
      </div>

      <!-- Profile (Account / Leave / History sub-tabs) -->
      <div class="tab-panel" id="e-profile">
        <div class="segmented">
          <button class="segmented-item active" data-subtab="account" type="button">Account</button>
          <button class="segmented-item" data-subtab="leave" type="button">Leave</button>
          <button class="segmented-item" data-subtab="history" type="button">History</button>
        </div>
        <div class="subtab-content">

          <!-- Account -->
          <div class="subtab-panel active" data-subtab-panel="account">
            <div class="grid grid-2">
              <div class="card">
                <div class="section-head"><h3>Profile Details</h3></div>
                <div class="form-row">
                  <div class="form-field" id="field-profile-name"><label>Full Name</label><input type="text" id="profile-name" value="<?= htmlspecialchars($currentUser['name']) ?>"></div>
                  <div class="form-field"><label>Employee ID</label><input type="text" value="<?= htmlspecialchars($currentUser['employee_id']) ?>" disabled></div>
                  <div class="form-field" id="field-profile-email"><label>Email</label><input type="text" id="profile-email" value="<?= htmlspecialchars($currentUser['email']) ?>"></div>
                  <div class="form-field"><label>Department</label><input type="text" id="profile-department" value="<?= htmlspecialchars($currentUser['department']) ?>"></div>
                </div>
                <p class="form-error" id="profile-save-error" style="display:none;"></p>
                <p class="form-success" id="profile-save-success" style="display:none;">Profile updated.</p>
                <button class="btn btn-pink" style="margin-top:16px;" id="btn-save-profile" type="button">Save Changes</button>
              </div>
              <div class="card">
                <div class="section-head"><h3>Change Password</h3></div>
                <?php foreach ([['pwd-current', 'Current Password'], ['pwd-new', 'New Password'], ['pwd-confirm', 'Confirm New Password']] as [$pwId, $pwLabel]): ?>
                <div class="form-field" style="margin-bottom:14px;">
                  <label><?= htmlspecialchars($pwLabel) ?></label>
                  <div class="password-field">
                    <input type="password" id="<?= htmlspecialchars($pwId) ?>">
                    <button type="button" class="btn-icon pw-toggle" data-target="<?= htmlspecialchars($pwId) ?>" title="Show password" aria-label="Show password">
                      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8Z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                  </div>
                </div>
                <?php endforeach; ?>
                <p class="form-error" id="profile-password-error" style="display:none;"></p>
                <p class="form-success" id="profile-password-success" style="display:none;">Password updated.</p>
                <button class="btn btn-outline" id="btn-update-password" type="button">Update Password</button>
              </div>
            </div>
          </div>

          <!-- Leave -->
          <div class="subtab-panel" data-subtab-panel="leave">
            <div class="grid grid-2">
              <div class="card">
                <div class="section-head"><h3>Apply for Leave</h3></div>
                <div class="form-row">
                  <div class="form-field" id="field-leave-type"><label>Leave Type</label>
                    <select id="leave-type">
                      <?php foreach ($leaveTypeOptions as $opt): ?>
                      <option value="<?= htmlspecialchars($opt['value']) ?>"><?= htmlspecialchars($opt['label']) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="form-field" id="field-leave-start"><label>Start Date</label><input type="date" id="leave-start" min="<?= date('Y-m-d') ?>"></div>
                  <div class="form-field" id="field-leave-end"><label>End Date</label><input type="date" id="leave-end" min="<?= date('Y-m-d') ?>"></div>
                </div>
                <div class="form-field" id="field-leave-reason" style="margin-top:14px;"><label>Reason</label><textarea id="leave-reason" placeholder="Briefly describe your reason for leave"></textarea></div>
                <p class="form-error" id="leave-form-error" style="display:none;">Please fill in all required fields above.</p>
                <button class="btn btn-pink" style="margin-top:14px;" id="btn-submit-leave" type="button">Submit Request</button>
              </div>
              <div class="card">
                <div class="section-head"><h3>Leave Status</h3></div>
                <div id="leave-status-list">
                  <?php foreach ($leaveRequests as $req): ?>
                  <?php
                      $stroke = $req['status'] === 'approved' ? '#6C714F' : ($req['status'] === 'declined' ? '#6D382B' : '#D2A7A7');
                      $iconPath = $req['status'] === 'declined'
                          ? '<path d="M12 3v6M12 21c-5-2-8-6-8-11 3 0 6 1.5 8 5 2-3.5 5-5 8-5 0 5-3 9-8 11Z"/>'
                          : '<path d="M4 20 C4 12 8 5 14 3 C16 9 15 16 4 20Z"/>';
                  ?>
                  <div class="leave-req-card" data-leave-id="<?= (int) $req['leave_id'] ?>" data-leave-type="<?= htmlspecialchars($req['leave_type']) ?>" data-start-date="<?= htmlspecialchars($req['start_date']) ?>" data-end-date="<?= htmlspecialchars($req['end_date']) ?>" data-reason="<?= htmlspecialchars($req['reason']) ?>">
                    <div class="lr-main">
                      <div class="lr-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="<?= $stroke ?>" stroke-width="1.8"><?= $iconPath ?></svg></div>
                      <div>
                        <div class="lr-title"><?= htmlspecialchars(leave_type_label($req['leave_type'], $leaveTypeOptions)) ?></div>
                        <div class="lr-sub"><?= htmlspecialchars(format_date_range($req['start_date'], $req['end_date'])) ?> · <?= htmlspecialchars($req['reason']) ?></div>
                      </div>
                    </div>
                    <div class="leave-req-actions">
                      <span class="badge badge-<?= htmlspecialchars($req['status']) ?>"><?= htmlspecialchars(ucfirst($req['status'])) ?></span>
                      <?php if ($req['status'] === 'pending'): ?>
                      <button class="btn-icon" data-leave-action="edit" data-id="<?= (int) $req['leave_id'] ?>" title="Edit request" aria-label="Edit request" type="button">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5Z"/></svg>
                      </button>
                      <button class="btn-icon" data-leave-action="cancel" data-id="<?= (int) $req['leave_id'] ?>" title="Cancel request" aria-label="Cancel request" type="button">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18"/><path d="M6 6l12 12"/></svg>
                      </button>
                      <?php endif; ?>
                    </div>
                  </div>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>
          </div>

          <!-- History -->
          <div class="subtab-panel" data-subtab-panel="history">
            <div class="card">
              <div class="toolbar">
                <input class="search-input" id="history-search" placeholder="Search by date…">
                <select class="select-input" id="history-status-filter">
                  <option value="">All statuses</option>
                  <option value="present">Present</option>
                  <option value="late">Late</option>
                  <option value="absent">Absent</option>
                </select>
              </div>
              <div class="table-wrap">
                <table>
                  <thead><tr><th>Date</th><th>Clock In</th><th>Clock Out</th><th>Total Hrs</th><th>Status</th></tr></thead>
                  <tbody id="history-tbody">
                    <?php foreach ($attendanceHistory as $row): ?>
                    <tr data-status="<?= htmlspecialchars($row['status']) ?>" data-date-label="<?= htmlspecialchars($row['date_label']) ?>">
                      <td><?= htmlspecialchars($row['date_label']) ?></td>
                      <td><?= htmlspecialchars($row['clock_in'] ?? '—') ?></td>
                      <td><?= htmlspecialchars($row['clock_out'] ?? '—') ?></td>
                      <td><?= htmlspecialchars($row['total_hours'] ?? '—') ?></td>
                      <td><span class="badge badge-<?= htmlspecialchars($row['status']) ?>"><?= htmlspecialchars(ucfirst($row['status'])) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

        </div>
      </div>
    </div>
  </div>
</div>

<script src="../assets/js/app.js?v=<?= filemtime(__DIR__ . '/../assets/js/app.js') ?>"></script>
<script src="../assets/js/employee.js?v=<?= filemtime(__DIR__ . '/../assets/js/employee.js') ?>"></script>
</body>
</html>
