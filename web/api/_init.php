<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/helpers/response.php';
require_once $root . '/helpers/request.php';
$config = require $root . '/config/config.php';
$pdo = require $root . '/config/database.php';
return [$pdo, $config];
