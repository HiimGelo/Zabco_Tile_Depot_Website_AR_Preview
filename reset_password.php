<?php
session_start();
include 'db_connect.php';

$token = trim($_GET['token'] ?? '');
$error = '';
$success = false;

/* ── Validate token on GET and POST ── */
if (empty($token)) {
    header("Location: Login&Signup.php?error=invalid_token");
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE token = ? AND used = 0 AND expires_at > NOW() LIMIT 1");
    $stmt->execute([$token]);
    $reset = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

if (!$reset) {
    header("Location: Login&Signup.php?error=expired_token");
    exit;
}

/* ── Handle POST (new password submission) ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPass    = $_POST['new_pass']     ?? '';
    $confirmPass = $_POST['confirm_pass'] ?? '';

    if (strlen($newPass) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($newPass !== $confirmPass) {
        $error = 'Passwords do not match.';
    } else {
        $hashed = password_hash($newPass, PASSWORD_DEFAULT);

        try {
            // Update customer or staff table
            $updated = false;

            $stmt = $pdo->prepare("UPDATE customer SET Password = ? WHERE Email = ?");
            $stmt->execute([$hashed, $reset['email']]);
            if ($stmt->rowCount() > 0) $updated = true;

            if (!$updated) {
                $stmt = $pdo->prepare("UPDATE staff SET Password = ? WHERE Email = ?");
                $stmt->execute([$hashed, $reset['email']]);
                if ($stmt->rowCount() > 0) $updated = true;
            }

            // Mark token as used
            $pdo->prepare("UPDATE password_resets SET used = 1 WHERE token = ?")->execute([$token]);

            // Invalidate any remember-me tokens for security
            $pdo->prepare("DELETE rt FROM remember_tokens rt
                           JOIN customer c ON rt.user_id = c.CustomerID AND rt.user_role = 'customer'
                           WHERE c.Email = ?")->execute([$reset['email']]);
            $pdo->prepare("DELETE rt FROM remember_tokens rt
                           JOIN staff s ON rt.user_id = s.StaffID AND rt.user_role = 'admin'
                           WHERE s.Email = ?")->execute([$reset['email']]);
            setcookie('remember_token', '', time() - 3600, '/', '', true, true);

            $success = true;
        } catch (PDOException $e) {
            $error = "Something went wrong. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password — Zabco Tile Depot</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
    :root {
        --bg:          #111214;
        --surface:     #1a1b1e;
        --surface-2:   #222428;
        --surface-3:   #2a2b30;
        --accent:      #ed8d1b;
        --accent-dark: #c97415;
        --accent-glow: rgba(237,141,27,0.18);
        --text:        #f0f0f0;
        --text-muted:  #999;
        --text-dim:    #666;
        --border:      #2e2f34;
        --radius:      12px;
        --radius-sm:   8px;
        --transition:  0.25s ease;
    }
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html, body {
        height: 100%;
        font-family: 'Inter', system-ui, sans-serif;
        -webkit-font-smoothing: antialiased;
    }
    body {
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        background: var(--bg);
        background-image:
            radial-gradient(ellipse 80% 80% at 20% 20%, rgba(237,141,27,0.06) 0%, transparent 60%),
            radial-gradient(ellipse 60% 60% at 80% 80%, rgba(237,141,27,0.04) 0%, transparent 60%);
        padding: 24px 16px;
    }
    .card {
        width: 100%;
        max-width: 400px;
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 20px;
        padding: 40px 36px;
        box-shadow: 0 24px 60px rgba(0,0,0,0.7);
    }
    .logo-bar {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 28px;
    }
    .logo-bar img { height: 32px; width: auto; }
    .icon-wrap {
        width: 52px; height: 52px;
        background: var(--accent-glow);
        border: 1.5px solid var(--accent);
        border-radius: 14px;
        display: flex; align-items: center; justify-content: center;
        margin-bottom: 18px;
    }
    .icon-wrap svg {
        width: 24px; height: 24px;
        stroke: var(--accent); fill: none;
        stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round;
    }
    h1 {
        font-size: 24px; font-weight: 800;
        color: var(--text); margin-bottom: 6px; letter-spacing: -0.5px;
    }
    .subtitle {
        font-size: 13px; color: var(--text-muted);
        margin-bottom: 24px; line-height: 1.5;
    }
    .input-group {
        position: relative; width: 100%; margin-bottom: 12px;
    }
    .input-group input {
        width: 100%; height: 46px; padding: 0 46px 0 16px;
        background: var(--surface-3); border: 1.5px solid var(--border);
        border-radius: var(--radius-sm); color: var(--text);
        font-size: 14px; font-weight: 500; font-family: inherit; outline: none;
        transition: border-color var(--transition), background var(--transition), box-shadow var(--transition);
    }
    .input-group input::placeholder { color: var(--text-dim); }
    .input-group input:focus {
        border-color: var(--accent); background: var(--surface-2);
        box-shadow: 0 0 0 3px var(--accent-glow);
    }
    .pass-toggle {
        position: absolute; right: 12px; top: 50%;
        transform: translateY(-50%);
        background: transparent; border: none; cursor: pointer;
        color: var(--text-dim); padding: 4px;
        transition: color var(--transition);
        display: flex; align-items: center; justify-content: center;
        -webkit-tap-highlight-color: transparent;
    }
    .pass-toggle svg {
        width: 18px; height: 18px;
        stroke: currentColor; fill: none;
        stroke-width: 2; stroke-linecap: round; stroke-linejoin: round;
        pointer-events: none;
    }
    /* Default: password hidden → show eye (click to reveal) */
    .pass-toggle .icon-eye-off { display: none; }
    .pass-toggle .icon-eye     { display: block; }
    /* Active: password revealed → show eye-off (click to hide) */
    .pass-toggle.active .icon-eye     { display: none; }
    .pass-toggle.active .icon-eye-off { display: block; }
    .pass-toggle:hover { color: var(--text-muted); }
    .pass-toggle.active { color: var(--accent); }
    .btn-primary {
        width: 100%; height: 46px;
        background: var(--accent); border: none;
        border-radius: var(--radius-sm); color: #fff;
        font-size: 14px; font-weight: 700; font-family: inherit;
        cursor: pointer; margin-top: 4px;
        transition: background var(--transition), transform 0.1s ease;
        box-shadow: 0 4px 16px rgba(237,141,27,0.3);
    }
    .btn-primary:hover { background: var(--accent-dark); }
    .btn-primary:active { transform: scale(0.97); }
    .error-box {
        background: #221616; border: 1px solid #6a2d2d;
        border-radius: var(--radius-sm); color: #d87d7d;
        font-size: 13px; padding: 11px 14px;
        margin-bottom: 16px;
    }
    .success-box {
        text-align: center;
    }
    .success-box .check-icon {
        width: 56px; height: 56px; border-radius: 50%;
        background: rgba(45,106,45,0.2); border: 1.5px solid #2d6a2d;
        display: flex; align-items: center; justify-content: center;
        margin: 0 auto 18px;
    }
    .success-box .check-icon svg {
        width: 26px; height: 26px; stroke: #7dd87d;
        fill: none; stroke-width: 2.5;
        stroke-linecap: round; stroke-linejoin: round;
    }
    .back-link {
        display: block; text-align: center;
        margin-top: 20px; font-size: 13px;
        color: var(--text-dim); text-decoration: none;
        transition: color var(--transition);
    }
    .back-link:hover { color: var(--accent); }
    </style>
</head>
<body>
<div class="card">
    <div class="logo-bar">
        <img src="Logo.png" alt="Zabco Tile Depot">
    </div>

    <?php if ($success): ?>
    <div class="success-box">
        <div class="check-icon">
            <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
        </div>
        <h1>Password updated!</h1>
        <p class="subtitle">Your password has been reset successfully. You can now log in with your new password.</p>
        <a href="Login&Signup.php" class="btn-primary" style="display:flex;align-items:center;justify-content:center;text-decoration:none;">Go to Log In</a>
    </div>

    <?php else: ?>
    <div class="icon-wrap">
        <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
    </div>
    <h1>Set new password</h1>
    <p class="subtitle">Choose a strong password for your account.</p>

    <?php if ($error): ?>
    <div class="error-box"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="?token=<?= urlencode($token) ?>">
        <div class="input-group">
            <input type="password" name="new_pass" id="newPass"
                   placeholder="New password (min. 8 chars)"
                   required minlength="8" autocomplete="new-password">
            <button type="button" class="pass-toggle" onclick="togglePass('newPass', this)" aria-label="Toggle"><svg class="icon-eye" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg><svg class="icon-eye-off" viewBox="0 0 24 24"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg></button>
        </div>
        <div class="input-group">
            <input type="password" name="confirm_pass" id="confirmPass"
                   placeholder="Confirm new password"
                   required minlength="8" autocomplete="new-password">
            <button type="button" class="pass-toggle" onclick="togglePass('confirmPass', this)" aria-label="Toggle"><svg class="icon-eye" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg><svg class="icon-eye-off" viewBox="0 0 24 24"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg></button>
        </div>
        <button type="submit" class="btn-primary">Update Password</button>
    </form>

    <a href="Login&Signup.php" class="back-link">← Back to Log In</a>
    <?php endif; ?>
</div>

<script>
function togglePass(id, btn) {
    const input = document.getElementById(id);
    if (!input) return;
    const isHidden = input.type === 'password';
    input.type = isHidden ? 'text' : 'password';
    btn.classList.toggle('active', isHidden);
}
</script>
</body>
</html>