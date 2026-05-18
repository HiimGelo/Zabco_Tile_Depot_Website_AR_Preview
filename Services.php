<?php
session_start();
include 'db_connect.php';

$isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

// ── Upload helper ─────────────────────────────────────────────────────────────
function uploadImg(string $field, string $destDir): ?string {
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) return null;
    $f       = $_FILES[$field];
    $allowed = ['image/jpeg','image/png','image/gif','image/webp'];
    $mime    = mime_content_type($f['tmp_name']);
    if (!in_array($mime, $allowed) || $f['size'] > 5 * 1024 * 1024) return null;
    $ext  = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    $name = uniqid('svc_') . '.' . $ext;
    if (!is_dir($destDir)) mkdir($destDir, 0755, true);
    $dest = rtrim($destDir, '/') . '/' . $name;
    return move_uploaded_file($f['tmp_name'], $dest) ? $dest : null;
}

// ── Service Inquiry (any visitor) – saves to DB ──────────────────────
$inquirySuccess = false;
$inquiryError   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['admin_action'] ?? '') === 'avail_service') {
    $svcLabel = trim(strip_tags($_POST['inquiry_service'] ?? ''));
    $iName    = trim(strip_tags($_POST['inquiry_name']    ?? ''));
    $iContact = trim(strip_tags($_POST['inquiry_contact'] ?? ''));
    $iEmail   = trim(strip_tags($_POST['inquiry_email']   ?? ''));
    $iMsg     = trim(strip_tags($_POST['inquiry_message'] ?? ''));

    if ($iName === '' || $iContact === '' || $iEmail === '' || $iMsg === '') {
        $inquiryError = 'Please fill in all required fields.';
    } elseif (!filter_var($iEmail, FILTER_VALIDATE_EMAIL)) {
        $inquiryError = 'Please enter a valid email address.';
    } else {
        // Ensure table exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS service_inquiries (
            id           INT AUTO_INCREMENT PRIMARY KEY,
            service_name VARCHAR(255) NOT NULL DEFAULT '',
            name         VARCHAR(255) NOT NULL,
            contact      VARCHAR(100) NOT NULL,
            email        VARCHAR(255) NOT NULL,
            message      TEXT         NOT NULL,
            status       ENUM('new','read','responded') NOT NULL DEFAULT 'new',
            source       VARCHAR(50)  NOT NULL DEFAULT 'services',
            created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
        )");
        $pdo->prepare("INSERT INTO service_inquiries
            (service_name, name, contact, email, message, source)
            VALUES (?, ?, ?, ?, ?, 'services')")
            ->execute([$svcLabel, $iName, $iContact, $iEmail, $iMsg]);
        header('Location: Services.php?inquired=1'); exit;
    }
}

// ── Admin POST handler ────────────────────────────────────────────────────────
if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['admin_action'] ?? '';

    if ($act === 'add_service') {
        $name = trim($_POST['svc_name'] ?? '');
        $path = uploadImg('svc_img', 'service_images');
        if ($name && $path) {
            $pdo->prepare("INSERT INTO services (ServiceName, ImagePath) VALUES (?,?)")->execute([$name, $path]);
        }
        header('Location: Services.php?added=1'); exit;
    }

    if ($act === 'edit_service') {
        $id   = (int)($_POST['svc_id']   ?? 0);
        $name = trim($_POST['svc_name']  ?? '');
        if ($id && $name) {
            $path = uploadImg('svc_img', 'service_images');
            if ($path) {
                $pdo->prepare("UPDATE services SET ServiceName=?, ImagePath=? WHERE ServiceID=?")->execute([$name, $path, $id]);
            } else {
                $pdo->prepare("UPDATE services SET ServiceName=? WHERE ServiceID=?")->execute([$name, $id]);
            }
        }
        header('Location: Services.php?edited=1'); exit;
    }

    if ($act === 'delete_service') {
        $id = (int)($_POST['svc_id'] ?? 0);
        if ($id) $pdo->prepare("DELETE FROM services WHERE ServiceID=?")->execute([$id]);
        header('Location: Services.php?deleted=1'); exit;
    }
}

