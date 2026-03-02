<?php
/**
 * =====================================================
 * MASOOM'S ROLE & PERMISSION SYSTEM
 * Example Usage Guide
 * =====================================================
 * 
 * This file demonstrates how to use the Role & Permission System
 * in your module pages.
 * 
 * Topics covered:
 * 1. Including required files
 * 2. Using middleware to protect pages
 * 3. Using permission checks in code
 * 4. Menu visibility based on permissions
 * 5. Assigning permissions to roles
 * 6. Assigning roles to users
 * =====================================================
 */

// ============================================================================
// 1. INCLUDING REQUIRED FILES
// ============================================================================

// At the top of every protected page, include:
include_once(__DIR__ . '/../../config/db.php');
include_once(__DIR__ . '/../../core/permission_functions.php');
include_once(__DIR__ . '/../../core/role_functions.php');
include_once(__DIR__ . '/../../core/tenant_middleware.php');


// ============================================================================
// 2. USING MIDDLEWARE TO PROTECT PAGES
// ============================================================================

/*
 * Method A: Require permission - stops execution if denied
 * Use this at the top of pages that should only be accessible 
 * with specific permission
 */

// Example: Only users with 'user.view' permission can view this page
// require_permission('user.view');

// Example: Only admins can access user management
// require_permission('user.create');

/*
 * Method B: Conditional check - allows custom handling
 * Use this when you want to show/hide content on the same page
 */
if (!can('user.view')) {
    echo "You don't have permission to view users.";
    exit;
}


// ============================================================================
// 3. USING PERMISSION CHECKS IN CODE
// ============================================================================

// Check if current user can create users
if (can('user.create')) {
    echo '<a href="add_user.php" class="btn">Add New User</a>';
}

// Check using shortcut functions
if (can_create_users()) {
    echo '<button>Create User</button>';
}

if (can_delete_users()) {
    echo '<button>Delete</button>';
}

// Get all permissions for current user
$my_permissions = get_current_user_permissions();
echo "You have " . count($my_permissions) . " permissions";


// ============================================================================
// 4. MENU VISIBILITY BASED ON PERMISSIONS
// ============================================================================

/*
 * Method A: Simple inline check
 */
?>

<!-- Example Navigation Menu -->
<nav>
    <ul>
        <?php if (can('user.view')): ?>
            <li><a href="list_user.php">Users</a></li>
        <?php endif; ?>
        
        <?php if (can('role.view')): ?>
            <li><a href="list_role.php">Roles</a></li>
        <?php endif; ?>
        
        <?php if (can('reports.basic')): ?>
            <li><a href="reports.php">Reports</a></li>
        <?php endif; ?>
        
        <?php if (can('audit.view')): ?>
            <li><a href="audit_logs.php">Audit Logs</a></li>
        <?php endif; ?>
        
        <?php if (can('settings.view')): ?>
            <li><a href="settings.php">Settings</a></li>
        <?php endif; ?>
    </ul>
</nav>

<?php
/*
 * Method B: Using show_menu_item() function
 */
?>

<nav>
    <ul>
        <li><a href="dashboard.php">Dashboard</a></li>
        <?php if (show_menu_item('user.view')): ?><li>Users</li><?php endif; ?>
        <?php if (show_menu_item('role.view')): ?><li>Roles</li><?php endif; ?>
        <?php if (show_menu_item('reports.basic')): ?><li>Reports</li><?php endif; ?>
        <?php if (show_menu_item('audit.view')): ?><li>Audit</li><?php endif; ?>
    </ul>
</nav>

<?php
// ============================================================================
// 5. ASSIGNING PERMISSIONS TO A ROLE (Example Code)
// ============================================================================

/*
 * This would typically be in a role edit form handler
 */

// Get all available permissions
$all_permissions = get_all_permissions();

// Example: Assign specific permissions to a role
$permission_ids = [1, 2, 3, 6, 7, 11]; // IDs of permissions to assign

$result = assign_permissions_to_role($role_id, $permission_ids);

if ($result['success']) {
    echo "Permissions assigned successfully!";
} else {
    echo "Error: " . $result['message'];
}


// ============================================================================
// 6. ASSIGNING ROLES TO USERS (Example Code)
// ============================================================================

/*
 * This would typically be in a user edit form handler
 */

// Get all roles for current tenant
$roles = get_roles_by_tenant();

// Get all users in tenant
$users = get_tenant_users();

// Assign role to user
$result = assign_role_to_user($user_id, $role_id);

if ($result['success']) {
    echo "Role assigned to user successfully!";
} else {
    echo "Error: " . $result['message'];
}

// Remove role from user
$result = remove_role_from_user($user_id, $role_id);


// ============================================================================
// 7. ROLE MANAGEMENT - COMPLETE EXAMPLE
// ============================================================================

/*
 * Example: Creating a new role
 */
$result = create_role('Supervisor', 'Can manage team and view reports');

if ($result['success']) {
    $new_role_id = $result['role_id'];
    
    // Now assign permissions to the new role
    $permissions = [1, 2, 3, 6, 11, 13]; // user.view, user.create, user.edit, role.view, reports.basic, audit.view
    assign_permissions_to_role($new_role_id, $permissions);
    
    echo "Role created with permissions!";
} else {
    echo "Error: " . $result['message'];
}

/*
 * Example: Updating a role
 */
$result = update_role($role_id, 'Senior Supervisor', 'Extended responsibilities');

