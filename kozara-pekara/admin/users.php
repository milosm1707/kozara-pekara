<?php

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('admin');

$action = $_GET['action'] ?? 'list';
$error  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Neispravan zahtjev.';
    } else {
        $postAction = $_POST['post_action'] ?? '';

        switch ($postAction) {
            case 'create_manager':
                $result = createManager($_POST);
                if ($result['success']) {
                    setFlash('success', $result['message']);
                    header('Location: /admin/users.php');
                    exit;
                }
                $error  = $result['message'];
                $action = 'create';
                break;

            case 'toggle_status':
                $userId = (int)($_POST['user_id'] ?? 0);
                if (toggleUserStatus($userId)) {
                    setFlash('success', 'Status korisnika promijenjen.');
                } else {
                    setFlash('error', 'Greška ili nije dozvoljeno.');
                }
                header('Location: /admin/users.php');
                exit;

            case 'delete_user':
                $userId = (int)($_POST['user_id'] ?? 0);
                $result = deleteUser($userId);
                setFlash($result['success'] ? 'success' : 'error', $result['message']);
                header('Location: /admin/users.php');
                exit;
        }
    }
}

$users     = getAllUsers();
$pageTitle = 'Upravljanje korisnicima';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container admin-page">
    <div class="admin-sidebar">
        <nav class="admin-nav">
            <div class="admin-nav-title">Admin</div>
            <a href="/admin/index.php" class="admin-nav-link">📊 Dashboard</a>
            <a href="/admin/users.php" class="admin-nav-link active">👥 Korisnici</a>
            <a href="/admin/orders.php" class="admin-nav-link">📦 Narudžbe</a>
            <a href="/manager/products.php" class="admin-nav-link">🍞 Proizvodi</a>
            <a href="/manager/promotions.php" class="admin-nav-link">🏷️ Akcije</a>
            <a href="/manager/categories.php" class="admin-nav-link">🗂️ Kategorije</a>
            <a href="/manager/homepage.php" class="admin-nav-link">💼 Oglasi za posao</a>
            <div class="admin-nav-divider"></div>
            <a href="/index.php" class="admin-nav-link">🏠 Nazad na sajt</a>
        </nav>
    </div>

    <div class="admin-content">
        <div class="admin-header">
            <h1>Korisnici</h1>
            <?php if ($action !== 'create'): ?>
                <a href="?action=create" class="btn btn-primary">+ Novi zaposleni</a>
            <?php endif; ?>
        </div>

        <?php if ($action === 'create'): ?>
            <!-- Forma za novog zaposlenog -->
            <div class="admin-form-card">
                <h2>Kreiraj zaposlenog</h2>

                <?php if ($error): ?>
                    <div class="alert alert-error"><span>✗</span> <?= e($error) ?></div>
                <?php endif; ?>

                <form method="POST" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
                    <input type="hidden" name="post_action" value="create_manager">

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Ime *</label>
                            <input type="text" name="name" class="form-input"
                                   value="<?= e($_POST['name'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Email *</label>
                            <input type="email" name="email" class="form-input"
                                   value="<?= e($_POST['email'] ?? '') ?>" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Lozinka * (min. 8 karaktera)</label>
                            <input type="password" name="password" class="form-input" required minlength="8">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Rola *</label>
                            <select name="role" class="form-input">
                                <option value="manager" <?= ($_POST['role'] ?? '') === 'manager' ? 'selected' : '' ?>>Menadžer</option>
                                <option value="pekar" <?= ($_POST['role'] ?? '') === 'pekar' ? 'selected' : '' ?>>Pekar</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Kreiraj zaposlenog</button>
                        <a href="/admin/users.php" class="btn btn-outline">Odustani</a>
                    </div>
                </form>
            </div>

        <?php else: ?>
            <!-- Lista korisnika -->
            <div class="table-responsive">
                <table class="admin-table users-table">
                    <thead>
                        <tr>
                            <th>Korisnik</th>
                            <th>Rola</th>
                            <th>Status</th>
                            <th>Akcije</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                            <tr class="<?= $u['is_active'] ? '' : 'row-inactive' ?>">
                                <td>
                                    <strong><?= e($u['name']) ?></strong><br>
                                    <span class="text-sm text-muted" style="word-break: break-all;">
                                        <?= e($u['email']) ?>
                                    </span><br>
                                    <span class="text-sm text-muted">
                                        Registrovan: <?= formatDateTimeApp($u['created_at'], 'd.m.Y') ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="role-badge role-<?= $u['role'] ?>">
                                        <?php
                                        $roleLabels = ['admin' => '⭐ Admin', 'manager' => '🔑 Menadžer', 'pekar' => '🍞 Pekar', 'user' => '👤 Korisnik'];
                                        echo $roleLabels[$u['role']] ?? $u['role'];
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-dot <?= $u['is_active'] ? 'active' : 'inactive' ?>">
                                        <?= $u['is_active'] ? 'Aktivan' : 'Neaktivan' ?>
                                    </span>
                                </td>
                                <td class="table-actions">
                                    <?php if ($u['role'] !== 'admin'): ?>
                                        <!-- Toggle status -->
                                        <form method="POST" style="display:inline">
                                            <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
                                            <input type="hidden" name="post_action" value="toggle_status">
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <button type="submit" class="btn btn-outline btn-xs">
                                                <?= $u['is_active'] ? 'Deaktiviraj' : 'Aktiviraj' ?>
                                            </button>
                                        </form>

                                        <!-- Brisanje -->
                                        <form method="POST" style="display:inline" onsubmit="return confirm('Obrisati korisnika <?= e(addslashes($u['name'])) ?>?')">
                                            <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
                                            <input type="hidden" name="post_action" value="delete_user">
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <button type="submit" class="btn btn-danger btn-xs">Obriši</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
