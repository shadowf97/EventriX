<?php
// ===================================================
// EventriX - Helper Functions File
// Beginner-friendly common utility functions
// ===================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Escape output to prevent Cross-Site Scripting (XSS)
 */
function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/**
 * Trim and sanitize string input
 */
function sanitize_input($data) {
    return trim((string)$data);
}

/**
 * Generate or get CSRF token
 */
function get_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Output hidden input field with CSRF token
 */
function csrf_field() {
    $token = get_csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . e($token) . '">';
}

/**
 * Verify submitted CSRF token
 */
function verify_csrf_token($token) {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Set a one-time flash message
 */
function set_flash_message($type, $message) {
    $_SESSION['flash_type'] = $type; // 'success' or 'error'
    $_SESSION['flash_message'] = $message;
}

/**
 * Display and clear flash message
 */
function display_flash_message() {
    if (!empty($_SESSION['flash_message'])) {
        $type = $_SESSION['flash_type'] ?? 'info';
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_type'], $_SESSION['flash_message']);

        echo "<center>";
        if ($type === 'error') {
            echo "<p><mark><strong>[Error]</strong> " . e($message) . "</mark></p>";
        } else {
            echo "<p><mark><strong>[Success]</strong> " . e($message) . "</mark></p>";
        }
        echo "</center>";
    }
}

/**
 * Format price in Bangladeshi Taka
 */
function format_price($amount) {
    return '৳' . number_format((float)$amount, 2);
}

/**
 * Format date for friendly display
 */
function format_date($date_str) {
    if (empty($date_str)) return 'N/A';
    return date('F j, Y', strtotime($date_str));
}

/**
 * Format time for friendly display
 */
function format_time($time_str) {
    if (empty($time_str)) return 'N/A';
    return date('g:i A', strtotime($time_str));
}

/**
 * Generate a unique booking reference code
 * Example: EVX-202611-4A8F
 */
function generate_booking_reference() {
    $prefix = 'EVX-' . date('Ym') . '-';
    $random_part = strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
    return $prefix . $random_part;
}

/**
 * Generate a unique payment reference code
 * Example: PAY-202611-7C2E
 */
function generate_payment_reference() {
    $prefix = 'PAY-' . date('Ym') . '-';
    $random_part = strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
    return $prefix . $random_part;
}

?>
