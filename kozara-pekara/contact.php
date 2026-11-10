<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Kontakt';
$pageDescription = 'Kontaktirajte Kozara pekaru — telefon, email, lokacije i kontakt forma.';

$error = '';
$success = '';
$topic = trim((string)($_GET['topic'] ?? $_POST['topic'] ?? 'general'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Neispravan zahtjev.';
    } else {
        $name = trim((string)($_POST['name'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $phone = trim((string)($_POST['phone'] ?? ''));
        $message = trim((string)($_POST['message'] ?? ''));

        if ($name === '' || $message === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Unesite ime, ispravan email i poruku.';
        } else {
            $subject = $topic === 'job' ? 'Prijava za posao preko sajta' : 'Nova kontakt poruka sa sajta';
            $body = "Ime: {$name}\n"
                . "Email: {$email}\n"
                . "Telefon: {$phone}\n"
                . "Tema: {$topic}\n\n"
                . "Poruka:\n{$message}\n";

            $recipients = getManagerNotificationEmails();
            $sent = sendPlainEmailToMany($recipients, $subject, $body);

            if ($sent > 0) {
                $success = 'Poruka je uspešno poslata. Javićemo vam se uskoro.';
            } else {
                $error = 'Poruku trenutno nije moguće poslati. Pokušajte kasnije.';
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="container page-contact">
    <div class="page-header">
        <h1 class="page-title">Kontakt</h1>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><span>✗</span> <?= e($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><span>✓</span> <?= e($success) ?></div>
    <?php endif; ?>

    <section class="info-hero-card">
        <div>
            <p class="info-eyebrow">Tu smo za vas</p>
            <h2>Imate pitanje, sugestiju ili posebnu porudžbinu?</h2>
            <p>
                Pišite nam ili pozovite — odgovaramo brzo i rado pomažemo oko izbora proizvoda,
                vremena isporuke i svih dodatnih informacija.
            </p>
        </div>
        <div class="info-highlight">
            <strong>Radno vreme</strong>
            <span><?= e(getOrderingHoursLabel()) ?></span>
            <small>Narudžbine se prihvataju u navedenom terminu.</small>
        </div>
    </section>

    <section class="info-grid-3">
        <article class="info-card">
            <h3>📍 Adresa</h3>
            <p>Kozara — više lokacija u gradu</p>
            <small class="text-muted">Preuzimanje uživo i podrška na licu mjesta.</small>
        </article>
        <article class="info-card">
            <h3>📞 Telefon</h3>
            <p><a href="tel:052233333">052 233 333</a></p>
            <small class="text-muted">Pozivi u toku radnog vremena.</small>
        </article>
        <article class="info-card">
            <h3>✉ Email</h3>
            <p><a href="mailto:info@kozara.ba">info@kozara.ba</a></p>
            <small class="text-muted">Za upite i poslovnu saradnju.</small>
        </article>
    </section>

    <div class="locations-grid">
        <div class="location-embed-card location-embed-card--green">
            <div class="location-map-frame">
                <iframe
                    src="https://www.google.com/maps?q=XPH6%2BM4G%2C%20Milo%C5%A1a%20Obrenovi%C4%87a%2C%20Prijedor%2C%20Bosnia%20and%20Herzegovina&output=embed"
                    loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade"
                    aria-label="Kozara 1 mapa"
                ></iframe>
            </div>
            <p class="location-label location-label--green"><strong>Kozara 1</strong></p>
            <a href="https://www.google.com/maps?q=XPH6%2BM4G%2C%20Milo%C5%A1a%20Obrenovi%C4%87a%2C%20Prijedor%2C%20Bosnia%20and%20Herzegovina" target="_blank" rel="noopener" class="location-link location-link--green">
                Otvori u Google mapama
            </a>
        </div>

        <div class="location-embed-card location-embed-card--blue">
            <div class="location-map-frame">
                <iframe
                    src="https://www.google.com/maps?q=Kozarska%2050%2C%20Prijedor%2C%20Bosnia%20and%20Herzegovina&output=embed"
                    loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade"
                    aria-label="Kozara 2 mapa"
                ></iframe>
            </div>
            <p class="location-label location-label--blue"><strong>Kozara 2</strong></p>
            <a href="https://www.google.com/maps?q=Kozarska%2050%2C%20Prijedor%2C%20Bosnia%20and%20Herzegovina" target="_blank" rel="noopener" class="location-link location-link--blue">
                Otvori u Google mapama
            </a>
        </div>

        <div class="location-embed-card location-embed-card--orange">
            <div class="location-map-frame">
                <iframe
                    src="https://www.google.com/maps?q=XPH7%2B634%2C%20Milo%C5%A1a%20Obrenovi%C4%87a%2C%20Prijedor%2C%20Bosnia%20and%20Herzegovina&output=embed"
                    loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade"
                    aria-label="Kozara 3 mapa"
                ></iframe>
            </div>
            <p class="location-label location-label--orange"><strong>Kozara 3</strong></p>
            <a href="https://www.google.com/maps?q=XPH7%2B634%2C%20Milo%C5%A1a%20Obrenovi%C4%87a%2C%20Prijedor%2C%20Bosnia%20and%20Herzegovina" target="_blank" rel="noopener" class="location-link location-link--orange">
                Otvori u Google mapama
            </a>
        </div>

        <div class="location-embed-card location-embed-card--purple">
            <div class="location-map-frame">
                <iframe
                    src="https://www.google.com/maps?q=Vo%C5%BEda%20Kara%C4%91or%C4%91a%2015%2C%20Prijedor%2C%20Bosnia%20and%20Herzegovina&output=embed"
                    loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade"
                    aria-label="Kozara 4 mapa"
                ></iframe>
            </div>
            <p class="location-label location-label--purple"><strong>Kozara 4</strong></p>
            <a href="https://www.google.com/maps?q=Vo%C5%BEda%20Kara%C4%91or%C4%91a%2015%2C%20Prijedor%2C%20Bosnia%20and%20Herzegovina" target="_blank" rel="noopener" class="location-link location-link--purple">
                Otvori u Google mapama
            </a>
        </div>
    </div>

    <section class="info-split-card">
        <div>
            <h3>Najčešća pitanja</h3>
            <ul class="info-list">
                <li>Kako mogu da pratim status narudžbe? — Kroz stranicu „Moje narudžbe“.</li>
                <li>Da li je dostava dostupna svaki dan? — Da, tokom radnog vremena.</li>
                <li>Mogu li izmeniti porudžbinu? — Kontaktirajte nas odmah nakon poručivanja.</li>
            </ul>
        </div>
        <div>
            <h3>Brzi linkovi</h3>
            <div class="form-actions" style="margin-top:0.5rem">
                <a href="/index.php" class="btn btn-primary">Pogledaj meni</a>
                <a href="/about.php" class="btn btn-outline">Saznaj više o nama</a>
            </div>
        </div>
    </section>

    <section class="info-split-card" style="margin-top:1rem">
        <div>
            <h3><?= $topic === 'job' ? 'Prijava za posao' : 'Kontakt forma' ?></h3>
            <p class="text-muted" style="margin-bottom:0.7rem">
                <?= $topic === 'job'
                    ? 'Popunite podatke i napišite zbog čega ste zainteresovani za posao.'
                    : 'Pošaljite pitanje, sugestiju ili zahtev i odgovorićemo što pre.' ?>
            </p>

            <form method="POST" class="auth-form" novalidate>
                <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
                <input type="hidden" name="topic" value="<?= e($topic) ?>">

                <div class="form-group">
                    <label class="form-label" for="name">Ime i prezime</label>
                    <input id="name" type="text" name="name" class="form-input" value="<?= e($_POST['name'] ?? '') ?>" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="email">Email</label>
                        <input id="email" type="email" name="email" class="form-input" value="<?= e($_POST['email'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="phone">Telefon</label>
                        <input id="phone" type="text" name="phone" class="form-input" value="<?= e($_POST['phone'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="message">Poruka</label>
                    <textarea id="message" name="message" class="form-input form-textarea" rows="5" required><?= e($_POST['message'] ?? '') ?></textarea>
                </div>

                <button type="submit" class="btn btn-primary">Pošalji poruku</button>
            </form>
        </div>
        <div>
            <h3>Napomena</h3>
            <ul class="info-list">
                <li>Odgovor obično stiže u roku od 24h.</li>
                <li>Za hitne informacije koristite telefonski kontakt.</li>
                <li>Prijave za posao prosleđuju se menadžment timu.</li>
            </ul>
        </div>
    </section>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
