<?php
/* ================================================================
   setup.php  —  ConstructPro v3.1  (Fixed)
   Creates all tables and inserts demo users safely.
   Visit: http://localhost/app/setup.php
   ================================================================ */
require_once 'config.php';
$pdo = getDBConnection();

$errors = [];
$log    = [];

/* ── Step 1: Disable FK checks so we can clear tables safely ── */
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

/* ── Step 2: Create all tables ── */
$tables = [

"users" => "CREATE TABLE IF NOT EXISTS users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    employee_id   VARCHAR(20)  UNIQUE NOT NULL,
    full_name     VARCHAR(100) NOT NULL,
    role          ENUM('admin','hr','site_manager','foreman','safety_officer','general_labour') NOT NULL,
    password      VARCHAR(255) NOT NULL,
    site_assigned VARCHAR(100) DEFAULT NULL,
    is_active     TINYINT(1)   DEFAULT 1,
    created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"messages" => "CREATE TABLE IF NOT EXISTS messages (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    sender_id       INT  NOT NULL,
    receiver_id     INT  NOT NULL,
    subject         VARCHAR(200) NOT NULL,
    body            TEXT NOT NULL,
    is_read         TINYINT(1)   DEFAULT 0,
    attachment_name VARCHAR(255) DEFAULT NULL,
    attachment_path VARCHAR(500) DEFAULT NULL,
    sent_at         TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id)   REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"attendance" => "CREATE TABLE IF NOT EXISTS attendance (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT  NOT NULL,
    date        DATE NOT NULL,
    status      ENUM('present','absent','late') DEFAULT 'present',
    recorded_by INT  NOT NULL,
    notes       VARCHAR(255) DEFAULT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"daily_reports" => "CREATE TABLE IF NOT EXISTS daily_reports (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    foreman_id       INT  NOT NULL,
    site_name        VARCHAR(100) NOT NULL,
    report_date      DATE NOT NULL,
    progress_update  TEXT NOT NULL,
    resource_usage   TEXT NOT NULL,
    equipment_needs  TEXT DEFAULT NULL,
    status           ENUM('pending','approved','rejected') DEFAULT 'pending',
    manager_comment  TEXT DEFAULT NULL,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (foreman_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"safety_reports" => "CREATE TABLE IF NOT EXISTS safety_reports (
    id                   INT AUTO_INCREMENT PRIMARY KEY,
    officer_id           INT  NOT NULL,
    site_name            VARCHAR(100) NOT NULL,
    report_date          DATE NOT NULL,
    inspection_findings  TEXT NOT NULL,
    unsafe_conditions    TEXT DEFAULT NULL,
    incident_description TEXT DEFAULT NULL,
    severity             ENUM('low','medium','high','critical') DEFAULT 'low',
    status               ENUM('open','in_progress','resolved')  DEFAULT 'open',
    created_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (officer_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"material_orders" => "CREATE TABLE IF NOT EXISTS material_orders (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    requested_by    INT  NOT NULL,
    order_type      ENUM('safety','general') DEFAULT 'general',
    items           TEXT NOT NULL,
    reason          TEXT NOT NULL,
    status          ENUM('pending','approved','rejected') DEFAULT 'pending',
    manager_comment TEXT DEFAULT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"complaints" => "CREATE TABLE IF NOT EXISTS complaints (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    worker_id      INT  NOT NULL,
    complaint_text TEXT NOT NULL,
    status         ENUM('open','in_progress','resolved') DEFAULT 'open',
    response       TEXT DEFAULT NULL,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (worker_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"schedules" => "CREATE TABLE IF NOT EXISTS schedules (
    id               INT          AUTO_INCREMENT PRIMARY KEY,
    worker_id        INT          NOT NULL,
    site_name        VARCHAR(100) NOT NULL,
    shift_date       DATE         NOT NULL,
    shift_start      TIME         NOT NULL,
    shift_end        TIME         NOT NULL,
    task_description VARCHAR(255) DEFAULT NULL,
    created_by       INT          NOT NULL,
    FOREIGN KEY (worker_id)  REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"system_logs" => "CREATE TABLE IF NOT EXISTS system_logs (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT          NOT NULL,
    action     VARCHAR(200) NOT NULL,
    details    TEXT         DEFAULT NULL,
    ip_address VARCHAR(45)  DEFAULT NULL,
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

];

foreach ($tables as $name => $sql) {
    try {
        $pdo->exec($sql);
        $log[] = ['ok', "Table '$name' ready"];
    } catch (Exception $e) {
        $errors[] = "Table '$name': " . $e->getMessage();
        $log[] = ['err', "Table '$name' FAILED: " . $e->getMessage()];
    }
}

/* ── Step 3: Clear all existing data (FK-safe because checks are off) ── */
$clearOrder = ['system_logs','messages','schedules','material_orders','safety_reports','daily_reports','attendance','complaints','users'];
foreach ($clearOrder as $t) {
    try {
        $pdo->exec("DELETE FROM `$t`");
        $log[] = ['ok', "Cleared '$t'"];
    } catch (Exception $e) {
        $log[] = ['warn', "Could not clear '$t' (may be empty): " . $e->getMessage()];
    }
}

/* ── Step 4: Re-enable FK checks ── */
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

/* ── Step 4b: Add attachment columns if upgrading existing DB ── */
try {
    $pdo->exec("ALTER TABLE messages ADD COLUMN IF NOT EXISTS attachment_name VARCHAR(255) DEFAULT NULL");
    $pdo->exec("ALTER TABLE messages ADD COLUMN IF NOT EXISTS attachment_path VARCHAR(500) DEFAULT NULL");
    $log[] = ['ok', "Attachment columns ensured on messages table"];
} catch (Exception $e) {
    $log[] = ['warn', "Could not add attachment columns (may already exist): " . $e->getMessage()];
}

/* ── Step 5: Insert demo users ── */
$pw = password_hash('password123', PASSWORD_BCRYPT);

$users = [
    ['AD001', 'Admin User',     'admin',          $pw, 'Head Office'],
    ['HR001', 'Nomsa Dlamini',  'hr',             $pw, 'Head Office'],
    ['SM001', 'Sipho Mkhontfo', 'site_manager',   $pw, 'Site A – Mbabane'],
    ['FM001', 'Bongani Nkosi',  'foreman',        $pw, 'Site A – Mbabane'],
    ['SO001', 'Thandi Zwane',   'safety_officer', $pw, 'Site A – Mbabane'],
    ['GL001', 'Musa Sithole',   'general_labour', $pw, 'Site A – Mbabane'],
    ['GL002', 'Lindiwe Dube',   'general_labour', $pw, 'Site A – Mbabane'],
];

$stmt = $pdo->prepare("INSERT INTO users (employee_id, full_name, role, password, site_assigned) VALUES (?, ?, ?, ?, ?)");
$inserted = 0;
foreach ($users as $u) {
    try {
        $stmt->execute($u);
        $inserted++;
        $log[] = ['ok', "User created: {$u[0]} — {$u[1]} ({$u[2]})"];
    } catch (Exception $e) {
        $errors[] = "User {$u[0]}: " . $e->getMessage();
        $log[] = ['err', "User {$u[0]} FAILED: " . $e->getMessage()];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>ConstructPro — Setup</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:Inter,Arial,sans-serif;background:#0f0f0f;color:#eee;padding:40px 20px;max-width:800px;margin:auto;line-height:1.6}
  h1{color:#f59e0b;font-size:30px;margin-bottom:4px}
  h2{color:#f59e0b;font-size:17px;margin:28px 0 12px;border-bottom:1px solid #222;padding-bottom:8px}
  .ok  {color:#22c55e} .warn{color:#f59e0b} .err{color:#ef4444}
  .box{border-radius:5px;padding:16px 20px;margin-top:16px;font-size:13px}
  .box-ok  {background:rgba(34,197,94,.08);border:1px solid rgba(34,197,94,.35)}
  .box-err {background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.35)}
  table{width:100%;border-collapse:collapse;margin-top:4px;font-size:13px}
  th,td{padding:10px 14px;text-align:left;border-bottom:1px solid #222}
  th{color:#f59e0b;font-size:10px;letter-spacing:2px;text-transform:uppercase;background:#161616}
  .badge{display:inline-block;padding:2px 9px;border-radius:3px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:1px}
  .bp{background:rgba(168,85,247,.18);color:#a855f7}
  .bb{background:rgba(59,130,246,.18); color:#3b82f6}
  .ba{background:rgba(245,158,11,.18); color:#f59e0b}
  .bo{background:rgba(249,115,22,.18); color:#f97316}
  .br{background:rgba(239,68,68,.18);  color:#ef4444}
  .bg{background:rgba(34,197,94,.18);  color:#22c55e}
  .log-box{background:#141414;border:1px solid #2a2a2a;border-radius:4px;padding:14px;max-height:260px;overflow-y:auto;margin-top:8px}
  .log-row{font-size:12px;font-family:monospace;padding:3px 0;border-bottom:1px solid #1e1e1e;display:flex;gap:8px}
  .pw{display:inline-block;padding:7px 18px;background:#1e1e1e;border:1px solid #f59e0b;border-radius:4px;color:#f59e0b;font-weight:700;font-size:15px;letter-spacing:3px;margin:8px 0}
  .btn{display:inline-block;margin-top:28px;padding:14px 32px;background:#f59e0b;color:#000;text-decoration:none;border-radius:4px;font-weight:700;font-size:15px;transition:background .2s}
  .btn:hover{background:#fcd34d}
  code{background:#1e1e1e;padding:2px 7px;border-radius:3px;font-size:12px;color:#f59e0b}
</style>
</head>
<body>

<h1>ConstructPro — Setup</h1>
<p style="color:#666;font-size:13px;">University of Eswatini · Group 20 · v3.1</p>

<?php if (!empty($errors)): ?>
<div class="box box-err" style="margin-top:20px;">
  <div class="err" style="font-weight:700;margin-bottom:8px;">⚠ Errors encountered (<?= count($errors) ?>):</div>
  <?php foreach ($errors as $e): ?>
  <div class="err" style="font-size:12px;margin-top:4px;">• <?= htmlspecialchars($e) ?></div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($inserted > 0): ?>
<div class="box box-ok" style="margin-top:20px;">
  <div class="ok" style="font-weight:700;font-size:16px;">✅ Setup Complete — <?= $inserted ?> users created</div>
  <div style="margin-top:10px;font-size:13px;color:#aaa;">Default password for all accounts:</div>
  <div class="pw">password123</div>
</div>
<?php else: ?>
<div class="box box-err" style="margin-top:20px;">
  <div class="err" style="font-weight:700;">❌ No users were created. Check errors above.</div>
</div>
<?php endif; ?>

<h2>Login Credentials</h2>
<table>
  <thead><tr><th>Employee ID</th><th>Name</th><th>Role</th><th>Site</th><th>Password</th></tr></thead>
  <tbody>
  <?php
  $badges = ['admin'=>'bp','hr'=>'bb','site_manager'=>'ba','foreman'=>'bo','safety_officer'=>'br','general_labour'=>'bg'];
  foreach ($users as $u): ?>
  <tr>
    <td><strong style="color:#fff;font-size:14px;"><?= $u[0] ?></strong></td>
    <td><?= htmlspecialchars($u[1]) ?></td>
    <td><span class="badge <?= $badges[$u[2]] ?? '' ?>"><?= str_replace('_',' ',$u[2]) ?></span></td>
    <td style="color:#aaa;"><?= htmlspecialchars($u[4]) ?></td>
    <td style="color:#f59e0b;font-weight:700;">password123</td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<h2>Setup Log</h2>
<div class="log-box">
  <?php foreach ($log as [$t, $m]): ?>
  <div class="log-row">
    <span class="<?= $t==='ok'?'ok':($t==='warn'?'warn':'err') ?>"><?= $t==='ok'?'✓':($t==='warn'?'⚠':'✗') ?></span>
    <span style="color:#aaa;"><?= htmlspecialchars($m) ?></span>
  </div>
  <?php endforeach; ?>
</div>

<a class="btn" href="index.php">Go to Login Page →</a>

<p style="margin-top:28px;font-size:12px;color:#444;">
  ⚠ <strong style="color:#f59e0b">Security reminder:</strong>
  Delete <code>setup.php</code> from your server after setup is complete.
</p>

</body>
</html>
