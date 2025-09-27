<?php
/**
 * Role-based Dashboard System
 * Different interfaces for admin, editor, and user roles
 */

require_once 'db.php';
require_once 'functions.php';

// Start secure session and require login
startSecureSession();
requireLogin();

// Get user info
$currentUser = getCurrentUser();
if (!$currentUser) {
    setFlashMessage('Session error. Please login again.', 'error');
    header('Location: login.php');
    exit;
}

// Handle different role views
$activeTab = $_GET['tab'] ?? getDefaultTab($currentUser['role']);
$content = generateRoleContent($currentUser, $activeTab);

/**
 * Get current user details from session and database
 */
function getCurrentUser(): ?array {
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('SELECT id, username, email, role, created_at, last_active FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch();
    } catch (Exception $e) {
        error_log('Failed to get current user: ' . $e->getMessage());
        return null;
    }
}

/**
 * Get default tab based on user role
 */
function getDefaultTab(string $role): string {
    switch ($role) {
        case 'admin':
            return 'overview';
        case 'editor':
            return 'content';
        case 'user':
        default:
            return 'profile';
    }
}

/**
 * Generate role-specific content
 */
function generateRoleContent(array $user, string $activeTab): array {
    $role = $user['role'];
    $content = ['title' => '', 'body' => ''];
    
    switch ($role) {
        case 'admin':
            $content = generateAdminContent($user, $activeTab);
            break;
        case 'editor':
            $content = generateEditorContent($user, $activeTab);
            break;
        case 'user':
        default:
            $content = generateUserContent($user, $activeTab);
            break;
    }
    
    return $content;
}

/**
 * Generate admin dashboard content
 */
function generateAdminContent(array $user, string $activeTab): array {
    switch ($activeTab) {
        case 'overview':
            return [
                'title' => 'System Overview',
                'body' => generateSystemOverview()
            ];
        case 'users':
            return [
                'title' => 'User Management',
                'body' => generateUserManagement()
            ];
        case 'security':
            return [
                'title' => 'Security & Sessions',
                'body' => generateSecurityDashboard()
            ];
        case 'settings':
            return [
                'title' => 'System Settings',
                'body' => generateSystemSettings()
            ];
        default:
            return [
                'title' => 'System Overview',
                'body' => generateSystemOverview()
            ];
    }
}

/**
 * Generate editor dashboard content
 */
function generateEditorContent(array $user, string $activeTab): array {
    switch ($activeTab) {
        case 'content':
            return [
                'title' => 'Content Management',
                'body' => generateContentManagement()
            ];
        case 'users':
            return [
                'title' => 'User Profiles',
                'body' => generateUserProfiles()
            ];
        case 'reports':
            return [
                'title' => 'Content Reports',
                'body' => generateContentReports()
            ];
        default:
            return [
                'title' => 'Content Management',
                'body' => generateContentManagement()
            ];
    }
}

/**
 * Generate user dashboard content
 */
function generateUserContent(array $user, string $activeTab): array {
    switch ($activeTab) {
        case 'profile':
            return [
                'title' => 'My Profile',
                'body' => generateUserProfile($user)
            ];
        case 'content':
            return [
                'title' => 'My Content',
                'body' => generateUserMatches($user)
            ];
        case 'settings':
            return [
                'title' => 'Account Settings',
                'body' => generateUserSettings($user)
            ];
        default:
            return [
                'title' => 'My Profile',
                'body' => generateUserProfile($user)
            ];
    }
}

/**
 * System overview for admins
 */
