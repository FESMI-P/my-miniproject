<?php
// Ensure no output before headers
ob_start();

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0); // Disable error output to prevent it from corrupting JSON
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    ob_clean();
    echo json_encode(['success' => false, 'error' => 'User not logged in']);
    exit;
}

require_once 'db_connect.php';

try {
    $user_id = $_SESSION['user_id'];
    
    // Get form data
    $category = $_POST['category'] ?? '';
    $amount = floatval($_POST['amount'] ?? 0);
    $description = $_POST['description'] ?? '';
    $date = $_POST['date'] ?? date('Y-m-d');

    // Validate inputs
    if (empty($category) || $amount <= 0) {
        throw new Exception('Invalid category or amount');
    }

    // Begin transaction
    $conn->beginTransaction();

    try {
        // Add the expense to offline_expenses table
        $stmt = $conn->prepare("INSERT INTO offline_expenses (user_id, category, amount, description, date) VALUES (:user_id, :category, :amount, :description, :date)");
        
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':category', $category, PDO::PARAM_STR);
        $stmt->bindParam(':amount', $amount, PDO::PARAM_STR);
        $stmt->bindParam(':description', $description, PDO::PARAM_STR);
        $stmt->bindParam(':date', $date, PDO::PARAM_STR);
        
        $stmt->execute();

        // Commit transaction
        $conn->commit();
        
        ob_clean(); // Clear any previous output
        echo json_encode(['success' => true]);

    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    error_log("Error in add_expense.php: " . $e->getMessage());
    http_response_code(500);
    ob_clean(); // Clear any previous output
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

// End output buffering
ob_end_flush();
?>