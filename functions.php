<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
ob_start();



function checkRole($role)
{
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== $role) {
        header('Content-Type: application/json');
        echo json_encode(['result' => 'Error: Unauthorized']);
        exit;
    }
}

function createStudent($data)
{ // Removed $file parameter
    global $pdo;
    checkRole('admin');
    try {
        $pdo->beginTransaction();

        $password = password_hash('default123', PASSWORD_BCRYPT);
        $profileImage = !empty($data['profile_image']) ? $data['profile_image'] : null;

        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role, profile_image) VALUES (?, ?, ?, 'student', ?)");
        $stmt->execute([$data['username'], $data['email'], $password, $profileImage]);
        $user_id = $pdo->lastInsertId();

        // Updated to use course_id instead of department
        $stmt = $pdo->prepare("INSERT INTO student_details (user_id, full_name, student_id, course_id, enrollment_date, graduation_date, phone_number, address) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $data['full_name'], $data['student_id'], $data['course_id'], $data['enrollment_date'], $data['graduation_date'], $data['phone'], $data['address']]);

        $pdo->commit();

        $expire_date = date('Y-m-d', strtotime($data['graduation_date'] . ' +1 day'));
        $username = escapeshellarg($data['username']);
        $command = "sudo useradd -m -e '$expire_date' $username";
        $output = shell_exec("$command 2>&1");
        error_log("Shell command executed: $command, Output: " . ($output ?: 'No output'));
        if ($output && strpos($output, 'already exists') === false && strpos($output, 'success') === false) {
            error_log("Failed to create Linux user: $output");
            return "Student created successfully in database, but failed to create Linux user: $output";
        }

        return true;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error in createStudent: " . $e->getMessage());
        return "Failed to create student: " . $e->getMessage();
    }
}

function createTeacher($data)
{
    global $pdo;
    checkRole('admin');
    try {
        $pdo->beginTransaction();
        $password = password_hash('default123', PASSWORD_BCRYPT);
        $profileImage = !empty($data['profile_image']) ? $data['profile_image'] : null;

        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role, profile_image) VALUES (?, ?, ?, 'teacher', ?)");
        $stmt->execute([$data['username'], $data['email'], $password, $profileImage]);
        $user_id = $pdo->lastInsertId();

        // Updated to use course_id instead of department
        $stmt = $pdo->prepare("INSERT INTO teacher_details (user_id, full_name, course_id, hire_date, qualification, phone_number) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $data['full_name'], $data['course_id'], $data['hire_date'], $data['qualification'], $data['phone']]);
        error_log("Teacher created: user_id=$user_id, username={$data['username']}, course_id={$data['course_id']}");

        $pdo->commit();
        $output = shell_exec("sudo useradd -m " . escapeshellarg($data['username']) . " 2>&1");
        if ($output && strpos($output, 'already exists') === false) {
            error_log("Shell exec output for useradd: $output");
        }
        return true;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("PDO Error in createTeacher: " . $e->getMessage());
        return "Database error: " . $e->getMessage();
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error in createTeacher: " . $e->getMessage());
        return "Error: " . $e->getMessage();
    }
}

function createEnrollment($data)
{
    global $pdo;
    checkRole('admin');
    try {
        // Check if the student is already enrolled in the class
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE student_id = ? AND class_id = ?");
        $stmt->execute([$data['student_id'], $data['class_id']]);
        if ($stmt->fetchColumn() > 0) {
            return "Student is already enrolled in this class.";
        }

        // Check class capacity
        $stmt = $pdo->prepare("SELECT capacity, COUNT(e.id) as enrolled_count FROM class c LEFT JOIN enrollments e ON c.id = e.class_id WHERE c.id = ? GROUP BY c.id");
        $stmt->execute([$data['class_id']]);
        $classData = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($classData && $classData['enrolled_count'] >= $classData['capacity']) {
            return "Class is full.";
        }

        // Insert enrollment
        $stmt = $pdo->prepare("INSERT INTO enrollments (student_id, course_id, class_id) VALUES (?, (SELECT course_id FROM class WHERE id = ?), ?)");
        $result = $stmt->execute([$data['student_id'], $data['class_id'], $data['class_id']]);
        if ($result) {
            return true;
        } else {
            return "Failed to create enrollment.";
        }
    } catch (Exception $e) {
        return "Error creating enrollment: " . $e->getMessage();
    }
}

