<?php
require_once 'db_connect.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    $user_id = 1; // Default user ID
    $category = 'Food & Dining';
    $budget_limit = 10000.00;
    $priority = 'High';

    // First check if budget exists
    $stmt = $conn->prepare("SELECT id FROM budgets WHERE category = :category AND user_id = :user_id");
    $stmt->bindParam(':category', $category);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        // Update existing budget
        $stmt = $conn->prepare("UPDATE budgets SET budget_limit = :budget_limit, priority = :priority WHERE id = :id");
        $stmt->bindParam(':budget_limit', $budget_limit);
        $stmt->bindParam(':priority', $priority);
        $stmt->bindParam(':id', $existing['id']);
        $stmt->execute();
        echo "Budget updated successfully!";
    } else {
        // Insert new budget
        $stmt = $conn->prepare("INSERT INTO budgets (user_id, category, budget_limit, priority) VALUES (:user_id, :category, :budget_limit, :priority)");
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':category', $category);
        $stmt->bindParam(':budget_limit', $budget_limit);
        $stmt->bindParam(':priority', $priority);
        $stmt->execute();
        echo "Budget created successfully!";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?> 