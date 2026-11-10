<?php

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) {
    header('Location: /index.php');
    exit;
}

$error = '';
$success = '';
$email = trim((string)($_SESSION['verify_email'] ?? $_GET['email'] ?? $_POST['email'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Neispravan zahtjev.';
    } else {
        $action = $_POST['action'] ?? 'verify';
        $email = trim((string)($_POST['email'] ?? $email));

        if ($action === 'resend') {
            $db = getDB();
            $stmt = $db->prepare("SELECT id, name, is_verified FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user) {
                $error = 'Korisnik sa ovom email adresom ne postoji.';
            } elseif ((int)$user['is_verified'] === 1) {
                $success = 'Email je već verifikovan. Možete se prijaviti.';
            } else {
                $sent = issueEmailVerificationCode((int)$user['id'], $email, (string)($user['name'] ?? ''));
                if ($sent) {
                    $success = 'Novi verifikacioni kod je poslat.';
                } else {
                    $error = 'Kod nije moguće poslati trenutno. Pokušajte ponovo kasnije.';
                }
            }
        } else {
            $result = verifyUserEmailCode($email, $_POST['code'] ?? '');
            if ($result['success']) {
                unset($_SESSION['verify_email']);
                setFlash('success', $result['message']);
                header('Location: /login.php');
                exit;
            }
            $error = $result['message'];
        }
    }
}

$pageTitle = 'Verifikacija emaila';
require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-page">
    <div class="auth-card">
        <div class="auth-header">
            <a href="/index.php" class="auth-logo">
                <img src="<?= asset_url('/images/kozara.png') ?>" alt="Kozara" class="auth-logo-img">
            </a>
            <h1 class="auth-title">Potvrdite email</h1>
            <p class="auth-subtitle">Unesite 6-cifreni kod koji je poslat na vašu email adresu.</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><span>✗</span> <?= e($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><span>✓</span> <?= e($success) ?></div>
        <?php endif; ?>

        <form class="auth-form" method="POST" action="/verify-email.php" novalidate>
            <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
            <input type="hidden" name="action" value="verify">

            <div class="form-group">
                <label class="form-label" for="email">Email</label>
                <input id="email" type="email" name="email" class="form-input" value="<?= e($email) ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="code">Verifikacioni kod</label>
                <input id="code" type="text" name="code" class="form-input" placeholder="Npr. 123456" minlength="6" maxlength="6" pattern="\d{6}" required>
            </div>

            <button type="submit" class="btn btn-primary btn-full btn-lg">Potvrdi kod</button>
        </form>

        <form class="auth-form" method="POST" action="/verify-email.php" style="margin-top:0.75rem">
            <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
            <input type="hidden" name="action" value="resend">
            <input type="hidden" name="email" value="<?= e($email) ?>">
            <button type="submit" class="btn btn-outline btn-full">Pošalji novi kod</button>
        </form>

        <div class="auth-footer">
            <p>Već ste verifikovali nalog? <a href="/login.php">Prijavite se</a></p>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
