<?php

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

@ini_set('zlib.output_compression', '0');
@ini_set('output_buffering', 'off');
@ini_set('implicit_flush', '1');
@set_time_limit(0);

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$user   = currentUser();
$userId = (int)($user['id'] ?? 0);

if ($userId <= 0) {
    echo "event: user-orders-meta\n";
    echo 'data: ' . json_encode(['total' => 0, 'latest_id' => 0, 'latest_activity' => 0], JSON_UNESCAPED_UNICODE) . "\n\n";
    exit;
}

$iterations    = 0;
$maxIterations = 10;

while ($iterations < $maxIterations) {
    $meta = getUserOrdersRealtimeMeta($userId);

    echo "event: user-orders-meta\n";
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
