<?php
// Database configuration
$host = "localhost";             // Usually "localhost" on Hostinger
$db   = "u159499074_TileWebsite_DB";                   // Your database name
$user = "u159499074_HiimGelo08";            // MySQL username
$pass = "HiimGelo08";          // MySQL password
$charset = "utf8mb4";            // Recommended character set

// Data Source Name
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

// PDO options
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Throw exceptions on errors
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Fetch rows as associative arrays
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Use real prepared statements
];

try {
    // Create PDO instance
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    // If connection fails, show a readable error
    die("Database connection failed: " . $e->getMessage());
}
?>