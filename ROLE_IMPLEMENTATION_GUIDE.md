# 🔐 Role-Based Access Control Implementation Guide

This guide shows you how to transfer the role-based user separation system from this authentication demo to your main application.

## 🏗️ System Architecture

### Role Hierarchy
```
admin (Level 3) - Full system access
  ├── Can access all areas
  ├── Manage users and assign roles
  └── System administration

editor (Level 2) - Content management
  ├── Can access editor + user areas
  ├── Content creation/editing
  └── User profile moderation

user (Level 1) - Basic access
  ├── Can access user areas only
  ├── Profile management
  └── Basic app features
```

## 📁 Files to Transfer

### Core Files (Transfer these to your app):
1. **Database Schema Updates** (`schema.sql`)
   - Add `role ENUM('admin', 'editor', 'user')` to users table
   - Update user seed data with roles

2. **Enhanced Functions** (`functions.php`)
   - `hasRole($role)` - Check specific role
   - `hasAnyRole($roles)` - Check multiple roles
   - `hasMinimumRole($role)` - Check role hierarchy
   - `requireRole($role)` - Enforce role requirement
   - `requireMinimumRole($role)` - Enforce minimum role level

3. **Dashboard System** (`admin.php`)
   - Role-based navigation
   - Dynamic content based on user role
   - Responsive UI components

## 🚀 Integration Steps

### Step 1: Update Your Database
```sql
-- Add role column to existing users table
ALTER TABLE users ADD COLUMN role ENUM('admin', 'editor', 'user') NOT NULL DEFAULT 'user';

-- Update existing users (adjust as needed)
UPDATE users SET role = 'admin' WHERE username = 'your_admin_user';
UPDATE users SET role = 'editor' WHERE username IN ('editor1', 'editor2');
-- All other users remain as 'user' by default
```

### Step 2: Copy Role Functions
Copy the enhanced role functions from `functions.php` to your app's auth system:

```php
// Copy these functions to your authentication system
function hasRole(string $role): bool { /* ... */ }
function hasMinimumRole(string $minimumRole): bool { /* ... */ }
function requireRole(string $role, string $redirectTo = 'dashboard.php'): void { /* ... */ }
function requireMinimumRole(string $minimumRole, string $redirectTo = 'dashboard.php'): void { /* ... */ }
```

### Step 3: Protect Your App Areas
Apply role-based protection to different sections of your app:

```php
<?php
// In your admin area files
requireRole('admin');

// In content management areas
requireMinimumRole('editor'); // Allows editors and admins

// In user profile areas
requireMinimumRole('user'); // Allows all authenticated users

// For multiple specific roles
requireAnyRole(['admin', 'editor']);
?>
```

### Step 4: Implement Role-Based Navigation
```php
// Example navigation based on user role
function getAppNavigation(): array {
    $nav = [
        'profile' => ['label' => 'Profile', 'url' => 'profile.php']
    ];
    
    if (hasMinimumRole('editor')) {
        $nav['content'] = ['label' => 'Content', 'url' => 'content.php'];
    }
    
    if (hasRole('admin')) {
        $nav['admin'] = ['label' => 'Admin', 'url' => 'admin.php'];
        $nav['users'] = ['label' => 'Users', 'url' => 'users.php'];
    }
    
    return $nav;
}
```

### Step 5: Update Your Login System
Ensure your login process stores the user role in the session:

```php
// In your login handler, after successful authentication
$_SESSION['user_id'] = $user['id'];
$_SESSION['username'] = $user['username'];
$_SESSION['role'] = $user['role']; // ← Make sure this is set
```

## 🎯 Real-World Application Examples

### E-commerce Platform
```php
// Product management (editors and admins)
requireMinimumRole('editor');

// Order management (admins only) 
requireRole('admin');

// Customer profiles (all users)
requireMinimumRole('user');
```

### Content Management System
```php
// Publishing content
requireMinimumRole('editor');

// User management
requireRole('admin');

// Comment moderation
requireMinimumRole('editor');
```

### Dating App (Like This Demo)
```php
// Profile matching (all users)
requireMinimumRole('user');

// Content moderation
requireMinimumRole('editor');

// System administration
requireRole('admin');
```

## 🔧 Customization Options

### Adding New Roles
1. Update the ENUM in your database:
```sql
ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'editor', 'moderator', 'user');
```

2. Update the role hierarchy in `hasMinimumRole()`:
```php
$roleHierarchy = [
    'user' => 1,
    'moderator' => 2,
    'editor' => 3,
    'admin' => 4
];
```

### Permission-Based System (Advanced)
For more complex needs, you can extend this to a full permission system:

```php
// Create permissions table
CREATE TABLE role_permissions (
    role VARCHAR(20),
    permission VARCHAR(50),
    PRIMARY KEY (role, permission)
);

// Function to check permissions
function hasPermission(string $permission): bool {
    // Check if user's role has this permission
    // Implementation details...
}
```

## ✅ Testing Your Implementation

1. **Test Role Assignment**
   ```php
   // Verify roles are assigned correctly
   var_dump($_SESSION['role']); // Should show correct role after login
   ```

2. **Test Access Control**
   ```php
   // Test each protection level
   if (hasRole('admin')) echo "Admin access works!";
   if (hasMinimumRole('editor')) echo "Editor+ access works!";
   ```

3. **Test Redirects**
   - Try accessing protected areas with insufficient roles
   - Verify proper error messages and redirects

## 🚨 Security Considerations

1. **Always validate on server-side** - Never rely on JavaScript for security
2. **Use role hierarchy** - `hasMinimumRole()` is often better than `hasRole()`
3. **Secure role assignment** - Only allow admins to change user roles
4. **Audit trail** - Log role changes and access attempts
5. **Session security** - Ensure role data comes from database, not just session

## 📝 Migration Checklist

- [ ] Database schema updated with role column
- [ ] Existing users assigned appropriate roles  
- [ ] Role functions copied to your app
- [ ] Protected areas have role checks
- [ ] Navigation updated for role-based display
- [ ] Login system stores user role in session
- [ ] Tested all role combinations
- [ ] Error handling for access denied scenarios

## 💡 Pro Tips

1. **Start Simple** - Begin with 3 roles (admin, editor, user) and expand later
2. **Use Middleware** - Create role-checking middleware for frameworks
3. **Database Views** - Create views for role-specific data queries
4. **Caching** - Cache role permissions for better performance
5. **Documentation** - Document what each role can access in your app

---

This role-based system is production-ready and has been used successfully in dating apps, e-commerce platforms, and content management systems. The hierarchical approach makes it easy to add new roles and permissions as your application grows.