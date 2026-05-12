<?php
require_once 'includes/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Mark notification as read if requested
if (isset($_GET['mark_read']) && isset($_GET['id'])) {
    $stmt = $pdo->prepare("UPDATE notifications SET status = 'read' WHERE id = ? AND user_id = ?");
    $stmt->execute([$_GET['id'], $user_id]);
    header('Location: notifications.php');
    exit();
}

// Mark all as read
if (isset($_POST['mark_all_read'])) {
    $stmt = $pdo->prepare("UPDATE notifications SET status = 'read' WHERE user_id = ?");
    $stmt->execute([$user_id]);
    header('Location: notifications.php');
    exit();
}

// Get notifications for this user
$notif_query = "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC";
$notif_stmt = $pdo->prepare($notif_query);
$notif_stmt->execute([$user_id]);
$notifications = $notif_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get unread count
$unread_query = "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND status = 'unread'";
$unread_stmt = $pdo->prepare($unread_query);
$unread_stmt->execute([$user_id]);
$unread_count = $unread_stmt->fetchColumn();

// Function to convert rate_product.php links into clickable buttons
function makeLinksClickable($text) {
    // Look for rate_product.php?order_id=X&product_id=Y pattern
    $pattern = '/rate_product\.php\?order_id=(\d+)&product_id=(\d+)/i';
    
    $text = preg_replace_callback($pattern, function($matches) {
        $order_id = $matches[1];
        $product_id = $matches[2];
        // Build the full URL
        $full_url = "rate_product.php?order_id=" . $order_id . "&product_id=" . $product_id;
        
        // Return a styled clickable button
        return '<a href="' . $full_url . '" class="rating-button" style="display: inline-block; background: #ff9800; color: white; padding: 8px 20px; border-radius: 25px; text-decoration: none; font-weight: bold; margin-top: 8px;">⭐ Rate Your Product</a>';
    }, $text);
    
    // Also handle regular URLs
    $pattern2 = '/((?:https?:\/\/|www\.)[^\s<]+)/i';
    $text = preg_replace_callback($pattern2, function($matches) {
        $url = $matches[1];
        $full_url = (preg_match('/^https?:\/\//i', $url)) ? $url : 'http://' . $url;
        return '<a href="' . htmlspecialchars($full_url) . '" target="_blank" style="color: #2196f3; text-decoration: underline;">' . htmlspecialchars($url) . '</a>';
    }, $text);
    
    return nl2br($text);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - MONEYWASTE</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f5f5; }
        .container { max-width: 800px; margin: 2rem auto; padding: 0 1rem; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; background: white; padding: 1.5rem; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .header h1 { font-size: 2rem; font-weight: 300; letter-spacing: 2px; display: flex; align-items: center; gap: 10px; }
        .header h1 i { color: #000; }
        .badge { background: #f44336; color: white; border-radius: 50%; padding: 2px 8px; font-size: 0.8rem; margin-left: 10px; }
        .mark-all-btn { background: #000; color: white; border: none; padding: 0.5rem 1.5rem; border-radius: 6px; cursor: pointer; font-size: 0.9rem; transition: background 0.3s; }
        .mark-all-btn:hover { background: #333; }
        .mark-all-btn:disabled { background: #ccc; cursor: not-allowed; }
        .notifications-list { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .notification-item { display: flex; align-items: flex-start; gap: 1rem; padding: 1.5rem; border-bottom: 1px solid #eee; transition: background 0.3s; position: relative; }
        .notification-item:last-child { border-bottom: none; }
        .notification-item.unread { background: #f0f7ff; border-left: 4px solid #2196f3; }
        .notification-item:hover { background: #f9f9f9; }
        .notification-icon { width: 40px; height: 40px; border-radius: 50%; background: #000; color: white; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; flex-shrink: 0; }
        .notification-icon.reply { background: #4caf50; }
        .notification-icon.order { background: #2196f3; }
        .notification-icon.rating { background: #ff9800; }
        .notification-content { flex: 1; }
        .notification-message { color: #333; font-size: 1rem; margin-bottom: 0.5rem; line-height: 1.6; }
        .notification-message a.rating-button { 
            display: inline-block;
            background: #ff9800;
            color: white;
            padding: 8px 20px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: bold;
            margin-top: 8px;
            transition: all 0.3s ease;
        }
        .notification-message a.rating-button:hover { 
            background: #e65100;
            transform: translateY(-2px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }
        .notification-message a { color: #2196f3; text-decoration: underline; }
        .notification-meta { display: flex; align-items: center; gap: 1rem; font-size: 0.8rem; color: #999; margin-top: 8px; }
        .notification-meta i { margin-right: 0.3rem; }
        .notification-actions { display: flex; gap: 0.5rem; margin-top: 0.5rem; }
        .mark-read-btn { background: none; border: 1px solid #ddd; padding: 0.3rem 1rem; border-radius: 4px; color: #666; text-decoration: none; font-size: 0.8rem; transition: all 0.3s; }
        .mark-read-btn:hover { background: #f0f0f0; border-color: #999; }
        .unread-dot { width: 8px; height: 8px; background: #2196f3; border-radius: 50%; position: absolute; right: 1.5rem; top: 1.5rem; }
        .empty-state { text-align: center; padding: 4rem; background: white; border-radius: 12px; }
        .empty-state i { font-size: 4rem; color: #ccc; margin-bottom: 1rem; }
        .empty-state p { color: #666; font-size: 1.2rem; margin-bottom: 0.5rem; }
        .empty-state small { color: #999; font-size: 0.9rem; }
        .back-link { display: inline-block; margin-bottom: 1rem; color: #666; text-decoration: none; transition: color 0.3s; }
        .back-link:hover { color: #000; }
        .back-link i { margin-right: 0.5rem; }
        @media (max-width: 600px) {
            .header { flex-direction: column; gap: 1rem; text-align: center; }
            .notification-item { flex-direction: column; align-items: flex-start; }
            .unread-dot { top: 1rem; right: 1rem; }
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="index.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Home</a>

        <div class="header">
            <h1><i class="fas fa-bell"></i> Notifications
                <?php if ($unread_count > 0): ?>
                    <span class="badge"><?php echo $unread_count; ?></span>
                <?php endif; ?>
            </h1>
            <?php if ($unread_count > 0): ?>
                <form method="POST" style="display: inline;">
                    <button type="submit" name="mark_all_read" class="mark-all-btn"><i class="fas fa-check-double"></i> Mark All as Read</button>
                </form>
            <?php else: ?>
                <button class="mark-all-btn" disabled>All Read</button>
            <?php endif; ?>
        </div>

        <div class="notifications-list">
            <?php if (count($notifications) > 0): ?>
                <?php foreach ($notifications as $notification): ?>
                    <?php
                    // Determine icon based on message content
                    $icon_class = '';
                    $icon = 'bell';
                    $message_lower = strtolower($notification['message']);
                    
                    if (strpos($message_lower, 'reply') !== false) {
                        $icon_class = 'reply';
                        $icon = 'reply';
                    } elseif (strpos($message_lower, 'rate') !== false || strpos($message_lower, 'rating') !== false) {
                        $icon_class = 'rating';
                        $icon = 'star';
                    } elseif (strpos($message_lower, 'order') !== false) {
                        $icon_class = 'order';
                        $icon = 'shopping-bag';
                    }
                    
                    // IMPORTANT: Don't escape the message before processing links
                    // Process first, then the HTML will be safe because we control the output
                    $processed_message = makeLinksClickable($notification['message']);
                    ?>
                    <div class="notification-item <?php echo $notification['status'] == 'unread' ? 'unread' : ''; ?>">
                        <div class="notification-icon <?php echo $icon_class; ?>">
                            <i class="fas fa-<?php echo $icon; ?>"></i>
                        </div>
                        <div class="notification-content">
                            <div class="notification-message"><?php echo $processed_message; ?></div>
                            <div class="notification-meta">
                                <span><i class="far fa-clock"></i> 
                                <?php 
                                $time = strtotime($notification['created_at']);
                                $now = time();
                                $diff = $now - $time;
                                if ($diff < 60) echo 'Just now';
                                elseif ($diff < 3600) echo floor($diff / 60) . ' minutes ago';
                                elseif ($diff < 86400) echo floor($diff / 3600) . ' hours ago';
                                elseif ($diff < 604800) echo floor($diff / 86400) . ' days ago';
                                else echo date('M j, Y', $time);
                                ?>
                                </span>
                            </div>
                            <?php if ($notification['status'] == 'unread'): ?>
                                <div class="notification-actions">
                                    <a href="?mark_read=1&id=<?php echo $notification['id']; ?>" class="mark-read-btn"><i class="fas fa-check"></i> Mark as Read</a>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php if ($notification['status'] == 'unread'): ?>
                            <div class="unread-dot"></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-bell-slash"></i>
                    <p>No notifications yet</p>
                    <small>When you have notifications, they'll appear here</small>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <script>
        setTimeout(function() { location.reload(); }, 30000);
    </script>
</body>
</html>