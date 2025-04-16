<?php
session_start();
require_once 'db.php';

// Check if user is logged in
requireLogin();

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Process review submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id = intval($_POST['order_id']);
    $gig_id = intval($_POST['gig_id']);
    $rating = intval($_POST['rating']);
    $review_text = sanitize($_POST['review']);
    
    // Validate data
    if ($order_id <= 0 || $gig_id <= 0) {
        $error_message = "Invalid order or gig information.";
    } elseif ($rating < 1 || $rating > 5) {
        $error_message = "Rating must be between 1 and 5.";
    } elseif (empty($review_text)) {
        $error_message = "Please provide a review.";
    } else {
        // Verify that the order belongs to the user and is completed
        $stmt = $conn->prepare("SELECT * FROM orders WHERE id = :order_id AND buyer_id = :user_id AND status = 'completed'");
        $stmt->bindParam(':order_id', $order_id);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        if ($stmt->rowCount() === 0) {
            $error_message = "You cannot review this order.";
        } else {
            // Check if review already exists
            $stmt = $conn->prepare("SELECT * FROM reviews WHERE order_id = :order_id");
            $stmt->bindParam(':order_id', $order_id);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $error_message = "You have already submitted a review for this order.";
            } else {
                // Insert review
                $stmt = $conn->prepare("INSERT INTO reviews (gig_id, user_id, order_id, rating, review, created_at) 
                                        VALUES (:gig_id, :user_id, :order_id, :rating, :review, NOW())");
                
                $stmt->bindParam(':gig_id', $gig_id);
                $stmt->bindParam(':user_id', $user_id);
                $stmt->bindParam(':order_id', $order_id);
                $stmt->bindParam(':rating', $rating);
                $stmt->bindParam(':review', $review_text);
                
                if ($stmt->execute()) {
                    // Update gig rating and total reviews
                    $stmt = $conn->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews 
                                            FROM reviews WHERE gig_id = :gig_id");
                    $stmt->bindParam(':gig_id', $gig_id);
                    $stmt->execute();
                    $rating_data = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    $avg_rating = $rating_data['avg_rating'];
                    $total_reviews = $rating_data['total_reviews'];
                    
                    $stmt = $conn->prepare("UPDATE gigs SET rating = :rating, total_reviews = :total_reviews WHERE id = :gig_id");
                    $stmt->bindParam(':rating', $avg_rating);
                    $stmt->bindParam(':total_reviews', $total_reviews);
                    $stmt->bindParam(':gig_id', $gig_id);
                    $stmt->execute();
                    
                    $success_message = "Your review has been submitted successfully!";
                } else {
                    $error_message = "Failed to submit review. Please try again.";
                }
            }
        }
    }
}

// Redirect to orders page with appropriate message
if ($success_message) {
    $_SESSION['success_message'] = $success_message;
} elseif ($error_message) {
    $_SESSION['error_message'] = $error_message;
}

echo "<script>window.location.href = 'orders.php';</script>";
exit;
?>
