<?php

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdminOrManager();

$action = $_GET['action'] ?? 'list';
$editId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error = '';
$forceType = 'job';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Neispravan zahtjev.';
    } else {
        $postAction = $_POST['post_action'] ?? '';

        if ($postAction === 'save_item') {
            $itemId = (int)($_POST['item_id'] ?? 0) ?: null;
            $_POST['item_type'] = $forceType;
            $result = saveHomepageItem($_POST, $itemId);
            if ($result['success']) {
                setFlash('success', $result['message']);
                header('Location: /manager/homepage.php');
                exit;
            }
            $error = $result['message'];
            $action = $itemId ? 'edit' : 'create';
            $editId = (int)$itemId;
        }

        if ($postAction === 'delete_item') {
            $result = deleteHomepageItem((int)($_POST['item_id'] ?? 0));
            setFlash($result['success'] ? 'success' : 'error', $result['message']);
            header('Location: /manager/homepage.php');
            exit;
        }
    }
}

$editItem = $editId ? getHomepageItemById($editId) : null;
$items = getHomepageItems('job', false);
$pageTitle = 'Homepage sadržaj';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container admin-page">
    <div class="admin-sidebar">
        <nav class="admin-nav">
            <div class="admin-nav-title">Manager</div>
            <a href="/manager/index.php" class="admin-nav-link">📊 Dashboard</a>
            <a href="/manager/products.php" class="admin-nav-link">🍞 Proizvodi</a>
            <a href="/manager/promotions.php" class="admin-nav-link">🏷️ Akcije</a>
            <a href="/manager/categories.php" class="admin-nav-link">🗂️ Kategorije</a>
            <a href="/manager/homepage.php" class="admin-nav-link active">💼 Oglasi za posao</a>
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
                <h1><?= $action === 'edit' ? 'Uredi oglas za posao' : 'Novi oglas za posao' ?></h1>
                <a href="/manager/homepage.php" class="btn btn-outline">← Nazad</a>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><span>✗</span> <?= e($error) ?></div>
            <?php endif; ?>

            <div class="admin-form-card" style="max-width:900px">
                <form method="POST" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
                    <input type="hidden" name="post_action" value="save_item">
                    <input type="hidden" name="item_id" value="<?= (int)$editId ?>">

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Tip stavke</label>
                            <input type="text" class="form-input" value="Oglas za posao" disabled>
                            <input type="hidden" name="item_type" value="job">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Redosled</label>
                            <input type="number" name="sort_order" class="form-input" value="<?= e($editItem['sort_order'] ?? $_POST['sort_order'] ?? '0') ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Naslov *</label>
                        <input type="text" name="title" class="form-input" value="<?= e($editItem['title'] ?? $_POST['title'] ?? '') ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Opis *</label>
                        <textarea name="description" class="form-input form-textarea" rows="4" required><?= e($editItem['description'] ?? $_POST['description'] ?? '') ?></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Badge (opciono)</label>
                            <input type="text" name="badge" class="form-input" value="<?= e($editItem['badge'] ?? $_POST['badge'] ?? '') ?>" placeholder="Npr. AKCIJA, NOVO, Potreban radnik">
                        </div>
                        <div class="form-group">
                            <label class="form-label">CTA tekst</label>
                            <input type="text" name="cta_text" class="form-input" value="<?= e($editItem['cta_text'] ?? $_POST['cta_text'] ?? '') ?>" placeholder="Npr. Iskoristi akciju">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Link za prijavu (kontakt forma)</label>
                        <input type="text" name="cta_url" class="form-input" value="<?= e($editItem['cta_url'] ?? $_POST['cta_url'] ?? '/contact.php?topic=job') ?>" placeholder="Npr. /contact.php?topic=job">
                    </div>

                    <label class="checkbox-label" style="margin:0.4rem 0 0.8rem 0">
                        <input type="checkbox" name="is_active" value="1" <?= (($editItem['is_active'] ?? 1) ? 'checked' : '') ?>>
                        <span>Aktivno i vidljivo na početnoj</span>
                    </label>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Sačuvaj stavku</button>
                        <a href="/manager/homepage.php" class="btn btn-outline">Odustani</a>
                    </div>
                </form>
            </div>

        <?php else: ?>
            <div class="admin-header">
                <h1>Oglasi za posao</h1>
                <a href="?action=create" class="btn btn-primary">+ Novi oglas</a>
            </div>

            <div class="table-responsive">
                <table class="admin-table jobs-table">
                    <thead>
                        <tr><th>Naslov</th><th>Status</th><th>Redosled</th><th>Akcija</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($items)): ?>
                            <tr><td colspan="4" class="text-muted">Nema oglasa za posao.</td></tr>
                        <?php else: ?>
                            <?php foreach ($items as $item): ?>
                                <tr>
                                    <td>
                                        <strong><?= e($item['title']) ?></strong>
                                        <div class="text-muted text-sm"><?= e($item['description']) ?></div>
                                    </td>
                                    <td><?= (int)$item['is_active'] === 1 ? '🟢 Aktivno' : '⚪ Neaktivno' ?></td>
                                    <td><?= (int)$item['sort_order'] ?></td>
                                    <td>
                                        <a href="?action=edit&id=<?= (int)$item['id'] ?>" class="btn btn-outline btn-xs">✏️ Uredi</a>
                                        <form method="POST" style="display:inline" onsubmit="return confirm('Obrisati ovu homepage stavku?')">
                                            <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
                                            <input type="hidden" name="post_action" value="delete_item">
                                            <input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>">
                                            <button type="submit" class="btn btn-danger btn-xs">🗑️</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
