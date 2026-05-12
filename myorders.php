<?php
require_once 'includes/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Get unread notification count for header
$unreadNotifications = 0;
try {
    $notifStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND status = 'unread'");
    $notifStmt->execute([$user_id]);
    $unreadNotifications = $notifStmt->fetchColumn();
} catch (PDOException $e) {
    $unreadNotifications = 0;
}

// Get all orders for the user
$ordersQuery = "
    SELECT o.*, 
           (SELECT COUNT(*) FROM notifications WHERE order_id = o.id AND user_id = o.user_id AND status = 'unread') as has_unread
    FROM orders o 
    WHERE o.user_id = ? 
    ORDER BY o.order_date DESC
";
$ordersStmt = $pdo->prepare($ordersQuery);
$ordersStmt->execute([$user_id]);
$orders = $ordersStmt->fetchAll(PDO::FETCH_ASSOC);

// Count unread notifications for badge
$unreadCount = $unreadNotifications;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - MONEYWASTE</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #fafafa;
            color: #333;
            line-height: 1.6;
        }

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

        .logo {
            font-size: 1.5rem;
            letter-spacing: 1px;
            font-weight: normal;
        }

        .minimal-nav ul {
            display: flex;
            list-style: none;
            gap: 2rem;
            margin: 0;
            padding: 0;
            align-items: center;
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

        /* Profile Dropdown */
        .profile-container {
            position: relative;
            display: inline-block;
        }

        .profile-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #000;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 1.2rem;
            transition: all 0.3s ease;
            border: 2px solid transparent;
            position: relative;
        }

        .profile-icon:hover {
            background: #333;
            transform: scale(1.05);
            border-color: #ddd;
        }

        .notification-dot {
            position: absolute;
            top: -2px;
            right: -2px;
            width: 12px;
            height: 12px;
            background: #ff4444;
            border-radius: 50%;
            border: 2px solid white;
            display: <?php echo $unreadNotifications > 0 ? 'block' : 'none'; ?>;
        }

        .profile-dropdown {
            position: absolute;
            top: 50px;
            right: 0;
            background: white;
            min-width: 280px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            border-radius: 8px;
            padding: 0.5rem 0;
            display: none;
            z-index: 1000;
            border: 1px solid #f0f0f0;
            animation: dropdownFade 0.2s ease;
        }

        .profile-dropdown.show {
            display: block;
        }

        @keyframes dropdownFade {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 20px;
            color: #333;
            text-decoration: none;
            transition: all 0.2s ease;
            font-size: 0.95rem;
            border-bottom: 1px solid #f5f5f5;
        }

        .dropdown-item:last-child {
            border-bottom: none;
        }

        .dropdown-item:hover {
            background: #fafafa;
            color: #000;
        }

        .dropdown-item i {
            width: 20px;
            font-size: 1.1rem;
            color: #666;
        }

        .dropdown-item:hover i {
            color: #000;
        }

        .dropdown-divider {
            height: 1px;
            background: #f0f0f0;
            margin: 0.5rem 0;
        }

        .user-greeting {
            padding: 15px 20px;
            background: #fafafa;
            font-size: 0.9rem;
            color: #666;
            border-bottom: 1px solid #f0f0f0;
            border-radius: 8px 8px 0 0;
            font-weight: 500;
        }

        .user-greeting span {
            color: #000;
            font-weight: 600;
        }

        .badge {
            background: #ff4444;
            color: white;
            border-radius: 12px;
            padding: 2px 8px;
            font-size: 0.7rem;
            margin-left: 8px;
        }

        /* Main Container */
        .container {
            max-width: 1200px;
            margin: 3rem auto;
            padding: 0 2rem;
        }

        .page-header {
            margin-bottom: 3rem;
            text-align: center;
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 300;
            letter-spacing: 4px;
            margin-bottom: 1rem;
            text-transform: uppercase;
            color: #000;
        }

        .page-subtitle {
            color: #666;
            font-size: 1rem;
            letter-spacing: 1px;
        }

        /* Orders Grid */
        .orders-grid {
            display: grid;
            gap: 2rem;
        }

        .order-card {
            background: white;
            border: 1px solid #f0f0f0;
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .order-card:hover {
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
        }

        .order-header {
            padding: 1.5rem;
            background: #fafafa;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .order-id {
            font-size: 1.1rem;
            font-weight: 500;
            color: #000;
        }

        .order-date {
            color: #999;
            font-size: 0.9rem;
        }

        .order-status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-pending {
            background: #fff3e0;
            color: #f57c00;
        }

        .status-processing {
            background: #e8eaf6;
            color: #3f51b5;
        }

        .status-shipped, .status-out_for_delivery {
            background: #e3f2fd;
            color: #1976d2;
        }

        .status-delivered {
            background: #e8f5e8;
            color: #2e7d32;
        }

        .status-cancelled {
            background: #ffebee;
            color: #c62828;
        }

        .order-body {
            padding: 1.5rem;
        }

        .order-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
            gap: 0.3rem;
        }

        .detail-label {
            font-size: 0.8rem;
            color: #999;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .detail-value {
            font-size: 1rem;
            color: #333;
        }

        .order-total {
            font-size: 1.2rem;
            font-weight: 500;
            color: #000;
            border-top: 1px solid #f0f0f0;
            padding-top: 1rem;
            text-align: right;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border: 1px solid #f0f0f0;
            border-radius: 12px;
        }

        .empty-state i {
            font-size: 4rem;
            color: #ddd;
            margin-bottom: 1.5rem;
        }

        .empty-state h3 {
            font-size: 1.5rem;
            font-weight: 300;
            color: #666;
            margin-bottom: 1rem;
        }

        .empty-state p {
            color: #999;
            margin-bottom: 2rem;
        }

        .btn {
            display: inline-block;
            padding: 12px 30px;
            background: #000;
            color: white;
            text-decoration: none;
            font-size: 0.9rem;
            letter-spacing: 1px;
            border: 1px solid #000;
            transition: all 0.3s ease;
        }

        .btn:hover {
            background: #333;
            border-color: #333;
        }

        /* Footer */
        .minimal-footer {
            border-top: 1px solid #f0f0f0;
            padding: 3rem 2rem 2rem;
            background: #fafafa;
            margin-top: 4rem;
        }

        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
            text-align: center;
        }

        .footer-text {
            color: #666;
            font-size: 0.9rem;
            letter-spacing: 1px;
            margin: 0.2rem 0;
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

        /* Responsive */
        @media (max-width: 768px) {
            .minimal-header {
                flex-direction: column;
                gap: 1rem;
                padding: 1rem;
            }

            .container {
                padding: 0 1rem;
            }

            .page-title {
                font-size: 2rem;
            }

            .order-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .profile-dropdown {
                position: fixed;
                top: auto;
                right: 1rem;
                width: calc(100% - 2rem);
                max-width: 280px;
            }
        }
    </style>
</head>
<body>
    <!-- Loading Animation -->
    <div class="loading-bar"></div>

    <header class="minimal-header">
        <div class="logo">MONEYWASTE</div>
        <nav class="minimal-nav">
            <ul class="nav-list">
                <li><a href="index.php" class="nav-link">Home</a></li>
                <li><a href="products.php" class="nav-link">Collection</a></li>
                <?php if(isset($_SESSION['username']) && $_SESSION['username'] == 'admin'): ?>
                    <li><a href="admin/dashboard.php" class="nav-link">Admin</a></li>
                <?php endif; ?>
                <!-- Profile Dropdown -->
                <li class="profile-container">
                    <div class="profile-icon" onclick="toggleProfileDropdown()">
                        <i class="fas fa-user"></i>
                        <?php if($unreadNotifications > 0): ?>
                            <span class="notification-dot"></span>
                        <?php endif; ?>
                    </div>
                    <div class="profile-dropdown" id="profileDropdown">
                        <div class="user-greeting">
                            Hello, <span><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                        </div>
                        <a href="profile.php" class="dropdown-item">
                            <i class="fas fa-user-circle"></i>
                            My Profile
                        </a>
                        <!-- GOES TO SEPARATE NOTIFICATIONS PAGE -->
                        <a href="notifications.php" class="dropdown-item">
                            <i class="fas fa-bell"></i>
                            Notifications
                            <?php if($unreadCount > 0): ?>
                                <span class="badge"><?php echo $unreadCount; ?></span>
                            <?php endif; ?>
                        </a>
                        <div class="dropdown-divider"></div>
                        <a href="logout.php" class="dropdown-item">
                            <i class="fas fa-sign-out-alt"></i>
                            Logout
                        </a>
                    </div>
                </li>
            </ul>
        </nav>
    </header>

    <main class="container">
        <div class="page-header">
            <h1 class="page-title">My Orders</h1>
            <p class="page-subtitle">Track and manage your orders</p>
        </div>

        <!-- Orders Only - No Notifications Tab -->
        <?php if(empty($orders)): ?>
            <div class="empty-state">
                <i class="fas fa-shopping-bag"></i>
                <h3>No orders yet</h3>
                <p>Looks like you haven't placed any orders yet.</p>
                <a href="products.php" class="btn">Start Shopping</a>
            </div>
        <?php else: ?>
            <div class="orders-grid">
                <?php foreach($orders as $order): ?>
                    <?php
                    $status_class = 'status-pending';
                    $status_lower = strtolower($order['order_status']);
                    
                    if (strpos($status_lower, 'process') !== false) {
                        $status_class = 'status-processing';
                    } elseif (strpos($status_lower, 'ship') !== false || strpos($status_lower, 'out') !== false) {
                        $status_class = 'status-shipped';
                    } elseif (strpos($status_lower, 'deliver') !== false) {
                        $status_class = 'status-delivered';
                    } elseif (strpos($status_lower, 'cancel') !== false) {
                        $status_class = 'status-cancelled';
                    }
                    ?>
                    <div class="order-card">
                        <div class="order-header">
                            <div>
                                <span class="order-id">Order #<?php echo $order['id']; ?></span>
                                <?php if($order['has_unread'] > 0): ?>
                                    <span class="badge" style="margin-left: 1rem;">New update</span>
                                <?php endif; ?>
                            </div>
                            <span class="order-status <?php echo $status_class; ?>">
                                <?php echo htmlspecialchars($order['order_status']); ?>
                            </span>
                        </div>
                        <div class="order-body">
                            <div class="order-details">
                                <div class="detail-item">
                                    <span class="detail-label">Order Date</span>
                                    <span class="detail-value">
                                        <?php echo date('F j, Y, g:i a', strtotime($order['order_date'])); ?>
                                    </span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Payment Method</span>
                                    <span class="detail-value">
                                        <?php echo ucwords(str_replace('_', ' ', $order['payment_method'])); ?>
                                    </span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Shipping Address</span>
                                    <span class="detail-value">
                                        <?php echo htmlspecialchars($order['customer_address']); ?>, 
                                        <?php echo htmlspecialchars($order['customer_city']); ?> 
                                        <?php echo htmlspecialchars($order['customer_postal']); ?>
                                    </span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Contact</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($order['customer_phone']); ?></span>
                                </div>
                            </div>
                            <div class="order-total">
                                Total: ₱<?php echo number_format($order['total_amount'], 2); ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <footer class="minimal-footer">
        <div class="footer-content">
            <div class="footer-links">
                <a href="about.php" class="footer-link">About</a>
                <a href="contact.php" class="footer-link">Contact</a>
            </div>
            <div class="social-links">
                <a href="https://www.instagram.com/moneywasteofficial" target="_blank" class="social-link"><i class="fab fa-instagram"></i></a>
                <a href="https://www.facebook.com/share/18C3mzvp6a/" target="_blank" class="social-link"><i class="fab fa-facebook-f"></i></a>
            </div>
            <p class="footer-text">MONEYWASTE © 2024</p>
            <p class="footer-text">Minimal streetwear for conscious consumers</p>
        </div>
    </footer>

    <script>
        // Show loading bar on page load
        document.addEventListener('DOMContentLoaded', function() {
            const loadingBar = document.querySelector('.loading-bar');
            if (loadingBar) {
                loadingBar.style.display = 'block';
                setTimeout(() => {
                    loadingBar.style.display = 'none';
                }, 500);
            }
        });

        // Toggle profile dropdown
        function toggleProfileDropdown() {
            const dropdown = document.getElementById('profileDropdown');
            dropdown.classList.toggle('show');
            event.stopPropagation();
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const profileContainer = document.querySelector('.profile-container');
            const dropdown = document.getElementById('profileDropdown');
            
            if (profileContainer && dropdown && !profileContainer.contains(event.target)) {
                dropdown.classList.remove('show');
            }
        });
    </script>
</body>
</html>