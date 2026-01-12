<?php
// Database credentials
$host = 'localhost';
$dbname = 'customer_segmentation_ph';
$username = 'root'; // Replace with your MySQL username
$password = '';     // Replace with your MySQL password

// db.php
// 1. Enable error logging to file, disable display to user
ini_set('log_errors', 1);
ini_set('display_errors', 0);
ini_set('error_log', __DIR__ . '/logs/app_errors.log');

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // 2. Log the raw error internally
    error_log("[DB Error] Connection failed: " . $e->getMessage());
    
    // 3. Show a friendly message and stop
    include 'error_page.php'; // A custom 500 error page
    exit;
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>