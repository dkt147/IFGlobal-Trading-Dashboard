<?php
// Start session FIRST before anything else (before any HTML output)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/db.php';
require_once 'includes/auth.php';

if (isLoggedIn()) {
    header('Location: pages/dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    logDebug('Login attempt', ['username' => $username]);
    
    if (empty($username) || empty($password)) {
        logDebug('Login failed - empty credentials');
        $error = 'Invalid username or password.';
    } else {
        $stmt = $conn->prepare("SELECT id, username, password, full_name, company_name, city, bank_details, phone, email FROM owner WHERE username = ?");
        
        if (!$stmt) {
            logDebug('Query prepare failed', ['error' => $conn->error]);
            $error = 'Database error: ' . $conn->error;
        } else {
            $stmt->bind_param("s", $username);
            
            if (!$stmt->execute()) {
                logDebug('Query execute failed', ['error' => $stmt->error]);
                $error = 'Database error: ' . $stmt->error;
            } else {
                $result = $stmt->get_result();
                $owner = $result->fetch_assoc();
                
                logDebug('Query executed', ['owner_found' => ($owner !== null)]);
                
                if ($owner) {
                    logDebug('Owner record found', ['id' => $owner['id'], 'username' => $owner['username']]);
                    
                    if (password_verify($password, $owner['password'])) {
                        logDebug('Password verification SUCCESS', ['owner_id' => $owner['id']]);
                        $_SESSION['owner_id'] = $owner['id'];
                        $_SESSION['owner'] = $owner;
                        header('Location: pages/dashboard.php');
                        exit;
                    } else {
                        logDebug('Password verification FAILED');
                        $error = 'Invalid username or password.';
                    }
                } else {
                    logDebug('Owner record NOT found', ['username' => $username]);
                    $error = 'Invalid username or password.';
                }
            }
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/png" href="/Global-Sourcing/images/global.png">
<title>IF Global Sourcing — Sign In</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --teal: #2B8CAE;
    --teal-dark: #1F5F7A;
    --teal-light: #E8F3F8;
    --light-bg: #F5F7FA;
    --dark: #1A1F2E;
    --gray: #6B7280;
    --charcoal: #1A1F2E;
    --error: #EF4444;
    --white: #FFFFFF;
  }

  body {
    font-family: 'Inter', sans-serif;
    background: linear-gradient(135deg, var(--teal-light) 0%, var(--light-bg) 100%);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    overflow: hidden;
  }

  /* Decorative corner elements */
  body::after {
    content: '';
    position: fixed;
    top: 0; right: 0;
    width: 350px; height: 350px;
    background: radial-gradient(ellipse at top right, rgba(43,140,174,0.15), transparent 70%);
    pointer-events: none;
  }

  .corner-deco {
    position: fixed;
    bottom: 0; left: 0;
    width: 300px; height: 300px;
    background: radial-gradient(ellipse at bottom left, rgba(43,140,174,0.1), transparent 70%);
    pointer-events: none;
  }

  .login-wrap {
    position: relative;
    z-index: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    width: 100%;
    max-width: 420px;
    padding: 2rem;
    animation: fadeUp 0.8s ease both;
  }

  @keyframes fadeUp {
    from { opacity: 0; transform: translateY(24px); }
    to   { opacity: 1; transform: translateY(0); }
  }

  .logo-mark {
    width: 140px; height: 70px;
    /* border: 2px solid var(--teal); */
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 1.5rem;
    position: relative;
    background: var(--white);
  }
  .logo-mark::before, .logo-mark::after {
    content: '';
    position: absolute;
    /* border: 1px solid var(--teal); */
  }
  .logo-mark::before { inset: -4px; }
  .logo-mark::after  { inset: -8px; opacity: 0.4; }

  .logo-mark img {
    width: 170px;
    height: auto;
    object-fit: contain;
    display: block;
  }

  .brand-name {
    font-family: 'Inter', sans-serif;
    font-size: 1.6rem;
    font-weight: 600;
    color: var(--dark);
    letter-spacing: 0;
    text-transform: uppercase;
    text-align: center;
    margin-bottom: 0.3rem;
  }

  .tagline {
    font-size: 0.65rem;
    color: var(--gray);
    letter-spacing: 0;
    text-transform: uppercase;
    text-align: center;
    margin-bottom: 2.5rem;
  }

  .card {
    width: 100%;
    background: var(--white);
    border: 1px solid #E5E7EB;
    padding: 2.5rem 2rem;
    position: relative;
    box-shadow: 0 4px 40px rgba(26,31,46,0.08);
  }
  .card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
    background: linear-gradient(90deg, transparent, var(--teal), transparent);
  }

  .section-label {
    font-size: 0.6rem;
    letter-spacing: 0;
    text-transform: uppercase;
    color: var(--teal);
    margin-bottom: 1.8rem;
    display: flex;
    align-items: center;
    gap: 0.8rem;
    margin-top: 50px;
  }
  .section-label::after {
    content: '';
    flex: 1;
    height: 1px;
    background: #E5E7EB;
  }

  .field { margin-bottom: 1.4rem; }

  .field label {
    display: block;
    font-size: 0.6rem;
    letter-spacing: 0;
    text-transform: uppercase;
    color: var(--gray);
    margin-bottom: 0.5rem;
  }

  .field input {
    width: 100%;
    background: var(--white);
    border: 1px solid #E5E7EB;
    border-radius: 0;
    padding: 0.75rem 1rem;
    font-family: 'Inter', sans-serif;
    font-size: 0.85rem;
    color: var(--dark);
    outline: none;
    transition: border-color 0.2s, background 0.2s;
  }
  .field input:focus {
    border-color: var(--teal);
    background: var(--teal-light);
  }

  .error-msg {
    background: rgba(239,68,68,0.07);
    border-left: 3px solid var(--error);
    padding: 0.6rem 0.8rem;
    font-size: 0.72rem;
    color: var(--error);
    margin-bottom: 1.2rem;
    letter-spacing: 0;
  }

  .btn-login {
    width: 100%;
    background: var(--teal);
    color: var(--white);
    border: none;
    padding: 0.9rem 1rem;
    font-family: 'Inter', sans-serif;
    font-size: 0.72rem;
    letter-spacing: 0;
    text-transform: uppercase;
    cursor: pointer;
    transition: background 0.2s, color 0.2s;
    position: relative;
    overflow: hidden;
    font-weight: 600;
  }
  .btn-login:hover {
    background: var(--teal-dark);
    color: var(--white);
  }
  .btn-login:active { transform: scale(0.99); }

  .footer-note {
    margin-top: 2rem;
    font-size: 0.6rem;
    letter-spacing: 0;
    color: var(--gray);
    text-align: center;
    text-transform: uppercase;
  }
</style>
<body>
<div class="corner-deco"></div>

  <div class="login-wrap">
    <div class="card">
      <div class="logo-mark"><img src="/Global-Sourcing/images/global.png" alt="Global Sourcing"></div>
      <!-- <div class="brand-name">Global Sourcing</div>
      <div class="tagline">Commission Management Portal</div> -->
      <div class="section-label">Access Portal</div>

    <?php if ($error): ?>
    <div class="error-msg"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="index.php">
      <div class="field">
        <label>Username</label>
        <input type="text" name="username" autocomplete="username" placeholder="Enter username" required>
      </div>
      <div class="field">
        <label>Password</label>
        <input type="password" name="password" autocomplete="current-password" placeholder="••••••••" required>
      </div>
      <button type="submit" class="btn-login">Sign In &rarr;</button>
    </form>
    <div class="footer-note">Karachi &bull; Pakistan</div>
  </div>
</div>
</body>
</html>
