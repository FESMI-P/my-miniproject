<?php
require_once 'telegram_config.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Getting recent updates from Telegram...\n\n";

$ch = curl_init("https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/getUpdates");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false
]);

$result = curl_exec($ch);
$error = curl_error($ch);
curl_close($ch);

$updates = json_decode($result, true);

if ($updates && $updates['ok']) {
    if (empty($updates['result'])) {
        echo "No recent messages found.\n";
        echo "Please:\n";
        echo "1. Open Telegram\n";
        echo "2. Find @Fesmibot\n";
        echo "3. Send /start command\n";
        echo "4. Run this script again\n";
    } else {
        echo "Recent messages:\n\n";
        foreach ($updates['result'] as $update) {
            if (isset($update['message'])) {
                $message = $update['message'];
                echo "Chat ID: " . $message['chat']['id'] . "\n";
                echo "From: " . ($message['from']['username'] ?? 'Unknown') . "\n";
                echo "Message: " . ($message['text'] ?? 'No text') . "\n";
                echo "-------------------\n";
            }
        }
    }
} else {
    echo "Error getting updates:\n";
    echo $result . "\n";
    if ($error) {
        echo "CURL Error: " . $error . "\n";
    }
}
?> 