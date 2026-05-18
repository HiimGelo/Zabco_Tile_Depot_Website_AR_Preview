<?php
session_start();
include 'db_connect.php';

/*
 * ============================================================
 *  REQUIRED — run this SQL once to create the pending table:
 * ============================================================
 *  CREATE TABLE IF NOT EXISTS pending_customers (
 *      id         INT AUTO_INCREMENT PRIMARY KEY,
 *      token      VARCHAR(64)  NOT NULL UNIQUE,
 *      email      VARCHAR(255) NOT NULL,
 *      first_name VARCHAR(100) NOT NULL,
 *      last_name  VARCHAR(100) NOT NULL,
 *      address    TEXT         NOT NULL,
 *      password   VARCHAR(255) NOT NULL,
 *      phone      VARCHAR(20)  NOT NULL,
 *      created_at DATETIME     DEFAULT CURRENT_TIMESTAMP,
 *      expires_at DATETIME     NOT NULL,
 *      INDEX idx_token (token),
 *      INDEX idx_email (email)
 *  );
 *
 *  CREATE TABLE IF NOT EXISTS password_resets (
 *      id         INT AUTO_INCREMENT PRIMARY KEY,
 *      email      VARCHAR(255) NOT NULL,
 *      token      VARCHAR(64)  NOT NULL UNIQUE,
 *      expires_at DATETIME     NOT NULL,
 *      used       TINYINT(1)   DEFAULT 0,
 *      created_at DATETIME     DEFAULT CURRENT_TIMESTAMP,
 *      INDEX idx_token (token),
 *      INDEX idx_email (email)
 *  );
 *
 *  CREATE TABLE IF NOT EXISTS remember_tokens (
 *      id         INT AUTO_INCREMENT PRIMARY KEY,
 *      user_id    INT          NOT NULL,
 *      user_role  VARCHAR(20)  NOT NULL,
 *      token      VARCHAR(64)  NOT NULL UNIQUE,
 *      expires_at DATETIME     NOT NULL,
 *      created_at DATETIME     DEFAULT CURRENT_TIMESTAMP,
 *      INDEX idx_token (token)
 *  );
 * ============================================================
 */

/* ============================================================
   EMAIL VERIFICATION HELPER
   Uses PHP's built-in mail() by default.

   ── To use PHPMailer / SMTP instead ──────────────────────────
   1. Install via Composer:  composer require phpmailer/phpmailer
   2. Comment out the mail() call below.
   3. Uncomment the PHPMailer block and fill in your SMTP creds.
   ─────────────────────────────────────────────────────────── */
function sendVerificationEmail(string $toEmail, string $toName, string $token): bool
{
    $scheme    = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host      = $_SERVER['HTTP_HOST'];
    $dir       = rtrim(dirname($_SERVER['PHP_SELF']), '/');
    $verifyUrl = "{$scheme}://{$host}{$dir}/verify_email.php?token=" . urlencode($token);

    $appName   = 'Zabco Tile Depot';
    $fromEmail = 'noreply@zabcotiledepot.com';   // ← change to your sender address
    $subject   = "Verify your {$appName} account";

    // Plain-text body
    $plain = <<<TEXT
Hello {$toName},

Thanks for signing up with {$appName}!

Click the link below to verify your email address.
This link expires in 24 hours.

{$verifyUrl}

If you did not create an account, you can safely ignore this email.

— The {$appName} Team
TEXT;

    // HTML body
    $html = <<<HTML
<!DOCTYPE html>
<html>
<body style="margin:0;padding:0;background:#111214;font-family:Inter,system-ui,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0">
    <tr><td align="center" style="padding:40px 16px;">
      <table width="100%" style="max-width:480px;background:#1a1b1e;border:1px solid #2e2f34;border-radius:12px;overflow:hidden;">
        <tr><td style="background:linear-gradient(135deg,#ed8d1b,#c97415);padding:28px 32px;">
          <p style="margin:0;font-size:20px;font-weight:800;color:#fff;">{$appName}</p>
        </td></tr>
        <tr><td style="padding:32px;">
          <p style="margin:0 0 8px;font-size:22px;font-weight:700;color:#f0f0f0;">Verify your email</p>
          <p style="margin:0 0 24px;font-size:14px;color:#999;line-height:1.6;">
            Hello {$toName}, thanks for joining! Click the button below to activate your account.
            This link is valid for <strong style="color:#f0f0f0;">24 hours</strong>.
          </p>
          <a href="{$verifyUrl}"
             style="display:inline-block;padding:14px 28px;background:#ed8d1b;color:#fff;
                    text-decoration:none;border-radius:8px;font-weight:700;font-size:14px;">
            Verify my email address
          </a>
          <p style="margin:24px 0 0;font-size:12px;color:#666;word-break:break-all;">
            Or copy this link: {$verifyUrl}
          </p>
        </td></tr>
        <tr><td style="padding:16px 32px;border-top:1px solid #2e2f34;">
          <p style="margin:0;font-size:11px;color:#666;">
            If you didn't create this account, ignore this email — no action is needed.
          </p>
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;

    // ── PHP mail() ────────────────────────────────────────────
    $boundary = md5(uniqid());
    $headers  = "From: {$appName} <{$fromEmail}>\r\n";
    $headers .= "Reply-To: {$fromEmail}\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";

    $body  = "--{$boundary}\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n{$plain}\r\n";
    $body .= "--{$boundary}\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n\r\n{$html}\r\n";
    $body .= "--{$boundary}--";

    return mail($toEmail, $subject, $body, $headers);
    // ──────────────────────────────────────────────────────────

    /* ── PHPMailer / SMTP alternative ───────────────────────────
    use PHPMailer\PHPMailer\PHPMailer;
    require __DIR__ . '/vendor/autoload.php';
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.example.com';    // ← SMTP host
        $mail->SMTPAuth   = true;
        $mail->Username   = 'you@example.com';     // ← SMTP user
        $mail->Password   = 'your_password';       // ← SMTP pass
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->setFrom($fromEmail, $appName);
        $mail->addAddress($toEmail, $toName);
        $mail->Subject  = $subject;
        $mail->Body     = $html;
        $mail->AltBody  = $plain;
        $mail->send();
        return true;
    } catch (\Exception $e) { return false; }
    ─────────────────────────────────────────────────────────── */
}

