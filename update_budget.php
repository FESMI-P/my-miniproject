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
    ob_clean();
    echo json_encode(['success' => false, 'error' => 'User not logged in']);
    exit;
}

// Get and validate JSON data
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data || !isset($data['id']) || !isset($data['category']) || !isset($data['budget_limit'])) {
    http_response_code(400);
    ob_clean();
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

$budget_id = intval($data['id']);
$category = trim($data['category']);
$budget_limit = floatval($data['budget_limit']);

// Validate inputs
if ($budget_id <= 0) {
    http_response_code(400);
    ob_clean();
    echo json_encode(['success' => false, 'error' => 'Invalid budget ID']);
    exit;
}

if (empty($category)) {
    http_response_code(400);
    ob_clean();
    echo json_encode(['success' => false, 'error' => 'Category cannot be empty']);
    exit;
}

if ($budget_limit <= 0) {
    http_response_code(400);
    ob_clean();
    echo json_encode(['success' => false, 'error' => 'Budget limit must be greater than 0']);
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

    $current_budget = $check_stmt->fetch(PDO::FETCH_ASSOC);

    // Check if the new category already exists for this user (excluding the current budget)
    if ($current_budget['category'] !== $category) {
        $category_check = $conn->prepare("SELECT id FROM budgets WHERE user_id = :user_id AND category = :category AND id != :id");
        $category_check->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
        $category_check->bindParam(':category', $category, PDO::PARAM_STR);
        $category_check->bindParam(':id', $budget_id, PDO::PARAM_INT);
        $category_check->execute();
        
        if ($category_check->rowCount() > 0) {
            throw new Exception('A budget for this category already exists');
        }
    }

    // Get user's income and savings goal
    $income_stmt = $conn->prepare("SELECT total_income, savings_goal FROM income WHERE user_id = :user_id");
    $income_stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
    $income_stmt->execute();
    $income_data = $income_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$income_data) {
        throw new Exception('Please set your income and savings goal first');
    }

    $totalIncome = floatval($income_data['total_income']);
    $savingsGoal = floatval($income_data['savings_goal']);
    $availableForBudgets = $totalIncome - $savingsGoal;

    // Calculate total of all other budgets (excluding current budget)
    $total_stmt = $conn->prepare("SELECT COALESCE(SUM(budget_limit), 0) as total FROM budgets WHERE user_id = :user_id AND id != :id");
    $total_stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
    $total_stmt->bindParam(':id', $budget_id, PDO::PARAM_INT);
    $total_stmt->execute();
    $total_data = $total_stmt->fetch(PDO::FETCH_ASSOC);

    $totalOtherBudgets = floatval($total_data['total']);
    $totalBudget = $totalOtherBudgets + $budget_limit;

    if ($totalBudget > $availableForBudgets) {
        throw new Exception(sprintf(
            'Budget limit exceeds available amount. Available: ₹%.2f, Required: ₹%.2f',
            $availableForBudgets,
            $totalBudget
        ));
    }

    // Update the budget
    $update_stmt = $conn->prepare("UPDATE budgets SET category = :category, budget_limit = :budget_limit WHERE id = :id AND user_id = :user_id");
    $update_stmt->bindParam(':category', $category, PDO::PARAM_STR);
    $update_stmt->bindParam(':budget_limit', $budget_limit, PDO::PARAM_STR);
    $update_stmt->bindParam(':id', $budget_id, PDO::PARAM_INT);
    $update_stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
    
    if ($update_stmt->execute()) {
        // Commit transaction
        $conn->commit();
        
        ob_clean();
        echo json_encode([
            'success' => true,
            'message' => 'Budget updated successfully',
            'budget_info' => [
                'total_income' => $totalIncome,
                'savings_goal' => $savingsGoal,
                'available_amount' => $availableForBudgets,
                'total_budgets' => $totalBudget,
                'remaining' => $availableForBudgets - $totalBudget
            ]
        ]);
    } else {
        throw new Exception('Failed to update budget');
    }
} catch (Exception $e) {
    // Rollback transaction on error
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    
    error_log("Error in update_budget.php: " . $e->getMessage());
    http_response_code(500);
    ob_clean();
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

// End output buffering
ob_end_flush();
?> 