<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

/** Specialty tags a service can be assigned to (which kind of dentist performs it). */
const SPECIALTIES = [
    'general'        => 'General Dentist',
    'endodontist'    => 'Endodontist',
    'orthodontist'   => 'Orthodontist',
    'periodontist'   => 'Periodontist',
    'prosthodontist' => 'Prosthodontist',
    'oral_surgeon'   => 'Oral Surgeon',
    'pediatric'      => 'Pediatric Dentist',
];

/** Units a per-quantity service can be charged per (services.quantity_unit). */
const QUANTITY_UNITS = ['tooth', 'tooth surface', 'unit', 'arch', 'quadrant', 'scan'];

/* ---- Read: available to any authenticated staff/patient ---- */
if ($method === 'GET' && $action === '') {
    apiAuth();
    $onlyActive = isset($_GET['active']);
    $sql = 'SELECT * FROM services' . ($onlyActive ? ' WHERE is_active = 1' : '')
         . ' ORDER BY service_name';
    $rows = db()->query($sql)->fetchAll();
    foreach ($rows as &$row) {
        $file = $row['image'] ? __DIR__ . '/../uploads/services/' . $row['image'] : null;
        $row['image_v'] = ($file && is_file($file)) ? filemtime($file) : null;
    }
    unset($row);
    jsonOut(['ok' => true, 'services' => $rows, 'specialties' => SPECIALTIES, 'quantity_units' => QUANTITY_UNITS]);
}

/* ---- Write: admin only ---- */
$me = apiAuth(['admin']);

/* Service image upload — multipart, before requestBody(). */
if ($action === 'upload_image') {
    $file = $_FILES['image'] ?? null;
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        jsonOut(['ok' => false, 'error' => 'No image was uploaded.'], 422);
    }
    if ($file['size'] > 3 * 1024 * 1024) {
        jsonOut(['ok' => false, 'error' => 'Image must be 3 MB or smaller.'], 422);
    }
    $ext = match (mime_content_type($file['tmp_name'])) {
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        default      => null,
    };
    if ($ext === null) {
        jsonOut(['ok' => false, 'error' => 'Use a JPG, PNG or WEBP image.'], 422);
    }
    $dir = __DIR__ . '/../uploads/services';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $name = 'svc_' . bin2hex(random_bytes(8)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
        jsonOut(['ok' => false, 'error' => 'Could not save the image.'], 500);
    }
    jsonOut(['ok' => true, 'image' => $name]);
}

$in = requestBody();

if ($action === 'save') {
    $id       = (int) ($in['service_id'] ?? 0);
    $name     = trim($in['service_name'] ?? '');
    $price    = (float) ($in['base_price'] ?? 0);
    $desc     = trim($in['description'] ?? '');
    $shortDesc = trim($in['short_description'] ?? '');
    $features = trim($in['features'] ?? '');
    $image    = basename(trim($in['image'] ?? ''));
    // Unit the price is charged per (per-tooth, per-arch, ...); empty = a
    // single-session service that always bills at quantity 1.
    $qtyUnit  = trim((string) ($in['quantity_unit'] ?? ''));
    $allowInst = !empty($in['allows_installment']) ? 1 : 0;   // installment payments allowed (migration 069)
    // Usual time per visit (or per unit), shown to patients (migration 074).
    $duration = (int) ($in['duration_minutes'] ?? 30);
    if ($duration < 5 || $duration > 480) {
        jsonOut(['ok' => false, 'error' => 'Usual duration must be between 5 and 480 minutes.'], 422);
    }
    if ($qtyUnit !== '' && !in_array($qtyUnit, QUANTITY_UNITS, true)) {
        jsonOut(['ok' => false, 'error' => 'Choose a valid "charged per" unit.'], 422);
    }

    $specs = array_values(array_intersect(
        array_keys(SPECIALTIES),
        array_map('strval', (array) ($in['specialties'] ?? []))
    ));

    if ($name === '' || $price < 0) {
        jsonOut(['ok' => false, 'error' => 'A service name and a non-negative price are required.'], 422);
    }
    if ($specs === []) {
        jsonOut(['ok' => false, 'error' => 'Select at least one specialty that performs this service.'], 422);
    }
    $specCsv = implode(',', $specs);
    $imageOk = $image !== '' && is_file(__DIR__ . '/../uploads/services/' . $image) ? $image : null;

    if ($id > 0) {
        $keepImage = $imageOk;
        if ($keepImage === null) {
            $cur = db()->prepare('SELECT image FROM services WHERE service_id = ?');
            $cur->execute([$id]);
            $keepImage = $cur->fetchColumn() ?: null;
        }
        $stmt = db()->prepare(
            'UPDATE services SET service_name = ?, specialties = ?,
                    description = ?, short_description = ?, features = ?, image = ?, base_price = ?, quantity_unit = ?, allows_installment = ?, duration_minutes = ?
             WHERE service_id = ?'
        );
        $stmt->execute([$name, $specCsv, $desc ?: null, $shortDesc ?: null, $features ?: null, $keepImage, $price, $qtyUnit ?: null, $allowInst, $duration, $id]);
    } else {
        $stmt = db()->prepare(
            'INSERT INTO services (service_name, specialties, description, short_description, features, image, base_price, quantity_unit, allows_installment, duration_minutes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$name, $specCsv, $desc ?: null, $shortDesc ?: null, $features ?: null, $imageOk, $price, $qtyUnit ?: null, $allowInst, $duration]);
        $id = (int) db()->lastInsertId();
    }
    jsonOut(['ok' => true, 'service_id' => $id]);
}

if ($action === 'toggle') {
    $id = (int) ($in['service_id'] ?? 0);
    $stmt = db()->prepare('UPDATE services SET is_active = 1 - is_active WHERE service_id = ?');
    $stmt->execute([$id]);
    jsonOut(['ok' => true]);
}

jsonOut(['ok' => false, 'error' => 'Unknown action'], 400);
