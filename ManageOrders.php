<?php
ob_start();
// ── Temporary: show errors instead of blank page ──────────────────────────────
ini_set('display_errors', 1);
error_reporting(E_ALL);
// ─────────────────────────────────────────────────────────────────────────────
session_start();
include 'db_connect.php';

// ── Admin guard ────────────────────────────────────────────────────
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: Login&Signup.php");
    exit;
}

// ── Handle status update ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    $orderID = (int)($_POST['orderID'] ?? 0);

    if ($_POST['action'] === 'update_status' && !empty($_POST['new_status'])) {
        $allowed = ['Pending','Processing','Paid','Shipped','Delivered','Cancelled'];
        $status  = in_array($_POST['new_status'], $allowed) ? $_POST['new_status'] : null;
        if ($status && $orderID) {
            // Prevent any change if the order is already Cancelled
            $chk = $pdo->prepare("SELECT OrderStatus FROM orders WHERE OrderID = ?");
            $chk->execute([$orderID]);
            if (in_array($chk->fetchColumn(), ['Cancelled', 'Delivered'])) {
                $_SESSION['flash_error'] = "Order #$orderID is cancelled and cannot be updated.";
                header("Location: ManageOrders.php");
                exit;
            }
            if ($status === 'Paid') {
                $payMethod = trim($_POST['payment_method'] ?? '');
                $payRef    = trim($_POST['payment_ref']    ?? '');
                $datePaid  = trim($_POST['date_paid']      ?? '') ?: date('Y-m-d');
                $stmt = $pdo->prepare("UPDATE orders SET OrderStatus = ?, PaymentMethod = ?, PaymentReference = ?, DatePaid = ? WHERE OrderID = ?");
                $stmt->execute([$status, $payMethod, $payRef, $datePaid, $orderID]);
            } else {
                $stmt = $pdo->prepare("UPDATE orders SET OrderStatus = ? WHERE OrderID = ?");
                $stmt->execute([$status, $orderID]);
            }
            $_SESSION['flash_success'] = "Order #$orderID status updated to \"$status\".";
        }

    } elseif ($_POST['action'] === 'cancel_order' && $orderID) {
        $stmt = $pdo->prepare("DELETE FROM orders WHERE OrderID = ?");
        $stmt->execute([$orderID]);
        $_SESSION['flash_success'] = "Order #$orderID has been cancelled and removed.";
    }

    header("Location: ManageOrders.php");
    exit;
}

require 'header.php';

// ── Filter / Search / Pagination ───────────────────────────────────
$search      = trim($_GET['search'] ?? '');
$statusFilter= $_GET['status'] ?? '';
$perPage     = 20;
$page        = max(1, (int)($_GET['page'] ?? 1));
$offset      = ($page - 1) * $perPage;

$validStatuses = ['Pending','Processing','Paid','Shipped','Delivered','Cancelled'];

// Build WHERE
$whereParts  = [];
$params      = [];

if ($search !== '') {
    $whereParts[] = "(o.OrderID LIKE :search OR c.FirstName LIKE :search OR c.LastName LIKE :search OR c.Email LIKE :search)";
    $params[':search'] = "%$search%";
}
if ($statusFilter !== '' && in_array($statusFilter, $validStatuses)) {
    $whereParts[] = "o.OrderStatus = :status";
    $params[':status'] = $statusFilter;
}

$whereSQL = $whereParts ? 'WHERE ' . implode(' AND ', $whereParts) : '';

// ── Fetch orders (try/catch so a DB error shows a message instead of blank page)
$totalOrders  = 0;
$totalPages   = 1;
$orders       = [];
$statusCounts = [];
$totalCount   = 0;
$dbError      = null;

try {
    // Count
    $countSQL  = "SELECT COUNT(*) FROM orders o
                  LEFT JOIN customer c ON o.CustomerID = c.CustomerID
                  $whereSQL";
    $countStmt = $pdo->prepare($countSQL);
    $countStmt->execute($params);
    $totalOrders = (int)$countStmt->fetchColumn();
    $totalPages  = max(1, ceil($totalOrders / $perPage));

    // Fetch page
    $sql = "SELECT o.*, c.FirstName, c.LastName, c.Email
            FROM orders o
            LEFT JOIN customer c ON o.CustomerID = c.CustomerID
            $whereSQL
            ORDER BY o.OrderDate DESC
            LIMIT :offset, :perPage";
    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':perPage', $perPage, PDO::PARAM_INT);
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $dbError = 'Could not load orders: ' . $e->getMessage();
}

