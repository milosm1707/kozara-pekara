<?php

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin('/login.php');

$user = currentUser();

if ($user && $user['role'] === 'pekar') {
    setFlash('error', 'Vaša uloga (pekar) nema mogućnost naručivanja.');
    header('Location: /pekar/orders.php');
    exit;
}

$cart = getCart();
if (empty($cart)) {
    setFlash('info', 'Korpa je prazna. Dodajte proizvode prije naručivanja.');
    header('Location: /index.php');
    exit;
}

$isOpen = isOrderingOpen();
$hoursLabel = getOrderingHoursLabel();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isOpen) {
    setFlash('error', 'Kozara je trenutno zatvorena. Radno vreme: ' . $hoursLabel . '.');
    header('Location: /cart.php');
    exit;
}

$error = '';

$db = getDB();
$stmt = $db->prepare("SELECT id, name, email, phone, address, role FROM users WHERE id = ?");
$stmt->execute([$user['id']]);
$userData = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Neispravan zahtjev.';
    } else {
        $deliveryType = $_POST['delivery_type'] ?? '';
        if ($deliveryType !== 'delivery' && $deliveryType !== 'pickup') {
            $error = 'Molimo odaberite da li želite dostavu ili lično preuzimanje.';
        } else {
            $result = createOrder((int)$user['id'], $cart, [
                'address'       => $_POST['address'] ?? '',
                'phone'         => $_POST['phone'] ?? '',
                'note'          => $_POST['note'] ?? '',
                'delivery_type' => $deliveryType,
                'pickup_time'   => $_POST['pickup_time'] ?? '',
            ]);

            if ($result['success']) {
                setFlash('success', "Narudžba {$result['order_number']} je uspješno kreirana! Uskoro ćemo vas kontaktirati.");
                header('Location: /orders.php');
                exit;
            } else {
                $error = $result['message'];
            }
        }
    }
}

