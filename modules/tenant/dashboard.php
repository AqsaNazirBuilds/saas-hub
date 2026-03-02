<?php
session_start();
require_once '../../config/db.php';

// Session Check: Ensure $_SESSION['tenant_id'] exists
if (!isset($_SESSION['tenant_id'])) {
    header("Location: ../../login.php");
    exit();
}

$tenant_id = $_SESSION['tenant_id'];
$success = '';
$error = '';

// Fetch Tenant details
$stmt = $conn->prepare("SELECT * FROM tenants WHERE id = ?");
$stmt->bind_param("i", $tenant_id);
$stmt->execute();
$tenant = $stmt->get_result()->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['company_name'])) {
    $new_name = trim($_POST['company_name']);

    if (!empty($new_name)) {
        // Update query
        $update = $conn->prepare("UPDATE tenants SET company_name = ? WHERE id = ?");
        $update->bind_param("si", $new_name, $tenant_id);
        if ($update->execute()) {
            $success = "Company details updated successfully!";
            $tenant['company_name'] = $new_name; // Refresh the UI value
        }
        else {
            $error = "Failed to update details. Please try again.";
        }
    }
    else {
        $error = "Company name cannot be empty.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tenant Settings – SaaS Hub</title>
   
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
        .form-control:disabled {
            background: rgba(0, 0, 0, 0.2);
            color: #64748b;
            cursor: not-allowed;
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
            <a href="dashboard.php" class="active"> Home</a>
            <a href="../user/list_user.php">Manage Users</a>
            <a href="../subscription/status.php">Subscription Status</a>
            <a href="../audit/audit_view.php">Audit Logs</a>
            <a href="../../core/auth.php?logout=true" style="margin-top: auto;">Logout</a>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="page-header">
                <span class="badge" style="position: static; transform: none; display: inline-block; margin-bottom: 1rem;">Tenant Profile</span>
                <h1 class="hero-title" style="font-size: 2.2rem; margin-bottom: 0.5rem;">Manage company identity.</h1>
                <p class="hero-subtitle" style="font-size: 1rem; margin-bottom: 0;">Update your company shell safely while retaining data isolation.</p>
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
                        <label>Company name</label>
                        <input 
                            type="text" 
                            name="company_name" 
                            class="form-control"
                            value="<?php echo htmlspecialchars($tenant['company_name'] ?? ''); ?>" 
                            placeholder="Your Company Ltd"
                            required>
                    </div>

                    <div class="form-group">
                        <label>Domain slug (read-only)</label>
                        <input 
                            type="text" 
                            class="form-control"
                            value="<?php echo htmlspecialchars($tenant['domain_slug'] ?? ''); ?>" 
                            disabled>
                        <small style="color: var(--text-muted); display: block; margin-top: 5px; font-size: 0.8rem;">
                            Domain slugs act as unique routing parameters and cannot be modified.
                        </small>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">
                        Save Changes
                    </button>
                </form>
            </div>
        </main>
    </div>
</body>
</html>