<?php

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdminOrManager();

$action = $_GET['action'] ?? 'list';
$editId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Neispravan zahtjev.';
    } else {
        $postAction = $_POST['post_action'] ?? '';

        if ($postAction === 'save_promotion') {
            $promotionId = (int)($_POST['promotion_id'] ?? 0) ?: null;

            $payload = $_POST;
            $uploadFailed = false;
            if (!empty($_FILES['image']['name'])) {
                $uploadResult = uploadPromotionImage($_FILES['image']);
                if (!$uploadResult['success']) {
                    $error = $uploadResult['message'];
                    $action = $promotionId ? 'edit' : 'create';
                    $editId = (int)$promotionId;
                    $uploadFailed = true;
                } else {
                    $payload['image'] = $uploadResult['filename'];
                }
            }

            if (!$uploadFailed) {
                $result = savePromotion($payload, $promotionId);
                if ($result['success']) {
                    setFlash('success', $result['message']);
                    header('Location: /manager/promotions.php');
                    exit;
                }
                $error = $result['message'];
                $action = $promotionId ? 'edit' : 'create';
                $editId = (int)$promotionId;
            }
        }

        if ($postAction === 'delete_promotion') {
            $result = deletePromotion((int)($_POST['promotion_id'] ?? 0));
            setFlash($result['success'] ? 'success' : 'error', $result['message']);
            header('Location: /manager/promotions.php');
            exit;
        }
    }
}

$allProducts = getProducts(0, '', false);
$promotions = getPromotions(false);
$editPromotion = $editId ? getPromotionById($editId) : null;

