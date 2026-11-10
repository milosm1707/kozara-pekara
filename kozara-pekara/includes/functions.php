<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/mailer.php';

if (defined('APP_TIMEZONE') && APP_TIMEZONE) {
    @date_default_timezone_set(APP_TIMEZONE);
}

function base_url(): string {
    if (defined('BASE_URL') && BASE_URL) {
        return rtrim((string)BASE_URL, '/');
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? '';
    return $host ? $scheme . '://' . rtrim($host, '/') : '';
}

function asset_url(string $path): string {
    $base = base_url();
    $path = '/' . ltrim($path, '/');
    return $base . $path;
}

function getOrderingHours(): array {
    $db = getDB();

    try {
        $stmt = $db->query("SELECT * FROM ordering_settings ORDER BY id ASC LIMIT 1");
        $row = $stmt->fetch();
    } catch (Throwable $e) {
        $row = false;
    }

    if ($row) {
        $openHour  = (int)$row['open_hour'];
        $closeHour = (int)$row['close_hour'];
    } else {
        $openHour  = defined('ORDERING_OPEN_HOUR') ? (int)ORDERING_OPEN_HOUR : 8;
        $closeHour = defined('ORDERING_CLOSE_HOUR') ? (int)ORDERING_CLOSE_HOUR : 22;

        try {
            $insert = $db->prepare("INSERT INTO ordering_settings (open_hour, close_hour) VALUES (?, ?)");
            $insert->execute([$openHour, $closeHour]);
        } catch (Throwable $e) {
        }
    }

    $openHour  = max(0, min(23, $openHour));
    $closeHour = max(1, min(24, $closeHour));

    if ($closeHour <= $openHour) {
        $openHour = 8;
        $closeHour = 22;
    }

    return [
        'open'  => $openHour,
        'close' => $closeHour,
    ];
}

function isOrderingOpen(?int $timestamp = null): bool {
    $hours = getOrderingHours();
    $hour  = (int)date('G', $timestamp ?? time());
    return $hour >= $hours['open'] && $hour < $hours['close'];
}

function getOrderingHoursLabel(): string {
    $hours = getOrderingHours();
    return str_pad((string)$hours['open'], 2, '0', STR_PAD_LEFT) . ':00 — ' . str_pad((string)$hours['close'], 2, '0', STR_PAD_LEFT) . ':00';
}

function formatDateTimeApp(?string $dateTime, string $format = 'd.m.Y H:i'): string {
    if (!$dateTime) {
        return '—';
    }

    try {
        $utc = new DateTimeZone('UTC');
        $appTz = new DateTimeZone(defined('APP_TIMEZONE') ? APP_TIMEZONE : 'Europe/Belgrade');
        $dt = new DateTime($dateTime, $utc);
        $dt->setTimezone($appTz);
        return $dt->format($format);
    } catch (Exception $e) {
        return date($format, strtotime($dateTime));
    }
}

function getManagerNotificationEmails(): array {
    $emails = [];

    if (defined('MANAGER_NOTIFICATION_EMAIL') && trim((string)MANAGER_NOTIFICATION_EMAIL) !== '') {
        $configured = trim((string)MANAGER_NOTIFICATION_EMAIL);
        if (filter_var($configured, FILTER_VALIDATE_EMAIL)) {
            $emails[] = $configured;
        }
    }

    $db = getDB();
    $stmt = $db->query("SELECT email FROM users WHERE role IN ('manager', 'admin') AND is_active = 1 ORDER BY FIELD(role, 'manager', 'admin'), id ASC");
    foreach ($stmt->fetchAll() as $row) {
        if (!empty($row['email']) && filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
            $emails[] = trim($row['email']);
        }
    }

    return array_values(array_unique($emails));
}

function sendPlainEmailToMany(array $emails, string $subject, string $message): int {
    return appSendPlainEmailToMany($emails, $subject, $message);
}

function notifyManagerNewOrder(int $orderId, string $orderNumber): bool {
    $emails = getManagerNotificationEmails();
    if (empty($emails)) {
        return false;
    }

    $order = getOrderDetails($orderId);
    if (!$order) {
        return false;
    }

    $subject = 'Nova narudžba: ' . $orderNumber;
    $message = "Stigla je nova narudžba.\n\n"
        . "Broj narudžbe: {$orderNumber}\n"
        . "Kupac: {$order['user_name']} ({$order['user_email']})\n"
        . "Telefon: {$order['delivery_phone']}\n"
        . "Adresa: {$order['delivery_address']}\n"
        . "Ukupno: " . formatPrice((float)$order['total_price']) . "\n"
        . "Datum: " . formatDateTimeApp($order['created_at']) . "\n\n"
        . "Ulogujte se u panel za detalje narudžbe.";

    return sendPlainEmailToMany($emails, $subject, $message) > 0;
}

function getOrdersRealtimeMeta(string $statusFilter = ''): array {
    $db = getDB();

    if ($statusFilter !== '') {
        $stmt = $db->prepare("SELECT COUNT(*) AS total, COALESCE(MAX(id), 0) AS latest_id, COALESCE(UNIX_TIMESTAMP(MAX(updated_at)), 0) AS latest_activity FROM orders WHERE status = ?");
        $stmt->execute([$statusFilter]);
    } else {
        $stmt = $db->query("SELECT COUNT(*) AS total, COALESCE(MAX(id), 0) AS latest_id, COALESCE(UNIX_TIMESTAMP(MAX(updated_at)), 0) AS latest_activity FROM orders");
    }

    $meta = $stmt->fetch() ?: ['total' => 0, 'latest_id' => 0, 'latest_activity' => 0];

    $pendingStmt = $db->query("SELECT COUNT(*) AS pending_count FROM orders WHERE status = 'na_cekanju'");
    $pending = $pendingStmt->fetch();

    return [
        'total'           => (int)($meta['total'] ?? 0),
        'latest_id'       => (int)($meta['latest_id'] ?? 0),
        'latest_activity' => (int)($meta['latest_activity'] ?? 0),
        'pending_count'   => (int)($pending['pending_count'] ?? 0),
    ];
}

function getUserOrdersRealtimeMeta(int $userId): array {
    $db = getDB();

    $stmt = $db->prepare(
        "SELECT COUNT(*) AS total,
                COALESCE(MAX(id), 0) AS latest_id,
                COALESCE(UNIX_TIMESTAMP(MAX(updated_at)), 0) AS latest_activity
         FROM orders
         WHERE user_id = ?"
    );
    $stmt->execute([$userId]);

    $meta = $stmt->fetch() ?: ['total' => 0, 'latest_id' => 0, 'latest_activity' => 0];

    return [
        'total'           => (int)($meta['total'] ?? 0),
        'latest_id'       => (int)($meta['latest_id'] ?? 0),
        'latest_activity' => (int)($meta['latest_activity'] ?? 0),
    ];
}


function getCategories(bool $excludeActions = true): array {
    $db = getDB();
    $sql = "SELECT * FROM categories";
    $params = [];

    if ($excludeActions) {
        $sql .= " WHERE slug <> ?";
        $params[] = 'akcije';
    }

    $sql .= " ORDER BY sort_order, name";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getCategoriesWithProductCount(): array {
    $db = getDB();
    $stmt = $db->query("SELECT c.*, COUNT(p.id) AS products_count FROM categories c LEFT JOIN products p ON p.category_id = c.id GROUP BY c.id ORDER BY c.sort_order, c.name");
    return $stmt->fetchAll();
}

function createCategory(array $data): array {
    $db = getDB();

    $name = trim((string)($data['name'] ?? ''));
    $sortOrder = (int)($data['sort_order'] ?? 0);

    if ($name === '') {
        return ['success' => false, 'message' => 'Naziv kategorije je obavezan.'];
    }

    $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $name), '-'));
    if ($slug === '') {
        $slug = 'kategorija-' . time();
    }

    $stmt = $db->prepare("SELECT id FROM categories WHERE slug = ? LIMIT 1");
    $stmt->execute([$slug]);
    if ($stmt->fetch()) {
        $slug .= '-' . rand(100, 999);
    }

    $stmt = $db->prepare("INSERT INTO categories (name, slug, sort_order) VALUES (?, ?, ?)");
    $stmt->execute([$name, $slug, $sortOrder]);

    return ['success' => true, 'message' => 'Kategorija je uspešno dodata.'];
}

