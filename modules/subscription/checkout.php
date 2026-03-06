<?php
// modules/subscription/checkout.php
session_start(); // Session start zaroori hai audit log ke liye
require_once(__DIR__ . '/../../config/db.php');

// --- LAIBA: Audit log include kiya ---
require_once(__DIR__ . '/../audit/audit.php');
$audit_obj = new AuditLog($db);

// Record karein ke user ne checkout page dekha
if (isset($_SESSION['user_id'])) {
    $audit_obj->logAction($_SESSION['user_id'], "Viewed Subscription Plans", "Subscription", $_SESSION['tenant_id'] ?? 0);
}

// Database se Basic (2) aur Premium (3) plans uthayein
$plans_query = "SELECT * FROM plans WHERE id IN (2, 3)"; 
$plans_result = $db->query($plans_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Select Your Plan</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f4f7f6; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .checkout-container { width: 90%; max-width: 800px; text-align: center; }
        .plans-flex { display: flex; gap: 20px; justify-content: center; margin-top: 30px; }
        .plan-card { background: white; padding: 25px; border-radius: 15px; box-shadow: 0 10px 20px rgba(0,0,0,0.05); width: 280px; border: 2px solid #e2e8f0; }
        .plan-name { font-size: 20px; font-weight: bold; color: #1f3b57; text-transform: uppercase; }
        .price-tag { font-size: 35px; font-weight: 800; color: #3b82f6; margin: 15px 0; }
        .btn-pay { background: #22c55e; color: white; border: none; padding: 12px; width: 100%; border-radius: 8px; font-weight: bold; cursor: pointer; transition: 0.3s; }
        .btn-pay:hover { background: #16a34a; }
        .feature-list { text-align: left; font-size: 14px; color: #64748b; list-style: none; padding: 0; margin: 20px 0; }
    </style>
</head>
<body>

<div class="checkout-container">
    <h2 style="color: #1f3b57;">Choose Your Plan</h2>
    <p style="color: #64748b;">Upgrade to unlock more features</p>

    <div class="plans-flex">
        <?php while($plan = $plans_result->fetch_assoc()): ?>
            <div class="plan-card">
                <div class="plan-name"><?php echo $plan['plan_name']; ?></div>
                <div class="price-tag">$<?php echo number_format($plan['price'], 2); ?></div>
                
                <ul class="feature-list">
                    <li><i class="fas fa-check" style="color: #22c55e;"></i> Limit: <?php echo $plan['user_limit']; ?> Users</li>
                    <li><i class="fas fa-check" style="color: #22c55e;"></i> Cycle: <?php echo $plan['billing_cycle']; ?></li>
                </ul>

                <form action="<?php echo BASE_URL; ?>modules/subscription/upgrade_process.php" method="POST">
                    <input type="hidden" name="payment_confirmed" value="true">
                    <input type="hidden" name="plan_id" value="<?php echo $plan['id']; ?>">
                    <input type="hidden" name="amount" value="<?php echo $plan['price']; ?>">
                    
                    <button type="submit" class="btn-pay">
                        Activate <?php echo $plan['plan_name']; ?>
                    </button>
                </form>
            </div>
        <?php endwhile; ?>
    </div>
</div>

</body>
</html>