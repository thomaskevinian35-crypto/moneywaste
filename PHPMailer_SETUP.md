# PHPMailer Setup for MoneyWaste

This document explains how to set up email notifications for user account approvals.

## Gmail Configuration

To send emails via Gmail, you need to configure your Gmail account:

### Step 1: Enable 2-Factor Authentication
1. Go to your Google Account settings
2. Navigate to Security > 2-Step Verification
3. Enable 2-Step Verification if not already enabled

### Step 2: Generate App Password
1. Go to your Google Account settings
2. Navigate to Security > 2-Step Verification > App passwords
3. Select "Mail" and "Other (custom name)"
4. Enter "MoneyWaste" as the custom name
5. Click "Generate"
6. Copy the 16-character password (ignore spaces)

### Step 3: Configure Email Settings
1. Open `includes/email_config.php`
2. Replace `'your-email@gmail.com'` with your actual Gmail address
3. Replace `'your-app-password'` with the app password you generated
4. Save the file

## Testing

After configuration:
1. Register a new user account (status will be 'pending')
2. Login as admin
3. Go to the users management page
4. Approve the user
5. The user should receive an email notification

## Troubleshooting

- **Emails not sending**: Check your Gmail credentials and app password
- **"Less secure app" error**: Make sure you're using an app password, not your regular password
- **SMTP errors**: Verify your Gmail account has 2FA enabled and app password is correct

## Security Notes

- Never commit your actual email credentials to version control
- Consider using environment variables for production deployments
- The app password is specific to this application and can be revoked if needed