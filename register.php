<?php
session_start();
require_once 'db.php';

$errors = [];
$username = $email = $fullName = '';

// Check if already logged in
if (isset($_SESSION['user_id'])) {
    echo "<script>window.location.href = 'index.php';</script>";
    exit;
}

// Process registration form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $username = sanitize($_POST['username']);
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    $fullName = sanitize($_POST['full_name']);
    $isSeller = isset($_POST['is_seller']) ? 1 : 0;

    // Validate form data
    if (empty($username)) {
        $errors[] = "Username is required";
    } elseif (strlen($username) < 3 || strlen($username) > 20) {
        $errors[] = "Username must be between 3 and 20 characters";
    }

    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }

    if (empty($password)) {
        $errors[] = "Password is required";
    } elseif (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters";
    }

    if ($password !== $confirmPassword) {
        $errors[] = "Passwords do not match";
    }

    // Check if username or email already exists
    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = :username OR email = :email");
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $errors[] = "Username or email already exists";
        }
    }

    // If no errors, create user account
    if (empty($errors)) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $conn->prepare("INSERT INTO users (username, email, password, full_name, is_seller) VALUES (:username, :email, :password, :full_name, :is_seller)");
        
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':password', $hashedPassword);
        $stmt->bindParam(':full_name', $fullName);
        $stmt->bindParam(':is_seller', $isSeller);
        
        if ($stmt->execute()) {
            // Get the user ID
            $userId = $conn->lastInsertId();
            
            // Set session variables
            $_SESSION['user_id'] = $userId;
            $_SESSION['username'] = $username;
            $_SESSION['email'] = $email;
            $_SESSION['is_seller'] = $isSeller;
            
            // Redirect to homepage
            echo "<script>window.location.href = 'index.php';</script>";
            exit;
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
    <title>Register - FiverrClone</title>
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

        .back-to-home {
            display: inline-block;
            margin: 20px 0;
            color: var(--text-color);
            text-decoration: none;
        }

        .back-to-home i {
            margin-right: 5px;
        }

        .back-to-home:hover {
            color: var(--primary-color);
        }

        .register-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 40px 0;
        }

        .register-card {
            width: 100%;
            max-width: 500px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            padding: 40px;
        }

        .register-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .register-header h1 {
            font-size: 28px;
            margin-bottom: 10px;
            color: var(--secondary-color);
        }

        .register-header p {
            color: var(--text-color);
        }

        .error-messages {
            background-color: #ffebee;
            color: #c62828;
            padding: 10px 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            display: none;
        }

        .error-messages ul {
            margin-left: 20px;
        }

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

        .checkbox-group {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }

        .checkbox-group input {
            margin-right: 10px;
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
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .btn:hover {
            background-color: #19a463;
        }

        .login-link {
            text-align: center;
            margin-top: 20px;
            color: var(--text-color);
        }

        .login-link a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
        }

        .login-link a:hover {
            text-decoration: underline;
        }

        @media (max-width: 768px) {
            .register-card {
                padding: 20px;
            }
        }

        .form-group.error .form-control {
            border-color: #c62828;
        }

        .form-group .error-text {
            color: #c62828;
            font-size: 14px;
            margin-top: 5px;
            display: none;
        }

        .form-group.error .error-text {
            display: block;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="index.php" class="back-to-home"><i class="fas fa-arrow-left"></i> Back to Home</a>
        
        <div class="register-container">
            <div class="register-card">
                <div class="register-header">
                    <h1>Create an Account</h1>
                    <p>Join our community of buyers and sellers</p>
                </div>
                
                <?php if (!empty($errors)): ?>
                <div class="error-messages" style="display: block;">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
                
                <form action="register.php" method="POST" id="register-form">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" class="form-control" value="<?php echo $username; ?>" required>
                        <div class="error-text">Username is required</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" class="form-control" value="<?php echo $email; ?>" required>
                        <div class="error-text">Please enter a valid email</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="full_name">Full Name</label>
                        <input type="text" id="full_name" name="full_name" class="form-control" value="<?php echo $fullName; ?>">
                        <div class="error-text">Full name is required</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" class="form-control" required>
                        <div class="error-text">Password must be at least 6 characters</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                        <div class="error-text">Passwords do not match</div>
                    </div>
                    
                    <div class="checkbox-group">
                        <input type="checkbox" id="is_seller" name="is_seller">
                        <label for="is_seller">Join as a seller</label>
                    </div>
                    
                    <button type="submit" class="btn">Register</button>
                    
                    <div class="login-link">
                        Already have an account? <a href="login.php">Login</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Client-side form validation
        const form = document.getElementById('register-form');
        const username = document.getElementById('username');
        const email = document.getElementById('email');
        const fullName = document.getElementById('full_name');
        const password = document.getElementById('password');
        const confirmPassword = document.getElementById('confirm_password');
        
        form.addEventListener('submit', function(e) {
            let isValid = true;
            
            // Reset previous error states
            const formGroups = form.querySelectorAll('.form-group');
            formGroups.forEach(group => {
                group.classList.remove('error');
            });
            
            // Validate username
            if (username.value.trim() === '') {
                setError(username, 'Username is required');
                isValid = false;
            } else if (username.value.length < 3 || username.value.length > 20) {
                setError(username, 'Username must be between 3 and 20 characters');
                isValid = false;
            }
            
            // Validate email
            if (email.value.trim() === '') {
                setError(email, 'Email is required');
                isValid = false;
            } else if (!isValidEmail(email.value.trim())) {
                setError(email, 'Please enter a valid email');
                isValid = false;
            }
            
            // Validate full name
            if (fullName.value.trim() === '') {
                setError(fullName, 'Full name is required');
                isValid = false;
            }
            
            // Validate password
            if (password.value === '') {
                setError(password, 'Password is required');
                isValid = false;
            } else if (password.value.length < 6) {
                setError(password, 'Password must be at least 6 characters');
                isValid = false;
            }
            
            // Validate confirm password
            if (confirmPassword.value === '') {
                setError(confirmPassword, 'Please confirm your password');
                isValid = false;
            } else if (password.value !== confirmPassword.value) {
                setError(confirmPassword, 'Passwords do not match');
                isValid = false;
            }
            
            if (!isValid) {
                e.preventDefault();
            }
        });
        
        function setError(input, message) {
            const formGroup = input.parentElement;
            const errorText = formGroup.querySelector('.error-text');
            formGroup.classList.add('error');
            errorText.textContent = message;
        }
        
        function isValidEmail(email) {
            const re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
            return re.test(String(email).toLowerCase());
        }
    </script>
</body>
</html>
