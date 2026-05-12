<?php
require_once 'includes/config.php';

// Handle search functionality
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$categoryFilter = isset($_GET['category']) ? $_GET['category'] : '';

// Check if category column exists
$checkCategoryColumn = false;
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM products LIKE 'category'");
    $checkCategoryColumn = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $checkCategoryColumn = false;
}

// Check if is_featured column exists, if not create it
try {
    $checkFeatured = $pdo->query("SHOW COLUMNS FROM products LIKE 'is_featured'");
    if ($checkFeatured->rowCount() == 0) {
        $pdo->exec("ALTER TABLE products ADD COLUMN is_featured TINYINT(1) DEFAULT 0");
    }
} catch (PDOException $e) {
    // Column might already exist
}

// Create product_comments table for admin panel comments functionality
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS product_comments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        user_id INT NOT NULL,
        parent_id INT NULL,
        comment TEXT NOT NULL,
        is_admin_reply TINYINT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_product_id (product_id),
        INDEX idx_parent_id (parent_id)
    )");
} catch (PDOException $e) {
    // Table might already exist
}

// ========== FIXED SEARCH FUNCTIONALITY ==========
// Function to determine product type from name, description, and category
function getProductType($name, $description, $category = '') {
    $text = strtolower($name . ' ' . $description . ' ' . $category);
    
    // Define product type patterns
    $types = [
        't-shirt' => ['t-shirt', 't shirt', 'tshirt', 'tee', 'cotton tee', 'graphic tee', 'basic tee', 'oversized tee', 'round neck tee', 'v-neck'],
        'hoodie' => ['hoodie', 'hoody', 'sweatshirt', 'hooded', 'pullover hoodie', 'zip hoodie', 'hooded sweater'],
        'jacket' => ['jacket', 'coat', 'outerwear', 'bomber', 'denim jacket', 'varsity jacket', 'windbreaker'],
        'towel' => ['towel', 'bath towel', 'beach towel', 'face towel', 'hand towel'],
        'pants' => ['pants', 'trousers', 'jeans', 'pant', 'cargo pants', 'sweatpants', 'joggers'],
        'shorts' => ['shorts', 'short pant', 'cargo shorts', 'bermuda'],
        'accessories' => ['cap', 'hat', 'bag', 'belt', 'wallet', 'beanie', 'scarf']
    ];
    
    foreach ($types as $type => $keywords) {
        foreach ($keywords as $keyword) {
            if (strpos($text, $keyword) !== false) {
                return $type;
            }
        }
    }
    
    return 'other';
}

// Build the base query
$query = "SELECT * FROM products WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (name LIKE ? OR description LIKE ?";
    
    if ($checkCategoryColumn) {
        $query .= " OR category LIKE ?";
    }
    
    $query .= ")";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    
    if ($checkCategoryColumn) {
        $params[] = $searchTerm;
    }
}

if ($checkCategoryColumn && !empty($categoryFilter)) {
    $query .= " AND category = ?";
    $params[] = $categoryFilter;
}

// For homepage (no search, no category filter), show ONLY featured products
if (empty($search) && empty($categoryFilter)) {
    $query .= " AND is_featured = 1";
    $query .= " LIMIT 8";
} else {
    $query .= " ORDER BY name ASC";
}

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$allProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ========== SMART PRODUCT FILTERING BASED ON SEARCH INTENT ==========
$products = [];

