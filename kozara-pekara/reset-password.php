<?php

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) {
    header('Location: /index.php');
    exit;
}

$error = '';

$tokenFromGet = $_GET['token'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Neispravan zahtjev.';
    } else {
        $token = $_POST['token'] ?? '';
        $password = $_POST['password'] ?? '';
        $passwordConfirm = $_POST['password_confirm'] ?? '';

        $result = resetPasswordWithToken($token, $password, $passwordConfirm);
        if ($result['success']) {
            setFlash('success', $result['message']);
            header('Location: /login.php');
            exit;
        } else {
            $error = $result['message'];
            $tokenFromGet = $token;
        }
    }
}

$pageTitle = 'Nova lozinka';
require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-page">
    <div class="auth-card auth-card-wide">
        <div class="auth-header">
            <a href="/index.php" class="auth-logo">
                <img src="<?= asset_url('/images/kozara.png') ?>" alt="Kozara" class="auth-logo-img">
            </a>
            <h1 class="auth-title">Postavite novu lozinku</h1>
            <p class="auth-subtitle">Unesite novu lozinku za vaš nalog.</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><span>✗</span> <?= e($error) ?></div>
        <?php endif; ?>

        <form class="auth-form" method="POST" action="/reset-password.php" novalidate>
            <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
            <input type="hidden" name="token" value="<?= e($tokenFromGet) ?>">

            <div class="form-row">
                <div class="form-group">
                    <label for="password" class="form-label">Nova lozinka *</label>
                    <div class="input-password-wrap">
                        <input type="password" id="password" name="password" class="form-input"
                               placeholder="Min. 8 karaktera, veliko slovo, broj i specijalan znak" required minlength="8">
                        <button type="button" class="password-toggle" onclick="togglePassword('password')" aria-label="Prikaži">
                            <svg class="eye-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                            </svg>
                        </button>
                    </div>
                </div>
                <div class="form-group">
                    <label for="password_confirm" class="form-label">Potvrda lozinke *</label>
                    <div class="input-password-wrap">
                        <input type="password" id="password_confirm" name="password_confirm" class="form-input"
                               placeholder="Ponovite lozinku" required>
                        <button type="button" class="password-toggle" onclick="togglePassword('password_confirm')" aria-label="Prikaži">
                            <svg class="eye-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <p class="form-help-text password-rules-text">
                Lozinka mora imati najmanje 8 karaktera, bar jedno veliko i malo slovo, jednu cifru i jedan specijalan znak
                (npr. !, ?, #, @).
            </p>

            <button type="submit" class="btn btn-primary btn-full btn-lg">
                Sačuvaj novu lozinku
            </button>
        </form>

        <div class="auth-footer">
            <p>Znate lozinku? <a href="/login.php">Prijavite se</a></p>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
