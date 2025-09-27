<?php
/**
 * Index page - redirects to appropriate page based on login status
 */

require_once 'db.php';
require_once 'functions.php';

startSecureSession();

// If user is already logged in, redirect to dashboard
if (isLoggedIn()) {
    header('Location: admin.php');
    exit;
}

// Otherwise redirect to login page
header('Location: login.php');
exit;
?>