<?php
require_once __DIR__ . '/auth.php';

// If already logged in, skip straight to the right portal.
// Build the path relative to the project root so it works whether TAPP is
// deployed at the domain root or under a subdirectory (e.g. /tapp/).
$base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
if (!empty($_SESSION['authenticated'])) {
    header('Location: ' . $base . ($_SESSION['role'] === 'admin' ? '/admin/portal.php' : '/employee/portal.php'));
    exit;
}

$error = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>TAPP — Sign In</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,300;0,9..144,500;0,9..144,600;1,9..144,500&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/tokens.css">
<link rel="stylesheet" href="assets/css/components/index.css">
</head>
<body>

<div id="view-login">
  <svg class="bg-lotus" viewBox="0 0 800 800" xmlns="http://www.w3.org/2000/svg">
    <g fill="none" stroke="#D2A7A7" stroke-width="1.1">
      <path d="M400 620 C 300 560, 260 460, 300 380 C 340 460, 380 500, 400 620 Z"/>
      <path d="M400 620 C 500 560, 540 460, 500 380 C 460 460, 420 500, 400 620 Z"/>
      <path d="M400 620 C 340 520, 340 420, 400 340 C 460 420, 460 520, 400 620 Z"/>
      <path d="M400 620 C 260 540, 180 440, 200 320 C 300 380, 360 460, 400 620 Z"/>
      <path d="M400 620 C 540 540, 620 440, 600 320 C 500 380, 440 460, 400 620 Z"/>
      <circle cx="400" cy="360" r="14"/>
    </g>
    <g fill="none" stroke="#6C714F" stroke-width="1">
      <path d="M180 700 C 130 660, 120 610, 160 580 C 190 620, 200 650, 180 700 Z"/>
      <path d="M620 700 C 670 660, 680 610, 640 580 C 610 620, 600 650, 620 700 Z"/>
    </g>
  </svg>

  <div class="login-card">
    <div class="login-brand">
      <svg class="lotus-mark" viewBox="0 0 100 100" fill="none">
        <path d="M50 78 C 38 68 32 54 38 42 C 44 54 48 60 50 78 Z" fill="#D2A7A7"/>
        <path d="M50 78 C 62 68 68 54 62 42 C 56 54 52 60 50 78 Z" fill="#D2A7A7" opacity="0.85"/>
        <path d="M50 78 C 42 62 42 46 50 34 C 58 46 58 62 50 78 Z" fill="#A18261"/>
        <path d="M50 78 C 30 66 20 48 24 30 C 40 40 46 54 50 78 Z" fill="#6D382B" opacity="0.8"/>
        <path d="M50 78 C 70 66 80 48 76 30 C 60 40 54 54 50 78 Z" fill="#6D382B" opacity="0.8"/>
        <circle cx="50" cy="40" r="4" fill="#6C714F"/>
      </svg>
      <h1>TAPP</h1>
      <p>Digital Attendance, in full bloom</p>
    </div>

    <?php if ($error === '1'): ?>
      <p style="background:rgba(109,56,43,0.25); color:#e6b2a4; padding:10px 14px; border-radius:9px; font-size:12.5px; margin-bottom:16px;">
        Please enter both an email and a password.
      </p>
    <?php elseif ($error === '2'): ?>
      <p style="background:rgba(109,56,43,0.25); color:#e6b2a4; padding:10px 14px; border-radius:9px; font-size:12.5px; margin-bottom:16px;">
        We were unable to process your login right now. Please try again later.
        <?php
          $dbg = $_SESSION['login_debug'] ?? [];
          $hint = $dbg['exception']
            ?? ($dbg['api_body']['error']['message'] ?? null)
            ?? (isset($dbg['api_status']) ? 'HTTP ' . $dbg['api_status'] : null);
          if ($hint):
        ?>
          <br><small style="opacity:0.9;"><?= htmlspecialchars((string) $hint) ?></small>
        <?php endif; ?>
      </p>
    <?php elseif ($error === '3'): ?>
      <p style="background:rgba(109,56,43,0.25); color:#e6b2a4; padding:10px 14px; border-radius:9px; font-size:12.5px; margin-bottom:16px;">
        Invalid login credentials. Please check your email and password.
      </p>
    <?php endif; ?>

    <?php if (!empty($_SESSION['login_debug'])): ?>
      <script>
        console.group('TAPP Login Debug');
        console.log(<?php echo json_encode($_SESSION['login_debug'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);
        console.groupEnd();
      </script>
      <details style="margin-bottom:16px; padding:12px; background:rgba(255,255,255,0.08); border:1px solid rgba(255,255,255,0.15); border-radius:8px; color:#e6b2a4; font-size:12px;">
        <summary style="font-weight:600; cursor:pointer;">Show login debug details</summary>
        <pre style="white-space:pre-wrap; word-break:break-word; margin-top:8px;"><?php echo htmlspecialchars(json_encode($_SESSION['login_debug'], JSON_PRETTY_PRINT), ENT_QUOTES, 'UTF-8'); ?></pre>
      </details>
      <?php unset($_SESSION['login_debug']); ?>
    <?php endif; ?>

    <form method="POST" action="login_process.php" id="login-form">
      <input type="hidden" name="role" id="role-input" value="employee">

      <div class="role-toggle">
        <button type="button" id="tab-employee" class="active" data-role="employee">Employee</button>
        <button type="button" id="tab-admin" data-role="admin">Admin</button>
      </div>

      <div class="field">
        <label id="login-label-id">Email</label>
        <input name="login_id" id="login-id" type="text" placeholder="employee@gmail.com">
      </div>
      <div class="field">
        <label>Password</label>
        <input name="password" type="password" placeholder="••••••••">
      </div>
      <button type="submit" class="btn-primary">Sign In</button>
    </form>
    <p style="text-align:center; margin-top:14px; font-size:13px;">
      <a href="forgot_password.php" style="color:inherit; opacity:0.85;">Forgot password?</a>
    </p>

  </div>
</div>

<script src="assets/js/app.js"></script>
</body>
</html>
