<?php

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdminOrManager();

$action = $_GET['action'] ?? 'list';
$editId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Neispravan zahtjev.';
    } else {
        $postAction = $_POST['post_action'] ?? '';

        switch ($postAction) {
            case 'save_product':
                $productId = (int)($_POST['product_id'] ?? 0) ?: null;
                $imageFilename = null;

                if (!empty($_FILES['image']['name'])) {
                    $uploadResult = uploadProductImage($_FILES['image']);
                    if (!$uploadResult['success']) {
                        $error  = $uploadResult['message'];
                        $action = $productId ? 'edit' : 'create';
                        $editId = $productId;
                        break;
                    }
                    $imageFilename = $uploadResult['filename'];

                    if ($productId) {
                        $oldProduct = getProductById($productId);
                        if ($oldProduct && $oldProduct['image']) {
                            $oldPath = __DIR__ . '/../uploads/products/' . $oldProduct['image'];
                            if (file_exists($oldPath)) unlink($oldPath);
                        }
                    }
                }

                $data = array_merge($_POST, $imageFilename ? ['image' => $imageFilename] : []);
                $result = saveProduct($data, $productId);

                if ($result['success']) {
                    setFlash('success', $result['message']);
                    header('Location: /manager/products.php');
                    exit;
                }
                $error  = $result['message'];
                $action = $productId ? 'edit' : 'create';
                $editId = $productId;
                break;

            case 'delete_product':
                $pid    = (int)($_POST['product_id'] ?? 0);
                $result = deleteProduct($pid);
                setFlash($result['success'] ? 'success' : 'error', $result['message']);
                header('Location: /manager/products.php');
                exit;

            case 'toggle_availability':
                $pid = (int)($_POST['product_id'] ?? 0);
                $db  = getDB();
                $stmt = $db->prepare("UPDATE products SET is_available = IF(is_available=1,0,1) WHERE id=?");
                $stmt->execute([$pid]);
                setFlash('success', 'Dostupnost ažurirana.');
                header('Location: /manager/products.php');
                exit;
        }
    }
}

$categoryFilter = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$categories = getCategories();
$allProducts = getProducts(0, '', false);
$products = $categoryFilter
    ? array_values(array_filter($allProducts, fn($p) => (int)($p['category_id'] ?? 0) === $categoryFilter))
    : $allProducts;
$productCountByCategory = [];
foreach ($allProducts as $_p) {
    $productCountByCategory[(int)($_p['category_id'] ?? 0)] = ($productCountByCategory[(int)($_p['category_id'] ?? 0)] ?? 0) + 1;
}
$editProduct = $editId ? getProductById($editId) : null;