if (!empty($search)) {
    $searchLower = strtolower($search);
    
    // Detect what the user is searching for
    $searchingForTshirt = false;
    $searchingForHoodie = false;
    $searchingForJacket = false;
    $searchingForTowel = false;
    
    // T-shirt keywords
    $tshirtKeywords = ['t-shirt', 'tshirt', 't shirt', 'tee', 'cotton tee', 'graphic tee', 'basic tee'];
    // Hoodie keywords (to exclude when searching for t-shirts)
    $hoodieKeywords = ['hoodie', 'hoody', 'sweatshirt', 'hooded', 'pullover'];
    // Jacket keywords (to exclude when searching for t-shirts)
    $jacketKeywords = ['jacket', 'coat', 'outerwear', 'bomber', 'windbreaker'];
    
    foreach ($tshirtKeywords as $keyword) {
        if (strpos($searchLower, $keyword) !== false) {
            $searchingForTshirt = true;
            break;
        }
    }
    
    foreach ($hoodieKeywords as $keyword) {
        if (strpos($searchLower, $keyword) !== false) {
            $searchingForHoodie = true;
            break;
        }
    }
    
    foreach ($jacketKeywords as $keyword) {
        if (strpos($searchLower, $keyword) !== false) {
            $searchingForJacket = true;
            break;
        }
    }
    
    // Filter products based on search intent
    foreach ($allProducts as $product) {
        $productNameLower = strtolower($product['name']);
        $productDescLower = strtolower($product['description']);
        $productCategory = isset($product['category']) ? strtolower($product['category']) : '';
        
        $productType = getProductType($product['name'], $product['description'], $productCategory);
        
        // CASE 1: User is searching for T-SHIRT - ONLY show t-shirts, NO hoodies or jackets
        if ($searchingForTshirt) {
            $isTshirt = false;
            $isExcluded = false;
            
            // Check if it's a t-shirt by keywords
            foreach ($tshirtKeywords as $keyword) {
                if (strpos($productNameLower, $keyword) !== false || 
                    strpos($productDescLower, $keyword) !== false ||
                    strpos($productCategory, $keyword) !== false) {
                    $isTshirt = true;
                    break;
                }
            }
            
            // Also check product type detection
            if ($productType == 't-shirt') {
                $isTshirt = true;
            }
            
            // Check if it should be excluded (hoodie or jacket)
            $excludeKeywords = array_merge($hoodieKeywords, $jacketKeywords);
            foreach ($excludeKeywords as $keyword) {
                if (strpos($productNameLower, $keyword) !== false || 
                    strpos($productDescLower, $keyword) !== false) {
                    $isExcluded = true;
                    break;
                }
            }
            
            // Only include if it's a t-shirt and not excluded
            if ($isTshirt && !$isExcluded) {
                $products[] = $product;
            }
        }
        // CASE 2: User is searching for HOODIE
        elseif ($searchingForHoodie) {
            $isHoodie = false;
            
            foreach ($hoodieKeywords as $keyword) {
                if (strpos($productNameLower, $keyword) !== false || 
                    strpos($productDescLower, $keyword) !== false ||
                    strpos($productCategory, $keyword) !== false) {
                    $isHoodie = true;
                    break;
                }
            }
            
            if ($productType == 'hoodie') {
                $isHoodie = true;
            }
            
            if ($isHoodie) {
                $products[] = $product;
            }
        }
        // CASE 3: User is searching for JACKET
        elseif ($searchingForJacket) {
            $isJacket = false;
            
            foreach ($jacketKeywords as $keyword) {
                if (strpos($productNameLower, $keyword) !== false || 
                    strpos($productDescLower, $keyword) !== false ||
                    strpos($productCategory, $keyword) !== false) {
                    $isJacket = true;
                    break;
                }
            }
            
            if ($productType == 'jacket') {
                $isJacket = true;
            }
            
            if ($isJacket) {
                $products[] = $product;
            }
        }
        // CASE 4: General search - include products that match the search term
        else {
            if (strpos($productNameLower, $searchLower) !== false || 
                strpos($productDescLower, $searchLower) !== false) {
                $products[] = $product;
            }
        }
    }
} else {
    // No search term, use all products
    $products = $allProducts;
}
// ========== END OF FIXED SEARCH FUNCTIONALITY ==========

// Get unique categories for filter dropdown
$categories = [];
if ($checkCategoryColumn) {
    $categoryStmt = $pdo->prepare("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category != ''");
    $categoryStmt->execute();
    $categories = $categoryStmt->fetchAll(PDO::FETCH_COLUMN);
}

// Get total comments count for admin panel stats
$totalComments = 0;
$pendingReplies = 0;
try {
    $commentStmt = $pdo->query("SELECT COUNT(*) FROM product_comments WHERE parent_id IS NULL OR parent_id = 0");
    $totalComments = $commentStmt->fetchColumn();
    
    $replyStmt = $pdo->query("SELECT COUNT(*) FROM product_comments WHERE parent_id IS NOT NULL AND parent_id != 0");
    $pendingReplies = $replyStmt->fetchColumn();
} catch (PDOException $e) {
    $totalComments = 0;
    $pendingReplies = 0;
}

// Product descriptions
$better_descriptions = [
    1 => "Clean-cut urban jacket with minimalist silhouette. Crafted from premium Japanese cotton with hidden zipper details and reinforced stitching for everyday durability.",
    2 => "Street-ready black tee featuring geometric line art. Oversized fit with dropped shoulders, made from heavyweight 280gsm cotton for structured drape.",
    3 => "Essential white tee with abstract graphic print. Breathable ring-spun cotton, tagless neckline, and double-stitched seams for clean minimal styling.",
    4 => "Luxurious cotton towel with minimalist geometric pattern. Premium Egyptian cotton with double-stitched edges and fade-resistant print for lasting quality."
];

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

