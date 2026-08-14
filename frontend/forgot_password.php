<?php
require_once __DIR__ . '/auth.php';
$base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
if (!empty($_SESSION['authenticated'])) {
    header('Location: ' . $base . ($_SESSION['role'] === 'admin' ? '/admin/portal.php' : '/employee/portal.php'));
    exit;
}
$error   = $_GET['error'] ?? '';
$success = $_GET['success'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>TAPP — Forgot Password</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,300;0,9..144,500;0,9..144,600;1,9..144,500&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/tokens.css">
<link rel="stylesheet" href="assets/css/components/index.css">
</head>
<body>
<div id="view-login">
  <div class="login-card">
    <div class="login-brand">
      <h1>TAPP</h1>
      <p>Reset your password</p>
    </div>

    <?php if ($error === '1'): ?>
      <p style="background:rgba(109,56,43,0.25); color:#e6b2a4; padding:10px 14px; border-radius:9px; font-size:12.5px; margin-bottom:16px;">
        Please fill in Employee ID, Email, and generate a new password.
      </p>
    <?php elseif ($error === '2'): ?>
      <p style="background:rgba(109,56,43,0.25); color:#e6b2a4; padding:10px 14px; border-radius:9px; font-size:12.5px; margin-bottom:16px;">
        No employee was found with that Employee ID and Email.
      </p>
    <?php elseif ($error === '3'): ?>
      <p style="background:rgba(109,56,43,0.25); color:#e6b2a4; padding:10px 14px; border-radius:9px; font-size:12.5px; margin-bottom:16px;">
        Unable to submit request. Please try again later.
      </p>
    <?php elseif ($error === '4'): ?>
      <p style="background:rgba(109,56,43,0.25); color:#e6b2a4; padding:10px 14px; border-radius:9px; font-size:12.5px; margin-bottom:16px;">
        You already have a pending password reset request. Wait for admin approval.
      </p>
    <?php elseif ($success === '1'): ?>
      <p style="background:rgba(108,113,79,0.25); color:#c5d0a0; padding:10px 14px; border-radius:9px; font-size:12.5px; margin-bottom:16px;">
        Request submitted. An admin must verify you before the new password becomes active. Keep the password you generated — you will use it after approval.
      </p>
    <?php endif; ?>

    <form method="POST" action="forgot_password_process.php" id="forgot-form">
      <div class="form-field" style="margin-bottom:14px;">
        <label>Employee ID</label>
        <input type="text" name="employee_id" id="fp-employee-id" placeholder="e.g. S-001" required>
      </div>
      <div class="form-field" style="margin-bottom:14px;">
        <label>Email</label>
        <input type="email" name="email" id="fp-email" placeholder="you@company.com" required>
      </div>
      <div class="form-field" style="margin-bottom:14px;">
        <label>New Password (auto-generated)</label>
        <div style="display:flex; gap:8px; align-items:center;">
          <input type="text" name="new_password" id="fp-new-password" readonly required style="flex:1;">
          <button type="button" class="btn btn-outline" id="fp-regenerate" title="Generate new password">↻</button>
        </div>
        <small style="opacity:0.7; font-size:11px;">Copy this password. It only becomes active after an admin verifies your request.</small>
      </div>
      <button class="btn btn-pink" type="submit" style="width:100%; margin-top:8px;">Submit Reset Request</button>
    </form>

    <p style="text-align:center; margin-top:18px; font-size:13px;">
      <a href="login.php" style="color:inherit; opacity:0.85;">← Back to Sign In</a>
    </p>
  </div>
</div>
<script>
function genPassword(length) {
  const upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
  const lower = 'abcdefghijkmnopqrstuvwxyz';
  const numbers = '23456789';
  const symbols = '!@#$%&*';
  const all = upper + lower + numbers + symbols;
  let p = upper[Math.floor(Math.random()*upper.length)]
        + lower[Math.floor(Math.random()*lower.length)]
        + numbers[Math.floor(Math.random()*numbers.length)]
        + symbols[Math.floor(Math.random()*symbols.length)];
  for (let i = p.length; i < length; i++) p += all[Math.floor(Math.random()*all.length)];
  return p.split('').sort(() => Math.random() - 0.5).join('');
}
const pwdEl = document.getElementById('fp-new-password');
pwdEl.value = genPassword(12);
document.getElementById('fp-regenerate').addEventListener('click', () => {
  pwdEl.value = genPassword(12);
});
</script>
</body>
</html>
