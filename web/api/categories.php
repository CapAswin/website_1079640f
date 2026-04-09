<?php

declare(strict_types=1);

if (!isset($pdo)) {
    $init = is_file(__DIR__ . '/_init.php') ? __DIR__ . '/_init.php' : __DIR__ . '/../_init.php';
    list($pdo, $config) = require $init;
}
$stmt = $pdo->query('SELECT category_id, category_name, status FROM category WHERE status = 1 ORDER BY category_name');
$list = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $list[] = ['category_id' => (int) $row['category_id'], 'category_name' => $row['category_name'], 'status' => (int) $row['status']];
}
json_response($list);
