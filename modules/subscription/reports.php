<?php 
// Fixed include path because file is in subscription folder
include(__DIR__ . '/check_access.php'); 
?>
<?php
require_once(__DIR__ . '/../../config/db.php');
require_once(__DIR__ . '/plan_logic.php');

// --- LAIBA: Audit log include kiya ---
require_once(__DIR__ . '/../audit/audit.php');
$audit_obj = new AuditLog($db);

$plan_logic = new PlanLogic($db);

$tid = $_SESSION['tenant_id'] ?? 1;
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'month';

$usage = $plan_logic->get_user_usage($tid); 
$monthly_data = $plan_logic->get_monthly_logins($tid, $filter);
$reg_data = $plan_logic->get_monthly_registrations($tid, $filter);
$sales_data = $plan_logic->get_premium_sales($tid, $filter);
$top_users = $plan_logic->get_top_users($tid, $filter); 
$billing_details = $plan_logic->get_subscription_details($tid);
$recent_activities = $plan_logic->get_recent_activity($tid);

$plan_logic->sync_notifications($tid);

$sql_notif = "SELECT * FROM notifications WHERE tenant_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT 5";
$stmt_n = $db->prepare($sql_notif);
$stmt_n->bind_param("i", $tid);
$stmt_n->execute();
$notifications = $stmt_n->get_result()->fetch_all(MYSQLI_ASSOC);

$total_sales_count = array_sum($sales_data['data']);
$can_download_pdf = ($usage['plan_id'] >= 2); 
$revenue = $plan_logic->get_total_revenue($tid);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Advanced Analytics | Reports</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>css/laiba/reports.css?v=<?php echo time(); ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
</head>
<body>
<?php 
// FIX: Path corrected to same folder

?>

<div class="main-wrapper"> 
    <div class="reports-container">
        
        <div class="report-header" style="margin-bottom: 25px; text-align: center;">
            <h1><i class="fas fa-chart-pie"></i> Business Insights Dashboard</h1>
            <p>Your system's real-time performance overview</p>
        </div>

        <div class="notification-alerts" style="margin-bottom: 20px;">
            <?php foreach($notifications as $notif): ?>
                <div id="notif-<?php echo $notif['id']; ?>" style="background: #fff3cd; border-left: 5px solid #ffca2c; padding: 15px; border-radius: 8px; margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                    <div>
                        <strong style="color: #856404; display: block; font-size: 14px;"><?php echo $notif['title']; ?></strong>
                        <span style="font-size: 13px; color: #666;"><?php echo $notif['message']; ?></span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 15px;">
                        <small style="color: #999; font-size: 11px;"><?php echo date('d M', strtotime($notif['created_at'])); ?></small>
                        <button class="dismiss-btn" onclick="dismissNotif(<?php echo $notif['id']; ?>)" title="Dismiss">
                            <i class="fas fa-times-circle"></i>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="controls-wrapper" id="no-export">
            <div class="filter-section">
                <label for="timeframe" style="font-weight: bold; margin-right: 10px;">View Stats For:</label>
                <select id="timeframe" onchange="updateDashboard()" style="padding: 8px; border-radius: 5px; border: 1px solid #ccc; cursor:pointer;">
                    <option value="7days" <?php echo ($filter == '7days') ? 'selected' : ''; ?>>Last 7 Days</option>
                    <option value="month" <?php echo ($filter == 'month') ? 'selected' : ''; ?>>This Month</option>
                    <option value="6months" <?php echo ($filter == '6months') ? 'selected' : ''; ?>>Last 6 Months</option>
                    <option value="year" <?php echo ($filter == 'year') ? 'selected' : ''; ?>>Full Year</option>
                </select>
            </div>
            <?php if($can_download_pdf): ?>
                <button onclick="downloadPDF()" class="btn-download"><i class="fas fa-file-pdf"></i> Download PDF Report</button>
            <?php else: ?>
                <button class="btn-download btn-locked" onclick="alert('Please upgrade to Premium to download PDF reports')"><i class="fas fa-lock"></i> PDF Locked (Premium Only)</button>
            <?php endif; ?>
        </div>

        <div class="report-content <?php echo ($usage['plan_id'] == 1) ? 'blurred' : ''; ?>">
            <div class="stats-grid">
                <div class="stat-box">
                    <span class="stat-label">Total Users</span>
                    <span class="stat-value"><?php echo $usage['current']; ?> / <?php echo $usage['limit']; ?></span>
                </div>
                <div class="stat-box">
                    <span class="stat-label">Login Count</span>
                    <span class="stat-value"><?php echo $usage['logins_total'] ?? 0; ?></span>
                </div>
                <div class="stat-box">
                    <span class="stat-label">Status</span>
                    <span class="stat-value" style="color: #22c55e;">Active</span>
                </div>
                <div class="stat-box bottom-card">
                    <span class="stat-label">Total Revenue</span>
                    <span class="stat-value" style="color: #22c55e;">$<?php echo number_format($revenue, 2); ?></span>
                </div>
                <div class="stat-box bottom-card">
                    <span class="stat-label">New Registrations</span>
                    <span class="stat-value"><?php echo array_sum($reg_data['data']); ?></span>
                </div>
            </div>

            <div class="charts-double">
                <div class="report-card">
                    <div class="card-title"><i class="fas fa-user-plus" style="color:#0ea5e9;"></i> User Registration</div>
                    <div style="height: 250px;"><canvas id="regChart"></canvas></div>
                </div>
                <div class="report-card">
                    <div class="card-title"><i class="fas fa-shopping-cart" style="color:#22c55e;"></i> Premium Sales</div>
                    <div style="height: 250px;"><canvas id="salesChart"></canvas></div>
                </div>
            </div>

            <div class="report-card">
                <div class="card-title"><i class="fas fa-file-invoice-dollar" style="color: #ff8c42;"></i> Subscription Billing Details</div>
                <table class="custom-table">
                    <thead><tr><th>Plan Name</th><th>Start Date</th><th>Expiry Date</th><th>Days Left</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php foreach($billing_details as $bill): ?>
                        <tr>
                            <td><strong><?php echo $bill['plan_name']; ?></strong></td>
                            <td><?php echo date('M d, Y', strtotime($bill['start_date'])); ?></td>
                            <td><?php echo date('M d, Y', strtotime($bill['expiry_date'])); ?></td>
                            <td><?php echo ($bill['days_remaining'] > 0) ? $bill['days_remaining'] . " Days" : "Expired"; ?></td>
                            <td><span class="status-pill" style="background: <?php echo $bill['color']; ?>"><?php echo $bill['status_tag']; ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="report-card">
                <div class="card-title"><i class="fas fa-crown" style="color: #ff8c42;"></i> Top Active Users</div>
                <?php foreach($top_users as $user): ?>
                    <div class="user-row">
                        <span><a href="<?php echo BASE_URL; ?>modules/subscription/user_details.php?id=<?php echo $user['user_id']; ?>" style="text-decoration: none; color: #1f3b57; font-weight: bold;"><i class="fas fa-user-circle"></i> <?php echo $user['username']; ?></a></span>
                        <span class="badge-count"><?php echo $user['activity_count']; ?> Logins</span>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="report-card">
                <div class="card-title"><i class="fas fa-tasks" style="color: #ff8c42;"></i> Recent System Activity</div>
                <table class="custom-table">
                    <thead><tr><th>Action</th><th>Time & Date</th><th>Result</th></tr></thead>
                    <tbody>
                        <?php foreach($recent_activities as $activity): ?>
                        <tr>
                            <td><strong><?php echo $activity['action']; ?></strong></td>
                            <td><?php echo date('d M, Y | h:i A', strtotime($activity['created_at'])); ?></td>
                            <td><span class="badge-status">Logged</span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="report-card">
                <div class="card-title"><i class="fas fa-history" style="color:#1f3b57;"></i> Monthly Login Activity</div>
                <div style="height: 300px;"><canvas id="usageChart"></canvas></div>
            </div>
        </div> 
    </div> 
