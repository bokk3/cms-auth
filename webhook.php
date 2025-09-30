<?php
/**
 * Stripe Webhook Endpoint
 * Handles payment events and subscription changes from Stripe
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/db.php';

use Stripe\Stripe;
use Stripe\Webhook;
use Stripe\Exception\SignatureVerificationException;

// Set the API keys and webhook secret
Stripe::setApiKey(getenv('STRIPE_SECRET_KEY') ?: STRIPE_SECRET_KEY);
$endpoint_secret = getenv('STRIPE_WEBHOOK_SECRET') ?: STRIPE_WEBHOOK_SECRET;

// Get the raw POST data
$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

$event = null;

try {
    // Verify webhook signature
    $event = Webhook::constructEvent($payload, $sig_header, $endpoint_secret);
    
    // Log the webhook event for debugging
    error_log('Webhook received: ' . $event['type'] . ' - ID: ' . $event['id']);
    
    // Handle the event
    switch ($event['type']) {
        case 'customer.subscription.created':
            handleSubscriptionCreated($event['data']['object']);
            break;
            
        case 'customer.subscription.updated':
            handleSubscriptionUpdated($event['data']['object']);
            break;
            
        case 'customer.subscription.deleted':
            handleSubscriptionDeleted($event['data']['object']);
            break;
            
        case 'invoice.payment_succeeded':
            handleInvoicePaymentSucceeded($event['data']['object']);
            break;
            
        case 'invoice.payment_failed':
            handleInvoicePaymentFailed($event['data']['object']);
            break;
            
        case 'checkout.session.completed':
            handleCheckoutSessionCompleted($event['data']['object']);
            break;
            
        case 'customer.created':
            handleCustomerCreated($event['data']['object']);
            break;
            
        default:
            // Log unhandled event types for monitoring
            error_log('Unhandled webhook event type: ' . $event['type']);
    }
    
    // Return success response
    http_response_code(200);
    echo json_encode(['status' => 'success']);
    
} catch (SignatureVerificationException $e) {
    error_log('Webhook signature verification failed: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode(['error' => 'Invalid signature']);
    
} catch (Exception $e) {
    error_log('Webhook error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Webhook processing failed']);
}

/**
 * Handle subscription creation
 */
