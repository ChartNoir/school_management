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

// Function to generate student ID (based on existing implementation)
function generateStudentId()
{
    global $pdo;

    // Get the last student_id to increment it
    $stmt = $pdo->query("SELECT student_id FROM student_details ORDER BY id DESC LIMIT 1");
    $lastId = $stmt->fetchColumn();

    if ($lastId) {
        // Extract the numeric part
        preg_match('/S(\d+)/', $lastId, $matches);
        if (isset($matches[1])) {
            $numericPart = intval($matches[1]);
            $newNumericPart = $numericPart + 1;
            return 'S' . str_pad($newNumericPart, 5, '0', STR_PAD_LEFT);
        }
    }

    // If no existing student IDs or pattern not matched, start with S00001
    return 'S00001';
}

// Registration function with debugging
function registerStudent($data, $files)
{
    global $pdo;
    try {
        // ULTRA VERBOSE LOGGING
        error_log("===== REGISTRATION DEBUG START =====");
        error_log("Received POST Data: " . print_r($data, true));

        // Extensive FILES array debugging
        error_log("Received FILES Data: " . print_r($files, true));

        $pdo->beginTransaction();

        // Check if username already exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $stmt->execute([$data['username']]);
        if ($stmt->fetchColumn() > 0) {
            return "Username already exists";
        }

        // Check if email already exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $stmt->execute([$data['email']]);
        if ($stmt->fetchColumn() > 0) {
            return "Email already exists";
        }

        // Handle profile image upload
        $profileImageUrl = null;

        // Comprehensive file check
        if (isset($files['profile_image'])) {
            error_log("Profile Image Input Details:");
            error_log("Temp Name: " . ($files['profile_image']['tmp_name'] ?? 'N/A'));
            error_log("Original Name: " . ($files['profile_image']['name'] ?? 'N/A'));
            error_log("File Size: " . ($files['profile_image']['size'] ?? 'N/A'));
            error_log("File Type: " . ($files['profile_image']['type'] ?? 'N/A'));
            error_log("File Error: " . ($files['profile_image']['error'] ?? 'N/A'));
        } else {
            error_log("NO PROFILE IMAGE FOUND IN FILES ARRAY");
        }

        // Check if profile image is uploaded
        if (!empty($files['profile_image']) && $files['profile_image']['error'] === UPLOAD_ERR_OK) {
            // Attempt Cloudinary upload
            $uploadResult = uploadToCloudinary($files['profile_image'], 'student_images');

            error_log("Cloudinary Upload Result: " . print_r($uploadResult, true));

            // Only set profile image URL if upload is successful
            if (is_array($uploadResult) && isset($uploadResult['url'])) {
                $profileImageUrl = $uploadResult['url'];
                error_log("SUCCESSFUL IMAGE UPLOAD URL: " . $profileImageUrl);
            } else {
                // If upload fails, log the error
                error_log(
                    "UPLOAD FAILED: " .
                        (is_string($uploadResult) ? $uploadResult : 'Unknown upload error')
                );
            }
        }

        // Log final image URL before insertion
        error_log("FINAL PROFILE IMAGE URL FOR DATABASE: " . ($profileImageUrl ?? 'NULL'));

        // Create user account with default password
        $defaultPassword = 'default123';
        $hashedPassword = password_hash($defaultPassword, PASSWORD_BCRYPT);

        // Insert user with profile image
        $stmt = $pdo->prepare("
            INSERT INTO users 
            (username, email, password, role, profile_image) 
            VALUES (?, ?, ?, 'student', ?)
        ");
        $userInsertResult = $stmt->execute([
            $data['username'],
            $data['email'],
            $hashedPassword,
            $profileImageUrl
        ]);

        // Log insert details
        error_log("USER INSERT RESULT: " . ($userInsertResult ? 'SUCCESS' : 'FAILED'));
        error_log("PDO ERROR INFO: " . print_r($stmt->errorInfo(), true));

        if (!$userInsertResult) {
            $errorInfo = $stmt->errorInfo();
            throw new Exception("Failed to create user: " . $errorInfo[2]);
        }

        $userId = $pdo->lastInsertId();

        // Create student details
        $enrollmentDate = date('Y-m-d');
        $graduationDate = date('Y-m-d', strtotime('+4 years'));

        $stmt = $pdo->prepare("
            INSERT INTO student_details 
            (user_id, full_name, student_id, enrollment_date, graduation_date, 
             phone_number, address, course_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $studentInsertResult = $stmt->execute([
            $userId,
            $data['full_name'],
            generateStudentId(), // Assuming this function exists
            $enrollmentDate,
            $graduationDate,
            $data['phone'] ?? null,
            $data['address'] ?? null,
            $data['course_id']
        ]);

        if (!$studentInsertResult) {
            $errorInfo = $stmt->errorInfo();
            throw new Exception("Failed to create student details: " . $errorInfo[2]);
        }

        // Commit transaction
        $pdo->commit();

        error_log("===== REGISTRATION SUCCESSFUL =====");

        // Return registration details
        return [
            'success' => true,
            'student_id' => generateStudentId(),
            'password' => $defaultPassword,
            'profile_image' => $profileImageUrl
        ];
    } catch (Exception $e) {
        // Rollback transaction on error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        // Log and return error
        error_log("REGISTRATION FINAL EXCEPTION: " . $e->getMessage());
        return "Registration failed: " . $e->getMessage();
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
        // Validate required fields
        $requiredFields = ['username', 'email', 'full_name', 'course_id'];
        $missingFields = [];

        foreach ($requiredFields as $field) {
            if (empty($_POST[$field])) {
                $missingFields[] = $field;
            }
        }

        if (!empty($missingFields)) {
            $error = "Please fill in all required fields: " . implode(', ', $missingFields);
            error_log("Missing required fields: " . implode(', ', $missingFields));
        } else {
            error_log("Calling registerStudent function");
            $result = registerStudent($_POST, $_FILES);

            if (is_array($result) && isset($result['success'])) {
                $success = "Registration successful! Your student ID is: <strong>" .
                    htmlspecialchars($result['student_id']) .
                    "</strong> and your default password is: <strong>" .
                    htmlspecialchars($result['password']) .
                    "</strong><br><br>Please <a href='login.php'>Login</a> with your new account and change your password for security.";
                error_log("Registration successful for student ID: " . $result['student_id']);

                // Clear CSRF token after successful registration to prevent reuse
                unset($_SESSION['csrf_token']);
                // Clear form data
                unset($_POST);
            } else {
                $error = $result;
                error_log("Registration failed: " . $result);
            }
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
                <input type="text" name="username" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
            </div>

            <div class="form-group">
                <label>Email *</label>
                <input type="email" name="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
            </div>

            <div class="form-group">
                <label>Full Name *</label>
                <input type="text" name="full_name" value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>" required>
            </div>

            <div class="form-group">
                <label>Profile Image</label>
                <input type="file" name="profile_image" accept="image/jpeg,image/png,image/gif">
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
                <input type="tel" name="phone" value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
            </div>

            <div class="form-group">
                <label>Address</label>
                <textarea name="address"><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
            </div>

            <button type="submit">Register</button>
            <p><a href="login.php">Back to Login</a></p>
        </form>
    </div>
</body>

</html>