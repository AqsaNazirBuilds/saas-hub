<?php
session_start();
include('../../config/db.php');

// --- LAIBA: Audit log include kiya ---
require_once '../audit/audit.php';
$audit_obj = new AuditLog($conn);

// Only Super Admin should use this controller
if (empty($_SESSION['is_super_admin'])) {
    http_response_code(403);
    die('Access denied: Super Admin only.');
}

// Check karein ke kya ID aur Action URL mein mojood hain
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $action = $_GET['action'];

    if ($action == 'suspend') {
        // Tenants table mein status ko 'suspended' kar dein
        $stmt = $conn->prepare("UPDATE tenants SET status = 'suspended' WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        
        // --- LAIBA: Suspend ka log yahan add kiya ---
        $audit_obj->logAction($_SESSION['user_id'], "Super Admin suspended Tenant ID: $id", "SuperAdmin_Action", 0);
        
    } elseif ($action == 'delete') {
        // Tenant ko delete karna (Foreign keys ki wajah se users/subs bhi delete ho jayenge)
        $stmt = $conn->prepare("DELETE FROM tenants WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();

        // --- LAIBA: Delete ka log yahan add kiya ---
        $audit_obj->logAction($_SESSION['user_id'], "Super Admin DELETED Tenant ID: $id", "SuperAdmin_Action", 0);

    } elseif ($action == 'subscription') {
        // Impersonate this tenant to view Laiba's subscription status dashboard
        $_SESSION['tenant_id'] = $id;
        header("Location: " . BASE_URL . "modules/subscription/status.php");
        exit();
    }

    // Wapas list page par bhej dein
    header("Location: super_admin_list.php?msg=success");
    exit();
}
?>