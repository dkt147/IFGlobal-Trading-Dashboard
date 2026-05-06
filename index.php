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
<title>IF Global Sourcing — Sign In</title>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@300;400;500;600&family=DM+Mono:wght@300;400&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --ivory: #F8F5EF;
    --cream: #EDE8DF;
    --clay: #C5A882;
    --bronze: #9C7A4A;
    --charcoal: #2C2A26;
    --ash: #6B6560;
    --error: #B94040;
    --white: #FFFFFF;
  }

  body {
    font-family: 'DM Mono', monospace;
    background: var(--ivory);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    overflow: hidden;
  }

  /* Textile grain background */
  body::before {
    content: '';
    position: fixed; inset: 0;
    background-image: 
      repeating-linear-gradient(0deg, transparent, transparent 2px, rgba(197,168,130,0.04) 2px, rgba(197,168,130,0.04) 4px),
      repeating-linear-gradient(90deg, transparent, transparent 3px, rgba(197,168,130,0.03) 3px, rgba(197,168,130,0.03) 6px);
    pointer-events: none;
    z-index: 0;
  }

  /* Decorative corner elements */
  body::after {
    content: '';
    position: fixed;
    top: 0; right: 0;
    width: 350px; height: 350px;
    background: radial-gradient(ellipse at top right, rgba(197,168,130,0.18), transparent 70%);
    pointer-events: none;
  }

  .corner-deco {
    position: fixed;
    bottom: 0; left: 0;
    width: 300px; height: 300px;
    background: radial-gradient(ellipse at bottom left, rgba(197,168,130,0.12), transparent 70%);
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
    width: 64px; height: 64px;
    border: 1.5px solid var(--clay);
    display: flex; align-items: center; justify-content: center;
    margin-bottom: 1.5rem;
    position: relative;
  }
  .logo-mark::before, .logo-mark::after {
    content: '';
    position: absolute;
    border: 1px solid var(--clay);
  }
  .logo-mark::before { inset: -5px; }
  .logo-mark::after  { inset: -9px; opacity: 0.4; }
  .logo-mark span {
    font-family: 'Cormorant Garamond', serif;
    font-size: 1.5rem;
    font-weight: 500;
    color: var(--bronze);
    letter-spacing: 0.05em;
  }

  .brand-name {
    font-family: 'Cormorant Garamond', serif;
    font-size: 1.6rem;
    font-weight: 300;
    color: var(--charcoal);
    letter-spacing: 0.12em;
    text-transform: uppercase;
    text-align: center;
    margin-bottom: 0.3rem;
  }

  .tagline {
    font-size: 0.65rem;
    color: var(--ash);
    letter-spacing: 0.2em;
    text-transform: uppercase;
    text-align: center;
    margin-bottom: 2.5rem;
  }

  .card {
    width: 100%;
    background: var(--white);
    border: 1px solid rgba(197,168,130,0.3);
    padding: 2.5rem 2rem;
    position: relative;
    box-shadow: 0 4px 40px rgba(44,42,38,0.08);
  }
  .card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 2px;
    background: linear-gradient(90deg, transparent, var(--clay), transparent);
  }

  .section-label {
    font-size: 0.6rem;
    letter-spacing: 0.25em;
    text-transform: uppercase;
    color: var(--clay);
    margin-bottom: 1.8rem;
    display: flex;
    align-items: center;
    gap: 0.8rem;
  }
  .section-label::after {
    content: '';
    flex: 1;
    height: 1px;
    background: rgba(197,168,130,0.3);
  }

  .field { margin-bottom: 1.4rem; }

  .field label {
    display: block;
    font-size: 0.6rem;
    letter-spacing: 0.2em;
    text-transform: uppercase;
    color: var(--ash);
    margin-bottom: 0.5rem;
  }

  .field input {
    width: 100%;
    background: var(--ivory);
    border: 1px solid rgba(197,168,130,0.35);
    border-radius: 0;
    padding: 0.75rem 1rem;
    font-family: 'DM Mono', monospace;
    font-size: 0.85rem;
    color: var(--charcoal);
    outline: none;
    transition: border-color 0.2s, background 0.2s;
  }
  .field input:focus {
    border-color: var(--clay);
    background: #FDFBF8;
  }

  .error-msg {
    background: rgba(185,64,64,0.07);
    border-left: 3px solid var(--error);
    padding: 0.6rem 0.8rem;
    font-size: 0.72rem;
    color: var(--error);
    margin-bottom: 1.2rem;
    letter-spacing: 0.05em;
  }

  .btn-login {
    width: 100%;
    background: var(--charcoal);
    color: var(--cream);
    border: none;
    padding: 0.9rem 1rem;
    font-family: 'DM Mono', monospace;
    font-size: 0.72rem;
    letter-spacing: 0.25em;
    text-transform: uppercase;
    cursor: pointer;
    transition: background 0.2s, color 0.2s;
    position: relative;
    overflow: hidden;
  }
  .btn-login:hover {
    background: var(--bronze);
    color: var(--white);
  }
  .btn-login:active { transform: scale(0.99); }

  .footer-note {
    margin-top: 2rem;
    font-size: 0.6rem;
    letter-spacing: 0.15em;
    color: var(--ash);
    text-align: center;
    text-transform: uppercase;
  }
</style>
<body>
<div class="corner-deco"></div>

<div class="login-wrap">
  <div class="logo-mark"><span>IF</span></div>
  <div class="brand-name">IF Global Sourcing</div>
  <div class="tagline">Commission Management Portal</div>

  <div class="card">
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
  </div>

  <div class="footer-note">Karachi &bull; Pakistan</div>
</div>
</body>
</html>
