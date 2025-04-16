<?php
// Ensure no output before headers
ob_start();

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0); // Disable error output
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'User not logged in']);
    exit;
}

require_once 'db_connect.php';

try {
    error_log("Getting expenses for user_id: " . $_SESSION['user_id']);
    
    // Get offline expenses
    $offlineStmt = $conn->prepare("
        SELECT 
            id, 
            category, 
            amount, 
            description, 
            DATE_FORMAT(date, '%Y-%m-%d') as date,
            'manual' as source,
            user_id
        FROM offline_expenses 
        WHERE user_id = :user_id
        ORDER BY date DESC
    ");
    
    $offlineStmt->execute([':user_id' => $_SESSION['user_id']]);
    $offlineExpenses = $offlineStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get online expenses (from Telegram)
    $onlineStmt = $conn->prepare("
        SELECT 
            id, 
            category, 
            amount, 
            description, 
            DATE_FORMAT(created_at, '%Y-%m-%d') as date,
            'telegram' as source,
            user_id
        FROM expenses 
        WHERE source = 'telegram'
        ORDER BY created_at DESC
    ");
    
    $onlineStmt->execute();
    $onlineExpenses = $onlineStmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("Retrieved " . count($offlineExpenses) . " offline expenses and " . count($onlineExpenses) . " online expenses");
    
    ob_clean(); // Clear any previous output
    echo json_encode([
        'success' => true,
        'offline_expenses' => $offlineExpenses,
        'online_expenses' => $onlineExpenses
    ]);
    
} catch (PDOException $e) {
    error_log("Error in get_expenses.php: " . $e->getMessage());
    http_response_code(500);
    ob_clean(); // Clear any previous output
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}

// End output buffering
ob_end_flush();
?>