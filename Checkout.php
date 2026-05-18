<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include 'db_connect.php';

// ── Auth check ────────────────────────────────────────────────
if (!isset($_SESSION['CustomerID'])) {
    $_SESSION['flash_error'] = 'Please log in to continue.';
    header('Location: Login&Signup.php');
    exit;
}

// ── Must have items ───────────────────────────────────────────
$isCartCheckout = isset($_SESSION['cart_checkout']) && !empty($_SESSION['cart_checkout']);
$isBuyNow       = isset($_SESSION['buy_now']);

if (!$isCartCheckout && !$isBuyNow) {
    $_SESSION['flash_error'] = 'Session expired or nothing to check out.';
    header('Location: Products.php');
    exit;
}

// ── Back to Cart: restore deleted items and cancel checkout ─────
if (isset($_GET['cancel_checkout'])) {
    // Cart.php deletes items from the DB before passing them via session.
    // Re-insert them so the user sees their cart unchanged.
    if (!empty($_SESSION['cart_checkout'])) {
        $customerID = $_SESSION['CustomerID'];
        foreach ($_SESSION['cart_checkout'] as $item) {
            $pdo->prepare(
                "INSERT INTO cart (CustomerID, ProductName, Quantity)
                 VALUES (?, ?, ?)"
            )->execute([
                $customerID,
                $item['productName'],
                $item['quantity'],
            ]);
        }
    }
    unset($_SESSION['cart_checkout'], $_SESSION['buy_now']);
    header('Location: Cart.php');
    exit;
}

// ── Build checkout items ──────────────────────────────────────
$checkoutItems = [];

if ($isCartCheckout) {
    foreach ($_SESSION['cart_checkout'] as $item) {
        $productID = $item['productID'] ?? null;
        $price     = $item['amount']    ?? $item['price'] ?? 0;
        if (!$productID) {
            foreach (['productsmedian','productssophisticated','productsluxurious'] as $table) {
                $s = $pdo->prepare("SELECT ProductID, Price FROM $table WHERE ProductName = ?");
                $s->execute([$item['productName']]);
                $row = $s->fetch(PDO::FETCH_ASSOC);
                if ($row) { $productID = $row['ProductID']; $price = $row['Price']; break; }
            }
        }
        $checkoutItems[] = [
            'productID'   => $productID,
            'productName' => $item['productName'],
            'quantity'    => (int)$item['quantity'],
            'price'       => (float)$price,
            'total'       => (float)$price * (int)$item['quantity'],
        ];
    }
} else {
    $bn         = $_SESSION['buy_now'];
    $productID  = $bn['productID'];
    $price      = $bn['price'];
    $productRow = null;
    foreach (['productsmedian','productssophisticated','productsluxurious'] as $table) {
        $s = $pdo->prepare("SELECT ProductName, Price FROM $table WHERE ProductID = ?");
        $s->execute([$productID]);
        $productRow = $s->fetch(PDO::FETCH_ASSOC);
        if ($productRow) { $price = $productRow['Price']; break; }
    }
    $checkoutItems[] = [
        'productID'   => $productID,
        'productName' => $productRow['ProductName'] ?? $bn['productName'],
        'quantity'    => (int)$bn['quantity'],
        'price'       => (float)$price,
        'total'       => (float)$price * (int)$bn['quantity'],
    ];
}

// ── Fetch address ─────────────────────────────────────────────
$stmt    = $pdo->prepare("SELECT Address FROM customer WHERE CustomerID = ?");
$stmt->execute([$_SESSION['CustomerID']]);
$address = $stmt->fetchColumn() ?: 'No address on file';

$grandTotal = array_sum(array_column($checkoutItems, 'total'));
$itemCount  = array_sum(array_column($checkoutItems, 'quantity'));

