<?php
/**
 * =====================================================
 * MASOOM'S ROLE & PERMISSION SYSTEM
 * Edit Role Page
 * =====================================================
 * 
 * This page allows editing existing roles and
 * managing their permissions.
 * 
 * Required Permissions: role.edit
 * =====================================================
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include required files
include_once(__DIR__ . '/../../config/db.php');
include_once(__DIR__ . '/../../core/tenant_middleware.php');
include_once(__DIR__ . '/../../core/permission_functions.php');
include_once(__DIR__ . '/../../core/role_functions.php');

// --- LAIBA: Audit Log Include ---
require_once(__DIR__ . '/../audit/audit.php');
$audit_obj = new AuditLog($db);

// Check if user has permission to edit roles
require_permission('role.edit');

$tenant_id = $_SESSION['tenant_id'];

// Get role ID from URL
$role_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($role_id <= 0) {
    header('Location: list_role.php?error=invalid_role');
    exit();
}

// Get the role details
$role = get_role_by_id($role_id, $tenant_id);

if (!$role) {
    header('Location: list_role.php?error=role_not_found');
    exit();
}

// Get all available permissions
$all_permissions = get_all_permissions();

// Get current permissions for this role
$current_permissions = get_role_permissions($role_id, $tenant_id);
$current_permission_ids = array_map(function($p) { return $p['id']; }, $current_permissions);

// Group permissions by category
$permissions_by_category = [];
foreach ($all_permissions as $perm) {
    $parts = explode('.', $perm['permission_key']);
    $category = $parts[0] ?? 'other';
    if (!isset($permissions_by_category[$category])) {
        $permissions_by_category[$category] = [];
    }
    $permissions_by_category[$category][] = $perm;
}

// Handle form submission
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role_name = isset($_POST['role_name']) ? trim($_POST['role_name']) : '';
    $role_description = isset($_POST['role_description']) ? trim($_POST['role_description']) : '';
    $selected_permissions = isset($_POST['permissions']) ? $_POST['permissions'] : [];
    
    // Validate input
    if (empty($role_name)) {
        $message = 'Role name is required.';
        $message_type = 'error';
    } else {
        // Update the role
        $result = update_role($role_id, $role_name, $role_description, $tenant_id);
        
        if ($result['success']) {

        // --- LAIBA: Audit Log Entry YAHAN DALEN ---
            $audit_msg = "Updated role: " . $role_name . " (ID: " . $role_id . ")";
            $audit_obj->log($_SESSION['user_id'], $audit_msg, "Roles");
            
            // Update permissions
            $perm_result = assign_permissions_to_role($role_id, $selected_permissions, $tenant_id);
            
            if ($perm_result['success']) {
                $message = 'Role updated successfully with ' . count($selected_permissions) . ' permissions!';
                $message_type = 'success';
                
                // Refresh role data
                $role = get_role_by_id($role_id, $tenant_id);
                $current_permissions = get_role_permissions($role_id, $tenant_id);
                $current_permission_ids = array_map(function($p) { return $p['id']; }, $current_permissions);
            } else {
                $message = 'Role updated but failed to update permissions: ' . $perm_result['message'];
                $message_type = 'error';
            }
        } else {
            $message = $result['message'];
            $message_type = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Role – SaaS Hub</title>
    <link rel="stylesheet" href="../../css/futuristic.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .dashboard-layout {
            display: flex;
            min-height: 100vh;
        }
        .sidebar {
            width: 250px;
            background: rgba(255, 255, 255, 0.02);
            border-right: 1px solid var(--glass-border);
            padding: 2rem;
            display: flex;
            flex-direction: column;
            gap: 1rem;
            backdrop-filter: blur(10px);
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }
        .sidebar a {
            color: var(--text-muted);
            text-decoration: none;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            transition: all 0.3s;
            font-weight: 500;
        }
        .sidebar a:hover, .sidebar a.active {
            background: var(--glass-bg);
            color: var(--text-main);
            border-left: 3px solid var(--primary);
        }
        .main-content {
            flex: 1;
            padding: 3rem;
            position: relative;
            margin-left: 250px;
            max-width: 900px;
        }
        .dashboard-panel {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--glass-shadow);
        }
        .role-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--glass-border);
        }
        .role-header h2 {
            margin: 0;
            font-size: 1.5rem;
        }
        .system-badge {
            background: rgba(155, 89, 182, 0.1);
            color: #9b59b6;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            border: 1px solid rgba(155, 89, 182, 0.2);
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--text-muted);
            font-weight: 500;
        }
        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--glass-border);
            border-radius: 8px;
            color: var(--text-main);
            font-family: inherit;
            font-size: 1rem;
        }
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 2px var(--primary-glow);
        }
        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            color: #f87171;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }
        .alert-success {
            background: rgba(34, 197, 94, 0.1);
            color: #4ade80;
            border: 1px solid rgba(34, 197, 94, 0.2);
        }
        .permission-category {
            margin-bottom: 1.5rem;
        }
        .permission-category h3 {
            font-size: 1rem;
            color: var(--text-main);
            margin-bottom: 0.75rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--glass-border);
            text-transform: capitalize;
        }
        .permission-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 0.5rem;
        }
        .permission-checkbox {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem;
            border-radius: 6px;
            cursor: pointer;
            transition: background 0.2s;
        }
        .permission-checkbox:hover {
            background: rgba(255, 255, 255, 0.05);
        }
        .permission-checkbox input {
            width: 18px;
            height: 18px;
            accent-color: var(--primary);
        }
        .permission-checkbox span {
            font-size: 0.9rem;
            color: var(--text-muted);
        }
        .premium-badge {
            background: linear-gradient(135deg, #f39c12, #e74c3c);
            color: white;
            font-size: 0.7rem;
            padding: 2px 6px;
            border-radius: 4px;
            margin-left: 4px;
        }
        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            text-decoration: none;
            font-size: 1rem;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
        }
        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: var(--text-muted);
            border: 1px solid var(--glass-border);
        }
        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.15);
        }
        .btn-group {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }
        .current-perms {
            background: rgba(52, 152, 219, 0.1);
            border: 1px solid rgba(52, 152, 219, 0.2);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
        .current-perms h4 {
            margin: 0 0 0.5rem 0;
            color: #3498db;
            font-size: 0.9rem;
        }
        .current-perms p {
            margin: 0;
            color: var(--text-muted);
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="blob-bg blob-1"></div>
    <div class="blob-bg blob-2"></div>
    
    <div class="dashboard-layout">
        <!-- Sidebar Navigation -->
        <aside class="sidebar">
            <div class="logo" style="margin-bottom: 2rem; font-size: 1.25rem;">
                <span class="logo-icon">💠</span>
                <span class="logo-text">SaaS Hub</span>
            </div>
            <a href="../tenant/dashboard.php">Dashboard</a>
            <a href="../user/list_user.php">Manage Users</a>
            <a href="list_role.php" class="active">Role Management</a>
            <?php if (can('subscription.view')): ?>
            <a href="../subscription/status.php">Subscription Status</a>
            <?php endif; ?>
            <?php if (can('audit.view')): ?>
            <a href="../audit/audit_view.php">Audit Logs</a>
            <?php endif; ?>
            <a href="../../core/auth.php?logout=true" style="margin-top: auto;">Logout</a>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="role-header">
                <h2>Edit Role</h2>
                <?php if (!empty($role['is_system_role'])): ?>
                <span class="system-badge">System Role</span>
                <?php endif; ?>
            </div>
            
            <p class="hero-subtitle" style="text-align: left; margin-bottom: 1.5rem;">
                Editing: <strong><?php echo htmlspecialchars($role['role_name']); ?></strong>
            </p>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="dashboard-panel">
                <div class="current-perms">
                    <h4><i class="fa-solid fa-info-circle"></i> Current Permissions</h4>
                    <p>This role currently has <strong><?php echo count($current_permissions); ?></strong> permissions assigned.</p>
                </div>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="role_name">Role Name *</label>
                        <input type="text" id="role_name" name="role_name" class="form-control" 
                               placeholder="e.g., Supervisor, Team Lead" required
                               value="<?php echo htmlspecialchars($role['role_name']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="role_description">Description</label>
                        <textarea id="role_description" name="role_description" class="form-control" 
                                  placeholder="Describe what this role can do..."><?php echo htmlspecialchars($role['role_description'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Assign Permissions</label>
                        <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 1rem;">
                            Select the permissions this role should have. Current selections are checked.
                        </p>
                        
                        <?php if (!empty($permissions_by_category)): ?>
                            <?php foreach ($permissions_by_category as $category => $perms): ?>
                                <div class="permission-category">
                                    <h3><?php echo htmlspecialchars($category); ?></h3>
                                    <div class="permission-grid">
                                        <?php foreach ($perms as $perm): ?>
                                            <label class="permission-checkbox">
                                                <input type="checkbox" name="permissions[]" 
                                                       value="<?php echo $perm['id']; ?>"
                                                       <?php echo in_array($perm['id'], $current_permission_ids) ? 'checked' : ''; ?>>
                                                <span><?php echo htmlspecialchars($perm['permission_key']); ?></span>
                                                <?php if (!empty($perm['is_premium'])): ?>
                                                <span class="premium-badge">Premium</span>
                                                <?php endif; ?>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="color: var(--text-muted);">No permissions available. Please contact administrator.</p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="btn-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fa-solid fa-check"></i> Update Role
                        </button>
                        <a href="list_role.php" class="btn btn-secondary">
                            <i class="fa-solid fa-xmark"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </main>
    </div>
</body>
</html>

