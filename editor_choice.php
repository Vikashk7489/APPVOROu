<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

if (!isAdminLoggedIn()) {
    header("Location: login.php");
    exit();
}

if (isset($_GET['id']) && isset($_GET['current'])) {
    $appId = (int)$_GET['id'];
    $currentStatus = (int)$_GET['current'];
    $newStatus = $currentStatus ? 0 : 1;
    
    $stmt = $pdo->prepare("UPDATE apps SET is_editor_choice = ? WHERE id = ?");
    $stmt->execute([$newStatus, $appId]);
    
    // Redirect back to the previous page
    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit();
}

header("Location: all_apps.php");
exit();
?>