<?php
require_once 'includes/db_connection.php';

$showPopup = false;
$popupMessage = '';
$popupType = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $address = trim($_POST['address']);
    $city = trim($_POST['city']);
    $postal_code = trim($_POST['postal_code']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validation array
    $errors = [];
    
    // Check if passwords match
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }
    
    // Validate password length
    if (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters long.";
    }
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    }
    
    // Validate phone number (Philippine format)
    $phoneClean = preg_replace('/\D/', '', $phone);
    if (!preg_match('/^(09|\+639|9)\d{9}$/', $phoneClean) && strlen($phoneClean) !== 11) {
        $errors[] = "Please enter a valid Philippine mobile number (e.g., 09123456789).";
    }
    
    // Validate postal code (4 digits)
    if (!preg_match('/^\d{4}$/', $postal_code)) {
        $errors[] = "Postal code must be 4 digits.";
    }
    
    // Validate username (alphanumeric and underscore only)
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $errors[] = "Username can only contain letters, numbers, and underscores.";
    }
    
    if (strlen($username) < 3) {
        $errors[] = "Username must be at least 3 characters.";
    }
    
    // If there are validation errors, show them
    if (!empty($errors)) {
        $popupMessage = implode("\\n", $errors);
        $popupType = "error";
        $showPopup = true;
    } else {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        try {
            // Check if username already exists
            $checkStmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $checkStmt->execute([$username]);
            
            if ($checkStmt->fetch()) {
                $popupMessage = "Username already taken. Please choose another.";
                $popupType = "error";
                $showPopup = true;
            } else {
                // Check if email already exists
                $checkEmail = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $checkEmail->execute([$email]);
                
                if ($checkEmail->fetch()) {
                    $popupMessage = "Email already registered. Please login or use another email.";
                    $popupType = "error";
                    $showPopup = true;
                } else {
                    // Insert new user with ALL fields
                    $stmt = $pdo->prepare("INSERT INTO users (username, fullname, email, address, city, postal_code, phone, password, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
                    $stmt->execute([$username, $fullname, $email, $address, $city, $postal_code, $phone, $password_hash]);
                    
                    $popupMessage = "Account created successfully! Please wait for admin approval. You will be redirected to login.";
                    $popupType = "success";
                    $showPopup = true;
                }
            }
        } catch(PDOException $e) {
            $popupMessage = "Registration failed: " . $e->getMessage();
            $popupType = "error";
            $showPopup = true;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register — MONEYWASTE</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .register-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 2rem;
            background: #f9f9f9;
        }
        
        .register-box {
            width: 100%;
            max-width: 500px;
            padding: 2.5rem 3rem;
            border: 1px solid #e8e8e8;
            background: #fff;
            animation: fadeInUp 0.8s ease-out;
            box-shadow: 0 5px 30px rgba(0, 0, 0, 0.05);
            border-radius: 2px;
        }
        
        .register-title {
            font-size: 1.5rem;
            font-weight: 300;
            letter-spacing: 4px;
            text-transform: uppercase;
            margin-bottom: 2rem;
            text-align: center;
            color: #000;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            color: #000;
            font-size: 0.75rem;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            font-weight: 500;
        }
        
        .form-label .required {
            color: #d32f2f;
            margin-left: 2px;
        }
        
        .register-input {
            width: 100%;
            padding: 1rem;
            border: 1px solid #ddd;
            background: #fff;
            font-size: 0.9rem;
            font-weight: 400;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            color: #000;
            border-radius: 1px;
            box-sizing: border-box;
        }
        
        textarea.register-input {
            resize: vertical;
            min-height: 80px;
            font-family: inherit;
        }
        
        .register-input:focus {
            outline: none;
            border-color: #000;
            box-shadow: 0 0 0 2px rgba(0, 0, 0, 0.05);
        }
        
        .register-input::placeholder {
            color: #999;
            font-weight: 300;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .register-btn {
            width: 100%;
            background: transparent;
            color: #000;
            border: 1px solid #000;
            padding: 1rem;
            font-size: 0.85rem;
            font-weight: 500;
            letter-spacing: 2.5px;
            text-transform: uppercase;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 0.5rem;
            border-radius: 1px;
        }
        
        .register-btn:hover {
            background: #000;
            color: #fff;
        }
        
        .register-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .login-link {
            text-align: center;
            margin-top: 2rem;
            color: #666;
            font-size: 0.85rem;
            padding-top: 1.5rem;
            border-top: 1px solid #f0f0f0;
        }
        
        .login-link a {
            color: #000;
            text-decoration: none;
            position: relative;
            padding-bottom: 2px;
            font-weight: 500;
            letter-spacing: 0.5px;
        }
        
        .login-link a::after {
            content: "";
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0;
            height: 1px;
            background: #000;
            transition: width 0.3s ease;
        }
        
        .login-link a:hover::after {
            width: 100%;
        }
        
        .input-hint {
            font-size: 0.65rem;
            color: #999;
            margin-top: 4px;
            display: block;
        }
        
        /* Popup Modal Styles */
        .popup-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            animation: fadeIn 0.3s ease;
        }
        
        .popup-overlay.active {
            display: flex;
        }
        
        .popup-modal {
            background: white;
            width: 90%;
            max-width: 400px;
            padding: 2.5rem;
            border-radius: 2px;
            text-align: center;
            animation: popupSlideIn 0.4s ease;
            border: 1px solid #e8e8e8;
        }
        
        .popup-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        
        .popup-title {
            font-size: 1.2rem;
            font-weight: 300;
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-bottom: 1rem;
            color: #000;
        }
        
        .popup-message {
            font-size: 0.9rem;
            line-height: 1.5;
            color: #666;
            margin-bottom: 1.5rem;
            letter-spacing: 0.5px;
            white-space: pre-line;
        }
        
        .popup-btn {
            background: transparent;
            color: #000;
            border: 1px solid #000;
            padding: 0.8rem 2rem;
            font-size: 0.85rem;
            font-weight: 500;
            letter-spacing: 2px;
            text-transform: uppercase;
            cursor: pointer;
            transition: all 0.3s ease;
            border-radius: 1px;
        }
        
        .popup-btn:hover {
            background: #000;
            color: #fff;
        }
        
        .success-icon {
            color: #4caf50;
        }
        
        .error-icon {
            color: #d32f2f;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes popupSlideIn {
            from { 
                opacity: 0; 
                transform: translateY(-30px) scale(0.95); 
            }
            to { 
                opacity: 1; 
                transform: translateY(0) scale(1); 
            }
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
        
        @media (max-width: 600px) {
            .register-box {
                padding: 1.5rem;
                max-width: 100%;
            }
            
            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }
            
            .register-container {
                padding: 1rem;
                background: #fff;
            }
            
            .popup-modal {
                padding: 1.5rem;
                margin: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-box">
            <h1 class="register-title">Create Account</h1>
            
            <form method="POST" action="" id="registerForm">
                <div class="form-group">
                    <label class="form-label">Username <span class="required">*</span></label>
                    <input type="text" 
                           name="username" 
                           class="register-input" 
                           placeholder="Choose a username" 
                           required
                           minlength="3"
                           maxlength="30"
                           pattern="[a-zA-Z0-9_]+"
                           title="Only letters, numbers, and underscores allowed"
                           autocomplete="username"
                           value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                    <span class="input-hint">Letters, numbers, and underscores only</span>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Full Name <span class="required">*</span></label>
                    <input type="text" 
                           name="fullname" 
                           class="register-input" 
                           placeholder="Enter your full name" 
                           required
                           autocomplete="name"
                           value="<?php echo isset($_POST['fullname']) ? htmlspecialchars($_POST['fullname']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Email Address <span class="required">*</span></label>
                    <input type="email" 
                           name="email" 
                           class="register-input" 
                           placeholder="yourname@gmail.com" 
                           required
                           autocomplete="email"
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    <span class="input-hint">We'll send order confirmations here</span>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Phone Number <span class="required">*</span></label>
                    <input type="tel" 
                           name="phone" 
                           class="register-input" 
                           placeholder="09123456789" 
                           required
                           autocomplete="tel"
                           value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                    <span class="input-hint">Philippine mobile number (11 digits)</span>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Street Address <span class="required">*</span></label>
                    <textarea name="address" 
                              class="register-input" 
                              placeholder="House/Unit No., Street, Barangay" 
                              required><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">City <span class="required">*</span></label>
                        <input type="text" 
                               name="city" 
                               class="register-input" 
                               placeholder="e.g., Manila" 
                               required
                               value="<?php echo isset($_POST['city']) ? htmlspecialchars($_POST['city']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Postal Code <span class="required">*</span></label>
                        <input type="text" 
                               name="postal_code" 
                               class="register-input" 
                               placeholder="1000" 
                               required
                               pattern="\d{4}"
                               title="4-digit postal code"
                               value="<?php echo isset($_POST['postal_code']) ? htmlspecialchars($_POST['postal_code']) : ''; ?>">
                        <span class="input-hint">4-digit code</span>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Password <span class="required">*</span></label>
                    <input type="password" 
                           name="password" 
                           class="register-input" 
                           placeholder="Create a password (min. 6 characters)" 
                           required
                           minlength="6"
                           id="passwordInput"
                           autocomplete="new-password">
                    <span class="input-hint">At least 6 characters</span>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Confirm Password <span class="required">*</span></label>
                    <input type="password" 
                           name="confirm_password" 
                           class="register-input" 
                           placeholder="Confirm your password" 
                           required
                           id="confirmPasswordInput"
                           autocomplete="new-password">
                </div>
                
                <button type="submit" class="register-btn" id="submitBtn">
                    Create Account
                </button>
            </form>
            
            <div class="login-link">
                Already have an account? 
                <a href="login.php">Sign in here</a>
            </div>
        </div>
    </div>
    
    <!-- Popup Modal -->
    <div class="popup-overlay" id="popupOverlay">
        <div class="popup-modal">
            <div class="popup-icon" id="popupIcon">
                <?php if($popupType == 'success'): ?>
                    ✓
                <?php elseif($popupType == 'error'): ?>
                    ✕
                <?php endif; ?>
            </div>
            
            <h2 class="popup-title" id="popupTitle">
                <?php echo $popupType == 'success' ? 'Success' : 'Error'; ?>
            </h2>
            
            <p class="popup-message" id="popupMessage">
                <?php echo htmlspecialchars($popupMessage); ?>
            </p>
            
            <button class="popup-btn" id="popupCloseBtn">
                OK
            </button>
        </div>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('registerForm');
        const passwordInput = document.getElementById('passwordInput');
        const confirmPasswordInput = document.getElementById('confirmPasswordInput');
        const submitBtn = document.getElementById('submitBtn');
        const popupOverlay = document.getElementById('popupOverlay');
        const popupCloseBtn = document.getElementById('popupCloseBtn');
        const phoneInput = document.querySelector('input[name="phone"]');
        const postalInput = document.querySelector('input[name="postal_code"]');
        
        // Show popup if needed (from PHP)
        <?php if($showPopup): ?>
            setTimeout(() => {
                popupOverlay.classList.add('active');
                <?php if($popupType == 'success'): ?>
                    setTimeout(() => {
                        window.location.href = 'login.php';
                    }, 2500);
                <?php endif; ?>
            }, 100);
        <?php endif; ?>
        
        // Close popup when OK button is clicked
        popupCloseBtn.addEventListener('click', function() {
            popupOverlay.classList.remove('active');
            <?php if($popupType == 'success'): ?>
                window.location.href = 'login.php';
            <?php endif; ?>
        });
        
        // Close popup when clicking outside
        popupOverlay.addEventListener('click', function(e) {
            if (e.target === popupOverlay) {
                popupOverlay.classList.remove('active');
                <?php if($popupType == 'success'): ?>
                    window.location.href = 'login.php';
                <?php endif; ?>
            }
        });
        
        // Phone number formatting (only numbers, max 11 digits)
        if (phoneInput) {
            phoneInput.addEventListener('input', function(e) {
                let phone = this.value.replace(/\D/g, '');
                if (phone.length > 11) phone = phone.slice(0, 11);
                this.value = phone;
            });
        }
        
        // Postal code formatting (only numbers, max 4 digits)
        if (postalInput) {
            postalInput.addEventListener('input', function(e) {
                let postal = this.value.replace(/\D/g, '');
                if (postal.length > 4) postal = postal.slice(0, 4);
                this.value = postal;
            });
        }
        
        // Full name capitalization
        const fullnameInput = document.querySelector('input[name="fullname"]');
        if (fullnameInput) {
            fullnameInput.addEventListener('blur', function() {
                let words = this.value.split(' ');
                for (let i = 0; i < words.length; i++) {
                    if (words[i].length > 0) {
                        words[i] = words[i].charAt(0).toUpperCase() + words[i].slice(1).toLowerCase();
                    }
                }
                this.value = words.join(' ');
            });
        }
        
        // Password validation
        function validatePasswords() {
            const password = passwordInput.value;
            const confirmPassword = confirmPasswordInput.value;
            
            if (password !== confirmPassword && confirmPassword !== '') {
                confirmPasswordInput.style.borderColor = '#d32f2f';
                confirmPasswordInput.style.boxShadow = '0 0 0 2px rgba(211, 47, 47, 0.1)';
                return false;
            } else {
                confirmPasswordInput.style.borderColor = '';
                confirmPasswordInput.style.boxShadow = '';
                return true;
            }
        }
        
        // Real-time password validation
        confirmPasswordInput.addEventListener('input', validatePasswords);
        passwordInput.addEventListener('input', validatePasswords);
        
        // Form submission
        form.addEventListener('submit', function(e) {
            // Validate passwords match
            if (!validatePasswords()) {
                e.preventDefault();
                showErrorPopup('Passwords do not match. Please re-enter your password.');
                return;
            }
            
            // Validate password length
            if (passwordInput.value.length < 6) {
                e.preventDefault();
                showErrorPopup('Password must be at least 6 characters long.');
                return;
            }
            
            // Validate phone number
            const phone = phoneInput ? phoneInput.value.replace(/\D/g, '') : '';
            if (phone.length !== 11 || !phone.match(/^09/)) {
                e.preventDefault();
                showErrorPopup('Please enter a valid Philippine mobile number (e.g., 09123456789)');
                return;
            }
            
            // Validate postal code
            const postal = postalInput ? postalInput.value : '';
            if (!/^\d{4}$/.test(postal)) {
                e.preventDefault();
                showErrorPopup('Please enter a valid 4-digit postal code.');
                return;
            }
            
            // Validate email
            const email = document.querySelector('input[name="email"]').value;
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                e.preventDefault();
                showErrorPopup('Please enter a valid email address.');
                return;
            }
            
            // Show loading state
            submitBtn.innerHTML = 'Creating Account...';
            submitBtn.disabled = true;
            submitBtn.style.opacity = '0.8';
        });
        
        function showErrorPopup(message) {
            document.getElementById('popupMessage').textContent = message;
            document.getElementById('popupTitle').textContent = 'Error';
            document.getElementById('popupIcon').innerHTML = '✕';
            document.getElementById('popupIcon').style.color = '#d32f2f';
            popupOverlay.classList.add('active');
        }
        
        // Input focus effects
        const inputs = document.querySelectorAll('.register-input');
        inputs.forEach(input => {
            input.addEventListener('focus', function() {
                this.style.borderColor = '#000';
                this.style.boxShadow = '0 0 0 2px rgba(0, 0, 0, 0.05)';
            });
            
            input.addEventListener('blur', function() {
                if (!this.value) {
                    this.style.borderColor = '';
                    this.style.boxShadow = '';
                }
            });
        });
        
        // Username validation
        const usernameInput = document.querySelector('input[name="username"]');
        if (usernameInput) {
            usernameInput.addEventListener('input', function() {
                const value = this.value;
                const pattern = /^[a-zA-Z0-9_]+$/;
                
                if (!pattern.test(value) && value !== '') {
                    this.style.borderColor = '#d32f2f';
                } else {
                    this.style.borderColor = '';
                    this.style.boxShadow = '';
                }
            });
        }
        
        // Clear loading state if form submission fails
        window.addEventListener('pageshow', function() {
            submitBtn.innerHTML = 'Create Account';
            submitBtn.disabled = false;
            submitBtn.style.opacity = '1';
        });
    });
    </script>
</body>
</html>