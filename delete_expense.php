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

// Check if it's a POST request and has the required parameters
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['id']) || !isset($_POST['action']) || $_POST['action'] !== 'delete') {
    http_response_code(400);
    ob_clean();
    echo json_encode(['success' => false, 'error' => 'Invalid request method or parameters']);
    exit;
}

require_once 'db_connect.php';

try {
    // Begin transaction
    $conn->beginTransaction();

    // Get the expense ID from the request
    $expenseId = $_POST['id'];
    
    // Verify the expense belongs to the logged-in user
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
    
    // Delete the expense
    $stmt = $conn->prepare("DELETE FROM expenses WHERE id = :id AND user_id = :user_id");
    $stmt->bindParam(':id', $expenseId, PDO::PARAM_INT);
    $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        // Commit transaction
        $conn->commit();
        
        ob_clean();
        echo json_encode([
            'success' => true,
            'message' => 'Expense deleted successfully'
        ]);
    } else {
        throw new Exception('Failed to delete expense');
    }
} catch (Exception $e) {
    // Rollback transaction on error
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    
    error_log("Error in delete_expense.php: " . $e->getMessage());
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