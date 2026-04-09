<?php

declare(strict_types=1);

if (!isset($pdo)) {
    $init = is_file(__DIR__ . '/_init.php') ? __DIR__ . '/_init.php' : __DIR__ . '/../_init.php';
    list($pdo, $config) = require $init;
}
$stmt = $pdo->query('SELECT platform_id, platform_name, base_url, status FROM social_platform WHERE status = 1 AND COALESCE(deleted, 0) = 0 ORDER BY platform_name');
$list = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $list[] = ['platform_id' => (int) $row['platform_id'], 'platform_name' => $row['platform_name'], 'base_url' => $row['base_url'] ?? '', 'status' => (int) $row['status']];
}
json_response($list);
