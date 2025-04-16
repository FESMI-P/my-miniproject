<?php
session_start(); // Start the session

// Connect to the database
$host = 'localhost';
$dbname = 'expense_tracker1'; // Updated database name
$username = 'root'; // Default username for XAMPP
$password = ''; // Default password for XAMPP

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die(json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]));
}

// Check if the income table is empty
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM income");
$stmt->execute();
$incomeCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Check if the budgets table is empty
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM budgets");
$stmt->execute();
$budgetsCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Return result
if ($incomeCount == 0 && $budgetsCount == 0) {
    echo json_encode(['isEmpty' => true]);
} else {
    echo json_encode(['isEmpty' => false]);
}
?>