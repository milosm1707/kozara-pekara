<?php

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdminOrManager();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['post_action'] ?? '') === 'update_delivery_settings') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Neispravan CSRF token.');
        header('Location: /manager/index.php');
        exit;
    }

    $feeRaw   = trim((string)($_POST['delivery_fee'] ?? ''));
    $freeRaw  = trim((string)($_POST['free_delivery_from'] ?? ''));

    $fee  = (float)str_replace(',', '.', $feeRaw);
    $free = (float)str_replace(',', '.', $freeRaw);

    if ($fee < 0 || $free < 0) {
        setFlash('error', 'Vrijednosti za dostavu ne mogu biti negativne.');
        header('Location: /manager/index.php');
        exit;
    }

    $db = getDB();
    $settings = getDeliverySettings();

    $stmt = $db->prepare("UPDATE delivery_settings SET delivery_fee = ?, free_delivery_from = ? WHERE id = ?");
    $stmt->execute([$fee, $free, (int)$settings['id']]);

    setFlash('success', 'Podešavanja dostave su ažurirana.');
    header('Location: /manager/index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['post_action'] ?? '') === 'update_ordering_hours') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Neispravan CSRF token.');
        header('Location: /manager/index.php');
        exit;
    }

    $openRaw  = trim((string)($_POST['open_hour'] ?? ''));
    $closeRaw = trim((string)($_POST['close_hour'] ?? ''));

    $openHour  = (int)$openRaw;
    $closeHour = (int)$closeRaw;

    if ($openHour < 0 || $openHour > 23 || $closeHour < 1 || $closeHour > 24) {
        setFlash('error', 'Radno vrijeme mora biti u opsegu 0–23 za početak i 1–24 za kraj.');
        header('Location: /manager/index.php');
        exit;
    }

    if ($closeHour <= $openHour) {
        setFlash('error', 'Vrijeme zatvaranja mora biti veće od vremena otvaranja.');
        header('Location: /manager/index.php');
        exit;
    }

    $db = getDB();
    try {
        $stmt = $db->query("SELECT id FROM ordering_settings ORDER BY id ASC LIMIT 1");
        $row = $stmt->fetch();

        if ($row) {
            $upd = $db->prepare("UPDATE ordering_settings SET open_hour = ?, close_hour = ? WHERE id = ?");
            $upd->execute([$openHour, $closeHour, (int)$row['id']]);
        } else {
            $ins = $db->prepare("INSERT INTO ordering_settings (open_hour, close_hour) VALUES (?, ?)");
            $ins->execute([$openHour, $closeHour]);
        }

        setFlash('success', 'Radno vrijeme naručivanja je ažurirano.');
    } catch (Throwable $e) {
        setFlash('error', 'Greška pri ažuriranju radnog vremena.');
    }

    header('Location: /manager/index.php');
    exit;
}

$db = getDB();

$stmt = $db->query("SELECT COUNT(*) as cnt FROM products WHERE is_available = 1");
$totalProducts = $stmt->fetch()['cnt'];

$stmt = $db->query("SELECT COUNT(*) as cnt FROM orders WHERE status = 'na_cekanju'");
$pendingOrders = $stmt->fetch()['cnt'];

$stmt = $db->query("SELECT COUNT(*) as cnt FROM orders WHERE status = 'u_pripremi'");
$preparingOrders = $stmt->fetch()['cnt'];

$stmt = $db->query("SELECT COUNT(*) as cnt FROM users");
$totalUsers = $stmt->fetch()['cnt'];

$recentOrders = array_slice(getAllOrders(), 0, 8);

$deliverySettings = getDeliverySettings();
$orderingHours    = getOrderingHours();

