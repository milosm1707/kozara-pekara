<?php

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) {
    header('Location: /index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Neispravan zahtjev. Pokušajte ponovo.';
    } else {
        $result = loginUser($_POST['email'] ?? '', $_POST['password'] ?? '');

        if ($result['success']) {
            $redirect = $_SESSION['redirect_after_login'] ?? null;
            unset($_SESSION['redirect_after_login']);

            if ($redirect) {
                header("Location: $redirect");
            } elseif ($result['role'] === 'admin') {
                header('Location: /admin/index.php');
            } elseif ($result['role'] === 'manager') {
                header('Location: /manager/index.php');
            } else {
                header('Location: /index.php');
            }
            exit;
        } else {
            $error = $result['message'];
        }
    }
}

$pageTitle = 'Prijava';
require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-page">
    <div class="auth-card">
        <!-- Logo -->
        <div class="auth-header">
            <a href="/index.php" class="auth-logo">
                <img src="<?= asset_url('/images/kozara.png') ?>" alt="Kozara" class="auth-logo-img">
            </a>
            <h1 class="auth-title">Dobrodošli nazad</h1>
            <p class="auth-subtitle">Prijavite se na vaš nalog</p>
        </div>

        <!-- Greška -->
        <?php if ($error): ?>
            <div class="alert alert-error">
                <span>✗</span> <?= e($error) ?>
            </div>
        <?php endif; ?>

        <!-- Forma -->
        <form class="auth-form" method="POST" action="/login.php" novalidate>
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

            <div class="form-group">
                <label for="password" class="form-label">
                    Lozinka
                    <a href="/forgot-password.php" class="form-label-link">Zaboravili ste?</a>
                </label>
                <div class="input-password-wrap">
                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="form-input"
                        placeholder="Vaša lozinka"
                        required
                        autocomplete="current-password"
                    >
                    <button type="button" class="password-toggle" onclick="togglePassword('password')" aria-label="Prikaži lozinku">
                        <svg class="eye-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                        </svg>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-full btn-lg">
                Prijavite se
            </button>
        </form>

        <div class="auth-footer">
            <p>Nemate nalog? <a href="/register.php">Registrujte se</a></p>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
