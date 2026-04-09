<?php
/**
 * Provinces API – list provinces for a country.
 * GET api/provinces.php?country_id=1
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

$country_id = isset($_GET['country_id']) ? (int)$_GET['country_id'] : 0;
if ($country_id > 0) {
    $stmt = $conn->prepare("SELECT province_id, country_id, province_name FROM province WHERE country_id = ? ORDER BY province_name");
    if ($stmt) {
        $stmt->bind_param('i', $country_id);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $out['data'][] = ['id' => (int)$row['province_id'], 'country_id' => (int)$row['country_id'], 'name' => $row['province_name']];
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
