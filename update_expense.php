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

// Check if all required fields are present
if (!isset($_POST['id']) || !isset($_POST['category']) || !isset($_POST['amount']) || !isset($_POST['date'])) {
    http_response_code(400);
    ob_clean();
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

require_once 'db_connect.php';

try {
    // Begin transaction
    $conn->beginTransaction();

    try {
        // Get the expense ID and verify ownership
        $expenseId = $_POST['id'];
        $stmt = $conn->prepare("SELECT user_id FROM expenses WHERE id = :id");
        $stmt->bindParam(':id', $expenseId, PDO::PARAM_INT);
        $stmt->execute();
        $expense = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$expense) {
            throw new Exception('Expense not found');
        }
        
        if ($expense['user_id'] != $_SESSION['user_id']) {
            throw new Exception('Unauthorized access');
        }
        
        // Update the expense
        $stmt = $conn->prepare("UPDATE expenses SET category = :category, amount = :amount, description = :description, date = :date WHERE id = :id AND user_id = :user_id");
        
        $stmt->bindParam(':category', $_POST['category'], PDO::PARAM_STR);
        $stmt->bindParam(':amount', $_POST['amount'], PDO::PARAM_STR);
        $stmt->bindParam(':description', $_POST['description'], PDO::PARAM_STR);
        $stmt->bindParam(':date', $_POST['date'], PDO::PARAM_STR);
        $stmt->bindParam(':id', $expenseId, PDO::PARAM_INT);
        $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
        
        $stmt->execute();
        
        if ($stmt->rowCount() > 0 || $stmt->errorCode() === '00000') {
            // Commit transaction
            $conn->commit();
            
            ob_clean();
            echo json_encode(['success' => true]);
        } else {
            throw new Exception('No changes made to expense');
        }
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollBack();
        throw $e;
    }
} catch (Exception $e) {
    error_log("Error in update_expense.php: " . $e->getMessage());
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