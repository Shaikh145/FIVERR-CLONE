<?php
session_start();
require_once 'db.php';

// Check if user is logged in
requireLogin();

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Handle order status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $order_id = intval($_POST['order_id']);
    $new_status = sanitize($_POST['new_status']);
    
    // Verify that status is valid
    $valid_statuses = ['in_progress', 'completed', 'cancelled', 'delivered'];
    if (!in_array($new_status, $valid_statuses)) {
        $error_message = "Invalid status provided";
    } else {
        // Get order details to confirm ownership
        $stmt = $conn->prepare("SELECT o.*, g.user_id as seller_id FROM orders o 
                                JOIN gigs g ON o.gig_id = g.id 
                                WHERE o.id = :order_id");
        $stmt->bindParam(':order_id', $order_id);
        $stmt->execute();
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Verify that the user is either the buyer or seller
        if ($order && ($order['buyer_id'] == $user_id || $order['seller_id'] == $user_id)) {
            // Some validations based on user role
            $is_seller = ($order['seller_id'] == $user_id);
            $is_buyer = ($order['buyer_id'] == $user_id);
            
            $can_update = false;
            
            // Validate status changes based on role and current status
            if ($is_seller) {
                // Sellers can change order to in_progress, delivered or cancelled
                if (($order['status'] == 'pending' && ($new_status == 'in_progress' || $new_status == 'cancelled')) ||
                    ($order['status'] == 'in_progress' && ($new_status == 'delivered' || $new_status == 'cancelled'))) {
                    $can_update = true;
                }
            } else if ($is_buyer) {
                // Buyers can only cancel pending orders or mark delivered orders as completed
                if (($order['status'] == 'pending' && $new_status == 'cancelled') ||
                    ($order['status'] == 'delivered' && $new_status == 'completed')) {
                    $can_update = true;
                }
            }
            
            if ($can_update) {
                $stmt = $conn->prepare("UPDATE orders SET status = :new_status, updated_at = NOW() WHERE id = :order_id");
                $stmt->bindParam(':new_status', $new_status);
                $stmt->bindParam(':order_id', $order_id);
                
                if ($stmt->execute()) {
                    $success_message = "Order status updated successfully";
                    
                    // If order is now complete, prompt for review
                    if ($new_status == 'completed' && $is_buyer) {
                        $success_message .= ". Please leave a review for this order.";
                    }
                } else {
                    $error_message = "Failed to update order status";
                }
            } else {
                $error_message = "You are not allowed to make this status change";
            }
        } else {
            $error_message = "You don't have permission to update this order";
        }
    }
}

// Fetch all orders for the user (both as buyer and seller)
$orders = getUserOrders($user_id, $conn);

