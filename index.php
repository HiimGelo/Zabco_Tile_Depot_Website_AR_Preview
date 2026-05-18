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
    $name = uniqid('img_') . '.' . $ext;
    if (!is_dir($destDir)) mkdir($destDir, 0755, true);
    $dest = rtrim($destDir, '/') . '/' . $name;
    return move_uploaded_file($f['tmp_name'], $dest) ? $dest : null;
}

// ── Upload multiple images helper ─────────────────────────────────────────────
function uploadImgs(string $field, string $destDir): array {
    $paths = [];
    if (!isset($_FILES[$field]['name']) || !is_array($_FILES[$field]['name'])) return $paths;
    $allowed = ['image/jpeg','image/png','image/gif','image/webp'];
    if (!is_dir($destDir)) mkdir($destDir, 0755, true);
    $count = count($_FILES[$field]['name']);
    for ($i = 0; $i < $count; $i++) {
        if ($_FILES[$field]['error'][$i] !== UPLOAD_ERR_OK) continue;
        $tmp  = $_FILES[$field]['tmp_name'][$i];
        $mime = mime_content_type($tmp);
        $size = $_FILES[$field]['size'][$i];
        $name = $_FILES[$field]['name'][$i];
        if (!in_array($mime, $allowed) || $size > 5 * 1024 * 1024) continue;
        $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $dest = rtrim($destDir, '/') . '/' . uniqid('img_') . '.' . $ext;
        if (move_uploaded_file($tmp, $dest)) $paths[] = $dest;
    }
    return $paths;
}

// ── Save setting helper ───────────────────────────────────────────────────────
function setSetting(PDO $pdo, string $k, string $v): void {
    $pdo->prepare("INSERT INTO site_settings (setting_key,setting_value) VALUES(?,?)
                   ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)")
        ->execute([$k, $v]);
}

// ── Service Inquiry handler (any visitor) – saves to DB ─────────────────────
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
            source       VARCHAR(50)  NOT NULL DEFAULT 'homepage',
            created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
        )");
        $pdo->prepare("INSERT INTO service_inquiries
            (service_name, name, contact, email, message, source)
            VALUES (?, ?, ?, ?, ?, 'homepage')")
            ->execute([$svcLabel, $iName, $iContact, $iEmail, $iMsg]);
        header('Location: index.php?inquired=1'); exit;
    }
}

// ── Admin POST handler ────────────────────────────────────────────────────────
if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['admin_action'] ?? '';

    if ($act === 'update_browse_img') {
        $slot   = $_POST['slot'] ?? '';
        $keyMap = [
            'median'        => ['browse_median_img',        'Tile Images/Median'],
            'sophisticated' => ['browse_sophisticated_img', 'Tile Images/Sophisticated'],
            'luxurious'     => ['browse_luxurious_img',     'Tile Images/Luxurious'],
        ];
        if (isset($keyMap[$slot])) {
            $path = uploadImg('img_file', $keyMap[$slot][1]);
            if ($path) setSetting($pdo, $keyMap[$slot][0], $path);
        }
        header('Location: index.php'); exit;
    }

    if ($act === 'update_about_img') {
        $path = uploadImg('img_file', 'uploads');
        if ($path) setSetting($pdo, 'index_about_img', $path);
        header('Location: index.php'); exit;
    }

    if ($act === 'add_service') {
        $name = trim($_POST['svc_name'] ?? '');
        $path = uploadImg('svc_img', 'service_images');
        if ($name && $path) {
            $pdo->prepare("INSERT INTO services (ServiceName, ImagePath) VALUES (?,?)")->execute([$name, $path]);
        }
        header('Location: index.php'); exit;
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
        header('Location: index.php'); exit;
    }

    if ($act === 'delete_service') {
        $id = (int)($_POST['svc_id'] ?? 0);
        if ($id) $pdo->prepare("DELETE FROM services WHERE ServiceID=?")->execute([$id]);
        header('Location: index.php'); exit;
    }

    if ($act === 'add_whats_new') {
        $title = trim($_POST['wn_title'] ?? '');
        $desc  = trim($_POST['wn_desc']  ?? '');
        $paths = uploadImgs('wn_img', 'whats_new_images');
        if ($title && !empty($paths)) {
            $pdo->prepare("INSERT INTO whats_new (title, description, image_path) VALUES (?,?,?)")
                ->execute([$title, $desc, json_encode($paths)]);
        }
        header('Location: index.php'); exit;
    }

    if ($act === 'edit_whats_new') {
        $id    = (int)($_POST['wn_id']    ?? 0);
        $title = trim($_POST['wn_title']  ?? '');
        $desc  = trim($_POST['wn_desc']   ?? '');
        if ($id && $title) {
            $keep     = json_decode($_POST['wn_keep_imgs'] ?? '[]', true) ?: [];
            $newPaths = uploadImgs('wn_img', 'whats_new_images');
            $all      = array_merge($keep, $newPaths);
            if (!empty($all)) {
                $pdo->prepare("UPDATE whats_new SET title=?, description=?, image_path=? WHERE id=?")
                    ->execute([$title, $desc, json_encode($all), $id]);
            } else {
                $pdo->prepare("UPDATE whats_new SET title=?, description=? WHERE id=?")
                    ->execute([$title, $desc, $id]);
            }
        }
        header('Location: index.php'); exit;
    }

    if ($act === 'delete_whats_new') {
        $id = (int)($_POST['wn_id'] ?? 0);
        if ($id) $pdo->prepare("DELETE FROM whats_new WHERE id=?")->execute([$id]);
        header('Location: index.php'); exit;
    }

    // ── Review handlers ───────────────────────────────────────────────────────
    if ($act === 'add_review') {
        $text   = trim($_POST['rv_text']   ?? '');
        $label  = trim($_POST['rv_label']  ?? 'Verified Customer');
        $rating = max(1, min(5, (int)($_POST['rv_rating'] ?? 5)));
        if ($text) {
            $pdo->prepare("INSERT INTO customer_reviews (review_text, reviewer_label, rating) VALUES (?,?,?)")
                ->execute([$text, $label ?: 'Verified Customer', $rating]);
        }
        header('Location: index.php'); exit;
    }

    if ($act === 'edit_review') {
        $id     = (int)($_POST['rv_id']    ?? 0);
        $text   = trim($_POST['rv_text']   ?? '');
        $label  = trim($_POST['rv_label']  ?? 'Verified Customer');
        $rating = max(1, min(5, (int)($_POST['rv_rating'] ?? 5)));
        if ($id && $text) {
            $pdo->prepare("UPDATE customer_reviews SET review_text=?, reviewer_label=?, rating=? WHERE id=?")
                ->execute([$text, $label ?: 'Verified Customer', $rating, $id]);
        }
        header('Location: index.php'); exit;
    }

    if ($act === 'delete_review') {
        $id = (int)($_POST['rv_id'] ?? 0);
        if ($id) $pdo->prepare("DELETE FROM customer_reviews WHERE id=?")->execute([$id]);
        header('Location: index.php'); exit;
    }

    if ($act === 'toggle_review') {
        $id = (int)($_POST['rv_id'] ?? 0);
        if ($id) $pdo->prepare("UPDATE customer_reviews SET is_hidden = NOT is_hidden WHERE id=?")->execute([$id]);
        header('Location: index.php'); exit;
    }
}

// ── Load settings ─────────────────────────────────────────────────────────────
$settings = [];
try {
    $rows = $pdo->query("SELECT setting_key, setting_value FROM site_settings")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) $settings[$r['setting_key']] = $r['setting_value'];
} catch (PDOException $e) {}

$browseImgs = [
    'median'        => $settings['browse_median_img']        ?? 'Tile Images/Median/medianCover.jpg',
    'sophisticated' => $settings['browse_sophisticated_img'] ?? 'Tile Images/Sophisticated/sophisticatedCover.jpg',
    'luxurious'     => $settings['browse_luxurious_img']     ?? 'Tile Images/Luxurious/luxuriousCover.jpg',
];
$aboutImg = $settings['index_about_img'] ?? 'aboutzab.jpg';

// ── Fetch services ────────────────────────────────────────────────────────────
try {
    $services = $pdo->query("SELECT * FROM services")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $services = []; }

// ── Ensure whats_new table exists & fetch posts ───────────────────────────────
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS whats_new (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        title      VARCHAR(255) NOT NULL,
        description TEXT,
        image_path VARCHAR(500) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    $whatsNew = $pdo->query("SELECT * FROM whats_new ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($whatsNew as &$wn) {
        $decoded = json_decode($wn['image_path'], true);
        $wn['images'] = is_array($decoded) ? $decoded : [$wn['image_path']];
    }
    unset($wn);
} catch (PDOException $e) { $whatsNew = []; }

// ── Ensure customer_reviews table exists & fetch ──────────────────────────────
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS customer_reviews (
        id              INT AUTO_INCREMENT PRIMARY KEY,
        review_text     TEXT NOT NULL,
        reviewer_label  VARCHAR(100) NOT NULL DEFAULT 'Verified Customer',
        rating          TINYINT NOT NULL DEFAULT 5,
        is_hidden       TINYINT(1) NOT NULL DEFAULT 0,
        created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    // Add is_hidden column if it doesn't exist yet (for existing tables)
    try { $pdo->exec("ALTER TABLE customer_reviews ADD COLUMN is_hidden TINYINT(1) NOT NULL DEFAULT 0"); } catch (PDOException $e) {}
    // Admins see all reviews; visitors only see visible ones
    $reviewSQL = $isAdmin
        ? "SELECT * FROM customer_reviews ORDER BY created_at DESC"
        : "SELECT * FROM customer_reviews WHERE is_hidden = 0 ORDER BY created_at DESC";
    $reviews = $pdo->query($reviewSQL)->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $reviews = []; }

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require 'header.php';
?>

