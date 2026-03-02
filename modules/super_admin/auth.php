<?php
// modules/super_admin/auth.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

$role = isset($_SESSION['role']) ? strtolower(trim($_SESSION['role'])) : '';
$tenant_id = $_SESSION['tenant_id'] ?? null;

// Middleware security check: Super Admins only
// Super Admins must have the 'super_admin' role and a NULL tenant_id
if ($role !== 'super_admin' || !is_null($tenant_id)) {
    echo "<script>
        alert('Access Denied: This area is restricted to Super Admins only.');
        window.location.href = '../tenant/dashboard.php';
    </script>";
    exit();
}

// Set a helper session variable for the module
$_SESSION['is_super_admin'] = true;
?>
