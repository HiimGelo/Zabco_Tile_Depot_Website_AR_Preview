<?php
// ══════════════════════════════════════════════════════════════
//  Cart.php — Fixed & Redesigned
//  ROOT FIX: ALL redirect-causing handlers are BEFORE require
//  'header.php', which outputs HTML and would block header().
// ══════════════════════════════════════════════════════════════
session_start();
include 'db_connect.php';

$customerID = (int)($_SESSION['CustomerID'] ?? 0);
$isGuest    = ($customerID === 0);

// ── GUEST: REMOVE ITEM (GET) ──────────────────────────────────
if ($isGuest && isset($_GET['remove'])) {
    $removeKey = (int)$_GET['remove'];
    if (isset($_SESSION['guest_cart'][$removeKey])) {
        unset($_SESSION['guest_cart'][$removeKey]);
    }
    header("Location: Cart.php");
    exit;
}

// ── LOGGED-IN: REMOVE ITEM (GET) ─────────────────────────────
if (!$isGuest && isset($_GET['remove']) && ctype_digit((string)$_GET['remove'])) {
    $pdo->prepare("DELETE FROM cart WHERE CartID = ? AND CustomerID = ?")
        ->execute([(int)$_GET['remove'], $customerID]);
    header("Location: Cart.php");
    exit;
}

// ── AJAX QTY UPDATE — logged-in only ─────────────────────────
if (!$isGuest && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_update_qty'])) {
    header('Content-Type: application/json');
    $cartID = (int)($_POST['cartID']   ?? 0);
    $qty    = max(1, (int)($_POST['quantity'] ?? 1));
    $ok     = $pdo->prepare("UPDATE cart SET Quantity = ? WHERE CartID = ? AND CustomerID = ?")
                  ->execute([$qty, $cartID, $customerID]);
    echo json_encode(['ok' => $ok, 'qty' => $qty]);
    exit;
}

// ── GUEST AJAX QTY UPDATE ─────────────────────────────────────
if ($isGuest && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_update_qty'])) {
    header('Content-Type: application/json');
    $cartID = (int)($_POST['cartID'] ?? 0);
    $qty    = max(1, (int)($_POST['quantity'] ?? 1));
    if (isset($_SESSION['guest_cart'][$cartID])) {
        $_SESSION['guest_cart'][$cartID]['quantity'] = $qty;
        echo json_encode(['ok' => true, 'qty' => $qty]);
    } else {
        echo json_encode(['ok' => false]);
    }
    exit;
}

// ── CHECKOUT SELECTED ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout_selected'])) {
    // Guests must log in first — redirect to login and come back to cart
    if ($isGuest) {
        $_SESSION['redirect_after_login'] = 'Cart.php';
        header("Location: Login&Signup.php?checkout=1");
        exit;
    }

    if (empty($_POST['checkout_items'])) {
        $_SESSION['flash_error'] = 'Please select at least one item to checkout.';
        header("Location: Cart.php");
        exit;
    }

    $selectedIDs  = array_map('intval', (array)$_POST['checkout_items']);
    $postedQtys   = (array)($_POST['quantities'] ?? []);
    $ph           = implode(',', array_fill(0, count($selectedIDs), '?'));

    $stmt = $pdo->prepare(
        "SELECT c.CartID, c.ProductName, c.Quantity,
                COALESCE(pm.Price, ps.Price, pl.Price) AS Price
         FROM cart c
         LEFT JOIN productsmedian pm        ON c.ProductName = pm.ProductName
         LEFT JOIN productssophisticated ps ON c.ProductName = ps.ProductName
         LEFT JOIN productsluxurious pl     ON c.ProductName = pl.ProductName
         WHERE c.CartID IN ($ph) AND c.CustomerID = ?"
    );
    $stmt->execute(array_merge($selectedIDs, [$customerID]));
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $selectedItems = [];
    foreach ($rows as $item) {
        $cid   = (int)$item['CartID'];
        $qty   = isset($postedQtys[$cid]) ? max(1, (int)$postedQtys[$cid]) : (int)$item['Quantity'];
        $price = (float)($item['Price'] ?? 0);
        $pid   = null;

        foreach (['productsmedian','productssophisticated','productsluxurious'] as $t) {
            $r = $pdo->prepare("SELECT ProductID, Price FROM $t WHERE ProductName = ?");
            $r->execute([$item['ProductName']]);
            $row = $r->fetch(PDO::FETCH_ASSOC);
            if ($row) { $pid = (int)$row['ProductID']; $price = (float)$row['Price']; break; }
        }

        $selectedItems[] = [
            'productID'   => $pid,
            'productName' => $item['ProductName'],
            'quantity'    => $qty,
            'price'       => $price,
            'amount'      => $price,
            'total'       => $price * $qty,
        ];
    }

    if (empty($selectedItems)) {
        $_SESSION['flash_error'] = 'Could not find the selected products. Please try again.';
        header("Location: Cart.php");
        exit;
    }

    foreach ($selectedIDs as $cid) {
        $pdo->prepare("DELETE FROM cart WHERE CartID = ? AND CustomerID = ?")
            ->execute([$cid, $customerID]);
    }

    $_SESSION['cart_checkout'] = $selectedItems;
    unset($_SESSION['buy_now']);
    header("Location: Checkout.php");
    exit;
}

