<?php
// Detect current page for active nav link
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<header id="siteHeader">
    <!-- Logo -->
    <div class="logo">
        <a href="index.php">
            <img src="Logo.png" alt="Logo">
        </a>
    </div>

    <!-- Desktop Search Bar -->
    <form action="Products.php" method="GET" class="search-bar" id="desktopSearch">
        <input type="hidden" name="searchBy" value="all">
        <input type="text" name="search" placeholder="Search products…" autocomplete="off"
               value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
        <input type="hidden" name="perPage" value="12">
        <button type="submit" class="search-submit-btn" aria-label="Search">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        </button>
    </form>

    <!-- Right-side actions -->
    <div class="header-actions">
        <!-- Desktop Nav -->
        <nav id="primaryNav" aria-label="Main navigation">
            <ul id="primary-nav">
                <li><a href="index.php" class="<?= $currentPage === 'index.php' ? 'active-link' : '' ?>">Home</a></li>
                <li><a href="Products.php" class="<?= $currentPage === 'Products.php' ? 'active-link' : '' ?>">Products</a></li>
                <li><a href="TileCalculator.php" class="<?= $currentPage === 'TileCalculator.php' ? 'active-link' : '' ?>">Cost Estimator</a></li>
            </ul>
        </nav>

        <!-- Mobile Search Icon -->
        <button class="mobile-search-btn" id="mobileSearchBtn" aria-label="Open search">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        </button>

        <!-- User Icon + Dropdown -->
        <div class="user" id="userContainer">
            <button class="user-btn" id="userBtn" aria-label="User menu" aria-expanded="false">
                <span class="user-btn-inner">
                    <svg class="user-icon-svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    <svg class="chevron-icon" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                </span>
            </button>
            <div id="dropdown" role="menu" aria-hidden="true">
                <!-- Dropdown arrow tip -->
                <div class="dropdown-tip"></div>
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                    <!-- ADMIN header -->
                    <div class="dropdown-header">
                        <div class="dropdown-avatar dropdown-avatar--admin">A</div>
                        <div class="dropdown-user-info">
                            <span class="dropdown-user-name">Administrator</span>
                            <span class="dropdown-user-role">Admin Panel</span>
                        </div>
                    </div>
                    <div class="dropdown-divider"></div>
                    <ul>
                        <li role="menuitem">
                            <a href="ManageOrders.php" class="<?= $currentPage === 'ManageOrders.php' ? 'active-link' : '' ?>">
                                <span class="dd-icon">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                                </span>
                                Manage Orders
                            </a>
                        </li>
                        <li role="menuitem"><a href="ManageInquiries.php" class="<?= $currentPage === 'ManageInquiries.php' ? 'active-link' : '' ?>">Manage Inquiries</a></li>
                        <li role="menuitem"><a href="UserManagement.php" class="<?= $currentPage === 'UserManagement.php' ? 'active-link' : '' ?>">User Management</a></li>
                    </ul>
                    <div class="dropdown-divider"></div>
                    <ul>
                        <li role="menuitem"><a href="logout.php" class="logout-link">
                            <span class="dd-icon">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                            </span>
                            Log Out
                        </a></li>
                    </ul>

                <?php elseif (isset($_SESSION['CustomerID'])): ?>
                    <!-- LOGGED-IN header -->
                    <div class="dropdown-header">
                        <div class="dropdown-avatar">
                            <?= strtoupper(substr($_SESSION['CustomerName'] ?? 'U', 0, 1)) ?>
                        </div>
                        <div class="dropdown-user-info">
                            <span class="dropdown-user-name"><?= htmlspecialchars($_SESSION['CustomerName'] ?? 'My Account') ?></span>
                            <span class="dropdown-user-role">Customer</span>
                        </div>
                    </div>
                    <div class="dropdown-divider"></div>
                    <ul>
                        <li role="menuitem"><a href="Orders.php" class="<?= $currentPage === 'Orders.php' ? 'active-link' : '' ?>">
                            <span class="dd-icon">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                            </span>
                            Orders
                        </a></li>
                        <li role="menuitem"><a href="Cart.php" class="<?= $currentPage === 'Cart.php' ? 'active-link' : '' ?>">
                            <span class="dd-icon">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                            </span>
                            Cart
                        </a></li>
                        <li role="menuitem"><a href="MyAccount.php" class="<?= $currentPage === 'MyAccount.php' ? 'active-link' : '' ?>">
                            <span class="dd-icon">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            </span>
                            My Account
                        </a></li>
                    </ul>
                    <div class="dropdown-divider"></div>
                    <ul>
                        <li role="menuitem"><a href="logout.php" class="logout-link">
                            <span class="dd-icon">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                            </span>
                            Log Out
                        </a></li>
                    </ul>

                <?php else: ?>
                    <!-- GUEST header -->
                    <div class="dropdown-header dropdown-header--guest">
                        <div class="dropdown-avatar dropdown-avatar--guest">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        </div>
                        <div class="dropdown-user-info">
                            <span class="dropdown-user-name">Welcome!</span>
                            <span class="dropdown-user-role">Sign in for more</span>
                        </div>
                    </div>
                    <div class="dropdown-divider"></div>
                    <ul>
                        <li role="menuitem"><a href="Orders.php">
                            <span class="dd-icon">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                            </span>
                            Orders
                        </a></li>
                        <li role="menuitem"><a href="Cart.php">
                            <span class="dd-icon">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                            </span>
                            Cart
                        </a></li>
                        <li role="menuitem"><a href="MyAccount.php">
                            <span class="dd-icon">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            </span>
                            My Account
                        </a></li>
                    </ul>
                    <div class="dropdown-divider"></div>
                    <ul>
                        <li role="menuitem"><a href="Login&Signup.php" class="login-link">
                            <span class="dd-icon">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                            </span>
                            Log In
                        </a></li>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <!-- Hamburger -->
        <button class="nav-toggle" id="navToggle" aria-label="Toggle navigation" aria-expanded="false">
            <span class="bar"></span>
            <span class="bar"></span>
            <span class="bar"></span>
        </button>
    </div>
