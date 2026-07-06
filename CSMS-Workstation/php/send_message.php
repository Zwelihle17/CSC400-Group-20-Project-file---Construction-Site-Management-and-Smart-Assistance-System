<?php
/* ================================================================
   php/send_message.php  —  Send a message, optionally with a file
   Accepts multipart/form-data POST:
     receiver_id, subject, body, attachment (optional file)
   Saves file to /uploads/attachments/, stores path in DB.
   ================================================================ */
require_once '../config.php';
requireLogin();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success'=>false,'error'=>'Invalid method']); exit();
}

$rid  = intval($_POST['receiver_id'] ?? 0);
$subj = trim($_POST['subject']       ?? '');
$body = trim($_POST['body']          ?? '');

if (!$rid || !$subj || !$body) {
    echo json_encode(['success'=>false,'error'=>'Missing required fields']); exit();
}

$attachName = null;
$attachPath = null;

/* ── Handle optional file upload ── */
if (!empty($_FILES['attachment']['name'])) {
    $file     = $_FILES['attachment'];
    $origName = basename($file['name']);
    $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

    /* Allowed file types */
    $allowed = ['pdf','doc','docx','xls','xlsx','png','jpg','jpeg','txt','zip','ppt','pptx'];
    if (!in_array($ext, $allowed)) {
        echo json_encode(['success'=>false,'error'=>'File type not allowed. Allowed: '.implode(', ',$allowed)]); exit();
    }

    /* Max 10 MB */
    if ($file['size'] > 10 * 1024 * 1024) {
        echo json_encode(['success'=>false,'error'=>'File too large. Maximum size is 10 MB.']); exit();
    }

    /* Create upload directory if needed */
    $uploadDir = dirname(__DIR__) . '/uploads/attachments/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    /* Unique filename to prevent overwrites */
    $safeName   = preg_replace('/[^a-zA-Z0-9._-]/', '_', $origName);
    $uniqueName = date('Ymd_His') . '_' . $_SESSION['user_id'] . '_' . $safeName;
    $destPath   = $uploadDir . $uniqueName;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        echo json_encode(['success'=>false,'error'=>'Failed to save uploaded file.']); exit();
    }

    $attachName = $origName;
    $attachPath = 'uploads/attachments/' . $uniqueName;
}

/* ── Insert message into DB ── */
$pdo = getDBConnection();
$pdo->prepare(
    "INSERT INTO messages (sender_id, receiver_id, subject, body, attachment_name, attachment_path)
     VALUES (?, ?, ?, ?, ?, ?)"
)->execute([$_SESSION['user_id'], $rid, $subj, $body, $attachName, $attachPath]);

try { logAction($_SESSION['user_id'], 'MESSAGE_SENT', "To user $rid: $subj" . ($attachName ? " [attachment: $attachName]" : '')); }
catch (Exception $e) { /* ignore log errors */ }

echo json_encode(['success'=>true]);
?>
