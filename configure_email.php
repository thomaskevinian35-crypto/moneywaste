<?php
// Email configuration setup page
require_once 'includes/db_connection.php';

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $gmail = trim($_POST['gmail']);
    $appPassword = trim($_POST['app_password']);

    if (empty($gmail) || empty($appPassword)) {
        $message = 'Please fill in both fields.';
        $messageType = 'error';
    } elseif (!filter_var($gmail, FILTER_VALIDATE_EMAIL) || !str_ends_with($gmail, '@gmail.com')) {
        $message = 'Please enter a valid Gmail address.';
        $messageType = 'error';
    } elseif (strlen($appPassword) !== 16 || !preg_match('/^[a-zA-Z0-9-]+$/', $appPassword)) {
        $message = 'App password should be 16 characters (letters, numbers, and hyphens only).';
        $messageType = 'error';
    } else {
        // Update the email_config.php file
        $configContent = "<?php
// Email configuration for PHPMailer
// Place this file in includes/ directory

// Gmail SMTP Configuration
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_USERNAME', '$gmail');
define('SMTP_PASSWORD', '$appPassword');
define('SMTP_PORT', 587);
define('SMTP_FROM_EMAIL', '$gmail');
define('SMTP_FROM_NAME', 'MoneyWaste Admin');

// Function to send approval email
function sendApprovalEmail(\$userEmail, \$userFullname) {
    global \$pdo; // Assuming \$pdo is available from db_connection.php

    // Log the attempt
    error_log(\"Attempting to send approval email to: \$userEmail (\$userFullname)\");

    require_once '../vendor/phpmailer/src/Exception.php';
    require_once '../vendor/phpmailer/src/PHPMailer.php';
    require_once '../vendor/phpmailer/src/SMTP.php';

    \$mail = new PHPMailer\\PHPMailer\\PHPMailer(true);

    try {
        // Server settings
        \$mail->isSMTP();
        \$mail->Host = SMTP_HOST;
        \$mail->SMTPAuth = true;
        \$mail->Username = SMTP_USERNAME;
        \$mail->Password = SMTP_PASSWORD;
        \$mail->SMTPSecure = PHPMailer\\PHPMailer\\PHPMailer::ENCRYPTION_STARTTLS;
        \$mail->Port = SMTP_PORT;

        // Recipients
        \$mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        \$mail->addAddress(\$userEmail, \$userFullname);

        // Content
        \$mail->isHTML(true);
        \$mail->Subject = 'Account Approved - MoneyWaste';
        \$mail->Body = \"
            <!DOCTYPE html>
            <html>
            <head><meta charset='UTF-8'></head>
            <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
                <div style='max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #eee; border-radius: 10px;'>
                    <div style='text-align: center; border-bottom: 2px solid #ffc107; padding-bottom: 15px; margin-bottom: 20px;'>
                        <h1 style='color: #000; letter-spacing: 2px;'>MONEYWASTE</h1>
                        <p style='color: #666;'>Minimal Streetwear</p>
                    </div>
                    <h2 style='color: #2e7d32;'>✅ Account Approved!</h2>
                    <p>Hello <strong>{\$userFullname}</strong>,</p>
                    <p>Your account has been approved by an administrator. You can now log in and start shopping.</p>
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='http://localhost/moneywaste/login.php' style='background-color: #000; color: white; padding: 12px 24px; text-decoration: none; border-radius: 30px; font-weight: bold;'>🔗 LOGIN NOW</a>
                    </div>
                    <hr style='border: none; border-top: 1px solid #eee; margin: 20px 0;'>
                    <p style='font-size: 12px; color: #999;'>© \" . date('Y') . \" MoneyWaste – All rights reserved.</p>
                </div>
            </body>
            </html>
        \";
        \$mail->AltBody = \"Your account has been APPROVED!\\n\\nHello {\$userFullname},\\n\\nYour account has been approved by an administrator. You can now log in at: http://localhost/moneywaste/login.php\\n\\nThank you for joining MoneyWaste.\\n\\n– MoneyWaste Team\";

        \$mail->send();
        error_log(\"Approval email sent successfully to: \$userEmail\");
        return true;
    } catch (Exception \$e) {
        error_log(\"Email could not be sent to \$userEmail. Mailer Error: {\$mail->ErrorInfo}\");
        return false;
    }
}
?>";

        if (file_put_contents('includes/email_config.php', $configContent)) {
            $message = '✅ Email configuration saved successfully! You can now test the email system.';
            $messageType = 'success';
        } else {
            $message = '❌ Failed to update configuration file. Please check file permissions.';
            $messageType = 'error';
        }
    }
}

// Read current configuration (if any)
$currentGmail = '';
if (file_exists('includes/email_config.php')) {
    include_once 'includes/email_config.php';
    if (defined('SMTP_USERNAME') && SMTP_USERNAME !== 'your-gmail@gmail.com') {
        $currentGmail = SMTP_USERNAME;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Configure Email - MoneyWaste Admin</title>
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
        .config-container {
            max-width: 650px;
            width: 100%;
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .config-header {
            background: #000;
            color: white;
            padding: 25px 30px;
            text-align: center;
        }
        .config-header h1 {
            font-size: 1.8rem;
            letter-spacing: 3px;
            font-weight: 500;
            margin-bottom: 5px;
        }
        .config-header p {
            font-size: 0.85rem;
            opacity: 0.8;
        }
        .config-body {
            padding: 30px;
        }
        .instructions-box {
            background: #fff8e1;
            border-left: 4px solid #ffc107;
            padding: 18px;
            margin-bottom: 25px;
            border-radius: 12px;
        }
        .instructions-box h3 {
            color: #856404;
            margin-bottom: 12px;
            font-size: 1.1rem;
        }
        .instructions-box ol {
            margin-left: 20px;
            color: #555;
            font-size: 0.9rem;
        }
        .instructions-box li {
            margin: 8px 0;
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
        .form-group {
            margin-bottom: 22px;
        }
        label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #333;
        }
        input {
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
        small {
            display: block;
            margin-top: 6px;
            font-size: 0.75rem;
            color: #888;
        }
        .btn-save {
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
        .btn-save:hover {
            background: #333;
        }
        .info-box {
            background: #f8f9fa;
            padding: 18px;
            border-radius: 12px;
            margin: 25px 0 15px;
            border: 1px solid #f0f0f0;
        }
        .info-box h3 {
            font-size: 1rem;
            margin-bottom: 10px;
        }
        .info-box ul {
            margin-left: 20px;
            color: #666;
            font-size: 0.85rem;
        }
        .info-box li {
            margin: 5px 0;
        }
        .links {
            text-align: center;
            margin-top: 20px;
            font-size: 0.85rem;
        }
        .links a {
            color: #666;
            text-decoration: none;
            margin: 0 8px;
        }
        .links a:hover {
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
    <div class="config-container">
        <div class="config-header">
            <h1>MONEYWASTE</h1>
            <p>Email Configuration</p>
        </div>
        <div class="config-body">
            <div class="instructions-box">
                <h3>⚠️ Before You Start – Get a Gmail App Password</h3>
                <ol>
                    <li>Go to <a href="https://myaccount.google.com/security" target="_blank">Google Account Security</a> and enable <strong>2-Step Verification</strong>.</li>
                    <li>Once enabled, go to <strong>App passwords</strong>.</li>
                    <li>Select <strong>Mail</strong> → <strong>Other (custom name)</strong> → Enter "MoneyWaste".</li>
                    <li>Click <strong>Generate</strong> and copy the <strong>16-character password</strong> (ignore spaces).</li>
                </ol>
            </div>

            <?php if ($message): ?>
                <div class="message <?php echo $messageType; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label for="gmail">Gmail Address</label>
                    <input type="email" id="gmail" name="gmail" required
                           placeholder="your-email@gmail.com"
                           value="<?php echo htmlspecialchars($currentGmail); ?>">
                    <small>This email will be used to send approval notifications.</small>
                </div>

                <div class="form-group">
                    <label for="app_password">App Password (16 characters)</label>
                    <input type="password" id="app_password" name="app_password" required
                           placeholder="abcd-efgh-ijkl-mnop"
                           pattern="[a-zA-Z0-9-]{16}"
                           maxlength="16">
                    <small>Paste the 16‑character app password you generated (hyphens allowed).</small>
                </div>

                <button type="submit" class="btn-save">💾 Save Configuration</button>
            </form>

            <div class="info-box">
                <h3>📬 What happens after saving?</h3>
                <ul>
                    <li>Your Gmail credentials are stored securely in <code>includes/email_config.php</code>.</li>
                    <li>When you approve a user account, they will receive a professional "Account Approved" email.</li>
                    <li>You can test the setup using the <a href="test_email.php"><strong>Test Email Page</strong></a>.</li>
                </ul>
            </div>

            <hr>

            <div class="links">
                <a href="admin/dashboard.php">← Back to Dashboard</a> |
                <a href="test_email.php">📧 Test Email</a>
            </div>
        </div>
    </div>
</body>
</html>