</header>

<!-- Mobile search slide-down panel -->
<div id="mobileSearchPanel" class="mobile-search-panel" aria-hidden="true">
    <form action="Products.php" method="GET" class="mobile-search-form">
        <input type="hidden" name="searchBy" value="all">
        <input type="text" name="search" id="mobileSearchInput" placeholder="Search products…" autocomplete="off"
               value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
        <input type="hidden" name="perPage" value="12">
        <button type="submit" class="ms-submit" aria-label="Search">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        </button>
        <button type="button" class="ms-close" id="closeSearchBtn" aria-label="Close search">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
    </form>
</div>

<!-- Mobile nav panel -->
<nav id="mobileNavPanel" class="mobile-nav-panel" aria-hidden="true">
    <ul>
        <li>
            <a href="index.php" class="<?= $currentPage === 'index.php' ? 'active-link' : '' ?>">
                <span class="mnav-icon">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                </span>
                Home
            </a>
        </li>
        <li>
            <a href="Products.php" class="<?= $currentPage === 'Products.php' ? 'active-link' : '' ?>">
                <span class="mnav-icon">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
                </span>
                Products
            </a>
        </li>
        <li>
            <a href="TileCalculator.php" class="<?= $currentPage === 'TileCalculator.php' ? 'active-link' : '' ?>">
                <span class="mnav-icon">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                </span>
                Cost Estimator
            </a>
        </li>
    </ul>
</nav>

<style>
/* ================================================================
   GLOBAL RESETS
   ================================================================ */
*, *::before, *::after { box-sizing: border-box; }

html, body {
    margin: 0;
    padding: 0;
    overflow-x: hidden;
    font-family: 'Sora', 'Segoe UI', system-ui, sans-serif;
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
    color: #151616;
}

body { padding-top: 80px !important; }

/* ================================================================
   CSS VARIABLES
   ================================================================ */