$total     = getCartTotal();
$deliveryCalc = calculateDeliveryFee((float)$total);
$deliveryFee  = (float)$deliveryCalc['fee'];
$grandTotal   = (float)$total + $deliveryFee;
$cartPromotionMap = getPromotionMapByLinkedProductIds(array_column($cart, 'product_id'));
$pageTitle = 'Narudžba';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container page-checkout">
    <div class="page-header">
        <h1 class="page-title">Finalizacija narudžbe</h1>
        <a href="/cart.php" class="btn btn-outline">← Nazad u korpu</a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><span>✗</span> <?= e($error) ?></div>
    <?php endif; ?>

    <div class="checkout-layout">
        <!-- Forma dostave -->
        <div class="checkout-form-wrap">
            <form class="checkout-form" id="checkoutForm" method="POST" action="/checkout.php" novalidate data-ordering-open="<?= $isOpen ? '1' : '0' ?>" data-ordering-hours="<?= e($hoursLabel) ?>">
                <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">

                <div class="checkout-section">
                    <h2 class="checkout-section-title">
                        <span class="checkout-step">1</span> Podaci o dostavi
                    </h2>

                    <div class="form-group">
                        <label for="address" class="form-label">Adresa dostave *</label>
                        <input
                            type="text" id="address" name="address" class="form-input"
                            placeholder="Ulica i broj, grad"
                            value="<?= e($_POST['address'] ?? $userData['address'] ?? '') ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="phone" class="form-label">Kontakt telefon *</label>
                        <input
                            type="tel" id="phone" name="phone" class="form-input"
                            placeholder="052 233 333 ili mobilni"
                            value="<?= e($_POST['phone'] ?? $userData['phone'] ?? '') ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="note" class="form-label">Napomena (opciono)</label>
                        <textarea
                            id="note" name="note" class="form-input form-textarea"
                            placeholder="Npr. zvonite na 2. ulaz, allergije, posebni zahtjevi..."
                            rows="3"
                        ><?= e($_POST['note'] ?? '') ?></textarea>
                    </div>
                </div>

                <div class="checkout-section">
                    <h2 class="checkout-section-title">
                        <span class="checkout-step">2</span> Način preuzimanja
                    </h2>

                    <div class="pickup-options" id="pickupOptions">
                        <label class="pickup-option-card">
                            <input type="radio" name="delivery_type" value="delivery">
                            <div class="pickup-option-body">
                                <div class="pickup-option-title">Dostava na adresu</div>
                                <div class="pickup-option-subtitle">Kurir dolazi na unijetu adresu, primjenjuje se cijena dostave.</div>
                            </div>
                        </label>

                        <label class="pickup-option-card">
                            <input type="radio" name="delivery_type" value="pickup">
                            <div class="pickup-option-body">
                                <div class="pickup-option-title">Lično preuzimanje</div>
                                <div class="pickup-option-subtitle">Preuzimate narudžbu u pekari, dostava se ne naplaćuje.</div>
                            </div>
                        </label>
                    </div>

                    <div class="form-group" id="pickupTimeGroup" style="display:none;margin-top:0.75rem">
                        <label for="pickup_time" class="form-label">Termin preuzimanja (obavezno za lično preuzimanje)</label>
                        <input
                            type="time" id="pickup_time" name="pickup_time" class="form-input"
                            step="900"
                            value="<?= e($_POST['pickup_time'] ?? '') ?>"
                        >
                    </div>
                </div>

                <div class="checkout-section">
                    <h2 class="checkout-section-title">
                        <span class="checkout-step">3</span> Način plaćanja
                    </h2>

                    <div class="payment-option selected">
                        <div class="payment-option-icon">💵</div>
                        <div class="payment-option-info">
                            <strong>Plaćanje pouzećem</strong>
                            <span>Platite gotovinom dostavljaču</span>
                        </div>
                        <div class="payment-option-check">✓</div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-full btn-lg checkout-submit <?= !$isOpen ? 'checkout-submit-closed' : '' ?>" <?= !$isOpen ? 'aria-disabled="true"' : '' ?> id="checkoutSubmit">
                    🛒 Potvrdi narudžbu — <span id="checkoutSubmitTotal"><?= formatPrice($grandTotal) ?></span>
                </button>
                <?php if (!$isOpen): ?>
                    <div class="alert alert-info" style="margin-top:0.75rem">Kozara trenutno NE RADI. Radno vreme: <?= e($hoursLabel) ?>.</div>
                <?php endif; ?>
            </form>
        </div>

        <!-- Pregled narudžbe -->
        <div class="checkout-summary">
            <div class="checkout-summary-card">
                <h2 class="cart-summary-title">Vaša narudžba</h2>

                <div class="checkout-items-list">
                    <?php foreach ($cart as $item): ?>
                        <div class="checkout-item">
                            <div class="checkout-item-img">
                                <img
                                    src="<?= e(productImageUrl($item['image'])) ?>"
                                    alt="<?= e($item['name']) ?>"
                                    onerror="this.src='/assets/img/no-image.svg'"
                                >
                                <span class="checkout-item-qty"><?= (int)$item['quantity'] ?></span>
                            </div>
                            <div class="checkout-item-name"><?= e($item['name']) ?></div>
                            <?php if (!empty($cartPromotionMap[(int)$item['product_id']]['composition_label'])): ?>
                                <div class="text-muted text-sm" style="grid-column:2; margin-top:-0.2rem; margin-bottom:0.2rem;">
                                    <?= e($cartPromotionMap[(int)$item['product_id']]['composition_label']) ?>
                                </div>
                            <?php endif; ?>
                            <div class="checkout-item-price">
                                <?= formatPrice($item['price'] * $item['quantity']) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="checkout-summary-divider"></div>

                <div class="cart-summary-row">
                    <span>Međuzbir:</span>
                    <span><?= formatPrice($total) ?></span>
                </div>
                <div class="cart-summary-row">
                    <span>Dostava:</span>
                    <span id="checkoutDeliveryValue">
                        <?php if ($deliveryFee > 0): ?>
                            <?= formatPrice($deliveryFee) ?>
                        <?php else: ?>
                            <span class="text-success">Besplatno</span>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="cart-summary-row cart-summary-total">
                    <span>Ukupno:</span>
                    <span id="checkoutGrandTotal"><?= formatPrice($grandTotal) ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Iznosi za oba scenarija se računaju na serveru (isti kod koji stvarno
// naplaćuje narudžbu), ovdje se samo bira koji prikazati na osnovu
// odabranog načina preuzimanja — bez toga, cijena dostave je uvijek
// prikazivala "dostava" scenario čak i kad je izabrano lično preuzimanje.
const CHECKOUT_TOTALS = {
    deliveryFeeLabel: <?= json_encode($deliveryFee > 0 ? formatPrice($deliveryFee) : null) ?>,
    grandTotalDelivery: <?= json_encode(formatPrice($grandTotal)) ?>,
    grandTotalPickup: <?= json_encode(formatPrice($total)) ?>
};

(function initCheckoutPickup() {
    const form = document.querySelector('.checkout-form');
    if (!form) return;

    const radios = form.querySelectorAll('input[name="delivery_type"]');
    const submitBtn = document.getElementById('checkoutSubmit');
    const pickupTimeGroup = document.getElementById('pickupTimeGroup');
    const pickupTimeInput = document.getElementById('pickup_time');
    const deliveryValueEl = document.getElementById('checkoutDeliveryValue');
    const grandTotalEl = document.getElementById('checkoutGrandTotal');
    const submitTotalEl = document.getElementById('checkoutSubmitTotal');

    function getMinPickupTime() {
        const now = new Date();
        const totalMinutes = now.getHours() * 60 + now.getMinutes();
        const remainder = totalMinutes % 15;
        const roundedMinutes = remainder === 0 ? totalMinutes : totalMinutes + (15 - remainder);
        const hh = String(Math.floor(roundedMinutes / 60) % 24).padStart(2, '0');
        const mm = String(roundedMinutes % 60).padStart(2, '0');
        return hh + ':' + mm;
    }

    function updateSummary(selected) {
        if (!deliveryValueEl || !grandTotalEl || !submitTotalEl) return;

        if (selected === 'pickup') {
            deliveryValueEl.innerHTML = '<span class="text-success">Besplatno</span>';
            grandTotalEl.textContent = CHECKOUT_TOTALS.grandTotalPickup;
            submitTotalEl.textContent = CHECKOUT_TOTALS.grandTotalPickup;
        } else {
            deliveryValueEl.innerHTML = CHECKOUT_TOTALS.deliveryFeeLabel
                ? CHECKOUT_TOTALS.deliveryFeeLabel
                : '<span class="text-success">Besplatno</span>';
            grandTotalEl.textContent = CHECKOUT_TOTALS.grandTotalDelivery;
            submitTotalEl.textContent = CHECKOUT_TOTALS.grandTotalDelivery;
        }
    }

    function updateState() {
        let selected = null;
        radios.forEach(r => {
            const card = r.closest('.pickup-option-card');
            if (card) card.classList.toggle('selected', r.checked);
            if (r.checked) selected = r.value;
        });

        if (pickupTimeGroup) {
            pickupTimeGroup.style.display = selected === 'pickup' ? 'block' : 'none';
        }

        if (selected === 'pickup' && pickupTimeInput) {
            pickupTimeInput.min = getMinPickupTime();
        }

        updateSummary(selected);
    }

    radios.forEach(radio => {
        radio.addEventListener('change', updateState);
    });

    updateState();

    form.addEventListener('submit', function (e) {
        const selected = form.querySelector('input[name="delivery_type"]:checked');
        if (!selected) {
            e.preventDefault();
            alert('Molimo odaberite da li želite dostavu ili lično preuzimanje prije slanja narudžbe.');
            return;
        }

        if (selected.value === 'pickup' && pickupTimeInput && pickupTimeInput.value) {
            const now = new Date();
            const nowStr = String(now.getHours()).padStart(2, '0') + ':' + String(now.getMinutes()).padStart(2, '0');
            if (pickupTimeInput.value < nowStr) {
                e.preventDefault();
                alert('Termin preuzimanja ne može biti u prošlosti. Trenutno je ' + nowStr + ' — odaberite kasniji termin.');
            }
        }
    });
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
