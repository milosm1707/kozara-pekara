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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Neispravan zahtjev.';
    } else {
        $email = $_POST['email'] ?? '';
        $result = requestPasswordReset($email);
        if ($result['success']) {
            $success = $result['message'];
        } else {
            $error = $result['message'];
        }
    }
}

$pageTitle = 'Zaboravljena lozinka';
require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-page">
    <div class="auth-card auth-card-wide">
        <div class="auth-header">
            <a href="/index.php" class="auth-logo">
                <img src="<?= asset_url('/images/kozara.png') ?>" alt="Kozara" class="auth-logo-img">
            </a>
            <h1 class="auth-title">Zaboravili ste lozinku?</h1>
            <p class="auth-subtitle">Unesite email adresu i poslaćemo vam uputstva za reset lozinke.</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><span>✗</span> <?= e($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><span>✓</span> <?= e($success) ?></div>
        <?php endif; ?>

        <form class="auth-form" method="POST" action="/forgot-password.php" novalidate>
            <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">

            <div class="form-group">
                <label for="email" class="form-label">Email adresa</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    class="form-input"
                    placeholder="vas@email.com"
                    value="<?= e($_POST['email'] ?? '') ?>"
                    required
                    autocomplete="email"
                >
            </div>

            <button type="submit" class="btn btn-primary btn-full btn-lg">
                Pošalji uputstva za reset lozinke
            </button>
        </form>

        <div class="auth-footer">
            <p>Zapamtili ste lozinku? <a href="/login.php">Prijavite se</a></p>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
