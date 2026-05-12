<?php
// NO session_start() here - it's already in config.php
require_once 'includes/config.php'; // This now handles session safely

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?redirect=checkout");
    exit();
}

// Check if product ID is provided
if (!isset($_GET['product_id']) || empty($_GET['product_id'])) {
    header("Location: products.php");
    exit();
}

// Use PDO from db_connection.php (included via config.php)
global $pdo;

$product_id = intval($_GET['product_id']);
$user_id = $_SESSION['user_id'];

// Size price mapping (additional cost per size)
$sizePrices = [
    'XS' => 0,
    'S' => 0,
    'M' => 0,
    'L' => 50,
    'XL' => 100,
    'XXL' => 150
];

// Get REAL-TIME stock from database
$sizeStock = [
    'XS' => 0,
    'S' => 0,
    'M' => 0,
    'L' => 0,
    'XL' => 0,
    'XXL' => 0
];

try {
    // Get product details
    $query = "SELECT * FROM products WHERE id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        header("Location: products.php");
        exit();
    }

    // Get REAL-TIME stock from product_sizes table
    $stockQuery = "SELECT size, stock FROM product_sizes WHERE product_id = ?";
    $stockStmt = $pdo->prepare($stockQuery);
    $stockStmt->execute([$product_id]);
    while ($row = $stockStmt->fetch(PDO::FETCH_ASSOC)) {
        if (isset($sizeStock[$row['size']])) {
            $sizeStock[$row['size']] = $row['stock'];
        }
    }

    // Get product image path
    $product_image = 'images/no-image.jpg';
    if (!empty($product['image_url']) && file_exists('images/' . $product['image_url'])) {
        $product_image = 'images/' . $product['image_url'];
    } elseif (!empty($product['image_url']) && file_exists($product['image_url'])) {
        $product_image = $product['image_url'];
    } else {
        $productName = strtolower($product['name']);
        if (strpos($productName, 'hoodie') !== false) {
            $product_image = 'images/jacket1.jpg';
        } elseif (strpos($productName, 'black') !== false && strpos($productName, 'tee') !== false) {
            $product_image = 'images/tshirtblk.jpg';
        } elseif (strpos($productName, 'white') !== false && strpos($productName, 'tee') !== false) {
            $product_image = 'images/tshirtwht.jpg';
        } elseif (strpos($productName, 'towel') !== false) {
            $product_image = 'images/featured7.png';
        } elseif (file_exists('images/featured.jpg')) {
            $product_image = 'images/featured.jpg';
        }
    }

    // Get user details - UPDATED to fetch all address fields
    $user = [
        'fullname' => '',
        'email' => '',
        'address' => '',
        'city' => '',
        'postal_code' => '',
        'phone' => ''
    ];
    
    try {
        $user_query = "SELECT id, username, fullname, email, address, city, postal_code, phone FROM users WHERE id = ?";
        $user_stmt = $pdo->prepare($user_query);
        $user_stmt->execute([$user_id]);
        $user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user_data) {
            $user['fullname'] = !empty($user_data['fullname']) ? $user_data['fullname'] : $user_data['username'];
            $user['email'] = $user_data['email'] ?? '';
            $user['address'] = $user_data['address'] ?? '';
            $user['city'] = $user_data['city'] ?? '';
            $user['postal_code'] = $user_data['postal_code'] ?? '';
            $user['phone'] = $user_data['phone'] ?? '';
        }
    } catch (PDOException $e) {
        error_log("User data fetch error: " . $e->getMessage());
    }

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Process checkout form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $address = trim($_POST['address']);
    $city = trim($_POST['city']);
    $postal_code = trim($_POST['postal_code']);
    $phone = trim($_POST['phone']);
    $payment_method = $_POST['payment_method'];
    $quantity = intval($_POST['quantity']);
    $size = $_POST['size'];
    
    // Validate inputs
    $errors = [];
    
    if (empty($fullname)) $errors[] = "Full name is required.";
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required.";
    if (empty($address)) $errors[] = "Address is required.";
    if (empty($city)) $errors[] = "City is required.";
    if (empty($postal_code) || !preg_match('/^\d{4}$/', $postal_code)) $errors[] = "Valid 4-digit postal code is required.";
    
    $phoneClean = preg_replace('/\D/', '', $phone);
    if (empty($phone) || !preg_match('/^(09|\+639|9)\d{9}$/', $phoneClean)) {
        $errors[] = "Valid Philippine mobile number is required (e.g., 09123456789).";
    }
    
    if (empty($size)) $errors[] = "Please select a size.";
    if ($quantity < 1) $errors[] = "Quantity must be at least 1.";
    
    // Calculate price based on size
    $basePrice = $product['price'];
    $sizeExtra = isset($sizePrices[$size]) ? $sizePrices[$size] : 0;
    $unitPrice = $basePrice + $sizeExtra;
    $total_amount = $unitPrice * $quantity;
    
    // Check REAL-TIME stock for selected size
    $availableStock = isset($sizeStock[$size]) ? $sizeStock[$size] : 0;
    
    if ($quantity > $availableStock) {
        $errors[] = "Sorry, only $availableStock item(s) available in size $size.";
    }
    
    if (!empty($errors)) {
        $error = implode("<br>", $errors);
    } else {
        try {
            $pdo->beginTransaction();
            
            // Insert order into database
            $order_query = "INSERT INTO orders (
                user_id, product_id, size, quantity, unit_price, total_amount, 
                customer_name, customer_email, customer_address, 
                customer_city, customer_postal, customer_phone, 
                payment_method, order_status, order_date
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())";
            
            $order_stmt = $pdo->prepare($order_query);
            $order_stmt->execute([
                $user_id,
                $product_id,
                $size,
                $quantity,
                $unitPrice,
                $total_amount,
                $fullname,
                $email,
                $address,
                $city,
                $postal_code,
                $phone,
                $payment_method
            ]);
            
            $order_id = $pdo->lastInsertId();
            
            // ========== REAL-TIME STOCK DEDUCTION ==========
            try {
                // Deduct stock from product_sizes table
                $updateStockSql = "UPDATE product_sizes SET stock = stock - ? WHERE product_id = ? AND size = ? AND stock >= ?";
                $updateStock = $pdo->prepare($updateStockSql);
                $updateStock->execute([$quantity, $product_id, $size, $quantity]);
                
                // Also update the main products table stock (for backward compatibility)
                $updateMainSql = "UPDATE products SET stock = stock - ? WHERE id = ?";
                $updateMain = $pdo->prepare($updateMainSql);
                $updateMain->execute([$quantity, $product_id]);
                
                error_log("STOCK DEDUCTED: Product ID $product_id, Size $size, Quantity $quantity");
                
            } catch (PDOException $e) {
                error_log("STOCK DEDUCTION FAILED: " . $e->getMessage());
            }
            
            $pdo->commit();
            
            // If GCash is selected, redirect to GCash payment page
            if ($payment_method == 'gcash') {
                $_SESSION['pending_order_id'] = $order_id;
                $_SESSION['gcash_amount'] = $total_amount;
                $_SESSION['gcash_order_id'] = $order_id;
                header("Location: gcash_payment.php?order_id=" . $order_id . "&amount=" . $total_amount);
                exit();
            } else {
                header("Location: order_confirmation.php?order_id=" . $order_id);
                exit();
            }
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Failed to place order. Please try again. Error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - MoneyWaste</title>
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
        
        .checkout-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 40px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }
        
        h1 {
            font-size: 2.5rem;
            font-weight: 300;
            letter-spacing: 2px;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        h2 {
            font-size: 1.5rem;
            font-weight: 400;
            margin: 30px 0 20px;
            color: #333;
        }
        
        .checkout-grid {
            display: grid;
            grid-template-columns: 1fr 1.5fr;
            gap: 40px;
        }
        
        .product-summary {
            background: #f9f9f9;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #eee;
            position: sticky;
            top: 20px;
        }
        
        .product-image-section {
            background: white;
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid #eee;
        }
        
        .product-image {
            max-width: 100%;
            max-height: 300px;
            object-fit: contain;
            border-radius: 8px;
        }
        
        .product-details-section {
            padding: 25px;
        }
        
        .product-name {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 10px;
            color: #000;
        }
        
        .product-description {
            color: #666;
            font-size: 0.9rem;
            line-height: 1.6;
            margin-bottom: 20px;
        }
        
        .price-breakdown {
            background: white;
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
            border: 1px solid #e0e0e0;
        }
        
        .price-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .price-row:last-child {
            border-bottom: none;
        }
        
        .price-label {
            color: #666;
        }
        
        .price-value {
            font-weight: 500;
            color: #333;
        }
        
        .total-section {
            background: #000;
            color: white;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .total-label {
            font-size: 1.2rem;
            letter-spacing: 1px;
        }
        
        .total-amount {
            font-size: 1.8rem;
            font-weight: 600;
            letter-spacing: 2px;
        }
        
        .size-selector {
            margin: 20px 0;
        }
        
        .size-title {
            font-weight: 600;
            margin-bottom: 12px;
            color: #333;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .size-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 10px;
        }
        
        .size-option {
            position: relative;
        }
        
        .size-option input {
            position: absolute;
            opacity: 0;
            cursor: pointer;
        }
        
        .size-label {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 12px 5px;
            background: white;
            border: 2px solid #ddd;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
        }
        
        .size-option input:checked + .size-label {
            border-color: #000;
            background: #000;
            color: white;
        }
        
        .size-option input:checked + .size-label .size-price {
            color: #ffd700;
        }
        
        .size-label:hover:not(.disabled) {
            border-color: #999;
            transform: translateY(-2px);
        }
        
        .size-label.disabled {
            opacity: 0.4;
            cursor: not-allowed;
            background: #f5f5f5;
        }
        
        .size-name {
            font-weight: 600;
            font-size: 1rem;
        }
        
        .size-price {
            font-size: 0.7rem;
            color: #666;
            margin-top: 4px;
        }
        
        .size-stock {
            font-size: 0.65rem;
            color: #4caf50;
            margin-top: 2px;
        }
        
        .size-stock.sold-out {
            color: #f44336;
        }
        
        .size-option input:checked + .size-label .size-stock {
            color: #a5d6a7;
        }
        
        .quantity-selector {
            display: flex;
            align-items: center;
            gap: 15px;
            margin: 20px 0;
        }
        
        .quantity-btn {
            width: 40px;
            height: 40px;
            border: 1px solid #ddd;
            background: white;
            font-size: 1.5rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            border-radius: 4px;
        }
        
        .quantity-btn:hover {
            background: #f0f0f0;
            border-color: #999;
        }
        
        .quantity-input {
            width: 80px;
            height: 40px;
            text-align: center;
            font-size: 1.2rem;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
            font-size: 0.95rem;
            letter-spacing: 0.5px;
        }
        
        .form-group label .required {
            color: #d32f2f;
        }
        
        .form-group input, 
        .form-group select, 
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 1rem;
            transition: all 0.3s ease;
            font-family: inherit;
        }
        
        .form-group input:focus, 
        .form-group select:focus, 
        .form-group textarea:focus {
            outline: none;
            border-color: #000;
            box-shadow: 0 0 0 3px rgba(0,0,0,0.1);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .btn-submit {
            background: #000;
            color: white;
            padding: 18px 30px;
            border: none;
            border-radius: 6px;
            font-size: 1.2rem;
            font-weight: 500;
            letter-spacing: 2px;
            cursor: pointer;
            width: 100%;
            margin-top: 30px;
            transition: all 0.3s ease;
            text-transform: uppercase;
        }
        
        .btn-submit:hover {
            background: #333;
            letter-spacing: 3px;
        }
        
        .error {
            color: #d32f2f;
            margin-bottom: 20px;
            padding: 15px;
            background: #ffebee;
            border-radius: 6px;
            border-left: 4px solid #d32f2f;
            font-size: 0.95rem;
        }
        
        .note {
            background: #e3f2fd;
            color: #1976d2;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            border-left: 4px solid #1976d2;
        }
        
        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: #666;
            text-decoration: none;
            font-size: 0.95rem;
            letter-spacing: 0.5px;
            transition: color 0.3s ease;
        }
        
        .back-link:hover {
            color: #000;
        }
        
        select {
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 15px center;
            background-size: 15px;
            cursor: pointer;
        }
        
        .input-hint {
            font-size: 0.7rem;
            color: #999;
            margin-top: 4px;
            display: block;
        }
        
        @media (max-width: 900px) {
            .checkout-grid {
                grid-template-columns: 1fr;
                gap: 30px;
            }
            .product-summary {
                position: static;
            }
        }
        
        @media (max-width: 768px) {
            .checkout-container {
                margin: 20px;
                padding: 25px;
            }
            h1 {
                font-size: 2rem;
            }
            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }
            .size-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 8px;
            }
            .total-amount {
                font-size: 1.5rem;
            }
        }
        
        @media (max-width: 480px) {
            .size-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <div class="checkout-container">
        <h1>Checkout</h1>
        
        <?php if (isset($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (empty($user['fullname']) || empty($user['address']) || empty($user['phone'])): ?>
        <div class="note">
            <strong>Note:</strong> Please fill in your shipping information below. This will be saved to your account for future orders.
        </div>
        <?php endif; ?>
        
        <form method="POST" action="" id="checkoutForm">
            <input type="hidden" name="quantity" id="hiddenQuantity" value="1">
            
            <div class="checkout-grid">
                <div class="product-summary">
                    <div class="product-image-section">
                        <img src="<?php echo $product_image; ?>" 
                             alt="<?php echo htmlspecialchars($product['name']); ?>" 
                             class="product-image"
                             onerror="this.src='https://via.placeholder.com/300x300?text=No+Image'">
                    </div>
                    
                    <div class="product-details-section">
                        <h3 class="product-name"><?php echo htmlspecialchars($product['name']); ?></h3>
                        <p class="product-description"><?php echo htmlspecialchars($product['description']); ?></p>
                        
                        <div class="price-breakdown">
                            <div class="price-row">
                                <span class="price-label">Base Price:</span>
                                <span class="price-value">₱<?php echo number_format($product['price'], 2); ?></span>
                            </div>
                            <div class="price-row" id="sizeExtraRow" style="display: none;">
                                <span class="price-label">Size Premium:</span>
                                <span class="price-value" id="sizeExtra">₱0.00</span>
                            </div>
                            <div class="price-row">
                                <span class="price-label">Unit Price:</span>
                                <span class="price-value" id="unitPriceDisplay">₱<?php echo number_format($product['price'], 2); ?></span>
                            </div>
                        </div>
                        
                        <div class="size-selector">
                            <div class="size-title">
                                <span>Select Size <span style="color: red;">*</span></span>
                            </div>
                            <div class="size-grid" id="sizeGrid">
                                <?php foreach ($sizePrices as $sizeName => $extraPrice): 
                                    $stock = isset($sizeStock[$sizeName]) ? $sizeStock[$sizeName] : 0;
                                    $disabled = $stock <= 0;
                                    $stockClass = $stock <= 0 ? 'sold-out' : '';
                                    $stockText = $stock <= 0 ? 'SOLD OUT' : $stock . ' left';
                                ?>
                                <div class="size-option">
                                    <input type="radio" 
                                           name="size" 
                                           id="size_<?php echo $sizeName; ?>" 
                                           value="<?php echo $sizeName; ?>"
                                           data-extra="<?php echo $extraPrice; ?>"
                                           data-stock="<?php echo $stock; ?>"
                                           <?php echo $disabled ? 'disabled' : ''; ?>
                                           <?php echo !$disabled && $sizeName == 'M' ? 'checked' : ''; ?>
                                           required>
                                    <label for="size_<?php echo $sizeName; ?>" class="size-label <?php echo $disabled ? 'disabled' : ''; ?>">
                                        <span class="size-name"><?php echo $sizeName; ?></span>
                                        <?php if ($extraPrice > 0): ?>
                                            <span class="size-price">+₱<?php echo number_format($extraPrice, 2); ?></span>
                                        <?php else: ?>
                                            <span class="size-price">Regular</span>
                                        <?php endif; ?>
                                        <span class="size-stock <?php echo $stockClass; ?>"><?php echo $stockText; ?></span>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div class="quantity-selector">
                            <button type="button" class="quantity-btn" id="decreaseBtn">−</button>
                            <input type="number" id="quantity" class="quantity-input" value="1" min="1" max="99">
                            <button type="button" class="quantity-btn" id="increaseBtn">+</button>
                        </div>
                        
                        <div class="total-section">
                            <span class="total-label">TOTAL AMOUNT:</span>
                            <span class="total-amount" id="totalAmount">₱<?php echo number_format($product['price'], 2); ?></span>
                        </div>
                    </div>
                </div>
                
                <div>
                    <h2>Shipping Information</h2>
                    
                    <div class="form-group">
                        <label for="fullname">Full Name <span class="required">*</span></label>
                        <input type="text" id="fullname" name="fullname" value="<?php echo htmlspecialchars($user['fullname']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address <span class="required">*</span></label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        <span class="input-hint">Order confirmation will be sent here</span>
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Phone Number <span class="required">*</span></label>
                        <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>" required placeholder="09123456789">
                        <span class="input-hint">Philippine mobile number (11 digits)</span>
                    </div>
                    
                    <div class="form-group">
                        <label for="address">Street Address <span class="required">*</span></label>
                        <textarea id="address" name="address" rows="3" required placeholder="House/Unit No., Street, Barangay"><?php echo htmlspecialchars($user['address']); ?></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="city">City <span class="required">*</span></label>
                            <input type="text" id="city" name="city" required placeholder="e.g., Manila" value="<?php echo htmlspecialchars($user['city']); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="postal_code">Postal Code <span class="required">*</span></label>
                            <input type="text" id="postal_code" name="postal_code" required placeholder="1000" value="<?php echo htmlspecialchars($user['postal_code']); ?>">
                            <span class="input-hint">4-digit postal code</span>
                        </div>
                    </div>
                    
                    <h2>Payment Method</h2>
                    
                    <div class="form-group">
                        <label for="payment_method">Select Payment Method <span class="required">*</span></label>
                        <select id="payment_method" name="payment_method" required>
                            <option value="">-- Choose payment method --</option>
                            <option value="cash_on_delivery">💵 Cash on Delivery (COD)</option>
                            <option value="gcash">📱 GCash</option>
                        </select>
                    </div>
                    
                    <!-- GCash Instructions -->
                    <div id="gcashInstructions" style="display: none; background: #e8f5e9; padding: 15px; border-radius: 8px; margin-top: 15px; border-left: 4px solid #4caf50;">
                        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                            <i class="fas fa-mobile-alt" style="font-size: 1.5rem; color: #00b4d8;"></i>
                            <strong style="color: #00b4d8;">GCash Payment Instructions:</strong>
                        </div>
                        <ol style="margin-left: 20px; color: #555; font-size: 0.85rem; line-height: 1.6;">
                            <li>You will be redirected to the GCash payment page after placing your order</li>
                            <li>Send the exact amount to our GCash number: <strong style="color: #00b4d8;">09123456789</strong></li>
                            <li>Use your Order ID as reference</li>
                            <li>Upload or enter the reference number to confirm your payment</li>
                            <li>Your order will be processed once payment is confirmed</li>
                        </ol>
                    </div>
                    
                    <button type="submit" class="btn-submit" id="submitBtn">Place Order</button>
                </div>
            </div>
        </form>
        
        <a href="products.php" class="back-link">← Continue Shopping</a>
    </div>

    <script>
    const basePrice = <?php echo $product['price']; ?>;
    let currentSizeExtra = 0;
    let currentMaxStock = <?php echo isset($sizeStock['M']) ? $sizeStock['M'] : 20; ?>;
    
    // DOM elements
    const quantityInput = document.getElementById('quantity');
    const decreaseBtn = document.getElementById('decreaseBtn');
    const increaseBtn = document.getElementById('increaseBtn');
    const totalAmountSpan = document.getElementById('totalAmount');
    const unitPriceDisplay = document.getElementById('unitPriceDisplay');
    const hiddenQuantity = document.getElementById('hiddenQuantity');
    const paymentMethodSelect = document.getElementById('payment_method');
    const gcashInstructions = document.getElementById('gcashInstructions');
    const submitBtn = document.getElementById('submitBtn');
    
    // Show/hide GCash instructions
    if (paymentMethodSelect) {
        paymentMethodSelect.addEventListener('change', function() {
            gcashInstructions.style.display = this.value === 'gcash' ? 'block' : 'none';
        });
    }
    
    // Size selection event
    document.querySelectorAll('input[name="size"]').forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.checked) {
                currentSizeExtra = parseFloat(this.dataset.extra) || 0;
                currentMaxStock = parseInt(this.dataset.stock) || 0;
                
                quantityInput.max = currentMaxStock;
                let currentQty = parseInt(quantityInput.value);
                if (currentQty > currentMaxStock) {
                    quantityInput.value = currentMaxStock;
                }
                
                const sizeExtraRow = document.getElementById('sizeExtraRow');
                const sizeExtraSpan = document.getElementById('sizeExtra');
                
                if (currentSizeExtra > 0) {
                    sizeExtraRow.style.display = 'flex';
                    sizeExtraSpan.textContent = '₱' + currentSizeExtra.toFixed(2);
                } else {
                    sizeExtraRow.style.display = 'none';
                }
                
                updateTotal();
            }
        });
    });
    
    function updateTotal() {
        const quantity = parseInt(quantityInput.value) || 1;
        const unitPrice = basePrice + currentSizeExtra;
        const total = unitPrice * quantity;
        
        unitPriceDisplay.textContent = '₱' + unitPrice.toFixed(2);
        totalAmountSpan.textContent = '₱' + total.toFixed(2);
        hiddenQuantity.value = quantity;
    }
    
    function increaseQuantity() {
        let value = parseInt(quantityInput.value);
        if (value < currentMaxStock && value < 99) {
            quantityInput.value = value + 1;
            updateTotal();
        } else if (value >= currentMaxStock) {
            alert('Only ' + currentMaxStock + ' items available in this size.');
        }
    }
    
    function decreaseQuantity() {
        let value = parseInt(quantityInput.value);
        if (value > 1) {
            quantityInput.value = value - 1;
            updateTotal();
        }
    }
    
    if (decreaseBtn) decreaseBtn.addEventListener('click', decreaseQuantity);
    if (increaseBtn) increaseBtn.addEventListener('click', increaseQuantity);
    if (quantityInput) quantityInput.addEventListener('change', updateTotal);
    
    // Form validation
    const checkoutForm = document.getElementById('checkoutForm');
    
    checkoutForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const fullname = document.getElementById('fullname').value.trim();
        const email = document.getElementById('email').value.trim();
        const phone = document.getElementById('phone').value.trim();
        const address = document.getElementById('address').value.trim();
        const city = document.getElementById('city').value.trim();
        const postal = document.getElementById('postal_code').value.trim();
        const payment = document.getElementById('payment_method').value;
        const quantity = parseInt(quantityInput.value);
        
        let sizeSelected = false;
        let selectedSize = null;
        document.querySelectorAll('input[name="size"]').forEach(radio => {
            if (radio.checked) {
                sizeSelected = true;
                selectedSize = radio.value;
            }
        });
        
        if (!sizeSelected) {
            alert('Please select a size');
            return;
        }
        
        if (!fullname || !email || !phone || !address || !city || !postal || !payment) {
            alert('Please fill in all required fields');
            return;
        }
        
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
            alert('Please enter a valid email address');
            return;
        }
        
        const phoneClean = phone.replace(/\D/g, '');
        const phoneRegex = /^(09|\+639|9)\d{9}$/;
        if (!phoneRegex.test(phoneClean) || phoneClean.length !== 11) {
            alert('Please enter a valid Philippine mobile number (e.g., 09123456789)');
            return;
        }
        
        const postalRegex = /^\d{4}$/;
        if (!postalRegex.test(postal)) {
            alert('Please enter a valid 4-digit postal code');
            return;
        }
        
        if (quantity < 1 || quantity > currentMaxStock) {
            alert('Please enter a valid quantity between 1 and ' + currentMaxStock);
            return;
        }
        
        let confirmMessage = 'Confirm your order?';
        if (payment === 'gcash') {
            confirmMessage = 'You will be redirected to GCash payment page to complete your transaction. Click OK to continue.';
        }
        
        if (confirm(confirmMessage)) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = 'Processing...';
            checkoutForm.submit();
        }
    });
    
    // Auto-format fullname (capitalize first letter of each word)
    const fullnameField = document.getElementById('fullname');
    if (fullnameField) {
        fullnameField.addEventListener('blur', function() {
            let words = this.value.split(' ');
            for (let i = 0; i < words.length; i++) {
                if (words[i].length > 0) {
                    words[i] = words[i].charAt(0).toUpperCase() + words[i].slice(1).toLowerCase();
                }
            }
            this.value = words.join(' ');
        });
    }
    
    // Phone number formatting (only numbers)
    const phoneField = document.getElementById('phone');
    if (phoneField) {
        phoneField.addEventListener('input', function() {
            let phone = this.value.replace(/\D/g, '');
            if (phone.length > 11) phone = phone.slice(0, 11);
            this.value = phone;
        });
    }
    
    // Postal code formatting (only numbers)
    const postalField = document.getElementById('postal_code');
    if (postalField) {
        postalField.addEventListener('input', function() {
            let postal = this.value.replace(/\D/g, '');
            if (postal.length > 4) postal = postal.slice(0, 4);
            this.value = postal;
        });
    }
    
    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        const defaultSize = document.getElementById('size_M');
        if (defaultSize && !defaultSize.disabled) {
            defaultSize.checked = true;
            currentSizeExtra = parseFloat(defaultSize.dataset.extra) || 0;
            currentMaxStock = parseInt(defaultSize.dataset.stock) || 0;
            quantityInput.max = currentMaxStock;
            
            if (currentSizeExtra > 0) {
                document.getElementById('sizeExtraRow').style.display = 'flex';
                document.getElementById('sizeExtra').textContent = '₱' + currentSizeExtra.toFixed(2);
            }
        }
        updateTotal();
    });
    </script>
</body>
</html>