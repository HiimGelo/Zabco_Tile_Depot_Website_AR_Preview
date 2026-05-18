<?php
session_start();
require 'header.php';

// ── Email handler ────────────────────────────────────────────
$success = false;
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim(strip_tags($_POST['input_1'] ?? ''));
    $contact = trim(strip_tags($_POST['input_2'] ?? ''));
    $email   = trim(strip_tags($_POST['input_3'] ?? ''));
    $message = trim(strip_tags($_POST['input_4'] ?? ''));

    if ($name === '' || $contact === '' || $email === '' || $message === '') {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $to      = 'hiimgelooo@gmail.com';
        $subject = 'New Contact Form Submission – Zabco Tile Depot';
        $body    = "You have received a new message from the Contact Us form.\n\n";
        $body   .= "Name:           {$name}\n";
        $body   .= "Contact Number: {$contact}\n";
        $body   .= "Email:          {$email}\n";
        $body   .= "Message:\n{$message}\n";
        $headers  = "From: no-reply@zabcotiledepot.com\r\n";
        $headers .= "Reply-To: {$email}\r\n";
        $headers .= "X-Mailer: PHP/" . PHP_VERSION;
        if (mail($to, $subject, $body, $headers)) {
            $success = true;
        } else {
            $error = 'Sorry, we could not send your message. Please try again later.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light" data-bss-forced-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Contact Us – Zabco Tile Depot</title>
    <link rel="icon" type="image/ico" href="Favicon.ico">
    <link rel="stylesheet" href="assets/bootstrap/css/bootstrap.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">
    <style>
      *, *::before, *::after { box-sizing: border-box; }
      body { background: #f9f9f9; font-family: 'Inter', sans-serif; margin: 0; }

      /* ── Hero ── */
      .cu-hero {
        background: #151616;
        padding: 72px 40px 64px;
        text-align: center;
        position: relative; overflow: hidden;
      }
      .cu-hero::before {
        content: '';
        position: absolute; inset: 0;
        background: radial-gradient(ellipse at 50% 60%, rgba(237,141,27,.12) 0%, transparent 65%);
        pointer-events: none;
      }
      .cu-hero-eyebrow {
        display: inline-flex; align-items: center; gap: 8px;
        font-size: .72rem; font-weight: 700; letter-spacing: 2px;
        text-transform: uppercase; color: #ed8d1b; margin-bottom: 14px;
      }
      .cu-hero-eyebrow span { opacity: .5; }
      .cu-hero h1 {
        font-size: clamp(2rem, 5vw, 3.2rem); font-weight: 900;
        color: #fff; margin: 0 0 14px; letter-spacing: -.5px; line-height: 1.1;
      }
      .cu-hero h1 span { color: #ed8d1b; }
      .cu-hero p {
        color: #aaa; font-size: clamp(.9rem, 1.5vw, 1.05rem);
        max-width: 480px; margin: 0 auto; line-height: 1.7;
      }

      /* ── Main content layout ── */
      .cu-main {
        max-width: 1200px; margin: 0 auto;
        padding: 72px 40px 80px;
        display: grid; grid-template-columns: 1fr 1fr; gap: 48px;
        align-items: start;
      }

      /* ── Form card ── */
      .cu-form-card {
        background: #fff; border-radius: 20px;
        padding: 40px 38px;
        box-shadow: 0 6px 28px rgba(0,0,0,.08);
      }
      .cu-card-eyebrow {
        font-size: .72rem; font-weight: 700; letter-spacing: 2px;
        text-transform: uppercase; color: #ed8d1b;
        display: flex; align-items: center; gap: 8px; margin-bottom: 10px;
      }
      .cu-card-eyebrow::before {
        content: ''; display: inline-block; width: 24px; height: 2px;
        background: #ed8d1b; border-radius: 2px;
      }
      .cu-form-card h2 {
        font-size: clamp(1.4rem, 2.5vw, 1.9rem); font-weight: 900;
        color: #151616; margin: 0 0 28px; letter-spacing: -.3px;
      }
      .cu-form-card h2 span { color: #ed8d1b; }

      /* Feedback banners */
      .cu-feedback {
        border-radius: 10px; padding: 13px 18px; margin-bottom: 22px;
        font-size: 14px; font-weight: 600; display: flex; align-items: center; gap: 10px;
      }
      .cu-feedback.success {
        background: #1a3a1a; color: #6fcf6f; border: 1px solid #2d5a2d;
      }
      .cu-feedback.error {
        background: #3a1a1a; color: #cf6f6f; border: 1px solid #5a2d2d;
      }

      /* Form fields */
      .cu-field { margin-bottom: 18px; }
      .cu-field label {
        display: block; font-size: 12px; font-weight: 700; color: #555;
        text-transform: uppercase; letter-spacing: .7px; margin-bottom: 7px;
      }
      .cu-field label .req { color: #e05; }
      .cu-field input, .cu-field textarea {
        width: 100%; padding: 13px 16px;
        background: #f7f7f7; border: 1.5px solid #e8e8e8;
        border-radius: 10px; font-family: inherit; font-size: 14px;
        color: #151616; transition: border-color .2s, background .2s;
        outline: none;
      }
      .cu-field input:focus, .cu-field textarea:focus {
        border-color: #ed8d1b; background: #fff;
        box-shadow: 0 0 0 3px rgba(237,141,27,.12);
      }
      .cu-field textarea { resize: vertical; min-height: 130px; }
      .cu-field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }

      .cu-submit-btn {
        width: 100%; padding: 14px;
        background: #ed8d1b; color: #151616;
        border: none; border-radius: 10px;
        font-family: inherit; font-weight: 800; font-size: 15px;
        cursor: pointer; transition: background .2s, transform .2s;
        letter-spacing: .2px; margin-top: 6px;
        display: flex; align-items: center; justify-content: center; gap: 8px;
      }
      .cu-submit-btn:hover { background: #c97415; transform: translateY(-2px); }
      .cu-submit-btn::after { content: '→'; }

      /* ── Info panel (right column) ── */
      .cu-info-panel {
        display: flex; flex-direction: column; gap: 24px;
      }
      .cu-info-card {
        background: #151616; border-radius: 20px; padding: 32px 30px;
        position: relative; overflow: hidden;
      }
      .cu-info-card::before {
        content: '';
        position: absolute; top: -40px; right: -40px;
        width: 160px; height: 160px; border-radius: 50%;
        background: rgba(237,141,27,.07); pointer-events: none;
      }
      .cu-info-card h3 {
        font-size: 1.15rem; font-weight: 800; color: #fff;
        margin: 0 0 20px; padding-bottom: 12px;
        border-bottom: 2px solid rgba(237,141,27,.3);
        display: flex; align-items: center; gap: 10px;
      }
      .cu-info-card h3 .icon {
        width: 32px; height: 32px; background: rgba(237,141,27,.18);
        border-radius: 8px; display: flex; align-items: center; justify-content: center;
        font-size: 16px; flex-shrink: 0;
      }
      .cu-info-item {
        display: flex; align-items: flex-start; gap: 12px;
        margin-bottom: 16px;
      }
      .cu-info-item:last-child { margin-bottom: 0; }
      .cu-info-dot {
        width: 8px; height: 8px; border-radius: 50%;
        background: #ed8d1b; margin-top: 6px; flex-shrink: 0;
      }
      .cu-info-item p {
        margin: 0; font-size: .9rem; line-height: 1.6; color: #aaa;
      }
      .cu-info-item strong { color: #fff; font-weight: 700; display: block; font-size: .8rem; text-transform: uppercase; letter-spacing: .6px; margin-bottom: 2px; }
      .cu-info-item a { color: #ed8d1b; text-decoration: none; }
      .cu-info-item a:hover { text-decoration: underline; }

      /* Social row */
      .cu-social-row {
        display: flex; gap: 12px; flex-wrap: wrap; margin-top: 6px;
      }
      .cu-social-btn {
        display: inline-flex; align-items: center; gap: 7px;
        background: rgba(237,141,27,.12); border: 1px solid rgba(237,141,27,.3);
        color: #ed8d1b; text-decoration: none;
        font-size: .8rem; font-weight: 700;
        padding: 7px 16px; border-radius: 20px;
        transition: background .18s, border-color .18s;
      }
      .cu-social-btn:hover { background: rgba(237,141,27,.25); border-color: #ed8d1b; color: #ed8d1b; }

      /* ── Map section ── */
      .cu-map-section { padding: 0 0 0; }
      .cu-map-header {
        max-width: 1200px; margin: 0 auto;
        padding: 0 40px 24px;
        display: flex; align-items: center; justify-content: space-between;
        border-bottom: 3px solid #ed8d1b; margin-bottom: 0;
      }
      .cu-map-header h2 {
        font-size: clamp(1.3rem, 2.5vw, 1.8rem);
        font-weight: 900; color: #151616; margin: 0;
      }
      .cu-map-header h2 span { color: #ed8d1b; }
      .cu-map-header p { color: #777; font-size: .9rem; margin: 0; }
      .cu-map-wrap {
        width: 100%; height: 420px; overflow: hidden;
        box-shadow: 0 -4px 20px rgba(0,0,0,.06);
      }
      .cu-map-wrap iframe {
        width: 100%; height: 100%; border: none; display: block;
      }

      /* ── Responsive ── */
      @media (max-width: 900px) {
        .cu-main { grid-template-columns: 1fr; gap: 32px; padding: 48px 24px 64px; }
        .cu-hero { padding: 52px 24px 48px; }
        .cu-map-header { padding: 0 24px 20px; }
        .cu-field-row { grid-template-columns: 1fr; }
      }
      @media (max-width: 560px) {
        body { padding-top: 70px !important; }
        .cu-hero { padding: 40px 16px 36px; }
        .cu-hero h1 { font-size: 1.9rem; }
        .cu-form-card { padding: 24px 18px; border-radius: 16px; }
        .cu-info-card { padding: 22px 18px; border-radius: 16px; }
        .cu-main { padding: 28px 14px 48px; gap: 20px; }
        .cu-map-wrap { height: 280px; }
        .cu-map-header { padding: 0 14px 16px; flex-direction: column; align-items: flex-start; gap: 4px; }
        .cu-field input, .cu-field textarea { font-size: 15px; padding: 14px 15px; }
        .cu-field input { height: 50px; }
        .cu-submit-btn { padding: 15px; font-size: 15px; }
        .cu-social-row { flex-direction: row; flex-wrap: wrap; }
      }
      @media (max-width: 400px) {
        body { padding-top: 64px !important; }
        .cu-hero h1 { font-size: 1.65rem; }
        .cu-form-card { padding: 20px 14px; }
        .cu-info-card { padding: 18px 14px; }
        .cu-main { padding: 20px 10px 40px; }
      }
    </style>
</head>
<body>

<!-- ── Hero ── -->
<section class="cu-hero">
    <div class="cu-hero-eyebrow"><span>—</span> Get In Touch <span>—</span></div>
    <h1>Talk <span>To Us</span></h1>
    <p>Have a question, need a quote, or want to know more? We'd love to hear from you.</p>
</section>

<!-- ── Main: Form + Info ── -->
<div class="cu-main">

    <!-- Form -->
    <div class="cu-form-card">
        <div class="cu-card-eyebrow">Send a Message</div>
        <h2>We'll Get Back <span>To You</span></h2>

        <?php if ($success): ?>
        <div class="cu-feedback success">
            ✓ Your message has been sent! We'll get back to you soon.
        </div>
        <?php elseif ($error !== ''): ?>
        <div class="cu-feedback error">
            ✕ <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="ContactUs.php" novalidate>
            <div class="cu-field">
                <label>Name <span class="req">*</span></label>
                <input type="text" name="input_1" placeholder="Your full name"
                       value="<?= htmlspecialchars($_POST['input_1'] ?? '') ?>">
            </div>
            <div class="cu-field-row">
                <div class="cu-field">
                    <label>Contact Number <span class="req">*</span></label>
                    <input type="text" name="input_2" placeholder="09XX XXX XXXX"
                           value="<?= htmlspecialchars($_POST['input_2'] ?? '') ?>">
                </div>
                <div class="cu-field">
                    <label>Email <span class="req">*</span></label>
                    <input type="text" name="input_3" placeholder="you@example.com"
                           value="<?= htmlspecialchars($_POST['input_3'] ?? '') ?>">
                </div>
            </div>
            <div class="cu-field">
                <label>Message <span class="req">*</span></label>
                <textarea name="input_4" placeholder="Write your message here..."><?= htmlspecialchars($_POST['input_4'] ?? '') ?></textarea>
            </div>
            <button type="submit" class="cu-submit-btn">Send Message</button>
        </form>
    </div>

    <!-- Info Panel -->
    <div class="cu-info-panel">
        <div class="cu-info-card">
            <h3><span class="icon">📍</span> Find Us</h3>
            <div class="cu-info-item">
                <span class="cu-info-dot"></span>
                <p><strong>Address</strong>Ricvic Building, 354 Tirona Hwy, Bacoor, 4102 Cavite, Philippines</p>
            </div>
            <div class="cu-info-item">
                <span class="cu-info-dot"></span>
                <p><strong>Hours</strong>Monday – Saturday: 8:00 AM – 6:00 PM<br>Sunday: Closed</p>
            </div>
        </div>

        <div class="cu-info-card">
            <h3><span class="icon">📞</span> Contact Details</h3>
            <div class="cu-info-item">
                <span class="cu-info-dot"></span>
                <p><strong>Phone / Viber</strong><a href="tel:+639983552852">+63 998 355 2852</a></p>
            </div>
            <div class="cu-info-item">
                <span class="cu-info-dot"></span>
                <p><strong>Email</strong><a href="mailto:hiimgelooo@gmail.com">zabcotiledepot@gmail.com</a></p>
            </div>
            <div class="cu-social-row">
                <a href="#" class="cu-social-btn">Facebook</a>
                <a href="#" class="cu-social-btn">Instagram</a>
            </div>
        </div>

        <div class="cu-info-card">
            <h3><span class="icon">💬</span> Quick Links</h3>
            <div class="cu-info-item">
                <span class="cu-info-dot"></span>
                <p><a href="Services.php">Browse Our Services →</a></p>
            </div>
            <div class="cu-info-item">
                <span class="cu-info-dot"></span>
                <p><a href="Dealership.php">Become a Dealer →</a></p>
            </div>
            <div class="cu-info-item">
                <span class="cu-info-dot"></span>
                <p><a href="Products.php">View Product Catalog →</a></p>
            </div>
        </div>
    </div>

</div>

<!-- ── Map ── -->
<section class="cu-map-section">
    <div class="cu-map-header">
        <div>
            <h2>Visit Our <span>Store</span></h2>
        </div>
        <p>Ricvic Building, 354 Tirona Hwy, Bacoor, 4102 Cavite, Philippines</p>
    </div>
    <div class="cu-map-wrap">
        <iframe
            src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3863.6790681943585!2d120.95008751486027!3d14.445649784727841!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3397d3f8fdbaa8a5%3A0xe7619b9821b996ee!2sZabco%20Tile%20Depot!5e0!3m2!1sen!2ssg!4v1652781361507!5m2!1sen!2ssg"
            allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade">
        </iframe>
    </div>
</section>

<script src="assets/bootstrap/js/bootstrap.min.js"></script>
<?php require 'footer.php'; ?>
</html>