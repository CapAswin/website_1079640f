<?php

declare(strict_types=1);

if (!isset($pdo)) {
    $init = is_file(dirname(__DIR__) . '/_init.php') ? dirname(__DIR__) . '/_init.php' : dirname(__DIR__, 2) . '/_init.php';
    list($pdo, $config) = require $init;
}
$body = body();
$userType = trim((string) ($body['user_type'] ?? ''));
$email = trim((string) ($body['email'] ?? ''));
$password = (string) ($body['password'] ?? '');

$errors = [];
if (!in_array($userType, ['influencer', 'brand'], true)) {
    $errors['user_type'] = 'Must be "influencer" or "brand"';
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Valid email is required';
}
if (strlen($password) < 8) {
    $errors['password'] = 'Minimum 8 characters';
}
if ($errors !== []) {
    json_fail('Validation failed', $errors, 422);
}

if ($userType === 'brand') {
    json_fail('Brand registration not supported', ['user_type' => 'Use influencer'], 400);
}

$stmt = $pdo->prepare('SELECT 1 FROM influencer WHERE email = ?');
$stmt->execute([$email]);
if ($stmt->fetch()) {
    json_fail('Email already exists', ['email' => 'This email is already registered'], 400);
}
$stmt = $pdo->prepare('SELECT 1 FROM brand WHERE brand_email = ?');
$stmt->execute([$email]);
if ($stmt->fetch()) {
    json_fail('Email already exists', ['email' => 'This email is already registered'], 400);
}

$base = preg_replace('/[^a-z0-9]/i', '', explode('@', $email)[0]);
$base = $base !== '' ? substr($base, 0, 40) : 'user';
$username = $base;
$n = 0;
while (true) {
    $stmt = $pdo->prepare('SELECT 1 FROM influencer WHERE username = ?');
    $stmt->execute([$username]);
    if (!$stmt->fetch()) {
        break;
    }
    $username = $base . '_' . (++$n);
    if (strlen($username) > 50) {
        $username = substr($base, 0, 45) . '_' . $n;
    }
}

$pdo->prepare('INSERT INTO influencer (username, email, password_hash, account_status) VALUES (?, ?, ?, 1)')
    ->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT)]);
$id = (int) $pdo->lastInsertId();

json_ok('Initial registration successful', [
    'user_id'              => $id,
    'user_type'            => 'influencer',
    'requires_verification' => true,
], 201);
