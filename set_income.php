<?php
header('Content-Type: application/json');
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'User not logged in']);
    exit();
}

require_once 'db_connect.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $total_income = floatval($_POST['total_income']);

    // Validate input
    if ($total_income <= 0) {
        echo json_encode(['success' => false, 'message' => 'Income must be a positive number.']);
        exit();
    }

    try {
        // Insert or update the income in the database
        $stmt = $conn->prepare("INSERT INTO income (user_id, total_income) VALUES (:user_id, :total_income) ON DUPLICATE KEY UPDATE total_income = :total_income");
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':total_income', $total_income, PDO::PARAM_STR);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Income set successfully!']);
        } else {
            throw new PDOException('Failed to set income.');
        }
    } catch (PDOException $e) {
        error_log("Error in set_income.php: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
}
?>