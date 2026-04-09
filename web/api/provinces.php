<?php

declare(strict_types=1);

if (!isset($pdo)) {
    $init = is_file(__DIR__ . '/_init.php') ? __DIR__ . '/_init.php' : __DIR__ . '/../_init.php';
    list($pdo, $config) = require $init;
}
$countryId = get_int('country_id');
if ($countryId < 1) {
    json_fail('country_id required', [], 400);
}
$stmt = $pdo->prepare('SELECT province_id, country_id, province_name FROM province WHERE country_id = ? ORDER BY province_name');
$stmt->execute([$countryId]);
$list = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $list[] = ['province_id' => (int) $row['province_id'], 'country_id' => (int) $row['country_id'], 'province_name' => $row['province_name']];
}
json_response($list);
