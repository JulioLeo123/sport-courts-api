<?php
declare(strict_types=1);

namespace App\Controllers;

use PDO;

class ReservationsController
{
    public function __construct(private PDO $pdo) {}

    private function currentUserId(): ?int
    {
        $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? null;
        $rawId = \App\Controllers\AuthController::userIdFromAuthHeader($auth);
        if ($rawId === null) return null;

        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$rawId]);
        return $stmt->fetch() ? $rawId : null;
    }

    public function create(array $input): array
    {
        $uid = $this->currentUserId();
        if ($uid === null) {
            http_response_code(401);
            return ['status'=>'error','error'=>['code'=>'AUTH_REQUIRED','message'=>'Token ausente ou inválido']];
        }

        $courtId = (int)($input['court_id'] ?? 0);
        $start   = $input['start_datetime'] ?? '';
        $end     = $input['end_datetime'] ?? '';

        if ($courtId <= 0 || $start === '' || $end === '') {
            http_response_code(422);
            return ['status'=>'error','error'=>['code'=>'VALIDATION','message'=>'Campos obrigatórios']];
        }

        // Derivar weekday + horários do schema de availability
        $weekday   = (int)date('w', strtotime($start));
        $startTime = date('H:i:s', strtotime($start));
        $endTime   = date('H:i:s', strtotime($end));

        // Verificar se existe disponibilidade correspondente
        $slotStmt = $this->pdo->prepare(
            "SELECT price FROM court_availabilities
             WHERE court_id = ? AND weekday = ? AND start_time = ? AND end_time = ?"
        );
        $slotStmt->execute([$courtId, $weekday, $startTime, $endTime]);
        $slot = $slotStmt->fetch();

        if (!$slot) {
            http_response_code(409);
            return ['status'=>'error','error'=>['code'=>'NOT_AVAILABLE','message'=>'Slot indisponível']];
        }

        // Checar conflito com reservas existentes (pending / confirmed)
        $confStmt = $this->pdo->prepare(
            "SELECT id FROM reservations
             WHERE court_id = ? AND start_datetime = ? AND end_datetime = ?
               AND status IN ('pending','confirmed')
             LIMIT 1"
        );
        $confStmt->execute([$courtId, $start, $end]);
        if ($confStmt->fetch()) {
            http_response_code(409);
            return ['status'=>'error','error'=>['code'=>'CONFLICT','message'=>'Horário já reservado']];
        }

        // Criar reserva (status inicial = pending)
        $ins = $this->pdo->prepare(
            "INSERT INTO reservations (court_id, user_id, start_datetime, end_datetime, status, total_price, created_at, updated_at)
             VALUES (?, ?, ?, ?, 'pending', ?, NOW(), NOW())"
        );
        $ins->execute([$courtId, $uid, $start, $end, $slot['price']]);

        return ['status'=>'success','data'=>['id'=>(int)$this->pdo->lastInsertId()]];
    }

    public function mine(): array
    {
        $uid = $this->currentUserId();
        if ($uid === null) {
            http_response_code(401);
            return ['status'=>'error','error'=>['code'=>'AUTH_REQUIRED','message'=>'Token ausente']];
        }

        $stmt = $this->pdo->prepare(
            "SELECT id, court_id, start_datetime, end_datetime, status, total_price
             FROM reservations
             WHERE user_id = ?
             ORDER BY start_datetime DESC"
        );
        $stmt->execute([$uid]);
        $rows = $stmt->fetchAll();

        return ['status'=>'success','data'=>$rows];
    }

    public function cancel(int $id): array
    {
        $uid = $this->currentUserId();
        if ($uid === null) {
            http_response_code(401);
            return ['status'=>'error','error'=>['code'=>'AUTH_REQUIRED','message'=>'Token ausente']];
        }

        $stmt = $this->pdo->prepare(
            "SELECT id FROM reservations
             WHERE id = ? AND user_id = ? AND status IN ('pending','confirmed')"
        );
        $stmt->execute([$id, $uid]);
        $res = $stmt->fetch();
        if (!$res) {
            http_response_code(404);
            return ['status'=>'error','error'=>['code'=>'NOT_FOUND_OR_FORBIDDEN','message'=>'Reserva não encontrada ou inválida']];
        }

        $up = $this->pdo->prepare("UPDATE reservations SET status = 'cancelled', updated_at = NOW() WHERE id = ?");
        $up->execute([$id]);

        return ['status'=>'success','data'=>['cancelled_id'=>$id]];
    }

    // Atualização completa (PUT)
    public function updateFull(int $id, array $input): array
    {
        $uid = $this->currentUserId();
        if ($uid === null) {
            http_response_code(401);
            return ['status'=>'error','error'=>['code'=>'AUTH_REQUIRED','message'=>'Token ausente']];
        }

        $required = ['user_id','court_id','start_datetime','end_datetime'];
        foreach ($required as $k) {
            if (!isset($input[$k]) || $input[$k] === '') {
                http_response_code(422);
                return ['status'=>'error','error'=>['code'=>'VALIDATION','message'=>"$k é obrigatório"]];
            }
        }

        // Verifica se a reserva existe e pertence ao user (ou permitir admin depois)
        $chk = $this->pdo->prepare("SELECT user_id FROM reservations WHERE id=?");
        $chk->execute([$id]);
        $row = $chk->fetch();
        if (!$row) {
            http_response_code(404);
            return ['status'=>'error','error'=>['code'=>'NOT_FOUND','message'=>'Reserva não encontrada']];
        }

        $sql = "UPDATE reservations SET user_id=?, court_id=?, start_datetime=?, end_datetime=?";
        $params = [(int)$input['user_id'], (int)$input['court_id'], $input['start_datetime'], $input['end_datetime']];

        if (isset($input['status'])) {
            $sql .= ", status=?";
            $params[] = $input['status'];
        }
        if (isset($input['total_price'])) {
            $sql .= ", total_price=?";
            $params[] = $input['total_price'];
        }
        $sql .= ", updated_at=NOW() WHERE id=?";
        $params[] = $id;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return ['status'=>'success','data'=>['id'=>$id]];
    }

    // Atualização parcial (PATCH)
    public function updatePartial(int $id, array $input): array
    {
        $uid = $this->currentUserId();
        if ($uid === null) {
            http_response_code(401);
            return ['status'=>'error','error'=>['code'=>'AUTH_REQUIRED','message'=>'Token ausente']];
        }

        $allowed = ['user_id','court_id','start_datetime','end_datetime','status','total_price'];
        $set = [];
        $params = [];
        foreach ($allowed as $f) {
            if (array_key_exists($f, $input)) {
                $set[] = "$f = ?";
                $params[] = $input[$f];
            }
        }
        if (!$set) {
            http_response_code(422);
            return ['status'=>'error','error'=>['code'=>'VALIDATION','message'=>'Nenhum campo para atualizar']];
        }
        $params[] = $id;
        $sql = "UPDATE reservations SET " . implode(', ',$set) . ", updated_at=NOW() WHERE id=?";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return ['status'=>'success','data'=>['id'=>$id]];
    }

    public function delete(int $id): array
    {
        $uid = $this->currentUserId();
        if ($uid === null) {
            http_response_code(401);
            return ['status'=>'error','error'=>['code'=>'AUTH_REQUIRED','message'=>'Token ausente']];
        }

        $stmt = $this->pdo->prepare("DELETE FROM reservations WHERE id=? AND user_id=?");
        $stmt->execute([$id, $uid]);

        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            return ['status'=>'error','error'=>['code'=>'NOT_FOUND','message'=>'Reserva não encontrada']];
        }

        return ['status'=>'success','data'=>['id'=>$id]];
    }
}