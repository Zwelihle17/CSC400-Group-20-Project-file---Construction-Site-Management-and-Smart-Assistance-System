<?php
require_once '../config.php';
if (!empty($_SESSION['user_id'])) logAction($_SESSION['user_id'], 'LOGOUT', '');
session_destroy();
header("Location: ../index.php"); exit();
?>
