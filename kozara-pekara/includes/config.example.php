<?php
// Kopiraj ovaj fajl u config.php i prilagodi. config.php se NE commituje.

define('BASE_URL', 'http://localhost:8000');

define('APP_TIMEZONE', 'Europe/Belgrade');

define('ORDERING_OPEN_HOUR', 0);
define('ORDERING_CLOSE_HOUR', 24);

define('MANAGER_NOTIFICATION_EMAIL', '');

define('MAIL_FROM_ADDRESS', 'no-reply@localhost');
define('MAIL_FROM_NAME', 'Kozara');

define('SMTP_ENABLED', false);
define('SMTP_HOST', 'smtp.hostinger.com');
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'tls');
define('SMTP_USERNAME', '');
define('SMTP_PASSWORD', '');

define('FORCE_HTTPS_REDIRECT', false); // na produkciji: true
