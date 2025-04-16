<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session and set headers
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'User not logged in']);
    exit;
}

// Get JSON data from request body
$input = file_get_contents('php://input');
error_log("Raw input: " . $input);

$data = json_decode($input, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    error_log("JSON decode error: " . json_last_error_msg());
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON data']);
    exit;
}

// Log the received data for debugging
error_log("Received delete request data: " . print_r($data, true));

// Validate input
if (!isset($data['id']) || !is_numeric($data['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid expense ID']);
    exit;
}

try {
    // Connect to database
    require_once 'db_connect.php';
    error_log("Database connection successful");
    
    // Begin transaction
    $conn->beginTransaction();
    error_log("Transaction started");
    
    // Delete the expense from offline_expenses table
    $query = "DELETE FROM offline_expenses WHERE id = :id AND user_id = :user_id";
    error_log("Executing query: " . $query . " with id: " . $data['id'] . " and user_id: " . $_SESSION['user_id']);
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        error_log("Failed to prepare statement");
        throw new Exception("Failed to prepare statement");
    }
    error_log("Statement prepared successfully");
    
    $stmt->bindParam(':id', $data['id'], PDO::PARAM_INT);
    $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
    error_log("Parameters bound successfully");
    
    if (!$stmt->execute()) {
        error_log("Failed to execute statement: " . print_r($stmt->errorInfo(), true));
        throw new Exception("Failed to delete expense");
    }
    error_log("Statement executed successfully");
    
    // Check if any rows were affected
    if ($stmt->rowCount() === 0) {
        error_log("No rows affected by delete operation");
        throw new Exception("Expense not found or unauthorized");
    }
    error_log("Rows affected: " . $stmt->rowCount());
    
    // Commit transaction
    $conn->commit();
    error_log("Transaction committed successfully");
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if ($conn->inTransaction()) {
        $conn->rollBack();
        error_log("Transaction rolled back due to error");
    }
    
    error_log("Error deleting expense: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

// Close statement and connection
if (isset($stmt)) {
    $stmt = null;
}
if (isset($conn)) {
    $conn = null;
}
?> 