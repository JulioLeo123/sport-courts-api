<?php
declare(strict_types=1);

namespace App\Controllers;

use PDO;

class AvailabilityController
{
    public function __construct(private PDO $pdo) {}

    public function getAvailability(string $date, ?int $clubId, ?int $sportId): array
    {
        $weekday = (int)date('w', strtotime($date));

        // Base query
        $sql = "SELECT c.id AS court_id, c.name AS court_name,
                       ca.start_time AS start, ca.end_time AS end, ca.price
                FROM court_availabilities ca
                JOIN courts c ON c.id = ca.court_id
                WHERE ca.weekday = ?";
        $params = [$weekday];

        // Tente aplicar sport_id se a coluna existir
        try {
            $colCheck = $this->pdo->query("SHOW COLUMNS FROM courts LIKE 'sport_id'")->fetch();
            if ($colCheck && $sportId !== null) {
                $sql .= " AND c.sport_id = ?";
                $params[] = $sportId;
            }
        } catch (\Throwable $e) {
            // Ignora filtro se falhar
        }

        $sql .= " ORDER BY ca.start_time ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}