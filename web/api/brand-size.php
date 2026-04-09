<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/helpers/response.php';
require_once dirname(__DIR__) . '/helpers/request.php';

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'POST')) !== 'POST') {
    json_response([
        'success' => false,
        'message' => 'Method not allowed',
    ], 405);
}

$payload = body();
$payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES);
$remoteUrl = 'https://www.opulentprimeproperties.com/influencer_house/web/niche';

$statusCode = 0;
$responseBody = false;
$transportError = null;

if (function_exists('curl_init')) {
    $ch = curl_init($remoteUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER => [
            'Content-Type: text/plain',
            'Accept: application/json',
        ],
        CURLOPT_POSTFIELDS => $payloadJson,
    ]);

    $responseBody = curl_exec($ch);
    if ($responseBody === false) {
        $transportError = curl_error($ch);
    }
    $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
} else {
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: text/plain\r\nAccept: application/json\r\n",
            'content' => (string) $payloadJson,
            'timeout' => 30,
            'ignore_errors' => true,
        ],
    ]);

    $responseBody = @file_get_contents($remoteUrl, false, $context);
    if ($responseBody === false) {
        $transportError = 'Unable to reach remote API';
    }

    if (!empty($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $matches)) {
        $statusCode = (int) $matches[1];
    }
}

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
