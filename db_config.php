<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'expense_tracker1');

function getDBConnection() {
    try {
        $conn = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
            DB_USER,
            DB_PASS
        );
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        error_log("Database connection successful");
        return $conn;
    } catch(PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        return false;
    }
}

function saveExpense($user_id, $amount, $description, $category) {
    $conn = getDBConnection();
    if (!$conn) {
        error_log("Failed to get database connection in saveExpense");
        return false;
    }

    try {
        $stmt = $conn->prepare("INSERT INTO expenses (user_id, amount, description, category, source, created_at) VALUES (?, ?, ?, ?, 'telegram', NOW())");
        $result = $stmt->execute([$user_id, $amount, $description, $category]);
        error_log("Expense saved successfully: user_id=$user_id, amount=$amount, description=$description, category=$category, source=telegram");
        return $result;
    } catch(PDOException $e) {
        error_log("Error saving expense: " . $e->getMessage());
        return false;
    }
}

function getRecentExpenses($user_id, $limit = 5) {
    $conn = getDBConnection();
    if (!$conn) {
        error_log("Failed to get database connection in getRecentExpenses");
        return false;
    }

    try {
        $query = "SELECT * FROM expenses WHERE user_id = :user_id ORDER BY created_at DESC LIMIT :limit";
        $stmt = $conn->prepare($query);
        $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("Retrieved " . count($expenses) . " expenses for user_id=$user_id");
        return $expenses;
    } catch(PDOException $e) {
        error_log("Error fetching expenses: " . $e->getMessage());
        return false;
    }
} 