<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Database;
use App\Controllers\SportsController;
use App\Controllers\AvailabilityController;
use App\Controllers\AuthController;
use App\Controllers\ReservationsController;

// CORS para consumo pelo Android/Frontend
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Content-Type: application/json; charset=utf-8');

// Responder preflight do navegador/Android
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Normaliza quando projeto está em subpasta (e.g. /sport-courts-api/public)
$script = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
if ($script !== '/' && strpos($uri, $script) === 0) {
    $uri = substr($uri, strlen($script));
}
$uri = '/' . trim($uri, '/');

try {
    $db = new Database();
    $pdo = $db->getConnection();

    // Healthcheck
    if ($method === 'GET' && ($uri === '/' || $uri === '')) {
        echo json_encode([
            'status' => 'ok',
            'service' => 'sport-courts-api',
            'time' => date('c'),
        ]);
        exit;
    }

    // Sports - listagem
    if ($method === 'GET' && $uri === '/sports') {
        $ctrl = new SportsController($pdo);
        echo json_encode(['status' => 'success', 'data' => $ctrl->index()]);
        exit;
    }

    // Availability - usa weekday + start_time/end_time do schema atual
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

    // List "my" reservations (mine=true) ou lista geral
    if ($method === 'GET' && $uri === '/reservations') {
        $ctrl = new ReservationsController($pdo);
        $mine = isset($_GET['mine']) && ($_GET['mine'] === 'true' || $_GET['mine'] === '1');
        if ($mine) {
            echo json_encode($ctrl->mine());
            exit;
        }

        // Lista geral simples (sem filtros, pode adicionar se quiser)
        $stmt = $pdo->query("SELECT id, court_id, user_id, start_datetime, end_datetime, status, total_price FROM reservations ORDER BY start_datetime DESC");
        echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll()]);
        exit;
    }

    // Detalhe de reserva /reservations/{id}
    if ($method === 'GET' && preg_match('#^/reservations/(\d+)$#', $uri, $m)) {
        $id = (int)$m[1];
        $stmt = $pdo->prepare("SELECT id, court_id, user_id, start_datetime, end_datetime, status, total_price FROM reservations WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'error' => ['code' => 'NOT_FOUND', 'message' => 'Reserva não encontrada']]);
            exit;
        }
        echo json_encode(['status' => 'success', 'data' => $row]);
        exit;
    }

    // Cancel reservation /reservations/{id}/cancel
    if ($method === 'PUT' && preg_match('#^/reservations/(\d+)/cancel$#', $uri, $m)) {
        $id = (int)$m[1];
        $ctrl = new ReservationsController($pdo);
        echo json_encode($ctrl->cancel($id));
        exit;
    }

    // PUT /reservations/{id} (atualização completa)
    if ($method === 'PUT' && preg_match('#^/reservations/(\d+)$#', $uri, $m)) {
        $id = (int)$m[1];
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $ctrl = new ReservationsController($pdo);
        echo json_encode($ctrl->updateFull($id, $input));
        exit;
    }

    // PATCH /reservations/{id} (atualização parcial)
    if ($method === 'PATCH' && preg_match('#^/reservations/(\d+)$#', $uri, $m)) {
        $id = (int)$m[1];
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $ctrl = new ReservationsController($pdo);
        echo json_encode($ctrl->updatePartial($id, $input));
        exit;
    }

    // DELETE /reservations/{id}
    if ($method === 'DELETE' && preg_match('#^/reservations/(\d+)$#', $uri, $m)) {
        $id = (int)$m[1];
        $ctrl = new ReservationsController($pdo);
        echo json_encode($ctrl->delete($id));
        exit;
    }

    // 404 padrão
    http_response_code(404);
    echo json_encode(['status' => 'error', 'error' => ['code' => 'NOT_FOUND', 'message' => 'Endpoint não encontrado']]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'error' => ['code' => 'SERVER_ERROR', 'message' => $e->getMessage()]]);
    exit;
}