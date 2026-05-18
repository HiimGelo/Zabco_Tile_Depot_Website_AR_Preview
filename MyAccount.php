<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['CustomerID'])) {
    header("Location: Login&Signup.php");
    exit;
}

$customerId = (int) $_SESSION['CustomerID'];

$sql  = "SELECT CustomerID, FirstName, LastName, PhoneNumber, Email, Address FROM customer WHERE CustomerID = :id";
$stmt = $pdo->prepare($sql);
$stmt->execute([':id' => $customerId]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$customer) die("User not found.");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $firstName = trim($_POST["fname"]);
    $lastName  = trim($_POST["lname"]);
    $phone     = trim($_POST["phone"]);
    $email     = trim($_POST["email"]);
    $address   = trim($_POST["address"]);
    $password  = trim($_POST["pass"]);

    $updateSql  = "UPDATE customer SET FirstName=:fname, LastName=:lname, PhoneNumber=:phone, Email=:email, Address=:address WHERE CustomerID=:id";
    $updateStmt = $pdo->prepare($updateSql);
    $updateStmt->execute([':fname'=>$firstName,':lname'=>$lastName,':phone'=>$phone,':email'=>$email,':address'=>$address,':id'=>$customerId]);

    if (!empty($password)) {
        $hashedPass = password_hash($password, PASSWORD_DEFAULT);
        $passStmt   = $pdo->prepare("UPDATE customer SET Password=:pass WHERE CustomerID=:id");
        $passStmt->execute([':pass'=>$hashedPass,':id'=>$customerId]);
    }

    header("Location: MyAccount.php?success=1");
    exit;
}
require 'header.php';
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light" data-bss-forced-theme="light" style="background:#f0f0f0;">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Account</title>
    <link rel="icon" type="image/ico" href="Favicon.ico">
    <link rel="stylesheet" href="assets/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="MyAccountStyles.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">
    <style>
    /* ── Mobile improvements for My Account ─────────────────── */
    @media (max-width: 768px) {
        body { padding-top: 80px !important; }

        .container { padding: 0 12px; }
        .container1 { padding: 20px 0 40px; }

        /* Alert */
        .alert-success {
            font-size: 13px;
            padding: 11px 14px;
            border-radius: 10px;
            margin-bottom: 18px;
        }

        /* Header */
        .details-wrapper h2 { font-size: 22px; padding-left:15px; }
        .title h5 { font-size: 13px; padding-left:15px; }

        /* Stack two-column fields */
        li.two-up {
            display: flex !important;
            flex-direction: column !important;
            gap: 14px !important;
        }
        li.two-up .field { width: 100% !important; }

        /* Make inputs taller for touch */
        .field input,
        .field textarea {
            height: 48px !important;
            font-size: 15px !important;
            padding: 0 14px !important;
        }

        /* Save button full width */
        .save-button {
            width: 100% !important;
            padding: 14px !important;
            font-size: 14px !important;
            letter-spacing: 0.5px;
        }

        .info-details { padding: 0 !important; }
        .info-container { padding: 16px !important; }
    }

    @media (max-width: 480px) {
        body { padding-top: 70px !important; }
        .container { padding: 0 8px; }
        .details-wrapper h2 { font-size: 19px; }
    }

    @media (max-width: 360px) {
        body { padding-top: 64px !important; }
    }
    </style>
</head>

<body style="background:#f0f0f0;">

<div class="container">
    <div class="container1">

        <?php if (isset($_GET['success'])): ?>
        <div class="alert-success">✓ Your details have been updated successfully.</div>
        <?php endif; ?>

        <div class="details-wrapper">
            <h2>My Details</h2>
        </div>
        <div class="title">
            <h5>Personal Information</h5>
        </div>

        <div class="info-container">
            <form method="POST">
                <ul class="info-details">

                    <li class="two-up">
                        <div class="field">
                            <label for="fname">First Name</label>
                            <input id="fname" name="fname" type="text" value="<?= htmlspecialchars($customer['FirstName']) ?>" placeholder="First name">
                        </div>
                        <div class="field">
                            <label for="lname">Last Name</label>
                            <input id="lname" name="lname" type="text" value="<?= htmlspecialchars($customer['LastName']) ?>" placeholder="Last name">
                        </div>
                    </li>

                    <li class="one-up">
                        <div class="field">
                            <label for="phone">Phone Number</label>
                            <input id="phone" name="phone" type="tel" value="<?= htmlspecialchars($customer['PhoneNumber']) ?>" placeholder="09XX XXX XXXX">
                        </div>
                    </li>

                    <li class="one-up">
                        <div class="field">
                            <label for="email">Email Address</label>
                            <input id="email" name="email" type="text" value="<?= htmlspecialchars($customer['Email']) ?>" placeholder="you@example.com">
                        </div>
                    </li>

                    <li class="one-up">
                        <div class="field">
                            <label for="address">Address</label>
                            <input id="address" name="address" type="text" value="<?= htmlspecialchars($customer['Address'] ?? '') ?>" placeholder="Full address">
                        </div>
                    </li>

                    <li class="one-up">
                        <div class="field">
                            <label for="pass">Password</label>
                            <input id="pass" name="pass" type="password" placeholder="Enter new password (leave blank to keep current)">
                        </div>
                    </li>

                </ul>

                <div class="save">
                    <button type="submit" class="save-button">SAVE CHANGES</button>
                </div>
            </form>
        </div>

    </div>
</div>

</body>

<script src="assets/bootstrap/js/bootstrap.min.js"></script>

<?php require 'footer.php'; ?>
</html>