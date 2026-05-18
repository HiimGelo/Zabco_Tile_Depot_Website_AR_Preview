<footer class="footer-area">

    <!-- Logo col -->
    <div class="footer-col footer-logo-col">
        <a href="index.php" class="footer-logo-link">
            <img src="Logo.png" alt="Zabco Tile Depot">
        </a>
    </div>

    <!-- About col -->
    <div class="footer-col footer-about">
        <h4>About Us</h4>
        <p>ZABCO TILE DEPOT is your affordable and trusted tile supplier in the Metro. Building your best place is our priority.</p>
    </div>

    <!-- Quick Links col -->
    <div class="footer-col footer-links">
        <h4>Quick Links</h4>
        <nav class="footer-nav" aria-label="Footer navigation">
            <a href="Products.php">Products</a>
            <a href="AboutUs.php">About Us</a>
            <a href="Services.php">Services</a>
            <a href="Dealership.php">Dealership</a>
            <a href="ContactUs.php">Contact Us</a>
        </nav>
    </div>

    <!-- Categories col -->
    <div class="footer-col footer-categories">
        <h4>Categories</h4>
        <nav class="footer-nav" aria-label="Product categories">
            <a href="Products.php?table[]=productsmedian">Median</a>
            <a href="Products.php?table[]=productssophisticated">Sophisticated</a>
            <a href="Products.php?table[]=productsluxurious">Luxurious</a>
        </nav>
    </div>

    <!-- Contact col -->
    <div class="footer-col footer-contact">
        <h4>Contact Us</h4>
        <div class="contact-info">
            <a href="tel:+639983552852" class="contact-row">
                <span class="contact-icon">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.6 1.2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.78a16 16 0 0 0 5.89 5.89l1.84-1.84a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                </span>
                (+63) 998 355 2852
            </a>
            <a href="mailto:zabcotiledepot@gmail.com" class="contact-row">
                <span class="contact-icon">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                </span>
                zabcotiledepot@gmail.com
            </a>
            <div class="social-icons">
                <a href="https://www.facebook.com/zabcotiledepot" target="_blank" rel="noopener" aria-label="Facebook" class="social-link">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg>
                </a>
                <a href="https://www.instagram.com/zabcotiledepot" target="_blank" rel="noopener" aria-label="Instagram" class="social-link">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/></svg>
                </a>
                <a href="https://www.tiktok.com/@zabcotiledepot" target="_blank" rel="noopener" aria-label="TikTok" class="social-link">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-2.88 2.5 2.89 2.89 0 0 1-2.89-2.89 2.89 2.89 0 0 1 2.89-2.89c.28 0 .54.04.79.1V9.01a6.32 6.32 0 0 0-.79-.05 6.34 6.34 0 0 0-6.34 6.34 6.34 6.34 0 0 0 6.34 6.34 6.34 6.34 0 0 0 6.33-6.34V8.69a8.17 8.17 0 0 0 4.78 1.52V6.75a4.85 4.85 0 0 1-1.01-.06z"/></svg>
                </a>
            </div>
        </div>
    </div>

    <!-- Bottom bar -->
    <div class="footer-bottom">
        <span>© <?= date('Y') ?> Zabco Tile Depot. All rights reserved.</span>
        <span class="footer-bottom-sep">·</span>
        <a href="ContactUs.php">Contact</a>
    </div>

</footer>

<style>
/* ================================================================
   FOOTER
   ================================================================ */
.footer-area {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-start;
    gap: 2rem 2.5rem;
    background: #1a1a1a;
    padding: 48px 40px 0;
    background-clip: padding-box;
    width: 100%;
    box-sizing: border-box;
    position: relative;
}

/* Accent top border matching the header gradient */
.footer-area::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 2px;
    background: linear-gradient(90deg, transparent 0%, #ed8d1b 30%, #c97415 70%, transparent 100%);
    opacity: 0.7;
}

/* ── Columns ── */
.footer-col {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 0;
}

.footer-logo-col {
    flex: 0 0 auto;
    align-items: flex-start;
    min-width: 120px;
}
.footer-about    { flex: 0 1 210px; min-width: 160px; }
.footer-links    { flex: 0 1 130px; min-width: 110px; }
.footer-categories { flex: 0 1 155px; min-width: 120px; }
.footer-contact  { flex: 0 1 230px; min-width: 180px; }

/* ── Logo ── */
.footer-logo-link {
    display: inline-flex;
    align-items: center;
    transition: opacity 0.18s ease;
}
.footer-logo-link:hover { opacity: 0.8; }
.footer-logo-link img {
    display: block;
    max-width: 130px;
    height: auto;
}

/* ── Headings ── */
.footer-area h4 {
    font-family: 'Sora', 'Segoe UI', system-ui, sans-serif;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.12em;
    color: #ed8d1b;
    margin: 0 0 14px;
    padding: 0;
    text-transform: uppercase;
}

/* ── Body text ── */
.footer-area p {
    font-family: 'Sora', 'Segoe UI', system-ui, sans-serif;
    font-size: 13px;
    line-height: 1.7;
    color: #888;
    margin: 0;
    padding: 0;
}

