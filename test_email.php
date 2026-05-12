<?php
// Test email script for PHPMailer configuration – simulates account approval email
require_once 'includes/db_connection.php';
require_once 'includes/email_config.php';

// Function to send an account approval email (test version)
function sendApprovalTestEmail($testEmail, $username = 'Test User') {
    require_once 'vendor/phpmailer/src/Exception.php';
    require_once 'vendor/phpmailer/src/PHPMailer.php';
    require_once 'vendor/phpmailer/src/SMTP.php';

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;

        // Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($testEmail, $username);

        // Approval message content
        $loginLink = "http://" . $_SERVER['HTTP_HOST'] . "/moneywaste/login.php";
        
        $mail->isHTML(true);
        $mail->Subject = '🎉 Your MoneyWaste account has been APPROVED!';
        $mail->Body = "
            <!DOCTYPE html>
            <html>
            <head><meta charset='UTF-8'></head>
            <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
                <div style='max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #eee; border-radius: 10px;'>
                    <div style='text-align: center; border-bottom: 2px solid #ffc107; padding-bottom: 15px; margin-bottom: 20px;'>
                        <h1 style='color: #000; letter-spacing: 2px;'>MONEYWASTE</h1>
                        <p style='color: #666;'>Minimal Streetwear</p>
                    </div>
                    
                    <h2 style='color: #2e7d32;'>✅ Your account has been approved!</h2>
                    
                    <p>Hello <strong>" . htmlspecialchars($username) . "</strong>,</p>
                    
                    <p>Great news! An administrator has reviewed and <strong>approved</strong> your registration on <strong>MoneyWaste</strong>.</p>
                    
                    <p>You can now log in and start shopping for our curated collection of minimalist streetwear.</p>
                    
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='$loginLink' style='background-color: #000; color: white; padding: 12px 24px; text-decoration: none; border-radius: 30px; font-weight: bold;'>🔗 LOGIN NOW</a>
                    </div>
                    
                    <p>If the button doesn't work, copy and paste this link into your browser:<br>
                    <code style='background: #f4f4f4; padding: 3px 6px;'>$loginLink</code></p>
                    
                    <hr style='border: none; border-top: 1px solid #eee; margin: 20px 0;'>
                    
                    <p style='font-size: 12px; color: #999;'>This is an automated message from MoneyWaste. Please do not reply directly to this email.</p>
                    <p style='font-size: 12px; color: #999;'>© " . date('Y') . " MoneyWaste – All rights reserved.</p>
                </div>
            </body>
            </html>
        ";
        
        $mail->AltBody = "Your MoneyWaste account has been APPROVED!\n\n"
                       . "Hello $username,\n\n"
                       . "An administrator has approved your registration. You can now log in at: $loginLink\n\n"
                       . "Thank you for joining MoneyWaste.\n\n"
                       . "– MoneyWaste Team";

        $mail->send();
        return ['success' => true, 'message' => "✅ Account approval email sent successfully to $testEmail"];
    } catch (Exception $e) {
        return ['success' => false, 'message' => "❌ Failed to send email. Error: {$mail->ErrorInfo}"];
    }
}

// Handle test request
$message = '';
$messageType = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['test_email'])) {
    $testEmail = trim($_POST['test_email']);
    $username = trim($_POST['username']) ?: 'Test User';
    
    if (filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
        $result = sendApprovalTestEmail($testEmail, $username);
        $message = $result['message'];
        $messageType = $result['success'] ? 'success' : 'error';
    } else {
        $message = 'Please enter a valid email address.';
        $messageType = 'error';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Test Approval Email - MoneyWaste</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }
        .test-container {
            max-width: 550px;
            width: 100%;
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .test-header {
            background: #000;
            color: white;
            padding: 25px 30px;
            text-align: center;
        }
        .test-header h1 {
            font-size: 1.8rem;
            letter-spacing: 3px;
            font-weight: 500;
            margin-bottom: 5px;
        }
        .test-header p {
            font-size: 0.85rem;
            opacity: 0.8;
        }
        .test-body {
            padding: 30px;
        }
        .info-box {
            background: #f8f9fa;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin-bottom: 25px;
            border-radius: 8px;
            font-size: 0.9rem;
        }
        .info-box i {
            color: #ffc107;
            margin-right: 8px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #333;
        }
        input[type="email"], input[type="text"] {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 10px;
            font-size: 0.95rem;
            transition: all 0.2s;
        }
        input:focus {
            outline: none;
            border-color: #000;
            box-shadow: 0 0 0 2px rgba(0,0,0,0.1);
        }
        .btn-send {
            background: #000;
            color: white;
            border: none;
            padding: 12px 20px;
            width: 100%;
            border-radius: 30px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-send:hover {
            background: #333;
        }
        .message {
            padding: 12px 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }
        .success {
            background: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #c8e6c9;
        }
        .error {
            background: #ffebee;
            color: #c62828;
            border: 1px solid #ffcdd2;
        }
        .config-preview {
            background: #fafafa;
            padding: 15px;
            border-radius: 12px;
            margin-top: 25px;
            font-size: 0.8rem;
            border: 1px solid #f0f0f0;
        }
        .config-preview h4 {
            margin-bottom: 10px;
            font-weight: 600;
        }
        .config-preview p {
            margin: 6px 0;
            color: #666;
            word-break: break-all;
        }
        .back-link {
            display: inline-block;
            margin-top: 20px;
            text-align: center;
            width: 100%;
            color: #666;
            text-decoration: none;
            font-size: 0.85rem;
        }
        .back-link:hover {
            color: #000;
        }
        hr {
            margin: 20px 0;
            border: none;
            border-top: 1px solid #eee;
        }
    </style>
</head>
<body>
    <div class="test-container">
        <div class="test-header">
            <h1>MONEYWASTE</h1>
            <p>Test Account Approval Email</p>
        </div>
        <div class="test-body">
            <div class="info-box">
                <i>📧</i> This will send a realistic <strong>"Account Approved"</strong> email to the address you provide, just like when an admin approves a user registration.
            </div>

            <?php if ($message): ?>
                <div class="message <?php echo $messageType; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label for="test_email">Recipient Email *</label>
                    <input type="email" id="test_email" name="test_email" required placeholder="Enter your Gmail address">
                </div>
                <div class="form-group">
                    <label for="username">User Name (optional)</label>
                    <input type="text" id="username" name="username" placeholder="Default: Test User">
                </div>
                <button type="submit" class="btn-send">📨 Send Approval Test Email</button>
            </form>

            <hr>

            <div class="config-preview">
                <h4>📬 Current SMTP Configuration</h4>
                <p><strong>SMTP Host:</strong> <?php echo SMTP_HOST; ?></p>
                <p><strong>Username:</strong> <?php echo SMTP_USERNAME; ?></p>
                <p><strong>Port:</strong> <?php echo SMTP_PORT; ?></p>
                <p><strong>From Email:</strong> <?php echo SMTP_FROM_EMAIL; ?></p>
                <p><strong>From Name:</strong> <?php echo SMTP_FROM_NAME; ?></p>
            </div>

            <a href="admin/dashboard.php" class="back-link">← Back to Admin Dashboard</a>
        </div>
    </div>
</body>
</html>