// ── Load services ─────────────────────────────────────────────────────────────
try {
    $services = $pdo->query("SELECT * FROM services ORDER BY ServiceID")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $services = []; }
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light" data-bss-forced-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Our Services – Zabco Tile Depot</title>
    <link rel="icon" type="image/ico" href="Favicon.ico">
    <link rel="stylesheet" href="assets/bootstrap/css/bootstrap.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">
    <style>
      *, *::before, *::after { box-sizing: border-box; }
      body { background: #f9f9f9; font-family: 'Inter', sans-serif; margin: 0; }

      /* ── Hero ── */
      .svc-hero {
        background: #151616;
        padding: 72px 40px 64px;
        text-align: center;
        position: relative;
        overflow: hidden;
      }
      .svc-hero::before {
        content: '';
        position: absolute; inset: 0;
        background: radial-gradient(ellipse at 60% 40%, rgba(237,141,27,.13) 0%, transparent 70%);
        pointer-events: none;
      }
      .svc-hero-eyebrow {
        display: inline-flex; align-items: center; gap: 8px;
        font-size: .72rem; font-weight: 700; letter-spacing: 2px;
        text-transform: uppercase; color: #ed8d1b; margin-bottom: 14px;
      }
      .svc-hero-eyebrow span { opacity: .5; }
      .svc-hero h1 {
        font-size: clamp(2rem, 5vw, 3.2rem);
        font-weight: 900; color: #fff; margin: 0 0 14px;
        letter-spacing: -.5px; line-height: 1.1;
      }
      .svc-hero h1 span { color: #ed8d1b; }
      .svc-hero p {
        color: #aaa; font-size: clamp(.9rem, 1.5vw, 1.05rem);
        max-width: 520px; margin: 0 auto; line-height: 1.7;
      }

      /* ── Section wrapper ── */
      .svc-section {
        max-width: 1400px; margin: 0 auto;
        padding: 64px 40px 80px;
      }
      .svc-section-header {
        text-align: center; margin-bottom: 48px;
      }
      .svc-section-eyebrow {
        display: inline-flex; align-items: center; gap: 8px;
        font-size: .72rem; font-weight: 700; letter-spacing: 2px;
        text-transform: uppercase; color: #ed8d1b; margin-bottom: 10px;
      }
      .svc-section-eyebrow span { opacity: .45; }
      .svc-section-header h2 {
        font-size: clamp(1.6rem, 3vw, 2.2rem);
        font-weight: 900; color: #151616; margin: 0; letter-spacing: -.3px;
      }
      .svc-section-header h2 span { color: #ed8d1b; }
      .svc-section-header p {
        color: #777; font-size: .95rem; margin: 10px auto 0;
        max-width: 500px; line-height: 1.65;
      }

      /* ── Admin add row ── */
      .admin-add-row {
        display: flex; justify-content: center; margin-bottom: 36px;
      }
      .admin-add-btn {
        background: #ed8d1b; color: #151616; border: none;
        border-radius: 10px; padding: 11px 24px;
        font-weight: 800; font-size: 14px; cursor: pointer;
        transition: background .2s; display: flex; align-items: center; gap: 8px;
      }
      .admin-add-btn:hover { background: #c97415; }

      /* ── Grid ── */
      .svc-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 28px;
      }

      /* ── Card ── */
      .svc-card {
        background: #fff; border-radius: 18px; overflow: hidden;
        box-shadow: 0 4px 18px rgba(0,0,0,.07);
        transition: transform .22s, box-shadow .22s;
        position: relative; cursor: pointer;
        border: 2px solid transparent;
      }
      .svc-card:hover {
        transform: translateY(-7px);
        box-shadow: 0 18px 44px rgba(0,0,0,.13);
        border-color: rgba(237,141,27,.35);
      }
      .svc-img-wrap {
        aspect-ratio: 4 / 3; overflow: hidden; background: #111;
        position: relative;
      }
      .svc-img {
        width: 100%; height: 100%; object-fit: cover;
        display: block; transition: transform .38s ease;
      }
      .svc-card:hover .svc-img { transform: scale(1.07); }

      /* Avail overlay on card hover */
      .svc-avail-overlay {
        position: absolute; inset: 0;
        background: rgba(21,22,22,.52);
        display: flex; align-items: center; justify-content: center;
        opacity: 0; transition: opacity .22s; pointer-events: none;
      }
      .svc-card:hover .svc-avail-overlay { opacity: 1; }
      .svc-avail-badge {
        background: #ed8d1b; color: #151616;
        font-weight: 800; font-size: .85rem; letter-spacing: .4px;
        padding: 10px 22px; border-radius: 30px;
        box-shadow: 0 4px 16px rgba(237,141,27,.45);
        transform: translateY(6px); transition: transform .22s;
      }
      .svc-card:hover .svc-avail-badge { transform: translateY(0); }

      .svc-name {
        padding: 18px 20px 20px;
        font-size: 1rem; font-weight: 800; color: #151616;
        margin: 0; text-align: center; letter-spacing: .1px;
        border-top: 2px solid #f0f0f0;
        display: flex; align-items: center; justify-content: center; gap: 8px;
      }
      .svc-name-arrow {
        color: #ed8d1b; font-size: .8rem; opacity: 0;
        transition: opacity .2s, transform .2s; transform: translateX(-4px);
      }
      .svc-card:hover .svc-name-arrow { opacity: 1; transform: translateX(0); }

      /* Admin card overlay */
      .svc-card-wrap { position: relative; }
      .svc-admin-actions {
        position: absolute; top: 10px; right: 10px;
        display: flex; gap: 6px;
        opacity: 0; transition: opacity .18s; z-index: 20;
      }
      .svc-card:hover .svc-admin-actions { opacity: 1; }
      .svc-edit, .svc-del {
        border: none; border-radius: 6px;
        padding: 5px 12px; font-size: 12px; font-weight: 800; cursor: pointer;
      }
      .svc-edit { background: #ed8d1b; color: #151616; }
      .svc-del  { background: #8b1a1a; color: #fff; }

      /* ── Admin badge ── */
      .admin-badge {
        position: fixed; bottom: 20px; right: 20px;
        background: #ed8d1b; color: #151616; font-weight: 800;
        font-size: 11px; letter-spacing: .8px; padding: 6px 14px;
        border-radius: 100px; z-index: 500; text-transform: uppercase;
        box-shadow: 0 4px 16px rgba(237,141,27,.35); pointer-events: none;
      }

      /* ── Toast ── */
      .toast-notif {
        position: fixed; top: 100px; left: 50%; transform: translateX(-50%);
        background: #162216; border: 1px solid #2d6a2d; color: #7dd87d;
        padding: 10px 24px; border-radius: 10px; font-weight: 700; font-size: 14px;
        z-index: 9999; white-space: nowrap; box-shadow: 0 4px 20px rgba(0,0,0,.3);
      }
      .toast-notif.inquiry { background: #1a1f30; border-color: #3a5a9a; color: #9ac4f8; }

      /* ── Empty state ── */
      .svc-empty {
        grid-column: 1 / -1; text-align: center;
        padding: 72px 20px; color: #aaa; font-size: 1rem;
      }

      /* ═══ MODAL SYSTEM ═══ */
      .aov {
        display: none; position: fixed; inset: 0;
        background: rgba(0,0,0,.78); z-index: 9000;
        align-items: center; justify-content: center;
        padding: 20px; backdrop-filter: blur(4px);
      }
      .aov.open { display: flex; }
      .am {
        background: #1e1e1e; border: 1px solid #2e2e2e;
        border-radius: 20px; padding: 34px 30px;
        width: 100%; max-width: 500px;
        box-shadow: 0 12px 48px rgba(0,0,0,.6);
        max-height: 92vh; overflow-y: auto;
      }
      .am h3 {
        color: #fff; font-size: 20px; font-weight: 800;
        margin: 0 0 20px; padding-bottom: 12px;
        border-bottom: 3px solid #ed8d1b;
      }
      .am h3 span { color: #ed8d1b; }
      .am label {
        display: block; font-size: 11px; font-weight: 700;
        color: #888; text-transform: uppercase; letter-spacing: .6px;
        margin-top: 16px; margin-bottom: 6px;
      }
      .am input[type="text"], .am input[type="email"], .am textarea, .am select {
        width: 100%; padding: 11px 14px;
        background: #2a2a2a; border: 1.5px solid #3a3a3a;
        border-radius: 9px; color: #fff; font-family: inherit;
        font-size: 14px; transition: border-color .2s; resize: vertical;
      }
      .am input:focus, .am textarea:focus, .am select:focus {
        outline: none; border-color: #ed8d1b; background: #2f2f2f;
      }
      .am input[type="file"] { width: 100%; color: #aaa; font-size: 13px; padding: 8px 0; }
      .am-cur-img { width: 100%; max-height: 120px; object-fit: cover; border-radius: 8px; margin-top: 8px; border: 1px solid #3a3a3a; }
      .am-actions { display: flex; gap: 10px; margin-top: 24px; flex-wrap: wrap; }
      .am-submit {
        flex: 1; padding: 12px;
        background: #ed8d1b; color: #151616;
        border: none; border-radius: 9px; font-weight: 800; font-size: 14px;
        cursor: pointer; transition: background .2s;
      }
      .am-submit:hover { background: #c97415; }
      .am-cancel {
        padding: 12px 20px; background: transparent; color: #888;
        border: 1.5px solid #3a3a3a; border-radius: 9px;
        font-weight: 700; font-size: 14px; cursor: pointer;
        transition: border-color .2s, color .2s;
      }
      .am-cancel:hover { border-color: #666; color: #ccc; }
      .am-danger {
        padding: 12px 20px; background: #8b1a1a; color: #fff;
        border: none; border-radius: 9px; font-weight: 700; font-size: 14px;
        cursor: pointer; transition: background .2s;
      }
      .am-danger:hover { background: #b52020; }
      .am-note { color: #666; font-size: 12px; margin-top: 6px; }

      /* Inquiry service pill */
      .inquiry-svc-pill {
        display: inline-flex; align-items: center; gap: 7px;
        background: rgba(237,141,27,.15); border: 1px solid rgba(237,141,27,.4);
        color: #ed8d1b; font-size: .8rem; font-weight: 700;
        padding: 6px 14px; border-radius: 20px; margin-bottom: 6px;
      }

      /* Feedback inline */
      .am-feedback {
        border-radius: 8px; padding: 10px 14px; margin-bottom: 14px;
        font-size: 13px; font-weight: 600;
      }
      .am-feedback.error {
        background: #3a1a1a; color: #cf6f6f; border: 1px solid #5a2d2d;
      }

      /* ── Responsive ── */
      @media (max-width: 1000px) { .svc-grid { grid-template-columns: repeat(2, 1fr); gap: 20px; } }
      @media (max-width: 640px)  {
        .svc-grid { grid-template-columns: 1fr; gap: 16px; }
        .svc-section { padding: 44px 18px 60px; }
        .svc-hero { padding: 52px 20px 48px; }
        .svc-avail-badge { opacity: 1; }
        .svc-name-arrow { opacity: 1; transform: none; }
      }
      @media (max-width: 480px){
          body{ padding-top: 65px !important;}
      }
    </style>
</head>
<body>
<?php require 'header.php'; ?>

<?php if ($isAdmin): ?>
<div class="admin-badge">⚙ Admin Mode</div>
<?php if (isset($_GET['added']))   echo '<div class="toast-notif" id="tn">✓ Service added!</div>'; ?>
<?php if (isset($_GET['edited']))  echo '<div class="toast-notif" id="tn">✓ Service updated!</div>'; ?>
<?php if (isset($_GET['deleted'])) echo '<div class="toast-notif" id="tn">✓ Service deleted.</div>'; ?>
<?php endif; ?>
<?php if (isset($_GET['inquired'])): ?>
<div class="toast-notif inquiry" id="tn">✓ Your inquiry has been sent! We'll be in touch soon.</div>
<?php endif; ?>

<!-- ── Hero ── -->
<section class="svc-hero">
    <div class="svc-hero-eyebrow"><span>—</span> Zabco Tile Depot <span>—</span></div>
    <h1>Our <span>Services</span></h1>
    <p>From free tile estimates to expert installation — we've got everything you need to build your best place.</p>
</section>

<!-- ── Services Grid ── -->
<section class="svc-section">
    <div class="svc-section-header">
        <div class="svc-section-eyebrow"><span>—</span> What We Offer <span>—</span></div>
        <h2>Browse & <span>Avail</span></h2>
        <p>Click any service card to send us an inquiry and we'll get back to you as soon as possible.</p>
    </div>

    <?php if ($isAdmin): ?>
    <div class="admin-add-row">
        <button class="admin-add-btn" onclick="openModal('modal-add-service')" type="button">
            + Add New Service
        </button>
    </div>
    <?php endif; ?>

    <div class="svc-grid">
        <?php if (empty($services)): ?>
        <div class="svc-empty">
            <p>No services yet.<?= $isAdmin ? ' Click "Add New Service" to get started.' : '' ?></p>
        </div>
        <?php else: ?>
        <?php foreach ($services as $svc): ?>
        <div class="svc-card <?= $isAdmin ? 'svc-card-wrap' : '' ?>"
            <?= !$isAdmin ? 'onclick="openInquiry(\'' . addslashes(htmlspecialchars($svc['ServiceName'], ENT_QUOTES)) . '\')" role="button" tabindex="0"' : '' ?>>

            <?php if ($isAdmin): ?>
            <div class="svc-admin-actions">
                <button class="svc-edit" type="button"
                    onclick="event.stopPropagation(); openEditService(<?= $svc['ServiceID'] ?>,'<?= addslashes(htmlspecialchars($svc['ServiceName'])) ?>','<?= addslashes(htmlspecialchars($svc['ImagePath'])) ?>')">
                    ✎ Edit
                </button>
                <button class="svc-del" type="button"
                    onclick="event.stopPropagation(); openDeleteService(<?= $svc['ServiceID'] ?>,'<?= addslashes(htmlspecialchars($svc['ServiceName'])) ?>')">
                    ✕ Delete
                </button>
            </div>
            <?php endif; ?>

            <div class="svc-img-wrap">
                <img src="<?= htmlspecialchars($svc['ImagePath']) ?>"
                     alt="<?= htmlspecialchars($svc['ServiceName']) ?>"
                     class="svc-img">
                <?php if (!$isAdmin): ?>
                <div class="svc-avail-overlay">
                    <span class="svc-avail-badge">Avail This Service →</span>
                </div>
                <?php endif; ?>
            </div>
            <p class="svc-name">
                <?= htmlspecialchars($svc['ServiceName']) ?>
                <?php if (!$isAdmin): ?><span class="svc-name-arrow">→</span><?php endif; ?>
            </p>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>

<!-- ════════════════════════════════════════
     SERVICE INQUIRY MODAL (all users)
════════════════════════════════════════ -->
<div class="aov" id="modal-inquiry">
    <div class="am">
        <h3>Avail a <span>Service</span></h3>
        <?php if ($inquiryError !== ''): ?>
        <div class="am-feedback error">✕ <?= htmlspecialchars($inquiryError) ?></div>
        <?php endif; ?>
        <form method="POST" action="Services.php">
            <input type="hidden" name="admin_action" value="avail_service">
            <input type="hidden" name="inquiry_service" id="inquiry-service-name">
            <div class="inquiry-svc-pill" id="inquiry-svc-label">🔧 Service Name</div>
            <label>Your Name <span style="color:#e05;">*</span></label>
            <input type="text" name="inquiry_name" placeholder="Your full name" required>
            <label>Contact Number <span style="color:#e05;">*</span></label>
            <input type="text" name="inquiry_contact" placeholder="e.g. 09XX XXX XXXX" required>
            <label>Email Address <span style="color:#e05;">*</span></label>
            <input type="text" name="inquiry_email" placeholder="you@example.com" required>
            <label>Message <span style="color:#e05;">*</span></label>
            <textarea name="inquiry_message" rows="4" placeholder="Tell us more about what you need..." required></textarea>
            <div class="am-actions">
                <button type="button" class="am-cancel" onclick="closeModal('modal-inquiry')">Cancel</button>
                <button type="submit" class="am-submit">Send Inquiry</button>
            </div>
        </form>
    </div>
</div>

<?php if ($isAdmin): ?>
<!-- ════════════════════════════════════════
     ADMIN MODALS
════════════════════════════════════════ -->

<!-- Add Service -->
<div class="aov" id="modal-add-service">
    <div class="am">
        <h3>Add New Service</h3>
        <form method="POST" enctype="multipart/form-data" action="Services.php">
            <input type="hidden" name="admin_action" value="add_service">
            <label>Service Name</label>
            <input type="text" name="svc_name" placeholder="e.g. Free Tile Estimate" required>
            <label>Service Image (JPG / PNG / WEBP, max 5 MB)</label>
            <input type="file" name="svc_img" accept="image/*" required>
            <div class="am-actions">
                <button type="button" class="am-cancel" onclick="closeModal('modal-add-service')">Cancel</button>
                <button type="submit" class="am-submit">Add Service</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Service -->
<div class="aov" id="modal-edit-service">
    <div class="am">
        <h3>Edit Service</h3>
        <form method="POST" enctype="multipart/form-data" action="Services.php">
            <input type="hidden" name="admin_action" value="edit_service">
            <input type="hidden" name="svc_id" id="edit-svc-id">
            <label>Service Name</label>
            <input type="text" name="svc_name" id="edit-svc-name" required>
            <label>Current Image</label>
            <img id="edit-svc-preview" class="am-cur-img" src="" alt="Current">
            <label>Replace Image (optional)</label>
            <input type="file" name="svc_img" accept="image/*">
            <p class="am-note">Leave the file input empty to keep the existing image.</p>
            <div class="am-actions">
                <button type="button" class="am-cancel" onclick="closeModal('modal-edit-service')">Cancel</button>
                <button type="submit" class="am-submit">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Service Confirm -->
<div class="aov" id="modal-delete-service">
    <div class="am">
        <h3>Delete Service</h3>
        <p style="color:#ccc; margin-bottom:6px;">Are you sure you want to delete<br>
        "<span id="del-svc-name" style="color:#fff;font-weight:700;"></span>"?<br>
        <span style="color:#666;font-size:13px;">This cannot be undone.</span></p>
        <form method="POST" action="Services.php">
            <input type="hidden" name="admin_action" value="delete_service">
            <input type="hidden" name="svc_id" id="del-svc-id">
            <div class="am-actions">
                <button type="button" class="am-cancel" onclick="closeModal('modal-delete-service')">Cancel</button>
                <button type="submit" class="am-danger">Yes, Delete</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script src="assets/bootstrap/js/bootstrap.min.js"></script>
<script>
/* ── Toast auto-dismiss ── */
const tn = document.getElementById('tn');
if (tn) setTimeout(() => tn.remove(), 3500);

/* ── Modal helpers ── */
function openModal(id)  { const el=document.getElementById(id); if(el){ el.classList.add('open'); document.body.style.overflow='hidden'; } }
function closeModal(id) { const el=document.getElementById(id); if(el){ el.classList.remove('open'); document.body.style.overflow=''; } }
document.querySelectorAll('.aov').forEach(ov => ov.addEventListener('click', e => { if(e.target===ov) closeModal(ov.id); }));

/* ── Service inquiry ── */
function openInquiry(serviceName) {
    document.getElementById('inquiry-service-name').value = serviceName;
    document.getElementById('inquiry-svc-label').textContent = '🔧 ' + serviceName;
    openModal('modal-inquiry');
}
/* Keyboard accessibility for cards */
document.querySelectorAll('.svc-card[role="button"]').forEach(card => {
    card.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') card.click(); });
});

<?php if ($isAdmin): ?>
/* ── Admin modal helpers ── */
function openEditService(id, name, imgPath) {
    document.getElementById('edit-svc-id').value    = id;
    document.getElementById('edit-svc-name').value  = name;
    document.getElementById('edit-svc-preview').src = imgPath;
    openModal('modal-edit-service');
}
function openDeleteService(id, name) {
    document.getElementById('del-svc-id').value         = id;
    document.getElementById('del-svc-name').textContent = name;
    openModal('modal-delete-service');
}
<?php endif; ?>

<?php if ($inquiryError !== ''): ?>
/* Re-open inquiry modal if there was an error */
openModal('modal-inquiry');
<?php endif; ?>
</script>

</body>
<?php require 'footer.php'; ?>
</html>