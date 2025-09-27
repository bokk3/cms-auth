# 🔐 CMS Authentication System

A robust, secure PHP authentication system with role-based access control.

## ✨ Features

- **🔒 Secure Authentication**: Argon2ID password hashing
- **🛡️ CSRF Protection**: Built-in token validation  
- **⚡ Session Management**: Secure session handling with timeout
- **� Role-Based Access**: Admin, Editor, and User roles with different permissions
- **�📱 Responsive Design**: Mobile-first CSS framework
- **🌙 Theme Toggle**: Dark/Light mode support
- **🔄 Password Reset**: Email-based password recovery framework
- **📊 User Management**: Complete user lifecycle with admin interface

## 🚀 Quick Start

1. **Database Setup**:
   ```sql
   -- Import the schema
   mysql -u root -p your_database < schema.sql
   ```

2. **Configuration**:
   ```php
   // Update db.php with your database credentials
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'your_database');
   define('DB_USER', 'your_username'); 
   define('DB_PASS', 'your_password');
   ```

3. **Web Server**:
   - Point document root to this directory
   - Ensure PHP 7.4+ with PDO MySQL extension

## 📁 Core Files

- `db.php` - Database connection & configuration
- `functions.php` - Core authentication functions with role management
- `admin.php` - Role-based dashboard system
- `login.php` - Login page with validation
- `register.php` - User registration 
- `logout.php` - Secure logout handling
- `password_reset.php` - Password recovery framework
- `admin-only.php` - Admin-restricted area example
- `editor-area.php` - Editor+ restricted area example
- `assets/` - CSS framework & JavaScript
- `schema.sql` - Database structure with role system

## 🔧 Security Features

- ✅ Argon2ID password hashing
- ✅ CSRF token protection
- ✅ SQL injection prevention (PDO)
- ✅ XSS protection (input sanitization)
- ✅ Secure session management
- ✅ Password complexity validation
- ✅ Role-based access control
- ✅ Rate limiting framework

## 💡 Usage Example

```php
// Include the core functions
require_once 'functions.php';
require_once 'db.php';

// Start secure session
startSecureSession();

// Check if user is logged in and has required role
requireMinimumRole('editor'); // Allows editors and admins

if (hasRole('admin')) {
    echo "Welcome Admin: " . $_SESSION['username'];
} else {
    echo "Welcome " . $_SESSION['role'] . ": " . $_SESSION['username'];
}
```

## 👥 Role System

### Admin Role
- Full system access
- User management
- System settings
- Security dashboard

### Editor Role  
- Content management
- User profile moderation
- Content reports
- Cannot access admin areas

### User Role
- Profile management
- Basic app features
- Cannot access admin or editor areas

## 🎨 Styling

The included CSS framework provides:
- Mobile-first responsive design
- Modern glassmorphism effects
- Dark/light theme support
- Beautiful form styling
- Accessible UI components

## 📄 License

MIT License - Feel free to use in your projects!

---

**Crafted with ❤️ by [Truyens.pro](https://truyens.pro)**