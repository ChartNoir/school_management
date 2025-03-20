<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config.php';

$user_id = $_SESSION['user_id'] ?? null;
$username = 'Unknown';
$profile_image = 'default-user.png';
if ($user_id) {
    $stmt = $pdo->prepare("SELECT username, profile_image FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $username = htmlspecialchars($user['username']);
        $profile_image = $user['profile_image'] ?: 'default-user.png';
    }
}
?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>School Management System</title>
    <link rel="stylesheet" href="includes/style.css">
    <link rel="icon" href="data:;base64,iVBORw0KGgo=">
</head>

<body>
    <header>
        <nav>
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                <a href="#" onclick="showSection('createStudent')">Manage Students</a>
                <a href="#" onclick="showSection('teacher')">Manage Teachers</a>
                <a href="#" onclick="showSection('course')">Manage Courses</a>
                <a href="#" onclick="showSection('class')">Manage Classes</a>
                <a href="#" onclick="showSection('enrollment')">Manage Enrollments</a>
                <a href="#" onclick="showSection('updateGrades')">Manage Grades</a>
                <a href="#" onclick="showSection('edit')">View & Export Records</a>
            <?php endif; ?>
        </nav>
        <div class="user-profile" onclick="toggleProfileMenu()">
            <div class="profile-image" style="background-image: url('<?php echo htmlspecialchars($profile_image); ?>');"></div>
            <span><?php echo $username; ?></span>
            <div id="profileMenu" class="profile-menu">
                <button onclick="openProfileImageUpload()">Change Profile Picture</button>
                <button onclick="showPasswordPrompt()">Reset Password</button>
                <?php if (isset($_SESSION['role']) && ($_SESSION['role'] === 'student' || $_SESSION['role'] === 'teacher')): ?>
                    <button onclick="window.location.href='my_details.php'">My Details</button>
                <?php endif; ?>
                <form method="POST" action="logout.php" style="margin: 0;">
                    <button type="submit">Logout</button>
                </form>
            </div>
        </div>
    </header>