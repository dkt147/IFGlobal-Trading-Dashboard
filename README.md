# IF Global Sourcing — Commission Management System

## Setup Instructions

### 1. Requirements
- PHP 7.4+ with MySQLi extension
- MySQL 5.7+ or MariaDB 10.3+
- Web server (Apache/Nginx) with mod_rewrite

### 2. Database Setup
```sql
-- Import the schema:
mysql -u root -p < schema.sql
```
Or open phpMyAdmin and import `schema.sql`.

### 3. Configure Database
Edit `includes/db.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');
define('DB_NAME', 'textile_commission');
```

### 4. Web Server Config (Apache)
Place files in your web root (e.g., `/var/www/html/textile/` or `htdocs/textile/`).

Add `.htaccess` in root:
```apache
RewriteEngine On
RewriteCond %{REQUEST_URI} ^/$
RewriteRule ^ /index.php [L]
```

### 5. Default Login
- **Username:** `admin`
- **Password:** `password`

> ⚠️ Change the password after first login (update the hash in the `owner` table using `password_hash('newpassword', PASSWORD_DEFAULT)`)

### 6. Change Owner Password
```php
// Run this once in a temporary PHP file:
echo password_hash('your_new_password', PASSWORD_DEFAULT);
// Then update the owner table with the generated hash
```

---

## Features

| Feature | Description |
|---------|-------------|
| **Login** | Secure session-based auth |
| **Dashboard** | Stats: suppliers, customers, contracts, orders, totals |
| **Suppliers** | Add/edit/delete suppliers |
| **Customers** | Add/edit/delete customers |
| **Contracts** | Date, supplier, customer, description, qty, unit, rate, commission (% or per unit) |
| **Delivery Orders** | Date, type (send/return), link to contract, auto-fill from contract |
| **Payments** | Customer or supplier payments, payment or return type |
| **Ledger** | Full running balance ledger per customer/supplier (like Excel view) |
| **Commission PDF** | Auto-generate commission invoices from contracts, print to PDF |

---

## File Structure
```
textile_app/
├── index.php              # Login page
├── logout.php
├── schema.sql             # Database schema
├── css/app.css            # Main stylesheet
├── js/app.js              # Main JS
├── includes/
│   ├── db.php             # Database connection
│   ├── auth.php           # Session auth
│   ├── header.php         # Sidebar + nav
│   └── footer.php
└── pages/
    ├── dashboard.php
    ├── suppliers.php
    ├── customers.php
    ├── contracts.php
    ├── delivery_orders.php
    ├── payments.php
    ├── ledger.php
    └── commission_pdf.php
```