// Map ProductID → ProductName
$tables = ['productsmedian','productssophisticated','productsluxurious'];
foreach ($orders as &$order) {
    $productName = null;
    foreach ($tables as $table) {
        try {
            $s = $pdo->prepare("SELECT ProductName FROM $table WHERE ProductID = ?");
            $s->execute([$order['ProductID'] ?? 0]);
            $result = $s->fetch(PDO::FETCH_ASSOC);
            if ($result && !empty($result['ProductName'])) { $productName = $result['ProductName']; break; }
        } catch (PDOException $e) { /* table may not exist yet */ }
    }
    $order['ProductName'] = $productName ?? 'N/A';
}
unset($order);

// Status counts for summary chips
try {
    $summaryStmt = $pdo->query("SELECT OrderStatus, COUNT(*) as cnt FROM orders GROUP BY OrderStatus");
    while ($row = $summaryStmt->fetch(PDO::FETCH_ASSOC)) {
        $statusCounts[$row['OrderStatus']] = $row['cnt'];
    }
    $totalCount = array_sum($statusCounts);
} catch (PDOException $e) {
    if (!$dbError) $dbError = 'Could not load status counts: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en" style="background:#f0f0f0;">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage Orders — Admin</title>
    <link rel="icon" type="image/ico" href="Favicon.ico">
    <link rel="stylesheet" href="assets/bootstrap/css/bootstrap.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">
    <style>
        /* ── Base ─────────────────────────────────────────────── */
        body {
            font-family: 'Inter', sans-serif;
            -webkit-font-smoothing: antialiased;
            background: #f0f0f0;
        }
        a { text-decoration: none; }

        @media (max-width: 480px) { body { padding-top: 70px !important; } }
        @media (max-width: 360px) { body { padding-top: 64px !important; } }

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
            box-sizing: border-box;
        }
        .page-title-bar h1 {
            color: #fff;
            font-size: clamp(16px, 4vw, 28px);
            font-weight: 800;
            padding: 16px 0;
            letter-spacing: -0.4px;
            margin: 0;
        }
        .title-badge {
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(237,141,27,0.15);
            border: 1.5px solid rgba(237,141,27,0.4);
            border-radius: 8px;
            padding: 6px 14px;
            color: #ed8d1b;
            font-size: 12px;
            font-weight: 800;
            white-space: nowrap;
        }
        .title-badge svg { flex-shrink: 0; }

        /* ── Main wrapper ─────────────────────────────────────── */
        .manage-wrapper {
            max-width: 1600px;
            margin: 28px auto 80px;
            padding: 0 20px;
        }

        /* ── Flash ────────────────────────────────────────────── */
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

        /* ── Summary chips ────────────────────────────────────── */
        .summary-row {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 22px;
        }
        .summary-chip {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            background: #1e1e1e;
            border: 1px solid #2e2e2e;
            border-radius: 12px;
            padding: 12px 18px;
            min-width: 110px;
            flex: 1;
            box-shadow: 0 2px 10px rgba(0,0,0,0.15);
            cursor: pointer;
            text-decoration: none;
            transition: border-color 0.2s, transform 0.15s;
        }
        .summary-chip:hover { border-color: #ed8d1b; transform: translateY(-2px); }
        .summary-chip.active { border-color: #ed8d1b; background: rgba(237,141,27,0.08); }
        .summary-chip .chip-count {
            font-size: 26px;
            font-weight: 900;
            color: #fff;
            line-height: 1.1;
        }
        .summary-chip .chip-label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.7px;
            margin-top: 4px;
        }
        .chip-all .chip-label    { color: #aaa; }
        .chip-pending .chip-label { color: #f0ad4e; }
        .chip-processing .chip-label { color: #5bc0de; }
        .chip-paid .chip-label    { color: #00bcd4; }
        .chip-shipped .chip-label { color: #a78bfa; }
        .chip-delivered .chip-label { color: #27ae60; }
        .chip-cancelled .chip-label { color: #e74c3c; }

        /* ── Toolbar: search + filter ─────────────────────────── */
        .toolbar {
            display: flex;
            gap: 10px;
            margin-bottom: 18px;
            flex-wrap: wrap;
            align-items: center;
        }
        .toolbar-search {
            display: flex;
            flex: 1;
            min-width: 200px;
        }
        .toolbar-search input {
            flex: 1;
            height: 42px;
            padding: 0 16px;
            background: #1e1e1e;
            border: 2px solid #2e2e2e;
            border-right: none;
            border-radius: 10px 0 0 10px;
            color: #fff;
            font-size: 14px;
            font-weight: 600;
            font-family: 'Inter', sans-serif;
            outline: none;
            transition: border-color 0.2s;
        }
        .toolbar-search input::placeholder { color: #666; }
        .toolbar-search input:focus { border-color: #ed8d1b; }
        .toolbar-search button {
            width: 44px; height: 42px;
            background: #ed8d1b;
            border: none;
            border-radius: 0 10px 10px 0;
            cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            transition: background 0.2s;
            flex-shrink: 0;
        }
        .toolbar-search button:hover { background: #c97415; }
        .toolbar-search button svg { color: #fff; }

        .toolbar select {
            height: 42px;
            padding: 0 12px;
            background: #1e1e1e;
            border: 2px solid #2e2e2e;
            border-radius: 10px;
            color: #ccc;
            font-size: 14px;
            font-weight: 600;
            font-family: 'Inter', sans-serif;
            cursor: pointer;
            outline: none;
            min-width: 160px;
        }
        .toolbar select:focus { border-color: #ed8d1b; }

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
            flex-wrap: wrap;
        }
        .orders-card-header h2 {
            color: #ed8d1b;
            font-size: clamp(15px, 3vw, 19px);
            font-weight: 900;
            letter-spacing: -0.4px;
            margin: 0;
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

        /* ── Table ────────────────────────────────────────────── */
        .table-responsive {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: thin;
            scrollbar-color: #ed8d1b #1a1a1a;
        }
        .table-responsive::-webkit-scrollbar {
            height: 6px;
        }
        .table-responsive::-webkit-scrollbar-track {
            background: #1a1a1a;
            border-radius: 0 0 16px 16px;
        }
        .table-responsive::-webkit-scrollbar-thumb {
            background: linear-gradient(90deg, #ed8d1b, #c97415);
            border-radius: 10px;
        }
        .table-responsive::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(90deg, #f5a340, #ed8d1b);
        }
        .orders-table {
            width: 100%;
            min-width: 760px;
            border-collapse: collapse;
        }
        .orders-table thead tr { background: #252525; }
        .orders-table thead th {
            padding: 11px 14px;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #888;
            text-align: left;
            border-bottom: 1px solid #2e2e2e;
            white-space: nowrap;
        }
        .orders-table tbody tr { border-bottom: 1px solid #252525; transition: background 0.15s; }
        .orders-table tbody tr:last-child { border-bottom: none; }
        .orders-table tbody tr:hover td { background: rgba(255,255,255,0.025); }
        .orders-table tbody td {
            padding: 13px 14px;
            font-size: 13px;
            color: #ccc;
            font-weight: 600;
            vertical-align: middle;
        }

        /* ── Cell types ───────────────────────────────────────── */
        .order-id-badge {
            display: inline-flex;
            align-items: center;
            background: rgba(237,141,27,0.12);
            color: #ed8d1b;
            font-size: 12px;
            font-weight: 800;
            padding: 4px 9px;
            border-radius: 7px;
            border: 1px solid rgba(237,141,27,0.25);
            white-space: nowrap;
        }
        .customer-cell { display: flex; flex-direction: column; gap: 2px; }
        .customer-name { color: #fff; font-weight: 700; font-size: 13px; }
        .customer-email { color: #777; font-size: 11px; font-weight: 600; }
        .product-name { color: #e0e0e0; font-weight: 600; font-size: 13px; }
        .qty-badge {
            display: inline-flex; align-items: center; justify-content: center;
            width: 28px; height: 28px;
            background: #2a2a2a;
            border: 1px solid #3a3a3a;
            border-radius: 6px;
            color: #ccc; font-weight: 800; font-size: 12px;
        }
        .amount-cell { color: #ed8d1b; font-weight: 800; font-size: 13px; }
        .date-cell { color: #ccc; font-size: 12px; font-weight: 600; white-space: nowrap; }
        .address-cell { color: #999; font-size: 12px; max-width: 160px; }

        /* ── Status badge ─────────────────────────────────────── */
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.3px;
            white-space: nowrap;
        }
        .status-Pending    { background: rgba(240,173,78,0.15);  color: #f0ad4e; border: 1px solid rgba(240,173,78,0.3); }
        .status-Processing { background: rgba(91,192,222,0.15);  color: #5bc0de; border: 1px solid rgba(91,192,222,0.3); }
        .status-Paid       { background: rgba(0,188,212,0.15);   color: #00bcd4; border: 1px solid rgba(0,188,212,0.3); }
        .status-Shipped    { background: rgba(167,139,250,0.15); color: #a78bfa; border: 1px solid rgba(167,139,250,0.3); }
        .status-Delivered  { background: rgba(39,174,96,0.15);   color: #2ecc71; border: 1px solid rgba(39,174,96,0.3); }
        .status-Cancelled  { background: rgba(231,76,60,0.15);   color: #e74c3c; border: 1px solid rgba(231,76,60,0.3); }

        /* ── Payment info cells ───────────────────────────────── */
        .payment-ref {
            display: inline-block;
            background: rgba(0,188,212,0.12);
            border: 1px solid rgba(0,188,212,0.3);
            color: #00bcd4;
            font-size: 11px;
            font-weight: 800;
            padding: 3px 9px;
            border-radius: 6px;
            font-family: 'Inter', monospace;
            white-space: nowrap;
        }
        .payment-method-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 10px;
            background: rgba(0,188,212,0.08);
            border: 1px solid rgba(0,188,212,0.25);
            color: #80deea;
            font-size: 11px;
            font-weight: 700;
            border-radius: 6px;
            white-space: nowrap;
        }
        .payment-na { color: #444; font-size: 12px; font-style: italic; }

        /* ── Actions cell ─────────────────────────────────────── */
        .actions-cell { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }

        .status-select-form { display: flex; gap: 6px; align-items: center; }
        .status-select {
            height: 34px;
            padding: 0 8px;
            background: #252525;
            border: 1.5px solid #3a3a3a;
            border-radius: 8px;
            color: #ccc;
            font-size: 12px;
            font-weight: 700;
            font-family: 'Inter', sans-serif;
            cursor: pointer;
            outline: none;
            min-width: 130px;
            transition: border-color 0.2s;
        }
        .status-select:focus { border-color: #ed8d1b; }

        .btn-update {
            height: 34px;
            padding: 0 12px;
            background: #ed8d1b;
            border: none;
            border-radius: 8px;
            color: #151616;
            font-size: 12px;
            font-weight: 800;
            cursor: pointer;
            white-space: nowrap;
            font-family: 'Inter', sans-serif;
            transition: background 0.2s, transform 0.1s;
            display: flex; align-items: center; gap: 5px;
        }
        .btn-update:hover  { background: #c97415; }
        .btn-update:active { transform: scale(0.97); }

        .btn-cancel {
            height: 34px;
            padding: 0 10px;
            background: transparent;
            border: 1.5px solid #3a3a3a;
            border-radius: 8px;
            color: #e74c3c;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            font-family: 'Inter', sans-serif;
            transition: border-color 0.2s, background 0.2s;
            display: flex; align-items: center; gap: 5px;
            white-space: nowrap;
        }
        .btn-cancel:hover { border-color: #e74c3c; background: rgba(231,76,60,0.08); }

        /* ── Empty state ──────────────────────────────────────── */
        .empty-orders {
            text-align: center;
            padding: 60px 20px;
        }
        .empty-orders svg { opacity: 0.3; margin-bottom: 16px; }
        .empty-orders p { color: #888; font-size: 16px; font-weight: 700; }

        /* ── Pagination ───────────────────────────────────────── */
        .pagination {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            align-items: center;
            margin: 28px 0 0;
            gap: 6px;
        }
        .pagination a, .pagination span {
            display: flex; justify-content: center; align-items: center;
            width: 36px; height: 36px;
            border: 2px solid #d0d0d0;
            border-radius: 8px;
            font-size: 13px; font-weight: 700;
            color: #555;
            background: #fff;
            text-decoration: none;
            transition: all 0.15s;
        }
        .pagination a:hover { background: #ed8d1b; color: #fff; border-color: #ed8d1b; }
        .pagination span.active { background: #ed8d1b; color: #fff; border-color: #ed8d1b; font-weight: 800; }
        .pagination span.dots { background: transparent; border: none; color: #666; font-size: 16px; }

        /* ── Cancel confirmation modal ────────────────────────── */
        .modal-overlay {
            display: none;
            position: fixed; inset: 0;
            background: rgba(0,0,0,0.72);
            backdrop-filter: blur(4px);
            z-index: 9999;
            align-items: center; justify-content: center;
            padding: 16px;
        }
        .modal-overlay.active { display: flex; }
        .modal-card {
            background: #1a1a1a;
            border: 1px solid #2e2e2e;
            border-radius: 18px;
            padding: 32px 28px 24px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 24px 64px rgba(0,0,0,0.6);
        }
        .modal-icon-area { text-align: center; margin-bottom: 20px; }
        .modal-icon-ring {
            width: 68px; height: 68px;
            background: rgba(217,83,79,0.12);
            border: 2px solid rgba(217,83,79,0.3);
            border-radius: 50%;
            display: inline-flex; align-items: center; justify-content: center;
            margin-bottom: 14px;
        }
        .modal-title {
            color: #fff; font-size: 20px; font-weight: 900;
            letter-spacing: -0.4px; margin-bottom: 6px;
        }
        .modal-subtitle {
            color: #888; font-size: 13px; font-weight: 500; margin: 0; line-height: 1.5;
        }
        .modal-detail {
            display: flex; align-items: center; gap: 8px;
            background: #252525; border: 1px solid #2e2e2e;
            border-radius: 10px; padding: 12px 14px;
            color: #aaa; font-size: 13px; font-weight: 600;
            margin-top: 18px;
        }
        .modal-detail strong { color: #fff; }
        .modal-divider { height: 1px; background: #2e2e2e; margin: 20px 0; }
        .modal-actions { display: flex; gap: 10px; }
        .modal-btn-confirm {
            flex: 1; display: flex; align-items: center; justify-content: center; gap: 7px;
            padding: 13px;
            background: #d9534f; color: #fff;
            border: none; border-radius: 11px;
            font-size: 14px; font-weight: 800; cursor: pointer;
            transition: opacity 0.2s, transform 0.1s;
            font-family: 'Inter', sans-serif; width: 100%;
        }
        .modal-btn-confirm:hover  { opacity: 0.9; }
        .modal-btn-confirm:active { transform: scale(0.98); }
        .modal-btn-keep {
            flex: 1; display: flex; align-items: center; justify-content: center; gap: 7px;
            padding: 13px;
            background: transparent; color: #aaa;
            border: 1.5px solid #2e2e2e; border-radius: 11px;
            font-size: 14px; font-weight: 700; cursor: pointer;
            transition: border-color 0.2s, color 0.2s;
            font-family: 'Inter', sans-serif; width: 100%;
        }
        .modal-btn-keep:hover { border-color: #ed8d1b; color: #ed8d1b; }

        /* ── Payment Details Modal ────────────────────────────── */
        .pay-modal-overlay {
            display: none;
            position: fixed; inset: 0;
            background: rgba(0,0,0,0.75);
            backdrop-filter: blur(4px);
            z-index: 10000;
            align-items: center; justify-content: center;
            padding: 16px;
        }
        .pay-modal-overlay.active { display: flex; }
        .pay-modal-card {
            background: #1a1a1a;
            border: 1px solid #2e2e2e;
            border-radius: 18px;
            padding: 28px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 24px 64px rgba(0,0,0,0.6);
            animation: modalIn 0.25s cubic-bezier(0.22,1,0.36,1) both;
        }
        @keyframes modalIn {
            from { opacity:0; transform: scale(0.94) translateY(12px); }
            to   { opacity:1; transform: scale(1) translateY(0); }
        }
        .pay-modal-header {
            border-bottom: 2px solid #00bcd4;
            padding-bottom: 14px;
            margin-bottom: 20px;
        }
        .pay-modal-header h3 {
            color: #fff; font-size: 18px; font-weight: 900;
            letter-spacing: -0.4px; margin: 0 0 4px;
        }
        .pay-modal-header p { color: #888; font-size: 13px; margin: 0; }
        .pay-modal-header p span { color: #00bcd4; font-weight: 700; }
        .pay-field-label {
            display: block;
            font-size: 10.5px; font-weight: 800;
            text-transform: uppercase; letter-spacing: 0.7px;
            color: #888; margin-bottom: 7px; margin-top: 16px;
        }
        .pay-select, .pay-input {
            width: 100%;
            padding: 10px 13px;
            background: #252525;
            border: 1.5px solid #3a3a3a;
            border-radius: 9px;
            color: #fff;
            font-family: 'Inter', sans-serif;
            font-size: 13px; font-weight: 600;
            outline: none;
            transition: border-color 0.2s;
            box-sizing: border-box;
        }
        .pay-select:focus, .pay-input:focus { border-color: #00bcd4; }
        .pay-input::placeholder { color: #555; }
        .pay-modal-actions { display: flex; gap: 10px; margin-top: 22px; }
        .pay-btn-confirm {
            flex: 1; padding: 12px;
            background: #00bcd4; color: #0a0a0a;
            border: none; border-radius: 10px;
            font-size: 13px; font-weight: 800;
            cursor: pointer; font-family: 'Inter', sans-serif;
            display: flex; align-items: center; justify-content: center; gap: 6px;
            transition: background 0.2s;
        }
        .pay-btn-confirm:hover { background: #0097a7; color: #fff; }
        .pay-btn-back {
            padding: 12px 16px;
            background: transparent; color: #888;
            border: 1.5px solid #2e2e2e; border-radius: 10px;
            font-size: 13px; font-weight: 700;
            cursor: pointer; font-family: 'Inter', sans-serif;
            transition: border-color 0.2s, color 0.2s;
        }
        .pay-btn-back:hover { border-color: #555; color: #ccc; }

        /* ── Responsive ───────────────────────────────────────── */
        @media (max-width: 900px) {
            .manage-wrapper { padding: 0 14px; }
        }
        @media (max-width: 640px) {
            .toolbar { flex-direction: column; align-items: stretch; }
            .toolbar-search { min-width: unset; }
            .toolbar select { width: 100%; }
            .summary-chip .chip-count { font-size: 20px; }
            .summary-chip { padding: 10px 7px; min-width: 80px;  align-items: center; }
            .actions-cell { flex-direction: column; align-items: flex-start; }
            .status-select-form { width: 100%; }
            .status-select { flex: 1; min-width: unset; }
            .summary-row {gap: 12px;}
        }
        @media (max-width: 480px) {
            .page-title-bar { padding: 0 16px; }
            .manage-wrapper { padding: 0 10px; margin-top: 18px; }
            .orders-card-header { padding: 14px 16px; }
        }
    </style>
</head>

<body style="background:#f0f0f0;">

<!-- ── Payment Details Modal ───────────────────────────────────── -->
<div id="payModal" class="pay-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="payModalTitle">
    <div class="pay-modal-card">
        <div class="pay-modal-header">
            <h3 id="payModalTitle">💳 Mark as <span style="color:#00bcd4;">Paid</span></h3>
            <p>Enter payment details for Order <span id="payModalOrderLabel">#—</span></p>
        </div>
        <form method="POST" action="ManageOrders.php" id="payModalForm">
            <input type="hidden" name="action"     value="update_status">
            <input type="hidden" name="new_status" value="Paid">
            <input type="hidden" name="orderID"    id="payModalOrderID" value="">

            <label class="pay-field-label" for="pay_method">Payment Method <span style="color:#e05;">*</span></label>
            <select name="payment_method" id="pay_method" class="pay-select" required>
                <option value="" disabled selected>— Select method —</option>
                <option value="Cash">💵 Cash</option>
                <option value="GCash">📱 GCash</option>
                <option value="Bank Transfer">🏦 Bank Transfer</option>
                <option value="Bank Deposit / Cheque">🏧 Bank Deposit / Cheque</option>
                <option value="Credit Card">💳 Credit Card</option>
            </select>

            <label class="pay-field-label" for="pay_ref">Reference # <span style="color:#888;font-weight:400;text-transform:none;font-size:10px;">(optional)</span></label>
            <input
                type="text"
                name="payment_ref"
                id="pay_ref"
                class="pay-input"
                placeholder="e.g. GCash ref, bank transaction #"
                maxlength="100"
            >

            <label class="pay-field-label" for="pay_date">Date Paid <span style="color:#e05;">*</span></label>
            <input
                type="datetime-local"
                name="date_paid"
                id="pay_date"
                class="pay-input"
                required
            >

            <div class="pay-modal-actions">
                <button type="button" class="pay-btn-back" onclick="closePayModal()">← Back</button>
                <button type="submit" class="pay-btn-confirm">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                    Confirm Payment
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ── Cancel Confirmation Modal ──────────────────────────────── -->
<div id="cancelModal" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="adminModalTitle">
    <div class="modal-card">
        <div class="modal-icon-area">
            <div class="modal-icon-ring">
                <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="#d9534f" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="3 6 5 6 21 6"/>
                    <path d="M19 6l-1 14H6L5 6"/>
                    <path d="M10 11v6M14 11v6"/>
                    <path d="M9 6V4h6v2"/>
                </svg>
            </div>
            <div id="adminModalTitle" class="modal-title">Cancel Order?</div>
            <p class="modal-subtitle">This will permanently delete the order from the database.</p>
        </div>
        <div class="modal-detail">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/></svg>
            Order <strong id="modalOrderLabel">&nbsp;—&nbsp;</strong> will be permanently removed.
        </div>
        <div class="modal-divider"></div>
        <div class="modal-actions">
            <form method="post" action="ManageOrders.php" style="flex:1;display:flex;">
                <input type="hidden" name="action"  value="cancel_order">
                <input type="hidden" name="orderID" id="modalOrderID" value="">
                <button type="submit" class="modal-btn-confirm">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/></svg>
                    Yes, Cancel Order
                </button>
            </form>
            <button type="button" class="modal-btn-keep" onclick="closeCancelModal()">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                Keep It
            </button>
        </div>
    </div>
</div>

<!-- ── Page title bar ─────────────────────────────────────────── -->
<div class="page-title-bar">
    <h1>Manage Orders</h1>
    <div class="title-badge">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        Admin Panel
    </div>
</div>

<!-- ── Main content ───────────────────────────────────────────── -->
<div class="manage-wrapper">

    <!-- DB error banner (shows instead of blank page) -->
    <?php if (!empty($dbError)): ?>
        <div class="flash error" style="word-break:break-word;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <strong>Database error:</strong> <?= htmlspecialchars($dbError) ?>
        </div>
    <?php endif; ?>

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

    <!-- Summary chips -->
    <?php
    $chipDefs = [
        ['key' => '', 'label' => 'All Orders', 'class' => 'chip-all', 'count' => $totalCount],
        ['key' => 'Pending',    'label' => 'Pending',    'class' => 'chip-pending',    'count' => $statusCounts['Pending']    ?? 0],
        ['key' => 'Processing', 'label' => 'Processing', 'class' => 'chip-processing', 'count' => $statusCounts['Processing'] ?? 0],
        ['key' => 'Paid',       'label' => 'Paid',       'class' => 'chip-paid',       'count' => $statusCounts['Paid']       ?? 0],
        ['key' => 'Shipped',    'label' => 'Shipped',    'class' => 'chip-shipped',    'count' => $statusCounts['Shipped']    ?? 0],
        ['key' => 'Delivered',  'label' => 'Delivered',  'class' => 'chip-delivered',  'count' => $statusCounts['Delivered']  ?? 0],
        ['key' => 'Cancelled',  'label' => 'Cancelled',  'class' => 'chip-cancelled',  'count' => $statusCounts['Cancelled']  ?? 0],
    ];
    ?>
    <div class="summary-row">
        <?php foreach ($chipDefs as $chip):
            $isActive = ($statusFilter === $chip['key']);
            $href = 'ManageOrders.php?status=' . urlencode($chip['key']) . ($search ? '&search=' . urlencode($search) : '');
        ?>
        <a href="<?= $href ?>" class="summary-chip <?= $chip['class'] ?><?= $isActive ? ' active' : '' ?>">
            <span class="chip-count"><?= $chip['count'] ?></span>
            <span class="chip-label"><?= $chip['label'] ?></span>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Toolbar: search + sort -->
    <form method="GET" action="ManageOrders.php">
        <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
        <div class="toolbar">
            <div class="toolbar-search">
                <input
                    type="text"
                    name="search"
                    placeholder="Search by Order #, customer name or email…"
                    value="<?= htmlspecialchars($search) ?>"
                    autocomplete="off"
                >
                <button type="submit" aria-label="Search">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                </button>
            </div>
            <?php if ($search): ?>
                <a href="ManageOrders.php?status=<?= urlencode($statusFilter) ?>" style="height:42px;display:flex;align-items:center;padding:0 14px;background:#2a2a2a;border:1.5px solid #3a3a3a;border-radius:10px;color:#aaa;font-size:13px;font-weight:700;white-space:nowrap;text-decoration:none;transition:border-color 0.2s;">
                    ✕ Clear
                </a>
            <?php endif; ?>
        </div>
    </form>

    <!-- Orders card -->
    <div class="orders-card">
        <div class="orders-card-header">
            <h2>
                <?= $statusFilter ? htmlspecialchars($statusFilter) . ' Orders' : 'All Orders' ?>
                <?php if ($search): ?><span style="color:#888;font-size:13px;font-weight:600;"> — "<?= htmlspecialchars($search) ?>"</span><?php endif; ?>
            </h2>
            <span class="order-count"><?= $totalOrders ?> order<?= $totalOrders !== 1 ? 's' : '' ?></span>
        </div>

        <?php if (empty($orders)): ?>
            <div class="empty-orders">
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#555" stroke-width="1.5"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/><line x1="9" y1="12" x2="15" y2="12"/><line x1="9" y1="16" x2="12" y2="16"/></svg>
                <p>No orders found<?= $search ? " for &ldquo;$search&rdquo;" : '' ?>.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="orders-table">
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Date</th>
                            <th>Customer</th>
                            <th>Product</th>
                            <th>Qty</th>
                            <th>Unit (₱)</th>
                            <th>Total (₱)</th>
                            <th>Shipping</th>
                            <th>Status</th>
                            <th>Payment Ref #</th>
                            <th>Payment Method</th>
                            <th>Date Paid</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($orders as $order):
                        $currentStatus = $order['OrderStatus'] ?? 'Pending';
                    ?>
                        <tr>
                            <td><span class="order-id-badge">#<?= $order['OrderID'] ?></span></td>
                            <td class="date-cell"><?= htmlspecialchars($order['OrderDate']) ?></td>
                            <td>
                                <div class="customer-cell">
                                    <span class="customer-name">
                                        <?= htmlspecialchars(trim(($order['FirstName'] ?? '') . ' ' . ($order['LastName'] ?? ''))) ?: 'Unknown' ?>
                                    </span>
                                    <span class="customer-email"><?= htmlspecialchars($order['Email'] ?? '') ?></span>
                                </div>
                            </td>
                            <td><span class="product-name"><?= htmlspecialchars($order['ProductName']) ?></span></td>
                            <td><span class="qty-badge"><?= (int)$order['Quantity'] ?></span></td>
                            <td>₱<?= number_format($order['Amount'], 2) ?></td>
                            <td class="amount-cell">₱<?= number_format($order['Total'], 2) ?></td>
                            <td class="address-cell"><?= htmlspecialchars($order['ShippingAddress']) ?></td>
                            <td>
                                <span class="status-badge status-<?= htmlspecialchars($currentStatus) ?>">
                                    <?= htmlspecialchars($currentStatus) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($currentStatus === 'Paid' && !empty($order['PaymentReference'])): ?>
                                    <span class="payment-ref"><?= htmlspecialchars($order['PaymentReference']) ?></span>
                                <?php else: ?>
                                    <span class="payment-na">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $methodIcons = [
                                    'Cash'                  => '💵',
                                    'GCash'                 => '📱',
                                    'Bank Transfer'         => '🏦',
                                    'Bank Deposit / Cheque' => '🏧',
                                    'Credit Card'           => '💳',
                                ];
                                if ($currentStatus === 'Paid' && !empty($order['PaymentMethod'])):
                                    $pm   = htmlspecialchars($order['PaymentMethod']);
                                    $icon = $methodIcons[$order['PaymentMethod']] ?? '💰';
                                ?>
                                    <span class="payment-method-badge"><?= $icon . ' ' . $pm ?></span>
                                <?php else: ?>
                                    <span class="payment-na">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($currentStatus === 'Paid' && !empty($order['DatePaid'])): ?>
                                    <span class="date-cell"><?= htmlspecialchars(date('M j, Y g:i A', strtotime($order['DatePaid']))) ?></span>
                                <?php else: ?>
                                    <span class="payment-na">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="actions-cell">
                                    <?php if (!in_array($currentStatus, ['Cancelled', 'Delivered'])): ?>
                                    <!-- Update status -->
                                    <form method="POST" action="ManageOrders.php" class="status-select-form"
                                          onsubmit="return interceptPaid(event, this)">
                                        <input type="hidden" name="action"  value="update_status">
                                        <input type="hidden" name="orderID" value="<?= $order['OrderID'] ?>">
                                        <select name="new_status" class="status-select" aria-label="Change status">
                                            <?php foreach (['Pending','Processing','Paid','Shipped','Delivered','Cancelled'] as $s): ?>
                                                <option value="<?= $s ?>" <?= $currentStatus === $s ? 'selected' : '' ?>><?= $s ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="btn-update">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                            Save
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php
        $baseUrl = 'ManageOrders.php?' . http_build_query(array_filter(['search' => $search, 'status' => $statusFilter]));

        // Prev
        if ($page > 1):
        ?><a href="<?= $baseUrl ?>&page=<?= $page - 1 ?>" aria-label="Previous">&#8249;</a><?php
        endif;

        // Pages
        for ($p = 1; $p <= $totalPages; $p++):
            if ($p === 1 || $p === $totalPages || abs($p - $page) <= 2):
                if ($p === $page): ?><span class="active"><?= $p ?></span><?php
                else: ?><a href="<?= $baseUrl ?>&page=<?= $p ?>"><?= $p ?></a><?php
                endif;
            elseif (abs($p - $page) === 3):
                ?><span class="dots">…</span><?php
            endif;
        endfor;

        // Next
        if ($page < $totalPages):
        ?><a href="<?= $baseUrl ?>&page=<?= $page + 1 ?>" aria-label="Next">&#8250;</a><?php
        endif;
        ?>
    </div>
    <?php endif; ?>

</div><!-- /.manage-wrapper -->

<script src="assets/bootstrap/js/bootstrap.min.js"></script>
<script>
    // ── Cancel modal ──────────────────────────────────────────────
    function openCancelModal(orderId) {
        document.getElementById('modalOrderID').value        = orderId;
        document.getElementById('modalOrderLabel').textContent = ' #' + orderId + ' ';
        document.getElementById('cancelModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    function closeCancelModal() {
        document.getElementById('cancelModal').classList.remove('active');
        document.body.style.overflow = '';
    }
    document.getElementById('cancelModal').addEventListener('click', function(e) {
        if (e.target === this) closeCancelModal();
    });

    // ── Payment modal ─────────────────────────────────────────────
    function openPayModal(orderId) {
        document.getElementById('payModalOrderID').value          = orderId;
        document.getElementById('payModalOrderLabel').textContent = '#' + orderId;
        document.getElementById('pay_method').value               = '';
        document.getElementById('pay_ref').value                  = '';
        document.getElementById('pay_date').value                 = new Date().toISOString().slice(0, 16);
        document.getElementById('payModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    function closePayModal() {
        document.getElementById('payModal').classList.remove('active');
        document.body.style.overflow = '';
    }
    document.getElementById('payModal').addEventListener('click', function(e) {
        if (e.target === this) closePayModal();
    });

    // Intercept Save when "Paid" is selected → open payment modal instead
    function interceptPaid(e, form) {
        const select = form.querySelector('select[name="new_status"]');
        if (select && select.value === 'Paid') {
            e.preventDefault();
            const orderID = form.querySelector('input[name="orderID"]').value;
            openPayModal(orderID);
            return false;
        }
        return true;
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') { closeCancelModal(); closePayModal(); }
    });
</script>

<?php require 'footer.php'; ?>
</html>