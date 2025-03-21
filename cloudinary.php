<?php
// Cloudinary configuration
define('CLOUDINARY_CLOUD_NAME', 'dxmjvgg1y');
define('CLOUDINARY_API_KEY', '881954448675579');
define('CLOUDINARY_API_SECRET', 'huYsbjoK7dMg7ATI2uPUGmBFSQE');

/**
 * Upload an image to Cloudinary with comprehensive error handling and logging
 * 
 * @param array $file File upload array from $_FILES
 * @param string $folder Cloudinary folder to upload to
 * @return string|null Secure URL of uploaded image or null on failure
 */
function uploadToCloudinary($file, $folder = 'student_images')
{
    // Extensive logging for debugging
    error_log("===== CLOUDINARY UPLOAD DEBUG START =====");

    // Validate file existence and initial checks
    if (!isset($file) || empty($file['tmp_name'])) {
        error_log("NO FILE PROVIDED TO UPLOAD FUNCTION");
        return null;
    }

    // Log detailed file information
    error_log("File Details:");
    error_log("Temp Name: " . $file['tmp_name']);
    error_log("Original Name: " . $file['name']);
    error_log("File Size: " . $file['size']);
    error_log("File Type: " . $file['type']);
    error_log("File Error Code: " . $file['error']);

    // Check file upload error
    if ($file['error'] !== UPLOAD_ERR_OK) {
        error_log("FILE UPLOAD ERROR: " . $file['error']);
        return null;
    }

    // Validate file size and type
    $maxFileSize = 5 * 1024 * 1024; // 5MB
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/jpg'];

    if ($file['size'] > $maxFileSize) {
        error_log("FILE TOO LARGE: " . $file['size'] . " bytes");
        return null;
    }

    if (!in_array($file['type'], $allowedTypes)) {
        error_log("INVALID FILE TYPE: " . $file['type']);
        return null;
    }

    // Check if file actually exists and is readable
    if (!is_uploaded_file($file['tmp_name'])) {
        error_log("INVALID UPLOADED FILE: Not a valid uploaded file");
        return null;
    }

    try {
        // Prepare multipart form data manually
        $boundary = '--------------------------' . microtime(true);

        // Prepare the POST fields
        $body = '';

        // Add upload preset
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"upload_preset\"\r\n\r\n";
        $body .= "ml_default\r\n";

        // Add folder
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"folder\"\r\n\r\n";
        $body .= "{$folder}\r\n";

        // Add file
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"file\"; filename=\"" . $file['name'] . "\"\r\n";
        $body .= "Content-Type: " . $file['type'] . "\r\n\r\n";
        $body .= file_get_contents($file['tmp_name']) . "\r\n";

        // Close boundary
        $body .= "--{$boundary}--\r\n";

        // Prepare cURL
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => "https://api.cloudinary.com/v1_1/" . CLOUDINARY_CLOUD_NAME . "/image/upload",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                "Content-Type: multipart/form-data; boundary={$boundary}",
            ],
            CURLOPT_POSTFIELDS => $body,
        ]);

        // Execute upload
        $response = curl_exec($ch);

        // Check for cURL errors
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            error_log("CURL ERROR: " . $error);
            curl_close($ch);
            return null;
        }

        // Get HTTP status code
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        error_log("HTTP Response Code: " . $httpCode);

        // Close cURL
        curl_close($ch);

        // Parse response
        $result = json_decode($response, true);

        // Log full response for debugging
        error_log("FULL CLOUDINARY RESPONSE:");
        error_log(print_r($result, true));

        // Validate upload result
        if (isset($result['secure_url'])) {
            error_log("===== CLOUDINARY UPLOAD SUCCESSFUL =====");
            error_log("Uploaded Image URL: " . $result['secure_url']);
            return $result['secure_url'];
        } else {
            // Log error details
            error_log("===== CLOUDINARY UPLOAD FAILED =====");
            $errorMessage = isset($result['error']['message'])
                ? $result['error']['message']
                : 'Unknown Cloudinary upload error';

            error_log("UPLOAD ERROR: " . $errorMessage);
            return null;
        }
    } catch (Exception $e) {
        // Catch any unexpected errors
        error_log("UNEXPECTED CLOUDINARY UPLOAD ERROR: " . $e->getMessage());
        return null;
    }
}
/**
 * Extract public ID from Cloudinary URL
 * 
 * @param string $url Cloudinary image URL
 * @return string|null Public ID or null if extraction fails
 */
function extractCloudinaryPublicId($url)
{
    if (empty($url)) return null;

    // Regex pattern to extract public ID from Cloudinary URL
    $pattern = '/\/v\d+\/([^\/]+)\/([^\/\.]+)/';

    if (preg_match($pattern, $url, $matches)) {
        return $matches[1] . '/' . $matches[2];
    }

    return null;
}