:root {
    --hdr-height: 80px;
    --hdr-bg: #181818;
    --accent: #ed8d1b;
    --accent-dark: #c97415;
    --accent-glow: rgba(237,141,27,0.18);
    --text-muted: #b0b0b0;
    --text-white: #f5f5f5;
    --dropdown-bg: #1c1c1c;
    --dropdown-border: rgba(237,141,27,0.3);
    --panel-bg: #1c1c1c;
    --border-color: #2e2e2e;
    --radius-sm: 8px;
    --radius-md: 14px;
    --transition: 0.2s ease;
}

/* ================================================================
   HEADER
   ================================================================ */
#siteHeader {
    position: fixed;
    top: 0; left: 0;
    width: 100%;
    height: var(--hdr-height);
    z-index: 900;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 0 28px;
    background: var(--hdr-bg);
    /* Accent bottom border using gradient */
    border-bottom: none;
    box-shadow: 0 1px 0 0 var(--border-color), 0 4px 24px rgba(0,0,0,0.5);
}
/* Accent gradient line at bottom */
#siteHeader::after {
    content: '';
    position: absolute;
    bottom: 0; left: 0; right: 0;
    height: 2px;
    background: linear-gradient(90deg, transparent 0%, var(--accent) 30%, var(--accent-dark) 70%, transparent 100%);
    opacity: 0.7;
}

/* ── Logo ── */
.logo { display: flex; align-items: center; flex-shrink: 0; }
.logo a { display: flex; align-items: center; }
.logo img {
    display: block;
    max-height: 58px;
    width: auto;
    transition: transform var(--transition), opacity var(--transition);
}
.logo img:hover { transform: scale(0.96); opacity: 0.85; }

/* ── Desktop Search Bar ── */
.search-bar {
    display: flex;
    align-items: center;
    flex: 1;
    max-width: 460px;
    margin: 0 20px;
}
.search-filter-select {
    height: 42px;
    padding: 0 10px;
    background: #252525;
    border: 1.5px solid #333;
    border-right: none;
    border-radius: var(--radius-sm) 0 0 var(--radius-sm);
    color: var(--accent);
    font-size: 13px;
    font-weight: 600;
    font-family: inherit;
    outline: none;
    cursor: pointer;
    flex-shrink: 0;
    appearance: none;
    -webkit-appearance: none;
    /* Custom caret arrow */
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpath d='M1 1l4 4 4-4' stroke='%23ed8d1b' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round' fill='none'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 8px center;
    padding-right: 24px;
    transition: background-color var(--transition), border-color var(--transition);
}
.search-filter-select:focus {
    border-color: var(--accent);
    background-color: #2d2d2d;
}
.search-filter-select option {
    background: #1c1c1c;
    color: #f5f5f5;
    font-weight: 500;
}
.search-divider {
    width: 1px;
    height: 24px;
    background: #3a3a3a;
    flex-shrink: 0;
    align-self: center;
}
.search-bar input[type="text"] {
    flex: 1;
    height: 42px;
    padding: 0 16px;
    background: #252525;
    border: 1.5px solid #333;
    border-right: none;
    border-radius: var(--radius-sm) 0 0 var(--radius-sm);
    color: var(--text-white);
    font-size: 14px;
    font-family: inherit;
    outline: none;
    transition: background var(--transition), border-color var(--transition);
}
.search-bar input[type="text"]::placeholder { color: #555; }
.search-bar input[type="text"]:focus,
.search-bar:focus-within input[type="text"] {
    background: #2d2d2d;
    border-color: var(--accent);
}
.search-submit-btn {
    width: 44px; height: 42px;
    background: var(--accent);
    border: none;
    border-radius: 0 var(--radius-sm) var(--radius-sm) 0;
    cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    color: #fff;
    transition: background var(--transition), transform var(--transition);
}
.search-submit-btn:hover {
    background: var(--accent-dark);
    transform: scale(1.04);
}

/* ── Header Right Actions ── */
.header-actions {
    display: flex;
    align-items: center;
    gap: 4px;
    flex-shrink: 0;
}

/* ── Desktop Nav ── */
#primaryNav ul {
    display: flex;
    align-items: center;
    gap: 2px;
    list-style: none;
    margin: 0; padding: 0;
}
#primaryNav li a {
    position: relative;
    display: block;
    padding: 8px 14px;
    border-radius: var(--radius-sm);
    color: var(--text-muted);
    font-size: 14px;
    font-weight: 600;
    text-decoration: none;
    white-space: nowrap;
    letter-spacing: 0.01em;
    background: transparent;
    transition: color var(--transition);
}
/* Animated underline accent */
#primaryNav li a::after {
    content: '';
    position: absolute;
    bottom: 2px; left: 14px; right: 14px;
    height: 2px;
    background: var(--accent);
    border-radius: 2px;
    transform: scaleX(0);
    transform-origin: center;
    transition: transform 0.22s cubic-bezier(0.4,0,0.2,1);
}
#primaryNav li a:hover { color: var(--text-white); }
#primaryNav li a:hover::after { transform: scaleX(0.5); }
#primaryNav li a.active-link { color: var(--accent); }
#primaryNav li a.active-link::after { transform: scaleX(1); }

