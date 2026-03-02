<?php
session_start();
require_once __DIR__ . '/../../config/db.php';

// Authenticate Super Admin via Reusable Middleware
require_once __DIR__ . '/auth.php';

// Stat Calculation
$total_tenants = 0;
$active_subs_count = 0;
$system_revenue = 0;

$tenants = [];
$sql = "SELECT t.id, t.company_name, t.domain_slug, t.status, t.created_at,
               (SELECT COUNT(*) FROM users u WHERE u.tenant_id = t.id) AS total_users
        FROM tenants t
        ORDER BY t.created_at DESC";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $tenants[] = $row;
    }
}
$total_tenants = count($tenants);

// Check subscriptions table for active count
$subCheck = $conn->query("SHOW TABLES LIKE 'subscriptions'");
if ($subCheck && $subCheck->num_rows > 0) {
    if ($subs_res = $conn->query("SELECT COUNT(*) as count FROM subscriptions WHERE status = 'active'")) {
        $active_subs_count = $subs_res->fetch_assoc()['count'];
    }
}

// Check payments or plans for revenue
$payCheck = $conn->query("SHOW TABLES LIKE 'payments'");
if ($payCheck && $payCheck->num_rows > 0) {
    if ($rev_res = $conn->query("SELECT SUM(amount) as total FROM payments WHERE status = 'completed'")) {
        $system_revenue = $rev_res->fetch_assoc()['total'] ?? 0;
    }
}
else if ($subCheck && $subCheck->num_rows > 0) {
    $planCheck = $conn->query("SHOW TABLES LIKE 'plans'");
    if ($planCheck && $planCheck->num_rows > 0) {
        if ($rev_res = $conn->query("SELECT SUM(p.price) as total FROM subscriptions s JOIN plans p ON s.plan_id = p.id WHERE s.status = 'active'")) {
            $system_revenue = $rev_res->fetch_assoc()['total'] ?? 0;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Dashboard – SaaS Hub</title>
    <!-- Use the Anti-gravity Theme -->
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
        /* Top Cards Style */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2.5rem;
        }
        .stat-card {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            box-shadow: var(--glass-shadow);
            position: relative;
            overflow: hidden;
        }
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; height: 3px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
        }
        .stat-title {
            color: var(--text-muted);
            font-size: 0.95rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .stat-value {
            color: var(--text-main);
            font-size: 2.2rem;
            font-weight: 700;
            text-shadow: 0 0 10px var(--primary-glow);
        }
        /* Table Styles */
        .user-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        .user-table th, .user-table td {
            padding: 1.2rem 1rem;
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
            padding: 0.35rem 0.85rem;
            border-radius: 20px;
            font-size: 0.85rem;
            border: 1px solid rgba(34, 197, 94, 0.2);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }
        .status-suspended {
            background: rgba(239, 68, 68, 0.1);
            color: #f87171;
            border-color: rgba(239, 68, 68, 0.2);
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
            <a href="dashboard.php" class="active"><i class="fa-solid fa-house" style="margin-right:10px;"></i> Dashboard Home</a>
            <a href="../subscription/reports.php"><i class="fa-solid fa-chart-pie" style="margin-right:10px;"></i> Subscription Reports</a>
            <a href="../audit/audit_view.php"><i class="fa-solid fa-shield-halved" style="margin-right:10px;"></i> Audit Logs</a>
            <a href="../../core/auth.php?logout=true" style="margin-top: auto;"><i class="fa-solid fa-right-from-bracket" style="margin-right:10px;"></i> Logout</a>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <h1 class="hero-title" style="font-size: 2.5rem; text-align: left; margin-bottom: 0.5rem;">Super Admin Dashboard</h1>
            <p class="hero-subtitle" style="text-align: left; margin-bottom: 2rem;">Global oversight of your SaaS Hub universe.</p>

            <?php if (isset($_GET['success'])): ?>
                <div style="background: rgba(34, 197, 94, 0.1); color: #4ade80; padding: 1rem; border-radius: 8px; border: 1px solid rgba(34, 197, 94, 0.2); margin-bottom: 1.5rem;">
                    <i class="fa-solid fa-circle-check"></i> <?php echo htmlspecialchars($_GET['success']); ?>
                </div>
            <?php
endif; ?>

            <?php if (isset($_GET['error'])): ?>
                <div style="background: rgba(239, 68, 68, 0.1); color: #f87171; padding: 1rem; border-radius: 8px; border: 1px solid rgba(239, 68, 68, 0.2); margin-bottom: 1.5rem;">
                    <i class="fa-solid fa-circle-exclamation"></i> <?php echo htmlspecialchars($_GET['error']); ?>
                </div>
            <?php
endif; ?>

            <!-- Top Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-title">Total Tenants</div>
                    <div class="stat-value"><?php echo number_format($total_tenants); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-title">Active Subscriptions</div>
                    <div class="stat-value"><?php echo number_format($active_subs_count); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-title">System Revenue</div>
                    <div class="stat-value">$<?php echo number_format($system_revenue, 2); ?></div>
                </div>
            </div>

            <!-- Recent Tenants Table -->
            <div class="dashboard-panel" style="width: 100%;">
                <h2 style="font-size: 1.25rem; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 10px;">
                    <i class="fa-solid fa-building" style="color: var(--primary);"></i> Recent Tenants
                </h2>
                
                <table class="user-table">
                    <thead>
                        <tr>
                            <th>Company Name</th>
                            <th>Domain Slug</th>
                            <th>Status</th>
                            <th>Users Allowed/Used</th>
                            <th>Created On</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($tenants) > 0): ?>
                            <?php foreach ($tenants as $t): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($t['company_name']); ?></strong></td>
                                    <td style="color: var(--text-muted);"><?php echo htmlspecialchars($t['domain_slug']); ?></td>
                                    <td>
                                        <?php if (strtolower($t['status']) === 'active'): ?>
                                            <span class="status-badge"><?php echo htmlspecialchars($t['status']); ?></span>
                                        <?php
        else: ?>
                                            <span class="status-badge status-suspended"><?php echo htmlspecialchars($t['status']); ?></span>
                                        <?php
        endif; ?>
                                    </td>
                                    <td style="color: var(--text-muted);"><?php echo (int)$t['total_users']; ?> users</td>
                                    <td style="color: var(--text-muted); font-size: 0.9rem;">
                                        <?php echo isset($t['created_at']) ? date('M j, Y', strtotime($t['created_at'])) : 'N/A'; ?>
                                    </td>
                                    <td>
                                        <div style="display: flex; gap: 10px;">
                                            <a href="actions.php?action=suspend&id=<?php echo $t['id']; ?>" class="status-badge" style="background: rgba(234, 179, 8, 0.1); color: #facc15; border-color: rgba(234, 179, 8, 0.2); text-decoration: none; display: flex; align-items: center; justify-content: center; width: 32px; height: 32px; padding: 0;" title="Suspend/Activate">
                                                <i class="fa-solid fa-pause"></i>
                                            </a>
                                            <a href="actions.php?action=delete&id=<?php echo $t['id']; ?>" class="status-badge status-suspended" style="text-decoration: none; display: flex; align-items: center; justify-content: center; width: 32px; height: 32px; padding: 0;" onclick="return confirm('Are you sure you want to delete this tenant?')" title="Delete Tenant">
                                                <i class="fa-solid fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php
    endforeach; ?>
                        <?php
else: ?>
                            <tr>
                                <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 2rem;">No tenants found in the system.</td>
                            </tr>
                        <?php
endif; ?>
                    </tbody>
                </table>
            </div>
            
        </main>
    </div>
</body>
</html>
