<?php
session_start();
require_once(__DIR__ . '/../../config/db.php');
require_once(__DIR__ . '/audit.php');

if (isset($_POST['action']) && isset($_SESSION['user_id'])) {
    $audit_obj = new AuditLog($db);
    
    // Yahan hum database mein entry kar rahe hain
    $result = $audit_obj->logAction(
        $_SESSION['user_id'], 
        $_POST['action'], 
        $_POST['module'], 
        $_SESSION['tenant_id'] ?? 0
    );

    if($result) {
        echo "success";
    } else {
        echo "error_db";
    }
} else {
    echo "missing_data";
}
?>