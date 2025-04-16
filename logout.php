<?php
session_start(); // Start the session

// Destroy the session
session_destroy();

// Clear local storage (optional, for frontend)
echo '<script>localStorage.removeItem("username");</script>';

// Redirect to the login page
header("Location: login.html");
exit();
?>