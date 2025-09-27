-- CMS Authentication System Database Schema
-- PHP 8.2 + MariaDB with role-based authentication and Stripe payments

-- Drop existing tables if they exist (for clean rebuild)
DROP TABLE IF EXISTS payment_transactions;
DROP TABLE IF EXISTS user_subscriptions;
DROP TABLE IF EXISTS payment_plans;
DROP TABLE IF EXISTS sessions;
DROP TABLE IF EXISTS users;

-- Enhanced users table for CMS system
CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'editor', 'user') NOT NULL DEFAULT 'user',
    
    -- Profile information
    first_name VARCHAR(50) DEFAULT NULL,
    last_name VARCHAR(50) DEFAULT NULL,
    bio TEXT DEFAULT NULL,
    
    -- Payment information
    stripe_customer_id VARCHAR(255) DEFAULT NULL,
    subscription_status ENUM('active', 'inactive', 'canceled', 'past_due', 'unpaid') DEFAULT 'inactive',
    subscription_plan_id INT UNSIGNED DEFAULT NULL,
    
    -- UI preferences
    dark_mode BOOLEAN DEFAULT FALSE,
    
    -- Account status
    is_active BOOLEAN DEFAULT TRUE,
    is_verified BOOLEAN DEFAULT FALSE,
    last_active TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Indexes for performance
    INDEX idx_role (role),
    INDEX idx_active (is_active, last_active),
    INDEX idx_email (email),
    INDEX idx_stripe_customer (stripe_customer_id),
    INDEX idx_subscription (subscription_status, subscription_plan_id),
    INDEX idx_created (created_at)
);

-- Sessions table for secure authentication
CREATE TABLE sessions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    session_id VARCHAR(128) NOT NULL UNIQUE,
    ip_address VARCHAR(45), -- Support IPv6
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_session_id (session_id),
    INDEX idx_user_sessions (user_id, last_activity),
    INDEX idx_cleanup (expires_at)
);

-- Payment plans table
CREATE TABLE payment_plans (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) NOT NULL DEFAULT 'USD',
    billing_period ENUM('monthly', 'yearly', 'one-time') NOT NULL DEFAULT 'monthly',
    stripe_price_id VARCHAR(255) NOT NULL,
    stripe_product_id VARCHAR(255) NOT NULL,
    features JSON DEFAULT NULL,
    max_users INT UNSIGNED DEFAULT NULL,
    max_projects INT UNSIGNED DEFAULT NULL,
    storage_limit_gb INT UNSIGNED DEFAULT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_active (is_active, sort_order),
    INDEX idx_stripe_price (stripe_price_id),
    INDEX idx_billing_period (billing_period)
);

-- User subscriptions table
CREATE TABLE user_subscriptions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    plan_id INT UNSIGNED NOT NULL,
    stripe_subscription_id VARCHAR(255) NOT NULL,
    status ENUM('active', 'inactive', 'canceled', 'past_due', 'unpaid', 'trialing') NOT NULL,
    current_period_start TIMESTAMP NULL,
    current_period_end TIMESTAMP NULL,
    canceled_at TIMESTAMP NULL,
    trial_start TIMESTAMP NULL,
    trial_end TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (plan_id) REFERENCES payment_plans(id) ON DELETE CASCADE,
    INDEX idx_user_subscription (user_id, status),
    INDEX idx_stripe_subscription (stripe_subscription_id),
    INDEX idx_status (status),
    INDEX idx_period (current_period_end)
);

-- Payment transactions table
CREATE TABLE payment_transactions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    plan_id INT UNSIGNED DEFAULT NULL,
    stripe_payment_intent_id VARCHAR(255) DEFAULT NULL,
    stripe_invoice_id VARCHAR(255) DEFAULT NULL,
    type ENUM('subscription', 'one-time', 'refund') NOT NULL,
    status ENUM('pending', 'succeeded', 'failed', 'canceled', 'refunded') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) NOT NULL DEFAULT 'USD',
    description TEXT,
    metadata JSON DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (plan_id) REFERENCES payment_plans(id) ON DELETE SET NULL,
    INDEX idx_user_transactions (user_id, created_at),
    INDEX idx_stripe_payment (stripe_payment_intent_id),
    INDEX idx_stripe_invoice (stripe_invoice_id),
    INDEX idx_status (status),
    INDEX idx_type (type)
);

