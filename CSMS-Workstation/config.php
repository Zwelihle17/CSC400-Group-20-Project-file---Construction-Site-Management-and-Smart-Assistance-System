<?php
/* ================================================================
   config.php  —  ConstructPro v3
   Database connection, session bootstrap, shared helpers
   ================================================================ */

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'construction_db');

if (session_status() === PHP_SESSION_NONE) session_start();

function getDBConnection(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8", DB_USER, DB_PASS);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            die("<h2 style='font-family:sans-serif;color:red'>DB Error: ".$e->getMessage()."</h2><p style='font-family:sans-serif'>Check config.php credentials and ensure MySQL is running.</p>");
        }
    }
    return $pdo;
}

/* Redirect to login if not authenticated */
function requireLogin(): void {
    if (empty($_SESSION['user_id'])) {
        header("Location: ../index.php"); exit();
    }
}

/* Redirect if role is not in allowed list */
function requireRole(array $roles): void {
    requireLogin();
    if (!in_array($_SESSION['role'], $roles)) {
        header("Location: ../index.php?error=2"); exit();
    }
}

/* Count unread messages for a user */
function getUnreadCount(int $userId): int {
    $s = getDBConnection()->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id=? AND is_read=0");
    $s->execute([$userId]);
    return (int)$s->fetchColumn();
}

/* Write to system_logs table */
function logAction(int $userId, string $action, string $details = ''): void {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    getDBConnection()->prepare("INSERT INTO system_logs(user_id,action,details,ip_address) VALUES(?,?,?,?)")
        ->execute([$userId, $action, $details, $ip]);
}
?>
