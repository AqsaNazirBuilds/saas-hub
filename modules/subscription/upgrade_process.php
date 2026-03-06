<?php
// modules/subscription/upgrade_process.php
session_start(); // Session start lazmi hai
require_once(__DIR__ . '/../../config/db.php');
require_once(__DIR__ . '/plan_logic.php'); 

// --- LAIBA: Audit log include kiya ---
require_once(__DIR__ . '/../audit/audit.php');
$audit_obj = new AuditLog($db); // Aapka connection variable yahan $db hai

if (isset($_POST['payment_confirmed']) && $_POST['payment_confirmed'] == 'true') {
    
    // Session se tenant_id lein
    $tenant_id = $_SESSION['tenant_id'] ?? 1; 
    $target_plan_id = $_POST['plan_id']; 
    $amount = $_POST['amount']; 
    $new_expiry = date('Y-m-d', strtotime('+30 days'));

    $plan_name = ($target_plan_id == 3) ? 'Premium' : 'Basic';

    // STEP 1: Payment table mein entry
    $pay_query = "INSERT INTO payments (tenant_id, amount, payment_status) VALUES ($tenant_id, $amount, 'success')";
    
    if ($db->query($pay_query)) {
        // STEP 2: Plan upgrade karein
        $upgrade_query = "UPDATE subscriptions SET 
                          plan_id = $target_plan_id, 
                          expiry_date = '$new_expiry', 
                          status = 'active' 
                          WHERE tenant_id = $tenant_id";

        if ($db->query($upgrade_query)) {
            
            // --- LAIBA: Plan Upgrade ka Audit Log yahan add kiya ---
            $audit_obj->logAction($_SESSION['user_id'], "Upgraded to $plan_name Plan (Amount: $amount)", "Subscription", $tenant_id);

            // STEP 3: Notification Table mein entry
            $plan_logic = new PlanLogic($db);
            $plan_logic->add_payment_notification($tenant_id, $amount, $plan_name);

            // SUCCESS
            header("Location: " . BASE_URL . "modules/subscription/reports.php?status=success"); 
            exit();
        } else {
            die("Subscription Update Failed. <a href='" . BASE_URL . "modules/subscription/checkout.php'>Try Again</a>");
        }
    } else {
        die("Payment Record Failed. <a href='" . BASE_URL . "modules/subscription/checkout.php'>Try Again</a>");
    }
} else {
    header("Location: " . BASE_URL . "modules/subscription/checkout.php");
    exit();
}
?>