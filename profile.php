<?php
session_start();
require_once 'db.php';

// Check if user is logged in
requireLogin();

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Get user information
$user = getUserInfo($user_id, $conn);

// Get user's gigs if they are a seller
$userGigs = [];
if ($user['is_seller']) {
    $userGigs = getUserGigs($user_id, $conn);
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $fullName = sanitize($_POST['full_name']);
    $bio = sanitize($_POST['bio']);
    $skills = sanitize($_POST['skills']);
    $location = sanitize($_POST['location']);
    
    // Update user profile
    $stmt = $conn->prepare("UPDATE users SET full_name = :full_name, bio = :bio, skills = :skills, location = :location WHERE id = :user_id");
    
    $stmt->bindParam(':full_name', $fullName);
    $stmt->bindParam(':bio', $bio);
    $stmt->bindParam(':skills', $skills);
    $stmt->bindParam(':location', $location);
    $stmt->bindParam(':user_id', $user_id);
    
    if ($stmt->execute()) {
        $success_message = "Profile updated successfully!";
        // Update user info
        $user = getUserInfo($user_id, $conn);
    } else {
        $error_message = "Failed to update profile.";
    }
}

// Handle tab navigation
$currentTab = isset($_GET['tab']) ? $_GET['tab'] : 'profile';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - FiverrClone</title>
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

        .profile-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }

        .profile-header {
            display: flex;
            align-items: center;
            padding: 30px;
            border-bottom: 1px solid var(--border-color);
        }

        .profile-pic {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 20px;
        }

        .profile-info h1 {
            font-size: 24px;
            margin-bottom: 5px;
        }

        .profile-info p {
            color: var(--text-color);
        }

        .profile-info .seller-badge {
            display: inline-block;
            background-color: var(--primary-color);
            color: white;
            font-size: 12px;
            padding: 3px 8px;
            border-radius: 4px;
            margin-left: 10px;
        }

        .profile-tabs {
            display: flex;
            border-bottom: 1px solid var(--border-color);
        }

        .profile-tab {
            padding: 15px 20px;
            font-weight: 600;
            cursor: pointer;
            transition: color 0.2s, border-bottom 0.2s;
            border-bottom: 2px solid transparent;
        }

        .profile-tab.active {
            color: var(--primary-color);
            border-bottom: 2px solid var(--primary-color);
        }

        .tab-content {
            padding: 30px;
        }

        .tab-pane {
            display: none;
        }

        .tab-pane.active {
            display: block;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
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
            display: inline-block;
            padding: 10px 20px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .btn:hover {
            background-color: #19a463;
        }

        .btn-outline {
            background-color: transparent;
            color: var(--primary-color);
            border: 1px solid var(--primary-color);
        }

        .btn-outline:hover {
            background-color: var(--primary-color);
            color: white;
        }

        /* Gigs Grid */
        .gigs-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
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
            height: 150px;
            object-fit: cover;
        }

        .gig-content {
            padding: 15px;
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

        .gig-price {
            font-weight: 700;
            font-size: 18px;
            color: var(--secondary-color);
            margin-bottom: 10px;
        }

        .gig-actions {
            display: flex;
            justify-content: space-between;
        }

        .gig-action-btn {
            padding: 5px 10px;
            font-size: 14px;
            border-radius: 4px;
            text-decoration: none;
        }

        .edit-btn {
            background-color: #f0f0f0;
            color: var(--secondary-color);
        }

        .delete-btn {
            background-color: #ffebee;
            color: #c62828;
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

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 30px;
        }

        .empty-state i {
            font-size: 60px;
            color: var(--border-color);
            margin-bottom: 20px;
        }

        .empty-state h3 {
            font-size: 20px;
            margin-bottom: 10px;
        }

        .empty-state p {
            color: var(--text-color);
            margin-bottom: 20px;
        }

        @media (max-width: 768px) {
            .profile-header {
                flex-direction: column;
                text-align: center;
            }

            .profile-pic {
                margin-right: 0;
                margin-bottom: 20px;
            }

            .profile-tabs {
                overflow-x: auto;
                white-space: nowrap;
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
                        <img src="<?php echo $user['profile_pic'] ?? 'https://www.gravatar.com/avatar/00000000000000000000000000000000?d=mp&f=y'; ?>" alt="Profile" class="user-avatar">
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
            <div class="profile-container">
                <div class="profile-header">
                    <img src="<?php echo $user['profile_pic'] ?? 'https://www.gravatar.com/avatar/00000000000000000000000000000000?d=mp&f=y'; ?>" alt="Profile" class="profile-pic">
                    <div class="profile-info">
                        <h1><?php echo $user['full_name'] ? $user['full_name'] : $user['username']; ?>
                            <?php if ($user['is_seller']): ?>
                                <span class="seller-badge">Seller</span>
                            <?php endif; ?>
                        </h1>
                        <p><i class="fas fa-map-marker-alt"></i> <?php echo $user['location'] ? $user['location'] : 'Location not set'; ?></p>
                        <p><i class="fas fa-calendar-alt"></i> Member since <?php echo date('F Y', strtotime($user['joined_date'])); ?></p>
                    </div>
                </div>

                <div class="profile-tabs">
                    <div class="profile-tab <?php echo $currentTab === 'profile' ? 'active' : ''; ?>" onclick="changeTab('profile')">Profile</div>
                    <?php if ($user['is_seller']): ?>
                        <div class="profile-tab <?php echo $currentTab === 'gigs' ? 'active' : ''; ?>" onclick="changeTab('gigs')">My Gigs</div>
                    <?php endif; ?>
                    <div class="profile-tab <?php echo $currentTab === 'reviews' ? 'active' : ''; ?>" onclick="changeTab('reviews')">Reviews</div>
                </div>

                <div class="tab-content">
                    <!-- Profile Tab -->
                    <div class="tab-pane <?php echo $currentTab === 'profile' ? 'active' : ''; ?>" id="profile-tab">
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

                        <form action="profile.php" method="POST">
                            <div class="form-group">
                                <label for="full_name">Full Name</label>
                                <input type="text" id="full_name" name="full_name" class="form-control" value="<?php echo $user['full_name']; ?>">
                            </div>

                            <div class="form-group">
                                <label for="bio">Bio</label>
                                <textarea id="bio" name="bio" class="form-control"><?php echo $user['bio']; ?></textarea>
                            </div>

                            <div class="form-group">
                                <label for="skills">Skills (comma separated)</label>
                                <input type="text" id="skills" name="skills" class="form-control" value="<?php echo $user['skills']; ?>">
                            </div>

                            <div class="form-group">
                                <label for="location">Location</label>
                                <input type="text" id="location" name="location" class="form-control" value="<?php echo $user['location']; ?>">
                            </div>

                            <button type="submit" name="update_profile" class="btn">Update Profile</button>
                        </form>
                    </div>

                    <!-- Gigs Tab -->
                    <div class="tab-pane <?php echo $currentTab === 'gigs' ? 'active' : ''; ?>" id="gigs-tab">
                        <?php if ($user['is_seller']): ?>
                            <?php if (empty($userGigs)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-list"></i>
                                    <h3>No Gigs Yet</h3>
                                    <p>You haven't created any gigs yet. Start selling your services!</p>
                                    <a href="create_gig.php" class="btn">Create a Gig</a>
                                </div>
                            <?php else: ?>
                                <a href="create_gig.php" class="btn" style="margin-bottom: 20px;">Create New Gig</a>
                                <div class="gigs-grid">
                                    <?php foreach($userGigs as $gig): ?>
                                        <div class="gig-card">
                                            <img src="<?php echo !empty($gig['image1']) ? $gig['image1'] : 'https://via.placeholder.com/300x200?text=No+Image'; ?>" alt="<?php echo $gig['title']; ?>" class="gig-image">
                                            <div class="gig-content">
                                                <h3 class="gig-title"><?php echo $gig['title']; ?></h3>
                                                <div class="gig-price">$<?php echo $gig['price']; ?></div>
                                                <div class="gig-actions">
                                                    <a href="edit_gig.php?id=<?php echo $gig['id']; ?>" class="gig-action-btn edit-btn">Edit</a>
                                                    <a href="#" class="gig-action-btn delete-btn" onclick="deleteGig(<?php echo $gig['id']; ?>)">Delete</a>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Reviews Tab -->
                    <div class="tab-pane <?php echo $currentTab === 'reviews' ? 'active' : ''; ?>" id="reviews-tab">
                        <div class="empty-state">
                            <i class="fas fa-star"></i>
                            <h3>No Reviews Yet</h3>
                            <p>You haven't received any reviews yet.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function changeTab(tabName) {
            // Update URL without refreshing page
            const url = new URL(window.location.href);
            url.searchParams.set('tab', tabName);
            window.history.pushState({}, '', url);
            
            // Update active tab
            document.querySelectorAll('.profile-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelectorAll('.tab-pane').forEach(pane => {
                pane.classList.remove('active');
            });
            
            document.querySelector(`.profile-tab:nth-child(${tabName === 'profile' ? 1 : tabName === 'gigs' ? 2 : 3})`).classList.add('active');
            document.getElementById(`${tabName}-tab`).classList.add('active');
        }

        function deleteGig(gigId) {
            if (confirm("Are you sure you want to delete this gig? This action cannot be undone.")) {
                window.location.href = `delete_gig.php?id=${gigId}`;
            }
        }
    </script>
</body>
</html>
