<?php
session_start();
require 'db.php';

require 'vendor/autoload.php';

// Import PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;



$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (!$email) {
        $error = "Please enter your registered email.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } else {
        // Check if email exists
        $stmt = $conn->prepare("SELECT id, name FROM users WHERE email=? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        $stmt->bind_result($user_id, $user_name);

        if ($stmt->fetch()) {
            // Generate a secure token
            $token = bin2hex(random_bytes(32)); // 64 chars for better security
            $expires = date("Y-m-d H:i:s", strtotime('+1 hour'));

            // Store token in DB
            $stmt_update = $conn->prepare("UPDATE users SET reset_token=?, reset_expires=? WHERE id=?");
            $stmt_update->bind_param("ssi", $token, $expires, $user_id);
            $stmt_update->execute();
            $stmt_update->close();

            // Create reset link - FIXED: Now includes the correct folder path
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
            $host = $_SERVER['HTTP_HOST'];
            $current_dir = dirname($_SERVER['PHP_SELF']);
            $reset_link = "$protocol://$host$current_dir/reset_password.php?token=$token";

            // Send email using PHPMailer with Gmail SMTP
            $mail = new PHPMailer(true);

            try {
                //Server settings
                $mail->SMTPDebug = 0; // Set to 2 for detailed debug output
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'cyclepoint.08@gmail.com';
                $mail->Password   = 'avqi auym mtce nvhw';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;

                //Recipients
                $mail->setFrom('cyclepoint.08@gmail.com', 'CyclePoint');
                $mail->addAddress($email, $user_name);
                $mail->addReplyTo('cyclepoint.08@gmail.com', 'CyclePoint Support');

                //Content
                $mail->isHTML(true);
                $mail->Subject = 'Password Reset Request - CyclePoint';
                
                // HTML email body
                $mail->Body = "
                <!DOCTYPE html>
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background: linear-gradient(135deg, #0033a0 0%, #0055cc 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                        .logo { font-family: 'Pacifico', cursive; font-size: 36px; margin: 0; }
                        .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                        .button { display: inline-block; padding: 15px 30px; background: #0033a0; color: white; text-decoration: none; border-radius: 8px; font-weight: bold; margin: 20px 0; }
                        .button:hover { background: #002080; }
                        .footer { text-align: center; margin-top: 30px; color: #777; font-size: 12px; }
                        .warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 5px; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h1 class='logo'>CyclePoint</h1>
                            <p>Password Reset Request</p>
                        </div>
                        <div class='content'>
                            <h2>Hello, $user_name!</h2>
                            <p>We received a request to reset your password for your CyclePoint account.</p>
                            <p>Click the button below to reset your password:</p>
                            <center>
                                <a href='$reset_link' class='button'>Reset Password</a>
                            </center>
                            <p>Or copy and paste this link into your browser:</p>
                            <p style='word-break: break-all; background: white; padding: 10px; border-radius: 5px;'>$reset_link</p>
                            
                            <div class='warning'>
                                <strong>⚠️ Important:</strong>
                                <ul>
                                    <li>This link will expire in <strong>1 hour</strong> for security reasons</li>
                                    <li>If you didn't request this, please ignore this email</li>
                                    <li>Never share this link with anyone</li>
                                </ul>
                            </div>
                            
                            <p>If you have any questions, contact us at cyclepoint08.@gmail.com</p>
                        </div>
                        <div class='footer'>
                            <p>© " . date('Y') . " CyclePoint - All rights reserved</p>
                            <p>This is an automated email, please do not reply</p>
                        </div>
                    </div>
                </body>
                </html>
                ";

                // Plain text version for email clients that don't support HTML
                $mail->AltBody = "Hello $user_name,\n\n"
                    . "We received a request to reset your password for your CyclePoint account.\n\n"
                    . "Click this link to reset your password:\n$reset_link\n\n"
                    . "This link will expire in 1 hour for security reasons.\n\n"
                    . "If you didn't request this, please ignore this email.\n\n"
                    . "Best regards,\nCyclePoint Team";

                $mail->send();
                $success = "✅ Password recovery email has been sent successfully! Please check your inbox and spam folder.";
                
            } catch (Exception $e) {
                // Log the actual error for debugging
                error_log("PHPMailer Error: {$mail->ErrorInfo}");
                
                // Show test link with error details (for development only)
                $error_details = $mail->ErrorInfo;
                $success = "⚠️ Email sending failed, but here's your test link:<br><br>"
                    . "<strong>Error:</strong> " . htmlspecialchars($error_details) . "<br><br>"
                    . "<a href='$reset_link' style='display: inline-block; margin-top: 10px; padding: 10px 20px; background: #28a745; color: white; text-decoration: none; border-radius: 8px; font-weight: 600;'>Reset Password (Test Link)</a>";
            }
        } else {
            // For security, don't reveal if email exists or not
            $success = "If an account exists with that email, you will receive password reset instructions shortly.";
        }

        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Password Recovery - CyclePoint</title>
<link href="https://fonts.googleapis.com/css2?family=Pacifico&family=Inter:wght@400;500;700&display=swap" rel="stylesheet">
<style>
* { 
    box-sizing: border-box; 
    margin: 0; 
    padding: 0; 
}

body { 
    font-family: 'Inter', sans-serif; 
    background: linear-gradient(135deg, #f4f6fa 0%, #e9ecf5 100%);
    display: flex; 
    justify-content: center; 
    align-items: center; 
    min-height: 100vh; 
    padding: 20px;
    position: relative;
    overflow: hidden;
}

/* Animated background elements */
body::before {
    content: '';
    position: fixed;
    top: -50%;
    right: -20%;
    width: 600px;
    height: 600px;
    background: radial-gradient(circle, rgba(0, 51, 160, 0.08) 0%, transparent 70%);
    border-radius: 50%;
    animation: float 15s ease-in-out infinite;
}

body::after {
    content: '';
    position: fixed;
    bottom: -30%;
    left: -10%;
    width: 500px;
    height: 500px;
    background: radial-gradient(circle, rgba(0, 150, 255, 0.06) 0%, transparent 70%);
    border-radius: 50%;
    animation: float 20s ease-in-out infinite reverse;
}

@keyframes float {
    0%, 100% { transform: translateY(0) rotate(0deg); }
    50% { transform: translateY(-30px) rotate(5deg); }
}

.container { 
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    padding: 50px 40px;
    border-radius: 20px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
    width: 100%;
    max-width: 480px;
    text-align: center;
    position: relative;
    z-index: 1;
    animation: slideUp 0.6s ease-out;
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

.logo-text { 
    font-family: 'Pacifico', cursive; 
    font-size: 48px; 
    color: #0033a0; 
    margin-bottom: 8px;
    text-shadow: 2px 2px 4px rgba(0, 51, 160, 0.1);
}

.logo-subtitle { 
    font-size: 18px; 
    color: #555; 
    margin-bottom: 15px; 
    font-weight: 500; 
}

.description {
    font-size: 14px;
    color: #777;
    margin-bottom: 30px;
    line-height: 1.6;
}

form {
    width: 100%;
}

.input-wrapper {
    position: relative;
    width: 100%;
    margin-bottom: 20px;
}

.input-wrapper input {
    width: 100%;
    padding: 14px 45px 14px 50px;
    border-radius: 12px;
    border: 2px solid #e0e0e0;
    font-size: 15px;
    background: #f9f9f9;
    transition: all 0.3s ease;
    font-family: 'Inter', sans-serif;
}

.input-wrapper input:focus {
    outline: none;
    border-color: #0033a0;
    background: #fff;
    box-shadow: 0 0 0 4px rgba(0, 51, 160, 0.1);
}

.input-wrapper svg {
    position: absolute;
    top: 50%;
    left: 15px;
    transform: translateY(-50%);
    width: 22px;
    height: 22px;
    fill: #777;
    pointer-events: none;
    transition: fill 0.3s;
}

.input-wrapper input:focus + svg {
    fill: #0033a0;
}

button { 
    width: 100%;
    padding: 14px;
    background: linear-gradient(135deg, #0033a0 0%, #0055cc 100%);
    color: #fff;
    border: none;
    border-radius: 12px;
    cursor: pointer;
    font-size: 16px;
    font-weight: 600;
    transition: all 0.3s ease;
    margin-bottom: 20px;
    box-shadow: 0 4px 15px rgba(0, 51, 160, 0.3);
    letter-spacing: 0.5px;
}

button:hover { 
    background: linear-gradient(135deg, #002080 0%, #0044aa 100%);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0, 51, 160, 0.4);
}

button:active {
    transform: translateY(0);
}

.error-msg { 
    color: #dc3545;
    margin-bottom: 20px;
    padding: 12px 16px;
    background: #fee;
    border-radius: 10px;
    border-left: 4px solid #dc3545;
    font-size: 14px;
    animation: shake 0.4s ease;
    display: flex;
    align-items: center;
    gap: 10px;
}

.error-msg::before {
    content: '⚠️';
    font-size: 20px;
}

@keyframes shake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-10px); }
    75% { transform: translateX(10px); }
}

.success-msg { 
    color: #28a745;
    margin-bottom: 20px;
    padding: 16px;
    background: #d4edda;
    border-radius: 10px;
    border-left: 4px solid #28a745;
    font-size: 14px;
    line-height: 1.6;
    animation: slideDown 0.5s ease;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.success-msg::before {
    content: '✅';
    font-size: 24px;
    display: block;
    margin-bottom: 10px;
}

.info-box {
    background: #e7f3ff;
    border: 1px solid #b3d9ff;
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 25px;
    display: flex;
    align-items: start;
    gap: 12px;
    text-align: left;
}

.info-box svg {
    width: 24px;
    height: 24px;
    fill: #0066cc;
    flex-shrink: 0;
    margin-top: 2px;
}

.info-box p {
    font-size: 13px;
    color: #0066cc;
    line-height: 1.6;
    margin: 0;
}

.back-login { 
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #e0e0e0;
    font-size: 14px;
    color: #666;
}

.back-login a { 
    color: #0033a0;
    text-decoration: none;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.3s;
}

.back-login a:hover { 
    text-decoration: underline;
    gap: 10px;
}

.back-login a::before {
    content: '←';
    font-size: 18px;
    transition: transform 0.3s;
}

.back-login a:hover::before {
    transform: translateX(-3px);
}

/* Loading animation */
button.loading {
    pointer-events: none;
    opacity: 0.7;
    position: relative;
}

button.loading::after {
    content: '';
    position: absolute;
    width: 16px;
    height: 16px;
    top: 50%;
    left: 50%;
    margin-left: -8px;
    margin-top: -8px;
    border: 2px solid #ffffff;
    border-radius: 50%;
    border-top-color: transparent;
    animation: spin 0.6s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* Responsive */
@media (max-width: 480px) {
    .container {
        padding: 40px 25px;
    }
    
    .logo-text {
        font-size: 38px;
    }
    
    .logo-subtitle {
        font-size: 16px;
    }
}
</style>
</head>
<body>

<div class="container">
    <div class="logo-text">CyclePoint</div>
    <div class="logo-subtitle">Password Recovery</div>
    <p class="description">Enter your registered email address and we'll send you instructions to reset your password.</p>

    <?php if(!empty($error)) echo "<p class='error-msg'>$error</p>"; ?>
    <?php if(!empty($success)) echo "<p class='success-msg'>$success</p>"; ?>

    <?php if(empty($success)): ?>
    <div class="info-box">
        <svg viewBox="0 0 24 24">
            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
        </svg>
        <p>You'll receive an email with instructions to reset your password. The link will expire in 1 hour for security reasons.</p>
    </div>

    <form method="post" onsubmit="handleSubmit(event)">
        <div class="input-wrapper">
            <input type="email" name="email" placeholder="Enter your registered email" required>
            <svg viewBox="0 0 24 24">
                <path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/>
            </svg>
        </div>
        <button type="submit" id="submitBtn">Send Recovery Email</button>
    </form>
    <?php endif; ?>

    <div class="back-login">
        <a href="login.php">Back to Login</a>
    </div>
</div>

<script>
function handleSubmit(event) {
    const button = document.getElementById('submitBtn');
    button.classList.add('loading');
    button.textContent = 'Sending...';
}
</script>

<script src="assets/js/page-transitions.js"></script>
<script src="assets/js/notification-badges.js"></script>

</body>
</html>