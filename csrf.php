<?php
/**
 * CSRF protection helpers.
 * Include this once (it's pulled in automatically via auth.php) and:
 *   - call csrf_field() inside every admin <form> you render
 *   - call csrf_verify() at the top of every POST handler
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Echoes a hidden input carrying the CSRF token. Purely functional,
 * it has no visual output, so it never affects page design/layout.
 */
function csrf_field() {
    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES) . '">';
}

/**
 * Call at the very top of any POST handler. Stops execution with a 403
 * if the token is missing/invalid (e.g. request forged from another site).
 */
function csrf_verify() {
    $submitted = $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $submitted)) {
        http_response_code(403);
        die('Security check failed (invalid or expired form token). Please go back, refresh the page, and try again.');
    }
}