// Get unread notification count and recent notifications for logged-in user
$unreadNotifications = 0;
$recentNotifications = [];
if (isLoggedIn()) {
    $user_id = $_SESSION['user_id'];
    
    // Check if notifications table exists
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
    } catch (PDOException $e) {
        // Table might already exist
    }
    
    // Get unread count
    $notifStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND status = 'unread'");
    $notifStmt->execute([$user_id]);
    $unreadNotifications = $notifStmt->fetchColumn();
    
    // Get recent notifications for dropdown
    $recentStmt = $pdo->prepare("
        SELECT n.*, 
               CASE 
                   WHEN n.order_id IS NOT NULL THEN o.order_status 
                   ELSE 'message' 
               END as order_status,
               CASE 
                   WHEN n.order_id IS NOT NULL THEN o.total_amount 
                   ELSE 0 
               END as total_amount
        FROM notifications n 
        LEFT JOIN orders o ON n.order_id = o.id 
        WHERE n.user_id = ? 
        ORDER BY n.created_at DESC 
        LIMIT 5
    ");
    $recentStmt->execute([$user_id]);
    $recentNotifications = $recentStmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to create notification when order status changes
function createOrderStatusNotification($pdo, $order_id, $user_id, $new_status) {
    $status_messages = [
        'pending' => 'Your order #' . $order_id . ' is pending confirmation.',
        'processing' => 'Your order #' . $order_id . ' is now being processed.',
        'shipped' => 'Great news! Your order #' . $order_id . ' has been shipped and will arrive in 3-5 business days.',
        'out_for_delivery' => 'Your order #' . $order_id . ' is out for delivery today!',
        'delivered' => 'Your order #' . $order_id . ' has been delivered. Thank you for shopping with us!',
        'cancelled' => 'Your order #' . $order_id . ' has been cancelled.',
        'refunded' => 'Your order #' . $order_id . ' has been refunded.'
    ];
    
    $message = isset($status_messages[$new_status]) ? $status_messages[$new_status] : 'Your order #' . $order_id . ' status has been updated to: ' . $new_status;
    
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, order_id, message, status) VALUES (?, ?, ?, 'unread')");
    return $stmt->execute([$user_id, $order_id, $message]);
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

// Check if user is admin
$isAdmin = (isset($_SESSION['username']) && $_SESSION['username'] == 'admin');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>MONEYWASTE — Minimal Streetwear</title>
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

        /* Desktop Search Container - shown on desktop */
        .search-container {
            flex: 1;
            max-width: 500px;
            position: relative;
        }

        .search-form {
            display: flex;
            gap: 10px;
            width: 100%;
        }

        .search-input {
            flex: 1;
            padding: 10px 15px;
            border: 1px solid #ddd;
            font-size: 0.9rem;
            letter-spacing: 0.5px;
            background: #fafafa;
            transition: all 0.3s ease;
            outline: none;
            min-width: 150px;
            border-radius: 4px;
        }

        .search-input:focus {
            border-color: #000;
            background: white;
            box-shadow: 0 0 0 2px rgba(0,0,0,0.1);
        }

        .search-btn {
            padding: 10px 20px;
            background: #000;
            color: white;
            border: 1px solid #000;
            cursor: pointer;
            font-size: 0.9rem;
            letter-spacing: 1px;
            transition: all 0.3s ease;
            white-space: nowrap;
            border-radius: 4px;
        }

        .search-btn:hover {
            background: #333;
            border-color: #333;
        }

        /* Mobile Search Icon Button */
        .mobile-search-btn {
            display: none;
            background: #000;
            color: white;
            border: none;
            width: 45px;
            height: 45px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 1.2rem;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .mobile-search-btn:hover {
            background: #333;
            transform: scale(1.05);
        }

        /* Mobile Search Modal */
        .search-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.9);
            z-index: 2000;
            justify-content: center;
            align-items: flex-start;
            padding-top: 100px;
        }

        .search-modal-content {
            width: 90%;
            max-width: 500px;
            background: white;
            border-radius: 12px;
            padding: 20px;
            position: relative;
        }

        .search-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .search-modal-header h3 {
            font-size: 1.2rem;
            margin: 0;
        }

        .close-modal-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #666;
        }

        .close-modal-btn:hover {
            color: #000;
        }

        .search-modal-form {
            display: flex;
            gap: 10px;
            width: 100%;
        }

        .search-modal-input {
            flex: 1;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            outline: none;
        }

        .search-modal-input:focus {
            border-color: #000;
        }

        .search-modal-btn {
            padding: 12px 20px;
            background: #000;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9rem;
        }

        /* Navigation */
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

        /* Admin Button Special Style */
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

        /* Profile Dropdown Styles */
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

        /* Notification Item Styles */
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

        .notification-time i {
            font-size: 0.6rem;
            color: #ccc;
        }

        .notification-status-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.65rem;
            font-weight: 500;
            margin-left: 8px;
            text-transform: uppercase;
        }

        .status-shipped, .status-out_for_delivery {
            background: #e3f2fd;
            color: #1976d2;
        }

        .status-delivered {
            background: #e8f5e8;
            color: #2e7d32;
        }

        .status-pending {
            background: #fff3e0;
            color: #f57c00;
        }

        .status-processing {
            background: #e8eaf6;
            color: #3f51b5;
        }

        .status-message {
            background: #e8eaf6;
            color: #5c6bc0;
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

        .no-notifications i {
            font-size: 2rem;
            margin-bottom: 10px;
            color: #ddd;
        }

        /* Search Results Info */
        .search-results-info {
            text-align: center;
            padding: 2rem 0 1rem;
            border-bottom: 1px solid #f0f0f0;
            margin: 0 2rem 3rem;
            opacity: 0;
            animation: fadeIn 1s ease-out 0.5s forwards;
        }

        .search-term {
            color: #000;
            font-weight: 500;
        }

        .result-count {
            color: #666;
            font-size: 0.9rem;
            letter-spacing: 1px;
            margin-top: 0.5rem;
        }

        .clear-search {
            display: inline-block;
            margin-top: 1rem;
            padding: 8px 20px;
            background: #f0f0f0;
            color: #333;
            text-decoration: none;
            font-size: 0.8rem;
            letter-spacing: 1px;
            transition: all 0.3s ease;
        }

        .clear-search:hover {
            background: #000;
            color: white;
        }

        /* Slideshow Styles */
        .slideshow-container {
            max-width: 100%;
            position: relative;
            margin: 0;
            overflow: hidden;
            background: #000;
            height: 85vh;
            min-height: 700px;
        }
        
        .slideshow {
            position: relative;
            height: 100%;
            width: 100%;
        }
        
        .slide {
            display: none;
            height: 100%;
            width: 100%;
            position: relative;
        }
        
        .slide.fade {
            animation-name: fade;
            animation-duration: 1.5s;
        }
        
        .slide img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            opacity: 1;
        }
        
        .slide-overlay {
            position: absolute;
            bottom: 100px;
            left: 50%;
            transform: translateX(-50%);
            text-align: center;
            color: white;
            width: 90%;
            max-width: 800px;
            background: rgba(0, 0, 0, 0.5);
            padding: 2rem;
            backdrop-filter: blur(5px);
            border-radius: 4px;
        }

        .slide-title {
            font-size: 2.5rem;
            font-weight: 300;
            letter-spacing: 3px;
            margin: 0 0 1rem 0;
            text-transform: uppercase;
        }

        .slide-subtitle {
            font-size: 1.1rem;
            letter-spacing: 1px;
            line-height: 1.6;
            opacity: 0.9;
            margin: 0;
        }
        
        .prev, .next {
            cursor: pointer;
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 60px;
            height: 60px;
            padding: 0;
            color: white;
            font-weight: bold;
            font-size: 28px;
            transition: all 0.4s ease;
            border-radius: 50%;
            user-select: none;
            background: rgba(0, 0, 0, 0.5);
            border: 2px solid rgba(255, 255, 255, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
        }
        
        .prev {
            left: 30px;
        }
        
        .next {
            right: 30px;
        }
        
        .prev:hover, .next:hover {
            background: rgba(0, 0, 0, 0.8);
            border-color: white;
            transform: translateY(-50%) scale(1.1);
        }
        
        .dots-container {
            text-align: center;
            position: absolute;
            bottom: 40px;
            width: 100%;
            z-index: 10;
        }
        
        /* FIXED: Smaller dots for slideshow */
        .dot {
            cursor: pointer;
            height: 10px;
            width: 10px;
            margin: 0 6px;
            background-color: rgba(255, 255, 255, 0.4);
            border-radius: 50%;
            display: inline-block;
            transition: all 0.3s ease;
        }
        
        .active, .dot:hover {
            background-color: white;
            transform: scale(1.1);
        }
        
        @keyframes fade {
            from {opacity: .6}
            to {opacity: 1}
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Main Content */
        .main-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 2rem 5rem;
            position: relative;
            z-index: 2;
            background: white;
        }

        .page-title {
            text-align: center;
            font-size: 3rem;
            font-weight: 300;
            letter-spacing: 4px;
            margin: 4rem 0 2rem;
            text-transform: uppercase;
            animation: slideUp 1s ease-out;
            position: relative;
            z-index: 3;
            color: #000;
            padding-top: 2rem;
        }
        
        .intro-text {
            text-align: center;
            font-size: 1.2rem;
            color: #666;
            margin: 2rem auto 4rem;
            max-width: 800px;
            opacity: 0;
            animation: fadeIn 1s ease-out 0.5s forwards;
            letter-spacing: 1px;
            line-height: 1.8;
        }
        
        /* Products Grid */
        .products-minimal {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 2rem;
            margin: 4rem 0;
            position: relative;
            z-index: 2;
        }

        .product-card-minimal {
            border: 1px solid #f0f0f0;
            padding: 1.5rem;
            transition: all 0.4s ease;
            background: white;
            cursor: pointer;
            position: relative;
            border-radius: 12px;
        }

        .product-card-minimal:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            border-color: transparent;
        }

        .product-image-container {
            margin-bottom: 1.5rem;
            overflow: hidden;
            height: 280px;
            border-radius: 8px;
        }
        
        .product-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            background: #f8f8f8;
            transition: transform 0.7s ease;
        }

        .product-card-minimal:hover .product-image {
            transform: scale(1.05);
        }
        
        .no-image {
            width: 100%;
            height: 100%;
            background: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #999;
            font-size: 0.9rem;
        }
        
        .product-title {
            font-size: 1.3rem;
            font-weight: 400;
            margin: 0 0 0.5rem 0;
            letter-spacing: 1px;
        }

        .hover-line {
            position: relative;
            display: inline-block;
        }

        .hover-line:after {
            content: '';
            position: absolute;
            bottom: -4px;
            left: 0;
            width: 0;
            height: 2px;
            background: #000;
            transition: width 0.4s ease;
        }

        .product-card-minimal:hover .hover-line:after {
            width: 100%;
        }
        
        .product-category {
            font-size: 0.75rem;
            color: #666;
            margin-bottom: 0.5rem;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        
        .product-description {
            height: 70px;
            overflow: hidden;
            margin-bottom: 1rem;
            font-size: 0.9rem;
            line-height: 1.5;
            color: #666;
        }

        .product-card-minimal:hover .product-description {
            color: #333;
        }
        
        .product-price {
            font-size: 1.3rem;
            font-weight: 300;
            letter-spacing: 2px;
            margin: 1rem 0;
        }

        .product-card-minimal:hover .product-price {
            color: #000;
            letter-spacing: 3px;
        }
        
        .product-buttons {
            display: flex;
            gap: 10px;
            margin-top: 1rem;
        }
        
        .minimal-btn {
            flex: 1;
            padding: 12px 0;
            border: 1px solid #ddd;
            background: white;
            color: #333;
            cursor: pointer;
            font-size: 0.85rem;
            letter-spacing: 1px;
            transition: all 0.3s ease;
            text-align: center;
            border-radius: 6px;
        }
        
        .minimal-btn:hover {
            border-color: #333;
            background: #f8f8f8;
            letter-spacing: 2px;
        }
        
        .buy-now-btn {
            flex: 1;
            padding: 12px 0;
            border: 1px solid #000;
            background: #000;
            color: white;
            cursor: pointer;
            font-size: 0.85rem;
            letter-spacing: 1px;
            transition: all 0.3s ease;
            text-align: center;
            border-radius: 6px;
        }
        
        .buy-now-btn:hover {
            background: #333;
            border-color: #333;
            letter-spacing: 2px;
        }
        
        .brand-message {
            text-align: center;
            margin: 6rem 0 4rem;
            padding: 3rem;
            border-top: 1px solid #f0f0f0;
            border-bottom: 1px solid #f0f0f0;
            background: #fafafa;
        }
        
        .message-line {
            font-size: 1.1rem;
            color: #000;
            letter-spacing: 3px;
            text-transform: uppercase;
            margin: 1rem 0;
            opacity: 0;
            transform: translateY(20px);
            animation: messageReveal 0.8s ease-out forwards;
            font-weight: 300;
        }
        
        .message-line:nth-child(1) { animation-delay: 0.3s; }
        .message-line:nth-child(2) { animation-delay: 0.6s; }
        .message-line:nth-child(3) { animation-delay: 0.9s; }
        
        @keyframes messageReveal {
            to { 
                opacity: 1; 
                transform: translateY(0); 
            }
        }
        
        /* Modal styles */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            animation: fadeIn 0.3s ease;
        }
        
        .modal-content {
            background: white;
            padding: 2rem;
            max-width: 400px;
            width: 90%;
            text-align: center;
            border-radius: 12px;
            box-shadow: 0 30px 60px rgba(0,0,0,0.2);
            animation: slideUp 0.4s ease;
        }
        
        .modal-buttons {
            display: flex;
            gap: 15px;
            margin-top: 2rem;
        }
        
        .modal-btn {
            flex: 1;
            padding: 12px;
            border: 1px solid #ddd;
            background: white;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.9rem;
            letter-spacing: 1px;
            border-radius: 6px;
        }

        .modal-btn:hover {
            border-color: #000;
            letter-spacing: 2px;
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
        
        /* Responsive Design - Search becomes icon on mobile */
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
            
            /* Hide desktop search, show mobile search icon */
            .search-container {
                display: none;
            }
            
            .mobile-search-btn {
                display: flex !important;
            }
            
            .slideshow-container {
                height: 60vh;
                min-height: 400px;
            }
            
            .slide-overlay {
                padding: 1rem;
                bottom: 60px;
            }
            
            .slide-title {
                font-size: 1.5rem;
            }
            
            .prev, .next {
                width: 40px;
                height: 40px;
                font-size: 20px;
            }
            
            .prev {
                left: 10px;
            }
            
            .next {
                right: 10px;
            }
            
            /* FIXED: Even smaller dots on tablet */
            .dot {
                height: 8px;
                width: 8px;
                margin: 0 5px;
            }
        }
        
        @media only screen and (max-width: 768px) {
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
            
            .admin-nav-link {
                padding: 0.4rem 1rem !important;
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
            
            .main-content {
                padding: 0 1rem 3rem;
            }
            
            .page-title {
                font-size: 1.8rem;
                margin: 2rem 0 1rem;
            }
            
            .products-minimal {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }
            
            .product-image-container {
                height: 240px;
            }
            
            .brand-message {
                padding: 2rem;
            }
            
            .message-line {
                font-size: 0.9rem;
                letter-spacing: 2px;
            }
            
            /* FIXED: Smaller dots on mobile */
            .dot {
                height: 7px;
                width: 7px;
                margin: 0 4px;
            }
        }
        
        @media only screen and (max-width: 480px) {
            .logo-image {
                height: 35px;
            }
            
            .logo-text {
                font-size: 1.1rem;
            }
            
            .mobile-search-btn {
                width: 38px;
                height: 38px;
                font-size: 1rem;
            }
            
            .minimal-nav ul {
                gap: 0.7rem;
            }
            
            .nav-link {
                font-size: 0.7rem;
            }
            
            .admin-nav-link {
                padding: 0.3rem 0.8rem !important;
                font-size: 0.7rem;
            }
            
            .slideshow-container {
                height: 50vh;
                min-height: 300px;
            }
            
            .slide-overlay {
                display: none;
            }
            
            .page-title {
                font-size: 1.5rem;
            }
            
            .product-image-container {
                height: 200px;
            }
            
            .product-title {
                font-size: 1.1rem;
            }
            
            .product-price {
                font-size: 1.1rem;
            }
            
            /* FIXED: Tiny dots on very small phones */
            .dot {
                height: 6px;
                width: 6px;
                margin: 0 3px;
            }
        }
        
        h1.page-title {
            all: unset !important;
            text-align: center !important;
            font-size: 3rem !important;
            font-weight: 300 !important;
            letter-spacing: 4px !important;
            margin: 4rem 0 2rem !important;
            text-transform: uppercase !important;
            color: #000 !important;
            padding-top: 2rem !important;
            display: block !important;
        }
    </style>
</head>
<body>
    <div class="loading-bar"></div>
    
    <!-- Mobile Search Modal -->
    <div class="search-modal" id="searchModal">
        <div class="search-modal-content">
            <div class="search-modal-header">
                <h3><i class="fas fa-search"></i> Search Products</h3>
                <button class="close-modal-btn" onclick="closeSearchModal()">&times;</button>
            </div>
            <form method="GET" action="index.php" class="search-modal-form" id="mobileSearchForm">
                <input type="text" 
                       name="search" 
                       class="search-modal-input" 
                       placeholder="Search products..."
                       value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="search-modal-btn">Search</button>
            </form>
        </div>
    </div>
    
    <header class="minimal-header">
        <div class="header-left">
            <div class="logo-container">
                <a href="index.php" class="logo-link">
                    <img src="images/logo.jpg" alt="MONEYWASTE Logo" class="logo-image" onerror="this.src='https://via.placeholder.com/60x60?text=MW'">
                    <span class="logo-text">MONEYWASTE</span>
                </a>
            </div>
            
            <!-- Desktop Search Bar -->
            <div class="search-container">
                <form method="GET" action="index.php" class="search-form">
                    <input type="text" 
                           name="search" 
                           class="search-input" 
                           placeholder="Search products..."
                           value="<?php echo htmlspecialchars($search); ?>">
                    <input type="hidden" name="category" value="">
                    <button type="submit" class="search-btn">Search</button>
                </form>
            </div>
            
            <!-- Mobile Search Icon Button -->
            <button class="mobile-search-btn" onclick="openSearchModal()">
                <i class="fas fa-search"></i>
            </button>
        </div>
        
        <nav class="minimal-nav">
            <ul class="nav-list">
                <li><a href="index.php" class="nav-link">Home</a></li>
                <li><a href="products.php" class="nav-link">Collection</a></li>
                
                <?php if(isLoggedIn()): ?>
                    <?php if($isAdmin): ?>
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
                            
                            <!-- REMOVED: My Profile link for ALL users -->
                            
                            <!-- My Orders link (only for regular users, not admin) -->
                            <?php if(!$isAdmin): ?>
                                <a href="myorders.php" class="dropdown-item">
                                    <i class="fas fa-shopping-bag"></i>
                                    My Orders
                                </a>
                            <?php endif; ?>
                            
                            <!-- Notifications section (shown for both admin and regular users) -->
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
    
    <?php if(!empty($search) || !empty($categoryFilter)): ?>
    <div class="search-results-info">
        <h2>
            <?php if(!empty($search)): ?>
                Results for "<span class="search-term"><?php echo htmlspecialchars($search); ?></span>"
            <?php elseif(!empty($categoryFilter)): ?>
                Category: "<span class="search-term"><?php echo htmlspecialchars($categoryFilter); ?></span>"
            <?php endif; ?>
        </h2>
        <p class="result-count">
            Found <?php echo count($products); ?> product(s)
        </p>
        <a href="index.php" class="clear-search">Clear Search</a>
    </div>
    <?php endif; ?>
    
    <?php if(empty($search) && empty($categoryFilter)): ?>
    <section class="slideshow-container">
        <div class="slideshow">
            <div class="slide fade">
                <img src="images/featured.jpg" alt="Featured Product 1" onerror="this.src='https://via.placeholder.com/1920x1080?text=Urban+Minimalism'">
                <div class="slide-overlay">
                    <h2 class="slide-title">Urban Minimalism</h2>
                    <p class="slide-subtitle">Discover our curated collection of essential streetwear pieces</p>
                </div>
            </div>
            <div class="slide fade">
                <img src="images/featured1.jpg" alt="Featured Product 2" onerror="this.src='https://via.placeholder.com/1920x1080?text=Quality+Craftsmanship'">
                <div class="slide-overlay">
                    <h2 class="slide-title">Quality Craftsmanship</h2>
                    <p class="slide-subtitle">Premium materials for lasting comfort and style</p>
                </div>
            </div>
            <div class="slide fade">
                <img src="images/featured2.jpg" alt="Featured Product 3" onerror="this.src='https://via.placeholder.com/1920x1080?text=Timeless+Design'">
                <div class="slide-overlay">
                    <h2 class="slide-title">Timeless Design</h2>
                    <p class="slide-subtitle">Clean lines, neutral tones, versatile pieces</p>
                </div>
            </div>
            <div class="slide fade">
                <img src="images/featured3.jpg" alt="Featured Product 4" onerror="this.src='https://via.placeholder.com/1920x1080?text=Sustainable+Fashion'">
                <div class="slide-overlay">
                    <h2 class="slide-title">Sustainable Fashion</h2>
                    <p class="slide-subtitle">Ethically produced with minimal environmental impact</p>
                </div>
            </div>
            <div class="slide fade">
                <img src="images/featured4.jpg" alt="Featured Product 5" onerror="this.src='https://via.placeholder.com/1920x1080?text=Essential+Collection'">
                <div class="slide-overlay">
                    <h2 class="slide-title">Essential Collection</h2>
                    <p class="slide-subtitle">Pieces that work together, season after season</p>
                </div>
            </div>
            
            <a class="prev" onclick="plusSlides(-1)">&#10094;</a>
            <a class="next" onclick="plusSlides(1)">&#10095;</a>
            
            <div class="dots-container">
                <span class="dot" onclick="currentSlide(1)"></span>
                <span class="dot" onclick="currentSlide(2)"></span>
                <span class="dot" onclick="currentSlide(3)"></span>
                <span class="dot" onclick="currentSlide(4)"></span>
                <span class="dot" onclick="currentSlide(5)"></span>
            </div>
        </div>
    </section>
    <?php endif; ?>
    
    <main class="main-content">
        <h1 class="page-title">
            <?php if(!empty($search) || !empty($categoryFilter)): ?>
                Search Results
            <?php else: ?>
                Featured Products
            <?php endif; ?>
        </h1>
        
        <?php if(count($products) > 0): ?>
        <div class="products-minimal">
            <?php 
            $product_counter = 0;
            foreach($products as $product): 
                $product_counter++;
                
                $image = 'no-image.jpg';
                
                if (!empty($product['image_url'])) {
                    $image_url = $product['image_url'];
                    if (strpos($image_url, '/') !== false) {
                        $parts = explode('/', $image_url);
                        $image = end($parts);
                    } else {
                        $image = $image_url;
                    }
                }
                
                if ($image == 'no-image.jpg' || !file_exists('images/' . $image)) {
                    $productName = strtolower($product['name']);
                    
                    if (strpos($productName, 'hoodie') !== false) {
                        $image = 'jacket1.jpg';
                    } elseif (strpos($productName, 'black') !== false && strpos($productName, 'tee') !== false) {
                        $image = 'tshirtblk.jpg';
                    } elseif (strpos($productName, 'white') !== false && strpos($productName, 'tee') !== false) {
                        $image = 'tshirtwht.jpg';
                    } elseif (strpos($productName, 'towel') !== false) {
                        $image = 'featured7.png';
                    }
                }
                
                $description = htmlspecialchars($product['description']);
                if ($product_counter <= 4 && isset($better_descriptions[$product_counter])) {
                    $description = $better_descriptions[$product_counter];
                }
            ?>
            
            <div class="product-card-minimal">
                <div class="product-image-container">
                    <?php 
                    $image_found = false;
                    $image_paths = [
                        'images/' . $image,
                        $image,
                        'images/jacket1.jpg',
                        'images/tshirtblk.jpg',
                        'images/tshirtwht.jpg',
                        'images/featured7.png'
                    ];
                    
                    foreach ($image_paths as $img_path) {
                        if (file_exists($img_path)) {
                            $image_found = true;
                            ?>
                            <img src="<?php echo $img_path; ?>" 
                                 alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                 class="product-image">
                            <?php
                            break;
                        }
                    }
                    
                    if (!$image_found): ?>
                        <div class="no-image">
                            <i class="fas fa-image"></i>
                        </div>
                    <?php endif; ?>
                </div>
                
                <h3 class="product-title hover-line"><?php echo htmlspecialchars($product['name']); ?></h3>
                
                <?php if($checkCategoryColumn && !empty($product['category'])): ?>
                <div class="product-category">
                    <?php echo htmlspecialchars($product['category']); ?>
                </div>
                <?php endif; ?>
                
                <p class="product-description">
                    <?php 
                    echo strlen($description) > 80 ? substr($description, 0, 80) . '...' : $description;
                    ?>
                </p>
                <div class="product-price">₱<?php echo number_format($product['price'], 2); ?></div>
                
                <div class="product-buttons">
                    <button class="minimal-btn" onclick="addToCart(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars($product['name']); ?>')">
                        Add to Cart
                    </button>
                    <button class="buy-now-btn" onclick="buyNow(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars($product['name']); ?>', <?php echo $product['price']; ?>)">
                        Buy Now
                    </button>
                </div>
            </div>
            
            <?php endforeach; ?>
        </div>
        <?php else: ?>
            <div style="text-align: center; padding: 4rem; color: #666;">
                <i class="fas fa-box-open" style="font-size: 3rem; margin-bottom: 1rem; color: #ccc;"></i>
                <p style="font-size: 1.2rem; margin-bottom: 1rem;">No products found.</p>
                <a href="products.php" style="color: #000; text-decoration: underline;">Browse all products</a>
            </div>
        <?php endif; ?>
        
        <?php if(empty($search) && empty($categoryFilter)): ?>
        <div class="brand-message">
            <div class="message-line">Quality Over Quantity</div>
            <div class="message-line">Essence Over Excess</div>
            <div class="message-line">Substance Over Show</div>
        </div>
        <?php endif; ?>
    </main>
    
    <div class="modal-overlay" id="buyNowModal">
        <div class="modal-content">
            <h3 id="modalProductName">Login Required</h3>
            <p id="modalProductDetails">Please login to continue with your purchase.</p>
            <p><strong>Total: ₱<span id="modalProductPrice">0.00</span></strong></p>
            <div class="modal-buttons">
                <button class="modal-btn" onclick="closeModal()">Cancel</button>
                <button class="modal-btn modal-confirm" onclick="redirectToLogin()">Login Now</button>
            </div>
        </div>
    </div>
    
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
    document.addEventListener('DOMContentLoaded', function() {
        const loadingBar = document.querySelector('.loading-bar');
        loadingBar.style.display = 'block';
        
        setTimeout(() => {
            loadingBar.style.display = 'none';
        }, 500);
        
        if (document.querySelector('.slideshow-container')) {
            initSlideshow();
        }
        
        const searchInput = document.querySelector('.search-input');
        if (searchInput && searchInput.value) {
            searchInput.focus();
            searchInput.select();
        }

        document.addEventListener('click', function(event) {
            const profileContainer = document.querySelector('.profile-container');
            const dropdown = document.getElementById('profileDropdown');
            
            if (profileContainer && dropdown && !profileContainer.contains(event.target)) {
                dropdown.classList.remove('show');
            }
        });
        
        // Close modal when clicking outside
        const searchModal = document.getElementById('searchModal');
        if (searchModal) {
            searchModal.addEventListener('click', function(e) {
                if (e.target === searchModal) {
                    closeSearchModal();
                }
            });
        }
    });

    function initSlideshow() {
        let slideIndex = 1;
        showSlides(slideIndex);
        
        window.plusSlides = function(n) {
            showSlides(slideIndex += n);
        }
        
        window.currentSlide = function(n) {
            showSlides(slideIndex = n);
        }
        
        function showSlides(n) {
            let i;
            let slides = document.getElementsByClassName("slide");
            let dots = document.getElementsByClassName("dot");
            
            if (!slides.length) return;
            
            if (n > slides.length) { slideIndex = 1; }
            if (n < 1) { slideIndex = slides.length; }
            
            for (i = 0; i < slides.length; i++) {
                slides[i].style.display = "none";
            }
            
            for (i = 0; i < dots.length; i++) {
                dots[i].className = dots[i].className.replace(" active", "");
            }
            
            slides[slideIndex - 1].style.display = "block";
            if (dots.length > 0) {
                dots[slideIndex - 1].className += " active";
            }
        }
        
        setInterval(() => {
            plusSlides(1);
        }, 6000);
    }

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
    
    // Mobile Search Functions
    function openSearchModal() {
        const modal = document.getElementById('searchModal');
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        setTimeout(() => {
            const input = modal.querySelector('.search-modal-input');
            if (input) input.focus();
        }, 100);
    }
    
    function closeSearchModal() {
        const modal = document.getElementById('searchModal');
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }
    
    let currentProductId = null;
    let currentProductName = '';
    let currentProductPrice = 0;
    
    const isLoggedIn = <?php echo isLoggedIn() ? 'true' : 'false'; ?>;
    const isApproved = <?php echo (isLoggedIn() && isApprovedUser()) ? 'true' : 'false'; ?>;
    
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
            closeSearchModal();
        }
    });
    </script>
</body>
</html>