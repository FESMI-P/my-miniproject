<?php
require_once 'db_connect.php';
require_once 'auth_check.php';

// Ensure user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit;
}

$userId = $_SESSION['user_id'];

try {
    // Get SMS transactions for the current user
    $stmt = $conn->prepare("
        SELECT 
            id,
            amount,
            merchant as description,
            category,
            created_at as date,
            'SMS' as source
        FROM " . SMS_TRANSACTIONS_TABLE . "
        WHERE user_id = :user_id
        ORDER BY created_at DESC
    ");
    
    $stmt->execute([':user_id' => $userId]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'transactions' => $transactions
    ]);
} catch (Exception $e) {
    error_log("Error fetching SMS transactions: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch transactions',
        'error' => $e->getMessage()
    ]);
}
?> 