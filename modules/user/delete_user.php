<?php
session_start();
require_once '../../config/db.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['tenant_id'])) {
    header("Location: ../../login.php");
    exit();
}

$tenant_id = $_SESSION['tenant_id'];

if (isset($_GET['id'])) {
    $user_id_to_delete = intval($_GET['id']);

    // Safety check: Cannot delete yourself
    if ($user_id_to_delete === intval($_SESSION['user_id'])) {
        echo "<script>alert('You cannot delete your own account.'); window.location.href = 'list_user.php';</script>";
        exit();
    }

    // Verify user belongs to the same tenant_id before deletion
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND tenant_id = ?");
    $stmt->bind_param("ii", $user_id_to_delete, $tenant_id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            // Delete successful, UI User limits will automatically re-evaluate upon redirection because COUNT(id) runs on list_user.php load
            echo "<script>window.location.href = 'list_user.php';</script>";
        }
        else {
            // No rows affected might mean the user ID doesn't exist or doesn't belong to this tenant
            echo "<script>alert('Error: User not found or you do not have permission to delete this user.'); window.location.href = 'list_user.php';</script>";
        }
    }
    else {
        echo "<script>alert('Server Error. Could not delete user.'); window.location.href = 'list_user.php';</script>";
    }
}
else {
    // Redirection if accessed directly without an ID parameter
    header("Location: list_user.php");
    exit();
}
?>