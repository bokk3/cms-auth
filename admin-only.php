<?php
/**
 * Admin-only area - System Administration
 * Demonstrates role-based access control
 */

require_once 'db.php';
require_once 'functions.php';

startSecureSession();
requireLogin();

// Require admin role specifically
requireRole('admin', 'admin.php');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Administration - CMS Auth</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="container">
        <header style="margin-bottom: 2rem; text-align: center;">
            <h1>🔒 System Administration</h1>
            <p>This area is only accessible to administrators.</p>
        </header>
        
        <div style="background: var(--surface-color); padding: 2rem; border-radius: 1rem; box-shadow: var(--shadow-md); margin-bottom: 2rem;">
            <h2>Admin Tools</h2>
            <div style="display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-top: 1rem;">
                <div class="btn btn-primary">User Management</div>
                <div class="btn btn-secondary">System Settings</div>
                <div class="btn btn-secondary">Database Admin</div>
                <div class="btn btn-secondary">Security Logs</div>
            </div>
        </div>
        
        <div style="text-align: center;">
            <a href="admin.php" class="btn btn-secondary">Back to Dashboard</a>
            <a href="logout.php" class="btn btn-danger">Logout</a>
        </div>
    </div>
</body>
</html>