-- Seed data: Create sample user accounts

-- Messages table for matched users (future enhancement)
CREATE TABLE messages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    match_id INT UNSIGNED NOT NULL,
    sender_id INT UNSIGNED NOT NULL,
    recipient_id INT UNSIGNED NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (recipient_id) REFERENCES users(id) ON DELETE CASCADE,
    
    INDEX idx_match_messages (match_id, created_at),
    INDEX idx_recipient_unread (recipient_id, is_read, created_at)
);

-- User reports table for safety (future enhancement)
CREATE TABLE user_reports (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reporter_id INT UNSIGNED NOT NULL,
    reported_id INT UNSIGNED NOT NULL,
    reason ENUM('inappropriate', 'fake', 'harassment', 'spam', 'other') NOT NULL,
    description TEXT,
    status ENUM('pending', 'reviewed', 'resolved') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reported_id) REFERENCES users(id) ON DELETE CASCADE,
    
    INDEX idx_reported_user (reported_id, status),
    INDEX idx_status (status, created_at)
);

-- Seed data: Create diverse dating profiles
INSERT INTO users (username, email, password_hash, gender, bio, interests, age, location, looking_for, role, is_active, is_verified) VALUES 

-- Admin user
('admin', 'admin@cmsauth.com', '$argon2id$v=19$m=65536,t=4,p=1$VUIucTRLcjBxb1VnREhZeg$bcEhxvnLj/xtpF/GBzqVq1YihU0isVa52WrKzbzxK9Y', 'Admin', 'User', 'System administrator account with full access to all features and user management.', 'admin', TRUE, TRUE),

-- Editor user
('editor', 'editor@cmsauth.com', '$argon2id$v=19$m=65536,t=4,p=1$Nlkwd2RCTzVyVFk3aWE3aw$1WfT61QJ3Ql4Zxr1azS7u7E3eoCE3KXttSBLElqlnz4', 'Content', 'Editor', 'Content manager and community moderator with editing privileges.', 'editor', TRUE, TRUE),

-- Demo users
('alex_tech', 'alex@example.com', '$argon2id$v=19$m=65536,t=4,p=1$Nlkwd2RCTzVyVFk3aWE3aw$1WfT61QJ3Ql4Zxr1azS7u7E3eoCE3KXttSBLElqlnz4', 'Alex', 'Thompson', 'Software engineer with experience in web development and system architecture.', 'user', TRUE, TRUE),

('sarah_artist', 'sarah@example.com', '$argon2id$v=19$m=65536,t=4,p=1$UjlERS5sb04vS05zWGRQdw$pUa+tjJChnoUaXrmZ5qbHonitxhUMx0rLvEUSNjOsZk', 'Sarah', 'Williams', 'Artist and creative professional with a passion for visual design and user experience.', 'user', TRUE, TRUE),

('mike_chef', 'mike@example.com', '$argon2id$v=19$m=65536,t=4,p=1$QzVoR1FkNWpVcGRyVGE4bQ$sP9kVw2M8nQ4xL7jR5tY3mC8nF1oK6pW9qE4vZ2hB7A', 'Mike', 'Johnson', 'Professional chef and culinary instructor specializing in modern cuisine techniques.', 'user', TRUE, TRUE),

('emma_doctor', 'emma@example.com', '$argon2id$v=19$m=65536,t=4,p=1$VGJmc3R5UWxOcDJyVWFudA$hN7wY8mP3qR9tK5xL2vF4oS1nE6jW9cM8bH4gV7aZ3D', 'Emma', 'Davis', 'Emergency room physician with expertise in critical care and medical research.', 'user', TRUE, TRUE),

