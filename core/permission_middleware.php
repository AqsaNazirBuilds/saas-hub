<?php
// Include the RBAC middleware class
include_once(__DIR__ . '/../modules/role/RBACMiddleware.php');

/**
 * Legacy hasPermission function retained for backward compatibility.
 * It now delegates to the RBACMiddleware class.
 */
function hasPermission($conn, $user_id, $permission_key) {
    // The RBACMiddleware::handle method checks permission and exits on failure.
    // Here we just return true/false without exiting.
    // Use the existing can() helper which performs the same DB check.
    return can($permission_key, $user_id);
}

/**
 * Enforce permission for the current request.
 * This should be called at the top of protected pages.
 *
 * @param string $requiredPermission Permission key required for the route.
 */
function enforcePermission(string $requiredPermission) {
    // Delegate to RBACMiddleware which handles logging and response.
    RBACMiddleware::handle($requiredPermission);
}
?>