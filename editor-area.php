<?php
/**
 * Editor area - Content Management
 * Accessible to editors and admins
 */

require_once 'db.php';
require_once 'functions.php';

startSecureSession();
requireLogin();

// Require editor role or higher (editors and admins can access)
requireMinimumRole('editor', 'admin.php');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Content Management - CMS Auth</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="container">
        <header style="margin-bottom: 2rem; text-align: center;">
            <h1>✏️ Content Management</h1>
            <p>This area is accessible to editors and administrators.</p>
            <p><strong>Your role:</strong> <span class="role-badge role-<?= $_SESSION['role'] ?>"><?= ucfirst($_SESSION['role']) ?></span></p>
        </header>
        
        <div style="background: var(--surface-color); padding: 2rem; border-radius: 1rem; box-shadow: var(--shadow-md); margin-bottom: 2rem;">
            <h2>Content Tools</h2>
            <div style="display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-top: 1rem;">
                <div class="btn btn-primary">Create Content</div>
                <div class="btn btn-secondary">Edit Profiles</div>
                <div class="btn btn-secondary">Moderate Posts</div>
                <div class="btn btn-secondary">Review Reports</div>
            </div>
        </div>
        
        <div style="text-align: center;">
            <a href="admin.php" class="btn btn-secondary">Back to Dashboard</a>
            <?php if (hasRole('admin')): ?>
                <a href="admin-only.php" class="btn btn-primary">Admin Tools</a>
            <?php endif; ?>
            <a href="logout.php" class="btn btn-danger">Logout</a>
        </div>
    </div>
</body>
</html>