$pageTitle = 'Upravljanje';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container admin-page">
    <div class="admin-sidebar">
        <nav class="admin-nav">
            <div class="admin-nav-title">Manager</div>
            <a href="/manager/index.php" class="admin-nav-link active">📊 Dashboard</a>
            <a href="/manager/products.php" class="admin-nav-link">🍞 Proizvodi</a>
            <a href="/manager/promotions.php" class="admin-nav-link">🏷️ Akcije</a>
            <a href="/manager/categories.php" class="admin-nav-link">🗂️ Kategorije</a>
            <a href="/manager/homepage.php" class="admin-nav-link">💼 Oglasi za posao</a>
            <a href="/manager/orders.php" class="admin-nav-link">📦 Narudžbe</a>
            <?php if (hasRole('admin')): ?>
                <div class="admin-nav-divider"></div>
                <a href="/admin/index.php" class="admin-nav-link">⭐ Admin panel</a>
            <?php endif; ?>
            <div class="admin-nav-divider"></div>
            <a href="/index.php" class="admin-nav-link">🏠 Nazad na sajt</a>
        </nav>
    </div>

    <div class="admin-content">
        <div class="admin-header">
            <h1>Dashboard</h1>
            <span class="admin-role-badge admin-role-manager">Menadžer</span>
        </div>

        <div class="stats-grid">
            <div class="stat-card stat-card-highlight">
                <div class="stat-icon stat-icon-red">⏳</div>
                <div class="stat-body">
                    <div class="stat-value"><?= $pendingOrders ?></div>
                    <div class="stat-label">Na čekanju</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon stat-icon-orange">🔥</div>
                <div class="stat-body">
                    <div class="stat-value"><?= $preparingOrders ?></div>
                    <div class="stat-label">U pripremi</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon stat-icon-brown">🍞</div>
                <div class="stat-body">
                    <div class="stat-value"><?= $totalProducts ?></div>
                    <div class="stat-label">Aktivni proizvodi</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon stat-icon-purple">👥</div>
                <div class="stat-body">
                    <div class="stat-value"><?= $totalUsers ?></div>
                    <div class="stat-label">Korisnika ukupno</div>
                </div>
            </div>
        </div>

        <div class="admin-quick-actions">
            <a href="/manager/products.php?action=create" class="quick-action-btn">
                ➕ Novi proizvod
            </a>
            <a href="/manager/orders.php?status=na_cekanju" class="quick-action-btn">
                ⏳ Čekajuće narudžbe
            </a>
        </div>

        <div class="admin-form-card" style="max-width:480px;margin-top:1.5rem">
            <h2 style="margin-bottom:0.75rem">Podešavanje dostave</h2>
            <p class="text-muted" style="margin-bottom:0.75rem;font-size:0.9rem">
                Ako je ukupan iznos narudžbe manji od zadatog praga, obračunava se cijena dostave.
                Iznad praga dostava je besplatna.
            </p>
            <form method="POST" action="/manager/index.php" class="admin-inline-form" novalidate>
                <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
                <input type="hidden" name="post_action" value="update_delivery_settings">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Cijena dostave (KM)</label>
                        <input type="number" step="0.1" min="0" name="delivery_fee" class="form-input" value="<?= e($deliverySettings['delivery_fee']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Besplatna od iznosa (KM)</label>
                        <input type="number" step="0.1" min="0" name="free_delivery_from" class="form-input" value="<?= e($deliverySettings['free_delivery_from']) ?>" required>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-sm">Sačuvaj podešavanja</button>
            </form>
        </div>

        <div class="admin-form-card" style="max-width:480px;margin-top:1.5rem">
            <h2 style="margin-bottom:0.75rem">Radno vrijeme naručivanja</h2>
            <p class="text-muted" style="margin-bottom:0.75rem;font-size:0.9rem">
                Odredi u kom periodu tokom dana je moguće slati narudžbe. Van ovog opsega korisnici ne mogu završiti narudžbu.
            </p>
            <form method="POST" action="/manager/index.php" class="admin-inline-form" novalidate>
                <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
                <input type="hidden" name="post_action" value="update_ordering_hours">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Otvaranje (sat)</label>
                        <input type="number" name="open_hour" min="0" max="23" class="form-input" value="<?= e($orderingHours['open']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Zatvaranje (sat)</label>
                        <input type="number" name="close_hour" min="1" max="24" class="form-input" value="<?= e($orderingHours['close']) ?>" required>
                    </div>
                </div>
                <p class="text-sm text-muted" style="margin-top:0.35rem">Vrijeme se upisuje u cijelim satima (0–24). Trenutno: <?= e(getOrderingHoursLabel()) ?>.</p>
                <button type="submit" class="btn btn-primary btn-sm">Sačuvaj radno vrijeme</button>
            </form>
        </div>

        <div class="admin-table-section">
            <div class="admin-table-header">
                <h2>Posljednje narudžbe</h2>
                <a href="/manager/orders.php" class="btn btn-outline btn-sm">Sve</a>
            </div>

            <?php if (empty($recentOrders)): ?>
                <p class="empty-text">Nema narudžbi.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="admin-table orders-mobile-compact">
                        <thead>
                            <tr>
                                <th>Korisnik</th>
                                <th>Način</th>
                                <th>Status</th>
                                <th>Akcija</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentOrders as $order): ?>
                                <tr>
                                    <td><?= e($order['user_name']) ?></td>
                                    <td>
                                        <?php $type = $order['delivery_type'] ?? 'delivery'; ?>
                                        <?php if ($type === 'pickup'): ?>
                                            <span class="order-badge order-badge-pickup">LIČNO PREUZIMANJE</span>
                                        <?php else: ?>
                                            <span class="order-badge order-badge-delivery">DOSTAVA</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="order-status <?= orderStatusClass($order['status']) ?>"><?= orderStatusLabel($order['status']) ?></span>
                                    </td>
                                    <td>
                                        <a href="/manager/orders.php?id=<?= $order['id'] ?>" class="btn btn-outline btn-xs">Detalji</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
