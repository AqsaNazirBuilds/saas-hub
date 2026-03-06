<?php
// modules/audit/export_audit.php
require_once(__DIR__ . '/../../config/db.php');
// --- LAIBA: Audit class include ki ---
require_once(__DIR__ . '/audit.php');

// Security Check
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. LOGIN & PREMIUM CHECK
if (!isset($_SESSION['user_id'])) {
    die("Access Denied: Please login first.");
}

if (!isset($_SESSION['plan_name']) || $_SESSION['plan_name'] !== 'Premium') {
    die("Unauthorized Access: Export feature is only available for Premium Plan users.");
}

$tenant_id = $_SESSION['tenant_id'];
$user_id = $_SESSION['user_id'];
$is_super_admin = $_SESSION['is_super_admin'] ?? false;

// --- LAIBA: Log generate kiya ke is bande ne export kiya hai ---
$audit_obj = new AuditLog($db);
$audit_obj->logAction($user_id, "Exported Audit Logs as CSV/Excel", "Audit", $tenant_id);

// 2. PURANI OUTPUT CLEAN KAREIN
if (ob_get_length()) ob_end_clean();

// 3. DATABASE SE DATA NIKALNA
$query = "SELECT id, user_id, action, module, created_at FROM audit_logs WHERE 1=1";

if (!$is_super_admin) {
    $query .= " AND tenant_id = $tenant_id";
}

$query .= " ORDER BY created_at DESC";
$result = $db->query($query);

// 4. BROWSER HEADERS
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=audit_report_' . date('Y-m-d') . '.csv');
header('Pragma: no-cache');
header('Expires: 0');

// 5. CSV WRITING
$output = fopen('php://output', 'w');

// Headings
fputcsv($output, array('Log ID', 'User ID', 'Action Performed', 'Module Name', 'Date Time'));

// Data Rows
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, array(
            $row['id'],
            $row['user_id'],
            $row['action'],
            $row['module'],
            $row['created_at']
        ));
    }
}

fclose($output);
exit();
?>