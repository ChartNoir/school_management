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

// Function to generate student ID
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

        // Generate student ID
        $studentId = generateStudentId();

        // Handle profile image upload if provided
        $profileImageUrl = null;
        if (isset($files['profile_image']) && $files['profile_image']['error'] === 0) {
            error_log("Attempting to upload profile image: " . $files['profile_image']['name']);
            $uploadResult = uploadToCloudinary($files['profile_image'], 'student_images');

            if (is_array($uploadResult) && isset($uploadResult['url'])) {
                $profileImageUrl = $uploadResult['url'];
                error_log("Profile image uploaded successfully to Cloudinary: " . $profileImageUrl);
            } elseif (is_string($uploadResult)) {
                // Upload failed, return error message
                error_log("Profile image upload failed: " . $uploadResult);
                return "Profile image upload failed: " . $uploadResult;
            }
        } else {
            error_log("No profile image provided or upload error: " . ($files['profile_image']['error'] ?? 'No file'));
        }

        // Create user account with default password
        $defaultPassword = 'default123';
        $hashedPassword = password_hash($defaultPassword, PASSWORD_BCRYPT);

        error_log("Attempting to insert user with profile image URL: " . ($profileImageUrl ?: 'NULL'));

        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role, profile_image) VALUES (?, ?, ?, 'student', ?)");
        $executeResult = $stmt->execute([$data['username'], $data['email'], $hashedPassword, $profileImageUrl]);

        if (!$executeResult) {
            $errorInfo = $stmt->errorInfo();
            error_log("Database error when inserting user: " . print_r($errorInfo, true));
            throw new Exception("Database error: " . $errorInfo[2]);
        }

        $userId = $pdo->lastInsertId();
        error_log("User created with ID: " . $userId);

        // Create student details
        $enrollmentDate = date('Y-m-d');
        $graduationDate = date('Y-m-d', strtotime('+4 years')); // Default graduation date (4 years from now)

        $stmt = $pdo->prepare("INSERT INTO student_details (user_id, full_name, student_id, enrollment_date, graduation_date, phone_number, address, course_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $executeResult = $stmt->execute([
            $userId,
            $data['full_name'],
            $studentId,
            $enrollmentDate,
            $graduationDate,
            $data['phone'] ?? null,
            $data['address'] ?? null,
            $data['course_id']
        ]);

        if (!$executeResult) {
            $errorInfo = $stmt->errorInfo();
            error_log("Database error when inserting student details: " . print_r($errorInfo, true));
            throw new Exception("Database error: " . $errorInfo[2]);
        }

        // Verify the profile image URL was stored correctly
        $checkStmt = $pdo->prepare("SELECT profile_image FROM users WHERE id = ?");
        $checkStmt->execute([$userId]);
        $storedImageUrl = $checkStmt->fetchColumn();
        error_log("Profile image URL stored in database: " . ($storedImageUrl ?: 'None'));

        // Make sure to commit the transaction
        $pdo->commit();
        error_log("Transaction committed successfully");

        return ['success' => true, 'student_id' => $studentId, 'password' => $defaultPassword];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
            error_log("Transaction rolled back due to error: " . $e->getMessage());
        }
        return "Registration error: " . $e->getMessage();
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
                $success = "Registration successful! Your student ID is: <strong>" . htmlspecialchars($result['student_id']) . "</strong> and your default password is: <strong>" . htmlspecialchars($result['password']) . "</strong><br><br>Please <a href='login.php'>Login</a> with your new account and change your password for security.";
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
    <style>
        .success {
            color: green;
        }

        .error {
            color: red;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .container {
            max-width: 550px;
        }

        .required:after {
            content: " *";
            color: red;
        }

        .image-preview {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            margin: 10px auto;
            border: 2px solid #ddd;
            background-color: #f8f9fa;
            background-size: cover;
            background-position: center;
            display: none;
        }
    </style>
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
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

            <div class="form-group">
                <label class="required">Username</label>
                <input type="text" name="username" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
            </div>

            <div class="form-group">
                <label class="required">Email</label>
                <input type="email" name="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
            </div>

            <div class="form-group">
                <label class="required">Full Name</label>
                <input type="text" name="full_name" value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>" required>
            </div>

            <div class="form-group">
                <p><strong>Note:</strong> Student ID will be automatically generated.<br>
                    Default password will be set to "default123".</p>
            </div>

            <div class="form-group">
                <label>Profile Image</label>
                <input type="file" name="profile_image" id="profileImageInput" accept="image/jpeg,image/png,image/gif">
                <small class="text-muted">Max size: 5MB. Accepted formats: JPG, PNG, GIF</small>
                <div id="imagePreview" class="image-preview"></div>
            </div>

            <div class="form-group">
                <label class="required">Course</label>
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

    <script>
        // Image preview functionality
        document.getElementById('profileImageInput').addEventListener('change', function() {
            const preview = document.getElementById('imagePreview');

            if (this.files && this.files[0]) {
                const file = this.files[0];

                // Check file size (max 5MB)
                if (file.size > 5 * 1024 * 1024) {
                    alert('File is too large. Maximum size is 5MB.');
                    this.value = '';
                    preview.style.display = 'none';
                    return;
                }

                // Check file type
                const validTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/jpg'];
                if (!validTypes.includes(file.type)) {
                    alert('Invalid file type. Only JPG, JPEG, PNG and GIF are allowed.');
                    this.value = '';
                    preview.style.display = 'none';
                    return;
                }

                // Show preview
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.style.backgroundImage = 'url(' + e.target.result + ')';
                    preview.style.display = 'block';
                };
                reader.readAsDataURL(file);
            } else {
                preview.style.display = 'none';
            }
        });
    </script>
</body>

</html>