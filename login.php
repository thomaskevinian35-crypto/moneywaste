<?php
require_once 'includes/config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        if ($user['status'] == 'approved') {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['status'] = $user['status'];
            header("Location: index.php");
            exit();
        } else {
            $error = "Account pending admin approval";
        }
    } else {
        $error = "Invalid credentials";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — MONEYWASTE</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: #fff;
            color: #000;
        }
        
        .login-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 2rem;
        }
        
        .login-box {
            width: 100%;
            max-width: 400px;
            padding: 2.5rem;
            background: #fff;
            animation: fadeInUp 0.8s ease-out;
        }
        
        .login-title {
            font-size: 1.5rem;
            font-weight: 300;
            letter-spacing: 4px;
            text-transform: uppercase;
            margin-bottom: 2rem;
            text-align: center;
        }
        
        .message-error {
            color: #000;
            background: #f8f8f8;
            padding: 1rem;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            text-align: center;
            border-left: 2px solid #000;
            animation: shake 0.5s ease-in-out;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        
        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            color: #000;
            font-size: 0.85rem;
            letter-spacing: 1px;
            text-transform: uppercase;
            font-weight: 400;
        }
        
        .login-input {
            width: 100%;
            padding: 1rem;
            border: 1px solid #e0e0e0;
            background: #fff;
            font-size: 0.9rem;
            font-weight: 400;
            letter-spacing: 1px;
            transition: all 0.3s ease;
            color: #000;
            box-sizing: border-box;
        }
        
        .login-input:focus {
            outline: none;
            border-color: #000;
            box-shadow: 0 0 0 1px #000;
        }
        
        .login-btn {
            width: 100%;
            background: transparent;
            color: #000;
            border: 1px solid #000;
            padding: 1rem;
            font-size: 0.9rem;
            font-weight: 400;
            letter-spacing: 2px;
            text-transform: uppercase;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 1rem;
        }
        
        .login-btn:hover {
            background: #000;
            color: #fff;
        }
        
        .register-link {
            text-align: center;
            margin-top: 2rem;
            color: #666;
            font-size: 0.9rem;
        }
        
        .register-link a {
            color: #000;
            text-decoration: none;
            position: relative;
            padding-bottom: 2px;
        }
        
        .register-link a::after {
            content: "";
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0;
            height: 1px;
            background: #000;
            transition: width 0.3s ease;
        }
        
        .register-link a:hover::after {
            width: 100%;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Remove loading bar */
        .loading-bar {
            display: none;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <h1 class="login-title">Account Login</h1>
            
            <?php if(isset($error)): ?>
                <div class="message-error">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" id="loginForm">
                <div class="form-group">
                    <label class="form-label">Username</label>
                    <input type="text" 
                           name="username" 
                           class="login-input" 
                           placeholder="Enter your username" 
                           required
                           autocomplete="username">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Password</label>
                    <input type="password" 
                           name="password" 
                           class="login-input" 
                           placeholder="Enter your password" 
                           required
                           autocomplete="current-password"
                           id="passwordInput">
                </div>
                
                <button type="submit" class="login-btn" id="submitBtn">
                    Login
                </button>
            </form>
            
            <div class="register-link">
                No account? 
                <a href="signup.php">Register here</a>
            </div>
        </div>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('loginForm');
        const submitBtn = document.getElementById('submitBtn');
        
        // Form submission
        form.addEventListener('submit', function(e) {
            // Show loading state
            submitBtn.innerHTML = 'Logging in...';
            submitBtn.disabled = true;
            
            // Optional: Add a small delay to show loading state
            setTimeout(() => {
                submitBtn.innerHTML = 'Login';
                submitBtn.disabled = false;
            }, 2000);
        });
        
        // Input focus effects
        const inputs = document.querySelectorAll('.login-input');
        inputs.forEach(input => {
            input.addEventListener('focus', function() {
                this.style.borderColor = '#000';
                this.style.boxShadow = '0 0 0 1px #000';
            });
            
            input.addEventListener('blur', function() {
                if (!this.value) {
                    this.style.borderColor = '#e0e0e0';
                    this.style.boxShadow = '';
                }
            });
            
            // Initialize border color
            this.style.borderColor = '#e0e0e0';
        });
        
        // Auto-focus first input
        if (inputs.length > 0) {
            inputs[0].focus();
        }
        
        // Enter key to submit
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.target.matches('button')) {
                const activeElement = document.activeElement;
                if (activeElement && activeElement.matches('.login-input')) {
                    form.requestSubmit();
                }
            }
        });
    });
    </script>
</body>
</html>