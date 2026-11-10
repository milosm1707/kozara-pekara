<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/mailer.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 86400,
        'path'     => '/',
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

function currentUser(): ?array {
    if (empty($_SESSION['user_id']) || empty($_SESSION['session_token'])) {
        return null;
    }

    try {
        $db = getDB();
    } catch (Throwable $e) {
        return null;
    }

    $stmt = $db->prepare("SELECT id, name, email, role, is_active, session_token FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([(int)$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user || (int)$user['is_active'] !== 1) {
        logoutUser();
        return null;
    }

    if (empty($user['session_token']) || !hash_equals((string)$user['session_token'], (string)$_SESSION['session_token'])) {
        logoutUser();
        return null;
    }

    return [
        'id'    => (int)$user['id'],
        'name'  => (string)$user['name'],
        'email' => (string)$user['email'],
        'role'  => (string)$user['role'],
    ];
}

function isLoggedIn(): bool {
    return currentUser() !== null;
}

function hasRole(string $role): bool {
    if (!isLoggedIn()) return false;
    return $_SESSION['user_role'] === $role;
}

function isAdminOrManager(): bool {
    return hasRole('admin') || hasRole('manager');
}

function isPekar(): bool {
    return hasRole('pekar');
}

function isStaff(): bool {
    return hasRole('admin') || hasRole('manager') || hasRole('pekar');
}

function requireLogin(string $redirect = '/login.php'): void {
    if (!isLoggedIn()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header("Location: $redirect");
        exit;
    }
}

function requireRole(string $role, string $redirect = '/index.php'): void {
    requireLogin();
    if (!hasRole($role)) {
        $_SESSION['flash_error'] = 'Nemate dozvolu za pristup ovoj stranici.';
        header("Location: $redirect");
        exit;
    }
}

function requireAdminOrManager(): void {
    requireLogin();
    if (!isAdminOrManager()) {
        $_SESSION['flash_error'] = 'Nemate dozvolu za pristup.';
        header("Location: /index.php");
        exit;
    }
}

function requireStaff(): void {
    requireLogin();
    if (!isStaff()) {
        $_SESSION['flash_error'] = 'Nemate dozvolu za pristup.';
        header("Location: /index.php");
        exit;
    }
}

function loginUser(string $email, string $password): array {
    $db = getDB();

    $email = trim(strtolower($email));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Nevažeći format email adrese.'];
    }

    $stmt = $db->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        return ['success' => false, 'message' => 'Pogrešan email ili lozinka.'];
    }

    if ((int)$user['is_active'] !== 1) {
        return ['success' => false, 'message' => 'Nalog je deaktiviran.'];
    }

    if (array_key_exists('is_verified', $user) && (int)$user['is_verified'] !== 1) {
        return ['success' => false, 'message' => 'Nalog nije verifikovan. Molimo potvrdite kod sa email adrese.'];
    }

    $sessionToken = bin2hex(random_bytes(32));
    $update = $db->prepare("UPDATE users SET session_token = ?, session_token_issued_at = NOW() WHERE id = ?");
    $update->execute([$sessionToken, (int)$user['id']]);

    session_regenerate_id(true);
    $_SESSION['user_id']        = (int)$user['id'];
    $_SESSION['user_name']      = (string)$user['name'];
    $_SESSION['user_email']     = (string)$user['email'];
    $_SESSION['user_role']      = (string)$user['role'];
    $_SESSION['session_token']  = $sessionToken;

    return ['success' => true, 'role' => $user['role']];
}

