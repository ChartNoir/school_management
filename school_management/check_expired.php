<?php
require_once 'functions.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION['role'] = 'admin'; // For cron, simulate admin role
$result = checkAndUpdateExpiredAccounts();
error_log("Cron job result: " . json_encode($result));
?>
