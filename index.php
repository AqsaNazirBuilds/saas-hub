<?php
// Include database connection
require_once 'config/db.php';

// Fetch plans from database
$plans_query = "SELECT * FROM plans";
$plans_result = $conn->query($plans_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SaaS Hub – The Future of Business Management</title>
    <!-- Dark, futuristic Dribbble theme -->
    <link rel="stylesheet" href="css/futuristic.css">
</head>
<body>
    <div class="blob-bg blob-1"></div>
    <div class="blob-bg blob-2"></div>
    <div class="glass-container">
        
        <!-- Navigation -->
        <nav class="navbar">
            <a href="#" class="logo">
                <span class="logo-icon">💠</span>
                <span class="logo-text">SaaS Hub</span>
            </a>
            <div class="nav-links">
                <a href="#features">Features</a>
                <a href="#pricing">Pricing</a>
            </div>
            <div class="nav-actions">
                <a href="login.php" class="btn btn-ghost">Login</a>
                <a href="modules/tenant/register.php" class="btn btn-primary">Start Your Free Trial</a>
            </div>
        </nav>

        <!-- Hero Section -->
        <section class="hero">
            <h1 class="hero-title">The Future of Business Management</h1>
            <p class="hero-subtitle">Experience a cutting-edge multi-tenant control center designed to elevate your operational efficiency. Secure, scalable, and built for tomorrow.</p>
            <div class="hero-cta">
                <a href="modules/tenant/register.php" class="btn btn-primary btn-lg">Start Your Free Trial</a>
            </div>
        </section>

        <!-- Features Grid -->
        <section id="features" class="features">
            <div class="section-header">
                <h2>Why Choose SaaS Hub?</h2>
                <p>Designed natively for multi-tenant isolation</p>
            </div>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">🛡️</div>
                    <h3 class="feature-title">Multi-Tenant Security</h3>
                    <p class="feature-desc">Uncompromised isolation and robust data protection across all your workspaces.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">👥</div>
                    <h3 class="feature-title">Advanced User Management</h3>
                    <p class="feature-desc">Granular control over specific roles, access levels, and user lifecycles across tenants.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">📈</div>
                    <h3 class="feature-title">Real-time Analytics</h3>
                    <p class="feature-desc">Deep insights into core metrics, user activities, and subscription health live.</p>
                </div>
            </div>
        </section>

        <!-- Pricing Section -->
        <section id="pricing" class="pricing">
            <div class="section-header">
                <h2>Subscription Plans</h2>
                <p>Flexible pricing powered by our subscription logic module.</p>
            </div>
            <div class="pricing-grid">
                <?php
if ($plans_result && $plans_result->num_rows > 0) {
    while ($plan = $plans_result->fetch_assoc()) {
        $is_popular = strtolower($plan['plan_name']) === 'basic';
        $price = floatval($plan['price']);

        // Handle features dynamically
        $features_json = isset($plan['features_json']) ? json_decode($plan['features_json'], true) : [];
        $user_limit = $plan['user_limit'] ?? 'Unlimited';
?>
                        <div class="pricing-card <?php echo $is_popular ? 'popular' : ''; ?>">
                            <?php if ($is_popular): ?>
                                <div class="badge">Most Popular</div>
                            <?php
        endif; ?>
                            
                            <h3 class="plan-name"><?php echo htmlspecialchars($plan['plan_name']); ?></h3>
                            <div class="plan-price">$<?php echo $price; ?><span>/ <?php echo htmlspecialchars($plan['billing_cycle'] ?? 'month'); ?></span></div>
                            <p class="plan-meta">Structured toolkit tailored for focused teams.</p>
                            <ul class="plan-features">
                                <li><?php echo $user_limit; ?> users per tenant</li>
                                <?php
        if (!empty($features_json) && is_array($features_json)) {
            foreach ($features_json as $feature_name => $has_feature) {
                if ($has_feature) {
                    $display_feature = ucwords(str_replace('_', ' ', $feature_name));
                    echo "<li>{$display_feature}</li>";
                }
            }
        }
        else {
            echo "<li>Core capabilities unlocked</li>";
        }
?>
                            </ul>
                            <!-- Redirects to checkout -->
                            <a href="modules/subscription/checkout.php?plan_id=<?php echo $plan['id']; ?>" class="btn <?php echo $is_popular ? 'btn-primary' : 'btn-ghost'; ?> plan-btn">Select Plan</a>
                        </div>
                        <?php
    }
}
else {
    echo "<p style='text-align:center;'>No pricing plans available at the moment.</p>";
}
?>
            </div>
        </section>

        <!-- Footer -->
        <footer>
            <div class="footer-content">
                <p>&copy; <?php echo date('Y'); ?> SaaS Hub &bull; Internship Project</p>
                <div class="footer-links">
                    <a href="#features">Features</a>
                    <a href="#pricing">Pricing</a>
                </div>
            </div>
        </footer>
        
    </div>
</body>
</html>