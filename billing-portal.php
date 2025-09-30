<?php
require_once 'functions.php';
require_once 'payment-functions.php';

requireAuth();

$user = getCurrentUser();
$subscription = getUserSubscription($user['id']);

// Only allow access if user has an active paid subscription
if (!$subscription || !$subscription['stripe_subscription_id']) {
    header('Location: subscribe.php?error=no_subscription');
    exit;
}

try {
    // Create Stripe billing portal session
    $portalUrl = createBillingPortalSession($user['id'], [
        'return_url' => getBaseUrl() . '/subscribe.php'
    ]);
    
    // Redirect to Stripe billing portal
    header('Location: ' . $portalUrl);
    exit;
    
} catch (Exception $e) {
    error_log('Billing portal error: ' . $e->getMessage());
    header('Location: subscribe.php?error=portal_failed');
    exit;
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