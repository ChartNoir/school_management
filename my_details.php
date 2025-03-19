<?php
session_start();
require_once 'functions.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
include 'templates/header.php';
$csrf_token = generateCsrfToken();

$details = ($_SESSION['role'] === 'student') ? getStudentDetails() : getTeacherDetails();
if (!$details || !is_array($details)) {
    $details = ['full_name' => 'N/A', 'email' => 'N/A', 'department' => 'N/A'];
    if ($_SESSION['role'] === 'student') {
        $details['student_id'] = 'N/A';
        $details['enrollment_date'] = 'N/A';
        $details['graduation_date'] = 'N/A';
        $details['phone_number'] = 'N/A';
        $details['address'] = 'N/A';
    } elseif ($_SESSION['role'] === 'teacher') {
        $details['hire_date'] = 'N/A';
        $details['qualification'] = 'N/A';
        $details['phone_number'] = 'N/A';
    }
}
?>
<!DOCTYPE html>
<html>

<head>
    <title>My Details - School Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="/school_management/includes/style.css">
    <style>
        .details-card {
            max-width: 600px;
            margin: 50px auto;
            padding: 30px;
            background: #ffffff;
            border-radius: 15px;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        .details-card h2 {
            text-align: center;
            color: #333;
            margin-bottom: 20px;
        }

        .details-card p {
            margin: 10px 0;
            font-size: 16px;
            color: #555;
        }

        .details-card .label {
            font-weight: bold;
            color: #007bff;
        }

        .details-card .profile-image {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background-size: cover;
            background-position: center;
            margin: 0 auto 20px;
            border: 2px solid #007bff;
        }

        .details-card .back-btn {
            display: block;
            width: 100%;
            margin-top: 20px;
            text-align: center;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="details-card">
            <h2>My Details</h2>
            <?php if ($details['profile_image']): ?>
                <div class="profile-image" style="background-image: url('<?php echo htmlspecialchars($details['profile_image']); ?>');"></div>
            <?php else: ?>
                <div class="profile-image" style="background-image: url('default-user.png');"></div>
            <?php endif; ?>
            <p><span class="label">Full Name:</span> <?php echo htmlspecialchars($details['full_name']); ?></p>
            <p><span class="label">Role:</span> <?php echo htmlspecialchars($_SESSION['role']); ?></p>
            <p><span class="label">Email:</span> <?php echo htmlspecialchars($details['email']); ?></p>
            <?php if ($_SESSION['role'] === 'student'): ?>
                <p><span class="label">Student ID:</span> <?php echo htmlspecialchars($details['student_id'] ?? 'N/A'); ?></p>
                <p><span class="label">Department:</span> <?php echo htmlspecialchars($details['department'] ?? 'N/A'); ?></p>
                <p><span class="label">Enrollment Date:</span> <?php echo htmlspecialchars($details['enrollment_date'] ?? 'N/A'); ?></p>
                <p><span class="label">Graduation Date:</span> <?php echo htmlspecialchars($details['graduation_date'] ?? 'N/A'); ?></p>
                <p><span class="label">Phone Number:</span> <?php echo htmlspecialchars($details['phone_number'] ?? 'N/A'); ?></p>
                <p><span class="label">Address:</span> <?php echo htmlspecialchars($details['address'] ?? 'N/A'); ?></p>

                <?php if (!empty($details['account_expiration']) && !empty($details['expiration_message'])): ?>
                    <div class="account-expiration-warning <?php echo $details['days_until_expiration'] <= 7 ? 'text-danger' : 'text-warning'; ?>" id="account-expiration-countdown">
                        <p><strong>Account Expiration:</strong> <?php echo htmlspecialchars($details['expiration_message']); ?></p>
                        <p>
                            <span class="label">Expiration Date:</span>
                            <?php echo htmlspecialchars($details['account_expiration']); ?>
                        </p>
                        <div class="countdown-timer mt-2" id="countdown-timer">
                            <!-- Countdown will be populated by JavaScript -->
                        </div>
                    </div>
                <?php endif; ?>
            <?php elseif ($_SESSION['role'] === 'teacher'): ?>
                <p><span class="label">Department:</span> <?php echo htmlspecialchars($details['department'] ?? 'N/A'); ?></p>
                <p><span class="label">Hire Date:</span> <?php echo htmlspecialchars($details['hire_date'] ?? 'N/A'); ?></p>
                <p><span class="label">Qualification:</span> <?php echo htmlspecialchars($details['qualification'] ?? 'N/A'); ?></p>
                <p><span class="label">Phone Number:</span> <?php echo htmlspecialchars($details['phone_number'] ?? 'N/A'); ?></p>
            <?php endif; ?>
            <button class="btn btn-secondary back-btn" onclick="window.location.href='index.php'">Back to Dashboard</button>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
        <script src="/school_management/includes/script.js"></script>
</body>

</html>