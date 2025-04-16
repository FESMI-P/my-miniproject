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

// Check if budget ID is provided
if (!isset($_GET['id'])) {
    http_response_code(400);
    ob_clean();
    echo json_encode(['success' => false, 'error' => 'Budget ID is required']);
    exit;
}

$budget_id = intval($_GET['id']);

if ($budget_id <= 0) {
    http_response_code(400);
    ob_clean();
    echo json_encode(['success' => false, 'error' => 'Invalid budget ID']);
    exit;
}

try {
    require_once 'db_connect.php';
    
    // Fetch the budget details
    $stmt = $conn->prepare("SELECT id, category, budget_limit FROM budgets WHERE id = :id AND user_id = :user_id");
    $stmt->bindParam(':id', $budget_id, PDO::PARAM_INT);
    $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'Budget not found']);
        exit;
    }

    $budget = $stmt->fetch(PDO::FETCH_ASSOC);
    
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
    $totalBudget = $totalOtherBudgets + floatval($budget['budget_limit']);

    ob_clean();
    echo json_encode([
        'success' => true,
        'data' => [
            'id' => (int)$budget['id'],
            'category' => $budget['category'],
            'budget_limit' => (float)$budget['budget_limit'],
            'total_income' => $totalIncome,
            'savings_goal' => $savingsGoal,
            'available_amount' => $availableForBudgets,
            'total_budgets' => $totalBudget,
            'remaining' => $availableForBudgets - $totalBudget
        ]
    ]);
} catch (Exception $e) {
    error_log("Error in get_budget.php: " . $e->getMessage());
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