<?php
session_start();
require_once 'functions.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

// Verify CSRF token
if (!verifyCsrfToken($_GET['csrf_token'] ?? '')) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

// Get export parameters
$table = $_GET['table'] ?? '';
$format = $_GET['format'] ?? 'csv';
$fields = isset($_GET['fields']) ? explode(',', $_GET['fields']) : [];

// Validate table name
$valid_tables = [
    'users' => 'users',
    'student_details' => 'student_details',
    'teacher_details' => 'teacher_details',
    'courses' => 'courses',
    'class' => 'class',
    'enrollments' => 'enrollments',
    'grades' => 'grades'
];

if (!isset($valid_tables[$table])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid table']);
    exit;
}

try {
    // Apply any filters (similar to showTable function)
    $query = "SELECT * FROM " . $valid_tables[$table];
    $params = [];

    // Apply is_active filter for users table if provided
    if ($table === 'users' && isset($_GET['is_active']) && $_GET['is_active'] !== '') {
        $isActive = filter_var($_GET['is_active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($isActive !== null) {
            $query .= " WHERE is_active = ?";
            $params[] = $isActive;
        }
    }

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // If no data is found
    if (empty($data)) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'No data found']);
        exit;
    }

    // Filter fields if specified
    if (!empty($fields)) {
        $filtered_data = [];
        foreach ($data as $row) {
            $filtered_row = [];
            foreach ($fields as $field) {
                if (isset($row[$field])) {
                    $filtered_row[$field] = $row[$field];
                }
            }
            $filtered_data[] = $filtered_row;
        }
        $data = $filtered_data;
    }

    // Remove sensitive fields
    $sensitive_fields = ['password', 'password_reset_token', 'password_reset_expiry'];
    foreach ($data as &$row) {
        foreach ($sensitive_fields as $field) {
            if (isset($row[$field])) {
                unset($row[$field]);
            }
        }
    }

    // Generate filename
    $timestamp = date('Y-m-d_H-i-s');
    $filename = $table . '_export_' . $timestamp;

    // Export based on format
    if ($format === 'csv') {
        // CSV Export
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');

        $output = fopen('php://output', 'w');

        // Write header row
        if (!empty($data)) {
            fputcsv($output, array_keys($data[0]));
        }

        // Write data rows
        foreach ($data as $row) {
            fputcsv($output, $row);
        }

        fclose($output);
    } elseif ($format === 'excel') {
        // Excel Export (actually CSV with Excel MIME type)
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="' . $filename . '.xls"');

        echo "<table border='1'>\n";

        // Output header row
        if (!empty($data)) {
            echo "<tr>";
            foreach (array_keys($data[0]) as $header) {
                echo "<th>" . htmlspecialchars($header) . "</th>";
            }
            echo "</tr>\n";
        }

        // Output data rows
        foreach ($data as $row) {
            echo "<tr>";
            foreach ($row as $cell) {
                echo "<td>" . htmlspecialchars($cell ?? '') . "</td>";
            }
            echo "</tr>\n";
        }

        echo "</table>";
    } else {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Invalid export format']);
    }
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    exit;
}