function updateStudentDetails($data)
{
    global $pdo;
    if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'student') {
        return "Error: Unauthorized";
    }
    try {
        $pdo->beginTransaction();

        $userId = $_SESSION['role'] === 'student' ? $_SESSION['user_id'] : null;
        $studentId = $_SESSION['role'] === 'admin' && isset($data['student_id']) ? $data['student_id'] : null;

        if ($_SESSION['role'] === 'student') {
            $stmt = $pdo->prepare("SELECT student_id FROM student_details WHERE user_id = ?");
            $stmt->execute([$userId]);
            $studentId = $stmt->fetchColumn();
            if (!$studentId) {
                throw new Exception("Student ID not found for user.");
            }
        } elseif (!$studentId) {
            throw new Exception("Student ID is required for admin updates.");
        }

        // Verify student exists and get user_id
        $stmt = $pdo->prepare("SELECT user_id FROM student_details WHERE student_id = ?");
        $stmt->execute([$studentId]);
        $userIdFromStudent = $stmt->fetchColumn();
        if ($userIdFromStudent === false) {
            throw new Exception("No student found with ID: $studentId");
        }

        // Update users table
        $userFields = [];
        $userParams = [];
        if (!empty($data['profile_image'])) {
            $userFields[] = "profile_image = ?";
            $userParams[] = $data['profile_image'];
        }
        if (!empty($data['email'])) {
            $userFields[] = "email = ?";
            $userParams[] = $data['email'];
        }
        if (!empty($userFields)) {
            $userParams[] = $userIdFromStudent;
            $query = "UPDATE users SET " . implode(", ", $userFields) . " WHERE id = ?";
            $stmt = $pdo->prepare($query);
            $stmt->execute($userParams);
            error_log("Updated users table with query: $query, params: " . implode(", ", $userParams));
            if ($stmt->rowCount() === 0) {
                throw new Exception("No user found for student ID: $studentId");
            }
        }

        // Update student_details table
        $studentFields = [];
        $studentParams = [];
        if (!empty($data['full_name'])) {
            $studentFields[] = "full_name = ?";
            $studentParams[] = $data['full_name'];
        }
        if (!empty($data['course_id'])) {
            $studentFields[] = "course_id = ?";
            $studentParams[] = $data['course_id'];
        }
        if (!empty($data['phone'])) {
            $studentFields[] = "phone_number = ?";
            $studentParams[] = $data['phone'];
        }
        if (!empty($studentFields)) {
            $studentParams[] = $studentId;
            $query = "UPDATE student_details SET " . implode(", ", $studentFields) . " WHERE student_id = ?";
            $stmt = $pdo->prepare($query);
            $stmt->execute($studentParams);
            error_log("Updated student_details with query: $query, params: " . implode(", ", $studentParams));
            if ($stmt->rowCount() === 0) {
                throw new Exception("Failed to update student details for ID: $studentId");
            }
        }

        if (empty($userFields) && empty($studentFields)) {
            throw new Exception("No fields provided to update.");
        }

        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error in updateStudentDetails: " . $e->getMessage());
        return "Error updating student details: " . $e->getMessage();
    }
}

