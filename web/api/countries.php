<?php

declare(strict_types=1);

if (!isset($pdo)) {
    $init = is_file(__DIR__ . '/_init.php') ? __DIR__ . '/_init.php' : __DIR__ . '/../_init.php';
    list($pdo, $config) = require $init;
}
$stmt = $pdo->query('SELECT country_id, country_name, currency FROM country ORDER BY country_name');
$list = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $list[] = ['country_id' => (int) $row['country_id'], 'country_name' => $row['country_name'], 'currency' => $row['currency']];
}
json_response($list);