// ── Handle order placement (POST) ────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        foreach ($checkoutItems as $item) {
            $stmt = $pdo->prepare(
                "INSERT INTO orders (ProductID, Quantity, Total, OrderDate, Amount, ShippingAddress, CustomerID)
                 VALUES (?, ?, ?, NOW(), ?, ?, ?)"
            );
            $stmt->execute([
                $item['productID'],
                $item['quantity'],
                $item['total'],
                $item['price'],
                $address,
                $_SESSION['CustomerID']
            ]);
        }
        $_SESSION['flash_success'] = 'Order placed successfully!';
        unset($_SESSION['buy_now'], $_SESSION['cart_checkout']);
        header('Location: OrderConfirmation.php');
        exit;
    } catch (Exception $e) {
        error_log('Checkout error: ' . $e->getMessage());
        $_SESSION['flash_error'] = 'Checkout failed. Please try again.';
        header('Location: Checkout.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Checkout — Order Summary</title>
    <link rel="icon" type="image/ico" href="Favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        html, body {
            height: 100%;
            font-family: 'Inter', sans-serif;
            -webkit-font-smoothing: antialiased;
            overflow-x: hidden;
        }

        body {
            background: #151616;
            background-image:
                radial-gradient(ellipse at 20% 0%,   rgba(237,141,27,0.08) 0%, transparent 55%),
                radial-gradient(ellipse at 80% 100%, rgba(237,141,27,0.05) 0%, transparent 55%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 40px 16px 60px;
        }

        a { text-decoration: none; }

        /* ── Mini top bar ──────────────────────────────────── */
        .top-bar {
            width: 100%;
            max-width: 680px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 32px;
        }
        .top-bar .site-name {
            font-size: 20px;
            font-weight: 900;
            color: #fff;
            letter-spacing: -0.5px;
        }
        .top-bar .site-name span { color: #ed8d1b; }
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #888;
            font-size: 13px;
            font-weight: 600;
            transition: color 0.2s;
        }
        .back-link:hover { color: #ed8d1b; }

        /* ── Steps indicator ───────────────────────────────── */
        .steps {
            display: flex;
            align-items: center;
            gap: 0;
            width: 100%;
            max-width: 400px;
            margin-bottom: 28px;
        }
        .step {
            display: flex;
            flex-direction: column;
            align-items: center;
            flex: 1;
            gap: 6px;
        }
        .step-dot {
            width: 32px; height: 32px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 13px; font-weight: 800;
        }
        .step.done .step-dot   { background: #27ae60; color: #fff; }
        .step.active .step-dot { background: #ed8d1b; color: #151616; }
        .step.pending .step-dot{ background: #2a2a2a; color: #666; border: 2px solid #333; }
        .step-label { font-size: 11px; font-weight: 700; color: #666; text-transform: uppercase; letter-spacing: 0.6px; white-space: nowrap; }
        .step.active .step-label { color: #ed8d1b; }
        .step.done .step-label   { color: #27ae60; }
        .step-line { flex: 1; height: 2px; background: #2a2a2a; margin-bottom: 18px; }
        .step-line.done { background: #27ae60; }

        /* ── Card ──────────────────────────────────────────── */
        .checkout-card {
            width: 100%;
            max-width: 680px;
            background: #1e1e1e;
            border-radius: 20px;
            border: 1px solid #2e2e2e;
            box-shadow: 0 8px 40px rgba(0,0,0,0.4);
            overflow: hidden;
        }

        /* Card header */
        .card-header {
            background: linear-gradient(135deg, #ed8d1b 0%, #c97415 100%);
            padding: 22px 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .card-header h2 {
            font-size: 20px;
            font-weight: 900;
            color: #151616;
            letter-spacing: -0.4px;
        }
        .item-count-badge {
            background: rgba(0,0,0,0.2);
            color: #151616;
            font-size: 12px;
            font-weight: 800;
            padding: 4px 10px;
            border-radius: 20px;
        }

        /* Card body */
        .card-body { padding: 0; }

        /* ── Items table ───────────────────────────────────── */
        .items-table-wrap { overflow-x: auto; }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 380px;
        }
        .items-table thead tr { background: #252525; }
        .items-table thead th {
            padding: 12px 20px;
            font-size: 10.5px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #888;
            text-align: left;
        }
        .items-table thead th:last-child { text-align: right; }

        .items-table tbody tr { border-bottom: 1px solid #272727; }
        .items-table tbody tr:last-child { border-bottom: none; }
        .items-table tbody tr:hover { background: rgba(255,255,255,0.02); }

        .items-table tbody td {
            padding: 14px 20px;
            font-size: 14px;
            color: #c8c8c8;
            vertical-align: middle;
        }
        .items-table tbody td:last-child { text-align: right; color: #fff; font-weight: 700; }

        .product-name { font-weight: 700; color: #fff; font-size: 15px; }

        .qty-badge {
            display: inline-block;
            background: #2a2a2a;
            border: 1px solid #3a3a3a;
            color: #aaa;
            font-size: 12px;
            font-weight: 700;
            padding: 3px 9px;
            border-radius: 6px;
        }

        /* ── Summary sections ──────────────────────────────── */
        .summary-section {
            padding: 20px 24px;
            border-top: 1px solid #272727;
        }

        .section-label {
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #888;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .section-label svg { opacity: 0.6; }

        /* Address box */
        .address-box {
            background: #252525;
            border: 1px solid #333;
            border-radius: 10px;
            padding: 13px 16px;
            font-size: 14px;
            color: #ccc;
            line-height: 1.5;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }
        .address-box svg { flex-shrink: 0; margin-top: 2px; opacity: 0.5; }

        /* Grand total row */
        .grand-total-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px 24px;
            background: #252525;
            border-top: 1px solid #333;
        }
        .grand-total-label {
            font-size: 14px;
            font-weight: 700;
            color: #aaa;
        }
        .grand-total-value {
            font-size: 28px;
            font-weight: 900;
            color: #fff;
            letter-spacing: -0.8px;
        }
        .grand-total-value span { color: #ed8d1b; }

        /* ── Confirm button ────────────────────────────────── */
        .confirm-section { padding: 20px 24px; }

        .confirm-btn {
            width: 100%;
            background: linear-gradient(135deg, #ed8d1b 0%, #c97415 100%);
            color: #151616;
            border: none;
            padding: 16px;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 900;
            cursor: pointer;
            transition: opacity 0.2s, transform 0.1s;
            letter-spacing: 0.3px;
            font-family: 'Inter', sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .confirm-btn:hover  { opacity: 0.92; }
        .confirm-btn:active { transform: scale(0.98); }

        .confirm-btn svg { flex-shrink: 0; }

        .security-note {
            text-align: center;
            font-size: 11.5px;
            color: #555;
            margin-top: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }

        /* ── Flash error ───────────────────────────────────── */
        .flash-error {
            width: 100%;
            max-width: 680px;
            background: rgba(192,57,43,0.15);
            border: 1px solid #c0392b;
            color: #e74c3c;
            border-radius: 10px;
            padding: 13px 18px;
            margin-bottom: 20px;
            font-weight: 600;
            font-size: 14px;
        }

        /* ── Responsive ────────────────────────────────────── */
        @media (max-width: 1920px){
            body{
                padding-bottom: 15px;
            }
        }
        
        @media (max-width: 1024px){
            body{
                padding-bottom: 15px;
            }
        }
        
        @media (max-width: 768px){
            body{
                padding-bottom: 15px;
            }
        }
        
        @media (max-width: 600px) {
            body { padding: 24px 10px 48px; }
            .card-header { padding: 18px 18px; }
            .card-header h2 { font-size: 17px; }
            .items-table thead th, .items-table tbody td { padding: 11px 14px; }
            .grand-total-value { font-size: 22px; }
            .grand-total-row, .summary-section, .confirm-section { padding: 16px 16px; }
            .steps { max-width: 100%; }
        }
    </style>
</head>
<body>

    <!-- ── Top bar ─────────────────────────────────────────── -->
    <div class="top-bar">
        <div class="site-name"><span>Zabco</span> Tile Depot</div>
        <a href="Checkout.php?cancel_checkout=1" class="back-link">
            ← Back to Cart
        </a>
    </div>

    <!-- ── Steps ───────────────────────────────────────────── -->
    <div class="steps">
        <div class="step done">
            <div class="step-dot">✓</div>
            <div class="step-label">Cart</div>
        </div>
        <div class="step-line done"></div>
        <div class="step active">
            <div class="step-dot">2</div>
            <div class="step-label">Review</div>
        </div>
        <div class="step-line"></div>
        <div class="step pending">
            <div class="step-dot">3</div>
            <div class="step-label">Confirm</div>
        </div>
    </div>

    <?php if (!empty($_SESSION['flash_error'])): ?>
        <div class="flash-error">⚠ <?= htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div>
    <?php endif; ?>

    <!-- ── Checkout card ───────────────────────────────────── -->
    <div class="checkout-card">

        <!-- Header -->
        <div class="card-header">
            <h2>Order Summary</h2>
            <span class="item-count-badge"><?= $itemCount ?> item<?= $itemCount !== 1 ? 's' : '' ?></span>
        </div>

        <!-- Items table -->
        <div class="card-body">
            <div class="items-table-wrap">
                <table class="items-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Price</th>
                            <th>Qty</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($checkoutItems as $item): ?>
                        <tr>
                            <td><span class="product-name"><?= htmlspecialchars($item['productName']) ?></span></td>
                            <td>₱<?= number_format($item['price'], 2) ?></td>
                            <td><span class="qty-badge"><?= $item['quantity'] ?></span></td>
                            <td>₱<?= number_format($item['total'], 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Shipping address -->
            <div class="summary-section">
                <div class="section-label">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/><circle cx="12" cy="9" r="2.5"/></svg>
                    Shipping Address
                </div>
                <div class="address-box">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                    <?= htmlspecialchars($address) ?>
                </div>
            </div>
        </div>

        <!-- Grand total -->
        <div class="grand-total-row">
            <div class="grand-total-label">Grand Total</div>
            <div class="grand-total-value"><span>₱</span><?= number_format($grandTotal, 2) ?></div>
        </div>

        <!-- Confirm button -->
        <div class="confirm-section">
            <form method="post">
                <button type="submit" class="confirm-btn">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                    Confirm &amp; Place Order
                </button>
            </form>
            <p class="security-note">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
                Your order is secure and encrypted
            </p>
        </div>

    </div><!-- /.checkout-card -->

</body>
</html>