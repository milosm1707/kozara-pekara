<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

if (defined('FORCE_HTTPS_REDIRECT') && FORCE_HTTPS_REDIRECT) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') === '443');
    if (!$isHttps && !empty($_SERVER['HTTP_HOST']) && !empty($_SERVER['REQUEST_URI'])) {
        header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'], true, 301);
        exit;
    }
}

$cartCount = getCartCount();
$user = currentUser();

// Pekar ima pristup samo narudžbama i svom profilu — na svakoj drugoj
// stranici (koja učitava header.php) ga vraćamo nazad. Provjera je ovdje,
// na jednom mjestu, da bi važila za CIJELU aplikaciju bez izuzetaka.
if ($user && $user['role'] === 'pekar') {
    $_pekarAllowedPaths = ['/pekar/orders.php', '/profile.php'];
    $_pekarCurrentPath  = strtolower((string)(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/'));
    if (!in_array($_pekarCurrentPath, $_pekarAllowedPaths, true)) {
        header('Location: /pekar/orders.php');
        exit;
    }
}
?>
<?php
$_seoDesc      = $pageDescription ?? 'Kozara pekara Prijedor — svježi pekarski proizvodi, hljeb, peciva i kolači sa dostavom na kućnu adresu.';
$_seoTitle     = ($pageTitle ?? 'Kozara') . ' — Kozara';
$_baseUrl      = base_url();
$_requestPath  = strtolower(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/');
$_isRestricted = strpos($_requestPath, '/admin') !== false || strpos($_requestPath, '/manager') !== false;
$_canonical    = isset($pageCanonical) ? $pageCanonical : ($_baseUrl . ($_SERVER['REQUEST_URI'] ?? '/'));
$_ogImage      = $_baseUrl . '/images/kozara.png';
?>
<!DOCTYPE html>
<html lang="bs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="description" content="<?= e($_seoDesc) ?>">
    <meta name="robots" content="<?= $_isRestricted ? 'noindex, nofollow' : 'index, follow' ?>">
    <link rel="canonical" href="<?= e($_canonical) ?>">
    <meta property="og:title" content="<?= e($_seoTitle) ?>">
    <meta property="og:description" content="<?= e($_seoDesc) ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= e($_canonical) ?>">
    <meta property="og:image" content="<?= e($_ogImage) ?>">
    <meta property="og:locale" content="bs_BA">
    <title><?= e($pageTitle ?? 'Kozara') ?> — Kozara</title>
    <!-- Dodan query param za CSS da se izbjegne keširanje starog fajla na hostingu -->
    <link rel="stylesheet" href="<?= asset_url('/assets/css/style.css') . '?v=2' ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="<?= asset_url('/images/kozara.png') ?>">
    <?php if (!$_isRestricted): ?>
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "Bakery",
        "name": "Kozara",
        "url": "<?= e($_baseUrl) ?>",
        "telephone": "052233333",
        "address": {
            "@type": "PostalAddress",
            "addressLocality": "Prijedor",
            "addressCountry": "BA"
        }
    }
    </script>
    <?php endif; ?>
</head>
<body>

<!-- Navigacija -->
<nav class="navbar" id="navbar">
    <div class="nav-container">

        <!-- Logo -->
        <a href="/index.php" class="nav-logo">
            <span class="logo-icon">
                <!-- Fiksna visina kroz HTML atribut, da logo ostane mali čak i ako CSS ne učita odmah -->
                <img src="<?= asset_url('/images/kozara.png') ?>" alt="Kozara" class="nav-logo-img" height="36">
            </span>
            <span class="logo-text">Kozara</span>
        </a>

        <!-- Cart ikona odmah pored loga (pekar nema mogućnost naručivanja, pa je ne vidi) -->
        <?php if (!($user && $user['role'] === 'pekar')): ?>
        <a href="/cart.php" class="nav-cart nav-cart-inline nav-cart-left" aria-label="Korpa">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
                <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
            </svg>
            <?php if ($cartCount > 0): ?>
                <span class="cart-badge" id="cartBadgeInline"><?= $cartCount ?></span>
            <?php else: ?>
                <span class="cart-badge cart-badge-hidden" id="cartBadgeInline">0</span>
            <?php endif; ?>
        </a>
        <?php endif; ?>

        <div class="nav-right">
            <!-- Mobile hamburger (meni dugme) -->
            <button class="nav-toggle" id="navToggle" aria-label="Otvori meni" aria-expanded="false">
                <span></span>
                <span></span>
                <span></span>
            </button>
        </div>

    <!-- Nav links -->
    <ul class="nav-links" id="navLinks">
        <?php if ($user && $user['role'] === 'pekar'): ?>
            <!-- Pekar ima ograničen meni: samo profil i narudžbe, bez mogućnosti naručivanja -->
            <li><a href="/profile.php" class="nav-link">Moj profil</a></li>
            <li><a href="/pekar/orders.php" class="nav-link">Narudžbe</a></li>
            <li><a href="/logout.php" class="nav-link nav-link-logout">Odjava</a></li>
        <?php else: ?>
            <li><a href="/index.php" class="nav-link">Početna</a></li>
            <li><a href="/index.php#meni" class="nav-link">Meni</a></li>
            <li><a href="/about.php" class="nav-link">O nama</a></li>
            <li><a href="/contact.php" class="nav-link">Kontakt</a></li>

            <?php if ($user): ?>
                <li><a href="/orders.php" class="nav-link">Moje narudžbe</a></li>
                <li><a href="/profile.php" class="nav-link">Moj profil</a></li>

                <?php if ($user['role'] === 'admin'): ?>
                    <li><a href="/admin/index.php" class="nav-link nav-link-admin">Admin panel</a></li>
                <?php elseif ($user['role'] === 'manager'): ?>
                    <li><a href="/manager/index.php" class="nav-link nav-link-manager">Upravljanje</a></li>
                <?php endif; ?>

                <li><a href="/logout.php" class="nav-link nav-link-logout">Odjava</a></li>
            <?php else: ?>
                <li><a href="/login.php" class="nav-link">Prijava</a></li>
                <li><a href="/register.php" class="nav-link nav-link-register">Registracija</a></li>
            <?php endif; ?>
        <?php endif; ?>

            <?php if ($user): ?>
                <li class="nav-user-info nav-user-info-end">
                    <span class="nav-user-name">Zdravo, <?= e(explode(' ', $user['name'])[0]) ?></span>
                </li>
            <?php endif; ?>
        </ul>
    </div>
</nav>

<!-- Flash poruke -->
<?php
$flashSuccess = getFlash('success');
$flashError   = getFlash('error');
$flashInfo    = getFlash('info');
?>
<?php if ($flashSuccess): ?>
    <div class="flash flash-success" role="alert">
        <span class="flash-icon">✓</span>
        <?= e($flashSuccess) ?>
        <button class="flash-close" onclick="this.parentElement.remove()">×</button>
    </div>
<?php endif; ?>
<?php if ($flashError): ?>
    <div class="flash flash-error" role="alert">
        <span class="flash-icon">✗</span>
        <?= e($flashError) ?>
        <button class="flash-close" onclick="this.parentElement.remove()">×</button>
    </div>
<?php endif; ?>
<?php if ($flashInfo): ?>
    <div class="flash flash-info" role="alert">
        <span class="flash-icon">ℹ</span>
        <?= e($flashInfo) ?>
        <button class="flash-close" onclick="this.parentElement.remove()">×</button>
    </div>
<?php endif; ?>

<main class="main-content">
