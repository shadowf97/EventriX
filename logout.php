<?php
// ===================================================
// EventriX - Logout Handler
// Safely terminates session and redirects to login
// ===================================================

session_start();

require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/functions.php';

if (isset($_SESSION['user_id'])) {
}

// Clear all session data
$_SESSION = [];

// Invalidate session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 3600,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy session data from server storage
session_destroy();

header("Location: login.html?status=logged_out");
exit();
?>
