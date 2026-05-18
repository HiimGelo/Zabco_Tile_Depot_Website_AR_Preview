<?php
ob_start(); // Buffer output so header() redirects always work
include 'db_connect.php';
session_start();

$isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

$validTables = ['productsmedian','productssophisticated','productsluxurious'];

// ── Admin POST handler ────────────────────────────────────────────────────────
// Must run BEFORE require 'header.php' — once header.php outputs HTML,
// header() redirects will fail and produce a blank page.
if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['admin_action'] ?? '';

    // Add product
    if ($act === 'add_product') {
        $table = $_POST['product_table'] ?? '';
        $name  = trim($_POST['product_name']  ?? '');
        $price = trim($_POST['product_price'] ?? '');
        $size  = trim($_POST['product_size']  ?? '');
        $type  = trim($_POST['product_type']  ?? '');
        $imgData = null;

        if (in_array($table, $validTables) && $name !== '') {
            if (isset($_FILES['product_img']) && $_FILES['product_img']['error'] === UPLOAD_ERR_OK) {
                $imgData = file_get_contents($_FILES['product_img']['tmp_name']);
            }
            $pdo->prepare("INSERT INTO $table (ProductName, Price, Size, Type, Image) VALUES (?,?,?,?,?)")
                ->execute([$name, $price ?: null, $size ?: null, $type ?: null, $imgData]);
        }
        header('Location: Products.php'); exit;
    }

    // Edit product
    if ($act === 'edit_product') {
        $table = $_POST['product_table'] ?? '';
        $id    = (int)($_POST['product_id']    ?? 0);
        $name  = trim($_POST['product_name']   ?? '');
        $price = trim($_POST['product_price']  ?? '');
        $size  = trim($_POST['product_size']   ?? '');
        $type  = trim($_POST['product_type']   ?? '');

        if (in_array($table, $validTables) && $id && $name) {
            if (isset($_FILES['product_img']) && $_FILES['product_img']['error'] === UPLOAD_ERR_OK) {
                $imgData = file_get_contents($_FILES['product_img']['tmp_name']);
                $pdo->prepare("UPDATE $table SET ProductName=?, Price=?, Size=?, Type=?, Image=? WHERE ProductID=?")
                    ->execute([$name, $price ?: null, $size ?: null, $type ?: null, $imgData, $id]);
            } else {
                $pdo->prepare("UPDATE $table SET ProductName=?, Price=?, Size=?, Type=? WHERE ProductID=?")
                    ->execute([$name, $price ?: null, $size ?: null, $type ?: null, $id]);
            }
        }
        header('Location: Products.php'); exit;
    }

    // Delete product
    if ($act === 'delete_product') {
        $table = $_POST['product_table'] ?? '';
        $id    = (int)($_POST['product_id'] ?? 0);
        if (in_array($table, $validTables) && $id) {
            $pdo->prepare("DELETE FROM $table WHERE ProductID=?")->execute([$id]);
        }
        header('Location: Products.php'); exit;
    }

    // Add type
    if ($act === 'add_type') {
        $typeName    = trim($_POST['type_name']    ?? '');
        $displayName = trim($_POST['display_name'] ?? '');
        if ($typeName && $displayName) {
            try {
                $pdo->prepare("INSERT IGNORE INTO product_types (TypeName, DisplayName) VALUES (?,?)")
                    ->execute([$typeName, $displayName]);
            } catch (PDOException $e) {}
        }
        header('Location: Products.php'); exit;
    }

    // Delete type
    if ($act === 'delete_type') {
        $id = (int)($_POST['type_id'] ?? 0);
        if ($id) {
            try { $pdo->prepare("DELETE FROM product_types WHERE TypeID=?")->execute([$id]); }
            catch (PDOException $e) {}
        }
        header('Location: Products.php'); exit;
    }
}

// ── require header.php AFTER all POST redirects ───────────────────────────────
require 'header.php';

// ── Sort / Filter inputs ──────────────────────────────────────────────────────
$sort   = $_GET['sort'] ?? 'default';
$orderBy = match($sort) {
    'price_asc'  => 'Price ASC',
    'price_desc' => 'Price DESC',
    default      => 'created_at DESC'
};

$search         = isset($_GET['search'])  ? trim($_GET['search'])  : '';
$perPage        = max(1, isset($_GET['perPage']) && ctype_digit($_GET['perPage']) ? (int)$_GET['perPage'] : 24);
$page           = max(1, isset($_GET['page'])    && ctype_digit($_GET['page'])    ? (int)$_GET['page']    : 1);
$offset         = ($page - 1) * $perPage;
$selectedTables = $_GET['table']  ?? [];
$selectedFinish = $_GET['finish'] ?? [];
$selectedSize   = $_GET['size']   ?? [];

if (!is_array($selectedFinish)) $selectedFinish = [$selectedFinish];
if (!is_array($selectedSize))   $selectedSize   = [$selectedSize];
if (!is_array($selectedTables)) $selectedTables = [$selectedTables];
$selectedFinish = array_values(array_filter($selectedFinish, fn($v) => $v !== ''));
$selectedSize   = array_values(array_filter($selectedSize,   fn($v) => $v !== ''));

$selectedTables = array_intersect($selectedTables, $validTables);
$tablesToUse    = !empty($selectedTables) ? $selectedTables : $validTables;

// ── Fetch product types from DB (fallback to hardcoded) ───────────────────────
$allTypes = [];
try {
    $typeRows = $pdo->query("SELECT TypeID, TypeName, DisplayName FROM product_types ORDER BY DisplayName")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($typeRows as $tr) $allTypes[$tr['TypeName']] = ['display' => $tr['DisplayName'], 'id' => $tr['TypeID']];
} catch (PDOException $e) {
    // Fallback if product_types table doesn't exist yet
    $fallback = ['Matte'=>'Matte','Glossy'=>'Glossy','Paver Stone Tile'=>'Paver Stone','Border Tiles'=>'Border','Stone Tile'=>'Stone','Wood Plank'=>'Wood Plank','Mosaic Tile'=>'Mosaic','Glazed'=>'Glazed','Rustic Tile'=>'Rustic','Rustic Stone Tile'=>'Rustic Stone','Wood Slab Tiles'=>'Wood Slab','WALL TILE'=>'Wall','STAIR TILE'=>'Stair','Rough Tile'=>'Rough','Homogeneous Matte'=>'Homogeneous Matte','Homogeneous Glossy'=>'Homogeneous Glossy','GLOSSY GRANITE TILE'=>'Glossy Granite','MATTE GRANITE TILE'=>'Matte Granite','POLISH GRANITE TILE'=>'Polish Granite','GRANITE TILE'=>'Granite'];
    foreach ($fallback as $k => $v) $allTypes[$k] = ['display' => $v, 'id' => null];
}

// ── SQL: Fetch products (add source_table for admin edit/delete) ──────────────
$queries = [];
$params  = [];

