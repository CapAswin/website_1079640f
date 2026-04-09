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

$fullName = isset($body['full_name']) ? trim((string) $body['full_name']) : null;
$countryId = isset($body['country_id']) ? (int) $body['country_id'] : null;
$provinceId = isset($body['province_id']) ? (int) $body['province_id'] : null;
$cityName = isset($body['city_name']) ? trim((string) $body['city_name']) : null;
$dateOfBirth = isset($body['date_of_birth']) ? trim((string) $body['date_of_birth']) : null;
$gender = isset($body['gender']) ? (int) $body['gender'] : null;
$influencerType = (int) ($body['influencer_type'] ?? 0);

$errors = [];
if ($countryId > 0) {
    $stmt = $pdo->prepare('SELECT 1 FROM country WHERE country_id = ?');
    $stmt->execute([$countryId]);
    if (!$stmt->fetch()) {
        $errors['country_id'] = 'Invalid country';
    }
}
if ($provinceId > 0 && $countryId > 0) {
    $stmt = $pdo->prepare('SELECT 1 FROM province WHERE province_id = ? AND country_id = ?');
    $stmt->execute([$provinceId, $countryId]);
    if (!$stmt->fetch()) {
        $errors['province_id'] = 'Invalid province';
    }
}
if ($dateOfBirth !== null) {
    $ts = strtotime($dateOfBirth);
    if ($ts !== false && (int) date('Y') - (int) date('Y', $ts) < 13) {
        $errors['date_of_birth'] = 'Must be 13 or older';
    }
}
if ($gender !== null && !in_array($gender, [0, 1, 2], true)) {
    $errors['gender'] = 'Invalid';
}
if (!in_array($influencerType, [0, 1, 2], true)) {
    $errors['influencer_type'] = 'Invalid';
}
if ($errors !== []) {
    json_fail('Validation failed', $errors, 422);
}

$pdo->prepare(
    'UPDATE influencer SET full_name = ?, country_id = ?, province_id = ?, city_name = ?, date_of_birth = ?, gender = ?, influencer_type = ? WHERE influencer_id = ?'
)->execute([$fullName, $countryId ?: null, $provinceId ?: null, $cityName, $dateOfBirth ?: null, $gender, $influencerType, $influencerId]);

json_ok('Profile information saved', ['influencer_id' => $influencerId, 'next_step' => '/register/niche']);
