<?php

declare(strict_types=1);

function body(): array
{
    $raw = file_get_contents('php://input');
    $decoded = $raw !== false ? json_decode($raw, true) : null;
    return is_array($decoded) ? $decoded : [];
}

function get_int(string $key): int
{
    return (int) ($_GET[$key] ?? 0);
}
