<?php
header('Content-Type: application/json');
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'User not logged in']);
    exit;
}

// Validate required fields
if (!isset($_POST['id']) || !isset($_POST['category']) || !isset($_POST['amount']) || !isset($_POST['date'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

$id = intval($_POST['id']);
$category = trim($_POST['category']);
$amount = floatval($_POST['amount']);
$description = isset($_POST['description']) ? trim($_POST['description']) : '';
$date = trim($_POST['date']);

// Validate inputs
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid expense ID']);
    exit;
}

if (empty($category)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Category cannot be empty']);
    exit;
}

if ($amount <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Amount must be greater than 0']);
    exit;
}

require_once 'db_connect.php';

try {
    // Start transaction
    $conn->beginTransaction();

    // Check if expense exists and belongs to user
    $check_stmt = $conn->prepare("
        SELECT id FROM offline_expenses 
        WHERE id = :id AND user_id = :user_id
    ");
    $check_stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $check_stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
    $check_stmt->execute();

    if ($check_stmt->rowCount() === 0) {
        throw new Exception('Expense not found or unauthorized');
    }

    // Update the expense
    $update_stmt = $conn->prepare("
        UPDATE offline_expenses 
        SET category = :category,
            amount = :amount,
            description = :description,
            created_at = :date
        WHERE id = :id AND user_id = :user_id
    ");

    $update_stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $update_stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
    $update_stmt->bindParam(':category', $category, PDO::PARAM_STR);
    $update_stmt->bindParam(':amount', $amount, PDO::PARAM_STR);
    $update_stmt->bindParam(':description', $description, PDO::PARAM_STR);
    $update_stmt->bindParam(':date', $date, PDO::PARAM_STR);

    if (!$update_stmt->execute()) {
        throw new Exception('Failed to update expense');
    }

    // Commit transaction
    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Expense updated successfully'
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    error_log("Error in update_offline_expense.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?> 