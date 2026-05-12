<?php
require_once 'includes/config.php';

// Function to get product image with fallback
function getProductImage($product) {
    // Check if product has an uploaded image
    if (!empty($product['image_url']) && file_exists('images/' . $product['image_url'])) {
        return $product['image_url'];
    }
    
    // Try to find a default image based on product name
    $productName = strtolower($product['name']);
    
    if (strpos($productName, 'hoodie') !== false) {
        return 'jacket1.jpg';
    } elseif (strpos($productName, 'black') !== false && (strpos($productName, 'tee') !== false || strpos($productName, 't-shirt') !== false)) {
        return 'tshirtblk.jpg';
    } elseif (strpos($productName, 'white') !== false && (strpos($productName, 'tee') !== false || strpos($productName, 't-shirt') !== false)) {
        return 'tshirtwht.jpg';
    } elseif (strpos($productName, 'towel') !== false) {
        return 'featured7.png';
    }
    
    return null;
}

// ========== CREATE product_ratings table if not exists ==========
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS product_ratings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NULL,
        product_id INT NOT NULL,
        user_id INT NOT NULL,
        rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
        review TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_product_id (product_id),
        INDEX idx_user_id (user_id)
    )");
} catch (PDOException $e) {}

// Function to get product ratings with detailed stats
function getProductRatings($pdo, $product_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                COALESCE(AVG(rating), 0) as avg_rating,
                COUNT(*) as total_ratings,
                COUNT(CASE WHEN rating = 5 THEN 1 END) as five_star,
                COUNT(CASE WHEN rating = 4 THEN 1 END) as four_star,
                COUNT(CASE WHEN rating = 3 THEN 1 END) as three_star,
                COUNT(CASE WHEN rating = 2 THEN 1 END) as two_star,
                COUNT(CASE WHEN rating = 1 THEN 1 END) as one_star
            FROM product_ratings 
            WHERE product_id = ?
        ");
        $stmt->execute([$product_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return ['avg_rating' => 0, 'total_ratings' => 0, 'five_star' => 0, 'four_star' => 0, 'three_star' => 0, 'two_star' => 0, 'one_star' => 0];
    }
}

// Function to get recent reviews - UPDATED to show ONLY 1 review
function getRecentReviews($pdo, $product_id, $limit = 1) {
    try {
        $limit = (int)$limit;
        $stmt = $pdo->prepare("
            SELECT r.*, u.username 
            FROM product_ratings r
            LEFT JOIN users u ON r.user_id = u.id
            WHERE r.product_id = ?
            ORDER BY r.created_at DESC
            LIMIT $limit
        ");
        $stmt->execute([$product_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

// Get all unique categories from products
$categoryQuery = "SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category != '' ORDER BY category";
$categoryStmt = $pdo->query($categoryQuery);
$categories = $categoryStmt->fetchAll(PDO::FETCH_COLUMN);

// Get current category filter
$currentCategory = isset($_GET['category']) ? $_GET['category'] : 'all';

// Build query based on category filter
if ($currentCategory == 'all' || empty($currentCategory)) {
    $query = "SELECT * FROM products ORDER BY created_at DESC";
    $products = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);
} else {
    $query = "SELECT * FROM products WHERE category = ? ORDER BY created_at DESC";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$currentCategory]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to check if user is logged in
if (!function_exists('isLoggedIn')) {
    function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
}

// Function to check if user is approved
if (!function_exists('isApprovedUser')) {
    function isApprovedUser() {
        return isset($_SESSION['approved']) && $_SESSION['approved'] === true;
    }
}

// Get unread notifications count for logged-in user
$unreadNotifications = 0;
$recentNotifications = [];
if (isLoggedIn()) {
    $user_id = $_SESSION['user_id'];
    
    try {
        $checkTable = $pdo->query("SHOW TABLES LIKE 'notifications'");
        if ($checkTable->rowCount() == 0) {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS notifications (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    order_id INT NULL,
                    message TEXT NOT NULL,
                    status VARCHAR(50) DEFAULT 'unread',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                )
            ");
        }
    } catch (PDOException $e) {}
    
    $notifStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND status = 'unread'");
    $notifStmt->execute([$user_id]);
    $unreadNotifications = $notifStmt->fetchColumn();
    
    $recentStmt = $pdo->prepare("
        SELECT n.*, 
               CASE 
                   WHEN n.order_id IS NOT NULL THEN o.order_status 
                   ELSE 'message' 
               END as order_status
        FROM notifications n 
        LEFT JOIN orders o ON n.order_id = o.id 
        WHERE n.user_id = ? 
        ORDER BY n.created_at DESC 
        LIMIT 5
    ");
    $recentStmt->execute([$user_id]);
    $recentNotifications = $recentStmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get unread messages count for admin
$unread_msgs = 0;
if (isLoggedIn() && isset($_SESSION['username']) && $_SESSION['username'] == 'admin') {
    try {
        $msgStmt = $pdo->query("SELECT COUNT(*) FROM contact_messages WHERE status = 'unread'");
        $unread_msgs = $msgStmt->fetchColumn();
    } catch (PDOException $e) {
        $unread_msgs = 0;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes, viewport-fit=cover">
    <title>MONEYWASTE — Collection</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* All your existing styles remain the same */
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
            height: 60px;
            width: auto;
            display: block;
            transition: opacity 0.3s ease;
        }

        .logo-image:hover {
            opacity: 0.8;
        }

        .logo-text {
            font-size: 2.2rem;
            letter-spacing: 2px;
            font-weight: 600;
            white-space: nowrap;
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

        /* Admin Button */
        .admin-nav-link {
            background: #000;
            color: white !important;
            padding: 0.6rem 1.5rem !important;
            border-radius: 30px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
            border: 1px solid #000;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }

        .admin-nav-link:hover {
            background: #333;
            color: white !important;
            border-color: #333;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }

        .admin-nav-link:hover:after {
            display: none;
        }

        .admin-nav-link i {
            color: #ffd700;
            font-size: 1rem;
        }

        .unread-message-badge {
            background: #ff4444;
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 0.7rem;
            margin-left: 5px;
            min-width: 18px;
            text-align: center;
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

        .profile-icon i {
            font-size: 1.3rem;
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
            min-width: 320px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            border-radius: 12px;
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

        .dropdown-item i {
            width: 20px;
            font-size: 1.1rem;
            color: #666;
            transition: color 0.2s ease;
        }

        .dropdown-item:hover {
            background: #fafafa;
            color: #000;
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
            border-radius: 12px 12px 0 0;
            font-weight: 500;
        }

        .user-greeting span {
            color: #000;
            font-weight: 600;
        }

        .notification-badge {
            background: #ff4444;
            color: white;
            border-radius: 20px;
            padding: 2px 10px;
            font-size: 0.7rem;
            margin-left: auto;
            min-width: 18px;
            text-align: center;
            font-weight: bold;
        }

        .notification-item {
            padding: 15px 20px;
            border-bottom: 1px solid #f5f5f5;
            cursor: pointer;
            transition: background 0.2s ease;
        }

        .notification-item:hover {
            background: #fafafa;
        }

        .notification-item.unread {
            background: #f0f7ff;
            border-left: 3px solid #2196f3;
        }

        .notification-item.unread:hover {
            background: #e6f0fe;
        }

        .notification-message {
            font-size: 0.9rem;
            color: #333;
            margin-bottom: 6px;
            line-height: 1.4;
        }

        .notification-time {
            font-size: 0.7rem;
            color: #999;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .view-all-link {
            display: block;
            text-align: center;
            padding: 15px;
            color: #000;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            border-top: 1px solid #f0f0f0;
            transition: background 0.2s ease;
            border-radius: 0 0 12px 12px;
        }

        .view-all-link:hover {
            background: #fafafa;
        }

        .no-notifications {
            padding: 30px 20px;
            text-align: center;
            color: #999;
            font-size: 0.9rem;
        }

        /* Page header styles */
        .page-header {
            text-align: center;
            padding: 4rem 2rem 2rem;
            background: white;
            max-width: 800px;
            margin: 0 auto;
        }
        
        .page-title {
            font-size: 2.5rem;
            color: #000;
            letter-spacing: 4px;
            text-transform: uppercase;
            margin-bottom: 1.5rem;
            font-weight: 300;
            position: relative;
        }
        
        .page-title:after {
            content: '';
            display: block;
            width: 60px;
            height: 1px;
            background: #000;
            margin: 1.5rem auto 0;
        }
        
        .page-subtitle {
            font-size: 1rem;
            color: #666;
            line-height: 1.8;
            font-weight: 300;
            letter-spacing: 0.5px;
        }
        
        /* Category Navigation */
        .category-nav {
            max-width: 1200px;
            margin: 3rem auto 2rem;
            padding: 0 2rem;
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 0.5rem;
            border-bottom: 1px solid #f0f0f0;
            padding-bottom: 2rem;
        }
        
        .category-link {
            padding: 8px 20px;
            background: transparent;
            color: #999;
            text-decoration: none;
            font-size: 0.85rem;
            letter-spacing: 1px;
            transition: all 0.3s ease;
            text-transform: uppercase;
            border: none;
            position: relative;
        }
        
        .category-link:hover {
            color: #000;
        }
        
        .category-link.active {
            color: #000;
            font-weight: 500;
        }
        
        .category-link.active:after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 20px;
            right: 20px;
            height: 1px;
            background: #000;
        }
        
        .filter-container {
            max-width: 1200px;
            margin: 2rem auto 3rem;
            padding: 0 2rem;
            display: flex;
            justify-content: flex-start;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .products-count {
            font-size: 0.85rem;
            color: #999;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        
        /* Products grid */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 2.5rem;
            max-width: 1400px;
            margin: 0 auto 5rem;
            padding: 0 2rem;
        }
        
        .product-card {
            background: white;
            transition: all 0.4s ease;
            cursor: pointer;
            border: 1px solid #f0f0f0;
            padding: 1.5rem;
            border-radius: 12px;
        }
        
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            border-color: transparent;
        }
        
        .product-image-container {
            margin-bottom: 1.5rem;
            position: relative;
            overflow: hidden;
            height: 280px;
            background: linear-gradient(135deg, #f5f5f5 0%, #e8e8e8 100%);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .product-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.6s ease;
        }
        
        .product-card:hover .product-image {
            transform: scale(1.03);
        }
        
        /* Rating Stars Styles */
        .product-rating {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 10px 0;
            flex-wrap: wrap;
        }
        
        .stars {
            display: inline-flex;
            gap: 2px;
            color: #ffc107;
            font-size: 0.85rem;
        }
        
        .stars i.far {
            color: #ddd;
        }
        
        .rating-count {
            font-size: 0.7rem;
            color: #999;
            cursor: pointer;
        }
        
        .rating-count:hover {
            text-decoration: underline;
        }
        
        .reviews-preview {
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid #f0f0f0;
        }
        
        .review-item {
            font-size: 0.75rem;
            color: #666;
            margin-bottom: 8px;
            line-height: 1.4;
        }
        
        .review-stars {
            display: inline-flex;
            gap: 1px;
            font-size: 0.6rem;
            color: #ffc107;
            margin-right: 5px;
        }
        
        .default-placeholder {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #f8f8f8 0%, #e8e8e8 100%);
            text-align: center;
        }
        
        .default-placeholder i {
            font-size: 4rem;
            color: #ccc;
            margin-bottom: 0.5rem;
        }
        
        .default-placeholder span {
            font-size: 0.8rem;
            color: #999;
            letter-spacing: 2px;
            text-transform: uppercase;
        }
        
        .product-category-badge {
            font-size: 0.7rem;
            color: #999;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-bottom: 0.5rem;
        }
        
        .product-title {
            font-size: 1.2rem;
            color: #000;
            margin-bottom: 0.8rem;
            letter-spacing: 1px;
            font-weight: 400;
            line-height: 1.4;
        }
        
        .product-description {
            color: #999;
            font-size: 0.85rem;
            line-height: 1.7;
            margin-bottom: 1.2rem;
            min-height: 60px;
            font-weight: 300;
            letter-spacing: 0.3px;
        }
        
        .product-price {
            font-size: 1.2rem;
            color: #000;
            margin-bottom: 1.5rem;
            font-weight: 400;
            letter-spacing: 1px;
        }
        
        .product-buttons {
            display: flex;
            gap: 12px;
            margin-top: 1rem;
        }
        
        .minimal-btn {
            flex: 1;
            padding: 12px 0;
            border: 1px solid #e0e0e0;
            background: white;
            color: #333;
            cursor: pointer;
            font-size: 0.8rem;
            transition: all 0.3s ease;
            text-align: center;
            letter-spacing: 1px;
            text-transform: uppercase;
            border-radius: 6px;
        }
        
        .minimal-btn:hover {
            border-color: #000;
            background: #fcfcfc;
        }
        
        .buy-now-btn {
            flex: 1;
            padding: 12px 0;
            border: 1px solid #000;
            background: #000;
            color: white;
            cursor: pointer;
            font-size: 0.8rem;
            transition: all 0.3s ease;
            text-align: center;
            letter-spacing: 1px;
            text-transform: uppercase;
            border-radius: 6px;
        }
        
        .buy-now-btn:hover {
            background: #333;
            border-color: #333;
        }
        
        .empty-state {
            text-align: center;
            padding: 5rem 2rem;
            grid-column: 1 / -1;
        }
        
        .empty-state p {
            font-size: 1rem;
            color: #999;
            margin-bottom: 1.5rem;
            letter-spacing: 1px;
        }
        
        .empty-state a {
            color: #000;
            text-decoration: none;
            font-size: 0.85rem;
            letter-spacing: 1px;
            text-transform: uppercase;
            padding: 12px 30px;
            border: 1px solid #000;
            transition: all 0.3s ease;
        }
        
        /* ========== FIXED: Modal styles for mobile ========== */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.85);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(4px);
        }
        
        .modal-content {
            background: white;
            padding: 2rem;
            max-width: 90%;
            width: 340px;
            text-align: center;
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
            animation: modalSlideIn 0.3s ease;
        }
        
        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-20px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
        
        .modal-content h3 {
            font-size: 1.3rem;
            font-weight: 500;
            letter-spacing: 0px;
            margin-bottom: 0.75rem;
            color: #000;
        }
        
        .modal-content p {
            color: #666;
            font-size: 0.9rem;
            line-height: 1.5;
            margin-bottom: 1rem;
            font-weight: 400;
        }
        
        .modal-content strong {
            font-weight: 600;
            color: #000;
        }
        
        .modal-buttons {
            display: flex;
            gap: 12px;
            margin-top: 1.5rem;
            flex-direction: row;
        }
        
        .modal-btn {
            flex: 1;
            padding: 12px 0;
            border: 1px solid #ccc;
            background: white;
            cursor: pointer;
            font-size: 0.9rem;
            letter-spacing: 0.5px;
            font-weight: 500;
            transition: all 0.2s ease;
            border-radius: 8px;
            text-align: center;
        }

        .modal-btn:hover {
            border-color: #999;
            background: #f5f5f5;
        }
        
        .modal-cancel {
            background: white;
            color: #333;
            border: 1px solid #ddd;
        }
        
        .modal-confirm {
            background: #000;
            color: white;
            border-color: #000;
        }

        .modal-confirm:hover {
            background: #333;
            border-color: #333;
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
            gap: 2rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .footer-link {
            text-decoration: none;
            color: #333;
            font-size: 0.9rem;
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
            font-size: 0.85rem;
            letter-spacing: 1px;
            margin: 0.2rem 0;
            text-align: center;
        }
        
        .social-links {
            display: flex;
            gap: 1.5rem;
            justify-content: center;
            margin: 0.5rem 0;
        }
        
        .social-link {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: #000;
            color: white;
            font-size: 1.5rem;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        
        .social-link:hover {
            background: #333;
            transform: translateY(-3px);
        }
        
        .social-link i {
            font-size: 1.5rem;
        }
        
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
        
        /* ========== Product Detail Modal Styles ========== */
        .product-detail-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.95);
            z-index: 2000;
            justify-content: center;
            align-items: center;
            overflow-y: auto;
        }
        
        .product-detail-content {
            background: white;
            max-width: 900px;
            width: 90%;
            margin: 40px auto;
            border-radius: 20px;
            overflow: hidden;
            animation: modalSlideIn 0.3s ease;
        }
        
        .product-detail-header {
            position: relative;
            background: #000;
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .close-detail-modal {
            position: absolute;
            top: 20px;
            right: 20px;
            background: none;
            border: none;
            color: white;
            font-size: 1.8rem;
            cursor: pointer;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s;
        }
        
        .close-detail-modal:hover {
            background: rgba(255,255,255,0.2);
        }
        
        .product-detail-body {
            padding: 30px;
            max-height: 70vh;
            overflow-y: auto;
        }
        
        /* Rating bars */
        .rating-bar {
            flex: 1;
            height: 8px;
            background: #e0e0e0;
            border-radius: 4px;
            overflow: hidden;
        }
        
        .rating-bar-fill {
            height: 100%;
            background: #ffc107;
            border-radius: 4px;
        }
        
        /* Responsive Design */
        @media only screen and (max-width: 992px) {
            .minimal-header {
                flex-direction: column;
                gap: 1rem;
                padding: 1rem;
            }
            
            .header-left {
                width: 100%;
                justify-content: space-between;
                align-items: center;
            }
            
            .logo-image {
                height: 45px;
            }
            
            .logo-text {
                font-size: 1.5rem;
            }
        }
        
        @media only screen and (max-width: 768px) {
            .minimal-header {
                padding: 1rem;
            }
            
            .logo-image {
                height: 40px;
            }
            
            .logo-text {
                font-size: 1.3rem;
            }
            
            .minimal-nav ul {
                gap: 1rem;
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .nav-link {
                font-size: 0.8rem;
            }
            
            .profile-icon {
                width: 35px;
                height: 35px;
            }
            
            .profile-icon i {
                font-size: 1rem;
            }
            
            .profile-dropdown {
                position: fixed;
                right: 1rem;
                width: calc(100% - 2rem);
                max-width: 300px;
            }
            
            .category-nav {
                padding: 0 1rem 1.5rem;
                margin: 2rem auto 1.5rem;
                gap: 0.3rem;
            }
            
            .category-link {
                padding: 6px 12px;
                font-size: 0.75rem;
            }
            
            .filter-container {
                padding: 0 1.5rem;
                margin: 1rem auto 2rem;
            }
            
            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
                gap: 1.5rem;
                padding: 0 1.5rem;
            }
            
            .product-card {
                padding: 1rem;
            }
            
            .product-image-container {
                height: 240px;
            }
            
            .page-header {
                padding: 3rem 1rem 1.5rem;
            }
            
            .page-title {
                font-size: 2rem;
            }
            
            .modal-buttons {
                flex-direction: row;
            }
        }
        
        @media only screen and (max-width: 480px) {
            .logo-image {
                height: 35px;
            }
            
            .logo-text {
                font-size: 1.1rem;
            }
            
            .minimal-nav ul {
                gap: 0.7rem;
            }
            
            .nav-link {
                font-size: 0.7rem;
            }
            
            .page-title {
                font-size: 1.5rem;
            }
            
            .page-subtitle {
                font-size: 0.85rem;
            }
            
            .category-link {
                padding: 4px 10px;
                font-size: 0.7rem;
            }
            
            .products-grid {
                grid-template-columns: 1fr;
                padding: 0 1rem;
            }
            
            .product-image-container {
                height: 220px;
            }
            
            .product-title {
                font-size: 1rem;
            }
            
            .product-description {
                font-size: 0.8rem;
                min-height: 50px;
            }
            
            .product-price {
                font-size: 1rem;
            }
            
            .product-buttons {
                flex-direction: column;
                gap: 8px;
            }
            
            .modal-content {
                padding: 1.5rem;
                width: 85%;
            }
            
            .modal-buttons {
                gap: 10px;
            }
            
            .modal-btn {
                padding: 10px 0;
                font-size: 0.85rem;
            }
            
            .product-detail-content {
                width: 95%;
                margin: 20px auto;
            }
            
            .product-detail-header {
                padding: 20px;
            }
            
            .product-detail-body {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <!-- Loading Animation -->
    <div class="loading-bar"></div>
    
    <!-- Header -->
    <header class="minimal-header">
        <div class="header-left">
            <div class="logo-container">
                <a href="index.php" class="logo-link">
                    <img src="images/logo.jpg" alt="MONEYWASTE Logo" class="logo-image" onerror="this.src='https://via.placeholder.com/60x60?text=MW'">
                    <span class="logo-text">MONEYWASTE</span>
                </a>
            </div>
        </div>
        
        <nav class="minimal-nav">
            <ul class="nav-list">
                <li><a href="index.php" class="nav-link">Home</a></li>
                <li><a href="products.php" class="nav-link active">Collection</a></li>
                
                <?php if(isLoggedIn()): ?>
                    <?php if(isset($_SESSION['username']) && $_SESSION['username'] == 'admin'): ?>
                        <li>
                            <a href="admin/dashboard.php" class="nav-link admin-nav-link">
                                <i class="fas fa-tachometer-alt"></i>
                                <span>Admin</span>
                                <?php if($unread_msgs > 0): ?>
                                    <span class="unread-message-badge"><?php echo $unread_msgs; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
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
                                Hello, <span><?php echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'User'; ?></span>
                            </div>
                            
                            <a href="profile.php" class="dropdown-item">
                                <i class="fas fa-user-circle"></i>
                                My Profile
                            </a>
                            
                            <a href="myorders.php" class="dropdown-item">
                                <i class="fas fa-shopping-bag"></i>
                                My Orders
                            </a>
                            
                            <div class="dropdown-item" style="cursor: default; background: #fafafa; justify-content: space-between;">
                                <span>
                                    <i class="fas fa-bell"></i>
                                    Notifications
                                </span>
                                <?php if($unreadNotifications > 0): ?>
                                    <span class="notification-badge"><?php echo $unreadNotifications; ?> new</span>
                                <?php endif; ?>
                            </div>
                            
                            <div style="max-height: 300px; overflow-y: auto;">
                                <?php if(empty($recentNotifications)): ?>
                                    <div class="no-notifications">
                                        <i class="fas fa-bell-slash"></i>
                                        <p>No notifications yet</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach($recentNotifications as $notif): 
                                        $is_unread = $notif['status'] == 'unread';
                                    ?>
                                        <div class="notification-item <?php echo $is_unread ? 'unread' : ''; ?>" onclick="markNotificationRead(<?php echo $notif['id']; ?>)">
                                            <div class="notification-message">
                                                <?php echo htmlspecialchars($notif['message']); ?>
                                            </div>
                                            <div class="notification-time">
                                                <i class="far fa-clock"></i>
                                                <?php 
                                                $time = strtotime($notif['created_at']);
                                                $now = time();
                                                $diff = $now - $time;
                                                
                                                if ($diff < 60) {
                                                    echo 'Just now';
                                                } elseif ($diff < 3600) {
                                                    echo floor($diff / 60) . ' minutes ago';
                                                } elseif ($diff < 86400) {
                                                    echo floor($diff / 3600) . ' hours ago';
                                                } else {
                                                    echo date('M j, g:i A', $time);
                                                }
                                                ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            
                            <a href="notifications.php" class="view-all-link">
                                View All Notifications
                            </a>
                            
                            <div class="dropdown-divider"></div>
                            <a href="logout.php" class="dropdown-item">
                                <i class="fas fa-sign-out-alt"></i>
                                Logout
                            </a>
                        </div>
                    </li>
                <?php else: ?>
                    <li><a href="login.php" class="nav-link">Login</a></li>
                    <li><a href="signup.php" class="nav-link">Register</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </header>
    
    <!-- Page Header -->
    <section class="page-header">
        <h1 class="page-title">Collection</h1>
        <p class="page-subtitle">
            Minimalist streetwear crafted for those who appreciate the beauty of simplicity and quality of substance.
        </p>
    </section>
    
    <!-- Category Navigation -->
    <div class="category-nav">
        <a href="products.php?category=all" class="category-link <?php echo $currentCategory == 'all' ? 'active' : ''; ?>">
            All
        </a>
        <?php foreach($categories as $category): ?>
            <a href="products.php?category=<?php echo urlencode($category); ?>" 
               class="category-link <?php echo $currentCategory == $category ? 'active' : ''; ?>">
                <?php echo htmlspecialchars($category); ?>
            </a>
        <?php endforeach; ?>
    </div>
    
    <!-- Filter Container -->
    <div class="filter-container">
        <div class="products-count">
            <?php echo count($products); ?> <?php echo count($products) != 1 ? 'products' : 'product'; ?>
            <?php if($currentCategory != 'all' && !empty($currentCategory)): ?>
                in <?php echo htmlspecialchars($currentCategory); ?>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Products Grid -->
    <main class="products-grid">
        <?php if(count($products) > 0): ?>
            <?php foreach($products as $product): 
                $ratings = getProductRatings($pdo, $product['id']);
                // UPDATED: Show ONLY 1 review preview
                $recentReviews = getRecentReviews($pdo, $product['id'], 1);
                $avgRating = round($ratings['avg_rating'], 1);
                $totalRatings = $ratings['total_ratings'];
                $productImage = getProductImage($product);
                $hasImage = $productImage && file_exists('images/' . $productImage);
            ?>
                
                <div class="product-card" onclick="showProductDetail(<?php echo $product['id']; ?>)">
                    <div class="product-image-container">
                        <?php if($hasImage): ?>
                            <img src="images/<?php echo $productImage; ?>" 
                                 alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                 class="product-image">
                        <?php else: ?>
                            <div class="default-placeholder">
                                <i class="fas fa-tshirt"></i>
                                <span>MONEYWASTE</span>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if(!empty($product['category'])): ?>
                    <div class="product-category-badge">
                        <?php echo htmlspecialchars($product['category']); ?>
                    </div>
                    <?php endif; ?>
                    
                    <h3 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h3>
                    
                    <div class="product-rating">
                        <div class="stars">
                            <?php if($totalRatings > 0): ?>
                                <?php 
                                $fullStars = floor($avgRating);
                                $halfStar = ($avgRating - $fullStars) >= 0.5;
                                for($i = 1; $i <= 5; $i++):
                                    if($i <= $fullStars):
                                ?>
                                        <i class="fas fa-star"></i>
                                    <?php elseif($halfStar && $i == $fullStars + 1): ?>
                                        <i class="fas fa-star-half-alt"></i>
                                    <?php else: ?>
                                        <i class="far fa-star"></i>
                                    <?php endif; ?>
                                <?php endfor; ?>
                                <span class="rating-count">(<?php echo $totalRatings; ?> <?php echo $totalRatings == 1 ? 'review' : 'reviews'; ?>)</span>
                            <?php else: ?>
                                <?php for($i = 1; $i <= 5; $i++): ?>
                                    <i class="far fa-star"></i>
                                <?php endfor; ?>
                                <span class="rating-count">No reviews yet</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <p class="product-description">
                        <?php 
                        $desc = htmlspecialchars($product['description']);
                        echo strlen($desc) > 100 ? substr($desc, 0, 100) . '...' : $desc;
                        ?>
                    </p>
                    <div class="product-price">₱<?php echo number_format($product['price'], 2); ?></div>
                    
                    <?php if($totalRatings > 0 && !empty($recentReviews)): ?>
                        <div class="reviews-preview">
                            <?php foreach($recentReviews as $review): ?>
                                <div class="review-item">
                                    <div class="review-stars">
                                        <?php for($i = 1; $i <= 5; $i++): ?>
                                            <?php echo $i <= $review['rating'] ? '<i class="fas fa-star"></i>' : '<i class="far fa-star"></i>'; ?>
                                        <?php endfor; ?>
                                    </div>
                                    <strong><?php echo htmlspecialchars($review['username']); ?></strong>: 
                                    <?php echo htmlspecialchars(substr($review['review'], 0, 60)); ?><?php echo strlen($review['review']) > 60 ? '...' : ''; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="product-buttons" onclick="event.stopPropagation()">
                        <button class="minimal-btn" onclick="addToCart(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars($product['name']); ?>')">
                            Add to Cart
                        </button>
                        <button class="buy-now-btn" onclick="buyNow(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars($product['name']); ?>', <?php echo $product['price']; ?>)">
                            Buy Now
                        </button>
                    </div>
                </div>
                
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <p>No products found in this category</p>
                <a href="products.php?category=all">View all</a>
            </div>
        <?php endif; ?>
    </main>
    
    <!-- Product Detail Modal -->
    <div class="product-detail-modal" id="productDetailModal">
        <div class="product-detail-content">
            <div class="product-detail-header">
                <button class="close-detail-modal" onclick="closeProductDetail()">&times;</button>
                <h2>Product Reviews</h2>
            </div>
            <div class="product-detail-body" id="productDetailBody">
                <div style="text-align: center; padding: 40px;"><i class="fas fa-spinner fa-spin"></i> Loading...</div>
            </div>
        </div>
    </div>
    
    <!-- Login Modal -->
    <div class="modal-overlay" id="buyNowModal">
        <div class="modal-content">
            <h3 id="modalProductName">Login Required</h3>
            <p id="modalProductDetails">Please login to continue with your purchase.</p>
            <p><strong>Total: ₱<span id="modalProductPrice">0.00</span></strong></p>
            <div class="modal-buttons">
                <button class="modal-btn modal-cancel" onclick="closeModal()">Cancel</button>
                <button class="modal-btn modal-confirm" onclick="redirectToLogin()">Login Now</button>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
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
            <p class="footer-text">Quality garments designed to last</p>
        </div>
    </footer>
    
    <script>
    window.addEventListener('DOMContentLoaded', () => {
        const loadingBar = document.querySelector('.loading-bar');
        loadingBar.style.display = 'block';
        
        setTimeout(() => {
            loadingBar.style.display = 'none';
        }, 500);

        document.addEventListener('click', function(event) {
            const profileContainer = document.querySelector('.profile-container');
            const dropdown = document.getElementById('profileDropdown');
            
            if (profileContainer && dropdown && !profileContainer.contains(event.target)) {
                dropdown.classList.remove('show');
            }
        });
    });
    
    function toggleProfileDropdown() {
        const dropdown = document.getElementById('profileDropdown');
        dropdown.classList.toggle('show');
        if(event) event.stopPropagation();
    }
    
    function markNotificationRead(notificationId) {
        fetch('mark_notification_read.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'notification_id=' + notificationId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.href = 'myorders.php';
            }
        });
    }
    
    // UPDATED: showProductDetail now uses can_comment and ONLY shows comment form
    function showProductDetail(productId) {
        const modal = document.getElementById('productDetailModal');
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        
        document.getElementById('productDetailBody').innerHTML = '<div style="text-align: center; padding: 40px;"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
        
        fetch('get_product_details.php?product_id=' + productId)
            .then(response => response.json())
            .then(data => {
                const body = document.getElementById('productDetailBody');
                if (!data.success) {
                    body.innerHTML = '<div style="text-align: center; padding: 40px; color: red;">Error loading product details</div>';
                    return;
                }
                
                // Rating summary (display only)
                let ratingSummaryHtml = '';
                if (data.total_ratings > 0) {
                    const fivePercent = (data.five_star / data.total_ratings) * 100;
                    const fourPercent = (data.four_star / data.total_ratings) * 100;
                    const threePercent = (data.three_star / data.total_ratings) * 100;
                    const twoPercent = (data.two_star / data.total_ratings) * 100;
                    const onePercent = (data.one_star / data.total_ratings) * 100;
                    
                    ratingSummaryHtml = `
                        <div class="rating-summary" style="background: #f8f8f8; padding: 20px; border-radius: 12px; margin: 20px 0;">
                            <div style="text-align: center;">
                                <div class="big-rating" style="font-size: 2rem; font-weight: 700;">${data.avg_rating}</div>
                                <div class="big-stars" style="color: #ffc107;">${data.avg_rating_stars}</div>
                                <div style="font-size: 0.8rem;">Based on ${data.total_ratings} reviews</div>
                            </div>
                            <div style="margin-top: 15px;">
                                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 5px;"><span style="width: 30px;">5 ★</span><div style="flex:1; height:6px; background:#e0e0e0; border-radius:3px;"><div style="width:${fivePercent}%; height:100%; background:#ffc107; border-radius:3px;"></div></div><span>${data.five_star}</span></div>
                                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 5px;"><span style="width: 30px;">4 ★</span><div style="flex:1; height:6px; background:#e0e0e0; border-radius:3px;"><div style="width:${fourPercent}%; height:100%; background:#ffc107; border-radius:3px;"></div></div><span>${data.four_star}</span></div>
                                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 5px;"><span style="width: 30px;">3 ★</span><div style="flex:1; height:6px; background:#e0e0e0; border-radius:3px;"><div style="width:${threePercent}%; height:100%; background:#ffc107; border-radius:3px;"></div></div><span>${data.three_star}</span></div>
                                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 5px;"><span style="width: 30px;">2 ★</span><div style="flex:1; height:6px; background:#e0e0e0; border-radius:3px;"><div style="width:${twoPercent}%; height:100%; background:#ffc107; border-radius:3px;"></div></div><span>${data.two_star}</span></div>
                                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 5px;"><span style="width: 30px;">1 ★</span><div style="flex:1; height:6px; background:#e0e0e0; border-radius:3px;"><div style="width:${onePercent}%; height:100%; background:#ffc107; border-radius:3px;"></div></div><span>${data.one_star}</span></div>
                            </div>
                        </div>
                    `;
                } else {
                    ratingSummaryHtml = `<div style="background:#f8f8f8; padding:20px; border-radius:12px; text-align:center;">No ratings yet</div>`;
                }
                
                // Reviews from purchased users (display only)
                let reviewsHtml = '';
                if (data.reviews && data.reviews.length > 0) {
                    data.reviews.forEach(review => {
                        const reviewTextDisplay = review.review ? escapeHtml(review.review) : '<em style="color:#999;">No comment</em>';
                        reviewsHtml += `
                            <div style="padding: 15px 0; border-bottom: 1px solid #f0f0f0;">
                                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
                                    <div style="width: 35px; height: 35px; background: #000; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center;">${(review.username ? review.username.charAt(0) : 'U').toUpperCase()}</div>
                                    <div><strong>${escapeHtml(review.username)}</strong><div style="font-size: 0.7rem; color: #999;">${review.formatted_date}</div></div>
                                </div>
                                <div style="color: #ffc107; margin-bottom: 8px;">${review.stars_html}</div>
                                <div style="font-size: 0.9rem; color: #555;">${reviewTextDisplay}</div>
                            </div>
                        `;
                    });
                } else {
                    reviewsHtml = '<div style="text-align: center; padding: 20px; color: #999;">No reviews yet</div>';
                }
                
                // Comments from all users
                let commentsHtml = '';
                if (data.comments && data.comments.length > 0) {
                    data.comments.forEach(comment => {
                        commentsHtml += `
                            <div style="padding: 15px 0; border-bottom: 1px solid #f0f0f0;">
                                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
                                    <div style="width: 35px; height: 35px; background: #ddd; color: #333; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold;">${(comment.username ? comment.username.charAt(0) : 'U').toUpperCase()}</div>
                                    <div>
                                        <strong style="font-size: 0.9rem;">${escapeHtml(comment.username)}</strong>
                                        <div style="font-size: 0.7rem; color: #999;">${comment.time_ago}</div>
                                    </div>
                                </div>
                                <div style="font-size: 0.85rem; color: #555; margin-left: 45px; line-height: 1.5;">${escapeHtml(comment.comment)}</div>
                            </div>
                        `;
                    });
                } else {
                    commentsHtml = '<div style="text-align: center; padding: 20px; color: #999;">No comments yet. Be the first to comment!</div>';
                }
                
                // COMMENT FORM ONLY - NO RATING STARS
                let commentFormHtml = '';
                if (data.can_comment) {
                    commentFormHtml = `
                        <div style="margin-top: 30px; padding: 20px; background: #f9f9f9; border-radius: 12px;">
                            <h3 style="margin-bottom: 15px;">Leave a Comment</h3>
                            <div id="commentMessage" style="padding: 10px; border-radius: 8px; margin-bottom: 15px; display: none;"></div>
                            <textarea id="commentText" rows="3" style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 0.9rem; font-family: inherit; resize: vertical; margin-bottom: 15px;" placeholder="Ask a question or share your thoughts about this product..."></textarea>
                            <button onclick="submitComment(${data.id})" style="background: #000; color: white; border: none; padding: 12px 24px; border-radius: 8px; cursor: pointer; font-size: 0.9rem; width: 100%;">Post Comment</button>
                        </div>
                    `;
                } else {
                    commentFormHtml = `<div style="margin-top: 30px; padding: 20px; background: #f0f0f0; border-radius: 12px; text-align: center;"><a href="login.php" style="color: #000; font-weight: 600;">Login</a> to leave a comment</div>`;
                }
                
                let productImageHtml = '';
                if (data.image_url) {
                    productImageHtml = `<img src="images/${data.image_url}" alt="${escapeHtml(data.name)}" style="width:100%; border-radius:12px;">`;
                } else {
                    productImageHtml = `<div style="height:300px; background:#f0f0f0; display:flex; align-items:center; justify-content:center;"><i class="fas fa-tshirt" style="font-size:5rem; color:#ccc;"></i></div>`;
                }
                
                body.innerHTML = `
                    <div style="display: flex; gap: 30px; flex-wrap: wrap;">
                        <div style="flex:1; min-width:250px;">${productImageHtml}</div>
                        <div style="flex:1; min-width:250px;">
                            <h2 style="font-size:1.8rem; margin-bottom:10px;">${escapeHtml(data.name)}</h2>
                            <div style="font-size:1.5rem; color:#2e7d32; margin-bottom:15px;">₱${parseFloat(data.price).toFixed(2)}</div>
                            <div style="color:#666; line-height:1.6; margin-bottom:20px;">${escapeHtml(data.description)}</div>
                            ${ratingSummaryHtml}
                            <div style="display: flex; gap: 15px; margin-top: 25px;">
                                <button onclick="addToCartFromDetail(${data.id}, '${escapeHtml(data.name)}')" style="flex:1; padding:14px; border-radius:8px; background:white; border:1px solid #000; color:#000; cursor:pointer;">Add to Cart</button>
                                <button onclick="buyNowFromDetail(${data.id}, '${escapeHtml(data.name)}', ${data.price})" style="flex:1; padding:14px; border-radius:8px; background:#000; border:1px solid #000; color:white; cursor:pointer;">Buy Now</button>
                            </div>
                        </div>
                    </div>
                    <div style="margin-top: 30px; border-top: 1px solid #f0f0f0; padding-top: 30px;">
                        <h3 style="font-size:1.2rem; margin-bottom:20px;">Customer Reviews (${data.total_ratings})</h3>
                        <div id="reviewsList">${reviewsHtml}</div>
                        
                        <h3 style="font-size:1.2rem; margin-top: 30px; margin-bottom: 20px;">Comments (${data.total_comments})</h3>
                        <div id="commentsList">${commentsHtml}</div>
                        ${commentFormHtml}
                    </div>
                `;
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('productDetailBody').innerHTML = '<div style="text-align: center; padding: 40px; color: red;">Error loading product details</div>';
            });
    }
    
    // Submit comment function (NO rating)
    function submitComment(productId) {
        const commentText = document.getElementById('commentText').value.trim();
        
        if (!commentText) {
            alert('Please write a comment');
            return;
        }
        
        const submitBtn = event.target;
        submitBtn.disabled = true;
        submitBtn.textContent = 'Posting...';
        submitBtn.style.opacity = '0.7';
        
        fetch('get_product_details.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'product_id=' + productId + '&comment=' + encodeURIComponent(commentText)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('✓ ' + data.message);
                document.getElementById('commentText').value = '';
                setTimeout(() => { showProductDetail(productId); }, 1000);
            } else {
                alert(data.error || 'Error posting comment');
                submitBtn.disabled = false;
                submitBtn.textContent = 'Post Comment';
                submitBtn.style.opacity = '1';
            }
        })
        .catch(error => {
            alert('Error posting comment');
            submitBtn.disabled = false;
            submitBtn.textContent = 'Post Comment';
            submitBtn.style.opacity = '1';
        });
    }
    
    function generateStarHTML(rating) {
        let stars = '';
        const fullStars = Math.floor(rating);
        const halfStar = (rating - fullStars) >= 0.5;
        for (let i = 1; i <= 5; i++) {
            if (i <= fullStars) stars += '<i class="fas fa-star"></i>';
            else if (halfStar && i === fullStars + 1) stars += '<i class="fas fa-star-half-alt"></i>';
            else stars += '<i class="far fa-star"></i>';
        }
        return stars;
    }
    
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    function closeProductDetail() {
        document.getElementById('productDetailModal').style.display = 'none';
        document.body.style.overflow = '';
    }
    
    document.getElementById('productDetailModal').addEventListener('click', function(e) {
        if (e.target === this) closeProductDetail();
    });
    
    function addToCartFromDetail(productId, productName) {
        if (!isLoggedIn || !isApproved) {
            closeProductDetail();
            showLoginAlert();
            return;
        }
        alert('Added to cart: ' + productName);
    }
    
    function buyNowFromDetail(productId, productName, productPrice) {
        if (!isLoggedIn || !isApproved) {
            closeProductDetail();
            currentProductId = productId;
            currentProductName = productName;
            currentProductPrice = productPrice;
            document.getElementById('modalProductName').textContent = 'Login Required';
            document.getElementById('modalProductDetails').textContent = 'Please login to continue with your purchase.';
            document.getElementById('modalProductPrice').textContent = productPrice.toFixed(2);
            document.getElementById('buyNowModal').style.display = 'flex';
            return;
        }
        window.location.href = 'checkout.php?product_id=' + productId + '&buy_now=1';
    }
    
    let currentProductId = null;
    let currentProductName = '';
    let currentProductPrice = 0;
    
    const isLoggedIn = <?php echo isLoggedIn() ? 'true' : 'false'; ?>;
    const isApproved = <?php echo isLoggedIn() && isApprovedUser() ? 'true' : 'false'; ?>;
    
    function addToCart(productId, productName) {
        if (!isLoggedIn || !isApproved) {
            showLoginAlert();
            return;
        }
        
        const btn = event.target;
        const originalText = btn.textContent;
        
        btn.textContent = 'Adding...';
        btn.style.opacity = '0.7';
        btn.style.cursor = 'wait';
        
        setTimeout(() => {
            btn.textContent = 'Added ✓';
            btn.style.borderColor = '#000';
            btn.style.background = '#f8f8f8';
            btn.style.opacity = '1';
            btn.style.cursor = 'pointer';
            
            setTimeout(() => {
                btn.textContent = originalText;
                btn.style.borderColor = '';
                btn.style.background = '';
            }, 1500);
        }, 500);
    }
    
    function buyNow(productId, productName, productPrice) {
        if (!isLoggedIn || !isApproved) {
            currentProductId = productId;
            currentProductName = productName;
            currentProductPrice = productPrice;
            
            document.getElementById('modalProductName').textContent = 'Login Required';
            document.getElementById('modalProductDetails').textContent = 'Please login to continue with your purchase.';
            document.getElementById('modalProductPrice').textContent = productPrice.toFixed(2);
            
            document.getElementById('buyNowModal').style.display = 'flex';
            return;
        }
        
        window.location.href = 'checkout.php?product_id=' + productId + '&buy_now=1';
    }
    
    function showLoginAlert() {
        currentProductId = null;
        document.getElementById('modalProductName').textContent = 'Login Required';
        document.getElementById('modalProductDetails').textContent = 'Please login to continue with your purchase.';
        document.getElementById('modalProductPrice').textContent = '0.00';
        document.getElementById('buyNowModal').style.display = 'flex';
    }
    
    function closeModal() {
        document.getElementById('buyNowModal').style.display = 'none';
    }
    
    function redirectToLogin() {
        if (currentProductId) {
            sessionStorage.setItem('pending_purchase_product_id', currentProductId);
            sessionStorage.setItem('pending_purchase_product_name', currentProductName);
            sessionStorage.setItem('pending_purchase_product_price', currentProductPrice);
        }
        
        window.location.href = 'login.php?redirect=checkout';
    }
    
    const modal = document.getElementById('buyNowModal');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    }
    
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeModal();
            closeProductDetail();
        }
    });
    </script>
</body>
</html>