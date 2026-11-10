<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

requireAdminOrManager();

@ini_set('zlib.output_compression', '0');
@ini_set('output_buffering', 'off');
@ini_set('implicit_flush', '1');

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$statusFilter = trim($_GET['status'] ?? '');
$allowedStatuses = ['na_cekanju', 'u_pripremi', 'u_dostavi', 'isporuceno', 'otkazano'];
if ($statusFilter !== '' && !in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = '';
}

$iterations = 0;
$maxIterations = 10;

while ($iterations < $maxIterations) {
    $meta = getOrdersRealtimeMeta($statusFilter);

    echo "event: orders-meta\n";
    echo 'data: ' . json_encode($meta, JSON_UNESCAPED_UNICODE) . "\n\n";

    if (function_exists('ob_get_level')) {
        while (ob_get_level() > 0) {
            @ob_end_flush();
        }
    }
    @flush();

    if (connection_aborted()) {
        break;
    }

    $iterations++;
    sleep(3);
}
