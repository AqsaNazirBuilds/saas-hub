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
    if ($user_limit > 3 || $user_limit == 0) {
        $user_limit = 3;
    }
}

// Get current user count
$stmt_count = $conn->prepare("SELECT COUNT(id) as total FROM users WHERE tenant_id = ?");
$stmt_count->bind_param("i", $tenant_id);
$stmt_count->execute();
$current_user_count = $stmt_count->get_result()->fetch_assoc()['total'];

// Fetch available roles
$stmt_roles = $conn->prepare("SELECT id, role_name FROM roles WHERE role_name NOT IN ('super_admin', 'Super Admin')");
$stmt_roles->execute();
$available_roles = $stmt_roles->get_result();

// Handle Add User Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role_id = intval($_POST['role_id'] ?? 0);

    // Get actual role name string for 'users' table 
    $role_name = 'user';
    if ($role_id > 0) {
        $r_stmt = $conn->prepare("SELECT role_name FROM roles WHERE id = ?");
        $r_stmt->bind_param("i", $role_id);
        $r_stmt->execute();
        $r_res = $r_stmt->get_result();
        if ($r_row = $r_res->fetch_assoc()) {
            $role_name = strtolower(str_replace(' ', '_', $r_row['role_name']));
        }
    }

    if ($name && $email && $password && $role_id > 0) {
        if ($current_user_count >= $user_limit) {
            $error = "Limit reached! Your $plan_name plan only allows $user_limit users. Please upgrade your subscription.";
        }
        else {
            // Check if email already exists globally
            $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $check->bind_param("s", $email);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                $error = "This email is already registered.";
            }
            else {
                $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                $stmt_insert = $conn->prepare("INSERT INTO users (tenant_id, name, email, password, role, status) VALUES (?, ?, ?, ?, ?, 'active')");
                $stmt_insert->bind_param("issss", $tenant_id, $name, $email, $hashed_password, $role_name);

                if ($stmt_insert->execute()) {
                    $new_user_id = $conn->insert_id;
                    // Insert into user_roles table
                    $stmt_ur = $conn->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
                    $stmt_ur->bind_param("ii", $new_user_id, $role_id);
                    $stmt_ur->execute();

                    $success = "User added successfully!";
                    $current_user_count++; // Update count immediately for UI
                }
                else {
                    $error = "Failed to add user.";
                }
            }
        }
    }
    else {
        $error = "Please fill all fields, including the user role.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add User – SaaS Hub</title>
    <!-- Use the exact same Anti-gravity CSS -->
    <link rel="stylesheet" href="../../css/futuristic.css">
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
        .sidebar a.active, .sidebar a:hover {
            background: var(--glass-bg);
            color: var(--text-main);
            border-left: 3px solid var(--primary);
        }
        .main-content {
            flex: 1;
            padding: 3rem;
            position: relative;
            margin-left: 250px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        /* Dashboard Form Glass-card */
        .glass-card {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            padding: 2.5rem;
            border-radius: 16px;
            backdrop-filter: blur(10px);
            width: 100%;
            max-width: 500px;
            margin-top: 2rem;
            box-shadow: var(--glass-shadow);
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
            color: var(--text-muted);
            font-weight: 600;
        }
        .form-control {
            width: 100%;
            padding: 0.85rem 1rem;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--glass-border);
            border-radius: 8px;
            color: var(--text-main);
            font-family: inherit;
            transition: all 0.3s ease;
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
            width: 100%;
            max-width: 500px;
        }
        .alert-success {
            background: rgba(34, 197, 94, 0.1);
            color: #4ade80;
            border: 1px solid rgba(34, 197, 94, 0.2);
        }
        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            color: #f87171;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }
        .page-header {
            width: 100%;
            max-width: 500px;
            text-align: left;
            margin-bottom: 1rem;
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
            <!-- Sidebar Links -->
            <a href="../tenant/dashboard.php">Home</a>
            <a href="list_user.php" class="active">Manage Users</a>
            <a href="../subscription/status.php">Subscription Status</a>
            <a href="../audit/audit_view.php">Audit Logs</a>
            <a href="../../core/auth.php?logout=true" style="margin-top: auto;">Logout</a>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="page-header">
                <a href="list_user.php" style="color: var(--text-muted); text-decoration: none; font-size: 0.9rem; margin-bottom: 1rem; display: inline-block;">&larr; Back to Users</a>
                <h1 class="hero-title" style="font-size: 2.2rem; margin-bottom: 0.5rem; text-align: left;">Add New User</h1>
                <p class="hero-subtitle" style="font-size: 1rem; margin-bottom: 0; text-align: left;">Create a new member profile for your workspace.</p>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php
endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?>
                    <?php if (strpos($error, 'upgrade') !== false): ?>
                        <br><br><a href="../subscription/checkout.php" class="btn btn-primary" style="text-align: center; display: block;">View Upgrade Options</a>
                    <?php
    endif; ?>
                </div>
            <?php
endif; ?>

            <div class="glass-card">
                <?php
$isLimitReached = $current_user_count >= $user_limit;
$usageColor = $isLimitReached ? '#f87171' : 'var(--accent)';
$usageShadow = $isLimitReached ? 'rgba(248, 113, 113, 0.5)' : 'var(--primary-glow)';
?>
                <div style="margin-bottom: 1.5rem; padding-bottom: 1.5rem; border-bottom: 1px solid var(--glass-border);">
                    <div style="display: flex; justify-content: space-between; font-size: 0.9rem; margin-bottom: 0.8rem;">
                        <span style="color: var(--text-muted);">Plan: <strong style="color: var(--text-main);"><?php echo htmlspecialchars($plan_name); ?></strong></span>
                        <span style="color: <?php echo $usageColor; ?>; font-weight: 600;">Used <?php echo $current_user_count; ?>/<?php echo $user_limit; ?> users</span>
                    </div>
                    <div style="width: 100%; background: rgba(255, 255, 255, 0.05); height: 6px; border-radius: 10px; overflow: hidden;">
                        <?php $percentage = min(100, ($current_user_count / max(1, $user_limit)) * 100); ?>
                        <div style="width: <?php echo $percentage; ?>%; background: <?php echo $usageColor; ?>; height: 100%; border-radius: 10px; box-shadow: 0 0 10px <?php echo $usageShadow; ?>;"></div>
                    </div>
                </div>

                <?php if (!$isLimitReached): ?>
                <form method="POST">
                    <div class="form-group">
                        <label>Full Name</label>
                        <input 
                            type="text" 
                            name="name" 
                            class="form-control"
                            placeholder="e.g. John Doe"
                            required>
                    </div>

                    <div class="form-group">
                        <label>Email Address</label>
                        <input 
                            type="email" 
                            name="email" 
                            class="form-control"
                            placeholder="john@company.com"
                            required>
                    </div>

                    <div class="form-group">
                        <label>User Role</label>
                        <select 
                            name="role_id" 
                            class="form-control"
                            required
                            style="cursor: pointer;">
                            <option value="" disabled selected>Select a role...</option>
                            <?php if ($available_roles && $available_roles->num_rows > 0): ?>
                                <?php while ($r = $available_roles->fetch_assoc()): ?>
                                    <option value="<?php echo htmlspecialchars($r['id']); ?>" style="background: #020617; color: #e5e7eb;">
                                        <?php echo htmlspecialchars($r['role_name']); ?>
                                    </option>
                                <?php
        endwhile; ?>
                            <?php
    endif; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Temporary Password</label>
                        <input 
                            type="password" 
                            name="password" 
                            class="form-control"
                            placeholder="Minimum 6 characters"
                            required
                            minlength="6">
                    </div>

                    <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">
                        Add User
                    </button>
                    <a href="list_user.php" class="btn btn-ghost" style="width: 100%; margin-top: 10px; text-align: center; display: block; box-sizing: border-box;">Cancel</a>
                </form>
            </div>
            <?php
endif; ?>
        </main>
    </div>
</body>
</html>