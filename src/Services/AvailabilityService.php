<?php
namespace App\Services;

class AvailabilityService
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Basic availability: returns slots for each active court filtered by club/sport.
     * This implementation also removes slots that overlap existing reservations or blackout_dates.
     */
    public function getAvailabilityForDate(string $date, ?int $clubId = null, ?int $sportId = null): array
    {
        $sql = "SELECT c.id, c.name, c.open_time, c.close_time, c.slot_minutes, c.price_per_slot, s.name AS sport_name, cl.name AS club_name
                FROM courts c
                JOIN sports s ON s.id = c.sport_id
                JOIN clubs cl ON cl.id = c.club_id
                WHERE c.active = 1";
        $params = [];
        if ($clubId) { $sql .= " AND c.club_id = ?"; $params[] = $clubId; }
        if ($sportId) { $sql .= " AND c.sport_id = ?"; $params[] = $sportId; }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $courts = $stmt->fetchAll();

        $result = [];
        foreach ($courts as $c) {
            $open = new \DateTimeImmutable($date . ' ' . $c['open_time']);
            $close = new \DateTimeImmutable($date . ' ' . $c['close_time']);
            $slot = (int)$c['slot_minutes'];
            $slots = [];
            $current = $open;
            while ($current < $close) {
                $end = $current->modify("+{$slot} minutes");
                if ($end > $close) break;

                // check reservation conflict for this slot
                $hasReservation = $this->slotHasReservation((int)$c['id'], $current->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s'));
                $isBlackout = $this->slotIsBlackout((int)$c['id'], $current->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s'));

                if (! $hasReservation && ! $isBlackout) {
                    $slots[] = [
                        'start' => $current->format('Y-m-d H:i:s'),
                        'end' => $end->format('Y-m-d H:i:s'),
                        'price' => (float)$c['price_per_slot']
                    ];
                }
                $current = $end;
            }

            $result[] = [
                'court_id' => (int)$c['id'],
                'court_name' => $c['name'],
                'club_name' => $c['club_name'],
                'sport_name' => $c['sport_name'],
                'slots' => $slots
            ];
        }

        return $result;
    }

    private function slotHasReservation(int $courtId, string $start, string $end): bool
    {
        $sql = "SELECT 1 FROM reservations WHERE court_id = ? AND status IN ('CREATED','CONFIRMED')
                AND NOT (end_datetime <= ? OR start_datetime >= ?) LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$courtId, $start, $end]);
        return (bool)$stmt->fetchColumn();
    }

    private function slotIsBlackout(int $courtId, string $start, string $end): bool
    {
        $sql = "SELECT 1 FROM blackout_dates WHERE court_id = ? 
                AND NOT (end_datetime <= ? OR start_datetime >= ?) LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$courtId, $start, $end]);
        return (bool)$stmt->fetchColumn();
    }
}