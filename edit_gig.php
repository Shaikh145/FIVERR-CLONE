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

// Get gig details
$stmt = $conn->prepare("SELECT * FROM gigs WHERE id = :gig_id AND user_id = :user_id");
$stmt->bindParam(':gig_id', $gig_id);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();

if ($stmt->rowCount() === 0) {
    echo "<script>window.location.href = 'profile.php?tab=gigs';</script>";
    exit;
}

$gig = $stmt->fetch(PDO::FETCH_ASSOC);

// Get all categories
$categories = getCategories($conn);

$errors = [];
$success = false;

// Process gig update form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $title = sanitize($_POST['title']);
    $description = sanitize($_POST['description']);
    $category_id = intval($_POST['category_id']);
    $price = floatval($_POST['price']);
    $delivery_time = intval($_POST['delivery_time']);
    
    // Image placeholders
    $image1 = isset($_POST['image1']) ? sanitize($_POST['image1']) : '';
    $image2 = isset($_POST['image2']) ? sanitize($_POST['image2']) : '';
    $image3 = isset($_POST['image3']) ? sanitize($_POST['image3']) : '';
    
    // Validate form data
    if (empty($title)) {
        $errors[] = "Title is required";
    } elseif (strlen($title) < 5 || strlen($title) > 100) {
        $errors[] = "Title must be between 5 and 100 characters";
    }
    
    if (empty($description)) {
        $errors[] = "Description is required";
    } elseif (strlen($description) < 50) {
        $errors[] = "Description must be at least 50 characters";
    }
    
    if ($category_id <= 0) {
        $errors[] = "Please select a category";
    }
    
    if ($price <= 0) {
        $errors[] = "Price must be greater than 0";
    }
    
    if ($delivery_time <= 0) {
        $errors[] = "Delivery time must be greater than 0 days";
    }
    
    // If no errors, update the gig
    if (empty($errors)) {
        $stmt = $conn->prepare("UPDATE gigs SET title = :title, description = :description, category_id = :category_id, 
                                price = :price, delivery_time = :delivery_time, image1 = :image1, image2 = :image2, image3 = :image3 
                                WHERE id = :gig_id AND user_id = :user_id");
        
        $stmt->bindParam(':title', $title);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':category_id', $category_id);
        $stmt->bindParam(':price', $price);
        $stmt->bindParam(':delivery_time', $delivery_time);
        $stmt->bindParam(':image1', $image1);
        $stmt->bindParam(':image2', $image2);
        $stmt->bindParam(':image3', $image3);
        $stmt->bindParam(':gig_id', $gig_id);
        $stmt->bindParam(':user_id', $user_id);
        
        if ($stmt->execute()) {
            $success = true;
            // Update gig variable with new data
            $gig['title'] = $title;
            $gig['description'] = $description;
            $gig['category_id'] = $category_id;
            $gig['price'] = $price;
            $gig['delivery_time'] = $delivery_time;
            $gig['image1'] = $image1;
            $gig['image2'] = $image2;
            $gig['image3'] = $image3;
        } else {
            $errors[] = "Something went wrong. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Gig - FiverrClone</title>
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

        .gig-form-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            padding: 30px;
        }

        .form-section {
            margin-bottom: 30px;
        }

        .form-section h3 {
            font-size: 20px;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border-color);
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
            min-height: 150px;
            resize: vertical;
        }

        .select-control {
            width: 100%;
            padding: 12px 15px;
            font-size: 16px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            background-color: white;
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 15px center;
            background-size: 15px;
        }

        .form-row {
            display: flex;
            gap: 20px;
        }

        .form-row .form-group {
            flex: 1;
        }

        .image-uploader {
            display: flex;
            gap: 15px;
            margin-top: 10px;
        }

        .image-preview {
            width: 100px;
            height: 100px;
            border: 2px dashed var(--border-color);
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            overflow: hidden;
            position: relative;
        }

        .image-preview i {
            font-size: 24px;
            color: var(--border-color);
        }

        .image-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            position: absolute;
            top: 0;
            left: 0;
        }

        .btn {
            display: inline-block;
            padding: 12px 25px;
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

        .form-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
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
            .form-row {
                flex-direction: column;
                gap: 0;
            }

            .image-uploader {
                flex-wrap: wrap;
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
                        <li><a href="create_gig.php">Create Gig</a></li>
                    </ul>
                </nav>
                <div class="user-menu">
                    <div class="user-profile">
                        <img src="<?php echo $_SESSION['profile_pic'] ?? 'https://www.gravatar.com/avatar/00000000000000000000000000000000?d=mp&f=y'; ?>" alt="Profile" class="user-avatar">
                        <span><?php echo $_SESSION['username']; ?> <i class="fas fa-chevron-down"></i></span>
                    </div>
                    <div class="dropdown-content">
                        <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
                        <a href="profile.php?tab=gigs"><i class="fas fa-list"></i> My Gigs</a>
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
            <h1 class="page-title">Edit Gig</h1>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <p>Your gig has been updated successfully! <a href="gig_detail.php?id=<?php echo $gig_id; ?>">View your gig</a> or <a href="profile.php?tab=gigs">go to your gigs</a>.</p>
                </div>
            <?php elseif (!empty($errors)): ?>
                <div class="alert alert-error">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo $error; ?></li><?php
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

// Get gig information
$stmt = $conn->prepare("SELECT * FROM gigs WHERE id = :gig_id AND user_id = :user_id");
$stmt->bindParam(':gig_id', $gig_id);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();

// Check if gig exists and belongs to the user
if ($stmt->rowCount() === 0) {
    echo "<script>window.location.href = 'profile.php?tab=gigs';</script>";
    exit;
}

$gig = $stmt->fetch(PDO::FETCH_ASSOC);

// Get all categories
$categories = getCategories($conn);

$errors = [];
$success = false;

// Process gig update form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $title = sanitize($_POST['title']);
    $description = sanitize($_POST['description']);
    $category_id = intval($_POST['category_id']);
    $price = floatval($_POST['price']);
    $delivery_time = intval($_POST['delivery_time']);
    
    // Image placeholders
    $image1 = isset($_POST['image1']) ? sanitize($_POST['image1']) : '';
    $image2 = isset($_POST['image2']) ? sanitize($_POST['image2']) : '';
    $image3 = isset($_POST['image3']) ? sanitize($_POST['image3']) : '';
    
    // Validate form data
    if (empty($title)) {
        $errors[] = "Title is required";
    } elseif (strlen($title) < 5 || strlen($title) > 100) {
        $errors[] = "Title must be between 5 and 100 characters";
    }
    
    if (empty($description)) {
        $errors[] = "Description is required";
    } elseif (strlen($description) < 50) {
        $errors[] = "Description must be at least 50 characters";
    }
    
    if ($category_id <= 0) {
        $errors[] = "Please select a category";
    }
    
    if ($price <= 0) {
        $errors[] = "Price must be greater than 0";
    }
    
    if ($delivery_time <= 0) {
        $errors[] = "Delivery time must be greater than 0 days";
    }
    
    // If no errors, update the gig
    if (empty($errors)) {
        $stmt = $conn->prepare("UPDATE gigs SET title = :title, description = :description, category_id = :category_id, 
                              price = :price, delivery_time = :delivery_time, image1 = :image1, image2 = :image2, image3 = :image3 
                              WHERE id = :gig_id AND user_id = :user_id");
        
        $stmt->bindParam(':title', $title);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':category_id', $category_id);
        $stmt->bindParam(':price', $price);
        $stmt->bindParam(':delivery_time', $delivery_time);
        $stmt->bindParam(':image1', $image1);
        $stmt->bindParam(':image2', $image2);
        $stmt->bindParam(':image3', $image3);
        $stmt->bindParam(':gig_id', $gig_id);
        $stmt->bindParam(':user_id', $user_id);
        
        if ($stmt->execute()) {
            $success = true;
            // Update gig data
            $gig['title'] = $title;
            $gig['description'] = $description;
            $gig['category_id'] = $category_id;
            $gig['price'] = $price;
            $gig['delivery_time'] = $delivery_time;
            $gig['image1'] = $image1;
            $gig['image2'] = $image2;
            $gig['image3'] = $image3;
        } else {
            $errors[] = "Something went wrong. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Gig - FiverrClone</title>
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

        .gig-form-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            padding: 30px;
        }

        .form-section {
            margin-bottom: 30px;
        }

        .form-section h3 {
            font-size: 20px;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border-color);
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

        .form-text {
            font-size: 14px;
            color: var(--text-color);
            margin-top: 5px;
            display: block;
        }

        textarea.form-control {
            min-height: 150px;
            resize: vertical;
        }

        .select-control {
            width: 100%;
            padding: 12px 15px;
            font-size: 16px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            background-color: white;
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 15px center;
            background-size: 15px;
        }

        .form-row {
            display: flex;
            gap: 20px;
        }

        .form-row .form-group {
            flex: 1;
        }

        .image-preview {
            width: 100%;
            max-width: 300px;
            margin-top: 10px;
            border-radius: 4px;
        }

        .image-preview-container {
            margin-top: 10px;
        }

        .btn {
            display: inline-block;
            padding: 12px 25px;
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

        .form-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
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
            .form-row {
                flex-direction: column;
                gap: 0;
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
                        <li><a href="create_gig.php">Create Gig</a></li>
                    </ul>
                </nav>
                <div class="user-menu">
                    <div class="user-profile">
                        <img src="<?php echo $_SESSION['profile_pic'] ?? 'https://www.gravatar.com/avatar/00000000000000000000000000000000?d=mp&f=y'; ?>" alt="Profile" class="user-avatar">
                        <span><?php echo $_SESSION['username']; ?> <i class="fas fa-chevron-down"></i></span>
                    </div>
                    <div class="dropdown-content">
                        <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
                        <a href="profile.php?tab=gigs"><i class="fas fa-list"></i> My Gigs</a>
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
            <h1 class="page-title">Edit Gig</h1>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <p>Your gig has been updated successfully! <a href="gig_detail.php?id=<?php echo $gig_id; ?>">View your gig</a> or <a href="profile.php?tab=gigs">go to your gigs</a>.</p>
                </div>
            <?php elseif (!empty($errors)): ?>
                <div class="alert alert-error">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <div class="gig-form-container">
                <form action="edit_gig.php?id=<?php echo $gig_id; ?>" method="POST" id="edit-gig-form">
                    <div class="form-section">
                        <h3>Gig Overview</h3>
                        <div class="form-group">
                            <label for="title">Gig Title</label>
                            <input type="text" id="title" name="title" class="form-control" value="<?php echo $gig['title']; ?>" maxlength="100" required>
                            <small class="form-text">Be clear and specific about what you are offering.</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="category_id">Category</label>
                            <select id="category_id" name="category_id" class="select-control" required>
                                <option value="">Select a category</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>" <?php echo ($category['id'] == $gig['category_id']) ? 'selected' : ''; ?>>
                                        <?php echo $category['name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h3>Gig Description</h3>
                        <div class="form-group">
                            <label for="description">Describe your gig in detail</label>
                            <textarea id="description" name="description" class="form-control" required><?php echo $gig['description']; ?></textarea>
                            <small class="form-text">Min. 50 characters</small>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h3>Pricing & Delivery</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="price">Price ($)</label>
                                <input type="number" id="price" name="price" class="form-control" min="5" step="5" value="<?php echo $gig['price']; ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="delivery_time">Delivery Time (days)</label>
                                <input type="number" id="delivery_time" name="delivery_time" class="form-control" min="1" max="30" value="<?php echo $gig['delivery_time']; ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h3>Gig Gallery</h3>
                        <p>Add up to 3 images that best showcase your service</p>
                        <div class="form-group">
                            <label for="image1">Image URL 1</label>
                            <input type="text" id="image1" name="image1" class="form-control" value="<?php echo $gig['image1']; ?>" placeholder="https://example.com/image1.jpg">
                            <?php if (!empty($gig['image1'])): ?>
                                <div class="image-preview-container">
                                    <img src="<?php echo $gig['image1']; ?>" alt="Gig Image 1" class="image-preview" id="preview1">
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label for="image2">Image URL 2 (Optional)</label>
                            <input type="text" id="image2" name="image2" class="form-control" value="<?php echo $gig['image2']; ?>" placeholder="https://example.com/image2.jpg">
                            <?php if (!empty($gig['image2'])): ?>
                                <div class="image-preview-container">
                                    <img src="<?php echo $gig['image2']; ?>" alt="Gig Image 2" class="image-preview" id="preview2">
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label for="image3">Image URL 3 (Optional)</label>
                            <input type="text" id="image3" name="image3" class="form-control" value="<?php echo $gig['image3']; ?>" placeholder="https://example.com/image3.jpg">
                            <?php if (!empty($gig['image3'])): ?>
                                <div class="image-preview-container">
                                    <img src="<?php echo $gig['image3']; ?>" alt="Gig Image 3" class="image-preview" id="preview3">
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="form-footer">
                        <a href="profile.php?tab=gigs" class="btn btn-outline">Cancel</a>
                        <button type="submit" class="btn">Update Gig</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Client-side validation
        const form = document.getElementById('edit-gig-form');
        const titleInput = document.getElementById('title');
        const descriptionInput = document.getElementById('description');
        const categorySelect = document.getElementById('category_id');
        const priceInput = document.getElementById('price');
        const deliveryInput = document.getElementById('delivery_time');
        
        form.addEventListener('submit', function(e) {
            let isValid = true;
            
            // Validate title
            if (titleInput.value.trim().length < 5) {
                alert('Title must be at least 5 characters long');
                titleInput.focus();
                isValid = false;
            }
            
            // Validate description
            if (descriptionInput.value.trim().length < 50) {
                alert('Description must be at least 50 characters long');
                descriptionInput.focus();
                isValid = false;
            }
            
            // Validate category
            if (categorySelect.value === "") {
                alert('Please select a category');
                categorySelect.focus();
                isValid = false;
            }
            
            // Validate price
            if (priceInput.value < 5) {
                alert('Price must be at least $5');
                priceInput.focus();
                isValid = false;
            }
            
            // Validate delivery time
            if (deliveryInput.value < 1 || deliveryInput.value > 30) {
                alert('Delivery time must be between 1 and 30 days');
                deliveryInput.focus();
                isValid = false;
            }
            
            if (!isValid) {
                e.preventDefault();
            }
        });
        
        // Preview images when URLs are entered
        const imageInputs = [
            { input: document.getElementById('image1'), preview: document.getElementById('preview1') },
            { input: document.getElementById('image2'), preview: document.getElementById('preview2') },
            { input: document.getElementById('image3'), preview: document.getElementById('preview3') }
        ];
        
        imageInputs.forEach(item => {
            if (item.input && item.preview) {
                item.input.addEventListener('blur', function() {
                    const url = this.value.trim();
                    if (url) {
                        item.preview.src = url;
                    }
                });
            }
        });
    </script>
</body>
</html>
