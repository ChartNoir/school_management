<?php
// Cloudinary integration file

// Cloudinary configuration
define('CLOUDINARY_CLOUD_NAME', 'dxmjvgg1y'); // Replace with your Cloudinary cloud name
define('CLOUDINARY_API_KEY', '881954448675579'); // Replace with your Cloudinary API key
define('CLOUDINARY_API_SECRET', 'huYsbjoK7dMg7ATI2uPUGmBFSQE'); // Replace with your Cloudinary API secret

/**
 * Upload an image to Cloudinary using unsigned upload
 * 
 * @param array $file $_FILES array element
 * @param string $folder Cloudinary folder to store the image in
 * @return array|string Array with upload details on success, error message on failure
 */
function uploadToCloudinary($file, $folder = 'school_management')
{
    // Check if file exists and has no errors
    if (!isset($file) || $file['error'] != 0) {
        return "File upload error: " . ($file['error'] ?? 'No file provided');
    }

    // Validate file type is an image
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/jpg'];
    if (!in_array($file['type'], $allowedTypes)) {
        return "Invalid file type. Only JPG, JPEG, PNG and GIF are allowed.";
    }

    // Validate file size (limit to 5MB)
    if ($file['size'] > 5 * 1024 * 1024) {
        return "File too large. Maximum size is 5MB.";
    }

    // Prepare unique filename
    $filename = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9.]/', '_', $file['name']);

    // Prepare API URL for unsigned upload using an upload preset
    $cloudName = CLOUDINARY_CLOUD_NAME;
    $uploadPreset = 'student_upload'; // Create this unsigned upload preset in your Cloudinary dashboard

    $url = "https://api.cloudinary.com/v1_1/$cloudName/image/upload";

    // Initialize cURL
    $ch = curl_init($url);

    // Setup cURL options for file upload
    curl_setopt($ch, CURLOPT_POST, true);

    // Create simple form data
    $postFields = [
        'file' => new CURLFile($file['tmp_name'], $file['type'], $filename),
        'upload_preset' => $uploadPreset,
        'folder' => $folder
    ];

    curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // Execute cURL session
    $response = curl_exec($ch);

    // Log cURL info for debugging
    $info = curl_getinfo($ch);
    error_log("Cloudinary upload HTTP status: " . $info['http_code']);

    // Check for cURL errors
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        error_log("Cloudinary upload cURL error: " . $error);
        return "Upload error: $error";
    }

    // Close cURL session
    curl_close($ch);

    // Debug the raw response
    error_log("Cloudinary raw response: " . $response);

    // Parse response
    $result = json_decode($response, true);

    // Check if the result is valid JSON
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("Cloudinary response is not valid JSON: " . json_last_error_msg());
        return "Invalid response from Cloudinary server";
    }

    // Log the JSON decoded result
    error_log("Cloudinary decoded result: " . print_r($result, true));

    // Return secure URL if upload successful
    if (isset($result['secure_url'])) {
        return [
            'url' => $result['secure_url'],
            'public_id' => $result['public_id'],
            'format' => $result['format'],
            'original_filename' => $result['original_filename'],
        ];
    } else {
        return "Upload failed: " . (isset($result['error']) ? $result['error']['message'] : 'Unknown error');
    }
}

/**
 * Delete an image from Cloudinary
 * 
 * @param string $public_id Public ID of the image to delete
 * @return bool True on success, false on failure
 */
function deleteFromCloudinary($public_id)
{
    // Prepare API URL
    $url = 'https://api.cloudinary.com/v1_1/' . CLOUDINARY_CLOUD_NAME . '/image/destroy';

    // Prepare API parameters
    $timestamp = time();
    $to_sign = "public_id=$public_id&timestamp=$timestamp" . CLOUDINARY_API_SECRET;
    $signature = sha1($to_sign);

    $data = [
        'public_id' => $public_id,
        'api_key' => CLOUDINARY_API_KEY,
        'timestamp' => $timestamp,
        'signature' => $signature
    ];

    // Initialize cURL session
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // Execute cURL request
    $response = curl_exec($ch);

    // Close cURL session
    curl_close($ch);

    // Parse response
    $result = json_decode($response, true);

    return isset($result['result']) && $result['result'] === 'ok';
}
