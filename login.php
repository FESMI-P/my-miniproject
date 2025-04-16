<?php
session_start(); // Start the session

// Debugging: Check if session is active
echo "Session ID: " . session_id() . "<br>";

// Connect to the database
$host = 'localhost';
$dbname = 'expense_tracker1';
$username = 'root';
$password = '';

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Fetch user from the database
    $stmt = $conn->prepare("SELECT * FROM users WHERE username = :username");
    $stmt->bindParam(':username', $username);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        // Store user ID in the session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
    
        // Debugging: Log session data
        error_log("Session Data After Login: " . print_r($_SESSION, true));
    
        // Redirect to dashboard after successful login
        header("Location: ../dashboard.html");
        exit();
    } else {
        echo "Invalid username or password.";
    }
}
?>