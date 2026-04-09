<?php

declare(strict_types=1);

require_once dirname(dirname(__DIR__)) . '/helpers/response.php';

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'POST')) !== 'POST') {
    json_response([
        'success' => false,
        'message' => 'Method not allowed',
    ], 405);
}

if (!function_exists('curl_init')) {
    json_response([
        'success' => false,
        'message' => 'cURL support is required for document uploads',
    ], 500);
}

$remoteUrl = 'https://www.opulentprimeproperties.com/influencer_house/web/brand/upload-documents';
$payload = $_POST;
$postFields = $payload;

foreach ($_FILES as $fieldName => $file) {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        continue;
    }

    $postFields[$fieldName] = new CURLFile(
        (string) $file['tmp_name'],
        (string) ($file['type'] ?? 'application/octet-stream'),
        (string) ($file['name'] ?? $fieldName)
    );
}

$ch = curl_init($remoteUrl);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
    ],
    CURLOPT_POSTFIELDS => $postFields,
]);

$responseBody = curl_exec($ch);
$transportError = $responseBody === false ? curl_error($ch) : null;
$statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($transportError !== null) {
    json_response([
        'success' => false,
        'message' => 'API service is currently unavailable',
        'error' => $transportError,
    ], 502);
}

$decoded = json_decode((string) $responseBody, true);
if (is_array($decoded)) {
    json_response($decoded, $statusCode > 0 ? $statusCode : 200);
}

json_response([
    'success' => $statusCode >= 200 && $statusCode < 300,
    'message' => 'Unexpected API response',
    'raw' => $responseBody,
], $statusCode > 0 ? $statusCode : 502);
