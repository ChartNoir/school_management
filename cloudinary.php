<?php
// Cloudinary configuration
define('CLOUDINARY_CLOUD_NAME', 'dxmjvgg1y');
define('CLOUDINARY_API_KEY', '881954448675579');
define('CLOUDINARY_API_SECRET', 'huYsbjoK7dMg7ATI2uPUGmBFSQE');

/**
 * Upload an image to Cloudinary with ULTRA VERBOSE logging
 */
function uploadToCloudinary($file, $folder = 'student_images')
{
    error_log("===== CLOUDINARY UPLOAD DEBUG START =====");

    // Validate file existence and initial checks
    if (!isset($file)) {
        error_log("NO FILE PROVIDED TO UPLOAD FUNCTION");
        return "No file provided";
    }

    error_log("File Details:");
    error_log("Temp Name: " . ($file['tmp_name'] ?? 'N/A'));
    error_log("Original Name: " . ($file['name'] ?? 'N/A'));
    error_log("File Size: " . ($file['size'] ?? 'N/A'));
    error_log("File Type: " . ($file['type'] ?? 'N/A'));
    error_log("File Error Code: " . ($file['error'] ?? 'N/A'));

    // Check file error
    if ($file['error'] !== UPLOAD_ERR_OK) {
        error_log("FILE UPLOAD ERROR: " . $file['error']);
        return "File upload error: " . $file['error'];
    }

    // Validate file size and type
    if ($file['size'] > 5 * 1024 * 1024) {
        error_log("FILE TOO LARGE: " . $file['size'] . " bytes");
        return "File too large. Maximum size is 5MB.";
    }

    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/jpg'];
    if (!in_array($file['type'], $allowedTypes)) {
        error_log("INVALID FILE TYPE: " . $file['type']);
        return "Invalid file type. Only JPG, JPEG, PNG and GIF are allowed.";
    }

    // Prepare upload parameters
    $timestamp = time();
    $folder = 'student_images'; // Hardcoded for consistency
    $filename = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9.]/', '_', $file['name']);

    // Prepare signature
    $signature_string = "folder={$folder}&timestamp={$timestamp}" . CLOUDINARY_API_SECRET;
    $signature = hash('sha256', $signature_string);

    // Prepare cURL
    $url = "https://api.cloudinary.com/v1_1/" . CLOUDINARY_CLOUD_NAME . "/image/upload";

    // Prepare POST fields
    $postFields = [
        'file' => new CURLFile($file['tmp_name'], $file['type'], $filename),
        'api_key' => CLOUDINARY_API_KEY,
        'timestamp' => $timestamp,
        'signature' => $signature,
        'folder' => $folder,
        'upload_preset' => 'student_upload' // Ensure this preset exists in Cloudinary
    ];

    error_log("cURL Upload Parameters:");
    error_log("URL: " . $url);
    error_log("Timestamp: " . $timestamp);
    error_log("Signature: " . $signature);
    error_log("Folder: " . $folder);

    // Initialize cURL
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // Execute upload
    $response = curl_exec($ch);

    // Check for cURL errors
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        error_log("CURL ERROR: " . $error);
        return "Upload failed: " . $error;
    }

    // Close cURL
    curl_close($ch);

    // Parse response
    $result = json_decode($response, true);

    // Log full response
    error_log("CLOUDINARY RESPONSE:");
    error_log(print_r($result, true));

    // Validate upload result
    if (isset($result['secure_url'])) {
        error_log("===== CLOUDINARY UPLOAD SUCCESSFUL =====");
        return [
            'url' => $result['secure_url'],
            'public_id' => $result['public_id'],
            'format' => $result['format'],
            'original_filename' => $filename
        ];
    } else {
        // Log error details
        error_log("===== CLOUDINARY UPLOAD FAILED =====");
        $errorMessage = isset($result['error']['message'])
            ? $result['error']['message']
            : 'Unknown Cloudinary upload error';

        error_log("UPLOAD ERROR: " . $errorMessage);
        return "Upload failed: " . $errorMessage;
    }
}
