<?php
ob_start();
session_start();
include 'db_connect.php';

$isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

// ── Auto-create accomplished_projects table ────────────────────────────────
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS accomplished_projects (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        title       VARCHAR(255) NOT NULL,
        description TEXT,
        image1      VARCHAR(500) DEFAULT '',
        image2      VARCHAR(500) DEFAULT '',
        image3      VARCHAR(500) DEFAULT '',
        sort_order  INT DEFAULT 0,
        created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
} catch (PDOException $e) {}

// ── Image upload helper ────────────────────────────────────────────────────
function uploadProjectImg($file) {
    $dir = 'uploads/projects/';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','webp','gif'])) return '';
    $name = 'proj_' . uniqid() . '.' . $ext;
    return move_uploaded_file($file['tmp_name'], $dir . $name) ? $dir . $name : '';
}

// ── Admin POST actions (MUST run before header.php outputs HTML) ───────────
if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['admin_action'] ?? '';

    // Save About / Mission / Vision text
    if ($action === 'save_about_text') {
        $sql  = "INSERT INTO site_settings(setting_key,setting_value) VALUES(?,?)
                 ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)";
        $stmt = $pdo->prepare($sql);
        foreach (['p1','p2','mission','vision'] as $k) {
            $v = trim($_POST[$k] ?? '');
            if ($v !== '') $stmt->execute(["aboutus_$k", $v]);
        }
        header('Location: AboutUs.php?saved=1'); exit;
    }

    // Add project
    if ($action === 'add_project') {
        $title = trim($_POST['proj_title'] ?? '');
        $desc  = trim($_POST['proj_desc']  ?? '');
        $imgs  = ['','',''];
        for ($i = 1; $i <= 3; $i++) {
            if (!empty($_FILES["proj_img$i"]['name']))
                $imgs[$i-1] = uploadProjectImg($_FILES["proj_img$i"]);
        }
        if ($title) {
            $pdo->prepare("INSERT INTO accomplished_projects(title,description,image1,image2,image3) VALUES(?,?,?,?,?)")
                ->execute([$title, $desc, $imgs[0], $imgs[1], $imgs[2]]);
        }
        header('Location: AboutUs.php?saved=1'); exit;
    }

    // Edit project
    if ($action === 'edit_project') {
        $id    = (int)($_POST['proj_id']    ?? 0);
        $title = trim($_POST['proj_title']  ?? '');
        $desc  = trim($_POST['proj_desc']   ?? '');
        if ($id && $title) {
            $cur = $pdo->prepare("SELECT * FROM accomplished_projects WHERE id=?");
            $cur->execute([$id]);
            $p    = $cur->fetch(PDO::FETCH_ASSOC);
            $imgs = [$p['image1'] ?? '', $p['image2'] ?? '', $p['image3'] ?? ''];
            for ($i = 1; $i <= 3; $i++) {
                if (!empty($_FILES["proj_img$i"]['name'])) {
                    $u = uploadProjectImg($_FILES["proj_img$i"]);
                    if ($u) $imgs[$i-1] = $u;
                }
            }
            $pdo->prepare("UPDATE accomplished_projects SET title=?,description=?,image1=?,image2=?,image3=? WHERE id=?")
                ->execute([$title, $desc, $imgs[0], $imgs[1], $imgs[2], $id]);
        }
        header('Location: AboutUs.php?saved=1'); exit;
    }

    // Delete project
    if ($action === 'delete_project') {
        $id = (int)($_POST['proj_id'] ?? 0);
        if ($id) $pdo->prepare("DELETE FROM accomplished_projects WHERE id=?")->execute([$id]);
        header('Location: AboutUs.php?saved=1'); exit;
    }

    // Upload About Us hero image
    if ($action === 'save_hero_image') {
        if (!empty($_FILES['hero_img']['name'])) {
            $dir = 'uploads/about/';
            if (!is_dir($dir)) @mkdir($dir, 0755, true);
            $ext = strtolower(pathinfo($_FILES['hero_img']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','webp','gif'])) {
                $filename = 'hero_' . uniqid() . '.' . $ext;
                if (move_uploaded_file($_FILES['hero_img']['tmp_name'], $dir . $filename)) {
                    $sql  = "INSERT INTO site_settings(setting_key,setting_value) VALUES(?,?)
                             ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)";
                    $pdo->prepare($sql)->execute(['aboutus_hero_img', $dir . $filename]);
                }
            }
        }
        header('Location: AboutUs.php?saved=1'); exit;
    }
}

// ── require header AFTER all redirects ────────────────────────────────────
require 'header.php';

// ── Load site-settings text ────────────────────────────────────────────────
$defaults = [
    'aboutus_p1'       => 'ZabCo Tile Depot and Construction Supply Inc., established in 2021, is a fast-growing supplier of high-quality construction and home improvement materials in the Philippines. Located at Ricvic Building, 354 Tirona Hiway, Bacoor City, Cavite.',
    'aboutus_p2'       => 'ZabCo has quickly become a trusted retailer for residential, commercial, and government projects across Luzon. We pride ourselves on offering premium products while continuously improving our services and operations to help customers build their best spaces.',
    'aboutus_mission'  => "To redefine the way people perceive construction materials. We're committed to offering a curated range of tiles and supplies that not only elevate the aesthetics of spaces but also simplify the construction process for individuals and professionals alike.",
    'aboutus_vision'   => 'To distrupt the traditional norms of the tile and construction supply market. We aim to transform the industry by providing innovative solutions, outstanding services, and top-quality products to meet the evolving needs of our customers.',
    'aboutus_hero_img' => 'zab5.jpg',
];
$texts = $defaults;
try {
    $rows = $pdo->query("SELECT setting_key,setting_value FROM site_settings WHERE setting_key LIKE 'aboutus_%'")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) if (array_key_exists($r['setting_key'], $texts)) $texts[$r['setting_key']] = $r['setting_value'];
} catch (PDOException $e) {}

