<?php
require_once 'functions.php';
require_once 'payment-functions.php';

requireAuth();

$user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Cancelled - CMS</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .cancel-message {
            max-width: 600px;
            margin: 50px auto;
            padding: 40px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        .cancel-icon {
            font-size: 48px;
            color: #dc3545;
            margin-bottom: 20px;
        }
        .cancel-title {
            font-size: 24px;
            font-weight: bold;
            color: #333;
            margin-bottom: 15px;
        }
        .cancel-description {
            color: #666;
            line-height: 1.6;
            margin-bottom: 30px;
        }
        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .btn {
            padding: 12px 24px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
            border: none;
            cursor: pointer;
            display: inline-block;
        }
        .btn-primary {
            background-color: #007cba;
            color: white;
        }
        .btn-primary:hover {
            background-color: #005a87;
        }
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        .btn-secondary:hover {
            background-color: #545b62;
        }
    </style>
</head>
<body>
    <div class="container">
        <nav>
            <h1>CMS Dashboard</h1>
            <div class="nav-links">
                <a href="admin.php">Dashboard</a>
                <a href="subscribe.php">Subscription</a>
                <a href="logout.php">Logout</a>
            </div>
        </nav>

        <main>
            <div class="cancel-message">
                <div class="cancel-icon">⚠️</div>
                <div class="cancel-title">Payment Cancelled</div>
                <div class="cancel-description">
                    <p>Your payment was cancelled and no charges were made to your account.</p>
                    <p>You can try again anytime or contact support if you need assistance.</p>
                </div>
                <div class="action-buttons">
                    <a href="subscribe.php" class="btn btn-primary">View Plans</a>
                    <a href="admin.php" class="btn btn-secondary">Return to Dashboard</a>
                </div>
            </div>
        </main>
    </div>
</body>
</html>