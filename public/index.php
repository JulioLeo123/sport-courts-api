<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Database;
use App\Controllers\SportsController;
use App\Controllers\AvailabilityController;
use App\Controllers\AuthController;
use App\Controllers\ReservationsController;

// CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Content-Type: application/json; charset=utf-8');
header('X-Router-Version: 3'); // debug

// Preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Normaliza subpasta (ex.: /sport-courts-api/public)
$script = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
if ($script !== '/' && strpos($uri, $script) === 0) {
    $uri = substr($uri, strlen($script));
}
$uri = '/' . trim($uri, '/');

try {
    $db = new Database();
    $pdo = $db->getConnection();

    // Helper de auth no router (reforço)
    $requireAuth = function() use ($pdo) : array {
        $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        $tokenPreview = $auth ? substr($auth, 0, 20) . (strlen($auth) > 20 ? '...' : '') : 'none';
        header('X-Auth-Debug-Token: ' . $tokenPreview);
        if (!$auth || stripos($auth, 'Bearer ') !== 0) {
            http_response_code(401);
            echo json_encode(['status'=>'error','error'=>['code'=>'AUTH_REQUIRED','message'=>'Token ausente']], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            exit;
        }
        $token = trim(substr($auth, 7));
        if ($token === '') {
            http_response_code(401);
            echo json_encode(['status'=>'error','error'=>['code'=>'AUTH_REQUIRED','message'=>'Token ausente']], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            exit;
        }
        $stmt = $pdo->prepare("SELECT id, role, api_token FROM users WHERE api_token = :t LIMIT 1");
        $stmt->execute([':t' => $token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            http_response_code(401);
            echo json_encode(['status'=>'error','error'=>['code'=>'AUTH_INVALID','message'=>'Token inválido']], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            exit;
        }
        header('X-Auth-Debug-User: ' . (string)$user['id']);
        return $user;
    };

    // Healthcheck
    if ($method === 'GET' && ($uri === '/' || $uri === '')) {
        echo json_encode(['status'=>'ok','service'=>'sport-courts-api','time'=>date('c')], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        exit;
    }

    // Públicos: sports, availability, auth
    if ($method === 'GET' && $uri === '/sports') {
        $ctrl = new SportsController($pdo);
        echo json_encode(['status'=>'success','data'=>$ctrl->index()], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        exit;
    }
    if ($method === 'GET' && $uri === '/availability') {
        $date = $_GET['date'] ?? date('Y-m-d');
        $clubId = isset($_GET['club_id']) ? (int)$_GET['club_id'] : null;
        $sportId = isset($_GET['sport_id']) ? (int)$_GET['sport_id'] : null;
        $ctrl = new AvailabilityController($pdo);
        echo json_encode(['status'=>'success','data'=>$ctrl->getAvailability($date, $clubId, $sportId)], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        exit;
    }
    if ($method === 'POST' && $uri === '/auth/register') {
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $ctrl = new AuthController($pdo);
        echo json_encode($ctrl->register($input), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        exit;
    }
    if ($method === 'POST' && $uri === '/auth/login') {
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $ctrl = new AuthController($pdo);
        echo json_encode($ctrl->login($input), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        exit;
    }

    // Protegidos: qualquer rota que comece com /reservations
    if (strpos($uri, '/reservations') === 0) {
        header('X-Router-Guard: on'); // debug
        $requireAuth(); // reforço no router

        $ctrl = new ReservationsController($pdo);

        if ($method === 'GET' && $uri === '/reservations') {
            echo json_encode($ctrl->index(), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            exit;
        }
        if ($method === 'POST' && $uri === '/reservations') {
            $input = json_decode(file_get_contents('php://input'), true) ?: [];
            echo json_encode($ctrl->create($input), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            exit;
        }
        if ($method === 'GET' && preg_match('#^/reservations/(\d+)$#', $uri, $m)) {
            echo json_encode($ctrl->show((int)$m[1]), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            exit;
        }
        if ($method === 'PUT' && preg_match('#^/reservations/(\d+)$#', $uri, $m)) {
            $input = json_decode(file_get_contents('php://input'), true) ?: [];
            echo json_encode($ctrl->updateFull((int)$m[1], $input), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            exit;
        }
        if ($method === 'PATCH' && preg_match('#^/reservations/(\d+)$#', $uri, $m)) {
            $input = json_decode(file_get_contents('php://input'), true) ?: [];
            echo json_encode($ctrl->updatePartial((int)$m[1], $input), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            exit;
        }
        if ($method === 'PUT' && preg_match('#^/reservations/(\d+)/cancel$#', $uri, $m)) {
            echo json_encode($ctrl->cancel((int)$m[1]), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            exit;
        }
        if ($method === 'DELETE' && preg_match('#^/reservations/(\d+)$#', $uri, $m)) {
            echo json_encode($ctrl->delete((int)$m[1]), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            exit;
        }
    }

    // 404 padrão
    http_response_code(404);
    echo json_encode(['status'=>'error','error'=>['code'=>'NOT_FOUND','message'=>'Endpoint não encontrado']], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['status'=>'error','error'=>['code'=>'SERVER_ERROR','message'=>$e->getMessage()]], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}