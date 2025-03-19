<?php
session_start();
require_once 'config.php';

// CSRF token functions
function generateCsrfToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND is_active = TRUE");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        
        $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $stmt->execute([$user['id']]);
        
        header("Location: index.php");
        exit;
    } else {
        // If user is not found or password doesn't match, check if the user exists but is inactive
        $stmt = $pdo->prepare("SELECT is_active FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user_check = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user_check && $user_check['is_active'] === false) {
            $error = "Account is inactive. Please contact an administrator.";
        } else {
            $error = "Invalid credentials";
        }
    }
}

// Handle forgot password request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['forgot_password'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $forgot_error = "Invalid CSRF token";
    } else {
    	    $email = $_POST['email'] ?? '';
    }
}

$csrf_token = generateCsrfToken();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login - School Management</title>
    <link rel="stylesheet" href="includes/style.css">
</head>
<body class="login-page">
    <div class="container login-container">
        <h1 class="login-title">School Management Login</h1>
        
        <!-- Login Form -->
        <?php if (isset($error) && !isset($_POST['forgot_password'])): ?>
            <p class="error"><?php echo $error; ?></p>
        <?php endif; ?>
        <form method="POST" class="login-form">
            <input type="hidden" name="login" value="1">
            <input type="text" name="username" placeholder="Username" required class="login-input">
            <input type="password" name="password" placeholder="Password" required class="login-input">
            <button type="submit" class="login-button">Login</button>
        </form>

        <!-- Links -->
        <div class="links">
            <a href="#" onclick="toggleForgotPassword()">Forgot Password?</a> | 
            <a href="reset_password.php">Already have token?</a>
        </div>

        <!-- Forgot Password Form -->
        <div id="forgotPasswordForm" class="forgot-password-form" <?php echo (isset($forgot_message) || isset($forgot_error)) ? 'style="display: block;"' : ''; ?>>
            <div id="forgotPasswordContent">
                <?php if (isset($forgot_message)): ?>
                    <p class="success login-success"><?php echo $forgot_message; ?></p>
                <?php endif; ?>
                <?php if (isset($forgot_error)): ?>
                    <p class="error"><?php echo $forgot_error; ?></p>
                <?php endif; ?>
                <form method="POST" id="forgotPasswordFormInner">
                    <input type="hidden" name="forgot_password" value="1">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="email" name="email" placeholder="Enter your email" required class="login-input">
                    <button type="submit" class="login-button">Request Reset Token</button>
                </form>
            </div>
        </div>
    </div>

    <script>
    function toggleForgotPassword() {
        const form = document.getElementById('forgotPasswordForm');
        form.style.display = form.style.display === 'block' ? 'none' : 'block';
    }

    function copyToken() {
        const token = document.getElementById('resetToken').textContent;
        const tempInput = document.createElement('input');
        tempInput.value = token;
        document.body.appendChild(tempInput);
        tempInput.select();
        document.execCommand('copy');
        document.body.removeChild(tempInput);
        alert('Token copied to clipboard! Paste it in reset_password.php');
    }
    </script>
</body>
</html>
