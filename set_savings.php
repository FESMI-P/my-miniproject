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
    $savings_goal = floatval($_POST['savings_goal']);

    // Validate input
    if ($savings_goal < 0) {
        echo json_encode(['success' => false, 'message' => 'Savings goal must be a positive number.']);
        exit();
    }

    try {
        // First check if income exists
        $stmt = $conn->prepare("SELECT total_income FROM income WHERE user_id = :user_id");
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        $income = $stmt->fetch();

        if (!$income) {
            echo json_encode(['success' => false, 'message' => 'Please set your income first.']);
            exit();
        }

        // Update the savings goal in the database
        $stmt = $conn->prepare("UPDATE income SET savings_goal = :savings_goal WHERE user_id = :user_id");
        $stmt->bindParam(':savings_goal', $savings_goal, PDO::PARAM_STR);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Savings goal set successfully!']);
        } else {
            throw new PDOException('Failed to set savings goal.');
        }
    } catch (PDOException $e) {
        error_log("Error in set_savings.php: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
}
?>