</div> 

<script>
    function dismissNotif(id) {
    // Kyunke dono files ek hi folder mein hain, toh sirf file ka naam kaafi hai
    fetch('mark_read.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'id=' + id
    })
    .then(response => response.text())
    .then(data => {
        // Console mein check karne ke liye ke response kya aaya
        console.log("Response from server:", data);
        
        if(data.trim() === 'success') {
            const element = document.getElementById('notif-' + id);
            if(element) {
                element.style.transition = "0.5s ease";
                element.style.opacity = "0";
                element.style.transform = "translateX(20px)"; // Slide effect
                setTimeout(() => element.remove(), 500);
            }
        } else {
            console.error("Error: Server responded with " + data);
        }
    })
    .catch(err => console.error("Fetch error:", err));
}
    function mapData(labels, counts) {
        const months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
        return months.map(m => {
            const i = labels.indexOf(m);
            return i !== -1 ? counts[i] : 0;
        });
    }
    const ms = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

    new Chart(document.getElementById('regChart'), {
        type: 'line',
        data: { labels: ms, datasets: [{ label: 'Users', data: mapData(<?php echo json_encode($reg_data['labels'] ?? []); ?>, <?php echo json_encode($reg_data['data'] ?? []); ?>), borderColor: '#0ea5e9', backgroundColor: 'rgba(14, 165, 233, 0.1)', fill: true, tension: 0.4 }] },
        options: { responsive: true, maintainAspectRatio: false }
    });

    new Chart(document.getElementById('salesChart'), {
        type: 'bar',
        data: { labels: ms, datasets: [{ label: 'Sales', data: mapData(<?php echo json_encode($sales_data['labels'] ?? []); ?>, <?php echo json_encode($sales_data['data'] ?? []); ?>), backgroundColor: '#22c55e', borderRadius: 5 }] },
        options: { responsive: true, maintainAspectRatio: false }
    });

    new Chart(document.getElementById('usageChart'), {
        type: 'bar',
        data: { labels: ms, datasets: [{ label: 'Logins', data: mapData(<?php echo json_encode($monthly_data['labels'] ?? []); ?>, <?php echo json_encode($monthly_data['data'] ?? []); ?>), backgroundColor: '#1f3b57', borderRadius: 8 }] },
        options: { responsive: true, maintainAspectRatio: false }
    });

    function downloadPDF() {

       // --- LAIBA: Background mein Audit Log save karne ke liye ---
    fetch('<?php echo BASE_URL; ?>modules/audit/log_ajax.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=Downloaded PDF Business Report&module=Reports'
    });
        const element = document.querySelector('.report-content'); 
        const noExport = document.getElementById('no-export');
        noExport.style.display = 'none';
        const opt = { margin: 10, filename: 'Report.pdf', image: { type: 'jpeg', quality: 0.98 }, html2canvas: { scale: 2 }, jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' } };
        html2pdf().set(opt).from(element).save().then(() => { noExport.style.display = 'flex'; });
    }

    function updateDashboard() {
        // FIX: Redirect path ensured for subscription folder
        window.location.href = "<?php echo BASE_URL; ?>modules/subscription/reports.php?filter=" + document.getElementById('timeframe').value;
    }
</script>
</body>
</html>