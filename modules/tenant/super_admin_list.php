<?php
session_start();
require_once __DIR__ . '/../../config/db.php';

// Restrict access to Super Admin only
if (empty($_SESSION['is_super_admin'])) {
    http_response_code(403);
    die('Access denied: Super Admin only.');
}

// Fetch all tenants with basic subscription info
$query = "SELECT t.id, t.company_name, t.domain_slug, t.status AS tenant_status, 
                 s.expiry_date, p.plan_name 
          FROM tenants t
          LEFT JOIN subscriptions s ON t.id = s.tenant_id
          LEFT JOIN plans p ON s.plan_id = p.id
          ORDER BY t.id ASC";

$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Super Admin – Tenants Overview</title>
    <link rel="stylesheet" href="../../css/aqsa_styles/main.css">
</head>
<body>
<div class="aqsa-page">
    <main class="aqsa-hero" style="padding-top:32px; padding-bottom:50px;">
        <div class="aqsa-hero-inner" style="grid-template-columns:minmax(0,1fr); max-width:900px;">
            <div>
                <div class="aqsa-hero-kicker">
                    <span class="aqsa-kicker-pill">Super Admin</span>
                    <span class="aqsa-kicker-text">
                        <span class="aqsa-kicker-dot"></span>
                        Global overview of all tenants
                    </span>
                </div>
                <h1 class="aqsa-hero-title" style="font-size:2rem;">
                    Tenant &amp; subscription status.
                </h1>
                <p class="aqsa-hero-subtitle">
                    View every company registered in the system along with its plan, expiry and
                    quick links into Laiba’s subscription status dashboard.
                </p>

                <div style="border-radius:18px; border:1px solid rgba(168,185,255,0.25); overflow:hidden; background:rgba(12,16,38,0.7); margin-top:18px;">
                    <table style="width:100%; border-collapse:collapse; font-size:0.8rem;">
                        <thead>
                            <tr style="background:rgba(15,23,42,0.95);">
                                <th style="text-align:left; padding:10px 12px; border-bottom:1px solid rgba(168,185,255,0.25);">ID</th>
                                <th style="text-align:left; padding:10px 12px; border-bottom:1px solid rgba(168,185,255,0.25);">Company</th>
                                <th style="text-align:left; padding:10px 12px; border-bottom:1px solid rgba(168,185,255,0.25);">Slug</th>
                                <th style="text-align:left; padding:10px 12px; border-bottom:1px solid rgba(168,185,255,0.25);">Plan</th>
                                <th style="text-align:left; padding:10px 12px; border-bottom:1px solid rgba(168,185,255,0.25);">Expiry</th>
                                <th style="text-align:left; padding:10px 12px; border-bottom:1px solid rgba(168,185,255,0.25);">Status</th>
                                <th style="text-align:left; padding:10px 12px; border-bottom:1px solid rgba(168,185,255,0.25);">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = $result->fetch_assoc()): ?>
                                <?php
                                    $statusUpper = strtoupper($row['tenant_status']);
                                ?>
                                <tr style="border-bottom:1px solid rgba(15,23,42,0.9);">
                                    <td style="padding:9px 12px;"><?php echo (int)$row['id']; ?></td>
                                    <td style="padding:9px 12px;"><?php echo htmlspecialchars($row['company_name']); ?></td>
                                    <td style="padding:9px 12px;"><?php echo htmlspecialchars($row['domain_slug']); ?></td>
                                    <td style="padding:9px 12px;"><?php echo htmlspecialchars($row['plan_name'] ?? 'No Plan'); ?></td>
                                    <td style="padding:9px 12px;"><?php echo htmlspecialchars($row['expiry_date'] ?? 'N/A'); ?></td>
                                    <td style="padding:9px 12px;"><?php echo $statusUpper; ?></td>
                                    <td style="padding:9px 12px;">
                                        <a href="view_details.php?id=<?php echo (int)$row['id']; ?>" class="aqsa-btn aqsa-btn-outline" style="padding:3px 10px; font-size:0.72rem; margin-right:4px;">
                                            View
                                        </a>
                                        <a href="actions.php?action=suspend&id=<?php echo (int)$row['id']; ?>" class="aqsa-btn aqsa-btn-outline" style="padding:3px 10px; font-size:0.72rem; margin-right:4px;">
                                            Suspend
                                        </a>
                                        <a href="actions.php?action=delete&id=<?php echo (int)$row['id']; ?>" class="aqsa-btn aqsa-btn-outline" style="padding:3px 10px; font-size:0.72rem; border-color:#ef4444; color:#fecaca;" onclick="return confirm('Are you sure?')">
                                            Delete
                                        </a>
                                        <a href="actions.php?action=subscription&id=<?php echo (int)$row['id']; ?>" class="aqsa-btn aqsa-btn-primary" style="padding:3px 10px; font-size:0.72rem; margin-left:4px;">
                                            Subscription
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>
</body>
</html>