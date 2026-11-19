<?php
// ===================================================
// EventriX - Authentication & Authorization Helper
// Handles user session checks, roles, and security
// ===================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if the user is currently logged in
 */
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

/**
 * Get current user ID
 */
function current_user_id() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current user's role: 'admin', 'host', or 'user'
 */
function current_user_role() {
    return $_SESSION['role'] ?? null;
}

/**
 * Get current user's full name
 */
function current_user_name() {
    return $_SESSION['full_name'] ?? 'Guest';
}

/**
 * Require user to be logged in
 * If not logged in, redirect to login page
 */
function require_login($base_path = '') {
    if (!is_logged_in()) {
        header("Location: " . $base_path . "login.html");
        exit();
    }
}

/**
 * Require a specific role (e.g. 'admin', 'host', 'user')
 * If role does not match, access is denied
 */
function require_role($required_role, $base_path = '../') {
    require_login($base_path);

    if ($_SESSION['role'] !== $required_role) {
        // Stop execution and show access denied
        echo "<!DOCTYPE html><html><head><title>Access Denied</title></head><body><center>";
        echo "<h1>Access Denied (403)</h1>";
        echo "<p>You do not have permission to view this page.</p>";
        echo "<p><a href='" . $base_path . "index.html'>Return to Homepage</a></p>";
        echo "</center></body></html>";
        exit();
    }
}

/**
 * Redirect logged in user to their role-specific dashboard
 */
function redirect_by_role($role, $base_path = '') {
    if ($role === 'admin') {
        header("Location: " . $base_path . "admin/dashboard.html");
        exit();
    } elseif ($role === 'host') {
        header("Location: " . $base_path . "host/dashboard.html");
        exit();
    } else {
        header("Location: " . $base_path . "user/dashboard.html");
        exit();
    }
}
?>