/*
 * Example: Deleting a role (with safety check)
 */
$result = delete_role($role_id);

if (!$result['success']) {
    // This will fail if role is assigned to users
    echo $result['message'];
}


// ============================================================================
// 8. CHECKING PERMISSIONS BEFORE ACTIONS
// ============================================================================

/*
 * Example: In a form processing file
 */

// Check before creating user
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_user'])) {
    require_permission('user.create');  // Will return 403 if not allowed
    
    // Proceed with user creation...
    // Your user creation code here
}

// Check before deleting user
if (isset($_GET['action']) && $_GET['action'] == 'delete') {
    require_permission('user.delete');  // Will return 403 if not allowed
    
    // Proceed with deletion...
    $user_id = $_GET['id'];
    // Your deletion code here
}


// ============================================================================
// 9. CHECKING MULTIPLE PERMISSIONS
// ============================================================================

/*
 * Check if user has ANY of the permissions
 */
if (can_any(['user.create', 'role.create', 'settings.edit'])) {
    echo "You have at least one administrative permission";
}

/*
 * Check if user has ALL of the permissions
 */
if (can_all(['user.view', 'user.edit', 'user.delete'])) {
    echo "You have full user management access";
}


// ============================================================================
// 10. EXAMPLE: PROTECTED PAGE STRUCTURE
// ============================================================================

/*
 * Complete example of a protected user list page
 */
?>

<?php
// File: modules/user/list_user.php (Updated with permissions)

// Include required files
include_once(__DIR__ . '/../../config/db.php');
include_once(__DIR__ . '/../../core/tenant_middleware.php');
include_once(__DIR__ . '/../../core/permission_functions.php');
include_once(__DIR__ . '/../../core/role_functions.php');

// Check if user has permission to view users
require_permission('user.view');

$tenant_id = $_SESSION['tenant_id'];

// Get users for this tenant
$stmt = $conn->prepare("SELECT * FROM users WHERE tenant_id = ? ORDER BY name");
$stmt->bind_param("i", $tenant_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <title>User Management</title>
    <link rel="stylesheet" href="../../css/aqsa_core_style.css">
</head>
<body>
    <div class="container">
        <h2>User Management</h2>
        
        <!-- Show Add button only if user has permission -->
        <?php if (can('user.create')): ?>
            <a href="add_user.php" class="btn btn-primary">+ Add New User</a>
        <?php endif; ?>
        
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($user = $result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($user['name']); ?></td>
                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                    <td><?php echo htmlspecialchars($user['status']); ?></td>
                    <td>
                        <!-- Show Edit only if user has permission -->
                        <?php if (can('user.edit')): ?>
                            <a href="edit_user.php?id=<?php echo $user['id']; ?>">Edit</a>
                        <?php endif; ?>
                        
                        <!-- Show Delete only if user has permission -->
                        <?php if (can('user.delete')): ?>
                            <a href="delete_user.php?id=<?php echo $user['id']; ?>" 
                               onclick="return confirm('Are you sure?')">Delete</a>
                        <?php endif; ?>
                        
                        <!-- Show Assign Role only if user has permission -->
                        <?php if (can('user.assign_role')): ?>
                            <a href="assign_role.php?user_id=<?php echo $user['id']; ?>">Assign Role</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</body>
</html>


<?php

// ============================================================================
// SUMMARY: QUICK REFERENCE
// ============================================================================

/*
 * KEY FUNCTIONS:
 * 
 * Permission Functions:
 * - get_user_permissions($user_id)           -> Get all permissions for a user
 * - get_current_user_permissions()            -> Get permissions for current user
 * - can($permission_key)                       -> Check if current user has permission
 * - require_permission($permission_key)        -> Deny access if no permission (403)
 * - can_any([$perms])                         -> Check if has ANY of permissions
 * - can_all([$perms])                         -> Check if has ALL permissions
 * - show_menu_item($permission_key)           -> Check for menu visibility
 * 
 * Role Functions:
 * - create_role($name, $description)          -> Create new role
 * - get_roles_by_tenant()                     -> Get all roles for tenant
 * - get_role_by_id($id)                      -> Get single role
 * - update_role($id, $name, $description)     -> Update role
 * - delete_role($id)                          -> Delete role (safe check)
 * - assign_permissions_to_role($role_id, $perm_ids)  -> Assign permissions
 * - get_role_permissions($role_id)           -> Get permissions for role
 * - get_all_permissions()                     -> Get all available permissions
 * - assign_role_to_user($user_id, $role_id)  -> Assign role to user
 * - remove_role_from_user($user_id, $role_id) -> Remove role from user
 * - get_user_roles($user_id)                  -> Get roles for user
 * - is_role_assigned_to_users($role_id)      -> Check if role is in use
 * - get_tenant_users()                        -> Get all users in tenant
 * 
 * Shortcut Functions:
 * - can_view_users(), can_create_users(), can_edit_users(), can_delete_users()
 * - can_assign_user_roles()
 * - can_view_roles(), can_create_roles(), can_edit_roles(), can_delete_roles()
 * - can_view_basic_reports(), can_view_advanced_reports()
 * - can_view_audit_logs(), can_export_audit_logs()
 * - can_view_subscription(), can_manage_subscription()
 * - can_view_analytics(), can_view_detailed_analytics()
 * - can_view_settings(), can_edit_settings()
 */
