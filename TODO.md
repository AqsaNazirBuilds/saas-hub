# TODO - Masoom's Role & Permission System

## Step 1: Update SQL Schema
- [ ] Update database/permissions.sql with 15-20 permissions
- [ ] Update database/roles.sql with unique constraint
- [ ] Update database/role_permissions.sql with proper constraints
- [ ] Update database/user_roles.sql with proper constraints

## Step 2: Create Enhanced Permission Middleware
- [ ] Create core/permission_functions.php with:
  - [ ] get_user_permissions($user_id)
  - [ ] can($permission_key)
  - [ ] require_permission($permission_key)
  - [ ] log_unauthorized_attempt()

## Step 3: Create Role Management Functions
- [ ] Create core/role_functions.php with:
  - [ ] create_role()
  - [ ] get_roles_by_tenant()
  - [ ] update_role()
  - [ ] delete_role() (with safety check)
  - [ ] assign_permissions_to_role()
  - [ ] assign_role_to_user()
  - [ ] remove_role_from_user()

## Step 4: Create Example Usage
- [ ] Create modules/role/example_usage.php
- [ ] Update modules/user/list_user.php with permission checks
- [ ] Create menu visibility example

## Step 5: Integration Testing
- [ ] Test get_user_permissions()
- [ ] Test can() function
- [ ] Test require_permission() middleware
- [ ] Test role deletion safety
