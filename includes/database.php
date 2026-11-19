<?php
// ===================================================
// EventriX - Database Connection
// Standard beginner-friendly connection for XAMPP / MySQL
// ===================================================

$db_host = 'localhost';
$db_port = '3306';
$db_name = 'eventrix';
$db_user = 'root';
$db_pass = ''; // Default is blank in XAMPP

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $dsn = "mysql:host={$db_host};port={$db_port};dbname={$db_name};charset=utf8mb4";
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
} catch (PDOException $e) {
    // Fallback to 127.0.0.1 if localhost fails on Windows
    try {
        $dsn = "mysql:host=127.0.0.1;port={$db_port};dbname={$db_name};charset=utf8mb4";
        $pdo = new PDO($dsn, $db_user, $db_pass, $options);
    } catch (PDOException $e2) {
        die("<center><h2>Database Connection Failed</h2><p>Please make sure MySQL is running in XAMPP and the database 'eventrix' is imported.</p><p>Error: " . htmlspecialchars($e2->getMessage()) . "</p></center>");
    }
}
?>
