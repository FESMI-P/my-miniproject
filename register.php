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
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT); // Hash the password

    // Insert user into the database
    $stmt = $conn->prepare("INSERT INTO users (username, email, password) VALUES (:username, :email, :password)");
    $stmt->bindParam(':username', $username);
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':password', $password);

    if ($stmt->execute()) {
        // Store user ID in the session
        $_SESSION['user_id'] = $conn->lastInsertId();
        $_SESSION['username'] = $username;
    
        // Debugging: Log session data
        error_log("Session Data After Registration: " . print_r($_SESSION, true));
    
        // Redirect to dashboard after successful registration
        header("Location: ../dashboard.html");
        exit();
    } else {
        echo "Registration failed. Please try again.";
    }
}
?>