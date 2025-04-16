<?php
require_once 'db_connect.php';
require_once 'telegram_config.php';

// Function to execute SQL file
function executeSQLFile($conn, $filename) {
    $sql = file_get_contents($filename);
    if ($sql === false) {
        throw new Exception("Could not read SQL file: " . $filename);
    }
    
    // Split SQL file into individual statements
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    foreach ($statements as $statement) {
        if (!empty($statement)) {
            try {
                if (!$conn->query($statement)) {
                    // Only throw exception if it's not a "duplicate key" error
                    if ($conn->errno != 1061) {
                        throw new Exception("Error executing SQL: " . $conn->error);
                    } else {
                        echo "Note: Index already exists, skipping...\n";
                    }
                }
            } catch (mysqli_sql_exception $e) {
                // Skip duplicate key errors
                if ($e->getCode() != 1061) {
                    throw $e;
                }
                echo "Note: Index already exists, skipping...\n";
            }
        }
    }
}

try {
    // Create telegram_messages table
    echo "Setting up telegram_messages table...\n";
    executeSQLFile($conn, 'create_telegram_table.sql');
    
    // Create telegram_users table
    echo "Setting up telegram_users table...\n";
    executeSQLFile($conn, 'create_telegram_users_table.sql');
    
    // Set up webhook
    echo "Setting up Telegram webhook...\n";
    $result = setTelegramWebhook();
    
    if (isset($result['ok']) && $result['ok']) {
        echo "✅ Webhook set up successfully!\n";
        echo "URL: " . TELEGRAM_WEBHOOK_URL . "\n";
    } else {
        throw new Exception("Failed to set webhook: " . json_encode($result));
    }
    
    echo "\n✅ Database tables created successfully!\n";
    echo "\nSetup complete! Your Telegram bot is ready to use.\n";
    echo "\nTo get started:\n";
    echo "1. Open Telegram and search for your bot\n";
    echo "2. Start a chat with the bot using /start\n";
    echo "3. Link your account using /link [your-email]\n";
    echo "4. Start tracking expenses!\n";
    
} catch (Exception $e) {
    echo "❌ Error during setup: " . $e->getMessage() . "\n";
    exit(1);
}
?> 