function registerUser(array $data): array {
    $db = getDB();

    $name    = trim(htmlspecialchars($data['name']));
    $email   = trim(strtolower($data['email']));
    $phone   = trim($data['phone'] ?? '');
    $address = trim($data['address'] ?? '');

    if (empty($name) || strlen($name) < 2) {
        return ['success' => false, 'message' => 'Ime mora imati najmanje 2 karaktera.'];
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Nevažeći format email adrese.'];
    }
    $password = (string)($data['password'] ?? '');
    $passwordConfirm = (string)($data['password_confirm'] ?? '');

    $passwordError = validatePasswordStrength($password);
    if ($passwordError !== null) {
        return ['success' => false, 'message' => $passwordError];
    }
    if ($password !== $passwordConfirm) {
        return ['success' => false, 'message' => 'Lozinke se ne podudaraju.'];
    }

    $stmt = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        return ['success' => false, 'message' => 'Korisnik sa ovim emailom već postoji.'];
    }

    $hashedPassword = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

    $stmt = $db->prepare("INSERT INTO users (name, email, password, phone, address, role, is_verified) VALUES (?, ?, ?, ?, ?, 'user', 0)");
    $stmt->execute([$name, $email, $hashedPassword, $phone, $address]);

    $userId = (int)$db->lastInsertId();
    $mailSent = issueEmailVerificationCode($userId, $email, $name);

    return [
        'success' => true,
        'message' => $mailSent
            ? 'Registracija uspešna! Poslali smo verifikacioni kod na email adresu.'
            : 'Registracija uspešna! Kod nije moguće poslati trenutno, pokušajte ponovo slanje na verifikacionoj stranici.',
        'requires_verification' => true,
        'email' => $email,
    ];
}

function validatePasswordStrength(string $password): ?string {
    if (strlen($password) < 8) {
        return 'Lozinka mora imati najmanje 8 karaktera.';
    }

    if (!preg_match('/[A-Z]/', $password)) {
        return 'Lozinka mora sadržati bar jedno VELIKO slovo.';
    }

    if (!preg_match('/[a-z]/', $password)) {
        return 'Lozinka mora sadržati bar jedno malo slovo.';
    }

    if (!preg_match('/\d/', $password)) {
        return 'Lozinka mora sadržati bar jednu cifru (0-9).';
    }

    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        return 'Lozinka mora sadržati bar jedan specijalan znak (npr. !, ?, #, @).';
    }

    return null;
}

function requestPasswordReset(string $email): array {
    $db = getDB();
    $email = trim(strtolower($email));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Unesite ispravnu email adresu.'];
    }

    $stmt = $db->prepare("SELECT id, name, is_active FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    $genericSuccessMessage = 'Ako postoji nalog sa ovom adresom, poslali smo uputstva za reset lozinke.';

    if (!$user || (int)$user['is_active'] !== 1) {
        return ['success' => true, 'message' => $genericSuccessMessage];
    }

    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', time() + 3600);

    $update = $db->prepare("UPDATE users SET password_reset_token = ?, password_reset_expires_at = ? WHERE id = ?");
    $update->execute([$token, $expiresAt, (int)$user['id']]);

    $resetLink = base_url() . '/reset-password.php?token=' . urlencode($token);

    $subject = 'Reset lozinke - Kozara';
    $greetName = $user['name'] !== '' ? $user['name'] : 'Korisniče';
    $message = "Zdravo {$greetName},\n\n"
    . "Primili smo zahtjev za promjenu lozinke za vaš nalog na Kozara sajtu.\n\n"
        . "Za postavljanje nove lozinke posjetite sljedeći link (važi 1 sat):\n"
        . $resetLink . "\n\n"
        . "Ako niste vi tražili promjenu lozinke, ignorišite ovu poruku.\n";

    $sent = appSendPlainEmailToMany([$email], $subject, $message) > 0;

    if (!$sent) {
        return [
            'success' => false,
            'message' => 'Trenutno nije moguće poslati email za reset lozinke. Pokušajte ponovo kasnije.',
        ];
    }

    return ['success' => true, 'message' => $genericSuccessMessage];
}

