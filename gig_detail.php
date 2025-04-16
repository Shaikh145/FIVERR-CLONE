<?php
session_start();
require_once 'db.php';

// Check if gig ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo "<script>window.location.href = 'index.php';</script>";
    exit;
}

$gig_id = intval($_GET['id']);

// Get gig information
$gig = getGigInfo($gig_id, $conn);

// Check if gig exists
if (!$gig) {
    echo "<script>window.location.href = 'index.php';</script>";
    exit;
}

// Get seller information
$seller = getUserInfo($gig['user_id'], $conn);

// Get category information
$stmt = $conn->prepare("SELECT name FROM categories WHERE id = :category_id");
$stmt->bindParam(':category_id', $gig['category_id']);
$stmt->execute();
$category = $stmt->fetch(PDO::FETCH_ASSOC);

// Get reviews for this gig
$stmt = $conn->prepare("SELECT r.*, u.username, u.profile_pic FROM reviews r 
                        JOIN users u ON r.user_id = u.id 
                        WHERE r.gig_id = :gig_id 
                        ORDER BY r.created_at DESC");
$stmt->bindParam(':gig_id', $gig_id);
$stmt->execute();
$reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Process order placement
$orderSuccess = false;
$orderError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    // Check if user is logged in
    if (!isLoggedIn()) {
        echo "<script>window.location.href = 'login.php';</script>";
        exit;
    }
    
    // Check if user is not trying to buy their own gig
    if ($_SESSION['user_id'] == $gig['user_id']) {
        $orderError = "You cannot purchase your own gig.";
    } else {
        $buyer_id = $_SESSION['user_id'];
        $requirements = sanitize($_POST['requirements']);
        $price = $gig['price'];
        
        // Create order
        $stmt = $conn->prepare("INSERT INTO orders (gig_id, buyer_id, requirements, price, status, created_at, delivery_date) 
                                VALUES (:gig_id, :buyer_id, :requirements, :price, 'pending', NOW(), DATE_ADD(NOW(), INTERVAL :delivery_time DAY))");
        
        $stmt->bindParam(':gig_id', $gig_id);
        $stmt->bindParam(':buyer_id', $buyer_id);
        $stmt->bindParam(':requirements', $requirements);
        $stmt->bindParam(':price', $price);
        $stmt->bindParam(':delivery_time', $gig['delivery_time'], PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            $order_id = $conn->lastInsertId();
            $orderSuccess = true;
            
            // Create or get conversation for messaging
            $conversation_id = getOrCreateConversation($buyer_id, $gig['user_id'], $conn);
            
            // Add automatic message about the order
            $message = "I've placed an order #" . $order_id . " for your gig: " . $gig['title'];
            
            $stmt = $conn->prepare("INSERT INTO messages (conversation_id, sender_id, message, created_at) 
                                    VALUES (:conversation_id, :sender_id, :message, NOW())");
            
            $stmt->bindParam(':conversation_id', $conversation_id);
            $stmt->bindParam(':sender_id', $buyer_id);
            $stmt->bindParam(':message', $message);
            $stmt->execute();
        } else {
            $orderError = "Failed to place order. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $gig['title']; ?> - FiverrClone</title>
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

        .auth-buttons a {
            text-decoration: none;
            margin-left: 15px;
            font-weight: 600;
        }

        .login-btn {
            color: var(--secondary-color);
        }

        .signup-btn {
            background-color: var(--primary-color);
            color: white;
            padding: 10px 20px;
            border-radius: 4px;
            transition: background-color 0.2s;
        }

        .signup-btn:hover {
            background-color: #19a463;
        }

        /* Main Content */
        .main-content {
            margin-top: 80px;
            padding: 30px 0;
        }

        /* Breadcrumbs */
        .breadcrumbs {
            display: flex;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .breadcrumbs a {
            color: var(--text-color);
            text-decoration: none;
            margin-right: 10px;
        }

        .breadcrumbs a:hover {
            color: var(--primary-color);
        }

        .breadcrumbs span {
            margin-right: 10px;
            color: var(--text-color);
        }

        /* Gig Details */
        .gig-details {
            display: flex;
            flex-wrap: wrap;
            gap: 30px;
        }

        .gig-left {
            flex: 2;
            min-width: 300px;
        }

        .gig-right {
            flex: 1;
            min-width: 300px;
        }

        .gig-title {
            font-size: 28px;
            margin-bottom: 15px;
            color: var(--secondary-color);
        }

        .gig-seller {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }

        .seller-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            margin-right: 15px;
            object-fit: cover;
        }

        .seller-info h3 {
            font-size: 18px;
            margin-bottom: 5px;
        }

        .seller-info p {
            color: var(--text-color);
            font-size: 14px;
        }

        .gig-rating {
            display: flex;
            align-items: center;
            color: #ffb33e;
            margin-bottom: 10px;
        }

        .gig-rating span {
            margin-left: 5px;
            color: var(--text-color);
        }

        .gig-gallery {
            margin-bottom: 30px;
        }

        .main-image {
            width: 100%;
            height: 400px;
            object-fit: cover;
            border-radius: 8px;
            margin-bottom: 10px;
        }

        .gallery-thumbnails {
            display: flex;
            gap: 10px;
        }

        .thumbnail {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 4px;
            cursor: pointer;
            border: 2px solid transparent;
            transition: border-color 0.2s;
        }

        .thumbnail.active {
            border-color: var(--primary-color);
        }

        .gig-description {
            background-color: white;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }

        .gig-description h2 {
            font-size: 20px;
            margin-bottom: 15px;
        }

        .gig-description p {
            color: var(--text-color);
            margin-bottom: 15px;
        }

        .gig-details-list {
            display: flex;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .gig-detail-item {
            display: flex;
            align-items: center;
            margin-right: 30px;
            margin-bottom: 15px;
        }

        .gig-detail-item i {
            font-size: 18px;
            color: var(--primary-color);
            margin-right: 10px;
        }

        /* Reviews Section */
        .reviews-section {
            background-color: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }

        .reviews-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .reviews-count {
            font-size: 20px;
            font-weight: 600;
        }

        .review-item {
            padding: 20px 0;
            border-bottom: 1px solid var(--border-color);
        }

        .review-item:last-child {
            border-bottom: none;
        }

        .review-header {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }

        .reviewer-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 15px;
            object-fit: cover;
        }

        .reviewer-info h4 {
            font-size: 16px;
            margin-bottom: 5px;
        }

        .reviewer-info .review-date {
            font-size: 14px;
            color: var(--text-color);
        }

        .review-rating {
            color: #ffb33e;
            margin-bottom: 10px;
        }

        .review-content {
            color: var(--text-color);
        }

        /* Order Box */
        .order-box {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            position: sticky;
            top: 100px;
        }

        .order-box-header {
            display: flex;
            justify-content: space-between;
            padding: 20px;
            border-bottom: 1px solid var(--border-color);
        }

        .order-box-header h3 {
            font-size: 20px;
        }

        .order-price {
            font-size: 28px;
            font-weight: 700;
            color: var(--secondary-color);
        }

        .order-details {
            padding: 20px;
        }

        .order-detail-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
        }

        .order-detail-item:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .order-detail-name {
            font-weight: 600;
        }

        .order-detail-value {
            color: var(--text-color);
        }

        .order-form {
            padding: 20px;
            border-top: 1px solid var(--border-color);
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

        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }

        .btn {
            display: block;
            width: 100%;
            padding: 12px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            font-weight: 600;
            text-align: center;
            cursor: pointer;
            transition: background-color 0.2s;
            text-decoration: none;
        }

        .btn:hover {
            background-color: #19a463;
        }

        .btn-contact {
            background-color: transparent;
            color: var(--primary-color);
            border: 1px solid var(--primary-color);
            margin-top: 10px;
        }

        .btn-contact:hover {
            background-color: var(--primary-color);
            color: white;
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

        /* Responsive styles */
        @media (max-width: 768px) {
            .gig-details {
                flex-direction: column;
            }
            
            .main-image {
                height: 300px;
            }
            
            .gig-title {
                font-size: 24px;
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
                        <?php if (isset($_SESSION['user_id']) && $_SESSION['is_seller']): ?>
                            <li><a href="create_gig.php">Create Gig</a></li>
                        <?php endif; ?>
                    </ul>
                </nav>
                <?php if (isset($_SESSION['user_id'])): ?>
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
                <?php else: ?>
                    <div class="auth-buttons">
                        <a href="login.php" class="login-btn">Sign In</a>
                        <a href="register.php" class="signup-btn">Join</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container">
            <!-- Breadcrumbs -->
            <div class="breadcrumbs">
                <a href="index.php">Home</a>
                <span>/</span>
                <a href="search.php?category=<?php echo $gig['category_id']; ?>"><?php echo $category['name']; ?></a>
                <span>/</span>
                <span><?php echo $gig['title']; ?></span>
            </div>
            
            <?php if ($orderSuccess): ?>
                <div class="alert alert-success">
                    <p>Order placed successfully! <a href="orders.php">View your orders</a>.</p>
                </div>
            <?php elseif ($orderError): ?>
                <div class="alert alert-error">
                    <p><?php echo $orderError; ?></p>
                </div>
            <?php endif; ?>
            
            <div class="gig-details">
                <div class="gig-left">
                    <h1 class="gig-title"><?php echo $gig['title']; ?></h1>
                    
                    <div class="gig-seller">
                        <img src="<?php echo $seller['profile_pic'] ?? 'https://www.gravatar.com/avatar/00000000000000000000000000000000?d=mp&f=y'; ?>" alt="<?php echo $seller['username']; ?>" class="seller-avatar">
                        <div class="seller-info">
                            <h3><?php echo $seller['username']; ?></h3>
                            <p><?php echo $seller['location'] ? $seller['location'] : 'Location not specified'; ?></p>
                        </div>
                    </div>
                    
                    <div class="gig-rating">
                        <i class="fas fa-star"></i>
                        <span><?php echo number_format($gig['rating'], 1); ?> (<?php echo $gig['total_reviews']; ?> reviews)</span>
                    </div>
                    
                    <div class="gig-gallery">
                        <img src="<?php echo !empty($gig['image1']) ? $gig['image1'] : 'https://via.placeholder.com/800x400?text=No+Image'; ?>" alt="<?php echo $gig['title']; ?>" class="main-image" id="main-image">
                        
                        <?php if (!empty($gig['image1']) || !empty($gig['image2']) || !empty($gig['image3'])): ?>
                            <div class="gallery-thumbnails">
                                <?php if (!empty($gig['image1'])): ?>
                                    <img src="<?php echo $gig['image1']; ?>" alt="Thumbnail 1" class="thumbnail active" onclick="changeImage(this, '<?php echo $gig['image1']; ?>')">
                                <?php endif; ?>
                                <?php if (!empty($gig['image2'])): ?>
                                    <img src="<?php echo $gig['image2']; ?>" alt="Thumbnail 2" class="thumbnail" onclick="changeImage(this, '<?php echo $gig['image2']; ?>')">
                                <?php endif; ?>
                                <?php if (!empty($gig['image3'])): ?>
                                    <img src="<?php echo $gig['image3']; ?>" alt="Thumbnail 3" class="thumbnail" onclick="changeImage(this, '<?php echo $gig['image3']; ?>')">
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="gig-description">
                        <h2>About This Gig</h2>
                        <p><?php echo nl2br($gig['description']); ?></p>
                        
                        <div class="gig-details-list">
                            <div class="gig-detail-item">
                                <i class="fas fa-clock"></i>
                                <span><?php echo $gig['delivery_time']; ?> day delivery</span>
                            </div>
                            <div class="gig-detail-item">
                                <i class="fas fa-redo"></i>
                                <span>Unlimited revisions</span>
                            </div>
                            <div class="gig-detail-item">
                                <i class="fas fa-tag"></i>
                                <span><?php echo $category['name']; ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="reviews-section">
                        <div class="reviews-header">
                            <h2 class="reviews-count"><?php echo count($reviews); ?> Reviews</h2>
                            <div class="gig-rating">
                                <i class="fas fa-star"></i>
                                <span><?php echo number_format($gig['rating'], 1); ?></span>
                            </div>
                        </div>
                        
                        <?php if (empty($reviews)): ?>
                            <p>No reviews yet.</p>
                        <?php else: ?>
                            <?php foreach ($reviews as $review): ?>
                                <div class="review-item">
                                    <div class="review-header">
                                        <img src="<?php echo $review['profile_pic'] ?? 'https://www.gravatar.com/avatar/00000000000000000000000000000000?d=mp&f=y'; ?>" alt="<?php echo $review['username']; ?>" class="reviewer-avatar">
                                        <div class="reviewer-info">
                                            <h4><?php echo $review['username']; ?></h4>
                                            <div class="review-date"><?php echo date('F j, Y', strtotime($review['created_at'])); ?></div>
                                        </div>
                                    </div>
                                    <div class="review-rating">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star <?php echo $i <= $review['rating'] ? '' : 'far'; ?>"></i>
                                        <?php endfor; ?>
                                    </div>
                                    <div class="review-content">
                                        <?php echo nl2br($review['review']); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="gig-right">
                    <div class="order-box">
                        <div class="order-box-header">
                            <h3>Service Package</h3>
                            <div class="order-price">$<?php echo $gig['price']; ?></div>
                        </div>
                        
                        <div class="order-details">
                            <div class="order-detail-item">
                                <div class="order-detail-name">
                                    <i class="fas fa-clock"></i> Delivery Time
                                </div>
                                <div class="order-detail-value"><?php echo $gig['delivery_time']; ?> days</div>
                            </div>
                            <div class="order-detail-item">
                                <div class="order-detail-name">
                                    <i class="fas fa-redo"></i> Revisions
                                </div>
                                <div class="order-detail-value">Unlimited</div>
                            </div>
                        </div>
                        
                        <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] != $gig['user_id']): ?>
                            <div class="order-form">
                                <form action="gig_detail.php?id=<?php echo $gig_id; ?>" method="POST">
                                    <div class="form-group">
                                        <label for="requirements">Tell the seller what you need</label>
                                        <textarea id="requirements" name="requirements" class="form-control" placeholder="Provide details about what you expect from this service..." required></textarea>
                                    </div>
                                    <button type="submit" name="place_order" class="btn">Continue ($<?php echo $gig['price']; ?>)</button>
                                </form>
                                
                                <a href="chat.php?seller_id=<?php echo $gig['user_id']; ?>" class="btn btn-contact">
                                    <i class="fas fa-comment"></i> Contact Seller
                                </a>
                            </div>
                        <?php elseif (!isset($_SESSION['user_id'])): ?>
                            <div class="order-form">
                                <a href="login.php" class="btn">
                                    <i class="fas fa-sign-in-alt"></i> Sign in to order
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Gallery image switching
        function changeImage(thumbnail, imageUrl) {
            // Update main image
            document.getElementById('main-image').src = imageUrl;
            
            // Update active thumbnail
            const thumbnails = document.querySelectorAll('.thumbnail');
            thumbnails.forEach(thumb => {
                thumb.classList.remove('active');
            });
            
            thumbnail.classList.add('active');
        }
    </script>
</body>
</html>
