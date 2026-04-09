<?php

declare(strict_types=1);

if (!isset($pdo)) {
    $init = is_file(__DIR__ . '/_init.php') ? __DIR__ . '/_init.php' : __DIR__ . '/../_init.php';
    list($pdo, $config) = require $init;
}
$categoryId = get_int('category_id');
if ($categoryId < 1) {
    json_fail('category_id required', [], 400);
}
$stmt = $pdo->prepare('SELECT niche_id, category_id, niche_name, status FROM niche WHERE category_id = ? AND status = 1 ORDER BY niche_name');
$stmt->execute([$categoryId]);
$list = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $list[] = ['niche_id' => (int) $row['niche_id'], 'category_id' => (int) $row['category_id'], 'niche_name' => $row['niche_name'], 'status' => (int) $row['status']];
}
json_response($list);
