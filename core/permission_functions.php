<?php
/**
 * =====================================================
 * MASOOM'S ROLE & PERMISSION SYSTEM
 * Permission Functions
 * =====================================================
 * 
 * This file contains all permission-related functions:
 * - get_user_permissions($user_id)
 * - can($permission_key)
 * - require_permission($permission_key)
 * - log_unauthorized_attempt()
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
 * CORE PERMISSION FUNCTIONS
 * =====================================================
 */

/**
 * Get all permissions for a user
 * Returns array of permission_keys
 * 
 * @param int $user_id The user ID
 * @return array Array of permission keys
 */
function get_user_permissions($user_id) {
    global $conn;
    
    $permissions = [];
    
    // Validate input
    if (!is_numeric($user_id) || $user_id <= 0) {
        return $permissions;
    }
    
    // Check if user is super admin (bypass permission check)
    if (isset($_SESSION['is_super_admin']) && $_SESSION['is_super_admin'] === true) {
        // Get ALL permissions for super admin
        $stmt = $conn->prepare("SELECT permission_key FROM permissions");
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $permissions[] = $row['permission_key'];
        }
        return $permissions;
    }
    
    // Get permissions through user -> roles -> permissions chain
    $stmt = $conn->prepare("
        SELECT DISTINCT p.permission_key 
        FROM permissions p 
        INNER JOIN role_permissions rp ON p.id = rp.permission_id
        INNER JOIN user_roles ur ON rp.role_id = ur.role_id
        WHERE ur.user_id = ?
    ");
    
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $permissions[] = $row['permission_key'];
    }
    
    return $permissions;
}

/**
 * Get all permissions for current logged-in user (from session)
 * 
 * @return array Array of permission keys
 */
function get_current_user_permissions() {
    if (!isset($_SESSION['user_id'])) {
        return [];
    }
    
    return get_user_permissions($_SESSION['user_id']);
}

/**
 * Check if user has a specific permission
 * 
 * @param string $permission_key The permission key to check
 * @param int|null $user_id Optional user ID (defaults to current user)
 * @return bool True if user has permission, false otherwise
 */
function can($permission_key, $user_id = null) {
    // Use current user if no user_id provided
    if ($user_id === null) {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }
        $user_id = $_SESSION['user_id'];
    }
    
    // Validate input
    if (empty($permission_key) || !is_numeric($user_id) || $user_id <= 0) {
        return false;
    }
    
    // Check if user is super admin
    if (isset($_SESSION['is_super_admin']) && $_SESSION['is_super_admin'] === true) {
        return true;
    }
    
    global $conn;
    
    // Check permission using prepared statement
    $stmt = $conn->prepare("
        SELECT p.permission_key 
        FROM permissions p 
        INNER JOIN role_permissions rp ON p.id = rp.permission_id
        INNER JOIN user_roles ur ON rp.role_id = ur.role_id
        WHERE ur.user_id = ? AND p.permission_key = ?
        LIMIT 1
    ");
    
    $stmt->bind_param("is", $user_id, $permission_key);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return ($result->num_rows > 0);
}

/**
 * Require a specific permission - if not found, deny access
 * Returns 403 error and logs the attempt
 * 
 * @param string $permission_key The permission key required
 * @return void
 */
function require_permission($permission_key) {
    if (!can($permission_key)) {
        // Log unauthorized attempt
        log_unauthorized_attempt($permission_key);
        
        // Return 403 Forbidden
        http_response_code(403);
        
        // Check if this is an AJAX request
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Access Denied: You do not have permission to perform this action.'
            ]);
            exit();
        }
        
        // Show error page for regular requests
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>403 - Access Denied</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    height: 100vh;
                    margin: 0;
                    background-color: #f5f5f5;
                }
                .error-container {
                    text-align: center;
                    padding: 40px;
                    background: white;
                    border-radius: 10px;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                }
                .error-code {
                    font-size: 72px;
                    color: #e74c3c;
                    margin: 0;
                }
                .error-message {
                    font-size: 24px;
                    color: #333;
                    margin: 20px 0;
                }
                .error-detail {
                    font-size: 14px;
                    color: #666;
                    margin-bottom: 20px;
                }
                .btn-back {
                    display: inline-block;
                    padding: 10px 20px;
                    background-color: #3498db;
                    color: white;
                    text-decoration: none;
                    border-radius: 5px;
                }
                .btn-back:hover {
                    background-color: #2980b9;
                }
            </style>
        </head>
        <body>
            <div class="error-container">
                <h1 class="error-code">403</h1>
                <h2 class="error-message">Access Denied</h2>
                <p class="error-detail">You do not have permission to access this page.</p>
                <p class="error-detail">Required Permission: <strong><?php echo htmlspecialchars($permission_key); ?></strong></p>
                <a href="javascript:history.back()" class="btn-back">Go Back</a>
            </div>
        </body>
        </html>
        <?php
        exit();
    }
}

