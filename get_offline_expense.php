<?php
header('Content-Type: application/json');
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'User not logged in']);
    exit;
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
$id = isset($data['id']) ? intval($data['id']) : 0;

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid expense ID']);
    exit;
}

require_once 'db_connect.php';

try {
    // Get expense details
    $stmt = $conn->prepare("
        SELECT id, category, amount, description, DATE_FORMAT(created_at, '%Y-%m-%d') as date
        FROM offline_expenses
        WHERE id = :id AND user_id = :user_id
    ");
    
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        throw new Exception('Expense not found');
    }
    
    $expense = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'expense' => $expense
    ]);
} catch (Exception $e) {
    error_log("Error in get_offline_expense.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?> 