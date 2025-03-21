<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Start session and include necessary files
session_start();
require_once 'config.php';
require_once 'cloudinary.php';

// Set response headers
header('Content-Type: application/json');

// Detailed logging function
function logUploadError($message)
{
    error_log("[Profile Image Upload] " . $message);
}

try {
    // Comprehensive authentication check
    if (!isset($_SESSION['user_id'])) {
        logUploadError("No user ID in session");
        throw new Exception('Authentication required');
    }

    // Log session and request details
    logUploadError("User ID: " . $_SESSION['user_id']);
    logUploadError("Role: " . ($_SESSION['role'] ?? 'No role'));
    logUploadError("Files: " . print_r($_FILES, true));
    logUploadError("POST data: " . print_r($_POST, true));

    // Validate file upload
    if (!isset($_FILES['profile_image'])) {
        logUploadError("No file uploaded");
        throw new Exception('No file uploaded');
    }

    $file = $_FILES['profile_image'];

    // Check file upload errors
    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_NO_FILE:
            logUploadError("No file sent");
            throw new Exception('No file was uploaded');
        case UPLOAD_ERR_INI_SIZE:
            logUploadError("File too large (PHP ini)");
            throw new Exception('File is too large');
        default:
            logUploadError("Unknown upload error: " . $file['error']);
            throw new Exception('File upload failed');
    }

    // Validate file type and size
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/jpg'];
    $maxFileSize = 5 * 1024 * 1024; // 5MB

    if (!in_array($file['type'], $allowedTypes)) {
        logUploadError("Invalid file type: " . $file['type']);
        throw new Exception('Invalid file type. Only JPG, JPEG, PNG, and GIF are allowed.');
    }

    if ($file['size'] > $maxFileSize) {
        logUploadError("File too large: " . $file['size'] . " bytes");
        throw new Exception('File is too large. Maximum size is 5MB.');
    }

    // Upload to Cloudinary
    $imageUrl = uploadToCloudinary($file);

    if (!$imageUrl) {
        logUploadError("Cloudinary upload failed");
        throw new Exception('Failed to upload image');
    }

    // Update profile image in database
    $stmt = $pdo->prepare("UPDATE users SET profile_image = ? WHERE id = ?");
    $result = $stmt->execute([$imageUrl, $_SESSION['user_id']]);

    if (!$result) {
        logUploadError("Database update failed");
        throw new Exception('Failed to update profile image in database');
    }

    // Successful response
    echo json_encode([
        'success' => true,
        'message' => 'Profile image updated successfully',
        'imageUrl' => $imageUrl
    ]);
} catch (Exception $e) {
    // Log and return error
    logUploadError("Exception: " . $e->getMessage());

    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
exit;
