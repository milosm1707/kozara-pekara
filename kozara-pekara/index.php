<?php

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Početna';

$categoryId = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$search     = isset($_GET['q']) ? trim($_GET['q']) : '';

$categories = getCategories();
$products   = getProducts($categoryId, $search);
$promotions = getPromotions(true);
$jobItems   = getHomepageItems('job', true);

$pageDescription = 'Naručite svježe pekarske proizvode online — hljeb, peciva, kolače. Dostava na adresu u Prijedoru.';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Hero sekcija -->
<section class="hero">
    <div class="hero-content">
        <h1 class="hero-title">Svježe iz pekare,<br><span>direktno do vas</span></h1>
        <p class="hero-subtitle">Naručite omiljene pekarske proizvode i uživajte u dostavi na kućnu adresu.</p>

        <!-- Pretraga -->
        <form class="hero-search" action="/index.php#productsGrid" method="GET">
            <?php if ($categoryId): ?>
                <input type="hidden" name="category" value="<?= $categoryId ?>">
            <?php endif; ?>
            <input
                type="text"
                name="q"
                placeholder="Pretražite proizvode..."
                value="<?= e($search) ?>"
                class="search-input"
                autocomplete="off"
            >
            <button type="submit" class="search-btn" aria-label="Pretraži">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                </svg>
            </button>
        </form>
    </div>

    <div class="hero-illustration" aria-hidden="true">
        <div class="hero-circle"></div>
        <span class="hero-emoji">🥖</span>
    </div>
</section>

<!-- Homepage akcije -->
<?php if (!empty($promotions)): ?>
<section class="products-section" style="padding-top:1.2rem">
    <div class="container">
        <div class="admin-header" style="margin-bottom:1rem">
            <h2 class="page-title" style="font-size:1.45rem">🔥 Aktuelne akcije</h2>
        </div>
        <div class="info-grid-3">
            <?php foreach ($promotions as $promo): ?>
                <article class="info-card">
                    <?php if (!empty($promo['image'])): ?>
                        <img
                            src="<?= e(promotionImageUrl($promo['image'])) ?>"
                            alt="<?= e($promo['title']) ?>"
                            style="width:100%;max-height:170px;object-fit:cover;border-radius:12px;margin-bottom:0.8rem"
                            loading="lazy"
                            onerror="this.src='/assets/img/no-image.svg'"
                        >
                    <?php endif; ?>
                    <?php if (!empty($promo['badge'])): ?>
                        <span class="order-status status-pending" style="margin-bottom:0.5rem"><?= e($promo['badge']) ?></span>
                    <?php endif; ?>
                    <h3><?= e($promo['title']) ?></h3>
                    <?php if (!empty($promo['products'])): ?>
                        <p class="text-sm text-muted" style="margin-bottom:0.35rem">Sadrži: <?= e(implode(' + ', array_column($promo['products'], 'name'))) ?></p>
                    <?php endif; ?>
                    <p><?= nl2br(e($promo['description'])) ?></p>
                    <p style="margin-top:0.7rem"><strong><?= formatPrice((float)$promo['promo_price']) ?></strong></p>

                    <?php if (!empty($promo['linked_product_id']) && $user && $user['role'] !== 'pekar'): ?>
                        <button class="btn btn-primary" style="margin-top:0.8rem" onclick="addToCart(<?= (int)$promo['linked_product_id'] ?>, this)">
                            Dodaj akciju u korpu
                        </button>
                    <?php elseif (!$user): ?>
                        <a href="/login.php" class="btn btn-outline" style="margin-top:0.8rem">Prijavi se za naručivanje</a>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Oglasi za posao -->
<?php if (!empty($jobItems)): ?>
<section class="products-section" style="padding-top:0.5rem">
    <div class="container">
        <div class="admin-header" style="margin-bottom:1rem">
            <h2 class="page-title" style="font-size:1.45rem">💼 Otvorene pozicije</h2>
        </div>
        <div class="info-grid-3">
            <?php foreach ($jobItems as $job): ?>
                <article class="info-card">
                    <?php if (!empty($job['badge'])): ?>
                        <span class="order-status status-preparing" style="margin-bottom:0.5rem"><?= e($job['badge']) ?></span>
                    <?php endif; ?>
                    <h3><?= e($job['title']) ?></h3>
                    <p><?= nl2br(e($job['description'])) ?></p>
                    <a href="<?= e($job['cta_url'] ?: '/contact.php?topic=job') ?>" class="btn btn-outline" style="margin-top:0.8rem">
                        <?= e($job['cta_text'] ?: 'Prijavi se') ?>
                    </a>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Kategorije filter -->
