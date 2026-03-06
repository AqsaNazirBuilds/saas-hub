<?php
/**
 * =====================================================
 * MASOOM'S ROLE & PERMISSION SYSTEM
 * Create Role Page
 * =====================================================
 * 
 * This page allows creating new roles and assigning
 * permissions to them.
 * 
 * Required Permissions: role.create
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

// Check if user has permission to create roles
require_permission('role.create');
$tenant_id = $_SESSION['tenant_id'];

// Get all available permissions
$all_permissions = get_all_permissions();

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
        // Create the role
        $result = create_role($role_name, $role_description, $tenant_id);
        
        if ($result['success']) {
            $new_role_id = $result['role_id'];

            // --- LAIBA: Audit Log Entry ---
            $audit_msg = "Created new role: " . $role_name . " (ID: " . $new_role_id . ")";
            $audit_obj->log($_SESSION['user_id'], $audit_msg, "Roles");
            
            // Assign selected permissions to the role
            if (!empty($selected_permissions)) {
                $perm_result = assign_permissions_to_role($new_role_id, $selected_permissions, $tenant_id);
                if (!$perm_result['success']) {
                    $message = 'Role created but failed to assign permissions: ' . $perm_result['message'];
                    $message_type = 'error';
                } else {
                    $message = 'Role created successfully with ' . count($selected_permissions) . ' permissions!';
                    $message_type = 'success';
                }
            } else {
                $message = 'Role created successfully (no permissions assigned).';
                $message_type = 'success';
            }
            
            // Redirect to list page on success
            if ($message_type === 'success') {
                header('Location: list_role.php?success=1');
                exit();
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
    <title>Create Role – SaaS Hub</title>
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
            <h1 class="hero-title" style="font-size: 2.5rem; text-align: left; margin-bottom: 0.5rem;">Create New Role</h1>
            <p class="hero-subtitle" style="text-align: left;">Define a new role and assign permissions.</p>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="dashboard-panel">
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="role_name">Role Name *</label>
                        <input type="text" id="role_name" name="role_name" class="form-control" 
                               placeholder="e.g., Supervisor, Team Lead" required
                               value="<?php echo isset($_POST['role_name']) ? htmlspecialchars($_POST['role_name']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="role_description">Description</label>
                        <textarea id="role_description" name="role_description" class="form-control" 
                                  placeholder="Describe what this role can do..."><?php echo isset($_POST['role_description']) ? htmlspecialchars($_POST['role_description']) : ''; ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Assign Permissions</label>
                        <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 1rem;">
                            Select the permissions this role should have. You can change these later.
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
                                                       <?php echo (isset($_POST['permissions']) && in_array($perm['id'], $_POST['permissions'])) ? 'checked' : ''; ?>>
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
                            <i class="fa-solid fa-check"></i> Create Role
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