/* ============================================================
   PASSWORD RESET EMAIL HELPER
   ============================================================ */
function sendPasswordResetEmail(string $toEmail, string $toName, string $token): bool
{
    $scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host     = $_SERVER['HTTP_HOST'];
    $dir      = rtrim(dirname($_SERVER['PHP_SELF']), '/');
    $resetUrl = "{$scheme}://{$host}{$dir}/reset_password.php?token=" . urlencode($token);

    $appName   = 'Zabco Tile Depot';
    $fromEmail = 'noreply@zabcotiledepot.com';
    $subject   = "Reset your {$appName} password";

    $plain = <<<TEXT
Hello {$toName},

We received a request to reset your password for your {$appName} account.

Click the link below to set a new password. This link expires in 1 hour.

{$resetUrl}

If you did not request a password reset, you can safely ignore this email.

— The {$appName} Team
TEXT;

    $html = <<<HTML
<!DOCTYPE html>
<html>
<body style="margin:0;padding:0;background:#111214;font-family:Inter,system-ui,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0">
    <tr><td align="center" style="padding:40px 16px;">
      <table width="100%" style="max-width:480px;background:#1a1b1e;border:1px solid #2e2f34;border-radius:12px;overflow:hidden;">
        <tr><td style="background:linear-gradient(135deg,#ed8d1b,#c97415);padding:28px 32px;">
          <p style="margin:0;font-size:20px;font-weight:800;color:#fff;">{$appName}</p>
        </td></tr>
        <tr><td style="padding:32px;">
          <p style="margin:0 0 8px;font-size:22px;font-weight:700;color:#f0f0f0;">Reset your password</p>
          <p style="margin:0 0 24px;font-size:14px;color:#999;line-height:1.6;">
            Hello {$toName}, click the button below to reset your password.
            This link is valid for <strong style="color:#f0f0f0;">1 hour</strong>.
          </p>
          <a href="{$resetUrl}"
             style="display:inline-block;padding:14px 28px;background:#ed8d1b;color:#fff;
                    text-decoration:none;border-radius:8px;font-weight:700;font-size:14px;">
            Reset my password
          </a>
          <p style="margin:24px 0 0;font-size:12px;color:#666;word-break:break-all;">
            Or copy this link: {$resetUrl}
          </p>
        </td></tr>
        <tr><td style="padding:16px 32px;border-top:1px solid #2e2f34;">
          <p style="margin:0;font-size:11px;color:#666;">
            If you didn't request this, ignore this email — your password will not change.
          </p>
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;

    $boundary = md5(uniqid());
    $headers  = "From: {$appName} <{$fromEmail}>\r\n";
    $headers .= "Reply-To: {$fromEmail}\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";

    $body  = "--{$boundary}\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n{$plain}\r\n";
    $body .= "--{$boundary}\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n\r\n{$html}\r\n";
    $body .= "--{$boundary}--";

    return mail($toEmail, $subject, $body, $headers);
}

/* ============================================================
   REMEMBER ME — auto-login from cookie on page load
   ============================================================ */
if (!isset($_SESSION['CustomerID']) && !isset($_SESSION['StaffID']) && isset($_COOKIE['remember_token'])) {
    $cookieToken = $_COOKIE['remember_token'];
    try {
        $stmt = $pdo->prepare("SELECT * FROM remember_tokens WHERE token = ? AND expires_at > NOW() LIMIT 1");
        $stmt->execute([$cookieToken]);
        $rt = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($rt) {
            if ($rt['user_role'] === 'customer') {
                $u = $pdo->prepare("SELECT CustomerID AS id, FirstName, Email FROM customer WHERE CustomerID = ?");
                $u->execute([$rt['user_id']]);
                $user = $u->fetch(PDO::FETCH_ASSOC);
                if ($user) {
                    $_SESSION['CustomerID'] = $user['id'];
                    $_SESSION['FirstName']  = $user['FirstName'];
                    $_SESSION['Email']      = $user['Email'];
                    $_SESSION['role']       = 'customer';
                    header("Location: index.php");
                    exit;
                }
            } else {
                $u = $pdo->prepare("SELECT StaffID AS id, FirstName, Email FROM staff WHERE StaffID = ?");
                $u->execute([$rt['user_id']]);
                $user = $u->fetch(PDO::FETCH_ASSOC);
                if ($user) {
                    $_SESSION['StaffID']   = $user['id'];
                    $_SESSION['FirstName'] = $user['FirstName'];
                    $_SESSION['Email']     = $user['Email'];
                    $_SESSION['role']      = 'admin';
                    header("Location: index.php");
                    exit;
                }
            }
            // Token points to a deleted user — clean it up
            $pdo->prepare("DELETE FROM remember_tokens WHERE token = ?")->execute([$cookieToken]);
            setcookie('remember_token', '', time() - 3600, '/', '', true, true);
        }
    } catch (PDOException $e) { /* silently skip */ }
}

