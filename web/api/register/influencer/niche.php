<?php

declare(strict_types=1);

if (!isset($pdo)) {
    $init = is_file(dirname(__DIR__, 2) . '/_init.php') ? dirname(__DIR__, 2) . '/_init.php' : dirname(__DIR__, 3) . '/_init.php';
    list($pdo, $config) = require $init;
}
$body = body();
$influencerId = (int) ($body['influencer_id'] ?? 0);
if ($influencerId < 1) {
    json_fail('influencer_id required', [], 400);
}

$stmt = $pdo->prepare('SELECT 1 FROM influencer WHERE influencer_id = ?');
$stmt->execute([$influencerId]);
if (!$stmt->fetch()) {
    json_fail('Influencer not found', [], 404);
}

$bio = isset($body['bio']) ? trim((string) $body['bio']) : null;
$experienceSince = isset($body['experience_since']) ? (int) $body['experience_since'] : null;
$pastBrands = is_array($body['past_brands'] ?? null) ? $body['past_brands'] : [];
$primaryNicheId = isset($body['primary_niche_id']) ? (int) $body['primary_niche_id'] : null;
$secondaryNicheId = isset($body['secondary_niche_id']) ? (int) $body['secondary_niche_id'] : null;
$contentCategories = is_array($body['content_categories'] ?? null) ? array_map('intval', $body['content_categories']) : [];

$errors = [];
if ($primaryNicheId > 0) {
    $stmt = $pdo->prepare('SELECT 1 FROM niche WHERE niche_id = ? AND status = 1');
    $stmt->execute([$primaryNicheId]);
    if (!$stmt->fetch()) {
        $errors['primary_niche_id'] = 'Invalid niche';
    }
}
if ($secondaryNicheId > 0) {
    $stmt = $pdo->prepare('SELECT 1 FROM niche WHERE niche_id = ? AND status = 1');
    $stmt->execute([$secondaryNicheId]);
    if (!$stmt->fetch()) {
        $errors['secondary_niche_id'] = 'Invalid niche';
    }
}
if ($errors !== []) {
    json_fail('Validation failed', $errors, 422);
}

$pdo->prepare(
    'UPDATE influencer SET bio = ?, experience_since = ?, primary_niche_id = ?, secondary_niche_id = ? WHERE influencer_id = ?'
)->execute([$bio, $experienceSince ?: null, $primaryNicheId ?: null, $secondaryNicheId ?: null, $influencerId]);

$pastJson = json_encode(array_values($pastBrands));
$categoriesJson = json_encode($contentCategories);
$stmt = $pdo->prepare('SELECT pricing_id FROM content_pricing WHERE influencer_id = ?');
$stmt->execute([$influencerId]);
if ($stmt->fetch()) {
    $pdo->prepare('UPDATE content_pricing SET content_categories = ?, past_brands = ? WHERE influencer_id = ?')
        ->execute([$categoriesJson, $pastJson, $influencerId]);
} else {
    $pdo->prepare(
        'INSERT INTO content_pricing (influencer_id, content_categories, past_brands, collaboration_type, open_to_brand_collab, is_negotiable) VALUES (?, ?, ?, ?, 1, 1)'
    )->execute([$influencerId, $categoriesJson, $pastJson, 'paid']);
}

json_ok('Niche and bio information saved', ['influencer_id' => $influencerId, 'next_step' => '/register/social-pricing']);
