<?php
echo "PHP: " . PHP_VERSION . PHP_EOL;
echo "CWD: " . getcwd() . PHP_EOL;

echo PHP_EOL . "Require vendor/autoload.php... ";
$ok = @require __DIR__ . '/vendor/autoload.php';
echo ($ok ? "OK" : "FAIL") . PHP_EOL;

$psr4 = @require __DIR__ . '/vendor/composer/autoload_psr4.php';
echo PHP_EOL . "PSR-4 mapping for 'App\\\\':" . PHP_EOL;
var_export($psr4['App\\'] ?? null);
echo PHP_EOL . PHP_EOL;

$expected = realpath(__DIR__) . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Repositories' . DIRECTORY_SEPARATOR . 'SportsRepository.php';
echo "Expected file path: " . $expected . PHP_EOL;
echo "file_exists: " . (file_exists($expected) ? 'YES' : 'NO') . PHP_EOL;
echo "is_readable: " . (is_readable($expected) ? 'YES' : 'NO') . PHP_EOL;

$bytes = @file_get_contents($expected, false, null, 0, 4);
if ($bytes === false) {
    echo "Could not read file contents (or file empty)" . PHP_EOL;
} else {
    $hex = bin2hex(substr($bytes, 0, 3));
    echo "First 3 bytes (hex): $hex" . PHP_EOL;
    echo "(UTF-8 BOM = efbbbf hex -> 0xef 0xbb 0xbf)" . PHP_EOL;
}

echo PHP_EOL . "class_exists before autoload: " . (class_exists('App\\\\Repositories\\\\SportsRepository') ? 'YES' : 'NO') . PHP_EOL;

echo PHP_EOL . "Attempting to require the file directly..." . PHP_EOL;
$req = @require_once $expected;
echo "require_once returned: " . ($req === 1 || $req === null ? 'OK' : var_export($req, true)) . PHP_EOL;

echo "class_exists after require: " . (class_exists('App\\\\Repositories\\\\SportsRepository') ? 'YES' : 'NO') . PHP_EOL;

echo PHP_EOL . "Done." . PHP_EOL;