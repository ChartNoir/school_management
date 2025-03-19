<?php
session_start();
require_once 'config.php';

function generateCsrfToken()
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token)
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Enhanced login validation
function validateUserLogin($username, $password)
{
    global $pdo;

    // Prepare statement to fetch user details with complete account information
    $stmt = $pdo->prepare("
        SELECT 
            u.*, 
            sd.graduation_date,
            CASE 
                WHEN u.role = 'student' AND DATE(sd.graduation_date) < DATE_SUB(CURDATE(), INTERVAL 1 DAY) THEN TRUE 
                ELSE FALSE 
            END AS is_student_expired
        FROM users u
        LEFT JOIN student_details sd ON u.id = sd.user_id
        WHERE u.username = ?
    ");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Check if user exists and password is correct
    if (!$user || !password_verify($password, $user['password'])) {
        return ['status' => false, 'message' => 'Invalid credentials'];
    }

    // Check account active status
    if ($user['is_active'] === false) {
        return ['status' => false, 'message' => 'Account is inactive'];
    }

    // Specific check for student account expiration
    if ($user['role'] === 'student') {
        if ($user['is_student_expired']) {
            return ['status' => false, 'message' => 'Your student account has expired. Please contact the administration.'];
        }
    }

    return ['status' => true, 'user' => $user];
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $loginResult = validateUserLogin($username, $password);

    if ($loginResult['status']) {
        $user = $loginResult['user'];

        // Start a new session
        session_regenerate_id(true);

        // Store user information in session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];

        // Update last login time
        $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $stmt->execute([$user['id']]);

        // Insert session token
        $sessionToken = bin2hex(random_bytes(32));
        $stmt = $pdo->prepare("
            INSERT INTO user_sessions (user_id, session_token, last_activity) 
            VALUES (?, ?, NOW())
        ");
        $stmt->execute([$user['id'], $sessionToken]);

        // Set session token in cookie or session
        $_SESSION['session_token'] = $sessionToken;

        header("Location: index.php");
        exit;
    } else {
        $error = $loginResult['message'];
    }
}

// Handle forgot password request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['forgot_password'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $forgot_error = "Invalid CSRF token";
    } else {
        $email = $_POST['email'] ?? '';
        // Implement forgot password logic
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
        <?php if (isset($error)): ?>
            <p class="error"><?php echo htmlspecialchars($error); ?></p>
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
                    <p class="success login-success"><?php echo htmlspecialchars($forgot_message); ?></p>
                <?php endif; ?>
                <?php if (isset($forgot_error)): ?>
                    <p class="error"><?php echo htmlspecialchars($forgot_error); ?></p>
                <?php endif; ?>
                <form method="POST" id="forgotPasswordFormInner">
                    <input type="hidden" name="forgot_password" value="1">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
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