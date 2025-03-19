<?php

function checkAndUpdateExpiredAccounts()
{
    global $pdo;
    try {
        // Detailed logging
        error_log("Starting account expiration check at " . date('Y-m-d H:i:s'));

        // Select students whose graduation_date + 1 day has passed and are still active
        $stmt = $pdo->prepare("
    SELECT
    u.id,
    u.username,
    u.email,
    sd.student_id,
    sd.graduation_date,
    u.is_active
    FROM users u
    JOIN student_details sd ON u.id=sd.user_id
    WHERE u.role='student'
    AND u.is_active=TRUE
    AND DATE(sd.graduation_date) < DATE_SUB(CURDATE(), INTERVAL 1 DAY) ");
        $stmt->execute();
        $expiredStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);

        error_log(" Found " . count($expiredStudents) . " expired student accounts");

        foreach ($expiredStudents as $student) {
            try {
                // Start a transaction for each student to ensure atomicity
                $pdo->beginTransaction();

                // Comprehensive deactivation
                $updateStmt = $pdo->prepare("
    UPDATE users
    SET
    is_active = FALSE,
    password = NULL, # Invalidate password
    password_reset_token = NULL,
    password_reset_expiry = NULL
    WHERE id = ?
    ");
                $updateStmt->execute([$student['id']]);

                // Revoke all existing session tokens or add a session invalidation mechanism
                $revokeStmt = $pdo->prepare("
    DELETE FROM user_sessions WHERE user_id = ?
    "); // Assumes you have a user_sessions table
                $revokeStmt->execute([$student['id']]);

                // Optional: Send expiration notification email
                sendAccountExpirationNotification($student['email'], $student['username'], $student['graduation_date']);

                // Delete Linux user account
                $username = escapeshellarg($student['username']);
                $command = "sudo userdel -r $username 2>&1";
                $output = shell_exec($command);

                // Log detailed account expiration details
                error_log(sprintf(
                    "Expired Account Details:
    - User ID: %d
    - Username: %s
    - Email: %s
    - Graduation Date: %s
    - Linux User Deletion: %s",
                    $student['id'],
                    $student['username'],
                    $student['email'],
                    $student['graduation_date'],
                    $output ? 'Failed' : 'Successful'
                ));

                // Commit the transaction
                $pdo->commit();
            } catch (Exception $studentException) {
                // Rollback if any error occurs during student account processing
                $pdo->rollBack();
                error_log("Error processing expired student {$student['username']}: " . $studentException->getMessage());
            }
        }

        return true;
    } catch (Exception $e) {
        error_log("Critical error in checkAndUpdateExpiredAccounts: " . $e->getMessage());
        return "Error checking expired accounts: " . $e->getMessage();
    }
}

// Optional: Email notification function
function sendAccountExpirationNotification($email, $username, $graduationDate)
{
    // Implement email sending logic
    // You would need to configure email settings
    $subject = "Your School Account Has Expired";
    $message = "Dear {$username},\n\n"
        . "Your school account has expired as of {$graduationDate}. "
        . "Please contact the administration for further assistance.";

    // Use PHP's mail() function or a library like PHPMailer
    @mail($email, $subject, $message);
}
function getStudentDetails()
{
    global $pdo;
    if ($_SESSION['role'] !== 'student' && $_SESSION['role'] !== 'admin') {
        return "Unauthorized";
    }
    try {
        $userId = $_SESSION['role'] === 'student' ? $_SESSION['user_id'] : null;
        $studentId = $_SESSION['role'] === 'admin' && isset($_POST['student_id']) ? $_POST['student_id'] : null;
        if ($_SESSION['role'] === 'admin' && !$studentId) {
            return "Student ID required for admin.";
        }
        $query = "SELECT sd.*, u.last_login, u.profile_image, u.email, u.id as user_id, u.is_active
    FROM student_details sd
    JOIN users u ON sd.user_id = u.id
    WHERE ";
        $params = [];
        if ($_SESSION['role'] === 'student') {
            $query .= "sd.user_id = ?";
            $params[] = $userId;
        } else {
            $query .= "sd.student_id = ?";
            $params[] = $studentId;
        }
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($student) {
            // Calculate Account Expiration Details
            $student['account_expiration'] = null;
            $student['days_until_expiration'] = null;
            $student['expiration_message'] = null;

            // Add account expiration details
            $stmt = $pdo->prepare("
    SELECT graduation_date
    FROM student_details
    WHERE user_id = ?
    ");
            $stmt->execute([$userId ?? $student['user_id']]);
            $graduationDetails = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($graduationDetails && !empty($graduationDetails['graduation_date'])) {
                $graduationDate = new DateTime($graduationDetails['graduation_date']);
                $graduationDate->modify('+1 day'); // Accounts expire 1 day after graduation
                $today = new DateTime();

                if ($today <= $graduationDate) {
                    $interval = $today->diff($graduationDate);
                    $student['account_expiration'] = $graduationDate->format('Y-m-d');
                    $student['days_until_expiration'] = $interval->days;

                    if ($interval->days <= 30) {
                        $student['expiration_message'] = $interval->days <= 7
                            ? "Warning: Your account will expire in {$interval->days} days!"
                            : "Your account will expire in {$interval->days} days.";
                    }
                }
            }

            // Existing code for enrollments, GPA, and grades remains the same
            $stmt = $pdo->prepare("
                SELECT c.class_name, co.course_name, c.schedule_time, c.room_number, e.status, c.schedule_start_date, c.schedule_end_date
                FROM enrollments e
                JOIN class c ON e.class_id = c.id
                JOIN courses co ON c.course_id = co.id
                WHERE e.student_id = (SELECT id FROM student_details WHERE user_id = ?)
                ");
            $stmt->execute([$userId ?? $student['user_id']]);
            $student['enrollments'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Calculate GPA by academic year and fetch grades
            $stmt = $pdo->prepare("
                SELECT
                g.academic_year,
                AVG(g.gpa_value) as year_gpa,
                COUNT(g.id) as grade_count
                FROM grades g
                WHERE g.student_id = (SELECT id FROM student_details WHERE user_id = ?)
                GROUP BY g.academic_year
                ");
            $stmt->execute([$userId ?? $student['user_id']]);
            $student['gpa_by_year'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Fetch grades per subject
            $stmt = $pdo->prepare("
                SELECT
                g.score,
                g.grade,
                g.gpa_value,
                c.class_name,
                co.course_name
                FROM grades g
                JOIN class c ON g.class_id = c.id
                JOIN courses co ON c.course_id = co.id
                WHERE g.student_id = (SELECT id FROM student_details WHERE user_id = ?)
                ");
            $stmt->execute([$userId ?? $student['user_id']]);
            $student['subject_grades'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        return $student;
    } catch (PDOException $e) {
        return "Database error: " . $e->getMessage();
    }
}
