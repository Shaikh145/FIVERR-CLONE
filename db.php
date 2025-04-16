<?php
// Database connection
$host = 'localhost';
$db = 'dbvfpcc8wg8rpe';
$user = 'uklz9ew3hrop3';
$password = 'zyrbspyjlzjb';

try {
    $conn = new PDO("mysql:host=$host;dbname=$db", $user, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Function to check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Function to redirect to login if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        echo "<script>window.location.href = 'login.php';</script>";
        exit;
    }
}

// Function to sanitize user input
function sanitize($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Get user information
function getUserInfo($user_id, $conn) {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = :user_id");
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get gig information
function getGigInfo($gig_id, $conn) {
    $stmt = $conn->prepare("SELECT g.*, u.username, u.profile_pic FROM gigs g 
                            JOIN users u ON g.user_id = u.id 
                            WHERE g.id = :gig_id");
    $stmt->bindParam(':gig_id', $gig_id);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get user gigs
function getUserGigs($user_id, $conn) {
    $stmt = $conn->prepare("SELECT * FROM gigs WHERE user_id = :user_id ORDER BY created_at DESC");
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get featured gigs for homepage
function getFeaturedGigs($conn, $limit = 8) {
    $stmt = $conn->prepare("SELECT g.*, u.username, u.profile_pic FROM gigs g 
                            JOIN users u ON g.user_id = u.id 
                            ORDER BY g.rating DESC, g.created_at DESC LIMIT :limit");
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get all categories
function getCategories($conn) {
    $stmt = $conn->prepare("SELECT * FROM categories ORDER BY name");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get orders for a user (as buyer or seller)
function getUserOrders($user_id, $conn, $role = 'both') {
    if ($role == 'buyer') {
        $stmt = $conn->prepare("SELECT o.*, g.title as gig_title, u.username as seller_name 
                                FROM orders o 
                                JOIN gigs g ON o.gig_id = g.id 
                                JOIN users u ON g.user_id = u.id 
                                WHERE o.buyer_id = :user_id 
                                ORDER BY o.created_at DESC");
    } elseif ($role == 'seller') {
        $stmt = $conn->prepare("SELECT o.*, g.title as gig_title, u.username as buyer_name 
                                FROM orders o 
                                JOIN gigs g ON o.gig_id = g.id 
                                JOIN users u ON o.buyer_id = u.id 
                                WHERE g.user_id = :user_id 
                                ORDER BY o.created_at DESC");
    } else {
        // Both roles
        $stmt = $conn->prepare("SELECT o.*, g.title as gig_title, 
                                seller.username as seller_name, 
                                buyer.username as buyer_name,
                                CASE WHEN g.user_id = :user_id THEN 'seller' ELSE 'buyer' END as role
                                FROM orders o 
                                JOIN gigs g ON o.gig_id = g.id 
                                JOIN users seller ON g.user_id = seller.id 
                                JOIN users buyer ON o.buyer_id = buyer.id 
                                WHERE g.user_id = :user_id OR o.buyer_id = :user_id 
                                ORDER BY o.created_at DESC");
    }
    
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get conversations for a user
function getUserConversations($user_id, $conn) {
    $stmt = $conn->prepare("SELECT c.*, 
                            CASE WHEN c.sender_id = :user_id THEN receiver.username ELSE sender.username END as other_username,
                            CASE WHEN c.sender_id = :user_id THEN receiver.profile_pic ELSE sender.profile_pic END as other_profile_pic,
                            m.message as last_message, m.created_at as last_message_time
                            FROM conversations c
                            JOIN users sender ON c.sender_id = sender.id
                            JOIN users receiver ON c.receiver_id = receiver.id
                            LEFT JOIN (
                                SELECT conversation_id, message, created_at, 
                                ROW_NUMBER() OVER (PARTITION BY conversation_id ORDER BY created_at DESC) as rn
                                FROM messages
                            ) m ON m.conversation_id = c.id AND m.rn = 1
                            WHERE c.sender_id = :user_id OR c.receiver_id = :user_id
                            ORDER BY m.created_at DESC");
    
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get messages for a conversation
function getConversationMessages($conversation_id, $conn) {
    $stmt = $conn->prepare("SELECT m.*, u.username, u.profile_pic FROM messages m
                            JOIN users u ON m.sender_id = u.id
                            WHERE m.conversation_id = :conversation_id
                            ORDER BY m.created_at ASC");
    
    $stmt->bindParam(':conversation_id', $conversation_id);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get or create conversation between two users
function getOrCreateConversation($sender_id, $receiver_id, $conn) {
    // Check if conversation exists
    $stmt = $conn->prepare("SELECT * FROM conversations 
                            WHERE (sender_id = :sender_id AND receiver_id = :receiver_id)
                            OR (sender_id = :receiver_id AND receiver_id = :sender_id)");
    
    $stmt->bindParam(':sender_id', $sender_id);
    $stmt->bindParam(':receiver_id', $receiver_id);
    $stmt->execute();
    
    $conversation = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($conversation) {
        return $conversation['id'];
    }
    
    // Create new conversation
    $stmt = $conn->prepare("INSERT INTO conversations (sender_id, receiver_id, created_at) 
                            VALUES (:sender_id, :receiver_id, NOW())");
    
    $stmt->bindParam(':sender_id', $sender_id);
    $stmt->bindParam(':receiver_id', $receiver_id);
    $stmt->execute();
    
    return $conn->lastInsertId();
}
?>
