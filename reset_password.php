<?php
session_start();
require 'db.php';

$error = $success = '';
$token = $_GET['token'] ?? '';
$valid_token = false;
$user_id = null;

// Validate token
if ($token) {
    $stmt = $conn->prepare("SELECT id, reset_expires FROM users WHERE reset_token=? LIMIT 1");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $stmt->store_result();
    $stmt->bind_result($user_id, $reset_expires);
    
    if ($stmt->fetch()) {
        // Check if token hasn't expired
        if (strtotime($reset_expires) > time()) {
            $valid_token = true;
        } else {
            $error = "This reset link has expired. Please request a new one.";
        }
    } else {
        $error = "Invalid reset link.";
    }
    $stmt->close();
} else {
    $error = "No reset token provided.";
}

// Process password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid_token) {
    $new_password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($new_password) || empty($confirm_password)) {
        $error = "Please fill in all fields.";
    } elseif (strlen($new_password) < 8) {
        $error = "Password must be at least 8 characters long.";
    } elseif ($new_password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        // Hash the new password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        // Update password and clear reset token
        $stmt = $conn->prepare("UPDATE users SET password=?, reset_token=NULL, reset_expires=NULL WHERE id=?");
        $stmt->bind_param("si", $hashed_password, $user_id);
        
        if ($stmt->execute()) {
            $success = "Your password has been successfully reset! You can now log in with your new password.";
            $valid_token = false; // Prevent form from showing again
        } else {
            $error = "An error occurred. Please try again.";
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
<title>Reset Password - CyclePoint</title>
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

.toggle-password {
    position: absolute;
    top: 50%;
    right: 15px;
    transform: translateY(-50%);
    cursor: pointer;
    width: 22px;
    height: 22px;
    fill: #999;
    transition: fill 0.3s;
}

.toggle-password:hover {
    fill: #0033a0;
}

.password-strength {
    margin-top: -15px;
    margin-bottom: 15px;
    text-align: left;
    padding: 0 5px;
}

.strength-bar {
    height: 4px;
    background: #e0e0e0;
    border-radius: 2px;
    margin-bottom: 5px;
    overflow: hidden;
}

.strength-fill {
    height: 100%;
    width: 0%;
    transition: all 0.3s ease;
    border-radius: 2px;
}

.strength-text {
    font-size: 12px;
    color: #777;
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

button:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none !important;
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

.password-requirements {
    background: #e7f3ff;
    border: 1px solid #b3d9ff;
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 25px;
    text-align: left;
}

.password-requirements h4 {
    font-size: 14px;
    color: #0066cc;
    margin-bottom: 10px;
}

.password-requirements ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.password-requirements li {
    font-size: 13px;
    color: #0066cc;
    padding: 4px 0;
    padding-left: 24px;
    position: relative;
}

.password-requirements li::before {
    content: '•';
    position: absolute;
    left: 8px;
}

.password-requirements li.valid {
    color: #28a745;
}

.password-requirements li.valid::before {
    content: '✓';
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
    <div class="logo-subtitle">Reset Your Password</div>
    
    <?php if(!empty($error)) echo "<p class='error-msg'>$error</p>"; ?>
    <?php if(!empty($success)) echo "<p class='success-msg'>$success</p>"; ?>

    <?php if($valid_token && empty($success)): ?>
        <p class="description">Enter your new password below. Make sure it's strong and secure.</p>

        <div class="password-requirements">
            <h4>Password Requirements:</h4>
            <ul id="requirements">
                <li id="req-length">At least 8 characters</li>
                <li id="req-uppercase">One uppercase letter</li>
                <li id="req-lowercase">One lowercase letter</li>
                <li id="req-number">One number</li>
            </ul>
        </div>

        <form method="post" id="resetForm">
            <div class="input-wrapper">
                <input type="password" name="password" id="password" placeholder="New password" required>
                <svg viewBox="0 0 24 24">
                    <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/>
                </svg>
                <svg class="toggle-password" id="togglePassword" viewBox="0 0 24 24">
                    <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>
                </svg>
            </div>

            <div class="password-strength">
                <div class="strength-bar">
                    <div class="strength-fill" id="strengthBar"></div>
                </div>
                <div class="strength-text" id="strengthText"></div>
            </div>

            <div class="input-wrapper">
                <input type="password" name="confirm_password" id="confirmPassword" placeholder="Confirm new password" required>
                <svg viewBox="0 0 24 24">
                    <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/>
                </svg>
                <svg class="toggle-password" id="toggleConfirm" viewBox="0 0 24 24">
                    <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>
                </svg>
            </div>

            <button type="submit" id="submitBtn">Reset Password</button>
        </form>
    <?php endif; ?>

    <?php if(!empty($success)): ?>
        <a href="login.php" style="display: inline-block; padding: 12px 30px; background: linear-gradient(135deg, #0033a0 0%, #0055cc 100%); color: white; text-decoration: none; border-radius: 12px; font-weight: 600; margin-top: 10px; transition: all 0.3s;">
            Go to Login
        </a>
    <?php endif; ?>

    <div class="back-login">
        <a href="login.php">Back to Login</a>
    </div>
</div>

<script>
// Toggle password visibility
document.getElementById('togglePassword')?.addEventListener('click', function() {
    const passwordInput = document.getElementById('password');
    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
    passwordInput.setAttribute('type', type);
    
    this.innerHTML = type === 'password' 
        ? '<path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>'
        : '<path d="M12 7c2.76 0 5 2.24 5 5 0 .65-.13 1.26-.36 1.83l2.92 2.92c1.51-1.26 2.7-2.89 3.43-4.75-1.73-4.39-6-7.5-11-7.5-1.4 0-2.74.25-3.98.7l2.16 2.16C10.74 7.13 11.35 7 12 7zM2 4.27l2.28 2.28.46.46C3.08 8.3 1.78 10.02 1 12c1.73 4.39 6 7.5 11 7.5 1.55 0 3.03-.3 4.38-.84l.42.42L19.73 22 21 20.73 3.27 3 2 4.27zM7.53 9.8l1.55 1.55c-.05.21-.08.43-.08.65 0 1.66 1.34 3 3 3 .22 0 .44-.03.65-.08l1.55 1.55c-.67.33-1.41.53-2.2.53-2.76 0-5-2.24-5-5 0-.79.2-1.53.53-2.2zm4.31-.78l3.15 3.15.02-.16c0-1.66-1.34-3-3-3l-.17.01z"/>';
});

document.getElementById('toggleConfirm')?.addEventListener('click', function() {
    const confirmInput = document.getElementById('confirmPassword');
    const type = confirmInput.getAttribute('type') === 'password' ? 'text' : 'password';
    confirmInput.setAttribute('type', type);
    
    this.innerHTML = type === 'password' 
        ? '<path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>'
        : '<path d="M12 7c2.76 0 5 2.24 5 5 0 .65-.13 1.26-.36 1.83l2.92 2.92c1.51-1.26 2.7-2.89 3.43-4.75-1.73-4.39-6-7.5-11-7.5-1.4 0-2.74.25-3.98.7l2.16 2.16C10.74 7.13 11.35 7 12 7zM2 4.27l2.28 2.28.46.46C3.08 8.3 1.78 10.02 1 12c1.73 4.39 6 7.5 11 7.5 1.55 0 3.03-.3 4.38-.84l.42.42L19.73 22 21 20.73 3.27 3 2 4.27zM7.53 9.8l1.55 1.55c-.05.21-.08.43-.08.65 0 1.66 1.34 3 3 3 .22 0 .44-.03.65-.08l1.55 1.55c-.67.33-1.41.53-2.2.53-2.76 0-5-2.24-5-5 0-.79.2-1.53.53-2.2zm4.31-.78l3.15 3.15.02-.16c0-1.66-1.34-3-3-3l-.17.01z"/>';
});

// Password strength checker
const passwordInput = document.getElementById('password');
if (passwordInput) {
    passwordInput.addEventListener('input', function() {
        const password = this.value;
        const strengthBar = document.getElementById('strengthBar');
        const strengthText = document.getElementById('strengthText');
        
        let strength = 0;
        const checks = {
            length: password.length >= 8,
            uppercase: /[A-Z]/.test(password),
            lowercase: /[a-z]/.test(password),
            number: /[0-9]/.test(password)
        };
        
        // Update requirements list
        document.getElementById('req-length').classList.toggle('valid', checks.length);
        document.getElementById('req-uppercase').classList.toggle('valid', checks.uppercase);
        document.getElementById('req-lowercase').classList.toggle('valid', checks.lowercase);
        document.getElementById('req-number').classList.toggle('valid', checks.number);
        
        // Calculate strength
        Object.values(checks).forEach(check => { if(check) strength++; });
        
        const colors = ['#dc3545', '#fd7e14', '#ffc107', '#28a745'];
        const texts = ['Weak', 'Fair', 'Good', 'Strong'];
        const widths = ['25%', '50%', '75%', '100%'];
        
        if (password.length === 0) {
            strengthBar.style.width = '0%';
            strengthText.textContent = '';
        } else {
            strengthBar.style.width = widths[strength - 1] || '25%';
            strengthBar.style.background = colors[strength - 1] || colors[0];
            strengthText.textContent = 'Password strength: ' + (texts[strength - 1] || texts[0]);
            strengthText.style.color = colors[strength - 1] || colors[0];
        }
    });
}

// Form validation
const form = document.getElementById('resetForm');
if (form) {
    form.addEventListener('submit', function(e) {
        const password = document.getElementById('password').value;
        const confirmPassword = document.getElementById('confirmPassword').value;
        
        if (password !== confirmPassword) {
            e.preventDefault();
            alert('Passwords do not match!');
            return false;
        }
        
        if (password.length < 8) {
            e.preventDefault();
            alert('Password must be at least 8 characters long!');
            return false;
        }
    });
}
</script>

<script src="assets/js/page-transitions.js"></script>
<script src="assets/js/notification-badges.js"></script>

</body>
</html>