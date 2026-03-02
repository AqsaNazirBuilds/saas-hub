<?php
include('../../config/db.php');

$error = '';
$success = '';

function aqsa_slugify(string $value): string {
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value);
    $value = trim($value ?? '', '-');
    return $value;
}

$company_name = '';
$domain_slug = '';
$admin_name = '';
$admin_email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $company_name = trim($_POST['company_name'] ?? '');
    $domain_slug  = aqsa_slugify($_POST['domain_slug'] ?? '');
    $admin_name   = trim($_POST['admin_name'] ?? '');
    $admin_email  = trim($_POST['admin_email'] ?? '');
    $password     = (string)($_POST['password'] ?? '');

    if ($company_name === '' || $domain_slug === '' || $admin_name === '' || $admin_email === '') {
        $error = 'Please fill all required fields.';
    } elseif (!filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif (strlen($domain_slug) < 3) {
        $error = 'Domain slug must be at least 3 characters.';
    } else {
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);

        $conn->begin_transaction();
        try {
            // Ensure unique tenant slug
            $check = $conn->prepare("SELECT id FROM tenants WHERE domain_slug = ? LIMIT 1");
            $check->bind_param("s", $domain_slug);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                throw new Exception('This domain slug is already taken. Try a different one.');
            }

            // Ensure admin email not already used globally (simple safety)
            $check = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $check->bind_param("s", $admin_email);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                throw new Exception('This email is already registered. Please login instead.');
            }

            // 1) Create Tenant (trial)
            $stmt = $conn->prepare("INSERT INTO tenants (company_name, domain_slug, status) VALUES (?, ?, 'trial')");
            $stmt->bind_param("ss", $company_name, $domain_slug);
            $stmt->execute();
            $tenant_id = $conn->insert_id;

            // 2) Create first user as Company Admin (role system handled by Masoom later)
            $stmt = $conn->prepare("INSERT INTO users (tenant_id, name, email, password, status) VALUES (?, ?, ?, ?, 'active')");
            $stmt->bind_param("isss", $tenant_id, $admin_name, $admin_email, $hashed_password);
            $stmt->execute();

            // 3) Assign 7-day free trial subscription record (business logic handled by Laiba later)
            $start_date  = date('Y-m-d');
            $expiry_date = date('Y-m-d', strtotime('+7 days'));
            $plan_id = 1;
            $stmt = $conn->prepare("INSERT INTO subscriptions (tenant_id, plan_id, start_date, expiry_date, status) VALUES (?, ?, ?, ?, 'active')");
            $stmt->bind_param("iiss", $tenant_id, $plan_id, $start_date, $expiry_date);
            $stmt->execute();

            $conn->commit();
            $success = 'Tenant registered successfully. You can now log in to manage users.';
        } catch (Exception $e) {
            $conn->rollback();
            $error = $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Tenant – SaaS Hub</title>
    <link rel="stylesheet" href="../../css/aqsa_styles/main.css">
</head>
<body>
<div class="aqsa-page">
    <header class="aqsa-nav">
        <div class="aqsa-nav-inner">
            <a href="../../index.php" class="aqsa-logo">
                <div class="aqsa-logo-mark"><span></span></div>
                <div>
                    <span class="aqsa-logo-title">SaaS Hub</span>
                    <span class="aqsa-logo-subtitle">Create a tenant workspace</span>
                </div>
            </a>
            <div class="aqsa-nav-cta">
                <a href="../../login.php" class="aqsa-btn aqsa-btn-outline">Login</a>
                <a href="../../index.php#pricing" class="aqsa-btn aqsa-btn-primary">View pricing</a>
            </div>
        </div>
    </header>

    <main class="aqsa-hero" style="padding-top:34px; padding-bottom:60px;">
        <div class="aqsa-hero-inner" style="grid-template-columns:minmax(0,1.25fr); max-width:640px;">
            <div class="aqsa-hero-panel" style="padding:20px;">
                <div class="aqsa-hero-kicker" style="margin-bottom:12px;">
                    <span class="aqsa-kicker-pill">Registration</span>
                    <span class="aqsa-kicker-text">
                        <span class="aqsa-kicker-dot"></span>
                        Tenant isolation starts here
                    </span>
                </div>
                <h1 class="aqsa-hero-title" style="font-size:2rem; margin-top:0;">
                    Create your <span>company tenant</span>.
                </h1>
                <p class="aqsa-hero-subtitle" style="max-width:none;">
                    This will create your company workspace, generate a unique tenant ID, and register the
                    first user as the Company Admin with a 7‑day free trial.
                </p>

                <?php if ($error): ?>
                    <div class="aqsa-alert aqsa-alert-error" style="margin-bottom:12px;">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="aqsa-alert aqsa-alert-success" style="margin-bottom:12px;">
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                    <div style="display:flex; gap:10px; flex-wrap:wrap;">
                        <a class="aqsa-btn aqsa-btn-primary" href="../../login.php">Go to login</a>
                        <a class="aqsa-btn aqsa-btn-outline" href="../../index.php">Back to landing</a>
                    </div>
                <?php else: ?>
                    <form method="POST" autocomplete="off" style="margin-top:12px;">
                        <div class="aqsa-form-row">
                            <label class="aqsa-label">Company name</label>
                            <input class="aqsa-input" type="text" name="company_name" value="<?php echo htmlspecialchars($company_name); ?>" placeholder="e.g. Macro Solutions Tools Ltd" required>
                        </div>

                        <div class="aqsa-form-row">
                            <label class="aqsa-label">Domain slug</label>
                            <input class="aqsa-input" type="text" name="domain_slug" value="<?php echo htmlspecialchars($domain_slug); ?>" placeholder="e.g. macrotools" required>
                            <p class="aqsa-hero-note" style="margin:8px 0 0;">
                                Lowercase letters + numbers only. We’ll auto-format it to a safe slug.
                            </p>
                        </div>

                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                            <div class="aqsa-form-row">
                                <label class="aqsa-label">Admin full name</label>
                                <input class="aqsa-input" type="text" name="admin_name" value="<?php echo htmlspecialchars($admin_name); ?>" placeholder="e.g. Aqsa" required>
                            </div>
                            <div class="aqsa-form-row">
                                <label class="aqsa-label">Admin email</label>
                                <input class="aqsa-input" type="email" name="admin_email" value="<?php echo htmlspecialchars($admin_email); ?>" placeholder="aqsa@company.com" required>
                            </div>
                        </div>

                        <div class="aqsa-form-row">
                            <label class="aqsa-label">Password</label>
                            <input class="aqsa-input" type="password" name="password" placeholder="Minimum 6 characters" required>
                        </div>

                        <button class="aqsa-plan-button aqsa-plan-button-primary" type="submit">
                            Create tenant &amp; start trial
                        </button>

                        <p class="aqsa-hero-note" style="margin-top:12px;">
                            Already have an account? <strong><a href="../../login.php">Login</a></strong>.
                        </p>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>
</body>
</html>