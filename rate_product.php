<?php
require_once 'includes/config.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
$product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;
$success = '';
$error = '';

// Debug info (remove after testing)
$debug_info = "";

if (!$order_id || !$product_id) {
    header("Location: myorders.php");
    exit();
}

// First, check if order exists and get its details
$order_check = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
$order_check->execute([$order_id]);
$order_data = $order_check->fetch(PDO::FETCH_ASSOC);

if (!$order_data) {
    $error = "Order #$order_id not found.";
} else {
    $debug_info .= "Order found. User ID in order: " . $order_data['user_id'] . ", Your user ID: " . $user_id . ", Status: " . $order_data['order_status'];
    
    // Verify order belongs to user and is delivered
    if ($order_data['user_id'] != $user_id) {
        $error = "This order does not belong to you.";
    } elseif ($order_data['order_status'] != 'delivered') {
        $error = "You can only rate products from delivered orders. Current status: " . $order_data['order_status'];
    } else {
        // Get product name
        $prod = $pdo->prepare("SELECT name FROM products WHERE id = ?");
        $prod->execute([$product_id]);
        $product_name = $prod->fetchColumn();
        
        if (!$product_name) {
            $error = "Product not found.";
        }
    }
}

// If no error yet, proceed to get existing rating
if (empty($error)) {
    // Get existing rating
    $existing = $pdo->prepare("SELECT rating, review FROM product_ratings WHERE order_id = ? AND product_id = ?");
    $existing->execute([$order_id, $product_id]);
    $has_rated = $existing->fetch(PDO::FETCH_ASSOC);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && empty($error)) {
    $rating = intval($_POST['rating']);
    $review = trim($_POST['review']);
    
    if ($rating < 1 || $rating > 5) {
        $error = "Please select a rating (1-5 stars).";
    } elseif (strlen($review) < 5) {
        $error = "Please write at least 5 characters for your review.";
    } else {
        try {
            if ($has_rated) {
                $stmt = $pdo->prepare("UPDATE product_ratings SET rating = ?, review = ?, updated_at = NOW() WHERE order_id = ? AND product_id = ?");
                $stmt->execute([$rating, $review, $order_id, $product_id]);
                $success = "Your review has been updated!";
            } else {
                $stmt = $pdo->prepare("INSERT INTO product_ratings (order_id, product_id, user_id, rating, review) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$order_id, $product_id, $user_id, $rating, $review]);
                $success = "Thank you for your review!";
            }
            // Refresh existing data
            $existing = $pdo->prepare("SELECT rating, review FROM product_ratings WHERE order_id = ? AND product_id = ?");
            $existing->execute([$order_id, $product_id]);
            $has_rated = $existing->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rate Product - MoneyWaste</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f5f5; padding: 40px 20px; }
        .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 20px; padding: 30px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        h1 { font-size: 1.8rem; margin-bottom: 20px; text-align: center; }
        .product-name { text-align: center; color: #666; margin-bottom: 30px; }
        .stars { display: flex; justify-content: center; gap: 10px; font-size: 2.5rem; margin-bottom: 20px; cursor: pointer; }
        .stars i { color: #ddd; transition: 0.2s; }
        .stars i.active, .stars i:hover { color: #ffc107; }
        textarea { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 1rem; margin-bottom: 20px; font-family: inherit; resize: vertical; }
        button { background: #000; color: white; border: none; padding: 12px 24px; border-radius: 8px; font-size: 1rem; cursor: pointer; width: 100%; }
        button:hover { background: #333; }
        button:disabled { background: #ccc; cursor: not-allowed; }
        .message { padding: 10px; border-radius: 8px; margin-bottom: 20px; text-align: center; }
        .success { background: #d4edda; color: #155724; }
        .error { background: #f8d7da; color: #721c24; }
        .back-link { display: block; text-align: center; margin-top: 20px; color: #666; text-decoration: none; }
        .back-link:hover { color: #000; }
        .order-info { background: #f8f9fa; padding: 15px; border-radius: 12px; margin-bottom: 20px; font-size: 0.9rem; }
        .debug { background: #fff3cd; color: #856404; padding: 10px; border-radius: 8px; margin-bottom: 20px; font-size: 0.8rem; display: none; }
    </style>
</head>
<body>
<div class="container">
    <h1><i class="fas fa-star"></i> Rate Your Purchase</h1>
    
    <?php if ($error): ?>
        <div class="message error">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            <br><br>
            <small>Debug: <?php echo htmlspecialchars($debug_info); ?></small>
        </div>
        <a href="myorders.php" class="back-link">← Back to My Orders</a>
    <?php elseif ($success): ?>
        <div class="message success">
            <i class="fas fa-check-circle"></i> <?php echo $success; ?>
        </div>
        <div class="order-info">
            <strong>Product:</strong> <?php echo htmlspecialchars($product_name); ?><br>
            <strong>Order #:</strong> <?php echo $order_id; ?>
        </div>
        <a href="products.php" class="back-link">← Continue Shopping</a>
    <?php else: ?>
        <div class="order-info">
            <strong>Product:</strong> <?php echo htmlspecialchars($product_name); ?><br>
            <strong>Order #:</strong> <?php echo $order_id; ?><br>
            <strong>Status:</strong> Delivered ✓
        </div>
        
        <form method="POST">
            <div class="stars" id="starSelector">
                <i class="far fa-star" data-val="1"></i>
                <i class="far fa-star" data-val="2"></i>
                <i class="far fa-star" data-val="3"></i>
                <i class="far fa-star" data-val="4"></i>
                <i class="far fa-star" data-val="5"></i>
            </div>
            <input type="hidden" name="rating" id="ratingValue" value="<?php echo $has_rated ? $has_rated['rating'] : 0; ?>">
            <textarea name="review" rows="5" placeholder="Share your experience with this product..."><?php echo htmlspecialchars($has_rated['review'] ?? ''); ?></textarea>
            <button type="submit"><?php echo $has_rated ? 'Update Review' : 'Submit Review'; ?></button>
        </form>
        <a href="myorders.php" class="back-link">← Back to My Orders</a>
    <?php endif; ?>
</div>

<script>
    const stars = document.querySelectorAll('#starSelector i');
    const ratingInput = document.getElementById('ratingValue');
    let currentRating = parseInt(ratingInput.value) || 0;
    
    function updateStars(rating) {
        stars.forEach((star, idx) => {
            if (idx < rating) star.className = 'fas fa-star active';
            else star.className = 'far fa-star';
        });
    }
    
    if (stars.length > 0) {
        stars.forEach(star => {
            star.addEventListener('click', function() {
                currentRating = parseInt(this.dataset.val);
                ratingInput.value = currentRating;
                updateStars(currentRating);
            });
            star.addEventListener('mouseenter', function() {
                updateStars(parseInt(this.dataset.val));
            });
            star.addEventListener('mouseleave', function() {
                updateStars(currentRating);
            });
        });
        updateStars(currentRating);
    }
</script>
</body>
</html>