function deleteCategory(int $categoryId): array {
    $db = getDB();

    $check = $db->prepare("SELECT name FROM categories WHERE id = ? LIMIT 1");
    $check->execute([$categoryId]);
    $category = $check->fetch();

    if (!$category) {
        return ['success' => false, 'message' => 'Kategorija ne postoji.'];
    }

    $productsStmt = $db->prepare("SELECT COUNT(*) AS cnt FROM products WHERE category_id = ?");
    $productsStmt->execute([$categoryId]);
    $productsCount = (int)($productsStmt->fetch()['cnt'] ?? 0);

    if ($productsCount > 0) {
        return [
            'success' => false,
            'message' => 'Kategoriju nije moguće obrisati jer sadrži proizvode. Prvo promenite kategoriju proizvoda ili obrišite te proizvode.',
        ];
    }

    $del = $db->prepare("DELETE FROM categories WHERE id = ?");
    $del->execute([$categoryId]);

    return ['success' => true, 'message' => 'Kategorija je obrisana.'];
}

function getHomepageItems(string $type = '', bool $onlyActive = true): array {
    $db = getDB();
    $allowedTypes = ['promo', 'job'];

    $sql = "SELECT * FROM homepage_items WHERE 1=1";
    $params = [];

    if ($type !== '' && in_array($type, $allowedTypes, true)) {
        $sql .= " AND item_type = ?";
        $params[] = $type;
    }

    if ($onlyActive) {
        $sql .= " AND is_active = 1";
    }

    $sql .= " ORDER BY sort_order ASC, id DESC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getHomepageItemById(int $id): ?array {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM homepage_items WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function saveHomepageItem(array $data, ?int $id = null): array {
    $db = getDB();

    $itemType = trim((string)($data['item_type'] ?? 'promo'));
    if (!in_array($itemType, ['promo', 'job'], true)) {
        return ['success' => false, 'message' => 'Nevažeći tip stavke.'];
    }

    $title = trim((string)($data['title'] ?? ''));
    $description = trim((string)($data['description'] ?? ''));
    $badge = trim((string)($data['badge'] ?? ''));
    $ctaText = trim((string)($data['cta_text'] ?? ''));
    $ctaUrl = trim((string)($data['cta_url'] ?? ''));
    $sortOrder = (int)($data['sort_order'] ?? 0);
    $isActive = isset($data['is_active']) ? 1 : 0;

    if ($title === '' || $description === '') {
        return ['success' => false, 'message' => 'Naslov i opis su obavezni.'];
    }

    if ($ctaText === '') {
        $ctaText = $itemType === 'job' ? 'Prijavi se' : 'Saznaj više';
    }
    if ($ctaUrl === '') {
        $ctaUrl = $itemType === 'job' ? '/contact.php?topic=job' : '/index.php';
    }

    if ($id) {
        $stmt = $db->prepare("UPDATE homepage_items SET item_type=?, title=?, description=?, badge=?, cta_text=?, cta_url=?, sort_order=?, is_active=?, updated_at=NOW() WHERE id=?");
        $stmt->execute([$itemType, $title, $description, $badge, $ctaText, $ctaUrl, $sortOrder, $isActive, $id]);
        return ['success' => true, 'message' => 'Homepage stavka je ažurirana.'];
    }

    $stmt = $db->prepare("INSERT INTO homepage_items (item_type, title, description, badge, cta_text, cta_url, sort_order, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$itemType, $title, $description, $badge, $ctaText, $ctaUrl, $sortOrder, $isActive]);

    return ['success' => true, 'message' => 'Homepage stavka je dodata.'];
}

function deleteHomepageItem(int $id): array {
    $db = getDB();
    $stmt = $db->prepare("DELETE FROM homepage_items WHERE id = ?");
    $stmt->execute([$id]);
    return ['success' => true, 'message' => 'Homepage stavka je obrisana.'];
}

function getOrCreateActionsCategoryId(): int {
    $db = getDB();
    $slug = 'akcije';

    $stmt = $db->prepare("SELECT id FROM categories WHERE slug = ? LIMIT 1");
    $stmt->execute([$slug]);
    $existing = $stmt->fetch();
    if ($existing) {
        return (int)$existing['id'];
    }

    $insert = $db->prepare("INSERT INTO categories (name, slug, sort_order) VALUES ('Akcije', ?, 0)");
    $insert->execute([$slug]);
    return (int)$db->lastInsertId();
}

function getActionsCategoryId(): int {
    $db = getDB();
    $stmt = $db->prepare("SELECT id FROM categories WHERE slug = ? LIMIT 1");
    $stmt->execute(['akcije']);
    $row = $stmt->fetch();
    return $row ? (int)$row['id'] : 0;
}

function getPromotionLinkedProductIds(): array {
    $db = getDB();
    $rows = $db->query("SELECT linked_product_id FROM promotions")->fetchAll();
    return array_map('intval', array_column($rows, 'linked_product_id'));
}

function getPromotionMapByLinkedProductIds(array $productIds): array {
    $ids = array_values(array_unique(array_filter(array_map('intval', $productIds))));
    if (empty($ids)) {
        return [];
    }

    $db = getDB();
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $db->prepare("SELECT id, linked_product_id, title FROM promotions WHERE linked_product_id IN ($placeholders)");
    $stmt->execute($ids);
    $promotions = $stmt->fetchAll();

    if (empty($promotions)) {
        return [];
    }

    $result = [];
    $partsStmt = $db->prepare("SELECT pp.promotion_id, p.name FROM promotion_products pp JOIN products p ON p.id = pp.product_id WHERE pp.promotion_id = ? ORDER BY p.name");

    foreach ($promotions as $promotion) {
        $promotionId = (int)$promotion['id'];
        $partsStmt->execute([$promotionId]);
        $parts = array_column($partsStmt->fetchAll(), 'name');

        $result[(int)$promotion['linked_product_id']] = [
            'id' => $promotionId,
            'title' => (string)$promotion['title'],
            'parts' => $parts,
            'composition_label' => empty($parts) ? '' : ('Sastav: ' . implode(' + ', $parts)),
        ];
    }

    return $result;
}

function getPromotions(bool $onlyActive = false): array {
    $db = getDB();
    $sql = "SELECT pr.*, p.name AS linked_product_name, p.image AS linked_product_image, p.is_available AS linked_product_available
            FROM promotions pr
            LEFT JOIN products p ON p.id = pr.linked_product_id";

    if ($onlyActive) {
        $sql .= " WHERE pr.is_active = 1";
    }

    $sql .= " ORDER BY pr.sort_order ASC, pr.id DESC";
    $rows = $db->query($sql)->fetchAll();

    foreach ($rows as &$row) {
        $itemsStmt = $db->prepare("SELECT pp.product_id, p.name, p.price, p.image FROM promotion_products pp JOIN products p ON p.id = pp.product_id WHERE pp.promotion_id = ? ORDER BY p.name");
        $itemsStmt->execute([(int)$row['id']]);
        $row['products'] = $itemsStmt->fetchAll();

        if (empty($row['image']) && !empty($row['linked_product_image'])) {
            $row['image'] = $row['linked_product_image'];
        }
    }

    return $rows;
}

function getPromotionById(int $id): ?array {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM promotions WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $promotion = $stmt->fetch();
    if (!$promotion) {
        return null;
    }

    $itemsStmt = $db->prepare("SELECT product_id FROM promotion_products WHERE promotion_id = ?");
    $itemsStmt->execute([$id]);
    $promotion['product_ids'] = array_map('intval', array_column($itemsStmt->fetchAll(), 'product_id'));

    return $promotion;
}

function savePromotion(array $data, ?int $promotionId = null): array {
    $db = getDB();

    $title = trim((string)($data['title'] ?? ''));
    $description = trim((string)($data['description'] ?? ''));
    $badge = trim((string)($data['badge'] ?? ''));
    $promoPrice = (float)($data['promo_price'] ?? 0);
    $sortOrder = (int)($data['sort_order'] ?? 0);
    $isActive = isset($data['is_active']) ? 1 : 0;
    $image = isset($data['image']) ? trim((string)$data['image']) : null;
    $selectedIds = array_values(array_unique(array_map('intval', (array)($data['product_ids'] ?? []))));

    if ($title === '' || $promoPrice <= 0) {
        return ['success' => false, 'message' => 'Naslov akcije i cena su obavezni.'];
    }

    if (count($selectedIds) < 1) {
        return ['success' => false, 'message' => 'Dodajte bar jedan proizvod u akciju.'];
    }

    $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
    $productsStmt = $db->prepare("SELECT id, name FROM products WHERE id IN ($placeholders)");
    $productsStmt->execute($selectedIds);
    $products = $productsStmt->fetchAll();

    if (count($products) !== count($selectedIds)) {
        return ['success' => false, 'message' => 'Neki od odabranih proizvoda ne postoje.'];
    }

    usort($products, fn($a, $b) => strcmp($a['name'], $b['name']));
    $includedText = implode(' + ', array_column($products, 'name'));
    $finalDescription = $description !== ''
        ? $description . "\n\nSadrži: " . $includedText
        : "Sadrži: " . $includedText;

    $actionsCategoryId = getOrCreateActionsCategoryId();

    try {
        $db->beginTransaction();

        $linkedProductId = null;
        $existingPromotionImage = null;
        if ($promotionId) {
            $existingStmt = $db->prepare("SELECT linked_product_id, image FROM promotions WHERE id = ? LIMIT 1");
            $existingStmt->execute([$promotionId]);
            $existing = $existingStmt->fetch();
            if (!$existing) {
                $db->rollBack();
                return ['success' => false, 'message' => 'Akcija ne postoji.'];
            }
            $linkedProductId = (int)$existing['linked_product_id'];
            $existingPromotionImage = $existing['image'] ?? null;
        }

        $finalImage = $image ?: $existingPromotionImage;

        if ($linkedProductId) {
            $updateProduct = $db->prepare("UPDATE products SET name = ?, description = ?, price = ?, category_id = ?, is_available = ?, sort_order = ?, image = ?, updated_at = NOW() WHERE id = ?");
            $updateProduct->execute([$title, $finalDescription, $promoPrice, $actionsCategoryId, $isActive, $sortOrder, $finalImage, $linkedProductId]);
        } else {
            $insertProduct = $db->prepare("INSERT INTO products (name, description, price, category_id, is_available, sort_order, image) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $insertProduct->execute([$title, $finalDescription, $promoPrice, $actionsCategoryId, $isActive, $sortOrder, $finalImage]);
            $linkedProductId = (int)$db->lastInsertId();
        }

        if ($promotionId) {
            $updatePromo = $db->prepare("UPDATE promotions SET title=?, description=?, badge=?, promo_price=?, sort_order=?, is_active=?, linked_product_id=?, image=?, updated_at=NOW() WHERE id=?");
            $updatePromo->execute([$title, $description, $badge, $promoPrice, $sortOrder, $isActive, $linkedProductId, $finalImage, $promotionId]);

            $clear = $db->prepare("DELETE FROM promotion_products WHERE promotion_id = ?");
            $clear->execute([$promotionId]);
        } else {
            $insertPromo = $db->prepare("INSERT INTO promotions (title, description, badge, promo_price, sort_order, is_active, linked_product_id, image) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $insertPromo->execute([$title, $description, $badge, $promoPrice, $sortOrder, $isActive, $linkedProductId, $finalImage]);
            $promotionId = (int)$db->lastInsertId();
        }

        $insertMap = $db->prepare("INSERT INTO promotion_products (promotion_id, product_id) VALUES (?, ?)");
        foreach ($selectedIds as $pid) {
            $insertMap->execute([$promotionId, $pid]);
        }

        $db->commit();

        return ['success' => true, 'message' => $promotionId ? 'Akcija je sačuvana.' : 'Akcija je dodata.'];
    } catch (Exception $e) {
        $db->rollBack();
        error_log('Promotion save error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Greška pri snimanju akcije.'];
    }
}

function deletePromotion(int $promotionId): array {
    $db = getDB();

    $stmt = $db->prepare("SELECT linked_product_id FROM promotions WHERE id = ? LIMIT 1");
    $stmt->execute([$promotionId]);
    $promotion = $stmt->fetch();

    if (!$promotion) {
        return ['success' => false, 'message' => 'Akcija ne postoji.'];
    }

    try {
        $db->beginTransaction();

        $db->prepare("DELETE FROM promotion_products WHERE promotion_id = ?")->execute([$promotionId]);
        $db->prepare("DELETE FROM promotions WHERE id = ?")->execute([$promotionId]);

        $linkedProductId = (int)$promotion['linked_product_id'];
        if ($linkedProductId > 0) {
            deleteProduct($linkedProductId);
        }

        $db->commit();
        return ['success' => true, 'message' => 'Akcija je obrisana.'];
    } catch (Exception $e) {
        $db->rollBack();
        error_log('Promotion delete error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Greška pri brisanju akcije.'];
    }
}

function getProducts(int $categoryId = 0, string $search = '', bool $onlyAvailable = true, bool $includePromotionProducts = false): array {
    $db = getDB();

    $sql = "SELECT p.*, c.name as category_name
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE 1=1";
    $params = [];

    if (!$includePromotionProducts) {
        $actionsCategoryId = getActionsCategoryId();
        if ($actionsCategoryId > 0) {
            $sql .= " AND (p.category_id IS NULL OR p.category_id <> ?)";
            $params[] = $actionsCategoryId;
        }

        $promotionProductIds = getPromotionLinkedProductIds();
        if (!empty($promotionProductIds)) {
            $placeholders = implode(',', array_fill(0, count($promotionProductIds), '?'));
            $sql .= " AND p.id NOT IN ($placeholders)";
            foreach ($promotionProductIds as $promotionProductId) {
                $params[] = (int)$promotionProductId;
            }
        }
    }

    if ($onlyAvailable) {
        $sql .= " AND p.is_available = 1";
    }

    if ($categoryId > 0) {
        $sql .= " AND p.category_id = ?";
        $params[] = $categoryId;
    }

    $sql .= " ORDER BY p.sort_order, p.name";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    if ($search !== '') {
        $needle = normalizeForSearch($search);
        $rows = array_values(array_filter($rows, function (array $p) use ($needle): bool {
            return strpos(normalizeForSearch($p['name']), $needle) !== false
                || strpos(normalizeForSearch($p['description'] ?? ''), $needle) !== false;
        }));
    }

    return $rows;
}

function getProductById(int $id): ?array {
    $db = getDB();
    $stmt = $db->prepare("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function saveProduct(array $data, ?int $id = null): array {
    $db = getDB();

    $name        = trim(htmlspecialchars($data['name']));
    $description = trim(htmlspecialchars($data['description'] ?? ''));
    $price       = floatval($data['price']);
    $categoryId  = intval($data['category_id'] ?? 0) ?: null;
    $isAvailable = isset($data['is_available']) ? 1 : 0;
    $sortOrder   = intval($data['sort_order'] ?? 0);
    $image       = $data['image'] ?? null;

    if (empty($name)) return ['success' => false, 'message' => 'Naziv proizvoda je obavezan.'];
    if ($price <= 0) return ['success' => false, 'message' => 'Cijena mora biti veća od 0.'];

    if ($id) {
        if ($image) {
            $stmt = $db->prepare("UPDATE products SET name=?, description=?, price=?, category_id=?, is_available=?, sort_order=?, image=?, updated_at=NOW() WHERE id=?");
            $stmt->execute([$name, $description, $price, $categoryId, $isAvailable, $sortOrder, $image, $id]);
        } else {
            $stmt = $db->prepare("UPDATE products SET name=?, description=?, price=?, category_id=?, is_available=?, sort_order=?, updated_at=NOW() WHERE id=?");
            $stmt->execute([$name, $description, $price, $categoryId, $isAvailable, $sortOrder, $id]);
        }
        return ['success' => true, 'message' => 'Proizvod uspješno ažuriran.'];
    } else {
        $stmt = $db->prepare("INSERT INTO products (name, description, price, category_id, is_available, sort_order, image) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $description, $price, $categoryId, $isAvailable, $sortOrder, $image]);
        return ['success' => true, 'message' => 'Proizvod uspješno dodan.', 'id' => $db->lastInsertId()];
    }
}

function deleteProduct(int $id): array {
    $db = getDB();

    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM order_items WHERE product_id = ?");
    $stmt->execute([$id]);
    if ($stmt->fetch()['cnt'] > 0) {
        $stmt = $db->prepare("UPDATE products SET is_available = 0 WHERE id = ?");
        $stmt->execute([$id]);
        return ['success' => true, 'message' => 'Proizvod deaktiviran (koristi se u narudžbama).'];
    }

    $product = getProductById($id);
    $stmt = $db->prepare("DELETE FROM products WHERE id = ?");
    $stmt->execute([$id]);

    if ($product && $product['image']) {
        $imagePath = __DIR__ . '/../uploads/products/' . $product['image'];
        if (file_exists($imagePath)) unlink($imagePath);
    }

    return ['success' => true, 'message' => 'Proizvod obrisan.'];
}


function uploadProductImage(array $file): array {
    $uploadDir = __DIR__ . '/../uploads/products/';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $maxSize = 5 * 1024 * 1024;

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Greška pri uploadu slike.'];
    }

    if (!in_array($file['type'], $allowedTypes)) {
        return ['success' => false, 'message' => 'Dozvoljeni formati: JPG, PNG, WebP, GIF.'];
    }

    if ($file['size'] > $maxSize) {
        return ['success' => false, 'message' => 'Slika ne smije biti veća od 5MB.'];
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $realType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($realType, $allowedTypes)) {
        return ['success' => false, 'message' => 'Nevažeći tip fajla.'];
    }

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('prod_') . '_' . time() . '.' . strtolower($ext);
    $destination = $uploadDir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return ['success' => false, 'message' => 'Greška pri snimanju slike.'];
    }

    return ['success' => true, 'filename' => $filename];
}

function uploadPromotionImage(array $file): array {
    $uploadDir = __DIR__ . '/../uploads/promotions/';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $maxSize = 5 * 1024 * 1024;

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Greška pri uploadu slike akcije.'];
    }

    if (!in_array($file['type'], $allowedTypes)) {
        return ['success' => false, 'message' => 'Dozvoljeni formati: JPG, PNG, WebP, GIF.'];
    }

    if ($file['size'] > $maxSize) {
        return ['success' => false, 'message' => 'Slika ne smije biti veća od 5MB.'];
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $realType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($realType, $allowedTypes)) {
        return ['success' => false, 'message' => 'Nevažeći tip fajla.'];
    }

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('promo_') . '_' . time() . '.' . strtolower($ext);
    $destination = $uploadDir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return ['success' => false, 'message' => 'Greška pri snimanju slike akcije.'];
    }

    return ['success' => true, 'filename' => $filename];
}


function generateOrderNumber(): string {
    return 'ORD-' . strtoupper(date('Ymd')) . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
}

function createOrder(int $userId, array $cartItems, array $deliveryData): array {
    $db = getDB();

    if (empty($cartItems)) {
        return ['success' => false, 'message' => 'Korpa je prazna.'];
    }

    $deliveryType = $deliveryData['delivery_type'] ?? 'delivery';
    if (!in_array($deliveryType, ['delivery', 'pickup'], true)) {
        $deliveryType = 'delivery';
    }

    $address = trim($deliveryData['address'] ?? '');
    $phone   = trim($deliveryData['phone'] ?? '');
    $note    = trim(htmlspecialchars($deliveryData['note'] ?? ''));
    $pickupTimeRaw = trim($deliveryData['pickup_time'] ?? '');

    if (empty($phone)) {
        return ['success' => false, 'message' => 'Telefon je obavezan.'];
    }

    if ($deliveryType === 'delivery') {
        if ($address === '') {
            return ['success' => false, 'message' => 'Adresa dostave je obavezna.'];
        }
    } else {
        if ($pickupTimeRaw === '') {
            return ['success' => false, 'message' => 'Za lično preuzimanje je potrebno navesti termin preuzimanja.'];
        }
        if (preg_match('/^\d{2}:\d{2}$/', $pickupTimeRaw)) {
            $tz = new DateTimeZone(defined('APP_TIMEZONE') ? APP_TIMEZONE : 'Europe/Belgrade');
            $now = new DateTime('now', $tz);
            $pickupDt = new DateTime($now->format('Y-m-d') . ' ' . $pickupTimeRaw, $tz);
            if ($pickupDt < $now) {
                return ['success' => false, 'message' => 'Termin preuzimanja ne može biti u prošlosti. Trenutno je ' . $now->format('H:i') . ' — odaberite kasniji termin.'];
            }
        }
    }

    $orderSubtotal = 0.0;
    $validatedItems = [];

    foreach ($cartItems as $item) {
        $product = getProductById((int)$item['product_id']);
        if (!$product || !$product['is_available']) {
            return ['success' => false, 'message' => "Proizvod '{$item['name']}' nije dostupan."];
        }
        $quantity = max(1, (int)$item['quantity']);
        $itemSubtotal = round((float)$product['price'] * $quantity, 2);
        $orderSubtotal += $itemSubtotal;

        $validatedItems[] = [
            'product_id'    => $product['id'],
            'product_name'  => $product['name'],
            'product_price' => $product['price'],
            'quantity'      => $quantity,
            'subtotal'      => $itemSubtotal,
        ];
    }

    if ($deliveryType === 'pickup') {
        $deliveryFee = 0.0;
    } else {
        $deliveryCalc = calculateDeliveryFee((float)$orderSubtotal);
        $deliveryFee  = (float)$deliveryCalc['fee'];
    }
    $grandTotal   = (float)$orderSubtotal + (float)$deliveryFee;

    try {
        $db->beginTransaction();

        $orderNumber = generateOrderNumber();

        $pickupTimeToStore = $deliveryType === 'pickup' ? $pickupTimeRaw : null;
        $addressToStore    = $deliveryType === 'pickup' && $address === ''
            ? 'Lično preuzimanje'
            : $address;

        $stmt = $db->prepare("INSERT INTO orders (user_id, order_number, total_price, delivery_fee, delivery_type, pickup_time, delivery_address, delivery_phone, note, payment_method) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pouzecem')");
        $stmt->execute([$userId, $orderNumber, $grandTotal, $deliveryFee, $deliveryType, $pickupTimeToStore, $addressToStore, $phone, $note]);
        $orderId = $db->lastInsertId();

        $stmt = $db->prepare("INSERT INTO order_items (order_id, product_id, product_name, product_price, quantity, subtotal) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($validatedItems as $item) {
            $stmt->execute([$orderId, $item['product_id'], $item['product_name'], $item['product_price'], $item['quantity'], $item['subtotal']]);
        }

        $db->commit();

        $mailSent = notifyManagerNewOrder((int)$orderId, $orderNumber);
        if (!$mailSent) {
            error_log('Order mail notification failed for order ID: ' . $orderId);
        }

        $_SESSION['cart'] = [];

        return ['success' => true, 'order_number' => $orderNumber, 'order_id' => $orderId];

    } catch (Exception $e) {
        $db->rollBack();
        error_log("Order error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Greška pri kreiranju narudžbe.'];
    }
}

function getUserOrders(int $userId): array {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function getAllOrders(string $status = ''): array {
    $db = getDB();

    if ($status) {
        $stmt = $db->prepare("SELECT o.*, u.name as user_name, u.email as user_email FROM orders o JOIN users u ON o.user_id = u.id WHERE o.status = ? ORDER BY o.created_at DESC");
        $stmt->execute([$status]);
    } else {
        $stmt = $db->query("SELECT o.*, u.name as user_name, u.email as user_email FROM orders o JOIN users u ON o.user_id = u.id ORDER BY o.created_at DESC");
    }

    return $stmt->fetchAll();
}

function getOrderDetails(int $orderId): ?array {
    $db = getDB();
    $stmt = $db->prepare("SELECT o.*, u.name as user_name, u.email as user_email FROM orders o JOIN users u ON o.user_id = u.id WHERE o.id = ?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();

    if (!$order) return null;

    $stmt = $db->prepare("SELECT * FROM order_items WHERE order_id = ?");
    $stmt->execute([$orderId]);
    $order['items'] = $stmt->fetchAll();

    return $order;
}

function updateOrderStatus(int $orderId, string $status): bool {
    $allowedStatuses = ['na_cekanju', 'u_pripremi', 'u_dostavi', 'isporuceno', 'otkazano'];
    if (!in_array($status, $allowedStatuses)) return false;

    $db = getDB();
    $stmt = $db->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$status, $orderId]);
    return $stmt->rowCount() > 0;
}

function deleteOrder(int $orderId): array {
    $db = getDB();

    $stmt = $db->prepare("SELECT id FROM orders WHERE id = ? LIMIT 1");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();

    if (!$order) {
        return ['success' => false, 'message' => 'Narudžba ne postoji.'];
    }

    $del = $db->prepare("DELETE FROM orders WHERE id = ? LIMIT 1");
    $del->execute([$orderId]);

    if ($del->rowCount() < 1) {
        return ['success' => false, 'message' => 'Brisanje narudžbe nije uspjelo.'];
    }

    return ['success' => true, 'message' => 'Narudžba je obrisana.'];
}


function getCart(): array {
    return $_SESSION['cart'] ?? [];
}

function addToCart(int $productId, int $quantity = 1): array {
    $product = getProductById($productId);
    if (!$product || !$product['is_available']) {
        return ['success' => false, 'message' => 'Proizvod nije dostupan.'];
    }

    $cart = getCart();

    if (isset($cart[$productId])) {
        $cart[$productId]['quantity'] += $quantity;
    } else {
        $cart[$productId] = [
            'product_id' => $productId,
            'name'       => $product['name'],
            'price'      => $product['price'],
            'image'      => $product['image'],
            'quantity'   => $quantity,
        ];
    }

    $_SESSION['cart'] = $cart;
    return ['success' => true, 'count' => array_sum(array_column($cart, 'quantity'))];
}

function removeFromCart(int $productId): void {
    $cart = getCart();
    unset($cart[$productId]);
    $_SESSION['cart'] = $cart;
}

function updateCartQuantity(int $productId, int $quantity): void {
    $cart = getCart();
    if ($quantity <= 0) {
        unset($cart[$productId]);
    } elseif (isset($cart[$productId])) {
        $cart[$productId]['quantity'] = $quantity;
    }
    $_SESSION['cart'] = $cart;
}

function getCartCount(): int {
    $cart = getCart();
    return array_sum(array_column($cart, 'quantity'));
}

function getCartTotal(): float {
    $cart = getCart();
    $total = 0;
    foreach ($cart as $item) {
        $total += $item['price'] * $item['quantity'];
    }
    return $total;
}


function getDeliverySettings(): array {
    $db = getDB();

    $stmt = $db->query("SELECT * FROM delivery_settings ORDER BY id ASC LIMIT 1");
    $row = $stmt->fetch();

    if ($row) {
        return [
            'id'                => (int)$row['id'],
            'delivery_fee'      => (float)$row['delivery_fee'],
            'free_delivery_from'=> (float)$row['free_delivery_from'],
        ];
    }

    $insert = $db->prepare("INSERT INTO delivery_settings (delivery_fee, free_delivery_from) VALUES (5.00, 10.00)");
    $insert->execute();

    return [
        'id'                => (int)$db->lastInsertId(),
        'delivery_fee'      => 5.00,
        'free_delivery_from'=> 10.00,
    ];
}

function calculateDeliveryFee(float $subtotal): array {
    $settings = getDeliverySettings();
    $fee = $subtotal >= $settings['free_delivery_from'] ? 0.0 : $settings['delivery_fee'];

    return [
        'fee'      => $fee,
        'settings' => $settings,
    ];
}


function getAllUsers(): array {
    $db = getDB();
    $stmt = $db->query("SELECT id, name, email, phone, role, is_active, created_at FROM users ORDER BY role, name");
    return $stmt->fetchAll();
}

function createManager(array $data): array {
    $db = getDB();

    $name  = trim(htmlspecialchars($data['name']));
    $email = trim(strtolower($data['email']));
    $role  = in_array($data['role'] ?? '', ['manager', 'pekar'], true) ? $data['role'] : 'manager';

    if (empty($name)) return ['success' => false, 'message' => 'Ime je obavezno.'];
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return ['success' => false, 'message' => 'Nevažeći email.'];
    if (strlen($data['password']) < 8) return ['success' => false, 'message' => 'Lozinka min. 8 karaktera.'];

    $stmt = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    if ($stmt->fetch()) return ['success' => false, 'message' => 'Email već postoji.'];

    $hash = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);
    $stmt = $db->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
    $stmt->execute([$name, $email, $hash, $role]);

    $roleLabel = $role === 'pekar' ? 'Pekar' : 'Menadžer';
    return ['success' => true, 'message' => "{$roleLabel} uspješno kreiran."];
}

function toggleUserStatus(int $userId): bool {
    $db = getDB();
    $stmt = $db->prepare("UPDATE users SET is_active = IF(is_active=1, 0, 1) WHERE id = ? AND role != 'admin'");
    $stmt->execute([$userId]);
    return $stmt->rowCount() > 0;
}

function deleteUser(int $userId): array {
    $db = getDB();

    $stmt = $db->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) return ['success' => false, 'message' => 'Korisnik ne postoji.'];
    if ($user['role'] === 'admin') return ['success' => false, 'message' => 'Ne možete obrisati admina.'];

    $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$userId]);

    return ['success' => true, 'message' => 'Korisnik obrisan.'];
}

