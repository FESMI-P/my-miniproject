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

// Check if ID is provided
if (!isset($_GET['id'])) {
    http_response_code(400);
    ob_clean();
    echo json_encode(['success' => false, 'error' => 'Expense ID is required']);
    exit;
}

require_once 'db_connect.php';

try {
    $expenseId = $_GET['id'];
    
    // Fetch the expense for the logged-in user
    $stmt = $conn->prepare("SELECT id, category, amount, description, date FROM expenses WHERE id = :id AND user_id = :user_id");
    $stmt->bindParam(':id', $expenseId, PDO::PARAM_INT);
    $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
    $stmt->execute();
    
    $expense = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($expense) {
        // Format the date to YYYY-MM-DD for the input field
        $expense['date'] = date('Y-m-d', strtotime($expense['date']));
        ob_clean();
        echo json_encode($expense); // Return the expense data directly
    } else {
        http_response_code(404);
        ob_clean();
        echo json_encode([
            'success' => false,
            'error' => 'Expense not found'
        ]);
    }
} catch (PDOException $e) {
    error_log("Error in get_expense.php: " . $e->getMessage());
    http_response_code(500);
    ob_clean();
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}

// End output buffering
ob_end_flush();
?>