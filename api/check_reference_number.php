<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

// Any signed-in user (patient booking, cashier billing / counter) may check.
apiAuth();

$ref            = trim((string) ($_GET['ref'] ?? ''));
$excludePayment = isset($_GET['exclude_payment']) ? (int) $_GET['exclude_payment'] : null;
$excludeCounter = isset($_GET['exclude_counter']) ? (int) $_GET['exclude_counter'] : null;

if ($ref === '') {
    jsonOut(['ok' => true, 'available' => true]);
}

jsonOut([
    'ok'        => true,
    'available' => !referenceInUse($ref, $excludePayment ?: null, $excludeCounter ?: null),
]);
