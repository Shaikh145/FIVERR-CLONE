<?php
session_start();
require_once 'db.php';

// Get categories for filter
$categories = getCategories($conn);

// Prepare search query
$query = isset($_GET['query']) ? sanitize($_GET['query']) : '';
$category_id = isset($_GET['category']) ? intval($_GET['category']) : 0;
$min_price = isset($_GET['min_price']) ? floatval($_GET['min_price']) : 0;
$max_price = isset($_GET['max_price']) ? floatval($_GET['max_price']) : 1000; // Default max
$min_rating = isset($_GET['min_rating']) ? floatval($_GET['min_rating']) : 0;
$sort_by = isset($_GET['sort_by']) ? sanitize($_GET['sort_by']) : 'newest';

// Build the SQL query
$sql = "SELECT g.*, u.username, u.profile_pic FROM gigs g 
        JOIN users u ON g.user_id = u.id 
        WHERE 1=1";
$params = [];

// Add search conditions
if (!empty($query)) {
    $sql .= " AND (g.title LIKE :query OR g.description LIKE :query)";
    $params[':query'] = '%' . $query . '%';
}

if ($category_id > 0) {
    $sql .= " AND g.category_id = :category_id";
    $params[':category_id'] = $category_id;
}

if ($min_price > 0) {
    $sql .= " AND g.price >= :min_price";
    $params[':min_price'] = $min_price;
}

if ($max_price > 0) {
    $sql .= " AND g.price <= :max_price";
    $params[':max_price'] = $max_price;
}

if ($min_rating > 0) {
    $sql .= " AND g.rating >= :min_rating";
    $params[':min_rating'] = $min_rating;
}

// Add sorting
switch ($sort_by) {
    case 'price_asc':
        $sql .= " ORDER BY g.price ASC";
        break;
    case 'price_desc':
        $sql .= " ORDER BY g.price DESC";
        break;
    case 'rating_desc':
        $sql .= " ORDER BY g.rating DESC";
        break;
    default: // newest
        $sql .= " ORDER BY g.created_at DESC";
}

// Execute the query
$stmt = $conn->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$gigs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// If searching by category, get category name
$category_name = "";
if ($category_id > 0) {
    $stmt = $conn->prepare("SELECT name FROM categories WHERE id = :category_id");
    $stmt->bindParam(':category_id', $category_id);
    $stmt->execute();
    $category = $stmt->fetch(PDO::FETCH_ASSOC);
    $category_name = $category ? $category['name'] : "";
}

