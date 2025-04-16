<?php
require_once 'db_connect.php';

function parseTelegramTransaction($message) {
    $patterns = [
        // Pattern for formatted message
        '/Category:\s*([^\n]+)\s*Amount:\s*([0-9.]+)\s*Description:\s*([^\n]+)\s*Date:\s*([^\n]+)/i',
        
        // Pattern for bank transaction message
        '/(?:spent|paid|debited|transaction)\s*(?:INR|Rs\.?|₹)?\s*([0-9,.]+)\s*(?:at|to|for)?\s*([^0-9\n]+)/i',
        
        // Pattern for simple amount and description
        '/([0-9,.]+)\s*(?:for|on|at)?\s*([^0-9\n]+)/i'
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $message, $matches)) {
            if (count($matches) === 5) {  // Formatted message
                return [
                    'category' => trim($matches[1]),
                    'amount' => (float) str_replace([',', 'Rs', '₹'], '', $matches[2]),
                    'description' => trim($matches[3]),
                    'date' => date('Y-m-d', strtotime($matches[4])),
                    'source' => 'telegram'
                ];
            } else {  // Bank message or simple format
                $amount = (float) str_replace([',', 'Rs', '₹'], '', $matches[1]);
                $description = trim($matches[2]);
                
                // Try to guess category based on keywords
                $category = guessCategoryFromDescription($description);
                
                return [
                    'category' => $category,
                    'amount' => $amount,
                    'description' => $description,
                    'date' => date('Y-m-d'),
                    'source' => 'telegram'
                ];
            }
        }
    }
    
    return null;
}

function guessCategoryFromDescription($description) {
    $categories = [
        'Food' => ['restaurant', 'food', 'cafe', 'dining', 'lunch', 'dinner', 'breakfast'],
        'Shopping' => ['mall', 'store', 'shop', 'market', 'retail'],
        'Transportation' => ['uber', 'ola', 'taxi', 'bus', 'metro', 'fuel', 'petrol'],
        'Entertainment' => ['movie', 'theatre', 'cinema', 'game', 'netflix'],
        'Utilities' => ['bill', 'electricity', 'water', 'gas', 'internet', 'phone'],
        'Healthcare' => ['hospital', 'medical', 'pharmacy', 'doctor'],
        'Education' => ['school', 'college', 'course', 'books', 'tuition']
    ];
    
    $description = strtolower($description);
    foreach ($categories as $category => $keywords) {
        foreach ($keywords as $keyword) {
            if (strpos($description, $keyword) !== false) {
                return $category;
            }
        }
    }
    
    return 'Others';
}

function saveExpenseFromTelegram($expense_data, $chat_id) {
    global $conn;
    
    try {
        error_log("Attempting to save Telegram expense: " . json_encode($expense_data) . " for chat_id: " . $chat_id);
        
        // Begin transaction
        $conn->beginTransaction();
        
        // Insert into expenses table using the correct user_id and source
        $sql = "INSERT INTO expenses (user_id, category, amount, description, date, source) 
                VALUES (1, :category, :amount, :description, :date, 'telegram')";
        
        error_log("Preparing SQL: " . $sql);
        
        $stmt = $conn->prepare($sql);
        
        $params = [
            ':category' => $expense_data['category'],
            ':amount' => $expense_data['amount'],
            ':description' => $expense_data['description'],
            ':date' => $expense_data['date']
        ];
        
        error_log("Executing with parameters: " . json_encode($params));
        
        $result = $stmt->execute($params);
        error_log("Execute result: " . ($result ? "success" : "failed"));
        
        if ($result) {
            $rowCount = $stmt->rowCount();
            error_log("Rows affected: " . $rowCount);
            
            if ($rowCount > 0) {
                // Commit transaction
                $conn->commit();
                $lastId = $conn->lastInsertId();
                error_log("Successfully saved Telegram expense with ID: " . $lastId);
                return true;
            } else {
                error_log("No rows were inserted");
                throw new Exception("No rows were inserted");
            }
        } else {
            error_log("SQL Error: " . json_encode($stmt->errorInfo()));
            throw new Exception("Failed to execute SQL statement");
        }
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        error_log("Error saving expense from Telegram: " . $e->getMessage());
        return false;
    }
}
?> 