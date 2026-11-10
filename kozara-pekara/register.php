<?php

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) {
    header('Location: /index.php');
    exit;
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Neispravan zahtjev.';
    } else {
        $result = registerUser($_POST);
        if ($result['success']) {
            $_SESSION['verify_email'] = $result['email'] ?? ($_POST['email'] ?? '');
            setFlash('success', $result['message']);
            header('Location: /verify-email.php');
            exit;
        } else {
            $error = $result['message'];
        }
    }
}

$pageTitle = 'Registracija';
require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-page">
    <div class="auth-card auth-card-wide">
        <div class="auth-header">
            <a href="/index.php" class="auth-logo">
                <img src="<?= asset_url('/images/kozara.png') ?>" alt="Kozara" class="auth-logo-img">
            </a>
            <h1 class="auth-title">Kreirajte nalog</h1>
            <p class="auth-subtitle">Registrujte se i naručite odmah</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><span>✗</span> <?= e($error) ?></div>
        <?php endif; ?>

        <form class="auth-form" method="POST" action="/register.php" novalidate>
            <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">

                <div class="form-row">
                    <div class="form-group">
                        <label for="name" class="form-label">Puno ime *</label>
                        <input type="text" id="name" name="name" class="form-input"
                               placeholder="Ime i prezime"
                               value="<?= e($_POST['name'] ?? '') ?>" required minlength="2">
                    </div>
                    <div class="form-group">
                        <label for="phone" class="form-label">Telefon</label>
                        <input type="tel" id="phone" name="phone" class="form-input"
                               placeholder="052 233 333 ili mobilni"
                               value="<?= e($_POST['phone'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="email" class="form-label">Email adresa *</label>
                    <input type="email" id="email" name="email" class="form-input"
                           placeholder="vas@email.com"
                           value="<?= e($_POST['email'] ?? '') ?>" required>
                </div>

                <div class="form-group">
                    <label for="address" class="form-label">Adresa dostave</label>
                    <input type="text" id="address" name="address" class="form-input"
                           placeholder="Ulica i broj, grad"
                           value="<?= e($_POST['address'] ?? '') ?>">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="password" class="form-label">Lozinka *</label>
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

                <!-- Jačina lozinke -->
                <div class="password-strength" id="passwordStrength">
                    <div class="password-strength-bar">
                        <div class="password-strength-fill" id="strengthFill"></div>
                    </div>
                    <span class="password-strength-label" id="strengthLabel"></span>
                </div>

                <p class="form-help-text password-rules-text">
                    Lozinka mora imati najmanje 8 karaktera, bar jedno veliko i malo slovo, jednu cifru i jedan specijalan znak
                    (npr. !, ?, #, @).
                </p>

                <button type="submit" class="btn btn-primary btn-full btn-lg">
                    Kreirajte nalog
                </button>

                <p class="form-terms">
                    Registracijom prihvatate naše uslove korištenja i politiku privatnosti.
                </p>
        </form>

        <div class="auth-footer">
            <p>Već imate nalog? <a href="/login.php">Prijavite se</a></p>
        </div>
    </div>
</div>

<script>
document.getElementById('password').addEventListener('input', function() {
    const val = this.value;
    let strength = 0;
    if (val.length >= 8) strength++;
    if (/[a-z]/.test(val)) strength++;
    if (/[A-Z]/.test(val)) strength++;
    if (/[0-9]/.test(val)) strength++;
    if (/[^A-Za-z0-9]/.test(val)) strength++;

    const fill  = document.getElementById('strengthFill');
    const label = document.getElementById('strengthLabel');
    const labels = ['', 'Vrlo slaba', 'Slaba', 'Osrednja', 'Dobra', 'Odlična'];
    const colors = ['', '#ef4444', '#f97316', '#eab308', '#22c55e', '#22c55e'];

    const maxScore = 5;
    fill.style.width  = (strength / maxScore * 100) + '%';
    fill.style.background = colors[strength] || '#e5e7eb';
    label.textContent = labels[strength] || '';
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
