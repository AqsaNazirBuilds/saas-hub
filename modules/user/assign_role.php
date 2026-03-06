<?php
/**
 * =====================================================
 * MASOOM'S ROLE & PERMISSION SYSTEM
 * Assign Role to User Page
 * =====================================================
 * 
 * This page allows assigning roles to users within
 * the same tenant.
 * 
 * Required Permissions: user.assign_role
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

// Check if user has permission to assign roles
require_permission('user.assign_role');

$tenant_id = $_SESSION['tenant_id'];

// Get user ID from URL
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

if ($user_id <= 0) {
    header('Location: list_user.php?error=invalid_user');
    exit();
}

// Get user details
$stmt = $conn->prepare("SELECT id, name, email, tenant_id FROM users WHERE id = ? AND tenant_id = ?");
$stmt->bind_param("ii", $user_id, $tenant_id);
$stmt->execute();
$user_result = $stmt->get_result();
$user = $user_result->fetch_assoc();

if (!$user) {
    header('Location: list_user.php?error=user_not_found');
    exit();
}

// Get all roles for this tenant
$roles = get_roles_by_tenant($tenant_id);

// Get current roles for this user
$user_roles = get_user_roles($user_id);
$user_role_ids = array_map(function($r) { return $r['id']; }, $user_roles);

// Handle form submission
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected_roles = isset($_POST['roles']) ? $_POST['roles'] : [];
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Remove all current roles
        $delete_stmt = $conn->prepare("DELETE FROM user_roles WHERE user_id = ?");
        $delete_stmt->bind_param("i", $user_id);
        $delete_stmt->execute();
        
        // Add selected roles
        if (!empty($selected_roles)) {
            $insert_stmt = $conn->prepare("INSERT INTO user_roles (user_id, role_id, assigned_by) VALUES (?, ?, ?)");
            
            foreach ($selected_roles as $role_id) {
                $assigned_by = $_SESSION['user_id'];
                $insert_stmt->bind_param("iii", $user_id, $role_id, $assigned_by);
                $insert_stmt->execute();
            }
        }
        
        $conn->commit();
        
        // Log activity
        $role_names = [];
        foreach ($roles as $r) {
            if (in_array($r['id'], $selected_roles)) {
                $role_names[] = $r['role_name'];
            }
        }
        $log_details = "Updated roles for user {$user['name']}: " . implode(', ', $role_names);
        
        // Refresh data
        $user_roles = get_user_roles($user_id);
        $user_role_ids = array_map(function($r) { return $r['id']; }, $user_roles);
        
        $message = 'Roles updated successfully for ' . htmlspecialchars($user['name']) . '!';
        $message_type = 'success';
        
    } catch (Exception $e) {
        $conn->rollback();
        $message = 'Failed to update roles: ' . $e->getMessage();
        $message_type = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Role – SaaS Hub</title>
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
            max-width: 700px;
        }
        .dashboard-panel {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--glass-shadow);
        }
        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            margin-bottom: 2rem;
        }
        .user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            color: white;
        }
        .user-details h3 {
            margin: 0;
            font-size: 1.1rem;
        }
        .user-details p {
            margin: 0.25rem 0 0;
            color: var(--text-muted);
            font-size: 0.9rem;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.75rem;
            color: var(--text-muted);
            font-weight: 500;
        }
        .role-option {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--glass-border);
            border-radius: 8px;
            margin-bottom: 0.75rem;
            cursor: pointer;
            transition: all 0.2s;
        }
        .role-option:hover {
            background: rgba(255, 255, 255, 0.08);
            border-color: var(--primary);
        }
        .role-option input {
            width: 20px;
            height: 20px;
            accent-color: var(--primary);
        }
        .role-option.checked {
            border-color: var(--primary);
            background: rgba(52, 152, 219, 0.1);
        }
        .role-option h4 {
            margin: 0;
            font-size: 1rem;
        }
        .role-option p {
            margin: 0.25rem 0 0;
            color: var(--text-muted);
            font-size: 0.85rem;
        }
        .current-roles {
            margin-top: 1rem;
            padding: 1rem;
            background: rgba(52, 152, 219, 0.1);
            border: 1px solid rgba(52, 152, 219, 0.2);
            border-radius: 8px;
        }
        .current-roles h4 {
            margin: 0 0 0.5rem;
            color: #3498db;
            font-size: 0.9rem;
        }
        .current-roles p {
            margin: 0;
            color: var(--text-muted);
            font-size: 0.85rem;
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
    <script>
        function toggleRoleOption(checkbox) {
            const option = checkbox.closest('.role-option');
            if (checkbox.checked) {
                option.classList.add('checked');
            } else {
                option.classList.remove('checked');
            }
        }
    </script>
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
            <a href="list_user.php" class="active">Manage Users</a>
            <?php if (can('role.view')): ?>
            <a href="../role/list_role.php">Role Management</a>
            <?php endif; ?>
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
            <h1 class="hero-title" style="font-size: 2.5rem; text-align: left; margin-bottom: 0.5rem;">Assign Role</h1>
            <p class="hero-subtitle" style="text-align: left;">Manage role assignments for users.</p>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="dashboard-panel">
                <div class="user-info">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                    </div>
                    <div class="user-details">
                        <h3><?php echo htmlspecialchars($user['name']); ?></h3>
                        <p><?php echo htmlspecialchars($user['email']); ?></p>
                    </div>
                
                <?php if (!empty($user_roles)): ?>
                <div class="current-roles">
                    <h4><i class="fa-solid fa-check-circle"></i> Current Roles</h4>
                    <p>
                        <?php 
                        $role_names = array_map(function($r) { return $r['role_name']; }, $user_roles);
                        echo implode(', ', $role_names);
                        ?>
                    </p>
                </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label>Select Roles</label>
                        <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 1rem;">
                            Check the roles you want to assign to this user. A user can have multiple roles.
                        </p>
                        
                        <?php if (!empty($roles)): ?>
                            <?php foreach ($roles as $role): ?>
                                <label class="role-option <?php echo in_array($role['id'], $user_role_ids) ? 'checked' : ''; ?>">
                                    <input type="checkbox" name="roles[]" 
                                           value="<?php echo $role['id']; ?>"
                                           <?php echo in_array($role['id'], $user_role_ids) ? 'checked' : ''; ?>
                                           onchange="toggleRoleOption(this)">
                                    <div>
                                        <h4><?php echo htmlspecialchars($role['role_name']); ?></h4>
                                        <p><?php echo htmlspecialchars($role['role_description'] ?? 'No description'); ?></p>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="color: var(--text-muted);">No roles available. Please create roles first.</p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="btn-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fa-solid fa-check"></i> Save Changes
                        </button>
                        <a href="list_user.php" class="btn btn-secondary">
                            <i class="fa-solid fa-xmark"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </main>
    </div>
</body>
</html>
