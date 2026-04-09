<?php
declare(strict_types=1);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed'
    ]);
    exit;
}

$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
$raw = file_get_contents('php://input');
$payload = [];

if ($raw !== false && $raw !== '') {
    if (stripos($contentType, 'application/json') !== false) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $payload = $decoded;
        }
    } else {
        parse_str($raw, $parsed);
        if (is_array($parsed)) {
            $payload = $parsed;
        }
    }
}

if (!$payload) {
    $payload = $_POST;
}

$name = trim((string)($payload['name'] ?? ''));
$email = trim((string)($payload['email'] ?? ''));
$message = trim((string)($payload['message'] ?? ''));
$source = trim((string)($payload['source'] ?? 'Coming Soon Page'));

if ($name === '' || $email === '' || $message === '') {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'error' => 'Name, email, and message are required.'
    ]);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'error' => 'Please provide a valid email address.'
    ]);
    exit;
}

$to = 'contact@opulentinfluencershouse.com';
$subject = 'New enquiry from ' . $source;

$safeName = str_replace(["\r", "\n"], ' ', $name);
$safeEmail = str_replace(["\r", "\n"], '', $email);
$safeSource = str_replace(["\r", "\n"], ' ', $source);

$bodyLines = [
    'New enquiry received',
    '',
    'Source: ' . $safeSource,
    'Name: ' . $safeName,
    'Email: ' . $safeEmail,
    '',
    'Message:',
    $message
];

$headers = [];
$headers[] = 'From: Opulent Influencer House <noreply@opulentinfluencershouse.com>';
$headers[] = 'Reply-To: ' . $safeName . ' <' . $safeEmail . '>';
$headers[] = 'Content-Type: text/plain; charset=UTF-8';

$sent = mail($to, $subject, implode("\r\n", $bodyLines), implode("\r\n", $headers));

if (!$sent) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Mail sending failed. Server mail may not be configured.'
    ]);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => 'Your enquiry has been sent successfully.'
]);
