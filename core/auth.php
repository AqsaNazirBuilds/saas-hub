<?php
session_start();
include_once(__DIR__ . '/../config/db.php');

// Handle login POST from login.php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        header("Location: " . BASE_URL . "login.php?error=1");
        exit();
    }

    $stmt = $conn->prepare("SELECT id, tenant_id, password, role FROM users WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id']    = $user['id'];
        $_SESSION['tenant_id']  = $user['tenant_id'];
        $_SESSION['user_email'] = $email;

        $role = isset($user['role']) ? strtolower((string)$user['role']) : '';

        if ($role === 'super_admin') {
            $_SESSION['is_super_admin'] = true;
            header("Location: " . BASE_URL . "modules/admin/dashboard.php");
            exit();
        } elseif ($role === 'tenant_admin') {
            header("Location: " . BASE_URL . "modules/tenant/manage.php");
            exit();
        }

        header("Location: " . BASE_URL . "modules/tenant/manage.php");
        exit();
    }

    // Invalid credentials
    header("Location: " . BASE_URL . "login.php?error=1");
    exit();
}

// If someone opens auth.php directly, just send them to login instead of a blank page.
header("Location: " . BASE_URL . "login.php");
exit();