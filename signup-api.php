<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed',
    ]);
    exit;
}

function normalizeUploadArray(array $file): array
{
    $normalized = [];

    if (!isset($file['name'])) {
        return $normalized;
    }

    if (is_array($file['name'])) {
        foreach ($file['name'] as $index => $name) {
            if (($file['error'][$index] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $normalized[] = [
                'name' => (string) $name,
                'type' => (string) ($file['type'][$index] ?? 'application/octet-stream'),
                'tmp_name' => (string) ($file['tmp_name'][$index] ?? ''),
                'error' => (int) ($file['error'][$index] ?? UPLOAD_ERR_NO_FILE),
                'size' => (int) ($file['size'][$index] ?? 0),
            ];
        }

        return $normalized;
    }

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return $normalized;
    }

    return [[
        'name' => (string) $file['name'],
        'type' => (string) ($file['type'] ?? 'application/octet-stream'),
        'tmp_name' => (string) ($file['tmp_name'] ?? ''),
        'error' => (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE),
        'size' => (int) ($file['size'] ?? 0),
    ]];
}

$contentType = (string) ($_SERVER['CONTENT_TYPE'] ?? '');
$isMultipart = stripos($contentType, 'multipart/form-data') !== false;

if ($isMultipart) {
    $body = $_POST;

    if (isset($body['payload']) && is_string($body['payload'])) {
        $decodedPayload = json_decode($body['payload'], true);
        $body['payload'] = is_array($decodedPayload) ? $decodedPayload : [];
    }
} else {
    $rawBody = file_get_contents('php://input');
    $body = json_decode($rawBody ?: '{}', true);

    if (!is_array($body)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid JSON payload',
        ]);
        exit;
    }
}

$action = trim((string) ($body['action'] ?? ''));
$payload = isset($body['payload']) && is_array($body['payload']) ? $body['payload'] : [];

if ($action === '' && isset($body['account_type'], $body['email'], $body['password'])) {
    $accountType = trim((string) $body['account_type']);
    $email = trim((string) $body['email']);
    $password = (string) $body['password'];

    $errors = [];
    if (!in_array($accountType, ['brand', 'influencer'], true)) {
        $errors['account_type'] = 'Choose brand or influencer';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email address';
    }
    if (strlen($password) < 8) {
        $errors['password'] = 'Password must be at least 8 characters';
    }

    if ($errors !== []) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $errors,
        ]);
        exit;
    }

    $action = 'signup';
    $payload = [
        'email' => $email,
        'password' => $password,
        'user_type' => $accountType === 'influencer' ? '1' : '0',
    ];
}

$endpointMap = [
    'signup' => [
        'url' => 'https://www.opulentprimeproperties.com/influencer_house/web/signup',
        'next_url' => static function (array $sentPayload): string {
            return (($sentPayload['user_type'] ?? '') === '1') ? 'influencer-form.html' : 'signup.html';
        },
    ],
    'email-verify' => [
        'url' => 'https://www.opulentprimeproperties.com/influencer_house/web/email-verify',
    ],
    'phone-registration' => [
        'url' => 'https://www.opulentprimeproperties.com/influencer_house/web/phone-registration',
    ],
    'subscription-plans' => [
        'url' => 'https://www.opulentprimeproperties.com/influencer_house/web/subscription-plans',
    ],
    'country' => [
        'url' => 'https://www.opulentprimeproperties.com/influencer_house/web/country',
    ],
    'province' => [
        'url' => 'https://www.opulentprimeproperties.com/influencer_house/web/province',
    ],
    'industry' => [
        'url' => 'https://www.opulentprimeproperties.com/influencer_house/web/industry',
    ],
    'category' => [
        'url' => 'https://www.opulentprimeproperties.com/influencer_house/web/category',
    ],
    'niche' => [
        'url' => 'https://www.opulentprimeproperties.com/influencer_house/web/niche',
    ],
    'user-document-type' => [
        'url' => 'https://www.opulentprimeproperties.com/influencer_house/web/user-document-type',
    ],
    'brand-document-type' => [
        'url' => 'https://www.opulentprimeproperties.com/influencer_house/api/brand-document-type',
    ],
    'tell-us' => [
        'url' => 'https://www.opulentprimeproperties.com/influencer_house/web/influencers/tell-us',
        'next_url' => 'influencer-form.html',
    ],
    'brand-size' => [
        'url' => 'https://www.opulentprimeproperties.com/influencer_house/web/niche',
        'next_url' => 'signup.html',
        'content_type' => 'text/plain',
    ],
    'brand-type' => [
        'url' => 'https://www.opulentprimeproperties.com/influencer_house/web/brand/brand-type',
        'next_url' => 'signup.html',
    ],
    'register' => [
        'url' => 'https://www.opulentprimeproperties.com/influencer_house/web/brand/register',
        'next_url' => 'signup.html',
    ],
    'final-register' => [
        'url' => 'https://www.opulentprimeproperties.com/influencer_house/web/brand/final-register',
        'next_url' => 'dashboard-brand.html',
    ],
    'email-verify-confirm' => [
        'url' => 'https://www.opulentprimeproperties.com/influencer_house/web/email-verify-confirm',
    ],
    'phone-verify-confirm' => [
        'url' => 'https://www.opulentprimeproperties.com/influencer_house/web/phone-verify-confirm',
    ],
    'brand-upload-documents' => [
        'url' => 'https://www.opulentprimeproperties.com/influencer_house/web/brand/upload-documents',
        'multipart' => true,
        'next_url' => 'signup.html',
    ],
];

