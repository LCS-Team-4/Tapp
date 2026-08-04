<?php
require_once __DIR__ . '/../auth.php';
require_role('admin');
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
      <div class="tab-panel active" id="a-dashboard"></div>
      <div class="tab-panel" id="a-employees"></div>
      <div class="tab-panel" id="a-attendance"></div>
      <div class="tab-panel" id="a-leave"></div>
      <div class="tab-panel" id="a-reports"></div>
      <div class="tab-panel" id="a-settings"></div>
    </div>
  </div>
</div>

<script src="../assets/js/app.js"></script>
</body>
</html>
