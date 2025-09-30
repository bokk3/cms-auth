<?php
require_once 'functions.php';
require_once 'payment-functions.php';

requireAuth();

// CSRF protection
if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
    header('Location: subscribe.php?error=invalid_token');
    exit;
}

$user = getCurrentUser();
$action = $_POST['action'] ?? '';
$planId = $_POST['plan_id'] ?? '';

if (!$planId) {
    header('Location: subscribe.php?error=invalid_plan');
    exit;
}

// Get the selected plan
$plan = getPaymentPlan($planId);
if (!$plan) {
    header('Location: subscribe.php?error=plan_not_found');
    exit;
}

try {
    if ($action === 'subscribe') {
        // Handle paid subscription
        if ($plan['price'] > 0) {
            // Create Stripe checkout session
            $checkoutUrl = createSubscriptionCheckout($user['id'], $planId, [
                'success_url' => getBaseUrl() . '/payment-success.php?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => getBaseUrl() . '/subscribe.php?error=cancelled'
            ]);
            
            header('Location: ' . $checkoutUrl);
            exit;
        } else {
            // Handle free plan subscription
            assignFreePlan($user['id'], $planId);
            header('Location: subscribe.php?success=subscribed');
            exit;
        }
    } elseif ($action === 'downgrade') {
        // Handle downgrade to free plan
        if ($plan['price'] == 0) {
            // Cancel current subscription if exists
            $currentSubscription = getUserSubscription($user['id']);
            if ($currentSubscription && $currentSubscription['stripe_subscription_id']) {
                try {
                    cancelSubscription($user['id']);
                } catch (Exception $e) {
                    error_log('Failed to cancel subscription: ' . $e->getMessage());
                    // Continue with downgrade even if cancellation fails
                }
            }
            
            // Assign free plan
            assignFreePlan($user['id'], $planId);
            header('Location: subscribe.php?success=downgraded');
            exit;
        }
    }
    
    header('Location: subscribe.php?error=invalid_action');
    exit;
    
} catch (Exception $e) {
    error_log('Subscription processing error: ' . $e->getMessage());
    header('Location: subscribe.php?error=processing_failed');
    exit;
}

/**
 * Assign a free plan to a user
 */
function assignFreePlan($userId, $planId) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Cancel any existing subscription
        $stmt = $pdo->prepare("
            UPDATE user_subscriptions 
            SET status = 'cancelled', 
                cancelled_at = NOW(),
                updated_at = NOW()
            WHERE user_id = ? AND status = 'active'
        ");
        $stmt->execute([$userId]);
        
        // Create new free subscription
        $stmt = $pdo->prepare("
            INSERT INTO user_subscriptions (
                user_id, plan_id, status, current_period_start, 
                current_period_end, created_at, updated_at
            ) VALUES (
                ?, ?, 'active', NOW(), 
                DATE_ADD(NOW(), INTERVAL 1 YEAR), NOW(), NOW()
            )
        ");
        $stmt->execute([$userId, $planId]);
        
        $pdo->commit();
        
        // Log the transaction
        logPaymentTransaction($userId, 'free_subscription', 0, 'completed', [
            'plan_id' => $planId,
            'subscription_type' => 'free'
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Get the base URL for the application
 */
function getBaseUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $path = dirname($_SERVER['SCRIPT_NAME']);
    $path = $path === '/' ? '' : $path;
    return $protocol . '://' . $host . $path;
}
?>