<?php
namespace App\Controllers;

use App\Services\AvailabilityService;

class AvailabilityController
{
    private \PDO $pdo;
    private AvailabilityService $service;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->service = new AvailabilityService($pdo);
    }

    public function getAvailability(string $date, ?int $clubId = null, ?int $sportId = null): array
    {
        return $this->service->getAvailabilityForDate($date, $clubId, $sportId);
    }
}