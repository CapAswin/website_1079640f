<?php
header("Content-Type: application/json");
include 'db.php';

if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
    echo json_encode(["status" => false, "message" => "Invalid influencer id"]);
    exit;
}

$id = (int) $_GET['id'];

// Influencer
$infSql = "
SELECT 
    influencer_id,
    username,
    full_name,
    bio,
    profile_image
FROM influencer 
WHERE influencer_id = $id
LIMIT 1
";

$infRes = $conn->query($infSql);

if (!$infRes || $infRes->num_rows === 0) {
    echo json_encode([
        "status" => false,
        "message" => "Influencer not found",
        "debug_sql" => $infSql
    ]);
    exit;
}

$influencer = $infRes->fetch_assoc();

// Social accounts
$socialsSql = "
SELECT 
    social_account_id,
    platform_id,
    username,
    profile_link,
    is_verified
FROM influencer_social_account 
WHERE influencer_id = $id
";

$socialRes = $conn->query($socialsSql);

if (!$socialRes) {
    echo json_encode([
        "status" => false,
        "message" => "Social query failed",
        "mysql_error" => $conn->error,
        "debug_sql" => $socialsSql
    ]);
    exit;
}

$socials = [];
while ($row = $socialRes->fetch_assoc()) {
    $socials[] = $row;
}

// Pricing
$pricingSql = "
SELECT 
    price_per_post,
    currency,
    is_negotiable,
    collaboration_type,
    availability_status
FROM content_pricing 
WHERE influencer_id = $id
LIMIT 1
";

$priceRes = $conn->query($pricingSql);

$pricing = null;
if ($priceRes && $priceRes->num_rows > 0) {
    $pricing = $priceRes->fetch_assoc();
}

// Platform metrics (followers, reach, views, engagement per platform)
$metricsSql = "
SELECT 
    metrics_id,
    influencer_id,
    platform_id,
    followers_count,
    average_reach,
    average_views,
    engagement_rate,
    extra_metrics,
    last_updated
FROM platform_metrics 
WHERE influencer_id = $id
ORDER BY platform_id
";

$metricsRes = $conn->query($metricsSql);
$platform_metrics = [];
if ($metricsRes) {
    while ($row = $metricsRes->fetch_assoc()) {
        $platform_metrics[] = $row;
    }
}

echo json_encode([
    "status" => true,
    "data" => [
        "influencer" => $influencer,
        "social_accounts" => $socials,
        "pricing" => $pricing,
        "platform_metrics" => $platform_metrics
    ]
]);
