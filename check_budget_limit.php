<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Disable error output

// Ensure no output before headers
ob_start();

header('Content-Type: application/json');
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    ob_clean();
    echo json_encode(['success' => false, 'error' => 'User not logged in']);
    exit;
}

// Get JSON data from request body
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Check if required parameters are provided
if (!isset($data['category']) || !isset($data['amount'])) {
    http_response_code(400);
    ob_clean();
    echo json_encode(['success' => false, 'error' => 'Category and amount are required']);
    exit;
}

$category = trim($data['category']);
$amount = floatval($data['amount']);

// Validate inputs
if (empty($category)) {
    http_response_code(400);
    ob_clean();
    echo json_encode(['success' => false, 'error' => 'Category cannot be empty']);
    exit;
}

if ($amount <= 0) {
    http_response_code(400);
    ob_clean();
    echo json_encode(['success' => false, 'error' => 'Amount must be greater than 0']);
    exit;
}

require_once 'db_connect.php';

try {
    $user_id = $_SESSION['user_id'];

    error_log("Received request - Category: $category, Amount: $amount, User ID: $user_id");

    // Get user's total income and savings from income table
    $user_stmt = $conn->prepare("SELECT total_income, savings_goal as savings FROM income WHERE user_id = :user_id");
    $user_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $user_stmt->execute();
    $user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user_data) {
        throw new Exception("User income data not found");
    }

    $total_income = floatval($user_data['total_income']);
    $savings = floatval($user_data['savings']);

    // Get the budget for this category
    $budget_stmt = $conn->prepare("SELECT budget_limit FROM budgets WHERE user_id = :user_id AND category = :category");
    $budget_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $budget_stmt->bindParam(':category', $category, PDO::PARAM_STR);
    $budget_stmt->execute();
    
    if ($budget_stmt->rowCount() === 0) {
        error_log("No budget found for category: $category");
        throw new Exception("No budget found for category: $category. Please set a budget for this category first.");
    }

    $budget = $budget_stmt->fetch(PDO::FETCH_ASSOC);
    $category_budget = floatval($budget['budget_limit']);

    // Get current month's expenses for this category
    $current_month = date('Y-m-01');
    $next_month = date('Y-m-01', strtotime('+1 month'));

    // Get online expenses
    $online_expenses = $conn->prepare("
        SELECT COALESCE(SUM(amount), 0) as total 
        FROM expenses 
        WHERE user_id = :user_id 
        AND category = :category 
        AND created_at >= :current_month 
        AND created_at < :next_month
    ");
    $online_expenses->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $online_expenses->bindParam(':category', $category, PDO::PARAM_STR);
    $online_expenses->bindParam(':current_month', $current_month, PDO::PARAM_STR);
    $online_expenses->bindParam(':next_month', $next_month, PDO::PARAM_STR);
    $online_expenses->execute();
    $online_total = floatval($online_expenses->fetch(PDO::FETCH_ASSOC)['total']);

    // Get offline expenses
    $offline_expenses = $conn->prepare("
        SELECT COALESCE(SUM(amount), 0) as total 
        FROM offline_expenses 
        WHERE user_id = :user_id 
        AND category = :category 
        AND created_at >= :current_month 
        AND created_at < :next_month
    ");
    $offline_expenses->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $offline_expenses->bindParam(':category', $category, PDO::PARAM_STR);
    $offline_expenses->bindParam(':current_month', $current_month, PDO::PARAM_STR);
    $offline_expenses->bindParam(':next_month', $next_month, PDO::PARAM_STR);
    $offline_expenses->execute();
    $offline_total = floatval($offline_expenses->fetch(PDO::FETCH_ASSOC)['total']);

    // Get total expenses across all categories
    $total_expenses_stmt = $conn->prepare("
        SELECT COALESCE(SUM(amount), 0) as total 
        FROM (
            SELECT amount FROM expenses WHERE user_id = :user_id AND created_at >= :current_month AND created_at < :next_month
            UNION ALL
            SELECT amount FROM offline_expenses WHERE user_id = :user_id AND created_at >= :current_month AND created_at < :next_month
        ) as all_expenses
    ");
    $total_expenses_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $total_expenses_stmt->bindParam(':current_month', $current_month, PDO::PARAM_STR);
    $total_expenses_stmt->bindParam(':next_month', $next_month, PDO::PARAM_STR);
    $total_expenses_stmt->execute();
    $total_expenses = floatval($total_expenses_stmt->fetch(PDO::FETCH_ASSOC)['total']);

    $current_total_spent = $online_total + $offline_total;
    $remaining_budget = $category_budget - $current_total_spent;
    $new_total = $current_total_spent + $amount;
    $new_total_all = $total_expenses + $amount;

    // Calculate percentages
    $category_percentage = ($category_budget > 0) ? ($new_total / $category_budget) * 100 : 0;
    $income_percentage = ($total_income > 0) ? ($new_total_all / $total_income) * 100 : 0;
    $savings_percentage = (($total_income - $savings) > 0) ? ($new_total_all / ($total_income - $savings)) * 100 : 0;

    $response = [
        'success' => true,
        'category_budget' => $category_budget,
        'current_total_spent' => $current_total_spent,
        'remaining_budget' => $remaining_budget,
        'new_total' => $new_total,
        'total_income' => $total_income,
        'savings' => $savings,
        'new_total_all' => $new_total_all,
        'category_percentage' => $category_percentage,
        'income_percentage' => $income_percentage,
        'savings_percentage' => $savings_percentage
    ];

    // Case 1: Within budget (green message)
    if ($category_percentage <= 100 && $income_percentage <= 100) {
        $response['warning'] = [
            'type' => 'success',
            'message' => 'Within Budget!',
            'details' => [
                'Category Budget' => $category_budget,
                'Current Spent' => $current_total_spent,
                'New Total' => $new_total,
                'Category Usage' => $category_percentage
            ]
        ];
    }
    // Case 2: Exceeded category budget (red message)
    else if ($category_percentage > 100) {
        $response['warning'] = [
            'type' => 'error',
            'message' => 'Budget Exceeded!',
            'details' => [
                'Budget Limit' => $category_budget,
                'Current Spent' => $current_total_spent,
                'New Total' => $new_total,
                'Exceeded By' => $new_total - $category_budget
            ]
        ];
    }
    // Case 3: Reached savings amount (orange message)
    else if ($savings_percentage >= 100) {
        $response['warning'] = [
            'type' => 'warning',
            'message' => 'Savings Warning!',
            'details' => [
                'Total Income' => $total_income,
                'Savings Target' => $savings,
                'Current Expenses' => $new_total_all,
                'Remaining for Savings' => $total_income - $new_total_all - $savings
            ]
        ];
    }
    // Case 4: Exceeded total income (red message)
    else if ($income_percentage > 100) {
        $response['warning'] = [
            'type' => 'error',
            'message' => 'Income Exceeded!',
            'details' => [
                'Total Income' => $total_income,
                'Current Expenses' => $new_total_all,
                'Exceeded By' => $new_total_all - $total_income
            ]
        ];
    }

    ob_clean();
    echo json_encode($response);

} catch (Exception $e) {
    error_log("Error in check_budget_limit.php: " . $e->getMessage());
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