/* ============================================================
   REQUEST HANDLERS
   ============================================================ */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST['action'] ?? '';

    /* ── SIGNUP ── */
    if ($action === "signup") {
        $email    = trim($_POST["email"]   ?? '');
        $fname    = trim($_POST["fname"]   ?? '');
        $lname    = trim($_POST["lname"]   ?? '');
        $address  = trim($_POST["add"]     ?? '');
        $password = trim($_POST["pass"]    ?? '');
        $phone    = trim($_POST["phone"]   ?? '');

        if (empty($email) || empty($fname) || empty($lname) || empty($address) || empty($password) || empty($phone)) {
            echo "<script>alert('All fields are required.'); window.history.back();</script>";
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo "<script>alert('Please enter a valid email address.'); window.history.back();</script>";
            exit;
        }

        if (strlen($password) < 8) {
            echo "<script>alert('Password must be at least 8 characters.'); window.history.back();</script>";
            exit;
        }

        $hashedPass = password_hash($password, PASSWORD_DEFAULT);

        try {
            // Already a verified customer?
            $check = $pdo->prepare("SELECT COUNT(*) FROM customer WHERE Email = ?");
            $check->execute([$email]);
            if ($check->fetchColumn() > 0) {
                echo "<script>alert('An account with this email already exists.'); window.history.back();</script>";
                exit;
            }

            // Remove any stale pending record for this address (allows re-registration)
            $pdo->prepare("DELETE FROM pending_customers WHERE email = ?")->execute([$email]);

            // Create a new pending record with a 24-hour token
            $token   = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));

            $stmt = $pdo->prepare(
                "INSERT INTO pending_customers
                     (token, email, first_name, last_name, address, password, phone, expires_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([$token, $email, $fname, $lname, $address, $hashedPass, $phone, $expires]);

            // Attempt to send the verification email
            $sent = sendVerificationEmail($email, $fname, $token);

            if (!$sent) {
                $pdo->prepare("DELETE FROM pending_customers WHERE token = ?")->execute([$token]);
                echo "<script>alert('Could not send the verification email. Please try again or contact support.'); window.history.back();</script>";
                exit;
            }

            header("Location: Login&Signup.php?pending=1&email=" . urlencode($email));
            exit;

        } catch (PDOException $e) {
            die("Database error: " . $e->getMessage());
        }

    /* ── RESEND VERIFICATION ── */
    } elseif ($action === "resend") {
        $email = trim($_POST["email"] ?? '');

        if (empty($email)) {
            header("Location: Login&Signup.php?show=signup");
            exit;
        }

        try {
            $stmt = $pdo->prepare("SELECT * FROM pending_customers WHERE email = ?");
            $stmt->execute([$email]);
            $pending = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$pending) {
                // No pending record — maybe already verified
                header("Location: Login&Signup.php?error=no_pending");
                exit;
            }

            // Regenerate token and extend expiry
            $newToken   = bin2hex(random_bytes(32));
            $newExpires = date('Y-m-d H:i:s', strtotime('+24 hours'));
            $pdo->prepare("UPDATE pending_customers SET token = ?, expires_at = ? WHERE email = ?")
                ->execute([$newToken, $newExpires, $email]);

            sendVerificationEmail($email, $pending['first_name'], $newToken);

            header("Location: Login&Signup.php?pending=1&resent=1&email=" . urlencode($email));
            exit;

        } catch (PDOException $e) {
            die("Database error: " . $e->getMessage());
        }

    /* ── FORGOT PASSWORD ── */
    } elseif ($action === "forgot_password") {
        $email = trim($_POST['email'] ?? '');

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            header("Location: Login&Signup.php?fp_error=invalid");
            exit;
        }

        try {
            // Check customer table first, then staff
            $stmt = $pdo->prepare("SELECT FirstName FROM customer WHERE Email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$user) {
                $stmt = $pdo->prepare("SELECT FirstName FROM staff WHERE Email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
            }

            // Always show success (prevents email enumeration)
            if ($user) {
                // Delete any existing reset token for this email
                $pdo->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);

                $token   = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

                $pdo->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)")
                    ->execute([$email, $token, $expires]);

                sendPasswordResetEmail($email, $user['FirstName'], $token);
            }

            header("Location: Login&Signup.php?fp_sent=1");
            exit;

        } catch (PDOException $e) {
            die("Database error: " . $e->getMessage());
        }

    /* ── LOGIN ── */
    } elseif ($action === "login") {
        $email      = trim($_POST["email"] ?? '');
        $password   = trim($_POST["pass"]  ?? '');
        $rememberMe = !empty($_POST['remember_me']);

        if (empty($email) || empty($password)) {
            echo "<script>alert('Email and password are required.'); window.history.back();</script>";
            exit;
        }

        try {
            $stmt = $pdo->prepare("SELECT CustomerID AS id, Password, FirstName, 'customer' AS role FROM customer WHERE Email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                $stmt = $pdo->prepare("SELECT StaffID AS id, Password, FirstName, 'staff' AS role FROM staff WHERE Email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
            }

            if ($user) {
                $passwordOk = password_verify($password, $user["Password"]) || ($password === $user["Password"]);

                if ($passwordOk) {
                    if ($user['role'] === 'customer') {
                        $_SESSION["CustomerID"] = $user["id"];
                        $_SESSION["FirstName"]  = $user["FirstName"];
                        $_SESSION["Email"]      = $email;
                        $_SESSION["role"]       = "customer";
                        if ($rememberMe) {
                            $remToken = bin2hex(random_bytes(32));
                            $remExp   = date('Y-m-d H:i:s', strtotime('+30 days'));
                            $pdo->prepare("DELETE FROM remember_tokens WHERE user_id = ? AND user_role = 'customer'")->execute([$user['id']]);
                            $pdo->prepare("INSERT INTO remember_tokens (user_id, user_role, token, expires_at) VALUES (?, 'customer', ?, ?)")
                                ->execute([$user['id'], $remToken, $remExp]);
                            setcookie('remember_token', $remToken, time() + 60 * 60 * 24 * 30, '/', '', true, true);
                        }

                        // ── Merge guest cart into DB ──────────────────────────
                        if (!empty($_SESSION['guest_cart']) && is_array($_SESSION['guest_cart'])) {
                            foreach ($_SESSION['guest_cart'] as $item) {
                                $pname = $item['product_name'];
                                $qty   = (int)$item['quantity'];
                                // Check if this product already exists in the customer's DB cart
                                $existing = $pdo->prepare(
                                    "SELECT CartID, Quantity FROM cart WHERE CustomerID = ? AND ProductName = ? LIMIT 1"
                                );
                                $existing->execute([$user['id'], $pname]);
                                $row = $existing->fetch(PDO::FETCH_ASSOC);
                                if ($row) {
                                    // Update quantity
                                    $pdo->prepare("UPDATE cart SET Quantity = Quantity + ? WHERE CartID = ?")
                                        ->execute([$qty, (int)$row['CartID']]);
                                } else {
                                    // Insert new row
                                    $pdo->prepare(
                                        "INSERT INTO cart (CustomerID, ProductName, Quantity) VALUES (?, ?, ?)"
                                    )->execute([$user['id'], $pname, $qty]);
                                }
                            }
                            unset($_SESSION['guest_cart']);
                        }

                        // ── Redirect: back to cart if coming from checkout ────
                        $redirectTo = $_SESSION['redirect_after_login'] ?? 'index.php';
                        unset($_SESSION['redirect_after_login']);
                        header("Location: " . $redirectTo);
                    } else {
                        $_SESSION["StaffID"]    = $user["id"];
                        $_SESSION["FirstName"]  = $user["FirstName"];
                        $_SESSION["Email"]      = $email;
                        $_SESSION["role"]       = "admin";
                        if ($rememberMe) {
                            $remToken = bin2hex(random_bytes(32));
                            $remExp   = date('Y-m-d H:i:s', strtotime('+30 days'));
                            $pdo->prepare("DELETE FROM remember_tokens WHERE user_id = ? AND user_role = 'admin'")->execute([$user['id']]);
                            $pdo->prepare("INSERT INTO remember_tokens (user_id, user_role, token, expires_at) VALUES (?, 'admin', ?, ?)")
                                ->execute([$user['id'], $remToken, $remExp]);
                            setcookie('remember_token', $remToken, time() + 60 * 60 * 24 * 30, '/', '', true, true);
                        }
                        header("Location: index.php");
                    }
                    exit;
                } else {
                    // Check if email is pending verification
                    $pendingCheck = $pdo->prepare("SELECT COUNT(*) FROM pending_customers WHERE email = ? AND expires_at > NOW()");
                    $pendingCheck->execute([$email]);
                    if ($pendingCheck->fetchColumn() > 0) {
                        echo "<script>alert('Please verify your email address first. Check your inbox for the verification link.'); window.history.back();</script>";
                    } else {
                        echo "<script>alert('Invalid email or password.'); window.history.back();</script>";
                    }
                    exit;
                }
            } else {
                // Check if email is unverified
                $pendingCheck = $pdo->prepare("SELECT COUNT(*) FROM pending_customers WHERE email = ? AND expires_at > NOW()");
                $pendingCheck->execute([$email]);
                if ($pendingCheck->fetchColumn() > 0) {
                    header("Location: Login&Signup.php?pending=1&email=" . urlencode($email));
                } else {
                    echo "<script>alert('Invalid email or password.'); window.history.back();</script>";
                }
                exit;
            }
        } catch (PDOException $e) {
            die("Database error: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log In / Sign Up — Zabco Tile Depot</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
    /* ============================================================
       CSS VARIABLES & RESET
       ============================================================ */
    :root {
        --bg:           #111214;
        --surface:      #1a1b1e;
        --surface-2:    #222428;
        --surface-3:    #2a2b30;
        --accent:       #ed8d1b;
        --accent-dark:  #c97415;
        --accent-glow:  rgba(237,141,27,0.18);
        --text:         #f0f0f0;
        --text-muted:   #999;
        --text-dim:     #666;
        --border:       #2e2f34;
        --radius:       12px;
        --radius-sm:    8px;
        --shadow:       0 24px 60px rgba(0,0,0,0.7);
        --transition:   0.25s ease;
        --panel-w:      420px;
    }

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    html, body {
        height: 100%;
        font-family: 'Inter', system-ui, -apple-system, sans-serif;
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
    }

    body {
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        background-image:
            radial-gradient(ellipse 80% 80% at 20% 20%, rgba(237,141,27,0.06) 0%, transparent 60%),
            radial-gradient(ellipse 60% 60% at 80% 80%, rgba(237,141,27,0.04) 0%, transparent 60%);
        padding: 24px 16px;
        overflow-x: hidden;
    }

    /* ============================================================
       NOTIFICATION BANNER
       ============================================================ */
    .notif-banner {
        position: fixed;
        top: 20px;
        left: 50%;
        transform: translateX(-50%);
        z-index: 9999;
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 13px 20px;
        border-radius: 10px;
        font-size: 14px;
        font-weight: 600;
        white-space: nowrap;
        box-shadow: 0 8px 32px rgba(0,0,0,0.5);
        animation: notifIn 0.35s cubic-bezier(0.34,1.56,0.64,1) both,
                   notifOut 0.4s ease 5s forwards;
    }
    .notif-banner.success {
        background: #162216;
        border: 1px solid #2d6a2d;
        color: #7dd87d;
    }
    .notif-banner.error {
        background: #221616;
        border: 1px solid #6a2d2d;
        color: #d87d7d;
    }
    .notif-banner .notif-icon {
        width: 20px; height: 20px;
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-size: 11px; font-weight: 900; flex-shrink: 0;
    }
    .notif-banner.success .notif-icon { background: #2d6a2d; color: #7dd87d; }
    .notif-banner.error   .notif-icon { background: #6a2d2d; color: #d87d7d; }
    @keyframes notifIn  { from { top: -60px; opacity: 0; } to { top: 20px; opacity: 1; } }
    @keyframes notifOut { from { opacity: 1; } to { opacity: 0; pointer-events: none; } }

    /* ============================================================
       BACK BUTTON
       ============================================================ */
    .back-btn {
        position: fixed;
        top: 20px;
        left: 20px;
        z-index: 200;
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 16px 8px 10px;
        background: rgba(255,255,255,0.05);
        border: 1px solid var(--border);
        border-radius: 50px;
        color: var(--text-muted);
        font-size: 13px;
        font-weight: 600;
        text-decoration: none;
        transition: background var(--transition), color var(--transition), border-color var(--transition), transform var(--transition);
        backdrop-filter: blur(8px);
    }
    .back-btn:hover {
        background: rgba(237,141,27,0.12);
        border-color: var(--accent);
        color: var(--accent);
        transform: translateX(-2px);
    }
    .back-btn svg {
        width: 16px; height: 16px;
        stroke: currentColor;
        fill: none;
        stroke-width: 2.5;
        stroke-linecap: round;
        stroke-linejoin: round;
        flex-shrink: 0;
    }

    /* ============================================================
       MAIN CARD CONTAINER
       ============================================================ */
    .auth-card {
        position: relative;
        display: flex;
        width: 100%;
        max-width: 880px;
        min-height: 580px;
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 20px;
        box-shadow: var(--shadow);
        overflow: hidden;
    }

    /* ============================================================
       FORM PANELS
       ============================================================ */
    .panel {
        position: absolute;
        top: 0;
        width: 50%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 48px 40px;
        transition: all 0.65s cubic-bezier(0.68, -0.05, 0.27, 1.05);
        overflow-y: auto;
    }

    .panel-login {
        left: 0;
        z-index: 2;
        opacity: 1;
    }

    /* ── Sign-up panel: tighter spacing so all 6 fields fit ── */
    .panel-signup {
        padding: 32px 40px;
    }
    .panel-signup .form-inner .subtitle {
        margin-bottom: 14px;
    }
    .panel-signup .input-group {
        margin-bottom: 10px;
    }
    .panel-signup .input-group input {
        height: 42px;
    }
    .panel-signup .btn-primary {
        height: 42px;
        margin-top: 4px;
    }
    .panel-signup .divider {
        margin: 12px 0;
    }
    .panel-signup .btn-ghost {
        height: 40px;
    }

    .panel-signup {
        right: 0;
        z-index: 1;
        opacity: 0;
        pointer-events: none;
    }

    /* Active state — card slides to show signup */
    .auth-card.show-signup .panel-login {
        opacity: 0;
        pointer-events: none;
        transform: translateX(-100%);
    }
    .auth-card.show-signup .panel-signup {
        opacity: 1;
        pointer-events: auto;
        transform: translateX(0);
        z-index: 2;
    }

    /* ── Form Inner ── */
    .form-inner {
        width: 100%;
        max-width: 320px;
    }

    /* ── Form heading ── */
    .form-inner h1 {
        font-size: clamp(22px, 3vw, 28px);
        font-weight: 800;
        color: var(--text);
        margin-bottom: 8px;
        letter-spacing: -0.5px;
    }

    .form-inner .subtitle {
        font-size: 13px;
        color: var(--text-muted);
        margin-bottom: 20px;
        line-height: 1.5;
    }

    /* ── Input groups ── */
    .input-group {
        position: relative;
        width: 100%;
        margin-bottom: 14px;
    }

    .input-group input {
        width: 100%;
        height: 46px;
        padding: 0 16px;
        background: var(--surface-3);
        border: 1.5px solid var(--border);
        border-radius: var(--radius-sm);
        color: var(--text);
        font-size: 14px;
        font-weight: 500;
        font-family: inherit;
        outline: none;
        transition: border-color var(--transition), background var(--transition), box-shadow var(--transition);
    }

    .input-group input::placeholder { color: var(--text-dim); }

    .input-group input:focus {
        border-color: var(--accent);
        background: var(--surface-2);
        box-shadow: 0 0 0 3px var(--accent-glow);
    }

    /* Password wrapper */
    .input-group.has-toggle input { padding-right: 46px; }

    .pass-toggle {
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        background: transparent;
        border: none;
        cursor: pointer;
        color: var(--text-dim);
        padding: 4px;
        transition: color var(--transition);
        display: flex;
        align-items: center;
        justify-content: center;
        -webkit-tap-highlight-color: transparent;
    }
    .pass-toggle svg {
        width: 18px;
        height: 18px;
        stroke: currentColor;
        fill: none;
        stroke-width: 2;
        stroke-linecap: round;
        stroke-linejoin: round;
        pointer-events: none;
    }
    /* Default state: password hidden → show "eye" (click to reveal) */
    .pass-toggle .icon-eye-off { display: none; }
    .pass-toggle .icon-eye     { display: block; }
    /* Active state: password revealed → show "eye-off" (click to hide) */
    .pass-toggle.active .icon-eye     { display: none; }
    .pass-toggle.active .icon-eye-off { display: block; }
    .pass-toggle:hover  { color: var(--text-muted); }
    .pass-toggle.active { color: var(--accent); }

    /* ── Primary button ── */
    .btn-primary {
        width: 100%;
        height: 46px;
        background: var(--accent);
        border: none;
        border-radius: var(--radius-sm);
        color: #fff;
        font-size: 14px;
        font-weight: 700;
        font-family: inherit;
        letter-spacing: 0.5px;
        cursor: pointer;
        margin-top: 6px;
        transition: background var(--transition), transform 0.1s ease, box-shadow var(--transition);
        box-shadow: 0 4px 16px rgba(237,141,27,0.3);
    }
    .btn-primary:hover {
        background: var(--accent-dark);
        box-shadow: 0 6px 24px rgba(237,141,27,0.4);
    }
    .btn-primary:active { transform: scale(0.97); }

    /* ── Divider ── */
    .divider {
        display: flex;
        align-items: center;
        gap: 12px;
        margin: 20px 0;
    }
    .divider::before,
    .divider::after {
        content: '';
        flex: 1;
        height: 1px;
        background: var(--border);
    }
    .divider span {
        font-size: 12px;
        color: var(--text-dim);
        font-weight: 500;
        white-space: nowrap;
    }

    /* ── Switch link ── */
    .switch-wrap {
        text-align: center;
    }
    .switch-wrap p {
        font-size: 13px;
        color: var(--text-muted);
        margin-bottom: 10px;
        line-height: 1.4;
    }
    .btn-ghost {
        width: 100%;
        height: 44px;
        background: transparent;
        border: 1.5px solid var(--border);
        border-radius: var(--radius-sm);
        color: var(--text-muted);
        font-size: 14px;
        font-weight: 600;
        font-family: inherit;
        cursor: pointer;
        transition: border-color var(--transition), color var(--transition), background var(--transition);
    }
    .btn-ghost:hover {
        border-color: var(--accent);
        color: var(--accent);
        background: var(--accent-glow);
    }
    .btn-ghost:active { transform: scale(0.97); }

    /* ============================================================
       CHECK-EMAIL STATE (shown after successful signup submit)
       ============================================================ */
    #checkEmailState { display: none; }

    .verify-envelope {
        width: 56px;
        height: 56px;
        background: var(--accent-glow);
        border: 1.5px solid var(--accent);
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 20px;
    }
    .verify-envelope svg {
        width: 28px; height: 28px;
        stroke: var(--accent);
        fill: none;
        stroke-width: 1.8;
        stroke-linecap: round;
        stroke-linejoin: round;
    }

    #checkEmailState h1 { margin-bottom: 8px; }

    .verify-email-highlight {
        color: var(--accent);
        font-weight: 700;
        word-break: break-all;
    }

    .verify-hint {
        display: flex;
        align-items: flex-start;
        gap: 8px;
        background: var(--surface-3);
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
        padding: 12px 14px;
        margin: 16px 0 0;
    }
    .verify-hint svg {
        width: 15px; height: 15px;
        stroke: var(--text-dim);
        fill: none;
        stroke-width: 2;
        flex-shrink: 0;
        margin-top: 1px;
    }
    .verify-hint p {
        font-size: 12px;
        color: var(--text-dim);
        line-height: 1.5;
        margin: 0;
    }

    .resend-btn {
        width: 100%;
        height: 42px;
        background: transparent;
        border: 1.5px solid var(--border);
        border-radius: var(--radius-sm);
        color: var(--text-muted);
        font-size: 13px;
        font-weight: 600;
        font-family: inherit;
        cursor: pointer;
        margin-top: 16px;
        transition: border-color var(--transition), color var(--transition), background var(--transition);
    }
    .resend-btn:hover {
        border-color: var(--accent);
        color: var(--accent);
        background: var(--accent-glow);
    }
    .resend-btn:active { transform: scale(0.97); }
    .resend-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        pointer-events: none;
    }

    /* ============================================================
       OVERLAY PANEL (logo / branding side)
       ============================================================ */
    .overlay-panel {
        position: absolute;
        top: 0;
        right: 0;
        width: 50%;
        height: 100%;
        z-index: 3;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-direction: column;
        gap: 20px;
        padding: 40px;
        background: linear-gradient(135deg, var(--accent) 0%, var(--accent-dark) 55%, #a05a0a 100%);
        transition: transform 0.65s cubic-bezier(0.68, -0.05, 0.27, 1.05);
        pointer-events: none;
    }

    .overlay-panel::before {
        content: '';
        position: absolute;
        width: 350px; height: 350px;
        border-radius: 50%;
        border: 1px solid rgba(255,255,255,0.12);
        top: 50%; left: 50%;
        transform: translate(-50%, -50%);
        pointer-events: none;
    }
    .overlay-panel::after {
        content: '';
        position: absolute;
        width: 220px; height: 220px;
        border-radius: 50%;
        border: 1px solid rgba(255,255,255,0.18);
        top: 50%; left: 50%;
        transform: translate(-50%, -50%);
        pointer-events: none;
    }

    .auth-card.show-signup .overlay-panel {
        transform: translateX(-100%);
    }

    .overlay-logo {
        position: relative;
        z-index: 1;
        width: 160px;
        height: auto;
        filter: drop-shadow(0 4px 16px rgba(0,0,0,0.25));
    }

    .overlay-tagline {
        position: relative;
        z-index: 1;
        font-size: 13px;
        font-weight: 600;
        color: rgba(255,255,255,0.85);
        text-align: center;
        letter-spacing: 0.3px;
        line-height: 1.5;
        max-width: 200px;
    }

    /* ============================================================
       RESPONSIVE — Laptop/Desktop (1024px): stack vertically
       ============================================================ */
    @media (width: 1024px) {
        .back-btn{
            top: 60px;
            left: 90px;
            color: #ffffff;
        }
    }

    /* ============================================================
       RESPONSIVE — Laptop/Desktop (768px): stack vertically
       ============================================================ */
    @media (width: 768px) {
        .back-btn{
            top: 60px;
            left: 30px;
            color: #ffffff;
        }
    }

    /* ============================================================
       RESPONSIVE — Tablet (≤ 760px): stack vertically
       ============================================================ */
    @media (max-width: 760px) {
        body { padding: 0; align-items: stretch; }

        .auth-card {
            flex-direction: column;
            border-radius: 0;
            border: none;
            min-height: 100vh;
            max-width: 100%;
            box-shadow: none;
        }

        .overlay-panel { display: none; }

        .panel {
            position: relative;
            width: 100%;
            height: auto;
            min-height: unset;
            padding: 40px 24px 32px;
            transform: none !important;
            transition: opacity 0.3s ease;
        }

        .panel-signup {
            display: none;
        }

        .auth-card.show-signup .panel-login {
            display: none;
            opacity: 0;
        }
        .auth-card.show-signup .panel-signup {
            display: flex;
            opacity: 1;
        }

        .mobile-brand {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 28px 20px 20px;
            background: linear-gradient(135deg, var(--accent) 0%, var(--accent-dark) 100%);
        }
        .mobile-brand img { width: 130px; height: auto; display: block; }

        .form-inner { max-width: 400px; }

        .back-btn { top: 14px; left: 14px; padding: 7px 14px 7px 9px; }
    }

    /* ============================================================
       RESPONSIVE — Small mobile (≤ 480px)
       ============================================================ */
    @media (max-width: 480px) {
        .panel { padding: 55px 18px 28px; }
        .form-inner h1 { font-size: 22px; }
        .back-btn { font-size: 12px; padding: 6px 12px 6px 8px; }
        .btn-primary, .btn-ghost { height: 44px; font-size: 13px; }
        .input-group input { height: 44px; font-size: 13px; }
        .mobile-brand { padding: 22px 16px 18px; }
        .mobile-brand img { width: 110px; }
    }

    /* ============================================================
       MOBILE BRAND — hidden on desktop by default
       ============================================================ */
    .mobile-brand { display: none; }

    /* ============================================================
       REMEMBER ME & FORGOT PASSWORD ROW
       ============================================================ */
    .login-meta {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin: -4px 0 14px;
    }

    .remember-label {
        display: flex;
        align-items: center;
        gap: 7px;
        cursor: pointer;
        font-size: 13px;
        color: var(--text-muted);
        user-select: none;
    }
    .remember-label input[type="checkbox"] {
        appearance: none;
        -webkit-appearance: none;
        width: 16px;
        height: 16px;
        border: 1.5px solid var(--border);
        border-radius: 4px;
        background: var(--surface-3);
        cursor: pointer;
        flex-shrink: 0;
        position: relative;
        transition: border-color var(--transition), background var(--transition);
    }
    .remember-label input[type="checkbox"]:checked {
        background: var(--accent);
        border-color: var(--accent);
    }
    .remember-label input[type="checkbox"]:checked::after {
        content: '';
        position: absolute;
        left: 4px;
        top: 1px;
        width: 5px;
        height: 9px;
        border: 2px solid #fff;
        border-top: none;
        border-left: none;
        transform: rotate(45deg);
    }

    .forgot-link {
        font-size: 13px;
        color: var(--text-dim);
        text-decoration: none;
        cursor: pointer;
        background: none;
        border: none;
        font-family: inherit;
        padding: 0;
        transition: color var(--transition);
    }
    .forgot-link:hover { color: var(--accent); }

    /* ============================================================
       FORGOT PASSWORD PANEL
       ============================================================ */
    #forgotFormWrap { display: none; }

    .fp-icon {
        width: 56px;
        height: 56px;
        background: var(--accent-glow);
        border: 1.5px solid var(--accent);
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 20px;
    }
    .fp-icon svg {
        width: 26px; height: 26px;
        stroke: var(--accent);
        fill: none;
        stroke-width: 1.8;
        stroke-linecap: round;
        stroke-linejoin: round;
    }
    </style>
</head>
<body>

    <!-- Back Button -->
    <a href="index.php" class="back-btn" aria-label="Back to home">
        <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
        Back
    </a>

    <!-- Mobile brand strip (only visible on small screens) -->
    <div class="mobile-brand">
        <img src="Logo.png" alt="Zabco Tile Depot">
    </div>

    <!-- Auth Card -->
    <div class="auth-card" id="authCard">

        <div class="panel panel-login">
            <div class="form-inner">

                <!-- Normal login form -->
                <div id="loginFormWrap">
                <h1>Welcome back</h1>
                <p class="subtitle">Log in to your Zabco account to continue.</p>

                <form action="" method="POST" autocomplete="on">
                    <input type="hidden" name="action" value="login">

                    <div class="input-group">
                        <input type="email" name="email" placeholder="Email address" required autocomplete="email">
                    </div>

                    <div class="input-group has-toggle">
                        <input type="password" name="pass" id="loginPass" placeholder="Password" required autocomplete="current-password">
                        <button type="button" class="pass-toggle" onclick="togglePass('loginPass', this)" aria-label="Toggle password"><svg class="icon-eye" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg><svg class="icon-eye-off" viewBox="0 0 24 24"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg></button>
                    </div>

                    <div class="login-meta">
                        <label class="remember-label">
                            <input type="checkbox" name="remember_me" value="1">
                            Remember me
                        </label>
                        <button type="button" class="forgot-link" id="goForgot">Forgot password?</button>
                    </div>

                    <button type="submit" class="btn-primary">Log In</button>
                </form>

                <div class="divider"><span>Don't have an account?</span></div>

                <div class="switch-wrap">
                    <button type="button" class="btn-ghost" id="goSignup">Create an account</button>
                </div>
                </div><!-- /#loginFormWrap -->

                <!-- Forgot password form -->
                <div id="forgotFormWrap">
                    <div class="fp-icon">
                        <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    </div>
                    <h1>Forgot password?</h1>
                    <p class="subtitle">Enter your email and we'll send you a link to reset your password.</p>

                    <form action="" method="POST" autocomplete="on">
                        <input type="hidden" name="action" value="forgot_password">
                        <div class="input-group">
                            <input type="email" name="email" placeholder="Email address" required autocomplete="email">
                        </div>
                        <button type="submit" class="btn-primary">Send Reset Link</button>
                    </form>

                    <div class="divider"><span>Remembered it?</span></div>

                    <div class="switch-wrap">
                        <button type="button" class="btn-ghost" id="backToLogin">Back to Log In</button>
                    </div>
                </div><!-- /#forgotFormWrap -->

            </div>
        </div>

        <!-- ── Sign Up Panel ── -->
        <div class="panel panel-signup">
            <div class="form-inner">

                <!-- Normal sign-up form -->
                <div id="signupFormWrap">
                    <h1>Create account</h1>
                    <p class="subtitle">Join Zabco Tile Depot and start building.</p>

                    <form action="" method="POST" autocomplete="on">
                        <input type="hidden" name="action" value="signup">

                        <div class="input-group">
                            <input type="email" name="email" placeholder="Email address" required autocomplete="email">
                        </div>

                        <div class="input-group">
                            <input type="text" name="fname" placeholder="First name" required autocomplete="given-name">
                        </div>

                        <div class="input-group">
                            <input type="text" name="lname" placeholder="Last name" required autocomplete="family-name">
                        </div>

                        <div class="input-group">
                            <input type="text" name="add" placeholder="Address" required autocomplete="street-address">
                        </div>

                        <div class="input-group has-toggle">
                            <input type="password" name="pass" id="signupPass" placeholder="Password (min. 8 chars)" required autocomplete="new-password" minlength="8">
                            <button type="button" class="pass-toggle" onclick="togglePass('signupPass', this)" aria-label="Toggle password"><svg class="icon-eye" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg><svg class="icon-eye-off" viewBox="0 0 24 24"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg></button>
                        </div>

                        <div class="input-group">
                            <input type="tel" name="phone" placeholder="Phone number" required autocomplete="tel">
                        </div>

                        <button type="submit" class="btn-primary">Create Account</button>
                    </form>

                    <div class="divider"><span>Already have an account?</span></div>

                    <div class="switch-wrap">
                        <button type="button" class="btn-ghost" id="goLogin">Log in instead</button>
                    </div>
                </div>

                <!-- Check-email state (shown when ?pending=1) -->
                <div id="checkEmailState">
                    <div class="verify-envelope">
                        <svg viewBox="0 0 24 24">
                            <rect x="2" y="4" width="20" height="16" rx="2"/>
                            <polyline points="2,4 12,13 22,4"/>
                        </svg>
                    </div>

                    <h1>Check your email</h1>
                    <p class="subtitle">
                        We sent a verification link to<br>
                        <span class="verify-email-highlight" id="pendingEmailDisplay"></span>
                    </p>

                    <div class="verify-hint">
                        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        <p>The link expires in <strong>24 hours</strong>. Check your spam folder if you don't see it.</p>
                    </div>

                    <form action="" method="POST" id="resendForm">
                        <input type="hidden" name="action" value="resend">
                        <input type="hidden" name="email" id="resendEmailInput">
                        <button type="submit" class="resend-btn" id="resendBtn">Resend verification email</button>
                    </form>

                    <div class="divider"><span>Already verified?</span></div>

                    <div class="switch-wrap">
                        <button type="button" class="btn-ghost" id="goLoginFromVerify">Log in instead</button>
                    </div>
                </div>

            </div>
        </div>

        <!-- ── Overlay / Branding Panel ── -->
        <div class="overlay-panel" aria-hidden="true">
            <img src="Logo.png" alt="Zabco Tile Depot" class="overlay-logo">
            <p class="overlay-tagline">Your trusted tile supplier in the Metro</p>
        </div>

    </div><!-- /.auth-card -->

<script>
(function () {
    const card     = document.getElementById('authCard');
    const goSignup = document.getElementById('goSignup');
    const goLogin  = document.getElementById('goLogin');
    const goLoginV = document.getElementById('goLoginFromVerify');
    const goForgot = document.getElementById('goForgot');
    const backToLogin = document.getElementById('backToLogin');

    // Panel helpers
    function showLogin() {
        card.classList.remove('show-signup');
        document.getElementById('loginFormWrap').style.display = '';
        document.getElementById('forgotFormWrap').style.display = 'none';
    }
    function showForgot() {
        card.classList.remove('show-signup');
        document.getElementById('loginFormWrap').style.display = 'none';
        document.getElementById('forgotFormWrap').style.display = 'block';
    }

    if (goSignup)   goSignup.addEventListener('click', () => { showLogin(); card.classList.add('show-signup'); });
    if (goLogin)    goLogin.addEventListener('click',  showLogin);
    if (goLoginV)   goLoginV.addEventListener('click', showLogin);
    if (goForgot)   goForgot.addEventListener('click', showForgot);
    if (backToLogin) backToLogin.addEventListener('click', showLogin);

    // Password visibility toggle
    window.togglePass = function (id, btn) {
        const input = document.getElementById(id);
        if (!input) return;
        const isHidden = input.type === 'password';
        input.type = isHidden ? 'text' : 'password';
        btn.classList.toggle('active', isHidden);
    };

    // Notification banner helper
    function showNotif(message, type) {
        const el = document.createElement('div');
        el.className = 'notif-banner ' + type;
        el.innerHTML = '<span class="notif-icon">' + (type === 'success' ? '✓' : '✕') + '</span>' + message;
        document.body.appendChild(el);
        setTimeout(() => el.remove(), 5500);
    }

    // Parse URL params and apply states
    const params = new URLSearchParams(window.location.search);

    // ?verified=1 — email confirmed
    if (params.get('verified') === '1') {
        showNotif('Email verified! You can now log in.', 'success');
    }

    // ?error=expired_token
    if (params.get('error') === 'expired_token') {
        showNotif('That link has expired. Please sign up again.', 'error');
        card.classList.add('show-signup');
    }

    // ?error=invalid_token
    if (params.get('error') === 'invalid_token') {
        showNotif('Invalid verification link.', 'error');
    }

    // ?error=no_pending
    if (params.get('error') === 'no_pending') {
        showNotif('No pending registration found. Have you already verified?', 'error');
    }

    // ?show=signup
    if (params.get('show') === 'signup') {
        card.classList.add('show-signup');
    }

    // ?fp_sent=1 — reset email sent
    if (params.get('fp_sent') === '1') {
        showNotif('Password reset link sent! Check your inbox.', 'success');
    }

    // ?fp_error=invalid
    if (params.get('fp_error') === 'invalid') {
        showForgot();
        showNotif('Please enter a valid email address.', 'error');
    }

    // ?pending=1 — show "check your email" state
    if (params.get('pending') === '1') {
        card.classList.add('show-signup');
        document.getElementById('signupFormWrap').style.display = 'none';
        document.getElementById('checkEmailState').style.display = 'block';

        const email = params.get('email') || '';
        document.getElementById('pendingEmailDisplay').textContent = email;
        document.getElementById('resendEmailInput').value = email;

        if (params.get('resent') === '1') {
            showNotif('Verification email resent! Check your inbox.', 'success');
        }

        // Cooldown on resend button to prevent spam
        const resendBtn = document.getElementById('resendBtn');
        if (resendBtn) {
            let cooldown = 60;
            resendBtn.disabled = true;
            resendBtn.textContent = 'Resend in ' + cooldown + 's';
            const timer = setInterval(() => {
                cooldown--;
                if (cooldown <= 0) {
                    clearInterval(timer);
                    resendBtn.disabled = false;
                    resendBtn.textContent = 'Resend verification email';
                } else {
                    resendBtn.textContent = 'Resend in ' + cooldown + 's';
                }
            }, 1000);
        }
    }

    // Clean URL params without reloading
    if (window.history && window.history.replaceState && params.toString()) {
        const clean = window.location.pathname + window.location.hash;
        window.history.replaceState({}, '', clean);
    }
})();
</script>

</body>
</html>