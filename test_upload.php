<?php
session_start();
require_once 'config.php';
require_once 'cloudinary.php';

// Only provide the form if not uploaded yet
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['test_image']) || $_FILES['test_image']['error'] !== 0) {
?>
    <!DOCTYPE html>
    <html>

    <head>
        <title>Cloudinary Test Upload</title>
    </head>

    <body>
        <h1>Test Image Upload</h1>
        <form method="POST" enctype="multipart/form-data">
            <div>
                <label>Test Image:</label>
                <input type="file" name="test_image" accept="image/jpeg,image/png,image/gif" required>
            </div>
            <button type="submit">Upload Test Image</button>
        </form>
    </body>

    </html>
<?php
    exit;
}

// Process upload
echo "<h1>Upload Test Results</h1>";

// Print the file information
echo "<h2>File Information:</h2>";
echo "<pre>";
print_r($_FILES['test_image']);
echo "</pre>";

// Try uploading to Cloudinary
echo "<h2>Cloudinary Upload:</h2>";
$uploadResult = uploadToCloudinary($_FILES['test_image'], 'test_uploads');

echo "<pre>";
print_r($uploadResult);
echo "</pre>";

// If successful, try saving to database
if (is_array($uploadResult) && isset($uploadResult['url'])) {
    echo "<h2>Database Update Test:</h2>";

    try {
        $testUserId = 1; // Change this to an existing user ID

        // Get current image URL
        $stmt = $pdo->prepare("SELECT username, profile_image FROM users WHERE id = ?");
        $stmt->execute([$testUserId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        echo "Before update: User '" . ($user['username'] ?? 'Unknown') . "' has profile image: " . ($user['profile_image'] ?? 'NULL') . "<br>";

        // Update with the new Cloudinary URL
        $stmt = $pdo->prepare("UPDATE users SET profile_image = ? WHERE id = ?");
        $updateResult = $stmt->execute([$uploadResult['url'], $testUserId]);

        echo "Update result: " . ($updateResult ? "Success" : "Failed") . "<br>";
        if (!$updateResult) {
            print_r($stmt->errorInfo());
        } else {
            // Verify the update
            $stmt = $pdo->prepare("SELECT profile_image FROM users WHERE id = ?");
            $stmt->execute([$testUserId]);
            $newImageUrl = $stmt->fetchColumn();

            echo "After update: Profile image is now: " . ($newImageUrl ?? 'NULL') . "<br>";

            if ($newImageUrl === $uploadResult['url']) {
                echo "<strong style='color: green;'>SUCCESS: Database update confirmed!</strong>";
            } else {
                echo "<strong style='color: red;'>ERROR: Database update failed - stored value doesn't match!</strong>";
            }
        }
    } catch (PDOException $e) {
        echo "Database error: " . $e->getMessage() . "<br>";
    }
}
