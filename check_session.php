<?php
header('Content-Type: application/json');
session_start();

if (isset($_SESSION['user_id'])) {
    echo json_encode([
        'loggedIn' => true,
        'user_id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'] ?? null
    ]);
} else {
    http_response_code(401);
    echo json_encode([
        'loggedIn' => false,
        'error' => 'Not logged in'
    ]);
}
?> 