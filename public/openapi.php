<?php
declare(strict_types=1);

// Desativar warnings para não poluir JSON
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', '0');

require __DIR__ . '/../vendor/autoload.php';

use OpenApi\Generator;

$openapi = Generator::scan([__DIR__ . '/../src']);

header('Content-Type: application/json; charset=utf-8');
echo $openapi->toJson();