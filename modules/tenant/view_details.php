<?php
session_start();
include('../../config/db.php');

// Allow only logged-in users; if not a super admin, you should normally not hit this page directly
if (empty($_SESSION['is_super_admin'])) {
    // Optional: basic guard; adjust if you later want tenant-scoped access here
    http_response_code(403);
    die('Access denied: Super Admin only.');
}

// URL se company ki ID lein
if (!isset($_GET['id'])) {
    die("Error: No Tenant ID provided.");
}

$view_id = intval($_GET['id']);

// Complex Query: Company info + Plan name + Expiry + User count [cite: 77, 78, 112]
$query = "SELECT t.id, t.company_name, t.domain_slug, t.status, 
                 s.expiry_date, p.plan_name,
                 (SELECT COUNT(*) FROM users WHERE tenant_id = t.id) as total_users
          FROM tenants t
          LEFT JOIN subscriptions s ON t.id = s.tenant_id
          LEFT JOIN plans p ON s.plan_id = p.id
          WHERE t.id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $view_id);
$stmt->execute();
$details = $stmt->get_result()->fetch_assoc();

if (!$details) {
    die("Error: Tenant not found.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Tenant Details - <?php echo $details['company_name']; ?></title>
    <link rel="stylesheet" href="../../css/aqsa_styles/main.css">
</head>
<body>
    <div class="aqsa-page">
        <main class="aqsa-hero" style="padding-top:32px; padding-bottom:50px;">
            <div class="aqsa-hero-inner" style="grid-template-columns:minmax(0,1.1fr); max-width:640px;">
                <div class="aqsa-hero-panel" style="padding:20px;">
                    <div class="aqsa-hero-kicker" style="margin-bottom:12px;">
                        <span class="aqsa-kicker-pill">Tenant details</span>
                        <span class="aqsa-kicker-text">
                            <span class="aqsa-kicker-dot"></span>
                            ID #<?php echo (int)$details['id']; ?>
                        </span>
                    </div>
                    <h2 class="aqsa-hero-title" style="font-size:1.7rem; margin-top:0;">
                        <?php echo htmlspecialchars($details['company_name']); ?>
                    </h2>
                    <p class="aqsa-hero-subtitle" style="max-width:none;">
                        Domain slug <strong><?php echo htmlspecialchars($details['domain_slug']); ?></strong> ·
                        Status <strong><?php echo strtoupper($details['status']); ?></strong>
                    </p>

                    <div style="margin-top:16px;">
                        <h3 class="aqsa-section-title" style="font-size:1rem; margin-bottom:6px;">Subscription</h3>
                        <p class="aqsa-hero-note">
                            Plan: <strong><?php echo htmlspecialchars($details['plan_name'] ?? 'No Plan Assigned'); ?></strong><br>
                            Expiry: <strong><?php echo htmlspecialchars($details['expiry_date'] ?? 'N/A'); ?></strong>
                        </p>
                    </div>

                    <div style="margin-top:16px;">
                        <h3 class="aqsa-section-title" style="font-size:1rem; margin-bottom:6px;">Usage</h3>
                        <p class="aqsa-hero-note">
                            Total users registered: <strong><?php echo (int)$details['total_users']; ?></strong>
                        </p>
                    </div>

                    <div style="margin-top:20px; display:flex; gap:10px; flex-wrap:wrap;">
                        <a href="super_admin_list.php" class="aqsa-btn aqsa-btn-outline">← Back to list</a>
                        <a href="actions.php?action=subscription&id=<?php echo (int)$details['id']; ?>" class="aqsa-btn aqsa-btn-primary">
                            Open subscription dashboard
                        </a>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>