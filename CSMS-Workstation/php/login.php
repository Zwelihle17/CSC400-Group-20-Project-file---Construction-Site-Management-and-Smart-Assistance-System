<?php
/* ================================================================
   php/login.php  —  ConstructPro v3
   1. Validates credentials
   2. On success: sets session, outputs a full-screen loading page
      with the animated spinner + user's name + role
   3. The loading page auto-redirects to the correct dashboard
      after 2 seconds via JS (meta-refresh fallback included)
   ================================================================ */
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../index.php"); exit();
}

$eid = trim($_POST['employee_id'] ?? '');
$pw  = trim($_POST['password']    ?? '');

if (!$eid || !$pw) {
    header("Location: ../index.php?error=1"); exit();
}

/* ── Database lookup ── */
try {
    $pdo  = getDBConnection();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE employee_id = ? AND is_active = 1");
    $stmt->execute([$eid]);
    $user = $stmt->fetch();
} catch (Exception $e) {
    die("
    <div style='font-family:sans-serif;padding:40px;background:#111;color:#eee;min-height:100vh'>
      <h2 style='color:#ef4444'>Database Error</h2>
      <p style='margin-top:12px'>Could not connect to the database. Please check:</p>
      <ul style='margin-top:12px;line-height:2'>
        <li>MySQL is running in XAMPP</li>
        <li>Database <strong>construction_db</strong> exists in phpMyAdmin</li>
        <li>You have run <strong>database.sql</strong> and visited <strong>setup.php</strong></li>
      </ul>
      <p style='margin-top:20px;color:#f59e0b'>Error: " . htmlspecialchars($e->getMessage()) . "</p>
      <a href='../index.php' style='display:inline-block;margin-top:20px;padding:10px 22px;
         background:#f59e0b;color:#000;text-decoration:none;border-radius:4px;font-weight:700'>
        Back to Login
      </a>
    </div>");
}

/* ── Wrong credentials ── */
if (!$user || !password_verify($pw, $user['password'])) {
    header("Location: ../index.php?error=1"); exit();
}

/* ── Success: set session ── */
$_SESSION['user_id']   = $user['id'];
$_SESSION['emp_id']    = $user['employee_id'];
$_SESSION['full_name'] = $user['full_name'];
$_SESSION['role']      = $user['role'];
$_SESSION['site']      = $user['site_assigned'];

try { logAction($user['id'], 'LOGIN', 'IP: ' . ($_SERVER['REMOTE_ADDR'] ?? '')); }
catch (Exception $e) { /* ignore — system_logs may not exist yet */ }

/* ── Destination dashboard ── */
$dashboards = [
    'admin'          => '../dashboards/admin.php',
    'hr'             => '../dashboards/hr.php',
    'site_manager'   => '../dashboards/site_manager.php',
    'foreman'        => '../dashboards/foreman.php',
    'safety_officer' => '../dashboards/safety_officer.php',
    'general_labour' => '../dashboards/general_labour.php',
];
$destination = $dashboards[$user['role']] ?? '../index.php';

/* ── Role display names ── */
$roleLabels = [
    'admin'          => 'Administrator',
    'hr'             => 'Human Resources',
    'site_manager'   => 'Site Manager',
    'foreman'        => 'Foreman',
    'safety_officer' => 'Safety Officer',
    'general_labour' => 'General Labour',
];
$roleLabel = $roleLabels[$user['role']] ?? $user['role'];
$userName  = htmlspecialchars($user['full_name']);

/* ── Output loading screen (redirects automatically) ── */
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<!-- Fallback: redirect even if JS is disabled -->
<meta http-equiv="refresh" content="3;url=<?= htmlspecialchars($destination) ?>">
<title>Loading — CSMS</title>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }

  body {
    font-family: 'Inter', sans-serif;
    background: #0c0c0c;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    position: relative;
  }

  /* Background image with dark overlay — same as login page */
  .bg {
    position: fixed; inset: 0; z-index: 0;
    background: url('../images/background.jpg') center / cover no-repeat;
  }
  .bg::after {
    content: '';
    position: absolute; inset: 0;
    background: rgba(0, 0, 0, 0.82);
  }

  /* Central loading card */
  .load-card {
    position: relative; z-index: 2;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0;
    text-align: center;
    padding: 60px 70px;
    background: rgba(10, 10, 10, 0.75);
    border: 1px solid rgba(245, 158, 11, 0.20);
    border-radius: 6px;
    backdrop-filter: blur(20px);
    min-width: 340px;
    /* Fade in smoothly */
    animation: fadeIn 0.4s ease forwards;
  }
  @keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to   { opacity: 1; transform: translateY(0); }
  }

  /* ── Dual-ring spinner (same as login page left panel) ── */
  .loader {
    width: 9em; height: 9em;
    font-size: 16px;
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 36px;
  }
  .loader .face {
    position: absolute;
    border-radius: 50%;
    border-style: solid;
    animation: spinFace 2.4s linear infinite;
  }
  .loader .face:nth-child(1) {
    width: 100%; height: 100%;
    color: #f59e0b;
    border-color: currentColor transparent transparent currentColor;
    border-width: 0.2em 0.2em 0 0;
    animation-direction: normal;
  }
  .loader .face:nth-child(2) {
    width: 70%; height: 70%;
    color: #fcd34d;
    border-color: currentColor currentColor transparent transparent;
    border-width: 0.2em 0 0 0.2em;
    animation-direction: reverse;
  }
  .loader .face .circle {
    position: absolute;
    width: 50%; height: 0.1em;
    top: 50%; left: 50%;
    background: transparent;
    transform-origin: left;
  }
  .loader .face:nth-child(1) .circle { transform: rotate(-45deg); }
  .loader .face:nth-child(2) .circle { transform: rotate(-135deg); }
  .loader .face .circle::before {
    position: absolute;
    top: -0.5em; right: -0.5em;
    content: '';
    width: 1em; height: 1em;
    background-color: currentColor;
    border-radius: 50%;
    box-shadow:
      0 0 2em, 0 0 4em, 0 0 6em, 0 0 8em, 0 0 10em,
      0 0 0 0.5em rgba(245, 158, 11, 0.12);
  }
  .loader .face:nth-child(2) .circle::before {
    background-color: lime;
    box-shadow:
      0 0 2em lime, 0 0 4em lime, 0 0 6em lime,
      0 0 8em lime, 0 0 10em lime,
      0 0 0 0.5em rgba(0, 255, 0, 0.12);
  }
  @keyframes spinFace { to { transform: rotate(1turn); } }

  /* CSMS brand inside spinner */
  .spinner-label {
    position: absolute;
    top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    text-align: center;
    pointer-events: none;
  }
  .spinner-label .s-brand {
    font-family: 'Bebas Neue', sans-serif;
    font-size: 18px;
    letter-spacing: 4px;
    color: #f0ece3;
    display: block;
    line-height: 1;
  }

  /* Text below spinner */
  .load-greeting {
    font-family: 'Bebas Neue', sans-serif;
    font-size: 28px;
    letter-spacing: 2px;
    color: #f0ece3;
    line-height: 1;
    margin-bottom: 8px;
  }
  .load-greeting span { color: #f59e0b; }

  .load-role {
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 3px;
    text-transform: uppercase;
    color: rgba(245, 158, 11, 0.70);
    margin-bottom: 28px;
  }

  /* Progress bar */
  .progress-track {
    width: 200px;
    height: 2px;
    background: rgba(255,255,255,0.08);
    border-radius: 2px;
    overflow: hidden;
    margin-bottom: 16px;
  }
  .progress-fill {
    height: 100%;
    width: 0%;
    background: linear-gradient(90deg, #f59e0b, #fcd34d);
    border-radius: 2px;
    animation: fillBar 2s ease-out forwards;
  }
  @keyframes fillBar {
    0%   { width: 0%; }
    60%  { width: 75%; }
    85%  { width: 90%; }
    100% { width: 100%; }
  }

  .load-status {
    font-size: 11px;
    color: rgba(240, 236, 227, 0.35);
    letter-spacing: 1px;
    height: 16px;
  }

  /* Dots animation for loading text */
  .dots::after {
    content: '';
    animation: dots 1.4s steps(4, end) infinite;
  }
  @keyframes dots {
    0%  { content: ''; }
    25% { content: '.'; }
    50% { content: '..'; }
    75% { content: '...'; }
  }

  /* CSMS footer */
  .load-footer {
    position: fixed;
    bottom: 18px; left: 50%;
    transform: translateX(-50%);
    font-size: 9px;
    color: rgba(255,255,255,0.12);
    letter-spacing: 2px;
    text-transform: uppercase;
    white-space: nowrap;
    z-index: 3;
  }
</style>
</head>
<body>

<div class="bg"></div>

<div class="load-card">

  <!-- Dual-ring spinner with CSMS label inside -->
  <div class="loader">
    <div class="face"><div class="circle"></div></div>
    <div class="face"><div class="circle"></div></div>
    <div class="spinner-label">
      <span class="s-brand">CSMS</span>
    </div>
  </div>

  <!-- Personalised greeting -->
  <div class="load-greeting">Welcome, <span><?= $userName ?></span></div>
  <div class="load-role"><?= htmlspecialchars($roleLabel) ?></div>

  <!-- Progress bar -->
  <div class="progress-track">
    <div class="progress-fill"></div>
  </div>

  <!-- Status text -->
  <div class="load-status">
    Loading your dashboard<span class="dots"></span>
  </div>

</div>

<div class="load-footer">CSMS v3 · Eswatini</div>

<script>
  // Redirect to dashboard once progress bar completes (~2 seconds)
  // The meta-refresh above is the fallback if JS is disabled
  setTimeout(function() {
    window.location.href = <?= json_encode($destination) ?>;
  }, 2000);
</script>

</body>
</html>