if ($action === '' || !isset($endpointMap[$action])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Unknown API action',
        'available_actions' => array_keys($endpointMap),
    ]);
    exit;
}

$endpoint = $endpointMap[$action];
$remoteUrl = $endpoint['url'];
$payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES);
$requestFiles = [];

if ($isMultipart) {
    foreach ($_FILES as $fieldName => $fileConfig) {
        $files = normalizeUploadArray($fileConfig);
        if ($files !== []) {
            $requestFiles[$fieldName] = $files;
        }
    }
}

$statusCode = 0;
$responseBody = false;
$transportError = null;

if (function_exists('curl_init')) {
    $ch = curl_init($remoteUrl);
    $curlOptions = [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
    ];

    if (($endpoint['multipart'] ?? false) === true) {
        $postFields = $payload;

        foreach ($requestFiles as $fieldName => $files) {
            if (count($files) === 1) {
                $file = $files[0];
                $postFields[$fieldName] = new CURLFile($file['tmp_name'], $file['type'], $file['name']);
                continue;
            }

            foreach ($files as $index => $file) {
                $postFields[$fieldName . '[' . $index . ']'] = new CURLFile($file['tmp_name'], $file['type'], $file['name']);
            }
        }

        $curlOptions[CURLOPT_HTTPHEADER] = [
            'Accept: application/json',
        ];
        $curlOptions[CURLOPT_POSTFIELDS] = $postFields;
    } else {
        $curlOptions[CURLOPT_HTTPHEADER] = [
            'Content-Type: ' . (string) ($endpoint['content_type'] ?? 'application/json'),
            'Accept: application/json',
        ];
        $curlOptions[CURLOPT_POSTFIELDS] = $payloadJson;
    }

    curl_setopt_array($ch, $curlOptions);

    $responseBody = curl_exec($ch);
    if ($responseBody === false) {
        $transportError = curl_error($ch);
    }
    $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
} else {
    if (($endpoint['multipart'] ?? false) === true) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Multipart proxy requires cURL support on this server',
            'action' => $action,
        ]);
        exit;
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: ' . (string) ($endpoint['content_type'] ?? 'application/json') . "\r\nAccept: application/json\r\n",
            'content' => $payloadJson,
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
    http_response_code(502);
    echo json_encode([
        'success' => false,
        'message' => 'API service is currently unavailable',
        'action' => $action,
        'error' => $transportError,
    ]);
    exit;
}

$decoded = json_decode((string) $responseBody, true);
$responseData = is_array($decoded) ? $decoded : ['raw' => $responseBody];
$remoteSuccess = $statusCode >= 200 && $statusCode < 300;

if (is_array($decoded) && array_key_exists('success', $decoded)) {
    $remoteSuccess = (bool) $decoded['success'];
}

$message = 'Request completed.';
if (is_array($decoded)) {
    $message = (string) ($decoded['message'] ?? $decoded['msg'] ?? $decoded['response'] ?? $message);
}

$nextUrl = null;
if (isset($endpoint['next_url'])) {
    $nextUrl = is_callable($endpoint['next_url']) ? $endpoint['next_url']($payload) : $endpoint['next_url'];
}

http_response_code($remoteSuccess ? 200 : ($statusCode > 0 ? $statusCode : 400));
echo json_encode([
    'success' => $remoteSuccess,
    'message' => $message,
    'action' => $action,
    'target_url' => $remoteUrl,
    'next_url' => $nextUrl,
    'sent_payload' => $payload,
    'received_files' => array_map(
        static function (array $files): array {
            return array_map(
                static function (array $file): array {
                    return [
                        'name' => $file['name'],
                        'type' => $file['type'],
                        'size' => $file['size'],
                        'error' => $file['error'],
                    ];
                },
                $files
            );
        },
        $requestFiles
    ),
    'data' => $responseData,
], JSON_UNESCAPED_SLASHES);
