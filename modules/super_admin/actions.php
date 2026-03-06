<?php
// modules/super_admin/actions.php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../../config/db.php';

// --- LAIBA: Audit Log yahan add karein ---
require_once __DIR__ . '/../../modules/audit/audit.php'; 
$audit_obj = new AuditLog($conn);

$action = $_GET['action'] ?? '';
$tenant_id = (int)($_GET['id'] ?? 0);

if ($tenant_id <= 0) {
    header("Location: dashboard.php?error=Invalid tenant ID");
    exit();
}

if ($action === 'suspend') {
    // Toggle status between active and suspended
    $stmt = $conn->prepare("SELECT status FROM tenants WHERE id = ?");
    $stmt->bind_param("i", $tenant_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();

    $new_status = ($res['status'] === 'active') ? 'suspended' : 'active';

    $update = $conn->prepare("UPDATE tenants SET status = ? WHERE id = ?");
    $update->bind_param("si", $new_status, $tenant_id);
    if ($update->execute()) {

    // --- LOG ENTRY FOR STATUS CHANGE ---
        $audit_msg = "Super Admin changed Tenant ID: $tenant_id status to $new_status";
        $audit_obj->log($_SESSION['user_id'], $audit_msg, "SuperAdmin");

        header("Location: dashboard.php?success=Tenant status updated to " . $new_status);
    }
    else {
        header("Location: dashboard.php?error=Failed to update status");
    }
    exit();
}

if ($action === 'delete') {
    // Delete tenant (and cascade deletes if DB is configured properly)
    $stmt = $conn->prepare("DELETE FROM tenants WHERE id = ?");
    $stmt->bind_param("i", $tenant_id);
    if ($stmt->execute()) {

      // --- LOG ENTRY FOR DELETE ---
        $audit_msg = "Super Admin DELETED Tenant ID: $tenant_id";
        $audit_obj->log($_SESSION['user_id'], $audit_msg, "SuperAdmin");
        
        header("Location: dashboard.php?success=Tenant deleted successfully");
    }
    else {
        header("Location: dashboard.php?error=Failed to delete tenant");
    }
    exit();
}

header("Location: dashboard.php");
exit();
