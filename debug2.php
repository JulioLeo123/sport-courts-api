<?php
echo "PHP: " . PHP_VERSION . PHP_EOL;
echo "CWD: " . getcwd() . PHP_EOL;

echo PHP_EOL . "Declared classes before require: " . count(get_declared_classes()) . PHP_EOL;

echo PHP_EOL . "Require vendor/autoload.php... ";
$ok = @require __DIR__ . '/vendor/autoload.php';
echo ($ok ? "OK" : "FAIL") . PHP_EOL;

echo PHP_EOL . "Declared classes after require: " . count(get_declared_classes()) . PHP_EOL;

$target = 'App\\Repositories\\SportsRepository';
echo PHP_EOL . "class_exists('$target'): " . (class_exists($target) ? 'YES' : 'NO') . PHP_EOL;

if (class_exists($target)) {
    $r = new \ReflectionClass($target);
    echo "Reflection filename: " . $r->getFileName() . PHP_EOL;
    echo "Reflection start line: " . $r->getStartLine() . PHP_EOL;
}

echo PHP_EOL . "Included files list:" . PHP_EOL;
$files = get_included_files();
foreach ($files as $f) {
    echo $f . PHP_EOL;
}

echo PHP_EOL . "Searching for string 'SportsRepository' in vendor/composer files..." . PHP_EOL;
$composerFiles = ['vendor/composer/autoload_classmap.php', 'vendor/composer/autoload_files.php', 'vendor/composer/autoload_psr4.php', 'vendor/composer/autoload_static.php'];
foreach ($composerFiles as $cf) {
    if (file_exists($cf)) {
        echo "---- $cf ----" . PHP_EOL;
        echo substr(file_get_contents($cf), 0, 800) . PHP_EOL;
    } else {
        echo "No file: $cf" . PHP_EOL;
    }
}

echo PHP_EOL . "Done." . PHP_EOL;