<?php

declare(strict_types=1);

if (!isset($pdo)) {
    $init = is_file(dirname(__DIR__, 2) . '/_init.php') ? dirname(__DIR__, 2) . '/_init.php' : dirname(__DIR__, 3) . '/_init.php';
    list($pdo, $config) = require $init;
}
$influencerId = (int) ($_POST['influencer_id'] ?? 0);
if ($influencerId < 1) {
    json_fail('influencer_id required', [], 400);
}

$stmt = $pdo->prepare('SELECT 1 FROM influencer WHERE influencer_id = ?');
$stmt->execute([$influencerId]);
if (!$stmt->fetch()) {
    json_fail('Influencer not found', [], 404);
}

$openToCollab = (int) ($_POST['open_to_brand_collab'] ?? 1);
$pricePerPost = isset($_POST['price_per_post']) ? (float) $_POST['price_per_post'] : null;
$isNegotiable = (int) ($_POST['is_negotiable'] ?? 1);
$collabType = in_array($_POST['collaboration_type'] ?? '', ['paid', 'affiliate', 'barter'], true) ? $_POST['collaboration_type'] : 'paid';
$documentType = isset($_POST['document_type']) ? trim((string) $_POST['document_type']) : null;

// social_accounts: JSON array of { platform_id, username, profile_link?, is_verified? } — inserted into influencer_social_account (1..n per influencer)
$socialAccounts = is_array($decoded = json_decode((string) ($_POST['social_accounts'] ?? '[]'), true)) ? $decoded : [];
if (!empty($socialAccounts) && !isset($socialAccounts[0])) {
    $socialAccounts = [$socialAccounts];
}

// audience_demographics: top_countries (JSON array of country codes), gender_split (JSON object), age_groups (JSON object)
$topCountriesRaw = $_POST['top_countries'] ?? null;
$topCountries = null;
if ($topCountriesRaw !== null && $topCountriesRaw !== '') {
    $dec = json_decode((string) $topCountriesRaw, true);
    $topCountries = is_array($dec) ? json_encode($dec) : null;
}
$genderSplitRaw = $_POST['gender_split'] ?? null;
$genderSplit = null;
if ($genderSplitRaw !== null && $genderSplitRaw !== '') {
    $dec = json_decode((string) $genderSplitRaw, true);
    $genderSplit = is_array($dec) && !isset($dec[0]) ? json_encode($dec) : null;
}
$ageGroupsRaw = $_POST['age_groups'] ?? null;
$ageGroups = null;
if ($ageGroupsRaw !== null && $ageGroupsRaw !== '') {
    $dec = json_decode((string) $ageGroupsRaw, true);
    $ageGroups = is_array($dec) && !isset($dec[0]) ? json_encode($dec) : null;
}

$errors = [];
if ($pricePerPost !== null && ($pricePerPost < 0 || $pricePerPost > 999999.99)) {
    $errors['price_per_post'] = 'Invalid';
}

$root = dirname(__DIR__, 2);
if (!is_file($root . '/config/config.php')) {
    $root = dirname(__DIR__, 3);
}
$uploadDir = $root . '/uploads/influencer_documents/';
$documentPath = null;
if (!empty($_FILES['document_file']['name']) && $_FILES['document_file']['error'] === UPLOAD_ERR_OK) {
    $ext = strtolower(pathinfo($_FILES['document_file']['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
    $maxSize = 5242880;
    if (!in_array($ext, $allowed, true)) {
        $errors['document_file'] = 'Allowed: jpg, jpeg, png, pdf';
    } elseif ($_FILES['document_file']['size'] > $maxSize) {
        $errors['document_file'] = 'Max 5MB';
    } else {
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $name = 'influencer_' . $influencerId . '_doc_' . time() . '.' . $ext;
        if (move_uploaded_file($_FILES['document_file']['tmp_name'], $uploadDir . $name)) {
            $documentPath = 'uploads/influencer_documents/' . $name;
        } else {
            $errors['document_file'] = 'Upload failed';
        }
    }
}
if ($errors !== []) {
    json_fail('Validation failed', $errors, 422);
}

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare('SELECT pricing_id FROM content_pricing WHERE influencer_id = ?');
    $stmt->execute([$influencerId]);
    $exists = $stmt->fetch();

    if ($exists) {
        $pdo->prepare(
            'UPDATE content_pricing SET collaboration_type = ?, open_to_brand_collab = ?, price_per_post = ?, is_negotiable = ?, currency = ? WHERE influencer_id = ?'
        )->execute([$collabType, $openToCollab, $pricePerPost, $isNegotiable, 'USD', $influencerId]);
    } else {
        $pdo->prepare(
            'INSERT INTO content_pricing (influencer_id, collaboration_type, open_to_brand_collab, price_per_post, is_negotiable, currency) VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$influencerId, $collabType, $openToCollab, $pricePerPost, $isNegotiable, 'USD']);
    }

    $pdo->prepare('DELETE FROM influencer_social_account WHERE influencer_id = ?')->execute([$influencerId]);
    $ins = $pdo->prepare(
        'INSERT INTO influencer_social_account (influencer_id, platform_id, username, profile_link, is_verified) VALUES (?, ?, ?, ?, ?)'
    );
    foreach ($socialAccounts as $a) {
        $pid = (int) ($a['platform_id'] ?? 0);
        $un = trim((string) ($a['username'] ?? ''));
        $pl = trim((string) ($a['profile_link'] ?? ''));
        $isVerified = isset($a['is_verified']) ? (int) (bool) $a['is_verified'] : 0;
        if ($pid > 0 && $un !== '') {
            $ins->execute([$influencerId, $pid, $un, $pl, $isVerified]);
        }
    }

    // audience_demographics: one row per influencer (insert or update)
    $demoStmt = $pdo->prepare('SELECT demographics_id FROM audience_demographics WHERE influencer_id = ?');
    $demoStmt->execute([$influencerId]);
    $demoExists = $demoStmt->fetch();
    if ($demoExists) {
        $pdo->prepare(
            'UPDATE audience_demographics SET top_countries = ?, gender_split = ?, age_groups = ? WHERE influencer_id = ?'
        )->execute([$topCountries, $genderSplit, $ageGroups, $influencerId]);
    } else {
        $pdo->prepare(
            'INSERT INTO audience_demographics (influencer_id, top_countries, gender_split, age_groups) VALUES (?, ?, ?, ?)'
        )->execute([$influencerId, $topCountries, $genderSplit, $ageGroups]);
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    json_server_error($config['debug'], $e->getMessage());
}

json_ok('Registration completed successfully', [
    'influencer_id'       => $influencerId,
    'verification_status' => 'pending',
    'redirect_url'        => '/dashboard',
], 201);