$pageTitle = 'Akcije';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container admin-page">
    <div class="admin-sidebar">
        <nav class="admin-nav">
            <div class="admin-nav-title">Manager</div>
            <a href="/manager/index.php" class="admin-nav-link">📊 Dashboard</a>
            <a href="/manager/products.php" class="admin-nav-link">🍞 Proizvodi</a>
            <a href="/manager/promotions.php" class="admin-nav-link active">🏷️ Akcije</a>
            <a href="/manager/categories.php" class="admin-nav-link">🗂️ Kategorije</a>
            <a href="/manager/homepage.php" class="admin-nav-link">💼 Oglasi za posao</a>
            <a href="/manager/orders.php" class="admin-nav-link">📦 Narudžbe</a>
            <?php if (hasRole('admin')): ?>
                <div class="admin-nav-divider"></div>
                <a href="/admin/index.php" class="admin-nav-link">⭐ Admin panel</a>
            <?php endif; ?>
            <div class="admin-nav-divider"></div>
            <a href="/index.php" class="admin-nav-link">🏠 Nazad na sajt</a>
        </nav>
    </div>

    <div class="admin-content">
        <?php if ($action === 'create' || $action === 'edit'): ?>
            <div class="admin-header">
                <h1><?= $action === 'edit' ? 'Uredi akciju' : 'Nova akcija' ?></h1>
                <a href="/manager/promotions.php" class="btn btn-outline">← Nazad</a>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><span>✗</span> <?= e($error) ?></div>
            <?php endif; ?>

            <div class="admin-form-card" style="max-width:980px">
                <form method="POST" enctype="multipart/form-data" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
                    <input type="hidden" name="post_action" value="save_promotion">
                    <input type="hidden" name="promotion_id" value="<?= (int)$editId ?>">

                    <div class="form-row">
                        <div class="form-group" style="flex:2">
                            <label class="form-label">Naziv akcije *</label>
                            <input type="text" name="title" class="form-input" value="<?= e($editPromotion['title'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Cena akcije (KM) *</label>
                            <input type="number" name="promo_price" class="form-input" step="0.10" min="0.10" value="<?= e($editPromotion['promo_price'] ?? '') ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Opis akcije</label>
                        <textarea name="description" class="form-input form-textarea" rows="3"><?= e($editPromotion['description'] ?? '') ?></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Slika akcije (opciono)</label>
                        <?php if (!empty($editPromotion['image'])): ?>
                            <div class="current-image-preview" style="margin-bottom:0.5rem">
                                <img src="<?= e(promotionImageUrl($editPromotion['image'])) ?>" alt="Trenutna slika akcije" style="max-width:140px;border-radius:8px">
                            </div>
                        <?php endif; ?>
                        <input type="file" name="image" class="form-input" accept="image/*">
                        <small class="text-muted">Ako ne uploadujete novu sliku, ostaje postojeća (ili bez slike).</small>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Badge (npr. AKCIJA)</label>
                            <input type="text" name="badge" class="form-input" value="<?= e($editPromotion['badge'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Redosled</label>
                            <input type="number" name="sort_order" class="form-input" value="<?= e($editPromotion['sort_order'] ?? '0') ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Proizvodi u akciji *</label>
                        <select name="product_ids[]" class="form-input" multiple size="8" required>
                            <?php $selected = $editPromotion['product_ids'] ?? []; ?>
                            <?php foreach ($allProducts as $product): ?>
                                <option value="<?= (int)$product['id'] ?>" <?= in_array((int)$product['id'], $selected, true) ? 'selected' : '' ?>>
                                    <?= e($product['name']) ?> (<?= formatPrice((float)$product['price']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Držite Ctrl (Windows) za izbor više proizvoda.</small>
                    </div>

                    <label class="checkbox-label" style="margin:0.6rem 0 1rem 0">
                        <input type="checkbox" name="is_active" value="1" <?= (($editPromotion['is_active'] ?? 1) ? 'checked' : '') ?>>
                        <span>Akcija je aktivna i vidljiva korisnicima</span>
                    </label>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Sačuvaj akciju</button>
                        <a href="/manager/promotions.php" class="btn btn-outline">Odustani</a>
                    </div>
                </form>
            </div>

        <?php else: ?>
            <div class="admin-header">
                <h1>Akcije</h1>
                <a href="?action=create" class="btn btn-primary">+ Nova akcija</a>
            </div>

            <?php if (empty($promotions)): ?>
                <div class="empty-state">
                    <span class="empty-icon">🏷️</span>
                    <h3>Nema akcija</h3>
                    <a href="?action=create" class="btn btn-primary">Dodaj prvu akciju</a>
                </div>
            <?php else: ?>
                <!--
                  Umjesto široke tabele koristimo isti kartični grid
                  kao na stranici proizvoda. Ovo je potpuno responsive
                  i uklanja horizontalni scroll i "odsjecanje" footera.
                -->
                <div class="products-manager-grid">
                    <?php foreach ($promotions as $promotion): ?>
                        <div class="product-manager-card <?= (int)$promotion['is_active'] === 1 ? '' : 'product-inactive' ?>">
                            <div class="product-manager-img">
                                <?php if (!empty($promotion['image'])): ?>
                                    <img src="<?= e(promotionImageUrl($promotion['image'])) ?>"
                                         alt="<?= e($promotion['title']) ?>"
                                         onerror="this.src='/assets/img/no-image.svg'">
                                <?php else: ?>
                                    <img src="/assets/img/no-image.svg" alt="Bez slike">
                                <?php endif; ?>
                            </div>
                            <div class="product-manager-info">
                                <h3><?= e($promotion['title']) ?></h3>
                                <?php if (!empty($promotion['products'])): ?>
                                    <span class="product-manager-category">
                                        <?= e(implode(' + ', array_column($promotion['products'], 'name'))) ?>
                                    </span>
                                <?php endif; ?>
                                <span class="product-manager-price"><?= formatPrice((float)$promotion['promo_price']) ?></span>
                                <div class="text-sm" style="margin-top:0.2rem; color: var(--clr-muted);">
                                    <?= (int)$promotion['is_active'] === 1 ? '🟢 Aktivna' : '⚪ Neaktivna' ?>
                                    <?php if (!empty($promotion['badge'])): ?>
                                        · <?= e($promotion['badge']) ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="product-manager-actions">
                                <a href="?action=edit&id=<?= (int)$promotion['id'] ?>" class="btn btn-outline btn-xs">✏️ Uredi</a>

                                <form method="POST" style="display:inline" onsubmit="return confirm('Obrisati akciju <?= e(addslashes($promotion['title'])) ?>?')">
                                    <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
                                    <input type="hidden" name="post_action" value="delete_promotion">
                                    <input type="hidden" name="promotion_id" value="<?= (int)$promotion['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-xs">🗑️</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
