<?php
session_start();
include 'db_connect.php';

/* ── Guard: admins only ─────────────────────────────────────────────────── */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php'); exit;
}

/* ── Ensure table exists ────────────────────────────────────────────────── */
$pdo->exec("CREATE TABLE IF NOT EXISTS service_inquiries (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    service_name VARCHAR(255) NOT NULL DEFAULT '',
    name         VARCHAR(255) NOT NULL,
    contact      VARCHAR(100) NOT NULL,
    email        VARCHAR(255) NOT NULL,
    message      TEXT         NOT NULL,
    status       ENUM('new','read','responded') NOT NULL DEFAULT 'new',
    source       VARCHAR(50)  NOT NULL DEFAULT 'website',
    created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
)");

/* ── Handle POST actions ────────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';
    $id  = (int)($_POST['inq_id'] ?? 0);

    if ($id) {
        if ($act === 'mark_read') {
            $pdo->prepare("UPDATE service_inquiries SET status='read' WHERE id=?")->execute([$id]);
            $flash = 'Marked as read.';
        } elseif ($act === 'mark_responded') {
            $pdo->prepare("UPDATE service_inquiries SET status='responded' WHERE id=?")->execute([$id]);
            $flash = 'Marked as responded.';
        } elseif ($act === 'delete') {
            $pdo->prepare("DELETE FROM service_inquiries WHERE id=?")->execute([$id]);
            $flash = 'Inquiry deleted.';
        }
    }
    header('Location: ManageInquiries.php?flash=' . urlencode($flash ?? '')); exit;
}

/* ── Filters ────────────────────────────────────────────────────────────── */
$statusFilter  = in_array($_GET['status'] ?? '', ['all','new','read','responded']) ? ($_GET['status'] ?? 'all') : 'all';
$serviceFilter = trim($_GET['service'] ?? '');
$flash         = htmlspecialchars($_GET['flash'] ?? '');