/* ── Mobile Search Button ── */
.mobile-search-btn {
    display: none;
    align-items: center;
    justify-content: center;
    background: transparent;
    border: none;
    cursor: pointer;
    padding: 8px;
    border-radius: var(--radius-sm);
    color: var(--text-muted);
    transition: background var(--transition), color var(--transition);
    width: 40px; height: 40px;
}
.mobile-search-btn:hover { background: rgba(255,255,255,0.07); color: var(--text-white); }

/* ================================================================
   USER BUTTON + DROPDOWN
   ================================================================ */
.user { position: relative; }

.user-btn {
    display: flex; align-items: center; justify-content: center;
    background: transparent;
    border: 1.5px solid transparent;
    cursor: pointer;
    padding: 6px 10px;
    border-radius: 10px;
    transition: background var(--transition), border-color var(--transition);
}
.user-btn:hover {
    background: rgba(255,255,255,0.06);
    border-color: var(--border-color);
}
/* Active state when dropdown open */
.user.open .user-btn {
    background: rgba(237,141,27,0.1);
    border-color: rgba(237,141,27,0.4);
}
.user-btn-inner {
    display: flex;
    align-items: center;
    gap: 6px;
    color: var(--text-muted);
    transition: color var(--transition);
}
.user-btn:hover .user-btn-inner,
.user.open .user-btn-inner { color: var(--text-white); }

.user.open .user-btn-inner { color: var(--accent); }

.chevron-icon {
    transition: transform 0.22s cubic-bezier(0.4,0,0.2,1);
    flex-shrink: 0;
}
.user.open .chevron-icon { transform: rotate(180deg); }

/* ── Dropdown Panel ── */
#dropdown {
    position: absolute;
    top: calc(100% + 14px);
    right: 0;
    min-width: 210px;
    background: var(--dropdown-bg);
    border: 1px solid var(--dropdown-border);
    border-radius: var(--radius-md);
    box-shadow:
        0 0 0 1px rgba(0,0,0,0.5),
        0 16px 40px rgba(0,0,0,0.55),
        0 0 30px rgba(237,141,27,0.06);
    z-index: 1000;
    opacity: 0;
    visibility: hidden;
    transform: translateY(-8px) scale(0.97);
    transform-origin: top right;
    transition:
        opacity 0.2s cubic-bezier(0.4,0,0.2,1),
        transform 0.2s cubic-bezier(0.4,0,0.2,1),
        visibility 0.2s;
    pointer-events: none;
    overflow: hidden;
}
.user.open #dropdown {
    opacity: 1;
    visibility: visible;
    transform: translateY(0) scale(1);
    pointer-events: auto;
}

