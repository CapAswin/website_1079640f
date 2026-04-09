<?php
// CORS: allow admin dashboard to call this API
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Max-Age: 86400");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(204);
    exit;
}

header("Content-Type: application/json");
include 'db.php';

$sql = "
SELECT 
    i.influencer_id,
    i.username,
    i.full_name,
    i.bio,
    i.profile_image,
    i.country_id,
    i.primary_niche_id,
    i.secondary_niche_id,
    cp.price_per_post,
    cp.currency,
    cp.is_negotiable,
    cp.collaboration_type,
    cp.availability_status
FROM influencer i
LEFT JOIN content_pricing cp 
    ON cp.influencer_id = i.influencer_id
WHERE i.account_status = 1
ORDER BY i.influencer_id DESC
";

$result = $conn->query($sql);

if (!$result) {
    http_response_code(500);
    echo json_encode([
        "status" => false,
        "mysql_error" => $conn->error
    ]);
    exit;
}

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

echo json_encode([
    "status" => true,
    "count" => count($data),
    "data" => $data
]);
