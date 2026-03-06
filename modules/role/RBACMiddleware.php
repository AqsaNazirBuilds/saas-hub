<?php
/**
 * =====================================================
 * MASOOM'S ROLE & PERMISSION SYSTEM
 * RBAC Middleware Class
 * =====================================================
 * 
 * This class provides middleware functionality for checking
 * permissions before allowing access to protected routes.
 * 
 * Features:
 * - Permission checking
 * - Unauthorized attempt logging
 * - JSON and HTML response support
 * - AJAX detection
 * =====================================================
 */

class RBACMiddleware {
    
    /**
     * Handle permission check for the current request
     * This will exit with appropriate response if access is denied
     * 
     * @param string $requiredPermission The permission key required
     * @return void
     */
    public static function handle(string $requiredPermission): void {
        // Check if user is logged in
        if (!isset($_SESSION['user_id'])) {
            self::denyAccess('User not authenticated. Please login.');
            return;
        }
        
        // Check if user has the required permission
        if (!self::hasPermission($requiredPermission)) {
            // Log the unauthorized attempt
            self::logUnauthorizedAttempt($requiredPermission);
            
            // Deny access
            self::denyAccess('Access Denied: You do not have permission to perform this action.', $requiredPermission);
            return;
        }
        
        // Permission granted - continue
    }
    
