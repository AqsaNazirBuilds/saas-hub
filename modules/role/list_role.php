<?php
/**
 * =====================================================
 * MASOOM'S ROLE & PERMISSION SYSTEM
 * Role List Page
 * =====================================================
 * 
 * This page displays all roles for the current tenant
 * with their permissions and user counts.
 * 
 * Required Permissions: role.view
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

// Check if user has permission to view roles
require_permission('role.view');

$tenant_id = $_SESSION['tenant_id'];

// Get all roles for this tenant
$roles = get_roles_by_tenant($tenant_id);

// Handle role deletion request
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $role_id = isset($_POST['role_id']) ? (int)$_POST['role_id'] : 0;
    
    if ($role_id > 0) {
        $result = delete_role($role_id, $tenant_id);
        $message = $result['message'];
        $message_type = $result['success'] ? 'success' : 'error';
        
        // Refresh roles list
        $roles = get_roles_by_tenant($tenant_id);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Role Management – SaaS Hub</title>
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
        }
        .dashboard-panel {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--glass-shadow);
        }
        .role-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        .role-table th, .role-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--glass-border);
        }
        .role-table th {
            color: var(--text-muted);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
        }
        .role-badge {
            background: rgba(52, 152, 219, 0.1);
            color: #3498db;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            border: 1px solid rgba(52, 152, 219, 0.2);
            display: inline-block;
            margin: 2px;
        }
        .system-badge {
            background: rgba(155, 89, 182, 0.1);
            color: #9b59b6;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            border: 1px solid rgba(155, 89, 182, 0.2);
            margin-left: 8px;
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
        .action-btns {
            display: flex;
            gap: 0.5rem;
        }
        .btn {
            padding: 0.5rem 1rem;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.85rem;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
        }
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
        }
        .btn-danger {
            background: rgba(239, 68, 68, 0.2);
            color: #f87171;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }
        .btn-danger:hover {
            background: rgba(239, 68, 68, 0.3);
        }
        .btn-edit {
            background: rgba(52, 152, 219, 0.2);
            color: #3498db;
            border: 1px solid rgba(52, 152, 219, 0.3);
        }
        .btn-edit:hover {
            background: rgba(52, 152, 219, 0.3);
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
            <?php if (can('reports.basic')): ?>
            <a href="../subscription/reports.php">Reports</a>
            <?php endif; ?>
            <a href="../../core/auth.php?logout=true" style="margin-top: auto;">Logout</a>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <h1 class="hero-title" style="font-size: 2.5rem; text-align: left; margin-bottom: 0.5rem;">Role Management</h1>
            <p class="hero-subtitle" style="text-align: left;">Manage roles and permissions for your organization.</p>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="dashboard-panel" style="width: 100%;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                    <h2 style="font-size: 1.25rem; margin: 0;">All Roles</h2>
                    <?php if (can('role.create')): ?>
                    <a href="create_role.php" class="btn btn-primary" style="padding: 0.6rem 1.2rem; text-decoration: none; font-size: 0.9rem; display: flex; align-items: center; gap: 8px;">
                        <i class="fa-solid fa-plus"></i> Create New Role
                    </a>
                    <?php endif; ?>
                </div>
                
                <table class="role-table">
                    <thead>
                        <tr>
                            <th>Role Name</th>
                            <th>Description</th>
                            <th>Permissions</th>
                            <th>Users</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($roles)): ?>
                            <?php foreach ($roles as $role): ?>
                                <?php 
                                $role_perms = get_role_permissions($role['id'], $tenant_id);
                                $perm_count = count($role_perms);
                                ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($role['role_name']); ?></strong>
                                        <?php if (!empty($role['is_system_role'])): ?>
                                        <span class="system-badge">System</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="color: var(--text-muted);">
                                        <?php echo htmlspecialchars($role['role_description'] ?? 'No description'); ?>
                                    </td>
                                    <td>
                                        <span class="role-badge"><?php echo $perm_count; ?> permissions</span>
                                    </td>
                                    <td>
                                        <span class="role-badge"><?php echo (int)$role['user_count']; ?> users</span>
                                    </td>
                                    <td>
                                        <div class="action-btns">
                                            <?php if (can('role.edit')): ?>
                                            <a href="edit_role.php?id=<?php echo $role['id']; ?>" class="btn btn-edit" title="Edit Role">
                                                <i class="fa-solid fa-pencil"></i>
                                            </a>
                                            <?php endif; ?>
                                            
                                            <?php if (can('role.delete') && $role['user_count'] == 0): ?>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this role?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="role_id" value="<?php echo $role['id']; ?>">
                                                <button type="submit" class="btn btn-danger" title="Delete Role">
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
                                            </form>
                                            <?php elseif (can('role.delete')): ?>
                                            <span class="btn btn-danger" style="opacity: 0.5; cursor: not-allowed;" title="Cannot delete - role is assigned to users">
                                                <i class="fa-solid fa-trash"></i>
                                            </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 2rem;">
                                    No roles found. <?php if (can('role.create')): ?><a href="create_role.php">Create your first role</a><?php endif; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</body>
</html>

