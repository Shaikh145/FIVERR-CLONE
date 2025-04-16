<?php
session_start();
require_once 'db.php';

// Check if user is logged in
requireLogin();

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Get conversation ID if exists, otherwise user_id/seller_id for new conversation
$conversation_id = isset($_GET['conversation_id']) ? intval($_GET['conversation_id']) : 0;
$other_user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$seller_id = isset($_GET['seller_id']) ? intval($_GET['seller_id']) : 0;

// If seller_id is provided, use it as other_user_id
if ($seller_id > 0) {
    $other_user_id = $seller_id;
}

// Create or get conversation
if ($conversation_id === 0 && $other_user_id > 0) {
    $conversation_id = getOrCreateConversation($user_id, $other_user_id, $conn);
}

// Validate conversation exists and user is a participant
if ($conversation_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM conversations WHERE id = :conversation_id AND (sender_id = :user_id OR receiver_id = :user_id)");
    $stmt->bindParam(':conversation_id', $conversation_id);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        // User is not a participant in this conversation
        echo "<script>window.location.href = 'messages.php';</script>";
        exit;
    }
    
    $conversation = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Determine the other user
    if ($conversation['sender_id'] == $user_id) {
        $other_user_id = $conversation['receiver_id'];
    } else {
        $other_user_id = $conversation['sender_id'];
    }
    
    // Get other user info
    $other_user = getUserInfo($other_user_id, $conn);
    
    // Get messages for this conversation
    $messages = getConversationMessages($conversation_id, $conn);
    
    // Process new message if submitted
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
        $message_text = sanitize($_POST['message']);
        
        if (!empty($message_text)) {
            $stmt = $conn->prepare("INSERT INTO messages (conversation_id, sender_id, message, is_read, created_at) 
                                    VALUES (:conversation_id, :sender_id, :message, 0, NOW())");
            
            $stmt->bindParam(':conversation_id', $conversation_id);
            $stmt->bindParam(':sender_id', $user_id);
            $stmt->bindParam(':message', $message_text);
            
            if ($stmt->execute()) {
                // Get the new message to add to the messages array
                $stmt = $conn->prepare("SELECT m.*, u.username, u.profile_pic FROM messages m
                                        JOIN users u ON m.sender_id = u.id
                                        WHERE m.id = LAST_INSERT_ID()");
                $stmt->execute();
                $new_message = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Add new message to the messages array
                if ($new_message) {
                    $messages[] = $new_message;
                }
            } else {
                $error_message = "Failed to send message. Please try again.";
            }
        } else {
            $error_message = "Message cannot be empty.";
        }
    }
    
    // Mark messages as read
    $stmt = $conn->prepare("UPDATE messages SET is_read = 1 
                            WHERE conversation_id = :conversation_id 
                            AND sender_id = :other_user_id 
                            AND is_read = 0");
    
    $stmt->bindParam(':conversation_id', $conversation_id);
    $stmt->bindParam(':other_user_id', $other_user_id);
    $stmt->execute();
} else {
    // No valid conversation found
    echo "<script>window.location.href = 'messages.php';</script>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat with <?php echo $other_user['username']; ?> - FiverrClone</title>
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
            --message-sent: #e3f2fd;
            --message-received: #f5f5f5;
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

        /* Chat Container */
        .chat-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            height: calc(100vh - 160px);
        }

        /* Chat Header */
        .chat-header {
            padding: 15px 20px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
        }

        .chat-header-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 15px;
            object-fit: cover;
        }

        .chat-header-info h3 {
            font-size: 18px;
            margin-bottom: 3px;
        }

        .chat-header-info p {
            font-size: 14px;
            color: var(--text-color);
        }

        .back-to-messages {
            margin-left: auto;
            color: var(--text-color);
            text-decoration: none;
            font-size: 14px;
            transition: color 0.2s;
        }

        .back-to-messages:hover {
            color: var(--primary-color);
        }

        /* Chat Messages */
        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
        }

        .message {
            display: flex;
            margin-bottom: 20px;
            max-width: 80%;
        }

        .message.sent {
            margin-left: auto;
            flex-direction: row-reverse;
        }

        .message-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin: 0 10px;
            object-fit: cover;
        }

        .message-content {
            padding: 10px 15px;
            border-radius: 18px;
            position: relative;
        }

        .message.sent .message-content {
            background-color: var(--message-sent);
            border-bottom-right-radius: 4px;
        }

        .message.received .message-content {
            background-color: var(--message-received);
            border-bottom-left-radius: 4px;
        }

        .message-text {
            margin-bottom: 5px;
        }

        .message-time {
            font-size: 12px;
            color: var(--text-color);
            text-align: right;
        }

        /* Chat Input */
        .chat-input {
            padding: 15px 20px;
            border-top: 1px solid var(--border-color);
            display: flex;
            align-items: center;
        }

        .chat-input form {
            display: flex;
            width: 100%;
        }

        .message-input {
            flex: 1;
            padding: 12px 15px;
            font-size: 16px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            transition: border-color 0.2s;
            margin-right: 10px;
        }

        .message-input:focus {
            outline: none;
            border-color: var(--primary-color);
        }

        .send-btn {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 0 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: background-color 0.2s;
        }

        .send-btn:hover {
            background-color: #19a463;
        }

        /* Alert Messages */
        .alert {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }

        .alert-success {
            background-color: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #c8e6c9;
        }

        .alert-error {
            background-color: #ffebee;
            color: #c62828;
            border: 1px solid #ffcdd2;
        }

        /* Date Divider */
        .date-divider {
            text-align: center;
            margin: 20px 0;
            position: relative;
        }

        .date-divider span {
            background-color: white;
            padding: 0 10px;
            font-size: 14px;
            color: var(--text-color);
            position: relative;
            z-index: 1;
        }

        .date-divider:before {
            content: "";
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background-color: var(--border-color);
            z-index: 0;
        }

        /* Responsive styles */
        @media (max-width: 768px) {
            .chat-container {
                height: calc(100vh - 140px);
            }
            
            .message {
                max-width: 90%;
            }
            
            .message-avatar {
                width: 30px;
                height: 30px;
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
            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <?php echo $success_message; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-error">
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>
            
            <div class="chat-container">
                <div class="chat-header">
                    <img src="<?php echo !empty($other_user['profile_pic']) ? $other_user['profile_pic'] : 'https://www.gravatar.com/avatar/00000000000000000000000000000000?d=mp&f=y'; ?>" alt="<?php echo $other_user['username']; ?>" class="chat-header-avatar">
                    <div class="chat-header-info">
                        <h3><?php echo $other_user['username']; ?></h3>
                        <p><?php echo $other_user['is_seller'] ? 'Seller' : 'Buyer'; ?></p>
                    </div>
                    <a href="messages.php" class="back-to-messages"><i class="fas fa-arrow-left"></i> Back to Messages</a>
                </div>
                
                <div class="chat-messages" id="chatMessages">
                    <?php 
                    $current_date = '';
                    foreach ($messages as $message): 
                        $message_date = date('Y-m-d', strtotime($message['created_at']));
                        
                        // Show date divider if date changes
                        if ($message_date != $current_date) {
                            $current_date = $message_date;
                            $display_date = date('F j, Y', strtotime($message['created_at']));
                            echo '<div class="date-divider"><span>' . $display_date . '</span></div>';
                        }
                    ?>
                        <div class="message <?php echo ($message['sender_id'] == $user_id) ? 'sent' : 'received'; ?>">
                            <img src="<?php echo !empty($message['profile_pic']) ? $message['profile_pic'] : 'https://www.gravatar.com/avatar/00000000000000000000000000000000?d=mp&f=y'; ?>" alt="<?php echo $message['username']; ?>" class="message-avatar">
                            <div class="message-content">
                                <div class="message-text"><?php echo nl2br($message['message']); ?></div>
                                <div class="message-time"><?php echo date('g:i a', strtotime($message['created_at'])); ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="chat-input">
                    <form action="chat.php?conversation_id=<?php echo $conversation_id; ?>" method="POST">
                        <input type="text" name="message" class="message-input" placeholder="Type a message..." autofocus>
                        <button type="submit" name="send_message" class="send-btn">
                            <i class="fas fa-paper-plane"></i> Send
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Scroll to bottom of chat on load
        document.addEventListener('DOMContentLoaded', function() {
            const chatMessages = document.getElementById('chatMessages');
            chatMessages.scrollTop = chatMessages.scrollHeight;
        });
    </script>
</body>
</html>
