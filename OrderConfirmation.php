<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Order Confirmation</title>
    <link rel="icon" type="image/ico" href="Favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">
    <style>
        /* ── Reset ─────────────────────────────────────────── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        /* ── CSS custom properties (single source of truth) ── */
        :root {
            --orange:      #ed8d1b;
            --orange-dark: #c97415;
            --green:       #27ae60;
            --green-light: #2ecc71;
            --red:         #c0392b;
            --red-light:   #e74c3c;
            --bg:          #151616;
            --card-bg:     #1e1e1e;
            --border:      #2e2e2e;
            --text-dim:    #888;
            --text-dimmer: #555;

            /* Fluid spacing scale */
            --space-xs:  clamp(8px,  1.5vw, 12px);
            --space-sm:  clamp(12px, 2vw,   16px);
            --space-md:  clamp(16px, 3vw,   24px);
            --space-lg:  clamp(24px, 4vw,   36px);
            --space-xl:  clamp(32px, 5vw,   48px);

            /* Fluid type scale */
            --text-xs:   clamp(10px,  1.2vw, 11.5px);
            --text-sm:   clamp(12px,  1.6vw, 14px);
            --text-base: clamp(13px,  1.8vw, 15px);
            --text-lg:   clamp(20px,  4vw,   26px);

            /* Card sizing */
            --card-radius:   clamp(16px, 3vw, 24px);
            --inner-radius:  clamp(8px,  1.5vw, 12px);
            --card-padding-x: clamp(14px, 4vw, 24px);
        }

        html {
            -webkit-text-size-adjust: 100%;
            text-size-adjust: 100%;
        }

        html, body {
            height: 100%;
            font-family: 'Inter', sans-serif;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            overflow-x: hidden;
        }

        body {
            background: var(--bg);
            background-image:
                radial-gradient(ellipse at 20% 0%,   rgba(237,141,27,0.07) 0%, transparent 55%),
                radial-gradient(ellipse at 80% 100%, rgba(39,174,96,0.04)  0%, transparent 55%);
            min-height: 100vh;
            min-height: 100dvh; /* dynamic viewport for mobile browsers */
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: var(--space-lg) var(--space-sm);
        }

        a { text-decoration: none; }

        /* ── Card ──────────────────────────────────────────── */
        .confirm-card {
            width: 100%;
            max-width: min(520px, 100%);
            background: var(--card-bg);
            border-radius: var(--card-radius);
            border: 1px solid var(--border);
            box-shadow: 0 8px 48px rgba(0,0,0,0.45);
            overflow: hidden;
            text-align: center;
            animation: slideUp 0.4s cubic-bezier(0.22, 1, 0.36, 1) both;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(28px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ── Icon area ─────────────────────────────────────── */
        .icon-area {
            padding: var(--space-xl) var(--card-padding-x) var(--space-md);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: var(--space-sm);
        }

        /* Success ring animation */
        .icon-ring {
            width:  clamp(72px, 15vw, 96px);
            height: clamp(72px, 15vw, 96px);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .icon-ring.success {
            background: rgba(39,174,96,0.12);
            border: 3px solid var(--green);
            animation: ringPulse 0.6s ease 0.1s both;
        }
        .icon-ring.error {
            background: rgba(192,57,43,0.12);
            border: 3px solid var(--red);
        }

        /* Scale the SVG icon proportionally with the ring */
        .icon-ring svg {
            width:  clamp(32px, 7vw, 46px);
            height: clamp(32px, 7vw, 46px);
            animation: iconDraw 0.4s ease 0.35s both;
        }

        @keyframes ringPulse {
            0%   { transform: scale(0.7); opacity: 0; }
            60%  { transform: scale(1.08); opacity: 1; }
            100% { transform: scale(1); }
        }
        @keyframes iconDraw {
            from { opacity: 0; transform: scale(0.6); }
            to   { opacity: 1; transform: scale(1); }
        }

        /* ── Text content ──────────────────────────────────── */
        .card-title {
            font-size: var(--text-lg);
            font-weight: 900;
            letter-spacing: -0.6px;
            line-height: 1.2;
            margin-top: var(--space-xs);
        }
        .card-title.success { color: #fff; }
        .card-title.error   { color: var(--red-light); }

        .card-subtitle {
            font-size: var(--text-base);
            color: var(--text-dim);
            line-height: 1.55;
            max-width: 340px;
            margin: 0 auto;
        }

        /* ── Message box ───────────────────────────────────── */
        .message-box {
            margin: 0 var(--card-padding-x);
            padding: clamp(10px, 2vw, 14px) clamp(12px, 3vw, 18px);
            border-radius: var(--inner-radius);
            font-size: var(--text-sm);
            font-weight: 600;
            line-height: 1.55;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            text-align: left;
            word-break: break-word;
        }
        .message-box.success {
            background: rgba(39,174,96,0.1);
            border: 1px solid rgba(39,174,96,0.3);
            color: var(--green-light);
        }
        .message-box.error {
            background: rgba(192,57,43,0.1);
            border: 1px solid rgba(192,57,43,0.3);
            color: var(--red-light);
        }
        .message-box svg { flex-shrink: 0; margin-top: 2px; }

        /* ── Divider ───────────────────────────────────────── */
        .divider {
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--border), transparent);
            margin: var(--space-md) 0;
        }

        /* ── What's next section ───────────────────────────── */
        .whats-next { padding: 0 var(--card-padding-x); text-align: left; }
        .whats-next-title {
            font-size: clamp(9px, 1.5vw, 11px);
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.9px;
            color: #666;
            margin-bottom: clamp(10px, 2vw, 14px);
        }
        .step-list { display: flex; flex-direction: column; gap: clamp(8px, 1.5vw, 11px); }
        .step-item {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: var(--text-sm);
            color: #999;
            font-weight: 600;
            line-height: 1.4;
        }
        .step-num {
            width: 24px; height: 24px;
            min-width: 24px; /* prevent squish */
            background: #2a2a2a;
            border: 1.5px solid #333;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 11px;
            font-weight: 800;
            color: var(--orange);
            flex-shrink: 0;
        }

        /* ── Action buttons ────────────────────────────────── */
        .actions {
            padding: var(--space-md) var(--card-padding-x);
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .btn-primary-action,
        .btn-secondary-action {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: clamp(12px, 2.5vw, 14px);
            border-radius: var(--inner-radius);
            font-family: 'Inter', sans-serif;
            font-size: var(--text-base);
            font-weight: 800;
            cursor: pointer;
            width: 100%;
            /* Ensure comfortable tap targets on touch devices */
            min-height: 48px;
            -webkit-tap-highlight-color: transparent;
            touch-action: manipulation;
            white-space: nowrap;
        }

        .btn-primary-action {
            background: linear-gradient(135deg, var(--orange) 0%, var(--orange-dark) 100%);
            color: #151616;
            border: none;
            transition: opacity 0.2s, transform 0.1s;
        }
        .btn-primary-action:hover  { opacity: 0.9; }
        .btn-primary-action:active { transform: scale(0.98); }

        .btn-secondary-action {
            background: transparent;
            color: #aaa;
            border: 1.5px solid var(--border);
            transition: border-color 0.2s, color 0.2s;
        }
        .btn-secondary-action:hover { border-color: var(--orange); color: var(--orange); }

        /* ── Order ID note ─────────────────────────────────── */
        .order-note {
            padding: 0 var(--card-padding-x) var(--space-md);
            font-size: var(--text-xs);
            color: var(--text-dimmer);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            flex-wrap: wrap;
            text-align: center;
            line-height: 1.5;
        }

        /* ═══════════════════════════════════════════════════
           RESPONSIVE BREAKPOINTS
        ═══════════════════════════════════════════════════ */

        /* ── Extra-small phones (< 360px) ─────────────────── */
        @media (max-width: 359px) {
            body { padding: 12px 8px; }
            .confirm-card { border-radius: 16px; }
            .icon-ring { width: 64px; height: 64px; }
            .icon-ring svg { width: 28px; height: 28px; }
            .card-title { font-size: 19px; letter-spacing: -0.3px; }
            .card-subtitle { font-size: 13px; }
            .actions { flex-direction: column; }
            .btn-primary-action,
            .btn-secondary-action { font-size: 13px; }
        }

        /* ── Small phones (360px – 479px) — stack buttons ─── */
        @media (max-width: 479px) {
            .actions { flex-direction: column; }
        }

        /* ── Standard phones (480px+) — side-by-side buttons  */
        @media (min-width: 480px) {
            .actions { flex-direction: row; }
            .btn-primary-action,
            .btn-secondary-action { flex: 1; }
        }

        /* ── Landscape phones ──────────────────────────────── */
        @media (max-height: 500px) and (orientation: landscape) {
            body {
                justify-content: flex-start;
                padding: 16px var(--space-sm);
            }
            .icon-area { padding: 20px var(--card-padding-x) 12px; gap: 10px; }
            .icon-ring  { width: 56px; height: 56px; }
            .icon-ring svg { width: 26px; height: 26px; }
            .card-title { font-size: 18px; }
            .card-subtitle { font-size: 13px; }
            .divider { margin: 12px 0; }
            .actions { padding: 12px var(--card-padding-x); }
            .order-note { padding-bottom: 12px; }
            .step-list { gap: 8px; }
        }

        /* ── Tablets (768px – 1023px) ──────────────────────── */
        @media (min-width: 768px) {
            body { padding: 15px 32px; }
            .confirm-card { max-width: 540px; }
        }

        /* ── Large / desktop (1024px+) ─────────────────────── */
        @media (min-width: 1024px) {
            body { padding: 5px 48px; }
            .confirm-card { max-width: 560px; }
        }

        /* ── Ultra-wide (1440px+) ──────────────────────────── */
        @media (min-width: 1440px) {
            body { padding: 5px 64px; }
        }

        /* ── High-DPI / Retina ─────────────────────────────── */
        @media (-webkit-min-device-pixel-ratio: 2), (min-resolution: 192dpi) {
            .confirm-card { box-shadow: 0 8px 64px rgba(0,0,0,0.55); }
        }

        /* ── Reduced motion ────────────────────────────────── */
        @media (prefers-reduced-motion: reduce) {
            .confirm-card,
            .icon-ring.success,
            .icon-ring svg { animation: none; opacity: 1; transform: none; }
        }
    </style>
</head>
<body>

<?php
$isSuccess = isset($_SESSION['flash_success']);
$isError   = isset($_SESSION['flash_error']);
$message   = $_SESSION['flash_success'] ?? $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
?>

    <div class="confirm-card">

        <!-- ── Icon area ──────────────────────────────────── -->
        <div class="icon-area">
            <?php if ($isSuccess): ?>
                <div class="icon-ring success">
                    <svg width="46" height="46" viewBox="0 0 24 24" fill="none" stroke="#27ae60" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="20 6 9 17 4 12"/>
                    </svg>
                </div>
                <div>
                    <div class="card-title success">Order Placed!</div>
                </div>
                <p class="card-subtitle">Thank you for your purchase. We&rsquo;ve received your order and will process it shortly.</p>

            <?php elseif ($isError): ?>
                <div class="icon-ring error">
                    <svg width="46" height="46" viewBox="0 0 24 24" fill="none" stroke="#c0392b" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"/>
                        <line x1="6"  y1="6" x2="18" y2="18"/>
                    </svg>
                </div>
                <div>
                    <div class="card-title error">Order Failed</div>
                </div>
                <p class="card-subtitle">Something went wrong while placing your order. Please try again or contact support.</p>

            <?php else: ?>
                <div class="icon-ring error">
                    <svg width="46" height="46" viewBox="0 0 24 24" fill="none" stroke="#888" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="12" y1="8" x2="12" y2="12"/>
                        <line x1="12" y1="16" x2="12.01" y2="16"/>
                    </svg>
                </div>
                <div>
                    <div class="card-title error">No Recent Order</div>
                </div>
                <p class="card-subtitle">We couldn&rsquo;t find a recent order. You may have already viewed this page.</p>
            <?php endif; ?>
        </div>

        <!-- ── Message box ────────────────────────────────── -->
        <?php if ($message): ?>
        <div class="message-box <?= $isSuccess ? 'success' : 'error' ?>">
            <?php if ($isSuccess): ?>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
            <?php else: ?>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <?php endif; ?>
            <?= htmlspecialchars($message) ?>
        </div>
        <?php endif; ?>

        <?php if ($isSuccess): ?>
        <div class="divider"></div>

        <!-- ── What's next ────────────────────────────────── -->
        <div class="whats-next">
            <div class="whats-next-title">What happens next</div>
            <div class="step-list">
                <div class="step-item">
                    <div class="step-num">1</div>
                    Our team will review and confirm your order.
                </div>
                <div class="step-item">
                    <div class="step-num">2</div>
                    A member of our team will contact you soon.
                </div>
                <div class="step-item">
                    <div class="step-num">3</div>
                    You can track your order in My Orders.
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ── Buttons ────────────────────────────────────── -->
        <div class="actions">
            <?php if ($isSuccess): ?>
                <a href="Orders.php" class="btn-primary-action">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1" ry="1"/></svg>
                    View My Orders
                </a>
                <a href="Products.php" class="btn-secondary-action">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                    Shop More
                </a>
            <?php else: ?>
                <a href="Cart.php" class="btn-primary-action">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                    Back to Cart
                </a>
                <a href="Products.php" class="btn-secondary-action">
                    Browse Products
                </a>
            <?php endif; ?>
        </div>

        <!-- ── Footer note ────────────────────────────────── -->
        <div class="order-note">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            Orders are typically processed within 1–2 business days.
        </div>

    </div><!-- /.confirm-card -->

</body>
</html>