/* Stagger list items on open */
.user.open #dropdown li {
    animation: ddItemIn 0.22s ease both;
}
.user.open #dropdown li:nth-child(1) { animation-delay: 0.03s; }
.user.open #dropdown li:nth-child(2) { animation-delay: 0.07s; }
.user.open #dropdown li:nth-child(3) { animation-delay: 0.11s; }
.user.open #dropdown li:nth-child(4) { animation-delay: 0.15s; }
@keyframes ddItemIn {
    from { opacity: 0; transform: translateX(-6px); }
    to   { opacity: 1; transform: translateX(0); }
}

/* Arrow tip pointing up at trigger */
.dropdown-tip {
    position: absolute;
    top: -6px; right: 18px;
    width: 12px; height: 12px;
    background: var(--dropdown-bg);
    border-top: 1px solid var(--dropdown-border);
    border-left: 1px solid var(--dropdown-border);
    transform: rotate(45deg);
    border-radius: 2px 0 0 0;
    z-index: 1;
}

/* ── Dropdown Header (user info) ── */
.dropdown-header {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 14px 16px 12px;
}
.dropdown-avatar {
    width: 34px; height: 34px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--accent), var(--accent-dark));
    color: #fff;
    font-size: 14px;
    font-weight: 700;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    letter-spacing: 0;
    box-shadow: 0 2px 8px rgba(237,141,27,0.35);
}
.dropdown-avatar--admin {
    background: linear-gradient(135deg, #7c3aed, #4f46e5);
    box-shadow: 0 2px 8px rgba(124,58,237,0.35);
}
.dropdown-avatar--guest {
    background: #2e2e2e;
    border: 1.5px solid #3a3a3a;
    color: #888;
    box-shadow: none;
}
.dropdown-user-info {
    display: flex;
    flex-direction: column;
    gap: 1px;
    min-width: 0;
    align-items: center;
}
.dropdown-user-name {
    color: var(--text-white);
    font-size: 13px;
    font-weight: 700;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.dropdown-user-role {
    color: #666;
    font-size: 11px;
    font-weight: 500;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

/* ── Dropdown Divider ── */
.dropdown-divider {
    height: 1px;
    background: var(--border-color);
    margin: 0;
}

/* ── Dropdown Links ── */
#dropdown ul {
    list-style: none;
    margin: 0;
    padding: 4px 0;
}
#dropdown li { display: block; }

#dropdown li a {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 9px 14px;
    color: #bbb;
    font-size: 13.5px;
    font-weight: 500;
    text-decoration: none;
    background: transparent;
    border-radius: 0;
    transition: background var(--transition), color var(--transition), padding-left var(--transition);
    white-space: nowrap;
}
#dropdown li a:hover {
    background: rgba(255,255,255,0.05);
    color: var(--text-white);
    padding-left: 18px;
}
#dropdown li a.active-link {
    color: var(--accent);
    background: var(--accent-glow);
}
#dropdown li a.logout-link { color: #e05d5d; }
#dropdown li a.logout-link:hover { background: rgba(224,93,93,0.1); color: #ff7e7e; padding-left: 18px; }
#dropdown li a.login-link { color: var(--accent); font-weight: 600; }
#dropdown li a.login-link:hover { background: var(--accent-glow); }

/* ── Icon wrapper ── */
.dd-icon {
    display: flex; align-items: center; justify-content: center;
    width: 22px; height: 22px;
    border-radius: 6px;
    background: rgba(255,255,255,0.05);
    flex-shrink: 0;
    color: inherit;
    transition: background var(--transition);
}
#dropdown li a:hover .dd-icon { background: rgba(255,255,255,0.1); }
#dropdown li a.active-link .dd-icon { background: var(--accent-glow); }
#dropdown li a.logout-link .dd-icon { background: rgba(224,93,93,0.1); }
#dropdown li a.login-link .dd-icon { background: var(--accent-glow); }

/* ================================================================
   HAMBURGER
   ================================================================ */
