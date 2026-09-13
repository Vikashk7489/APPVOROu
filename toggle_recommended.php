<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isAdminLoggedIn()) {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: all_apps.php");
    exit();
}

$appId = (int)$_GET['id'];
$currentStatus = isset($_GET['current']) ? (int)$_GET['current'] : 0;
$newStatus = $currentStatus ? 0 : 1;

// Update the recommended status
$stmt = $pdo->prepare("UPDATE apps SET is_recommended = ? WHERE id = ?");
$stmt->execute([$newStatus, $appId]);

// Redirect back to all apps page
header("Location: all_apps.php");
exit();
?>