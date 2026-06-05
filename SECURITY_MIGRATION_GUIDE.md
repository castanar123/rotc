# Database Security Migration Guide

This guide explains how to migrate existing PHP files to use the new security implementations.

## Overview of Security Enhancements

### 1. New Security Files Created
- `secure_db.php` - Secure database connection class
- `input_validator.php` - Input validation and sanitization
- `security_config.php` - Role-based access controls and encryption
- `secure_error_handler.php` - Secure error handling
- `security_test.php` - Comprehensive security testing

### 2. Secure Versions Created
- `admin_dashboard_secure.php` - Secure version of admin dashboard
- `user_management_secure.php` - Secure version of user management

## Migration Steps for Existing Files

### Step 1: Include Security Files
Add these includes at the top of your PHP files:

```php
require_once 'secure_db.php';
require_once 'input_validator.php';
require_once 'security_config.php';
require_once 'secure_error_handler.php';
```

### Step 2: Replace Database Connections

**Before (Insecure):**
```php
$pdo = new PDO("mysql:host=localhost;dbname=qr_attendance", $username, $password);
$result = $pdo->query("SELECT * FROM users WHERE id = " . $_GET['id']);
```

**After (Secure):**
```php
$db = new SecureDatabase();
$validator = new InputValidator();
$id = $validator->validateInteger($_GET['id']);
if ($id) {
    $result = $db->secureQuery("SELECT * FROM users WHERE id = ?", [$id]);
}
```

### Step 3: Implement Input Validation

**Before (Insecure):**
```php
$email = $_POST['email'];
$name = $_POST['name'];
```

**After (Secure):**
```php
$validator = new InputValidator();
$email = $validator->validateEmail($_POST['email']);
$name = $validator->sanitizeString($_POST['name'], 100);

if (!$email || !$name) {
    throw new Exception('Invalid input data');
}
```

### Step 4: Add Access Control Checks

```php
$security = new SecurityConfig();

// Check page access
if (!$security->checkPageAccess($_SESSION['role'], basename(__FILE__))) {
    header('Location: unauthorized.php');
    exit;
}

// Check action permissions
if (!$security->checkActionPermission($_SESSION['role'], 'delete_user')) {
    throw new Exception('Insufficient permissions');
}
```

### Step 5: Implement CSRF Protection

**In forms:**
```php
$security = new SecurityConfig();
$csrfToken = $security->generateCSRFToken();
echo '<input type="hidden" name="csrf_token" value="' . $csrfToken . '">';
```

**In form processing:**
```php
if (!$security->validateCSRFToken($_POST['csrf_token'])) {
    throw new Exception('CSRF token validation failed');
}
```

### Step 6: Encrypt Sensitive Data

```php
$security = new SecurityConfig();

// Encrypt before storing
$encryptedData = $security->encryptData($sensitiveInfo);
$db->secureQuery("INSERT INTO table (encrypted_field) VALUES (?)", [$encryptedData]);

// Decrypt when retrieving
$result = $db->secureQuery("SELECT encrypted_field FROM table WHERE id = ?", [$id]);
$decryptedData = $security->decryptData($result[0]['encrypted_field']);
```

### Step 7: Add Audit Logging

```php
$db->logAuditEvent(
    $_SESSION['username'],
    'user_login',
    'User logged in successfully',
    $_SERVER['REMOTE_ADDR']
);
```

## Files That Need Migration

Based on the security analysis, these files contain raw SQL queries and need migration:

### High Priority (Admin Functions)
1. `registration_management.php`
2. `advance_rotc_management.php`
3. `profile_management.php`
4. `debug_admin_dashboard.php`

### Medium Priority (User Functions)
5. `view_profile.php`
6. `schedule.php`
7. `login.php`
8. `register.php`
9. `settings.php`

### Low Priority (Utility Scripts)
10. `check_db_structure.php`
11. `fix_database.php`
12. `test_dashboard_access.php`
13. `check_database.php`

## Security Testing

After migrating each file:

1. Run the security test suite:
   ```
   http://localhost:8000/security_test.php?run_tests=1
   ```

2. Test specific vulnerabilities:
   - SQL injection attempts
   - XSS attacks
   - CSRF attacks
   - Unauthorized access attempts
   - Rate limiting

## Best Practices

1. **Never trust user input** - Always validate and sanitize
2. **Use prepared statements** - Never concatenate SQL queries
3. **Implement least privilege** - Users should only access what they need
4. **Log security events** - Track all important actions
5. **Encrypt sensitive data** - Both in transit and at rest
6. **Handle errors securely** - Don't expose system information
7. **Use CSRF tokens** - Protect against cross-site request forgery
8. **Implement rate limiting** - Prevent brute force attacks

## Emergency Procedures

If a security breach is detected:

1. Check audit logs: `SELECT * FROM security_audit_logs ORDER BY created_at DESC`
2. Review error logs: Check `logs/security_errors.log`
3. Block suspicious IPs using the rate limiting system
4. Reset all user sessions if necessary
5. Update encryption keys if data was compromised

## Monitoring

Regularly monitor:
- Security audit logs for suspicious activity
- Error logs for security violations
- Failed login attempts
- Rate limiting triggers
- Database access patterns

## Support

For questions about the security implementation:
1. Review the code comments in security files
2. Run the security test suite
3. Check the audit logs for examples
4. Test in a development environment first

---

**Remember: Security is an ongoing process. Regularly update and test your security measures.**