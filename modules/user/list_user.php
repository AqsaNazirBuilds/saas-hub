<?php
session_start();
require_once '../../config/db.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['tenant_id'])) {
    header("Location: ../../login.php");
    exit();
}

$tenant_id = $_SESSION['tenant_id'];
$error = '';
$success = '';

// Check current subscription limit
$stmt3 = $conn->prepare("
    SELECT p.plan_name, p.user_limit 
    FROM subscriptions s 
    JOIN plans p ON s.plan_id = p.id 
    WHERE s.tenant_id = ? AND s.status = 'active' ORDER BY s.id DESC LIMIT 1
");
$stmt3->bind_param("i", $tenant_id);
$stmt3->execute();
$sub_result = $stmt3->get_result();
$sub = $sub_result->fetch_assoc();

// Default values if no sub found
$plan_name = $sub ? $sub['plan_name'] : 'Trial';

$user_limit = $sub ? $sub['user_limit'] : 3;

// If Trial plan, enforce limit of 3
if (strpos(strtolower($plan_name), 'trial') !== false) {
    if ($user_limit > 3 || $user_limit == 0) { // Safety check
        $user_limit = 3;
    }
}

// Get current user count
$stmt_count = $conn->prepare("SELECT COUNT(id) as total FROM users WHERE tenant_id = ?");
$stmt_count->bind_param("i", $tenant_id);
$stmt_count->execute();
$current_user_count = $stmt_count->get_result()->fetch_assoc()['total'];

// Add User logic moved to add_user.php

// Fetch Users specific to this tenant
$stmt_users = $conn->prepare("SELECT id, name, email, role, status, created_at FROM users WHERE tenant_id = ? ORDER BY created_at DESC");
$stmt_users->bind_param("i", $tenant_id);
$stmt_users->execute();
$users = $stmt_users->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users – SaaS Hub</title>
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
        .user-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        .user-table th, .user-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--glass-border);
        }
        .user-table th {
            color: var(--text-muted);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
        }
        .status-badge {
            background: rgba(34, 197, 94, 0.1);
            color: #4ade80;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            border: 1px solid rgba(34, 197, 94, 0.2);
        }
        .form-group {
            margin-bottom: 1rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--text-muted);
        }
        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--glass-border);
            border-radius: 8px;
            color: var(--text-main);
            font-family: inherit;
        }
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 2px var(--primary-glow);
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
            <a href="../tenant/dashboard.php">Home</a>
            <a href="list_user.php" class="active">Manage Users</a>
            <a href="../subscription/status.php">Subscription Status</a>
            <a href="../audit/audit_view.php">Audit Logs</a>
            <a href="../../core/auth.php?logout=true" style="margin-top: auto;">Logout</a>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <h1 class="hero-title" style="font-size: 2.5rem; text-align: left; margin-bottom: 0.5rem;">User Management</h1>
            <p class="hero-subtitle" style="text-align: left;">Manage access for your team. You are using <?php echo $current_user_count; ?> of <?php echo $user_limit; ?> allowed users on the <?php echo htmlspecialchars($plan_name); ?> plan.</p>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php
endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php
endif; ?>

            <div>
                <!-- Users Table Panel -->
                <div class="dashboard-panel" style="width: 100%;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                        <h2 style="font-size: 1.25rem; margin: 0;">Active Users</h2>
                        <a href="add_user.php" class="btn btn-primary" style="padding: 0.6rem 1.2rem; text-decoration: none; font-size: 0.9rem; display: flex; align-items: center; gap: 8px;">
                            <i class="fa-solid fa-plus"></i> Add New User
                        </a>
                    </div>
                    <table class="user-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Date Added</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($users && $users->num_rows > 0): ?>
                                <?php while ($user = $users->fetch_assoc()): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($user['name']); ?></strong></td>
                                        <td style="color: var(--text-muted);"><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td style="text-transform: capitalize;"><?php echo htmlspecialchars(str_replace('_', ' ', $user['role'])); ?></td>
                                        <td><span class="status-badge"><?php echo htmlspecialchars($user['status']); ?></span></td>
                                        <td style="color: var(--text-muted); font-size: 0.9rem;"><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                        <td>
                                            <a href="edit_user.php?id=<?php echo $user['id']; ?>" style="color: var(--accent); text-decoration: none; margin-right: 15px; font-size: 1.1rem;" title="Edit"><i class="fa-solid fa-pencil"></i></a>
                                            <a href="delete_user.php?id=<?php echo $user['id']; ?>" onclick="return confirm('Are you sure you want to delete this user? This action cannot be undone.');" style="color: #f87171; text-decoration: none; font-size: 1.1rem;" title="Delete"><i class="fa-solid fa-trash"></i></a>
                                        </td>
                                    </tr>
                                <?php
    endwhile; ?>
                            <?php
else: ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 2rem;">No users found in this tenant.</td>
                                </tr>
                            <?php
endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</body>
</html>