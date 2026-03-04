<?php
// modules/users/user_details.php
require_once(__DIR__ . '/../../config/db.php');

$user_id = isset($_GET['id']) ? $_GET['id'] : null;

if (!$user_id) {
    die("User ID missing!");
}

// 1. User ki basic info nikalna
$user_sql = "SELECT name, email FROM users WHERE id = ?";
$stmt = $db->prepare($user_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_info = $stmt->get_result()->fetch_assoc();

// 2. Us user ke Audit Logs nikalna
$log_sql = "SELECT action, created_at FROM audit_logs WHERE user_id = ? ORDER BY created_at DESC";
$stmt_log = $db->prepare($log_sql);
$stmt_log->bind_param("i", $user_id);
$stmt_log->execute();
$logs = $stmt_log->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Activity Profile | Analytics</title>
    <link rel="stylesheet" href="../../css/laiba/user_details.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>css/laiba/user_details.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { margin: 0; padding: 0; background: #f1f5f9; }
    </style>
</head>
<body>


<div class="main-wrapper">

    <div class="container">
        <h1 class="main-title">USER ACTIVITY PROFILE</h1>

        <div class="content-wrapper">
            <div class="profile-sidebar">
                <div class="card profile-card shadow">
                    <div class="profile-img-container">
                        <img src="https://cdn-icons-png.flaticon.com/512/4140/4140047.png" alt="Profile avatar" class="profile-avatar">
                    </div>
                    
                    <div class="profile-header-info">
                        <h2 class="user-display-name"><?php echo $user_info['name'] ?? 'Unknown User'; ?></h2>
                        <p class="user-display-role">System User</p>
                    </div>
                    
                    <hr class="divider">

                    <div class="info-details-box">
                        <div class="info-group">
                            <label><i class="fas fa-envelope-open-text"></i> EMAIL ADDRESS</label>
                            <p class="highlight-text"><?php echo $user_info['email'] ?? 'No email found'; ?></p>
                        </div>
                        
                        <div class="info-group">
                            <label><i class="fas fa-user-shield"></i> INFO STATUS</label>
                            <div class="status-badge-container">
                                 <span class="status-badge active-status">
                                    <i class="fas fa-circle"></i> Active User
                                </span>
                            </div>
                        </div>

                        <div class="info-group">
                            <label><i class="fas fa-calendar-check"></i> ACCOUNT STATUS</label>
                            <p class="highlight-text">Verified Account</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="logs-main">
                <div class="card shadow">
                    <h3 class="section-heading">
                        <span><i class="fas fa-list-ul"></i> Detailed Audit Logs</span>
                        <i class="fas fa-history muted-icon"></i>
                    </h3>
                    
                    <div class="table-container">
                        <table class="custom-table">
                            <thead>
                                <tr>
                                    <th>Action Performed</th>
                                    <th>Date & Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if($logs->num_rows > 0): ?>
                                    <?php while($row = $logs->fetch_assoc()): ?>
                                    <tr>
                                        <td><span class="action-text"><?php echo $row['action']; ?></span></td>
                                        <td class="time-text"><?php echo date('d M Y | h:i A', strtotime($row['created_at'])); ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="2" style="text-align:center; padding: 30px;">No activity logs found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="footer-actions">
            <a href="<?php echo BASE_URL; ?>modules/subscription/reports.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> BACK TO DASHBOARD
            </a>
        </div>
    </div> 
</div> 
</body>
</html>