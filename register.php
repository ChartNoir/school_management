<?php
session_start();
require_once 'config.php';
require_once 'cloudinary.php'; // Include Cloudinary integration

// CSRF token functions
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

// Function to generate unique student ID
function generateStudentId()
{
    global $pdo;

    // Maximum attempts to generate a unique ID
    $maxAttempts = 100;

    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
        // Get the last student ID
        $stmt = $pdo->query("SELECT student_id FROM student_details ORDER BY id DESC LIMIT 1");
        $lastId = $stmt->fetchColumn();

        // Generate new student ID
        if ($lastId) {
            // Extract numeric part
            preg_match('/S(\d+)/', $lastId, $matches);
            $numericPart = isset($matches[1]) ? intval($matches[1]) + 1 : 1;
        } else {
            // Start with first student ID if no previous IDs exist
            $numericPart = 1;
        }

        // Format the new student ID
        $newStudentId = 'S' . str_pad($numericPart, 5, '0', STR_PAD_LEFT);

        // Check if the ID is unique
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM student_details WHERE student_id = ?");
        $checkStmt->execute([$newStudentId]);

        // If the ID is unique, return it
        if ($checkStmt->fetchColumn() == 0) {
            return $newStudentId;
        }
    }

    // Fallback: generate a random unique ID if attempts fail
    do {
        $randomId = 'S' . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM student_details WHERE student_id = ?");
        $checkStmt->execute([$randomId]);
    } while ($checkStmt->fetchColumn() > 0);

    return $randomId;
}

