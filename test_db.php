<?php
require_once 'config.php';

try {
    // Check the current database
    $current_db = $pdo->query("SELECT DATABASE()")->fetchColumn();
    echo "Current database: $current_db<br>";

    // Test the DESCRIBE query
    $stmt = $pdo->query("DESCRIBE users");
    $fields = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    echo "Fields in 'users' table: <pre>";
    print_r($fields);
    echo "</pre>";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
