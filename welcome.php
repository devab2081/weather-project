<?php
// Initialize the session
session_start();

// Check if the user is logged in, if not then redirect to login page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

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

// Handle file upload
$uploadError = '';
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["post_image"])) {
    $targetDir = "uploads/";
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0777, true);
    }
    
    $targetFile = $targetDir . basename($_FILES["post_image"]["name"]);
    $imageFileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
    $check = getimagesize($_FILES["post_image"]["tmp_name"]);
    
    if ($check === false) {
        $uploadError = "File is not an image.";
    } elseif ($_FILES["post_image"]["size"] > 5000000) {
        $uploadError = "Sorry, your file is too large (max 5MB).";
    } elseif (!in_array($imageFileType, ["jpg", "png", "jpeg", "gif"])) {
        $uploadError = "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
    } elseif (move_uploaded_file($_FILES["post_image"]["tmp_name"], $targetFile)) {
        // Save to database
        $sql = "INSERT INTO posts (user_id, image_path) VALUES (?, ?)";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("is", $_SESSION["id"], $targetFile);
            $stmt->execute();
            $stmt->close();
        }
    } else {
        $uploadError = "Sorry, there was an error uploading your file.";
    }
}

// Handle post deletion
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["delete_post"])) {
    $postId = $_POST["post_id"];
    $userId = $_SESSION["id"];
    
    // First get the image path to delete the file
    $sql = "SELECT image_path FROM posts WHERE id = ? AND user_id = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("ii", $postId, $userId);
        $stmt->execute();
        $stmt->bind_result($imagePath);
        $stmt->fetch();
        $stmt->close();
        
        // Delete the file
        if (file_exists($imagePath)) {
            unlink($imagePath);
        }
        
        // Then delete the post from database
        $sql = "DELETE FROM posts WHERE id = ? AND user_id = ?";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("ii", $postId, $userId);
            $stmt->execute();
            $stmt->close();
            
            // Refresh the page to show updated posts
            header("Location: ".$_SERVER['PHP_SELF']);
            exit;
        }
    }
}

// Handle like action
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["like_post"])) {
    $postId = $_POST["post_id"];
    $userId = $_SESSION["id"];
    
    // Check if user already liked this post
    $sql = "SELECT id FROM likes WHERE user_id = ? AND post_id = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("ii", $userId, $postId);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            // User already liked - remove like
            $sql = "DELETE FROM likes WHERE user_id = ? AND post_id = ?";
            if ($stmt2 = $conn->prepare($sql)) {
                $stmt2->bind_param("ii", $userId, $postId);
                $stmt2->execute();
                $stmt2->close();
            }
        } else {
            // Add new like
            $sql = "INSERT INTO likes (user_id, post_id) VALUES (?, ?)";
            if ($stmt2 = $conn->prepare($sql)) {
                $stmt2->bind_param("ii", $userId, $postId);
                $stmt2->execute();
                $stmt2->close();
            }
        }
        $stmt->close();
    }
}

// Handle comment submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["comment_text"]) && !empty(trim($_POST["comment_text"]))) {
    $postId = $_POST["post_id"];
    $userId = $_SESSION["id"];
    $commentText = trim($_POST["comment_text"]);
    
    $sql = "INSERT INTO comments (user_id, post_id, comment) VALUES (?, ?, ?)";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("iis", $userId, $postId, $commentText);
        $stmt->execute();
        $stmt->close();
    }
}

// Prepare a select statement to get user details
$sql = "SELECT email, location, profile_picture FROM users WHERE id = ?";
$user_email = $user_location = $profile_picture = "";

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $param_id);
    $param_id = $_SESSION["id"];
    
    if ($stmt->execute()) {
        $stmt->store_result();
        if ($stmt->num_rows == 1) {
            $stmt->bind_result($user_email, $user_location, $profile_picture);
            $stmt->fetch();
        }
    }
    $stmt->close();
}

