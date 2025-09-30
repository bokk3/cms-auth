<?php
/**
 * Payment Processing Functions with Stripe Integration
 * Handles subscriptions, one-time payments, and billing management
 */

// Prevent multiple inclusions
if (function_exists('getOrCreateStripeCustomer')) {
    return;
}

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/db.php';

use Stripe\Stripe;
use Stripe\Checkout\Session as CheckoutSession;
use Stripe\BillingPortal\Session as BillingPortalSession;
use Stripe\Customer;
use Stripe\Subscription;
use Stripe\PaymentIntent;
use Stripe\Price;
use Stripe\Product;
use Stripe\Webhook;

// Initialize Stripe
if (defined('STRIPE_SECRET_KEY') && !empty(STRIPE_SECRET_KEY)) {
    Stripe::setApiKey(STRIPE_SECRET_KEY);
}

/**
 * Get or create Stripe customer for user
 * 
 * @param array $user User data from database
 * @return string Stripe customer ID
 */
function getOrCreateStripeCustomer(array $user): string {
    try {
        // Check if user already has a Stripe customer ID
        if (!empty($user['stripe_customer_id'])) {
            return $user['stripe_customer_id'];
        }
        
        // Create new Stripe customer
        $customer = Customer::create([
            'email' => $user['email'],
            'name' => trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')),
            'metadata' => [
                'user_id' => $user['id'],
                'username' => $user['username'],
                'role' => $user['role']
            ]
        ]);
        
        // Save customer ID to database
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('UPDATE users SET stripe_customer_id = ? WHERE id = ?');
        $stmt->execute([$customer->id, $user['id']]);
        
        return $customer->id;
        
    } catch (Exception $e) {
        error_log('Failed to create Stripe customer: ' . $e->getMessage());
        throw new Exception('Failed to create payment customer');
    }
}

/**
 * Get all available payment plans
 * 
 * @param bool $activeOnly Only return active plans
 * @return array Array of payment plans
 */