.nav-toggle {
    display: none;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 5px;
    background: transparent;
    border: none;
    cursor: pointer;
    padding: 8px;
    border-radius: var(--radius-sm);
    width: 40px; height: 40px;
    transition: background var(--transition);
    flex-shrink: 0;
}
.nav-toggle:hover { background: rgba(255,255,255,0.07); }
.nav-toggle .bar {
    display: block;
    width: 22px; height: 2px;
    background: var(--text-muted);
    border-radius: 2px;
    transition: transform 0.22s ease, opacity 0.22s ease, background 0.22s ease;
}
.nav-toggle:hover .bar { background: var(--text-white); }
#siteHeader.nav-open .nav-toggle .bar:nth-child(1) { transform: translateY(7px) rotate(45deg); background: var(--accent); }
#siteHeader.nav-open .nav-toggle .bar:nth-child(2) { opacity: 0; }
#siteHeader.nav-open .nav-toggle .bar:nth-child(3) { transform: translateY(-7px) rotate(-45deg); background: var(--accent); }

/* ================================================================
   MOBILE SEARCH PANEL
   ================================================================ */
.mobile-search-panel {
    position: fixed;
    top: var(--hdr-height);
    left: 0; right: 0;
    z-index: 890;
    background: #161616;
    border-bottom: 1px solid var(--border-color);
    padding: 12px 16px;
    box-shadow: 0 8px 30px rgba(0,0,0,0.5);
    transform: translateY(-110%);
    transition: transform 0.28s cubic-bezier(0.4,0,0.2,1);
    pointer-events: none;
}
.mobile-search-panel.open {
    transform: translateY(0);
    pointer-events: auto;
}
.mobile-search-form {
    display: flex;
    align-items: center;
    max-width: 640px;
    margin: 0 auto;
}
.search-filter-select--mobile {
    height: 44px;
    border-radius: var(--radius-sm) 0 0 var(--radius-sm);
    font-size: 12px;
    padding: 0 22px 0 10px;
    background-position: right 6px center;
}
.search-divider--mobile {
    height: 26px;
}
.mobile-search-form input[type="text"] {
    flex: 1;
    height: 44px;
    padding: 0 16px;
    background: #252525;
    border: 1.5px solid #333;
    border-right: none;
    border-radius: var(--radius-sm) 0 0 var(--radius-sm);
    color: var(--text-white);
    font-size: 14px;
    font-family: inherit;
    outline: none;
    transition: border-color var(--transition), background var(--transition);
}
.mobile-search-form input[type="text"]:focus,
.mobile-search-form:focus-within input[type="text"] {
    border-color: var(--accent);
    background: #2d2d2d;
}
.mobile-search-form input[type="text"]::placeholder { color: #555; }
.ms-submit {
    width: 44px; height: 44px;
    background: var(--accent);
    border: none;
    border-radius: 0 var(--radius-sm) var(--radius-sm) 0;
    cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    color: #fff;
    transition: background var(--transition);
}
.ms-submit:hover { background: var(--accent-dark); }
.ms-close {
    width: 38px; height: 38px;
    background: transparent;
    border: 1px solid #333;
    border-radius: var(--radius-sm);
    cursor: pointer;
    color: #888;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    transition: background var(--transition), color var(--transition), border-color var(--transition);
}
.ms-close:hover { background: #2a2a2a; color: var(--text-white); border-color: #555; }

/* ================================================================
   MOBILE NAV PANEL
   ================================================================ */
.mobile-nav-panel {
    position: fixed;
    left: 50%;
    transform: translateX(-50%) translateY(-10px);
    top: calc(var(--hdr-height) + 10px);
    width: min(270px, calc(100vw - 32px));
    z-index: 890;
    background: var(--panel-bg);
    border: 1px solid var(--dropdown-border);
    border-radius: var(--radius-md);
    box-shadow: 0 16px 48px rgba(0,0,0,0.6), 0 0 30px rgba(237,141,27,0.05);
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.22s ease, transform 0.22s ease, visibility 0.22s;
    pointer-events: none;
    overflow: hidden;
}
.mobile-nav-panel.open {
    opacity: 1;
    visibility: visible;
    transform: translateX(-50%) translateY(0);
    pointer-events: auto;
}
.mobile-nav-panel ul {
    list-style: none;
    margin: 0;
    padding: 6px 0;
}
.mobile-nav-panel li { display: block; }
.mobile-nav-panel li a {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 18px;
    color: var(--text-muted);
    font-size: 14px;
    font-weight: 600;
    text-decoration: none;
    background: transparent;
    transition: color var(--transition), background var(--transition), padding-left var(--transition);
    letter-spacing: 0.01em;
}
.mobile-nav-panel li a:hover {
    color: var(--text-white);
    background: rgba(255,255,255,0.05);
    padding-left: 22px;
}
.mobile-nav-panel li a.active-link {
    color: var(--accent);
    background: var(--accent-glow);
}
/* Stagger mobile nav items when open */
.mobile-nav-panel.open li {
    animation: ddItemIn 0.2s ease both;
}
.mobile-nav-panel.open li:nth-child(1) { animation-delay: 0.04s; }
.mobile-nav-panel.open li:nth-child(2) { animation-delay: 0.09s; }
.mobile-nav-panel.open li:nth-child(3) { animation-delay: 0.14s; }

.mnav-icon {
    display: flex; align-items: center; justify-content: center;
    width: 24px; height: 24px;
    border-radius: 7px;
    background: rgba(255,255,255,0.06);
    flex-shrink: 0;
    color: inherit;
    transition: background var(--transition);
}
.mobile-nav-panel li a:hover .mnav-icon { background: rgba(255,255,255,0.12); }
.mobile-nav-panel li a.active-link .mnav-icon { background: var(--accent-glow); }

/* ================================================================
   RESPONSIVE BREAKPOINTS
   ================================================================ */
@media (max-width: 992px) {
    .nav-toggle { display: flex; }
    #primaryNav { display: none; }
    .search-bar { max-width: 300px; }
}

@media (max-width: 768px) {
    #siteHeader { padding: 0 16px; gap: 10px; }
    .search-bar { display: none; }
    .mobile-search-btn { display: flex; }
    .logo img { max-height: 50px; }

    /* Center dropdown on tablet */
    #dropdown {
        position: fixed !important;
        top: calc(var(--hdr-height) + 10px) !important;
        right: auto !important;
        left: 50% !important;
        transform: translateX(-50%) translateY(-8px) scale(0.97) !important;
        transform-origin: top center !important;
        min-width: min(240px, calc(100vw - 32px));
    }
    .user.open #dropdown {
        transform: translateX(-50%) translateY(0) scale(1) !important;
    }
    .dropdown-tip { display: none; }
}

