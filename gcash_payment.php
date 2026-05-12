<?php
require_once 'includes/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get order details from URL
$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
$amount = isset($_GET['amount']) ? floatval($_GET['amount']) : 0;

if (!$order_id || !$amount) {
    header("Location: products.php");
    exit();
}

// Create sales_records table if not exists
$pdo->exec("CREATE TABLE IF NOT EXISTS sales_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method VARCHAR(50),
    recorded_at DATETIME NOT NULL,
    UNIQUE KEY unique_order (order_id)
)");

// Verify order belongs to user
$verifyQuery = "SELECT o.*, p.name as product_name 
                FROM orders o 
                LEFT JOIN products p ON o.product_id = p.id 
                WHERE o.id = ? AND o.user_id = ?";
$verifyStmt = $pdo->prepare($verifyQuery);
$verifyStmt->execute([$order_id, $_SESSION['user_id']]);
$order = $verifyStmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header("Location: products.php");
    exit();
}

// If order is already processing or delivered, redirect to confirmation
if ($order['order_status'] == 'processing' || $order['order_status'] == 'delivered') {
    header("Location: order_confirmation.php?order_id=" . $order_id);
    exit();
}

// GCash number (CHANGE THIS TO YOUR ACTUAL GCASH NUMBER)
$gcash_number = "09123456789";

