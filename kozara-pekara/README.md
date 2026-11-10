# Kozara Pekara

Web aplikacija za online naručivanje iz pekare — meni, korpa, dostava ili preuzimanje, i paneli za osoblje koji prate porudžbine u realnom vremenu.
Produkcija: [kozarapek.com](https://kozarapek.com)

## Funkcionalnosti

**Za kupce**
- Meni po kategorijama (hljeb, pecivo, pite, pizza, kolači, gotova jela...)
- Korpa i checkout: dostava na adresu ili lično preuzimanje u izabrano vreme
- Automatski obračun dostave: besplatna iznad zadatog iznosa
- Naručivanje samo u radnom vremenu koje se podešava iz panela
- Registracija sa potvrdom emaila putem koda, prijava, reset lozinke
- Profil i istorija porudžbina sa statusom koji se osvežava uživo

**Za osoblje (uloge)**

| Uloga | Mogućnosti |
|---|---|
| `admin` | upravljanje korisnicima i ulogama, sve porudžbine |
| `manager` | proizvodi, kategorije, promocije, sadržaj početne strane, porudžbine, podešavanja dostave i radnog vremena |
| `pekar` | pregled porudžbina za pripremu i promena statusa |
| `user` | kupac |

Statusi porudžbine: `na_cekanju` → `u_pripremi` → `u_dostavi` → `isporuceno` (ili `otkazano`).
Nove porudžbine stižu osoblju bez osvežavanja stranice, preko Server-Sent Events (`orders_stream.php`).

## Tehnologije

| Sloj | Tehnologija |
|---|---|
| Backend | PHP 8.0+ (bez frameworka), PDO |
| Baza | MySQL / MariaDB |
| Frontend | HTML, CSS, vanilla JavaScript (korpa u `assets/js/cart.js`) |
| Real-time | Server-Sent Events |
| Email | PHP `mail()` ili SMTP preko PHPMailer-a (opcionalno, preko Composer-a) |
| Hosting | Hostinger (Apache) |

## Struktura projekta

```
├── index.php, cart.php, checkout.php, orders.php, profile.php ...   # javne stranice
├── admin/            # panel administratora
├── manager/          # panel menadžera (proizvodi, kategorije, promocije, početna)
├── pekar/            # panel pekara (porudžbine)
├── includes/
│   ├── config.example.php   # šablon podešavanja (commitovan)
│   ├── db.example.php       # šablon konekcije na bazu (commitovan)
│   ├── config.php, db.php   # prava podešavanja (NISU u gitu)
│   ├── auth.php, functions.php, mailer.php, orders_stream.php
│   └── header.php, footer.php
├── assets/           # CSS, JS, ikonice
├── images/           # statične slike sajta
├── uploads/          # slike proizvoda i promocija (NISU u gitu)
└── database/
    ├── schema.sql          # šema + kategorije i podrazumevana podešavanja
    └── seed_products.sql   # meni bez slika (opcionalno)
```

## Pokretanje lokalno

1. Kloniraj repozitorijum:
   ```bash
   git clone https://github.com/<korisnik>/kozara-pekara.git
   cd kozara-pekara
   ```
2. Napravi bazu i uvezi šemu (i, po želji, meni):
   ```bash
   mysql -u root -p -e "CREATE DATABASE pekara CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   mysql -u root -p pekara < database/schema.sql
   mysql -u root -p pekara < database/seed_products.sql
   ```
3. Napravi konfiguraciju iz šablona i popuni podatke:
   ```bash
   cp includes/db.example.php includes/db.php
   cp includes/config.example.php includes/config.php
   ```
4. Napravi admin nalog. Prvo generiši hash lozinke:
   ```bash
   php -r "echo password_hash('TvojaLozinka123!', PASSWORD_DEFAULT), PHP_EOL;"
   ```
   Zatim ga ubaci u bazu:
   ```sql
   INSERT INTO users (name, email, password, role, is_active, is_verified)
   VALUES ('Admin', 'admin@example.com', '<hash>', 'admin', 1, 1);
   ```
5. Pokreni PHP server i otvori `http://localhost:8000`:
   ```bash
   php -S localhost:8000
   ```

## Deploy na Hostinger

1. Upload fajlova u `public_html` (File Manager, FTP ili Hostinger Git integracija).
2. Na serveru napravi `includes/db.php` i `includes/config.php` sa produkcionim podacima: `BASE_URL`, SMTP, `FORCE_HTTPS_REDIRECT = true`.
3. Slike proizvoda se čuvaju samo na serveru, u `uploads/`. Pravi im backup odvojeno od gita.

## Bezbednost

- Lozinke heširane (`password_hash`), PDO prepared statements
- Provera uloga na svakoj stranici panela (`requireRole`, `requireStaff`)
- `.htaccess` blokira direktan pristup `includes/` i `.sql/.log/.env` fajlovima
- `uploads/` ne dozvoljava izvršavanje PHP-a
- Sigurnosni HTTP zaglavlja (`X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`)

## Autor

Miloš Milosavljević
