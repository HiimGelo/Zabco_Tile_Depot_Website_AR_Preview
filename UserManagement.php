<?php
session_start();
include 'db_connect.php';

/* ── Guard: admins only ─────────────────────────────────────────────────── */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php'); exit;
}

$currentStaffID = (int)($_SESSION['StaffID'] ?? $_SESSION['id'] ?? 0);

/* ── Handle POST actions ────────────────────────────────────────────────── */
$flash      = '';
$flashType  = 'success'; // 'success' | 'error'

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act   = $_POST['action'] ?? '';
    $rid   = (int)($_POST['rid'] ?? 0);
    $tab   = $_POST['tab']    ?? 'customers';

    /* ── Delete customer ── */
    if ($act === 'delete_customer' && $rid) {
        try {
            $pdo->prepare("DELETE FROM customer WHERE CustomerID = ?")->execute([$rid]);
            $flash = 'Customer deleted.';
        } catch (PDOException $e) {
            $flash = 'Error deleting customer.'; $flashType = 'error';
        }
    }

    /* ── Delete staff ── */
    if ($act === 'delete_staff' && $rid && $rid !== $currentStaffID) {
        try {
            $pdo->prepare("DELETE FROM staff WHERE StaffID = ?")->execute([$rid]);
            $flash = 'Staff member deleted.';
        } catch (PDOException $e) {
            $flash = 'Error deleting staff member.'; $flashType = 'error';
        }
    }

    /* ── Add staff ── */
    if ($act === 'add_staff') {
        $fn    = trim($_POST['first_name']  ?? '');
        $ln    = trim($_POST['last_name']   ?? '');
        $em    = trim($_POST['email']       ?? '');
        $job   = trim($_POST['job_title']   ?? '');
        $ph    = trim($_POST['phone']       ?? '');
        $bd    = trim($_POST['birthday']    ?? '') ?: null;
        $pw    = $_POST['password']         ?? '';
        $role  = in_array($_POST['role'] ?? '', ['admin','staff']) ? $_POST['role'] : 'staff';

        if ($fn && $ln && $em && $pw) {
            try {
                $hash = password_hash($pw, PASSWORD_BCRYPT);
                $pdo->prepare("INSERT INTO staff (FirstName, LastName, Email, JobTitle, PhoneNumber, Birthday, Password)
                               VALUES (?, ?, ?, ?, ?, ?, ?)")
                    ->execute([$fn, $ln, $em, $job, $ph, $bd, $hash]);
                $flash = 'Staff member added successfully.';
                $tab   = 'staff';
            } catch (PDOException $e) {
                $flash = 'Error adding staff (email may already exist).'; $flashType = 'error';
                $tab   = 'staff';
            }
        } else {
            $flash = 'Please fill in all required fields.'; $flashType = 'error';
            $tab   = 'staff';
        }
    }

    /* ── Edit staff ── */
    if ($act === 'edit_staff' && $rid) {
        $fn   = trim($_POST['first_name']  ?? '');
        $ln   = trim($_POST['last_name']   ?? '');
        $em   = trim($_POST['email']       ?? '');
        $job  = trim($_POST['job_title']   ?? '');
        $ph   = trim($_POST['phone']       ?? '');
        $bd   = trim($_POST['birthday']    ?? '') ?: null;
        $pw   = $_POST['password']         ?? '';
        $role = in_array($_POST['role'] ?? '', ['admin','staff']) ? $_POST['role'] : 'staff';

        if ($fn && $ln && $em) {
            try {
                if ($pw !== '') {
                    $hash = password_hash($pw, PASSWORD_BCRYPT);
                    $pdo->prepare("UPDATE staff SET FirstName=?, LastName=?, Email=?, JobTitle=?, PhoneNumber=?, Birthday=?, Password=? WHERE StaffID=?")
                        ->execute([$fn, $ln, $em, $job, $ph, $bd, $hash, $rid]);
                } else {
                    $pdo->prepare("UPDATE staff SET FirstName=?, LastName=?, Email=?, JobTitle=?, PhoneNumber=?, Birthday=? WHERE StaffID=?")
                        ->execute([$fn, $ln, $em, $job, $ph, $bd, $rid]);
                }
                $flash = 'Staff member updated.';
                $tab   = 'staff';
            } catch (PDOException $e) {
                $flash = 'Error updating staff member.'; $flashType = 'error';
                $tab   = 'staff';
            }
        } else {
            $flash = 'Please fill in all required fields.'; $flashType = 'error';
            $tab   = 'staff';
        }
    }

    /* ── Approve pending customer ── */
    if ($act === 'approve_pending' && $rid) {
        try {
            $row = $pdo->prepare("SELECT * FROM pending_customers WHERE id = ?");
            $row->execute([$rid]);
            $p = $row->fetch(PDO::FETCH_ASSOC);
            if ($p) {
                $pdo->prepare("INSERT INTO customer (FirstName, LastName, Email, PhoneNumber, Address, Password)
                               VALUES (?, ?, ?, ?, ?, ?)")
                    ->execute([
                        $p['first_name'], $p['last_name'], $p['email'],
                        $p['phone'],      $p['address'],   $p['password']
                    ]);
                $pdo->prepare("DELETE FROM pending_customers WHERE id = ?")->execute([$rid]);
                $flash = 'Customer approved and moved to customers.';
                $tab   = 'pending';
            }
        } catch (PDOException $e) {
            $flash = 'Error approving customer.'; $flashType = 'error'; $tab = 'pending';
        }
    }

    /* ── Reject pending customer ── */
    if ($act === 'reject_pending' && $rid) {
        try {
            $pdo->prepare("DELETE FROM pending_customers WHERE id = ?")->execute([$rid]);
            $flash = 'Pending registration rejected.';
            $tab   = 'pending';
        } catch (PDOException $e) {
            $flash = 'Error rejecting registration.'; $flashType = 'error'; $tab = 'pending';
        }
    }

    header('Location: UserManagement.php?tab=' . urlencode($tab) .
           '&flash=' . urlencode($flash) .
           '&flash_type=' . urlencode($flashType));
    exit;
}

$flash      = htmlspecialchars($_GET['flash']      ?? '');
$flashType  = in_array($_GET['flash_type'] ?? '', ['success','error']) ? $_GET['flash_type'] : 'success';
$activeTab  = in_array($_GET['tab'] ?? '', ['customers','staff','pending']) ? $_GET['tab'] : 'customers';
$search     = trim($_GET['search'] ?? '');

/* ── Load customers ──────────────────────────────────────────────────────── */
try {
    if ($search !== '') {
        $stmt = $pdo->prepare("SELECT * FROM customer WHERE
            FirstName LIKE ? OR LastName LIKE ? OR Email LIKE ? OR PhoneNumber LIKE ?
            ORDER BY CustomerID DESC");
        $like = "%$search%";
        $stmt->execute([$like, $like, $like, $like]);
    } else {
        $stmt = $pdo->query("SELECT * FROM customer ORDER BY CustomerID DESC");
    }
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $customers = []; }

/* ── Load staff ──────────────────────────────────────────────────────────── */
try {
    if ($search !== '') {
        $stmt = $pdo->prepare("SELECT * FROM staff WHERE
            FirstName LIKE ? OR LastName LIKE ? OR Email LIKE ? OR JobTitle LIKE ?
            ORDER BY StaffID DESC");
        $like = "%$search%";
        $stmt->execute([$like, $like, $like, $like]);
    } else {
        $stmt = $pdo->query("SELECT * FROM staff ORDER BY StaffID DESC");
    }
    $staffList = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $staffList = []; }

/* ── Load pending customers ──────────────────────────────────────────────── */
try {
    if ($search !== '' && $activeTab === 'pending') {
        $stmt = $pdo->prepare("SELECT * FROM pending_customers WHERE
            first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR phone LIKE ?
            ORDER BY id DESC");
        $like = "%$search%";
        $stmt->execute([$like, $like, $like, $like]);
    } else {
        $stmt = $pdo->query("SELECT * FROM pending_customers ORDER BY id DESC");
    }
    $pendingList = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $pendingList = []; }

/* ── Stats ───────────────────────────────────────────────────────────────── */
try { $totalCustomers = (int)$pdo->query("SELECT COUNT(*) FROM customer")->fetchColumn(); }
catch (PDOException $e) { $totalCustomers = 0; }

try { $totalStaff = (int)$pdo->query("SELECT COUNT(*) FROM staff")->fetchColumn(); }
catch (PDOException $e) { $totalStaff = 0; }

try { $pendingCount = (int)$pdo->query("SELECT COUNT(*) FROM pending_customers")->fetchColumn(); }
catch (PDOException $e) { $pendingCount = 0; }

require 'header.php';
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>User Management – Zabco Admin</title>
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    <link rel="stylesheet" href="assets/bootstrap/css/bootstrap.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <style>
    *, *::before, *::after { box-sizing: border-box; }
    body { background: #f0f0f0; font-family: 'Sora', 'Segoe UI', sans-serif; margin: 0; color: #ddd; }

    .um-page { max-width: 1280px; margin: 0 auto; padding: 40px 28px 80px; }

    /* ── Page header ── */
    .um-page-header {
        display: flex; flex-wrap: wrap; align-items: center;
        justify-content: space-between; gap: 14px;
        margin-bottom: 32px; padding-bottom: 20px;
        border-bottom: 2px solid #2a2a2a;
    }
    .um-page-header h1 { font-size: clamp(1.4rem,3vw,2rem); font-weight:900; color:#ed8d1b; margin:0; }
    .um-page-header h1 span { color: #ed8d1b; }
    .um-back-btn {
        display: inline-flex; align-items: center; gap: 6px;
        background: #222; color: #bbb; border: 1px solid #333;
        border-radius: 8px; padding: 8px 16px;
        font-size: .82rem; font-weight: 700; text-decoration: none;
        transition: background .18s, color .18s;
    }
    .um-back-btn:hover { background: #2a2a2a; color: #fff; }

    /* ── Flash ── */
    .um-flash {
        border-radius: 10px; padding: 11px 18px;
        font-size: .88rem; font-weight: 700;
        margin-bottom: 22px; display: flex; align-items: center; gap: 8px;
    }
    .um-flash.flash-success {
        background: rgba(237,141,27,.12); border: 1px solid rgba(237,141,27,.35); color: #ed8d1b;
    }
    .um-flash.flash-error {
        background: rgba(207,111,111,.12); border: 1px solid rgba(207,111,111,.35); color: #cf6f6f;
    }

    /* ── Stats ── */
    .um-stats { display: grid; grid-template-columns: repeat(3,1fr); gap: 14px; margin-bottom: 30px; }
    .um-stat {
        background: #1a1a1a; border: 1px solid #2a2a2a;
        border-radius: 14px; padding: 18px 20px;
        display: flex; flex-direction: column; gap: 4px;
    }
    .um-stat-label { font-size: .72rem; font-weight:700; letter-spacing:1.2px; text-transform:uppercase; color:#666; }
    .um-stat-val   { font-size: 2rem; font-weight:900; color:#fff; line-height:1; }
    .um-stat.accent  .um-stat-val { color: #ed8d1b; }
    .um-stat.success .um-stat-val { color: #4caf7d; }
    .um-stat.warn    .um-stat-val { color: #f0a830; }

    /* ── Tabs ── */
    .um-tabs { display: flex; gap: 4px; border-bottom: 2px solid #2a2a2a; margin-bottom: 22px; }
    .um-tab {
        padding: 10px 22px; font-size: .88rem; font-weight: 800;
        color: #555; text-decoration: none; border-radius: 8px 8px 0 0;
        border: 1px solid transparent; border-bottom: none;
        transition: color .18s, background .18s; display: inline-flex; align-items: center; gap: 7px;
    }
    .um-tab:hover { color: #ccc; background: #1a1a1a; }
    .um-tab.active { color: #ed8d1b; background: #1a1a1a; border-color: #2a2a2a; border-bottom: 2px solid #1a1a1a; margin-bottom: -2px; }
    .um-tab-badge {
        background: #f0a830; color: #151616;
        font-size: .65rem; font-weight: 900;
        border-radius: 20px; padding: 2px 7px; line-height: 1.4;
        min-width: 20px; text-align: center;
    }
    .um-tab.active .um-tab-badge { background: #ed8d1b; }

    /* ── Toolbar ── */
    .um-toolbar { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; margin-bottom: 16px; }
    .um-toolbar-count { font-size: .8rem; color: #555; font-weight: 700; }
    .um-search-form { display: flex; gap: 6px; margin-left: auto; }
    .um-search-input {
        background: #1a1a1a; color: #ccc; border: 1px solid #2a2a2a;
        border-radius: 8px; padding: 7px 12px;
        font-family: inherit; font-size: .82rem; font-weight: 500;
        outline: none; width: 220px; transition: border-color .18s;
    }
    .um-search-input:focus { border-color: #ed8d1b; }
    .um-search-btn {
        background: #ed8d1b; color: #151616; border: none;
        border-radius: 8px; padding: 7px 16px;
        font-family: inherit; font-size: .82rem; font-weight: 800;
        cursor: pointer; transition: background .18s;
    }
    .um-search-btn:hover { background: #c97415; }
    .um-clear-btn {
        background: #1a1a1a; color: #666; border: 1px solid #2a2a2a;
        border-radius: 8px; padding: 7px 12px;
        font-family: inherit; font-size: .82rem; font-weight: 700;
        text-decoration: none; display: inline-flex; align-items: center;
        transition: background .18s, color .18s;
    }
    .um-clear-btn:hover { background: #222; color: #aaa; }

    /* ── Add Staff Button ── */
    .um-add-btn {
        display: inline-flex; align-items: center; gap: 6px;
        background: #ed8d1b; color: #151616; border: none;
        border-radius: 8px; padding: 7px 16px;
        font-family: inherit; font-size: .82rem; font-weight: 800;
        cursor: pointer; transition: background .18s; text-decoration: none;
    }
    .um-add-btn:hover { background: #c97415; color: #111; }

    /* ── Table ── */
    .um-table-wrap { background: #1a1a1a; border: 1px solid #2a2a2a; border-radius: 16px; overflow: hidden; }
    .um-table { width: 100%; border-collapse: collapse; font-size: .85rem; }
    .um-table thead th {
        background: #212121; padding: 13px 16px;
        font-size: .7rem; font-weight: 700; letter-spacing: 1px;
        text-transform: uppercase; color: #555;
        text-align: left; border-bottom: 1px solid #2a2a2a; white-space: nowrap;
    }
    .um-table tbody tr { border-bottom: 1px solid #1e1e1e; transition: background .14s; }
    .um-table tbody tr:last-child { border-bottom: none; }
    .um-table tbody tr:hover { background: rgba(255,255,255,.025); }
    .um-table td { padding: 13px 16px; color: #bbb; vertical-align: middle; }
    .um-table td.td-name  { font-weight: 700; color: #fff; white-space: nowrap; }
    .um-table td.td-email { font-size: .8rem; color: #888; }
    .um-table td.td-id    { color: #555; font-size: .78rem; white-space: nowrap; }
    .um-table td.td-meta  { font-size: .78rem; color: #666; white-space: nowrap; }
    .td-actions { white-space: nowrap; }

    /* ── Avatar ── */
    .um-avatar {
        width: 34px; height: 34px; border-radius: 50%;
        display: inline-flex; align-items: center; justify-content: center;
        font-weight: 900; font-size: .85rem; flex-shrink: 0;
        text-transform: uppercase; margin-right: 10px; vertical-align: middle;
    }
    .um-avatar.cust-av    { background: linear-gradient(135deg,#2a4a6b,#1e3a5f); color: #7ab3e0; }
    .um-avatar.staff-av   { background: linear-gradient(135deg,#ed8d1b,#c97415); color: #151616; }
    .um-avatar.self-av    { background: linear-gradient(135deg,#2d6a2d,#1f4f1f); color: #7dd87d; }
    .um-avatar.pending-av { background: linear-gradient(135deg,#5a3a1a,#3a2010); color: #f0a830; }

    /* ── Actions ── */
    .um-act-btn {
        background: #252525; color: #aaa; border: 1px solid #333;
        border-radius: 7px; padding: 5px 12px;
        font-family: inherit; font-size: .75rem; font-weight: 700;
        cursor: pointer; transition: all .16s; white-space: nowrap;
        display: inline-flex; align-items: center; gap: 4px;
    }
    .um-act-btn:hover         { background: #ed8d1b; color: #151616; border-color: #ed8d1b; }
    .um-act-btn.btn-edit      { color: #7ab3e0; border-color: rgba(122,179,224,.3); }
    .um-act-btn.btn-edit:hover{ background: #1e3a5f; color: #7ab3e0; border-color: #1e3a5f; }
    .um-act-btn.btn-del       { color: #cf6f6f; border-color: rgba(207,111,111,.3); }
    .um-act-btn.btn-del:hover { background: #8b1a1a; color: #fff; border-color: #8b1a1a; }
    .um-act-btn.btn-approve   { color: #4caf7d; border-color: rgba(76,175,125,.3); }
    .um-act-btn.btn-approve:hover { background: #1f4f35; color: #4caf7d; border-color: #1f4f35; }
    .um-self-tag { font-size:.72rem; color:#4caf7d; font-style:italic; }

    /* ── Pending badge in table ── */
    .pending-date { font-size:.72rem; color:#555; }

    /* ── Empty ── */
    .um-empty { text-align:center; padding:60px 20px; color:#444; }
    .um-empty-icon { font-size:2.5rem; display:block; margin-bottom:10px; }

    /* ── Mobile cards ── */
    .um-card-list { display: none; flex-direction: column; gap: 12px; }
    .um-card { background:#1a1a1a; border:1px solid #2a2a2a; border-radius:14px; padding:16px 18px; }
    .um-card-top { display:flex; align-items:center; gap:12px; margin-bottom:8px; }
    .um-card-info { flex:1; min-width:0; }
    .um-card-name { font-weight:800; color:#fff; font-size:.95rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .um-card-email{ font-size:.78rem; color:#666; margin-top:2px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .um-card-meta { font-size:.75rem; color:#555; margin-bottom:10px; }
    .um-card-actions { display:flex; flex-wrap:wrap; gap:6px; }

    /* ── Modal ── */
    .um-modal-overlay {
        display: none; position: fixed; inset: 0;
        background: rgba(0,0,0,.75); z-index: 1000;
        align-items: center; justify-content: center;
        padding: 20px;
    }
    .um-modal-overlay.open { display: flex; }
    .um-modal {
        background: #1a1a1a; border: 1px solid #2a2a2a;
        border-radius: 18px; padding: 32px 28px;
        width: 100%; max-width: 520px;
        max-height: 90vh; overflow-y: auto;
        position: relative;
    }
    .um-modal h2 { font-size: 1.1rem; font-weight: 900; color: #fff; margin: 0 0 22px; }
    .um-modal h2 span { color: #ed8d1b; }
    .um-modal-close {
        position: absolute; top: 16px; right: 18px;
        background: none; border: none; color: #555;
        font-size: 1.3rem; cursor: pointer; line-height: 1;
        transition: color .15s;
    }
    .um-modal-close:hover { color: #fff; }
    .um-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px; }
    .um-form-group { display: flex; flex-direction: column; gap: 6px; }
    .um-form-group.full { grid-column: 1 / -1; }
    .um-form-group label { font-size: .72rem; font-weight: 700; letter-spacing: .8px; text-transform: uppercase; color: #555; }
    .um-form-group label .req { color: #ed8d1b; }
    .um-form-input, .um-form-select {
        background: #111; color: #ccc; border: 1px solid #2a2a2a;
        border-radius: 8px; padding: 9px 12px;
        font-family: inherit; font-size: .85rem;
        outline: none; transition: border-color .18s; width: 100%;
    }
    .um-form-input:focus, .um-form-select:focus { border-color: #ed8d1b; }
    .um-form-select { appearance: none; cursor: pointer; }
    .um-form-hint { font-size: .7rem; color: #444; margin-top: 2px; }
    .um-modal-actions { display: flex; gap: 10px; margin-top: 22px; justify-content: flex-end; }
    .um-modal-submit {
        background: #ed8d1b; color: #151616; border: none;
        border-radius: 8px; padding: 9px 22px;
        font-family: inherit; font-size: .85rem; font-weight: 800;
        cursor: pointer; transition: background .18s;
    }
    .um-modal-submit:hover { background: #c97415; }
    .um-modal-cancel {
        background: #252525; color: #888; border: 1px solid #333;
        border-radius: 8px; padding: 9px 18px;
        font-family: inherit; font-size: .85rem; font-weight: 700;
        cursor: pointer; transition: all .18s;
    }
    .um-modal-cancel:hover { background: #2a2a2a; color: #ccc; }

    /* ── Responsive ── */
    @media (max-width:820px) { .um-search-input { width:160px; } }
    @media (max-width:660px) {
        .um-page { padding:22px 14px 60px; }
        .um-stats { grid-template-columns:repeat(3,1fr); gap:8px; }
        .um-stat { padding:12px 12px; }
        .um-stat-val { font-size:1.5rem; }
        .um-table-wrap { display:none; }
        .um-card-list  { display:flex; }
        .um-search-form { margin-left:0; width:100%; }
        .um-search-input { width:1px; flex:1; }
        .um-toolbar { flex-wrap:wrap; }
        .um-form-row { grid-template-columns: 1fr; }
    }
    @media (max-width:400px){
        .um-page-header h1 { font-size:1.2rem; }
        .um-tab { font-size:.78rem; padding:8px 14px; }
    }
    </style>
</head>
<body>

<div class="um-page">

    <!-- Page header -->
    <div class="um-page-header">
        <h1>User <span>Management</span></h1>
        <a href="index.php" class="um-back-btn">← Back to Home</a>
    </div>

    <!-- Flash -->
    <?php if ($flash !== ''): ?>
    <div class="um-flash flash-<?= htmlspecialchars($flashType) ?>" id="um-flash">
        <?= $flashType === 'success' ? '✓' : '✕' ?> <?= $flash ?>
    </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="um-stats">
        <div class="um-stat accent">
            <span class="um-stat-label">Customers</span>
            <span class="um-stat-val"><?= $totalCustomers ?></span>
        </div>
        <div class="um-stat success">
            <span class="um-stat-label">Staff</span>
            <span class="um-stat-val"><?= $totalStaff ?></span>
        </div>
        <div class="um-stat warn">
            <span class="um-stat-label">Pending</span>
            <span class="um-stat-val"><?= $pendingCount ?></span>
        </div>
    </div>

    <!-- Tabs -->
    <div class="um-tabs">
        <a href="?tab=customers&search=<?= urlencode($search) ?>"
           class="um-tab <?= $activeTab === 'customers' ? 'active' : '' ?>">
            👤 Customers (<?= $totalCustomers ?>)
        </a>
        <a href="?tab=staff&search=<?= urlencode($search) ?>"
           class="um-tab <?= $activeTab === 'staff' ? 'active' : '' ?>">
            ⚙ Staff (<?= $totalStaff ?>)
        </a>
        <a href="?tab=pending&search=<?= urlencode($search) ?>"
           class="um-tab <?= $activeTab === 'pending' ? 'active' : '' ?>">
            🕐 Pending
            <?php if ($pendingCount > 0): ?>
            <span class="um-tab-badge"><?= $pendingCount ?></span>
            <?php endif; ?>
        </a>
    </div>

    <!-- Toolbar -->
    <div class="um-toolbar">
        <?php
            if ($activeTab === 'customers')     $activeList = $customers;
            elseif ($activeTab === 'staff')      $activeList = $staffList;
            else                                 $activeList = $pendingList;
        ?>
        <span class="um-toolbar-count">
            Showing <?= count($activeList) ?>
            <?php
                if ($search !== '')         echo ' result(s) for "' . htmlspecialchars($search) . '"';
                elseif ($activeTab === 'customers') echo ' customer(s)';
                elseif ($activeTab === 'staff')     echo ' staff member(s)';
                else                               echo ' pending registration(s)';
            ?>
        </span>

        <?php if ($activeTab === 'staff'): ?>
        <button class="um-add-btn" onclick="openAddModal()">＋ Add Staff</button>
        <?php endif; ?>

        <form method="GET" class="um-search-form">
            <input type="hidden" name="tab" value="<?= htmlspecialchars($activeTab) ?>">
            <input type="text" name="search" class="um-search-input"
                   placeholder="Search name, email…"
                   value="<?= htmlspecialchars($search) ?>">
            <button type="submit" class="um-search-btn">Search</button>
            <?php if ($search !== ''): ?>
            <a href="?tab=<?= htmlspecialchars($activeTab) ?>" class="um-clear-btn">✕</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if ($activeTab === 'customers'): ?>
    <!-- ═══════════════════════ CUSTOMERS TABLE ═══════════════════════ -->
    <div class="um-table-wrap">
        <?php if (empty($customers)): ?>
        <div class="um-empty">
            <span class="um-empty-icon">👤</span>
            No customers found<?= $search !== '' ? ' matching "' . htmlspecialchars($search) . '"' : '' ?>.
        </div>
        <?php else: ?>
        <table class="um-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Address</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($customers as $c): ?>
            <?php
                $fullName = trim(htmlspecialchars($c['FirstName'] ?? '') . ' ' . htmlspecialchars($c['LastName'] ?? ''));
                $initial  = strtoupper(substr($c['FirstName'] ?? 'C', 0, 1));
            ?>
            <tr>
                <td class="td-id">#<?= $c['CustomerID'] ?></td>
                <td class="td-name">
                    <span class="um-avatar cust-av"><?= $initial ?></span>
                    <?= $fullName ?>
                </td>
                <td class="td-email"><?= htmlspecialchars($c['Email'] ?? '—') ?></td>
                <td class="td-meta"><?= htmlspecialchars($c['PhoneNumber'] ?? '—') ?></td>
                <td class="td-meta" style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                    <?= htmlspecialchars($c['Address'] ?? '—') ?>
                </td>
                <td class="td-actions">
                    <form method="POST" style="margin:0;"
                          onsubmit="return confirm('Delete customer <?= htmlspecialchars(addslashes($fullName)) ?>? This cannot be undone.')">
                        <input type="hidden" name="action"  value="delete_customer">
                        <input type="hidden" name="rid"     value="<?= $c['CustomerID'] ?>">
                        <input type="hidden" name="tab"     value="customers">
                        <button class="um-act-btn btn-del" type="submit">🗑 Delete</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <!-- Customers mobile cards -->
    <div class="um-card-list">
        <?php if (empty($customers)): ?>
        <div class="um-empty">
            <span class="um-empty-icon">👤</span>
            No customers found<?= $search !== '' ? ' matching "' . htmlspecialchars($search) . '"' : '' ?>.
        </div>
        <?php else: ?>
        <?php foreach ($customers as $c): ?>
        <?php
            $fullName = trim(htmlspecialchars($c['FirstName'] ?? '') . ' ' . htmlspecialchars($c['LastName'] ?? ''));
            $initial  = strtoupper(substr($c['FirstName'] ?? 'C', 0, 1));
        ?>
        <div class="um-card">
            <div class="um-card-top">
                <span class="um-avatar cust-av"><?= $initial ?></span>
                <div class="um-card-info">
                    <div class="um-card-name"><?= $fullName ?></div>
                    <div class="um-card-email"><?= htmlspecialchars($c['Email'] ?? '—') ?></div>
                </div>
            </div>
            <div class="um-card-meta">
                ID: #<?= $c['CustomerID'] ?>
                <?php if (!empty($c['PhoneNumber'])): ?> · <?= htmlspecialchars($c['PhoneNumber']) ?><?php endif; ?>
            </div>
            <div class="um-card-actions">
                <form method="POST" style="margin:0;"
                      onsubmit="return confirm('Delete this customer? This cannot be undone.')">
                    <input type="hidden" name="action"  value="delete_customer">
                    <input type="hidden" name="rid"     value="<?= $c['CustomerID'] ?>">
                    <input type="hidden" name="tab"     value="customers">
                    <button class="um-act-btn btn-del" type="submit">🗑 Delete</button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php elseif ($activeTab === 'staff'): ?>
    <!-- ═══════════════════════ STAFF TABLE ═══════════════════════ -->
    <div class="um-table-wrap">
        <?php if (empty($staffList)): ?>
        <div class="um-empty">
            <span class="um-empty-icon">⚙</span>
            No staff found<?= $search !== '' ? ' matching "' . htmlspecialchars($search) . '"' : '' ?>.
        </div>
        <?php else: ?>
        <table class="um-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Job Title</th>
                    <th>Phone</th>
                    <th>Birthday</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($staffList as $s): ?>
            <?php
                $isSelf   = (int)$s['StaffID'] === $currentStaffID;
                $fullName = trim(htmlspecialchars($s['FirstName'] ?? '') . ' ' . htmlspecialchars($s['LastName'] ?? ''));
                $initial  = strtoupper(substr($s['FirstName'] ?? 'S', 0, 1));
            ?>
            <tr>
                <td class="td-id">#<?= $s['StaffID'] ?></td>
                <td class="td-name">
                    <span class="um-avatar <?= $isSelf ? 'self-av' : 'staff-av' ?>"><?= $initial ?></span>
                    <?= $fullName ?>
                    <?php if ($isSelf): ?><span class="um-self-tag"> (you)</span><?php endif; ?>
                </td>
                <td class="td-email"><?= htmlspecialchars($s['Email'] ?? '—') ?></td>
                <td class="td-meta"><?= htmlspecialchars($s['JobTitle'] ?? '—') ?></td>
                <td class="td-meta"><?= htmlspecialchars($s['PhoneNumber'] ?? '—') ?></td>
                <td class="td-meta"><?= htmlspecialchars($s['Birthday'] ?? '—') ?></td>
                <td class="td-actions">
                    <div style="display:flex;gap:6px;align-items:center;">
                        <!-- Edit button (allowed for all, including self) -->
                        <button class="um-act-btn btn-edit" type="button"
                            onclick="openEditModal(
                                <?= $s['StaffID'] ?>,
                                '<?= htmlspecialchars(addslashes($s['FirstName'] ?? ''), ENT_QUOTES) ?>',
                                '<?= htmlspecialchars(addslashes($s['LastName']  ?? ''), ENT_QUOTES) ?>',
                                '<?= htmlspecialchars(addslashes($s['Email']     ?? ''), ENT_QUOTES) ?>',
                                '<?= htmlspecialchars(addslashes($s['JobTitle']  ?? ''), ENT_QUOTES) ?>',
                                '<?= htmlspecialchars(addslashes($s['PhoneNumber'] ?? ''), ENT_QUOTES) ?>',
                                '<?= htmlspecialchars(addslashes($s['Birthday'] ?? ''), ENT_QUOTES) ?>'
                            )">✏ Edit</button>

                        <?php if ($isSelf): ?>
                        <span class="um-self-tag">Current session</span>
                        <?php else: ?>
                        <form method="POST" style="margin:0;"
                              onsubmit="return confirm('Delete staff member <?= htmlspecialchars(addslashes($fullName)) ?>? This cannot be undone.')">
                            <input type="hidden" name="action" value="delete_staff">
                            <input type="hidden" name="rid"    value="<?= $s['StaffID'] ?>">
                            <input type="hidden" name="tab"    value="staff">
                            <button class="um-act-btn btn-del" type="submit">🗑 Delete</button>
                        </form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <!-- Staff mobile cards -->
    <div class="um-card-list">
        <?php if (empty($staffList)): ?>
        <div class="um-empty">
            <span class="um-empty-icon">⚙</span>
            No staff found<?= $search !== '' ? ' matching "' . htmlspecialchars($search) . '"' : '' ?>.
        </div>
        <?php else: ?>
        <?php foreach ($staffList as $s): ?>
        <?php
            $isSelf   = (int)$s['StaffID'] === $currentStaffID;
            $fullName = trim(htmlspecialchars($s['FirstName'] ?? '') . ' ' . htmlspecialchars($s['LastName'] ?? ''));
            $initial  = strtoupper(substr($s['FirstName'] ?? 'S', 0, 1));
        ?>
        <div class="um-card">
            <div class="um-card-top">
                <span class="um-avatar <?= $isSelf ? 'self-av' : 'staff-av' ?>"><?= $initial ?></span>
                <div class="um-card-info">
                    <div class="um-card-name">
                        <?= $fullName ?>
                        <?php if ($isSelf): ?><span class="um-self-tag"> (you)</span><?php endif; ?>
                    </div>
                    <div class="um-card-email"><?= htmlspecialchars($s['Email'] ?? '—') ?></div>
                </div>
            </div>
            <div class="um-card-meta">
                ID: #<?= $s['StaffID'] ?>
                <?php if (!empty($s['JobTitle'])): ?> · <?= htmlspecialchars($s['JobTitle']) ?><?php endif; ?>
                <?php if (!empty($s['PhoneNumber'])): ?> · <?= htmlspecialchars($s['PhoneNumber']) ?><?php endif; ?>
            </div>
            <div class="um-card-actions">
                <button class="um-act-btn btn-edit" type="button"
                    onclick="openEditModal(
                        <?= $s['StaffID'] ?>,
                        '<?= htmlspecialchars(addslashes($s['FirstName'] ?? ''), ENT_QUOTES) ?>',
                        '<?= htmlspecialchars(addslashes($s['LastName']  ?? ''), ENT_QUOTES) ?>',
                        '<?= htmlspecialchars(addslashes($s['Email']     ?? ''), ENT_QUOTES) ?>',
                        '<?= htmlspecialchars(addslashes($s['JobTitle']  ?? ''), ENT_QUOTES) ?>',
                        '<?= htmlspecialchars(addslashes($s['PhoneNumber'] ?? ''), ENT_QUOTES) ?>',
                        '<?= htmlspecialchars(addslashes($s['Birthday'] ?? ''), ENT_QUOTES) ?>'
                    )">✏ Edit</button>

                <?php if (!$isSelf): ?>
                <form method="POST" style="margin:0;"
                      onsubmit="return confirm('Delete this staff member? This cannot be undone.')">
                    <input type="hidden" name="action" value="delete_staff">
                    <input type="hidden" name="rid"    value="<?= $s['StaffID'] ?>">
                    <input type="hidden" name="tab"    value="staff">
                    <button class="um-act-btn btn-del" type="submit">🗑 Delete</button>
                </form>
                <?php else: ?>
                <span class="um-self-tag">This is your current session.</span>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php else: ?>
    <!-- ═══════════════════════ PENDING TABLE ═══════════════════════ -->
    <div class="um-table-wrap">
        <?php if (empty($pendingList)): ?>
        <div class="um-empty">
            <span class="um-empty-icon">🕐</span>
            No pending registrations<?= $search !== '' ? ' matching "' . htmlspecialchars($search) . '"' : '' ?>.
        </div>
        <?php else: ?>
        <table class="um-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Address</th>
                    <th>Registered</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($pendingList as $p): ?>
            <?php
                $fullName = trim(htmlspecialchars($p['first_name'] ?? '') . ' ' . htmlspecialchars($p['last_name'] ?? ''));
                $initial  = strtoupper(substr($p['first_name'] ?? 'P', 0, 1));
                $regDate  = !empty($p['created_at']) ? date('M d, Y', strtotime($p['created_at'])) : '—';
            ?>
            <tr>
                <td class="td-id">#<?= $p['id'] ?></td>
                <td class="td-name">
                    <span class="um-avatar pending-av"><?= $initial ?></span>
                    <?= $fullName ?>
                </td>
                <td class="td-email"><?= htmlspecialchars($p['email'] ?? '—') ?></td>
                <td class="td-meta"><?= htmlspecialchars($p['phone'] ?? '—') ?></td>
                <td class="td-meta" style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                    <?= htmlspecialchars($p['address'] ?? '—') ?>
                </td>
                <td class="td-meta pending-date"><?= $regDate ?></td>
                <td class="td-actions">
                    <div style="display:flex;gap:6px;">
                        <form method="POST" style="margin:0;"
                              onsubmit="return confirm('Approve <?= htmlspecialchars(addslashes($fullName)) ?> as a registered customer?')">
                            <input type="hidden" name="action" value="approve_pending">
                            <input type="hidden" name="rid"    value="<?= $p['id'] ?>">
                            <input type="hidden" name="tab"    value="pending">
                            <button class="um-act-btn btn-approve" type="submit">✔ Approve</button>
                        </form>
                        <form method="POST" style="margin:0;"
                              onsubmit="return confirm('Reject and remove this pending registration? This cannot be undone.')">
                            <input type="hidden" name="action" value="reject_pending">
                            <input type="hidden" name="rid"    value="<?= $p['id'] ?>">
                            <input type="hidden" name="tab"    value="pending">
                            <button class="um-act-btn btn-del" type="submit">✕ Reject</button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <!-- Pending mobile cards -->
    <div class="um-card-list">
        <?php if (empty($pendingList)): ?>
        <div class="um-empty">
            <span class="um-empty-icon">🕐</span>
            No pending registrations.
        </div>
        <?php else: ?>
        <?php foreach ($pendingList as $p): ?>
        <?php
            $fullName = trim(htmlspecialchars($p['first_name'] ?? '') . ' ' . htmlspecialchars($p['last_name'] ?? ''));
            $initial  = strtoupper(substr($p['first_name'] ?? 'P', 0, 1));
            $regDate  = !empty($p['created_at']) ? date('M d, Y', strtotime($p['created_at'])) : '—';
        ?>
        <div class="um-card">
            <div class="um-card-top">
                <span class="um-avatar pending-av"><?= $initial ?></span>
                <div class="um-card-info">
                    <div class="um-card-name"><?= $fullName ?></div>
                    <div class="um-card-email"><?= htmlspecialchars($p['email'] ?? '—') ?></div>
                </div>
            </div>
            <div class="um-card-meta">
                ID: #<?= $p['id'] ?>
                <?php if (!empty($p['phone'])): ?> · <?= htmlspecialchars($p['phone']) ?><?php endif; ?>
                · Registered: <?= $regDate ?>
            </div>
            <div class="um-card-actions">
                <form method="POST" style="margin:0;"
                      onsubmit="return confirm('Approve this registration?')">
                    <input type="hidden" name="action" value="approve_pending">
                    <input type="hidden" name="rid"    value="<?= $p['id'] ?>">
                    <input type="hidden" name="tab"    value="pending">
                    <button class="um-act-btn btn-approve" type="submit">✔ Approve</button>
                </form>
                <form method="POST" style="margin:0;"
                      onsubmit="return confirm('Reject this registration? This cannot be undone.')">
                    <input type="hidden" name="action" value="reject_pending">
                    <input type="hidden" name="rid"    value="<?= $p['id'] ?>">
                    <input type="hidden" name="tab"    value="pending">
                    <button class="um-act-btn btn-del" type="submit">✕ Reject</button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div><!-- /um-page -->

<!-- ═══════════════════════ ADD STAFF MODAL ═══════════════════════ -->
<div class="um-modal-overlay" id="addModal" onclick="closeOnBackdrop(event,'addModal')">
    <div class="um-modal">
        <button class="um-modal-close" onclick="closeModal('addModal')">✕</button>
        <h2>Add New <span>Staff</span></h2>
        <form method="POST">
            <input type="hidden" name="action" value="add_staff">
            <input type="hidden" name="tab"    value="staff">
            <div class="um-form-row">
                <div class="um-form-group">
                    <label>First Name <span class="req">*</span></label>
                    <input type="text" name="first_name" class="um-form-input" required placeholder="Juan">
                </div>
                <div class="um-form-group">
                    <label>Last Name <span class="req">*</span></label>
                    <input type="text" name="last_name" class="um-form-input" required placeholder="Dela Cruz">
                </div>
                <div class="um-form-group full">
                    <label>Email Address <span class="req">*</span></label>
                    <input type="email" name="email" class="um-form-input" required placeholder="juan@zabco.com">
                </div>
                <div class="um-form-group">
                    <label>Job Title</label>
                    <input type="text" name="job_title" class="um-form-input" placeholder="Sales Associate">
                </div>
                <div class="um-form-group">
                    <label>Phone Number</label>
                    <input type="text" name="phone" class="um-form-input" placeholder="09xxxxxxxxx">
                </div>
                <div class="um-form-group">
                    <label>Birthday</label>
                    <input type="date" name="birthday" class="um-form-input">
                </div>
                <div class="um-form-group full">
                    <label>Password <span class="req">*</span></label>
                    <input type="password" name="password" class="um-form-input" required placeholder="Set a strong password">
                </div>
            </div>
            <div class="um-modal-actions">
                <button type="button" class="um-modal-cancel" onclick="closeModal('addModal')">Cancel</button>
                <button type="submit" class="um-modal-submit">Add Staff Member</button>
            </div>
        </form>
    </div>
</div>

<!-- ═══════════════════════ EDIT STAFF MODAL ═══════════════════════ -->
<div class="um-modal-overlay" id="editModal" onclick="closeOnBackdrop(event,'editModal')">
    <div class="um-modal">
        <button class="um-modal-close" onclick="closeModal('editModal')">✕</button>
        <h2>Edit <span>Staff</span> Member</h2>
        <form method="POST">
            <input type="hidden" name="action" value="edit_staff">
            <input type="hidden" name="tab"    value="staff">
            <input type="hidden" name="rid"    id="edit_rid">
            <div class="um-form-row">
                <div class="um-form-group">
                    <label>First Name <span class="req">*</span></label>
                    <input type="text" name="first_name" id="edit_first_name" class="um-form-input" required>
                </div>
                <div class="um-form-group">
                    <label>Last Name <span class="req">*</span></label>
                    <input type="text" name="last_name" id="edit_last_name" class="um-form-input" required>
                </div>
                <div class="um-form-group full">
                    <label>Email Address <span class="req">*</span></label>
                    <input type="email" name="email" id="edit_email" class="um-form-input" required>
                </div>
                <div class="um-form-group">
                    <label>Job Title</label>
                    <input type="text" name="job_title" id="edit_job_title" class="um-form-input">
                </div>
                <div class="um-form-group">
                    <label>Phone Number</label>
                    <input type="text" name="phone" id="edit_phone" class="um-form-input">
                </div>
                <div class="um-form-group">
                    <label>Birthday</label>
                    <input type="date" name="birthday" id="edit_birthday" class="um-form-input">
                </div>
                <div class="um-form-group full">
                    <label>New Password</label>
                    <input type="password" name="password" class="um-form-input" placeholder="Leave blank to keep current password">
                    <span class="um-form-hint">Leave empty to keep the existing password unchanged.</span>
                </div>
            </div>
            <div class="um-modal-actions">
                <button type="button" class="um-modal-cancel" onclick="closeModal('editModal')">Cancel</button>
                <button type="submit" class="um-modal-submit">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script src="assets/bootstrap/js/bootstrap.min.js"></script>
<script>
/* ── Flash auto-hide ── */
const fl = document.getElementById('um-flash');
if (fl) setTimeout(() => fl.style.display = 'none', 4000);

/* ── Modal helpers ── */
function openModal(id)  { document.getElementById(id).classList.add('open'); document.body.style.overflow = 'hidden'; }
function closeModal(id) { document.getElementById(id).classList.remove('open'); document.body.style.overflow = ''; }
function closeOnBackdrop(e, id) { if (e.target === document.getElementById(id)) closeModal(id); }

/* ── Add staff modal ── */
function openAddModal() { openModal('addModal'); }

/* ── Edit staff modal ── */
function openEditModal(id, fn, ln, em, job, ph, bd) {
    document.getElementById('edit_rid').value        = id;
    document.getElementById('edit_first_name').value = fn;
    document.getElementById('edit_last_name').value  = ln;
    document.getElementById('edit_email').value      = em;
    document.getElementById('edit_job_title').value  = job;
    document.getElementById('edit_phone').value      = ph;
    document.getElementById('edit_birthday').value   = bd;
    openModal('editModal');
}

/* ── Keyboard close (Escape) ── */
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        closeModal('addModal');
        closeModal('editModal');
    }
});
</script>

</body>
<?php require 'footer.php'; ?>
</html>