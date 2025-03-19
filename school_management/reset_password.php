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

function resetPassword($token, $newPassword) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE password_reset_token = ? AND password_reset_expiry > NOW()");
        $stmt->execute([$token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            return "Invalid or expired token";
        }
        $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("UPDATE users SET password = ?, password_reset_token = NULL, password_reset_expiry = NULL WHERE id = ?");
        $stmt->execute([$hashedPassword, $user['id']]);
        return true;
    } catch (Exception $e) {
        return "Error resetting password: " . $e->getMessage();
    }
}

// Generate CSRF token only if not already set
$csrf_token = generateCsrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "Invalid CSRF token";
    } else {
        $token = $_POST['token'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $result = resetPassword($token, $new_password);
        if ($result === true) {
            $message = "Password reset successfully. <a href='login.php'>Login</a> with your new password.";
            // Clear CSRF token after successful reset to prevent reuse
            unset($_SESSION['csrf_token']);
        } else {
            $error = $result;
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Reset Password - School Management</title>
    <link rel="stylesheet" href="includes/style.css">
    <style>
        .success { color: green; }
        .error { color: red; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Reset Password</h1>
        <?php if (isset($message)): ?>
            <p class="success"><?php echo $message; ?></p>
        <?php elseif (isset($error)): ?>
            <p class="error"><?php echo $error; ?></p>
        <?php endif; ?>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="text" name="token" placeholder="Enter your reset token" required>
            <input type="password" name="new_password" placeholder="Enter new password" required>
            <button type="submit">Reset Password</button>
        </form>
        <p><a href="login.php">Back to Login</a></p>
    </div>
</body>
</html>
