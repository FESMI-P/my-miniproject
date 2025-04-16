<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'User not logged in']);
    exit;
}

// Check if budget ID is provided
if (!isset($_POST['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Budget ID is required']);
    exit;
}

$budget_id = intval($_POST['id']);

if ($budget_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid budget ID']);
    exit;
}

require_once 'db_connect.php';

try {
    // Begin transaction
    $conn->beginTransaction();

    // First verify that this budget belongs to the logged-in user
    $check_stmt = $conn->prepare("SELECT id, category FROM budgets WHERE id = :id AND user_id = :user_id");
    $check_stmt->bindParam(':id', $budget_id, PDO::PARAM_INT);
    $check_stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() === 0) {
        throw new Exception('Budget not found or unauthorized');
    }

    $budget = $check_stmt->fetch(PDO::FETCH_ASSOC);
    $category = $budget['category'];

    // Check for expenses in the current month using created_at
    $current_month = date('Y-m-01');
    $next_month = date('Y-m-01', strtotime('+1 month'));

    // Check online expenses
    $online_expenses = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM expenses 
        WHERE user_id = :user_id 
        AND category = :category 
        AND created_at >= :current_month 
        AND created_at < :next_month
    ");
    $online_expenses->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
    $online_expenses->bindParam(':category', $category, PDO::PARAM_STR);
    $online_expenses->bindParam(':current_month', $current_month, PDO::PARAM_STR);
    $online_expenses->bindParam(':next_month', $next_month, PDO::PARAM_STR);
    $online_expenses->execute();
    $online_count = $online_expenses->fetch(PDO::FETCH_ASSOC)['count'];

    // Check offline expenses
    $offline_expenses = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM offline_expenses 
        WHERE user_id = :user_id 
        AND category = :category 
        AND created_at >= :current_month 
        AND created_at < :next_month
    ");
    $offline_expenses->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
    $offline_expenses->bindParam(':category', $category, PDO::PARAM_STR);
    $offline_expenses->bindParam(':current_month', $current_month, PDO::PARAM_STR);
    $offline_expenses->bindParam(':next_month', $next_month, PDO::PARAM_STR);
    $offline_expenses->execute();
    $offline_count = $offline_expenses->fetch(PDO::FETCH_ASSOC)['count'];

    $total_expenses = $online_count + $offline_count;

    if ($total_expenses > 0) {
        throw new Exception('Cannot delete this budget because there are expenses using it in the current month. Please wait until next month or delete the associated expenses first.');
    }

    // Delete the budget
    $delete_stmt = $conn->prepare("DELETE FROM budgets WHERE id = :id AND user_id = :user_id");
    $delete_stmt->bindParam(':id', $budget_id, PDO::PARAM_INT);
    $delete_stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
    
    if ($delete_stmt->execute()) {
        // Commit transaction
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Budget deleted successfully'
        ]);
    } else {
        throw new Exception('Failed to delete budget');
    }
} catch (Exception $e) {
    // Rollback transaction on error
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    
    error_log("Error in delete_budget.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>