function generateSystemOverview(): string {
    try {
        $pdo = getDbConnection();
        
        // Get user statistics
        $userStats = $pdo->query('
            SELECT 
                COUNT(*) as total_users,
                SUM(CASE WHEN role = "admin" THEN 1 ELSE 0 END) as admin_count,
                SUM(CASE WHEN role = "editor" THEN 1 ELSE 0 END) as editor_count,
                SUM(CASE WHEN role = "user" THEN 1 ELSE 0 END) as user_count,
                SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_users,
                SUM(CASE WHEN last_active > DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 ELSE 0 END) as daily_active
            FROM users
        ')->fetch();
        
        // Get session statistics
        $sessionStats = $pdo->query('
            SELECT 
                COUNT(*) as total_sessions,
                COUNT(DISTINCT user_id) as unique_users,
                AVG(TIMESTAMPDIFF(MINUTE, created_at, last_activity)) as avg_session_length
            FROM sessions
        ')->fetch();
        
        return "
        <div class='dashboard-grid'>
            <div class='stat-card'>
                <div class='stat-icon'>👥</div>
                <div class='stat-content'>
                    <div class='stat-number'>{$userStats['total_users']}</div>
                    <div class='stat-label'>Total Users</div>
                </div>
            </div>
            
            <div class='stat-card'>
                <div class='stat-icon'>🔒</div>
                <div class='stat-content'>
                    <div class='stat-number'>{$userStats['admin_count']}</div>
                    <div class='stat-label'>Administrators</div>
                </div>
            </div>
            
            <div class='stat-card'>
                <div class='stat-icon'>✏️</div>
                <div class='stat-content'>
                    <div class='stat-number'>{$userStats['editor_count']}</div>
                    <div class='stat-label'>Editors</div>
                </div>
            </div>
            
            <div class='stat-card'>
                <div class='stat-icon'>📱</div>
                <div class='stat-content'>
                    <div class='stat-number'>{$userStats['daily_active']}</div>
                    <div class='stat-label'>Daily Active</div>
                </div>
            </div>
        </div>
        
        <div class='dashboard-grid' style='margin-top: 2rem;'>
            <div class='info-panel'>
                <h3>User Breakdown</h3>
                <div class='role-breakdown'>
                    <div class='role-stat'>
                        <span class='role-label admin'>Admin</span>
                        <span class='role-count'>{$userStats['admin_count']}</span>
                    </div>
                    <div class='role-stat'>
                        <span class='role-label editor'>Editor</span>
                        <span class='role-count'>{$userStats['editor_count']}</span>
                    </div>
                    <div class='role-stat'>
                        <span class='role-label user'>User</span>
                        <span class='role-count'>{$userStats['user_count']}</span>
                    </div>
                </div>
            </div>
            
            <div class='info-panel'>
                <h3>System Health</h3>
                <div class='health-metrics'>
                    <div class='metric'>
                        <span class='metric-label'>Active Sessions</span>
                        <span class='metric-value'>{$sessionStats['total_sessions']}</span>
                    </div>
                    <div class='metric'>
                        <span class='metric-label'>Online Users</span>
                        <span class='metric-value'>{$sessionStats['unique_users']}</span>
                    </div>
                    <div class='metric'>
                        <span class='metric-label'>Avg Session (min)</span>
                        <span class='metric-value'>" . round($sessionStats['avg_session_length'] ?? 0, 1) . "</span>
                    </div>
                </div>
            </div>
        </div>
        ";
        
    } catch (Exception $e) {
        return "<div class='error-panel'>Error loading system overview: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

/**
 * User management for admins
 */
function generateUserManagement(): string {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->query('
            SELECT id, username, email, role, is_active, created_at, last_active 
            FROM users 
            ORDER BY created_at DESC
        ');
        $users = $stmt->fetchAll();
        
        $html = "<div class='table-container'>
                    <table class='data-table'>
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Last Active</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>";
        
        foreach ($users as $user) {
            $status = $user['is_active'] ? 'Active' : 'Inactive';
            $statusClass = $user['is_active'] ? 'status-active' : 'status-inactive';
            $lastActive = $user['last_active'] ? date('M j, Y H:i', strtotime($user['last_active'])) : 'Never';
            
            $html .= "
            <tr>
                <td>
                    <div class='user-info'>
                        <strong>{$user['username']}</strong>
                        <br><small>{$user['email']}</small>
                    </div>
                </td>
                <td><span class='role-badge role-{$user['role']}'>{$user['role']}</span></td>
                <td><span class='status-badge {$statusClass}'>{$status}</span></td>
                <td>{$lastActive}</td>
                <td>
                    <button class='btn btn-sm btn-secondary' onclick='editUser({$user['id']})'>Edit</button>
                    <button class='btn btn-sm btn-danger' onclick='deleteUser({$user['id']})'>Delete</button>
                </td>
            </tr>";
        }
        
        $html .= "</tbody></table></div>";
        
        return $html;
        
    } catch (Exception $e) {
        return "<div class='error-panel'>Error loading users: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

/**
 * Generate other dashboard sections (placeholder implementations)
 */
function generateSecurityDashboard(): string {
    return "<div class='info-panel'><h3>Security Dashboard</h3><p>Session monitoring, login attempts, and security logs would be displayed here.</p></div>";
}

function generateSystemSettings(): string {
    return "<div class='info-panel'><h3>System Settings</h3><p>System configuration options would be displayed here.</p></div>";
}

function generateContentManagement(): string {
    return "<div class='info-panel'><h3>Content Management</h3><p>Content creation and editing tools would be displayed here.</p></div>";
}

function generateUserProfiles(): string {
    return "<div class='info-panel'><h3>User Profiles</h3><p>User profile moderation tools would be displayed here.</p></div>";
}

function generateContentReports(): string {
    return "<div class='info-panel'><h3>Content Reports</h3><p>User-generated content reports would be displayed here.</p></div>";
}

function generateUserProfile(array $user): string {
    return "
    <div class='profile-section'>
        <div class='profile-header'>
            <div class='profile-avatar'>👤</div>
            <div class='profile-info'>
                <h2>{$user['username']}</h2>
                <p>{$user['email']}</p>
                <span class='role-badge role-{$user['role']}'>{$user['role']}</span>
            </div>
        </div>
        
        <div class='profile-details'>
            <h3>Account Information</h3>
            <div class='detail-row'>
                <span class='detail-label'>Member since:</span>
                <span class='detail-value'>" . date('F j, Y', strtotime($user['created_at'])) . "</span>
            </div>
            <div class='detail-row'>
                <span class='detail-label'>Last active:</span>
                <span class='detail-value'>" . date('F j, Y H:i', strtotime($user['last_active'])) . "</span>
            </div>
        </div>
    </div>";
}

function generateUserMatches(array $user): string {
    return "<div class='info-panel'><h3>My Content</h3><p>Your personal content and activity would be displayed here.</p></div>";
}

function generateUserSettings(array $user): string {
    return "<div class='info-panel'><h3>Account Settings</h3><p>Account settings and preferences would be displayed here.</p></div>";
}

/**
 * Get navigation items based on user role
 */
function getNavigation(string $role): array {
    switch ($role) {
        case 'admin':
            return [
                'overview' => ['icon' => '📊', 'label' => 'Overview'],
                'users' => ['icon' => '👥', 'label' => 'Users'],
                'security' => ['icon' => '🔒', 'label' => 'Security'],
                'settings' => ['icon' => '⚙️', 'label' => 'Settings']
            ];
        case 'editor':
            return [
                'content' => ['icon' => '📝', 'label' => 'Content'],
                'users' => ['icon' => '👤', 'label' => 'Profiles'],
                'reports' => ['icon' => '📋', 'label' => 'Reports']
            ];
        case 'user':
        default:
            return [
                'profile' => ['icon' => '👤', 'label' => 'Profile'],
                'content' => ['icon' => '�', 'label' => 'Content'],
                'settings' => ['icon' => '⚙️', 'label' => 'Settings']
            ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= ucfirst($currentUser['role']) ?> Dashboard - CMS Auth</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        /* Dashboard-specific styles */
        .dashboard-layout {
            min-height: 100vh;
            background: var(--bg-color);
        }
        
        .dashboard-header {
            background: var(--surface-color);
            border-bottom: 1px solid var(--border-color);
            padding: 0 var(--spacing-lg);
            height: var(--header-height);
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .dashboard-brand {
            display: flex;
            align-items: center;
            gap: var(--spacing-md);
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .dashboard-user {
            display: flex;
            align-items: center;
            gap: var(--spacing-md);
        }
        
        .user-info {
            text-align: right;
        }
        
        .user-name {
            font-weight: 600;
        }
        
        .user-role {
            font-size: var(--font-size-sm);
            color: var(--text-secondary);
        }
        
        .dashboard-main {
            display: flex;
            min-height: calc(100vh - var(--header-height));
        }
        
        .dashboard-sidebar {
            width: var(--sidebar-width);
            background: var(--surface-color);
            border-right: 1px solid var(--border-color);
            padding: var(--spacing-lg);
        }
        
        .dashboard-content {
            flex: 1;
            padding: var(--spacing-lg);
        }
        
        .nav-list {
            list-style: none;
        }
        
        .nav-item {
            margin-bottom: var(--spacing-xs);
        }
        
        .nav-link {
            display: flex;
            align-items: center;
            gap: var(--spacing-md);
            padding: var(--spacing-md);
            text-decoration: none;
            color: var(--text-primary);
            border-radius: var(--border-radius-md);
            transition: var(--transition-fast);
        }
        
        .nav-link:hover,
        .nav-link.active {
            background: var(--primary-color);
            color: white;
        }
        
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: var(--spacing-lg);
            margin-bottom: var(--spacing-lg);
        }
        
        .stat-card {
            background: var(--surface-color);
            padding: var(--spacing-lg);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-sm);
            display: flex;
            align-items: center;
            gap: var(--spacing-md);
        }
        
        .stat-icon {
            font-size: 2rem;
            opacity: 0.7;
        }
        
        .stat-number {
            font-size: var(--font-size-2xl);
            font-weight: 700;
            color: var(--primary-color);
        }
        
        .stat-label {
            color: var(--text-secondary);
            font-size: var(--font-size-sm);
        }
        
        .info-panel {
            background: var(--surface-color);
            padding: var(--spacing-lg);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-sm);
        }
        
        .role-badge {
            padding: var(--spacing-xs) var(--spacing-sm);
            border-radius: var(--border-radius-sm);
            font-size: var(--font-size-xs);
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .role-badge.role-admin { background: #e3f2fd; color: #1976d2; }
        .role-badge.role-editor { background: #f3e5f5; color: #7b1fa2; }
        .role-badge.role-user { background: #e8f5e8; color: #388e3c; }
        
        .status-badge {
            padding: var(--spacing-xs) var(--spacing-sm);
            border-radius: var(--border-radius-sm);
            font-size: var(--font-size-xs);
            font-weight: 600;
        }
        
        .status-active { background: #e8f5e8; color: #388e3c; }
        .status-inactive { background: #ffebee; color: #d32f2f; }
        
        .table-container {
            background: var(--surface-color);
            border-radius: var(--border-radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .data-table th,
        .data-table td {
            padding: var(--spacing-md);
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        
        .data-table th {
            background: var(--bg-color);
            font-weight: 600;
            color: var(--text-secondary);
        }
        
        .profile-section {
            background: var(--surface-color);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }
        
        .profile-header {
            background: var(--gradient-primary);
            color: white;
            padding: var(--spacing-xl);
            display: flex;
            align-items: center;
            gap: var(--spacing-lg);
        }
        
        .profile-avatar {
            font-size: 3rem;
            background: rgba(255, 255, 255, 0.2);
            border-radius: var(--border-radius-full);
            width: 80px;
            height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .profile-details {
            padding: var(--spacing-lg);
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: var(--spacing-md) 0;
            border-bottom: 1px solid var(--border-color);
        }
        
        .detail-label {
            color: var(--text-secondary);
        }
        
        .detail-value {
            font-weight: 600;
        }
        
        /* Mobile responsive */
        @media (max-width: 768px) {
            .dashboard-main {
                flex-direction: column;
            }
            
            .dashboard-sidebar {
                width: 100%;
                border-right: none;
                border-bottom: 1px solid var(--border-color);
            }
            
            .nav-list {
                display: flex;
                overflow-x: auto;
                gap: var(--spacing-sm);
            }
            
            .nav-item {
                margin-bottom: 0;
                flex-shrink: 0;
            }
        }
    </style>
</head>
<body class="dashboard-layout">
    <!-- Header -->
    <header class="dashboard-header">
        <div class="dashboard-brand">
            <span style="font-size: 1.5rem;">�</span>
            <span>CMS Auth</span>
            <span class="role-badge role-<?= $currentUser['role'] ?>"><?= ucfirst($currentUser['role']) ?></span>
        </div>
        
        <div class="dashboard-user">
            <div class="user-info">
                <div class="user-name"><?= htmlspecialchars($currentUser['username']) ?></div>
                <div class="user-role"><?= ucfirst($currentUser['role']) ?> Dashboard</div>
            </div>
            <div class="theme-toggle-container">
                <button type="button" class="theme-toggle" aria-label="Toggle dark mode">
                    <div class="theme-toggle-slider"></div>
                </button>
            </div>
            <a href="logout.php" class="btn btn-secondary btn-sm">Logout</a>
        </div>
    </header>
    
    <!-- Main Dashboard -->
    <main class="dashboard-main">
        <!-- Sidebar Navigation -->
        <aside class="dashboard-sidebar">
            <nav>
                <ul class="nav-list">
                    <?php foreach (getNavigation($currentUser['role']) as $key => $nav): ?>
                        <li class="nav-item">
                            <a href="?tab=<?= $key ?>" class="nav-link <?= $activeTab === $key ? 'active' : '' ?>">
                                <span><?= $nav['icon'] ?></span>
                                <span><?= $nav['label'] ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </nav>
        </aside>
        
        <!-- Content Area -->
        <section class="dashboard-content">
            <?= displayFlashMessages() ?>
            
            <div class="content-header">
                <h1><?= htmlspecialchars($content['title']) ?></h1>
            </div>
            
            <div class="content-body">
                <?= $content['body'] ?>
            </div>
        </section>
    </main>
    
    <script src="assets/app.js"></script>
    <script>
        // Dashboard-specific JavaScript
        function editUser(userId) {
            alert('Edit user functionality would be implemented here. User ID: ' + userId);
        }
        
        function deleteUser(userId) {
            if (confirm('Are you sure you want to delete this user?')) {
                alert('Delete user functionality would be implemented here. User ID: ' + userId);
            }
        }
    </script>
</body>
</html>