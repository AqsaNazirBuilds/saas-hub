<?php
session_start();
require_once '../../config/db.php';
include_once(__DIR__ . '/../../core/permission_functions.php');

// Check if user has permission to edit users
require_permission('user.edit');

// --- LAIBA: Audit log include kiya ---
require_once '../audit/audit.php';
$audit_obj = new AuditLog($conn);

// Ensure user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['tenant_id'])) {
    header("Location: ../../login.php");
    exit();
}

$tenant_id = $_SESSION['tenant_id'];
$error = '';
$success = '';

if (!isset($_GET['id'])) {
    header("Location: list_user.php");
    exit();
}
$edit_user_id = intval($_GET['id']);

// Fetch user data securely (ensuring tenant_id matches)
$stmt = $conn->prepare("SELECT id, name, email FROM users WHERE id = ? AND tenant_id = ? LIMIT 1");
$stmt->bind_param("ii", $edit_user_id, $tenant_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // User not found or isolated out
    header("Location: list_user.php");
    exit();
}
$user = $result->fetch_assoc();

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_name = trim($_POST['name'] ?? '');
    $new_email = trim($_POST['email'] ?? '');
    $new_password = $_POST['password'] ?? '';

    if (!empty($new_name) && !empty($new_email)) {
        // Verify email uniqueness checking other users
        $check = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $check->bind_param("si", $new_email, $edit_user_id);
        $check->execute();

        if ($check->get_result()->num_rows > 0) {
            $error = "This email is already registered to another user.";
        }
        else {
            if (!empty($new_password)) {
                $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
                $update = $conn->prepare("UPDATE users SET name = ?, email = ?, password = ? WHERE id = ? AND tenant_id = ?");
                $update->bind_param("sssii", $new_name, $new_email, $hashed_password, $edit_user_id, $tenant_id);
            }
            else {
                $update = $conn->prepare("UPDATE users SET name = ?, email = ? WHERE id = ? AND tenant_id = ?");
                $update->bind_param("ssii", $new_name, $new_email, $edit_user_id, $tenant_id);
            }

            if ($update->execute()) {
                // --- LAIBA: Edit User ka log yahan banega ---
    $audit_obj->logAction($_SESSION['user_id'], "Updated details for user: $new_name (ID: $edit_user_id)", "Users", $tenant_id);
                $success = "User details updated successfully!";
                $user['name'] = $new_name; // Update local display
                $user['email'] = $new_email;
            }
            else {
                $error = "Failed to update details. Please try again.";
            }
        }
    }
    else {
        $error = "Name and Email cannot be empty.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User – SaaS Hub</title>
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
            text-align: center;
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
            <a href="../subscription/reports.php">Reports</a>
            <a href="../../core/auth.php?logout=true" style="margin-top: auto;">Logout</a>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="page-header">
                <a href="list_user.php" style="color: var(--text-muted); text-decoration: none; font-size: 0.9rem; margin-bottom: 1rem; display: inline-block;">&larr; Back to Users</a>
                <h1 class="hero-title" style="font-size: 2.2rem; margin-bottom: 0.5rem;">Edit User Details</h1>
                <p class="hero-subtitle" style="font-size: 1rem; margin-bottom: 0;">Update member profiles securely inside your tenant workspace.</p>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php
endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php
endif; ?>

            <div class="glass-card">
                <form method="POST">
                    <div class="form-group">
                        <label>Full Name</label>
                        <input 
                            type="text" 
                            name="name" 
                            class="form-control"
                            value="<?php echo htmlspecialchars($user['name']); ?>" 
                            required>
                    </div>

                    <div class="form-group">
                        <label>Email Address</label>
                        <input 
                            type="email" 
                            name="email" 
                            class="form-control"
                            value="<?php echo htmlspecialchars($user['email']); ?>" 
                            required>
                    </div>

                    <div class="form-group">
                        <label>Reset Password</label>
                        <input 
                            type="password" 
                            name="password" 
                            class="form-control"
                            placeholder="Leave blank to keep current password"
                            minlength="6">
                    </div>

                    <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">
                        Save Changes
                    </button>
                    <a href="list_user.php" class="btn btn-ghost" style="width: 100%; margin-top: 10px; text-align: center; display: block; box-sizing: border-box;">Cancel</a>
                </form>
            </div>
        </main>
    </div>
</body>
</html>