// ── ALL REDIRECTS DONE — safe to output HTML now ─────────────
require 'header.php';

// Fetch cart items for display
if ($isGuest) {
    // Build cart items from session guest_cart
    $cartItems = [];
    foreach ($_SESSION['guest_cart'] ?? [] as $key => $g) {
        // Look up price from DB
        $price = null;
        foreach (['productsmedian','productssophisticated','productsluxurious'] as $t) {
            $r = $pdo->prepare("SELECT Price FROM $t WHERE ProductName = ?");
            $r->execute([$g['product_name']]);
            $row = $r->fetch(PDO::FETCH_ASSOC);
            if ($row) { $price = $row['Price']; break; }
        }
        $cartItems[] = [
            'CartID'      => $key,
            'ProductName' => $g['product_name'],
            'Quantity'    => $g['quantity'],
            'Price'       => $price,
        ];
    }
} else {
    $stmt = $pdo->prepare(
        "SELECT c.CartID, c.ProductName, c.Quantity,
                COALESCE(pm.Price, ps.Price, pl.Price) AS Price
         FROM cart c
         LEFT JOIN productsmedian pm        ON c.ProductName = pm.ProductName
         LEFT JOIN productssophisticated ps ON c.ProductName = ps.ProductName
         LEFT JOIN productsluxurious pl     ON c.ProductName = pl.ProductName
         WHERE c.CustomerID = ?
         ORDER BY c.CartID DESC"
    );
    $stmt->execute([$customerID]);
    $cartItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Cart</title>
    <link rel="icon" type="image/ico" href="Favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">
    <style>
        /* ── Reset ───────────────────────────────────────────── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', sans-serif;
            background: #f0f0f0;
            padding-top: 120px;
            min-height: 100vh;
            color: #151616;
            -webkit-font-smoothing: antialiased;
        }

        a { text-decoration: none; }

        /* ── Page title bar (matches Products page) ──────────── */
        .page-title-bar {
            display: flex;
            width: 100%;
            background: linear-gradient(90deg, #1e1e1e 0%, #2d2d2d 100%);
            padding: 0 28px;
            justify-content: space-between;
            align-items: center;
            border-bottom: 3px solid #ed8d1b;
        }
        .page-title-bar h1 {
            color: #fff;
            font-size: clamp(18px, 4vw, 28px);
            font-weight: 800;
            padding: 20px 0;
            letter-spacing: -0.4px;
        }
        .page-title-bar a {
            color: #ed8d1b;
            font-size: 14px;
            font-weight: 700;
            padding: 8px 16px;
            border: 1.5px solid #ed8d1b;
            border-radius: 8px;
            transition: background 0.2s, color 0.2s;
        }
        .page-title-bar a:hover { background: #ed8d1b; color: #151616; }

        /* ── Cart wrapper ─────────────────────────────────────── */
        .cart-wrapper {
            max-width: 1040px;
            margin: 32px auto 72px;
            padding: 0 20px;
        }

        /* ── Flash messages ───────────────────────────────────── */
        .flash {
            padding: 13px 18px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 600;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .flash.error   { background: rgba(192,57,43,0.15); border: 1px solid #c0392b; color: #e74c3c; }
        .flash.success { background: rgba(39,174,96,0.15);  border: 1px solid #27ae60; color: #2ecc71; }

        /* ── Empty cart ───────────────────────────────────────── */
        .empty-cart {
            text-align: center;
            padding: 60px 20px;
            background: #1e1e1e;
            border-radius: 16px;
            border: 1px solid #2e2e2e;
        }
        .empty-cart img  { width: 110px; opacity: 0.45; margin: 0 auto 20px; display: block; }
        .empty-cart p    { font-size: 17px; font-weight: 600; color: #aaa; margin-bottom: 24px; }
        .empty-cart a {
            display: inline-block;
            background: #ed8d1b;
            color: #151616;
            padding: 11px 28px;
            border-radius: 10px;
            font-weight: 800;
            font-size: 14px;
        }
        .empty-cart a:hover { background: #c97415; }

        /* ── Cart card ────────────────────────────────────────── */
        .cart-card {
            background: #1e1e1e;
            border-radius: 16px;
            border: 1px solid #2e2e2e;
            box-shadow: 0 6px 32px rgba(0,0,0,0.22);
            overflow: hidden;
        }

        /* ── Select-all header row ────────────────────────────── */
        .select-all-bar {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 13px 20px;
            background: #252525;
            border-bottom: 1px solid #2e2e2e;
            cursor: pointer;
            user-select: none;
        }
        .select-all-bar span { font-size: 13px; font-weight: 700; color: #aaa; }
        .select-all-bar input { cursor: pointer; accent-color: #ed8d1b; width: 16px; height: 16px; }

        /* ── Table ────────────────────────────────────────────── */
        .table-scroll { overflow-x: auto; }

        .cart-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 520px;
        }
        .cart-table thead tr { background: #ed8d1b; }
        .cart-table thead th {
            padding: 13px 16px;
            font-size: 11.5px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #151616;
            text-align: left;
            white-space: nowrap;
        }
        .cart-table thead th:last-child,
        .cart-table thead th:first-child { text-align: center; }

        .cart-table tbody tr {
            border-bottom: 1px solid #2a2a2a;
            transition: background 0.15s;
        }
        .cart-table tbody tr:last-child { border-bottom: none; }
        .cart-table tbody tr:hover { background: rgba(255,255,255,0.025); }

        .cart-table tbody td {
            padding: 14px 16px;
            font-size: 14px;
            color: #c8c8c8;
            vertical-align: middle;
        }
        .cart-table tbody td:last-child,
        .cart-table tbody td:first-child { text-align: center; }

        .product-name-cell { font-weight: 700; color: #fff; font-size: 15px; }

        /* ── Checkbox ─────────────────────────────────────────── */
        .item-cb {
            accent-color: #ed8d1b;
            width: 17px; height: 17px;
            cursor: pointer;
        }

        /* ── Qty control ──────────────────────────────────────── */
        .qty-wrap { display: flex; flex-direction: column; align-items: flex-start; gap: 4px; }
        .qty-control { display: flex; align-items: center; gap: 5px; }

        .qty-btn {
            width: 30px; height: 30px;
            background: #2a2a2a;
            border: 1.5px solid #444;
            color: #fff;
            font-size: 18px;
            font-weight: 700;
            border-radius: 7px;
            cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            line-height: 1;
            transition: background 0.15s, border-color 0.15s, color 0.15s;
            font-family: 'Inter', sans-serif;
        }
        .qty-btn:hover { background: #ed8d1b; border-color: #ed8d1b; color: #151616; }

        .qty-input {
            width: 52px;
            text-align: center;
            background: #2a2a2a;
            border: 1.5px solid #444;
            color: #fff;
            border-radius: 7px;
            padding: 5px 4px;
            font-size: 14px;
            font-weight: 700;
            font-family: 'Inter', sans-serif;
        }
        .qty-input:focus { outline: none; border-color: #ed8d1b; }
        .qty-input::-webkit-inner-spin-button,
        .qty-input::-webkit-outer-spin-button { -webkit-appearance: none; }

        .save-ind {
            font-size: 11px;
            min-height: 14px;
            color: transparent;
            transition: color 0.2s;
            font-weight: 600;
        }
        .save-ind.saving { color: #ed8d1b; }
        .save-ind.saved  { color: #27ae60; }
        .save-ind.err    { color: #e74c3c; }

        /* ── Remove button ────────────────────────────────────── */
        .remove-btn {
            display: inline-flex; align-items: center; justify-content: center;
            width: 34px; height: 34px;
            background: rgba(192,57,43,0.12);
            border: 1.5px solid rgba(192,57,43,0.35);
            border-radius: 8px;
            color: #e74c3c;
            font-size: 18px;
            font-weight: 700;
            transition: background 0.15s, border-color 0.15s, color 0.15s;
            line-height: 1;
        }
        .remove-btn:hover { background: #c0392b; border-color: #c0392b; color: #fff; }

        /* ── Cart footer ──────────────────────────────────────── */
        .cart-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px 24px;
            background: #252525;
            border-top: 1px solid #333;
            gap: 16px;
            flex-wrap: wrap;
        }
        .total-block { display: flex; flex-direction: column; gap: 2px; }
        .total-label {
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #888;
        }
        .total-value {
            font-size: 24px;
            font-weight: 900;
            color: #fff;
            letter-spacing: -0.5px;
        }
        .total-value span { color: #ed8d1b; }

        .checkout-btn {
            background: #ed8d1b;
            color: #151616;
            border: none;
            padding: 13px 36px;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 800;
            cursor: pointer;
            transition: background 0.2s, transform 0.1s;
            letter-spacing: 0.3px;
            font-family: 'Inter', sans-serif;
        }
        .checkout-btn:hover:not(:disabled) { background: #c97415; }
        .checkout-btn:active:not(:disabled) { transform: scale(0.97); }
        .checkout-btn:disabled {
            background: #3a3a3a;
            color: #666;
            cursor: not-allowed;
        }

        

        /* ── Responsive ───────────────────────────────────────── */
        @media (max-width: 992px) {
            .nav-toggle { display: inline-flex; }
        }
        @media (max-width: 720px) {
            .cart-wrapper { padding: 0 12px; margin-top: 20px; }
            .page-title-bar { padding: 0 16px; }
            .cart-footer { flex-direction: column; align-items: stretch; }
            .checkout-btn { width: 100%; text-align: center; }
            .total-value { font-size: 20px; }
        }

        /* ── Mobile card layout for cart table ────────────────── */
        @media (max-width: 600px) {
            body { padding-top: 70px !important; }

            .page-title-bar h1 { font-size: 18px; }
            .page-title-bar a  { font-size: 12px; padding: 6px 12px; }

            /* Hide desktop table, show mobile cards */
            .table-scroll { overflow-x: unset; }
            .cart-table   { min-width: unset; display: block; }

            .cart-table thead { display: none; }

            .cart-table tbody,
            .cart-table tbody tr { display: block; }

            /* Each row becomes a card */
            .cart-table tbody tr {
                background: #252525;
                border-radius: 12px;
                border: 1px solid #2e2e2e;
                margin: 10px 0;
                padding: 14px 14px 12px;
            }
            .cart-table tbody tr:hover { background: #282828; }

            /* All cells become block, with label prefix */
            .cart-table tbody td {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 6px 0;
                font-size: 13px;
                border: none;
                text-align: right;
            }
            .cart-table tbody td::before {
                content: attr(data-label);
                font-size: 10.5px;
                font-weight: 800;
                text-transform: uppercase;
                letter-spacing: 0.7px;
                color: #666;
                text-align: left;
                flex: 1;
            }

            /* Checkbox cell — row at top, centred */
            .cart-table tbody td:first-child {
                justify-content: flex-start;
                gap: 10px;
                padding-bottom: 10px;
                border-bottom: 1px solid #333;
                margin-bottom: 6px;
            }
            .cart-table tbody td:first-child::before { content: 'Select item'; }

            /* Product name gets full width */
            .cart-table tbody td:nth-child(2) { justify-content: flex-start; }
            .cart-table tbody td:nth-child(2)::before { display: none; }
            .product-name-cell { font-size: 15px; font-weight: 800; }

            /* Remove button cell */
            .cart-table tbody td:last-child {
                padding-top: 10px;
                border-top: 1px solid #333;
                margin-top: 6px;
                justify-content: flex-end;
            }
            .cart-table tbody td:last-child::before { content: 'Remove'; color: #8a4040; }

            /* Qty control inline */
            .qty-wrap { align-items: flex-end; }

            /* Cart footer stacks */
            .cart-footer {
                padding: 16px;
                gap: 12px;
            }
            .total-value { font-size: 22px; }
            .checkout-btn { padding: 14px; font-size: 15px; }

            /* Select-all bar */
            .select-all-bar { padding: 12px 14px; }
        }

        @media (max-width: 360px) {
            body { padding-top: 64px !important; }
            .cart-wrapper { padding: 0 8px; }
        }
    </style>
</head>
<body>

<!-- ── Page title bar ──────────────────────────────────────── -->
<div class="page-title-bar">
    <h1>🛒 My Cart</h1>
    <a href="Products.php">← Continue Shopping</a>
</div>

<!-- ── Main content ───────────────────────────────────────── -->
<div class="cart-wrapper">

    <?php if (!empty($_SESSION['flash_error'])): ?>
        <div class="flash error">⚠ <?= htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['flash_success'])): ?>
        <div class="flash success">✓ <?= htmlspecialchars($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?></div>
    <?php endif; ?>

    <?php if ($isGuest && !empty($cartItems)): ?>
        <div class="flash" style="background:rgba(237,141,27,0.12);border:1px solid #ed8d1b;color:#ed8d1b;">
            🔒 You're browsing as a guest. <a href="Login&amp;Signup.php" style="color:#fff;font-weight:800;text-decoration:underline;">Log in or create an account</a> to proceed to checkout — your cart items will be saved automatically.
        </div>
    <?php endif; ?>

    <?php if (empty($cartItems)): ?>
        <div class="empty-cart">
            <img src="Empty Cart.png" alt="Empty Cart">
            <p>Your cart is currently empty.</p>
            <a href="Products.php">Browse Products</a>
        </div>

    <?php else: ?>

    <form method="post" id="cartForm">
        <div class="cart-card">

            <!-- Select-all bar -->
            <label class="select-all-bar">
                <input type="checkbox" id="selectAll" class="item-cb">
                <span>Select All (<?= count($cartItems) ?> item<?= count($cartItems) !== 1 ? 's' : '' ?>)</span>
            </label>

            <!-- Scrollable table -->
            <div class="table-scroll">
                <table class="cart-table">
                    <thead>
                        <tr>
                            <th></th>
                            <th>Product</th>
                            <th>Price (₱)</th>
                            <th>Quantity</th>
                            <th>Subtotal (₱)</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cartItems as $item):
                            $price    = ($item['Price'] !== null) ? (float)$item['Price'] : 0;
                            $subtotal = $price * (int)$item['Quantity'];
                            $cid      = (int)$item['CartID'];
                        ?>
                        <tr id="row-<?= $cid ?>">

                            <!-- Checkbox -->
                            <td>
                                <input type="checkbox"
                                       name="checkout_items[]"
                                       value="<?= $cid ?>"
                                       class="item-cb item-checkbox"
                                       data-price="<?= $price ?>"
                                       data-cartid="<?= $cid ?>">
                            </td>

                            <!-- Product name -->
                            <td><span class="product-name-cell"><?= htmlspecialchars($item['ProductName']) ?></span></td>

                            <!-- Price -->
                            <td data-label="Price (₱)"><?= $price > 0 ? number_format($price, 2) : 'N/A' ?></td>

                            <!-- Quantity controls -->
                            <td data-label="Quantity">
                                <div class="qty-wrap">
                                    <div class="qty-control">
                                        <button type="button" class="qty-btn"
                                                data-action="minus"
                                                data-cartid="<?= $cid ?>">−</button>
                                        <input type="number"
                                               class="qty-input"
                                               name="quantities[<?= $cid ?>]"
                                               data-cartid="<?= $cid ?>"
                                               value="<?= (int)$item['Quantity'] ?>"
                                               min="1">
                                        <button type="button" class="qty-btn"
                                                data-action="plus"
                                                data-cartid="<?= $cid ?>">+</button>
                                    </div>
                                    <div class="save-ind" id="save-<?= $cid ?>"></div>
                                </div>
                            </td>

                            <!-- Subtotal -->
                            <td data-label="Subtotal (₱)" id="subtotal-<?= $cid ?>"><?= number_format($subtotal, 2) ?></td>

                            <!-- Remove -->
                            <td>
                                <a href="Cart.php?remove=<?= $cid ?>"
                                   class="remove-btn"
                                   title="Remove item"
                                   onclick="return confirm('Remove this item from your cart?')">×</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Footer: total + checkout -->
            <div class="cart-footer">
                <div class="total-block">
                    <div class="total-label">Selected Total</div>
                    <div class="total-value">₱<span id="selected-total">0.00</span></div>
                </div>
                <button type="submit"
                        name="checkout_selected"
                        class="checkout-btn"
                        id="checkoutBtn"
                        disabled>
                    <?= $isGuest ? 'Log In to Checkout →' : 'Checkout Selected →' ?>
                </button>
            </div>
        </div>
    </form>

    <?php endif; ?>
</div><!-- /.cart-wrapper -->

<!-- ── Scripts ────────────────────────────────────────────── -->
<script>
(function () {
    'use strict';

    // ── Recalculate selected total ──────────────────────────
    function recalcTotal() {
        let total      = 0;
        let anyChecked = false;

        document.querySelectorAll('.item-checkbox').forEach(cb => {
            const cid       = cb.dataset.cartid;
            const qtyInput  = document.querySelector(`input.qty-input[data-cartid="${cid}"]`);
            const qty       = qtyInput ? Math.max(1, parseInt(qtyInput.value) || 1) : 1;
            const price     = parseFloat(cb.dataset.price) || 0;

            if (cb.checked) {
                anyChecked = true;
                total += price * qty;
            }

            // Always keep subtotal cell in sync
            const cell = document.getElementById('subtotal-' + cid);
            if (cell) cell.textContent = (price * qty).toFixed(2);
        });

        const totalEl = document.getElementById('selected-total');
        if (totalEl) totalEl.textContent = total.toFixed(2);

        const btn = document.getElementById('checkoutBtn');
        if (btn) btn.disabled = !anyChecked;
    }

    // ── Checkbox listeners ──────────────────────────────────
    document.querySelectorAll('.item-checkbox').forEach(cb => {
        cb.addEventListener('change', () => {
            // Sync select-all state
            const all    = document.querySelectorAll('.item-checkbox');
            const checked = document.querySelectorAll('.item-checkbox:checked');
            const selectAll = document.getElementById('selectAll');
            if (selectAll) selectAll.checked = (checked.length === all.length);
            recalcTotal();
        });
    });

    // ── Select All ──────────────────────────────────────────
    const selectAll = document.getElementById('selectAll');
    if (selectAll) {
        selectAll.addEventListener('change', () => {
            document.querySelectorAll('.item-checkbox').forEach(cb => {
                cb.checked = selectAll.checked;
            });
            recalcTotal();
        });
    }

    // ── AJAX qty save (debounced) ───────────────────────────
    const saveTimers = {};

    function saveQty(cartID, qty, indicator) {
        if (indicator) {
            indicator.textContent = 'Saving…';
            indicator.className   = 'save-ind saving';
        }
        fetch('Cart.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body:    `ajax_update_qty=1&cartID=${encodeURIComponent(cartID)}&quantity=${encodeURIComponent(qty)}`
        })
        .then(r => r.json())
        .then(data => {
            if (!indicator) return;
            if (data.ok) {
                indicator.textContent = '✓ Saved';
                indicator.className   = 'save-ind saved';
            } else {
                indicator.textContent = 'Error';
                indicator.className   = 'save-ind err';
            }
            setTimeout(() => {
                indicator.textContent = '';
                indicator.className   = 'save-ind';
            }, 1800);
        })
        .catch(() => {
            if (indicator) { indicator.textContent = 'Error'; indicator.className = 'save-ind err'; }
        });
    }

    function scheduleQtySave(cartID, qty) {
        const indicator = document.getElementById('save-' + cartID);
        clearTimeout(saveTimers[cartID]);
        saveTimers[cartID] = setTimeout(() => saveQty(cartID, qty, indicator), 500);
    }

    // ── +/- buttons ─────────────────────────────────────────
    document.querySelectorAll('.qty-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const cid   = btn.dataset.cartid;
            const input = document.querySelector(`input.qty-input[data-cartid="${cid}"]`);
            if (!input) return;
            let val = parseInt(input.value) || 1;
            val = btn.dataset.action === 'plus' ? val + 1 : Math.max(1, val - 1);
            input.value = val;
            recalcTotal();
            scheduleQtySave(cid, val);
        });
    });

    // ── Direct qty input ────────────────────────────────────
    document.querySelectorAll('.qty-input').forEach(input => {
        input.addEventListener('input', () => {
            const cid = input.dataset.cartid;
            const val = Math.max(1, parseInt(input.value) || 1);
            input.value = val;
            recalcTotal();
            scheduleQtySave(cid, val);
        });
    });

    document.addEventListener('click', e => {
        if (menuList && menuList.contains(e.target)) return;
        if (userEl   && userEl.contains(e.target))   return;
        if (navToggle && navToggle.contains(e.target)) return;
        closeAllMenus();
    });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeAllMenus(); });
    window.addEventListener('resize', () => {
        if (window.innerWidth > 992) { closeAllMenus(); if (menuList) menuList.removeAttribute('style'); }
    });

    const userBtn  = document.getElementById('userBtn');
    const userIcon = document.getElementById('userIcon');
    const dropdown = document.getElementById('dropdown');
    if (userBtn && userIcon && dropdown) {
        userBtn.addEventListener('click', e => {
            e.preventDefault();
            const isWhite = userIcon.src.includes('user-white.png');
            userIcon.src  = isWhite ? 'user-orange.png' : 'user-white.png';
            dropdown.style.display = (!dropdown.style.display || dropdown.style.display === 'none') ? 'block' : 'none';
        });
        document.addEventListener('click', e => {
            if (!userBtn.contains(e.target) && !dropdown.contains(e.target)) {
                dropdown.style.display = 'none';
                userIcon.src = 'user-white.png';
            }
        });
    }

    // Init on load
    recalcTotal();
})();
</script>

<?php require 'footer.php'; ?>
</body>
</html>