('jordan_nb', 'jordan@example.com', '$argon2id$v=19$m=65536,t=4,p=1$T3B1RnJkWm1QeVRrVGRzcw$mK8qL4wN9eP2xR6yT5vC3hB7nM1oF8jS4gD6aZ9rH5E', 'Jordan', 'Smith', 'Graphic designer with focus on sustainable design practices and digital accessibility.', 'user', TRUE, TRUE);

-- Payment plans seed data
INSERT INTO payment_plans (name, description, price, currency, billing_period, stripe_price_id, stripe_product_id, features, max_users, max_projects, storage_limit_gb, is_active, sort_order) VALUES
('Free', 'Perfect for getting started with basic features', 0.00, 'USD', 'monthly', 'price_free_plan', 'prod_free_plan', '["Basic dashboard", "Up to 3 projects", "1GB storage", "Email support"]', 1, 3, 1, TRUE, 1),
('Basic', 'Great for small teams and growing businesses', 9.99, 'USD', 'monthly', 'price_basic_monthly', 'prod_basic_plan', '["Everything in Free", "Up to 10 users", "Unlimited projects", "10GB storage", "Priority support", "Advanced analytics"]', 10, NULL, 10, TRUE, 2),
('Pro', 'Perfect for established teams and organizations', 29.99, 'USD', 'monthly', 'price_pro_monthly', 'prod_pro_plan', '["Everything in Basic", "Up to 50 users", "100GB storage", "Custom integrations", "API access", "Advanced security", "24/7 support"]', 50, NULL, 100, TRUE, 3),
('Enterprise', 'For large organizations with custom needs', 99.99, 'USD', 'monthly', 'price_enterprise_monthly', 'prod_enterprise_plan', '["Everything in Pro", "Unlimited users", "1TB storage", "Custom development", "Dedicated support", "SLA guarantee", "On-premise option"]', NULL, NULL, 1000, TRUE, 4),
('Basic Yearly', 'Basic plan with yearly billing (2 months free)', 99.99, 'USD', 'yearly', 'price_basic_yearly', 'prod_basic_plan', '["Everything in Free", "Up to 10 users", "Unlimited projects", "10GB storage", "Priority support", "Advanced analytics"]', 10, NULL, 10, TRUE, 5),
('Pro Yearly', 'Pro plan with yearly billing (2 months free)', 299.99, 'USD', 'yearly', 'price_pro_yearly', 'prod_pro_plan', '["Everything in Basic", "Up to 50 users", "100GB storage", "Custom integrations", "API access", "Advanced security", "24/7 support"]', 50, NULL, 100, TRUE, 6);

-- Create some sample sessions (these will be managed by the app)
-- Sessions are created dynamically during login

-- Cleanup procedure for expired sessions (to be run periodically)
DELIMITER //
CREATE PROCEDURE CleanupExpiredSessions()
BEGIN
    DELETE FROM sessions WHERE expires_at < NOW();
END //
DELIMITER ;

-- Indexes for optimal queries
CREATE INDEX idx_user_auth ON users(username, is_active);
CREATE INDEX idx_session_cleanup ON sessions(expires_at);

-- Views for common queries
CREATE VIEW active_users AS 
SELECT id, username, email, first_name, last_name, role, created_at, last_active
FROM users 
WHERE is_active = TRUE AND last_active > DATE_SUB(NOW(), INTERVAL 30 DAY);

-- Demo passwords (for development only):
-- admin: admin123 (role: admin)
-- editor: editor123 (role: editor)
-- alex_tech: editor123 (role: user)  
-- sarah_artist: user123 (role: user)
-- mike_chef: chef123 (role: user)
-- emma_doctor: doctor123 (role: user)
-- jordan_nb: designer123 (role: user)
-- DELETE FROM sessions WHERE last_activity < DATE_SUB(NOW(), INTERVAL 30 MINUTE);