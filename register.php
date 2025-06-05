<?php
// Database configuration
$host = "localhost";
$dbUsername = "root";
$dbPassword = "";
$dbName = "user_auth";

// Create connection
$conn = new mysqli($host, $dbUsername, $dbPassword, $dbName);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Initialize variables
$username = $email = $password = $confirm_password = $location = "";
$errors = [
    'username' => '',
    'email' => '',
    'password' => '',
    'confirm_password' => '',
    'location' => ''
];

// Process form data
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate username
    if (empty(trim($_POST["username"]))) {
        $errors['username'] = "Please enter a username.";
    } else {
        // ... (rest of your validation logic)
    }

    // ... (rest of your PHP validation and database insertion code)

    // If no errors, redirect to login
    if (empty(array_filter($errors))) {
        // Prepare and execute SQL (from your original code)
        header("Location: login.php");
        exit;
    }
}
?>
