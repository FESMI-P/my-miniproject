<?php
// Prevent any output before including
error_reporting(E_ALL);
ini_set('display_errors', 0); // Disable error display

// Database connection parameters
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'expense_tracker1';

try {
    // Create connection using PDO
    $conn = new PDO("mysql:host=$host;dbname=$database", $username, $password);
    
    // Set error mode to exception
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Set charset to utf8
    $conn->exec("SET NAMES utf8");
    
    // Set fetch mode to associative array by default
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Database connection error: " . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}
?> 