# 🔐 CMS Authentication System

A robust, secure PHP authentication system extracted from LoveConnect dating app.

## ✨ Features

- **🔒 Secure Authentication**: Argon2ID password hashing
- **🛡️ CSRF Protection**: Built-in token validation  
- **⚡ Session Management**: Secure session handling with timeout
- **📱 Responsive Design**: Mobile-first CSS framework
- **🌙 Theme Toggle**: Dark/Light mode support
- **🔄 Password Reset**: Email-based password recovery
- **📊 User Management**: Complete user lifecycle

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
- `functions.php` - Core authentication functions
- `login.php` - Login page with validation
- `register.php` - User registration 
- `logout.php` - Secure logout handling
- `password_reset.php` - Password recovery
- `assets/` - CSS framework & JavaScript
- `schema.sql` - Database structure

## 🔧 Security Features

- ✅ Argon2ID password hashing
- ✅ CSRF token protection
- ✅ SQL injection prevention (PDO)
- ✅ XSS protection (input sanitization)
- ✅ Secure session management
- ✅ Password complexity validation
- ✅ Rate limiting ready

## 💡 Usage Example

```php
// Include the core functions
require_once 'functions.php';
require_once 'db.php';

// Start secure session
startSecureSession();

// Check if user is logged in
if (isLoggedIn()) {
    echo "Welcome " . $_SESSION['username'];
} else {
    header('Location: login.php');
}
```

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