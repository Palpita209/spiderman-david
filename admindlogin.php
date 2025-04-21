<?php
session_start();

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get username and password from form
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    // Here you would typically connect to a database and verify credentials
    // For demonstration, using hardcoded credentials (replace with database check)
    $valid_username = "Capitol";
    $valid_password = "Capitol";
    
    // Validate credentials
    if ($username === $valid_username && $password === $valid_password) {
        // Set session variables
        $_SESSION['loggedin'] = true;
        $_SESSION['username'] = $username;
        $_SESSION['user_id'] = 1; // Set a default user ID
        $_SESSION['role'] = 'Administrator'; // Set a default role
        
        // Redirect to index.php instead of dashboard.php
        header("Location: admindashboard.php");
        exit;
    } else {
        // Invalid credentials
        $login_error = "Invalid username or password!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Philippine Design</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --phil-blue: #0038A8;
            --phil-red: #CE1126;
            --phil-yellow: #FCD116;
            --phil-white: #FFFFFF;
        }
        
        body {
            overflow-x: hidden;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--phil-blue);
            margin: 0;
            padding: 0;
        }
        
        .login-container {
            height: 100vh;
            max-height: 100vh;
            overflow: hidden;
        }
        
        .image-section {
            padding: 0;
            height: 100vh;
            position: relative;
            overflow: hidden;
            width: 65%; /* Larger image section */
        }
        
        .image-section img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
      
      
        .form-section {
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 2rem;
            position: relative;
            z-index: 10;
            background-color: white;
            height: 100vh;
            width: 35%; /* Smaller form section */
        }
        
        .login-form {
            max-width: 320px; /* Slightly narrower form */
            margin: 0 auto;
            position: relative;
        }
        
        .login-header {
            margin-bottom: 2rem;
            text-align: center;
        }
        
        .login-header h2 {
            font-weight: 700;
            color: var(--phil-blue);
            margin-top: 0.5rem;
            margin-bottom: 0.5rem;
            font-size: 1.8rem; /* Larger heading */
        }
        
        .login-header p {
            color: #6c757d;
            margin-bottom: 0;
            font-size: 0.9rem; /* Smaller subheading */
        }
        
        .input-group {
            height: 44px; /* Slightly smaller input groups */
            margin-bottom: 1rem;
            
        }
        
        .form-control {
            border-radius: 0 8px 8px 0;
            height: 44px;
            border: 1px solid #ced4da;
            font-size: 0.9rem; /* Smaller font for inputs */
        }
        
        .input-group-text {
            border-radius: 8px 0 0 8px;
            background-color: var(--phil-blue);
            color: white;
            font-size: 0.9rem; /* Matching icon size */
        }
        
        .form-control:focus {
            box-shadow: 0 0 0 0.25rem rgba(0, 56, 168, 0.25);
            border-color: var(--phil-blue);
        }
        
        .password-container {
            position: relative;
        }
        
        .password-toggle {
            position: absolute;
            right: 10px;
            top: 12px;
            cursor: pointer;
            color: #6c757d;
            z-index: 10;
            font-size: 0.9rem; /* Smaller eye icon */
        }
        
        .btn-login {
            height: 44px;
            border-radius: 8px;
            background-color: var(--phil-red);
            border: none;
            font-weight: 600;
            transition: all 0.3s ease;
            font-size: 0.95rem; /* Button text size */
        }
        
        .btn-login:hover {
            background-color: #a00e1e;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        
        .logo {
            width: 100px; /* Smaller logo */
            height: 100px;
            margin-bottom: 1rem;
            object-fit: contain;
        }
        
        /* Form check styling */
        .form-check {
            height: 22px;
            line-height: 22px;
            font-size: 0.85rem; /* Smaller checkbox text */
        }
        
        .form-check-input:checked {
            background-color: var(--phil-blue);
            border-color: var(--phil-blue);
        }
        
        .form-check-input {
            margin-top: 0.25rem;
        }
        
        /* Links */
        a {
            color: var(--phil-blue);
            text-decoration: none;
            font-size: 0.85rem; /* Smaller link text */
        }
        
        a:hover {
            color: var(--phil-red);
        }
        
        /* Additional spacing to prevent layout shift */
        .alert {
            margin-bottom: 1rem;
            min-height: 50px; /* Smaller alert container */
            display: flex;
            align-items: center;
            font-size: 0.85rem; /* Smaller alert text */
        }
        
        /* Hidden alert container when no message */
        .alert-container {
            min-height: 50px;
            margin-bottom: 1rem;
        }
        
        /* Flag-inspired divider */
        .flag-divider {
            height: 5px;
            width: 100%;
            margin: 1rem 0;
            background: #dddddd;
            position: relative;
        }
        
        .flag-divider::before {
            content: "";
            position: absolute;
            top: -3px;
            left: 50%;
            transform: translateX(-50%);
            width: 12px;
            height: 12px;
            background-color: #999999;
            clip-path: polygon(
                50% 0%, 61% 35%, 98% 35%, 68% 57%, 
                79% 91%, 50% 70%, 21% 91%, 32% 57%, 
                2% 35%, 39% 35%
            );
        }
        
        /* Responsive adjustments */
        @media (max-width: 991.98px) {
            .image-section {
                height: 40vh;
                width: 100%;
            }
            .form-section {
                width: 100%;
                height: auto;
                min-height: 60vh;
                padding: 2rem 1.5rem;
            }
            .login-form {
                max-width: 100%;
            }
            .flag-overlay {
                clip-path: polygon(0 0, 100% 0, 100% 85%, 0 100%);
            }
            .login-header h2 {
                font-size: 1.6rem;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid p-0">
        <div class="row g-0 login-container">
            <!-- Right Image Section (Full height) -->
            <div class="col-lg-8 image-section order-lg-2"> <!-- Changed to col-lg-8 for larger image -->
                <img src="images/image.png" alt="Philippines Landscape">
                <div class="flag-overlay"></div>
                <div class="sun-rays"></div>
            </div>
            
            <!-- Left Form Section -->
            <div class="col-lg-4 form-section order-lg-1"> <!-- Changed to col-lg-4 for smaller form -->
                <div class="login-form">
                    <div class="login-header">
                        <img src="images/logo2.png" alt="Philippine Emblem" class="logo">
                        <h2>Welcome Back</h2>
                        <p>Please login to your account</p>
                    </div>
                    
                    <div class="flag-divider"></div>
                    
                    <div class="alert-container">
                        <?php if(isset($login_error)): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="fas fa-exclamation-circle me-2"></i><?php echo $login_error; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                        <div class="mb-3">
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-user"></i></span>
                                <input type="text" class="form-control" id="username" name="username" placeholder="Username" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                                <span class="password-toggle" onclick="togglePassword()">
                                    <i class="fas fa-eye" id="toggleIcon"></i>
                                </span>
                            </div>
                        </div>

                        
                        <button type="submit" class="btn btn-login btn-primary w-100">Login</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Function to toggle password visibility
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>