// Get current tab
$currentTab = isset($_GET['tab']) ? $_GET['tab'] : 'all';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - FiverrClone</title>
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

        /* Orders Container */
        .orders-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }

        /* Tabs */
        .orders-tabs {
            display: flex;
            border-bottom: 1px solid var(--border-color);
        }

        .orders-tab {
            padding: 15px 20px;
            font-weight: 600;
            cursor: pointer;
            transition: color 0.2s, border-bottom 0.2s;
            border-bottom: 2px solid transparent;
        }

        .orders-tab.active {
            color: var(--primary-color);
            border-bottom: 2px solid var(--primary-color);
        }

        /* Orders Table */
        .orders-table {
            width: 100%;
            border-collapse: collapse;
        }

        .orders-table th, 
        .orders-table td {
            padding: 15px 20px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        .orders-table th {
            font-weight: 600;
            color: var(--secondary-color);
            background-color: var(--light-gray);
        }

        .orders-table tr:last-child td {
            border-bottom: none;
        }

        .order-id {
            font-weight: 600;
            color: var(--secondary-color);
        }

        .order-title {
            width: 30%;
            font-weight: 600;
            color: var(--secondary-color);
        }

        .order-title a {
            color: var(--secondary-color);
            text-decoration: none;
            transition: color 0.2s;
        }

        .order-title a:hover {
            color: var(--primary-color);
        }

        .order-price {
            font-weight: 600;
            color: var(--secondary-color);
        }

        .order-status {
            padding: 5px 10px;
            border-radius: 50px;
            font-size: 14px;
            font-weight: 600;
            text-align: center;
            display: inline-block;
            min-width: 100px;
        }

        .status-pending {
            background-color: #fff8e1;
            color: #ffa000;
        }

        .status-in_progress {
            background-color: #e3f2fd;
            color: #1976d2;
        }

        .status-delivered {
            background-color: #e8f5e9;
            color: #388e3c;
        }

        .status-completed {
            background-color: #e8f5e9;
            color: #2e7d32;
            font-weight: 700;
        }

        .status-cancelled {
            background-color: #ffebee;
            color: #c62828;
        }

        .order-actions {
            white-space: nowrap;
        }

        .order-actions button,
        .order-actions a {
            padding: 8px 12px;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s;
            border: none;
            margin-right: 5px;
            text-decoration: none;
            display: inline-block;
        }

        .message-btn {
            background-color: var(--light-gray);
            color: var(--secondary-color);
        }

        .message-btn:hover {
            background-color: var(--border-color);
        }

        .accept-btn {
            background-color: var(--primary-color);
            color: white;
        }

        .accept-btn:hover {
            background-color: #19a463;
        }

        .deliver-btn {
            background-color: #2196f3;
            color: white;
        }

        .deliver-btn:hover {
            background-color: #0d8bf2;
        }

        .complete-btn {
            background-color: #4caf50;
            color: white;
        }

        .complete-btn:hover {
            background-color: #3d8b40;
        }

        .cancel-btn {
            background-color: #f44336;
            color: white;
        }

        .cancel-btn:hover {
            background-color: #d32f2f;
        }

        .review-btn {
            background-color: #ff9800;
            color: white;
        }

        .review-btn:hover {
            background-color: #f57c00;
        }

        /* Empty Orders */
        .empty-orders {
            text-align: center;
            padding: 50px 0;
        }

        .empty-orders i {
            font-size: 60px;
            color: var(--border-color);
            margin-bottom: 20px;
        }

        .empty-orders h3 {
            font-size: 20px;
            margin-bottom: 10px;
        }

        .empty-orders p {
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

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1050;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.4);
        }

        .modal-content {
            background-color: white;
            margin: 10% auto;
            padding: 20px;
            border-radius: 8px;
            width: 80%;
            max-width: 500px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            position: relative;
        }

        .close {
            position: absolute;
            right: 20px;
            top: 15px;
            font-size: 24px;
            font-weight: bold;
            color: var(--text-color);
            cursor: pointer;
        }

        .modal-title {
            font-size: 20px;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border-color);
        }

        .modal-form {
            margin-top: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            font-size: 16px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            transition: border-color 0.2s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
        }

        .modal-buttons {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
        }

        .modal-btn {
            padding: 10px 20px;
            border-radius: 4px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s;
            border: none;
        }

        .modal-cancel {
            background-color: var(--light-gray);
            color: var(--secondary-color);
        }

        .modal-submit {
            background-color: var(--primary-color);
            color: white;
        }

        .modal-submit:hover {
            background-color: #19a463;
        }

        /* Responsive styles */
        @media (max-width: 768px) {
            .orders-tabs {
                overflow-x: auto;
                white-space: nowrap;
            }
            
            .orders-table {
                display: block;
                overflow-x: auto;
            }
            
            .orders-table th, 
            .orders-table td {
                white-space: nowrap;
            }
            
            .order-title {
                width: auto;
            }
            
            .order-actions {
                display: flex;
                flex-direction: column;
                gap: 5px;
            }
            
            .order-actions button,
            .order-actions a {
                margin-right: 0;
                text-align: center;
                margin-bottom: 5px;
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
            <h1 class="page-title">My Orders</h1>
            
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
            
            <div class="orders-container">
                <div class="orders-tabs">
                    <div class="orders-tab <?php echo $currentTab === 'all' ? 'active' : ''; ?>" onclick="changeTab('all')">All Orders</div>
                    <div class="orders-tab <?php echo $currentTab === 'buying' ? 'active' : ''; ?>" onclick="changeTab('buying')">Buying</div>
                    <?php if ($_SESSION['is_seller']): ?>
                        <div class="orders-tab <?php echo $currentTab === 'selling' ? 'active' : ''; ?>" onclick="changeTab('selling')">Selling</div>
                    <?php endif; ?>
                </div>
                
                <?php
                // Filter orders based on tab
                $filtered_orders = [];
                foreach ($orders as $order) {
                    if ($currentTab === 'all' || 
                        ($currentTab === 'buying' && $order['role'] === 'buyer') || 
                        ($currentTab === 'selling' && $order['role'] === 'seller')) {
                        $filtered_orders[] = $order;
                    }
                }
                ?>
                
                <?php if (empty($filtered_orders)): ?>
                    <div class="empty-orders">
                        <i class="fas fa-shopping-cart"></i>
                        <h3>No orders found</h3>
                        <?php if ($currentTab === 'buying' || $currentTab === 'all'): ?>
                            <p>You haven't placed any orders yet.</p>
                            <a href="search.php" class="browse-btn">Browse Gigs</a>
                        <?php elseif ($currentTab === 'selling'): ?>
                            <p>You haven't received any orders yet.</p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="orders-table-wrapper">
                        <table class="orders-table">
                            <thead>
                                <tr>
                                    <th>Order</th>
                                    <th>Service</th>
                                    <th>Price</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($filtered_orders as $order): ?>
                                    <tr>
                                        <td class="order-id">#<?php echo $order['id']; ?></td>
                                        <td class="order-title">
                                            <a href="gig_detail.php?id=<?php echo $order['gig_id']; ?>"><?php echo $order['gig_title']; ?></a>
                                        </td>
                                        <td class="order-price">$<?php echo $order['price']; ?></td>
                                        <td><?php echo date('M d, Y', strtotime($order['created_at'])); ?></td>
                                        <td>
                                            <span class="order-status status-<?php echo $order['status']; ?>">
                                                <?php 
                                                    switch ($order['status']) {
                                                        case 'pending':
                                                            echo 'Pending';
                                                            break;
                                                        case 'in_progress':
                                                            echo 'In Progress';
                                                            break;
                                                        case 'delivered':
                                                            echo 'Delivered';
                                                            break;
                                                        case 'completed':
                                                            echo 'Completed';
                                                            break;
                                                        case 'cancelled':
                                                            echo 'Cancelled';
                                                            break;
                                                        default:
                                                            echo ucfirst($order['status']);
                                                    }
                                                ?>
                                            </span>
                                        </td>
                                        <td class="order-actions">
                                            <?php
                                            $is_seller = ($order['role'] === 'seller');
                                            $is_buyer = ($order['role'] === 'buyer');
                                            $status = $order['status'];
                                            
                                            // Message button is always available
                                            if ($is_seller) {
                                                $other_user_id = $order['buyer_id'];
                                            } else {
                                                $other_user_id = $order['seller_id'];
                                            }
                                            ?>
                                            
                                            <a href="chat.php?user_id=<?php echo $other_user_id; ?>" class="message-btn">
                                                <i class="fas fa-comment"></i> Message
                                            </a>
                                            
                                            <?php if ($is_seller && $status === 'pending'): ?>
                                                <!-- Seller can accept or decline pending orders -->
                                                <button class="accept-btn" onclick="updateOrderStatus(<?php echo $order['id']; ?>, 'in_progress')">
                                                    <i class="fas fa-check"></i> Accept
                                                </button>
                                                <button class="cancel-btn" onclick="updateOrderStatus(<?php echo $order['id']; ?>, 'cancelled')">
                                                    <i class="fas fa-times"></i> Decline
                                                </button>
                                            <?php elseif ($is_seller && $status === 'in_progress'): ?>
                                                <!-- Seller can deliver in-progress orders -->
                                                <button class="deliver-btn" onclick="updateOrderStatus(<?php echo $order['id']; ?>, 'delivered')">
                                                    <i class="fas fa-truck"></i> Deliver
                                                </button>
                                            <?php elseif ($is_buyer && $status === 'pending'): ?>
                                                <!-- Buyer can cancel pending orders -->
                                                <button class="cancel-btn" onclick="updateOrderStatus(<?php echo $order['id']; ?>, 'cancelled')">
                                                    <i class="fas fa-times"></i> Cancel
                                                </button>
                                            <?php elseif ($is_buyer && $status === 'delivered'): ?>
                                                <!-- Buyer can complete delivered orders -->
                                                <button class="complete-btn" onclick="updateOrderStatus(<?php echo $order['id']; ?>, 'completed')">
                                                    <i class="fas fa-check"></i> Complete
                                                </button>
                                            <?php elseif ($is_buyer && $status === 'completed'): ?>
                                                <!-- Buyer can review completed orders -->
                                                <a href="#" class="review-btn" onclick="openReviewModal(<?php echo $order['id']; ?>, <?php echo $order['gig_id']; ?>)">
                                                    <i class="fas fa-star"></i> Review
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Review Modal -->
    <div id="reviewModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeReviewModal()">&times;</span>
            <h2 class="modal-title">Leave a Review</h2>
            <form id="reviewForm" action="submit_review.php" method="POST">
                <input type="hidden" id="order_id" name="order_id" value="">
                <input type="hidden" id="gig_id" name="gig_id" value="">
                
                <div class="form-group">
                    <label>Rating</label>
                    <div class="rating-stars">
                        <input type="radio" name="rating" value="5" id="rating-5" checked>
                        <label for="rating-5"><i class="fas fa-star"></i></label>
                        <input type="radio" name="rating" value="4" id="rating-4">
                        <label for="rating-4"><i class="fas fa-star"></i></label>
                        <input type="radio" name="rating" value="3" id="rating-3">
                        <label for="rating-3"><i class="fas fa-star"></i></label>
                        <input type="radio" name="rating" value="2" id="rating-2">
                        <label for="rating-2"><i class="fas fa-star"></i></label>
                        <input type="radio" name="rating" value="1" id="rating-1">
                        <label for="rating-1"><i class="fas fa-star"></i></label>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="review">Your Review</label>
                    <textarea id="review" name="review" class="form-control" placeholder="Share your experience with this service..." required></textarea>
                </div>
                
                <div class="modal-buttons">
                    <button type="button" class="modal-btn modal-cancel" onclick="closeReviewModal()">Cancel</button>
                    <button type="submit" class="modal-btn modal-submit">Submit Review</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Change tab function
        function changeTab(tabName) {
            window.location.href = 'orders.php?tab=' + tabName;
        }
        
        // Update order status function
        function updateOrderStatus(orderId, newStatus) {
            // Confirm before updating
            let confirmMessage = '';
            
            switch(newStatus) {
                case 'in_progress':
                    confirmMessage = 'Are you sure you want to accept this order?';
                    break;
                case 'delivered':
                    confirmMessage = 'Are you sure you want to mark this order as delivered?';
                    break;
                case 'completed':
                    confirmMessage = 'Are you sure you want to complete this order?';
                    break;
                case 'cancelled':
                    confirmMessage = 'Are you sure you want to cancel this order?';
                    break;
                default:
                    confirmMessage = 'Are you sure you want to update this order?';
            }
            
            if (confirm(confirmMessage)) {
                // Create and submit a form
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'orders.php';
                
                const orderIdInput = document.createElement('input');
                orderIdInput.type = 'hidden';
                orderIdInput.name = 'order_id';
                orderIdInput.value = orderId;
                
                const statusInput = document.createElement('input');
                statusInput.type = 'hidden';
                statusInput.name = 'new_status';
                statusInput.value = newStatus;
                
                const submitInput = document.createElement('input');
                submitInput.type = 'hidden';
                submitInput.name = 'update_status';
                submitInput.value = '1';
                
                form.appendChild(orderIdInput);
                form.appendChild(statusInput);
                form.appendChild(submitInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Review modal functions
        function openReviewModal(orderId, gigId) {
            const modal = document.getElementById('reviewModal');
            document.getElementById('order_id').value = orderId;
            document.getElementById('gig_id').value = gigId;
            modal.style.display = 'block';
        }
        
        function closeReviewModal() {
            const modal = document.getElementById('reviewModal');
            modal.style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('reviewModal');
            if (event.target == modal) {
                closeReviewModal();
            }
        }
    </script>
</body>
</html>
