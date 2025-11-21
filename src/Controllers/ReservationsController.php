<?php
namespace App\Controllers;

use App\Auth;
use App\Services\ReservationService;
use App\Repositories\ReservationsRepository;

class ReservationsController
{
    private \PDO $pdo;
    private Auth $auth;
    private ReservationService $service;
    private ReservationsRepository $repo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->auth = new Auth($pdo);
        $this->service = new ReservationService($pdo);
        $this->repo = new ReservationsRepository($pdo);
    }

    private function getBearerToken(): ?string
    {
        $h = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['Authorization'] ?? null);
        if (!$h) return null;
        if (stripos($h, 'Bearer ') === 0) return trim(substr($h, 7));
        return null;
    }

    private function requireUser(): ?array
    {
        $token = $this->getBearerToken();
        $user = $this->auth->findUserByToken($token);
        if (!$user) {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'error' => ['code' => 'UNAUTHORIZED', 'message' => 'Token inválido']]);
            exit;
        }
        return $user;
    }

    public function create(array $input): array
    {
        $user = $this->requireUser();
        if (empty($input['court_id']) || empty($input['start_datetime']) || empty($input['end_datetime'])) {
            http_response_code(422);
            return ['error' => 'Missing fields'];
        }
        try {
            $reservationId = $this->service->createReservation((int)$user['id'], (int)$input['court_id'], $input['start_datetime'], $input['end_datetime']);
            http_response_code(201);
            return ['id' => $reservationId];
        } catch (\InvalidArgumentException $ex) {
            http_response_code(422);
            return ['error' => $ex->getMessage()];
        } catch (\RuntimeException $ex) {
            http_response_code(409);
            return ['error' => $ex->getMessage()];
        } catch (\Throwable $ex) {
            http_response_code(500);
            return ['error' => $ex->getMessage()];
        }
    }

    public function mine(): array
    {
        $user = $this->requireUser();
        return $this->repo->findByUser((int)$user['id']);
    }

    public function cancel(int $id): array
    {
        $user = $this->requireUser();
        $reservation = $this->repo->findById($id);
        if (!$reservation) {
            http_response_code(404);
            return ['error' => 'Reservation not found'];
        }
        if ((int)$reservation['user_id'] !== (int)$user['id'] && ($user['role'] ?? '') !== 'ADMIN') {
            http_response_code(403);
            return ['error' => 'Forbidden'];
        }
        $ok = $this->repo->cancel($id);
        return ['ok' => (bool)$ok];
    }
}