// Handle payment confirmation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm_payment'])) {
    $reference_number = trim($_POST['reference_number']);
    $sender_name = trim($_POST['sender_name']);
    
    if (empty($reference_number) || empty($sender_name)) {
        $error = "Please provide the reference number and your name.";
    } else {
        try {
            $pdo->beginTransaction();
            
            // ========== UPDATED: Add GCash reference number to order ==========
            // Update order with GCash payment info AND set to processing
            $updateQuery = "UPDATE orders SET 
                            payment_status = 'paid',
                            order_status = 'processing',
                            gcash_reference = ?,
                            gcash_sender = ?,
                            payment_date = NOW()
                            WHERE id = ?";
            $updateStmt = $pdo->prepare($updateQuery);
            $updateStmt->execute([$reference_number, $sender_name, $order_id]);
            
            // ========== AUTO-ADD TO TOTAL SALES ==========
            // GCash payment is confirmed, so add to sales_records immediately
            $check_sales = $pdo->prepare("SELECT id FROM sales_records WHERE order_id = ?");
            $check_sales->execute([$order_id]);
            
            if ($check_sales->rowCount() == 0) {
                $insert_sales = $pdo->prepare("INSERT INTO sales_records (order_id, amount, payment_method, recorded_at) VALUES (?, ?, 'gcash', NOW())");
                $insert_sales->execute([$order_id, $order['total_amount']]);
                error_log("GCash Payment: Order #$order_id added to Total Sales - ₱" . $order['total_amount']);
            }
            // ========== END AUTO-ADD TO SALES ==========
            
            // Create notification for user
            $notif_message = "✅ GCash Payment Confirmed! Reference #: $reference_number\n\nYour order #$order_id (₱" . number_format($order['total_amount'], 2) . ") is now being processed.";
            $notif_stmt = $pdo->prepare("INSERT INTO notifications (user_id, order_id, message, status, created_at) VALUES (?, ?, ?, 'unread', NOW())");
            $notif_stmt->execute([$_SESSION['user_id'], $order_id, $notif_message]);
            
            $pdo->commit();
            
            // Redirect to order confirmation
            header("Location: order_confirmation.php?order_id=" . $order_id);
            exit();
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Error confirming payment: " . $e->getMessage();
            error_log("GCash payment error: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GCash Payment - MoneyWaste</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #f5f5f5;
            line-height: 1.6;
        }
        
        .gcash-container {
            max-width: 600px;
            margin: 40px auto;
            padding: 40px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }
        
        .gcash-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .gcash-logo {
            background: #00b4d8;
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }
        
        .gcash-logo i {
            font-size: 3rem;
            color: white;
        }
        
        h1 {
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 10px;
            color: #00b4d8;
        }
        
        .subtitle {
            color: #666;
            font-size: 0.9rem;
        }
        
        .payment-details {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 25px;
            margin: 20px 0;
            border: 1px solid #e9ecef;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .detail-row:last-child {
            border-bottom: none;
        }
        
        .detail-label {
            color: #666;
            font-weight: 500;
        }
        
        .detail-value {
            font-weight: 600;
            color: #333;
        }
        
        .amount-value {
            font-size: 1.5rem;
            color: #00b4d8;
        }
        
        .gcash-number-box {
            background: #00b4d8;
            color: white;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            margin: 20px 0;
        }
        
        .gcash-number {
            font-size: 2rem;
            font-weight: 700;
            letter-spacing: 2px;
            margin: 10px 0;
        }
        
        .instruction {
            background: #fff3e0;
            border-left: 4px solid #ff9800;
            padding: 15px;
            margin: 20px 0;
            border-radius: 8px;
        }
        
        .instruction h3 {
            color: #e65100;
            margin-bottom: 10px;
            font-size: 1rem;
        }
        
        .instruction ol {
            margin-left: 20px;
            color: #666;
        }
        
        .instruction li {
            margin: 8px 0;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #00b4d8;
            box-shadow: 0 0 0 3px rgba(0,180,216,0.1);
        }
        
        .btn-confirm {
            background: #00b4d8;
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            transition: all 0.3s ease;
        }
        
        .btn-confirm:hover {
            background: #0096b4;
            transform: translateY(-2px);
        }
        
        .error {
            background: #ffebee;
            color: #c62828;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #c62828;
        }
        
        .auto-sales-note {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 12px;
            border-radius: 8px;
            margin: 15px 0;
            font-size: 0.85rem;
            text-align: center;
            border-left: 4px solid #4caf50;
        }
        
        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: #666;
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.3s ease;
        }
        
        .back-link:hover {
            color: #000;
        }
        
        @media (max-width: 600px) {
            .gcash-container {
                margin: 20px;
                padding: 25px;
            }
            .gcash-number {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="gcash-container">
        <div class="gcash-header">
            <div class="gcash-logo">
                <i class="fab fa-google-pay"></i>
            </div>
            <h1>GCash Payment</h1>
            <p class="subtitle">Complete your payment securely via GCash</p>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="payment-details">
            <div class="detail-row">
                <span class="detail-label">Order ID:</span>
                <span class="detail-value">#<?php echo $order_id; ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Amount to Pay:</span>
                <span class="detail-value amount-value">₱<?php echo number_format($amount, 2); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Product:</span>
                <span class="detail-value"><?php echo htmlspecialchars($order['product_name'] ?? 'Product'); ?></span>
            </div>
        </div>
        
        <!-- Auto Sales Note -->
        <div class="auto-sales-note">
            <i class="fas fa-chart-line"></i> 
            <strong>Auto Sales Tracking:</strong> Once you confirm your payment, this order will be 
            <strong>automatically added to Total Sales</strong> (no admin action needed).
        </div>
        
        <div class="gcash-number-box">
            <i class="fas fa-qrcode" style="font-size: 2rem;"></i>
            <div class="gcash-number"><?php echo $gcash_number; ?></div>
            <small>Send payment to this GCash number</small>
        </div>
        
        <div class="instruction">
            <h3><i class="fas fa-info-circle"></i> How to pay via GCash:</h3>
            <ol>
                <li>Open your GCash app</li>
                <li>Click "Send Money" → "Send to GCash"</li>
                <li>Enter the GCash number above: <strong><?php echo $gcash_number; ?></strong></li>
                <li>Enter the amount: <strong>₱<?php echo number_format($amount, 2); ?></strong></li>
                <li>Enter your name and a note with your Order ID: <strong>#<?php echo $order_id; ?></strong></li>
                <li>Complete the payment</li>
                <li>Enter the reference number below to confirm your payment</li>
            </ol>
        </div>
        
        <form method="POST" action="" onsubmit="return validateForm()">
            <div class="form-group">
                <label for="reference_number">GCash Reference Number *</label>
                <input type="text" id="reference_number" name="reference_number" required 
                       placeholder="Enter the reference number from GCash"
                       autocomplete="off">
                <small style="color: #666; font-size: 0.7rem;">Found in your GCash transaction history</small>
            </div>
            
            <div class="form-group">
                <label for="sender_name">Your Name (as shown in GCash) *</label>
                <input type="text" id="sender_name" name="sender_name" required 
                       placeholder="Enter your full name">
            </div>
            
            <button type="submit" name="confirm_payment" class="btn-confirm">
                <i class="fas fa-check-circle"></i> Confirm & Complete Payment
            </button>
        </form>
        
        <div style="text-align: center; margin-top: 20px;">
            <a href="products.php" class="back-link">← Back to Shopping</a>
        </div>
    </div>
    
    <script>
        function validateForm() {
            const reference = document.getElementById('reference_number').value.trim();
            const senderName = document.getElementById('sender_name').value.trim();
            
            if (!reference) {
                alert('Please enter the GCash reference number');
                return false;
            }
            
            if (!senderName) {
                alert('Please enter your name');
                return false;
            }
            
            if (reference.length < 10) {
                alert('Please enter a valid GCash reference number (usually 12-13 digits)');
                return false;
            }
            
            return confirm('IMPORTANT: Click OK to confirm your payment.\n\nYour order will be automatically processed and added to Total Sales.\n\nMake sure you have completed the payment before confirming.');
        }
        
        // Capitalize first letter of each word in sender name
        const senderNameInput = document.getElementById('sender_name');
        if (senderNameInput) {
            senderNameInput.addEventListener('input', function(e) {
                let words = this.value.split(' ');
                for (let i = 0; i < words.length; i++) {
                    if (words[i].length > 0) {
                        words[i] = words[i].charAt(0).toUpperCase() + words[i].slice(1).toLowerCase();
                    }
                }
                this.value = words.join(' ');
            });
        }
        
        // Only allow numbers in reference field
        const referenceInput = document.getElementById('reference_number');
        if (referenceInput) {
            referenceInput.addEventListener('input', function(e) {
                this.value = this.value.replace(/[^0-9]/g, '');
            });
        }
    </script>
</body>
</html>