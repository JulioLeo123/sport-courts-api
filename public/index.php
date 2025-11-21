<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Database;
use App\Controllers\SportsController;
use App\Controllers\AvailabilityController;
use App\Controllers\AuthController;
use App\Controllers\ReservationsController;

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Normalize when project is in subfolder (e.g. /sport-courts-api/public)
$script = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
if ($script !== '/' && strpos($uri, $script) === 0) {
    $uri = substr($uri, strlen($script));
}
$uri = '/' . trim($uri, '/');

try {
    $db = new Database();
    $pdo = $db->getConnection();

    // Sports
    if ($method === 'GET' && $uri === '/sports') {
        $ctrl = new SportsController($pdo);
        echo json_encode(['status' => 'success', 'data' => $ctrl->index()]);
        exit;
    }

    // Availability
    if ($method === 'GET' && $uri === '/availability') {
        $date = $_GET['date'] ?? date('Y-m-d');
        $clubId = isset($_GET['club_id']) ? (int)$_GET['club_id'] : null;
        $sportId = isset($_GET['sport_id']) ? (int)$_GET['sport_id'] : null;
        $ctrl = new AvailabilityController($pdo);
        echo json_encode(['status' => 'success', 'data' => $ctrl->getAvailability($date, $clubId, $sportId)]);
        exit;
    }

    // Auth register
    if ($method === 'POST' && $uri === '/auth/register') {
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $ctrl = new AuthController($pdo);
        echo json_encode($ctrl->register($input));
        exit;
    }

    // Auth login
    if ($method === 'POST' && $uri === '/auth/login') {
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $ctrl = new AuthController($pdo);
        echo json_encode($ctrl->login($input));
        exit;
    }

    // Create reservation
    if ($method === 'POST' && $uri === '/reservations') {
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $ctrl = new ReservationsController($pdo);
        echo json_encode($ctrl->create($input));
        exit;
    }

    // List my reservations
    if ($method === 'GET' && $uri === '/reservations') {
        $mine = isset($_GET['mine']) && ($_GET['mine'] === 'true' || $_GET['mine'] === '1');
        if ($mine) {
            $ctrl = new ReservationsController($pdo);
            echo json_encode(['status' => 'success', 'data' => $ctrl->mine()]);
            exit;
        }
    }

    // Cancel reservation
    // match /reservations/{id}/cancel
    if ($method === 'PUT' && preg_match('#^/reservations/(\d+)/cancel$#', $uri, $m)) {
        $id = (int)$m[1];
        $ctrl = new ReservationsController($pdo);
        echo json_encode($ctrl->cancel($id));
        exit;
    }

    http_response_code(404);
    echo json_encode(['status' => 'error', 'error' => ['code' => 'NOT_FOUND', 'message' => 'Endpoint não encontrado']]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'error' => ['code' => 'SERVER_ERROR', 'message' => $e->getMessage()]]);
    exit;
}