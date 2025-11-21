<?php
namespace App\Repositories;

class ReservationsRepository
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function hasConflict(int $courtId, string $start, string $end): bool
    {
        $sql = "SELECT 1 FROM reservations WHERE court_id = ? AND status IN ('CREATED','CONFIRMED')
                AND NOT (end_datetime <= ? OR start_datetime >= ?) LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$courtId, $start, $end]);
        return (bool)$stmt->fetchColumn();
    }

    public function create(int $userId, int $courtId, string $start, string $end, float $total): int
    {
        // Corrigido: seis placeholders para seis colunas (user_id,court_id,start_datetime,end_datetime,total,status)
        $stmt = $this->pdo->prepare('INSERT INTO reservations (user_id,court_id,start_datetime,end_datetime,total,status) VALUES (?,?,?,?,?,?);');
        $stmt->execute([$userId, $courtId, $start, $end, $total, 'CREATED']);
        return (int)$this->pdo->lastInsertId();
    }

    public function findByUser(int $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT r.*, c.name AS court_name, cl.name AS club_name, s.name AS sport_name
                                     FROM reservations r
                                     JOIN courts c ON c.id = r.court_id
                                     JOIN clubs cl ON cl.id = c.club_id
                                     JOIN sports s ON s.id = c.sport_id
                                     WHERE r.user_id = ? ORDER BY r.start_datetime DESC');
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM reservations WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function cancel(int $id): bool
    {
        $stmt = $this->pdo->prepare('UPDATE reservations SET status = ? WHERE id = ?');
        return $stmt->execute(['CANCELED', $id]);
    }
}