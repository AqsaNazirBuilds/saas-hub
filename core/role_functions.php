<?php
/**
 * =====================================================
 * MASOOM'S ROLE & PERMISSION SYSTEM
 * Role Management Functions
 * =====================================================
 * 
 * This file contains all role-related functions:
 * - create_role()
 * - get_roles_by_tenant()
 * - update_role()
 * - delete_role()
 * - assign_permissions_to_role()
 * - remove_permissions_from_role()
 * - assign_role_to_user()
 * - remove_role_from_user()
 * - get_user_roles()
 * - get_role_permissions()
 * - is_role_assigned_to_users()
 * 
 * All functions are:
 * - Secure (using prepared statements)
 * - Tenant-aware
 * - Follow RBAC best practices
 * =====================================================
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include_once(__DIR__ . '/../config/db.php');

/**
 * =====================================================
 * ROLE CRUD OPERATIONS
 * =====================================================
 */

/**
 * Create a new role for a tenant
 * 
 * @param string $role_name Name of the role
 * @param string|null $role_description Description of the role
 * @param int|null $tenant_id Tenant ID (defaults to current session)
 * @return array Result with 'success' and 'message' keys
 */
function create_role($role_name, $role_description = null, $tenant_id = null) {
    global $conn;
    
    // Get tenant_id from session if not provided
    if ($tenant_id === null) {
        if (!isset($_SESSION['tenant_id'])) {
            return ['success' => false, 'message' => 'Tenant ID not found. Please login again.'];
        }
        $tenant_id = $_SESSION['tenant_id'];
    }
    
    // Validate input
    $role_name = trim($role_name);
    if (empty($role_name)) {
        return ['success' => false, 'message' => 'Role name is required.'];
    }
    
    if (strlen($role_name) > 50) {
        return ['success' => false, 'message' => 'Role name must be 50 characters or less.'];
    }
    
    // Check if role already exists for this tenant
    $check_stmt = $conn->prepare("
        SELECT id FROM roles 
        WHERE tenant_id = ? AND role_name = ?
    ");
    $check_stmt->bind_param("is", $tenant_id, $role_name);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        return ['success' => false, 'message' => 'A role with this name already exists for your company.'];
    }
    
    // Insert new role
    $stmt = $conn->prepare("
        INSERT INTO roles (tenant_id, role_name, role_description, is_system_role)
        VALUES (?, ?, ?, 0)
    ");
    
    $stmt->bind_param("iss", $tenant_id, $role_name, $role_description);
    
    if ($stmt->execute()) {
        $new_role_id = $stmt->insert_id;
        
        // Log the action (if audit_logs table exists)
        log_role_activity('ROLE_CREATED', $tenant_id, "Created role: {$role_name}");
        
        return [
            'success' => true, 
            'message' => 'Role created successfully.',
            'role_id' => $new_role_id
        ];
    }
    
    return ['success' => false, 'message' => 'Failed to create role. Please try again.'];
}

/**
 * Get all roles for a tenant
 * 
 * @param int|null $tenant_id Tenant ID (defaults to current session)
 * @return array Array of role records
 */
function get_roles_by_tenant($tenant_id = null) {
    global $conn;
    
    // Get tenant_id from session if not provided
    if ($tenant_id === null) {
        if (!isset($_SESSION['tenant_id'])) {
            return [];
        }
        $tenant_id = $_SESSION['tenant_id'];
    }
    
    $stmt = $conn->prepare("
        SELECT r.*, 
               (SELECT COUNT(*) FROM user_roles WHERE role_id = r.id) as user_count
        FROM roles r
        WHERE r.tenant_id = ?
        ORDER BY r.is_system_role DESC, r.role_name ASC
    ");
    
    $stmt->bind_param("i", $tenant_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $roles = [];
    while ($row = $result->fetch_assoc()) {
        $roles[] = $row;
    }
    
    return $roles;
}

/**
 * Get a single role by ID (tenant-aware)
 * 
 * @param int $role_id Role ID
 * @param int|null $tenant_id Tenant ID (defaults to current session)
 * @return array|null Role record or null if not found
 */
function get_role_by_id($role_id, $tenant_id = null) {
    global $conn;
    
    // Get tenant_id from session if not provided
    if ($tenant_id === null) {
        if (!isset($_SESSION['tenant_id'])) {
            return null;
        }
        $tenant_id = $_SESSION['tenant_id'];
    }
    
    $stmt = $conn->prepare("
        SELECT r.*, 
               (SELECT COUNT(*) FROM user_roles WHERE role_id = r.id) as user_count
        FROM roles r
        WHERE r.id = ? AND r.tenant_id = ?
    ");
    
    $stmt->bind_param("ii", $role_id, $tenant_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_assoc();
}

/**
 * Update an existing role
 * 
 * @param int $role_id Role ID to update
 * @param string $role_name New role name
 * @param string|null $role_description New description
 * @param int|null $tenant_id Tenant ID (defaults to current session)
 * @return array Result with 'success' and 'message' keys
 */
function update_role($role_id, $role_name, $role_description = null, $tenant_id = null) {
    global $conn;
    
    // Get tenant_id from session if not provided
    if ($tenant_id === null) {
        if (!isset($_SESSION['tenant_id'])) {
            return ['success' => false, 'message' => 'Tenant ID not found. Please login again.'];
        }
        $tenant_id = $_SESSION['tenant_id'];
    }
    
    // Validate input
    $role_name = trim($role_name);
    if (empty($role_name)) {
        return ['success' => false, 'message' => 'Role name is required.'];
    }
    
    // Check if role exists and belongs to tenant
    $existing_role = get_role_by_id($role_id, $tenant_id);
    if (!$existing_role) {
        return ['success' => false, 'message' => 'Role not found or access denied.'];
    }
    
    // Prevent editing system roles
    if ($existing_role['is_system_role'] == 1) {
        return ['success' => false, 'message' => 'Cannot edit system roles.'];
    }
    
    // Check if new name already exists for another role
    $check_stmt = $conn->prepare("
        SELECT id FROM roles 
        WHERE tenant_id = ? AND role_name = ? AND id != ?
    ");
    $check_stmt->bind_param("isi", $tenant_id, $role_name, $role_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        return ['success' => false, 'message' => 'A role with this name already exists.'];
    }
    
    // Update role
    $stmt = $conn->prepare("
        UPDATE roles 
        SET role_name = ?, role_description = ?
        WHERE id = ? AND tenant_id = ?
    ");
    
    $stmt->bind_param("ssii", $role_name, $role_description, $role_id, $tenant_id);
    
    if ($stmt->execute()) {
        log_role_activity('ROLE_UPDATED', $tenant_id, "Updated role: {$role_name} (ID: {$role_id})");
        return ['success' => true, 'message' => 'Role updated successfully.'];
    }
    
    return ['success' => false, 'message' => 'Failed to update role. Please try again.'];
}

/**
 * Delete a role (with safety check)
 * 
 * @param int $role_id Role ID to delete
 * @param int|null $tenant_id Tenant ID (defaults to current session)
 * @return array Result with 'success' and 'message' keys
 */
function delete_role($role_id, $tenant_id = null) {
    global $conn;
    
    // Get tenant_id from session if not provided
    if ($tenant_id === null) {
        if (!isset($_SESSION['tenant_id'])) {
            return ['success' => false, 'message' => 'Tenant ID not found. Please login again.'];
        }
        $tenant_id = $_SESSION['tenant_id'];
    }
    
    // Check if role exists and belongs to tenant
    $existing_role = get_role_by_id($role_id, $tenant_id);
    if (!$existing_role) {
        return ['success' => false, 'message' => 'Role not found or access denied.'];
    }
    
    // Prevent deleting system roles
    if ($existing_role['is_system_role'] == 1) {
        return ['success' => false, 'message' => 'Cannot delete system roles.'];
    }
    
    // Check if role is assigned to any users
    if (is_role_assigned_to_users($role_id)) {
        return [
            'success' => false, 
            'message' => 'Cannot delete this role because it is assigned to users. Please remove the role from all users first.'
        ];
    }
    
    // Delete role (permissions will be cascade deleted)
    $stmt = $conn->prepare("DELETE FROM roles WHERE id = ? AND tenant_id = ?");
    $stmt->bind_param("ii", $role_id, $tenant_id);
    
    if ($stmt->execute()) {
        log_role_activity('ROLE_DELETED', $tenant_id, "Deleted role: {$existing_role['role_name']} (ID: {$role_id})");
        return ['success' => true, 'message' => 'Role deleted successfully.'];
    }
    
    return ['success' => false, 'message' => 'Failed to delete role. Please try again.'];
}

/**
 * =====================================================
 * PERMISSION-ROLE ASSIGNMENTS
 * =====================================================
 */

/**
 * Assign permissions to a role
 * 
 * @param int $role_id Role ID
 * @param array $permission_ids Array of permission IDs
 * @param int|null $tenant_id Tenant ID (defaults to current session)
 * @return array Result with 'success' and 'message' keys
 */
function assign_permissions_to_role($role_id, $permission_ids, $tenant_id = null) {
    global $conn;
    
    // Get tenant_id from session if not provided
    if ($tenant_id === null) {
        if (!isset($_SESSION['tenant_id'])) {
            return ['success' => false, 'message' => 'Tenant ID not found.'];
        }
        $tenant_id = $_SESSION['tenant_id'];
    }
    
    // Verify role belongs to tenant
    $role = get_role_by_id($role_id, $tenant_id);
    if (!$role) {
        return ['success' => false, 'message' => 'Role not found or access denied.'];
    }
    
    // Validate permission_ids is an array
    if (!is_array($permission_ids) || empty($permission_ids)) {
        return ['success' => false, 'message' => 'No permissions selected.'];
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Remove existing permissions first
        $delete_stmt = $conn->prepare("DELETE FROM role_permissions WHERE role_id = ?");
        $delete_stmt->bind_param("i", $role_id);
        $delete_stmt->execute();
        
        // Insert new permissions
        $insert_stmt = $conn->prepare("
            INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)
        ");
        
        foreach ($permission_ids as $perm_id) {
            $insert_stmt->bind_param("ii", $role_id, $perm_id);
            $insert_stmt->execute();
        }
        
        $conn->commit();
        
        log_role_activity('PERMISSIONS_UPDATED', $tenant_id, 
            "Updated permissions for role: {$role['role_name']} (ID: {$role_id})");
        
        return ['success' => true, 'message' => 'Permissions assigned successfully.'];
        
    } catch (Exception $e) {
        $conn->rollback();
        return ['success' => false, 'message' => 'Failed to assign permissions.'];
    }
}

/**
 * Get all permissions assigned to a role
 * 
 * @param int $role_id Role ID
 * @param int|null $tenant_id Tenant ID (defaults to current session)
 * @return array Array of permission records
 */
function get_role_permissions($role_id, $tenant_id = null) {
    global $conn;
    
    // Get tenant_id from session if not provided
    if ($tenant_id === null) {
        if (!isset($_SESSION['tenant_id'])) {
            return [];
        }
        $tenant_id = $_SESSION['tenant_id'];
    }
    
    // Verify role belongs to tenant
    $role = get_role_by_id($role_id, $tenant_id);
    if (!$role) {
        return [];
    }
    
    $stmt = $conn->prepare("
        SELECT p.* 
        FROM permissions p
        INNER JOIN role_permissions rp ON p.id = rp.permission_id
        WHERE rp.role_id = ?
        ORDER BY p.category, p.permission_name
    ");
    
    $stmt->bind_param("i", $role_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $permissions = [];
    while ($row = $result->fetch_assoc()) {
        $permissions[] = $row;
    }
    
    return $permissions;
}

/**
 * Get all available permissions (for role assignment UI)
 * 
 * @return array Array of all permissions grouped by category
 */
function get_all_permissions() {
    global $conn;
    
    $stmt = $conn->prepare("SELECT * FROM permissions ORDER BY category, permission_name");
    $stmt->execute();
    $result = $stmt->get_result();
    
    $permissions = [];
    while ($row = $result->fetch_assoc()) {
        $permissions[$row['category']][] = $row;
    }
    
    return $permissions;
}

/**
 * =====================================================
 * USER-ROLE ASSIGNMENTS
 * =====================================================
 */

/**
 * Assign a role to a user (within same tenant)
 * 
 * @param int $user_id User ID
 * @param int $role_id Role ID
 * @param int|null $assigned_by User ID who assigned the role
 * @return array Result with 'success' and 'message' keys
 */
function assign_role_to_user($user_id, $role_id, $assigned_by = null) {
    global $conn;
    
    // Get current tenant_id
    if (!isset($_SESSION['tenant_id'])) {
        return ['success' => false, 'message' => 'Tenant ID not found.'];
    }
    $tenant_id = $_SESSION['tenant_id'];
    
    // Get assigned_by from session if not provided
    if ($assigned_by === null) {
        $assigned_by = $_SESSION['user_id'] ?? null;
    }
    
    // Verify user belongs to same tenant
    $user_stmt = $conn->prepare("SELECT id, tenant_id FROM users WHERE id = ?");
    $user_stmt->bind_param("i", $user_id);
    $user_stmt->execute();
    $user_result = $user_stmt->get_result();
    $user = $user_result->fetch_assoc();
    
    if (!$user) {
        return ['success' => false, 'message' => 'User not found.'];
    }
    
    if ($user['tenant_id'] != $tenant_id) {
        return ['success' => false, 'message' => 'Cannot assign role to user from different company.'];
    }
    
    // Verify role belongs to same tenant
    $role = get_role_by_id($role_id, $tenant_id);
    if (!$role) {
        return ['success' => false, 'message' => 'Role not found or access denied.'];
    }
    
    // Check if assignment already exists
    $check_stmt = $conn->prepare("
        SELECT * FROM user_roles WHERE user_id = ? AND role_id = ?
    ");
    $check_stmt->bind_param("ii", $user_id, $role_id);
    $check_stmt->execute();
    
    if ($check_stmt->get_result()->num_rows > 0) {
        return ['success' => false, 'message' => 'User already has this role.'];
    }
    
    // Insert assignment
    $stmt = $conn->prepare("
        INSERT INTO user_roles (user_id, role_id, assigned_by)
        VALUES (?, ?, ?)
    ");
    
    $stmt->bind_param("iii", $user_id, $role_id, $assigned_by);
    
    if ($stmt->execute()) {
        log_role_activity('USER_ROLE_ASSIGNED', $tenant_id, 
            "Assigned role '{$role['role_name']}' to user ID: {$user_id}");
        
        return ['success' => true, 'message' => 'Role assigned to user successfully.'];
    }
    
    return ['success' => false, 'message' => 'Failed to assign role.'];
}

/**
 * Remove a role from a user
 * 
 * @param int $user_id User ID
 * @param int $role_id Role ID
 * @return array Result with 'success' and 'message' keys
 */
function remove_role_from_user($user_id, $role_id) {
    global $conn;
    
    // Get current tenant_id
    if (!isset($_SESSION['tenant_id'])) {
        return ['success' => false, 'message' => 'Tenant ID not found.'];
    }
    $tenant_id = $_SESSION['tenant_id'];
    
    // Verify user belongs to same tenant
    $user_stmt = $conn->prepare("SELECT id, tenant_id FROM users WHERE id = ?");
    $user_stmt->bind_param("i", $user_id);
    $user_stmt->execute();
    $user_result = $user_stmt->get_result();
    $user = $user_result->fetch_assoc();
    
    if (!$user || $user['tenant_id'] != $tenant_id) {
        return ['success' => false, 'message' => 'User not found or access denied.'];
    }
    
    // Verify role belongs to same tenant
    $role = get_role_by_id($role_id, $tenant_id);
    if (!$role) {
        return ['success' => false, 'message' => 'Role not found or access denied.'];
    }
    
    // Delete assignment
    $stmt = $conn->prepare("DELETE FROM user_roles WHERE user_id = ? AND role_id = ?");
    $stmt->bind_param("ii", $user_id, $role_id);
    
    if ($stmt->execute()) {
        log_role_activity('USER_ROLE_REMOVED', $tenant_id, 
            "Removed role '{$role['role_name']}' from user ID: {$user_id}");
        
        return ['success' => true, 'message' => 'Role removed from user successfully.'];
    }
    
    return ['success' => false, 'message' => 'Failed to remove role.'];
}

/**
 * Get all roles assigned to a user
 * 
 * @param int $user_id User ID
 * @return array Array of role records
 */
function get_user_roles($user_id) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT r.* 
        FROM roles r
        INNER JOIN user_roles ur ON r.id = ur.role_id
        WHERE ur.user_id = ?
        ORDER BY r.role_name
    ");
    
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $roles = [];
    while ($row = $result->fetch_assoc()) {
        $roles[] = $row;
    }
    
    return $roles;
}

/**
 * Get all users with a specific role
 * 
 * @param int $role_id Role ID
 * @param int|null $tenant_id Tenant ID (defaults to current session)
 * @return array Array of user records
 */
function get_users_with_role($role_id, $tenant_id = null) {
    global $conn;
    
    // Get tenant_id from session if not provided
    if ($tenant_id === null) {
        if (!isset($_SESSION['tenant_id'])) {
            return [];
        }
        $tenant_id = $_SESSION['tenant_id'];
    }
    
    $stmt = $conn->prepare("
        SELECT u.* 
        FROM users u
        INNER JOIN user_roles ur ON u.id = ur.user_id
        WHERE ur.role_id = ? AND u.tenant_id = ?
        ORDER BY u.name
    ");
    
    $stmt->bind_param("ii", $role_id, $tenant_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    
    return $users;
}

/**
 * Check if a role is assigned to any users
 * 
 * @param int $role_id Role ID
 * @return bool True if role is assigned to users
 */
function is_role_assigned_to_users($role_id) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM user_roles WHERE role_id = ?");
    $stmt->bind_param("i", $role_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return ($row['count'] > 0);
}

/**
 * =====================================================
 * HELPER FUNCTIONS
 * =====================================================
 */

/**
 * Log role-related activities (internal function)
 * 
 * @param string $action Action name
 * @param int $tenant_id Tenant ID
 * @param string $details Action details
 */
function log_role_activity($action, $tenant_id, $details) {
    global $conn;
    
    // Check if audit_logs table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'audit_logs'");
    if ($table_check->num_rows === 0) {
        return; // Table doesn't exist yet
    }
    
    $user_id = $_SESSION['user_id'] ?? null;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    
    $stmt = $conn->prepare("
        INSERT INTO audit_logs (user_id, tenant_id, action, ip_address, details, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->bind_param("iisss", $user_id, $tenant_id, $action, $ip_address, $details);
    $stmt->execute();
}

/**
 * Get users list for a tenant (for role assignment)
 * 
 * @param int|null $tenant_id Tenant ID (defaults to current session)
 * @return array Array of user records
 */
function get_tenant_users($tenant_id = null) {
    global $conn;
    
    // Get tenant_id from session if not provided
    if ($tenant_id === null) {
        if (!isset($_SESSION['tenant_id'])) {
            return [];
        }
        $tenant_id = $_SESSION['tenant_id'];
    }
    
    $stmt = $conn->prepare("
        SELECT u.id, u.name, u.email, u.status,
               GROUP_CONCAT(r.role_name ORDER BY r.role_name SEPARATOR ', ') as roles
        FROM users u
        LEFT JOIN user_roles ur ON u.id = ur.user_id
        LEFT JOIN roles r ON ur.role_id = r.id
        WHERE u.tenant_id = ?
        GROUP BY u.id
        ORDER BY u.name
    ");
    
    $stmt->bind_param("i", $tenant_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    
    return $users;
}
