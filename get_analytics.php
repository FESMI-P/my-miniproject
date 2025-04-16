<?php
header('Content-Type: application/json');
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'User not logged in']);
    exit;
}

require_once 'db_connect.php';

try {
    $user_id = $_SESSION['user_id'];
    $current_month = date('Y-m-01');
    $next_month = date('Y-m-01', strtotime('+1 month'));

    // Get total spent (both online and offline)
    $total_stmt = $conn->prepare("
        SELECT COALESCE(
            (SELECT SUM(amount) FROM expenses WHERE user_id = :user_id) +
            (SELECT COALESCE(SUM(amount), 0) FROM offline_expenses WHERE user_id = :user_id2),
            0
        ) as total_spent
    ");
    $total_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $total_stmt->bindParam(':user_id2', $user_id, PDO::PARAM_INT);
    $total_stmt->execute();
    $total_spent = floatval($total_stmt->fetch(PDO::FETCH_ASSOC)['total_spent']);

    // Get average daily spending
    $avg_daily_stmt = $conn->prepare("
        SELECT COALESCE(
            (SELECT AVG(daily_total) FROM (
                SELECT DATE(created_at) as date, SUM(amount) as daily_total
                FROM expenses
                WHERE user_id = :user_id
                GROUP BY DATE(created_at)
                UNION ALL
                SELECT DATE(created_at) as date, SUM(amount) as daily_total
                FROM offline_expenses
                WHERE user_id = :user_id2
                GROUP BY DATE(created_at)
            ) daily_totals),
            0
        ) as avg_daily
    ");
    $avg_daily_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $avg_daily_stmt->bindParam(':user_id2', $user_id, PDO::PARAM_INT);
    $avg_daily_stmt->execute();
    $avg_daily = floatval($avg_daily_stmt->fetch(PDO::FETCH_ASSOC)['avg_daily']);

    // Get current month's spending
    $monthly_stmt = $conn->prepare("
        SELECT COALESCE(
            (SELECT SUM(amount) FROM expenses 
            WHERE user_id = :user_id 
            AND created_at >= :current_month 
            AND created_at < :next_month) +
            (SELECT COALESCE(SUM(amount), 0) FROM offline_expenses 
            WHERE user_id = :user_id2 
            AND created_at >= :current_month 
            AND created_at < :next_month),
            0
        ) as monthly_spent
    ");
    $monthly_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $monthly_stmt->bindParam(':user_id2', $user_id, PDO::PARAM_INT);
    $monthly_stmt->bindParam(':current_month', $current_month, PDO::PARAM_STR);
    $monthly_stmt->bindParam(':next_month', $next_month, PDO::PARAM_STR);
    $monthly_stmt->execute();
    $monthly_spent = floatval($monthly_stmt->fetch(PDO::FETCH_ASSOC)['monthly_spent']);

    // Get monthly comparison (online vs offline)
    $comparison_stmt = $conn->prepare("
        SELECT 
            DATE_FORMAT(month, '%M %Y') as month,
            SUM(online) as online,
            SUM(offline) as offline
        FROM (
            SELECT 
                DATE_FORMAT(created_at, '%Y-%m-01') as month,
                SUM(amount) as online,
                0 as offline
            FROM expenses
            WHERE user_id = :user_id
            AND created_at >= DATE_SUB(CURRENT_DATE, INTERVAL 6 MONTH)
            GROUP BY DATE_FORMAT(created_at, '%Y-%m-01')
            UNION ALL
            SELECT 
                DATE_FORMAT(created_at, '%Y-%m-01') as month,
                0 as online,
                SUM(amount) as offline
            FROM offline_expenses
            WHERE user_id = :user_id2
            AND created_at >= DATE_SUB(CURRENT_DATE, INTERVAL 6 MONTH)
            GROUP BY DATE_FORMAT(created_at, '%Y-%m-01')
        ) monthly_data
        GROUP BY month
        ORDER BY month ASC
    ");
    $comparison_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $comparison_stmt->bindParam(':user_id2', $user_id, PDO::PARAM_INT);
    $comparison_stmt->execute();
    $monthly_comparison = $comparison_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get savings data
    $savings_stmt = $conn->prepare("
        SELECT 
            DATE_FORMAT(month, '%M %Y') as month,
            SUM(savings) as savings
        FROM (
            SELECT 
                DATE_FORMAT(created_at, '%Y-%m-01') as month,
                (SELECT total_income FROM income WHERE user_id = :user_id) - SUM(amount) as savings
            FROM expenses
            WHERE user_id = :user_id2
            AND created_at >= DATE_SUB(CURRENT_DATE, INTERVAL 6 MONTH)
            GROUP BY DATE_FORMAT(created_at, '%Y-%m-01')
            UNION ALL
            SELECT 
                DATE_FORMAT(created_at, '%Y-%m-01') as month,
                (SELECT total_income FROM income WHERE user_id = :user_id3) - SUM(amount) as savings
            FROM offline_expenses
            WHERE user_id = :user_id4
            AND created_at >= DATE_SUB(CURRENT_DATE, INTERVAL 6 MONTH)
            GROUP BY DATE_FORMAT(created_at, '%Y-%m-01')
        ) savings_data
        GROUP BY month
        ORDER BY month ASC
    ");
    $savings_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $savings_stmt->bindParam(':user_id2', $user_id, PDO::PARAM_INT);
    $savings_stmt->bindParam(':user_id3', $user_id, PDO::PARAM_INT);
    $savings_stmt->bindParam(':user_id4', $user_id, PDO::PARAM_INT);
    $savings_stmt->execute();
    $savings_trend = $savings_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get current month's savings
    $monthly_savings_stmt = $conn->prepare("
        SELECT COALESCE(
            (SELECT total_income FROM income WHERE user_id = :user_id) -
            (SELECT COALESCE(SUM(amount), 0) FROM expenses 
            WHERE user_id = :user_id2 
            AND created_at >= :current_month 
            AND created_at < :next_month) -
            (SELECT COALESCE(SUM(amount), 0) FROM offline_expenses 
            WHERE user_id = :user_id3 
            AND created_at >= :current_month 
            AND created_at < :next_month),
            0
        ) as monthly_savings
    ");
    $monthly_savings_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $monthly_savings_stmt->bindParam(':user_id2', $user_id, PDO::PARAM_INT);
    $monthly_savings_stmt->bindParam(':user_id3', $user_id, PDO::PARAM_INT);
    $monthly_savings_stmt->bindParam(':current_month', $current_month, PDO::PARAM_STR);
    $monthly_savings_stmt->bindParam(':next_month', $next_month, PDO::PARAM_STR);
    $monthly_savings_stmt->execute();
    $monthly_savings = floatval($monthly_savings_stmt->fetch(PDO::FETCH_ASSOC)['monthly_savings']);

    // Get category distribution
    $category_stmt = $conn->prepare("
        SELECT category, SUM(total) as total
        FROM (
            SELECT category, SUM(amount) as total
            FROM expenses
            WHERE user_id = :user_id
            GROUP BY category
            UNION ALL
            SELECT category, SUM(amount) as total
            FROM offline_expenses
            WHERE user_id = :user_id2
            GROUP BY category
        ) combined
        GROUP BY category
        ORDER BY total DESC
    ");
    $category_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $category_stmt->bindParam(':user_id2', $user_id, PDO::PARAM_INT);
    $category_stmt->execute();
    $category_distribution = $category_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get monthly trend (last 6 months)
    $trend_stmt = $conn->prepare("
        SELECT DATE_FORMAT(month, '%M %Y') as month, SUM(total) as total
        FROM (
            SELECT DATE_FORMAT(created_at, '%Y-%m-01') as month, SUM(amount) as total
            FROM expenses
            WHERE user_id = :user_id
            AND created_at >= DATE_SUB(CURRENT_DATE, INTERVAL 6 MONTH)
            GROUP BY DATE_FORMAT(created_at, '%Y-%m-01')
            UNION ALL
            SELECT DATE_FORMAT(created_at, '%Y-%m-01') as month, SUM(amount) as total
            FROM offline_expenses
            WHERE user_id = :user_id2
            AND created_at >= DATE_SUB(CURRENT_DATE, INTERVAL 6 MONTH)
            GROUP BY DATE_FORMAT(created_at, '%Y-%m-01')
        ) monthly_totals
        GROUP BY month
        ORDER BY month ASC
    ");
    $trend_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $trend_stmt->bindParam(':user_id2', $user_id, PDO::PARAM_INT);
    $trend_stmt->execute();
    $monthly_trend = $trend_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get total number of categories
    $categories_stmt = $conn->prepare("
        SELECT COUNT(DISTINCT category) as total_categories
        FROM (
            SELECT category FROM expenses WHERE user_id = :user_id
            UNION
            SELECT category FROM offline_expenses WHERE user_id = :user_id2
        ) combined
    ");
    $categories_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $categories_stmt->bindParam(':user_id2', $user_id, PDO::PARAM_INT);
    $categories_stmt->execute();
    $total_categories = intval($categories_stmt->fetch(PDO::FETCH_ASSOC)['total_categories']);

    echo json_encode([
        'success' => true,
        'total_spent' => $total_spent,
        'avg_daily' => $avg_daily,
        'monthly_spent' => $monthly_spent,
        'monthly_savings' => $monthly_savings,
        'category_distribution' => $category_distribution,
        'monthly_trend' => $monthly_trend,
        'monthly_comparison' => $monthly_comparison,
        'savings_trend' => $savings_trend,
        'total_categories' => $total_categories
    ]);

} catch (Exception $e) {
    error_log("Error in get_analytics.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?> 