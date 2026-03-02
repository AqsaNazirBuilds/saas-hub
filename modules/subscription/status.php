<?php
// modules/subscription/status.php
require_once(__DIR__ . '/../../config/db.php');

require_once(__DIR__ . '/subscription.php');
require_once(__DIR__ . '/plan_logic.php');


$sub_logic = new Subscription($db);
$plan_logic = new PlanLogic($db);

$tenant_id = $_SESSION['tenant_id'] ?? 1;

// 1. Check if blocked
$is_blocked = $plan_logic->is_subscription_blocked($tenant_id);


// Super Admin Bypass
if (isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin') {
    $is_blocked = false;
}

if ($is_blocked) {
    // Blocked screen with dynamic BASE_URL
    die("
    <div style='height: 100vh; display: flex; align-items: center; justify-content: center; background: #f8fafc; font-family: sans-serif;'>
        <div style='background: white; padding: 40px; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); text-align: center; max-width: 500px; border-top: 5px solid #ef4444;'>
            <div style='font-size: 50px; margin-bottom: 20px;'>🚫</div>
            <h2 style='color: #1e293b; margin-bottom: 15px;'>SYSTEM BLOCKED</h2>
            <p style='color: #64748b; margin-bottom: 25px;'>Aapka plan khatam ho chuka hai. Agay barhne ke liye apna plan upgrade karein.</p>
            <a href='" . BASE_URL . "modules/subscription/checkout.php' style='display: inline-block; background: #1f3b57; color: white; padding: 12px 30px; border-radius: 8px; text-decoration: none; font-weight: bold;'>Upgrade to Premium</a>
            <div style='margin-top: 20px;'>
                <a href='" . BASE_URL . "index.php' style='color: #94a3b8; text-decoration: none; font-size: 14px;'>Back to Home</a>
            </div>
        </div>
    </div>
    ");
}

$my_sub = $sub_logic->get_active_subscription($tenant_id);
$is_active = $sub_logic->is_valid($my_sub);
$usage = $plan_logic->get_user_usage($tenant_id);


$current_plan_name = "N/A";
if ($usage['plan_id'] == 1)
    $current_plan_name = "Free Trial";
elseif ($usage['plan_id'] == 2)
    $current_plan_name = "Basic Plan";
elseif ($usage['plan_id'] == 3)
    $current_plan_name = "Premium Plan";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Subscription Dashboard</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>css/laiba/status.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
</head>
<body>
<?php include(__DIR__ . '/sidebar.php'); ?>
<div class="main-wrapper">
    <div class="status-card">
        <div class="card-header">
            <div class="header-left">
                <h2><i class="fas fa-rocket" style="color: #fff; margin-right: 10px;"></i> Manage Your Business</h2>
                <p>Track your subscription & features</p>
            </div>
            <span class="status-badge <?php echo $is_active ? 'active' : 'expired'; ?>">
                ● <?php echo $is_active ? 'Active' : 'Expired'; ?>
            </span>
        </div>

        <div class="card-content">
            <div class="info-grid">
                <div class="info-box">
                    <span class="box-label"><i class="fas fa-crown"></i> CURRENT PLAN</span>
                    <h3 class="box-value"><?php echo $current_plan_name; ?></h3>
                </div>
                <div class="info-box">
                    <span class="box-label"><i class="fas fa-calendar-alt"></i> EXPIRY DATE</span>
                    <h3 class="box-value"><?php echo $my_sub['expiry_date'] ?? 'N/A'; ?></h3>
                </div>
                <div class="info-box">
                    <span class="box-label"><i class="fas fa-users"></i> USER LIMIT</span>
                    <h3 class="box-value"><?php echo $usage['limit']; ?></h3>
                </div>
            </div>

            <div class="usage-analytics-box" style="margin-top: 20px; border: 1px solid #e2e8f0; border-radius: 12px; padding: 15px;">
                <div class="usage-header" style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                    <span style="font-weight: 600; color: #1f3b57;"><i class="fas fa-chart-bar"></i> User Growth Analysis</span>
                    <span style="font-size: 13px; color: #64748b;">Current Usage: <?php echo $usage['percentage']; ?>%</span>
                </div>
                
                <div style="display: flex; align-items: flex-end; justify-content: space-around; height: 120px; padding-top: 20px; border-bottom: 2px solid #f1f5f9;">
                    <div style="width: 25px; height: 30%; background: #e2e8f0; border-radius: 4px 4px 0 0;"></div>
                    <div style="width: 25px; height: 55%; background: #e2e8f0; border-radius: 4px 4px 0 0;"></div>
                    <div style="width: 25px; height: 45%; background: #e2e8f0; border-radius: 4px 4px 0 0;"></div>
                    <div style="width: 25px; height: 75%; background: #e2e8f0; border-radius: 4px 4px 0 0;"></div>
                    <div style="width: 25px; height: <?php echo $usage['percentage']; ?>%; background: linear-gradient(to top, #1f3b57, #3b82f6); border-radius: 4px 4px 0 0; position: relative;">
                        <span style="position: absolute; top: -22px; left: 50%; transform: translateX(-50%); font-size: 10px; font-weight: bold; color: #1f3b57;"><?php echo $usage['current']; ?></span>
                    </div>
                </div>
                <div style="display: flex; justify-content: space-around; margin-top: 8px; color: #94a3b8; font-size: 11px; font-weight: 600;">
                    <span>Jan</span><span>Feb</span><span>Mar</span><span>Apr</span><span>Current</span>
                </div>
            </div>

            <div class="action-area" style="margin-top: 30px; text-align: center;">
                <?php if ($usage['plan_id'] != 3): ?>
                    <a href="<?php echo BASE_URL; ?>modules/subscription/checkout.php" class="btn-manage" style="display: block; width: 100%; padding: 14px; background: #1f3b57; color: white; border: none; border-radius: 8px; font-weight: 700; text-decoration: none; box-sizing: border-box; transition: 0.3s; cursor: pointer;">
                        <i class="fas fa-arrow-up"></i> Upgrade to Premium Plan
                    </a>
                <?php
else: ?>
                    <div style="padding: 14px; background: #22c55e; color: white; border-radius: 8px; font-weight: 700;">
                        <i class="fas fa-check-double"></i> You are on Premium Plan
                    </div>
                <?php
endif; ?>
                
                <div style="margin-top: 15px;">
                    <a href="<?php echo BASE_URL; ?>modules/subscription/reports.php" style="color: #64748b; text-decoration: none; font-size: 14px; font-weight: 600;">
                        <i class="fas fa-chevron-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>

        </div>
    </div>
</div>

</body>
</html>