function handleSubscriptionCreated($subscription) {
    try {
        $pdo = getDbConnection();
        
        // Get user by Stripe customer ID
        $userId = getUserIdByStripeCustomer($subscription->customer);
        if (!$userId) {
            error_log('No user found for customer: ' . $subscription->customer);
            return;
        }
        
        // Get plan by Stripe price ID
        $planId = getPlanIdByStripePrice($subscription->items->data[0]->price->id);
        if (!$planId) {
            error_log('No plan found for price: ' . $subscription->items->data[0]->price->id);
            return;
        }
        
        // Create or update subscription record
        $stmt = $pdo->prepare("
            INSERT INTO user_subscriptions (
                user_id, plan_id, stripe_subscription_id, stripe_customer_id,
                status, current_period_start, current_period_end, next_billing_date,
                created_at, updated_at
            ) VALUES (
                ?, ?, ?, ?, ?, FROM_UNIXTIME(?), FROM_UNIXTIME(?), FROM_UNIXTIME(?),
                NOW(), NOW()
            )
            ON DUPLICATE KEY UPDATE
                status = VALUES(status),
                current_period_start = VALUES(current_period_start),
                current_period_end = VALUES(current_period_end),
                next_billing_date = VALUES(next_billing_date),
                updated_at = NOW()
        ");
        
        $stmt->execute([
            $userId,
            $planId,
            $subscription->id,
            $subscription->customer,
            mapStripeStatus($subscription->status),
            $subscription->current_period_start,
            $subscription->current_period_end,
            $subscription->current_period_end
        ]);
        
        // Log transaction
        logPaymentTransaction($userId, 'subscription', $subscription->items->data[0]->price->unit_amount / 100, 'completed', [
            'stripe_subscription_id' => $subscription->id,
            'plan_id' => $planId,
            'period_start' => $subscription->current_period_start,
            'period_end' => $subscription->current_period_end
        ]);
        
        error_log("Subscription created for user {$userId}: {$subscription->id}");
        
    } catch (Exception $e) {
        error_log('Error handling subscription created: ' . $e->getMessage());
        throw $e;
    }
}

/**
 * Handle subscription updates
 */
function handleSubscriptionUpdated($subscription) {
    try {
        $pdo = getDbConnection();
        
        $stmt = $pdo->prepare("
            UPDATE user_subscriptions 
            SET status = ?,
                current_period_start = FROM_UNIXTIME(?),
                current_period_end = FROM_UNIXTIME(?),
                next_billing_date = FROM_UNIXTIME(?),
                updated_at = NOW()
            WHERE stripe_subscription_id = ?
        ");
        
        $stmt->execute([
            mapStripeStatus($subscription->status),
            $subscription->current_period_start,
            $subscription->current_period_end,
            $subscription->current_period_end,
            $subscription->id
        ]);
        
        // Handle cancellation
        if ($subscription->status === 'canceled') {
            $pdo->prepare("
                UPDATE user_subscriptions 
                SET cancelled_at = FROM_UNIXTIME(?)
                WHERE stripe_subscription_id = ?
            ")->execute([$subscription->canceled_at, $subscription->id]);
        }
        
        error_log("Subscription updated: {$subscription->id} - Status: {$subscription->status}");
        
    } catch (Exception $e) {
        error_log('Error handling subscription updated: ' . $e->getMessage());
        throw $e;
    }
}

/**
 * Handle subscription deletion/cancellation
 */
function handleSubscriptionDeleted($subscription) {
    try {
        $pdo = getDbConnection();
        
        $stmt = $pdo->prepare("
            UPDATE user_subscriptions 
            SET status = 'cancelled',
                cancelled_at = FROM_UNIXTIME(?),
                updated_at = NOW()
            WHERE stripe_subscription_id = ?
        ");
        
        $stmt->execute([
            $subscription->canceled_at ?: time(),
            $subscription->id
        ]);
        
        error_log("Subscription cancelled: {$subscription->id}");
        
    } catch (Exception $e) {
        error_log('Error handling subscription deleted: ' . $e->getMessage());
        throw $e;
    }
}

/**
 * Handle successful invoice payment
 */
function handleInvoicePaymentSucceeded($invoice) {
    try {
        if (!$invoice->subscription) {
            return; // Skip if not a subscription invoice
        }
        
        $userId = getUserIdByStripeCustomer($invoice->customer);
        if (!$userId) {
            return;
        }
        
        // Log successful payment
        logPaymentTransaction($userId, 'subscription', $invoice->amount_paid / 100, 'completed', [
            'stripe_invoice_id' => $invoice->id,
            'stripe_subscription_id' => $invoice->subscription,
            'invoice_number' => $invoice->number
        ]);
        
        error_log("Invoice payment succeeded for user {$userId}: {$invoice->id}");
        
    } catch (Exception $e) {
        error_log('Error handling invoice payment succeeded: ' . $e->getMessage());
        throw $e;
    }
}

/**
 * Handle failed invoice payment
 */
function handleInvoicePaymentFailed($invoice) {
    try {
        if (!$invoice->subscription) {
            return;
        }
        
        $userId = getUserIdByStripeCustomer($invoice->customer);
        if (!$userId) {
            return;
        }
        
        // Log failed payment
        logPaymentTransaction($userId, 'subscription', $invoice->amount_due / 100, 'failed', [
            'stripe_invoice_id' => $invoice->id,
            'stripe_subscription_id' => $invoice->subscription,
            'failure_reason' => $invoice->last_finalization_error->message ?? 'Unknown'
        ]);
        
        error_log("Invoice payment failed for user {$userId}: {$invoice->id}");
        
    } catch (Exception $e) {
        error_log('Error handling invoice payment failed: ' . $e->getMessage());
        throw $e;
    }
}

/**
 * Handle completed checkout session
 */
function handleCheckoutSessionCompleted($session) {
    try {
        $userId = getUserIdByStripeCustomer($session->customer);
        if (!$userId) {
            return;
        }
        
        if ($session->mode === 'subscription') {
            // Subscription checkout - will be handled by subscription.created event
            error_log("Checkout session completed for subscription: {$session->id}");
        } elseif ($session->mode === 'payment') {
            // One-time payment
            $planId = $session->metadata->plan_id ?? null;
            logPaymentTransaction($userId, 'one-time', $session->amount_total / 100, 'completed', [
                'stripe_session_id' => $session->id,
                'plan_id' => $planId
            ]);
            error_log("One-time payment completed: {$session->id}");
        }
        
    } catch (Exception $e) {
        error_log('Error handling checkout session completed: ' . $e->getMessage());
        throw $e;
    }
}

/**
 * Handle customer creation
 */
function handleCustomerCreated($customer) {
    try {
        // Update user record with Stripe customer ID if not already set
        if (isset($customer->metadata->user_id)) {
            $pdo = getDbConnection();
            $stmt = $pdo->prepare("
                UPDATE users 
                SET stripe_customer_id = ? 
                WHERE id = ? AND (stripe_customer_id IS NULL OR stripe_customer_id = '')
            ");
            $stmt->execute([$customer->id, $customer->metadata->user_id]);
            
            error_log("Customer created and linked to user {$customer->metadata->user_id}: {$customer->id}");
        }
        
    } catch (Exception $e) {
        error_log('Error handling customer created: ' . $e->getMessage());
        throw $e;
    }
}

/**
 * Get user ID by Stripe customer ID
 */
function getUserIdByStripeCustomer($stripeCustomerId) {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('SELECT id FROM users WHERE stripe_customer_id = ?');
        $stmt->execute([$stripeCustomerId]);
        $result = $stmt->fetch();
        return $result ? $result['id'] : null;
    } catch (Exception $e) {
        error_log('Error getting user by Stripe customer: ' . $e->getMessage());
        return null;
    }
}

/**
 * Get plan ID by Stripe price ID
 */
function getPlanIdByStripePrice($stripePriceId) {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('SELECT id FROM payment_plans WHERE stripe_price_id = ?');
        $stmt->execute([$stripePriceId]);
        $result = $stmt->fetch();
        return $result ? $result['id'] : null;
    } catch (Exception $e) {
        error_log('Error getting plan by Stripe price: ' . $e->getMessage());
        return null;
    }
}

/**
 * Map Stripe subscription status to our internal status
 */
function mapStripeStatus($stripeStatus) {
    $statusMap = [
        'active' => 'active',
        'past_due' => 'past_due',
        'unpaid' => 'unpaid',
        'canceled' => 'cancelled',
        'incomplete' => 'incomplete',
        'incomplete_expired' => 'cancelled',
        'trialing' => 'active'
    ];
    
    return $statusMap[$stripeStatus] ?? 'cancelled';
}

/**
 * Log payment transaction
 */
function logPaymentTransaction($userId, $type, $amount, $status, $metadata = []) {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("
            INSERT INTO payment_transactions (
                user_id, transaction_type, amount, status, metadata, 
                processed_at, created_at, updated_at
            ) VALUES (
                ?, ?, ?, ?, ?, NOW(), NOW(), NOW()
            )
        ");
        
        $stmt->execute([
            $userId,
            $type,
            $amount,
            $status,
            json_encode($metadata)
        ]);
        
    } catch (Exception $e) {
        error_log('Error logging payment transaction: ' . $e->getMessage());
    }
}
?>