/* On very small screens, make the mobile nav panel full-width */
@media (max-width: 480px) {
    :root { --hdr-height: 70px; }
    #siteHeader { padding: 0 12px; gap: 8px; }
    .logo img { max-height: 42px; }
    .nav-toggle { width: 36px; height: 36px; }
    .mobile-search-btn { width: 36px; height: 36px; }
    .mobile-search-panel { top: 64px; }
    .ms-close { display: none; }

    /* Full-width dropdown on small screens */
    #dropdown {
        left: 8px !important;
        right: 8px !important;
        min-width: unset !important;
        transform: translateX(0) translateY(-8px) scale(0.97) !important;
    }
    .user.open #dropdown {
        transform: translateX(0) translateY(0) scale(1) !important;
    }

    /* Wider mobile nav panel */
    .mobile-nav-panel {
        width: min(320px, calc(100vw - 24px));
    }

    /* Larger touch targets in mobile nav */
    .mobile-nav-panel li a {
        padding: 14px 18px;
        font-size: 15px;
    }

    /* Mobile search inputs bigger for touch */
    .mobile-search-form input[type="text"] {
        height: 48px;
        font-size: 15px;
    }
    .search-filter-select--mobile { height: 48px; }
    .ms-submit { width: 48px; height: 48px; }
}

@media (max-width: 360px) {
    :root { --hdr-height: 64px; }
    .logo img { max-height: 38px; }
}

/* Global resets */
li, ul { list-style: none; margin: 0; padding: 0; }
a { text-decoration: none; color: inherit; background: transparent; }
</style>

