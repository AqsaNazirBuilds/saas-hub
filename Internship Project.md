# **Masoom – Role & Permission System**

You control who can do what.

## **Your Responsibilities**

### Role System

**Tables:**
- `tenants`
- `roles`
- `permissions`
- `role_permissions`
- `user_roles`

All roles must belong to a tenant.

### Permission Middleware

Before any action:
- Check permission
- If no permission → 403 error
- Log the attempt

No hardcoded admin checks.

### Role Management Panel

Company Admin can:
- Create role
- Assign permissions
- Edit role
- Delete role (if safe)

### Dashboard Control

Different dashboards:
- **Admin** → full access
- **Manager** → limited control
- **Staff** → basic view only

Menus must hide based on permission.

---

## Detailed Design

### 1. Database Schema (MySQL)
```sql
-- Tenants (companies) table
CREATE TABLE tenants (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Roles table (tenant scoped)
CREATE TABLE roles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_role_tenant_name (tenant_id, name),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Permissions table (global list)
CREATE TABLE permissions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Pivot table linking roles and permissions
CREATE TABLE role_permissions (
    role_id BIGINT UNSIGNED NOT NULL,
    permission_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Pivot table linking users and roles (tenant scoped)
CREATE TABLE user_roles (
    user_id BIGINT UNSIGNED NOT NULL,
    role_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (user_id, role_id),
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
    -- Assuming a separate `users` table exists
) ENGINE=InnoDB;

-- Optional: Log unauthorized permission attempts
CREATE TABLE permission_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    permission VARCHAR(100) NOT NULL,
    uri VARCHAR(255) NOT NULL,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_permission (permission)
) ENGINE=InnoDB;
```

### 2. Permission Middleware (PHP – Slim / Laravel style)
```php
<?php
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Server\MiddlewareInterface as Middleware;

class PermissionMiddleware implements Middleware {
    /** @var PDO */
    private $db;

    public function __construct(PDO $db) { $this->db = $db; }

    public function __invoke(Request $request, Response $response, callable $next) {
        $required = $request->getAttribute('required_permission');
        if (!$required) { return $next($request, $response); }
        $userId = $request->getAttribute('user_id');
        if (!$userId) { return $this->deny($response, 'Unauthenticated'); }
        if (!$this->userHasPermission($userId, $required)) {
            $this->logUnauthorized($userId, $required, (string)$request->getUri());
            return $this->deny($response, 'Forbidden');
        }
        return $next($request, $response);
    }

    private function userHasPermission(int $userId, string $permName): bool {
        $sql = "SELECT 1 FROM user_roles ur
                JOIN roles r ON ur.role_id = r.id
                JOIN role_permissions rp ON r.id = rp.role_id
                JOIN permissions p ON rp.permission_id = p.id
                WHERE ur.user_id = :uid AND p.name = :perm LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['uid' => $userId, 'perm' => $permName]);
        return (bool) $stmt->fetchColumn();
    }

    private function deny(Response $response, string $msg): Response {
        $payload = ['error' => $msg];
        $response->getBody()->write(json_encode($payload));
        return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
    }

    private function logUnauthorized(int $userId, string $perm, string $uri): void {
        $sql = "INSERT INTO permission_logs (user_id, permission, uri) VALUES (:uid, :perm, :uri)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['uid' => $userId, 'perm' => $perm, 'uri' => $uri]);
    }
}
?>
```

### 3. Role Management Service (PHP)
```php
<?php
class RoleService {
    private $db;
    public function __construct(PDO $db) { $this->db = $db; }

    // Create a role for a specific tenant
    public function createRole(int $tenantId, string $name, string $description = ''): int {
        $sql = "INSERT INTO roles (tenant_id, name, description) VALUES (:tid, :name, :desc)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['tid' => $tenantId, 'name' => $name, 'desc' => $description]);
        return (int) $this->db->lastInsertId();
    }

    // Assign permissions to a role (replace existing set)
    public function setPermissions(int $roleId, array $permissionIds): void {
        $this->db->beginTransaction();
        $this->db->exec('DELETE FROM role_permissions WHERE role_id = '.(int)$roleId);
        $sql = "INSERT INTO role_permissions (role_id, permission_id) VALUES (:rid, :pid)";
        $stmt = $this->db->prepare($sql);
        foreach ($permissionIds as $pid) {
            $stmt->execute(['rid' => $roleId, 'pid' => $pid]);
        }
        $this->db->commit();
    }

    // Update role name/description
    public function updateRole(int $roleId, string $newName, string $newDesc = ''): void {
        $sql = "UPDATE roles SET name = :name, description = :desc, updated_at = NOW() WHERE id = :rid";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['name' => $newName, 'desc' => $newDesc, 'rid' => $roleId]);
    }

    // Delete role only if no users are attached
    public function deleteRole(int $roleId): bool {
        $check = $this->db->prepare('SELECT 1 FROM user_roles WHERE role_id = :rid LIMIT 1');
        $check->execute(['rid' => $roleId]);
        if ($check->fetchColumn()) { return false; }
        $this->db->exec('DELETE FROM roles WHERE id = '.(int)$roleId);
        return true;
    }
}
?>
```

### 4. Dashboard Permission Helpers
#### Backend (PHP helper)
```php
function canAccess(string $permission, int $userId, PDO $db): bool {
    $sql = "SELECT 1 FROM user_roles ur
            JOIN role_permissions rp ON ur.role_id = rp.role_id
            JOIN permissions p ON rp.permission_id = p.id
            WHERE ur.user_id = :uid AND p.name = :perm LIMIT 1";
    $stmt = $db->prepare($sql);
    $stmt->execute(['uid' => $userId, 'perm' => $permission]);
    return (bool) $stmt->fetchColumn();
}
```
#### Front‑end (Vanilla JS) – hide menu items without permission
```html
<ul id="menu">
  <li data-perm="view_reports">Reports</li>
  <li data-perm="manage_users">User Management</li>
  <li data-perm="settings_access">Settings</li>
</ul>
<script>
async function hideUnauthorized() {
  const res = await fetch('/api/me/permissions');
  const allowed = await res.json(); // e.g. ["view_reports","manage_users"]
  document.querySelectorAll('#menu li').forEach(li => {
    if (!allowed.includes(li.dataset.perm)) li.style.display = 'none';
  });
}
hideUnauthorized();
</script>
```

### 5. Security Testing Checklist
- **Permission Escalation**: Ensure no endpoint lets a user modify their own `user_roles` row.
- **URL Tampering**: All protected routes must be wrapped by `PermissionMiddleware`. Test by sending requests with altered `required_permission` attributes.
- **Logging**: Verify entries appear in `permission_logs` for every 403.
- **Least‑Privilege Defaults**: New users receive a minimal role (e.g., `staff`). Verify default role has only required permissions.
- **Tenant Isolation**: All queries that involve `roles` or `user_roles` must filter by `tenant_id`. Write integration tests that attempt cross‑tenant data access.

---

*The above design provides a complete, multi‑tenant aware Role & Permission system ready for integration into your SaaS platform.*
