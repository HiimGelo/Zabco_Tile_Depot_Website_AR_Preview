<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['CustomerID'])) {
    header("Location: Login&Signup.php");
    exit;
}

$customerID = $_SESSION['CustomerID'];

// Handle cancellation POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_cancel']) && isset($_POST['orderID'])) {
    $orderID = (int)$_POST['orderID'];
    $stmt    = $pdo->prepare("UPDATE orders SET OrderStatus = 'Cancelled' WHERE OrderID = ? AND CustomerID = ?");
    $stmt->execute([$orderID, $customerID]);
    $_SESSION['flash_success'] = "Order #$orderID has been cancelled.";
    header("Location: Orders.php");
    exit;
}

// Handle Write Review POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'write_review') {
    $reviewedOrderID = (int)($_POST['order_id'] ?? 0);
    $text   = trim($_POST['rv_text']   ?? '');
    $label  = trim($_POST['rv_label']  ?? 'Verified Customer');
    $rating = max(1, min(5, (int)($_POST['rv_rating'] ?? 5)));
    if ($text !== '' && $reviewedOrderID) {
        // Ensure table exists (mirrors index.php logic)
        $pdo->exec("CREATE TABLE IF NOT EXISTS customer_reviews (
            id             INT AUTO_INCREMENT PRIMARY KEY,
            review_text    TEXT NOT NULL,
            reviewer_label VARCHAR(100) NOT NULL DEFAULT 'Verified Customer',
            rating         TINYINT NOT NULL DEFAULT 5,
            created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        $pdo->prepare("INSERT INTO customer_reviews (review_text, reviewer_label, rating) VALUES (?,?,?)")
            ->execute([$text, $label ?: 'Verified Customer', $rating]);
        // Mark this order as reviewed in the session so the button hides
        if (!isset($_SESSION['reviewed_orders'][$customerID])) {
            $_SESSION['reviewed_orders'][$customerID] = [];
        }
        $_SESSION['reviewed_orders'][$customerID][] = $reviewedOrderID;
        $_SESSION['flash_success'] = 'Thank you for your review! It will appear on our homepage.';
    }
    header('Location: Orders.php');
    exit;
}
require 'header.php';

