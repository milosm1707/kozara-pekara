<?php

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

function getCartDeliveryMeta(): array {
    $subtotal = getCartTotal();
    $deliveryCalc = calculateDeliveryFee((float)$subtotal);
    $deliveryFee = (float)$deliveryCalc['fee'];
    $settings    = $deliveryCalc['settings'];

    $grandTotal = (float)$subtotal + $deliveryFee;

    return [
        'subtotal_raw'                 => (float)$subtotal,
        'grand_total_raw'              => $grandTotal,
        'delivery_fee_raw'             => $deliveryFee,
        'free_delivery_from_raw'       => (float)$settings['free_delivery_from'],
        'delivery_fee_base_raw'        => (float)$settings['delivery_fee'],

        'subtotal'                     => formatPrice($subtotal),
        'grand_total'                  => formatPrice($grandTotal),
        'delivery_label'               => $deliveryFee > 0 ? formatPrice($deliveryFee) : 'Besplatno',
        'free_delivery_from_formatted' => formatPrice((float)$settings['free_delivery_from']),
        'delivery_base_label'          => formatPrice((float)$settings['delivery_fee']),
        'is_free_delivery'             => $deliveryFee <= 0.00001,
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    if (!isLoggedIn()) {
        echo json_encode(['success' => false, 'message' => 'Niste prijavljeni.']);
        exit;
    }

    $action = $_POST['action'];

    switch ($action) {
        case 'add':
            $__cu = currentUser();
            if ($__cu && $__cu['role'] === 'pekar') {
                echo json_encode(['success' => false, 'message' => 'Vaša uloga (pekar) nema mogućnost naručivanja.']);
                break;
            }
            $result = addToCart((int)($_POST['product_id'] ?? 0), (int)($_POST['quantity'] ?? 1));
            $meta = getCartDeliveryMeta();
            $result = array_merge($result, [
                'total'                     => $meta['grand_total'],
                'subtotal'                  => $meta['subtotal'],
                'delivery_label'            => $meta['delivery_label'],
                'is_free_delivery'          => $meta['is_free_delivery'],
                'free_delivery_from'        => $meta['free_delivery_from_formatted'],
                'delivery_base_label'       => $meta['delivery_base_label'],
                'count'                     => getCartCount(),
            ]);
            echo json_encode($result);
            break;

        case 'remove':
            removeFromCart((int)($_POST['product_id'] ?? 0));
            $meta = getCartDeliveryMeta();
            echo json_encode([
                'success'                  => true,
                'count'                    => getCartCount(),
                'total'                    => $meta['grand_total'],
                'subtotal'                 => $meta['subtotal'],
                'delivery_label'           => $meta['delivery_label'],
                'is_free_delivery'         => $meta['is_free_delivery'],
                'free_delivery_from'       => $meta['free_delivery_from_formatted'],
                'delivery_base_label'      => $meta['delivery_base_label'],
            ]);
            break;

        case 'update':
            updateCartQuantity((int)($_POST['product_id'] ?? 0), (int)($_POST['quantity'] ?? 1));
            $meta = getCartDeliveryMeta();
            echo json_encode([
                'success'                  => true,
                'count'                    => getCartCount(),
                'total'                    => $meta['grand_total'],
                'subtotal'                 => $meta['subtotal'],
                'delivery_label'           => $meta['delivery_label'],
                'is_free_delivery'         => $meta['is_free_delivery'],
                'free_delivery_from'       => $meta['free_delivery_from_formatted'],
                'delivery_base_label'      => $meta['delivery_base_label'],
            ]);
            break;

        case 'count':
            echo json_encode(['count' => getCartCount()]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Nepoznata akcija.']);
    }
    exit;
}

$pageTitle = 'Korpa';
$cart      = getCart();
$total     = getCartTotal();
$meta      = getCartDeliveryMeta();
$isOpenNow = isOrderingOpen();
$hoursLabel = getOrderingHoursLabel();
$cartPromotionMap = getPromotionMapByLinkedProductIds(array_column($cart, 'product_id'));

require_once __DIR__ . '/includes/header.php';
?>

<div class="container page-cart page-cart-alt">
    <div class="page-header">
        <h1 class="page-title">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
                <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
            </svg>
            Nova korpa
        </h1>
        <div class="page-header-actions">
            <a href="/index.php" class="btn btn-outline">← Nastavi kupovinu</a>
        </div>
    </div>

    <?php if (empty($cart)): ?>
        <div class="empty-state">
            <span class="empty-icon">🛒</span>
            <h3>Korpa je prazna</h3>
            <p>Dodajte neke ukusne proizvode iz naše pekare!</p>
            <a href="/index.php" class="btn btn-primary">Pogledajte meni</a>
        </div>
    <?php else: ?>
        <div class="cart-layout cart-layout-alt">
            <!-- Lista stavki -->
            <div class="cart-items" id="cartItems">
                <?php foreach ($cart as $item): ?>
                    <article class="cart-item cart-item-alt" data-product-id="<?= $item['product_id'] ?>">
                        <header class="cart-item-head">
                            <div class="cart-item-image">
                                <img
                                    src="<?= e(productImageUrl($item['image'])) ?>"
                                    alt="<?= e($item['name']) ?>"
                                    loading="lazy"
                                    onerror="this.src='/assets/img/no-image.svg'"
                                >
                            </div>
                            <div class="cart-item-info">
                                <h3 class="cart-item-name"><?= e($item['name']) ?></h3>
                                <?php if (!empty($cartPromotionMap[(int)$item['product_id']]['composition_label'])): ?>
                                    <div class="text-muted text-sm" style="margin-top:0.2rem">
                                        <?= e($cartPromotionMap[(int)$item['product_id']]['composition_label']) ?>
                                    </div>
                                <?php endif; ?>
                                <span class="cart-item-price"><?= formatPrice((float)$item['price']) ?></span>
                            </div>
                            <button class="cart-item-remove" onclick="removeItem(<?= $item['product_id'] ?>)" aria-label="Ukloni">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6m4-6v6"/><path d="M9 6V4h6v2"/>
                                </svg>
                            </button>
                        </header>

                        <div class="cart-item-body">
                            <div class="cart-item-controls">
                                <button class="qty-btn" onclick="changeQty(<?= $item['product_id'] ?>, -1)" aria-label="Smanji">−</button>
                                <span class="qty-value" id="qty-<?= $item['product_id'] ?>"><?= (int)$item['quantity'] ?></span>
                                <button class="qty-btn" onclick="changeQty(<?= $item['product_id'] ?>, 1)" aria-label="Povećaj">+</button>
                            </div>
                            <div class="cart-item-subtotal" id="subtotal-<?= $item['product_id'] ?>">
                                <?= formatPrice($item['price'] * $item['quantity']) ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <!-- Sumarija -->
            <aside class="cart-summary cart-summary-alt">
                <div class="cart-summary-card">
                    <h2 class="cart-summary-title">Pregled narudžbe</h2>

                    <div class="cart-summary-row">
                        <span>Stavki:</span>
                        <span id="summaryCount"><?= getCartCount() ?></span>
                    </div>
                    <div class="cart-summary-row">
                        <span>Međuzbir:</span>
                        <span id="summarySubtotal"><?= $meta['subtotal'] ?></span>
                    </div>
                    <div class="cart-summary-row">
                        <span>Dostava:</span>
                        <span
                            id="summaryDelivery"
                            class="<?= $meta['is_free_delivery'] ? 'text-success' : '' ?>"
                        >
                            <?= $meta['delivery_label'] ?>
                        </span>
                    </div>
                    <div class="cart-summary-row cart-summary-total">
                        <span>Ukupno:</span>
                        <span id="summaryTotal"><?= $meta['grand_total'] ?></span>
                    </div>

                    <p
                        id="cartDeliveryNote"
                        class="cart-delivery-note text-sm <?= $meta['is_free_delivery'] ? 'text-success' : 'text-muted' ?>"
                        style="margin-top:0.5rem"
                    >
                        <?php if ($meta['is_free_delivery']): ?>
                            Vaša narudžba ispunjava uslov za besplatnu dostavu (iznad <?= $meta['free_delivery_from_formatted'] ?>).
                        <?php else: ?>
                            Za narudžbe ispod <?= $meta['free_delivery_from_formatted'] ?> naplaćuje se dostava <?= $meta['delivery_base_label'] ?>.
                        <?php endif; ?>
                    </p>

                    <div class="cart-payment-info">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/>
                        </svg>
                        Plaćanje pouzećem
                    </div>

                    <?php if (isLoggedIn()): ?>
                        <a href="/checkout.php" id="checkoutBtn" class="btn btn-primary btn-full btn-lg <?= !$isOpenNow ? 'btn-disabled' : '' ?>" data-ordering-open="<?= $isOpenNow ? '1' : '0' ?>" data-ordering-hours="<?= e($hoursLabel) ?>" aria-disabled="<?= $isOpenNow ? 'false' : 'true' ?>">
                            Naruči →
                        </a>
                        <?php if (!$isOpenNow): ?>
                            <p class="text-sm text-muted" style="margin-top:0.6rem">Kozara je trenutno zatvorena. Radno vreme: <?= e($hoursLabel) ?></p>
                        <?php endif; ?>
                    <?php else: ?>
                        <a href="/login.php" class="btn btn-primary btn-full btn-lg">
                            Prijavi se za narudžbu
                        </a>
                    <?php endif; ?>

                    <a href="/index.php" class="btn btn-outline btn-full" style="margin-top:0.75rem">
                        Nastavi kupovinu
                    </a>
                </div>
            </aside>
        </div>

        <script>
            const cartItemPrices = {
                <?php foreach ($cart as $item): ?>
                '<?= $item['product_id'] ?>': <?= (float)$item['price'] ?>,
                <?php endforeach; ?>
            };
        </script>
    <?php endif; ?>
</div>

<div class="toast" id="toast" role="status" aria-live="polite"></div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
