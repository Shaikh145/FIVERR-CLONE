<?php
session_start();
require_once 'db.php';

// Check if user is logged in
requireLogin();

$user_id = $_SESSION['user_id'];

// Get all user conversations
$conversations = getUserConversations($user_id, $conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - FiverrClone</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        :root {
            --primary-color: #1dbf73;
            --secondary-color: #0e0e0e;
            --text-color: #62646a;
            --light-gray: #f5f5f5;
            --border-color: #e4e5e7;
        }

        body {
            color: var(--secondary-color);
            background-color: var(--light-gray);
            line-height: 1.6;
        }

        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }

        /* Header Styles */
        header {
            background-color: #fff;
            box-shadow: 0 1px 5px rgba(0, 0, 0, 0.1);
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 1000;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
        }

        .logo {
            font-size: 26px;
            font-weight: 700;
            color: var(--secondary-color);
            text-decoration: none;
        }

        .logo span {
            color: var(--primary-color);
        }

        nav ul {
            display: flex;
            list-style: none;
        }

        nav ul li {
            margin-left: 30px;
        }

        nav ul li a {
            color: var(--secondary-color);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.2s;
        }

        nav ul li a:hover {
            color: var(--primary-color);
        }

        .user-menu {
            position: relative;
            display: inline-block;
        }

        .user-profile {
            display: flex;
            align-items: center;
            cursor: pointer;
        }

        .user-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            margin-right: 8px;
            object-fit: cover;
        }

        .dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            background-color: white;
            min-width: 200px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
            border-radius: 4px;
            z-index: 1;
        }

        .dropdown-content a {
            color: var(--secondary-color);
            padding: 12px 16px;
            text-decoration: none;
            display: block;
            font-weight: normal;
        }

        .dropdown-content a:hover {
            background-color: var(--light-gray);
        }

        .user-menu:hover .dropdown-content {
            display: block;
        }

        .divider {
            height: 1px;
            background-color: var(--border-color);
            margin: 5px 0;
        }

        /* Main Content */
        .main-content {
            margin-top: 80px;
            padding: 30px 0;
        }

        .page-title {
            font-size: 28px;
            margin-bottom: 20px;
            color: var(--secondary-color);
        }

        /* Messages Container */
        .messages-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            display: flex;
            min-height: 600px;
        }

        /* Conversation List */
        .conversation-list {
            width: 300px;
            border-right: 1px solid var(--border-color);
            overflow-y: auto;
            max-height: 600px;
        }

        .conversation-header {
            padding: 20px;
            border-bottom: 1px solid var(--border-color);
        }

        .conversation-header h2 {
            font-size: 20px;
            color: var(--secondary-color);
        }

        .conversation-search {
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
        }

        .search-input {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 14px;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--primary-color);
        }

        .conversation-item {
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
            cursor: pointer;
            transition: background-color 0.2s;
            display: flex;
            align-items: center;
        }

        .conversation-item:hover {
            background-color: var(--light-gray);
        }

        .conversation-item.active {
            background-color: #e3f2fd;
        }

        .conversation-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            margin-right: 15px;
            object-fit: cover;
        }

        .conversation-info {
            flex: 1;
        }

        .conversation-name {
            font-weight: 600;
            color: var(--secondary-color);
            margin-bottom: 5px;
            display: flex;
            justify-content: space-between;
        }

        .conversation-time {
            font-size: 12px;
            color: var(--text-color);
        }

        .conversation-preview {
            font-size: 14px;
            color: var(--text-color);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            max-width: 190px;
        }

        .unread-indicator {
            width: 10px;
            height: 10px;
            background-color: var(--primary-color);
            border-radius: 50%;
            display: inline-block;
            margin-left: 5px;
        }

        /* Empty state */
        .empty-conversations {
            text-align: center;
            padding: 50px 0;
            width: 100%;
        }

        .empty-conversations i {
            font-size: 60px;
            color: var(--border-color);
            margin-bottom: 20px;
        }

        .empty-conversations h3 {
            font-size: 20px;
            margin-bottom: 10px;
        }

        .empty-conversations p {
            color: var(--text-color);
            margin-bottom: 20px;
        }

        /* Empty Messages */
        .empty-messages {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            flex: 1;
            padding: 30px;
            text-align: center;
            background-color: var(--light-gray);
        }

        .empty-messages i {
            font-size: 60px;
            color: var(--border-color);
            margin-bottom: 20px;
        }

        .empty-messages h3 {
            font-size: 20px;
            margin-bottom: 10px;
        }

        .empty-messages p {
            color: var(--text-color);
        }

        /* Responsive styles */
        @media (max-width: 768px) {
            .messages-container {
                flex-direction: column;
            }
            
            .conversation-list {
                width: 100%;
                max-height: 300px;
                border-right: none;
                border-bottom: 1px solid var(--border-color);
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header>
        <div class="container">
            <div class="header-content">
                <a href="index.php" class="logo">Fiverr<span>Clone</span></a>
                <nav>
                    <ul>
                        <li><a href="index.php">Home</a></li>
                        <li><a href="search.php">Explore</a></li>
                        <?php if ($_SESSION['is_seller']): ?>
                            <li><a href="create_gig.php">Create Gig</a></li>
                        <?php endif; ?>
                    </ul>
                </nav>
                <div class="user-menu">
                    <div class="user-profile">
                        <img src="<?php echo $_SESSION['profile_pic'] ?? 'https://www.gravatar.com/avatar/00000000000000000000000000000000?d=mp&f=y'; ?>" alt="Profile" class="user-avatar">
                        <span><?php echo $_SESSION['username'<?php
session_start();
require_once 'db.php';

// Check if user is logged in
requireLogin();

$user_id = $_SESSION['user_id'];

// Get user's conversations
$conversations = getUserConversations($user_id, $conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - FiverrClone</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        :root {
            --primary-color: #1dbf73;
            --secondary-color: #0e0e0e;
            --text-color: #62646a;
            --light-gray: #f5f5f5;
            --border-color: #e4e5e7;
        }

        body {
            color: var(--secondary-color);
            background-color: var(--light-gray);
            line-height: 1.6;
        }

        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }

        /* Header Styles */
        header {
            background-color: #fff;
            box-shadow: 0 1px 5px rgba(0, 0, 0, 0.1);
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 1000;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
        }

        .logo {
            font-size: 26px;
            font-weight: 700;
            color: var(--secondary-color);
            text-decoration: none;
        }

        .logo span {
            color: var(--primary-color);
        }

        nav ul {
            display: flex;
            list-style: none;
        }

        nav ul li {
            margin-left: 30px;
        }

        nav ul li a {
            color: var(--secondary-color);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.2s;
        }

        nav ul li a:hover {
            color: var(--primary-color);
        }

        .user-menu {
            position: relative;
            display: inline-block;
        }

        .user-profile {
            display: flex;
            align-items: center;
            cursor: pointer;
        }

        .user-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            margin-right: 8px;
            object-fit: cover;
        }

        .dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            background-color: white;
            min-width: 200px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
            border-radius: 4px;
            z-index: 1;
        }

        .dropdown-content a {
            color: var(--secondary-color);
            padding: 12px 16px;
            text-decoration: none;
            display: block;
            font-weight: normal;
        }

        .dropdown-content a:hover {
            background-color: var(--light-gray);
        }

        .user-menu:hover .dropdown-content {
            display: block;
        }

        .divider {
            height: 1px;
            background-color: var(--border-color);
            margin: 5px 0;
        }

        /* Main Content */
        .main-content {
            margin-top: 80px;
            padding: 30px 0;
        }

        .page-title {
            font-size: 28px;
            margin-bottom: 20px;
            color: var(--secondary-color);
        }

        /* Messages Container */
        .messages-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }

        /* Conversation List */
        .conversation-list {
            list-style: none;
        }

        .conversation-item {
            padding: 20px;
            border-bottom: 1px solid var(--border-color);
            transition: background-color 0.2s;
            cursor: pointer;
            display: flex;
            align-items: center;
        }

        .conversation-item:last-child {
            border-bottom: none;
        }

        .conversation-item:hover {
            background-color: var(--light-gray);
        }

        .conversation-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            margin-right: 15px;
            object-fit: cover;
        }

        .conversation-content {
            flex: 1;
        }

        .conversation-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }

        .conversation-name {
            font-weight: 600;
            color: var(--secondary-color);
        }

        .conversation-time {
            font-size: 14px;
            color: var(--text-color);
        }

        .conversation-message {
            color: var(--text-color);
            font-size: 14px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            max-width: 500px;
        }
        
        .unread-indicator {
            width: 10px;
            height: 10px;
            background-color: var(--primary-color);
            border-radius: 50%;
            margin-left: 10px;
        }

        /* Empty Messages */
        .empty-messages {
            text-align: center;
            padding: 50px 0;
        }

        .empty-messages i {
            font-size: 60px;
            color: var(--border-color);
            margin-bottom: 20px;
        }

        .empty-messages h3 {
            font-size: 20px;
            margin-bottom: 10px;
        }

        .empty-messages p {
            color: var(--text-color);
            margin-bottom: 20px;
        }

        .browse-btn {
            display: inline-block;
            padding: 10px 20px;
            background-color: var(--primary-color);
            color: white;
            border-radius: 4px;
            text-decoration: none;
            font-weight: 600;
            transition: background-color 0.2s;
        }

        .browse-btn:hover {
            background-color: #19a463;
        }

        /* Responsive styles */
        @media (max-width: 768px) {
            .conversation-item {
                padding: 15px;
            }
            
            .conversation-avatar {
                width: 40px;
                height: 40px;
            }
            
            .conversation-message {
                max-width: 200px;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header>
        <div class="container">
            <div class="header-content">
                <a href="index.php" class="logo">Fiverr<span>Clone</span></a>
                <nav>
                    <ul>
                        <li><a href="index.php">Home</a></li>
                        <li><a href="search.php">Explore</a></li>
                        <?php if ($_SESSION['is_seller']): ?>
                            <li><a href="create_gig.php">Create Gig</a></li>
                        <?php endif; ?>
                    </ul>
                </nav>
                <div class="user-menu">
                    <div class="user-profile">
                        <img src="<?php echo $_SESSION['profile_pic'] ?? 'https://www.gravatar.com/avatar/00000000000000000000000000000000?d=mp&f=y'; ?>" alt="Profile" class="user-avatar">
                        <span><?php echo $_SESSION['username']; ?> <i class="fas fa-chevron-down"></i></span>
                    </div>
                    <div class="dropdown-content">
                        <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
                        <?php if ($_SESSION['is_seller']): ?>
                            <a href="profile.php?tab=gigs"><i class="fas fa-list"></i> My Gigs</a>
                        <?php endif; ?>
                        <a href="orders.php"><i class="fas fa-shopping-cart"></i> Orders</a>
                        <a href="messages.php"><i class="fas fa-envelope"></i> Messages</a>
                        <div class="divider"></div>
                        <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container">
            <h1 class="page-title">Messages</h1>
            
            <div class="messages-container">
                <?php if (empty($conversations)): ?>
                    <div class="empty-messages">
                        <i class="fas fa-comments"></i>
                        <h3>No messages yet</h3>
                        <p>Start a conversation with a seller or buyer</p>
                        <a href="search.php" class="browse-btn">Browse Gigs</a>
                    </div>
                <?php else: ?>
                    <ul class="conversation-list">
                        <?php foreach ($conversations as $conversation): ?>
                            <li class="conversation-item" onclick="window.location.href='chat.php?conversation_id=<?php echo $conversation['id']; ?>'">
                                <img src="<?php echo !empty($conversation['other_profile_pic']) ? $conversation['other_profile_pic'] : 'https://www.gravatar.com/avatar/00000000000000000000000000000000?d=mp&f=y'; ?>" alt="<?php echo $conversation['other_username']; ?>" class="conversation-avatar">
                                <div class="conversation-content">
                                    <div class="conversation-header">
                                        <span class="conversation-name"><?php echo $conversation['other_username']; ?></span>
                                        <span class="conversation-time"><?php echo date('M d, g:i a', strtotime($conversation['last_message_time'] ?? $conversation['created_at'])); ?></span>
                                    </div>
                                    <div class="conversation-message">
                                        <?php echo $conversation['last_message'] ?? 'Start a conversation...'; ?>
                                    </div>
                                </div>
                                <?php if (isset($conversation['has_unread']) && $conversation['has_unread']): ?>
                                    <div class="unread-indicator"></div>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