foreach ($tablesToUse as $index => $table) {
    $where = [];
    $searchKey      = ":search_name$index";
    $searchSizeKey  = ":search_size$index";
    $searchTypeKey  = ":search_type$index";
    $like           = "%$search%";
    $where[]        = "(ProductName LIKE $searchKey OR LOWER(Size) LIKE $searchSizeKey OR LOWER(Type) LIKE $searchTypeKey)";
    $params[$searchKey]     = $like;
    $params[$searchSizeKey] = strtolower($like);
    $params[$searchTypeKey] = strtolower($like);

    if (!empty($selectedFinish)) {
        $fp = [];
        foreach ($selectedFinish as $i => $f) {
            $fk = ":finish{$index}_$i"; $fp[] = "Type = $fk"; $params[$fk] = $f;
        }
        $where[] = "(" . implode(" OR ", $fp) . ")";
    }
    if (!empty($selectedSize)) {
        $sp = [];
        foreach ($selectedSize as $i => $s) {
            $sk = ":size{$index}_$i"; $sp[] = "LOWER(Size) = $sk"; $params[$sk] = strtolower($s);
        }
        $where[] = "(" . implode(" OR ", $sp) . ")";
    }
    $queries[] = "SELECT ProductID, ProductName, Price, Size, Type, Image, '$table' AS source_table, created_at
                  FROM $table WHERE " . implode(" AND ", $where);
}

$sql      = implode(" UNION ALL ", $queries) . " ORDER BY $orderBy LIMIT :offset, :perPage";
$countSql = "SELECT COUNT(*) FROM (" . implode(" UNION ALL ", $queries) . ") AS total";

$stmt      = $pdo->prepare($sql);
$countStmt = $pdo->prepare($countSql);
foreach ($params as $k => $v) { $stmt->bindValue($k, $v); $countStmt->bindValue($k, $v); }
$stmt->bindValue(':offset',  (int)$offset,  PDO::PARAM_INT);
$stmt->bindValue(':perPage', (int)$perPage, PDO::PARAM_INT);
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
$countStmt->execute();
$totalRows  = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($totalRows / $perPage));

// ── Available sizes ───────────────────────────────────────────────────────────
$sizeAvailQueries = [];
$sizeAvailParams  = [];
foreach ($tablesToUse as $index => $table) {
    $sWhere = ["Size IS NOT NULL", "Size != ''"];
    if (!empty($selectedFinish)) {
        $fp = [];
        foreach ($selectedFinish as $i => $f) { $k = ":savfin{$index}_{$i}"; $fp[] = "Type=$k"; $sizeAvailParams[$k] = $f; }
        $sWhere[] = "(" . implode(" OR ", $fp) . ")";
    }
    $sizeAvailQueries[] = "SELECT DISTINCT LOWER(Size) AS Size FROM $table WHERE " . implode(" AND ", $sWhere);
}
$sizeAvailStmt = $pdo->prepare(implode(" UNION ", $sizeAvailQueries));
foreach ($sizeAvailParams as $k => $v) $sizeAvailStmt->bindValue($k, $v);
$sizeAvailStmt->execute();
$availableSizes = array_map('strtolower', array_column($sizeAvailStmt->fetchAll(PDO::FETCH_ASSOC), 'Size'));

// ── Pagination helper ─────────────────────────────────────────────────────────
$qp = function($p) use ($perPage, $search, $selectedTables, $selectedFinish, $selectedSize, $sort) {
    $url = "Products.php?page=$p&perPage=$perPage&search=" . urlencode($search) . "&sort=" . urlencode($sort);
    foreach ($selectedTables as $t) $url .= "&table[]="  . urlencode($t);
    if (!empty($selectedFinish)) $url .= "&finish=" . urlencode($selectedFinish[0]);
    if (!empty($selectedSize))   $url .= "&size="   . urlencode($selectedSize[0]);
    return $url;
};

$allSizes = ['10x10'=>'10x10','15x90'=>'15x90','20x20'=>'20x20','20x100'=>'20x100','20x120'=>'20x120','30x30'=>'30x30','30x60'=>'30x60','40x40'=>'40x40','60x60'=>'60x60','60x120'=>'60x120','75x150'=>'75x150','80x80'=>'80x80','90x180'=>'90x180'];
?>