/* ── Nav links ── */
.footer-nav {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.footer-nav a {
    font-family: 'Sora', 'Segoe UI', system-ui, sans-serif;
    font-size: 13px;
    font-weight: 500;
    color: #888;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: color 0.18s ease, padding-left 0.18s ease;
}
.footer-nav a::before {
    content: '';
    width: 0;
    height: 1.5px;
    background: #ed8d1b;
    border-radius: 2px;
    transition: width 0.2s ease;
    flex-shrink: 0;
}
.footer-nav a:hover {
    color: #f5f5f5;
    padding-left: 4px;
}
.footer-nav a:hover::before { width: 10px; }

/* ── Contact info ── */
.contact-info {
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.contact-row {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-family: 'Sora', 'Segoe UI', system-ui, sans-serif;
    font-size: 13px;
    font-weight: 500;
    color: #888;
    text-decoration: none;
    transition: color 0.18s ease;
    word-break: break-all;
}
.contact-row:hover { color: #f5f5f5; }
.contact-icon {
    display: flex; align-items: center; justify-content: center;
    width: 24px; height: 24px;
    border-radius: 6px;
    background: rgba(237,141,27,0.1);
    color: #ed8d1b;
    flex-shrink: 0;
}

/* ── Social Icons ── */
.social-icons {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 2px;
}
.social-link {
    display: flex; align-items: center; justify-content: center;
    width: 34px; height: 34px;
    border-radius: 8px;
    background: rgba(255,255,255,0.06);
    border: 1px solid rgba(255,255,255,0.08);
    color: #888;
    text-decoration: none;
    transition: background 0.18s ease, color 0.18s ease, transform 0.18s ease, border-color 0.18s ease;
}
.social-link:hover {
    background: rgba(237,141,27,0.15);
    border-color: rgba(237,141,27,0.4);
    color: #ed8d1b;
    transform: translateY(-2px);
}

/* ── Bottom bar ── */
.footer-bottom {
    width: 100%;
    flex-basis: 100%;
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 16px 0 20px;
    margin-top: 8px;
    border-top: 1px solid #2a2a2a;
    font-family: 'Sora', 'Segoe UI', system-ui, sans-serif;
    font-size: 12px;
    color: #555;
}
.footer-bottom a {
    color: #666;
    text-decoration: none;
    transition: color 0.18s ease;
}
.footer-bottom a:hover { color: #ed8d1b; }
.footer-bottom-sep { color: #333; }

/* ================================================================
   RESPONSIVE: Tablet (≤ 900px)
   ================================================================ */
@media (max-width: 900px) {
    .footer-area {
        padding: 36px 28px 0;
        gap: 1.8rem 2rem;
    }
    .footer-logo-col { flex: 0 0 100%; }
    .footer-about    { flex: 0 1 calc(55% - 1rem); }
    .footer-links    { flex: 0 1 calc(25% - 1rem); }
    .footer-categories { flex: 0 1 calc(30% - 1rem); }
    .footer-contact  { flex: 0 1 calc(40% - 1rem); }
}

/* ================================================================
   RESPONSIVE: Mobile (≤ 640px) — minimalistic
   ================================================================ */
@media (max-width: 640px) {
    /* Hide logo, about, and categories on mobile */
    .footer-logo-col,
    .footer-about,
    .footer-categories { display: none; }

    .footer-area {
        flex-direction: row;
        flex-wrap: wrap;
        align-items: flex-start;
        padding: 28px 20px 0;
        gap: 1.6rem 2rem;
        justify-content: space-between;
    }

    /* Quick links and contact sit side by side */
    .footer-links   { flex: 1 1 120px; }
    .footer-contact { flex: 1 1 160px; }

    .footer-nav a:hover { padding-left: 0; }

    .footer-bottom {
        justify-content: center;
        text-align: center;
        flex-wrap: wrap;
        gap: 6px;
        padding: 14px 0 16px;
    }
    .footer-bottom-sep { display: none; }
    .footer-bottom a { display: none; } /* keep it truly minimal */
}

/* ================================================================
   RESPONSIVE: Small mobile (≤ 480px)
   ================================================================ */
@media (max-width: 480px) {
    .footer-area {
        flex-direction: column;
        padding: 24px 16px 0;
        gap: 1.4rem;
    }
    .footer-links,
    .footer-contact { flex: none; width: 100%; align-items: center; }

    /* Show social icons in a row */
    .social-icons { gap: 10px; }
    .social-link { width: 36px; height: 36px; }

    /* Show contact info inline */
    .contact-info { gap: 8px; align-items:center; }
}

/* ================================================================
   RESPONSIVE: Small mobile (≤ 400px)
   ================================================================ */
@media (max-width: 400px) {
    .footer-area { padding: 20px 12px 0; gap: 1.2rem; }
    .footer-area h4 { font-size: 9px; }
    .footer-nav a,
    .contact-row,
    .footer-area p { font-size: 12.5px; }
    .social-link { width: 30px; height: 30px; }
}
</style>