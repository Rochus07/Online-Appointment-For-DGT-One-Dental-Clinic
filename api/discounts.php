<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

/* ---------------------------------------------------------------- Reads */
if ($method === 'GET') {

    // Active promos with their parsed service-id lists — used by the cashier
    // Billing engine to offer eligible discounts on a bill.
    if ($action === 'active') {
        apiAuth(['admin', 'cashier']);
        jsonOut(['ok' => true, 'discounts' => activeDiscounts()]);
    }

    // Full list for the admin Settings management view, plus the active
    // service catalogue for the "specific services" picker.
    apiAuth(['admin']);
    $rows = db()->query(
        'SELECT id, discount_name, discount_type, discount_value, target_scope,
                applied_service_ids, is_active, updated_at
         FROM discounts ORDER BY is_active DESC, discount_name'
    )->fetchAll();
    foreach ($rows as &$d) {
        $d['service_ids']    = discountServiceIds($d['applied_service_ids']);
        $d['discount_value'] = (float) $d['discount_value'];
        $d['is_active']      = (int) $d['is_active'];
        unset($d['applied_service_ids']);
    }
    unset($d);

    $services = db()->query(
        'SELECT service_id, service_name, base_price FROM services WHERE is_active = 1 ORDER BY service_name'
    )->fetchAll();

    jsonOut(['ok' => true, 'discounts' => $rows, 'services' => $services]);
}

/* ---------------------------------------------------------------- Mutations (admin) */
$me = apiAuth(['admin']);
$in = requestBody();

if ($action === 'save') {
    $id    = (int) ($in['id'] ?? 0);
    $name  = trim((string) ($in['discount_name'] ?? ''));
    $type  = ($in['discount_type'] ?? 'percentage') === 'fixed' ? 'fixed' : 'percentage';
    $value = round((float) ($in['discount_value'] ?? 0), 2);
    $scope = ($in['target_scope'] ?? 'all') === 'specific' ? 'specific' : 'all';
    $active = !empty($in['is_active']) ? 1 : 0;

    if (mb_strlen($name) < 2) {
        jsonOut(['ok' => false, 'error' => 'Give the promotion a name.'], 422);
    }
    if ($value <= 0) {
        jsonOut(['ok' => false, 'error' => 'Enter a discount value greater than zero.'], 422);
    }
    if ($type === 'percentage' && $value > 100) {
        jsonOut(['ok' => false, 'error' => 'A percentage discount cannot be more than 100%.'], 422);
    }

    $serviceJson = null;
    if ($scope === 'specific') {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', (array) ($in['applied_service_ids'] ?? [])),
            static fn ($n) => $n > 0
        )));
        if (!$ids) {
            jsonOut(['ok' => false, 'error' => 'Pick at least one service for a service-specific promo.'], 422);
        }
        $place = implode(',', array_fill(0, count($ids), '?'));
        $st = db()->prepare("SELECT COUNT(*) FROM services WHERE service_id IN ($place)");
        $st->execute($ids);
        if ((int) $st->fetchColumn() !== count($ids)) {
            jsonOut(['ok' => false, 'error' => 'One or more selected services no longer exist.'], 422);
        }
        $serviceJson = json_encode($ids);
    }

    if ($id > 0) {
        db()->prepare(
            'UPDATE discounts SET discount_name = ?, discount_type = ?, discount_value = ?,
                    target_scope = ?, applied_service_ids = ?, is_active = ?
             WHERE id = ?'
        )->execute([$name, $type, $value, $scope, $serviceJson, $active, $id]);
    } else {
        db()->prepare(
            'INSERT INTO discounts (discount_name, discount_type, discount_value, target_scope, applied_service_ids, is_active)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$name, $type, $value, $scope, $serviceJson, $active]);
        $id = (int) db()->lastInsertId();
    }

    jsonOut(['ok' => true, 'id' => $id]);
}

if ($action === 'toggle') {
    $id = (int) ($in['id'] ?? 0);
    if ($id <= 0 || !discountById($id)) {
        jsonOut(['ok' => false, 'error' => 'Promotion not found.'], 404);
    }
    db()->prepare('UPDATE discounts SET is_active = 1 - is_active WHERE id = ?')->execute([$id]);
    $fresh = discountById($id);
    jsonOut(['ok' => true, 'is_active' => (int) $fresh['is_active']]);
}

jsonOut(['ok' => false, 'error' => 'Unknown action'], 400);
