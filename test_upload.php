<?php
function registerStudent($data, $files)
{
    global $pdo;

    // Increase execution time for this function
    set_time_limit(60);

    try {
        // Start with extensive input validation
        $validationErrors = [];

        // Required field validation
        $requiredFields = [
            'username' => 'Username',
            'email' => 'Email',
            'full_name' => 'Full Name',
            'course_id' => 'Course'
        ];

        foreach ($requiredFields as $field => $label) {
            if (empty(trim($data[$field]))) {
                $validationErrors[] = "$label is required";
            }
        }

        // Email validation
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $validationErrors[] = "Invalid email format";
        }

        // Username length and format
        if (strlen($data['username']) < 3 || strlen($data['username']) > 50) {
            $validationErrors[] = "Username must be between 3 and 50 characters";
        }

        // Throw validation errors if any
        if (!empty($validationErrors)) {
            throw new Exception(implode(", ", $validationErrors));
        }

        // Begin transaction for atomic operation
        $pdo->beginTransaction();

        // Comprehensive duplicate checks with prepared statements
        $checkStmt = $pdo->prepare("
    SELECT
    (SELECT COUNT(*) FROM users WHERE username = ?) as username_count,
    (SELECT COUNT(*) FROM users WHERE email = ?) as email_count
    ");
        $checkStmt->execute([$data['username'], $data['email']]);
        $checkResult = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if ($checkResult['username_count'] > 0) {
            throw new Exception("Username already exists");
        }

        if ($checkResult['email_count'] > 0) {
            throw new Exception("Email already exists");
        }

        // Profile image handling (simplified)
        $profileImageUrl = null;
        if (!empty($files['profile_image']['tmp_name'])) {
            try {
                $uploadResult = uploadToCloudinary($files['profile_image'], 'student_images');
                if (is_array($uploadResult) && isset($uploadResult['url'])) {
                    $profileImageUrl = $uploadResult['url'];
                }
            } catch (Exception $uploadError) {
                error_log("Profile image upload failed: " . $uploadError->getMessage());
                // Continue registration even if image upload fails
            }
        }

        // Generate default password
        $defaultPassword = bin2hex(random_bytes(4)); // More secure default password
        $hashedPassword = password_hash($defaultPassword, PASSWORD_BCRYPT);

        // Prepare user insertion
        $userStmt = $pdo->prepare("
    INSERT INTO users
    (username, email, password, role, profile_image)
    VALUES (?, ?, ?, 'student', ?)
    ");
        $userInsertResult = $userStmt->execute([
            $data['username'],
            $data['email'],
            $hashedPassword,
            $profileImageUrl
        ]);

        if (!$userInsertResult) {
            throw new Exception("Failed to create user account");
        }

        $userId = $pdo->lastInsertId();

        // Generate unique student ID
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
        $studentInsertResult = $studentStmt->execute([
            $userId,
            $data['full_name'],
            $studentId,
            $enrollmentDate,
            $graduationDate,
            $data['phone'] ?? null,
            $data['address'] ?? null,
            $data['course_id']
        ]);

        if (!$studentInsertResult) {
            throw new Exception("Failed to create student details");
        }

        // Commit transaction
        $pdo->commit();

        // Optional: Send welcome email with credentials
        // sendWelcomeEmail($data['email'], $studentId, $defaultPassword);

        return [
            'success' => true,
            'student_id' => $studentId,
            'password' => $defaultPassword,
            'profile_image' => $profileImageUrl
        ];
    } catch (Exception $e) {
        // Always rollback on error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        // Log detailed error
        error_log("Registration Error: " . $e->getMessage());

        return $e->getMessage();
    }
}

// Improved student ID generation
function generateStudentId()
{
    global $pdo;

    // Use a more robust approach with transaction and retry
    $maxAttempts = 10;
    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
        try {
            // Start a transaction to prevent race conditions
            $pdo->beginTransaction();

            // Get the last student ID
            $stmt = $pdo->query("SELECT student_id FROM student_details ORDER BY id DESC LIMIT 1");
            $lastId = $stmt->fetchColumn();

            // Generate new student ID
            if ($lastId) {
                preg_match('/S(\d+)/', $lastId, $matches);
                $numericPart = isset($matches[1]) ? intval($matches[1]) + 1 : 1;
            } else {
                $numericPart = 1;
            }

            $newStudentId = 'S' . str_pad($numericPart, 5, '0', STR_PAD_LEFT);

            // Check if the new ID is unique
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM student_details WHERE student_id = ?");
            $checkStmt->execute([$newStudentId]);

            if ($checkStmt->fetchColumn() == 0) {
                // Commit the transaction
                $pdo->commit();
                return $newStudentId;
            }

            // If not unique, rollback and continue the loop
            $pdo->rollBack();
        } catch (Exception $e) {
            // Rollback on any error
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            // Log the error
            error_log("Student ID Generation Error: " . $e->getMessage());

            // On last attempt, throw an exception
            if ($attempt == $maxAttempts) {
                throw new Exception("Failed to generate unique student ID after $maxAttempts attempts");
            }
        }
    }

    // This should never be reached due to the loop and exception handling
    throw new Exception("Unexpected error in student ID generation");
}