function updateTeacherDetails($data)
{
    global $pdo;
    if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'teacher') {
        return "Error: Unauthorized";
    }
    try {
        $pdo->beginTransaction();

        $userId = $_SESSION['role'] === 'teacher' ? $_SESSION['user_id'] : (isset($data['user_id']) ? $data['user_id'] : null);
        if ($_SESSION['role'] === 'admin' && !$userId) {
            throw new Exception("User ID is required for admin updates.");
        }

        // Update users table
        $userFields = [];
        $userParams = [];
        if (!empty($data['profile_image'])) {
            $userFields[] = "profile_image = ?";
            $userParams[] = $data['profile_image'];
        }
        if (!empty($data['email'])) {
            $userFields[] = "email = ?";
            $userParams[] = $data['email'];
        }
        if (!empty($userFields)) {
            $userParams[] = $userId;
            $query = "UPDATE users SET " . implode(", ", $userFields) . " WHERE id = ?";
            $stmt = $pdo->prepare($query);
            $stmt->execute($userParams);
            error_log("Updated users table with query: $query, params: " . implode(", ", $userParams));
        }

        // Update teacher_details table with course_id
        $teacherFields = [];
        $teacherParams = [];
        if (!empty($data['full_name'])) {
            $teacherFields[] = "full_name = ?";
            $teacherParams[] = $data['full_name'];
        }
        if (!empty($data['course_id'])) { // Changed from department to course_id
            $teacherFields[] = "course_id = ?";
            $teacherParams[] = $data['course_id'];
        }
        if (!empty($data['phone'])) {
            $teacherFields[] = "phone_number = ?";
            $teacherParams[] = $data['phone'];
        }
        if (!empty($teacherFields)) {
            $teacherParams[] = $userId;
            $query = "UPDATE teacher_details SET " . implode(", ", $teacherFields) . " WHERE user_id = ?";
            $stmt = $pdo->prepare($query);
            $stmt->execute($teacherParams);
            error_log("Updated teacher_details with query: $query, params: " . implode(", ", $teacherParams));
            if ($stmt->rowCount() === 0) {
                throw new Exception("No teacher found with ID: $userId");
            }
        }

        if (empty($userFields) && empty($teacherFields)) {
            throw new Exception("No fields provided to update.");
        }

        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error in updateTeacherDetails: " . $e->getMessage());
        return "Error updating teacher details: " . $e->getMessage();
    }
}


// Specific update handlers

function createCourse($data)
{
    global $pdo;
    checkRole('admin');
    $stmt = $pdo->prepare("INSERT INTO courses (course_code, course_name, department, credits, description) VALUES (?, ?, ?, ?, ?)");
    return $stmt->execute([$data['course_code'], $data['course_name'], $data['department'], $data['credits'], $data['description']]);
}