// Fetch orders
$stmt = $pdo->prepare("SELECT * FROM orders WHERE CustomerID = ? ORDER BY OrderDate DESC");
$stmt->execute([$customerID]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Map ProductID → ProductName
$tables = ['productsmedian','productssophisticated','productsluxurious'];
foreach ($orders as &$order) {
    $productName = null;
    foreach ($tables as $table) {
        $s = $pdo->prepare("SELECT ProductName FROM $table WHERE ProductID = ?");
        $s->execute([$order['ProductID']]);
        $result = $s->fetch(PDO::FETCH_ASSOC);
        if ($result && !empty($result['ProductName'])) { $productName = $result['ProductName']; break; }
    }
    $order['ProductName'] = $productName ?? 'N/A';
}
unset($order);
?>
<!DOCTYPE html>
<html lang="en" style="background:#f0f0f0;">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Orders</title>
    <link rel="icon" type="image/png" href="Favicon.ico">
    <link rel="stylesheet" href="assets/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="OrderStyles.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            -webkit-font-smoothing: antialiased;
        }
        a { text-decoration: none; }

        /* ── Page title bar ───────────────────────────────────── */
        .page-title-bar {
            display: flex;
            width: 100%;
            background: linear-gradient(90deg, #1e1e1e 0%, #2d2d2d 100%);
            padding: 0 28px;
            justify-content: space-between;
            align-items: center;
            border-bottom: 3px solid #ed8d1b;
            flex-wrap: wrap;
            gap: 8px;
        }
        .page-title-bar h1 {
            color: #fff;
            font-size: clamp(16px, 4vw, 28px);
            font-weight: 800;
            padding: 16px 0;
            letter-spacing: -0.4px;
        }
        .page-title-bar a {
            color: #ed8d1b;
            font-size: 13px;
            font-weight: 700;
            padding: 7px 14px;
            border: 1.5px solid #ed8d1b;
            border-radius: 8px;
            transition: background 0.2s, color 0.2s;
            white-space: nowrap;
        }
        .page-title-bar a:hover { background: #ed8d1b; color: #151616; }

        /* ── Orders wrapper ───────────────────────────────────── */
        .orders-wrapper {
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
        .flash.success { background: rgba(39,174,96,0.15); border: 1px solid #27ae60; color: #2ecc71; }
        .flash.error   { background: rgba(192,57,43,0.15);  border: 1px solid #c0392b; color: #e74c3c; }

        /* ── Empty state ──────────────────────────────────────── */
        .empty-orders {
            text-align: center;
            padding: 60px 20px;
            background: #1e1e1e;
            border-radius: 16px;
            border: 1px solid #2e2e2e;
            box-shadow: 0 4px 24px rgba(0,0,0,0.2);
        }
        .empty-orders img  { max-width: 160px; opacity: 0.7; margin-bottom: 20px; }
        .empty-orders p    { color: #888; font-size: 18px; font-weight: 700; margin-bottom: 24px; }
        .empty-orders a    {
            display: inline-block;
            padding: 12px 28px;
            background: #ed8d1b;
            color: #151616;
            font-weight: 800;
            font-size: 14px;
            border-radius: 10px;
            transition: background 0.2s;
        }
        .empty-orders a:hover { background: #c97415; }

        /* ── Orders card ──────────────────────────────────────── */
        .orders-card {
            background: #1e1e1e;
            border-radius: 16px;
            border: 1px solid #2e2e2e;
            box-shadow: 0 4px 24px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        .orders-card-header {
            background: linear-gradient(90deg, #1a1a1a 0%, #252525 100%);
            border-bottom: 2px solid #ed8d1b;
            padding: 16px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }
        .orders-card-header h2 {
            color: #ed8d1b;
            font-size: clamp(16px, 3vw, 20px);
            font-weight: 900;
            letter-spacing: -0.4px;
        }
        .order-count {
            background: rgba(237,141,27,0.15);
            border: 1px solid rgba(237,141,27,0.3);
            color: #ed8d1b;
            font-size: 12px;
            font-weight: 800;
            padding: 4px 10px;
            border-radius: 20px;
            white-space: nowrap;
        }

        /* ── Status badges ───────────────────────────────────── */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.4px;
            white-space: nowrap;
        }
        .status-badge::before {
            content: '';
            width: 6px; height: 6px;
            border-radius: 50%;
            background: currentColor;
            flex-shrink: 0;
        }
        .status-Pending    { background: rgba(243,156,18,0.15);  color: #f39c12; border: 1px solid rgba(243,156,18,0.3); }
        .status-Processing { background: rgba(52,152,219,0.15);  color: #3498db; border: 1px solid rgba(52,152,219,0.3); }
        .status-Paid       { background: rgba(0,188,212,0.15);   color: #00bcd4; border: 1px solid rgba(0,188,212,0.3); }
        .status-Shipped    { background: rgba(155,89,182,0.15);  color: #9b59b6; border: 1px solid rgba(155,89,182,0.3); }
        .status-Delivered  { background: rgba(39,174,96,0.15);   color: #27ae60; border: 1px solid rgba(39,174,96,0.3); }
        .status-Cancelled  { background: rgba(192,57,43,0.15);   color: #e74c3c; border: 1px solid rgba(192,57,43,0.3); }

        /* ── Table ────────────────────────────────────────────── */
        .table-responsive {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        .orders-table {
            width: 100%;
            min-width: 600px;
            border-collapse: collapse;
        }
        .orders-table thead tr { background: #252525; }
        .orders-table thead th {
            padding: 12px 16px;
            font-size: 10.5px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #888;
            text-align: left;
            border-bottom: 1px solid #2e2e2e;
            white-space: nowrap;
        }
        .orders-table tbody tr { border-bottom: 1px solid #252525; }
        .orders-table tbody tr:last-child { border-bottom: none; }
        .orders-table tbody tr:hover td { background: rgba(255,255,255,0.025); }
        .orders-table tbody td {
            padding: 14px 16px;
            font-size: 14px;
            color: #c8c8c8;
            vertical-align: middle;
        }

        /* ── Cell styles ──────────────────────────────────────── */
        .product-name  { font-weight: 700; color: #fff; font-size: 14px; }
        .order-id-badge {
            display: inline-block;
            background: #2a2a2a;
            border: 1px solid #3a3a3a;
            color: #ed8d1b;
            font-size: 12px;
            font-weight: 800;
            padding: 3px 9px;
            border-radius: 6px;
            font-family: 'Inter', monospace;
        }
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
        .amount-cell { color: #fff; font-weight: 700; }

        /* ── Write Review button ──────────────────────────────── */
        .review-btn {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 7px 14px;
            background: #27ae60;
            color: #fff;
            border: none;
            border-radius: 7px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s;
            font-family: 'Inter', sans-serif;
            white-space: nowrap;
            margin-left: 6px;
        }
        .review-btn:hover { background: #1e8449; color: #fff; }

        /* ── Review Modal ─────────────────────────────────────── */
        .review-modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.75);
            z-index: 4000;
            backdrop-filter: blur(4px);
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .review-modal-overlay.active { display: flex; }

        .review-modal-card {
            background: #1e1e1e;
            border-radius: 20px;
            border: 1px solid #2e2e2e;
            box-shadow: 0 12px 56px rgba(0,0,0,0.55);
            width: 100%;
            max-width: 480px;
            overflow: hidden;
            animation: modalIn 0.28s cubic-bezier(0.22,1,0.36,1) both;
            max-height: 90vh;
            overflow-y: auto;
        }

        .review-modal-header {
            padding: 28px 28px 16px;
            border-bottom: 2px solid #ed8d1b;
            margin-bottom: 22px;
        }
        .review-modal-header h3 {
            color: #fff;
            font-size: 20px;
            font-weight: 900;
            margin: 0 0 4px;
            letter-spacing: -0.4px;
        }
        .review-modal-header p {
            color: #888;
            font-size: 13px;
            margin: 0;
        }
        .review-modal-header p span { color: #ed8d1b; font-weight: 700; }

        .review-modal-body { padding: 0 28px 28px; }

        .rv-field-label {
            display: block;
            font-size: 11px;
            font-weight: 700;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 0.7px;
            margin-bottom: 8px;
            margin-top: 18px;
        }

        /* ── Interactive star rating ── */
        .star-rating-input {
            display: flex;
            gap: 4px;
            margin-bottom: 4px;
        }
        .star-rating-input .star-btn {
            background: none;
            border: none;
            font-size: 30px;
            color: #333;
            cursor: pointer;
            padding: 0;
            transition: color 0.15s, transform 0.12s;
            line-height: 1;
        }
        .star-rating-input .star-btn.lit  { color: #ed8d1b; }
        .star-rating-input .star-btn:hover { transform: scale(1.18); }

        .rv-textarea {
            width: 100%;
            padding: 12px 14px;
            background: #2a2a2a;
            border: 1.5px solid #3a3a3a;
            border-radius: 10px;
            color: #fff;
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            line-height: 1.55;
            resize: vertical;
            min-height: 110px;
            transition: border-color 0.2s;
            box-sizing: border-box;
        }
        .rv-textarea:focus { outline: none; border-color: #ed8d1b; background: #2f2f2f; }
        .rv-textarea::placeholder { color: #555; }

        .rv-input-text {
            width: 100%;
            padding: 10px 14px;
            background: #2a2a2a;
            border: 1.5px solid #3a3a3a;
            border-radius: 10px;
            color: #fff;
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            transition: border-color 0.2s;
            box-sizing: border-box;
        }
        .rv-input-text:focus { outline: none; border-color: #ed8d1b; background: #2f2f2f; }

        .review-modal-actions {
            display: flex;
            gap: 10px;
            margin-top: 24px;
        }
        .rv-submit-btn {
            flex: 1;
            padding: 13px;
            background: #27ae60;
            color: #fff;
            border: none;
            border-radius: 11px;
            font-size: 14px;
            font-weight: 800;
            cursor: pointer;
            font-family: 'Inter', sans-serif;
            transition: background 0.2s;
        }
        .rv-submit-btn:hover { background: #1e8449; }
        .rv-cancel-btn {
            padding: 12px 18px;
            background: transparent;
            color: #888;
            border: 1.5px solid #2e2e2e;
            border-radius: 11px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            font-family: 'Inter', sans-serif;
            transition: border-color 0.2s, color 0.2s;
        }
        .rv-cancel-btn:hover { border-color: #555; color: #ccc; }

        /* ── Cancel button ────────────────────────────────────── */
        .cancel-btn {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 7px 14px;
            background: #d9534f;
            color: #fff;
            border: none;
            border-radius: 7px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s;
            font-family: 'Inter', sans-serif;
            white-space: nowrap;
        }
        .cancel-btn:hover { background: #b52b27; color: #fff; }


        /* ── Responsive: table → cards ────────────────────────── */
        @media (max-width: 640px) {
            .orders-wrapper { margin: 20px auto 56px; padding: 0 12px; }
            .page-title-bar { padding: 0 16px; }
            .orders-card-header { padding: 14px 16px; }

            .table-responsive { overflow-x: unset; }
            .orders-table          { min-width: unset; }
            .orders-table,
            .orders-table thead,
            .orders-table tbody,
            .orders-table th,
            .orders-table td,
            .orders-table tr       { display: block; }

            /* Hide table header row */
            .orders-table thead tr { display: none; }

            /* Each row becomes a card */
            .orders-table tbody tr {
                border: 1px solid #2a2a2a;
                border-radius: 12px;
                margin: 0 0 12px;
                padding: 4px 4px 0;
                background: #252525;
                overflow: hidden;
            }
            .orders-table tbody tr:last-child { margin-bottom: 0; }
            .orders-table tbody tr:hover td   { background: transparent; }

            /* Each cell: label + value side by side */
            .orders-table tbody td {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 9px 14px;
                border-bottom: 1px solid #2a2a2a;
                font-size: 13px;
                text-align: right;
            }
            .orders-table tbody td:last-child { border-bottom: none; }

            /* Auto-label from data-label attribute */
            .orders-table tbody td::before {
                content: attr(data-label);
                font-weight: 800;
                font-size: 10px;
                text-transform: uppercase;
                letter-spacing: 0.6px;
                color: #666;
                flex-shrink: 0;
                margin-right: 12px;
                text-align: left;
            }

            .orders-table .product-name { font-size: 13px; }
            .cancel-btn { padding: 6px 12px; font-size: 12px; }
        }

        @media (max-width: 768px) {
            .nav-toggle { display: inline-flex; }
            .navbar > ul { display:none; }
            header.nav-open .navbar > ul { display:flex !important; position:absolute !important; top:0 !important; left:0 !important; width:220px !important; max-height:70vh; overflow-y:auto; flex-direction:column; gap:6px; margin:0; padding:8px 0; background:#333333 !important; box-shadow:0 8px 28px rgba(0,0,0,0.18) !important; border-radius:10px !important; z-index:1400 !important; list-style:none; border:1px solid #000 !important; text-align:center; }
            header.nav-open .navbar > ul li { padding:10px 12px; border-radius:6px; }
            header.nav-open .navbar > ul li a { display:block; padding:6px 8px; }
        }

        header.nav-open .nav-toggle .bar:nth-child(1) { transform:translateY(6px) rotate(45deg); }
        header.nav-open .nav-toggle .bar:nth-child(2) { opacity:0; }
        header.nav-open .nav-toggle .bar:nth-child(3) { transform:translateY(-6px) rotate(-45deg); }
        .navbar > ul > li { margin-right:0 !important; text-align:center !important; display:block; }

        /* ── Cancel Confirmation Modal ────────────────────────── */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.65);
            z-index: 3000;
            backdrop-filter: blur(3px);
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .modal-overlay.active { display: flex; }

        .modal-card {
            background: #1e1e1e;
            border-radius: 20px;
            border: 1px solid #2e2e2e;
            box-shadow: 0 12px 56px rgba(0,0,0,0.5);
            width: 100%;
            max-width: 460px;
            overflow: hidden;
            animation: modalIn 0.28s cubic-bezier(0.22,1,0.36,1) both;
        }
        @keyframes modalIn {
            from { opacity:0; transform: scale(0.93) translateY(16px); }
            to   { opacity:1; transform: scale(1) translateY(0); }
        }

        .modal-icon-area {
            padding: 36px 24px 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 16px;
        }
        .modal-icon-ring {
            width: 80px; height: 80px;
            border-radius: 50%;
            background: rgba(217,83,79,0.12);
            border: 2.5px solid #d9534f;
            display: flex; align-items: center; justify-content: center;
            animation: ringPop 0.5s ease both;
        }
        @keyframes ringPop {
            0%   { transform: scale(0.7); opacity: 0; }
            65%  { transform: scale(1.08); opacity: 1; }
            100% { transform: scale(1); }
        }
        .modal-title {
            font-size: 22px; font-weight: 900;
            letter-spacing: -0.5px; color: #fff;
            text-align: center;
        }
        .modal-subtitle {
            font-size: 14px; color: #888;
            text-align: center; line-height: 1.5;
            margin-top: -6px;
        }

        .modal-detail {
            margin: 0 24px;
            padding: 12px 16px;
            border-radius: 10px;
            background: rgba(217,83,79,0.08);
            border: 1px solid rgba(217,83,79,0.25);
            color: #d9534f;
            font-size: 13px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .modal-detail strong { color: #fff; }

        .modal-divider {
            height: 1px;
            background: linear-gradient(90deg, transparent, #2e2e2e, transparent);
            margin: 20px 0;
        }

        .modal-actions {
            padding: 0 24px 24px;
            display: flex;
            gap: 10px;
            flex-direction: column;
        }
        @media (min-width: 400px) {
            .modal-actions { flex-direction: row; }
        }

        .modal-btn-confirm {
            flex: 1;
            display: flex; align-items: center; justify-content: center; gap: 7px;
            padding: 13px;
            background: linear-gradient(135deg, #d9534f, #b52b27);
            color: #fff;
            border: none; border-radius: 11px;
            font-size: 14px; font-weight: 800;
            cursor: pointer;
            transition: opacity 0.2s, transform 0.1s;
            font-family: 'Inter', sans-serif;
            width: 100%;
        }
        .modal-btn-confirm:hover  { opacity: 0.9; }
        .modal-btn-confirm:active { transform: scale(0.98); }

        .modal-btn-keep {
            flex: 1;
            display: flex; align-items: center; justify-content: center; gap: 7px;
            padding: 12px;
            background: transparent;
            color: #aaa;
            border: 1.5px solid #2e2e2e; border-radius: 11px;
            font-size: 14px; font-weight: 700;
            cursor: pointer;
            transition: border-color 0.2s, color 0.2s;
            font-family: 'Inter', sans-serif;
            width: 100%;
        }
        .modal-btn-keep:hover { border-color: #ed8d1b; color: #ed8d1b; }
        
        @media (max-width: 480px){
            body{
                padding-top: 70px !important;
            }
        }
        
        @media (max-width: 360px){
            body{
                padding-top: 64px !important;
            }
        }
    </style>
</head>

<body style="background:#f0f0f0;">

<!-- ── Cancel Confirmation Modal ──────────────────────────────── -->
<!-- ── Write Review Modal ──────────────────────────────────────── -->
<div id="reviewModal" class="review-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="reviewModalTitle">
    <div class="review-modal-card">
        <div class="review-modal-header">
            <h3 id="reviewModalTitle">Write a <span style="color:#ed8d1b;">Review</span></h3>
            <p>Sharing your experience for <span id="reviewProductName">your order</span></p>
        </div>
        <div class="review-modal-body">
            <form method="POST" action="Orders.php" id="reviewForm">
                <input type="hidden" name="action" value="write_review">
                <input type="hidden" name="order_id" id="rv_order_id" value="">
                <input type="hidden" name="rv_rating" id="rv_rating_input" value="5">

                <label class="rv-field-label">Your Rating</label>
                <div class="star-rating-input" id="starRating">
                    <button type="button" class="star-btn lit" data-val="1">&#9733;</button>
                    <button type="button" class="star-btn lit" data-val="2">&#9733;</button>
                    <button type="button" class="star-btn lit" data-val="3">&#9733;</button>
                    <button type="button" class="star-btn lit" data-val="4">&#9733;</button>
                    <button type="button" class="star-btn lit" data-val="5">&#9733;</button>
                </div>

                <label class="rv-field-label" for="rv_text_area">Your Review <span style="color:#e05;">*</span></label>
                <textarea
                    id="rv_text_area"
                    name="rv_text"
                    class="rv-textarea"
                    placeholder="Tell us about your experience with this product..."
                    required
                ></textarea>

                <label class="rv-field-label" for="rv_label_input">Display Name <span style="font-weight:400;text-transform:none;font-size:10px;">(shown publicly, e.g. "Verified Customer" or "Homeowner")</span></label>
                <input
                    type="text"
                    id="rv_label_input"
                    name="rv_label"
                    class="rv-input-text"
                    value="Verified Customer"
                    placeholder="Verified Customer"
                >

                <div class="review-modal-actions">
                    <button type="button" class="rv-cancel-btn" onclick="closeReviewModal()">Cancel</button>
                    <button type="submit" class="rv-submit-btn">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-right:5px;"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                        Submit Review
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="cancelModal" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
    <div class="modal-card">
        <div class="modal-icon-area">
            <div class="modal-icon-ring">
                <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#d9534f" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="3 6 5 6 21 6"/>
                    <path d="M19 6l-1 14H6L5 6"/>
                    <path d="M10 11v6M14 11v6"/>
                    <path d="M9 6V4h6v2"/>
                </svg>
            </div>
            <div id="modalTitle" class="modal-title">Cancel Order?</div>
            <p class="modal-subtitle">This action cannot be undone. Your order will be permanently removed.</p>
        </div>

        <div class="modal-detail">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/></svg>
            Order <strong id="modalOrderLabel">&nbsp;&mdash;&nbsp;</strong> will be canceled.
        </div>

        <div class="modal-divider"></div>

        <div class="modal-actions">
            <form method="post" action="Orders.php" style="flex:1;display:flex;">
                <input type="hidden" name="orderID" id="modalOrderID" value="">
                <button type="submit" name="confirm_cancel" value="1" class="modal-btn-confirm">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/></svg>
                    Yes, Cancel Order
                </button>
            </form>
            <button type="button" class="modal-btn-keep" onclick="closeCancelModal()">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                No, Keep It
            </button>
        </div>
    </div>
</div>

<!-- ── Page title bar ─────────────────────────────────────────── -->
<div class="page-title-bar">
    <h1>My Orders</h1>
    <a href="Products.php">← Continue Shopping</a>
</div>

<div class="orders-wrapper">

    <!-- Flash messages -->
    <?php if (isset($_SESSION['flash_success'])): ?>
        <div class="flash success">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
            <?= htmlspecialchars($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['flash_error'])): ?>
        <div class="flash error">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <?= htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?>
        </div>
    <?php endif; ?>

    <?php if (empty($orders)): ?>
        <!-- Empty state -->
        <div class="empty-orders">
            <img src="No order.png" alt="No orders">
            <p>You have no ongoing orders yet.</p>
            <a href="Products.php">Browse Products</a>
        </div>

    <?php else: ?>
        <!-- Orders card -->
        <div class="orders-card">
            <div class="orders-card-header">
                <h2>Order History</h2>
                <span class="order-count"><?= count($orders) ?> order<?= count($orders) !== 1 ? 's' : '' ?></span>
            </div>

            <div class="table-responsive">
                <table class="orders-table">
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Date</th>
                            <th>Product</th>
                            <th>Qty</th>
                            <th>Unit Price (₱)</th>
                            <th>Total (₱)</th>
                            <th>Shipping Address</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td data-label="Order #">
                                <span class="order-id-badge">#<?= $order['OrderID'] ?></span>
                            </td>
                            <td data-label="Date"><?= htmlspecialchars($order['OrderDate']) ?></td>
                            <td data-label="Product">
                                <span class="product-name"><?= htmlspecialchars($order['ProductName']) ?></span>
                            </td>
                            <td data-label="Qty">
                                <span class="qty-badge"><?= $order['Quantity'] ?></span>
                            </td>
                            <td data-label="Unit Price">₱<?= number_format($order['Amount'], 2) ?></td>
                            <td data-label="Total" class="amount-cell">₱<?= number_format($order['Total'], 2) ?></td>
                            <td data-label="Shipping"><?= htmlspecialchars($order['ShippingAddress']) ?></td>
                            <td data-label="Status">
                                <?php $st = $order['OrderStatus'] ?? 'Pending'; ?>
                                <span class="status-badge status-<?= htmlspecialchars($st) ?>">
                                    <?= htmlspecialchars($st) ?>
                                </span>
                            </td>
                            <td data-label="Actions" style="display:flex;flex-wrap:wrap;gap:6px;align-items:center;">
                                <?php if (!in_array($order['OrderStatus'] ?? 'Pending', ['Paid','Shipped','Delivered','Cancelled'])): ?>
                                <button
                                    class="cancel-btn"
                                    onclick="openCancelModal(<?= $order['OrderID'] ?>)"
                                    type="button"
                                >
                                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/></svg>
                                    Cancel
                                </button>
                                <?php endif; ?>
                                <?php
                                $alreadyReviewed = in_array($order['OrderID'], $_SESSION['reviewed_orders'][$customerID] ?? []);
                                if (($order['OrderStatus'] ?? '') === 'Delivered' && !$alreadyReviewed): ?>
                                <button
                                    class="review-btn"
                                    onclick="openReviewModal(<?= $order['OrderID'] ?>, '<?= addslashes(htmlspecialchars($order['ProductName'])) ?>')"
                                    type="button"
                                >
                                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                                    Write Review
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php endif; ?>

</div><!-- /.orders-wrapper -->

<script src="assets/bootstrap/js/bootstrap.min.js"></script>
<script>

  // ── Review Modal ──────────────────────────────────────────────
  let currentRating = 5;

  function openReviewModal(orderId, productName) {
    document.getElementById('reviewProductName').textContent = productName || 'your order';
    document.getElementById('rv_order_id').value    = orderId;
    document.getElementById('rv_text_area').value   = '';
    document.getElementById('rv_label_input').value = 'Verified Customer';
    setStars(5);
    document.getElementById('reviewModal').classList.add('active');
    document.body.style.overflow = 'hidden';
  }

  function closeReviewModal() {
    document.getElementById('reviewModal').classList.remove('active');
    document.body.style.overflow = '';
  }

  function setStars(val) {
    currentRating = val;
    document.getElementById('rv_rating_input').value = val;
    document.querySelectorAll('#starRating .star-btn').forEach(btn => {
      btn.classList.toggle('lit', parseInt(btn.dataset.val) <= val);
    });
  }

  document.querySelectorAll('#starRating .star-btn').forEach(btn => {
    btn.addEventListener('click', () => setStars(parseInt(btn.dataset.val)));
    btn.addEventListener('mouseover', () => {
      document.querySelectorAll('#starRating .star-btn').forEach(b => {
        b.classList.toggle('lit', parseInt(b.dataset.val) <= parseInt(btn.dataset.val));
      });
    });
    btn.addEventListener('mouseout', () => setStars(currentRating));
  });

  document.getElementById('reviewModal').addEventListener('click', function(e) {
    if (e.target === this) closeReviewModal();
  });

  // ── Cancel Modal ──────────────────────────────────────────────
  function openCancelModal(orderId) {
    document.getElementById('modalOrderID').value    = orderId;
    document.getElementById('modalOrderLabel').textContent = ' #' + orderId + ' ';
    document.getElementById('cancelModal').classList.add('active');
    document.body.style.overflow = 'hidden';
  }

  function closeCancelModal() {
    document.getElementById('cancelModal').classList.remove('active');
    document.body.style.overflow = '';
  }

  // Close on backdrop click
  document.getElementById('cancelModal').addEventListener('click', function(e) {
    if (e.target === this) closeCancelModal();
  });

  // Close on Escape key
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeCancelModal();
  });
</script>

<?php require 'footer.php'; ?>
</html>