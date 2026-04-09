<?php
/**
 * Categories API – list all categories (status = 1).
 * GET api/categories.php
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

$out = ['success' => false, 'data' => []];

$configPath = __DIR__ . '/db.php';
if (!is_file($configPath)) {
    echo json_encode(['success' => false, 'data' => [], 'error' => 'Config not found']);
    exit;
}
require_once $configPath;

if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_error) {
    echo json_encode($out);
    exit;
}

$res = @$conn->query("SELECT category_id, category_name FROM category WHERE status = 1 ORDER BY category_name");
if ($res && $res->num_rows >= 0) {
    while ($row = $res->fetch_assoc()) {
        $out['data'][] = ['id' => (int)$row['category_id'], 'name' => (string)$row['category_name']];
    }
    if ($res) $res->free();
    $out['success'] = true;
} else {
    $out['error'] = $conn->error ?: 'Query failed';
}

echo json_encode($out);
