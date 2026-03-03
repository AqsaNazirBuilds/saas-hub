<?php 
include(__DIR__ . '/../subscription/check_access.php'); 
require_once(__DIR__ . '/../../config/db.php');
require_once(__DIR__ . '/audit.php');

$audit_obj = new AuditLog($db);

// Logic for Search and Filters
$search = $_GET['search'] ?? '';
$module = $_GET['module'] ?? '';
$tenant_id = $_SESSION['tenant_id'];
$is_super_admin = $_SESSION['is_super_admin'] ?? false;

// Query building
$query = "SELECT * FROM audit_logs WHERE 1=1";

// --- LAIBA: Security Fix ---
if (!$is_super_admin) {
    $query .= " AND tenant_id = $tenant_id";
}

if ($search) {
    $search_safe = $db->real_escape_string($search);
    $query .= " AND action LIKE '%$search_safe%'";
}

if ($module) {
    $module_safe = $db->real_escape_string($module);
    $query .= " AND module = '$module_safe'";
}

$query .= " ORDER BY created_at DESC";
$logs = $db->query($query);

// Plan check for Export Feature
$current_plan = $_SESSION['plan_name'] ?? 'Basic'; 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Audit Logs | Saas Project</title>
    
     <link rel="stylesheet" href="<?php echo BASE_URL; ?>css/laiba/audit_view.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>

<?php include(__DIR__ . '/../subscription/sidebar.php'); ?>

<div class="main-wrapper"> 
    <div class="status-card">
        
        <div class="card-header">
            <div class="header-left">
                <h2><i class="fas fa-history"></i> System Activity Logs</h2>
                <p>Track all changes and actions performed</p>
            </div>
        </div>

        <div class="filter-bar">
            <form method="GET" action="<?php echo BASE_URL; ?>modules/audit/audit_view.php" class="filter-form">
                <div class="search-input-group">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" class="search-field" placeholder="Search logs..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <select name="module" class="filter-select">
    <option value="">All Modules</option>
    <option value="Subscription" <?php if($module == 'Subscription') echo 'selected'; ?>>Subscription</option>
    <option value="Users" <?php if($module == 'Users') echo 'selected'; ?>>Users</option>
    <option value="Auth" <?php if($module == 'Auth') echo 'selected'; ?>>Auth</option>
    <option value="Audit" <?php if($module == 'Audit') echo 'selected'; ?>>Audit</option>
</select>  
                <button type="submit" class="btn-filter">Filter</button>
                
                <?php if($current_plan === 'Premium'): ?>
                    <a href="<?php echo BASE_URL; ?>modules/audit/export_audit.php" class="btn-download" style="background: #22c55e; color: white; padding: 10px 15px; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 14px; display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-file-excel"></i> Export
                    </a>
                <?php else: ?>
                    <button type="button" class="btn-download" onclick="alert('Export feature is only for Premium Users!')" style="background: #94a3b8; color: white; padding: 10px 15px; border-radius: 6px; border:none; cursor: not-allowed; font-weight: 600; font-size: 14px; display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-lock"></i> Export (Premium)
                    </button>
                <?php endif; ?>
                </form>
        </div>

        <div class="table-container">
            <table class="audit-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Module</th>
                        <th>Timestamp</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($logs && $logs->num_rows > 0): ?>
                        <?php while($row = $logs->fetch_assoc()): ?>
                        <tr>
                            <td class="badge-id">#<?php echo $row['id']; ?></td>
                            <td><strong>User ID: <?php echo $row['user_id']; ?></strong></td>
                            <td class="action-text"><?php echo htmlspecialchars($row['action']); ?></td>
                            <td><span class="badge-module"><?php echo htmlspecialchars($row['module']); ?></span></td>
                            <td class="timestamp"><i class="far fa-clock"></i> <?php echo $row['created_at']; ?></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5" style="text-align:center; padding: 20px;">No records found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</body>
</html>