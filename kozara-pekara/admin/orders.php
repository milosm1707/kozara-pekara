<?php

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['post_action'] ?? '') === 'delete_order') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Neispravan CSRF token.');
        header('Location: /admin/orders.php');
        exit;
    }

    $orderId = (int)($_POST['order_id'] ?? 0);
    $result = deleteOrder($orderId);
    setFlash($result['success'] ? 'success' : 'error', $result['message']);
    header('Location: /admin/orders.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    header('Content-Type: application/json');
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Invalid token']);
        exit;
    }
    $success = updateOrderStatus((int)$_POST['order_id'], $_POST['status'] ?? '');
    echo json_encode(['success' => $success]);
    exit;
}

$statusFilter = $_GET['status'] ?? '';
$orderId      = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$orders      = getAllOrders($statusFilter);
$orderDetail = $orderId ? getOrderDetails($orderId) : null;
$ordersMeta  = getOrdersRealtimeMeta($statusFilter);
$orderPromotionMap = $orderDetail ? getPromotionMapByLinkedProductIds(array_column($orderDetail['items'], 'product_id')) : [];

$backHref = '/admin/orders.php' . ($statusFilter ? '?status=' . urlencode($statusFilter) : '');

$pageTitle = 'Narudžbe';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container admin-page staff-orders-page">
    <div class="admin-sidebar">
        <nav class="admin-nav">
            <div class="admin-nav-title">Admin</div>
            <a href="/admin/index.php" class="admin-nav-link">📊 Dashboard</a>
            <a href="/admin/users.php" class="admin-nav-link">👥 Korisnici</a>
            <a href="/admin/orders.php" class="admin-nav-link active">📦 Narudžbe</a>
            <a href="/manager/products.php" class="admin-nav-link">🍞 Proizvodi</a>
            <a href="/manager/promotions.php" class="admin-nav-link">🏷️ Akcije</a>
            <a href="/manager/categories.php" class="admin-nav-link">🗂️ Kategorije</a>
            <a href="/manager/homepage.php" class="admin-nav-link">💼 Oglasi za posao</a>
            <div class="admin-nav-divider"></div>
            <a href="/index.php" class="admin-nav-link">🏠 Nazad na sajt</a>
        </nav>
    </div>

    <div class="admin-content">
        <?php if ($orderDetail): ?>
            <div class="staff-detail">
                <div class="staff-detail-header">
                    <a href="<?= e($backHref) ?>" class="btn btn-outline btn-sm staff-back-btn">← Nazad na listu</a>

                    <div class="staff-detail-headline">
                        <h1 class="staff-detail-number">Narudžba #<?= e($orderDetail['order_number']) ?></h1>
                        <div class="staff-detail-sub">
                            <span class="order-status <?= orderStatusClass($orderDetail['status']) ?>">
                                <?= orderStatusLabel($orderDetail['status']) ?>
                            </span>
                            <span class="staff-detail-date">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                <?= formatDateTimeApp($orderDetail['created_at']) ?>
                            </span>
                        </div>
                    </div>

                    <div class="staff-status-changer">
                        <label for="statusSelect">Promijeni status</label>
                        <div class="staff-status-changer-row">
                            <select id="statusSelect" class="form-input">
                                <?php foreach (['na_cekanju','u_pripremi','u_dostavi','isporuceno','otkazano'] as $s): ?>
                                    <option value="<?= $s ?>" <?= $orderDetail['status'] === $s ? 'selected' : '' ?>>
                                        <?= orderStatusLabel($s) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button class="btn btn-primary" onclick="updateStatus(<?= $orderDetail['id'] ?>)">Sačuvaj</button>
                        </div>
                    </div>
                </div>

                <div class="staff-detail-grid">
                    <?php
                    $itemsSubtotal = 0.0;
                    foreach ($orderDetail['items'] as $it) { $itemsSubtotal += (float)$it['subtotal']; }
                    $deliveryFee  = isset($orderDetail['delivery_fee']) ? (float)$orderDetail['delivery_fee'] : 0.0;
                    $deliveryType = $orderDetail['delivery_type'] ?? 'delivery';
                    ?>

                    <div class="staff-card staff-card-items">
                        <h2 class="staff-card-title">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>
                            Naručeni proizvodi
                        </h2>
                        <div class="table-responsive">
                            <table class="order-items-table">
                                <thead>
                                    <tr>
                                        <th>Proizvod</th>
                                        <th class="text-center">Kol.</th>
                                        <th class="text-right">Cijena</th>
                                        <th class="text-right">Ukupno</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orderDetail['items'] as $item): ?>
                                        <tr>
                                            <td>
                                                <?= e($item['product_name']) ?>
                                                <?php if (!empty($orderPromotionMap[(int)$item['product_id']]['composition_label'])): ?>
                                                    <div class="text-muted text-sm" style="margin-top:0.2rem">
                                                        <?= e($orderPromotionMap[(int)$item['product_id']]['composition_label']) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center"><span class="staff-qty-chip">×<?= (int)$item['quantity'] ?></span></td>
                                            <td class="text-right"><?= formatPrice((float)$item['product_price']) ?></td>
                                            <td class="text-right"><strong><?= formatPrice((float)$item['subtotal']) ?></strong></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="staff-order-summary">
                            <div class="staff-summary-row">
                                <span>Međuzbir</span>
                                <span><?= formatPrice($itemsSubtotal) ?></span>
                            </div>
                            <div class="staff-summary-row">
                                <span>Dostava</span>
                                <span>
                                    <?php if ($deliveryFee > 0): ?>
                                        <?= formatPrice($deliveryFee) ?>
                                    <?php else: ?>
                                        <span class="text-success">Besplatno</span>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div class="staff-summary-row">
                                <span>Način preuzimanja</span>
                                <span>
                                    <?php if ($deliveryType === 'pickup'): ?>
                                        <span class="order-badge order-badge-pickup">LIČNO PREUZIMANJE</span>
                                    <?php else: ?>
                                        <span class="order-badge order-badge-delivery">DOSTAVA</span>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div class="staff-summary-row staff-summary-total">
                                <span>Ukupno za naplatu</span>
                                <span class="price-total"><?= formatPrice((float)$orderDetail['total_price']) ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="staff-card staff-card-delivery">
                        <h2 class="staff-card-title">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                            Podaci o dostavi
                        </h2>

                        <ul class="staff-info-list">
                            <li class="staff-info-row">
                                <span class="staff-info-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                </span>
                                <span class="staff-info-text">
                                    <span class="staff-info-label">Kupac</span>
                                    <span class="staff-info-value"><?= e($orderDetail['user_name']) ?></span>
                                </span>
                            </li>
                            <li class="staff-info-row">
                                <span class="staff-info-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16v16H4z" fill="none"/><path d="m22 6-10 7L2 6"/><path d="M2 6h20v12H2z"/></svg>
                                </span>
                                <span class="staff-info-text">
                                    <span class="staff-info-label">Email</span>
                                    <span class="staff-info-value"><a href="mailto:<?= e($orderDetail['user_email']) ?>"><?= e($orderDetail['user_email']) ?></a></span>
                                </span>
                            </li>
                            <?php if (!empty($orderDetail['delivery_phone'])): ?>
                            <li class="staff-info-row">
                                <span class="staff-info-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                                </span>
                                <span class="staff-info-text">
                                    <span class="staff-info-label">Telefon</span>
                                    <span class="staff-info-value"><a href="tel:<?= e($orderDetail['delivery_phone']) ?>"><?= e($orderDetail['delivery_phone']) ?></a></span>
                                </span>
                            </li>
                            <?php endif; ?>
                            <li class="staff-info-row">
                                <span class="staff-info-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0Z"/><circle cx="12" cy="10" r="3"/></svg>
                                </span>
                                <span class="staff-info-text">
                                    <span class="staff-info-label">Adresa</span>
                                    <span class="staff-info-value"><?= e($orderDetail['delivery_address']) ?></span>
                                </span>
                            </li>
                            <?php if (!empty($orderDetail['pickup_time'])): ?>
                            <li class="staff-info-row">
                                <span class="staff-info-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                </span>
                                <span class="staff-info-text">
                                    <span class="staff-info-label">Termin preuzimanja</span>
                                    <span class="staff-info-value"><?= e($orderDetail['pickup_time']) ?></span>
                                </span>
                            </li>
                            <?php endif; ?>
                        </ul>

                        <?php if (!empty($orderDetail['note'])): ?>
                            <div class="staff-note-callout">
                                <span class="staff-note-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                                </span>
                                <div>
                                    <span class="staff-info-label">Napomena kupca</span>
                                    <p class="staff-note-text"><?= e($orderDetail['note']) ?></p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <script>
            function updateStatus(orderId) {
                const status = document.getElementById('statusSelect').value;
                const saveBtn = document.querySelector('.staff-status-changer .btn.btn-primary');
                if (saveBtn) {
                    saveBtn.disabled = true;
                    saveBtn.textContent = 'Čuvam...';
                }

                fetch('/admin/orders.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `action=update_status&order_id=${orderId}&status=${status}&csrf_token=<?= getCsrfToken() ?>`
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        showToast('Status ažuriran!', 'success');
                        setTimeout(() => window.location.reload(), 500);
                    } else {
                        showToast('Greška pri ažuriranju.', 'error');
                        if (saveBtn) {
                            saveBtn.disabled = false;
                            saveBtn.textContent = 'Sačuvaj';
                        }
                    }
                })
                .catch(() => {
                    showToast('Greška mreže.', 'error');
                    if (saveBtn) {
                        saveBtn.disabled = false;
                        saveBtn.textContent = 'Sačuvaj';
                    }
                });
            }
            </script>

        <?php else: ?>
            <div class="staff-page-head">
                <div>
                    <h1 class="staff-page-title">Narudžbe</h1>
                    <p class="staff-page-subtitle">
                        <?= count($orders) ?> <?= count($orders) === 1 ? 'narudžba' : 'narudžbi' ?><?= $statusFilter ? ' sa statusom „' . orderStatusLabel($statusFilter) . '”' : ' ukupno' ?>
                    </p>
                </div>

                <?php if ($ordersMeta['pending_count'] > 0): ?>
                    <div class="staff-alert-banner">
                        <span class="staff-alert-dot"></span>
                        <?= $ordersMeta['pending_count'] ?> <?= $ordersMeta['pending_count'] === 1 ? 'narudžba čeka' : 'narudžbi čeka' ?> na obradu
                    </div>
                <?php endif; ?>
            </div>

            <div class="filter-group">
                <?php
                $statuses = ['' => 'Sve', 'na_cekanju' => 'Na čekanju', 'u_pripremi' => 'U pripremi', 'u_dostavi' => 'U dostavi', 'isporuceno' => 'Isporučeno', 'otkazano' => 'Otkazano'];
                foreach ($statuses as $val => $label):
                ?>
                    <a href="?status=<?= $val ?>" class="filter-chip <?= $statusFilter === $val ? 'active' : '' ?>"><?= $label ?></a>
                <?php endforeach; ?>
            </div>

            <?php if (empty($orders)): ?>
                <div class="staff-empty-state">
                    <span class="staff-empty-icon">📭</span>
                    <h3>Nema narudžbi za odabrani filter</h3>
                    <p>Nove narudžbe će se automatski pojaviti ovdje čim stignu.</p>
                </div>
            <?php else: ?>
                <div class="staff-orders-list">
                    <?php foreach ($orders as $order): ?>
                        <?php $type = $order['delivery_type'] ?? 'delivery'; ?>
                        <div class="staff-order-card no-arrow <?= orderStatusClass($order['status']) ?>">
                            <div class="staff-order-card-top">
                                <span class="staff-order-number">#<?= e($order['order_number']) ?></span>
                                <span class="staff-order-time">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                    <?= formatDateTimeApp($order['created_at']) ?>
                                </span>
                            </div>

                            <div class="staff-order-card-mid">
                                <span class="staff-order-line">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                    <?= e($order['user_name']) ?>
                                </span>
                                <?php if (!empty($order['delivery_phone'])): ?>
                                    <span class="staff-order-line">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                                        <?= e($order['delivery_phone']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <div class="staff-order-card-bottom">
                                <div class="staff-order-badges">
                                    <span class="order-status <?= orderStatusClass($order['status']) ?>"><?= orderStatusLabel($order['status']) ?></span>
                                    <?php if ($type === 'pickup'): ?>
                                        <span class="order-badge order-badge-pickup">Lično</span>
                                    <?php else: ?>
                                        <span class="order-badge order-badge-delivery">Dostava</span>
                                    <?php endif; ?>
                                </div>
                                <div class="staff-order-bottom-right">
                                    <span class="staff-order-total"><?= formatPrice((float)$order['total_price']) ?></span>
                                    <div class="staff-order-actions">
                                        <a href="?id=<?= $order['id'] ?><?= $statusFilter ? '&status=' . urlencode($statusFilter) : '' ?>" class="staff-action-btn staff-action-view" title="Detalji" aria-label="Detalji">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8Z"/><circle cx="12" cy="12" r="3"/></svg>
                                        </a>
                                        <form method="POST" class="staff-order-delete-form" onsubmit="return confirm('Obrisati ovu narudžbu?');">
                                            <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
                                            <input type="hidden" name="post_action" value="delete_order">
                                            <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">
                                            <button type="submit" class="staff-action-btn staff-action-delete" title="Obriši narudžbu" aria-label="Obriši narudžbu">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6m4-6v6"/><path d="M9 6V4h6v2"/></svg>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (!$orderDetail): ?>
        <script>
        (function initOrdersRealtime() {
            if (!window.EventSource) return;

            let lastLatestId = <?= (int)$ordersMeta['latest_id'] ?>;
            let lastActivity = <?= (int)$ordersMeta['latest_activity'] ?>;
            const streamUrl = '/orders_stream.php?status=<?= urlencode($statusFilter) ?>';

            const source = new EventSource(streamUrl);

            source.addEventListener('orders-meta', function (event) {
                try {
                    const meta = JSON.parse(event.data || '{}');
                    if (!meta) return;

                    const hasNewActivity = Number(meta.latest_activity || 0) > lastActivity;
                    const hasNewOrder = Number(meta.latest_id || 0) > lastLatestId;

                    if (hasNewActivity || hasNewOrder) {
                        showToast('Nova aktivnost na narudžbama. Osvježavam prikaz…', 'success', 2600);
                        setTimeout(function () {
                            window.location.reload();
                        }, 900);
                    }

                    lastLatestId = Number(meta.latest_id || lastLatestId);
                    lastActivity = Number(meta.latest_activity || lastActivity);
                } catch (err) {
                }
            });

            window.addEventListener('beforeunload', function () {
                source.close();
            });
        })();
        </script>
        <?php endif; ?>
    </div>
</div>

<div class="toast" id="toast" role="status" aria-live="polite"></div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
