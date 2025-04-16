<?php
session_start();
require_once 'db_connect.php';

// Function to determine category based on description
function determineCategory($description) {
    $categories = array(
        'Food' => array('swiggy', 'zomato', 'food', 'restaurant', 'cafe'),
        'Shopping' => array('amazon', 'flipkart', 'myntra', 'ajio'),
        'Entertainment' => array('netflix', 'prime', 'spotify', 'hotstar'),
        'Transportation' => array('uber', 'ola', 'rapido'),
        'Utilities' => array('electricity', 'water', 'gas', 'internet', 'phone')
    );

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

// Get example online transactions
$example_transactions = array(
    array(
        'amount' => 599.00,
        'description' => 'SWIGGY INDIA',
        'date' => '2024-03-25',
        'sender' => 'HDFC-Bank'
    ),
    array(
        'amount' => 1299.00,
        'description' => 'AMAZON INDIA',
        'date' => '2024-03-25',
        'sender' => 'ICICI-Bank'
    ),
    array(
        'amount' => 199.00,
        'description' => 'NETFLIX INDIA',
        'date' => '2024-03-25',
        'sender' => 'SBI-Bank'
    )
);

// Process transactions
$parsed_transactions = array();
foreach ($example_transactions as $transaction) {
    $category = determineCategory($transaction['description']);
    
    $parsed = array(
        'amount' => $transaction['amount'],
        'description' => $transaction['description'],
        'date' => $transaction['date'],
        'category' => $category,
        'is_online' => 1,
        'sender' => $transaction['sender']
    );

    $parsed_transactions[] = array(
        'message' => "Transaction of Rs.{$transaction['amount']} at {$transaction['description']}",
        'parsed' => array(
            'success' => true,
            'transaction' => $parsed
        )
    );
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode(array(
    'success' => true,
    'examples' => $parsed_transactions
));
?>