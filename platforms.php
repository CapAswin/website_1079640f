<?php
/**
 * Platforms API – list active platforms from social_platform (status = 1, deleted = 0).
 * GET api/platforms.php
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

$res = @$conn->query("
    SELECT platform_id, platform_name, base_url
    FROM social_platform
    WHERE status = 1 AND (deleted = 0 OR deleted IS NULL)
    ORDER BY platform_id
");
if ($res && $res->num_rows >= 0) {
    $slugMap = [
        'Instagram' => 'instagram',
        'YouTube'   => 'youtube',
        'TikTok'    => 'tiktok',
        'Facebook'  => 'facebook',
        'Twitter'   => 'twitter',
    ];
    while ($row = $res->fetch_assoc()) {
        $name = (string)$row['platform_name'];
        $slug = isset($slugMap[$name]) ? $slugMap[$name] : strtolower(preg_replace('/\s+/', '_', $name));
        $out['data'][] = [
            'id'   => (int)$row['platform_id'],
            'name' => $name,
            'slug' => $slug,
            'base_url' => $row['base_url'] ? (string)$row['base_url'] : null,
        ];
    }
    if ($res) $res->free();
    $out['success'] = true;
} else {
    $out['error'] = $conn->error ?: 'Query failed';
}

echo json_encode($out);
