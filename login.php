<?php
require_once 'config/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!empty($email) && !empty($password)) {
        // Step 1: Select the user from the users table using ONLY the email address
        $stmt = $conn->prepare("SELECT id, tenant_id, password FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();

            // Step 2: Verification of hashed password
            if (password_verify(trim($password), trim($user['password']))) {

                // Step 3: Fetch the user's role
                $userId = $user['id'];
                $role_name = 'user'; // default

                $roleStmt = $conn->prepare("
                    SELECT r.role_name 
                    FROM user_roles ur 
                    JOIN roles r ON ur.role_id = r.id 
                    WHERE ur.user_id = ? LIMIT 1
                ");
                $roleStmt->bind_param("i", $userId);
                $roleStmt->execute();
                $roleResult = $roleStmt->get_result();

                if ($roleResult->num_rows > 0) {
                    $roleRow = $roleResult->fetch_assoc();
                    $role_name = strtolower(str_replace(' ', '_', $roleRow['role_name'] ?? ''));
                }
                else {
                    // Debugging support
                    $error = "[Debug] Role not found for user ID: " . $userId;
                }

                if (session_status() === PHP_SESSION_NONE) {
                    session_start();
                }

                $tenant_id = $user['tenant_id'];

                // Step 4: Logic / Redirection
                if (is_null($tenant_id) && $role_name === 'super_admin') {
                    // Super Admin
                    $_SESSION['user_id'] = $userId;
                    $_SESSION['tenant_id'] = null;
                    $_SESSION['role'] = 'super_admin';
                    header("Location: modules/super_admin/dashboard.php");
                    exit();
                }
                else if (!is_null($tenant_id)) {
                    // Tenant
                    $_SESSION['user_id'] = $userId;
                    $_SESSION['tenant_id'] = $tenant_id;
                    $_SESSION['role'] = $role_name;
                    header("Location: modules/tenant/dashboard.php");
                    exit();
                }
                else {
                    // Fallback
                    $error = "[Debug] Invalid configuration. Tenant ID: " . var_export($tenant_id, true) . ", Role: " . $role_name;
                }
            }
            else {
                // Step 5: Debugging
                $error = "[Debug] Password mismatch."; // Usually "Invalid password" in prod, using debug as requested
            }
        }
        else {
            // Step 5: Debugging
            $error = "[Debug] User not found with email: " . htmlspecialchars($email);
        }
    }
    else {
        $error = "Email and Password are required.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login – SaaS Hub</title>
    <link rel="stylesheet" href="css/aqsa_styles/main.css">
</head>
<body>
<div class="aqsa-page">
    <header class="aqsa-nav">
        <div class="aqsa-nav-inner">
            <a href="index.php" class="aqsa-logo">
                <div class="aqsa-logo-mark">
                    <span></span>
                </div>
                <div class="aqsa-logo-text">
                    <span class="aqsa-logo-title">SaaS Hub</span>
                    <span class="aqsa-logo-subtitle">Multi‑Tenant Control Center</span>
                </div>
            </a>
            <div class="aqsa-nav-cta">
                <a href="modules/tenant/register.php" class="aqsa-btn aqsa-btn-outline">Create tenant</a>
            </div>
        </div>
    </header>

    <main class="aqsa-hero" style="padding-top:40px; padding-bottom:60px;">
        <div class="aqsa-hero-inner" style="grid-template-columns:minmax(0,1.25fr); max-width:420px;">
            <div>
                <div class="aqsa-hero-kicker">
                    <span class="aqsa-kicker-pill">Secure login</span>
                    <span class="aqsa-kicker-text">
                        <span class="aqsa-kicker-dot"></span>
                        Tenant‑scoped access only
                    </span>
                </div>
                <h1 class="aqsa-hero-title" style="font-size:2rem;">
                    Welcome back.
                </h1>
                <p class="aqsa-hero-subtitle">
                    Sign in to manage your company’s users, roles and subscriptions securely.
                </p>

                <?php if (!empty($error) || isset($_GET['error'])): ?>
                    <div class="aqsa-alert aqsa-alert-error" style="margin-bottom:10px;">
                        <?php echo !empty($error) ? htmlspecialchars($error) : 'Invalid email or password. Please try again.'; ?>
                    </div>
                <?php
endif; ?>

                <form action="login.php" method="POST" style="margin-top: 10px;">
                    <div class="aqsa-form-row">
                        <label class="aqsa-label">Email</label>
                        <input class="aqsa-input" type="email" name="email" placeholder="Work email address" required>
                    </div>
                    <div class="aqsa-form-row">
                        <label class="aqsa-label">Password</label>
                        <input class="aqsa-input" type="password" name="password" placeholder="Password" required>
                    </div>

                    <button type="submit" class="aqsa-plan-button aqsa-plan-button-primary" style="width:100%; margin-bottom:10px;">
                        Login now
                    </button>
                    <p class="aqsa-hero-note" style="margin-top:4px;">
                        New here? <strong><a href="modules/tenant/register.php">Create a tenant first</a></strong>.
                    </p>
                </form>
            </div>
        </div>
    </main>
</div>
</body>
</html>