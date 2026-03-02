<?php
session_start();
include_once(__DIR__ . '/../config/db.php'); // Database connection

/**
 * Yeh function check karega ke user logged in hai aur uske paas valid tenant_id hai.
 */
function protect_tenant_access() {
    // 1. Must be logged in
    if (!isset($_SESSION['user_id'])) {
        header("Location: " . BASE_URL . "login.php");
        exit();
    }

    // 2. Super Admin: tenant_id optional / not required
    if (isset($_SESSION['is_super_admin']) && $_SESSION['is_super_admin'] === true) {
        return null; // Super Admin sab dekh sakta hai (no hard tenant scope)
    }

    // 3. Normal tenant users must have tenant_id
    if (!isset($_SESSION['tenant_id'])) {
        header("Location: " . BASE_URL . "login.php");
        exit();
    }

    return $_SESSION['tenant_id'];
}

/**
 * Team ke liye Helper Function: Har SQL query mein automatic tenant filter lagane ke liye.
 * Isay Masoom (Roles) aur Laiba (Logs/Subs) dono use karein gi.
 */
function apply_tenant_scope($query, $is_first_condition = false) {
    // Super Admin ke liye koi automatic tenant filter nahi lagana
    if (isset($_SESSION['is_super_admin']) && $_SESSION['is_super_admin'] === true) {
        return $query;
    }

    $tenant_id = $_SESSION['tenant_id'];
    
    // Agar query mein pehlay se WHERE clause hai toh AND lagayein
    if ($is_first_condition) {
        return $query . " WHERE tenant_id = $tenant_id";
    } else {
        return $query . " AND tenant_id = $tenant_id";
    }
}

/**
 * URL Tampering Protection:
 * Agar koi user URL mein ?id=5 likh kar kisi aur ka record kholnay ki koshish kare.
 */
function check_record_owner($table_name, $record_id) {
    global $conn;
    $tenant_id = $_SESSION['tenant_id'];

    $stmt = $conn->prepare("SELECT id FROM $table_name WHERE id = ? AND tenant_id = ?");
    $stmt->bind_param("ii", $record_id, $tenant_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        // Log unauthorized attempt (Laiba's Audit Log system can use this)
        die("Security Alert: You do not have permission to access this record."); 
    }
}

// Global execution
$current_tenant_id = protect_tenant_access();
?>