// ── Load accomplished projects ─────────────────────────────────────────────
$projects = [];
try {
    $projects = $pdo->query("SELECT * FROM accomplished_projects ORDER BY sort_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light" data-bss-forced-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>About Us – Zabco Tile Depot</title>
    <link rel="icon" type="image/ico" href="Favicon.ico">
    <link rel="stylesheet" href="assets/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="AboutUs.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,wght@0,400;0,600;0,700;0,900;1,400&display=swap" rel="stylesheet">
    <style>
        /* ══════════════════════════════════════════════════════
           BASE
        ══════════════════════════════════════════════════════ */
        *, *::before, *::after { box-sizing: border-box; }
        body { margin: 0; background: #f0f0f0; font-family: 'Inter', sans-serif; }

        /* ══════════════════════════════════════════════════════
           ABOUT HERO
        ══════════════════════════════════════════════════════ */
        .about-hero {
            display: flex;
            align-items: stretch;
            background: #161616;
            color: #fff;
            min-height: 460px;
        }

        /* — Image pane — */
        .about-hero__img {
            flex: 0 0 44%;
            position: relative;
            overflow: hidden;
        }
        .about-hero__img img {
            width: 100%; height: 100%;
            object-fit: cover; display: block;
            transition: transform .5s ease;
        }
        /* Subtle gradient vignette over the photo */
        .about-hero__img::after {
            content: '';
            position: absolute; inset: 0;
            background: linear-gradient(to right, transparent 60%, #161616 100%);
            pointer-events: none;
        }

        /* Admin click-to-change overlay */
        .hero-img-overlay {
            position: absolute; inset: 0; z-index: 2;
            background: rgba(0,0,0,.52);
            display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            gap: 10px;
            opacity: 0;
            transition: opacity .25s;
            cursor: pointer;
        }
        .about-hero__img:hover .hero-img-overlay { opacity: 1; }
        .hero-img-overlay svg { width: 40px; height: 40px; fill: #fff; }
        .hero-img-overlay span {
            color: #fff; font-size: 12px; font-weight: 700;
            letter-spacing: .08em; text-transform: uppercase;
            background: rgba(237,141,27,.9);
            padding: 5px 16px; border-radius: 20px;
        }

        /* — Text pane — */
        .about-hero__text {
            flex: 1;
            padding: 56px 60px 56px 52px;
            display: flex; flex-direction: column; justify-content: center;
        }
        .about-hero__eyebrow {
            display: flex; align-items: center; gap: 10px;
            margin-bottom: 14px;
        }
        .about-hero__eyebrow span {
            display: block; width: 36px; height: 3px;
            background: #ed8d1b; border-radius: 2px; flex-shrink: 0;
        }
        .about-hero__eyebrow em {
            font-style: normal; font-size: 11px; font-weight: 700;
            letter-spacing: .14em; text-transform: uppercase;
            color: #ed8d1b;
        }
        .about-hero__text h2 {
            font-size: 2rem; font-weight: 800;
            color: #fff; margin: 0 0 24px;
            line-height: 1.2;
        }
        .about-hero__text p {
            font-size: 0.975rem; line-height: 1.82;
            color: #a8a8a8; margin: 0 0 14px;
        }
        .about-hero__text p:last-of-type { margin-bottom: 0; }

        /* ══════════════════════════════════════════════════════
           MISSION / VISION
        ══════════════════════════════════════════════════════ */
        .mission-vision {
            background: #ed8d1b;
            padding: 56px 72px;
            display: flex;
            gap: 0;
            position: relative;
        }
        /* subtle repeating diamond pattern */
        .mission-vision::before {
            content: '';
            position: absolute; inset: 0;
            background-image: repeating-linear-gradient(
                45deg,
                rgba(255,255,255,.04) 0px,
                rgba(255,255,255,.04) 1px,
                transparent 1px,
                transparent 18px
            );
            pointer-events: none;
        }
        .mv-col {
            flex: 1; position: relative; z-index: 1;
            padding-right: 52px;
        }
        .mv-col + .mv-col {
            padding-right: 0;
            padding-left: 52px;
            border-left: 1px solid rgba(0,0,0,.15);
        }
        .mv-icon {
            width: 38px; height: 38px;
            background: rgba(0,0,0,.12);
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            margin-bottom: 14px;
        }
        .mv-icon svg { width: 20px; height: 20px; fill: #1a1a1a; }
        .mv-col h3 {
            font-size: 1.25rem; font-weight: 800;
            color: #1a1a1a; margin: 0 0 14px;
            letter-spacing: .01em;
        }
        .mv-col p {
            font-size: 0.965rem; line-height: 1.8;
            color: #1a1a1a; margin: 0;
        }

        /* ══════════════════════════════════════════════════════
           ACCOMPLISHED PROJECTS
        ══════════════════════════════════════════════════════ */
        .projects-section {
            background: #f5f5f5;
            padding: 72px 0 88px;
        }

        /* Header */
        .proj-header {
            text-align: center;
            margin-bottom: 52px;
            position: relative;
            padding: 0 140px;
        }
        .proj-header__accent {
            display: block;
            width: 48px; height: 3px;
            background: #ed8d1b;
            margin: 0 auto 16px;
            border-radius: 2px;
        }
        .proj-header h2 {
            font-size: 2.4rem; font-weight: 900;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: #111; margin: 0;
        }
        .proj-header::before,
        .proj-header::after {
            content: '';
            position: absolute;
            top: 50%; transform: translateY(-50%);
            width: 110px; height: 90px;
            background-image: radial-gradient(circle, #bbb 1.8px, transparent 1.8px);
            background-size: 14px 14px;
            opacity: .5;
        }
        .proj-header::before { left: 20px; }
        .proj-header::after  { right: 20px; }

        /* Carousel wrapper */
        .proj-carousel-wrap {
            position: relative;
            max-width: 1180px;
            margin: 0 auto;
            padding: 0 70px;
        }
        .proj-track-outer { overflow: hidden; border-radius: 4px; }
        .proj-track {
            display: flex;
            transition: transform .5s cubic-bezier(.4,0,.2,1);
            will-change: transform;
        }
        .proj-slide {
            flex: 0 0 100%;
            max-width: 100%;
            padding: 0 8px;
            box-sizing: border-box;
            overflow: hidden;
        }

        /* Three-image row */
        .proj-images {
            display: flex;
            gap: 14px;
            margin-bottom: 28px;
        }
        .proj-images img,
        .proj-img-placeholder {
            flex: 1 1 0;
            min-width: 0;
            height: 285px;
            object-fit: cover;
            border-radius: 8px;
            display: block;
            box-shadow: 0 4px 18px rgba(0,0,0,.10);
        }
        .proj-img-placeholder {
            background: #e2e2e2;
            display: flex;
            align-items: center; justify-content: center;
            color: #bbb; font-size: 0.82rem;
        }

        /* Slide text */
        .proj-info { text-align: center; padding: 0 20px; }
        .proj-info h3 {
            color: #ed8d1b;
            font-size: 1.1rem; font-weight: 800;
            letter-spacing: .1em; text-transform: uppercase;
            margin: 0 0 12px;
        }
        .proj-info p {
            font-size: 0.955rem; line-height: 1.78;
            color: #444;
            max-width: 680px; margin: 0 auto;
        }

        /* ── Arrow buttons ── */
        .proj-arrow {
            position: absolute;
            top: calc(50% - 56px);   /* vertically centred on images */
            transform: translateY(-50%);
            width: 42px; height: 42px;
            background: #ed8d1b;
            border: none; border-radius: 50%;
            cursor: pointer; z-index: 10; padding: 0;
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 4px 14px rgba(237,141,27,.4);
            transition: background .2s, transform .15s, box-shadow .2s;
        }
        .proj-arrow:hover {
            background: #c97415;
            box-shadow: 0 6px 20px rgba(237,141,27,.55);
            transform: translateY(-50%) scale(1.08);
        }
        .proj-arrow:active { transform: translateY(-50%) scale(.96); }
        .proj-arrow--prev { left: 10px; }
        .proj-arrow--next { right: 10px; }
        .proj-arrow svg { width: 18px; height: 18px; fill: #fff; flex-shrink: 0; }

        /* ── Dot indicators ── */
        .proj-dots {
            display: flex; justify-content: center;
            gap: 7px; margin-top: 28px;
        }
        .proj-dot {
            width: 8px; height: 8px;
            border-radius: 4px;
            background: #ccc; border: none;
            cursor: pointer; padding: 0;
            transition: background .2s, width .25s;
        }
        .proj-dot.active {
            background: #ed8d1b;
            width: 24px;
        }

        /* Empty state */
        .proj-empty {
            text-align: center; color: #aaa;
            padding: 50px 20px; font-size: 0.95rem;
        }

        /* ══════════════════════════════════════════════════════
           ADMIN UI
        ══════════════════════════════════════════════════════ */
        .admin-badge {
            position: fixed; bottom: 20px; right: 20px;
            background: #ed8d1b; color: #151616;
            font-weight: 800; font-size: 11px;
            letter-spacing: .8px; padding: 6px 14px;
            border-radius: 100px; z-index: 500;
            text-transform: uppercase;
            box-shadow: 0 4px 16px rgba(237,141,27,.4);
            pointer-events: none;
        }
        .admin-toolbar {
            position: sticky; top: 0; z-index: 300;
            display: flex; align-items: center; gap: 10px;
            background: #1e1e1e; border-bottom: 2px solid #ed8d1b;
            padding: 9px 20px; flex-wrap: wrap;
        }
        .admin-toolbar > span {
            color: #888; font-size: 12px; font-weight: 600; flex: 1;
            min-width: 200px;
        }
        .at-btn {
            padding: 8px 16px; border: none; border-radius: 8px;
            font-weight: 800; font-size: 12px; cursor: pointer;
            transition: background .2s, opacity .2s; white-space: nowrap;
        }
        .at-save   { background: #ed8d1b; color: #151616; }
        .at-save:hover { background: #c97415; }
        .at-cancel { background: transparent; color: #aaa; border: 1.5px solid #3a3a3a; }
        .at-cancel:hover { border-color: #555; color: #fff; }
        .at-add    { background: #2e7d32; color: #fff; }
        .at-add:hover { background: #1b5e20; }
        .at-edit   { background: #1565c0; color: #fff; font-size: 11px;
                     padding: 5px 12px; border-radius: 6px; }
        .at-edit:hover { background: #0d47a1; }
        .at-del    { background: #b71c1c; color: #fff; font-size: 11px;
                     padding: 5px 12px; border-radius: 6px; }
        .at-del:hover { background: #7f0000; }

        /* Inline text editing */
        .editable-p {
            outline: none; border-radius: 5px;
            padding: 3px 6px; margin: -3px -6px;
            min-height: 1.4em;
            transition: background .2s, box-shadow .2s;
        }
        .edit-mode .editable-p {
            background: rgba(237,141,27,.1);
            box-shadow: 0 0 0 2px rgba(237,141,27,.45);
            cursor: text;
        }
        .edit-mode .editable-p:focus {
            background: rgba(237,141,27,.14);
            box-shadow: 0 0 0 2px #ed8d1b;
        }
        .edit-hint {
            display: none; font-size: 11px;
            color: rgba(26,26,26,.5); margin-top: 10px; font-style: italic;
        }
        .edit-mode .edit-hint { display: block; }

        /* Toast */
        .save-notif {
            position: fixed; top: 120px;
            left: 50%; transform: translateX(-50%);
            background: #162216; border: 1px solid #2d6a2d;
            color: #7dd87d; padding: 10px 24px;
            border-radius: 10px; font-weight: 700; font-size: 14px;
            z-index: 9999; white-space: nowrap;
            box-shadow: 0 4px 20px rgba(0,0,0,.3);
            animation: fadeInOut 3.2s ease forwards;
        }
        @keyframes fadeInOut {
            0%  { opacity:0; transform:translateX(-50%) translateY(-8px); }
            12% { opacity:1; transform:translateX(-50%) translateY(0); }
            80% { opacity:1; }
            100%{ opacity:0; }
        }

        .proj-admin-controls {
            display: flex; gap: 8px;
            justify-content: center;
            margin-top: 16px;
        }

        /* Modal */
        .proj-modal-backdrop {
            position: fixed; inset: 0;
            background: rgba(0,0,0,.65);
            z-index: 1100;
            display: flex; align-items: center; justify-content: center;
            padding: 16px;
        }
        .proj-modal-box {
            background: #fff; border-radius: 14px;
            padding: 32px; width: 580px; max-width: 100%;
            max-height: 92vh; overflow-y: auto;
            box-shadow: 0 24px 72px rgba(0,0,0,.35);
        }
        .proj-modal-box h3 {
            font-size: 1.2rem; font-weight: 800;
            color: #111; margin: 0 0 22px;
        }
        .mf-group { margin-bottom: 16px; }
        .mf-group label {
            display: block; font-size: 12px;
            font-weight: 700; color: #444; margin-bottom: 6px;
            text-transform: uppercase; letter-spacing: .05em;
        }
        .mf-group input[type="text"],
        .mf-group textarea {
            width: 100%; padding: 10px 13px;
            border: 1.5px solid #e0e0e0; border-radius: 8px;
            font-size: 14px; font-family: inherit;
            transition: border-color .2s;
            color: #111;
        }
        .mf-group input[type="text"]:focus,
        .mf-group textarea:focus { border-color: #ed8d1b; outline: none; }
        .mf-group textarea { resize: vertical; min-height: 90px; }
        .mf-imgs { display: flex; gap: 10px; flex-wrap: wrap; }
        .mf-img-box {
            flex: 1; min-width: 130px;
            border: 2px dashed #e0e0e0; border-radius: 9px;
            padding: 10px; text-align: center;
            transition: border-color .2s;
        }
        .mf-img-box:hover { border-color: #ed8d1b; }
        .mf-img-box span {
            display: block; font-size: 10px;
            font-weight: 700; color: #999; margin-bottom: 6px;
            text-transform: uppercase; letter-spacing: .06em;
        }
        .mf-img-box .mf-preview {
            width: 100%; height: 72px;
            object-fit: cover; border-radius: 5px;
            margin-bottom: 6px; display: none;
        }
        .mf-img-box input[type="file"] { width: 100%; font-size: 11px; cursor: pointer; }
        .mf-actions {
            display: flex; gap: 10px;
            justify-content: flex-end; margin-top: 20px;
        }

        /* ══════════════════════════════════════════════════════
           RESPONSIVE — 1024px
        ══════════════════════════════════════════════════════ */
        @media (max-width: 1024px) {
            .about-hero__text { padding: 48px 44px; }
            .mission-vision { padding: 48px 48px; }
            .mv-col { padding-right: 36px; }
            .mv-col + .mv-col { padding-left: 36px; }
            .proj-carousel-wrap { padding: 0 60px; }
            .proj-header { padding: 0 100px; }
        }

        /* ══════════════════════════════════════════════════════
           RESPONSIVE — 768px  (tablet portrait)
        ══════════════════════════════════════════════════════ */
        @media (max-width: 768px) {
            /* Hero stacks vertically */
            .about-hero { flex-direction: column; min-height: unset; }
            .about-hero__img {
                flex: none; height: 280px;
                /* remove right-edge gradient on mobile */
            }
            .about-hero__img::after {
                background: linear-gradient(to bottom, transparent 50%, #161616 100%);
            }
            .about-hero__text { padding: 36px 28px 40px; }
            .about-hero__text h2 { font-size: 1.65rem; }

            /* Mission/Vision stacks */
            .mission-vision { flex-direction: column; padding: 40px 28px; }
            .mv-col { padding-right: 0 !important; }
            .mv-col + .mv-col {
                padding-left: 0 !important;
                border-left: none;
                border-top: 1px solid rgba(0,0,0,.15);
                padding-top: 28px !important;
                margin-top: 28px;
            }

            /* Projects */
            .proj-header { padding: 0 60px; }
            .proj-header::before, .proj-header::after { display: none; }
            .proj-header h2 { font-size: 1.9rem; }
            .proj-carousel-wrap { padding: 0 52px; }
            .proj-images img, .proj-img-placeholder { height: 200px; }
            .proj-arrow { width: 36px; height: 36px; }
            .proj-arrow svg { width: 15px; height: 15px; }
        }

        /* ══════════════════════════════════════════════════════
           RESPONSIVE — 540px  (large phone)
        ══════════════════════════════════════════════════════ */
        @media (max-width: 540px) {
            body { padding-top: 70px !important; }
            .about-hero__img { height: 230px; }
            .about-hero__text { padding: 28px 20px 36px; }
            .about-hero__text h2 { font-size: 1.45rem; }
            .about-hero__text p { font-size: 0.93rem; }

            .mission-vision { padding: 36px 20px; }

            .proj-header { padding: 0 16px; margin-bottom: 28px; }
            .proj-header h2 { font-size: 1.55rem; letter-spacing: .03em; }

            .proj-carousel-wrap { padding: 0 16px; }

            /* Slide: reset padding, allow content to breathe */
            .proj-slide { padding: 0; overflow: visible; }

            /* ── Stacked images: full-width, one per row ── */
            .proj-images {
                flex-direction: column;
                gap: 10px;
                margin-bottom: 20px;
                overflow: visible;
            }
            .proj-images img,
            .proj-img-placeholder {
                flex: none !important;
                width: 100% !important;
                min-width: 0 !important;
                height: 210px;
                border-radius: 10px;
                cursor: pointer;
            }

            /* Hide desktop side arrows on mobile */
            .proj-arrow--prev,
            .proj-arrow--next { display: none; }

            /* Mobile nav: arrows centred below the slide */
            .proj-mobile-nav {
                display: flex;
                justify-content: center;
                align-items: center;
                gap: 16px;
                margin: 14px 0 6px;
            }
            .proj-arrow--prev.mobile,
            .proj-arrow--next.mobile {
                display: flex;
                position: static;
                transform: none !important;
                width: 42px; height: 42px;
            }
            .proj-arrow--prev.mobile:hover,
            .proj-arrow--next.mobile:hover { transform: scale(1.06) !important; }

            .proj-info { padding: 0 4px; }
            .proj-info h3 { font-size: 1rem; margin-bottom: 10px; }
            .proj-info p  { font-size: 0.9rem; line-height: 1.7; }

            .proj-admin-controls { margin-top: 14px; }

            .admin-toolbar > span { display: none; }
            .mf-imgs { flex-direction: column; }
        }

        /* ══════════════════════════════════════════════════════
           IMAGE LIGHTBOX
        ══════════════════════════════════════════════════════ */
        .proj-images img { cursor: pointer; }
        .lightbox-overlay {
            display: none;
            position: fixed; inset: 0;
            background: rgba(0,0,0,.93);
            z-index: 3000;
            align-items: center;
            justify-content: center;
            padding: 20px;
            animation: lbFadeIn .2s ease;
        }
        .lightbox-overlay.open { display: flex; }
        @keyframes lbFadeIn {
            from { opacity: 0; }
            to   { opacity: 1; }
        }
        .lightbox-img {
            max-width: 100%; max-height: 88vh;
            object-fit: contain;
            border-radius: 10px;
            box-shadow: 0 8px 48px rgba(0,0,0,.6);
            animation: lbZoomIn .22s ease;
        }
        @keyframes lbZoomIn {
            from { transform: scale(.92); opacity: 0; }
            to   { transform: scale(1);   opacity: 1; }
        }
        .lightbox-close {
            position: absolute; top: 16px; right: 16px;
            width: 40px; height: 40px;
            background: rgba(255,255,255,.15);
            border: 1.5px solid rgba(255,255,255,.25);
            border-radius: 50%;
            color: #fff; font-size: 20px; line-height: 1;
            cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            transition: background .2s;
        }
        .lightbox-close:hover { background: rgba(255,255,255,.28); }
        .lightbox-counter {
            position: absolute; bottom: 20px; left: 50%;
            transform: translateX(-50%);
            color: rgba(255,255,255,.7);
            font-size: 13px; font-weight: 600;
            background: rgba(0,0,0,.4);
            padding: 4px 14px; border-radius: 20px;
            letter-spacing: .05em;
        }
        .lightbox-prev, .lightbox-next {
            position: absolute; top: 50%; transform: translateY(-50%);
            width: 44px; height: 44px;
            background: rgba(255,255,255,.13);
            border: 1.5px solid rgba(255,255,255,.22);
            border-radius: 50%;
            color: #fff; font-size: 20px;
            cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            transition: background .2s;
        }
        .lightbox-prev:hover, .lightbox-next:hover { background: rgba(255,255,255,.26); }
        .lightbox-prev { left: 14px; }
        .lightbox-next { right: 14px; }

        /* ══════════════════════════════════════════════════════
           RESPONSIVE — 400px  (small phone)
        ══════════════════════════════════════════════════════ */
        @media (max-width: 400px) {
            body { padding-top: 64px !important; }
            .about-hero__img { height: 200px; }
            .about-hero__text h2 { font-size: 1.3rem; }
            .proj-header h2 { font-size: 1.35rem; }
            .proj-images img:first-child,
            .proj-img-placeholder:first-child { height: 185px; }
            .proj-images img:not(:first-child),
            .proj-img-placeholder:not(:first-child) { height: 115px; }
        }
    </style>
</head>
<body>

<?php if ($isAdmin): ?>
<div class="admin-badge">⚙ Admin Mode</div>

<?php if (isset($_GET['saved'])): ?>
<div class="save-notif" id="saveNotif">✓ Changes saved successfully!</div>
<script>setTimeout(()=>{const n=document.getElementById('saveNotif');if(n)n.remove();},3400);</script>
<?php endif; ?>

<!-- Admin Toolbar -->
<div class="admin-toolbar">
    <span>ℹ Use <strong style="color:#ed8d1b">Edit Text</strong> to update About / Mission / Vision · Use <strong style="color:#ed8d1b">Add Project</strong> to manage the Accomplished Projects section.</span>
    <button class="at-btn at-add" onclick="openAddModal()">＋ Add Project</button>
    <button class="at-btn at-cancel" id="btnEditToggle" onclick="toggleEditMode()">✎ Edit Text</button>
    <!-- Save-text form (shown in edit mode) -->
    <form method="POST" action="AboutUs.php" id="adminSaveForm" style="display:none;gap:8px;align-items:center;">
        <input type="hidden" name="admin_action" value="save_about_text">
        <input type="hidden" name="p1"      id="save-p1">
        <input type="hidden" name="p2"      id="save-p2">
        <input type="hidden" name="mission" id="save-mission">
        <input type="hidden" name="vision"  id="save-vision">
        <button type="button" class="at-btn at-cancel" onclick="toggleEditMode()">Cancel</button>
        <button type="button" class="at-btn at-save"   onclick="saveText()">💾 Save Changes</button>
    </form>
</div>
<?php endif; ?>


<!-- ════════════════════════════════════════════
     ABOUT HERO
════════════════════════════════════════════ -->
<section class="about-hero" id="infoWrapper">
    <div class="about-hero__img">
        <img src="<?= htmlspecialchars($texts['aboutus_hero_img']) ?>"
             alt="Zabco Tile Depot Showroom" id="heroImg">
        <?php if ($isAdmin): ?>
        <div class="hero-img-overlay"
             onclick="document.getElementById('heroImgInput').click()"
             title="Click to change image">
            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path d="M12 15.5A3.5 3.5 0 0 1 8.5 12 3.5 3.5 0 0 1 12 8.5a3.5 3.5 0 0 1 3.5 3.5 3.5 3.5 0 0 1-3.5 3.5m7.43-2.92c.04-.3.07-.61.07-.93s-.03-.63-.07-.93l2-1.56c.18-.14.23-.38.12-.58l-1.9-3.28c-.11-.2-.35-.27-.55-.2l-2.36.95c-.49-.38-1.02-.7-1.6-.94L14.75 3.1C14.7 2.88 14.5 2.73 14.28 2.73h-3.8c-.22 0-.42.15-.47.37L9.64 5.5c-.58.24-1.11.57-1.6.94L5.68 5.5c-.2-.07-.44 0-.55.2L3.23 8.98c-.11.2-.06.44.12.58l2 1.56c-.04.3-.07.61-.07.93s.03.63.07.93l-2 1.56c-.18.14-.23.38-.12.58l1.9 3.28c.11.2.35.27.55.2l2.36-.95c.49.38 1.02.7 1.6.94l.37 2.41c.05.22.25.37.47.37h3.8c.22 0 .42-.15.47-.37l.37-2.41c.58-.24 1.11-.57 1.6-.94l2.36.95c.2.07.44 0 .55-.2l1.9-3.28c.11-.2.06-.44-.12-.58l-2-1.56z"/>
            </svg>
            <span>Change Image</span>
        </div>
        <!-- Hidden upload form -->
        <form method="POST" action="AboutUs.php" enctype="multipart/form-data"
              id="heroImgForm" style="display:none;">
            <input type="hidden" name="admin_action" value="save_hero_image">
            <input type="file" id="heroImgInput" name="hero_img"
                   accept="image/*" onchange="submitHeroImg(this)">
        </form>
        <?php endif; ?>
    </div>
    <div class="about-hero__text">
        <div class="about-hero__eyebrow">
            <span></span>
            <em>Who We Are</em>
        </div>
        <h2>About Us</h2>
        <p <?= $isAdmin ? 'class="editable-p" id="about-p1" contenteditable="false"' : '' ?>>
            <?= htmlspecialchars($texts['aboutus_p1']) ?>
        </p>
        <p <?= $isAdmin ? 'class="editable-p" id="about-p2" contenteditable="false"' : '' ?>>
            <?= htmlspecialchars($texts['aboutus_p2']) ?>
        </p>
        <?php if ($isAdmin): ?>
        <p class="edit-hint">✎ Click on any paragraph above to edit. Click "Save Changes" when done.</p>
        <?php endif; ?>
    </div>
</section>


<!-- ════════════════════════════════════════════
     MISSION / VISION
════════════════════════════════════════════ -->
<section class="mission-vision" id="mvWrapper">
    <div class="mv-col">
        <div class="mv-icon">
            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path d="M14.4 6L14 4H5v17h2v-7h5.6l.4 2h7V6z"/>
            </svg>
        </div>
        <h3>Mission</h3>
        <p <?= $isAdmin ? 'class="editable-p" id="about-mission" contenteditable="false"' : '' ?>>
            <?= htmlspecialchars($texts['aboutus_mission']) ?>
        </p>
        <?php if ($isAdmin): ?>
        <p class="edit-hint">✎ Click to edit Mission text.</p>
        <?php endif; ?>
    </div>
    <div class="mv-col">
        <div class="mv-icon">
            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>
            </svg>
        </div>
        <h3>Vision</h3>
        <p <?= $isAdmin ? 'class="editable-p" id="about-vision" contenteditable="false"' : '' ?>>
            <?= htmlspecialchars($texts['aboutus_vision']) ?>
        </p>
        <?php if ($isAdmin): ?>
        <p class="edit-hint">✎ Click to edit Vision text.</p>
        <?php endif; ?>
    </div>
</section>


<!-- ════════════════════════════════════════════
     ACCOMPLISHED PROJECTS
════════════════════════════════════════════ -->
<section class="projects-section">
    <div class="proj-header">
        <span class="proj-header__accent"></span>
        <h2>Accomplished Project</h2>
    </div>

    <?php if (empty($projects)): ?>
        <div class="proj-empty">
            <?= $isAdmin
                ? 'No projects yet — click <strong>＋ Add Project</strong> in the toolbar to add your first one.'
                : 'No accomplished projects to display yet.' ?>
        </div>
    <?php else: ?>

    <div class="proj-carousel-wrap">
        <!-- Navigation arrows -->
        <!-- Desktop arrows (hidden on mobile via CSS) -->
        <button class="proj-arrow proj-arrow--prev" onclick="projPrev()" aria-label="Previous project">
            <svg viewBox="0 0 24 24"><path d="M15.41 16.59L10.83 12l4.58-4.59L14 6l-6 6 6 6z"/></svg>
        </button>
        <button class="proj-arrow proj-arrow--next" onclick="projNext()" aria-label="Next project">
            <svg viewBox="0 0 24 24"><path d="M8.59 16.59L13.17 12 8.59 7.41 10 6l6 6-6 6z"/></svg>
        </button>

        <div class="proj-track-outer">
            <div class="proj-track" id="projTrack">
                <?php foreach ($projects as $idx => $proj): ?>
                <div class="proj-slide">
                    <!-- Images -->
                    <div class="proj-images">
                        <?php for ($i = 1; $i <= 3; $i++):
                            $src = trim($proj["image$i"] ?? '');
                        ?>
                            <?php if ($src): ?>
                                <img src="<?= htmlspecialchars($src) ?>"
                                     alt="<?= htmlspecialchars($proj['title']) ?> – image <?= $i ?>"
                                     onclick="openLightbox(this, <?= $idx ?>)">
                            <?php else: ?>
                                <div class="proj-img-placeholder">No image</div>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </div>

                    <!-- Text -->
                    <div class="proj-info">
                        <h3><?= htmlspecialchars($proj['title']) ?></h3>
                        <p><?= nl2br(htmlspecialchars($proj['description'] ?? '')) ?></p>
                    </div>

                    <!-- Admin controls -->
                    <?php if ($isAdmin): ?>
                    <div class="proj-admin-controls">
                        <button class="at-btn at-edit"
                                onclick='openEditModal(<?= htmlspecialchars(json_encode($proj), ENT_QUOTES) ?>)'>
                            ✎ Edit
                        </button>
                        <button class="at-btn at-del"
                                onclick="confirmDelete(<?= (int)$proj['id'] ?>)">
                            🗑 Delete
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div><!-- /proj-track -->
        </div><!-- /proj-track-outer -->

        <!-- Mobile nav row (visible only on small screens via CSS) -->
        <div class="proj-mobile-nav">
            <button class="proj-arrow proj-arrow--prev mobile" onclick="projPrev()" aria-label="Previous project">
                <svg viewBox="0 0 24 24"><path d="M15.41 16.59L10.83 12l4.58-4.59L14 6l-6 6 6 6z"/></svg>
            </button>
            <button class="proj-arrow proj-arrow--next mobile" onclick="projNext()" aria-label="Next project">
                <svg viewBox="0 0 24 24"><path d="M8.59 16.59L13.17 12 8.59 7.41 10 6l6 6-6 6z"/></svg>
            </button>
        </div>

        <!-- Dots -->
        <div class="proj-dots" id="projDots">
            <?php foreach ($projects as $idx => $proj): ?>
            <button class="proj-dot <?= $idx === 0 ? 'active' : '' ?>"
                    onclick="projGoTo(<?= $idx ?>)"
                    aria-label="Project <?= $idx + 1 ?>"></button>
            <?php endforeach; ?>
        </div>
    </div><!-- /proj-carousel-wrap -->

    <?php endif; ?>
</section>


<!-- ════════════════════════════════════════════
     ADMIN MODALS (rendered only for admin)
════════════════════════════════════════════ -->
<?php if ($isAdmin): ?>

<!-- Add / Edit Project Modal -->
<div class="proj-modal-backdrop" id="projModal" style="display:none;">
    <div class="proj-modal-box" onclick="event.stopPropagation()">
        <h3 id="projModalTitle">Add Accomplished Project</h3>
        <form method="POST" action="AboutUs.php"
              enctype="multipart/form-data" id="projForm">
            <input type="hidden" name="admin_action" id="mf-action" value="add_project">
            <input type="hidden" name="proj_id"      id="mf-proj-id">

            <div class="mf-group">
                <label>Project Title <span style="color:#e44;">*</span></label>
                <input type="text" name="proj_title" id="mf-title"
                       placeholder="e.g. The Vineyard Manor – Twin Lakes" required>
            </div>
            <div class="mf-group">
                <label>Description</label>
                <textarea name="proj_desc" id="mf-desc"
                          placeholder="Briefly describe Zabco's role in this project…"></textarea>
            </div>
            <div class="mf-group">
                <label>Project Images (up to 3)</label>
                <div class="mf-imgs">
                    <?php for ($i = 1; $i <= 3; $i++): ?>
                    <div class="mf-img-box">
                        <span>Image <?= $i ?></span>
                        <img id="mf-preview-<?= $i ?>" class="mf-preview" src="" alt="">
                        <input type="file" name="proj_img<?= $i ?>"
                               accept="image/*"
                               onchange="previewImg(this, <?= $i ?>)">
                    </div>
                    <?php endfor; ?>
                </div>
            </div>

            <div class="mf-actions">
                <button type="button" class="at-btn at-cancel" onclick="closeModal()">Cancel</button>
                <button type="submit" class="at-btn at-save">💾 Save Project</button>
            </div>
        </form>
    </div>
</div>

<!-- Hidden delete form -->
<form method="POST" action="AboutUs.php" id="deleteForm" style="display:none;">
    <input type="hidden" name="admin_action" value="delete_project">
    <input type="hidden" name="proj_id" id="delete-proj-id">
</form>

<?php endif; ?>


<script src="assets/bootstrap/js/bootstrap.min.js"></script>

<!-- ════════════════════════════════════════════
     CAROUSEL JS
════════════════════════════════════════════ -->
<script>
(function () {
    const track      = document.getElementById('projTrack');
    const dotsEl     = document.querySelectorAll('.proj-dot');
    const trackOuter = track ? track.closest('.proj-track-outer') : null;
    if (!track) return;

    const total   = track.children.length;
    let   current = 0;
    let   timer;

    function getSlideWidth() {
        return track.children[0] ? track.children[0].offsetWidth : trackOuter.offsetWidth;
    }

    function goTo(n) {
        current = ((n % total) + total) % total;
        track.style.transform = 'translateX(-' + (current * getSlideWidth()) + 'px)';
        dotsEl.forEach((d, i) => d.classList.toggle('active', i === current));
    }

    // Recalculate position on resize to prevent drift
    window.addEventListener('resize', () => {
        track.style.transition = 'none';
        track.style.transform = 'translateX(-' + (current * getSlideWidth()) + 'px)';
        requestAnimationFrame(() => { track.style.transition = ''; });
    });

    window.projGoTo = goTo;
    window.projPrev = () => { clearInterval(timer); goTo(current - 1); startAuto(); };
    window.projNext = () => { clearInterval(timer); goTo(current + 1); startAuto(); };

    function startAuto() {
        clearInterval(timer);
        if (total > 1) timer = setInterval(() => goTo(current + 1), 6000);
    }

    /* ── Touch / swipe support ── */
    let touchStartX = 0, touchStartY = 0, dragging = false;

    trackOuter.addEventListener('touchstart', e => {
        touchStartX = e.touches[0].clientX;
        touchStartY = e.touches[0].clientY;
        dragging = true;
    }, { passive: true });

    trackOuter.addEventListener('touchend', e => {
        if (!dragging) return;
        dragging = false;
        const dx = e.changedTouches[0].clientX - touchStartX;
        const dy = e.changedTouches[0].clientY - touchStartY;
        // Only trigger if horizontal swipe is dominant
        if (Math.abs(dx) > Math.abs(dy) && Math.abs(dx) > 40) {
            clearInterval(timer);
            dx < 0 ? projNext() : projPrev();
        }
    }, { passive: true });

    /* ── Keyboard support ── */
    document.addEventListener('keydown', e => {
        if (e.key === 'ArrowLeft')  { clearInterval(timer); projPrev(); }
        if (e.key === 'ArrowRight') { clearInterval(timer); projNext(); }
    });

    startAuto();
})();
</script>


<!-- ════════════════════════════════════════════
     ADMIN JS
════════════════════════════════════════════ -->
<?php if ($isAdmin): ?>
<script>
/* ── Inline text editing ── */
let editMode = false;

function toggleEditMode() {
    editMode = !editMode;
    const infoWrap = document.getElementById('infoWrapper');
    const mvWrap   = document.getElementById('mvWrapper');
    const form     = document.getElementById('adminSaveForm');
    const editBtn  = document.getElementById('btnEditToggle');
    const ps       = document.querySelectorAll('.editable-p');

    if (editMode) {
        infoWrap.classList.add('edit-mode');
        mvWrap.classList.add('edit-mode');
        ps.forEach(p => p.setAttribute('contenteditable', 'true'));
        form.style.display = 'flex';
        editBtn.style.display = 'none';
    } else {
        infoWrap.classList.remove('edit-mode');
        mvWrap.classList.remove('edit-mode');
        ps.forEach(p => p.setAttribute('contenteditable', 'false'));
        form.style.display = 'none';
        editBtn.style.display = '';
    }
}

function saveText() {
    const get = id => {
        const el = document.getElementById(id);
        return el ? el.innerText.trim() : '';
    };
    document.getElementById('save-p1').value      = get('about-p1');
    document.getElementById('save-p2').value      = get('about-p2');
    document.getElementById('save-mission').value = get('about-mission');
    document.getElementById('save-vision').value  = get('about-vision');
    document.getElementById('adminSaveForm').submit();
}

/* ── Project modal ── */
function openAddModal() {
    document.getElementById('projModalTitle').textContent = 'Add Accomplished Project';
    document.getElementById('mf-action').value    = 'add_project';
    document.getElementById('mf-proj-id').value   = '';
    document.getElementById('mf-title').value     = '';
    document.getElementById('mf-desc').value      = '';
    clearPreviews();
    document.getElementById('projModal').style.display = 'flex';
}

function openEditModal(proj) {
    document.getElementById('projModalTitle').textContent = 'Edit Project';
    document.getElementById('mf-action').value    = 'edit_project';
    document.getElementById('mf-proj-id').value   = proj.id;
    document.getElementById('mf-title').value     = proj.title   || '';
    document.getElementById('mf-desc').value      = proj.description || '';
    clearPreviews();
    for (let i = 1; i <= 3; i++) {
        const src = (proj['image' + i] || '').trim();
        if (src) {
            const img = document.getElementById('mf-preview-' + i);
            img.src = src;
            img.style.display = 'block';
        }
    }
    document.getElementById('projModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('projModal').style.display = 'none';
    document.getElementById('projForm').reset();
    clearPreviews();
}

function clearPreviews() {
    for (let i = 1; i <= 3; i++) {
        const img = document.getElementById('mf-preview-' + i);
        img.src = ''; img.style.display = 'none';
    }
}

function previewImg(input, idx) {
    const file = input.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = e => {
        const img = document.getElementById('mf-preview-' + idx);
        img.src = e.target.result;
        img.style.display = 'block';
    };
    reader.readAsDataURL(file);
}

/* ── Hero image live-preview + auto-submit ── */
function submitHeroImg(input) {
    const file = input.files[0];
    if (!file) return;
    // Show instant preview so admin sees the change immediately
    const reader = new FileReader();
    reader.onload = e => {
        document.getElementById('heroImg').src = e.target.result;
    };
    reader.readAsDataURL(file);
    // Submit the hidden form
    document.getElementById('heroImgForm').submit();
}

function confirmDelete(id) {
    if (!confirm('Delete this project? This action cannot be undone.')) return;
    document.getElementById('delete-proj-id').value = id;
    document.getElementById('deleteForm').submit();
}

/* Close modal on backdrop click */
document.getElementById('projModal').addEventListener('click', function (e) {
    if (e.target === this) closeModal();
});
</script>
<?php endif; ?>

<!-- ════════════════════════════════════════════
     IMAGE LIGHTBOX
════════════════════════════════════════════ -->
<div class="lightbox-overlay" id="imgLightbox" onclick="lbBgClose(event)">
    <button class="lightbox-close" onclick="closeLightbox()" aria-label="Close">✕</button>
    <button class="lightbox-prev" onclick="lbNav(-1); event.stopPropagation()" aria-label="Previous">&#8249;</button>
    <img class="lightbox-img" id="lbImg" src="" alt="">
    <button class="lightbox-next" onclick="lbNav(1); event.stopPropagation()" aria-label="Next">&#8250;</button>
    <div class="lightbox-counter" id="lbCounter"></div>
</div>

<script>
(function () {
    let lbImages = [], lbIdx = 0;

    window.openLightbox = function (imgEl, slideIdx) {
        // Collect all visible images in this slide
        const slide = imgEl.closest('.proj-slide');
        lbImages = Array.from(slide.querySelectorAll('.proj-images img'));
        lbIdx    = lbImages.indexOf(imgEl);
        showLb();
    };

    function showLb() {
        const lb  = document.getElementById('imgLightbox');
        const img = document.getElementById('lbImg');
        const ctr = document.getElementById('lbCounter');
        img.src = lbImages[lbIdx].src;
        img.alt = lbImages[lbIdx].alt;
        ctr.textContent = (lbIdx + 1) + ' / ' + lbImages.length;
        lb.classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    window.closeLightbox = function () {
        document.getElementById('imgLightbox').classList.remove('open');
        document.body.style.overflow = '';
    };

    window.lbNav = function (dir) {
        lbIdx = ((lbIdx + dir) + lbImages.length) % lbImages.length;
        const img = document.getElementById('lbImg');
        img.style.animation = 'none';
        requestAnimationFrame(() => {
            img.style.animation = '';
            img.src = lbImages[lbIdx].src;
            img.alt = lbImages[lbIdx].alt;
            document.getElementById('lbCounter').textContent = (lbIdx + 1) + ' / ' + lbImages.length;
        });
    };

    window.lbBgClose = function (e) {
        if (e.target === document.getElementById('imgLightbox') ||
            e.target === document.getElementById('lbImg')) {
            closeLightbox();
        }
    };

    // Keyboard support
    document.addEventListener('keydown', e => {
        const lb = document.getElementById('imgLightbox');
        if (!lb.classList.contains('open')) return;
        if (e.key === 'Escape')     closeLightbox();
        if (e.key === 'ArrowLeft')  lbNav(-1);
        if (e.key === 'ArrowRight') lbNav(1);
    });

    // Touch swipe support in lightbox
    let lbTouchX = 0;
    document.getElementById('imgLightbox').addEventListener('touchstart', e => {
        lbTouchX = e.touches[0].clientX;
    }, { passive: true });
    document.getElementById('imgLightbox').addEventListener('touchend', e => {
        const dx = e.changedTouches[0].clientX - lbTouchX;
        if (Math.abs(dx) > 40) lbNav(dx < 0 ? 1 : -1);
    }, { passive: true });
})();
</script>

</body>
<?php require 'footer.php'; ?>
</html>