<!DOCTYPE html>
<html data-bs-theme="light" lang="en" data-bss-forced-theme="light" style="color:#FFF;background:#f9f9f9;">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Homepage</title>
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    <link rel="stylesheet" href="assets/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="HomeStyles.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">

    <style>
      .nav-toggle { display:none; background:transparent; border:none; cursor:pointer; padding:10px; margin-left:8px; align-self:center; }
      .nav-toggle .bar { display:block; width:22px; height:2px; background:#151616; margin:4px 0; transition:transform .18s ease, opacity .18s ease; }
      html, body { overflow-x: hidden; }
      .logo { display:flex; align-items:center; height:100%; overflow:hidden; padding-left:8px; }
      .logo img { display:block; max-height:72px; width:auto; height:auto; }

      @media (max-width: 1800px){ .search-bar{ margin-left:-550px !important; margin-right:0 !important; } .navbar{ margin-left:0 !important; } }
      @media (max-width: 1600px){ .search-bar{ margin-left:-400px !important; margin-right:0 !important; } .navbar{ margin-left:0 !important; } .items{ padding:40px; padding-top:30px; margin-bottom:20px } }
      @media (max-width: 1440px){ .search-bar{ margin-left:-300px !important; margin-right:0 !important; } .navbar{ margin-left:0 !important; } }
      @media (max-width: 1080px){ .search-icon{ overflow:clip !important; } .search-bar{ margin-left:0 !important; margin-right:0 !important; } .navbar{ margin-left:0 !important; } }
      @media (max-width: 768px) { .search-bar{ margin-left:0 !important; margin-right:0 !important; } .navbar{ margin-left:0 !important; } .nav-toggle{ display:block; } .navbar>ul{ display:none; } header.nav-open .navbar>ul{ display:flex !important; position:absolute !important; top:0 !important; left:0 !important; width:220px !important; max-height:70vh; overflow-y:auto; flex-direction:column; gap:6px; margin:0; padding:8px 0; background:#333 !important; box-shadow:0 8px 28px rgba(0,0,0,.18) !important; border-radius:10px !important; z-index:1400 !important; list-style:none; border:1px solid #000 !important; text-align:center; } header.nav-open .navbar>ul li{ padding:10px 12px; border-radius:6px; } header.nav-open .navbar>ul li a{ display:block; padding:6px 8px; } .logo img{ max-height:56px; } }
      header.nav-open .nav-toggle .bar:nth-child(1){ transform:translateY(6px) rotate(45deg); }
      header.nav-open .nav-toggle .bar:nth-child(2){ opacity:0; }
      header.nav-open .nav-toggle .bar:nth-child(3){ transform:translateY(-6px) rotate(-45deg); }
      .navbar>ul>li{ margin-right:0 !important; text-align:center !important; display:block; }

      @media (max-width:900px){ .container1,.container2,.services,.items{ width:100vw !important; min-width:0 !important; padding-left:0 !important; padding-right:0 !important; } .slider-wrapper img{ height:auto !important; } .wrapper2 img{ width:60vw !important; height:auto !important; max-width:350px !important; } }
      @media (max-width:700px){ body,html{ text-align:center !important; padding-top:35px !important; } .search-bar{ margin-left:0 !important; margin-right:0 !important; } .navbar{ margin-left:0 !important; } .container,.container1,.container2,.services,.items,.items2,.footerArea,.aboutSection,.section1,.section2,.section3,.section4{ display:flex !important; flex-direction:column !important; align-items:center !important; justify-content:center !important; text-align:center !important; } .card-list,.card-wrapper,.card-item,.card-link,.review{ align-items:center !important; justify-content:center !important; text-align:center !important; display:flex !important; flex-direction:column !important; } .search-bar{ justify-content:center !important; align-items:center !important; text-align:center !important; margin-left:0 !important; } .footerArea>div{ align-items:center !important; text-align:center !important; } .aboutSection .description,.categoryLinks,.sec2-links,.contactInfo,.social-media-icons{ align-items:center !important; text-align:center !important; display:flex !important; flex-direction:column !important; } .social-media-icons{ flex-direction:row !important; } }
      @media (max-width:500px){ header{ height:70px !important; min-height:0 !important; padding:0 !important; } .logo img{ max-height:38px !important; } .search-bar input[type="text"]{ width:60vw !important; } }
      @media (max-width:400px){ html,body{ font-size:14px !important; overflow-x:hidden !important; padding-top:27px !important; } header{ height:54px !important; min-height:0 !important; padding:0 !important; } .logo img{ max-height:28px !important; } .nav-toggle{ padding:6px !important; margin-left:2px !important; } .navbar>ul{ width:90vw !important; min-width:0 !important; font-size:1rem !important; } .user img{ width:26px !important; height:26px !important; } .search-bar input[type="text"]{ width:48vw !important; font-size:.95rem !important; padding:6px 4px !important; } .search-bar .search-icon img{ width:28px !important; height:28px !important; } .slider-wrapper img{ max-width:100vw !important; height:auto !important; } .container,.container1,.container2,.services,.items{ width:100vw !important; min-width:0 !important; padding:0 !important; } .card-item{ width:98vw !important; margin:6px auto !important; } .card-image{ max-width:100vw !important; height:auto !important; } .wrapper2 img{ width:80vw !important; max-width:90vw !important; height:auto !important; } .footerArea{ flex-direction:column !important; align-items:flex-start !important; padding:4px !important; } .footerArea>div{ width:100% !important; margin-bottom:8px !important; } .aboutSection .description p,.aboutSection .description h4,.categoryLinks a,.sec2-links a,.contactInfo p,.contactInfo a,.review p{ font-size:.9rem !important; } .social-media-icons img{ width:20px !important; height:20px !important; } .header-border,.header-border a{ font-size:.95rem !important; padding:2px 0 !important; } .services strong,.services span,.services{ font-size:.95rem !important; } h2,h3,h4{ font-size:1rem !important; } button,.card-title,.badge{ font-size:.95rem !important; } .mobile-search-panel{ top:54px; } }
      @media (max-width:500px){ .wrapper2{ width:50%; margin-bottom:50px; } .wrapper2 img{ width:90vw !important; max-width:240px !important; } .container1,.container2,.services,.items{ padding:0 2vw !important; width:100vw !important; } .card-item{ width:98vw !important; margin:8px auto !important; } .card-image{ max-width:100vw !important; height:auto !important; } .footerArea{ flex-direction:column !important; align-items:flex-start !important; padding:8px !important; } .footerArea>div{ width:100% !important; margin-bottom:12px !important; } .wrapper h3{ font-size:32px } .wrapper h3,p{ margin-left:0; } .wrapper button{ width:60%; margin-left:0; height:45px; margin-top:10px; margin-bottom:20px; } .wrapper button a{ font-size:20px; display:flex; justify-content:center; } .services strong,.container2 span{ display:none; } }

      /* ── Admin styles ──────────────────────────────────────── */
      .admin-badge {
        position: fixed; bottom: 20px; right: 20px;
        background: #ed8d1b; color: #151616;
        font-weight: 800; font-size: 11px; letter-spacing: 0.8px;
        padding: 6px 14px; border-radius: 100px;
        z-index: 500; text-transform: uppercase;
        box-shadow: 0 4px 16px rgba(237,141,27,.35);
        pointer-events: none;
      }
      .ae-wrap { position: relative; }
      .ae-btn {
        position: absolute; top: 8px; right: 8px;
        background: rgba(237,141,27,.92); color: #151616;
        border: none; border-radius: 8px;
        padding: 5px 12px; font-size: 12px; font-weight: 800;
        cursor: pointer; opacity: 0; transition: opacity .18s; z-index: 20;
      }
      .ae-wrap:hover .ae-btn { opacity: 1; }
      .ae-actions {
        position: absolute; top: 8px; right: 8px;
        display: flex; gap: 6px;
        opacity: 0; transition: opacity .18s; z-index: 20;
      }
      .ae-wrap:hover .ae-actions { opacity: 1; }
      .ae-edit,.ae-del { border: none; border-radius: 6px; padding: 5px 10px; font-size: 11px; font-weight: 800; cursor: pointer; }
      .ae-edit { background: #ed8d1b; color: #151616; }
      .ae-del  { background: #8b1a1a; color: #fff; }
      .admin-add-row {
        display: flex; justify-content: flex-end;
        padding: 4px 12px 12px;
      }
      .admin-add-btn {
        background: #ed8d1b; color: #151616;
        border: none; border-radius: 10px;
        padding: 9px 18px; font-weight: 800; font-size: 13px;
        cursor: pointer; transition: background .2s;
        display: flex; align-items: center; gap: 6px;
      }
      .admin-add-btn:hover { background: #c97415; }
      /* Modal */
      .aov {
        display: none; position: fixed; inset: 0;
        background: rgba(0,0,0,.76); z-index: 9000;
        align-items: center; justify-content: center;
        padding: 20px; backdrop-filter: blur(3px);
      }
      .aov.open { display: flex; }
      .am {
        background: #1e1e1e; border: 1px solid #2e2e2e;
        border-radius: 18px; padding: 32px 28px;
        width: 100%; max-width: 480px;
        box-shadow: 0 8px 40px rgba(0,0,0,.55);
        max-height: 90vh; overflow-y: auto;
      }
      .am h3 { color: #fff; font-size: 20px; font-weight: 800; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 3px solid #ed8d1b; }
      .am label { display: block; font-size: 11px; font-weight: 700; color: #999; text-transform: uppercase; letter-spacing: .6px; margin-top: 14px; margin-bottom: 5px; }
      .am input[type="text"],.am input[type="number"],.am select,.am textarea { width: 100%; padding: 10px 13px; background: #2a2a2a; border: 1.5px solid #3a3a3a; border-radius: 8px; color: #fff; font-family: inherit; font-size: 14px; transition: border-color .2s; }
      .am input:focus,.am select:focus,.am textarea:focus { outline: none; border-color: #ed8d1b; background: #2f2f2f; }
      .am input[type="file"] { width: 100%; color: #aaa; font-size: 13px; padding: 8px 0; }
      .am-cur-img { width: 80px; height: 60px; object-fit: cover; border-radius: 6px; margin-top: 8px; border: 1px solid #3a3a3a; }
      .am-actions { display: flex; gap: 10px; margin-top: 22px; flex-wrap: wrap; }
      .am-submit { flex: 1; padding: 11px; background: #ed8d1b; color: #151616; border: none; border-radius: 8px; font-weight: 800; font-size: 14px; cursor: pointer; transition: background .2s; }
      .am-submit:hover { background: #c97415; }
      .am-cancel { padding: 11px 18px; background: transparent; color: #888; border: 1.5px solid #3a3a3a; border-radius: 8px; font-weight: 700; font-size: 14px; cursor: pointer; transition: border-color .2s,color .2s; }
      .am-cancel:hover { border-color: #555; color: #ccc; }
      .am-danger { padding: 11px 18px; background: #8b1a1a; color: #fff; border: none; border-radius: 8px; font-weight: 700; font-size: 14px; cursor: pointer; transition: background .2s; }
      .am-danger:hover { background: #b52020; }
      .am-note { color: #888; font-size: 12px; margin-top: 8px; }

      /* ── What's New section ─────────────────────────────────── */
      .whats-new-section {
        padding: 48px 40px 56px;
        background: #f9f9f9;
        max-width: 1400px;
        margin: 0 auto;
      }
      .whats-new-header {
        display: flex; align-items: center; justify-content: space-between;
        margin-bottom: 28px;
        border-bottom: 3px solid #ed8d1b;
        padding-bottom: 12px;
      }
      .whats-new-header h2 {
        font-size: clamp(1.4rem, 3vw, 2rem);
        font-weight: 900;
        color: #151616;
        margin: 0;
      }
      .wn-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 24px;
      }
      .wn-card {
        background: #fff;
        border-radius: 14px;
        overflow: visible;
        box-shadow: 0 4px 18px rgba(0,0,0,.09);
        transition: transform .2s, box-shadow .2s;
        position: relative;
        display: flex;
        flex-direction: column;
      }
      .wn-card:hover { transform: translateY(-4px); box-shadow: 0 10px 32px rgba(0,0,0,.14); }
      .wn-img-wrap {
        border-radius: 14px 14px 0 0;
        overflow: hidden;
        position: relative;
        aspect-ratio: 1 / 1;
        background: #111;
      }
      .wn-card-img {
        width: 100%;
        height: 100%;
        object-fit: contain;
        display: block;
        transition: opacity .3s ease;
      }
      .wn-slides-track {
        display: flex;
        position: absolute;
        top: 0; left: 0;
        width: 100%;
        height: 100%;
        transition: transform .35s ease;
      }
      .wn-slides-track .wn-card-img { flex: 0 0 100%; }
      .wn-slide-btn {
        position: absolute; top: 50%; transform: translateY(-50%);
        background: rgba(0,0,0,.5); color: #fff;
        border: none; border-radius: 50%;
        width: 30px; height: 30px;
        font-size: 16px; line-height: 1;
        cursor: pointer; z-index: 10;
        display: flex; align-items: center; justify-content: center;
        transition: background .2s;
      }
      .wn-slide-btn:hover { background: rgba(237,141,27,.85); }
      .wn-slide-prev { left: 8px; }
      .wn-slide-next { right: 8px; }
      .wn-dots {
        position: absolute; bottom: 8px; left: 50%; transform: translateX(-50%);
        display: flex; gap: 5px; z-index: 10;
      }
      .wn-dot {
        width: 7px; height: 7px; border-radius: 50%;
        background: rgba(255,255,255,.5); cursor: pointer;
        transition: background .2s;
      }
      .wn-dot.active { background: #ed8d1b; }
      .wn-card-body { padding: 16px 18px 20px; flex: 1; display: flex; flex-direction: column; }
      .wn-card-title { font-size: 1rem; font-weight: 800; color: #151616; margin: 0 0 8px; line-height: 1.3; }
      .wn-card-desc { font-size: .875rem; color: #555; line-height: 1.55; margin: 0; flex: 1; }
      .wn-empty { grid-column: 1/-1; text-align: center; color: #999; padding: 48px 0; font-size: 1rem; }
      .wn-card .ae-actions { top: 8px; right: 8px; }

      @media (max-width: 1024px) { .wn-grid { grid-template-columns: repeat(2, 1fr); } }
      @media (max-width: 640px)  { .wn-grid { grid-template-columns: 1fr; gap: 16px; } .whats-new-section { padding: 32px 16px 40px; } }

      /* ── Customer Reviews ───────────────────────────────────── */
      .reviews-section {
        background: #151616;
        padding: 56px 40px 64px;
      }
      .reviews-section-header {
        text-align: center;
        margin-bottom: 40px;
      }
      .reviews-section-header h2 {
        color: #fff;
        font-size: clamp(1.4rem, 3vw, 2rem);
        font-weight: 900;
        margin: 0 0 6px;
      }
      .reviews-section-header h2 span { color: #ed8d1b; }
      .reviews-section-header p { color: #888; font-size: .95rem; margin: 0; }
      /* ── Static grid (≤3 reviews) ── */
      .reviews-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 20px;
        max-width: 1200px;
        margin: 0 auto;
      }

      /* ── Carousel wrapper (>3 reviews) ── */
      .rv-carousel-wrap {
        position: relative;
        max-width: 1200px;
        margin: 0 auto;
      }
      .rv-track-outer {
        overflow: hidden;
        border-radius: 4px;
      }
      .rv-track {
        display: flex;
        gap: 20px;
        transition: transform .42s cubic-bezier(.4,0,.2,1);
        will-change: transform;
      }
      .rv-track .rv-card {
        flex: 0 0 calc((100% - 40px) / 3);
        min-width: 0;
      }

      /* ── Shared card ── */
      .rv-card {
        background: #1e1e1e;
        border: 1px solid #2a2a2a;
        border-radius: 16px;
        padding: 28px 24px 24px;
        display: flex;
        flex-direction: column;
        gap: 16px;
        position: relative;
        transition: transform .2s, border-color .2s;
      }
      .rv-card:hover { transform: translateY(-3px); border-color: #ed8d1b44; }
      .rv-quote-icon {
        font-size: 56px; line-height: 1; color: #ed8d1b; opacity: .25;
        position: absolute; top: 14px; right: 20px;
        font-family: Georgia, serif; pointer-events: none; user-select: none;
      }
      .rv-stars { display: flex; gap: 3px; }
      .rv-star { color: #ed8d1b; font-size: 16px; }
      .rv-star.empty { color: #333; }
      .rv-text {
        color: #ccc;
        font-size: .92rem;
        line-height: 1.7;
        margin: 0;
        max-height: 160px;
        overflow-y: auto;
        scrollbar-width: thin;
        scrollbar-color: #ed8d1b #2a2a2a;
      }
      .rv-text::-webkit-scrollbar { width: 4px; }
      .rv-text::-webkit-scrollbar-track { background: #2a2a2a; border-radius: 4px; }
      .rv-text::-webkit-scrollbar-thumb { background: #ed8d1b; border-radius: 4px; }
      .rv-label {
        color: #555; font-size: .75rem; font-weight: 700;
        text-transform: uppercase; letter-spacing: .8px;
        padding-top: 14px; border-top: 1px solid #2a2a2a;
      }
      .rv-label::before { content: '— '; color: #ed8d1b; }
      .rv-empty { grid-column: 1/-1; text-align: center; color: #555; padding: 48px 0; font-size: 1rem; }
      .reviews-add-row { display: flex; justify-content: center; margin-top: 32px; }
      .rv-card .ae-actions { top: 10px; left: 10px; right: auto; }
      .rv-card.rv-hidden { opacity: 0.38; }
      .rv-card.rv-hidden::after {
          content: 'Hidden from visitors';
          position: absolute; inset: 0;
          display: flex; align-items: center; justify-content: center;
          font-size: .7rem; font-weight: 800; letter-spacing: 1px;
          text-transform: uppercase; color: #ed8d1b;
          background: rgba(0,0,0,.45); border-radius: inherit;
          pointer-events: none;
      }
      .ae-toggle {
          background: #252525; color: #f0a830;
          border: 1px solid rgba(240,168,48,.35);
          border-radius: 5px; padding: 3px 9px;
          font-size: .68rem; font-weight: 800; cursor: pointer;
          font-family: inherit; transition: all .15s;
          white-space: nowrap;
      }
      .ae-toggle:hover { background: #f0a830; color: #151616; border-color: #f0a830; }
      .ae-toggle.is-hidden { color: #4caf7d; border-color: rgba(76,175,125,.35); }
      .ae-toggle.is-hidden:hover { background: #4caf7d; color: #111; border-color: #4caf7d; }

      /* ── Carousel arrows ── */
      .rv-arrow {
        position: absolute;
        top: 50%; transform: translateY(-50%);
        width: 44px; height: 44px;
        border-radius: 50%;
        background: #2a2a2a;
        border: 2px solid #3a3a3a;
        color: #fff;
        font-size: 20px;
        cursor: pointer;
        display: flex; align-items: center; justify-content: center;
        transition: background .18s, border-color .18s, transform .18s;
        z-index: 10;
        box-shadow: 0 4px 14px rgba(0,0,0,.4);
      }
      .rv-arrow:hover {
        background: #ed8d1b; border-color: #ed8d1b;
        transform: translateY(-50%) scale(1.08);
      }
      .rv-arrow:disabled { opacity: .3; cursor: default; pointer-events: none; }
      .rv-prev { left: -22px; }
      .rv-next { right: -22px; }

      /* ── Carousel dots ── */
      .rv-dots {
        display: flex; justify-content: center; gap: 7px;
        margin-top: 28px;
      }
      .rv-dot {
        width: 8px; height: 8px;
        border-radius: 50%;
        background: #333; border: none; cursor: pointer;
        transition: background .2s, width .2s;
        padding: 0;
      }
      .rv-dot.active { background: #ed8d1b; width: 24px; border-radius: 4px; }

      @media (max-width: 900px) {
        .reviews-grid { grid-template-columns: repeat(2,1fr); }
        .rv-track .rv-card { flex: 0 0 calc((100% - 20px) / 2); }
        .rv-prev { left: -14px; } .rv-next { right: -14px; }
      }
      @media (max-width: 560px) {
        .reviews-grid { grid-template-columns: 1fr; }
        .reviews-section { padding: 40px 16px 48px; }
        .rv-track .rv-card { flex: 0 0 100%; }
        .rv-prev { left: -10px; } .rv-next { right: -10px; }
        .rv-arrow { width: 36px; height: 36px; font-size: 16px; }
      }

      /* ═══════════════════════════════════════════════════
         BROWSE PRODUCTS
      ═══════════════════════════════════════════════════ */
      .hp-browse-section {
        padding: 0 40px 60px;
        max-width: 1400px;
        margin: 0 auto;
      }
      .hp-browse-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 28px 0 22px;
        border-bottom: 3px solid #ed8d1b;
        margin-bottom: 28px;
      }
      .hp-browse-header h2 {
        font-size: clamp(1.3rem, 2.8vw, 1.9rem);
        font-weight: 900;
        color: #151616;
        margin: 0;
        letter-spacing: -.3px;
      }
      .hp-browse-header h2 span { color: #ed8d1b; }
      .hp-browse-see-more {
        font-size: .85rem;
        font-weight: 700;
        color: #ed8d1b;
        text-decoration: none;
        border: 1.5px solid #ed8d1b;
        padding: 6px 16px;
        border-radius: 20px;
        transition: background .18s, color .18s;
        white-space: nowrap;
      }
      .hp-browse-see-more:hover { background: #ed8d1b; color: #151616; }
      .hp-browse-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 20px;
      }
      .hp-browse-card {
        position: relative;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 4px 20px rgba(0,0,0,.10);
        transition: transform .25s, box-shadow .25s;
        aspect-ratio: 3 / 4;
      }
      .hp-browse-card:hover { transform: translateY(-6px); box-shadow: 0 14px 40px rgba(0,0,0,.18); }
      .hp-browse-link {
        display: block;
        width: 100%; height: 100%;
        text-decoration: none;
        position: relative;
      }
      .hp-browse-img {
        width: 100%; height: 100%;
        object-fit: cover;
        display: block;
        transition: transform .4s ease;
      }
      .hp-browse-card:hover .hp-browse-img { transform: scale(1.06); }
      .hp-browse-overlay {
        position: absolute;
        inset: 0;
        background: linear-gradient(to top, rgba(0,0,0,.78) 0%, rgba(0,0,0,.15) 55%, transparent 100%);
        display: flex;
        flex-direction: column;
        justify-content: flex-end;
        padding: 24px 22px;
        transition: background .25s;
      }
      .hp-browse-card:hover .hp-browse-overlay {
        background: linear-gradient(to top, rgba(0,0,0,.88) 0%, rgba(0,0,0,.28) 55%, transparent 100%);
      }
      .hp-browse-tier {
        font-size: .72rem;
        font-weight: 700;
        letter-spacing: 1.2px;
        text-transform: uppercase;
        color: #ed8d1b;
        background: rgba(237,141,27,.15);
        border: 1px solid rgba(237,141,27,.4);
        padding: 3px 10px;
        border-radius: 20px;
        align-self: flex-start;
        margin-bottom: 8px;
      }
      .hp-browse-name {
        font-size: clamp(1.2rem, 2.2vw, 1.6rem);
        font-weight: 900;
        color: #fff;
        margin: 0 0 4px;
        line-height: 1.1;
      }
      .hp-browse-sub {
        font-size: .8rem;
        color: rgba(255,255,255,.65);
        margin: 0 0 14px;
        font-weight: 500;
      }
      .hp-browse-cta {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: .8rem;
        font-weight: 800;
        color: #151616;
        background: #ed8d1b;
        padding: 7px 16px;
        border-radius: 8px;
        align-self: flex-start;
        opacity: 0;
        transform: translateY(8px);
        transition: opacity .25s, transform .25s;
      }
      .hp-browse-card:hover .hp-browse-cta { opacity: 1; transform: translateY(0); }

      @media (max-width: 900px) {
        .hp-browse-section { padding: 0 20px 48px; }
        .hp-browse-grid { grid-template-columns: repeat(3,1fr); gap: 12px; }
        .hp-browse-card { aspect-ratio: 2 / 3; }
      }
      @media (max-width: 640px) {
        .hp-browse-section { padding: 0 16px 40px; }
        .hp-browse-grid { grid-template-columns: 1fr; gap: 16px; }
        .hp-browse-card { aspect-ratio: 4 / 3; }
        .hp-browse-cta { opacity: 1; transform: none; }
      }

      /* ═══════════════════════════════════════════════════
         ABOUT ZABCO
      ═══════════════════════════════════════════════════ */
      .hp-about-section {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0;
        max-width: 100%;
        background: #151616;
        overflow: hidden;
      }
      .hp-about-text {
        padding: clamp(40px, 6vw, 80px) clamp(28px, 5vw, 72px);
        display: flex;
        flex-direction: column;
        justify-content: center;
        gap: 0;
      }
      .hp-about-eyebrow {
        font-size: .72rem;
        font-weight: 700;
        letter-spacing: 2px;
        text-transform: uppercase;
        color: #ed8d1b;
        margin-bottom: 12px;
        display: flex;
        align-items: center;
        gap: 8px;
      }
      .hp-about-eyebrow::before {
        content: '';
        display: inline-block;
        width: 28px; height: 2px;
        background: #ed8d1b;
        border-radius: 2px;
      }
      .hp-about-text h2 {
        font-size: clamp(1.6rem, 3.5vw, 2.6rem);
        font-weight: 900;
        color: #fff;
        line-height: 1.1;
        margin: 0 0 18px;
        letter-spacing: -.5px;
      }
      .hp-about-text h2 span { color: #ed8d1b; }
      .hp-about-text > p {
        color: #aaa;
        font-size: clamp(.88rem, 1.2vw, 1rem);
        line-height: 1.75;
        margin: 0 0 24px;
      }
      .hp-about-bullets {
        list-style: none;
        padding: 0; margin: 0 0 32px;
        display: flex;
        flex-direction: column;
        gap: 10px;
      }
      .hp-about-bullets li {
        display: flex;
        align-items: center;
        gap: 10px;
        color: #ccc;
        font-size: .9rem;
        font-weight: 500;
      }
      .hp-about-bullets li::before {
        content: '';
        display: inline-flex;
        width: 20px; height: 20px;
        min-width: 20px;
        border-radius: 50%;
        background: rgba(237,141,27,.15);
        border: 1.5px solid #ed8d1b;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12'%3E%3Cpath d='M2 6l3 3 5-5' stroke='%23ed8d1b' stroke-width='1.8' fill='none' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: center;
        background-size: 12px;
      }
      .hp-about-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: #ed8d1b;
        color: #151616;
        font-weight: 800;
        font-size: .9rem;
        padding: 13px 28px;
        border-radius: 10px;
        text-decoration: none;
        align-self: flex-start;
        transition: background .2s, transform .2s;
        letter-spacing: .3px;
      }
      .hp-about-btn:hover { background: #c97415; transform: translateY(-2px); }
      .hp-about-btn::after { content: '→'; }
      .hp-about-img-wrap {
        position: relative;
        overflow: hidden;
        min-height: 420px;
      }
      .hp-about-img-wrap img {
        width: 100%; height: 100%;
        object-fit: fill;
        display: block;
        transition: transform .5s ease;
      }
      .hp-about-img-wrap:hover img { transform: scale(1.04); }
      .hp-about-img-wrap::before {
        content: '';
        position: absolute;
        inset: 0;
        background: linear-gradient(135deg, rgba(237,141,27,.12) 0%, transparent 60%);
        z-index: 1;
        pointer-events: none;
      }

      @media (max-width: 860px) {
        .hp-about-section {
          grid-template-columns: 1fr;
        }
        .hp-about-img-wrap {
          min-height: unset;
          height: unset;
          max-height: unset;
          aspect-ratio: 16 / 9;
          width: 100%;
          order: -1;
        }
        .hp-about-img-wrap img {
          width: 100%;
          height: 100%;
          max-height: unset;
          object-fit: fill;
          object-position: center;
        }
        .hp-about-text { padding: 36px 24px; }
      }
      @media (max-width: 480px) {
        .hp-about-img-wrap {
          aspect-ratio: 4 / 3;
        }
        .hp-about-text { padding: 28px 18px; }
        .hp-about-bullets li { font-size: .85rem; }
      }

      /* ═══════════════════════════════════════════════════
         OUR SERVICES
      ═══════════════════════════════════════════════════ */
      .hp-services-section {
        padding: 64px 0 72px;
        background: #f9f9f9;
        overflow: hidden;
      }
      .hp-services-inner {
        max-width: 1400px;
        margin: 0 auto;
        padding: 0 40px;
      }
      .hp-section-header {
        text-align: center;
        margin-bottom: 40px;
      }
      .hp-section-eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-size: .72rem;
        font-weight: 700;
        letter-spacing: 2px;
        text-transform: uppercase;
        color: #ed8d1b;
        margin-bottom: 10px;
      }
      .hp-section-eyebrow span { opacity: .45; }
      .hp-section-header h2 {
        font-size: clamp(1.6rem, 3vw, 2.2rem);
        font-weight: 900;
        color: #151616;
        margin: 0;
        letter-spacing: -.3px;
      }
      .hp-section-header h2 span { color: #ed8d1b; }
      .hp-services-add-row {
        display: flex;
        justify-content: center;
        margin-bottom: 32px;
      }

      /* ── Grid (≤4 items) ── */
      .hp-services-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 24px;
      }

      /* ── Carousel (>3 items) ── */
      .hp-services-carousel-wrap {
        position: relative;
      }
      .hp-services-track-outer {
        overflow: hidden;
        border-radius: 4px;
      }
      .hp-services-track {
        display: flex;
        gap: 24px;
        transition: transform .42s cubic-bezier(.4,0,.2,1);
        will-change: transform;
      }
      .hp-services-track .hp-service-card {
        flex: 0 0 calc((100% - 72px) / 4);
        min-width: 0;
      }

      /* ── Shared card styles ── */
      .hp-service-card {
        background: #fff;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 4px 18px rgba(0,0,0,.07);
        transition: transform .22s, box-shadow .22s;
        position: relative;
        cursor: pointer;
        border: 2px solid transparent;
      }
      .hp-service-card:hover { border-color: rgba(237,141,27,.35); }
      /* Avail overlay on hover */
      .hp-svc-avail-overlay {
        position: absolute; inset: 0;
        background: rgba(21,22,22,.50);
        display: flex; align-items: center; justify-content: center;
        opacity: 0; transition: opacity .22s; pointer-events: none; z-index: 5;
      }
      .hp-service-card:hover .hp-svc-avail-overlay { opacity: 1; }
      .hp-svc-avail-badge {
        background: #ed8d1b; color: #151616;
        font-weight: 800; font-size: .82rem;
        padding: 9px 20px; border-radius: 30px;
        box-shadow: 0 4px 16px rgba(237,141,27,.45);
        transform: translateY(6px); transition: transform .22s;
      }
      .hp-service-card:hover .hp-svc-avail-badge { transform: translateY(0); }
      /* Inquiry toast */
      .toast-notif-inquiry {
        position: fixed; top: 100px; left: 50%; transform: translateX(-50%);
        background: #1a1f30; border: 1px solid #3a5a9a; color: #9ac4f8;
        padding: 10px 24px; border-radius: 10px; font-weight: 700; font-size: 14px;
        z-index: 9999; white-space: nowrap; box-shadow: 0 4px 20px rgba(0,0,0,.3);
      }
      /* Inquiry pill */
      .inquiry-svc-pill {
        display: inline-flex; align-items: center; gap: 7px;
        background: rgba(237,141,27,.15); border: 1px solid rgba(237,141,27,.4);
        color: #ed8d1b; font-size: .8rem; font-weight: 700;
        padding: 6px 14px; border-radius: 20px; margin-bottom: 6px;
      }
      .am-feedback-error {
        background: #3a1a1a; color: #cf6f6f; border: 1px solid #5a2d2d;
        border-radius: 8px; padding: 10px 14px; margin-bottom: 14px; font-size: 13px; font-weight: 600;
      }
      /* Mobile-only inline avail button (shown below the title) */
      .hp-svc-avail-mobile-btn {
        display: none;
      }
      @media (max-width: 640px) {
        /* Hide the image overlay — button is now below the title */
        .hp-svc-avail-overlay { display: none !important; }

        /* Tighten the title bottom padding so the button sits snugly */
        .hp-service-name {
          padding-bottom: 6px;
        }

        .hp-svc-avail-mobile-btn {
          display: flex;
          align-items: center;
          justify-content: center;
          gap: 5px;
          margin: 0 10px 10px;
          padding: 9px 10px;
          background: #ed8d1b;
          color: #151616;
          font-family: inherit;
          font-weight: 800;
          font-size: .78rem;
          letter-spacing: .2px;
          border: none;
          border-radius: 30px;
          text-align: center;
          box-shadow: 0 3px 10px rgba(237,141,27,.30);
          pointer-events: none; /* card's onclick handles the tap */
        }
        .hp-svc-avail-mobile-btn::after {
          content: '→';
          font-size: .8rem;
        }
      }
      .hp-service-card:hover {
        transform: translateY(-6px);
        box-shadow: 0 14px 36px rgba(0,0,0,.13);
      }
      .hp-service-img-wrap {
        aspect-ratio: 4 / 3;
        overflow: hidden;
        background: #111;
      }
      .hp-service-img {
        width: 100%; height: 100%;
        object-fit: cover;
        display: block;
        transition: transform .38s ease;
      }
      .hp-service-card:hover .hp-service-img { transform: scale(1.07); }
      .hp-service-name {
        padding: 16px 18px 18px;
        font-size: 1rem;
        font-weight: 800;
        color: #151616;
        margin: 0;
        text-align: center;
        letter-spacing: .1px;
        border-top: 2px solid #f0f0f0;
      }
      .hp-services-empty {
        grid-column: 1 / -1;
        text-align: center;
        color: #aaa;
        padding: 48px 0;
        font-size: .95rem;
      }

      /* ── Carousel controls ── */
      .hp-svc-arrow {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        width: 44px; height: 44px;
        border-radius: 50%;
        background: #fff;
        border: 2px solid #e8e8e8;
        box-shadow: 0 4px 14px rgba(0,0,0,.12);
        color: #151616;
        font-size: 18px;
        cursor: pointer;
        display: flex; align-items: center; justify-content: center;
        transition: background .18s, border-color .18s, color .18s, transform .18s;
        z-index: 10;
      }
      .hp-svc-arrow:hover {
        background: #ed8d1b;
        border-color: #ed8d1b;
        color: #fff;
        transform: translateY(-50%) scale(1.08);
      }
      .hp-svc-arrow:disabled {
        opacity: .3;
        cursor: default;
        pointer-events: none;
      }
      .hp-svc-prev { left: -22px; }
      .hp-svc-next { right: -22px; }

      /* ── Carousel dots ── */
      .hp-svc-dots {
        display: flex;
        justify-content: center;
        gap: 7px;
        margin-top: 28px;
      }
      .hp-svc-dot {
        width: 8px; height: 8px;
        border-radius: 50%;
        background: #ccc;
        border: none;
        cursor: pointer;
        transition: background .2s, transform .2s, width .2s;
        padding: 0;
      }
      .hp-svc-dot.active {
        background: #ed8d1b;
        width: 24px;
        border-radius: 4px;
        transform: none;
      }

      @media (max-width: 1000px) {
        .hp-services-inner { padding: 0 24px; }
        .hp-services-grid { grid-template-columns: repeat(2, 1fr); gap: 16px; }
        .hp-services-track .hp-service-card { flex: 0 0 calc((100% - 24px) / 2); }
        .hp-svc-prev { left: -14px; }
        .hp-svc-next { right: -14px; }
      }
      @media (max-width: 700px) {
        .hp-services-section { padding: 44px 0 52px; }
        .hp-services-inner { padding: 0 16px; }
        .hp-services-grid { grid-template-columns: repeat(2, 1fr); gap: 12px; }
        .hp-services-track .hp-service-card { flex: 0 0 calc(100% - 12px); }
        .hp-svc-prev { left: -10px; }
        .hp-svc-next { right: -10px; }
        .hp-svc-arrow { width: 36px; height: 36px; font-size: 15px; }
        .hp-service-name { font-size: .88rem; padding: 12px 14px 14px; }
      }
    </style>
</head>

<body style="background:#f9f9f9;">

<?php if ($isAdmin): ?>
<div class="admin-badge">⚙ Admin Mode</div>
<?php endif; ?>
<?php if (isset($_GET['inquired'])): ?>
<div class="toast-notif-inquiry" id="tn-inquiry">✓ Your inquiry has been sent! We'll be in touch soon.</div>
<?php endif; ?>

<!-- Slider -->
<section class="container">
    <div class="slider-wrapper">
        <div class="slider">
            <img id="slide-1" src="testcover.jpg" alt="Unavailable"/>
            <div class="hero-content">
                <h1 class="hero-title">BUILD YOUR BEST PLACE WITH <span class="orange-text">ZABCO</span> TILES</h1>
                <a href="Products.php" class="hero-button">BROWSE CATALOG</a>
            </div>
        </div>
    </div>
</section>

<!-- What's New -->
<section class="whats-new-section">
    <div class="whats-new-header">
        <h2>What's New?</h2>
        <?php if ($isAdmin): ?>
        <button class="admin-add-btn" onclick="openModal('modal-add-whats-new')" type="button">+ Add Post</button>
        <?php endif; ?>
    </div>

    <div class="wn-grid">
        <?php if (empty($whatsNew)): ?>
        <p class="wn-empty">No posts yet. Check back soon!</p>
        <?php else: ?>
        <?php foreach ($whatsNew as $wn): ?>
        <div class="wn-card <?= $isAdmin ? 'ae-wrap' : '' ?>">
            <?php if ($isAdmin): ?>
            <div class="ae-actions">
                <button class="ae-edit" type="button"
                    data-id="<?= $wn['id'] ?>"
                    data-title="<?= htmlspecialchars($wn['title'], ENT_QUOTES) ?>"
                    data-desc="<?= htmlspecialchars($wn['description'] ?? '', ENT_QUOTES) ?>"
                    data-images="<?= htmlspecialchars(json_encode($wn['images']), ENT_QUOTES) ?>"
                    onclick="openEditWhatsNew(this)">✎ Edit</button>
                <button class="ae-del" type="button"
                    data-id="<?= $wn['id'] ?>"
                    data-title="<?= htmlspecialchars($wn['title'], ENT_QUOTES) ?>"
                    onclick="openDeleteWhatsNew(this)">✕ Delete</button>
            </div>
            <?php endif; ?>

            <?php $imgs = $wn['images']; $multi = count($imgs) > 1; ?>
            <div class="wn-img-wrap" <?= $multi ? 'data-slider="true"' : '' ?>>
                <div class="wn-slides-track">
                    <?php foreach ($imgs as $imgSrc): ?>
                    <img src="<?= htmlspecialchars($imgSrc) ?>" alt="<?= htmlspecialchars($wn['title']) ?>" class="wn-card-img">
                    <?php endforeach; ?>
                </div>
                <?php if ($multi): ?>
                <button class="wn-slide-btn wn-slide-prev" type="button" aria-label="Previous">&#8249;</button>
                <button class="wn-slide-btn wn-slide-next" type="button" aria-label="Next">&#8250;</button>
                <div class="wn-dots">
                    <?php for ($di = 0; $di < count($imgs); $di++): ?>
                    <span class="wn-dot <?= $di === 0 ? 'active' : '' ?>"></span>
                    <?php endfor; ?>
                </div>
                <?php endif; ?>
            </div>

            <div class="wn-card-body">
                <h3 class="wn-card-title"><?= htmlspecialchars($wn['title']) ?></h3>
                <?php if (!empty($wn['description'])): ?>
                <p class="wn-card-desc"><?= nl2br(htmlspecialchars($wn['description'])) ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>

<!-- Browse Products -->
<section class="hp-browse-section">
  <div class="hp-browse-header">
    <h2>Browse <span>Products</span></h2>
    <a href="Products.php" class="hp-browse-see-more">See all &rarr;</a>
  </div>
  <div class="hp-browse-grid">

    <!-- Median -->
    <div class="hp-browse-card <?= $isAdmin ? 'ae-wrap' : '' ?>">
      <?php if ($isAdmin): ?>
      <button class="ae-btn" onclick="openModal('modal-browse-median')" type="button">&#x270E; Edit Image</button>
      <?php endif; ?>
      <a href="Products.php?table[]=productsmedian" class="hp-browse-link">
        <img src="<?= htmlspecialchars($browseImgs['median']) ?>" alt="Median Tiles" class="hp-browse-img">
        <div class="hp-browse-overlay">
          <span class="hp-browse-tier">Budget Level</span>
          <h3 class="hp-browse-name">Median</h3>
          <p class="hp-browse-sub">Affordable quality for every home</p>
          <span class="hp-browse-cta">Shop Now &rarr;</span>
        </div>
      </a>
    </div>

    <!-- Sophisticated -->
    <div class="hp-browse-card <?= $isAdmin ? 'ae-wrap' : '' ?>">
      <?php if ($isAdmin): ?>
      <button class="ae-btn" onclick="openModal('modal-browse-sophisticated')" type="button">&#x270E; Edit Image</button>
      <?php endif; ?>
      <a href="Products.php?table[]=productssophisticated" class="hp-browse-link">
        <img src="<?= htmlspecialchars($browseImgs['sophisticated']) ?>" alt="Sophisticated Tiles" class="hp-browse-img">
        <div class="hp-browse-overlay">
          <span class="hp-browse-tier">Mid-Range</span>
          <h3 class="hp-browse-name">Sophisticated</h3>
          <p class="hp-browse-sub">Refined designs, elevated style</p>
          <span class="hp-browse-cta">Shop Now &rarr;</span>
        </div>
      </a>
    </div>

    <!-- Luxurious -->
    <div class="hp-browse-card <?= $isAdmin ? 'ae-wrap' : '' ?>">
      <?php if ($isAdmin): ?>
      <button class="ae-btn" onclick="openModal('modal-browse-luxurious')" type="button">&#x270E; Edit Image</button>
      <?php endif; ?>
      <a href="Products.php?table[]=productsluxurious" class="hp-browse-link">
        <img src="<?= htmlspecialchars($browseImgs['luxurious']) ?>" alt="Luxurious Tiles" class="hp-browse-img">
        <div class="hp-browse-overlay">
          <span class="hp-browse-tier">High-End</span>
          <h3 class="hp-browse-name">Luxurious</h3>
          <p class="hp-browse-sub">Premium tiles for discerning taste</p>
          <span class="hp-browse-cta">Shop Now &rarr;</span>
        </div>
      </a>
    </div>

  </div>
</section>

<!-- Our Services -->
<section class="hp-services-section">
  <div class="hp-services-inner">
    <div class="hp-section-header">
      <div class="hp-section-eyebrow"><span>—</span> Our Services <span>—</span></div>
      <h2>What We <span>Offer</span></h2>
    </div>

    <?php if ($isAdmin): ?>
    <div class="hp-services-add-row">
      <button class="admin-add-btn" onclick="openModal('modal-add-service')" type="button">+ Add Service</button>
    </div>
    <?php endif; ?>

    <?php if (empty($services)): ?>
    <div class="hp-services-grid">
      <p class="hp-services-empty">No services listed yet.</p>
    </div>

    <?php elseif (count($services) <= 4): ?>
    <!-- Static grid for 1–4 services -->
    <div class="hp-services-grid">
      <?php foreach ($services as $svc): ?>
      <div class="hp-service-card <?= $isAdmin ? 'ae-wrap' : '' ?>"
        <?= !$isAdmin ? 'onclick="openInquiry(\'' . addslashes(htmlspecialchars($svc['ServiceName'], ENT_QUOTES)) . '\')" role="button" tabindex="0"' : '' ?>>
        <?php if ($isAdmin): ?>
        <div class="ae-actions">
          <button class="ae-edit" type="button"
            onclick="event.stopPropagation(); openEditService(<?= $svc['ServiceID'] ?>, '<?= addslashes(htmlspecialchars($svc['ServiceName'])) ?>', '<?= addslashes(htmlspecialchars($svc['ImagePath'])) ?>')">
            &#x270E; Edit
          </button>
          <button class="ae-del" type="button"
            onclick="event.stopPropagation(); openDeleteService(<?= $svc['ServiceID'] ?>, '<?= addslashes(htmlspecialchars($svc['ServiceName'])) ?>')">
            &#x2715; Delete
          </button>
        </div>
        <?php endif; ?>
        <div class="hp-service-img-wrap">
          <img src="<?= htmlspecialchars($svc['ImagePath']) ?>"
               alt="<?= htmlspecialchars($svc['ServiceName']) ?>" class="hp-service-img">
          <?php if (!$isAdmin): ?>
          <div class="hp-svc-avail-overlay"><span class="hp-svc-avail-badge">Avail This Service →</span></div>
          <?php endif; ?>
        </div>
        <p class="hp-service-name"><?= htmlspecialchars($svc['ServiceName']) ?></p>
        <?php if (!$isAdmin): ?>
        <span class="hp-svc-avail-mobile-btn">Avail This Service</span>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>

    <?php else: ?>
    <!-- Carousel for 5+ services -->
    <div class="hp-services-carousel-wrap" id="svcCarousel">
      <button class="hp-svc-arrow hp-svc-prev" id="svcPrev" aria-label="Previous">&#8249;</button>
      <div class="hp-services-track-outer">
        <div class="hp-services-track" id="svcTrack">
          <?php foreach ($services as $svc): ?>
          <div class="hp-service-card <?= $isAdmin ? 'ae-wrap' : '' ?>"
            <?= !$isAdmin ? 'onclick="openInquiry(\'' . addslashes(htmlspecialchars($svc['ServiceName'], ENT_QUOTES)) . '\')" role="button" tabindex="0"' : '' ?>>
            <?php if ($isAdmin): ?>
            <div class="ae-actions">
              <button class="ae-edit" type="button"
                onclick="event.stopPropagation(); openEditService(<?= $svc['ServiceID'] ?>, '<?= addslashes(htmlspecialchars($svc['ServiceName'])) ?>', '<?= addslashes(htmlspecialchars($svc['ImagePath'])) ?>')">
                &#x270E; Edit
              </button>
              <button class="ae-del" type="button"
                onclick="event.stopPropagation(); openDeleteService(<?= $svc['ServiceID'] ?>, '<?= addslashes(htmlspecialchars($svc['ServiceName'])) ?>')">
                &#x2715; Delete
              </button>
            </div>
            <?php endif; ?>
            <div class="hp-service-img-wrap">
              <img src="<?= htmlspecialchars($svc['ImagePath']) ?>"
                   alt="<?= htmlspecialchars($svc['ServiceName']) ?>" class="hp-service-img">
              <?php if (!$isAdmin): ?>
              <div class="hp-svc-avail-overlay"><span class="hp-svc-avail-badge">Avail This Service →</span></div>
              <?php endif; ?>
            </div>
            <p class="hp-service-name"><?= htmlspecialchars($svc['ServiceName']) ?></p>
            <?php if (!$isAdmin): ?>
            <span class="hp-svc-avail-mobile-btn">Avail This Service</span>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <button class="hp-svc-arrow hp-svc-next" id="svcNext" aria-label="Next">&#8250;</button>
    </div>
    <div class="hp-svc-dots" id="svcDots"></div>
    <?php endif; ?>

  </div>
</section>

<!-- About Zabco -->
<section class="hp-about-section">
  <div class="hp-about-text">
    <span class="hp-about-eyebrow">Who We Are</span>
    <h2>About <span>Zabco</span><br>Tile Depot</h2>
    <p><strong>ZABCO TILE DEPOT</strong> is your affordable and trusted tile supplier in the Metro! Building your best place is our priority. Explore our wide catalog of floor and wall tiles crafted to suit every style and budget.</p>
    <ul class="hp-about-bullets">
      <li>Affordable prices for every budget</li>
      <li>Trusted tile supplier in Metro Manila</li>
      <li>Wide variety of floor &amp; wall tiles</li>
      <li>Quality products built to last</li>
    </ul>
    <a href="AboutUs.php" class="hp-about-btn">Learn More</a>
  </div>
  <div class="hp-about-img-wrap <?= $isAdmin ? 'ae-wrap' : '' ?>">
    <?php if ($isAdmin): ?>
    <button class="ae-btn" onclick="openModal('modal-about-img')" type="button">&#x270E; Edit Image</button>
    <?php endif; ?>
    <img fetchpriority="high" decoding="async"
         src="<?= htmlspecialchars($aboutImg) ?>" alt="About Zabco Tile Depot">
  </div>
</section>

<!-- Customer Reviews -->
<section class="reviews-section">
    <div class="reviews-section-header">
        <h2>Customer <span>Reviews</span></h2>
        <p>What our clients say about us</p>
    </div>

    <?php if (empty($reviews)): ?>
    <div class="reviews-grid">
        <p class="rv-empty">No reviews yet.</p>
    </div>

    <?php elseif (count($reviews) <= 3): ?>
    <!-- Static 3-col grid for 1–3 reviews -->
    <div class="reviews-grid">
        <?php foreach ($reviews as $rv): ?>
        <div class="rv-card <?= $isAdmin ? 'ae-wrap' : '' ?> <?= ($isAdmin && $rv['is_hidden']) ? 'rv-hidden' : '' ?>">
            <?php if ($isAdmin): ?>
            <div class="ae-actions">
                <button class="ae-edit" type="button"
                    data-id="<?= $rv['id'] ?>"
                    data-text="<?= htmlspecialchars($rv['review_text'], ENT_QUOTES) ?>"
                    data-label="<?= htmlspecialchars($rv['reviewer_label'], ENT_QUOTES) ?>"
                    data-rating="<?= (int)$rv['rating'] ?>"
                    onclick="openEditReview(this)">✎ Edit</button>
                <button class="ae-del" type="button"
                    data-id="<?= $rv['id'] ?>"
                    data-label="<?= htmlspecialchars($rv['reviewer_label'], ENT_QUOTES) ?>"
                    onclick="openDeleteReview(this)">✕ Delete</button>
                <form method="POST" action="index.php" style="display:inline;">
                    <input type="hidden" name="admin_action" value="toggle_review">
                    <input type="hidden" name="rv_id" value="<?= $rv['id'] ?>">
                    <button class="ae-toggle <?= $rv['is_hidden'] ? 'is-hidden' : '' ?>" type="submit">
                        <?= $rv['is_hidden'] ? '👁 Show' : '🚫 Hide' ?>
                    </button>
                </form>
            </div>
            <?php endif; ?>
            <span class="rv-quote-icon">&ldquo;</span>
            <div class="rv-stars">
                <?php for ($s = 1; $s <= 5; $s++): ?>
                <span class="rv-star <?= $s > $rv['rating'] ? 'empty' : '' ?>">★</span>
                <?php endfor; ?>
            </div>
            <p class="rv-text"><?= nl2br(htmlspecialchars($rv['review_text'])) ?></p>
            <div class="rv-label"><?= htmlspecialchars($rv['reviewer_label']) ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php else: ?>
    <!-- Carousel for 4+ reviews -->
    <div class="rv-carousel-wrap" id="rvCarousel">
        <button class="rv-arrow rv-prev" id="rvPrev" aria-label="Previous">&#8249;</button>
        <div class="rv-track-outer">
            <div class="rv-track" id="rvTrack">
                <?php foreach ($reviews as $rv): ?>
                <div class="rv-card <?= $isAdmin ? 'ae-wrap' : '' ?> <?= ($isAdmin && $rv['is_hidden']) ? 'rv-hidden' : '' ?>">
                    <?php if ($isAdmin): ?>
                    <div class="ae-actions">
                        <button class="ae-edit" type="button"
                            data-id="<?= $rv['id'] ?>"
                            data-text="<?= htmlspecialchars($rv['review_text'], ENT_QUOTES) ?>"
                            data-label="<?= htmlspecialchars($rv['reviewer_label'], ENT_QUOTES) ?>"
                            data-rating="<?= (int)$rv['rating'] ?>"
                            onclick="openEditReview(this)">✎ Edit</button>
                        <button class="ae-del" type="button"
                            data-id="<?= $rv['id'] ?>"
                            data-label="<?= htmlspecialchars($rv['reviewer_label'], ENT_QUOTES) ?>"
                            onclick="openDeleteReview(this)">✕ Delete</button>
                                <form method="POST" action="index.php" style="display:inline;">
                            <input type="hidden" name="admin_action" value="toggle_review">
                            <input type="hidden" name="rv_id" value="<?= $rv['id'] ?>">
                            <button class="ae-toggle <?= $rv['is_hidden'] ? 'is-hidden' : '' ?>" type="submit">
                                <?= $rv['is_hidden'] ? '👁 Show' : '🚫 Hide' ?>
                            </button>
                        </form>
                    </div>
                    <?php endif; ?>
                    <span class="rv-quote-icon">&ldquo;</span>
                    <div class="rv-stars">
                        <?php for ($s = 1; $s <= 5; $s++): ?>
                        <span class="rv-star <?= $s > $rv['rating'] ? 'empty' : '' ?>">★</span>
                        <?php endfor; ?>
                    </div>
                    <p class="rv-text"><?= nl2br(htmlspecialchars($rv['review_text'])) ?></p>
                    <div class="rv-label"><?= htmlspecialchars($rv['reviewer_label']) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <button class="rv-arrow rv-next" id="rvNext" aria-label="Next">&#8250;</button>
    </div>
    <div class="rv-dots" id="rvDots"></div>
    <?php endif; ?>

    <?php if ($isAdmin): ?>
    <div class="reviews-add-row">
        <button class="admin-add-btn" onclick="openModal('modal-add-review')" type="button">+ Add Review</button>
    </div>
    <?php endif; ?>
</section>

<!-- Service Inquiry Modal (all users) -->
<div class="aov" id="modal-inquiry">
    <div class="am">
        <h3 style="color:#fff;">Avail a <span style="color:#ed8d1b;">Service</span></h3>
        <?php if (!empty($inquiryError)): ?>
        <div class="am-feedback-error">✕ <?= htmlspecialchars($inquiryError) ?></div>
        <?php endif; ?>
        <form method="POST" action="index.php">
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
            <textarea name="inquiry_message" rows="4" placeholder="Tell us more about what you need..." required style="resize:vertical;"></textarea>
            <div class="am-actions">
                <button type="button" class="am-cancel" onclick="closeModal('modal-inquiry')">Cancel</button>
                <button type="submit" class="am-submit">Send Inquiry</button>
            </div>
        </form>
    </div>
</div>

<?php if ($isAdmin): ?>

<!-- Browse: Median image -->
<div class="aov" id="modal-browse-median">
    <div class="am">
        <h3>Edit Median Cover Image</h3>
        <form method="POST" enctype="multipart/form-data" action="index.php">
            <input type="hidden" name="admin_action" value="update_browse_img">
            <input type="hidden" name="slot" value="median">
            <label>Upload new image (JPG / PNG / WEBP, max 5 MB)</label>
            <input type="file" name="img_file" accept="image/*" required>
            <p class="am-note">Current: <?= htmlspecialchars($browseImgs['median']) ?></p>
            <div class="am-actions">
                <button type="button" class="am-cancel" onclick="closeModal('modal-browse-median')">Cancel</button>
                <button type="submit" class="am-submit">Save Image</button>
            </div>
        </form>
    </div>
</div>

<!-- Browse: Sophisticated image -->
<div class="aov" id="modal-browse-sophisticated">
    <div class="am">
        <h3>Edit Sophisticated Cover Image</h3>
        <form method="POST" enctype="multipart/form-data" action="index.php">
            <input type="hidden" name="admin_action" value="update_browse_img">
            <input type="hidden" name="slot" value="sophisticated">
            <label>Upload new image (JPG / PNG / WEBP, max 5 MB)</label>
            <input type="file" name="img_file" accept="image/*" required>
            <p class="am-note">Current: <?= htmlspecialchars($browseImgs['sophisticated']) ?></p>
            <div class="am-actions">
                <button type="button" class="am-cancel" onclick="closeModal('modal-browse-sophisticated')">Cancel</button>
                <button type="submit" class="am-submit">Save Image</button>
            </div>
        </form>
    </div>
</div>

<!-- Browse: Luxurious image -->
<div class="aov" id="modal-browse-luxurious">
    <div class="am">
        <h3>Edit Luxurious Cover Image</h3>
        <form method="POST" enctype="multipart/form-data" action="index.php">
            <input type="hidden" name="admin_action" value="update_browse_img">
            <input type="hidden" name="slot" value="luxurious">
            <label>Upload new image (JPG / PNG / WEBP, max 5 MB)</label>
            <input type="file" name="img_file" accept="image/*" required>
            <p class="am-note">Current: <?= htmlspecialchars($browseImgs['luxurious']) ?></p>
            <div class="am-actions">
                <button type="button" class="am-cancel" onclick="closeModal('modal-browse-luxurious')">Cancel</button>
                <button type="submit" class="am-submit">Save Image</button>
            </div>
        </form>
    </div>
</div>

<!-- About section image -->
<div class="aov" id="modal-about-img">
    <div class="am">
        <h3>Edit About Section Image</h3>
        <form method="POST" enctype="multipart/form-data" action="index.php">
            <input type="hidden" name="admin_action" value="update_about_img">
            <label>Upload new image (JPG / PNG / WEBP, max 5 MB)</label>
            <input type="file" name="img_file" accept="image/*" required>
            <p class="am-note">Current: <?= htmlspecialchars($aboutImg) ?></p>
            <div class="am-actions">
                <button type="button" class="am-cancel" onclick="closeModal('modal-about-img')">Cancel</button>
                <button type="submit" class="am-submit">Save Image</button>
            </div>
        </form>
    </div>
</div>

<!-- Add Service -->
<div class="aov" id="modal-add-service">
    <div class="am">
        <h3>Add New Service</h3>
        <form method="POST" enctype="multipart/form-data" action="index.php">
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
        <form method="POST" enctype="multipart/form-data" action="index.php" id="form-edit-service">
            <input type="hidden" name="admin_action" value="edit_service">
            <input type="hidden" name="svc_id" id="edit-svc-id">
            <label>Service Name</label>
            <input type="text" name="svc_name" id="edit-svc-name" required>
            <label>Current Image</label>
            <img id="edit-svc-img-preview" class="am-cur-img" src="" alt="Current Image">
            <label>Replace Image (optional — leave blank to keep current)</label>
            <input type="file" name="svc_img" accept="image/*">
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
        <p style="color:#ccc;margin-bottom:8px;">Are you sure you want to delete "<span id="del-svc-name" style="color:#fff;font-weight:700;"></span>"? This cannot be undone.</p>
        <form method="POST" action="index.php">
            <input type="hidden" name="admin_action" value="delete_service">
            <input type="hidden" name="svc_id" id="del-svc-id">
            <div class="am-actions">
                <button type="button" class="am-cancel" onclick="closeModal('modal-delete-service')">Cancel</button>
                <button type="submit" class="am-danger">Yes, Delete</button>
            </div>
        </form>
    </div>
</div>

<!-- Add What's New -->
<div class="aov" id="modal-add-whats-new">
    <div class="am">
        <h3>Add What's New Post</h3>
        <form method="POST" enctype="multipart/form-data" action="index.php">
            <input type="hidden" name="admin_action" value="add_whats_new">
            <label>Title</label>
            <input type="text" name="wn_title" placeholder="e.g. New Tile Designs" required>
            <label>Description (optional)</label>
            <textarea name="wn_desc" rows="3" placeholder="Short description..."></textarea>
            <label>Images — select one or more (JPG / PNG / WEBP, max 5 MB each)</label>
            <input type="file" name="wn_img[]" accept="image/*" multiple required>
            <p class="am-note">Tip: selecting multiple images will create an auto-sliding carousel on the card.</p>
            <div class="am-actions">
                <button type="button" class="am-cancel" onclick="closeModal('modal-add-whats-new')">Cancel</button>
                <button type="submit" class="am-submit">Add Post</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit What's New -->
<div class="aov" id="modal-edit-whats-new">
    <div class="am">
        <h3>Edit What's New Post</h3>
        <form method="POST" enctype="multipart/form-data" action="index.php" id="form-edit-whats-new">
            <input type="hidden" name="admin_action" value="edit_whats_new">
            <input type="hidden" name="wn_id" id="edit-wn-id">
            <input type="hidden" name="wn_keep_imgs" id="edit-wn-keep">
            <label>Title</label>
            <input type="text" name="wn_title" id="edit-wn-title" required>
            <label>Description</label>
            <textarea name="wn_desc" id="edit-wn-desc" rows="3"></textarea>
            <label>Current Images <span style="color:#666;font-weight:400;text-transform:none;font-size:10px;">(click × to remove)</span></label>
            <div id="edit-wn-current-imgs" style="display:flex;flex-wrap:wrap;gap:8px;margin-top:6px;"></div>
            <label style="margin-top:14px;">Add More Images (optional)</label>
            <input type="file" name="wn_img[]" accept="image/*" multiple>
            <p class="am-note">Tip: multiple images create an auto-sliding carousel on the card.</p>
            <div class="am-actions">
                <button type="button" class="am-cancel" onclick="closeModal('modal-edit-whats-new')">Cancel</button>
                <button type="submit" class="am-submit">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete What's New Confirm -->
<div class="aov" id="modal-delete-whats-new">
    <div class="am">
        <h3>Delete Post</h3>
        <p style="color:#ccc;margin-bottom:8px;">Are you sure you want to delete "<span id="del-wn-name" style="color:#fff;font-weight:700;"></span>"? This cannot be undone.</p>
        <form method="POST" action="index.php">
            <input type="hidden" name="admin_action" value="delete_whats_new">
            <input type="hidden" name="wn_id" id="del-wn-id">
            <div class="am-actions">
                <button type="button" class="am-cancel" onclick="closeModal('modal-delete-whats-new')">Cancel</button>
                <button type="submit" class="am-danger">Yes, Delete</button>
            </div>
        </form>
    </div>
</div>

<!-- Add Review -->
<div class="aov" id="modal-add-review">
    <div class="am">
        <h3>Add Customer Review</h3>
        <form method="POST" action="index.php">
            <input type="hidden" name="admin_action" value="add_review">
            <label>Review Text</label>
            <textarea name="rv_text" rows="4" placeholder="Write the customer's review..." required></textarea>
            <label>Label <span style="font-weight:400;text-transform:none;font-size:10px;">(anonymous, e.g. "Verified Customer" or "Homeowner")</span></label>
            <input type="text" name="rv_label" value="Verified Customer">
            <label>Star Rating</label>
            <select name="rv_rating">
                <option value="5" selected>★★★★★ (5)</option>
                <option value="4">★★★★☆ (4)</option>
                <option value="3">★★★☆☆ (3)</option>
                <option value="2">★★☆☆☆ (2)</option>
                <option value="1">★☆☆☆☆ (1)</option>
            </select>
            <div class="am-actions">
                <button type="button" class="am-cancel" onclick="closeModal('modal-add-review')">Cancel</button>
                <button type="submit" class="am-submit">Add Review</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Review -->
<div class="aov" id="modal-edit-review">
    <div class="am">
        <h3>Edit Review</h3>
        <form method="POST" action="index.php">
            <input type="hidden" name="admin_action" value="edit_review">
            <input type="hidden" name="rv_id" id="edit-rv-id">
            <label>Review Text</label>
            <textarea name="rv_text" id="edit-rv-text" rows="4" required></textarea>
            <label>Label</label>
            <input type="text" name="rv_label" id="edit-rv-label">
            <label>Star Rating</label>
            <select name="rv_rating" id="edit-rv-rating">
                <option value="5">★★★★★ (5)</option>
                <option value="4">★★★★☆ (4)</option>
                <option value="3">★★★☆☆ (3)</option>
                <option value="2">★★☆☆☆ (2)</option>
                <option value="1">★☆☆☆☆ (1)</option>
            </select>
            <div class="am-actions">
                <button type="button" class="am-cancel" onclick="closeModal('modal-edit-review')">Cancel</button>
                <button type="submit" class="am-submit">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Review Confirm -->
<div class="aov" id="modal-delete-review">
    <div class="am">
        <h3>Delete Review</h3>
        <p style="color:#ccc;margin-bottom:8px;">Delete the review from "<span id="del-rv-label" style="color:#fff;font-weight:700;"></span>"? This cannot be undone.</p>
        <form method="POST" action="index.php">
            <input type="hidden" name="admin_action" value="delete_review">
            <input type="hidden" name="rv_id" id="del-rv-id">
            <div class="am-actions">
                <button type="button" class="am-cancel" onclick="closeModal('modal-delete-review')">Cancel</button>
                <button type="submit" class="am-danger">Yes, Delete</button>
            </div>
        </form>
    </div>
</div>

<?php endif; ?>

<script src="assets/bootstrap/js/bootstrap.min.js"></script>

<script>
/* ── Admin modal helpers ── */
/* Auto-remove inquiry toast */
const tni = document.getElementById('tn-inquiry');
if (tni) setTimeout(() => tni.remove(), 4000);

/* ── Service inquiry modal ── */
function openInquiry(serviceName) {
    document.getElementById('inquiry-service-name').value = serviceName;
    document.getElementById('inquiry-svc-label').textContent = '🔧 ' + serviceName;
    openModal('modal-inquiry');
}
document.querySelectorAll('.hp-service-card[role="button"]').forEach(card => {
    card.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') card.click(); });
});

<?php if (!empty($inquiryError)): ?>
openModal('modal-inquiry');
<?php endif; ?>

function openModal(id) {
    const el = document.getElementById(id);
    if (el) { el.classList.add('open'); document.body.style.overflow = 'hidden'; }
}
function closeModal(id) {
    const el = document.getElementById(id);
    if (el) { el.classList.remove('open'); document.body.style.overflow = ''; }
}
document.querySelectorAll('.aov').forEach(ov => {
    ov.addEventListener('click', function(e) {
        if (e.target === this) closeModal(this.id);
    });
});

function openEditService(id, name, imgPath) {
    document.getElementById('edit-svc-id').value        = id;
    document.getElementById('edit-svc-name').value      = name;
    document.getElementById('edit-svc-img-preview').src = imgPath;
    openModal('modal-edit-service');
}
function openDeleteService(id, name) {
    document.getElementById('del-svc-id').value         = id;
    document.getElementById('del-svc-name').textContent = name;
    openModal('modal-delete-service');
}

function openEditWhatsNew(btn) {
    const images = JSON.parse(btn.dataset.images || '[]');
    document.getElementById('edit-wn-id').value    = btn.dataset.id;
    document.getElementById('edit-wn-title').value = btn.dataset.title;
    document.getElementById('edit-wn-desc').value  = btn.dataset.desc;

    let kept = Array.isArray(images) ? [...images] : [images];
    const container = document.getElementById('edit-wn-current-imgs');
    const keepInput = document.getElementById('edit-wn-keep');

    function renderThumbs() {
        container.innerHTML = '';
        keepInput.value = JSON.stringify(kept);
        kept.forEach((src, i) => {
            const wrap = document.createElement('div');
            wrap.style.cssText = 'position:relative;display:inline-block;';
            const img = document.createElement('img');
            img.src = src;
            img.style.cssText = 'width:64px;height:64px;object-fit:cover;border-radius:6px;border:1px solid #3a3a3a;display:block;';
            const b = document.createElement('button');
            b.type = 'button'; b.textContent = '×';
            b.style.cssText = 'position:absolute;top:-6px;right:-6px;background:#8b1a1a;color:#fff;border:none;border-radius:50%;width:18px;height:18px;font-size:13px;line-height:1;cursor:pointer;display:flex;align-items:center;justify-content:center;padding:0;';
            b.onclick = () => { kept.splice(i, 1); renderThumbs(); };
            wrap.appendChild(img); wrap.appendChild(b);
            container.appendChild(wrap);
        });
        if (kept.length === 0)
            container.innerHTML = '<span style="color:#666;font-size:12px;">All removed — upload at least one new image below.</span>';
    }
    renderThumbs();
    openModal('modal-edit-whats-new');
}
function openDeleteWhatsNew(btn) {
    document.getElementById('del-wn-id').value         = btn.dataset.id;
    document.getElementById('del-wn-name').textContent = btn.dataset.title;
    openModal('modal-delete-whats-new');
}

function openEditReview(btn) {
    document.getElementById('edit-rv-id').value     = btn.dataset.id;
    document.getElementById('edit-rv-text').value   = btn.dataset.text;
    document.getElementById('edit-rv-label').value  = btn.dataset.label;
    document.getElementById('edit-rv-rating').value = btn.dataset.rating;
    openModal('modal-edit-review');
}
function openDeleteReview(btn) {
    document.getElementById('del-rv-id').value          = btn.dataset.id;
    document.getElementById('del-rv-label').textContent = btn.dataset.label;
    openModal('modal-delete-review');
}

/* ── What's New card sliders ── */
document.querySelectorAll('.wn-img-wrap[data-slider="true"]').forEach(wrap => {
    const track = wrap.querySelector('.wn-slides-track');
    const dots  = wrap.querySelectorAll('.wn-dot');
    const total = dots.length;
    let idx = 0;
    function goTo(n) {
        idx = (n + total) % total;
        track.style.transform = `translateX(-${idx * 100}%)`;
        dots.forEach((d, i) => d.classList.toggle('active', i === idx));
    }
    wrap.querySelector('.wn-slide-prev').addEventListener('click', e => { e.stopPropagation(); goTo(idx - 1); });
    wrap.querySelector('.wn-slide-next').addEventListener('click', e => { e.stopPropagation(); goTo(idx + 1); });
    dots.forEach((d, i) => d.addEventListener('click', () => goTo(i)));
    let timer = setInterval(() => goTo(idx + 1), 3500);
    wrap.addEventListener('mouseenter', () => clearInterval(timer));
    wrap.addEventListener('mouseleave', () => { timer = setInterval(() => goTo(idx + 1), 3500); });
});


/* ── Reviews carousel (>3 reviews) ── */
(function() {
    const track   = document.getElementById('rvTrack');
    const prevBtn = document.getElementById('rvPrev');
    const nextBtn = document.getElementById('rvNext');
    const dotsEl  = document.getElementById('rvDots');
    if (!track || !prevBtn || !nextBtn) return;

    const cards = track.querySelectorAll('.rv-card');
    let idx = 0;

    function visibleCount() {
        const w = track.parentElement.offsetWidth;
        if (w < 500) return 1;
        if (w < 820) return 2;
        return 3;
    }
    function cardWidth()  { return cards[0] ? cards[0].offsetWidth : 0; }
    function gapPx()      { return 20; }
    function maxIdx()     { return Math.max(0, cards.length - visibleCount()); }

    function buildDots() {
        if (!dotsEl) return;
        dotsEl.innerHTML = '';
        const total = maxIdx() + 1;
        for (let i = 0; i < total; i++) {
            const d = document.createElement('button');
            d.className = 'rv-dot' + (i === idx ? ' active' : '');
            d.setAttribute('aria-label', 'Go to slide ' + (i + 1));
            d.addEventListener('click', () => goTo(i));
            dotsEl.appendChild(d);
        }
    }
    function updateDots() {
        if (!dotsEl) return;
        dotsEl.querySelectorAll('.rv-dot').forEach((d, i) => d.classList.toggle('active', i === idx));
    }
    function goTo(n) {
        idx = Math.max(0, Math.min(n, maxIdx()));
        track.style.transform = 'translateX(-' + (idx * (cardWidth() + gapPx())) + 'px)';
        prevBtn.disabled = idx === 0;
        nextBtn.disabled = idx >= maxIdx();
        updateDots();
    }

    prevBtn.addEventListener('click', () => goTo(idx - 1));
    nextBtn.addEventListener('click', () => goTo(idx + 1));

    // Touch / swipe
    let tx = 0;
    track.addEventListener('touchstart', e => { tx = e.touches[0].clientX; }, { passive: true });
    track.addEventListener('touchend',   e => {
        const dx = tx - e.changedTouches[0].clientX;
        if (Math.abs(dx) > 40) goTo(dx > 0 ? idx + 1 : idx - 1);
    });

    // Auto-advance every 5 s, pause on hover
    let autoTimer = setInterval(() => goTo(idx < maxIdx() ? idx + 1 : 0), 5000);
    track.parentElement.parentElement.addEventListener('mouseenter', () => clearInterval(autoTimer));
    track.parentElement.parentElement.addEventListener('mouseleave', () => {
        autoTimer = setInterval(() => goTo(idx < maxIdx() ? idx + 1 : 0), 5000);
    });

    let resizeTimer;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(() => { buildDots(); goTo(Math.min(idx, maxIdx())); }, 120);
    });

    buildDots();
    goTo(0);
})();

/* ── Services carousel ── */
(function() {
    const track   = document.getElementById('svcTrack');
    const prevBtn = document.getElementById('svcPrev');
    const nextBtn = document.getElementById('svcNext');
    const dotsEl  = document.getElementById('svcDots');
    if (!track || !prevBtn || !nextBtn) return;

    const cards = track.querySelectorAll('.hp-service-card');
    let idx = 0;

    function visibleCount() {
        const w = track.parentElement.offsetWidth;
        if (w < 500)  return 1;
        if (w < 900)  return 2;
        if (w < 1200) return 3;
        return 4;
    }

    function cardWidth() {
        return cards[0] ? cards[0].offsetWidth : 0;
    }

    function gap() {
        return 24; // matches CSS gap
    }

    function maxIdx() {
        return Math.max(0, cards.length - visibleCount());
    }

    function buildDots() {
        if (!dotsEl) return;
        dotsEl.innerHTML = '';
        const total = maxIdx() + 1;
        for (let i = 0; i < total; i++) {
            const d = document.createElement('button');
            d.className = 'hp-svc-dot' + (i === idx ? ' active' : '');
            d.setAttribute('aria-label', 'Go to slide ' + (i + 1));
            d.addEventListener('click', () => goTo(i));
            dotsEl.appendChild(d);
        }
    }

    function updateDots() {
        if (!dotsEl) return;
        dotsEl.querySelectorAll('.hp-svc-dot').forEach((d, i) => {
            d.classList.toggle('active', i === idx);
        });
    }

    function goTo(n) {
        idx = Math.max(0, Math.min(n, maxIdx()));
        const offset = idx * (cardWidth() + gap());
        track.style.transform = 'translateX(-' + offset + 'px)';
        prevBtn.disabled = idx === 0;
        nextBtn.disabled = idx >= maxIdx();
        updateDots();
    }

    prevBtn.addEventListener('click', () => goTo(idx - 1));
    nextBtn.addEventListener('click', () => goTo(idx + 1));

    // Touch / swipe support
    let touchStartX = 0;
    track.addEventListener('touchstart', e => { touchStartX = e.touches[0].clientX; }, { passive: true });
    track.addEventListener('touchend',   e => {
        const dx = touchStartX - e.changedTouches[0].clientX;
        if (Math.abs(dx) > 40) goTo(dx > 0 ? idx + 1 : idx - 1);
    });

    // Recalculate on resize
    let resizeTimer;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(() => { buildDots(); goTo(Math.min(idx, maxIdx())); }, 120);
    });

    buildDots();
    goTo(0);
})();

/* ── Mobile hamburger ── */
window.addEventListener('resize', () => {
    if (window.innerWidth > 768) {
        if (typeof headerEl !== 'undefined' && headerEl) headerEl.classList.remove('nav-open');
    }
});
</script>

</body>
<?php require 'footer.php'; ?>
</html>