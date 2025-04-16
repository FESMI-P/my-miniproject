<?php
// Ensure no output before headers
ob_start();

// Set headers
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');
error_reporting(E_ALL);
ini_set('display_errors', 0); // Disable error output to prevent it from corrupting JSON

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'User not logged in']);
    exit;
}

require_once 'db_connect.php';

try {
    // Fetch budgets for the current user only
    $stmt = $conn->prepare("SELECT id, category, budget_limit FROM budgets WHERE user_id = :user_id");
    $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
    $stmt->execute();
    
    $budgets = [];
    while ($row = $stmt->fetch()) {
        $budgets[] = [
            'id' => (int)$row['id'],
            'category' => $row['category'],
            'budget_limit' => (float)$row['budget_limit']
        ];
    }
    
    // Log the number of budgets found
    error_log("Found " . count($budgets) . " budgets for user_id: " . $_SESSION['user_id']);
    
    ob_clean(); // Clear any previous output
    echo json_encode([
        'success' => true,
        'budgets' => $budgets
    ]);
    
} catch (PDOException $e) {
    error_log("Error in get_budgets.php: " . $e->getMessage());
    http_response_code(500);
    ob_clean(); // Clear any previous output
    echo json_encode([
        'success' => false, 
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}

// End output buffering and flush
ob_end_flush();
?>