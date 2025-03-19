<?php
$stored_hash = '$2y$10$GUvIvJzMlpfgw5EjKEBPu.aODOe/X3DVd/QGxMbpTWCgRt1/lUgmO';
$test_password = 'admin12345';

if (password_verify($test_password, $stored_hash)) {
    echo "Password 'admin12345' matches the hash!";
} else {
    echo "Password 'admin12345' does NOT match the hash.";
}
?>