// Get user posts
$posts = [];
$sql = "SELECT posts.id, posts.image_path, posts.created_at, 
        COUNT(DISTINCT likes.id) AS like_count,
        COUNT(DISTINCT comments.id) AS comment_count
        FROM posts
        LEFT JOIN likes ON posts.id = likes.post_id
        LEFT JOIN comments ON posts.id = comments.post_id
        WHERE posts.user_id = ?
        GROUP BY posts.id
        ORDER BY posts.created_at DESC";

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $_SESSION["id"]);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $posts[] = $row;
    }
    $stmt->close();
}

// Get weather data if location exists
$current_weather = null;
$forecast = null;
if (!empty($user_location)) {
    $apiKey = '7ffbbf71823864d4c15aa53a708b9de8';
    $encodedLocation = urlencode($user_location);
    
    // Current weather API
    $currentApiUrl = "https://api.openweathermap.org/data/2.5/weather?q={$encodedLocation}&appid={$apiKey}&units=metric";
    $currentResponse = @file_get_contents($currentApiUrl);
    if ($currentResponse) {
        $current_weather = json_decode($currentResponse, true);
    }
    
    // Forecast API
    $forecastApiUrl = "https://api.openweathermap.org/data/2.5/forecast?q={$encodedLocation}&appid={$apiKey}&units=metric";
    $forecastResponse = @file_get_contents($forecastApiUrl);
    if ($forecastResponse) {
        $forecastData = json_decode($forecastResponse, true);
        if (isset($forecastData['list'])) {
            $forecast = array_slice($forecastData['list'], 0, 2);
        }
    }
}