$where  = [];
$params = [];
if ($statusFilter !== 'all') { $where[] = 'status = ?';          $params[] = $statusFilter; }
if ($serviceFilter !== '')   { $where[] = 'service_name LIKE ?'; $params[] = '%' . $serviceFilter . '%'; }
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare("SELECT * FROM service_inquiries $whereSql ORDER BY created_at DESC");
$stmt->execute($params);
$inquiries = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ── Stats ──────────────────────────────────────────────────────────────── */
$stats = $pdo->query("SELECT
    COUNT(*)                   AS total,
    SUM(status = 'new')        AS new_count,
    SUM(status = 'read')       AS read_count,
    SUM(status = 'responded')  AS responded_count
    FROM service_inquiries")->fetch(PDO::FETCH_ASSOC);

/* ── Distinct service names for filter dropdown ─────────────────────────── */
$serviceNames = $pdo->query("SELECT DISTINCT service_name FROM service_inquiries ORDER BY service_name")->fetchAll(PDO::FETCH_COLUMN);

require 'header.php';
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage Inquiries – Zabco Admin</title>
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    <link rel="stylesheet" href="assets/bootstrap/css/bootstrap.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <style>
    *, *::before, *::after { box-sizing: border-box; }
    body { background: #f0f0f0; font-family: 'Sora', 'Segoe UI', sans-serif; margin: 0; color: #ddd; }

    /* ── Page layout ── */
    .mi-page { max-width: 1300px; margin: 0 auto; padding: 40px 28px 80px; }

    /* ── Page header ── */
    .mi-page-header {
        display: flex; flex-wrap: wrap; align-items: center;
        justify-content: space-between; gap: 14px;
        margin-bottom: 32px;
        padding-bottom: 20px;
        border-bottom: 2px solid #2a2a2a;
    }
    .mi-page-header h1 {
        font-size: clamp(1.4rem, 3vw, 2rem);
        font-weight: 900; color: #ed8d1b; margin: 0;
    }
    .mi-page-header h1 span { color: #ed8d1b; }
    .mi-back-btn {
        display: inline-flex; align-items: center; gap: 6px;
        background: #222; color: #bbb; border: 1px solid #333;
        border-radius: 8px; padding: 8px 16px;
        font-size: .82rem; font-weight: 700; text-decoration: none;
        transition: background .18s, color .18s;
    }
    .mi-back-btn:hover { background: #2a2a2a; color: #fff; }

    /* ── Stats row ── */
    .mi-stats {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 14px;
        margin-bottom: 28px;
    }
    .mi-stat {
        background: #1a1a1a; border: 1px solid #2a2a2a;
        border-radius: 14px; padding: 18px 20px;
        display: flex; flex-direction: column; gap: 4px;
    }
    .mi-stat-label {
        font-size: .72rem; font-weight: 700; letter-spacing: 1.2px;
        text-transform: uppercase; color: #666;
    }
    .mi-stat-val {
        font-size: 2rem; font-weight: 900; color: #fff; line-height: 1;
    }
    .mi-stat.accent  .mi-stat-val { color: #ed8d1b; }
    .mi-stat.success .mi-stat-val { color: #4caf7d; }
    .mi-stat.muted   .mi-stat-val { color: #888; }

    /* ── Flash ── */
    .mi-flash {
        background: rgba(237,141,27,.12); border: 1px solid rgba(237,141,27,.35);
        color: #ed8d1b; border-radius: 10px;
        padding: 11px 18px; font-size: .88rem; font-weight: 700;
        margin-bottom: 22px;
        display: flex; align-items: center; gap: 8px;
    }

    /* ── Filter bar ── */
    .mi-filters {
        display: flex; flex-wrap: wrap; gap: 10px; align-items: center;
        margin-bottom: 22px;
    }
    .mi-filter-label {
        font-size: .78rem; font-weight: 700; color: #666;
        text-transform: uppercase; letter-spacing: .8px;
    }
    .mi-filter-group { display: flex; flex-wrap: wrap; gap: 6px; }
    .mi-filter-btn {
        background: #1a1a1a; color: #888; border: 1px solid #2a2a2a;
        border-radius: 20px; padding: 6px 14px;
        font-family: inherit; font-size: .8rem; font-weight: 700;
        cursor: pointer; transition: all .18s; text-decoration: none; display: inline-block;
    }
    .mi-filter-btn:hover,
    .mi-filter-btn.active { background: #ed8d1b; color: #151616; border-color: #ed8d1b; }
    .mi-filter-select {
        background: #1a1a1a; color: #ccc; border: 1px solid #2a2a2a;
        border-radius: 8px; padding: 7px 12px;
        font-family: inherit; font-size: .82rem; font-weight: 600;
        cursor: pointer; outline: none;
    }
    .mi-filter-select:focus { border-color: #ed8d1b; }

    /* ── Table wrapper ── */
    .mi-table-wrap {
        background: #1a1a1a; border: 1px solid #2a2a2a;
        border-radius: 16px; overflow: hidden;
    }
    .mi-table {
        width: 100%; border-collapse: collapse;
        font-size: .85rem;
    }
    .mi-table thead th {
        background: #212121;
        padding: 14px 16px;
        font-size: .72rem; font-weight: 700; letter-spacing: 1px;
        text-transform: uppercase; color: #666;
        text-align: left; border-bottom: 1px solid #2a2a2a;
        white-space: nowrap;
    }
    .mi-table tbody tr {
        border-bottom: 1px solid #222;
        transition: background .14s;
    }
    .mi-table tbody tr:last-child { border-bottom: none; }
    .mi-table tbody tr:hover { background: rgba(255,255,255,.03); }
    .mi-table td {
        padding: 14px 16px; color: #ccc; vertical-align: top;
    }
    .mi-table td.td-name  { color: #fff; font-weight: 700; white-space: nowrap; }
    .mi-table td.td-msg   { max-width: 260px; word-break: break-word; line-height: 1.55; }
    .mi-table td.td-contact { white-space: nowrap; font-size: .82rem; }
    .mi-table td.td-date  { white-space: nowrap; font-size: .78rem; color: #555; }
    .mi-table td.td-svc   { white-space: nowrap; }

    /* ── Status badge ── */
    .mi-badge {
        display: inline-flex; align-items: center; gap: 5px;
        font-size: .7rem; font-weight: 800; letter-spacing: .6px;
        text-transform: uppercase; padding: 3px 10px; border-radius: 20px;
        white-space: nowrap;
    }
    .mi-badge.new       { background: rgba(237,141,27,.15); color: #ed8d1b; border: 1px solid rgba(237,141,27,.35); }
    .mi-badge.read      { background: rgba(99,180,255,.10); color: #63b4ff; border: 1px solid rgba(99,180,255,.25); }
    .mi-badge.responded { background: rgba(76,175,125,.12); color: #4caf7d; border: 1px solid rgba(76,175,125,.3);  }

    /* ── Action buttons in table ── */
    .mi-actions { display: flex; flex-wrap: wrap; gap: 6px; }
    .mi-act-btn {
        background: #252525; color: #aaa; border: 1px solid #333;
        border-radius: 7px; padding: 5px 10px;
        font-family: inherit; font-size: .75rem; font-weight: 700;
        cursor: pointer; transition: all .16s; white-space: nowrap;
    }
    .mi-act-btn:hover         { background: #ed8d1b; color: #151616; border-color: #ed8d1b; }
    .mi-act-btn.btn-responded { color: #4caf7d; border-color: rgba(76,175,125,.3); }
    .mi-act-btn.btn-responded:hover { background: #4caf7d; color: #fff; border-color: #4caf7d; }
    .mi-act-btn.btn-del       { color: #cf6f6f; border-color: rgba(207,111,111,.3); }
    .mi-act-btn.btn-del:hover { background: #8b1a1a; color: #fff; border-color: #8b1a1a; }

    /* ── Empty state ── */
    .mi-empty {
        text-align: center; padding: 64px 20px;
        color: #444; font-size: 1rem;
    }
    .mi-empty-icon { font-size: 2.5rem; display: block; margin-bottom: 10px; }

    /* ── Detail modal ── */
    .mi-modal-ov {
        display: none; position: fixed; inset: 0;
        background: rgba(0,0,0,.72); z-index: 1200;
        align-items: center; justify-content: center;
        padding: 20px;
    }
    .mi-modal-ov.open { display: flex; }
    .mi-modal {
        background: #1a1a1a; border: 1px solid #2a2a2a;
        border-radius: 18px; width: 100%; max-width: 540px;
        padding: 32px 28px; position: relative;
        max-height: 90vh; overflow-y: auto;
        box-shadow: 0 24px 64px rgba(0,0,0,.6);
    }
    .mi-modal h3 {
        color: #fff; font-size: 1.15rem; font-weight: 900;
        margin: 0 0 20px; padding-right: 28px;
    }
    .mi-modal h3 span { color: #ed8d1b; }
    .mi-modal-close {
        position: absolute; top: 16px; right: 18px;
        background: none; border: none; color: #555;
        font-size: 20px; cursor: pointer; transition: color .15s;
        line-height: 1;
    }
    .mi-modal-close:hover { color: #fff; }
    .mi-detail-row {
        margin-bottom: 14px;
    }
    .mi-detail-label {
        font-size: .68rem; font-weight: 700; letter-spacing: 1px;
        text-transform: uppercase; color: #555; margin-bottom: 4px;
    }
    .mi-detail-val {
        color: #ddd; font-size: .9rem; line-height: 1.6;
        word-break: break-word;
    }
    .mi-detail-val a { color: #ed8d1b; text-decoration: none; }
    .mi-detail-val a:hover { text-decoration: underline; }
    .mi-modal-actions {
        display: flex; flex-wrap: wrap; gap: 8px; margin-top: 24px;
        padding-top: 18px; border-top: 1px solid #2a2a2a;
    }
    .mi-modal-btn {
        background: #252525; color: #ccc; border: 1px solid #333;
        border-radius: 8px; padding: 9px 16px;
        font-family: inherit; font-size: .82rem; font-weight: 700;
        cursor: pointer; transition: all .16s;
    }
    .mi-modal-btn:hover           { background: #ed8d1b; color: #151616; border-color: #ed8d1b; }
    .mi-modal-btn.btn-responded   { color: #4caf7d; }
    .mi-modal-btn.btn-responded:hover { background: #4caf7d; color: #fff; border-color: #4caf7d; }
    .mi-modal-btn.btn-del         { color: #cf6f6f; }
    .mi-modal-btn.btn-del:hover   { background: #8b1a1a; color: #fff; border-color: #8b1a1a; }
    .mi-modal-btn.btn-cancel      { margin-left: auto; }

    /* ── Divider line in modal ── */
    .mi-divider { border: none; border-top: 1px solid #2a2a2a; margin: 14px 0; }

    /* ── Mobile cards (hidden on desktop) ── */
    .mi-card-list  { display: none; flex-direction: column; gap: 12px; }
    .mi-card {
        background: #1a1a1a; border: 1px solid #2a2a2a;
        border-radius: 14px; padding: 16px 18px;
        position: relative;
    }
    .mi-card-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 8px; margin-bottom: 8px; }
    .mi-card-name { font-weight: 800; color: #fff; font-size: .95rem; }
    .mi-card-svc  { font-size: .78rem; color: #888; margin-top: 2px; }
    .mi-card-msg  { color: #aaa; font-size: .85rem; line-height: 1.55; margin-bottom: 10px;
                    display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
    .mi-card-meta { display: flex; gap: 10px; flex-wrap: wrap; align-items: center;
                    font-size: .75rem; color: #555; margin-bottom: 10px; }
    .mi-card-actions { display: flex; flex-wrap: wrap; gap: 6px; }

    /* ── Responsive ── */
    @media (max-width: 960px) {
        .mi-stats { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 700px) {
        .mi-page { padding: 24px 14px 60px; }
        .mi-stats { grid-template-columns: repeat(2, 1fr); gap: 10px; }
        .mi-stat { padding: 14px 16px; }
        .mi-stat-val { font-size: 1.6rem; }
        .mi-table-wrap { display: none; }
        .mi-card-list  { display: flex; }
        .mi-page-header h1 { font-size: 1.3rem; }
    }
    @media (max-width: 420px) {
        .mi-stats { grid-template-columns: 1fr 1fr; gap: 8px; }
        .mi-filter-group { gap: 5px; }
        .mi-filter-btn { font-size: .73rem; padding: 5px 10px; }
    }
    </style>
</head>
<body>

<div class="mi-page">

    <!-- Page header -->
    <div class="mi-page-header">
        <h1>Manage <span>Inquiries</span></h1>
        <a href="index.php" class="mi-back-btn">← Back to Home</a>
    </div>

    <!-- Flash message -->
    <?php if ($flash !== ''): ?>
    <div class="mi-flash" id="mi-flash">✓ <?= $flash ?></div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="mi-stats">
        <div class="mi-stat">
            <span class="mi-stat-label">Total</span>
            <span class="mi-stat-val"><?= (int)$stats['total'] ?></span>
        </div>
        <div class="mi-stat accent">
            <span class="mi-stat-label">New</span>
            <span class="mi-stat-val"><?= (int)$stats['new_count'] ?></span>
        </div>
        <div class="mi-stat">
            <span class="mi-stat-label">Read</span>
            <span class="mi-stat-val"><?= (int)$stats['read_count'] ?></span>
        </div>
        <div class="mi-stat success">
            <span class="mi-stat-label">Responded</span>
            <span class="mi-stat-val"><?= (int)$stats['responded_count'] ?></span>
        </div>
    </div>

    <!-- Filters -->
    <div class="mi-filters">
        <span class="mi-filter-label">Status:</span>
        <div class="mi-filter-group">
            <?php foreach (['all' => 'All', 'new' => 'New', 'read' => 'Read', 'responded' => 'Responded'] as $val => $label): ?>
            <a href="?status=<?= $val ?>&service=<?= urlencode($serviceFilter) ?>"
               class="mi-filter-btn <?= $statusFilter === $val ? 'active' : '' ?>">
               <?= $label ?>
            </a>
            <?php endforeach; ?>
        </div>

        <?php if (!empty($serviceNames)): ?>
        <span class="mi-filter-label" style="margin-left:6px;">Service:</span>
        <form method="GET" style="display:flex;gap:8px;align-items:center;">
            <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
            <select name="service" class="mi-filter-select" onchange="this.form.submit()">
                <option value="">— All Services —</option>
                <?php foreach ($serviceNames as $sn): ?>
                <option value="<?= htmlspecialchars($sn) ?>" <?= $serviceFilter === $sn ? 'selected' : '' ?>>
                    <?= htmlspecialchars($sn) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </form>
        <?php endif; ?>
    </div>

    <!-- Desktop table -->
    <div class="mi-table-wrap">
        <?php if (empty($inquiries)): ?>
        <div class="mi-empty">
            <span class="mi-empty-icon">📬</span>
            No inquiries found<?= $statusFilter !== 'all' || $serviceFilter !== '' ? ' matching your filters' : '' ?>.
        </div>
        <?php else: ?>
        <table class="mi-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Customer</th>
                    <th>Service</th>
                    <th>Contact</th>
                    <th>Message</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($inquiries as $inq): ?>
            <tr>
                <td style="color:#555;font-size:.78rem;"><?= $inq['id'] ?></td>
                <td class="td-name">
                    <?= htmlspecialchars($inq['name']) ?>
                    <div style="font-weight:400;font-size:.78rem;color:#888;margin-top:2px;">
                        <a href="mailto:<?= htmlspecialchars($inq['email']) ?>" style="color:#888;text-decoration:none;">
                            <?= htmlspecialchars($inq['email']) ?>
                        </a>
                    </div>
                </td>
                <td class="td-svc">
                    <span style="background:rgba(237,141,27,.1);color:#ed8d1b;border:1px solid rgba(237,141,27,.3);border-radius:6px;padding:3px 9px;font-size:.75rem;font-weight:700;white-space:nowrap;">
                        <?= htmlspecialchars($inq['service_name'] ?: '—') ?>
                    </span>
                </td>
                <td class="td-contact">
                    <?= htmlspecialchars($inq['contact']) ?>
                </td>
                <td class="td-msg"><?= nl2br(htmlspecialchars($inq['message'])) ?></td>
                <td>
                    <span class="mi-badge <?= $inq['status'] ?>">
                        <?= $inq['status'] === 'new' ? '● New' : ($inq['status'] === 'read' ? '○ Read' : '✓ Responded') ?>
                    </span>
                </td>
                <td class="td-date"><?= date('M j, Y<\b\r>g:i A', strtotime($inq['created_at'])) ?></td>
                <td>
                    <div class="mi-actions">
                        <button class="mi-act-btn" type="button"
                            onclick="openDetail(<?= htmlspecialchars(json_encode($inq), ENT_QUOTES) ?>)">View</button>
                        <?php if ($inq['status'] === 'new'): ?>
                        <form method="POST" style="margin:0;">
                            <input type="hidden" name="inq_id" value="<?= $inq['id'] ?>">
                            <input type="hidden" name="action" value="mark_read">
                            <button class="mi-act-btn" type="submit">Mark Read</button>
                        </form>
                        <?php endif; ?>
                        <?php if ($inq['status'] !== 'responded'): ?>
                        <form method="POST" style="margin:0;">
                            <input type="hidden" name="inq_id" value="<?= $inq['id'] ?>">
                            <input type="hidden" name="action" value="mark_responded">
                            <button class="mi-act-btn btn-responded" type="submit">Responded</button>
                        </form>
                        <?php endif; ?>
                        <form method="POST" style="margin:0;"
                            onsubmit="return confirm('Delete this inquiry from <?= htmlspecialchars(addslashes($inq['name'])) ?>?')">
                            <input type="hidden" name="inq_id" value="<?= $inq['id'] ?>">
                            <input type="hidden" name="action" value="delete">
                            <button class="mi-act-btn btn-del" type="submit">Delete</button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <!-- Mobile cards -->
    <div class="mi-card-list">
        <?php if (empty($inquiries)): ?>
        <div class="mi-empty">
            <span class="mi-empty-icon">📬</span>
            No inquiries found<?= $statusFilter !== 'all' || $serviceFilter !== '' ? ' matching your filters' : '' ?>.
        </div>
        <?php else: ?>
        <?php foreach ($inquiries as $inq): ?>
        <div class="mi-card">
            <div class="mi-card-top">
                <div>
                    <div class="mi-card-name"><?= htmlspecialchars($inq['name']) ?></div>
                    <div class="mi-card-svc">🔧 <?= htmlspecialchars($inq['service_name'] ?: 'General') ?></div>
                </div>
                <span class="mi-badge <?= $inq['status'] ?>">
                    <?= $inq['status'] === 'new' ? '● New' : ($inq['status'] === 'read' ? '○ Read' : '✓ Done') ?>
                </span>
            </div>
            <p class="mi-card-msg"><?= htmlspecialchars($inq['message']) ?></p>
            <div class="mi-card-meta">
                <span>📞 <?= htmlspecialchars($inq['contact']) ?></span>
                <span>✉ <?= htmlspecialchars($inq['email']) ?></span>
                <span><?= date('M j, Y', strtotime($inq['created_at'])) ?></span>
            </div>
            <div class="mi-card-actions">
                <button class="mi-act-btn" type="button"
                    onclick="openDetail(<?= htmlspecialchars(json_encode($inq), ENT_QUOTES) ?>)">View</button>
                <?php if ($inq['status'] === 'new'): ?>
                <form method="POST" style="margin:0;">
                    <input type="hidden" name="inq_id" value="<?= $inq['id'] ?>">
                    <input type="hidden" name="action" value="mark_read">
                    <button class="mi-act-btn" type="submit">Mark Read</button>
                </form>
                <?php endif; ?>
                <?php if ($inq['status'] !== 'responded'): ?>
                <form method="POST" style="margin:0;">
                    <input type="hidden" name="inq_id" value="<?= $inq['id'] ?>">
                    <input type="hidden" name="action" value="mark_responded">
                    <button class="mi-act-btn btn-responded" type="submit">Responded</button>
                </form>
                <?php endif; ?>
                <form method="POST" style="margin:0;"
                    onsubmit="return confirm('Delete this inquiry?')">
                    <input type="hidden" name="inq_id" value="<?= $inq['id'] ?>">
                    <input type="hidden" name="action" value="delete">
                    <button class="mi-act-btn btn-del" type="submit">Delete</button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div><!-- /mi-page -->

<!-- ── Detail modal ─────────────────────────────────────────────────────── -->
<div class="mi-modal-ov" id="mi-detail-modal">
    <div class="mi-modal">
        <button class="mi-modal-close" onclick="closeDetail()">✕</button>
        <h3>Inquiry <span>#<span id="md-id"></span></span></h3>

        <div class="mi-detail-row">
            <div class="mi-detail-label">Status</div>
            <div class="mi-detail-val" id="md-status"></div>
        </div>
        <div class="mi-detail-row">
            <div class="mi-detail-label">Service</div>
            <div class="mi-detail-val" id="md-service"></div>
        </div>
        <hr class="mi-divider">
        <div class="mi-detail-row">
            <div class="mi-detail-label">Customer Name</div>
            <div class="mi-detail-val" id="md-name"></div>
        </div>
        <div class="mi-detail-row">
            <div class="mi-detail-label">Contact Number</div>
            <div class="mi-detail-val" id="md-contact"></div>
        </div>
        <div class="mi-detail-row">
            <div class="mi-detail-label">Email</div>
            <div class="mi-detail-val" id="md-email"></div>
        </div>
        <hr class="mi-divider">
        <div class="mi-detail-row">
            <div class="mi-detail-label">Message</div>
            <div class="mi-detail-val" id="md-message" style="white-space:pre-wrap;"></div>
        </div>
        <div class="mi-detail-row">
            <div class="mi-detail-label">Received</div>
            <div class="mi-detail-val" id="md-date"></div>
        </div>

        <div class="mi-modal-actions" id="md-actions"></div>
    </div>
</div>

<script src="assets/bootstrap/js/bootstrap.min.js"></script>
<script>
/* Flash auto-dismiss */
const fl = document.getElementById('mi-flash');
if (fl) setTimeout(() => fl.style.display = 'none', 4000);

/* Detail modal */
let currentInq = null;

function openDetail(inq) {
    currentInq = inq;
    document.getElementById('md-id').textContent      = inq.id;
    document.getElementById('md-service').textContent = inq.service_name || '—';
    document.getElementById('md-name').textContent    = inq.name;
    document.getElementById('md-contact').textContent = inq.contact;
    document.getElementById('md-email').innerHTML     = `<a href="mailto:${inq.email}">${inq.email}</a>`;
    document.getElementById('md-message').textContent = inq.message;
    document.getElementById('md-date').textContent    = inq.created_at;

    const badgeMap = { new: 'new', read: 'read', responded: 'responded' };
    const labelMap = { new: '● New', read: '○ Read', responded: '✓ Responded' };
    document.getElementById('md-status').innerHTML =
        `<span class="mi-badge ${badgeMap[inq.status]}">${labelMap[inq.status]}</span>`;

    // Build action buttons
    let actions = '';
    if (inq.status === 'new') {
        actions += `<form method="POST" style="margin:0;">
            <input type="hidden" name="inq_id" value="${inq.id}">
            <input type="hidden" name="action" value="mark_read">
            <button class="mi-modal-btn" type="submit">Mark as Read</button>
        </form>`;
    }
    if (inq.status !== 'responded') {
        actions += `<form method="POST" style="margin:0;">
            <input type="hidden" name="inq_id" value="${inq.id}">
            <input type="hidden" name="action" value="mark_responded">
            <button class="mi-modal-btn btn-responded" type="submit">Mark as Responded</button>
        </form>`;
    }
    actions += `<form method="POST" style="margin:0;"
        onsubmit="return confirm('Delete this inquiry?')">
        <input type="hidden" name="inq_id" value="${inq.id}">
        <input type="hidden" name="action" value="delete">
        <button class="mi-modal-btn btn-del" type="submit">Delete</button>
    </form>`;
    actions += `<button class="mi-modal-btn btn-cancel" type="button" onclick="closeDetail()">Close</button>`;
    document.getElementById('md-actions').innerHTML = actions;

    document.getElementById('mi-detail-modal').classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closeDetail() {
    document.getElementById('mi-detail-modal').classList.remove('open');
    document.body.style.overflow = '';
}
document.getElementById('mi-detail-modal').addEventListener('click', e => {
    if (e.target === e.currentTarget) closeDetail();
});
</script>

</body>
<?php require 'footer.php'; ?>
</html>
