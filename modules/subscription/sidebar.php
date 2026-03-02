<?php
// modules/subscription/sidebar.php
include_once(__DIR__ . '/../../config/db.php');


if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$tid = $_SESSION['tenant_id'] ?? 1;

// Database connection check ($db variable is used in your config)
$conn_var = isset($db) ? $db : $conn;


// Check karein ke user login hai ya nahi
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $query = "SELECT name, role FROM users WHERE id = '$user_id'";
    $result = mysqli_query($conn_var, $query);
    $user_data = mysqli_fetch_assoc($result);

    $display_name = $user_data['name'] ?? "User";
    $display_role = $user_data['role'] ?? "Member";
}
else {
    $display_name = "Guest User";
    $display_role = "No Role";
}

// --- NEW CODE: Unread Notifications Count ---
$sql_count = "SELECT COUNT(*) as unread_count FROM notifications WHERE tenant_id = ? AND is_read = 0";
$stmt_c = $conn_var->prepare($sql_count);
$stmt_c->bind_param("i", $tid);
$stmt_c->execute();
$notif_res = $stmt_c->get_result()->fetch_assoc();
$unread_count = $notif_res['unread_count'] ?? 0;
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>css/laiba/sidebar.css?v=<?php echo time(); ?>">

<style>
    /* Bell icon ki styling */
    .notif-wrapper { position: relative; margin-right: 15px; cursor: pointer; }
    .notif-badge { 
        position: absolute; top: -5px; right: -5px; 
        background: #ff4d4d; color: white; 
        font-size: 10px; padding: 2px 5px; 
        border-radius: 50%; font-weight: bold;
        border: 2px solid #1f3b57;
    }
    .nav-link { display: flex; align-items: center; justify-content: space-between; width: 100%; }
    .bell-link { color: #fff; text-decoration: none; font-size: 18px; transition: 0.3s; }
    .bell-link:hover { color: #ffca2c; }
</style>

<div class="sidebar">
    <div class="sidebar-header" style="display: flex; justify-content: space-between; align-items: center; padding-right: 20px;">
        <div style="display: flex; align-items: center;">
            <div class="logo-icon"><i class="fas fa-rocket"></i></div>
            <div class="logo-text"><span>SAAS</span> PANEL</div>
        </div>

        <div class="notif-wrapper">
            <a href="<?php echo BASE_URL; ?>modules/audit/reports.php" class="bell-link" title="View Notifications">
                <i class="fas fa-bell"></i>
                <?php if ($unread_count > 0): ?>
                    <span class="notif-badge"><?php echo $unread_count; ?></span>
                <?php
endif; ?>
            </a>
        </div>
    </div>

    <ul class="nav-links">
        <li class="nav-item">
            <a href="<?php echo BASE_URL; ?>modules/subscription/reports.php" class="nav-link">
                <div><i class="fas fa-th-large"></i> <span class="link-name">Dashboard</span></div>
            </a>
        </li>

        <li class="nav-item">
            <a href="<?php echo BASE_URL; ?>modules/subscription/status.php" class="nav-link">
                <div><i class="fas fa-crown"></i> <span class="link-name">Plan & Billing</span></div>
            </a>
        </li>

        <li class="nav-item">
            <a href="<?php echo BASE_URL; ?>modules/audit/audit_view.php" class="nav-link">
                <div><i class="fas fa-history"></i> <span class="link-name">Security Logs</span></div>
            </a>
        </li>

        <li class="nav-item">
            <a href="<?php echo BASE_URL; ?>modules/subscription/reports.php" class="nav-link">
                <div><i class="fas fa-chart-pie"></i> <span class="link-name">Advanced Reports</span></div>
            </a>
        </li>
    </ul>

    <div class="sidebar-footer">
        <div class="user-avatar">
            <i class="fas fa-user-shield" style="font-size: 30px; color: #fff;"></i>
        </div>
        
        <div class="user-info">
            <span class="user-name"><?php echo htmlspecialchars($display_name); ?></span>
            <span class="user-role"><?php echo htmlspecialchars($display_role); ?></span>
        </div>
    </div>
</div>