// Get news data from GNews API
$news = [];
$newsApiKey = '01ff887834953f601e66708b3a598dab';
$newsApiUrl = "https://gnews.io/api/v4/top-headlines?token={$newsApiKey}&lang=en";
$newsResponse = @file_get_contents($newsApiUrl);
if ($newsResponse) {
    $newsData = json_decode($newsResponse, true);
    if (isset($newsData['articles'])) {
        $news = array_slice($newsData['articles'], 0, 5); // Get top 5 news articles
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        :root {
            --primary: #4361ee;
            --primary-dark: #3a56d4;
            --secondary: #3f37c9;
            --light: #f8f9fa;
            --dark: #212529;
            --danger: #dc3545;
            --success: #28a745;
            --warning: #ffc107;
            --info: #17a2b8;
            --gray: #6c757d;
            --light-gray: #e9ecef;
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
        
        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }
        
        .sidebar {
            width: 280px;
            background-color: white;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.05);
            padding: 20px 0;
            position: fixed;
            height: 100%;
            overflow-y: auto;
        }
        
        .sidebar-header {
            padding: 0 20px 20px;
            border-bottom: 1px solid var(--light-gray);
            margin-bottom: 20px;
        }
        
        .sidebar-header h2 {
            color: var(--primary);
            font-size: 24px;
            font-weight: 600;
        }
        
        .sidebar-menu {
            padding: 0 20px;
        }
        
        .menu-item {
            margin-bottom: 5px;
        }
        
        .menu-item a {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            color: var(--dark);
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s;
        }
        
        .menu-item a:hover, .menu-item a.active {
            background-color: rgba(67, 97, 238, 0.1);
            color: var(--primary);
        }
        
        .menu-item a i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 30px;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .header h1 {
            font-size: 28px;
            font-weight: 600;
            color: var(--dark);
        }
        
        .user-profile {
            display: flex;
            align-items: center;
        }
        
        .user-profile img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 10px;
            object-fit: cover;
        }
        
        .user-profile .user-name {
            font-weight: 500;
        }
        
        .grid-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .card {
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            padding: 20px;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--light-gray);
        }
        
        .card-header h3 {
            font-size: 18px;
            font-weight: 600;
            color: var(--dark);
        }
        
        .card-header .icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: rgba(67, 97, 238, 0.1);
            color: var(--primary);
        }
        
        .user-info p {
            margin-bottom: 8px;
        }
        
        .user-info strong {
            font-weight: 500;
            color: var(--dark);
        }
        
        .weather-card .weather-icon {
            text-align: center;
            margin: 15px 0;
        }
        
        .weather-card .weather-icon img {
            width: 80px;
            height: 80px;
        }
        
        .weather-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .weather-detail {
            display: flex;
            align-items: center;
        }
        
        .weather-detail i {
            margin-right: 10px;
            color: var(--primary);
        }
        
        .forecast-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 10px;
            margin-top: 15px;
        }
        
        .forecast-item {
            text-align: center;
            padding: 10px;
            background-color: var(--light-gray);
            border-radius: 8px;
        }
        
        .forecast-item img {
            width: 40px;
            height: 40px;
            margin: 5px auto;
        }
        
        .news-item {
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--light-gray);
        }
        
        .news-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .news-title {
            font-weight: 600;
            margin-bottom: 5px;
            color: var(--dark);
        }
        
        .news-description {
            font-size: 14px;
            color: var(--gray);
            margin-bottom: 5px;
        }
        
        .news-meta {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            color: var(--gray);
        }
        
        .posts-container {
            margin-top: 30px;
        }
        
        .post {
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .post-header {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .post-user {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 10px;
            font-weight: 600;
        }
        
        .post-info h4 {
            font-weight: 600;
            margin-bottom: 2px;
        }
        
        .post-date {
            font-size: 12px;
            color: var(--gray);
        }
        
        .post-image {
            width: 100%;
            border-radius: 8px;
            margin-bottom: 15px;
            max-height: 400px;
            object-fit: cover;
        }
        
        .post-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .post-stats {
            display: flex;
            gap: 15px;
        }
        
        .post-stat {
            display: flex;
            align-items: center;
            gap: 5px;
            color: var(--gray);
            font-size: 14px;
        }
        
        .post-buttons {
            display: flex;
            gap: 10px;
        }
        
        .btn {
            padding: 8px 15px;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            border: none;
            font-family: 'Poppins', sans-serif;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 14px;
        }
        
        .btn-primary {
            background-color: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
        }
        
        .btn-outline {
            background-color: transparent;
            border: 1px solid var(--primary);
            color: var(--primary);
        }
        
        .btn-outline:hover {
            background-color: rgba(67, 97, 238, 0.1);
        }
        
        .btn-danger {
            background-color: var(--danger);
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #c82333;
        }
        
        .post-form {
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            padding: 20px;
            margin-bottom: 30px;
            display: none;
        }
        
        .post-form.active {
            display: block;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
        }
        
        .error {
            color: var(--danger);
            margin-bottom: 15px;
        }
        
        .comments-section {
            margin-top: 15px;
            border-top: 1px solid var(--light-gray);
            padding-top: 15px;
        }
        
        .comment-form {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .comment-form input {
            flex: 1;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 20px;
        }
        
        .comment {
            display: flex;
            margin-bottom: 10px;
            padding: 8px;
            background-color: var(--light-gray);
            border-radius: 8px;
        }
        
        .comment-user {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background-color: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 10px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .comment-content {
            flex: 1;
        }
        
        .comment-author {
            font-weight: 600;
            margin-bottom: 2px;
        }
        
        .comment-text {
            font-size: 14px;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                position: relative;
                height: auto;
                margin-bottom: 20px;
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .grid-container {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h2>Dashboard</h2>
            </div>
            
            <div class="sidebar-menu">
                <div class="menu-item">
                    <a href="welcome.php" class="active">
                        <i class="fas fa-home"></i>
                        <span>Home</span>
                    </a>
                </div>
                <div class="menu-item">
                    <a href="profile.php">
                        <i class="fas fa-user"></i>
                        <span>Profile</span>
                    </a>
                </div>
                <div class="menu-item">
                    <a href="gallery.php">
                        <i class="fas fa-image"></i>
                        <span>Gallery</span>
                    </a>
                </div>
                <div class="menu-item">
                    <a href="#">
                        <i class="fas fa-cog"></i>
                        <span>Settings</span>
                    </a>
                </div>
                <div class="menu-item">
                    <a href="logout.php">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <div class="header">
                <h1>Welcome, <?php echo htmlspecialchars($_SESSION["username"]); ?>!</h1>
                <div class="user-profile">
                    <div class="user-avatar">
                        <?php 
                            if (!empty($profile_picture)) {
                                echo '<img src="' . htmlspecialchars($profile_picture) . '" alt="Profile Picture">';
                            } else {
                                $initial = strtoupper(substr($_SESSION["username"], 0, 1));
                                echo '<div style="width:40px;height:40px;border-radius:50%;background-color:#4361ee;color:white;display:flex;align-items:center;justify-content:center;font-weight:600;">'.$initial.'</div>';
                            }
                        ?>
                    </div>
                    <div class="user-name"><?php echo htmlspecialchars($_SESSION["username"]); ?></div>
                </div>
            </div>
            
            <button class="btn btn-primary" id="postBtn" style="margin-bottom: 20px;">
                <i class="fas fa-plus"></i> Create New Post
            </button>
            
            <div class="post-form" id="postForm">
                <h3>Create New Post</h3>
                <?php if (!empty($uploadError)): ?>
                    <div class="error"><?php echo $uploadError; ?></div>
                <?php endif; ?>
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="post_image">Upload Image</label>
                        <input type="file" name="post_image" id="post_image" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Upload Post</button>
                </form>
            </div>
            
            <div class="grid-container">
                <!-- User Info Card -->
                <div class="card">
                    <div class="card-header">
                        <h3>Account Information</h3>
                        <div class="icon">
                            <i class="fas fa-user"></i>
                        </div>
                    </div>
                    <div class="user-info">
                        <p><strong>Username:</strong> <?php echo htmlspecialchars($_SESSION["username"]); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($user_email); ?></p>
                        <?php if (!empty($user_location)): ?>
                            <p><strong>Location:</strong> <?php echo htmlspecialchars($user_location); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Weather Card -->
                <?php if (!empty($user_location) && $current_weather && !isset($current_weather['error'])): ?>
                <div class="card weather-card">
                    <div class="card-header">
                        <h3>Weather in <?php echo htmlspecialchars($user_location); ?></h3>
                        <div class="icon">
                            <i class="fas fa-cloud-sun"></i>
                        </div>
                    </div>
                    <div class="weather-icon">
                        <?php if (isset($current_weather['weather'][0]['icon'])): ?>
                            <img src="https://openweathermap.org/img/wn/<?php echo $current_weather['weather'][0]['icon']; ?>@2x.png" 
                                 alt="<?php echo isset($current_weather['weather'][0]['description']) ? $current_weather['weather'][0]['description'] : ''; ?>">
                        <?php endif; ?>
                    </div>
                    <div class="weather-details">
                        <div class="weather-detail">
                            <i class="fas fa-temperature-high"></i>
                            <span><?php echo isset($current_weather['main']['temp']) ? round($current_weather['main']['temp']) . '°C' : 'N/A'; ?></span>
                        </div>
                        <div class="weather-detail">
                            <i class="fas fa-water"></i>
                            <span><?php echo isset($current_weather['main']['humidity']) ? $current_weather['main']['humidity'] . '%' : 'N/A'; ?></span>
                        </div>
                        <div class="weather-detail">
                            <i class="fas fa-wind"></i>
                            <span><?php echo isset($current_weather['wind']['speed']) ? $current_weather['wind']['speed'] . ' m/s' : 'N/A'; ?></span>
                        </div>
                        <div class="weather-detail">
                            <i class="fas fa-compass"></i>
                            <span><?php echo isset($current_weather['weather'][0]['description']) ? ucfirst($current_weather['weather'][0]['description']) : 'N/A'; ?></span>
                        </div>
                    </div>
                    
                    <?php if ($forecast): ?>
                        <h4 style="margin-top: 20px; margin-bottom: 10px;">6-Hour Forecast</h4>
                        <div class="forecast-container">
                            <?php foreach ($forecast as $item): ?>
                                <div class="forecast-item">
                                    <div><?php echo date('H:i', $item['dt']); ?></div>
                                    <?php if (isset($item['weather'][0]['icon'])): ?>
                                        <img src="https://openweathermap.org/img/wn/<?php echo $item['weather'][0]['icon']; ?>.png" 
                                             alt="<?php echo isset($item['weather'][0]['description']) ? $item['weather'][0]['description'] : ''; ?>">
                                    <?php endif; ?>
                                    <div><?php echo isset($item['main']['temp']) ? round($item['main']['temp']) . '°C' : 'N/A'; ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <!-- News Card -->
                <div class="card">
                    <div class="card-header">
                        <h3>Top News Headlines</h3>
                        <div class="icon">
                            <i class="fas fa-newspaper"></i>
                        </div>
                    </div>
                    <div class="news-content">
                        <?php if (!empty($news)): ?>
                            <?php foreach ($news as $item): ?>
                                <div class="news-item">
                                    <div class="news-title"><?php echo htmlspecialchars($item['title']); ?></div>
                                    <div class="news-description"><?php echo htmlspecialchars($item['description']); ?></div>
                                    <?php if (isset($item['url'])): ?>
                                        <div class="news-meta">
                                            <span>Source: <?php echo htmlspecialchars($item['source']['name'] ?? 'Unknown'); ?></span>
                                            <a href="<?php echo htmlspecialchars($item['url']); ?>" target="_blank">Read more</a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p>No news available at the moment.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- User Posts -->
            <?php if (!empty($posts)): ?>
                <div class="posts-container">
                    <h2 style="margin-bottom: 20px;">Your Posts</h2>
                    <?php foreach ($posts as $post): ?>
                        <div class="post">
                            <div class="post-header">
                                <div class="post-user">
                                    <?php 
                                        $initial = strtoupper(substr($_SESSION["username"], 0, 1));
                                        echo $initial;
                                    ?>
                                </div>
                                <div class="post-info">
                                    <h4><?php echo htmlspecialchars($_SESSION["username"]); ?></h4>
                                    <div class="post-date">Posted on <?php echo date('F j, Y, g:i a', strtotime($post['created_at'])); ?></div>
                                </div>
                            </div>
                            <img src="<?php echo htmlspecialchars($post['image_path']); ?>" alt="User post" class="post-image">
                            
                            <div class="post-actions">
                                <div class="post-stats">
                                    <div class="post-stat">
                                        <i class="fas fa-heart"></i>
                                        <span><?php echo $post['like_count']; ?> likes</span>
                                    </div>
                                    <div class="post-stat">
                                        <i class="fas fa-comment"></i>
                                        <span><?php echo $post['comment_count']; ?> comments</span>
                                    </div>
                                </div>
                                
                                <div class="post-buttons">
                                    <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                                        <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                                        <button type="submit" name="like_post" class="btn btn-outline btn-sm">
                                            <i class="fas fa-heart"></i> Like
                                        </button>
                                    </form>
                                    <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                                        <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                                        <button type="submit" name="delete_post" class="btn btn-danger btn-sm">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </form>
                                </div>
                            </div>
                            
                            <!-- Comment Form -->
                            <div class="comments-section">
                                <form method="post" class="comment-form">
                                    <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                                    <input type="text" name="comment_text" placeholder="Write a comment..." required>
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        <i class="fas fa-paper-plane"></i> Post
                                    </button>
                                </form>
                                
                                <!-- Comments will be loaded here via AJAX -->
                                <div class="comments-list" id="comments-<?php echo $post['id']; ?>">
                                    <!-- Comments will be displayed here -->
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Toggle post form visibility
        document.getElementById('postBtn').addEventListener('click', function(e) {
            e.preventDefault();
            document.getElementById('postForm').classList.toggle('active');
        });
        
        // Load comments for each post
        document.querySelectorAll('.post').forEach(post => {
            const postId = post.querySelector('input[name="post_id"]').value;
            loadComments(postId);
        });
        
        function loadComments(postId) {
            fetch(`get_comments.php?post_id=${postId}`)
                .then(response => response.text())
                .then(data => {
                    document.getElementById(`comments-${postId}`).innerHTML = data;
                });
        }
    </script>
</body>
</html>