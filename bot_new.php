<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'telegram_config.php';
require_once 'db_config.php';

// Define expense categories and their keywords
$categories = [
    'Food & Dining' => ['food', 'lunch', 'dinner', 'breakfast', 'restaurant', 'cafe', 'coffee', 'tea', 'snack', 'meal'],
    'Transportation' => ['bus', 'train', 'metro', 'taxi', 'uber', 'ola', 'fuel', 'petrol', 'diesel', 'auto'],
    'Shopping' => ['clothes', 'shoes', 'grocery', 'market', 'store', 'shop', 'mall', 'purchase'],
    'Entertainment' => ['movie', 'cinema', 'theater', 'concert', 'game', 'sports', 'gym', 'fitness'],
    'Bills & Utilities' => ['electricity', 'water', 'gas', 'internet', 'phone', 'rent', 'bill'],
    'Healthcare' => ['medicine', 'doctor', 'hospital', 'pharmacy', 'medical', 'health'],
    'Education' => ['books', 'course', 'education', 'school', 'college', 'university', 'study'],
    'Other' => [] // Default category
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

// Function to format expenses for display
function formatExpenses($expenses) {
    if (!$expenses || empty($expenses)) {
        return "No expenses found.";
    }

    $message = "📊 Recent Expenses:\n\n";
    foreach ($expenses as $expense) {
        $date = date('Y-m-d H:i', strtotime($expense['created_at']));
        $message .= "₹{$expense['amount']} - {$expense['description']}\n";
        $message .= "Category: {$expense['category']}\n";
        $message .= "Date: $date\n\n";
    }
    return $message;
}

// Main bot loop
$last_update_id = 0;
while (true) {
    try {
        echo "Checking for messages...\n";
        
        $updates = getTelegramUpdates($last_update_id);
        
        if ($updates && isset($updates['result']) && !empty($updates['result'])) {
            foreach ($updates['result'] as $update) {
                $last_update_id = $update['update_id'] + 1;
                
                if (isset($update['message'])) {
                    $message = $update['message'];
                    $chat_id = $message['chat']['id'];
                    $text = isset($message['text']) ? $message['text'] : '';
                    
                    echo "Received message: $text from chat_id: $chat_id\n";
                    
                    // Handle commands
                    if (strpos($text, '/') === 0) {
                        switch ($text) {
                            case '/start':
                                $welcome_message = "👋 Welcome to Expense Tracker Bot!\n\n";
                                $welcome_message .= "Here's how to use me:\n";
                                $welcome_message .= "1. Send expenses in format: Spent [amount] for [description]\n";
                                $welcome_message .= "2. Use /help to see all commands\n";
                                $welcome_message .= "3. Use /expenses to view your recent expenses\n\n";
                                $welcome_message .= "Example: Spent 500 for lunch";
                                sendTelegramMessage($chat_id, $welcome_message);
                                break;
                                
                            case '/help':
                                $help_message = "📚 Available Commands:\n\n";
                                $help_message .= "/start - Start the bot\n";
                                $help_message .= "/help - Show this help message\n";
                                $help_message .= "/expenses - View your recent expenses\n\n";
                                $help_message .= "To add an expense, send:\n";
                                $help_message .= "Spent [amount] for [description]\n\n";
                                $help_message .= "Example: Spent 500 for lunch";
                                sendTelegramMessage($chat_id, $help_message);
                                break;
                                
                            case '/expenses':
                                $expenses = getRecentExpenses($chat_id);
                                $message = formatExpenses($expenses);
                                sendTelegramMessage($chat_id, $message);
                                break;
                        }
                    }
                    // Handle expense messages
                    else if (preg_match('/^Spent\s+(\d+)\s+for\s+(.+)$/i', $text, $matches)) {
                        $amount = $matches[1];
                        $description = $matches[2];
                        $category = detectCategory($description, $categories);
                        
                        echo "Processed expense message\n";
                        
                        // Save to database
                        if (saveExpense($chat_id, $amount, $description, $category)) {
                            $response = "✅ Expense recorded!\n\n";
                            $response .= "Amount: ₹$amount\n";
                            $response .= "Description: $description\n";
                            $response .= "Category: $category";
                        } else {
                            $response = "❌ Sorry, there was an error saving your expense. Please try again later.";
                        }
                        
                        sendTelegramMessage($chat_id, $response);
                    }
                }
            }
        }
        
        // Sleep for 1 second before next check
        sleep(1);
        
    } catch (Exception $e) {
        error_log("Bot error: " . $e->getMessage());
        sleep(5); // Wait longer on error
    }
} 