// Registration function
function registerStudent($data, $files)
{
    global $pdo;

    try {
        // Start database transaction
        $pdo->beginTransaction();

        // Validate input
        $errors = [];

        // Check required fields
        $requiredFields = ['username', 'email', 'full_name', 'course_id'];
        foreach ($requiredFields as $field) {
            if (empty(trim($data[$field]))) {
                $errors[] = ucfirst($field) . " is required";
            }
        }

        // Email validation
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format";
        }

        // Check for existing username or email
        $checkStmt = $pdo->prepare("
            SELECT 
                (SELECT COUNT(*) FROM users WHERE username = ?) as username_count,
                (SELECT COUNT(*) FROM users WHERE email = ?) as email_count
        ");
        $checkStmt->execute([$data['username'], $data['email']]);
        $checkResult = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if ($checkResult['username_count'] > 0) {
            $errors[] = "Username already exists";
        }

        if ($checkResult['email_count'] > 0) {
            $errors[] = "Email already exists";
        }

        // Throw validation errors if any
        if (!empty($errors)) {
            throw new Exception(implode(", ", $errors));
        }

        // Profile image upload
        $profileImageUrl = null;
        if (isset($files['profile_image']) && $files['profile_image']['error'] == UPLOAD_ERR_OK) {
            // Log file details
            error_log("Profile Image Details:");
            error_log("Temp Name: " . $files['profile_image']['tmp_name']);
            error_log("Original Name: " . $files['profile_image']['name']);
            error_log("File Size: " . $files['profile_image']['size']);
            error_log("File Type: " . $files['profile_image']['type']);

            // Attempt Cloudinary upload
            $profileImageUrl = uploadToCloudinary($files['profile_image']);

            if ($profileImageUrl) {
                error_log("Successfully uploaded image: " . $profileImageUrl);
            } else {
                error_log("Failed to upload image for user: " . $data['username']);
            }
        } else {
            // Log why file upload failed
            if (isset($files['profile_image'])) {
                error_log("File upload error code: " . $files['profile_image']['error']);
                switch ($files['profile_image']['error']) {
                    case UPLOAD_ERR_INI_SIZE:
                        error_log("File is larger than upload_max_filesize");
                        break;
                    case UPLOAD_ERR_FORM_SIZE:
                        error_log("File is larger than MAX_FILE_SIZE");
                        break;
                    case UPLOAD_ERR_PARTIAL:
                        error_log("File was only partially uploaded");
                        break;
                    case UPLOAD_ERR_NO_FILE:
                        error_log("No file was uploaded");
                        break;
                }
            } else {
                error_log("No profile_image key in FILES array");
            }
        }

        // Generate default password
        $defaultPassword = "default123";
        $hashedPassword = password_hash($defaultPassword, PASSWORD_BCRYPT);

        // Insert user
        $userStmt = $pdo->prepare("
            INSERT INTO users 
            (username, email, password, role, profile_image) 
            VALUES (?, ?, ?, 'student', ?)
        ");
        $userStmt->execute([
            $data['username'],
            $data['email'],
            $hashedPassword,
            $profileImageUrl
        ]);
        $userId = $pdo->lastInsertId();

        // Generate student ID
        $studentId = generateStudentId();

        // Calculate dates
        $enrollmentDate = date('Y-m-d');
        $graduationDate = date('Y-m-d', strtotime('+4 years'));

        // Insert student details
        $studentStmt = $pdo->prepare("
            INSERT INTO student_details 
            (user_id, full_name, student_id, enrollment_date, graduation_date, 
             phone_number, address, course_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $studentStmt->execute([
            $userId,
            $data['full_name'],
            $studentId,
            $enrollmentDate,
            $graduationDate,
            $data['phone'] ?? null,
            $data['address'] ?? null,
            $data['course_id']
        ]);

        // Commit transaction
        $pdo->commit();

        return [
            'success' => true,
            'student_id' => $studentId,
            'password' => $defaultPassword,
            'profile_image' => $profileImageUrl
        ];
    } catch (Exception $e) {
        // Rollback transaction
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        // Log detailed error
        error_log("Registration Error: " . $e->getMessage());

        return $e->getMessage();
    }
}

// Generate CSRF token
$csrf_token = generateCsrfToken();

// Handle registration submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("Processing registration form submission");

    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "Invalid CSRF token";
        error_log("CSRF token validation failed");
    } else {
        $result = registerStudent($_POST, $_FILES);

        if (is_array($result) && isset($result['success'])) {
            $success = "Registration successful! Your student ID is: <strong>" .
                htmlspecialchars($result['student_id']) .
                "</strong> and your default password is: <strong>" .
                htmlspecialchars($result['password']) .
                "</strong><br><br>Please <a href='login.php'>Login</a> with your new account and change your password.";
            error_log("Registration successful for student ID: " . $result['student_id']);

            // Clear form data
            unset($_POST);
        } else {
            $error = $result;
            error_log("Registration failed: " . $result);
        }
    }
}
?>
<!DOCTYPE html>
<html>

<head>
    <title>Student Registration - School Management</title>
    <link rel="stylesheet" href="includes/style.css">
</head>

<body>
    <div class="container">
        <h1>Student Registration</h1>

        <?php if (isset($success)): ?>
            <p class="success"><?php echo $success; ?></p>
        <?php elseif (isset($error)): ?>
            <p class="error"><?php echo $error; ?></p>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

            <div class="form-group">
                <label>Username *</label>
                <input type="text" name="username"
                    value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                    required>
            </div>

            <div class="form-group">
                <label>Email *</label>
                <input type="email" name="email"
                    value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                    required>
            </div>

            <div class="form-group">
                <label>Full Name *</label>
                <input type="text" name="full_name"
                    value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>"
                    required>
            </div>

            <input type="hidden" name="MAX_FILE_SIZE" value="5242880">
            <div class="form-group">
                <label>Profile Image</label>
                <input type="file" name="profile_image" id="profile_image"
                    accept="image/jpeg,image/png,image/gif,image/jpg">
                <small>Max size: 5MB. Accepted formats: JPG, PNG, GIF</small>
            </div>

            <div class="form-group">
                <label>Course *</label>
                <select name="course_id" required>
                    <option value="">Select Course</option>
                    <?php
                    $stmt = $pdo->query("SELECT id, course_code, course_name FROM courses");
                    while ($course = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $selected = (isset($_POST['course_id']) && $_POST['course_id'] == $course['id']) ? 'selected' : '';
                        echo "<option value='" . $course['id'] . "' $selected>" .
                            htmlspecialchars($course['course_code']) . " - " .
                            htmlspecialchars($course['course_name']) . "</option>";
                    }
                    ?>
                </select>
            </div>

            <div class="form-group">
                <label>Phone Number</label>
                <input type="tel" name="phone"
                    value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
            </div>

            <div class="form-group">
                <label>Address</label>
                <textarea name="address"><?php
                                            echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : '';
                                            ?></textarea>
            </div>

            <button type="submit">Register</button>
            <p><a href="login.php">Back to Login</a></p>
        </form>
    </div>
</body>

</html>