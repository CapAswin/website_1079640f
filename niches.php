<?php
/**
 * Niches API – list niches for a category.
 * GET api/niches.php?category_id=1
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

$category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
if ($category_id > 0) {
    $stmt = $conn->prepare("SELECT niche_id, category_id, niche_name FROM niche WHERE status = 1 AND category_id = ? ORDER BY niche_name");
    if ($stmt) {
        $stmt->bind_param('i', $category_id);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $out['data'][] = ['id' => (int)$row['niche_id'], 'category_id' => (int)$row['category_id'], 'name' => $row['niche_name']];
            }
            $res->free();
            $out['success'] = true;
        }
        $stmt->close();
    }
} else {
    $out['success'] = true;
}

echo json_encode($out);
