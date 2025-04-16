<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'User not logged in']);
    exit();
}

require_once 'db_connect.php';

try {
    // Get user's income and savings goal
    $stmt = $conn->prepare("SELECT total_income, savings_goal FROM income WHERE user_id = :user_id");
    $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
    $stmt->execute();
    $incomeData = $stmt->fetch();

    // If no income data is found, return zeros but don't exit
    if (!$incomeData) {
        $incomeData = [
            'total_income' => 0,
            'savings_goal' => 0
        ];
    }

    $totalIncome = floatval($incomeData['total_income']);
    $savingsGoal = floatval($incomeData['savings_goal']);
    $availableForBudgets = $totalIncome - $savingsGoal;

    // Get total of all budgets
    $stmt = $conn->prepare("SELECT COALESCE(SUM(budget_limit), 0) as total FROM budgets WHERE user_id = :user_id");
    $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
    $stmt->execute();
    $totalBudgets = floatval($stmt->fetchColumn());

    // Log the values for debugging
    error_log("Budget Info - Total Income: $totalIncome, Savings Goal: $savingsGoal, Available: $availableForBudgets, Total Budgets: $totalBudgets");

    echo json_encode([
        'success' => true,
        'budget_info' => [
            'total_income' => $totalIncome,
            'savings_goal' => $savingsGoal,
            'available_amount' => $availableForBudgets,
            'total_budgets' => $totalBudgets,
            'remaining' => $availableForBudgets - $totalBudgets
        ]
    ]);

} catch (PDOException $e) {
    error_log("Error in get_budget_info.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => 'Database error: ' . $e->getMessage(),
        'debug_info' => [
            'user_id' => $_SESSION['user_id'] ?? 'not set'
        ]
    ]);
}
?> 