$pageTitle = 'Proizvodi';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container admin-page">
    <div class="admin-sidebar">
        <nav class="admin-nav">
            <div class="admin-nav-title">Manager</div>
            <a href="/manager/index.php" class="admin-nav-link">📊 Dashboard</a>
            <a href="/manager/products.php" class="admin-nav-link active">🍞 Proizvodi</a>
            <a href="/manager/promotions.php" class="admin-nav-link">🏷️ Akcije</a>
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
            <!-- Forma za proizvod -->
            <div class="admin-header">
                <h1><?= $action === 'edit' ? 'Uredi proizvod' : 'Novi proizvod' ?></h1>
                <a href="/manager/products.php" class="btn btn-outline">← Nazad</a>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><span>✗</span> <?= e($error) ?></div>
            <?php endif; ?>

            <div class="admin-form-card">
                <form method="POST" enctype="multipart/form-data" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
                    <input type="hidden" name="post_action" value="save_product">
                    <input type="hidden" name="product_id" value="<?= $editId ?>">

                    <div class="form-row">
                        <div class="form-group" style="flex:2">
                            <label class="form-label">Naziv proizvoda *</label>
                            <input type="text" name="name" class="form-input"
                                   value="<?= e($editProduct['name'] ?? $_POST['name'] ?? '') ?>"
                                   required placeholder="Npr. Bijeli hljeb 500g">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Kategorija</label>
                            <select name="category_id" class="form-input">
                                <option value="">— Bez kategorije —</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>"
                                        <?= ((int)($editProduct['category_id'] ?? $_POST['category_id'] ?? 0)) === (int)$cat['id'] ? 'selected' : '' ?>>
                                        <?= e($cat['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Opis</label>
                        <textarea name="description" class="form-input form-textarea" rows="3"
                                  placeholder="Kratki opis proizvoda..."><?= e($editProduct['description'] ?? $_POST['description'] ?? '') ?></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Cijena (KM) *</label>
                            <input type="number" name="price" class="form-input"
                                   step="0.10" min="0.10"
                                   value="<?= e($editProduct['price'] ?? $_POST['price'] ?? '') ?>"
                                   required placeholder="0.00">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Redoslijed</label>
                            <input type="number" name="sort_order" class="form-input"
                                   min="0"
                                   value="<?= e($editProduct['sort_order'] ?? $_POST['sort_order'] ?? '0') ?>">
                        </div>
                        <div class="form-group" style="display:flex;align-items:flex-end;padding-bottom:0.3rem">
                            <label class="checkbox-label">
                                <input type="checkbox" name="is_available" value="1"
                                       <?= ($editProduct ? $editProduct['is_available'] : 1) ? 'checked' : '' ?>>
                                <span>Dostupan</span>
                            </label>
                        </div>
                    </div>

                    <!-- Upload slike -->
                    <div class="form-group">
                        <label class="form-label">Slika proizvoda</label>
                        <?php if ($editProduct && $editProduct['image']): ?>
                            <div class="current-image-preview">
                                <img src="/uploads/products/<?= e($editProduct['image']) ?>"
                                     alt="Trenutna slika" style="max-width:120px;border-radius:8px">
                                <span class="text-muted text-sm">Trenutna slika (uploadujte novu za zamjenu)</span>
                            </div>
                        <?php endif; ?>
                        <div class="file-upload-area" id="fileUploadArea">
                            <input type="file" name="image" id="imageInput" accept="image/*"
                                   onchange="previewImage(this)" class="file-input-hidden">
                            <label for="imageInput" class="file-upload-label">
                                <span class="file-upload-icon">📷</span>
                                <span>Kliknite ili prevucite sliku</span>
                                <span class="text-muted text-sm">JPG, PNG, WebP • Max 5MB</span>
                            </label>
                            <div id="imagePreview" style="display:none;margin-top:0.5rem">
                                <img id="previewImg" style="max-width:150px;border-radius:8px">
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <?= $action === 'edit' ? '💾 Sačuvaj izmjene' : '➕ Dodaj proizvod' ?>
                        </button>
                        <a href="/manager/products.php" class="btn btn-outline">Odustani</a>
                    </div>
                </form>
            </div>

        <?php else: ?>
            <!-- Lista proizvoda -->
            <div class="admin-header">
                <h1>Proizvodi (<?= count($products) ?>)</h1>
                <a href="?action=create" class="btn btn-primary">+ Novi proizvod</a>
            </div>

            <div class="filter-group" style="margin-bottom:1rem">
                <a href="/manager/products.php" class="filter-chip <?= $categoryFilter === 0 ? 'active' : '' ?>">
                    Sve <span class="filter-count"><?= count($allProducts) ?></span>
                </a>
                <?php foreach ($categories as $cat): ?>
                    <?php $cnt = $productCountByCategory[(int)$cat['id']] ?? 0; ?>
                    <a href="?category=<?= (int)$cat['id'] ?>" class="filter-chip <?= $categoryFilter === (int)$cat['id'] ? 'active' : '' ?>">
                        <?= e($cat['name']) ?> <span class="filter-count"><?= $cnt ?></span>
                    </a>
                <?php endforeach; ?>
            </div>

            <?php if (empty($products)): ?>
                <div class="empty-state">
                    <span class="empty-icon">🍞</span>
                    <h3>Nema proizvoda</h3>
                    <a href="?action=create" class="btn btn-primary">Dodaj prvi proizvod</a>
                </div>
            <?php else: ?>
                <div class="products-manager-grid">
                    <?php foreach ($products as $p): ?>
                        <div class="product-manager-card <?= $p['is_available'] ? '' : 'product-inactive' ?>">
                            <div class="product-manager-img">
                                <img src="<?= e(productImageUrl($p['image'])) ?>"
                                     alt="<?= e($p['name']) ?>"
                                     onerror="this.src='/assets/img/no-image.svg'">
                                <?php if (!$p['is_available']): ?>
                                    <div class="product-manager-overlay">Nedostupan</div>
                                <?php endif; ?>
                            </div>
                            <div class="product-manager-info">
                                <h3><?= e($p['name']) ?></h3>
                                <span class="product-manager-category"><?= e($p['category_name'] ?? '—') ?></span>
                                <span class="product-manager-price"><?= formatPrice((float)$p['price']) ?></span>
                            </div>
                            <div class="product-manager-actions">
                                <a href="?action=edit&id=<?= $p['id'] ?>" class="btn btn-outline btn-xs">✏️ Uredi</a>

                                <form method="POST" style="display:inline">
                                    <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
                                    <input type="hidden" name="post_action" value="toggle_availability">
                                    <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                    <button class="btn btn-outline btn-xs">
                                        <?= $p['is_available'] ? '🔴 Skloni' : '🟢 Aktiviraj' ?>
                                    </button>
                                </form>

                                <form method="POST" style="display:inline"
                                      onsubmit="return confirm('Obrisati <?= e(addslashes($p['name'])) ?>?')">
                                    <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
                                    <input type="hidden" name="post_action" value="delete_product">
                                    <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                    <button class="btn btn-danger btn-xs">🗑️</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<script>
function previewImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            document.getElementById('previewImg').src = e.target.result;
            document.getElementById('imagePreview').style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

const uploadArea = document.getElementById('fileUploadArea');
if (uploadArea) {
    uploadArea.addEventListener('dragover', e => {
        e.preventDefault();
        uploadArea.classList.add('drag-over');
    });
    uploadArea.addEventListener('dragleave', () => uploadArea.classList.remove('drag-over'));
    uploadArea.addEventListener('drop', e => {
        e.preventDefault();
        uploadArea.classList.remove('drag-over');
        const file = e.dataTransfer.files[0];
        if (file) {
            document.getElementById('imageInput').files = e.dataTransfer.files;
            previewImage(document.getElementById('imageInput'));
        }
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
