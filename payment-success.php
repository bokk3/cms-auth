<?php
require_once 'functions.php';
require_once 'payment-functions.php';

requireAuth();

$user = getCurrentUser();
$sessionId = $_GET['session_id'] ?? '';

if (!$sessionId) {
    header('Location: subscribe.php?error=no_session');
    exit;
}

$success = false;
$error = null;

try {
    // Process the successful payment
    $result = handlePaymentSuccess($user['id'], $sessionId);
    $success = true;
    
} catch (Exception $e) {
    error_log('Payment success processing error: ' . $e->getMessage());
    $error = 'processing_failed';
}

// Redirect to subscription page with result
if ($success) {
    header('Location: subscribe.php?success=subscribed');
} else {
    header('Location: subscribe.php?error=' . $error);
}
exit;
?>