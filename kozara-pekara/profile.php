<?php

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin('/login.php');

$user = currentUser();
$error = '';
$success = '';

$db = getDB();
$stmt = $db->prepare("SELECT name, email, phone, address, role, created_at FROM users WHERE id = ? LIMIT 1");
$stmt->execute([(int)$user['id']]);
$userDetails = $stmt->fetch() ?: $user;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Neispravan zahtjev.';
    } else {
        $action = $_POST['action'] ?? '';

        switch ($action) {
            case 'change_password':
                $newPassword = $_POST['new_password'] ?? '';
                $newPasswordConfirm = $_POST['new_password_confirm'] ?? '';

                if ($newPassword !== $newPasswordConfirm) {
                    $error = 'Nova lozinka i potvrda lozinke se ne podudaraju.';
                } else {
                    $result = updateUserPassword((int)$user['id'], $_POST['current_password'] ?? '', $newPassword);
                    if ($result['success']) {
                        logoutUser();
                        setFlash('success', $result['message']);
                        header('Location: /login.php');
                        exit;
                    } else {
                        $error = $result['message'];
                    }
                }
                break;

            case 'deactivate':
                if (deactivateUser((int)$user['id'])) {
                    logoutUser();
                    setFlash('success', 'Nalog je deaktiviran.');
                    header('Location: /index.php');
                    exit;
                } else {
                    $error = 'Neuspjeh pri deaktivaciji naloga.';
                }
                break;

            case 'delete_account':
                $result = deleteUser((int)$user['id']);
                if ($result['success']) {
                    logoutUser();
                    setFlash('success', 'Nalog je obrisan.');
                    header('Location: /index.php');
                    exit;
                } else {
                    $error = $result['message'];
                }
                break;
        }
    }
}

$pageTitle = 'Moj profil';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container page-profile">
    <div class="page-header">
        <h1 class="page-title">Moj profil</h1>
        <a href="<?= $user['role'] === 'pekar' ? '/pekar/orders.php' : '/index.php' ?>" class="btn btn-outline">← Nazad</a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><span>✗</span> <?= e($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><span>✓</span> <?= e($success) ?></div>
    <?php endif; ?>

    <section class="profile-hero-card">
        <div>
            <p class="info-eyebrow">Korisnički panel</p>
            <h2><?= e($userDetails['name'] ?? $user['name']) ?></h2>
            <p class="text-muted">Ovde možete upravljati bezbednošću naloga i ličnim podešavanjima.</p>
        </div>
        <div class="profile-hero-meta">
            <div><span>Email</span><strong><?= e($userDetails['email'] ?? $user['email']) ?></strong></div>
            <div><span>Rola</span><strong><?= e(ucfirst($userDetails['role'] ?? $user['role'])) ?></strong></div>
        </div>
    </section>

    <div class="profile-grid">
        <div class="profile-card profile-card-account">
            <h2>Detalji naloga</h2>
            <div class="order-meta-grid">
                <div class="order-meta-item">
                    <span class="meta-label">Ime</span>
                    <span><?= e($userDetails['name'] ?? $user['name']) ?></span>
                </div>
                <div class="order-meta-item">
                    <span class="meta-label">Email</span>
                    <span><?= e($userDetails['email'] ?? $user['email']) ?></span>
                </div>
                <div class="order-meta-item">
                    <span class="meta-label">Tip naloga</span>
                    <span><?= e($userDetails['role'] ?? $user['role']) ?></span>
                </div>
                <div class="order-meta-item">
                    <span class="meta-label">Telefon</span>
                    <span><?= e($userDetails['phone'] ?? '—') ?></span>
                </div>
                <div class="order-meta-item" style="grid-column:1/-1">
                    <span class="meta-label">Adresa</span>
                    <span><?= e($userDetails['address'] ?? '—') ?></span>
                </div>
                <div class="order-meta-item">
                    <span class="meta-label">Registrovan</span>
                    <span><?= formatDateTimeApp($userDetails['created_at'] ?? null) ?></span>
                </div>
            </div>
        </div>

        <div class="profile-card">
            <h2>Promeni lozinku</h2>
            <p class="text-muted text-sm" style="margin-bottom:0.75rem">Koristite najmanje 8 karaktera i kombinujte slova, brojeve i specijalne znakove.</p>
            <form method="POST" novalidate>
                <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
                <input type="hidden" name="action" value="change_password">

                <div class="form-group">
                    <label class="form-label" for="current_password">Trenutna lozinka</label>
                    <input id="current_password" type="password" name="current_password" class="form-input" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="new_password">Nova lozinka</label>
                    <input id="new_password" type="password" name="new_password" class="form-input" required minlength="8">
                </div>

                <div class="form-group">
                    <label class="form-label" for="new_password_confirm">Potvrdi novu lozinku</label>
                    <input id="new_password_confirm" type="password" name="new_password_confirm" class="form-input" required minlength="8">
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Sačuvaj novu lozinku</button>
                </div>
            </form>
        </div>

        <div class="profile-card profile-card-danger">
            <h2>Deaktivacija / brisanje naloga</h2>
            <p>
                Deaktivacijom se nalog privremeno gasi. Brisanjem se nalog trajno uklanja.
                Ove akcije zahtevaju novu prijavu ili registraciju za dalje korišćenje aplikacije.
            </p>

            <div class="alert alert-info" style="margin:0.75rem 0 1rem 0">
                <span>ℹ</span>
                Preporuka: pre brisanja naloga proverite svoje aktivne narudžbe.
            </div>

            <form method="POST" onsubmit="return confirm('Da li ste sigurni da želite deaktivirati nalog?');" style="display:inline-block; margin-right:0.45rem; margin-bottom:0.45rem">
                <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
                <input type="hidden" name="action" value="deactivate">
                <button type="submit" class="btn btn-outline">Deaktiviraj nalog</button>
            </form>

            <form method="POST" onsubmit="return confirm('Da li ste sigurni da želite trajno obrisati nalog? Ova akcija je nepovratna.');" style="display:inline-block; margin-bottom:0.45rem">
                <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
                <input type="hidden" name="action" value="delete_account">
                <button type="submit" class="btn btn-danger">Obriši nalog</button>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
