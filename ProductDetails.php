<?php
include 'db_connect.php';
session_start();
require 'header.php';
$productID = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($productID <= 0) { echo "Invalid product."; exit; }
$tables = ['productsmedian','productssophisticated','productsluxurious'];
$product = null;
foreach ($tables as $table) {
    $stmt = $pdo->prepare("SELECT * FROM $table WHERE ProductID = ?");
    $stmt->execute([$productID]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($product) break;
}
if (!$product) { echo "Product not found."; exit; }
$sql = "
SELECT ProductID, ProductName, Price, Size
FROM productsmedian
UNION ALL
SELECT ProductID, ProductName, Price, Size
FROM productssophisticated
UNION ALL
SELECT ProductID, ProductName, Price, Size
FROM productsluxurious
ORDER BY ProductName ASC
";
$stmt = $pdo->query($sql);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
$length = isset($_GET['length']) ? (float)$_GET['length'] : 0;
$width  = isset($_GET['width'])  ? (float)$_GET['width']  : 0;
$arSession = bin2hex(random_bytes(12));
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light" data-bss-forced-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($product['ProductName']) ?></title>
    <link rel="icon" type="image/ico" href="Favicon.ico">
    <link rel="stylesheet" href="assets/bootstrap/css/bootstrap.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">
    <style>
      /* ── Base ── */
      html, body { overflow-x:hidden; background:#f9f9f9; font-family:'Inter',sans-serif; color:#151616; }

      /* ── Nav toggle (matching Products.php — orange mobile menu) ── */
      .nav-toggle { display:none; background:transparent; border:none; cursor:pointer; padding:10px; margin-left:8px; align-self:center; }
      .nav-toggle .bar { display:block; width:22px; height:2px; background:#ffffff; margin:4px 0; transition:transform .18s ease, opacity .18s ease; }
      .logo { display:flex; align-items:center; height:100%; overflow:hidden; padding-left:8px; }
      .logo img { display:block; max-height:72px; width:auto; height:auto; }
      @media (max-width:768px) {
        .nav-toggle { display:block; }
        .navbar > ul { display:none; }
        header.nav-open .navbar > ul {
          display:flex !important; position:absolute !important; top:0 !important; left:0 !important;
          width:220px !important; max-height:70vh; overflow-y:auto; flex-direction:column; gap:6px;
          margin:0; padding:8px 0; background:#ed8d1b !important;
          box-shadow:0 8px 28px rgba(0,0,0,0.18) !important; border-radius:10px !important;
          z-index:1400 !important; list-style:none; border:1px solid #000 !important; text-align:center;
        }
        header.nav-open .navbar > ul li { padding:10px 12px; border-radius:6px; }
        header.nav-open .navbar > ul li a { display:block; padding:6px 8px; }
        .logo img { max-height:56px; }
      }
      header.nav-open .nav-toggle .bar:nth-child(1) { transform:translateY(6px) rotate(45deg); }
      header.nav-open .nav-toggle .bar:nth-child(2) { opacity:0; }
      header.nav-open .nav-toggle .bar:nth-child(3) { transform:translateY(-6px) rotate(-45deg); }
      .navbar > ul > li { margin-right:0 !important; text-align:center !important; display:block; }

      /* ── Page Header (matching Products.php) ── */
      .page-header {
        background: #151616;
        padding: 36px 48px 32px;
        display: flex; align-items: flex-end;
        justify-content: space-between; gap: 16px; flex-wrap: wrap;
      }
      .ph-left { display:flex; flex-direction:column; gap:5px; }
      .ph-eyebrow {
        display: inline-flex; align-items: center; gap: 8px;
        font-size: .72rem; font-weight: 700; letter-spacing: 2px;
        text-transform: uppercase; color: #ed8d1b;
      }
      .ph-eyebrow::before {
        content:''; display:inline-block; width:28px; height:2px;
        background:#ed8d1b; border-radius:2px;
      }
      .page-header h1 {
        font-size: clamp(1.3rem, 3vw, 2.2rem); font-weight: 900;
        color: #fff; margin: 0; letter-spacing: -.4px; line-height: 1.15;
      }
      .page-header h1 span { color: #ed8d1b; }
      .ph-right { display:flex; align-items:center; }
      .back-link {
        display: inline-flex; align-items: center; gap: 6px;
        font-size: .82rem; font-weight: 700; color: #888;
        text-decoration: none; transition: color .18s;
        padding: 8px 0;
      }
      .back-link:hover { color: #ed8d1b; }
      @media (max-width:640px) { .page-header { padding: 24px 20px 24px; } }

      /* ── Page outer ── */
      .page-outer {
        max-width: 1100px;
        margin: 0 auto;
        padding: 32px 24px 72px;
        display: flex;
        flex-direction: column;
        gap: 28px;
      }
      @media (max-width:600px) { .page-outer { padding: 20px 16px 56px; gap:20px; } }

      /* ── White card ── */
      .content-card {
        background: #fff;
        border-radius: 20px;
        border: 1px solid #ececec;
        box-shadow: 0 4px 24px rgba(0,0,0,.07);
        overflow: hidden;
      }

      /* ── Card header stripe ── */
      .card-header-stripe {
        background: #151616;
        padding: 18px 28px;
        display: flex; align-items: center; gap: 10px;
      }
      .card-header-stripe .stripe-eyebrow {
        font-size: .68rem; font-weight: 800; letter-spacing: 2px;
        text-transform: uppercase; color: #ed8d1b;
      }
      .card-header-stripe .stripe-title {
        font-size: 1rem; font-weight: 800; color: #fff; margin: 0;
      }

      /* ══════════════════════════════════
         PRODUCT DETAILS LAYOUT
      ══════════════════════════════════ */
      .product-layout {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0;
      }
      @media (max-width:700px) { .product-layout { grid-template-columns: 1fr; } }

      /* Image panel */
      .product-media {
        background: #f5f5f5;
        display: flex; align-items: center; justify-content: center;
        min-height: 340px; padding: 28px;
        border-right: 1px solid #f0f0f0;
      }
      @media (max-width:700px) { .product-media { border-right:none; border-bottom:1px solid #f0f0f0; min-height:260px; } }
      .product-media img {
        max-width: 100%; max-height: 380px;
        object-fit: contain; border-radius: 10px;
        cursor: pointer; transition: transform .22s;
        display: block;
      }
      .product-media img:hover { transform: scale(1.03); }

      /* Info panel */
      .product-info {
        padding: 32px 32px 28px;
        display: flex; flex-direction: column; gap: 14px;
      }
      @media (max-width:700px) { .product-info { padding: 24px 20px 24px; } }

      .product-price {
        font-size: 1.8rem; font-weight: 900;
        color: #ed8d1b; letter-spacing: -.5px; margin: 0;
        line-height: 1;
      }
      .product-price.no-price {
        font-size: 1rem; color: #aaa; font-weight: 500;
      }

      .info-tags {
        display: flex; flex-wrap: wrap; gap: 8px;
      }
      .info-tag {
        display: inline-flex; align-items: center; gap: 6px;
        background: #f5f5f5; border: 1px solid #eee;
        border-radius: 20px; padding: 6px 14px;
        font-size: .8rem; font-weight: 700; color: #555;
      }
      .info-tag strong { color: #151616; }

      .info-divider { height: 1px; background: #f0f0f0; }

      /* AR button */
      .btn-ar {
        display: inline-flex; align-items: center; gap: 8px;
        background: #151616; color: #fff;
        border: none; border-radius: 10px;
        padding: 11px 20px; font-size: .88rem; font-weight: 700;
        font-family: 'Inter', sans-serif;
        cursor: pointer; text-decoration: none;
        transition: background .18s;
        align-self: flex-start;
      }
      .btn-ar:hover { background: #2a2a2a; color:#fff; }

      /* Quantity row */
      .qty-row {
        display: flex; align-items: center; gap: 10px;
      }
      .qty-row label {
        font-size: .82rem; font-weight: 700; color: #555; white-space: nowrap;
        text-transform: uppercase; letter-spacing: .5px;
      }
      .qty-input {
        width: 80px; padding: 9px 12px;
        border: 1.5px solid #e0e0e0; border-radius: 9px;
        font-size: .9rem; font-family: 'Inter', sans-serif;
        color: #151616; outline: none;
        transition: border-color .18s;
      }
      .qty-input:focus { border-color: #ed8d1b; }

      /* Action buttons */
      .btn-row { display: flex; gap: 10px; flex-wrap: wrap; }
      .btn-add-cart {
        flex: 1; min-width: 130px;
        padding: 12px 18px; border: none; border-radius: 10px;
        background: #ed8d1b; color: #151616;
        font-weight: 900; font-size: .9rem; font-family: 'Inter', sans-serif;
        cursor: pointer; text-decoration: none; text-align: center;
        display: inline-flex; align-items: center; justify-content: center;
        transition: background .18s, box-shadow .18s;
      }
      .btn-add-cart:hover { background: #c97415; box-shadow: 0 4px 16px rgba(237,141,27,.3); color:#151616; }
      .btn-buy-now {
        flex: 1; min-width: 120px;
        padding: 12px 18px; border: 1.5px solid #151616; border-radius: 10px;
        background: #fff; color: #151616;
        font-weight: 800; font-size: .9rem; font-family: 'Inter', sans-serif;
        cursor: pointer; text-decoration: none; text-align: center;
        display: inline-flex; align-items: center; justify-content: center;
        transition: background .18s, color .18s;
      }
      .btn-buy-now:hover { background: #151616; color: #fff; }
      .login-notice {
        font-size: .82rem; color: #999;
      }
      .login-notice a { color: #ed8d1b; font-weight: 700; text-decoration: none; }
      .login-notice a:hover { text-decoration: underline; }

      /* ══════════════════════════════════
         ESTIMATOR SECTION
      ══════════════════════════════════ */
      .estimator-body {
        padding: 32px 36px 36px;
      }
      @media (max-width:600px) { .estimator-body { padding: 24px 20px 28px; } }

      /* Section eyebrow inside card */
      .section-label {
        font-size: .7rem; font-weight: 800; text-transform: uppercase;
        letter-spacing: 1.5px; color: #ed8d1b;
        display: flex; align-items: center; gap: 10px;
        margin-bottom: 18px;
      }
      .section-label::after { content:''; flex:1; height:1px; background:#f0f0f0; }

      /* Input grid */
      .input-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
        margin-bottom: 14px;
      }
      @media (max-width:500px) { .input-grid { grid-template-columns: 1fr; } }
      @media (max-width: 480px) { body{ padding-top: 65px !important;}}
      
      .input-group-custom { display: flex; flex-direction: column; gap: 6px; }
      .input-group-custom.span-full { grid-column: 1 / -1; }

      /* Labels */
      .control-label {
        font-size: .78rem; font-weight: 700; color: #555;
        text-transform: uppercase; letter-spacing: .7px;
      }

      /* Inputs */
      .form-input, .form-select-styled {
        width: 100%; padding: 11px 14px;
        border: 1.5px solid #e0e0e0; border-radius: 10px;
        font-size: .9rem; font-family: 'Inter', sans-serif;
        color: #151616; background: #fff;
        transition: border-color .18s, box-shadow .18s; outline: none;
        appearance: none; -webkit-appearance: none;
      }
      .form-input:focus, .form-select-styled:focus {
        border-color: #ed8d1b;
        box-shadow: 0 0 0 3px rgba(237,141,27,.1);
      }
      .form-input[readonly] {
        background: #f8f8f8; color: #888;
        cursor: not-allowed; border-color: #eee;
      }
      .form-input::placeholder { color: #c0c0c0; }
      .select-wrap { position: relative; }
      .select-wrap::after {
        content:''; position:absolute; right:14px; top:50%;
        transform:translateY(-50%);
        border:5px solid transparent; border-top-color:#aaa;
        pointer-events:none;
      }
      .select-wrap .form-select-styled { padding-right:36px; cursor:pointer; }

      /* OR divider */
      .or-divider {
        display: flex; align-items: center; gap: 12px;
        margin: 6px 0 18px;
      }
      .or-divider::before, .or-divider::after { content:''; flex:1; height:1px; background:#eee; }
      .or-divider span {
        font-size: .73rem; font-weight: 800; color: #bbb;
        text-transform: uppercase; letter-spacing: 1.2px; white-space:nowrap;
      }

      /* Options row */
      .options-row {
        display: flex; flex-wrap: wrap; gap: 20px; align-items: center;
        background: #fafafa; border: 1.5px solid #f0f0f0;
        border-radius: 12px; padding: 14px 18px; margin-bottom: 22px;
      }
      .options-row label {
        display: flex; align-items: center; gap: 8px;
        font-size: .85rem; font-weight: 600; color: #444;
        cursor: pointer; white-space: nowrap;
      }
      .grout-input {
        width: 80px; padding: 7px 10px;
        border: 1.5px solid #e0e0e0; border-radius: 8px;
        font-size: .85rem; font-family: inherit; color: #151616;
        outline: none; transition: border-color .18s; background:#fff;
      }
      .grout-input:focus { border-color: #ed8d1b; }
      .options-row input[type="checkbox"] {
        accent-color: #ed8d1b; width:16px; height:16px;
        cursor:pointer; flex-shrink:0;
      }

      /* Price display */
      .price-display-row {
        display: flex; align-items: center; gap: 10px; margin-bottom: 22px;
      }
      .price-display-input {
        width: 160px; padding: 9px 12px;
        border: 1.5px solid #e0e0e0; border-radius: 10px;
        font-size: .9rem; font-family: inherit; color: #888;
        background: #f8f8f8; cursor: not-allowed;
      }

      /* Calculate button */
      .calc-btn {
        width: 100%; padding: 14px;
        background: #ed8d1b; color: #151616; border: none; border-radius: 12px;
        font-size: 1rem; font-weight: 900; font-family: 'Inter', sans-serif;
        cursor: pointer; transition: background .18s, transform .12s, box-shadow .18s;
        letter-spacing: .2px;
        display: flex; align-items: center; justify-content: center; gap: 8px;
      }
      .calc-btn:hover { background: #c97415; box-shadow: 0 6px 20px rgba(237,141,27,.3); }
      .calc-btn:active { transform: scale(.98); }

      /* Result box */
      .result {
        margin-top: 22px; border-radius: 14px;
        padding: 22px 24px; font-size: .92rem; line-height: 1.75; display: none;
      }
      .result.ok {
        display: block; background: #151616; border: 1.5px solid #2a2a2a; color: #ccc;
      }
      .result.ok strong { color: #fff; }
      .result.ok hr { border:none; border-top:1px solid #2a2a2a; margin:14px 0; }
      .result.ok p { margin: 4px 0; }
      .result.error {
        display: block; background: #fff5f5; border: 1.5px solid #fecdcd;
        color: #c0392b; font-weight: 600; font-size: .88rem;
      }

      /* Result action buttons */
      .result-actions {
        display: none; margin-top: 14px; gap: 10px; flex-wrap: wrap;
      }
      .btn-save-img {
        display: flex; align-items: center; gap: 7px;
        background: #ed8d1b; border: none; color: #151616;
        font-weight: 800; font-size: .88rem; padding: 9px 18px;
        border-radius: 9px; cursor: pointer; transition: background .15s; font-family: inherit;
      }
      .btn-save-img:hover { background: #c97a16; }
      .btn-print {
        display: flex; align-items: center; gap: 7px;
        background: #fff; border: 1.5px solid #e0e0e0; color: #444;
        font-weight: 700; font-size: .88rem; padding: 9px 18px;
        border-radius: 9px; cursor: pointer; transition: background .15s, border-color .15s; font-family: inherit;
      }
      .btn-print:hover { background: #f5f5f5; border-color: #ccc; }

      /* ── Cart toast ── */
      .cart-overlay {
        display: none; position: fixed; inset: 0;
        background: rgba(0,0,0,.55); z-index: 8000;
        align-items: center; justify-content: center;
        padding: 20px; backdrop-filter: blur(3px);
        transition: opacity .25s;
        opacity: 0;
      }
      .cart-overlay.visible { display:flex; opacity:1; }
      .cart-modal {
        background: #1e1e1e; border: 1px solid #2e2e2e; border-radius: 18px;
        padding: 28px 28px 22px; max-width: 380px; width: 100%;
        position: relative; box-shadow: 0 20px 60px rgba(0,0,0,.5);
      }
      .cart-modal-close {
        position:absolute; top:14px; right:14px;
        background:rgba(255,255,255,0.08); border:none; color:#fff;
        width:30px; height:30px; border-radius:50%; font-size:18px;
        cursor:pointer; line-height:30px; display:flex; align-items:center; justify-content:center;
      }
      .cart-modal-inner { display:flex; align-items:center; gap:14px; margin-bottom:20px; }
      .cart-modal-icon {
        width:52px; height:52px; border-radius:50%; flex-shrink:0;
        display:flex; align-items:center; justify-content:center;
      }
      .cart-overlay.success .cart-modal-icon { background:#27ae60; }
      .cart-overlay.error   .cart-modal-icon { background:#c0392b; }
      .cart-modal-title { font-size:1rem; font-weight:800; color:#fff; margin:0; }
      .cart-modal-actions { display:flex; gap:10px; }
      .cart-btn {
        flex:1; padding:10px; border-radius:9px; text-align:center;
        font-weight:700; font-size:.88rem; text-decoration:none; font-family:inherit;
        display:inline-flex; align-items:center; justify-content:center;
      }
      .cart-btn-primary { background:#ed8d1b; color:#151616; border:none; }
      .cart-btn-primary:hover { background:#c97415; color:#151616; }
      .cart-btn:not(.cart-btn-primary) {
        background:rgba(255,255,255,.08); border:1px solid rgba(255,255,255,.15); color:#ccc;
      }
      .cart-btn:not(.cart-btn-primary):hover { background:rgba(255,255,255,.15); color:#fff; }
    </style>
</head>
<body>

<!-- ══════════════════════════ PAGE HEADER ══════════════════════════ -->
<div class="page-header">
  <div class="ph-left">
    <span class="ph-eyebrow">Products</span>
    <h1><?= htmlspecialchars($product['ProductName']) ?></h1>
  </div>
  <div class="ph-right">
    <a href="Products.php" class="back-link">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
        <polyline points="15 18 9 12 15 6"/>
      </svg>
      Back to Products
    </a>
  </div>
</div>

<!-- ══════════════════════════ PAGE BODY ══════════════════════════ -->
<div class="page-outer">

  <!-- ─── Product Details Card ─── -->
  <div class="content-card">
    <div class="card-header-stripe">
      <span class="stripe-eyebrow">Product Details</span>
    </div>
    <div class="product-layout">

      <!-- Image -->
      <div class="product-media">
        <img id="productImg"
             src="<?= $product['Image'] ? 'data:image/jpeg;base64,' . base64_encode($product['Image']) : 'noImage.png' ?>"
             alt="<?= htmlspecialchars($product['ProductName']) ?>"
             title="Click to view full image">
      </div>

      <!-- Info -->
      <div class="product-info">
        <p class="product-price <?= $product['Price'] === null ? 'no-price' : '' ?>">
          <?= $product['Price'] !== null ? '₱' . number_format($product['Price'], 2) : 'Price on request' ?>
        </p>

        <div class="info-tags">
          <?php if (!empty($product['Size'])): ?>
          <span class="info-tag">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="3" width="18" height="18" rx="2"/></svg>
            Size: <strong><?= htmlspecialchars($product['Size']) ?></strong>
          </span>
          <?php endif; ?>
          <?php if (!empty($product['Type'])): ?>
          <span class="info-tag">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
            Type: <strong><?= htmlspecialchars($product['Type']) ?></strong>
          </span>
          <?php endif; ?>
        </div>

        <div class="info-divider"></div>

        <!-- AR Button -->
        <a href="Measurement.php?id=<?= $productID ?>" class="btn-ar" id="useArBtn" role="button">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25"/>
          </svg>
          Measure Your Space with AR
        </a>

        <!-- Purchase actions -->
        <div>
          <?php if (isset($_SESSION['CustomerID'])): ?>
            <form method="post" action="AddToCart.php" id="addToCartForm">
              <input type="hidden" name="productID" value="<?= $productID ?>">
              <div class="qty-row" style="margin-bottom:12px;">
                <label for="quantity">Quantity:</label>
                <input type="number" name="quantity" id="quantity" value="1" min="1" class="qty-input">
              </div>
              <div class="btn-row">
                <button type="submit" class="btn-add-cart">Add to Cart</button>
                <button type="button" class="btn-buy-now" id="buyNowBtn">Buy Now</button>
              </div>
            </form>
            <form method="post" action="BuyNow.php" id="buyNowForm" style="display:none;">
              <input type="hidden" name="productID" value="<?= $productID ?>">
              <input type="hidden" name="quantity" id="buyNowQuantity" value="1">
            </form>
          <?php else: ?>
            <!-- Guest: Add to Cart works; Buy Now requires login -->
            <form method="post" action="GuestAddToCart.php" id="addToCartForm">
              <input type="hidden" name="productID" value="<?= $productID ?>">
              <div class="qty-row" style="margin-bottom:12px;">
                <label for="quantity">Quantity:</label>
                <input type="number" name="quantity" id="quantity" value="1" min="1" class="qty-input">
              </div>
              <div class="btn-row">
                <button type="submit" class="btn-add-cart">Add to Cart</button>
                <a href="Login&Signup.php" class="btn-buy-now">Buy Now</a>
              </div>
            </form>
            <p class="login-notice" style="margin-top:10px;">
              <a href="Login&Signup.php">Log in</a> to checkout. Items added to cart will be saved when you sign in.
            </p>
          <?php endif; ?>
        </div>

      </div><!-- /.product-info -->
    </div><!-- /.product-layout -->
  </div><!-- /.content-card (product) -->

  <!-- ─── Cost Estimator Card ─── -->
  <div class="content-card">
    <div class="card-header-stripe">
      <span class="stripe-eyebrow">Tools</span>
      <span class="stripe-title">Cost Estimator</span>
    </div>
    <div class="estimator-body">
      <form action="#" id="tileForm">

        <!-- Room Dimensions -->
        <div class="section-label">Room Dimensions</div>
        <div class="input-grid">
          <div class="input-group-custom">
            <label class="control-label">Length (m)</label>
            <input type="number" name="num1" id="num1" class="form-input"
                   placeholder="e.g. 4.5" step="any" min="0">
          </div>
          <div class="input-group-custom">
            <label class="control-label">Width (m)</label>
            <input type="number" name="num2" id="num2" class="form-input"
                   placeholder="e.g. 3.2" step="any" min="0">
          </div>
        </div>

        <div class="or-divider"><span>or enter area directly</span></div>

        <div class="input-group-custom" style="margin-bottom:28px;">
          <label class="control-label">Area (m²)</label>
          <input type="number" name="num3" id="num3" class="form-input"
                 placeholder="e.g. 14.4" step="any" min="0">
        </div>

        <!-- Tile Details -->
        <div class="section-label" style="margin-top:4px;">Tile Details</div>

        <div class="input-grid" style="margin-bottom:20px;">
          <div class="input-group-custom span-full">
            <label class="control-label">Tile Size (cm) — auto-filled from product</label>
            <input type="text" id="num4" class="form-input"
                   value="<?= htmlspecialchars($product['Size'] ?? '') ?>"
                   readonly>
          </div>
        </div>

        <!-- Price -->
        <div class="section-label" style="margin-top:4px;">Pricing</div>
        <div class="price-display-row">
          <div>
            <label class="control-label" style="display:block;margin-bottom:6px;">Price per piece (₱)</label>
            <input type="text" id="priceDisplay" class="price-display-input"
                   value="<?= $product['Price'] !== null ? number_format($product['Price'], 2, '.', '') : '' ?>"
                   readonly placeholder="Price on request">
          </div>
          <?php if ($product['Price'] === null): ?>
          <span style="color:#aaa;font-size:.85rem;margin-top:20px;">Price on request</span>
          <?php endif; ?>
        </div>
        <input type="hidden" id="currentProductPrice"
               value="<?= $product['Price'] !== null ? number_format($product['Price'], 2, '.', '') : '' ?>">

        <!-- Options -->
        <div class="section-label" style="margin-top:4px;">Options</div>
        <div class="options-row">
          <label>
            Grout gap (mm):
            <input type="number" id="groutGap" class="grout-input" step="any" min="0" placeholder="e.g. 3">
          </label>
          <label>
            <input type="checkbox" id="checkbox" checked>
            Add 10% Allowance
          </label>
        </div>

        <!-- Hidden -->
        <input type="checkbox" name="checkbox2" id="checkbox2" style="display:none;">

        <!-- Calculate -->
        <button type="button" id="calcBtn" class="calc-btn">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <rect x="4" y="2" width="16" height="20" rx="2"/>
            <line x1="8" y1="7" x2="16" y2="7"/><line x1="8" y1="11" x2="16" y2="11"/>
            <line x1="8" y1="15" x2="12" y2="15"/>
          </svg>
          Calculate Estimate
        </button>

        <!-- Result -->
        <div id="calcResult" class="result"></div>
        <div id="resultActions" class="result-actions">
          <button type="button" id="saveImgBtn" class="btn-save-img">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
              <polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>
            </svg>
            Save as Image
          </button>
          <button type="button" id="printBtn" class="btn-print">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
              <polyline points="6 9 6 2 18 2 18 9"/>
              <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
              <rect x="6" y="14" width="12" height="8"/>
            </svg>
            Print Results
          </button>
        </div>

      </form>
    </div><!-- /.estimator-body -->
  </div><!-- /.content-card (estimator) -->

</div><!-- /.page-outer -->

<!-- ══════════════════════════ IMAGE LIGHTBOX ══════════════════════════ -->
<div id="imgLightbox" aria-hidden="true" role="dialog" aria-modal="true" aria-label="Product image viewer"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.88);z-index:3500;
            align-items:center;justify-content:center;flex-direction:column;overflow:hidden;">
    <button id="lbClose" aria-label="Close image viewer"
            style="position:fixed;right:18px;top:18px;background:rgba(255,255,255,0.14);border:2px solid rgba(255,255,255,0.22);
                   font-size:20px;cursor:pointer;color:#fff;width:38px;height:38px;border-radius:50%;
                   z-index:10;line-height:1;transition:background .15s;"
            onmouseover="this.style.background='rgba(237,141,27,0.8)'"
            onmouseout="this.style.background='rgba(255,255,255,0.14)'">&times;</button>
    <div style="position:fixed;bottom:24px;left:50%;transform:translateX(-50%);
                display:flex;align-items:center;gap:10px;z-index:10;
                background:rgba(0,0,0,0.55);border:1px solid rgba(255,255,255,0.15);
                border-radius:99px;padding:7px 16px;backdrop-filter:blur(6px);">
        <button id="lbZoomOut" aria-label="Zoom out"
                style="background:rgba(255,255,255,0.12);border:none;color:#fff;font-size:20px;
                       width:34px;height:34px;border-radius:50%;cursor:pointer;line-height:1;transition:background .15s;"
                onmouseover="this.style.background='rgba(237,141,27,0.7)'"
                onmouseout="this.style.background='rgba(255,255,255,0.12)'">&#8722;</button>
        <span id="lbZoomLabel" style="color:rgba(255,255,255,0.85);font-size:13px;font-weight:600;min-width:44px;text-align:center;">100%</span>
        <button id="lbZoomIn" aria-label="Zoom in"
                style="background:rgba(255,255,255,0.12);border:none;color:#fff;font-size:20px;
                       width:34px;height:34px;border-radius:50%;cursor:pointer;line-height:1;transition:background .15s;"
                onmouseover="this.style.background='rgba(237,141,27,0.7)'"
                onmouseout="this.style.background='rgba(255,255,255,0.12)'">&#43;</button>
        <button id="lbZoomReset" aria-label="Reset zoom"
                style="background:rgba(255,255,255,0.12);border:none;color:rgba(255,255,255,0.75);
                       font-size:11px;font-weight:600;padding:0 12px;height:34px;border-radius:99px;
                       cursor:pointer;transition:background .15s;white-space:nowrap;"
                onmouseover="this.style.background='rgba(237,141,27,0.7)';this.style.color='#fff'"
                onmouseout="this.style.background='rgba(255,255,255,0.12)';this.style.color='rgba(255,255,255,0.75)'">Reset</button>
    </div>
    <div id="lbCanvas" style="width:100%;height:100%;overflow:hidden;display:flex;align-items:center;justify-content:center;cursor:grab;">
        <img id="lbImage" src="" alt="Product full view"
             style="max-width:90vw;max-height:85vh;object-fit:contain;border-radius:12px;
                    transform-origin:center center;user-select:none;-webkit-user-drag:none;">
    </div>
</div>

<!-- ══════════════════════════ QR MODAL ══════════════════════════ -->
<div id="qrModal" aria-hidden="true" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.65);z-index:2000;align-items:center;justify-content:center;padding:16px;">
    <div id="qrModalInner" role="dialog" aria-modal="true"
         style="position:relative;width:92vw;max-width:420px;background:#1e1e1e;border-radius:16px;
                border:1px solid #2e2e2e;padding:28px 24px 22px;box-shadow:0 10px 40px rgba(0,0,0,.5);text-align:center;">
        <button id="qrClose" aria-label="Close"
                style="position:absolute;right:14px;top:12px;background:rgba(255,255,255,0.08);border:none;
                       font-size:20px;cursor:pointer;color:#fff;width:32px;height:32px;border-radius:6px;line-height:32px;">&times;</button>
        <h3 style="margin:8px 0 12px;font-size:1.05rem;color:#ed8d1b;font-weight:800;">Open AR Measurement</h3>

        <!-- Desktop / Android: QR code -->
        <div id="qrPanel">
            <p id="qrNotice" style="margin:0 0 12px;color:rgba(255,255,255,0.7);font-size:0.88rem;line-height:1.55;">Scan the QR code to open the AR measurement page on your phone.</p>
            <img id="qrImage" alt="QR code" src="" style="width:76%;max-width:280px;height:auto;margin:8px auto 14px;display:block;border-radius:8px;border:1px solid rgba(255,255,255,0.1);background:#fff;">
            <small id="platformHint" style="display:block;margin-top:10px;color:rgba(255,255,255,0.4);font-size:0.8rem;"></small>
        </div>

        <!-- iOS Safari: copy-link panel -->
        <div id="iosPanel" style="display:none;">
            <p style="margin:0 0 14px;color:rgba(255,255,255,0.7);font-size:0.88rem;line-height:1.6;">
                Copy the link below and paste it into the <strong style="color:#ed8d1b;">WebXR Viewer</strong> address bar.
            </p>
            <div style="display:flex;align-items:center;gap:8px;background:#111;border:1px solid #333;border-radius:10px;padding:10px 12px;margin-bottom:14px;">
                <span id="iosLink" style="flex:1;font-size:0.78rem;color:#ccc;word-break:break-all;text-align:left;user-select:all;"></span>
                <button id="copyLinkBtn"
                        style="flex-shrink:0;background:#ed8d1b;border:none;color:#151616;font-weight:800;font-size:0.78rem;
                               padding:7px 13px;border-radius:7px;cursor:pointer;font-family:inherit;white-space:nowrap;">
                    Copy
                </button>
            </div>
            <p style="margin:0;color:rgba(255,255,255,0.35);font-size:0.78rem;line-height:1.5;">
                Open <strong style="color:rgba(255,255,255,0.6);">WebXR Viewer</strong> → tap the address bar → paste the link.
            </p>
        </div>

    </div>
</div>

<!-- ══════════════════════════ FOOTER ══════════════════════════ -->
<?php require 'footer.php'; ?>

<!-- ══════════════════════════ SCRIPTS ══════════════════════════ -->
<script src="assets/bootstrap/js/bootstrap.min.js"></script>
<script>
const AR_SESSION    = <?= json_encode($arSession) ?>;
const AR_PRODUCT_ID = <?= json_encode((int)$productID) ?>;
const AR_POLL_URL   = <?= json_encode('/ar-poll.php?session=' . $arSession) ?>;
const AR_QR_LINK    = <?= json_encode('Measurement.php?session=' . $arSession . '&product=' . $productID) ?>;
const PRODUCT_NAME  = <?= json_encode(htmlspecialchars($product['ProductName'])) ?>;
</script>

<!-- QR / AR helper -->
<script>
(function(){
    const useArBtn     = document.getElementById('useArBtn');
    if (!useArBtn) return;
    const modal        = document.getElementById('qrModal');
    const qrImg        = document.getElementById('qrImage');
    const qrClose      = document.getElementById('qrClose');
    const platformHint = document.getElementById('platformHint');
    const qrPanel      = document.getElementById('qrPanel');
    const iosPanel     = document.getElementById('iosPanel');
    const iosLink      = document.getElementById('iosLink');
    const copyLinkBtn  = document.getElementById('copyLinkBtn');

    function absoluteUrl(href){ try { return new URL(href, location.href).href; } catch(e){ return href; } }
    function buildMeasurementUrl(baseHref){
        const base = absoluteUrl(baseHref || '/Measurement.php');
        const sep  = base.includes('?') ? '&' : '?';
        return base + sep + 'session=' + encodeURIComponent(AR_SESSION) + '&product=' + encodeURIComponent(AR_PRODUCT_ID);
    }

    const ua        = navigator.userAgent || navigator.vendor || window.opera;
    const isAndroid = /android/i.test(ua);
    const isIOS     = /iPhone|iPad|iPod/i.test(navigator.userAgent);
    const isSafari  = /^((?!chrome|android).)*safari/i.test(navigator.userAgent);

    // Copy button
    if (copyLinkBtn) {
        copyLinkBtn.addEventListener('click', function () {
            const url = iosLink ? iosLink.textContent : '';
            if (!url) return;
            navigator.clipboard.writeText(url).then(function () {
                copyLinkBtn.textContent = 'Copied!';
                setTimeout(function () { copyLinkBtn.textContent = 'Copy'; }, 2000);
            }).catch(function () {
                // Fallback for older iOS
                var ta = document.createElement('textarea');
                ta.value = url; ta.style.position = 'fixed'; ta.style.opacity = '0';
                document.body.appendChild(ta); ta.focus(); ta.select();
                try { document.execCommand('copy'); copyLinkBtn.textContent = 'Copied!'; setTimeout(function(){ copyLinkBtn.textContent = 'Copy'; }, 2000); } catch(e){}
                document.body.removeChild(ta);
            });
        });
    }

    function showModal(baseHref){
        const measurementUrl = buildMeasurementUrl(baseHref);

        // iOS Safari → show copy-link panel
        if (isIOS && isSafari) {
            qrPanel.style.display  = 'none';
            iosPanel.style.display = 'block';
            iosLink.textContent    = measurementUrl;
            copyLinkBtn.textContent = 'Copy';
            modal.style.display = 'flex'; modal.setAttribute('aria-hidden', 'false');
            return;
        }

        // Android: open in new tab + show QR as backup
        // Desktop: show QR to scan with phone
        qrPanel.style.display  = 'block';
        iosPanel.style.display = 'none';
        qrImg.src = 'https://api.qrserver.com/v1/create-qr-code/?size=360x360&data=' + encodeURIComponent(measurementUrl);
        if (isAndroid)  platformHint.textContent = 'Detected: Android — opening AR page in a new tab…';
        else            platformHint.textContent = 'Desktop — scan the QR with your phone. Measurements will auto-fill when done.';
        modal.style.display = 'flex'; modal.setAttribute('aria-hidden', 'false');
        modal.dataset.currentMeasurementUrl = measurementUrl;
        if (isAndroid) { try { window.open(measurementUrl, '_blank'); } catch(e){} }
        startArPolling();
    }
    function hideModal(){ modal.style.display = 'none'; modal.setAttribute('aria-hidden', 'true'); delete modal.dataset.currentMeasurementUrl; stopArPolling(); }
    useArBtn.addEventListener('click', function(e){ e.preventDefault(); const href = this.getAttribute('href') || this.dataset.href || '/Measurement.php'; showModal(href); });
    qrClose.addEventListener('click', hideModal);
    modal.addEventListener('click', function(e){ if (e.target === modal) hideModal(); });
    document.addEventListener('keydown', function(e){ if (e.key === 'Escape' && modal.style.display === 'flex') hideModal(); });
})();
</script>

<!-- AR Polling -->
<script>
let arPollInterval = null;
function startArPolling(){
    if (arPollInterval) return;
    arPollInterval = setInterval(async () => {
        try {
            const res  = await fetch(AR_POLL_URL, { cache: 'no-store' });
            const data = await res.json();
            if (data && data.status === 'ok' && (data.length || data.width)) {
                const elLen = document.getElementById('num1');
                const elWid = document.getElementById('num2');
                if (elLen) elLen.value = data.length;
                if (elWid) elWid.value = data.width;
                elLen && elLen.dispatchEvent(new Event('input'));
                if (window.runEstimatorCalculation) window.runEstimatorCalculation();
                if (data.productId) {
                    const p = new URLSearchParams(window.location.search);
                    p.set('length', data.length); p.set('width', data.width);
                    window.history.replaceState({}, '', location.pathname + '?' + p.toString());
                }
                stopArPolling();
                const modal = document.getElementById('qrModal');
                if (modal) { modal.style.display = 'none'; modal.setAttribute('aria-hidden', 'true'); }
            }
        } catch(e) {}
    }, 2000);
}
function stopArPolling(){ if (arPollInterval) { clearInterval(arPollInterval); arPollInterval = null; } }
</script>

<!-- AR postMessage listener -->
<script>
window.addEventListener('message', (event) => {
    try {
        if (event.origin !== window.location.origin) return;
        const data = event.data;
        if (!data || data.type !== 'ar-measurement') return;
        const length = parseFloat(data.length) || 0;
        const width  = parseFloat(data.width)  || 0;
        const elLen = document.getElementById('num1');
        const elWid = document.getElementById('num2');
        if (elLen) elLen.value = length;
        if (elWid) elWid.value = width;
        elLen && elLen.dispatchEvent(new Event('input'));
        if (window.runEstimatorCalculation) window.runEstimatorCalculation();
        const modal = document.getElementById('qrModal');
        if (modal) { modal.style.display = 'none'; modal.setAttribute('aria-hidden', 'true'); }
        stopArPolling();
        try { window.focus(); } catch(e){}
    } catch(e){ console.error('AR message error', e); }
});
</script>

<!-- Flash toast -->
<?php
if (!empty($_SESSION['flash_success']) || !empty($_SESSION['flash_error'])):
    $toastMsg  = $_SESSION['flash_success'] ?? $_SESSION['flash_error'];
    $toastType = !empty($_SESSION['flash_success']) ? 'success' : 'error';
    unset($_SESSION['flash_success'], $_SESSION['flash_error']);
?>
<div id="cart-overlay" class="cart-overlay <?= $toastType ?>" role="dialog" aria-modal="true" aria-labelledby="cart-toast-title">
    <div class="cart-modal" id="cart-modal" role="document">
        <button class="cart-modal-close" aria-label="Close notification">&times;</button>
        <div class="cart-modal-inner">
            <div class="cart-modal-icon" aria-hidden="true">
                <?php if ($toastType === 'success'): ?>
                    <svg width="34" height="34" viewBox="0 0 24 24" fill="none"><path d="M20 6L9 17l-5-5" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <?php else: ?>
                    <svg width="34" height="34" viewBox="0 0 24 24" fill="none"><path d="M18 6L6 18M6 6l12 12" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <?php endif; ?>
            </div>
            <div class="cart-modal-body">
                <h3 id="cart-toast-title" class="cart-modal-title">
                    <?= $toastType === 'success' ? 'Added to Cart — Thank you!' : 'Action Failed' ?>
                </h3>
            </div>
        </div>
        <div class="cart-modal-actions">
            <a class="cart-btn cart-btn-primary" href="Cart.php">View Cart</a>
            <a class="cart-btn" href="Products.php">Continue Shopping</a>
        </div>
    </div>
</div>
<script>
(function(){
    const overlay = document.getElementById('cart-overlay');
    const modal   = document.getElementById('cart-modal');
    if (!overlay || !modal) return;
    requestAnimationFrame(()=> overlay.classList.add('visible'));
    const autoHide = setTimeout(()=>closeModal(), 3800);
    function closeModal(){
        overlay.classList.remove('visible');
        setTimeout(()=>{ if(overlay&&overlay.parentNode) overlay.parentNode.removeChild(overlay); },300);
        clearTimeout(autoHide);
    }
    overlay.addEventListener('click', function(e){ if(e.target===overlay) closeModal(); });
    const closeBtn = overlay.querySelector('.cart-modal-close');
    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    document.addEventListener('keydown', function(e){ if(e.key==='Escape') closeModal(); });
})();
</script>
<?php endif; ?>

<!-- Estimator logic -->
<script>
(function () {
    var lengthInput         = document.getElementById('num1');
    var widthInput          = document.getElementById('num2');
    var areaInput           = document.getElementById('num3');
    var tileSelect          = document.getElementById('num4');
    var allowanceCb         = document.getElementById('checkbox');
    var btn                 = document.getElementById('calcBtn');
    var resultBox           = document.getElementById('calcResult');
    var groutGapInput       = document.getElementById('groutGap');
    var currentProductPrice = document.getElementById('currentProductPrice');
    if (!lengthInput || !widthInput || !areaInput || !tileSelect || !btn || !resultBox) return;

    function parseNum(str) {
        if (str == null) return NaN;
        return parseFloat(String(str).trim().replace(/,/g, ''));
    }
    function fmtCurr(n)     { return '₱' + Number(n).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2}); }
    function fmtInt(n)      { return Number(n).toLocaleString(); }
    function fmtFloat(n, d) { return Number(n).toLocaleString(undefined, {minimumFractionDigits:d, maximumFractionDigits:d}); }
    function parseTileSize(str) {
        var clean = String(str).toLowerCase().replace(/\s+/g, '').replace(/[×*]/g, 'x');
        var parts = clean.split('x');
        if (parts.length !== 2) return null;
        var w = parseFloat(parts[0]), h = parseFloat(parts[1]);
        if (!isFinite(w) || !isFinite(h) || w <= 0 || h <= 0) return null;
        return { wCm: w, hCm: h };
    }
    function showError(msg) {
        resultBox.className = 'result error';
        resultBox.textContent = '⚠ ' + msg;
        var ra = document.getElementById('resultActions');
        if (ra) ra.style.display = 'none';
    }
    function autoFillArea() {
        var l = parseNum(lengthInput.value), w = parseNum(widthInput.value);
        areaInput.value = (isFinite(l) && isFinite(w) && l > 0 && w > 0) ? (l * w).toFixed(2) : '';
    }
    lengthInput.addEventListener('input', autoFillArea);
    widthInput.addEventListener('input',  autoFillArea);

    /* Pre-fill from URL params (AR redirect) */
    (function () {
        var params    = new URLSearchParams(window.location.search);
        var lengthVal = params.get('length');
        var widthVal  = params.get('width');
        if (lengthVal && !isNaN(lengthVal)) lengthInput.value = parseFloat(lengthVal);
        if (widthVal  && !isNaN(widthVal))  widthInput.value  = parseFloat(widthVal);
        autoFillArea();
        if (lengthVal && widthVal && !isNaN(lengthVal) && !isNaN(widthVal)) {
            setTimeout(function(){ runCalculation(); }, 150);
        }
    })();

    function runCalculation() {
        var length = parseNum(lengthInput.value), width = parseNum(widthInput.value);
        var areaFromInput = parseNum(areaInput.value);
        var area;
        if (isFinite(length) && isFinite(width) && length > 0 && width > 0) {
            area = length * width; areaInput.value = area.toFixed(2);
        } else if (isFinite(areaFromInput) && areaFromInput > 0) {
            area = areaFromInput;
        } else {
            return showError('Please enter a valid Length & Width (> 0) or a valid Area.');
        }
        var parsed = parseTileSize(tileSelect.value);
        if (!tileSelect.value || !parsed) return showError('Please select a tile size.');
        var groutMm    = parseNum(groutGapInput ? groutGapInput.value : '') || 0;
        var groutM     = groutMm / 1000;
        var wCm = parsed.wCm, hCm = parsed.hCm;
        var effW = (wCm / 100) + groutM, effH = (hCm / 100) + groutM;
        var tileAreaM2 = effW * effH;
        if (!(tileAreaM2 > 0)) return showError('Invalid tile size or grout gap.');
        var wastePct      = (allowanceCb && allowanceCb.checked) ? 0.10 : 0.00;
        var baseTiles     = Math.ceil(area / tileAreaM2);
        var tilesNeeded   = Math.ceil(area / tileAreaM2 * (1 + wastePct));
        var allowanceTiles = Math.ceil(baseTiles * wastePct);
        var productPriceVal = currentProductPrice ? parseNum(currentProductPrice.value) : NaN;
        if (!isFinite(productPriceVal) || productPriceVal <= 0) {
            return showError('Price not available for this product.');
        }
        var totalCost = tilesNeeded * productPriceVal;
        var breakdown = 'Using product price per piece ' + fmtCurr(productPriceVal) + '.';
        resultBox.className = 'result ok';
        document.getElementById('resultActions').style.display = 'flex';
        resultBox.innerHTML =
            '<p><strong>Product:</strong> ' + PRODUCT_NAME + '</p>' +
            '<p><strong>Computed area:</strong> ' + fmtFloat(area, 2) + ' m²</p>' +
            '<p><strong>Tile (with grout):</strong> ' + wCm + ' × ' + hCm + ' cm (+ ' + groutMm + ' mm grout) — effective ' + fmtFloat(tileAreaM2, 4) + ' m² per tile</p>' +
            (allowanceCb && allowanceCb.checked
                ? '<p><strong>Tiles needed:</strong> ' + fmtInt(baseTiles) + '</p>' +
                  '<p><strong>Allowance:</strong> ' + fmtInt(allowanceTiles) + ' tiles</p>' +
                  '<p style="font-size:1.05em;margin-top:6px;"><strong>Total (with allowance):</strong> <span style="color:#ed8d1b;font-size:1.15em;">' + fmtInt(tilesNeeded) + '</span></p>'
                : '<p style="font-size:1.05em;margin-top:6px;"><strong>Tiles needed:</strong> <span style="color:#ed8d1b;font-size:1.15em;">' + fmtInt(tilesNeeded) + '</span></p>'
            ) +
            '<hr>' +
            '<p><strong>' + breakdown + '</strong></p>' +
            '<p style="font-size:1.15em;"><strong>Total estimated cost: <span style="color:#ed8d1b;">' + fmtCurr(totalCost) + '</span></strong></p>' +
            '<p style="font-size:0.82em;color:#666;margin-top:6px;">Note: final cost may vary with discounts, taxes, or shipping.</p>';
    }
    btn.addEventListener('click', runCalculation);
    window.runEstimatorCalculation = runCalculation;
})();
</script>

<!-- Buy Now qty sync -->
<script>
(function(){
    var qtyInput   = document.getElementById('quantity');
    var buyNowQty  = document.getElementById('buyNowQuantity');
    var buyNowForm = document.getElementById('buyNowForm');
    var buyNowBtn  = document.getElementById('buyNowBtn');
    if (qtyInput && buyNowQty) {
        buyNowQty.value = qtyInput.value || 1;
        qtyInput.addEventListener('input', function(){ buyNowQty.value = Math.max(1, parseInt(qtyInput.value, 10) || 1); });
    }
    if (buyNowBtn && buyNowForm) {
        buyNowBtn.addEventListener('click', function(){
            if (qtyInput && buyNowQty) buyNowQty.value = Math.max(1, parseInt(qtyInput.value, 10) || 1);
            buyNowForm.submit();
        });
    }
})();
</script>

<!-- Lightbox -->
<script>
(function () {
    const productImg  = document.getElementById('productImg');
    const lightbox    = document.getElementById('imgLightbox');
    const lbCanvas    = document.getElementById('lbCanvas');
    const lbImage     = document.getElementById('lbImage');
    const lbClose     = document.getElementById('lbClose');
    const lbZoomIn    = document.getElementById('lbZoomIn');
    const lbZoomOut   = document.getElementById('lbZoomOut');
    const lbZoomReset = document.getElementById('lbZoomReset');
    const lbZoomLabel = document.getElementById('lbZoomLabel');
    if (!productImg || !lightbox) return;
    const MIN_SCALE = 1, MAX_SCALE = 5, STEP = 0.35;
    let scale = 1, panX = 0, panY = 0, isPanning = false, startX = 0, startY = 0;
    function applyTransform() {
        lbImage.style.transform = 'translate(' + panX + 'px,' + panY + 'px) scale(' + scale + ')';
        lbZoomLabel.textContent = Math.round(scale * 100) + '%';
        lbCanvas.style.cursor = scale > 1 ? 'grab' : 'default';
    }
    function clampPan() {
        const rect = lbImage.getBoundingClientRect();
        const cRect = lbCanvas.getBoundingClientRect();
        const overW = Math.max(0, rect.width  - cRect.width)  / 2;
        const overH = Math.max(0, rect.height - cRect.height) / 2;
        panX = Math.min(overW, Math.max(-overW, panX));
        panY = Math.min(overH, Math.max(-overH, panY));
    }
    function setScale(newScale, cx, cy) {
        const cRect = lbCanvas.getBoundingClientRect();
        cx = (cx !== undefined) ? cx - cRect.left - cRect.width  / 2 : 0;
        cy = (cy !== undefined) ? cy - cRect.top  - cRect.height / 2 : 0;
        const ratio = newScale / scale;
        panX = cx + (panX - cx) * ratio; panY = cy + (panY - cy) * ratio; scale = newScale;
        clampPan(); applyTransform();
    }
    function resetZoom() { scale = 1; panX = 0; panY = 0; applyTransform(); }
    lbZoomIn.addEventListener('click',    function () { setScale(Math.min(MAX_SCALE, scale + STEP)); });
    lbZoomOut.addEventListener('click',   function () { setScale(Math.max(MIN_SCALE, scale - STEP)); });
    lbZoomReset.addEventListener('click', resetZoom);
    lbCanvas.addEventListener('wheel', function (e) {
        if (lightbox.style.display !== 'flex') return;
        e.preventDefault();
        setScale(Math.min(MAX_SCALE, Math.max(MIN_SCALE, scale + (e.deltaY < 0 ? STEP : -STEP))), e.clientX, e.clientY);
    }, { passive: false });
    lbCanvas.addEventListener('mousedown', function (e) {
        if (scale <= 1) return; isPanning = true;
        startX = e.clientX - panX; startY = e.clientY - panY;
        lbCanvas.style.cursor = 'grabbing'; e.preventDefault();
    });
    window.addEventListener('mousemove', function (e) {
        if (!isPanning) return; panX = e.clientX - startX; panY = e.clientY - startY; clampPan(); applyTransform();
    });
    window.addEventListener('mouseup', function () {
        if (!isPanning) return; isPanning = false; lbCanvas.style.cursor = scale > 1 ? 'grab' : 'default';
    });
    let lastDist = 0, touchMidX = 0, touchMidY = 0, touchPanStartX = 0, touchPanStartY = 0;
    function getTouchDist(t) { return Math.hypot(t[0].clientX - t[1].clientX, t[0].clientY - t[1].clientY); }
    lbCanvas.addEventListener('touchstart', function (e) {
        if (e.touches.length === 2) { lastDist = getTouchDist(e.touches); touchMidX = (e.touches[0].clientX + e.touches[1].clientX)/2; touchMidY = (e.touches[0].clientY + e.touches[1].clientY)/2; }
        else if (e.touches.length === 1 && scale > 1) { touchPanStartX = e.touches[0].clientX - panX; touchPanStartY = e.touches[0].clientY - panY; }
        e.preventDefault();
    }, { passive: false });
    lbCanvas.addEventListener('touchmove', function (e) {
        if (e.touches.length === 2) { const dist = getTouchDist(e.touches); setScale(Math.min(MAX_SCALE, Math.max(MIN_SCALE, scale * (dist/lastDist))), touchMidX, touchMidY); lastDist = dist; }
        else if (e.touches.length === 1 && scale > 1) { panX = e.touches[0].clientX - touchPanStartX; panY = e.touches[0].clientY - touchPanStartY; clampPan(); applyTransform(); }
        e.preventDefault();
    }, { passive: false });
    let lastTap = 0;
    lbCanvas.addEventListener('touchend', function (e) {
        const now = Date.now();
        if (now - lastTap < 300) { scale > 1 ? resetZoom() : setScale(2.5, e.changedTouches[0].clientX, e.changedTouches[0].clientY); }
        lastTap = now;
    });
    productImg.addEventListener('click', function () { resetZoom(); lbImage.src = this.src; lightbox.style.display = 'flex'; lightbox.setAttribute('aria-hidden', 'false'); });
    function closeLightbox() { lightbox.style.display = 'none'; lightbox.setAttribute('aria-hidden', 'true'); lbImage.src = ''; resetZoom(); }
    lbClose.addEventListener('click', closeLightbox);
    lightbox.addEventListener('click', function (e) { if (e.target === lightbox || e.target === lbCanvas) closeLightbox(); });
    document.addEventListener('keydown', function (e) {
        if (lightbox.style.display !== 'flex') return;
        if (e.key === 'Escape') closeLightbox();
        if (e.key === '+' || e.key === '=') setScale(Math.min(MAX_SCALE, scale + STEP));
        if (e.key === '-') setScale(Math.max(MIN_SCALE, scale - STEP));
        if (e.key === '0') resetZoom();
    });
})();
</script>

<!-- Save/Print -->
<!-- Save / Print -->
<script>
(function () {
    'use strict';
    var saveBtn   = document.getElementById('saveImgBtn');
    var printBtn  = document.getElementById('printBtn');
    var resultBox = document.getElementById('calcResult');

    var FONT     = 'Inter,-apple-system,Arial,sans-serif';
    var LOGO_SRC = new URL('Logo.png', window.location.href).href;
    var PHONE    = '(+63) 998 355 2852';
    var EMAIL    = 'zabcotiledepot@gmail.com';

    function collectSegments(node, bold, color) {
        var segs = [];
        node.childNodes.forEach(function (n) {
            if (n.nodeType === 3) {
                if (n.textContent) segs.push({ text: n.textContent, bold: bold, color: color });
            } else if (n.nodeType === 1) {
                var b = bold || n.tagName === 'STRONG' || n.tagName === 'B';
                var c = (n.style && n.style.color) ? n.style.color : color;
                collectSegments(n, b, c).forEach(function (s) { segs.push(s); });
            }
        });
        return segs;
    }

    function parseResultItems() {
        var items = [];
        resultBox.childNodes.forEach(function (node) {
            if (!node.tagName) return;
            if (node.tagName === 'HR') {
                items.push({ type: 'hr' });
            } else if (node.tagName === 'P') {
                var fsRaw = parseFloat(node.style.fontSize) || 1.0;
                items.push({ type: 'p', fsPx: Math.round(fsRaw * 14), segs: collectSegments(node, false, null) });
            }
        });
        return items;
    }

    function drawAndDownload(logoImg) {
        var SCALE = 2, W = 600, PAD = 28;
        var LOGO_H = 46;
        var LOGO_W = logoImg ? Math.round(logoImg.naturalWidth * (LOGO_H / logoImg.naturalHeight)) : 0;
        var TEXT_X = logoImg ? PAD + LOGO_W + 14 : PAD;

        var items = parseResultItems();
        var contentH = 0;
        items.forEach(function (item) {
            contentH += item.type === 'hr' ? 24 : item.fsPx * 1.9 + 4;
        });
        var HEADER_H = 108, FOOTER_H = 88;
        var H = HEADER_H + PAD + contentH + PAD + FOOTER_H;

        var canvas = document.createElement('canvas');
        canvas.width  = W * SCALE;
        canvas.height = H * SCALE;
        var ctx = canvas.getContext('2d');
        ctx.scale(SCALE, SCALE);

        ctx.fillStyle = '#151616';
        ctx.fillRect(0, 0, W, H);

        ctx.fillStyle = '#ed8d1b';
        ctx.fillRect(0, 0, W, 5);

        var logoY = 20;
        if (logoImg) {
            ctx.save();
            var rx = PAD, ry = logoY, rw = LOGO_W, rh = LOGO_H, rad = 6;
            ctx.beginPath();
            ctx.moveTo(rx + rad, ry);
            ctx.lineTo(rx + rw - rad, ry); ctx.quadraticCurveTo(rx + rw, ry, rx + rw, ry + rad);
            ctx.lineTo(rx + rw, ry + rh - rad); ctx.quadraticCurveTo(rx + rw, ry + rh, rx + rw - rad, ry + rh);
            ctx.lineTo(rx + rad, ry + rh); ctx.quadraticCurveTo(rx, ry + rh, rx, ry + rh - rad);
            ctx.lineTo(rx, ry + rad); ctx.quadraticCurveTo(rx, ry, rx + rad, ry);
            ctx.closePath(); ctx.clip();
            ctx.drawImage(logoImg, rx, ry, rw, rh);
            ctx.restore();
        }

        var ty = logoY + 14;
        ctx.font = 'bold 10px ' + FONT;
        ctx.fillStyle = '#ed8d1b';
        ctx.fillText('ZABCO TILE DEPOT', TEXT_X, ty);

        ty += 25;
        ctx.font = 'bold 21px ' + FONT;
        ctx.fillStyle = '#ffffff';
        ctx.fillText('Cost Estimate', TEXT_X, ty);

        ty += 20;
        ctx.font = '11px ' + FONT;
        ctx.fillStyle = '#555555';
        var now = new Date();
        ctx.fillText(
            'Generated ' + now.toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' }),
            TEXT_X, ty
        );

        var divY = Math.max(logoY + LOGO_H, ty) + 12;
        ctx.fillStyle = '#2a2a2a';
        ctx.fillRect(PAD, divY, W - PAD * 2, 1);
        var y = divY + PAD;

        items.forEach(function (item) {
            if (item.type === 'hr') {
                ctx.fillStyle = '#2a2a2a';
                ctx.fillRect(PAD, y + 10, W - PAD * 2, 1);
                y += 24;
            } else {
                var cx = PAD;
                item.segs.forEach(function (seg) {
                    ctx.font = (seg.bold ? 'bold ' : '') + item.fsPx + 'px ' + FONT;
                    ctx.fillStyle = seg.color || '#cccccc';
                    ctx.fillText(seg.text, cx, y);
                    cx += ctx.measureText(seg.text).width;
                });
                y += item.fsPx * 1.9 + 4;
            }
        });

        var fY = H - FOOTER_H;
        ctx.fillStyle = '#2a2a2a';
        ctx.fillRect(PAD, fY, W - PAD * 2, 1);

        fY += 20;
        ctx.font = '13px ' + FONT;
        ctx.fillStyle = '#ed8d1b';
        ctx.fillText('\u260e', PAD, fY);
        ctx.font = '11px ' + FONT;
        ctx.fillStyle = '#aaaaaa';
        ctx.fillText(PHONE, PAD + 18, fY);

        var phoneEnd = PAD + 18 + ctx.measureText(PHONE).width + 22;
        ctx.font = '13px ' + FONT;
        ctx.fillStyle = '#ed8d1b';
        ctx.fillText('\u2709', phoneEnd, fY);
        ctx.font = '11px ' + FONT;
        ctx.fillStyle = '#aaaaaa';
        ctx.fillText(EMAIL, phoneEnd + 18, fY);

        fY += 22;
        ctx.font = '11px ' + FONT;
        ctx.fillStyle = '#aaaaaa';
        ctx.fillText('\u25cf  Ricvic Building, 354 Tirona Hwy, Bacoor, 4102 Cavite, Philippines', PAD, fY);

        fY += 18;
        ctx.font = '10px ' + FONT;
        ctx.fillStyle = '#3a3a3a';
        ctx.fillText('Estimates are approximate and subject to change.', PAD, fY);

        var link = document.createElement('a');
        link.download = 'zabco-cost-estimate.png';
        link.href = canvas.toDataURL('image/png');
        link.click();
    }

    var SAVE_ICON =
        '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor"' +
        ' stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">' +
        '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>' +
        '<polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>' +
        '</svg> Save as Image';

    function triggerSave() {
        saveBtn.disabled = true;
        saveBtn.textContent = 'Generating\u2026';
        var logo = new Image();
        logo.crossOrigin = 'anonymous';
        function finish(img) {
            try { drawAndDownload(img); } catch (e) { console.error('Save image error:', e); }
            saveBtn.disabled = false;
            saveBtn.innerHTML = SAVE_ICON;
        }
        logo.onload  = function () { finish(logo); };
        logo.onerror = function () { finish(null); };
        logo.src = LOGO_SRC;
    }

    function printEstimate() {
        var now     = new Date();
        var dateFmt = now.toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' });
        var timeFmt = now.toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit' });

        var win = window.open('', '_blank', 'width=780,height=760');
        if (!win) return;
        win.document.write(
            '<!DOCTYPE html><html><head>' +
            '<meta charset="UTF-8"><title>Cost Estimate \u2013 Zabco Tile Depot</title>' +
            '<link rel="preconnect" href="https://fonts.googleapis.com">' +
            '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">' +
            '<style>' +
            '*{box-sizing:border-box;margin:0;padding:0;}' +
            'body{font-family:Inter,-apple-system,Arial,sans-serif;background:#fff;color:#151616;}' +
            '.accent{height:5px;background:#ed8d1b;-webkit-print-color-adjust:exact;print-color-adjust:exact;}' +
            '.hdr{background:#151616;padding:20px 36px 18px;display:flex;justify-content:space-between;' +
            'align-items:center;-webkit-print-color-adjust:exact;print-color-adjust:exact;}' +
            '.hdr-left{display:flex;align-items:center;gap:14px;}' +
            '.hdr-logo{height:50px;width:auto;object-fit:contain;border-radius:6px;display:block;}' +
            '.eyebrow{font-size:10px;font-weight:800;color:#ed8d1b;text-transform:uppercase;letter-spacing:2px;' +
            'margin-bottom:5px;-webkit-print-color-adjust:exact;print-color-adjust:exact;}' +
            '.title{font-size:22px;font-weight:900;color:#fff;letter-spacing:-.4px;line-height:1;}' +
            '.hdr-date{font-size:12px;color:#888;text-align:right;line-height:1.8;}' +
            '.body{padding:24px 36px 14px;}' +
            'p{margin:6px 0;font-size:14px;line-height:1.8;color:#333;}' +
            'strong{font-weight:700;color:#151616;}' +
            'hr{border:none;border-top:1px solid #e8e8e8;margin:16px 0;}' +
            '.ftr{padding:12px 36px 26px;}' +
            '.ftr-row1{display:flex;justify-content:space-between;align-items:center;' +
            'padding-top:12px;border-top:1px solid #ebebeb;margin-bottom:10px;}' +
            '.ftr-brand{font-size:10px;font-weight:800;color:#bbb;text-transform:uppercase;letter-spacing:1.5px;}' +
            '.ftr-note{font-size:11px;color:#bbb;}' +
            '.ftr-contact{display:flex;gap:22px;flex-wrap:wrap;}' +
            '.ftr-contact span{font-size:12px;color:#555;display:inline-flex;align-items:center;gap:7px;}' +
            '.ftr-addr{width:100%;margin-top:6px;}' +
            '.ico{color:#ed8d1b;font-size:14px;line-height:1;-webkit-print-color-adjust:exact;print-color-adjust:exact;}' +
            '@media print{' +
            '.accent,.hdr,.eyebrow,.ico{-webkit-print-color-adjust:exact;print-color-adjust:exact;}' +
            '}' +
            '</style></head><body>' +
            '<div class="accent"></div>' +
            '<div class="hdr">' +
              '<div class="hdr-left">' +
                '<img class="hdr-logo" src="' + LOGO_SRC + '" alt="Zabco Tile Depot" onerror="this.style.display=\'none\'">' +
                '<div><div class="eyebrow">Zabco Tile Depot</div><div class="title">Cost Estimate</div></div>' +
              '</div>' +
              '<div class="hdr-date">' + dateFmt + '<br>' + timeFmt + '</div>' +
            '</div>' +
            '<div class="body">' + resultBox.innerHTML + '</div>' +
            '<div class="ftr">' +
              '<div class="ftr-row1">' +
                '<span class="ftr-brand">Zabco Tile Depot</span>' +
                '<span class="ftr-note">Estimates are approximate. Final cost may vary.</span>' +
              '</div>' +
              '<div class="ftr-contact">' +
                '<span><span class="ico">\u260e</span>' + PHONE + '</span>' +
                '<span><span class="ico">\u2709</span>' + EMAIL + '</span>' +
                '<span class="ftr-addr"><span class="ico">&#9679;</span>Ricvic Building, 354 Tirona Hwy, Bacoor, 4102 Cavite, Philippines</span>' +
              '</div>' +
            '</div>' +
            '</body></html>'
        );
        win.document.close();
        win.focus();
        setTimeout(function () { win.print(); }, 600);
    }

    if (saveBtn) saveBtn.addEventListener('click', triggerSave);
    if (printBtn) printBtn.addEventListener('click', printEstimate);
})();
</script>

</body>
</html>