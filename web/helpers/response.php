<?php

declare(strict_types=1);

function json_response(array $data, int $code = 200): void
{
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function json_ok(string $message, array $data = [], int $code = 200): void
{
    json_response(array_merge(['success' => true, 'message' => $message], $data), $code);
}

function json_fail(string $message, array $errors = [], int $code = 400): void
{
    $out = ['success' => false, 'message' => $message];
    if ($errors !== []) {
        $out['errors'] = $errors;
    }
    json_response($out, $code);
}

function json_server_error(bool $debug, ?string $detail = null): void
{
    $message = $debug && $detail ? $detail : 'Something went wrong';
    json_response(['success' => false, 'message' => $message], 500);
}