function resetPasswordWithToken(string $token, string $password, string $passwordConfirm): array {
    $db = getDB();

    $token = trim($token);
    if ($token === '' || !preg_match('/^[A-Fa-f0-9]{64}$/', $token)) {
        return ['success' => false, 'message' => 'Neispravan ili istekao link za reset lozinke.'];
    }

    $stmt = $db->prepare("SELECT id, password_reset_expires_at FROM users WHERE password_reset_token = ? LIMIT 1");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if (!$user) {
        return ['success' => false, 'message' => 'Neispravan ili istekao link za reset lozinke.'];
    }

    if (empty($user['password_reset_expires_at']) || strtotime((string)$user['password_reset_expires_at']) < time()) {
        return ['success' => false, 'message' => 'Link za reset lozinke je istekao. Pošaljite novi zahtjev.'];
    }

    $passwordError = validatePasswordStrength($password);
    if ($passwordError !== null) {
        return ['success' => false, 'message' => $passwordError];
    }

    if ($password !== $passwordConfirm) {
        return ['success' => false, 'message' => 'Lozinke se ne podudaraju.'];
    }

    $hashed = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

    $update = $db->prepare("UPDATE users SET password = ?, password_reset_token = NULL, password_reset_expires_at = NULL, session_token = NULL, session_token_issued_at = NULL WHERE id = ?");
    $update->execute([$hashed, (int)$user['id']]);

    return ['success' => true, 'message' => 'Lozinka je uspješno promijenjena. Sada se možete prijaviti.'];
}

function issueEmailVerificationCode(int $userId, string $email, string $name = ''): bool {
    $db = getDB();
    $code = (string)random_int(100000, 999999);
    $expiresAt = date('Y-m-d H:i:s', time() + (15 * 60));

    $stmt = $db->prepare("UPDATE users SET email_verification_code = ?, email_verification_expires_at = ? WHERE id = ?");
    $stmt->execute([$code, $expiresAt, $userId]);

    $subject = 'Verifikacioni kod za registraciju';
    $greetName = $name !== '' ? $name : 'Korisniče';
    $message = "Zdravo {$greetName},\n\n"
        . "Vaš verifikacioni kod je: {$code}\n"
        . "Kod važi do: {$expiresAt}\n\n"
        . "Ako niste vi kreirali nalog, ignorišite ovu poruku.";

    return appSendPlainEmailToMany([$email], $subject, $message) > 0;
}

function verifyUserEmailCode(string $email, string $code): array {
    $db = getDB();
    $email = trim(strtolower($email));
    $code = trim($code);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Neispravan email.'];
    }

    if (!preg_match('/^\d{6}$/', $code)) {
        return ['success' => false, 'message' => 'Kod mora imati 6 cifara.'];
    }

    $stmt = $db->prepare("SELECT id, is_verified, email_verification_code, email_verification_expires_at, name FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        return ['success' => false, 'message' => 'Korisnik ne postoji.'];
    }

    if ((int)$user['is_verified'] === 1) {
        return ['success' => true, 'message' => 'Email je već verifikovan. Možete se prijaviti.'];
    }

    if (empty($user['email_verification_code']) || empty($user['email_verification_expires_at'])) {
        return ['success' => false, 'message' => 'Kod nije pronađen. Zatražite novo slanje koda.'];
    }

    if (strtotime((string)$user['email_verification_expires_at']) < time()) {
        return ['success' => false, 'message' => 'Kod je istekao. Zatražite novi kod.'];
    }

    if (!hash_equals((string)$user['email_verification_code'], $code)) {
        return ['success' => false, 'message' => 'Pogrešan verifikacioni kod.'];
    }

    $update = $db->prepare("UPDATE users SET is_verified = 1, email_verification_code = NULL, email_verification_expires_at = NULL WHERE id = ?");
    $update->execute([(int)$user['id']]);

    return ['success' => true, 'message' => 'Email je uspešno verifikovan. Sada se možete prijaviti.'];
}

function logoutUser(): void {
    $userId = $_SESSION['user_id'] ?? null;

    if ($userId) {
        try {
            $db = getDB();
            $stmt = $db->prepare("UPDATE users SET session_token = NULL, session_token_issued_at = NULL WHERE id = ?");
            $stmt->execute([(int)$userId]);
        } catch (Throwable $e) {
        }
    }

    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_unset();
        session_destroy();
    }

    session_start();
    session_regenerate_id(true);
}

function setFlash(string $type, string $message): void {
    $_SESSION['flash_' . $type] = $message;
}

function getFlash(string $type): ?string {
    $key = 'flash_' . $type;
    if (isset($_SESSION[$key])) {
        $msg = $_SESSION[$key];
        unset($_SESSION[$key]);
        return $msg;
    }
    return null;
}
