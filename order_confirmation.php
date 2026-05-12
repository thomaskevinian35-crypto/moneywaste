<?php
// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/config.php'; // This already includes db_connection.php

if (!isset($_SESSION['user_id']) || !isset($_GET['order_id'])) {
    header("Location: products.php");
    exit();
}

$order_id = intval($_GET['order_id']);
$user_id = $_SESSION['user_id'];

try {
    // Use PDO instead of MySQLi
    global $pdo;
    
    // Get order details using PDO
    $query = "SELECT o.*, p.name as product_name, p.price 
              FROM orders o 
              JOIN products p ON o.product_id = p.id 
              WHERE o.id = ? AND o.user_id = ?";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$order_id, $user_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        header("Location: products.php");
        exit();
    }
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmation - MoneyWaste</title>
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
        
        .confirmation-container {
            max-width: 600px;
            margin: 50px auto;
            padding: 40px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .success-icon {
            color: #4CAF50;
            font-size: 80px;
            margin-bottom: 20px;
            width: 120px;
            height: 120px;
            line-height: 120px;
            background: #e8f5e9;
            border-radius: 50%;
            margin: 0 auto 30px;
            text-align: center;
        }
        
        h1 {
            font-size: 2.2rem;
            font-weight: 300;
            letter-spacing: 2px;
            margin-bottom: 15px;
            color: #333;
        }
        
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 1.1rem;
        }
        
        .order-details {
            background: #f9f9f9;
            padding: 30px;
            border-radius: 8px;
            margin: 30px 0;
            text-align: left;
            border: 1px solid #eee;
        }
        
        .order-details h3 {
            font-size: 1.3rem;
            font-weight: 500;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #ddd;
            color: #333;
        }
        
        .detail-row {
            display: flex;
            margin-bottom: 12px;
            padding: 5px 0;
        }
        
        .detail-label {
            font-weight: 600;
            width: 130px;
            color: #555;
        }
        
        .detail-value {
            flex: 1;
            color: #333;
        }
        
        .address-box {
            background: white;
            padding: 15px;
            border-radius: 6px;
            margin-top: 10px;
            border: 1px solid #ddd;
        }
        
        .address-box p {
            margin: 3px 0;
            color: #555;
        }
        
        .btn-continue {
            display: inline-block;
            background: #000;
            color: white;
            padding: 15px 40px;
            text-decoration: none;
            border-radius: 6px;
            font-size: 1.1rem;
            letter-spacing: 1px;
            transition: all 0.3s ease;
            margin-top: 20px;
            text-transform: uppercase;
        }
        
        .btn-continue:hover {
            background: #333;
            letter-spacing: 2px;
        }
        
        .payment-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .payment-cod {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .payment-gcash {
            background: #e8f5e9;
            color: #2e7d32;
        }
        
        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            background: #fff3e0;
            color: #f57c00;
            font-weight: 500;
        }
        
        @media (max-width: 768px) {
            .confirmation-container {
                margin: 20px;
                padding: 25px;
            }
            
            h1 {
                font-size: 1.8rem;
            }
            
            .detail-row {
                flex-direction: column;
            }
            
            .detail-label {
                width: 100%;
                margin-bottom: 5px;
            }
        }
    </style>
</head>
<body>
    <div class="confirmation-container">
        <div class="success-icon">✓</div>
        <h1>Thank You for Your Order!</h1>
        <p class="subtitle">Your order has been placed successfully.</p>
        
        <div class="order-details">
            <h3>Order Details</h3>
            
            <div class="detail-row">
                <span class="detail-label">Order ID:</span>
                <span class="detail-value">#<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?></span>
            </div>
            
            <div class="detail-row">
                <span class="detail-label">Product:</span>
                <span class="detail-value"><?php echo htmlspecialchars($order['product_name']); ?></span>
            </div>
            
            <div class="detail-row">
                <span class="detail-label">Quantity:</span>
                <span class="detail-value"><?php echo $order['quantity']; ?></span>
            </div>
            
            <div class="detail-row">
                <span class="detail-label">Total Amount:</span>
                <span class="detail-value"><strong>₱<?php echo number_format($order['total_amount'], 2); ?></strong></span>
            </div>
            
            <div class="detail-row">
                <span class="detail-label">Payment Method:</span>
                <span class="detail-value">
                    <?php if($order['payment_method'] == 'cash_on_delivery'): ?>
                        <span class="payment-badge payment-cod">💵 Cash on Delivery</span>
                    <?php else: ?>
                        <span class="payment-badge payment-gcash">📱 GCash</span>
                    <?php endif; ?>
                </span>
            </div>
            
            <div class="detail-row">
                <span class="detail-label">Order Status:</span>
                <span class="detail-value">
                    <span class="status-badge"><?php echo ucfirst($order['order_status']); ?></span>
                </span>
            </div>
            
            <div class="detail-row">
                <span class="detail-label">Customer Name:</span>
                <span class="detail-value"><?php echo htmlspecialchars($order['customer_name']); ?></span>
            </div>
            
            <div class="detail-row">
                <span class="detail-label">Email:</span>
                <span class="detail-value"><?php echo htmlspecialchars($order['customer_email']); ?></span>
            </div>
            
            <div class="detail-row">
                <span class="detail-label">Phone:</span>
                <span class="detail-value"><?php echo htmlspecialchars($order['customer_phone']); ?></span>
            </div>
            
            <div class="detail-row">
                <span class="detail-label">Shipping Address:</span>
                <span class="detail-value">
                    <div class="address-box">
                        <p><?php echo htmlspecialchars($order['customer_address']); ?></p>
                        <p><?php echo htmlspecialchars($order['customer_city']); ?> <?php echo htmlspecialchars($order['customer_postal']); ?></p>
                    </div>
                </span>
            </div>
            
            <div class="detail-row">
                <span class="detail-label">Order Date:</span>
                <span class="detail-value"><?php echo date('F j, Y, g:i a', strtotime($order['order_date'])); ?></span>
            </div>
        </div>
        
        <a href="products.php" class="btn-continue">Continue Shopping</a>
    </div>
</body>
</html>