<?php
session_start();
require 'header.php';

$isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

// ── Image upload handler (admin only) ────────────────────────
if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_image') {
    $target   = $_POST['img_target'] ?? '';
    $allowed  = ['testcover.jpg','zab1.jpg','zab2.jpg','zab3.jpg','zab4.jpg'];
    $allowedT = ['image/jpeg','image/png','image/webp','image/gif'];

    if (in_array($target, $allowed, true) && isset($_FILES['img_file']) && $_FILES['img_file']['error'] === UPLOAD_ERR_OK) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $_FILES['img_file']['tmp_name']);
        finfo_close($finfo);

        if (in_array($mime, $allowedT, true) && $_FILES['img_file']['size'] <= 8 * 1024 * 1024) {
            move_uploaded_file($_FILES['img_file']['tmp_name'], __DIR__ . '/' . $target);
            $_SESSION['img_success'] = "Image updated successfully.";
        } else {
            $_SESSION['img_error'] = "Invalid file type or file too large (max 8 MB).";
        }
    } else {
        $_SESSION['img_error'] = "Upload failed. Please try again.";
    }
    header('Location: Dealership.php');
    exit;
}
$imgSuccess = $_SESSION['img_success'] ?? ''; unset($_SESSION['img_success']);
$imgError   = $_SESSION['img_error']   ?? ''; unset($_SESSION['img_error']);

