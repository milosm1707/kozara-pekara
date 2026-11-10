<?php

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();

$user   = currentUser();
$orders = getUserOrders((int)$user['id']);
$userOrdersMeta = getUserOrdersRealtimeMeta((int)$user['id']);

$viewOrderId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$orderDetail = null;

if ($viewOrderId) {
    $orderDetail = getOrderDetails($viewOrderId);
    if ($orderDetail && (int)$orderDetail['user_id'] !== (int)$user['id']) {
        $orderDetail = null;
    }
}

$orderPromotionMap = [];
if ($orderDetail && !empty($orderDetail['items'])) {
    $orderPromotionMap = getPromotionMapByLinkedProductIds(array_column($orderDetail['items'], 'product_id'));
}

$pageTitle = 'Moje narudžbe';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container page-orders">
    <div class="page-header">
        <h1 class="page-title">Moje narudžbe</h1>
        <a href="/index.php" class="btn btn-primary">Naruči ponovo</a>
    </div>

    <?php if ($orderDetail): ?>
        <!-- Detalji narudžbe -->
        <div class="order-detail-wrap">
            <div class="order-detail-header">
                <a href="/orders.php" class="btn btn-outline btn-sm">← Nazad</a>
                <div>
                    <h2 class="order-detail-number"><?= e($orderDetail['order_number']) ?></h2>
                    <span class="order-status <?= orderStatusClass($orderDetail['status']) ?>">
                        <?= orderStatusLabel($orderDetail['status']) ?>
                    </span>
                </div>
            </div>

            <!-- Status timeline -->
            <div class="order-timeline">
                <?php
                $statuses = ['na_cekanju', 'u_pripremi', 'u_dostavi', 'isporuceno'];
                $currentIdx = array_search($orderDetail['status'], $statuses);
                foreach ($statuses as $idx => $s):
                    $isActive   = $idx <= $currentIdx;
                    $isCurrent  = $idx === $currentIdx;
                    $isCanceled = $orderDetail['status'] === 'otkazano';
                ?>
                    <div class="timeline-step <?= $isActive && !$isCanceled ? 'active' : '' ?> <?= $isCurrent && !$isCanceled ? 'current' : '' ?> <?= $isCanceled ? 'canceled' : '' ?>">
                        <div class="timeline-dot">
                            <?php if ($isActive && !$isCanceled): ?>
                                <?= $isCurrent ? '●' : '✓' ?>
                            <?php else: ?>
                                ○
                            <?php endif; ?>
                        </div>
                        <span class="timeline-label"><?= orderStatusLabel($s) ?></span>
                    </div>
                    <?php if ($idx < count($statuses) - 1): ?>
                        <div class="timeline-line <?= $idx < $currentIdx && !$isCanceled ? 'active' : '' ?>"></div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>

            <!-- Stavke -->
            <?php $itemsSubtotal = 0.0; foreach ($orderDetail['items'] as $it) { $itemsSubtotal += (float)$it['subtotal']; } $deliveryFee = isset($orderDetail['delivery_fee']) ? (float)$orderDetail['delivery_fee'] : 0.0; ?>

            <div class="order-detail-card">
                <h3>Naručeni proizvodi</h3>
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
                                <td class="text-center"><?= (int)$item['quantity'] ?></td>
                                <td class="text-right"><?= formatPrice((float)$item['product_price']) ?></td>
                                <td class="text-right"><strong><?= formatPrice((float)$item['subtotal']) ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="3" class="text-right"><strong>Međuzbir:</strong></td>
                            <td class="text-right"><?= formatPrice($itemsSubtotal) ?></td>
                        </tr>
                        <tr>
                            <td colspan="3" class="text-right"><strong>Dostava:</strong></td>
                            <td class="text-right">
                                <?php if ($deliveryFee > 0): ?>
                                    <?= formatPrice($deliveryFee) ?>
                                <?php else: ?>
                                    <span class="text-success">Besplatno</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="3" class="text-right"><strong>Ukupno:</strong></td>
                            <td class="text-right"><strong class="price-total"><?= formatPrice((float)$orderDetail['total_price']) ?></strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Detalji dostave -->
            <div class="order-detail-card">
                <h3>Podaci o dostavi</h3>
                <div class="order-meta-grid">
                    <div class="order-meta-item">
                        <span class="meta-label">Adresa:</span>
                        <span><?= e($orderDetail['delivery_address']) ?></span>
                    </div>
                    <div class="order-meta-item">
                        <span class="meta-label">Telefon:</span>
                        <span><?= e($orderDetail['delivery_phone']) ?></span>
                    </div>
                    <div class="order-meta-item">
                        <span class="meta-label">Email:</span>
                        <span><?= e($orderDetail['user_email']) ?></span>
                    </div>
                    <div class="order-meta-item">
                        <span class="meta-label">Plaćanje:</span>
                        <span>Pouzećem</span>
                    </div>
                    <div class="order-meta-item">
                        <span class="meta-label">Datum:</span>
                        <span><?= formatDateTimeApp($orderDetail['created_at']) ?></span>
                    </div>
                    <?php if ($orderDetail['note']): ?>
                        <div class="order-meta-item" style="grid-column: 1/-1">
                            <span class="meta-label">Napomena:</span>
                            <span><?= e($orderDetail['note']) ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    <?php elseif (empty($orders)): ?>
        <div class="empty-state">
            <span class="empty-icon">📦</span>
            <h3>Još nema narudžbi</h3>
            <p>Napravite prvu narudžbu i uživajte u svježim pekarskim proizvodima!</p>
            <a href="/index.php" class="btn btn-primary">Naruči sada</a>
        </div>

    <?php else: ?>
        <!-- Lista narudžbi -->
        <div class="orders-list">
            <?php foreach ($orders as $order): ?>
                <a href="/orders.php?id=<?= $order['id'] ?>" class="order-card">
                    <div class="order-card-header">
                        <div>
                            <span class="order-card-number"><?= e($order['order_number']) ?></span>
                            <span class="order-status <?= orderStatusClass($order['status']) ?>">
                                <?= orderStatusLabel($order['status']) ?>
                            </span>
                        </div>
                        <span class="order-card-date"><?= date('d.m.Y', strtotime($order['created_at'])) ?></span>
                    </div>
                    <div class="order-card-footer">
                        <span class="order-card-total"><?= formatPrice((float)$order['total_price']) ?></span>
                        <span class="order-card-arrow">→</span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php if (!empty($orders)): ?>
<script>
(function initUserOrdersRealtime() {
    if (!window.EventSource) return;

    let lastLatestId = <?= (int)$userOrdersMeta['latest_id'] ?>;
    let lastActivity = <?= (int)$userOrdersMeta['latest_activity'] ?>;

    const source = new EventSource('/user_orders_stream.php');

    source.addEventListener('user-orders-meta', function (event) {
        try {
            const meta = JSON.parse(event.data || '{}');
            if (!meta) return;

            const hasNewActivity = Number(meta.latest_activity || 0) > lastActivity;
            const hasNewOrder    = Number(meta.latest_id || 0) > lastLatestId;

            if (hasNewActivity || hasNewOrder) {
                if (typeof showToast === 'function') {
                    showToast('Status narudžbe je ažuriran. Osvježavam prikaz…', 'success', 2600);
                }
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

<?php require_once __DIR__ . '/includes/footer.php'; ?>
