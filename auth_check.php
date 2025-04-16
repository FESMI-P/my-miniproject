<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'User not logged in']);
    exit;
}

// Check if session has expired (optional, 30 minutes)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
    session_unset();
    session_destroy();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Session expired']);
    exit;
}

// Update last activity time
$_SESSION['last_activity'] = time();
?> 