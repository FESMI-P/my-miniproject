<?php
header('Content-Type: application/json');
session_start(); // Start the session

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Redirect to login if user is not logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'User not logged in']);
    exit();
}

// Connect to the database
$host = 'localhost';
$dbname = 'expense_tracker1';
$username = 'root';
$password = '';

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log("Database connection error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed: ' . $e->getMessage()]);
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Check if required parameters are provided
        if (!isset($_POST['category']) || !isset($_POST['budget_limit'])) {
            throw new Exception('Category and budget limit are required');
        }

        $user_id = $_SESSION['user_id'];
        $category = trim($_POST['category']);
        $budget_limit = floatval($_POST['budget_limit']);

        // Validate inputs
        if (empty($category)) {
            throw new Exception('Category cannot be empty');
        }

        if ($budget_limit <= 0) {
            throw new Exception('Budget limit must be greater than 0');
        }

        // Begin transaction
        $conn->beginTransaction();

        // Check if category already exists for this user
        $check_stmt = $conn->prepare("SELECT id FROM budgets WHERE user_id = :user_id AND category = :category");
        $check_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $check_stmt->bindParam(':category', $category, PDO::PARAM_STR);
        $check_stmt->execute();
        
        if ($check_stmt->rowCount() > 0) {
            throw new Exception('A budget for this category already exists');
        }

        // Get user's income and savings goal
        $income_stmt = $conn->prepare("SELECT total_income, savings_goal FROM income WHERE user_id = :user_id");
        $income_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $income_stmt->execute();
        $income_data = $income_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$income_data) {
            throw new Exception('Please set your income and savings goal first');
        }

        $totalIncome = floatval($income_data['total_income']);
        $savingsGoal = floatval($income_data['savings_goal']);
        $availableForBudgets = $totalIncome - $savingsGoal;

        // Calculate total of all budgets
        $total_stmt = $conn->prepare("SELECT COALESCE(SUM(budget_limit), 0) as total FROM budgets WHERE user_id = :user_id");
        $total_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $total_stmt->execute();
        $total_data = $total_stmt->fetch(PDO::FETCH_ASSOC);

        $totalBudgets = floatval($total_data['total']);
        $newTotal = $totalBudgets + $budget_limit;

        if ($newTotal > $availableForBudgets) {
            throw new Exception(sprintf(
                'Budget limit exceeds available amount. Available: ₹%.2f, Required: ₹%.2f',
                $availableForBudgets,
                $newTotal
            ));
        }

        // Insert new budget
        $stmt = $conn->prepare("INSERT INTO budgets (user_id, category, budget_limit) VALUES (:user_id, :category, :budget_limit)");
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':category', $category, PDO::PARAM_STR);
        $stmt->bindParam(':budget_limit', $budget_limit, PDO::PARAM_STR);
        
        if ($stmt->execute()) {
            // Commit transaction
            $conn->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Budget set successfully',
                'budget_info' => [
                    'total_income' => $totalIncome,
                    'savings_goal' => $savingsGoal,
                    'available_amount' => $availableForBudgets,
                    'total_budgets' => $newTotal,
                    'remaining' => $availableForBudgets - $newTotal
                ]
            ]);
        } else {
            throw new Exception('Failed to set budget');
        }
    } catch (Exception $e) {
        // Rollback transaction on error
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        
        error_log("Error in set_budget.php: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}
?>