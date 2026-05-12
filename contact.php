<?php
require_once 'includes/config.php';

// Function to check if user is logged in
if (!function_exists('isLoggedIn')) {
    function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
}

// Handle form submission
$message_sent = false;
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $comment = trim($_POST['comment'] ?? '');
    
    // Combine first and last name
    $full_name = $first_name . ' ' . $last_name;
    
    // Combine subject and comment for message
    $full_message = "Subject: " . $subject . "\n\n" . $comment;
    if (!empty($phone)) {
        $full_message = "Phone: " . $phone . "\n\n" . $full_message;
    }
    
    // Validation
    if (empty($first_name) || empty($last_name) || empty($subject) || empty($email)) {
        $error_message = 'Please fill in all required fields.';
    } 
    // Check if it's a valid email format
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Please enter a valid email address.';
    }
    // Check if it's a Gmail address (case insensitive)
    elseif (!preg_match('/^[a-zA-Z0-9._%+-]+@gmail\.com$/i', $email)) {
        $error_message = 'Only Gmail addresses (@gmail.com) are allowed.';
    }
    else {
        try {
            // Check if contact_messages table exists and has user_id column
            $checkTable = $pdo->query("SHOW TABLES LIKE 'contact_messages'");
            if ($checkTable->rowCount() == 0) {
                // Create contact_messages table with user_id column
                $createTable = "CREATE TABLE IF NOT EXISTS contact_messages (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(255) NOT NULL,
                    email VARCHAR(255) NOT NULL,
                    message TEXT,
                    user_id INT NULL,
                    status ENUM('unread', 'read', 'replied') DEFAULT 'unread',
                    admin_id INT DEFAULT NULL,
                    replied_at TIMESTAMP NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_user_id (user_id),
                    INDEX idx_status (status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
                
                $pdo->exec($createTable);
            } else {
                // Check if user_id column exists, if not add it
                $checkColumn = $pdo->query("SHOW COLUMNS FROM contact_messages LIKE 'user_id'");
                if ($checkColumn->rowCount() == 0) {
                    $pdo->exec("ALTER TABLE contact_messages ADD COLUMN user_id INT NULL, ADD INDEX idx_user_id (user_id)");
                }
            }
            
            // FIXED: ONLY get user_id if user is actually LOGGED IN
            // Do NOT try to find user by email - that would incorrectly mark guests as members
            $user_id = null;
            if (isLoggedIn()) {
                // User is logged in - use their session user_id
                $user_id = $_SESSION['user_id'];
            }
            // If not logged in, user_id remains NULL (guest)
            
            // Insert into database with user_id
            $insert_query = "INSERT INTO contact_messages (name, email, message, user_id, status, created_at) 
                            VALUES (?, ?, ?, ?, 'unread', NOW())";
            
            $insert_stmt = $pdo->prepare($insert_query);
            $result = $insert_stmt->execute([
                $full_name,
                $email,
                $full_message,
                $user_id
            ]);
            
            if ($result) {
                $message_sent = true;
                
                // Find admin user by username
                $admin_query = "SELECT id FROM users WHERE username = 'admin' LIMIT 1";
                $admin_stmt = $pdo->query($admin_query);
                $admin = $admin_stmt->fetch(PDO::FETCH_ASSOC);
                
                // If no admin found with username 'admin', try to get any user as fallback
                if (!$admin) {
                    $admin_query = "SELECT id FROM users LIMIT 1";
                    $admin_stmt = $pdo->query($admin_query);
                    $admin = $admin_stmt->fetch(PDO::FETCH_ASSOC);
                }
                
                // Create notification for admin
                if ($admin) {
                    try {
                        // Check if notifications table exists
                        $checkNotifTable = $pdo->query("SHOW TABLES LIKE 'notifications'");
                        if ($checkNotifTable->rowCount() > 0) {
                            $notif_query = "INSERT INTO notifications (user_id, order_id, message, status, created_at) 
                                           VALUES (?, NULL, ?, 'unread', NOW())";
                            $notif_stmt = $pdo->prepare($notif_query);
                            $notif_message = "New contact message from " . $full_name;
                            $notif_stmt->execute([$admin['id'], $notif_message]);
                        }
                    } catch (Exception $e) {
                        error_log("Admin notification error: " . $e->getMessage());
                    }
                }
                
                // Send email notification (suppress errors)
                @mail("mnywstdclthng@gmail.com", "Contact Form: $subject", 
                    "Name: $full_name\nEmail: $email\nPhone: $phone\nMessage: $comment", 
                    "From: $email\r\nReply-To: $email\r\n");
                
                // If user is logged in, create notification for them too
                if (isLoggedIn()) {
                    try {
                        $checkNotifTable = $pdo->query("SHOW TABLES LIKE 'notifications'");
                        if ($checkNotifTable->rowCount() > 0) {
                            $user_notif_query = "INSERT INTO notifications (user_id, order_id, message, status, created_at) 
                                               VALUES (?, NULL, ?, 'unread', NOW())";
                            $user_notif_stmt = $pdo->prepare($user_notif_query);
                            $user_notif_message = "Your message has been sent. We'll get back to you soon.";
                            $user_notif_stmt->execute([$_SESSION['user_id'], $user_notif_message]);
                        }
                    } catch (Exception $e) {
                        error_log("User notification error: " . $e->getMessage());
                    }
                }
                
                // Clear POST data
                $_POST = array();
            } else {
                $error_message = 'Failed to save message. Please try again.';
            }
            
        } catch (PDOException $e) {
            $error_message = 'Database error: ' . $e->getMessage();
            error_log("Contact form error: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us — MONEYWASTE</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Header Styles */
        .minimal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1.5rem 2rem;
            border-bottom: 1px solid #f0f0f0;
            background: white;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 3rem;
            flex: 1;
        }

        .logo-container {
            display: flex;
            align-items: center;
        }

        .logo-link {
            display: flex;
            align-items: center;
            text-decoration: none;
            color: inherit;
            gap: 12px;
        }

        .logo-image {
            height: 40px;
            width: auto;
            display: block;
            transition: opacity 0.3s ease;
        }

        .logo-image:hover {
            opacity: 0.8;
        }

        .logo-text {
            font-size: 1.5rem;
            letter-spacing: 1px;
            font-weight: normal;
            white-space: nowrap;
        }

        /* Navigation */
        .minimal-nav ul {
            display: flex;
            list-style: none;
            gap: 2rem;
            margin: 0;
            padding: 0;
        }

        .nav-link {
            text-decoration: none;
            color: #333;
            font-size: 0.9rem;
            letter-spacing: 0.5px;
            transition: color 0.3s ease;
            padding: 0.5rem 0;
            position: relative;
        }

        .nav-link:hover {
            color: #000;
        }

        .nav-link:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0;
            height: 1px;
            background: #000;
            transition: width 0.3s ease;
        }

        .nav-link:hover:after {
            width: 100%;
        }

        .nav-link.active {
            color: #000;
            font-weight: 500;
        }

        .nav-link.active:after {
            width: 100%;
        }

        /* Main Content */
        .main-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 4rem 2rem;
            min-height: 60vh;
        }

        /* Page Header */
        .page-header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .page-title {
            font-size: 3rem;
            font-weight: 300;
            letter-spacing: 8px;
            margin: 0 0 1rem;
            text-transform: uppercase;
            color: #000;
        }

        .page-subtitle {
            font-size: 1rem;
            color: #666;
            letter-spacing: 2px;
            max-width: 600px;
            margin: 0 auto;
            line-height: 1.8;
        }

        /* Contact Section */
        .contact-section {
            background: #ffffff;
            padding: 2rem 1rem;
            margin-bottom: 3rem;
            max-width: 1100px;
            margin-left: auto;
            margin-right: auto;
        }

        .contact-form {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .form-row {
            display: flex;
            flex-wrap: wrap;
            gap: 1.5rem;
            justify-content: space-between;
        }

        .form-group {
            flex: 1 1 calc(16.666% - 1.25rem);
            min-width: 140px;
            display: flex;
            flex-direction: column;
            gap: 0.3rem;
        }

        .form-group label {
            font-size: 0.75rem;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: #333;
            font-weight: 400;
            white-space: nowrap;
        }

        .form-group input,
        .form-group textarea {
            border: none;
            border-bottom: 1px solid #ccc;
            padding: 0.6rem 0.2rem;
            font-size: 0.9rem;
            background: transparent;
            transition: border 0.2s;
            outline: none;
            font-family: inherit;
            width: 100%;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            border-bottom-color: #000;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 60px;
        }

        .form-group.comment-group {
            flex: 2 1 300px;
        }

        .required-asterisk {
            color: #c00;
            font-weight: 400;
            margin-left: 2px;
        }

        .email-hint {
            font-size: 0.65rem;
            color: #666;
            margin-top: 0.2rem;
            font-style: italic;
        }

        .submit-container {
            display: flex;
            justify-content: flex-end;
            margin-top: 1rem;
        }

        .submit-btn {
            background: #000;
            color: white;
            border: none;
            padding: 0.8rem 2.5rem;
            font-size: 0.9rem;
            letter-spacing: 3px;
            text-transform: uppercase;
            cursor: pointer;
            transition: all 0.3s ease;
            min-width: 150px;
        }

        .submit-btn:hover {
            background: #333;
            letter-spacing: 4px;
        }

        .submit-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        /* Alert Messages */
        .alert {
            padding: 1rem;
            margin-bottom: 2rem;
            border-radius: 4px;
            text-align: center;
            letter-spacing: 1px;
        }

        .alert-success {
            background: #f0f8f0;
            color: #2e7d32;
            border: 1px solid #c8e6c9;
        }

        .alert-error {
            background: #fff3f3;
            color: #c62828;
            border: 1px solid #ffcdd2;
        }

        /* Contact Info */
        .contact-info {
            display: flex;
            justify-content: center;
            gap: 4rem;
            flex-wrap: wrap;
            margin-top: 3rem;
            padding-top: 2rem;
            border-top: 1px solid #f0f0f0;
        }

        .info-item {
            text-align: center;
        }

        .info-icon {
            font-size: 1.8rem;
            color: #000;
            margin-bottom: 0.8rem;
        }

        .info-title {
            font-size: 0.8rem;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: #666;
            margin-bottom: 0.3rem;
        }

        .info-text {
            color: #333;
            font-size: 0.95rem;
            letter-spacing: 0.5px;
        }

        /* Login Status Banner */
        .login-status {
            background: #f0f7ff;
            border-left: 4px solid #2196f3;
            padding: 0.75rem 1rem;
            margin-bottom: 2rem;
            border-radius: 6px;
            font-size: 0.85rem;
            text-align: center;
        }

        .login-status.logged-in {
            background: #e8f5e8;
            border-left-color: #4caf50;
            color: #2e7d32;
        }

        .login-status.guest {
            background: #fff3e0;
            border-left-color: #ff9800;
            color: #e65100;
        }

        /* Footer */
        .minimal-footer {
            border-top: 1px solid #f0f0f0;
            padding: 3rem 2rem 2rem;
            background: #fafafa;
        }

        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1.5rem;
        }

        .footer-links {
            display: flex;
            gap: 2.5rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .footer-link {
            text-decoration: none;
            color: #333;
            font-size: 1rem;
            letter-spacing: 2px;
            text-transform: uppercase;
            transition: color 0.2s;
            border-bottom: 1px dotted transparent;
        }

        .footer-link:hover {
            color: #000;
            border-bottom-color: #999;
        }

        .footer-text {
            color: #666;
            font-size: 0.9rem;
            letter-spacing: 1px;
            margin: 0.2rem 0;
            text-align: center;
            width: 100%;
            display: block;
        }

        .social-links {
            display: flex;
            gap: 2rem;
            justify-content: center;
            margin: 1rem 0;
        }

        .social-link {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: #000;
            color: white;
            font-size: 1.8rem;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .social-link:hover {
            background: #333;
            transform: translateY(-5px);
        }

        .social-link i {
            font-size: 1.8rem;
        }

        /* Loading Bar */
        .loading-bar {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background: linear-gradient(90deg, #000 0%, #666 50%, #000 100%);
            background-size: 200% 100%;
            animation: loading 2s ease-in-out infinite;
            z-index: 999;
            display: none;
        }

        @keyframes loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }

        /* Responsive Design */
        @media only screen and (max-width: 992px) {
            .form-group {
                flex: 1 1 calc(33.333% - 1rem);
            }
            
            .form-group.comment-group {
                flex: 1 1 100%;
            }
        }

        @media only screen and (max-width: 768px) {
            .minimal-header {
                flex-direction: column;
                gap: 1rem;
                padding: 1rem;
            }

            .minimal-nav ul {
                gap: 1rem;
                flex-wrap: wrap;
                justify-content: center;
            }

            .page-title {
                font-size: 2.2rem;
                letter-spacing: 4px;
            }

            .form-group {
                flex: 1 1 calc(50% - 0.75rem);
                min-width: 120px;
            }

            .form-group label {
                font-size: 0.7rem;
            }

            .contact-info {
                gap: 2rem;
            }
            
            .submit-container {
                justify-content: center;
            }
            
            /* Ensure footer text is centered on tablet */
            .footer-text {
                text-align: center !important;
                width: 100% !important;
            }
        }

        @media only screen and (max-width: 576px) {
            .main-content {
                padding: 2rem 1rem;
            }

            .page-title {
                font-size: 1.8rem;
            }

            .form-group {
                flex: 1 1 100%;
            }

            .logo-image {
                height: 30px;
            }

            .logo-text {
                font-size: 1.2rem;
            }
            
            /* Force center footer text on mobile */
            .footer-text {
                text-align: center !important;
                width: 100% !important;
                display: block !important;
            }
        }
    </style>
</head>
<body>
    <!-- Loading Animation -->
    <div class="loading-bar"></div>

    <!-- Minimal Header -->
    <header class="minimal-header">
        <div class="header-left">
            <div class="logo-container">
                <a href="index.php" class="logo-link">
                    <img src="images/logo.jpg" alt="MONEYWASTE Logo" class="logo-image" onerror="this.src='https://via.placeholder.com/40x40?text=MW'">
                    <span class="logo-text">MONEYWASTE</span>
                </a>
            </div>
        </div>

        <nav class="minimal-nav">
            <ul class="nav-list">
                <li><a href="index.php" class="nav-link">Home</a></li>
                <li><a href="products.php" class="nav-link">Collection</a></li>
                <li><a href="about.php" class="nav-link">About</a></li>
                <li><a href="contact.php" class="nav-link active">Contact</a></li>
                <?php if(isLoggedIn()): ?>
                    <li><a href="logout.php" class="nav-link">Logout</a></li>
                    <?php if(isset($_SESSION['username']) && $_SESSION['username'] == 'admin'): ?>
                        <li><a href="admin/dashboard.php" class="nav-link">Admin</a></li>
                    <?php endif; ?>
                <?php else: ?>
                    <li><a href="login.php" class="nav-link">Login</a></li>
                    <li><a href="signup.php" class="nav-link">Register</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </header>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">CONTACT</h1>
            <p class="page-subtitle">Get in touch with us. We'd love to hear from you.</p>
        </div>

        <!-- Login Status Banner -->
        <?php if(isLoggedIn()): ?>
            <div class="login-status logged-in">
                <i class="fas fa-user-check"></i> You are logged in as <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>. Your message will be linked to your account and you will receive notifications when we reply.
            </div>
        <?php else: ?>
            <div class="login-status guest">
                <i class="fas fa-user-friends"></i> You are sending as a guest. 
                <a href="login.php" style="color: #e65100; text-decoration: underline;">Login</a> or 
                <a href="signup.php" style="color: #e65100; text-decoration: underline;">Register</a> to receive notifications when we reply to your message!
            </div>
        <?php endif; ?>

        <!-- Contact Section -->
        <div class="contact-section">
            <?php if($message_sent): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle" style="margin-right: 8px;"></i>
                    Thank you for your message! We'll get back to you soon.
                </div>
            <?php endif; ?>

            <?php if($error_message): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle" style="margin-right: 8px;"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <form class="contact-form" method="POST" action="contact.php" id="contactForm">
                <div class="form-row">
                    <div class="form-group">
                        <label>YOUR NAME <span class="required-asterisk">*</span></label>
                        <input type="text" name="first_name" id="first_name" value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>" required placeholder="John">
                    </div>

                    <div class="form-group">
                        <label>YOUR LAST NAME <span class="required-asterisk">*</span></label>
                        <input type="text" name="last_name" id="last_name" value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>" required placeholder="Doe">
                    </div>

                    <div class="form-group">
                        <label>PHONE NUMBER</label>
                        <input type="tel" name="phone" id="phone" value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>" placeholder="09123456789">
                    </div>

                    <div class="form-group">
                        <label>SUBJECT <span class="required-asterisk">*</span></label>
                        <input type="text" name="subject" id="subject" value="<?php echo isset($_POST['subject']) ? htmlspecialchars($_POST['subject']) : ''; ?>" required placeholder="Inquiry">
                    </div>

                    <div class="form-group">
                        <label>EMAIL <span class="required-asterisk">*</span></label>
                        <input type="email" name="email" id="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required placeholder="username@gmail.com">
                        <div class="email-hint">Only Gmail addresses (@gmail.com) are accepted</div>
                    </div>

                    <div class="form-group comment-group">
                        <label>COMMENT</label>
                        <textarea name="comment" id="comment" rows="2" placeholder="Your message here..."><?php echo isset($_POST['comment']) ? htmlspecialchars($_POST['comment']) : ''; ?></textarea>
                    </div>
                </div>

                <div class="submit-container">
                    <button type="submit" class="submit-btn" id="submitBtn">SEND</button>
                </div>
            </form>
        </div>

        <!-- Contact Information -->
        <div class="contact-info">
            <div class="info-item">
                <div class="info-icon">
                    <i class="far fa-envelope"></i>
                </div>
                <div class="info-title">Email</div>
                <div class="info-text">mnywstdclthng@gmail.com</div>
            </div>

            <div class="info-item">
                <div class="info-icon">
                    <i class="fas fa-map-marker-alt"></i>
                </div>
                <div class="info-title">Location</div>
                <div class="info-text">Metro Manila, Philippines</div>
            </div>

            <div class="info-item">
                <div class="info-icon">
                    <i class="far fa-clock"></i>
                </div>
                <div class="info-title">Hours</div>
                <div class="info-text">Mon-Fri: 9AM - 6PM</div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="minimal-footer">
        <div class="footer-content">
            <div class="footer-links">
                <a href="about.php" class="footer-link">About</a>
                <a href="contact.php" class="footer-link">Contact</a>
            </div>

            <div class="social-links">
                <a href="https://www.instagram.com/moneywasteofficial" target="_blank" class="social-link"><i class="fab fa-instagram"></i></a>
                <a href="#" target="_blank" class="social-link"><i class="fab fa-facebook-f"></i></a>
            </div>

            <p class="footer-text">MONEYWASTE © 2024</p>
            <p class="footer-text">Minimal streetwear for conscious consumers</p>
            <p class="footer-text">Quality garments designed to last</p>
        </div>
    </footer>

    <script>
        // Show loading bar on page load
        document.addEventListener('DOMContentLoaded', function() {
            const loadingBar = document.querySelector('.loading-bar');
            loadingBar.style.display = 'block';

            setTimeout(() => {
                loadingBar.style.display = 'none';
            }, 500);
        });

        // Real-time Gmail validation
        document.getElementById('email').addEventListener('input', function(e) {
            const email = this.value;
            const gmailPattern = /^[a-zA-Z0-9._%+-]+@gmail\.com$/i;
            
            if (email && !gmailPattern.test(email)) {
                this.style.borderBottomColor = '#f44336';
                this.setCustomValidity('Only Gmail addresses (@gmail.com) are allowed');
            } else {
                this.style.borderBottomColor = '#ccc';
                this.setCustomValidity('');
            }
        });

        // Form submission with validation
        document.getElementById('contactForm').addEventListener('submit', function(e) {
            const email = document.getElementById('email').value;
            const gmailPattern = /^[a-zA-Z0-9._%+-]+@gmail\.com$/i;
            
            if (email && !gmailPattern.test(email)) {
                e.preventDefault();
                alert('Please use a valid Gmail address (@gmail.com)');
                return false;
            }
            
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.innerHTML = 'SENDING...';
            submitBtn.disabled = true;
            document.querySelector('.loading-bar').style.display = 'block';
        });

        // Add smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                const targetId = this.getAttribute('href');
                if (targetId === '#') return;

                const targetElement = document.querySelector(targetId);
                if (targetElement) {
                    window.scrollTo({
                        top: targetElement.offsetTop - 80,
                        behavior: 'smooth'
                    });
                }
            });
        });

        // Auto-format phone number
        document.querySelector('input[name="phone"]').addEventListener('input', function(e) {
            let phone = this.value.replace(/\D/g, '');
            if (phone.length > 11) phone = phone.slice(0, 11);
            this.value = phone;
        });

        // Auto-capitalize first letter of name fields
        document.querySelector('input[name="first_name"]').addEventListener('input', function(e) {
            if (this.value.length > 0) {
                this.value = this.value.charAt(0).toUpperCase() + this.value.slice(1).toLowerCase();
            }
        });

        document.querySelector('input[name="last_name"]').addEventListener('input', function(e) {
            if (this.value.length > 0) {
                this.value = this.value.charAt(0).toUpperCase() + this.value.slice(1).toLowerCase();
            }
        });
    </script>
</body>
</html>