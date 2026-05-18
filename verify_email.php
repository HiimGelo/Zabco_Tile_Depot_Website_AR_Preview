<?php
/*
 * verify_email.php
 * Handles the one-click email verification link sent during signup.
 *
 * Flow:
 *   1. User clicks link: verify_email.php?token=<hex>
 *   2. Token is looked up in pending_customers (must not be expired).
 *   3. A new row is inserted into customer.
 *   4. The pending record is deleted.
 *   5. User is redirected to the login page with ?verified=1.
 */

session_start();
include 'db_connect.php';

$token = trim($_GET['token'] ?? '');

/* ── Basic sanity check ─────────────────────────────────────── */
if (empty($token) || !ctype_xdigit($token) || strlen($token) !== 64) {
    header("Location: Login&Signup.php?error=invalid_token");
    exit;
}

try {
    /* ── Look up the token (must exist and not be expired) ──── */
    $stmt = $pdo->prepare(
        "SELECT * FROM pending_customers
         WHERE token = ? AND expires_at > NOW()
         LIMIT 1"
    );
    $stmt->execute([$token]);
    $pending = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pending) {
        /*
         * Token not found or expired.
         * Check if it was already used (email already in customer).
         */
        $used = $pdo->prepare(
            "SELECT COUNT(*) FROM pending_customers WHERE token = ?"
        );
        $used->execute([$token]);

        if ($used->fetchColumn() === 0) {
            /*
             * Token exists nowhere — either already verified or
             * was manually purged. Redirect to login; user may
             * already have an account.
             */
            header("Location: Login&Signup.php?verified=1");
        } else {
            // Token exists but expired
            header("Location: Login&Signup.php?error=expired_token");
        }
        exit;
    }

    /* ── Guard: email already registered (edge case) ────────── */
    $exists = $pdo->prepare("SELECT COUNT(*) FROM customer WHERE Email = ?");
    $exists->execute([$pending['email']]);
    if ($exists->fetchColumn() > 0) {
        // Already verified by another request — clean up and redirect
        $pdo->prepare("DELETE FROM pending_customers WHERE token = ?")->execute([$token]);
        header("Location: Login&Signup.php?verified=1");
        exit;
    }

    /* ── Insert verified customer ───────────────────────────── */
    $pdo->beginTransaction();

    $insert = $pdo->prepare(
        "INSERT INTO customer
             (Email, FirstName, LastName, Address, Password, PhoneNumber)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    $insert->execute([
        $pending['email'],
        $pending['first_name'],
        $pending['last_name'],
        $pending['address'],
        $pending['password'],   // already bcrypt-hashed
        $pending['phone'],
    ]);

    /* ── Remove the pending record ──────────────────────────── */
    $pdo->prepare("DELETE FROM pending_customers WHERE token = ?")->execute([$token]);

    $pdo->commit();

    /* ── Redirect to login with success flag ────────────────── */
    header("Location: Login&Signup.php?verified=1");
    exit;

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // Log the error server-side and show a generic message
    error_log("verify_email.php PDO error: " . $e->getMessage());
    die("Something went wrong while verifying your email. Please try again later.");
}