function updateUserPassword(int $userId, string $currentPassword, string $newPassword): array {
    $db = getDB();

    if (strlen($newPassword) < 8) {
        return ['success' => false, 'message' => 'Lozinka mora imati najmanje 8 karaktera.'];
    }

    $stmt = $db->prepare("SELECT password FROM users WHERE id = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) return ['success' => false, 'message' => 'Korisnik ne postoji ili nije aktivan.'];

    if (!password_verify($currentPassword, $user['password'])) {
        return ['success' => false, 'message' => 'Trenutna lozinka nije ispravna.'];
    }

    $hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
    $stmt = $db->prepare("UPDATE users SET password = ?, session_token = NULL, session_token_issued_at = NULL WHERE id = ?");
    $stmt->execute([$hash, $userId]);

    return ['success' => true, 'message' => 'Lozinka je uspješno promijenjena. Prijavite se ponovo na svoj nalog.'];
}

function deactivateUser(int $userId): bool {
    $db = getDB();
    $stmt = $db->prepare("UPDATE users SET is_active = 0 WHERE id = ? AND role != 'admin'");
    $stmt->execute([$userId]);
    return $stmt->rowCount() > 0;
}


function normalizeForSearch(string $str): string {
    $str = mb_strtolower($str, 'UTF-8');
    return strtr($str, [
        'č' => 'c', 'ć' => 'c',
        'š' => 's',
        'ž' => 'z',
        'đ' => 'd',
    ]);
}

function formatPrice(float $price): string {
    return number_format($price, 2, ',', '.') . ' KM';
}

function productImageUrl(?string $image): string {
    if ($image && file_exists(__DIR__ . '/../uploads/products/' . $image)) {
        return '/uploads/products/' . $image;
    }
    if ($image && file_exists(__DIR__ . '/../uploads/promotions/' . $image)) {
        return '/uploads/promotions/' . $image;
    }
    return '/assets/img/no-image.svg';
}

function promotionImageUrl(?string $image): string {
    if ($image && file_exists(__DIR__ . '/../uploads/promotions/' . $image)) {
        return '/uploads/promotions/' . $image;
    }
    if ($image && file_exists(__DIR__ . '/../uploads/products/' . $image)) {
        return '/uploads/products/' . $image;
    }
    return '/assets/img/no-image.svg';
}

function orderStatusLabel(string $status): string {
    $labels = [
        'na_cekanju' => 'Na čekanju',
        'u_pripremi' => 'U pripremi',
        'u_dostavi'  => 'U dostavi',
        'isporuceno' => 'Isporučeno',
        'otkazano'   => 'Otkazano',
    ];
    return $labels[$status] ?? $status;
}

function orderStatusClass(string $status): string {
    $classes = [
        'na_cekanju' => 'status-pending',
        'u_pripremi' => 'status-preparing',
        'u_dostavi'  => 'status-delivering',
        'isporuceno' => 'status-delivered',
        'otkazano'   => 'status-cancelled',
    ];
    return $classes[$status] ?? '';
}

function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

function getCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
