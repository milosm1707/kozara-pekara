<?php
$pageTitle = 'O nama';
$pageDescription = 'Kozara pekara Prijedor — tradicija, kvalitet i svježi proizvodi. Više lokacija u gradu.';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container page-about">
    <div class="page-header">
        <h1 class="page-title">O nama</h1>
    </div>

    <section class="info-hero-card">
        <div>
            <p class="info-eyebrow">Tradicija + kvalitet</p>
            <h2>Svako jutro počinje sveže pečenim pecivom</h2>
            <p>
                Kozara je lokalna pekara koja već godinama priprema hljeb, peciva i kolače od pažljivo biranih sastojaka.
                Kombinujemo provjerene recepte i moderan pristup usluzi kako biste dobili isti kvalitet svaki dan.
            </p>
        </div>
        <div class="info-highlight">
            <strong>Radno vreme naručivanja</strong>
            <span><?= e(getOrderingHoursLabel()) ?></span>
            <small>Dostava i preuzimanje u okviru radnog vremena.</small>
        </div>
    </section>

    <section class="info-grid-3">
        <article class="info-card">
            <h3>🥖 Svježina bez kompromisa</h3>
            <p>Pečemo više puta dnevno kako bi proizvodi do vas stigli topli i ukusni.</p>
        </article>
        <article class="info-card">
            <h3>🌾 Kvalitetni sastojci</h3>
            <p>Koristimo proverene dobavljače i recepture bez nepotrebnih dodataka.</p>
        </article>
        <article class="info-card">
            <h3>🚚 Brza dostava</h3>
            <p>Narudžbe se obrađuju brzo, a status možete pratiti kroz svoj nalog.</p>
        </article>
    </section>

    <section class="info-split-card">
        <div>
            <h3>Naše lokacije</h3>
            <p class="text-muted">Kozara trenutno posluje na više lokacija u gradu. Ispod je pregled mapa i linkova:</p>

            <div class="locations-embed">
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
        </div>
        <div>
            <h3>Kako do nas</h3>
            <p>
                Klikom na jednu od lokacija otvoriće vam se Google mapa sa tačnom adresom.
                Možete odmah pokrenuti navigaciju i odabrati najbržu rutu do Kozara pekare.
            </p>
            <p>
                Boje naziva i linkova odgovaraju lokacijama na mapi, kako bi bilo lakše da ih razlikujete.
            </p>
            <p>
                Za dodatna pitanja u vezi lokacija ili radnog vremena,
                posetite stranicu <a href="/contact.php">Kontakt</a> ili nas pozovite.
            </p>
        </div>
    </section>

    <section class="info-split-card">
        <div>
            <h3>Kako radimo</h3>
            <ul class="info-list">
                <li>Priprema proizvoda po dnevnom planu i potražnji.</li>
                <li>Kontrola kvaliteta pre svakog slanja narudžbe.</li>
                <li>Fokus na pouzdanu i ljubaznu korisničku podršku.</li>
            </ul>
        </div>
        <div>
            <h3>Zašto nam se kupci vraćaju</h3>
            <p>
                Uvek imate jasan pregled ponude, jednostavno naručivanje i transparentan status vaše narudžbe.
                Naš cilj je da iskustvo bude brzo, prijatno i bez stresa.
            </p>
            <a href="/index.php" class="btn btn-primary" style="margin-top:0.9rem">Pogledaj meni</a>
        </div>
    </section>

    <section class="info-note">
        <p>
            Imate predlog ili posebnu narudžbu? Posetite stranicu <a href="/contact.php">Kontakt</a> i javićemo se u najkraćem roku.
        </p>
    </section>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
