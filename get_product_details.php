<?php
require_once 'includes/config.php';

header('Content-Type: application/json');

// Handle POST request - Add a new COMMENT only (NO rating)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'error' => 'Please login to comment']);
        exit();
    }
    
    $user_id = $_SESSION['user_id'];
    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    $comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';
    
    if ($product_id <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid product']);
        exit();
    }
    
    if (empty($comment)) {
        echo json_encode(['success' => false, 'error' => 'Please write a comment']);
        exit();
    }
    
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS product_comments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            product_id INT NOT NULL,
            user_id INT NOT NULL,
            comment TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        
        $insertStmt = $pdo->prepare("
            INSERT INTO product_comments (product_id, user_id, comment, created_at) 
            VALUES (?, ?, ?, NOW())
        ");
        $insertStmt->execute([$product_id, $user_id, $comment]);
        
        echo json_encode(['success' => true, 'message' => 'Comment posted successfully!']);
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
    exit();
}

// Handle GET request
$product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;

if (!$product_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid product ID']);
    exit();
}

function generateStarsHTML($rating) {
    $stars = '';
    $fullStars = floor($rating);
    for ($i = 1; $i <= 5; $i++) {
        if ($i <= $fullStars) {
            $stars .= '<i class="fas fa-star"></i>';
        } else {
            $stars .= '<i class="far fa-star"></i>';
        }
    }
    return $stars;
}

function timeAgo($timestamp) {
    $diff = time() - $timestamp;
    if ($diff < 60) return "Just now";
    if ($diff < 3600) return round($diff / 60) . " minutes ago";
    if ($diff < 86400) return round($diff / 3600) . " hours ago";
    if ($diff < 604800) return round($diff / 86400) . " days ago";
    return date('M d, Y', $timestamp);
}

try {
    // Get product details
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        echo json_encode(['success' => false, 'error' => 'Product not found']);
        exit();
    }
    
    // Get ratings from product_ratings table
    $ratingsStmt = $pdo->prepare("
        SELECT 
            COALESCE(ROUND(AVG(rating), 1), 0) as avg_rating,
            COUNT(*) as total_ratings,
            SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as five_star,
            SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as four_star,
            SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as three_star,
            SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as two_star,
            SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as one_star
        FROM product_ratings 
        WHERE product_id = ?
    ");
    $ratingsStmt->execute([$product_id]);
    $ratings = $ratingsStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$ratings) {
        $ratings = ['avg_rating' => 0, 'total_ratings' => 0, 'five_star' => 0, 'four_star' => 0, 'three_star' => 0, 'two_star' => 0, 'one_star' => 0];
    }
    
    // Get reviews (with ratings) - these are from users who purchased
    $reviewsStmt = $pdo->prepare("
        SELECT r.*, COALESCE(u.username, 'Anonymous') as username 
        FROM product_ratings r
        LEFT JOIN users u ON r.user_id = u.id
        WHERE r.product_id = ?
        ORDER BY r.created_at DESC
    ");
    $reviewsStmt->execute([$product_id]);
    $reviews = $reviewsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($reviews as &$review) {
        $review['stars_html'] = generateStarsHTML($review['rating']);
        $review['formatted_date'] = date('M d, Y', strtotime($review['created_at']));
        if (empty($review['review'])) {
            $review['review'] = null;
        }
    }
    
    // Get comments (no ratings) - these are from any logged-in user
    $commentsStmt = $pdo->prepare("
        SELECT c.*, COALESCE(u.username, 'Anonymous') as username
        FROM product_comments c
        LEFT JOIN users u ON c.user_id = u.id
        WHERE c.product_id = ?
        ORDER BY c.created_at DESC
    ");
    $commentsStmt->execute([$product_id]);
    $comments = $commentsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($comments as &$comment) {
        $comment['time_ago'] = timeAgo(strtotime($comment['created_at']));
    }
    
    $clean_image_url = isset($product['image_url']) ? preg_replace('/[^a-zA-Z0-9\._\-]/', '', $product['image_url']) : null;
    
    $response = [
        'success' => true,
        'id' => $product['id'],
        'name' => htmlspecialchars($product['name']),
        'description' => htmlspecialchars($product['description'] ?? ''),
        'price' => floatval($product['price']),
        'image_url' => $clean_image_url,
        'avg_rating' => floatval($ratings['avg_rating']),
        'avg_rating_stars' => generateStarsHTML(floatval($ratings['avg_rating'])),
        'total_ratings' => intval($ratings['total_ratings']),
        'five_star' => intval($ratings['five_star']),
        'four_star' => intval($ratings['four_star']),
        'three_star' => intval($ratings['three_star']),
        'two_star' => intval($ratings['two_star']),
        'one_star' => intval($ratings['one_star']),
        'reviews' => $reviews,
        'comments' => $comments,
        'total_comments' => count($comments),
        'can_comment' => isset($_SESSION['user_id'])  // Only for comment form
    ];
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>