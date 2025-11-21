<?php
require __DIR__ . '/../vendor/autoload.php';
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $l) {
        if (trim($l) === '' || $l[0] === '#') continue;
        [$k, $v] = explode('=', $l, 2);
        putenv(trim($k) . '=' . trim($v));
    }
}