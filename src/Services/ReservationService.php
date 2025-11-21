<?php
namespace App\Services;

use App\Repositories\ReservationsRepository;

class ReservationService
{
    private \PDO $pdo;
    private ReservationsRepository $repo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->repo = new ReservationsRepository($pdo);
    }

    /**
     * Create a reservation inside a transaction with conflict checks.
     * Returns reservation id on success.
     * Throws \RuntimeException on conflict/validation.
     */
    public function createReservation(int $userId, int $courtId, string $start, string $end): int
    {
        // Basic validation
        $startDt = new \DateTimeImmutable($start);
        $endDt = new \DateTimeImmutable($end);
        if ($endDt <= $startDt) {
            throw new \InvalidArgumentException('End must be after start');
        }

        // Retrieve court data to calculate slot and price
        $stmt = $this->pdo->prepare('SELECT slot_minutes, price_per_slot, open_time, close_time FROM courts WHERE id = ? AND active = 1 LIMIT 1');
        $stmt->execute([$courtId]);
        $court = $stmt->fetch();
        if (!$court) throw new \RuntimeException('Court not found or inactive');

        // Check within open/close
        $date = $startDt->format('Y-m-d');
        $open = new \DateTimeImmutable($date . ' ' . $court['open_time']);
        $close = new \DateTimeImmutable($date . ' ' . $court['close_time']);
        if ($startDt < $open || $endDt > $close) {
            throw new \InvalidArgumentException('Interval outside court open hours');
        }

        // Check slot multiple
        $slotMinutes = (int)$court['slot_minutes'];
        $intervalMinutes = (int)$endDt->getTimestamp() - (int)$startDt->getTimestamp();
        $intervalMinutes = (int)($intervalMinutes / 60);
        if ($intervalMinutes % $slotMinutes !== 0) {
            throw new \InvalidArgumentException('Interval must be multiple of slot_minutes');
        }

        // Transactional conflict check & insert
        try {
            $this->pdo->beginTransaction();

            // Lock relevant reservations for this court (simple approach)
            $lockSql = 'SELECT id FROM reservations WHERE court_id = ? FOR UPDATE';
            $lockStmt = $this->pdo->prepare($lockSql);
            $lockStmt->execute([$courtId]);

            // Check conflicts
            if ($this->repo->hasConflict($courtId, $start, $end)) {
                $this->pdo->rollBack();
                throw new \RuntimeException('Conflict with existing reservation');
            }

            // Also check blackout_dates
            $blkSql = "SELECT 1 FROM blackout_dates WHERE court_id = ? AND NOT (end_datetime <= ? OR start_datetime >= ?) LIMIT 1";
            $blkStmt = $this->pdo->prepare($blkSql);
            $blkStmt->execute([$courtId, $start, $end]);
            if ($blkStmt->fetchColumn()) {
                $this->pdo->rollBack();
                throw new \RuntimeException('Court is blocked for the selected interval');
            }

            // Calculate total
            $slots = $intervalMinutes / $slotMinutes;
            $price = (float)$court['price_per_slot'];
            $total = $price * $slots;

            $reservationId = $this->repo->create($userId, $courtId, $start, $end, $total);

            $this->pdo->commit();
            return $reservationId;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }
}