    /**
     * Check if current user has a specific permission
     * 
     * @param string $permissionKey The permission key to check
     * @return bool True if user has permission
     */
    public static function hasPermission(string $permissionKey): bool {
        // Check if user is super admin
        if (isset($_SESSION['is_super_admin']) && $_SESSION['is_super_admin'] === true) {
            return true;
        }
        
        // Check if user is logged in
        if (!isset($_SESSION['user_id'])) {
            return false;
        }
        
        global $conn;
        
        // Use prepared statement to prevent SQL injection
        $stmt = $conn->prepare("
            SELECT p.permission_key 
            FROM permissions p 
            INNER JOIN role_permissions rp ON p.id = rp.permission_id
            INNER JOIN user_roles ur ON rp.role_id = ur.role_id
            WHERE ur.user_id = ? AND p.permission_key = ?
            LIMIT 1
        ");
        
        $stmt->bind_param("is", $_SESSION['user_id'], $permissionKey);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return ($result->num_rows > 0);
    }
    
    /**
     * Check if user has ANY of the specified permissions
     * 
     * @param array $permissionKeys Array of permission keys
     * @return bool True if user has at least one permission
     */
    public static function hasAnyPermission(array $permissionKeys): bool {
        foreach ($permissionKeys as $key) {
            if (self::hasPermission($key)) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Check if user has ALL of the specified permissions
     * 
     * @param array $permissionKeys Array of permission keys
     * @return bool True if user has all permissions
     */
    public static function hasAllPermissions(array $permissionKeys): bool {
        foreach ($permissionKeys as $key) {
            if (!self::hasPermission($key)) {
                return false;
            }
        }
        return true;
    }
    
    /**
     * Log unauthorized access attempt
     * 
     * @param string $permissionKey The permission that was attempted
     * @return void
     */
    public static function logUnauthorizedAttempt(string $permissionKey): void {
        global $conn;
        
        // Get current user info
        $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
        $tenant_id = isset($_SESSION['tenant_id']) ? $_SESSION['tenant_id'] : null;
        
        // Get request details
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $route = $_SERVER['REQUEST_URI'] ?? 'Unknown';
        $method = $_SERVER['REQUEST_METHOD'] ?? 'Unknown';
        
        // Check if audit_logs table exists
        $table_check = $conn->query("SHOW TABLES LIKE 'audit_logs'");
        if ($table_check->num_rows === 0) {
            return; // Table doesn't exist yet
        }
        
        // Prepare insert statement
        $stmt = $conn->prepare(
            "INSERT INTO audit_logs (user_id, tenant_id, permission, route, method, ip_address, attempted_at) VALUES (?, ?, ?, ?, ?, ?, NOW())"
        );
        
        // Handle null values
        $user_id = $user_id ?? 0;
        $tenant_id = $tenant_id ?? 0;
        
        $stmt->bind_param("iissss", $user_id, $tenant_id, $permissionKey, $route, $method, $ip_address);
        $stmt->execute();
    }
    
    /**
     * Deny access and show appropriate error response
     * 
     * @param string $message Error message
     * @param string|null $requiredPermission The permission that was required
     * @return void
     */
    private static function denyAccess(string $message, ?string $requiredPermission = null): void {
        // Set 403 status code
        http_response_code(403);
        
        // Check if this is an AJAX request
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => $message,
                'required_permission' => $requiredPermission
            ]);
            exit();
        }
        
        // Check if JSON is expected (API endpoint)
        if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => $message,
                'required_permission' => $requiredPermission
            ]);
            exit();
        }
        
        // Show HTML error page for regular requests
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>403 - Access Denied</title>
            <style>
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                }
                body {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
                    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
                    min-height: 100vh;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    padding: 20px;
                }
                .error-container {
                    text-align: center;
                    padding: 40px;
                    background: rgba(255, 255, 255, 0.05);
                    border-radius: 16px;
                    border: 1px solid rgba(255, 255, 255, 0.1);
                    backdrop-filter: blur(10px);
                    max-width: 500px;
                    width: 100%;
                }
                .error-code {
                    font-size: 96px;
                    font-weight: bold;
                    color: #e74c3c;
                    margin: 0;
                    text-shadow: 0 4px 20px rgba(231, 76, 60, 0.3);
                }
                .error-message {
                    font-size: 24px;
                    color: #fff;
                    margin: 20px 0;
                }
                .error-detail {
                    font-size: 14px;
                    color: rgba(255, 255, 255, 0.6);
                    margin-bottom: 20px;
                }
                .btn-back {
                    display: inline-block;
                    padding: 12px 24px;
                    background: linear-gradient(135deg, #3498db, #2980b9);
                    color: white;
                    text-decoration: none;
                    border-radius: 8px;
                    font-weight: 500;
                    transition: transform 0.2s, box-shadow 0.2s;
                }
                .btn-back:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 4px 15px rgba(52, 152, 219, 0.4);
                }
                .permission-box {
                    background: rgba(231, 76, 60, 0.1);
                    border: 1px solid rgba(231, 76, 60, 0.3);
                    border-radius: 8px;
                    padding: 15px;
                    margin: 20px 0;
                }
                .permission-label {
                    font-size: 12px;
                    color: rgba(255, 255, 255, 0.5);
                    text-transform: uppercase;
                    letter-spacing: 1px;
                }
                .permission-value {
                    font-size: 16px;
                    color: #e74c3c;
                    font-weight: 600;
                    margin-top: 5px;
                }
            </style>
        </head>
        <body>
            <div class="error-container">
                <h1 class="error-code">403</h1>
                <h2 class="error-message">Access Denied</h2>
                <p class="error-detail"><?php echo htmlspecialchars($message); ?></p>
                <?php if ($requiredPermission): ?>
                <div class="permission-box">
                    <div class="permission-label">Required Permission</div>
                    <div class="permission-value"><?php echo htmlspecialchars($requiredPermission); ?></div>
                </div>
                <?php endif; ?>
                <a href="javascript:history.back()" class="btn-back">Go Back</a>
            </div>
        </body>
        </html>
        <?php
        exit();
    }
    
    /**
     * Check if current user is a super admin
     * 
     * @return bool True if user is super admin
     */
    public static function isSuperAdmin(): bool {
        return isset($_SESSION['is_super_admin']) && $_SESSION['is_super_admin'] === true;
    }
    
    /**
     * Get current user ID
     * 
     * @return int|null User ID or null if not logged in
     */
    public static function getCurrentUserId(): ?int {
        return $_SESSION['user_id'] ?? null;
    }
    
    /**
     * Get current tenant ID
     * 
     * @return int|null Tenant ID or null if not available
     */
    public static function getCurrentTenantId(): ?int {
        return $_SESSION['tenant_id'] ?? null;
    }
}