<!DOCTYPE html>
<html lang="en" data-bs-theme="light" data-bss-forced-theme="light" style="background:#f9f9f9;">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Products</title>
    <link rel="icon" type="image/png" href="Favicon.ico">
    <link rel="stylesheet" href="assets/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="ProductStyles.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">
    <style>
      /* ── Base / Nav (matching index.php) ───────────────────────────────── */
      html { overflow-x: clip; }          /* clip doesn't create a scroll container — position:fixed works on iOS */
      body { overflow-x: clip; background:#f9f9f9; font-family:'Inter',sans-serif; }
      .user { position:relative; }
      .logo { display:flex; align-items:center; height:100%; overflow:hidden; padding-left:8px; }
      .logo img { display:block; width:auto; height:auto; }
      .nav-toggle { display:none; background:transparent; border:none; cursor:pointer; padding:10px; margin-left:8px; align-self:center; }
      .nav-toggle .bar { display:block; width:22px; height:2px; background:#ffffff; margin:4px 0; transition:transform .18s ease,opacity .18s ease; }
      @media (max-width:768px){
        .nav-toggle{display:block;}
        .navbar>ul{display:none;}
        header.nav-open .navbar>ul{display:flex !important;position:absolute !important;top:0 !important;left:0 !important;width:220px !important;max-height:70vh;overflow-y:auto;flex-direction:column;gap:6px;margin:0;padding:8px 0;background:#ed8d1b !important;box-shadow:0 8px 28px rgba(0,0,0,.18) !important;border-radius:10px !important;z-index:1400 !important;list-style:none;border:1px solid #000 !important;text-align:center;}
        header.nav-open .navbar>ul li{padding:10px 12px;border-radius:6px;}
        header.nav-open .navbar>ul li a{display:block;padding:6px 8px;}
      }
      header.nav-open .nav-toggle .bar:nth-child(1){transform:translateY(6px) rotate(45deg);}
      header.nav-open .nav-toggle .bar:nth-child(2){opacity:0;}
      header.nav-open .nav-toggle .bar:nth-child(3){transform:translateY(-6px) rotate(-45deg);}
      .navbar>ul>li{margin-right:0 !important;text-align:center !important;display:block;}

      /* ── Page header ─────────────────────────────────────────────────── */
      .products-page-header {
        background: #151616;
        padding: 44px 48px 20px;
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 16px;
        flex-wrap: wrap;
      }
      .products-page-header .ph-left { display:flex; flex-direction:column; gap:6px; }
      .products-page-header .ph-eyebrow {
        display: inline-flex; align-items: center; gap: 8px;
        font-size: .72rem; font-weight: 700; letter-spacing: 2px;
        text-transform: uppercase; color: #ed8d1b;
      }
      .products-page-header .ph-eyebrow::before {
        content:''; display:inline-block; width:28px; height:2px;
        background:#ed8d1b; border-radius:2px;
      }
      .products-page-header h1 {
        font-size: clamp(1.6rem, 3.5vw, 2.6rem);
        font-weight: 900; color: #fff; margin: 0;
        letter-spacing: -.5px; line-height: 1.1;
      }
      .products-page-header h1 span { color: #ed8d1b; }
      .products-page-header .ph-count {
        font-size: .82rem; color: #666; font-weight: 500;
        background: #222; border-radius: 8px; padding: 4px 12px;
        align-self: flex-end;
      }
      .products-page-header .ph-count strong { color: #ed8d1b; }
      @media (max-width:640px){
        .products-page-header { padding: 28px 20px 24px; }
        .products-page-header .ph-count { display:none; }
      }

      /* ── Admin bar ───────────────────────────────────────────────────── */
      .admin-product-bar {
        display: flex; justify-content: flex-end; align-items: center;
        padding: 0 48px 20px; gap: 10px;
        background: #151616;
      }
      @media (max-width:640px){ .admin-product-bar { padding: 10px 20px 0; } }

      /* ── Layout ──────────────────────────────────────────────────────── */
      .products-layout {
        max-width: 1600px;
        margin: 0 auto;
        padding: 28px 32px 60px;
        display: flex;
        gap: 28px;
        align-items: start;
      }
      .products-outer { flex: 1; min-width: 0; }
      .products-content { width: 100%; }

      /* Desktop sidebar */
      @media (min-width: 769px) {
        .filter-sidebar {
          width: 260px;
          flex-shrink: 0;
          align-self: flex-start;
          position: sticky;
          top: 20px;
          max-height: calc(100vh - 40px);
          overflow-y: auto;
        }
      }
      @media (min-width: 769px) and (max-width: 1024px) {
        .filter-sidebar { width: 220px; }
        .products-layout { gap: 20px; padding: 20px 20px 48px; }
      }

      /* ── Filter Sidebar ──────────────────────────────────────────────── */
      .filter-sidebar {
        background: #fff;
        border-radius: 16px;
        border: 1px solid #ececec;
        box-shadow: 0 2px 14px rgba(0,0,0,.06);
        padding: 20px;
      }
      .filter-header {
        display: flex; align-items: center; justify-content: space-between;
        padding: 14px 16px;
        margin: -20px -20px 16px;
        background: #151616;
        border-radius: 16px 16px 0 0;
        gap: 10px;
      }
      .filter-header h4 {
        margin: 0; font-size: 1rem; font-weight: 900;
        color: #fff; letter-spacing: -.2px;
        display: flex; align-items: center; gap: 8px;
      }
      .filter-header h4 svg { opacity: .7; }
      .filter-header-right {
        display: flex; align-items: center; gap: 8px; flex-shrink: 0;
      }
      .clear-filters-btn {
        font-size: 11px; font-weight: 700; color: #ed8d1b;
        text-decoration: none; border: 1.5px solid #ed8d1b;
        border-radius: 20px; padding: 3px 10px; letter-spacing: .4px;
        transition: background .18s, color .18s; white-space: nowrap;
      }
      .clear-filters-btn:hover { background: #ed8d1b; color: #151616; }
      .filter-drawer-close {
        background: rgba(255,255,255,.08);
        border: 1.5px solid rgba(255,255,255,.18);
        border-radius: 8px;
        width: 30px; height: 30px;
        display: none;
        align-items: center; justify-content: center;
        cursor: pointer; font-size: 13px; color: #ccc;
        transition: background .18s, color .18s; flex-shrink: 0;
      }
      .filter-drawer-close:hover { background: rgba(255,255,255,.18); color: #fff; border-color: rgba(255,255,255,.35); }
      .filter-group { display: flex; flex-direction: column; gap: 0; }
      .filter-group > p, .filter-section-label {
        font-size: .7rem; font-weight: 800; text-transform: uppercase;
        letter-spacing: 1.2px; color: #ed8d1b; margin: 16px 0 8px;
        display: flex; align-items: center; gap: 6px;
      }
      .filter-group > p::after, .filter-section-label::after {
        content: ''; flex: 1; height: 1px; background: #f0f0f0;
      }
      .filter-group label {
        display: flex; align-items: center; gap: 8px;
        font-size: .85rem; font-weight: 500; color: #444;
        padding: 5px 4px; cursor: pointer; border-radius: 6px;
        transition: background .14s;
      }
      .filter-group label:hover { background: #fdf4e7; color: #151616; }
      .filter-group input[type="checkbox"] {
        accent-color: #ed8d1b;
        width: 15px; height: 15px; cursor: pointer; flex-shrink: 0;
      }
      .size-label-unavailable { display: none !important; }
      /* ── Custom chip filter panels ── */
      .chip-filter-wrap {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-bottom: 4px;
        padding: 2px 0;
      }
      .chip-filter-wrap.collapsed .chip-label:nth-child(n+9) {
        display: none;
      }
      .chip-label {
        display: inline-flex;
        align-items: center;
        cursor: pointer;
        user-select: none;
      }
      .chip-label input[type="radio"] {
        position: absolute;
        opacity: 0;
        width: 0; height: 0;
        pointer-events: none;
      }
      .chip-text {
        display: inline-flex;
        align-items: center;
        padding: 5px 11px;
        border-radius: 20px;
        border: 1.5px solid #e4e4e4;
        background: #fafafa;
        font-size: .76rem;
        font-weight: 600;
        color: #555;
        letter-spacing: .2px;
        transition: border-color .15s, background .15s, color .15s, box-shadow .15s;
        white-space: nowrap;
      }
      .chip-label:hover .chip-text {
        border-color: #ed8d1b;
        background: #fdf4e7;
        color: #151616;
      }
      .chip-label input[type="radio"]:checked + .chip-text {
        background: #ed8d1b;
        border-color: #ed8d1b;
        color: #151616;
        font-weight: 800;
        box-shadow: 0 2px 8px rgba(237,141,27,.28);
      }
      .chip-text.chip-unavailable {
        opacity: .38;
        cursor: not-allowed;
        text-decoration: line-through;
      }
      .chip-show-more {
        background: none;
        border: 1.5px dashed #ddd;
        border-radius: 20px;
        padding: 5px 11px;
        font-size: .75rem;
        font-weight: 700;
        color: #888;
        cursor: pointer;
        font-family: inherit;
        transition: border-color .15s, color .15s;
        white-space: nowrap;
        letter-spacing: .2px;
      }
      .chip-show-more:hover { border-color: #ed8d1b; color: #ed8d1b; }

      /* mobile filter toggle */
      .mobile-filter-btn {
        display: none;
        align-items: center;
        gap: 7px;
        background: #151616;
        color: #fff;
        border: 1.5px solid #2e2e2e;
        border-radius: 10px;
        padding: 9px 14px;
        font-size: .82rem;
        font-weight: 700;
        font-family: inherit;
        cursor: pointer;
        transition: background .18s, border-color .18s;
        white-space: nowrap;
        flex-shrink: 0;
      }
      .mobile-filter-btn:hover { background: #222; border-color: #ed8d1b; }
      .mobile-filter-count {
        background: #ed8d1b;
        color: #151616;
        border-radius: 20px;
        font-size: .7rem;
        font-weight: 900;
        padding: 1px 7px;
        letter-spacing: 0;
      }

      /* ── Filter Drawer Overlay ── */
      .filter-drawer-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,.55);
        z-index: 1500;
        backdrop-filter: blur(2px);
        -webkit-backdrop-filter: blur(2px);
      }
      .filter-drawer-overlay.open { display: block; }

      /* ── Active filter badge ── */
      .filter-active-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #ed8d1b;
        color: #151616;
        border-radius: 20px;
        font-size: .65rem;
        font-weight: 900;
        padding: 1px 7px;
        margin-left: 6px;
        letter-spacing: 0;
      }

      /* ── Products grid ───────────────────────────────────────────────── */
      .products-content { min-width: 0; }
      .products-top-bar {
        display: flex; align-items: center; justify-content: space-between;
        margin-bottom: 20px; gap: 12px; flex-wrap: wrap;
      }
      .top-bar-left {
        display: flex; align-items: center; gap: 10px;
      }
      .products-count-label {
        font-size: .88rem; color: #888; font-weight: 500;
      }
      .products-count-label strong { color: #151616; font-weight: 800; }
      .sort-bar-inline {
        display: flex; align-items: center; gap: 10px;
      }
      .sort-bar-inline label {
        font-size: .82rem; font-weight: 700; color: #666;
        white-space: nowrap;
      }
      .sort-bar-inline select {
        padding: 7px 12px; border: 1.5px solid #e0e0e0;
        border-radius: 8px; font-size: .85rem; font-weight: 600;
        color: #151616; background: #fff; cursor: pointer;
        transition: border-color .18s;
        font-family: inherit;
      }
      .sort-bar-inline select:focus { outline: none; border-color: #ed8d1b; }
      .per-page-select {
        padding: 7px 12px; border: 1.5px solid #e0e0e0;
        border-radius: 8px; font-size: .85rem; font-weight: 600;
        color: #151616; background: #fff; cursor: pointer;
        transition: border-color .18s; font-family: inherit;
      }
      .per-page-select:focus { outline: none; border-color: #ed8d1b; }

      /* ── Product cards ───────────────────────────────────────────────── */
      .product-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 20px;
      }
      .product-card {
        background: #fff;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 4px 18px rgba(0,0,0,.07);
        transition: transform .22s, box-shadow .22s;
        position: relative;
        display: flex;
        flex-direction: column;
        text-decoration: none;
      }
      .product-card:hover {
        transform: translateY(-6px);
        box-shadow: 0 14px 36px rgba(0,0,0,.13);
      }
      .product-card-img-wrap {
        aspect-ratio: 1 / 1;
        overflow: hidden;
        background: #ffffff;
        position: relative;
      }
      .product-card-img {
        width: 100%; height: 100%;
        object-fit: cover;
        display: block;
        transition: transform .38s ease;
      }
      .product-card:hover .product-card-img { transform: scale(1.06); }
      .product-card-body {
        padding: 14px 14px 16px;
        display: flex; flex-direction: column; gap: 6px;
        flex: 1;
      }
      .product-card-name {
        font-size: .9rem; font-weight: 800; color: #151616;
        line-height: 1.3; margin: 0;
        display: -webkit-box; -webkit-line-clamp: 2;
        -webkit-box-orient: vertical; overflow: hidden;
      }
      .product-card-meta {
        display: flex; flex-wrap: wrap; gap: 5px;
        margin-top: 2px;
      }
      .product-card-tag {
        font-size: .68rem; font-weight: 700; letter-spacing: .4px;
        text-transform: uppercase;
        background: #f5f5f5; color: #666;
        padding: 2px 8px; border-radius: 20px;
        border: 1px solid #eee;
      }
      .product-card-price {
        font-size: 1.05rem; font-weight: 900;
        color: #ed8d1b; margin: 4px 0 0;
        letter-spacing: -.3px;
      }
      .product-card-price.no-price { color: #bbb; font-size: .85rem; font-weight: 500; }

      /* no-image placeholder */
      .product-card-img-wrap .no-img-placeholder {
        width:100%; height:100%;
        display:flex; align-items:center; justify-content:center;
        background: linear-gradient(135deg,#f5f5f5,#ebebeb);
        color: #ccc; font-size: 2.5rem;
      }

      /* empty state */
      .products-empty {
        grid-column: 1/-1;
        text-align: center; padding: 64px 20px;
        color: #aaa;
      }
      .products-empty .empty-icon { font-size: 3rem; margin-bottom: 12px; opacity:.4; }
      .products-empty p { font-size: 1rem; font-weight: 600; color: #999; margin: 0; }

      /* ── Pagination ──────────────────────────────────────────────────── */
      .pagination-wrap {
        display: flex; justify-content: center; align-items: center;
        gap: 6px; padding: 32px 0 8px; flex-wrap: wrap;
      }
      .btn-page {
        display: inline-flex; align-items: center; justify-content: center;
        min-width: 38px; height: 38px; padding: 0 10px;
        border-radius: 10px; font-size: .88rem; font-weight: 700;
        color: #444; background: #fff; border: 1.5px solid #e8e8e8;
        text-decoration: none; transition: all .18s;
      }
      .btn-page:hover { border-color: #ed8d1b; color: #ed8d1b; background: #fdf4e7; }
      .btn-page.active { background: #ed8d1b; color: #151616; border-color: #ed8d1b; }
      .btn-page img { width:16px; height:16px; opacity:.5; }
      .dots { color:#bbb; font-size:.9rem; padding:0 4px; display:inline-flex; align-items:center; height:38px; }

      /* ── Admin ───────────────────────────────────────────────────────── */
      .admin-badge{position:fixed;bottom:20px;right:20px;background:#ed8d1b;color:#151616;font-weight:800;font-size:11px;letter-spacing:.8px;padding:6px 14px;border-radius:100px;z-index:500;text-transform:uppercase;box-shadow:0 4px 16px rgba(237,141,27,.35);pointer-events:none;}
      .admin-add-btn{background:#ed8d1b;color:#151616;border:none;border-radius:10px;padding:9px 18px;font-weight:800;font-size:13px;cursor:pointer;transition:background .2s;display:flex;align-items:center;gap:6px;}
      .admin-add-btn:hover{background:#c97415;}
      .ae-wrap{position:relative;}
      .ae-actions{position:absolute;top:6px;right:6px;display:flex;gap:5px;opacity:0;transition:opacity .18s;z-index:20;}
      .ae-wrap:hover .ae-actions{opacity:1;}
      .ae-edit,.ae-del{border:none;border-radius:6px;padding:4px 9px;font-size:11px;font-weight:800;cursor:pointer;line-height:1.4;}
      .ae-edit{background:#ed8d1b;color:#151616;}
      .ae-del{background:#8b1a1a;color:#fff;}
      .admin-type-section{margin-top:18px;padding-top:14px;border-top:1.5px solid #f0f0f0;}
      .admin-type-list{list-style:none;padding:0;margin:0 0 10px;}
      .admin-type-list li{display:flex;align-items:center;justify-content:space-between;padding:5px 0;font-size:13px;color:#555;}
      .admin-type-del{background:none;border:none;color:#c0392b;font-size:16px;cursor:pointer;padding:0 4px;line-height:1;}
      .admin-type-form{display:flex;gap:6px;flex-wrap:wrap;margin-top:8px;}
      .admin-type-form input{flex:1;min-width:80px;padding:6px 10px;border:1.5px solid #e0e0e0;border-radius:6px;font-size:12px;font-family:inherit;}
      .admin-type-form button{padding:6px 12px;background:#ed8d1b;color:#151616;border:none;border-radius:6px;font-weight:800;font-size:12px;cursor:pointer;}
      /* Modal */
      .aov{display:none;position:fixed;inset:0;background:rgba(0,0,0,.76);z-index:9000;align-items:center;justify-content:center;padding:20px;backdrop-filter:blur(3px);}
      .aov.open{display:flex;}
      .am{background:#1e1e1e;border:1px solid #2e2e2e;border-radius:18px;padding:32px 28px;width:100%;max-width:500px;box-shadow:0 8px 40px rgba(0,0,0,.55);max-height:90vh;overflow-y:auto;}
      .am h3{color:#fff;font-size:20px;font-weight:800;margin-bottom:20px;padding-bottom:10px;border-bottom:3px solid #ed8d1b;}
      .am label{display:block;font-size:11px;font-weight:700;color:#999;text-transform:uppercase;letter-spacing:.6px;margin-top:14px;margin-bottom:5px;}
      .am input[type="text"],.am input[type="number"],.am select{width:100%;padding:10px 13px;background:#2a2a2a;border:1.5px solid #3a3a3a;border-radius:8px;color:#fff;font-family:inherit;font-size:14px;transition:border-color .2s;}
      .am input:focus,.am select:focus{outline:none;border-color:#ed8d1b;background:#2f2f2f;}
      .am input[type="file"]{width:100%;color:#aaa;font-size:13px;padding:8px 0;}
      .am select option{background:#2a2a2a;}
      .am-row{display:flex;gap:12px;}
      .am-row>div{flex:1;}
      .am-cur-img{width:80px;height:60px;object-fit:cover;border-radius:6px;margin-top:8px;border:1px solid #3a3a3a;}
      .am-actions{display:flex;gap:10px;margin-top:22px;flex-wrap:wrap;}
      .am-submit{flex:1;padding:11px;background:#ed8d1b;color:#151616;border:none;border-radius:8px;font-weight:800;font-size:14px;cursor:pointer;transition:background .2s;}
      .am-submit:hover{background:#c97415;}
      .am-cancel{padding:11px 18px;background:transparent;color:#888;border:1.5px solid #3a3a3a;border-radius:8px;font-weight:700;font-size:14px;cursor:pointer;transition:border-color .2s,color .2s;}
      .am-cancel:hover{border-color:#555;color:#ccc;}
      .am-danger{padding:11px 18px;background:#8b1a1a;color:#fff;border:none;border-radius:8px;font-weight:700;font-size:14px;cursor:pointer;transition:background .2s;}
      .am-danger:hover{background:#b52020;}

      /* ================================================================
         RESPONSIVE — placed AFTER base styles so they always win
         ================================================================ */

      @media (max-width:768px){
        .mobile-filter-btn { display: flex; }
        .filter-drawer-close { display: flex; }

        .products-layout {
          padding: 14px 14px 48px;
          gap: 0;
        }

        /* Sidebar slides in from the left — same card appearance as desktop */
        .filter-sidebar {
          position: fixed;
          top: 0; bottom: 0;
          left: -320px; right: auto;
          width: 290px;
          z-index: 1600;
          border-radius: 0 16px 16px 0;
          border: 1px solid #ececec;
          box-shadow: 6px 0 40px rgba(0,0,0,.15);
          background: #fff;
          /* Use dvh so the panel never extends under iOS Safari's browser chrome */
          height: 100vh;
          height: 100dvh;
          max-height: 100vh;
          max-height: 100dvh;
          /* Extra bottom padding so the last item clears the home indicator */
          padding: 20px 20px calc(env(safe-area-inset-bottom, 12px) + 20px);
          overflow-y: auto;
          overflow-x: hidden;
          -webkit-overflow-scrolling: touch;
          overscroll-behavior: contain;
          transition: left .28s cubic-bezier(0.4,0,0.2,1);
          will-change: left;
        }
        /* No drag handle on side panel */
        .filter-sidebar::before { display: none; }
        .filter-sidebar.drawer-open { left: 0; padding-top: 0px; }

        /* Header stays pinned at top while scrolling */
        .filter-header {
          position: sticky;
          top: 0;
          background: #151616;
          z-index: 2;
          margin-bottom: 15px;
          /* extend to sidebar edges so it covers content scrolling underneath */
          margin-left: -20px;
          margin-right: -20px;
          padding-left: 16px;
          padding-right: 16px;
          border-radius: 0;
        }

        .products-page-header { padding: 28px 20px 20px; }
      }

      @media (max-width:640px){
        /* ── 2-column product grid ── */
        .product-grid {
          grid-template-columns: repeat(2, 1fr) !important;
          gap: 10px;
        }
        .product-card-body { padding: 9px 9px 11px; gap: 4px; }
        .product-card-name { font-size: .78rem; }
        .product-card-price { font-size: .9rem; }
        .product-card-tag { font-size: .6rem; padding: 2px 5px; }

        /* ── Top bar: single row, filter left / sort right ── */
        .products-top-bar {
          flex-wrap: nowrap;
          gap: 8px;
          margin-bottom: 12px;
          align-items: center;
        }
        .top-bar-left { gap: 8px; flex-shrink: 0; }
        .products-count-label { display: none; }
        .sort-bar-inline { flex-shrink: 0; }
        .sort-bar-inline label { display: none; }
        .sort-bar-inline select { font-size: .78rem; padding: 7px 8px; }
        .per-page-select { display: none; }

        /* ── Page header ── */
        .products-page-header { padding: 22px 14px 18px; }
        .products-page-header h1 { font-size: 1.5rem; }

        /* ── Pagination ── */
        .pagination-wrap { gap: 4px; padding: 20px 0 6px; }
        .btn-page { min-width: 36px; height: 36px; font-size: .8rem; padding: 0 8px; }

        /* ── Outer padding ── */
        .products-layout { padding: 12px 10px 40px; }
      }
      
      @media (max-width:480px){
          .products-page-header{padding: 22px 14px 0px;}
          .admin-product-bar{padding: 10px 22px 15px;}
          .products-page-header h1{padding-bottom: 15px;}
      }

      @media (max-width:400px){
        .product-grid { gap: 8px !important; }
        .product-card-body { padding: 7px 7px 9px; }
        .product-card-name { font-size: .72rem; }
        .product-card-price { font-size: .84rem; }
        .mobile-filter-btn { padding: 8px 11px; font-size: .78rem; }
      }
    </style>
</head>

<body style="background:#f9f9f9;">

<?php if ($isAdmin): ?>
<div class="admin-badge">⚙ Admin Mode</div>
<?php endif; ?>

<!-- Page Header -->
<div class="products-page-header">
  <div class="ph-left">
    <span class="ph-eyebrow">Our Collection</span>
    <h1>Browse <span>Products</span></h1>
  </div>
  <span class="ph-count"><strong><?= number_format($totalRows) ?></strong> product<?= $totalRows !== 1 ? 's' : '' ?> found</span>
</div>

<?php if ($isAdmin): ?>
<div class="admin-product-bar">
  <button class="admin-add-btn" onclick="openModal('modal-add-product')" type="button">+ Add Product</button>
</div>
<?php endif; ?>

<!-- Mobile filter drawer overlay -->
<div class="filter-drawer-overlay" id="filterOverlay"></div>

<div class="products-layout">

<!-- Filter Sidebar — outside the products-outer div so position:fixed works correctly on mobile -->
<aside class="filter-sidebar">
  <div class="filter-header">
    <h4>
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#ed8d1b" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="4" y1="6" x2="20" y2="6"/><line x1="8" y1="12" x2="16" y2="12"/><line x1="11" y1="18" x2="13" y2="18"/></svg>
      Filters
      <?php $activeCount = count($selectedFinish) + count($selectedSize) + count($selectedTables); ?>
      <?php if ($activeCount > 0): ?><span class="filter-active-badge"><?= $activeCount ?></span><?php endif; ?>
    </h4>
    <div class="filter-header-right">
      <?php if (!empty($selectedFinish) || !empty($selectedSize) || !empty($selectedTables)): ?>
      <a href="Products.php?perPage=<?= $perPage ?>&sort=<?= urlencode($sort) ?>" class="clear-filters-btn">&#x2715; Clear</a>
      <?php endif; ?>
      <button class="filter-drawer-close" id="filterDrawerClose" aria-label="Close filters" type="button">&#x2715;</button>
    </div>
  </div>
  <div class="filter-group">
    <p>Category</p>
    <form method="GET" action="Products.php" id="filterMainForm">
      <input type="hidden" name="search"  id="filterSearchInput" value="">
      <input type="hidden" name="perPage" value="<?= $perPage ?>">
      <input type="hidden" name="sort"    value="<?= htmlspecialchars($sort) ?>">

      <label><input type="checkbox" id="allCheck" <?= empty($selectedTables) ? 'checked' : '' ?>> All</label>
      <label><input type="checkbox" name="table[]" value="productsmedian"        <?= in_array('productsmedian',        $selectedTables) ? 'checked':'' ?>> Median</label>
      <label><input type="checkbox" name="table[]" value="productssophisticated" <?= in_array('productssophisticated', $selectedTables) ? 'checked':'' ?>> Sophisticated</label>
      <label><input type="checkbox" name="table[]" value="productsluxurious"     <?= in_array('productsluxurious',     $selectedTables) ? 'checked':'' ?>> Luxurious</label>

      <p>Type</p>
      <div class="chip-filter-wrap collapsed" id="typeChipsWrap">
        <label class="chip-label">
          <input type="radio" name="finish" id="filterTypeSelect" value="" onchange="submitChipFilter()"
            <?= empty($selectedFinish) ? 'checked' : '' ?>>
          <span class="chip-text">All</span>
        </label>
        <?php foreach ($allTypes as $typeName => $typeData): ?>
        <label class="chip-label">
          <input type="radio" name="finish" value="<?= htmlspecialchars($typeName) ?>" onchange="submitChipFilter()"
            <?= (!empty($selectedFinish) && $selectedFinish[0] === $typeName) ? 'checked' : '' ?>>
          <span class="chip-text"><?= htmlspecialchars($typeData['display']) ?></span>
        </label>
        <?php endforeach; ?>
      </div>
      <button type="button" class="chip-show-more" id="typeShowMore" onclick="toggleChips('typeChipsWrap','typeShowMore')">+ More</button>

      <p>Size</p>
      <div class="chip-filter-wrap" id="sizeChipsWrap">
        <label class="chip-label">
          <input type="radio" name="size" id="filterSizeSelect" value="" onchange="submitChipFilter()"
            <?= empty($selectedSize) ? 'checked' : '' ?>>
          <span class="chip-text">All</span>
        </label>
        <?php foreach ($allSizes as $sVal => $sLabel):
            $isAvailable = empty($selectedFinish) || in_array($sVal, $availableSizes);
        ?>
        <label class="chip-label"<?= !$isAvailable ? ' style="pointer-events:none;"' : '' ?>>
          <input type="radio" name="size" value="<?= htmlspecialchars($sVal) ?>" onchange="submitChipFilter()"
            <?= (!empty($selectedSize) && $selectedSize[0] === $sVal) ? 'checked' : '' ?>
            <?= !$isAvailable ? 'disabled' : '' ?>>
          <span class="chip-text<?= !$isAvailable ? ' chip-unavailable' : '' ?>"><?= htmlspecialchars($sLabel) ?></span>
        </label>
        <?php endforeach; ?>
      </div>

      <p>Per Page</p>
      <div class="chip-filter-wrap" id="perPageChipsWrap">
        <?php foreach ([12, 24, 48, 96] as $pp): ?>
        <label class="chip-label">
          <input type="radio" name="perPage" value="<?= $pp ?>" onchange="submitChipFilter()"
            <?= $perPage === $pp ? 'checked' : '' ?>>
          <span class="chip-text"><?= $pp ?></span>
        </label>
        <?php endforeach; ?>
      </div>
    </form>

    <?php if ($isAdmin): ?>
    <div class="admin-type-section">
      <p class="filter-section-label">Manage Types</p>
      <ul class="admin-type-list">
        <?php foreach ($allTypes as $typeName => $typeData): ?>
        <?php if ($typeData['id']): ?>
        <li>
          <span><?= htmlspecialchars($typeData['display']) ?></span>
          <form method="POST" action="Products.php" style="display:inline;" onsubmit="return confirm('Delete type «<?= addslashes(htmlspecialchars($typeData['display'])) ?>»?')">
            <input type="hidden" name="admin_action" value="delete_type">
            <input type="hidden" name="type_id" value="<?= $typeData['id'] ?>">
            <button class="admin-type-del" type="submit" title="Delete">&times;</button>
          </form>
        </li>
        <?php endif; ?>
        <?php endforeach; ?>
      </ul>
      <form method="POST" action="Products.php" class="admin-type-form">
        <input type="hidden" name="admin_action" value="add_type">
        <input type="text" name="type_name"    placeholder="DB value (e.g. Matte)" required>
        <input type="text" name="display_name" placeholder="Label (e.g. Matte)"    required>
        <button type="submit">+ Add</button>
      </form>
    </div>
    <?php endif; ?>
  </div>
</aside>

<div class="products-outer" id="productsContainer">

  <!-- Products Content -->
  <div class="products-content">

    <!-- Top bar: count + sort + mobile filter btn -->
    <div class="products-top-bar">
      <div class="top-bar-left">
        <button class="mobile-filter-btn" id="mobileFilterBtn" type="button">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="4" y1="6" x2="20" y2="6"/><line x1="8" y1="12" x2="16" y2="12"/><line x1="11" y1="18" x2="13" y2="18"/></svg>
          Filters<?php if ($activeCount > 0): ?> <span class="mobile-filter-count"><?= $activeCount ?></span><?php endif; ?>
        </button>
        <span class="products-count-label">
          <strong><?= count($products) ?></strong> of <strong><?= number_format($totalRows) ?></strong> products
        </span>
      </div>
      <div class="sort-bar-inline">
        <form method="GET" id="sortForm" style="display:flex;align-items:center;gap:8px;">
          <input type="hidden" name="search"  value="<?= htmlspecialchars($search) ?>">
          <?php foreach ($selectedTables as $t): ?><input type="hidden" name="table[]"  value="<?= htmlspecialchars($t) ?>"><?php endforeach; ?>
          <?php if (!empty($selectedFinish)): ?><input type="hidden" name="finish" value="<?= htmlspecialchars($selectedFinish[0]) ?>"><?php endif; ?>
          <?php if (!empty($selectedSize)):   ?><input type="hidden" name="size"   value="<?= htmlspecialchars($selectedSize[0])   ?>"><?php endif; ?>
          <input type="hidden" name="page" value="1">
          <label>Sort:</label>
          <select name="sort" class="sort-bar-inline select" onchange="this.form.submit()">
            <option value="default"    <?= $sort==='default'    ? 'selected':'' ?>>Featured</option>
            <option value="price_asc"  <?= $sort==='price_asc'  ? 'selected':'' ?>>Price: Low → High</option>
            <option value="price_desc" <?= $sort==='price_desc' ? 'selected':'' ?>>Price: High → Low</option>
          </select>
          <select name="perPage" class="per-page-select" onchange="this.form.submit()">
            <?php foreach ([12,24,48,96] as $pp): ?>
            <option value="<?= $pp ?>" <?= $perPage===$pp ? 'selected':'' ?>><?= $pp ?> / page</option>
            <?php endforeach; ?>
          </select>
        </form>
      </div>
    </div>

    <!-- Product grid -->
    <div class="product-grid">
      <?php if (empty($products)): ?>
        <div class="products-empty">
          <div class="empty-icon">&#128269;</div>
          <p>No products found. Try adjusting your filters.</p>
        </div>
      <?php else: ?>
        <?php foreach ($products as $p): ?>
        <div class="product-card ae-wrap<?= $isAdmin ? '' : '' ?>">
          <?php if ($isAdmin): ?>
          <div class="ae-actions">
            <button class="ae-edit" type="button"
              onclick="openEditProduct(
                <?= $p['ProductID'] ?>,
                '<?= addslashes(htmlspecialchars($p['source_table'])) ?>',
                '<?= addslashes(htmlspecialchars($p['ProductName'])) ?>',
                '<?= $p['Price'] !== null ? $p['Price'] : '' ?>',
                '<?= addslashes(htmlspecialchars($p['Size'] ?? '')) ?>',
                '<?= addslashes(htmlspecialchars($p['Type'] ?? '')) ?>'
              )">&#x270E; Edit</button>
            <button class="ae-del" type="button"
              onclick="openDeleteProduct(<?= $p['ProductID'] ?>,'<?= addslashes(htmlspecialchars($p['source_table'])) ?>','<?= addslashes(htmlspecialchars($p['ProductName'])) ?>')">&#x2715; Del</button>
          </div>
          <?php endif; ?>
          <a href="ProductDetails.php?id=<?= $p['ProductID'] ?>&table=<?= urlencode($p['source_table']) ?>" class="product-card" style="text-decoration:none;display:contents;">
            <div class="product-card-img-wrap">
              <?php if ($p['Image']): ?>
              <img class="product-card-img"
                   src="data:image/jpeg;base64,<?= base64_encode($p['Image']) ?>"
                   alt="<?= htmlspecialchars($p['ProductName']) ?>">
              <?php else: ?>
              <div class="no-img-placeholder">&#128247;</div>
              <?php endif; ?>
            </div>
            <div class="product-card-body">
              <p class="product-card-name"><?= htmlspecialchars($p['ProductName']) ?></p>
              <div class="product-card-meta">
                <?php if (!empty($p['Size'])): ?><span class="product-card-tag"><?= htmlspecialchars($p['Size']) ?></span><?php endif; ?>
                <?php if (!empty($p['Type'])): ?><span class="product-card-tag"><?= htmlspecialchars($p['Type']) ?></span><?php endif; ?>
              </div>
              <p class="product-card-price <?= $p['Price'] === null ? 'no-price' : '' ?>">
                <?= $p['Price'] !== null ? '&#8369;' . number_format($p['Price'], 2) : 'Price on request' ?>
              </p>
            </div>
          </a>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- Pagination -->
    <div class="pagination-wrap">
      <?php if ($page > 1): ?><a href="<?= $qp($page-1) ?>" class="btn-page">&lsaquo;</a><?php endif; ?>
      <?php
      $range = 2; $start = max(1,$page-$range); $end = min($totalPages,$page+$range);
      if ($start > 1) { echo '<a href="'.$qp(1).'" class="btn-page">1</a>'; if ($start>2) echo '<span class="dots">…</span>'; }
      for ($i=$start;$i<=$end;$i++) echo '<a href="'.$qp($i).'" class="btn-page '.($page==$i?'active':'').'">'.$i.'</a>';
      if ($end<$totalPages) { if ($end<$totalPages-1) echo '<span class="dots">…</span>'; echo '<a href="'.$qp($totalPages).'" class="btn-page">'.$totalPages.'</a>'; }
      ?>
      <?php if ($page < $totalPages): ?><a href="<?= $qp($page+1) ?>" class="btn-page">&rsaquo;</a><?php endif; ?>
    </div>

</div><!-- end .products-content -->

</div><!-- end .products-outer -->

</div><!-- end .products-layout -->

<?php if ($isAdmin): ?>
<!-- ════════════════════════════════════════
     ADMIN MODALS
════════════════════════════════════════ -->

<!-- Add Product -->
<div class="aov" id="modal-add-product">
    <div class="am">
        <h3>Add New Product</h3>
        <form method="POST" enctype="multipart/form-data" action="Products.php">
            <input type="hidden" name="admin_action" value="add_product">
            <label>Category</label>
            <select name="product_table" required>
                <option value="">— Select Category —</option>
                <option value="productsmedian">Median (Budget)</option>
                <option value="productssophisticated">Sophisticated (Mid-Range)</option>
                <option value="productsluxurious">Luxurious (High-End)</option>
            </select>
            <label>Product Name</label>
            <input type="text" name="product_name" placeholder="e.g. Marble Slate 60x60" required>
            <div class="am-row">
                <div>
                    <label>Price (₱)</label>
                    <input type="number" name="product_price" placeholder="0.00" step="0.01" min="0">
                </div>
                <div>
                    <label>Size</label>
                    <select name="product_size">
                        <option value="">— Size —</option>
                        <?php foreach ($allSizes as $sv => $sl): ?>
                        <option value="<?= $sv ?>"><?= $sl ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <label>Type</label>
            <select name="product_type">
                <option value="">— Type —</option>
                <?php foreach ($allTypes as $tn => $td): ?>
                <option value="<?= htmlspecialchars($tn) ?>"><?= htmlspecialchars($td['display']) ?></option>
                <?php endforeach; ?>
            </select>
            <label>Product Image (JPG / PNG, max 5 MB)</label>
            <input type="file" name="product_img" accept="image/*">
            <div class="am-actions">
                <button type="button" class="am-cancel" onclick="closeModal('modal-add-product')">Cancel</button>
                <button type="submit" class="am-submit">Add Product</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Product -->
<div class="aov" id="modal-edit-product">
    <div class="am">
        <h3>Edit Product</h3>
        <form method="POST" enctype="multipart/form-data" action="Products.php">
            <input type="hidden" name="admin_action" value="edit_product">
            <input type="hidden" name="product_id"    id="ep-id">
            <input type="hidden" name="product_table" id="ep-table">
            <label>Product Name</label>
            <input type="text" name="product_name" id="ep-name" required>
            <div class="am-row">
                <div>
                    <label>Price (₱)</label>
                    <input type="number" name="product_price" id="ep-price" step="0.01" min="0">
                </div>
                <div>
                    <label>Size</label>
                    <select name="product_size" id="ep-size">
                        <option value="">— Size —</option>
                        <?php foreach ($allSizes as $sv => $sl): ?>
                        <option value="<?= $sv ?>"><?= $sl ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <label>Type</label>
            <select name="product_type" id="ep-type">
                <option value="">— Type —</option>
                <?php foreach ($allTypes as $tn => $td): ?>
                <option value="<?= htmlspecialchars($tn) ?>"><?= htmlspecialchars($td['display']) ?></option>
                <?php endforeach; ?>
            </select>
            <label>Replace Image (optional)</label>
            <input type="file" name="product_img" accept="image/*">
            <div class="am-actions">
                <button type="button" class="am-cancel" onclick="closeModal('modal-edit-product')">Cancel</button>
                <button type="submit" class="am-submit">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Product Confirm -->
<div class="aov" id="modal-delete-product">
    <div class="am">
        <h3>Delete Product</h3>
        <p style="color:#ccc;margin-bottom:8px;">Delete "<span id="dp-name" style="color:#fff;font-weight:700;"></span>"? This cannot be undone.</p>
        <form method="POST" action="Products.php">
            <input type="hidden" name="admin_action" value="delete_product">
            <input type="hidden" name="product_id"    id="dp-id">
            <input type="hidden" name="product_table" id="dp-table">
            <div class="am-actions">
                <button type="button" class="am-cancel" onclick="closeModal('modal-delete-product')">Cancel</button>
                <button type="submit" class="am-danger">Yes, Delete</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script src="assets/bootstrap/js/bootstrap.min.js"></script>
<script>
/* ── Admin modal helpers ── */
function openModal(id)  { const el=document.getElementById(id); if(el){el.classList.add('open');document.body.style.overflow='hidden';} }
function closeModal(id) { const el=document.getElementById(id); if(el){el.classList.remove('open');document.body.style.overflow='';} }
document.querySelectorAll('.aov').forEach(ov => ov.addEventListener('click', e => { if(e.target===ov) closeModal(ov.id); }));

function openEditProduct(id, table, name, price, size, type) {
    document.getElementById('ep-id').value    = id;
    document.getElementById('ep-table').value = table;
    document.getElementById('ep-name').value  = name;
    document.getElementById('ep-price').value = price;
    const sizeEl = document.getElementById('ep-size');
    if (sizeEl) { for (let o of sizeEl.options) o.selected = (o.value === size); }
    const typeEl = document.getElementById('ep-type');
    if (typeEl) { for (let o of typeEl.options) o.selected = (o.value === type); }
    openModal('modal-edit-product');
}
function openDeleteProduct(id, table, name) {
    document.getElementById('dp-id').value          = id;
    document.getElementById('dp-table').value       = table;
    document.getElementById('dp-name').textContent  = name;
    openModal('modal-delete-product');
}

/* ── Resize helper ── */
window.addEventListener('resize', () => {
    if (window.innerWidth > 768) {
        if (typeof headerEl !== 'undefined' && headerEl) headerEl.classList.remove('nav-open');
    }
});

/* ── Sidebar DOM placement is fixed — no need to move on resize ── */

/* ── Mobile filter drawer ── */
(function() {
    const filterBtn     = document.getElementById('mobileFilterBtn');
    const filterSidebar = document.querySelector('.filter-sidebar');
    const filterOverlay = document.getElementById('filterOverlay');
    const filterClose   = document.getElementById('filterDrawerClose');

    function openDrawer() {
        if (!filterSidebar) return;
        filterSidebar.classList.add('drawer-open');
        if (filterOverlay) filterOverlay.classList.add('open');
        document.body.style.overflow = 'hidden';
    }
    function closeDrawer() {
        if (!filterSidebar) return;
        filterSidebar.classList.remove('drawer-open');
        if (filterOverlay) filterOverlay.classList.remove('open');
        document.body.style.overflow = '';
    }

    if (filterBtn)   filterBtn.addEventListener('click', openDrawer);
    if (filterClose) filterClose.addEventListener('click', closeDrawer);
    if (filterOverlay) filterOverlay.addEventListener('click', closeDrawer);
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeDrawer(); });

    /* Swipe left to close */
    let startX = 0;
    if (filterSidebar) {
        filterSidebar.addEventListener('touchstart', e => { startX = e.touches[0].clientX; }, { passive: true });
        filterSidebar.addEventListener('touchmove', e => {
            const dx = e.touches[0].clientX - startX;
            if (dx < 0) filterSidebar.style.left = dx + 'px';
        }, { passive: true });
        filterSidebar.addEventListener('touchend', e => {
            const dx = e.changedTouches[0].clientX - startX;
            filterSidebar.style.left = '';
            if (dx < -60) closeDrawer();
        });
    }
})();

/* ── Chip "show more / less" toggle ── */
function toggleChips(wrapId, btnId) {
    const wrap = document.getElementById(wrapId);
    const btn  = document.getElementById(btnId);
    if (!wrap) return;
    const collapsed = wrap.classList.toggle('collapsed');
    if (btn) btn.textContent = collapsed ? '+ More' : '− Less';
}
/* Expand chips wrap if a non-"All" chip is already selected (on page load) */
(function(){
    document.querySelectorAll('.chip-filter-wrap').forEach(wrap => {
        const checked = wrap.querySelector('input[type="radio"]:checked');
        if (checked && checked.value !== '') {
            wrap.classList.remove('collapsed');
            const btn = document.getElementById(wrap.id.replace('ChipsWrap','ShowMore'));
            if (btn) btn.textContent = '− Less';
        }
    });
})();


/* ── Auto-submit filters ── */
(function() {
    const form        = document.getElementById('filterMainForm');
    const searchInput = document.getElementById('filterSearchInput');
    if (!form) return;
    const allCheck    = document.getElementById('allCheck');
    const tableChecks = form.querySelectorAll('input[name="table[]"]');
    if (allCheck) {
        allCheck.addEventListener('change', function() {
            if (this.checked) tableChecks.forEach(cb => cb.checked = false);
            submitFilter();
        });
        tableChecks.forEach(cb => cb.addEventListener('change', function() {
            allCheck.checked = !Array.from(tableChecks).some(c => c.checked);
            submitFilter();
        }));
    }
    const typeSelect = document.getElementById('filterTypeSelect');
    const sizeSelect = document.getElementById('filterSizeSelect');
    // Radio chips auto-submit via inline onchange="submitChipFilter()"
    // Keep these refs for any other code that reads them (no-op if null)
    function submitFilter() {
        if (searchInput) searchInput.value = '';
        let pi = form.querySelector('input[name="page"]');
        if (!pi) { pi = document.createElement('input'); pi.type='hidden'; pi.name='page'; form.appendChild(pi); }
        pi.value = '1';
        // Close the mobile drawer immediately so it feels responsive
        const sidebar  = document.querySelector('.filter-sidebar');
        const overlay  = document.getElementById('filterOverlay');
        if (sidebar) sidebar.classList.remove('drawer-open');
        if (overlay) overlay.classList.remove('open');
        document.body.style.overflow = '';
        form.submit();
    }
    window.submitChipFilter = submitFilter;
})();
</script>
</body>
<?php require 'footer.php'; ?>
</html>