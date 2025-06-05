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

// Process form data when submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate username
    if (empty(trim($_POST["username"]))) {
        $errors['username'] = "Please enter a username.";
    } else {
        $sql = "SELECT id FROM users WHERE username = ?";
        
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $param_username);
            $param_username = trim($_POST["username"]);
            
            if ($stmt->execute()) {
                $stmt->store_result();
                
                if ($stmt->num_rows == 1) {
                    $errors['username'] = "This username is already taken.";
                } else {
                    $username = trim($_POST["username"]);
                }
            }
            $stmt->close();
        }
    }
    
    // Validate email
    if (empty(trim($_POST["email"]))) {
        $errors['email'] = "Please enter an email.";
    } else {
        $sql = "SELECT id FROM users WHERE email = ?";
        
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $param_email);
            $param_email = trim($_POST["email"]);
            
            if ($stmt->execute()) {
                $stmt->store_result();
                
                if ($stmt->num_rows == 1) {
                    $errors['email'] = "This email is already registered.";
                } else {
                    $email = trim($_POST["email"]);
                }
            }
            $stmt->close();
        }
    }
    
    // Validate password
    if (empty(trim($_POST["password"]))) {
        $errors['password'] = "Please enter a password.";     
    } elseif (strlen(trim($_POST["password"])) < 6) {
        $errors['password'] = "Password must have at least 6 characters.";
    } else {
        $password = trim($_POST["password"]);
    }
    
    // Validate confirm password
    if (empty(trim($_POST["confirm_password"]))) {
        $errors['confirm_password'] = "Please confirm password.";     
    } else {
        $confirm_password = trim($_POST["confirm_password"]);
        if (empty($errors['password']) && ($password != $confirm_password)) {
            $errors['confirm_password'] = "Password did not match.";
        }
    }
    
    // Validate location
    if (empty(trim($_POST["location"]))) {
        $errors['location'] = "Please enter your location.";     
    } else {
        $location = trim($_POST["location"]);
    }
    
    // Check input errors before inserting in database
    if (empty(array_filter($errors))) {
        // Prepare insert statement
        $sql = "INSERT INTO users (username, email, password, location) VALUES (?, ?, ?, ?)";
         
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("ssss", $param_username, $param_email, $param_password, $param_location);
            
            $param_username = $username;
            $param_email = $email;
            $param_password = password_hash($password, PASSWORD_DEFAULT);
            $param_location = $location;
            
            if ($stmt->execute()) {
                header("Location: login.php");
                exit;
            } else {
                echo "Something went wrong. Please try again later.";
            }
            $stmt->close();
        }
    }
    $conn->close();
}

// Display the form
include 'register.html';
?>
