<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

logoutUser();
setFlash('success', 'Uspješno ste se odjavili.');
header('Location: /index.php');
exit;
