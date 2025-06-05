<?php
session_start();

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

$host = "localhost";
$dbUsername = "root";
$dbPassword = "";
$dbName = "user_auth";

$conn = new mysqli($host, $dbUsername, $dbPassword, $dbName);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get all posts
$posts = [];
$sql = "SELECT posts.*, users.username 
        FROM posts 
        JOIN users ON posts.user_id = users.id 
        ORDER BY posts.created_at DESC";
        
if ($stmt = $conn->prepare($sql)) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $posts[] = $row;
    }
    $stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gallery</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        :root {
            --primary: #4361ee;
            --light: #f8f9fa;
            --dark: #212529;
            --gray: #6c757d;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
            color: var(--dark);
            line-height: 1.6;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .gallery-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .gallery-header h1 {
            font-size: 32px;
        }
        
        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 25px;
        }
        
        .gallery-item {
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s, box-shadow 0.3s;
            height: 300px;
        }
        
        .gallery-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }
        
        .gallery-item img {
            width: 100%;
            height: 80%;
            object-fit: cover;
        }
        
        .gallery-item-info {
            padding: 15px;
            background-color: white;
        }
        
        .gallery-item-user {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
        }
        
        .user-avatar {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background-color: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 10px;
            font-weight: 600;
            font-size: 14px;
        }
        
        .user-name {
            font-weight: 600;
        }
        
        .post-date {
            font-size: 12px;
            color: var(--gray);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="gallery-header">
            <h1>Community Gallery</h1>
        </div>
        
        <div class="gallery-grid">
            <?php foreach ($posts as $post): ?>
                <div class="gallery-item">
                    <img src="<?php echo htmlspecialchars($post['image_path']); ?>" alt="Gallery image">
                    <div class="gallery-item-info">
                        <div class="gallery-item-user">
                            <div class="user-avatar">
                                <?php echo strtoupper(substr($post['username'], 0, 1)); ?>
                            </div>
                            <div class="user-name"><?php echo htmlspecialchars($post['username']); ?></div>
                        </div>
                        <div class="post-date"><?php echo date('M d, Y', strtotime($post['created_at'])); ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>