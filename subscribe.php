<?php
require_once 'functions.php';
require_once 'payment-functions.php';

requireAuth();

$user = getCurrentUser();
$currentSubscription = getUserSubscription($user['id']);
$paymentPlans = getPaymentPlans();

// Check if user already has an active subscription
$hasActiveSubscription = $currentSubscription && $currentSubscription['status'] === 'active';

$success = isset($_GET['success']) ? $_GET['success'] : null;
$error = isset($_GET['error']) ? $_GET['error'] : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscription Plans - CMS</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .plans-container {
            display: flex;
            gap: 20px;
            justify-content: center;
            flex-wrap: wrap;
            margin: 20px 0;
        }
        .plan-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            width: 300px;
            text-align: center;
            background: white;
        }
        .plan-card.current {
            border-color: #007cba;
            background: #f0f8ff;
        }
        .plan-card.recommended {
            border-color: #28a745;
            position: relative;
        }
        .plan-card.recommended::before {
            content: "Recommended";
            position: absolute;
            top: -10px;
            left: 50%;
            transform: translateX(-50%);
            background: #28a745;
            color: white;
            padding: 5px 15px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
        }
        .plan-title {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .plan-price {
            font-size: 36px;
            font-weight: bold;
            color: #007cba;
            margin: 15px 0;
        }
        .plan-price .currency {
            font-size: 18px;
            vertical-align: top;
        }
        .plan-price .period {
            font-size: 16px;
            color: #666;
        }
        .plan-description {
            color: #666;
            margin-bottom: 20px;
        }
        .plan-features {
            list-style: none;
            padding: 0;
            margin: 20px 0;
        }
        .plan-features li {
            padding: 5px 0;
            border-bottom: 1px solid #eee;
        }
        .plan-features li:last-child {
            border-bottom: none;
        }
        .subscribe-btn {
            background: #007cba;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            width: 100%;
        }
        .subscribe-btn:hover {
            background: #005a87;
        }
        .subscribe-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        .current-plan {
            background: #28a745;
            color: white;
        }
        .manage-subscription {
            margin-top: 20px;
            text-align: center;
        }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        .alert-success {
            background-color: #d4edda;
            border-color: #c3e6cb;
            color: #155724;
        }
        .alert-error {
            background-color: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
        }
    </style>
</head>
<body>
    <div class="container">
        <nav>
            <h1>CMS Dashboard</h1>
            <div class="nav-links">
                <a href="admin.php">Dashboard</a>
                <a href="subscribe.php" class="active">Subscription</a>
                <a href="logout.php">Logout</a>
            </div>
        </nav>

        <main>
            <h2>Subscription Plans</h2>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php if ($success === 'subscribed'): ?>
                        Congratulations! Your subscription has been activated successfully.
                    <?php elseif ($success === 'cancelled'): ?>
                        Your subscription has been cancelled.
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <?php if ($error === 'payment_failed'): ?>
                        Payment failed. Please try again or contact support.
                    <?php elseif ($error === 'cancelled'): ?>
                        Payment was cancelled. You can try again anytime.
                    <?php else: ?>
                        An error occurred. Please try again.
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($hasActiveSubscription): ?>
                <div class="alert alert-success">
                    <strong>Current Subscription:</strong> <?= htmlspecialchars($currentSubscription['plan_name']) ?> 
                    - Next billing: <?= date('M d, Y', strtotime($currentSubscription['next_billing_date'])) ?>
                </div>
            <?php endif; ?>

            <div class="plans-container">
                <?php foreach ($paymentPlans as $plan): ?>
                    <?php 
                    $isCurrentPlan = $hasActiveSubscription && $currentSubscription['plan_id'] == $plan['id'];
                    $isRecommended = $plan['name'] === 'Professional';
                    ?>
                    <div class="plan-card <?= $isCurrentPlan ? 'current' : '' ?> <?= $isRecommended ? 'recommended' : '' ?>">
                        <div class="plan-title"><?= htmlspecialchars($plan['name']) ?></div>
                        
                        <div class="plan-price">
                            <?php if ($plan['price'] == 0): ?>
                                Free
                            <?php else: ?>
                                <span class="currency">$</span><?= number_format($plan['price'], 0) ?>
                                <span class="period">/month</span>
                            <?php endif; ?>
                        </div>

                        <div class="plan-description"><?= htmlspecialchars($plan['description']) ?></div>

                        <ul class="plan-features">
                            <?php 
                            $features = json_decode($plan['features'], true);
                            if ($features) {
                                foreach ($features as $feature) {
                                    echo '<li>' . htmlspecialchars($feature) . '</li>';
                                }
                            }
                            ?>
                        </ul>

                        <?php if ($isCurrentPlan): ?>
                            <button class="subscribe-btn current-plan" disabled>Current Plan</button>
                        <?php elseif ($plan['price'] == 0): ?>
                            <form method="post" action="process-subscription.php" style="margin: 0;">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="action" value="downgrade">
                                <input type="hidden" name="plan_id" value="<?= $plan['id'] ?>">
                                <button type="submit" class="subscribe-btn">
                                    <?= $hasActiveSubscription ? 'Downgrade to Free' : 'Select Free Plan' ?>
                                </button>
                            </form>
                        <?php else: ?>
                            <form method="post" action="process-subscription.php" style="margin: 0;">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="action" value="subscribe">
                                <input type="hidden" name="plan_id" value="<?= $plan['id'] ?>">
                                <button type="submit" class="subscribe-btn">
                                    <?= $hasActiveSubscription ? 'Upgrade to ' . $plan['name'] : 'Subscribe Now' ?>
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($hasActiveSubscription && $currentSubscription['stripe_subscription_id']): ?>
                <div class="manage-subscription">
                    <h3>Manage Subscription</h3>
                    <p>Need to update your billing information or cancel your subscription?</p>
                    <a href="billing-portal.php" class="subscribe-btn" style="display: inline-block; text-decoration: none; max-width: 200px;">
                        Manage Billing
                    </a>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>