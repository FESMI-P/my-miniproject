<?php
// Telegram Bot Configuration
define('TELEGRAM_BOT_TOKEN', '7327040764:AAFpWWYBk-L0M5t6x94A5i-S7c71eyPZnQs');

// Your webhook URL (where Telegram will send updates)
define('TELEGRAM_WEBHOOK_URL', 'http://localhost/expense%20tracker1/backend/telegram_webhook.php');

// Function to send message to Telegram
function sendTelegramMessage($chat_id, $message) {
    $url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/sendMessage";
    $data = [
        'chat_id' => $chat_id,
        'text' => $message,
        'parse_mode' => 'HTML'
    ];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $data,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ]);
    
    $result = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        error_log("Telegram API Error: " . $error);
        return false;
    }
    
    return json_decode($result, true);
}

// Function to get updates from Telegram
function getTelegramUpdates($offset = 0) {
    $url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/getUpdates";
    $data = [
        'offset' => $offset,
        'timeout' => 30
    ];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $data,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ]);
    
    $result = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        error_log("Telegram API Error: " . $error);
        return false;
    }
    
    return json_decode($result, true);
}

// Function to set webhook
function setTelegramWebhook() {
    $url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/setWebhook";
    $data = [
        'url' => TELEGRAM_WEBHOOK_URL
    ];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $data,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ]);
    
    $result = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        error_log("Telegram API Error: " . $error);
        return false;
    }
    
    return json_decode($result, true);
}
?> 