<script>
(function () {
    const header             = document.getElementById('siteHeader');
    const navToggle          = document.getElementById('navToggle');
    const mobileNav          = document.getElementById('mobileNavPanel');
    const userContainer      = document.getElementById('userContainer');
    const userBtn            = document.getElementById('userBtn');
    const mobileSearchBtn    = document.getElementById('mobileSearchBtn');
    const closeSearchBtn     = document.getElementById('closeSearchBtn');
    const mobileSearchPanel  = document.getElementById('mobileSearchPanel');
    const mobileSearchInput  = document.getElementById('mobileSearchInput');

    function closeAll() {
        if (header)             header.classList.remove('nav-open');
        if (mobileNav)          { mobileNav.classList.remove('open'); mobileNav.setAttribute('aria-hidden','true'); }
        if (navToggle)          navToggle.setAttribute('aria-expanded','false');
        if (userContainer)      userContainer.classList.remove('open');
        if (userBtn)            userBtn.setAttribute('aria-expanded','false');
        if (mobileSearchPanel)  { mobileSearchPanel.classList.remove('open'); mobileSearchPanel.setAttribute('aria-hidden','true'); }
    }

    /* Hamburger → Mobile Nav */
    if (navToggle && mobileNav) {
        navToggle.addEventListener('click', function (e) {
            e.stopPropagation();
            const isOpen = mobileNav.classList.toggle('open');
            header.classList.toggle('nav-open', isOpen);
            navToggle.setAttribute('aria-expanded', String(isOpen));
            mobileNav.setAttribute('aria-hidden', String(!isOpen));
            if (isOpen) {
                if (userContainer) userContainer.classList.remove('open');
                if (mobileSearchPanel) { mobileSearchPanel.classList.remove('open'); mobileSearchPanel.setAttribute('aria-hidden','true'); }
            }
        });
    }

    /* User button → Dropdown */
    if (userBtn && userContainer) {
        userBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            const isOpen = userContainer.classList.toggle('open');
            userBtn.setAttribute('aria-expanded', String(isOpen));
            const dd = document.getElementById('dropdown');
            if (dd) dd.setAttribute('aria-hidden', String(!isOpen));
            if (isOpen) {
                if (header)            header.classList.remove('nav-open');
                if (mobileNav)         { mobileNav.classList.remove('open'); mobileNav.setAttribute('aria-hidden','true'); }
                if (mobileSearchPanel) { mobileSearchPanel.classList.remove('open'); mobileSearchPanel.setAttribute('aria-hidden','true'); }
            }
        });
    }

    /* Mobile Search */
    if (mobileSearchBtn && mobileSearchPanel) {
        mobileSearchBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            const isOpen = !mobileSearchPanel.classList.contains('open');
            if (isOpen) {
                closeAll();
                mobileSearchPanel.classList.add('open');
                mobileSearchPanel.setAttribute('aria-hidden','false');
                setTimeout(() => { if (mobileSearchInput) mobileSearchInput.focus(); }, 300);
            } else {
                mobileSearchPanel.classList.remove('open');
                mobileSearchPanel.setAttribute('aria-hidden','true');
            }
        });
    }
    if (closeSearchBtn) {
        closeSearchBtn.addEventListener('click', function () {
            if (mobileSearchPanel) { mobileSearchPanel.classList.remove('open'); mobileSearchPanel.setAttribute('aria-hidden','true'); }
        });
    }

    /* Click outside → close all */
    document.addEventListener('click', function (e) {
        const inHeader = header && header.contains(e.target);
        const inMobileNav = mobileNav && mobileNav.contains(e.target);
        const inSearch = mobileSearchPanel && mobileSearchPanel.contains(e.target);
        if (!inHeader && !inMobileNav && !inSearch) closeAll();
    });

    /* Escape key */
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeAll();
    });

    /* Resize reset */
    window.addEventListener('resize', function () {
        if (window.innerWidth > 992) closeAll();
    });
})();
</script>