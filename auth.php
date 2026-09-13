<?php
require_once 'config.php';
require_once 'functions.php';
require_once 'csrf.php';

function adminLogin($username, $password) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE username = :username");
    $stmt->bindParam(':username', $username);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $user['username'];
        return true;
    }
    
    return false;
}

function isAdminLoggedIn() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

function adminLogout() {
    unset($_SESSION['admin_logged_in']);
    unset($_SESSION['admin_username']);
    session_destroy();
}

// NOTE: the admin account is created once, during the guided installer
// (install/admin.php), where the buyer sets their own username/password.
// There is intentionally no auto-created fallback admin account here
// anymore — a hardcoded default login is a common attack vector and was
// removed for security.
?>