<?php
require_once __DIR__ . '/../auth.php';
require_role('employee');
$user = current_user();
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
      <div class="tab-panel active" id="e-dashboard"></div>
      <div class="tab-panel" id="e-attendance"></div>
      <div class="tab-panel" id="e-profile"></div>
    </div>
  </div>
</div>

<script src="../assets/js/app.js"></script>
</body>
</html>
