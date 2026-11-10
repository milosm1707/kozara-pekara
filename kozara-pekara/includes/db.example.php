<?php
// Kopiraj ovaj fajl u db.php i popuni podacima baze. db.php se NE commituje.
define('DB_HOST', 'localhost');
define('DB_NAME', 'ime_baze');
define('DB_USER', 'korisnik_baze');
define('DB_PASS', 'PROMENI_ME');
define('DB_CHARSET', 'utf8mb4');

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("DB Error: " . $e->getMessage());
            die(json_encode(['error' => 'Greška pri spajanju na bazu podataka.']));
        }
    }
    return $pdo;
}