<section class="categories-section" id="meni">
    <div class="container">
        <div class="categories-scroll">
                <a href="/index.php<?= $search ? '?q=' . urlencode($search) : '' ?>#meni"
               class="category-chip <?= $categoryId === 0 ? 'active' : '' ?>">
                🍞 Sve
            </a>
            <?php foreach ($categories as $cat): ?>
                     <a href="/index.php?category=<?= $cat['id'] ?><?= $search ? '&q=' . urlencode($search) : '' ?>#meni"
                   class="category-chip <?= $categoryId === (int)$cat['id'] ? 'active' : '' ?>">
                    <?= e($cat['name']) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Rezultati pretrage -->
<?php if ($search): ?>
<div class="container">
    <p class="search-results-info">
        <?= count($products) ?> rezultat(a) za "<strong><?= e($search) ?></strong>"
        — <a href="/index.php<?= $categoryId ? '?category=' . $categoryId : '' ?>">Poništi pretragu</a>
    </p>
</div>
<?php endif; ?>

<!-- Proizvodi -->
<section class="products-section">
    <div class="container">
        <?php if (empty($products)): ?>
            <div class="empty-state">
                <span class="empty-icon">🔍</span>
                <h3>Nema proizvoda</h3>
                <p>Nismo pronašli proizvode koji odgovaraju vašoj pretrazi.</p>
                <a href="/index.php" class="btn btn-primary">Pogledajte sve</a>
            </div>
        <?php else: ?>
            <div class="products-grid" id="productsGrid">
                <?php foreach ($products as $product): ?>
                    <article
                        class="product-card"
                        data-product-id="<?= $product['id'] ?>"
                        role="button"
                        tabindex="0"
                        aria-label="Prikaži detalje: <?= e($product['name']) ?>"
                    >
                        <!-- Slika -->
                        <div class="product-image-wrap">
                            <img
                                src="<?= e(productImageUrl($product['image'])) ?>"
                                alt="<?= e($product['name']) ?>"
                                class="product-image"
                                loading="lazy"
                                onerror="this.src='/assets/img/no-image.svg'"
                            >
                            <?php if ($product['category_name']): ?>
                                <span class="product-badge"><?= e($product['category_name']) ?></span>
                            <?php endif; ?>
                        </div>

                        <!-- Info -->
                        <div class="product-info">
                            <h3 class="product-name"><?= e($product['name']) ?></h3>
                            <?php if ($product['description']): ?>
                                <p class="product-desc"><?= e($product['description']) ?></p>
                            <?php endif; ?>

                            <div class="product-footer">
                                <span class="product-price"><?= formatPrice((float)$product['price']) ?></span>

                                <?php if ($user && $user['role'] !== 'pekar'): ?>
                                    <button
                                        class="btn btn-add-cart"
                                        onclick="addToCart(<?= $product['id'] ?>, this)"
                                        aria-label="Dodaj u korpu"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                                        </svg>
                                        <span class="btn-add-cart-label">Dodaj</span>
                                    </button>
                                <?php elseif (!$user): ?>
                                    <a href="/login.php" class="btn btn-add-cart btn-login-prompt">
                                        Prijavi se
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted text-sm">Samo pregled</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Toast notifikacija -->
<div class="toast" id="toast" role="status" aria-live="polite"></div>

<!-- Modal sa detaljima proizvoda (otvara se klikom na proizvod u gridu) -->
<div class="product-modal" id="productModal" aria-hidden="true">
    <div class="product-modal-backdrop" data-close-product-modal="1"></div>
    <div class="product-modal-card" role="dialog" aria-modal="true" aria-labelledby="productModalTitle">
        <button type="button" class="product-modal-close" id="productModalClose" aria-label="Zatvori">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
        </button>
        <div class="product-modal-image-wrap">
            <img src="" alt="" id="productModalImage" class="product-modal-image">
            <span class="product-badge" id="productModalBadge" hidden></span>
        </div>
        <div class="product-modal-body">
            <h2 class="product-modal-name" id="productModalTitle"></h2>
            <p class="product-modal-desc" id="productModalDesc" hidden></p>
            <div class="product-modal-footer" id="productModalFooter"></div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
