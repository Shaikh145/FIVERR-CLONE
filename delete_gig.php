<?php
session_start();
require_once 'db.php';

// Check if user is logged in and is a seller
requireLogin();

if (!$_SESSION['is_seller']) {
    echo "<script>window.location.href = 'index.php';</script>";
    exit;
}

// Check if gig ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo "<script>window.location.href = 'profile.php?tab=gigs';</script>";
    exit;
}

$gig_id = intval($_GET['id']);
$user_id = $_SESSION['user_id'];

// Check if the gig belongs to the user
$stmt = $conn->prepare("SELECT * FROM gigs WHERE id = :gig_id AND user_id = :user_id");
$stmt->bindParam(':gig_id', $gig_id);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();

if ($stmt->rowCount() === 0) {
    // Gig not found or doesn't belong to the user
    $_SESSION['error_message'] = "You don't have permission to delete this gig.";
    echo "<script>window.location.href = 'profile.php?tab=gigs';</script>";
    exit;
}

// Check if gig has any active orders
$stmt = $conn->prepare("SELECT COUNT(*) as order_count FROM orders 
                        WHERE gig_id = :gig_id 
                        AND status IN ('pending', 'in_progress', 'delivered')");
$stmt->bindParam(':gig_id', $gig_id);
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);

if ($result['order_count'] > 0) {
    $_SESSION['error_message'] = "Cannot delete gig with active orders. Please complete or cancel all orders first.";
    echo "<script>window.location.href = 'profile.php?tab=gigs';</script>";
    exit;
}

// Delete the gig reviews first (foreign key constraint)
$stmt = $conn->prepare("DELETE FROM reviews WHERE gig_id = :gig_id");
$stmt->bindParam(':gig_id', $gig_id);
$stmt->execute();

// Delete completed orders associated with the gig
$stmt = $conn->prepare("DELETE FROM orders WHERE gig_id = :gig_id AND status IN ('completed', 'cancelled')");
$stmt->bindParam(':gig_id', $gig_id);
$stmt->execute();

// Delete the gig
$stmt = $conn->prepare("DELETE FROM gigs WHERE id = :gig_id AND user_id = :user_id");
$stmt->bindParam(':gig_id', $gig_id);
$stmt->bindParam(':user_id', $user_id);

if ($stmt->execute()) {
    $_SESSION['success_message'] = "Gig deleted successfully!";
} else {
    $_SESSION['error_message'] = "Failed to delete gig. Please try again.";
}

// Redirect back to profile gigs tab
echo "<script>window.location.href = 'profile.php?tab=gigs';</script>";
exit;
?>