/**
 * Check if user has ANY of the specified permissions
 * 
 * @param array $permission_keys Array of permission keys
 * @param int|null $user_id Optional user ID
 * @return bool True if user has at least one permission
 */
function can_any($permission_keys, $user_id = null) {
    foreach ($permission_keys as $key) {
        if (can($key, $user_id)) {
            return true;
        }
    }
    return false;
}

/**
 * Check if user has ALL of the specified permissions
 * 
 * @param array $permission_keys Array of permission keys
 * @param int|null $user_id Optional user ID
 * @return bool True if user has all permissions
 */
function can_all($permission_keys, $user_id = null) {
    foreach ($permission_keys as $key) {
        if (!can($key, $user_id)) {
            return false;
        }
    }
    return true;
}

/**
 * =====================================================
 * AUDIT LOGGING FUNCTIONS
 * =====================================================
 */

/**
 * Log unauthorized access attempt
 * 
 * @param string $permission_key The permission that was attempted
 * @return void
 */
function log_unauthorized_attempt($permission_key) {
    global $conn;
    
    // Get current user info
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    $tenant_id = isset($_SESSION['tenant_id']) ? $_SESSION['tenant_id'] : null;
    
    // Get user IP
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    
    // Get the requested URL
    $requested_url = $_SERVER['REQUEST_URI'] ?? 'Unknown';
    
    // Get request method
    $request_method = $_SERVER['REQUEST_METHOD'] ?? 'Unknown';
    
    // Insert log entry
    $stmt = $conn->prepare("
        INSERT INTO audit_logs (user_id, tenant_id, action, ip_address, details, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    
    $action = "UNAUTHORIZED_ACCESS_ATTEMPT: {$permission_key}";
    $details = json_encode([
        'required_permission' => $permission_key,
        'requested_url' => $requested_url,
        'request_method' => $request_method,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
    ]);
    
    // Handle null values
    $user_id = $user_id ?? null;
    $tenant_id = $tenant_id ?? null;
    
    if ($user_id !== null && $tenant_id !== null) {
        $stmt->bind_param("iisss", $user_id, $tenant_id, $action, $ip_address, $details);
    } elseif ($user_id !== null) {
        $null_tenant = null;
        $stmt->bind_param("iisss", $user_id, $null_tenant, $action, $ip_address, $details);
    } else {
        $null_user = null;
        $stmt->bind_param("iisss", $null_user, $tenant_id, $action, $ip_address, $details);
    }
    
    $stmt->execute();
}

/**
 * =====================================================
 * MENU/VISIBILITY HELPER FUNCTIONS
 * =====================================================
 */

/**
 * Check if menu item should be visible based on permissions
 * Use in HTML to conditionally show menu items
 * 
 * @param string $permission_key The permission required
 * @return bool True if should be visible
 */
function show_menu_item($permission_key) {
    return can($permission_key);
}

/**
 * Get menu items filtered by permissions (for dynamic menus)
 * 
 * @param array $menu_items Array of menu items with 'permission' key
 * @return array Filtered menu items
 */
function get_filtered_menu($menu_items) {
    return array_filter($menu_items, function($item) {
        if (!isset($item['permission'])) {
            return true; // No permission required
        }
        return can($item['permission']);
    });
}

/**
 * =====================================================
 * PERMISSION CHECK SHORTCUTS FOR COMMON OPERATIONS
 * =====================================================
 */

// User management shortcuts
function can_view_users() { return can('user.view'); }
function can_create_users() { return can('user.create'); }
function can_edit_users() { return can('user.edit'); }
function can_delete_users() { return can('user.delete'); }
function can_assign_user_roles() { return can('user.assign_role'); }

// Role management shortcuts
function can_view_roles() { return can('role.view'); }
function can_create_roles() { return can('role.create'); }
function can_edit_roles() { return can('role.edit'); }
function can_delete_roles() { return can('role.delete'); }
function can_assign_permissions() { return can('role.assign_permission'); }

// Reports shortcuts
function can_view_basic_reports() { return can('reports.basic'); }
function can_view_advanced_reports() { return can('reports.advanced'); }

// Audit shortcuts
function can_view_audit_logs() { return can('audit.view'); }
function can_export_audit_logs() { return can('audit.export'); }

// Subscription shortcuts
function can_view_subscription() { return can('subscription.view'); }
function can_manage_subscription() { return can('subscription.manage'); }

// Analytics shortcuts
function can_view_analytics() { return can('analytics.view'); }
function can_view_detailed_analytics() { return can('analytics.detailed'); }

// Settings shortcuts
function can_view_settings() { return can('settings.view'); }
function can_edit_settings() { return can('settings.edit'); }
