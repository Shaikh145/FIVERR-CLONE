<?php
session_start();
require_once 'db.php';

$error = '';
$email = '';

// Check if already logged in
if (isset($_SESSION['user_id'])) {
    echo "<script>window.location.href = 'index.php';</script>";
    exit;
}

// Process login form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    
    // Validate form data
    if (empty($email) || empty($password)) {
        $error = "Please enter both email and password";
    } else {
        // Check user credentials
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = :email");
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (password_verify($password, $user['password'])) {
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['is_seller'] = $user['is_seller'];
                $_SESSION['profile_pic'] = $user['profile_pic'];
                
                // Redirect to homepage
                echo "<script>window.location.href = 'index.php';</script>";
                exit;
            } else {
                $error = "Invalid password";
            }
        } else {
            $error = "No account found with that email";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - FiverrClone</title>
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

        .login-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 40px 0;
        }

        .login-card {
            width: 100%;
            max-width: 450px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            padding: 40px;
        }

        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .login-header h1 {
            font-size: 28px;
            margin-bottom: 10px;
            color: var(--secondary-color);
        }

        .login-header p {
            color: var(--text-color);
        }

        .error-message {
            background-color: #ffebee;
            color: #c62828;
            padding: 10px 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            display: none;
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

        .form-group .forgot-password {
            display: block;
            text-align: right;
            font-size: 14px;
            color: var(--primary-color);
            text-decoration: none;
            margin-top: 5px;
        }

        .form-group .forgot-password:hover {
            text-decoration: underline;
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

        .register-link {
            text-align: center;
            margin-top: 20px;
            color: var(--text-color);
        }

        .register-link a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
        }

        .register-link a:hover {
            text-decoration: underline;
        }

        .social-login {
            margin-top: 30px;
            text-align: center;
        }

        .social-login p {
            margin-bottom: 15px;
            color: var(--text-color);
            position: relative;
        }

        .social-login p:before,
        .social-login p:after {
            content: "";
            display: inline-block;
            width: 40%;
            height: 1px;
            background-color: var(--border-color);
            position: absolute;
            top: 50%;
        }

        .social-login p:before {
            left: 0;
        }

        .social-login p:after {
            right: 0;
        }

        .social-buttons {
            display: flex;
            justify-content: center;
            gap: 15px;
        }

        .social-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--light-gray);
            color: var(--secondary-color);
            text-decoration: none;
            transition: background-color 0.2s;
        }

        .social-btn:hover {
            background-color: var(--border-color);
        }

        @media (max-width: 768px) {
            .login-card {
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
        
        <div class="login-container">
            <div class="login-card">
                <div class="login-header">
                    <h1>Welcome Back</h1>
                    <p>Log in to your FiverrClone account</p>
                </div>
                
                <?php if (!empty($error)): ?>
                <div class="error-message" style="display: block;">
                    <?php echo $error; ?>
                </div>
                <?php endif; ?>
                
                <form action="login.php" method="POST" id="login-form">
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" class="form-control" value="<?php echo $email; ?>" required>
                        <div class="error-text">Please enter a valid email</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" class="form-control" required>
                        <div class="error-text">Password is required</div>
                        <a href="#" class="forgot-password">Forgot Password?</a>
                    </div>
                    
                    <button type="submit" class="btn">Login</button>
                    
                    <div class="register-link">
                        Don't have an account? <a href="register.php">Register</a>
                    </div>
                    
                    <div class="social-login">
                        <p>Or continue with</p>
                        <div class="social-buttons">
                            <a href="#" class="social-btn"><i class="fab fa-google"></i></a>
                            <a href="#" class="social-btn"><i class="fab fa-facebook-f"></i></a>
                            <a href="#" class="social-btn"><i class="fab fa-apple"></i></a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Client-side form validation
        const form = document.getElementById('login-form');
        const email = document.getElementById('email');
        const password = document.getElementById('password');
        
        form.addEventListener('submit', function(e) {
            let isValid = true;
            
            // Reset previous error states
            const formGroups = form.querySelectorAll('.form-group');
            formGroups.forEach(group => {
                group.classList.remove('error');
            });
            
            // Validate email
            if (email.value.trim() === '') {
                setError(email, 'Email is required');
                isValid = false;
            } else if (!isValidEmail(email.value.trim())) {
                setError(email, 'Please enter a valid email');
                isValid = false;
            }
            
            // Validate password
            if (password.value === '') {
                setError(password, 'Password is required');
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
