<?php
include 'db_connect.php';
session_start();
require 'header.php';

$sql = "
SELECT ProductID, ProductName, Price, Size, (Image IS NOT NULL AND Image != '') AS HasImage FROM productsmedian
UNION ALL
SELECT ProductID, ProductName, Price, Size, (Image IS NOT NULL AND Image != '') AS HasImage FROM productssophisticated
UNION ALL
SELECT ProductID, ProductName, Price, Size, (Image IS NOT NULL AND Image != '') AS HasImage FROM productsluxurious
ORDER BY ProductName ASC
";
$stmt     = $pdo->query($sql);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light" data-bss-forced-theme="light">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <title>Cost Estimator</title>
    <link rel="icon" type="image/png" href="Favicon.ico">
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

      /* ── Page header (matching Products.php dark header) ── */
      .page-header {
        background: #151616;
        padding: 44px 48px 36px;
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 16px;
        flex-wrap: wrap;
      }
      .ph-left { display:flex; flex-direction:column; gap:6px; }
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
        font-size: clamp(1.6rem, 3.5vw, 2.6rem);
        font-weight: 900; color: #fff; margin: 0;
        letter-spacing: -.5px; line-height: 1.1;
      }
      .page-header h1 span { color: #ed8d1b; }
      .ph-desc {
        font-size: .88rem; color: #888; margin: 0;
        max-width: 480px; line-height: 1.5;
      }
      @media (max-width:640px) { .page-header { padding: 28px 20px 28px; } }

      /* ── Main layout ── */
      .estimator-outer {
        max-width: 820px;
        margin: 0 auto;
        padding: 36px 24px 72px;
      }
      @media (max-width:600px) { .estimator-outer { padding: 24px 16px 56px; } }

      /* ── White card ── */
      .estimator-card {
        background: #fff;
        border-radius: 20px;
        border: 1px solid #ececec;
        box-shadow: 0 4px 24px rgba(0,0,0,.07);
        padding: 36px 36px 40px;
      }
      @media (max-width:600px) { .estimator-card { padding: 24px 20px 28px; } }

      /* ── Section eyebrow inside card ── */
      .section-label {
        font-size: .7rem; font-weight: 800; text-transform: uppercase;
        letter-spacing: 1.5px; color: #ed8d1b;
        display: flex; align-items: center; gap: 10px;
        margin-bottom: 18px; margin-top: 4px;
      }
      .section-label::after { content:''; flex:1; height:1px; background:#f0f0f0; }

      /* ── Input grid ── */
      .input-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
        margin-bottom: 14px;
      }
      @media (max-width:500px) { .input-grid { grid-template-columns: 1fr; } }
      
      .input-grid.full-row > * { grid-column: 1 / -1; }
      
      @media (max-width:480px) {
        body { padding-top: 65px !important; }
      }
      

      .input-group-custom {
        display: flex;
        flex-direction: column;
        gap: 6px;
      }
      .input-group-custom.span-full { grid-column: 1 / -1; }

      /* ── Labels ── */
      .control-label {
        font-size: .78rem; font-weight: 700; color: #555;
        text-transform: uppercase; letter-spacing: .7px;
      }

      /* ── Inputs ── */
      .form-input, .form-select {
        width: 100%;
        padding: 11px 14px;
        border: 1.5px solid #e0e0e0;
        border-radius: 10px;
        font-size: .9rem;
        font-family: 'Inter', sans-serif;
        color: #151616;
        background: #fff;
        transition: border-color .18s, box-shadow .18s;
        outline: none;
        appearance: none;
        -webkit-appearance: none;
      }
      .form-input:focus, .form-select:focus {
        border-color: #ed8d1b;
        box-shadow: 0 0 0 3px rgba(237,141,27,.1);
      }
      .form-input[readonly] {
        background: #f8f8f8; color: #999;
        cursor: not-allowed; border-color: #eee;
      }
      .form-input::placeholder { color: #c0c0c0; }
      .select-wrap { position:relative; }
      .select-wrap::after {
        content:''; position:absolute; right:14px; top:50%;
        transform:translateY(-50%);
        border:5px solid transparent;
        border-top-color:#aaa;
        pointer-events:none;
      }
      .select-wrap .form-select { padding-right:36px; cursor:pointer; }

      /* ── OR divider ── */
      .or-divider {
        display: flex; align-items: center; gap: 12px;
        margin: 6px 0 18px;
      }
      .or-divider::before, .or-divider::after {
        content:''; flex:1; height:1px; background:#eee;
      }
      .or-divider span {
        font-size: .73rem; font-weight: 800; color: #bbb;
        text-transform: uppercase; letter-spacing: 1.2px; white-space:nowrap;
      }

      /* ── Options row (grout + allowance) ── */
      .options-row {
        display: flex; flex-wrap: wrap; gap: 20px; align-items: center;
        background: #fafafa; border: 1.5px solid #f0f0f0;
        border-radius: 12px; padding: 14px 18px;
        margin-bottom: 22px;
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

      /* ── Calculate button ── */
      .calc-btn {
        width: 100%;
        padding: 14px;
        background: #ed8d1b;
        color: #151616;
        border: none;
        border-radius: 12px;
        font-size: 1rem;
        font-weight: 900;
        font-family: 'Inter', sans-serif;
        cursor: pointer;
        transition: background .18s, transform .12s, box-shadow .18s;
        letter-spacing: .2px;
        display: flex; align-items: center; justify-content: center; gap: 8px;
      }
      .calc-btn:hover {
        background: #c97415;
        box-shadow: 0 6px 20px rgba(237,141,27,.3);
      }
      .calc-btn:active { transform: scale(.98); }

      /* ── Result box ── */
      .result {
        margin-top: 22px;
        border-radius: 14px;
        padding: 22px 24px;
        font-size: .92rem;
        line-height: 1.75;
        display: none;
      }
      .result.ok {
        display: block;
        background: #151616;
        border: 1.5px solid #2a2a2a;
        color: #ccc;
      }
      .result.ok strong { color: #fff; }
      .result.ok hr { border:none; border-top:1px solid #2a2a2a; margin:14px 0; }
      .result.ok p { margin: 4px 0; }
      .result.error {
        display: block;
        background: #fff5f5;
        border: 1.5px solid #fecdcd;
        color: #c0392b;
        font-weight: 600;
        font-size: .88rem;
      }

      /* ── Result action buttons ── */
      .result-actions {
        display: none;
        margin-top: 14px;
        gap: 10px;
        flex-wrap: wrap;
      }
      .btn-save-img {
        display: flex; align-items: center; gap: 7px;
        background: #ed8d1b; border: none; color: #151616;
        font-weight: 800; font-size: .88rem;
        padding: 9px 18px; border-radius: 9px; cursor: pointer;
        transition: background .15s; font-family: inherit;
      }
      .btn-save-img:hover { background: #c97a16; }
      .btn-print {
        display: flex; align-items: center; gap: 7px;
        background: #fff; border: 1.5px solid #e0e0e0; color: #444;
        font-weight: 700; font-size: .88rem;
        padding: 9px 18px; border-radius: 9px; cursor: pointer;
        transition: background .15s, border-color .15s; font-family: inherit;
      }
      .btn-print:hover { background: #f5f5f5; border-color: #ccc; }
    </style>
</head>

<body>

<!-- ══════════════════════════ PAGE HEADER ══════════════════════════ -->
<div class="page-header">
  <div class="ph-left">
    <span class="ph-eyebrow">Tools</span>
    <h1>Cost <span>Estimator</span></h1>
    <p class="ph-desc">Enter your room dimensions and select a product to estimate how many tiles you need and the total cost.</p>
  </div>
</div>

<!-- ══════════════════════════ ESTIMATOR CARD ══════════════════════════ -->
<div class="estimator-outer">
  <div class="estimator-card">

    <form action="#" id="tileForm">

      <!-- ── Room Dimensions ── -->
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

      <!-- ── Product & Tile ── -->
      <div class="section-label" style="margin-top:8px;">Product &amp; Tile</div>

      <div class="input-grid" style="margin-bottom:20px;">
        <div class="input-group-custom span-full">
          <label class="control-label">Select Product</label>

          <!-- Hidden select — keeps all existing JS (data-price, data-size, data-name, change event) intact -->
          <select id="productSelect" style="display:none;" aria-hidden="true">
            <option value="">— Choose a product —</option>
            <?php foreach ($products as $prod):
                $safeName = htmlspecialchars($prod['ProductName']);
                $price    = is_null($prod['Price']) ? '' : number_format($prod['Price'], 2, '.', '');
                $size     = htmlspecialchars($prod['Size'] ?? '');
                $hasImage = !empty($prod['HasImage']);
                $image    = $hasImage ? 'tile_image.php?id=' . (int)$prod['ProductID'] : '';
            ?>
            <option value="<?= (int)$prod['ProductID'] ?>"
                    data-price="<?= $price ?>"
                    data-size="<?= $size ?>"
                    data-name="<?= $safeName ?>"
                    data-image="<?= $image ?>">
                <?= $safeName ?><?= $price !== '' ? " — ₱{$price}" : "" ?>
            </option>
            <?php endforeach; ?>
          </select>

          <!-- Visible picker trigger -->
          <button type="button" id="productPickerBtn" class="picker-trigger">
            <span class="picker-preview" id="pickerPreview">
              <span class="picker-thumb-placeholder">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                  <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/>
                  <rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>
                </svg>
              </span>
              <img id="pickerThumb" src="" alt="" style="display:none;">
            </span>
            <span id="pickerLabel" class="picker-label-text">Browse &amp; choose a product…</span>
            <svg class="picker-arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
              <polyline points="6 9 12 15 18 9"/>
            </svg>
          </button>
        </div>
        <div class="input-group-custom span-full">
          <label class="control-label">Tile Size (cm) — auto-filled from product</label>
          <input type="text" id="num4" class="form-input"
                 placeholder="Select a product above to auto-fill" readonly>
        </div>
      </div>

      <!-- ── Options ── -->
      <div class="section-label" style="margin-top:8px;">Options</div>

      <div class="options-row">
        <label>
          Grout gap (mm):
          <input type="number" id="groutGap" class="grout-input"
                 step="any" min="0" placeholder="e.g. 3">
        </label>
        <label>
          <input type="checkbox" id="checkbox" checked>
          Add 10% Allowance
        </label>
      </div>

      <!-- ── Calculate ── -->
      <button type="button" id="calcBtn" class="calc-btn">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <rect x="4" y="2" width="16" height="20" rx="2"/><line x1="8" y1="7" x2="16" y2="7"/>
          <line x1="8" y1="11" x2="16" y2="11"/><line x1="8" y1="15" x2="12" y2="15"/>
        </svg>
        Calculate Estimate
      </button>

      <!-- ── Result ── -->
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
  </div><!-- /.estimator-card -->
</div><!-- /.estimator-outer -->

<!-- ══════════ PRODUCT PICKER MODAL ══════════ -->
<div id="productModal" class="pm-overlay" role="dialog" aria-modal="true" aria-label="Select a Product">
  <div class="pm-sheet">

    <!-- Header -->
    <div class="pm-head">
      <div class="pm-head-left">
        <span class="pm-eyebrow">Catalogue</span>
        <h2 class="pm-title">Choose a Tile Product</h2>
      </div>
      <button type="button" id="pmClose" class="pm-close" aria-label="Close">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
        </svg>
      </button>
    </div>

    <!-- Search + count -->
    <div class="pm-toolbar">
      <div class="pm-search-wrap">
        <svg class="pm-search-ico" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
        </svg>
        <input type="text" id="pmSearch" class="pm-search" placeholder="Search by name or size…" autocomplete="off">
        <button type="button" id="pmSearchClear" class="pm-search-clear" aria-label="Clear search" style="display:none;">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
          </svg>
        </button>
      </div>
      <span id="pmCount" class="pm-count"></span>
    </div>

    <!-- Product grid -->
    <div class="pm-body" id="pmBody">
      <div class="pm-grid" id="pmGrid">
        <?php foreach ($products as $prod):
            $safeName = htmlspecialchars($prod['ProductName']);
            $price    = is_null($prod['Price']) ? '' : number_format($prod['Price'], 2, '.', '');
            $size     = htmlspecialchars($prod['Size'] ?? '');
            $hasImage = !empty($prod['HasImage']);
            $image    = $hasImage ? 'tile_image.php?id=' . (int)$prod['ProductID'] : '';
            $id       = (int)$prod['ProductID'];
        ?>
        <button type="button" class="pm-card"
                data-id="<?= $id ?>"
                data-price="<?= $price ?>"
                data-size="<?= $size ?>"
                data-name="<?= $safeName ?>"
                data-image="<?= $image ?>">

          <!-- Tile image -->
          <div class="pm-img-wrap">
            <?php if ($image !== ''): ?>
              <img src="<?= $image ?>" alt="<?= $safeName ?>" class="pm-img" loading="lazy"
                   onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
              <span class="pm-img-fallback" style="display:none;">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#d0d0d0" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round">
                  <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/>
                  <rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>
                </svg>
              </span>
            <?php else: ?>
              <span class="pm-img-fallback">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#d0d0d0" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round">
                  <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/>
                  <rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>
                </svg>
              </span>
            <?php endif; ?>
            <span class="pm-selected-badge">
              <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="20 6 9 17 4 12"/>
              </svg>
            </span>
          </div>

          <!-- Info -->
          <div class="pm-card-info">
            <div class="pm-card-name"><?= $safeName ?></div>
            <div class="pm-card-meta">
              <?php if ($size !== ''): ?>
              <span class="pm-meta-chip pm-chip-size">
                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                </svg>
                <?= $size ?> cm
              </span>
              <?php endif; ?>
              <?php if ($price !== ''): ?>
              <span class="pm-meta-chip pm-chip-price">₱<?= $price ?></span>
              <?php endif; ?>
            </div>
          </div>

        </button>
        <?php endforeach; ?>
      </div>

      <!-- Empty state -->
      <div class="pm-empty" id="pmEmpty" style="display:none;">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#e0e0e0" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
        </svg>
        <p>No products match your search.</p>
        <button type="button" id="pmClearSearch" class="pm-empty-btn">Clear search</button>
      </div>
    </div>

  </div>
</div>

</body>

<script src="assets/bootstrap/js/bootstrap.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const lengthInput   = document.getElementById('num1');
  const widthInput    = document.getElementById('num2');
  const areaInput     = document.getElementById('num3');
  const tileInput     = document.getElementById('num4');
  const allowanceCb   = document.getElementById('checkbox');
  const btn           = document.getElementById('calcBtn');
  const resultBox     = document.getElementById('calcResult');
  const productSelect = document.getElementById('productSelect');
  const groutGapInput = document.getElementById('groutGap');
  const resultActions = document.getElementById('resultActions');

  const fmtCurr  = n => '₱' + Number(n).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
  const fmtInt   = n => Number(n).toLocaleString();
  const fmtFloat = (n, d=2) => Number(n).toLocaleString(undefined, {minimumFractionDigits:d, maximumFractionDigits:d});
  function parseNumber(str){ if (str==null) return NaN; const s=String(str).trim().replace(/\s/g,'').replace(/,/g,''); return parseFloat(s); }

  // Auto-fill tile size when product changes
  productSelect.addEventListener('change', () => {
    const opt = productSelect.selectedOptions[0];
    tileInput.value = (opt && opt.dataset.size) ? opt.dataset.size : '';
  });

  // Auto-fill area from length × width
  function autoFillArea() {
    const l = parseNumber(lengthInput.value);
    const w = parseNumber(widthInput.value);
    if (isFinite(l) && l > 0 && isFinite(w) && w > 0) {
      areaInput.value = (l * w).toFixed(2);
    } else {
      areaInput.value = '';
    }
  }
  lengthInput.addEventListener('input', autoFillArea);
  widthInput.addEventListener('input', autoFillArea);

  // Calculate
  btn.addEventListener('click', () => {
    const length        = parseNumber(lengthInput.value);
    const width         = parseNumber(widthInput.value);
    const areaFromInput = parseNumber(areaInput.value);
    let area;

    if (isFinite(length) && isFinite(width) && length > 0 && width > 0) {
      area = length * width;
      areaInput.value = area.toFixed(2);
    } else if (isFinite(areaFromInput) && areaFromInput > 0) {
      area = areaFromInput;
    } else {
      return showError('Please enter valid Length & Width (> 0), or a valid Area.');
    }

    const parsed = parseTileSize(tileInput.value);
    if (!tileInput.value || !parsed) return showError('Please select a product first — tile size will be auto-filled.');

    const groutMm    = parseNumber(groutGapInput.value) || 0;
    const groutM     = groutMm / 1000;
    const { wCm, hCm } = parsed;
    const effW       = (wCm / 100) + groutM;
    const effH       = (hCm / 100) + groutM;
    const tileAreaM2 = effW * effH;
    if (!(tileAreaM2 > 0)) return showError('Invalid tile size or grout value.');

    const wastePct      = allowanceCb.checked ? 0.10 : 0.00;
    const baseTiles     = Math.ceil(area / tileAreaM2);
    const tilesNeeded   = Math.ceil(area / tileAreaM2 * (1 + wastePct));
    const allowanceTiles = Math.ceil(baseTiles * wastePct);

    const opt = productSelect.selectedOptions[0];
    let totalCost = null, breakdown = '';

    if (opt && opt.dataset.price) {
      const p = parseNumber(opt.dataset.price);
      if (p > 0) { totalCost = tilesNeeded * p; breakdown = 'Using product price (per piece)'; }
    } else {
      breakdown = 'Select a product above to see the total cost.';
    }

    resultBox.className = 'result ok';
    resultActions.style.display = 'flex';

    const productName = (opt && opt.dataset.name) ? opt.dataset.name : '';
    let html = '';
    if (productName) html += `<p><strong>Product:</strong> ${productName}</p>`;
    html += `
      <p><strong>Computed area:</strong> ${fmtFloat(area,2)} m²</p>
      <p><strong>Tile (with grout):</strong> ${wCm} × ${hCm} cm (+ ${groutMm} mm grout) — effective ${fmtFloat(tileAreaM2,4)} m² per tile</p>
      ${allowanceCb.checked
        ? `<p><strong>Tiles needed:</strong> ${fmtInt(baseTiles)}</p>
      <p><strong>Allowance:</strong> ${fmtInt(allowanceTiles)} tiles</p>
      <p style="font-size:1.05em;margin-top:6px;"><strong>Total (with allowance):</strong> <span style="color:#ed8d1b;font-size:1.15em;">${fmtInt(tilesNeeded)}</span></p>`
        : `<p style="font-size:1.05em;margin-top:6px;"><strong>Tiles needed:</strong> <span style="color:#ed8d1b;font-size:1.15em;">${fmtInt(tilesNeeded)}</span></p>`
      }
      <hr>
    `;
    if (totalCost !== null) {
      html += `
        <p><strong>${breakdown}</strong></p>
        <p style="font-size:1.2em;"><strong>Total estimated cost: <span style="color:#ed8d1b;">${fmtCurr(totalCost)}</span></strong></p>
        <p style="color:#666;font-size:0.82em;margin-top:6px;">Note: final cost may vary with discounts, taxes, or shipping.</p>
      `;
    } else {
      html += `<p style="color:#f0c060;">⚠ ${breakdown}</p>`;
    }
    resultBox.innerHTML = html;
  });

  function parseTileSize(str) {
    const clean = String(str).toLowerCase().replace(/\s+/g,'');
    const parts = clean.split('x');
    if (parts.length !== 2) return null;
    const w = parseFloat(parts[0]), h = parseFloat(parts[1]);
    if (!isFinite(w) || !isFinite(h) || w <= 0 || h <= 0) return null;
    return { wCm: w, hCm: h };
  }

  function showError(msg) {
    resultBox.className = 'result error';
    resultBox.textContent = '⚠ ' + msg;
    resultActions.style.display = 'none';
  }
});
</script>

<?php require 'footer.php'; ?>
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


<style>
  /* ── Picker trigger button ── */
  .picker-trigger {
    width: 100%; padding: 10px 14px;
    border: 1.5px solid #e0e0e0; border-radius: 10px;
    background: #fff; cursor: pointer;
    font-family: 'Inter', sans-serif;
    display: flex; align-items: center; gap: 11px;
    transition: border-color .18s, box-shadow .18s;
    text-align: left; min-height: 52px;
  }
  .picker-trigger:hover, .picker-trigger:focus-visible {
    border-color: #ed8d1b;
    box-shadow: 0 0 0 3px rgba(237,141,27,.12);
    outline: none;
  }
  .picker-trigger.chosen { border-color: #ed8d1b; background: #fffbf5; }
  .picker-preview {
    width: 38px; height: 38px; border-radius: 7px; overflow: hidden; flex-shrink: 0;
    background: #f4f4f4; border: 1px solid #eee;
    display: flex; align-items: center; justify-content: center;
  }
  .picker-thumb-placeholder { color: #ccc; display: flex; }
  #pickerThumb { width: 100%; height: 100%; object-fit: cover; }
  .picker-label-text {
    flex: 1; font-size: .88rem; color: #b0b0b0;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    font-family: 'Inter', sans-serif;
  }
  .picker-trigger.chosen .picker-label-text { color: #151616; font-weight: 600; }
  .picker-arrow { flex-shrink: 0; color: #ccc; transition: color .18s; }
  .picker-trigger:hover .picker-arrow,
  .picker-trigger.chosen .picker-arrow { color: #ed8d1b; }

  /* ── Modal overlay ── */
  .pm-overlay {
    display: none; position: fixed; inset: 0;
    background: rgba(10,10,10,.52);
    backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px);
    z-index: 9999; align-items: center; justify-content: center;
    padding: 20px;
  }
  .pm-overlay.open { display: flex; animation: pmFadeIn .18s ease both; }
  @keyframes pmFadeIn { from { opacity: 0; } to { opacity: 1; } }

  .pm-sheet {
    background: #fff; border-radius: 22px;
    width: 100%; max-width: 860px; max-height: 88vh;
    display: flex; flex-direction: column; overflow: hidden;
    box-shadow: 0 28px 80px rgba(0,0,0,.22), 0 0 0 1px rgba(0,0,0,.05);
    animation: pmSlide .22s cubic-bezier(.22,1,.36,1) both;
  }
  @keyframes pmSlide {
    from { opacity: 0; transform: translateY(20px) scale(.975); }
    to   { opacity: 1; transform: none; }
  }

  .pm-head {
    display: flex; align-items: center; justify-content: space-between; gap: 12px;
    padding: 24px 28px 16px; border-bottom: 1px solid #f2f2f2; flex-shrink: 0;
  }
  .pm-eyebrow {
    display: block; font-size: .64rem; font-weight: 800;
    text-transform: uppercase; letter-spacing: 2px; color: #ed8d1b; margin-bottom: 3px;
  }
  .pm-title { font-size: 1.2rem; font-weight: 900; color: #151616; margin: 0; letter-spacing: -.3px; }
  .pm-close {
    background: #f4f4f4; border: none; border-radius: 9px;
    width: 38px; height: 38px; display: flex; align-items: center; justify-content: center;
    cursor: pointer; flex-shrink: 0; color: #666;
    transition: background .15s, color .15s;
  }
  .pm-close:hover { background: #fde8cc; color: #ed8d1b; }

  .pm-toolbar {
    display: flex; align-items: center; gap: 14px;
    padding: 14px 28px 10px; flex-shrink: 0;
  }
  .pm-search-wrap { flex: 1; position: relative; display: flex; align-items: center; }
  .pm-search-ico { position: absolute; left: 12px; color: #bbb; pointer-events: none; z-index: 1; }
  .pm-search {
    width: 100%; padding: 10px 38px;
    border: 1.5px solid #ebebeb; border-radius: 10px;
    font-size: .88rem; font-family: 'Inter', sans-serif;
    color: #151616; background: #fafafa; outline: none;
    transition: border-color .18s, box-shadow .18s;
  }
  .pm-search:focus { border-color: #ed8d1b; box-shadow: 0 0 0 3px rgba(237,141,27,.1); background: #fff; }
  .pm-search::placeholder { color: #c0c0c0; }
  .pm-search-clear {
    position: absolute; right: 10px; background: none; border: none; cursor: pointer;
    color: #bbb; display: flex; align-items: center; padding: 4px;
    border-radius: 4px; transition: color .15s;
  }
  .pm-search-clear:hover { color: #ed8d1b; }
  .pm-count { font-size: .72rem; font-weight: 700; color: #bbb; white-space: nowrap; text-transform: uppercase; letter-spacing: .6px; min-width: 70px; text-align: right; }

  .pm-body { flex: 1; overflow-y: auto; padding: 6px 20px 28px; }
  .pm-body::-webkit-scrollbar { width: 5px; }
  .pm-body::-webkit-scrollbar-thumb { background: #e6e6e6; border-radius: 4px; }

  .pm-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(155px, 1fr)); gap: 12px; padding-top: 8px; }

  .pm-card {
    background: #fafafa; border: 1.5px solid #efefef; border-radius: 14px;
    padding: 0; cursor: pointer; text-align: left; font-family: 'Inter', sans-serif;
    display: flex; flex-direction: column;
    transition: border-color .16s, box-shadow .16s, transform .15s, background .16s;
    overflow: hidden; position: relative;
  }
  .pm-card:hover {
    border-color: #ed8d1b; background: #fff;
    box-shadow: 0 6px 22px rgba(237,141,27,.15); transform: translateY(-3px);
  }
  .pm-card.selected {
    border-color: #ed8d1b; background: #fff;
    box-shadow: 0 0 0 3px rgba(237,141,27,.22), 0 6px 22px rgba(237,141,27,.12);
  }

  .pm-img-wrap {
    width: 100%; aspect-ratio: 1 / 1; background: #f0f0f0; position: relative;
    display: flex; align-items: center; justify-content: center; overflow: hidden;
  }
  .pm-img { width: 100%; height: 100%; object-fit: cover; display: block; transition: transform .35s ease; }
  .pm-card:hover .pm-img { transform: scale(1.06); }
  .pm-img-fallback {
    width: 100%; height: 100%; display: flex; align-items: center; justify-content: center;
    background: linear-gradient(135deg, #f6f6f6 0%, #ececec 100%);
  }

  .pm-selected-badge {
    position: absolute; top: 8px; right: 8px;
    width: 26px; height: 26px; border-radius: 50%;
    background: #ed8d1b; color: #fff;
    display: none; align-items: center; justify-content: center;
    box-shadow: 0 2px 10px rgba(237,141,27,.45);
  }
  .pm-card.selected .pm-selected-badge { display: flex; animation: badgePop .22s cubic-bezier(.34,1.56,.64,1) both; }
  @keyframes badgePop { from { transform: scale(0) rotate(-20deg); } to { transform: scale(1) rotate(0); } }

  .pm-card-info { padding: 10px 12px 12px; }
  .pm-card-name { font-size: .8rem; font-weight: 700; color: #151616; line-height: 1.3; margin-bottom: 7px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
  .pm-card-meta { display: flex; flex-wrap: wrap; gap: 5px; }
  .pm-meta-chip { font-size: .67rem; font-weight: 700; border-radius: 5px; padding: 2px 7px; display: inline-flex; align-items: center; gap: 3px; }
  .pm-chip-size { background: #f2f2f2; color: #777; }
  .pm-chip-price { background: #fff3e0; color: #b86b00; }
  .pm-card.selected .pm-chip-price { background: #fde4b4; }

  .pm-empty { padding: 52px 20px; text-align: center; }
  .pm-empty p { margin: 12px 0 18px; font-size: .88rem; color: #bbb; font-weight: 600; }
  .pm-empty-btn { background: #f4f4f4; border: none; border-radius: 8px; padding: 8px 20px; font-size: .82rem; font-weight: 700; color: #666; cursor: pointer; font-family: inherit; transition: background .15s; }
  .pm-empty-btn:hover { background: #ebebeb; }

  @media (max-width: 560px) {
    .pm-overlay { padding: 0; align-items: flex-end; }
    .pm-sheet { border-radius: 22px 22px 0 0; max-height: 93vh; animation: pmSheetUp .24s cubic-bezier(.22,1,.36,1) both; }
    @keyframes pmSheetUp { from { transform: translateY(100%); } to { transform: none; } }
    .pm-head { padding: 18px 20px 14px; }
    .pm-toolbar { padding: 10px 16px 8px; gap: 10px; }
    .pm-body { padding: 4px 12px 36px; }
    .pm-grid { grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 10px; }
    .pm-card-info { padding: 8px 10px 10px; }
    .pm-card-name { font-size: .75rem; }
  }
  @media (max-width: 340px) { .pm-grid { grid-template-columns: 1fr 1fr; } }
</style>

<script>
(function () {
  'use strict';
  var overlay     = document.getElementById('productModal');
  var openBtn     = document.getElementById('productPickerBtn');
  var closeBtn    = document.getElementById('pmClose');
  var searchInput = document.getElementById('pmSearch');
  var searchClear = document.getElementById('pmSearchClear');
  var grid        = document.getElementById('pmGrid');
  var emptyEl     = document.getElementById('pmEmpty');
  var clearBtn2   = document.getElementById('pmClearSearch');
  var countEl     = document.getElementById('pmCount');
  var pickerLabel = document.getElementById('pickerLabel');
  var pickerThumb = document.getElementById('pickerThumb');
  var thumbHolder = document.querySelector('.picker-thumb-placeholder');
  var hiddenSel   = document.getElementById('productSelect');
  var cards       = Array.from(grid.querySelectorAll('.pm-card'));
  var total       = cards.length;

  function setCount(n) {
    countEl.textContent = n === total ? total + ' products' : n + ' / ' + total;
  }

  function filterCards(q) {
    q = (q || '').trim().toLowerCase();
    var vis = 0;
    cards.forEach(function(c) {
      var hit = !q
        || c.dataset.name.toLowerCase().indexOf(q) > -1
        || (c.dataset.size && c.dataset.size.toLowerCase().indexOf(q) > -1);
      c.style.display = hit ? '' : 'none';
      if (hit) vis++;
    });
    grid.style.display    = vis ? ''     : 'none';
    emptyEl.style.display = vis ? 'none' : 'block';
    searchClear.style.display = q ? 'flex' : 'none';
    setCount(vis);
  }

  function openModal() {
    overlay.classList.add('open');
    document.body.style.overflow = 'hidden';
    searchInput.value = '';
    filterCards('');
    setTimeout(function(){ searchInput.focus(); }, 80);
    var sel = grid.querySelector('.pm-card.selected');
    if (sel) setTimeout(function(){ sel.scrollIntoView({ block: 'nearest', behavior: 'smooth' }); }, 150);
  }

  function closeModal() {
    overlay.classList.remove('open');
    document.body.style.overflow = '';
  }

  function chooseCard(card) {
    cards.forEach(function(c){ c.classList.remove('selected'); });
    card.classList.add('selected');

    hiddenSel.value = card.dataset.id;
    hiddenSel.dispatchEvent(new Event('change'));

    var img = card.dataset.image;
    if (img) {
      pickerThumb.src = img;
      pickerThumb.style.display = 'block';
      thumbHolder.style.display = 'none';
    } else {
      pickerThumb.style.display = 'none';
      thumbHolder.style.display = 'flex';
    }
    pickerLabel.textContent = card.dataset.name
      + (card.dataset.price ? '  —  ₱' + card.dataset.price : '');
    openBtn.classList.add('chosen');
    closeModal();
  }

  setCount(total);
  openBtn.addEventListener('click', openModal);
  closeBtn.addEventListener('click', closeModal);
  overlay.addEventListener('click', function(e){ if (e.target === overlay) closeModal(); });
  document.addEventListener('keydown', function(e){ if (e.key === 'Escape') closeModal(); });
  searchInput.addEventListener('input', function(){ filterCards(this.value); });
  function clearSearch() { searchInput.value = ''; filterCards(''); searchInput.focus(); }
  searchClear.addEventListener('click', clearSearch);
  if (clearBtn2) clearBtn2.addEventListener('click', clearSearch);
  cards.forEach(function(card){
    card.addEventListener('click', function(){ chooseCard(card); });
  });
}());
</script>
</html>