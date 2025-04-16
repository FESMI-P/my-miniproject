<?php
require_once 'db_connect.php';
require_once 'auth_check.php';

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Get the last 50 messages
$stmt = $conn->prepare("SELECT message_text, received_at FROM telegram_messages ORDER BY received_at DESC LIMIT 50");
$stmt->execute();
$result = $stmt->get_result();

$messages = [];
while ($row = $result->fetch_assoc()) {
    $messages[] = [
        'text' => $row['message_text'],
        'timestamp' => $row['received_at']
    ];
}

$stmt->close();

// Return messages
header('Content-Type: application/json');
echo json_encode(['messages' => $messages]);
?> 