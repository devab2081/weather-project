<?php
session_start();

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

if (!isset($_GET['post_id'])) {
    exit;
}

$post_id = intval($_GET['post_id']);

$host = "localhost";
$dbUsername = "root";
$dbPassword = "";
$dbName = "user_auth";

$conn = new mysqli($host, $dbUsername, $dbPassword, $dbName);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get comments for this post
$sql = "SELECT comments.*, users.username 
        FROM comments 
        JOIN users ON comments.user_id = users.id 
        WHERE post_id = ? 
        ORDER BY comments.created_at DESC";
        
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $post_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        echo '<div class="comment">';
        echo '<div class="comment-user">' . strtoupper(substr($row['username'], 0, 1)) . '</div>';
        echo '<div class="comment-content">';
        echo '<div class="comment-author">' . htmlspecialchars($row['username']) . '</div>';
        echo '<div class="comment-text">' . htmlspecialchars($row['comment']) . '</div>';
        echo '</div></div>';
    }
    
    $stmt->close();
}

$conn->close();
?>