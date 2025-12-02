<?php
declare(strict_types=1);

namespace App\Controllers;

use PDO;

class ReservationsController
{
    public function __construct(private PDO $pdo) {}

    private function getBearerToken(): ?string
    {
        $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (!$auth || stripos($auth, 'Bearer ') !== 0) return null;
        $token = trim(substr($auth, 7));
        return $token !== '' ? $token : null;
    }

    private function findUserByToken(string $token): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, name, email, role, api_token
             FROM users
             WHERE api_token = :t
             LIMIT 1"
        );
        $stmt->execute([':t' => $token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user ?: null;
    }

    // Exige token válido — responde 401 e exit em caso inválido
    private function requireAuth(): array
    {
        $token = $this->getBearerToken();
        $preview = $token ? substr($token, 0, 12) . (strlen($token) > 12 ? '...' : '') : 'none';
        header('X-Auth-Debug-Token: ' . $preview);

        if (!$token) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['status'=>'error','error'=>['code'=>'AUTH_REQUIRED','message'=>'Token ausente']], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            exit;
        }

        $user = $this->findUserByToken($token);
        if (!$user) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['status'=>'error','error'=>['code'=>'AUTH_INVALID','message'=>'Token inválido']], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            exit;
        }

        header('X-Auth-Debug-User: ' . (string)$user['id']);
        return $user;
    }

    private function userHasAccess(array $user, int $resourceUserId): bool
    {
        if (($user['role'] ?? 'user') === 'admin') return true;
        return (int)$user['id'] === $resourceUserId;
    }

    public function index(): array
    {
        $user = $this->requireAuth();

        $mine = isset($_GET['mine']) ? (int)$_GET['mine'] : 0;
        if ($mine === 1 || (($user['role'] ?? 'user') !== 'admin')) {
            $stmt = $this->pdo->prepare(
                "SELECT r.id, r.court_id, r.user_id, r.start_datetime, r.end_datetime, r.status, r.total_price,
                        s.name AS court_name, r.created_at, r.updated_at
                 FROM reservations r
                 JOIN sports s ON s.id = r.court_id
                 WHERE r.user_id = :uid
                 ORDER BY r.start_datetime DESC"
            );
            $stmt->execute([':uid' => (int)$user['id']]);
        } else {
            $stmt = $this->pdo->query(
                "SELECT r.id, r.court_id, r.user_id, r.start_datetime, r.end_datetime, r.status, r.total_price,
                        s.name AS court_name, r.created_at, r.updated_at
                 FROM reservations r
                 JOIN sports s ON s.id = r.court_id
                 ORDER BY r.start_datetime DESC"
            );
        }

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return ['status' => 'success', 'data' => $rows];
    }

    public function create(array $input): array
    {
        $user = $this->requireAuth();

        $courtId = (int)($input['court_id'] ?? 0);
        $start   = trim((string)($input['start_datetime'] ?? ''));
        $end     = trim((string)($input['end_datetime'] ?? ''));

        if ($courtId <= 0 || $start === '' || $end === '') {
            http_response_code(422);
            return ['status'=>'error','error'=>['code'=>'VALIDATION','message'=>'Campos obrigatórios: court_id, start_datetime, end_datetime']];
        }

        $tsStart = strtotime($start);
        $tsEnd   = strtotime($end);
        if ($tsStart === false || $tsEnd === false || $tsEnd <= $tsStart) {
            http_response_code(422);
            return ['status'=>'error','error'=>['code'=>'VALIDATION','message'=>'Intervalo de datas/horas inválido']];
        }

        $weekday   = (int)date('w', $tsStart);
        $startTime = date('H:i:s', $tsStart);
        $endTime   = date('H:i:s', $tsEnd);

        $slotStmt = $this->pdo->prepare(
            "SELECT price FROM court_availabilities
             WHERE court_id = ? AND weekday = ? AND start_time = ? AND end_time = ?
             LIMIT 1"
        );
        $slotStmt->execute([$courtId, $weekday, $startTime, $endTime]);
        $slot = $slotStmt->fetch(PDO::FETCH_ASSOC);
        if (!$slot) {
            http_response_code(409);
            return ['status'=>'error','error'=>['code'=>'NOT_AVAILABLE','message'=>'Slot indisponível']];
        }

        $confStmt = $this->pdo->prepare(
            "SELECT id FROM reservations
             WHERE court_id = ? AND start_datetime = ? AND end_datetime = ?
               AND status IN ('pending','confirmed')
             LIMIT 1"
        );
        $confStmt->execute([$courtId, $start, $end]);
        if ($confStmt->fetch(PDO::FETCH_ASSOC)) {
            http_response_code(409);
            return ['status'=>'error','error'=>['code'=>'CONFLICT','message'=>'Horário já reservado']];
        }

        $ins = $this->pdo->prepare(
            "INSERT INTO reservations (court_id, user_id, start_datetime, end_datetime, status, total_price, created_at, updated_at)
             VALUES (?, ?, ?, ?, 'pending', ?, NOW(), NOW())"
        );
        $ins->execute([$courtId, (int)$user['id'], $start, $end, $slot['price']]);

        http_response_code(201);
        return ['status'=>'success','data'=>['id'=>(int)$this->pdo->lastInsertId()]];
    }

    public function show(int $id): array
    {
        $user = $this->requireAuth();

        $stmt = $this->pdo->prepare(
            "SELECT r.*, s.name AS court_name
             FROM reservations r
             JOIN sports s ON s.id = r.court_id
             WHERE r.id = ? LIMIT 1"
        );
        $stmt->execute([$id]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$res) {
            http_response_code(404);
            return ['status'=>'error','error'=>['code'=>'NOT_FOUND','message'=>'Reserva não encontrada']];
        }
        if (!$this->userHasAccess($user, (int)$res['user_id'])) {
            http_response_code(403);
            return ['status'=>'error','error'=>['code'=>'FORBIDDEN','message'=>'Sem permissão']];
        }

        return ['status'=>'success','data'=>$res];
    }

    public function updateFull(int $id, array $input): array
    {
        $user = $this->requireAuth();

        $chk = $this->pdo->prepare("SELECT user_id FROM reservations WHERE id=?");
        $chk->execute([$id]);
        $row = $chk->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            http_response_code(404);
            return ['status'=>'error','error'=>['code'=>'NOT_FOUND','message'=>'Reserva não encontrada']];
        }
        if (!$this->userHasAccess($user, (int)$row['user_id'])) {
            http_response_code(403);
            return ['status'=>'error','error'=>['code'=>'FORBIDDEN','message'=>'Sem permissão']];
        }

        $required = ['court_id','start_datetime','end_datetime'];
        foreach ($required as $k) {
            if (!isset($input[$k]) || trim((string)$input[$k]) === '') {
                http_response_code(422);
                return ['status'=>'error','error'=>['code'=>'VALIDATION','message'=>"$k é obrigatório"]];
            }
        }

        $courtId = (int)$input['court_id'];
        $start   = trim((string)$input['start_datetime']);
        $end     = trim((string)$input['end_datetime']);
        $tsStart = strtotime($start);
        $tsEnd   = strtotime($end);
        if ($courtId <= 0 || $tsStart === false || $tsEnd === false || $tsEnd <= $tsStart) {
            http_response_code(422);
            return ['status'=>'error','error'=>['code'=>'VALIDATION','message'=>'Dados inválidos']];
        }

        $weekday   = (int)date('w', $tsStart);
        $startTime = date('H:i:s', $tsStart);
        $endTime   = date('H:i:s', $tsEnd);

        $slotStmt = $this->pdo->prepare(
            "SELECT price FROM court_availabilities
             WHERE court_id = ? AND weekday = ? AND start_time = ? AND end_time = ?
             LIMIT 1"
        );
        $slotStmt->execute([$courtId, $weekday, $startTime, $endTime]);
        $slot = $slotStmt->fetch(PDO::FETCH_ASSOC);
        if (!$slot) {
            http_response_code(409);
            return ['status'=>'error','error'=>['code'=>'NOT_AVAILABLE','message'=>'Slot indisponível']];
        }

        $confStmt = $this->pdo->prepare(
            "SELECT id FROM reservations
             WHERE court_id = ? AND start_datetime = ? AND end_datetime = ?
               AND status IN ('pending','confirmed') AND id <> ?
             LIMIT 1"
        );
        $confStmt->execute([$courtId, $start, $end, $id]);
        if ($confStmt->fetch(PDO::FETCH_ASSOC)) {
            http_response_code(409);
            return ['status'=>'error','error'=>['code'=>'CONFLICT','message'=>'Horário já reservado']];
        }

        $sql = "UPDATE reservations
                   SET court_id = ?, start_datetime = ?, end_datetime = ?, total_price = ?, updated_at = NOW()";
        $params = [$courtId, $start, $end, $slot['price']];

        if (isset($input['status']) && $input['status'] !== '') {
            $sql .= ", status = ?";
            $params[] = $input['status'];
        }
        if (isset($input['user_id']) && (int)$input['user_id'] > 0) {
            if (($user['role'] ?? 'user') !== 'admin') {
                http_response_code(403);
                return ['status'=>'error','error'=>['code'=>'FORBIDDEN','message'=>'Apenas admin pode transferir a reserva']];
            }
            $sql .= ", user_id = ?";
            $params[] = (int)$input['user_id'];
        }

        $sql .= " WHERE id = ?";
        $params[] = $id;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return ['status'=>'success','data'=>['id'=>$id]];
    }

    public function updatePartial(int $id, array $input): array
    {
        $user = $this->requireAuth();

        $chk = $this->pdo->prepare("SELECT * FROM reservations WHERE id=?");
        $chk->execute([$id]);
        $res = $chk->fetch(PDO::FETCH_ASSOC);
        if (!$res) {
            http_response_code(404);
            return ['status'=>'error','error'=>['code'=>'NOT_FOUND','message'=>'Reserva não encontrada']];
        }
        if (!$this->userHasAccess($user, (int)$res['user_id'])) {
            http_response_code(403);
            return ['status'=>'error','error'=>['code'=>'FORBIDDEN','message'=>'Sem permissão']];
        }

        $allowed = ['court_id','start_datetime','end_datetime','status','total_price'];
        $set = [];
        $params = [];

        $newCourtId = isset($input['court_id']) ? (int)$input['court_id'] : (int)$res['court_id'];
        $newStart   = isset($input['start_datetime']) ? (string)$input['start_datetime'] : (string)$res['start_datetime'];
        $newEnd     = isset($input['end_datetime'])   ? (string)$input['end_datetime']   : (string)$res['end_datetime'];

        if ($newCourtId !== (int)$res['court_id'] || $newStart !== (string)$res['start_datetime'] || $newEnd !== (string)$res['end_datetime']) {
            $tsStart = strtotime($newStart);
            $tsEnd   = strtotime($newEnd);
            if ($newCourtId <= 0 || $tsStart === false || $tsEnd === false || $tsEnd <= $tsStart) {
                http_response_code(422);
                return ['status'=>'error','error'=>['code'=>'VALIDATION','message'=>'Dados inválidos']];
            }
            $weekday   = (int)date('w', $tsStart);
            $startTime = date('H:i:s', $tsStart);
            $endTime   = date('H:i:s', $tsEnd);

            $slotStmt = $this->pdo->prepare(
                "SELECT price FROM court_availabilities
                 WHERE court_id = ? AND weekday = ? AND start_time = ? AND end_time = ?
                 LIMIT 1"
            );
            $slotStmt->execute([$newCourtId, $weekday, $startTime, $endTime]);
            $slot = $slotStmt->fetch(PDO::FETCH_ASSOC);
            if (!$slot) {
                http_response_code(409);
                return ['status'=>'error','error'=>['code'=>'NOT_AVAILABLE','message'=>'Slot indisponível']];
            }

            $confStmt = $this->pdo->prepare(
                "SELECT id FROM reservations
                 WHERE court_id = ? AND start_datetime = ? AND end_datetime = ?
                   AND status IN ('pending','confirmed') AND id <> ?
                 LIMIT 1"
            );
            $confStmt->execute([$newCourtId, $newStart, $newEnd, $id]);
            if ($confStmt->fetch(PDO::FETCH_ASSOC)) {
                http_response_code(409);
                return ['status'=>'error','error'=>['code'=>'CONFLICT','message'=>'Horário já reservado']];
            }

            if (!isset($input['total_price'])) {
                $set[] = "total_price = ?";
                $params[] = $slot['price'];
            }
        }

        foreach ($allowed as $f) {
            if (array_key_exists($f, $input)) {
                if ($f === 'user_id') continue; // transferência de dono só em updateFull com admin
                $set[] = "$f = ?";
                $params[] = $input[$f];
            }
        }

        if (($user['role'] ?? 'user') === 'admin' && array_key_exists('user_id', $input)) {
            $set[] = "user_id = ?";
            $params[] = (int)$input['user_id'];
        }

        if (!$set) {
            http_response_code(422);
            return ['status'=>'error','error'=>['code'=>'VALIDATION','message'=>'Nenhum campo para atualizar']];
        }

        $params[] = $id;
        $sql = "UPDATE reservations SET " . implode(', ', $set) . ", updated_at = NOW() WHERE id = ?";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return ['status'=>'success','data'=>['id'=>$id]];
    }

    public function cancel(int $id): array
    {
        $user = $this->requireAuth();

        $stmt = $this->pdo->prepare(
            "SELECT id, user_id, status
             FROM reservations
             WHERE id = ? AND status IN ('pending','confirmed')
             LIMIT 1"
        );
        $stmt->execute([$id]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$res) {
            http_response_code(404);
            return ['status'=>'error','error'=>['code'=>'NOT_FOUND_OR_INVALID','message'=>'Reserva não encontrada ou já cancelada']];
        }
        if (!$this->userHasAccess($user, (int)$res['user_id'])) {
            http_response_code(403);
            return ['status'=>'error','error'=>['code'=>'FORBIDDEN','message'=>'Sem permissão']];
        }

        $up = $this->pdo->prepare("UPDATE reservations SET status = 'cancelled', updated_at = NOW() WHERE id = ?");
        $up->execute([$id]);

        return ['status'=>'success','data'=>['cancelled_id'=>$id]];
    }

    public function delete(int $id): array
    {
        $user = $this->requireAuth();

        $chk = $this->pdo->prepare("SELECT user_id FROM reservations WHERE id = ?");
        $chk->execute([$id]);
        $row = $chk->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            http_response_code(404);
            return ['status'=>'error','error'=>['code'=>'NOT_FOUND','message'=>'Reserva não encontrada']];
        }
        if (!$this->userHasAccess($user, (int)$row['user_id'])) {
            http_response_code(403);
            return ['status'=>'error','error'=>['code'=>'FORBIDDEN','message'=>'Sem permissão']];
        }

        $stmt = $this->pdo->prepare("DELETE FROM reservations WHERE id = ?");
        $stmt->execute([$id]);

        http_response_code(200);
        return ['status'=>'success','data'=>['id'=>$id]];
    }

    public function mine(): array
    {
        $_GET['mine'] = 1;
        return $this->index();
    }
}