// Get min and max price for the slider
$stmt = $conn->prepare("SELECT MIN(price) as min_price, MAX(price) as max_price FROM gigs");
$stmt->execute();
$price_range = $stmt->fetch(PDO::FETCH_ASSOC);
$db_min_price = $price_range['min_price'] ?? 5;
$db_max_price = $price_range['max_price'] ?? 1000;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Gigs - FiverrClone</title>
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

        /* Search Header */
        .search-header {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
        }

        .search-form {
            display: flex;
            gap: 10px;
        }

        .search-input {
            flex: 1;
            padding: 12px 15px;
            font-size: 16px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            transition: border-color 0.2s;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--primary-color);
        }

        .search-btn {
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

        .search-btn:hover {
            background-color: #19a463;
        }

        /* Search Results Layout */
        .search-results {
            display: flex;
            gap: 30px;
        }

        .filters-column {
            width: 250px;
            flex-shrink: 0;
        }

        .results-column {
            flex: 1;
        }

        /* Filters */
        .filters {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }

        .filters-header {
            padding: 15px 20px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .filters-header h3 {
            font-size: 18px;
        }

        .clear-filters {
            color: var(--primary-color);
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
        }

        .filter-section {
            padding: 15px 20px;
            border-bottom: 1px solid var(--border-color);
        }

        .filter-section:last-child {
            border-bottom: none;
        }

        .filter-section h4 {
            font-size: 16px;
            margin-bottom: 15px;
        }

        .filter-options label {
            display: block;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            cursor: pointer;
        }

        .filter-options input[type="checkbox"],
        .filter-options input[type="radio"] {
            margin-right: 10px;
        }

        .range-slider {
            margin-top: 15px;
        }

        .range-inputs {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }

        .range-input {
            width: 80px;
            padding: 8px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
        }

        .filter-btn {
            display: block;
            width: 100%;
            padding: 10px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s;
            margin-top: 15px;
        }

        .filter-btn:hover {
            background-color: #19a463;
        }

        /* Results Header */
        .results-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .results-count {
            font-size: 18px;
            font-weight: 600;
        }

        .results-count span {
            color: var(--primary-color);
        }

        .sort-options {
            display: flex;
            align-items: center;
        }

        .sort-options label {
            margin-right: 10px;
            font-weight: 600;
        }

        .sort-select {
            padding: 8px 12px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 14px;
            background-color: white;
        }

        /* Gigs Grid */
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
            background-color: white;
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

        /* No Results */
        .no-results {
            text-align: center;
            padding: 50px 0;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }

        .no-results i {
            font-size: 60px;
            color: var(--border-color);
            margin-bottom: 20px;
        }

        .no-results h3 {
            font-size: 24px;
            margin-bottom: 10px;
        }

        .no-results p {
            color: var(--text-color);
            margin-bottom: 20px;
        }

        /* Responsive styles */
        @media (max-width: 768px) {
            .search-results {
                flex-direction: column;
            }

            .filters-column {
                width: 100%;
                margin-bottom: 20px;
            }

            .gigs-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
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
                        <li><a href="search.php" class="active">Explore</a></li>
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
            <!-- Search Header -->
            <div class="search-header">
                <form action="search.php" method="GET" class="search-form">
                    <input type="text" name="query" class="search-input" placeholder="Search for any service..." value="<?php echo $query; ?>">
                    <button type="submit" class="search-btn"><i class="fas fa-search"></i> Search</button>
                </form>
            </div>
            
            <!-- Search Results -->
            <div class="search-results">
                <!-- Filters Column -->
                <div class="filters-column">
                    <div class="filters">
                        <div class="filters-header">
                            <h3>Filters</h3>
                            <a href="search.php" class="clear-filters">Clear All</a>
                        </div>
                        
                        <form action="search.php" method="GET" id="filter-form">
                            <!-- Keep the search query if it exists -->
                            <?php if (!empty($query)): ?>
                                <input type="hidden" name="query" value="<?php echo $query; ?>">
                            <?php endif; ?>
                            
                            <!-- Categories Filter -->
                            <div class="filter-section">
                                <h4>Categories</h4>
                                <div class="filter-options">
                                    <?php foreach ($categories as $cat): ?>
                                        <label>
                                            <input type="radio" name="category" value="<?php echo $cat['id']; ?>" <?php echo ($category_id == $cat['id']) ? 'checked' : ''; ?>>
                                            <?php echo $cat['name']; ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <!-- Price Range Filter -->
                            <div class="filter-section">
                                <h4>Price Range</h4>
                                <div class="range-slider">
                                    <div class="range-inputs">
                                        <input type="number" name="min_price" class="range-input" placeholder="Min" value="<?php echo $min_price > 0 ? $min_price : ''; ?>" min="<?php echo $db_min_price; ?>">
                                        <span>to</span>
                                        <input type="number" name="max_price" class="range-input" placeholder="Max" value="<?php echo $max_price < 1000 ? $max_price : ''; ?>" max="<?php echo $db_max_price; ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Seller Rating Filter -->
                            <div class="filter-section">
                                <h4>Seller Rating</h4>
                                <div class="filter-options">
                                    <label>
                                        <input type="radio" name="min_rating" value="4.5" <?php echo ($min_rating == 4.5) ? 'checked' : ''; ?>>
                                        4.5 & up
                                    </label>
                                    <label>
                                        <input type="radio" name="min_rating" value="4" <?php echo ($min_rating == 4) ? 'checked' : ''; ?>>
                                        4.0 & up
                                    </label>
                                    <label>
                                        <input type="radio" name="min_rating" value="3" <?php echo ($min_rating == 3) ? 'checked' : ''; ?>>
                                        3.0 & up
                                    </label>
                                    <label>
                                        <input type="radio" name="min_rating" value="0" <?php echo ($min_rating == 0 || $min_rating == '') ? 'checked' : ''; ?>>
                                        Any rating
                                    </label>
                                </div>
                            </div>
                            
                            <div class="filter-section">
                                <button type="submit" class="filter-btn">Apply Filters</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Results Column -->
                <div class="results-column">
                    <div class="results-header">
                        <div class="results-count">
                            <?php if (!empty($category_name)): ?>
                                <h2><?php echo $category_name; ?></h2>
                            <?php endif; ?>
                            
                            <?php if (!empty($query)): ?>
                                <h2>Results for "<?php echo $query; ?>"</h2>
                            <?php endif; ?>
                            
                            <?php if (empty($category_name) && empty($query)): ?>
                                <h2>All Services</h2>
                            <?php endif; ?>
                            
                            <p><?php echo count($gigs); ?> services available</p>
                        </div>
                        
                        <div class="sort-options">
                            <label for="sort-select">Sort by:</label>
                            <select id="sort-select" class="sort-select" onchange="updateSort(this.value)">
                                <option value="newest" <?php echo ($sort_by == 'newest') ? 'selected' : ''; ?>>Newest</option>
                                <option value="price_asc" <?php echo ($sort_by == 'price_asc') ? 'selected' : ''; ?>>Price (Low to High)</option>
                                <option value="price_desc" <?php echo ($sort_by == 'price_desc') ? 'selected' : ''; ?>>Price (High to Low)</option>
                                <option value="rating_desc" <?php echo ($sort_by == 'rating_desc') ? 'selected' : ''; ?>>Rating</option>
                            </select>
                        </div>
                    </div>
                    
                    <?php if (empty($gigs)): ?>
                        <div class="no-results">
                            <i class="fas fa-search"></i>
                            <h3>No services found</h3>
                            <p>Try adjusting your search or filter settings</p>
                        </div>
                    <?php else: ?>
                        <div class="gigs-grid">
                            <?php foreach ($gigs as $gig): ?>
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
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Update sort order
        function updateSort(sortValue) {
            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('sort_by', sortValue);
            window.location.href = currentUrl.toString();
        }
        
        // Update filter form when radio buttons are clicked
        document.querySelectorAll('input[type="radio"]').forEach(radio => {
            radio.addEventListener('change', function() {
                if (this.checked) {
                    const name = this.getAttribute('name');
                    const value = this.value;
                    
                    // If we're changing category, remove query string
                    if (name === 'category' && value !== '<?php echo $category_id; ?>') {
                        document.querySelector('input[name="query"]')?.remove();
                    }
                }
            });
        });
    </script>
</body>
</html>