function createClass($data)
{
    global $pdo;
    checkRole('admin');
    try {
        $stmt = $pdo->prepare("INSERT INTO class (course_id, teacher_id, class_name, schedule_start_date, schedule_end_date, schedule_time, room_number, capacity, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $result = $stmt->execute([
            $data['course_id'],
            $data['teacher_id'],
            $data['class_name'],
            $data['schedule_start_date'],
            $data['schedule_end_date'],
            $data['schedule_time'],
            $data['room_number'],
            $data['capacity'],
            $data['description']
        ]);
        if ($result) {
            return true;
        } else {
            return "Failed to create class.";
        }
    } catch (Exception $e) {
        return "Error creating class: " . $e->getMessage();
    }
}

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
function showTable($table)
{
    global $pdo;
    $valid_tables = [
        'users' => 'users',
        'student_details' => 'student_details',
        'teacher_details' => 'teacher_details',
        'courses' => 'courses',
        'class' => 'class',
        'enrollments' => 'enrollments',
        'grades' => 'grades'
    ];
    if (!isset($valid_tables[$table])) {
        return "Invalid table";
    }
    try {
        $query = "SELECT * FROM " . $valid_tables[$table];
        $params = [];

        // Apply is_active filter for users table only
        if ($table === 'users' && isset($_POST['is_active']) && $_POST['is_active'] !== '') {
            $isActive = filter_var($_POST['is_active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($isActive !== null) {
                $query .= " WHERE is_active = ?";
                $params[] = $isActive;
            }
        }

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return "Database error: " . $e->getMessage();
    }
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

function getTeacherDetails()
{
    global $pdo;
    if ($_SESSION['role'] !== 'teacher' && $_SESSION['role'] !== 'admin') {
        return "Unauthorized";
    }
    try {
        $userId = $_SESSION['role'] === 'teacher' ? $_SESSION['user_id'] : (isset($_POST['user_id']) ? $_POST['user_id'] : null);
        if ($_SESSION['role'] === 'admin' && !$userId) {
            return "User ID required for admin.";
        }
        $stmt = $pdo->prepare("SELECT td.*, u.last_login, u.profile_image, u.email, u.id as user_id FROM teacher_details td JOIN users u ON td.user_id = u.id WHERE td.user_id = ?");
        $stmt->execute([$userId]);
        $teacher = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($teacher) {
            // Fetch classes taught by the teacher
            $stmt = $pdo->prepare("
                SELECT c.id, c.class_name, co.course_name, c.schedule_time, c.room_number, c.schedule_start_date, c.schedule_end_date
                FROM class c
                JOIN courses co ON c.course_id = co.id
                WHERE c.teacher_id = ?
            ");
            $stmt->execute([$userId]);
            $teacher['classes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        return $teacher;
    } catch (PDOException $e) {
        return "Database error: " . $e->getMessage();
    }
}

// Helper function to calculate grade and GPA from score
function calculateGradeAndGpa($score)
{
    if ($score >= 85 && $score <= 100) return ['grade' => 'A', 'gpa' => 4.0];
    if ($score >= 80 && $score <= 84) return ['grade' => 'B+', 'gpa' => 3.5];
    if ($score >= 70 && $score <= 79) return ['grade' => 'B', 'gpa' => 3.0];
    if ($score >= 65 && $score <= 69) return ['grade' => 'C+', 'gpa' => 2.5];
    if ($score >= 50 && $score <= 64) return ['grade' => 'C', 'gpa' => 2.0];
    if ($score >= 45 && $score <= 49) return ['grade' => 'D', 'gpa' => 1.5];
    return ['grade' => 'F', 'gpa' => 0.0]; // Below 45
}

// New function to update or insert grades (for admin use)
function updateGrade($data)
{
    global $pdo;
    checkRole('admin');
    try {
        $studentId = $data['student_id'];
        $classId = $data['class_id'];
        $score = $data['score'];

        $gradeData = calculateGradeAndGpa($score);
        $grade = $gradeData['grade'];
        $gpaValue = $gradeData['gpa'];

        // Determine academic year from class schedule
        $stmt = $pdo->prepare("SELECT schedule_start_date, schedule_end_date FROM class WHERE id = ?");
        $stmt->execute([$classId]);
        $class = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$class) return "Class not found.";
        $startYear = date('Y', strtotime($class['schedule_start_date']));
        $endYear = date('Y', strtotime($class['schedule_end_date']));
        $academicYear = "$startYear-$endYear";

        // Check if grade exists, update or insert
        $stmt = $pdo->prepare("SELECT id FROM grades WHERE student_id = (SELECT id FROM student_details WHERE student_id = ?) AND class_id = ?");
        $stmt->execute([$studentId, $classId]);
        if ($stmt->fetchColumn()) {
            $stmt = $pdo->prepare("UPDATE grades SET score = ?, grade = ?, gpa_value = ?, academic_year = ? WHERE student_id = (SELECT id FROM student_details WHERE student_id = ?) AND class_id = ?");
            $stmt->execute([$score, $grade, $gpaValue, $academicYear, $studentId, $classId]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO grades (student_id, class_id, academic_year, score, grade, gpa_value) VALUES ((SELECT id FROM student_details WHERE student_id = ?), ?, ?, ?, ?, ?)");
            $stmt->execute([$studentId, $classId, $academicYear, $score, $grade, $gpaValue]);
        }
        return true;
    } catch (PDOException $e) {
        return "Database error: " . $e->getMessage();
    }
}

function generateCsrfToken()
{
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    error_log("Generated CSRF token: " . $_SESSION['csrf_token']);
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token)
{
    $result = isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    error_log("Verifying CSRF token. Session: " . ($_SESSION['csrf_token'] ?? 'none') . ", Sent: " . ($token ?? 'none') . ", Result: " . ($result ? 'true' : 'false'));
    return $result;
}

function generatePasswordResetToken()
{
    global $pdo;
    try {
        error_log("Generating reset token for user ID: " . ($_SESSION['user_id'] ?? 'none'));
        $stmt = $pdo->prepare("SELECT id, email, password, role FROM users WHERE id = ? AND is_active = TRUE");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            error_log("User not found for ID: " . ($_SESSION['user_id'] ?? 'none'));
            return "User not found";
        }

        // Require current password for all roles
        if (!isset($_POST['current_password']) || empty($_POST['current_password'])) {
            return "Current password is required!";
        }
        $currentPassword = $_POST['current_password'];
        if (!password_verify($currentPassword, $user['password'])) {
            return "Incorrect current password";
        }

        // Generate token and update database for all roles
        $token = bin2hex(random_bytes(16));
        $stmt = $pdo->prepare("UPDATE users SET password_reset_token = ?, password_reset_expiry = NOW() + INTERVAL 3 MINUTE WHERE id = ?");
        $result = $stmt->execute([$token, $user['id']]);
        if (!$result) {
            error_log("Failed to update password_reset_token for user ID: " . $user['id'] . ". Error: " . print_r($stmt->errorInfo(), true));
            return "Failed to generate token: Database update error";
        }
        error_log("Successfully updated token for user ID: " . $user['id']);

        // Return a generic success message without the token
        return "A password reset token has been generated. Please contact an administrator to receive it.";
    } catch (Exception $e) {
        error_log("Exception in generatePasswordResetToken: " . $e->getMessage());
        return "Error generating reset token: " . $e->getMessage();
    }
}

function resetPassword($token, $newPassword)
{
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

// New function to get table fields
function getTableFields($table)
{
    global $pdo;
    $current_db = $pdo->query("SELECT DATABASE()")->fetchColumn();
    error_log("getTableFields: Current database: $current_db, Table: $table");
    $valid_tables = [
        'users' => 'users',
        'student_details' => 'student_details',
        'teacher_details' => 'teacher_details',
        'courses' => 'courses',
        'class' => 'class',
        'enrollments' => 'enrollments',
        'grades' => 'grades'
    ];
    if (!isset($valid_tables[$table])) {
        return "Invalid table";
    }
    try {
        $stmt = $pdo->query("DESCRIBE " . $valid_tables[$table]);
        $fields = $stmt->fetchAll(PDO::FETCH_COLUMN, 0); // Fetch the 'Field' column
        // Filter out hidden fields
        $hiddenFields = ['password']; // Add more as needed
        $visibleFields = array_filter($fields, function ($field) use ($hiddenFields) {
            return !in_array($field, $hiddenFields);
        });
        return array_values($visibleFields); // Re-index array
    } catch (PDOException $e) {
        return "Database error: " . $e->getMessage();
    }
}

// Update Users Record
function updateUsersRecord($data)
{
    global $pdo;
    checkRole('admin');
    try {
        // Ensure id is present
        if (!isset($data['id'])) {
            return "Error: ID is required.";
        }

        // Build dynamic query based on provided fields
        $fields = [];
        $params = [];
        $allowedFields = ['username', 'email', 'role', 'profile_image', 'is_active'];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = ?";
                $params[] = $field === 'is_active' ? filter_var($data[$field], FILTER_VALIDATE_BOOLEAN) : $data[$field];
            }
        }

        if (empty($fields)) {
            return "Error: No fields provided to update.";
        }

        $params[] = $data['id'];
        $query = "UPDATE users SET " . implode(", ", $fields) . " WHERE id = ?";
        $stmt = $pdo->prepare($query);
        $result = $stmt->execute($params);

        if (!$result) {
            return "Failed to update user: No changes made or user not found.";
        }

        return true;
    } catch (Exception $e) {
        error_log("Error in updateUsersRecord: " . $e->getMessage());
        return "Error updating user: " . $e->getMessage();
    }
}

// Update Student Details Record
function updateStudentDetailsRecord($data)
{
    global $pdo;
    checkRole('admin');
    try {
        $stmt = $pdo->prepare("UPDATE student_details SET full_name = ?, student_id = ?, course_id = ?, enrollment_date = ?, graduation_date = ?, phone_number = ?, address = ? WHERE id = ?");
        $result = $stmt->execute([
            $data['full_name'],
            $data['student_id'],
            $data['course_id'],
            $data['enrollment_date'],
            $data['graduation_date'],
            $data['phone_number'],
            $data['address'],
            $data['id']
        ]);
        // Optionally update the associated users table if email or profile_image changes
        if (isset($data['email']) || isset($data['profile_image'])) {
            $userStmt = $pdo->prepare("UPDATE users SET email = ?, profile_image = ? WHERE id = (SELECT user_id FROM student_details WHERE id = ?)");
            $userStmt->execute([
                $data['email'] ?? null,
                $data['profile_image'] ?? null,
                $data['id']
            ]);
        }
        return $result ? true : "Failed to update student details.";
    } catch (Exception $e) {
        return "Error updating student details: " . $e->getMessage();
    }
}

// Update Teacher Details Record
function updateTeacherDetailsRecord($data)
{
    global $pdo;
    checkRole('admin');
    try {
        $stmt = $pdo->prepare("UPDATE teacher_details SET full_name = ?, course_id = ?, hire_date = ?, qualification = ?, phone_number = ? WHERE id = ?");
        $result = $stmt->execute([
            $data['full_name'],
            $data['course_id'],
            $data['hire_date'],
            $data['qualification'],
            $data['phone_number'],
            $data['id']
        ]);
        // Optionally update the associated users table if email or profile_image changes
        if (isset($data['email']) || isset($data['profile_image'])) {
            $userStmt = $pdo->prepare("UPDATE users SET email = ?, profile_image = ? WHERE id = (SELECT user_id FROM teacher_details WHERE id = ?)");
            $userStmt->execute([
                $data['email'] ?? null,
                $data['profile_image'] ?? null,
                $data['id']
            ]);
        }
        return $result ? true : "Failed to update teacher details.";
    } catch (Exception $e) {
        return "Error updating teacher details: " . $e->getMessage();
    }
}

// Update Courses Record
function updateCoursesRecord($data)
{
    global $pdo;
    checkRole('admin');
    try {
        $stmt = $pdo->prepare("UPDATE courses SET course_code = ?, course_name = ?, department = ?, credits = ?, description = ? WHERE id = ?");
        $result = $stmt->execute([
            $data['course_code'],
            $data['course_name'],
            $data['department'],
            $data['credits'],
            $data['description'],
            $data['id']
        ]);
        return $result ? true : "Failed to update course.";
    } catch (Exception $e) {
        return "Error updating course: " . $e->getMessage();
    }
}

// Update Class Record
function updateClassRecord($data)
{
    global $pdo;
    checkRole('admin');
    try {
        $stmt = $pdo->prepare("UPDATE class SET course_id = ?, teacher_id = ?, class_name = ?, schedule_start_date = ?, schedule_end_date = ?, schedule_time = ?, room_number = ?, capacity = ?, description = ? WHERE id = ?");
        $result = $stmt->execute([
            $data['course_id'],
            $data['teacher_id'],
            $data['class_name'],
            $data['schedule_start_date'],
            $data['schedule_end_date'],
            $data['schedule_time'],
            $data['room_number'],
            $data['capacity'],
            $data['description'],
            $data['id']
        ]);
        return $result ? true : "Failed to update class.";
    } catch (Exception $e) {
        return "Error updating class: " . $e->getMessage();
    }
}

// Update Enrollments Record
function updateEnrollmentsRecord($data)
{
    global $pdo;
    checkRole('admin');
    try {
        $stmt = $pdo->prepare("UPDATE enrollments SET student_id = ?, course_id = ?, class_id = ? WHERE id = ?");
        $result = $stmt->execute([
            $data['student_id'],
            $data['course_id'],
            $data['class_id'],
            $data['id']
        ]);
        return $result ? true : "Failed to update enrollment.";
    } catch (Exception $e) {
        return "Error updating enrollment: " . $e->getMessage();
    }
}

// Update Grades Record
function updateGradesRecord($data)
{
    global $pdo;
    checkRole('admin');
    try {
        $gradeData = calculateGradeAndGpa($data['score']);
        $stmt = $pdo->prepare("UPDATE grades SET student_id = ?, class_id = ?, academic_year = ?, score = ?, grade = ?, gpa_value = ? WHERE id = ?");
        $result = $stmt->execute([
            $data['student_id'],
            $data['class_id'],
            $data['academic_year'],
            $data['score'],
            $gradeData['grade'],
            $gradeData['gpa'],
            $data['id']
        ]);
        return $result ? true : "Failed to update grade.";
    } catch (Exception $e) {
        return "Error updating grade: " . $e->getMessage();
    }
}

// Delete Users Record
function deleteUsersRecord($id)
{
    global $pdo;
    checkRole('admin');
    try {
        // Check for related records in student_details or teacher_details
        $stmtStudent = $pdo->prepare("SELECT id FROM student_details WHERE user_id = ?");
        $stmtStudent->execute([$id]);
        $studentExists = $stmtStudent->fetchColumn();

        $stmtTeacher = $pdo->prepare("SELECT id FROM teacher_details WHERE user_id = ?");
        $stmtTeacher->execute([$id]);
        $teacherExists = $stmtTeacher->fetchColumn();

        if ($studentExists) {
            $updateStmt = $pdo->prepare("UPDATE student_details SET user_id = NULL WHERE user_id = ?");
            $updateStmt->execute([$id]);
        }

        if ($teacherExists) {
            $updateStmt = $pdo->prepare("UPDATE teacher_details SET user_id = NULL WHERE user_id = ?");
            $updateStmt->execute([$id]);
        }

        // Instead of deleting, deactivate the user for safety
        $stmt = $pdo->prepare("UPDATE users SET is_active = FALSE WHERE id = ?");
        $result = $stmt->execute([$id]);
        return $result ? true : "Failed to deactivate user.";
    } catch (Exception $e) {
        return "Error deactivating user: " . $e->getMessage();
    }
}

// Delete Student Details Record
function deleteStudentDetailsRecord($id)
{
    global $pdo;
    checkRole('admin');
    try {
        // First, get the user_id to potentially deactivate the user
        $stmt = $pdo->prepare("SELECT user_id FROM student_details WHERE id = ?");
        $stmt->execute([$id]);
        $userId = $stmt->fetchColumn();

        $stmt = $pdo->prepare("DELETE FROM student_details WHERE id = ?");
        $result = $stmt->execute([$id]);
        if ($result && $userId) {
            // Optionally deactivate the user instead of deleting (safer approach)
            $userStmt = $pdo->prepare("UPDATE users SET is_active = FALSE WHERE id = ?");
            $userStmt->execute([$userId]);
        }
        return $result ? true : "Failed to delete student details.";
    } catch (Exception $e) {
        return "Error deleting student details: " . $e->getMessage();
    }
}

// Delete Teacher Details Record
function deleteTeacherDetailsRecord($id)
{
    global $pdo;
    checkRole('admin');
    try {
        // First, get the user_id to potentially deactivate the user
        $stmt = $pdo->prepare("SELECT user_id FROM teacher_details WHERE id = ?");
        $stmt->execute([$id]);
        $userId = $stmt->fetchColumn();

        $stmt = $pdo->prepare("DELETE FROM teacher_details WHERE id = ?");
        $result = $stmt->execute([$id]);
        if ($result && $userId) {
            // Optionally deactivate the user instead of deleting (safer approach)
            $userStmt = $pdo->prepare("UPDATE users SET is_active = FALSE WHERE id = ?");
            $userStmt->execute([$userId]);
        }
        return $result ? true : "Failed to delete teacher details.";
    } catch (Exception $e) {
        return "Error deleting teacher details: " . $e->getMessage();
    }
}

// Delete Record Functions
function deleteCoursesRecord($id)
{
    global $pdo;
    checkRole('admin');
    try {
        $stmt = $pdo->prepare("DELETE FROM courses WHERE id = ?");
        return $stmt->execute([$id]) ? true : "Failed to delete course.";
    } catch (Exception $e) {
        return "Error deleting course: " . $e->getMessage();
    }
}

function deleteClassRecord($id)
{
    global $pdo;
    checkRole('admin');
    try {
        $stmt = $pdo->prepare("DELETE FROM class WHERE id = ?");
        return $stmt->execute([$id]) ? true : "Failed to delete class.";
    } catch (Exception $e) {
        return "Error deleting class: " . $e->getMessage();
    }
}

function deleteEnrollmentsRecord($id)
{
    global $pdo;
    checkRole('admin');
    try {
        $stmt = $pdo->prepare("DELETE FROM enrollments WHERE id = ?");
        return $stmt->execute([$id]) ? true : "Failed to delete enrollment.";
    } catch (Exception $e) {
        return "Error deleting enrollment: " . $e->getMessage();
    }
}

function deleteGradesRecord($id)
{
    global $pdo;
    checkRole('admin');
    try {
        $stmt = $pdo->prepare("DELETE FROM grades WHERE id = ?");
        return $stmt->execute([$id]) ? true : "Failed to delete grade.";
    } catch (Exception $e) {
        return "Error deleting grade: " . $e->getMessage();
    }
}

// Process CLI or POST requests
if (PHP_SAPI === 'cli') {
    // CLI mode (e.g., cron job)
    try {
        $_SESSION['role'] = 'admin';
        $result = checkAndUpdateExpiredAccounts();
        echo json_encode(['result' => $result]);
    } catch (Exception $e) {
        echo json_encode(['result' => 'Error: ' . $e->getMessage()]);
    }
    ob_end_flush();
    exit;
}

// Process HTTP POST requests only
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    try {
        error_log("Session CSRF: " . ($_SESSION['csrf_token'] ?? 'none') . ", Sent CSRF: " . ($_POST['csrf_token'] ?? 'none'));
        if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            error_log("CSRF token validation failed.");
            echo json_encode(['result' => 'Error: Invalid CSRF token']);
            exit;
        }
        $action = $_POST['action'] ?? '';
        error_log("Processing action: $action");
        $result = '';

        switch ($action) {
            case 'create_student':
                $result = createStudent($_POST, $_FILES);
                break;
            case 'create_teacher':
                $result = createTeacher($_POST, $_FILES);
                break;
            case 'create_course':
                $result = createCourse($_POST);
                break;
            case 'create_class':
                $result = createClass($_POST);
                break;
            case 'create_enrollment':
                $result = createEnrollment($_POST);
                break;
            case 'show_table':
                $result = showTable($_POST['table']);
                break;
            case 'get_student_details':
                $result = getStudentDetails();
                break;
            case 'get_teacher_details':
                $result = getTeacherDetails();
                break;
            case 'generate_reset_token':
                $result = generatePasswordResetToken();
                break;
            case 'reset_password':
                $result = resetPassword($_POST['token'], $_POST['new_password']);
                break;
            case 'update_grade':
                $result = updateGrade($_POST);
                break;
            case 'check_expired_accounts':
                checkRole('admin');
                $result = checkAndUpdateExpiredAccounts();
                break;
            case 'get_table_fields':
                $result = getTableFields($_POST['table']);
                break;
            case 'update_courses_record':
                $result = updateCoursesRecord(json_decode($_POST['data'], true));
                break;
            case 'update_class_record':
                $result = updateClassRecord(json_decode($_POST['data'], true));
                break;
            case 'update_enrollments_record':
                $result = updateEnrollmentsRecord(json_decode($_POST['data'], true));
                break;
            case 'update_grades_record':
                $result = updateGradesRecord(json_decode($_POST['data'], true));
                break;
            case 'update_student_details_record':
                $result = updateStudentDetailsRecord(json_decode($_POST['data'], true));
                break;
            case 'update_teacher_details_record':
                $result = updateTeacherDetailsRecord(json_decode($_POST['data'], true));
                break;

            case 'update_users_record':
                $result = updateUsersRecord(json_decode($_POST['data'], true));
                break;
            case 'delete_users_record':
                $result = deleteUsersRecord($_POST['id']);
                break;
            case 'delete_student_details_record':
                $result = deleteStudentDetailsRecord($_POST['id']);
                break;
            // case 'delete_teacher_details_record':
            //     $result = deleteTeacherDetailsRecords($_POST['id']);
            //     break;
            case 'delete_courses_record':
                $result = deleteCoursesRecord($_POST['id']);
                break;
            case 'delete_class_record':
                $result = deleteClassRecord($_POST['id']);
                break;
            case 'delete_enrollments_record':
                $result = deleteEnrollmentsRecord($_POST['id']);
                break;
            case 'delete_grades_record':
                $result = deleteGradesRecord($_POST['id']);
                break;

            default:
                error_log("Unknown action: $action");
                $result = "Unknown action";
                break;
        }

        echo json_encode(['result' => $result]);
    } catch (Exception $e) {
        error_log("Exception in POST handler: " . $e->getMessage());
        echo json_encode(['result' => 'Error: ' . $e->getMessage()]);
    }
    ob_end_flush();
    exit;
}

// For GET requests, allow the script to continue (e.g., for index.php rendering)
ob_end_flush();
