<?php
session_start();
require_once 'db.php';

// Get featured gigs
$featuredGigs = getFeaturedGigs($conn);

// Get all categories
$categories = getCategories($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FiverrClone - Freelance Services Marketplace</title>
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
            background-color: #fff;
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

        /* Hero Section */
        .hero {
            background-image: linear-gradient(to right, rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.3)), url('https://fiverr-res.cloudinary.com/images/q_auto,f_auto/gigs/129325364/original/afaddcb9d95bc6ee589a0c0d238c038195395e05/create-a-responsive-website-using-html-css-javascript.png');
            background-size: cover;
            background-position: center;
            color: white;
            height: 500px;
            display: flex;
            align-items: center;
            margin-top: 65px;
        }

        .hero-content {
            max-width: 600px;
        }

        .hero h1 {
            font-size: 42px;
            margin-bottom: 20px;
        }

        .hero p {
            font-size: 20px;
            margin-bottom: 30px;
        }

        .search-form {
            display: flex;
            max-width: 500px;
        }

        .search-form input {
            flex: 1;
            padding: 15px;
            border: none;
            border-radius: 4px 0 0 4px;
            font-size: 16px;
        }

        .search-form button {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 0 20px;
            border-radius: 0 4px 4px 0;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.2s;
        }

        .search-form button:hover {
            background-color: #19a463;
        }

        /* Categories Section */
        .categories {
            padding: 60px 0;
            background-color: var(--light-gray);
        }

        .section-title {
            text-align: center;
            margin-bottom: 40px;
            font-size: 28px;
            color: var(--secondary-color);
        }

        .categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 30px;
        }

        .category-card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            text-align: center;
            padding: 25px;
            transition: transform 0.3s;
            cursor: pointer;
        }

        .category-card:hover {
            transform: translateY(-5px);
        }

        .category-icon {
            font-size: 40px;
            color: var(--primary-color);
            margin-bottom: 15px;
        }

        .category-name {
            font-weight: 600;
            font-size: 18px;
        }

        /* Featured Gigs Section */
        .featured-gigs {
            padding: 60px 0;
        }

        .gigs-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 30px;
        }

        .gig-card {
            border: 1px solid var(--border-color);
            border-radius: 8px;
            overflow: hidden;
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .gig-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        .gig-image {
            width: 100%;
            height: 180px;
            object-fit: cover;
        }

        .gig-content {
            padding: 15px;
        }

        .gig-seller {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }

        .seller-avatar {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            margin-right: 10px;
            object-fit: cover;
        }

        .seller-name {
            font-weight: 600;
            color: var(--secondary-color);
        }

        .gig-title {
            font-size: 16px;
            margin-bottom: 10px;
            color: var(--secondary-color);
            text-decoration: none;
            display: block;
            height: 40px;
            overflow: hidden;
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

        .gig-price {
            font-weight: 700;
            font-size: 18px;
            color: var(--secondary-color);
        }

        /* How it Works Section */
        .how-it-works {
            padding: 60px 0;
            background-color: var(--light-gray);
        }

        .steps-container {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            margin-top: 30px;
        }

        .step {
            flex: 1;
            min-width: 250px;
            text-align: center;
            padding: 20px;
            margin: 10px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }

        .step-number {
            display: inline-block;
            width: 40px;
            height: 40px;
            background-color: var(--primary-color);
            color: white;
            border-radius: 50%;
            line-height: 40px;
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 15px;
        }

        .step-title {
            font-size: 20px;
            margin-bottom: 10px;
            color: var(--secondary-color);
        }

        .step-desc {
            color: var(--text-color);
        }

        /* Footer */
        footer {
            background-color: var(--secondary-color);
            color: white;
            padding: 50px 0 20px;
        }

        .footer-content {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            margin-bottom: 40px;
        }

        .footer-column {
            flex: 1;
            min-width: 200px;
            margin-bottom: 20px;
        }

        .footer-column h4 {
            font-size: 18px;
            margin-bottom: 20px;
            font-weight: 600;
        }

        .footer-column ul {
            list-style: none;
        }

        .footer-column ul li {
            margin-bottom: 10px;
        }

        .footer-column ul li a {
            color: #ccc;
            text-decoration: none;
            transition: color 0.2s;
        }

        .footer-column ul li a:hover {
            color: white;
        }

        .footer-bottom {
            border-top: 1px solid #444;
            padding-top: 20px;
            text-align: center;
            color: #ccc;
        }

        .social-icons {
            display: flex;
            margin-top: 20px;
        }

        .social-icons a {
            color: white;
            margin-right: 15px;
            font-size: 18px;
        }

        /* Responsive styles */
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                text-align: center;
            }

            nav ul {
                margin: 20px 0;
            }

            nav ul li {
                margin: 0 10px;
            }

            .hero {
                height: auto;
                padding: 80px 0;
            }

            .hero h1 {
                font-size: 32px;
            }

            .search-form {
                flex-direction: column;
            }

            .search-form input {
                border-radius: 4px;
                margin-bottom: 10px;
            }

            .search-form button {
                border-radius: 4px;
                padding: 15px;
            }

            .steps-container {
                flex-direction: column;
            }

            .footer-content {
                flex-direction: column;
            }
        }

        /* User menu dropdown styles */
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

    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <div class="hero-content">
                <h1>Find the perfect freelance services for your business</h1>
                <p>Millions of people use FiverrClone to turn their ideas into reality.</p>
                <form action="search.php" method="GET" class="search-form">
                    <input type="text" name="query" placeholder="Search for any service...">
                    <button type="submit"><i class="fas fa-search"></i></button>
                </form>
            </div>
        </div>
    </section>

    <!-- Categories Section -->
    <section class="categories">
        <div class="container">
            <h2 class="section-title">Popular Professional Services</h2>
            <div class="categories-grid">
                <?php foreach($categories as $category): ?>
                <a href="search.php?category=<?php echo $category['id']; ?>">
                    <div class="category-card">
                        <div class="category-icon">
                            <i class="fas fa-<?php echo $category['icon']; ?>"></i>
                        </div>
                        <h3 class="category-name"><?php echo $category['name']; ?></h3>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Featured Gigs Section -->
    <section class="featured-gigs">
        <div class="container">
            <h2 class="section-title">Featured Gigs</h2>
            <div class="gigs-grid">
                <?php foreach($featuredGigs as $gig): ?>
                <div class="gig-card">
                    <img src="<?php echo !empty($gig['image1']) ? $gig['image1'] : 'https://via.placeholder.com/300x200?text=No+Image'; ?>" alt="<?php echo $gig['title']; ?>" class="gig-image">
                    <div class="gig-content">
                        <div class="gig-seller">
                            <img src="<?php echo !empty($gig['profile_pic']) ? $gig['profile_pic'] : 'https://www.gravatar.com/avatar/00000000000000000000000000000000?d=mp&f=y'; ?>" alt="<?php echo $gig['username']; ?>" class="seller-avatar">
                            <span class="seller-name"><?php echo $gig['username']; ?></span>
                        </div>
                        <a href="gig_detail.php?id=<?php echo $gig['id']; ?>" class="gig-title"><?php echo $gig['title']; ?></a>
                        <div class="gig-rating">
                            <i class="fas fa-star"></i>
                            <span><?php echo number_format($gig['rating'], 1); ?> (<?php echo $gig['total_reviews']; ?>)</span>
                        </div>
                        <div class="gig-price">From $<?php echo $gig['price']; ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- How It Works Section -->
    <section class="how-it-works">
        <div class="container">
            <h2 class="section-title">How FiverrClone Works</h2>
            <div class="steps-container">
                <div class="step">
                    <div class="step-number">1</div>
                    <h3 class="step-title">Find the Perfect Service</h3>
                    <p class="step-desc">Browse through the diverse range of services offered by talented professionals.</p>
                </div>
                <div class="step">
                    <div class="step-number">2</div>
                    <h3 class="step-title">Place an Order</h3>
                    <p class="step-desc">Communicate your needs to the seller and finalize your order details.</p>
                </div>
                <div class="step">
                    <div class="step-number">3</div>
                    <h3 class="step-title">Receive & Review</h3>
                    <p class="step-desc">Get your delivery on time and provide feedback to help the community.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-column">
                    <h4>Categories</h4>
                    <ul>
                        <?php foreach(array_slice($categories, 0, 5) as $category): ?>
                            <li><a href="search.php?category=<?php echo $category['id']; ?>"><?php echo $category['name']; ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="footer-column">
                    <h4>About</h4>
                    <ul>
                        <li><a href="#">Careers</a></li>
                        <li><a href="#">Press & News</a></li>
                        <li><a href="#">Partnerships</a></li>
                        <li><a href="#">Privacy Policy</a></li>
                        <li><a href="#">Terms of Service</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h4>Support</h4>
                    <ul>
                        <li><a href="#">Help & Support</a></li>
                        <li><a href="#">Trust & Safety</a></li>
                        <li><a href="#">Selling on FiverrClone</a></li>
                        <li><a href="#">Buying on FiverrClone</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h4>Community</h4>
                    <ul>
                        <li><a href="#">Events</a></li>
                        <li><a href="#">Blog</a></li>
                        <li><a href="#">Forum</a></li>
                        <li><a href="#">Community Standards</a></li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> FiverrClone. All rights reserved.</p>
                <div class="social-icons">
                    <a href="#"><i class="fab fa-facebook-f"></i></a>
                    <a href="#"><i class="fab fa-twitter"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                    <a href="#"><i class="fab fa-linkedin-in"></i></a>
                    <a href="#"><i class="fab fa-pinterest-p"></i></a>
                </div>
            </div>
        </div>
    </footer>

    <script>
        // JavaScript for sticky header
        window.addEventListener('scroll', function() {
            const header = document.querySelector('header');
            header.classList.toggle('sticky', window.scrollY > 0);
        });
    </script>
</body>
</html>