function getPaymentPlans(bool $activeOnly = true): array {
    try {
        $pdo = getDbConnection();
        $sql = 'SELECT * FROM payment_plans';
        
        if ($activeOnly) {
            $sql .= ' WHERE is_active = TRUE';
        }
        
        $sql .= ' ORDER BY sort_order ASC, price ASC';
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        
        $plans = $stmt->fetchAll();
        
        // Decode JSON features
        foreach ($plans as &$plan) {
            $plan['features'] = json_decode($plan['features'], true) ?? [];
        }
        
        return $plans;
        
    } catch (Exception $e) {
        error_log('Failed to get payment plans: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get payment plan by ID
 * 
 * @param int $planId Plan ID
 * @return array|null Payment plan or null if not found
 */
function getPaymentPlan(int $planId): ?array {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('SELECT * FROM payment_plans WHERE id = ? AND is_active = TRUE');
        $stmt->execute([$planId]);
        
        $plan = $stmt->fetch();
        
        if ($plan) {
            $plan['features'] = json_decode($plan['features'], true) ?? [];
        }
        
        return $plan ?: null;
        
    } catch (Exception $e) {
        error_log('Failed to get payment plan: ' . $e->getMessage());
        return null;
    }
}

/**
 * Create Stripe checkout session for subscription
 * 
 * @param int $userId User ID
 * @param int $planId Payment plan ID
 * @param string $successUrl Success redirect URL
 * @param string $cancelUrl Cancel redirect URL
 * @return array Checkout session data
 */
function createSubscriptionCheckout(int $userId, int $planId, string $successUrl, string $cancelUrl): array {
    try {
        $pdo = getDbConnection();
        
        // Get user data
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            throw new Exception('User not found');
        }
        
        // Get payment plan
        $plan = getPaymentPlan($planId);
        if (!$plan) {
            throw new Exception('Payment plan not found');
        }
        
        // Get or create Stripe customer
        $customerId = getOrCreateStripeCustomer($user);
        
        // Create checkout session
        $session = CheckoutSession::create([
            'customer' => $customerId,
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price' => $plan['stripe_price_id'],
                'quantity' => 1,
            ]],
            'mode' => 'subscription',
            'success_url' => $successUrl . '?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $cancelUrl,
            'metadata' => [
                'user_id' => $userId,
                'plan_id' => $planId,
                'type' => 'subscription'
            ],
            'subscription_data' => [
                'metadata' => [
                    'user_id' => $userId,
                    'plan_id' => $planId
                ]
            ]
        ]);
        
        return [
            'success' => true,
            'session_id' => $session->id,
            'url' => $session->url,
            'customer_id' => $customerId
        ];
        
    } catch (Exception $e) {
        error_log('Failed to create subscription checkout: ' . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Create Stripe checkout session for one-time payment
 * 
 * @param int $userId User ID
 * @param int $planId Payment plan ID
 * @param string $successUrl Success redirect URL
 * @param string $cancelUrl Cancel redirect URL
 * @return array Checkout session data
 */
function createOneTimeCheckout(int $userId, int $planId, string $successUrl, string $cancelUrl): array {
    try {
        $pdo = getDbConnection();
        
        // Get user data
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            throw new Exception('User not found');
        }
        
        // Get payment plan
        $plan = getPaymentPlan($planId);
        if (!$plan || $plan['billing_period'] !== 'one-time') {
            throw new Exception('Invalid payment plan for one-time purchase');
        }
        
        // Get or create Stripe customer
        $customerId = getOrCreateStripeCustomer($user);
        
        // Create checkout session
        $session = CheckoutSession::create([
            'customer' => $customerId,
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price' => $plan['stripe_price_id'],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'success_url' => $successUrl . '?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $cancelUrl,
            'metadata' => [
                'user_id' => $userId,
                'plan_id' => $planId,
                'type' => 'one-time'
            ]
        ]);
        
        return [
            'success' => true,
            'session_id' => $session->id,
            'url' => $session->url,
            'customer_id' => $customerId
        ];
        
    } catch (Exception $e) {
        error_log('Failed to create one-time checkout: ' . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Handle successful payment completion
 * 
 * @param string $sessionId Checkout session ID
 * @return array Result data
 */
function handlePaymentSuccess(string $sessionId): array {
    try {
        // Retrieve checkout session
        $session = CheckoutSession::retrieve($sessionId);
        
        $userId = (int)$session->metadata->user_id;
        $planId = (int)$session->metadata->plan_id;
        $type = $session->metadata->type;
        
        $pdo = getDbConnection();
        
        if ($type === 'subscription') {
            // Handle subscription payment
            $subscription = Subscription::retrieve($session->subscription);
            
            // Create or update user subscription
            $stmt = $pdo->prepare('
                INSERT INTO user_subscriptions 
                (user_id, plan_id, stripe_subscription_id, status, current_period_start, current_period_end)
                VALUES (?, ?, ?, ?, FROM_UNIXTIME(?), FROM_UNIXTIME(?))
                ON DUPLICATE KEY UPDATE
                status = VALUES(status),
                current_period_start = VALUES(current_period_start),
                current_period_end = VALUES(current_period_end)
            ');
            $stmt->execute([
                $userId,
                $planId,
                $subscription->id,
                $subscription->status,
                $subscription->current_period_start,
                $subscription->current_period_end
            ]);
            
            // Update user subscription status
            $stmt = $pdo->prepare('
                UPDATE users 
                SET subscription_status = ?, subscription_plan_id = ?
                WHERE id = ?
            ');
            $stmt->execute([$subscription->status, $planId, $userId]);
            
        } else {
            // Handle one-time payment
            $paymentIntent = PaymentIntent::retrieve($session->payment_intent);
            
            // Record transaction
            $stmt = $pdo->prepare('
                INSERT INTO payment_transactions 
                (user_id, plan_id, stripe_payment_intent_id, type, status, amount, currency, description)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ');
            $stmt->execute([
                $userId,
                $planId,
                $paymentIntent->id,
                'one-time',
                $paymentIntent->status,
                $paymentIntent->amount / 100, // Convert from cents
                $paymentIntent->currency,
                'One-time payment for plan'
            ]);
        }
        
        return [
            'success' => true,
            'type' => $type,
            'user_id' => $userId,
            'plan_id' => $planId
        ];
        
    } catch (Exception $e) {
        error_log('Failed to handle payment success: ' . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Get user's current subscription
 * 
 * @param int $userId User ID
 * @return array|null Subscription data or null if no active subscription
 */
function getUserSubscription(int $userId): ?array {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('
            SELECT us.*, pp.name as plan_name, pp.price, pp.billing_period, pp.features
            FROM user_subscriptions us
            JOIN payment_plans pp ON us.plan_id = pp.id
            WHERE us.user_id = ? AND us.status IN ("active", "trialing", "past_due")
            ORDER BY us.created_at DESC
            LIMIT 1
        ');
        $stmt->execute([$userId]);
        
        $subscription = $stmt->fetch();
        
        if ($subscription) {
            $subscription['features'] = json_decode($subscription['features'], true) ?? [];
        }
        
        return $subscription ?: null;
        
    } catch (Exception $e) {
        error_log('Failed to get user subscription: ' . $e->getMessage());
        return null;
    }
}

/**
 * Cancel user subscription
 * 
 * @param int $userId User ID
 * @return array Result data
 */
function cancelUserSubscription(int $userId): array {
    try {
        $subscription = getUserSubscription($userId);
        
        if (!$subscription) {
            return [
                'success' => false,
                'error' => 'No active subscription found'
            ];
        }
        
        // Cancel in Stripe
        $stripeSubscription = Subscription::retrieve($subscription['stripe_subscription_id']);
        $stripeSubscription->cancel();
        
        // Update in database
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('
            UPDATE user_subscriptions 
            SET status = "canceled", canceled_at = NOW()
            WHERE user_id = ? AND stripe_subscription_id = ?
        ');
        $stmt->execute([$userId, $subscription['stripe_subscription_id']]);
        
        // Update user status
        $stmt = $pdo->prepare('UPDATE users SET subscription_status = "canceled" WHERE id = ?');
        $stmt->execute([$userId]);
        
        return [
            'success' => true,
            'message' => 'Subscription canceled successfully'
        ];
        
    } catch (Exception $e) {
        error_log('Failed to cancel subscription: ' . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Get user's payment history
 * 
 * @param int $userId User ID
 * @param int $limit Number of transactions to retrieve
 * @return array Array of payment transactions
 */
function getUserPaymentHistory(int $userId, int $limit = 20): array {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('
            SELECT pt.*, pp.name as plan_name
            FROM payment_transactions pt
            LEFT JOIN payment_plans pp ON pt.plan_id = pp.id
            WHERE pt.user_id = ?
            ORDER BY pt.created_at DESC
            LIMIT ?
        ');
        $stmt->execute([$userId, $limit]);
        
        return $stmt->fetchAll();
        
    } catch (Exception $e) {
        error_log('Failed to get payment history: ' . $e->getMessage());
        return [];
    }
}

/**
 * Check if user has access to feature based on subscription
 * 
 * @param int $userId User ID
 * @param string $feature Feature name
 * @return bool True if user has access
 */
function hasFeatureAccess(int $userId, string $feature): bool {
    $subscription = getUserSubscription($userId);
    
    if (!$subscription) {
        // Check for free plan features
        $freePlan = array_filter(getPaymentPlans(), function($plan) {
            return $plan['price'] == 0;
        });
        
        if ($freePlan) {
            $freePlan = reset($freePlan);
            return in_array($feature, $freePlan['features']);
        }
        
        return false;
    }
    
    return in_array($feature, $subscription['features']);
}

/**
 * Format price for display
 * 
 * @param float $price Price amount
 * @param string $currency Currency code
 * @return string Formatted price
 */
function formatPrice(float $price, string $currency = 'USD'): string {
    $formatter = new NumberFormatter('en_US', NumberFormatter::CURRENCY);
    return $formatter->formatCurrency($price, $currency);
}

/**
 * Get billing period display text
 * 
 * @param string $period Billing period
 * @return string Display text
 */
function getBillingPeriodText(string $period): string {
    switch ($period) {
        case 'monthly':
            return 'per month';
        case 'yearly':
            return 'per year';
        case 'one-time':
            return 'one-time';
        default:
            return $period;
    }
}

/**
 * Create Stripe billing portal session for subscription management
 * 
 * @param int $userId User ID
 * @param array $options Portal options
 * @return string Portal URL
 * @throws Exception If portal creation fails
 */
function createBillingPortalSession(int $userId, array $options = []): string {
    try {
        $pdo = getDbConnection();
        
        // Get user data
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user || !$user['stripe_customer_id']) {
            throw new Exception('User not found or no Stripe customer');
        }
        
        $returnUrl = $options['return_url'] ?? getBaseUrl() . '/admin.php';
        
        // Create billing portal session
        $session = BillingPortalSession::create([
            'customer' => $user['stripe_customer_id'],
            'return_url' => $returnUrl,
        ]);
        
        return $session->url;
        
    } catch (Exception $e) {
        error_log('Failed to create billing portal session: ' . $e->getMessage());
        throw new Exception('Failed to create billing portal session');
    }
}

/**
 * Get base URL for redirects
 * 
 * @return string Base URL
 */
function getBaseUrl(): string {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $path = dirname($_SERVER['SCRIPT_NAME']);
    $path = $path === '/' ? '' : $path;
    return $protocol . '://' . $host . $path;
}
?>