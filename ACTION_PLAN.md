# 🚀 QUICK ACTION PLAN

## Immediate Issues to Fix

### 1️⃣ LOGIN NOT WORKING
**Status:** 🔴 Critical  
**Files:**
- Debug Page: Visit `http://localhost/textile_app/debug.php`
- Check Logs: `logs/debug.log`
- Enhanced Files: `includes/db.php`, `index.php`

**What to Look For:**
- Is database connected? ✓
- Does owner table exist? ✓
- Do owner records exist? ✓
- Is password hash correct? ✓

---

### 2️⃣ SQL INJECTION - payments.php
**Status:** 🟡 Important  
**Location:** `pages/payments.php` (lines ~29)
**Issue:** Using string concatenation with `real_escape_string()`
**Solution:** Use prepared statements

```php
// BEFORE (VULNERABLE):
if ($filter_type)  $where .= " AND p.payment_type='" . $conn->real_escape_string($filter_type) . "'";

// AFTER (SAFE):
$stmt = $conn->prepare("SELECT ... FROM payments p LEFT JOIN customers c ON p.customer_id = c.id LEFT JOIN suppliers s ON p.supplier_id = s.id WHERE 1=1 AND p.payment_type = ?");
```

---

### 3️⃣ ADD CSRF PROTECTION
**Status:** 🔴 Critical  
**Affected:** All forms  
**Solution:** Add token-based CSRF protection

```php
// In header.php - Generate token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// In all forms
<input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

// In handlers
if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    die('CSRF token validation failed');
}
```

---

### 4️⃣ RATE LIMITING - Login
**Status:** 🔴 Critical  
**Location:** `index.php`
**Solution:** Add failed attempt tracking

```php
// Add to index.php
session_start();
$ip = $_SERVER['REMOTE_ADDR'];
$key = "login_attempts_$ip";
$attempts = $_SESSION[$key] ?? 0;
$time = $_SESSION["{$key}_time"] ?? 0;

if (time() - $time > 900) { // Reset after 15 min
    $attempts = 0;
    $_SESSION[$key] = 0;
}

if ($attempts >= 5) {
    die('Too many login attempts. Try again later.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION[$key] = ++$attempts;
    $_SESSION["{$key}_time"] = time();
}
```

---

### 5️⃣ ADD .htaccess (Directory Protection)
**Status:** 🟡 Important  
**Create:** `.htaccess` in project root

```apache
# Deny direct access to sensitive directories
<FilesMatch "\.php$">
    Deny from all
</FilesMatch>

# Allow only index.php
<Files "index.php|debug.php|*.jpg|*.css|*.js">
    Allow from all
</Files>

# Deny access to includes and pdf directories
<Directory "includes">
    Deny from all
</Directory>

<Directory "pdf">
    Deny from all
</Directory>
```

---

## Testing Before Going Live

```
1. Test login with admin/admin123
2. Create a test supplier
3. Create a test customer  
4. Create a test contract
5. Create delivery order
6. Record payment
7. View ledger
8. Generate PDF
9. Test logout
10. Check logs in debug page
```

---

## After Fixes - MUST DO

- [ ] Change admin password
- [ ] Test with real data
- [ ] Check all forms submit correctly
- [ ] Verify PDF generation works
- [ ] Test on mobile/tablet
- [ ] Test with different browsers
- [ ] Enable HTTPS (production)
- [ ] Set proper file permissions
- [ ] Setup database backups
- [ ] Setup error logging to file

---

## Database Setup Reminder

```sql
-- Run in MySQL console:
mysql -u root
SOURCE schema.sql;

-- Or in phpMyAdmin:
-- Import schema.sql file
```

Default credentials:
- **Username:** admin
- **Password:** admin123
- **Company:** IF Global Sourcing
- **City:** Karachi

---

Last Updated: 2026-05-06