// ── Email handler ────────────────────────────────────────────
$success = false;
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name         = trim(strip_tags($_POST['name']         ?? ''));
    $email        = trim(strip_tags($_POST['email']        ?? ''));
    $mobileNumber = trim(strip_tags($_POST['mobileNumber'] ?? ''));
    $target       = trim(strip_tags($_POST['target']       ?? ''));
    $businessName = trim(strip_tags($_POST['businessName'] ?? ''));
    $businessAdd  = trim(strip_tags($_POST['businessAdd']  ?? ''));

    if ($name === '' || $email === '' || $mobileNumber === '' || $target === '') {
        $error = 'Please fill in all required fields (Name, Email, Mobile Number, Target Area).';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $to      = 'hiimgelooo@gmail.com';
        $subject = 'New Dealer Application – Zabco Tile Depot';
        $body    = "A new dealer application has been submitted.\n\n";
        $body   .= "Name:                 {$name}\n";
        $body   .= "Email:                {$email}\n";
        $body   .= "Mobile Number:        {$mobileNumber}\n";
        $body   .= "Target Area:          {$target}\n";
        if ($businessName !== '' || $businessAdd !== '') {
            $body .= "\n--- Existing Business ---\n";
            $body .= "Business Name:    {$businessName}\n";
            $body .= "Business Address: {$businessAdd}\n";
        }
        $headers  = "From: no-reply@zabcotiledepot.com\r\n";
        $headers .= "Reply-To: {$email}\r\n";
        $headers .= "X-Mailer: PHP/" . PHP_VERSION;
        if (mail($to, $subject, $body, $headers)) {
            $success = true;
        } else {
            $error = 'Sorry, we could not submit your application. Please try again later.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light" data-bss-forced-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dealership – Zabco Tile Depot</title>
    <link rel="icon" type="image/ico" href="Favicon.ico">
    <link rel="stylesheet" href="assets/bootstrap/css/bootstrap.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">
    <style>
      *, *::before, *::after { box-sizing: border-box; }
      body { background: #f9f9f9; font-family: 'Inter', sans-serif; margin: 0; }

      /* ── Hero Banner ── */
      .dl-hero {
        position: relative; overflow: hidden;
        min-height: 480px;
        display: flex; align-items: center; justify-content: center;
      }
      .dl-hero img {
        position: absolute; inset: 0;
        width: 100%; height: 100%; object-fit: cover;
        display: block; z-index: 0;
      }
      .dl-hero-overlay {
        position: absolute; inset: 0; z-index: 1;
        background: linear-gradient(135deg, rgba(21,22,22,.85) 0%, rgba(21,22,22,.5) 60%, transparent 100%);
      }
      .dl-hero-content {
        position: relative; z-index: 2;
        text-align: center; padding: 48px 24px;
        max-width: 700px;
      }
      .dl-hero-eyebrow {
        display: inline-flex; align-items: center; gap: 8px;
        font-size: .72rem; font-weight: 700; letter-spacing: 2px;
        text-transform: uppercase; color: #ed8d1b; margin-bottom: 16px;
      }
      .dl-hero-eyebrow span { opacity: .5; }
      .dl-hero-content h1 {
        font-size: clamp(2rem, 5.5vw, 3.4rem); font-weight: 900;
        color: #fff; margin: 0 0 16px; line-height: 1.1; letter-spacing: -.5px;
      }
      .dl-hero-content h1 span { color: #ed8d1b; }
      .dl-hero-content p {
        color: rgba(255,255,255,.7); font-size: clamp(.9rem, 1.4vw, 1.05rem);
        max-width: 480px; margin: 0 auto; line-height: 1.7;
      }

      /* ── Why Become a Dealer ── */
      .dl-why-section {
        background: #151616;
        padding: 64px 40px;
      }
      .dl-why-inner {
        max-width: 1200px; margin: 0 auto;
      }
      .dl-section-eyebrow {
        display: inline-flex; align-items: center; gap: 8px;
        font-size: .72rem; font-weight: 700; letter-spacing: 2px;
        text-transform: uppercase; color: #ed8d1b; margin-bottom: 10px;
      }
      .dl-section-eyebrow span { opacity: .5; }
      .dl-why-inner h2 {
        font-size: clamp(1.5rem, 3vw, 2.1rem); font-weight: 900;
        color: #fff; margin: 0 0 36px; letter-spacing: -.3px;
      }
      .dl-why-inner h2 span { color: #ed8d1b; }
      .dl-perks-grid {
        display: grid; grid-template-columns: repeat(3, 1fr);
        gap: 20px;
      }
      .dl-perk-card {
        background: #1e1e1e; border: 1px solid #2a2a2a;
        border-radius: 16px; padding: 26px 24px;
        transition: transform .2s, border-color .2s;
      }
      .dl-perk-card:hover { transform: translateY(-4px); border-color: rgba(237,141,27,.35); }
      .dl-perk-icon {
        width: 44px; height: 44px; background: rgba(237,141,27,.15);
        border-radius: 12px; display: flex; align-items: center; justify-content: center;
        font-size: 20px; margin-bottom: 14px;
        border: 1px solid rgba(237,141,27,.25);
      }
      .dl-perk-card h4 {
        color: #fff; font-size: .95rem; font-weight: 800;
        margin: 0 0 8px; letter-spacing: .1px;
      }
      .dl-perk-card p {
        color: #888; font-size: .85rem; line-height: 1.6; margin: 0;
      }

      /* ── Form section ── */
      .dl-form-section {
        max-width: 1200px; margin: 0 auto;
        padding: 72px 40px 80px;
        display: grid; grid-template-columns: 1fr 1.4fr; gap: 56px;
        align-items: start;
      }

      /* Left: context */
      .dl-form-context {}
      .dl-form-context h2 {
        font-size: clamp(1.5rem, 2.8vw, 2rem); font-weight: 900;
        color: #151616; margin: 0 0 16px; letter-spacing: -.3px; line-height: 1.2;
      }
      .dl-form-context h2 span { color: #ed8d1b; }
      .dl-form-context > p {
        color: #666; font-size: .95rem; line-height: 1.7; margin: 0 0 28px;
      }
      .dl-steps { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 16px; }
      .dl-steps li {
        display: flex; align-items: flex-start; gap: 14px;
        color: #444; font-size: .9rem; line-height: 1.5;
      }
      .dl-step-num {
        width: 30px; height: 30px; min-width: 30px; border-radius: 50%;
        background: #ed8d1b; color: #151616;
        font-weight: 900; font-size: .8rem;
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0; margin-top: 1px;
      }
      .dl-steps li strong { color: #151616; font-weight: 700; display: block; }

      /* Right: form card */
      .dl-form-card {
        background: #fff; border-radius: 20px;
        padding: 40px 38px;
        box-shadow: 0 6px 28px rgba(0,0,0,.08);
      }
      .dl-card-eyebrow {
        font-size: .72rem; font-weight: 700; letter-spacing: 2px;
        text-transform: uppercase; color: #ed8d1b;
        display: flex; align-items: center; gap: 8px; margin-bottom: 10px;
      }
      .dl-card-eyebrow::before {
        content: ''; display: inline-block; width: 24px; height: 2px;
        background: #ed8d1b; border-radius: 2px;
      }
      .dl-form-card h3 {
        font-size: 1.5rem; font-weight: 900; color: #151616;
        margin: 0 0 26px; letter-spacing: -.3px;
      }

      /* Feedback */
      .dl-feedback {
        border-radius: 10px; padding: 13px 18px; margin-bottom: 22px;
        font-size: 14px; font-weight: 600; display: flex; align-items: center; gap: 10px;
      }
      .dl-feedback.success { background: #1a3a1a; color: #6fcf6f; border: 1px solid #2d5a2d; }
      .dl-feedback.error   { background: #3a1a1a; color: #cf6f6f; border: 1px solid #5a2d2d; }

      /* Fields */
      .dl-field { margin-bottom: 18px; }
      .dl-field label {
        display: block; font-size: 12px; font-weight: 700; color: #555;
        text-transform: uppercase; letter-spacing: .7px; margin-bottom: 7px;
      }
      .dl-field label .req { color: #e05; }
      .dl-field input {
        width: 100%; padding: 13px 16px;
        background: #f7f7f7; border: 1.5px solid #e8e8e8;
        border-radius: 10px; font-family: inherit; font-size: 14px;
        color: #151616; transition: border-color .2s, background .2s; outline: none;
      }
      .dl-field input:focus {
        border-color: #ed8d1b; background: #fff;
        box-shadow: 0 0 0 3px rgba(237,141,27,.12);
      }
      .dl-field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }

      .dl-divider {
        border: none; border-top: 1.5px dashed #e8e8e8;
        margin: 24px 0 20px;
        position: relative;
      }
      .dl-divider-label {
        display: flex; align-items: center; gap: 10px;
        font-size: 11px; font-weight: 700; color: #aaa;
        text-transform: uppercase; letter-spacing: .7px; margin-bottom: 20px;
      }
      .dl-divider-label::before, .dl-divider-label::after {
        content: ''; flex: 1; height: 1px; background: #e8e8e8;
      }

      .dl-submit-btn {
        width: 100%; padding: 14px;
        background: #151616; color: #fff;
        border: none; border-radius: 10px;
        font-family: inherit; font-weight: 800; font-size: 15px;
        cursor: pointer; transition: background .2s, transform .2s;
        letter-spacing: .4px; margin-top: 6px;
        display: flex; align-items: center; justify-content: center; gap: 8px;
        position: relative; overflow: hidden;
      }
      .dl-submit-btn::before {
        content: ''; position: absolute; inset: 0;
        background: linear-gradient(90deg, transparent 0%, rgba(237,141,27,.12) 50%, transparent 100%);
        transform: translateX(-100%); transition: transform .4s ease;
      }
      .dl-submit-btn:hover { background: #ed8d1b; color: #151616; transform: translateY(-2px); }
      .dl-submit-btn:hover::before { transform: translateX(100%); }
      .dl-submit-btn::after { content: '→'; }

      /* ── Gallery strip ── */
      .dl-gallery {
        display: grid; grid-template-columns: repeat(4, 1fr);
        gap: 0; overflow: hidden;
      }
      .dl-gallery-item {
        position: relative; aspect-ratio: 1 / 1; overflow: hidden;
      }
      .dl-gallery-item img {
        width: 100%; height: 100%; object-fit: cover; display: block;
        transition: transform .4s ease;
      }
      .dl-gallery-item:hover img { transform: scale(1.08); }
      .dl-gallery-item::after {
        content: ''; position: absolute; inset: 0;
        background: rgba(21,22,22,0);
        transition: background .25s;
      }
      .dl-gallery-item:hover::after { background: rgba(237,141,27,.15); }

      /* ── Responsive ── */
      @media (max-width: 1000px) {
        .dl-form-section { grid-template-columns: 1fr; gap: 36px; padding: 52px 24px 64px; }
        .dl-perks-grid { grid-template-columns: 1fr 1fr; }
        .dl-why-section { padding: 52px 24px; }
      }
      @media (max-width: 640px) {
        .dl-hero { min-height: 360px; }
        .dl-perks-grid { grid-template-columns: 1fr; gap: 14px; }
        .dl-form-card { padding: 28px 22px; }
        .dl-field-row { grid-template-columns: 1fr; }
        .dl-gallery { grid-template-columns: repeat(2, 1fr); }
        .dl-form-section { padding: 36px 16px 52px; }
      }
      @media (max-width: 480px) {
         body { padding-top: 65px !important}
      }

      /* ── Admin image-edit overlay ── */
      .img-edit-wrap { position: relative; display: block; }
      .img-edit-btn {
        position: absolute; top: 10px; right: 10px; z-index: 20;
        display: flex; align-items: center; gap: 6px;
        padding: 7px 14px;
        background: rgba(21,22,22,.82);
        backdrop-filter: blur(6px);
        border: 1.5px solid rgba(237,141,27,.55);
        border-radius: 8px;
        color: #ed8d1b; font-size: 12px; font-weight: 700;
        cursor: pointer; font-family: 'Inter', sans-serif;
        transition: background .18s, border-color .18s, transform .15s;
        white-space: nowrap;
      }
      .img-edit-btn:hover {
        background: rgba(237,141,27,.92); color: #151616;
        border-color: #ed8d1b; transform: translateY(-1px);
      }
      /* Hero-specific (larger, darker bg) */
      .dl-hero .img-edit-btn { top: 16px; right: 16px; z-index: 10; }

      /* ── Upload modal ── */
      .img-modal-overlay {
        display: none; position: fixed; inset: 0;
        background: rgba(0,0,0,.78); z-index: 5000;
        backdrop-filter: blur(5px);
        align-items: center; justify-content: center; padding: 20px;
      }
      .img-modal-overlay.active { display: flex; }
      .img-modal-card {
        background: #1e1e1e; border: 1px solid #2e2e2e;
        border-radius: 20px;
        box-shadow: 0 16px 60px rgba(0,0,0,.6);
        width: 100%; max-width: 440px;
        animation: imgModalIn .26s cubic-bezier(.22,1,.36,1) both;
      }
      @keyframes imgModalIn { from { opacity:0; transform:scale(.93) translateY(12px); } to { opacity:1; transform:none; } }
      .img-modal-header {
        padding: 26px 28px 16px;
        border-bottom: 2px solid #ed8d1b;
        margin-bottom: 22px;
      }
      .img-modal-header h3 { color:#fff; font-size:18px; font-weight:900; margin:0 0 4px; }
      .img-modal-header p  { color:#777; font-size:13px; margin:0; }
      .img-modal-body { padding: 0 28px 28px; }

      /* Preview box */
      .img-preview-box {
        width: 100%; aspect-ratio: 16/9; border-radius: 12px;
        border: 2px dashed #3a3a3a;
        overflow: hidden; background: #141414;
        display: flex; align-items: center; justify-content: center;
        margin-bottom: 18px; position: relative;
        transition: border-color .2s;
      }
      .img-preview-box.has-image { border-style: solid; border-color: #ed8d1b44; }
      .img-preview-box img { width:100%; height:100%; object-fit:cover; display:block; }
      .img-preview-placeholder { color:#444; font-size:13px; text-align:center; padding:20px; }

      /* File input */
      .img-file-label {
        display: flex; align-items: center; justify-content: center; gap: 8px;
        width: 100%; padding: 11px;
        background: #2a2a2a; border: 1.5px solid #3a3a3a;
        border-radius: 10px; color: #ccc; font-size: 13px; font-weight: 600;
        cursor: pointer; transition: border-color .2s, background .2s;
        font-family: 'Inter', sans-serif;
      }
      .img-file-label:hover { border-color: #ed8d1b; background: #2f2f2f; color: #fff; }
      .img-file-input { display: none; }

      /* File name display */
      .img-file-name {
        font-size: 11px; color: #666; text-align: center;
        margin-top: 7px; min-height: 16px;
      }

      /* Actions */
      .img-modal-actions { display:flex; gap:10px; margin-top:20px; }
      .img-save-btn {
        flex:1; padding:13px;
        background: #ed8d1b; color: #151616;
        border: none; border-radius: 11px;
        font-size: 14px; font-weight: 800; cursor: pointer;
        font-family: 'Inter', sans-serif; transition: background .2s;
      }
      .img-save-btn:hover { background: #d07a10; }
      .img-save-btn:disabled { background: #444; color: #666; cursor: default; }
      .img-cancel-btn {
        padding: 12px 18px;
        background: transparent; color: #888;
        border: 1.5px solid #2e2e2e; border-radius: 11px;
        font-size: 14px; font-weight: 700; cursor: pointer;
        font-family: 'Inter', sans-serif; transition: border-color .2s, color .2s;
      }
      .img-cancel-btn:hover { border-color: #555; color: #ccc; }

      /* Flash banner */
      .img-flash {
        padding: 11px 18px; border-radius: 9px;
        font-size: 13px; font-weight: 600;
        display: flex; align-items: center; gap: 9px;
        margin-bottom: 18px;
      }
      .img-flash.success { background:#1a3a1a; color:#6fcf6f; border:1px solid #2d5a2d; }
      .img-flash.error   { background:#3a1a1a; color:#cf6f6f; border:1px solid #5a2d2d; }
    </style>
</head>
<body>

<!-- ── Hero Banner ── -->
<?php if ($imgSuccess): ?>
<div class="img-flash success" style="margin:12px 24px 0; max-width:600px; margin-left:auto; margin-right:auto;">
    ✓ <?= htmlspecialchars($imgSuccess) ?>
</div>
<?php elseif ($imgError): ?>
<div class="img-flash error" style="margin:12px 24px 0; max-width:600px; margin-left:auto; margin-right:auto;">
    ✕ <?= htmlspecialchars($imgError) ?>
</div>
<?php endif; ?>

<section class="dl-hero">
    <img src="testcover.jpg?v=<?= filemtime(__DIR__.'/testcover.jpg') ?>" alt="Zabco Tile Depot Dealership">
    <?php if ($isAdmin): ?>
    <button class="img-edit-btn" onclick="openImgModal('testcover.jpg', 'Hero Banner')" type="button">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
        Edit Image
    </button>
    <?php endif; ?>
    <div class="dl-hero-overlay"></div>
    <div class="dl-hero-content">
        <div class="dl-hero-eyebrow"><span>—</span> Zabco Tile Depot <span>—</span></div>
        <h1>Build Your Business<br>With <span>Zabco</span> Tiles</h1>
        <p>Join our growing network of trusted dealers and bring quality tiles to your community.</p>
    </div>
</section>

<!-- ── Why Become a Dealer ── -->
<section class="dl-why-section">
    <div class="dl-why-inner">
        <div class="dl-section-eyebrow"><span>—</span> Why Partner With Us <span>—</span></div>
        <h2>The <span>Zabco</span> Advantage</h2>
        <div class="dl-perks-grid">
            <div class="dl-perk-card">
                <div class="dl-perk-icon">🏷️</div>
                <h4>Competitive Pricing</h4>
                <p>Access dealer-exclusive pricing that lets you earn great margins while offering customers the best value.</p>
            </div>
            <div class="dl-perk-card">
                <div class="dl-perk-icon">🎨</div>
                <h4>Wide Product Range</h4>
                <p>From budget-friendly median tiles to luxurious premium designs — there's something for every customer.</p>
            </div>
            <div class="dl-perk-card">
                <div class="dl-perk-icon">🤝</div>
                <h4>Dedicated Support</h4>
                <p>Our team is always ready to assist you with orders, marketing materials, and product training.</p>
            </div>
        </div>
    </div>
</section>

<!-- ── Form + Context ── -->
<div class="dl-form-section">

    <!-- Left: context -->
    <div class="dl-form-context">
        <div class="dl-section-eyebrow"><span>—</span> Partnership <span>—</span></div>
        <h2>Become a <span>Dealer</span> Today</h2>
        <p>Fill out the application form and a member of our team will reach out to discuss the next steps with you.</p>
        <ul class="dl-steps">
            <li>
                <span class="dl-step-num">1</span>
                <span><strong>Submit Your Application</strong>Complete the form with your contact details and target dealership area.</span>
            </li>
            <li>
                <span class="dl-step-num">2</span>
                <span><strong>We'll Reach Out</strong>Our team will contact you within 2–3 business days to discuss terms.</span>
            </li>
            <li>
                <span class="dl-step-num">3</span>
                <span><strong>Start Selling</strong>Once approved, get access to our full product catalog and dealer pricing.</span>
            </li>
        </ul>
    </div>

    <!-- Right: form -->
    <div class="dl-form-card">
        <div class="dl-card-eyebrow">Application Form</div>
        <h3>Your Details</h3>

        <?php if ($success): ?>
        <div class="dl-feedback success">
            ✓ Your application has been submitted! We'll be in touch soon.
        </div>
        <?php elseif ($error !== ''): ?>
        <div class="dl-feedback error">
            ✕ <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="Dealership.php" novalidate>
            <div class="dl-field">
                <label>Full Name <span class="req">*</span></label>
                <input type="text" name="name" placeholder="Your full name"
                       value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
            </div>
            <div class="dl-field-row">
                <div class="dl-field">
                    <label>Email <span class="req">*</span></label>
                    <input type="text" name="email" placeholder="you@example.com"
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>
                <div class="dl-field">
                    <label>Mobile Number <span class="req">*</span></label>
                    <input type="text" name="mobileNumber" placeholder="09XX XXX XXXX"
                           value="<?= htmlspecialchars($_POST['mobileNumber'] ?? '') ?>">
                </div>
            </div>
            <div class="dl-field">
                <label>Target Area of Dealership <span class="req">*</span></label>
                <input type="text" name="target" placeholder="e.g. Parañaque, Cavite, Laguna"
                       value="<?= htmlspecialchars($_POST['target'] ?? '') ?>">
            </div>

            <div class="dl-divider-label">If you have an existing business (optional)</div>

            <div class="dl-field">
                <label>Business Name</label>
                <input type="text" name="businessName" placeholder="Business name"
                       value="<?= htmlspecialchars($_POST['businessName'] ?? '') ?>">
            </div>
            <div class="dl-field">
                <label>Business Address</label>
                <input type="text" name="businessAdd" placeholder="Full address"
                       value="<?= htmlspecialchars($_POST['businessAdd'] ?? '') ?>">
            </div>

            <button type="submit" class="dl-submit-btn">Submit Application</button>
        </form>
    </div>

</div>

<!-- ── Gallery Strip ── -->
<div class="dl-gallery">
    <?php
    $galleryImages = [
        ['file'=>'zab1.jpg','alt'=>'Zabco Gallery 1'],
        ['file'=>'zab2.jpg','alt'=>'Zabco Gallery 2'],
        ['file'=>'zab3.jpg','alt'=>'Zabco Gallery 3'],
        ['file'=>'zab4.jpg','alt'=>'Zabco Gallery 4'],
    ];
    foreach ($galleryImages as $i => $g):
        $v = @filemtime(__DIR__.'/'.$g['file']) ?: 1;
    ?>
    <div class="dl-gallery-item img-edit-wrap">
        <img src="<?= $g['file'] ?>?v=<?= $v ?>" alt="<?= $g['alt'] ?>">
        <?php if ($isAdmin): ?>
        <button class="img-edit-btn"
                onclick="openImgModal('<?= $g['file'] ?>', 'Gallery Image <?= $i+1 ?>')"
                type="button">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            Edit
        </button>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>

<script src="assets/bootstrap/js/bootstrap.min.js"></script>

<?php if ($isAdmin): ?>
<!-- ── Image Upload Modal (admin only) ── -->
<div id="imgModal" class="img-modal-overlay" role="dialog" aria-modal="true">
    <div class="img-modal-card">
        <div class="img-modal-header">
            <h3>Replace <span id="imgModalLabel" style="color:#ed8d1b;">Image</span></h3>
            <p>Upload a new image — JPG, PNG, or WebP, max 8 MB</p>
        </div>
        <div class="img-modal-body">
            <div class="img-preview-box" id="imgPreviewBox">
                <img id="imgPreviewEl" src="" alt="" style="display:none;">
                <div class="img-preview-placeholder" id="imgPreviewPlaceholder">
                    <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#444" stroke-width="1.5" style="display:block;margin:0 auto 8px;"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                    No image selected
                </div>
            </div>

            <form method="POST" action="Dealership.php" enctype="multipart/form-data" id="imgUploadForm">
                <input type="hidden" name="action" value="update_image">
                <input type="hidden" name="img_target" id="imgTarget" value="">

                <label class="img-file-label" for="imgFileInput">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="16 16 12 12 8 16"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"/></svg>
                    Choose Image
                </label>
                <input type="file" name="img_file" id="imgFileInput" class="img-file-input" accept="image/jpeg,image/png,image/webp,image/gif">
                <div class="img-file-name" id="imgFileName">No file chosen</div>

                <div class="img-modal-actions">
                    <button type="button" class="img-cancel-btn" onclick="closeImgModal()">Cancel</button>
                    <button type="submit" class="img-save-btn" id="imgSaveBtn" disabled>
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-right:5px;"><polyline points="20 6 9 17 4 12"/></svg>
                        Save Image
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openImgModal(target, label) {
    document.getElementById('imgModalLabel').textContent = label;
    document.getElementById('imgTarget').value           = target;
    // Reset state
    document.getElementById('imgFileInput').value  = '';
    document.getElementById('imgFileName').textContent  = 'No file chosen';
    document.getElementById('imgSaveBtn').disabled      = true;
    const previewEl  = document.getElementById('imgPreviewEl');
    const placeholder = document.getElementById('imgPreviewPlaceholder');
    const previewBox = document.getElementById('imgPreviewBox');
    previewEl.style.display  = 'none';
    placeholder.style.display = '';
    previewBox.classList.remove('has-image');

    document.getElementById('imgModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeImgModal() {
    document.getElementById('imgModal').classList.remove('active');
    document.body.style.overflow = '';
}

document.getElementById('imgFileInput').addEventListener('change', function() {
    const file = this.files[0];
    if (!file) return;

    const nameEl = document.getElementById('imgFileName');
    const saveBtn = document.getElementById('imgSaveBtn');
    const previewEl = document.getElementById('imgPreviewEl');
    const placeholder = document.getElementById('imgPreviewPlaceholder');
    const previewBox = document.getElementById('imgPreviewBox');

    nameEl.textContent = file.name + ' (' + (file.size / 1024 / 1024).toFixed(2) + ' MB)';

    const reader = new FileReader();
    reader.onload = e => {
        previewEl.src = e.target.result;
        previewEl.style.display = 'block';
        placeholder.style.display = 'none';
        previewBox.classList.add('has-image');
        saveBtn.disabled = false;
    };
    reader.readAsDataURL(file);
});

// Close on backdrop click
document.getElementById('imgModal').addEventListener('click', function(e) {
    if (e.target === this) closeImgModal();
});
</script>
<?php endif; ?>

<?php require 'footer.php'; ?>
</html>