<?php
require_once 'db_connect.php';
require_once 'telegram_config.php';

// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Define expense categories and their keywords
$categories = [
    'Food' => ['restaurant', 'food', 'cafe', 'dining', 'lunch', 'dinner', 'breakfast'],
    'Shopping' => ['mall', 'store', 'shop', 'market', 'retail'],
    'Transportation' => ['uber', 'ola', 'taxi', 'bus', 'metro', 'fuel', 'petrol'],
    'Entertainment' => ['movie', 'theatre', 'cinema', 'game', 'netflix'],
    'Utilities' => ['bill', 'electricity', 'water', 'gas', 'internet', 'phone'],
    'Healthcare' => ['hospital', 'medical', 'pharmacy', 'doctor'],
    'Education' => ['school', 'college', 'course', 'books', 'tuition']
];

// Function to detect category from description
function detectCategory($description, $categories) {
    $description = strtolower($description);
    foreach ($categories as $category => $keywords) {
        foreach ($keywords as $keyword) {
            if (strpos($description, $keyword) !== false) {
                return $category;
            }
        }
    }
    return 'Other';
}

// Log incoming updates
$update = json_decode(file_get_contents('php://input'), true);
file_put_contents('telegram_debug.log', date('Y-m-d H:i:s') . " - Received update: " . json_encode($update) . "\n", FILE_APPEND);

// Check if it's a message
if (isset($update['message'])) {
    $message = $update['message'];
    $chat_id = $message['chat']['id'];
    $text = $message['text'] ?? '';
    
    // Log the message
    file_put_contents('telegram_debug.log', date('Y-m-d H:i:s') . " - Processing message: " . $text . " from chat_id: " . $chat_id . "\n", FILE_APPEND);

    // Handle commands
    if (strpos($text, '/') === 0) {
        $command = strtolower(explode(' ', $text)[0]);
        
        switch ($command) {
            case '/start':
                $welcome = "👋 Welcome to your Personal Expense Tracker Bot!\n\n";
                $welcome .= "I'm here to help you track your expenses. Here's what you can do:\n\n";
                $welcome .= "1️⃣ Add expenses in these formats:\n";
                $welcome .= "• Formatted: \nCategory: Food\nAmount: 500\nDescription: Lunch\nDate: 2024-03-23\n\n";
                $welcome .= "• Simple: Spent 500 for lunch\n\n";
                $welcome .= "• Bank style: Debited INR 1000 for Shopping\n\n";
                $welcome .= "2️⃣ Use these commands:\n";
                $welcome .= "/help - Show this help message\n";
                $welcome .= "/expenses - View recent expenses\n\n";
                $welcome .= "Your Chat ID is: " . $chat_id . "\n";
                $welcome .= "Save this ID for testing purposes!";
                
                sendTelegramMessage($chat_id, $welcome);
                break;
                
            case '/help':
                $help = "🤖 Available Commands:\n\n";
                $help .= "/start - Start the bot\n";
                $help .= "/help - Show this help message\n";
                $help .= "/expenses - View your recent expenses\n\n";
                $help .= "You can also send expenses directly:\n";
                $help .= "Example: Spent 500 for lunch";
                
                sendTelegramMessage($chat_id, $help);
                break;
                
            case '/expenses':
                // For now, just acknowledge
                sendTelegramMessage($chat_id, "This feature will show your recent expenses. Coming soon!");
                break;
                
            default:
                sendTelegramMessage($chat_id, "❓ Unknown command. Use /help to see available commands.");
        }
    } else {
        // Handle regular messages (potential expenses)
        if (preg_match('/^Spent\s+(\d+)\s+for\s+(.+)$/i', $text, $matches)) {
            $amount = $matches[1];
            $description = $matches[2];
            $category = detectCategory($description, $categories);
            
            error_log("Processing Telegram expense: amount=$amount, description=$description, category=$category");
            
            try {
                // Save to database with user_id = 1 and source = telegram
                $sql = "INSERT INTO expenses (user_id, category, amount, description, date, source) 
                        VALUES (1, :category, :amount, :description, :date, 'telegram')";
                
                $stmt = $conn->prepare($sql);
                $params = [
                    ':category' => $category,
                    ':amount' => $amount,
                    ':description' => $description,
                    ':date' => date('Y-m-d')
                ];
                
                error_log("Executing SQL: " . $sql);
                error_log("With parameters: " . json_encode($params));
                
                if ($stmt->execute($params)) {
                    $response = "✅ Expense recorded!\n\n";
                    $response .= "Amount: ₹$amount\n";
                    $response .= "Description: $description\n";
                    $response .= "Category: $category";
                    error_log("Successfully saved expense with user_id 1 and source telegram");
                } else {
                    $error = $stmt->errorInfo();
                    error_log("Error saving expense: " . json_encode($error));
                    $response = "❌ Sorry, there was an error saving your expense. Please try again later.";
                }
            } catch (Exception $e) {
                error_log("Exception while saving expense: " . $e->getMessage());
                $response = "❌ Sorry, there was an error saving your expense. Please try again later.";
            }
            
            sendTelegramMessage($chat_id, $response);
        } else {
            sendTelegramMessage($chat_id, "✅ Got your message: " . $text . "\n\nI'll process this as an expense soon!");
        }
    }
}

// Return success response
http_response_code(200);
echo json_encode(['status' => 'success']);
?> 