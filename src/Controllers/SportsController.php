<?php
declare(strict_types=1);

namespace App\Controllers;

use PDO;

class SportsController
{
    public function __construct(private PDO $pdo) {}

    public function index(): array
    {
        $stmt = $this->pdo->query("SELECT id, name FROM sports ORDER BY id");
        return $stmt->fetchAll();
    }
}