<?php
require_once '../config.php'; requireLogin();
header('Content-Type: application/json');
$mid = intval($_POST['msg_id']??0);
if($mid){
    getDBConnection()->prepare("UPDATE messages SET is_read=1 WHERE id=? AND receiver_id=?")->execute([$mid,$_SESSION['user_id']]);
}
echo json_encode(['success'=>true]);
?>
