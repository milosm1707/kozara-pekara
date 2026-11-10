<?php

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdminOrManager();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Neispravan zahtjev.';
    } else {
        $action = $_POST['post_action'] ?? '';

        if ($action === 'create_category') {
            $result = createCategory($_POST);
            if ($result['success']) {
                setFlash('success', $result['message']);
                header('Location: /manager/categories.php');
                exit;
            }
            $error = $result['message'];
        }

        if ($action === 'delete_category') {
            $result = deleteCategory((int)($_POST['category_id'] ?? 0));
            setFlash($result['success'] ? 'success' : 'error', $result['message']);
            header('Location: /manager/categories.php');
            exit;
        }
    }
}

$categories = getCategoriesWithProductCount();
$pageTitle = 'Kategorije';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container admin-page">
    <div class="admin-sidebar">
        <nav class="admin-nav">
            <div class="admin-nav-title">Manager</div>
            <a href="/manager/index.php" class="admin-nav-link">📊 Dashboard</a>
            <a href="/manager/products.php" class="admin-nav-link">🍞 Proizvodi</a>
            <a href="/manager/promotions.php" class="admin-nav-link">🏷️ Akcije</a>
            <a href="/manager/categories.php" class="admin-nav-link active">🗂️ Kategorije</a>
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
        <div class="admin-header">
            <h1>Kategorije</h1>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><span>✗</span> <?= e($error) ?></div>
        <?php endif; ?>

        <div class="admin-form-card" style="max-width:680px">
            <h3>Dodaj novu kategoriju</h3>
            <form method="POST" novalidate>
                <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
                <input type="hidden" name="post_action" value="create_category">

                <div class="form-row">
                    <div class="form-group" style="flex:2">
                        <label class="form-label" for="name">Naziv kategorije *</label>
                        <input id="name" type="text" name="name" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="sort_order">Redosled</label>
                        <input id="sort_order" type="number" name="sort_order" class="form-input" value="0">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">Dodaj kategoriju</button>
            </form>
        </div>

        <div class="admin-table-section">
            <div class="admin-table-header">
                <h2>Postojeće kategorije (<?= count($categories) ?>)</h2>
            </div>

            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Naziv</th>
                            <th>Slug</th>
                            <th>Proizvoda</th>
                            <th>Akcija</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $category): ?>
                            <tr>
                                <td><strong><?= e($category['name']) ?></strong></td>
                                <td><code><?= e($category['slug']) ?></code></td>
                                <td><?= (int)$category['products_count'] ?></td>
                                <td>
                                    <?php if ((int)$category['products_count'] > 0): ?>
                                        <button class="btn btn-outline btn-xs" type="button" onclick="showToast('Kategoriju nije moguće obrisati dok postoje proizvodi u njoj.', 'error');">
                                            🚫 Obriši
                                        </button>
                                    <?php else: ?>
                                        <form method="POST" style="display:inline" onsubmit="return confirm('Obrisati kategoriju <?= e(addslashes($category['name'])) ?>?')">
                                            <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
                                            <input type="hidden" name="post_action" value="delete_category">
                                            <input type="hidden" name="category_id" value="<?= (int)$category['id'] ?>">
                                            <button class="btn btn-danger btn-xs" type="submit">🗑️ Obriši</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="toast" id="toast" role="status" aria-live="polite"></div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
